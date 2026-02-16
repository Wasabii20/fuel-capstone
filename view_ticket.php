<?php
session_start();
require_once 'db_connect.php';

// Get ticket ID from QR code scan
$ticket_id = isset($_GET['id']) ? intval($_GET['id']) : null;
$control_no = isset($_GET['control']) ? $_GET['control'] : null;
$format = isset($_GET['format']) ? $_GET['format'] : 'html'; // html or pdf

if (!$ticket_id) {
    die("Error: No ticket ID provided");
}

try {
    // Fetch ticket details
    $sql = "SELECT t.*, d.full_name FROM trip_tickets t 
            LEFT JOIN drivers d ON t.driver_id = d.driver_id 
            WHERE t.id = ? LIMIT 1";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$ticket_id]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$ticket) {
        die("Error: Ticket not found (ID: $ticket_id)");
    }
    
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// Format date
function formatDateFull($date) {
    if (!$date) return 'N/A';
    $dateObj = new DateTime($date);
    return $dateObj->format('F j, Y');
}

// Escape HTML
function escapeHtml($text) {
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}

// Handle PDF download
if ($format === 'pdf') {
    generatePDF($ticket);
    exit;
}

// Function to generate and download PDF
function generatePDF($ticket) {
    // Calculate gas totals
    $gasTotal = floatval($ticket['gas_balance_start'] ?? 0) + floatval($ticket['gas_issued_office'] ?? 0) + floatval($ticket['gas_added_trip'] ?? 0);
    $gasEnd = $gasTotal - floatval($ticket['gas_used_trip'] ?? 0);
    
    // Generate HTML content for PDF
    $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: 'Times New Roman', Times, serif;
            margin: 0;
            padding: 40px;
            background: white;
        }
        .container {
            max-width: 8.5in;
            margin: 0 auto;
            padding: 40px;
            background: white;
            line-height: 1.7;
            color: #000;
            font-size: 12px;
        }
        .header-section {
            text-align: center;
            margin-bottom: 15px;
            line-height: 1.5;
        }
        .header-section strong {
            display: block;
            font-weight: bold;
            font-size: 13px;
        }
        .fire-station-name {
            text-decoration: underline;
            font-weight: bold;
        }
        .control-no {
            text-align: right;
            margin-bottom: 10px;
            font-weight: bold;
        }
        .qr-code {
            text-align: center;
            margin: 15px 0;
        }
        .qr-code img {
            width: 120px;
            height: 120px;
            border: 1px solid #000;
        }
        h3 {
            text-align: center;
            margin: 12px 0 8px 0;
            font-size: 13px;
            font-weight: bold;
        }
        .appendix {
            position: absolute;
            font-weight: bold;
            right: 40px;
            top: 50px;
        }
        .section-title {
            font-weight: bold;
            margin-top: 12px;
            margin-bottom: 6px;
        }
        .indent {
            margin-left: 30px;
        }
        .line-item {
            display: block;
            margin-bottom: 8px;
        }
        .underline {
            border-bottom: 1px solid black;
            display: inline-block;
            min-width: 150px;
            padding: 0 5px;
            text-align: center;
        }
        ol, ul {
            margin: 8px 0;
            padding-left: 30px;
        }
        li {
            margin-bottom: 6px;
        }
        .gas-table {
            width: 100%;
            border-collapse: collapse;
            margin: 5px 0;
        }
        .gas-table td {
            padding: 2px 5px;
            border: none;
        }
        .gas-value {
            border-bottom: 1px solid black;
            text-align: center;
            min-width: 80px;
        }
        .gas-unit {
            width: 40px;
            text-align: left;
            padding-left: 10px;
        }
        .sig-row {
            display: flex;
            justify-content: flex-end;
            margin-top: 15px;
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
    </style>
</head>
<body>
<div class="container">
    <div class="appendix">Appendix A</div>
    
    <div class="header-section">
        <div>Republic of the Philippines</div>
        <div>Province of Southern Leyte</div>
        <strong>CITY OF MAASIN</strong>
        <div><span>Office of the <span class="fire-station-name">MAASIN CITY FIRE STATION</span></span></div>
    </div>
    
    <div class="control-no">Control No: {control_no}</div>
    
    {qr_code_html}
    
    <h3>DRIVER'S TRIP TICKET</h3>
    <div style="text-align: center; margin-bottom: 30px; font-size: 9px;">({ticket_date})</div>
    
    <div class="section-title">A. To be filled by the Administrative Official Authorizing Official Travel:</div>
    <div class="indent">
        <div class="line-item">1. Name of Driver of the Vehicle: <span class="underline">{driver_name}</span></div>
        <div class="line-item">2. Government car to be used. Plate No.: <span class="underline">{vehicle_plate}</span></div>
        <div class="line-item">3. Name of Authorized Passenger: <span class="underline">{authorized_passenger}</span></div>
        <div class="line-item">4. Place or places to be visited/inspected: <span class="underline">{places_to_visit}</span></div>
        <div class="line-item">5. Purpose: <span class="underline">{purpose}</span></div>
    </div>
    
    <div class="sig-row">
        <div class="sig-block">
            <span class="underline"></span>
            <small>Head of Office or his duly<br>Authorized Representative</small>
        </div>
    </div>
    
    <div class="section-title">B. To be filled by the Driver:</div>
    <div class="indent">
        <ol>
            <li>Time of departure from Office / Garage: <span class="underline">{dep_office_time}</span> a.m./p.m.</li>
            <li>Time of arrival at (per No. 4 above): <span class="underline">{arr_location_time}</span> a.m./p.m.</li>
            <li>Time of departure from (per No. 4): <span class="underline">{dep_location_time}</span> a.m./p.m.</li>
            <li>Time of arrival back to Office/Garage: <span class="underline">{arr_office_time}</span> a.m./p.m.</li>
            <li>Approximate distance travelled (to and from): <span class="underline">{approx_distance}</span> kms.</li>
            <li>Gasoline issued, purchase and consumed:
                <table class="gas-table">
                    <tr><td style="width: 240px; font-size: 9px;">a. Balance in Tank:</td><td class="gas-value">{gas_balance_start}</td><td class="gas-unit">liters</td></tr>
                    <tr><td style="font-size: 9px;">b. Issued by Office from Stock:</td><td class="gas-value">{gas_issued_office}</td><td class="gas-unit">liters</td></tr>
                    <tr><td style="font-size: 9px;">c. Add purchased during trip:</td><td class="gas-value">{gas_added_trip}</td><td class="gas-unit">liters</td></tr>
                    <tr><td style="padding-left: 20px; font-size: 9px;"><strong>TOTAL:</strong></td><td class="gas-value"><strong>{gas_total}</strong></td><td class="gas-unit"><strong>liters</strong></td></tr>
                    <tr><td style="font-size: 9px;">d. Deduct Used during trip:</td><td class="gas-value">{gas_used_trip}</td><td class="gas-unit">liters</td></tr>
                    <tr><td style="font-size: 9px;">e. Balance at end of trip:</td><td class="gas-value"><strong>{gas_end}</strong></td><td class="gas-unit">liters</td></tr>
                </table>
            </li>
            <li>Gear oil issued: <span class="underline">{gear_oil_issued}</span> liters</li>
            <li>Lub. Oil issued: <span class="underline">{lub_oil_issued}</span> liters</li>
            <li>Grease issued: <span class="underline">{grease_issued}</span> liters</li>
            <li>Speedometer readings, if any:
                <div style="margin-left: 10px; font-size: 9px; margin-top: 2px;">
                    <div>At beginning of trip: <span class="underline">{speedometer_start}</span> kms.</div>
                    <div>At end of trip: <span class="underline">{speedometer_end}</span> kms.</div>
                    <div>Distance travelled (per No. 5 above): <span class="underline">{approx_distance}</span> kms.</div>
                </div>
            </li>
            <li>Remarks: <span class="underline">{remarks}</span></li>
        </ol>
    </div>
    
    <div style="margin-top: 6px; text-align: center; font-style: italic; font-size: 9px;">
        I hereby certify to the correctness of the above statement of record of travel.
    </div>
    <div class="sig-box">Driver</div>
</div>
</body>
</html>
HTML;

    // Prepare QR code HTML
    $qr_html = '';
    if ($ticket['qr_code']) {
        $qr_html = '<div class="qr-code"><img src="' . escapeHtml($ticket['qr_code']) . '" alt="QR Code"></div>';
    }

    // Replace placeholders with actual data
    $html = str_replace('{control_no}', escapeHtml($ticket['control_no']), $html);
    $html = str_replace('{qr_code_html}', $qr_html, $html);
    $html = str_replace('{ticket_date}', formatDateFull($ticket['ticket_date']), $html);
    $html = str_replace('{driver_name}', escapeHtml($ticket['full_name'] ?? 'N/A'), $html);
    $html = str_replace('{vehicle_plate}', escapeHtml($ticket['vehicle_plate_no']), $html);
    $html = str_replace('{authorized_passenger}', escapeHtml($ticket['authorized_passenger'] ?? 'N/A'), $html);
    $html = str_replace('{places_to_visit}', escapeHtml($ticket['places_to_visit'] ?? 'N/A'), $html);
    $html = str_replace('{purpose}', escapeHtml($ticket['purpose'] ?? 'N/A'), $html);
    $html = str_replace('{dep_office_time}', escapeHtml($ticket['dep_office_time'] ?? 'N/A'), $html);
    $html = str_replace('{arr_location_time}', escapeHtml($ticket['arr_location_time'] ?? 'N/A'), $html);
    $html = str_replace('{dep_location_time}', escapeHtml($ticket['dep_location_time'] ?? 'N/A'), $html);
    $html = str_replace('{arr_office_time}', escapeHtml($ticket['arr_office_time'] ?? 'N/A'), $html);
    $html = str_replace('{approx_distance}', escapeHtml($ticket['approx_distance'] ?? '0'), $html);
    $html = str_replace('{gas_balance_start}', escapeHtml($ticket['gas_balance_start'] ?? '0'), $html);
    $html = str_replace('{gas_issued_office}', escapeHtml($ticket['gas_issued_office'] ?? '0'), $html);
    $html = str_replace('{gas_added_trip}', escapeHtml($ticket['gas_added_trip'] ?? '0'), $html);
    $html = str_replace('{gas_total}', number_format($gasTotal, 2), $html);
    $html = str_replace('{gas_used_trip}', escapeHtml($ticket['gas_used_trip'] ?? '0'), $html);
    $html = str_replace('{gas_end}', number_format($gasEnd, 2), $html);
    $html = str_replace('{gear_oil_issued}', escapeHtml($ticket['gear_oil_issued'] ?? '0'), $html);
    $html = str_replace('{lub_oil_issued}', escapeHtml($ticket['lub_oil_issued'] ?? '0'), $html);
    $html = str_replace('{grease_issued}', escapeHtml($ticket['grease_issued'] ?? '0'), $html);
    $html = str_replace('{speedometer_start}', escapeHtml($ticket['speedometer_start'] ?? '0'), $html);
    $html = str_replace('{speedometer_end}', escapeHtml($ticket['speedometer_end'] ?? '0'), $html);
    $html = str_replace('{remarks}', escapeHtml($ticket['remarks'] ?? 'None'), $html);

    // Use DOMPDF library if available, otherwise use alternative
    try {
        // Check if DOMPDF is installed via Composer
        if (file_exists('../vendor/autoload.php')) {
            require_once '../vendor/autoload.php';
            
            $dompdf = new \Dompdf\Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            
            $filename = 'Trip_Ticket_' . escapeHtml($ticket['control_no']) . '_' . date('Y-m-d') . '.pdf';
            
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            echo $dompdf->output();
            exit;
        }
    } catch (Exception $e) {
        // Fallback: use HTML2PDF or send as HTML file download
    }
    
    // Fallback: Save as HTML file that can be printed to PDF
    $filename = 'Trip_Ticket_' . escapeHtml($ticket['control_no']) . '_' . date('Y-m-d') . '.html';
    
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $html;
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trip Ticket - <?php echo escapeHtml($ticket['control_no']); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Times New Roman', Times, serif;
            background-color: #f5f5f5;
            padding: 20px;
        }
        
        .print-container {
            background: white;
            max-width: 8.5in;
            margin: 0 auto;
            padding: 50px 40px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            line-height: 1.7;
            color: #000;
            font-size: 12px;
        }
        
        .header-section {
            text-align: center;
            margin-bottom: 15px;
            line-height: 1.5;
            font-size: 12px;
        }
        
        .header-section strong {
            display: block;
            font-weight: bold;
            font-size: 13px;
        }
        
        .fire-station-name {
            text-decoration: underline;
            font-weight: bold;
        }
        
        .control-no {
            text-align: right;
            margin-bottom: 10px;
            font-weight: bold;
            font-size: 12px;
        }
        
        .qr-code {
            text-align: center;
            margin: 15px 0;
        }
        
        .qr-code img {
            width: 150px;
            height: 150px;
            border: 1px solid #000;
        }
        
        h3 {
            text-align: center;
            margin: 12px 0 8px 0;
            font-size: 13px;
            font-weight: bold;
        }
        
        .appendix {
            position: absolute;
            font-weight: bold;
            right: 40px;
            top: 50px;
        }
        
        .section-title {
            font-weight: bold;
            margin-top: 12px;
            margin-bottom: 6px;
            font-size: 12px;
        }
        
        .indent {
            margin-left: 30px;
        }
        
        .line-item {
            display: block;
            margin-bottom: 8px;
        }
        
        .underline {
            border-bottom: 1px solid black;
            display: inline-block;
            min-width: 150px;
            padding: 0 5px;
            text-align: center;
        }
        
        ol, ul {
            margin: 8px 0;
            padding-left: 30px;
        }
        
        li {
            margin-bottom: 6px;
        }
        
        .gas-table {
            width: 100%;
            border-collapse: collapse;
            margin: 5px 0;
        }
        
        .gas-table td {
            padding: 2px 5px;
            border: none;
        }
        
        .gas-value {
            border-bottom: 1px solid black;
            text-align: center;
            min-width: 80px;
        }
        
        .gas-unit {
            width: 40px;
            text-align: left;
            padding-left: 10px;
        }
        
        .sig-row {
            display: flex;
            justify-content: flex-end;
            margin-top: 15px;
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
        
        .controls {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #ccc;
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        
        .btn {
            background: #B22222;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin: 0 5px;
            font-size: 14px;
        }
        
        .btn:hover {
            background: #8B1A1A;
        }
        
        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .controls {
                display: none !important;
            }
            
            .print-container {
                box-shadow: none;
                margin: 0;
                padding: 0.5in;
            }
        }
    </style>
</head>
<body>
    <div class="print-container">
        <div class="appendix">Appendix A</div>
        
        <div class="header-section">
            <div>Republic of the Philippines</div>
            <div>Province of Southern Leyte</div>
            <strong>CITY OF MAASIN</strong>
            <div><span>Office of the <span class="fire-station-name">MAASIN CITY FIRE STATION</span></span></div>
        </div>
        
        <div class="control-no">Control No: <?php echo escapeHtml($ticket['control_no']); ?></div>
        
        <?php if ($ticket['qr_code']): ?>
        <div class="qr-code">
            <img src="<?php echo escapeHtml($ticket['qr_code']); ?>" alt="QR Code">
        </div>
        <?php endif; ?>
        
        <h3>DRIVER'S TRIP TICKET</h3>
        <div style="text-align: center; margin-bottom: 30px; font-size: 9px;">(<?php echo formatDateFull($ticket['ticket_date']); ?>)</div>
        
        <div class="section-title">A. To be filled by the Administrative Official Authorizing Official Travel:</div>
        <div class="indent">
            <div class="line-item">1. Name of Driver of the Vehicle: <span class="underline"><?php echo escapeHtml($ticket['full_name'] ?? 'N/A'); ?></span></div>
            <div class="line-item">2. Government car to be used. Plate No.: <span class="underline"><?php echo escapeHtml($ticket['vehicle_plate_no']); ?></span></div>
            <div class="line-item">3. Name of Authorized Passenger: <span class="underline"><?php echo escapeHtml($ticket['authorized_passenger'] ?? 'N/A'); ?></span></div>
            <div class="line-item">4. Place or places to be visited/inspected: <span class="underline"><?php echo escapeHtml($ticket['places_to_visit'] ?? 'N/A'); ?></span></div>
            <div class="line-item">5. Purpose: <span class="underline"><?php echo escapeHtml($ticket['purpose'] ?? 'N/A'); ?></span></div>
        </div>
        
        <div class="sig-row">
            <div class="sig-block">
                <span class="underline"></span>
                <small>Head of Office or his duly<br>Authorized Representative</small>
            </div>
        </div>
        
        <div class="section-title">B. To be filled by the Driver:</div>
        <div class="indent">
            <ol>
                <li>Time of departure from Office / Garage: <span class="underline"><?php echo escapeHtml($ticket['dep_office_time'] ?? 'N/A'); ?></span> a.m./p.m.</li>
                <li>Time of arrival at (per No. 4 above): <span class="underline"><?php echo escapeHtml($ticket['arr_location_time'] ?? 'N/A'); ?></span> a.m./p.m.</li>
                <li>Time of departure from (per No. 4): <span class="underline"><?php echo escapeHtml($ticket['dep_location_time'] ?? 'N/A'); ?></span> a.m./p.m.</li>
                <li>Time of arrival back to Office/Garage: <span class="underline"><?php echo escapeHtml($ticket['arr_office_time'] ?? 'N/A'); ?></span> a.m./p.m.</li>
                <li>Approximate distance travelled (to and from): <span class="underline"><?php echo escapeHtml($ticket['approx_distance'] ?? '0'); ?></span> kms.</li>
                <li>Gasoline issued, purchase and consumed:
                    <table class="gas-table">
                        <tr><td style="width: 240px; font-size: 9px;">a. Balance in Tank:</td><td class="gas-value"><?php echo escapeHtml($ticket['gas_balance_start'] ?? '0'); ?></td><td class="gas-unit">liters</td></tr>
                        <tr><td style="font-size: 9px;">b. Issued by Office from Stock:</td><td class="gas-value"><?php echo escapeHtml($ticket['gas_issued_office'] ?? '0'); ?></td><td class="gas-unit">liters</td></tr>
                        <tr><td style="font-size: 9px;">c. Add purchased during trip:</td><td class="gas-value"><?php echo escapeHtml($ticket['gas_added_trip'] ?? '0'); ?></td><td class="gas-unit">liters</td></tr>
                        <?php 
                        $gasTotal = floatval($ticket['gas_balance_start'] ?? 0) + floatval($ticket['gas_issued_office'] ?? 0) + floatval($ticket['gas_added_trip'] ?? 0);
                        $gasEnd = $gasTotal - floatval($ticket['gas_used_trip'] ?? 0);
                        ?>
                        <tr><td style="padding-left: 20px; font-size: 9px;"><strong>TOTAL:</strong></td><td class="gas-value"><strong><?php echo number_format($gasTotal, 2); ?></strong></td><td class="gas-unit"><strong>liters</strong></td></tr>
                        <tr><td style="font-size: 9px;">d. Deduct Used during trip:</td><td class="gas-value"><?php echo escapeHtml($ticket['gas_used_trip'] ?? '0'); ?></td><td class="gas-unit">liters</td></tr>
                        <tr><td style="font-size: 9px;">e. Balance at end of trip:</td><td class="gas-value"><strong><?php echo number_format($gasEnd, 2); ?></strong></td><td class="gas-unit">liters</td></tr>
                    </table>
                </li>
                <li>Gear oil issued: <span class="underline"><?php echo escapeHtml($ticket['gear_oil_issued'] ?? '0'); ?></span> liters</li>
                <li>Lub. Oil issued: <span class="underline"><?php echo escapeHtml($ticket['lub_oil_issued'] ?? '0'); ?></span> liters</li>
                <li>Grease issued: <span class="underline"><?php echo escapeHtml($ticket['grease_issued'] ?? '0'); ?></span> liters</li>
                <li>Speedometer readings, if any:
                    <div style="margin-left: 10px; font-size: 9px; margin-top: 2px;">
                        <div>At beginning of trip: <span class="underline"><?php echo escapeHtml($ticket['speedometer_start'] ?? '0'); ?></span> kms.</div>
                        <div>At end of trip: <span class="underline"><?php echo escapeHtml($ticket['speedometer_end'] ?? '0'); ?></span> kms.</div>
                        <div>Distance travelled (per No. 5 above): <span class="underline"><?php echo escapeHtml($ticket['approx_distance'] ?? '0'); ?></span> kms.</div>
                    </div>
                </li>
                <li>Remarks: <span class="underline"><?php echo escapeHtml($ticket['remarks'] ?? 'None'); ?></span></li>
            </ol>
        </div>
        
        <div style="margin-top: 6px; text-align: center; font-style: italic; font-size: 9px;">
            I hereby certify to the correctness of the above statement of record of travel.
        </div>
        <div class="sig-box">Driver</div>
    </div>
    
    <div class="controls">
        <button class="btn" onclick="downloadPDF()">📥 Download PDF</button>
        <button class="btn" onclick="window.print()">🖨️ Print</button>
        <button class="btn" onclick="window.history.back()">⬅️ Back</button>
    </div>
    
    <script>
        function downloadPDF() {
            // Get current URL and add format=pdf parameter
            const url = new URL(window.location);
            url.searchParams.set('format', 'pdf');
            window.location.href = url.toString();
        }
    </script>
</body>
</html>
