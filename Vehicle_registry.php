<?php
session_start();
include("db_connect.php");

// Check login
if (!isset($_SESSION['username'])) {
    // Uncomment to enforce login
    // header("Location: login.php");
    // exit();
}

// Handle form submission for vehicle registration
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'register_vehicle') {
        try {
            $vehicle_no = trim($_POST['vehicle_no'] ?? '');
            $vehicle_type = trim($_POST['vehicle_type'] ?? '');
            $make = trim($_POST['make'] ?? '');
            $model = trim($_POST['model'] ?? '');
            $year = intval($_POST['year'] ?? 0);
            $color = trim($_POST['color'] ?? '');
            $engine_no = trim($_POST['engine_no'] ?? '');
            $chassis_no = trim($_POST['chassis_no'] ?? '');
            $fuel_type = trim($_POST['fuel_type'] ?? 'gasoline');
            $fuel_capacity = floatval($_POST['fuel_capacity'] ?? 0);
            $current_fuel = floatval($_POST['current_fuel'] ?? 0);
            
            // Sensor information
            $gps_enabled = isset($_POST['gps_enabled']) ? 1 : 0;
            $gps_device_id = trim($_POST['gps_device_id'] ?? '');
            $sensor_enabled = isset($_POST['sensor_enabled']) ? 1 : 0;
            $sensor_device_id = trim($_POST['sensor_device_id'] ?? '');
            
            $description = trim($_POST['description'] ?? '');
            $status = trim($_POST['status'] ?? 'available');
            
            // Handle vehicle photo upload
            $vehicle_photo = '';
            if (isset($_FILES['vehicle_photo']) && $_FILES['vehicle_photo']['error'] == UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/vehicle_photos/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_extension = pathinfo($_FILES['vehicle_photo']['name'], PATHINFO_EXTENSION);
                $allowed_extensions = array('jpg', 'jpeg', 'png', 'gif');
                
                if (in_array(strtolower($file_extension), $allowed_extensions)) {
                    $new_filename = 'vehicle_' . date('YmdHis') . '_' . preg_replace('/[^a-zA-Z0-9-]/', '', $vehicle_no) . '.' . $file_extension;
                    $upload_path = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($_FILES['vehicle_photo']['tmp_name'], $upload_path)) {
                        $vehicle_photo = $upload_path;
                    }
                }
            }
            
            // Insert into database - using parameterized query
            $sql = "INSERT INTO vehicles (
                vehicle_no, vehicle_type, make, model, year, color, 
                engine_no, chassis_no, fuel_type, fuel_capacity, current_fuel,
                gps_enabled, gps_device_id, sensor_enabled, sensor_device_id,
                vehicle_photo, description, status, created_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, NOW()
            )";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $vehicle_no, $vehicle_type, $make, $model, $year, $color,
                $engine_no, $chassis_no, $fuel_type, $fuel_capacity, $current_fuel,
                $gps_enabled, $gps_device_id, $sensor_enabled, $sensor_device_id,
                $vehicle_photo, $description, $status
            ]);
            
            $success_message = "✓ Vehicle registered successfully!";
        } catch (Exception $e) {
            $error_message = "Error registering vehicle: " . $e->getMessage();
        }
    } elseif ($action === 'update_fuel') {
        try {
            $vehicle_id = intval($_POST['vehicle_id'] ?? 0);
            $new_fuel_level = floatval($_POST['fuel_level'] ?? 0);
            
            $sql = "UPDATE vehicles SET current_fuel = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$new_fuel_level, $vehicle_id]);
            
            $success_message = "✓ Fuel level updated successfully!";
        } catch (Exception $e) {
            $error_message = "Error updating fuel: " . $e->getMessage();
        }
    } elseif ($action === 'update_sensor') {
        try {
            $vehicle_id = intval($_POST['vehicle_id'] ?? 0);
            $gps_enabled = isset($_POST['gps_enabled']) ? 1 : 0;
            $gps_device_id = trim($_POST['gps_device_id'] ?? '');
            $sensor_enabled = isset($_POST['sensor_enabled']) ? 1 : 0;
            $sensor_device_id = trim($_POST['sensor_device_id'] ?? '');
            
            $sql = "UPDATE vehicles SET 
                    gps_enabled = ?, 
                    gps_device_id = ?,
                    sensor_enabled = ?, 
                    sensor_device_id = ? 
                    WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $gps_enabled, $gps_device_id,
                $sensor_enabled, $sensor_device_id,
                $vehicle_id
            ]);
            
            $success_message = "✓ Sensor configuration updated successfully!";
        } catch (Exception $e) {
            $error_message = "Error updating sensor: " . $e->getMessage();
        }
    }
}

