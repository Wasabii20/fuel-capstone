<?php
/**
 * Fuel Stocks Database Setup
 * Run this file once to create the fuel_stocks table
 */

require_once 'db_connect.php';

try {
    // Create fuel_stocks table
    $createTableSQL = "
    CREATE TABLE IF NOT EXISTS fuel_stocks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        stock_type VARCHAR(50) NOT NULL DEFAULT 'gasoline' COMMENT 'Type of fuel stock',
        amount DECIMAL(10, 2) NOT NULL COMMENT 'Amount in liters',
        transaction_type ENUM('added', 'removed') NOT NULL COMMENT 'Type of transaction',
        note TEXT COMMENT 'Notes about the transaction',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_stock_type (stock_type),
        INDEX idx_transaction_type (transaction_type),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Tracks all fuel stock transactions (additions and removals)';
    ";
    
    $pdo->exec($createTableSQL);
    echo "✓ fuel_stocks table created successfully!";
    
} catch (Exception $e) {
    echo "✗ Error creating table: " . $e->getMessage();
}
?>
