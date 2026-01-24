<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $additionalInfo = trim($_POST['additionalInfo'] ?? '');

    if (empty($id) || empty($name) || empty($phone)) {
        $response['message'] = '❌ ID, Name, and phone number are required.';
    } else {
        $sql = "UPDATE contacts SET name = ?, phone = ?, email = ?, address = ?, additional_info = ? WHERE id = ?";
        
        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param("sssssi", $name, $phone, $email, $address, $additionalInfo, $id);
            
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = "✅ Contact '" . htmlspecialchars($name) . "' updated successfully.";
            } else {
                $response['message'] = "❌ Error: Could not execute the query. " . $stmt->error;
            }
            $stmt->close();
        } else {
            $response['message'] = "❌ Error: Could not prepare the query. " . $mysqli->error;
        }
    }
} else {
    $response['message'] = 'Invalid request method.';
}

$mysqli->close();
echo json_encode($response);
?>