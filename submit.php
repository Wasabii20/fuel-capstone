<?php
session_start();
require_once 'db_connect.php';
require_once 'utils/log_activity.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Validate required fields
        $errors = [];
        
        if (empty($_POST['control_no'])) {
            $errors[] = "⚠️ Control Number is required!";
        }
        
        // If there are validation errors, return them
        if (!empty($errors)) {
            $error_msg = implode("\\n", $errors);
            echo "
            <script>
                alert('❌ FORM VALIDATION ERROR\\n\\n" . $error_msg . "');
                window.history.back();
            </script>
            ";
            exit();
        }
        
        $sql = "INSERT INTO trip_tickets (
            control_no, ticket_date, driver_id, vehicle_plate_no, 
            authorized_passenger, places_to_visit, purpose, 
            dep_office_time, arr_location_time, dep_location_time, arr_office_time, 
            approx_distance, speedometer_start, speedometer_end,
            gas_balance_start, gas_issued_office, gas_added_trip, 
            gas_total, gas_used_trip, gas_balance_end, 
            gear_oil_issued, lub_oil_issued, grease_issued, 
            remarks, passenger_1_name, passenger_1_date, passenger_2_name, passenger_2_date, passenger_3_name, passenger_3_date, status
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )";

        $stmt = $pdo->prepare($sql);

        $gas_balance_end = $_POST['gas_balance_end'] ?: 0;
        $vehicle_plate_no = $_POST['vehicle_no'];

        $stmt->execute([
            $_POST['control_no'],
            $_POST['date'],
            $_POST['driver_id'] ?? null,  // Allow null if driver not selected
            $vehicle_plate_no, // from HTML 'name="vehicle_no"'
            $_POST['Autho_passenger'],
            $_POST['places_to_visit'],
            $_POST['purpose'],
            $_POST['dep_office_time'],
            $_POST['arr_location_time'],
            $_POST['dep_location_time'],
            $_POST['arr_office_time'],
            $_POST['approx_distance'] ?: 0,
            $_POST['speedometer_start'] ?: 0,
            $_POST['speedometer_end'] ?: 0,
            $_POST['gas_balance_start'] ?: 0,
            $_POST['gas_issued_office'] ?: 0,
            $_POST['gas_added_trip'] ?: 0,
            $_POST['gas_total'] ?: 0,
            $_POST['gas_deducted'] ?: 0, // Maps to gas_used_trip
            $gas_balance_end,
            $_POST['gear_oil'] ?: 0,     // Maps to gear_oil_issued
            $_POST['lub_oil'] ?: 0,      // Maps to lub_oil_issued
            $_POST['grease'] ?: 0,       // Maps to grease_issued
            $_POST['remarks'],
            $_POST['passenger_1_name'] ?? null,
            $_POST['passenger_1_date'] ?? null,
            $_POST['passenger_2_name'] ?? null,
            $_POST['passenger_2_date'] ?? null,
            $_POST['passenger_3_name'] ?? null,
            $_POST['passenger_3_date'] ?? null,
            isset($_POST['status']) ? $_POST['status'] : 'Pending'  // Use status from form, default to Pending
        ]);

        // Get the last inserted ID
        $ticket_id = $pdo->lastInsertId();
        
        // Generate QR code for the ticket
        $qr_code = generateQRCode($ticket_id, $_POST['control_no']);
        
        // Update trip ticket with QR code
        $update_qr_sql = "UPDATE trip_tickets SET qr_code = ? WHERE id = ?";
        $update_qr_stmt = $pdo->prepare($update_qr_sql);
        $update_qr_stmt->execute([$qr_code, $ticket_id]);
        
        // Log the trip ticket creation
        if (isset($_SESSION['user_id'])) {
            logTripTicketAction($pdo, $_SESSION['user_id'], 'Create Trip Ticket', $ticket_id, 
                "Created trip ticket: Control#" . $_POST['control_no']);
        }

        // Update the vehicle's current fuel level after trip ticket is saved
        $update_vehicle_sql = "UPDATE vehicles SET current_fuel = ? WHERE vehicle_no = ?";
        $update_stmt = $pdo->prepare($update_vehicle_sql);
        $update_stmt->execute([$gas_balance_end, $vehicle_plate_no]);

        // Show success message and redirect
        echo "
        <script>
            alert('✅ SUCCESS!\\n\\n' +
                  'Trip Ticket Successfully Created\\n\\n' +
                  'Control #: " . htmlspecialchars($_POST['control_no']) . "\\n' +
                  'Vehicle: " . htmlspecialchars($vehicle_plate_no) . "\\n' +
                  'Ending Fuel: " . htmlspecialchars($gas_balance_end) . " L\\n\\n' +
                  'Redirecting to Pending Reports...');
            window.location.href = 'Pending_reports.php?status=success';
        </script>
        ";
        exit();

    } catch (PDOException $e) {
        // Check for specific database errors
        $error_code = $e->getCode();
        $error_msg = $e->getMessage();
        
        if ($error_code == 23000 || strpos($error_msg, 'Duplicate entry') !== false) {
            // Duplicate control number error
            echo "
            <script>
                alert('❌ ERROR: DUPLICATE CONTROL NUMBER\\n\\n' +
                      'Control #" . htmlspecialchars($_POST['control_no'] ?? '') . " already exists in the system.\\n\\n' +
                      'Please use a different control number and try again.');
                window.history.back();
            </script>
            ";
        } else if (strpos($error_msg, 'Undefined') !== false) {
            // Missing field error
            echo "
            <script>
                alert('❌ ERROR: MISSING REQUIRED FIELD\\n\\n' +
                      'Please ensure all required fields are filled in:\\n' +
                      '- Control Number\\n' +
                      '- Authorized Representative\\n' +
                      '- Authorization Date');
                window.history.back();
            </script>
            ";
        } else {
            // Generic database error
            echo "
            <script>
                alert('❌ ERROR SAVING TICKET\\n\\n' +
                      'An unexpected error occurred. Please try again.\\n\\n' +
                      'Error Details: " . htmlspecialchars($error_msg) . "');
                window.history.back();
            </script>
            ";
        }
        exit();
    }
}

// Function to generate QR code using QR Server API
// QR code encodes a URL that can be scanned to view the ticket
function generateQRCode($ticket_id, $control_no) {
    // Get the base URL of the application
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $base_url = $protocol . '://' . $host . dirname($_SERVER['REQUEST_URI']);
    
    // Create a scannable URL that will show the ticket details
    // When scanned, it will open: http://localhost/FuelCapstone/view_ticket.php?id=123
    $qr_data = $base_url . '/view_ticket.php?id=' . $ticket_id . '&control=' . urlencode($control_no);
    
    // Generate QR code image URL using QR Server API
    // The QR code will contain the full URL to view the ticket
    $qr_code_url = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($qr_data);
    
    return $qr_code_url;
}
