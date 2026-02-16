<style>
    :root { 
        --bfp-red: #B22222; 
        --sidebar-bg: #1e1e2d; 
        --active-gradient: linear-gradient(90deg, rgba(80, 80, 255, 0.15) 0%, rgba(80, 80, 255, 0) 100%); 
        --sidebar-width: 280px; 
        --sidebar-collapsed-width: 80px; 
        --transition-speed: 0.4s; 
    }
    
    .sidebar { 
        width: var(--sidebar-width); 
        background: var(--sidebar-bg); 
        display: flex; 
        flex-direction: column; 
        transition: width var(--transition-speed) cubic-bezier(0.25, 1, 0.5, 1); 
        border-right: 1px solid rgba(255,255,255,0.05); 
        overflow: hidden; 
        white-space: nowrap; 
        position: relative; 
        z-index: 1010;
    }
    
    .sidebar.collapsed { 
        width: var(--sidebar-collapsed-width); 
    }
    
    .sidebar-header { 
        padding: 15px 20px; 
        display: flex; 
        align-items: center; 
        height: 70px; 
        box-sizing: border-box; 
        transition: padding 0.3s; 
    }
    
    .sidebar.collapsed .sidebar-header { 
        padding: 15px 0; 
        justify-content: center; 
    }
    
    .brand-logo { 
        width: 40px; 
        height: 40px; 
        flex-shrink: 0; 
    }
    
    .brand-text { 
        font-size: 0.85rem; 
        font-weight: bold; 
        color: #a2a2c2; 
        margin-left: 15px; 
        transition: opacity 0.3s, transform 0.3s; 
    }
    
    .toggle-btn { 
        background: rgba(255,255,255,0.05); 
        border: none; 
        color: #565674; 
        cursor: pointer; 
        width: 30px; 
        height: 30px; 
        border-radius: 5px; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        transition: all 0.3s; 
        margin-left: auto; 
    }
    
    .sidebar.collapsed .brand-text { 
        opacity: 0; 
        width: 0; 
        margin: 0; 
        pointer-events: none; 
    }
    
    .sidebar.collapsed .toggle-btn { 
        margin-left: 0; 
        transform: rotate(180deg); 
    }
    
    .user-profile { 
        margin: 10px 15px 20px; 
        padding: 12px; 
        background: rgba(93, 93, 255, 0.08);
        border: 1px solid rgba(93, 93, 255, 0.2);
        border-radius: 12px; 
        display: flex; 
        align-items: center; 
        justify-content: space-between;
        transition: all 0.3s; 
        min-height: 60px; 
        box-sizing: border-box; 
        position: relative;
    }
    
    .user-profile:hover {
        background: rgba(93, 93, 255, 0.12);
        border-color: rgba(93, 93, 255, 0.3);
    }
    
    .user-profile-content {
        display: flex;
        align-items: center;
        flex: 1;
    }
    
    .notif-bell {
        background: rgba(255, 255, 255, 0.05);
        border: none;
        color: #a2a2c2;
        cursor: pointer;
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        transition: all 0.3s;
        flex-shrink: 0;
        position: absolute;
        top: -8px;
        right: -8px;
        background: rgba(178, 34, 34, 0.9);
        border: 2px solid var(--sidebar-bg);
    }
    
    .sidebar.collapsed .notif-bell {
        top: -46px;
        right: -12px;
        width: 28px;
        height: 28px;
        font-size: 0.95rem;
    }
    
    .notif-bell:hover {
        background: rgba(93, 93, 255, 0.2);
        color: #5d5dff;
        transform: scale(1.05);
    }
    
    .notif-badge {
        position: absolute;
        top: -5px;
        right: -5px;
        width: 18px;
        height: 18px;
        background: #ff4757;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.7rem;
        font-weight: bold;
        color: white;
        z-index: 1001;
    }
    
    .sidebar.collapsed .user-profile { 
        margin: 10px 10px; 
        padding: 12px 8px; 
        justify-content: center;
        align-items: center;
        min-height: auto;
        flex-direction: column;
    }
    
    .avatar { 
        width: 38px; 
        height: 38px; 
        background: #5d5dff; 
        border-radius: 10px; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        flex-shrink: 0; 
        font-weight: bold; 
        font-size: 0.9rem; 
        overflow: hidden; 
        object-fit: cover; 
    }
    
    .avatar img { 
        width: 100%; 
        height: 100%; 
        object-fit: cover; 
    }
    
    .avatar-initials { 
        width: 100%; 
        height: 100%; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        color: white; 
    }
    
    .profile-info { 
        margin-left: 12px; 
        transition: opacity 0.2s; 
    }
    
    .sidebar.collapsed .profile-info { 
        opacity: 0; 
        width: 0; 
        margin: 0; 
        display: none; 
    }

    .sidebar.collapsed .user-profile-content {
        flex: 0;
        width: 100%;
        justify-content: center;
        margin: 0;
    }
    
    .sidebar ul { 
        list-style: none; 
        padding: 0 15px; 
        margin: 0; 
        flex-grow: 1; 
    }
    
    .sidebar.collapsed ul { 
        padding: 0 10px; 
    }
    
    .menu-item { 
        margin-bottom: 5px; 
    }
    
    .menu-label { 
        display: flex; 
        align-items: center; 
        padding: 12px 15px; 
        cursor: pointer; 
        border-radius: 12px; 
        transition: all 0.3s; 
        color: #9899ac; 
        text-decoration: none; 
        position: relative; 
    }
    
    .sidebar.collapsed .menu-label { 
        justify-content: center; 
        padding: 12px 0; 
    }
    
    .active > .menu-label { 
        background: var(--active-gradient); 
        border-left: 3px solid #5d5dff; 
        color: white; 
    }
    
    .nav-icon { 
        font-size: 1.2rem; 
        width: 25px; 
        text-align: center; 
        flex-shrink: 0; 
    }
    
    .nav-text { 
        margin-left: 15px; 
        transition: opacity 0.3s; 
    }
    
    .sidebar.collapsed .nav-text { 
        display: none; 
    }
    
    .arrow { 
        border: solid #565674; 
        border-width: 0 2px 2px 0; 
        display: inline-block; 
        padding: 3px; 
        transform: rotate(45deg); 
        transition: 0.3s; 
        margin-left: auto; 
    }
    
    .active .arrow { 
        transform: rotate(-135deg); 
        border-color: white; 
    }
    
    .sidebar.collapsed .arrow { 
        display: none; 
    }
    
    .submenu { 
        max-height: 0; 
        overflow: hidden; 
        background: rgba(0,0,0,0.15); 
        border-radius: 8px; 
        margin-left: 35px; 
        transition: max-height 0.3s ease-out; 
    }
    
    .active .submenu { 
        max-height: 600px; 
        margin-top: 5px; 
        padding: 5px 0; 
    }
    
    .sidebar.collapsed .submenu { 
        display: none; 
    }
    
    .submenu-header { 
        background: rgba(255,255,255,0.03); 
        font-size: 0.65rem; 
        font-weight: bold; 
        color: #565674; 
        padding: 8px 20px; 
        letter-spacing: 1px; 
        text-transform: uppercase; 
    }
    
    .submenu li a { 
        padding: 10px 20px; 
        display: block; 
        color: #7e8299; 
        text-decoration: none; 
        font-size: 0.85rem; 
        transition: all 0.3s ease; 
        border-radius: 8px; 
        margin: 0 8px; 
    }
    
    .submenu li a:hover { 
        color: #5d5dff; 
        background: rgba(93, 93, 255, 0.1); 
        padding-left: 25px; 
    }
    
    .sidebar-footer { 
        margin-top: auto; 
        padding: 20px 15px; 
        border-top: 1px solid rgba(255,255,255,0.05); 
    }
    
    .sidebar.collapsed .sidebar-footer { 
        padding: 20px 10px; 
    }
    
    .logout-btn { 
        display: flex; 
        align-items: center; 
        padding: 12px; 
        background: transparent; 
        border: 1px solid rgba(255, 71, 87, 0.2); 
        border-radius: 12px; 
        color: #ff4757; 
        cursor: pointer; 
        transition: all 0.3s; 
        text-decoration: none; 
        width: 100%; 
        box-sizing: border-box; 
    }
    
    .sidebar.collapsed .logout-btn { 
        justify-content: center; 
        border: none; 
    }
    
    .logout-btn:hover { 
        background: rgba(255, 71, 87, 0.1); 
        border-color: #ff4757; 
    }
    
    .notif-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        animation: fadeIn 0.3s ease;
    }
    
    .notif-modal.show {
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .notif-modal-content {
        background: #1e1e2d;
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 16px;
        width: 90%;
        max-width: 500px;
        max-height: 80vh;
        display: flex;
        flex-direction: column;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        animation: slideUp 0.3s ease;
    }
    
    .notif-modal-header {
        padding: 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    
    .notif-modal-header h2 {
        margin: 0;
        color: white;
        font-size: 1.2rem;
    }
    
    .notif-modal-close {
        background: none;
        border: none;
        color: #a2a2c2;
        font-size: 1.5rem;
        cursor: pointer;
        transition: all 0.3s;
    }
    
    .notif-modal-close:hover {
        color: #ff4757;
    }
    
    .notif-modal-body {
        padding: 20px;
        flex: 1;
        overflow-y: auto;
        color: #7e8299;
        text-align: center;
    }

    .notif-item {
        background: rgba(93, 93, 255, 0.08);
        border: 1px solid rgba(93, 93, 255, 0.2);
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 12px;
        text-align: left;
        transition: all 0.3s;
    }

    .notif-item:hover {
        background: rgba(93, 93, 255, 0.12);
        border-color: rgba(93, 93, 255, 0.3);
    }

    .notif-title {
        font-weight: 600;
        color: #5d5dff;
        margin-bottom: 8px;
        font-size: 0.95rem;
    }

    .notif-message {
        color: #a2a2c2;
        font-size: 0.85rem;
        margin-bottom: 8px;
        line-height: 1.4;
        white-space: pre-wrap;
    }

    .notif-time {
        color: #7e8299;
        font-size: 0.75rem;
        margin-bottom: 10px;
    }

    .notif-actions {
        display: flex;
        gap: 8px;
        margin-top: 10px;
    }

    .btn-confirm-trip {
        flex: 1;
        background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
        color: white;
        border: none;
        padding: 8px 12px;
        border-radius: 6px;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
    }

    .btn-confirm-trip:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(34, 197, 94, 0.3);
    }

    .trips-badge {
        position: absolute;
        top: 5px;
        right: 5px;
        width: 20px;
        height: 20px;
        background: linear-gradient(135deg, #ff4757 0%, #ff3838 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        font-weight: bold;
        color: white;
        border: 2px solid var(--sidebar-bg);
        min-width: 20px;
    }

    .menu-label {
        position: relative;
    }

    .trips-badge-submenu {
        width: 18px;
        height: 18px;
        background: linear-gradient(135deg, #ff4757 0%, #ff3838 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.65rem;
        font-weight: bold;
        color: white;
        border: 1px solid var(--sidebar-bg);
        min-width: 18px;
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

    /* ===== MOBILE FRIENDLY STYLES ===== */
    .mobile-menu-toggle {
        display: none;
        background: none;
        border: none;
        color: #a2a2c2;
        font-size: 1.5rem;
        cursor: pointer;
        padding: 8px;
        z-index: 999;
    }

    .sidebar-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 500;
    }

    .sidebar-overlay.show {
        display: block;
    }

    /* Mobile Media Query */
    @media (max-width: 768px) {
        :root {
            --sidebar-width: 260px;
            --sidebar-collapsed-width: 70px;
        }

        * {
            -webkit-tap-highlight-color: transparent;
        }

        html, body {
            overflow-x: hidden;
        }

        .mobile-menu-toggle {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .wrapper {
            flex-direction: row;
            position: relative;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            z-index: 1000;
            border-right: 1px solid rgba(255,255,255,0.05);
            transform: translateX(-100%);
            transition: transform var(--transition-speed) cubic-bezier(0.25, 1, 0.5, 1);
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.3);
        }

        .sidebar.mobile-open {
            transform: translateX(0);
        }

        .sidebar.collapsed {
            width: var(--sidebar-width);
            transform: translateX(-100%);
        }

        .sidebar.collapsed.mobile-open {
            transform: translateX(0);
        }

        main {
            flex: 1;
            flex-direction: column;
            padding: 15px;
            gap: 15px;
        }

        .brand-text {
            font-size: 0.75rem;
        }

        .user-profile {
            margin: 8px 12px 15px;
            padding: 10px;
            min-height: 55px;
            flex-wrap: wrap;
        }

        .profile-info {
            margin-left: 10px;
        }

        .profile-info span {
            font-size: 0.85rem !important;
        }

        .profile-info small {
            font-size: 0.75rem !important;
        }

        .notif-bell {
            width: 30px;
            height: 30px;
            font-size: 1rem;
        }

        .sidebar ul {
            padding: 0 12px;
        }

        .menu-label {
            padding: 10px 12px;
            font-size: 0.9rem;
        }

        .nav-text {
            margin-left: 12px;
        }

        .nav-icon {
            font-size: 1.1rem;
        }

        .submenu {
            margin-left: 30px;
        }

        .submenu li a {
            padding: 8px 15px;
            font-size: 0.8rem;
            margin: 0 4px;
        }

        .submenu li a:hover {
            padding-left: 20px;
        }

        .sidebar-footer {
            padding: 15px 12px;
        }

        .logout-btn {
            padding: 10px;
            font-size: 0.9rem;
        }

        .toggle-btn {
            width: 28px;
            height: 28px;
            font-size: 0.9rem;
        }

        #trip-map {
            min-height: 300px;
        }

        .content {
            flex-direction: column;
            gap: 15px;
        }

        .map-section {
            flex: 0 0 auto;
            height: 350px;
            padding: 12px;
        }

        .panels-container {
            flex: 0 0 auto;
            gap: 12px;
        }

        .panel {
            padding: 12px;
            gap: 8px;
        }

        .panel-title {
            font-size: 1rem;
            padding: 8px 10px;
        }

        .panel-list {
            gap: 8px;
        }

        .vehicle-card {
            padding: 10px;
            gap: 8px;
            grid-template-columns: auto 1fr auto;
        }

        .vehicle-icon {
            font-size: 1.5rem;
            min-width: 35px;
        }

        .vehicle-no {
            font-size: 0.9rem;
        }

        .vehicle-type,
        .vehicle-trip-info {
            font-size: 0.75rem;
        }

        .fuel-bar-container {
            height: 18px;
            min-width: 60px;
        }

        .fuel-bar {
            font-size: 0.65rem;
        }

        .location-btn {
            padding: 6px 8px;
            font-size: 0.8rem;
            min-width: auto;
        }

        .current-use-display {
            padding: 8px 10px;
            margin-top: 2px;
        }

        .empty-panel {
            padding: 15px;
            font-size: 0.85rem;
        }

        .modal-content {
            width: 95%;
            max-width: 90vw;
            margin: 20% auto;
            padding: 20px;
        }

        .vehicle-modal-info {
            grid-template-columns: 1fr;
            gap: 10px;
        }

        .fuel-gauge {
            width: 150px;
            height: 150px;
            margin: 15px auto;
        }

        .notif-modal-content {
            width: 95%;
            max-width: 90vw;
            max-height: 70vh;
        }

        .notif-item {
            padding: 12px;
            margin-bottom: 10px;
        }
    }

    @media (max-width: 480px) {
        :root {
            --sidebar-width: 240px;
        }

        .sidebar {
            width: var(--sidebar-width);
        }

        .sidebar-header {
            height: 60px;
            padding: 12px 15px;
        }

        .brand-logo {
            width: 35px;
            height: 35px;
        }

        .brand-text {
            font-size: 0.7rem;
            margin-left: 10px;
        }

        .toggle-btn {
            width: 26px;
            height: 26px;
        }

        .user-profile {
            margin: 8px 10px 12px;
            padding: 8px;
        }

        .avatar {
            width: 32px;
            height: 32px;
            font-size: 0.8rem;
        }

        .notif-bell {
            width: 28px;
            height: 28px;
            font-size: 0.95rem;
        }

        .sidebar ul {
            padding: 0 10px;
        }

        .menu-label {
            padding: 10px 10px;
            font-size: 0.85rem;
        }

        .nav-icon {
            font-size: 1rem;
            width: 22px;
        }

        .submenu {
            margin-left: 28px;
        }

        .submenu li a {
            padding: 8px 12px;
            font-size: 0.75rem;
            margin: 0 2px;
        }

        .submenu-header {
            font-size: 0.6rem;
            padding: 6px 15px;
        }

        main {
            padding: 12px;
            gap: 12px;
        }

        .map-section {
            height: 280px;
            padding: 10px;
        }

        #trip-map {
            min-height: 280px;
        }

        .vehicle-card {
            padding: 8px;
            gap: 6px;
            grid-template-columns: auto 1fr;
        }

        .vehicle-icon {
            font-size: 1.3rem;
            min-width: 30px;
        }

        .fuel-bar-container {
            display: none;
        }

        .location-btn {
            padding: 5px 8px;
            font-size: 0.75rem;
        }

        .vehicle-no {
            font-size: 0.85rem;
        }

        .vehicle-type {
            font-size: 0.7rem;
        }

        .logout-btn {
            padding: 8px;
            font-size: 0.85rem;
        }

        .notif-modal-header h2 {
            font-size: 1rem;
        }

        .notif-title {
            font-size: 0.9rem;
        }

        .notif-message {
            font-size: 0.8rem;
        }

        .fuel-gauge {
            width: 130px;
            height: 130px;
            margin: 12px auto;
        }

        .fuel-gauge .value {
            font-size: 1rem;
        }

        .fuel-gauge .label {
            font-size: 0.8rem;
        }
    }
</style>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="Album/Official Logo.png" class="brand-logo" alt="BFP">
        <div class="brand-text">BFP FUEL<br>SYSTEM</div>
        <button class="toggle-btn" id="toggleSidebar">◀</button>
    </div>

    <div class="user-profile">
        <?php
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Get user initials for avatar
        $userFirstName = isset($_SESSION['first_name']) ? $_SESSION['first_name'] : 'U';
        $userLastName = isset($_SESSION['last_name']) ? $_SESSION['last_name'] : 'N';
        $userInitials = strtoupper(substr($userFirstName, 0, 1) . substr($userLastName, 0, 1));
        
        // Get user display info
        $userName = isset($_SESSION['first_name']) && isset($_SESSION['last_name']) 
            ? $_SESSION['first_name'] . ' ' . $_SESSION['last_name']
            : (isset($_SESSION['username']) ? $_SESSION['username'] : 'Guest');
        $userPosition = isset($_SESSION['position']) ? $_SESSION['position'] : 'Officer';
        $userRole = isset($_SESSION['role']) ? ucfirst($_SESSION['role']) : 'User';
        ?>
    <div class="user-profile-content">
    <div class="avatar" title="<?php echo htmlspecialchars($userName); ?>">
        <?php
            $session_pic = isset($_SESSION['profile_pic']) ? $_SESSION['profile_pic'] : '';
            $default_pic = 'ALBUM/defult.png'; // Double check the spelling of "defult" vs "default"
            
            // Check if session pic exists and is a valid file
            if (!empty($session_pic) && file_exists($session_pic)) {
                $display_pic = $session_pic;
            } else {
                $display_pic = $default_pic;
            }
        ?>
        <img src="<?php echo htmlspecialchars($display_pic); ?>" alt="<?php echo htmlspecialchars($userName); ?>">
    </div>
    
    <div class="profile-info">
        <span style="display:block; font-size:0.9rem; font-weight:bold; color:white;">
            <?php echo htmlspecialchars($userName); ?>
        </span>
        <small style="color:#9899ac;">
            <?php echo htmlspecialchars($userRole); ?>
        </small>
    </div>
</div>
        <button class="notif-bell" id="notifBell" title="Notifications">
            🔔
        </button>
    </div>
    
    <ul>
        <?php
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Define current_role if not already set
        if (!isset($current_role)) {
            $current_role = $_SESSION['role'] ?? '';
        }

        // Get current page
        $current_page = basename($_SERVER['PHP_SELF']);

        $dashboardLink = 'login.php';
        $dashboardPage = 'login.php';
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
            $dashboardLink = 'Profile_Admin.php';
            $dashboardPage = 'Profile_Admin.php';
        } elseif (isset($_SESSION['role'])) {
            $dashboardLink = 'Profile_user.php';
            $dashboardPage = 'Profile_user.php';
        }

        // Determine active states
        $isDashboard = ($current_page === $dashboardPage || $current_page === 'login.php');
        $isTripsActive = in_array($current_page, ['CreateForm.php', 'active_trips.php', 'Maps.php', 'Trip_ticket_dashboard.php', 'pending_reports.php', 'fuel_summary.php', 'vehicle_status.php', 'User_Logs.php']);
        $isFuelActive = in_array($current_page, ['fuel_monthly.php', 'fuel_stocks.php']);
        $isVehicles = ($current_page === 'vehicle.php');
        $isAnalytics = in_array($current_page, ['Analytics_data.php', 'Expenses.php', 'fuel_stocks.php', 'fuel_summary.php', 'vehicle_repair_request_list.php']);
        $isSettings = ($current_page === 'settings.php');
        ?>
        <li class="menu-item <?php echo $isDashboard ? 'active' : ''; ?>">
            <a href="<?php echo htmlspecialchars($dashboardLink); ?>" class="menu-label">
                <span class="nav-icon">🏠</span>
                <span class="nav-text">Dashboard</span>
            </a>
        </li>
        
        <li class="menu-item dropdown <?php echo $isTripsActive ? 'active' : ''; ?>">
            <div class="menu-label">
                <span class="nav-icon">📑</span>
                <span class="nav-text">Trip Tickets</span>
                <span class="trips-badge" id="tripsBadge" style="display: none;">0</span>
                <i class="arrow"></i>
            </div>
            <ul class="submenu">
                <li class="submenu-header">Action</li>
                <?php if ($current_role === 'admin'): ?>
                    <li><a href="CreateForm.php">➕ Issue New Ticket</a></li>
                <?php endif; ?>
                <li style="position: relative;">
                    <a href="active_trips.php">🚒 Active Trips</a>
                    <span class="trips-badge-submenu" id="tripsBadgeSubmenu" style="display: none; position: absolute; right: 15px; top: 50%; transform: translateY(-50%);">0</span>
                </li>

                <li class="submenu-header" style="margin-top:10px;">Records</li>
                <li><a href="Trip_ticket_dashboard.php">📑 View Logs</a></li>
                <li><a href="pending_reports.php">⏳ Pending Submissions</a></li>
                <li><a href="request_trip_ticket.php">⏳ Request Trip ticket</a></li>    
            </ul>
        </li>
        <li class="menu-item dropdown <?php echo $isVehicles ? 'active' : ''; ?>">
            <div class="menu-label">
                <span class="nav-icon">🚙</span>
                <span class="nav-text">Vehicles</span>
                <i class="arrow"></i>
            </div>
            <ul class="submenu">
                <li><a href="vehicle.php">📋 Vehicle Management</a></li>
            </ul>
        </li>

        <li class="menu-item dropdown <?php echo $isAnalytics ? 'active' : ''; ?>">
            <div class="menu-label">
                <span class="nav-icon">📊</span>
                <span class="nav-text">Analytics</span>
                <i class="arrow"></i>
            </div>
            <ul class="submenu">
                <?php if ($current_role === 'admin'): ?>
                <li class="submenu-header">Market Data</li>
                <li><a href="Analytics_data.php">📊 Price Management</a></li>
                <?php endif; ?>
                <li class="submenu-header" style="margin-top:10px;">Reports</li>
                <li><a href="Expenses1.php">📈 Expenses Summary</a></li>
                <li><a href="fuel_summary.php">⛽ Gasoline Consumption</a></li>
            </ul>
        </li>
        <li class="menu-item dropdown <?php echo $isVehicles ? 'active' : ''; ?>">
            <div class="menu-label">
                <span class="nav-icon">⛽</span>
                <span class="nav-text">Fuel Storage</span>
                <i class="arrow"></i>
            </div>
            <ul class="submenu">
                <li><a href="fuel_stocks.php">📦 Gasoline Stocks</a></li>
            </ul>
        </li>
    </ul>

    <div class="sidebar-footer">
        <?php if (isset($_SESSION['username'])): ?>
            <form method="POST" action="logout.php" style="margin: 0; width: 100%;">
                <button type="submit" class="logout-btn">
                    <span class="nav-icon">🚪</span><span class="nav-text">Logout</span>
                </button>
            </form>
        <?php else: ?>
            <a href="login.php" class="logout-btn">
                <span class="nav-icon">👤</span><span class="nav-text">Login</span>
            </a>
        <?php endif; ?>
    </div>
