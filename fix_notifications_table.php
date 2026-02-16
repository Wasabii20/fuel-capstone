<?php
include 'db_connect.php';

try {
    // Add related_data column to notifications table if it doesn't exist
    $check_column = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                     WHERE TABLE_NAME = 'notifications' AND COLUMN_NAME = 'related_data'";
    
    $result = $pdo->query($check_column);
    
    if ($result->rowCount() == 0) {
        // Column doesn't exist, add it
        $alter_sql = "ALTER TABLE notifications ADD COLUMN related_data LONGTEXT AFTER related_id";
        $pdo->exec($alter_sql);
        echo "✓ Added 'related_data' column to notifications table<br>";
    } else {
        echo "✓ Column 'related_data' already exists<br>";
    }
    
    echo "✅ Database update complete!<br>";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Setup Complete</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #1e1e2d;
            color: #a2a2c2;
        }
        .success {
            background: rgba(34, 197, 94, 0.2);
            border: 1px solid #22c55e;
            padding: 20px;
            border-radius: 8px;
            color: #22c55e;
        }
    </style>
</head>
<body>
    <div class="success">
        <h2>✅ Database Setup Complete!</h2>
        <p>The notifications table has been updated. You can now use the trip request feature.</p>
        <p><a href="javascript:history.back()" style="color: #5d5dff;">Go back</a></p>
    </div>
</body>
</html>
