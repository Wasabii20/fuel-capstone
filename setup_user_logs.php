<?php
session_start();
include("db_connect.php");

// Check if user is logged in and is admin
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$message = '';
$message_type = '';

// Create the table if it doesn't exist
$create_table_query = "CREATE TABLE IF NOT EXISTS user_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL,
    role ENUM('admin','user','chief') NOT NULL,
    action VARCHAR(100) NOT NULL DEFAULT 'login',
    date_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    browser VARCHAR(255),
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_role (role),
    INDEX idx_date_time (date_time),
    FOREIGN KEY (username) REFERENCES users(username) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

try {
    $pdo->exec($create_table_query);
    $message = "User logs table created successfully!";
    $message_type = 'success';
} catch (PDOException $e) {
    $message = "Error creating table: " . $e->getMessage();
    $message_type = 'error';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create User Logs Table - BFP Fuel System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --bfp-red: #B22222;
            --dark-blue: #2c3e50;
        }

        body {
            font-family: 'Poppins', Arial, sans-serif;
            background: linear-gradient(135deg, var(--dark-blue), var(--bfp-red));
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            background: white;
            padding: 50px;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            text-align: center;
            max-width: 500px;
        }

        .icon {
            font-size: 4rem;
            margin-bottom: 20px;
        }

        h1 {
            color: var(--dark-blue);
            margin-bottom: 15px;
            font-size: 2rem;
        }

        .message {
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            font-size: 1.1rem;
            font-weight: 500;
        }

        .success {
            background: rgba(76, 175, 80, 0.2);
            color: #4CAF50;
            border: 2px solid #4CAF50;
        }

        .error {
            background: rgba(244, 67, 54, 0.2);
            color: #F44336;
            border: 2px solid #F44336;
        }

        .button {
            display: inline-block;
            background: linear-gradient(135deg, var(--bfp-red) 0%, #FF4500 100%);
            color: white;
            padding: 15px 40px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            margin-top: 20px;
            transition: transform 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 1rem;
        }

        .button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(178, 34, 34, 0.3);
        }

        .info-text {
            color: #666;
            margin-top: 20px;
            line-height: 1.6;
            font-size: 0.95rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">
            <?php if ($message_type === 'success'): ?>
                <i class="fas fa-check-circle" style="color: #4CAF50;"></i>
            <?php else: ?>
                <i class="fas fa-exclamation-circle" style="color: #F44336;"></i>
            <?php endif; ?>
        </div>

        <h1>Database Setup</h1>

        <div class="message <?php echo $message_type; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>

        <p class="info-text">
            The <strong>user_logs</strong> table has been initialized. This table will store all user activity logs including login records, timestamps, browser information, and more.
        </p>

        <button class="button" onclick="location.href='User_Logs.php'">
            <i class="fas fa-arrow-right"></i> Go to User Logs
        </button>
    </div>
</body>
</html>
