<?php
session_start();
require_once("db_connect.php");

$username = $_SESSION['username'] ?? 'User';
$user_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("SELECT first_name, last_name, profile_pic, role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
} catch(Exception $e) {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit();
}

$default_pic_src = 'ALBUM/default_profile.png';
$profile_pic_src = (!empty($user['profile_pic']) && file_exists($user['profile_pic'])) 
    ? htmlspecialchars($user['profile_pic']) 
    : $default_pic_src;

$_SESSION['profile_pic'] = $profile_pic_src;

$first_name = htmlspecialchars($user['first_name'] ?? '');
$greeting_name = $first_name ?: $username;

// Get date filters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-01-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

// ====== 1. VEHICLE REPAIR EXPENSES ======
$repair_cost_sql = "SELECT 
    vr.id,
    'Repair' as expense_type,
    vr.repair_type,
    vr.priority,
    vr.status,
    0 as amount,
    vr.requested_date as expense_date,
    v.vehicle_no,
    u.username,
    vr.description
FROM vehicle_repairs vr
LEFT JOIN vehicles v ON vr.vehicle_id = v.id
LEFT JOIN users u ON vr.user_id = u.id
WHERE DATE(vr.requested_date) BETWEEN ? AND ?
ORDER BY vr.requested_date DESC";

$repair_cost_stmt = $pdo->prepare($repair_cost_sql);
$repair_cost_stmt->execute([$date_from, $date_to]);
$repair_expenses = $repair_cost_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate repair expenses (assuming repair costs are tracked, or use estimated values)
$repair_total = 0;
foreach ($repair_expenses as $repair) {
    // You can add logic here to calculate repair costs based on priority/type
    // For now, we'll show the count
}

// ====== 2. GASOLINE/FUEL STOCK EXPENSES ======
// Get fuel price
$fuel_price_sql = "SELECT price FROM price_data WHERE item_name = 'Fuel Price' LIMIT 1";
$fuel_price_result = $pdo->query($fuel_price_sql)->fetch();
$fuel_price = $fuel_price_result['price'] ?? 58.00;

// Calculate fuel expenses from submitted trip tickets
$fuel_cost_sql = "SELECT 
    t.id,
    t.control_no,
    t.ticket_date as expense_date,
    v.vehicle_no,
    u.username,
    t.gas_used_trip as fuel_liters,
    t.gas_used_trip * ? as fuel_cost,
    t.approx_distance,
    t.purpose as description
FROM trip_tickets t
LEFT JOIN vehicles v ON t.vehicle_plate_no = v.vehicle_no
LEFT JOIN users u ON t.driver_id = u.id
WHERE t.status = 'Submitted' AND DATE(t.ticket_date) BETWEEN ? AND ?
ORDER BY t.ticket_date DESC";

$fuel_cost_stmt = $pdo->prepare($fuel_cost_sql);
$fuel_cost_stmt->execute([$fuel_price, $date_from, $date_to]);
$fuel_expenses = $fuel_cost_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate fuel total
$fuel_total = 0;
$total_fuel_liters = 0;
foreach ($fuel_expenses as &$fuel) {
    $fuel['cost'] = floatval($fuel['fuel_liters']) * floatval($fuel_price);
    $fuel_total += $fuel['cost'];
    $total_fuel_liters += floatval($fuel['fuel_liters']);
}

// ====== 3. VEHICLE MAINTENANCE EXPENSES ======
// No separate vehicle_expenses table exists, so this section is disabled
$vehicle_expenses_list = [];
$vehicle_total = 0;

// ====== SUMMARY TOTALS ======
$summary_sql = "SELECT 
    (SELECT COUNT(*) FROM vehicle_repairs WHERE DATE(requested_date) BETWEEN ? AND ?) as repair_count,
    (SELECT COALESCE(SUM(gas_used_trip), 0) FROM trip_tickets WHERE status = 'Submitted' AND DATE(ticket_date) BETWEEN ? AND ?) as fuel_liters";

$summary_stmt = $pdo->prepare($summary_sql);
$summary_stmt->execute([$date_from, $date_to, $date_from, $date_to]);
$summary = $summary_stmt->fetch(PDO::FETCH_ASSOC);

