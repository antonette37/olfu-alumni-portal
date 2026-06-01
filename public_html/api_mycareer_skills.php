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
$method = $_SERVER['REQUEST_METHOD'];

function normalizeSkill($name) {
    $n = trim($name);
    $n = preg_replace('/\s+/', ' ', $n);
    return $n;
}

if ($method === 'GET') {
    $stmt = $conn->prepare("SELECT skill_name FROM alumni_skills WHERE alumni_id = ? ORDER BY skill_name ASC");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $skills = array_map(function($r){ return $r['skill_name']; }, $rows);
    echo json_encode(['skills' => $skills]);
    exit;
}

if ($method === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true) ?? [];
    $tags = $payload['tags'] ?? [];
    if (!is_array($tags) || count($tags) === 0) {
        http_response_code(400);
        echo json_encode(['error' => 'No tags provided']);
        exit;
    }
    // Deduplicate and validate
    $clean = [];
    foreach ($tags as $t) {
        $t = normalizeSkill($t);
        if ($t === '' || strlen($t) > 50) continue;
        $clean[strtolower($t)] = $t;
    }
    if (empty($clean)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid tags']);
        exit;
    }
    // Insert ignoring duplicates
    $inserted = [];
    $stmt = $conn->prepare("SELECT COUNT(*) c FROM alumni_skills WHERE alumni_id = ? AND LOWER(skill_name) = LOWER(?)");
    $ins = $conn->prepare("INSERT INTO alumni_skills (alumni_id, skill_name) VALUES (?, ?)");
    foreach ($clean as $k => $val) {
        $stmt->bind_param('is', $userId, $val);
        $stmt->execute();
        $c = $stmt->get_result()->fetch_assoc()['c'] ?? 0;
        if ($c == 0) {
            $ins->bind_param('is', $userId, $val);
            $ins->execute();
            $inserted[] = $val;
        }
    }
    echo json_encode(['inserted' => $inserted]);
    exit;
}

if ($method === 'DELETE') {
    $payload = json_decode(file_get_contents('php://input'), true) ?? [];
    $name = normalizeSkill($payload['name'] ?? '');
    if ($name === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Skill name required']);
        exit;
    }
    $stmt = $conn->prepare("DELETE FROM alumni_skills WHERE alumni_id = ? AND LOWER(skill_name) = LOWER(?)");
    $stmt->bind_param('is', $userId, $name);
    $stmt->execute();
    echo json_encode(['deleted' => $name]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
?>


