<?php
session_start();
include("db_connect.php");
include("utils/log_activity.php");

// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$current_role = $_SESSION['role'] ?? '';
$current_user_id = $_SESSION['user_id'] ?? null;
$current_driver_id = null;
if ($current_user_id) {
    try {
        $u = $pdo->prepare("SELECT driver_id FROM users WHERE id = ? LIMIT 1");
        $u->execute([$current_user_id]);
        $urow = $u->fetch();
        $current_driver_id = $urow['driver_id'] ?? null;
    } catch (Exception $e) {
        $current_driver_id = null;
    }
}

// Handle messages
$message = '';
$error = '';

// ===== DRIVER OPERATIONS =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_driver'])) {
    try {
        $full_name = $_POST['full_name'] ?? '';
        $license_no = $_POST['license_no'] ?? '';
        $status = $_POST['status'] ?? 'active';
        
        if (empty($full_name)) {
            $error = "Driver name is required!";
        } else {
            $result = $pdo->query("SELECT MAX(driver_id) as max_id FROM drivers");
            $row = $result->fetch();
            $next_id = ($row['max_id'] ?? 0) + 1;
            
            $stmt = $pdo->prepare("INSERT INTO drivers (driver_id, full_name, license_no, status) VALUES (?, ?, ?, ?)");
            $stmt->execute([$next_id, $full_name, $license_no, $status]);
            
            // Also create a default user account for this driver
            $default_username = 'Officer';
            $default_password = '123';
            $hashed_password = password_hash($default_password, PASSWORD_DEFAULT);
            $default_role = 'user';
            
            $user_stmt = $pdo->prepare("INSERT INTO users (username, password, role, driver_id) VALUES (?, ?, ?, ?)");
            $user_stmt->execute([$default_username, $hashed_password, $default_role, $next_id]);
            
            // Log the action
            logDriverAction($pdo, $current_user_id, 'Add Driver', $next_id, "Added driver: $full_name with default user account (Officer/123)");
            
            $message = "✅ Driver '$full_name' added successfully! A default user account has been automatically created with:<br><strong>Username:</strong> Officer | <strong>Password:</strong> 123 | <strong>Role:</strong> user";
        }
    } catch (Exception $e) {
        $error = "Error adding driver: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_driver'])) {
    try {
        $driver_id = (int)$_POST['driver_id'];
        $stmt = $pdo->prepare("DELETE FROM drivers WHERE driver_id = ?");
        $stmt->execute([$driver_id]);
        
        // Log the action
        logDriverAction($pdo, $current_user_id, 'Delete Driver', $driver_id, "Deleted driver with ID: $driver_id");
        
        $message = "Driver deleted successfully!";
    } catch (Exception $e) {
        $error = "Error deleting driver: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_driver'])) {
    try {
        $driver_id = (int)$_POST['driver_id'];
        $full_name = $_POST['full_name'] ?? '';
        $license_no = $_POST['license_no'] ?? '';
        $status = $_POST['status'] ?? 'active';
        
        if (empty($full_name)) {
            $error = "Driver name is required!";
        } else {
            $stmt = $pdo->prepare("UPDATE drivers SET full_name = ?, license_no = ?, status = ? WHERE driver_id = ?");
            $stmt->execute([$full_name, $license_no, $status, $driver_id]);
            
            // Log the action
            logDriverAction($pdo, $current_user_id, 'Update Driver', $driver_id, "Updated driver: $full_name (Status: $status)");
            
            $message = "Driver updated successfully!";
        }
    } catch (Exception $e) {
        $error = "Error updating driver: " . $e->getMessage();
    }
}

// ===== USER OPERATIONS =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    try {
        $user_id = (int)$_POST['user_id'];
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        
        // Log the action
        logUserManagement($pdo, $current_user_id, 'Delete User', $user_id, "Deleted user with ID: $user_id");
        
        $message = "User deleted successfully!";
    } catch (Exception $e) {
        $error = "Error deleting user: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    try {
        $user_id = (int)$_POST['user_id'];
        $role = $_POST['role'] ?? 'user';
        
        $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->execute([$role, $user_id]);
        
        // Log the action
        logUserManagement($pdo, $current_user_id, 'Update User Role', $user_id, "Changed user role to: $role");
        
        $message = "User updated successfully!";
    } catch (Exception $e) {
        $error = "Error updating user: " . $e->getMessage();
    }
}

