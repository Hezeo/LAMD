<?php
session_start();
require_once '../config.php';

// 1. Security Check
// if (!isset($_SESSION['admin_logged_in'])) {
//     header("Location: admin_login.php");
//     exit();
// }

// 2. Fetch Users with Role Name
try {
    $stmt = $pdo->query("SELECT u.*, r.role_name 
                         FROM tbl_users u 
                         LEFT JOIN tbl_roles r ON u.role_id = r.role_id 
                         ORDER BY u.date_added DESC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching users: " . $e->getMessage());
}

// 3. Fetch All Roles for Dropdowns
try {
    $roleStmt = $pdo->query("SELECT * FROM tbl_roles ORDER BY role_name ASC");
    $roles = $roleStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching roles: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" />
    <link rel="icon" href="../images/web_icon.png" type="image/x-icon">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="css/admin_users.css">
    <title>Manage Users | LAMD</title>
</head>

<body>
    <section id="sidebar">
        <a href="#" class="brand">
            <img src="../images/web_icon.png" alt="LAND ASSET MANAGEMENT"
                style="height: 40px; width: auto; object-fit: contain; margin-right: 10px;">
            <span class="text" style="display: grid;">LAND ASSET MANAGEMENT DEPARTMENT</span>
        </a>
        <ul class="side-menu top">
            <li>
                <a href="admin_dashboard.php">
                    <i class='bx bxs-dashboard bx-sm'></i>
                    <span class="text">Dashboard</span>
                </a>
            </li>
            <li class="active">
                <a href="admin_users.php">
                    <i class='bx bxs-group bx-sm'></i>
                    <span class="text">Manage Users</span>
                </a>
            </li>
            <li>
                <a href="admin_role_permissions.php">
                    <i class='bx bxs-lock-alt bx-sm'></i>
                    <span class="text">Roles & Access</span>
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
        <!-- NAVBAR -->

        <!-- MAIN -->
        <main>
            <div class="head-title">
                <div class="left">
                    <h1>User Management</h1>
                </div>
                <a class="btn-download" id="addUserBtn">
                    <i class='bx bx-plus bx-fade-down-hover'></i>
                    <span class="text">Add User</span>
                </a>
            </div>

            <div class="table-data">
                <div class="order">
                    <div class="head">
                        <h3>Recently Added</h3>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>User ID</th>
                                <th style="width: 50px;"></th> 
                                <th>Name</th>
                                <th>Position</th> <!-- ADDED POSITION COLUMN -->
                                <th>Department</th>
                                <th>Role</th>
                                <th>Contact</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="userTableBody">
                            <?php if ($users): ?>
                                <?php foreach ($users as $user): ?>
                                    <tr data-id="<?php echo $user['user_id']; ?>">
                                        
                                        <td><strong><?php echo htmlspecialchars($user['user_id']); ?></strong></td>
                                        <td>
                                            <?php 
                                            // Smart Image Logic (Matches user profile page)
                                            $default_img = "https://i.ibb.co/kVPtjmbK/default-profile-pic.png";
                                            $user_img_src = $default_img;
                                            
                                            if (!empty($user['profile_image'])) {
                                                if (filter_var($user['profile_image'], FILTER_VALIDATE_URL)) {
                                                    $user_img_src = htmlspecialchars($user['profile_image']);
                                                } else {
                                                    $user_img_src = '../users/images/' . htmlspecialchars($user['profile_image']);
                                                }
                                            }
                                            ?>
                                            <img src="<?php echo $user_img_src; ?>" alt="Profile" />
                                        </td>
                                        <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($user['position']); ?></td> <!-- ADDED POSITION DATA -->
                                        <td><?php echo htmlspecialchars($user['department']); ?></td>
                                        <td><?php echo htmlspecialchars($user['role_name'] ?? 'Unassigned'); ?></td>
                                        <td>
                                            <span class="status-email"><?php echo htmlspecialchars($user['email']); ?></span><br>
                                            <small><?php echo htmlspecialchars($user['phone_number']); ?></small>
                                        </td>
                                        <td>
                                            <button class="btn btn-edit"
                                                data-id="<?php echo $user['user_id']; ?>"
                                                data-fname="<?php echo htmlspecialchars($user['first_name']); ?>"
                                                data-mi="<?php echo htmlspecialchars($user['middle_initial']); ?>"
                                                data-lname="<?php echo htmlspecialchars($user['last_name']); ?>"
                                                data-dept="<?php echo htmlspecialchars($user['department']); ?>"
                                                data-pos="<?php echo htmlspecialchars($user['position']); ?>"
                                                data-email="<?php echo htmlspecialchars($user['email']); ?>"
                                                data-phone="<?php echo htmlspecialchars($user['phone_number']); ?>"
                                                data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                                data-role-id="<?php echo $user['role_id']; ?>"
                                                title="Edit">
                                                <i class="fas fa-pencil-alt"></i>
                                            </button>
                                            <button class="btn btn-delete" data-id="<?php echo $user['user_id']; ?>" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <!-- Updated COLSPAN FROM 7 TO 8 -->
                                <tr>
                                    <td colspan="8" style="text-align:center;">No users found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Add User Modal -->
            <div id="addUserModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <i class='bx bxs-user-plus'></i>
                        <h2>New Staff Registration</h2>
                        <span class="close-btn">&times;</span>
                    </div>
                    <form id="addUserForm">
                        <!-- ADDED AUTO-GENERATE NOTE HERE -->
                        <div class="full-width" style="text-align: center; margin-bottom: 10px; color: #6c757d;">
                            <small><i class="fas fa-info-circle"></i> Note: User ID is automatically generated upon registration.</small>
                        </div>

                        <div><label>First Name</label><input type="text" name="first_name" placeholder="e.g.,Juan" required></div>
                        <div><label>Middle Initial</label><input type="text" name="middle_initial" placeholder="e.g.,M." maxlength="2"></div>
                        <div class="full-width"><label>Last Name</label><input type="text" name="last_name" placeholder="e.g.,Dela Cruz" required></div>
                        <div><label>Department</label><input type="text" name="department" placeholder="e.g.,Land Matters" required></div>
                        <div><label>Position</label><input type="text" name="position" placeholder="e.g.,Clerk" required></div>
                        <div><label>Email Address</label><input type="email" name="email" placeholder="e.g.,juan@delmonte.com" required></div>
                        <div><label>Phone Number</label><input type="text" name="phone_number" placeholder="e.g.,09*********" required></div>
                        <div><label>Username</label><input type="text" id="addUsername" name="username" required></div>
                        <div><label>Password</label>
                            <div class="password-wrapper">
                                <input type="password" id="addPassword" name="password" required>
                                <span id="toggleAddPassword" class="toggle-icon"><i class="fas fa-eye"></i></span>
                            </div>
                        </div>

                        <!-- Role Selection Only -->
                        <div class="full-width">
                            <label>Assign Role</label>
                            <select id="addRoleSelect" name="role_id" required>
                                <option value="">-- Select Role --</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo $role['role_id']; ?>">
                                        <?php echo htmlspecialchars($role['role_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="modal-footer full-width">
                            <button type="submit" class="btn-save">Register User</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Edit User Modal -->
            <div id="editUserModal">
                <div class="editModal-content">
                    <div class="edit-header">
                        <i class='bx bxs-edit'></i>
                        <h2>Edit Staff User</h2>
                        <button class="editClose-btn">&times;</button>
                    </div>
                    <form id="editUserForm">
                        <input type="hidden" id="editUserId" name="user_id">
                        <div><label>First Name</label><input type="text" id="editFirstName" name="first_name" required></div>
                        <div><label>Middle Initial</label><input type="text" id="editMiddleInitial" name="middle_initial" maxlength="2"></div>
                        <div class="full-width"><label>Last Name</label><input type="text" id="editLastName" name="last_name" required></div>
                        <div><label>Department</label><input type="text" id="editDepartment" name="department" required></div>
                        <div><label>Position</label><input type="text" id="editPosition" name="position" required></div>
                        <div><label>Email Address</label><input type="email" id="editEmail" name="email" required></div>
                        <div><label>Phone Number</label><input type="text" id="editPhone" name="phone_number" required></div>
                        <div><label>Username</label><input type="text" id="editUsername" name="username" required></div>
                        <div><label>Password</label>
                            <div class="password-wrapper">
                                <input type="password" id="editPassword" name="password">
                                <span id="toggleEditPassword" class="toggle-icon"><i class="fas fa-eye"></i></span>
                            </div>
                        </div>

                        <!-- Role Selection Only -->
                        <div class="full-width">
                            <label>Assign Role</label>
                            <select id="editRoleSelect" name="role_id" required>
                                <option value="">-- Select Role --</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo $role['role_id']; ?>">
                                        <?php echo htmlspecialchars($role['role_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="edit-footer full-width">
                            <button type="button" class="editbtn-cancel">Cancel</button>
                            <button type="submit" class="editbtn-save">Update User</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Delete Modal -->
            <div id="deleteModal" class="delmodal">
                <div class="delmodal-content">
                    <div class="del-icon"><i class='bx bxs-error-circle'></i></div>
                    <p>Remove User Account?</p>
                    <span class="del-subtitle">This action cannot be undone.</span>
                    <div class="delmodal-buttons">
                        <button id="confirmNo" class="delbtn-base delbtn-cancel">Cancel</button>
                        <button id="confirmYes" class="delbtn-base delbtn-confirm">Delete User</button>
                    </div>
                </div>
            </div>

        </main>
        <!-- MAIN -->
    </section>
    <!-- CONTENT -->

    <script src="js/admin_users.js"></script>
</body>

</html>