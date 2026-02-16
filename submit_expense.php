<?php
session_start();
include("db_connect.php");

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents("php://input"), true);

// Validate input
if (!$data || !isset($data['vehicleId'], $data['expenseType'], $data['amount'], $data['expenseDate'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

$vehicle_id = (int)$data['vehicleId'];
$expense_type = $data['expenseType'];
$amount = (float)$data['amount'];
$expense_date = $data['expenseDate'];
$description = $data['description'] ?? null;
$trip_ticket_id = $data['tripTicketId'] ?? null;
$user_id = $_SESSION['user_id'];

// Validate expense type
$allowed_types = ['fuel', 'repairs', 'gear_oil', 'lub_oil', 'grease', 'maintenance', 'parts', 'other'];
if (!in_array($expense_type, $allowed_types)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid expense type']);
    exit();
}

// Validate amount
if ($amount <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Amount must be greater than 0']);
    exit();
}

// Validate date
if (strtotime($expense_date) === false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
    exit();
}

// Check if vehicle exists
$check_vehicle = $pdo->prepare("SELECT id FROM vehicles WHERE id = ?");
$check_vehicle->execute([$vehicle_id]);
if (!$check_vehicle->fetch()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Vehicle not found']);
    exit();
}

// Insert expense record
try {
    $sql = "INSERT INTO vehicle_expenses 
            (vehicle_id, user_id, trip_ticket_id, expense_type, amount, expense_date, description) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $vehicle_id,
        $user_id,
        $trip_ticket_id,
        $expense_type,
        $amount,
        $expense_date,
        $description
    ]);
    
    $expense_id = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Expense recorded successfully',
        'expense_id' => $expense_id
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error recording expense: ' . $e->getMessage()
    ]);
}
?>
