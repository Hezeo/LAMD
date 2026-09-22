<?php
// admin_login.php
session_start();

// =============================================================
// 1. PREVENT CACHING
// =============================================================
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// =============================================================
// 2. SESSION CHECK
// =============================================================
require_once '../config.php'; 

// Note: Adjust variable names if your admin session uses different names
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: admin_dashboard.php"); // Adjust redirect path as needed
    exit();
}

// Get error message from session (if any)
 $error_message = isset($_SESSION['admin_login_error']) ? $_SESSION['admin_login_error'] : '';
 $saved_username = isset($_SESSION['admin_login_username']) ? $_SESSION['admin_login_username'] : '';

// Clear session messages after displaying
unset($_SESSION['admin_login_error']);
unset($_SESSION['admin_login_username']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="icon" href="../images/web_icon.png" type="image/x-icon" />
    <title>Admin Panel | LAMD</title>

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;500;600&display=swap" rel="stylesheet">
    
    <!-- Main CSS -->
    <link href="css/admin_login.css" rel="stylesheet">

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
                    <h1 class="login-label">Admin Login</h1>
                </div>

                <label for="username">Username</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($saved_username); ?>" placeholder="" autocomplete="username" required>

                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="" autocomplete="current-password" required>

                <!-- Error Message -->
                <?php if (!empty($error_message)): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <button type="submit" name="login">Log In</button>
            </form>
        </div>

    </div>
    <!-- END SPLIT CONTAINER -->

    <!-- JAVASCRIPT FILES -->
    <script src="js/admin_login.js"></script>

    <!-- LOADER SECTION -->
    <div class="loader" id="loadingScreen">
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