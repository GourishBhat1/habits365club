<?php

// Replace with your actual receiving email address
$receiving_email_address = 'prashant@habits365club.com';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Sanitize and validate input
    $name = htmlspecialchars(strip_tags($_POST['name']));
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $subject = htmlspecialchars(strip_tags($_POST['subject']));
    $message = htmlspecialchars(strip_tags($_POST['message']));
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die('Invalid email address!');
    }
    
    // Email headers
    $headers = "From: $name <$email>\r\n";
    $headers .= "Reply-To: $email\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    // Email content
    $full_message = "From: $name\n";
    $full_message .= "Email: $email\n";
    $full_message .= "Subject: $subject\n\n";
    $full_message .= "Message:\n$message\n";
    
    // Send email
    if (mail($receiving_email_address, $subject, $full_message, $headers)) {
        echo "Your message has been sent. Thank you!";
    } else {
        echo "Error: Unable to send your message. Please try again later.";
    }

} else {
    echo "Invalid request.";
}
?>

