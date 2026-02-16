<?php
session_start();
include("db_connect.php");

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['count' => 0, 'error' => 'Not logged in']);
    exit;
}

try {
    // Count active trips
    $sql = "SELECT COUNT(*) as count FROM trip_tickets WHERE status = 'Active'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'count' => (int)$result['count'],
        'success' => true
    ]);
} catch (Exception $e) {
    echo json_encode([
        'count' => 0,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
