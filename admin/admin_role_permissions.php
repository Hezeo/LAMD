<?php
session_start();
require_once '../config.php';

// Security Check
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_login.php");
    exit();
}

 $message = '';
 $show_success_modal = false;

// ==========================================
// ROLE MANAGEMENT HANDLERS
// ==========================================

// 1. Create Role
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_role'])) {
    $new_role_name = trim($_POST['new_role_name']);
    if (!empty($new_role_name)) {
        try {
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM tbl_roles WHERE role_name = ?");
            $stmtCheck->execute([$new_role_name]);
            if ($stmtCheck->fetchColumn() == 0) {
                $stmtIns = $pdo->prepare("INSERT INTO tbl_roles (role_name, dateAdded) VALUES (?, NOW())");
                $stmtIns->execute([$new_role_name]);
                $message = 'Role created successfully!';
                $show_success_modal = true;
            } else {
                $message = 'Error: Role name already exists.';
            }
        } catch (PDOException $e) {
            $message = 'Error creating role: ' . $e->getMessage();
        }
    }
}

// 2. Rename Role
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rename_role'])) {
    $role_id = $_POST['rename_role_id'];
    $new_name = trim($_POST['rename_role_name']);
    if (!empty($new_name) && $role_id > 0) {
        try {
            $stmtUpdate = $pdo->prepare("UPDATE tbl_roles SET role_name = ?, dateModified = NOW() WHERE role_id = ?");
            $stmtUpdate->execute([$new_name, $role_id]);
            $message = 'Role renamed successfully!';
            $show_success_modal = true;
        } catch (PDOException $e) {
            $message = 'Error renaming role: ' . $e->getMessage();
        }
    }
}

// 3. Delete Role
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_role'])) {
    $delete_id = $_POST['delete_role_id'];
    // Prevent deleting critical roles (like Admin - assume ID 1 is Admin)
    if ($delete_id > 1) { 
        try {
            // Delete permissions associated with this role first
            $pdo->prepare("DELETE FROM tbl_role_permissions WHERE role_id = ?")->execute([$delete_id]);
            // Delete the role
            $pdo->prepare("DELETE FROM tbl_roles WHERE role_id = ?")->execute([$delete_id]);
            $message = 'Role deleted successfully!';
            $show_success_modal = true;
        } catch (PDOException $e) {
            $message = 'Error deleting role: ' . $e->getMessage();
        }
    } else {
        $message = 'Error: Cannot delete the primary Administrator role.';
        $show_success_modal = true; // Show modal for error too
    }
}

// ==========================================
// PERMISSION MANAGEMENT HANDLERS
// ==========================================

// Save Access
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_permissions'])) {
    $role_id = $_POST['role_id'] ?? 0;
    $permissions = $_POST['permissions'] ?? []; 

    if ($role_id > 0) {
        try {
            $pdo->beginTransaction();

            // 1. Get all existing permission IDs for this role
            $stmtExisting = $pdo->prepare("SELECT permission_id FROM tbl_role_permissions WHERE role_id = ?");
            $stmtExisting->execute([$role_id]);
            $existing_ids = $stmtExisting->fetchAll(PDO::FETCH_COLUMN);

            // Prepare statements for efficiency
            // Update existing record
            $stmtUpdate = $pdo->prepare("UPDATE tbl_role_permissions SET can = ? WHERE role_id = ? AND permission_id = ?");
            // Insert new record
            $stmtInsert = $pdo->prepare("INSERT INTO tbl_role_permissions (role_id, permission_id, can, dateAdded) VALUES (?, ?, ?, NOW())");
            // Delete unchecked/none
            $stmtDelete = $pdo->prepare("DELETE FROM tbl_role_permissions WHERE role_id = ? AND permission_id = ?");

            foreach ($permissions as $perm_id => $access_level) {
                $perm_id = (int)$perm_id; // Ensure integer

                if ($access_level === 'none') {
                    // If set to none, delete it
                    $stmtDelete->execute([$role_id, $perm_id]);
                } else {
                    // Check if this permission already exists in DB
                    if (in_array($perm_id, $existing_ids)) {
                        // Update existing
                        $stmtUpdate->execute([$access_level, $role_id, $perm_id]);
                    } else {
                        // Insert new
                        $stmtInsert->execute([$role_id, $perm_id, $access_level]);
                    }
                }
            }

            $pdo->commit();
            $message = 'Access updated successfully!';
            $show_success_modal = true;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = 'Error updating permissions: ' . $e->getMessage();
        }
    }
}

