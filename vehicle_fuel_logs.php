<?php
session_start();
require_once("db_connect.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit();
}

$username = $_SESSION['username'] ?? 'User';

// Get filters
$vehicle_filter = isset($_GET['vehicle']) ? (int)$_GET['vehicle'] : null;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : null;
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : null;

// Fetch vehicles
$vehicles = $pdo->query("SELECT id, vehicle_no, vehicle_type FROM vehicles ORDER BY vehicle_no")->fetchAll(PDO::FETCH_ASSOC);

// Build fuel logs query
$logs_sql = "SELECT 
    tt.id, tt.vehicle_plate_no, tt.ticket_date, tt.places_to_visit, tt.purpose,
    v.id as vehicle_id, v.vehicle_no, v.vehicle_type, tt.gas_balance_end, tt.gas_used_trip,
    u.username as driver_name
FROM trip_tickets tt
LEFT JOIN vehicles v ON UPPER(v.vehicle_no) = UPPER(tt.vehicle_plate_no)
LEFT JOIN users u ON tt.driver_id = u.id
WHERE 1=1";

$params = [];

if ($vehicle_filter) {
    $logs_sql .= " AND v.id = ?";
    $params[] = $vehicle_filter;
}

if ($date_from) {
    $logs_sql .= " AND tt.ticket_date >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $logs_sql .= " AND tt.ticket_date <= ?";
    $params[] = $date_to;
}

$logs_sql .= " ORDER BY tt.ticket_date DESC LIMIT 500";

$stmt = $pdo->prepare($logs_sql);
$stmt->execute($params);
$fuel_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$stats_sql = "SELECT 
    COUNT(*) as total_trips,
    SUM(CASE WHEN gas_balance_end > 50 THEN 1 ELSE 0 END) as high_fuel,
    SUM(CASE WHEN gas_balance_end BETWEEN 25 AND 50 THEN 1 ELSE 0 END) as medium_fuel,
    SUM(CASE WHEN gas_balance_end < 25 THEN 1 ELSE 0 END) as low_fuel
FROM trip_tickets";

$stats = $pdo->query($stats_sql)->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Fuel Logs - Admin Dashboard</title>
    <link rel="icon" href="ALBUM/favicon_io/favicon-32x32.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Poppins', sans-serif; background: #1e1e2d; color: #e2e2e2; display: flex; min-height: 100vh; }
        .wrapper { display: flex; width: 100%; }
        main { flex: 1; padding: 30px; overflow-y: auto; }
        .page-header { margin-bottom: 30px; }
        .page-header h1 { font-size: 2.5rem; color: #5d5dff; margin: 0 0 10px 0; }
        .page-header p { color: #a2a2c2; font-size: 1.1rem; margin: 0; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: rgba(93, 93, 255, 0.1); border: 1px solid rgba(93, 93, 255, 0.2); padding: 20px; border-radius: 12px; text-align: center; }
        .stat-card h3 { margin: 0 0 10px 0; color: #a2a2c2; font-size: 0.9rem; text-transform: uppercase; }
        .stat-card .value { font-size: 2rem; color: #5d5dff; font-weight: 700; }
        .filters { display: flex; gap: 15px; margin-bottom: 30px; flex-wrap: wrap; }
        .filter-group { display: flex; gap: 10px; align-items: center; }
        .filter-group label { color: #a2a2c2; font-weight: 600; }
        .filter-group input, .filter-group select { padding: 8px 12px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(93, 93, 255, 0.3); color: #e2e2e2; border-radius: 6px; }
        .btn-filter { padding: 8px 20px; background: #5d5dff; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; transition: all 0.3s; }
        .btn-filter:hover { background: #7d7dff; }
        .logs-table { width: 100%; border-collapse: collapse; background: transparent; }
        .logs-table th { background: rgba(93, 93, 255, 0.1); padding: 15px; text-align: left; font-weight: 600; border-bottom: 1px solid rgba(93, 93, 255, 0.2); color: #a2a2c2; text-transform: uppercase; font-size: 0.85rem; }
        .logs-table td { padding: 15px; border-bottom: 1px solid rgba(255, 255, 255, 0.05); color: #e2e2e2; }
        .logs-table tr:hover { background: rgba(93, 93, 255, 0.08); }
        .fuel-bar { display: inline-block; height: 25px; background: linear-gradient(90deg, #28a745, #ffc107); border-radius: 4px; min-width: 50px; color: white; font-size: 0.8rem; line-height: 25px; padding: 0 8px; font-weight: 600; }
        @media (max-width: 1200px) { .logs-table { font-size: 0.9rem; } }
    </style>
</head>
<body>
    <div class="wrapper">
        
        <main>
            <div class="page-header">
                <h1><i class="fas fa-gas-pump"></i> Vehicle Fuel Logs</h1>
                <p>Monitor fuel consumption and vehicle usage</p>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Trips</h3>
                    <div class="value"><?php echo number_format($stats['total_trips']); ?></div>
                </div>
                <div class="stat-card">
                    <h3>High Fuel Level</h3>
                    <div class="value" style="color: #27ae60;"><?php echo number_format($stats['high_fuel']); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Medium Fuel Level</h3>
                    <div class="value" style="color: #f1c40f;"><?php echo number_format($stats['medium_fuel']); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Low Fuel Level</h3>
                    <div class="value" style="color: #e74c3c;"><?php echo number_format($stats['low_fuel']); ?></div>
                </div>
            </div>

            <form method="GET" class="filters">
                <div class="filter-group">
                    <label for="vehicle">Vehicle:</label>
                    <select name="vehicle" id="vehicle">
                        <option value="">All Vehicles</option>
                        <?php foreach ($vehicles as $v): ?>
                            <option value="<?php echo $v['id']; ?>" <?php echo ($vehicle_filter == $v['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($v['vehicle_no'] . ' - ' . $v['vehicle_type']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="date_from">From:</label>
                    <input type="date" name="date_from" id="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                </div>

                <div class="filter-group">
                    <label for="date_to">To:</label>
                    <input type="date" name="date_to" id="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                </div>

                <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filter</button>
            </form>

            <table class="logs-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Vehicle</th>
                        <th>Destination</th>
                        <th>Purpose</th>
                        <th>Driver</th>
                        <th>Fuel Level</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fuel_logs as $log): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($log['ticket_date'])); ?></td>
                            <td><strong><?php echo htmlspecialchars($log['vehicle_no'] ?? $log['vehicle_plate_no']); ?></strong><br><small><?php echo htmlspecialchars($log['vehicle_type']); ?></small></td>
                            <td><?php echo htmlspecialchars($log['places_to_visit']); ?></td>
                            <td><?php echo htmlspecialchars($log['purpose']); ?></td>
                            <td><?php echo htmlspecialchars($log['driver_name'] ?? 'N/A'); ?></td>
                            <td><span class="fuel-bar"><?php echo htmlspecialchars($log['gas_balance_end']); ?>L</span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if (empty($fuel_logs)): ?>
                <div style="text-align: center; padding: 60px 20px; color: #a2a2c2;">
                    <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 20px; display: block;"></i>
                    <p>No fuel logs found</p>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
<?php