// Add new user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    try {
        $username = trim($_POST['username'] ?? '');
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? 'user';
        $department = trim($_POST['department'] ?? null);
        $position = trim($_POST['position'] ?? null);

        if (empty($username) || empty($first_name) || empty($last_name) || empty($email)) {
            $error = "Please fill all required fields (username, first name, last name, email).";
        } else {
            // Check if username already exists
            $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $check->execute([$username]);
            if ($check->fetchColumn() > 0) {
                $error = "Username already exists. Please choose another.";
            } else {
                // Default password - user should change later
                $default_password = 'Test123';
                $hashed = password_hash($default_password, PASSWORD_DEFAULT);

                // Default profile picture
                $default_profile = 'ALBUM/defult.png';

                $ins = $pdo->prepare("INSERT INTO users (username, password, first_name, last_name, email, role, department, position, profile_pic, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $ins->execute([$username, $hashed, $first_name, $last_name, $email, $role, $department, $position, $default_profile]);

                $newUserId = $pdo->lastInsertId();
                logUserManagement($pdo, $current_user_id, 'Add User', $newUserId, "Created user: $username");

                $message = "User '$username' added successfully. Default password: welcome2026";
            }
        }
    } catch (Exception $e) {
        $error = "Error adding user: " . $e->getMessage();
    }
}

// Fetch drivers
try {
    $query = "SELECT d.driver_id, d.full_name, d.license_no, d.status, COUNT(t.id) as trip_count 
              FROM drivers d 
              LEFT JOIN trip_tickets t ON d.driver_id = t.driver_id 
              GROUP BY d.driver_id 
              ORDER BY d.full_name ASC";
    $result = $pdo->query($query);
    $drivers = $result->fetchAll();
} catch (Exception $e) {
    $error = "Error fetching drivers: " . $e->getMessage();
    $drivers = [];
}

