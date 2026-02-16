<?php
session_start();
include("db_connect.php");

$message = '';
$error = '';

// Helper: get current user's driver_id
$current_user_id = $_SESSION['user_id'] ?? null;
$current_role = $_SESSION['role'] ?? '';
$current_driver_id = null;
if ($current_user_id) {
    try {
        $u = $pdo->prepare("SELECT driver_id FROM users WHERE id = ? LIMIT 1");
        $u->execute([$current_user_id]);
        $ur = $u->fetch();
        $current_driver_id = isset($ur['driver_id']) && $ur['driver_id'] !== null && $ur['driver_id'] !== '' ? (int)$ur['driver_id'] : null;
    } catch (Exception $e) {
        $current_driver_id = null;
    }
}

// Handle delete ticket
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_ticket'])) {
    $control_no = trim($_POST['control_no'] ?? '');
    if ($control_no === '') {
        $error = 'Please provide a control number.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM trip_tickets WHERE control_no = ? LIMIT 1");
            $stmt->execute([$control_no]);
            $ticket = $stmt->fetch();
            if (!$ticket) {
                $error = 'Ticket not found.';
            } else {
                $ticket_driver_id = isset($ticket['driver_id']) && $ticket['driver_id'] !== null ? (int)$ticket['driver_id'] : null;
                // Allow: admins always, any logged-in driver for unassigned tickets or their own tickets
                $canDelete = ($current_role === 'admin') || 
                            ($current_user_id !== null && $current_role === 'user' && 
                             ($ticket_driver_id === $current_driver_id || $ticket_driver_id === null)) ||
                            ($current_user_id !== null && $current_role === 'chief');
                if (!$canDelete) {
                    $error = 'You are not authorized to delete this ticket.';
                } else {
                    $delete = $pdo->prepare("DELETE FROM trip_tickets WHERE control_no = ?");
                    $delete->execute([$control_no]);
                    $message = 'Ticket deleted successfully. Redirecting...';
                    header("refresh:2;url=Pending_reports.php");
                    $ticket = null;
                }
            }
        } catch (Exception $e) { $error = 'Database error: ' . $e->getMessage(); }
    }
}