// Add Module
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_new_tab'])) {
    $tab_name = trim($_POST['tab_name']);
    $tab_link = trim($_POST['tab_link']);
    $tab_icon = trim($_POST['selected_icon']);

    if (!empty($tab_name) && !empty($tab_link) && !empty($tab_icon)) {
        try {
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM tbl_permissions WHERE permission_name = ?");
            $stmtCheck->execute([$tab_name]);
            if ($stmtCheck->fetchColumn() == 0) {
                $stmtInsPerm = $pdo->prepare("INSERT INTO tbl_permissions (permission_name, permission_link, permission_icon, dateAdded) VALUES (?, ?, ?, NOW())");
                $stmtInsPerm->execute([$tab_name, $tab_link, $tab_icon]);
                $message = 'Module added successfully!';
                $show_success_modal = true;
            } else {
                $message = 'Error: Module Name already exists.';
            }
        } catch (PDOException $e) {
            $message = 'Error adding module: ' . $e->getMessage();
        }
    }
}

// Edit Module
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_module'])) {
    $edit_id = $_POST['edit_perm_id'];
    $edit_name = trim($_POST['edit_tab_name']);
    $edit_link = trim($_POST['edit_tab_link']);
    $edit_icon = trim($_POST['edit_selected_icon']);
    try {
        $stmtUpdate = $pdo->prepare("UPDATE tbl_permissions SET permission_name = ?, permission_link = ?, permission_icon = ?, dateModified = NOW() WHERE permission_id = ?");
        $stmtUpdate->execute([$edit_name, $edit_link, $edit_icon, $edit_id]);
        $message = 'Module updated successfully!';
        $show_success_modal = true;
    } catch (PDOException $e) {
        $message = 'Error updating module: ' . $e->getMessage();
    }
}

// Delete Module
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_module'])) {
    $delete_id = $_POST['delete_perm_id'];
    try {
        $pdo->prepare("DELETE FROM tbl_role_permissions WHERE permission_id = ?")->execute([$delete_id]);
        $pdo->prepare("DELETE FROM tbl_permissions WHERE permission_id = ?")->execute([$delete_id]);
        $message = 'Module deleted successfully!';
        $show_success_modal = true;
    } catch (PDOException $e) {
        $message = 'Error deleting module: ' . $e->getMessage();
    }
}

// Fetch Data
 $roles = $pdo->query("SELECT * FROM tbl_roles ORDER BY role_name ASC")->fetchAll(PDO::FETCH_ASSOC);
 $permissions = $pdo->query("SELECT * FROM tbl_permissions ORDER BY permission_name ASC")->fetchAll(PDO::FETCH_ASSOC);

 $selected_role_id = $_GET['role_id'] ?? 0;
 $current_perms = [];

