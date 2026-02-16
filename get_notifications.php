<?php
session_start();

// Any logged-in user can see their own notifications
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

include 'db_connect.php';

try {
    // Fetch all notifications for the user (not just unread)
    $query = "SELECT id, type, title, message, related_id, is_read, created_at 
              FROM notifications 
              WHERE user_id = ? 
              ORDER BY created_at DESC 
              LIMIT 10";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get unread count
    $count_query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND (is_read = FALSE OR is_read IS NULL)";
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute([$_SESSION['user_id']]);
    $count_result = $count_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Format response
    $response = [
        'notifications' => [],
        'unread_count' => $count_result['count'] ?? 0
    ];
    
    foreach ($notifications as $notif) {
        $response['notifications'][] = [
            'id' => $notif['id'],
            'type' => $notif['type'],
            'title' => $notif['title'],
            'message' => htmlspecialchars($notif['message']),
            'related_id' => $notif['related_id'],
            'is_read' => (bool)$notif['is_read'],
            'created_at' => date('M d, Y h:i A', strtotime($notif['created_at']))
        ];
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