// Handle update from driver/admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_ticket'])) {
    $control_no = trim($_POST['control_no'] ?? '');
    if ($control_no === '') {
        $error = 'Please provide a control number.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM trip_tickets WHERE control_no = ? LIMIT 1");
            $stmt->execute([$control_no]);
            $ticket = $stmt->fetch();
            if (!$ticket) {
                $error = 'Ticket not found.';
            } else {
                $ticket_driver_id = isset($ticket['driver_id']) && $ticket['driver_id'] !== null ? (int)$ticket['driver_id'] : null;
                // Allow: admins always, any logged-in driver for unassigned tickets or their own tickets
                $canEdit = ($current_role === 'admin') || 
                          ($current_user_id !== null && $current_role === 'user' && 
                           ($ticket_driver_id === $current_driver_id || $ticket_driver_id === null)) ||
                          ($current_user_id !== null && $current_role === 'chief');
                if (!$canEdit) {
                    $error = 'You are not authorized to update this ticket.';
                } else {
                    // For non-admin drivers, only allow updating driver-section fields
                    $isAdminUser = ($current_role === 'admin');
                    
                    // Admin can update all fields, drivers can only update driver-section fields
                    $ticket_date = $isAdminUser && isset($_POST['ticket_date']) && $_POST['ticket_date'] !== '' ? $_POST['ticket_date'] : ($ticket['ticket_date'] ?? null);
                    $new_driver_id = $isAdminUser && isset($_POST['driver_id']) && $_POST['driver_id'] !== '' ? (int)$_POST['driver_id'] : ($ticket['driver_id'] ?? null);
                    $vehicle_plate_no = $isAdminUser ? ($_POST['vehicle_no'] ?? ($ticket['vehicle_plate_no'] ?? null)) : ($ticket['vehicle_plate_no'] ?? null);
                    $authorized_passenger = $isAdminUser ? ($_POST['authorized_passenger'] ?? ($ticket['authorized_passenger'] ?? null)) : ($ticket['authorized_passenger'] ?? null);
                    $places_to_visit = $isAdminUser ? ($_POST['places_to_visit'] ?? ($ticket['places_to_visit'] ?? null)) : ($ticket['places_to_visit'] ?? null);
                    $purpose = $isAdminUser ? ($_POST['purpose'] ?? ($ticket['purpose'] ?? null)) : ($ticket['purpose'] ?? null);

                    // Driver-section fields - all users can update these
                    $dep_office_time = $_POST['dep_office_time'] ?? ($ticket['dep_office_time'] ?? null);
                    $arr_location_time = $_POST['arr_location_time'] ?? ($ticket['arr_location_time'] ?? null);
                    $dep_location_time = $_POST['dep_location_time'] ?? ($ticket['dep_location_time'] ?? null);
                    $arr_office_time = $_POST['arr_office_time'] ?? ($ticket['arr_office_time'] ?? null);
                    $approx_distance = $_POST['approx_distance'] !== '' ? $_POST['approx_distance'] : ($ticket['approx_distance'] ?? null);
                    $speedometer_start = $_POST['speedometer_start'] !== '' ? $_POST['speedometer_start'] : ($ticket['speedometer_start'] ?? null);
                    $speedometer_end = $_POST['speedometer_end'] !== '' ? $_POST['speedometer_end'] : ($ticket['speedometer_end'] ?? null);
                    $gas_balance_start = $_POST['gas_balance_start'] !== '' ? $_POST['gas_balance_start'] : ($ticket['gas_balance_start'] ?? null);
                    $gas_issued_office = $_POST['gas_issued_office'] !== '' ? $_POST['gas_issued_office'] : ($ticket['gas_issued_office'] ?? null);
                    $gas_added_trip = $_POST['gas_added_trip'] !== '' ? $_POST['gas_added_trip'] : ($ticket['gas_added_trip'] ?? null);
                    $gas_used_trip = $_POST['gas_used_trip'] !== '' ? $_POST['gas_used_trip'] : ($ticket['gas_used_trip'] ?? null);

                    $gas_total = null;
                    if ($gas_balance_start !== null || $gas_issued_office !== null || $gas_added_trip !== null) {
                        $b = floatval($gas_balance_start ?: 0);
                        $i = floatval($gas_issued_office ?: 0);
                        $a = floatval($gas_added_trip ?: 0);
                        $gas_total = $b + $i + $a;
                    }
                    $end = ($gas_used_trip !== null) ? (floatval($gas_total ?: 0) - floatval($gas_used_trip)) : ($ticket['gas_balance_end'] ?? null);

                    // Passenger fields - all users can update these
                    $passenger_1_name = $_POST['passenger_1_name'] ?? ($ticket['passenger_1_name'] ?? null);
                    $passenger_1_date = $_POST['passenger_1_date'] ?? ($ticket['passenger_1_date'] ?? null);
                    $passenger_2_name = $_POST['passenger_2_name'] ?? ($ticket['passenger_2_name'] ?? null);
                    $passenger_2_date = $_POST['passenger_2_date'] ?? ($ticket['passenger_2_date'] ?? null);
                    $passenger_3_name = $_POST['passenger_3_name'] ?? ($ticket['passenger_3_name'] ?? null);
                    $passenger_3_date = $_POST['passenger_3_date'] ?? ($ticket['passenger_3_date'] ?? null);

                    $update = $pdo->prepare("UPDATE trip_tickets SET ticket_date = :ticket_date, driver_id = :driver_id, vehicle_plate_no = :vehicle_plate_no, authorized_passenger = :authorized_passenger, places_to_visit = :places_to_visit, purpose = :purpose, dep_office_time = :dep_office_time, arr_location_time = :arr_location_time, dep_location_time = :dep_location_time, arr_office_time = :arr_office_time, approx_distance = :approx_distance, speedometer_start = :speedometer_start, speedometer_end = :speedometer_end, gas_balance_start = :gas_balance_start, gas_issued_office = :gas_issued_office, gas_added_trip = :gas_added_trip, gas_total = :gas_total, gas_used_trip = :gas_used_trip, gas_balance_end = :gas_balance_end, gear_oil_issued = :gear_oil_issued, lub_oil_issued = :lub_oil_issued, grease_issued = :grease_issued, remarks = :remarks, passenger_1_name = :passenger_1_name, passenger_1_date = :passenger_1_date, passenger_2_name = :passenger_2_name, passenger_2_date = :passenger_2_date, passenger_3_name = :passenger_3_name, passenger_3_date = :passenger_3_date WHERE control_no = :control_no");

                    $update->execute([
                        ':ticket_date' => $ticket_date, ':driver_id' => $new_driver_id, ':vehicle_plate_no' => $vehicle_plate_no,
                        ':authorized_passenger' => $authorized_passenger, ':places_to_visit' => $places_to_visit, ':purpose' => $purpose,
                        ':dep_office_time' => $dep_office_time, ':arr_location_time' => $arr_location_time, ':dep_location_time' => $dep_location_time,
                        ':arr_office_time' => $arr_office_time, ':approx_distance' => $approx_distance, ':speedometer_start' => $speedometer_start,
                        ':speedometer_end' => $speedometer_end, ':gas_balance_start' => $gas_balance_start, ':gas_issued_office' => $gas_issued_office,
                        ':gas_added_trip' => $gas_added_trip, ':gas_total' => $gas_total, ':gas_used_trip' => $gas_used_trip,
                        ':gas_balance_end' => $end, ':gear_oil_issued' => $_POST['gear_oil_issued'] ?? null,
                        ':lub_oil_issued' => $_POST['lub_oil_issued'] ?? null, ':grease_issued' => $_POST['grease_issued'] ?? null,
                        ':remarks' => $_POST['remarks'] ?? null, ':passenger_1_name' => $passenger_1_name,
                        ':passenger_1_date' => $passenger_1_date, ':passenger_2_name' => $passenger_2_name,
                        ':passenger_2_date' => $passenger_2_date, ':passenger_3_name' => $passenger_3_name,
                        ':passenger_3_date' => $passenger_3_date, ':control_no' => $control_no
                    ]);
                    $message = 'Ticket updated successfully.';
                }
            }
        } catch (Exception $e) { $error = 'Database error: ' . $e->getMessage(); }
    }
}

// Handle search
$ticket = null;
$search = trim($_GET['search'] ?? '');

// Check if coming from edit button with ticket ID
$ticket_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

