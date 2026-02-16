<?php
session_start();
require_once 'db_connect.php';

// Get filters from GET request - default to current month and year
$selectedMonth = isset($_GET['month']) ? $_GET['month'] : date('m');
$selectedYear = isset($_GET['year']) ? $_GET['year'] : date('Y');

// Array of month names
$months = [
    '01' => 'January', '02' => 'February', '03' => 'March', '04' => 'April',
    '05' => 'May', '06' => 'June', '07' => 'July', '08' => 'August',
    '09' => 'September', '10' => 'October', '11' => 'November', '12' => 'December'
];

try {
    // Build query with filters - Show only Pending reports
    $sql = "SELECT t.*, d.full_name FROM trip_tickets t LEFT JOIN drivers d ON t.driver_id = d.driver_id WHERE t.status = 'Pending'";
    $params = [];
    
    if (!empty($selectedYear)) {
        $sql .= " AND YEAR(t.ticket_date) = ?";
        $params[] = $selectedYear;
    }
    
    if (!empty($selectedMonth)) {
        $sql .= " AND MONTH(t.ticket_date) = ?";
        $params[] = $selectedMonth;
    }
    
    $sql .= " ORDER BY t.ticket_date DESC, t.created_at DESC";
    
    // Prepare and execute statement
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
    
    // Fetch Active trips
    $activeSql = "SELECT t.*, d.full_name FROM trip_tickets t LEFT JOIN drivers d ON t.driver_id = d.driver_id WHERE t.status = 'Active'";
    $activeParams = [];
    
    if (!empty($selectedYear)) {
        $activeSql .= " AND YEAR(t.ticket_date) = ?";
        $activeParams[] = $selectedYear;
    }
    
    if (!empty($selectedMonth)) {
        $activeSql .= " AND MONTH(t.ticket_date) = ?";
        $activeParams[] = $selectedMonth;
    }
    
    $activeSql .= " ORDER BY t.ticket_date DESC, t.created_at DESC";
    $activeStmt = $pdo->prepare($activeSql);
    $activeStmt->execute($activeParams);
    $activeLogs = $activeStmt->fetchAll();
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BFP - View All Logs</title>
    <link rel="icon" href="ALBUM/favicon_io/favicon-32x32.png" type="image/x-icon" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet"/>

    <style>
       :root { --bfp-red: #B22222; --sidebar-bg: #1e1e2d; --dark-bg: #0f0f18; --card-bg: #2a2a3e; --active-blue: #5d5dff; --text-primary: #e4e4e7; --text-secondary: #a2a2c2; --border-color: rgba(255, 255, 255, 0.05); --transition-speed: 0.3s; }
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Poppins', 'Segoe UI', Arial, sans-serif; background-color: var(--dark-bg); color: var(--text-primary); margin: 0; display: flex; flex-direction: column; height: 100vh; }
header { background: var(--sidebar-bg); color: white; padding: 15px 30px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border-color); z-index: 100; }
.wrapper { display: flex; flex: 1; overflow: hidden; }
main { flex: 1; padding: 30px; overflow-y: auto; background-color: var(--sidebar-bg); }
.trip-list { background: var(--card-bg); border-radius: 12px; padding: 20px; box-shadow: 0 8px 32px rgba(0,0,0,0.3); border: 1px solid var(--border-color); }
.filter-controls { display: flex; justify-content: space-between; align-items: center; gap: 1.5rem; margin-bottom: 24px; flex-wrap: wrap; }
.date-filters { display: flex; gap: 10px; }
.date-select { padding: 10px 15px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.95rem; background: var(--card-bg); color: var(--text-primary); cursor: pointer; transition: all 0.2s ease; }
.date-select:hover { border-color: var(--active-blue); box-shadow: 0 0 10px rgba(93, 93, 255, 0.2); }
.date-select:focus { outline: none; border-color: var(--active-blue); background: rgba(93, 93, 255, 0.1); }
.date-select option { background: var(--card-bg); color: var(--text-primary); }
.trip-item { background: rgba(93, 93, 255, 0.03); border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.2); margin-bottom: 12px; transition: all 0.3s ease; border: 1px solid var(--border-color); cursor: pointer; overflow: hidden; }
.trip-item:hover { background: rgba(93, 93, 255, 0.08); border-color: var(--active-blue); box-shadow: 0 8px 24px rgba(93, 93, 255, 0.15); transform: translateY(-2px); }
.trip-item.active { background: rgba(93, 93, 255, 0.12); border-color: var(--active-blue); box-shadow: 0 8px 32px rgba(93, 93, 255, 0.25); }
.trip-summary { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; font-size: 1rem; font-weight: 500; color: var(--text-primary); }
.trip-summary .summary-left { display: flex; align-items: center; gap: 16px; flex: 1; }
.trip-summary .summary-left i { font-size: 1.2rem; color: var(--active-blue); }
.driver-info { display: flex; flex-direction: column; gap: 2px; }
.driver-name { font-weight: 600; color: var(--active-blue); }
.trip-datetime { font-size: 0.85rem; color: var(--text-secondary); }
.trip-summary .summary-right { display: flex; align-items: center; gap: 12px; color: var(--text-secondary); }
.arrow-expand { border: solid var(--active-blue); border-width: 0 2px 2px 0; display: inline-block; padding: 4px; transform: rotate(-45deg); transition: transform 0.3s cubic-bezier(0.68, -0.55, 0.27, 1.55); }
.trip-item.active .arrow-expand { transform: rotate(45deg); }
.trip-details { display: none; background: rgba(0, 0, 0, 0.2); border-top: 1px solid var(--border-color); padding: 30px; font-size: 0.9rem; color: var(--text-primary); }
.trip-item.active .trip-details { display: block; animation: expandDown 0.3s ease; }
@keyframes expandDown { from { opacity: 0; max-height: 0; } to { opacity: 1; max-height: 2000px; } }
.ticket-preview { width: 8.5in; min-width: 8.5in; height: auto; background: white; padding: 0.5in; box-shadow: 0 0 15px rgba(0,0,0,0.5); font-family: "Times New Roman", serif; font-size: 10.5pt; color: black; line-height: 1.15; position: relative; margin: 0 auto; }
@media (max-width: 900px) { .ticket-preview { transform: scale(0.8); } }
.ticket-preview .header-section { text-align: center; margin-bottom: 10px; line-height: 1.3; }
.ticket-preview .header-section strong { display: block; font-weight: bold; font-size: 14pt; }
.ticket-preview .fire-station-name { text-decoration: underline; font-weight: bold; }
.ticket-preview .appendix { position: absolute; font-weight: bold; right: 0.5in; top: 0.4in; font-size: 10.5pt; }
.ticket-preview .control-no { text-align: right; margin-bottom: 8px; font-weight: bold; font-size: 10.5pt; }
.ticket-preview h3 { text-align: center; margin: 15px 0 10px 0; font-size: 11pt; font-weight: bold; }
.ticket-preview .section-title { font-weight: bold; margin-top: 8px; margin-bottom: 6px; font-size: 10.5pt; }
.ticket-preview .indent { margin-left: 25px; }
.ticket-preview ol, .ticket-preview ul { margin: 0; padding-left: 0; }
.ticket-preview li { margin-bottom: 3px; font-size: 10.5pt; }
.ticket-preview .line-item { display: flex; align-items: flex-end; margin-bottom: 1px; font-size: 8.5pt; }
.ticket-preview .underline { border-bottom: 1px solid black; display: inline-block; min-width: 100px; padding: 0 5px; text-align: center; }
.ticket-preview .dotted { border-bottom: 1px solid black; flex: 1; margin-left: 5px; min-height: 1.1em; padding-left: 5px; }
.ticket-preview .gas-table .dotted { border-bottom: 1px solid black; display: inline-block; min-width: 60px; padding: 2px 1px; text-align: center; }
.ticket-preview .gas-table { width: 100%; border-collapse: collapse; margin: -7px 0; font-size: 7.5pt; }
.ticket-preview .gas-table td { padding: 2px 0; border: none; vertical-align: baseline; }
.ticket-preview .gas-table td:first-child { padding-right: 5px; white-space: nowrap; }
.ticket-preview .gas-table td:last-child { padding-left: 5px; display: flex; align-items: baseline; gap: 3px; }
.ticket-preview .gas-table .dotted { border-bottom: 1px solid black; flex: 1; min-width: 40px; padding: 0 2px; text-align: center; display: flex; align-items: center; justify-content: center; }
.ticket-preview .gas-table td.gas-value { border-bottom: 1px solid black; text-align: center; min-width: 80px; }
.ticket-preview .gas-table td.gas-unit { width: 40px; text-align: left; padding-left: 5px; flex-shrink: 0; }
.ticket-preview .sig-driver { 
    width: 250px; 
    margin: 20px auto 0 auto; /* Centers the block */
    text-align: center; 
    font-weight: bold; 
    display: block; 
}

