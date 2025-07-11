<?php
// parent/dashboard.php

session_start();
require_once '../connection.php';
require_once '../vendor/autoload.php';
use Aws\S3\S3Client;

// DigitalOcean Spaces credentials (move to env/config for production)
$spaceName = 'habits-storage';
$region = 'blr1';
$accessKey = 'DO00AHJXYMZ2MY6ARBJL';
$secretKey = 'FmZaGYgkp1h8r1qo+rkr8qcgitZDpqSvMSr7g5V6z6g';

$s3 = new S3Client([
    'version' => 'latest',
    'region'  => $region,
    'endpoint' => "https://blr1.digitaloceanspaces.com",
    'credentials' => [
        'key'    => $accessKey,
        'secret' => $secretKey,
    ],
    'suppress_php_deprecation_warning' => true,
]);

function slugify($text) {
    return strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '_', $text)));
}

function uploadErrorMessage($error_code) {
    $errors = [
        UPLOAD_ERR_OK => "No error.",
        UPLOAD_ERR_INI_SIZE => "Exceeds php.ini size.",
        UPLOAD_ERR_FORM_SIZE => "Exceeds MAX_FILE_SIZE in form.",
        UPLOAD_ERR_PARTIAL => "Partial upload.",
        UPLOAD_ERR_NO_FILE => "No file uploaded.",
        UPLOAD_ERR_NO_TMP_DIR => "Missing temp folder.",
        UPLOAD_ERR_CANT_WRITE => "Failed to write to disk.",
        UPLOAD_ERR_EXTENSION => "Upload stopped by extension."
    ];
    return $errors[$error_code] ?? "Unknown error.";
}

// Check if the parent is authenticated
if (!isset($_SESSION['parent_username']) && !isset($_COOKIE['parent_username'])) {
    header("Location: index.php");
    exit();
}

// Retrieve parent username
$parent_username = $_SESSION['parent_username'] ?? $_COOKIE['parent_username'];

$database = new Database();
$conn = $database->getConnection();
if ($conn->ping() === false) {
    $conn = $database->getConnection(); // Reconnect if needed
}

$stmt = $conn->prepare("SELECT id, full_name, location FROM users WHERE username = ? AND role = 'parent'");
$stmt->bind_param("s", $parent_username);
$stmt->execute();
$result = $stmt->get_result();
$parent = $result->fetch_assoc();
$parent_id = $parent['id'] ?? null;
$parent_full_name = $parent['full_name'] ?? "Parent";
$stmt->close();

if (!$parent_id) {
    die("Parent not found.");
}

$galleryImages = [];
$query = "SELECT image_path, caption FROM gallery ORDER BY uploaded_at DESC"; // Latest images first
$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $galleryImages[] = [
            'image_path' => $row['image_path'],
            'caption' => $row['caption'] ?? ''
        ];
    }
    $stmt->close();
}

// Get current date
$current_date = date('Y-m-d');

// Fetch all available habits and their **assessment status** & **upload status**
$query = "
    SELECT h.id, h.title, h.description, h.upload_type,
           COALESCE((
               SELECT eu.status FROM evidence_uploads eu 
               WHERE eu.habit_id = h.id 
               AND eu.parent_id = ? 
               AND DATE(eu.uploaded_at) = ? 
               ORDER BY eu.uploaded_at DESC LIMIT 1
           ), 'pending') AS assessment_status, 
           (SELECT COUNT(*) FROM evidence_uploads eu 
            WHERE eu.habit_id = h.id 
            AND eu.parent_id = ? 
            AND DATE(eu.uploaded_at) = ?) AS upload_count
    FROM habits h
";

$stmt = $conn->prepare($query);
$stmt->bind_param("isis", $parent_id, $current_date, $parent_id, $current_date);
$stmt->execute();
$habits = $stmt->get_result();
$stmt->close();

$habit_count = $habits->num_rows;

