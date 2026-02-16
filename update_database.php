<?php
include 'db_connect.php';

try {
    // Check if notifications table exists and add related_data column if needed
    $alter_sql = "ALTER TABLE notifications ADD COLUMN related_data LONGTEXT AFTER related_id";
    
    try {
        $pdo->exec($alter_sql);
        echo "✓ Added related_data column to notifications table\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "✓ Column related_data already exists in notifications table\n";
        } else {
            throw $e;
        }
    }
    
    echo "✅ Database updates complete!\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
