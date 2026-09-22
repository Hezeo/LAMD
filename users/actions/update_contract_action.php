<?php
// Disable default error printing to screen (prevents JSON corruption)
ini_set('display_errors', 0);
// Log errors to file instead
ini_set('log_errors', 1);

require '../../config.php';
require_once '../../config_session.php';
checkLogin();
requirePermission('Land Owners');

// Set custom exception handler to return JSON errors
set_exception_handler(function ($exception) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine()
    ]);
    exit;
});

header('Content-Type: application/json');

$response = ['success' => false, 'message' => '', 'deleted_ids' => []];

try {
    // --- NEW: IMMEDIATE DELETE LOGIC (For "Remove Lot" button) ---
    if (isset($_POST['delete_immediate_lot'])) {

        // PRIORITY 1: Delete by "Group Representative ID" (The Lot Block Deletion)
        // The JS sends an ID of one row inside the Lot. We use this to find the Lot No and Contract Signature,
        // then delete EVERYTHING matching that Lot No + Signature.
        $ids = json_decode($_POST['ids'] ?? '[]', true);

        if (!empty($ids)) {
            $pdo->beginTransaction();

            // 1. Use the first ID to identify the Lot Block
            $target_id = intval($ids[0]);

            // 2. Fetch the details of this specific row to identify its Lot and Contract Signature
            $sql_check = "SELECT lot_no, contracting_party, start_date, expiry_date, paid_up_date, contract_id FROM tbl_main WHERE id = ? LIMIT 1";
            $stmt_check = $pdo->prepare($sql_check);
            $stmt_check->execute([$target_id]);
            $targetRow = $stmt_check->fetch(PDO::FETCH_ASSOC);

            if ($targetRow) {
                $lot_no = $targetRow['lot_no'];
                // GET THE CONTRACT ID FROM THE TARGET ROW
                $contract_id = $targetRow['contract_id'];

                // 3. Delete ALL rows that match this Lot No AND this Contract ID
                // (Old logic used Party + Dates. New logic uses ID.)
                $sql_del_lot = "DELETE FROM tbl_main 
                                WHERE lot_no = ? 
                                AND contract_id = ?";

                $stmt_del_lot = $pdo->prepare($sql_del_lot);
                $stmt_del_lot->execute([
                    $lot_no,
                    $contract_id
                ]);

                $pdo->commit();

                $response['success'] = true;
                $response['message'] = "Lot " . htmlspecialchars($lot_no) . " deleted successfully.";

                echo json_encode($response);
                exit;
            } else {
                $pdo->rollBack();
                $response['success'] = false;
                $response['message'] = "Record not found. It may have been already deleted.";
                echo json_encode($response);
                exit;
            }
        }

        // PRIORITY 2: Delete by Signature (if IDs are empty but Lot No is provided)
        // This is a fallback logic already in your code, kept for safety.
        if (isset($_POST['delete_lot_no'])) {
            $lot_no = strtoupper(trim($_POST['delete_lot_no']));

            if (!empty($lot_no)) {
                $pdo->beginTransaction();

                // We use the OLD hidden inputs for the signature match
                $old_contracting_party = $_POST['old_contracting_party'] ?? '';
                $old_start_date       = $_POST['old_start_date'] ?? '';
                $old_expiry_date      = $_POST['old_expiry_date'] ?? '';
                $old_paid_up_date     = $_POST['old_paid_up_date'] ?? '';

                $sql_del_sig = "DELETE FROM tbl_main 
                               WHERE lot_no = ? 
                               AND contracting_party = ? 
                               AND start_date = ? 
                               AND expiry_date = ? 
                               AND (paid_up_date = ? OR (paid_up_date IS NULL AND ? = ''))";

                $stmt_del_sig = $pdo->prepare($sql_del_sig);
                $stmt_del_sig->execute([
                    $lot_no,
                    $old_contracting_party,
                    $old_start_date,
                    $old_expiry_date,
                    $old_paid_up_date,
                    $old_paid_up_date
                ]);

                $pdo->commit();

                $response['success'] = true;
                $response['message'] = "Lot deleted successfully.";

                echo json_encode($response);
                exit;
            }
        }

        // If we reach here, nothing to delete
        $response['success'] = true;
        $response['message'] = "Nothing to delete.";
        echo json_encode($response);
        exit;
    }

    // --- 1. VALIDATE INPUT ---
    if (!isset($_POST['update_contract'])) {
        throw new Exception("Invalid request method.");
    }

    // --- 2. START TRANSACTION ---
    $pdo->beginTransaction();

    $user_id = $_SESSION['user_id'];

    // --- 3. DEFINE THE "OLD CONTRACT ID" ---
    // We now rely on the ID sent from the hidden input
    $old_contract_id = $_POST['old_contract_id'] ?? '';

    // --- PREPARE DELETION LIST ---
    // We define this array BEFORE the delete block so the Restore logic can see it.
    $idsToDelete = [];
    if (isset($_POST['docs_to_delete']) && !empty($_POST['docs_to_delete'])) {
        $idsToDelete = json_decode($_POST['docs_to_delete'], true);
        if (!is_array($idsToDelete)) {
            $idsToDelete = [];
        }
    }

        // --- =======================================================================
    // --- NEW: SOFT DELETE EXISTING DOCUMENTS ---
    // --- =======================================================================
    if (!empty($idsToDelete)) {
        $idsPlaceholder = implode(',', array_fill(0, count($idsToDelete), '?'));
        
        // UPDATE: Set status to 'deleted' instead of deleting the row
        $sqlSoftDel = "UPDATE tbl_documents SET file_status = 'deleted' WHERE doc_id IN ($idsPlaceholder)";
        $stmtSoftDel = $pdo->prepare($sqlSoftDel);
        $stmtSoftDel->execute($idsToDelete);
        
        // NOTE: We do NOT unlink() files. 
        // Keeping the file on disk allows you to view it in history logs later.
    }
    // --- =======================================================================
    // --- END SOFT DELETE ---
    // --- =======================================================================

        // --- =======================================================================
    // --- NEW: RESTORE PHOTOS TO DOCUMENTS TABLE (FIXED) ---
    // --- =======================================================================
    try {
        $currentDocs = [];
        
        // 1. Only restore if we are NOT in a "Delete Only" mode
        // Check if we have IDs to delete
        if (empty($idsToDelete)) {
            // No deletions, fetch normal active docs
            $sqlFetchDocs = "SELECT file_name, file_path, file_size FROM tbl_documents 
                             WHERE contract_id = ? 
                             AND file_status = 'active'
                             AND date_added = (
                                 SELECT MAX(date_added) 
                                 FROM tbl_documents 
                                 WHERE contract_id = ?
                             )";
            $stmtFetchDocs = $pdo->prepare($sqlFetchDocs);
            $stmtFetchDocs->execute([$old_contract_id, $old_contract_id]);
            $currentDocs = $stmtFetchDocs->fetchAll(PDO::FETCH_ASSOC);
            
        } else {
            // 2. WE HAVE DELETIONS: Fetch active docs BUT exclude the ones we just deleted.
            // Using array_merge instead of '...' for PHP 5.4/5.4 compatibility
            $bindParams = array_merge([$old_contract_id], $idsToDelete, [$old_contract_id]);
            
            $sqlFetchDocs = "SELECT file_name, file_path, file_size FROM tbl_documents 
                             WHERE contract_id = ? 
                             AND file_status = 'active'
                             AND doc_id NOT IN (" . implode(',', array_fill(0, count($idsToDelete), '?')) . ")
                             AND date_added = (
                                 SELECT MAX(date_added) 
                                 FROM tbl_documents 
                                 WHERE contract_id = ?
                             )";
            $stmtFetchDocs = $pdo->prepare($sqlFetchDocs);
            $stmtFetchDocs->execute($bindParams);
            $currentDocs = $stmtFetchDocs->fetchAll(PDO::FETCH_ASSOC);
            
            // Debug log to check if this runs
            error_log("Restore Logic Executed. Found " . count($currentDocs) . " docs to restore.");
        }

        // 3. If documents exist, insert them again (Restore/Backup logic)
        if (!empty($currentDocs)) {
            $sqlRestore = "INSERT INTO tbl_documents 
                           (contract_id, file_name, file_path, file_size, uploaded_by, date_added, file_status) 
                           VALUES (?, ?, ?, ?, ?, NOW(), 'active')"; // Default new backups to 'active'
            $stmtRestore = $pdo->prepare($sqlRestore);

            foreach ($currentDocs as $doc) {
                $stmtRestore->execute([
                    $old_contract_id,
                    $doc['file_name'],
                    $doc['file_path'],
                    $doc['file_size'],
                    $user_id
                ]);
            }
            
            // Debug log to confirm insertion
            error_log("Inserted " . count($currentDocs) . " new document rows.");
        }
    } catch (PDOException $restoreErr) {
        // Log error but do not stop the main update process
        error_log("Failed to restore documents: " . $restoreErr->getMessage());
    }
    // --- =======================================================================
    // --- END RESTORE PHOTOS ---
    // --- =======================================================================

    
    // --- 4. FETCH CURRENT DATABASE RECORDS ---
    // FIX: Fetch by Contract ID. This is much more robust than Dates.
    $existingRows = [];

    if (!empty($old_contract_id)) {
        $sql_fetch = "SELECT * FROM tbl_main WHERE contract_id = ?";
        $stmt = $pdo->prepare($sql_fetch);
        $stmt->execute([$old_contract_id]);
        $existingRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Safety Fallback: If ID is missing (very old data), try to use the Old Signature
        $old_contracting_party = $_POST['old_contracting_party'] ?? '';
        $old_start_date       = $_POST['old_start_date'] ?? '';
        $old_expiry_date      = $_POST['old_expiry_date'] ?? '';
        $old_paid_up_date     = $_POST['old_paid_up_date'] ?? '';

        $sql_fetch = "SELECT * FROM tbl_main 
                      WHERE contracting_party = ? 
                      AND start_date = ? 
                      AND expiry_date = ? 
                      AND (paid_up_date = ? OR (paid_up_date IS NULL AND ? = ''))";
        $stmt = $pdo->prepare($sql_fetch);
        $stmt->execute([
            $old_contracting_party,
            $old_start_date,
            $old_expiry_date,
            $old_paid_up_date,
            $old_paid_up_date
        ]);
        $existingRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Create Map of existing rows. 
    // FIX: Use ID as the Map Key to prevent Overwrites when Lot/Group is identical.
    $existingMap = [];
    foreach ($existingRows as $row) {
        // Normalize keys
        $lotKey = strtoupper(trim($row['lot_no'] ?? ''));
        $grpKey = strtoupper(trim($row['group_acc'] ?? ''));

        // Use the Database ID as the Key. This is unique and prevents data loss.
        $dbId = $row['id'];
        $existingMap[$dbId] = $row;
    }

    // --- =======================================================================
    // --- NEW: BACKUP PREVIOUS RECORDS TO HISTORY TABLE ---
    // --- =======================================================================
    try {
        // We insert the existing rows into tbl_updated_contracts BEFORE we modify tbl_main.
        // This ensures we save the "Previous" state of the data.

        $sqlHistory = "INSERT INTO tbl_updated_contracts (
            user_id, contract_id, cms_application_no, vendor, contracting_party,
            group_acc, lot_no, field_no, field_section, barangay,
            municipality, province, contracted_arable, start_date,
            expiry_date, paid_up_date, rate, longlat, crop_type,
            lease_status, contract_status, contract_class
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )";

        $stmtHistory = $pdo->prepare($sqlHistory);

        foreach ($existingRows as $row) {
            $stmtHistory->execute([
                $row['user_id'],
                $row['contract_id'],
                $row['cms_application_no'],
                $row['vendor'],
                $row['contracting_party'],
                $row['group_acc'],
                $row['lot_no'],
                $row['field_no'],
                $row['field_section'],
                $row['barangay'],
                $row['municipality'],
                $row['province'],
                $row['contracted_arable'],
                $row['start_date'],
                $row['expiry_date'],
                $row['paid_up_date'],
                $row['rate'],
                $row['longlat'],
                $row['crop_type'],
                $row['lease_status'],
                $row['contract_status'],
                $row['contract_class']
            ]);
        }
    } catch (PDOException $historyErr) {
        // If the history insert fails, we log the error but do NOT stop the main update.
        // This ensures the main functionality (updating tbl_main) continues to work even if history fails.
        error_log("History Insert Failed: " . $historyErr->getMessage());
    }
    // --- =======================================================================
    // --- END BACKUP LOGIC ---
    // --- =======================================================================


    // --- =======================================================================
    // --- NEW: DOCUMENT UPLOAD HANDLING ---
    // --- =======================================================================
    if (isset($_FILES['contract_documents']) && $_FILES['contract_documents']['error'][0] !== UPLOAD_ERR_NO_FILE) {

        // Define Upload Directory (Matches Add Contract path)
        $uploadDir = '../documents/';

        // Create directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $files = $_FILES['contract_documents'];
        $uploadedCount = 0;

        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $tmpName = $files['tmp_name'][$i];
                $fileName = basename($files['name'][$i]);
                $fileSize = $files['size'][$i];
                $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                // Validate File Type
                $allowedTypes = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
                if (in_array($fileType, $allowedTypes)) {

                    // FIX: Generate Unique Filename (Using same logic as Add Contract: timestamp + random hex)
                    // Produces format: 1777362427_f96ef3b7.png
                    $newFileName = time() . '_' . bin2hex(random_bytes(4)) . '.' . $fileType;
                    $destination = $uploadDir . $newFileName;

                    // Move File
                    if (move_uploaded_file($tmpName, $destination)) {
                        $uploadedCount++;

                        // --- SAVE PATH TO DATABASE ---
                        try {
                            // Updated to match your exact 'tbl_documents' schema
                            $docSql = "INSERT INTO tbl_documents 
                                (contract_id, file_name, file_path, file_size, uploaded_by, date_added, file_status) 
                                VALUES (?, ?, ?, ?, ?, NOW(), 'active')";

                            // FIX 1: Store ONLY the filename (Matches Add Contract behavior)
                            $dbPath = $newFileName;

                            // FIX 2: Format File Size (Using same logic as Add Contract: round() instead of number_format())
                            $fileSizeFormatted = ($fileSize >= 1048576) ? round($fileSize / 1048576, 2) . ' MB' : round($fileSize / 1024, 2) . ' KB';

                            $docStmt = $pdo->prepare($docSql);
                            $docStmt->execute([
                                $old_contract_id,  // Links to the contract
                                $fileName,
                                $dbPath,           // Stores just the filename (e.g. 1777362427_f96ef3b7.png)
                                $fileSizeFormatted, // Stores formatted size (e.g. 18.94 KB)
                                $user_id          // Stores who uploaded it
                            ]);
                        } catch (PDOException $docErr) {
                            // Log error but don't stop the update process
                            error_log("Failed to save document path to DB: " . $docErr->getMessage());
                        }
                        // ---------------------------------------

                    }
                }
            }
        }
    }
    // --- =======================================================================
    // --- END DOCUMENT UPLOAD HANDLING ---
    // --- =======================================================================


    // --- 5. PREPARE NEW HEADER DATA ---
    $new_contracting_party = $_POST['contracting_party'] ?? '';
    $new_cms_app_no        = $_POST['cms_application_no'] ?? '';
    $new_vendor            = $_POST['vendor'] ?? '';
    $new_start_date        = $_POST['start_date'] ?? '';
    $new_expiry_date       = $_POST['expiry_date'] ?? '';
    $new_paid_up_date      = $_POST['paid_up_date'] ?? '';

    // Convert empty date strings to NULL for cleaner DB storage
    $new_start_date   = ($new_start_date === '') ? null : $new_start_date;
    $new_expiry_date  = ($new_expiry_date === '') ? null : $new_expiry_date;
    $new_paid_up_date = ($new_paid_up_date === '') ? null : $new_paid_up_date;

    // --- 6. PROCESS SUBMITTED LOTS AND GROUPS (UPSERT) ---
    if (isset($_POST['lots']) && is_array($_POST['lots'])) {

        foreach ($_POST['lots'] as $lotIndex => $lot) {
            // Safe extraction of Lot Data
            $lot_no = strtoupper(trim($lot['lot_no'] ?? ''));

            // If lot_no is empty, we skip this iteration (safety)
            if (empty($lot_no)) continue;

            $field_no       = $lot['field_no'] ?? '';
            $field_section  = $lot['field_section'] ?? '';
            $province       = $lot['province'] ?? '';
            $municipality   = $lot['municipality'] ?? '';
            $barangay       = $lot['barangay'] ?? '';
            $longlat        = $lot['longlat'] ?? '';
            $rate           = $lot['rate'] ?? '';
            $crop_type      = $lot['crop_type'] ?? '';
            $lease_status   = $lot['lease_status'] ?? '';
            $contract_status = $lot['contract_status'] ?? '';
            $contract_class = $lot['contract_class'] ?? '';

            // Process Groups
            if (isset($lot['groups']) && is_array($lot['groups'])) {
                foreach ($lot['groups'] as $group) {
                    $group_acc = strtoupper(trim($group['name'] ?? ''));
                    $arable    = $group['arable'] ?? 0;

                    // CHECK FOR ID IN FORM DATA
                    $formGroupId = isset($group['id']) ? intval($group['id']) : null;

                    // --- LOGIC A: UPDATE EXISTING (BY ID) ---
                    if ($formGroupId && isset($existingMap[$formGroupId])) {
                        $dbRow = $existingMap[$formGroupId];
                        $db_id = $dbRow['id'];

                        $sql_update = "UPDATE tbl_main SET 
                            contract_id = ?,     /* <--- ADDED THIS LINE */
                            cms_application_no = ?, 
                            vendor = ?, 
                            contracting_party = ?, 
                            group_acc = ?, 
                            lot_no = ?, 
                            field_no = ?, 
                            field_section = ?, 
                            barangay = ?, 
                            municipality = ?, 
                            province = ?, 
                            contracted_arable = ?, 
                            start_date = ?, 
                            expiry_date = ?, 
                            paid_up_date = ?, 
                            rate = ?, 
                            longlat = ?, 
                            crop_type = ?, 
                            lease_status = ?, 
                            contract_status = ?, 
                            contract_class = ?,
                            user_id = ?
                            WHERE id = ?";

                        $stmt_upd = $pdo->prepare($sql_update);
                        $stmt_upd->execute([
                            $old_contract_id,  /* <--- ADDED THIS BINDING */
                            $new_cms_app_no,
                            $new_vendor,
                            $new_contracting_party,
                            $group_acc,
                            $lot_no,
                            $field_no,
                            $field_section,
                            $barangay,
                            $municipality,
                            $province,
                            $arable,
                            $new_start_date,
                            $new_expiry_date,
                            $new_paid_up_date,
                            $rate,
                            $longlat,
                            $crop_type,
                            $lease_status,
                            $contract_status,
                            $contract_class,
                            $user_id,
                            $db_id
                        ]);

                        // Remove from map so it is not deleted later
                        unset($existingMap[$formGroupId]);
                    }
                    // --- LOGIC B: INSERT NEW (FIXED) ---
                    else {
                        // Explicitly define the column order and values to prevent HY093 errors
                        $columns = [
                            'contract_id',  // <--- ADDED THIS
                            'cms_application_no',
                            'vendor',
                            'contracting_party',
                            'group_acc',
                            'lot_no',
                            'field_no',
                            'field_section',
                            'barangay',
                            'municipality',
                            'province',
                            'contracted_arable',
                            'start_date',
                            'expiry_date',
                            'paid_up_date',
                            'rate',
                            'longlat',
                            'crop_type',
                            'lease_status',
                            'contract_status',
                            'contract_class',
                            'user_id'
                        ];

                        $values = [
                            $old_contract_id, // <--- ADDED THIS
                            $new_cms_app_no,
                            $new_vendor,
                            $new_contracting_party,
                            $group_acc,
                            $lot_no,
                            $field_no,
                            $field_section,
                            $barangay,
                            $municipality,
                            $province,
                            $arable,
                            $new_start_date,
                            $new_expiry_date,
                            $new_paid_up_date,
                            $rate,
                            $longlat,
                            $crop_type,
                            $lease_status,
                            $contract_status,
                            $contract_class,
                            $user_id
                        ];

                        $placeholders = implode(',', array_fill(0, count($columns), '?'));
                        $colList = implode(',', $columns);

                        $sql_insert = "INSERT INTO tbl_main ({$colList}) VALUES ({$placeholders})";

                        $stmt_ins = $pdo->prepare($sql_insert);
                        $stmt_ins->execute($values);
                    }
                }
            }
        }
    }

    // --- 7. DELETE REMOVED ITEMS ---
    // Anything left in the map was not sent in the form -> DELETE IT
    $deletedIds = [];
    foreach ($existingMap as $row) {
        $deletedIds[] = $row['id']; // Track IDs
        $sql_delete = "DELETE FROM tbl_main WHERE id = ?";
        $stmt_del = $pdo->prepare($sql_delete);
        $stmt_del->execute([$row['id']]);
    }

    // --- 8. COMMIT ---
    $pdo->commit();


        // ==========================================
    // INSERT ACTIVITY LOG & NOTIFICATIONS (UPDATED CONTRACT)
    // ==========================================
    try {
        // 1. INSERT ACTIVITY LOG
        $logStmt = $pdo->prepare("INSERT INTO tbl_activity_logs 
                (activity, action, user_id, admin_id, contract_id, start_date, expiry_date, paid_up_date, dateAdded) 
                VALUES 
                (:activity, :action, :user_id, :admin_id, :contract_id, :start_date, :expiry_date, :paid_up_date, NOW())");

        $logStmt->execute([
            // Message: "Updated a contract of DELA CRUZ"
            ':activity'           => "Updated a contract of {$new_contracting_party}",
            ':action'             => "update",
            ':user_id'            => $_SESSION['user_id'],
            ':admin_id'           => null,
            // Parameter: contract_id
            ':contract_id'        => $old_contract_id,
            ':start_date'         => $new_start_date,
            ':expiry_date'        => $new_expiry_date,
            ':paid_up_date'       => $new_paid_up_date
        ]);

        // 2. GET THE ID OF THE LOG WE JUST CREATED
        $last_log_id = $pdo->lastInsertId();

        // 3. INSERT NOTIFICATIONS FOR ALL COWORKERS
        if ($last_log_id) {
            
            // Get ALL user_ids from tbl_users EXCEPT the current user
            $getUsersStmt = $pdo->prepare("SELECT user_id FROM tbl_users WHERE user_id != :my_id");
            $getUsersStmt->execute([':my_id' => $_SESSION['user_id']]);
            
            // Fetch all matching IDs
            $all_other_users = $getUsersStmt->fetchAll(PDO::FETCH_COLUMN);

            // Prepare the insert statement for notifications
            $insertNotifStmt = $pdo->prepare("INSERT INTO tbl_notifications (user_id, log_id, is_seen, date_added) VALUES (:user_id, :log_id, 'no', NOW())");

            // LOOP THROUGH EACH USER ID AND INSERT A ROW FOR EACH ONE
            if (!empty($all_other_users)) {
                foreach ($all_other_users as $user_id_to_notify) {
                    $insertNotifStmt->execute([
                        ':user_id' => $user_id_to_notify,
                        ':log_id'  => $last_log_id
                    ]);
                }
            }
        }

    } catch (PDOException $logErr) {
        // Fail silently
    }
    // ==========================================



    $response['success'] = true;
    $response['message'] = "Contract updated successfully.";
    $response['deleted_ids'] = $deletedIds; // Send back list of deleted IDs

    // --- FIX START: Send new values back to the browser ---
    $response['new_party'] = $new_contracting_party;
    $response['new_start'] = $new_start_date ?? ''; // Convert NULL to empty string for URL
    $response['new_expiry'] = $new_expiry_date ?? '';
    $response['new_paid'] = $new_paid_up_date ?? '';
    // --- FIX END ---

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;

// FUNCTIONAL UPDATE STARTS HERE