if ($selected_role_id > 0) {
    $stmtCurr = $pdo->prepare("SELECT permission_id, can FROM tbl_role_permissions WHERE role_id = ?");
    $stmtCurr->execute([$selected_role_id]);
    while ($row = $stmtCurr->fetch(PDO::FETCH_ASSOC)) {
        $current_perms[$row['permission_id']] = $row['can'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../images/web_icon.png" type="image/x-icon" />
    <title>Advanced Access Management | LAMD</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/admin_role_permissions.css"> 
</head>

<body>

    <!-- SIDEBAR -->
    <section id="sidebar">
        <a href="#" class="brand">
            <img src="../images/web_icon.png" alt="LAND ASSET MANAGEMENT"
                style="height: 40px; width: auto; object-fit: contain; margin-right: 10px;">
            <span class="text" style="display: grid;">LAND ASSET MANAGEMENT DEPARTMENT</span>
        </a>
        <ul class="side-menu top">
            <li><a href="admin_dashboard.php"><i class='bx bxs-dashboard bx-sm'></i><span class="text">Dashboard</span></a></li>
            <li><a href="admin_users.php"><i class='bx bxs-group bx-sm'></i><span class="text">Manage Users</span></a></li>
            <li class="active"><a href="admin_role_permissions.php"><i class='bx bxs-lock-alt bx-sm'></i><span class="text">Roles & Access</span></a></li>
        </ul>
    </section>

    <!-- CONTENT -->
    <section id="content">
        <!-- NAVBAR -->
        <nav>
            <i class='bx bx-menu bx-sm'></i>
            <form action="#"></form>
            <a href="#" class="profile" id="profileIcon">
                <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTtWEpbWZaMRKSthRD1G2AatWLDQeHG37Bw_w&s" alt="Profile">
            </a>
            <div class="profile-menu" id="profileMenu">
                <ul>
                    <li onclick="window.location.href='actions/logout.php';" style="cursor:pointer;">Log Out</li>
                </ul>
            </div>
        </nav>

        <!-- MAIN -->
        <main>
            <div class="page-header">
                <h1>Access Control Center</h1>
                <p>Manage roles, permissions, and module visibility dynamically.</p>
            </div>

            <!-- Success Modal -->
            <div id="successModal" class="modal-overlay <?php echo $show_success_modal ? 'active' : ''; ?>">
                <div class="modal-content">
                    <div class="modal-icon success"><i class='bx bx-check'></i></div>
                    <h2 class="modal-title">Success!</h2>
                    <p class="modal-message"><?php echo $message; ?></p>
                    <button class="btn-primary" onclick="closeSuccessModal()">Continue</button>
                </div>
            </div>

            <!-- Delete Role Confirmation Modal -->
            <div id="deleteRoleModal" class="modal-overlay">
                <div class="modal-content">
                    <div class="modal-icon danger"><i class='bx bx-trash'></i></div>
                    <h2 class="modal-title">Confirm Deletion</h2>
                    <p class="modal-message">Are you sure you want to delete this role? All permissions for this role will be lost.</p>
                    <div style="display: flex; gap: 15px; justify-content: center; margin-top: 10px;">
                        <button class="btn-primary" style="background:#64748b;" onclick="closeDeleteRoleModal()">Cancel</button>
                        <form method="POST" style="margin:0; padding:0;">
                            <input type="hidden" name="delete_role_id" id="delete_role_id_form">
                            <button type="submit" name="delete_role" class="btn-primary btn-delete-action">
                                <i class='bx bx-trash'></i> Delete
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Delete Module Confirmation Modal -->
            <div id="deleteConfirmModal" class="modal-overlay">
                <div class="modal-content">
                    <div class="modal-icon danger"><i class='bx bx-trash'></i></div>
                    <h2 class="modal-title">Confirm Deletion</h2>
                    <p class="modal-message">Are you sure you want to delete this module?</p>
                    <input type="hidden" id="delete_perm_id_hidden">
                    <div style="display: flex; gap: 15px; justify-content: center; margin-top: 10px;">
                        <button class="btn-primary" style="background:#64748b;" onclick="closeDeleteModal()">Cancel</button>
                        <form method="POST" style="margin:0; padding:0;">
                            <input type="hidden" name="delete_perm_id" id="delete_perm_id_form">
                            <button type="submit" name="delete_module" class="btn-primary btn-delete-action">
                                <i class='bx bx-trash'></i> Delete
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Icon Picker Modal -->
            <div id="iconPickerModal" class="modal-overlay">
                <div class="modal-content icon-picker-modal">
                    <h3 style="margin-bottom: 20px;">Select an Icon</h3>
                    <input type="text" id="iconSearchInput" class="form-input icon-search" placeholder="Search icons...">
                    <div id="iconGridContainer" class="icon-grid"></div>
                    <div style="margin-top: 20px; display:flex; gap:10px; justify-content:center;">
                        <button class="btn-primary" style="background:#64748b;" onclick="closeIconPicker()">Cancel</button>
                        <button class="btn-primary" id="confirmIconBtn">Select Icon</button>
                    </div>
                </div>
            </div>

            <!-- SECTION 1: CONFIGURE PERMISSIONS (Select Role + Toggles) -->
            <div class="card">
                <div class="role-selector-header">
                    <div>
                        <h3 style="margin:0; color:#1e293b;">Configure Access</h3>
                        <p style="font-size:0.85rem; color:#64748b; margin-top:5px;">Select a role to modify its module access</p>
                    </div>
                    <select id="role_select" class="role-select-modern" onchange="window.location.href='admin_role_permissions.php?role_id=' + this.value">
                        <option value="">-- Select a Role to Configure --</option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?php echo $role['role_id']; ?>" <?php echo ($selected_role_id == $role['role_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($role['role_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($selected_role_id > 0): ?>
                    <form method="POST" action="admin_role_permissions.php?role_id=<?php echo $selected_role_id; ?>">
                        <input type="hidden" name="role_id" value="<?php echo $selected_role_id; ?>">

                        <div class="permissions-grid">
                            <?php foreach ($permissions as $perm): 
                                $current_val = $current_perms[$perm['permission_id']] ?? 'none';
                                $has_view = ($current_val === 'view' || $current_val === 'edit');
                                $has_edit = ($current_val === 'edit');
                                $icon_class = $perm['permission_icon'] ?? 'bx-file';
                                
                                $card_class = '';
                                if($has_edit) $card_class = 'has-edit';
                                elseif($has_view) $card_class = 'has-view';
                            ?>
                                <div class="perm-card <?php echo $card_class; ?>">
                                    <div class="perm-info">
                                        <div class="perm-icon-box">
                                            <i class='bx <?php echo htmlspecialchars($icon_class); ?>'></i>
                                        </div>
                                        <div class="perm-details">
                                            <h4><?php echo htmlspecialchars($perm['permission_name']); ?></h4>
                                            <p title="<?php echo htmlspecialchars($perm['permission_link']); ?>"><?php echo htmlspecialchars($perm['permission_link']); ?></p>
                                        </div>
                                    </div>

                                    <div class="toggle-container">
                                        <div class="toggle-row">
                                            <span class="toggle-label">View</span>
                                            <label class="switch">
                                                <input type="checkbox" 
                                                    data-perm-id="<?php echo $perm['permission_id']; ?>" 
                                                    class="toggle-view" 
                                                    onchange="updatePermission(this, 'view')"
                                                    <?php echo $has_view ? 'checked' : ''; ?>>
                                                <span class="slider"></span>
                                            </label>
                                        </div>
                                        
                                        <div class="toggle-row" style="<?php echo !$has_view ? 'opacity:0.5; pointer-events:none;' : ''; ?>">
                                            <span class="toggle-label">Edit</span>
                                            <label class="switch">
                                                <input type="checkbox" 
                                                    data-perm-id="<?php echo $perm['permission_id']; ?>" 
                                                    class="toggle-edit" 
                                                    onchange="updatePermission(this, 'edit')"
                                                    <?php echo $has_edit ? 'checked' : ''; ?>>
                                                <span class="slider"></span>
                                            </label>
                                        </div>
                                        
                                        <input type="hidden" name="permissions[<?php echo $perm['permission_id']; ?>]" 
                                            id="final_val_<?php echo $perm['permission_id']; ?>" 
                                            value="<?php echo $current_val; ?>">
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div style="margin-top: 30px; text-align: right; border-top: 1px solid #f1f5f9; padding-top: 20px;">
                            <button type="submit" name="save_permissions" id="save_config_btn" class="btn-primary" style="padding: 14px 40px;" disabled>
                                <i class='bx bx-save'></i> Save Configuration
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <div style="text-align: center; padding: 60px 20px; color: #94a3b8;">
                        <i class='bx bx-select-arrows' style="font-size: 48px; display:block; margin-bottom:15px;"></i>
                        <h3 style="margin:0; color:#64748b;">Ready to Configure</h3>
                        <p style="font-size:0.9rem;">Select a role from the dropdown above to view and edit permissions.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- SECTION 2: ROLE MANAGEMENT (Create, Rename, Delete) -->
            <div class="card">
                <div class="section-header" style="margin-bottom: 25px;">
                    <h3 style="margin:0;"><i class='bx bx-user-detail' style="position:relative; top:2px;"></i> Role Management</h3>
                    <p style="font-size:0.85rem; color:#64748b; margin-top:5px;">Create, Rename, or Delete system roles</p>
                </div>

                <div class="action-grid" style="margin-bottom: 0;">
                    
                    <!-- Create Role -->
                    <div class="add-tab-section">
                        <h4><i class='bx bx-plus-circle'></i> Create New Role</h4>
                        <form method="POST" class="add-tab-form">
                            <div>
                                <div class="form-group">
                                    <label>Role Name</label>
                                    <input type="text" name="new_role_name" class="form-input" placeholder="e.g., Auditor" required>
                                </div>
                            </div>
                            <button type="submit" name="create_role" class="btn-primary">
                                <i class='bx bx-plus'></i> Create Role
                            </button>
                        </form>
                    </div>

                    <!-- Rename Role -->
                    <div class="edit-tab-section">
                        <h4><i class='bx bx-rename'></i> Rename Role</h4>
                        <form method="POST" class="add-tab-form">
                            <div>
                                <div class="form-group">
                                    <label>Select Role</label>
                                    <select class="form-input" id="rename_select" onchange="fillRenameInput(this)" required>
                                        <option value="">-- Select --</option>
                                        <?php foreach ($roles as $role): ?>
                                            <option value="<?php echo $role['role_id']; ?>" data-name="<?php echo htmlspecialchars($role['role_name']); ?>">
                                                <?php echo htmlspecialchars($role['role_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <input type="hidden" name="rename_role_id" id="rename_role_id">
                                <div class="form-group">
                                    <label>New Name</label>
                                    <input type="text" name="rename_role_name" id="rename_role_input" class="form-input" placeholder="Enter new name..." required>
                                </div>
                            </div>
                            <button type="submit" name="rename_role" class="btn-primary btn-edit-action">
                                <i class='bx bx-pencil'></i> Rename
                            </button>
                        </form>
                    </div>

                    <!-- Delete Role -->
                    <div class="delete-tab-section">
                        <h4><i class='bx bx-trash-alt'></i> Delete Role</h4>
                        <div class="add-tab-form">
                            <div>
                                <div class="form-group">
                                    <label>Select Role</label>
                                    <select class="form-input" id="delete_role_select" onchange="confirmDeleteRole(this.value)" required>
                                        <option value="">-- Select --</option>
                                        <?php foreach ($roles as $role): ?>
                                            <option value="<?php echo $role['role_id']; ?>" <?php echo ($role['role_id'] == 1) ? 'disabled' : ''; ?>>
                                                <?php echo htmlspecialchars($role['role_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div style="margin-top: 20px; padding: 15px; background: #fef2f2; border-radius: 10px; color: #991b1b; font-size: 0.85rem; border: 1px solid #fecaca;">
                                    <i class='bx bx-error-alt'></i> Warning: Deleting a role is permanent.
                                </div>
                            </div>
                            <button type="button" class="btn-primary btn-delete-action" style="visibility: hidden;">
                                <i class='bx bx-trash'></i> Delete
                            </button>
                        </div>
                    </div>
                </div>
            </div>

                        <!-- SECTION 3: MODULE MANAGEMENT -->
            <div class="card" style="margin-top: 24px;">
                <div class="section-header" style="margin-bottom: 25px;">
                    <h3 style="margin:0;"><i class='bx bx-extension' style="position:relative; top:2px;"></i> Module Management</h3>
                    <p style="font-size:0.85rem; color:#64748b; margin-top:5px;">Manage system modules and pages</p>
                </div>

                <div class="action-grid" style="margin-bottom: 0;">
                    <!-- Add New Module -->
                    <div class="add-tab-section">
                        <h4><i class='bx bx-plus-circle'></i> Add New Module</h4>
                        <form method="POST" action="admin_role_permissions.php" class="add-tab-form">
                            <div>
                                <div class="form-group">
                                    <label>Module Name</label>
                                    <input type="text" name="tab_name" class="form-input" placeholder="e.g., Reports" required>
                                </div>
                                <div class="form-group">
                                    <label>Link / URL</label>
                                    <input type="text" name="tab_link" class="form-input" placeholder="e.g., user_reports.php" required>
                                </div>
                                <div class="form-group">
                                    <label>Icon</label>
                                    <input type="hidden" name="selected_icon" id="selectedIconInput" required>
                                    <div class="icon-picker-group" onclick="openIconPicker('add')">
                                        <input type="text" id="iconDisplayInput" class="form-input" placeholder="Click to select icon" readonly required>
                                        <div class="icon-preview-box"><i id="addFormIconPreview" class='bx bx-help-circle'></i></div>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" name="add_new_tab" class="btn-primary">
                                <i class='bx bx-plus'></i> Add Module
                            </button>
                        </form>
                    </div>

                    <!-- Edit Module -->
                    <div class="edit-tab-section">
                        <h4><i class='bx bx-edit-alt'></i> Edit Module</h4>
                        <form method="POST" action="admin_role_permissions.php" class="add-tab-form">
                            <div>
                                <div class="form-group">
                                    <label>Select Module</label>
                                    <select class="form-input" id="edit_module_select" onchange="loadModuleData(this.value)" required>
                                        <option value="">-- Select to Edit --</option>
                                        <?php foreach ($permissions as $perm): ?>
                                            <option value="<?php echo $perm['permission_id']; ?>" 
                                                data-name="<?php echo htmlspecialchars($perm['permission_name']); ?>"
                                                data-link="<?php echo htmlspecialchars($perm['permission_link']); ?>"
                                                data-icon="<?php echo htmlspecialchars($perm['permission_icon']); ?>">
                                                <?php echo htmlspecialchars($perm['permission_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <input type="hidden" name="edit_perm_id" id="edit_perm_id">
                                <div class="form-group">
                                    <label>Module Name</label>
                                    <input type="text" name="edit_tab_name" id="edit_tab_name" class="form-input" required>
                                </div>
                                <div class="form-group">
                                    <label>Link / URL</label>
                                    <input type="text" name="edit_tab_link" id="edit_tab_link" class="form-input" required>
                                </div>
                                <div class="form-group">
                                    <label>Icon</label>
                                    <input type="hidden" name="edit_selected_icon" id="editSelectedIconInput" required>
                                    <div class="icon-picker-group" onclick="openIconPicker('edit')">
                                        <input type="text" id="editIconDisplayInput" class="form-input" placeholder="Select module first" readonly required>
                                        <div class="icon-preview-box"><i id="editFormIconPreview" class='bx bx-help-circle'></i></div>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" name="edit_module" class="btn-primary btn-edit-action">
                                <i class='bx bx-save'></i> Update Module
                            </button>
                        </form>
                    </div>

                    <!-- Delete Module -->
                    <div class="delete-tab-section">
                        <h4><i class='bx bx-trash-alt'></i> Delete Module</h4>
                        <div class="add-tab-form">
                            <div>
                                <div class="form-group">
                                    <label>Select Module</label>
                                    <select class="form-input" id="delete_module_select" onchange="confirmDelete(this.value, this.options[this.selectedIndex].text)" required>
                                        <option value="">-- Select to Delete --</option>
                                        <?php foreach ($permissions as $perm): ?>
                                            <option value="<?php echo $perm['permission_id']; ?>">
                                                <?php echo htmlspecialchars($perm['permission_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div style="margin-top: 20px; padding: 15px; background: #fef2f2; border-radius: 10px; color: #991b1b; font-size: 0.85rem; border: 1px solid #fecaca;">
                                    <i class='bx bx-error-alt' style="top: 3px; position: relative;"></i> 
                                    Warning: Deleting a module will remove it from all roles.
                                </div>
                            </div>
                            <button type="button" class="btn-primary btn-delete-action" style="visibility: hidden;">
                                <i class='bx bx-trash'></i> Delete Module
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </section>

    <script>
        // ==========================================
        // 1. PROFILE MENU TOGGLE
        // ==========================================
        const profileIcon = document.getElementById('profileIcon');
        const profileMenu = document.getElementById('profileMenu');

        if (profileIcon && profileMenu) {
            profileIcon.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                profileMenu.classList.toggle('show');
            });
            window.addEventListener('click', (e) => {
                if (!profileMenu.contains(e.target) && !profileIcon.contains(e.target)) {
                    profileMenu.classList.remove('show');
                }
            });
        }

        // ==========================================
        // 2. PERMISSION TOGGLE LOGIC
        // ==========================================
        function updatePermission(checkbox, type) {
            const permId = checkbox.dataset.permId;
            const hiddenInput = document.getElementById('final_val_' + permId);
            const card = checkbox.closest('.perm-card');
            const editRow = card.querySelector('.toggle-edit').parentElement;
            const editCheck = card.querySelector('.toggle-edit');
            
            if (type === 'view') {
                if (!checkbox.checked) {
                    editCheck.checked = false;
                    hiddenInput.value = 'none';
                    editRow.style.opacity = '0.5';
                    editRow.style.pointerEvents = 'none';
                    card.classList.remove('has-view', 'has-edit');
                } else {
                    hiddenInput.value = 'view';
                    editRow.style.opacity = '1';
                    editRow.style.pointerEvents = 'auto';
                    card.classList.add('has-view');
                }
            } else if (type === 'edit') {
                if (checkbox.checked) {
                    hiddenInput.value = 'edit';
                    card.classList.add('has-edit');
                } else {
                    hiddenInput.value = 'view';
                    card.classList.remove('has-edit');
                }
            }
            checkForUnsavedChanges();
        }

        // ==========================================
        // 3. ROLE MANAGEMENT LOGIC (NEW FIX)
        // ==========================================
        
        // A. Fill Rename Inputs
        function fillRenameInput(selectElement) {
            const selectedOption = selectElement.options[selectElement.selectedIndex];
            const roleId = selectedOption.value;
            const roleName = selectedOption.dataset.name;

            if (roleId) {
                document.getElementById('rename_role_id').value = roleId;
                document.getElementById('rename_role_input').value = roleName;
            } else {
                document.getElementById('rename_role_id').value = '';
                document.getElementById('rename_role_input').value = '';
            }
        }

        // B. Delete Role Modal
        function confirmDeleteRole(roleId) {
            if (!roleId) return;
            
            const modal = document.getElementById('deleteRoleModal');
            document.getElementById('delete_role_id_form').value = roleId;
            
            // Optional: Get role name for the message
            const select = document.getElementById('delete_role_select');
            const roleName = select.options[select.selectedIndex].text;
            
            modal.querySelector('.modal-message').innerText = `Are you sure you want to delete the role "${roleName}"? All permissions for this role will be lost.`;
            
            modal.classList.add('active');
        }

        function closeDeleteRoleModal() {
            document.getElementById('deleteRoleModal').classList.remove('active');
            document.getElementById('delete_role_select').value = ""; // Reset dropdown
        }

        // ==========================================
        // 4. MODULE MANAGEMENT LOGIC
        // ==========================================
        function loadModuleData(permId) {
            if (!permId) {
                document.getElementById('edit_perm_id').value = '';
                document.getElementById('edit_tab_name').value = '';
                document.getElementById('edit_tab_link').value = '';
                document.getElementById('editSelectedIconInput').value = '';
                document.getElementById('editIconDisplayInput').value = '';
                document.getElementById('editFormIconPreview').className = 'bx bx-help-circle';
                return;
            }

            const select = document.getElementById('edit_module_select');
            const option = select.options[select.selectedIndex];

            document.getElementById('edit_perm_id').value = permId;
            document.getElementById('edit_tab_name').value = option.dataset.name;
            document.getElementById('edit_tab_link').value = option.dataset.link;
            
            const iconClass = option.dataset.icon;
            document.getElementById('editSelectedIconInput').value = iconClass;
            document.getElementById('editIconDisplayInput').value = iconClass;
            document.getElementById('editFormIconPreview').className = `bx ${iconClass}`;
        }

        function confirmDelete(permId, permName) {
            if (!permId) return;

            const modal = document.getElementById('deleteConfirmModal');
            document.getElementById('delete_perm_id_form').value = permId;
            // Update modal text to show module name
            modal.querySelector('.modal-message').innerText = `Are you sure you want to delete "${permName}"? This action cannot be undone.`;
            
            modal.classList.add('active');
        }

        function closeDeleteModal() {
            document.getElementById('deleteConfirmModal').classList.remove('active');
            document.getElementById('delete_module_select').value = ""; // Reset dropdown
        }

        // ==========================================
        // 5. ICON PICKER LOGIC
        // ==========================================
        const iconList = [
            'bx-home', 'bx-home-circle', 'bx-user', 'bx-user-circle', 'bx-group', 'bx-file', 'bx-file-blank', 
            'bx-folder', 'bx-calendar', 'bx-calendar-event', 'bx-chart', 'bx-line-chart', 'bx-pie-chart', 
            'bx-map', 'bx-map-pin', 'bx-cog', 'bx-lock', 'bx-key', 'bx-message', 'bx-notification', 
            'bx-search', 'bx-bookmark', 'bx-image', 'bx-camera', 'bx-video', 'bx-phone', 'bx-envelope', 
            'bx-printer', 'bx-cloud', 'bx-download', 'bx-upload', 'bx-trash', 'bx-edit', 'bx-check', 
            'bx-x', 'bx-plus', 'bx-minus', 'bx-help', 'bx-info', 'bx-landscape', 'bx-building', 'bxs-dashboard', 
            'bxs-user', 'bxs-group', 'bxs-file', 'bxs-folder', 'bxs-calendar', 'bxs-chart', 'bxs-map', 
            'bxs-cog', 'bxs-lock', 'bxs-key', 'bxs-message', 'bxs-notification', 'bxs-bookmark', 'bxs-image', 
            'bxs-camera', 'bxs-video', 'bxs-phone', 'bxs-envelope', 'bxs-printer', 'bxs-cloud', 'bxs-trash', 
            'bxs-edit', 'bxs-check', 'bxs-x', 'bxs-plus', 'bxs-landscape', 'bxs-building', 'bxs-landmark'
        ];

        let selectedIconForForm = '';
        let currentIconTarget = 'add';

        function openIconPicker(target) {
            currentIconTarget = target;
            const modal = document.getElementById('iconPickerModal');
            const grid = document.getElementById('iconGridContainer');
            grid.innerHTML = '';
            
            iconList.forEach(icon => {
                const div = document.createElement('div');
                div.className = 'icon-item';
                div.innerHTML = `<i class='bx ${icon}'></i>`;
                div.onclick = () => selectTempIcon(icon, div);
                grid.appendChild(div);
            });
            
            modal.classList.add('active');
        }

        function selectTempIcon(icon, element) {
            document.querySelectorAll('.icon-item').forEach(el => el.classList.remove('selected'));
            element.classList.add('selected');
            selectedIconForForm = icon;
        }

        document.getElementById('confirmIconBtn').onclick = () => {
            if(selectedIconForForm) {
                if (currentIconTarget === 'add') {
                    document.getElementById('selectedIconInput').value = selectedIconForForm;
                    document.getElementById('iconDisplayInput').value = selectedIconForForm;
                    document.getElementById('addFormIconPreview').className = `bx ${selectedIconForForm}`;
                } else {
                    document.getElementById('editSelectedIconInput').value = selectedIconForForm;
                    document.getElementById('editIconDisplayInput').value = selectedIconForForm;
                    document.getElementById('editFormIconPreview').className = `bx ${selectedIconForForm}`;
                }
                closeIconPicker();
            }
        };

        function closeIconPicker() {
            document.getElementById('iconPickerModal').classList.remove('active');
        }

        // Icon Search Filter
        document.getElementById('iconSearchInput').addEventListener('input', (e) => {
            const term = e.target.value.toLowerCase();
            const items = document.querySelectorAll('.icon-item');
            items.forEach(item => {
                const iconClass = item.querySelector('i').className;
                if(iconClass.includes(term)) item.style.display = 'flex';
                else item.style.display = 'none';
            });
        });

        function closeSuccessModal() {
            document.getElementById('successModal').classList.remove('active');
        }
        
        // Sidebar Toggle
        const menuBar = document.querySelector('#content nav .bx.bx-menu');
        const sidebar = document.getElementById('sidebar');
        if (menuBar) menuBar.addEventListener('click', () => sidebar.classList.toggle('hide'));











            // ==========================================
    // 6. SAVE BUTTON STATE LOGIC
    // ==========================================
    
    // Store the initial state of permissions on page load
    let initialPermissions = {};

    function saveInitialPermissionsState() {
        // Only run if we are on the permission configuration screen
        const cards = document.querySelectorAll('.perm-card');
        if (cards.length === 0) return;

        cards.forEach(card => {
            const viewInput = card.querySelector('.toggle-view');
            const editInput = card.querySelector('.toggle-edit');
            const permId = viewInput.dataset.permId;
            
            let state = 'none';
            if (viewInput.checked) state = 'view';
            if (editInput.checked) state = 'edit';
            
            initialPermissions[permId] = state;
        });

        // Check immediately (button should be disabled on load)
        checkForUnsavedChanges();
    } 

    // Function to check if current state differs from initial state
    function checkForUnsavedChanges() {
        const saveBtn = document.getElementById('save_config_btn');
        if (!saveBtn) return; // Guard clause if element doesn't exist

        let hasChanges = false;
        const cards = document.querySelectorAll('.perm-card');

        cards.forEach(card => {
            const viewInput = card.querySelector('.toggle-view');
            const editInput = card.querySelector('.toggle-edit');
            const permId = viewInput.dataset.permId;
            
            let currentState = 'none';
            if (viewInput.checked) currentState = 'view';
            if (editInput.checked) currentState = 'edit';

            // Compare current state with what we saved on load
            if (initialPermissions[permId] !== currentState) {
                hasChanges = true;
            }
        });

        // Enable or Disable based on comparison
        saveBtn.disabled = !hasChanges;
    }

    // Initialize state when DOM is ready
    document.addEventListener('DOMContentLoaded', saveInitialPermissionsState);
    </script>
</body>
</html>