// If ticket ID is provided, fetch by ID
if ($ticket_id) {
    try {
        $s = $pdo->prepare("SELECT tt.*, d.full_name as driver_name FROM trip_tickets tt LEFT JOIN drivers d ON tt.driver_id = d.driver_id WHERE tt.id = ? LIMIT 1");
        $s->execute([$ticket_id]);
        $ticket = $s->fetch();
        if ($ticket) {
            $search = $ticket['control_no']; // Set search to control_no for display
        }
    } catch (Exception $e) { $error = 'Database error: ' . $e->getMessage(); }
} elseif ($search !== '') {
    try {
        $s = $pdo->prepare("SELECT tt.*, d.full_name as driver_name FROM trip_tickets tt LEFT JOIN drivers d ON tt.driver_id = d.driver_id WHERE tt.control_no = ? LIMIT 1");
        $s->execute([$search]);
        $ticket = $s->fetch();
    } catch (Exception $e) { $error = 'Database error: ' . $e->getMessage(); }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BFP - Trip Ticket System (Reports)</title>
    <style>
    :root { 
        --bfp-red: #B22222; 
        --sidebar-bg: #1e1e2d;
        --dark-bg: #0f0f1a;
        --accent-blue: #5d5dff;
        --accent-blue-light: rgba(93, 93, 255, 0.08);
        --text-primary: #e8e9ef;
        --text-secondary: #a2a2c2;
        --border-light: rgba(255, 255, 255, 0.05);
        --active-gradient: linear-gradient(90deg, rgba(80, 80, 255, 0.15) 0%, rgba(80, 80, 255, 0) 100%);
    }
    
    body { 
        margin: 0; 
        font-family: 'Segoe UI', Arial, sans-serif; 
        background-color: var(--dark-bg); 
        display: flex; 
        flex-direction: column; 
        height: 100vh;
        color: var(--text-primary);
    }
    
    header { display: none; }
    .wrapper { display: flex; flex: 1; overflow: hidden; }
    main { display: flex; flex: 1; padding: 20px; overflow: hidden; gap: 15px; }
    .form-container { display: flex; width: 100%; gap: 0; transition: all 0.5s ease; overflow: hidden; }
    
    /* FORM PANEL: Default 100% width */
    .form-panel { 
        flex: 1 1 100%; 
        max-width: 100%; 
        background: var(--sidebar-bg); 
        padding: 25px; 
        border-radius: 12px; 
        border: 1px solid var(--border-light);
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3); 
        overflow-y: auto; 
        transition: all 0.5s ease; 
    }
    
    /* PREVIEW PANEL: Hidden by default */
    .preview-panel { 
        flex: 0 0 0%; 
        max-width: 0%; 
        opacity: 0; 
        overflow: hidden; 
        pointer-events: none; 
        background: var(--sidebar-bg);
        border: 1px solid var(--border-light);
        border-radius: 12px;
        transition: all 0.5s ease; 
    }

    /* ACTIVE STATE: Form 40% | Preview 60% with SIDE-TO-SIDE & UP-AND-DOWN SLIDERS */
    .form-container.preview-active .form-panel { 
        flex: 0 0 40%; 
        max-width: 40%; 
        border-radius: 12px 0 0 12px; 
    }
    
    .form-container.preview-active .preview-panel { 
        flex: 0 0 60%; 
        max-width: 60%; 
        opacity: 1; 
        pointer-events: auto; 
        padding-right: 20px; 
        border-radius: 0 12px 12px 0; 
        display: block; 
        overflow-x: auto; 
        overflow-y: auto; 
    }

    .form-section-title { 
        background: var(--accent-blue-light); 
        padding: 12px 15px; 
        border-left: 4px solid var(--accent-blue);
        margin: 25px 0 15px 0; 
        font-weight: bold;
        border-radius: 8px;
        color: var(--accent-blue);
        text-transform: uppercase;
        font-size: 0.8rem;
        letter-spacing: 0.5px;
    }
    
    label { 
        display: block; 
        font-size: 0.8rem; 
        font-weight: 600; 
        margin-bottom: 6px; 
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }
    
    input, select { 
        width: 100%; 
        padding: 10px 12px; 
        margin-bottom: 12px; 
        border: 1px solid var(--border-light);
        border-radius: 8px; 
        box-sizing: border-box;
        background: rgba(255, 255, 255, 0.03);
        color: var(--text-primary);
        transition: all 0.3s;
    }
    
    input:hover, select:hover {
        border-color: var(--accent-blue);
        background: rgba(93, 93, 255, 0.06);
    }
    
    input:focus, select:focus {
        outline: none;
        border-color: var(--accent-blue);
        background: rgba(93, 93, 255, 0.1);
        box-shadow: 0 0 0 3px rgba(93, 93, 255, 0.1);
    }
    
    input:disabled, select:disabled {
        background: rgba(255, 255, 255, 0.02);
        color: var(--text-secondary);
        cursor: not-allowed;
    }
    
    .btn { 
        padding: 11px 24px; 
        border: none; 
        border-radius: 8px; 
        cursor: pointer; 
        font-weight: 600; 
        background: var(--bfp-red); 
        color: white;
        transition: all 0.3s;
        text-transform: uppercase;
        font-size: 0.85rem;
        letter-spacing: 0.5px;
    }
    
    .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 16px rgba(178, 34, 34, 0.3);
    }
    
    .btn:active {
        transform: translateY(0);
    }
    
    .btn-preview-toggle { 
        background: var(--accent-blue); 
        margin-top: 15px; 
        width: 100%; 
        transition: all 0.3s; 
    }
    
    .btn-preview-toggle:hover {
        background: #6f6fff;
    }
    
    .btn-preview-toggle.active { 
        background: #28a745; 
    }

    /* Ticket Document Styling: min-width triggers horizontal scroll */
    .ticket-page { 
        width: 8.5in; 
        min-width: 8.5in; 
        background: white; 
        padding: 0.5in; 
        box-shadow: 0 0 15px rgba(0, 0, 0, 0.5); 
        font-family: "Times New Roman", serif; 
        font-size: 10.5pt; 
        color: black; 
        line-height: 1.15; 
        position: relative; 
        transform-origin: top center; 
        margin: 0 auto; 
    }
    
    .underline { 
        border-bottom: 1px solid black; 
        display: inline-block; 
        min-width: 100px; 
        padding: 0 5px; 
        text-align: center; 
    }
    
    .dotted { 
        border-bottom: 1px solid black; 
        flex: 1; 
        margin-left: 5px; 
        min-height: 1.1em; 
        padding-left: 5px; 
    }
    
    .line-item { 
        display: flex; 
        align-items: flex-end; 
        margin-bottom: 3px; 
    }
    
    .indent { 
        margin-left: 25px; 
    }
    
    .appendix { 
        position: absolute; 
        font-weight: bold; 
        right: 0.5in; 
        top: 0.4in; 
    }
    
    .control-block { 
        text-align: right; 
        margin-top: -10px; 
    }
    
    .doc-header { 
        text-align: center; 
        margin-bottom: 10px; 
        line-height: 1.3; 
    }
    
    .fire-station-name { 
        text-decoration: underline; 
        font-weight: bold; 
    }
    
    .gas-table { 
        width: 100%; 
        border-collapse: collapse; 
        margin: 5px 0; 
    }
    
    .sig-row { 
        display: flex; 
        justify-content: flex-end; 
    }
    
    .sig-block { 
        text-align: center; 
    }
    
    .sig-box { 
        width: 250px; 
        border-top: 1px solid black; 
        text-align: center; 
        padding-top: 5px; 
        font-weight: bold; 
        margin: 25px auto 5px auto; 
    }
    
    .sig-driver { 
        width: 250px; 
        margin: 0; 
        padding: 0 0 0 calc(50% - 125px); 
        text-align: center; 
        text-decoration: none; 
        font-weight: bold; 
        text-underline-offset: 4px; 
        float: left; 
        clear: both; 
    }
    
    .Sig-driver { 
        width: 250px; 
        margin: 20px auto 5px auto; 
        text-align: center; 
        text-decoration: none; 
        font-weight: bold; 
        text-underline-offset: 4px; 
    }

    /* Scrollbar Styling */
    .form-panel::-webkit-scrollbar,
    .preview-panel::-webkit-scrollbar {
        width: 8px;
    }

    .form-panel::-webkit-scrollbar-track,
    .preview-panel::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.02);
    }

    .form-panel::-webkit-scrollbar-thumb,
    .preview-panel::-webkit-scrollbar-thumb {
        background: var(--accent-blue);
        border-radius: 4px;
    }

    .form-panel::-webkit-scrollbar-thumb:hover,
    .preview-panel::-webkit-scrollbar-thumb:hover {
        background: #6f6fff;
    }

    @media print {
        header, .sidebar, .form-panel, .btn-preview-toggle { display: none !important; }
        .preview-panel { flex: 1 1 100% !important; max-width: 100% !important; opacity: 1 !important; background: white; overflow: visible !important; }
        .ticket-page { transform: scale(1); box-shadow: none; margin: 0; min-width: auto; }
    }
