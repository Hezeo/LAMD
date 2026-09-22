<?php
require '../config.php'; // your PDO connection file
require_once '../config_session.php';
checkLogin();

// ADD THIS LINE: Block access if they don't have 'Land Owners' permission
requirePermission('Land Owners');

$user_id = getCurrentUserId();
$message = '';
$error = '';

// --- NEW: CHECK IF USER HAS EDIT PERMISSION ---
// Default to false
$can_edit_landowners = false;

if (!empty($_SESSION['user_permissions']) && isset($_SESSION['user_permissions']['Land Owners'])) {
    $perm_data = $_SESSION['user_permissions']['Land Owners'];
    // Check if 'can' is 'edit'
    $access_level = is_array($perm_data) ? ($perm_data['can'] ?? '') : $perm_data;
    if ($access_level === 'edit') {
        $can_edit_landowners = true;
    }
}
// ----------------------------------------------

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
    longlat,
    contract_id 
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
    <link rel="stylesheet" href="css/land_owners.css">

    <title>Land Owners | Land Asset Management Department</title>

    <!-- ADD THIS SCRIPT HERE -->
    <script>
        // Pass PHP permission variable to JavaScript
        window.CAN_EDIT_LANDOWNERS = <?php echo $can_edit_landowners ? 'true' : 'false'; ?>;
    </script>
    <!-- END ADD SCRIPT -->
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



    <!-- CONTENT -->
    <section id="content">

        <?php include 'components/user_navbar.php'; ?>

        <!-- MAIN -->
        <main>
            <h1>Land Owners List</h1>
            <div class="head-title">
                <div class="left">
                    <!-- MOVED SEARCH HERE -->
                    <form action="#" id="landownersSearchForm" class="head-search-form">
                        <div class="form-input"> <!-- CHANGED FROM head-search-input TO form-input -->
                            <input type="search" id="searchInput" placeholder="Search contracting party, lot no, barangay..." autocomplete="off">
                            <button type="submit"><i class='bx bx-search'></i></button>
                        </div>
                    </form>
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
                        <h3>Sorted by Start Date (Latest First)</h3>
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
                                <th>Number of Contracts</th>
                                <th>Total Hectares</th>
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

                                // Extract the ID of the first record for the main row data attribute
                                $firstRecordId = $main['id'];
                            ?>
                                <tr class="table-row" data-id="<?= htmlspecialchars($firstRecordId) ?>"
                                    data-name="<?= htmlspecialchars($main['contracting_party']) ?>"
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
                                    <!-- Placeholder TDs for JS to populate -->
                                    <td class="contract-count-cell"></td>
                                    <td class="contract-total-has-cell"></td>
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
                    style="max-height: 530px; overflow-y: auto; padding-right: 10px;"></div>
                <div id="old_records" class="oldrec-tab-content"
                    style="max-height: 530px; overflow-y: auto; padding-right: 10px;"></div>

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


                <div class="section-title">Contract Information</div>
                <div class="popup-form-row">
                    <div class="popup-form-group">
                        <label>Contracting Party</label>
                        <input type="text" name="contracting_party" class="uppercase-input"
                            placeholder="e.g. DELA CRUZ, JUAN A." required>
                    </div>
                    <div class="popup-form-group">
                    </div>
                </div>

                <!-- SHARED DATA: Only CMS, Vendor, Party, and Dates go here -->
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

                <hr style="border: 0; border-top: 1px solid #ddd; margin: 20px 0;">

                <div class="section-title">Lots & Group Accounts</div>
                <!-- DYNAMIC LOTS SECTION -->
                <div class="popup-form-group">
                    <!-- <p style="font-size: 0.8rem; color: #666; margin-bottom: 10px;">
                        Each Lot can have its own Location, Rate, and Status.
                    </p> -->

                    <!-- Container where Lot Blocks will be added -->
                    <div id="lotsContainer">
                        <!-- Initial Lot Block (Will be populated by JS on load) -->
                    </div>

                    <!-- Button to Add a NEW LOT -->
                    <button type="button" class="add-row-btn" onclick="addLotBlock()" style="margin-top: 10px; background-color: #2E7D32; color: white;">
                        <i class='bx bx-plus'></i> Add New Lot
                    </button>
                </div>

                <hr style="border: 0; border-top: 1px solid #ddd; margin: 20px 0;">

                <!-- SHARED INCEPTION DATES -->
                <div class="section-title">Inception Dates</div>
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


                <!-- SUPPORTING DOCUMENTS SECTION -->
                <div class="popup-form-group">
                    <div class="section-title">Supporting Documents</div>
                    <div style="display: flex; gap: 10px;">
                        <input type="file" name="contract_documents[]" id="contract_documents" multiple
                            accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                            style="flex-grow: 1; font-size: 0.9rem;">
                    </div>
                    <small style="color: #666; font-size: 0.8rem;">Allowed: PDF, DOCX, JPG, PNG</small>

                    <!-- ADD THIS NEW CONTAINER HERE -->
                    <div id="documentPreviewArea" class="preview-grid"></div>
                </div>
                <!-- END SUPPORTING DOCUMENTS SECTION -->


                <style>
                    .section-title {
                        color: #1b5e20;
                        font-size: 0.95rem;
                        font-weight: 700;
                        text-transform: uppercase;
                        letter-spacing: 0.5px;
                        margin-bottom: 20px;
                        display: flex;
                        align-items: center;
                        gap: 10px;
                    }

                    .section-title::before {
                        content: '';
                        display: block;
                        width: 4px;
                        height: 16px;
                        background: #2E7D32;
                        border-radius: 2px;
                    }
                    
                    /* --- CLEAN DOCUMENT PREVIEW STYLES --- */
                    .preview-grid {
                        display: flex;
                        /* Changed from grid to flex to allow variable widths */
                        flex-wrap: wrap;
                        /* Allows items to wrap to the next line */
                        gap: 15px;
                        margin-top: 15px;
                        align-items: flex-start;
                        /* Ensures cards don't stretch to match the tallest card in the row */
                    }

                    /* --- UPDATE DOC PREVIEW ITEM (Add position relative) --- */
                    .doc-preview-item {
                        /* Keep existing properties... */
                        position: relative;
                        /* IMPORTANT: This allows the delete button to be positioned absolutely inside the card */
                        /* ... rest of your existing CSS ... */
                    }

                    /* --- NEW: DELETE BUTTON STYLE --- */
                    .remove-doc-btn {
                        position: absolute;
                        top: -8px;
                        right: -8px;
                        width: 24px;
                        height: 24px;
                        background: #d32f2f;
                        color: white;
                        border-radius: 50%;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        cursor: pointer;
                        z-index: 10;
                        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
                        border: 2px solid #fff;
                        /* Creates a nice gap from the image */
                        transition: background 0.2s;
                    }

                    .remove-doc-btn:hover {
                        background: #b71c1c;
                    }

                    /* Top Half: The Image/Icon Box */
                    .img-preview-box {
                        height: 150px;
                        /* FIXED HEIGHT: Levels all images as requested */
                        width: auto;
                        /* AUTO WIDTH: Allows width to be "exact as it is" */
                        background: #f9f9f9;
                        border-radius: 4px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        overflow: hidden;
                        margin-bottom: 8px;
                        /* Space below image */
                    }

                    .img-preview-box img {
                        width: auto;
                        /* Auto width based on aspect ratio */
                        height: 100%;
                        /* Fill the fixed height box (150px) */
                        object-fit: contain;
                        /* Keeps aspect ratio, fits inside 150px height */
                        border-radius: 4px;
                        display: block;
                    }

                    .img-preview-box i {
                        font-size: 50px;
                        color: #666;
                    }

                    /* Bottom Half: Filename Wrapper */
                    .filename-info {
                        text-align: center;
                        position: relative;
                        /* Needed for Tooltip positioning */
                    }

                    .filename-text {
                        font-size: 0.8rem;
                        color: #333;
                        display: block;
                        width: 100%;

                        /* Limit to 2 lines */
                        line-height: 1.3;
                        height: 2.6em;
                        overflow: hidden;
                        text-overflow: ellipsis;
                        white-space: nowrap;
                    }

                    /* Tooltip: Appears on Hover */
                    .filename-tooltip {
                        position: absolute;
                        bottom: 105%;
                        /* Position ABOVE text */
                        left: 50%;
                        transform: translateX(-50%);
                        background: #333;
                        color: #fff;
                        padding: 6px 12px;
                        border-radius: 4px;
                        font-size: 0.8rem;
                        white-space: normal;
                        /* Allow wrapping */
                        z-index: 10;
                        opacity: 0;
                        visibility: hidden;
                        transition: opacity 0.2s;
                        min-width: 100px;
                        /* CHANGED from 150px to 100px so it doesn't force grid expansion */
                        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
                        pointer-events: none;
                        /* Prevents mouse flickering */
                    }

                    /* Show Tooltip on Hover */
                    .doc-preview-item:hover .filename-tooltip {
                        opacity: 1;
                        visibility: visible;
                    }
                    
                <style>
    /* --- UPDATED: GREEN THEME DARK MODE STYLES FOR LOTS & GROUP ACCOUNTS --- */

    /* 1. Section Title (Bright Green) */
    body.dark .section-title {
        color: #2ecc71 !important; /* Match your --green variable */
    }
    body.dark .section-title::before {
        background: #2ecc71 !important;
    }

    /* 2. FORCE Input and Select Backgrounds (Greenish Dark) */
    body.dark #lotsContainer input,
    body.dark #lotsContainer select {
        background-color: rgba(0, 0, 0, 0.4) !important; /* Dark Green-Black */
        color: #e8f5e9 !important;            /* Light Green Text */
        border: 1px solid rgba(46, 204, 113, 0.4) !important;  /* Stronger Green Border */
    }

    /* Placeholder Text */
    body.dark #lotsContainer input::placeholder {
        color: rgba(169, 199, 172, 0.6) !important; /* Dimmed Green */
        opacity: 1;
    }

    /* Focus State (Bright Green Glow) */
    body.dark #lotsContainer input:focus,
    body.dark #lotsContainer select:focus {
        border-color: #2ecc71 !important; /* Bright Emerald Focus */
        background-color: rgba(0, 0, 0, 0.5) !important;
        box-shadow: 0 0 0 3px rgba(46, 204, 113, 0.15) !important; /* Green Glow */
        outline: none !important;
    }

    /* 3. Labels inside the Lots Container */
    body.dark #lotsContainer label {
        color: #a9c7ac !important; /* Match your --dark-grey variable */
    }

    /* 4. Individual Lot Blocks (Semi-transparent Green) */
    body.dark #lotsContainer > div {
        background-color: rgba(0, 0, 0, 0.25) !important; /* Transparent Dark */
        border: 1px solid rgba(46, 204, 113, 0.2) !important; /* Green Border */
        padding: 15px;
        margin-bottom: 15px;
        border-radius: 8px;
    }

    /* 5. Add New Lot Button (Green Button) */
    body.dark .add-row-btn {
        background-color: #2ecc71 !important; /* Bright Green BG */
        border-color: #2ecc71 !important;
        color: #0b1a12 !important; /* Dark Text for contrast */
    }
    body.dark .add-row-btn:hover {
        background-color: #27ae60 !important; /* Slightly darker green on hover */
        border-color: #27ae60 !important;
    }

    /* 6. Fix the Horizontal Lines (HR) */
    body.dark #addContractModal hr {
        border-top-color: rgba(46, 204, 113, 0.2) !important; /* Green HR line */
    }
</style>
                <!-- Footer -->
                <div class="popup-modal-footer">
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

    <script src="js/land_owners.js"></script>
    <script src="js/upload_documents.js"></script>

    <!-- Theme Sync Script (Body Application) -->
    <script>
        // Apply to body now that it exists
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            document.body.classList.add('dark');
        } else {
            document.body.classList.remove('dark');
        }
    </script>
</body>

</html>
