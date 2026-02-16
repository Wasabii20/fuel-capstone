<?php
session_start();
require_once 'db_connect.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Initialize prices table if not exists
$createTableSQL = "
CREATE TABLE IF NOT EXISTS price_data (
    id INT PRIMARY KEY AUTO_INCREMENT,
    item_name VARCHAR(100) NOT NULL UNIQUE,
    price DECIMAL(10, 2) NOT NULL,
    unit VARCHAR(50) DEFAULT 'per unit',
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT,
    FOREIGN KEY (updated_by) REFERENCES users(id)
)
";

try {
    $pdo->exec($createTableSQL);
} catch (Exception $e) {
    // Table might already exist
}

// Define default items if they don't exist
$defaultItems = [
    ['Grease Oil', 0.00, 'per liter'],
    ['Lubricating Oil', 0.00, 'per liter'],
    ['Gear Oil', 0.00, 'per liter'],
    ['Fuel Price', 0.00, 'per liter'],
    ['Vehicle Repair - General', 0.00, 'per service'],
];

// Insert default items if not exist
foreach ($defaultItems as $item) {
    try {
        $checkSQL = "SELECT id FROM price_data WHERE item_name = ?";
        $checkStmt = $pdo->prepare($checkSQL);
        $checkStmt->execute([$item[0]]);
        
        if ($checkStmt->rowCount() === 0) {
            $insertSQL = "INSERT INTO price_data (item_name, price, unit) VALUES (?, ?, ?)";
            $insertStmt = $pdo->prepare($insertSQL);
            $insertStmt->execute($item);
        }
    } catch (Exception $e) {
        // Skip if insertion fails
    }
}

// Handle update request
$updateMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_prices'])) {
    try {
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'price_') === 0) {
                $itemId = str_replace('price_', '', $key);
                $price = floatval($value);
                
                $updateSQL = "UPDATE price_data SET price = ?, updated_by = ? WHERE id = ?";
                $updateStmt = $pdo->prepare($updateSQL);
                $updateStmt->execute([$price, $_SESSION['user_id'], $itemId]);
            }
        }
        $updateMessage = "✓ Prices updated successfully!";
    } catch (Exception $e) {
        $updateMessage = "✗ Error updating prices: " . $e->getMessage();
    }
}

// Fetch all prices
try {
    $pricesSQL = "SELECT * FROM price_data ORDER BY id ASC";
    $pricesStmt = $pdo->prepare($pricesSQL);
    $pricesStmt->execute();
    $prices = $pricesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $prices = [];
}

