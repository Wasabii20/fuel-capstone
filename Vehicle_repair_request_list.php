<?php
session_start();

// Check login
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

require_once 'db_connect.php';

// Handle new repair request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_request') {
    $vehicleId = $_POST['vehicle_id'];
    $repairType = $_POST['repair_type'];
    $description = $_POST['description'];
    $priority = $_POST['priority'] ?? 'medium';
    $takenByPersonnel = $_POST['taken_by_personnel'] ?? '';
    $repairLocation = $_POST['repair_location'] ?? '';
    $actualRepairDate = $_POST['actual_repair_date'] ?? null;
    $firstName = $_SESSION['first_name'] ?? $_SESSION['username'] ?? 'User';
    $lastName = $_SESSION['last_name'] ?? '';
    $requesterName = trim($firstName . ' ' . $lastName);
    $userId = $_SESSION['user_id'];
    
    $insertQuery = "INSERT INTO vehicle_repairs 
                    (vehicle_id, user_id, repair_type, description, status, priority, requester_name, taken_by_personnel, repair_location, actual_repair_date, requested_date)
                    VALUES (:vehicle_id, :user_id, :repair_type, :description, :status, :priority, :requester_name, :taken_by_personnel, :repair_location, :actual_repair_date, NOW())";
    
    try {
        $insertStmt = $pdo->prepare($insertQuery);
        $insertStmt->execute([
            ':vehicle_id' => $vehicleId,
            ':user_id' => $userId,
            ':repair_type' => $repairType,
            ':description' => $description,
            ':status' => 'pending',
            ':priority' => $priority,
            ':requester_name' => $requesterName,
            ':taken_by_personnel' => $takenByPersonnel ?: null,
            ':repair_location' => $repairLocation ?: null,
            ':actual_repair_date' => $actualRepairDate ?: null
        ]);
        
        // Store success message in session and redirect
        $_SESSION['repair_success'] = true;
        header('Location: ' . $_SERVER['PHP_SELF'] . '?status=pending');
        exit();
    } catch (Exception $e) {
        $error = "Error submitting repair request: " . $e->getMessage();
    }
}

// Check if form was successfully submitted
$success = '';
if (isset($_SESSION['repair_success']) && $_SESSION['repair_success']) {
    $success = "✓ Repair request submitted successfully!";
    // Clear the success message so it doesn't show on next page load
    unset($_SESSION['repair_success']);
}

// Get filter status
$filterStatus = isset($_GET['status']) ? $_GET['status'] : 'pending';

// Build query for repair requests
$statusFilter = '';
if ($filterStatus !== 'all') {
    $statusFilter = "WHERE vr.status = :status";
}

$requestQuery = "
    SELECT 
        vr.id,
        vr.vehicle_id,
        v.vehicle_no,
        v.vehicle_type,
        vr.repair_type,
        vr.description,
        vr.status,
        vr.priority,
        vr.requester_name,
        vr.taken_by_personnel,
        vr.repair_location,
        vr.actual_repair_date,
        vr.notes,
        vr.requested_date,
        vr.approval_date,
        vr.completed_date
    FROM vehicle_repairs vr
    LEFT JOIN vehicles v ON vr.vehicle_id = v.id
    $statusFilter
    ORDER BY vr.requested_date DESC
";

try {
    $requestStmt = $pdo->prepare($requestQuery);
    if ($filterStatus !== 'all') {
        $requestStmt->bindValue(':status', $filterStatus, PDO::PARAM_STR);
    }
    $requestStmt->execute();
    $requests = $requestStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $requests = [];
    $error = "Error fetching requests: " . $e->getMessage();
}

// Get statistics
$statsQuery = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_count,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count
FROM vehicle_repairs";

try {
    $statsResult = $pdo->query($statsQuery);
    $stats = $statsResult->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $stats = ['total' => 0, 'pending_count' => 0, 'in_progress_count' => 0, 'completed_count' => 0];
}

// Get vehicles for dropdown
$vehiclesQuery = "SELECT id, vehicle_no, vehicle_type FROM vehicles ORDER BY vehicle_no";
$vehiclesStmt = $pdo->query($vehiclesQuery);
$vehicles = $vehiclesStmt->fetchAll(PDO::FETCH_ASSOC);

// Get common repair locations and personnel from database
$locationsQuery = "SELECT DISTINCT repair_location FROM vehicle_repairs WHERE repair_location IS NOT NULL AND repair_location != '' ORDER BY repair_location";
$locationsStmt = $pdo->query($locationsQuery);
$locations = $locationsStmt->fetchAll(PDO::FETCH_COLUMN);

