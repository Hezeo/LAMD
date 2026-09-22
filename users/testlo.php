<?php
require '../config.php'; // your PDO connection file
require_once '../config_session.php';
checkLogin();

$user_id = getCurrentUserId();
$message = '';
$error = '';

// 1. Prepare and Execute the SQL query first
// ADDED: longlat to the SELECT list
$sql = "SELECT 
    id,
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
    contract_class,
    longlat 
FROM tbl_main 
ORDER BY start_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC); // Now $rows is defined and contains data

// 2. Define the helper function
function statusClass($status)
{
    return strtolower(str_replace([' ', '-'], '', $status));
}

// 3. Now perform the grouping logic
$groupedRows = [];
foreach ($rows as $row) {
    // Create a unique key based on Party, Barangay, and Municipality
    $key = $row['contracting_party'] . '|' . $row['barangay'] . '|' . $row['municipality'];

    if (!isset($groupedRows[$key])) {
        $groupedRows[$key] = [
            'display' => $row, // Main info for the table row
            'details' => []    // Array to hold all related records
        ];
    }
    $groupedRows[$key]['details'][] = $row;
}

//FILTER
// Extract unique Fiscal Years for the filter
$filter_years = ['start' => [], 'paid' => [], 'expiry' => []];

// Helper function with CUTOFF DATE LOGIC
// --- START: UPDATED FILTER LOGIC ---

// Helper function for Fiscal Year
function getFiscalYearString($dateStr)
{
    if (empty($dateStr) || $dateStr === '0000-00-00')
        return null;
    if ($dateStr < '2014-05-01')
        return date('Y', strtotime($dateStr));

    $timestamp = strtotime($dateStr);
    $year = (int) date('Y', $timestamp);
    $month = (int) date('m', $timestamp);
    $fyStartYear = ($month <= 4) ? $year - 1 : $year;

    return 'FY-' . substr($fyStartYear, -2);
}

// Helper function for Calendar Year
function getCalendarYearString($dateStr)
{
    if (empty($dateStr) || $dateStr === '0000-00-00')
        return null;
    return date('Y', strtotime($dateStr));
}

// Arrays to hold both types of years
$filter_years = [
    'start' => ['fy' => [], 'cy' => []],
    'paid' => ['fy' => [], 'cy' => []],
    'expiry' => ['fy' => [], 'cy' => []]
];

foreach ($rows as $row) {
    // Start Date
    $fyS = getFiscalYearString($row['start_date']);
    $cyS = getCalendarYearString($row['start_date']);
    if ($fyS)
        $filter_years['start']['fy'][] = $fyS;
    if ($cyS)
        $filter_years['start']['cy'][] = $cyS;

    // Paid Date
    $fyP = getFiscalYearString($row['paid_up_date']);
    $cyP = getCalendarYearString($row['paid_up_date']);
    if ($fyP)
        $filter_years['paid']['fy'][] = $fyP;
    if ($cyP)
        $filter_years['paid']['cy'][] = $cyP;

    // Expiry Date
    $fyE = getFiscalYearString($row['expiry_date']);
    $cyE = getCalendarYearString($row['expiry_date']);
    if ($fyE)
        $filter_years['expiry']['fy'][] = $fyE;
    if ($cyE)
        $filter_years['expiry']['cy'][] = $cyE;
}

// Unique and Sort
foreach ($filter_years as $type => $modes) {
    foreach ($modes as $mode => $years) {
        $filter_years[$type][$mode] = array_unique($years);
        usort($filter_years[$type][$mode], function ($a, $b) {
            // Sort logic (FY-14 vs 2013 etc)
            $vA = (strpos($a, 'FY-') === 0) ? (2000 + (int) substr($a, 3)) + 0.1 : (int) $a;
            $vB = (strpos($b, 'FY-') === 0) ? (2000 + (int) substr($b, 3)) + 0.1 : (int) $b;
            return $vB - $vA; // Descending
        });
    }
}
// --- END: UPDATED FILTER LOGIC ---

