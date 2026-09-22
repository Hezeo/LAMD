<?php
session_start();
require_once '../config.php'; 

// 1. Security Check
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_login.php");
    exit();
}

// 2. Fetch Statistics
try {
    // --- CORE USER STATS ---
    // Total Users
    $stmt_total = $pdo->query("SELECT COUNT(*) as total from tbl_users");
    $total_users = $stmt_total->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // Total Roles
    $stmt_roles = $pdo->query("SELECT COUNT(*) as total from tbl_roles");
    $total_roles = $stmt_roles->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // Total Permissions/Modules
    $stmt_perms = $pdo->query("SELECT COUNT(*) as total from tbl_permissions");
    $total_perms = $stmt_perms->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // Total Distinct Departments
    $stmt_dept = $pdo->query("SELECT COUNT(DISTINCT department) as count FROM tbl_users WHERE department IS NOT NULL AND department != ''");
    $total_departments = $stmt_dept->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // --- ADMIN ANALYTICS (Adapted to your schema) ---

    // 1. Users per Department (Top 5)
    $stmt_users_dept = $pdo->query("SELECT department, COUNT(*) as count FROM tbl_users WHERE department IS NOT NULL AND department != '' GROUP BY department ORDER BY count DESC LIMIT 5");
    $users_by_dept = $stmt_users_dept->fetchAll(PDO::FETCH_ASSOC);

    // 2. Role Distribution
    // Join tbl_roles with tbl_users on role_id
    $stmt_role_dist = $pdo->query("SELECT r.role_name, COUNT(u.user_id) as count FROM tbl_roles r LEFT JOIN tbl_users u ON r.role_id = u.role_id GROUP BY r.role_id ORDER BY count DESC");
    $role_distribution = $stmt_role_dist->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error fetching statistics: " . $e->getMessage());
}