</style>
</head>
<body>
    <div class="wrapper">
        <?php include("Components/sidebar.php");?>
        <main>
            <div class="form-container" id="mainContainer">
                <div class="form-panel">
                    <?php if ($message): ?><div style="color:green; margin-bottom:10px;"><?php echo $message; ?></div><?php endif; ?>
                    <?php if ($error): ?><div style="color:red; margin-bottom:10px;"><?php echo $error; ?></div><?php endif; ?>

                    <form method="GET" style="display: flex; gap: 10px; align-items: flex-end;">
                        <div style="flex: 1;">
                            <label>Control No.</label>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Enter control number">
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <button type="submit" class="btn">Search</button>
                            <a href="Pending_reports.php" class="btn" style="text-decoration: none; display: inline-flex; align-items: center; justify-content: center;">← Return</a>
                        </div>
                    </form>

                    <?php if ($ticket): 
                        $ticket_driver_id = isset($ticket['driver_id']) ? (int)$ticket['driver_id'] : null;
                        $isAssignedDriver = ($current_driver_id !== null && $ticket_driver_id === $current_driver_id);
                        $canEdit = ($current_role === 'admin') || ($current_role === 'user') || $isAssignedDriver;
                    ?>
                        <button type="button" id="previewToggleBtn" class="btn btn-preview-toggle">📋 Show Preview</button>
                        <hr>
                        <form method="POST">
                            <input type="hidden" name="control_no" value="<?php echo htmlspecialchars($ticket['control_no']); ?>">
                            
                            <div class="form-section-title">ADMINISTRATIVE SECTION</div>
                            <label>Date</label>
                            <input type="date" name="ticket_date" class="editable-field admin-only-field" value="<?php echo htmlspecialchars($ticket['ticket_date'] ?? ''); ?>" disabled>
                            
                            <label>Driver Name</label>
                            <input type="text" name="driver_name" class="editable-field admin-only-field" value="<?php echo htmlspecialchars($ticket['driver_name'] ?? ''); ?>" disabled>

                            <label>Vehicle Plate No.</label>
                            <input type="text" name="vehicle_no" class="editable-field admin-only-field" value="<?php echo htmlspecialchars($ticket['vehicle_plate_no'] ?? ''); ?>" disabled>

                            <label>Authorized Passenger</label>
                            <input type="text" name="authorized_passenger" class="editable-field admin-only-field" value="<?php echo htmlspecialchars($ticket['authorized_passenger'] ?? ''); ?>" disabled>

                            <label>Places to Visit</label>
                            <input type="text" name="places_to_visit" class="editable-field admin-only-field" value="<?php echo htmlspecialchars($ticket['places_to_visit'] ?? ''); ?>" disabled>

                            <label>Purpose</label>
                            <input type="text" name="purpose" class="editable-field admin-only-field" value="<?php echo htmlspecialchars($ticket['purpose'] ?? ''); ?>" disabled>

                            <div class="form-section-title">DRIVER SECTION</div>
                            <label>Time Departed (Garage)</label>
                            <input type="text" name="dep_office_time" class="editable-field driver-section-field" value="<?php echo htmlspecialchars($ticket['dep_office_time'] ?? ''); ?>" disabled>

                            <label>Time of Arrival at Destination</label>
                            <input type="text" name="arr_location_time" class="editable-field driver-section-field" value="<?php echo htmlspecialchars($ticket['arr_location_time'] ?? ''); ?>" disabled>

                            <label>Time of Departure from Destination</label>
                            <input type="text" name="dep_location_time" class="editable-field driver-section-field" value="<?php echo htmlspecialchars($ticket['dep_location_time'] ?? ''); ?>" disabled>

                            <label>Time of Arrival back to Office/Garage</label>
                            <input type="text" name="arr_office_time" class="editable-field driver-section-field" value="<?php echo htmlspecialchars($ticket['arr_office_time'] ?? ''); ?>" disabled>

                            <label>Approx. Distance (kms)</label>
                            <input type="number" step="0.01" name="approx_distance" class="editable-field driver-section-field" value="<?php echo htmlspecialchars($ticket['approx_distance'] ?? ''); ?>" disabled>

                            <label>Balance in Tank (Liters)</label>
                            <input type="number" step="0.01" name="gas_balance_start" class="editable-field driver-section-field" value="<?php echo htmlspecialchars($ticket['gas_balance_start'] ?? ''); ?>" disabled>

                            <label>Issued from Stock (Liters)</label>
                            <input type="number" step="0.01" name="gas_issued_office" class="editable-field driver-section-field" value="<?php echo htmlspecialchars($ticket['gas_issued_office'] ?? ''); ?>" disabled>

                            <label>Add Purchased during Trip (Liters)</label>
                            <input type="number" step="0.01" name="gas_added_trip" class="editable-field driver-section-field" value="<?php echo htmlspecialchars($ticket['gas_added_trip'] ?? ''); ?>" disabled>

                            <label>Deduct Used during trip (Liters)</label>
                            <input type="number" step="0.01" name="gas_used_trip" class="editable-field driver-section-field" value="<?php echo htmlspecialchars($ticket['gas_used_trip'] ?? ''); ?>" disabled>

                            <label>Gas Total (Liters)</label>
                            <input type="number" step="0.01" name="gas_total" class="editable-field driver-section-field" value="<?php echo htmlspecialchars($ticket['gas_total'] ?? ''); ?>" disabled>

                            <label>Gas Balance End (Liters)</label>
                            <input type="number" step="0.01" name="gas_balance_end" class="editable-field driver-section-field" value="<?php echo htmlspecialchars($ticket['gas_balance_end'] ?? ''); ?>" disabled>

                            <label>Gear Oil Issued</label>
                            <input type="number" step="0.01" name="gear_oil_issued" class="editable-field driver-section-field" value="<?php echo htmlspecialchars($ticket['gear_oil_issued'] ?? ''); ?>" disabled>

                            <label>Lub. Oil Issued</label>
                            <input type="number" step="0.01" name="lub_oil_issued" class="editable-field driver-section-field" value="<?php echo htmlspecialchars($ticket['lub_oil_issued'] ?? ''); ?>" disabled>

                            <label>Grease Issued</label>
                            <input type="number" step="0.01" name="grease_issued" class="editable-field driver-section-field" value="<?php echo htmlspecialchars($ticket['grease_issued'] ?? ''); ?>" disabled>

                            <label>Speedometer at Beginning of Trip</label>
                            <input type="number" step="0.01" name="speedometer_start" class="editable-field driver-section-field" value="<?php echo htmlspecialchars($ticket['speedometer_start'] ?? ''); ?>" disabled>

                            <label>Speedometer at End of Trip</label>
                            <input type="number" step="0.01" name="speedometer_end" class="editable-field driver-section-field" value="<?php echo htmlspecialchars($ticket['speedometer_end'] ?? ''); ?>" disabled>

                            <label>Remarks</label>
                            <input type="text" name="remarks" class="editable-field driver-section-field" value="<?php echo htmlspecialchars($ticket['remarks'] ?? ''); ?>" disabled>

                            <div class="form-section-title" style="margin-top:30px;">PASSENGER CERTIFICATION SECTION</div>
                            
                            <div style="display: flex; justify-content: space-between; gap: 20px; margin-top: 20px;">
                                <div style="flex: 1; text-align: center;">
                                    <div style="border-bottom: 1px solid #000; padding: 20px 0; margin-bottom: 10px;">
                                        <input type="text" name="passenger_1_name" class="editable-field driver-section-field" value="<?php echo htmlspecialchars($ticket['passenger_1_name'] ?? ''); ?>" disabled placeholder="Name" style="width: 100%; border: none; text-align: center; background: transparent; font-size: 12px;" oninput="updatePassengerDisplay(1)">
                                    </div>
                                    <input type="date" name="passenger_1_date" class="editable-field driver-section-field" value="<?php echo htmlspecialchars($ticket['passenger_1_date'] ?? ''); ?>" disabled style="width: 100%; border: none; text-align: center; background: transparent; font-size: 11px; margin-top: 5px;" oninput="updatePassengerDisplay(1)">
                                </div>
                                <div style="flex: 1; text-align: center;">
                                    <div style="border-bottom: 1px solid #000; padding: 20px 0; margin-bottom: 10px;">
                                        <input type="text" name="passenger_2_name" class="editable-field driver-section-field" value="<?php echo htmlspecialchars($ticket['passenger_2_name'] ?? ''); ?>" disabled placeholder="Name" style="width: 100%; border: none; text-align: center; background: transparent; font-size: 12px;" oninput="updatePassengerDisplay(2)">
                                    </div>
                                    <input type="date" name="passenger_2_date" class="editable-field driver-section-field" value="<?php echo htmlspecialchars($ticket['passenger_2_date'] ?? ''); ?>" disabled style="width: 100%; border: none; text-align: center; background: transparent; font-size: 11px; margin-top: 5px;" oninput="updatePassengerDisplay(2)">
                                </div>
                                <div style="flex: 1; text-align: center;">
                                    <div style="border-bottom: 1px solid #000; padding: 20px 0; margin-bottom: 10px;">
                                        <input type="text" name="passenger_3_name" class="editable-field driver-section-field" value="<?php echo htmlspecialchars($ticket['passenger_3_name'] ?? ''); ?>" disabled placeholder="Name" style="width: 100%; border: none; text-align: center; background: transparent; font-size: 12px;" oninput="updatePassengerDisplay(3)">
                                    </div>
                                    <input type="date" name="passenger_3_date" class="editable-field driver-section-field" value="<?php echo htmlspecialchars($ticket['passenger_3_date'] ?? ''); ?>" disabled style="width: 100%; border: none; text-align: center; background: transparent; font-size: 11px; margin-top: 5px;" oninput="updatePassengerDisplay(3)">
                                </div>
                            </div>

                            <div style="margin-top:20px;">
                                <?php if ($canEdit): ?>
                                    <button type="button" id="editToggleBtn" class="btn" style="background:#6c757d;">Edit Fields</button>
                                    <button type="submit" name="update_ticket" id="saveBtn" class="btn" style="display:none; background:#28a745;">Save Changes</button>
                                    <button type="submit" name="delete_ticket" id="deleteBtn" class="btn" style="background:#dc3545; margin-left:10px;" onclick="return confirm('Are you sure you want to delete this ticket? This action cannot be undone.');">Delete Ticket</button>
                                <?php endif; ?>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>

                <div class="preview-panel" id="previewPanel">
                    <div class="ticket-page">
                        <div class="appendix">Appendix A</div>

                        <div class="doc-header">
                            Republic of the Philippines<br>
                            Province of Southern Leyte<br>
                            <strong>CITY OF MAASIN</strong><br>
                            <span>Office of the</span> <strong><span class="fire-station-name">MAASIN CITY FIRE STATION</span></strong></div>

                        <div class="control-block">
                            Control No: <span class="underline" style="min-width: 150px;" id="p_control"><?php echo htmlspecialchars($ticket['control_no'] ?? ''); ?></span></div>

                                <h3 style="text-align: center; margin: 15px 0 0 0;">DRIVER'S TRIP TICKET</h3>
                                <div style="text-align: center; margin-bottom: 15px;">(<span id="p_date"><?php echo htmlspecialchars($ticket['ticket_date'] ?? ''); ?></span>)</div>

                                <p><strong>A. To be filled by the Administrative Official Authorizing Official Travel:</strong></p>
                                <div class="indent">
                                    <div class="line-item">1. Name of Driver of the Vehicle: <span class="dotted" id="p_driver"><?php echo htmlspecialchars($ticket['driver_name'] ?? ''); ?></span></div>
                                    <div class="line-item">2. Government car to be used. Plate No.: <span class="dotted" id="p_plate"><?php echo htmlspecialchars($ticket['vehicle_plate_no'] ?? ''); ?></span></div>
                                    <div class="line-item">3. Name of Authorized Passenger: <span class="dotted" id="p_pass"><?php echo htmlspecialchars($ticket['authorized_passenger'] ?? ''); ?></span></div>
                                    <div class="line-item">4. Place or places to be visited/inspected: <span class="dotted" id="p_places"><?php echo htmlspecialchars($ticket['places_to_visit'] ?? ''); ?></span></div>
                                    <div class="line-item">5. Purpose: <span class="dotted" id="p_purpose"><?php echo htmlspecialchars($ticket['purpose'] ?? ''); ?></span></div><br>
                                </div>

                                <div class="sig-row">
                                    <div class="sig-block">
                                        <span class="underline" style="width: 200px;"></span><br>
                                        <small>Head of Office or his duly<br>Authorized Representative</small>
                                    </div>
                                </div>

                                <p><strong>B. To be filled by the Driver:</strong></p>
                                <div class="indent">
                                    <div class="line-item">1. Time of departure from Office / Garage: <span class="dotted" id="p_dep1"><?php echo htmlspecialchars($ticket['dep_office_time'] ?? ''); ?></span> a.m./p.m.</div>
                                    <div class="line-item">2. Time of arrival at (per No. 4 above): <span class="dotted" id="p_arrival_at"><?php echo htmlspecialchars($ticket['arr_location_time'] ?? ''); ?></span> a.m./p.m.</div>
                                    <div class="line-item">3. Time of departure from (per No. 4): <span class="dotted" id="p_dep_from"><?php echo htmlspecialchars($ticket['dep_location_time'] ?? ''); ?></span> a.m./p.m.</div>
                                    <div class="line-item">4. Time of arrival back to Office/Garage: <span class="dotted" id="p_arrival_back"><?php echo htmlspecialchars($ticket['arr_office_time'] ?? ''); ?></span> a.m./p.m.</div>
                                    <div class="line-item">5. Approximate distance travelled (to and from): <span class="dotted" id="p_dist"><?php echo htmlspecialchars($ticket['approx_distance'] ?? ''); ?></span> kms.</div>
                                    <div class="line-item">6. Gasoline issued, purchase and consumed:</div>
                                    <div class="indent">
                                        <table class="gas-table">
                                            <tr><td style="width: 280px;">a. Balance in Tank:</td><td class="dotted" id="p_bal"><?php echo htmlspecialchars($ticket['gas_balance_start'] ?? ''); ?></td><td style="width: 50px; padding-left: 5px;">liters</td></tr>
                                            <tr><td>b. Issued by Office from Stock:</td><td class="dotted" id="p_issued"><?php echo htmlspecialchars($ticket['gas_issued_office'] ?? ''); ?></td><td>liters</td></tr>
                                            <tr><td>c. Add purchased during trip:</td><td class="dotted" id="p_purchased"><?php echo htmlspecialchars($ticket['gas_added_trip'] ?? ''); ?></td><td>liters</td></tr>
                                            <tr><td style="padding-left: 40px;"><strong>TOTAL. . . :</strong></td><td class="dotted" id="p_total"><?php echo htmlspecialchars($ticket['gas_total'] ?? ''); ?></td><td><strong>liters</strong></td></tr>
                                            <tr><td>d. Deduct Used during the trip (to and from):</td><td class="dotted" id="p_used"><?php echo htmlspecialchars($ticket['gas_used_trip'] ?? ''); ?></td><td>liters</td></tr>
                                            <tr><td>e. Balance in tank at the end of trip:</td><td class="dotted" id="p_end"><?php echo htmlspecialchars($ticket['gas_balance_end'] ?? ''); ?></td><td>liters</td></tr>
                                        </table>
                                    </div>
                                    <div class="line-item">7. Gear oil issued: <span class="dotted" id="p_gear_oil"><?php echo htmlspecialchars($ticket['gear_oil_issued'] ?? ''); ?></span> liters</div>
                                    <div class="line-item">8. Lub. Oil issued: <span class="dotted" id="p_lub_oil"><?php echo htmlspecialchars($ticket['lub_oil_issued'] ?? ''); ?></span> liters</div>
                                    <div class="line-item">9. Grease issued: <span class="dotted" id="p_grease"><?php echo htmlspecialchars($ticket['grease_issued'] ?? ''); ?></span> liters</div>
                                    <div class="line-item">10. Speedometer readings, if any:</div>
                                    <div class="indent" style="margin-top: 2px;">
                                        <div class="line-item">&nbsp;&nbsp;&nbsp;&nbsp;At beginning of trip <span class="dotted" id="p_speed_begin"><?php echo htmlspecialchars($ticket['speedometer_start'] ?? ''); ?></span> kms.</div>
                                        <div class="line-item">&nbsp;&nbsp;&nbsp;&nbsp;At end of trip <span class="dotted" id="p_speed_end"><?php echo htmlspecialchars($ticket['speedometer_end'] ?? ''); ?></span> kms.</div>
                                        <div class="line-item">&nbsp;&nbsp;&nbsp;&nbsp;Distance travelled (per No. 5 above) <span class="dotted" id="p_speed_dist"><?php echo htmlspecialchars($ticket['approx_distance'] ?? ''); ?></span> kms.</div>
                                    </div>
                                    <div class="line-item">11. Remarks: <span class="dotted" id="p_remarks"><?php echo htmlspecialchars($ticket['remarks'] ?? ''); ?></span></div>
                                </div>

                                <div style="margin-top: 20px; text-align: center; font-style: italic;">
                                    I hereby certify to the correctness of the above statement of record of travel.
                                </div>
                                <div class="Sig-driver"><?php echo htmlspecialchars($ticket['driver_name'] ?? ''); ?></div>
                                <div class="sig-box" style="width: 250px; margin-top: 20px;" id="p_driver_sig"></div>
                                <div style="text-align: center; margin-top: 5px; font-weight: bold;">Driver</div>
                                
                                <div style="margin-top: 15px; text-align: center;">
                                    I hereby certify that I used this car on official business as stated above.
                                </div>
                                
                                <div style="display: flex; justify-content: space-between; gap: 20px; margin-top: 25px; font-size: 9pt;">
                                    <div style="flex: 1; text-align: center;">
                                        <div id="p_pass1" style="border-bottom: 1px solid #000; padding: 20px 0; margin-bottom: 10px; min-height: 20px;"><?php $name1=$ticket['passenger_1_name'] ?? ''; $date1=$ticket['passenger_1_date'] ?? ''; echo htmlspecialchars($name1 && $date1 ? "$name1 / $date1" : ($name1 ?: $date1)); ?></div>
                                        <small>Name of Passenger / Date</small>
                                    </div>
                                    <div style="flex: 1; text-align: center;">
                                        <div id="p_pass2" style="border-bottom: 1px solid #000; padding: 20px 0; margin-bottom: 10px; min-height: 20px;"><?php $name2=$ticket['passenger_2_name'] ?? ''; $date2=$ticket['passenger_2_date'] ?? ''; echo htmlspecialchars($name2 && $date2 ? "$name2 / $date2" : ($name2 ?: $date2)); ?></div>
                                        <small>Name of Passenger / Date</small>
                                    </div>
                                    <div style="flex: 1; text-align: center;">
                                        <div id="p_pass3" style="border-bottom: 1px solid #000; padding: 20px 0; margin-bottom: 10px; min-height: 20px;"><?php $name3=$ticket['passenger_3_name'] ?? ''; $date3=$ticket['passenger_3_date'] ?? ''; echo htmlspecialchars($name3 && $date3 ? "$name3 / $date3" : ($name3 ?: $date3)); ?></div>
                                        <small>Name of Passenger / Date</small>
                                    </div>
                                </div>
                            </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
    function updatePassengerDisplay(passengerNum) {
        const nameInput = document.querySelector(`input[name="passenger_${passengerNum}_name"]`);
        const dateInput = document.querySelector(`input[name="passenger_${passengerNum}_date"]`);
        const preview = document.getElementById(`p_pass${passengerNum}`);
        
        const name = nameInput ? nameInput.value : "";
        const date = dateInput ? dateInput.value : "";
        
        let displayText = "";
        if (name && date) {
            displayText = `${name} / ${date}`;
        } else if (name) {
            displayText = name;
        } else if (date) {
            displayText = date;
        }
        
        if (preview) preview.innerText = displayText;
    }

    document.addEventListener('DOMContentLoaded', () => {
        // --- Sidebar dropdown ---
        document.querySelectorAll('.dropdown').forEach(item => {
            item.addEventListener('click', function(e) {
                if (e.target.closest('.submenu')) return;
                this.classList.toggle('active');
                document.querySelectorAll('.dropdown').forEach(other => {
                    if (other !== this) other.classList.remove('active');
                });
            });
        });
    });

    // --- Panel Toggling ---
    document.addEventListener('DOMContentLoaded', () => {
        const toggleBtn = document.getElementById('previewToggleBtn');
        const container = document.getElementById('mainContainer');
        
        if(toggleBtn && container) {
            toggleBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const isActive = container.classList.toggle('preview-active');
                toggleBtn.classList.toggle('active');
                toggleBtn.textContent = isActive ? '📋 Hide Preview' : '📋 Show Preview';
            });
        }
    });

    // --- Edit Mode Logic ---
    document.addEventListener('DOMContentLoaded', () => {
        const editBtn = document.getElementById('editToggleBtn');
        const saveBtn = document.getElementById('saveBtn');
        const fields = document.querySelectorAll('.editable-field');
        const adminOnlyFields = document.querySelectorAll('.admin-only-field');
        const driverSectionFields = document.querySelectorAll('.driver-section-field');
        const userRole = '<?php echo $current_role; ?>';
        const isAdmin = userRole === 'admin';

        if(editBtn) {
            editBtn.addEventListener('click', () => {
                const isEditing = editBtn.textContent === 'Cancel';
                
                // Determine which fields to enable based on user role
                if (isAdmin) {
                    // Admin can edit ALL fields
                    fields.forEach(f => f.disabled = isEditing);
                } else {
                    // Non-admin drivers can only edit driver section fields
                    adminOnlyFields.forEach(f => f.disabled = true); // Always disable admin fields
                    driverSectionFields.forEach(f => f.disabled = isEditing);
                }
                
                saveBtn.style.display = isEditing ? 'none' : 'inline-block';
                editBtn.textContent = isEditing ? 'Edit Fields' : 'Cancel';
                editBtn.style.background = isEditing ? '#6c757d' : '#dc3545';
            });
        }

        // --- Live Preview Sync ---
        const fieldMap = {
            'ticket_date': 'p_date',
            'driver_name': 'p_driver',
            'vehicle_no': 'p_plate',
            'authorized_passenger': 'p_pass',
            'places_to_visit': 'p_places',
            'purpose': 'p_purpose',
            'dep_office_time': 'p_dep1',
            'arr_location_time': 'p_arrival_at',
            'dep_location_time': 'p_dep_from',
            'arr_office_time': 'p_arrival_back',
            'approx_distance': 'p_dist',
            'gas_balance_start': 'p_bal',
            'gas_issued_office': 'p_issued',
            'gas_added_trip': 'p_purchased',
            'gas_used_trip': 'p_used',
            'gear_oil_issued': 'p_gear_oil',
            'lub_oil_issued': 'p_lub_oil',
            'grease_issued': 'p_grease',
            'speedometer_start': 'p_speed_begin',
            'speedometer_end': 'p_speed_end',
            'remarks': 'p_remarks',
            'passenger_1_name': 'p_pass1',
            'passenger_2_name': 'p_pass2',
            'passenger_3_name': 'p_pass3',
            'passenger_1_date': 'p_date1',
            'passenger_2_date': 'p_date2',
            'passenger_3_date': 'p_date3'
        };

        fields.forEach(input => {
            input.addEventListener('input', () => {
                const previewId = fieldMap[input.name];
                if(previewId) document.getElementById(previewId).textContent = input.value;
                
                // Auto-calculate Gas Total and End Balance
                const bal = parseFloat(document.querySelector('[name="gas_balance_start"]').value) || 0;
                const issued = parseFloat(document.querySelector('[name="gas_issued_office"]').value) || 0;
                const purchased = parseFloat(document.querySelector('[name="gas_added_trip"]').value) || 0;
                const used = parseFloat(document.querySelector('[name="gas_used_trip"]').value) || 0;
                
                const total = bal + issued + purchased;
                const end = total - used;
                
                document.getElementById('p_total').textContent = total >= 0 ? total.toFixed(2) : '0';
                document.getElementById('p_end').textContent = end >= 0 ? end.toFixed(2) : '0';
                document.getElementById('p_speed_dist').textContent = input.name === 'approx_distance' ? input.value : document.querySelector('[name="approx_distance"]').value;
            });
        });
    });
    </script>
</body>
</html>