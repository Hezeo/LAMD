<?php
require '../../config.php';
require_once '../../config_session.php';
checkLogin();
requirePermission('Land Owners');

// --- 1. INITIALIZE VARIABLES ---
 $sourceRow = false;

// --- 2. PRIMARY LOOKUP: BY CONTRACT ID ---
// Querying tbl_archived_contracts
if (isset($_GET['contract_id'])) {
    $contract_id_param = intval($_GET['contract_id']);

    $sql_source = "SELECT * FROM tbl_archived_contracts WHERE contract_id = ? LIMIT 1";
    $stmt_source = $pdo->prepare($sql_source);
    $stmt_source->execute([$contract_id_param]);
    $sourceRow = $stmt_source->fetch(PDO::FETCH_ASSOC);
}

// --- 3. FALLBACK LOOKUP: BY ID ---
// Querying tbl_archived_contracts
if (!$sourceRow && isset($_GET['id'])) {
    $record_id_param = intval($_GET['id']);
    $sql_id = "SELECT * FROM tbl_archived_contracts WHERE id = ? LIMIT 1";
    $stmt_id = $pdo->prepare($sql_id);
    $stmt_id->execute([$record_id_param]);
    $sourceRow = $stmt_id->fetch(PDO::FETCH_ASSOC);

    if ($sourceRow) {
        $actual_party = $sourceRow['contracting_party'];
        $actual_start = $sourceRow['start_date'];
        $actual_expiry = $sourceRow['expiry_date'];
        $actual_paid = $sourceRow['paid_up_date'];

        // Reblock logic
        $sql_reblock = "SELECT * FROM tbl_archived_contracts 
                        WHERE contracting_party = ? 
                        AND start_date = ? 
                        AND expiry_date = ? 
                        AND paid_up_date = ?
                        ORDER BY lot_no, field_no";
        $stmt_reblock = $pdo->prepare($sql_reblock);
        $stmt_reblock->execute([$actual_party, $actual_start, $actual_expiry, $actual_paid]);
        $sourceRow = $stmt_reblock->fetch(PDO::FETCH_ASSOC);
    }
}

// --- 4. FINAL ERROR CHECK ---
if (!$sourceRow) {
    // Logic: Try to find the ID passed in the URL to forward it to the other page
    $target_id = isset($_GET['contract_id']) ? $_GET['contract_id'] : (isset($_GET['id']) ? $_GET['id'] : '');
    // Build the link with the ID parameter
    $link_href = "view_contract.php" . (!empty($target_id) ? "?contract_id=" . intval($target_id) : "");

    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
        <title>Archived Record Not Found</title>
        <style>
            body {
                font-family: 'Inter', sans-serif;
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                background-color: #f4f6f8; /* Matches your --bg-body */
                color: #2c3e50;
                margin: 0;
            }
            .error-card {
                background: white;
                padding: 40px;
                border-radius: 12px;
                text-align: center;
                box-shadow: 0 4px 24px rgba(0,0,0,0.06);
                max-width: 450px;
                border-top: 5px solid #2E7D32; /* Matches your --primary */
            }
            .error-icon {
                font-size: 60px;
                color: #2E7D32;
                margin-bottom: 20px;
                display: block;
            }
            .error-title {
                font-size: 1.5rem;
                margin-bottom: 10px;
                font-weight: 700;
            }
            .error-msg {
                font-size: 1rem;
                color: #666;
                margin-bottom: 30px;
                line-height: 1.6;
            }
            .btn-archive {
                display: inline-block;
                background: #2E7D32;
                color: white;
                text-decoration: none;
                padding: 12px 30px;
                border-radius: 6px;
                font-weight: 600;
                transition: 0.2s;
                align-items: center;
                gap: 8px;
            }
            .btn-archive:hover {
                background: #1b5e20;
            }
        </style>
    </head>
    <body>
        <div class="error-card">
            <i class='bx bx-search error-icon'></i>
            <h3 class="error-title">Record Not Found</h3>
            <p class="error-msg">
                We could not locate the requested contract record in the archives.<br><br>
                It may have been restored back to the active database.
            </p>
            <!-- The href now dynamically includes the contract ID -->
            <a href="<?= $link_href ?>" class="btn-archive">
                <i class='bx bx-file-find'></i> View Active Contracts
            </a>
        </div>
    </body>
    </html>
    <?php
    exit; // Stop script execution
}

// --- 5. EXTRACT DATA ---
 $record_id = $sourceRow['id'];
 $old_contracting_party = $sourceRow['contracting_party'];
 $old_start_date = $sourceRow['start_date'];
 $old_expiry_date = $sourceRow['expiry_date'];
 $old_paid_up_date = $sourceRow['paid_up_date'];

// --- 6. FETCH ENTIRE BLOCK ---
// Querying tbl_archived_contracts
 $sql_block = "SELECT * FROM tbl_archived_contracts WHERE contract_id = ? ORDER BY lot_no, field_no";
 $stmt_block = $pdo->prepare($sql_block);
 $stmt_block->execute([$sourceRow['contract_id']]);
 $allRows = $stmt_block->fetchAll(PDO::FETCH_ASSOC);

// --- 6.5 FETCH CONTRACT HISTORY (UPDATED) ---
 $historyData = [];

// 1. Get the Original Creation Date from tbl_archived_contracts (group_acc = NONE)
 $sql_orig_date = "SELECT date_added FROM tbl_archived_contracts WHERE contract_id = ? AND group_acc = 'NONE' LIMIT 1";
 $stmt_orig_date = $pdo->prepare($sql_orig_date);
 $stmt_orig_date->execute([$sourceRow['contract_id']]);
 $original_creation_date = $stmt_orig_date->fetchColumn();

if (!$original_creation_date) {
    $original_creation_date = $sourceRow['date_added'];
}