// Helper to calculate percentage for bars
function getPercentage($count, $total) {
    return $total > 0 ? round(($count / $total) * 100) : 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="icon" href="../images/web_icon.png" type="image/x-icon" />
    <!-- Boxicons -->
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <!-- Google Font Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- My CSS -->
    <link rel="stylesheet" href="css/admin_dashboard.css">
    <title>Admin Dashboard | LAMD</title>
</head>
<body>
    <!-- SIDEBAR -->
    <section id="sidebar">
        <a href="#" class="brand">
            <img src="../images/web_icon.png" alt="LAND ASSET MANAGEMENT"
                style="height: 40px; width: auto; object-fit: contain; margin-right: 10px;">
            <span class="text" style="display: grid;">LAND ASSET MANAGEMENT DEPARTMENT</span>
        </a>
        <ul class="side-menu top">
            <li class="active">
                <a href="admin_dashboard.php">
                    <i class='bx bxs-dashboard bx-sm'></i>
                    <span class="text">Dashboard</span>
                </a>
            </li>
            <li>
                <a href="admin_users.php">
                    <i class='bx bxs-group bx-sm'></i>
                    <span class="text">Manage Users</span>
                </a>
            </li>
            <li>
                <a href="admin_role_permissions.php">
                    <i class='bx bxs-lock-alt bx-sm'></i>
                    <span class="text">Roles & Access</span>
                </a>
            </li>
        </ul>
    </section>
    <!-- SIDEBAR -->

    <!-- CONTENT -->
    <section id="content">
        <!-- NAVBAR -->
        <nav>
            <i class='bx bx-menu bx-sm'></i>
            <form action="#"></form>
            <a href="#" class="profile" id="profileIcon">
                <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTtWEpbWZaMRKSthRD1G2AatWLDQeHG37Bw_w&s" alt="Profile">
            </a>
            <div class="profile-menu" id="profileMenu">
                <ul>
                    <li onclick="window.location.href='actions/logout.php';" style="cursor:pointer;">Log Out</li>
                </ul>
            </div>
        </nav>
        <!-- NAVBAR -->

        <!-- MAIN -->
        <main>
            <div class="page-header">
                <div class="left">
                    <h1>Admin Dashboard</h1>
                    <p>Manage users, roles, and system security.</p>
                </div>
            </div>

            <!-- Section 1: Quick Stats -->
            <div class="section-title">System Overview</div>
            <div class="stats-grid small-cards">
                <a href="admin_users.php" class="stat-card">
                    <div class="stat-icon primary"><i class='bx bxs-group'></i></div>
                    <div class="stat-info">
                        <h2><?php echo number_format($total_users); ?></h2>
                        <p>Total Users</p>
                    </div>
                </a>
                <a href="admin_users.php" class="stat-card">
                    <div class="stat-icon success"><i class='bx bxs-user-detail'></i></div>
                    <div class="stat-info">
                        <h2><?php echo number_format($total_departments); ?></h2>
                        <p>Departments</p>
                    </div>
                </a>
                <a href="admin_role_permissions.php" class="stat-card">
                    <div class="stat-icon warning"><i class='bx bxs-lock-alt'></i></div>
                    <div class="stat-info">
                        <h2><?php echo $total_roles; ?></h2>
                        <p>Roles Defined</p>
                    </div>
                </a>
                <a href="admin_role_permissions.php" class="stat-card">
                    <div class="stat-icon danger"><i class='bx bxs-key'></i></div>
                    <div class="stat-info">
                        <h2><?php echo $total_perms; ?></h2>
                        <p>Modules</p>
                    </div>
                </a>
            </div>

            <!-- Section 2: User Analytics -->
            <div class="section-title">User Analytics</div>
            <div class="analytics-grid">
                
                <!-- Left Column: Department Breakdown -->
                <div class="card table-card">
                    <div class="card-header">
                        <h3>Users by Department</h3>
                    </div>
                    <div class="table-content">
                        <table>
                            <thead>
                                <tr>
                                    <th>Department</th>
                                    <th>Users</th>
                                    <th>Distribution</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($users_by_dept): ?>
                                    <?php foreach($users_by_dept as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['department']); ?></td>
                                        <td><?php echo $row['count']; ?></td>
                                        <td>
                                            <div class="progress-bar-bg">
                                                <div class="progress-bar-fill" style="width: <?php echo getPercentage($row['count'], $total_users); ?>%"></div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" style="text-align:center;">No department data found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Right Column: Role Breakdown -->
                <div class="card">
                    <div class="card-header">
                        <h3>Role Distribution</h3>
                        <i class='bx bxs-pie-chart'></i>
                    </div>
                    <div class="visual-breakdown">
                        <?php if($role_distribution): ?>
                            <?php foreach($role_distribution as $role): ?>
                                <div class="breakdown-item">
                                    <div class="meta">
                                        <span class="label"><?php echo htmlspecialchars($role['role_name']); ?></span>
                                        <span class="count"><?php echo $role['count']; ?></span>
                                    </div>
                                    <div class="progress-bar-bg light">
                                        <div class="progress-bar-fill" style="width: <?php echo getPercentage($role['count'], $total_users); ?>%; background-color: #4361ee;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="color:#777; font-size:0.9rem;">No roles defined yet.</p>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </main>
        <!-- MAIN -->
    </section>
    <!-- CONTENT -->

    <script src="js/admin_dashboard.js"></script>
</body>
</html>


<!-- OLD -->

<!-- < ?php
session_start();
require_once '../config.php'; 

// 1. Security Check
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_login.php");
    exit();
}

