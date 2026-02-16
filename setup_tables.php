<?php
include 'db_connect.php';

try {
    // Create activity_logs table
    $sql = "CREATE TABLE IF NOT EXISTS `activity_logs` (
      `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
      `user_id` INT(11) NOT NULL,
      `action` VARCHAR(255) NOT NULL,
      `details` LONGTEXT,
      `timestamp` DATETIME DEFAULT CURRENT_TIMESTAMP,
      INDEX `user_id` (`user_id`),
      INDEX `action` (`action`),
      INDEX `timestamp` (`timestamp`),
      FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
    echo "✓ activity_logs table created successfully\n";
    
    // Create trip_requests table
    $sql2 = "CREATE TABLE IF NOT EXISTS `trip_requests` (
      `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
      `user_id` INT(11) NOT NULL,
      `vehicle_no` VARCHAR(50) NOT NULL,
      `trip_purpose` VARCHAR(255) NOT NULL,
      `trip_details` LONGTEXT,
      `estimated_fuel` DECIMAL(8, 2),
      `request_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
      `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
      `admin_notes` LONGTEXT,
      `approved_by` INT(11),
      `approval_date` DATETIME,
      INDEX `user_id` (`user_id`),
      INDEX `vehicle_no` (`vehicle_no`),
      INDEX `status` (`status`),
      FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql2);
    echo "✓ trip_requests table created successfully\n";
    
    // Create notifications table
    $sql3 = "CREATE TABLE IF NOT EXISTS `notifications` (
      `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
      `user_id` INT(11) NOT NULL,
      `type` VARCHAR(100) NOT NULL,
      `title` VARCHAR(255) NOT NULL,
      `message` LONGTEXT NOT NULL,
      `related_id` INT(11),
      `is_read` BOOLEAN DEFAULT FALSE,
      `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
      INDEX `user_id` (`user_id`),
      INDEX `is_read` (`is_read`),
      INDEX `created_at` (`created_at`),
      FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql3);
    echo "✓ notifications table created successfully\n";
    
    echo "\n✅ All required tables have been created!\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
