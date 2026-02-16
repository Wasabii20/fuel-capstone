<?php
session_start();
include 'db_connect.php';

// This page is for testing - shows notifications for the current user

if (!isset($_SESSION['user_id'])) {
    die("Not logged in");
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Unknown';

echo "<h2>Testing Notification System for User: $username (ID: $user_id)</h2>";
echo "<hr>";

// Check notifications
try {
    $query = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 20";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Notifications in Database:</h3>";
    echo "<p>Total: " . count($notifications) . "</p>";
    
    if (count($notifications) > 0) {
        echo "<table border='1' cellpadding='10'>";
        echo "<tr><th>ID</th><th>Type</th><th>Title</th><th>Message</th><th>Related ID</th><th>Is Read</th><th>Created At</th></tr>";
        
        foreach ($notifications as $notif) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($notif['id']) . "</td>";
            echo "<td>" . htmlspecialchars($notif['type']) . "</td>";
            echo "<td>" . htmlspecialchars($notif['title']) . "</td>";
            echo "<td>" . htmlspecialchars(substr($notif['message'], 0, 50)) . "...</td>";
            echo "<td>" . htmlspecialchars($notif['related_id']) . "</td>";
            echo "<td>" . ($notif['is_read'] ? 'YES' : 'NO') . "</td>";
            echo "<td>" . htmlspecialchars($notif['created_at']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: red;'>No notifications found for this user</p>";
    }
    
    // Test get_notifications.php endpoint
    echo "<h3>Testing get_notifications.php API:</h3>";
    echo "<pre>";
    
    $query2 = "SELECT id, type, title, message, related_id, is_read, created_at 
              FROM notifications 
              WHERE user_id = ? 
              ORDER BY created_at DESC 
              LIMIT 10";
    
    $stmt2 = $pdo->prepare($query2);
    $stmt2->execute([$user_id]);
    $notifications2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    $count_query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND (is_read = FALSE OR is_read IS NULL)";
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute([$user_id]);
    $count_result = $count_stmt->fetch(PDO::FETCH_ASSOC);
    
    $response = [
        'notifications' => [],
        'unread_count' => $count_result['count'] ?? 0
    ];
    
    foreach ($notifications2 as $notif) {
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
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    echo "</pre>";
    
    // Check all admin users
    echo "<h3>Admin Users:</h3>";
    $admin_query = "SELECT id, username, first_name, last_name FROM users WHERE role = 'admin'";
    $admin_result = $pdo->query($admin_query);
    $admins = $admin_result->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<ul>";
    foreach ($admins as $admin) {
        $admin_id = $admin['id'];
        $notif_count = $pdo->query("SELECT COUNT(*) as count FROM notifications WHERE user_id = $admin_id")->fetch()['count'];
        echo "<li>" . htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']) . " (ID: $admin_id) - Notifications: $notif_count</li>";
    }
    echo "</ul>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

<style>
table { border-collapse: collapse; }
td, th { border: 1px solid #ddd; padding: 8px; }
th { background-color: #f2f2f2; }
</style>
