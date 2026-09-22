<?php
// --- user_notifications.php ---
require_once '../config.php';
require_once '../config_session.php';

$notif_user_id = getCurrentUserId();
$notif_logs = [];

$notif_user_id = getCurrentUserId();
$notif_logs = [];
$notif_count = 0;

// 1. HANDLE AJAX: MARK AS SEEN
// This listens for the request sent when you click the bell
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_seen') {
    try {
        // Update ALL notifications for the current user to 'yes'
        $updateStmt = $pdo->prepare("UPDATE tbl_notifications SET is_seen = 'yes', date_seen = NOW() WHERE user_id = ? AND is_seen = 'no'");
        $updateStmt->execute([$notif_user_id]);
        echo json_encode(['status' => 'success']);
        exit; // Stop script execution here for AJAX
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

// 2. COUNT UNSEEN NOTIFICATIONS
try {
    $countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM tbl_notifications WHERE user_id = ? AND is_seen = 'no'");
    $countStmt->execute([$notif_user_id]);
    $notif_count = $countStmt->fetchColumn();
} catch (PDOException $e) {
    $notif_count = 0;
}

// 3. FETCH NOTIFICATIONS FOR DROPDOWN
// We now join tbl_notifications to get only logs intended for the current user
try {
    $stmt = $pdo->prepare("
        SELECT 
            al.*, 
            n.is_seen, -- <--- ADDED THIS LINE
            CONCAT(u.first_name, ' ', COALESCE(u.middle_initial, ''), ' ', u.last_name) as full_name
        FROM tbl_notifications n
        JOIN tbl_activity_logs al ON n.log_id = al.log_id
        JOIN tbl_users u ON al.user_id = u.user_id
        WHERE n.user_id = ?
        ORDER BY al.dateAdded DESC 
        LIMIT 10
    ");
    $stmt->execute([$notif_user_id]);
    $notif_logs = $stmt->fetchAll();
} catch (PDOException $e) {
    $notif_logs = [];
}

// --- HELPERS (Keep your existing helpers) ---
function getNotifMeta($action_code, $activity_desc = '')
{
    $code = strtolower(trim($action_code));
    if ($code === 'login') return ['icon' => 'bx bx-log-in', 'color' => '#2ecc71', 'bg' => 'rgba(46, 204, 113, 0.1)', 'label' => 'Login'];
    if ($code === 'logout') return ['icon' => 'bx bx-log-out', 'color' => '#e67e22', 'bg' => 'rgba(230, 126, 34, 0.1)', 'label' => 'Logout'];
    if ($code === 'create') return ['icon' => 'bx bx-plus-circle', 'color' => '#3498db', 'bg' => 'rgba(52, 152, 219, 0.1)', 'label' => 'Created'];
    if ($code === 'update') return ['icon' => 'bx bx-edit', 'color' => '#9b59b6', 'bg' => 'rgba(155, 89, 182, 0.1)', 'label' => 'Updated'];
    if ($code === 'restore') return ['icon' => 'bx bx-undo', 'color' => '#27ae60', 'bg' => 'rgba(39, 174, 96, 0.1)', 'label' => 'Restored'];
    if ($code === 'archive') return ['icon' => 'bx bx-trash', 'color' => '#e74c3c', 'bg' => 'rgba(231, 76, 60, 0.1)', 'label' => 'Archived'];
    if ($code === 'renew') return ['icon' => 'bx bx-refresh', 'color' => '#16a085', 'bg' => 'rgba(22, 160, 133, 0.1)', 'label' => 'Renewed'];

    $desc = strtolower($activity_desc);
    if (!empty($desc)) {
        if (strpos($desc, 'logged in') !== false) return ['icon' => 'bx bx-log-in', 'color' => '#2ecc71', 'bg' => 'rgba(46, 204, 113, 0.1)', 'label' => 'Login'];
        if (strpos($desc, 'logged out') !== false) return ['icon' => 'bx bx-log-out', 'color' => '#e67e22', 'bg' => 'rgba(230, 126, 34, 0.1)', 'label' => 'Logout'];
        if (strpos($desc, 'added') !== false) return ['icon' => 'bx bx-plus-circle', 'color' => '#3498db', 'bg' => 'rgba(52, 152, 219, 0.1)', 'label' => 'Created'];
        if (strpos($desc, 'updated') !== false || strpos($desc, 'edited') !== false) return ['icon' => 'bx bx-edit', 'color' => '#9b59b6', 'bg' => 'rgba(155, 89, 182, 0.1)', 'label' => 'Updated'];
        if (strpos($desc, 'archived') !== false) return ['icon' => 'bx bx-trash', 'color' => '#e74c3c', 'bg' => 'rgba(231, 76, 60, 0.1)', 'label' => 'Archived'];
    }
    return ['icon' => 'bx bx-bolt-circle', 'color' => '#006400', 'bg' => 'transparent', 'label' => 'Action'];
}

function notifRelativeTime($datetime)
{
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    if ($diff->y > 0) return $diff->y . 'y ago';
    if ($diff->m > 0) return $diff->m . 'mo ago';
    if ($diff->d > 0) return $diff->d . 'd ago';
    if ($diff->h > 0) return $diff->h . 'h ago';
    if ($diff->i > 0) return $diff->i . 'm ago';
    return 'Just now';
}
?>

<?php
// Profile Image Logic (kept separate to avoid overwriting main page $user)
$current_user_id_for_nav = getCurrentUserId();
$nav_profile_pic = 'https://i.pinimg.com/736x/11/0e/6a/110e6affbed02f3f1b2d864832423fdc.jpg';

try {
    $navStmt = $pdo->prepare("SELECT profile_image FROM tbl_users WHERE user_id = ?");
    $navStmt->execute([$current_user_id_for_nav]);
    $navUserData = $navStmt->fetch();

    if ($navUserData && !empty($navUserData['profile_image'])) {
        $nav_profile_pic = $navUserData['profile_image'];
    }
} catch (PDOException $e) {
    // Keep default
}
?>

<head>

    <!-- ADD THIS INSIDE <head> TAG -->
    <script>
        // Define the absolute path of this file for the AJAX request.
        // This ensures the fetch works correctly even if this file is included in a different folder.
        const notifScriptPath = "<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>";
    </script>
    <!-- Ensure Boxicons is loaded here or in the main file head -->

    <style>
        /* Switch Mode & Icons */
        #content nav .switch-mode {
            display: block;
            min-width: 50px;
            height: 25px;
            border-radius: 25px;
            background: var(--grey);
            cursor: pointer;
            position: relative;
        }

        #content nav .switch-mode::before {
            content: '';
            position: absolute;
            top: 2px;
            left: 2px;
            bottom: 2px;
            width: calc(25px - 4px);
            background: var(--dark);
            border-radius: 50%;
            transition: all .3s ease;
        }

        #content nav #switch-mode:checked+.switch-mode::before {
            left: calc(100% - (25px - 4px) - 2px);
        }

        #content nav .swith-lm {
            background-color: var(--grey);
            border-radius: 50px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 3px;
            position: relative;
            height: 21px;
            width: 45px;
            transform: scale(1.5);
        }

        #content nav .swith-lm .ball {
            background-color: var(--dark);
            border-radius: 50%;
            position: absolute;
            top: 2px;
            left: 2px;
            height: 20px;
            width: 20px;
            transform: translateX(0px);
            transition: transform 0.2s linear;
        }

        #content nav .checkbox:checked+.swith-lm .ball {
            transform: translateX(22px);
        }

        .bxs-moon {
            color: var(--yellow);
        }

        .bx-sun {
            color: var(--orange);
            animation: shakeOn .7s;
        }

        /* Notification & Profile */
        #content nav .notification {
            font-size: 20px;
            position: relative;
        }

        #content nav .notification .num {
            position: absolute;
            top: -6px;
            right: -6px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid var(--light);
            background: var(--red);
            color: var(--light);
            font-weight: 700;
            font-size: 12px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        #content nav .notification-menu {
            display: none;
            position: absolute;
            top: 56px;
            right: 0;
            background: var(--light);
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            width: 250px;
            max-height: 300px;
            overflow-y: auto;
            z-index: 9999;
            font-family: var(--lato);
        }

        #content nav .notification-menu ul {
            list-style: none;
            padding: 10px;
            margin: 0;
        }

        #content nav .notification-menu li {
            padding: 10px;
            border-bottom: 1px solid var(--card-border);
            color: var(--dark);
        }

        #content nav .notification-menu li:hover {
            background-color: var(--grey);
            color: var(--dark);
        }

        #content nav .notification-menu li:hover a {
            background-color: transparent;
            color: var(--dark);
        }

        #content nav .profile img {
            width: 36px;
            height: 36px;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid #ebebb6;
        }

        #content nav .profile-menu {
            display: none;
            position: absolute;
            top: 47px;
            right: 21px;
            background: var(--light);
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            width: 200px;
            z-index: 9999;
        }

        #content nav .profile-menu ul {
            list-style: none;
            padding: 10px;
            margin: 0;
        }

        #content nav .profile-menu li {
            padding: 10px;
            border-bottom: 1px solid var(--card-border);
            color: var(--dark);
            font-size: 16px;
        }

        #content nav .profile-menu li:hover {
            background-color: var(--grey);
            color: var(--dark);
            border-radius: 0px;
        }

        #content nav .notification-menu.show,
        #content nav .profile-menu.show {
            display: block;
        }

        /* --- NAVBAR THEME SWITCH --- */
        .theme-switch-container {
            display: flex;
            align-items: center;
        }

        .theme-switch {
            position: relative;
            display: inline-block;
            width: 55px;
            height: 30px;
        }

        .theme-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #e2e8f0;
            /* Light mode background */
            transition: .4s;
            border-radius: 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 6px;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .slider i {
            font-size: 16px;
            color: #64748b;
            /* Icon color */
            z-index: 1;
            transition: color 0.3s;
        }

        /* The moving circle */
        .slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            border-radius: 50%;
            transition: .4s;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }

        /* --- Prevent animation glitch on page load --- */
        .slider.no-transition,
        .slider.no-transition:before {
            transition: none !important;
        }

        /* Checked State (Dark Mode) */
        input:checked+.slider {
            background-color: var(--dark);
            /* Green background */
        }

        input:checked+.slider:before {
            transform: translateX(25px);
        }

        input:checked+.slider i.bx-moon {
            color: #64748b;
            /* Icon color */
        }

        input:checked+.slider i.bx-sun {
            color: rgba(255, 255, 255, 0.5);
        }

        /* NAVBAR LAYOUT */
        #content nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            height: 56px;
        }

        #content nav .row {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        #content nav .profile-menu li {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        #content nav .profile-menu li i {
            font-size: 18px;
        }

        /* Dark Mode Body Adjustments */
        body.dark .slider {
            background-color: #1e5e35;
            /* Darker green for the track */
        }

        body.dark #content nav .profile-menu li:hover {
            color: #ffff !important;
            background-color: rgba(255, 255, 255, 0.1);
        }


        /* --- NOTIFICATION BELL & DROPDOWN STYLES --- */

        /* The Bell Icon Wrapper (Legacy - Kept for safety) */
        #content nav .notification-icon-wrapper {
            font-size: 20px;
            position: relative;
            cursor: pointer;
            color: var(--dark);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 5px;
            border-radius: 50%;
            transition: background 0.2s;
        }

        body.dark #content nav .notification-icon-wrapper {
            color: #fff;
        }

        #content nav .notification-icon-wrapper:hover {
            background-color: var(--grey);
        }

        /* The Dropdown Modal (Old Menu - Kept for safety) */
        #content nav .notification-menu {
            display: none;
            position: absolute;
            top: 60px;
            right: 0;
            background: var(--light);
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            /* CHANGES: Made wider and longer here */
            width: 380px;
            max-height: 500px;
            overflow-y: auto;
            z-index: 9999;
            font-family: var(--lato);
            animation: slideDown 0.2s ease-in-out;
        }

        /* --- NOTIFICATION BELL STYLES (IMAGE VERSION) --- */

        /* 1. The Bell Trigger Container */
        #content nav .notif-trigger {
            position: relative;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 5px;
            padding: 5px;
            border-radius: 50%;
            transition: all 0.3s ease;

            /* ADDED: This sets the hover color as the permanent default */
            background-color: rgba(59, 130, 246, 0.1);
        }

        /* 2. The Bell Image Styling */
        .bell-img {
            width: 27px; /* Adjust size to fit your navbar */
            height: 27px;
            object-fit: contain; /* Ensures the image doesn't stretch */
            pointer-events: none; /* Ensures clicks go to the parent div */
            transition: transform 0.3s ease;
        }

        /* Hover Effects (Light Mode) */
        #content nav .notif-trigger:hover {
            /* CHANGED: Increased opacity to make it lighter */
            background-color: rgba(59, 130, 246, 0.25);
        }

        /* Slight zoom effect on the image when hovering the container */
        #content nav .notif-trigger:hover .bell-img {
            transform: scale(1.05);
        }

        /* ADDED: Dark Mode Default Background */
        body.dark #content nav .notif-trigger {
            background-color: rgba(255, 255, 255, 0.1);
        }

        /* Hover Effects (Dark Mode) */
        body.dark #content nav .notif-trigger:hover {
            /* CHANGED: Increased opacity to make it lighter */
            background-color: rgba(255, 255, 255, 0.25);
        }

        /* 2. The Pulsing Red Badge (Updated for Number) */
        #content nav .notif-badge {
            position: absolute; 
            top: 0px;
            right: 0px;

            /* Size Logic: Auto width to fit numbers, min-height to ensure circle for '1' */
            width: auto;
            min-width: 18px;
            height: 18px;

            background-color: var(--red);
            border-radius: 50%;
            /* Keeps it circular/oval */
            border: 0px solid var(--light);

            /* Flexbox to center the number text */
            /* Flexbox allows PHP content to show, but empty elements collapse automatically */
            display: flex;
            align-items: center;
            justify-content: center;
            align-items: center;
            justify-content: center;

            /* Text Styling */
            font-size: 10px;
            font-weight: bold;
            color: #fff;
            padding: 0 4px;
            /* Adds space inside the dot if number is wide */
            line-height: 1;

            /* Champion Additions: Pulse Animation */
            box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7);
            animation: pulse-red 2s infinite;
            z-index: 10;
        }

        /* Ensure Dark Mode Border still works */
        body.dark #content nav .notif-badge {
            border-color: var(--dark-blue);
        }

        /* Pulse Animation Keyframes */
        @keyframes pulse-red {
            0% {
                transform: scale(0.95);
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7);
            }

            70% {
                transform: scale(1);
                box-shadow: 0 0 0 6px rgba(239, 68, 68, 0);
            }

            100% {
                transform: scale(0.95);
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
            }
        }

        /* 3. The Dropdown Modal */
        #content nav .notif-dropdown {
            display: none;
            position: absolute;
            top: 40px;
            /* Pushed down slightly for breathing room */
            right: 0px;
            /* Shifted right to align perfectly with the bell */
            width: 360px;
            /* Wider for better readability */
            background: var(--light);

            /* Champion Additions: Deep shadow and distinct border */
            border: 1px solid rgba(0, 0, 0, 0.08);
            /* Subtle distinct border */
            box-shadow: 0 10px 40px -10px rgba(0, 0, 0, 0.15);
            /* Deep, soft shadow */
            border-radius: 16px;
            /* More modern, rounded corners */

            z-index: 9999;
            overflow: hidden;
            /* Ensures border radius clips content */
            flex-direction: column;
            backdrop-filter: blur(10px);
            /* Glassmorphism effect */
        }

        /* Dark Mode Adjustments for Dropdown (MATCHING DASHBOARD GREEN THEME) */
        body.dark #content nav .notif-dropdown {
            border: 1px solid var(--card-border);
            background: var(--card-bg);
            /* Updated: Added Green Glow (0 0 20px...) + Deep Shadow */
            box-shadow: 0 0 20px rgba(46, 204, 113, 0.15), 0 10px 40px -10px rgba(0, 0, 0, 0.6);
        }

        body.dark .notif-header {
            background: linear-gradient(135deg, rgba(46, 204, 113, 0.15), rgba(46, 204, 113, 0.05));
            /* Green Glass Gradient */
            color: #fff !important;
            /* Light Green Text */
            border-bottom: 1px solid var(--card-border);
        }

        /* Fix Text Colors for Readability */
        body.dark .notif-content p {
            color: var(--dark);
            /* Light Green Text */
        }

        body.dark .notif-time {
            color: var(--dark-grey);
            /* Muted Green Text */
        }

        /* Fix Link Colors */
        body.dark .notif-link {
            color: var(--dark) !important;
            /* Light Green Links */
        }

        /* Fix Hover State */
        body.dark .notif-item:hover {
            background-color: rgba(255, 255, 255, 0.05);
            /* Subtle light highlight */
        }

        /* Show Class */
        #content nav .notif-dropdown.show {
            display: flex;
            /* Updated animation for smoother feel */
            animation: slideDown 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        /* Unified Animation Keyframes (Removed Duplicate) */
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

        /* Dropdown Header */
        .notif-header {
            padding: 12px 15px;
            border-bottom: 1px solid var(--card-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--grey);
            font-weight: 600;
            font-size: 14px;
            color: var(--dark);
            border-radius: 16px 16px 0 0;
            /* Match modal radius */
        }

        body.dark .notif-header {
            background: #2c3e50;
            color: var(--light);
            border-bottom: 1px solid #444;
        }

        /* List Styling */
        .notif-list {
            list-style: none;
            padding: 0;
            margin: 0;
            max-height: 500px;
            overflow-y: auto;
        }

        .notif-item {
            padding: 12px 15px;
            border-bottom: 1px solid var(--grey);
            display: flex;
            gap: 12px;
            align-items: flex-start;
            transition: background 0.2s ease;
            /* Removed 'all' to stop transform */
        }

        /* UPDATED: Removed transform: translateX - Now only background change */
        .notif-item:hover {
            background-color: var(--grey);
        }

        /* Dark mode hover adjustment */
        body.dark .notif-item:hover {
            background-color: rgba(255, 255, 255, 0.05);
        }

        .notif-icon-box {
            min-width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .notif-content p {
            margin: 0 0 4px 0;
            font-size: 13px;
            line-height: 1.4;
            color: var(--dark);
        }

        body.dark .notif-content p {
            color: var(--light);
        }

        .notif-link {
            text-decoration: none;
            font-weight: 500;
            transition: opacity 0.2s;
        }

        .notif-link:hover {
            text-decoration: underline;
            opacity: 0.8;
        }

        .notif-time {
            font-size: 11px;
            color: var(--dark-grey);
        }

        .notif-empty {
            padding: 30px;
            text-align: center;
            color: var(--dark-grey);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
        }

        .notif-empty i {
            font-size: 24px;
        }

        /* --- DARK MODE SCROLLBAR FOR NOTIFICATIONS --- */
        body.dark .notif-list::-webkit-scrollbar {
            width: 8px;
        }

        body.dark .notif-list::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.1);
            /* Dark transparent background */
        }

        body.dark .notif-list::-webkit-scrollbar-thumb {
            background: rgba(46, 204, 113, 0.3);
            /* Green transparent handle */
            border-radius: 10px;
        }

        body.dark .notif-list::-webkit-scrollbar-thumb:hover {
            background: rgba(46, 204, 113, 0.6);
            /* Brighter green on hover */
        }





        /* --- UPDATED FIX: Bold text for unread notifications --- */

        /* 1. Bold for the paragraph container */
        .notif-item.unread .notif-content p {
            font-weight: 700;
            color: var(--dark);
        }

        /* 2. Bold for the LINK itself (Since the text is inside <a class="notif-link">) */
        /* We use !important to override the default .notif-link weight of 500 */
        .notif-item.unread .notif-link {
            font-weight: 700 !important;
            color: var(--dark);
        }

        /* 3. Style for READ notifications (Normal weight) */
        .notif-item:not(.unread) .notif-content p {
            font-weight: 400;
            opacity: 0.8;
        }

        /* 4. DARK MODE adjustments for Unread */
        body.dark .notif-item.unread .notif-content p {
            color: #fff;
        }

        body.dark .notif-item.unread .notif-link {
            color: #fff !important;
        }
    </style>
