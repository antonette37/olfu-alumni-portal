<?php
require_once 'db_config.php';
$conn = getDBConnection();
session_start();
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) { header("Location: al_login.php"); exit(); }

@$conn->query("ALTER TABLE contact_messages ADD COLUMN status ENUM('New','In Progress','Resolved','Spam') NOT NULL DEFAULT 'New'");
@$conn->query("ALTER TABLE contact_messages ADD COLUMN is_read TINYINT(1) NOT NULL DEFAULT 0");
@$conn->query("ALTER TABLE contact_messages ADD COLUMN subject VARCHAR(255) NULL");
@$conn->query("UPDATE contact_messages SET status='New' WHERE status IS NULL OR TRIM(status)=''");

$limit = 15;
$tab = $_GET['tab'] ?? 'all';
$tq = trim($_GET['tq'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$where = [];
if ($tab === 'open') $where[] = "status IN ('New','In Progress')";
if ($tab === 'in_progress') $where[] = "status='In Progress'";
if ($tab === 'resolved') $where[] = "status='Resolved'";
if ($tq !== '') { $e = $conn->real_escape_string($tq); $where[] = "(CAST(id AS CHAR) LIKE '%$e%' OR name LIKE '%$e%' OR email LIKE '%$e%' OR subject LIKE '%$e%' OR message LIKE '%$e%')"; }
$whereSql = count($where) ? "WHERE " . implode(" AND ", $where) : "";

$kpi_total = (int)($conn->query("SELECT COUNT(*) c FROM contact_messages")->fetch_assoc()['c'] ?? 0);
$kpi_open = (int)($conn->query("SELECT COUNT(*) c FROM contact_messages WHERE status IN ('New','In Progress')")->fetch_assoc()['c'] ?? 0);
$kpi_sent = (int)($conn->query("SELECT COUNT(*) c FROM contact_messages WHERE status='Resolved'")->fetch_assoc()['c'] ?? 0);
$kpi_rate = $kpi_total > 0 ? round(($kpi_sent / $kpi_total) * 100) : 0;
$count_tab_all = (int)($conn->query("SELECT COUNT(*) c FROM contact_messages")->fetch_assoc()['c'] ?? 0);
$count_tab_open = (int)($conn->query("SELECT COUNT(*) c FROM contact_messages WHERE status IN ('New','In Progress')")->fetch_assoc()['c'] ?? 0);
$count_tab_inprog = (int)($conn->query("SELECT COUNT(*) c FROM contact_messages WHERE status='In Progress'")->fetch_assoc()['c'] ?? 0);
$count_tab_res = (int)($conn->query("SELECT COUNT(*) c FROM contact_messages WHERE status='Resolved'")->fetch_assoc()['c'] ?? 0);

$totalRows = (int)($conn->query("SELECT COUNT(*) c FROM contact_messages $whereSql")->fetch_assoc()['c'] ?? 0);
$totalPages = max(1, (int)ceil(max(1, $totalRows) / $limit));
$rows = [];
$res = $conn->query("SELECT id,name,email,subject,message,status,is_read,submitted_at FROM contact_messages $whereSql ORDER BY submitted_at DESC LIMIT $limit OFFSET $offset");
if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta content="width=device-width, initial-scale=1" name="viewport" />
    <title>Communication & Support - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --cr:#8b0000;--cr-dk:#600000;--cr-lt:#b91c1c;--cr-pale:#fef2f2;--border:#e8dada;--surface:#fff;--bg:#faf7f7;--muted:#7a6a6a;--ink:#1a0a0a;--shadow-sm:0 1px 4px rgba(139,0,0,.07),0 2px 12px rgba(139,0,0,.05);--r:14px; }
        body { background: var(--bg); font-family: 'Sora', sans-serif; color: var(--ink); }
        .page-title h1 { font-size: 1.5rem; font-weight: 700; color: var(--ink); line-height: 1.2; }
        .page-title p  { font-size: 0.82rem; color: var(--muted); margin-top: 2px; }
        .stat-pill { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 12px; padding: .75rem 1.25rem; color: var(--ink); }
        .admin-card { background: var(--surface); border-radius: var(--r); border: 1px solid var(--border); box-shadow: var(--shadow-sm); overflow: hidden; }
        .status-tab { padding: .55rem 1rem; font-size: .78rem; font-weight: 600; color: var(--muted); border:1.5px solid var(--border); border-radius:100px; text-decoration: none; display:flex;justify-content:center;align-items:center;gap:.4rem; background:var(--surface); margin:.35rem; }
        .status-tab.active { color: #fff; background: var(--cr); border-color: var(--cr); }
        .status-tab:hover { color:var(--cr); border-color:var(--cr); background:var(--cr-pale); }
        .search-input { border: 1.5px solid #d1d5db; border-radius: 8px; padding:.45rem .875rem .45rem 2.5rem; }
        .search-input:focus { outline:none; border-color:#7a0000; box-shadow:0 0 0 3px rgba(122,0,0,.1); }
        .tickets-table tbody tr:hover { background:#fdf2f2; }
        .pagination-link { padding: .4rem .7rem; border:1px solid #e5e7eb; border-radius:7px; font-size:.8rem; font-weight:600; color:#374151; text-decoration:none; }
        .pagination-link.active { background:#7a0000; color:white; border-color:#7a0000; }
    </style>
</head>
<body>
<?php include 'ad_header_universal.php'; ?>
<?php include 'ad_sidebar_universal.php'; ?>
<main class="pt-24 ml-16 min-h-screen relative z-0">
    <div class="max-w-7xl mx-auto px-4 mb-5">
        <div class="page-title">
            <h1>Communication & Support</h1>
            <p>Manage alumni inquiries and support tickets</p>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-4">
            <div class="stat-pill"><div class="text-2xl font-bold"><?= $kpi_total ?></div><div class="text-xs text-gray-500">Total Messages</div></div>
            <div class="stat-pill"><div class="text-2xl font-bold"><?= $kpi_open ?></div><div class="text-xs text-gray-500">Open Tickets</div></div>
            <div class="stat-pill"><div class="text-2xl font-bold"><?= $kpi_sent ?></div><div class="text-xs text-gray-500">Resolved</div></div>
            <div class="stat-pill"><div class="text-2xl font-bold"><?= $kpi_rate ?>%</div><div class="text-xs text-gray-500">Response Rate</div></div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 pb-10">
        <div class="admin-card">
            <div class="p-4 border-b bg-gray-50">
                <form class="flex gap-2 items-center">
                    <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                    <div class="relative flex-1">
                        <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                        <input class="search-input w-full" name="tq" value="<?= htmlspecialchars($tq) ?>" placeholder="Search by ticket ID, name, email, or message...">
                    </div>
                    <button class="px-4 py-2 text-white rounded-lg text-sm font-semibold" style="background:#7a0000;">Search</button>
                </form>
            </div>
            <div class="grid grid-cols-4 border-b">
                <a href="?tab=all" class="status-tab <?= $tab==='all'?'active':'' ?>">All <span class="text-xs">(<?= $count_tab_all ?>)</span></a>
                <a href="?tab=open" class="status-tab <?= $tab==='open'?'active':'' ?>">Open <span class="text-xs">(<?= $count_tab_open ?>)</span></a>
                <a href="?tab=in_progress" class="status-tab <?= $tab==='in_progress'?'active':'' ?>">In Progress <span class="text-xs">(<?= $count_tab_inprog ?>)</span></a>
                <a href="?tab=resolved" class="status-tab <?= $tab==='resolved'?'active':'' ?>">Resolved <span class="text-xs">(<?= $count_tab_res ?>)</span></a>
            </div>
            <div class="overflow-x-auto">
                <table class="tickets-table w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-gray-500">Ticket</th>
                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-gray-500">Issue</th>
                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-gray-500">Alumni</th>
                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-gray-500">Status</th>
                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-gray-500">Created</th>
                            <th class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wider text-gray-500">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="6" class="text-center py-12 text-gray-400">No messages found.</td></tr>
                    <?php else: foreach ($rows as $row): ?>
                        <tr>
                            <td class="px-5 py-3.5 text-sm font-semibold text-gray-800">TKT-<?= str_pad((int)$row['id'], 3, '0', STR_PAD_LEFT) ?></td>
                            <td class="px-5 py-3.5 text-sm text-gray-900"><?= htmlspecialchars(($row['subject'] && trim($row['subject'])!=='') ? $row['subject'] : '(No subject)') ?></td>
                            <td class="px-5 py-3.5 text-sm text-gray-700"><?= htmlspecialchars($row['name']) ?><div class="text-xs text-blue-600"><?= htmlspecialchars($row['email']) ?></div></td>
                            <td class="px-5 py-3.5 text-sm text-gray-700"><?= htmlspecialchars($row['status']) ?></td>
                            <td class="px-5 py-3.5 text-sm text-gray-500"><?= htmlspecialchars($row['submitted_at']) ?></td>
                            <td class="px-5 py-3.5 text-right"><a href="view_inquiry.php?id=<?= (int)$row['id'] ?>" target="_blank" class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold rounded-lg" style="background:#fff5f5;color:#7a0000;border:1px solid #fecaca;"><i class="fas fa-eye"></i>View</a></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-4 border-t border-gray-100 flex items-center justify-between">
                <div class="text-sm text-gray-500">Page <?= $page ?> of <?= $totalPages ?> · <?= $totalRows ?> total</div>
                <div class="flex items-center gap-1">
                    <?php for ($i=max(1,$page-2); $i<=min($totalPages,$page+2); $i++): ?>
                        <a class="pagination-link <?= $i===$page?'active':'' ?>" href="?tab=<?= urlencode($tab) ?>&tq=<?= urlencode($tq) ?>&page=<?= $i ?>"><?= $i ?></a>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </div>
</main>
</body>
</html>
<?php $conn->close(); ?>
