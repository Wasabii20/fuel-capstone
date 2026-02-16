<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

require_once 'db_connect.php';

// Get month and year filter
$currentMonth = isset($_GET['month']) ? $_GET['month'] : date('m');
$currentYear = isset($_GET['year']) ? $_GET['year'] : date('Y');
$filterPeriod = isset($_GET['filter']) ? $_GET['filter'] : 'monthly';

// Validate inputs
$currentMonth = intval($currentMonth);
$currentYear = intval($currentYear);

// Build date filter
$dateFilter = '';
$dateParams = [];

if ($filterPeriod === 'monthly') {
    $dateFilter = "WHERE MONTH(t.ticket_date) = :month AND YEAR(t.ticket_date) = :year AND t.status = 'Submitted'";
    $dateParams = ['month' => $currentMonth, 'year' => $currentYear];
} elseif ($filterPeriod === 'yearly') {
    $dateFilter = "WHERE YEAR(t.ticket_date) = :year AND t.status = 'Submitted'";
    $dateParams = ['year' => $currentYear];
} else {
    $dateFilter = "WHERE t.status = 'Submitted'";
}

// Get fuel price
$fuelPriceQuery = "SELECT price FROM price_data WHERE item_name = 'Fuel Price' LIMIT 1";
$fuelPriceResult = $pdo->query($fuelPriceQuery);
$fuelPrice = $fuelPriceResult ? $fuelPriceResult->fetch(PDO::FETCH_ASSOC)['price'] : 50;

// Main query for consumption data from submitted trip tickets
$query = "
    SELECT 
        t.id,
        t.control_no,
        t.ticket_date,
        t.vehicle_plate_no,
        v.vehicle_no,
        v.vehicle_type,
        t.gas_used_trip,
        t.approx_distance,
        t.status,
        t.driver_id,
        d.full_name as driver_name
    FROM trip_tickets t
    LEFT JOIN vehicles v ON t.vehicle_plate_no = v.vehicle_no
    LEFT JOIN drivers d ON t.driver_id = d.driver_id
    $dateFilter
    ORDER BY t.ticket_date DESC
";

try {
    $stmt = $pdo->prepare($query);
    foreach ($dateParams as $key => $value) {
        $stmt->bindValue(':' . $key, $value, PDO::PARAM_INT);
    }
    $stmt->execute();
    $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $trips = [];
    $error = "Error fetching data: " . $e->getMessage();
}

// Calculate consumption statistics
$totalGasUsed = 0;
$totalTrips = count($trips);
$totalDistance = 0;
$totalCost = 0;
$vehicleConsumption = [];
$driverConsumption = [];
$consumptionByStatus = [];
$hourlyConsumption = [];
$dailyConsumption = [];

foreach ($trips as $trip) {
    $gasUsed = floatval($trip['gas_used_trip'] ?? 0);
    $distance = floatval($trip['approx_distance'] ?? 0);
    
    $totalGasUsed += $gasUsed;
    $totalDistance += $distance;
    $totalCost += $gasUsed * $fuelPrice;
    
    // Vehicle consumption
    $plate = $trip['vehicle_no'] ?? $trip['vehicle_plate_no'];
    if (!isset($vehicleConsumption[$plate])) {
        $vehicleConsumption[$plate] = [
            'name' => $trip['vehicle_type'] ?? 'Unknown',
            'plate' => $plate,
            'trips' => 0,
            'gas_used' => 0,
            'distance' => 0,
            'cost' => 0
        ];
    }
    $vehicleConsumption[$plate]['trips']++;
    $vehicleConsumption[$plate]['gas_used'] += $gasUsed;
    $vehicleConsumption[$plate]['distance'] += $distance;
    $vehicleConsumption[$plate]['cost'] += $gasUsed * $fuelPrice;
    
    // Driver consumption
    $driver = $trip['driver_name'] ?? 'Unknown';
    if (!isset($driverConsumption[$driver])) {
        $driverConsumption[$driver] = [
            'name' => $driver,
            'trips' => 0,
            'gas_used' => 0,
            'distance' => 0,
            'cost' => 0
        ];
    }
    $driverConsumption[$driver]['trips']++;
    $driverConsumption[$driver]['gas_used'] += $gasUsed;
    $driverConsumption[$driver]['distance'] += $distance;
    $driverConsumption[$driver]['cost'] += $gasUsed * $fuelPrice;
    
    // Daily consumption
    $day = date('Y-m-d', strtotime($trip['ticket_date']));
    if (!isset($dailyConsumption[$day])) {
        $dailyConsumption[$day] = ['gas' => 0, 'distance' => 0, 'trips' => 0];
    }
    $dailyConsumption[$day]['gas'] += $gasUsed;
    $dailyConsumption[$day]['distance'] += $distance;
    $dailyConsumption[$day]['trips']++;
}

