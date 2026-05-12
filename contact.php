<?php
// contact.php — Public enquiry form handler
// NOTE: Requires BUSINESS_EMAIL and BUSINESS_NAME defined in config.php

require_once __DIR__ . '/config.php';
require_once ROOT_DIR . '/includes/helpers.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$name    = trim($_POST['name']    ?? '');
$phone   = trim($_POST['phone']   ?? '');
$email   = trim($_POST['email']   ?? '');
$message = trim($_POST['message'] ?? '');

// Validate required fields
if ($name === '' || $message === '') {
    echo json_encode(['success' => false, 'message' => 'Name and message are required.']);
    exit;
}

// Validate email if provided
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
    exit;
}

// Strip newlines from all header-injectable fields to prevent header injection
$name    = str_replace(["\r", "\n"], ' ', $name);
$email   = str_replace(["\r", "\n"], '', $email);

$to      = BUSINESS_EMAIL;
$subject = 'New Enquiry from ' . $name . ' — ' . BUSINESS_NAME;

$body  = "You have received a new enquiry via the website.\r\n\r\n";
$body .= "Name:    " . $name    . "\r\n";
$body .= "Phone:   " . ($phone   ?: 'Not provided') . "\r\n";
$body .= "Email:   " . ($email   ?: 'Not provided') . "\r\n\r\n";
$body .= "Message:\r\n" . $message . "\r\n";

$headers  = "From: " . BUSINESS_EMAIL . "\r\n";
$headers .= "Reply-To: " . ($email ?: BUSINESS_EMAIL) . "\r\n";
$headers .= "X-Mailer: PHP/" . phpversion();

$sent = mail($to, $subject, $body, $headers);

if ($sent) {
    logInfo('CONTACT', 'Enquiry received', ['name' => $name, 'email' => $email]);
    echo json_encode(['success' => true, 'message' => 'Thank you! We will be in touch soon.']);
} else {
    logError('CONTACT', 'Failed to send enquiry email', ['name' => $name, 'email' => $email]);
    echo json_encode(['success' => false, 'message' => 'Could not send your enquiry. Please try again later.']);
}