</head>

<!-- HERE -->


<!-- NAVBAR -->
<nav>
    <!-- LEFT: Menu Burger -->
    <div class="nav-left">
        <i class='bx bx-menu bx-sm' style="cursor:pointer; font-size:24px;"></i>
    </div>

    <!-- RIGHT: Switch + Profile -->
    <div class="row nav-right">
        <!-- Theme Toggle Switch -->
        <div class="theme-switch-container">
            <label class="theme-switch" for="navbar-switch">
                <input type="checkbox" id="navbar-switch">
                <span class="slider">
                    <i class='bx bx-sun'></i>
                    <i class='bx bx-moon'></i>
                </span>
            </label>
        </div>

                <!-- NOTIFICATION BELL ADDITION START -->
        <div class="notif-trigger" id="notifBell">
            <!-- REPLACED ICON WITH YOUR IMAGE -->
            <img src="../images/NOTIF_BELL_PNG.png" alt="Notifications" class="bell-img">
            
            <?php if ($notif_count > 0): ?>
                <span class="notif-badge">
                    <?= $notif_count ?>
                </span>
            <?php endif; ?>

            <!-- Notification Dropdown Modal -->
            <div class="notif-dropdown" id="notifDropdown">

                <!-- Notif List Items -->
                <div class="notif-header">
                    <span>Recent Activity</span>
                    <a href="#" style="font-size: 12px; color: var(--blue); text-decoration:none;">View All</a>
                </div>

                <ul class="notif-list">
                    <!-- FIX 1: Check the correct variable -->
                    <?php if (empty($notif_logs)): ?>
                        <li class="notif-empty">
                            <i class='bx bx-bell-off'></i>
                            <span>No recent notifications</span>
                        </li>
                    <?php else: ?>
                        <!-- FIX 2: Loop through the correct variable -->
                        <?php foreach ($notif_logs as $log):
                            $meta = getNotifMeta($log['action'], $log['activity']);
                            $log_date = new DateTime($log['dateAdded']);
                            $contract_id = $log['contract_id'] ?? '';
                            $fallback_id = $log['log_id'] ?? '';

                            // --- NEW: Check if seen ---
                            $is_unseen = (isset($log['is_seen']) && $log['is_seen'] === 'no');
                            $item_class = 'notif-item';
                            if ($is_unseen) {
                                $item_class .= ' unread';
                            }
                        ?>
                            <!-- Apply the class here -->
                            <li class="<?= $item_class ?>">
                                <!-- Icon -->
                                <div class="notif-icon-box" style="background: <?= $meta['bg'] ?>;">
                                    <i class="<?= $meta['icon'] ?>" style="color: <?= $meta['color'] ?>;"></i>
                                </div>

                                <!-- Content -->
                                <div class="notif-content">
                                    <!-- Custom Message -->
                                    <p>
                                        <?php
                                        // Get the coworker's full name from the joined query
                                        $actor_name = htmlspecialchars($log['full_name'] ?? 'Unknown User');

                                        // Construct the message
                                        $message = "<strong>" . $actor_name . "</strong> ";

                                        if ($meta['label'] === 'Created') {
                                            $message .= "created a new entry: ";
                                        } elseif ($meta['label'] === 'Updated') {
                                            $message .= "updated an entry: ";
                                        } elseif ($meta['label'] === 'Archived') {
                                            $message .= "archived an entry: ";
                                        } elseif ($meta['label'] === 'Restored') {
                                            $message .= "restored an entry: ";
                                        } elseif ($meta['label'] === 'Renewed') {
                                            $message .= "renewed a contract: ";
                                        } else {
                                            $message .= $meta['label'] . ": ";
                                        }

                                        // Append the actual activity description
                                        $message .= htmlspecialchars($log['activity']);

                                        // Wrap in link if it's a contract action
                                        if (in_array($meta['label'], ['Created', 'Updated', 'Archived', 'Restored', 'Renewed'])) {
                                            if (!empty($contract_id)) {
                                                $viewUrl = ($meta['label'] === 'Archived')
                                                    ? 'landowners_tabs/view_archived_contract.php?contract_id=' . urlencode($contract_id)
                                                    : 'landowners_tabs/view_contract.php?contract_id=' . urlencode($contract_id);

                                                echo '<a href="' . $viewUrl . '" class="notif-link" style="color: var(--dark);" target="_blank">' . $message . '</a>';
                                            } else {
                                                echo $message;
                                            }
                                        } else {
                                            echo $message;
                                        }
                                        ?>
                                    </p>
                                    <span class="notif-time"><?= notifRelativeTime($log['dateAdded']) ?></span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        <!-- NOTIFICATION BELL ADDITION END -->

        <!-- Profile -->
        <a href="#" class="profile" id="profileIcon">
            <img src="../users/images/<?php echo htmlspecialchars($nav_profile_pic); ?>" alt="Profile">
        </a>

        <div class="profile-menu" id="profileMenu">
            <ul>
                <li onclick="window.location.href='user_profile.php';" style="cursor:pointer;"><i class='bx bx-user'></i> My Profile</li>
                <li onclick="window.location.href='user_settings.php';" style="cursor:pointer;"><i class='bx bx-cog'></i> Settings</li>
                <li onclick="window.location.href='user_history.php';" style="cursor:pointer;"><i class='bx bx-history'></i> History</li>
                <li onclick="window.location.href='user_archives.php';" style="cursor:pointer;"><i class='bx bx-archive'></i> Archives</li>
                <li onclick="window.location.href='actions/logout.php';" style="cursor:pointer;"><i class='bx bx-log-out'></i> Log Out</li>
            </ul>
        </div>
    </div>