// 2. Fetch archived versions from BOTH tables (Updated AND Renewed)
// We add a column 'source_table' to identify where the data came from later.

// A. From Updated Contracts
 $sql_updated = "SELECT *, 'tbl_updated_contracts' as source_table FROM tbl_updated_contracts WHERE contract_id = ? ORDER BY date_added ASC";
 $stmt_hist = $pdo->prepare($sql_updated);
 $stmt_hist->execute([$sourceRow['contract_id']]);
 $updatedRows = $stmt_hist->fetchAll(PDO::FETCH_ASSOC);

// B. From Renewed Contracts
 $sql_renewed = "SELECT *, 'tbl_renewed_contracts' as source_table FROM tbl_renewed_contracts WHERE contract_id = ? ORDER BY date_added ASC";
 $stmt_renewed = $pdo->prepare($sql_renewed);
 $stmt_renewed->execute([$sourceRow['contract_id']]);
 $renewedRows = $stmt_renewed->fetchAll(PDO::FETCH_ASSOC);

// C. Merge and Sort
 $allHistoryRows = array_merge($updatedRows, $renewedRows);

// Sort combined array by date_added (Oldest to Newest for grouping)
usort($allHistoryRows, function($a, $b) {
    return strtotime($a['date_added']) - strtotime($b['date_added']);
});

// 3. Group the rows by 'date_added' to create snapshots
 $groupedHistory = [];
foreach ($allHistoryRows as $row) {
    $groupedHistory[$row['date_added']][] = $row;
}

// 4. Sort the snapshot dates DESCENDING (Newest First)
 $dates = array_keys($groupedHistory);
rsort($dates);
 $hasEditHistory = !empty($dates);

// Helper to get a representative row (prefer non-NONE for headers)
 $getRepRow = function ($group) {
    foreach ($group as $r) {
        if ($r['group_acc'] != 'NONE') {
            return $r;
        }
    }
    return $group[0]; // Fallback
};

if (!empty($dates)) {
    // --- CASE: History exists ---

    // A. LATEST EVENT (Newest Archive vs Current Archived)
    $newestDate = $dates[0];
    $newestArchive = $groupedHistory[$newestDate];
    $newestRepRow = $getRepRow($newestArchive);
    
    // Determine Type: Renewed or Updated based on source_table
    $latestType = ($newestRepRow['source_table'] == 'tbl_renewed_contracts') ? 'renewed' : 'updated';

    $historyData[] = [
        'type' => $latestType,
        'source_table' => $newestRepRow['source_table'],
        'display_date' => $newestDate,
        'user_id' => $sourceRow['user_id'], // User from the archived table
        'from_data' => $newestRepRow,    // State Before (Newest Archive)
        'to_data' => $sourceRow         // State After (Current Archived Record)
    ];

    // B. PREVIOUS EVENTS
    for ($i = 0; $i < count($dates) - 1; $i++) {
        $newerDate = $dates[$i];
        $olderDate = $dates[$i + 1];

        $newerArchive = $groupedHistory[$newerDate];
        $olderArchive = $groupedHistory[$olderDate];

        $newerRepRow = $getRepRow($newerArchive);
        $olderRepRow = $getRepRow($olderArchive);

        // Determine Type based on the OLDER snapshot
        $eventHistoryType = ($olderRepRow['source_table'] == 'tbl_renewed_contracts') ? 'renewed' : 'updated';

        $historyData[] = [
            'type' => $eventHistoryType,
            'source_table' => $olderRepRow['source_table'],
            'display_date' => $olderDate,
            'user_id' => $olderRepRow['user_id'],
            'from_data' => $olderRepRow, 
            'to_data' => $newerRepRow    
        ];
    }

    // C. THE "ADDED" CONTAINER
    $oldestDate = $dates[count($dates) - 1];
    $oldestArchive = $groupedHistory[$oldestDate];
    $oldestRepRow = $getRepRow($oldestArchive);

    $historyData[] = [
        'type' => 'added',
        'source_table' => $oldestRepRow['source_table'],
        'display_date' => $original_creation_date,
        'user_id' => $oldestRepRow['user_id'],
        'data' => $oldestRepRow
    ];
} else {
    // --- CASE: No history yet ---
    $historyData[] = [
        'type' => 'added',
        'source_table' => 'tbl_archived_contracts',
        'display_date' => $original_creation_date,
        'user_id' => $sourceRow['user_id'],
        'data' => $sourceRow
    ];
}

// --- 6.5 FETCH SCANNED DOCUMENTS (UPDATED: Latest Batch Only) ---
 $documents = [];
if ($sourceRow['contract_id']) {
    try {
        // We use a subquery to find the MAX date_added for this specific contract.
        $sql_docs = "SELECT * FROM tbl_documents 
                         WHERE contract_id = ? 
                         AND date_added = (
                             SELECT MAX(date_added) 
                             FROM tbl_documents 
                             WHERE contract_id = ?
                         ) 
                         ORDER BY doc_id DESC";
        
        $stmt_docs = $pdo->prepare($sql_docs);
        $stmt_docs->execute([$sourceRow['contract_id'], $sourceRow['contract_id']]);
        $documents = $stmt_docs->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Ignore error
    }
}

// --- 7. DATA RESTRUCTURING ---
 $lotsData = [];
