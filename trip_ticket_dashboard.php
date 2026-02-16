<?php
session_start();
include("db_connect.php");

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query based on filters
$query = "SELECT tt.id, tt.control_no, tt.ticket_date, tt.driver_id, d.full_name, 
                 tt.vehicle_plate_no as plate_no, tt.status, tt.created_at, tt.qr_code
          FROM trip_tickets tt
          LEFT JOIN drivers d ON tt.driver_id = d.driver_id
          WHERE 1=1";

$params = [];

if ($status_filter !== 'all') {
    $query .= " AND tt.status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $query .= " AND (tt.control_no LIKE ? OR d.full_name LIKE ? OR tt.vehicle_plate_no LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$query .= " ORDER BY tt.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

// Count by status - safely check if column exists
try {
    $count_stmt = $pdo->query("SELECT status, COUNT(*) as count FROM trip_tickets WHERE status IS NOT NULL GROUP BY status");
    $status_counts = $count_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    $status_counts = [];
}

// Get total count
$total_count = array_sum($status_counts);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Trip Ticket Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <style>
        :root { --bfp-red: #B22222; --sidebar-bg: #1e1e2d; --sidebar-border: #2a2a3e; --accent-blue: #5d5dff; --text-light: #a2a2c2; --text-primary: #e0e0ff; --transition-speed: 0.4s; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', 'Segoe UI', Arial, sans-serif; background-color: var(--sidebar-bg); display: flex; flex-direction: column; min-height: 100vh; -webkit-font-smoothing: antialiased; -webkit-touch-callout: none; }
        header { background: linear-gradient(90deg, var(--bfp-red) 60%, #FF4500 100%); color: white; padding: 15px 30px; display: none; align-items: center; justify-content: space-between; box-shadow: 0 2px 5px rgba(0,0,0,0.2); z-index: 100; }
        .wrapper { display: flex; flex: 1; overflow: hidden; }
        main { flex: 1; padding: 30px; overflow-y: auto; background-color: var(--sidebar-bg); }
        .dashboard-header { margin-bottom: 30px; animation: slideInDown 0.6s ease-out; }
        .dashboard-header h1 { font-size: 2.4rem; color: var(--text-primary); margin-bottom: 8px; font-weight: 700; text-align: center; letter-spacing: -0.5px; }
        .dashboard-header p { color: var(--text-light); margin-bottom: 32px; font-size: 1rem; text-align: center; font-weight: 500; }
        .dashboard-main { display: grid; grid-template-columns: 1fr; gap: 20px; transition: all 0.4s cubic-bezier(0.25, 1, 0.5, 1); }
        .dashboard-main.analytics-active { grid-template-columns: 1fr 1.5fr; align-items: start; }
        .left-panel { display: none; }
        .dashboard-main.analytics-active .left-panel { display: block; grid-column: 1; }
        .header-content { display: none; }
        .dashboard-main.analytics-active .header-content { display: none; }
        .dashboard-main.analytics-active .right-panel { grid-column: 2; display: flex; flex-direction: column; gap: 20px; }
        .dashboard-main.analytics-active .stats { flex-direction: row; flex-wrap: wrap; gap: 15px; }
        .dashboard-main.analytics-active .stats .stat-card { flex: 1; min-width: 120px; padding: 12px 14px; }
        .dashboard-main.analytics-active .analytics-section { width: 100%; max-height: none; opacity: 1 !important; animation: slideInUp 0.6s ease-out; }
        .header-content-inactive { display: grid; grid-template-columns: 1fr 1.2fr; gap: 20px; align-items: start; transition: all 0.4s cubic-bezier(0.25, 1, 0.5, 1); }
        .left-panel { display: flex; flex-direction: column; gap: 20px; }
        .right-panel { display: flex; flex-direction: column; }
        .stats.inline-stats { gap: 12px; flex-direction: row; flex-wrap: wrap; }
        .stats.inline-stats .stat-card { flex: 1 1 calc(33.333% - 8px); min-width: 110px; padding: 10px 12px; border-left-width: 3px; box-shadow: none; transform: none; animation: none; font-size: 0.85rem; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; }
        .stats.inline-stats .stat-card:hover { transform: none; box-shadow: none; }
        .stats.inline-stats .stat-card h3 { font-size: 0.7rem; margin-bottom: 4px; }
        .stats.inline-stats .stat-card .number { font-size: 1.4rem; }
        .header-wrapper { display: contents; }
        .header-controls { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 24px; }
        .control-btn { background: linear-gradient(135deg, rgba(93, 93, 255, 0.1) 0%, rgba(93, 93, 255, 0.05) 100%); border: 1px solid rgba(93, 93, 255, 0.3); color: var(--accent-blue); padding: 10px 18px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; transition: all 0.3s cubic-bezier(0.25, 1, 0.5, 1); }
        .control-btn:hover { background: linear-gradient(135deg, rgba(93, 93, 255, 0.2) 0%, rgba(93, 93, 255, 0.1) 100%); border-color: rgba(93, 93, 255, 0.5); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(93, 93, 255, 0.2); }
        .control-btn.active { background: linear-gradient(135deg, rgba(93, 93, 255, 0.25) 0%, rgba(93, 93, 255, 0.15) 100%); border-color: var(--accent-blue); }
        .stats { display: flex; flex-direction: column; gap: 12px; }
        .stat-card { background: linear-gradient(135deg, rgba(93, 93, 255, 0.08) 0%, rgba(93, 93, 255, 0.03) 100%); padding: 14px 18px; border-radius: 10px; border: 1px solid rgba(93, 93, 255, 0.2); border-left: 4px solid var(--accent-blue); transition: all 0.3s cubic-bezier(0.25, 1, 0.5, 1); animation: slideInUp 0.6s ease-out; display: flex; align-items: center; justify-content: space-between; }
        .stat-card:hover { background: linear-gradient(135deg, rgba(93, 93, 255, 0.12) 0%, rgba(93, 93, 255, 0.06) 100%); border-color: rgba(93, 93, 255, 0.4); transform: translateY(-2px); box-shadow: 0 6px 16px rgba(93, 93, 255, 0.15); }
        .stat-card:nth-child(2) { border-left-color: #ffc107; background: linear-gradient(135deg, rgba(255, 193, 7, 0.08) 0%, rgba(255, 152, 0, 0.03) 100%); }
        .stat-card:nth-child(2):hover { background: linear-gradient(135deg, rgba(255, 193, 7, 0.12) 0%, rgba(255, 152, 0, 0.06) 100%); border-color: rgba(255, 152, 0, 0.4); }
        .stat-card:nth-child(3) { border-left-color: #28a745; background: linear-gradient(135deg, rgba(40, 167, 69, 0.08) 0%, rgba(32, 201, 151, 0.03) 100%); }
        .stat-card:nth-child(3):hover { background: linear-gradient(135deg, rgba(40, 167, 69, 0.12) 0%, rgba(32, 201, 151, 0.06) 100%); border-color: rgba(32, 201, 151, 0.4); }
        .stat-card h3 { color: var(--text-light); font-size: 0.8rem; font-weight: 600; margin-bottom: 0; text-transform: uppercase; letter-spacing: 0.6px; flex: 1; }
        .stat-card .number { font-size: 1.8rem; font-weight: 700; color: var(--accent-blue); margin: 0; text-align: right; }
        .stat-card:nth-child(2) .number { color: #ff9800; }
        .stat-card:nth-child(3) .number { color: #28a745; }
        .analytics-section { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; max-height: 0; opacity: 0; margin-bottom: 0; overflow: hidden; transition: all 0.4s cubic-bezier(0.25, 1, 0.5, 1); }
        .analytics-section.collapsed { max-height: 0; opacity: 0; }
        .dashboard-main.analytics-active .analytics-section { max-height: 800px; opacity: 1; }
        .chart-card { background: linear-gradient(135deg, rgba(93, 93, 255, 0.08) 0%, rgba(93, 93, 255, 0.03) 100%); padding: 20px; border-radius: 12px; border: 1px solid rgba(93, 93, 255, 0.2); transition: all 0.3s ease; }
        .chart-card:hover { background: linear-gradient(135deg, rgba(93, 93, 255, 0.12) 0%, rgba(93, 93, 255, 0.06) 100%); border-color: rgba(93, 93, 255, 0.4); }
        .chart-card h3 { color: var(--accent-blue); font-size: 1.1rem; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 2px solid rgba(93, 93, 255, 0.2); }

        .status-chart { display: flex; align-items: center; justify-content: space-around; height: 200px; }
        .chart-item { text-align: center; flex: 1; }
        .chart-circle { width: 90px; height: 90px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px; font-weight: 700; color: white; font-size: 1.6rem; background: linear-gradient(135deg, var(--accent-blue) 0%, rgba(93, 93, 255, 0.7) 100%); box-shadow: 0 8px 20px rgba(93, 93, 255, 0.3); }
        .chart-circle.pending { background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%); box-shadow: 0 8px 20px rgba(255, 152, 0, 0.3); }
        .chart-circle.submitted { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); box-shadow: 0 8px 20px rgba(40, 199, 151, 0.3); }
        .chart-label { font-size: 0.9rem; color: var(--text-light); margin-top: 8px; font-weight: 600; }
        .summary-box { background: linear-gradient(135deg, rgba(93, 93, 255, 0.12) 0%, rgba(93, 93, 255, 0.06) 100%); border: 1px solid rgba(93, 93, 255, 0.3); color: var(--text-primary); padding: 24px; border-radius: 12px; animation: slideInUp 0.6s ease-out 0.1s both; }
        .dashboard-main.analytics-active .summary-box { padding: 32px; min-height: 390px; display: flex; flex-direction: column; justify-content: space-around; }
        .summary-box h3 { color: var(--text-primary); margin-bottom: 18px; font-size: 1.1rem; font-weight: 700; }
        .dashboard-main.analytics-active .summary-box h3 { font-size: 1.3rem; margin-bottom: 24px; }
        .summary-items { display: flex; flex-direction: column; gap: 12px; }
        .dashboard-main.analytics-active .summary-items { gap: 16px; }
        .summary-item { background: linear-gradient(135deg, rgba(93, 93, 255, 0.1) 0%, rgba(93, 93, 255, 0.05) 100%); padding: 12px 14px; border-radius: 8px; border-left: 3px solid var(--accent-blue); transition: all 0.3s ease; display: flex; align-items: center; justify-content: space-between; }
        .dashboard-main.analytics-active .summary-item { padding: 16px 18px; }
        .summary-item:hover { background: linear-gradient(135deg, rgba(93, 93, 255, 0.15) 0%, rgba(93, 93, 255, 0.08) 100%); transform: translateY(-2px); }
        .summary-item:nth-child(2) { border-left-color: #ffc107; background: linear-gradient(135deg, rgba(255, 193, 7, 0.1) 0%, rgba(255, 152, 0, 0.05) 100%); }
        .summary-item:nth-child(2):hover { background: linear-gradient(135deg, rgba(255, 193, 7, 0.15) 0%, rgba(255, 152, 0, 0.08) 100%); }
        .summary-item:nth-child(3) { border-left-color: #28a745; background: linear-gradient(135deg, rgba(40, 167, 69, 0.1) 0%, rgba(32, 201, 151, 0.05) 100%); }
        .summary-item:nth-child(3):hover { background: linear-gradient(135deg, rgba(40, 167, 69, 0.15) 0%, rgba(32, 201, 151, 0.08) 100%); }
        .summary-item label { font-size: 0.8rem; color: var(--text-light); font-weight: 600; margin-bottom: 0; }
        .summary-item .value { font-size: 1.5rem; font-weight: 700; color: var(--accent-blue); margin-left: 12px; }
        .summary-item:nth-child(2) .value { color: #ff9800; }
        .summary-item:nth-child(3) .value { color: #28a745; }

        /* Filter Enhancement */
        .advanced-filters { background: linear-gradient(135deg, rgba(93, 93, 255, 0.08) 0%, rgba(93, 93, 255, 0.03) 100%); padding: 16px; border-radius: 8px; margin-bottom: 20px; border: 1px solid rgba(93, 93, 255, 0.2); display: none; transition: all 0.3s ease; animation: slideDown 0.3s ease; }
        .advanced-filters.show { display: block; }
        .filter-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 15px; }
        .filter-group-item { display: flex; flex-direction: column; }
        .filter-group-item label { font-weight: 600; color: var(--text-primary); margin-bottom: 6px; font-size: 0.9rem; }
        .filter-group-item input, .filter-group-item select { padding: 10px 12px; border: 1px solid rgba(93, 93, 255, 0.3); border-radius: 6px; font-size: 0.9rem; background: rgba(93, 93, 255, 0.05); color: var(--text-primary); transition: all 0.2s ease; }
        .filter-group-item input:focus, .filter-group-item select:focus { outline: none; border-color: var(--accent-blue); background: rgba(93, 93, 255, 0.1); box-shadow: 0 0 8px rgba(93, 93, 255, 0.2); }
        .toggle-advanced { background: none; border: none; color: var(--accent-blue); cursor: pointer; font-weight: 600; padding: 0; text-decoration: none; display: flex; align-items: center; gap: 6px; transition: all 0.2s ease; }
        .toggle-advanced:hover { color: rgba(93, 93, 255, 0.8); }
        /* Export Button */
        .export-btn { background: linear-gradient(135deg, var(--accent-blue) 0%, rgba(93, 93, 255, 0.8) 100%); color: white; border: none; padding: 10px 18px; border-radius: 6px; cursor: pointer; font-weight: 600; transition: all 0.2s ease; display: flex; align-items: center; gap: 6px; font-size: 0.9rem; }
        .export-btn:hover { box-shadow: 0 4px 12px rgba(93, 93, 255, 0.3); transform: translateY(-2px); }
        /* Performance Indicators */
        .performance-card { background: linear-gradient(135deg, rgba(93, 93, 255, 0.08) 0%, rgba(93, 93, 255, 0.03) 100%); padding: 20px; border-radius: 12px; border: 1px solid rgba(93, 93, 255, 0.2); }
        .performance-metric { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid rgba(93, 93, 255, 0.1); }
        .performance-metric:last-child { border-bottom: none; }
        .metric-label { font-weight: 600; color: var(--text-primary); }
        .metric-bar { flex: 1; height: 6px; background: rgba(93, 93, 255, 0.1); border-radius: 3px; margin: 0 15px; overflow: hidden; }
        .metric-bar-fill { height: 100%; background: linear-gradient(90deg, var(--accent-blue), rgba(93, 93, 255, 0.6)); border-radius: 3px; }
        .metric-value { font-weight: 700; color: var(--accent-blue); min-width: 50px; text-align: right; }
        
        .trip-list { background: linear-gradient(135deg, rgba(93, 93, 255, 0.05) 0%, rgba(93, 93, 255, 0.02) 100%); border-radius: 12px; padding: 20px; border: 1px solid rgba(93, 93, 255, 0.15); }
        .filter-controls { display: flex; justify-content: space-between; align-items: center; gap: 1.5rem;margin-top: 20px  ;margin-bottom: 24px; flex-wrap: wrap; }
        .date-filters { display: flex; gap: 10px; }
        .date-select { padding: 10px 16px; border: 1px solid rgba(93, 93, 255, 0.3); border-radius: 8px; font-size: 0.95rem; background: linear-gradient(135deg, rgba(93, 93, 255, 0.05) 0%, rgba(93, 93, 255, 0.02) 100%); cursor: pointer; transition: all 0.2s ease; color: var(--text-light); font-weight: 500; }
        .date-select:hover { border-color: rgba(93, 93, 255, 0.6); background: linear-gradient(135deg, rgba(93, 93, 255, 0.1) 0%, rgba(93, 93, 255, 0.05) 100%); }
        .date-select:focus { outline: none; border-color: var(--accent-blue); }
        /* Trip List Item Styles */
        .trip-item { background: linear-gradient(135deg, rgba(93, 93, 255, 0.08) 0%, rgba(93, 93, 255, 0.03) 100%); border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 12px; transition: all 0.3s cubic-bezier(0.25, 1, 0.5, 1); border: 1px solid rgba(93, 93, 255, 0.2); cursor: pointer; overflow: hidden; }
        .trip-item:hover { box-shadow: 0 8px 24px rgba(93, 93, 255, 0.2); transform: translateY(-3px); border-color: rgba(93, 93, 255, 0.4); }
        .trip-item.active { box-shadow: 0 12px 32px rgba(93, 93, 255, 0.3); border-color: var(--accent-blue); }
        .trip-summary { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; font-size: 1rem; font-weight: 500; color: var(--text-primary); }
        .trip-summary .summary-left { display: flex; align-items: center; gap: 16px; flex: 1; }
        .trip-summary .summary-left i { font-size: 1.2rem; color: var(--accent-blue); }
        .driver-info { display: flex; flex-direction: column; gap: 2px; }
        .driver-name { font-weight: 700; color: var(--accent-blue); font-size: 1rem; }
        .trip-datetime { font-size: 0.85rem; color: var(--text-light); }
        .trip-summary .summary-right { display: flex; align-items: center; gap: 12px; color: var(--text-light); }
        .arrow-expand { border: solid var(--accent-blue); border-width: 0 2px 2px 0; display: inline-block; padding: 4px; transform: rotate(-45deg); transition: transform 0.3s cubic-bezier(0.68, -0.55, 0.27, 1.55); }
        .trip-item.active .arrow-expand { transform: rotate(45deg); }
        /* Trip Details - Expandable Section */
        .trip-details { display: none; background: linear-gradient(135deg, rgba(93, 93, 255, 0.04) 0%, rgba(0, 0, 0, 0.05) 100%); border-top: 1px solid rgba(93, 93, 255, 0.2); padding: 30px; font-size: 0.9rem; color: var(--text-primary); }
        .trip-item.active .trip-details { display: block; animation: expandDown 0.3s ease; }
        @keyframes expandDown { from { opacity: 0; max-height: 0; } to { opacity: 1; max-height: 2000px; } }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes slideInDown { from { opacity: 0; transform: translateY(-30px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes slideInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }

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
        .badge { background: rgba(93, 93, 255, 0.15); color: var(--accent-blue); padding: 4px 8px; border-radius: 4px; font-weight: 600; font-size: 0.85rem; display: inline-block; width: fit-content; }
        .fuel-badge { background: rgba(255, 152, 0, 0.15); color: #ff9800; padding: 4px 8px; border-radius: 4px; font-weight: 600; font-size: 0.85rem; display: inline-block; width: fit-content; }
        .btn-view { background: linear-gradient(90deg, var(--accent-blue) 60%, rgba(93, 93, 255, 0.7) 100%); color: white; border: none; font-weight: 600; font-size: 0.85rem; padding: 8px 16px; border-radius: 6px; cursor: pointer; transition: all 0.2s; }
        .btn-view:hover { box-shadow: 0 4px 12px rgba(93, 93, 255, 0.3); transform: translateY(-2px); }

        /* Ticket Actions Sidebar */
        .ticket-actions-container { display: flex; gap: 30px; align-items: flex-start; }
        .ticket-actions-sidebar { flex-shrink: 0; width: 180px; display: flex; flex-direction: column; gap: 12px; }
        .action-button { background: linear-gradient(135deg, var(--accent-blue) 0%, rgba(93, 93, 255, 0.7) 100%); color: white; border: none; font-weight: 600; font-size: 0.9rem; padding: 10px 14px; border-radius: 6px; cursor: pointer; transition: all 0.2s ease; display: flex; align-items: center; justify-content: center; gap: 8px; text-align: center; }
        .action-button:hover { box-shadow: 0 4px 12px rgba(93, 93, 255, 0.3); transform: translateY(-2px); }
        .action-button i { font-size: 1rem; }
        .qr-code-box { background: linear-gradient(135deg, rgba(93, 93, 255, 0.08) 0%, rgba(93, 93, 255, 0.03) 100%); border: 2px solid rgba(93, 93, 255, 0.3); border-radius: 6px; padding: 12px; text-align: center; flex-shrink: 0; width: 180px; }
        .qr-code-box .qr-placeholder { background: rgba(93, 93, 255, 0.05); border: 1px dashed rgba(93, 93, 255, 0.4); width: 150px; height: 150px; margin: 0 auto 10px; display: flex; align-items: center; justify-content: center; border-radius: 4px; color: var(--text-light); font-size: 12px; }
        .qr-code-box .qr-label { font-weight: 600; color: var(--accent-blue); font-size: 12px; }
        .ticket-content { flex: 1; }
        /* ===== MODAL STYLES ===== */
        .modal { display: none; position: fixed; bottom: 0; left: 0; right: 0; background: linear-gradient(135deg, rgba(93, 93, 255, 0.08) 0%, rgba(93, 93, 255, 0.03) 100%); border-top: 3px solid var(--accent-blue); box-shadow: 0 -4px 15px rgba(0,0,0,0.1); z-index: 200; max-height: 70vh; overflow-y: auto; animation: slideUp 0.3s ease; }
        @keyframes slideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
        .modal.active { display: block; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 1px solid rgba(93, 93, 255, 0.2); background: linear-gradient(90deg, var(--accent-blue) 60%, rgba(93, 93, 255, 0.7) 100%); color: white; }
        .modal-header h2 { margin: 0; }
        .modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: white; transition: color 0.2s ease; }
        .modal-close:hover { opacity: 0.8; }
        .modal-body { padding: 20px; display: flex; justify-content: center; background: #525659; }
        .ticket-document { width: 8.5in; background: white; padding: 0.5in; box-shadow: 0 0 15px rgba(0,0,0,0.5); font-family: "Times New Roman", Times, serif; color: black; font-size: 10.5pt; line-height: 1.15; }
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
        .sig-driver { width: 250px; margin: 0; padding: 0 0 0 calc(50% - 125px); text-align: center; text-decoration: none; font-weight: bold; text-underline-offset: 4px; float: left; clear: both; }
        .sig-row { display: flex; justify-content: flex-end; }
        .sig-block { text-align: center; }
        .loading { text-align: center; padding: 20px; color: var(--text-light); }
        .no-data { text-align: center; color: var(--accent-blue); padding: 40px 20px; font-weight: 600; }
        footer { display: none; }
        
        /* Dashboard Toggle Control */
        .dashboard-toggle-btn {
            display: none;
            background: linear-gradient(135deg, rgba(93, 93, 255, 0.1) 0%, rgba(93, 93, 255, 0.05) 100%);
            border: 1px solid rgba(93, 93, 255, 0.3);
            color: var(--accent-blue);
            padding: 10px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            gap: 8px;
            align-items: center;
            transition: all 0.3s ease;
            -webkit-user-select: none;
            user-select: none;
            min-height: 44px;
        }

        .dashboard-toggle-btn:active {
            background: linear-gradient(135deg, rgba(93, 93, 255, 0.2) 0%, rgba(93, 93, 255, 0.1) 100%);
            border-color: rgba(93, 93, 255, 0.5);
        }

        .dashboard-main.collapsed {
            display: none;
        }
        
        /* ===== MOBILE RESPONSIVE (480px) ===== */
        @media (max-width: 480px) {
            body {
                position: fixed;
                width: 100%;
                height: 100vh;
                overflow: hidden;
            }

            .wrapper {
                height: 100%;
                flex-direction: column;
            }

            main {
                padding: 12px;
                overflow-y: auto;
                -webkit-overflow-scrolling: touch;
                max-height: calc(100vh - 60px);
            }

            .dashboard-header {
                margin-bottom: 16px;
            }

            .dashboard-header h1 {
                font-size: 1.4rem;
                margin-bottom: 4px;
            }

            .dashboard-header p {
                font-size: 0.75rem;
                margin-bottom: 12px;
            }

            .header-controls {
                gap: 8px;
                margin-bottom: 12px;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                flex-wrap: nowrap;
            }

            .control-btn {
                padding: 8px 10px;
                font-size: 0.75rem;
                flex: 0 0 auto;
                min-height: 36px;
                justify-content: center;
                flex-direction: column;
                gap: 3px;
                white-space: nowrap;
                min-width: fit-content;
            }

            .control-btn i {
                font-size: 1rem;
            }

            .dashboard-toggle-btn {
                display: flex;
                width: 100%;
                margin-bottom: 12px;
                justify-content: center;
            }

            .dashboard-main {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .dashboard-main.analytics-active {
                grid-template-columns: 1fr;
            }

            .dashboard-main.analytics-active .left-panel {
                grid-column: 1;
            }

            .dashboard-main.analytics-active .right-panel {
                grid-column: 1;
            }

            .left-panel {
                gap: 12px;
            }

            .summary-box {
                padding: 12px;
                margin-bottom: 12px;
            }

            .summary-box h3 {
                font-size: 0.95rem;
                margin-bottom: 12px;
            }

            .summary-items {
                gap: 8px;
            }

            .summary-item {
                padding: 10px 12px;
                font-size: 0.8rem;
            }

            .summary-item label {
                font-size: 0.7rem;
            }

            .summary-item .value {
                font-size: 1.2rem;
            }

            .stats {
                gap: 10px;
            }

            .stats.inline-stats {
                grid-template-columns: repeat(2, 1fr);
                gap: 8px;
            }

            .stat-card {
                padding: 12px 10px;
                font-size: 0.8rem;
                flex-direction: column;
                align-items: center;
                text-align: center;
                justify-content: center;
            }

            .stat-card h3 {
                font-size: 0.7rem;
                margin-bottom: 4px;
            }

            .stat-card .number {
                font-size: 1.3rem;
            }

            .analytics-section {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .chart-card {
                padding: 12px;
                margin-bottom: 12px;
            }

            .chart-card h3 {
                font-size: 0.95rem;
                margin-bottom: 12px;
            }

            .status-chart {
                height: 200px;
                flex-direction: row;
                justify-content: center;
                gap: 16px;
            }

            .chart-circle {
                width: 65px;
                height: 65px;
                font-size: 1.2rem;
            }

            .chart-label {
                font-size: 0.7rem;
                max-width: 60px;
                word-wrap: break-word;
            }

            .trip-list {
                padding: 12px;
            }

            .filter-controls {
                flex-direction: column;
                gap: 12px;
                margin: 12px 0;
            }

            .date-filters {
                flex-direction: column;
                width: 100%;
            }

            .date-select {
                padding: 10px 12px;
                font-size: 0.85rem;
                width: 100%;
            }

            .trip-item {
                margin-bottom: 10px;
            }

            .trip-summary {
                padding: 12px;
                flex-wrap: wrap;
            }

            .trip-summary .summary-left {
                gap: 10px;
                flex: 1 100%;
            }

            .trip-summary .summary-left i {
                font-size: 1rem;
            }

            .driver-name {
                font-size: 0.9rem;
            }

            .trip-datetime {
                font-size: 0.75rem;
            }

            .trip-details {
                padding: 16px;
                font-size: 0.85rem;
            }

            .ticket-preview {
                max-width: 100%;
                padding: 0.3in;
                font-size: 8pt;
            }

            .ticket-actions-container {
                flex-direction: column;
                gap: 12px;
                align-items: stretch;
            }

            .ticket-actions-sidebar {
                width: 100%;
                flex-direction: row;
            }

            .action-button {
                flex: 1;
                padding: 10px 8px;
                font-size: 0.75rem;
                min-height: 40px;
            }

            .action-button i {
                font-size: 0.9rem;
            }

            .qr-code-box {
                width: 100%;
                max-width: 150px;
                margin: 0 auto;
            }

            .qr-code-box .qr-placeholder {
                width: 120px;
                height: 120px;
                margin: 0 auto 8px;
            }

            .modal {
                max-height: 80vh;
            }

            .modal-header {
                padding: 12px;
            }

            .modal-header h2 {
                font-size: 1rem;
            }

            .modal-body {
                padding: 12px;
            }

            .ticket-document {
                font-size: 8pt;
                padding: 0.25in;
            }
        }

        /* ===== ULTRA-SMALL DEVICES (320px) ===== */
        @media (max-width: 360px) {
            main {
                padding: 8px;
            }

            .dashboard-header h1 {
                font-size: 1.2rem;
            }

            .dashboard-header p {
                font-size: 0.7rem;
            }

            .header-controls {
                gap: 6px;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                flex-wrap: nowrap;
            }

            .control-btn {
                padding: 6px 8px;
                font-size: 0.7rem;
                flex: 0 0 auto;
                min-width: fit-content;
            }

            .dashboard-toggle-btn {
                padding: 8px 10px;
                font-size: 0.75rem;
            }

            .summary-box {
                padding: 10px;
            }

            .summary-box h3 {
                font-size: 0.85rem;
            }

            .stat-card {
                padding: 10px 8px;
            }

            .stat-card .number {
                font-size: 1.1rem;
            }

            .trip-summary {
                flex-direction: column;
                padding: 10px;
            }

            .trip-details {
                padding: 12px;
            }

            .action-button {
                font-size: 0.7rem;
                padding: 8px 6px;
            }

            .chart-circle {
                width: 60px;
                height: 60px;
                font-size: 1.1rem;
            }
        }

        /* ===== TABLET RESPONSIVE (768px) ===== */
        @media (max-width: 768px) {
            main {
                padding: 20px;
                overflow-y: auto;
                -webkit-overflow-scrolling: touch;
            }

            .dashboard-header h1 {
                font-size: 2rem;
            }

            .header-controls {
                gap: 10px;
            }

            .control-btn {
                padding: 10px 16px;
                font-size: 0.85rem;
            }

            .sidebar {
                width: 200px;
            }

            .trip-list {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .filter-controls {
                flex-direction: column;
                align-items: stretch;
            }

            .date-filters {
                flex-direction: column;
            }

            .trip-list table {
                font-size: 0.85rem;
            }

            .trip-list th,
            .trip-list td {
                padding: 8px 10px;
            }
        }
    </style>
</head>
<body>

<div class="wrapper">
    <?php include("Components/Sidebar.php")?>

    <main>
        <div class="dashboard-header">
            <h1>📋 Trip Ticket Logs</h1>
            <p>Manage and track all trip tickets efficiently</p>
            
            <!-- Control Module -->
            <div class="header-controls">
                <button class="control-btn" onclick="toggleAnalyticsSection()" title="Toggle analytics section">
                    <i class="fas fa-chart-bar"></i> Analytics
                </button>
                <button class="control-btn" onclick="toggleAdvancedFilters()" title="Toggle advanced filters">
                    <i class="fas fa-sliders-h"></i> Filters
                </button>
                <button class="control-btn" onclick="refreshDashboard()" title="Refresh dashboard">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
                <button class="control-btn" onclick="exportData()" title="Export data to CSV">
                    <i class="fas fa-download"></i> Export
                </button>
            </div>

            <!-- Dashboard Toggle (Mobile) -->
            <button class="dashboard-toggle-btn" id="dashboardToggle" onclick="toggleDashboard()" title="Toggle dashboard visibility">
                <i class="fas fa-chevron-down"></i> Show Summary
            </button>

            <!-- Stats and Summary Layout -->
            <div class="dashboard-main">
                <!-- Left Panel (visible when analytics active) -->
                <div class="left-panel">
                    <div class="summary-box">
                        <h3>📊 Summary Overview</h3>
                        <div class="summary-items">
                            <div class="summary-item">
                                <label>Completion Rate</label>
                                <div class="value"><?php echo $total_count > 0 ? round((($status_counts['Submitted'] ?? 0) / $total_count) * 100) : 0; ?>%</div>
                            </div>
                            <div class="summary-item">
                                <label>Pending Items</label>
                                <div class="value"><?php echo $status_counts['Pending'] ?? 0; ?></div>
                            </div>
                            <div class="summary-item">
                                <label>This Week</label>
                                <div class="value"><?php echo count(array_filter($tickets, function($t) { return strtotime($t['created_at']) > strtotime('-7 days'); })); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="header-content analytics-inactive">
                    <!-- Summary Box (when analytics inactive) -->
                    <div class="summary-box">
                        <h3>📊 Summary Overview</h3>
                        <div class="summary-items">
                            <div class="summary-item">
                                <label>Completion Rate</label>
                                <div class="value"><?php echo $total_count > 0 ? round((($status_counts['Submitted'] ?? 0) / $total_count) * 100) : 0; ?>%</div>
                            </div>
                            <div class="summary-item">
                                <label>Pending Items</label>
                                <div class="value"><?php echo $status_counts['Pending'] ?? 0; ?></div>
                            </div>
                            <div class="summary-item">
                                <label>This Week</label>
                                <div class="value"><?php echo count(array_filter($tickets, function($t) { return strtotime($t['created_at']) > strtotime('-7 days'); })); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Stats Box Stack (when analytics inactive) -->
                    <div class="stats">
                        <div class="stat-card">
                            <h3>Total Tickets</h3>
                            <p class="number"><?php echo $total_count ?? 0; ?></p>
                        </div>
                        <div class="stat-card">
                            <h3>Pending</h3>
                            <p class="number"><?php echo $status_counts['Pending'] ?? 0; ?></p>
                        </div>
                        <div class="stat-card">
                            <h3>Active</h3>
                            <p class="number"><?php echo $status_counts['Active'] ?? 0; ?></p>
                        </div>
                        <div class="stat-card">
                            <h3>Submitted</h3>
                            <p class="number"><?php echo $status_counts['Submitted'] ?? 0; ?></p>
                        </div>
                    </div>
                </div>

                <!-- Right Panel (when analytics active) -->
                <div class="right-panel">
                    <!-- Horizontal Stats -->
                    <div class="stats inline-stats">
                        <div class="stat-card">
                            <h3>Total Tickets</h3>
                            <p class="number"><?php echo $total_count ?? 0; ?></p>
                        </div>
                        <div class="stat-card">
                            <h3>Pending</h3>
                            <p class="number"><?php echo $status_counts['Pending'] ?? 0; ?></p>
                        </div>
                        <div class="stat-card">
                            <h3>Active</h3>
                            <p class="number"><?php echo $status_counts['Active'] ?? 0; ?></p>
                        </div>
                        <div class="stat-card">
                            <h3>Submitted</h3>
                            <p class="number"><?php echo $status_counts['Submitted'] ?? 0; ?></p>
                        </div>
                    </div>

                    <!-- Analytics Charts -->
                    <div class="analytics-section collapsed" id="analyticsSection">
            <div class="chart-card">
                <h3>Status Distribution</h3>
                <div class="status-chart">
                    <div class="chart-item">
                        <div class="chart-circle pending"><?php echo $status_counts['Pending'] ?? 0; ?></div>
                        <div class="chart-label">Pending</div>
                    </div>
                    <div class="chart-item">
                        <div class="chart-circle pending" style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); box-shadow: 0 8px 20px rgba(23, 162, 184, 0.3);"><?php echo $status_counts['Active'] ?? 0; ?></div>
                        <div class="chart-label">Active</div>
                    </div>
                    <div class="chart-item">
                        <div class="chart-circle submitted"><?php echo $status_counts['Submitted'] ?? 0; ?></div>
                        <div class="chart-label">Submitted</div>
                    </div>
                </div>
            </div>

            <div class="performance-card">
                <h3>Performance Metrics</h3>
                <div class="performance-metric">
                    <span class="metric-label">Submission Rate</span>
                    <div class="metric-bar">
                        <div class="metric-bar-fill" style="width: <?php echo $total_count > 0 ? round((($status_counts['Submitted'] ?? 0) / $total_count) * 100) : 0; ?>%"></div>
                    </div>
                    <span class="metric-value"><?php echo $total_count > 0 ? round((($status_counts['Submitted'] ?? 0) / $total_count) * 100) : 0; ?>%</span>
                </div>
                <div class="performance-metric">
                    <span class="metric-label">Pending Workload</span>
                    <div class="metric-bar">
                        <div class="metric-bar-fill" style="width: <?php echo $total_count > 0 ? round((($status_counts['Pending'] ?? 0) / $total_count) * 100) : 0; ?>%"></div>
                    </div>
                    <span class="metric-value"><?php echo $total_count > 0 ? round((($status_counts['Pending'] ?? 0) / $total_count) * 100) : 0; ?>%</span>
                </div>
                </div>
                </div>
            </div>
        </div>

        <!-- Filter Controls -->
        <div class="filter-controls">
            <form method="get" id="filterForm" style="display: flex; gap: 1.5rem; width: 100%; align-items: center;">
                <div class="date-filters">
                    <div style="display: flex; gap: 10px;">
                        <a href="?status=all" class="date-select" style="text-decoration: none; <?php echo $status_filter === 'all' ? 'background: linear-gradient(135deg, var(--accent-blue) 0%, rgba(93, 93, 255, 0.7) 100%); color: white; border-color: var(--accent-blue);' : ''; ?>">All</a>
                        <a href="?status=Pending" class="date-select" style="text-decoration: none; <?php echo $status_filter === 'Pending' ? 'background: linear-gradient(135deg, var(--accent-blue) 0%, rgba(93, 93, 255, 0.7) 100%); color: white; border-color: var(--accent-blue);' : ''; ?>">Pending</a>
                        <a href="?status=Active" class="date-select" style="text-decoration: none; <?php echo $status_filter === 'Active' ? 'background: linear-gradient(135deg, var(--accent-blue) 0%, rgba(93, 93, 255, 0.7) 100%); color: white; border-color: var(--accent-blue);' : ''; ?>">Active</a>
                        <a href="?status=Submitted" class="date-select" style="text-decoration: none; <?php echo $status_filter === 'Submitted' ? 'background: linear-gradient(135deg, var(--accent-blue) 0%, rgba(93, 93, 255, 0.7) 100%); color: white; border-color: var(--accent-blue);' : ''; ?>">Submitted</a>
                    </div>
                </div>
                <div style="display: flex; gap: 10px; flex: 1; align-items: center;">
                    <input type="text" name="search" placeholder="Search by Control No, Driver, or Plate No..." value="<?php echo htmlspecialchars($search); ?>" style="flex: 1; padding: 10px 15px; border: 1px solid rgba(93, 93, 255, 0.3); border-radius: 8px; font-size: 0.95rem; background: rgba(93, 93, 255, 0.05); color: var(--text-primary);">
                    <button type="submit" class="date-select" style="cursor: pointer;">Search</button>
                    <?php if (!empty($search)): ?>
                        <a href="?" class="date-select" style="text-decoration: none; cursor: pointer;">Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Advanced Filters -->
        <div class="advanced-filters" id="advancedFilters">
            <h4 style="margin: 0 0 15px 0; color: var(--accent-blue); display: flex; align-items: center; gap: 8px;"><i class="fas fa-filter"></i>Advanced Search</h4>
            <div class="filter-row">
                <div class="filter-group-item">
                    <label>Date From</label>
                    <input type="date" id="dateFrom" style="padding: 8px 12px; border: 1px solid rgba(93, 93, 255, 0.3); border-radius: 6px; background: rgba(93, 93, 255, 0.05); color: var(--text-primary);">
                </div>
                <div class="filter-group-item">
                    <label>Date To</label>
                    <input type="date" id="dateTo" style="padding: 8px 12px; border: 1px solid rgba(93, 93, 255, 0.3); border-radius: 6px; background: rgba(93, 93, 255, 0.05); color: var(--text-primary);">
                </div>
                <div class="filter-group-item">
                    <label>Vehicle Plate</label>
                    <input type="text" id="vehiclePlate" placeholder="e.g., ABC-123" style="padding: 8px 12px; border: 1px solid rgba(93, 93, 255, 0.3); border-radius: 6px; background: rgba(93, 93, 255, 0.05); color: var(--text-primary);">
                </div>
                <div class="filter-group-item">
                    <button type="button" onclick="applyAdvancedFilter()" class="export-btn" style="width: 100%; margin-top: 23px;"><i class="fas fa-check"></i> Apply Filter</button>
                </div>
            </div>
        </div>

        <!-- Trip List -->
        <div class="trip-list">
            <?php if (count($tickets) > 0): ?>
                <?php foreach ($tickets as $row): ?>
                <div class="trip-item" onclick="toggleTrip(this)">
                    <div class="trip-summary">
                        <div class="summary-left">
                            <i class="fas fa-user-tie"></i>
                            <div class="driver-info">
                                <div class="driver-name"><?= htmlspecialchars($row['full_name'] ?? 'N/A') ?></div>
                                <div class="trip-datetime">
                                    <?= date('M d, Y', strtotime($row['ticket_date'])) ?> 
                                    <span style="color: var(--bfp-red); font-weight: 600;"><?= date('g:i A', strtotime($row['created_at'])) ?></span>
                                    <?php 
                                        $status = isset($row['status']) ? $row['status'] : 'Pending';
                                        if ($status === 'Submitted') {
                                            $status_color = '#28a745';
                                            $status_bg = '#d4edda';
                                        } elseif ($status === 'Active') {
                                            $status_color = '#17a2b8';
                                            $status_bg = '#d1ecf1';
                                        } else {
                                            $status_color = '#ffc107';
                                            $status_bg = '#fff3cd';
                                        }
                                    ?>
                                    <span style="margin-left: 10px; padding: 4px 8px; background: <?= $status_bg ?>; color: <?= $status_color ?>; border-radius: 4px; font-size: 0.85rem; font-weight: bold;">
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
                <div class="no-data">No trip ticket logs found</div>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
    // Toggle Analytics Section
    function toggleAnalyticsSection() {
        const dashboardMain = document.querySelector('.dashboard-main');
        const analyticsSection = document.getElementById('analyticsSection');
        const stats = document.querySelector('.stats');
        
        dashboardMain.classList.toggle('analytics-active');
        analyticsSection.classList.toggle('collapsed');
        stats.classList.toggle('inline-stats');
        
        // Update control button appearance
        const buttons = document.querySelectorAll('.control-btn');
        buttons.forEach(btn => {
            if (btn.innerHTML.includes('Analytics')) {
                btn.classList.toggle('active');
            }
        });
    }

    // Toggle Dashboard Visibility (Mobile)
    function toggleDashboard() {
        const dashboardMain = document.querySelector('.dashboard-main');
        const toggleBtn = document.getElementById('dashboardToggle');
        
        dashboardMain.classList.toggle('collapsed');
        
        if (dashboardMain.classList.contains('collapsed')) {
            toggleBtn.innerHTML = '<i class="fas fa-chevron-up"></i> Hide Summary';
        } else {
            toggleBtn.innerHTML = '<i class="fas fa-chevron-down"></i> Show Summary';
        }
    }

    // Refresh Dashboard
    function refreshDashboard() {
        location.reload();
    }

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
                        qrBox.innerHTML = `<img src="${ticket.qr_code}" alt="QR Code" width="150" height="150" style="border-radius: 4px;">`;
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
        printWindow.document.write('p { margin: 0; padding: 0; font-size: 10.5pt; }');
        printWindow.document.write('strong { font-weight: bold; }');
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

    function editTicket(ticketId) {
        window.location.href = 'edit_ticket.php?id=' + ticketId;
    }

    function submitTicket(ticketId) {
        if (confirm('Are you sure you want to submit this ticket?')) {
            fetch('submit_ticket_status.php?id=' + ticketId, { method: 'POST' })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Ticket submitted successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    alert('Error submitting ticket');
                    console.error('Error:', error);
                });
        }
    }

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

    // Toggle Advanced Filters
    function toggleAdvancedFilters() {
        const advancedFilters = document.getElementById('advancedFilters');
        advancedFilters.classList.toggle('show');
        
        // Update control button appearance
        const buttons = document.querySelectorAll('.control-btn');
        buttons.forEach(btn => {
            if (btn.innerHTML.includes('Filters')) {
                btn.classList.toggle('active');
            }
        });
    }

    // Apply Advanced Filter
    function applyAdvancedFilter() {
        const dateFrom = document.getElementById('dateFrom').value;
        const dateTo = document.getElementById('dateTo').value;
        const vehiclePlate = document.getElementById('vehiclePlate').value;

        let url = '?';
        if (dateFrom) url += 'dateFrom=' + encodeURIComponent(dateFrom) + '&';
        if (dateTo) url += 'dateTo=' + encodeURIComponent(dateTo) + '&';
        if (vehiclePlate) url += 'vehiclePlate=' + encodeURIComponent(vehiclePlate) + '&';
        
        window.location.href = url;
    }

    // Export Data to CSV
    function exportData() {
        const tickets = <?php echo json_encode($tickets); ?>;
        let csv = 'Control No,Driver Name,Vehicle Plate,Ticket Date,Status,Created At\n';
        
        tickets.forEach(ticket => {
            csv += `"${ticket.control_no}","${ticket.full_name || 'N/A'}","${ticket.plate_no || 'N/A'}","${ticket.ticket_date}","${ticket.status || 'Pending'}","${ticket.created_at}"\n`;
        });

        // Create blob and download
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'trip-tickets-' + new Date().getTime() + '.csv';
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
    }

    // Initialize mobile-specific behavior
    window.addEventListener('DOMContentLoaded', function() {
        // Collapse dashboard on mobile by default
        if (window.innerWidth <= 480) {
            const dashboardMain = document.querySelector('.dashboard-main');
            const toggleBtn = document.getElementById('dashboardToggle');
            dashboardMain.classList.add('collapsed');
            if (toggleBtn) {
                toggleBtn.innerHTML = '<i class="fas fa-chevron-up"></i> Show Summary';
            }
        }

        // Re-initialize on resize
        window.addEventListener('resize', function() {
            const toggleBtn = document.getElementById('dashboardToggle');
            const dashboardMain = document.querySelector('.dashboard-main');
            
            if (window.innerWidth > 480 && toggleBtn) {
                toggleBtn.style.display = 'none';
                dashboardMain.classList.remove('collapsed');
            } else if (window.innerWidth <= 480 && toggleBtn) {
                toggleBtn.style.display = 'flex';
            }
        });
    });

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
