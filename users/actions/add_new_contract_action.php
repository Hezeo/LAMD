<?php
session_start();
require_once '../../config.php';
require_once '../../config_session.php';

// Set header to return JSON response
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
    exit();
}

if (isset($_POST['add_contract'])) {

    // 1. COLLECT SHARED DATA (Only the Header info)
    $cms_application_no = trim($_POST['cms_application_no']);
    $vendor = trim($_POST['vendor']);
    $contracting_party = strtoupper(trim($_POST['contracting_party']));

    $start_date = trim($_POST['start_date']);
    $expiry_date = trim($_POST['expiry_date']);
    $paid_up_date = !empty($_POST['paid_up_date']) ? trim($_POST['paid_up_date']) : NULL;

    // Validation: Check if lots array exists
    if (empty($_POST['lots'])) {
        echo json_encode(['success' => false, 'message' => 'Please add at least one Lot.']);
        exit();
    }

    try {
        $pdo->beginTransaction();

        // CHECK LATEST CONTRACT ID
        // We check the highest ID in BOTH tbl_main AND tbl_archived_contracts to prevent ID reuse.
        $stmt_check_id = $pdo->query("SELECT COALESCE(MAX(id), 0) + 1 AS next_contract_id FROM (
            SELECT contract_id as id FROM tbl_main 
            UNION 
            SELECT contract_id as id FROM tbl_archived_contracts
        ) AS combined_ids");
        $new_contract_id = $stmt_check_id->fetch(PDO::FETCH_ASSOC)['next_contract_id'];

        // SQL for tbl_main
        $sql_main = "INSERT INTO tbl_main (
            contract_id, cms_application_no, vendor, contracting_party, group_acc, lot_no, 
            field_no, field_section, barangay, municipality, province, 
            contracted_arable, start_date, expiry_date, paid_up_date, rate, 
            longlat, crop_type, lease_status, contract_status, contract_class, user_id
        ) VALUES (
            :contract_id, :cms_application_no, :vendor, :contracting_party, :group_acc, :lot_no, 
            :field_no, :field_section, :barangay, :municipality, :province, 
            :contracted_arable, :start_date, :expiry_date, :paid_up_date, :rate, 
            :longlat, :crop_type, :lease_status, :contract_status, :contract_class, :user_id
        )";

        $stmt_main = $pdo->prepare($sql_main);

        // SQL for tbl_contracts_and_users
        $sql_link = "INSERT INTO tbl_contracts_and_users (id, vendor, user_id, dateAdded) 
                     VALUES (:id, :vendor, :user_id, NOW())";
        $stmt_link = $pdo->prepare($sql_link);

        // ===== NESTED LOOP LOGIC =====
        // Loop through each LOT
        foreach ($_POST['lots'] as $lotData) {

            // Get Data SPECIFIC to this Lot (Location, Rate, Status, etc.)
            $current_lot_no = isset($lotData['lot_no']) ? trim($lotData['lot_no']) : '';
            $current_field_no = isset($lotData['field_no']) ? trim($lotData['field_no']) : '';
            $current_field_section = isset($lotData['field_section']) ? trim($lotData['field_section']) : '';

            $current_province = isset($lotData['province']) ? trim($lotData['province']) : '';
            $current_municipality = isset($lotData['municipality']) ? trim($lotData['municipality']) : '';
            $current_barangay = isset($lotData['barangay']) ? trim($lotData['barangay']) : '';
            $current_longlat = isset($lotData['longlat']) ? trim($lotData['longlat']) : '';

            $current_rate = isset($lotData['rate']) ? trim($lotData['rate']) : '0';
            $current_crop_type = isset($lotData['crop_type']) ? trim($lotData['crop_type']) : 'C74';

            $current_lease_status = isset($lotData['lease_status']) ? trim($lotData['lease_status']) : 'Active';
            $current_contract_status = isset($lotData['contract_status']) ? trim($lotData['contract_status']) : 'EXISTING';
            $current_contract_class = isset($lotData['contract_class']) ? trim($lotData['contract_class']) : 'CP&GA';

            if (empty($current_lot_no)) continue; // Skip empty lots

            // 2. Loop through each GROUP ACCOUNT inside this Lot
            if (isset($lotData['groups']) && is_array($lotData['groups'])) {
                foreach ($lotData['groups'] as $groupData) {

                    $current_group = isset($groupData['name']) ? strtoupper(trim($groupData['name'])) : "NONE";
                    $current_arable = isset($groupData['arable']) ? trim($groupData['arable']) : '0';

                    // 3. Insert into tbl_main
                    // Note: We use the SHARED dates but the SPECIFIC Lot details
                    $stmt_main->execute([
                        ':contract_id' => $new_contract_id,
                        ':cms_application_no' => $cms_application_no,
                        ':vendor' => $vendor,
                        ':contracting_party' => $contracting_party,
                        ':group_acc' => $current_group,
                        ':lot_no' => $current_lot_no,
                        ':field_no' => $current_field_no,
                        ':field_section' => $current_field_section,
                        ':barangay' => $current_barangay,     // From Lot Data
                        ':municipality' => $current_municipality, // From Lot Data
                        ':province' => $current_province,       // From Lot Data
                        ':contracted_arable' => $current_arable,
                        ':start_date' => $start_date,           // Shared
                        ':expiry_date' => $expiry_date,         // Shared
                        ':paid_up_date' => $paid_up_date,       // Shared
                        ':rate' => $current_rate,               // From Lot Data
                        ':longlat' => $current_longlat,         // From Lot Data
                        ':crop_type' => $current_crop_type,     // From Lot Data
                        ':lease_status' => $current_lease_status,         // From Lot Data
                        ':contract_status' => $current_contract_status,   // From Lot Data
                        ':contract_class' => $current_contract_class,     // From Lot Data
                        ':user_id' => $_SESSION['user_id'],
                    ]);

                    // 4. Link to User
                    $last_id = $pdo->lastInsertId();
                    $stmt_link->execute([
                        ':id' => $last_id,
                        ':vendor' => $vendor,
                        ':user_id' => $_SESSION['user_id']
                    ]);
                }
            }
        }

        $pdo->commit();

        // ==========================================
        // UPLOAD DOCUMENTS LOGIC
        // ==========================================
        if (isset($_FILES['contract_documents']) && !empty($_FILES['contract_documents']['name'][0])) {

            // 1. Define Target Directory
            // Path is relative to 'actions/' folder, pointing to 'users/documents/'
            $uploadDir = '../documents/';

            // Create directory if it doesn't exist
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            // 2. Prepare Variables
            $files = $_FILES['contract_documents'];
            $numFiles = count($files['name']);

            // 3. Prepare SQL Statement for Documents
            // We use named parameters for consistency with your main contract insert
            $docStmt = $pdo->prepare("INSERT INTO tbl_documents 
                (contract_id, file_name, file_path, file_size, uploaded_by) 
                VALUES 
                (:contract_id, :file_name, :file_path, :file_size, :uploaded_by)");

            // 4. Loop through each uploaded file
            for ($i = 0; $i < $numFiles; $i++) {

                $fileName = basename($files['name'][$i]);
                $fileTmp  = $files['tmp_name'][$i];
                $fileSize = $files['size'][$i];
                $fileError = $files['error'][$i];

                // Skip if upload error
                if ($fileError !== UPLOAD_ERR_OK) continue;

                // Get Extension
                $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                // Allowed Extensions
                $allowedExts = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];

                if (in_array($fileExt, $allowedExts)) {

                    // Generate Unique Filename to prevent overwrites
                    // Format: Timestamp + RandomString + Extension
                    $newFileName = time() . '_' . bin2hex(random_bytes(4)) . '.' . $fileExt;
                    $targetFilePath = $uploadDir . $newFileName;

                    // Move File
                    if (move_uploaded_file($fileTmp, $targetFilePath)) {

                        // Format File Size for display
                        $displaySize = ($fileSize >= 1048576) ? round($fileSize / 1048576, 2) . ' MB' : round($fileSize / 1024, 2) . ' KB';

                        // Insert into Database
                        $docStmt->execute([
                            ':contract_id'   => $new_contract_id,
                            ':file_name'     => $fileName,             // Original Name
                            ':file_path'     => $newFileName,          // Server Name
                            ':file_size'     => $displaySize,
                            ':uploaded_by'   => $_SESSION['user_id']
                        ]);
                    }
                }
            }
        }
        // ==========================================

                // ==========================================
        // INSERT ACTIVITY LOG & NOTIFICATIONS
        // ==========================================
        try {
            // 1. INSERT ACTIVITY LOG (YOUR ORIGINAL CODE)
            $logStmt = $pdo->prepare("INSERT INTO tbl_activity_logs 
                (activity, action, user_id, admin_id, contract_id, start_date, expiry_date, paid_up_date) 
                VALUES 
                (:activity, :action, :user_id, :admin_id, :contract_id, :start_date, :expiry_date, :paid_up_date)");

            $logStmt->execute([
                ':activity'           => "Created new contract for {$contracting_party}",
                ':action'             => "create",
                ':user_id'            => $_SESSION['user_id'],
                ':admin_id'           => null,
                ':contract_id'        => $new_contract_id,
                ':start_date'         => $start_date,
                ':expiry_date'        => $expiry_date,
                ':paid_up_date'       => $paid_up_date
            ]);

            // 2. GET THE ID OF THE LOG WE JUST CREATED
            $last_log_id = $pdo->lastInsertId();

            // 3. INSERT NOTIFICATIONS FOR ALL COWORKERS
            if ($last_log_id) {
                
                // Get ALL user_ids from tbl_users EXCEPT the current user (you)
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

        // Return success JSON
        echo json_encode(['success' => true, 'message' => 'Contract saved successfully!']);
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
        exit();
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit();
}
