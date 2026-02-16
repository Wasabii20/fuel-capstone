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
if (!$data || !isset($data['vehicleId'], $data['repairType'], $data['description'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

$vehicle_id = (int)$data['vehicleId'];
$repair_type = $data['repairType'];
$description = $data['description'];
$priority = $data['priority'] ?? 'medium';
$user_id = $_SESSION['user_id'];

// Validate priority
$allowed_priorities = ['low', 'medium', 'high', 'urgent'];
if (!in_array($priority, $allowed_priorities)) {
    $priority = 'medium';
}

// Check if vehicle exists
$check_vehicle = $pdo->prepare("SELECT id FROM vehicles WHERE id = ?");
$check_vehicle->execute([$vehicle_id]);
if (!$check_vehicle->fetch()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Vehicle not found']);
    exit();
}

// Create vehicle_repairs table if it doesn't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `vehicle_repairs` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `vehicle_id` int(11) NOT NULL,
        `user_id` int(11) NOT NULL,
        `repair_type` varchar(100) NOT NULL,
        `description` text NOT NULL,
        `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
        `status` enum('pending','approved','in_progress','completed','rejected') DEFAULT 'pending',
        `requested_date` timestamp DEFAULT current_timestamp(),
        `approval_date` datetime DEFAULT NULL,
        `completed_date` datetime DEFAULT NULL,
        `approved_by` int(11) DEFAULT NULL,
        `notes` text DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_vehicle_id` (`vehicle_id`),
        KEY `idx_user_id` (`user_id`),
        KEY `idx_status` (`status`),
        KEY `idx_priority` (`priority`),
        CONSTRAINT `fk_repair_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_repair_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
    // Table might already exist
}

// Insert repair request
try {
    $sql = "INSERT INTO vehicle_repairs 
            (vehicle_id, user_id, repair_type, description, priority) 
            VALUES (?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $vehicle_id,
        $user_id,
        $repair_type,
        $description,
        $priority
    ]);
    
    $repair_id = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Repair request submitted successfully',
        'repair_id' => $repair_id
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error submitting repair request: ' . $e->getMessage()
    ]);
}
?>
