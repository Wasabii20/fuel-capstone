<?php
session_start();
// NOTE: Assuming db_connect.php contains the database connection logic:
// $conn = new mysqli($servername, $username, $password, $dbname);
include("db_connect.php"); 

// Check login
if (!isset($_SESSION['username'])) {
    // header("Location: login.php");
    // exit();
    // For testing purposes without a login page, we skip the redirect
}

// --- 1. Fetch Active Trip Tickets (status = 'Active') ---
// Deployment is determined by an entry in the trip_tickets table with status = 'Active'
$active_trips = [];
$sql_trips = "SELECT vehicle_plate_no, places_to_visit, purpose, driver_id FROM trip_tickets WHERE status = 'Active' ORDER BY created_at DESC";
$result_trips = $pdo->query($sql_trips);

if ($result_trips) {
    while ($trip = $result_trips->fetch()) {
        // Store the deployment details keyed by vehicle number (uppercase for safe matching)
        $vehicle_location = $trip['places_to_visit'];
        $vehicle_purpose = $trip['purpose'];

        // Store complete trip info including purpose and location
        $active_trips[strtoupper($trip['vehicle_plate_no'])] = [
            'use' => "{$vehicle_purpose} to {$vehicle_location}",
            'destination' => $vehicle_location,
            'purpose' => $vehicle_purpose,
            'driver_id' => $trip['driver_id']
        ];
    }
}
// --------------------------------------------------

// Fetch all vehicles
$sql = "SELECT v.id, v.vehicle_no, v.vehicle_type, v.status,
                 v.current_fuel AS fuel_level, v.description
        FROM vehicles v
        ORDER BY v.id ASC";
$result = $pdo->query($sql);
$vehicles = [];
$totalFuel = 0;
$countWithFuel = 0;

$inUseCount = 0; 
$availableCount = 0; 
$inactiveCount = 0;
$inRepairCount = 0; 

// --- CRITICAL FUEL THRESHOLD (used for warning display) ---
const CRITICAL_FUEL_PERCENT = 25;
// --- INACTIVE FUEL THRESHOLD ---
const INACTIVE_FUEL_LEVEL = 0; // If current_fuel is 0, the vehicle is considered inactive.


while ($row = $result->fetch()) {
    $vehicle_no = strtoupper($row['vehicle_no']);
    $fuel_level = $row['fuel_level'] ?? 0;
    
    // --- 2. Determine Deployment Status using Trip Tickets ---
    $is_deployed = isset($active_trips[$vehicle_no]);
    $row['current_use'] = $is_deployed ? 
        $active_trips[$vehicle_no]['use'] : 'N/A';

    // --- 3. Determine the Final Operational Status based on Fuel and Deployment ---
    
    // Default to the status from the database, but override based on business logic:
    $final_status = strtolower($row['status']);

    if ($final_status === 'in_repair') {
        // Rule 1 (Highest Priority): Vehicle is in repair - keep it as is
        $final_status = 'in_repair';
    } elseif ($is_deployed) {
        // Rule 2: Deployed via a trip ticket.
        $final_status = 'deployed';
    } elseif ($fuel_level <= INACTIVE_FUEL_LEVEL) {
        // Rule 3: If fuel is 0, the vehicle is inactive.
        $final_status = 'inactive';
    } else {
        // Rule 4: Otherwise, the vehicle is available/active.
        $final_status = 'available';
    }
    
    // Assign the derived status back to the row for display/counting
    $row['status'] = $final_status; 

    // --- 4. Counting logic (using the DERIVED status) ---
    if ($final_status === 'in_repair') {
        $inRepairCount++;
    } elseif ($final_status === 'deployed') {
        $inUseCount++;
    } elseif ($final_status === 'available') {
        $availableCount++;
    } else { // 'inactive'
        $inactiveCount++;
    }
    
    $vehicles[] = $row;
    
    // Total fuel calculation
    if ($row['fuel_level'] !== null) {
        $totalFuel += $row['fuel_level']; 
        $countWithFuel++;
    }
}
// PDO connection auto-closes, no need to call close()

// Helper functions 
function getVehicleIcon($type) {
    $icons = [
        'Fire Truck' => '🔥',
        'Rescue Truck' => '🚑',
        'Ambulance' => '🚨',
        'Patrol Vehicle' => '🚓',
        'Water Tanker' => '💧',
    ];
    return $icons[$type] ?? '🚗';
}
function getFuelColor($fuel) {
    if ($fuel === null) return '#6c757d'; 
    if ($fuel >= 75) return '#28a745'; 
    if ($fuel >= 50) return '#ffc107'; 
    if ($fuel >= 25) return '#fd7e14'; 
    return '#dc3545'; 
}

// Compute average fuel
$avgFuel = $countWithFuel > 0 ? round($totalFuel / $countWithFuel, 1) : null;
$avgFuelColor = getFuelColor($avgFuel);

// Leaflet Map Configuration
$fireStationLat = 10.1327; // Maasin City Fire Station
$fireStationLng = 124.8348;
$mapZoom = 17; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, maximum-scale=5.0, user-scalable=yes">
    <meta name="theme-color" content="#5d5dff">
    <meta name="description" content="BFP Vehicle Dashboard - Fuel Monitoring & GPS Tracking">
    <title>BFP - Trip Ticket System (Vehicle Dashboard)</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <style>
        :root {
    --bfp-red: #5d5dff; --sidebar-bg: #1e1e2d; --active-gradient: linear-gradient(90deg, rgba(93, 93, 255, 0.15) 0%, rgba(93, 93, 255, 0) 100%);
    --dark-blue: #1e1e2d; --submenu-bg: #161628; --paper-shadow: rgba(0, 0, 0, 0.5);
    --primary-color: #5d5dff; --primary-dark: #4d4ddd; --primary-light: #7d7dff;
    --bg-light: #2a2a3e; --bg-white: #1e1e2d; --text-dark: #e2e2e2; --text-gray: #a2a2c2;
    --border-light: rgba(255, 255, 255, 0.1); --success: #27ae60; --warning: #f39c12; --danger: #e74c3c;
    --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.3); --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.2); --shadow-lg: 0 8px 24px rgba(0, 0, 0, 0.3);
    --radius-sm: 8px; --radius-md: 12px; --radius-lg: 16px; --transition: all 0.3s cubic-bezier(0.25, 1, 0.5, 1);
}

/* ===== GLOBAL STYLES ===== */
* { box-sizing: border-box; margin: 0; padding: 0; }

body { font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: var(--dark-blue); color: var(--text-dark); min-height: 100vh; display: flex; flex-direction: column; line-height: 1.6; }

.wrapper { display: flex; flex: 1; overflow: hidden; }

main { flex: 1; overflow: auto; padding: 20px; background: var(--dark-blue); }

/* ===== MAIN CONTENT ===== */
.form-container { flex: 1; max-width: 1400px; margin: 32px auto; width: 100%; padding: 0 32px; display: flex; flex-direction: column; gap: 24px; overflow: hidden; }

.header-title { text-align: center; margin-bottom: 12px; }

