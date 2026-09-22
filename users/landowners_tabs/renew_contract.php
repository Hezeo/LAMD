<?php
require '../../config.php';
require_once '../../config_session.php';
checkLogin();
requirePermission('Land Owners');

// --- 1. INITIALIZE VARIABLES ---
$sourceRow = false;
$old_contract_id = null; // Store this for the Action script

// --- 2. PRIMARY LOOKUP: BY CONTRACT ID ---
// This matches the new logic in land_owners.js
if (isset($_GET['contract_id'])) {
    $contract_id_param = htmlspecialchars($_GET['contract_id']);

    // SQL: Find ONE record by contract_id to identify the block
    $sql_source = "SELECT * FROM tbl_main WHERE contract_id = ? LIMIT 1";
    $stmt_source = $pdo->prepare($sql_source);
    $stmt_source->execute([$contract_id_param]);
    $sourceRow = $stmt_source->fetch(PDO::FETCH_ASSOC);

    if ($sourceRow) {
        $old_contract_id = $sourceRow['contract_id'];
    }
}

// --- 3. FALLBACK LOOKUP: BY SIGNATURE (Old Logic) ---
// Only runs if contract_id was not found in URL or DB
if (!$sourceRow && isset($_GET['party']) && isset($_GET['start']) && isset($_GET['expiry']) && isset($_GET['paid'])) {
    // ... (Keep your existing fallback code here exactly as is) ...
    $party_name = htmlspecialchars($_GET['party']);
    $start_date = htmlspecialchars($_GET['start']);
    $expiry_date = htmlspecialchars($_GET['expiry']);
    $paid_up_date = htmlspecialchars($_GET['paid']);

    $sql_source = "SELECT * FROM tbl_main 
                        WHERE contracting_party = ? 
                        AND start_date = ? 
                        AND expiry_date = ? 
                        AND (paid_up_date = ? OR (paid_up_date IS NULL AND ? = '')) 
                        LIMIT 1";

    $stmt_source = $pdo->prepare($sql_source);
    $stmt_source->execute([$party_name, $start_date, $expiry_date, $paid_up_date, $paid_up_date]);
    $sourceRow = $stmt_source->fetch(PDO::FETCH_ASSOC);

    if ($sourceRow) {
        // Try to find the ID even if we looked up by dates
        $old_contract_id = $sourceRow['contract_id'];
    }
}

// --- 4. FINAL ERROR CHECK ---
if (!$sourceRow) {
    die("Error: Could not locate the contract record. The link may be broken, or the data has been modified.");
}

// --- 5. EXTRACT DATA FROM SUCCESSFUL LOOKUP ---
// ... (Keep existing variable extraction: $record_id, $old_contracting_party, etc.) ...
$record_id = $sourceRow['id'];
$old_contracting_party = $sourceRow['contracting_party'];
$old_start_date = $sourceRow['start_date'];
$old_expiry_date = $sourceRow['expiry_date'];
$old_paid_up_date = $sourceRow['paid_up_date'];
$old_vendor = $sourceRow['vendor'];

// --- 6. FETCH THE ENTIRE CONTRACT BLOCK ---
// CRITICAL FIX: Use contract_id to fetch the block, NOT dates.
// This ensures that even if dates are changed during the renew, we still renew the correct contract.
if ($old_contract_id) {
    $sql_block = "SELECT * FROM tbl_main WHERE contract_id = ? ORDER BY lot_no, field_no";
    $stmt_block = $pdo->prepare($sql_block);
    $stmt_block->execute([$old_contract_id]);
} else {
    // Fallback if ID is somehow missing
    $sql_block = "SELECT * FROM tbl_main 
                     WHERE contracting_party = ? 
                     AND start_date = ? 
                     AND expiry_date = ? 
                     AND (paid_up_date = ? OR (paid_up_date IS NULL AND ? = ''))
                     ORDER BY lot_no, field_no";
    $stmt_block = $pdo->prepare($sql_block);
    $stmt_block->execute([$old_contracting_party, $old_start_date, $old_expiry_date, $old_paid_up_date, $old_paid_up_date]);
}

$allRows = $stmt_block->fetchAll(PDO::FETCH_ASSOC);

// --- 7. DATA RESTRUCTURING (FLAT -> NESTED) ---
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
        'id' => (string)$row['id'], // Cast to string to match HTML input type
        'name' => $row['group_acc'],
        'arable' => $row['contracted_arable']
    ];
}

$lotsData = array_values($lotsData);

