<?php
session_start();
require_once 'config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

// Helper: safe count from query
function countQuery(mysqli $conn, string $sql, array $params, string $types) {
    $stmt = $conn->prepare($sql);
    if ($stmt === false) return 0;
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res) return 0;
    $row = $res->fetch_row();
    return (int)($row[0] ?? 0);
}

// Pull base profile
$stmt = $conn->prepare("SELECT current_position, company, industry, program, year_graduated, summary FROM itcp WHERE id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc() ?: [];

// Skills (>=5 gives full credit)
$skillsCount = countQuery($conn, "SELECT COUNT(*) FROM alumni_skills WHERE alumni_id = ?", [$userId], 'i');

// Milestones by type (if career_milestones table is used)
$expCount  = countQuery($conn, "SELECT COUNT(*) FROM career_milestones WHERE alumni_id = ? AND milestone_type IN ('Employment','Work','Experience')", [$userId], 'i');
$eduCount  = countQuery($conn, "SELECT COUNT(*) FROM career_milestones WHERE alumni_id = ? AND milestone_type IN ('Education','Degree','School')", [$userId], 'i');
$achCount  = countQuery($conn, "SELECT COUNT(*) FROM career_milestones WHERE alumni_id = ? AND milestone_type IN ('Achievement','Award','Promotion','Publication','Patent','Talk')", [$userId], 'i');

// Certifications
$certCount = countQuery($conn, "SELECT COUNT(*) FROM certifications WHERE alumni_id = ?", [$userId], 'i');
// Projects
$projCount = countQuery($conn, "SELECT COUNT(*) FROM projects WHERE alumni_id = ?", [$userId], 'i');

// Willingness to help (optional table/field)
$help = 0;
if ($stmt = $conn->prepare("SELECT willingness FROM profile_settings WHERE alumni_id = ?")) {
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    if ($r && !empty($r['willingness'])) $help = 1;
}

// Compute score with weights (sum to 100)
$score = 0;

// Summary (20%)
$hasSummary = !empty(trim($profile['summary'] ?? ''));
$score += $hasSummary ? 20 : 0;

// Experience≥1 (20%)
$score += ($expCount > 0) ? 20 : 0;

// Education≥1 (15%) fallback to program/year if no milestone
$hasEdu = $eduCount > 0 || (!empty($profile['program']) && !empty($profile['year_graduated']));
$score += $hasEdu ? 15 : 0;

// Skills≥5 (15%) partial if 1-4
if ($skillsCount >= 5) {
    $score += 15;
} elseif ($skillsCount > 0) {
    $score += max(5, min(12, $skillsCount * 3));
}

// Certifications or Projects≥1 (15%)
$score += (($certCount > 0) || ($projCount > 0)) ? 15 : 0;

// Willingness-to-help (10%)
$score += ($help ? 10 : 0);

// Achievements≥1 (5%)
$score += ($achCount > 0) ? 5 : 0;

// Clamp 0..100
$score = max(0, min(100, (int)round($score)));

echo json_encode([
    'completeness' => $score,
    'breakdown' => [
        'summary' => $hasSummary,
        'experience' => $expCount,
        'education' => $hasEdu ? 1 : 0,
        'skills' => $skillsCount,
        'certifications' => $certCount,
        'projects' => $projCount,
        'willingness' => $help,
        'achievements' => $achCount
    ]
]);
?>


