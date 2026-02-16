<?php
session_start();
include("db_connect.php");

if (!isset($_SESSION['user_id'])) { 
    header("Location: index.php"); 
    exit(); 
}
$user_id = $_SESSION['user_id'];

$success_message = $error_message = '';

// Handle profile update (including profile picture)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $first_name  = trim($_POST['first_name'] ?? '');
        $last_name   = trim($_POST['last_name'] ?? '');
        $email       = trim($_POST['email'] ?? '');
        $phone       = trim($_POST['phone'] ?? '');
        $department  = trim($_POST['department'] ?? '');
        $position    = trim($_POST['position'] ?? '');
        $employee_id = trim($_POST['employee_id'] ?? '');
        
        $profile_pic_path = '';

        // Handle profile picture if uploaded
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['profile_picture'];
            $allowedTypes = ['image/jpeg','image/png','image/gif'];
            $maxSize = 5*1024*1024;

            if (!in_array($file['type'], $allowedTypes)) {
                $error_message = "Invalid file type. Only JPG, PNG, GIF allowed.";
            } elseif ($file['size'] > $maxSize) {
                $error_message = "File too large. Max 5MB.";
            } else {
                $uploadDir = 'uploads/profile_pics/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $profile_pic_path = $uploadDir.'user_'.$user_id.'_'.time().'.'.$ext;
                if (!move_uploaded_file($file['tmp_name'], $profile_pic_path)) {
                    $error_message = "Failed to upload profile picture.";
                }
            }
        }

        if (empty($error_message)) {
            // Build SQL with profile_pic only if uploaded
            if ($profile_pic_path) {
                $stmt = $pdo->prepare("UPDATE users SET first_name=?, last_name=?, email=?, phone=?, department=?, position=?, employee_id=?, profile_pic=? WHERE id=?");
                $stmt->execute([$first_name, $last_name, $email, $phone, $department, $position, $employee_id, $profile_pic_path, $user_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET first_name=?, last_name=?, email=?, phone=?, department=?, position=?, employee_id=? WHERE id=?");
                $stmt->execute([$first_name, $last_name, $email, $phone, $department, $position, $employee_id, $user_id]);
            }

            $_SESSION['first_name'] = $first_name;
            $_SESSION['last_name'] = $last_name;
            $success_message = "Profile updated successfully!";
        }
    } catch(Exception $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    try {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error_message = "All password fields are required.";
        } elseif (strlen($new_password) < 6) {
            $error_message = "New password must be at least 6 characters long.";
        } elseif ($new_password !== $confirm_password) {
            $error_message = "New passwords do not match.";
        } else {
            // Verify current password
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id=?");
            $stmt->execute([$user_id]);
            $user_data = $stmt->fetch();
            
            if (!$user_data || !password_verify($current_password, $user_data['password'])) {
                $error_message = "Current password is incorrect.";
            } else {
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_stmt = $pdo->prepare("UPDATE users SET password=? WHERE id=?");
                $update_stmt->execute([$hashed_password, $user_id]);
                $success_message = "Password changed successfully!";
            }
        }
    } catch(Exception $e) {
        $error_message = "Error changing password: " . $e->getMessage();
    }
}

// Fetch user data
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        session_unset();
        session_destroy();
        header("Location: index.php");
        exit();
    }
} catch(Exception $e) {
    header("Location: index.php");
    exit();
}

