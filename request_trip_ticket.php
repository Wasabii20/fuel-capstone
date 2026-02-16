<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

$current_role = $_SESSION['role'] ?? '';
$current_user_id = $_SESSION['user_id'];

include 'db_connect.php';

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    try {
        // Get user info
        $user_query = "SELECT first_name, last_name, username, position FROM users WHERE id = ?";
        $user_stmt = $pdo->prepare($user_query);
        $user_stmt->execute([$current_user_id]);
        $user_info = $user_stmt->fetch(PDO::FETCH_ASSOC);
        $user_name = $user_info['first_name'] . ' ' . $user_info['last_name'];
        
        // Send notifications to all admins
        $admin_query = "SELECT id FROM users WHERE role = 'admin'";
        $admin_result = $pdo->query($admin_query);
        $admins = $admin_result->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($admins)) {
            $error_message = "No admins found to notify. Please contact the administrator.";
        } else {
            foreach ($admins as $admin) {
                $notif_query = "INSERT INTO notifications (user_id, type, title, message, related_id, created_at) 
                              VALUES (?, ?, ?, ?, ?, NOW())";
                $notif_stmt = $pdo->prepare($notif_query);
                $notif_stmt->execute([
                    $admin['id'],
                    'trip_request',
                    "🚗 Trip Request from {$user_name}",
                    "Driver: {$user_name} ({$user_info['position']})\nUser ID: {$current_user_id}",
                    $current_user_id
                ]);
            }
            
            $success_message = "✓ Trip request submitted! Admins have been notified and will create your ticket.";
        }
        
    } catch (PDOException $e) {
        $error_message = "Error submitting request: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Trip Ticket - BFP Fuel System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1e1e2d 0%, #16213e 100%);
            color: #e0e0e0;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 2rem;
            color: #5d5dff;
            margin-left: 15px;
        }

        .header-icon {
            font-size: 2.5rem;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            animation: slideDown 0.3s ease;
        }

        .alert-success {
            background: rgba(34, 197, 94, 0.15);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #22c55e;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #ef4444;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .form-card {
            background: rgba(30, 30, 45, 0.8);
            border: 1px solid rgba(93, 93, 255, 0.2);
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 30px;
            backdrop-filter: blur(10px);
        }

        .form-section-title {
            font-size: 1.2rem;
            color: #5d5dff;
            margin-bottom: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #a2a2c2;
            font-weight: 500;
        }

        label .required {
            color: #ef4444;
        }

        input, select, textarea {
            width: 100%;
            padding: 12px 15px;
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(93, 93, 255, 0.2);
            border-radius: 8px;
            color: #e0e0e0;
            font-family: inherit;
            font-size: 1rem;
            transition: all 0.3s;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            background: rgba(93, 93, 255, 0.08);
            border-color: #5d5dff;
            box-shadow: 0 0 10px rgba(93, 93, 255, 0.2);
        }

        textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .btn-submit {
            background: linear-gradient(135deg, #5d5dff 0%, #3d3dff 100%);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
            font-size: 1rem;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(93, 93, 255, 0.3);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .requests-section {
            display: none;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .header {
                flex-direction: column;
                text-align: center;
            }

            .header h1 {
                margin-left: 0;
                margin-top: 10px;
            }

            .requests-table {
                font-size: 0.9rem;
            }

            .requests-table th, .requests-table td {
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-icon">📝</div>
            <h1>Request Trip Ticket</h1>
        </div>

        <!-- Messages -->
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <span>✓</span>
                <div><?php echo htmlspecialchars($success_message); ?></div>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-error">
                <span>✕</span>
                <div><?php echo htmlspecialchars($error_message); ?></div>
            </div>
        <?php endif; ?>

        <!-- Request Form -->
        <div class="form-card">
            <div class="form-section-title">🚗 Request Trip Ticket</div>
            
            <form method="POST" action="">
                <div style="text-align: center; padding: 40px 0;">
                    <p style="font-size: 1.1rem; color: #a2a2c2; margin-bottom: 30px;">
                        Click the button below to request a trip ticket. Admins will review your request and create the ticket with full details.
                    </p>
                    <button type="submit" name="submit_request" class="btn-submit" style="max-width: 300px; margin: 0 auto;">
                        🚀 Request Trip Ticket
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
