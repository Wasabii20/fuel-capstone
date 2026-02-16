<?php
header('Content-Type: application/json');
session_start();
include("db_connect.php");

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get search parameter
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';

if (empty($searchTerm)) {
    echo json_encode(['success' => false, 'results' => []]);
    exit();
}

try {
    // Search by control_no or driver full_name
    $query = "SELECT 
                t.control_no, 
                t.ticket_date as date, 
                t.driver_id,
                t.status,
                d.full_name as driver_name
              FROM trip_tickets t
              LEFT JOIN drivers d ON t.driver_id = d.driver_id
              WHERE t.control_no LIKE :search 
                 OR d.full_name LIKE :search
              ORDER BY t.ticket_date DESC
              LIMIT 20";
    
    $stmt = $pdo->prepare($query);
    $searchPattern = '%' . $searchTerm . '%';
    $stmt->execute(['search' => $searchPattern]);
    $results = $stmt->fetchAll();

    $formattedResults = [];
    foreach ($results as $row) {
        $formattedResults[] = [
            'control_no' => htmlspecialchars($row['control_no']),
            'date' => date('M d, Y', strtotime($row['date'])),
            'driver_id' => htmlspecialchars($row['driver_id'] ?? ''),
            'driver_name' => htmlspecialchars($row['driver_name'] ?? 'Unknown'),
            'status' => htmlspecialchars($row['status'] ?? 'pending')
        ];
    }

    echo json_encode(['success' => true, 'results' => $formattedResults]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
