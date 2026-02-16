<?php
session_start();
require_once 'db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get ticket ID and status from request
$ticket_id = isset($_GET['id']) ? intval($_GET['id']) : null;
$new_status = isset($_GET['status']) ? trim($_GET['status']) : null;

if (!$ticket_id || !$new_status) {
    echo json_encode(['success' => false, 'message' => 'Invalid ticket ID or status']);
    exit();
}

// Allowed statuses
$allowed_statuses = ['Pending', 'Active', 'Submitted'];
if (!in_array($new_status, $allowed_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status value']);
    exit();
}

try {
    // Update trip ticket status
    $stmt = $pdo->prepare("UPDATE trip_tickets SET status = ? WHERE id = ?");
    $result = $stmt->execute([$new_status, $ticket_id]);
    
    if ($result) {
        // Log the action
        $log_stmt = $pdo->prepare("
            INSERT INTO user_logs (user_id, role, action, description, module, reference_id) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $log_stmt->execute([
            $_SESSION['user_id'],
            $_SESSION['role'] ?? 'user',
            'Update Trip Ticket Status',
            "Updated trip ticket status to: $new_status",
            'trip_tickets',
            $ticket_id
        ]);
        
        echo json_encode(['success' => true, 'message' => "Status updated to $new_status"]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update status']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
