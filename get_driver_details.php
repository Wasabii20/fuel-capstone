<?php
include("db_connect.php");

header('Content-Type: application/json');

if(!isset($_GET['driver_id'])) {
    echo json_encode(['success' => false, 'message' => 'Driver ID required']);
    exit;
}

$driver_id = intval($_GET['driver_id']);

try {
    $query = "SELECT driver_id, full_name, license_no FROM drivers WHERE driver_id = ? AND status = 'active'";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$driver_id]);
    $row = $stmt->fetch();
    
    if($row) {
        echo json_encode([
            'success' => true,
            'full_name' => $row['full_name'],
            'license_no' => $row['license_no']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Driver not found']);
    }
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