// Fetch users
try {
    $query = "SELECT id, username, first_name, last_name, email, phone, role, department, position, employee_id, driver_id, created_at FROM users ORDER BY created_at DESC";
    $result = $pdo->query($query);
    $users = $result->fetchAll();
    
    // Check if users have applied for driver and update position
    foreach ($users as &$user) {
        if (!empty($user['driver_id']) || strtolower($user['position']) === 'driver') {
            $user['position'] = 'Driver';
        }
    }
    unset($user);
} catch (Exception $e) {
    $error = "Error fetching users: " . $e->getMessage();
    $users = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BFP - Management</title>
    <style>
        :root {
            --bfp-red: #ff4757;
            --sidebar-bg: #1e1e2d;
            --active-gradient: linear-gradient(90deg, rgba(93, 93, 255, 0.15) 0%, rgba(93, 93, 255, 0) 100%);
            --card-bg: #252c3c;
            --text-primary: #ffffff;
            --text-secondary: #9899ac;
            --text-tertiary: #7e8299;
            --border-color: rgba(255, 255, 255, 0.05);
            --transition-speed: 0.3s;
        }

        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
        }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background-color: var(--sidebar-bg);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            color: var(--text-primary);
        }

        header {
            display: none;
        }

        .wrapper { 
            display: flex; 
            flex: 1; 
            overflow: hidden; 
        }

        main { 
            display: flex; 
            flex: 1; 
            padding: 30px; 
            gap: 20px; 
            overflow: hidden; 
            flex-direction: column; 
        }

        .container {
            flex: 1;
            background: var(--card-bg);
            border-radius: 16px;
            border: 1px solid var(--border-color);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .navbar {
            display: flex;
            background: rgba(0, 0, 0, 0.2);
            border-bottom: 1px solid var(--border-color);
            overflow: hidden;
        }

        .nav-btn {
            flex: 1;
            padding: 15px 25px;
            background: none;
            border: none;
            font-size: 1rem;
            font-weight: bold;
            color: var(--text-tertiary);
            cursor: pointer;
            transition: all var(--transition-speed) ease;
            white-space: nowrap;
            border-bottom: 3px solid transparent;
            margin-bottom: -1px;
        }

        .nav-btn:hover {
            background: rgba(93, 93, 255, 0.1);
            color: #5d5dff;
        }

        .nav-btn.active {
            color: var(--text-primary);
            background: var(--active-gradient);
            border-bottom-color: #5d5dff;
        }

        .content {
            flex: 1;
            padding: 25px;
            overflow-y: auto;
            display: none;
        }

        .content.active {
            display: block;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            gap: 20px;
            flex-wrap: wrap;
        }

        .page-title {
            font-size: 1.5rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: bold;
        }

        .btn-add {
            background: #5d5dff;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-weight: bold;
            font-size: 1rem;
            transition: all var(--transition-speed) ease;
        }

        .btn-add:hover { 
            background: #4d4dff;
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(93, 93, 255, 0.3);
        }

        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .alert.success {
            background: rgba(46, 213, 115, 0.15);
            color: #2ed573;
            border: 1px solid rgba(46, 213, 115, 0.3);
        }

        .alert.error {
            background: rgba(255, 71, 87, 0.15);
            color: #ff4757;
            border: 1px solid rgba(255, 71, 87, 0.3);
        }

        .alert-close { 
            cursor: pointer; 
            font-weight: bold;
            opacity: 0.7;
            transition: opacity var(--transition-speed) ease;
        }

        .alert-close:hover {
            opacity: 1;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table thead {
            background: rgba(0, 0, 0, 0.2);
            border-bottom: 1px solid var(--border-color);
        }

        table th {
            padding: 15px;
            text-align: left;
            font-weight: bold;
            color: var(--text-secondary);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        table td {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-secondary);
        }

        table tbody tr {
            transition: all var(--transition-speed) ease;
        }

        table tbody tr:hover { 
            background: rgba(93, 93, 255, 0.08);
        }

        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: bold;
            text-transform: capitalize;
        }

        .badge.active {
            background: rgba(46, 213, 115, 0.15);
            color: #2ed573;
        }

        .badge.inactive {
            background: rgba(255, 71, 87, 0.15);
            color: #ff4757;
        }

        .badge.admin {
            background: rgba(255, 71, 87, 0.15);
            color: #ff4757;
        }

        .badge.user {
            background: rgba(93, 93, 255, 0.15);
            color: #5d5dff;
        }

        .badge.chief {
            background: rgba(255, 195, 0, 0.15);
            color: #ffc300;
        }

        .trip-count {
            background: rgba(93, 93, 255, 0.15);
            color: #5d5dff;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: bold;
        }

        .actions {
            display: flex;
            gap: 8px;
        }

        .btn {
            padding: 8px 12px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: bold;
            transition: all var(--transition-speed) ease;
        }

        .btn-edit {
            background: rgba(93, 93, 255, 0.15);
            color: #5d5dff;
        }

        .btn-edit:hover { 
            background: rgba(93, 93, 255, 0.25);
            transform: translateY(-2px);
        }

        .btn-view {
            background: rgba(166, 166, 190, 0.15);
            color: #a6a6be;
        }

        .btn-view:hover { 
            background: rgba(166, 166, 190, 0.25);
            transform: translateY(-2px);
        }

        .btn-delete {
            background: rgba(255, 71, 87, 0.15);
            color: #ff4757;
        }

        .btn-delete:hover { 
            background: rgba(255, 71, 87, 0.25);
            transform: translateY(-2px);
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease;
        }

        .modal.active { 
            display: flex; 
        }

        .modal-content {
            background: var(--card-bg);
            padding: 25px;
            border-radius: 16px;
            border: 1px solid var(--border-color);
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease;
        }

        .modal-header {
            font-size: 1.5rem;
            color: var(--text-primary);
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: bold;
        }

        .modal-close {
            cursor: pointer;
            font-size: 1.5rem;
            color: var(--text-tertiary);
            background: none;
            border: none;
            transition: color var(--transition-speed) ease;
        }

        .modal-close:hover {
            color: var(--bfp-red);
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.2);
            color: var(--text-primary);
            font-family: inherit;
            font-size: 1rem;
            transition: all var(--transition-speed) ease;
        }

        .form-group input:hover,
        .form-group select:hover {
            border-color: rgba(93, 93, 255, 0.3);
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #5d5dff;
            background: rgba(93, 93, 255, 0.08);
        }

        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .btn-primary {
            flex: 1;
            background: #5d5dff;
            color: white;
            padding: 12px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            font-size: 1rem;
            transition: all var(--transition-speed) ease;
        }

        .btn-primary:hover { 
            background: #4d4dff;
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(93, 93, 255, 0.3);
        }

        .btn-secondary {
            flex: 1;
            background: rgba(166, 166, 190, 0.15);
            color: #a6a6be;
            padding: 12px;
            border: 1px solid rgba(166, 166, 190, 0.3);
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            font-size: 1rem;
            transition: all var(--transition-speed) ease;
        }

        .btn-secondary:hover { 
            background: rgba(166, 166, 190, 0.25);
            border-color: #a6a6be;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin: 20px 0;
        }

        .detail-item {
            background: rgba(0, 0, 0, 0.2);
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #5d5dff;
            border: 1px solid var(--border-color);
        }

        .detail-label {
            font-weight: bold;
            color: var(--text-tertiary);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .detail-value {
            color: var(--text-primary);
            font-size: 1.1rem;
            margin-top: 5px;
            font-weight: 600;
        }

        footer { 
            display: none; 
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        @keyframes slideUp {
            from {
                transform: translateY(20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        @media (max-width: 768px) {
            main {
                padding: 15px;
                gap: 10px;
            }

            .navbar { 
                overflow-x: auto;
                flex-wrap: nowrap;
            }

            .nav-btn { 
                font-size: 0.9rem; 
                padding: 12px 15px;
                flex-shrink: 0;
            }

            .page-header { 
                flex-direction: column;
                align-items: stretch;
            }

            .page-title {
                font-size: 1.2rem;
            }

            .btn-add { 
                width: 100%;
                padding: 14px 16px;
                font-size: 0.95rem;
            }

            table { 
                font-size: 0.85rem;
                display: none;
            }

            table.mobile-visible {
                display: table;
            }

            table th, table td { 
                padding: 10px 8px; 
            }

            .actions { 
                flex-direction: column;
                gap: 6px;
            }

            .btn { 
                width: 100%;
                padding: 10px 8px;
                font-size: 0.8rem;
            }

            .detail-grid { 
                grid-template-columns: 1fr; 
            }

            .modal-content {
                width: 95%;
                padding: 20px;
            }

            .modal-header {
                font-size: 1.2rem;
            }

            .form-group input,
            .form-group select {
                font-size: 16px;
                padding: 12px;
            }

            .form-actions {
                gap: 8px;
            }

            .btn-primary,
            .btn-secondary {
                padding: 12px;
                font-size: 0.95rem;
            }

            .badge {
                font-size: 0.75rem;
            }

            .trip-count {
                font-size: 0.75rem;
            }

            /* Mobile Card View */
            .table-card {
                background: rgba(0, 0, 0, 0.2);
                border: 1px solid var(--border-color);
                border-radius: 12px;
                padding: 15px;
                margin-bottom: 12px;
                display: none;
            }

            .table-card.active {
                display: block;
            }

            .card-row {
                display: flex;
                justify-content: space-between;
                padding: 8px 0;
                border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            }

            .card-row:last-child {
                border-bottom: none;
            }

            .card-label {
                font-weight: bold;
                color: var(--text-tertiary);
                font-size: 0.85rem;
                text-transform: uppercase;
                flex: 1;
            }

            .card-value {
                color: var(--text-primary);
                text-align: right;
                flex: 1;
            }

            .card-actions {
                display: flex;
                gap: 8px;
                margin-top: 12px;
            }

            .card-actions .btn {
                flex: 1;
            }
        }

        @media (max-width: 480px) {
            main {
                padding: 10px;
            }

            .container {
                border-radius: 12px;
                border: none;
            }

            .navbar {
                border-bottom: 1px solid var(--border-color);
            }

            .nav-btn {
                font-size: 0.8rem;
                padding: 10px 12px;
                white-space: nowrap;
            }

            .page-header {
                gap: 10px;
            }

            .page-title {
                font-size: 1rem;
                margin-bottom: 10px;
            }

            .btn-add {
                padding: 12px 14px;
                font-size: 0.9rem;
            }

            .content {
                padding: 15px;
            }

            .modal-content {
                width: 98%;
                padding: 15px;
                max-width: none;
            }

            .modal-header {
                font-size: 1rem;
                margin-bottom: 15px;
            }

            .form-group {
                margin-bottom: 12px;
            }

            .form-group label {
                font-size: 0.85rem;
                margin-bottom: 6px;
            }

            .form-group input,
            .form-group select {
                font-size: 16px;
                padding: 12px;
                border-radius: 6px;
            }

            .row {
                flex-direction: column !important;
                gap: 0 !important;
            }

            .row > * {
                flex: 1 !important;
            }

            .form-actions {
                flex-direction: column;
                gap: 10px;
            }

            .btn-primary,
            .btn-secondary {
                width: 100%;
                padding: 14px 12px;
                font-size: 0.95rem;
            }

            .detail-grid {
                gap: 10px;
            }

            .detail-item {
                padding: 12px;
                border-radius: 8px;
            }

            .detail-label {
                font-size: 0.75rem;
            }

            .detail-value {
                font-size: 1rem;
                margin-top: 4px;
            }

            .table-card {
                padding: 12px;
                margin-bottom: 10px;
                border-radius: 10px;
            }

            .card-row {
                padding: 6px 0;
                flex-direction: column;
            }

            .card-label {
                font-size: 0.8rem;
                margin-bottom: 4px;
            }

            .card-value {
                text-align: left;
                margin-bottom: 8px;
                color: var(--text-primary);
                font-weight: 500;
            }

            .card-actions {
                gap: 6px;
                margin-top: 10px;
            }

            .card-actions .btn {
                padding: 10px 8px;
                font-size: 0.75rem;
            }

            .alert {
                padding: 12px;
                font-size: 0.9rem;
                margin-bottom: 15px;
            }
        }
    </style>
</head>
<body>

<div class="wrapper">
    <?php include("Components/Sidebar.php");?>

    <main>
        <div class="container">
            <!-- Navbar Tabs -->
            <div class="navbar">
                <button class="nav-btn" onclick="switchTab('users', this)">👥 Users</button>
                <button class="nav-btn active" onclick="switchTab('drivers', this)">🚗 Drivers</button>
            </div>

            <!-- Drivers Tab -->
            <div id="drivers" class="content active">
                <div class="page-header">
                    <div class="page-title">Manage Drivers</div>
                    <button class="btn-add" onclick="openAddDriverModal()">➕ Add New Driver</button>
                </div>

                <?php if ($message): ?>
                    <div class="alert success">
                        <span><?php echo htmlspecialchars($message); ?></span>
                        <span class="alert-close" onclick="this.parentElement.style.display='none';">✕</span>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert error">
                        <span><?php echo htmlspecialchars($error); ?></span>
                        <span class="alert-close" onclick="this.parentElement.style.display='none';">✕</span>
                    </div>
                <?php endif; ?>

                <?php if (count($drivers) > 0): ?>
                    <!-- Desktop Table View -->
                    <table class="drivers-table">
                        <thead>
                            <tr>
                                <th>Driver ID</th>
                                <th>Full Name</th>
                                <th>License No.</th>
                                <th>Status</th>
                                <th>Trip Count</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($drivers as $driver): ?>
                                <tr>
                                    <td>#<?php echo htmlspecialchars($driver['driver_id']); ?></td>
                                    <td><?php echo htmlspecialchars($driver['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($driver['license_no'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="badge <?php echo strtolower($driver['status']); ?>">
                                            <?php echo ucfirst($driver['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="trip-count"><?php echo (int)$driver['trip_count']; ?> trips</span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <button class="btn btn-view" onclick="openViewDriverModal(<?php echo $driver['driver_id']; ?>, '<?php echo htmlspecialchars($driver['full_name']); ?>', '<?php echo htmlspecialchars($driver['license_no'] ?? ''); ?>', '<?php echo htmlspecialchars($driver['status']); ?>', <?php echo (int)$driver['trip_count']; ?>)">View</button>
                                            <button class="btn btn-edit" onclick="openEditDriverModal(<?php echo $driver['driver_id']; ?>, '<?php echo htmlspecialchars($driver['full_name']); ?>', '<?php echo htmlspecialchars($driver['license_no'] ?? ''); ?>', '<?php echo htmlspecialchars($driver['status']); ?>')">Edit</button>
                                            <button class="btn btn-delete" onclick="confirmDeleteDriver(<?php echo $driver['driver_id']; ?>)">Delete</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Mobile Card View -->
                    <div class="drivers-mobile-view">
                        <?php foreach ($drivers as $driver): ?>
                            <div class="table-card active">
                                <div class="card-row">
                                    <div class="card-label">Name</div>
                                    <div class="card-value"><?php echo htmlspecialchars($driver['full_name']); ?></div>
                                </div>
                                <div class="card-row">
                                    <div class="card-label">License</div>
                                    <div class="card-value"><?php echo htmlspecialchars($driver['license_no'] ?? 'N/A'); ?></div>
                                </div>
                                <div class="card-row">
                                    <div class="card-label">Status</div>
                                    <div class="card-value">
                                        <span class="badge <?php echo strtolower($driver['status']); ?>">
                                            <?php echo ucfirst($driver['status']); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="card-row">
                                    <div class="card-label">Trips</div>
                                    <div class="card-value"><?php echo (int)$driver['trip_count']; ?></div>
                                </div>
                                <div class="card-actions">
                                    <button class="btn btn-view" onclick="openViewDriverModal(<?php echo $driver['driver_id']; ?>, '<?php echo htmlspecialchars($driver['full_name']); ?>', '<?php echo htmlspecialchars($driver['license_no'] ?? ''); ?>', '<?php echo htmlspecialchars($driver['status']); ?>', <?php echo (int)$driver['trip_count']; ?>)">View</button>
                                    <button class="btn btn-edit" onclick="openEditDriverModal(<?php echo $driver['driver_id']; ?>, '<?php echo htmlspecialchars($driver['full_name']); ?>', '<?php echo htmlspecialchars($driver['license_no'] ?? ''); ?>', '<?php echo htmlspecialchars($driver['status']); ?>')">Edit</button>
                                    <button class="btn btn-delete" onclick="confirmDeleteDriver(<?php echo $driver['driver_id']; ?>)">Delete</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; color: #999; margin-top: 20px;">No drivers found.</p>
                <?php endif; ?>
            </div>

            <!-- Users Tab -->
            <div id="users" class="content">
                <div class="page-header">
                    <div class="page-title">Manage Users</div>
                    <button class="btn-add" onclick="openAddUserModal()">➕ Add New User</button>
                </div>

                <?php if (count($users) > 0): ?>
                    <!-- Desktop Table View -->
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Department</th>
                                <th>Position</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')); ?></td>
                                    <td><?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="badge <?php echo strtolower($user['role']); ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['department'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($user['position'] ?? 'N/A'); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <div class="actions">
                                            <button class="btn btn-edit" onclick="openEditUserModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>', '<?php echo htmlspecialchars($user['role']); ?>')">Edit</button>
                                            <button class="btn btn-delete" onclick="confirmDeleteUser(<?php echo $user['id']; ?>)">Delete</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Mobile Card View -->
                    <div class="users-mobile-view">
                        <?php foreach ($users as $user): ?>
                            <div class="table-card active">
                                <div class="card-row">
                                    <div class="card-label">Username</div>
                                    <div class="card-value"><?php echo htmlspecialchars($user['username']); ?></div>
                                </div>
                                <div class="card-row">
                                    <div class="card-label">Full Name</div>
                                    <div class="card-value"><?php echo htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')); ?></div>
                                </div>
                                <div class="card-row">
                                    <div class="card-label">Email</div>
                                    <div class="card-value"><?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></div>
                                </div>
                                <div class="card-row">
                                    <div class="card-label">Role</div>
                                    <div class="card-value">
                                        <span class="badge <?php echo strtolower($user['role']); ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="card-row">
                                    <div class="card-label">Department</div>
                                    <div class="card-value"><?php echo htmlspecialchars($user['department'] ?? 'N/A'); ?></div>
                                </div>
                                <div class="card-row">
                                    <div class="card-label">Position</div>
                                    <div class="card-value"><?php echo htmlspecialchars($user['position'] ?? 'N/A'); ?></div>
                                </div>
                                <div class="card-row">
                                    <div class="card-label">Created</div>
                                    <div class="card-value"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></div>
                                </div>
                                <div class="card-actions">
                                    <button class="btn btn-edit" onclick="openEditUserModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>', '<?php echo htmlspecialchars($user['role']); ?>')">Edit</button>
                                    <button class="btn btn-delete" onclick="confirmDeleteUser(<?php echo $user['id']; ?>)">Delete</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; color: #999; margin-top: 20px;">No users found.</p>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- DRIVER MODALS -->

<!-- Add Driver Modal -->
<div id="addDriverModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <span>Add New Driver</span>
            <span class="modal-close" onclick="closeAddDriverModal();">✕</span>
        </div>
        <form method="POST">
            <input type="hidden" name="add_driver" value="1">
            <div class="form-group">
                <label>Full Name *</label>
                <input type="text" name="full_name" required placeholder="Enter driver's full name">
            </div>
            <div class="form-group">
                <label>License No.</label>
                <input type="text" name="license_no" placeholder="Enter license number">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status" required>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn-primary">Add Driver</button>
                <button type="button" class="btn-secondary" onclick="closeAddDriverModal();">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- View Driver Modal -->
<div id="viewDriverModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <span>Driver Details</span>
            <span class="modal-close" onclick="closeViewDriverModal();">✕</span>
        </div>
        <div class="detail-grid">
            <div class="detail-item">
                <div class="detail-label">Driver ID</div>
                <div class="detail-value">#<span id="view_driver_id"></span></div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Full Name</div>
                <div class="detail-value"><span id="view_driver_name"></span></div>
            </div>
            <div class="detail-item">
                <div class="detail-label">License No.</div>
                <div class="detail-value"><span id="view_driver_license"></span></div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Status</div>
                <div class="detail-value"><span id="view_driver_status"></span></div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Total Trips</div>
                <div class="detail-value"><span id="view_driver_trips"></span></div>
            </div>
        </div>
        <div class="form-actions">
            <button type="button" class="btn-secondary" onclick="closeViewDriverModal();">Close</button>
        </div>
    </div>
</div>

<!-- Edit Driver Modal -->
<div id="editDriverModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <span>Edit Driver</span>
            <span class="modal-close" onclick="closeEditDriverModal();">✕</span>
        </div>
        <form method="POST">
            <input type="hidden" id="edit_driver_id" name="driver_id">
            <input type="hidden" name="update_driver" value="1">
            <div class="form-group">
                <label>Full Name *</label>
                <input type="text" id="edit_full_name" name="full_name" required>
            </div>
            <div class="form-group">
                <label>License No.</label>
                <input type="text" id="edit_license_no" name="license_no">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select id="edit_driver_status" name="status" required>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn-primary">Save Changes</button>
                <button type="button" class="btn-secondary" onclick="closeEditDriverModal();">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Driver Modal -->
<div id="deleteDriverModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <span>Confirm Delete</span>
            <span class="modal-close" onclick="closeDeleteDriverModal();">✕</span>
        </div>
        <p style="margin-bottom: 20px; color: #666;">Are you sure you want to delete this driver? This action cannot be undone.</p>
        <form method="POST">
            <input type="hidden" id="delete_driver_id" name="driver_id">
            <input type="hidden" name="delete_driver" value="1">
            <div class="form-actions">
                <button type="submit" class="btn-primary" style="background: #dc3545;">Delete Driver</button>
                <button type="button" class="btn-secondary" onclick="closeDeleteDriverModal();">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- USER MODALS -->

<!-- Edit User Modal -->
<!-- Add User Modal -->
<div id="addUserModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <span>Add New User</span>
            <span class="modal-close" onclick="closeAddUserModal();">✕</span>
        </div>
        <form method="POST">
            <input type="hidden" name="add_user" value="1">
            <div class="form-group">
                <label>Username *</label>
                <input type="text" name="username" required placeholder="e.g. jdoe2026">
            </div>
            <div class="row" style="display:flex; gap:10px;">
                <div class="form-group" style="flex:1;">
                    <label>First Name *</label>
                    <input type="text" name="first_name" required>
                </div>
                <div class="form-group" style="flex:1;">
                    <label>Last Name *</label>
                    <input type="text" name="last_name" required>
                </div>
            </div>
            <div class="form-group">
                <label>Email Address *</label>
                <input type="email" name="email" required placeholder="name@example.com">
            </div>
            <div class="form-group">
                <label>Role</label>
                <select id="add_user_role" name="role" class="form-select" required onchange="syncAddUserPosition()">
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div class="row" style="display:flex; gap:10px;">
                <div class="form-group" style="flex:1;">
                    <label>Department</label>
                    <input type="text" id="add_user_department" name="department" value="Fire Operations">
                </div>
                <div class="form-group" style="flex:1;">
                    <label>Position</label>
                    <input type="text" id="add_user_position" name="position" readonly>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn-primary">Save User</button>
                <button type="button" class="btn-secondary" onclick="closeAddUserModal();">Cancel</button>
            </div>
        </form>
    </div>
</div>
<div id="editUserModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <span>Edit User</span>
            <span class="modal-close" onclick="closeEditUserModal();">✕</span>
        </div>
        <form method="POST">
            <input type="hidden" id="edit_user_id" name="user_id">
            <input type="hidden" name="update_user" value="1">
            <div class="form-group">
                <label>Username</label>
                <input type="text" id="edit_username" readonly style="background: #f5f5f5;">
            </div>
            <div class="form-group">
                <label>Role</label>
                <select id="edit_user_role" name="role" required>
                    <option value="admin">Admin</option>
                    <option value="user">User</option>
                    <option value="chief">Chief</option>
                </select>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn-primary">Save Changes</button>
                <button type="button" class="btn-secondary" onclick="closeEditUserModal();">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete User Modal -->
<div id="deleteUserModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <span>Confirm Delete</span>
            <span class="modal-close" onclick="closeDeleteUserModal();">✕</span>
        </div>
        <p style="margin-bottom: 20px; color: #666;">Are you sure you want to delete this user? This action cannot be undone.</p>
        <form method="POST">
            <input type="hidden" id="delete_user_id" name="user_id">
            <input type="hidden" name="delete_user" value="1">
            <div class="form-actions">
                <button type="submit" class="btn-primary" style="background: #dc3545;">Delete User</button>
                <button type="button" class="btn-secondary" onclick="closeDeleteUserModal();">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Handle responsive display
    function handleResponsive() {
        const isMobile = window.innerWidth <= 768;
        
        // Handle drivers table/cards
        const driversTable = document.querySelector('.drivers-table');
        const driversMobileView = document.querySelector('.drivers-mobile-view');
        
        if (driversTable) {
            driversTable.style.display = isMobile ? 'none' : 'table';
        }
        if (driversMobileView) {
            driversMobileView.style.display = isMobile ? 'block' : 'none';
        }
        
        // Handle users table/cards
        const usersTable = document.querySelector('.users-table');
        const usersMobileView = document.querySelector('.users-mobile-view');
        
        if (usersTable) {
            usersTable.style.display = isMobile ? 'none' : 'table';
        }
        if (usersMobileView) {
            usersMobileView.style.display = isMobile ? 'block' : 'none';
        }
    }
    
    // Listen to resize events
    window.addEventListener('resize', handleResponsive);
    window.addEventListener('load', handleResponsive);
    document.addEventListener('DOMContentLoaded', handleResponsive);

    // ===== TAB SWITCHING =====
    function switchTab(tabName, btn) {
        // Hide all tabs
        const contents = document.querySelectorAll('.content');
        contents.forEach(content => content.classList.remove('active'));
        
        // Remove active from all buttons
        const navBtns = document.querySelectorAll('.nav-btn');
        navBtns.forEach(navBtn => navBtn.classList.remove('active'));
        
        // Show selected tab
        document.getElementById(tabName).classList.add('active');
        
        // Highlight active button
        btn.classList.add('active');
        
        // Ensure responsive display is correct
        setTimeout(handleResponsive, 100);
    }

    // ===== DRIVER MODALS =====
    function openAddDriverModal() {
        document.getElementById('addDriverModal').classList.add('active');
    }

    function closeAddDriverModal() {
        document.getElementById('addDriverModal').classList.remove('active');
    }

    function openViewDriverModal(driverId, driverName, licenseNo, status, tripCount) {
        document.getElementById('view_driver_id').innerText = driverId;
        document.getElementById('view_driver_name').innerText = driverName;
        document.getElementById('view_driver_license').innerText = licenseNo || 'N/A';
        document.getElementById('view_driver_status').innerHTML = '<span class="badge ' + status.toLowerCase() + '">' + status.charAt(0).toUpperCase() + status.slice(1) + '</span>';
        document.getElementById('view_driver_trips').innerText = tripCount + ' trips';
        document.getElementById('viewDriverModal').classList.add('active');
    }

    function closeViewDriverModal() {
        document.getElementById('viewDriverModal').classList.remove('active');
    }

    function openEditDriverModal(driverId, driverName, licenseNo, status) {
        document.getElementById('edit_driver_id').value = driverId;
        document.getElementById('edit_full_name').value = driverName;
        document.getElementById('edit_license_no').value = licenseNo;
        document.getElementById('edit_driver_status').value = status;
        document.getElementById('editDriverModal').classList.add('active');
    }

    function closeEditDriverModal() {
        document.getElementById('editDriverModal').classList.remove('active');
    }

    function confirmDeleteDriver(driverId) {
        document.getElementById('delete_driver_id').value = driverId;
        document.getElementById('deleteDriverModal').classList.add('active');
    }

    function closeDeleteDriverModal() {
        document.getElementById('deleteDriverModal').classList.remove('active');
    }

    // ===== USER MODALS =====
    function openEditUserModal(userId, username, role) {
        document.getElementById('edit_user_id').value = userId;
        document.getElementById('edit_username').value = username;
        document.getElementById('edit_user_role').value = role;
        document.getElementById('editUserModal').classList.add('active');
    }

    function closeEditUserModal() {
        document.getElementById('editUserModal').classList.remove('active');
    }

    function openAddUserModal() {
        document.getElementById('addUserModal').classList.add('active');
        // ensure department default and position are synced when opening
        const dept = document.getElementById('add_user_department');
        if (dept && dept.value.trim() === '') dept.value = 'Fire Operations';
        syncAddUserPosition();
    }

    function closeAddUserModal() {
        document.getElementById('addUserModal').classList.remove('active');
    }

    function syncAddUserPosition() {
        const roleEl = document.getElementById('add_user_role');
        const posEl = document.getElementById('add_user_position');
        if (!roleEl || !posEl) return;
        const val = roleEl.value || '';
        posEl.value = val;
    }

    function confirmDeleteUser(userId) {
        document.getElementById('delete_user_id').value = userId;
        document.getElementById('deleteUserModal').classList.add('active');
    }

    function closeDeleteUserModal() {
        document.getElementById('deleteUserModal').classList.remove('active');
    }

    // Close modals when clicking outside
    window.onclick = function(event) {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (event.target === modal) {
                modal.classList.remove('active');
            }
        });
    };
    // sidebar dropdown functionality
    document.querySelectorAll('.dropdown').forEach(item => {
        item.addEventListener('click', function(e) {
            if (e.target.closest('.submenu')) return;
            this.classList.toggle('active');
            document.querySelectorAll('.dropdown').forEach(other => {
                if (other !== this) other.classList.remove('active');
            });
        });
    });
</script>

</body>
</html>