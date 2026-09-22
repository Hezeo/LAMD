<?php
require '../config.php'; // your PDO connection file
require_once '../config_session.php';
checkLogin();

// ADD THIS LINE: Block access if they don't have 'Expiry Tracker' permission
requirePermission('Expiry Tracker'); 

 $user_id = getCurrentUserId();
 $message = '';
 $error = '';

// --- NEW: CHECK IF USER HAS EDIT PERMISSION ---
// Default to false
 $can_edit_expiry = false; 

if (!empty($_SESSION['user_permissions']) && isset($_SESSION['user_permissions']['Expiry Tracker'])) {
    $perm_data = $_SESSION['user_permissions']['Expiry Tracker'];
    // Check if 'can' is 'edit'
    $access_level = is_array($perm_data) ? ($perm_data['can'] ?? '') : $perm_data;
    if ($access_level === 'edit') {
        $can_edit_expiry = true;
    }
}
// ----------------------------------------------

// 1. Prepare and Execute the SQL query first
 $sql = "SELECT 
    id,
    contract_id,
    cms_application_no,
    vendor,
    contracting_party,
    group_acc,
    lot_no,
    field_no,
    field_section,
    barangay,
    municipality,
    province,
    contracted_arable,
    start_date,
    expiry_date,
    paid_up_date,
    rate,
    crop_type,
    lease_status,
    contract_status,
    contract_class 
FROM tbl_main 
ORDER BY start_date DESC";

 $stmt = $pdo->prepare($sql);
 $stmt->execute();
 $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ... (Keep your existing grouping and sorting logic exactly as it is) ...
// 2. Define the helper function
function statusClass($status)
{
    return strtolower(str_replace([' ', '-'], '', $status));
}

// 3. Now perform the grouping logic
 $groupedRows = [];
foreach ($rows as $row) {
    $key = $row['contracting_party'] . '|' . $row['lease_status'];
    if (!isset($groupedRows[$key])) {
        $groupedRows[$key] = [
            'display' => $row,
            'details' => []
        ];
    }
    $groupedRows[$key]['details'][] = $row;
}

// =============================================================
// --- SORTING LOGIC (PRIORITY: NEAR EXPIRATION > EXPIRED > ACTIVE) ---
// =============================================================
 $statusPriority = [
    'Near Expiration' => 1,
    'Expired' => 2,
    'Active' => 3
];
usort($groupedRows, function ($a, $b) use ($statusPriority) {
    $statusA = $a['display']['lease_status'];
    $statusB = $b['display']['lease_status'];
    $prioA = isset($statusPriority[$statusA]) ? $statusPriority[$statusA] : 99;
    $prioB = isset($statusPriority[$statusB]) ? $statusPriority[$statusB] : 99;
    if ($prioA != $prioB) {
        return $prioA - $prioB;
    }
    $dateA = strtotime($a['display']['start_date']);
    $dateB = strtotime($b['display']['start_date']);
    return $dateB - $dateA;
});
// =============================================================
// --- END SORTING LOGIC ---
// =============================================================


// ... (Keep your existing filter year logic) ...
//FILTER
 $filter_years = [
    'start' => ['fy' => [], 'cy' => []],
    'paid' => ['fy' => [], 'cy' => []],
    'expiry' => ['fy' => [], 'cy' => []]
];

function getFiscalYearString($dateStr)
{
    if (empty($dateStr) || $dateStr === '0000-00-00')
        return null;
    if ($dateStr < '2014-05-01')
        return date('Y', strtotime($dateStr));
    $timestamp = strtotime($dateStr);
    $year = (int) date('Y', $timestamp);
    $month = (int) date('m', $timestamp);
    
    // --- UPDATED: START YEAR LOGIC ---
    // If Month is Jan-Apr (<=4), FY started last year.
    // If Month is May-Dec (>4), FY started this year.
    $fyStartYear = ($month <= 4) ? $year - 1 : $year;
    return 'FY-' . substr($fyStartYear, -2);
}

function getCalendarYearString($dateStr)
{
    if (empty($dateStr) || $dateStr === '0000-00-00')
        return null;
    return date('Y', strtotime($dateStr));
}

