<?php
/**
 * One-time script: ensure itcp has all columns used by alumni registration
 * so that registration can save (and profile can display) all user-entered data.
 * Run once in browser or CLI. Safe to run multiple times (skips columns that already exist).
 */
require_once __DIR__ . '/db_config.php';

$conn = getDBConnection();
if (!$conn) {
    die('Database connection failed.');
}

$columns_to_add = [
    'photo' => 'VARCHAR(255) NULL DEFAULT NULL',
    'lastname' => 'VARCHAR(100) NULL DEFAULT NULL',
    'firstname' => 'VARCHAR(100) NULL DEFAULT NULL',
    'middlename' => 'VARCHAR(100) NULL DEFAULT NULL',
    'name_ext' => 'VARCHAR(20) NULL DEFAULT NULL',
    'birthday' => 'DATE NULL DEFAULT NULL',
    'age' => 'INT NULL DEFAULT NULL',
    'gender' => 'VARCHAR(50) NULL DEFAULT NULL',
    'civil_status' => 'VARCHAR(50) NULL DEFAULT NULL',
    'religion' => 'VARCHAR(100) NULL DEFAULT NULL',
    'nationality' => 'VARCHAR(100) NULL DEFAULT NULL',
    'email' => 'VARCHAR(150) NULL DEFAULT NULL',
    'address' => 'TEXT NULL',
    'personal_contact' => 'VARCHAR(50) NULL DEFAULT NULL',
    'emergency_contact' => 'VARCHAR(50) NULL DEFAULT NULL',
    'student_number' => 'VARCHAR(50) NULL DEFAULT NULL',
    'program' => 'VARCHAR(150) NULL DEFAULT NULL',
    'campus' => 'VARCHAR(100) NULL DEFAULT NULL',
    'month_graduated' => 'VARCHAR(20) NULL DEFAULT NULL',
    'year_graduated' => 'VARCHAR(10) NULL DEFAULT NULL',
    'post_grad' => 'VARCHAR(255) NULL DEFAULT NULL',
    'licensure_exam' => 'VARCHAR(255) NULL DEFAULT NULL',
    'club_involvement' => 'TEXT NULL',
    'employment_status' => 'VARCHAR(50) NULL DEFAULT NULL',
    'company' => 'VARCHAR(150) NULL DEFAULT NULL',
    'industry' => 'VARCHAR(100) NULL DEFAULT NULL',
    'position' => 'VARCHAR(150) NULL DEFAULT NULL',
    'employment_history' => 'TEXT NULL',
    'previous_role' => 'VARCHAR(150) NULL DEFAULT NULL',
    'length_of_service' => 'VARCHAR(50) NULL DEFAULT NULL',
    'college' => 'VARCHAR(150) NULL DEFAULT NULL',
    'months_to_get_job' => 'VARCHAR(100) NULL DEFAULT NULL',
    'job_aligned' => 'VARCHAR(100) NULL DEFAULT NULL',
    'college_prepared' => 'VARCHAR(255) NULL DEFAULT NULL',
    'important_soft_skill' => 'VARCHAR(255) NULL DEFAULT NULL',
    'proud_alumni' => 'VARCHAR(255) NULL DEFAULT NULL',
];

$existing = [];
$res = @$conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'itcp'");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $existing[strtolower($row['COLUMN_NAME'])] = true;
    }
    $res->close();
}

$added = [];
foreach ($columns_to_add as $col => $def) {
    if (isset($existing[strtolower($col)])) {
        continue;
    }
    $safe_col = '`' . $conn->real_escape_string($col) . '`';
    $sql = "ALTER TABLE itcp ADD COLUMN $safe_col $def";
    if (@$conn->query($sql)) {
        $added[] = $col;
    }
}

$conn->close();

header('Content-Type: text/plain; charset=utf-8');
if (empty($added)) {
    echo "itcp already has all registration columns. No changes made.\n";
} else {
    echo "Added " . count($added) . " column(s) to itcp: " . implode(', ', $added) . "\n";
    echo "New registrations will save all details. Existing users can update their profile to fill in data.\n";
}