</aside>

<!-- Notifications Modal -->
<div class="notif-modal" id="notifModal">
    <div class="notif-modal-content">
        <div class="notif-modal-header">
            <h2>🔔 Notifications</h2>
            <button class="notif-modal-close" id="notifModalClose">✕</button>
        </div>
        <div class="notif-modal-body" id="notifModalBody">
            <p>Loading notifications...</p>
        </div>
    </div>
</div>

<script>
    // ===== MOBILE MENU FUNCTIONALITY =====
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('toggleSidebar');
    let sidebarOverlay = document.querySelector('.sidebar-overlay');
    
    // Create overlay if it doesn't exist
    if (!sidebarOverlay) {
        sidebarOverlay = document.createElement('div');
        sidebarOverlay.className = 'sidebar-overlay';
        document.body.appendChild(sidebarOverlay);
    }
    
    // Mobile menu toggle (for responsive hamburger menu)
    function toggleMobileMenu() {
        if (window.innerWidth <= 768) {
            sidebar.classList.toggle('mobile-open');
            sidebarOverlay.classList.toggle('show');
        }
    }
    
    // Close mobile menu
    function closeMobileMenu() {
        if (window.innerWidth <= 768) {
            sidebar.classList.remove('mobile-open');
            sidebarOverlay.classList.remove('show');
        }
    }
    
    // Toggle button for desktop collapse/expand
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (window.innerWidth > 768) {
                // Desktop: toggle collapse
                sidebar.classList.toggle('collapsed');
            } else {
                // Mobile: toggle menu
                toggleMobileMenu();
            }
        });
    }
    
    // Overlay click to close mobile menu
    sidebarOverlay.addEventListener('click', closeMobileMenu);
    
    // Close mobile menu when window is resized to desktop
    window.addEventListener('resize', () => {
        if (window.innerWidth > 768) {
            closeMobileMenu();
        }
    });
    
    // Close mobile menu when a link is clicked
    const menuLinks = sidebar.querySelectorAll('a');
    menuLinks.forEach(link => {
        link.addEventListener('click', function() {
            closeMobileMenu();
        });
    });
    
    // Close mobile menu when dropdown menu is opened
    document.querySelectorAll('.dropdown .menu-label').forEach(label => {
        label.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                // Keep menu open to show submenu, don't close
            }
        });
    });
    
    
    // ===== ORIGINAL SIDEBAR FUNCTIONALITY =====
    const notifBell = document.getElementById('notifBell');
    const notifModal = document.getElementById('notifModal');
    const notifModalClose = document.getElementById('notifModalClose');
    const notifModalBody = document.getElementById('notifModalBody');

    // Load notifications
    function loadNotifications() {
        fetch('get_notifications.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error('HTTP error, status = ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    notifModalBody.innerHTML = '<p style="color: #ef4444;">Error: ' + data.error + '</p>';
                    return;
                }
                
                if (data.notifications && data.notifications.length > 0) {
                    let html = '';
                    data.notifications.forEach(notif => {
                        html += `
                            <div class="notif-item" data-notif-id="${notif.id}">
                                <div class="notif-title">${notif.title}</div>
                                <div class="notif-message">${notif.message}</div>
                                <div class="notif-time">${notif.created_at}</div>
                                ${notif.type === 'trip_request' ? `
                                    <div class="notif-actions">
                                        <button class="btn-confirm-trip" onclick="confirmAndCreateTrip(${notif.id}, ${notif.related_id})">
                                            ✓ Confirm & Create Ticket
                                        </button>
                                    </div>
                                ` : ''}
                            </div>
                        `;
                    });
                    notifModalBody.innerHTML = html;
                    
                    // Update bell badge to show total notification count ONLY if modal is closed
                    const notifCount = data.notifications.length;
                    if (notifCount > 0 && !notifModal.classList.contains('show')) {
                        let badge = document.querySelector('.notif-badge');
                        if (!badge) {
                            badge = document.createElement('div');
                            badge.className = 'notif-badge';
                            notifBell.style.position = 'relative';
                            notifBell.appendChild(badge);
                        }
                        badge.textContent = notifCount;
                        badge.style.display = 'flex';
                    } else {
                        let badge = document.querySelector('.notif-badge');
                        if (badge) badge.style.display = 'none';
                    }
                } else {
                    notifModalBody.innerHTML = '<p style="color: #7e8299; padding: 20px;">No notifications at this time</p>';
                    let badge = document.querySelector('.notif-badge');
                    if (badge) badge.remove();
                }
            })
            .catch(error => {
                console.error('Notification fetch error:', error);
                notifModalBody.innerHTML = '<p style="color: #ef4444;">Error loading notifications: ' + error.message + '</p>';
            });
    }

    // Confirm trip request and go to CreateForm
    function confirmAndCreateTrip(notifId, userId) {
        // Show loading state
        event.target.disabled = true;
        event.target.textContent = '⏳ Processing...';
        
        // Mark notification as read and send notification to driver
        fetch('confirm_trip_request.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'notif_id=' + notifId + '&user_id=' + userId
        }).then(response => response.json())
          .then(data => {
            if (data.success) {
                // Close modal and reload notifications
                notifModal.classList.remove('show');
                loadNotifications();
                
                // Redirect to CreateForm with driver ID parameter
                setTimeout(() => {
                    window.location.href = 'CreateForm.php?driver_id=' + userId;
                }, 500);
            } else {
                alert('Error: ' + data.message);
                event.target.disabled = false;
                event.target.textContent = '✓ Confirm & Create Ticket';
            }
        })
        .catch(error => {
            alert('Error processing request: ' + error.message);
            event.target.disabled = false;
            event.target.textContent = '✓ Confirm & Create Ticket';
        });
    }

    // Notification bell click
    notifBell.addEventListener('click', () => {
        notifModal.classList.add('show');
        let badge = document.querySelector('.notif-badge');
        if (badge) badge.style.display = 'none';
        loadNotifications();
    });
    
    // Close notification modal
    notifModalClose.addEventListener('click', () => {
        notifModal.classList.remove('show');
    });
    
    // Close modal when clicking outside
    notifModal.addEventListener('click', (e) => {
        if (e.target === notifModal) {
            notifModal.classList.remove('show');
        }
    });

    // Prevent modal from closing when clicking on sidebar
    if (sidebar) {
        sidebar.addEventListener('click', (e) => {
            e.stopPropagation();
        });
    }

    // Load active trips count
    function loadActiveTripsCount() {
        fetch('get_active_trips_count.php')
            .then(response => response.json())
            .then(data => {
                const tripsBadge = document.getElementById('tripsBadge');
                const tripsBadgeSubmenu = document.getElementById('tripsBadgeSubmenu');
                if (data.count > 0) {
                    tripsBadge.textContent = data.count;
                    tripsBadge.style.display = 'flex';
                    tripsBadgeSubmenu.textContent = data.count;
                    tripsBadgeSubmenu.style.display = 'flex';
                } else {
                    tripsBadge.style.display = 'none';
                    tripsBadgeSubmenu.style.display = 'none';
                }
            })
            .catch(error => console.error('Error loading trips count:', error));
    }

    // Load notifications on page load for all users
    document.addEventListener('DOMContentLoaded', () => {
        loadNotifications();
        loadActiveTripsCount();
        // Refresh notifications every 10 seconds
        setInterval(loadNotifications, 10000);
        // Refresh active trips count every 15 seconds
        setInterval(loadActiveTripsCount, 15000);
    });

    document.querySelectorAll('.dropdown .menu-label').forEach(label => {
        label.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const dropdownItem = this.closest('.dropdown');
            
            // Remove active from other dropdowns
            document.querySelectorAll('.dropdown.active').forEach(other => {
                if (other !== dropdownItem) {
                    other.classList.remove('active');
                }
            });
            
            // Toggle current dropdown
            dropdownItem.classList.toggle('active');

            // Force expand if user clicks a menu while collapsed
            if (sidebar.classList.contains('collapsed')) {
                sidebar.classList.remove('collapsed');
            }
        });
    });

    // Create and insert hamburger menu button for mobile
    document.addEventListener('DOMContentLoaded', function() {
        if (window.innerWidth <= 768) {
            const wrapper = document.querySelector('.wrapper');
            const main = document.querySelector('main');
            
            // Create hamburger button container
            const hamburgerContainer = document.createElement('div');
            hamburgerContainer.style.cssText = 'display: flex; align-items: center; gap: 15px; margin-bottom: 10px; padding: 0;';
            
            const hamburgerBtn = document.createElement('button');
            hamburgerBtn.className = 'mobile-menu-toggle';
            hamburgerBtn.innerHTML = '☰';
            hamburgerBtn.title = 'Toggle Menu';
            hamburgerBtn.setAttribute('aria-label', 'Toggle navigation menu');
            hamburgerBtn.style.cssText = 'margin: 0; padding: 8px;';
            
            hamburgerBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleMobileMenu();
            });
            
            hamburgerContainer.appendChild(hamburgerBtn);
            
            // Insert hamburger button at the beginning of main
            if (main && main.firstChild) {
                main.insertBefore(hamburgerContainer, main.firstChild);
            }
        }
    });

    // Re-create hamburger on window resize for responsiveness
    window.addEventListener('resize', function() {
        const existingHamburger = document.querySelector('.mobile-menu-toggle');
        if (window.innerWidth <= 768 && !existingHamburger) {
            location.reload(); // Simple reload to recreate UI
        } else if (window.innerWidth > 768 && existingHamburger) {
            existingHamburger.parentElement.remove();
        }
    });
</script>