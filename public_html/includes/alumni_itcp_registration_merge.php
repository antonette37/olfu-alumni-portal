<?php
/**
 * When itcp is missing data that exists in alumni_registration, merge into $user
 * and optionally persist those fields to itcp (keeps profile + edit forms in sync).
 *
 * @param mysqli $conn
 * @param int    $user_id
 * @param array  $user    Passed by reference; normalized to lowercase keys.
 * @param bool   $persist When true, UPDATE itcp for any fields filled from registration.
 */
if (is_file(__DIR__ . '/mysqli_compat.php')) {
    require_once __DIR__ . '/mysqli_compat.php';
}

function alumni_merge_registration_into_user(mysqli $conn, int $user_id, array &$user, bool $persist = true): void
{
	if ($user === []) {
		return;
	}
	$user = array_change_key_case($user, CASE_LOWER);

	$chk = @$conn->query("SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'alumni_registration'");
	if (!$chk) {
		return;
	}
	$row = $chk->fetch_assoc();
	$chk->close();
	if (!$row || (int) ($row['c'] ?? 0) < 1) {
		return;
	}

	$sn = trim((string) ($user['student_number'] ?? ''));
	$ar_row = null;
	if ($sn !== '') {
		$ar_sql = 'SELECT student_number, name, course, grad_year FROM alumni_registration WHERE student_number = ? OR REPLACE(REPLACE(TRIM(student_number), \'-\', \'\'), \' \', \'\') = ? ORDER BY created_at DESC, id DESC LIMIT 1';
		$ar_stmt = $conn->prepare($ar_sql);
		if ($ar_stmt) {
			$sn_norm = preg_replace('/[\s\-]+/', '', $sn);
			$ar_stmt->bind_param('ss', $sn, $sn_norm);
			if ($ar_stmt->execute()) {
				$ar_result = olfu_stmt_get_result($ar_stmt);
				if ($ar_result && method_exists($ar_result, 'fetch_assoc')) {
					$ar_row = $ar_result->fetch_assoc();
				}
			}
			$ar_stmt->close();
		}
	}
	if (!$ar_row && (trim((string) ($user['firstname'] ?? '')) !== '' || trim((string) ($user['lastname'] ?? '')) !== '')) {
		$full = trim(trim($user['firstname'] ?? '') . ' ' . trim($user['lastname'] ?? ''));
		if ($full !== '') {
			$ar_sql2 = 'SELECT student_number, name, course, grad_year FROM alumni_registration WHERE TRIM(name) = ? ORDER BY created_at DESC, id DESC LIMIT 1';
			$ar_stmt2 = $conn->prepare($ar_sql2);
			if ($ar_stmt2) {
				$ar_stmt2->bind_param('s', $full);
				if ($ar_stmt2->execute()) {
					$ar_res2 = olfu_stmt_get_result($ar_stmt2);
					if ($ar_res2 && method_exists($ar_res2, 'fetch_assoc')) {
						$ar_row = $ar_res2->fetch_assoc();
					}
				}
				$ar_stmt2->close();
			}
		}
	}
	if (!$ar_row) {
		return;
	}

	$updates = [];
	$up_params = [];
	$up_types = '';
	if (trim((string) ($user['student_number'] ?? '')) === '' && trim($ar_row['student_number'] ?? '') !== '') {
		$user['student_number'] = trim($ar_row['student_number']);
		$updates[] = 'student_number = ?';
		$up_params[] = $user['student_number'];
		$up_types .= 's';
	}
	if (trim((string) ($user['program'] ?? '')) === '' && trim($ar_row['course'] ?? '') !== '') {
		$user['program'] = trim($ar_row['course']);
		$updates[] = 'program = ?';
		$up_params[] = $user['program'];
		$up_types .= 's';
	}
	if (trim((string) ($user['year_graduated'] ?? '')) === '' && isset($ar_row['grad_year']) && $ar_row['grad_year'] !== null && (string) $ar_row['grad_year'] !== '') {
		$user['year_graduated'] = (string) $ar_row['grad_year'];
		$updates[] = 'year_graduated = ?';
		$up_params[] = $user['year_graduated'];
		$up_types .= 's';
	}
	$full_name_ar = trim($ar_row['name'] ?? '');
	if ($full_name_ar !== '' && trim((string) ($user['firstname'] ?? '')) === '' && trim((string) ($user['lastname'] ?? '')) === '') {
		$parts = preg_split('/\s+/', $full_name_ar, 2);
		$user['firstname'] = $parts[0] ?? $full_name_ar;
		$user['lastname'] = $parts[1] ?? '';
		$updates[] = 'firstname = ?';
		$up_params[] = $user['firstname'];
		$up_types .= 's';
		$updates[] = 'lastname = ?';
		$up_params[] = $user['lastname'];
		$up_types .= 's';
	}
	if ($persist && !empty($updates)) {
		$up_sql = 'UPDATE itcp SET ' . implode(', ', $updates) . ' WHERE id = ?';
		$up_stmt = $conn->prepare($up_sql);
		if ($up_stmt) {
			$up_params[] = $user_id;
			$up_types .= 'i';
			$up_stmt->bind_param($up_types, ...$up_params);
			@$up_stmt->execute();
			$up_stmt->close();
		}
	}
}