// Calculate averages
$avgGasPerTrip = $totalTrips > 0 ? ($totalGasUsed / $totalTrips) : 0;
$avgGasPerKm = $totalDistance > 0 ? ($totalGasUsed / $totalDistance) : 0;
$avgCostPerTrip = $totalTrips > 0 ? ($totalCost / $totalTrips) : 0;

// Get monthly consumption data for chart
$monthlyQuery = "
    SELECT 
        MONTH(ticket_date) as month,
        YEAR(ticket_date) as year,
        SUM(gas_used_trip) as total_gas,
        SUM(approx_distance) as total_distance,
        COUNT(*) as trip_count,
        AVG(gas_used_trip) as avg_gas
    FROM trip_tickets
    WHERE status = 'Submitted' AND YEAR(ticket_date) = :year
    GROUP BY YEAR(ticket_date), MONTH(ticket_date)
    ORDER BY month
";

try {
    $monthlyStmt = $pdo->prepare($monthlyQuery);
    $monthlyStmt->bindValue(':year', $currentYear, PDO::PARAM_INT);
    $monthlyStmt->execute();
    $monthlyData = $monthlyStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $monthlyData = [];
}

// Sort drivers and vehicles by consumption
usort($vehicleConsumption, function($a, $b) {
    return $b['gas_used'] <=> $a['gas_used'];
});

usort($driverConsumption, function($a, $b) {
    return $b['gas_used'] <=> $a['gas_used'];
});

