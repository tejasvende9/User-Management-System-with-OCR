<?php
header('Content-Type: application/json');
require_once '../config/database.php';

try {
    // Validate user ID
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        throw new Exception('User ID is required');
    }

    $database = new Database();
    $db = $database->getConnection();

    // Get user data
    $query = "SELECT * FROM users WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $_GET['id']);
    $stmt->execute();
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        echo json_encode([
            'success' => true,
            'user' => $user
        ]);
    } else {
        throw new Exception('User not found');
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