.ticket-preview .sig-box { 
    width: 250px; 
    border-top: 1px solid black; 
    margin: 0 auto 5px auto; /* Removed top margin so it hugs the name */
    text-align: center;
}
.ticket-preview .sig-row { display: flex; justify-content: flex-end; margin: 1px 0; }
.ticket-preview .sig-block { text-align: center; }
.ticket-preview .sig-driver { width: 150px; margin: 0 auto; padding-left: 0px; text-align: center; text-decoration: none; font-weight: bold; text-underline-offset: 4px; float: inherit; clear: both; display: block; }
.ticket-preview .sig-block .underline { width: 200px; margin-bottom: 1px; }
.ticket-preview .sig-block small { display: block; font-size: 9pt; margin-top: 0; }
.badge { background: rgba(93, 93, 255, 0.2); color: var(--active-blue); padding: 4px 8px; border-radius: 6px; font-weight: 600; font-size: 0.85rem; display: inline-block; width: fit-content; border: 1px solid rgba(93, 93, 255, 0.3); }
.fuel-badge { background: rgba(255, 152, 0, 0.2); color: #ffb74d; padding: 4px 8px; border-radius: 6px; font-weight: 600; font-size: 0.85rem; display: inline-block; width: fit-content; border: 1px solid rgba(255, 152, 0, 0.3); }
.btn-view { background: linear-gradient(135deg, var(--active-blue) 0%, #7b7bff 100%); color: white; border: none; font-weight: 600; font-size: 0.85rem; padding: 8px 16px; border-radius: 8px; cursor: pointer; transition: all 0.2s; }
.btn-view:hover { box-shadow: 0 4px 12px rgba(93, 93, 255, 0.4); transform: translateY(-2px); }
.ticket-actions-container { display: flex; gap: 30px; align-items: flex-start; }
.ticket-actions-sidebar { flex-shrink: 0; width: 180px; display: flex; flex-direction: column; gap: 12px; }
.action-button { background: linear-gradient(135deg, var(--active-blue) 0%, #7b7bff 100%); color: white; border: none; font-weight: 600; font-size: 0.9rem; padding: 10px 14px; border-radius: 8px; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px; text-align: center; }
.action-button:hover { box-shadow: 0 4px 12px rgba(93, 93, 255, 0.4); transform: translateY(-2px); }
.action-button i { font-size: 1rem; }
.qr-code-box { background: var(--card-bg); border: 2px solid var(--active-blue); border-radius: 12px; padding: 12px; text-align: center; flex-shrink: 0; width: 180px; }
.qr-code-box .qr-placeholder { background: rgba(93, 93, 255, 0.1); border: 1px dashed var(--active-blue); width: 150px; height: 150px; margin: 0 auto 10px; display: flex; align-items: center; justify-content: center; border-radius: 4px; color: var(--text-secondary); font-size: 12px; cursor: pointer; transition: all 0.2s ease; }
.qr-code-box .qr-placeholder:hover { background: rgba(93, 93, 255, 0.2); transform: scale(1.05); }
.qr-code-box .qr-placeholder img { cursor: pointer; transition: all 0.2s ease; }
.qr-code-box .qr-placeholder img:hover { transform: scale(1.05); }
.qr-code-box .qr-label { font-weight: 600; color: var(--active-blue); font-size: 12px; }
.ticket-content { flex: 1; }
.modal { display: none; position: fixed; bottom: 0; left: 0; right: 0; background: white; border-top: 3px solid var(--bfp-red); box-shadow: 0 -4px 15px rgba(0,0,0,0.1); z-index: 200; max-height: 70vh; overflow-y: auto; animation: slideUp 0.3s ease; }
@keyframes slideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
.modal.active { display: block; }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 1px solid #eee; background: linear-gradient(90deg, var(--bfp-red) 60%, var(--orange) 100%); color: white; }
.modal-header h2 { margin: 0; }
.modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: white; transition: color 0.2s; }
.modal-close:hover { opacity: 0.8; }
.modal-body { padding: 20px; display: flex; justify-content: center; background: #525659; }
.ticket-document { width: 8.27in; height: 11.69in; background: white; padding: 0.5in; box-shadow: 0 0 15px rgba(0,0,0,0.5); font-family: "Times New Roman", Times, serif; color: black; font-size: 10.5pt; line-height: 1.15; }
.doc-header { text-align: center; margin-bottom: 10px; line-height: 1.3; }
.appendix { position: absolute; font-weight: bold; right: 0.5in; top: 0.4in; }
.control-block { text-align: right; margin-top: -10px; }
.underline { border-bottom: 1px solid black; display: inline-block; min-width: 100px; padding: 0 5px; text-align: center; }
.dotted { border-bottom: 1px solid black; display: inline-block; min-width: 80px; padding: 0 5px; }
.line-item { display: block; margin-bottom: 3px; }
.indent { margin-left: 25px; }
.gas-table { width: 100%; border-collapse: collapse; margin: 5px 0; }
.gas-table td { padding: 2px 5px; border: none; }
.sig-box { width: 250px; border-top: 1px solid black; text-align: center; padding-top: 5px; font-weight: bold; margin: 25px auto 5px auto; }
.sig-row { display: flex; justify-content: flex-end; }
.sig-block { text-align: center; }
.loading { text-align: center; padding: 20px; color: var(--text-secondary); }
.no-data { text-align: center; color: var(--active-blue); padding: 40px 20px; font-weight: 600; }
h2 { color: var(--text-primary); font-size: 1.8rem; font-weight: 600; }
.tabs-container { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid var(--border-color); }
.tab-button { padding: 12px 24px; background: none; border: none; color: var(--text-secondary); font-weight: 600; cursor: pointer; border-bottom: 3px solid transparent; transition: all 0.3s; }
.tab-button:hover { color: var(--active-blue); }
.tab-button.active { color: var(--active-blue); border-bottom-color: var(--active-blue); }
.tab-content { display: none; }
.tab-content.active { display: block; }
footer { display: none; }
@media (max-width: 768px) { .sidebar { width: 200px; } header { flex-direction: column; gap: 10px; } main { padding: 20px; } h2 { font-size: 1.5rem; } .filter-controls { flex-direction: column; align-items: stretch; } .date-filters { flex-direction: column; } .trip-list { overflow-x: auto; } .trip-list table { font-size: 0.85rem; } .trip-list th, .trip-list td { padding: 8px 10px; } }
    </style>
</head>
<body>

<div class="wrapper">
<?php include("Components/Sidebar.php");?>

<main>
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;">
            <h2 style="margin:0;">Pending Submissions </h2>
        </div>

        <!-- Filter Controls -->
        <div class="filter-controls">
            <form method="get" id="filterForm" style="display: flex; gap: 1.5rem; width: 100%;">
                <div class="date-filters">
                    <select name="month" class="date-select" onchange="this.form.submit()">
                        <option value="">Month</option>
                        <?php foreach ($months as $value => $name): ?>
                            <option value="<?php echo htmlspecialchars($value); ?>" <?php echo ($selectedMonth == $value) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="year" class="date-select" onchange="this.form.submit()">
                        <option value="">Year</option>
                        <?php
                            $currentYear = date('Y');
                            for ($year = $currentYear; $year >= $currentYear - 5; $year--):
                        ?>
                            <option value="<?php echo htmlspecialchars($year); ?>" <?php echo ($selectedYear == $year) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($year); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
            </form>
        </div>

        <!-- Tabs -->
        <div class="tabs-container">
            <button class="tab-button active" onclick="switchTab('pending')">📋 Pending Submissions (<?php echo count($logs); ?>)</button>
            <button class="tab-button" onclick="switchTab('active')">🚗 Active Trips (<?php echo count($activeLogs); ?>)</button>
        </div>

        <!-- Pending Tab -->
        <div id="pending-tab" class="tab-content active">
            <!-- Trip List -->
            <div class="trip-list">
                <?php if (count($logs) > 0): ?>
                    <?php foreach ($logs as $row): ?>
                <div class="trip-item" onclick="toggleTrip(this)">
                    <div class="trip-summary">
                        <div class="summary-left">
                            <i class="fas fa-user-tie"></i>
                            <div class="driver-info">
                                <div class="driver-name"><?= htmlspecialchars($row['full_name'] ?? 'N/A') ?></div>
                                <div class="trip-datetime">
                                    <?= date('M d, Y', strtotime($row['ticket_date'])) ?> 
                                    <span style="color: var(--active-blue); font-weight: 600;"><?= date('g:i A', strtotime($row['created_at'])) ?></span>
                                    <?php 
                                        $status = isset($row['status']) ? $row['status'] : 'Pending';
                                        $status_color = ($status === 'Submitted') ? '#4caf50' : '#ff9800';
                                        $status_bg = ($status === 'Submitted') ? 'rgba(76, 175, 80, 0.2)' : 'rgba(255, 152, 0, 0.2)';
                                        $status_border = ($status === 'Submitted') ? 'rgba(76, 175, 80, 0.4)' : 'rgba(255, 152, 0, 0.4)';
                                    ?>
                                    <span style="margin-left: 10px; padding: 4px 8px; background: <?= $status_bg ?>; color: <?= $status_color ?>; border-radius: 6px; font-size: 0.85rem; font-weight: bold; border: 1px solid <?= $status_border ?>;">
                                        <?= htmlspecialchars($status) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="summary-right">
                            <i class="arrow-expand"></i>
                        </div>
                    </div>

                    <div class="trip-details" id="details-<?= $row['id'] ?>">
                        <div class="ticket-actions-container">
                            <div class="ticket-actions-sidebar">
                                <button class="action-button" onclick="event.stopPropagation(); printTicket(<?= $row['id'] ?>)" title="Print the ticket">
                                    <i class="fas fa-print"></i> Print
                                </button>
                                <button class="action-button" onclick="event.stopPropagation(); editTicket(<?= $row['id'] ?>)" title="Edit this ticket">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="action-button" onclick="event.stopPropagation(); takeTask(<?= $row['id'] ?>)" title="Take task - Update status to Active" style="background: linear-gradient(90deg, #17a2b8 60%, #138496 100%);">
                                    <i class="fas fa-tasks"></i> Take Task
                                </button>
                                <button class="action-button" onclick="event.stopPropagation(); submitTicket(<?= $row['id'] ?>)" title="Submit for approval">
                                    <i class="fas fa-check"></i> Submit
                                </button>
                                <div class="qr-code-box">
                                    <div class="qr-placeholder" id="qr-<?= $row['id'] ?>">
                                        <i class="fas fa-qrcode" style="font-size: 3rem; color: #ccc;"></i>
                                    </div>
                                    <div class="qr-label">Ticket QR Code</div>
                                </div>
                            </div>
                            <div class="ticket-content">
                                <div class="ticket-preview"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-data">No pending trip tickets found</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Active Trips Tab -->
        <div id="active-tab" class="tab-content">
            <!-- Trip List -->
            <div class="trip-list">
                <?php if (count($activeLogs) > 0): ?>
                    <?php foreach ($activeLogs as $row): ?>
                    <div class="trip-item" onclick="toggleTrip(this)">
                        <div class="trip-summary">
                            <div class="summary-left">
                                <i class="fas fa-user-tie"></i>
                                <div class="driver-info">
                                    <div class="driver-name"><?= htmlspecialchars($row['full_name'] ?? 'N/A') ?></div>
                                    <div class="trip-datetime">
                                        <?= date('M d, Y', strtotime($row['ticket_date'])) ?> 
                                        <span style="color: var(--active-blue); font-weight: 600;"><?= date('g:i A', strtotime($row['created_at'])) ?></span>
                                        <span style="margin-left: 10px; padding: 4px 8px; background: rgba(76, 175, 80, 0.2); color: #4caf50; border-radius: 6px; font-size: 0.85rem; font-weight: bold; border: 1px solid rgba(76, 175, 80, 0.4);">Active</span>
                                    </div>
                                </div>
                            </div>
                            <div class="summary-right">
                                <i class="arrow-expand"></i>
                            </div>
                        </div>

                        <div class="trip-details" id="details-<?= $row['id'] ?>">
                            <div class="ticket-actions-container">
                                <div class="ticket-actions-sidebar">
                                    <button class="action-button" onclick="event.stopPropagation(); printTicket(<?= $row['id'] ?>)" title="Print the ticket">
                                        <i class="fas fa-print"></i> Print
                                    </button>
                                    <button class="action-button" onclick="event.stopPropagation(); editTicket(<?= $row['id'] ?>)" title="Edit this ticket">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="action-button" onclick="event.stopPropagation(); submitTicket(<?= $row['id'] ?>)" title="Submit for approval">
                                        <i class="fas fa-check"></i> Submit
                                    </button>
                                    <div class="qr-code-box">
                                        <div class="qr-placeholder" id="qr-<?= $row['id'] ?>">
                                            <i class="fas fa-qrcode" style="font-size: 3rem; color: #ccc;"></i>
                                        </div>
                                        <div class="qr-label">Ticket QR Code</div>
                                    </div>
                                </div>
                                <div class="ticket-content">
                                    <div class="ticket-preview"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-data">No active trip tickets found</div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<script>
    // Toggle trip details expansion
    function toggleTrip(element) {
        const tripId = element.querySelector('.trip-details').id.replace('details-', '');
        
        // Remove active class from all trip items
        document.querySelectorAll('.trip-item').forEach(item => {
            if (item !== element) {
                item.classList.remove('active');
            }
        });
        
        // Toggle current item
        element.classList.toggle('active');
        
        // If activating, load ticket details
        if (element.classList.contains('active')) {
            loadTicketPreview(tripId);
        }
    }

    // Load ticket preview data
    function loadTicketPreview(ticketId) {
        const previewDiv = document.querySelector(`#details-${ticketId} .ticket-preview`);
        previewDiv.innerHTML = '<div class="loading">Loading ticket details...</div>';
        
        fetch('get_ticket_details.php?id=' + ticketId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const ticket = data.ticket;
                    
                    // Update QR Code Box
                    const qrBox = document.querySelector(`#qr-${ticketId}`);
                    if (qrBox && ticket.qr_code) {
                        qrBox.innerHTML = `<img src="${ticket.qr_code}" alt="QR Code" width="150" height="150" style="border-radius: 4px;" onclick="downloadTicketAsWord(${ticketId}, '${escapeQuotes(ticket.full_name)}', '${ticket.vehicle_plate_no}', '${escapeQuotes(ticket.control_no || '')}')">`;
                    }
                    
                    const gasTotal = parseFloat(ticket.gas_balance_start || 0) + parseFloat(ticket.gas_issued_office || 0) + parseFloat(ticket.gas_added_trip || 0);
                    const gasEnd = gasTotal - parseFloat(ticket.gas_used_trip || 0);
                    const ticketDate = formatDateFull(ticket.ticket_date);
                    
                    previewDiv.innerHTML = `
                        <div class="appendix">Appendix A</div>
                        <div class="header-section" style="display: flex; align-items: center; justify-content: flex-start; gap: 15px; margin-bottom: 20px; padding-left: 130px;">
                            
                            <img src="ALBUM/Official Seal (2).png" alt="Logo" 
                                style="width: 80px; height: 80px; flex-shrink: 0; margin-top: -15px;">
                            
                            <div style="text-align: center; line-height: 1.3;">
                                <div style="font-size: 10.5pt;">Republic of the Philippines</div>
                                <div style="font-size: 10.5pt;">Province of Southern Leyte</div>
                                <strong style="font-size: 14pt; display: block; margin: 2px 0;">CITY OF MAASIN</strong>
                                <div style="font-size: 10.5pt;">
                                    Office of the <span class="fire-station-name" style="text-decoration: underline; font-weight: bold;">MAASIN CITY FIRE STATION</span>
                                </div>
                            </div>
                        </div>

                        <div class="control-no">Control No: <span class="underline">${escapeHtml(ticket.control_no)}</span></div>

                        <h3 style="text-align: center; margin: 15px 0 0 0;">DRIVER'S TRIP TICKET</h3>
                        <div style="text-align: center; margin-bottom: 15px;">(${ticketDate})</div>
                        
                        <p><strong>A. To be filled by the Administrative Official Authorizing Official Travel:</strong></p>
                        <div class="indent">
                            <div class="line-item">1. Name of Driver of the Vehicle: <span class="dotted">${escapeHtml(ticket.full_name || 'N/A')}</span></div>
                            <div class="line-item">2. Government car to be used. Plate No.: <span class="dotted">${escapeHtml(ticket.vehicle_plate_no)}</span></div>
                            <div class="line-item">3. Name of Authorized Passenger: <span class="dotted">${escapeHtml(ticket.authorized_passenger || 'N/A')}</span></div>
                            <div class="line-item">4. Place or places to be visited/inspected: <span class="dotted">${escapeHtml(ticket.places_to_visit || 'N/A')}</span></div>
                            <div class="line-item">5. Purpose: <span class="dotted">${escapeHtml(ticket.purpose || 'N/A')}</span></div><br>
                        </div>

                        <div class="sig-row">
                            <div class="sig-block">
                                <span class="underline" style="width: 200px;"></span><br>
                                <small>Head of Office or his duly<br>Authorized Representative</small>
                            </div>
                        </div>

                        <p><strong>B. To be filled by the Driver:</strong></p>
                        <div class="indent">
                            <div class="line-item">1. Time of departure from Office / Garage: <span class="dotted">${escapeHtml(ticket.dep_office_time || 'N/A')}</span> a.m./p.m.</div>
                            <div class="line-item">2. Time of arrival at (per No. 4 above): <span class="dotted">${escapeHtml(ticket.arr_location_time || 'N/A')}</span> a.m./p.m.</div>
                            <div class="line-item">3. Time of departure from (per No. 4): <span class="dotted">${escapeHtml(ticket.dep_location_time || 'N/A')}</span> a.m./p.m.</div>
                            <div class="line-item">4. Time of arrival back to Office/Garage: <span class="dotted">${escapeHtml(ticket.arr_office_time || 'N/A')}</span> a.m./p.m.</div>
                            <div class="line-item">5. Approximate distance travelled (to and from): <span class="dotted">${escapeHtml(ticket.approx_distance || '0')}</span> kms.</div>
                            <div class="line-item">6. Gasoline issued, purchase and consumed:</div>
                            <div class="indent">
                                <table class="gas-table">
                                    <tr><td style="width: 160px;">a. Balance in Tank:</td><td><span class="dotted">${escapeHtml(ticket.gas_balance_start || '0')}</span> liters</td></tr>
                                    <tr><td>b. Issued by Office from Stock:</td><td><span class="dotted">${escapeHtml(ticket.gas_issued_office || '0')}</span> liters</td></tr>
                                    <tr><td>c. Add purchased during trip:</td><td><span class="dotted">${escapeHtml(ticket.gas_added_trip || '0')}</span> liters</td></tr>
                                    <tr><td style="padding-left: 40px;"><strong>TOTAL. . . :</strong></td><td><span class="dotted"><strong>${gasTotal.toFixed(2)}</strong></span> <strong>liters</strong></td></tr>
                                    <tr><td>d. Deduct Used during the trip (to and from):</td><td><span class="dotted">${escapeHtml(ticket.gas_used_trip || '0')}</span> liters</td></tr>
                                    <tr><td>e. Balance in tank at the end of trip:</td><td><span class="dotted"><strong>${gasEnd.toFixed(2)}</strong></span> liters</td></tr>
                                </table>
                            </div>
                            <div class="line-item">7. Gear oil issued: <span class="dotted">${escapeHtml(ticket.gear_oil_issued || '0')}</span> liters</div>
                            <div class="line-item">8. Lub. Oil issued: <span class="dotted">${escapeHtml(ticket.lub_oil_issued || '0')}</span> liters</div>
                            <div class="line-item">9. Grease issued: <span class="dotted">${escapeHtml(ticket.grease_issued || '0')}</span> liters</div>
                            <div class="line-item">10. Speedometer readings, if any:</div>
                            <div class="indent" style="margin-top: 2px;">
                                <div class="line-item">&nbsp;&nbsp;&nbsp;&nbsp;At beginning of trip <span class="dotted">${escapeHtml(ticket.speedometer_start || '0')}</span> kms.</div>
                                <div class="line-item">&nbsp;&nbsp;&nbsp;&nbsp;At end of trip <span class="dotted">${escapeHtml(ticket.speedometer_end || '0')}</span> kms.</div>
                                <div class="line-item">&nbsp;&nbsp;&nbsp;&nbsp;Distance travelled (per No. 5 above) <span class="dotted">${escapeHtml(ticket.approx_distance || '0')}</span> kms.</div>
                            </div>
                            <div class="line-item">11. Remarks: <span class="dotted">${escapeHtml(ticket.remarks || 'None')}</span></div>
                        </div>

                        <div style="margin-top: 20px; text-align: center; font-style: italic;">
                            I hereby certify to the correctness of the above statement of record of travel.
                        </div>
                        <div class="sig-driver">${escapeHtml(ticket.full_name || '')}</div>
                        <div class="sig-box" style="width: 250px; margin-top: 0px;" id="p_driver_sig"></div>
                        <div style="text-align: center; margin-top: 5px; font-weight: bold;">Driver</div>
                        
                        <div style="margin-top: 15px; text-align: center;">
                            I hereby certify that I used this car on official business as stated above.
                        </div>
                        
                        <div style="display: flex; justify-content: space-between; gap: 10px; font-size: 9pt; align-items: flex-start;">
                            <div style="flex: 1; min-width: 0; display: flex; flex-direction: column;">
                                <div style="border-bottom: 1px solid #000; padding: 8px 0; margin-bottom: 4px; min-height: 40px; word-break: break-word; display: flex; align-items: center; justify-content: center; gap: 15px;">
                                    <div style="font-weight: normal; word-break: break-word;">${(ticket.passenger_1_name) ? escapeHtml(ticket.passenger_1_name) : ''}</div>
                                    <div style="font-size: 8pt;">${(ticket.passenger_1_date && ticket.passenger_1_date !== '' && ticket.passenger_1_date !== '0000-00-00') ? escapeHtml(ticket.passenger_1_date) : ''}</div>
                                </div>
                                <small style="font-size: 8pt;">Name of Passenger / Date</small>
                            </div>
                            <div style="flex: 1; min-width: 0; display: flex; flex-direction: column;">
                                <div style="border-bottom: 1px solid #000; padding: 8px 0; margin-bottom: 4px; min-height: 40px; word-break: break-word; display: flex; align-items: center; justify-content: center; gap: 15px;">
                                    <div style="font-weight: normal; word-break: break-word;">${(ticket.passenger_2_name) ? escapeHtml(ticket.passenger_2_name) : ''}</div>
                                    <div style="font-size: 8pt;">${(ticket.passenger_2_date && ticket.passenger_2_date !== '' && ticket.passenger_2_date !== '0000-00-00') ? escapeHtml(ticket.passenger_2_date) : ''}</div>
                                </div>
                                <small style="font-size: 8pt;">Name of Passenger / Date</small>
                            </div>
                            <div style="flex: 1; min-width: 0; display: flex; flex-direction: column;">
                                <div style="border-bottom: 1px solid #000; padding: 8px 0; margin-bottom: 4px; min-height: 40px; word-break: break-word; display: flex; align-items: center; justify-content: center; gap: 15px;">
                                    <div style="font-weight: normal; word-break: break-word;">${(ticket.passenger_3_name) ? escapeHtml(ticket.passenger_3_name) : ''}</div>
                                    <div style="font-size: 8pt;">${(ticket.passenger_3_date && ticket.passenger_3_date !== '' && ticket.passenger_3_date !== '0000-00-00') ? escapeHtml(ticket.passenger_3_date) : ''}</div>
                                </div>
                                <small style="font-size: 8pt;">Name of Passenger / Date</small>
                            </div>
                        </div>
                    `;
                } else {
                    previewDiv.innerHTML = '<div class="loading" style="color: #d32f2f;">Error loading ticket details</div>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                previewDiv.innerHTML = '<div class="loading" style="color: #d32f2f;">Error loading ticket details</div>';
            });
    }

    // Print ticket function
    function printTicket(ticketId) {
        const previewDiv = document.querySelector(`#details-${ticketId} .ticket-preview`);
        const printWindow = window.open('', 'Print Ticket', 'height=900,width=1000');
        printWindow.document.write('<html><head><title></title>');
        printWindow.document.write('<style>');
        printWindow.document.write('@page { margin: 0; padding: 0; size: letter; }');
        printWindow.document.write('* { margin: 0; padding: 0; box-sizing: border-box; }');
        printWindow.document.write('html, body { margin: 0; padding: 0; width: 100%; height: 100%; }');
        printWindow.document.write('body { font-family: "Times New Roman", Times, serif; line-height: 1.15; color: #000; font-size: 10.5pt; }');
        printWindow.document.write('.ticket-preview { width: 8.5in; background: white; padding: 0.5in; border: none; font-family: "Times New Roman", serif; line-height: 1.15; color: black; font-size: 10.5pt; margin: 0 auto; page-break-after: always; position: relative; }');
        printWindow.document.write('.appendix { position: absolute; font-weight: bold; right: 0.5in; top: 0.4in; font-size: 10.5pt; }');
        printWindow.document.write('.header-section { text-align: center; margin-bottom: 10px; line-height: 1.3; font-size: 10.5pt; }');
        printWindow.document.write('.header-section img { width: 80px; height: 80px; border-radius: 4px; margin-right: 15px; margin-bottom: 8px; float: left; }');
        printWindow.document.write('.header-section strong { display: block; font-weight: bold; font-size: 14pt; }');
        printWindow.document.write('.fire-station-name { text-decoration: underline; font-weight: bold; }');
        printWindow.document.write('.control-no { text-align: right; margin-bottom: 8px; font-weight: bold; font-size: 10.5pt; }');
        printWindow.document.write('h3 { text-align: center; margin: 15px 0 10px 0; font-size: 11pt; font-weight: bold; }');
        printWindow.document.write('.section-title { font-weight: bold; margin-top: 8px; margin-bottom: 6px; font-size: 10.5pt; }');
        printWindow.document.write('.indent { margin-left: 25px; }');
        printWindow.document.write('ol, ul { margin: 0; padding-left: 0; }');
        printWindow.document.write('li { margin-bottom: 3px; font-size: 10.5pt; }');
        printWindow.document.write('.line-item { display: flex; align-items: flex-end; margin-bottom: 3px; font-size: 10.5pt; }');
        printWindow.document.write('.underline { border-bottom: 1px solid black; display: inline-block; min-width: 100px; padding: 0 5px; text-align: center; }');
        printWindow.document.write('.dotted { border-bottom: 1px solid black; flex: 1; margin-left: 5px; min-height: 1.1em; padding-left: 5px; }');
        printWindow.document.write('.gas-table { width: 100%; border-collapse: collapse; margin: -7px 0; font-size: 7.5pt; }');
        printWindow.document.write('.gas-table td { padding: 2px 0; border: none; vertical-align: baseline; }');
        printWindow.document.write('.gas-table td:first-child { padding-right: 5px; white-space: nowrap; }');
        printWindow.document.write('.gas-table td:last-child { padding-left: 5px; display: flex; align-items: baseline; gap: 3px; }');
        printWindow.document.write('.gas-table .dotted { border-bottom: 1px solid black; flex: 1; min-width: 40px; padding: 0 2px; text-align: center; display: flex; align-items: center; justify-content: center; }');
        printWindow.document.write('.gas-table td.gas-value { border-bottom: 1px solid black; text-align: center; min-width: 80px; }');
        printWindow.document.write('.gas-table td.gas-unit { width: 40px; text-align: left; padding-left: 5px; flex-shrink: 0; }');
        printWindow.document.write('.sig-driver { width: 250px; margin: 20px auto 0 auto; text-align: center; font-weight: bold; display: block; }');
        printWindow.document.write('.sig-box { width: 250px; border-top: 1px solid black; text-align: center; padding-top: 5px; font-weight: bold; margin: 25px auto 5px auto; font-size: 10.5pt; }');
        printWindow.document.write('.sig-row { display: flex; justify-content: flex-end; margin: 15px 0; }');
        printWindow.document.write('.sig-block { text-align: center; }');
        printWindow.document.write('.sig-block .underline { width: 200px; margin-bottom: 5px; }');
        printWindow.document.write('.sig-block small { display: block; font-size: 9pt; margin-top: 0; }');
        printWindow.document.write('@media print { * { margin: 0; padding: 0; } body { margin: 0; padding: 0; } .ticket-preview { box-shadow: none; margin: 0; padding: 0.5in; } }');
        printWindow.document.write('</style></head><body>');
        printWindow.document.write('<div class="ticket-preview">');
        // Use the preview HTML directly for print
        let html = previewDiv.innerHTML;
        printWindow.document.write(html);
        printWindow.document.write('</div>');
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        setTimeout(() => {
            printWindow.print();
        }, 250);
    }

    // Edit ticket function
    function editTicket(ticketId) {
        window.location.href = 'Edit_Report.php?id=' + ticketId;
    }

    // Submit ticket function
    function submitTicket(ticketId) {
        if (confirm('Are you sure you want to submit this ticket? Once submitted, it cannot be edited.')) {
            // Send AJAX request to update status
            fetch('submit_ticket_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=submit_ticket&ticket_id=' + ticketId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✓ Ticket submitted successfully!');
                    // Reload the page to refresh the status
                    location.reload();
                } else {
                    alert('✗ Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('✗ An error occurred: ' + error);
            });
        }
    }

    // Take Task function - Update status to Active
    function takeTask(ticketId) {
        if (confirm('Are you sure you want to take this task? Status will be updated to Active.')) {
            fetch('update_ticket_status.php?id=' + ticketId + '&status=Active', { method: 'POST' })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('✅ Task taken! Status updated to Active.');
                        location.reload();
                    } else {
                        alert('Error: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    alert('Error taking task');
                    console.error('Error:', error);
                });
        }
    }

    // Sidebar dropdown functionality
    document.querySelectorAll('.dropdown').forEach(item => {
        item.addEventListener('click', function (e) {
            // Don't toggle if clicking a direct link inside the submenu
            if (e.target.closest('a')) return;
            
            const isActive = this.classList.contains('active');
            
            // Accordion effect: Close others
            document.querySelectorAll('.dropdown').forEach(other => {
                other.classList.remove('active');
            });

            // Toggle current
            if (!isActive) {
                this.classList.add('active');
            }
        });
    });

    // Helper function to escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Helper function to format date in full format
    function formatDateFull(dateString) {
        const date = new Date(dateString);
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        return date.toLocaleDateString('en-US', options);
    }

    function switchTab(tabName) {
        // Hide all tabs
        document.getElementById('pending-tab').classList.remove('active');
        document.getElementById('active-tab').classList.remove('active');
        
        // Remove active class from all buttons
        document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
        
        // Show selected tab
        document.getElementById(tabName + '-tab').classList.add('active');
        event.target.classList.add('active');
    }

    // Helper function to escape quotes in strings
    function escapeQuotes(str) {
        if (!str) return '';
        return str.replace(/"/g, '\\"').replace(/'/g, "\\'");
    }

    // Download Ticket as Word Document
    function downloadTicketAsWord(ticketId, driverName, vehiclePlate, controlNo) {
        // Fetch full ticket details
        fetch('get_ticket_details.php?id=' + ticketId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const ticket = data.ticket;
                    const ticketDate = new Date(ticket.ticket_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
                    
                    // Create Word document content as HTML-based format
                    const docContent = `
<!DOCTYPE html>
<html xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
<head>
    <meta charset="UTF-8">
    <title>${ticket.control_no} - Trip Ticket</title>
</head>
<body style="font-family: 'Times New Roman', serif; font-size: 11pt; line-height: 1.15;">
    <div style="text-align: center; margin-bottom: 20px;">
        <h1 style="margin: 5px 0; font-size: 14pt;">Republic of the Philippines</h1>
        <p style="margin: 2px 0; font-size: 11pt;">Province of Southern Leyte</p>
        <h2 style="margin: 5px 0; font-size: 14pt; font-weight: bold;">CITY OF MAASIN</h2>
        <p style="margin: 2px 0; font-size: 11pt;">Office of the <span style="text-decoration: underline; font-weight: bold;">MAASIN CITY FIRE STATION</span></p>
    </div>

    <p style="text-align: right; font-weight: bold;">Control No: ${escapeHtml(controlNo)}</p>

    <h3 style="text-align: center; margin: 15px 0; font-size: 12pt; font-weight: bold;">DRIVER'S TRIP TICKET</h3>
    <p style="text-align: center; margin-bottom: 15px; font-size: 11pt;">(${ticketDate})</p>

    <p style="margin: 10px 0;"><strong>A. To be filled by the Administrative Official Authorizing Official Travel:</strong></p>
    <div style="margin-left: 25px; margin-bottom: 10px;">
        <p style="margin: 5px 0;">1. Name of Driver of the Vehicle: <span style="border-bottom: 1px solid #000; padding: 0 5px;">${escapeHtml(ticket.full_name || 'N/A')}</span></p>
        <p style="margin: 5px 0;">2. Government car to be used. Plate No.: <span style="border-bottom: 1px solid #000; padding: 0 5px;">${escapeHtml(ticket.vehicle_plate_no)}</span></p>
        <p style="margin: 5px 0;">3. Name of Authorized Passenger: <span style="border-bottom: 1px solid #000; padding: 0 5px;">${escapeHtml(ticket.authorized_passenger || 'N/A')}</span></p>
        <p style="margin: 5px 0;">4. Place or places to be visited/inspected: <span style="border-bottom: 1px solid #000; padding: 0 5px;">${escapeHtml(ticket.places_to_visit || 'N/A')}</span></p>
        <p style="margin: 5px 0;">5. Purpose: <span style="border-bottom: 1px solid #000; padding: 0 5px;">${escapeHtml(ticket.purpose || 'N/A')}</span></p>
    </div>

    <p style="margin: 15px 0 10px 0;"><strong>B. To be filled by the Driver:</strong></p>
    <div style="margin-left: 25px; margin-bottom: 10px;">
        <p style="margin: 5px 0;">1. Time of departure from Office / Garage: <span style="border-bottom: 1px solid #000; padding: 0 5px;">${escapeHtml(ticket.dep_office_time || 'N/A')}</span> a.m./p.m.</p>
        <p style="margin: 5px 0;">2. Time of arrival at (per No. 4 above): <span style="border-bottom: 1px solid #000; padding: 0 5px;">${escapeHtml(ticket.arr_location_time || 'N/A')}</span> a.m./p.m.</p>
        <p style="margin: 5px 0;">3. Time of departure from (per No. 4): <span style="border-bottom: 1px solid #000; padding: 0 5px;">${escapeHtml(ticket.dep_location_time || 'N/A')}</span> a.m./p.m.</p>
        <p style="margin: 5px 0;">4. Time of arrival back to Office/Garage: <span style="border-bottom: 1px solid #000; padding: 0 5px;">${escapeHtml(ticket.arr_office_time || 'N/A')}</span> a.m./p.m.</p>
        <p style="margin: 5px 0;">5. Approximate distance travelled (to and from): <span style="border-bottom: 1px solid #000; padding: 0 5px;">${escapeHtml(ticket.approx_distance || '0')}</span> kms.</p>
        
        <p style="margin: 10px 0 5px 0;">6. Gasoline issued, purchase and consumed:</p>
        <table style="margin-left: 40px; border-collapse: collapse; margin-bottom: 10px;">
            <tr><td style="width: 250px; padding: 3px 0;">a. Balance in Tank:</td><td style="border-bottom: 1px solid #000; padding: 0 5px; width: 100px;"><span>${escapeHtml(ticket.gas_balance_start || '0')}</span></td><td style="padding: 0 5px;">liters</td></tr>
            <tr><td style="padding: 3px 0;">b. Issued by Office from Stock:</td><td style="border-bottom: 1px solid #000; padding: 0 5px;"><span>${escapeHtml(ticket.gas_issued_office || '0')}</span></td><td style="padding: 0 5px;">liters</td></tr>
            <tr><td style="padding: 3px 0;">c. Add purchased during trip:</td><td style="border-bottom: 1px solid #000; padding: 0 5px;"><span>${escapeHtml(ticket.gas_added_trip || '0')}</span></td><td style="padding: 0 5px;">liters</td></tr>
            <tr><td style="padding: 3px 0; font-weight: bold;">TOTAL . . . :</td><td style="border-bottom: 1px solid #000; padding: 0 5px; font-weight: bold;"><span>${(parseFloat(ticket.gas_balance_start || 0) + parseFloat(ticket.gas_issued_office || 0) + parseFloat(ticket.gas_added_trip || 0)).toFixed(2)}</span></td><td style="padding: 0 5px; font-weight: bold;">liters</td></tr>
            <tr><td style="padding: 3px 0;">d. Deduct Used during the trip (to and from):</td><td style="border-bottom: 1px solid #000; padding: 0 5px;"><span>${escapeHtml(ticket.gas_used_trip || '0')}</span></td><td style="padding: 0 5px;">liters</td></tr>
            <tr><td style="padding: 3px 0;">e. Balance in tank at the end of trip:</td><td style="border-bottom: 1px solid #000; padding: 0 5px; font-weight: bold;"><span>${(parseFloat(ticket.gas_balance_start || 0) + parseFloat(ticket.gas_issued_office || 0) + parseFloat(ticket.gas_added_trip || 0) - parseFloat(ticket.gas_used_trip || 0)).toFixed(2)}</span></td><td style="padding: 0 5px; font-weight: bold;">liters</td></tr>
        </table>
        
        <p style="margin: 5px 0;">7. Gear oil issued: <span style="border-bottom: 1px solid #000; padding: 0 5px;">${escapeHtml(ticket.gear_oil_issued || '0')}</span> liters</p>
        <p style="margin: 5px 0;">8. Lub. Oil issued: <span style="border-bottom: 1px solid #000; padding: 0 5px;">${escapeHtml(ticket.lub_oil_issued || '0')}</span> liters</p>
        <p style="margin: 5px 0;">9. Grease issued: <span style="border-bottom: 1px solid #000; padding: 0 5px;">${escapeHtml(ticket.grease_issued || '0')}</span> liters</p>
        
        <p style="margin: 10px 0 5px 0;">10. Speedometer readings, if any:</p>
        <div style="margin-left: 40px;">
            <p style="margin: 3px 0;">At beginning of trip <span style="border-bottom: 1px solid #000; padding: 0 5px;">${escapeHtml(ticket.speedometer_start || '0')}</span> kms.</p>
            <p style="margin: 3px 0;">At end of trip <span style="border-bottom: 1px solid #000; padding: 0 5px;">${escapeHtml(ticket.speedometer_end || '0')}</span> kms.</p>
            <p style="margin: 3px 0;">Distance travelled (per No. 5 above) <span style="border-bottom: 1px solid #000; padding: 0 5px;">${escapeHtml(ticket.approx_distance || '0')}</span> kms.</p>
        </div>
        
        <p style="margin: 5px 0;">11. Remarks: <span style="border-bottom: 1px solid #000; padding: 0 5px;">${escapeHtml(ticket.remarks || 'None')}</span></p>
    </div>

    <div style="margin-top: 30px; text-align: center; font-style: italic;">
        <p>I hereby certify to the correctness of the above statement of record of travel.</p>
    </div>

    <div style="margin-top: 20px;">
        <p style="text-align: center; font-weight: bold; margin: 20px 0 5px 0;">${escapeHtml(ticket.full_name || '')}</p>
        <p style="text-align: center; margin: 40px 0 5px 0;">_________________________________</p>
        <p style="text-align: center; margin: 0; font-weight: bold;">Driver</p>
    </div>

    <div style="margin-top: 20px; text-align: center;">
        I hereby certify that I used this car on official business as stated above.
    </div>

    <p style="margin-top: 20px; color: #999; font-size: 9pt; text-align: center;">
        Document Generated: ${new Date().toLocaleString()}<br>
        Control No: ${escapeHtml(controlNo)}
    </p>
</body>
</html>`;

                    // Create blob and download
                    const blob = new Blob([docContent], { type: 'application/msword' });
                    const url = window.URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = `Trip_Ticket_${controlNo || ticketId}.doc`;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    window.URL.revokeObjectURL(url);
                } else {
                    alert('Error: Unable to load ticket details');
                }
            })
            .catch(error => {
                alert('Error downloading ticket: ' + error.message);
            });
    }
</script>

</body>
</html>