foreach ($allRows as $row) {
    $lKey = $row['lot_no'];
    if (!isset($lotsData[$lKey])) {
        $lotsData[$lKey] = [
            'lot_no' => $row['lot_no'],
            'field_no' => $row['field_no'],
            'field_section' => $row['field_section'],
            'province' => $row['province'],
            'municipality' => $row['municipality'],
            'barangay' => $row['barangay'],
            'longlat' => $row['longlat'],
            'rate' => $row['rate'],
            'crop_type' => $row['crop_type'],
            'lease_status' => $row['lease_status'],
            'contract_status' => $row['contract_status'],
            'contract_class' => $row['contract_class'],
            'groups' => []
        ];
    }
    $lotsData[$lKey]['groups'][] = [
        'id' => $row['id'],
        'name' => $row['group_acc'],
        'arable' => $row['contracted_arable']
    ];
}
 $lotsData = array_values($lotsData);

// GOOGLE EARTH - MULTIPLE LOTS START
 $allContractLonglats = [];
foreach ($lotsData as $lot) {
    if (!empty($lot['longlat'])) {
        $allContractLonglats[] = $lot['longlat'];
    }
}

// Only show the button if there are 2 or more lots with coordinates
 $hasMultipleGeoLocations = count($allContractLonglats) >= 1;

// Construct query string: coords separated by space (Standard for search queries)
 $geoSearchQuery = urlencode(implode(' ', $allContractLonglats));

// GOOGLE EARTH - MULTIPLE LOTS END

function e($str)
{
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function getUserDetails($pdo, $user_id)
{
    if (!$user_id) return "Unknown User";
    $stmt = $pdo->prepare("SELECT first_name, middle_initial, last_name FROM tbl_users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $name = trim($user['first_name'] . ' ' . ($user['middle_initial'] ? $user['middle_initial'] . '. ' : '') . $user['last_name']);
        return $name;
    }
    return "Unknown User";
}

function getHistoryDocuments($pdo, $contract_id, $target_date)
{
    $docs = [];
    if (empty($target_date)) return $docs;

    try {
        // Fetch documents strictly matching the target date_added
        $sql = "SELECT * FROM tbl_documents 
                WHERE contract_id = ? 
                AND date_added = ? 
                ORDER BY doc_id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$contract_id, $target_date]);
        $docs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Ignore error
    }
    return $docs;
}

function renderCompactContractView($data, $pdo, $sourceTable = 'tbl_main', $doc_date = null)
{
    // Fetch the full block of rows for this contract snapshot
    $contract_id = $data['contract_id'];
    $snap_rows = [];

    if ($sourceTable == 'tbl_main') {
        $sql = "SELECT * FROM tbl_main WHERE contract_id = ? ORDER BY lot_no, field_no";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$contract_id]);
        $snap_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($sourceTable == 'tbl_archived_contracts') {
        // Archived State: Get all rows from archived table for this contract
        $sql = "SELECT * FROM tbl_archived_contracts WHERE contract_id = ? ORDER BY lot_no, field_no";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$contract_id]);
        $snap_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // History State: Use the dynamic $sourceTable (could be updated OR renewed)
        $sql = "SELECT * FROM " . $sourceTable . " WHERE contract_id = ? AND date_added = ? ORDER BY lot_no, field_no";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$contract_id, $data['date_added']]);
        $snap_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Structure the data exactly like the Main View (Step 7)
    $lotsData = [];
    foreach ($snap_rows as $row) {
        $lKey = $row['lot_no'];
        if (!isset($lotsData[$lKey])) {
            $lotsData[$lKey] = [
                'lot_no' => $row['lot_no'],
                'field_no' => $row['field_no'],
                'field_section' => $row['field_section'],
                'province' => $row['province'],
                'municipality' => $row['municipality'],
                'barangay' => $row['barangay'],
                'longlat' => $row['longlat'],
                'rate' => $row['rate'],
                'crop_type' => $row['crop_type'],
                'lease_status' => $row['lease_status'],
                'contract_status' => $row['contract_status'],
                'contract_class' => $row['contract_class'],
                'groups' => []
            ];
        }
        // Add ALL groups found for this lot
        $lotsData[$lKey]['groups'][] = [
            'id' => $row['id'],
            'name' => $row['group_acc'],
            'arable' => $row['contracted_arable']
        ];
    }
    $lotsData = array_values($lotsData);

    // --- RENDERING LOGIC ---
    ob_start();
