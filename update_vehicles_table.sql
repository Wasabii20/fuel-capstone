-- Update script to add missing columns to vehicles table
-- Run this if you already have data in the vehicles table

ALTER TABLE `vehicles` 
ADD COLUMN `make` varchar(100) DEFAULT NULL AFTER `vehicle_type`,
ADD COLUMN `model` varchar(100) DEFAULT NULL AFTER `make`,
ADD COLUMN `year` int(4) DEFAULT NULL AFTER `model`,
ADD COLUMN `color` varchar(50) DEFAULT NULL AFTER `year`,
ADD COLUMN `engine_no` varchar(100) DEFAULT NULL AFTER `color`,
ADD COLUMN `chassis_no` varchar(100) DEFAULT NULL AFTER `engine_no`,
ADD COLUMN `fuel_type` enum('gasoline','diesel','hybrid','lpg') DEFAULT 'gasoline' AFTER `chassis_no`,
ADD COLUMN `fuel_capacity` decimal(10,2) DEFAULT 0.00 AFTER `fuel_type`,
ADD COLUMN `gps_enabled` tinyint(1) DEFAULT 0 AFTER `fuel_capacity`,
ADD COLUMN `gps_device_id` varchar(100) DEFAULT NULL AFTER `gps_enabled`,
ADD COLUMN `sensor_enabled` tinyint(1) DEFAULT 0 AFTER `gps_device_id`,
ADD COLUMN `sensor_device_id` varchar(100) DEFAULT NULL AFTER `sensor_enabled`,
ADD COLUMN `vehicle_photo` varchar(255) DEFAULT NULL AFTER `sensor_device_id`;

-- Reorder columns to match the new schema
ALTER TABLE `vehicles`
MODIFY COLUMN `current_fuel` decimal(10,2) DEFAULT 0.00,
MODIFY COLUMN `description` text DEFAULT NULL,
MODIFY COLUMN `status` enum('available','deployed','inactive','in_repair') DEFAULT 'available',
MODIFY COLUMN `created_at` timestamp NOT NULL DEFAULT current_timestamp();

-- Optional: Update existing records with default fuel capacity
UPDATE `vehicles` SET `fuel_capacity` = 100.00 WHERE `fuel_capacity` = 0.00;
UPDATE `vehicles` SET `gps_enabled` = 1 WHERE `gps_enabled` = 0;
UPDATE `vehicles` SET `sensor_enabled` = 1 WHERE `sensor_enabled` = 0;