$grand_total = $fuel_total + $vehicle_total;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expenses Summary - BFP Fuel System</title>
    <link rel="icon" href="ALBUM/favicon_io/favicon-32x32.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #5d5dff;
            --bg-dark: #1e1e2d;
            --bg-light-dark: #2a2a3e;
            --text-light: #e2e2e2;
            --text-gray: #a2a2c2;
            --border-color: #3d3d5c;
            --success: #4caf50;
            --danger: #ff6b6b;
            --warning: #ffc107;
        }

        * { box-sizing: border-box; }
        
        body {
            margin: 0;
            font-family: 'Poppins', Arial, sans-serif;
            background: var(--bg-dark);
            color: var(--text-light);
        }
        .wrapper {
            display: flex;
            flex: 1;
            overflow: visible;
            width: 100%;
            position: relative;
        }

        main {
            display: flex;
            flex-direction: column;
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            width: 100%;
            padding: 0;
        }
        .header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--primary-color);
        }

        .header h1 {
            margin: 0;
            color: var(--primary-color);
            font-size: 2.5rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .header p {
            color: var(--text-gray);
            margin: 10px 0 0 0;
        }

        .filters {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
            align-items: center;
            padding: 20px;
            background: var(--bg-light-dark);
            border-radius: 10px;
        }

        .filters input[type="date"] {
            padding: 10px 15px;
            border: 1px solid var(--border-color);
            background: var(--bg-dark);
            color: var(--text-light);
            border-radius: 5px;
            font-family: 'Poppins', Arial, sans-serif;
        }

        .filters button {
            padding: 10px 25px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .filters button:hover {
            background: #4949d1;
            transform: translateY(-2px);
        }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .card {
            background: var(--bg-light-dark);
            padding: 25px;
            border-radius: 10px;
            border-left: 5px solid var(--primary-color);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        .card.repair {
            border-left-color: var(--danger);
        }

        .card.fuel {
            border-left-color: var(--warning);
        }

        .card.maintenance {
            border-left-color: var(--success);
        }

        .card.total {
            border-left-color: var(--primary-color);
            background: linear-gradient(135deg, rgba(93, 93, 255, 0.1), rgba(93, 93, 255, 0.05));
        }

        .card-icon {
            font-size: 2rem;
            margin-bottom: 10px;
        }

        .card-label {
            color: var(--text-gray);
            font-size: 0.9rem;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .card-value {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 10px 0 5px 0;
        }

        .card-amount {
            font-size: 1.5rem;
            color: var(--primary-color);
            font-weight: 600;
        }

        .card-subtitle {
            font-size: 0.85rem;
            color: var(--text-gray);
            margin-top: 5px;
        }

        .expense-section {
            margin-bottom: 40px;
        }

        .section-title {
            font-size: 1.5rem;
            color: var(--primary-color);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .table-responsive {
            overflow-x: auto;
            background: var(--bg-light-dark);
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95rem;
        }

        thead {
            background: rgba(93, 93, 255, 0.1);
            position: sticky;
            top: 0;
        }

        th {
            padding: 15px;
            text-align: left;
            color: var(--text-gray);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--border-color);
        }

        td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-light);
        }

        tbody tr:hover {
            background: rgba(93, 93, 255, 0.08);
        }

        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .badge-success {
            background: rgba(76, 175, 80, 0.2);
            color: #4caf50;
            border: 1px solid rgba(76, 175, 80, 0.5);
        }

        .badge-danger {
            background: rgba(255, 107, 107, 0.2);
            color: #ff6b6b;
            border: 1px solid rgba(255, 107, 107, 0.5);
        }

        .badge-warning {
            background: rgba(255, 193, 7, 0.2);
            color: #ffc107;
            border: 1px solid rgba(255, 193, 7, 0.5);
        }

        .badge-info {
            background: rgba(93, 93, 255, 0.2);
            color: #5d5dff;
            border: 1px solid rgba(93, 93, 255, 0.5);
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: var(--text-gray);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .summary-cards {
                grid-template-columns: 1fr;
            }

            .filters {
                flex-direction: column;
            }

            .filters input, .filters button {
                width: 100%;
            }

            table {
                font-size: 0.85rem;
            }

            th, td {
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    
    <div class="wrapper">
        <?php include("Components/Sidebar.php"); ?>
        <main>
            <!-- HEADER -->
            <div class="header">
                <h1><i class="fas fa-chart-pie"></i> Expenses Summary</h1>
                <p>Comprehensive view of repair, gasoline, and maintenance expenses</p>
            </div>

            <!-- FILTERS -->
            <div style="padding: 20px; max-width: 1400px; margin: 0 auto; width: 100%;">
                <form method="GET" class="filters">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <label style="color: var(--text-gray); font-weight: 600; white-space: nowrap;">From:</label>
                        <input type="date" name="date_from" value="<?php echo $date_from; ?>" required>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <label style="color: var(--text-gray); font-weight: 600; white-space: nowrap;">To:</label>
                        <input type="date" name="date_to" value="<?php echo $date_to; ?>" required>
                    </div>
                    <button type="submit" style="padding: 10px 25px; background: var(--primary-color); color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: 600; transition: all 0.3s ease;">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <a href="Expenses.php" style="padding: 10px 25px; background: var(--text-gray); color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s ease;">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                </form>
            </div>

            <!-- SUMMARY CARDS -->
            <div style="padding: 20px; max-width: 1400px; margin: 0 auto; width: 100%;">
                <div class="summary-cards">
                    <!-- Repair Count -->
                    <div class="card repair">
                        <div class="card-icon"><i class="fas fa-tools"></i></div>
                        <div class="card-label">Vehicle Repairs</div>
                        <div class="card-value"><?php echo count($repair_expenses); ?></div>
                        <div class="card-subtitle">Repair requests in period</div>
                    </div>

                    <!-- Fuel Expenses -->
                    <div class="card fuel">
                        <div class="card-icon"><i class="fas fa-gas-pump"></i></div>
                        <div class="card-label">Gasoline Expenses</div>
                        <div class="card-amount">₱<?php echo number_format($fuel_total, 2); ?></div>
                        <div class="card-subtitle"><?php echo round($total_fuel_liters, 2); ?> L @ ₱<?php echo number_format($fuel_price, 2); ?>/L</div>
                    </div>

                    <!-- Maintenance Expenses -->
                    <div class="card maintenance">
                        <div class="card-icon"><i class="fas fa-wrench"></i></div>
                        <div class="card-label">Repair Estimates</div>
                        <div class="card-value"><?php echo count($repair_expenses); ?></div>
                        <div class="card-subtitle">Tracked in repair requests</div>
                    </div>

                    <!-- Total Expenses -->
                    <div class="card total">
                        <div class="card-icon"><i class="fas fa-chart-bar"></i></div>
                        <div class="card-label">Total Gasoline Costs</div>
                        <div class="card-amount" style="color: #4caf50;">₱<?php echo number_format($fuel_total, 2); ?></div>
                        <div class="card-subtitle">From fuel stock removals</div>
                    </div>
                </div>
            </div>


            <!-- VEHICLE REPAIRS SECTION -->
            <div style="padding: 20px; max-width: 1400px; margin: 0 auto; width: 100%;">
                <div class="expense-section">
                    <h2 class="section-title"><i class="fas fa-tools"></i> Vehicle Repair Requests</h2>
                    <?php if (count($repair_expenses) > 0): ?>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Vehicle</th>
                                        <th>Repair Type</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($repair_expenses as $repair): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($repair['expense_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($repair['vehicle_no'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($repair['repair_type'] ?? 'N/A'); ?></td>
                                            <td>
                                                <span class="badge badge-<?php 
                                                    echo match($repair['priority']) {
                                                        'urgent' => 'danger',
                                                        'high' => 'warning',
                                                        'medium' => 'info',
                                                        default => 'success'
                                                    };
                                                ?>">
                                                    <?php echo ucfirst(htmlspecialchars($repair['priority'] ?? 'N/A')); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php 
                                                    echo match($repair['status']) {
                                                        'completed' => 'success',
                                                        'in_progress' => 'warning',
                                                        'rejected' => 'danger',
                                                        default => 'info'
                                                    };
                                                ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($repair['status'] ?? 'N/A'))); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars(substr($repair['description'] ?? 'N/A', 0, 50)); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>No repair requests found for this period</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- GASOLINE EXPENSES SECTION -->
            <div style="padding: 20px; max-width: 1400px; margin: 0 auto; width: 100%;">
                <div class="expense-section">
                    <h2 class="section-title"><i class="fas fa-gas-pump"></i> Gasoline/Fuel Consumption Expenses</h2>
                    <?php if (count($fuel_expenses) > 0): ?>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Control No.</th>
                                        <th>Vehicle</th>
                                        <th>Driver</th>
                                        <th>Distance (km)</th>
                                        <th>Liters Used</th>
                                        <th>Price/Liter</th>
                                        <th>Total Cost</th>
                                        <th>Purpose</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($fuel_expenses as $fuel): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($fuel['expense_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($fuel['control_no'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($fuel['vehicle_no'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($fuel['username'] ?? 'N/A'); ?></td>
                                            <td><?php echo round($fuel['approx_distance'] ?? 0, 2); ?></td>
                                            <td><?php echo round($fuel['fuel_liters'] ?? 0, 2); ?></td>
                                            <td>₱<?php echo number_format($fuel_price, 2); ?></td>
                                            <td style="color: var(--warning); font-weight: 600;">₱<?php echo number_format($fuel['cost'], 2); ?></td>
                                            <td><?php echo htmlspecialchars(substr($fuel['description'] ?? 'N/A', 0, 50)); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>No submitted trip tickets found for this period</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- MAINTENANCE EXPENSES SECTION -->
            <div style="padding: 20px; max-width: 1400px; margin: 0 auto; width: 100%;">
                <div class="expense-section">
                    <h2 class="section-title"><i class="fas fa-info-circle"></i> Expenses Summary</h2>
                    <div style="background: var(--bg-light-dark); padding: 20px; border-radius: 10px; border-left: 5px solid var(--primary-color);">
                        <p><strong>Repair Costs:</strong> Tracked in Vehicle Repair Requests section above</p>
                        <p><strong>Gasoline Costs:</strong> Displayed in Gasoline/Fuel Stock Expenses section</p>
                        <p><strong>Maintenance Costs:</strong> Can be added to vehicle repairs with detailed tracking</p>
                        <p style="margin: 0; color: var(--text-gray); font-size: 0.9rem;"><i class="fas fa-lightbulb"></i> Total gasoline costs shown above are calculated from fuel stock removals at current fuel price.</p>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
