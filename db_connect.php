<?php
$host = 'localhost';
$port = '3307'; // Match your SQL dump port
$db   = 'bfp_fuel_system'; 
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

// API endpoints for quick checks (used by client-side JS)
if (php_sapi_name() !== 'cli' && isset($_GET['action'])) {
     if (session_status() == PHP_SESSION_NONE) session_start();
     header('Content-Type: application/json');
     $action = $_GET['action'];

     if ($action === 'validate_admin_code') {
          $data = json_decode(file_get_contents('php://input'), true);
          $code = $data['code'] ?? '';
          $stored = $_SESSION['admin_code'] ?? 'Admin123';
          echo json_encode(['valid' => ($code !== '' && $code === $stored)]);
          exit;
     }

     if ($action === 'check_driver_trip') {
          $data = json_decode(file_get_contents('php://input'), true);
          $driver_id = $data['driver_id'] ?? null;
          if (!$driver_id) { echo json_encode(['is_on_trip' => false]); exit; }
          $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM trip_tickets WHERE driver_id = ? AND status IN ('Pending','Active')");
          $stmt->execute([$driver_id]);
          $res = $stmt->fetch();
          echo json_encode(['is_on_trip' => ($res['count'] > 0)]);
          exit;
     }

     if ($action === 'check_vehicle_status') {
          $data = json_decode(file_get_contents('php://input'), true);
          $vehicle_no = $data['vehicle_no'] ?? null;
          if (!$vehicle_no) { echo json_encode(['is_inactive' => false, 'is_on_trip' => false]); exit; }
          $stmt = $pdo->prepare("SELECT status FROM vehicles WHERE vehicle_no = ? LIMIT 1");
          $stmt->execute([$vehicle_no]);
          $veh = $stmt->fetch();
          $is_inactive = ($veh && ($veh['status'] ?? '') === 'inactive');

          $stmt2 = $pdo->prepare("SELECT COUNT(*) as count FROM trip_tickets WHERE vehicle_plate_no = ? AND status IN ('Pending','Active')");
          $stmt2->execute([$vehicle_no]);
          $r2 = $stmt2->fetch();

          echo json_encode(['is_inactive' => $is_inactive, 'is_on_trip' => ($r2['count'] > 0)]);
          exit;
     }
}
?>