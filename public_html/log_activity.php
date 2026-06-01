<?php
function logActivity($conn, $user_id, $activity_type, $activity_description) {
    $ip_address = $_SERVER['REMOTE_ADDR'];
    
    $sql = "INSERT INTO user_logs (user_id, activity_type, activity_description, ip_address) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isss", $user_id, $activity_type, $activity_description, $ip_address);
    $stmt->execute();
    $stmt->close();
}

// Common activity types
define('ACTIVITY_LOGIN', 'login');
define('ACTIVITY_LOGOUT', 'logout');
define('ACTIVITY_PROFILE_UPDATE', 'profile_update');
define('ACTIVITY_SEARCH', 'search');
define('ACTIVITY_VIEW_PROFILE', 'view_profile');
define('ACTIVITY_VIEW_DIRECTORY', 'view_directory');
define('ACTIVITY_VIEW_EVENTS', 'view_events');
define('ACTIVITY_VIEW_JOBS', 'view_jobs');
define('ACTIVITY_VIEW_LOGS', 'view_logs');
?> 