.header-title p { font-size: 2.2rem; font-weight: 700; color: #5d5dff; letter-spacing: -0.5px; }

/* ===== MAIN LAYOUT CONTAINER ===== */
.main-layout { display: flex; gap: 24px; flex: 1; min-height: 0; }

/* ===== MAP CONTAINER (LEFT SIDE - 65%) ===== */
.map-container { 
    flex: 0 0 65%; 
    display: flex; 
    flex-direction: column; 
    gap: 16px; 
    overflow: hidden;
    position: sticky;
    top: 20px;
    height: fit-content;
    max-height: calc(100vh - 40px);
}

/* ===== MAP STYLES ===== */
#map { height: 100%; width: 100%; border-radius: var(--radius-lg); border: 1px solid rgba(93, 93, 255, 0.2); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3); background: #2a2a3e; flex: 1; min-height: 700px; }

/* ===== VEHICLE PANEL (RIGHT SIDE - 35%) ===== */
.vehicle-panel { flex: 0 0 35%; display: flex; flex-direction: column; gap: 16px; overflow: hidden; background: #2a2a3e; border: 1px solid rgba(93, 93, 255, 0.2); border-radius: var(--radius-lg); padding: 20px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3); }

.vehicle-panel-title { font-size: 1.3rem; font-weight: 700; color: #5d5dff; margin: 0 0 12px 0; border-bottom: 2px solid rgba(93, 93, 255, 0.3); padding-bottom: 8px; }

/* ===== SUMMARY SECTION ===== */
.summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; background: transparent; border-radius: var(--radius-lg); padding: 24px; }

.summary-item { text-align: center; padding: 16px; border-radius: var(--radius-md); background: rgba(93, 93, 255, 0.08); border: 1px solid rgba(93, 93, 255, 0.2); transition: all 0.3s cubic-bezier(0.25, 1, 0.5, 1); }

.summary-item:hover { background: rgba(93, 93, 255, 0.12); border-color: rgba(93, 93, 255, 0.4); }

.summary-item h3 { margin: 0 0 8px 0; font-size: 0.9rem; font-weight: 600; color: #a2a2c2; text-transform: uppercase; letter-spacing: 0.5px; }

.summary-item p { margin: 0; font-weight: 700; font-size: 1.6rem; color: #5d5dff; }

/* ===== VEHICLE CARDS ===== */
.dashboard { display: flex; flex-direction: column; gap: 12px; overflow-y: auto; flex: 1; min-height: 0; }

.dashboard::-webkit-scrollbar { width: 8px; }

.dashboard::-webkit-scrollbar-track { background: rgba(93, 93, 255, 0.1); border-radius: 4px; }

.dashboard::-webkit-scrollbar-thumb { background: #5d5dff; border-radius: 4px; }

.dashboard::-webkit-scrollbar-thumb:hover { background: #7d7dff; }

/* ===== TOUCH-FRIENDLY IMPROVEMENTS ===== */
button, input[type="submit"], input[type="button"], .location-btn {
    min-height: 44px;
    min-width: 44px;
    -webkit-user-select: none;
    user-select: none;
    -webkit-tap-highlight-color: rgba(93, 93, 255, 0.2);
}

a { -webkit-tap-highlight-color: transparent; }

/* Improved focus for mobile */
button:focus, input:focus, select:focus, textarea:focus {
    outline: 2px solid rgba(93, 93, 255, 0.6);
    outline-offset: 2px;
}

@supports (padding: max(0px)) {
    main { padding: max(10px, env(safe-area-inset-bottom)); }
}

.vehicle-card { background: #2a2a3e; border: 1px solid rgba(93, 93, 255, 0.2); border-radius: var(--radius-md); padding: 14px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3); text-align: left; transition: all 0.3s cubic-bezier(0.25, 1, 0.5, 1); cursor: pointer; position: relative; display: grid; grid-template-columns: auto 1fr auto auto; gap: 12px; align-items: center; flex-shrink: 0; word-break: break-word; touch-action: manipulation; }

.vehicle-card:hover { transform: translateX(4px); box-shadow: 0 4px 16px rgba(93, 93, 255, 0.2); border-color: rgba(93, 93, 255, 0.5); background: #32324a; }

.vehicle-card[data-status="deployed"] { border-color: rgba(76, 175, 80, 0.4); background: rgba(76, 175, 80, 0.05); }

.vehicle-card[data-status="deployed"]:hover { box-shadow: 0 4px 16px rgba(76, 175, 80, 0.2); border-color: rgba(76, 175, 80, 0.6); background: rgba(76, 175, 80, 0.1); }

.vehicle-card[data-status="inactive"] { opacity: 0.7; }

.vehicle-card[data-status="inactive"]:hover { box-shadow: 0 4px 16px rgba(231, 76, 60, 0.2); }

.vehicle-icon { font-size: 2rem; color: #5d5dff; min-width: 40px; text-align: center; }

.vehicle-card-info { display: flex; flex-direction: column; gap: 2px; flex: 1; }

.vehicle-no { font-weight: 700; font-size: 1rem; color: #e2e2e2; margin: 0; letter-spacing: 0.3px; }

.vehicle-type { color: #a2a2c2; margin: 0; font-size: 0.8rem; }

.vehicle-status { font-weight: 600; margin: 0; padding: 3px 6px; border-radius: var(--radius-sm); font-size: 0.75rem; display: inline-block; }

/* ===== LOW FUEL WARNING ===== */
.vehicle-status.deployed { background-color: rgba(76, 175, 80, 0.2); color: #4caf50; border: 1px solid rgba(76, 175, 80, 0.4); }

.vehicle-status.available { background-color: rgba(52, 152, 219, 0.2); color: #3498db; border: 1px solid rgba(52, 152, 219, 0.4); }

.vehicle-status.in_repair { background-color: rgba(255, 152, 0, 0.2); color: #ff9800; border: 1px solid rgba(255, 152, 0, 0.4); }

.vehicle-status.inactive { background-color: rgba(231, 76, 60, 0.2); color: #e74c3c; border: 1px solid rgba(231, 76, 60, 0.4); }

.vehicle-card[data-status="in_repair"] { border-color: rgba(255, 152, 0, 0.4); background: rgba(255, 152, 0, 0.05); }

.vehicle-card[data-status="in_repair"]:hover { box-shadow: 0 4px 16px rgba(255, 152, 0, 0.2); border-color: rgba(255, 152, 0, 0.6); background: rgba(255, 152, 0, 0.1); }

.fuel-bar-container { background: var(--border-light); border-radius: var(--radius-sm); height: 20px; width: 100%; overflow: hidden; border: 1px solid #ddd; min-width: 80px; display: flex; align-items: center; }

.fuel-bar { height: 100%; text-align: center; color: white; font-weight: 600; font-size: 0.75rem; line-height: 20px; transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1); display: flex; align-items: center; justify-content: center; white-space: nowrap; padding: 0 4px; }

/* ===== LOW FUEL WARNING ===== */
.low-fuel-warning { position: absolute; top: 12px; right: 12px; background: linear-gradient(135deg, var(--warning) 0%, #e67e22 100%); color: white; padding: 8px 12px; border-radius: var(--radius-sm); font-weight: 700; font-size: 0.8rem; box-shadow: var(--shadow-md); display: flex; align-items: center; gap: 6px; animation: pulse 2s infinite; }

.low-fuel-warning i { color: white; }

@keyframes pulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.05); } }

/* ===== CURRENT USE DISPLAY ===== */
.current-use-display { text-align: left; background: linear-gradient(135deg, rgba(178, 34, 34, 0.05) 0%, rgba(255, 69, 0, 0.05) 100%); border: 1px solid rgba(178, 34, 34, 0.2); border-radius: var(--radius-sm); padding: 8px; margin: 0; font-size: 0.8rem; grid-column: 1 / -1; }

.current-use-display strong { color: var(--primary-color); display: block; margin-bottom: 2px; font-weight: 700; font-size: 0.75rem; }

/* ===== MODAL STYLES ===== */
.modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.6); animation: fadeIn 0.3s ease; }

@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

.modal-content { background: #1e1e2d; border: 1px solid rgba(255, 255, 255, 0.1); margin: 5% auto; padding: 32px; border-radius: 16px; width: 90%; max-width: 500px; position: relative; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3); animation: slideUp 0.3s ease; }

@keyframes slideUp { from { transform: translateY(30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

.close { position: absolute; top: 16px; right: 20px; font-size: 28px; color: #a2a2c2; cursor: pointer; transition: all 0.3s; background: none; border: none; }

.close:hover { color: #5d5dff; transform: scale(1.15); }

.vehicle-modal-info { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 20px; }

.vehicle-modal-info div { background: var(--bg-light); border-radius: var(--radius-md); padding: 14px; border-left: 4px solid var(--primary-color); }

.vehicle-modal-info strong { display: block; font-size: 0.85rem; color: var(--text-gray); margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px; }

.vehicle-modal-info span { display: block; font-size: 1.1rem; font-weight: 700; color: var(--text-dark); }

/* ===== FORM ELEMENTS IN MODALS ===== */
.form-group { margin-bottom: 16px; }

.form-group label { display: block; margin-bottom: 6px; font-weight: 600; color: #e2e2e2; font-size: 0.95rem; }

.form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px 12px; border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; font-family: inherit; font-size: 0.95rem; color: #e2e2e2; background: rgba(255, 255, 255, 0.05); transition: all 0.3s; min-height: 44px; }

.form-group input::placeholder, .form-group textarea::placeholder { color: #7e8299; }

.form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: rgba(93, 93, 255, 0.6); background: rgba(93, 93, 255, 0.08); box-shadow: 0 0 0 3px rgba(93, 93, 255, 0.1); }

.form-group textarea { resize: vertical; min-height: 80px; }

.modal-actions { display: flex; gap: 10px; margin-top: 24px; justify-content: flex-end; }

.btn-submit, .btn-cancel { padding: 11px 24px; border-radius: 8px; border: none; font-weight: 600; cursor: pointer; transition: all 0.3s; font-size: 0.95rem; min-height: 44px; min-width: 44px; display: inline-flex; align-items: center; justify-content: center; }

.btn-submit { background: #5d5dff; color: white; }

.btn-submit:hover { background: #7d7dff; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(93, 93, 255, 0.3); }

.btn-cancel { background: rgba(255, 255, 255, 0.05); color: #a2a2c2; border: 1px solid rgba(255, 255, 255, 0.1); }

.btn-cancel:hover { background: rgba(255, 255, 255, 0.1); color: #e2e2e2; }

.filter-section { background: rgba(93, 93, 255, 0.06); border: 1px solid rgba(93, 93, 255, 0.15); padding: 16px; border-radius: 8px; margin-bottom: 16px; }

.filter-section .form-group { margin-bottom: 12px; }

.filter-section .form-group:last-child { margin-bottom: 0; }

/* ===== EXPENSES & LOGS TABLE ===== */
.expenses-table, .logs-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; background: transparent; }

.expenses-table th, .logs-table th { background: rgba(93, 93, 255, 0.1); padding: 14px; text-align: left; font-weight: 600; border-bottom: 1px solid rgba(93, 93, 255, 0.2); color: #a2a2c2; text-transform: uppercase; letter-spacing: 0.5px; font-size: 0.85rem; }

.expenses-table td, .logs-table td { padding: 12px 14px; border-bottom: 1px solid rgba(255, 255, 255, 0.05); color: #e2e2e2; }

.expenses-table tr:hover, .logs-table tr:hover { background: rgba(93, 93, 255, 0.08); }

.expense-type-badge { display: inline-block; padding: 6px 12px; border-radius: 6px; font-size: 0.85rem; font-weight: 600; background: rgba(93, 93, 255, 0.2); color: #5d5dff; border: 1px solid rgba(93, 93, 255, 0.4); }

.status-good { color: var(--success); }
.status-warning { color: var(--warning); }
.status-danger { color: var(--danger); }

/* ===== FUEL GAUGE ===== */
.fuel-gauge { position: relative; width: 200px; height: 200px; margin: 24px auto; border-radius: 50%; background: radial-gradient(circle at center, #1a1a1a 60%, #0d0d0d 100%); box-shadow: inset 0 2px 8px rgba(0, 0, 0, 0.8), var(--shadow-lg); }

.gauge-arc { position: absolute; inset: 10px; border-radius: 50%; border: 10px solid transparent; border-top-color: #e74c3c; border-right-color: #f39c12; border-bottom-color: #27ae60; transform: rotate(270deg); }

.needle { position: absolute; bottom: 50%; left: 50%; width: 4px; height: 70px; background: linear-gradient(to top, #e74c3c, #c0392b); transform-origin: bottom center; transform: rotate(-135deg); border-radius: 2px; transition: transform 1s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 0 2px 4px rgba(0, 0, 0, 0.4); }

.center-cap { position: absolute; bottom: 50%; left: 50%; width: 22px; height: 22px; background: linear-gradient(135deg, #444 0%, #222 100%); border: 3px solid #555; border-radius: 50%; transform: translate(-50%, 50%); box-shadow: 0 2px 6px rgba(0, 0, 0, 0.6); }

.label { position: absolute; color: #fff; font-family: 'Poppins', sans-serif; font-weight: 600; font-size: 14px; }

.label.e { left: 20px; bottom: 40px; }
.label.f { right: 20px; bottom: 40px; }

.value { position: absolute; bottom: 12px; width: 100%; text-align: center; color: white; font-family: 'Courier New', monospace; font-size: 18px; font-weight: 700; }

/* ===== NAVIGATION BUTTONS ===== */
.nav-buttons {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin: 32px auto;
    width: fit-content;
    max-width: 100%;
    padding: 0 20px;
}

.nav-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    padding: 14px 28px;
    background: linear-gradient(135deg, #5d5dff 0%, #7d7dff 100%);
    color: white;
    text-decoration: none;
    border-radius: 12px;
    font-weight: 600;
    font-size: 1rem;
    box-shadow: 0 4px 15px rgba(93, 93, 255, 0.3);
    border: 1px solid rgba(93, 93, 255, 0.5);
    transition: all 0.3s cubic-bezier(0.25, 1, 0.5, 1);
    cursor: pointer;
    min-height: 48px;
    text-align: center;
    white-space: nowrap;
}

.nav-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(93, 93, 255, 0.5);
    background: linear-gradient(135deg, #7d7dff 0%, #9d9dff 100%);
    border-color: rgba(93, 93, 255, 0.8);
}

.nav-btn:active {
    transform: translateY(0);
    box-shadow: 0 2px 8px rgba(93, 93, 255, 0.3);
}

.nav-btn i {
    font-size: 1.2rem;
    flex-shrink: 0;
}

/* ===== PAGE NAVIGATION TABS ===== */
.page-tabs {
    display: flex;
    gap: 8px;
    border-bottom: 2px solid rgba(93, 93, 255, 0.2);
    margin-bottom: 24px;
    padding: 12px 16px 8px 16px;
    overflow-x: auto;
    scroll-behavior: smooth;
    background: rgba(30, 30, 45, 0.5);
    border-radius: 12px 12px 0 0;
    scrollbar-width: thin;
}

.page-tabs::-webkit-scrollbar {
    height: 4px;
}

.page-tabs::-webkit-scrollbar-track {
    background: rgba(93, 93, 255, 0.1);
    border-radius: 10px;
}

.page-tabs::-webkit-scrollbar-thumb {
    background: rgba(93, 93, 255, 0.4);
    border-radius: 10px;
}

.page-tabs::-webkit-scrollbar-thumb:hover {
    background: rgba(93, 93, 255, 0.7);
}

.page-tab {
    padding: 10px 20px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(93, 93, 255, 0.2);
    color: var(--text-secondary);
    font-weight: 600;
    cursor: pointer;
    font-size: 1rem;
    border-bottom: 3px solid transparent;
    transition: all 0.3s cubic-bezier(0.25, 1, 0.5, 1);
    white-space: nowrap;
    border-radius: 8px 8px 0 0;
    display: flex;
    align-items: center;
    gap: 6px;
}

.page-tab:hover {
    color: var(--active-blue);
    background: rgba(93, 93, 255, 0.15);
    border-color: rgba(93, 93, 255, 0.4);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(93, 93, 255, 0.2);
}

.page-tab:active {
    transform: translateY(0);
}

.page-tab.active {
    color: var(--active-blue);
    border-bottom-color: var(--active-blue);
    background: rgba(93, 93, 255, 0.25);
    border-color: var(--active-blue);
    box-shadow: 0 4px 12px rgba(93, 93, 255, 0.3), inset 0 -3px 0 var(--active-blue);
}

.page-tab:focus {
    outline: 2px solid var(--active-blue);
    outline-offset: -2px;
}

.page-tab:focus:not(:focus-visible) {
    outline: none;
}

.page-content {
    display: none;
}

.page-content.active {
    display: block;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { 
        opacity: 0; 
        transform: translateY(2px);
    }
    to { 
        opacity: 1; 
        transform: translateY(0);
    }
}

.iframe-container {
    width: 100%;
    height: calc(100vh - 400px);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    background: var(--card-bg);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    touch-action: manipulation;
}

.iframe-container iframe {
    width: 100%;
    height: 100%;
    border: none;
    border-radius: 12px;
    display: block;
    transition: opacity 0.3s ease;
}

.iframe-container iframe:hover {
    opacity: 0.95;
}

/* ===== RESPONSIVE DESIGN ===== */
@media (max-width: 1200px) {
    .main-layout { flex-direction: column; gap: 16px; }
    .map-container { 
        flex: 0 0 auto; 
        height: 400px;
        position: sticky;
        top: 20px;
        max-height: calc(100vh - 40px);
    }
    .vehicle-panel { flex: 0 0 300px; }
    #map { min-height: 400px; }
}

@media (max-width: 768px) {
    .form-container { margin: 12px; padding: 0 8px; gap: 16px; }
    .header-title p { font-size: 1.4rem; }
    .summary { grid-template-columns: repeat(2, 1fr); gap: 12px; padding: 16px; }
    .summary-item { padding: 12px; }
    .summary-item h3 { font-size: 0.8rem; }
    .summary-item p { font-size: 1.3rem; }
    .main-layout { flex-direction: column; gap: 12px; }
    .map-container { 
        flex: 0 0 300px;
        position: sticky;
        top: 10px;
        max-height: calc(100vh - 20px);
    }
    
    .vehicle-panel {
        flex: 0 0 auto;
        max-height: 400px;
    }
    
    .vehicle-card {
        grid-template-columns: auto 1fr auto;
        gap: 8px;
        padding: 10px;
    }
    
    .vehicle-icon { font-size: 1.5rem; min-width: 35px; }
    .vehicle-no { font-size: 0.95rem; }
    .vehicle-type { font-size: 0.75rem; }
    .vehicle-status { font-size: 0.7rem; padding: 2px 4px; }
    .fuel-bar-container { min-width: 60px; height: 18px; }
    
    .location-btn { padding: 6px 10px; font-size: 0.85rem; }
    
    .modal-content {
        width: 95%;
        margin: 25% auto;
        padding: 20px;
    }
    
    .vehicle-modal-info { grid-template-columns: 1fr; gap: 10px; }
}

@media (max-width: 600px) {
    .form-container { margin: 10px; padding: 0; }
    .header-title { margin-bottom: 8px; }
    .header-title p { font-size: 1.2rem; }
    .summary { grid-template-columns: 1fr; gap: 10px; padding: 12px; }
    .summary-item { padding: 10px; }
    .summary-item h3 { font-size: 0.75rem; }
    .summary-item p { font-size: 1.1rem; }
    
    .map-container {
        position: sticky;
        top: 0;
        z-index: 10;
        flex: 0 0 250px;
        max-height: none;
        margin-bottom: 12px;
    }
    
    .nav-buttons {
        grid-template-columns: 1fr;
        gap: 12px;
        margin: 20px auto;
        padding: 0 10px;
        max-width: 100%;
        width: 100%;
    }
    
    .nav-btn {
        padding: 12px 20px;
        font-size: 0.95rem;
        width: 100%;
    }
    
    .main-layout { 
        flex-direction: column; 
        gap: 12px;
        overflow: visible;
    }
    #map { min-height: 250px; }
    
    .vehicle-panel {
        flex: 0 0 auto;
        padding: 12px;
        max-height: none;
        height: auto;
        margin-top: 12px;
    }
    
    .vehicle-panel-title { font-size: 1.1rem; margin-bottom: 8px; }
    
    .vehicle-card {
        grid-template-columns: auto 1fr;
        gap: 6px;
        padding: 8px;
    }
    
    .vehicle-icon { font-size: 1.3rem; }
    .vehicle-no { font-size: 0.9rem; }
    .vehicle-type { font-size: 0.7rem; }
    .vehicle-status { font-size: 0.65rem; padding: 2px 3px; margin-top: 2px; }
    
    .fuel-bar-container { 
        min-width: 50px; 
        height: 16px;
        display: none;
    }
    
    .location-btn { 
        padding: 5px 8px; 
        font-size: 0.75rem;
        height: fit-content;
    }
    
    .current-use-display { 
        font-size: 0.75rem; 
        padding: 6px;
        margin-top: 4px;
    }
    
    .current-use-display strong { font-size: 0.7rem; margin-bottom: 2px; }
    
    .modal-content {
        width: 98%;
        margin: 30% auto;
        padding: 16px;
    }
    
    .vehicle-modal-info { grid-template-columns: 1fr; gap: 8px; }
    .vehicle-modal-info div { padding: 10px; }
    .vehicle-modal-info strong { font-size: 0.8rem; }
    .vehicle-modal-info span { font-size: 1rem; }
    
    .form-group label { font-size: 0.9rem; margin-bottom: 4px; }
    .form-group input, .form-group select, .form-group textarea { 
        padding: 8px 10px; 
        font-size: 0.9rem;
    }
    
    .form-group textarea { min-height: 60px; }
    
    .modal-actions { gap: 8px; }
    .btn-submit, .btn-cancel { padding: 9px 16px; font-size: 0.85rem; }
    
    .fuel-gauge { width: 150px; height: 150px; margin: 16px auto; }
    .needle { height: 50px; }
    .label { font-size: 12px; }
    .value { font-size: 14px; }
    
    /* Page Navigation Responsive */
    .page-tabs {
        overflow-x: auto;
        gap: 6px;
        padding: 10px 8px 6px 8px;
        margin-bottom: 16px;
        scroll-snap-type: x mandatory;
        -webkit-overflow-scrolling: touch;
    }
    
    .page-tab {
        padding: 10px 16px;
        font-size: 0.9rem;
        min-width: fit-content;
        white-space: nowrap;
        flex-shrink: 0;
        scroll-snap-align: center;
        border-radius: 6px 6px 0 0;
    }
    
    .page-tab:hover {
        transform: translateY(-1px);
    }
    
    .page-content {
        max-height: calc(100vh - 280px);
        overflow-y: auto;
        padding: 12px;
    }
    
    .iframe-container {
        height: calc(100vh - 320px);
        border-radius: 8px;
    }
}

@media (max-width: 400px) {
    main { padding: 10px; }
    .form-container { margin: 8px; padding: 0; gap: 12px; }
    .header-title p { font-size: 1rem; }
    
    .summary { 
        grid-template-columns: repeat(2, 1fr); 
        gap: 6px; 
        padding: 6px;
        margin-bottom: 8px;
    }
    .summary-item { 
        padding: 6px 4px; 
        text-align: center;
    }
    .summary-item:first-child {
        grid-column: 1 / -1;
    }
    .summary-item h3 { 
        font-size: 0.65rem; 
        margin-bottom: 3px;
    }
    .summary-item p { 
        font-size: 0.85rem; 
    }
    
    .map-container { flex: 0 0 200px; }
    #map { min-height: 200px; }
    
    .vehicle-panel {
        padding: 8px;
        border-radius: 8px;
    }
    
    .vehicle-panel-title { font-size: 1rem; }
    
    .vehicle-card {
        padding: 6px;
        gap: 4px;
    }
    
    .vehicle-icon { font-size: 1.1rem; min-width: 30px; }
    .vehicle-card-info { gap: 1px; }
    .vehicle-no { font-size: 0.85rem; }
    .vehicle-type { font-size: 0.65rem; }
    
    .location-btn {
        padding: 4px 6px;
        font-size: 0.7rem;
    }
    
    .modal-content {
        width: 96%;
        margin: 40% auto;
        padding: 12px;
    }
    
    .filter-section { padding: 12px; margin-bottom: 12px; }
    .form-group { margin-bottom: 12px; }
    
    /* Page Navigation Extra Small */
    .page-tabs {
        gap: 4px;
        padding: 8px 4px 4px 4px;
        overflow: hidden;
        overflow-x: auto;
    }
    
    .page-tab {
        padding: 8px 12px;
        font-size: 0.8rem;
        min-width: 90px;
        flex-shrink: 0;
        border-radius: 6px 6px 0 0;
    }
    
    .page-tab:hover {
        transform: none;
    }
    
    .page-tab:active {
        background: rgba(93, 93, 255, 0.25);
    }
    
    .page-content {
        max-height: calc(100vh - 260px);
        padding: 10px;
    }
    
    .iframe-container {
        height: calc(100vh - 300px);
        border-radius: 6px;
    }
}

@media (max-width: 768px) {
    .page-tabs {
        padding: 10px 8px;
        gap: 8px;
    }
    
    .page-tab {
        padding: 10px 16px;
        font-size: 0.9rem;
        min-width: 120px;
    }
    
    .page-content {
        max-height: calc(100vh - 280px);
    }
    
    .iframe-container {
        height: calc(100vh - 320px);
    }
}

/* ===== LEAFLET ZOOM CONTROLS ===== */
.leaflet-control-zoom {
    background: rgba(93, 93, 255, 0.1) !important;
    border: 2px solid rgba(93, 93, 255, 0.3) !important;
    border-radius: 12px !important;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3) !important;
    padding: 6px !important;
    left: auto !important;
    right: 12px !important;
    top: 12px !important;
    z-index: 500 !important;
}

.leaflet-control-zoom-in,
.leaflet-control-zoom-out {
    background: linear-gradient(135deg, #5d5dff 0%, #4a4a9f 100%) !important;
    color: white !important;
    border: none !important;
    border-radius: 8px !important;
    font-weight: 700 !important;
    font-size: 1.1rem !important;
    width: 38px !important;
    height: 38px !important;
    line-height: 38px !important;
    text-align: center !important;
    transition: all 0.3s cubic-bezier(0.25, 1, 0.5, 1) !important;
    cursor: pointer !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    box-shadow: 0 2px 6px rgba(93, 93, 255, 0.2) !important;
    z-index: 500 !important;
}

.leaflet-control-zoom-in:hover,
.leaflet-control-zoom-out:hover {
    background: linear-gradient(135deg, #7d7dff 0%, #6d6dff 100%) !important;
    transform: translateY(-2px) !important;
    box-shadow: 0 4px 12px rgba(93, 93, 255, 0.4) !important;
}

.leaflet-control-zoom-in:active,
.leaflet-control-zoom-out:active {
    transform: translateY(0) !important;
    box-shadow: 0 2px 4px rgba(93, 93, 255, 0.2) !important;
}

.leaflet-control-zoom-out {
    margin-top: 4px !important;
}

@media (max-width: 768px) {
    .leaflet-control-zoom {
        padding: 4px !important;
        right: 8px !important;
        top: auto !important;
        bottom: 80px !important;
    }
    
    .leaflet-control-zoom-in,
    .leaflet-control-zoom-out {
        width: 36px !important;
        height: 36px !important;
        line-height: 36px !important;
        font-size: 1rem !important;
    }
}

@media (max-width: 400px) {
    .leaflet-control-zoom {
        padding: 3px !important;
        right: 8px !important;
        top: auto !important;
        bottom: 60px !important;
    }
    
    .leaflet-control-zoom-in,
    .leaflet-control-zoom-out {
        width: 32px !important;
        height: 32px !important;
        line-height: 32px !important;
        font-size: 0.9rem !important;
    }
}
    </style>
</head>
<body>

<div class="wrapper">
    <?php include("Components/Sidebar.php");?>

    <main>
        <div style="text-align: center; margin-bottom: 20px;">
            <p style="margin: 0; font-size: 2rem; font-weight: 700; color: #B22222;">Vehicle Dashboard</p>
        </div>

        <!-- PAGE NAVIGATION TABS -->
        <div class="page-tabs">
            <button class="page-tab active" onclick="switchPage(1)">🚗 Fleet Overview</button>
            <button class="page-tab" onclick="switchPage(2)">📋 Vehicle Registry</button>
            <button class="page-tab" onclick="switchPage(3)">🔧 Repair Requests</button>
        </div>

        <!-- PAGE 1: FLEET OVERVIEW -->
        <div id="page-1" class="page-content active">
        <div class="summary">
            <div class="summary-item">
                <h3>Average Fuel Level</h3>
                <div class="fuel-bar-container" style="width:150px; margin:auto; height:15px;">
                    <div class="fuel-bar" style="width:<?php echo $avgFuel ? min($avgFuel,100) : 0; ?>%; background:<?php echo $avgFuelColor; ?>; line-height:15px; font-size:10px;">
                        <?php echo $avgFuel !== null ? $avgFuel.'%' : 'N/A'; ?>
                    </div>
                </div>
            </div>
            
            <div class="summary-item">
                <h3>Available Vehicles ✅</h3>
                <p style="color:#28a745;"><?php echo $availableCount; ?></p>
            </div>
            
            <div class="summary-item">
                <h3>Vehicles Deployed 🚨</h3>
                <p style="color:#B22222;"><?php echo $inUseCount; ?></p>
            </div>
            
            <div class="summary-item">
                <h3>Inactive Vehicles 🛠️</h3>
                <p style="color:#dc3545;"><?php echo $inactiveCount; ?></p>
            </div>
        </div>

        <!-- NEW LAYOUT: Map on left (65%), Vehicles panel on right (35%) -->
        <div class="main-layout">
            <!-- MAP CONTAINER (LEFT SIDE) -->
            <div class="map-container">
                <div id="map"></div>
            </div>

            <!-- VEHICLE PANEL (RIGHT SIDE) -->
            <div class="vehicle-panel">
                <h2 class="vehicle-panel-title">🚗 Vehicles</h2>
                <div class="dashboard">
            <?php foreach($vehicles as $v):
                $fuel = $v['fuel_level'];
                $fuel_text = $fuel !== null ? $fuel.' L' : 'N/A';
                $fuel_color = getFuelColor($fuel);
                $is_in_use = $v['current_use'] !== 'N/A';
                // Use derived fuel level for warning check
                $is_critical_fuel = ($fuel !== null && $fuel > 0 && $fuel <= CRITICAL_FUEL_PERCENT);
                
                // Use the derived status for display
                $displayed_status = ucfirst($v['status']);
            ?>
            <div class="vehicle-card"
                    data-no="<?php echo htmlspecialchars($v['vehicle_no']); ?>"
                    data-type="<?php echo htmlspecialchars($v['vehicle_type']); ?>"
                    data-status="<?php echo htmlspecialchars($v['status']); ?>"
                    data-fuel="<?php echo htmlspecialchars($fuel_text); ?>"
                    data-fuelval="<?php echo htmlspecialchars($fuel ?? 0); ?>"
                    data-use="<?php echo htmlspecialchars($v['current_use']); ?>"
                    data-destination="<?php echo htmlspecialchars($v['status'] === 'deployed' && isset($v['current_use']) ? substr($v['current_use'], strpos($v['current_use'], 'to ') + 3) : ''); ?>"
                    data-is-deployed="<?php echo $is_in_use ? 'true' : 'false'; ?>"
                    data-description="<?php echo htmlspecialchars($v['description'] ?? ''); ?>">
                
                <div class="vehicle-icon"><?php echo getVehicleIcon($v['vehicle_type']); ?></div>
                
                <div class="vehicle-card-info">
                    <div class="vehicle-no"><?php echo htmlspecialchars($v['vehicle_no']); ?></div>
                    <div class="vehicle-type"><?php echo htmlspecialchars($v['vehicle_type']); ?></div>
                    <div class="vehicle-status <?php echo htmlspecialchars($v['status']); ?>">
                        <?php if ($v['status'] === 'deployed'): ?>
                            🚨 Deployed
                        <?php elseif ($v['status'] === 'in_repair'): ?>
                            🔧 In Repair
                        <?php elseif ($v['status'] === 'available'): ?>
                            ✅ Available
                        <?php else: ?>
                            🔴 Inactive
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="fuel-bar-container">
                    <div class="fuel-bar" style="width:<?php echo $fuel ? min($fuel,100) : 0; ?>%; background:<?php echo $fuel_color; ?>;">
                        <?php echo $fuel_text; ?>
                    </div>
                </div>
                
                <button class="location-btn" data-vehicle-no="<?php echo htmlspecialchars($v['vehicle_no']); ?>" style="
                    background-color: #B22222; 
                    color: white; 
                    border: none; 
                    padding: 8px 12px; 
                    border-radius: 4px; 
                    cursor: pointer;
                    font-weight: 600;
                    font-size: 0.9rem;
                    transition: background-color 0.2s;
                    white-space: nowrap;
                    height: fit-content;
                " onmouseover="this.style.backgroundColor='#8b1a1a';" onmouseout="this.style.backgroundColor='#B22222';">
                    <i class="fas fa-map-marker-alt"></i>
                </button>
                
                <?php if ($is_critical_fuel): ?>
                <div class="current-use-display">
                    <strong>⚠️ LOW FUEL</strong>
                </div>
                <?php endif; ?>

                <?php if ($is_in_use): ?>
                <div class="current-use-display" style="grid-column: 1 / -1; background: linear-gradient(135deg, rgba(76, 175, 80, 0.1) 0%, rgba(76, 175, 80, 0.05) 100%); border: 1px solid rgba(76, 175, 80, 0.3);">
                    <strong style="color: #4caf50; font-size: 0.9rem;">🚨 Current Use</strong>
                    <p style="color: #e2e2e2; margin: 4px 0 0 0; font-size: 0.85rem;"><?php echo htmlspecialchars($v['current_use']); ?></p>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
                </div>
            </div>
        </div>
        </div>
        <!-- END PAGE 1 -->

        <!-- PAGE 2: VEHICLE REGISTRY -->
        <div id="page-2" class="page-content">
            <div class="iframe-container">
                <iframe src="Vehicle_registry.php" allow="same-origin"></iframe>
            </div>
        </div>
        <!-- END PAGE 2 -->

        <!-- PAGE 3: VEHICLE REPAIR REQUESTS -->
        <div id="page-3" class="page-content">
            <div class="iframe-container">
                <iframe src="Vehicle_repair_request_list.php" allow="same-origin"></iframe>
            </div>
        </div>
        <!-- END PAGE 3 -->

        <div id="vehicleModal" class="modal">
    <div class="modal-content">
        <span class="close">×</span>
        <h3 id="modalVehicle"></h3>
        
        <div id="modalFuelWarning" class="current-use-display" style="display:none; text-align:center; background:#fff3cd; border-color:#ffeeba;">
            <strong style="font-size: 1.1rem; color:#856404;">⚠️ LOW FUEL WARNING ⚠️</strong>
            <p style="margin: 5px 0;">Fuel is below <?php echo CRITICAL_FUEL_PERCENT; ?>%. Refuel immediately.</p>
        </div>
        
        <div id="modalCurrentUse" class="current-use-display" style="display:none; text-align:center;">
            <strong style="font-size: 1.1rem;">🚨 CURRENTLY DEPLOYED 🚨</strong>
            <p id="modalUseDetail" style="margin: 5px 0;"></p>
        </div>
        
        <div class="vehicle-modal-info">
            <div><strong>Type:</strong> <br><span id="modalType"></span></div>
            <div><strong>Status:</strong> <br><span id="modalStatus"></span></div>
            <div><strong>Fuel:</strong> <br><span id="modalFuel"></span></div>
            <div><strong>Condition:</strong> <br><span id="modalCondition"></span></div>
        </div>
        
        <div id="modalDescriptionDiv" style="margin-top: 16px; padding: 14px; background: rgba(93, 93, 255, 0.08); border: 1px solid rgba(93, 93, 255, 0.2); border-radius: 8px; display: none;">
            <strong style="color: #a2a2c2; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 8px;">📋 Description</strong>
            <p id="modalDescription" style="margin: 0; color: #e2e2e2; font-size: 0.95rem; line-height: 1.5;"></p>
        </div>

        <div class="fuel-gauge">
            <div class="gauge-arc"></div>
            <div class="needle" id="needle"></div>
            <div class="center-cap"></div>
            <div class="label e">E</div>
            <div class="label f">F</div>
            <div class="value" id="fuelValue">0%</div>
        </div>
    </div>
</div>

<!-- REPAIR REQUEST MODAL -->
<div id="repairModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeRepairModal()">×</span>
        <h3>🔧 Request Vehicle Repair</h3>
        
        <form id="repairForm" onsubmit="submitRepairRequest(event)">
            <div class="form-group">
                <label for="repairVehicle">Select Vehicle:</label>
                <select id="repairVehicle" required>
                    <option value="">-- Choose Vehicle --</option>
                    <?php foreach($vehicles as $v): ?>
                        <option value="<?php echo htmlspecialchars($v['id']); ?>"><?php echo htmlspecialchars($v['vehicle_no'] . ' - ' . $v['vehicle_type']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="repairType">Repair Type:</label>
                <select id="repairType" required>
                    <option value="">-- Select Type --</option>
                    <option value="engine">Engine Repair</option>
                    <option value="tires">Tires</option>
                    <option value="brakes">Brakes</option>
                    <option value="transmission">Transmission</option>
                    <option value="electrical">Electrical</option>
                    <option value="suspension">Suspension</option>
                    <option value="other">Other</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="repairDescription">Description:</label>
                <textarea id="repairDescription" rows="4" placeholder="Describe the repair needed..." required></textarea>
            </div>
            
            <div class="form-group">
                <label for="repairPriority">Priority:</label>
                <select id="repairPriority" required>
                    <option value="low">Low</option>
                    <option value="medium" selected>Medium</option>
                    <option value="high">High</option>
                    <option value="urgent">Urgent</option>
                </select>
            </div>
            
            <div class="modal-actions">
                <button type="submit" class="btn-submit">Submit Request</button>
                <button type="button" class="btn-cancel" onclick="closeRepairModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- EXPENSES MODAL -->
<div id="expensesModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeExpensesModal()">×</span>
        <h3>💰 Vehicle Expenses</h3>
        
        <form id="expensesForm" onsubmit="submitExpense(event)">
            <div class="form-group">
                <label for="expenseVehicle">Select Vehicle:</label>
                <select id="expenseVehicle" required onchange="loadVehicleExpenses()">
                    <option value="">-- Choose Vehicle --</option>
                    <?php foreach($vehicles as $v): ?>
                        <option value="<?php echo htmlspecialchars($v['id']); ?>"><?php echo htmlspecialchars($v['vehicle_no'] . ' - ' . $v['vehicle_type']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="expenseType">Expense Type:</label>
                <select id="expenseType" required>
                    <option value="">-- Select Type --</option>
                    <option value="fuel">Fuel</option>
                    <option value="repairs">Repairs</option>
                    <option value="gear_oil">Gear Oil</option>
                    <option value="lub_oil">Lubricant Oil</option>
                    <option value="grease">Grease</option>
                    <option value="maintenance">Maintenance</option>
                    <option value="parts">Parts</option>
                    <option value="other">Other</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="expenseAmount">Amount (₱):</label>
                <input type="number" id="expenseAmount" step="0.01" min="0" placeholder="0.00" required>
            </div>
            
            <div class="form-group">
                <label for="expenseDate">Date:</label>
                <input type="date" id="expenseDate" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="expenseDescription">Description:</label>
                <textarea id="expenseDescription" rows="3" placeholder="Enter description..."></textarea>
            </div>
            
            <div class="modal-actions">
                <button type="submit" class="btn-submit">Record Expense</button>
                <button type="button" class="btn-cancel" onclick="closeExpensesModal()">Cancel</button>
            </div>
        </form>
        
        <div id="expensesList" style="margin-top: 20px; max-height: 300px; overflow-y: auto;">
            <h4>Recent Expenses</h4>
            <div id="expensesTableContainer"></div>
        </div>
    </div>
</div>

<!-- FUEL REPORTS LOG MODAL -->
<div id="fuelLogsModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeFuelLogsModal()">×</span>
        <h3>📊 Fuel Reports Log</h3>
        
        <div class="filter-section">
            <div class="form-group" style="margin-bottom: 15px;">
                <label for="logsVehicle">Filter by Vehicle:</label>
                <select id="logsVehicle" onchange="loadFuelLogs()">
                    <option value="">-- All Vehicles --</option>
                    <?php foreach($vehicles as $v): ?>
                        <option value="<?php echo htmlspecialchars($v['id']); ?>"><?php echo htmlspecialchars($v['vehicle_no']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group" style="margin-bottom: 15px;">
                <label for="logsDateRange">Date Range:</label>
                <select id="logsDateRange" onchange="loadFuelLogs()">
                    <option value="7days">Last 7 Days</option>
                    <option value="30days">Last 30 Days</option>
                    <option value="90days">Last 90 Days</option>
                    <option value="all">All Time</option>
                </select>
            </div>
        </div>
        
        <div id="fuelLogsTableContainer" style="max-height: 400px; overflow-y: auto;">
            <p style="text-align: center; color: #7f8c8d;">Select a vehicle to view fuel logs...</p>
        </div>
    </div>
</div>

<script>
// Make map global so other functions can access it
let vehicleMap;
let activeVehicleMarker = null;
let activeRoutingControl = null;
let stationMarker = null;
let allLocations = []; // Store loaded locations from Maps.php

// --- Load Locations from Maps.php Database ---
async function loadLocationsFromDatabase() {
    try {
        const response = await fetch('Maps.php?api=get_all', { method: 'POST' });
        const data = await response.json();
        if (data.success) {
            allLocations = data.locations;
        }
    } catch (error) {
        console.warn('Error loading locations from Maps.php:', error);
    }
}

// --- Leaflet Map JS ---
function initMap() {
    const fireStationLat = <?php echo $fireStationLat; ?>;
    const fireStationLng = <?php echo $fireStationLng; ?>;
    const maasinFireStation = [fireStationLat, fireStationLng];

    // Initialize Leaflet map
    vehicleMap = L.map('map').setView(maasinFireStation, <?php echo $mapZoom; ?>);

    // Add OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(vehicleMap);

    // Fire Station Marker
    stationMarker = L.marker(maasinFireStation, {
        title: "Maasin City Fire Station"
    }).addTo(vehicleMap)
        .bindPopup('<h5>Maasin City Fire Station (HQ)</h5>')
        .openPopup();

    // Load and display all locations from database
    displayLocationsOnMap();
}

// --- LOCATION PLOTTING FUNCTION ---
function displayLocationsOnMap() {
    if (!vehicleMap || allLocations.length === 0) return;
    
    allLocations.forEach(loc => {
        const markerColor = loc.type === 'custom' ? 'blue' : 'red';
        L.circleMarker([loc.lat, loc.lng], {
            radius: 6,
            fillColor: markerColor,
            color: '#fff',
            weight: 2,
            opacity: 1,
            fillOpacity: 0.7
        }).addTo(vehicleMap)
        .bindPopup(`<strong>${loc.name}</strong><br>Category: ${loc.category}<br>Lat: ${loc.lat.toFixed(6)}<br>Lng: ${loc.lng.toFixed(6)}`);
    });
}

// --- LOCATION PLOTTING FUNCTION ---
function plotVehicleLocation(vehicleNo, lat, lng) {
    if (!vehicleMap) return;

    const vehicleLocation = [lat, lng];

    // 1. Clear any previous vehicle marker
    if (activeVehicleMarker) {
        vehicleMap.removeLayer(activeVehicleMarker);
    }

    // 2. Create the new vehicle marker with blue icon
    activeVehicleMarker = L.marker(vehicleLocation, {
        title: `Current Location: ${vehicleNo}`,
        icon: L.icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-blue.png',
            iconSize: [25, 41],
            iconAnchor: [12, 41]
        })
    }).addTo(vehicleMap)
        .bindPopup(`<p style="font-weight:bold; margin:0;">Current Location of ${vehicleNo}</p><small>(${lat.toFixed(4)}, ${lng.toFixed(4)})</small>`)
        .openPopup();

    // 3. Center the map on the new marker and zoom in
    vehicleMap.setView(vehicleLocation, 17);
}

// --- Event Listener for Location Buttons ---
document.querySelectorAll(".location-btn").forEach(button => {
    button.addEventListener("click", async () => {
        const vehicleCard = button.closest('.vehicle-card');
        const vehicleNo = button.dataset.vehicleNo;
        const isDeployed = vehicleCard?.dataset.isDeployed === 'true';
        const destination = vehicleCard?.dataset.destination || '';
        
        const fireStationLat = <?php echo $fireStationLat; ?>;
        const fireStationLng = <?php echo $fireStationLng; ?>;

        if (isDeployed && destination) {
            // Vehicle is in use - navigate to active trips page
            window.location.href = 'Active_trips.php';
        } else {
            // Vehicle is not in use - show default fire station location
            plotVehicleLocation(vehicleNo, fireStationLat, fireStationLng);
        }
    });
});

// Function to show route on map
function showVehicleRoute(startLat, startLng, endLat, endLng, vehicleNo, destination) {
    if (!vehicleMap) return;
    
    // Clear existing route
    if (activeRoutingControl) {
        vehicleMap.removeControl(activeRoutingControl);
    }

    const startPoint = L.latLng(startLat, startLng);
    const endPoint = L.latLng(endLat, endLng);

    activeRoutingControl = L.Routing.control({
        waypoints: [startPoint, endPoint],
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
                }).bindPopup('<strong>🚒 Fire Station (Start)</strong><br>Maasin City Fire Station')
                    .openPopup();
            } else {
                return L.marker(wp.latLng, {
                    icon: L.icon({
                        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-green.png', 
                        iconSize: [25, 41],
                        iconAnchor: [12, 41]
                    })
                }).bindPopup(`<strong>📍 Destination</strong><br>${destination}`)
                    .openPopup();
            }
        }
    }).addTo(vehicleMap);

    activeRoutingControl.on('routesfound', function(e) {
        const route = e.routes[0];
        const distanceKm = route.summary.totalDistance / 1000;
        const durationMin = Math.round(route.summary.totalTime / 60);
        const durationSec = route.summary.totalTime;
        
        console.log(`✅ Route Found for ${vehicleNo}!\n📍 Destination: ${destination}\n📏 Distance: ${distanceKm.toFixed(2)} km\n⏱️ Time: ${durationMin} min`);
        
        try {
            const coords = e.routes[0].coordinates.map(c => [c.lat, c.lng]);
            vehicleMap.fitBounds(coords, { padding: [50, 50] });
            
            // Optional: Add animated arrow animation if needed
            // animateArrowAlongRoute(coords, durationSec);
        } catch (err) {
            console.warn('Could not fit bounds:', err);
        }
    });

    activeRoutingControl.on('routingerror', function() {
        console.error('Error calculating route for ' + vehicleNo);
    });
}

// Geocode address function with fallback locations
let maasinLocations = {};
async function geocodeAddress(name) {
    if (!name || !name.trim()) return null;
    
    const searchName = name.trim().toLowerCase();
    
    // Load default locations if not already loaded
    if (Object.keys(maasinLocations).length === 0) {
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
        
        // Try to load from Maps.php database
        try {
            const response = await fetch('Maps.php?api=get_all', { method: 'POST' });
            const data = await response.json();
            if (data.success && data.locations) {
                data.locations.forEach(loc => {
                    maasinLocations[loc.name] = [loc.lat, loc.lng];
                });
            }
        } catch (e) {
            console.warn('Could not load locations from database, using defaults');
        }
    }
    
    // EXACT MATCH - Check database locations first
    for (const locName in maasinLocations) {
        if (locName.toLowerCase() === searchName) {
            const [lat, lon] = maasinLocations[locName];
            return { lat: lat, lon: lon, source: 'Database (exact)' };
        }
    }
    
    // PARTIAL MATCH - Check if search text is contained in location name
    for (const locName in maasinLocations) {
        if (locName.toLowerCase().includes(searchName) || searchName.includes(locName.toLowerCase())) {
            const [lat, lon] = maasinLocations[locName];
            return { lat: lat, lon: lon, source: 'Database (partial)', matchedName: locName };
        }
    }

    // Fallback to Nominatim for external locations
    let q = name;
    if (q.indexOf('Maasin') === -1 && q.indexOf('City') === -1) {
        q += ' Maasin City';
    }

    const url = `https://nominatim.openstreetmap.org/search?format=json&limit=1&q=${encodeURIComponent(q)}&countrycodes=ph&viewbox=124.83,10.12,124.85,10.15&bounded=1`;
    try {
        const res = await fetch(url, { headers: { 'User-Agent': 'BFP-Vehicle-Dashboard' }});
        if (!res.ok) return null;
        const data = await res.json();
        if (!data || !data.length) return null;
        return { lat: parseFloat(data[0].lat), lon: parseFloat(data[0].lon), source: 'Nominatim (external)' };
    } catch (err) {
        console.error('Geocode error:', err);
        return null;
    }
}
// --- End Location Function ---

// Initialize map and load locations when page loads
window.addEventListener('DOMContentLoaded', async () => {
    await loadLocationsFromDatabase();
    initMap();
});


// --- Modal JS ---
const modal = document.getElementById("vehicleModal");
const closeBtn = document.querySelector(".close");
const needle = document.getElementById("needle");
const fuelValue = document.getElementById("fuelValue");
const modalCurrentUseDiv = document.getElementById("modalCurrentUse");
const modalUseDetail = document.getElementById("modalUseDetail");
const modalFuelWarning = document.getElementById("modalFuelWarning");

function updateFuelGauge(level) {
    const angle = -135 + (level * 2.7);
    needle.style.transform = `rotate(${angle}deg)`;
    fuelValue.textContent = `${level} L`; // Changed to L for consistency, though gauge logic is based on 0-100 scale implicitly
}

document.querySelectorAll(".vehicle-card").forEach(card => {
    card.addEventListener("click", (event) => {
        // Prevent modal from opening if the location button was clicked
        if (event.target.classList.contains('location-btn') || event.target.closest('.location-btn')) {
            return;
        }

        const fuelVal = parseFloat(card.dataset.fuelval);
        const description = card.dataset.description;
        const criticalFuelPercent = <?php echo CRITICAL_FUEL_PERCENT; ?>;

        let condition = "Good";
        let conditionClass = "status-good";
        
        // Condition logic based on derived fuel status:
        if (fuelVal <= 0) { condition = "INACTIVE (Fuel Empty)"; conditionClass = "status-danger"; }
        else if (fuelVal <= criticalFuelPercent) { condition = "Critical (Needs Refuel)"; conditionClass = "status-warning"; }

        document.getElementById("modalVehicle").innerText = card.dataset.no;
        document.getElementById("modalType").innerText = card.dataset.type;
        
        // Display status with descriptions
        const status = card.dataset.status;
        let statusDisplay = status.charAt(0).toUpperCase() + status.slice(1);
        if (status === 'deployed') {
            statusDisplay = '🚨 Deployed (In Active Trip)';
        } else if (status === 'in_repair') {
            statusDisplay = '🔧 In Repair (Maintenance)';
        } else if (status === 'available') {
            statusDisplay = '✅ Available';
        } else if (status === 'inactive') {
            statusDisplay = '🔴 Inactive (No Fuel)';
        }
        document.getElementById("modalStatus").innerText = statusDisplay;
        
        document.getElementById("modalFuel").innerText = card.dataset.fuel;
        const conditionSpan = document.getElementById("modalCondition");
        conditionSpan.innerText = condition;
        conditionSpan.className = conditionClass;

        const currentUse = card.dataset.use;
        if (currentUse !== 'N/A') {
            modalUseDetail.innerText = currentUse;
            modalCurrentUseDiv.style.display = 'block';
        } else {
            modalUseDetail.innerText = '';
            modalCurrentUseDiv.style.display = 'none';
        }
        
        // Show low fuel warning if fuel is critical AND greater than 0
        if (fuelVal > 0 && fuelVal <= criticalFuelPercent) {
            modalFuelWarning.style.display = 'block';
        } else {
            modalFuelWarning.style.display = 'none';
        }
        
        // Display description if available
        const modalDescriptionDiv = document.getElementById('modalDescriptionDiv');
        if (description && description.trim() !== '') {
            document.getElementById('modalDescription').innerText = description;
            modalDescriptionDiv.style.display = 'block';
        } else {
            modalDescriptionDiv.style.display = 'none';
        }

        updateFuelGauge(Math.min(fuelVal,100)); // Update gauge based on 0-100 scale (assuming max capacity is 100L or normalized to 100%)
        modal.style.display = "block";
    });
});

closeBtn.onclick = () => modal.style.display = "none";
window.onclick = e => { if (e.target === modal) modal.style.display = "none"; };

// ===== REPAIR REQUEST MODAL FUNCTIONS =====
function openRepairModal() {
    document.getElementById('repairModal').style.display = 'block';
}

function closeRepairModal() {
    document.getElementById('repairModal').style.display = 'none';
    document.getElementById('repairForm').reset();
}

function submitRepairRequest(event) {
    event.preventDefault();
    
    const vehicleId = document.getElementById('repairVehicle').value;
    const repairType = document.getElementById('repairType').value;
    const description = document.getElementById('repairDescription').value;
    const priority = document.getElementById('repairPriority').value;
    
    if (!vehicleId || !repairType || !description) {
        alert('Please fill in all required fields');
        return;
    }
    
    // Send to backend API
    fetch('submit_repair.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            vehicleId: vehicleId,
            repairType: repairType,
            description: description,
            priority: priority
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✓ Repair request submitted successfully!\n\nRepair ID: ' + data.repair_id + '\nVehicle ID: ' + vehicleId + '\nType: ' + repairType + '\nPriority: ' + priority);
            closeRepairModal();
        } else {
            alert('✗ Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('✗ Error submitting repair request: ' + error.message);
    });
}

// ===== EXPENSES MODAL FUNCTIONS =====
function openExpensesModal() {
    document.getElementById('expensesModal').style.display = 'block';
}

function closeExpensesModal() {
    document.getElementById('expensesModal').style.display = 'none';
    document.getElementById('expensesForm').reset();
}

function loadVehicleExpenses() {
    const vehicleId = document.getElementById('expenseVehicle').value;
    const container = document.getElementById('expensesTableContainer');
    
    if (!vehicleId) {
        container.innerHTML = '<p style="color: #7f8c8d; text-align: center; padding: 20px;">Select a vehicle to view expenses</p>';
        return;
    }
    
    // Fetch expenses from backend
    fetch('get_vehicle_expenses.php?vehicle_id=' + vehicleId)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.expenses.length > 0) {
                let html = '<table class="expenses-table"><thead><tr><th>Date</th><th>Type</th><th>Amount</th><th>Description</th><th>Recorded By</th></tr></thead><tbody>';
                
                data.expenses.forEach(exp => {
                    const expenseDate = new Date(exp.expense_date).toLocaleDateString();
                    html += `<tr>
                        <td>${expenseDate}</td>
                        <td><span class="expense-type-badge">${capitalizeWords(exp.expense_type.replace(/_/g, ' '))}</span></td>
                        <td style="font-weight: 600; color: #5d5dff;">₱${parseFloat(exp.amount).toLocaleString('en-PH', {minimumFractionDigits: 2})}</td>
                        <td style="font-size: 0.9rem;">${exp.description || '—'}</td>
                        <td style="font-size: 0.9rem;">${exp.user_name || 'Unknown'}</td>
                    </tr>`;
                });
                
                html += '</tbody></table>';
                html += '<div style="margin-top: 16px; padding: 12px; background: rgba(93, 93, 255, 0.1); border-radius: 8px; border-left: 3px solid #5d5dff;">';
                html += '<strong>Total Expenses:</strong> <span style="color: #5d5dff; font-size: 1.2rem; font-weight: 700;">₱' + parseFloat(data.total).toLocaleString('en-PH', {minimumFractionDigits: 2}) + '</span>';
                html += '</div>';
                container.innerHTML = html;
            } else {
                container.innerHTML = '<p style="color: #7f8c8d; text-align: center; padding: 20px;">No expenses recorded for this vehicle</p>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            container.innerHTML = '<p style="color: #e74c3c; text-align: center; padding: 20px;">Error loading expenses</p>';
        });
}

// Helper function to capitalize words
function capitalizeWords(str) {
    return str.replace(/\b\w/g, char => char.toUpperCase());
}

function submitExpense(event) {
    event.preventDefault();
    
    const vehicleId = document.getElementById('expenseVehicle').value;
    const expenseType = document.getElementById('expenseType').value;
    const amount = document.getElementById('expenseAmount').value;
    const date = document.getElementById('expenseDate').value;
    const description = document.getElementById('expenseDescription').value;
    
    if (!vehicleId || !expenseType || !amount || !date) {
        alert('Please fill in all required fields');
        return;
    }
    
    // Send to backend API
    fetch('submit_expense.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            vehicleId: vehicleId,
            expenseType: expenseType,
            amount: parseFloat(amount),
            expenseDate: date,
            description: description || null
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✓ Expense recorded successfully!\n\nVehicle ID: ' + vehicleId + '\nType: ' + expenseType + '\nAmount: ₱' + parseFloat(amount).toLocaleString() + '\nDate: ' + date);
            
            // Reset form
            document.getElementById('expensesForm').reset();
            
            // Reload expenses list
            loadVehicleExpenses();
        } else {
            alert('✗ Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('✗ Error recording expense: ' + error.message);
    });
}

// ===== FUEL REPORTS LOG FUNCTIONS =====
function openFuelLogsModal() {
    document.getElementById('fuelLogsModal').style.display = 'block';
}

function closeFuelLogsModal() {
    document.getElementById('fuelLogsModal').style.display = 'none';
}

function loadFuelLogs() {
    const vehicleId = document.getElementById('logsVehicle').value;
    const dateRange = document.getElementById('logsDateRange').value;
    const container = document.getElementById('fuelLogsTableContainer');
    
    if (!vehicleId) {
        container.innerHTML = '<p style="text-align: center; color: #7f8c8d; padding: 20px;">Select a vehicle to view fuel logs...</p>';
        return;
    }
    
    // Sample fuel log data - replace with actual data from backend
    const sampleLogs = [
        { date: '2026-01-26', time: '10:30 AM', vehicle: 'BFP-001', fuelAmount: 50, location: 'Shell Gas Station', operator: 'Jonas' },
        { date: '2026-01-25', time: '02:15 PM', vehicle: 'BFP-001', fuelAmount: 45, location: 'Petronas Station', operator: 'Michael' },
        { date: '2026-01-24', time: '09:00 AM', vehicle: 'BFP-001', fuelAmount: 55, location: 'Shell Gas Station', operator: 'Jonas' }
    ];
    
    let html = '<table class="logs-table"><thead><tr><th>Date & Time</th><th>Vehicle</th><th>Fuel (L)</th><th>Location</th><th>Operator</th></tr></thead><tbody>';
    
    sampleLogs.forEach(log => {
        html += `<tr>
            <td>${log.date}<br><small>${log.time}</small></td>
            <td style="font-weight: 600;">${log.vehicle}</td>
            <td><div class="fuel-bar" style="background: linear-gradient(90deg, #28a745, #ffc107); width: ${log.fuelAmount}%; min-width: 40px;">${log.fuelAmount}L</div></td>
            <td>${log.location}</td>
            <td>${log.operator}</td>
        </tr>`;
    });
    
    html += '</tbody></table>';
    container.innerHTML = html;
}

// Close modals when clicking outside
document.addEventListener('click', function(event) {
    const repairModal = document.getElementById('repairModal');
    const expensesModal = document.getElementById('expensesModal');
    const fuelLogsModal = document.getElementById('fuelLogsModal');
    
    if (event.target === repairModal) closeRepairModal();
    if (event.target === expensesModal) closeExpensesModal();
    if (event.target === fuelLogsModal) closeFuelLogsModal();
});

// ===== PAGE NAVIGATION FUNCTION =====
function switchPage(pageNum) {
    // Hide all pages
    document.querySelectorAll('.page-content').forEach(page => {
        page.classList.remove('active');
    });
    
    // Remove active class from all tabs
    document.querySelectorAll('.page-tab').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Show selected page
    document.getElementById(`page-${pageNum}`).classList.add('active');
    document.querySelectorAll('.page-tab')[pageNum - 1].classList.add('active');
}
</script>

    </main>
</div>

<script>
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