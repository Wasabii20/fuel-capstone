<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}
require_once 'db_connect.php';

// Handle adding stock
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $amount = floatval($_POST['amount'] ?? 0);
    $note = $_POST['note'] ?? '';
    $category = $_POST['category'] ?? 'fuel tank';
    $container_label = ($_POST['container_label'] ?? '') ?: null;
    
    if ($action === 'add_stock' && $amount > 0) {
        $insertQuery = "INSERT INTO fuel_stocks (stock_type, amount, transaction_type, category, container_label, note, created_at) 
                       VALUES ('gasoline', :amount, 'added', :category, :container_label, :note, NOW())";
        $insertStmt = $pdo->prepare($insertQuery);
        $insertStmt->execute([':amount' => $amount, ':category' => $category, ':container_label' => $container_label, ':note' => $note]);
        // Redirect to prevent duplicate submission on page refresh
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    } elseif ($action === 'remove_stock' && $amount > 0) {
        $insertQuery = "INSERT INTO fuel_stocks (stock_type, amount, transaction_type, category, container_label, note, created_at) 
                       VALUES ('gasoline', :amount, 'removed', :category, :container_label, :note, NOW())";
        $insertStmt = $pdo->prepare($insertQuery);
        $insertStmt->execute([':amount' => $amount, ':category' => $category, ':container_label' => $container_label, ':note' => $note]);
        // Redirect to prevent duplicate submission on page refresh
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
}

// Get current fuel price
$fuelPriceQuery = "SELECT price FROM price_data WHERE item_name = 'Fuel Price' LIMIT 1";
$fuelPriceResult = $pdo->query($fuelPriceQuery);
$fuelPrice = $fuelPriceResult ? $fuelPriceResult->fetch(PDO::FETCH_ASSOC)['price'] : 50;

// Get all fuel stock transactions
$stockQuery = "
    SELECT * FROM fuel_stocks 
    WHERE stock_type = 'gasoline'
    ORDER BY created_at DESC
";

try {
    $stockStmt = $pdo->query($stockQuery);
    $allStocks = $stockStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $allStocks = [];
}

// Get recent fuel stock transactions (last 10)
$recentQuery = "
    SELECT * FROM fuel_stocks 
    WHERE stock_type = 'gasoline'
    ORDER BY created_at DESC
    LIMIT 10
";

try {
    $recentStmt = $pdo->query($recentQuery);
    $recentTransactions = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recentTransactions = [];
}

// Calculate current inventory
$currentStock = 0;
$totalAdded = 0;
$totalRemoved = 0;
$addTransactions = 0;
$removeTransactions = 0;

foreach ($allStocks as $stock) {
    if ($stock['transaction_type'] === 'added') {
        $currentStock += floatval($stock['amount']);
        $totalAdded += floatval($stock['amount']);
        $addTransactions++;
    } elseif ($stock['transaction_type'] === 'removed') {
        $currentStock -= floatval($stock['amount']);
        $totalRemoved += floatval($stock['amount']);
        $removeTransactions++;
    }
}

// Calculate stock value
$stockValue = $currentStock * $fuelPrice;

// Get available fuel containers for removal
$availableContainers = [];
try {
    $containerQuery = "
        SELECT 
            category,
            container_label,
            SUM(CASE WHEN transaction_type = 'added' THEN amount ELSE amount * -1 END) as available_amount
        FROM fuel_stocks 
        WHERE stock_type = 'gasoline' AND category IN ('fuel can', 'fuel drum') AND container_label IS NOT NULL
        GROUP BY category, container_label
        HAVING available_amount > 0
        ORDER BY category, container_label
    ";
    $containerStmt = $pdo->query($containerQuery);
    $availableContainers = $containerStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $availableContainers = [];
}