// Profile picture display
$default_pic_url = 'ALBUM/default_profile.png';
$profile_pic_src = !empty($user['profile_pic']) && file_exists($user['profile_pic']) ? $user['profile_pic'] : $default_pic_url;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile | BFP</title>
    <link rel="icon" href="ALBUM/favicon_io/favicon-32x32.png" type="image/x-icon" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet" />
    <style>
        :root {
            --primary-color: #5d5dff;
            --bg-dark: #1e1e2d;
            --bg-light-dark: #2a2a3e;
            --text-light: #e2e2e2;
            --text-gray: #a2a2c2;
            --border-color: #3d3d5c;
        }

        * { box-sizing: border-box; }
        
        body {
            margin: 0;
            font-family: 'Poppins', Arial, sans-serif;
            background: var(--bg-dark);
            color: var(--text-light);
            display: flex;
            flex-direction: column;
            height: 100vh;
        }

        .wrapper { 
            display: flex; 
            flex: 1; 
            overflow: visible; 
            width: 100%; 
            position: relative; 
        }
        
        main { 
            display: flex;
            flex-direction: column;
            flex: 1; 
            overflow-y: auto;
            overflow-x: hidden;
            width: 100%;
            padding: 0;
        }

        .dashboard-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--bg-light-dark);
            padding: 40px;
            border-radius: 0;
            border-bottom: 3px solid var(--primary-color);
            width: 100%;
            flex-shrink: 0;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
        }

        .header-title {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .header-title h1 {
            color: var(--primary-color);
            font-size: 2rem;
            margin: 0;
            font-weight: 700;
        }

        .header-title i {
            font-size: 2rem;
            color: var(--primary-color);
        }

        .content-area {
            padding: 40px;
            flex: 1;
            overflow-y: auto;
            width: 100%;
        }

        .form-container {
            background: var(--bg-light-dark);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
            max-width: 1900px;
        }

        .profile-preview {
            display: flex;
            align-items: center;
            gap: 30px;
            margin-bottom: 40px;
            padding-bottom: 40px;
            border-bottom: 1px solid var(--border-color);
        }

        .profile-picture-container {
            position: relative;
            cursor: pointer;
            flex-shrink: 0;
        }

        .profile-picture {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--primary-color);
            box-shadow: 0 0 20px rgba(93, 93, 255, 0.3);
            transition: transform 0.3s ease;
        }

        .profile-picture-container:hover .profile-picture {
            transform: scale(1.05);
        }

        .edit-overlay {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 50px;
            height: 50px;
            background: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            box-shadow: 0 4px 12px rgba(93, 93, 255, 0.4);
            transition: all 0.3s ease;
        }

        .profile-picture-container:hover .edit-overlay {
            transform: scale(1.1);
        }

        .profile-info h2 {
            color: var(--primary-color);
            font-size: 1.8rem;
            margin: 0 0 10px 0;
            font-weight: 700;
        }

        .profile-info p {
            color: var(--text-gray);
            margin: 5px 0;
            font-size: 0.95rem;
        }

        .profile-info .role-badge {
            display: inline-block;
            background: rgba(93, 93, 255, 0.2);
            color: var(--primary-color);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-top: 10px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            font-weight: 600;
            color: var(--text-light);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.95rem;
        }

        .form-group label i {
            color: var(--primary-color);
            font-size: 1rem;
        }

        .form-group input,
        .form-group select {
            padding: 12px 16px;
            background: var(--bg-dark);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-light);
            font-size: 0.95rem;
            font-family: inherit;
            transition: all 0.3s ease;
            width: 100%;
            min-height: 44px;
        }

        .form-group input::placeholder {
            color: var(--text-gray);
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(93, 93, 255, 0.1);
            background: rgba(93, 93, 255, 0.05);
        }

        .form-group select {
            cursor: pointer;
        }

        .form-group select option {
            background: var(--bg-dark);
            color: var(--text-light);
        }

        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            flex: 1;
            font-family: inherit;
            min-height: 44px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), #7070ff);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(93, 93, 255, 0.4);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .btn-secondary {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-light);
        }

        .btn-secondary:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
            background: rgba(93, 93, 255, 0.1);
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            border-left: 4px solid;
        }

        .alert-success {
            background: rgba(0, 255, 136, 0.1);
            color: #00ff88;
            border-left-color: #00ff88;
        }

        .alert-error {
            background: rgba(255, 80, 100, 0.1);
            color: #ff5064;
            border-left-color: #ff5064;
        }

        .alert i {
            font-size: 1.2rem;
        }
        .password-section {
            border-top: 2px solid var(--border-color);
            padding-top: 30px;
            margin-top: 30px;
        }

        .password-section h3 {
            color: var(--primary-color);
            font-size: 1.3rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
        }

        .password-section h3 i {
            font-size: 1.3rem;
        }

        @media (max-width: 768px) {
            .password-section {
                border-top: 1px solid var(--border-color);
                padding-top: 20px;
                margin-top: 20px;
            }

            .password-section h3 {
                font-size: 1.1rem;
                margin-bottom: 15px;
            }
        }

        @media (max-width: 480px) {
            .password-section {
                padding-top: 15px;
                margin-top: 15px;
            }

            .password-section h3 {
                font-size: 1rem;
                margin-bottom: 12px;
            }

            .password-section h3 i {
                font-size: 1rem;
            }
        }
        footer { display: none; }

        @media (max-width: 768px) {
            .dashboard-header {
                padding: 25px 15px;
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .header-title h1 {
                font-size: 1.5rem;
            }

            .header-title i {
                font-size: 1.5rem;
            }

            .content-area {
                padding: 15px;
            }

            .form-container {
                padding: 20px;
                border-radius: 10px;
            }

            .form-grid {
                grid-template-columns: 1fr;
                gap: 15px;
                margin-bottom: 20px;
            }

            .profile-preview {
                flex-direction: column;
                text-align: center;
                align-items: center;
                gap: 20px;
                margin-bottom: 30px;
                padding-bottom: 30px;
            }

            .profile-picture {
                width: 120px;
                height: 120px;
            }

            .button-group {
                flex-direction: column;
                gap: 10px;
            }

            .btn {
                padding: 12px 20px;
                font-size: 0.95rem;
                flex: 1;
                width: 100%;
            }

            .form-group label {
                font-size: 0.9rem;
                margin-bottom: 8px;
            }

            .form-group input,
            .form-group select {
                padding: 11px 14px;
                font-size: 16px;
                border-radius: 6px;
            }

            .profile-info h2 {
                font-size: 1.5rem;
            }

            .profile-info p {
                font-size: 0.9rem;
            }

            .alert {
                padding: 12px 15px;
                font-size: 0.9rem;
            }
        }

        @media (max-width: 480px) {
            .wrapper {
                flex-direction: column;
            }

            .dashboard-header {
                padding: 20px 12px;
                border-bottom: 2px solid var(--primary-color);
                gap: 10px;
            }

            .header-title {
                gap: 10px;
                width: 100%;
            }

            .header-title h1 {
                font-size: 1.2rem;
                margin: 0;
            }

            .header-title i {
                font-size: 1.2rem;
            }

            .content-area {
                padding: 12px;
            }

            .form-container {
                padding: 15px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            }

            .form-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .profile-preview {
                flex-direction: column;
                text-align: center;
                gap: 15px;
                margin-bottom: 25px;
                padding-bottom: 20px;
                border-bottom: 1px solid var(--border-color);
            }

            .profile-picture-container {
                margin: 0 auto;
            }

            .profile-picture {
                width: 100px;
                height: 100px;
                border-width: 3px;
            }

            .edit-overlay {
                width: 40px;
                height: 40px;
                font-size: 1.2rem;
            }

            .profile-info h2 {
                font-size: 1.3rem;
                margin-bottom: 8px;
            }

            .profile-info p {
                font-size: 0.85rem;
                margin: 4px 0;
            }

            .profile-info .role-badge {
                font-size: 0.8rem;
                padding: 5px 10px;
                margin-top: 8px;
            }

            .form-group label {
                font-size: 0.85rem;
                margin-bottom: 6px;
                gap: 6px;
            }

            .form-group label i {
                font-size: 0.9rem;
            }

            .form-group input,
            .form-group select {
                padding: 10px 12px;
                font-size: 16px;
                border-radius: 6px;
                width: 100%;
            }

            .button-group {
                flex-direction: column;
                gap: 8px;
                margin-top: 20px;
            }

            .btn {
                padding: 11px 16px;
                font-size: 0.9rem;
                width: 100%;
                border-radius: 6px;
                gap: 8px;
            }

            .btn i {
                font-size: 0.9rem;
            }

            .alert {
                padding: 10px 12px;
                font-size: 0.85rem;
                border-radius: 6px;
                margin-bottom: 15px;
            }

            .alert i {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include("Components/Sidebar.php")?>

        <main>
            <!-- Dashboard Header -->
            <div class="dashboard-header">
                <div class="header-title">
                    <h1><i class="fas fa-user-edit"></i> Edit Profile</h1>
                </div>
            </div>

            <!-- Content Area -->
            <div class="content-area">
                <div class="form-container">
                    <form method="POST" enctype="multipart/form-data">
                        <!-- Profile Preview -->
                        <div class="profile-preview">
                            <div class="profile-picture-container">
                                <img id="profilePreviewImg" src="<?php echo htmlspecialchars($profile_pic_src); ?>" alt="Profile Picture" class="profile-picture">
                                <div class="edit-overlay" title="Click to change profile picture"><i class="fas fa-camera"></i></div>
                            </div>
                            <div class="profile-info">
                                <h2><?php echo htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '') ?: $user['username']); ?></h2>
                                <p><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($user['position'] ?? 'N/A'); ?></p>
                                <p><i class="fas fa-building"></i> <?php echo htmlspecialchars($user['department'] ?? 'N/A'); ?></p>
                                <p><i class="fas fa-id-card"></i> <?php echo htmlspecialchars($user['employee_id'] ?? 'N/A'); ?></p>
                                <span class="role-badge"><i class="fas fa-crown"></i> <?php echo ucfirst($user['role']); ?></span>
                            </div>
                            <div class="form-group full-width" style="display:none;">
                                <input type="file" id="profile_picture" name="profile_picture" accept="image/*">
                            </div>
                        </div>

                        <!-- Messages -->
                        <?php if($success_message): ?>
                            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success_message; ?></div>
                        <?php endif; ?>
                        <?php if($error_message): ?>
                            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?></div>
                        <?php endif; ?>

                        <!-- Form Fields -->
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="first_name"><i class="fas fa-user"></i> First Name</label>
                                <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="last_name"><i class="fas fa-user"></i> Last Name</label>
                                <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="email"><i class="fas fa-envelope"></i> Email</label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="phone"><i class="fas fa-phone"></i> Phone</label>
                                <input type="tel" id="phone" name="phone" placeholder="+63 (555) 000-0000" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="department"><i class="fas fa-building"></i> Department</label>
                                <select id="department" name="department">
                                    <option value="">Select Department</option>
                                    <option value="Fire Operations" <?php echo ($user['department'] ?? '') === 'Fire Operations' ? 'selected' : ''; ?>>Fire Operations</option>
                                    <option value="Emergency Medical Services" <?php echo ($user['department'] ?? '') === 'Emergency Medical Services' ? 'selected' : ''; ?>>Emergency Medical Services</option>
                                    <option value="Administration" <?php echo ($user['department'] ?? '') === 'Administration' ? 'selected' : ''; ?>>Administration</option>
                                    <option value="Logistics" <?php echo ($user['department'] ?? '') === 'Logistics' ? 'selected' : ''; ?>>Logistics</option>
                                    <option value="Training" <?php echo ($user['department'] ?? '') === 'Training' ? 'selected' : ''; ?>>Training</option>
                                    <option value="BFP Command Center" <?php echo ($user['department'] ?? '') === 'BFP Command Center' ? 'selected' : ''; ?>>BFP Command Center</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="position"><i class="fas fa-briefcase"></i> Position</label>
                                <input type="text" id="position" name="position" placeholder="e.g., Fire Chief, Captain" value="<?php echo htmlspecialchars($user['position'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="employee_id"><i class="fas fa-id-card"></i> Employee ID</label>
                                <input type="text" id="employee_id" name="employee_id" placeholder="e.g., BFP-2024-001" value="<?php echo htmlspecialchars($user['employee_id'] ?? ''); ?>">
                            </div>
                        </div>

                        <!-- Password Change Section -->
                        <div class="password-section">
                            <h3><i class="fas fa-lock"></i> Change Password</h3>
                            <div class="form-grid">
                                <div class="form-group full-width">
                                    <label for="current_password"><i class="fas fa-key"></i> Current Password</label>
                                    <input type="password" id="current_password" name="current_password" placeholder="Enter your current password">
                                </div>
                                <div class="form-group">
                                    <label for="new_password"><i class="fas fa-key"></i> New Password</label>
                                    <input type="password" id="new_password" name="new_password" placeholder="At least 6 characters">
                                </div>
                                <div class="form-group">
                                    <label for="confirm_password"><i class="fas fa-key"></i> Confirm Password</label>
                                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password">
                                </div>
                            </div>
                            <div style="display: flex; gap: 15px; margin-top: 20px;">
                                <button type="submit" name="action" value="change_password" class="btn btn-primary" style="flex: 1;"><i class="fas fa-save"></i> Change Password</button>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="button-group">
                            <button type="submit" name="action" value="save_profile" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                            <a href="<?php echo ($user['role'] === 'admin') ? 'Profile_Admin.php' : (($user['role'] === 'chief') ? 'Profile_Chief.php' : 'Profile_user.php'); ?>" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Click-to-upload and live preview for profile picture
        (function() {
            const fileInput = document.getElementById('profile_picture');
            const previewImg = document.getElementById('profilePreviewImg');
            const pictureContainer = document.querySelector('.profile-picture-container');
            const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB
            const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/gif'];

            if (!fileInput || !previewImg || !pictureContainer) return;

            pictureContainer.addEventListener('click', () => fileInput.click());

            fileInput.addEventListener('change', (e) => {
                const file = e.target.files[0];
                if (!file) return;

                if (!ALLOWED_TYPES.includes(file.type)) {
                    alert('Only JPG, PNG and GIF images are allowed.');
                    fileInput.value = '';
                    return;
                }

                if (file.size > MAX_FILE_SIZE) {
                    alert('File is too large. Maximum size is 5MB.');
                    fileInput.value = '';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(evt) {
                    previewImg.src = evt.target.result;
                };
                reader.readAsDataURL(file);
            });
        })();
    </script>
</body>
</html>
