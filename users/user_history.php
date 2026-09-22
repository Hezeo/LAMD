<?php
require_once '../config.php';
require_once '../config_session.php';
checkLogin();

$user_id = getCurrentUserId();

// --- FETCH ACTIVITY LOGS ---
$logs = [];
$total_logs = 0;
$login_count = 0;
$logout_count = 0;
$action_count = 0;
$first_activity = null;
$last_activity = null;

try {
    // Get total counts
    $countStmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN activity LIKE '%logged in%' THEN 1 ELSE 0 END) as logins,
            SUM(CASE WHEN activity LIKE '%logged out%' THEN 1 ELSE 0 END) as logouts,
            SUM(CASE WHEN activity NOT LIKE '%logged in%' AND activity NOT LIKE '%logged out%' THEN 1 ELSE 0 END) as actions,
            MIN(dateAdded) as first_activity,
            MAX(dateAdded) as last_activity
        FROM tbl_activity_logs 
        WHERE user_id = ? AND user_id IS NOT NULL
    ");
    $countStmt->execute([$user_id]);
    $stats = $countStmt->fetch();

    $total_logs = (int)$stats['total'];
    $login_count = (int)$stats['logins'];
    $logout_count = (int)$stats['logouts'];
    $action_count = (int)$stats['actions'];
    $first_activity = $stats['first_activity'];
    $last_activity = $stats['last_activity'];

    // Get paginated logs
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $per_page = 15;
    $offset = ($page - 1) * $per_page;

    $logStmt = $pdo->prepare(
        "
        SELECT * FROM tbl_activity_logs 
        WHERE user_id = ? AND user_id IS NOT NULL 
        ORDER BY dateAdded DESC 
        LIMIT " . (int)$per_page . " OFFSET " . (int)$offset
    );
    $logStmt->execute([$user_id]);
    $logs = $logStmt->fetchAll();

    // Total pages
    $total_pages = ceil($total_logs / $per_page);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// --- FETCH USER DATA (for display) ---