// Sort daily consumption
ksort($dailyConsumption);
$dailyConsumption = array_reverse($dailyConsumption, true);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gasoline Consumption - BFP Fuel System</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #0f0f1e;
            color: #e4e6eb;
            min-height: 100vh;
            display: flex;
        }

        .wrapper {
            display: flex;
            flex: 1;
            overflow: hidden;
        }

        main {
            flex: 1;
            overflow: auto;
            padding: 20px;
            background: #0f0f1e;
        }

        .header {
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            color: #fff;
        }

        .header p {
            color: #a2a2c2;
            font-size: 1rem;
        }

        .filter-section {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
            background: rgba(93, 93, 255, 0.08);
            padding: 15px;
            border-radius: 12px;
            border: 1px solid rgba(93, 93, 255, 0.2);
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-group label {
            color: #a2a2c2;
            font-weight: 500;
        }

        select {
            background: #1e1e2d;
            border: 1px solid rgba(93, 93, 255, 0.2);
            color: #e4e6eb;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 0.9rem;
        }

        select:focus {
            outline: none;
            border-color: #5d5dff;
            box-shadow: 0 0 8px rgba(93, 93, 255, 0.2);
        }

        button {
            background: linear-gradient(135deg, #5d5dff 0%, #4a4a9f 100%);
            border: none;
            color: white;
            padding: 8px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(93, 93, 255, 0.4);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(93, 93, 255, 0.08);
            border: 1px solid rgba(93, 93, 255, 0.2);
            border-radius: 12px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: all 0.3s;
        }

        .stat-card:hover {
            border-color: #5d5dff;
            background: rgba(93, 93, 255, 0.12);
            transform: translateY(-5px);
        }

        .stat-label {
            color: #a2a2c2;
            font-size: 0.9rem;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #fff;
        }

        .stat-unit {
            color: #7e8299;
            font-size: 0.85rem;
            margin-top: 5px;
        }

        .charts-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .chart-card {
            background: rgba(93, 93, 255, 0.08);
            border: 1px solid rgba(93, 93, 255, 0.2);
            border-radius: 12px;
            padding: 20px;
            min-height: 400px;
            display: flex;
            flex-direction: column;
        }

        .chart-card h3 {
            color: #fff;
            margin-bottom: 15px;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .chart-card canvas {
            max-height: 300px;
            flex-grow: 1;
        }

        .table-section {
            background: rgba(93, 93, 255, 0.08);
            border: 1px solid rgba(93, 93, 255, 0.2);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .table-section h3 {
            color: #fff;
            margin-bottom: 15px;
            font-size: 1.1rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: rgba(0, 0, 0, 0.2);
            color: #a2a2c2;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid rgba(93, 93, 255, 0.1);
            color: #e4e6eb;
        }

        tr:hover {
            background: rgba(93, 93, 255, 0.05);
        }

        .vehicle-name {
            font-weight: 500;
            color: #5d5dff;
        }

        .print-btn {
            background: linear-gradient(135deg, #4cb050 0%, #388e3c 100%);
            margin-left: auto;
            display: block;
            margin-bottom: 15px;
        }

        .print-btn:hover {
            box-shadow: 0 5px 15px rgba(76, 175, 80, 0.4);
        }

        .efficiency-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: bold;
        }

        .efficiency-good {
            background: rgba(76, 175, 80, 0.2);
            color: #4cb050;
        }

        .efficiency-normal {
            background: rgba(255, 193, 7, 0.2);
            color: #ffc107;
        }

        .efficiency-poor {
            background: rgba(244, 67, 54, 0.2);
            color: #f44336;
        }

        @media (max-width: 768px) {
            .charts-section {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            table {
                font-size: 0.85rem;
            }
        }

        @media print {
            body {
                background: white;
                color: #000;
            }

            .filter-section,
            .print-btn {
                display: none;
            }

            .stat-card,
            .chart-card,
            .table-section {
                border: 1px solid #ccc;
                break-inside: avoid;
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include("Components/Sidebar.php"); ?>
    <main>
        <div class="content">
            <div class="header">
                <h1>⛽ Gasoline Consumption Analytics</h1>
                <p>Track and analyze fuel consumption from submitted trip tickets</p>
            </div>

            <button class="print-btn" onclick="window.print()">🖨️ Print Report</button>

            <!-- Filter Section -->
            <form method="GET" class="filter-section">
                <div class="filter-group">
                    <label for="filter">Filter:</label>
                    <select name="filter" id="filter" onchange="updateFilterOptions()">
                        <option value="monthly" <?php echo $filterPeriod === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                        <option value="yearly" <?php echo $filterPeriod === 'yearly' ? 'selected' : ''; ?>>Yearly</option>
                        <option value="all" <?php echo $filterPeriod === 'all' ? 'selected' : ''; ?>>All Time</option>
                    </select>
                </div>

                <div class="filter-group" id="monthGroup" style="<?php echo $filterPeriod !== 'monthly' ? 'display:none;' : ''; ?>">
                    <label for="month">Month:</label>
                    <select name="month" id="month">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $m == $currentMonth ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="year">Year:</label>
                    <select name="year" id="year">
                        <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                            <option value="<?php echo $y; ?>" <?php echo $y == $currentYear ? 'selected' : ''; ?>>
                                <?php echo $y; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <button type="submit">🔍 Filter</button>
            </form>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Gasoline Used</div>
                    <div class="stat-value"><?php echo number_format($totalGasUsed, 2); ?></div>
                    <div class="stat-unit">Liters</div>
                </div>

                <div class="stat-card">
                    <div class="stat-label">Total Trips (Submitted)</div>
                    <div class="stat-value"><?php echo $totalTrips; ?></div>
                    <div class="stat-unit">Trip Tickets</div>
                </div>

                <div class="stat-card">
                    <div class="stat-label">Total Distance Traveled</div>
                    <div class="stat-value"><?php echo number_format($totalDistance, 0); ?></div>
                    <div class="stat-unit">Kilometers</div>
                </div>

                <div class="stat-card">
                    <div class="stat-label">Average Gas per Trip</div>
                    <div class="stat-value"><?php echo number_format($avgGasPerTrip, 2); ?></div>
                    <div class="stat-unit">Liters</div>
                </div>

                <div class="stat-card">
                    <div class="stat-label">Average Gas per KM</div>
                    <div class="stat-value"><?php echo number_format($avgGasPerKm, 3); ?></div>
                    <div class="stat-unit">Liters per KM</div>
                </div>

                <div class="stat-card">
                    <div class="stat-label">Total Consumption Cost</div>
                    <div class="stat-value">₱<?php echo number_format($totalCost, 2); ?></div>
                    <div class="stat-unit">at ₱<?php echo number_format($fuelPrice, 2); ?>/L</div>
                </div>

                <div class="stat-card">
                    <div class="stat-label">Average Cost per Trip</div>
                    <div class="stat-value">₱<?php echo number_format($avgCostPerTrip, 2); ?></div>
                    <div class="stat-unit">Per Trip</div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="charts-section">
                <div class="chart-card">
                    <h3>📊 Top Consuming Vehicles</h3>
                    <canvas id="vehicleChart"></canvas>
                </div>

                <div class="chart-card">
                    <h3>👤 Top Consuming Drivers</h3>
                    <canvas id="driverChart"></canvas>
                </div>
            </div>

            <?php if (!empty($monthlyData)): ?>
            <div class="chart-card" style="margin-bottom: 30px;">
                <h3>📈 Monthly Consumption Trend (<?php echo $currentYear; ?>)</h3>
                <canvas id="monthlyChart"></canvas>
            </div>
            <?php endif; ?>

            <!-- Vehicle Consumption Table -->
            <?php if (!empty($vehicleConsumption)): ?>
            <div class="table-section">
                <h3>🚙 Vehicle Fuel Consumption</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Vehicle</th>
                            <th>Plate No.</th>
                            <th>Trips</th>
                            <th>Total Gas (L)</th>
                            <th>Distance (KM)</th>
                            <th>Avg Gas/KM</th>
                            <th>Efficiency</th>
                            <th>Cost</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vehicleConsumption as $vehicle): 
                            $efficiency = $vehicle['distance'] > 0 ? ($vehicle['gas_used'] / $vehicle['distance']) : 0;
                            $efficiencyClass = $efficiency < 0.05 ? 'efficiency-good' : ($efficiency < 0.08 ? 'efficiency-normal' : 'efficiency-poor');
                        ?>
                        <tr>
                            <td><span class="vehicle-name"><?php echo htmlspecialchars($vehicle['name']); ?></span></td>
                            <td><?php echo htmlspecialchars($vehicle['plate']); ?></td>
                            <td><?php echo $vehicle['trips']; ?></td>
                            <td><?php echo number_format($vehicle['gas_used'], 2); ?></td>
                            <td><?php echo number_format($vehicle['distance'], 0); ?></td>
                            <td><?php echo number_format($efficiency, 3); ?></td>
                            <td><span class="efficiency-badge <?php echo $efficiencyClass; ?>"><?php echo number_format($efficiency, 3); ?>L/KM</span></td>
                            <td>₱<?php echo number_format($vehicle['cost'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <!-- Driver Consumption Table -->
            <?php if (!empty($driverConsumption)): ?>
            <div class="table-section">
                <h3>👤 Driver Fuel Consumption</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Driver Name</th>
                            <th>Trips</th>
                            <th>Total Gas (L)</th>
                            <th>Distance (KM)</th>
                            <th>Avg Gas/Trip</th>
                            <th>Avg Gas/KM</th>
                            <th>Cost</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($driverConsumption as $driver): 
                            $avgTrip = $driver['trips'] > 0 ? ($driver['gas_used'] / $driver['trips']) : 0;
                            $avgKm = $driver['distance'] > 0 ? ($driver['gas_used'] / $driver['distance']) : 0;
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($driver['name']); ?></td>
                            <td><?php echo $driver['trips']; ?></td>
                            <td><?php echo number_format($driver['gas_used'], 2); ?></td>
                            <td><?php echo number_format($driver['distance'], 0); ?></td>
                            <td><?php echo number_format($avgTrip, 2); ?></td>
                            <td><?php echo number_format($avgKm, 3); ?></td>
                            <td>₱<?php echo number_format($driver['cost'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <!-- Consumption Details Table -->
            <?php if (!empty($trips)): ?>
            <div class="table-section">
                <h3>📋 Trip Consumption Details</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Control No.</th>
                            <th>Vehicle</th>
                            <th>Driver</th>
                            <th>Gas Used (L)</th>
                            <th>Distance (KM)</th>
                            <th>Efficiency (L/KM)</th>
                            <th>Cost</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($trips, 0, 50) as $trip):
                            $gasUsed = floatval($trip['gas_used_trip'] ?? 0);
                            $distance = floatval($trip['approx_distance'] ?? 0);
                            $efficiency = $distance > 0 ? ($gasUsed / $distance) : 0;
                        ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($trip['ticket_date'])); ?></td>
                            <td style="color: #5d5dff; font-weight: 500;"><?php echo htmlspecialchars($trip['control_no']); ?></td>
                            <td><span class="vehicle-name"><?php echo htmlspecialchars($trip['vehicle_type'] ?? 'Unknown'); ?></span></td>
                            <td><?php echo htmlspecialchars($trip['driver_name'] ?? 'Unknown'); ?></td>
                            <td><?php echo number_format($gasUsed, 2); ?></td>
                            <td><?php echo number_format($distance, 0); ?></td>
                            <td><?php echo number_format($efficiency, 3); ?></td>
                            <td>₱<?php echo number_format(($gasUsed * $fuelPrice), 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
    function updateFilterOptions() {
        const filter = document.getElementById('filter').value;
        const monthGroup = document.getElementById('monthGroup');
        monthGroup.style.display = filter === 'monthly' ? 'flex' : 'none';
    }

    // Vehicle Consumption Chart
    const vehicleCtx = document.getElementById('vehicleChart')?.getContext('2d');
    if (vehicleCtx) {
        const vehicleData = <?php echo json_encode($vehicleConsumption); ?>;
        const topVehicles = vehicleData.slice(0, 8);
        
        new Chart(vehicleCtx, {
            type: 'bar',
            data: {
                labels: topVehicles.map(v => v.name + '\n(' + v.plate + ')'),
                datasets: [{
                    label: 'Gas Consumption (L)',
                    data: topVehicles.map(v => v.gas_used),
                    backgroundColor: 'rgba(255, 152, 0, 0.8)',
                    borderColor: '#ff9800',
                    borderWidth: 1,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                indexAxis: undefined,
                plugins: {
                    legend: { labels: { color: '#a2a2c2' } }
                },
                scales: {
                    y: {
                        ticks: { color: '#a2a2c2' },
                        grid: { color: 'rgba(255, 255, 255, 0.05)' },
                        beginAtZero: true
                    },
                    x: {
                        ticks: { color: '#a2a2c2' },
                        grid: { color: 'rgba(255, 255, 255, 0.05)' }
                    }
                }
            }
        });
    }

    // Driver Consumption Chart
    const driverCtx = document.getElementById('driverChart')?.getContext('2d');
    if (driverCtx) {
        const driverData = <?php echo json_encode($driverConsumption); ?>;
        const topDrivers = driverData.slice(0, 8);
        
        new Chart(driverCtx, {
            type: 'bar',
            data: {
                labels: topDrivers.map(d => d.name),
                datasets: [{
                    label: 'Gas Consumption (L)',
                    data: topDrivers.map(d => d.gas_used),
                    backgroundColor: 'rgba(76, 175, 80, 0.8)',
                    borderColor: '#4cb050',
                    borderWidth: 1,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { labels: { color: '#a2a2c2' } }
                },
                scales: {
                    y: {
                        ticks: { color: '#a2a2c2' },
                        grid: { color: 'rgba(255, 255, 255, 0.05)' },
                        beginAtZero: true
                    },
                    x: {
                        ticks: { color: '#a2a2c2' },
                        grid: { color: 'rgba(255, 255, 255, 0.05)' }
                    }
                }
            }
        });
    }

    // Monthly Trend Chart
    const monthlyCtx = document.getElementById('monthlyChart')?.getContext('2d');
    if (monthlyCtx) {
        const monthlyData = <?php echo json_encode($monthlyData); ?>;
        
        if (monthlyData && monthlyData.length > 0) {
            const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            const labels = monthlyData.map(d => monthNames[d.month - 1]);
            const gasUsed = monthlyData.map(d => parseFloat(d.total_gas) || 0);
            const avgGas = monthlyData.map(d => parseFloat(d.avg_gas) || 0);

            new Chart(monthlyCtx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Total Gas (L)',
                            data: gasUsed,
                            borderColor: '#ff4444',
                            backgroundColor: 'rgba(255, 68, 68, 0.15)',
                            tension: 0.4,
                            fill: true,
                            borderWidth: 3,
                            pointBackgroundColor: '#ff4444',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 6,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Avg Gas per Trip (L)',
                            data: avgGas,
                            borderColor: '#44ff44',
                            backgroundColor: 'rgba(68, 255, 68, 0.15)',
                            tension: 0.4,
                            fill: false,
                            borderWidth: 2,
                            pointBackgroundColor: '#44ff44',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 5,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    interaction: { intersect: false, mode: 'index' },
                    plugins: {
                        legend: { labels: { color: '#a2a2c2', padding: 15 } }
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            ticks: { color: '#a2a2c2' },
                            grid: { color: 'rgba(255, 255, 255, 0.05)' },
                            title: { display: true, text: 'Total Gas (L)', color: '#a2a2c2' }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            ticks: { color: '#a2a2c2' },
                            grid: { drawOnChartArea: false },
                            title: { display: true, text: 'Avg per Trip (L)', color: '#a2a2c2' }
                        },
                        x: {
                            ticks: { color: '#a2a2c2' },
                            grid: { color: 'rgba(255, 255, 255, 0.05)' }
                        }
                    }
                }
            });
        }
    }
</script>
</body>
</html>
