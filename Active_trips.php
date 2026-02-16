<?php
session_start();
include("db_connect.php");

// Fetch only ACTIVE trips with vehicle and driver info
$sql = "SELECT t.*, d.full_name as driver_name FROM trip_tickets t LEFT JOIN drivers d ON t.driver_id = d.driver_id WHERE t.status = 'Active' ORDER BY t.created_at DESC";
$result = $pdo->query($sql);
$trips = $result->fetchAll();

// Extract vehicle info from ACTIVE trips
$active_trips = [];
foreach ($trips as $trip) {
    $vehicle_location = $trip['places_to_visit'];
    $vehicle_purpose = $trip['purpose'];
    $active_trips[strtoupper($trip['vehicle_plate_no'])] = [
        'use' => "{$vehicle_purpose} to {$vehicle_location}",
        'destination' => $vehicle_location,
        'purpose' => $vehicle_purpose,
        'driver_id' => $trip['driver_id'],
        'driver_name' => $trip['driver_name']
    ];
}

// Fetch ALL vehicles
$sqlVehicles = "SELECT id, vehicle_no, vehicle_type, status, current_fuel, description FROM vehicles ORDER BY vehicle_no ASC";
$resultVehicles = $pdo->query($sqlVehicles);
$vehicles = [];
$totalFuel = 0;
$countWithFuel = 0;

const CRITICAL_FUEL_PERCENT = 25;
const INACTIVE_FUEL_LEVEL = 0;

$inUseCount = 0;
$availableCount = 0;
$inactiveCount = 0;

while ($row = $resultVehicles->fetch()) {
    $vehicle_no = strtoupper($row['vehicle_no']);
    $fuel_level = $row['current_fuel'] ?? 0;
    
    // Determine deployment status
    $is_deployed = isset($active_trips[$vehicle_no]);
    $row['current_use'] = $is_deployed ? $active_trips[$vehicle_no]['use'] : 'N/A';
    $row['trip_info'] = $is_deployed ? $active_trips[$vehicle_no] : null;
    
    // Determine final operational status
    $final_status = strtolower($row['status']);
    
    if ($final_status === 'in_repair') {
        $final_status = 'in_repair';
    } elseif ($is_deployed) {
        $final_status = 'deployed';
    } elseif ($fuel_level <= INACTIVE_FUEL_LEVEL) {
        $final_status = 'inactive';
    } else {
        $final_status = 'available';
    }
    
    $row['status'] = $final_status;
    
    // Count vehicles by status
    if ($final_status === 'deployed') {
        $inUseCount++;
    } elseif ($final_status === 'available') {
        $availableCount++;
    } else {
        $inactiveCount++;
    }
    
    $vehicles[] = $row;
    
    // Total fuel calculation
    if ($fuel_level !== null) {
        $totalFuel += $fuel_level;
        $countWithFuel++;
    }
}

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

$avgFuel = $countWithFuel > 0 ? round($totalFuel / $countWithFuel, 1) : 0;
$avgFuelColor = getFuelColor($avgFuel);

