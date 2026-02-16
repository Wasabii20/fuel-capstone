<?php
/**
 * Setup script to add vehicle registry columns to the vehicles table
 * This script adds support for: Make, Model, Year, Color, Engine No, Chassis No,
 * Fuel Type, Fuel Capacity, GPS Configuration, Sensor Configuration, Vehicle Photo
 */

include("db_connect.php");

try {
    // Check if columns exist before adding them
    $checkColumns = $pdo->query("DESCRIBE vehicles");
    $columns = $checkColumns->fetchAll(PDO::FETCH_COLUMN, 0);
    
    $columnsToAdd = [
        'make' => "ALTER TABLE vehicles ADD COLUMN `make` VARCHAR(100) DEFAULT NULL AFTER `vehicle_type`",
        'model' => "ALTER TABLE vehicles ADD COLUMN `model` VARCHAR(100) DEFAULT NULL AFTER `make`",
        'year' => "ALTER TABLE vehicles ADD COLUMN `year` INT DEFAULT NULL AFTER `model`",
        'color' => "ALTER TABLE vehicles ADD COLUMN `color` VARCHAR(50) DEFAULT NULL AFTER `year`",
        'engine_no' => "ALTER TABLE vehicles ADD COLUMN `engine_no` VARCHAR(100) DEFAULT NULL AFTER `color`",
        'chassis_no' => "ALTER TABLE vehicles ADD COLUMN `chassis_no` VARCHAR(100) DEFAULT NULL AFTER `engine_no`",
        'fuel_type' => "ALTER TABLE vehicles ADD COLUMN `fuel_type` VARCHAR(50) DEFAULT 'gasoline' AFTER `chassis_no`",
        'fuel_capacity' => "ALTER TABLE vehicles ADD COLUMN `fuel_capacity` DECIMAL(10,2) DEFAULT 0.00 AFTER `fuel_type`",
        'gps_enabled' => "ALTER TABLE vehicles ADD COLUMN `gps_enabled` TINYINT DEFAULT 0 AFTER `fuel_capacity`",
        'gps_device_id' => "ALTER TABLE vehicles ADD COLUMN `gps_device_id` VARCHAR(100) DEFAULT NULL AFTER `gps_enabled`",
        'sensor_enabled' => "ALTER TABLE vehicles ADD COLUMN `sensor_enabled` TINYINT DEFAULT 0 AFTER `gps_device_id`",
        'sensor_device_id' => "ALTER TABLE vehicles ADD COLUMN `sensor_device_id` VARCHAR(100) DEFAULT NULL AFTER `sensor_enabled`",
        'vehicle_photo' => "ALTER TABLE vehicles ADD COLUMN `vehicle_photo` VARCHAR(255) DEFAULT NULL AFTER `sensor_device_id`"
    ];
    
    $addedColumns = [];
    $skippedColumns = [];
    
    foreach ($columnsToAdd as $columnName => $alterSQL) {
        if (!in_array($columnName, $columns)) {
            try {
                $pdo->query($alterSQL);
                $addedColumns[] = $columnName;
            } catch (Exception $e) {
                $skippedColumns[$columnName] = $e->getMessage();
            }
        } else {
            $skippedColumns[$columnName] = "Column already exists";
        }
    }
    
    // Create vehicle_photos upload directory if it doesn't exist
    $uploadDir = 'uploads/vehicle_photos/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Vehicle Registry Setup</title>
        <style>
            body {
                font-family: 'Poppins', Arial, sans-serif;
                background: linear-gradient(135deg, #1e1e2d 0%, #2a2a3e 100%);
                color: #e2e2e2;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }

            .container {
                background: #2a2a3e;
                border: 1px solid rgba(93, 93, 255, 0.2);
                border-radius: 16px;
                padding: 40px;
                max-width: 600px;
                width: 100%;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            }

            h1 {
                color: #5d5dff;
                margin-bottom: 10px;
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .subtitle {
                color: #a2a2c2;
                margin-bottom: 30px;
                font-size: 0.95rem;
            }

            .status {
                padding: 15px;
                border-radius: 8px;
                margin-bottom: 15px;
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .status.success {
                background: rgba(34, 197, 94, 0.1);
                border: 1px solid rgba(34, 197, 94, 0.3);
                color: #22c55e;
            }

            .status.info {
                background: rgba(59, 130, 246, 0.1);
                border: 1px solid rgba(59, 130, 246, 0.3);
                color: #3b82f6;
            }

            .status.skip {
                background: rgba(156, 163, 175, 0.1);
                border: 1px solid rgba(156, 163, 175, 0.3);
                color: #9ca3af;
                font-size: 0.9rem;
            }

            .status i {
                font-size: 1.2rem;
                flex-shrink: 0;
            }

            .section {
                margin-bottom: 25px;
            }

            .section-title {
                color: #5d5dff;
                font-weight: 600;
                font-size: 1.05rem;
                margin-bottom: 12px;
                padding-bottom: 8px;
                border-bottom: 2px solid rgba(93, 93, 255, 0.2);
            }

            .button-group {
                display: flex;
                gap: 10px;
                justify-content: center;
                margin-top: 30px;
            }

            .btn {
                padding: 12px 24px;
                border: none;
                border-radius: 8px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                gap: 8px;
            }

            .btn-primary {
                background: linear-gradient(135deg, #5d5dff 0%, #4d4ddd 100%);
                color: white;
            }

            .btn-primary:hover {
                transform: translateY(-2px);
                box-shadow: 0 10px 25px rgba(93, 93, 255, 0.3);
            }

            .btn-secondary {
                background: rgba(255, 255, 255, 0.1);
                color: #a2a2c2;
                border: 1px solid rgba(255, 255, 255, 0.2);
            }

            .btn-secondary:hover {
                background: rgba(255, 255, 255, 0.15);
                border-color: #5d5dff;
                color: #5d5dff;
            }

            .summary {
                background: rgba(93, 93, 255, 0.05);
                border: 1px solid rgba(93, 93, 255, 0.2);
                border-radius: 8px;
                padding: 15px;
                margin-top: 20px;
            }

            .summary-item {
                display: flex;
                justify-content: space-between;
                padding: 8px 0;
                border-bottom: 1px solid rgba(93, 93, 255, 0.1);
            }

            .summary-item:last-child {
                border-bottom: none;
            }

            .summary-label {
                color: #a2a2c2;
            }

            .summary-value {
                color: #5d5dff;
                font-weight: 600;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>
                <i class="fas fa-wrench"></i> Vehicle Registry Setup
            </h1>
            <p class="subtitle">Database configuration for enhanced vehicle management</p>

            <div class="section">
                <div class="section-title">✓ Database Columns</div>
                
                <?php if (!empty($addedColumns)): ?>
                    <div class="status success">
                        <i class="fas fa-check-circle"></i>
                        <div>
                            <strong>Successfully Added (<?php echo count($addedColumns); ?>)</strong>
                            <br><small><?php echo implode(", ", $addedColumns); ?></small>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($skippedColumns)): ?>
                    <div class="status skip">
                        <i class="fas fa-info-circle"></i>
                        <div>
                            <strong>Already Exist or Skipped (<?php echo count($skippedColumns); ?>)</strong>
                            <br><small><?php foreach ($skippedColumns as $col => $reason): echo htmlspecialchars($col) . " • "; endforeach; ?></small>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="section">
                <div class="section-title">📁 File System</div>
                
                <div class="status success">
                    <i class="fas fa-check-circle"></i>
                    <div>
                        <strong>Upload Directory Created</strong>
                        <br><small><?php echo htmlspecialchars($uploadDir); ?></small>
                    </div>
                </div>
            </div>

            <div class="section">
                <div class="section-title">📋 New Vehicle Registry Features</div>
                
                <div class="status info">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <strong>Vehicle Details Tracking</strong>
                        <br><small>Make, Model, Year, Color, Engine Number, Chassis Number</small>
                    </div>
                </div>

                <div class="status info">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <strong>Fuel Management</strong>
                        <br><small>Fuel Type, Tank Capacity, Current Fuel Level</small>
                    </div>
                </div>

                <div class="status info">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <strong>GPS & Sensor Configuration</strong>
                        <br><small>GPS Device ID, Sensor Device ID, Enable/Disable Tracking</small>
                    </div>
                </div>

                <div class="status info">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <strong>Vehicle Photography</strong>
                        <br><small>Upload and store vehicle photos for identification</small>
                    </div>
                </div>
            </div>

            <div class="summary">
                <div class="summary-item">
                    <span class="summary-label">Total Columns Added</span>
                    <span class="summary-value"><?php echo count($addedColumns); ?></span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Setup Status</span>
                    <span class="summary-value" style="color: #22c55e;">✓ Complete</span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Next Step</span>
                    <span class="summary-value">Go to Vehicle Registry</span>
                </div>
            </div>

            <div class="button-group">
                <a href="vehicle_registry.php" class="btn btn-primary">
                    <i class="fas fa-arrow-right"></i> Go to Vehicle Registry
                </a>
                <a href="Profile_Admin.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Go to Dashboard
                </a>
            </div>
        </div>
    </body>
    </html>
    <?php
} catch (Exception $e) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Vehicle Registry Setup - Error</title>
        <style>
            body {
                font-family: 'Poppins', Arial, sans-serif;
                background: linear-gradient(135deg, #1e1e2d 0%, #2a2a3e 100%);
                color: #e2e2e2;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }

            .container {
                background: #2a2a3e;
                border: 1px solid rgba(231, 76, 60, 0.3);
                border-radius: 16px;
                padding: 40px;
                max-width: 600px;
                width: 100%;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            }

            h1 {
                color: #ef4444;
                margin-bottom: 20px;
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .error-message {
                background: rgba(239, 68, 68, 0.1);
                border: 1px solid rgba(239, 68, 68, 0.3);
                color: #fca5a5;
                padding: 15px;
                border-radius: 8px;
                margin-bottom: 20px;
            }

            .btn {
                display: inline-block;
                padding: 12px 24px;
                background: linear-gradient(135deg, #5d5dff 0%, #4d4ddd 100%);
                color: white;
                border: none;
                border-radius: 8px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s;
                text-decoration: none;
            }

            .btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 10px 25px rgba(93, 93, 255, 0.3);
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>
                <i class="fas fa-exclamation-circle"></i> Setup Error
            </h1>
            <div class="error-message">
                <strong>Error:</strong> <?php echo htmlspecialchars($e->getMessage()); ?>
            </div>
            <p style="color: #a2a2c2; margin-bottom: 20px;">
                There was an issue setting up the vehicle registry database. Please check the error message above and try again.
            </p>
            <a href="vehicle_registry.php" class="btn">Go Back to Vehicle Registry</a>
        </div>
    </body>
    </html>
    <?php
}
?>
