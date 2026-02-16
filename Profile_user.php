<?php
session_start();
include("db_connect.php"); // Ensure $pdo is initialized

// ----------------------------
// 1. CHECK USER AUTHENTICATION
// ----------------------------
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';

// ----------------------------
// 2. FETCH USER DATA
// ----------------------------
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    // If no user found, log out
    if (!$user) {
        session_unset();
        session_destroy();
        header("Location: index.php?error=user_not_found");
        exit();
    }
} catch(Exception $e) {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit();
}

// ----------------------------
// 3. ASSIGN DISPLAY VARIABLES
// ----------------------------
$first_name  = htmlspecialchars($user['first_name'] ?? '');
$last_name   = htmlspecialchars($user['last_name'] ?? '');
$full_name   = trim($first_name . ' ' . $last_name) ?: $username;
$email       = htmlspecialchars($user['email'] ?? 'N/A');
$phone       = htmlspecialchars($user['phone'] ?? 'N/A');
$department  = htmlspecialchars($user['department'] ?? 'N/A');
$position    = htmlspecialchars($user['position'] ?? 'N/A');
$employee_id = htmlspecialchars($user['employee_id'] ?? 'N/A');
$role_display = ucfirst(htmlspecialchars($user['role'] ?? 'User'));

// ----------------------------
// 4. FETCH DRIVER DATA IF APPLICABLE
// ----------------------------
$driver = null;
$is_driver = false;

// Check if user has a driver_id OR if their position is explicitly 'driver'
if (!empty($user['driver_id'])) {
    try {
        $driver_stmt = $pdo->prepare("SELECT * FROM drivers WHERE driver_id = ?");
        $driver_stmt->execute([$user['driver_id']]);
        $driver = $driver_stmt->fetch();
        
        if ($driver) {
            $is_driver = true;
        }
    } catch(Exception $e) {
        $is_driver = false;
    }
}

// ----------------------------
// 5. PROFILE PICTURE LOGIC
// ----------------------------

// Prefer user's uploaded picture; otherwise prefer project default images (including ALBUM/defult.png),
// then fall back to the placeholder generator.
$default_pic_candidates = [
    'ALBUM/defult.png',
    'ALBUM/default_profile.png'
];
$default_pic_src = null;
foreach ($default_pic_candidates as $cand) {
    if (file_exists($cand)) { $default_pic_src = $cand; break; }
}

if (!empty($user['profile_pic']) && file_exists($user['profile_pic'])) {
    $profile_pic_src = htmlspecialchars($user['profile_pic']);
} elseif ($default_pic_src !== null) {
    $profile_pic_src = htmlspecialchars($default_pic_src);
} else {
    $profile_pic_src = 'generate_placeholder.php?username=' . urlencode($username) . '&size=100';
}

// Update session variables
$_SESSION['profile_pic'] = $profile_pic_src;
$_SESSION['role'] = $user['role'] ?? 'User';

$is_driver = !empty($user['driver_id']) || strtolower($user['position']) === 'driver';



