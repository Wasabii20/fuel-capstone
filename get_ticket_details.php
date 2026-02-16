<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid ticket ID']);
    exit;
}

$ticket_id = intval($_GET['id']);

try {
    $stmt = $pdo->prepare("SELECT t.*, d.full_name FROM trip_tickets t LEFT JOIN drivers d ON t.driver_id = d.driver_id WHERE t.id = ?");
    $stmt->execute([$ticket_id]);
    $ticket = $stmt->fetch();
    
    if ($ticket) {
        echo json_encode(['success' => true, 'ticket' => $ticket]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Ticket not found']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