// --- FETCH USER DATA ---
try {
    $stmt = $pdo->prepare("SELECT * FROM tbl_users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    if (!$user)
        die("User not found");

    $full_name = trim($user['first_name'] . ' ' . ($user['middle_initial'] ? $user['middle_initial'] . '.' : '') . ' ' . $user['last_name']);
    $full_name = preg_replace('/\s+/', ' ', $full_name);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
$default_cloud_url = "https://i.ibb.co/kVPtjmbK/default-profile-pic.png";
$header_profile_image = !empty($user['profile_image']) ? $user['profile_image'] : $default_cloud_url;

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="icon" href="../images/web_icon.png" type="image/x-icon" />

    <!-- Boxicons -->
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link href='https://unpkg.com/boxicons@2.1.4/dist/boxicons.js' rel='stylesheet'>

    <!-- My CSS -->
    <link rel="stylesheet" href="css/testlo.css">

    <title>Land Owners | Land Asset Management Department</title>
</head>

<body>
    <!-- SIDEBAR -->
    <section id="sidebar">
        <a href="#" class="brand">
            <img src="../images/web_icon.png" alt="LAND ASSET MANAGEMENT"
                style="height: 40px; width: auto; object-fit: contain; margin-right: 10px;">
            <span class="text">LAND ASSET MANAGEMENT DEPARTMENT</span>
        </a>
        <ul class="side-menu top">
            <li>
                <a href="user_dashboard.php">
                    <i class='bx bxs-dashboard bx-sm'></i>
                    <span class="text">Dashboard</span>
                </a>
            </li>
            <li class="active">
                <a href="land_owners.php">
                    <i class='bx bxs-user bx-sm'></i>
                    <span class="text">Land Owners</span>
                </a>
            </li>
            <li>
                <a href="user_exp.php">
                    <i class='bx bx-calendar-exclamation bx-sm'></i>
                    <span class="text">Expiry Tracker</span>
                </a>
            </li>
            <li>
                <a href="user_report.php">
                    <i class='bx bx-line-chart bx-sm'></i>
                    <span class="text">Reports Generator</span>
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
            <!-- <a href="#" class="nav-link">Categories</a> -->
            <form action="#">
                <div class="form-input">
                    <input type="search" id="searchInput" placeholder="Search...">
                    <button type="submit" class="search-btn"><i class='bx bx-search'></i></button>
                </div>
            </form>


            <input type="checkbox" class="checkbox" id="switch-mode" hidden />

            <!-- Profile Menu -->
            <?php
            // Determine profile image path
            if (isset($user['profile_image']) && !empty($user['profile_image'])) {
                $profile_image_path = $user['profile_image'];
            } else {
                $profile_image_path = 'https://i.pinimg.com/736x/11/0e/6a/110e6affbed02f3f1b2d864832423fdc.jpg';
            }
            ?>

            <a href="#" class="profile" id="profileIcon">
                <img src="../users/images/<?php echo htmlspecialchars($profile_image_path); ?>" alt="Profile">
            </a>

            <div class="profile-menu" id="profileMenu">
                <ul>
                    <li onclick="window.location.href='user_profile.php';" style="cursor:pointer;">My Profile</li>
                    <li onclick="window.location.href='user_settings.php';" style="cursor:pointer;">Settings</li>
                    <li onclick="window.location.href='actions/logout.php';" style="cursor:pointer;">Log Out</li>
                </ul>
            </div>
        </nav>
        <!-- NAVBAR -->


        <!-- MAIN -->
        <main>
            <div class="head-title">
                <div class="left">
                    <h1>Land Owners List</h1>
                </div>
                <div class="right">
                    <!-- ADDED ID: openAddContractModal -->
                    <a href="#" class="btn-add-contract" id="openAddContractModal">
                        <i class='bx bx-user-plus bx-sm'></i>
                        <span class="text">Add New Contract</span>
                    </a>
                </div>
            </div>

            <div class="table-data">
                <div class="records">
                    <div class="head">
                        <h3>Recently Added</h3>
                        <div class="filter-wrapper">
                            <span id="activeFilterDisplay"
                                style="position: absolute; white-space: nowrap; color: #2e7d32; top: -10px; right: 55px;">
                            </span>
                            <span class="f_lo off"><i class="bx bx-slider-alt"></i></span>

                            <ul class="lo_dropdown_menu">

                                <!-- TOGGLE BUTTON -->
                                <li class="toggle-mode-li" onclick="event.stopPropagation();">
                                    <span class="toggle-label">Fiscal Year</span>
                                    <label class="toggle-switch">
                                        <!-- Removed 'checked' attribute so it defaults to OFF (Calendar Year) -->
                                        <input type="checkbox" id="fyModeToggle"
                                            onchange="toggleYearMode(this.checked)">
                                        <span class="toggle-slider"></span>
                                    </label>
                                </li>
                                <li class="menu-divider"></li>
                                <!-- END TOGGLE -->

                                <li onclick="showAllRows()" style="border-bottom: 1px solid #eee; color: #d32f2f;">
                                    <i class='bx bx-refresh'></i> Clear Filter
                                </li>

                                <!-- START DATE SUBMENU -->
                                <li class="has-submenu">
                                    <i class='bx bx-chevron-left'></i> Start Date
                                    <ul class="submenu">
                                        <!-- FY List (Hidden by default) -->
                                        <div class="fy-list" style="display:none;">
                                            <?php foreach ($filter_years['start']['fy'] as $yr): ?>
                                                <li onclick="filterBy('start', '<?= $yr ?>')"><?= $yr ?></li>
                                            <?php endforeach; ?>
                                        </div>
                                        <!-- CY List (Visible by default) -->
                                        <div class="cy-list">
                                            <?php foreach ($filter_years['start']['cy'] as $yr): ?>
                                                <li onclick="filterBy('start', '<?= $yr ?>')"><?= $yr ?></li>
                                            <?php endforeach; ?>
                                        </div>
                                    </ul>
                                </li>

                                <!-- PAID DATE SUBMENU -->
                                <li class="has-submenu">
                                    <i class='bx bx-chevron-left'></i> Paid Date
                                    <ul class="submenu">
                                        <!-- FY List (Hidden by default) -->
                                        <div class="fy-list" style="display:none;">
                                            <?php foreach ($filter_years['paid']['fy'] as $yr): ?>
                                                <li onclick="filterBy('paid', '<?= $yr ?>')"><?= $yr ?></li>
                                            <?php endforeach; ?>
                                        </div>
                                        <!-- CY List (Visible by default) -->
                                        <div class="cy-list">
                                            <?php foreach ($filter_years['paid']['cy'] as $yr): ?>
                                                <li onclick="filterBy('paid', '<?= $yr ?>')"><?= $yr ?></li>
                                            <?php endforeach; ?>
                                        </div>
                                    </ul>
                                </li>

                                <!-- EXPIRY DATE SUBMENU -->
                                <li class="has-submenu">
                                    <i class='bx bx-chevron-left'></i> Expiry Date
                                    <ul class="submenu">
                                        <!-- FY List (Hidden by default) -->
                                        <div class="fy-list" style="display:none;">
                                            <?php foreach ($filter_years['expiry']['fy'] as $yr): ?>
                                                <li onclick="filterBy('expiry', '<?= $yr ?>')"><?= $yr ?></li>
                                            <?php endforeach; ?>
                                        </div>
                                        <!-- CY List (Visible by default) -->
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
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($groupedRows as $group):
                                $main = $group['display'];

                                $cleanDetails = [];
                                foreach ($group['details'] as $rec) {
                                    $rec['rate'] = isset($rec['rate']) ? preg_replace('/[^0-9.]/', '', $rec['rate']) : '0';
                                    $rec['contracted_arable'] = isset($rec['contracted_arable']) ? preg_replace('/[^0-9.]/', '', $rec['contracted_arable']) : '0';
                                    $cleanDetails[] = $rec;
                                }

                                $jsonDetails = htmlspecialchars(json_encode($cleanDetails), ENT_QUOTES, 'UTF-8');
                                ?>
                                <tr class="table-row" data-name="<?= htmlspecialchars($main['contracting_party']) ?>"
                                    data-barangay="<?= htmlspecialchars($main['barangay']) ?>"
                                    data-municipality="<?= htmlspecialchars($main['municipality']) ?>"
                                    data-province="<?= htmlspecialchars($main['province']) ?>"
                                    data-landlist="<?= htmlspecialchars($main['cms_application_no']) ?>"
                                    data-longlat="<?= htmlspecialchars($main['longlat']) ?>"
                                    data-lot-no="<?= htmlspecialchars(implode(', ', array_column($group['details'], 'lot_no'))) ?>"
                                    data-field-no="<?= htmlspecialchars(implode(', ', array_column($group['details'], 'field_no'))) ?>"
                                    data-field-section="<?= htmlspecialchars(implode(', ', array_column($group['details'], 'field_section'))) ?>"
                                    data-all-records='<?= $jsonDetails ?>'>

                                    <td>
                                        <img src="../images/landOwner_icon.png" style="width: auto; vertical-align:middle;">
                                        <p style="display:inline;"><?= htmlspecialchars($main['contracting_party']) ?>
                                        </p>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- No Results Message (hidden by default) -->
                    <div id="noResultsMessage"
                        style="display: none; text-align: center; padding: 40px; background: #f9f9f9; border-radius: 8px; margin-top: 10px;">
                        <div style="font-size: 48px; margin-bottom: 20px;">🔍</div>
                        <h3 style="color: #666; margin-bottom: 10px;">No results found</h3>
                        <p style="color: #888;">Try adjusting your search terms or filters</p>
                    </div>
                </div>
            </div>

            <!-- Results Counter -->
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

                <!-- NAME + STATUS -->
                <div class="modal-header-row">
                    <div>
                        <h4 id="modalName"></h4>
                        <p id="modalCode"></p>
                        <!-- <p id="modalAddress"></p> -->
                    </div>
                </div>

                <!-- TABS -->
                <div class="modal-tabs">
                    <button class="active" data-tab="overview" id="overview_btn">Overview</button>
                    <button data-tab="old_records" id="old_records_btn">Old Records</button>
                </div>

                <!-- TAB CONTENT -->
                <div id="overview" class="modal-tab-content active"
                    style="max-height: 520px; overflow-y: auto; padding-right: 10px;"></div>
                <div id="old_records" class="oldrec-tab-content"
                    style="max-height: 520px; overflow-y: auto; padding-right: 10px;"></div>

            </div>
        </div>
    </div>

    <!-- ===== ADD NEW CONTRACT MODAL ===== -->
    <div id="addContractModal" class="popup-modal">
        <div class="popup-modal-content">

            <!-- Header -->
            <div class="popup-modal-header">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div class="user-profile-badge">
                        <i class='bx bxs-file-plus'></i>
                    </div>
                    <div>
                        <h2 style="margin:0;">Add New Contract</h2>
                        <p style="margin:0; font-size: 0.85rem; color: #666;">Fill in the details below</p>
                    </div>
                </div>
                <span class="popup-close-modal">&times;</span>
            </div>

            <!-- Form Body -->
            <form method="POST" action="actions/add_new_contract_action.php" id="addContractForm">

                <!-- Row 1: CMS Application No. -->
                <div class="popup-form-row">
                    <div class="popup-form-group">
                        <label>CMS Application No.</label>
                        <input type="text" name="cms_application_no" id="cms_application_no"
                            oninput="this.value = this.value.toUpperCase()" required>
                    </div>
                    <div class="popup-form-group">
                        <label>Vendor</label>
                        <input type="text" name="vendor" id="vendor_input" pattern="[0-9]+"
                            title="Vendor must be numbers only" required>
                    </div>
                </div>

                <!-- Row 2: Contracting Party -->
                <div class="popup-form-group">
                    <label>Contracting Party</label>
                    <input type="text" name="contracting_party" class="uppercase-input"
                        placeholder="e.g. DELA CRUZ, JUAN A." required>
                </div>

                <!-- Row 3: Location (Province -> Mun -> Brgy) -->
                <div class="popup-form-row">
                    <div class="popup-form-group">
                        <label>Province</label>
                        <select name="province" id="provinceSelect" required>
                            <option value="" disabled selected>Select Province</option>
                            <option value="Bukidnon">Bukidnon</option>
                            <option value="Agusan del Norte">Agusan del Norte</option>
                            <option value="Agusan del Sur">Agusan del Sur</option>
                            <option value="Camiguin">Camiguin</option>
                            <option value="Davao del Norte">Davao del Norte</option>
                            <option value="Davao del Sur">Davao del Sur</option>
                            <option value="Davao Oriental">Davao Oriental</option>
                            <option value="Lanao del Norte">Lanao del Norte</option>
                            <option value="Lanao del Sur">Lanao del Sur</option>
                            <option value="Misamis Occidental">Misamis Occidental</option>
                            <option value="Misamis Oriental">Misamis Oriental</option>
                            <option value="South Cotabato">South Cotabato</option>
                            <option value="Sultan Kudarat">Sultan Kudarat</option>
                            <option value="Surigao del Norte">Surigao del Norte</option>
                            <option value="Surigao del Sur">Surigao del Sur</option>
                        </select>
                    </div>
                    <div class="popup-form-group">
                        <label>Municipality</label>
                        <select name="municipality" id="municipalitySelect" required disabled>
                            <option value="">Select Province First</option>
                        </select>
                    </div>
                </div>

                <div class="popup-form-row">
                    <div class="popup-form-group">
                        <label>Barangay</label>
                        <select name="barangay" id="barangaySelect" required disabled>
                            <option value="">Select Municipality First</option>
                        </select>
                    </div>
                    <div class="popup-form-group">
                        <label>Long/Lat</label>
                        <input type="text" name="longlat" placeholder="e.g. 8.123, 124.456">
                    </div>
                </div>

                <!-- Row 4: Specifics -->
                <div class="popup-form-row">
                    <div class="popup-form-group">
                        <label>Lot No</label>
                        <input type="text" name="lot_no" oninput="this.value = this.value.toUpperCase()" required>
                    </div>
                    <div class="popup-form-group">
                        <label>Field No</label>
                        <input type="text" name="field_no" oninput="this.value = this.value.toUpperCase()">
                    </div>
                </div>

                <div class="popup-form-row">
                    <div class="popup-form-group">
                        <label>Field Section</label>
                        <input type="text" name="field_section" oninput="this.value = this.value.toUpperCase()">
                    </div>
                    <div class="popup-form-group">
                        <!-- Empty spacer -->
                    </div>
                </div>

                <!-- Row 5: Group Accounts (Dynamic) -->
                <label
                    style="font-weight: 600; font-size: 0.85rem; color: #475569; margin-bottom: 5px; display: block;">Group
                    Accounts & Arable</label>
                <div id="groupAccountContainer">
                    <div class="dynamic-row">
                        <div class="popup-form-group" style="flex: 2;">
                            <input type="text" name="group_acc[]" placeholder="e.g. DELA CRUZ, JUAN A." value="NONE"
                                oninput="this.value = this.value.toUpperCase()">
                        </div>
                        <div class="popup-form-group" style="flex: 1;">
                            <input type="number" step="0.0001" min="0" name="contracted_arable[]"
                                placeholder="Arable (HAS)" required>
                        </div>
                    </div>
                </div>
                <button type="button" class="add-row-btn" onclick="addGroupRow()"><i class='bx bx-plus'></i> Add Group
                    Account</button>

                <!-- Row 6: Rate & Dates -->
                <div class="popup-form-row" style="margin-top: 15px;">
                    <div class="popup-form-group">
                        <label>Rate</label>
                        <input type="text" name="rate" id="rate_input" placeholder="e.g. 15000.00">
                    </div>
                    <div class="popup-form-group">
                        <label>Crop Type</label>
                        <select name="crop_type" required>
                            <option value="C74">C74</option>
                            <option value="PAPAYA">PAPAYA</option>
                            <option value="WHSE">WHSE</option>
                            <option value="S16">S16</option>
                            <option value="AVOCADO">AVOCADO</option>
                            <option value="BODEGA">BODEGA</option>
                            <option value="OP">OP</option>
                        </select>
                    </div>
                </div>

                <div class="popup-form-row">
                    <div class="popup-form-group">
                        <label>Start Date</label>
                        <input type="date" name="start_date" required>
                    </div>
                    <div class="popup-form-group">
                        <label>Expiry Date</label>
                        <input type="date" name="expiry_date" required>
                    </div>
                </div>

                <div class="popup-form-row">
                    <div class="popup-form-group">
                        <label>Paid Up Date</label>
                        <input type="date" name="paid_up_date">
                    </div>
                    <div class="popup-form-group">
                        <!-- Spacer -->
                    </div>
                </div>

                <!-- Row 7: Status & Class -->
                <div class="popup-form-row">
                    <div class="popup-form-group">
                        <label>Lease Status</label>
                        <select name="lease_status">
                            <option value="Active">Active</option>
                            <option value="Expired">Expired</option>
                            <option value="Near Expiration">Near Expiration</option>
                        </select>
                    </div>
                    <div class="popup-form-group">
                        <label>Contract Status</label>
                        <select name="contract_status">
                            <option value="EXISTING">EXISTING</option>
                            <option value="NONRENEWING">NONRENEWING</option>
                            <option value="FOR RETURN">FOR RETURN</option>
                            <option value="RETURN">RETURN</option>
                            <option value="RENEWED">RENEWED</option>
                            <option value="EXTENSION">EXTENSION</option>
                            <option value="NEWLAND">NEWLAND</option>
                            <option value="EXPIRED">EXPIRED</option>
                            <option value="UNUTILIZED">UNUTILIZED</option>
                            <option value="RETAIN">RETAIN</option>
                            <option value="CANCELLED">CANCELLED</option>
                        </select>
                    </div>
                </div>

                <div class="popup-form-row">
                    <div class="popup-form-group">
                        <label>Contract Class</label>
                        <select name="contract_class">
                            <option value="CP&GA">CP&GA</option>
                            <option value="DEVELOPMENT AGREEMENT">DEVELOPMENT AGREEMENT</option>
                            <option value="MOA">MOA</option>
                            <option value="CONTRACT OF LEASE">CONTRACT OF LEASE</option>
                            <option value="GROWERSHIP AGREEMENT">GROWERSHIP AGREEMENT</option>
                            <option value="GROWERSHIP">GROWERSHIP</option>
                        </select>
                    </div>
                    <div class="popup-form-group">
                        <!-- Empty space -->
                    </div>
                </div>

                <!-- Footer -->
                <div class="popup-modal-footer">
                    <!-- CHANGED TYPE TO BUTTON AND ADDED ONCLICK -->
                    <button type="button" onclick="validateAndConfirm()" class="btn-save-profile">
                        <i class='bx bx-save'></i> Save Contract
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ===== CONFIRMATION MODAL (MOVED OUTSIDE) ===== -->
    <div id="confirmModal" class="popup-modal">
        <div class="popup-modal-content" style="max-width: 400px; text-align: center;">

            <!-- ADJUSTED: Added padding-bottom: 0 and margin-bottom: 10px to bring it closer -->
            <div class="popup-modal-header"
                style="border-bottom: none; display: flex; justify-content: center; padding-bottom: 0; margin-bottom: 10px;">
                <i class='bx bx-question-mark'
                    style="font-size: 50px; color: #FFC107; background: #FFF9C4; padding: 20px; border-radius: 50%;"></i>
            </div>

            <!-- ADJUSTED: Added margin-top: 0 to remove default spacing -->
            <h2 style="margin-bottom: 10px; margin-top: 0;">Confirm Save</h2>

            <p style="color: #666; margin-bottom: 20px;">Are you sure you want to save this contract? Please review the
                details carefully.</p>

            <div style="display: flex; justify-content: center; gap: 15px;">
                <button class="btn-cancel" onclick="closeConfirmModal()"
                    style="background: #e0e0e0; color: #333; padding: 10px 30px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer;">Cancel</button>
                <button class="btn-confirm" onclick="submitFinalForm()"
                    style="background: #4CAF50; color: white; padding: 10px 30px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer;">Yes,
                    Save</button>
            </div>
        </div>
    </div>

    <!-- ===== SUCCESS MODAL (MOVED OUTSIDE) ===== -->
    <div id="successModal" class="popup-modal">
        <div class="popup-modal-content" style="max-width: 400px; text-align: center;">
            <div class="popup-modal-header" style="border-bottom: none; justify-content: center;">
                <i class='bx bx-check-circle'
                    style="font-size: 50px; color: #4CAF50; background: #E8F5E9; padding: 20px; border-radius: 50%;"></i>
            </div>
            <h2 style="margin-bottom: 10px;">Success!</h2>
            <p style="color: #666; margin-bottom: 20px;">Contract saved successfully.</p>

            <div style="display: flex; justify-content: center; gap: 15px;">
                <button onclick="closeSuccessModal()"
                    style="background: #4CAF50; color: white; padding: 10px 30px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer;">OK</button>
            </div>
        </div>
    </div>

    <!-- ===== DELETE CONFIRMATION MODAL ===== -->
    <div id="deleteModal" class="popup-modal">
        <div class="popup-modal-content">

            <div class="popup-modal-header"
                style="border-bottom: none; display: flex; justify-content: center; padding-bottom: 0; margin-bottom: 10px;">
                <i class='bx bx-error'
                    style="font-size: 50px; color: #d32f2f; background: #FFEBEE; padding: 20px; border-radius: 50%;"></i>
            </div>

            <h2 style="margin-bottom: 10px; margin-top: 0;">Confirm Deletion</h2>

            <p style="color: #666; margin-bottom: 5px;">You are about to delete this contract record. This action cannot
                be undone.</p>

            <!-- Details Section -->
<div class="delete-details">
    <p><strong>Lot No:</strong> <span id="deleteLotNo"></span></p>
    <p><strong>Contracting Party:</strong> <span id="deleteName"></span></p>
    <input type="hidden" id="deleteRecordId">
    <!-- ADDED: Hidden input to store Vendor ID -->
    <input type="hidden" id="deleteVendor">
</div>

            <div style="display: flex; justify-content: center; gap: 15px;">
                <button class="btn-cancel" onclick="closeDeleteModal()"
                    style="background: #e0e0e0; color: #333; padding: 10px 30px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer;">Cancel</button>
                <button class="btn-confirm-delete" onclick="confirmDelete()"
                    style="padding: 10px 30px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer;">Delete
                    Record</button>
            </div>
        </div>
    </div>

    <script src="js/testlo.js"></script>
</body>

</html>