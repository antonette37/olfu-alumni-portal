<?php
/**
 * Load and persist alumni profile data (itcp) for view/edit pages.
 */
declare(strict_types=1);

require_once __DIR__ . '/mysqli_compat.php';
require_once __DIR__ . '/alumni_itcp_registration_merge.php';

/**
 * Columns used by registration and profile edit (canonical lowercase keys).
 *
 * @return list<string>
 */
function alumni_profile_canonical_fields(): array
{
    return [
        'id', 'photo', 'firstname', 'lastname', 'middlename', 'name_ext', 'birthday', 'age', 'gender', 'civil_status',
        'religion', 'nationality', 'email', 'address', 'personal_contact', 'emergency_contact', 'student_number',
        'program', 'campus', 'college', 'month_graduated', 'year_graduated', 'post_grad', 'licensure_exam', 'club_involvement',
        'employment_status', 'company', 'industry', 'position', 'employment_history', 'previous_role', 'length_of_service',
        'months_to_get_job', 'job_aligned', 'college_prepared', 'important_soft_skill', 'proud_alumni',
        'skills', 'signature_path', 'employment_private', 'data_privacy_consent_at', 'status',
    ];
}

/**
 * Ensure itcp has registration/profile columns (safe to call repeatedly).
 */
function alumni_ensure_itcp_registration_columns(mysqli $conn): void
{
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
        'skills' => 'TEXT NULL',
        'signature_path' => 'VARCHAR(255) NULL DEFAULT NULL',
        'employment_private' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'data_privacy_consent_at' => 'DATETIME NULL DEFAULT NULL',
    ];

    $existing = [];
    $res = @$conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'itcp'");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $existing[strtolower((string) $row['COLUMN_NAME'])] = true;
        }
        $res->close();
    }

    foreach ($columns_to_add as $col => $def) {
        if (isset($existing[strtolower($col)])) {
            continue;
        }
        $safe_col = '`' . $conn->real_escape_string($col) . '`';
        @$conn->query("ALTER TABLE itcp ADD COLUMN $safe_col $def");
    }
}

/**
 * Map logical field names to actual DB column names on itcp.
 *
 * @return array<string, string> canonical_key => db_column_name
 */
function alumni_itcp_column_map(mysqli $conn): array
{
    $existing_cols = [];
    $rc = @$conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'itcp' ORDER BY ORDINAL_POSITION");
    if ($rc) {
        while ($r = $rc->fetch_assoc()) {
            $existing_cols[] = (string) $r['COLUMN_NAME'];
        }
        $rc->close();
    }

    $normalize = static function (string $name): string {
        return strtolower(str_replace('_', '', $name));
    };

    $map = [];
    foreach (alumni_profile_canonical_fields() as $col) {
        $col_lower = strtolower($col);
        $col_norm = $normalize($col);
        foreach ($existing_cols as $db_col) {
            $db_norm = $normalize($db_col);
            if (strtolower($db_col) === $col_lower || $db_norm === $col_norm) {
                $map[$col_lower] = $db_col;
                break;
            }
        }
    }

    return $map;
}

/**
 * Load one alumni row for profile display/edit (lowercase keys).
 */
function alumni_load_itcp_user(mysqli $conn, int $userId): ?array
{
    if ($userId < 1) {
        return null;
    }

    alumni_ensure_itcp_registration_columns($conn);

    $user = null;
    $stmt = $conn->prepare('SELECT * FROM itcp WHERE id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        if ($stmt->execute()) {
            $user = mysqli_stmt_fetch_assoc_compat($stmt);
        }
        $stmt->close();
    }

    if (!is_array($user) || $user === []) {
        return null;
    }

    $user = array_change_key_case($user, CASE_LOWER);
    try {
        alumni_merge_registration_into_user($conn, $userId, $user, true);
    } catch (Throwable $e) {
        error_log('alumni_merge_registration_into_user: ' . $e->getMessage());
    }

    return $user;
}

