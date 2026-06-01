<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
	echo json_encode(['success' => false, 'message' => 'Not authorized']);
	exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$title = isset($data['title']) ? trim($data['title']) : '';
$content = isset($data['content']) ? trim($data['content']) : '';

if ($title === '' || $content === '') { 
	echo json_encode(['success' => false, 'message' => 'Title and content are required']); 
	exit();
}

$userId = (int)$_SESSION['user_id'];

// Fetch author info
$stmt = $conn->prepare("SELECT firstname, lastname, program, year_graduated, photo FROM itcp WHERE id = ?");
if (!$stmt) {
	echo json_encode(['success' => false, 'message' => 'Prepare failed']);
	exit();
}
$stmt->bind_param('i', $userId);
$stmt->execute();
$u = $stmt->get_result()->fetch_assoc();
$stmt->close();

$authorName = trim(($u['firstname'] ?? '') . ' ' . ($u['lastname'] ?? ''));
$authorProgram = $u['program'] ?? '';
$authorYear = (int)($u['year_graduated'] ?? 0);
$authorPhoto = $u['photo'] ?? null;

// Ensure table exists (best-effort)
@$conn->query("CREATE TABLE IF NOT EXISTS alumni_success_stories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    author_id INT NOT NULL,
    author_name VARCHAR(255) NOT NULL,
    author_program VARCHAR(255) NOT NULL,
    author_year INT NOT NULL,
    author_photo VARCHAR(255),
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status ENUM('draft', 'published', 'archived') DEFAULT 'published',
    featured BOOLEAN DEFAULT FALSE,
    likes_count INT DEFAULT 0,
    comments_count INT DEFAULT 0
)");

$stmt = $conn->prepare("INSERT INTO alumni_success_stories 
    (author_id, author_name, author_program, author_year, author_photo, title, content, status) 
    VALUES (?, ?, ?, ?, ?, ?, ?, 'published')");
if (!$stmt) {
	echo json_encode(['success' => false, 'message' => 'Prepare failed']);
	exit();
}
$stmt->bind_param('ississs', $userId, $authorName, $authorProgram, $authorYear, $authorPhoto, $title, $content);
$ok = $stmt->execute();
$stmt->close();

if ($ok) {
	echo json_encode(['success' => true]);
} else {
	echo json_encode(['success' => false, 'message' => 'Insert failed']);
}
?>