// Handle file uploads
$upload_success = "";
$error_message = "";
$upload_dir = "../admin/uploads/";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // ✅ Handle image uploads (already handled via $_FILES)
    foreach ($_FILES['image_evidence']['name'] as $habit_id => $file_name) {
        if (!empty($file_name)) {
            $file_tmp = $_FILES['image_evidence']['tmp_name'][$habit_id];
            $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
            $habit_title = 'habit';
            $stmt = $conn->prepare("SELECT title FROM habits WHERE id = ?");
            $stmt->bind_param("i", $habit_id);
            $stmt->execute();
            $stmt->bind_result($habit_title_raw);
            if ($stmt->fetch()) {
                $habit_title = slugify($habit_title_raw);
            }
            $stmt->close();
            $new_file_name = "evidence_{$parent_id}_{$habit_id}_{$habit_title}_" . time() . "." . $file_ext;
            $object_key = "evidence_uploads/" . $new_file_name;

            try {
                $result = $s3->putObject([
                    'Bucket' => $spaceName,
                    'Key'    => $object_key,
                    'Body'   => fopen($file_tmp, 'rb'),
                    'ACL'    => 'public-read',
                    'ContentType' => mime_content_type($file_tmp),
                ]);
                // Store only the object key in DB
                $file_path = $object_key;
            } catch (Exception $e) {
                $error_message = "Image upload failed: " . $e->getMessage();
                continue;
            }

            // Check if evidence already exists for today's date
            $stmt = $conn->prepare("SELECT COUNT(*) FROM evidence_uploads WHERE parent_id = ? AND habit_id = ? AND DATE(uploaded_at) = ?");
            $stmt->bind_param("iis", $parent_id, $habit_id, $current_date);
            $stmt->execute();
            $stmt->bind_result($existing_count);
            $stmt->fetch();
            $stmt->close();

            if ($existing_count > 0) {
                continue;
            }

            $stmt = $conn->prepare("
                INSERT INTO evidence_uploads (
                    parent_id, 
                    habit_id, 
                    file_path, 
                    file_type, 
                    status, 
                    points, 
                    uploaded_at
                ) 
                VALUES (
                    ?, 
                    ?, 
                    ?, 
                    'image', 
                    'pending',
                    0,
                    NOW()
                )
            ");
            $stmt->bind_param("iis", $parent_id, $habit_id, $file_path);
            $stmt->execute();
            $stmt->close();
        }
    }
 
    // ✅ Handle audio uploads (from base64 string)
    if (!empty($_POST['recorded_audio'])) {
        foreach ($_POST['recorded_audio'] as $habit_id => $audioData) {
            if (strpos($audioData, 'data:audio/webm;base64,') === 0) {
                $audioData = str_replace('data:audio/webm;base64,', '', $audioData);
                $audioBinary = base64_decode($audioData);

                $habit_title = 'habit';
                $stmt = $conn->prepare("SELECT title FROM habits WHERE id = ?");
                $stmt->bind_param("i", $habit_id);
                $stmt->execute();
                $stmt->bind_result($habit_title_raw);
                if ($stmt->fetch()) {
                    $habit_title = slugify($habit_title_raw);
                }
                $stmt->close();
                $file_name = "audio_{$parent_id}_{$habit_id}_{$habit_title}_" . time() . ".webm";
                $object_key = "evidence_uploads/" . $file_name;

                // Upload audio to Spaces
                try {
                    $result = $s3->putObject([
                        'Bucket' => $spaceName,
                        'Key'    => $object_key,
                        'Body'   => $audioBinary,
                        'ACL'    => 'public-read',
                        'ContentType' => 'audio/webm',
                    ]);
                    $file_path = $object_key;
                } catch (Exception $e) {
                    $error_message = "Audio upload failed: " . $e->getMessage();
                    continue;
                }

                // Check if evidence already exists for today's date
                $stmt = $conn->prepare("SELECT COUNT(*) FROM evidence_uploads WHERE parent_id = ? AND habit_id = ? AND DATE(uploaded_at) = ?");
                $stmt->bind_param("iis", $parent_id, $habit_id, $current_date);
                $stmt->execute();
                $stmt->bind_result($existing_count);
                $stmt->fetch();
                $stmt->close();

                if ($existing_count > 0) {
                    continue;
                }

                $stmt = $conn->prepare("
                    INSERT INTO evidence_uploads (
                        parent_id, 
                        habit_id, 
                        file_path, 
                        file_type, 
                        status, 
                        points, 
                        uploaded_at
                    ) 
                    VALUES (
                        ?, 
                        ?, 
                        ?, 
                        'audio', 
                        'pending',
                        0,
                        NOW()
                    )
                ");
                $stmt->bind_param("iis", $parent_id, $habit_id, $file_path);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    // Handle video uploads
    foreach ($_FILES['video_evidence']['name'] as $habit_id => $file_name) {
        if (!empty($file_name)) {
            $file_tmp = $_FILES['video_evidence']['tmp_name'][$habit_id];
            $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
            $habit_title = 'habit';
            $stmt = $conn->prepare("SELECT title FROM habits WHERE id = ?");
            $stmt->bind_param("i", $habit_id);
            $stmt->execute();
            $stmt->bind_result($habit_title_raw);
            if ($stmt->fetch()) {
                $habit_title = slugify($habit_title_raw);
            }
            $stmt->close();
            $new_file_name = "video_{$parent_id}_{$habit_id}_{$habit_title}_" . time() . "." . $file_ext;
            $object_key = "evidence_uploads/" . $new_file_name;

            // Upload video to Spaces
            try {
                $result = $s3->putObject([
                    'Bucket' => $spaceName,
                    'Key'    => $object_key,
                    'Body'   => fopen($file_tmp, 'rb'),
                    'ACL'    => 'public-read',
                    'ContentType' => mime_content_type($file_tmp),
                ]);
                $file_path = $object_key;
            } catch (Exception $e) {
                $error_message = "Video upload failed: " . $e->getMessage();
                continue;
            }

            // Check if evidence already exists for today's date
            $stmt = $conn->prepare("SELECT COUNT(*) FROM evidence_uploads WHERE parent_id = ? AND habit_id = ? AND DATE(uploaded_at) = ?");
            $stmt->bind_param("iis", $parent_id, $habit_id, $current_date);
            $stmt->execute();
            $stmt->bind_result($existing_count);
            $stmt->fetch();
            $stmt->close();

            if ($existing_count > 0) {
                continue;
            }

            $stmt = $conn->prepare("
                INSERT INTO evidence_uploads (
                    parent_id, 
                    habit_id, 
                    file_path, 
                    file_type, 
                    status, 
                    points, 
                    uploaded_at
                ) 
                VALUES (
                    ?, 
                    ?, 
                    ?, 
                    'video', 
                    'pending',
                    0,
                    NOW()
                )
            ");
            $stmt->bind_param("iis", $parent_id, $habit_id, $file_path);
            $stmt->execute();
            $stmt->close();
        }
    }
 
    header('Location: dashboard.php');
}
?>

<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Parent Dashboard - Habits365Club</title>

    <link rel="stylesheet" href="css/app-light.css" id="lightTheme">
    <style>
        .file-input-label {
            display: inline-block;
            padding: 10px 14px;
            background-color: #007bff;
            color: white;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 5px;
            text-align: center;
        }
        .file-input {
            display: none;
        }
        .alert {
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }
        .badge-pending { background-color: #ffc107; }
        .badge-uploaded { background-color: #007bff; }
        .badge-approved { background-color: #28a745; }
        .badge-rejected { background-color: #dc3545; }

        /* Card-based UI for mobile-friendly design */
        .habit-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            background: #fff;
        }
        .habit-title {
            font-size: 16px;
            font-weight: bold;
        }
        .habit-desc {
            font-size: 14px;
            color: #666;
        }

        .welcome-text {
    font-size: 22px;
    font-weight: bold;
    color: #333;
    margin-bottom: 20px;
}

.preloader-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(255, 255, 255, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}

.preloader {
    text-align: center;
}

.spinner {
    border: 4px solid rgba(0, 0, 0, 0.1);
    border-left-color: #007bff;
    border-radius: 50%;
    width: 50px;
    height: 50px;
    animation: spin 1s linear infinite;
    margin: auto;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.carousel-img-wrapper {
    width: 100%;
    height: 300px; /* or whatever fixed height you prefer */
    overflow: hidden;
}

.carousel-img-wrapper img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.recording-indicator {
    display: inline-block;
    width: 12px;
    height: 12px;
    margin-left: 10px;
    border-radius: 50%;
    background-color: red;
    animation: pulse 1s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.5); opacity: 0.6; }
    100% { transform: scale(1); opacity: 1; }
}

.disabled-upload {
    opacity: 0.5;
    pointer-events: none;
}
    </style>
</head>
<body class="vertical light">
<div class="wrapper">
    
    <!-- Include Navbar -->
    <?php include 'includes/navbar.php'; ?>

    <!-- Include Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main role="main" class="main-content">

    <div class="container-fluid">
        <?php
        // Fetch latest notice for this parent
        if (!empty($parent['location'])) {
            $notice_stmt = $conn->prepare("
                SELECT title, created_at FROM notices 
                WHERE location = ? OR location IS NULL 
                ORDER BY created_at DESC LIMIT 1
            ");
            $notice_stmt->bind_param("s", $parent['location']);
        } else {
            $notice_stmt = $conn->prepare("
                SELECT title, created_at FROM notices 
                WHERE location IS NULL 
                ORDER BY created_at DESC LIMIT 1
            ");
        }
        $notice_stmt->execute();
        $latest_notice = $notice_stmt->get_result()->fetch_assoc();
        $notice_stmt->close();
        ?>
        <h2 class="page-title">Welcome, <?php echo htmlspecialchars($parent_full_name); ?>!</h2>
        <?php if (!empty($latest_notice)): ?>
        <div id="noticeToast" class="toast show position-fixed bottom-0 end-0 m-3 bg-warning text-dark shadow" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="false" style="z-index: 1055; max-width: 300px;">
          <div class="toast-header">
            <strong class="me-auto">📢 New Notice</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
          </div>
          <div class="toast-body small">
            <?php echo htmlspecialchars($latest_notice['title']); ?><br>
            <a href="notices.php" class="btn btn-sm btn-outline-dark mt-2">View All Notices</a>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($upload_success): ?>
            <div class="alert alert-success"><?php echo $upload_success; ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <?php if (!empty($galleryImages)): ?>
<div id="galleryCarousel" class="carousel slide" data-bs-ride="carousel">
    <div class="carousel-inner">
        <?php foreach ($galleryImages as $index => $image): ?>
            <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
    <div class="carousel-img-wrapper">
            <img src="<?php echo htmlspecialchars($image['image_path']); ?>" class="d-block w-100" alt="Gallery Image">
            <?php if (!empty($image['caption'])): ?>
                <div class="carousel-caption d-none d-md-block">
                    <p><?php echo htmlspecialchars($image['caption']); ?></p>
                </div>
            <?php endif; ?>
    </div>
</div>
        <?php endforeach; ?>
    </div>

    <!-- Carousel Controls -->
    <button class="carousel-control-prev" type="button" data-bs-target="#galleryCarousel" data-bs-slide="prev">
        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Previous</span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#galleryCarousel" data-bs-slide="next">
        <span class="carousel-control-next-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Next</span>
    </button>
</div>

<?php else: ?>
    <p class="text-muted text-center">No images available.</p>
<?php endif; ?>

<br>
<br>

        <div class="card shadow mb-4">
            <div class="card-header">
                <strong>Upload Habits</strong>
            </div>
            <div class="card-body">
                <form action="" method="POST" enctype="multipart/form-data">
                    <?php if ($habit_count > 0): ?>
                        <div class="row">
                            <?php while ($habit = $habits->fetch_assoc()): ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="card habit-card p-3 mb-3 shadow-sm">
                                        <div class="habit-title font-weight-bold"><?php echo htmlspecialchars($habit['title']); ?></div>
                                        <div class="habit-desc text-muted"><?php echo htmlspecialchars($habit['description']); ?></div>

<?php
$allowed_types = explode(',', $habit['upload_type']);
?>

<!-- Image Upload Button -->
<?php if (in_array('image', $allowed_types)): ?>
    <label class="custom-file-upload mt-3">
        <input type="file" id="imageEvidence_<?php echo $habit['id']; ?>"
               name="image_evidence[<?php echo $habit['id']; ?>]"
               class="file-input" accept="image/*" capture="camera"
               onchange="handleFileSelection('<?php echo $habit['id']; ?>', 'image')">
        <span class="btn btn-outline-primary">📸 Capture Image</span>
        <span id="imageLabel_<?php echo $habit['id']; ?>" class="file-name text-muted ml-2"></span>
    </label>
<?php endif; ?>

<!-- Audio Upload Button -->
<?php if (in_array('audio', $allowed_types)): ?>
    <div class="audio-recorder mt-3" data-habit-id="<?php echo $habit['id']; ?>">
        <button type="button" class="btn btn-outline-success start-recording">🎙️ Start Recording</button>
        <button type="button" class="btn btn-outline-danger stop-recording" disabled>⏹ Stop</button>
        <audio controls style="display:none;" class="audio-preview mt-2"></audio>
        <div class="recording-status ml-2" style="display:none;"></div>
        <input type="hidden" name="recorded_audio[<?php echo $habit['id']; ?>]" class="recorded-audio-blob">
    </div>
<?php endif; ?>

<!-- Video Upload Button -->
<?php if (in_array('video', $allowed_types)): ?>
    <label class="custom-file-upload mt-3">
        <input type="file" id="videoEvidence_<?php echo $habit['id']; ?>"
               name="video_evidence[<?php echo $habit['id']; ?>]"
               class="file-input" accept="video/*" capture="camcorder"
               onchange="handleFileSelection('<?php echo $habit['id']; ?>', 'video')">
        <span class="btn btn-outline-secondary">🎥 Capture Video</span>
        <span id="videoLabel_<?php echo $habit['id']; ?>" class="file-name text-muted ml-2"></span>
    </label>
<?php endif; ?>
<script>
function disableAudioRecording(habitId) {
    const recorder = document.querySelector(`.audio-recorder[data-habit-id="${habitId}"]`);
    if (recorder) {
        const startBtn = recorder.querySelector('.start-recording');
        const stopBtn = recorder.querySelector('.stop-recording');
        startBtn.disabled = true;
        stopBtn.disabled = true;
    }
}

function enableAudioRecording(habitId) {
    const recorder = document.querySelector(`.audio-recorder[data-habit-id="${habitId}"]`);
    if (recorder) {
        const startBtn = recorder.querySelector('.start-recording');
        startBtn.disabled = false;
    }
}

function handleFileSelection(habitId, type) {
    const imageInput = document.getElementById(`imageEvidence_${habitId}`);
    const imageLabel = document.getElementById(`imageLabel_${habitId}`);
    const videoInput = document.getElementById(`videoEvidence_${habitId}`);
    const videoLabel = document.getElementById(`videoLabel_${habitId}`);
    const audioRecorder = document.querySelector(`.audio-recorder[data-habit-id="${habitId}"]`);
    const audioHiddenInput = audioRecorder ? audioRecorder.querySelector('.recorded-audio-blob') : null;

    if (type === 'image' && imageInput) {
        if (imageInput.files.length > 0) {
            // Disable and reset audio and video
            if (audioRecorder) {
                disableAudioRecording(habitId);
                if (audioHiddenInput) audioHiddenInput.value = '';
                const audioPreview = audioRecorder.querySelector('.audio-preview');
                if (audioPreview) {
                    audioPreview.src = '';
                    audioPreview.style.display = 'none';
                }
            }
            if (videoInput) {
                videoInput.disabled = true;
                videoInput.value = '';
                if (videoLabel) videoLabel.textContent = '';
            }
            imageLabel.textContent = "📸 Image Selected";
        } else {
            // Re-enable audio and video
            if (audioRecorder) enableAudioRecording(habitId);
            if (videoInput) videoInput.disabled = false;
            imageLabel.textContent = "";
        }
    }

    if (type === 'video' && videoInput) {
        if (videoInput.files.length > 0) {
            // Disable and reset image and audio
            if (imageInput) {
                imageInput.disabled = true;
                imageInput.value = '';
                if (imageLabel) imageLabel.textContent = '';
            }
            if (audioRecorder) {
                disableAudioRecording(habitId);
                if (audioHiddenInput) audioHiddenInput.value = '';
                const audioPreview = audioRecorder.querySelector('.audio-preview');
                if (audioPreview) {
                    audioPreview.src = '';
                    audioPreview.style.display = 'none';
                }
            }
            videoLabel.textContent = "🎥 Video Selected";
        } else {
            // Re-enable image and audio
            if (imageInput) imageInput.disabled = false;
            if (audioRecorder) enableAudioRecording(habitId);
            videoLabel.textContent = "";
        }
    }
}
</script>

                                        <div class="mt-3">
                                            <!-- Upload Status -->
                                            <?php 
                                            $upload_status = ($habit['upload_count'] > 0) ? 'Uploaded' : 'Upload Pending';
                                            $upload_badge_class = ($habit['upload_count'] > 0) ? 'badge-success' : 'badge-warning';
                                            ?>
                                            <span class="badge text-white <?php echo $upload_badge_class; ?>">
                                                <?php echo $upload_status; ?>
                                            </span>

                                            <!-- Assessment Status -->
                                            <?php 
                                            $assessment_status = ucfirst($habit['assessment_status']);
                                            $status_classes = [
                                                'approved'  => 'badge-success',
                                                'pending'   => 'badge-warning',
                                                'rejected'  => 'badge-danger'
                                            ];
                                            $assessment_badge_class = $status_classes[$habit['assessment_status']] ?? 'badge-warning';
                                            ?>
                                            <span class="badge text-white <?php echo $assessment_badge_class; ?>">
                                                Assessment: <?php echo $assessment_status; ?>
                                            </span>
                                        </div>

                                        <!-- Submit Button (Hidden if Uploaded) -->
                                        <?php if ($habit['upload_count'] == 0): ?>
                                            <button type="submit" class="btn btn-primary btn-sm mt-3 btn-block">Submit Evidence</button>
                                        <?php endif; ?>

                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center">No habits found.</p>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</main>
</div>

<!-- Include Footer -->
<?php include 'includes/footer.php'; ?>
<script src="js/preloader.js"></script>
<script>
document.querySelectorAll('.audio-recorder').forEach(recorder => {
    let mediaRecorder;
    let chunks = [];
    const startBtn = recorder.querySelector('.start-recording');
    const stopBtn = recorder.querySelector('.stop-recording');
    const preview = recorder.querySelector('.audio-preview');
    const hiddenInput = recorder.querySelector('.recorded-audio-blob');
    const statusIndicator = recorder.querySelector('.recording-status');
    const habitId = recorder.dataset.habitId;

    navigator.mediaDevices.getUserMedia({ audio: true }).then(stream => {
        mediaRecorder = new MediaRecorder(stream);
        mediaRecorder.ondataavailable = e => {
            if (e.data.size > 0) chunks.push(e.data);
        };
        mediaRecorder.onstop = () => {
            const blob = new Blob(chunks, { type: 'audio/webm' });
            const url = URL.createObjectURL(blob);
            preview.src = url;
            preview.style.display = 'block';
            preview.controls = true;

            // Disable and reset image and video
            const imageInput = document.getElementById(`imageEvidence_${habitId}`);
            const imageLabel = document.getElementById(`imageLabel_${habitId}`);
            if (imageInput) {
                imageInput.disabled = true;
                imageInput.value = '';
                if (imageLabel) imageLabel.textContent = '';
            }
            const videoInput = document.getElementById(`videoEvidence_${habitId}`);
            const videoLabel = document.getElementById(`videoLabel_${habitId}`);
            if (videoInput) {
                videoInput.disabled = true;
                videoInput.value = '';
                if (videoLabel) videoLabel.textContent = '';
            }

            // Convert blob to base64 and store in hidden input
            const reader = new FileReader();
            reader.readAsDataURL(blob);
            reader.onloadend = function () {
                hiddenInput.value = reader.result;
            };
        };

        startBtn.onclick = () => {
            chunks = [];
            mediaRecorder.start();
            startBtn.disabled = true;
            stopBtn.disabled = false;
            statusIndicator.innerHTML = '<span class="recording-indicator"></span>';
            statusIndicator.style.display = 'inline-block';

            // Disable image upload when starting recording
            const imageInput = document.getElementById(`imageEvidence_${habitId}`);
            if (imageInput) {
                imageInput.disabled = true;
                imageInput.value = ''; // Clear any selected image
            }
        };

        stopBtn.onclick = () => {
            mediaRecorder.stop();
            startBtn.disabled = false;
            stopBtn.disabled = true;
            statusIndicator.innerHTML = '';
            statusIndicator.style.display = 'none';
        };
    }).catch(err => {
        console.error('Mic error:', err);
        startBtn.disabled = true;
        stopBtn.disabled = true;
    });
});
</script>

</body>
</html>
