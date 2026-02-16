<?php
session_start();

// Only admins can confirm trip requests
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

include 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notif_id']) && isset($_POST['user_id'])) {
    $notif_id = intval($_POST['notif_id']);
    $user_id = intval($_POST['user_id']);
    $admin_id = $_SESSION['user_id'];
    
    try {
        // Delete the admin notification after accepting
        $delete_notif = "DELETE FROM notifications WHERE id = ? AND user_id = ?";
        $delete_stmt = $pdo->prepare($delete_notif);
        $delete_stmt->execute([$notif_id, $admin_id]);
        
        // Get admin name
        $admin_query = "SELECT first_name, last_name FROM users WHERE id = ?";
        $admin_stmt = $pdo->prepare($admin_query);
        $admin_stmt->execute([$admin_id]);
        $admin_info = $admin_stmt->fetch(PDO::FETCH_ASSOC);
        $admin_name = $admin_info['first_name'] . ' ' . $admin_info['last_name'];
        
        // Send notification to the user who requested the trip
        $user_notif_query = "INSERT INTO notifications (user_id, type, title, message, related_id, is_read, created_at) 
                            VALUES (?, ?, ?, ?, ?, 0, NOW())";
        $user_notif_stmt = $pdo->prepare($user_notif_query);
        $user_notif_stmt->execute([
            $user_id,
            'trip_approved',
            '✓ Trip Request Approved',
            "Admin {$admin_name} has approved your trip ticket request and is creating the ticket details now.",
            $notif_id
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Trip request confirmed']);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
}
?>
