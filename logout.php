<?php
session_start();
include 'db_connect.php';
include 'utils/log_activity.php';

if (isset($_SESSION['user_id'])) {
    try {
        $user_id = $_SESSION['user_id'];
        logLogout($pdo, $user_id);
    } catch(Exception $e) {
        // Continue anyway
    }
}

session_destroy();
header("Location: index.php");
exit();
?>
