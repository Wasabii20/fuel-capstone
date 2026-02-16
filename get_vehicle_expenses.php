<?php
session_start();
include("db_connect.php");

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit();
}

// Get vehicle ID from query parameter
$vehicle_id = isset($_GET['vehicle_id']) ? (int)$_GET['vehicle_id'] : null;

if (!$vehicle_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Vehicle ID required']);
    exit();
}

try {
    // Fetch recent expenses for the vehicle (last 20)
    $sql = "SELECT 
            ve.id,
            ve.expense_type,
            ve.amount,
            ve.expense_date,
            ve.description,
            u.username as user_name,
            ve.created_at
        FROM vehicle_expenses ve
        LEFT JOIN users u ON ve.user_id = u.id
        WHERE ve.vehicle_id = ?
        ORDER BY ve.expense_date DESC, ve.created_at DESC
        LIMIT 20";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$vehicle_id]);
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate total expenses for the vehicle
    $total_sql = "SELECT SUM(amount) as total FROM vehicle_expenses WHERE vehicle_id = ?";
    $total_stmt = $pdo->prepare($total_sql);
    $total_stmt->execute([$vehicle_id]);
    $total_result = $total_stmt->fetch(PDO::FETCH_ASSOC);
    $total_expenses = $total_result['total'] ?? 0;
    
    echo json_encode([
        'success' => true,
        'expenses' => $expenses,
        'total' => (float)$total_expenses,
        'count' => count($expenses)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching expenses: ' . $e->getMessage()
    ]);
}
?>
