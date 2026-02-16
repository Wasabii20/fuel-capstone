<?php
 session_start();
 include("db_connect.php");
 
 // Check if user is logged in
 if (!isset($_SESSION['user_id'])) {
     header("Location: login.php");
     exit();
 }
 
 $current_user_id = $_SESSION['user_id'] ?? null;
 $current_role = $_SESSION['role'] ?? '';
 $current_driver_id = null;
 
 if ($current_user_id) {
     try {
         $u = $pdo->prepare("SELECT driver_id, username FROM users WHERE id = ? LIMIT 1");
         $u->execute([$current_user_id]);
         $urow = $u->fetch();
         $current_driver_id = $urow['driver_id'] ?? null;
         $current_username = $urow['username'] ?? 'User';
     } catch (Exception $e) {
         $current_driver_id = null;
         $current_username = 'User';
     }
 }
 
 // ===== STATISTICS FOR DASHBOARD =====
 
 // 1. Trip Tickets - Total, Pending, Completed
 try {
     $ticket_stats = $pdo->query("SELECT status, COUNT(*) as count FROM trip_tickets GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
     $total_tickets = array_sum($ticket_stats);
     $pending_tickets = $ticket_stats['Pending'] ?? 0;
     $completed_tickets = $ticket_stats['Submitted'] ?? 0;
     $in_progress_tickets = 0;
 } catch (Exception $e) {
     $total_tickets = $pending_tickets = $completed_tickets = $in_progress_tickets = 0;
 }
 
 // 2. Vehicle Status
 try {
     $vehicle_stats = $pdo->query("SELECT status, COUNT(*) as count FROM vehicles GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
     $total_vehicles = array_sum($vehicle_stats);
     $available_vehicles = $vehicle_stats['available'] ?? 0;
     $deployed_vehicles = $vehicle_stats['deployed'] ?? 0;
     $inactive_vehicles = $vehicle_stats['inactive'] ?? 0;
 } catch (Exception $e) {
     $total_vehicles = $available_vehicles = $deployed_vehicles = $inactive_vehicles = 0;
 }
 
 // 3. Drivers
 try {
     $driver_stmt = $pdo->query("SELECT COUNT(*) as total FROM drivers WHERE status = 'active'");
     $active_drivers = $driver_stmt->fetch()['total'] ?? 0;
     
     $all_drivers_stmt = $pdo->query("SELECT COUNT(*) as total FROM drivers");
     $total_drivers = $all_drivers_stmt->fetch()['total'] ?? 0;
 } catch (Exception $e) {
     $active_drivers = $total_drivers = 0;
 }
 
 // 4. Fuel Statistics
 try {
     $fuel_stmt = $pdo->query("SELECT SUM(current_fuel) as total_fuel, AVG(current_fuel) as avg_fuel FROM vehicles");
     $fuel_row = $fuel_stmt->fetch();
     $total_fuel = $fuel_row['total_fuel'] ?? 0;
     $avg_fuel = $fuel_row['avg_fuel'] ?? 0;
 } catch (Exception $e) {
     $total_fuel = $avg_fuel = 0;
 }
 
 // 5. Recent Trip Tickets (Last 10)
 try {
     $recent_tickets = $pdo->query("
         SELECT tt.id, tt.control_no, tt.ticket_date, tt.driver_id, d.full_name, 
                tt.vehicle_plate_no, tt.status, tt.created_at
         FROM trip_tickets tt
         LEFT JOIN drivers d ON tt.driver_id = d.driver_id
         ORDER BY tt.created_at DESC LIMIT 10
     ")->fetchAll();
 } catch (Exception $e) {
     $recent_tickets = [];
 }
 
 // 6. Active Deployments (Today)
 try {
     $active_deployments = $pdo->query("
         SELECT tt.control_no, d.full_name, tt.vehicle_plate_no, tt.places_to_visit, tt.purpose
         FROM trip_tickets tt
         LEFT JOIN drivers d ON tt.driver_id = d.driver_id
         WHERE tt.ticket_date = CURDATE() AND tt.status IN ('pending', 'in_progress')
         ORDER BY tt.created_at DESC
     ")->fetchAll();
 } catch (Exception $e) {
     $active_deployments = [];
 }
 
 // 7. Users in System
 try {
     $users_stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
     $user_stats = $users_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
     $total_users = array_sum($user_stats);
     $admin_users = $user_stats['admin'] ?? 0;
     $driver_users = $user_stats['driver'] ?? 0;
 } catch (Exception $e) {
     $total_users = $admin_users = $driver_users = 0;
 }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BFP - Trip Ticket System (Appendix A)</title>
    <style>
        :root {
            --bfp-red: #B22222;
            --dark-blue: #2c3e50;
            --submenu-bg: #1a252f;
            --paper-shadow: rgba(0, 0, 0, 0.5);
        }

        /* ===== BASE LAYOUT ===== */
        body {
            margin: 0;
            font-family: 'Segoe UI', Arial, sans-serif;
            background-color: #e9ecef;
            display: flex;
            flex-direction: column;
            height: 100vh;
        }

        /* ===== HEADER ===== */
        header {
            background: linear-gradient(90deg, var(--bfp-red) 60%, #FF4500 100%);
            color: white;
            padding: 15px 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            z-index: 100;
        }

        .head-left { display: flex; align-items: center; gap: 15px; }
        .bfp-logo { 
            height: 70px; 
            border-radius: 12px; 
            padding: 5px; 
            background: white; 
            box-shadow: 0 2px 5px rgba(0,0,0,0.3); 
        }
        .title-text b { display: block; font-size: 1.2rem; }

        /* ===== SIDEBAR NAVIGATION ===== */
        .wrapper { display: flex; flex: 1; overflow: hidden; }

        .sidebar {
            width: 230px;
            background: var(--dark-blue);
            color: white;
            padding-top: 10px;
            transition: width 0.3s ease;
            flex-shrink: 0;
        }

        .sidebar ul { list-style: none; padding: 0; margin: 0; }
        .menu-item { border-bottom: 1px solid #3e5871; cursor: pointer; }

        .menu-label {
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: white;
            text-decoration: none;
        }
        .menu-label li a{
            color:white;
            text-decoration: none;

        }
        .menu-label:hover { background: var(--bfp-red); }

        .submenu {
            max-height: 0;
            overflow: hidden;
            background: var(--submenu-bg);
            transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .submenu li {
            padding: 12px 35px;
            font-size: 0.85rem;
            border-bottom: 1px solid #2c3e50;
            color: #bdc3c7;
            transition: all 0.2s;
        }
        .submenu li a{
            color:white;
            text-decoration: none;
        }

        .submenu li:hover { background: #253341; color: white; padding-left: 45px; }

        .arrow {
            border: solid white; border-width: 0 2px 2px 0;
            display: inline-block; padding: 3px;
            transform: rotate(45deg); transition: transform 0.3s ease;
        }

        .active .submenu { max-height: 300px; }
        .active .arrow { transform: rotate(-135deg); }
        .active .menu-label { background: rgba(178, 34, 34, 0.3); }

        main { 
            display: flex; 
            flex: 1; 
            padding: 20px; 
            gap: 20px; 
            overflow: hidden; 
        }

        footer { display: none; }
    </style>
</head>
<body>

<header>
    <div class="head-left">
        <img src="Album/Official Logo.png" class="bfp-logo" alt="BFP Logo">
        <div class="title-text">
            <b>Bureau of Fire Protection</b>
            <span>Maasin City Fire Station</span>
        </div>
    </div>
    <h2>FUEL MONITORING SYSTEM</h2>
</header>

<div class="wrapper">
    <?php include("Components/sidebar.php");?>

    <main style="flex-direction: column; padding: 20px; overflow-y: auto;">
        <!-- WELCOME SECTION -->
        <div style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
            <h2 style="color: #B22222; margin: 0 0 10px 0;">Welcome, <?php echo htmlspecialchars($current_username); ?>!</h2>
            <p style="color: #666; margin: 0;">Role: <strong><?php echo ucfirst($current_role); ?></strong> | <?php echo date('l, F j, Y'); ?></p>
        </div>

        <!-- STATISTICS CARDS ROW 1 -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
            
            <!-- Trip Tickets Card -->
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <p style="margin: 0; font-size: 0.9rem; opacity: 0.9;">TOTAL TICKETS</p>
                        <h3 style="margin: 5px 0; font-size: 2rem;"><?php echo $total_tickets; ?></h3>
                    </div>
                    <div style="font-size: 2.5rem; opacity: 0.3;">📋</div>
                </div>
            </div>

            <!-- Pending Tickets Card -->
            <div style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <p style="margin: 0; font-size: 0.9rem; opacity: 0.9;">PENDING TICKETS</p>
                        <h3 style="margin: 5px 0; font-size: 2rem;"><?php echo $pending_tickets; ?></h3>
                    </div>
                    <div style="font-size: 2.5rem; opacity: 0.3;">⏳</div>
                </div>
            </div>

            <!-- In Progress Card -->
            <div style="background: linear-gradient(135deg, #ffa751 0%, #ffe259 100%); color: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <p style="margin: 0; font-size: 0.9rem; opacity: 0.9;">IN PROGRESS</p>
                        <h3 style="margin: 5px 0; font-size: 2rem;"><?php echo $in_progress_tickets; ?></h3>
                    </div>
                    <div style="font-size: 2.5rem; opacity: 0.3;">🚗</div>
                </div>
            </div>

            <!-- Completed Card -->
            <div style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <p style="margin: 0; font-size: 0.9rem; opacity: 0.9;">COMPLETED</p>
                        <h3 style="margin: 5px 0; font-size: 2rem;"><?php echo $completed_tickets; ?></h3>
                    </div>
                    <div style="font-size: 2.5rem; opacity: 0.3;">✅</div>
                </div>
            </div>

        </div>

        <!-- STATISTICS CARDS ROW 2 -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
            
            <!-- Vehicles Card -->
            <div style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <p style="margin: 0; font-size: 0.9rem; opacity: 0.9;">TOTAL VEHICLES</p>
                        <h3 style="margin: 5px 0; font-size: 2rem;"><?php echo $total_vehicles; ?></h3>
                    </div>
                    <div style="font-size: 2.5rem; opacity: 0.3;">🚙</div>
                </div>
            </div>

            <!-- Available Vehicles Card -->
            <div style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); color: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <p style="margin: 0; font-size: 0.9rem; opacity: 0.9;">AVAILABLE</p>
                        <h3 style="margin: 5px 0; font-size: 2rem;"><?php echo $available_vehicles; ?></h3>
                    </div>
                    <div style="font-size: 2.5rem; opacity: 0.3;">🟢</div>
                </div>
            </div>

            <!-- Deployed Vehicles Card -->
            <div style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <p style="margin: 0; font-size: 0.9rem; opacity: 0.9;">DEPLOYED</p>
                        <h3 style="margin: 5px 0; font-size: 2rem;"><?php echo $deployed_vehicles; ?></h3>
                    </div>
                    <div style="font-size: 2.5rem; opacity: 0.3;">🔵</div>
                </div>
            </div>

            <!-- Inactive Vehicles Card -->
            <div style="background: linear-gradient(135deg, #fa4659 0%, #ff6348 100%); color: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <p style="margin: 0; font-size: 0.9rem; opacity: 0.9;">INACTIVE</p>
                        <h3 style="margin: 5px 0; font-size: 2rem;"><?php echo $inactive_vehicles; ?></h3>
                    </div>
                    <div style="font-size: 2.5rem; opacity: 0.3;">🔴</div>
                </div>
            </div>

        </div>

        <!-- STATISTICS CARDS ROW 3 -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
            
            <!-- Total Drivers Card -->
            <div style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <p style="margin: 0; font-size: 0.9rem; opacity: 0.9;">TOTAL DRIVERS</p>
                        <h3 style="margin: 5px 0; font-size: 2rem;"><?php echo $total_drivers; ?></h3>
                    </div>
                    <div style="font-size: 2.5rem; opacity: 0.3;">👨‍✈️</div>
                </div>
            </div>

            <!-- Active Drivers Card -->
            <div style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <p style="margin: 0; font-size: 0.9rem; opacity: 0.9;">ACTIVE DRIVERS</p>
                        <h3 style="margin: 5px 0; font-size: 2rem;"><?php echo $active_drivers; ?></h3>
                    </div>
                    <div style="font-size: 2.5rem; opacity: 0.3;">✓</div>
                </div>
            </div>

            <!-- Total Users Card -->
            <div style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); color: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <p style="margin: 0; font-size: 0.9rem; opacity: 0.9;">TOTAL USERS</p>
                        <h3 style="margin: 5px 0; font-size: 2rem;"><?php echo $total_users; ?></h3>
                    </div>
                    <div style="font-size: 2.5rem; opacity: 0.3;">👥</div>
                </div>
            </div>

            <!-- Fuel Summary Card -->
            <div style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <p style="margin: 0; font-size: 0.9rem; opacity: 0.9;">TOTAL FUEL</p>
                        <h3 style="margin: 5px 0; font-size: 2rem;"><?php echo round($total_fuel, 2); ?> L</h3>
                    </div>
                    <div style="font-size: 2.5rem; opacity: 0.3;">⛽</div>
                </div>
            </div>

        </div>

        <!-- RECENT ACTIVITIES -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">

            <!-- Recent Trip Tickets -->
            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                <h3 style="color: #B22222; margin: 0 0 15px 0; border-bottom: 2px solid #B22222; padding-bottom: 10px;">📋 Recent Trip Tickets</h3>
                <div style="max-height: 300px; overflow-y: auto;">
                    <?php if (count($recent_tickets) > 0): ?>
                        <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                            <tbody>
                                <?php foreach ($recent_tickets as $ticket): ?>
                                    <tr style="border-bottom: 1px solid #eee;">
                                        <td style="padding: 8px; color: #666;">
                                            <strong><?php echo htmlspecialchars($ticket['control_no']); ?></strong><br>
                                            <small><?php echo $ticket['full_name'] ?? 'N/A'; ?> - <?php echo htmlspecialchars($ticket['vehicle_plate_no']); ?></small>
                                        </td>
                                        <td style="padding: 8px; text-align: right;">
                                            <span style="background: <?php echo $ticket['status'] == 'completed' ? '#38ef7d' : ($ticket['status'] == 'in_progress' ? '#ffe259' : '#f5576c'); ?>; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.8rem;">
                                                <?php echo ucfirst($ticket['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="color: #999; text-align: center;">No trip tickets found</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Today's Deployments -->
            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                <h3 style="color: #B22222; margin: 0 0 15px 0; border-bottom: 2px solid #B22222; padding-bottom: 10px;">🚗 Active Deployments (Today)</h3>
                <div style="max-height: 300px; overflow-y: auto;">
                    <?php if (count($active_deployments) > 0): ?>
                        <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                            <tbody>
                                <?php foreach ($active_deployments as $deploy): ?>
                                    <tr style="border-bottom: 1px solid #eee;">
                                        <td style="padding: 8px; color: #666;">
                                            <strong><?php echo htmlspecialchars($deploy['full_name'] ?? 'N/A'); ?></strong><br>
                                            <small><?php echo htmlspecialchars($deploy['vehicle_plate_no'] ?? 'N/A'); ?></small><br>
                                            <small style="color: #999;">To: <?php echo htmlspecialchars($deploy['places_to_visit'] ?? 'N/A'); ?></small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="color: #999; text-align: center;">No active deployments today</p>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- QUICK ACTIONS -->
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
            <h3 style="color: #B22222; margin: 0 0 15px 0;">⚡ Quick Actions</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px;">
                <a href="trip_ticket_dashboard.php" style="background: #667eea; color: white; padding: 12px; border-radius: 6px; text-align: center; text-decoration: none; transition: 0.3s;">📋 View All Tickets</a>
                <a href="vehicle.php" style="background: #fa709a; color: white; padding: 12px; border-radius: 6px; text-align: center; text-decoration: none; transition: 0.3s;">🚙 Manage Vehicles</a>
                <a href="Management.php" style="background: #11998e; color: white; padding: 12px; border-radius: 6px; text-align: center; text-decoration: none; transition: 0.3s;">👨‍✈️ Manage Drivers</a>
                <a href="reports.php" style="background: #ffa751; color: white; padding: 12px; border-radius: 6px; text-align: center; text-decoration: none; transition: 0.3s;">📊 View Reports</a>
                <a href="CreateForm.php" style="background: #38f9d7; color: white; padding: 12px; border-radius: 6px; text-align: center; text-decoration: none; transition: 0.3s;">➕ Create Ticket</a>
                <a href="Profile_user.php" style="background: #B22222; color: white; padding: 12px; border-radius: 6px; text-align: center; text-decoration: none; transition: 0.3s;">👤 My Profile</a>
            </div>
        </div>

    </main>
</div>

<script>
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