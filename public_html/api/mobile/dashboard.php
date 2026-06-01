<?php
/**
 * Mobile dashboard API — mirrors al_dashboard.php queries (defensive, no fatal on missing tables/columns).
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../../db_config.php';
$compat = __DIR__ . '/../../includes/mysqli_compat.php';
if (is_file($compat)) {
    require_once $compat;
}
require_once __DIR__ . '/includes/mobile_auth.php';

if (!function_exists('dash_profile_image_url')) {
    function dash_profile_image_url($photo, $userId)
    {
        $uid = (int) $userId;
        $photoFile = __DIR__ . '/includes/mobile_photo_file.php';
        $resolveFile = __DIR__ . '/includes/resolve_profile_image.php';
        if (is_file($photoFile) && is_file($resolveFile)) {
            require_once $photoFile;
            require_once $resolveFile;
            if (function_exists('mobile_resolve_profile_image_url')) {
                return mobile_resolve_profile_image_url($photo, 'https://ccsolfualumni.sbs', $uid);
            }
        }
        if ($uid > 0) {
            return 'https://ccsolfualumni.sbs/api/mobile/profile_photo.php?user_id=' . $uid;
        }

        return null;
    }
}

function dash_mobile_stmt_result($stmt)
{
    if (function_exists('olfu_stmt_get_result')) {
        return olfu_stmt_get_result($stmt);
    }
    if ($stmt && method_exists($stmt, 'get_result')) {
        return @$stmt->get_result();
    }
    return false;
}

function dash_mobile_clean_description($text)
{
    if ($text === null || $text === '') {
        return '';
    }
    $text = str_replace(['\\r\\n', '\\n', '\\r'], "\n", (string) $text);
    return stripslashes($text);
}

function dash_mobile_normalize_event(array $row): array
{
    $eventDate = $row['event_date'] ?? '';
    $dateOnly = substr((string) $eventDate, 0, 10);
    $eventTime = $row['event_time'] ?? '';
    if ($eventTime === '' && strlen((string) $eventDate) > 10) {
        $eventTime = substr((string) $eventDate, 11, 8);
    }
    $location = $row['location'] ?? $row['venue'] ?? $row['event_location'] ?? '';
    $type = $row['type'] ?? $row['event_type'] ?? 'General';

    return [
        'id' => (int) ($row['id'] ?? 0),
        'title' => $row['title'] ?? '',
        'description' => dash_mobile_clean_description($row['description'] ?? ''),
        'content' => dash_mobile_clean_description($row['description'] ?? $row['content'] ?? ''),
        'event_date' => $dateOnly,
        'event_time' => $eventTime,
        'location' => $location,
        'type' => $type,
        'image' => $row['image'] ?? $row['banner_image'] ?? null,
        'status' => $row['status'] ?? 'published',
        'created_at' => $row['created_at'] ?? null,
    ];
}

$user_id = mobile_auth_user_id();
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized — please log in again']);
    exit;
}

try {
    $conn = getDBConnection();

    // User (Hostinger-safe: works without mysqlnd)
    $user = [];
    $user_stmt = $conn->prepare(
        'SELECT id, email, firstname, lastname, status, photo, personal_contact, address, year_graduated, program, employment_status, company, position FROM itcp WHERE id = ? LIMIT 1'
    );
    if ($user_stmt) {
        $user_stmt->bind_param('i', $user_id);
        $user_stmt->execute();
        if (function_exists('mysqli_stmt_fetch_assoc_compat')) {
            $user = mysqli_stmt_fetch_assoc_compat($user_stmt) ?: [];
        } else {
            $user_result = dash_mobile_stmt_result($user_stmt);
            if ($user_result) {
                $user = $user_result->fetch_assoc() ?: [];
            }
        }
        $user_stmt->close();
    }

    if (empty($user)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
        exit;
    }

    $profile_fields = ['photo', 'firstname', 'lastname', 'email', 'program', 'year_graduated', 'position', 'company', 'address', 'personal_contact'];
    $filled_fields = 0;
    foreach ($profile_fields as $f) {
        $v = $user[$f] ?? null;
        if ($v !== null && trim((string) $v) !== '') {
            $filled_fields++;
        }
    }
    $profile_completion = count($profile_fields) > 0
        ? round(($filled_fields / count($profile_fields)) * 100)
        : 0;

    $userResponse = [
        'id' => (int) $user['id'],
        'email' => $user['email'] ?? '',
        'firstname' => $user['firstname'] ?? '',
        'lastname' => $user['lastname'] ?? '',
        'status' => $user['status'] ?? 'active',
        'profile_image' => dash_profile_image_url($user['photo'] ?? null, (int) $user['id']),
        'photo' => $user['photo'] ?? null,
        'phone' => $user['personal_contact'] ?? null,
        'address' => $user['address'] ?? '',
        'batch' => $user['year_graduated'] ?? null,
        'course' => $user['program'] ?? null,
        'employment_status' => $user['employment_status'] ?? null,
        'company' => $user['company'] ?? null,
        'position' => $user['position'] ?? null,
        'profile_completion' => $profile_completion,
    ];

    $user_skills = [];
    try {
        $skills_stmt = $conn->prepare('SELECT skill_name, proficiency FROM alumni_skills WHERE alumni_id = ? ORDER BY proficiency DESC LIMIT 8');
        if ($skills_stmt) {
            $skills_stmt->bind_param('i', $user_id);
            $skills_stmt->execute();
            $skills_result = dash_mobile_stmt_result($skills_stmt);
            if ($skills_result) {
                while (($row = $skills_result->fetch_assoc()) !== null) {
                    $user_skills[] = $row;
                }
                if (method_exists($skills_result, 'free')) {
                    $skills_result->free();
                }
            }
            $skills_stmt->close();
        }
    } catch (Throwable $e) {
        error_log('mobile dashboard skills: ' . $e->getMessage());
    }

    // Announcements — same query as al_dashboard.php
    $announcements = [];
    try {
        $sql = "SELECT * FROM announcements WHERE status = 'published' ORDER BY created_at DESC LIMIT 5";
        $announcements_result = $conn->query($sql);
        if ($announcements_result) {
            $site = 'https://ccsolfualumni.sbs';
            while ($row = $announcements_result->fetch_assoc()) {
                $row['content'] = dash_mobile_clean_description($row['content'] ?? '');
                $img = trim((string) ($row['image'] ?? ''));
                $row['image_url'] = $img !== ''
                    ? $site . '/serve_announcement_image.php?img=' . rawurlencode(basename($img))
                    : null;
                $announcements[] = $row;
            }
        }
    } catch (Throwable $e) {
        error_log('mobile dashboard announcements: ' . $e->getMessage());
    }

    // Upcoming events — same as al_dashboard.php (no status filter)
    $upcoming_events = [];
    try {
        $sql = "SELECT * FROM events WHERE event_date >= CURDATE() ORDER BY event_date ASC LIMIT 5";
        $events_result = $conn->query($sql);
        if ($events_result) {
            while ($row = $events_result->fetch_assoc()) {
                $upcoming_events[] = dash_mobile_normalize_event($row);
            }
        }
    } catch (Throwable $e) {
        error_log('mobile dashboard events: ' . $e->getMessage());
    }

    // Notifications
    $notifications = [];
    $unread_count = 0;
    try {
        $notifications_stmt = $conn->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10');
        if ($notifications_stmt) {
            $notifications_stmt->bind_param('i', $user_id);
            $notifications_stmt->execute();
            $notifications_result = dash_mobile_stmt_result($notifications_stmt);
            if ($notifications_result) {
                while ($row = $notifications_result->fetch_assoc()) {
                    $notifications[] = $row;
                    if (empty($row['is_read'])) {
                        $unread_count++;
                    }
                }
            }
            $notifications_stmt->close();
        }
    } catch (Throwable $e) {
        error_log('mobile dashboard notifications: ' . $e->getMessage());
    }

    // Job applications count
    $job_applications_count = 0;
    try {
        $job_apps_stmt = $conn->prepare('SELECT COUNT(*) as count FROM job_applications WHERE alumni_id = ?');
        if ($job_apps_stmt) {
            $job_apps_stmt->bind_param('i', $user_id);
            $job_apps_stmt->execute();
            if (function_exists('mysqli_stmt_fetch_assoc_compat')) {
                $job_apps_row = mysqli_stmt_fetch_assoc_compat($job_apps_stmt);
                $job_applications_count = (int) ($job_apps_row['count'] ?? 0);
            } else {
                $job_apps_result = dash_mobile_stmt_result($job_apps_stmt);
                if ($job_apps_result) {
                    $job_apps_row = $job_apps_result->fetch_assoc();
                    $job_applications_count = (int) ($job_apps_row['count'] ?? 0);
                }
            }
            $job_apps_stmt->close();
        }
    } catch (Throwable $e) {
        error_log('mobile dashboard job_applications: ' . $e->getMessage());
    }

    // Recent jobs — same as al_dashboard.php
    $recent_jobs = [];
    try {
        $jobs_result = $conn->query("SELECT id, title, company, location, posted_date AS created_at FROM jobs WHERE status = 'active' ORDER BY posted_date DESC LIMIT 3");
        if ($jobs_result) {
            while ($row = $jobs_result->fetch_assoc()) {
                $recent_jobs[] = $row;
            }
        }
    } catch (Throwable $e) {
        error_log('mobile dashboard jobs: ' . $e->getMessage());
    }
    if (empty($recent_jobs)) {
        try {
            $jp_result = $conn->query("SELECT id, title, company, location, created_at FROM job_postings WHERE status = 'active' ORDER BY created_at DESC LIMIT 3");
            if ($jp_result) {
                while ($row = $jp_result->fetch_assoc()) {
                    $recent_jobs[] = $row;
                }
            }
        } catch (Throwable $e) {
            error_log('mobile dashboard job_postings: ' . $e->getMessage());
        }
    }

    $recent_activities = [];
    try {
        $act_events = $conn->query(
            "SELECT 'event' AS type, title AS activity_title, event_date AS activity_date, 'calendar' AS icon, 'green' AS color
             FROM events WHERE event_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
             ORDER BY event_date ASC LIMIT 2"
        );
        if ($act_events) {
            while ($row = $act_events->fetch_assoc()) {
                $recent_activities[] = $row;
            }
        }
        $act_jobs = $conn->prepare(
            "SELECT 'job_application' AS type, CONCAT('Applied to ', jp.title) AS activity_title, ja.created_at AS activity_date, 'briefcase' AS icon, 'blue' AS color
             FROM job_applications ja JOIN job_postings jp ON ja.job_id = jp.id WHERE ja.alumni_id = ? ORDER BY ja.created_at DESC LIMIT 2"
        );
        if ($act_jobs) {
            $act_jobs->bind_param('i', $user_id);
            $act_jobs->execute();
            $act_result = dash_mobile_stmt_result($act_jobs);
            if ($act_result) {
                while ($row = $act_result->fetch_assoc()) {
                    $recent_activities[] = $row;
                }
            }
            $act_jobs->close();
        }
        usort($recent_activities, function ($a, $b) {
            return strtotime($b['activity_date'] ?? '') - strtotime($a['activity_date'] ?? '');
        });
        $recent_activities = array_slice($recent_activities, 0, 5);
    } catch (Throwable $e) {
        error_log('mobile dashboard activities: ' . $e->getMessage());
    }

    echo json_encode([
        'success' => true,
        'user' => $userResponse,
        'announcements' => $announcements,
        'upcoming_events' => $upcoming_events,
        'upcoming_events_count' => count($upcoming_events),
        'notifications' => $notifications,
        'unread_notifications' => $unread_count,
        'job_applications_count' => $job_applications_count,
        'jobs' => $recent_jobs,
        'skills' => $user_skills,
        'recent_activities' => $recent_activities,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
