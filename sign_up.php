<?php
session_start();
include("db_connect.php");
$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    $admin_code = trim($_POST['admin_code'] ?? '');

    try {
        // Validation
        if (empty($username) || empty($password) || empty($role)) {
            $message = "❌ All fields are required!";
        } elseif ($role === 'admin' && empty($admin_code)) {
            $message = "❌ Admin code is required for admin registration!";
        } elseif ($role === 'admin') {
            // Validate admin code (default is 'Admin123')
            $stored_code = isset($_SESSION['admin_code']) ? $_SESSION['admin_code'] : 'Admin123';
            if ($admin_code !== $stored_code) {
                $message = "❌ Invalid admin code!";
            } else {
                // Check if username exists
                $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $check->execute([$username]);
                $exists = $check->fetch();

                if ($exists) {
                    $message = "⚠ Username already exists!";
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $default_profile = 'ALBUM/default.png';
                    $stmt = $pdo->prepare("INSERT INTO users (username, password, role, profile_pic) VALUES (?, ?, ?, ?)");

                    if ($stmt->execute([$username, $hashed_password, $role, $default_profile])) {
                        $message = "✅ Registration successful! <a href='index.php'>Login now</a>";
                    } else {
                        $message = "❌ Error during registration. Please try again.";
                    }
                }
            }
        } else {
            // Check if username exists
            $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $check->execute([$username]);
            $exists = $check->fetch();

            if ($exists) {
                $message = "⚠ Username already exists!";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $default_profile = 'ALBUM/default.png';
                $stmt = $pdo->prepare("INSERT INTO users (username, password, role, profile_pic) VALUES (?, ?, ?, ?)");

                if ($stmt->execute([$username, $hashed_password, $role, $default_profile])) {
                    $message = "✅ Registration successful! <a href='index.php'>Login now</a>";
                } else {
                    $message = "❌ Error during registration. Please try again.";
                }
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
  <title>Bureau of Fire Protection - Sign Up</title>
  <link rel="icon" href="images/bfp_logo.png" type="image/x-icon">
  <style>
    :root {
      --primary-bg: #1e1e2d;
      --secondary-bg: #2a2a3e;
      --accent-color: #5d5dff;
      --text-primary: #ffffff;
      --text-secondary: #a2a2c2;
      --border-color: rgba(93, 93, 255, 0.2);
      --transition: 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: var(--primary-bg);
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      color: var(--text-primary);
      padding: 20px;
      position: relative;
      overflow: hidden;
    }

    body::before {
      content: "";
      position: absolute;
      top: 0;
      left: 0;
      width: 60vw;
      height: 100vh;
      background: url('../CAPSTONE/ALBUM/BackIMG.jpg') no-repeat center center;
      background-size: cover;
      clip-path: polygon(0 0, 100% 0, 70% 100%, 0% 100%);
      opacity: 0.4;
      z-index: 0;
    }

    .login-container {
      background: var(--secondary-bg);
      padding: 50px 40px;
      border-radius: 16px;
      width: 100%;
      max-width: 400px;
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4);
      border: 1px solid var(--border-color);
      animation: slideUp 0.5s ease;
      position: relative;
      z-index: 1;
    }

    @keyframes slideUp {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .login-container img {
      width: 70px;
      height: 70px;
      margin-bottom: 25px;
      display: block;
      margin-left: auto;
      margin-right: auto;
    }

    h2 {
      margin-bottom: 30px;
      color: var(--accent-color);
      font-size: 1.8rem;
      font-weight: 700;
      letter-spacing: 0.5px;
    }

    input[type="text"], input[type="password"], select {
      width: 100%;
      padding: 12px 15px;
      margin-bottom: 15px;
      border: 1px solid var(--border-color);
      border-radius: 8px;
      background: rgba(93, 93, 255, 0.05);
      color: var(--text-primary);
      font-size: 15px;
      transition: all var(--transition);
    }

    input[type="text"]::placeholder, input[type="password"]::placeholder {
      color: var(--text-secondary);
    }

    select {
      cursor: pointer;
    }

    select option {
      background: var(--secondary-bg);
      color: var(--text-primary);
    }

    input[type="text"]:focus, input[type="password"]:focus, select:focus {
      outline: none;
      border-color: var(--accent-color);
      background: rgba(93, 93, 255, 0.1);
      box-shadow: 0 0 0 3px rgba(93, 93, 255, 0.1);
    }

    input[type="submit"] {
      background: linear-gradient(90deg, var(--accent-color) 0%, #7a7aff 100%);
      border: none;
      padding: 13px 20px;
      width: 100%;
      border-radius: 8px;
      font-size: 16px;
      color: white;
      font-weight: 600;
      cursor: pointer;
      margin-top: 10px;
      transition: all var(--transition);
    }

    input[type="submit"]:hover {
      background: linear-gradient(90deg, #4a4aff 0%, #6a6aff 100%);
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(93, 93, 255, 0.4);
    }

    input[type="submit"]:active {
      transform: translateY(0);
    }

    .message {
      background: rgba(46, 204, 113, 0.15);
      color: #5dff71;
      padding: 12px 15px;
      border-radius: 8px;
      margin-top: 20px;
      font-size: 14px;
      border-left: 3px solid #2ecc71;
    }

    .message a {
      color: var(--accent-color);
      text-decoration: none;
      font-weight: 600;
      transition: all var(--transition);
    }

    .message a:hover {
      color: #7a7aff;
      text-decoration: underline;
    }

    .footer {
      margin-top: 25px;
      font-size: 13px;
      color: var(--text-secondary);
      text-align: center;
    }

    .footer-links {
      margin-bottom: 15px;
    }

    .footer-links a {
      color: var(--accent-color);
      text-decoration: none;
      font-weight: 600;
      transition: all var(--transition);
      margin-right: 15px;
    }

    .footer-links a:hover {
      color: #7a7aff;
      text-decoration: underline;
    }

    .footer-links a:last-child {
      margin-right: 0;
    }
  </style>
</head>
<body>
  <div class="login-container">
    <img src="../CAPSTONE/ALBUM/Official Logo.png" alt="BFP Logo">
    <h2>BFP Sign Up</h2>
    <form method="POST" action="">
      <input type="text" name="username" placeholder="Username" required><br>
      <input type="password" name="password" placeholder="Password" required><br>
      <select name="role" required onchange="toggleAdminCode(this.value)">
        <option value="">Select Role</option>
        <option value="driver">User</option>
        <option value="admin">Admin</option>
      </select><br>
      <div id="adminCodeContainer" style="display:none;">
        <input type="password" name="admin_code" placeholder="Enter Admin code" id="adminCodeInput"><br>
      </div>
      <input type="submit" value="Sign Up">
    </form>
    <script>
      function toggleAdminCode(role) {
        const container = document.getElementById('adminCodeContainer');
        const input = document.getElementById('adminCodeInput');
        if (role === 'admin') {
          container.style.display = 'block';
          input.required = true;
        } else {
          container.style.display = 'none';
          input.required = false;
          input.value = '';
        }
      }
    </script>
    <?php if (!empty($message)): ?>
      <div class="message"><?php echo $message; ?></div>
    <?php endif; ?>
    <div class="footer-links">
      <a href="index.php">Already have an Account?</a>
    </div>
    <div class="footer">
      &copy; <?php echo date("Y"); ?> Bureau of Fire Protection
    </div>
  </div>