<?php
session_start();
include("db_connect.php");

// Check if user is logged in and is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Get filter values
$username_filter = $_GET['username'] ?? '';
$role_filter = $_GET['role'] ?? '';
$date_filter = $_GET['date'] ?? '';
$current_page = isset($_GET['page']) ? (int)max(1, intval($_GET['page'])) : 1;

// Handle errors and messages
$error = '';
$message = '';

// Pagination settings
$items_per_page = 10;

// Fetch user logs with error handling
$logs_result = [];
$total_logs = 0;
$total_pages = 0;

try {
    // Count total logs for pagination
    $count_query = "SELECT COUNT(*) as total FROM user_logs ul 
                    LEFT JOIN users u ON ul.user_id = u.id WHERE ul.action = 'Login'";
    $count_params = [];

    if ($username_filter) {
        $count_query .= " AND u.username LIKE ?";
        $count_params[] = "%$username_filter%";
    }

    if ($role_filter) {
        $count_query .= " AND ul.role = ?";
        $count_params[] = $role_filter;
    }

    if ($date_filter) {
        $count_query .= " AND DATE(ul.created_at) = ?";
        $count_params[] = $date_filter;
    }

    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($count_params);
    $total_logs = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $total_pages = ceil($total_logs / $items_per_page);
    
    // Ensure current page doesn't exceed total pages
    if ($current_page > $total_pages && $total_pages > 0) {
        $current_page = $total_pages;
    }

    // Fetch paginated logs
    $offset = ($current_page - 1) * $items_per_page;
    
    $query = "SELECT ul.id, ul.user_id, u.username, u.profile_pic, ul.role, ul.action, ul.description, ul.ip_address, ul.created_at, ul.browser 
              FROM user_logs ul 
              LEFT JOIN users u ON ul.user_id = u.id WHERE ul.action = 'Login'";
    $params = [];

    if ($username_filter) {
        $query .= " AND u.username LIKE ?";
        $params[] = "%$username_filter%";
    }

    if ($role_filter) {
        $query .= " AND ul.role = ?";
        $params[] = $role_filter;
    }

    if ($date_filter) {
        $query .= " AND DATE(ul.created_at) = ?";
        $params[] = $date_filter;
    }

    $query .= " ORDER BY ul.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $items_per_page;
    $params[] = $offset;

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $logs_result = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Error fetching logs: " . $e->getMessage();
    $logs_result = [];
    $total_logs = 0;
    $total_pages = 0;
}

// Get dashboard stats with error handling
$total = 0;
$unique = 0;
$today = 0;
$online = 0;
$recent = [];

try {
    $total_stmt = $pdo->query("SELECT COUNT(*) as total FROM user_logs");
    $total = $total_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) {
    $error = "Error fetching total count: " . $e->getMessage();
}

try {
    $unique_stmt = $pdo->query("SELECT COUNT(DISTINCT user_id) as unique_users FROM user_logs");
    $unique = $unique_stmt->fetch(PDO::FETCH_ASSOC)['unique_users'] ?? 0;
} catch (Exception $e) {
    $error = "Error fetching unique users: " . $e->getMessage();
}

try {
    $today_stmt = $pdo->query("SELECT COUNT(*) as today_logins FROM user_logs WHERE DATE(created_at) = CURDATE() AND action = 'Login'");
    $today = $today_stmt->fetch(PDO::FETCH_ASSOC)['today_logins'] ?? 0;
} catch (Exception $e) {
    $error = "Error fetching today's count: " . $e->getMessage();
}

try {
    // Count users currently online (most recent action is Login)
    $online_stmt = $pdo->query("SELECT COUNT(DISTINCT user_id) as online_users FROM (
                                    SELECT user_id, action 
                                    FROM user_logs 
                                    WHERE user_id IN (
                                        SELECT DISTINCT user_id 
                                        FROM user_logs ul 
                                        WHERE ul.created_at = (
                                            SELECT MAX(created_at) 
                                            FROM user_logs 
                                            WHERE user_id = ul.user_id
                                        ) AND action = 'Login'
                                    )
                                ) as latest_logins");
    $online = $online_stmt->fetch(PDO::FETCH_ASSOC)['online_users'] ?? 0;
} catch (Exception $e) {
    $error = "Error fetching online count: " . $e->getMessage();
}