// --- 8. USER DATA ---
try {
    $stmt = $pdo->prepare("SELECT * FROM tbl_users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    die("DB Error");
}

// --- 8.5 FETCH EXISTING DOCUMENTS (UPDATED: Latest Batch Only) ---
 $existing_docs = [];
if ($old_contract_id) {
    try {
        // We use a subquery to find the MAX date_added for this contract.
        // This ensures we only show photos from the most recent update, hiding older redundant backups.
        $stmt_docs = $pdo->prepare("
            SELECT * FROM tbl_documents 
            WHERE contract_id = ? 
            AND file_status = 'active'
            AND date_added = (
                SELECT MAX(date_added) 
                FROM tbl_documents 
                WHERE contract_id = ?
            )
            ORDER BY doc_id DESC
        ");
        
        // Bind: contract_id (for main query), contract_id (for subquery)
        $stmt_docs->execute([$old_contract_id, $old_contract_id]);
        $existing_docs = $stmt_docs->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Ignore error if table doesn't exist yet
    }
}


$full_name = trim($user['first_name'] . ' ' . ($user['middle_initial'] ? $user['middle_initial'] . '. ' : '') . $user['last_name']);
$default_cloud_url = "https://i.ibb.co/kVPtjmbK/default-profile-pic.png";
$header_profile_image = !empty($user['profile_image']) ? $user['profile_image'] : $default_cloud_url;

function e($str)
{
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
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
    <title>Renew Contract | Land Asset Management</title>
    <style>
        /* --- CORE VARIABLES & RESET --- */
        :root {
            --primary: #2E7D32;
            --primary-dark: #1b5e20;
            --text-dark: #333333;
            --text-light: #666666;
            --border-color: #e0e0e0;
            --input-bg: #ffffff;
            --bg-body: #f4f6f8;
            --bg-card: #ffffff;
            --bg-section: #fafafa;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg-body);
            margin: 0;
            padding: 40px;
            color: var(--text-dark);
        }

        /* --- MAIN CONTAINER (CARD UI) --- */
        .renew-container {
            width: 1000px;
            margin: 0 auto;
            background: var(--bg-card);
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            /* Soft shadow */
        }

        /* --- HEADER --- */
        .renew-header {
            border-bottom: 2px solid var(--primary);
            padding-bottom: 20px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .renew-header h2 {
            margin: 0;
            color: var(--primary);
            font-size: 1.8rem;
        }

        .renew-header p {
            margin: 5px 0 0;
            color: var(--text-light);
            font-size: 0.95rem;
        }



        /* --- FORM GRID --- */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 15px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 600;
            font-size: 0.85rem;
            color: #555;
            margin-bottom: 8px;
        }

        .form-group input,
        .form-group select {
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 14px;
            background: var(--input-bg);
            transition: all 0.2s;
            width: 100%;
            /* Ensure full width */
            box-sizing: border-box;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.1);
            /* Green glow */
        }

        /* --- LOT WRAPPER (REPEATER) --- */
        .lot-wrapper {
            background: var(--bg-section);
            border: 1px solid var(--border-color);
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
            position: relative;
        }

        .lot-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #ddd;
        }

        .lot-title {
            color: var(--primary);
            font-weight: bold;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* --- BUTTONS --- */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            font-size: 0.9rem;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }

        .btn-add-new-lot {
            background-color: #007bff;
            /* Primary Blue */
            border: 1px solid #007bff;
            color: #ffffff;
            /* White Text */
        }

        .btn-add-new-lot:hover {
            background-color: #0056b3;
            /* Darker Blue on Hover */
            border-color: #0056b3;
        }

        .btn-close {
            background: #e0e0e0;
            color: #333;
        }

        .btn-close:hover {
            background: #d6d6d6;
        }

        .btn-danger {
            background: #ffebee;
            color: #c62828;
            font-size: 0.85rem;
            padding: 8px 15px;
        }

        .btn-danger:hover {
            background: #ffcdd2;
        }

        .btn-add-group {
            background: #e3f2fd;
            color: #1976d2;
            width: auto;
            justify-content: center;
            margin-top: 10px;
            font-size: 0.85rem;
        }

        .btn-add-group:hover {
            background: #bbdefb;
        }

        /* --- GROUP ACCOUNTS ROW --- */
        .group-list {
            margin-top: 15px;
        }

        .group-item {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            align-items: center;
        }

        /* --- SEPARATORS --- */
        hr.styled-hr {
            border: 0;
            border-top: 1px solid #eee;
            margin: 30px 0;
        }

        /* --- CONFIRMATION MODAL (Deletion) --- */
        .popup-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(2px);
        }

        .popup-modal.show-modal {
            display: flex;
        }

        .popup-modal-content {
            background-color: white;
            padding: 30px;
            border-radius: 12px;
            width: 90%;
            max-width: 400px;
            text-align: center;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            animation: fadeIn 0.3s cubic-bezier(0.18, 0.89, 0.32, 1.28);
        }

        .popup-modal-content i {
            font-size: 50px;
            color: #d32f2f;
            /* Red for Delete */
            background: #FFEBEE;
            padding: 15px;
            border-radius: 50%;
            margin-bottom: 15px;
            display: inline-block;
        }

        .popup-modal-content h2 {
            margin: 0 0 10px 0;
            color: #333;
            font-size: 1.5rem;
        }

        .popup-modal-content p {
            color: #666;
            margin-bottom: 25px;
            line-height: 1.5;
        }

        .popup-modal-btns {
            display: flex;
            justify-content: center;
            gap: 15px;
        }

        .btn-confirm-delete {
            background: #d32f2f;
            color: white;
        }

        .btn-confirm-delete:hover {
            background: #b71c1c;
        }

        .btn-cancel-modal {
            background: #e0e0e0;
            color: #333;
        }

        .btn-cancel-modal:hover {
            background: #d6d6d6;
        }

        /* --- LOADING OVERLAY --- */
        #loadingOverlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            z-index: 9999;
            display: none;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: scale(0.95);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
        }



        /* --- ADDITION: DOCUMENT PREVIEW STYLES --- */
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

        .preview-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 15px;
            align-items: flex-start;
        }

        .doc-preview-item {
            position: relative;
            /* REMOVED: border, background, border-radius, padding, fixed width, transition, hover effects */
        }

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
            transition: background 0.2s;
        }

        .remove-doc-btn:hover {
            background: #b71c1c;
        }

        /* Top Half: The Image/Icon Box */
        .img-preview-box {
            height: 150px;
            /* FIXED HEIGHT: Levels all images */
            width: auto;
            /* AUTO WIDTH: Allows width to be exact as image */
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
            /* Auto width based on aspect ratio */
            height: 100%;
            /* Fill the fixed height box (150px) */
            object-fit: contain;
            /* Keeps aspect ratio */
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
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
            pointer-events: none;
        }

        /* Show Tooltip on Hover */
        .doc-preview-item:hover .filename-tooltip {
            opacity: 1;
            visibility: visible;
        }

        /* --- END ADDITION --- */
    </style>
</head>

