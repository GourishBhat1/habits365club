<?php
session_start();
require_once '../connection.php';

use Aws\S3\S3Client;
require '../vendor/autoload.php';

// AUTH
if (!isset($_SESSION['quality_username']) && !isset($_COOKIE['quality_username'])) {
    header("Location: index.php?message=unauthorized");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// S3 CONFIG
$spaceName = 'habits-storage';
$region = 'blr1';
$accessKey = 'DO801E9DEQHLEQVWGT62';
$secretKey = 'ySPcqWo6U/ebs2ELB6SyOuuHi78P7uZNshaXMxTy4Ao';

$s3 = new S3Client([
    'version' => 'latest',
    'region'  => $region,
    'endpoint' => "https://blr1.digitaloceanspaces.com",
    'credentials' => [
        'key'    => $accessKey,
        'secret' => $secretKey,
    ],
    'suppress_php_deprecation_warning' => true
]);

$success = $error = "";

// Location scoping
$quality_locations = $_SESSION['quality_locations'] ?? [];

/* -----------------------------
   FETCH STUDENT
-----------------------------*/
$user_id = $_GET['user_id'] ?? 0;

$stmt = $db->prepare("SELECT * FROM users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    die("Invalid user");
}

// Scope: only allow access to students at the quality user's assigned locations
if (!empty($quality_locations) && !in_array($user['location'], $quality_locations)) {
    die("Access denied: student is not at your assigned center.");
}

$student_standard = $user['standard'] ?? '';
$school_name = $user['school_name'] ?? '';
$course_name = $user['course_name'] ?? '';
$location = $user['location'] ?? '';

// Look up teacher from student's batch
$teacher_name_prefill = '';
if (!empty($user['batch_id'])) {
    $tstmt = $db->prepare("
        SELECT GROUP_CONCAT(DISTINCT u.full_name SEPARATOR ', ') AS teacher_name
        FROM batch_teachers bt
        JOIN users u ON bt.teacher_id = u.id
        WHERE bt.batch_id = ?
    ");
    $tstmt->bind_param("i", $user['batch_id']);
    $tstmt->execute();
    $tres = $tstmt->get_result()->fetch_assoc();
    if ($tres && $tres['teacher_name']) {
        $teacher_name_prefill = $tres['teacher_name'];
    }
    $tstmt->close();
}

if (!$user) {
    die("Invalid user");
}

// DAYS SINCE JOIN
$days_since = floor((time() - strtotime($user['created_at'])) / (60*60*24));

// DETERMINE ASSESSMENT TYPE
$assessment_no = ($days_since >= 28) ? 2 : 1;


/* -----------------------------
   HANDLE SUBMIT
------------------------------*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Handle PHP upload size exceeded (POST empty case)
    if (empty($_POST) && empty($_FILES)) {
        $error = "Upload failed: File exceeds server limit (increase upload_max_filesize & post_max_size).";
    }

    if (empty($error)) {
        $content = $_POST['content_covered'] ?? '';
        $progress = $_POST['progress_status'] ?? '';
        $remarks = $_POST['remarks'] ?? '';
        $subject = $_POST['subject'] ?? '';
        $teacher_name = $_POST['teacher_name'] ?? '';
        $course_completed = $_POST['course_completed'] ?? '';
        $next_followup = $_POST['next_followup'] ?? '';
        if ($subject === 'Other' && !empty($_POST['other_subject'])) {
            $subject = trim($_POST['other_subject']);
        }

        // DUPLICATE CHECK (user_id + assessment_number)
        $stmt = $db->prepare("
            SELECT id FROM quality_assessments 
            WHERE user_id=? AND assessment_number=?
        ");
        $stmt->bind_param("ii", $user_id, $assessment_no);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = "This assessment has already been submitted.";
        }
        $stmt->close();
    }

    $video_url = "";

    // VIDEO UPLOAD
    if (empty($error) && !empty($_FILES['video']['name'])) {

        $allowedTypes = ['video/mp4', 'video/quicktime', 'video/x-msvideo'];
        $maxSize = 200 * 1024 * 1024; // 200MB

        if (!in_array($_FILES['video']['type'], $allowedTypes)) {
            $error = "Invalid file type. Only MP4, MOV, AVI allowed.";
        }

        if ($_FILES['video']['size'] > $maxSize) {
            $error = "File too large. Max 200MB allowed.";
        }

        if (empty($error)) {
            $fileTmp = $_FILES['video']['tmp_name'];
            $fileName = time() . "_" . basename($_FILES['video']['name']);

            $key = "quality_assessments/" . $fileName;

            try {
                $result = $s3->putObject([
                    'Bucket' => $spaceName,
                    'Key'    => $key,
                    'Body'   => fopen($fileTmp, 'rb'),
                    'ACL'    => 'public-read',
                    'ContentType' => $_FILES['video']['type'],
                ]);

                $video_url = $result['ObjectURL'];

            } catch (Exception $e) {
                $error = "Upload failed: " . $e->getMessage();
            }
        }
    }

    // INSERT
    if (empty($error)) {

        $stmt = $db->prepare("
            INSERT INTO quality_assessments (
                user_id,
                child_name,
                mobile,
                course_start_date,
                subject,
                teacher_name,
                assessor_name,
                assessment_date,
                assessment_number,
                days_since_join,
                content_covered,
                progress_status,
                remarks,
                video_path,
                course_completed,
                next_followup
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");

        $assessor = $_SESSION['quality_username'] ?? $_COOKIE['quality_username'] ?? '';

        $today = date('Y-m-d');

        $next_followup_val = !empty($next_followup) ? $next_followup : null;

        $stmt->bind_param(
            "isssssssiissssss",
            $user_id,
            $user['full_name'],
            $user['phone'],
            $user['created_at'],
            $subject,
            $teacher_name,
            $assessor,
            $today,
            $assessment_no,
            $days_since,
            $content,
            $progress,
            $remarks,
            $video_url,
            $course_completed,
            $next_followup_val
        );

        if ($stmt->execute()) {
            $success = "Assessment saved successfully!";
        } else {
            $error = "DB Error: " . $stmt->error;
        }

        $stmt->close();
    }
}
?>

<!doctype html>
<html>
<head>
<?php include 'includes/header.php'; ?>
<title>Add Assessment</title>
</head>

<body class="vertical light">
<div class="wrapper">

<?php include 'includes/navbar.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<main class="main-content">
<div class="container-fluid">

<h2>Add Assessment</h2>

<div class="card shadow p-4">

<!-- STUDENT INFO -->
<div class="card bg-light mb-4">
<div class="card-body py-3">
<div class="row">
<div class="col-md-4">
<strong>Name:</strong> <?= htmlspecialchars($user['full_name']) ?><br>
<strong>Phone:</strong> <?= htmlspecialchars($user['phone']) ?><br>
<strong>Standard:</strong> <?= htmlspecialchars($student_standard ?: 'N/A') ?>
</div>
<div class="col-md-4">
<strong>Date of Joining:</strong> <?= date('d M Y', strtotime($user['created_at'])) ?><br>
<strong>Days Since Join:</strong> <?= $days_since ?><br>
<strong>Center:</strong> <?= htmlspecialchars($location ?: 'N/A') ?>
</div>
<div class="col-md-4">
<strong>Course:</strong> <?= htmlspecialchars($course_name ?: 'N/A') ?><br>
<strong>School:</strong> <?= htmlspecialchars($school_name ?: 'N/A') ?><br>
<strong>Assessment:</strong> <?= $assessment_no == 1 ? "15 Day" : "28 Day" ?>
</div>
</div>
</div>
</div>

<?php if($success): ?>
<div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>

<?php if($error): ?>
<div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">

<div class="form-group">
<label>Subject</label>
<select name="subject" id="subjectSelect" class="form-control" required>
<option value="">Select</option>
<option value="English">English</option>
<option value="Marathi">Marathi</option>
<option value="Hindi">Hindi</option>
<option value="Konkani">Konkani</option>
<option value="Maths">Maths</option>
<option value="Other">Other</option>
</select>
</div>

<div class="form-group" id="otherSubjectWrapper" style="display:none;">
<label>Enter Subject</label>
<input type="text" name="other_subject" id="otherSubject" class="form-control">
</div>

<div class="form-group">
<label>Teacher Name</label>
<input type="text" name="teacher_name" class="form-control" value="<?= htmlspecialchars($teacher_name_prefill) ?>">
</div>

<div class="form-group" id="topicCheckboxWrapper" style="display:none;">
<label>Topics Covered</label>
<div id="topicCheckboxes" class="mb-2"></div>
</div>

<div class="form-group">
<label>Content Covered</label>
<textarea name="content_covered" id="contentCovered" class="form-control" required></textarea>
</div>

<div class="form-group">
<label>Progress</label>
<select name="progress_status" class="form-control" required>
<option value="satisfactory">Satisfactory</option>
<option value="needs_improvement">Needs Improvement</option>
</select>
</div>

<div class="form-group">
<label>Remarks</label>
<textarea name="remarks" class="form-control"></textarea>
</div>

<div class="form-group">
<label>Upload Video</label>
<input type="file" name="video" class="form-control">
</div>

<div class="form-group">
<label>Course Status</label>
<select name="course_completed" class="form-control">
<option value="">Select</option>
<option value="active">Active</option>
<option value="completed">Completed</option>
<option value="break">Break</option>
<option value="stopped">Stopped</option>
</select>
</div>

<div class="form-group">
<label>Next Follow-up Date (optional)</label>
<input type="date" name="next_followup" class="form-control">
</div>

<button class="btn btn-primary">Submit Assessment</button>

</form>

</div>

</div>
</main>
</div>

<?php include 'includes/footer.php'; ?>

<script>
var topicsData = {
    English: [
        'Identifying the alphabets',
        'Phonic sounds',
        '2 letter words',
        '2 letter sight words',
        '3 letter words',
        '3 letter sight words',
        'Consonant blending',
        'Diagraph',
        'Ending blending P-1',
        'Magic e',
        'Vowel pairs ( a e i o u  )',
        'Sounds of Y',
        'Long Vowel Sound ( a e i o u  )',
        'Dipthongs / Tricky vowel',
        'Big sight words',
        'Soft Hard Sound',
        'Cat & Kite rule',
        'Ending blending P-2',
        'Bossy R',
        'Compound words',
        'Prefix / Surfix',
        'Syllable breaking',
        'Reading practice'
    ],
    Marathi: [
        '1. व्यंजन निर्मिती (Vyanjan Nirmiti)',
        '2. स्वर (Swar)',
        '3. विनामात्रा शब्द (Without Matra Words)',
        '4. मात्रा ओळख (Matra Identification)',
        '5. अ मात्रा',
        '6. आ मात्रा (ा)',
        '7. इ मात्रा (ि)',
        '8. ई मात्रा (ी)',
        '9. उ मात्रा (ु)',
        '10. ऊ मात्रा (ू)',
        '11. ए मात्रा (े)',
        '12. ऐ मात्रा (ै)',
        '13. ओ मात्रा (ो)',
        '14. औ मात्रा (ौ)',
        '15. अं मात्रा (ं)',
        '16. अः मात्रा (ः)',
        '17. जोडाक्षर / र प्रकार',
        '18. Story reading practice'
    ],
    Hindi: [
        '1. व्यंजन निर्मिती (Vyanjan Nirmiti)',
        '2. स्वर (Swar)',
        '3. विनामात्रा शब्द (Without Matra Words)',
        '4. मात्रा ओळख (Matra Identification)',
        '5. अ मात्रा',
        '6. आ मात्रा (ा)',
        '7. इ मात्रा (ि)',
        '8. ई मात्रा (ी)',
        '9. उ मात्रा (ु)',
        '10. ऊ मात्रा (ू)',
        '11. ए मात्रा (े)',
        '12. ऐ मात्रा (ै)',
        '13. ओ मात्रा (ो)',
        '14. औ मात्रा (ौ)',
        '15. अं मात्रा (ं)',
        '16. अः मात्रा (ः)',
        '17. जोडाक्षर / र प्रकार',
        '18. Story reading practice'
    ],
    Konkani: [
        '1. व्यंजन निर्मिती (Vyanjan Nirmiti)',
        '2. स्वर (Swar)',
        '3. विनामात्रा शब्द (Without Matra Words)',
        '4. मात्रा ओळख (Matra Identification)',
        '5. अ मात्रा',
        '6. आ मात्रा (ा)',
        '7. इ मात्रा (ि)',
        '8. ई मात्रा (ी)',
        '9. उ मात्रा (ु)',
        '10. ऊ मात्रा (ू)',
        '11. ए मात्रा (े)',
        '12. ऐ मात्रा (ै)',
        '13. ओ मात्रा (ो)',
        '14. औ मात्रा (ौ)',
        '15. अं मात्रा (ं)',
        '16. अः मात्रा (ः)',
        '17. जोडाक्षर / र प्रकार',
        '18. Story reading practice'
    ],
    Maths: [
        'Number recognition (1-100)',
        'Counting (forward & backward)',
        'Skip counting',
        'Addition (single digit)',
        'Addition (with carry)',
        'Subtraction (single digit)',
        'Subtraction (with borrowing)',
        'Multiplication tables',
        'Multiplication sums',
        'Division basics',
        'Shapes & geometry',
        'Patterns & sequences',
        'Time (reading clock)',
        'Money (coins & notes)',
        'Measurement (length, weight)',
        'Data handling',
        'Word problems',
        'Fractions basics',
        'Decimals basics'
    ]
};

function buildTopicCheckboxes(subject) {
    var container = document.getElementById('topicCheckboxes');
    var wrapper = document.getElementById('topicCheckboxWrapper');
    container.innerHTML = '';
    if (!subject || subject === 'Other' || subject === 'Maths') {
        wrapper.style.display = 'none';
        return;
    }
    var topics = topicsData[subject];
    if (!topics) { wrapper.style.display = 'none'; return; }
    wrapper.style.display = 'block';
    topics.forEach(function(topic, i) {
        var label = document.createElement('label');
        label.className = 'checkbox-inline mr-3 mb-2';
        label.style.fontWeight = 'normal';
        var cb = document.createElement('input');
        cb.type = 'checkbox';
        cb.className = 'topic-cb mr-1';
        cb.value = topic;
        cb.addEventListener('change', updateContentCovered);
        label.appendChild(cb);
        label.appendChild(document.createTextNode(topic));
        container.appendChild(label);
        container.appendChild(document.createElement('br'));
    });
}

function updateContentCovered() {
    var ta = document.getElementById('contentCovered');
    var checked = document.querySelectorAll('.topic-cb:checked');
    var items = [];
    checked.forEach(function(cb) { items.push(cb.value); });
    ta.value = items.join('\n');
}

document.getElementById('subjectSelect').addEventListener('change', function() {
    var val = this.value;
    var otherWrapper = document.getElementById('otherSubjectWrapper');
    if (val === 'Other') {
        otherWrapper.style.display = 'block';
    } else {
        otherWrapper.style.display = 'none';
        document.getElementById('otherSubject').value = '';
    }
    buildTopicCheckboxes(val);
    if (val !== 'Other') {
        document.getElementById('contentCovered').value = '';
    }
});
</script>

</body>
</html>