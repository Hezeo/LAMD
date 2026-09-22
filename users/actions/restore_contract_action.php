<?php
require_once '../../config.php';
require_once '../../config_session.php';

// 1. Check Login
checkLogin();

// 2. Check Request Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// 3. Validate Input
if (!isset($_POST['contract_id']) || empty($_POST['contract_id'])) {
    echo json_encode(['success' => false, 'message' => 'Contract ID is required']);
    exit;
}

 $contract_id = intval($_POST['contract_id']);
 $user_id = getCurrentUserId();

 $response = [];

try {
    // --- START TRANSACTION ---
    $pdo->beginTransaction();

    // --- STEP 1: FETCH ARCHIVED DATA ---
    $stmtFetch = $pdo->prepare("SELECT * FROM tbl_archived_contracts WHERE contract_id = :contract_id");
    $stmtFetch->execute([':contract_id' => $contract_id]);
    $archivedRows = $stmtFetch->fetchAll(PDO::FETCH_ASSOC);

    if (empty($archivedRows)) {
        throw new Exception("Contract not found in archive.");
    }

    // --- STEP 2: RESTORE TO MAIN TABLE ---
    // Using Named Parameters (:name) eliminates counting errors.
    // We match the array keys exactly to the column names.
    $sqlInsert = "INSERT INTO tbl_main (
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
                    longlat, 
                    crop_type, 
                    lease_status, 
                    contract_status, 
                    contract_class, 
                    user_id
                  ) VALUES (
                    :contract_id,
                    :cms_application_no,
                    :vendor,
                    :contracting_party,
                    :group_acc,
                    :lot_no,
                    :field_no,
                    :field_section,
                    :barangay,
                    :municipality,
                    :province,
                    :contracted_arable,
                    :start_date,
                    :expiry_date,
                    :paid_up_date,
                    :rate,
                    :longlat,
                    :crop_type,
                    :lease_status,
                    :contract_status,
                    :contract_class,
                    :user_id
                  )";

    $stmtInsert = $pdo->prepare($sqlInsert);

    foreach ($archivedRows as $row) {
        // We pass an array where keys match the :names in the SQL above.
        // No counting required!
        $stmtInsert->execute([
            ':contract_id'          => $row['contract_id'],
            ':cms_application_no'   => $row['cms_application_no'],
            ':vendor'               => $row['vendor'],
            ':contracting_party'    => $row['contracting_party'],
            ':group_acc'            => $row['group_acc'],
            ':lot_no'               => $row['lot_no'],
            ':field_no'             => $row['field_no'],
            ':field_section'        => $row['field_section'],
            ':barangay'             => $row['barangay'],
            ':municipality'         => $row['municipality'],
            ':province'             => $row['province'],
            ':contracted_arable'    => $row['contracted_arable'],
            ':start_date'           => $row['start_date'],
            ':expiry_date'          => $row['expiry_date'],
            ':paid_up_date'         => $row['paid_up_date'],
            ':rate'                 => $row['rate'],
            ':longlat'              => $row['longlat'],
            ':crop_type'            => $row['crop_type'],
            ':lease_status'         => $row['lease_status'],
            ':contract_status'      => $row['contract_status'],
            ':contract_class'       => $row['contract_class'],
            ':user_id'              => $user_id 
        ]);
    }

    // --- STEP 3: DELETE FROM ARCHIVE ---
    $stmtDelete = $pdo->prepare("DELETE FROM tbl_archived_contracts WHERE contract_id = :contract_id");
    $stmtDelete->execute([':contract_id' => $contract_id]);

        // --- STEP 4: LOG ACTIVITY & NOTIFICATIONS ---
    try {
        // 1. PREPARE LOG DATA
        $firstRow = $archivedRows[0]; 
        
        $stmtLog = $pdo->prepare("INSERT INTO tbl_activity_logs (
            activity, 
            action, 
            user_id, 
            contract_id, 
            start_date, 
            expiry_date, 
            paid_up_date,
            dateAdded
        ) VALUES (
            :activity, 
            :action, 
            :user_id, 
            :contract_id, 
            :start_date, 
            :expiry_date, 
            :paid_up_date,
            NOW()
        )");

        $logAction = 'restore'; 
        $logMessage = "Restored a contract of " . $firstRow['contracting_party'];

        // 2. EXECUTE LOG INSERT
        $stmtLog->execute([
            ':activity'     => $logMessage,
            ':action'       => $logAction,
            ':user_id'      => $user_id,
            ':contract_id'  => $firstRow['contract_id'],
            ':start_date'   => $firstRow['start_date'],
            ':expiry_date'  => $firstRow['expiry_date'],
            ':paid_up_date' => $firstRow['paid_up_date']
        ]);

        // 3. GET THE ID OF THE LOG WE JUST CREATED
        $last_log_id = $pdo->lastInsertId();

        // 4. INSERT NOTIFICATIONS FOR ALL COWORKERS
        if ($last_log_id) {
            
            // Get ALL user_ids from tbl_users EXCEPT the current user
            $getUsersStmt = $pdo->prepare("SELECT user_id FROM tbl_users WHERE user_id != :my_id");
            $getUsersStmt->execute([':my_id' => $user_id]);
            
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
        // Fail silently so the restore process doesn't break if logging fails
        error_log("Restore Log/Notification Error: " . $logErr->getMessage());
    }

    // --- COMMIT TRANSACTION ---
    $pdo->commit();

    $response = [
        'success' => true, 
        'message' => 'Contract restored successfully.'
    ];

} catch (Exception $e) {
    // Rollback on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $response = [
        'success' => false, 
        'message' => 'Database Error: ' . $e->getMessage()
    ];
}

// Output JSON
header('Content-Type: application/json');
echo json_encode($response);
?>