// Calculate total expenses
$totalExpenses = 0;
foreach ($prices as $item) {
    $totalExpenses += $item['price'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BFP - Analytics Data Management</title>
    <link rel="icon" href="ALBUM/favicon_io/favicon-32x32.png" type="image/x-icon" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet"/>
    
    <style>
        :root {
            --bfp-red: #B22222;
            --primary-color: #5d5dff;
            --sidebar-bg: #1e1e2d;
            --dark-bg: #0f0f18;
            --card-bg: #2a2a3e;
            --text-primary: #e4e4e7;
            --text-secondary: #a2a2c2;
            --border-color: rgba(255, 255, 255, 0.05);
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --transition-speed: 0.3s;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', 'Segoe UI', Arial, sans-serif;
            background-color: var(--dark-bg);
            color: var(--text-primary);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .wrapper {
            display: flex;
            flex: 1;
            overflow: hidden;
        }

        main {
            flex: 1;
            padding: 30px 40px;
            overflow-y: auto;
            background-color: var(--sidebar-bg);
        }

        .container {
            max-width: 100%;
            margin: 0;
            padding: 0;
        }

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
            gap: 20px;
        }

        .page-title {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .page-title h1 {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin: 0;
        }

        .page-title .icon {
            font-size: 2.5rem;
        }

        .message {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            animation: slideIn 0.3s ease;
        }

        .message.success {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .message.error {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
        }

        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        .card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            transition: all var(--transition-speed);
        }

        .card:hover {
            border-color: var(--primary-color);
            box-shadow: 0 8px 32px rgba(93, 93, 255, 0.15);
        }

        .card-title {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--border-color);
        }

        .card-title i {
            font-size: 1.5rem;
        }

        .price-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .price-item {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 15px;
            align-items: center;
            padding: 15px;
            background: rgba(93, 93, 255, 0.05);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            transition: all var(--transition-speed);
        }

        .price-item:hover {
            background: rgba(93, 93, 255, 0.1);
            border-color: var(--primary-color);
        }

        .price-item-label {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .price-item-label .name {
            font-weight: 600;
            color: var(--text-primary);
        }

        .price-item-label .unit {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        .price-input-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .price-input {
            flex: 1;
            padding: 10px 15px;
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 1rem;
            font-weight: 600;
            transition: all var(--transition-speed);
        }

        .price-input:focus {
            outline: none;
            border-color: var(--primary-color);
            background: rgba(93, 93, 255, 0.1);
            box-shadow: 0 0 10px rgba(93, 93, 255, 0.2);
        }

        .price-currency {
            color: var(--text-secondary);
            font-weight: 600;
            min-width: 30px;
            text-align: right;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-box {
            background: rgba(93, 93, 255, 0.1);
            border: 1px solid var(--primary-color);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: all var(--transition-speed);
        }

        .stat-box:hover {
            background: rgba(93, 93, 255, 0.15);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(93, 93, 255, 0.2);
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition-speed);
            display: flex;
            align-items: center;
            gap: 8px;
            justify-content: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, #7b7bff 100%);
            color: white;
            flex: 1;
        }

        .btn-primary:hover {
            box-shadow: 0 8px 24px rgba(93, 93, 255, 0.4);
            transform: translateY(-2px);
        }

        .btn-reset {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
        }

        .btn-reset:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--text-primary);
        }

        .no-data {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-secondary);
        }

        .history-section {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 25px;
            margin-top: 25px;
        }

        .history-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        .history-table th {
            padding: 12px;
            text-align: left;
            background: rgba(93, 93, 255, 0.1);
            color: var(--primary-color);
            font-weight: 600;
            border-bottom: 2px solid var(--border-color);
        }

        .history-table td {
            padding: 12px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-secondary);
        }

        .history-table tr:hover td {
            background: rgba(93, 93, 255, 0.05);
        }

        footer {
            display: none;
        }

        @media (max-width: 768px) {
            main {
                padding: 15px;
            }

            .page-header {
                flex-direction: column;
                text-align: center;
            }

            .price-item {
                grid-template-columns: 1fr;
            }

            .price-input-group {
                flex-direction: column;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include("Components/sidebar.php"); ?>

    <main>
        <div class="container">
            <div class="page-header">
                <div class="page-title">
                    <span class="icon">📊</span>
                    <h1>Analytics & Price Management</h1>
                </div>
            </div>

            <?php if (!empty($updateMessage)): ?>
                <div class="message <?php echo strpos($updateMessage, '✓') === 0 ? 'success' : 'error'; ?>">
                    <i class="fas fa-<?php echo strpos($updateMessage, '✓') === 0 ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($updateMessage); ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="pricesForm">
                <div class="content-grid">
                    <!-- Statistics Card -->
                    <div class="card">
                        <div class="card-title">
                            <i class="fas fa-chart-pie"></i>
                            Expense Overview
                        </div>
                        <div class="stats-grid">
                            <div class="stat-box">
                                <div class="stat-label">Total Items</div>
                                <div class="stat-value"><?php echo count($prices); ?></div>
                            </div>
                            <div class="stat-box">
                                <div class="stat-label">Total Value</div>
                                <div class="stat-value">₱<?php echo number_format($totalExpenses, 2); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Info Card -->
                    <div class="card">
                        <div class="card-title">
                            <i class="fas fa-info-circle"></i>
                            Information
                        </div>
                        <div style="color: var(--text-secondary); line-height: 1.8;">
                            <p><strong>📝 Last Updated:</strong></p>
                            <p style="font-size: 0.9rem; margin-bottom: 15px;">
                                <?php 
                                $lastUpdated = $prices[0]['last_updated'] ?? null;
                                echo $lastUpdated ? date('F d, Y @ g:i A', strtotime($lastUpdated)) : 'Never';
                                ?>
                            </p>
                            <p><strong>👤 Current User:</strong> <?php 
                                $firstName = isset($_SESSION['first_name']) ? htmlspecialchars($_SESSION['first_name']) : '';
                                $lastName = isset($_SESSION['last_name']) ? htmlspecialchars($_SESSION['last_name']) : '';
                                $displayName = trim($firstName . ' ' . $lastName);
                                echo !empty($displayName) ? $displayName : htmlspecialchars($_SESSION['username'] ?? 'Unknown User');
                            ?></p>
                        </div>
                    </div>
                </div>

                <!-- Price Data Card -->
                <div class="card">
                    <div class="card-title">
                        <i class="fas fa-tag"></i>
                        Edit Market Prices
                    </div>

                    <?php if (!empty($prices)): ?>
                        <div class="price-list">
                            <?php foreach ($prices as $price): ?>
                                <div class="price-item">
                                    <div class="price-item-label">
                                        <span class="name"><?php echo htmlspecialchars($price['item_name']); ?></span>
                                        <span class="unit"><?php echo htmlspecialchars($price['unit']); ?></span>
                                    </div>
                                    <div class="price-input-group">
                                        <span class="price-currency">₱</span>
                                        <input 
                                            type="number" 
                                            name="price_<?php echo $price['id']; ?>" 
                                            class="price-input" 
                                            value="<?php echo number_format($price['price'], 2, '.', ''); ?>" 
                                            step="0.01" 
                                            min="0" 
                                            placeholder="0.00"
                                        >
                                    </div>
                                    <div style="text-align: right; color: var(--text-secondary); font-size: 0.9rem;">
                                        Updated:<br><?php echo date('M d, Y', strtotime($price['last_updated'])); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="form-actions">
                            <button type="submit" name="update_prices" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                            <button type="reset" class="btn btn-reset">
                                <i class="fas fa-redo"></i> Reset
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 10px;"></i>
                            <p>No price data available</p>
                        </div>
                    <?php endif; ?>
                </div>
            </form>

            <!-- History Section -->
            <div class="history-section">
                <div class="card-title" style="margin-bottom: 15px;">
                    <i class="fas fa-history"></i>
                    Recent Updates
                </div>
                <div style="overflow-x: auto;">
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>Item Name</th>
                                <th>Current Price</th>
                                <th>Unit</th>
                                <th>Last Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($prices)): ?>
                                <?php foreach ($prices as $price): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($price['item_name']); ?></td>
                                        <td><strong>₱<?php echo number_format($price['price'], 2); ?></strong></td>
                                        <td><?php echo htmlspecialchars($price['unit']); ?></td>
                                        <td><?php echo date('F d, Y @ g:i A', strtotime($price['last_updated'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; color: var(--text-secondary);">No data available</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
    // Form submission confirmation
    document.getElementById('pricesForm').addEventListener('submit', function(e) {
        if (!confirm('Are you sure you want to update these prices? This action cannot be easily undone.')) {
            e.preventDefault();
        }
    });

    // Auto-save indicator
    const priceInputs = document.querySelectorAll('.price-input');
    let hasChanges = false;

    priceInputs.forEach(input => {
        input.addEventListener('change', () => {
            hasChanges = true;
            console.log('Price data modified. Remember to save!');
        });
    });

    window.addEventListener('beforeunload', (e) => {
        if (hasChanges) {
            e.preventDefault();
            e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
        }
    });
</script>

</body>
</html>
