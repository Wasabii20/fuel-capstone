<?php
session_start();
require_once("db_connect.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';

// Initialize admin code if not exists
if (!isset($_SESSION['admin_code'])) {
    $_SESSION['admin_code'] = 'Admin123';
}

// Handle admin code update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_admin_code'])) {
    $new_code = trim($_POST['new_admin_code'] ?? '');
    if (!empty($new_code)) {
        $_SESSION['admin_code'] = $new_code;
        // Optional: Save to database or file for persistence
        // For now, it's stored in session
    }
}

try {
    $stmt = $pdo->prepare("SELECT first_name, last_name, profile_pic, role, email, phone, department, position, employee_id 
                            FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        session_unset();
        session_destroy();
        header("Location: index.php?error=user_not_found");
        exit();
    }
} catch(Exception $e) {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit();
}

// Prefer user's provided profile pic; fall back to project default(s).
$default_pic_candidates = [
    'ALBUM/defult.png', // user-provided filename (misspelled 'defult')
    'ALBUM/default.png'
];
$default_pic_src = null;
foreach ($default_pic_candidates as $cand) {
    if (file_exists($cand)) { $default_pic_src = $cand; break; }
}
if ($default_pic_src === null) {
    $default_pic_src = 'ALBUM/default.png';
}

$profile_pic_src = (!empty($user['profile_pic']) && file_exists($user['profile_pic']))
    ? htmlspecialchars($user['profile_pic'])
    : htmlspecialchars($default_pic_src);

$_SESSION['profile_pic'] = $profile_pic_src;
$_SESSION['role'] = $user['role'] ?? 'admin';

if ($_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit();
}

$first_name = htmlspecialchars($user['first_name'] ?? '');
$last_name = htmlspecialchars($user['last_name'] ?? '');
$full_name = trim($first_name . ' ' . $last_name) ?: $username;
$greeting_name = $first_name ?: $username;

$email = htmlspecialchars($user['email'] ?? 'N/A');
$phone = htmlspecialchars($user['phone'] ?? 'N/A');
$department = htmlspecialchars($user['department'] ?? 'BFP Command Center');
$position = htmlspecialchars($user['position'] ?? 'Administrator');
$employee_id = htmlspecialchars($user['employee_id'] ?? 'N/A');
$role_display = ucfirst(htmlspecialchars($user['role'] ?? 'Admin'));