foreach ($rows as $row) {
    $fyS = getFiscalYearString($row['start_date']);
    $cyS = getCalendarYearString($row['start_date']);
    if ($fyS)
        $filter_years['start']['fy'][] = $fyS;
    if ($cyS)
        $filter_years['start']['cy'][] = $cyS;

    $fyP = getFiscalYearString($row['paid_up_date']);
    $cyP = getCalendarYearString($row['paid_up_date']);
    if ($fyP)
        $filter_years['paid']['fy'][] = $fyP;
    if ($cyP)
        $filter_years['paid']['cy'][] = $cyP;

    $fyE = getFiscalYearString($row['expiry_date']);
    $cyE = getCalendarYearString($row['expiry_date']);
    if ($fyE)
        $filter_years['expiry']['fy'][] = $fyE;
    if ($cyE)
        $filter_years['expiry']['cy'][] = $cyE;
}

foreach ($filter_years as $type => $modes) {
    foreach ($modes as $mode => $years) {
        $filter_years[$type][$mode] = array_unique($years);
        usort($filter_years[$type][$mode], function ($a, $b) {
            $vA = (strpos($a, 'FY-') === 0) ? (2000 + (int) substr($a, 3)) + 0.1 : (int) $a;
            $vB = (strpos($b, 'FY-') === 0) ? (2000 + (int) substr($b, 3)) + 0.1 : (int) $b;
            return $vB - $vA;
        });
    }
}

