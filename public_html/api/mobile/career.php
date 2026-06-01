<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

require_once '../../db_config.php';
require_once __DIR__ . '/includes/mobile_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user_id = mobile_auth_user_id();
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $conn = getDBConnection();
    $career_history = [];
    $skills = [];
    $achievements = [];

    $career_stmt = @$conn->prepare('SELECT id, alumni_id, position, company, start_date, end_date, description FROM career_history WHERE alumni_id = ? ORDER BY start_date DESC');
    if ($career_stmt) {
        $career_stmt->bind_param('i', $user_id);
        if ($career_stmt->execute()) {
            $career_result = $career_stmt->get_result();
            if ($career_result) {
                while ($row = $career_result->fetch_assoc()) {
                    $career_history[] = $row;
                }
            }
        }
        $career_stmt->close();
    }

    $skills_stmt = @$conn->prepare('SELECT id, alumni_id, skill_name, proficiency_level FROM alumni_skills WHERE alumni_id = ? ORDER BY skill_name');
    if ($skills_stmt) {
        $skills_stmt->bind_param('i', $user_id);
        if ($skills_stmt->execute()) {
            $skills_result = $skills_stmt->get_result();
            if ($skills_result) {
                while ($row = $skills_result->fetch_assoc()) {
                    $skills[] = $row;
                }
            }
        }
        $skills_stmt->close();
    }

    $achievements_stmt = @$conn->prepare('SELECT id, alumni_id, achievement_title, achievement_description, date_achieved FROM achievements WHERE alumni_id = ? ORDER BY date_achieved DESC');
    if ($achievements_stmt) {
        $achievements_stmt->bind_param('i', $user_id);
        if ($achievements_stmt->execute()) {
            $achievements_result = $achievements_stmt->get_result();
            if ($achievements_result) {
                while ($row = $achievements_result->fetch_assoc()) {
                    $achievements[] = $row;
                }
            }
        }
        $achievements_stmt->close();
    }
    
    echo json_encode([
        'career_history' => $career_history,
        'skills' => $skills,
        'achievements' => $achievements
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>

