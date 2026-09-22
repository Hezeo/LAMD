<?php
// index.php
require_once '../config.php';
require_once '../config_session.php';

// If logged in, go to dashboard
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header("Location: user_dashboard.php");
    exit();
}

// Otherwise, go to the intro FIRST
header("Location: land_matters_intro.php");
exit();
?>