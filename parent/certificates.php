<?php
// parent/certificates.php
session_start();
if (!isset($_SESSION['parent_id']) && !isset($_COOKIE['parent_id'])) {
    header("Location: index.php");
    exit();
}

require_once '../connection.php';
$database = new Database();
$db = $database->getConnection();

$parent_id = $_SESSION['parent_id'] ?? $_COOKIE['parent_id'];

// Fetch certificates for this parent
$query = "SELECT c.id, c.milestone, c.generated_at, c.certificate_path FROM certificates c WHERE c.user_id = ? ORDER BY c.generated_at DESC";
$stmt = $db->prepare($query);
$stmt->bind_param("i", $parent_id);
$stmt->execute();
$result = $stmt->get_result();
$certificates = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!doctype html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
    <title>My Certificates - Habits365Club</title>
    <link rel="stylesheet" href="css/app-light.css">
</head>
<body class="vertical light">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    <main role="main" class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">My Certificates</h2>
            <div class="card shadow mb-4">
                <div class="card-body">
                    <?php if (empty($certificates)): ?>
                        <p>You have not received any certificates yet.</p>
                    <?php else: ?>
                        <div class="row">
                        <?php foreach ($certificates as $cert):
                            $certUrl = '../certificates/' . htmlspecialchars($cert['certificate_path']);
                            $fullCertUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/' . $certUrl;
                        ?>
                            <div class="col-12 col-md-6 col-lg-4 mb-3">
                                <div class="card h-100 border-primary shadow-sm">
                                    <div class="card-body d-flex flex-column justify-content-between">
                                        <h5 class="card-title mb-2"><?php echo htmlspecialchars($cert['milestone']); ?></h5>
                                        <p class="card-text text-muted mb-2" style="font-size: 0.95em;">Issued: <?php echo htmlspecialchars($cert['generated_at']); ?></p>
                                        <a href="<?php echo $certUrl; ?>" target="_blank" class="btn btn-info btn-block mb-2">View Certificate</a>
                                        <div class="d-flex flex-wrap gap-1">
                                            <a href="https://wa.me/?text=<?php echo urlencode('Check out my certificate: ' . $fullCertUrl); ?>" target="_blank" class="btn btn-success btn-sm mr-1" title="Share on WhatsApp">
                                                <i class="fe fe-share-2"></i> WhatsApp
                                            </a>
                                            <button type="button" class="btn btn-outline-primary btn-sm mr-1 copy-link-btn" data-link="<?php echo $fullCertUrl; ?>" title="Copy Link">
                                                <i class="fe fe-link"></i> Copy Link
                                            </button>
                                            <button type="button" class="btn btn-outline-dark btn-sm native-share-btn" data-link="<?php echo $fullCertUrl; ?>" data-title="Certificate" data-text="Check out my certificate!" style="display:none;">
                                                <i class="fe fe-share-2"></i> Share
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>
<?php include 'includes/footer.php'; ?>
<script>
// Copy Link functionality
const copyBtns = document.querySelectorAll('.copy-link-btn');
copyBtns.forEach(btn => {
    btn.addEventListener('click', function() {
        const link = this.getAttribute('data-link');
        navigator.clipboard.writeText(link).then(() => {
            this.textContent = 'Copied!';
            setTimeout(() => { this.innerHTML = '<i class="fe fe-link"></i> Copy Link'; }, 1500);
        });
    });
});
// Native Share API
if (navigator.share) {
    document.querySelectorAll('.native-share-btn').forEach(btn => {
        btn.style.display = 'inline-block';
        btn.addEventListener('click', function() {
            navigator.share({
                title: this.getAttribute('data-title'),
                text: this.getAttribute('data-text'),
                url: this.getAttribute('data-link')
            });
        });
    });
}
</script>
</body>
</html>