?>
    <div class="history-view">
        <!-- 1. CONTRACT DETAILS -->
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Contracting Party</span>
                <span class="info-value" style="color: var(--primary-dark); font-weight:600;"><?= e($data['contracting_party']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">CMS Application No.</span>
                <span class="info-value"><?= e($data['cms_application_no']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Vendor ID</span>
                <span class="info-value"><?= e($data['vendor']) ?></span>
            </div>
        </div>

        <!-- 3. LOTS LOOP -->
        <?php foreach ($lotsData as $lot): ?>
            <div class="lot-card">
                <div class="lot-card-header">
                    <span class="lot-title"><i class='bx bx-map'></i> Lot <?= e($lot['lot_no']) ?></span>
                </div>
                <div class="lot-card-body" style="padding: 15px;">
                    <!-- Lot Basic Info -->
                    <div class="info-grid" style="margin-bottom: 10px;">
                        <div class="info-item">
                            <span class="info-label">Field No</span>
                            <span class="info-value"><?= e($lot['field_no']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Field Section</span>
                            <span class="info-value"><?= e($lot['field_section']) ?></span>
                        </div>
                    </div>

                    <!-- Location -->
                    <div class="info-grid" style="margin-bottom: 10px;">
                        <div class="info-item">
                            <span class="info-label">Province</span>
                            <span class="info-value"><?= e($lot['province']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Municipality</span>
                            <span class="info-value"><?= e($lot['municipality']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Barangay</span>
                            <span class="info-value"><?= e($lot['barangay']) ?></span>
                        </div>
                    </div>

                    <div class="info-grid" style="margin-bottom: 10px;">
                        <div class="info-item">
                            <span class="info-label">Long/Lat</span>
                            <span class="info-value"><?= e($lot['longlat']) ?></span>
                        </div>
                    </div>

                    <!-- Financials -->
                    <div class="info-grid" style="margin-bottom: 10px;">
                        <div class="info-item">
                            <span class="info-label">Rate</span>
                            <span class="info-value">₱<?= number_format($lot['rate'], 2) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Crop Type</span>
                            <span class="info-value"><?= e($lot['crop_type']) ?></span>
                        </div>
                    </div>

                    <!-- Statuses -->
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Lease Status</span>
                            <span class="info-value"><?= e($lot['lease_status']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Contract Status</span>
                            <span class="info-value"><?= e($lot['contract_status']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Class</span>
                            <span class="info-value"><?= e($lot['contract_class']) ?></span>
                        </div>
                    </div>

                    <!-- GROUPS -->
                    <?php if (!empty($lot['groups'])): ?>
                        <div class="group-section-title" style="margin-top:10px; font-size: 0.75rem;">Group Accounts</div>
                        <div class="group-table">
                            <div class="group-row" style="background-color: #f1f8e9; font-size: 0.75rem;">
                                <div>Account Name</div>
                                <div style="text-align: right;">Arable (HAS)</div>
                            </div>
                            <?php foreach ($lot['groups'] as $group): ?>
                                <div class="group-row" style="font-size: 0.8rem;">
                                    <div class="group-name"><?= e($group['name']) ?></div>
                                    <div class="group-arable"><?= number_format($group['arable'], 4) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- 2. INCEPTION DATES -->
        <div class="info-grid" style="margin-top: 20px; background: #fafafa; padding: 10px; border-radius: 6px;">
            <div class="info-item">
                <span class="info-label">Start Date</span>
                <span class="info-value"><?= e($data['start_date']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Expiry Date</span>
                <span class="info-value"><?= e($data['expiry_date']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Paid Up Date</span>
                <span class="info-value"><?= e($data['paid_up_date']) ?: 'N/A' ?></span>
            </div>
        </div>

        <?php 
        // --- HISTORY DOCUMENTS LOGIC ---
        // Use the specific $doc_date passed, otherwise fallback to the row's own date_added
        $target_doc_date = ($doc_date) ? $doc_date : $data['date_added'];
        
        $snapshotDocuments = getHistoryDocuments($pdo, $data['contract_id'], $target_doc_date);
        
        if (!empty($snapshotDocuments)): 
        ?>
            <div class="info-grid" style="margin-top: 15px; border-top: 1px solid #eee; padding-top: 10px;">
                <div class="info-item">
                    <span class="info-label">Attached Documents (<?= count($snapshotDocuments) ?>)</span>
                </div>
            </div>
            
            <div class="preview-grid" style="margin-top: 10px;">
                <?php foreach ($snapshotDocuments as $doc):
                    $ext = strtolower(pathinfo($doc['file_path'], PATHINFO_EXTENSION));
                    $is_image = in_array($ext, ['jpg', 'jpeg', 'png']);
                ?>
                    <a href="../documents/<?= $doc['file_path'] ?>" target="_blank" class="doc-preview-item">
                        <?php if ($is_image): ?>
                            <div class="img-preview-box">
                                <img src="../documents/<?= $doc['file_path'] ?>" alt="<?= htmlspecialchars($doc['file_name']) ?>">
                            </div>
                        <?php else: ?>
                            <div class="img-preview-box">
                                <?php if ($ext == 'pdf'): ?>
                                    <i class='bx bxs-file-pdf' style="color:#d32f2f; font-size: 3rem;"></i>
                                <?php elseif (in_array($ext, ['doc', 'docx'])): ?>
                                    <i class='bx bxs-file-doc' style="color:#1976d2; font-size: 3rem;"></i>
                                <?php else: ?>
                                    <i class='bx bxs-file-blank' style="color:#666; font-size: 3rem;"></i>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <div class="filename-info">
                            <div class="filename-text"><?= htmlspecialchars($doc['file_name']) ?></div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; 
        // --- END DOCUMENTS LOGIC ---
        ?>
    </div>
<?php
    return ob_get_clean();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../images/web_icon.png">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="css/land_owners.css">
    <title>Archived Contract | Land Asset Management</title>
    <style>
        :root {
            /* Using Gray Theme for Archived */
            --primary: #546e7a; 
            --primary-light: #eceff1;
            --primary-dark: #37474f;
            --text-dark: #2c3e50;
            --text-med: #546e7a;
            --text-light: #90a4ae;
            --border-color: #eceff1;
            --bg-body: #f4f6f8;
            --bg-card: #ffffff;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--bg-body);
            margin: 0;
            padding: 40px;
            color: var(--text-dark);
        }

        /* --- MAIN CONTAINER --- */
        .view-container {
            max-width: 1100px;
            margin: 0 auto;
            background: var(--bg-card);
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.06);
            overflow: hidden;
        }

        /* --- HEADER --- */
        .view-header {
            background: linear-gradient(135deg, #78909c 0%, #455a64 100%);
            /* Gray header */
            color: white;
            padding: 30px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-info h1 {
            margin: 0 0 5px 0;
            font-size: 1.5rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header-info p {
            margin: 0;
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .btn-close {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.4);
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: 0.2s;
        }

        .btn-close:hover {
            background: white;
            color: #455a64;
        }

        /* --- SECTION STYLES --- */
        .section-content {
            padding: 40px;
        }

        .section-title {
            color: var(--primary-dark);
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
            background: var(--primary);
            border-radius: 2px;
        }

        /* --- INFO GRID --- */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 24px;
            margin-bottom: 30px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .info-label {
            font-size: 0.8rem;
            color: var(--text-light);
            font-weight: 500;
            text-transform: uppercase;
        }

        .info-value {
            font-size: 1rem;
            color: var(--text-dark);
            font-weight: 500;
        }

        .info-value.highlight {
            font-weight: 700;
            color: var(--primary);
            font-size: 1.1rem;
        }

        /* --- LOT CARDS --- */
        .lot-card {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            margin-bottom: 30px;
            overflow: hidden;
        }

        .lot-card-header {
            background: #fafafa;
            border-bottom: 1px solid var(--border-color);
            padding: 15px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .lot-title {
            font-weight: 700;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .lot-card-body {
            padding: 24px;
        }

        /* --- GROUP ACCOUNTS TABLE --- */
        .group-section-title {
            font-size: 0.85rem;
            color: var(--text-med);
            font-weight: 600;
            margin-bottom: 12px;
            margin-top: 10px;
            padding-bottom: 8px;
            border-bottom: 2px solid #eceff1;
        }

        .group-table {
            width: 100%;
            border-collapse: collapse;
        }

        .group-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            align-items: center;
            font-size: 0.95rem;
        }

        .group-row:last-child {
            border-bottom: none;
        }

        .group-row:nth-child(even) {
            background-color: #fafafa;
        }

        .group-name {
            color: var(--text-dark);
            font-weight: 500;
        }

        .group-arable {
            color: var(--text-med);
            font-family: 'Consolas', monospace;
            text-align: right;
        }

        /* --- ACTION BUTTONS --- */
        .actions-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
        }

        .btn-action {
            padding: 8px 18px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            transition: all 0.2s;
        }

        /* Restore Button */
        .btn-restore {
            background-color: #2E7D32;
            color: white;
            border: 1px solid #2E7D32;
        }

        .btn-restore:hover {
            background-color: #1b5e20;
            box-shadow: 0 2px 8px rgba(46, 125, 50, 0.2);
        }

        /* --- MODAL STYLES --- */
        .simple-modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(2px);
        }

        .simple-modal-overlay.active {
            display: flex;
        }

        .simple-modal-box {
            background: white;
            padding: 30px;
            border-radius: 12px;
            width: 90%;
            max-width: 400px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            animation: zoomIn 0.2s ease-out;
        }

        .simple-modal-box h3 {
            margin: 0 0 10px 0;
            color: #333;
            font-size: 1.25rem;
        }

        .simple-modal-box p {
            color: #666;
            margin-bottom: 25px;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .modal-actions {
            display: flex;
            justify-content: center;
            gap: 15px;
        }

        .btn-modal {
            padding: 10px 30px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            flex: 1;
        }

        .btn-modal-cancel {
            background: #f5f5f5;
            color: #333;
            border: none;
        }

        .btn-modal-cancel:hover {
            background: #e0e0e0;
        }

        .btn-modal-restore {
            background: #2E7D32;
            color: white;
            border: none;
        }

        .btn-modal-restore:hover {
            background: #1b5e20;
        }

        .btn-modal-restore:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        @keyframes zoomIn {
            from {
                transform: scale(0.9);
                opacity: 0;
            }

            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        /* --- HISTORY SECTION STYLES --- */
        .history-toggle-wrapper {
            margin-top: 20px;
            text-align: center;
            position: relative;
        }

        .history-line {
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #eceff1;
            z-index: 1;
        }

        .btn-history-toggle {
            background: var(--bg-card);
            border: 1px solid #cfd8dc;
            padding: 8px 24px;
            border-radius: 20px;
            color: var(--text-med);
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            position: relative;
            z-index: 2;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .btn-history-toggle:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .btn-history-toggle i {
            transition: transform 0.3s;
        }

        .btn-history-toggle.active i {
            transform: rotate(180deg);
        }

        .history-content {
            display: none;
        }

        .history-content.open {
            display: block;
        }

        .history-inner {
            padding-top: 30px;
            padding-bottom: 10px;
        }

        .history-item {
            margin-bottom: 40px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            overflow: hidden;
        }

        .history-item-narrow {
            max-width: 50%;
            margin: 0 auto 40px auto;
        }

        .history-header {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            color: var(--text-med);
        }

        .history-header-top {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .history-user-details {
            display: flex;
            flex-direction: column;
            gap: 2px;
            align-items: flex-start;
        }

        .history-user-name {
            font-weight: 700;
            color: var(--text-dark);
        }

        .history-action-badge {
            font-weight: 700;
            text-transform: uppercase;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
        }

        .badge-added {
            background: #e3f2fd;
            color: #1976d2;
        }

        .badge-updated {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .badge-renewed {
            background: #fff3e0;
            color: #ef6c00;
            border: 1px solid #ffe0b2;
        }

        .history-user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .history-meta-text {
            font-size: 0.85rem;
            font-weight: 400;
            color: #78909c;
        }

        .history-meta-text-date {
            font-weight: 500;
        }

        .comparison-grid {
            display: grid;
            grid-template-columns: 1fr 60px 1fr;
            gap: 20px;
            padding: 25px;
            background: #fff;
            align-items: start;
        }

        .comp-col {
            font-size: 0.85rem;
            border: 1px solid #eee;
            border-radius: 8px;
            overflow: hidden;
            background: #fff;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.02);
            display: flex;
            flex-direction: column;
        }

        .comp-col-title {
            background: #e0e0e0;
            color: #333;
            padding: 10px;
            text-align: center;
            font-weight: 800;
            text-transform: uppercase;
            font-size: 0.9rem;
            letter-spacing: 1px;
            border-bottom: 1px solid #d6d6d6;
        }

        .comp-divider {
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 2rem;
        }

        .history-view {
            padding: 0;
            background: transparent;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .history-view .info-grid {
            gap: 15px;
            margin-bottom: 0;
            padding: 15px 20px;
        }

        .history-view .section-title {
            display: none;
        }

        .history-view .info-label {
            font-size: 0.7rem;
        }

        .history-view .info-value {
            font-size: 0.85rem;
        }

        .history-view .lot-card {
            border: none;
            box-shadow: none;
            margin-bottom: 0;
            padding: 0 15px 15px 15px;
        }

        .history-view .lot-card-header {
            background: #fff;
            padding: 5px 0;
            margin-bottom: 5px;
        }

        .history-view .lot-card-body {
            padding: 0;
        }

        .history-view .info-grid[style*="background: #fafafa"] {
            margin-top: auto;
            padding: 15px 20px;
        }

        /* --- CLEAN DOCUMENT PREVIEW STYLES --- */
        .preview-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-top: 15px;
            align-items: flex-start;
        }

        .doc-preview-item {
            position: relative;
            display: inline-flex;
            flex-direction: column;
            padding: 8px;
            border: none;
            background: transparent;
            text-decoration: none;
            color: inherit;
        }

        .img-preview-box {
            height: 200px;
            width: auto;
            background: #f9f9f9;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            margin-bottom: 8px;
        }

        .img-preview-box img {
            width: auto;
            height: 100%;
            object-fit: contain;
            border-radius: 4px;
            display: block;
        }

        .img-preview-box i {
            font-size: 50px;
            color: #666;
        }

        .filename-info {
            text-align: center;
            position: relative;
            padding: 0 4px;
        }

        .filename-text {
            font-size: 0.8rem;
            color: #333;
            display: block;
            width: 100%;
            line-height: 1.3;
            height: 2.6em;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .filename-tooltip {
            position: absolute;
            bottom: 105%;
            left: 50%;
            transform: translateX(-50%);
            background: #333;
            color: #fff;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 0.8rem;
            white-space: normal;
            z-index: 10;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.2s;
            min-width: 100px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.25);
            pointer-events: none;
        }

        .doc-preview-item:hover .filename-tooltip {
            opacity: 1;
            visibility: visible;
        }
    </style>
</head>

<body class="sidebar-closed">

    <div class="view-container">
        <!-- HEADER -->
        <div class="view-header">
            <div class="header-info">
                <h1><i class='bx bx-archive-in'></i> Archived Contract</h1>
                <p>Contracting Party: <strong><?= e($old_contracting_party) ?></strong></p>
            </div>
            <button onclick="window.close()" class="btn-close">
                <i class='bx bx-x'></i> Close
            </button>
        </div>

        <div class="section-content">

            <!-- 1. CONTRACT DETAILS -->
            <div class="section-title">Contract Information</div>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">CMS Application No.</span>
                    <span class="info-value highlight"><?= e($sourceRow['cms_application_no']) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Vendor ID</span>
                    <span class="info-value"><?= e($sourceRow['vendor']) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Status</span>
                    <span class="info-value" style="color: #d32f2f; font-weight:600;">Archived</span>
                </div>
            </div>

            <!-- 3. LOTS LOOP -->
            <div class="section-title">Lots & Group Accounts</div>

            <?php if (empty($lotsData)): ?>
                <div style="text-align:center; padding:40px; color: #999;">No lot details found.</div>
            <?php else: ?>

                <?php foreach ($lotsData as $index => $lot): ?>
                    <div class="lot-card">
                        <div class="lot-card-header">
                            <span class="lot-title"><i class='bx bx-map'></i> Lot <?= e($lot['lot_no']) ?></span>
                        </div>
                        <div class="lot-card-body">

                            <!-- Lot Basic Info -->
                            <div class="info-grid" style="margin-bottom: 20px;">
                                <div class="info-item">
                                    <span class="info-label">Field No</span>
                                    <span class="info-value"><?= e($lot['field_no']) ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Field Section</span>
                                    <span class="info-value"><?= e($lot['field_section']) ?></span>
                                </div>
                            </div>

                            <!-- Location -->
                            <div class="info-grid" style="margin-bottom: 20px;">
                                <div class="info-item">
                                    <span class="info-label">Province</span>
                                    <span class="info-value"><?= e($lot['province']) ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Municipality</span>
                                    <span class="info-value"><?= e($lot['municipality']) ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Barangay</span>
                                    <span class="info-value"><?= e($lot['barangay']) ?></span>
                                </div>
                            </div>

                            <div class="info-grid" style="margin-bottom: 20px;">
                                <div class="info-item">
                                    <span class="info-label">Long/Lat</span>
                                    <div class="info-value" style="display: flex; align-items: center; gap: 8px;">
                                        <span><?= e($lot['longlat']) ?></span>
                                        <?php if (!empty($lot['longlat'])): ?>
                                            <a href="https://earth.google.com/web/search/<?= urlencode($lot['longlat']) ?>" target="_blank" style="color: var(--primary); text-decoration: none; font-size: 1.2rem;" title="View in Google Earth">
                                                <img src="../images/GOOGLE_EARTH_LOGO.png" style="height: 20px; width: 20px; border-radius: 50%;">
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Financials -->
                            <div class="info-grid" style="margin-bottom: 20px;">
                                <div class="info-item">
                                    <span class="info-label">Rate</span>
                                    <span class="info-value">₱<?= number_format($lot['rate'], 2) ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Crop Type</span>
                                    <span class="info-value"><?= e($lot['crop_type']) ?></span>
                                </div>
                            </div>

                            <!-- Statuses -->
                            <div class="info-grid" style="margin-bottom: 30px;">
                                <div class="info-item">
                                    <span class="info-label">Lease Status</span>
                                    <span class="info-value" style="color: #d32f2f; font-weight:600;"><?= e($lot['lease_status']) ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Contract Status</span>
                                    <span class="info-value"><?= e($lot['contract_status']) ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Class</span>
                                    <span class="info-value"><?= e($lot['contract_class']) ?></span>
                                </div>
                            </div>

                            <!-- GROUP ACCOUNTS -->
                            <?php if (!empty($lot['groups'])): ?>
                                <div class="group-section-title">Group Accounts (<?= count($lot['groups']) ?>)</div>

                                <div class="group-table">
                                    <!-- Header Row -->
                                    <div class="group-row" style="background-color: #f1f8e9; font-weight: 700; color: var(--primary-dark); font-size: 0.85rem; text-transform: uppercase;">
                                        <div>Account Name</div>
                                        <div style="text-align: right;">Arable (HAS)</div>
                                    </div>

                                    <!-- Data Rows -->
                                    <?php foreach ($lot['groups'] as $group): ?>
                                        <div class="group-row">
                                            <div class="group-name"><?= e($group['name']) ?></div>
                                            <div class="group-arable"><?= number_format($group['arable'], 4) ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>



            <!-- 2. INCEPTION DATES -->
            <div class="section-title">Inception Dates</div>
            <div class="info-grid" style="margin-bottom: 40px; background: #eeeeee; padding: 20px; border-radius: 8px;">
                <div class="info-item">
                    <span class="info-label">Start Date</span>
                    <span class="info-value" style="color: #333; font-weight:600;"><?= e($old_start_date) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Expiry Date</span>
                    <span class="info-value" style="color: #333; font-weight:600;"><?= e($old_expiry_date) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Paid Up Date</span>
                    <span class="info-value"><?= e($old_paid_up_date) ?: 'N/A' ?></span>
                </div>
            </div>

            <!-- SUPPORTING DOCUMENTS SECTION -->
<div class="section-title">Supporting Documents</div>

<?php if (empty($documents)): ?>
    <div style="padding: 20px; color: #999; font-style: italic;">No documents uploaded.</div>
<?php else: ?>
    <div class="preview-grid">
        <?php foreach ($documents as $doc):
            $ext = strtolower(pathinfo($doc['file_path'], PATHINFO_EXTENSION));
            $is_image = in_array($ext, ['jpg', 'jpeg', 'png']);
        ?>
            <a href="../documents/<?= $doc['file_path'] ?>" target="_blank" class="doc-preview-item">
                <?php if ($is_image): ?>
                    <div class="img-preview-box">
                        <img src="../documents/<?= $doc['file_path'] ?>" alt="<?= htmlspecialchars($doc['file_name']) ?>">
                    </div>
                <?php else: ?>
                    <div class="img-preview-box">
                        <?php if ($ext == 'pdf'): ?>
                            <i class='bx bxs-file-pdf' style="color:#d32f2f; font-size: 3rem;"></i>
                        <?php elseif (in_array($ext, ['doc', 'docx'])): ?>
                            <i class='bx bxs-file-doc' style="color:#1976d2; font-size: 3rem;"></i>
                        <?php else: ?>
                            <i class='bx bxs-file-blank' style="color:#666; font-size: 3rem;"></i>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <div class="filename-info">
                    <div class="filename-text"><?= htmlspecialchars($doc['file_name']) ?></div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<!-- END SCANNED DOCUMENTS SECTION -->
 
            <!-- ACTION BUTTONS (Restored & Google Earth Only) -->
            <div class="actions-container" style="justify-content: space-between;">

                <!-- LEFT SIDE: Google Earth Button -->
                <?php if ($hasMultipleGeoLocations): ?>
                    <a href="https://earth.google.com/web/search?q=<?= $geoSearchQuery ?>" target="_blank"
                        style="display: inline-flex; align-items: center; gap: 8px;
              background: transparent;
              color: #0056b3;
              border: 1px solid #d1d5db;
              text-decoration: none; padding: 8px 18px; border-radius: 50px; font-weight: 600; font-size: 0.85rem; transition: all 0.2s; cursor: pointer;"
                        onmouseover="this.style.borderColor='#3498db'; this.style.color='#3498db'; this.style.boxShadow='0 0 10px rgba(52,152,219,0.5)'"
                        onmouseout="this.style.borderColor='#d1d5db'; this.style.color='#0056b3'; this.style.boxShadow='none'">
                        <img src="../images/GOOGLE_EARTH_LOGO.png" style="height: 20px; width: 20px; object-fit: contain;">
                        View All Lots on Google Earth
                    </a>
                <?php endif; ?>

                <!-- RIGHT SIDE: Restore Button -->
                <div style="display: flex; align-items: center; gap: 12px;">
                    <!-- Restore Contract -->
                    <button onclick="openRestoreModal()" class="btn-action btn-restore">
                        <i class='bx bx-undo'></i> Restore Contract
                    </button>
                </div>

            </div>

        </div>
    </div>

    <!-- HISTORY SECTION -->
    <div class="history-toggle-wrapper">
        <div class="history-line"></div>
        <button id="btnHistoryToggle" class="btn-history-toggle" onclick="toggleHistory()">
            Show Contract Edit History <i class='bx bx-chevron-down'></i>
        </button>
    </div>

    <div id="historyContent" class="history-content">
        <div class="history-inner">
            <?php if ($hasEditHistory): ?>

                <!-- LOOP -->
                <?php foreach ($historyData as $i => $item): ?>
                    <div class="history-item <?= ($item['type'] == 'added') ? 'history-item-narrow' : '' ?>">
                        <!-- Header -->
                        <div class="history-header">
                            <div class="history-header-top">
                                <span class="history-action-badge 
                                    <?= $item['type'] == 'added' ? 'badge-added' : ($item['type'] == 'renewed' ? 'badge-renewed' : 'badge-updated') ?>">
                                    <?= strtoupper($item['type']) ?>
                                </span>
                            </div>

                            <div class="history-user-details">
                                <span class="history-user-name">
                                    <?= getUserDetails($pdo, $item['user_id']) ?>
                                    <span class="history-meta-text">
                                        <?= $item['type'] == 'added' ? 'added this contract' : ($item['type'] == 'renewed' ? 'renewed this contract' : 'updated this contract') ?> on
                                        <span class="history-meta-text-date">
                                            <?= date('M d, Y h:i A', strtotime($item['display_date'])) ?>
                                        </span>
                                    </span>
                                </span>
                            </div>
                        </div>

                        <!-- Body -->
                        <?php if ($item['type'] != 'added'): ?>
                            <div class="comparison-grid">
                                <!-- FROM -->
                                <div class="comp-col">
                                    <div class="comp-col-title">From</div>
                                    <?php 
                                       // Determine Doc Date for "FROM" column
                                       if ($item === $historyData[0]) {
                                           $nextItem = isset($historyData[$i+1]) ? $historyData[$i+1] : null;
                                           $fromDocDate = ($nextItem) ? $nextItem['display_date'] : $item['from_data']['date_added'];
                                       } else {
                                           $nextItem = isset($historyData[$i+1]) ? $historyData[$i+1] : null;
                                           $fromDocDate = ($nextItem) ? $nextItem['display_date'] : $item['from_data']['date_added'];
                                       }
                                    ?>
                                    <?= renderCompactContractView($item['from_data'], $pdo, $item['source_table'], $fromDocDate) ?>
                                </div>

                                <!-- Divider -->
                                <div class="comp-divider">
                                    <i class='bx bx-right-arrow-alt'></i>
                                </div>

                                <!-- TO -->
                                <div class="comp-col">
                                    <div class="comp-col-title">To</div>
                                    <?php 
                                       // Determine table for "TO" column
                                       if ($item === $historyData[0]) {
                                           $toTable = 'tbl_archived_contracts'; // CRITICAL: Use archived table for current state
                                       } else {
                                           $toTable = isset($item['to_data']['source_table']) ? $item['to_data']['source_table'] : 'tbl_updated_contracts';
                                       }
                                       
                                       // Determine Doc Date for "TO" column
                                       $toDocDate = $item['display_date'];
                                    ?>
                                    
                                    <?= renderCompactContractView($item['to_data'], $pdo, $toTable, $toDocDate) ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- ADDED (Single View) -->
                            <div style="padding: 20px;">
                                <?= renderCompactContractView($item['data'], $pdo, $item['source_table'], $item['display_date']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <!-- END LOOP -->

            <?php else: ?>

                <div style="text-align: center; padding: 40px; color: #999; font-style: italic; font-size: 1rem;">
                    <i class='bx bx-history' style="font-size: 100px; color: #ccc; display: block; margin-bottom: 10px;"></i>
                    No edit history for this contract.
                </div>

            <?php endif; ?>
        </div>
    </div>

    <!-- MODAL (Restore Confirmation) -->
    <div id="restoreModal" class="simple-modal-overlay">
        <div class="simple-modal-box">
            <i class='bx bx-undo' style="font-size: 50px; color: #2E7D32; margin-bottom: 15px;"></i>
            <h3>Confirm Restoration</h3>
            <p>Are you sure you want to restore this contract? It will be moved back to the active list.</p>

            <div class="modal-actions">
                <button class="btn-modal btn-modal-cancel" onclick="closeRestoreModal()">Cancel</button>
                <button class="btn-modal btn-modal-restore" onclick="confirmRestore()">Restore</button>
            </div>
        </div>
    </div>

    <script>
        // Store current contract data for Actions
        const currentContract = {
            contract_id: "<?= e($sourceRow['contract_id']) ?>",
            party: "<?= e($old_contracting_party) ?>"
        };

        // --- RESTORE MODAL FUNCTIONS ---
        function openRestoreModal() {
            document.getElementById('restoreModal').classList.add('active');
        }

        function closeRestoreModal() {
            document.getElementById('restoreModal').classList.remove('active');
        }

        function confirmRestore() {
            const restoreBtn = document.querySelector('.btn-modal-restore');
            const originalText = restoreBtn.innerText;

            // Loading state
            restoreBtn.innerText = "Restoring...";
            restoreBtn.disabled = true;
            restoreBtn.style.opacity = "0.7";

            const formData = new FormData();
            formData.append('restore_contract_block', '1'); 
            formData.append('contract_id', currentContract.contract_id); 

            // Assuming you have a restore action file. If not, you need to create it.
            fetch('../actions/restore_contract_action.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert("Contract restored successfully.");
                        // Reload parent and close
                        if (window.opener) {
                            window.opener.location.reload();
                        }
                        window.close();
                    } else {
                        alert("Error: " + (data.message || "Could not restore record."));
                        // Reset button
                        restoreBtn.innerText = originalText;
                        restoreBtn.disabled = false;
                        restoreBtn.style.opacity = "1";
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert("Network Error.");
                    // Reset button
                    restoreBtn.innerText = originalText;
                    restoreBtn.disabled = false;
                    restoreBtn.style.opacity = "1";
                });
        }

        // Close modal on outside click
        window.onclick = function(event) {
            const modal = document.getElementById('restoreModal');
            if (event.target == modal) {
                closeRestoreModal();
            }
        }

        // --- HISTORY TOGGLE LOGIC ---
        function toggleHistory() {
            const content = document.getElementById('historyContent');
            const btn = document.getElementById('btnHistoryToggle');

            content.classList.toggle('open');
            btn.classList.toggle('active');

            if (content.classList.contains('open')) {
                btn.innerHTML = 'Hide Contract Edit History <i class=\'bx bx-chevron-up\'></i>';
            } else {
                btn.innerHTML = 'Show Contract Edit History <i class=\'bx bx-chevron-down\'></i>';
            }
        }
    </script>
</body>
</html>