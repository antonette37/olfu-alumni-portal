<?php
/**
 * One-time script: fill itcp with registration data from alumni_registration
 * so existing users see their registration details on the profile automatically.
 * Run once after ensure_itcp_registration_columns.php.
 * Safe to run multiple times (only updates empty fields).
 */
require_once __DIR__ . '/db_config.php';

$conn = getDBConnection();
if (!$conn) {
    die('Database connection failed.');
}

$res = $conn->query("SELECT id, student_number, name, course, grad_year FROM alumni_registration ORDER BY created_at DESC, id DESC");
if (!$res) {
    $conn->close();
    die('Query failed.');
}

$updated_by_sn = 0;
$updated_by_name = 0;
$skipped = 0;

while ($ar = $res->fetch_assoc()) {
    $sn = trim($ar['student_number'] ?? '');
    $sn_norm = preg_replace('/[\s\-]+/', '', $sn);
    $name = trim($ar['name'] ?? '');
    $course = trim($ar['course'] ?? '');
    $grad_year = $ar['grad_year'] !== null && (string)$ar['grad_year'] !== '' ? (string)$ar['grad_year'] : null;

    $name_parts = $name !== '' ? preg_split('/\s+/', $name, 2) : [];
    $firstname = $name_parts[0] ?? '';
    $lastname = $name_parts[1] ?? '';

    $itcp_id = null;

    if ($sn !== '') {
        $stmt = $conn->prepare("SELECT id FROM itcp WHERE student_number = ? OR REPLACE(REPLACE(TRIM(COALESCE(student_number,'')), '-', ''), ' ', '') = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('ss', $sn, $sn_norm);
            $stmt->execute();
            $r = $stmt->get_result();
            if ($r && $r->num_rows > 0) {
                $itcp_id = (int)$r->fetch_assoc()['id'];
            }
            $stmt->close();
        }
    }

    if ($itcp_id === null && $firstname !== '' && $lastname !== '') {
        $stmt = $conn->prepare("SELECT id FROM itcp WHERE TRIM(COALESCE(firstname,'')) = ? AND TRIM(COALESCE(lastname,'')) = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('ss', $firstname, $lastname);
            $stmt->execute();
            $r = $stmt->get_result();
            if ($r && $r->num_rows > 0) {
                $itcp_id = (int)$r->fetch_assoc()['id'];
            }
            $stmt->close();
        }
    }

    if ($itcp_id === null) {
        $skipped++;
        continue;
    }

    $sets = [];
    $params = [];
    $types = '';

    $check = $conn->prepare("SELECT student_number, program, year_graduated, firstname, lastname, college FROM itcp WHERE id = ?");
    if (!$check) continue;
    $check->bind_param('i', $itcp_id);
    $check->execute();
    $row = $check->get_result()->fetch_assoc();
    $check->close();
    if (!$row) continue;

    $row = array_change_key_case($row, CASE_LOWER);
    if ((trim($row['student_number'] ?? '') === '') && $sn !== '') {
        $sets[] = 'student_number = ?';
        $params[] = $sn;
        $types .= 's';
    }
    if ((trim($row['program'] ?? '') === '') && $course !== '') {
        $sets[] = 'program = ?';
        $params[] = $course;
        $types .= 's';
    }
    if ((trim($row['year_graduated'] ?? '') === '') && $grad_year !== '') {
        $sets[] = 'year_graduated = ?';
        $params[] = $grad_year;
        $types .= 's';
    }
    if ((trim($row['firstname'] ?? '') === '') && $firstname !== '') {
        $sets[] = 'firstname = ?';
        $params[] = $firstname;
        $types .= 's';
    }
    if ((trim($row['lastname'] ?? '') === '') && $lastname !== '') {
        $sets[] = 'lastname = ?';
        $params[] = $lastname;
        $types .= 's';
    }

    if (empty($sets)) {
        $skipped++;
        continue;
    }

    $params[] = $itcp_id;
    $types .= 'i';
    $sql = "UPDATE itcp SET " . implode(', ', $sets) . " WHERE id = ?";
    $up = $conn->prepare($sql);
    if ($up) {
        $up->bind_param($types, ...$params);
        if ($up->execute()) {
            if ($sn !== '') $updated_by_sn++;
            else $updated_by_name++;
        }
        $up->close();
    }
}

$res->close();
$conn->close();

header('Content-Type: text/plain; charset=utf-8');
echo "Backfill complete.\n";
echo "Updated itcp from alumni_registration: " . ($updated_by_sn + $updated_by_name) . " user(s) (by student_number: $updated_by_sn, by name: $updated_by_name).\n";
echo "Skipped (no match or already filled): $skipped.\n";
echo "Profile page will now show registration data for these users.\n";
echo "New registrations will continue to save all details to itcp automatically.\n";