</nav>
<!-- NAVBAR -->

<script>
    /**
     * THEME HANDLER SCRIPT
     * 1. Checks local storage for saved theme.
     * 2. Applies theme to body.
     * 3. Syncs Navbar switch AND Settings page buttons.
     */

    // Added 'isInitialLoad' parameter to detect first run
    function applyTheme(theme, isInitialLoad = false) {
        // 1. Apply class to body
        if (theme === 'dark') {
            document.body.classList.add('dark');
        } else {
            document.body.classList.remove('dark');
        }

        // 2. Save to local storage
        localStorage.setItem('theme', theme);

        // 3. Sync Navbar Switch (if it exists on the page)
        const navbarSwitch = document.getElementById('navbar-switch');
        if (navbarSwitch) {
            // Find the slider span inside the label
            const slider = navbarSwitch.parentElement.querySelector('.slider');

            // If this is the initial load, add the class to stop animation
            if (isInitialLoad && slider) {
                slider.classList.add('no-transition');
            }

            // Set the checked state
            navbarSwitch.checked = (theme === 'dark');

            // If this was the initial load, remove the class after a split second 
            // so future clicks animate normally
            if (isInitialLoad && slider) {
                requestAnimationFrame(() => {
                    slider.classList.remove('no-transition');
                });
            }
        }

        // 4. Sync Settings Page Buttons (if they exist on the page)
        const themeBtnLight = document.getElementById('theme-light');
        const themeBtnDark = document.getElementById('theme-dark');

        if (themeBtnLight && themeBtnDark) {
            if (theme === 'dark') {
                themeBtnLight.classList.remove('active');
                themeBtnDark.classList.add('active');
            } else {
                themeBtnLight.classList.add('active');
                themeBtnDark.classList.remove('active');
            }
        }
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', () => {
        const savedTheme = localStorage.getItem('theme') || 'light';

        // PASS 'true' here to tell the script to disable animation
        applyTheme(savedTheme, true);

        // EVENT LISTENER: Navbar Switch Change
        const navbarSwitch = document.getElementById('navbar-switch');
        if (navbarSwitch) {
            navbarSwitch.addEventListener('change', (e) => {
                // PASS 'false' here (default), so animations work when clicking
                const newTheme = e.target.checked ? 'dark' : 'light';
                applyTheme(newTheme, false);
            });
        }


        // --- NOTIFICATION & PROFILE DROPDOWN LOGIC ---

        const notifBell = document.getElementById('notifBell');
        const notifMenu = document.getElementById('notifMenu');
        const profileIcon = document.getElementById('profileIcon');
        const profileMenu = document.getElementById('profileMenu');

        // Toggle Notification Menu
        if (notifBell) {
            notifBell.addEventListener('click', (e) => {
                e.stopPropagation(); // Prevent closing immediately
                profileMenu.classList.remove('show'); // Close profile if open
                notifMenu.classList.toggle('show');
            });
        }

        // Toggle Profile Menu
        if (profileIcon) {
            profileIcon.addEventListener('click', (e) => {
                e.stopPropagation();
                notifMenu.classList.remove('show'); // Close notif if open
                profileMenu.classList.toggle('show');
            });
        }

        // Close menus when clicking outside
        document.addEventListener('click', (e) => {
            if (notifBell && !notifBell.contains(e.target) && !notifMenu.contains(e.target)) {
                notifMenu.classList.remove('show');
            }
            if (profileIcon && !profileIcon.contains(e.target) && !profileMenu.contains(e.target)) {
                profileMenu.classList.remove('show');
            }
        });
    });

    // Allow external calls from Settings page HTML (onclick="setTheme('dark')")
    // Defaults to false so manual clicks on Settings page animate correctly
    function setTheme(theme) {
        applyTheme(theme, false);
    }
