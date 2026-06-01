<?php
/**
 * User Activity Logging Functions
 * This file contains functions to log user activities including login/logout with device information
 */

function getUserAgentInfo($userAgent) {
    $deviceType = 'Unknown';
    $browser = 'Unknown';
    $os = 'Unknown';
    
    // Detect device type
    if (preg_match('/Mobile|Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i', $userAgent)) {
        $deviceType = 'Mobile';
    } elseif (preg_match('/Tablet|iPad/i', $userAgent)) {
        $deviceType = 'Tablet';
    } else {
        $deviceType = 'Desktop';
    }
    
    // Detect browser
    if (preg_match('/Chrome/i', $userAgent)) {
        $browser = 'Chrome';
    } elseif (preg_match('/Firefox/i', $userAgent)) {
        $browser = 'Firefox';
    } elseif (preg_match('/Safari/i', $userAgent)) {
        $browser = 'Safari';
    } elseif (preg_match('/Edge/i', $userAgent)) {
        $browser = 'Edge';
    } elseif (preg_match('/Opera/i', $userAgent)) {
        $browser = 'Opera';
    }
    
    // Detect operating system
    if (preg_match('/Windows NT 10/i', $userAgent)) {
        $os = 'Windows 10';
    } elseif (preg_match('/Windows NT 6.3/i', $userAgent)) {
        $os = 'Windows 8.1';
    } elseif (preg_match('/Windows NT 6.2/i', $userAgent)) {
        $os = 'Windows 8';
    } elseif (preg_match('/Windows NT 6.1/i', $userAgent)) {
        $os = 'Windows 7';
    } elseif (preg_match('/Mac OS X/i', $userAgent)) {
        $os = 'macOS';
    } elseif (preg_match('/Linux/i', $userAgent)) {
        $os = 'Linux';
    } elseif (preg_match('/Android/i', $userAgent)) {
        $os = 'Android';
    } elseif (preg_match('/iPhone|iPad|iPod/i', $userAgent)) {
        $os = 'iOS';
    }
    
    return [
        'device_type' => $deviceType,
        'browser' => $browser,
        'operating_system' => $os
    ];
}

function getClientIP() {
    $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    
    foreach ($ipKeys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
}

function logUserActivity($conn, $userId, $activityType, $description, $additionalData = []) {
    try {
        $ipAddress = getClientIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $deviceInfo = getUserAgentInfo($userAgent);
        
        // Get location (simplified - in production you might want to use a geolocation service)
        $location = 'Unknown';
        if ($ipAddress !== 'Unknown' && $ipAddress !== '127.0.0.1') {
            // For demo purposes, we'll just show the IP. In production, you'd use a geolocation API
            $location = "IP: $ipAddress";
        }
        
        $sql = "INSERT INTO user_logs (user_id, activity_type, activity_description, ip_address, user_agent, device_type, browser, operating_system, location) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        
        if ($stmt) {
            $stmt->bind_param("issssssss", 
                $userId, 
                $activityType, 
                $description, 
                $ipAddress, 
                $userAgent, 
                $deviceInfo['device_type'], 
                $deviceInfo['browser'], 
                $deviceInfo['operating_system'], 
                $location
            );
            
            $result = $stmt->execute();
            $stmt->close();
            return $result;
        }
    } catch (Exception $e) {
        error_log("Error logging user activity: " . $e->getMessage());
        return false;
    }
    
    return false;
}

function logUserLogin($conn, $userId, $username) {
    $description = "User '$username' logged in successfully";
    return logUserActivity($conn, $userId, 'login', $description);
}

function logUserLogout($conn, $userId, $username) {
    $description = "User '$username' logged out";
    return logUserActivity($conn, $userId, 'logout', $description);
}

function logProfileUpdate($conn, $userId, $username, $fieldsUpdated = []) {
    $description = "User '$username' updated profile";
    if (!empty($fieldsUpdated)) {
        $description .= " (Fields: " . implode(', ', $fieldsUpdated) . ")";
    }
    return logUserActivity($conn, $userId, 'profile_update', $description);
}

function logSearchActivity($conn, $userId, $username, $searchTerm) {
    $description = "User '$username' searched for: '$searchTerm'";
    return logUserActivity($conn, $userId, 'search', $description);
}
?>