// Fetch all vehicles
$vehicles = [];
try {
    $sql = "SELECT * FROM vehicles ORDER BY vehicle_no ASC";
    $result = $pdo->query($sql);
    while ($row = $result->fetch()) {
        $vehicles[] = $row;
    }
} catch (Exception $e) {
    $error_message = "Error fetching vehicles: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BFP - Vehicle Registry</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #5d5dff;
            --primary-dark: #4d4ddd;
            --primary-light: #7d7dff;
            --bg-dark: #1e1e2d;
            --bg-light: #2a2a3e;
            --text-dark: #e2e2e2;
            --text-gray: #a2a2c2;
            --border-color: rgba(255, 255, 255, 0.1);
            --success: #27ae60;
            --warning: #f39c12;
            --danger: #e74c3c;
            --radius: 12px;
            --transition: all 0.3s cubic-bezier(0.25, 1, 0.5, 1);
        }

        body {
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg-dark);
            color: var(--text-dark);
            min-height: 100vh;
            display: flex;
        }

        .wrapper {
            display: flex;
            width: 100%;
            flex: 1;
        }

        main {
            flex: 1;
            overflow-y: auto;
            padding: 30px;
            background: var(--bg-dark);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .page-title {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .btn-register {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            border: none;
            padding: 12px 28px;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1rem;
        }

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(93, 93, 255, 0.3);
        }

        .alert {
            padding: 16px 20px;
            border-radius: var(--radius);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideDown 0.3s ease;
        }

        .alert-success {
            background: rgba(39, 174, 96, 0.1);
            border: 1px solid rgba(39, 174, 96, 0.3);
            color: #27ae60;
        }

        .alert-error {
            background: rgba(231, 76, 60, 0.1);
            border: 1px solid rgba(231, 76, 60, 0.3);
            color: #e74c3c;
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

        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            overflow-y: auto;
            animation: fadeIn 0.3s ease;
        }

        .modal.show {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background: var(--bg-light);
            margin: 30px auto;
            padding: 30px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            width: 90%;
            max-width: 700px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from {
                transform: translateY(30px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 28px;
            color: var(--text-gray);
            cursor: pointer;
            transition: var(--transition);
        }

        .close-btn:hover {
            color: var(--primary-color);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text-dark);
            font-size: 0.95rem;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-dark);
            font-family: inherit;
            font-size: 0.95rem;
            transition: var(--transition);
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            background: rgba(93, 93, 255, 0.1);
            box-shadow: 0 0 0 3px rgba(93, 93, 255, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
            grid-column: 1 / -1;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 8px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: var(--primary-color);
        }

        .checkbox-group label {
            margin: 0;
            cursor: pointer;
            font-weight: 500;
        }

        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            border: 2px dashed var(--border-color);
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            transition: var(--transition);
            grid-column: 1 / -1;
        }

        .file-input-wrapper:hover {
            border-color: var(--primary-color);
            background: rgba(93, 93, 255, 0.05);
        }

        .file-input-wrapper input[type="file"] {
            display: none;
        }

        .file-input-label {
            cursor: pointer;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            color: var(--text-gray);
        }

        .file-input-label i {
            font-size: 2rem;
            color: var(--primary-color);
        }

        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .btn-submit {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            border: none;
            padding: 12px 28px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(93, 93, 255, 0.3);
        }

        .btn-cancel {
            background: transparent;
            color: var(--text-gray);
            border: 1px solid var(--border-color);
            padding: 12px 28px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-cancel:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .vehicles-section {
            background: var(--bg-light);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            overflow: hidden;
        }

        .vehicles-table {
            width: 100%;
            border-collapse: collapse;
            overflow-x: auto;
        }

        .vehicles-table thead {
            background: rgba(93, 93, 255, 0.1);
            border-bottom: 2px solid var(--border-color);
        }

        .vehicles-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--primary-color);
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }

        .vehicles-table td {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-dark);
        }

        .vehicles-table tbody tr {
            transition: var(--transition);
        }

        .vehicles-table tbody tr:hover {
            background: rgba(93, 93, 255, 0.05);
        }

        /* Mobile card view for vehicles */
        .vehicles-card {
            display: none;
            background: var(--bg-light);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            padding: 20px;
            margin-bottom: 15px;
            gap: 15px;
        }

        .vehicles-card.active {
            display: grid;
            grid-template-columns: auto 1fr;
        }

        .card-photo {
            grid-column: 1;
            grid-row: 1 / 3;
        }

        .card-header {
            grid-column: 2;
            grid-row: 1;
            display: flex;
            justify-content: space-between;
            align-items: start;
            gap: 10px;
        }

        .card-info {
            grid-column: 1 / -1;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        .card-info-item {
            background: rgba(93, 93, 255, 0.05);
            padding: 10px;
            border-radius: 6px;
        }

        .card-info-label {
            font-size: 0.75rem;
            color: var(--text-gray);
            text-transform: uppercase;
            font-weight: 600;
        }

        .card-info-value {
            font-size: 0.95rem;
            color: var(--text-dark);
            margin-top: 4px;
        }

        .card-actions {
            grid-column: 1 / -1;
            display: flex;
            gap: 8px;
            margin-top: 10px;
        }

        .vehicle-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .badge-available {
            background: rgba(39, 174, 96, 0.2);
            color: #27ae60;
        }

        .badge-deployed {
            background: rgba(52, 152, 219, 0.2);
            color: #3498db;
        }

        .badge-in-repair {
            background: rgba(243, 156, 18, 0.2);
            color: #f39c12;
        }

        .badge-inactive {
            background: rgba(231, 76, 60, 0.2);
            color: #e74c3c;
        }

        .sensor-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 8px;
            background: rgba(93, 93, 255, 0.15);
            border-radius: 4px;
            font-size: 0.75rem;
            color: var(--primary-color);
        }

        .vehicle-actions {
            display: flex;
            gap: 8px;
            justify-content: center;
        }

        .btn-small {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-gray);
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-small:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
            background: rgba(93, 93, 255, 0.1);
        }

        .vehicle-photo {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid var(--border-color);
        }

        .no-photo {
            width: 50px;
            height: 50px;
            background: rgba(93, 93, 255, 0.1);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-gray);
            font-size: 1.5rem;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        .details-item {
            background: rgba(93, 93, 255, 0.05);
            border: 1px solid var(--border-color);
            padding: 15px;
            border-radius: 8px;
        }

        .details-label {
            font-weight: 600;
            color: var(--primary-color);
            font-size: 0.85rem;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .details-value {
            color: var(--text-dark);
            font-size: 1.1rem;
        }

        .vehicle-photo-display {
            width: 100%;
            max-height: 300px;
            object-fit: contain;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            main {
                padding: 15px;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .page-title {
                font-size: 1.8rem;
            }

            .btn-register {
                width: 100%;
                justify-content: center;
            }

            .modal-content {
                width: 95%;
                padding: 20px;
                margin: 20px auto;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .details-grid {
                grid-template-columns: 1fr;
            }

            .vehicles-table {
                display: none !important;
            }

            .vehicles-section {
                background: transparent;
                border: none;
            }

            #vehicleCardsContainer {
                display: block !important;
            }

            .vehicles-card {
                display: grid !important;
                grid-template-columns: auto 1fr;
            }

            .btn-small {
                flex: 1;
                padding: 10px 8px;
                font-size: 0.75rem;
            }

            .vehicle-actions {
                flex-direction: row;
                gap: 6px;
            }

            .form-group input,
            .form-group select,
            .form-group textarea {
                font-size: 16px;
            }

            .btn-submit,
            .btn-cancel {
                flex: 1;
                padding: 14px 20px;
                font-size: 1rem;
            }

            .form-actions {
                gap: 10px;
            }

            .modal-title {
                font-size: 1.3rem;
            }

            .alert {
                font-size: 0.95rem;
            }

            .checkbox-group label {
                font-size: 0.95rem;
            }
        }

        @media (max-width: 480px) {
            main {
                padding: 10px;
            }

            .page-header {
                margin-bottom: 20px;
            }

            .page-title {
                font-size: 1.5rem;
                width: 100%;
            }

            .btn-register {
                width: 100%;
                padding: 14px 20px;
                font-size: 0.95rem;
            }

            .modal-content {
                width: 98%;
                padding: 15px;
                margin: 15px auto;
            }

            .form-group label {
                font-size: 0.9rem;
            }

            .form-group input,
            .form-group select,
            .form-group textarea {
                font-size: 16px;
                padding: 14px;
            }

            .modal-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .close-btn {
                align-self: flex-end;
            }

            .btn-submit,
            .btn-cancel {
                padding: 14px 16px;
            }

            .form-actions {
                flex-direction: column;
                justify-content: flex-end;
            }

            .details-item {
                padding: 12px;
            }

            .details-label {
                font-size: 0.75rem;
            }

            .details-value {
                font-size: 1rem;
            }

            .card-info {
                grid-template-columns: 1fr;
            }

            .vehicle-photo {
                width: 80px;
                height: 80px;
            }

            .no-photo {
                width: 80px;
                height: 80px;
            }

            #vehicleCardsContainer {
                display: block !important;
            }

            .vehicles-table {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <main>
            <div class="container">
                <!-- Page Header -->
                <div class="page-header">
                    <h1 class="page-title">🚗 Vehicle Registry</h1>
                    <button class="btn-register" onclick="openRegisterModal()">
                        <i class="fas fa-plus"></i> Register New Vehicle
                    </button>
                </div>

                <!-- Alert Messages -->
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <span><?php echo htmlspecialchars($success_message); ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo htmlspecialchars($error_message); ?></span>
                    </div>
                <?php endif; ?>

                <!-- Vehicles Table (Desktop) -->
                <div class="vehicles-section">
                    <table class="vehicles-table">
                        <thead>
                            <tr>
                                <th>Photo</th>
                                <th>Vehicle No</th>
                                <th>Type</th>
                                <th>Make/Model</th>
                                <th>Fuel Level</th>
                                <th>Status</th>
                                <th>GPS</th>
                                <th>Sensor</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($vehicles) > 0): ?>
                                <?php foreach ($vehicles as $vehicle): ?>
                                    <tr>
                                        <td>
                                            <?php if (!empty($vehicle['vehicle_photo']) && file_exists($vehicle['vehicle_photo'])): ?>
                                                <img src="<?php echo htmlspecialchars($vehicle['vehicle_photo']); ?>" alt="Vehicle Photo" class="vehicle-photo">
                                            <?php else: ?>
                                                <div class="no-photo">📸</div>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($vehicle['vehicle_no']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($vehicle['vehicle_type']); ?></td>
                                        <td><?php echo htmlspecialchars(($vehicle['make'] ?? '') . ' ' . ($vehicle['model'] ?? '')); ?></td>
                                        <td>
                                            <div style="text-align: center;">
                                                <?php echo htmlspecialchars($vehicle['current_fuel']); ?> L / <?php echo htmlspecialchars($vehicle['fuel_capacity'] ?? 'N/A'); ?> L
                                            </div>
                                        </td>
                                        <td>
                                            <span class="vehicle-badge badge-<?php echo htmlspecialchars(str_replace('_', '-', $vehicle['status'])); ?>">
                                                <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $vehicle['status']))); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (isset($vehicle['gps_enabled']) && $vehicle['gps_enabled']): ?>
                                                <span class="sensor-badge">✓ Enabled</span>
                                            <?php else: ?>
                                                <span style="color: var(--text-gray); font-size: 0.85rem;">Disabled</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (isset($vehicle['sensor_enabled']) && $vehicle['sensor_enabled']): ?>
                                                <span class="sensor-badge">✓ Enabled</span>
                                            <?php else: ?>
                                                <span style="color: var(--text-gray); font-size: 0.85rem;">Disabled</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="vehicle-actions">
                                                <button class="btn-small" onclick="openDetailsModal(<?php echo htmlspecialchars(json_encode($vehicle)); ?>)" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn-small" onclick="openFuelModal(<?php echo $vehicle['id']; ?>, <?php echo $vehicle['current_fuel']; ?>)" title="Update Fuel">
                                                    <i class="fas fa-gas-pump"></i>
                                                </button>
                                                <button class="btn-small" onclick="openSensorModal(<?php echo htmlspecialchars(json_encode($vehicle)); ?>)" title="Update Sensors">
                                                    <i class="fas fa-microchip"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" style="text-align: center; padding: 40px; color: var(--text-gray);">
                                        <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                                        No vehicles registered yet. Click "Register New Vehicle" to get started.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Vehicles Cards (Mobile) -->
                <div id="vehicleCardsContainer" style="display: none;">
                    <?php if (count($vehicles) > 0): ?>
                        <?php foreach ($vehicles as $vehicle): ?>
                            <div class="vehicles-card active">
                                <?php if (!empty($vehicle['vehicle_photo']) && file_exists($vehicle['vehicle_photo'])): ?>
                                    <img src="<?php echo htmlspecialchars($vehicle['vehicle_photo']); ?>" alt="Vehicle Photo" class="vehicle-photo" style="width: 80px; height: 80px;">
                                <?php else: ?>
                                    <div class="no-photo" style="width: 80px; height: 80px;">📸</div>
                                <?php endif; ?>
                                
                                <div class="card-header">
                                    <div>
                                        <strong style="display: block; font-size: 1.1rem;"><?php echo htmlspecialchars($vehicle['vehicle_no']); ?></strong>
                                        <small style="color: var(--text-gray);"><?php echo htmlspecialchars($vehicle['vehicle_type']); ?></small>
                                    </div>
                                    <span class="vehicle-badge badge-<?php echo htmlspecialchars(str_replace('_', '-', $vehicle['status'])); ?>">
                                        <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $vehicle['status']))); ?>
                                    </span>
                                </div>

                                <div class="card-info">
                                    <div class="card-info-item">
                                        <div class="card-info-label">Make/Model</div>
                                        <div class="card-info-value"><?php echo htmlspecialchars(($vehicle['make'] ?? '') . ' ' . ($vehicle['model'] ?? '')); ?></div>
                                    </div>
                                    <div class="card-info-item">
                                        <div class="card-info-label">Year</div>
                                        <div class="card-info-value"><?php echo htmlspecialchars($vehicle['year'] ?? 'N/A'); ?></div>
                                    </div>
                                    <div class="card-info-item">
                                        <div class="card-info-label">Fuel Level</div>
                                        <div class="card-info-value"><?php echo htmlspecialchars($vehicle['current_fuel']); ?>L / <?php echo htmlspecialchars($vehicle['fuel_capacity'] ?? 'N/A'); ?>L</div>
                                    </div>
                                    <div class="card-info-item">
                                        <div class="card-info-label">Fuel Type</div>
                                        <div class="card-info-value"><?php echo htmlspecialchars($vehicle['fuel_type'] ?? 'N/A'); ?></div>
                                    </div>
                                    <div class="card-info-item">
                                        <div class="card-info-label">GPS</div>
                                        <div class="card-info-value"><?php echo isset($vehicle['gps_enabled']) && $vehicle['gps_enabled'] ? '✓ Enabled' : 'Disabled'; ?></div>
                                    </div>
                                    <div class="card-info-item">
                                        <div class="card-info-label">Sensor</div>
                                        <div class="card-info-value"><?php echo isset($vehicle['sensor_enabled']) && $vehicle['sensor_enabled'] ? '✓ Enabled' : 'Disabled'; ?></div>
                                    </div>
                                </div>

                                <div class="card-actions">
                                    <button class="btn-small" onclick="openDetailsModal(<?php echo htmlspecialchars(json_encode($vehicle)); ?>)" title="View Details" style="flex: 1;">
                                        <i class="fas fa-eye"></i> Details
                                    </button>
                                    <button class="btn-small" onclick="openFuelModal(<?php echo $vehicle['id']; ?>, <?php echo $vehicle['current_fuel']; ?>)" title="Update Fuel" style="flex: 1;">
                                        <i class="fas fa-gas-pump"></i> Fuel
                                    </button>
                                    <button class="btn-small" onclick="openSensorModal(<?php echo htmlspecialchars(json_encode($vehicle)); ?>)" title="Update Sensors" style="flex: 1;">
                                        <i class="fas fa-microchip"></i> Sensors
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 40px; color: var(--text-gray);">
                            <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                            No vehicles registered yet. Click "Register New Vehicle" to get started.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Register Vehicle Modal -->
    <div id="registerModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Register New Vehicle</h2>
                <button class="close-btn" onclick="closeModal('registerModal')">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="register_vehicle">
                
                <div class="form-grid">
                    <!-- Basic Information -->
                    <div class="form-group">
                        <label>Vehicle Plate Number *</label>
                        <input type="text" name="vehicle_no" required placeholder="e.g., BFP-001">
                    </div>

                    <div class="form-group">
                        <label>Vehicle Type *</label>
                        <select name="vehicle_type" required>
                            <option value="">Select Type</option>
                            <option value="Fire Truck">Fire Truck</option>
                            <option value="Rescue Truck">Rescue Truck</option>
                            <option value="Ambulance">Ambulance</option>
                            <option value="Patrol Vehicle">Patrol Vehicle</option>
                            <option value="Water Tanker">Water Tanker</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <!-- Make and Model -->
                    <div class="form-group">
                        <label>Make *</label>
                        <input type="text" name="make" required placeholder="e.g., Toyota">
                    </div>

                    <div class="form-group">
                        <label>Model *</label>
                        <input type="text" name="model" required placeholder="e.g., Hiace">
                    </div>

                    <div class="form-group">
                        <label>Year *</label>
                        <input type="number" name="year" required placeholder="e.g., 2020" min="1900" max="2100">
                    </div>

                    <div class="form-group">
                        <label>Color</label>
                        <input type="text" name="color" placeholder="e.g., Red">
                    </div>

                    <!-- Engine Details -->
                    <div class="form-group">
                        <label>Engine Number</label>
                        <input type="text" name="engine_no" placeholder="Engine Serial Number">
                    </div>

                    <div class="form-group">
                        <label>Chassis Number</label>
                        <input type="text" name="chassis_no" placeholder="Chassis Serial Number">
                    </div>

                    <!-- Fuel Information -->
                    <div class="form-group">
                        <label>Fuel Type *</label>
                        <select name="fuel_type" required>
                            <option value="gasoline">Gasoline</option>
                            <option value="diesel">Diesel</option>
                            <option value="hybrid">Hybrid</option>
                            <option value="lpg">LPG</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Fuel Tank Capacity (L) *</label>
                        <input type="number" name="fuel_capacity" required placeholder="e.g., 100" min="0" step="0.01">
                    </div>

                    <div class="form-group">
                        <label>Current Fuel Level (L)</label>
                        <input type="number" name="current_fuel" placeholder="e.g., 50" min="0" step="0.01">
                    </div>

                    <!-- GPS Configuration -->
                    <div class="form-group full-width">
                        <label style="margin-bottom: 12px;">GPS Configuration</label>
                        <div class="checkbox-group">
                            <input type="checkbox" id="gps_enabled" name="gps_enabled" onchange="toggleGPSInput()">
                            <label for="gps_enabled">Enable GPS Tracking</label>
                        </div>
                    </div>

                    <div class="form-group" id="gps_device_group" style="display: none;">
                        <label>GPS Device ID</label>
                        <input type="text" name="gps_device_id" placeholder="e.g., GPS-001">
                    </div>

                    <!-- Sensor Configuration -->
                    <div class="form-group full-width">
                        <label style="margin-bottom: 12px;">Sensor Configuration</label>
                        <div class="checkbox-group">
                            <input type="checkbox" id="sensor_enabled" name="sensor_enabled" onchange="toggleSensorInput()">
                            <label for="sensor_enabled">Enable Sensor Monitoring</label>
                        </div>
                    </div>

                    <div class="form-group" id="sensor_device_group" style="display: none;">
                        <label>Sensor Device ID</label>
                        <input type="text" name="sensor_device_id" placeholder="e.g., SENSOR-001">
                    </div>

                    <!-- Status -->
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="available">Available</option>
                            <option value="deployed">Deployed</option>
                            <option value="in_repair">In Repair</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>

                    <!-- Photo Upload -->
                    <div class="file-input-wrapper">
                        <label for="vehicle_photo" class="file-input-label">
                            <i class="fas fa-camera"></i>
                            <span>Click to upload vehicle photo or drag & drop</span>
                            <span style="font-size: 0.85rem; color: var(--text-gray);">PNG, JPG, GIF up to 10MB</span>
                        </label>
                        <input type="file" id="vehicle_photo" name="vehicle_photo" accept="image/*">
                    </div>

                    <!-- Description -->
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" placeholder="Additional vehicle details..."></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeModal('registerModal')">Cancel</button>
                    <button type="submit" class="btn-submit">Register Vehicle</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Vehicle Details Modal -->
    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Vehicle Details</h2>
                <button class="close-btn" onclick="closeModal('detailsModal')">&times;</button>
            </div>
            <div id="detailsContent"></div>
        </div>
    </div>

    <!-- Update Fuel Modal -->
    <div id="fuelModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Update Fuel Level</h2>
                <button class="close-btn" onclick="closeModal('fuelModal')">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_fuel">
                <input type="hidden" name="vehicle_id" id="fuel_vehicle_id">
                
                <div class="form-group">
                    <label>Fuel Level (Liters)</label>
                    <input type="number" name="fuel_level" id="fuel_level_input" required placeholder="e.g., 50" min="0" step="0.01">
                </div>

                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeModal('fuelModal')">Cancel</button>
                    <button type="submit" class="btn-submit">Update Fuel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Update Sensor Modal -->
    <div id="sensorModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Configure Sensors & GPS</h2>
                <button class="close-btn" onclick="closeModal('sensorModal')">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_sensor">
                <input type="hidden" name="vehicle_id" id="sensor_vehicle_id">

                <div class="form-grid">
                    <div class="form-group full-width">
                        <label style="margin-bottom: 12px;">GPS Configuration</label>
                        <div class="checkbox-group">
                            <input type="checkbox" id="sensor_gps_enabled" name="gps_enabled" onchange="toggleGPSInput2()">
                            <label for="sensor_gps_enabled">Enable GPS Tracking</label>
                        </div>
                    </div>

                    <div class="form-group" id="sensor_gps_device_group" style="display: none;">
                        <label>GPS Device ID</label>
                        <input type="text" id="sensor_gps_device_id" name="gps_device_id" placeholder="e.g., GPS-001">
                    </div>

                    <div class="form-group full-width">
                        <label style="margin-bottom: 12px;">Sensor Configuration</label>
                        <div class="checkbox-group">
                            <input type="checkbox" id="sensor_enabled_checkbox" name="sensor_enabled" onchange="toggleSensorInput2()">
                            <label for="sensor_enabled_checkbox">Enable Sensor Monitoring</label>
                        </div>
                    </div>

                    <div class="form-group" id="sensor_device_group2" style="display: none;">
                        <label>Sensor Device ID</label>
                        <input type="text" id="sensor_device_id_input" name="sensor_device_id" placeholder="e.g., SENSOR-001">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeModal('sensorModal')">Cancel</button>
                    <button type="submit" class="btn-submit">Update Configuration</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal Functions
        function openRegisterModal() {
            document.getElementById('registerModal').classList.add('show');
        }

        function openDetailsModal(vehicle) {
            let html = '';
            
            if (vehicle.vehicle_photo && vehicle.vehicle_photo !== '') {
                html += `<img src="${vehicle.vehicle_photo}" alt="Vehicle Photo" class="vehicle-photo-display">`;
            }
            
            html += `
                <div class="details-grid">
                    <div class="details-item">
                        <div class="details-label">Vehicle Plate No</div>
                        <div class="details-value">${vehicle.vehicle_no}</div>
                    </div>
                    <div class="details-item">
                        <div class="details-label">Vehicle Type</div>
                        <div class="details-value">${vehicle.vehicle_type}</div>
                    </div>
                    <div class="details-item">
                        <div class="details-label">Make</div>
                        <div class="details-value">${vehicle.make || 'N/A'}</div>
                    </div>
                    <div class="details-item">
                        <div class="details-label">Model</div>
                        <div class="details-value">${vehicle.model || 'N/A'}</div>
                    </div>
                    <div class="details-item">
                        <div class="details-label">Year</div>
                        <div class="details-value">${vehicle.year || 'N/A'}</div>
                    </div>
                    <div class="details-item">
                        <div class="details-label">Color</div>
                        <div class="details-value">${vehicle.color || 'N/A'}</div>
                    </div>
                    <div class="details-item">
                        <div class="details-label">Engine No</div>
                        <div class="details-value">${vehicle.engine_no || 'N/A'}</div>
                    </div>
                    <div class="details-item">
                        <div class="details-label">Chassis No</div>
                        <div class="details-value">${vehicle.chassis_no || 'N/A'}</div>
                    </div>
                    <div class="details-item">
                        <div class="details-label">Fuel Type</div>
                        <div class="details-value">${vehicle.fuel_type || 'N/A'}</div>
                    </div>
                    <div class="details-item">
                        <div class="details-label">Tank Capacity</div>
                        <div class="details-value">${vehicle.fuel_capacity || 0}L</div>
                    </div>
                    <div class="details-item">
                        <div class="details-label">Current Fuel</div>
                        <div class="details-value">${vehicle.current_fuel}L</div>
                    </div>
                    <div class="details-item">
                        <div class="details-label">Status</div>
                        <div class="details-value">${vehicle.status}</div>
                    </div>
                    <div class="details-item">
                        <div class="details-label">GPS Tracking</div>
                        <div class="details-value">${vehicle.gps_enabled ? '✓ Enabled' : 'Disabled'}</div>
                    </div>
                    <div class="details-item">
                        <div class="details-label">GPS Device ID</div>
                        <div class="details-value">${vehicle.gps_device_id || 'N/A'}</div>
                    </div>
                    <div class="details-item">
                        <div class="details-label">Sensor Monitoring</div>
                        <div class="details-value">${vehicle.sensor_enabled ? '✓ Enabled' : 'Disabled'}</div>
                    </div>
                    <div class="details-item">
                        <div class="details-label">Sensor Device ID</div>
                        <div class="details-value">${vehicle.sensor_device_id || 'N/A'}</div>
                    </div>
                </div>
            `;
            
            document.getElementById('detailsContent').innerHTML = html;
            document.getElementById('detailsModal').classList.add('show');
        }

        function openFuelModal(vehicleId, currentFuel) {
            document.getElementById('fuel_vehicle_id').value = vehicleId;
            document.getElementById('fuel_level_input').value = currentFuel;
            document.getElementById('fuelModal').classList.add('show');
        }

        function openSensorModal(vehicle) {
            document.getElementById('sensor_vehicle_id').value = vehicle.id;
            document.getElementById('sensor_gps_enabled').checked = vehicle.gps_enabled;
            document.getElementById('sensor_gps_device_id').value = vehicle.gps_device_id || '';
            document.getElementById('sensor_enabled_checkbox').checked = vehicle.sensor_enabled;
            document.getElementById('sensor_device_id_input').value = vehicle.sensor_device_id || '';
            
            // Toggle visibility
            document.getElementById('sensor_gps_device_group').style.display = vehicle.gps_enabled ? 'block' : 'none';
            document.getElementById('sensor_device_group2').style.display = vehicle.sensor_enabled ? 'block' : 'none';
            
            document.getElementById('sensorModal').classList.add('show');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        function toggleGPSInput() {
            const enabled = document.getElementById('gps_enabled').checked;
            document.getElementById('gps_device_group').style.display = enabled ? 'block' : 'none';
        }

        function toggleSensorInput() {
            const enabled = document.getElementById('sensor_enabled').checked;
            document.getElementById('sensor_device_group').style.display = enabled ? 'block' : 'none';
        }

        function toggleGPSInput2() {
            const enabled = document.getElementById('sensor_gps_enabled').checked;
            document.getElementById('sensor_gps_device_group').style.display = enabled ? 'block' : 'none';
        }

        function toggleSensorInput2() {
            const enabled = document.getElementById('sensor_enabled_checkbox').checked;
            document.getElementById('sensor_device_group2').style.display = enabled ? 'block' : 'none';
        }

        // Handle responsive display
        function handleResponsive() {
            const tableSection = document.querySelector('.vehicles-section');
            const cardContainer = document.getElementById('vehicleCardsContainer');
            
            if (window.innerWidth <= 768) {
                tableSection.style.display = 'none';
                if (cardContainer) cardContainer.style.display = 'block';
            } else {
                tableSection.style.display = 'block';
                if (cardContainer) cardContainer.style.display = 'none';
            }
        }

        // Listen to resize events
        window.addEventListener('resize', handleResponsive);
        
        // Initialize on load
        window.addEventListener('load', handleResponsive);
        document.addEventListener('DOMContentLoaded', handleResponsive);

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.classList.remove('show');
                }
            });
        }

        // File input handler
        const fileInput = document.getElementById('vehicle_photo');
        if (fileInput) {
            fileInput.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.stopPropagation();
                document.querySelector('.file-input-wrapper').style.borderColor = 'var(--primary-color)';
            });

            fileInput.addEventListener('dragleave', (e) => {
                e.preventDefault();
                e.stopPropagation();
                document.querySelector('.file-input-wrapper').style.borderColor = 'var(--border-color)';
            });

            fileInput.addEventListener('drop', (e) => {
                e.preventDefault();
                e.stopPropagation();
                fileInput.files = e.dataTransfer.files;
                document.querySelector('.file-input-wrapper').style.borderColor = 'var(--border-color)';
            });
        }
    </script>
</body>
</html>

