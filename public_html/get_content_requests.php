<?php
// Set proper headers and start output buffering to prevent any HTML output
header('Content-Type: application/json');
ob_start();

// Suppress any warnings/notices that might interfere with JSON
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

// Start session to track admin visits
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db_config.php';
require_once __DIR__ . '/includes/mysqli_compat.php';

/**
 * Public URL for an itcp profile photo (same pattern as alumni directory / view profile).
 */
function gr_itcp_photo_url(?string $photo): string
{
    $p = trim((string) $photo);
    if ($p === '') {
        return '';
    }
    if (stripos($p, 'http') === 0) {
        return $p;
    }

    return 'serve_profile_image.php?img=' . rawurlencode(basename($p));
}

try {
    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    // Check if admin has visited content management page recently
    $last_visit_key = 'admin_last_visit_content_management';
    $last_visit = $_SESSION[$last_visit_key] ?? null;
    
    // Get pending job posts - with error handling
    $job_pending_count = 0;
    $job_requests = [];
    
    // Check if jobs table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'jobs'");
    if ($table_check && $table_check->num_rows > 0) {
        $jobs_has_user_id = false;
        $juid = $conn->query("SHOW COLUMNS FROM `jobs` LIKE 'user_id'");
        if ($juid && $juid->num_rows > 0) {
            $jobs_has_user_id = true;
        }

        $job_count_sql = "SELECT COUNT(*) as pending_count FROM jobs WHERE status = 'pending'";
        if ($last_visit) {
            $job_count_sql .= " AND posted_date > ?";
        }
        
        $job_stmt = $conn->prepare($job_count_sql);
        if ($job_stmt) {
            if ($last_visit) {
                $job_stmt->bind_param('s', $last_visit);
            }
            if ($job_stmt->execute()) {
                $job_count_result = mysqli_stmt_fetch_assoc_compat($job_stmt);
                $job_pending_count = $job_count_result['pending_count'] ?? 0;
            }
            $job_stmt->close();
        }
        
        // Get recent pending job posts (last 5)
        if ($jobs_has_user_id) {
            $job_requests_sql = "SELECT j.id, j.title, j.company, j.posted_date, j.user_id,
                                i.firstname, i.lastname, i.email AS requester_email, i.photo AS requester_photo
                                FROM jobs j
                                LEFT JOIN itcp i ON j.user_id = i.id
                                WHERE j.status = 'pending'";
            if ($last_visit) {
                $job_requests_sql .= " AND j.posted_date > ?";
            }
            $job_requests_sql .= " ORDER BY j.posted_date DESC LIMIT 5";
        } else {
            $job_requests_sql = "SELECT id, title, company, posted_date FROM jobs WHERE status = 'pending'";
            if ($last_visit) {
                $job_requests_sql .= " AND posted_date > ?";
            }
            $job_requests_sql .= " ORDER BY posted_date DESC LIMIT 5";
        }
        
        $job_requests_stmt = $conn->prepare($job_requests_sql);
        if ($job_requests_stmt) {
            if ($last_visit) {
                $job_requests_stmt->bind_param('s', $last_visit);
            }
            if ($job_requests_stmt->execute()) {
                foreach (mysqli_stmt_fetch_all_assoc_compat($job_requests_stmt) as $row) {
                    if ($jobs_has_user_id) {
                        $nm = trim((string) ($row['firstname'] ?? '') . ' ' . (string) ($row['lastname'] ?? ''));
                        $nm = trim(preg_replace('/\s+/', ' ', $nm));
                        if ($nm === '') {
                            $em = trim((string) ($row['requester_email'] ?? ''));
                            $nm = $em !== '' ? $em : (((int) ($row['user_id'] ?? 0)) > 0 ? 'Alumni #' . (int) $row['user_id'] : 'Unknown');
                        }
                    } else {
                        $nm = '—';
                    }
                    $job_requests[] = [
                        'kind' => 'job',
                        'id' => $row['id'],
                        'title' => htmlspecialchars((string) ($row['title'] ?? '')),
                        'company' => htmlspecialchars((string) ($row['company'] ?? '')),
                        'author' => htmlspecialchars($nm),
                        'time' => date('M d, Y H:i', strtotime((string) ($row['posted_date'] ?? 'now'))),
                        'photo_url' => gr_itcp_photo_url($jobs_has_user_id ? ($row['requester_photo'] ?? null) : null),
                    ];
                }
            }
            $job_requests_stmt->close();
        }
    }

    // Get pending success stories (if any) - with error handling
    $story_pending_count = 0;
    $story_requests = [];
    
    // Check if alumni_success_stories table exists
    $story_table_check = $conn->query("SHOW TABLES LIKE 'alumni_success_stories'");
    if ($story_table_check && $story_table_check->num_rows > 0) {
        $story_count_sql = "SELECT COUNT(*) as pending_count FROM alumni_success_stories WHERE status = 'draft'";
        if ($last_visit) {
            $story_count_sql .= " AND created_at > ?";
        }
        
        $story_stmt = $conn->prepare($story_count_sql);
        if ($story_stmt) {
            if ($last_visit) {
                $story_stmt->bind_param('s', $last_visit);
            }
            if ($story_stmt->execute()) {
                $story_count_result = mysqli_stmt_fetch_assoc_compat($story_stmt);
                $story_pending_count = $story_count_result['pending_count'] ?? 0;
            }
            $story_stmt->close();
        }
        
        // Get recent pending success stories (last 5)
        $story_has_author_photo = false;
        $apc = $conn->query("SHOW COLUMNS FROM `alumni_success_stories` LIKE 'author_photo'");
        if ($apc && $apc->num_rows > 0) {
            $story_has_author_photo = true;
        }
        $story_requests_sql = $story_has_author_photo
            ? "SELECT id, title, author_name, author_photo, created_at FROM alumni_success_stories WHERE status = 'draft'"
            : "SELECT id, title, author_name, created_at FROM alumni_success_stories WHERE status = 'draft'";
        if ($last_visit) {
            $story_requests_sql .= " AND created_at > ?";
        }
        $story_requests_sql .= " ORDER BY created_at DESC LIMIT 5";
        
        $story_requests_stmt = $conn->prepare($story_requests_sql);
        if ($story_requests_stmt) {
            if ($last_visit) {
                $story_requests_stmt->bind_param('s', $last_visit);
            }
            if ($story_requests_stmt->execute()) {
                foreach (mysqli_stmt_fetch_all_assoc_compat($story_requests_stmt) as $row) {
                    $story_requests[] = [
                        'kind' => 'story',
                        'id' => $row['id'],
                        'title' => htmlspecialchars((string) ($row['title'] ?? '')),
                        'author' => htmlspecialchars((string) ($row['author_name'] ?? '')),
                        'time' => date('M d, Y H:i', strtotime((string) ($row['created_at'] ?? 'now'))),
                        'photo_url' => $story_has_author_photo ? gr_itcp_photo_url($row['author_photo'] ?? null) : '',
                    ];
                }
            }
            $story_requests_stmt->close();
        }
    }

    $total_pending = $job_pending_count + $story_pending_count;

    $response = [
        'total_pending' => (int)$total_pending,
        'job_pending_count' => (int)$job_pending_count,
        'story_pending_count' => (int)$story_pending_count,
        'job_requests' => $job_requests,
        'story_requests' => $story_requests,
        'last_visit' => $last_visit
    ];

    // Clear any output buffer content and send clean JSON
    ob_clean();
    echo json_encode($response);
    
} catch (Exception $e) {
    // Clear any output buffer content and send error JSON
    ob_clean();
    echo json_encode(['error' => $e->getMessage(), 'total_pending' => 0, 'job_pending_count' => 0, 'story_pending_count' => 0, 'job_requests' => [], 'story_requests' => []]);
}
?>