try {
    $stmt = $pdo->prepare("SELECT u.*, r.role_name FROM tbl_users u LEFT JOIN tbl_roles r ON u.role_id = r.role_id WHERE u.user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    if (!$user) die("User not found");

    $full_name = trim($user['first_name'] . ' ' . ($user['middle_initial'] ? $user['middle_initial'] . '.' : '') . ' ' . $user['last_name']);
    $full_name = preg_replace('/\s+/', ' ', $full_name);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// --- SMART IMAGE LOGIC ---
$default_cloud_url = "https://i.ibb.co/kVPtjmbK/default-profile-pic.png";
$profile_image_src = $default_cloud_url;
if (!empty($user['profile_image'])) {
    if (filter_var($user['profile_image'], FILTER_VALIDATE_URL)) {
        $profile_image_src = htmlspecialchars($user['profile_image']);
    } else {
        $profile_image_src = '../users/images/' . htmlspecialchars($user['profile_image']);
    }
}
$header_profile_image_src = $profile_image_src;

// --- HELPER: Get activity icon/color (HYBRID VERSION) ---
// This function checks the new 'action' code first. 
// If that is empty, it checks the old 'activity' description.
function getActivityMeta($action_code, $activity_desc = '')
{
    // 1. Primary Check: The new 'action' column (e.g., 'login', 'add', 'update')
    $code = strtolower(trim($action_code));

    // Match exact codes from your new column
    if ($code === 'login') return ['icon' => 'bx bx-log-in', 'color' => '#2ecc71', 'bg' => 'rgba(46,204,113,0.12)', 'label' => 'Login'];
    if ($code === 'logout') return ['icon' => 'bx bx-log-out', 'color' => '#e67e22', 'bg' => 'rgba(230,126,34,0.12)', 'label' => 'Logout'];
    if ($code === 'create') return ['icon' => 'bx bx-plus-circle', 'color' => '#3498db', 'bg' => 'rgba(52,152,219,0.12)', 'label' => 'Created'];
    if ($code === 'update') return ['icon' => 'bx bx-edit', 'color' => '#9b59b6', 'bg' => 'rgba(155,89,182,0.12)', 'label' => 'Updated'];
    if ($code === 'restore') return ['icon' => 'bx bx-undo', 'color' => '#27ae60', 'bg' => 'rgba(39,174,96,0.12)', 'label' => 'Restored'];
    if ($code === 'archive') return ['icon' => 'bx bx-trash', 'color' => '#e74c3c', 'bg' => 'rgba(231,76,60,0.12)', 'label' => 'Archived'];
    if ($code === 'renew') return ['icon' => 'bx bx-refresh', 'color' => '#16a085', 'bg' => 'rgba(22,160,133,0.12)', 'label' => 'Renewed'];
    if ($code === 'print') return ['icon' => 'bx bx-printer', 'color' => '#7f8c8d', 'bg' => 'rgba(127,140,141,0.12)', 'label' => 'Printed'];

    // 2. Fallback Check: The old 'activity' description (e.g., 'Successfully logged in')
    // This runs if the 'action' column was empty or null (Old Logs)
    $desc = strtolower($activity_desc);
    if (!empty($desc)) {
        if (strpos($desc, 'logged in') !== false) return ['icon' => 'bx bx-log-in', 'color' => '#2ecc71', 'bg' => 'rgba(46,204,113,0.12)', 'label' => 'Login'];
        if (strpos($desc, 'logged out') !== false) return ['icon' => 'bx bx-log-out', 'color' => '#e67e22', 'bg' => 'rgba(230,126,34,0.12)', 'label' => 'Logout'];
        if (strpos($desc, 'created') !== false) return ['icon' => 'bx bx-plus-circle', 'color' => '#3498db', 'bg' => 'rgba(52,152,219,0.12)', 'label' => 'Created'];
        if (strpos($desc, 'updated') !== false || strpos($desc, 'edited') !== false) return ['icon' => 'bx bx-edit', 'color' => '#9b59b6', 'bg' => 'rgba(155,89,182,0.12)', 'label' => 'Updated'];
        if (strpos($desc, 'archived') !== false || strpos($desc, 'archived') !== false) return ['icon' => 'bx bx-trash', 'color' => '#e74c3c', 'bg' => 'rgba(231,76,60,0.12)', 'label' => 'Archived'];
    }

    // 3. Default Fallback
    return ['icon' => 'bx bx-bolt-circle', 'color' => '#006400', 'bg' => 'rgba(0,100,0,0.12)', 'label' => 'Action'];
}

// --- HELPER: Relative time ---
function relativeTime($datetime)
{
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'Just now';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="icon"
        href="https://upload.wikimedia.org/wikipedia/commons/thumb/3/35/Logo_Del_Monte.svg/2560px-Logo_Del_Monte.svg.png"
        type="image-x-icon" />
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="css/user_history.css">
    <title>Activity History | Land Asset Management Department</title>

    <style>
        /* --- Styles for Clickable Activity Link --- */
        .activity-link {
            color: #3498db;
            text-decoration: none;
            font-weight: 500;
            border-bottom: 1px dashed transparent;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            cursor: pointer;
        }

        .activity-link:hover {
            color: #2980b9;
            border-bottom-color: #2980b9;
            background-color: rgba(52, 152, 219, 0.05);
            padding: 0 4px;
            margin: 0 -4px;
            border-radius: 4px;
        }

        .activity-link i {
            font-size: 0.9em;
            opacity: 0;
            transition: opacity 0.2s;
        }

        .activity-link:hover i {
            opacity: 1;
        }
    </style>
</head>

<body>
    <!-- FIX: PREVENT FLASH OF WRONG THEME -->
    <script>
        (function() {
            try {
                const theme = localStorage.getItem('theme');
                if (theme === 'dark') {
                    document.body.classList.add('dark');
                }
            } catch (e) {}
        })();
    </script>
    <!-- END FIX -->
     
    <!-- SIDEBAR -->
    <section id="sidebar">
        <?php include 'components/user_sidebar.php'; ?>
    </section>
    <!-- SIDEBAR -->

    <!-- <section id="sidebar">
        <a href="#" class="brand">
            <img src="../images/web_icon.png" alt="LAND ASSET MANAGEMENT" style="height: 40px; width: auto; object-fit: contain; margin-right: 10px;">
            <span class="text">LAND ASSET MANAGEMENT DEPARTMENT</span>
        </a>
        <ul class="side-menu top">
            <?php if (hasPermission('Dashboard')): ?>
                <li><a href="user_dashboard.php"><i class='bx bxs-dashboard bx-sm'></i><span class="text">Dashboard</span></a></li>
            <?php endif; ?>
            <?php if (hasPermission('Land Owners')): ?>
                <li><a href="land_owners.php"><i class='bx bxs-user bx-sm'></i><span class="text">Land Owners</span></a></li>
            <?php endif; ?>
            <?php if (hasPermission('Expiry Tracker')): ?>
                <li><a href="user_exp.php"><i class='bx bx-calendar-exclamation bx-sm'></i><span class="text">Expiry Tracker</span></a></li>
            <?php endif; ?>
            <?php if (hasPermission('Reports Generator')): ?>
                <li><a href="user_report.php"><i class='bx bx-line-chart bx-sm'></i><span class="text">Reports Generator</span></a></li>
            <?php endif; ?>
        </ul>
    </section> -->

    <section id="content">
        <?php include 'components/user_navbar.php'; ?>

        <main>
            <div class="page-header">
                <h1>Activity History</h1>
                <p>Track all your account actions and sessions</p>
            </div>

            <div class="profile-container">
                <!-- MAIN COLUMN -->
                <div class="profile-main">
                    <div class="hero-meta">
                        <span class="hero-badge badge-total">
                            <i class='bx bxs-bar-chart-alt-2'></i>
                            <?= number_format($total_logs) ?> Total Events
                        </span>
                        <?php if ($last_activity): ?>
                            <span class="hero-badge badge-last">
                                <i class='bx bx-time-five'></i>
                                Last: <?= relativeTime($last_activity) ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Quick Stats Row -->
                    <div class="stats-row">
                        <div class="stat-card stat-login">
                            <div class="stat-icon"><i class='bx bx-log-in'></i></div>
                            <div class="stat-info">
                                <div class="stat-number"><?= number_format($login_count) ?></div>
                                <div class="stat-label">Logins</div>
                            </div>
                        </div>
                        <div class="stat-card stat-logout">
                            <div class="stat-icon"><i class='bx bx-log-out'></i></div>
                            <div class="stat-info">
                                <div class="stat-number"><?= number_format($logout_count) ?></div>
                                <div class="stat-label">Logouts</div>
                            </div>
                        </div>
                        <div class="stat-card stat-action">
                            <div class="stat-icon"><i class='bx bx-bolt-circle'></i></div>
                            <div class="stat-info">
                                <div class="stat-number"><?= number_format($action_count) ?></div>
                                <div class="stat-label">Actions</div>
                            </div>
                        </div>
                    </div>

                    <!-- Activity Log Timeline -->
                    <div class="profile-card">
                        <div class="profile-card-header">
                            <div class="icon-box"><i class='bx bx-list-ul'></i></div>
                            <div class="header-content">
                                <h3>Activity Timeline</h3>
                                <p>Chronological log of your activities</p>
                            </div>
                        </div>

                        <?php if (empty($logs)): ?>
                            <div class="empty-state">
                                <div class="empty-icon"><i class='bx bx-ghost'></i></div>
                                <h4>No activity recorded yet</h4>
                                <p>Your actions will appear here as you use the system.</p>
                            </div>
                        <?php else: ?>
                            <div class="timeline">
                                <?php
                                $current_date_group = null;
                                foreach ($logs as $i => $log):
                                    $log_date = new DateTime($log['dateAdded']);
                                    $date_group = $log_date->format('Y-m-d');

                                    // --- IMPORTANT FIX ---
                                    // We now pass BOTH variables to the function:
                                    // 1. $log['action'] (The new code: 'login', 'add', etc)
                                    // 2. $log['activity'] (The description: 'Successfully logged in')
                                    // The function will use whichever one works.
                                    $meta = getActivityMeta($log['action'], $log['activity']);

                                    $is_last = ($i === count($logs) - 1);
                                ?>
                                    <?php if ($date_group !== $current_date_group): ?>
                                        <?php $current_date_group = $date_group; ?>
                                        <div class="timeline-date-separator">
                                            <div class="date-dot"></div>
                                            <span class="date-text">
                                                <?php
                                                $today = new DateTime('today');
                                                $yesterday = new DateTime('yesterday');
                                                if ($log_date->format('Y-m-d') === $today->format('Y-m-d')) {
                                                    echo 'Today — ' . $log_date->format('F j, Y');
                                                } elseif ($log_date->format('Y-m-d') === $yesterday->format('Y-m-d')) {
                                                    echo 'Yesterday — ' . $log_date->format('F j, Y');
                                                } else {
                                                    echo $log_date->format('l, F j, Y');
                                                }
                                                ?>
                                            </span>
                                            <div class="date-line"></div>
                                        </div>
                                    <?php endif; ?>

                                    <div class="timeline-item <?= $is_last ? 'timeline-last' : '' ?>">
                                        <div class="timeline-connector">
                                            <div class="timeline-dot" style="background: <?= $meta['bg'] ?>; border-color: <?= $meta['color'] ?>;">
                                                <i class="<?= $meta['icon'] ?>" style="color: <?= $meta['color'] ?>;"></i>
                                            </div>
                                            <?php if (!$is_last): ?>
                                                <div class="timeline-line"></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="timeline-body">
                                            <div class="timeline-top">
                                                <span class="timeline-type-badge" style="background: <?= $meta['bg'] ?>; color: <?= $meta['color'] ?>;">
                                                    <?= $meta['label'] ?>
                                                </span>
                                                <span class="timeline-time"><?= $log_date->format('h:i A') ?></span>
                                            </div>

                                            <!-- MODIFIED ACTIVITY SECTION -->
                                            <p class="timeline-activity">
                                                <?php
                                                // CHANGE: We now check for 'Created', 'Updated', 'Archived', 'Restored', AND 'Renewed'
                                                if ($meta['label'] === 'Created' || $meta['label'] === 'Updated' || $meta['label'] === 'Archived' || $meta['label'] === 'Restored' || $meta['label'] === 'Renewed'):
                                                ?>
                                                    <?php
                                                    // STRICT CHANGE: Only look for contract_id
                                                    $contract_id = $log['contract_id'] ?? '';

                                                    // FIX: Use 'log_id' as fallback to prevent Undefined Index error
                                                    $fallback_id = $log['log_id'] ?? '';

                                                    // 1. Determine Target URL
                                                    // If 'Archived', link to view_archived_contract.php
                                                    if ($meta['label'] === 'Archived') {
                                                        $viewUrl = 'landowners_tabs/view_archived_contract.php?contract_id=' . urlencode($contract_id);
                                                    } else {
                                                        // Created or Updated -> link to normal view_contract.php
                                                        $viewUrl = 'landowners_tabs/view_contract.php?contract_id=' . urlencode($contract_id);
                                                    }

                                                                                                        // 2. Determine Link Style
                                                    // Apply the specific color code from $meta['color'] to ALL clickable links
                                                    // This ensures Created (Blue), Updated (Purple), Renewed (Teal), and Archived (Red) match their badge colors.
                                                    $linkStyle = 'style="color: ' . $meta['color'] . ';"';

                                                    // Only create link if contract_id is present
                                                    if (!empty($contract_id)):
                                                    ?>
                                                        <!-- Link target="_blank" -->
                                                        <!-- We use the variable $linkStyle here, so it is ONLY applied to Archived logs -->
                                                        <a href="<?= $viewUrl ?>" class="activity-link" <?= $linkStyle ?> title="View Contract Details" target="_blank" rel="noopener noreferrer">
                                                            <?= htmlspecialchars($log['activity']) ?> <i class='bx bx-link-external'></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <!-- Fallback link logic -->
                                                        <?php if ($meta['label'] === 'Archived'): ?>
                                                            <a href="landowners_tabs/view_archived_contract.php?contract_id=<?= urlencode($fallback_id) ?>" class="activity-link" <?= $linkStyle ?> title="View Contract Details" target="_blank" rel="noopener noreferrer">
                                                                <?= htmlspecialchars($log['activity']) ?> <i class='bx bx-link-external'></i>
                                                            </a>
                                                        <?php else: ?>
                                                            <a href="landowners_tabs/view_contract.php?contract_id=<?= urlencode($fallback_id) ?>" class="activity-link" <?= $linkStyle ?> title="View Contract Details" target="_blank" rel="noopener noreferrer">
                                                                <?= htmlspecialchars($log['activity']) ?> <i class='bx bx-link-external'></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <?= htmlspecialchars($log['activity']) ?>
                                                <?php endif; ?>
                                            </p>
                                            <!-- END MODIFIED SECTION -->

                                            <span class="timeline-relative"><?= relativeTime($log['dateAdded']) ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <div class="pagination">
                                    <?php if ($page > 1): ?>
                                        <a href="?page=<?= $page - 1 ?>" class="page-btn page-prev">
                                            <i class='bx bx-chevron-left'></i> Previous
                                        </a>
                                    <?php endif; ?>

                                    <div class="page-numbers">
                                        <?php
                                        $start = max(1, $page - 2);
                                        $end = min($total_pages, $page + 2);
                                        if ($start > 1) {
                                            echo '<a href="?page=1" class="page-num">1</a>';
                                            if ($start > 2) echo '<span class="page-dots">...</span>';
                                        }
                                        for ($p = $start; $p <= $end; $p++):
                                        ?>
                                            <a href="?page=<?= $p ?>" class="page-num <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
                                        <?php endfor;
                                        if ($end < $total_pages) {
                                            if ($end < $total_pages - 1) echo '<span class="page-dots">...</span>';
                                            echo '<a href="?page=' . $total_pages . '" class="page-num">' . $total_pages . '</a>';
                                        }
                                        ?>
                                    </div>

                                    <?php if ($page < $total_pages): ?>
                                        <a href="?page=<?= $page + 1 ?>" class="page-btn page-next">
                                            Next <i class='bx bx-chevron-right'></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- SIDEBAR COLUMN -->
                <div class="profile-sidebar">
                    <!-- Activity Summary Card -->
                    <div class="profile-card">
                        <div class="profile-card-header">
                            <div class="icon-box"><i class='bx bx-pie-chart-alt-2'></i></div>
                            <div class="header-content">
                                <h3>Summary</h3>
                                <p>Activity breakdown</p>
                            </div>
                        </div>

                        <?php if ($total_logs > 0): ?>
                            <!-- Visual Bar Chart -->
                            <div class="summary-bars">
                                <div class="summary-bar-item">
                                    <div class="summary-bar-header">
                                        <span class="summary-bar-label">Logins</span>
                                        <span class="summary-bar-value"><?= $login_count ?></span>
                                    </div>
                                    <div class="summary-bar-track">
                                        <div class="summary-bar-fill bar-login" style="width: <?= ($total_logs > 0) ? ($login_count / $total_logs * 100) : 0 ?>%"></div>
                                    </div>
                                </div>
                                <div class="summary-bar-item">
                                    <div class="summary-bar-header">
                                        <span class="summary-bar-label">Logouts</span>
                                        <span class="summary-bar-value"><?= $logout_count ?></span>
                                    </div>
                                    <div class="summary-bar-track">
                                        <div class="summary-bar-fill bar-logout" style="width: <?= ($total_logs > 0) ? ($logout_count / $total_logs * 100) : 0 ?>%"></div>
                                    </div>
                                </div>
                                <div class="summary-bar-item">
                                    <div class="summary-bar-header">
                                        <span class="summary-bar-label">Actions</span>
                                        <span class="summary-bar-value"><?= $action_count ?></span>
                                    </div>
                                    <div class="summary-bar-track">
                                        <div class="summary-bar-fill bar-action" style="width: <?= ($total_logs > 0) ? ($action_count / $total_logs * 100) : 0 ?>%"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Percentage Breakdown -->
                            <div class="summary-percentages">
                                <?php if ($login_count > 0): ?>
                                    <div class="summary-pct-item">
                                        <span class="pct-dot dot-login"></span>
                                        <span class="pct-label">Logins</span>
                                        <span class="pct-value"><?= round($login_count / $total_logs * 100, 1) ?>%</span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($logout_count > 0): ?>
                                    <div class="summary-pct-item">
                                        <span class="pct-dot dot-logout"></span>
                                        <span class="pct-label">Logouts</span>
                                        <span class="pct-value"><?= round($logout_count / $total_logs * 100, 1) ?>%</span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($action_count > 0): ?>
                                    <div class="summary-pct-item">
                                        <span class="pct-dot dot-action"></span>
                                        <span class="pct-label">Actions</span>
                                        <span class="pct-value"><?= round($action_count / $total_logs * 100, 1) ?>%</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="sidebar-empty">
                                <i class='bx bx-bar-chart-alt-2'></i>
                                <p>No data to display</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Timeline Info Card -->
                    <div class="profile-card">
                        <div class="profile-card-header">
                            <div class="icon-box"><i class='bx bx-info-circle'></i></div>
                            <div class="header-content">
                                <h3>Timeline Info</h3>
                                <p>Your activity metadata</p>
                            </div>
                        </div>
                        <div class="meta-list">
                            <div class="meta-item">
                                <i class='bx bx-calendar-check'></i>
                                <div class="meta-content">
                                    <div class="meta-label">First Activity</div>
                                    <div class="meta-value">
                                        <?php
                                        if ($first_activity && $first_activity !== '0000-00-00 00:00:00') {
                                            $d = new DateTime($first_activity);
                                            echo $d->format('M d, Y h:i A');
                                        } else {
                                            echo "N/A";
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                            <div class="meta-item">
                                <i class='bx bx-clock'></i>
                                <div class="meta-content">
                                    <div class="meta-label">Latest Activity</div>
                                    <div class="meta-value">
                                        <?php
                                        if ($last_activity && $last_activity !== '0000-00-00 00:00:00') {
                                            $d = new DateTime($last_activity);
                                            echo $d->format('M d, Y h:i A');
                                        } else {
                                            echo "N/A";
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                            <div class="meta-item">
                                <i class='bx bx-calendar'></i>
                                <div class="meta-content">
                                    <div class="meta-label">Activity Span</div>
                                    <div class="meta-value">
                                        <?php
                                        if ($first_activity && $last_activity && $first_activity !== '0000-00-00 00:00:00') {
                                            $d1 = new DateTime($first_activity);
                                            $d2 = new DateTime($last_activity);
                                            $span = $d1->diff($d2);
                                            $span_text = '';
                                            if ($span->y > 0) $span_text .= $span->y . 'y ';
                                            if ($span->m > 0) $span_text .= $span->m . 'm ';
                                            if ($span->d > 0) $span_text .= $span->d . 'd';
                                            echo $span_text ?: 'Same day';
                                        } else {
                                            echo "N/A";
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                            <div class="meta-item">
                                <i class='bx bx-hash'></i>
                                <div class="meta-content">
                                    <div class="meta-label">Total Events</div>
                                    <div class="meta-value"><?= number_format($total_logs) ?></div>
                                </div>
                            </div>
                            <div class="meta-item">
                                <i class='bx bx-show'></i>
                                <div class="meta-content">
                                    <div class="meta-label">Showing Page</div>
                                    <div class="meta-value"><?= $page ?> of <?= max(1, $total_pages) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </section>

    <script src="js/user_profile.js"></script>

    <script>
        // Theme Sync
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            document.body.classList.add('dark');
        } else {
            document.body.classList.remove('dark');
        }
    </script>
</body>

</html>