/**
 * Safe string for form value attributes.
 */
function alumni_profile_form_val(?array $user, string $key, string $default = ''): string
{
    if (!is_array($user)) {
        return htmlspecialchars($default, ENT_QUOTES, 'UTF-8');
    }
    $kl = strtolower($key);
    foreach ($user as $k => $v) {
        if (strtolower((string) $k) === $kl) {
            if ($v === null) {
                return '';
            }
            $s = trim((string) $v);
            if ($key === 'birthday' && preg_match('/^0000-00-00/', $s)) {
                return '';
            }
            return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        }
    }

    return htmlspecialchars($default, ENT_QUOTES, 'UTF-8');
}

/**
 * Whether a stored value matches a radio option.
 */
function alumni_profile_radio_checked(?array $user, string $key, string $option): bool
{
    if (!is_array($user)) {
        return false;
    }
    $kl = strtolower($key);
    $stored = '';
    foreach ($user as $k => $v) {
        if (strtolower((string) $k) === $kl) {
            $stored = trim((string) $v);
            break;
        }
    }

    return strcasecmp($stored, $option) === 0;
}

/**
 * Fields used for profile completion % (My Profile + Edit Profile must match).
 *
 * @return list<string>
 */
function alumni_profile_completion_field_names(): array
{
    return [
        'photo', 'firstname', 'lastname', 'middlename', 'email', 'personal_contact',
        'address', 'birthday', 'age', 'gender', 'nationality', 'program',
        'year_graduated', 'month_graduated', 'company', 'position',
        'employment_status', 'industry', 'employment_history', 'previous_role', 'length_of_service',
    ];
}

/**
 * Raw value from user row (case-insensitive key).
 */
function alumni_profile_user_val(?array $user, string $key): ?string
{
    if (!is_array($user)) {
        return null;
    }
    $kl = strtolower($key);
    foreach ($user as $k => $v) {
        if (strtolower((string) $k) === $kl) {
            if ($v === null) {
                return null;
            }
            $s = trim((string) $v);
            if ($key === 'birthday' && preg_match('/^0000-00-00/', $s)) {
                return '';
            }

            return $s;
        }
    }

    return null;
}

/**
 * Whether one completion field is filled (same rules as al_profile.php).
 */
function alumni_profile_field_filled(?array $user, string $field): bool
{
    $val = alumni_profile_user_val($user, $field);
    if ($val === null || $val === '') {
        return false;
    }

    return trim($val) !== '';
}

/**
 * Profile completion 0–100 (same formula as al_profile.php).
 */
function alumni_profile_completion_percent(?array $user): int
{
    $fields = alumni_profile_completion_field_names();
    $filled = 0;
    foreach ($fields as $field) {
        if (alumni_profile_field_filled($user, $field)) {
            $filled++;
        }
    }

    return count($fields) > 0 ? (int) round(($filled / count($fields)) * 100) : 0;
}

/**
 * Persist profile edit form — only updates columns that exist on itcp.
 *
 * @param array<string,bool> $itcpCols lowercase column name => true
 * @return array{ok:bool,error?:string}
 */
