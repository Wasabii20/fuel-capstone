<?php
/**
 * User Activity Logging Utility
 * Logs user actions to the user_logs table
 */

function logUserActivity($pdo, $user_id, $action, $module, $description = null, $reference_id = null) {
    try {
        // Get user role from session or database
        $role = $_SESSION['role'] ?? 'user';
        
        // Get browser info
        $browser = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        // Get IP address
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip_address = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        
        // Prepare and execute the insert query
        $stmt = $pdo->prepare("
            INSERT INTO user_logs 
            (user_id, role, action, description, module, reference_id, browser, ip_address) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $user_id,
            $role,
            $action,
            $description,
            $module,
            $reference_id,
            $browser,
            $ip_address
        ]);
        
        return true;
    } catch (Exception $e) {
        error_log("Error logging user activity: " . $e->getMessage());
        return false;
    }
}

/**
 * Quick login log
 */
function logLogin($pdo, $user_id) {
    return logUserActivity($pdo, $user_id, 'Login', 'auth', 'User logged in');
}

/**
 * Quick logout log
 */
function logLogout($pdo, $user_id) {
    return logUserActivity($pdo, $user_id, 'Logout', 'auth', 'User logged out');
}

/**
 * Log user management action
 */
function logUserManagement($pdo, $user_id, $action, $target_user_id, $description = null) {
    return logUserActivity($pdo, $user_id, $action, 'users', $description, $target_user_id);
}

/**
 * Log driver action
 */
function logDriverAction($pdo, $user_id, $action, $driver_id, $description = null) {
    return logUserActivity($pdo, $user_id, $action, 'drivers', $description, $driver_id);
}

/**
 * Log vehicle action
 */
function logVehicleAction($pdo, $user_id, $action, $vehicle_id, $description = null) {
    return logUserActivity($pdo, $user_id, $action, 'vehicles', $description, $vehicle_id);
}

/**
 * Log trip ticket action
 */
function logTripTicketAction($pdo, $user_id, $action, $ticket_id, $description = null) {
    return logUserActivity($pdo, $user_id, $action, 'trip_tickets', $description, $ticket_id);
}
?>
