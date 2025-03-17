<?php
header('Content-Type: application/json');
require_once '../config/database.php';

try {
    // Validate user ID
    if (!isset($_POST['id']) || empty($_POST['id'])) {
        throw new Exception('User ID is required');
    }

    $database = new Database();
    $db = $database->getConnection();

    // Get document image before deleting user
    $query = "SELECT document_image FROM users WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $_POST['id']);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('User not found');
    }

    // Delete user from database
    $query = "DELETE FROM users WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $_POST['id']);

    if ($stmt->execute()) {
        // Delete document image file if it exists
        if ($user['document_image'] && file_exists('../uploads/' . $user['document_image'])) {
            unlink('../uploads/' . $user['document_image']);
        }

        echo json_encode([
            'success' => true,
            'message' => 'User deleted successfully'
        ]);
    } else {
        throw new Exception('Failed to delete user');
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
