<?php
// change_password.php
session_start();
require_once '../config.php';

// 1. SECURITY CHECK: Must be logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_id'])) {
    header("Location: user_login.php");
    exit();
}

// 2. FETCH USER INFO
 $user_id = $_SESSION['user_id'];
 $user_data = null;
 $error_message = "";
 $success_message = "";

try {
    $stmt = $pdo->prepare("SELECT * FROM tbl_users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("System Error.");
}

// 3. HANDLE FORM SUBMISSION
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    
    $old_pass     = $_POST['old_password'];
    $new_pass     = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    // A. Validation
    if (empty($old_pass) || empty($new_pass) || empty($confirm_pass)) {
        $error_message = "All fields are required.";
    } elseif ($new_pass !== $confirm_pass) {
        $error_message = "New passwords do not match.";
    } elseif (strlen($new_pass) < 6) {
        $error_message = "Password must be at least 6 characters.";
    } else {
        
        // B. Verify Old Password
        $password_match = false;
        if ($old_pass === $user_data['password']) {
            $password_match = true;
        } elseif (password_verify($old_pass, $user_data['password'])) {
            $password_match = true;
        }

        if (!$password_match) {
            $error_message = "Incorrect current password.";
        } else {
            // C. Update Password & Set Status to 'done'
            try {
                $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);

                $update = $pdo->prepare("UPDATE tbl_users SET password = ?, force_password_change = 'done' WHERE user_id = ?");
                $update->execute([$new_hash, $user_id]);

                $success_message = "Password updated! Redirecting...";
                
                // Redirect after 2 seconds
                header("refresh:2;url=user_dashboard.php");

            } catch (PDOException $e) {
                $error_message = "Error updating password.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Change Password</title>
    
    <!-- Simple Embedded Styles -->
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap');

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f0f2f5; /* Neutral Light Gray */
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .card {
            background: #fff;
            width: 100%;
            max-width: 400px;
            padding: 40px 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            text-align: center;
        }

        .icon-box {
            width: 60px;
            height: 60px;
            background: #e0f2fe;
            color: #0284c7;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin: 0 auto 20px;
        }

        h2 {
            margin: 0 0 10px;
            color: #333;
            font-size: 24px;
        }

        p {
            color: #666;
            margin: 0 0 30px;
            font-size: 14px;
            line-height: 1.5;
        }

        .form-group {
            margin-bottom: 15px;
            text-align: left;
        }

        label {
            display: block;
            margin-bottom: 6px;
            font-size: 13px;
            font-weight: 500;
            color: #555;
        }

        input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            box-sizing: border-box; /* Ensures padding doesn't expand width */
            transition: border 0.3s;
        }

        input:focus {
            outline: none;
            border-color: #4361ee; /* Your Primary Color */
        }

        button {
            width: 100%;
            padding: 12px;
            background: #4361ee;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
            transition: background 0.2s;
        }

        button:hover {
            background: #3a56d4;
        }

        .logout-link {
            display: block;
            margin-top: 20px;
            font-size: 13px;
            color: #888;
            text-decoration: none;
        }
        .logout-link:hover { text-decoration: underline; }

        .message {
            padding: 10px;
            border-radius: 6px;
            font-size: 13px;
            margin-bottom: 20px;
        }
        .error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }

    </style>
    
    <!-- Boxicons -->
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>
<body>

    <div class="card">
        <div class="icon-box">
            <i class='bx bxs-lock-open-alt'></i>
        </div>

        <h2>Security Update</h2>
        <p>
            Hello, <?php echo htmlspecialchars($user_data['first_name'] ?? 'User'); ?>.<br>
            Please enter your temporary password below to set your new secure password.
        </p>

        <form method="POST" action="">
            
            <?php if (!empty($error_message)): ?>
                <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
                <div class="message success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>

            <div class="form-group">
                <label>Temporary Password</label>
                <input type="password" name="old_password" placeholder="Enter given password" required autofocus>
            </div>

            <div class="form-group">
                <label>New Password</label>
                <input type="password" name="new_password" placeholder="Create new password" required>
            </div>

            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" placeholder="Confirm new password" required>
            </div>

            <button type="submit" name="change_password">Update Password</button>
        </form>

        <a href="actions/logout.php" class="logout-link">Log Out</a>
    </div>

</body>
</html>