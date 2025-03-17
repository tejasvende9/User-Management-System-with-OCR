<?php
header('Content-Type: application/json');
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Validate required fields
    $required_fields = ['full_name', 'document_number', 'date_of_birth', 'address'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            throw new Exception("$field is required");
        }
    }

    // Handle document upload
    if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Document upload is required');
    }

    $file = $_FILES['document'];
    $fileName = time() . '_' . basename($file['name']);
    $uploadPath = '../uploads/' . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        throw new Exception('Failed to upload document');
    }

    // Insert user data
    $query = "INSERT INTO users (full_name, document_number, date_of_birth, address, document_image, created_at) 
              VALUES (:full_name, :document_number, :date_of_birth, :address, :document_image, NOW())";
    
    $stmt = $db->prepare($query);
    
    // Bind parameters
    $stmt->bindParam(':full_name', $_POST['full_name']);
    $stmt->bindParam(':document_number', $_POST['document_number']);
    $stmt->bindParam(':date_of_birth', $_POST['date_of_birth']);
    $stmt->bindParam(':address', $_POST['address']);
    $stmt->bindParam(':document_image', $fileName);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'User created successfully',
            'id' => $db->lastInsertId()
        ]);
    } else {
        // Delete uploaded file if database insert fails
        if (file_exists($uploadPath)) {
            unlink($uploadPath);
        }
        throw new Exception('Failed to create user');
    }

} catch (Exception $e) {
    // Delete uploaded file if it exists and there was an error
    if (isset($uploadPath) && file_exists($uploadPath)) {
        unlink($uploadPath);
    }
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
