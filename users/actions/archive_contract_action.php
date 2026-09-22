<?php
// Disable default error printing to screen (prevents JSON corruption)
ini_set('display_errors', 0);
// Log errors to file instead
ini_set('log_errors', 1);

require '../../config.php';
require_once '../../config_session.php';
checkLogin();
requirePermission('Land Owners');

// Set header early
header('Content-Type: application/json');

try {
    // 1. Get Contract ID (New Primary Input)
    // We no longer rely on Party + Dates being passed via POST
    $contract_id = intval($_POST['contract_id'] ?? 0);

    if ($contract_id <= 0) {
        throw new Exception("Invalid request: Contract ID is missing or invalid.");
    }

    // 2. SAFETY FETCH: Get the Data using the ID
    // We need to fetch the Party and Dates from the DB because 
    // the Log table expects these values, and we want to ensure accuracy.
    $sql_get_details = "SELECT * FROM tbl_main WHERE contract_id = ? LIMIT 1";
    $stmt_get = $pdo->prepare($sql_get_details);
    $stmt_get->execute([$contract_id]);
    $sourceData = $stmt_get->fetch(PDO::FETCH_ASSOC);

    if (!$sourceData) {
        throw new Exception("Error: Contract ID not found in database.");
    }

    // Extract variables for reuse (Archive, Log, Delete)
    $party = $sourceData['contracting_party'];
    $start_date = $sourceData['start_date'];
    $expiry_date = $sourceData['expiry_date'];
    $paid_up_date = $sourceData['paid_up_date'];

    $pdo->beginTransaction();

    // ==========================================
    // ARCHIVE CONTRACTS BEFORE DELETION
    // ==========================================
    try {
        // Updated WHERE Clause: Uses contract_id instead of Party/Dates combo
        // ADDED: 'contract_id' to both the INSERT column list and SELECT list
                $archiveSql = "INSERT INTO tbl_archived_contracts 
            (id, contract_id, cms_application_no, vendor, contracting_party, group_acc, lot_no, field_no, field_section, barangay, municipality, province, contracted_arable, start_date, expiry_date, paid_up_date, rate, longlat, crop_type, lease_status, contract_status, contract_class, user_id, date_added, date_modified) 
            SELECT 
                id, contract_id, cms_application_no, vendor, contracting_party, group_acc, lot_no, field_no, field_section, barangay, municipality, province, contracted_arable, start_date, expiry_date, paid_up_date, rate, longlat, crop_type, lease_status, contract_status, contract_class, ?, NOW(), NOW() 
            FROM tbl_main 
            WHERE contract_id = ?";

        $stmtArchive = $pdo->prepare($archiveSql);
        // We pass $_SESSION['user_id'] first to fill the '?' in the SELECT list,
        // and $contract_id second to fill the '?' in the WHERE clause.
        $stmtArchive->execute([$_SESSION['user_id'], $contract_id]);
    } catch (PDOException $archiveErr) {
        throw new Exception("Failed to archive contract: " . $archiveErr->getMessage());
    }

    // ==========================================
    // DELETE CONTRACTS
    // ==========================================
    // Updated WHERE Clause: Uses contract_id
    // We no longer need the complex (paid_up_date IS NULL OR...) logic because contract_id is unique
    $sql = "DELETE FROM tbl_main WHERE contract_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$contract_id]);

    $rowCount = $stmt->rowCount();
    $pdo->commit();

        // ==========================================
    // INSERT ACTIVITY LOG & NOTIFICATIONS (ARCHIVED CONTRACT)
    // ==========================================
    try {
        // 1. INSERT ACTIVITY LOG
        $logStmt = $pdo->prepare("INSERT INTO tbl_activity_logs 
            (activity, action, user_id, admin_id, contract_id, start_date, expiry_date, paid_up_date, dateAdded) 
            VALUES 
            (:activity, :action, :user_id, :admin_id, :contract_id, :start_date, :expiry_date, :paid_up_date, NOW())");

        $logStmt->execute([
            // Message: "Archived a contract of DELA CRUZ"
            ':activity'           => "Archived a contract of {$party}",
            // Action type: 'archive'
            ':action'             => "archive",
            ':user_id'            => $_SESSION['user_id'],
            ':admin_id'           => null,
            // Binding the actual contract ID
            ':contract_id'        => $contract_id,
            ':start_date'         => $start_date,
            ':expiry_date'        => $expiry_date,
            ':paid_up_date'       => $paid_up_date
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
        error_log("Log Error: " . $logErr->getMessage());
    }
    // ==========================================

    // ==========================================
    // RETURN RESPONSE
    // ==========================================
    // FIXED LOGIC: If rowCount > 0, it means we deleted rows. Success!
    if ($rowCount > 0) {
        echo json_encode(['success' => true, 'message' => "$rowCount contract record(s) deleted successfully."]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No matching contract records found to delete.']);
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Log error for debugging
    error_log("Delete Contract Error: " . $e->getMessage());

    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
} catch (Throwable $e) {
    // Catch fatal errors in PHP 7+
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Delete Contract Fatal Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
}
