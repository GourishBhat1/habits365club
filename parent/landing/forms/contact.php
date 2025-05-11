<?php
// Set your receiving email address
$receiving_email_address = 'prashant@habits365club.com'; 

// Validate if form is submitted via POST
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    // Basic validation
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        die("All fields are required.");
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die("Invalid email format.");
    }

    // Construct email headers
    $headers = "From: $name <$email>" . "\r\n";
    $headers .= "Reply-To: $email" . "\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8";

    // Construct email body
    $email_content = "You have received a new message:\n\n";
    $email_content .= "From: $name\n";
    $email_content .= "Email: $email\n";
    $email_content .= "Subject: $subject\n\n";
    $email_content .= "Message:\n$message\n";

    // Send email
    if (mail($receiving_email_address, $subject, $email_content, $headers)) {
        echo "success"; // Used for frontend AJAX handling
    } else {
        echo "Error sending email.";
    }
} else {
    die("Unauthorized access.");
}
?>