</script>


<script>
    // --- NOTIFICATION DROPDOWN LOGIC ---
    document.addEventListener('DOMContentLoaded', () => {
        const notifBell = document.getElementById('notifBell');
        const notifDropdown = document.getElementById('notifDropdown');
        const notifBadge = document.querySelector('.notif-badge'); // Select the badge
        const profileIcon = document.getElementById('profileIcon');
        const profileMenu = document.getElementById('profileMenu');

        // REPLACE THE EXISTING FUNCTION WITH THIS ONE
        function markAsSeenAndHide() {
            // 1. Get the badge element
            const notifBadge = document.querySelector('.notif-badge');

            // 2. Safety Check: Only run if the badge exists and has a number
            if (notifBadge && notifBadge.innerText.trim() !== '') {

                // 3. UPDATE DATABASE IMMEDIATELY (Background)
                // We send the request now so that if they refresh, the system knows it's read.
                fetch(notifScriptPath, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=mark_seen'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status !== 'success') {
                            console.error('Server Error:', data.message);
                        }
                    })
                    .catch(error => console.error('AJAX Error:', error));

                // 4. DELAY THE BADGE DISAPPEARANCE (2 Seconds)
                setTimeout(() => {
                    // After 2 seconds, hide the badge number visually
                    notifBadge.style.display = 'none';
                    // Clear the text so the check works correctly next time
                    notifBadge.innerText = '';
                }, 2000); // 2000ms = 2 seconds

                // 5. IMPORTANT: We do NOT remove the 'unread' class here.
                // This keeps the text bold while the user is on the page.
                // The bold text will only go away when they refresh (because PHP sees 'is_seen' = 'yes').
            }
        }

        // Toggle Notification Menu
        if (notifBell) {
            notifBell.addEventListener('click', (e) => {
                e.stopPropagation(); // Prevent click from closing immediately
                const isShowing = notifDropdown.classList.contains('show');

                // Close profile menu if open
                if (profileMenu) profileMenu.classList.remove('show');

                if (isShowing) {
                    notifDropdown.classList.remove('show');
                } else {
                    notifDropdown.classList.add('show');

                    // --- SCENARIO 1: Bell Clicked ---
                    // Mark everything as seen as soon as they open the dropdown
                    markAsSeenAndHide();
                }
            });
        }

        // --- SCENARIO 3: Link Clicked Inside Dropdown ---
        // Add click listeners to all links inside the notification list
        const notifLinks = document.querySelectorAll('.notif-link');
        notifLinks.forEach(link => {
            link.addEventListener('click', () => {
                // Mark as seen immediately upon clicking a specific item
                markAsSeenAndHide();
            });
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', (e) => {
            // If click is NOT inside the bell/dropdown AND NOT inside the profile/menu
            if (!notifBell.contains(e.target) && !profileIcon.contains(e.target)) {
                if (notifDropdown) notifDropdown.classList.remove('show');
                if (profileMenu) profileMenu.classList.remove('show');
            }
        });
    });
</script>