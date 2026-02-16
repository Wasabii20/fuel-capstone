<?php
session_start();
include 'db_connect.php';

header('Content-Type: text/html; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    die("<h1>Error: Not logged in</h1><p>Please <a href='login.php'>login</a> first</p>");
}

$current_user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Unknown';
$first_name = $_SESSION['first_name'] ?? '';
$last_name = $_SESSION['last_name'] ?? '';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification Debugger</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
            max-width: 1000px;
            margin: 0 auto;
        }
        .section {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            border-left: 4px solid #5d5dff;
        }
        .section h2 {
            margin-top: 0;
            color: #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #5d5dff;
            color: white;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .success {
            color: #22c55e;
        }
        .error {
            color: #ef4444;
        }
        .warning {
            color: #f59e0b;
        }
        pre {
            background: #f4f4f4;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
        }
        .json-output {
            background: #1e1e2d;
            color: #5d5dff;
            padding: 15px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
        }
    </style>
</head>
<body>

<h1>🔍 Notification System Debugger</h1>

<div class="section">
    <h2>👤 Current User Info</h2>
    <table>
        <tr>
            <th>Property</th>
            <th>Value</th>
        </tr>
        <tr>
            <td>User ID</td>
            <td><strong><?php echo $current_user_id; ?></strong></td>
        </tr>
        <tr>
            <td>Username</td>
            <td><?php echo htmlspecialchars($username); ?></td>
        </tr>
        <tr>
            <td>First Name</td>
            <td><?php echo htmlspecialchars($first_name) ?: '<span class="warning">NOT SET</span>'; ?></td>
        </tr>
        <tr>
            <td>Last Name</td>
            <td><?php echo htmlspecialchars($last_name) ?: '<span class="warning">NOT SET</span>'; ?></td>
        </tr>
        <tr>
            <td>Role</td>
            <td><?php echo htmlspecialchars($_SESSION['role'] ?? 'Unknown'); ?></td>
        </tr>
    </table>
</div>

<div class="section">
    <h2>📨 My Notifications</h2>
    <?php
    try {
        $query = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 20";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$current_user_id]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<p><strong>Total notifications: " . count($notifications) . "</strong></p>";
        
        if (count($notifications) > 0) {
            echo "<table>";
            echo "<tr>";
            echo "<th>ID</th>";
            echo "<th>Type</th>";
            echo "<th>Title</th>";
            echo "<th>Message</th>";
            echo "<th>Related ID</th>";
            echo "<th>Read?</th>";
            echo "<th>Created</th>";
            echo "</tr>";
            
            foreach ($notifications as $notif) {
                echo "<tr>";
                echo "<td>" . $notif['id'] . "</td>";
                echo "<td><strong>" . htmlspecialchars($notif['type']) . "</strong></td>";
                echo "<td>" . htmlspecialchars($notif['title']) . "</td>";
                echo "<td>" . htmlspecialchars(substr($notif['message'], 0, 100)) . (strlen($notif['message']) > 100 ? '...' : '') . "</td>";
                echo "<td>" . ($notif['related_id'] ?? '-') . "</td>";
                echo "<td>" . ($notif['is_read'] ? '<span class="success">✓ Yes</span>' : '<span class="error">✗ No</span>') . "</td>";
                echo "<td>" . htmlspecialchars($notif['created_at']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p class='error'><strong>❌ No notifications found!</strong></p>";
        }
    } catch (PDOException $e) {
        echo "<p class='error'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    ?>
</div>

<div class="section">
    <h2>🔄 Test API Response (get_notifications.php)</h2>
    <?php
    try {
        $query = "SELECT id, type, title, message, related_id, is_read, created_at 
                  FROM notifications 
                  WHERE user_id = ? 
                  ORDER BY created_at DESC 
                  LIMIT 10";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$current_user_id]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $count_query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND (is_read = FALSE OR is_read IS NULL)";
        $count_stmt = $pdo->prepare($count_query);
        $count_stmt->execute([$current_user_id]);
        $count_result = $count_stmt->fetch(PDO::FETCH_ASSOC);
        
        $response = [
            'notifications' => [],
            'unread_count' => $count_result['count'] ?? 0
        ];
        
        foreach ($notifications as $notif) {
            $response['notifications'][] = [
                'id' => (int)$notif['id'],
                'type' => $notif['type'],
                'title' => $notif['title'],
                'message' => $notif['message'],
                'related_id' => $notif['related_id'] ? (int)$notif['related_id'] : null,
                'is_read' => (bool)$notif['is_read'],
                'created_at' => date('M d, Y h:i A', strtotime($notif['created_at']))
            ];
        }
        
        echo "<div class='json-output'>";
        echo htmlspecialchars(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        echo "</div>";
        
    } catch (PDOException $e) {
        echo "<p class='error'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    ?>
</div>

<div class="section">
    <h2>👨‍💼 Admin Users (who can confirm requests)</h2>
    <?php
    try {
        $admin_query = "SELECT id, username, first_name, last_name FROM users WHERE role = 'admin' ORDER BY id";
        $admin_result = $pdo->query($admin_query);
        $admins = $admin_result->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($admins) > 0) {
            echo "<table>";
            echo "<tr><th>ID</th><th>Name</th><th>Username</th><th>Their Notifications</th></tr>";
            
            foreach ($admins as $admin) {
                $admin_id = $admin['id'];
                $notif_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ?");
                $notif_stmt->execute([$admin_id]);
                $notif_count = $notif_stmt->fetch()['count'];
                
                echo "<tr>";
                echo "<td>" . $admin_id . "</td>";
                echo "<td>" . htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']) . "</td>";
                echo "<td>" . htmlspecialchars($admin['username']) . "</td>";
                echo "<td>" . $notif_count . " notifications</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p class='error'>❌ No admin users found!</p>";
        }
    } catch (PDOException $e) {
        echo "<p class='error'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    ?>
</div>

<div class="section">
    <h2>🔍 Notification Flow Check</h2>
    <ol>
        <li>
            <strong>Step 1: Request Trip Ticket</strong>
            <p>When you submit a trip request in request_trip_ticket.php, it should:</p>
            <ul>
                <li>Create notifications for ALL admin users with type='trip_request'</li>
                <li>Use your user_id in the related_id field</li>
            </ul>
            <p><a href="request_trip_ticket.php" target="_blank">→ Go to Request Trip Ticket</a></p>
        </li>
        <li>
            <strong>Step 2: Admin Confirms Request</strong>
            <p>When admin clicks "Confirm & Create Ticket":</p>
            <ul>
                <li>It calls confirm_trip_request.php</li>
                <li>Creates a NEW notification for YOU with type='trip_approved'</li>
                <li>This page should reload and show the approval notification</li>
            </ul>
        </li>
        <li>
            <strong>Step 3: Check Notifications</strong>
            <p>Refresh this page to see if approval notifications appear in "My Notifications" above</p>
            <p><button onclick="location.reload()">🔄 Refresh This Page</button></p>
        </li>
    </ol>
</div>

<div class="section">
    <h2>🛠️ Quick Test Commands</h2>
    <p>Use browser console (F12) to test the API:</p>
    <pre><code>fetch('get_notifications.php')
    .then(r => r.json())
    .then(data => console.log(data))</code></pre>
</div>

</body>
</html>
