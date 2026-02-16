<?php
session_start();
include("db_connect.php");
$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = trim($_POST['full_name']);
    $license_no = trim($_POST['license_no']);

    try {
        // Check if license number already exists
        $check = $pdo->prepare("SELECT driver_id FROM drivers WHERE license_no = ?");
        $check->execute([$license_no]);
        $exists = $check->fetch();

        if ($exists) {
            $message = "⚠ This license number is already registered!";
        } else if (empty($full_name) || empty($license_no)) {
            $message = "⚠ Please fill in all required fields!";
        } else {
            // Insert driver information
            $driver_stmt = $pdo->prepare("INSERT INTO drivers (full_name, license_no, status) VALUES (?, ?, 'active')");
            
            if ($driver_stmt->execute([$full_name, $license_no])) {
                $driver_id = $pdo->lastInsertId();
                
                // Update current user's driver_id if logged in
                if (isset($_SESSION['user_id'])) {
                    try {
                        $update_user = $pdo->prepare("UPDATE users SET driver_id = ? WHERE id = ?");
                        $update_user->execute([$driver_id, $_SESSION['user_id']]);
                        $_SESSION['driver_id'] = $driver_id;  // Update session
                    } catch(Exception $e) {
                        // Log update error but don't block registration
                    }
                }
                
                $message = "✅ Driver registration successful! Driver ID: <strong>$driver_id</strong><br><a href='Profile_user.php'>Go to Profile</a>";
            } else {
                $message = "❌ Error during registration. Please try again.";
            }
        }
    } catch(Exception $e) {
        $message = "❌ Database error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Driver Sign Up - BFP Fuel System</title>
  <link rel="icon" href="images/bfp_logo.png" type="image/x-icon">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: linear-gradient(135deg, #8B0000 0%, #B22222 100%);
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      padding: 20px;
    }

    .container {
      background: white;
      border-radius: 15px;
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
      width: 100%;
      max-width: 500px;
      padding: 40px;
    }

    .header {
      text-align: center;
      margin-bottom: 30px;
    }

    .header h1 {
      color: #8B0000;
      font-size: 28px;
      margin-bottom: 10px;
    }

    .header p {
      color: #666;
      font-size: 14px;
    }

    .form-group {
      margin-bottom: 20px;
    }

    label {
      display: block;
      margin-bottom: 8px;
      color: #333;
      font-weight: 500;
      font-size: 14px;
    }

    input[type="text"],
    input[type="email"],
    input[type="tel"],
    input[type="password"] {
      width: 100%;
      padding: 12px 15px;
      border: 2px solid #ddd;
      border-radius: 8px;
      font-size: 14px;
      transition: border-color 0.3s;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    input[type="text"]:focus,
    input[type="email"]:focus,
    input[type="tel"]:focus,
    input[type="password"]:focus {
      outline: none;
      border-color: #8B0000;
      box-shadow: 0 0 5px rgba(139, 0, 0, 0.2);
    }

    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 15px;
    }

    .button-group {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
      margin-top: 25px;
    }

    button,
    a.btn {
      padding: 12px 20px;
      border: none;
      border-radius: 8px;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s;
      text-decoration: none;
      display: block;
      text-align: center;
    }

    button[type="submit"] {
      background: #8B0000;
      color: white;
    }

    button[type="submit"]:hover {
      background: #650000;
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(139, 0, 0, 0.3);
    }

    a.btn-back {
      background: #e0e0e0;
      color: #333;
    }

    a.btn-back:hover {
      background: #d0d0d0;
      transform: translateY(-2px);
    }

    .message {
      padding: 12px 15px;
      border-radius: 8px;
      margin-bottom: 20px;
      font-size: 14px;
    }

    .message {
      background-color: #f0f0f0;
      color: #333;
      border-left: 4px solid #999;
    }

    .footer-link {
      text-align: center;
      margin-top: 20px;
      color: #666;
      font-size: 14px;
    }

    .footer-link a {
      color: #8B0000;
      text-decoration: none;
      font-weight: 600;
    }

    .footer-link a:hover {
      text-decoration: underline;
    }

    .role-info {
      background: #f5f5f5;
      padding: 12px;
      border-radius: 8px;
      margin-bottom: 20px;
      font-size: 13px;
      color: #555;
      border-left: 4px solid #8B0000;
    }

    .required {
      color: #8B0000;
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>🚗 Driver Sign Up</h1>
      <p>Register as a driver in the BFP Fuel Management System</p>
    </div>

    <?php if ($message): ?>
      <div class="message"><?php echo $message; ?></div>
    <?php endif; ?>

    <div class="role-info">
      ℹ️ Register your driver information. You'll receive a Driver ID after registration.
    </div>

    <form method="POST" action="">
      <!-- Full Name -->
      <div class="form-group">
        <label for="full_name">Full Name <span class="required">*</span></label>
        <input type="text" id="full_name" name="full_name" required placeholder="Enter your full name">
      </div>

      <!-- License Number -->
      <div class="form-group">
        <label for="license_no">Driver's License Number <span class="required">*</span></label>
        <input type="text" id="license_no" name="license_no" required placeholder="e.g., D-12-34-567890">
      </div>

      <div class="button-group">
        <button type="submit">Register Driver</button>
        <a href="index.php" class="btn btn-back">Back</a>
      </div>
    </form>

    <div class="footer-link">
      Already have an account? <a href="index.php">Login here</a>
    </div>
  </div>
</body>
</html>