// Monthly stock analytics
$monthlyQuery = "
    SELECT 
        MONTH(created_at) as month,
        YEAR(created_at) as year,
        SUM(CASE WHEN transaction_type = 'added' THEN amount ELSE 0 END) as added,
        SUM(CASE WHEN transaction_type = 'removed' THEN amount ELSE 0 END) as removed,
        COUNT(*) as transaction_count
    FROM fuel_stocks
    WHERE YEAR(created_at) = :year AND stock_type = 'gasoline'
    GROUP BY YEAR(created_at), MONTH(created_at)
    ORDER BY month DESC
";

try {
    $monthlyStmt = $pdo->prepare($monthlyQuery);
    $monthlyStmt->bindValue(':year', date('Y'), PDO::PARAM_INT);
    $monthlyStmt->execute();
    $monthlyAnalytics = $monthlyStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $monthlyAnalytics = [];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gasoline Stocks - BFP Fuel System</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #0f0f1e 0%, #1a1a2e 100%);
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
            padding: 30px;
            background: linear-gradient(135deg, #0f0f1e 0%, #1a1a2e 100%);
        }

        .header {
            margin-bottom: 40px;
        }

        .header h1 {
            font-size: 2.8rem;
            margin-bottom: 8px;
            color: #fff;
            font-weight: 700;
            background: linear-gradient(135deg, #5d5dff, #00bcd4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .header p {
            color: #8d92a6;
            font-size: 1.05rem;
            font-weight: 400;
        }

        .header-controls {
            display: flex;
            gap: 12px;
            margin-top: 25px;
            flex-wrap: wrap;
        }

        button {
            border: none;
            color: white;
            padding: 12px 28px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.1);
            transition: left 0.3s;
            z-index: -1;
        }

        button:hover::before {
            left: 100%;
        }

        button:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        }

        button:active {
            transform: translateY(-1px);
        }

        .btn-add {
            background: linear-gradient(135deg, #4cb050 0%, #45a049 100%);
        }

        .btn-add:hover {
            box-shadow: 0 8px 25px rgba(76, 175, 80, 0.35);
        }

        .btn-remove {
            background: linear-gradient(135deg, #ff5722 0%, #e64a19 100%);
        }

        .btn-remove:hover {
            box-shadow: 0 8px 25px rgba(255, 87, 34, 0.35);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: rgba(30, 30, 45, 0.6);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(93, 93, 255, 0.15);
            border-radius: 16px;
            padding: 25px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: #5d5dff;
            transform: scaleX(0);
            transition: transform 0.3s;
        }

        .stat-card:hover {
            border-color: #5d5dff;
            background: rgba(93, 93, 255, 0.08);
            transform: translateY(-8px);
            box-shadow: 0 16px 40px rgba(93, 93, 255, 0.15);
        }

        .stat-card:hover::before {
            transform: scaleX(1);
        }

        .stat-label {
            color: #8d92a6;
            font-size: 0.85rem;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            font-weight: 600;
        }

        .stat-value {
            font-size: 2.2rem;
            font-weight: 700;
            color: #fff;
            line-height: 1;
        }

        .stat-unit {
            color: #6a6f80;
            font-size: 0.9rem;
            margin-top: 8px;
            font-weight: 500;
        }

        .stat-card.current::before {
            background: #00bcd4;
        }

        .stat-card.added::before {
            background: #4cb050;
        }

        .stat-card.removed::before {
            background: #ff5722;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 15, 30, 0.85);
            backdrop-filter: blur(5px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: linear-gradient(135deg, #1e1e2d 0%, #252537 100%);
            border: 1px solid rgba(93, 93, 255, 0.2);
            border-radius: 20px;
            padding: 35px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-content h2 {
            color: #fff;
            margin-bottom: 25px;
            font-size: 1.6rem;
            font-weight: 700;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            color: #8d92a6;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 0.95rem;
        }

        input, textarea, select {
            width: 100%;
            padding: 13px 16px;
            background: rgba(15, 15, 30, 0.5);
            border: 1.5px solid rgba(93, 93, 255, 0.2);
            border-radius: 10px;
            color: #e4e6eb;
            box-sizing: border-box;
            font-family: inherit;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: #5d5dff;
            background: rgba(93, 93, 255, 0.08);
            box-shadow: 0 0 12px rgba(93, 93, 255, 0.25);
        }

        textarea {
            resize: vertical;
            min-height: 100px;
        }

        select {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%238d92a6' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 15px center;
            padding-right: 40px;
        }

        select option {
            background: #1e1e2d;
            color: #e4e6eb;
        }
        .modal-buttons {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 28px;
        }

        .btn-cancel {
            background: rgba(255, 255, 255, 0.08);
            color: #8d92a6;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .btn-cancel:hover {
            background: rgba(255, 255, 255, 0.12);
            border-color: rgba(255, 255, 255, 0.2);
        }

        .success-message {
            background: linear-gradient(135deg, rgba(76, 175, 80, 0.1), rgba(76, 175, 80, 0.05));
            border: 1.5px solid #4cb050;
            color: #4cb050;
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .success-message span:first-child {
            font-size: 1.3rem;
        }

        .table-section {
            background: rgba(30, 30, 45, 0.6);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(93, 93, 255, 0.15);
            border-radius: 16px;
            padding: 28px;
            margin-bottom: 35px;
            overflow-x: auto;
        }

        .table-section h3 {
            color: #fff;
            margin-bottom: 20px;
            font-size: 1.25rem;
            font-weight: 700;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: rgba(93, 93, 255, 0.05);
            color: #8d92a6;
            padding: 14px 16px;
            text-align: left;
            font-weight: 700;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            border-bottom: 2px solid rgba(93, 93, 255, 0.1);
        }

        td {
            padding: 16px;
            border-bottom: 1px solid rgba(93, 93, 255, 0.08);
            color: #d8dce6;
            font-size: 0.95rem;
        }

        tr:hover {
            background: rgba(93, 93, 255, 0.05);
        }

        tr:last-child td {
            border-bottom: none;
        }

        .badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge.added {
            background: rgba(76, 175, 80, 0.15);
            color: #4cb050;
            border: 1px solid rgba(76, 175, 80, 0.3);
        }

        .badge.removed {
            background: rgba(255, 87, 34, 0.15);
            color: #ff5722;
            border: 1px solid rgba(255, 87, 34, 0.3);
        }

        .amount-value {
            font-weight: 700;
            font-size: 1.05rem;
        }

        .chart-section {
            background: rgba(30, 30, 45, 0.6);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(93, 93, 255, 0.15);
            border-radius: 16px;
            padding: 28px;
            margin-bottom: 35px;
        }

        .chart-section h3 {
            color: #fff;
            margin-bottom: 20px;
            font-size: 1.25rem;
            font-weight: 700;
        }

        canvas {
            max-height: 350px;
        }

        /* Fuel Tank Styles */
        .tank-container {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 50px;
            margin: 35px 0;
            flex-wrap: wrap;
            background: rgba(30, 30, 45, 0.4);
            border-radius: 16px;
            padding: 30px;
            border: 1px solid rgba(93, 93, 255, 0.1);
        }

        .tank-display {
            position: relative;
            width: 160px;
            height: 280px;
            border: 4px solid #5d5dff;
            border-radius: 35px 35px 20px 20px;
            background: #0f0f1e;
            overflow: hidden;
            box-shadow: 0 0 40px rgba(93, 93, 255, 0.25), inset 0 0 25px rgba(0, 0, 0, 0.4);
        }

        .tank-fill {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, #005cff 0%, #00d9ff 50%, #00ff88 100%);
            height: 0%;
            width: 100%;
            transition: height 0.8s cubic-bezier(0.34, 1.56, 0.64, 1);
            box-shadow: 0 0 25px rgba(0, 255, 136, 0.7), inset 0 0 15px rgba(255, 255, 255, 0.2);
        }

        .tank-fill::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 8px;
            background: rgba(255, 255, 255, 0.4);
            animation: wave 2s infinite;
        }

        @keyframes wave {
            0%, 100% { transform: translateX(0); }
            50% { transform: translateX(10px); }
        }

        .tank-level {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 700;
            color: #fff;
            text-shadow: 0 2px 12px rgba(0, 0, 0, 0.8);
            z-index: 10;
        }

        .tank-info {
            display: flex;
            flex-direction: column;
            gap: 12px;
            min-width: 280px;
        }

        .info-row {
            background: rgba(93, 93, 255, 0.05);
            border: 1px solid rgba(93, 93, 255, 0.15);
            border-radius: 12px;
            padding: 16px 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }

        .info-row:hover {
            background: rgba(93, 93, 255, 0.1);
            border-color: rgba(93, 93, 255, 0.25);
        }

        .info-label {
            color: #8d92a6;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }

        .info-value {
            font-size: 1.35rem;
            font-weight: 700;
            color: #fff;
        }

        .info-value.warning {
            color: #ff9800;
        }

        .info-value.critical {
            color: #ff5722;
        }

        .info-value.good {
            color: #4cb050;
        }

        .tank-status {
            background: rgba(0, 188, 212, 0.1);
            border: 1.5px solid rgba(0, 188, 212, 0.3);
            border-radius: 12px;
            padding: 14px;
            text-align: center;
            color: #00bcd4;
            font-weight: 700;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .tank-status.warning {
            background: rgba(255, 152, 0, 0.1);
            border-color: rgba(255, 152, 0, 0.35);
            color: #ff9800;
        }

        .tank-status.critical {
            background: rgba(255, 87, 34, 0.1);
            border-color: rgba(255, 87, 34, 0.35);
            color: #ff5722;
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        @media (max-width: 768px) {
            main {
                padding: 20px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .header h1 {
                font-size: 2rem;
            }

            .header-controls {
                flex-direction: column;
                gap: 10px;
            }

            button {
                width: 100%;
                padding: 13px 20px;
            }

            table {
                font-size: 0.85rem;
            }

            td, th {
                padding: 12px 10px;
            }

            .tank-container {
                flex-direction: column;
                gap: 30px;
                padding: 20px;
            }

            .tank-display {
                width: 140px;
                height: 260px;
            }

            .tank-info {
                min-width: 100%;
            }

            .modal-content {
                padding: 25px;
                width: 95%;
            }

            .table-section {
                padding: 18px;
                margin-bottom: 25px;
            }
        }

        @media (max-width: 480px) {
            .header h1 {
                font-size: 1.5rem;
            }

            .stat-value {
                font-size: 1.8rem;
            }

            button {
                padding: 12px 18px;
                font-size: 0.95rem;
            }

            .modal-content h2 {
                font-size: 1.4rem;
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
                <h1>⛽ Gasoline Stocks</h1>
                <p>Storage gas log and inventory management</p>
            </div>

            <?php if (isset($success)): ?>
                <div class="success-message">
                    <span>✓</span>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
            <?php endif; ?>

            <div class="header-controls">
                <button class="btn-add" onclick="openAddModal()">➕ Add Stock</button>
                <button class="btn-remove" onclick="openRemoveModal()">➖ Remove Stock</button>
            </div>

            <!-- Digital Fuel Tank Display -->
            <div class="tank-container">
                <div class="tank-display">
                    <div class="tank-fill" id="tankFill"></div>
                    <div class="tank-level" id="tankLevel">
                        <span id="tankPercentage">0%</span>
                    </div>
                </div>
                <div class="tank-info">
                    <div class="info-row">
                        <div class="info-label">Current Level</div>
                        <div class="info-value" id="infoCurrent"><?php echo number_format($currentStock, 2); ?> L</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Tank Capacity</div>
                        <div class="info-value">1000 L</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Available Space</div>
                        <div class="info-value" id="infoAvailable"><?php echo number_format(1000 - $currentStock, 2); ?> L</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Fill Percentage</div>
                        <div class="info-value" id="infoPercentage"><?php echo number_format(($currentStock / 1000) * 100, 1); ?>%</div>
                    </div>
                    <div class="tank-status" id="tankStatus">
                        ✓ Fuel Level Normal
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card current">
                    <div class="stat-label">Current Inventory</div>
                    <div class="stat-value"><?php echo number_format($currentStock, 2); ?></div>
                    <div class="stat-unit">Liters</div>
                </div>

                <div class="stat-card added">
                    <div class="stat-label">Total Added</div>
                    <div class="stat-value"><?php echo number_format($totalAdded, 2); ?></div>
                    <div class="stat-unit"><?php echo $addTransactions; ?> transactions</div>
                </div>

                <div class="stat-card removed">
                    <div class="stat-label">Total Removed</div>
                    <div class="stat-value"><?php echo number_format($totalRemoved, 2); ?></div>
                    <div class="stat-unit"><?php echo $removeTransactions; ?> transactions</div>
                </div>

                <div class="stat-card">
                    <div class="stat-label">Stock Value (at ₱<?php echo number_format($fuelPrice, 2); ?>/L)</div>
                    <div class="stat-value">₱<?php echo number_format($stockValue, 2); ?></div>
                    <div class="stat-unit">Current value</div>
                </div>
            </div>

            <!-- Monthly Analytics Chart -->
            <?php if (!empty($monthlyAnalytics)): ?>
            <div class="chart-section">
                <h3>📊 Monthly Stock Movement</h3>
                <canvas id="monthlyChart"></canvas>
            </div>
            <?php endif; ?>

            <!-- Recent Transactions -->
            <div class="table-section">
                <h3>📋 Recent Stock Updates</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Container Type</th>
                            <th>Transaction Type</th>
                            <th>Amount (L)</th>
                            <th>Note</th>
                            <th>Running Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $runningBalance = $currentStock;
                        foreach ($recentTransactions as $transaction):
                            $transAmount = floatval($transaction['amount']);
                            $transType = $transaction['transaction_type'];
                            $category = $transaction['category'] ?? 'fuel tank';
                            $container_label = $transaction['container_label'] ?? null;
                            $categoryIcon = match($category) {
                                'fuel tank' => '🏭',
                                'fuel can' => '🪣',
                                'fuel drum' => '🛢️',
                                default => '📦'
                            };
                            $displayLabel = $container_label ? htmlspecialchars($container_label) : ucfirst(htmlspecialchars($category));
                        ?>
                        <tr>
                            <td><?php echo date('M d, Y H:i', strtotime($transaction['created_at'])); ?></td>
                            <td><strong><?php echo $categoryIcon; ?> <?php echo $displayLabel; ?></strong></td>
                            <td>
                                <span class="badge <?php echo $transType; ?>">
                                    <?php echo $transType === 'added' ? '➕ Added' : '➖ Removed'; ?>
                                </span>
                            </td>
                            <td class="amount-value" style="color: <?php echo $transType === 'added' ? '#4cb050' : '#ff5722'; ?>">
                                <?php echo $transType === 'added' ? '+' : '-'; ?><?php echo number_format($transAmount, 2); ?>
                            </td>
                            <td><?php echo htmlspecialchars($transaction['note'] ?? '-'); ?></td>
                            <td style="color: #00bcd4; font-weight: bold;">
                                <?php 
                                echo number_format($runningBalance, 2);
                                if ($transType === 'added') {
                                    $runningBalance -= $transAmount;
                                } else {
                                    $runningBalance += $transAmount;
                                }
                                ?>L
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- All Transactions History -->
            <div class="table-section">
                <h3>📜 Complete Stock History</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Container Type</th>
                            <th>Type</th>
                            <th>Amount (L)</th>
                            <th>Note</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allStocks as $stock): 
                            $category = $stock['category'] ?? 'fuel tank';
                            $container_label = $stock['container_label'] ?? null;
                            $categoryIcon = match($category) {
                                'fuel tank' => '🏭',
                                'fuel can' => '🪣',
                                'fuel drum' => '🛢️',
                                default => '📦'
                            };
                            $displayLabel = $container_label ? htmlspecialchars($container_label) : ucfirst(htmlspecialchars($category));
                        ?>
                        <tr>
                            <td><?php echo date('M d, Y H:i', strtotime($stock['created_at'])); ?></td>
                            <td><strong><?php echo $categoryIcon; ?> <?php echo $displayLabel; ?></strong></td>
                            <td>
                                <span class="badge <?php echo $stock['transaction_type']; ?>">
                                    <?php echo $stock['transaction_type'] === 'added' ? '➕ Added' : '➖ Removed'; ?>
                                </span>
                            </td>
                            <td class="amount-value" style="color: <?php echo $stock['transaction_type'] === 'added' ? '#4cb050' : '#ff5722'; ?>">
                                <?php echo $stock['transaction_type'] === 'added' ? '+' : '-'; ?><?php echo number_format(floatval($stock['amount']), 2); ?>
                            </td>
                            <td><?php echo htmlspecialchars($stock['note'] ?? '-'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- Add Stock Modal -->
<div class="modal" id="addModal">
    <div class="modal-content">
        <h2>➕ Add Stock</h2>
        <form method="POST">
            <input type="hidden" name="action" value="add_stock">
            <div class="form-group">
                <label for="addCategory">Add To</label>
                <select id="addCategory" name="category" required onchange="toggleContainerLabel()">
                    <option value="fuel tank">🏭 Fill the Fuel Tank (Main Storage)</option>
                    <optgroup label="📦 Other Options" style="color: white;background: #313149;">
                        <option value="fuel can">🪣 Add Fuel Can (Portable)</option>
                        <option value="fuel drum">🛢️ Add Fuel Drum (200L)</option>
                    </optgroup>
                </select>
            </div>
            <div class="form-group" id="containerLabelGroup" style="display: none;">
                <label for="addContainerLabel">Container Label</label>
                <input type="text" id="addContainerLabel" name="container_label" placeholder="Brand">
            </div>
            <div class="form-group">
                <label for="addAmount">Amount (Liters)</label>
                <input type="number" id="addAmount" name="amount" step="0.01" min="0" required placeholder="Enter amount in liters">
            </div>
            <div class="form-group">
                <label for="addNote">Note</label>
                <textarea id="addNote" name="note" placeholder="e.g., Delivery from supplier, tank refill, etc."></textarea>
            </div>
            <div class="modal-buttons">
                <button type="button" class="btn-cancel" onclick="closeAddModal()">Cancel</button>
                <button type="submit" class="btn-add">Add Stock</button>
            </div>
        </form>
    </div>
</div>

<!-- Remove Stock Modal -->
<div class="modal" id="removeModal">
    <div class="modal-content">
        <h2>➖ Remove Stock</h2>
        <form method="POST">
            <input type="hidden" name="action" value="remove_stock">
            <div class="form-group">
                <label for="removeCategory">Take From</label>
                <select id="removeCategory" name="category" required onchange="updateRemoveContainer()">
                    <optgroup label="🏭 MAIN TANK"style="color: white;background: #313149;">
                        <option value="fuel tank" data-label="">Take from Fuel Tank (Primary)</option>
                    </optgroup>
                    <?php if (!empty($availableContainers)): ?>
                        <?php $hasCans = array_filter($availableContainers, fn($c) => $c['category'] === 'fuel can'); ?>
                        <?php $hasDrums = array_filter($availableContainers, fn($c) => $c['category'] === 'fuel drum'); ?>
                        
                        <?php if (!empty($hasCans)): ?>
                        <optgroup label="🪣 FUEL CANS"style="color: white;background: #313149;">
                            <?php foreach ($availableContainers as $container): 
                                if ($container['category'] === 'fuel can'):
                            ?>
                            <option value="fuel can" data-label="<?php echo htmlspecialchars($container['container_label']); ?>" data-amount="<?php echo floatval($container['available_amount']); ?>">
                                <?php echo htmlspecialchars($container['container_label']); ?> — <?php echo number_format(floatval($container['available_amount']), 2); ?>L
                            </option>
                            <?php endif; endforeach; ?>
                        </optgroup>
                        <?php endif; ?>
                        
                        <?php if (!empty($hasDrums)): ?>
                        <optgroup label="🛢️ FUEL DRUMS"style="color: white;background: #313149;">
                            <?php foreach ($availableContainers as $container): 
                                if ($container['category'] === 'fuel drum'):
                            ?>
                            <option value="fuel drum" data-label="<?php echo htmlspecialchars($container['container_label']); ?>" data-amount="<?php echo floatval($container['available_amount']); ?>">
                                <?php echo htmlspecialchars($container['container_label']); ?> — <?php echo number_format(floatval($container['available_amount']), 2); ?>L
                            </option>
                            <?php endif; endforeach; ?>
                        </optgroup>
                        <?php endif; ?>
                    <?php endif; ?>
                </select>
                <input type="hidden" id="removeCategoryLabel" name="container_label" value="">
            </div>
            <div class="form-group">
                <label for="removeAmount">Amount (Liters)</label>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <input type="number" id="removeAmount" name="amount" step="0.01" min="0" required placeholder="Enter amount in liters">
                    <span id="availableDisplay" style="color: #a2a2c2; font-size: 0.9rem; white-space: nowrap;">Max available: ∞</span>
                </div>
            </div>
            <div class="form-group">
                <label for="removeNote">Note</label>
                <textarea id="removeNote" name="note" placeholder="e.g., Issued to vehicle, inventory adjustment, etc."></textarea>
            </div>
            <div class="modal-buttons">
                <button type="button" class="btn-cancel" onclick="closeRemoveModal()">Cancel</button>
                <button type="submit" class="btn-remove">Remove Stock</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Fuel Tank Display Functionality
    const tankCapacity = 1000; // liters
    const currentFuel = <?php echo json_encode($currentStock); ?>;
    
    function updateTankDisplay() {
        const percentage = (currentFuel / tankCapacity) * 100;
        const fillElement = document.getElementById('tankFill');
        const percentageElement = document.getElementById('tankPercentage');
        const infoCurrentElement = document.getElementById('infoCurrent');
        const infoAvailableElement = document.getElementById('infoAvailable');
        const infoPercentageElement = document.getElementById('infoPercentage');
        const tankStatusElement = document.getElementById('tankStatus');
        
        // Update tank fill height with animation
        fillElement.style.height = percentage + '%';
        percentageElement.textContent = Math.round(percentage) + '%';
        infoPercentageElement.textContent = percentage.toFixed(1) + '%';
        infoCurrentElement.textContent = currentFuel.toFixed(2) + ' L';
        infoAvailableElement.textContent = (tankCapacity - currentFuel).toFixed(2) + ' L';
        
        // Update status color and message based on fuel level
        if (percentage > 75) {
            tankStatusElement.textContent = '✓ Tank Full - Fuel Storage Optimal';
            tankStatusElement.className = 'tank-status';
            infoCurrentElement.className = 'info-value good';
            infoAvailableElement.className = 'info-value';
        } else if (percentage > 50) {
            tankStatusElement.textContent = '⚠ Fuel Level Good';
            tankStatusElement.className = 'tank-status';
            infoCurrentElement.className = 'info-value good';
            infoAvailableElement.className = 'info-value';
        } else if (percentage > 25) {
            tankStatusElement.textContent = '⚠ Fuel Level Moderate - Consider Refill';
            tankStatusElement.className = 'tank-status warning';
            infoCurrentElement.className = 'info-value warning';
            infoAvailableElement.className = 'info-value';
        } else if (percentage > 0) {
            tankStatusElement.textContent = '🔴 Critical Low Fuel - Immediate Refill Required!';
            tankStatusElement.className = 'tank-status critical';
            infoCurrentElement.className = 'info-value critical';
            infoAvailableElement.className = 'info-value';
        } else {
            tankStatusElement.textContent = '🔴 Tank Empty!';
            tankStatusElement.className = 'tank-status critical';
            infoCurrentElement.className = 'info-value critical';
            infoAvailableElement.className = 'info-value';
        }
    }
    
    // Initialize tank display on page load
    document.addEventListener('DOMContentLoaded', function() {
        updateTankDisplay();
        
        // Add animation when page loads
        setTimeout(() => {
            const tankFill = document.getElementById('tankFill');
            tankFill.style.transition = 'height 0.8s cubic-bezier(0.34, 1.56, 0.64, 1)';
        }, 100);
    });

    function openAddModal() {
        document.getElementById('addModal').classList.add('show');
        document.getElementById('addAmount').focus();
    }

    function closeAddModal() {
        document.getElementById('addModal').classList.remove('show');
        document.getElementById('addAmount').value = '';
        document.getElementById('addNote').value = '';
        document.getElementById('addCategory').value = 'fuel tank';
        document.getElementById('addContainerLabel').value = '';
        toggleContainerLabel();
    }

    function toggleContainerLabel() {
        const category = document.getElementById('addCategory').value;
        const labelGroup = document.getElementById('containerLabelGroup');
        const labelInput = document.getElementById('addContainerLabel');
        
        if (category === 'fuel can' || category === 'fuel drum') {
            labelGroup.style.display = 'block';
            labelInput.required = true;
        } else {
            labelGroup.style.display = 'none';
            labelInput.required = false;
        }
    }

    function openRemoveModal() {
        document.getElementById('removeModal').classList.add('show');
        document.getElementById('removeAmount').focus();
        updateRemoveContainer();
    }

    function closeRemoveModal() {
        document.getElementById('removeModal').classList.remove('show');
        document.getElementById('removeAmount').value = '';
        document.getElementById('removeNote').value = '';
        document.getElementById('removeCategory').value = 'fuel tank';
        document.getElementById('removeCategoryLabel').value = '';
        updateRemoveContainer();
    }

    function updateAvailableAmount() {
        const selectElement = document.getElementById('removeCategory');
        const selectedOption = selectElement.options[selectElement.selectedIndex];
        const maxAmount = selectedOption.getAttribute('data-amount') ? parseFloat(selectedOption.getAttribute('data-amount')) : null;
        const displayElement = document.getElementById('availableDisplay');
        
        if (maxAmount !== null) {
            displayElement.textContent = 'Max available: ' + maxAmount.toFixed(2) + 'L';
            displayElement.style.color = '#4cb050';
        } else {
            displayElement.textContent = 'Max available: ∞ (Main Tank)';
            displayElement.style.color = '#a2a2c2';
        }
    }

    function updateRemoveContainer() {
        const selectElement = document.getElementById('removeCategory');
        const selectedOption = selectElement.options[selectElement.selectedIndex];
        const containerLabel = selectedOption.getAttribute('data-label') || '';
        
        document.getElementById('removeCategoryLabel').value = containerLabel;
        updateAvailableAmount();
    }

    // Close modal when clicking outside
    document.getElementById('addModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeAddModal();
        }
    });

    document.getElementById('removeModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeRemoveModal();
        }
    });

    // Monthly Chart
    const monthlyCtx = document.getElementById('monthlyChart');
    if (monthlyCtx) {
        const monthlyData = <?php echo json_encode($monthlyAnalytics); ?>;
        
        if (monthlyData && monthlyData.length > 0) {
            const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            const labels = monthlyData.map(d => monthNames[d.month - 1] + ' (' + d.transaction_count + ' trans)');
            const added = monthlyData.map(d => parseFloat(d.added) || 0);
            const removed = monthlyData.map(d => parseFloat(d.removed) || 0);

            new Chart(monthlyCtx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Stock Added',
                            data: added,
                            backgroundColor: 'rgba(76, 175, 80, 0.8)',
                            borderColor: '#4cb050',
                            borderWidth: 1,
                            borderRadius: 8
                        },
                        {
                            label: 'Stock Removed',
                            data: removed,
                            backgroundColor: 'rgba(255, 87, 34, 0.8)',
                            borderColor: '#ff5722',
                            borderWidth: 1,
                            borderRadius: 8
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            labels: { color: '#a2a2c2', padding: 15 }
                        }
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
    }
</script>
</body>
</html>