?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard | BFP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet" />
    <style>
        :root {
            --bfp-red: #B22222;
            --sidebar-bg: #1e1e2d;
            --dark-bg: #0f0f18;
            --card-bg: #2a2a3e;
            --active-blue: #5d5dff;
            --text-primary: #e4e4e7;
            --text-secondary: #a2a2c2;
            --border-color: rgba(255, 255, 255, 0.05);
            --transition-speed: 0.3s;
        }

        * { box-sizing: border-box; }
        
        body {
            margin: 0;
            font-family: 'Poppins', Arial, sans-serif;
            background: var(--dark-bg);
            color: var(--text-primary);
            display: flex;
            flex-direction: column;
            height: 100vh;
        }

        /* Header */
        header {
            background: var(--sidebar-bg);
            color: white;
            padding: 15px 30px;
            display: none;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--border-color);
            z-index: 100;
        }

        /* Wrapper */
        .wrapper { 
            display: flex; 
            flex: 1; 
            overflow: hidden; 
            width: 100%;
            background: var(--dark-bg);
        }

        main {
            display: flex;
            flex-direction: column;
            flex: 1;
            overflow-y: auto;
            width: 100%;
            background: var(--dark-bg);
        }

        .content-area {
            padding: 30px 40px;
            flex: 1;
            width: 100%;
            display: flex;
            flex-direction: column;
        }

        .dashboard-header {
            display: flex;
            align-items: center;
            background: var(--card-bg);
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            margin-bottom: 30px;
            width: 100%;
            gap: 30px;
            border: 1px solid var(--border-color);
        }

        .profile-display-pic {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid var(--active-blue);
            margin-right: 30px;
        }

        .user-titles h1 {
            color: var(--active-blue);
            font-size: 2.8rem;
            margin: 0 0 10px 0;
            font-weight: 700;
        }

        .user-titles p {
            color: var(--text-secondary);
            font-size: 1.3rem;
            margin: 0;
            font-weight: 500;
        }

        .main-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 30px;
            align-items: start;
            width: 100%;
            margin: 0;
        }

        .user-data-card {
            background: var(--card-bg);
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            padding: 30px;
            transition: all 0.3s ease;
            border: 1px solid var(--border-color);
        }

        .user-data-card:hover {
            transform: translateY(-3px);
            background: rgba(93, 93, 255, 0.08);
            border-color: var(--active-blue);
            box-shadow: 0 8px 32px rgba(93, 93, 255, 0.15);
        }

        .user-data-card h2 {
            color: var(--text-primary);
            font-size: 1.5rem;
            border-bottom: 2px solid var(--active-blue);
            padding-bottom: 15px;
            margin: 0 0 20px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-data-card h2 i {
            color: var(--active-blue);
        }

        .data-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .data-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.95rem;
            gap: 15px;
        }

        .data-list li:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .data-label {
            font-weight: 600;
            color: var(--active-blue);
            display: flex;
            align-items: center;
        }

        .data-label i {
            margin-right: 10px;
            color: var(--active-blue);
            font-size: 1.1rem;
        }

        .data-value {
            color: var(--text-secondary);
            font-weight: 500;
            text-align: right;
        }

        .profile-link {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 25px;
            background: linear-gradient(135deg, var(--active-blue) 0%, #7b7bff 100%);
            color: #fff;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            text-align: center;
            width: fit-content;
        }

        .profile-link:hover {
            box-shadow: 0 4px 12px rgba(93, 93, 255, 0.4);
            transform: translateY(-2px);
        }

        .profile-link i {
            margin-right: 10px;
        }

        /* Modal Styles */
        .modal {
            display: none !important;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
        }

        .modal.show {
            display: flex !important;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease;
        }

        .modal-content {
            background: var(--card-bg);
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideUp 0.3s ease;
            position: relative;
            z-index: 10000;
            border: 1px solid var(--border-color);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 30px;
            background: linear-gradient(135deg, var(--active-blue) 0%, #7b7bff 100%);
            color: white;
            border-radius: 15px 15px 0 0;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 1.8rem;
            cursor: pointer;
            transition: all 0.3s;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-close:hover {
            transform: scale(1.2);
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
        }

        .modal-body {
            padding: 30px;
        }

        .modal-section {
            margin-bottom: 25px;
        }

        .modal-section:last-child {
            margin-bottom: 0;
        }

        .modal-section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--active-blue);
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--active-blue);
        }

        .modal-field {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .modal-field:last-child {
            border-bottom: none;
        }

        .modal-field-label {
            font-weight: 600;
            color: var(--active-blue);
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 0 0 auto;
        }

        .modal-field-label i {
            color: var(--active-blue);
        }

        .modal-field-value {
            color: var(--text-secondary);
            text-align: right;
            font-weight: 500;
        }

        .modal-footer {
            padding: 20px 30px;
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 0 0 15px 15px;
        }

        .btn-modal {
            padding: 12px 25px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 1rem;
        }

        .btn-modal-primary {
            background: linear-gradient(135deg, var(--active-blue) 0%, #7b7bff 100%);
            color: white;
        }

        .btn-modal-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(93, 93, 255, 0.4);
        }

        .btn-modal-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
        }

        .btn-modal-secondary:hover {
            background: rgba(93, 93, 255, 0.2);
            border-color: var(--active-blue);
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        @keyframes slideUp {
            from {
                transform: translateY(20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        @media (max-width: 768px) {
            .dashboard-header {
                flex-direction: column;
                text-align: center;
                padding: 30px 20px;
            }

            .profile-display-pic {
                width: 100px;
                height: 100px;
                margin-right: 0;
                margin-bottom: 20px;
            }

            .user-titles h1 {
                font-size: 2rem;
            }

            .user-titles p {
                font-size: 1rem;
            }

            .main-content {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .content-area {
                padding: 20px;
            }

            .data-value {
                text-align: left;
            }

            .data-list li {
                flex-direction: column;
                align-items: flex-start;
                padding: 10px 0;
            }
        }

        /* Footer */
        footer { display: none; }
    </style>
</head>
<body>

    <div class="wrapper">
        <?php include("Components/Sidebar.php")?>

        <main>
            <div class="content-area">
                <div class="dashboard-header">
                    <img src="<?php echo $profile_pic_src; ?>" alt="Profile" class="profile-display-pic">
                    <div class="user-titles">
                        <h1>Welcome Back, <?php echo $first_name ?: $username; ?>!</h1>
                        <p><?php echo $role_display; ?> Dashboard Overview</p>
                    </div>
                </div>
                
                <div class="main-content">
                    <div class="user-data-card">
                        <h2><i class="fas fa-user-circle"></i> Account Details</h2>
                        <ul class="data-list">
                            <li>
                                <span class="data-label">Full Name:</span>
                                <span class="data-value"><?php echo $full_name; ?></span>
                            </li>
                            <li>
                                <span class="data-label"><i class="fas fa-envelope"></i> Email:</span>
                                <span class="data-value"><?php echo $email; ?></span>
                            </li>
                            <li>
                                <span class="data-label"><i class="fas fa-phone"></i> Phone:</span>
                                <span class="data-value"><?php echo $phone; ?></span>
                            </li>
                            <li>
                                <span class="data-label"><i class="fas fa-briefcase"></i> Position:</span>
                                <span class="data-value"><?php echo $position; ?></span>
                            </li>
                            <li>
                                <span class="data-label"><i class="fas fa-building"></i> Department:</span>
                                <span class="data-value"><?php echo $department; ?></span>
                            </li>
                            <li>
                                <span class="data-label"><i class="fas fa-id-card"></i> Employee ID:</span>
                                <span class="data-value"><?php echo $employee_id; ?></span>
                            </li>
                        </ul>
                        <a href="Profile_Edit.php" class="profile-link"><i class="fas fa-edit"></i> Edit Profile</a>
                    </div>

                    <?php if ($is_driver && $driver): ?>
                    <div class="user-data-card">
                        <h2><i class="fas fa-car"></i> Driver Details</h2>
                        <ul class="data-list">
                            <li>
                                <span class="data-label"><i class="fas fa-user"></i> Full Name:</span>
                                <span class="data-value"><?php echo htmlspecialchars($driver['full_name']); ?></span>
                            </li>
                            <li>
                                <span class="data-label"><i class="fas fa-id-badge"></i> License Number:</span>
                                <span class="data-value"><?php echo htmlspecialchars($driver['license_no'] ?: 'N/A'); ?></span>
                            </li>
                            <li>
                                <span class="data-label"><i class="fas fa-check-circle"></i> Status:</span>
                                <span class="data-value">
                                    <span style="background: <?php echo ($driver['status'] === 'active') ? 'rgba(76, 175, 80, 0.2)' : 'rgba(220, 53, 69, 0.2)'; ?>; color: <?php echo ($driver['status'] === 'active') ? '#4caf50' : '#ff6b6b'; ?>; padding: 5px 15px; border-radius: 20px; font-size: 0.9rem; border: 1px solid <?php echo ($driver['status'] === 'active') ? 'rgba(76, 175, 80, 0.5)' : 'rgba(220, 53, 69, 0.5)'; ?>;">
                                        <?php echo ucfirst(htmlspecialchars($driver['status'])); ?>
                                    </span>
                                </span>
                            </li>
                            <li>
                                <span class="data-label"><i class="fas fa-calendar"></i> Driver Since:</span>
                                <span class="data-value"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></span>
                            </li>
                        </ul>
                        <a href="logout.php" class="profile-link" style="background: #FF4500;"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                    <?php else: ?>
                    <div class="user-data-card">
                        <h2><i class="fas fa-info-circle"></i> Account Info</h2>
                        <ul class="data-list">
                            <li>
                                <span class="data-label"><i class="fas fa-shield-alt"></i> Role:</span>
                                <span class="data-value">
                                    <span style="background: #B22222; color: white; padding: 5px 15px; border-radius: 20px; font-size: 0.9rem;">
                                        <?php echo $role_display; ?>
                                    </span>
                                </span>
                            </li>
                            <li>
                                <span class="data-label"><i class="fas fa-calendar"></i> Member Since:</span>
                                <span class="data-value"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></span>
                            </li>
                            <li>
                                <span class="data-label"><i class="fas fa-check-circle"></i> Account Status:</span>
                                <span class="data-value">
                                    <span style="color: #28a745; font-weight: bold;">Active</span>
                                </span>
                            </li>
                            <li>
                                <span class="data-label"><i class="fas fa-user-check"></i> Username:</span>
                                <span class="data-value"><?php echo htmlspecialchars($user['username']); ?></span>
                            </li>
                        </ul>
                            <ul>

                                <?php if ($is_driver): ?>
                                    <button type="button" class="profile-link" style="background: #2c3e50;" onclick="window.openDriverModal(event)">
                                        <i class="fas fa-id-card"></i> View Driver Profile
                                    </button>
                                <?php else: ?>
                                    <a href="Driver_SignUp.php" class="profile-link" style="background: #FF4500;">
                                        <i class="fas fa-car"></i> Apply For Driver
                                    </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Driver Profile Modal -->
    <?php if ($is_driver && $driver): ?>
    <div id="driverModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-car"></i> Driver Profile</h2>
                <button class="modal-close" onclick="closeDriverModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="modal-section">
                    <div class="modal-section-title">Personal Information</div>
                    <div class="modal-field">
                        <span class="modal-field-label"><i class="fas fa-user"></i> Full Name:</span>
                        <span class="modal-field-value"><?php echo htmlspecialchars($driver['full_name']); ?></span>
                    </div>
                    <div class="modal-field">
                        <span class="modal-field-label"><i class="fas fa-phone"></i> Contact Number:</span>
                        <span class="modal-field-value"><?php echo htmlspecialchars($driver['contact_no'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="modal-field">
                        <span class="modal-field-label"><i class="fas fa-map-marker-alt"></i> Address:</span>
                        <span class="modal-field-value"><?php echo htmlspecialchars($driver['address'] ?? 'N/A'); ?></span>
                    </div>
                </div>

                <div class="modal-section">
                    <div class="modal-section-title">License Details</div>
                    <div class="modal-field">
                        <span class="modal-field-label"><i class="fas fa-id-badge"></i> License Number:</span>
                        <span class="modal-field-value"><?php echo htmlspecialchars($driver['license_no'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="modal-field">
                        <span class="modal-field-label"><i class="fas fa-calendar"></i> License Expiry:</span>
                        <span class="modal-field-value"><?php echo $driver['license_expiry'] ? date('M d, Y', strtotime($driver['license_expiry'])) : 'N/A'; ?></span>
                    </div>
                    <div class="modal-field">
                        <span class="modal-field-label"><i class="fas fa-check-circle"></i> License Status:</span>
                        <span class="modal-field-value">
                            <span style="background: <?php echo ($driver['status'] === 'active') ? 'rgba(76, 175, 80, 0.2)' : 'rgba(220, 53, 69, 0.2)'; ?>; color: <?php echo ($driver['status'] === 'active') ? '#4caf50' : '#ff6b6b'; ?>; padding: 5px 15px; border-radius: 20px; font-size: 0.9rem; border: 1px solid <?php echo ($driver['status'] === 'active') ? 'rgba(76, 175, 80, 0.5)' : 'rgba(220, 53, 69, 0.5)'; ?>;">
                                <?php echo ucfirst(htmlspecialchars($driver['status'])); ?>
                            </span>
                        </span>
                    </div>
                </div>

                <div class="modal-section">
                    <div class="modal-section-title">Account Information</div>
                    <div class="modal-field">
                        <span class="modal-field-label"><i class="fas fa-calendar"></i> Driver Since:</span>
                        <span class="modal-field-value"><?php echo date('M d, Y', strtotime($driver['date_hired'] ?? $user['created_at'])); ?></span>
                    </div>
                    <div class="modal-field">
                        <span class="modal-field-label"><i class="fas fa-id-card"></i> Driver ID:</span>
                        <span class="modal-field-value"><?php echo htmlspecialchars($driver['driver_id']); ?></span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-modal btn-modal-secondary" onclick="closeDriverModal()">Close</button>
                <a href="Profile_Edit.php" class="btn-modal btn-modal-primary" style="text-decoration: none; display: inline-block;">
                    <i class="fas fa-edit"></i> Edit Profile
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        console.log('Scripts loading...');

        // Make functions globally available immediately
        window.openDriverModal = function(e) {
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }
            console.log('openDriverModal called');
            const modal = document.getElementById('driverModal');
            console.log('Modal element:', modal);
            if (modal) {
                console.log('Modal found, adding show class');
                modal.classList.add('show');
                modal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
                console.log('Modal displayed');
            } else {
                console.warn('driverModal element NOT found');
            }
        }

        window.closeDriverModal = function(e) {
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }
            const modal = document.getElementById('driverModal');
            if (modal) {
                modal.classList.remove('show');
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }

        // Dropdown functionality
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.dropdown').forEach(item => {
                item.addEventListener('click', function(e) {
                    if (e.target.closest('.submenu')) return;
                    this.classList.toggle('active');
                    document.querySelectorAll('.dropdown').forEach(other => {
                        if (other !== this) other.classList.remove('active');
                    });
                });
            });

            // Close modal when clicking outside
            const modal = document.getElementById('driverModal');
            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        window.closeDriverModal();
                    }
                });

                // Close on Escape key
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && modal.classList.contains('show')) {
                        window.closeDriverModal();
                    }
                });
            }
        });
    </script>
</body>
</html>