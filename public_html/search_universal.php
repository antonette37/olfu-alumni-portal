<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['people'=>[], 'events'=>[], 'jobs'=>[], 'pages'=>[]]);
    exit;
}

$q = trim($_GET['q'] ?? '');
if ($q === '' || mb_strlen($q) < 2) {
    echo json_encode(['people'=>[], 'events'=>[], 'jobs'=>[], 'pages'=>[]]);
    exit;
}

$like = '%' . $conn->real_escape_string($q) . '%';

// People (alumni directory)
$people = [];
$stmt = $conn->prepare("SELECT id, firstname, lastname, course FROM itcp WHERE CONCAT(firstname,' ',lastname) LIKE ? OR course LIKE ? LIMIT 5");
if ($stmt) {
    $stmt->bind_param('ss', $like, $like);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $people[] = [
            'title' => $row['firstname'] . ' ' . $row['lastname'],
            'subtitle' => $row['course'] ?? '',
            'url' => 'al_directory.php?id=' . (int)$row['id'],
        ];
    }
    $stmt->close();
}

// Events
$events = [];
$stmt = $conn->prepare("SELECT id, title, event_date FROM events WHERE title LIKE ? OR description LIKE ? ORDER BY event_date DESC LIMIT 5");
if ($stmt) {
    $stmt->bind_param('ss', $like, $like);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $events[] = [
            'title' => $row['title'],
            'subtitle' => date('M d, Y', strtotime($row['event_date'])),
            'url' => 'al_events.php?id=' . (int)$row['id'],
        ];
    }
    $stmt->close();
}

// Jobs
$jobs = [];
$stmt = $conn->prepare("SELECT id, title, company FROM job_postings WHERE status = 'active' AND (title LIKE ? OR company LIKE ? OR description LIKE ?) ORDER BY created_at DESC LIMIT 5");
if ($stmt) {
    $stmt->bind_param('sss', $like, $like, $like);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $jobs[] = [
            'title' => $row['title'],
            'subtitle' => $row['company'] ?? '',
            'url' => 'al_career.php#jobs',
        ];
    }
    $stmt->close();
}

// Pages quick navigation (static mapping)
$pages = [];
$pagesMap = [
    ['Dashboard', 'al_dashboard.php'],
    ['Career', 'al_career.php'],
    ['Events', 'al_events.php'],
    ['Directory', 'al_directory.php'],
    ['Messages', 'al_messages.php'],
    ['General Settings', 'al_general_settings.php'],
    ['Privacy & Security', 'al_privacy_settings.php'],
    ['About', 'al_about.php'],
    ['FAQs', 'gen_faqs.php'],
    ['Contact', 'al_contact.php'],
];
$qLower = mb_strtolower($q);
foreach ($pagesMap as [$title, $url]) {
    if (strpos(mb_strtolower($title), $qLower) !== false) {
        $pages[] = ['title' => $title, 'url' => $url];
    }
}

echo json_encode([
    'people' => $people,
    'events' => $events,
    'jobs' => $jobs,
    'pages' => $pages,
]);
?>


