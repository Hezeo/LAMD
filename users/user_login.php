<?php
// user_login.php
session_start();

// =============================================================
// 1. PREVENT CACHING (Forces browser to re-run this script on "Back")
// =============================================================
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// =============================================================
// 2. SESSION CHECK (Redirect if already logged in)
// =============================================================
require_once '../config.php'; 

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header("Location: user_dashboard.php");
    exit();
}

// =============================================================
// --- AUTOMATIC STATUS UPDATE LOGIC (GLOBAL TRIGGER) ---
// =============================================================
try {
    // 1. Update Lease Status (Original Logic)
    $sql_update_status = "
        UPDATE tbl_main 
        SET lease_status = 
            CASE 
                WHEN expiry_date < CURDATE() THEN 'Expired'
                WHEN expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 2 YEAR) THEN 'Near Expiration'
                ELSE 'Active'
            END
        WHERE expiry_date IS NOT NULL 
          AND expiry_date != '0000-00-00'
    ";
    
    $stmt_update = $pdo->prepare($sql_update_status);
    $stmt_update->execute();

    // 2. Update Contract Status (Corrected Logic)
    // Calculate current Fiscal Year Start (May 1st)
    // If month is May (5) or later, FY started this year. Otherwise, last year.
    $currentMonth = date('n'); 
    $currentYear  = date('Y');

    if ($currentMonth >= 5) {
        $fyStartYear = $currentYear;
    } else {
        $fyStartYear = $currentYear - 1;
    }

    $fy_start_date = $fyStartYear . '-05-01';

    // LOGIC: Only change 'NEWLAND' to 'EXISTING' if the start_date is BEFORE the current FY start.
    // This prevents changing records that are already EXISTING, and prevents setting anything to NEWLAND automatically.
    $sql_update_contract = "
        UPDATE tbl_main 
        SET contract_status = 'EXISTING'
        WHERE contract_status = 'NEWLAND'
          AND start_date IS NOT NULL 
          AND start_date != '0000-00-00'
          AND start_date < :fy_start
    ";

    $stmt_contract = $pdo->prepare($sql_update_contract);
    $stmt_contract->execute([
        ':fy_start' => $fy_start_date
    ]);

} catch (PDOException $e) {
    // Optional: Log error if needed
}

// Get error message from session (if any)
 $error_message = isset($_SESSION['login_error']) ? $_SESSION['login_error'] : '';

// Clear session messages after displaying
unset($_SESSION['login_error']);
//MADE BY RYAN & JESS
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="icon" href="../images/web_icon.png" type="image/x-icon" />
    <title>Land Asset Management Department | Del Monte</title>

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;500;600&display=swap" rel="stylesheet">
    
    <!-- Main CSS -->
    <link href="css/user_login.css" rel="stylesheet">

    <!-- ICONS -->
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>

<body>

    <!-- Del Monte Themed Background Accents -->
    <div class="background"></div>

    <!-- NEW SPLIT CONTAINER -->
    <div class="main-container">
        
        <!-- LEFT SIDE: BRANDING (65%) -->
        <div class="left-panel">

                <div style="text-align:start; margin-bottom:8px;">
                    <img src="../images/web_icon.png" alt="Del Monte Logo"
                        style="width:120px; filter: drop-shadow(0 4px 8px rgba(0,0,0,0.3));">
                </div>
            <!-- Greeting moved here -->
            <h4 id="greeting" class="background-greeting"></h4>
            
            <h1 class="brand-title">
                <span class="line-1">Land Asset</span>
                <span class="line-2">Management Department</span>
            </h1>
                
                <span class="line-3"><p><em>Land Holdings Information System</em></p></span>
        </div>

        <!-- RIGHT SIDE: LOGIN FORM (35%) -->
        <div class="right-panel">
            <form method="POST" action="actions/login.php" id="login_container">

                <div style="text-align:center; margin-bottom:20px;">
                    <h1 class="login-label">User Login</h1>
                </div>

                <label for="user_id">User ID</label>
                <input type="text" id="user_id" name="user_id" placeholder="" autocomplete="user_id" required>

                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="" autocomplete="current-password" required>

                <!-- Error Message -->
                <?php if (!empty($error_message)): ?>
                    <div class="error-message">
                        <i></i> <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <button type="submit" name="login">Log In</button>
            </form>
        </div>

    </div>
    <!-- END SPLIT CONTAINER -->

    <!-- JAVASCRIPT FILES -->
    <script src="js/user_login.js"></script>

    <!-- LOADER SECTION -->
    <div class="loader" id="loadingScreen">
        <!-- ... Keep your existing Pineapple Loader HTML here ... -->
         <div class="pineapple">
            <div class="crown">
                <div class="leaf leaf--center"></div>
                <div class="leaf leaf--l1"></div>
                <div class="leaf leaf--l2"></div>
                <div class="leaf leaf--l3"></div>
                <div class="leaf leaf--r1"></div>
                <div class="leaf leaf--r2"></div>
                <div class="leaf leaf--r3"></div>
            </div>
            <div class="body"></div>
            <div class="legs">
                <div class="leg"></div>
                <div class="leg"></div>
            </div>
            <div class="arm arm--left"><div class="hand"></div></div>
            <div class="arm arm--right"><div class="hand"></div></div>
            <div class="teacup">
                <div class="steam"></div>
                <div class="steam"></div>
                <div class="steam"></div>
                <div class="cup-body">
                    <div class="handle"></div>
                </div>
            </div>
            <div class="face">
                <div class="eye eye--left"></div>
                <div class="eye eye--right"></div>
                <div class="cheek cheek--left"></div>
                <div class="cheek cheek--right"></div>
                <div class="mouth"></div>
            </div>
            <div class="shadow"></div>
        </div>
        <div class="dots">
            <div class="dot"></div>
            <div class="dot"></div>
            <div class="dot"></div>
        </div>
    </div>

    <!-- TRIGGER SCRIPT -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const loginForm = document.querySelector('form'); 
            const loader = document.getElementById('loadingScreen');

            window.addEventListener('pageshow', function(event) {
                if (event.persisted) {
                    if (loader) { loader.style.display = 'none'; }
                    window.location.reload();
                }
            });

            if (loginForm) {
                loginForm.addEventListener('submit', function() {
                    if(loader) loader.style.display = 'flex';
                });
            }
        });
    </script>

</body>
</html>