// Fetch notifications data
$pending_trips = $pdo->query("SELECT COUNT(*) as count FROM trip_tickets WHERE status = 'Pending'")->fetch(PDO::FETCH_ASSOC)['count'];
$submitted_trips = $pdo->query("SELECT COUNT(*) as count FROM trip_tickets WHERE status = 'Submitted'")->fetch(PDO::FETCH_ASSOC)['count'];
$new_users = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'user' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
$new_drivers = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'driver' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
$repair_requests = $pdo->query("SELECT COUNT(*) as count FROM vehicle_repairs WHERE status = 'pending'")->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
$fuel_refills = $pdo->query("SELECT COUNT(*) as count FROM trip_tickets WHERE status = 'Pending' AND ticket_date = CURDATE()")->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Recent notifications
$notifications = $pdo->query("
    SELECT 'trip_pending' as type, COUNT(*) as count, MAX(created_at) as time FROM trip_tickets WHERE status = 'Pending'
    UNION ALL
    SELECT 'trip_submitted', COUNT(*), MAX(created_at) FROM trip_tickets WHERE status = 'Submitted'
    UNION ALL
    SELECT 'repair_request', COUNT(*), MAX(requested_date) FROM vehicle_repairs WHERE status = 'pending'
    UNION ALL
    SELECT 'new_user', COUNT(*), MAX(created_at) FROM users WHERE role = 'user'
")->fetchAll(PDO::FETCH_ASSOC);

// Active trips (join drivers table to get correct driver name)
$active_trips = $pdo->query("
    SELECT tt.*, v.vehicle_no, d.full_name AS driver_name 
    FROM trip_tickets tt
    LEFT JOIN vehicles v ON tt.vehicle_plate_no = v.vehicle_no
    LEFT JOIN drivers d ON tt.driver_id = d.driver_id
    WHERE tt.status = 'Active'
    ORDER BY tt.created_at DESC LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Pending reports (use drivers table for driver full name)
$pending_reports = $pdo->query("
    SELECT tt.*, v.vehicle_no, d.full_name AS driver_name 
    FROM trip_tickets tt
    LEFT JOIN vehicles v ON tt.vehicle_plate_no = v.vehicle_no
    LEFT JOIN drivers d ON tt.driver_id = d.driver_id
    WHERE tt.status = 'Pending'
    ORDER BY tt.created_at DESC LIMIT 15
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo htmlspecialchars($username); ?> | Bureau of Fire Protection</title>
    <link rel="icon" href="ALBUM/favicon_io/favicon-32x32.png" type="image/x-icon" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine/dist/leaflet-routing-machine.css" />
    <style>
       :root { --primary-color: #5d5dff; --bg-dark: #1e1e2d; --bg-light-dark: #2a2a3e; --text-light: #e2e2e2; --text-gray: #a2a2c2; --border-color: #3d3d5c; }
* { box-sizing: border-box; }
body { margin: 0; font-family: 'Poppins', Arial, sans-serif; background: var(--bg-dark); color: var(--text-light); display: flex; flex-direction: column; height: 100vh; }
.wrapper { display: flex; flex: 1; overflow: visible; width: 100%; position: relative; }
main { display: flex; flex-direction: column; flex: 1; overflow-y: auto; overflow-x: hidden; width: 100%; padding: 0; }
.dashboard-header { display: flex; align-items: center; justify-content: space-between; background: var(--bg-light-dark); padding: 40px; border-radius: 0; border-bottom: 3px solid var(--primary-color); width: 100%; flex-shrink: 0; box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3); }
.profile-section { display: flex; align-items: center; gap: 30px; }
.profile-display-pic { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 4px solid var(--primary-color); box-shadow: 0 0 20px rgba(93, 93, 255, 0.3); }
.user-titles h1 { color: var(--primary-color); font-size: 2.2rem; margin: 0; font-weight: 700; }
.user-titles p { color: var(--text-gray); font-size: 1rem; margin: 5px 0 0 0; font-weight: 500; }
.admin-links-side { display: flex; flex-direction: column; gap: 10px; min-width: 200px; }
.admin-links-side a { color: var(--text-light); text-decoration: none; padding: 12px 15px; border-left: 3px solid transparent; transition: all 0.3s ease; display: flex; align-items: center; gap: 10px; font-size: 0.95rem; font-weight: 500; }
.admin-links-side a:hover { background: rgba(93, 93, 255, 0.1); border-left-color: var(--primary-color); color: var(--primary-color); }
.admin-links-side a i { color: var(--primary-color); font-size: 1rem; }
.dropdown-toggle { background: linear-gradient(135deg, var(--primary-color), #7070ff); color: white; padding: 12px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; transition: all 0.3s ease; display: flex; align-items: center; gap: 8px; }
.dropdown-toggle:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(93, 93, 255, 0.4); }
.account-dropdown { display: none; position: absolute; top: 100%; left: 40px; background: var(--bg-light-dark); border: 1px solid var(--border-color); border-radius: 8px; padding: 20px; min-width: 350px; z-index: 1000; box-shadow: 0 8px 20px rgba(0, 0, 0, 0.5); }
.account-dropdown.active { display: block; }
.account-dropdown h3 { color: var(--primary-color); margin-top: 0; font-size: 1.2rem; }
.account-field { margin: 15px 0; padding-bottom: 15px; border-bottom: 1px solid var(--border-color); }
.account-field:last-child { border-bottom: none; }
.account-field label { color: var(--text-gray); font-size: 0.9rem; font-weight: 600; display: flex; align-items: center; gap: 8px; }
.account-field label i { color: var(--primary-color); }
.account-field span { color: var(--text-light); display: block; margin-top: 5px; font-weight: 500; }
.account-edit-btn { display: block; margin-top: 15px; padding: 10px 20px; background: var(--primary-color); color: white; text-decoration: none; border-radius: 6px; text-align: center; font-weight: 600; transition: all 0.3s ease; }
.account-edit-btn:hover { background: #7070ff; box-shadow: 0 4px 12px rgba(93, 93, 255, 0.3); }
.pages-container { flex: 1; overflow-y: auto; padding: 40px; }
.page { display: none; }
.page.active { display: block; animation: fadeIn 0.3s ease; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
.page-navigation { display: flex; gap: 10px; margin-bottom: 30px; flex-wrap: wrap; background: var(--bg-light-dark); padding: 15px; border-radius: 10px; border: 1px solid var(--border-color); }
.page-nav-btn { padding: 10px 18px; background: transparent; border: 1px solid var(--border-color); color: var(--text-light); cursor: pointer; border-radius: 6px; font-weight: 600; transition: all 0.3s ease; font-family: 'Poppins', sans-serif; font-size: 0.9rem; }
.page-nav-btn:hover { border-color: var(--primary-color); color: var(--primary-color); }
.page-nav-btn.active { background: var(--primary-color); color: white; border-color: var(--primary-color); }
.badge { display: inline-flex; align-items: center; justify-content: center; min-width: 24px; height: 24px; padding: 0 6px; background: #ff5064; color: white; border-radius: 12px; font-size: 0.75rem; font-weight: 700; margin-left: 6px; }
.notification-item { background: var(--bg-light-dark); border-left: 4px solid var(--primary-color); padding: 20px; margin-bottom: 15px; border-radius: 8px; display: flex; align-items: start; gap: 15px; }
.notification-icon { font-size: 1.5rem; color: var(--primary-color); min-width: 30px; text-align: center; }
.notification-content h4 { margin: 0 0 5px 0; color: var(--text-light); }
.notification-content p { margin: 0; color: var(--text-gray); font-size: 0.9rem; }
.notification-time { color: var(--text-gray); font-size: 0.8rem; margin-top: 5px; }
.data-table { width: 100%; border-collapse: collapse; background: var(--bg-light-dark); border-radius: 8px; overflow: hidden; box-shadow: 0 6px 15px rgba(0, 0, 0, 0.3); }
.data-table thead { background: rgba(93, 93, 255, 0.2); border-bottom: 2px solid var(--primary-color); }
.data-table th { color: var(--primary-color); padding: 15px; text-align: left; font-weight: 600; }
.data-table td { color: var(--text-light); padding: 12px 15px; border-bottom: 1px solid var(--border-color); }
.data-table tbody tr:hover { background: rgba(93, 93, 255, 0.1); }
.status-badge { display: inline-block; padding: 6px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; }
.status-pending { background: rgba(255, 165, 0, 0.2); color: #ffb000; }
.status-submitted { background: rgba(93, 93, 255, 0.2); color: var(--primary-color); }
.status-completed { background: rgba(0, 255, 136, 0.2); color: #00ff88; }
.status-rejected { background: rgba(255, 80, 100, 0.2); color: #ff5064; }
.empty-state { text-align: center; padding: 60px 20px; color: var(--text-gray); }
.empty-state i { font-size: 4rem; color: var(--primary-color); margin-bottom: 20px; opacity: 0.5; }
.stat-card { background: var(--bg-light-dark); border: 1px solid var(--border-color); border-radius: 8px; padding: 20px; text-align: center; margin-bottom: 15px; cursor: pointer; transition: all 0.3s ease; text-decoration: none; color: inherit; display: block; }
.stat-card:hover { background: var(--bg-light); border-color: var(--primary-color); box-shadow: 0 4px 12px rgba(93, 93, 255, 0.2); transform: translateY(-2px); }
.stat-card h3 { margin: 0 0 10px 0; color: var(--text-gray); font-size: 0.9rem; }
.stat-card .number { font-size: 2.5rem; color: var(--primary-color); font-weight: 700; }
@media (max-width: 1200px) { .dashboard-header { flex-direction: column; gap: 20px; } .admin-links-side { width: 100%; flex-direction: row; } }
footer { display: none; }

/* Map Dropdown Styles */
.data-table tbody tr { cursor: pointer; position: relative; }
.trip-map-row { background: rgba(93, 93, 255, 0.05) !important; }
.map-dropdown { display: none; background: var(--bg-light-dark); padding: 20px; border-top: 2px solid var(--primary-color); }
.map-dropdown.show { display: block; animation: slideDown 0.3s ease; }
@keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
.trip-map-container { width: 100%; height: 350px; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3); }
#activeTripsMap { width: 100%; height: 100%; }
.map-info-panel { background: var(--bg-dark); padding: 15px; margin-top: 10px; border-radius: 6px; border-left: 3px solid var(--primary-color); }
.map-info-panel strong { color: var(--primary-color); }
.map-info-panel span { color: var(--text-light); margin-left: 8px; }

/* Animated Arrow Marker Styles */
.animated-arrow {
    width: 24px;
    height: 24px;
    background: linear-gradient(135deg, #5d5dff 0%, #7070ff 100%);
    clip-path: polygon(50% 0%, 100% 100%, 75% 75%, 50% 75%, 25% 75%, 0% 100%);
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.3));
}

.animated-marker {
    display: flex;
    align-items: center;
    justify-content: center;
    transform-origin: 50% 50%;
    transition: transform 0.1s linear;
}

.leaflet-map-instance {
    width: 100%;
    height: 100%;
}

/* Mobile Responsive Styles */
@media (max-width: 1200px) {
    .dashboard-header {
        flex-direction: column;
        gap: 20px;
    }
    
    .admin-links-side {
        width: 100%;
        flex-direction: row;
    }
}

@media (max-width: 768px) {
    html, body {
        overflow-x: hidden;
    }

    .dashboard-header {
        padding: 20px;
        flex-direction: column;
        gap: 15px;
        border-radius: 0;
    }

    .profile-section {
        gap: 15px;
        flex-direction: column;
        align-items: center;
        text-align: center;
    }

    .profile-display-pic {
        width: 70px;
        height: 70px;
        border-width: 3px;
    }

    .user-titles h1 {
        font-size: 1.5rem;
    }

    .user-titles p {
        font-size: 0.9rem;
    }

    .admin-links-side {
        width: 100%;
        flex-direction: row;
        gap: 10px;
        wrap: wrap;
    }

    .admin-links-side a {
        flex: 1;
        min-width: 130px;
        padding: 10px;
        font-size: 0.85rem;
        text-align: center;
        border-bottom: 2px solid transparent;
        border-left: none;
    }

    .admin-links-side a:hover {
        background: rgba(93, 93, 255, 0.1);
        border-bottom-color: var(--primary-color);
        border-left: none;
    }

    .pages-container {
        padding: 20px;
    }

    .page-navigation {
        gap: 8px;
        padding: 12px;
        overflow-x: auto;
        flex-wrap: nowrap;
        -webkit-overflow-scrolling: touch;
    }

    .page-nav-btn {
        padding: 8px 12px;
        font-size: 0.8rem;
        white-space: nowrap;
        flex-shrink: 0;
    }

    .page-nav-btn i {
        display: none;
    }

    .dropdown-toggle {
        padding: 10px 15px;
        font-size: 0.9rem;
        width: 100%;
    }

    .account-dropdown {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 90%;
        max-width: 400px;
        min-width: auto;
        z-index: 2000;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.8);
        max-height: 80vh;
        overflow-y: auto;
    }

    .account-field {
        margin: 12px 0;
        padding-bottom: 12px;
    }

    .account-field label {
        font-size: 0.85rem;
    }

    .account-field span {
        font-size: 0.9rem;
    }

    .stat-card {
        margin-bottom: 12px;
        padding: 15px;
    }

    .stat-card h3 {
        font-size: 0.85rem;
        margin-bottom: 8px;
    }

    .stat-card .number {
        font-size: 2rem;
    }

    .data-table {
        font-size: 0.85rem;
        overflow-x: auto;
        display: block;
    }

    .data-table thead,
    .data-table tbody,
    .data-table th,
    .data-table td,
    .data-table tr {
        display: block;
    }

    .data-table thead {
        display: none;
    }

    .data-table tbody tr {
        margin-bottom: 15px;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        padding: 15px;
        display: block;
    }

    .data-table td {
        border: none;
        padding: 8px 0;
        text-align: left;
        display: block;
        background: transparent;
    }

    .data-table td::before {
        content: attr(data-label);
        color: var(--primary-color);
        font-weight: 600;
        display: inline-block;
        min-width: 80px;
        margin-right: 10px;
    }

    .notification-item {
        gap: 10px;
        padding: 15px;
        margin-bottom: 12px;
    }

    .notification-icon {
        min-width: 24px;
        font-size: 1.3rem;
    }

    .notification-content h4 {
        font-size: 0.95rem;
    }

    .notification-content p {
        font-size: 0.85rem;
    }

    .trip-map-container {
        height: 300px;
    }

    .empty-state {
        padding: 40px 20px;
    }

    .empty-state i {
        font-size: 3rem;
    }

    iframe {
        height: auto !important;
        min-height: 500px !important;
    }
}

@media (max-width: 480px) {
    .dashboard-header {
        padding: 15px;
    }

    .profile-section {
        gap: 12px;
    }

    .profile-display-pic {
        width: 60px;
        height: 60px;
    }

    .user-titles h1 {
        font-size: 1.3rem;
    }

    .pages-container {
        padding: 15px;
    }

    .page-navigation {
        padding: 10px;
        gap: 6px;
    }

    .page-nav-btn {
        padding: 8px 10px;
        font-size: 0.7rem;
    }

    .page-nav-btn span.badge {
        font-size: 0.65rem;
        min-width: 18px;
        height: 18px;
        padding: 0 4px;
    }

    .admin-links-side a {
        min-width: 100px;
        padding: 8px;
        font-size: 0.8rem;
    }

    .admin-links-side a i {
        font-size: 0.9rem;
    }

    .dropdown-toggle {
        font-size: 0.85rem;
        padding: 8px 12px;
    }

    .account-dropdown {
        width: 95%;
        padding: 15px;
    }

    .account-dropdown h3 {
        font-size: 1rem;
    }

    .stat-card {
        padding: 12px;
        margin-bottom: 10px;
    }

    .stat-card h3 {
        font-size: 0.8rem;
    }

    .stat-card .number {
        font-size: 1.8rem;
    }

    .data-table tbody tr {
        margin-bottom: 12px;
        padding: 12px;
    }

    .data-table td {
        padding: 6px 0;
        font-size: 0.85rem;
    }

    .data-table td::before {
        min-width: 70px;
        font-size: 0.8rem;
    }

    .notification-item {
        padding: 12px;
        margin-bottom: 10px;
    }

    .notification-content h4 {
        font-size: 0.9rem;
    }

    .notification-time {
        font-size: 0.75rem;
    }

    h2 {
        font-size: 1.2rem;
    }

    h3 {
        font-size: 1rem;
    }

    .trip-map-container {
        height: 250px;
    }

    iframe {
        min-height: 400px !important;
    }
}
    </style>
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-routing-machine/dist/leaflet-routing-machine.js"></script>
</head>
<body>
    <div class="wrapper">
         <?php include("Components/Sidebar.php")?>

        <main>
            <!-- Dashboard Header -->
            <div class="dashboard-header">
                <div class="profile-section">
                    <img src="<?php echo $profile_pic_src; ?>" alt="Profile" class="profile-display-pic">
                    <div class="user-titles">
                        <h1>Welcome, <?php echo $greeting_name; ?>!</h1> 
                        <p><?php echo $role_display; ?> Dashboard</p>
                    </div>
                </div>

                <div style="position: relative;">
                    <button class="dropdown-toggle" id="accountToggle"><i class="fas fa-user"></i> Account</button>
                    <div class="account-dropdown" id="accountDropdown">
                        <h3><i class="fas fa-user-circle"></i> Account Details</h3>
                        <div class="account-field">
                            <label><i class="fas fa-user"></i> Full Name:</label>
                            <span><?php echo $full_name; ?></span>
                        </div>
                        <div class="account-field">
                            <label><i class="fas fa-envelope"></i> Email:</label>
                            <span><?php echo $email; ?></span>
                        </div>
                        <div class="account-field">
                            <label><i class="fas fa-phone"></i> Phone:</label>
                            <span><?php echo $phone; ?></span>
                        </div>
                        <div class="account-field">
                            <label><i class="fas fa-briefcase"></i> Position:</label>
                            <span><?php echo $position; ?></span>
                        </div>
                        <div class="account-field">
                            <label><i class="fas fa-building"></i> Department:</label>
                            <span><?php echo $department; ?></span>
                        </div>
                        <div class="account-field">
                            <label><i class="fas fa-id-card"></i> Employee ID:</label>
                            <span><?php echo $employee_id; ?></span>
                        </div>
                        <a href="Profile_Edit.php" class="account-edit-btn"><i class="fas fa-edit"></i> Edit Profile</a>
                    </div>
                </div>

                <div class="admin-links-side">
                    <a href="Management.php"><i class="fas fa-users"></i> Manage Users</a>
                    <a href="User_Logs.php"><i class="fas fa-clipboard-list"></i> Activity Logs</a>
                </div>
            </div>

            <!-- Pages Container -->
            <div class="pages-container">
                <!-- Page Navigation -->
                <div class="page-navigation">
                    <button class="page-nav-btn active" onclick="showPage(0)">
                        <i class="fas fa-bell"></i> Notifications
                        <?php 
                            $total_notifications = $pending_trips + $submitted_trips + $repair_requests + $new_users + $new_drivers;
                            if ($total_notifications > 0): 
                        ?>
                            <span class="badge"><?php echo $total_notifications; ?></span>
                        <?php endif; ?>
                    </button>
                    <button class="page-nav-btn" onclick="showPage(1)">
                        <i class="fas fa-road"></i> Active Trips
                        <?php if (count($active_trips) > 0): ?>
                            <span class="badge"><?php echo count($active_trips); ?></span>
                        <?php endif; ?>
                    </button>
                    <button class="page-nav-btn" onclick="showPage(2)">
                        <i class="fas fa-hourglass-half"></i> Pending Tickets
                        <?php if (count($pending_reports) > 0): ?>
                            <span class="badge"><?php echo count($pending_reports); ?></span>
                        <?php endif; ?>
                    </button>
                    <button class="page-nav-btn" onclick="showPage(3)">
                        <i class="fas fa-chart-bar"></i> Expenses
                    </button>
                    <button class="page-nav-btn" onclick="showPage(4)">
                        <i class="fas fa-gas-pump"></i> Fuel Logs
                    </button>
                    <button class="page-nav-btn" onclick="showPage(5)">
                        <i class="fas fa-tools"></i> Vehicle Repairs
                        <?php if ($repair_requests > 0): ?>
                            <span class="badge"><?php echo $repair_requests; ?></span>
                        <?php endif; ?>
                    </button>
                    <button class="page-nav-btn" onclick="showPage(6)">
                        <i class="fas fa-lock"></i> Admin Credentials
                    </button>
                </div>

                <!-- Page 1: Notifications -->
                <div class="page active">
                    <h2><i class="fas fa-bell"></i> Dashboard Notifications</h2>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
                        <a href="Trip_ticket_dashboard.php?status=Pending" class="stat-card">
                            <h3><i class="fas fa-hourglass-half"></i> Pending Trips</h3>
                            <div class="number"><?php echo $pending_trips; ?></div>
                        </a>
                        <a href="Trip_ticket_dashboard.php?status=Submitted" class="stat-card">
                            <h3><i class="fas fa-check-double"></i> Submitted Trips</h3>
                            <div class="number"><?php echo $submitted_trips; ?></div>
                        </a>
                        <a href="vehicle_repair_request_list.php?status=pending" class="stat-card">
                            <h3><i class="fas fa-tools"></i> Repair Requests</h3>
                            <div class="number"><?php echo $repair_requests; ?></div>
                        </a>
                        <a href="Management.php" class="stat-card">
                            <h3><i class="fas fa-users"></i> New Users</h3>
                            <div class="number"><?php echo $new_users; ?></div>
                        </a>
                        <a href="Management.php" class="stat-card">
                            <h3><i class="fas fa-user-tie"></i> New Drivers</h3>
                            <div class="number"><?php echo $new_drivers; ?></div>
                        </a>
                        <a href="fuel_summary.php" class="stat-card">
                            <h3><i class="fas fa-gas-pump"></i> Fuel Refills Needed</h3>
                            <div class="number"><?php echo $fuel_refills; ?></div>
                        </a>
                    </div>

                    <h3 style="color: var(--primary-color); margin-top: 30px;">Recent Notifications</h3>
                    
                    <?php if ($pending_trips > 0): ?>
                        <div class="notification-item">
                            <div class="notification-icon"><i class="fas fa-hourglass-half"></i></div>
                            <div class="notification-content">
                                <h4><?php echo $pending_trips; ?> Pending Trip<?php echo $pending_trips != 1 ? 's' : ''; ?></h4>
                                <p>There <?php echo $pending_trips == 1 ? 'is' : 'are'; ?> <?php echo $pending_trips; ?> trip ticket<?php echo $pending_trips != 1 ? 's' : ''; ?> waiting for approval</p>
                                <div class="notification-time"><i class="fas fa-clock"></i> Awaiting review</div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($submitted_trips > 0): ?>
                        <div class="notification-item">
                            <div class="notification-icon"><i class="fas fa-check-double"></i></div>
                            <div class="notification-content">
                                <h4><?php echo $submitted_trips; ?> Submitted Trip<?php echo $submitted_trips != 1 ? 's' : ''; ?></h4>
                                <p>Completed trips are ready for review and analytics</p>
                                <div class="notification-time"><i class="fas fa-clock"></i> Ready for processing</div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($repair_requests > 0): ?>
                        <div class="notification-item">
                            <div class="notification-icon"><i class="fas fa-tools"></i></div>
                            <div class="notification-content">
                                <h4><?php echo $repair_requests; ?> Vehicle Repair Request<?php echo $repair_requests != 1 ? 's' : ''; ?></h4>
                                <p>Maintenance request<?php echo $repair_requests != 1 ? 's' : ''; ?> awaiting your approval</p>
                                <div class="notification-time"><i class="fas fa-clock"></i> Action required</div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($new_users > 0): ?>
                        <div class="notification-item">
                            <div class="notification-icon"><i class="fas fa-users"></i></div>
                            <div class="notification-content">
                                <h4><?php echo $new_users; ?> New User<?php echo $new_users != 1 ? 's' : ''; ?> Registered</h4>
                                <p>New account<?php echo $new_users != 1 ? 's' : ''; ?> created in the last 7 days</p>
                                <div class="notification-time"><i class="fas fa-clock"></i> Recently joined</div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($new_drivers > 0): ?>
                        <div class="notification-item">
                            <div class="notification-icon"><i class="fas fa-user-tie"></i></div>
                            <div class="notification-content">
                                <h4><?php echo $new_drivers; ?> New Driver<?php echo $new_drivers != 1 ? 's' : ''; ?> Applied</h4>
                                <p>New driver application<?php echo $new_drivers != 1 ? 's' : ''; ?> available for review</p>
                                <div class="notification-time"><i class="fas fa-clock"></i> Pending verification</div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($pending_trips == 0 && $submitted_trips == 0 && $repair_requests == 0 && $new_users == 0 && $new_drivers == 0 && $fuel_refills == 0): ?>
                        <div class="empty-state">
                            <i class="fas fa-check-circle"></i>
                            <h3>All Clear!</h3>
                            <p>No pending notifications at this time</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Page 2: Active Trips -->
                <div class="page">
                    <h2><i class="fas fa-road"></i> Active Trips Today</h2>
                    <?php if (count($active_trips) > 0): ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Control No.</th>
                                    <th>Vehicle</th>
                                    <th>Driver</th>
                                    <th>Destination</th>
                                    <th>Purpose</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($active_trips as $trip): ?>
                                    <tr class="trip-row" 
                                        data-trip-id="<?php echo htmlspecialchars($trip['id'] ?? ''); ?>"
                                        data-destination="<?php echo htmlspecialchars($trip['places_to_visit'] ?? 'N/A'); ?>"
                                        data-vehicle="<?php echo htmlspecialchars($trip['vehicle_no'] ?? $trip['vehicle_plate_no']); ?>"
                                        data-driver="<?php echo htmlspecialchars($trip['driver_name'] ?? 'Unassigned'); ?>"
                                        data-purpose="<?php echo htmlspecialchars($trip['purpose'] ?? 'N/A'); ?>">
                                        <td data-label="Control No.:"><?php echo htmlspecialchars($trip['control_no']); ?></td>
                                        <td data-label="Vehicle:"><?php echo htmlspecialchars($trip['vehicle_no'] ?? $trip['vehicle_plate_no']); ?></td>
                                        <td data-label="Driver:"><?php echo htmlspecialchars($trip['driver_name'] ?? 'Unassigned'); ?></td>
                                        <td data-label="Destination:"><?php echo htmlspecialchars($trip['places_to_visit'] ?? 'N/A'); ?></td>
                                        <td data-label="Purpose:"><?php echo htmlspecialchars($trip['purpose'] ?? 'N/A'); ?></td>
                                        <td data-label="Status:"><span class="status-badge status-submitted"><?php echo htmlspecialchars($trip['status']); ?></span></td>
                                    </tr>
                                    <tr class="map-dropdown-row">
                                        <td colspan="6">
                                            <div class="map-dropdown" id="map-<?php echo htmlspecialchars($trip['id'] ?? rand(1, 9999)); ?>">
                                                <div class="trip-map-container">
                                                    <div id="activeTripsMap-<?php echo htmlspecialchars($trip['id'] ?? rand(1, 9999)); ?>" class="leaflet-map-instance"></div>
                                                </div>
                                                <div class="map-info-panel">
                                                    <div><strong>📍 Destination:</strong> <span><?php echo htmlspecialchars($trip['places_to_visit'] ?? 'N/A'); ?></span></div>
                                                    <div><strong>🚗 Vehicle:</strong> <span><?php echo htmlspecialchars($trip['vehicle_no'] ?? $trip['vehicle_plate_no']); ?></span></div>
                                                    <div><strong>👤 Driver:</strong> <span><?php echo htmlspecialchars($trip['driver_name'] ?? 'Unassigned'); ?></span></div>
                                                    <div><strong>📋 Purpose:</strong> <span><?php echo htmlspecialchars($trip['purpose'] ?? 'N/A'); ?></span></div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <h3>No Active Trips</h3>
                            <p>No trips are currently active for today</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Page 3: Pending Tickets -->
                <div class="page">
                            <h2><i class="fas fa-hourglass-half"></i> Pending Trip Tickets</h2>
                            
                            <?php if (!empty($pending_reports) && count($pending_reports) > 0): ?>
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Control No.</th>
                                            <th>Date</th>
                                            <th>Vehicle</th>
                                            <th>Driver</th>
                                            <th>Destination</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                            <?php foreach ($pending_reports as $report): ?>
                                <tr>
                                    <td data-label="Control No.:"><?php echo htmlspecialchars($report['control_no']); ?></td>
                                    <td data-label="Date:"><?php echo date('M d, Y', strtotime($report['ticket_date'])); ?></td>
                                    <td data-label="Vehicle:"><?php echo htmlspecialchars($report['vehicle_no'] ?? $report['vehicle_plate_no'] ?? 'N/A'); ?></td>
                                    
                                    <td data-label="Driver:">
                                        <?php 
                                            // Use driver_name from drivers table
                                            echo htmlspecialchars($report['driver_name'] ?? 'Unassigned');
                                        ?>
                                    </td>
                                    
                                    <td data-label="Destination:"><?php echo htmlspecialchars($report['places_to_visit'] ?? 'N/A'); ?></td>
                                    <td data-label="Status:">
                                        <span class="status-badge status-pending">
                                            <?php echo htmlspecialchars($report['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                                </table>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-inbox"></i>
                                    <h3>No Pending Tickets</h3>
                                    <p>All trip tickets have been processed</p>
                                </div>
                            <?php endif; ?>
                        </div>

                <!-- Page 4: Expenses (Inline) -->
                <div class="page">
                    <h2><i class="fas fa-chart-bar"></i> Expenses Summary</h2>
                    <iframe src="Expenses.php" style="width: 100%; height: 1000px; border: none; border-radius: 8px;"></iframe>
                </div>

                <!-- Page 5: Fuel Logs (Inline) -->
                <div class="page">
                    <h2><i class="fas fa-gas-pump"></i> Vehicle Fuel Logs</h2>
                    <iframe src="vehicle_fuel_logs.php" style="width: 100%; height: 800px; border: none; border-radius: 8px;"></iframe>
                </div>

                <!-- Page 6: Vehicle Repairs Approval (Inline) -->
                <div class="page">
                    <h2><i class="fas fa-tools"></i> Vehicle Repairs - Approval Management</h2>
                    <iframe src="vehicle_repairs_Admin_approval_list.php" style="width: 100%; height: 1000px; border: none; border-radius: 8px;"></iframe>
                </div>

                <!-- Page 7: Admin Credentials -->
                <div class="page">
                    <h2><i class="fas fa-lock"></i> Admin Credentials Management</h2>
                    <div style="background: var(--bg-light-dark); padding: 30px; border-radius: 10px; border: 1px solid var(--border-color);">
                        <div style="max-width: 1500px;">
                            <p style="color: var(--text-light); margin-bottom: 20px;">🔐 Configure the admin code that new admin users must use to register.</p>
                            
                            <div style="background: rgba(93, 93, 255, 0.1); padding: 15px; border-radius: 8px; border-left: 3px solid var(--primary-color); margin-bottom: 20px;">
                                <strong style="color: var(--primary-color);">Current Admin Code:</strong>
                                <div style="font-size: 1.2rem; font-family: monospace; letter-spacing: 2px; color: var(--primary-color); margin-top: 10px; user-select: all;">
                                    <?php echo isset($_SESSION['admin_code']) ? htmlspecialchars($_SESSION['admin_code']) : 'Admin123'; ?>
                                </div>
                            </div>

                            <form method="POST" style="background: rgba(0, 0, 0, 0.2); padding: 20px; border-radius: 8px;">
                                <div style="margin-bottom: 15px;">
                                    <label style="display: block; margin-bottom: 8px; color: var(--text-light); font-weight: 600;">New Admin Code</label>
                                    <input type="text" name="new_admin_code" placeholder="Enter new admin code" 
                                           style="width: 100%; padding: 12px; background: var(--bg-dark); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-light);" required>
                                </div>
                                <button type="submit" name="update_admin_code" 
                                        style="background: var(--primary-color); color: white; padding: 12px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; transition: all 0.3s;">
                                    💾 Update Admin Code
                                </button>
                            </form>

                            <div style="margin-top: 20px; padding: 15px; background: rgba(255, 193, 7, 0.1); border-radius: 8px; border-left: 3px solid #ffc107;">
                                <strong style="color: #ffc107;">⚠️ Note:</strong>
                                <p style="color: var(--text-light); margin-top: 8px; font-size: 0.9rem;">
                                    The admin code is used as an additional verification step for admin registration. Default code: <strong>Admin123</strong>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        const pageNavBtns = document.querySelectorAll('.page-nav-btn');
        const pages = document.querySelectorAll('.page');
        const accountToggle = document.getElementById('accountToggle');
        const accountDropdown = document.getElementById('accountDropdown');

        // Office coordinates (BFP Station)
        const OFFICE_LAT = 10.132752;
        const OFFICE_LNG = 124.834795;

        // Store active map instances
        const mapInstances = {};

        function showPage(index) {
            pages.forEach(p => p.classList.remove('active'));
            pageNavBtns.forEach(btn => btn.classList.remove('active'));
            
            if (pages[index]) {
                pages[index].classList.add('active');
                pageNavBtns[index].classList.add('active');
            }
        }

        accountToggle.addEventListener('click', () => {
            accountDropdown.classList.toggle('active');
        });

        document.addEventListener('click', (e) => {
            if (!accountToggle.contains(e.target) && !accountDropdown.contains(e.target)) {
                accountDropdown.classList.remove('active');
            }
        });

        // Trip Map Functionality
        let maasinLocations = {}; // Store location lookup for quick matching
        let animatedMarkers = {}; // Track animated markers per map instance
        let animationIntervals = {}; // Track animation intervals per map instance
        let routingControls = {}; // Track routing controls per map instance

        // Create animated arrow marker
        function createAnimatedArrow() {
            const arrowDiv = document.createElement('div');
            arrowDiv.className = 'animated-arrow';
            
            return L.divIcon({
                html: arrowDiv.outerHTML,
                iconSize: [24, 24],
                iconAnchor: [12, 24],
                className: 'animated-marker'
            });
        }

        // Animate arrow along route
        function animateArrowAlongRoute(tripId, coordinates, duration) {
            // Clear previous animation
            if (animationIntervals[tripId]) clearInterval(animationIntervals[tripId]);
            if (animatedMarkers[tripId]) mapInstances[tripId].removeLayer(animatedMarkers[tripId]);

            let currentIndex = 0;
            const totalPoints = coordinates.length;
            const animationStep = duration / totalPoints;

            // Create animated marker at start
            animatedMarkers[tripId] = L.marker(coordinates[0], {
                icon: createAnimatedArrow(),
                zIndexOffset: 1000
            }).addTo(mapInstances[tripId]);

            animationIntervals[tripId] = setInterval(() => {
                currentIndex++;
                
                if (currentIndex >= totalPoints) {
                    clearInterval(animationIntervals[tripId]);
                    if (animatedMarkers[tripId]) mapInstances[tripId].removeLayer(animatedMarkers[tripId]);
                    return;
                }

                // Update marker position
                const newPos = [coordinates[currentIndex].lat, coordinates[currentIndex].lng];
                animatedMarkers[tripId].setLatLng(newPos);

                // Rotate arrow based on direction
                const prevPos = coordinates[currentIndex - 1];
                const angle = Math.atan2(
                    newPos[0] - prevPos[0],
                    newPos[1] - prevPos[1]
                ) * (180 / Math.PI);

                const arrowElement = document.querySelector('#activeTripsMap-' + tripId + ' .animated-marker');
                if (arrowElement) {
                    arrowElement.style.transform = `rotate(${angle}deg)`;
                }
            }, animationStep * 1000);
        }

        // Load locations from Maps.php database
        async function loadLocationsFromDatabase() {
            try {
                const response = await fetch('Maps.php?api=get_all', { method: 'POST' });
                const data = await response.json();
                if (data.success && data.locations) {
                    data.locations.forEach(loc => {
                        maasinLocations[loc.name] = [loc.lat, loc.lng];
                    });
                }
            } catch (error) {
                console.warn('Error loading locations from Maps.php, using fallback:', error);
                // Fallback predefined locations
                maasinLocations = {
                    "Maasin City Fire Station": [10.132752, 124.834795],
                    "Maasin City Park": [10.132377, 124.838700],
                    "Maasin Cathedral": [10.132666, 124.837963],
                    "Maasin Gaisano Grand Mall": [10.133893, 124.84156],
                    "Port of Maasin": [10.131433, 124.841333],
                    "Maasin City Terminal": [10.131666, 124.834722],
                    "Maasin City Gym": [10.132172, 124.835468],
                    "Saint Joseph College": [10.132166, 124.837463],
                    "Maasin SSS (Social Security System)": [10.133353, 124.845656],
                };
            }
        }

        // Improved location matching - prioritizes Maps.php database
        async function geocodeAddress(name) {
            if (!name || !name.trim()) return null;
            
            const searchName = name.trim().toLowerCase();
            
            // EXACT MATCH - Check Maps.php locations first
            for (const locName in maasinLocations) {
                if (locName.toLowerCase() === searchName) {
                    const [lat, lon] = maasinLocations[locName];
                    return { lat: lat, lon: lon, source: 'Maps.php' };
                }
            }
            
            // PARTIAL MATCH - Check if search text is contained in location name
            for (const locName in maasinLocations) {
                if (locName.toLowerCase().includes(searchName) || searchName.includes(locName.toLowerCase())) {
                    const [lat, lon] = maasinLocations[locName];
                    return { lat: lat, lon: lon, source: 'Maps.php (partial match)', matchedName: locName };
                }
            }

            // If not found in Maps.php, try Nominatim as fallback
            let q = name;
            if (q.indexOf('Maasin') === -1 && q.indexOf('City') === -1) {
                q += ' Maasin City';
            }

            const url = `https://nominatim.openstreetmap.org/search?format=json&limit=1&q=${encodeURIComponent(q)}&countrycodes=ph&viewbox=124.83,10.12,124.85,10.15&bounded=1`;
            try {
                const res = await fetch(url, { headers: { 'User-Agent': 'BFP-Trip-App' }});
                if (!res.ok) return null;
                const data = await res.json();
                if (!data || !data.length) return null;
                return { lat: parseFloat(data[0].lat), lon: parseFloat(data[0].lon), source: 'Nominatim (external)' };
            } catch (err) {
                console.error('Geocode error:', err);
                return null;
            }
        }

        // Load locations on page load
        loadLocationsFromDatabase();

        function initializeTripMap(tripId, destination) {
            const mapElement = document.getElementById('activeTripsMap-' + tripId);
            if (!mapElement || mapInstances[tripId]) return;

            // Initialize map centered on office
            const map = L.map('activeTripsMap-' + tripId).setView([OFFICE_LAT, OFFICE_LNG], 15);
            
            // Add map tiles
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 19
            }).addTo(map);

            // Add office marker
            L.marker([OFFICE_LAT, OFFICE_LNG], {
                title: 'BFP Station (Start)',
                icon: L.icon({
                    iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-blue.png',
                    shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
                    iconSize: [25, 41],
                    iconAnchor: [12, 41],
                    popupAnchor: [1, -34],
                    shadowSize: [41, 41]
                })
            }).bindPopup('<b>BFP Station</b><br>Trip Start Point').addTo(map);

            // Try to geocode destination and add marker
            if (destination && destination !== 'N/A') {
                geocodeAddress(destination).then(geocoded => {
                    if (geocoded) {
                        const destLat = geocoded.lat;
                        const destLng = geocoded.lon;
                        
                        L.marker([destLat, destLng], {
                            title: 'Trip Destination',
                            icon: L.icon({
                                iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
                                shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
                                iconSize: [25, 41],
                                iconAnchor: [12, 41],
                                popupAnchor: [1, -34],
                                shadowSize: [41, 41]
                            })
                        }).bindPopup('<b>Destination</b><br>' + destination).addTo(map);

                        // Draw route with enhanced styling
                        if (window.L.Routing) {
                            try {
                                // Remove previous routing control if exists
                                if (routingControls[tripId]) {
                                    try {
                                        map.removeControl(routingControls[tripId]);
                                    } catch(e) {
                                        console.warn('Error removing previous routing control', e);
                                    }
                                }

                                routingControls[tripId] = L.Routing.control({
                                    waypoints: [
                                        L.latLng(OFFICE_LAT, OFFICE_LNG),
                                        L.latLng(destLat, destLng)
                                    ],
                                    router: L.Routing.osrmv1({ 
                                        serviceUrl: 'https://router.project-osrm.org/route/v1',
                                        profile: 'car' 
                                    }),
                                    lineOptions: { 
                                        styles: [{ 
                                            color: '#B22222', 
                                            weight: 5, 
                                            opacity: 0.8
                                        }] 
                                    },
                                    show: false,
                                    addWaypoints: false,
                                    draggableWaypoints: false,
                                    createMarker: function(i, wp) {
                                        if (i === 0) {
                                            return L.marker(wp.latLng, {
                                                icon: L.icon({
                                                    iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
                                                    iconSize: [25, 41],
                                                    iconAnchor: [12, 41]
                                                })
                                            }).bindPopup('<strong>🚒 Fire Station (Start)</strong><br>BFP Station');
                                        }
                                        
                                        return L.marker(wp.latLng, {
                                            icon: L.icon({
                                                iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-green.png', 
                                                iconSize: [25, 41],
                                                iconAnchor: [12, 41]
                                            })
                                        }).bindPopup('<strong>📍 Destination (End)</strong><br>' + destination);
                                    }
                                }).addTo(map);

                                // Animate route when ready
                                routingControls[tripId].on('routesfound', function(e) {
                                    const route = e.routes[0];
                                    if (route && route.coordinates && route.coordinates.length > 0) {
                                        animateArrowAlongRoute(tripId, route.coordinates, 10000);
                                    }
                                });
                            } catch(e) {
                                console.log('Routing unavailable:', e);
                            }
                        }

                        // Fit bounds
                        const group = new L.featureGroup([
                            L.marker([OFFICE_LAT, OFFICE_LNG]),
                            L.marker([destLat, destLng])
                        ]);
                        map.fitBounds(group.getBounds().pad(0.1));
                    }
                }).catch(e => console.log('Geocoding error:', e));
            }

            mapInstances[tripId] = map;

            // Trigger resize after a delay to ensure proper rendering
            setTimeout(() => {
                map.invalidateSize();
            }, 100);
        }

        // Handle trip row clicks
        document.querySelectorAll('.trip-row').forEach(row => {
            row.addEventListener('click', function(e) {
                if (e.target.closest('.status-badge')) return; // Don't toggle if clicking badge

                const tripId = this.getAttribute('data-trip-id');
                const destination = this.getAttribute('data-destination');
                const mapDropdown = document.getElementById('map-' + tripId);
                const dropdownRow = this.nextElementSibling;

                // Close all other dropdowns
                document.querySelectorAll('.map-dropdown.show').forEach(d => {
                    d.classList.remove('show');
                });
                document.querySelectorAll('.trip-row.trip-map-row').forEach(r => {
                    r.classList.remove('trip-map-row');
                });

                // Toggle current dropdown
                if (mapDropdown && !mapDropdown.classList.contains('show')) {
                    mapDropdown.classList.add('show');
                    this.classList.add('trip-map-row');
                    dropdownRow.style.display = 'table-row';

                    // Initialize map when dropdown is shown
                    setTimeout(() => {
                        initializeTripMap(tripId, destination);
                    }, 100);
                } else if (mapDropdown) {
                    mapDropdown.classList.remove('show');
                    this.classList.remove('trip-map-row');
                    if (dropdownRow) dropdownRow.style.display = 'none';
                }

                // Hide dropdown row by default on page load
                if (dropdownRow && !mapDropdown.classList.contains('show')) {
                    dropdownRow.style.display = 'none';
                }
            });
        });

        // Hide all dropdown rows on initial page load
        document.querySelectorAll('.map-dropdown-row').forEach(row => {
            row.style.display = 'none';
        });
    </script>


</body>
</html>