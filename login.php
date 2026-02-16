<?php
session_start();
include("db_connect.php");
include("utils/log_activity.php");

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    try {
        $stmt = $pdo->prepare("SELECT id, username, password, role FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $row = $stmt->fetch();

        if ($row && password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role'];

            // Log the login activity
            logLogin($pdo, $row['id']);

            if ($row['role'] === "admin") {
                header("Location: Profile_Admin.php");
            } elseif ($row['role'] === "chief") {
                header("Location: Profile_Chief.php");
            } else {
                header("Location: Profile_user.php");
            }
            exit;
        } else {
            $error = "❌ Invalid username or password.";
        }
    } catch(Exception $e) {
        $error = "❌ Database error. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Bureau of Fire Protection - Login</title>
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

    input[type="text"], input[type="password"] {
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

    input[type="text"]:focus, input[type="password"]:focus {
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

    .error {
      background: rgba(255, 71, 87, 0.15);
      color: #ff6b7a;
      padding: 12px 15px;
      border-radius: 8px;
      margin-bottom: 20px;
      font-size: 14px;
      border-left: 3px solid #ff4757;
    }

    .footer {
      margin-top: 25px;
      font-size: 13px;
      color: var(--text-secondary);
      text-align: center;
    }

    .footer a {
      color: var(--accent-color);
      text-decoration: none;
      font-weight: 600;
      transition: all var(--transition);
      margin-right: 15px;
    }

    .footer a:hover {
      color: #7a7aff;
      text-decoration: underline;
    }

    .footer a:last-child {
      margin-right: 0;
    }
  </style>
</head>
<body>
  <div class="login-container">
    <img src="../CAPSTONE/ALBUM/Official Logo.png" alt="BFP Logo">
    <h2>BFP LOGIN</h2>
    <?php if($error): ?>
      <div class="error"><?php echo $error; ?></div>
    <?php endif; ?>
    <form method="POST" action="">
      <input type="text" name="username" placeholder="Username" required><br>
      <input type="password" name="password" placeholder="Password" required><br>
      <input type="submit" value="Login">
    </form>
    <div class="footer">
      <div style="margin-top:18px;">
  <a href="sign_up.php" style="color:#FFD700; text-decoration:underline; font-size:15px; margin-right:18px;">Sign Up</a>
  <a href="forgot_password.php" style="color:#FFD700; text-decoration:underline; font-size:15px;">Forgot Password?</a>
</div>
      &copy; <?php echo date("Y"); ?> Bureau of Fire Protection
    </div>
  </div>
</body>
</html>
