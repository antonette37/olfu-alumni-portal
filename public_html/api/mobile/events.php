<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Register shutdown function to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        header('Content-Type: application/json');
        // Return empty array to match expected List<Event> format
        http_response_code(200);
        error_log("Events API Fatal Error: " . $error['message'] . " | File: " . $error['file'] . " | Line: " . $error['line']);
        echo json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
});

// Set output buffering to catch any errors
ob_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_clean();
    http_response_code(200);
    exit;
}

// Try to require db_config.php with error handling
$db_config_path = __DIR__ . '/../../db_config.php';
if (!file_exists($db_config_path)) {
    ob_clean();
    http_response_code(500);
    echo json_encode([]); // Return empty array to match expected format
    exit;
}

require_once $db_config_path;

// Set timezone to match al_events.php
date_default_timezone_set('Asia/Manila');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ob_clean();
    http_response_code(405);
    echo json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

require_once __DIR__ . '/includes/mobile_auth.php';
$user_id = mobile_auth_user_id();

if (!$user_id) {
    ob_clean();
    // Return empty array to match expected List<Event> format
    http_response_code(200);
    echo json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $conn = getDBConnection();
    
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
    
    // Set timezone for the database connection (same as al_events.php)
    @$conn->query("SET time_zone = '+08:00'");
    
    // Check if events table exists
    $check_events_table = @$conn->query("SHOW TABLES LIKE 'events'");
    if (!$check_events_table || $check_events_table->num_rows == 0) {
        throw new Exception("Events table does not exist");
    }
    
    // Check if event_registrations table exists
    $has_registrations_table = false;
    try {
        $check_table = @$conn->query("SHOW TABLES LIKE 'event_registrations'");
        $has_registrations_table = $check_table && $check_table->num_rows > 0;
    } catch (Exception $ex) {
        // Table check failed, assume it doesn't exist
        $has_registrations_table = false;
    }
    
    // Fetch all events with registration info (same query structure as al_events.php)
    // Match al_events.php exactly - no status filter in main query, get all events
    $all_events = [];
    
    try {
        if ($has_registrations_table) {
            $sql = "SELECT e.*, 
                    (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id) as registered_count,
                    (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id AND user_id = ?) as is_registered
                    FROM events e 
                    ORDER BY e.event_date DESC, e.event_time DESC";
            
            $stmt = @$conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Error preparing events query: " . ($conn->error ?? 'Unknown error'));
            }
            
            $stmt->bind_param("i", $user_id);
        } else {
            // Fallback if event_registrations table doesn't exist
            $sql = "SELECT e.*, 
                    0 as registered_count,
                    0 as is_registered
                    FROM events e 
                    ORDER BY e.event_date DESC, e.event_time DESC";
            
            $stmt = @$conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Error preparing events query: " . ($conn->error ?? 'Unknown error'));
            }
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Error executing query: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        
        if (!$result) {
            throw new Exception("Error getting result: " . $stmt->error);
        }
        
        while ($row = $result->fetch_assoc()) {
            $all_events[] = $row;
        }
        
        $stmt->close();
    } catch (Exception $query_ex) {
        // If the main query fails, try a simpler query without subqueries
        error_log("Main query failed, trying simple query: " . $query_ex->getMessage());
        
        $simple_sql = "SELECT * FROM events ORDER BY event_date DESC, event_time DESC LIMIT 100";
        $simple_result = @$conn->query($simple_sql);
        
        if ($simple_result) {
            while ($row = $simple_result->fetch_assoc()) {
                $row['registered_count'] = 0;
                $row['is_registered'] = 0;
                $all_events[] = $row;
            }
        } else {
            throw new Exception("Both queries failed. Last error: " . ($conn->error ?? 'Unknown error'));
        }
    }
    
    // Process events and add computed fields like al_events.php does
    foreach ($all_events as $index => $row) {
        $event_date = $row['event_date'] ?? '';
        $event_time = $row['event_time'] ?? '';
        
        // Extract just the date part if event_date contains datetime
        $date_only = substr($event_date, 0, 10);
        
        // Create datetime string for display
        if (!empty($event_time) && $event_time != '00:00:00' && $event_time != '') {
            $display_datetime = $date_only . ' ' . $event_time;
        } else {
            $display_datetime = $date_only;
        }
        
        $all_events[$index]['event_datetime'] = $display_datetime;
        $all_events[$index]['resolved_location'] = $row['location'] ?? $row['venue'] ?? $row['event_location'] ?? $row['place'] ?? $row['address'] ?? '';
        $all_events[$index]['normalized_event_type'] = $row['type'] ?? $row['event_type'] ?? 'General';
        
        // Convert is_registered to boolean (it's a count, so > 0 means registered)
        $all_events[$index]['is_registered'] = isset($row['is_registered']) ? ((int)$row['is_registered'] > 0 ? 1 : 0) : 0;
    }
    
    // Split into upcoming and past events (same logic as al_events.php)
    $now = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $upcoming_events = [];
    $past_events = [];
    
    foreach ($all_events as $e) {
        $event_date = $e['event_date'] ?? '';
        $event_time = $e['event_time'] ?? '';
        
        // Skip if no date
        if (empty($event_date)) {
            continue;
        }
        
        // Extract just the date part
        $date_only = substr($event_date, 0, 10);
        
        // Create datetime string for parsing
        if (!empty($event_time) && $event_time != '00:00:00' && $event_time != '') {
            $datetime_str = $date_only . ' ' . $event_time;
        } else {
            if (strlen($event_date) > 10 && strpos($event_date, ' ') !== false) {
                $datetime_str = $event_date;
            } else {
                $datetime_str = $date_only . ' 00:00:00';
            }
        }
        
        // Parse the event datetime
        try {
            $event_dt = new DateTime($datetime_str, new DateTimeZone('Asia/Manila'));
            
            // Compare with current time
            if ($event_dt >= $now) {
                $upcoming_events[] = $e;
            } else {
                $past_events[] = $e;
            }
        } catch (Exception $ex) {
            // If date parsing fails, try simpler parsing
            try {
                $event_dt = new DateTime($datetime_str);
                $event_dt->setTimezone(new DateTimeZone('Asia/Manila'));
                
                if ($event_dt >= $now) {
                    $upcoming_events[] = $e;
                } else {
                    $past_events[] = $e;
                }
            } catch (Exception $ex2) {
                // If both fail, add to past events
                error_log("Error parsing event date: " . $datetime_str . " - " . $ex2->getMessage());
                $past_events[] = $e;
            }
        }
    }
    
    // Sort upcoming events by date ascending, past events by date descending (same as al_events.php)
    usort($upcoming_events, function($a, $b) {
        $dt_a = ($a['event_date'] ?? '') . ' ' . ($a['event_time'] ?? '00:00:00');
        $dt_b = ($b['event_date'] ?? '') . ' ' . ($b['event_time'] ?? '00:00:00');
        return strtotime($dt_a) - strtotime($dt_b);
    });
    
    usort($past_events, function($a, $b) {
        $dt_a = ($a['event_date'] ?? '') . ' ' . ($a['event_time'] ?? '00:00:00');
        $dt_b = ($b['event_date'] ?? '') . ' ' . ($b['event_time'] ?? '00:00:00');
        return strtotime($dt_b) - strtotime($dt_a);
    });
    
    // Return combined list (upcoming first, then past) - mobile app can handle this
    $events = array_merge($upcoming_events, $past_events);
    
    // Clear output buffer before sending response
    ob_clean();
    
    $conn->close();
    
    echo json_encode($events, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    ob_clean();
    $error_msg = $e->getMessage();
    $error_file = $e->getFile();
    $error_line = $e->getLine();
    error_log("Events API Error: " . $error_msg . " | File: " . $error_file . " | Line: " . $error_line);
    // Return empty array to match expected List<Event> format
    http_response_code(200); // Return 200 with empty array instead of 500
    echo json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Error $e) {
    ob_clean();
    $error_msg = $e->getMessage();
    $error_file = $e->getFile();
    $error_line = $e->getLine();
    error_log("Events API Fatal Error: " . $error_msg . " | File: " . $error_file . " | Line: " . $error_line);
    // Return empty array to match expected List<Event> format
    http_response_code(200); // Return 200 with empty array instead of 500
    echo json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    ob_clean();
    $error_msg = $e->getMessage();
    $error_file = $e->getFile();
    $error_line = $e->getLine();
    error_log("Events API Throwable Error: " . $error_msg . " | File: " . $error_file . " | Line: " . $error_line);
    // Return empty array to match expected List<Event> format
    http_response_code(200); // Return 200 with empty array instead of 500
    echo json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
?>

