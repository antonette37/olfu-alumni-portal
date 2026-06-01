<?php
/**
 * Upload Registrar CSV masterlist (student_id, full_name, date_of_birth, program, campus_code) and
 * cross-check pending registrants.
 */
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: al_login.php');
    exit;
}

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/includes/cps_alumni_lib.php';

$conn = getDBConnection();
cps_ensure_schema($conn);

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['csv']['tmp_name'])) {
    $f = $_FILES['csv'];
    if ($f['error'] === UPLOAD_ERR_OK && is_uploaded_file($f['tmp_name'])) {
        $batch = 'import_' . date('Ymd_His');
        $fh = fopen($f['tmp_name'], 'r');
        if ($fh) {
            $header = fgetcsv($fh);
            $map = [];
            if (is_array($header)) {
                foreach ($header as $i => $h) {
                    $map[strtolower(trim((string)$h))] = $i;
                }
            }
            $sid_k = $map['student_id'] ?? $map['student number'] ?? $map['student_no'] ?? null;
            $name_k = $map['full_name'] ?? $map['name'] ?? $map['student_name'] ?? null;
            $dob_k = $map['date_of_birth'] ?? $map['birthday'] ?? $map['dob'] ?? null;
            $prog_k = $map['program'] ?? $map['course'] ?? null;
            $camp_k = $map['campus_code'] ?? $map['campus'] ?? null;

            $ins = $conn->prepare('INSERT INTO registrar_masterlist (student_id, full_name, date_of_birth, program, campus_code, import_batch) VALUES (?, ?, ?, ?, ?, ?)');
            $n = 0;
            while (($row = fgetcsv($fh)) !== false) {
                $sid = $sid_k !== null ? trim((string)($row[$sid_k] ?? '')) : trim((string)($row[0] ?? ''));
                $name = $name_k !== null ? trim((string)($row[$name_k] ?? '')) : trim((string)($row[1] ?? ''));
                if ($name === '') {
                    continue;
                }
                $dob_raw = $dob_k !== null ? trim((string)($row[$dob_k] ?? '')) : '';
                $dob = null;
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob_raw)) {
                    $dob = $dob_raw;
                } elseif (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $dob_raw, $m)) {
                    $dob = sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[1], (int)$m[2]);
                }
                $prog = $prog_k !== null ? substr(trim((string)($row[$prog_k] ?? '')), 0, 255) : null;
                $camp = $camp_k !== null ? substr(trim((string)($row[$camp_k] ?? '')), 0, 32) : null;
                if ($ins) {
                    $ins->bind_param('ssssss', $sid, $name, $dob, $prog, $camp, $batch);
                    if ($ins->execute()) {
                        $n++;
                    }
                }
            }
            fclose($fh);
            $ins->close();
            $message = "Imported {$n} masterlist row(s) batch {$batch}.";
        }
    }
}

$pending = [];
$q = $conn->query("SELECT p.id, p.student_id, p.itcp_id, p.date_of_birth, p.campus_code,
    i.firstname, i.lastname, i.middlename, i.program
    FROM pending_registrations p
    JOIN itcp i ON i.id = p.itcp_id
    WHERE p.status = 'pending'
    ORDER BY p.submitted_at DESC
    LIMIT 50");
if ($q) {
    while ($r = $q->fetch_assoc()) {
        $fn = trim(($r['firstname'] ?? '') . ' ' . ($r['middlename'] ?? '') . ' ' . ($r['lastname'] ?? ''));
        $r['full_name'] = $fn;
        $dob = $r['date_of_birth'] ?? '';
        $matches = [];
        $stmt = $conn->prepare("SELECT id, full_name, date_of_birth, program FROM registrar_masterlist WHERE (student_id = ? AND student_id != '') OR (full_name LIKE ?) LIMIT 8");
        if ($stmt) {
            $like = '%' . $fn . '%';
            $sid = $r['student_id'] ?? '';
            $stmt->bind_param('ss', $sid, $like);
            $stmt->execute();
            $mr = $stmt->get_result();
            while ($m = $mr->fetch_assoc()) {
                $matches[] = $m;
            }
            $stmt->close();
        }
        $r['masterlist_matches'] = $matches;
        $pending[] = $r;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Registrar masterlist · Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6">
    <?php include __DIR__ . '/ad_header_universal.php'; ?>
    <div class="max-w-5xl mx-auto">
        <h1 class="text-2xl font-bold text-gray-800 mb-4">Registrar CSV masterlist</h1>
        <?php if ($message): ?>
            <p class="mb-4 text-green-700"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data" class="bg-white p-6 rounded-lg shadow mb-8">
            <p class="text-sm text-gray-600 mb-3">Upload a CSV with headers such as: <code>student_id</code>, <code>full_name</code>, <code>date_of_birth</code> (YYYY-MM-DD or M/D/YYYY), <code>program</code>, <code>campus_code</code>.</p>
            <input type="file" name="csv" accept=".csv,text/csv" required class="block mb-3">
            <button type="submit" class="px-4 py-2 bg-green-700 text-white rounded-md hover:bg-green-800">Import</button>
        </form>

        <h2 class="text-xl font-semibold mb-3">Pending registrants vs masterlist</h2>
        <p class="text-sm text-gray-600 mb-4">Name and DOB side-by-side with possible masterlist matches (highlight duplicates).</p>
        <?php foreach ($pending as $p): ?>
            <div class="bg-white rounded-lg shadow p-4 mb-4 border-l-4 border-amber-400">
                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs uppercase text-gray-500">Registrant</p>
                        <p class="font-semibold"><?php echo htmlspecialchars($p['full_name']); ?></p>
                        <p class="text-sm">DOB: <?php echo htmlspecialchars($p['date_of_birth'] ?? '—'); ?></p>
                        <p class="text-sm">Student ID: <?php echo htmlspecialchars($p['student_id']); ?></p>
                        <p class="text-sm">Program: <?php echo htmlspecialchars($p['program'] ?? ''); ?></p>
                        <p class="text-sm">Campus: <?php echo htmlspecialchars($p['campus_code'] ?? ''); ?></p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-gray-500">Masterlist matches</p>
                        <?php if (empty($p['masterlist_matches'])): ?>
                            <p class="text-sm text-gray-500">No rows matched (check import).</p>
                        <?php else: ?>
                            <ul class="text-sm space-y-2">
                                <?php foreach ($p['masterlist_matches'] as $m): ?>
                                    <li class="p-2 rounded <?php echo (!empty($p['date_of_birth']) && $m['date_of_birth'] === $p['date_of_birth']) ? 'bg-green-50 border border-green-200' : 'bg-gray-50'; ?>">
                                        <strong><?php echo htmlspecialchars($m['full_name']); ?></strong><br>
                                        DOB: <?php echo htmlspecialchars($m['date_of_birth'] ?? '—'); ?><br>
                                        <?php echo htmlspecialchars($m['program'] ?? ''); ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
                <p class="mt-3 text-sm text-gray-500">Approve in <a href="ad_user_management.php?status=pending" class="text-green-700 underline">User management</a>.</p>
            </div>
        <?php endforeach; ?>
        <?php if (empty($pending)): ?>
            <p class="text-gray-600">No pending registrations in queue.</p>
        <?php endif; ?>
    </div>
</body>
</html>
