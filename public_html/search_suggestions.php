<?php
header('Content-Type: application/json');

require_once 'db_config.php';

// Basic CORS allowance for same-origin use; adjust if needed
header('Cache-Control: no-store');

try {
    $conn = getDBConnection();
}
catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
if ($q === '' || mb_strlen($q) < 2) {
    echo json_encode(['suggestions' => []]);
    exit;
}

// Build wildcard for LIKE
$like = '%' . $q . '%';

// Fields to search: adjust to your schema used in al_directory
// Using table itcp based on existing code
$sql = "SELECT id, CONCAT_WS(' ', firstname, middlename, lastname) AS full_name, program, company, year_graduated AS year, campus
        FROM itcp
        WHERE (
            CONCAT_WS(' ', firstname, middlename, lastname) LIKE ?
            OR program LIKE ?
            OR company LIKE ?
            OR year_graduated LIKE ?
            OR campus LIKE ?
        )
        ORDER BY
            CASE
                WHEN CONCAT_WS(' ', firstname, middlename, lastname) LIKE ? THEN 0
                WHEN program LIKE ? THEN 1
                WHEN company LIKE ? THEN 2
                WHEN year_graduated LIKE ? THEN 3
                ELSE 4
            END,
            lastname ASC
        LIMIT 10";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Query prepare failed']);
    exit;
}

// Bind parameters for both WHERE and ORDER BY LIKEs
$stmt->bind_param(
    'sssssssss',
    $like, // name
    $like, // program
    $like, // company
    $like, // year
    $like, // campus
    $like, // order name
    $like, // order program
    $like, // order company
    $like // order year
);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['error' => 'Query execution failed']);
    exit;
}

$result = $stmt->get_result();
$rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

$suggestions = [];
foreach ($rows as $row) {
    $parts = [];
    if (!empty($row['full_name'])) {
        $parts[] = $row['full_name'];
    }
    if (!empty($row['program'])) {
        $parts[] = $row['program'];
    }
    if (!empty($row['company'])) {
        $parts[] = $row['company'];
    }
    if (!empty($row['year'])) {
        $parts[] = $row['year'];
    }
    if (!empty($row['campus'])) {
        $parts[] = $row['campus'];
    }
    $label = implode(' • ', array_filter($parts));
    $suggestions[] = [
        'id' => (int)$row['id'],
        'label' => $label,
        'name' => $row['full_name'] ?? ''
    ];
}

echo json_encode(['suggestions' => $suggestions]);
exit;