// --- FETCH USER DATA ---
try {
    $stmt = $pdo->prepare("SELECT * FROM tbl_users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    if (!$user)
        die("User not found");
    $full_name = trim($user['first_name'] . ' ' . ($user['middle_initial'] ? $user['middle_initial'] . '.' : '') . ' ' . $user['last_name']);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}


// QUERY FOR TOTAL EXPIRING
try {
    $current_month_day = date('m-d');
    if ($current_month_day >= '05-01') {
        $start_year = date('Y');
        $end_year = date('Y') + 1;
    } else {
        $start_year = date('Y') - 1;
        $end_year = date('Y');
    }
    $fiscal_year_start = "$start_year-05-01";
    $fiscal_year_end = "$end_year-04-30";
     $fiscal_year_label = 'FY' . substr($start_year, 2);
    $stmt2 = $pdo->prepare("SELECT COUNT(DISTINCT lot_no, field_no) as total_expiring FROM tbl_main WHERE expiry_date BETWEEN ? AND ?");
    $stmt2->execute([$fiscal_year_start, $fiscal_year_end]);
    $total_expiring = $stmt2->fetch(PDO::FETCH_ASSOC)['total_expiring'];
    $formatted_expiring = number_format($total_expiring);
} catch (PDOException $e) {
    $formatted_expiring = "Error";
    error_log("Database Error: " . $e->getMessage());
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

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- My CSS -->
    <link rel="stylesheet" href="css/user_exp.css">

    <title>Expiry Tracker | Land Asset Management Department</title>

    <!-- ADD THIS SCRIPT HERE -->
    <script>
        // Pass PHP permission variable to JavaScript
        window.CAN_EDIT_EXPIRY = <?php echo $can_edit_expiry ? 'true' : 'false'; ?>;
    </script>
    <!-- END ADD SCRIPT -->
</head>
<!-- ... REST OF YOUR HTML REMAINS THE SAME ... -->

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

    <!-- CONTENT -->
    <section id="content">

		<?php include 'components/user_navbar.php'; ?>

        <!-- MAIN -->
        <main>
            <div class="head-title">
                <div class="left">
                    <h1>Contract Expiry Monitoring</h1>
                    <!-- <ul class="breadcrumb">
                        <li><a href="#">Dashboard</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="#">Expiry Tracker</a></li>
                    </ul> -->

                    <form action="#" id="landownersSearchForm" class="head-search-form">
                        <div class="form-input">
                            <input type="search" id="searchInput" placeholder="Search contracting party, lot no, barangay..." autocomplete="off">
                            <button type="submit"><i class='bx bx-search'></i></button>
                        </div>
                    </form>
                    
                </div>
            </div>

            <div class="table-data">
                <div class="records">
                    <div class="head">
                        <!-- <h3 id="expiryHeader">Expiring this: <span><?php echo htmlspecialchars($formatted_expiring); ?></span></h3> -->
                        <h3 id="expiryHeader">
                            <p><em><span class="spinner"></span> Loading expirees...</em></p>
                        </h3>
                        <div class="filter-wrapper">
                            <span id="activeFilterDisplay"
                                style="position: absolute; width: 50px; font-weight: 600; color: #2e7d32; top: -10px; right: 55px;">
                            </span>

                            <span class="f_lo off"><i class="bx bx-slider-alt"></i></span>

                            <ul class="lo_dropdown_menu">
                                <li class="toggle-mode-li" onclick="event.stopPropagation();">
                                    <span class="c">Fiscal Year</span>
                                    <label class="toggle-switch">
                                        <input type="checkbox" id="fyModeToggle"
                                            onchange="toggleYearMode(this.checked)">
                                        <span class="toggle-slider"></span>
                                    </label>
                                </li>
                                <li class="menu-divider"></li>

                                <li onclick="showAllRows()" style="border-bottom: 1px solid #eee; color: #d32f2f;">
                                    <i class='bx bx-refresh'></i> Clear Filter
                                </li>

                                <!-- EXPIRY DATE SUBMENU -->
                                <li class="has-submenu">
                                    <i class='bx bx-chevron-left'></i> Expiry Date
                                    <ul class="submenu">
                                        <div class="fy-list" style="display:none;">
                                            <?php foreach ($filter_years['expiry']['fy'] as $yr): ?>
                                                <li onclick="filterBy('expiry', '<?= $yr ?>')"><?= $yr ?></li>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="cy-list">
                                            <?php foreach ($filter_years['expiry']['cy'] as $yr): ?>
                                                <li onclick="filterBy('expiry', '<?= $yr ?>')"><?= $yr ?></li>
                                            <?php endforeach; ?>
                                        </div>
                                    </ul>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Contracting Parties</th>
                                <!-- <th>CMS Application No.</th> -->
                                <th>Lease Status</th>
                                <th>Expiration Date</th>
                                <th>Days Left</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Helper function for PHP days left
                            function getDaysLeftPHP($dateStr)
                            {
                                if (empty($dateStr) || $dateStr === '0000-00-00')
                                    return 'N/A';
                                $expiry = new DateTime($dateStr);
                                $today = new DateTime();
                                $diff = $today->diff($expiry);
                                // Invert is 1 if the date is in the past
                                return $diff->invert ? -$diff->days : $diff->days;
                            }

                            foreach ($groupedRows as $group):
                                $main = $group['display'];

                                $cleanDetails = [];
                                foreach ($group['details'] as $rec) {
                                    $rec['rate'] = isset($rec['rate']) ? preg_replace('/[^0-9.]/', '', $rec['rate']) : '0';
                                    $rec['contracted_arable'] = isset($rec['contracted_arable']) ? preg_replace('/[^0-9.]/', '', $rec['contracted_arable']) : '0';
                                    $cleanDetails[] = $rec;
                                }

                                // --- Extract unique Landlist IDs ---
                                $landlistIds = array_unique(array_column($group['details'], 'cms_application_no'));
                                $landlistDisplay = implode(', ', $landlistIds);

                                $expiry_display = 'N/A';
                                if (!empty($main['expiry_date']) && $main['expiry_date'] !== '0000-00-00') {
                                    $expiry_display = date('M d, Y', strtotime($main['expiry_date']));
                                }

                                // Calculate Days Left
                                $daysLeft = getDaysLeftPHP($main['expiry_date']);
                                $daysLeftClass = '';
                                if (is_numeric($daysLeft)) {
                                    if ($daysLeft < 0)
                                        $daysLeftClass = 'days-expired';
                                    elseif ($daysLeft < 90)
                                        $daysLeftClass = 'days-critical';
                                    elseif ($daysLeft < 180)
                                        $daysLeftClass = 'days-warning';
                                    else
                                        $daysLeftClass = 'days-ok';
                                }

                                $defaultStatusClass = statusClass($main['lease_status']);
                                $jsonDetails = json_encode($cleanDetails);
                                ?>
                                <tr class="table-row" data-name="<?= htmlspecialchars($main['contracting_party']) ?>"
                                    data-barangay="<?= htmlspecialchars($main['barangay']) ?>"
                                    data-municipality="<?= htmlspecialchars($main['municipality']) ?>"
                                    data-province="<?= htmlspecialchars($main['province']) ?>"
                                    data-landlist="<?= htmlspecialchars($landlistDisplay) ?>"
                                    data-lot-no="<?= htmlspecialchars(implode(', ', array_column($group['details'], 'lot_no'))) ?>"
                                    data-field-no="<?= htmlspecialchars(implode(', ', array_column($group['details'], 'field_no'))) ?>"
                                    data-field-section="<?= htmlspecialchars(implode(', ', array_column($group['details'], 'field_section'))) ?>"
                                    data-lease-status="<?= htmlspecialchars($main['lease_status']) ?>"
                                    data-all-records='<?= htmlspecialchars($jsonDetails, ENT_QUOTES, 'UTF-8') ?>'>

                                    <td>
                                        <img src="../images/landOwner_icon.png" style="width: auto; vertical-align:middle;">
                                        <p style="display:inline;"><?= htmlspecialchars($main['contracting_party']) ?></p>
                                    </td>
                                    <!-- NEW TD FOR LANDLIST ID -->
                                    <!-- <td>
                                        <p data-original-text="<?= htmlspecialchars($landlistDisplay) ?>"><?= htmlspecialchars($landlistDisplay) ?></p>
                                    </td> -->
                                    <td>
                                        <p class="status <?= $defaultStatusClass ?>" style="display:inline;"
                                            data-original-text="<?= htmlspecialchars($main['lease_status']) ?>"
                                            data-original-class="<?= $defaultStatusClass ?>">
                                            <?= htmlspecialchars($main['lease_status']) ?>
                                        </p>
                                    </td>
                                    <td>
                                        <p data-original-text="<?= htmlspecialchars($expiry_display) ?>">
                                            <?= $expiry_display ?>
                                        </p>
                                    </td>
                                    <td>
                                        <span class="days-left-badge <?= $daysLeftClass ?>">
                                            <?= is_numeric($daysLeft) ? ($daysLeft < 0 ? abs($daysLeft) . ' days ago' : $daysLeft . ' days') : 'N/A' ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div id="noResultsMessage"
                        style="display: none; text-align: center; padding: 40px; background: #f9f9f9; border-radius: 8px; margin-top: 10px;">
                        <div style="font-size: 48px; margin-bottom: 20px;">🔍</div>
                        <h3 style="color: #666; margin-bottom: 10px;">No results found</h3>
                        <p style="color: #888;">Try adjusting your search terms or filters</p>
                    </div>
                </div>
            </div>

            <div id="resultsCounter"
                style="position: absolute; margin-top: 0px; right: 20px; padding: 5px 10px; color: #666; font-size: 14px;">
                Showing <span id="currentCount"><?= count($groupedRows) ?></span> out of <span
                    id="totalCount"><?= count($groupedRows) ?></span> records
            </div>
        </main>
        <!-- MAIN -->
    </section>
    <!-- CONTENT -->

    <!-- ===== MODAL ===== -->
    <div id="landOwnerModal" class="modal-overlay">
        <div class="modal">
            <span class="modal-close">&times;</span>
            <div class="todo">
                <div class="head" style="border-bottom: 2px solid #2E7D32;">
                    <h3>Land Owner Information</h3>
                </div>
                <div class="modal-header-row">
                    <div>
                        <h4 id="modalName"></h4>
                        <p id="modalCode"></p>
                    </div>
                </div>
                <div class="modal-tabs">
                    <button class="active" data-tab="overview" id="overview_btn">Overview</button>
                    <button data-tab="old_records" id="old_records_btn">Old Records</button>
                </div>
                <div id="overview" class="modal-tab-content active"
                    style="max-height: 520px; overflow-y: auto; padding-right: 10px;"></div>
                <div id="old_records" class="oldrec-tab-content"
                    style="max-height: 520px; overflow-y: auto; padding-right: 10px;"></div>
            </div>
        </div>
    </div>

    <!-- PASS PHP DATA TO JS -->
    <script>
        // Pass the RAW rows (not grouped) to JS for Chart Rendering
        window.rawContractData = <?php echo json_encode($rows); ?>;
        
        // Pass the exact count from your PHP query to JavaScript
        window.phpExpiringCount = <?php echo json_encode($total_expiring); ?>;
    </script>

    <script src="js/user_exp.js"></script>
</body>

</html>