// Maasin City Fire Station Coordinates
$officeLat = 10.132752;
$officeLng = 124.834795;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>BFP - Active Trips Today</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine/dist/leaflet-routing-machine.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.css" />
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-routing-machine/dist/leaflet-routing-machine.js"></script>
    <script src="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.js"></script>
    
    <style>
        :root {
            --primary-color: #5d5dff;
            --bg-dark: #1e1e2d;
            --bg-light-dark: #2a2a3e;
            --text-light: #e2e2e2;
            --text-gray: #a2a2c2;
            --border-color: #3d3d5c;
        }

        /* ===== BASE LAYOUT ===== */
        body {
            margin: 0;
            font-family: 'Poppins', Arial, sans-serif;
            background-color: var(--bg-dark);
            display: flex;
            flex-direction: column;
            height: 100vh;
            color: var(--text-light);
            -webkit-font-smoothing: antialiased;
            -webkit-touch-callout: none;
            overflow: hidden;
            position: fixed;
            width: 100%;
        }

        /* ===== HEADER ===== */
        header {
            background: linear-gradient(90deg, var(--primary-color) 60%, #7070ff 100%);
            color: white;
            padding: 15px 30px;
            display: none;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 5px rgba(0,0,0,0.3);
            z-index: 100;
        }

        /* ===== WRAPPER ===== */
        .wrapper {
            display: flex;
            flex: 1;
            overflow: hidden;
            width: 100%;
            height: 100%;
        }

        main { 
            display: flex; 
            flex: 1; 
            padding: 20px; 
            gap: 20px; 
            overflow: hidden;
            flex-direction: column;
            width: 100%;
            height: 100%;
            -webkit-overflow-scrolling: touch;
        }

        footer { display: none; }

        /* ===== PAGE SPECIFIC STYLES ===== */
        .Top-board {
            background: var(--bg-light-dark);
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            border: 1px solid var(--border-color);
            border-top: 3px solid var(--primary-color);
            margin-bottom: 20px;
        }

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
        }

        .trips-header {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary-color);
            margin: 0;
            letter-spacing: 1px;
        }

        .summary {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .summary-item {
            background: rgba(93, 93, 255, 0.1);
            padding: 15px 20px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 0 1 auto;
        }

        .summary-item h3 {
            margin: 0;
            font-size: 0.9rem;
            color: var(--text-gray);
        }

        .summary-stat {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        /* NEW LAYOUT: Map (65%) | Panels (35%) */
        .content {
            display: flex;
            gap: 20px;
            flex: 1;
            min-height: 0;
            width: 100%;
        }

        .map-section {
            flex: 0 0 65%;
            background: var(--bg-light-dark);
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
            padding: 15px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            overflow: hidden;
            border: 1px solid var(--border-color);
        }

        #trip-map {
            height: 100%;
            width: 100%;
            border-radius: 8px;
            flex: 1;
            min-height: 400px;
        }

        #map-info {
            padding: 10px;
            color: var(--primary-color);
            text-align: center;
            font-weight: 600;
            background: rgba(93, 93, 255, 0.1);
            border-radius: 4px;
            font-size: 0.9rem;
            border: 1px solid var(--border-color);
        }

        /* ===== VEHICLE PANEL (RIGHT SIDE - 35%) ===== */
        .vehicle-panel { flex: 0 0 35%; display: flex; flex-direction: column; gap: 16px; overflow: hidden; background: #2a2a3e; border: 1px solid rgba(93, 93, 255, 0.2); border-radius: var(--radius-lg); padding: 20px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3); }

        .vehicle-panel-title { font-size: 1.3rem; font-weight: 700; color: #5d5dff; margin: 0 0 12px 0; border-bottom: 2px solid rgba(93, 93, 255, 0.3); padding-bottom: 8px; }

        /* Panels Container (Right Side) */
        .panels-container { flex: 0 0 35%; display: flex; flex-direction: column; gap: 15px; overflow: hidden; }

        .panel { background: var(--bg-light-dark); border-radius: 10px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3); padding: 15px; display: flex; flex-direction: column; gap: 10px; overflow: hidden; border: 1px solid var(--border-color); flex: 1; min-height: 0; width: 500px; }

        .panel-title { font-size: 1.1rem; font-weight: 700; color: white; margin: 0; padding: 10px 12px; border-radius: 6px; background: linear-gradient(135deg, var(--primary-color), #7070ff); }

        .panel-list { flex: 1; overflow-y: auto; display: flex; flex-direction: column; gap: 10px; -webkit-overflow-scrolling: touch; }

        .panel-list::-webkit-scrollbar { width: 8px; }

        .panel-list::-webkit-scrollbar-track { background: var(--bg-dark); border-radius: 4px; }

        .panel-list::-webkit-scrollbar-thumb { background: var(--primary-color); border-radius: 4px; }

        .panel-list::-webkit-scrollbar-thumb:hover { background: #7070ff; }

        .dashboard { display: flex; flex-direction: column; gap: 12px; overflow-y: auto; flex: 1; min-height: 0; -webkit-overflow-scrolling: touch; }

        .dashboard::-webkit-scrollbar { width: 8px; }

        .dashboard::-webkit-scrollbar-track { background: rgba(93, 93, 255, 0.1); border-radius: 4px; }

        .dashboard::-webkit-scrollbar-thumb { background: #5d5dff; border-radius: 4px; }

        .dashboard::-webkit-scrollbar-thumb:hover { background: #7d7dff; }

        /* ===== VEHICLE CARDS ===== */
        .vehicle-card { background: #2a2a3e; border: 1px solid rgba(93, 93, 255, 0.2); border-radius: var(--radius-md); padding: 14px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3); text-align: left; transition: all 0.3s cubic-bezier(0.25, 1, 0.5, 1); cursor: pointer; position: relative; display: grid; grid-template-columns: auto 1fr auto auto; gap: 12px; align-items: center; flex-shrink: 0; }

        .vehicle-card:hover { transform: translateX(4px); box-shadow: 0 4px 16px rgba(93, 93, 255, 0.2); border-color: rgba(93, 93, 255, 0.5); background: #32324a; }

        .vehicle-card[data-status="deployed"] { border-color: rgba(76, 175, 80, 0.4); background: rgba(76, 175, 80, 0.05); }

        .vehicle-card[data-status="deployed"]:hover { box-shadow: 0 4px 16px rgba(76, 175, 80, 0.2); border-color: rgba(76, 175, 80, 0.6); background: rgba(76, 175, 80, 0.1); }

        .vehicle-card[data-status="inactive"] { opacity: 0.7; }

        .vehicle-card[data-status="inactive"]:hover { box-shadow: 0 4px 16px rgba(231, 76, 60, 0.2); }

        .vehicle-icon { font-size: 2rem; color: #5d5dff; min-width: 40px; text-align: center; }

        .vehicle-card-info { display: flex; flex-direction: column; gap: 2px; flex: 1; }

        .vehicle-no { font-weight: 700; font-size: 1rem; color: #e2e2e2; margin: 0; letter-spacing: 0.3px; }

        .vehicle-type { color: #a2a2c2; margin: 0; font-size: 0.8rem; }

        .vehicle-trip-info { color: #a2a2c2; margin: 0; font-size: 0.8rem; font-style: italic; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        .vehicle-status { font-weight: 600; margin: 0; padding: 3px 6px; border-radius: var(--radius-sm); font-size: 0.75rem; display: inline-block; }

        .vehicle-status.deployed { background-color: rgba(76, 175, 80, 0.2); color: #4caf50; border: 1px solid rgba(76, 175, 80, 0.4); }

        .vehicle-status.available { background-color: rgba(52, 152, 219, 0.2); color: #3498db; border: 1px solid rgba(52, 152, 219, 0.4); }

        .vehicle-status.in_repair { background-color: rgba(255, 152, 0, 0.2); color: #ff9800; border: 1px solid rgba(255, 152, 0, 0.4); }

        .vehicle-status.inactive { background-color: rgba(231, 76, 60, 0.2); color: #e74c3c; border: 1px solid rgba(231, 76, 60, 0.4); }

        .vehicle-card[data-status="in_repair"] { border-color: rgba(255, 152, 0, 0.4); background: rgba(255, 152, 0, 0.05); }

        .vehicle-card[data-status="in_repair"]:hover { box-shadow: 0 4px 16px rgba(255, 152, 0, 0.2); border-color: rgba(255, 152, 0, 0.6); background: rgba(255, 152, 0, 0.1); }

        .fuel-bar-container { background: var(--border-color); border-radius: var(--radius-sm); height: 20px; width: 100%; overflow: hidden; border: 1px solid #ddd; min-width: 80px; display: flex; align-items: center; }

        .fuel-bar { height: 100%; text-align: center; color: white; font-weight: 600; font-size: 0.75rem; line-height: 20px; transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1); display: flex; align-items: center; justify-content: center; white-space: nowrap; padding: 0 4px; }

        .current-use-display { grid-column: 1 / -1; padding: 10px 12px; background: rgba(93, 93, 255, 0.08); border: 1px solid rgba(93, 93, 255, 0.2); border-radius: var(--radius-sm); margin-top: 4px; }

        .location-btn { background-color: #B22222; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 0.9rem; transition: background-color 0.2s; white-space: nowrap; height: fit-content; }

        .details-btn { background-color: #5d5dff; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 0.9rem; transition: background-color 0.2s; white-space: nowrap; height: fit-content; }

        /* Touch-friendly button sizing */
        button {
            min-height: 44px;
            min-width: 44px;
            -webkit-user-select: none;
            user-select: none;
            -webkit-tap-highlight-color: transparent;
            tap-highlight-color: transparent;
        }

        @media (hover: hover) {
            button:hover {
                filter: brightness(1.1);
            }
        }

        .empty-panel { display: flex; align-items: center; justify-content: center; color: var(--text-gray); padding: 20px; text-align: center; font-size: 0.9rem; flex: 1; }

        /* Responsive */
        @media (max-width: 1200px) {
            .content {
                flex-direction: column;
            }
            
            .map-section {
                flex: 0 0 auto;
                height: 400px;
            }
            
            .trips-panel {
                flex: 0 0 300px;
            }
        }

        /* ===== TABLET RESPONSIVE (768px) ===== */
        @media (max-width: 768px) {
            body {
                height: auto;
                min-height: 100vh;
                position: relative;
            }

            main {
                padding: 12px;
                gap: 12px;
                flex: 1;
                overflow-y: auto;
                overflow-x: hidden;
                -webkit-overflow-scrolling: touch;
            }

            .wrapper {
                flex-direction: column;
                height: auto;
            }

            .Top-board {
                padding: 15px;
                margin-bottom: 12px;
                border-radius: 10px;
            }

            .page-header {
                flex-direction: column;
                gap: 10px;
            }

            .trips-header {
                font-size: 1.4rem;
                letter-spacing: 0.5px;
            }

            .summary {
                gap: 10px;
                flex-wrap: wrap;
            }

            .summary-item {
                flex: 0 1 calc(50% - 5px);
                padding: 10px 12px;
                font-size: 0.85rem;
            }

            .summary-stat {
                font-size: 1.2rem;
            }

            .content {
                flex-direction: column;
                gap: 15px;
            }

            .map-section {
                flex: 0 0 auto;
                height: 350px;
                padding: 12px;
                border-radius: 8px;
            }

            #trip-map {
                min-height: 300px;
                border-radius: 6px;
            }

            #map-info {
                padding: 8px;
                font-size: 0.8rem;
                border-radius: 4px;
            }

            .panels-container {
                flex: 1 1 auto;
                gap: 12px;
                flex-direction: column;
            }

            .panel {
                padding: 12px;
                gap: 8px;
                border-radius: 8px;
                flex: 0 0 auto;
                max-height: 350px;
                min-height: 200px;
                width: auto;
            }

            .panel-title {
                font-size: 1rem;
                padding: 8px 10px;
                border-radius: 5px;
            }

            .panel-list {
                gap: 8px;
            }

            .vehicle-card {
                grid-template-columns: auto 1fr auto;
                gap: 10px;
                padding: 10px;
                border-radius: 6px;
            }

            .vehicle-icon {
                font-size: 1.5rem;
                min-width: 35px;
            }

            .vehicle-card-info {
                gap: 1px;
            }

            .vehicle-no {
                font-size: 0.9rem;
            }

            .vehicle-type,
            .vehicle-trip-info {
                font-size: 0.75rem;
            }

            .vehicle-status {
                padding: 2px 5px;
                font-size: 0.7rem;
            }

            .fuel-bar-container {
                min-width: 70px;
                height: 18px;
                border-radius: 3px;
            }

            .fuel-bar {
                font-size: 0.65rem;
                line-height: 18px;
            }

            .location-btn {
                padding: 6px 10px;
                font-size: 0.8rem;
                border-radius: 4px;
            }

            .empty-panel {
                padding: 15px;
                font-size: 0.85rem;
            }

            /* Fuel Gauge - Smaller */
            .fuel-gauge {
                width: 140px;
                height: 140px;
                margin: 15px auto;
            }

            .gauge-arc {
                border: 6px solid transparent;
            }

            .needle {
                height: 50px;
                width: 3px;
            }

            .center-cap {
                width: 16px;
                height: 16px;
            }

            .fuel-gauge .label {
                font-size: 0.8rem;
            }

            .fuel-gauge .value {
                font-size: 1rem;
            }

            /* Modal - Tablet */
            .modal-content {
                width: 85%;
                max-width: 400px;
                padding: 20px;
                margin: 20% auto;
            }

            .modal-content h3 {
                font-size: 1.2rem;
                margin-bottom: 15px;
            }

            .vehicle-modal-info {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }

            .vehicle-modal-info div {
                padding: 10px;
            }

            .vehicle-modal-info strong {
                font-size: 0.75rem;
            }

            .vehicle-modal-info span {
                font-size: 0.9rem;
            }
        }

        /* ===== MOBILE RESPONSIVE (480px) ===== */
        @media (max-width: 480px) {
            body {
                position: fixed;
                height: 100vh;
            }

            main {
                padding: 8px;
                gap: 8px;
                overflow-y: auto;
                overflow-x: hidden;
                -webkit-overflow-scrolling: touch;
            }

            .Top-board {
                padding: 10px;
                margin-bottom: 8px;
                border-radius: 8px;
                border-top: 2px solid var(--primary-color);
            }

            .trips-header {
                font-size: 1.1rem;
                letter-spacing: 0;
            }

            .summary {
                gap: 6px;
                flex-wrap: wrap;
            }

            .summary-item {
                flex: 0 1 calc(50% - 3px);
                padding: 8px 10px;
                font-size: 0.75rem;
                border-radius: 6px;
            }

            .summary-item h3 {
                font-size: 0.75rem;
            }

            .summary-stat {
                font-size: 1rem;
            }

            .map-section {
                flex: 0 0 auto;
                height: 250px;
                padding: 10px;
                border-radius: 6px;
            }

            #trip-map {
                min-height: 220px;
                border-radius: 5px;
            }

            #map-info {
                padding: 6px;
                font-size: 0.7rem;
                overflow: hidden;
                text-overflow: ellipsis;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
            }

            .panels-container {
                gap: 8px;
                flex-direction: column;
            }

            .panel {
                padding: 10px;
                gap: 6px;
                border-radius: 6px;
                max-height: 400px;
                min-height: 180px;
                width: 100%;
            }

            .panel-title {
                font-size: 0.9rem;
                padding: 6px 8px;
                margin: -10px -10px 0 -10px;
                border-radius: 6px 6px 0 0;
            }

            .panel-list {
                gap: 6px;
            }

            /* Vehicle Card - Mobile Optimized */
            .vehicle-card {
                grid-template-columns: auto 1fr;
                gap: 8px;
                padding: 10px;
                border-radius: 5px;
                row-gap: 8px;
            }

            .vehicle-card:hover {
                transform: translateX(2px);
                box-shadow: 0 2px 8px rgba(93, 93, 255, 0.15);
            }

            .vehicle-icon {
                font-size: 1.5rem;
                min-width: 35px;
                text-align: center;
                grid-column: 1;
                grid-row: 1 / 3;
            }

            .vehicle-card-info {
                gap: 0;
                min-width: 0;
                grid-column: 2;
                grid-row: 1;
            }

            .vehicle-no {
                font-size: 0.85rem;
                font-weight: 700;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .vehicle-type {
                font-size: 0.65rem;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .vehicle-trip-info {
                font-size: 0.65rem;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
                max-width: 100%;
            }

            /* Fuel Bar - Full Width Below */
            .fuel-bar-container {
                grid-column: 1 / -1;
                min-width: 100%;
                height: 24px;
                border-radius: 3px;
                display: flex;
                align-items: center;
                margin: 4px 0;
            }

            .fuel-bar {
                font-size: 0.65rem;
                line-height: 24px;
                height: 100%;
            }

            /* Buttons - Side by Side Below */
            .location-btn {
                padding: 8px 10px;
                font-size: 0.75rem;
                border-radius: 4px;
                height: auto;
                line-height: 1.2;
                flex: 1;
                grid-column: 1;
                grid-row: 3;
            }

            .details-btn {
                padding: 8px 10px;
                font-size: 0.75rem;
                border-radius: 4px;
                height: auto;
                line-height: 1.2;
                flex: 1;
                grid-column: 2;
                grid-row: 3;
                background-color: #5d5dff !important;
                border: none;
                color: white;
            }

            .details-btn:hover {
                background-color: #7070ff !important;
            }

            .empty-panel {
                padding: 12px;
                font-size: 0.8rem;
            }

            /* Current Use Display - Hidden on small mobile */
            .current-use-display {
                grid-column: 1 / -1;
                display: none;
                padding: 8px 10px;
                margin-top: 2px;
                font-size: 0.7rem;
                border-radius: 3px;
            }

            /* Fuel Gauge - Very Small */
            .fuel-gauge {
                width: 100px;
                height: 100px;
                margin: 15px auto;
            }

            .gauge-arc {
                border: 4px solid transparent;
                inset: 5px;
            }

            .needle {
                height: 35px;
                width: 2px;
                left: 50%;
                margin-left: -1px;
            }

            .center-cap {
                width: 10px;
                height: 10px;
            }

            .fuel-gauge .label {
                font-size: 0.65rem;
            }

            .fuel-gauge .label.e {
                bottom: 10px;
                left: 10px;
            }

            .fuel-gauge .label.f {
                bottom: 10px;
                right: 10px;
            }

            .fuel-gauge .value {
                font-size: 0.85rem;
                top: 55%;
            }

            /* Modal - Mobile Optimized */
            .modal-content {
                width: 90%;
                max-width: 100%;
                padding: 15px;
                margin: 25% auto;
                border-radius: 8px;
                max-height: 80vh;
                overflow-y: auto;
            }

            .modal-content .close {
                font-size: 24px;
                top: 8px;
                right: 10px;
            }

            .modal-content h3 {
                font-size: 1rem;
                margin-bottom: 12px;
            }

            .vehicle-modal-info {
                grid-template-columns: 1fr;
                gap: 8px;
                margin-top: 12px;
            }

            .vehicle-modal-info div {
                padding: 10px;
                border-left: 3px solid var(--primary-color);
                border-radius: 4px;
            }

            .vehicle-modal-info strong {
                font-size: 0.7rem;
                margin-bottom: 4px;
            }

            .vehicle-modal-info span {
                font-size: 0.9rem;
            }

            /* Modal Fuel Bar */
            .modal-content .fuel-bar-container {
                height: 28px !important;
                border-radius: 6px;
            }

            .modal-content .fuel-bar {
                font-size: 0.7rem;
                line-height: 28px;
            }
        }

        /* ===== ULTRA-SMALL MOBILE (320px) ===== */
        @media (max-width: 360px) {
            main {
                padding: 6px;
                gap: 6px;
            }

            .Top-board {
                padding: 8px;
                margin-bottom: 6px;
            }

            .trips-header {
                font-size: 1rem;
            }

            .summary-item {
                padding: 6px 8px;
                font-size: 0.7rem;
            }

            .map-section {
                height: 200px;
                padding: 8px;
            }

            #trip-map {
                min-height: 180px;
            }

            .panel {
                padding: 8px;
                gap: 4px;
                min-height: 160px;
                width: 100%;
            }

            .panel-title {
                font-size: 0.85rem;
                padding: 5px 6px;
            }

            .vehicle-no {
                font-size: 0.75rem;
            }

            .vehicle-type {
                font-size: 0.6rem;
            }

            .vehicle-trip-info {
                font-size: 0.6rem;
            }

            .fuel-bar-container {
                height: 20px;
                margin: 3px 0;
            }

            .fuel-bar {
                font-size: 0.6rem;
                line-height: 20px;
            }

            .location-btn {
                padding: 6px 8px;
                font-size: 0.7rem;
            }

            .details-btn {
                padding: 6px 8px;
                font-size: 0.7rem;
            }

            .fuel-gauge {
                width: 90px;
                height: 90px;
                margin: 10px auto;
            }

            .gauge-arc {
                border: 3px solid transparent;
                inset: 3px;
            }

            .needle {
                height: 30px;
                width: 2px;
            }

            .center-cap {
                width: 8px;
                height: 8px;
            }

            .fuel-gauge .label {
                font-size: 0.6rem;
            }

            .fuel-gauge .label.e {
                bottom: 8px;
                left: 8px;
            }

            .fuel-gauge .label.f {
                bottom: 8px;
                right: 8px;
            }

            .fuel-gauge .value {
                font-size: 0.75rem;
            }

            .modal-content {
                width: 92%;
                padding: 12px;
                margin: 40% auto;
            }

            .modal-content h3 {
                font-size: 0.95rem;
                margin-bottom: 10px;
            }

            .vehicle-modal-info strong {
                font-size: 0.65rem;
            }

            .vehicle-modal-info span {
                font-size: 0.85rem;
            }
        }

        /* Animated Route Arrow */
        @keyframes moveAlongRoute {
            0% { opacity: 1; }
            100% { opacity: 1; }
        }

        .animated-arrow {
            width: 0;
            height: 0;
            border-left: 12px solid transparent;
            border-right: 12px solid transparent;
            border-bottom: 24px solid var(--primary-color);
            filter: drop-shadow(0 0 2px rgba(93, 93, 255, 0.8)) drop-shadow(0 0 4px rgba(93, 93, 255, 0.6));
            animation: pulse 1.2s infinite;
        }

        @keyframes pulse {
            0%, 100% {
                filter: drop-shadow(0 0 4px rgba(93, 93, 255, 0.8)) drop-shadow(0 0 8px rgba(93, 93, 255, 0.6));
            }
            50% {
                filter: drop-shadow(0 0 8px rgba(93, 93, 255, 1)) drop-shadow(0 0 15px rgba(93, 93, 255, 0.9));
            }
        }

        .arrow-trail {
            opacity: 0.3;
            pointer-events: none;
        }

        /* ===== MODAL STYLES ===== */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background: var(--bg-light-dark);
            margin: 10% auto;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 450px;
            position: relative;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
            animation: slideUp 0.3s ease;
            border: 1px solid var(--border-color);
            max-height: 85vh;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
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

        .modal-content .close {
            position: absolute;
            top: 12px;
            right: 18px;
            font-size: 28px;
            color: var(--text-gray);
            cursor: pointer;
            transition: 0.3s;
            background: none;
            border: none;
        }

        .modal-content .close:hover {
            color: var(--primary-color);
        }

        .modal-content h3 {
            color: var(--primary-color);
            margin: 0 0 20px 0;
            font-size: 1.4rem;
        }

        .vehicle-modal-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 20px;
        }

        .vehicle-modal-info div {
            background: rgba(93, 93, 255, 0.1);
            border-radius: 6px;
            padding: 12px;
            border-left: 4px solid var(--primary-color);
            border: 1px solid var(--border-color);
        }

        .vehicle-modal-info strong {
            display: block;
            font-size: 0.8rem;
            color: var(--text-gray);
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .vehicle-modal-info strong {
            display: block;
            font-size: 0.8rem;
            color: var(--text-gray);
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .vehicle-modal-info span {
            display: block;
            font-size: 1rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .status-good { color: #00ff88; }
        .status-warning { color: #ffb000; }
        .status-danger { color: #ff5064; }

        /* ===== FUEL GAUGE ===== */
        .fuel-gauge {
            position: relative;
            width: 180px;
            height: 180px;
            margin: 20px auto;
            border-radius: 50%;
            background: radial-gradient(circle at center, #0a0a14 60%, #000 100%);
            box-shadow: inset 0 2px 8px rgba(0, 0, 0, 0.8), 0 0 20px rgba(93, 93, 255, 0.2);
        }

        .gauge-arc {
            position: absolute;
            inset: 10px;
            border-radius: 50%;
            border: 8px solid transparent;
            border-top-color: #ff5064;
            border-right-color: #ffb000;
            border-bottom-color: #00ff88;
            transform: rotate(270deg);
        }

        .needle {
            position: absolute;
            bottom: 50%;
            left: 50%;
            width: 4px;
            height: 65px;
            background: var(--primary-color);
            transform-origin: center bottom;
            margin-left: -2px;
            border-radius: 2px;
            transition: transform 0.3s ease;
            box-shadow: 0 0 6px rgba(93, 93, 255, 0.5);
        }

        .center-cap {
            position: absolute;
            width: 20px;
            height: 20px;
            background: var(--primary-color);
            border-radius: 50%;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.5), 0 0 8px rgba(93, 93, 255, 0.3);
        }

        .fuel-gauge .label {
            position: absolute;
            font-weight: 700;
            color: var(--text-light);
            font-size: 0.9rem;
        }

        .fuel-gauge .label.e {
            bottom: 15px;
            left: 15px;
        }

        .fuel-gauge .label.f {
            bottom: 15px;
            right: 15px;
        }

        .fuel-gauge .value {
            position: absolute;
            top: 60%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: var(--primary-color);
            font-weight: 700;
            font-size: 1.2rem;
            text-align: center;
        }

        /* ===== LEAFLET CONTROLS Z-INDEX ===== */
        .leaflet-control {
            z-index: 500 !important;
        }

        .leaflet-top,
        .leaflet-bottom {
            z-index: 500 !important;
        }

        .leaflet-control-zoom {
            z-index: 500 !important;
        }

        .leaflet-routing-container {
            z-index: 500 !important;
        }
    </style>
</head>
<body>
<div class="wrapper">
<?php include("Components/Sidebar.php");?>

<main>
        <div class="content">
            <!-- Map Section (Left 65%) -->
            <div class="map-section">
                <div id="trip-map"></div>
                <div id="map-info">Click on a trip to view its route.</div>
            </div>

            <!-- Panels Container (Right Side) -->
            <div class="panels-container">
                <!-- Active Trips Panel -->
                <div class="panel">
                    <h3 class="panel-title">🔥 Active Trips (<?php echo $inUseCount; ?>)</h3>
                    <div class="panel-list">
                        <?php
                            $deployedVehicles = array_filter($vehicles, function($v) { return $v['status'] === 'deployed'; });
                            if (count($deployedVehicles) > 0) {
                                foreach ($deployedVehicles as $vehicle) {
                                    $fuel = $vehicle['current_fuel'];
                                    $fuel_text = $fuel !== null ? $fuel.' L' : 'N/A';
                                    $fuel_color = getFuelColor($fuel);
                                    
                                    echo "
                                    <div class='vehicle-card' 
                                        data-no='" . htmlspecialchars($vehicle['vehicle_no']) . "'
                                        data-type='" . htmlspecialchars($vehicle['vehicle_type']) . "'
                                        data-status='" . htmlspecialchars($vehicle['status']) . "'
                                        data-fuel='" . htmlspecialchars($fuel_text) . "'
                                        data-fuelval='" . htmlspecialchars($fuel ?? 0) . "'
                                        data-use='" . htmlspecialchars($vehicle['current_use']) . "'
                                        data-destination='" . htmlspecialchars($vehicle['status'] === 'deployed' && isset($vehicle['current_use']) ? substr($vehicle['current_use'], strpos($vehicle['current_use'], 'to ') + 3) : '') . "'
                                        data-driver-name='" . htmlspecialchars($vehicle['trip_info']['driver_name'] ?? 'N/A') . "'
                                        data-purpose='" . htmlspecialchars($vehicle['trip_info']['purpose'] ?? 'N/A') . "'
                                        data-is-deployed='true'>
                                        <div class='vehicle-icon'>" . getVehicleIcon($vehicle['vehicle_type']) . "</div>
                                        <div class='vehicle-card-info'>
                                            <div class='vehicle-no'>" . htmlspecialchars($vehicle['vehicle_no']) . "</div>
                                            <div class='vehicle-type'>" . htmlspecialchars($vehicle['vehicle_type']) . "</div>
                                            <div class='vehicle-trip-info'>" . htmlspecialchars($vehicle['current_use']) . "</div>
                                        </div>
                                        <div class='fuel-bar-container'>
                                            <div class='fuel-bar' style='width:" . ($fuel ? min($fuel, 100) : 0) . "%; background:" . $fuel_color . ";'>" . $fuel_text . "</div>
                                        </div>
                                        <button class='location-btn' data-vehicle-no='" . htmlspecialchars($vehicle['vehicle_no']) . "' style='background-color: #B22222; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 0.9rem; transition: background-color 0.2s; white-space: nowrap; height: fit-content;' onmouseover=\"this.style.backgroundColor='#8b1a1a';\" onmouseout=\"this.style.backgroundColor='#B22222';\"><i class='fas fa-map-marker-alt'></i></button>
                                        <button class='details-btn' style='background-color: #5d5dff; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 0.9rem; transition: background-color 0.2s; white-space: nowrap; height: fit-content;' onmouseover=\"this.style.backgroundColor='#7070ff';\" onmouseout=\"this.style.backgroundColor='#5d5dff';\"><i class='fas fa-info-circle'></i></button>
                                    </div>
                                    ";
                                }
                            } else {
                                echo "<div class='empty-panel'>No active trips today</div>";
                            }
                        ?>
                    </div>
                </div>

                <!-- Available Vehicles Panel -->
                <div class="panel">
                    <h3 class="panel-title">✅ Available Vehicles (<?php echo $availableCount; ?>)</h3>
                    <div class="panel-list">
                        <?php
                            $availableVehicles = array_filter($vehicles, function($v) { return $v['status'] === 'available'; });
                            if (count($availableVehicles) > 0) {
                                foreach ($availableVehicles as $vehicle) {
                                    $fuel = $vehicle['current_fuel'];
                                    $fuel_text = $fuel !== null ? $fuel.' L' : 'N/A';
                                    $fuel_color = getFuelColor($fuel);
                                    $statusIcon = $fuel > 0 ? '✅' : '🔴';
                                    
                                    echo "
                                    <div class='vehicle-card' 
                                        data-no='" . htmlspecialchars($vehicle['vehicle_no']) . "'
                                        data-type='" . htmlspecialchars($vehicle['vehicle_type']) . "'
                                        data-status='" . htmlspecialchars($vehicle['status']) . "'
                                        data-fuel='" . htmlspecialchars($fuel_text) . "'
                                        data-fuelval='" . htmlspecialchars($fuel ?? 0) . "'
                                        data-is-deployed='false'>
                                        <div class='vehicle-icon'>" . $statusIcon . "</div>
                                        <div class='vehicle-card-info'>
                                            <div class='vehicle-no'>" . htmlspecialchars($vehicle['vehicle_no']) . "</div>
                                            <div class='vehicle-type'>" . htmlspecialchars($vehicle['vehicle_type']) . "</div>
                                            <div class='vehicle-trip-info'>" . ($fuel > 0 ? 'Ready' : 'No Fuel') . "</div>
                                        </div>
                                        <div class='fuel-bar-container'>
                                            <div class='fuel-bar' style='width:" . ($fuel ? min($fuel, 100) : 0) . "%; background:" . $fuel_color . ";'>" . $fuel_text . "</div>
                                        </div>
                                        <button class='details-btn' style='background-color: #5d5dff; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 0.9rem; transition: background-color 0.2s; white-space: nowrap; height: fit-content;' onmouseover=\"this.style.backgroundColor='#7070ff';\" onmouseout=\"this.style.backgroundColor='#5d5dff';\"><i class='fas fa-info-circle'></i>Vehicle details</button>
                                    </div>
                                    ";
                                }
                            } else {
                                echo "<div class='empty-panel'>All vehicles in use!</div>";
                            }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
<div id="vehicleModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h3 id="modalVehicle"></h3>
        
        <div class="vehicle-modal-info">
            <div><strong>Type:</strong> <br><span id="modalType"></span></div>
            <div><strong>Status:</strong> <br><span id="modalStatus"></span></div>
            <div><strong>Driver:</strong> <br><span id="modalDriver"></span></div>
            <div><strong>Fuel:</strong> <br><span id="modalFuel"></span></div>
        </div>

        <div style="margin-top: 15px; padding: 12px; background: rgba(93, 93, 255, 0.1); border-radius: 6px; border: 1px solid var(--border-color);">
            <strong style="color: var(--text-gray); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">Destination</strong>
            <p id="modalDestination" style="margin: 8px 0 0 0; color: var(--primary-color); font-weight: 600;"></p>
        </div>

        <div style="margin-top: 12px; padding: 12px; background: rgba(93, 93, 255, 0.1); border-radius: 6px; border: 1px solid var(--border-color);">
            <strong style="color: var(--text-gray); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">Purpose</strong>
            <p id="modalPurpose" style="margin: 8px 0 0 0; color: var(--primary-color); font-weight: 600;"></p>
        </div>

        <div style="margin-top: 15px;">
            <strong style="color: var(--text-gray); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 8px;">Fuel Level</strong>
            <div class="fuel-bar-container" style="height: 30px; border-radius: 6px;">
                <div class="fuel-bar" id="modalFuelBar" style="width: 0%; background: #28a745; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600;"></div>
            </div>
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

<script>
    // Sidebar dropdown toggle
    document.querySelectorAll('.dropdown').forEach(item => {
        item.addEventListener('click', function(e) {
            if (e.target.closest('.submenu')) return;
            this.classList.toggle('active');
            document.querySelectorAll('.dropdown').forEach(other => {
                if (other !== this) other.classList.remove('active');
            });
        });
    });

    // ===== LEAFLET MAP INITIALIZATION =====
    var officeCoords = [<?php echo $officeLat; ?>, <?php echo $officeLng; ?>]; 
    var map = L.map('trip-map').setView(officeCoords, 15);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    var officeMarker = L.marker(officeCoords).addTo(map)
        .bindPopup('Maasin City Fire Station').openPopup();

    var routingControl;

    // Animated marker for route
    let animatedMarker = null;
    let animationInterval = null;

    function createAnimatedArrow() {
        // Create arrow head using CSS triangle (24px wide, 24px tall)
        const arrowDiv = document.createElement('div');
        arrowDiv.className = 'animated-arrow';
        
        return L.divIcon({
            html: arrowDiv.outerHTML,
            iconSize: [24, 24],
            iconAnchor: [12, 24],  // Anchor at tip of arrow pointing down
            className: 'animated-marker'
        });
    }

    function animateArrowAlongRoute(coordinates, duration) {
        // Clear previous animation
        if (animationInterval) clearInterval(animationInterval);
        if (animatedMarker) map.removeLayer(animatedMarker);

        let currentIndex = 0;
        const totalPoints = coordinates.length;
        const animationStep = duration / totalPoints;

        // Create animated marker at start
        animatedMarker = L.marker(coordinates[0], {
            icon: createAnimatedArrow(),
            zIndexOffset: 1000
        }).addTo(map);

        animationInterval = setInterval(() => {
            currentIndex++;
            
            if (currentIndex >= totalPoints) {
                clearInterval(animationInterval);
                if (animatedMarker) map.removeLayer(animatedMarker);
                return;
            }

            // Update marker position
            const newPos = [coordinates[currentIndex].lat, coordinates[currentIndex].lng];
            animatedMarker.setLatLng(newPos);

            // Rotate arrow based on direction
            const prevPos = coordinates[currentIndex - 1];
            const angle = Math.atan2(
                newPos[0] - prevPos[0],
                newPos[1] - prevPos[1]
            ) * (180 / Math.PI);

            const arrowElement = document.querySelector('.animated-marker');
            if (arrowElement) {
                arrowElement.style.transform = `rotate(${angle}deg)`;
            }
        }, animationStep * 1000); // Convert to milliseconds
    }

    // Load locations from Maps.php database
    let maasinLocations = {};
    let allLocationsData = []; // Store full location data
    async function loadLocationsFromDatabase() {
        try {
            const response = await fetch('Maps.php?api=get_all', { method: 'POST' });
            const data = await response.json();
            if (data.success && data.locations) {
                allLocationsData = data.locations;
                // Create lookup object by name
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
    
    // When clicking a trip card, calculate route
    loadLocationsFromDatabase(); // Load locations from database
    
    function showVehicleRoute(card) {
        // Remove active class from all cards
        document.querySelectorAll('.vehicle-card').forEach(c => c.classList.remove('active'));
        card.classList.add('active');
        
        let destsString = card.dataset.dest || '';
        let driver = card.dataset.driver || '';

        // Clear existing route
        if (routingControl) {
            try { 
                map.removeControl(routingControl); 
            } catch(e) { 
                console.warn(e); 
            }
            routingControl = null;
        }

        // Parse destinations
        let destinationNames = destsString.split(';').map(s => s.trim()).filter(Boolean);
        if (destinationNames.length === 0) {
            document.getElementById('map-info').textContent = 'No destination specified for this trip.';
            return;
        }

        document.getElementById('map-info').innerHTML = `<strong>${driver}:</strong> Looking up destination(s)...`;

        // Geocode all destinations
        let geocodePromises = destinationNames.map(n => geocodeAddress(n));
        Promise.all(geocodePromises).then(geocoded => {
            // Filter out failed geocodes
            let valid = geocoded
                .map((g, i) => ({ name: destinationNames[i], loc: g }))
                .filter(x => x.loc);

            if (valid.length === 0) {
                document.getElementById('map-info').innerHTML = `<strong>Could not geocode destination(s).</strong> Try editing the destination name.`;
                return;
            }

            // Build route: office -> destinations
            let waypoints = [L.latLng(officeCoords[0], officeCoords[1])]; 
            valid.forEach(v => waypoints.push(L.latLng(v.loc.lat, v.loc.lon)));

            routingControl = L.Routing.control({
                waypoints: waypoints,
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
                        }).bindPopup('<strong>🚒 Fire Station (Start)</strong><br>Maasin City Fire Station');
                    }
                    
                    if (i === waypoints.length - 1) {
                        return L.marker(wp.latLng, {
                            icon: L.icon({
                                iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-green.png', 
                                iconSize: [25, 41],
                                iconAnchor: [12, 41]
                            })
                        }).bindPopup(`<strong>📍 Destination (End)</strong><br>${valid[i-1].name}`);
                    }
                    
                    return L.marker(wp.latLng, {
                        icon: L.icon({
                            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-yellow.png',
                            iconSize: [25, 41],
                            iconAnchor: [12, 41]
                        })
                    }).bindPopup(`<strong>📍 Waypoint</strong><br>${valid[i-1].name}`);
                }
            }).addTo(map);

            // Update info
            routingControl.on('routesfound', function(e) {
                let route = e.routes[0];
                let distanceKm = route.summary.totalDistance / 1000;
                let durationMin = Math.round(route.summary.totalTime / 60);
                let durationSec = route.summary.totalTime; // Duration in seconds
                
                // Build location details
                let locationDetails = valid.map((v, idx) => {
                    let detail = v.name;
                    if (v.matchedName) detail += ` (matched: ${v.matchedName})`;
                    return detail;
                }).join(' → ');
                
                document.getElementById('map-info').innerHTML =
                    `<strong>🚗 ${driver}</strong><br>
                    <strong>📍 Route:</strong> Maasin City Fire Station → ${locationDetails}<br>
                    <strong>📏 Distance:</strong> ${distanceKm.toFixed(2)} km | <strong>⏱️ Time:</strong> ${durationMin} min`;

                try {
                    const coords = e.routes[0].coordinates.map(c => [c.lat, c.lng]);
                    map.fitBounds(coords, { padding: [50, 50] });
                    
                    // Start animated arrow along the route
                    animateArrowAlongRoute(coords, durationSec);
                } catch (err) {
                    console.warn('Could not fit bounds:', err);
                }
            });

            routingControl.on('routingerror', function() {
                document.getElementById('map-info').innerHTML = 
                    `<strong>❌ Error:</strong> Could not calculate route. Try simpler destination names.`;
            });
        });
    }
    
    function showVehicleRouteFromPanel(card) {
        // Get destination from card data
        let destination = card.dataset.destination || '';
        let vehicleNo = card.dataset.no || '';

        // Clear existing route
        if (routingControl) {
            try { 
                map.removeControl(routingControl); 
            } catch(e) { 
                console.warn(e); 
            }
            routingControl = null;
        }

        if (!destination) {
            document.getElementById('map-info').textContent = 'No destination specified for this vehicle.';
            return;
        }

        document.getElementById('map-info').innerHTML = `<strong>${vehicleNo}:</strong> Looking up destination...`;

        // Geocode destination
        geocodeAddress(destination).then(geocoded => {
            if (!geocoded) {
                document.getElementById('map-info').innerHTML = `<strong>Could not geocode destination.</strong> Try simpler destination names.`;
                return;
            }

            // Build route: office -> destination
            let waypoints = [L.latLng(officeCoords[0], officeCoords[1])]; 
            waypoints.push(L.latLng(geocoded.lat, geocoded.lon));

            routingControl = L.Routing.control({
                waypoints: waypoints,
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
                        }).bindPopup('<strong>🚒 Fire Station (Start)</strong><br>Maasin City Fire Station');
                    }
                    
                    return L.marker(wp.latLng, {
                        icon: L.icon({
                            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-green.png', 
                            iconSize: [25, 41],
                            iconAnchor: [12, 41]
                        })
                    }).bindPopup(`<strong>📍 Destination</strong><br>${destination}`);
                }
            }).addTo(map);

            // Update info
            routingControl.on('routesfound', function(e) {
                let route = e.routes[0];
                let distanceKm = route.summary.totalDistance / 1000;
                let durationMin = Math.round(route.summary.totalTime / 60);
                let durationSec = route.summary.totalTime;
                
                document.getElementById('map-info').innerHTML =
                    `<strong>🚗 ${vehicleNo}</strong><br>
                    <strong>📍 Route:</strong> Maasin City Fire Station → ${destination}<br>
                    <strong>📏 Distance:</strong> ${distanceKm.toFixed(2)} km | <strong>⏱️ Time:</strong> ${durationMin} min`;

                try {
                    const coords = e.routes[0].coordinates.map(c => [c.lat, c.lng]);
                    map.fitBounds(coords, { padding: [50, 50] });
                    animateArrowAlongRoute(coords, durationSec);
                } catch (err) {
                    console.warn('Could not fit bounds:', err);
                }
            });

            routingControl.on('routingerror', function() {
                document.getElementById('map-info').innerHTML = 
                    `<strong>❌ Error:</strong> Could not calculate route. Try simpler destination names.`;
            });
        });
    }
    
    // Click handler for the location button
    document.querySelectorAll('.location-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const card = btn.closest('.vehicle-card');
            if (card && card.dataset.isDeployed === 'true' && card.dataset.destination) {
                showVehicleRouteFromPanel(card);
            }
        });
    });

    // Click handler for the details button
    document.querySelectorAll('.details-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const card = btn.closest('.vehicle-card');
            if (card) {
                openVehicleModal(card);
            }
        });
    });

    // ===== VEHICLE MODAL FUNCTIONALITY =====
    const modal = document.getElementById("vehicleModal");
    const closeBtn = document.querySelector(".modal-content .close");
    const needle = document.getElementById("needle");
    const fuelValue = document.getElementById("fuelValue");

    function updateFuelGauge(level) {
        const angle = -135 + (level * 2.7);
        needle.style.transform = `rotate(${angle}deg)`;
        fuelValue.textContent = `${level}%`;
    }

    // Click handler to open vehicle modal
    function openVehicleModal(card) {
        const fuelVal = parseFloat(card.dataset.fuelval) || 0;
        const fuelText = card.dataset.fuel || 'N/A';
        const criticalFuelPercent = 25;
        
        // Get fuel color based on level
        function getFuelColorFromLevel(level) {
            if (level === null) return '#6c757d';
            if (level >= 75) return '#28a745';
            if (level >= 50) return '#ffc107';
            if (level >= 25) return '#fd7e14';
            return '#dc3545';
        }
        
        const fuelColor = getFuelColorFromLevel(fuelVal);

        document.getElementById("modalVehicle").innerText = card.dataset.no || "Unknown";
        document.getElementById("modalType").innerText = card.dataset.type || "Unknown";
        document.getElementById("modalStatus").innerText = card.dataset.status ? 
            card.dataset.status.charAt(0).toUpperCase() + card.dataset.status.slice(1) : "Unknown";
        document.getElementById("modalFuel").innerText = fuelText;
        document.getElementById("modalDriver").innerText = card.dataset.driverName || "No Driver Assigned";
        document.getElementById("modalDestination").innerText = card.dataset.destination || "Vehicle on standby";
        document.getElementById("modalPurpose").innerText = card.dataset.purpose || "Vehicle on standby";
        
        // Update fuel bar
        const fuelBarElement = document.getElementById("modalFuelBar");
        const fuelBarWidth = Math.min(fuelVal, 100);
        fuelBarElement.style.width = fuelBarWidth + "%";
        fuelBarElement.style.backgroundColor = fuelColor;
        fuelBarElement.textContent = fuelText;

        updateFuelGauge(Math.min(fuelVal, 100));
        modal.style.display = "block";
    }

    closeBtn.onclick = () => modal.style.display = "none";
    window.onclick = (e) => { 
        if (e.target === modal) modal.style.display = "none"; 
    };
</script>

</body>
</html>