// 2. Fetch Statistics
try {
    // --- USER STATS ---
    $stmt_total = $pdo->query("SELECT COUNT(*) as total from tbl_users");
    $total_users = $stmt_total->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    $stmt_roles = $pdo->query("SELECT COUNT(*) as total from tbl_roles");
    $total_roles = $stmt_roles->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    $stmt_perms = $pdo->query("SELECT COUNT(*) as total from tbl_permissions");
    $total_perms = $stmt_perms->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    $stmt_dept = $pdo->query("SELECT COUNT(DISTINCT department) as count FROM tbl_users WHERE department IS NOT NULL AND department != ''");
    $total_departments = $stmt_dept->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // --- LAND PORTFOLIO STATS ---
    // Total Contracts
    $stmt_contracts = $pdo->query("SELECT COUNT(*) as total FROM tbl_main");
    $total_contracts = $stmt_contracts->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // Total Hectares (Sum of contracted_arable)
    $stmt_hectares = $pdo->query("SELECT SUM(contracted_arable) as total FROM tbl_main");
    $total_hectares = $stmt_hectares->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // Expiring in 30 Days (Alert)
    $stmt_expiring = $pdo->query("SELECT COUNT(*) as count FROM tbl_main WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
    $expiring_soon = $stmt_expiring->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // Contract Status Breakdown (For Chart)
    $stmt_status = $pdo->query("SELECT contract_status, COUNT(*) as count FROM tbl_main GROUP BY contract_status");
    $contract_statuses = $stmt_status->fetchAll(PDO::FETCH_KEY_PAIR);

    // Lease Status Breakdown (For Chart)
    $stmt_lease = $pdo->query("SELECT lease_status, COUNT(*) as count FROM tbl_main GROUP BY lease_status");
    $lease_statuses = $stmt_lease->fetchAll(PDO::FETCH_KEY_PAIR);

    // Top Municipalities
    $stmt_muni = $pdo->query("SELECT municipality, COUNT(*) as count FROM tbl_main GROUP BY municipality ORDER BY count DESC LIMIT 5");
    $top_municipalities = $stmt_muni->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error fetching statistics: " . $e->getMessage());
}

// Helper to calculate percentage for bars
function getPercentage($count, $total) {
    return $total > 0 ? round(($count / $total) * 100) : 0;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="icon" href="../images/web_icon.png" type="image/x-icon" />
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/admin_dashboard.css">
    <title>Admin Dashboard | LAMD</title>
</head>

<body>
    <!-- SIDEBAR -- >
    <section id="sidebar">
        <a href="#" class="brand">
            <img src="../images/web_icon.png" alt="LAND ASSET MANAGEMENT"
                style="height: 40px; width: auto; object-fit: contain; margin-right: 10px;">
            <span class="text" style="display: grid;">LAND ASSET MANAGEMENT DEPARTMENT</span>
        </a>
        <ul class="side-menu top">
            <li class="active">
                <a href="admin_dashboard.php">
                    <i class='bx bxs-dashboard bx-sm'></i>
                    <span class="text">Dashboard</span>
                </a>
            </li>
            <li>
                <a href="admin_users.php">
                    <i class='bx bxs-group bx-sm'></i>
                    <span class="text">Manage Users</span>
                </a>
            </li>
            <li>
                <a href="admin_role_permissions.php">
                    <i class='bx bxs-lock-alt bx-sm'></i>
                    <span class="text">Roles & Access</span>
                </a>
            </li>
        </ul>
    </section>
    <!-- SIDEBAR -- >

    <!-- CONTENT -- >
    <section id="content">
        <!-- NAVBAR -- >
        <nav>
            <i class='bx bx-menu bx-sm'></i>
            <form action="#"></form>
            <a href="#" class="profile" id="profileIcon">
                <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTtWEpbWZaMRKSthRD1G2AatWLDQeHG37Bw_w&s" alt="Profile">
            </a>
            <div class="profile-menu" id="profileMenu">
                <ul>
                    <li onclick="window.location.href='actions/logout.php';" style="cursor:pointer;">Log Out</li>
                </ul>
            </div>
        </nav>
        <!-- NAVBAR -- >

        <!-- MAIN -- >
        <main>
            <div class="page-header">
                <div class="left">
                    <h1>Admin Dashboard</h1>
                    <p>Welcome back! Here is the system overview.</p>
                </div>
            </div>

            <!-- Section 1: System Overview (Users/Roles) -- >
            <div class="section-title">System Overview</div>
            <div class="stats-grid small-cards">
                <a href="admin_users.php" class="stat-card">
                    <div class="stat-icon primary"><i class='bx bxs-group'></i></div>
                    <div class="stat-info">
                        <h2><?php echo number_format($total_users); ?></h2>
                        <p>Total Users</p>
                    </div>
                </a>
                <a href="admin_role_permissions.php" class="stat-card">
                    <div class="stat-icon success"><i class='bx bxs-lock-alt'></i></div>
                    <div class="stat-info">
                        <h2><?php echo $total_roles; ?></h2>
                        <p>Roles</p>
                    </div>
                </a>
                <a href="admin_role_permissions.php" class="stat-card">
                    <div class="stat-icon warning"><i class='bx bxs-key'></i></div>
                    <div class="stat-info">
                        <h2><?php echo $total_perms; ?></h2>
                        <p>Modules</p>
                    </div>
                </a>
                <a href="admin_users.php" class="stat-card">
                    <div class="stat-icon danger"><i class='bx bxs-buildings'></i></div>
                    <div class="stat-info">
                        <h2><?php echo $total_departments; ?></h2>
                        <p>Departments</p>
                    </div>
                </a>
            </div>

            <!-- Section 2: Land Portfolio Analytics -- >
            <div class="section-title">Land Portfolio Analytics</div>
            
            <div class="analytics-grid">
                <!-- Left Column: Main Stats -- >
                <div class="main-stats-col">
                    <!-- Top Row: Key Metrics -- >
                    <div class="stats-grid">
                        <div class="stat-card dark">
                            <div class="stat-icon"><i class='bx bxs-file-doc'></i></div>
                            <div class="stat-info">
                                <h2><?php echo number_format($total_contracts); ?></h2>
                                <p>Total Contracts</p>
                            </div>
                        </div>
                        <div class="stat-card dark">
                            <div class="stat-icon"><i class='bx bxs-map'></i></div>
                            <div class="stat-info">
                                <h2><?php echo number_format($total_hectares, 2); ?></h2>
                                <p>Total Hectares</p>
                            </div>
                        </div>
                    </div>

                    <!-- Alert Card -- >
                    <?php if ($expiring_soon > 0): ?>
                    <div class="alert-card">
                        <div class="alert-icon"><i class='bx bxs-bell-ring bx-tada'></i></div>
                        <div class="alert-info">
                            <h4>Action Required</h4>
                            <p><strong><?php echo $expiring_soon; ?></strong> contracts are expiring within the next 30 days.</p>
                        </div>
                        <a href="#" class="btn-alert">View List</a>
                    </div>
                    <?php else: ?>
                    <div class="alert-card success">
                        <div class="alert-icon"><i class='bx bxs-check-circle'></i></div>
                        <div class="alert-info">
                            <h4>All Clear</h4>
                            <p>No contracts expiring in the next 30 days.</p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Top Municipalities Table -- >
                    <div class="card table-card">
                        <div class="card-header">
                            <h3>Top Municipalities</h3>
                        </div>
                        <div class="table-content">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Municipality</th>
                                        <th>Count</th>
                                        <th>Share</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if($top_municipalities): ?>
                                        <?php foreach($top_municipalities as $muni): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($muni['municipality']); ?></td>
                                            <td><?php echo $muni['count']; ?></td>
                                            <td>
                                                <div class="progress-bar-bg">
                                                    <div class="progress-bar-fill" style="width: <?php echo getPercentage($muni['count'], $total_contracts); ?>%"></div>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="3">No data found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Charts/Breakdowns -- >
                <div class="charts-col">
                    
                    <!-- Contract Status Breakdown -- >
                    <div class="card">
                        <div class="card-header">
                            <h3>Contract Status</h3>
                            <i class='bx bxs-pie-chart'></i>
                        </div>
                        <div class="visual-breakdown">
                            <?php foreach($contract_statuses as $status => $count): ?>
                                <?php 
                                    // Dynamic Color Logic
                                    $color = '#64748b'; // default gray
                                    if (strtolower($status) == 'active') $color = '#10b981'; // green
                                    if (strtolower($status) == 'expired') $color = '#ef4444'; // red
                                    if (strtolower($status) == 'pending') $color = '#f59e0b'; // yellow
                                ?>
                                <div class="breakdown-item">
                                    <div class="meta">
                                        <span class="label"><?php echo htmlspecialchars($status); ?></span>
                                        <span class="count"><?php echo $count; ?></span>
                                    </div>
                                    <div class="progress-bar-bg light">
                                        <div class="progress-bar-fill" style="width: <?php echo getPercentage($count, $total_contracts); ?>%; background-color: <?php echo $color; ?>;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if(empty($contract_statuses)): ?>
                                <p style="color:#777; font-size:0.9rem;">No data available</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Lease Status Breakdown -- >
                    <div class="card">
                        <div class="card-header">
                            <h3>Lease Status</h3>
                            <i class='bx bxs-bar-chart-alt-2'></i>
                        </div>
                        <div class="visual-breakdown">
                             <?php foreach($lease_statuses as $status => $count): ?>
                                <div class="breakdown-item">
                                    <div class="meta">
                                        <span class="label"><?php echo htmlspecialchars($status); ?></span>
                                        <span class="count"><?php echo $count; ?></span>
                                    </div>
                                    <div class="progress-bar-bg light">
                                        <!-- Randomizing colors for variety if not standard -- >
                                        <div class="progress-bar-fill" style="width: <?php echo getPercentage($count, $total_contracts); ?>%; background-color: #4361ee;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if(empty($lease_statuses)): ?>
                                <p style="color:#777; font-size:0.9rem;">No data available</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        </main>
        <!-- MAIN -- >
    </section>
    <!-- CONTENT -- >

    <script src="js/admin_dashboard.js"></script>
</body>
</html> -->