try {
    $recent_stmt = $pdo->query("SELECT ul.id, u.username, u.profile_pic, ul.role, ul.action, ul.created_at 
                                FROM user_logs ul 
                                LEFT JOIN users u ON ul.user_id = u.id 
                                ORDER BY ul.created_at DESC LIMIT 5");
    $recent = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Error fetching recent logs: " . $e->getMessage();
    $recent = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Activity Logs - Fuel Capstone</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary-color: #5d5dff; --bg-dark: #1e1e2d; --bg-light-dark: #2a2a3e; --text-light: #e0e0e0; --text-muted: #a0a0a0; --border-color: #3a3a4e; --success-color: #10b981; --warning-color: #f59e0b; --danger-color: #ef4444; }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body { font-family: 'Poppins', sans-serif; background-color: var(--bg-dark); color: var(--text-light); line-height: 1.6; }

        .container { display: flex; height: 100vh; }

        main { flex: 1; overflow-y: auto; background-color: var(--bg-dark); }

        /* Dashboard Header */
        .dashboard-header { background: linear-gradient(135deg, var(--bg-light-dark) 0%, #3a3a4e 100%); padding: 30px 40px; margin: 0; border-bottom: 3px solid var(--primary-color); display: flex; align-items: center; gap: 15px; }

        .dashboard-header i { font-size: 2.5rem; color: var(--primary-color); }

        .dashboard-header h1 { font-size: 2rem; font-weight: 700; color: var(--text-light); }

        /* Main Content */
        .main-content { padding: 40px; max-width: 1400px; margin: 0 auto; }

        /* Dashboard Stats */
        .dashboard-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 40px; }

        .stat-card { background: var(--bg-light-dark); border: 2px solid var(--border-color); border-left: 4px solid var(--primary-color); padding: 25px; border-radius: 12px; display: flex; flex-direction: column; align-items: center; text-align: center; transition: all 0.3s ease; cursor: pointer; }

        .stat-card:hover { border-left-color: var(--primary-color); transform: translateY(-5px); background: linear-gradient(135deg, var(--bg-light-dark) 0%, #363654 100%); box-shadow: 0 10px 30px rgba(93, 93, 255, 0.1); }

        .stat-icon { font-size: 2.5rem; color: var(--primary-color); margin-bottom: 15px; }

        .stat-number { font-size: 2.2rem; font-weight: 700; color: var(--primary-color); margin-bottom: 8px; }

        .stat-label { font-size: 0.9rem; color: var(--text-muted); font-weight: 500; }

        /* Filter Section */
        .filter-section { background: var(--bg-light-dark); border: 2px solid var(--border-color); padding: 25px; border-radius: 12px; margin-bottom: 30px; }

        .filter-title { display: flex; align-items: center; gap: 10px; font-size: 1.1rem; font-weight: 600; color: var(--text-light); margin-bottom: 20px; }

        .filter-title i { color: var(--primary-color); }

        .filter-controls { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 20px; }

        .form-group { display: flex; flex-direction: column; gap: 8px; }

        .form-group label { font-size: 0.9rem; font-weight: 500; color: var(--text-light); display: flex; align-items: center; gap: 8px; }

        .form-group label i { color: var(--primary-color); }

        .form-group input,
        .form-group select { background: var(--bg-dark); border: 1px solid var(--border-color); color: var(--text-light); padding: 10px 12px; border-radius: 8px; font-family: 'Poppins', sans-serif; font-size: 0.9rem; transition: all 0.3s ease; }

        .form-group input:focus,
        .form-group select:focus { outline: none; border-color: var(--primary-color); background: var(--bg-dark); box-shadow: 0 0 0 3px rgba(93, 93, 255, 0.1); }

        .button-group { display: flex; gap: 10px; justify-content: flex-start; }

        .btn { padding: 10px 20px; border: none; border-radius: 8px; font-family: 'Poppins', sans-serif; font-size: 0.9rem; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: flex; align-items: center; gap: 8px; }

        .btn-filter { background: linear-gradient(135deg, var(--primary-color) 0%, #7373ff 100%); color: white; }

        .btn-filter:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(93, 93, 255, 0.4); }

        .btn-clear { background: var(--border-color); color: var(--text-light); }

        .btn-clear:hover { background: var(--primary-color); color: white; transform: translateY(-2px); }

        /* Table Section */
        .table-section { background: var(--bg-light-dark); border: 2px solid var(--border-color); padding: 25px; border-radius: 12px; }

        .table-title { color: var(--text-light); font-size: 1.2rem; font-weight: 600; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }

        .table-title i { color: var(--primary-color); }

        .table-wrapper { overflow-x: auto; }

        table { width: 100%; border-collapse: collapse; }

        table thead { background: linear-gradient(135deg, var(--primary-color) 0%, #7373ff 100%); color: white; }

        table th { padding: 15px; text-align: left; font-weight: 600; font-size: 0.95rem; border: none; }

        table td { padding: 15px; border-bottom: 1px solid var(--border-color); font-size: 0.9rem; }

        table tbody tr { transition: background 0.2s ease; }

        table tbody tr:hover { background: rgba(93, 93, 255, 0.05); }

        table tbody tr:last-child td { border-bottom: none; }

        .profile-pic-cell { display: flex; align-items: center; gap: 12px; }

        .profile-pic { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid var(--primary-color); background: var(--bg-dark); }

        .profile-pic.default { background: linear-gradient(135deg, var(--primary-color) 0%, #7373ff 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 1.2rem; font-weight: bold; }

        .role-badge { display: inline-block; padding: 6px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; text-align: center; min-width: 70px; }

        .role-admin { background: rgba(93, 93, 255, 0.2); color: var(--primary-color); }

        .role-user { background: rgba(16, 185, 129, 0.2); color: var(--success-color); }

        .role-chief { background: rgba(245, 158, 11, 0.2); color: var(--warning-color); }

        .role-driver { background: rgba(239, 68, 68, 0.2); color: var(--danger-color); }

        .action-badge { display: inline-block; padding: 6px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; background: rgba(16, 185, 129, 0.2); color: var(--success-color); }

        .no-data { text-align: center; padding: 40px 20px; color: var(--text-muted); font-size: 1rem; }

        .no-data i { font-size: 3rem; opacity: 0.5; margin-bottom: 10px; }

        /* Pagination */
        .pagination { display: flex; justify-content: center; list-style: none; padding: 0px; margin-top: 20px; }

        .pagination li a { display: block; padding: 8px 12px; text-decoration: none; border: 1px solid gray; color: var(--text-light); margin: 0 4px; border-radius: 5px; background: var(--bg-light-dark); transition: all 0.3s ease; cursor: pointer; }

        .pagination li a:hover { background: var(--primary-color); color: white; border-color: var(--primary-color); }

        /* Responsive Design */
        @media (max-width: 1200px) {
            main { padding: 30px; }
            .dashboard-stats { grid-template-columns: repeat(2, 1fr); }
            .filter-controls { grid-template-columns: 1fr; }
            .button-group { justify-content: stretch; }
            .btn { justify-content: center; }
            table { font-size: 0.85rem; }
            table th, table td { padding: 10px; }
        }

        @media (max-width: 768px) {
            .main-content { padding: 20px; }
            .dashboard-header { padding: 20px; flex-direction: column; text-align: center; }
            .dashboard-header h1 { font-size: 1.5rem; }
            .dashboard-stats { grid-template-columns: 1fr; }
            .filter-controls { grid-template-columns: 1fr; }
            .table-wrapper { overflow-x: auto; }
            .button-group { flex-direction: column; }
            .btn { width: 100%; }
            table { font-size: 0.8rem; }
            table th, table td { padding: 8px; }
            .profile-pic { width: 35px; height: 35px; }
        }

        @media (max-width: 480px) {
            .main-content { padding: 15px; }
            .dashboard-header { padding: 15px; gap: 10px; }
            .dashboard-header i { font-size: 1.8rem; }
            .dashboard-header h1 { font-size: 1.2rem; }
            .dashboard-stats { grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 20px; }
            .stat-card { padding: 10px; flex-direction: row; justify-content: center; align-items: center; gap: 6px; }
            .stat-icon { font-size: 1.2rem; margin-bottom: 0; }
            .stat-number { font-size: 1.2rem; margin-bottom: 0; }
            .stat-label { font-size: 0.55rem; margin-top: 2px; position: absolute; bottom: 2px; }
            
            .filter-section { padding: 15px; }
            .filter-title { font-size: 1rem; }
            .filter-controls { gap: 10px; }
            .form-group label { font-size: 0.85rem; }
            .form-group input,
            .form-group select { padding: 8px 10px; font-size: 0.85rem; }
            
            .btn { padding: 8px 15px; font-size: 0.85rem; }
            .button-group { gap: 8px; }
            
            .table-section { padding: 15px; }
            .table-title { font-size: 1.1rem; margin-bottom: 15px; }
            
            table { font-size: 0.7rem; }
            table th { padding: 8px 5px; font-size: 0.75rem; }
            table td { padding: 8px 5px; }
            
            .profile-pic-cell { gap: 8px; }
            .profile-pic { width: 30px; height: 30px; font-size: 0.9rem; }
            
            .role-badge, .action-badge { padding: 4px 8px; font-size: 0.7rem; }
            
            .pagination li a { padding: 6px 8px; margin: 0 2px; font-size: 0.75rem; }
            
            /* Hide browser column on very small screens */
            table th:last-child,
            table td:last-child { display: none; }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include("Components/Sidebar.php"); ?>

        <main>
            <!-- Dashboard Header -->
            <div class="dashboard-header">
                <i class="fas fa-history"></i>
                <h1>User Activity Logs</h1>
            </div>

            <!-- Main Content -->
            <div class="main-content">
                <!-- Dashboard Stats -->
                <div class="dashboard-stats">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-list"></i></div>
                        <div class="stat-number"><?php echo $total; ?></div>
                        <div class="stat-label">Total Records</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-users"></i></div>
                        <div class="stat-number"><?php echo $unique; ?></div>
                        <div class="stat-label">Unique Users</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                        <div class="stat-number"><?php echo $today; ?></div>
                        <div class="stat-label">Today's Logins</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-circle-dot" style="color: #10b981;"></i></div>
                        <div class="stat-number"><?php echo $online; ?></div>
                        <div class="stat-label">Accounts Online</div>
                    </div>
                </div>

                <!-- Filter Section -->
                <div class="filter-section">
                    <div class="filter-title">
                        <i class="fas fa-filter"></i>
                        Filter Logs
                    </div>

                    <form method="GET" action="User_Logs.php">
                        <div class="filter-controls">
                            <div class="form-group">
                                <label for="username"><i class="fas fa-user"></i> Username Search</label>
                                <input type="text" id="username" name="username" placeholder="Enter username..." value="<?php echo htmlspecialchars($username_filter); ?>">
                            </div>

                            <div class="form-group">
                                <label for="role"><i class="fas fa-shield-alt"></i> Role Filter</label>
                                <select id="role" name="role">
                                    <option value="">All Roles</option>
                                    <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    <option value="user" <?php echo $role_filter === 'user' ? 'selected' : ''; ?>>User</option>
                                    <option value="chief" <?php echo $role_filter === 'chief' ? 'selected' : ''; ?>>Chief</option>
                                    <option value="driver" <?php echo $role_filter === 'driver' ? 'selected' : ''; ?>>Driver</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="date"><i class="fas fa-calendar"></i> Date Filter</label>
                                <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($date_filter); ?>">
                            </div>
                        </div>

                        <div class="button-group">
                            <button type="submit" class="btn btn-filter">
                                <i class="fas fa-search"></i> Filter
                            </button>
                            <a href="User_Logs.php" class="btn btn-clear">
                                <i class="fas fa-redo"></i> Clear Filters
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Table Section -->
                <div class="table-section">
                    <div class="table-title">
                        <i class="fas fa-table"></i>
                        Login Records
                    </div>

                    <?php if (count($logs_result) > 0): ?>
                        <div class="table-wrapper">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Profile</th>
                                        <th>Username</th>
                                        <th>Role</th>
                                        <th>Action</th>
                                        <th>IP Address</th>
                                        <th>Date/Time</th>
                                        <th>Browser</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logs_result as $row): 
                                        $profile_pic = $row['profile_pic'] ?? '';
                                        $username = $row['username'] ?? 'System';
                                        $first_letter = strtoupper(substr($username, 0, 1));
                                    ?>
                                        <tr>
                                            <td>
                                                <div class="profile-pic-cell">
                                                    <?php if (!empty($profile_pic) && file_exists($profile_pic)): ?>
                                                        <img src="<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile" class="profile-pic">
                                                    <?php else: ?>
                                                        <div class="profile-pic default" title="<?php echo htmlspecialchars($username); ?>">
                                                            <?php echo $first_letter; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($username); ?></td>
                                            <td>
                                                <span class="role-badge role-<?php echo strtolower($row['role']); ?>">
                                                    <?php echo ucfirst($row['role']); ?>
                                                </span>
                                            </td>
                                            <td><span class="action-badge"><?php echo htmlspecialchars($row['action']); ?></span></td>
                                            <td><?php echo htmlspecialchars($row['ip_address'] ?? 'N/A'); ?></td>
                                            <td><?php echo date('M d, Y - h:i:s A', strtotime($row['created_at'])); ?></td>
                                            <td><?php echo htmlspecialchars($row['browser'] ?? 'N/A'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <ul class="pagination">
                            <li><a href="?page=1&username=<?php echo urlencode($username_filter); ?>&role=<?php echo urlencode($role_filter); ?>&date=<?php echo urlencode($date_filter); ?>" <?php echo $current_page == 1 ? 'style="opacity: 0.5; cursor: not-allowed;"' : ''; ?>>&laquo;</a></li>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li><a href="?page=<?php echo $i; ?>&username=<?php echo urlencode($username_filter); ?>&role=<?php echo urlencode($role_filter); ?>&date=<?php echo urlencode($date_filter); ?>" style="<?php echo $current_page == $i ? 'background: var(--primary-color); color: white; border-color: var(--primary-color);' : ''; ?>"><?php echo $i; ?></a></li>
                            <?php endfor; ?>
                            <li><a href="?page=<?php echo $total_pages; ?>&username=<?php echo urlencode($username_filter); ?>&role=<?php echo urlencode($role_filter); ?>&date=<?php echo urlencode($date_filter); ?>" <?php echo $current_page == $total_pages ? 'style="opacity: 0.5; cursor: not-allowed;"' : ''; ?>>&raquo;</a></li>
                        </ul>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-inbox"></i>
                            <p>No login records found.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Sidebar dropdown functionality
        document.querySelectorAll('.dropdown').forEach(item => {
            item.addEventListener('click', function(e) {
                if (e.target.closest('.submenu')) return;
                this.classList.toggle('active');
                document.querySelectorAll('.dropdown').forEach(other => {
                    if (other !== this) other.classList.remove('active');
                });
            });
        });
    </script>
</body>
</html>
