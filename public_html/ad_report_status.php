<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: al_login.php');
    exit();
}
// Redirect to reports page with status report type
header('Location: ad_reports.php?report=status');
exit();