$personnelQuery = "SELECT DISTINCT taken_by_personnel FROM vehicle_repairs WHERE taken_by_personnel IS NOT NULL AND taken_by_personnel != '' ORDER BY taken_by_personnel";
$personnelStmt = $pdo->query($personnelQuery);
$personnel = $personnelStmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, maximum-scale=5.0">
    <title>Vehicle Repair Requests - BFP Fuel System</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
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
        }

        .filter-btn, .btn-new-request {
            padding: 10px 20px;
            border-radius: 8px;
            border: 1px solid rgba(93, 93, 255, 0.2);
            background: rgba(93, 93, 255, 0.08);
            color: #a2a2c2;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            font-weight: 500;
            min-height: 44px;
            min-width: 44px;
            display: flex;
            align-items: center;
            touch-action: manipulation;
        }

        .filter-btn:hover, .filter-btn.active, .btn-new-request:hover {
            background: rgba(93, 93, 255, 0.2);
            border-color: #5d5dff;
            color: #fff;
        }

        .btn-new-request {
            background: linear-gradient(135deg, #5d5dff 0%, #4a4a9f 100%);
            color: white;
            border: none;
            margin-left: auto;
            justify-content: center;
        }
        
        .btn-new-request:active {
            transform: scale(0.98);
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
            display: none;
        }
        
        table.show-on-desktop {
            display: table;
        }
        
        /* Mobile card view */
        .table-card {
            display: none;
            background: rgba(93, 93, 255, 0.08);
            border: 1px solid rgba(93, 93, 255, 0.2);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 12px;
            gap: 12px;
        }
        
        .table-card.show-on-mobile {
            display: grid;
        }
        
        .card-row {
            display: grid;
            grid-template-columns: 120px 1fr;
            gap: 10px;
            padding: 8px 0;
            border-bottom: 1px solid rgba(93, 93, 255, 0.1);
            align-items: center;
        }
        
        .card-row:last-child {
            border-bottom: none;
        }
        
        .card-label {
            color: #a2a2c2;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .card-value {
            color: #e4e6eb;
            font-size: 0.95rem;
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

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: bold;
        }

        .status-pending {
            background: rgba(255, 193, 7, 0.2);
            color: #ffc107;
        }

        .status-completed {
            background: rgba(76, 175, 80, 0.2);
            color: #4cb050;
        }

        .status-in-progress {
            background: rgba(52, 152, 219, 0.2);
            color: #3498db;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: #1e1e2d;
            border: 1px solid rgba(93, 93, 255, 0.2);
            border-radius: 16px;
            padding: 30px;
            width: 90%;
            max-width: 700px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            margin-bottom: 20px;
        }

        .modal-header h2 {
            color: #fff;
            font-size: 1.5rem;
            margin-bottom: 10px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            color: #a2a2c2;
            font-weight: 500;
            margin-bottom: 8px;
        }

        input, select, textarea {
            width: 100%;
            padding: 12px;
            background: #0f0f1e;
            border: 1px solid rgba(93, 93, 255, 0.2);
            border-radius: 8px;
            color: #e4e6eb;
            box-sizing: border-box;
            font-family: inherit;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #5d5dff;
            box-shadow: 0 0 8px rgba(93, 93, 255, 0.2);
        }

        textarea {
            resize: vertical;
            min-height: 80px;
        }

        .modal-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        button {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            min-height: 44px;
            touch-action: manipulation;
        }

        .btn-primary {
            background: linear-gradient(135deg, #5d5dff 0%, #4a4a9f 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(93, 93, 255, 0.4);
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }

        .btn-cancel {
            background: rgba(255, 255, 255, 0.1);
            color: #a2a2c2;
        }

        .btn-cancel:hover {
            background: rgba(255, 255, 255, 0.15);
        }
        
        .btn-cancel:active {
            background: rgba(255, 255, 255, 0.2);
        }

        .success-message {
            background: rgba(76, 175, 80, 0.1);
            border: 1px solid #4cb050;
            color: #4cb050;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .error-message {
            background: rgba(244, 67, 54, 0.1);
            border: 1px solid #f44336;
            color: #f44336;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .vehicle-name {
            font-weight: 500;
            color: #5d5dff;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .stat-card {
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            border-color: #5d5dff;
            box-shadow: 0 8px 20px rgba(93, 93, 255, 0.3);
        }

        .stat-card.active {
            background: rgba(93, 93, 255, 0.15);
            border-color: #5d5dff;
            box-shadow: 0 8px 20px rgba(93, 93, 255, 0.3);
        }

        .request-page {
            display: none;
        }

        .request-page.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @media (max-width: 768px) {
            main {
                padding: 15px;
            }
            
            .header h1 {
                font-size: 1.8rem;
            }
            
            .header p {
                font-size: 0.95rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .stat-card {
                padding: 15px;
            }
            
            .stat-value {
                font-size: 1.8rem;
            }

            table.show-on-desktop {
                display: none;
            }
            
            .table-card.show-on-mobile {
                display: grid;
            }

            .modal-content {
                width: 95%;
                padding: 20px;
                margin: 20px auto;
            }
            
            .modal-header h2 {
                font-size: 1.3rem;
            }
            
            .modal-buttons {
                flex-direction: column;
                gap: 10px;
            }
            
            .modal-buttons button {
                width: 100%;
                padding: 12px;
                font-size: 1rem;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
            
            .form-group {
                margin-bottom: 15px;
            }
            
            input, select, textarea {
                font-size: 16px;
                padding: 12px;
            }

            .filter-section {
                flex-direction: column;
                gap: 10px;
            }

            .btn-new-request {
                margin-left: 0;
                width: 100%;
                justify-content: center;
            }
            
            .filter-btn {
                flex: 1;
                justify-content: center;
                min-height: 44px;
            }
            
            .table-section {
                padding: 15px;
            }
            
            .table-section h3 {
                font-size: 1rem;
                margin-bottom: 15px;
            }
        }
        
        @media (max-width: 480px) {
            main {
                padding: 12px;
            }
            
            .header h1 {
                font-size: 1.5rem;
                margin-bottom: 8px;
            }
            
            .header p {
                font-size: 0.9rem;
            }
            
            .filter-section {
                gap: 8px;
                margin-bottom: 15px;
            }
            
            .filter-btn {
                font-size: 0.85rem;
                padding: 8px 12px;
            }
            
            .stats-grid {
                gap: 12px;
                margin-bottom: 20px;
            }
            
            .stat-card {
                padding: 12px;
            }
            
            .stat-label {
                font-size: 0.8rem;
            }
            
            .stat-value {
                font-size: 1.5rem;
                margin: 5px 0;
            }
            
            .stat-unit {
                font-size: 0.75rem;
            }
            
            .table-section {
                padding: 12px;
                margin-bottom: 15px;
            }
            
            .table-card {
                padding: 12px;
                margin-bottom: 10px;
            }
            
            .card-row {
                grid-template-columns: 100px 1fr;
                gap: 8px;
                padding: 6px 0;
            }
            
            .card-label {
                font-size: 0.8rem;
            }
            
            .card-value {
                font-size: 0.9rem;
            }
            
            .status-badge {
                font-size: 0.7rem;
                padding: 3px 8px;
            }
            
            .modal-content {
                width: 98%;
                padding: 15px;
                max-height: 95vh;
            }
            
            .modal-header h2 {
                font-size: 1.1rem;
            }
            
            label {
                font-size: 0.95rem;
                margin-bottom: 6px;
            }
            
            input, select, textarea {
                font-size: 16px;
                padding: 12px;
            }
            
            .modal-buttons {
                gap: 8px;
            }
            
            .modal-buttons button {
                padding: 12px;
                font-size: 0.95rem;
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
                <h1>🔧 Vehicle Repair Requests</h1>
                <p>Submit and track vehicle repair requests</p>
            </div>

            <?php if (isset($success)): ?>
                <div class="success-message">
                    <span>✓</span>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="error-message">
                    <span>✕</span>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <!-- Filter and Action Section -->
            <div class="filter-section">
                <a href="?status=pending" class="filter-btn <?php echo $filterStatus === 'pending' ? 'active' : ''; ?>">⏳ Pending</a>
                <a href="?status=in_progress" class="filter-btn <?php echo $filterStatus === 'in_progress' ? 'active' : ''; ?>">🔨 In Progress</a>
                <a href="?status=completed" class="filter-btn <?php echo $filterStatus === 'completed' ? 'active' : ''; ?>">✅ Completed</a>
                <a href="?status=all" class="filter-btn <?php echo $filterStatus === 'all' ? 'active' : ''; ?>">📋 All</a>
                <button class="btn-new-request" onclick="openRequestModal()">➕ New Request</button>
            </div>

            <!-- Statistics Cards as Navigation -->
            <div class="stats-grid">
                <div class="stat-card active" onclick="showRequestPage('all')">
                    <div class="stat-label">Total Requests</div>
                    <div class="stat-value"><?php echo $stats['total']; ?></div>
                    <div class="stat-unit">All time</div>
                </div>

                <div class="stat-card" onclick="showRequestPage('pending')">
                    <div class="stat-label">Pending Requests</div>
                    <div class="stat-value"><?php echo $stats['pending_count']; ?></div>
                    <div class="stat-unit">Awaiting approval</div>
                </div>

                <div class="stat-card" onclick="showRequestPage('in_progress')">
                    <div class="stat-label">In Progress</div>
                    <div class="stat-value"><?php echo $stats['in_progress_count']; ?></div>
                    <div class="stat-unit">Being serviced</div>
                </div>

                <div class="stat-card" onclick="showRequestPage('completed')">
                    <div class="stat-label">Completed</div>
                    <div class="stat-value"><?php echo $stats['completed_count']; ?></div>
                    <div class="stat-unit">Finished repairs</div>
                </div>
            </div>

            <!-- Requests Table Pages -->
            <!-- Page 1: All Requests -->
            <div class="request-page active" id="page-all">
                <div class="table-section">
                    <h3>📋 All Repair Requests</h3>
                    <!-- Desktop Table -->
                    <table class="show-on-desktop">
                        <thead>
                            <tr>
                                <th>Date Requested</th>
                                <th>Vehicle</th>
                                <th>Repair Type</th>
                                <th>Priority</th>
                                <th>Requester</th>
                                <th>Location</th>
                                <th>Personnel</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Get all requests
                            $allQuery = "
                                SELECT 
                                    vr.id,
                                    vr.vehicle_id,
                                    v.vehicle_no,
                                    v.vehicle_type,
                                    vr.repair_type,
                                    vr.description,
                                    vr.status,
                                    vr.priority,
                                    vr.requester_name,
                                    vr.taken_by_personnel,
                                    vr.repair_location,
                                    vr.actual_repair_date,
                                    vr.notes,
                                    vr.requested_date,
                                    vr.approval_date,
                                    vr.completed_date
                                FROM vehicle_repairs vr
                                LEFT JOIN vehicles v ON vr.vehicle_id = v.id
                                ORDER BY vr.requested_date DESC
                            ";
                            try {
                                $allStmt = $pdo->prepare($allQuery);
                                $allStmt->execute();
                                $allRequests = $allStmt->fetchAll(PDO::FETCH_ASSOC);
                            } catch (Exception $e) {
                                $allRequests = [];
                            }
                            
                            if (count($allRequests) > 0):
                                foreach ($allRequests as $request): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($request['requested_date'])); ?></td>
                                    <td>
                                        <div class="vehicle-name"><?php echo htmlspecialchars($request['vehicle_type'] ?? 'Unknown'); ?></div>
                                        <small><?php echo htmlspecialchars($request['vehicle_no']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($request['repair_type']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($request['priority']); ?>">
                                            <?php echo ucfirst(htmlspecialchars($request['priority'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($request['requester_name'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($request['repair_location'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($request['taken_by_personnel'] ?? '-'); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower(str_replace('_', '-', $request['status'])); ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($request['status']))); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach;
                            else: ?>
                                <tr><td colspan="8" style="text-align: center; padding: 30px; color: #a2a2c2;">No requests found</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    
                    <!-- Mobile Card View -->
                    <div id="all-cards">
                        <?php if (count($allRequests) > 0): ?>
                            <?php foreach ($allRequests as $request): ?>
                                <div class="table-card show-on-mobile">
                                    <div class="card-row">
                                        <div class="card-label">Date</div>
                                        <div class="card-value"><?php echo date('M d, Y', strtotime($request['requested_date'])); ?></div>
                                    </div>
                                    <div class="card-row">
                                        <div class="card-label">Vehicle</div>
                                        <div class="card-value">
                                            <strong><?php echo htmlspecialchars($request['vehicle_type'] ?? 'Unknown'); ?></strong><br>
                                            <small style="color: #7e8299;"><?php echo htmlspecialchars($request['vehicle_no']); ?></small>
                                        </div>
                                    </div>
                                    <div class="card-row">
                                        <div class="card-label">Repair Type</div>
                                        <div class="card-value"><?php echo htmlspecialchars($request['repair_type']); ?></div>
                                    </div>
                                    <div class="card-row">
                                        <div class="card-label">Priority</div>
                                        <div class="card-value">
                                            <span class="status-badge status-<?php echo strtolower($request['priority']); ?>">
                                                <?php echo ucfirst(htmlspecialchars($request['priority'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="card-row">
                                        <div class="card-label">Requester</div>
                                        <div class="card-value"><?php echo htmlspecialchars($request['requester_name'] ?? '-'); ?></div>
                                    </div>
                                    <div class="card-row">
                                        <div class="card-label">Location</div>
                                        <div class="card-value"><?php echo htmlspecialchars($request['repair_location'] ?? '-'); ?></div>
                                    </div>
                                    <div class="card-row">
                                        <div class="card-label">Personnel</div>
                                        <div class="card-value"><?php echo htmlspecialchars($request['taken_by_personnel'] ?? '-'); ?></div>
                                    </div>
                                    <div class="card-row">
                                        <div class="card-label">Status</div>
                                        <div class="card-value">
                                            <span class="status-badge status-<?php echo strtolower(str_replace('_', '-', $request['status'])); ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($request['status']))); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="text-align: center; padding: 30px; color: #a2a2c2;">No requests found</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Page 2: Pending Requests -->
            <div class="request-page" id="page-pending">
                <div class="table-section">
                    <h3>⏳ Pending Repair Requests</h3>
                    <!-- Desktop Table -->
                    <table class="show-on-desktop">
                        <thead>
                            <tr>
                                <th>Date Requested</th>
                                <th>Vehicle</th>
                                <th>Repair Type</th>
                                <th>Priority</th>
                                <th>Requester</th>
                                <th>Location</th>
                                <th>Personnel</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if (count($requests) > 0 && $filterStatus === 'pending'):
                                foreach ($requests as $request): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($request['requested_date'])); ?></td>
                                    <td>
                                        <div class="vehicle-name"><?php echo htmlspecialchars($request['vehicle_type'] ?? 'Unknown'); ?></div>
                                        <small><?php echo htmlspecialchars($request['vehicle_no']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($request['repair_type']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($request['priority']); ?>">
                                            <?php echo ucfirst(htmlspecialchars($request['priority'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($request['requester_name'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($request['repair_location'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($request['taken_by_personnel'] ?? '-'); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower(str_replace('_', '-', $request['status'])); ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($request['status']))); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach;
                            else:
                                // Get pending requests if not already loaded
                                $pendingQuery = "
                                    SELECT 
                                        vr.id, vr.vehicle_id, v.vehicle_no, v.vehicle_type, vr.repair_type, vr.description,
                                        vr.status, vr.priority, vr.requester_name, vr.taken_by_personnel, vr.repair_location,
                                        vr.actual_repair_date, vr.notes, vr.requested_date, vr.approval_date, vr.completed_date
                                    FROM vehicle_repairs vr
                                    LEFT JOIN vehicles v ON vr.vehicle_id = v.id
                                    WHERE vr.status = 'pending'
                                    ORDER BY vr.requested_date DESC
                                ";
                                try {
                                    $pendingStmt = $pdo->prepare($pendingQuery);
                                    $pendingStmt->execute();
                                    $pendingRequests = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    if (count($pendingRequests) > 0):
                                        foreach ($pendingRequests as $request): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($request['requested_date'])); ?></td>
                                            <td>
                                                <div class="vehicle-name"><?php echo htmlspecialchars($request['vehicle_type'] ?? 'Unknown'); ?></div>
                                                <small><?php echo htmlspecialchars($request['vehicle_no']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($request['repair_type']); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo strtolower($request['priority']); ?>">
                                                    <?php echo ucfirst(htmlspecialchars($request['priority'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($request['requester_name'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($request['repair_location'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($request['taken_by_personnel'] ?? '-'); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo strtolower(str_replace('_', '-', $request['status'])); ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($request['status']))); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach;
                                    else: ?>
                                        <tr><td colspan="8" style="text-align: center; padding: 30px; color: #a2a2c2;">No pending requests</td></tr>
                                    <?php endif;
                                } catch (Exception $e) { ?>
                                    <tr><td colspan="8" style="text-align: center; padding: 30px; color: #a2a2c2;">No pending requests</td></tr>
                                <?php }
                            endif; ?>
                        </tbody>
                    </table>
                    
                    <!-- Mobile Card View -->
                    <div id="pending-cards">
                        <?php 
                        $pendingQuery = "
                            SELECT 
                                vr.id, vr.vehicle_id, v.vehicle_no, v.vehicle_type, vr.repair_type, vr.description,
                                vr.status, vr.priority, vr.requester_name, vr.taken_by_personnel, vr.repair_location,
                                vr.actual_repair_date, vr.notes, vr.requested_date, vr.approval_date, vr.completed_date
                            FROM vehicle_repairs vr
                            LEFT JOIN vehicles v ON vr.vehicle_id = v.id
                            WHERE vr.status = 'pending'
                            ORDER BY vr.requested_date DESC
                        ";
                        try {
                            $pendingStmt = $pdo->prepare($pendingQuery);
                            $pendingStmt->execute();
                            $pendingRequests = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            if (count($pendingRequests) > 0):
                                foreach ($pendingRequests as $request): ?>
                                <div class="table-card show-on-mobile">
                                    <div class="card-row">
                                        <div class="card-label">Date</div>
                                        <div class="card-value"><?php echo date('M d, Y', strtotime($request['requested_date'])); ?></div>
                                    </div>
                                    <div class="card-row">
                                        <div class="card-label">Vehicle</div>
                                        <div class="card-value">
                                            <strong><?php echo htmlspecialchars($request['vehicle_type'] ?? 'Unknown'); ?></strong><br>
                                            <small style="color: #7e8299;"><?php echo htmlspecialchars($request['vehicle_no']); ?></small>
                                        </div>
                                    </div>
                                    <div class="card-row">
                                        <div class="card-label">Repair Type</div>
                                        <div class="card-value"><?php echo htmlspecialchars($request['repair_type']); ?></div>
                                    </div>
                                    <div class="card-row">
                                        <div class="card-label">Priority</div>
                                        <div class="card-value">
                                            <span class="status-badge status-<?php echo strtolower($request['priority']); ?>">
                                                <?php echo ucfirst(htmlspecialchars($request['priority'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="card-row">
                                        <div class="card-label">Requester</div>
                                        <div class="card-value"><?php echo htmlspecialchars($request['requester_name'] ?? '-'); ?></div>
                                    </div>
                                    <div class="card-row">
                                        <div class="card-label">Location</div>
                                        <div class="card-value"><?php echo htmlspecialchars($request['repair_location'] ?? '-'); ?></div>
                                    </div>
                                    <div class="card-row">
                                        <div class="card-label">Personnel</div>
                                        <div class="card-value"><?php echo htmlspecialchars($request['taken_by_personnel'] ?? '-'); ?></div>
                                    </div>
                                </div>
                            <?php endforeach;
                            else: ?>
                                <div style="text-align: center; padding: 30px; color: #a2a2c2;">No pending requests</div>
                            <?php endif;
                        } catch (Exception $e) { ?>
                            <div style="text-align: center; padding: 30px; color: #a2a2c2;">No pending requests</div>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <!-- Page 3: In Progress Requests -->
            <div class="request-page" id="page-in_progress">
                <div class="table-section">
                    <h3>🔨 In Progress Repair Requests</h3>
                    <!-- Desktop Table -->
                    <table class="show-on-desktop">
                        <thead>
                            <tr>
                                <th>Date Requested</th>
                                <th>Vehicle</th>
                                <th>Repair Type</th>
                                <th>Priority</th>
                                <th>Requester</th>
                                <th>Location</th>
                                <th>Personnel</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $inProgressQuery = "
                                SELECT 
                                    vr.id, vr.vehicle_id, v.vehicle_no, v.vehicle_type, vr.repair_type, vr.description,
                                    vr.status, vr.priority, vr.requester_name, vr.taken_by_personnel, vr.repair_location,
                                    vr.actual_repair_date, vr.notes, vr.requested_date, vr.approval_date, vr.completed_date
                                FROM vehicle_repairs vr
                                LEFT JOIN vehicles v ON vr.vehicle_id = v.id
                                WHERE vr.status = 'in_progress'
                                ORDER BY vr.requested_date DESC
                            ";
                            try {
                                $inProgressStmt = $pdo->prepare($inProgressQuery);
                                $inProgressStmt->execute();
                                $inProgressRequests = $inProgressStmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                if (count($inProgressRequests) > 0):
                                    foreach ($inProgressRequests as $request): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($request['requested_date'])); ?></td>
                                        <td>
                                            <div class="vehicle-name"><?php echo htmlspecialchars($request['vehicle_type'] ?? 'Unknown'); ?></div>
                                            <small><?php echo htmlspecialchars($request['vehicle_no']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($request['repair_type']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower($request['priority']); ?>">
                                                <?php echo ucfirst(htmlspecialchars($request['priority'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($request['requester_name'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($request['repair_location'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($request['taken_by_personnel'] ?? '-'); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower(str_replace('_', '-', $request['status'])); ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($request['status']))); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach;
                                else: ?>
                                    <tr><td colspan="8" style="text-align: center; padding: 30px; color: #a2a2c2;">No in-progress requests</td></tr>
                                <?php endif;
                            } catch (Exception $e) { ?>
                                <tr><td colspan="8" style="text-align: center; padding: 30px; color: #a2a2c2;">No in-progress requests</td></tr>
                            <?php } ?>
                        </tbody>
                    </table>
                    
                    <!-- Mobile Card View -->
                    <div id="inprogress-cards">
                        <?php 
                        $inProgressQuery = "
                            SELECT 
                                vr.id, vr.vehicle_id, v.vehicle_no, v.vehicle_type, vr.repair_type, vr.description,
                                vr.status, vr.priority, vr.requester_name, vr.taken_by_personnel, vr.repair_location,
                                vr.actual_repair_date, vr.notes, vr.requested_date, vr.approval_date, vr.completed_date
                            FROM vehicle_repairs vr
                            LEFT JOIN vehicles v ON vr.vehicle_id = v.id
                            WHERE vr.status = 'in_progress'
                            ORDER BY vr.requested_date DESC
                        ";
                        try {
                            $inProgressStmt = $pdo->prepare($inProgressQuery);
                            $inProgressStmt->execute();
                            $inProgressRequests = $inProgressStmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            if (count($inProgressRequests) > 0):
                                foreach ($inProgressRequests as $request): ?>
                                <div class="table-card show-on-mobile">
                                    <div class="card-row">
                                        <div class="card-label">Date</div>
                                        <div class="card-value"><?php echo date('M d, Y', strtotime($request['requested_date'])); ?></div>
                                    </div>
                                    <div class="card-row">
                                        <div class="card-label">Vehicle</div>
                                        <div class="card-value">
                                            <strong><?php echo htmlspecialchars($request['vehicle_type'] ?? 'Unknown'); ?></strong><br>
                                            <small style="color: #7e8299;"><?php echo htmlspecialchars($request['vehicle_no']); ?></small>
                                        </div>
                                    </div>
                                    <div class="card-row">
                                        <div class="card-label">Repair Type</div>
                                        <div class="card-value"><?php echo htmlspecialchars($request['repair_type']); ?></div>
                                    </div>
                                    <div class="card-row">
                                        <div class="card-label">Priority</div>
                                        <div class="card-value">
                                            <span class="status-badge status-<?php echo strtolower($request['priority']); ?>">
                                                <?php echo ucfirst(htmlspecialchars($request['priority'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="card-row">
                                        <div class="card-label">Requester</div>
                                        <div class="card-value"><?php echo htmlspecialchars($request['requester_name'] ?? '-'); ?></div>
                                    </div>
                                    <div class="card-row">
                                        <div class="card-label">Location</div>
                                        <div class="card-value"><?php echo htmlspecialchars($request['repair_location'] ?? '-'); ?></div>
                                    </div>
                                    <div class="card-row">
                                        <div class="card-label">Personnel</div>
                                        <div class="card-value"><?php echo htmlspecialchars($request['taken_by_personnel'] ?? '-'); ?></div>
                                    </div>
                                </div>
                            <?php endforeach;
                            else: ?>
                                <div style="text-align: center; padding: 30px; color: #a2a2c2;">No in-progress requests</div>
                            <?php endif;
                        } catch (Exception $e) { ?>
                            <div style="text-align: center; padding: 30px; color: #a2a2c2;">No in-progress requests</div>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <!-- Page 4: Completed Requests -->
            <div class="request-page" id="page-completed">
                <div class="table-section">
                    <h3>✅ Completed Repair Requests</h3>
                    <!-- Desktop Table -->
                    <table class="show-on-desktop">
                        <thead>
                            <tr>
                                <th>Date Requested</th>
                                <th>Vehicle</th>
                                <th>Repair Type</th>
                                <th>Priority</th>
                                <th>Requester</th>
                                <th>Location</th>
                                <th>Personnel</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $completedQuery = "
                                SELECT 
                                    vr.id, vr.vehicle_id, v.vehicle_no, v.vehicle_type, vr.repair_type, vr.description,
                                    vr.status, vr.priority, vr.requester_name, vr.taken_by_personnel, vr.repair_location,
                                    vr.actual_repair_date, vr.notes, vr.requested_date, vr.approval_date, vr.completed_date
                                FROM vehicle_repairs vr
                                LEFT JOIN vehicles v ON vr.vehicle_id = v.id
                                WHERE vr.status = 'completed'
                                ORDER BY vr.completed_date DESC
                            ";
                            try {
                                $completedStmt = $pdo->prepare($completedQuery);
                                $completedStmt->execute();
                                $completedRequests = $completedStmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                if (count($completedRequests) > 0):
                                    foreach ($completedRequests as $request): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($request['requested_date'])); ?></td>
                                        <td>
                                            <div class="vehicle-name"><?php echo htmlspecialchars($request['vehicle_type'] ?? 'Unknown'); ?></div>
                                            <small><?php echo htmlspecialchars($request['vehicle_no']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($request['repair_type']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower($request['priority']); ?>">
                                                <?php echo ucfirst(htmlspecialchars($request['priority'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($request['requester_name'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($request['repair_location'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($request['taken_by_personnel'] ?? '-'); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower(str_replace('_', '-', $request['status'])); ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($request['status']))); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach;
                                else: ?>
                                    <tr><td colspan="8" style="text-align: center; padding: 30px; color: #a2a2c2;">No completed requests</td></tr>
                                <?php endif;
                            } catch (Exception $e) { ?>
                                <tr><td colspan="8" style="text-align: center; padding: 30px; color: #a2a2c2;">No completed requests</td></tr>
                            <?php } ?>
                        </tbody>
                    </table>
                    
                    <!-- Mobile Card View -->
                    <div id="completed-cards">
                        <?php 
                        $completedQuery = "
                            SELECT 
                                vr.id, vr.vehicle_id, v.vehicle_no, v.vehicle_type, vr.repair_type, vr.description,
                                vr.status, vr.priority, vr.requester_name, vr.taken_by_personnel, vr.repair_location,
                                vr.actual_repair_date, vr.notes, vr.requested_date, vr.approval_date, vr.completed_date
                            FROM vehicle_repairs vr
                            LEFT JOIN vehicles v ON vr.vehicle_id = v.id
                            WHERE vr.status = 'completed'
                            ORDER BY vr.completed_date DESC
                        ";
                        try {
                            $completedStmt = $pdo->prepare($completedQuery);
                            $completedStmt->execute();
                            $completedRequests = $completedStmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            if (count($completedRequests) > 0):
                                foreach ($completedRequests as $request): ?>
                                <div class="table-card show-on-mobile">
                                    <div class="card-row">
                                        <div class="card-label">Date</div>
                                        <div class="card-value"><?php echo date('M d, Y', strtotime($request['requested_date'])); ?></div>
                                    </div>
                                    <div class="card-row">
                                        <div class="card-label">Vehicle</div>
                                        <div class="card-value">
                                            <strong><?php echo htmlspecialchars($request['vehicle_type'] ?? 'Unknown'); ?></strong><br>
                                            <small style="color: #7e8299;"><?php echo htmlspecialchars($request['vehicle_no']); ?></small>
                                        </div>
                                    </div>
                                    <div class="card-row">
                                        <div class="card-label">Repair Type</div>
                                        <div class="card-value"><?php echo htmlspecialchars($request['repair_type']); ?></div>
                                    </div>
                                    <div class="card-row">
                                        <div class="card-label">Priority</div>
                                        <div class="card-value">
                                            <span class="status-badge status-<?php echo strtolower($request['priority']); ?>">
                                                <?php echo ucfirst(htmlspecialchars($request['priority'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="card-row">
                                        <div class="card-label">Requester</div>
                                        <div class="card-value"><?php echo htmlspecialchars($request['requester_name'] ?? '-'); ?></div>
                                    </div>
                                    <div class="card-row">
                                        <div class="card-label">Location</div>
                                        <div class="card-value"><?php echo htmlspecialchars($request['repair_location'] ?? '-'); ?></div>
                                    </div>
                                    <div class="card-row">
                                        <div class="card-label">Personnel</div>
                                        <div class="card-value"><?php echo htmlspecialchars($request['taken_by_personnel'] ?? '-'); ?></div>
                                    </div>
                                </div>
                            <?php endforeach;
                            else: ?>
                                <div style="text-align: center; padding: 30px; color: #a2a2c2;">No completed requests</div>
                            <?php endif;
                        } catch (Exception $e) { ?>
                            <div style="text-align: center; padding: 30px; color: #a2a2c2;">No completed requests</div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- New Repair Request Modal -->
<div class="modal" id="requestModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>➕ Submit Repair Request</h2>
            <p>Fill in the details below to request a vehicle repair</p>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="submit_request">
            
            <div class="form-group">
                <label for="requesterNameDisplay">Requested By</label>
                <input type="text" id="requesterNameDisplay" value="<?php echo htmlspecialchars($_SESSION['username']); ?>" readonly style="background: #0a0a15; opacity: 0.7; cursor: not-allowed;">
                <small style="color: #7e8299; margin-top: 5px; display: block;">Auto-filled with your username</small>
            </div>

            <div class="form-group">
                <label for="vehicleId">Vehicle *</label>
                <select name="vehicle_id" id="vehicleId" required>
                    <option value="">-- Select Vehicle --</option>
                    <?php foreach ($vehicles as $vehicle): ?>
                        <option value="<?php echo htmlspecialchars($vehicle['id']); ?>">
                            <?php echo htmlspecialchars($vehicle['vehicle_no'] . ' - ' . $vehicle['vehicle_type']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="repairType">Repair Type *</label>
                <select name="repair_type" id="repairType" required>
                    <option value="">-- Select Type --</option>
                    <option value="Engine Repair">Engine Repair</option>
                    <option value="Tires">Tires</option>
                    <option value="Brakes">Brakes</option>
                    <option value="Transmission">Transmission</option>
                    <option value="Electrical">Electrical</option>
                    <option value="Suspension">Suspension</option>
                    <option value="Hydraulic">Hydraulic</option>
                    <option value="Fuel System">Fuel System</option>
                    <option value="Other">Other</option>
                </select>
            </div>

            <div class="form-group">
                <label for="priority">Priority</label>
                <select name="priority" id="priority">
                    <option value="low">Low</option>
                    <option value="medium" selected>Medium</option>
                    <option value="high">High</option>
                    <option value="urgent">Urgent</option>
                </select>
            </div>

            <div class="form-group">
                <label for="description">Description *</label>
                <textarea name="description" id="description" placeholder="Describe the repair needed..." required></textarea>
            </div>

            <div class="modal-buttons">
                <button type="button" class="btn-cancel" onclick="closeRequestModal()">Cancel</button>
                <button type="submit" class="btn-primary">Submit Request</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openRequestModal() {
        document.getElementById('requestModal').classList.add('show');
    }

    function closeRequestModal() {
        document.getElementById('requestModal').classList.remove('show');
    }

    function showRequestPage(pageType) {
        // Hide all pages
        const pages = document.querySelectorAll('.request-page');
        pages.forEach(page => page.classList.remove('active'));
        
        // Remove active class from all stat cards
        const statCards = document.querySelectorAll('.stat-card');
        statCards.forEach(card => card.classList.remove('active'));
        
        // Show selected page
        const selectedPage = document.getElementById('page-' + pageType);
        if (selectedPage) {
            selectedPage.classList.add('active');
        }
        
        // Add active class to clicked stat card
        event.target.closest('.stat-card').classList.add('active');
        
        // Scroll to table
        selectedPage.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // Close modal when clicking outside
    document.getElementById('requestModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeRequestModal();
        }
    });
</script>
</body>
</html>