<body class="sidebar-closed">

    <!-- CONTENT -->
    <section id="content">
        <main>
            <div class="renew-container">
                <div class="renew-header">
                    <div>
                        <h2><i class='bx bx-edit-alt'></i> Renew Contract</h2>
                        <p>
                            Contracting Party: <strong><?= e($old_contracting_party) ?></strong>
                            <!-- Vendor: <strong><?= e($old_vendor) ?></strong> -->
                        </p>
                    </div>
                    <button onclick="window.close()" class="btn btn-close">
                        <i class='bx bx-x'></i> Close
                    </button>
                </div>

                <form id="renewContractForm">
                    <!-- HIDDEN FIELDS -->
                    <input type="hidden" name="current_record_id" value="<?= $record_id ?>">
                    <!-- Hidden Input to track deleted documents -->
                    <input type="hidden" name="docs_to_delete" id="docs_to_delete" value="">

                    <!-- ADD THIS NEW HIDDEN INPUT FOR CONTRACT ID -->
                    <input type="hidden" name="old_contract_id" value="<?= e($old_contract_id) ?>">

                    <input type="hidden" name="old_contracting_party" value="<?= e($old_contracting_party) ?>">
                    <input type="hidden" name="old_start_date" value="<?= e($old_start_date) ?>">
                    <input type="hidden" name="old_expiry_date" value="<?= e($old_expiry_date) ?>">
                    <input type="hidden" name="old_paid_up_date" value="<?= e($old_paid_up_date) ?>">
                    <input type="hidden" name="old_vendor" value="<?= e($old_vendor) ?>">

                    <!-- SHARED HEADER INFO -->
                    <h3 class="section-title">Contract Information</h3>
                    <div class="form-grid">
                        <div class="form-group" style="grid-column: span 1;">
                            <label>Contracting Party</label>
                            <!-- MODIFIED: Added oninput to block numbers -->
                            <input type="text" name="contracting_party" value="<?= e($old_contracting_party) ?>" class="uppercase-input" oninput="this.value = this.value.replace(/[0-9]/g, '')" required>
                        </div>
                        <div class="form-group" style="grid-column: span 1;">
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>CMS Application No.</label>
                            <input type="text" name="cms_application_no" value="<?= e($sourceRow['cms_application_no']) ?>" oninput="this.value = this.value.toUpperCase()" required>
                        </div>
                        <div class="form-group">
                            <label>Vendor ID</label>
                            <!-- MODIFIED: Added oninput to block letters instantly -->
                            <input type="text" name="vendor" value="<?= e($sourceRow['vendor']) ?>" pattern="[0-9]+" title="Numbers only" oninput="this.value = this.value.replace(/[^0-9]/g, '')" required>
                        </div>
                    </div>

                    <hr class="styled-hr">

                    <!-- LOTS CONTAINER -->
                    <h3 class="section-title">Lots & Group Accounts</h3>
                    <div id="lotsContainer">
                        <!-- Lots injected via JS -->
                    </div>

                    <button type="button" class="btn btn-add-new-lot" onclick="addEmptyLotBlock()" style="width: auto; margin-top: 10px; justify-content: center; border-style: dashed;">
                        <i class='bx bx-plus'></i> Add New Lot
                    </button>

                    <hr class="styled-hr">

                    <!-- INCEPTION DATES (MOVED BELOW LOTS) -->
                    <h3 class="section-title">Inception Dates</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Start Date</label>
                            <input type="date" name="start_date" value="<?= e($old_start_date) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Expiry Date</label>
                            <input type="date" name="expiry_date" value="<?= e($old_expiry_date) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Paid Up Date</label>
                            <input type="date" name="paid_up_date" value="<?= e($old_paid_up_date) ?>">
                        </div>
                    </div>

                    <hr class="styled-hr">
                    
                    <!-- ADDITION: SUPPORTING DOCUMENTS SECTION -->
                    <h3 class="section-title">Supporting Documents</h3>
                    <div class="form-group">
                        <div style="display: flex; gap: 10px;">
                            <input type="file" name="contract_documents[]" id="contract_documents" multiple
                                accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                                style="flex-grow: 1; font-size: 0.9rem;">
                        </div>
                        <small style="color: #666; font-size: 0.8rem;">Allowed: PDF, DOCX, JPG, PNG</small>

                        <!-- Container for Previews -->
                        <div id="documentPreviewArea" class="preview-grid"></div>
                    </div>
                    <!-- END ADDITION -->

                    <hr class="styled-hr">

                    <button type="button" id="btnSaveChanges" onclick="validateAndConfirmRenew()" class="btn btn-primary" style="width: 100%; padding: 15px; justify-content: center; font-size: 1.1rem;" disabled>
                        <i class='bx bx-save'></i> Renew Contract
                    </button>
                </form>
            </div>
        </main>
    </section>

    <!-- DELETION CONFIRMATION MODAL -->
    <div id="deleteLotModal" class="popup-modal">
        <div class="popup-modal-content">
            <i class='bx bx-error'></i>
            <h2>Confirm Deletion</h2>
            <p>You are about to delete this lot and all its associated group accounts. This action cannot be undone.</p>

            <div class="popup-modal-btns">
                <button class="btn btn-cancel-modal" onclick="closeDeleteLotModal()">Cancel</button>
                <button class="btn btn-confirm-delete" onclick="confirmDeleteLot()">Yes, Delete</button>
            </div>
        </div>
    </div>

    <!-- LOADING OVERLAY -->
    <div id="loadingOverlay">
        <i class='bx bx-loader-alt bx-spin' style="font-size: 50px; color: var(--primary);"></i>
        <h3 style="margin-top: 20px; color: var(--text-dark); font-weight: 600;">Updating Contract...</h3>
    </div>


    <!-- ===== SUCCESS MODAL ===== -->
    <div id="successModal" class="popup-modal">
        <div class="popup-modal-content" style="max-width: 400px; text-align: center;">
            <div class="popup-modal-header" style="border-bottom: none; justify-content: center;">
                <i class='bx bx-check-circle'
                    style="font-size: 50px; color: #4CAF50; background: #E8F5E9; padding: 20px; border-radius: 50%;"></i>
            </div>
            <h2 style="margin-bottom: 10px;">Success!</h2>
            <p id="successMessageText" style="color: #666; margin-bottom: 20px;">Contract saved successfully.</p>
            <div style="display: flex; justify-content: center; gap: 15px;">
                <button onclick="handleSuccessModalClose()"
                    style="background: #4CAF50; color: white; padding: 10px 30px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer;">OK</button>
            </div>
        </div>
    </div>

    <!-- ===== ERROR MODAL ===== -->
    <div id="errorModal" class="popup-modal">
        <div class="popup-modal-content" style="max-width: 400px; text-align: center;">
            <div class="popup-modal-header" style="border-bottom: none; justify-content: center;">
                <i class='bx bx-error'
                    style="font-size: 50px; color: #d32f2f; background: #FFEBEE; padding: 20px; border-radius: 50%;"></i>
            </div>
            <h2 style="margin-bottom: 10px;">Error</h2>
            <p id="errorMessageText" style="color: #666; margin-bottom: 20px;">Something went wrong.</p>
            <div style="display: flex; justify-content: center; gap: 15px;">
                <button onclick="closeErrorModal()"
                    style="background: #e0e0e0; color: #333; padding: 10px 30px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer;">OK</button>
            </div>
        </div>
    </div>

    <!-- JAVASCRIPT LOGIC -->
    <script>
        let newFiles = [];
        let docsToDelete = [];
        let anyDocumentModified = false; // Tracks if docs are added/removed
        let lotIndexToDelete = null; // Track which lot is being deleted

        // 1. DATA FROM PHP
        const initialLotsData = <?php echo json_encode($lotsData); ?>;
        const currentRecordId = "<?php echo $record_id; ?>"; // Need ID for LocalStorage key

        // --- SNAPSHOT INITIAL HEADER DATA FOR CHANGE DETECTION ---
        const initialHeaderData = {
            contracting_party: "<?php echo e($old_contracting_party); ?>".trim(),
            cms_application_no: "<?php echo e($sourceRow['cms_application_no']); ?>".trim(),
            vendor: "<?php echo e($sourceRow['vendor']); ?>".trim(),
            start_date: "<?php echo e($old_start_date); ?>".trim(),
            expiry_date: "<?php echo e($old_expiry_date); ?>".trim(),
            paid_up_date: "<?php echo e($old_paid_up_date); ?>".trim()
        };

        // ==========================================
        // --- 1. LOCAL STORAGE (DRAFT SAVE) ---
        // ==========================================
        const STORAGE_KEY = 'contract_draft_' + currentRecordId;

                function checkForChanges() {
            const btn = document.getElementById('btnSaveChanges');
            if (!btn) return;

            // 1. Compare Header Data
            const currentHeader = {
                contracting_party: document.querySelector('input[name="contracting_party"]').value.trim(),
                cms_application_no: document.querySelector('input[name="cms_application_no"]').value.trim(),
                vendor: document.querySelector('input[name="vendor"]').value.trim(),
                start_date: document.querySelector('input[name="start_date"]').value,
                expiry_date: document.querySelector('input[name="expiry_date"]').value,
                paid_up_date: document.querySelector('input[name="paid_up_date"]').value
            };

            const headerChanged = JSON.stringify(currentHeader) !== JSON.stringify(initialHeaderData);

            // 2. Compare Lots Data
            // We reconstruct the array similar to how saveDraft does it
            const container = document.getElementById('lotsContainer');
            const wrappers = container.querySelectorAll('.lot-wrapper');
            const currentLots = [];

            wrappers.forEach((wrapper, index) => {
                const getVal = (sel) => {
                    const el = wrapper.querySelector(sel);
                    return el ? el.value.trim() : '';
                };
            
                const groupItems = wrapper.querySelectorAll('.group-item');
                const groups = [];
                groupItems.forEach(gItem => {
                    const idInput = gItem.querySelector('input[name*="[id]"]');
                    const nameInput = gItem.querySelector('input[name*="[name]"]');
                    const arableInput = gItem.querySelector('input[name*="[arable]"]');
                    groups.push({
                        id: idInput ? idInput.value : '',
                        name: nameInput ? nameInput.value.trim() : '',
                        arable: arableInput ? arableInput.value.trim() : ''
                    });
                });

                currentLots.push({
                    lot_no: getVal(`input[name$="[lot_no]"]`),
                    field_no: getVal(`input[name$="[field_no]"]`),
                    field_section: getVal(`input[name$="[field_section]"]`),
                    province: getVal(`select[name$="[province]"]`),
                    municipality: getVal(`select[name$="[municipality]"]`),
                    barangay: getVal(`select[name$="[barangay]"]`),
                    longlat: getVal(`input[name$="[longlat]"]`),
                    rate: getVal(`input[name$="[rate]"]`),
                    crop_type: getVal(`select[name$="[crop_type]"]`),
                    lease_status: getVal(`select[name$="[lease_status]"]`),
                    contract_status: getVal(`select[name$="[contract_status]"]`),
                    contract_class: getVal(`select[name$="[contract_class]"]`),
                    groups: groups
                });
            });

            const lotsChanged = JSON.stringify(currentLots) !== JSON.stringify(initialLotsData);

            // 3. Check Document Data (New Addition)
            // Checks newFiles, deletedFiles, OR the manual flag from user interactions
            const docsChanged = (newFiles.length > 0 || docsToDelete.length > 0 || window.anyDocumentModified);

            // Enable button if anything is different
            if (headerChanged || lotsChanged || docsChanged) {
                btn.removeAttribute('disabled');
                btn.style.opacity = '1';
                btn.style.cursor = 'pointer';
            } else {
                btn.setAttribute('disabled', 'true');
                btn.style.opacity = '0.5';
                btn.style.cursor = 'not-allowed';
            }
        }

        function saveDraft() {
            const container = document.getElementById('lotsContainer');
            const wrappers = container.querySelectorAll('.lot-wrapper');

            const savedData = [];

            wrappers.forEach((wrapper, index) => {
                // Helper to get input value safely
                const getVal = (sel) => {
                    const el = wrapper.querySelector(sel);
                    return el ? el.value : '';
                };

                // Gather Group Accounts
                const groupItems = wrapper.querySelectorAll('.group-item');
                const groups = [];

                groupItems.forEach(gItem => {
                    const nameInput = gItem.querySelector('input[name*="[name]"]');
                    const arableInput = gItem.querySelector('input[name*="[arable]"]');

                    // Check if readonly (visual check) to preserve "NONE" logic on reload
                    const isReadonly = nameInput && nameInput.hasAttribute('readonly');

                    groups.push({
                        name: nameInput ? nameInput.value : '',
                        arable: arableInput ? arableInput.value : '',
                        // We mark it so we know to make it readonly on restore
                        isReadonly: isReadonly
                    });
                });

                savedData.push({
                    lot_no: getVal(`input[name$="[lot_no]"]`),
                    field_no: getVal(`input[name$="[field_no]"]`),
                    field_section: getVal(`input[name$="[field_section]"]`),
                    province: getVal(`select[name$="[province]"]`),
                    municipality: getVal(`select[name$="[municipality]"]`),
                    barangay: getVal(`select[name$="[barangay]"]`),
                    longlat: getVal(`input[name$="[longlat]"]`),
                    rate: getVal(`input[name$="[rate]"]`),
                    crop_type: getVal(`select[name$="[crop_type]"]`),
                    lease_status: getVal(`select[name$="[lease_status]"]`),
                    contract_status: getVal(`select[name$="[contract_status]"]`),
                    contract_class: getVal(`select[name$="[contract_class]"]`),
                    groups: groups
                });
            });

            localStorage.setItem(STORAGE_KEY, JSON.stringify(savedData));
            checkForChanges();
        }

        document.addEventListener('DOMContentLoaded', async () => {
            const container = document.getElementById('lotsContainer');

            // --- LOAD DRAFT IF EXISTS ---
            let dataToLoad = initialLotsData;
            const savedDraft = localStorage.getItem(STORAGE_KEY);

            if (savedDraft) {
                try {
                    dataToLoad = JSON.parse(savedDraft);
                    console.log("Draft restored from LocalStorage");
                } catch (e) {
                    console.error("Error parsing draft", e);
                }
            }

            if (dataToLoad.length > 0) {
                for (let [index, lot] of dataToLoad.entries()) {
                    const lotBlock = createLotBlockHTML(index, lot);
                    container.appendChild(lotBlock);

                    await loadProvincesForLot(index);
                    populateSelect(`province_${index}`, lot.province);

                    const pSelect = document.getElementById(`province_${index}`);
                    if (pSelect && pSelect.selectedIndex > -1) {
                        const pCode = pSelect.options[pSelect.selectedIndex].dataset.code;
                        if (pCode) {
                            await loadMunicipalitiesForLot(index);
                            populateSelect(`municipality_${index}`, lot.municipality);

                            const mSelect = document.getElementById(`municipality_${index}`);
                            if (mSelect && mSelect.selectedIndex > 0) {
                                const mCode = mSelect.options[mSelect.selectedIndex].dataset.code;
                                if (mCode) {
                                    await loadBarangaysForLot(index);
                                    populateSelect(`barangay_${index}`, lot.barangay);
                                }
                            }
                        }
                    }
                }
            } else {
                addEmptyLotBlock();
            }

            // ADD THIS BLOCK: Listen for changes in header inputs
            const headerInputs = document.querySelectorAll('#renewContractForm input:not([type="hidden"]), #renewContractForm select');
            headerInputs.forEach(input => {
                input.addEventListener('input', checkForChanges);
                input.addEventListener('change', checkForChanges);
            });

            // Attach auto-save listener to container (existing)
            container.addEventListener('input', saveDraft);
            container.addEventListener('change', saveDraft);
            checkForChanges();
        });

        // --- HELPER: SMART POPULATE ---
        function populateSelect(id, value) {
            const el = document.getElementById(id);
            if (!el) return;
            if (!value) return;

            const cleanVal = value.trim().toUpperCase().replace(/ (CITY|MUNICIPALITY)/g, "").replace(/\s+/g, "");
            let foundIndex = -1;

            for (let i = 0; i < el.options.length; i++) {
                const optText = el.options[i].value.trim().toUpperCase().replace(/ (CITY|MUNICIPALITY)/g, "").replace(/\s+/g, "");
                if (optText === cleanVal) {
                    foundIndex = i;
                    break;
                }
                if (optText.includes(cleanVal) || cleanVal.includes(optText)) {
                    foundIndex = i;
                }
            }

            if (foundIndex !== -1) {
                el.selectedIndex = foundIndex;
            } else {
                if (el.options.length > 1) {
                    el.selectedIndex = 1;
                }
            }
        }

        // --- CREATE LOT HTML (Styled) ---
        function createLotBlockHTML(index, data = {}) {
            const div = document.createElement('div');
            div.className = 'lot-wrapper';
            const mainLotId = (data.groups && data.groups[0]) ? data.groups[0].id : '';
            div.dataset.mainLotId = mainLotId;

            // Generate Group Rows (FIXED: Retains 'NONE' value)
            // Checks if data.groups[x].isReadonly is true (from draft restore) or if index 0
            const groupsHTML = (data.groups || []).map((g, gIndex) => {
                // Determine if this specific group should be readonly
                const isReadonly = (g.isReadonly === true) || (gIndex === 0 && data.groups.length > 0 && g.name === 'NONE');

                const inputStyle = isReadonly ?
                    `padding: 10px; border: 1px solid #ddd; border-radius: 4px; width: 100%; background-color: #f5f5f5; color: #777; cursor: not-allowed;` :
                    `padding: 10px; border: 1px solid #ddd; border-radius: 4px; width: 100%;`;

                const inputAttrs = isReadonly ?
                    `readonly value="${g.name}"` :
                    `value="${g.name}" placeholder="Group Name" oninput="this.value = this.value.replace(/[0-9]/g, '').toUpperCase()"`;

                return `
                <div class="group-item">
                    <!-- ADDED: HIDDEN ID INPUT -->
                    <input type="hidden" name="lots[${index}][groups][${gIndex}][id]" value="${g.id}">

                    <div style="flex: 2; margin-right: 15px;">
                        <input type="text" name="lots[${index}][groups][${gIndex}][name]" ${inputAttrs} style="${inputStyle}">
                    </div>
                    <div style="flex: 1;">
                         <!-- Added data-record-id="${g.id}" here -->
                        <input type="number" step="0.0001" name="lots[${index}][groups][${gIndex}][arable]" value="${g.arable}" placeholder="Has" required style="padding: 10px; border: 1px solid #ddd; border-radius: 4px; width: 100%;">
                    </div>
                    <button type="button" onclick="this.parentElement.remove()" class="btn btn-danger" style="padding: 10px;">
                        <i class='bx bx-trash'></i>
                    </button>
                </div>
            
            `
            }).join('');

            // ... (Rest of the function stays the same, keep existing logic for Lot inputs, Header, etc.)
            // ... Copy the rest of your existing createLotBlockHTML function ...

            const renewTitleFunc = `renewLotTitle(this, ${index})`;

            div.innerHTML = `
                <div class="lot-header">
                    <!-- <span class="lot-title" id="lot_title_${index}"><i class='bx bx-map'></i> Lot ${data.lot_no || (index + 1)}</span> Hi AI, DON'T REMOVE THIS COMMENT-->
                    <span class="lot-title" id="lot_title_${index}"><i class='bx bx-map'></i> Lot ${data.lot_no || '?' }</span>
                    <button type="button" onclick="prepareDeleteLot(${index})" class="btn btn-danger">
                        <i class='bx bx-trash'></i> Remove Lot
                    </button>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Lot No</label>
                        <input type="text" name="lots[${index}][lot_no]" id="lot_no_${index}" value="${data.lot_no || ''}" required oninput="${renewTitleFunc}" style="font-weight:bold;">
                    </div>
                    <div class="form-group">
                        <label>Field No</label>
                        <input type="text" name="lots[${index}][field_no]" value="${data.field_no || ''}">
                    </div>
                    <div class="form-group">
                        <label>Field Section</label>
                        <input type="text" name="lots[${index}][field_section]" value="${data.field_section || ''}">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Province</label>
                        <select name="lots[${index}][province]" id="province_${index}" onchange="loadMunicipalitiesForLot(${index})" required>
                            <option value="" disabled selected>Loading...</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Municipality</label>
                        <select name="lots[${index}][municipality]" id="municipality_${index}" onchange="loadBarangaysForLot(${index})" disabled>
                            <option value="" disabled selected>Select Province First</option>
                        </select>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Barangay</label>
                        <select name="lots[${index}][barangay]" id="barangay_${index}" disabled>
                            <option value="" disabled selected>Select Municipality First</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Long/Lat</label>
                        <input type="text" name="lots[${index}][longlat]" value="${data.longlat || ''}" placeholder="e.g. 8.123, 124.456">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Rate</label>
                        <input type="text" name="lots[${index}][rate]" value="${data.rate || ''}" placeholder="0.00" oninput="this.value = this.value.replace(/[^0-9.,]/g, '')">
                    </div>
                    <div class="form-group">
                        <label>Crop Type</label>
                        <select name="lots[${index}][crop_type]">
                            <option value="S16" ${data.crop_type === 'S16' ? 'selected' : ''}>S16</option>
                            <option value="C74" ${data.crop_type === 'C74' ? 'selected' : ''}>C74</option>
                            <option value="PAPAYA" ${data.crop_type === 'PAPAYA' ? 'selected' : ''}>PAPAYA</option>
                            <option value="AVOCADO" ${data.crop_type === 'AVOCADO' ? 'selected' : ''}>AVOCADO</option>
                            <option value="OP" ${data.crop_type === 'OP' ? 'selected' : ''}>OP</option>
                            <option value="WHSE" ${data.crop_type === 'WHSE' ? 'selected' : ''}>WHSE</option>
                            <option value="BODEGA" ${data.crop_type === 'BODEGA' ? 'selected' : ''}>BODEGA</option>
                        </select>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Lease Status</label>
                        <select name="lots[${index}][lease_status]">
                            <option value="Active" ${data.lease_status === 'Active' ? 'selected' : ''}>Active</option>
                            <option value="Expired" ${data.lease_status === 'Expired' ? 'selected' : ''}>Expired</option>
                            <option value="Near Expiration" ${data.lease_status === 'Near Expiration' ? 'selected' : ''}>Near Expiration</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Contract Status</label>
                        <select name="lots[${index}][contract_status]">
                            <option value="EXISTING" ${data.contract_status === 'EXISTING' ? 'selected' : ''}>EXISTING</option>
                            <option value="NONRENEWING" ${data.contract_status === 'NONRENEWING' ? 'selected' : ''}>NONRENEWING</option>
                            <option value="FOR RETURN" ${data.contract_status === 'FOR RETURN' ? 'selected' : ''}>FOR RETURN</option>
                            <option value="RETURN" ${data.contract_status === 'RETURN' ? 'selected' : ''}>RETURN</option>
                            <option value="RENEWED" ${data.contract_status === 'RENEWED' ? 'selected' : ''}>RENEWED</option>
                            <option value="EXTENSION" ${data.contract_status === 'EXTENSION' ? 'selected' : ''}>EXTENSION</option>
                            <option value="NEWLAND" ${data.contract_status === 'NEWLAND' ? 'selected' : ''}>NEWLAND</option>
                            <option value="EXPIRED" ${data.contract_status === 'EXPIRED' ? 'selected' : ''}>EXPIRED</option>
                            <option value="UNUTILIZED" ${data.contract_status === 'UNUTILIZED' ? 'selected' : ''}>UNUTILIZED</option>
                            <option value="RETAIN" ${data.contract_status === 'RETAIN' ? 'selected' : ''}>RETAIN</option>
                            <option value="CANCELLED" ${data.contract_status === 'CANCELLED' ? 'selected' : ''}>CANCELLED</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Contract Class</label>
                        <select name="lots[${index}][contract_class]">
                            <option value="CP&GA" ${data.contract_class === 'CP&GA' ? 'selected' : ''}>CP&GA</option>
                            <option value="DEVELOPMENT AGREEMENT" ${data.contract_class === 'DEVELOPMENT AGREEMENT' ? 'selected' : ''}>DEVELOPMENT AGREEMENT</option>
                            <option value="MOA" ${data.contract_class === 'MOA' ? 'selected' : ''}>MOA</option>
                            <option value="CONTRACT OF LEASE" ${data.contract_class === 'CONTRACT OF LEASE' ? 'selected' : ''}>CONTRACT OF LEASE</option>
                            <option value="GROWERSHIP AGREEMENT" ${data.contract_class === 'GROWERSHIP AGREEMENT' ? 'selected' : ''}>GROWERSHIP AGREEMENT</option>
                            <option value="GROWERSHIP" ${data.contract_class === 'GROWERSHIP' ? 'selected' : ''}>GROWERSHIP</option>
                        </select>
                    </div>
                    <div class="form-group"></div>
                </div>

                <div style="border-top: 1px dashed #ccc; padding-top: 15px; margin-top: 10px;">
                    <label style="font-weight:600; font-size:13px; color: #555;">Group Accounts</label>
                    <div class="group-list">
                        ${groupsHTML}
                    </div>
                    <button type="button" class="btn btn-add-group" onclick="addGroupRow(${index})">
                        <i class='bx bx-plus'></i> Add Group Account
                    </button>
                </div>
            `;
            return div;
        }

        // --- FEATURE: Live Renew Title ---
        function renewLotTitle(input, index) {
            const titleEl = document.getElementById(`lot_title_${index}`);
            const val = input.value.trim();
            if (val) {
                titleEl.innerHTML = `<i class='bx bx-map'></i> Lot ${val}`;
            } else {
                // titleEl.innerHTML = `<i class='bx bx-map'></i> Lot #${index + 1}`; Hi AI, DON'T REMOVE THIS COMMENT
                titleEl.innerHTML = `<i class='bx bx-map'></i> Lot ${ '?' }`;
            }
        }

        // UPDATED: Added true parameter to force "NONE" group for new lots
        window.addEmptyLotBlock = function() {
            const container = document.getElementById('lotsContainer');
            const currentCount = container.querySelectorAll('.lot-wrapper').length;
            const newBlock = createLotBlockHTML(currentCount, {});
            container.appendChild(newBlock);
            loadProvincesForLot(currentCount);

            // FIX: Automatically add the "NONE" group for new lots
            addGroupRow(currentCount, true);
            checkForChanges();
        }

        // UPDATED: Accepts 'forceNone' parameter
        window.addGroupRow = function(lotIndex, forceNone = false) {
            const container = document.querySelector(`.lot-wrapper:nth-child(${lotIndex+1}) .group-list`);
            const existingGroups = container.querySelectorAll('.group-item').length;

            // Determine if this is the first group or forced to be NONE
            const isFirst = (existingGroups === 0);
            const shouldBeReadonly = isFirst || forceNone;

            const div = document.createElement('div');
            div.className = 'group-item';

            // Conditional HTML rendering
            if (shouldBeReadonly) {
                // Readonly "NONE" Group
                div.innerHTML = `
                <div style="flex: 2; margin-right: 15px;">
                    <input type="text" name="lots[${lotIndex}][groups][${existingGroups}][name]" value="NONE" readonly style="padding: 10px; border: 1px solid #ddd; border-radius: 4px; width: 100%; background-color: #f5f5f5; color: #777; cursor: not-allowed;">
                </div>
                <div style="flex: 1;">
                    <input type="number" step="0.0001" name="lots[${lotIndex}][groups][${existingGroups}][arable]" placeholder="Has" required style="padding: 10px; border: 1px solid #ddd; border-radius: 4px; width: 100%;">
                </div>
                <button type="button" onclick="this.parentElement.remove()" class="btn btn-danger" style="padding: 10px;">
                    <i class='bx bx-trash'></i>
                </button>
                `;
            } else {
                // Normal Editable Group
                div.innerHTML = `
                <div style="flex: 2; margin-right: 15px;">
                    <input type="text" name="lots[${lotIndex}][groups][${existingGroups}][name]" placeholder="Group Name" oninput="this.value = this.value.replace(/[0-9]/g, '').toUpperCase()" style="padding: 10px; border: 1px solid #ddd; border-radius: 4px; width: 100%;">
                </div>
                <div style="flex: 1;">
                    <input type="number" step="0.0001" name="lots[${lotIndex}][groups][${existingGroups}][arable]" placeholder="Has" required style="padding: 10px; border: 1px solid #ddd; border-radius: 4px; width: 100%;">
                </div>
                <button type="button" onclick="this.parentElement.remove()" class="btn btn-danger" style="padding: 10px;">
                    <i class='bx bx-trash'></i>
                </button>
                `;
            }

            container.appendChild(div);
            checkForChanges();
        }

        // --- API CALLS ---
        window.loadProvincesForLot = async function(index) {
            const pSelect = document.getElementById(`province_${index}`);
            return fetch('https://psgc.gitlab.io/api/provinces/')
                .then(response => response.json())
                .then(data => {
                    pSelect.innerHTML = '<option value="" disabled selected>Select Province</option>';
                    data.sort((a, b) => {
                        const nameA = a.name.toUpperCase();
                        const nameB = b.name.toUpperCase();
                        if (nameA === 'BUKIDNON') return -1;
                        if (nameB === 'BUKIDNON') return 1;
                        return nameA.localeCompare(nameB);
                    });
                    data.forEach(p => {
                        const opt = document.createElement('option');
                        opt.value = p.name.toUpperCase();
                        opt.textContent = p.name.toUpperCase();
                        opt.dataset.code = p.code;
                        pSelect.appendChild(opt);
                    });
                });
        };

        window.loadMunicipalitiesForLot = async function(index) {
            const pSelect = document.getElementById(`province_${index}`);
            const mSelect = document.getElementById(`municipality_${index}`);
            const bSelect = document.getElementById(`barangay_${index}`);

            const code = pSelect.options[pSelect.selectedIndex].dataset.code;
            mSelect.innerHTML = '<option value="" disabled selected>Loading...</option>';
            mSelect.disabled = true;
            bSelect.disabled = true;

            if (code) {
                return fetch(`https://psgc.gitlab.io/api/provinces/${code}/cities-municipalities/`)
                    .then(r => r.json())
                    .then(data => {
                        mSelect.innerHTML = '<option value="" disabled selected>Select Municipality</option>';
                        data.forEach(m => {
                            const opt = document.createElement('option');
                            opt.value = m.name.toUpperCase();
                            opt.textContent = m.name.toUpperCase();
                            opt.dataset.code = m.code;
                            mSelect.appendChild(opt);
                        });
                        mSelect.disabled = false;
                    });
            }
        };

        window.loadBarangaysForLot = async function(index) {
            const mSelect = document.getElementById(`municipality_${index}`);
            const bSelect = document.getElementById(`barangay_${index}`);

            if (!mSelect || mSelect.selectedIndex < 1) return;

            const code = mSelect.options[mSelect.selectedIndex].dataset.code;
            bSelect.innerHTML = '<option value="" disabled selected>Loading...</option>';
            bSelect.disabled = true;

            if (code) {
                return fetch(`https://psgc.gitlab.io/api/cities-municipalities/${code}/barangays/`)
                    .then(r => r.json())
                    .then(data => {
                        bSelect.innerHTML = '<option value="" disabled selected>Select Barangay</option>';
                        data.forEach(b => {
                            const opt = document.createElement('option');
                            opt.value = b.name.toUpperCase();
                            opt.textContent = b.name.toUpperCase();
                            bSelect.appendChild(opt);
                        });
                        bSelect.disabled = false;
                    });
            }
        };

        // --- MODAL LOGIC FOR DELETION ---
        window.prepareDeleteLot = function(index) {
            lotIndexToDelete = index;
            document.getElementById('deleteLotModal').classList.add('show-modal');
        }

        window.closeDeleteLotModal = function() {
            document.getElementById('deleteLotModal').classList.remove('show-modal');
            lotIndexToDelete = null;
        }

        // --- REPLACE EXISTING confirmDeleteLot WITH THIS VERSION ---
        window.confirmDeleteLot = function() {
            if (lotIndexToDelete === null) return;

            const lotWrappers = document.querySelectorAll('.lot-wrapper');
            const wrapper = lotWrappers[lotIndexToDelete];

            // FIX: Use the Master ID from the wrapper attribute
            const mainIdToDelete = wrapper.dataset.mainLotId;

            if (mainIdToDelete) {
                // Case A: We have an ID -> Delete by ID
                wrapper.style.opacity = '0.5';

                const formData = new FormData();
                formData.append('delete_immediate_lot', '1'); // Use Immediate Delete
                formData.append('ids', JSON.stringify([mainIdToDelete])); // Send the specific ID

                fetch('../actions/renew_contract_action.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        wrapper.style.opacity = '1';
                        closeDeleteLotModal();

                        if (data.success) {
                            wrapper.remove();
                            // Trigger save state again because DOM changed
                            saveDraft();
                            checkForChanges();

                            const successModal = document.getElementById('successModal');
                            const msgText = document.getElementById('successMessageText');
                            if (msgText) msgText.textContent = "Record deleted successfully.";
                            if (successModal) successModal.classList.add('show-modal');
                        } else {
                            alert("Error: " + data.message);
                        }
                    })
                    .catch(err => {
                        wrapper.style.opacity = '1';
                        closeDeleteLotModal();
                        alert("Network Error: " + err.message);
                    });
            } else {
                // Case B: New Lot (No ID). Just remove from DOM.
                wrapper.remove();
                closeDeleteLotModal();
                // Trigger save state again because DOM changed
                saveDraft();
                checkForChanges();
            }
        }

        window.validateAndConfirmRenew = function() {
            const form = document.getElementById('renewContractForm');
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            // Using a simple confirm here, but you could use a modal if preferred
            if (confirm("Are you sure you want to renew this contract?")) {
                submitRenewForm();
            }
        }

        window.submitRenewForm = function() {
            document.getElementById('loadingOverlay').style.display = 'flex';

            const form = document.getElementById('renewContractForm');
            const formData = new FormData(form);
            formData.append('renew_contract', '1');

            // Append the list of documents to delete
            const docsToDeleteHidden = document.getElementById('docs_to_delete');
            // We need to access the 'docsToDelete' array from the IIFE scope.
            // Since we defined it inside the IIFE, it's private.
            // We need to make it globally accessible or renew the hidden input inside the IIFE.

            // SIMPLEST FIX: Renew the hidden input whenever docsToDelete changes.
            // Go back to the IIFE code in step 2 and add this inside the removeBtn.onclick for Existing Docs:
            // document.getElementById('docs_to_delete').value = JSON.stringify(docsToDelete);

            // Get the current ID from the hidden input
            const currentRecordId = parseInt(document.querySelector('input[name="current_record_id"]').value);

            fetch('../actions/renew_contract_action.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    document.getElementById('loadingOverlay').style.display = 'none';

                    if (data.success) {
                        // --- CLEAR DRAFT ON SUCCESS ---
                        localStorage.removeItem(STORAGE_KEY);

                        // Check if the ID we are currently viewing was deleted
                        const wasDeleted = data.deleted_ids && data.deleted_ids.includes(currentRecordId);

                        // --- FIX START: Construct New URL ---
                        // We create a new URL based on the data returned from the server
                        // FIX: Redirect using Contract ID
                        const contractId = document.querySelector('input[name="old_contract_id"]').value;
                        const newUrl = `renew_contract.php?contract_id=${encodeURIComponent(contractId)}`;
                        // --- FIX END ---

                        if (wasDeleted) {
                            // Show Success Modal but configure it to CLOSE window on OK
                            const successModal = document.getElementById('successModal');
                            const msgText = document.getElementById('successMessageText');

                            if (msgText) msgText.textContent = data.message || "Contract renewd successfully.";
                            if (successModal) successModal.classList.add('show-modal');

                            // Override the close function for this specific instance
                            window.handleSuccessModalClose = function() {
                                const successModal = document.getElementById('successModal');
                                if (successModal) successModal.classList.remove('show-modal');

                                if (window.opener) {
                                    window.opener.location.reload();
                                }
                                // CLOSE THIS WINDOW because our ID is gone
                                window.close();
                            };
                        } else {
                            // Normal Success (ID still exists) -> Reload using NEW URL
                            const successModal = document.getElementById('successModal');
                            const msgText = document.getElementById('successMessageText');

                            if (msgText) msgText.textContent = data.message || "Contract Renewed Successfully!";
                            if (successModal) successModal.classList.add('show-modal');

                            // Reset the close function to redirect to the new URL
                            window.handleSuccessModalClose = function() {
                                const successModal = document.getElementById('successModal');
                                if (successModal) successModal.classList.remove('show-modal');

                                if (window.opener) {
                                    window.opener.location.reload();
                                }
                                // --- FIXED: Redirect to the URL with new values ---
                                window.location.href = newUrl;
                            };
                        }

                    } else {
                        // Show Error Modal
                        showErrorModal(data.message || "An unknown error occurred.");
                    }
                })
                .catch(err => {
                    document.getElementById('loadingOverlay').style.display = 'none';
                    showErrorModal("Network Error: Could not connect to the server.");
                    console.error(err);
                });
        }


        // Helper: Show Error Modal
        function showErrorModal(message) {
            const errorModal = document.getElementById('errorModal');
            const msgText = document.getElementById('errorMessageText');

            if (msgText) msgText.textContent = message;
            if (errorModal) errorModal.classList.add('show-modal');
        }

        // Helper: Close Error Modal
        function closeErrorModal() {
            const errorModal = document.getElementById('errorModal');
            if (errorModal) errorModal.classList.remove('show-modal');
        }

        // Helper: Close Success Modal and Reload
        function handleSuccessModalClose() {
            const successModal = document.getElementById('successModal');
            if (successModal) successModal.classList.remove('show-modal');

            // Reload parent window if it exists
            if (window.opener) {
                window.opener.location.reload();
            }
            // Reload this page to reflect changes
            window.location.reload();
        }

        // =========================================================================
        // ADDITION: DOCUMENT UPLOAD & PREVIEW LOGIC (UPDATED FOR EXISTING FILES)
        // =========================================================================
        (function() {
            const fileInput = document.getElementById('contract_documents');
            const previewContainer = document.getElementById('documentPreviewArea');

            // 1. Data from PHP (Existing Documents)
            const existingDocsData = <?php echo json_encode($existing_docs ?? []); ?>;

            // 2. State Management
            // Removed 'let' to reference global scope variables defined in main script
            newFiles = [];
            docsToDelete = [];

            // 2. State Management
            newFiles = [];
            docsToDelete = [];

            // RESET: Document modification flag is false on load
            window.anyDocumentModified = false;

            // Helper: Sync HTML input with newFiles array
            function syncFileInput() {
                const dataTransfer = new DataTransfer();
                newFiles.forEach(file => {
                    dataTransfer.items.add(file);
                });
                fileInput.files = dataTransfer.files;
            }

            // 3. Render Existing Documents (From Database)
            function renderExistingDocs() {
                // We create a wrapper for existing docs so we can prepend new ones if needed, 
                // or just append them. Let's clear existing to ensure sync.
                previewContainer.innerHTML = '';

                if (existingDocsData.length === 0) {
                    // Optional: Show message if no docs exist
                    // previewContainer.innerHTML = '<small style="color:#999; width:100%; text-align:center; padding:20px;">No existing documents.</small>';
                }

                existingDocsData.forEach((doc) => {
                    // Check if this doc is already marked for deletion (shouldn't happen on initial load, but good practice)
                    if (docsToDelete.includes(doc.doc_id)) return;

                    const card = document.createElement('div');
                    card.className = 'doc-preview-item';
                    card.dataset.docId = doc.doc_id; // Mark as DB item
                    card.dataset.type = 'existing';

                    // Delete Button
                    const removeBtn = document.createElement('div');
                    removeBtn.className = 'remove-doc-btn';
                    removeBtn.innerHTML = '<i class=\'bx bx-x\'></i>';
                    removeBtn.title = 'Remove Document';

                    // Logic: Mark for deletion
                    removeBtn.onclick = function(e) {
                        e.stopPropagation();
                        docsToDelete.push(doc.doc_id); // Add to delete list

                        // Remove from visual DOM immediately
                        card.remove();

                        // Trigger change detection so "Save Changes" enables
                        checkForChanges();

                        // MARK DOCUMENT AS MODIFIED
                        window.anyDocumentModified = true;

                        document.getElementById('docs_to_delete').value = JSON.stringify(docsToDelete);
                    };

                    card.appendChild(removeBtn);

                    // Image / Icon Box
                    const imgBox = document.createElement('div');
                    imgBox.className = 'img-preview-box';

                    // Check if it's an image based on extension
                    const ext = doc.file_name.split('.').pop().toLowerCase();
                    const isImage = ['jpg', 'jpeg', 'png', 'gif'].includes(ext);

                    if (isImage) {
                        const img = document.createElement('img');
                        // Construct correct path assuming 'uploads/contracts/' is in root
                        // Note: Ensure file_path in DB is relative or handle absolute path correctly
                        // If file_path is just "uploads/contracts/x.jpg", ensure relative link is correct.
                        // Adjust "../../" based on your file structure. 
                        // Assuming renew_contract.php is in pages/ and uploads is in root.
                        img.src = "../documents/" + doc.file_path;
                        imgBox.appendChild(img);
                    } else {
                        const icon = document.createElement('i');
                        icon.className = 'bx';

                        if (ext === 'pdf') {
                            icon.classList.add('bxs-file-pdf');
                            icon.style.color = '#d32f2f';
                        } else if (['doc', 'docx'].includes(ext)) {
                            icon.classList.add('bxs-file-doc');
                            icon.style.color = '#1976d2';
                        } else {
                            icon.classList.add('bxs-file');
                            icon.style.color = '#666';
                        }
                        imgBox.appendChild(icon);
                    }

                    card.appendChild(imgBox);

                    // Filename Info
                    const nameWrapper = document.createElement('div');
                    nameWrapper.className = 'filename-info';

                    const span = document.createElement('span');
                    span.className = 'filename-text';
                    span.textContent = doc.file_name;

                    const tooltip = document.createElement('div');
                    tooltip.className = 'filename-tooltip';
                    tooltip.textContent = doc.file_name;

                    nameWrapper.appendChild(span);
                    nameWrapper.appendChild(tooltip);

                    card.appendChild(nameWrapper);
                    previewContainer.appendChild(card);
                });
            }

            // 4. Render New Files (From Input)
            function renderNewFiles() {
                // Note: We do NOT clear the container here. 
                // We append new file previews to the existing list.

                newFiles.forEach((file, index) => {
                    // Create a temporary ID to find the element if needed
                    const tempId = `new_${index}`;
                    // Check if this file is already rendered to prevent duplicates in DOM
                    if (document.getElementById(`preview_${tempId}`)) return;

                    const card = document.createElement('div');
                    card.className = 'doc-preview-item';
                    card.id = `preview_${tempId}`;
                    card.dataset.type = 'new';
                    card.dataset.index = index;

                    // Delete Button
                    const removeBtn = document.createElement('div');
                    removeBtn.className = 'remove-doc-btn';
                    removeBtn.innerHTML = '<i class=\'bx bx-x\'></i>';
                    removeBtn.title = 'Remove';

                    removeBtn.onclick = function(e) {
                        e.stopPropagation();
                        newFiles.splice(index, 1); // Remove from array
                        syncFileInput(); // Renew input
                        card.remove(); // Remove from DOM
                        checkForChanges(); // Enable save button
                    };

                    card.appendChild(removeBtn);

                    // Image / Icon Box
                    const imgBox = document.createElement('div');
                    imgBox.className = 'img-preview-box';

                    if (file.type.startsWith('image/')) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const img = document.createElement('img');
                            img.src = e.target.result;
                            imgBox.appendChild(img);
                        }
                        reader.readAsDataURL(file);
                    } else {
                        const icon = document.createElement('i');
                        icon.className = 'bx';

                        if (file.type.includes('pdf')) {
                            icon.classList.add('bxs-file-pdf');
                            icon.style.color = '#d32f2f';
                        } else if (file.type.includes('word') || file.name.endsWith('.docx')) {
                            icon.classList.add('bxs-file-doc');
                            icon.style.color = '#1976d2';
                        } else {
                            icon.classList.add('bxs-file');
                            icon.style.color = '#666';
                        }
                        imgBox.appendChild(icon);
                    }

                    card.appendChild(imgBox);

                    // Filename Info
                    const nameWrapper = document.createElement('div');
                    nameWrapper.className = 'filename-info';

                    const span = document.createElement('span');
                    span.className = 'filename-text';
                    span.textContent = file.name;

                    const tooltip = document.createElement('div');
                    tooltip.className = 'filename-tooltip';
                    tooltip.textContent = file.name;

                    nameWrapper.appendChild(span);
                    nameWrapper.appendChild(tooltip);

                    card.appendChild(nameWrapper);
                    previewContainer.appendChild(card);
                });
            }

            if (fileInput && previewContainer) {
                // 1. Listen for new file selections
                fileInput.addEventListener('change', function(e) {
                    const selectedFiles = Array.from(this.files);

                    if (selectedFiles.length === 0) return;

                    selectedFiles.forEach(file => {
                        // Check for duplicates in newFiles
                        const isDuplicate = newFiles.some(existingFile => existingFile.name === file.name);
                        if (!isDuplicate) {
                            newFiles.push(file);
                        }
                    });

                    syncFileInput();
                    renderNewFiles(); // Append new previews
                    checkForChanges();

                    // MARK DOCUMENT AS MODIFIED
                    window.anyDocumentModified = true;
                });
            }

            // Initialize on Load
            // We use a small timeout to ensure DOM is fully ready if called inline
            setTimeout(() => {
                renderExistingDocs();
            }, 100);

        })();
        // =========================================================================
    </script>
</body>

</html>

<!-- FUNCTIONAL RENEW STARTS HERE -->
<!-- DISABLED SAVE CHANGES BUTTON -->