function alumni_profile_save_from_post(
    mysqli $conn,
    int $userId,
    array $user,
    array $post,
    array $itcpCols,
    string $photoFilename,
    string $signatureRel,
    string $ageForDb
): array {
    alumni_ensure_itcp_registration_columns($conn);

    $licensure_save = trim((string) ($post['licensure_exam'] ?? ''));
    $licensure_radio = trim((string) ($post['licensure_passed'] ?? ''));
    if ($licensure_save === '' && $licensure_radio !== '') {
        $licensure_save = $licensure_radio;
    }

    $values = [
        'firstname' => (string) ($post['firstname'] ?? ''),
        'lastname' => (string) ($post['lastname'] ?? ''),
        'middlename' => (string) ($post['middlename'] ?? ''),
        'name_ext' => (string) ($post['name_ext'] ?? ''),
        'birthday' => (string) ($post['birthday'] ?? ''),
        'age' => $ageForDb,
        'gender' => (string) ($post['gender'] ?? ''),
        'civil_status' => (string) ($post['civil_status'] ?? ''),
        'religion' => (string) ($post['religion'] ?? ''),
        'nationality' => (string) ($post['nationality'] ?? ''),
        'email' => (string) ($post['email'] ?? ''),
        'address' => (string) ($post['address'] ?? ''),
        'personal_contact' => (string) ($post['personal_contact'] ?? ''),
        'emergency_contact' => (string) ($post['emergency_contact'] ?? ''),
        'student_number' => (string) ($post['student_number'] ?? ''),
        'program' => (string) ($post['program'] ?? ''),
        'campus' => (string) ($post['campus'] ?? ''),
        'college' => (string) ($post['college'] ?? ''),
        'month_graduated' => (string) ($post['month_graduated'] ?? ''),
        'year_graduated' => (string) ($post['year_graduated'] ?? ''),
        'post_grad' => (string) ($post['post_grad'] ?? ''),
        'licensure_exam' => $licensure_save,
        'club_involvement' => (string) ($post['club_involvement'] ?? ''),
        'employment_status' => (string) ($post['employment_status'] ?? ''),
        'company' => (string) ($post['company'] ?? ''),
        'industry' => (string) ($post['industry'] ?? ''),
        'position' => (string) ($post['position'] ?? ''),
        'employment_history' => (string) ($post['employment_history'] ?? ''),
        'previous_role' => (string) ($post['previous_role'] ?? ''),
        'length_of_service' => (string) ($post['length_of_service'] ?? ''),
        'months_to_get_job' => (string) ($post['months_to_get_job'] ?? ''),
        'job_aligned' => (string) ($post['job_aligned'] ?? ''),
        'college_prepared' => (string) ($post['college_prepared'] ?? ''),
        'important_soft_skill' => (string) ($post['important_soft_skill'] ?? ''),
        'proud_alumni' => (string) ($post['proud_alumni'] ?? ''),
        'photo' => $photoFilename,
        'signature_path' => $signatureRel,
    ];

    $sets = [];
    $params = [];
    $types = '';

    foreach ($values as $col => $val) {
        if (empty($itcpCols[strtolower($col)])) {
            continue;
        }
        $sets[] = '`' . str_replace('`', '``', $col) . '` = ?';
        $params[] = $val;
        $types .= 's';
    }

    if (!empty($itcpCols['data_privacy_consent_at'])) {
        $consent_flag = !empty($post['data_privacy_consent']) ? 1 : 0;
        $sets[] = 'data_privacy_consent_at = IF(? = 1, NOW(), data_privacy_consent_at)';
        $params[] = $consent_flag;
        $types .= 'i';
    }

    if ($sets === []) {
        return ['ok' => false, 'error' => 'No profile columns available to update.'];
    }

    $sql = 'UPDATE itcp SET ' . implode(', ', $sets) . ' WHERE id = ?';
    $params[] = $userId;
    $types .= 'i';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return ['ok' => false, 'error' => 'Database error: ' . $conn->error];
    }

    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        return ['ok' => false, 'error' => 'Error updating profile: ' . $err];
    }
    $stmt->close();

    if (!empty($itcpCols['skills'])) {
        $sk = (string) ($post['skills'] ?? '');
        $st2 = $conn->prepare('UPDATE itcp SET skills = ? WHERE id = ?');
        if ($st2) {
            $st2->bind_param('si', $sk, $userId);
            @$st2->execute();
            $st2->close();
        }
    }
    if (!empty($itcpCols['employment_private'])) {
        $ep = !empty($post['employment_private']) ? 1 : 0;
        $st3 = $conn->prepare('UPDATE itcp SET employment_private = ? WHERE id = ?');
        if ($st3) {
            $st3->bind_param('ii', $ep, $userId);
            @$st3->execute();
            $st3->close();
        }
    }

    return ['ok' => true];
}
