<?php
session_start();
require_once 'db_connect.php';

// Check if this is an AJAX request or POST request for updating status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_ticket') {
    $ticket_id = isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : 0;
    
    if ($ticket_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid ticket ID']);
        exit;
    }
    
    try {
        // Update ticket status from Pending to Submitted
        $sql = "UPDATE trip_tickets SET status = 'Submitted' WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([$ticket_id]);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Ticket submitted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to submit ticket']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// If GET request, redirect back
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Location: Pending_reports.php');
    exit;
}
?>
