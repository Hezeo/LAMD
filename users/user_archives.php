<?php
require_once '../config.php';
require_once '../config_session.php';
checkLogin();
requirePermission('Land Owners');

// --- ADD THIS MISSING BLOCK ---
// --- FETCH USER DATA (for display) ---
try {
    $stmt = $pdo->prepare("SELECT u.*, r.role_name FROM tbl_users u LEFT JOIN tbl_roles r ON u.role_id = r.role_id WHERE u.user_id = ?");
    $stmt->execute([getCurrentUserId()]);
    $user = $stmt->fetch();
    if (!$user) die("User not found");

    $full_name = trim($user['first_name'] . ' ' . ($user['middle_initial'] ? $user['middle_initial'] . '.' : '') . ' ' . $user['last_name']);
    $full_name = preg_replace('/\s+/', ' ', $full_name);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
// ------------------------------

// --- 1. FETCH ARCHIVED CONTRACTS ---
 $searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';

try {
    $sql = "SELECT 
                contract_id, 
                contracting_party, 
                cms_application_no, 
                vendor, 
                start_date, 
                expiry_date, 
                paid_up_date, 
                lease_status,
                MAX(date_added) as date_archived 
            FROM tbl_archived_contracts 
            WHERE contracting_party LIKE :search 
               OR cms_application_no LIKE :search
            GROUP BY contract_id 
            ORDER BY date_archived DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['search' => "%$searchTerm%"]);
    $contracts = $stmt->fetchAll();
    
    $total_contracts = count($contracts);

    // --- 2. CALCULATE STATS FOR SIDEBAR ---
    $expired_count = 0;
    $near_expiration_count = 0;
    $other_count = 0;
    $oldest_archive = null;
    $newest_archive = null;

    foreach ($contracts as $c) {
        // Count statuses
        if (stripos($c['lease_status'], 'Expired') !== false) {
            $expired_count++;
        } elseif (stripos($c['lease_status'], 'Near Expiration') !== false) {
            $near_expiration_count++;
        } else {
            $other_count++;
        }

        // Track dates
        $this_date = strtotime($c['date_archived']);
        if (!$oldest_archive || $this_date < $oldest_archive) $oldest_archive = $this_date;
        if (!$newest_archive || $this_date > $newest_archive) $newest_archive = $this_date;
    }

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="icon" href="../../images/web_icon.png" type="image-x-icon" />
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <!-- Using same CSS as user_history to match theme perfectly -->
    <link rel="stylesheet" href="css/user_history.css">
    <title>Archived Contracts | Land Asset Management</title>

    <style>
        /* --- CUSTOM STYLES FOR CONTRACTS GRID --- */
        /* These override or extend user_history.css specifically for this page */

        .contracts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
            padding: 10px; /* Inner padding inside card */
        }

        .contract-card {
            background: #fff;
            border: 1px solid #eceff1;
            border-radius: 8px;
            padding: 15px;
            transition: transform 0.2s, box-shadow 0.2s;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .contract-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            border-color: #d0d7de;
        }

        .card-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 12px;
            padding-bottom: 10px;
            border-bottom: 1px solid #f1f3f5;
        }

        .card-title {
            font-size: 1rem;
            font-weight: 700;
            color: #2c3e50;
            margin: 0;
            line-height: 1.2;
            
            /* --- FIX FOR CSS ERROR --- */
            display: -webkit-box;
            -webkit-box-orient: vertical;
            overflow: hidden;
            -webkit-line-clamp: 2; /* Vendor prefix for Safari */
            line-clamp: 2;        /* Standard property to fix Linter error */
        }

        .badge-archived {
            background-color: #ffebee;
            color: #c62828;
            font-size: 0.7rem;
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: 700;
            text-transform: uppercase;
            flex-shrink: 0;
            white-space: nowrap;
        }

        .info-grid-mini {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 15px;
            font-size: 0.85rem;
        }

        .info-item-mini {
            display: flex;
            flex-direction: column;
        }

        .info-label-mini {
            font-size: 0.7rem;
            color: #90a4ae;
            font-weight: 600;
            margin-bottom: 2px;
            text-transform: uppercase;
        }

        .info-value-mini {
            color: #546e7a;
            font-weight: 500;
            word-break: break-word;
        }

        .card-footer {
            margin-top: auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 10px;
            border-top: 1px solid #f1f3f5;
        }

        .date-meta {
            font-size: 0.75rem;
            color: #b0bec5;
        }

        .btn-view {
            background-color: #2E7D32; /* Using primary green */
            color: white;
            text-decoration: none;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: background 0.2s;
            border: none;
            cursor: pointer;
        }

        .btn-view:hover {
            background-color: #1b5e20;
        }

        /* Search container styling to match theme */
        .search-container {
            background: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border: 1px solid #eceff1;
        }

        .search-input {
            flex: 1;
            padding: 8px 12px;
            border: 1px solid #eceff1;
            border-radius: 4px;
            font-size: 0.9rem;
            outline: none;
            transition: border 0.2s;
        }

        .search-input:focus {
            border-color: #2E7D32;
        }

        .btn-search {
            background-color: #2E7D32;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .btn-search:hover {
            background-color: #1b5e20;
        }





        
        /* ==========================================
       1. LIGHT MODE STYLES (Default)
       ========================================== */
        
        /* --- CUSTOM STYLES FOR CONTRACTS GRID --- */
        .contracts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
            padding: 10px;
        }

        .contract-card {
            background: #fff;
            border: 1px solid #eceff1;
            border-radius: 8px;
            padding: 15px;
            transition: transform 0.2s, box-shadow 0.2s;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .contract-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            border-color: #d0d7de;
        }

        .card-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 12px;
            padding-bottom: 10px;
            border-bottom: 1px solid #f1f3f5;
        }

        .card-title {
            font-size: 1rem;
            font-weight: 700;
            color: #2c3e50;
            margin: 0;
            line-height: 1.2;
            display: -webkit-box;
            -webkit-box-orient: vertical;
            overflow: hidden;
            -webkit-line-clamp: 2;
            line-clamp: 2;
        }

        .badge-archived {
            background-color: #ffebee;
            color: #c62828;
            font-size: 0.7rem;
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: 700;
            text-transform: uppercase;
            flex-shrink: 0;
            white-space: nowrap;
        }

        .info-grid-mini {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 15px;
            font-size: 0.85rem;
        }

        .info-item-mini {
            display: flex;
            flex-direction: column;
        }

        .info-label-mini {
            font-size: 0.7rem;
            color: #90a4ae;
            font-weight: 600;
            margin-bottom: 2px;
            text-transform: uppercase;
        }

        .info-value-mini {
            color: #546e7a;
            font-weight: 500;
            word-break: break-word;
        }

        .card-footer {
            margin-top: auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 10px;
            border-top: 1px solid #f1f3f5;
        }

        .date-meta {
            font-size: 0.75rem;
            color: #b0bec5;
        }

        .btn-view {
            background-color: #2E7D32;
            color: white;
            text-decoration: none;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: background 0.2s;
            border: none;
            cursor: pointer;
        }

        .btn-view:hover {
            background-color: #1b5e20;
        }

        /* Search container styling */
        .search-container {
            background: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border: 1px solid #eceff1;
        }

        .search-input {
            flex: 1;
            padding: 8px 12px;
            border: 1px solid #eceff1;
            border-radius: 4px;
            font-size: 0.9rem;
            outline: none;
            transition: border 0.2s;
            background: #fff;
            color: #333;
        }

        .search-input:focus {
            border-color: #2E7D32;
        }

        .btn-search {
            background-color: #2E7D32;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .btn-search:hover {
            background-color: #1b5e20;
        }

        /* ==========================================
       2. DARK MODE STYLES (FORCED APPLY)
       ========================================== */

        /* 1. Force Profile Card Container to be Dark (In case variables fail) */
        body.dark .profile-card {
            background-color: #0a1f12 !important;
            border-color: rgba(255, 255, 255, 0.05) !important;
        }

        /* 2. Search Container & Input */
        body.dark .search-container {
            background-color: rgba(0, 0, 0, 0.25) !important;
            border-color: rgba(46, 204, 113, 0.2) !important;
        }

        body.dark .search-container i {
            color: #a9c7ac !important;
        }

        body.dark .search-input {
            background-color: rgba(0, 0, 0, 0.4) !important; /* Force Dark Input */
            border-color: rgba(46, 204, 113, 0.15) !important;
            color: #e8f5e9 !important; /* Force Light Text */
        }

        body.dark .search-input::placeholder {
            color: rgba(169, 199, 172, 0.5) !important;
        }

        body.dark .search-input:focus {
            border-color: #2ecc71 !important;
            background-color: rgba(0, 0, 0, 0.5) !important;
        }

        body.dark .btn-search {
            background-color: #2ecc71 !important;
            color: #0b1a12 !important;
        }

        body.dark .btn-search:hover {
            background-color: #27ae60 !important;
        }

        /* 3. Contract Cards */
        body.dark .contract-card {
            background-color: rgba(0, 0, 0, 0.25) !important; /* Force Dark Card */
            border-color: rgba(46, 204, 113, 0.15) !important;
        }

        body.dark .contract-card:hover {
            border-color: rgba(46, 204, 113, 0.4) !important;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.5) !important;
        }

        /* 4. Card Header & Title */
        body.dark .card-header {
            border-bottom-color: rgba(255, 255, 255, 0.05) !important;
        }

        body.dark .card-title {
            color: #e8f5e9 !important; /* Light Green Title */
        }

        body.dark .badge-archived {
            background-color: rgba(183, 28, 28, 0.25) !important; /* Dark Red BG */
            color: #ef9a9a !important; /* Light Red Text */
            border-color: rgba(183, 28, 28, 0.3) !important;
        }

        /* 5. Info Grid Labels & Values */
        body.dark .info-label-mini {
            color: rgba(169, 199, 172, 0.6) !important; /* Muted Label */
        }

        body.dark .info-value-mini {
            color: #a9c7ac !important; /* Secondary Green Text */
        }

        /* Override hard-coded red for Lease Status */
        body.dark .info-value-mini[style*="color: #e74c3c"] {
            color: #ef9a9a !important; 
        }

        /* 6. Card Footer & Date */
        body.dark .card-footer {
            border-top-color: rgba(255, 255, 255, 0.05) !important;
        }

        body.dark .date-meta {
            color: rgba(169, 199, 172, 0.5) !important;
        }

        /* 7. Buttons */
        body.dark .btn-view {
            background-color: #2ecc71 !important;
            color: #0b1a12 !important;
        }

        body.dark .btn-view:hover {
            background-color: #27ae60 !important;
        }

        /* 8. Clear Button (Inline Style Override) */
        body.dark .btn-view[style*="background: #eee"] {
            background-color: rgba(255, 255, 255, 0.1) !important;
            color: #e8f5e9 !important;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        body.dark .btn-view[style*="background: #eee"]:hover {
            background-color: rgba(255, 255, 255, 0.2) !important;
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

    <section id="content">
        <?php include 'components/user_navbar.php'; ?>

        <main>
            <!-- Page Header -->
            <div class="page-header">
                <h1>Archived Contracts</h1>
                <p>View and manage previously removed contracts</p>
            </div>

            <div class="profile-container">
                <!-- MAIN COLUMN -->
                <div class="profile-main">
                    
                    <div class="hero-meta">
                        <span class="hero-badge badge-total">
                            <i class='bx bxs-bar-chart-alt-2'></i>
                            <?= number_format($total_contracts) ?> Total Archived
                        </span>
                        <?php if ($newest_archive): ?>
                            <span class="hero-badge badge-last">
                                <i class='bx bx-time-five'></i>
                                Latest: <?= date('M d', $newest_archive) ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Stats Row (Mimicking User History stats) -->
                    <div class="stats-row">
                        <div class="stat-card stat-login" style="--stat-color: #e74c3c;">
                            <div class="stat-icon"><i class='bx bx-x-circle'></i></div>
                            <div class="stat-info">
                                <div class="stat-number"><?= number_format($expired_count) ?></div>
                                <div class="stat-label">Expired</div>
                            </div>
                        </div>
                        <div class="stat-card stat-logout" style="--stat-color: #f39c12;">
                            <div class="stat-icon"><i class='bx bx-time'></i></div>
                            <div class="stat-info">
                                <div class="stat-number"><?= number_format($near_expiration_count) ?></div>
                                <div class="stat-label">Near Exp.</div>
                            </div>
                        </div>
                        <div class="stat-card stat-action" style="--stat-color: #2E7D32;">
                            <div class="stat-icon"><i class='bx bx-archive-in'></i></div>
                            <div class="stat-info">
                                <div class="stat-number"><?= number_format($other_count) ?></div>
                                <div class="stat-label">Others</div>
                            </div>
                        </div>
                    </div>

                    <!-- Main Contract List Card -->
                    <div class="profile-card">
                        <div class="profile-card-header">
                            <div class="icon-box"><i class='bx bx-archive'></i></div>
                            <div class="header-content">
                                <h3>Contract Archive</h3>
                                <p>List of all removed contracts</p>
                            </div>
                        </div>

                        <!-- Search Bar -->
                        <form action="" method="GET" class="search-container">
                            <i class='bx bx-search' style="font-size: 1.2rem; color: #90a4ae;"></i>
                            <!-- CHANGED: Added ID here for JS access -->
                            <input type="text" id="searchInput" name="search" class="search-input" placeholder="Search by Party or CMS No..." value="<?= htmlspecialchars($searchTerm) ?>">
                            <button type="submit" class="btn-search">Search</button>
                            
                            <!-- CHANGED: Converted to Button with JS onclick -->
                            <?php if (!empty($searchTerm)): ?>
                                <button type="button" class="btn-view" style="background: #eee; color: #333;" onclick="clearSearch()">Clear</button>
                            <?php endif; ?>
                        </form>

                        <?php if (empty($contracts)): ?>
                            <div class="empty-state">
                                <div class="empty-icon"><i class='bx bx-box'></i></div>
                                <h4>No archived contracts found</h4>
                                <p><?= !empty($searchTerm) ? 'Try adjusting your search terms.' : 'The archive is currently empty.' ?></p>
                            </div>
                        <?php else: ?>
                            
                            <div class="contracts-grid">
                                <?php foreach ($contracts as $c): ?>
                                    <div class="contract-card">
                                        <div>
                                            <div class="card-header">
                                                <h3 class="card-title">
                                                    <?= htmlspecialchars($c['contracting_party']) ?>
                                                </h3>
                                                <span class="badge-archived">
                                                    <i class='bx bx-archive'></i> Archived
                                                </span>
                                            </div>

                                            <div class="info-grid-mini">
                                                <div class="info-item-mini">
                                                    <span class="info-label-mini">CMS No.</span>
                                                    <span class="info-value-mini"><?= htmlspecialchars($c['cms_application_no']) ?></span>
                                                </div>
                                                <div class="info-item-mini">
                                                    <span class="info-label-mini">Vendor ID</span>
                                                    <span class="info-value-mini"><?= htmlspecialchars($c['vendor']) ?></span>
                                                </div>
                                                <div class="info-item-mini">
                                                    <span class="info-label-mini">Start Date</span>
                                                    <span class="info-value-mini"><?= htmlspecialchars($c['start_date']) ?></span>
                                                </div>
                                                <div class="info-item-mini">
                                                    <span class="info-label-mini">Expiry Date</span>
                                                    <span class="info-value-mini"><?= htmlspecialchars($c['expiry_date']) ?></span>
                                                </div>
                                                <div class="info-item-mini">
                                                    <span class="info-label-mini">Lease Status</span>
                                                    <span class="info-value-mini" style="color: #e74c3c;"><?= htmlspecialchars($c['lease_status']) ?></span>
                                                </div>
                                                <div class="info-item-mini">
                                                    <span class="info-label-mini">Paid Up</span>
                                                    <span class="info-value-mini"><?= htmlspecialchars($c['paid_up_date']) ?: 'N/A' ?></span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="card-footer">
                                            <span class="date-meta">
                                                Archived: <?= date('M d, Y', strtotime($c['date_archived'])) ?>
                                            </span>
                                            
                                            <!-- Link relative to same folder -->
                                            <a href="../users/landowners_tabs/view_archived_contract.php?contract_id=<?= htmlspecialchars($c['contract_id']) ?>" 
                                               class="btn-view" 
                                               target="_blank"
                                               title="View Contract Details">
                                                <i class='bx bx-show'></i> View
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                        <?php endif; ?>
                    </div>
                </div>

                <!-- SIDEBAR COLUMN -->
                <div class="profile-sidebar">
                    <!-- Summary Card (Status Breakdown) -->
                    <div class="profile-card">
                        <div class="profile-card-header">
                            <div class="icon-box"><i class='bx bx-pie-chart-alt-2'></i></div>
                            <div class="header-content">
                                <h3>Status Breakdown</h3>
                                <p>Distribution of leases</p>
                            </div>
                        </div>

                        <?php if ($total_contracts > 0): ?>
                            <!-- Visual Bar Chart -->
                            <div class="summary-bars">
                                <div class="summary-bar-item">
                                    <div class="summary-bar-header">
                                        <span class="summary-bar-label">Expired</span>
                                        <span class="summary-bar-value"><?= $expired_count ?></span>
                                    </div>
                                    <div class="summary-bar-track">
                                        <div class="summary-bar-fill bar-login" style="background-color: #e74c3c; width: <?= ($total_contracts > 0) ? ($expired_count / $total_contracts * 100) : 0 ?>%"></div>
                                    </div>
                                </div>
                                <div class="summary-bar-item">
                                    <div class="summary-bar-header">
                                        <span class="summary-bar-label">Near Exp.</span>
                                        <span class="summary-bar-value"><?= $near_expiration_count ?></span>
                                    </div>
                                    <div class="summary-bar-track">
                                        <div class="summary-bar-fill bar-logout" style="background-color: #f39c12; width: <?= ($total_contracts > 0) ? ($near_expiration_count / $total_contracts * 100) : 0 ?>%"></div>
                                    </div>
                                </div>
                                <div class="summary-bar-item">
                                    <div class="summary-bar-header">
                                        <span class="summary-bar-label">Others</span>
                                        <span class="summary-bar-value"><?= $other_count ?></span>
                                    </div>
                                    <div class="summary-bar-track">
                                        <div class="summary-bar-fill bar-action" style="background-color: #2E7D32; width: <?= ($total_contracts > 0) ? ($other_count / $total_contracts * 100) : 0 ?>%"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Percentage Breakdown -->
                            <div class="summary-percentages">
                                <?php if ($expired_count > 0): ?>
                                    <div class="summary-pct-item">
                                        <span class="pct-dot dot-login" style="background-color: #e74c3c;"></span>
                                        <span class="pct-label">Expired</span>
                                        <span class="pct-value"><?= round($expired_count / $total_contracts * 100, 1) ?>%</span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($near_expiration_count > 0): ?>
                                    <div class="summary-pct-item">
                                        <span class="pct-dot dot-logout" style="background-color: #f39c12;"></span>
                                        <span class="pct-label">Near Exp.</span>
                                        <span class="pct-value"><?= round($near_expiration_count / $total_contracts * 100, 1) ?>%</span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($other_count > 0): ?>
                                    <div class="summary-pct-item">
                                        <span class="pct-dot dot-action" style="background-color: #2E7D32;"></span>
                                        <span class="pct-label">Others</span>
                                        <span class="pct-value"><?= round($other_count / $total_contracts * 100, 1) ?>%</span>
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

                    <!-- Archive Info Card -->
                    <div class="profile-card">
                        <div class="profile-card-header">
                            <div class="icon-box"><i class='bx bx-info-circle'></i></div>
                            <div class="header-content">
                                <h3>Archive Info</h3>
                                <p>Contract metadata</p>
                            </div>
                        </div>
                        <div class="meta-list">
                            <div class="meta-item">
                                <i class='bx bx-calendar-check'></i>
                                <div class="meta-content">
                                    <div class="meta-label">Oldest Archive</div>
                                    <div class="meta-value">
                                        <?php
                                        if ($oldest_archive) {
                                            echo date('M d, Y', $oldest_archive);
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
                                    <div class="meta-label">Newest Archive</div>
                                    <div class="meta-value">
                                        <?php
                                        if ($newest_archive) {
                                            echo date('M d, Y', $newest_archive);
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
                                    <div class="meta-label">Archive Span</div>
                                    <div class="meta-value">
                                        <?php
                                        if ($oldest_archive && $newest_archive && $oldest_archive !== $newest_archive) {
                                            $d1 = new DateTime(date('Y-m-d', $oldest_archive));
                                            $d2 = new DateTime(date('Y-m-d', $newest_archive));
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
                                    <div class="meta-label">Total Archived</div>
                                    <div class="meta-value"><?= number_format($total_contracts) ?></div>
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
        // Theme Sync (same as user_history.php)
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            document.body.classList.add('dark');
        } else {
            document.body.classList.remove('dark');
        }

        // --- JAVASCRIPT CLEAR FUNCTION ---
        function clearSearch() {
            // 1. Clear the input field
            const input = document.getElementById('searchInput');
            if(input) {
                input.value = '';
            }

            // 2. Submit the form to reload page cleanly without query parameters
            // This ensures the list resets to show all contracts
            const form = document.querySelector('.search-container');
            if(form) {
                form.submit();
            }
        }
    </script>
</body>

</html>