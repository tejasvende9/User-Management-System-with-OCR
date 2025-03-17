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

    $id = $_POST['id'];
    $data = [
        'full_name' => $_POST['full_name'],
        'document_number' => $_POST['document_number'],
        'date_of_birth' => $_POST['date_of_birth'],
        'address' => $_POST['address']
    ];

    // Handle document update if provided
    if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['document'];
        $fileName = time() . '_' . basename($file['name']);
        $uploadPath = '../uploads/' . $fileName;

        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            $data['document_image'] = $fileName;

            // Delete old document if exists
            $stmt = $db->prepare("SELECT document_image FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $oldImage = $stmt->fetchColumn();
            if ($oldImage && file_exists('../uploads/' . $oldImage)) {
                unlink('../uploads/' . $oldImage);
            }
        }
    }

    // Build update query
    $setClauses = [];
    foreach ($data as $key => $value) {
        $setClauses[] = "$key = :$key";
    }
    $query = "UPDATE users SET " . implode(', ', $setClauses) . " WHERE id = :id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id);
    foreach ($data as $key => $value) {
        $stmt->bindParam(':' . $key, $data[$key]);
    }
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'User updated successfully'
        ]);
    } else {
        throw new Exception('Failed to update user');
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
