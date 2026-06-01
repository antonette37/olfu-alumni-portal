<?php
require_once 'db_config.php';
$conn = getDBConnection();

session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: al_login.php');
    exit();
}

$limit = 20;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? $conn->real_escape_string(trim($_GET['search'])) : '';
$program = isset($_GET['program']) ? $conn->real_escape_string(trim($_GET['program'])) : '';
$batch = isset($_GET['batch']) ? $conn->real_escape_string(trim($_GET['batch'])) : '';
$college_filter = isset($_GET['college']) ? $conn->real_escape_string(trim($_GET['college'])) : '';
$month_graduated_filter = isset($_GET['month_graduated']) ? $conn->real_escape_string(trim($_GET['month_graduated'])) : '';
$employment_filter = isset($_GET['employment']) ? $conn->real_escape_string(trim($_GET['employment'])) : '';

// College and degree options (match registration / ad_user_management)
$college_options = [
    'College of Computer Studies', 'College of Engineering', 'College of Business',
    'College of Arts and Sciences', 'College of Education', 'College of Nursing', 'College of Medicine',
];
$degree_options = [
    'Bachelor of Science in Information Technology', 'Bachelor of Science in Computer Science',
    'Bachelor of Science in Information Systems', 'Bachelor of Science in Civil Engineering',
    'Bachelor of Science in Electrical Engineering', 'Bachelor of Science in Mechanical Engineering',
    'Bachelor of Science in Computer Engineering', 'Bachelor of Science in Business Administration',
    'Bachelor of Science in Accountancy', 'Bachelor of Science in Entrepreneurship',
    'Bachelor of Arts in Communication', 'Bachelor of Science in Psychology', 'Bachelor of Science in Biology',
    'Bachelor of Elementary Education', 'Bachelor of Secondary Education', 'Bachelor of Physical Education',
    'Bachelor of Science in Nursing', 'Doctor of Medicine',
];
sort($degree_options);
$current_year = (int)date('Y');
$year_graduated_options = range($current_year, $current_year - 40);
$month_graduated_options = [
    '01' => 'January', '02' => 'February', '03' => 'March', '04' => 'April',
    '05' => 'May', '06' => 'June', '07' => 'July', '08' => 'August',
    '09' => 'September', '10' => 'October', '11' => 'November', '12' => 'December'
];
$employment_options = ['Employed', 'Unemployed', 'Self-employed', 'Student'];

// Column existence checks
$has_college = false;
$has_year_graduated = false;
$has_month_graduated = false;
$has_employment_status = false;
$chk = @$conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'itcp' AND COLUMN_NAME = 'college'");
if ($chk && $chk->fetch_row()) { $has_college = true; } if ($chk) $chk->close();
$chk = @$conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'itcp' AND COLUMN_NAME = 'year_graduated'");
if ($chk && $chk->fetch_row()) { $has_year_graduated = true; } if ($chk) $chk->close();
$chk = @$conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'itcp' AND COLUMN_NAME = 'month_graduated'");
if ($chk && $chk->fetch_row()) { $has_month_graduated = true; } if ($chk) $chk->close();
$chk = @$conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'itcp' AND COLUMN_NAME = 'employment_status'");
if ($chk && $chk->fetch_row()) { $has_employment_status = true; } if ($chk) $chk->close();

$where_conditions = array();

// Exclude pending users
$where_conditions[] = "LOWER(status) != 'pending'";

if (!empty($search)) {
    $search_parts = ["firstname LIKE '%$search%'", "lastname LIKE '%$search%'", "middlename LIKE '%$search%'", "email LIKE '%$search%'", "student_number LIKE '%$search%'"];
    if ($has_year_graduated) $search_parts[] = "year_graduated LIKE '%$search%'";
    if ($has_month_graduated) $search_parts[] = "month_graduated LIKE '%$search%'";
    $where_conditions[] = "(" . implode(" OR ", $search_parts) . ")";
}

if (!empty($program)) {
    $where_conditions[] = "program = '$program'";
}

if (!empty($batch) && $has_year_graduated) {
    $where_conditions[] = "year_graduated = '$batch'";
}

if (!empty($college_filter) && $has_college) {
    $where_conditions[] = "college = '$college_filter'";
}

if (!empty($month_graduated_filter) && $has_month_graduated) {
    $where_conditions[] = "month_graduated = '$month_graduated_filter'";
}

if (!empty($employment_filter) && $has_employment_status) {
    $where_conditions[] = "employment_status = '$employment_filter'";
}

$where = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

$count_sql = "SELECT COUNT(*) AS total FROM itcp $where";
$count_result = $conn->query($count_sql);
$total_rows = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

$sql = "SELECT * FROM itcp $where ORDER BY lastname ASC LIMIT $limit OFFSET $offset";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alumni Records - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap">
    <link rel="stylesheet" href="admin_page_patches.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Dropdown Animation Styles */
        .dropdown-menu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-in-out;
            opacity: 0;
            visibility: hidden;
        }
        .dropdown-menu.show {
            max-height: 500px;
            opacity: 1;
            visibility: visible;
        }
        .dropdown-icon {
            transition: transform 0.3s ease-in-out;
        }
        .dropdown-icon.rotate {
            transform: rotate(180deg);
        }
    </style>
</head>
<body class="admin-skin">
    <!-- HEADER -->
    <!-- Universal Admin Header -->
    <?php include __DIR__ . '/ad_header_universal.php'; ?>

    <!-- Universal Admin Sidebar -->
    <?php include __DIR__ . '/ad_sidebar_universal.php'; ?>

    <main class="admin-main">
        <div class="page">
            <header class="pg-hd fade-in">
                <h1>Alumni Records</h1>
            </header>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="mb-4 p-4 rounded-md bg-green-50 border border-green-200">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-check-circle text-green-400"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-green-800">
                                <?= htmlspecialchars($_SESSION['success']) ?>
                            </p>
                        </div>
                        <div class="ml-auto pl-3">
                            <div class="-mx-1.5 -my-1.5">
                                <button onclick="this.parentElement.parentElement.parentElement.parentElement.remove()" class="inline-flex rounded-md p-1.5 text-green-500 hover:bg-green-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                    <span class="sr-only">Dismiss</span>
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="mb-4 p-4 rounded-md bg-red-50 border border-red-200">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-circle text-red-400"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-red-800">
                                <?= htmlspecialchars($_SESSION['error']) ?>
                            </p>
                        </div>
                        <div class="ml-auto pl-3">
                            <div class="-mx-1.5 -my-1.5">
                                <button onclick="this.parentElement.parentElement.parentElement.parentElement.remove()" class="inline-flex rounded-md p-1.5 text-red-500 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                    <span class="sr-only">Dismiss</span>
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <div class="filter-bar fade-in">
                <form action="" method="GET" class="filter-grid">
                        <div class="fi" style="min-width:200px;flex:1">
                            <label for="ar_search">Search</label>
                            <div class="filter-search">
                                <input id="ar_search" type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search alumni..." class="filter-input" style="width:100%">
                                <i class="fas fa-search" aria-hidden="true"></i>
                            </div>
                        </div>
                        <div class="fi">
                            <label for="ar_program">Degree / Program</label>
                            <select id="ar_program" name="program" class="filter-input" style="width:100%">
                                <option value="">All degrees</option>
                                <?php foreach ($degree_options as $deg): ?>
                                <option value="<?= htmlspecialchars($deg) ?>" <?= $program === $deg ? 'selected' : '' ?>><?= htmlspecialchars($deg) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($has_college): ?>
                        <div class="fi">
                            <label for="ar_college">College</label>
                            <select id="ar_college" name="college" class="filter-input" style="width:100%">
                                <option value="">All colleges</option>
                                <?php foreach ($college_options as $co): ?>
                                <option value="<?= htmlspecialchars($co) ?>" <?= $college_filter === $co ? 'selected' : '' ?>><?= htmlspecialchars($co) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <?php if ($has_year_graduated): ?>
                        <div class="fi">
                            <label for="ar_batch">Year graduated</label>
                            <select id="ar_batch" name="batch" class="filter-input" style="width:100%">
                                <option value="">All years</option>
                                <?php foreach ($year_graduated_options as $yr): ?>
                                <option value="<?= (int)$yr ?>" <?= $batch === (string)(int)$yr ? 'selected' : '' ?>><?= (int)$yr ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <?php if ($has_month_graduated): ?>
                        <div class="fi">
                            <label for="ar_month">Month graduated</label>
                            <select id="ar_month" name="month_graduated" class="filter-input" style="width:100%">
                                <option value="">All months</option>
                                <?php foreach ($month_graduated_options as $num => $label): ?>
                                <option value="<?= htmlspecialchars($num) ?>" <?= $month_graduated_filter === $num ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <?php if ($has_employment_status): ?>
                        <div class="fi">
                            <label for="ar_emp">Employment</label>
                            <select id="ar_emp" name="employment" class="filter-input" style="width:100%">
                                <option value="">All</option>
                                <?php foreach ($employment_options as $emp): ?>
                                <option value="<?= htmlspecialchars($emp) ?>" <?= $employment_filter === $emp ? 'selected' : '' ?>><?= htmlspecialchars($emp) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="fi" style="min-width:auto;flex-direction:row;align-items:flex-end;gap:8px">
                            <button type="submit" class="btn-cr">
                                <i class="fas fa-filter"></i> Apply Filters
                            </button>
                            <a href="ad_alumnirecord.php" class="btn-ghost">Clear</a>
                        </div>
                </form>
            </div>

            <div class="flex justify-end mb-4 fade-in">
                <div class="view-toggle">
                    <button type="button" class="vt-btn" id="gridViewBtn" data-view="grid" title="Grid view">
                        <i class="fas fa-th-large"></i>
                    </button>
                    <button type="button" class="vt-btn" id="tableViewBtn" data-view="table" title="Table view">
                        <i class="fas fa-list"></i>
                    </button>
                </div>
            </div>

            <div id="gridView" class="alumni-records-grid fade-in">
                <?php if ($result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                        <article class="alumni-card-new">
                            <div class="card-avatar">
                                        <?php 
                                        $photo = $row['photo'] ?? '';
                                        $photo_display = false;
                                        $photo_path = '';
                                        
                                        if (!empty($photo) && is_string($photo) && trim($photo) !== '') {
                                            $photo = trim($photo);
                                            if (strpos($photo, 'http') === 0 || strpos($photo, 'https') === 0) {
                                                $photo_path = $photo;
                                                $photo_display = true;
                                            } else {
                                                $photo_filename = basename($photo);
                                                $photo_path = 'serve_profile_image.php?img=' . rawurlencode($photo_filename);
                                                $photo_display = true;
                                            }
                                        }
                                        
                                        if ($photo_display): 
                                        ?>
                                            <img src="<?= htmlspecialchars($photo_path) ?>" 
                                                 alt=""
                                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <span style="display:none"><?= strtoupper(substr($row['firstname'] ?? '', 0, 1) . substr($row['lastname'] ?? '', 0, 1)) ?></span>
                                        <?php else: ?>
                                            <?= strtoupper(substr($row['firstname'] ?? '', 0, 1) . substr($row['lastname'] ?? '', 0, 1)) ?>
                                        <?php endif; ?>
                            </div>
                                <p class="card-name"><?= htmlspecialchars($row['firstname'] . ' ' . $row['lastname']) ?></p>
                                <p class="card-prog"><?= htmlspecialchars($row['program']) ?></p>
                                <p class="card-batch">Batch <?= htmlspecialchars($row['year_graduated']) ?></p>
                                <div class="card-actions">
                                    <a href="ad_viewprofile.php?id=<?= $row['id'] ?>" class="card-btn card-btn-view" title="View Profile">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <button type="button" class="card-btn card-btn-arch" title="Archive" onclick="confirmArchive(<?= $row['id'] ?>, '<?= htmlspecialchars($row['firstname'] . ' ' . $row['lastname'], ENT_QUOTES, 'UTF-8') ?>')">
                                        <i class="fas fa-archive"></i> Archive
                                    </button>
                                </div>
                        </article>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty" style="grid-column:1/-1">
                        <i class="fas fa-inbox"></i>
                        <h3>No alumni records found</h3>
                        <p>Try adjusting your filters.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div id="tableView" class="hidden al-tbl-wrap fade-in">
                <div class="overflow-x-auto">
                    <table class="al-tbl">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Program</th>
                                <th>Batch Year</th>
                                <th class="hidden sm:table-cell">Contact</th>
                                <th class="hidden sm:table-cell">Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $result->data_seek(0);
                            while($row = $result->fetch_assoc()): 
                            ?>
                            <tr>
                                <td>
                                    <div class="name-cell">
                                        <div class="t-av">
                                                <?php 
                                                $photo = $row['photo'] ?? '';
                                                $photo_display = false;
                                                $photo_path = '';
                                                
                                                if (!empty($photo) && is_string($photo) && trim($photo) !== '') {
                                                    $photo = trim($photo);
                                                    if (strpos($photo, 'http') === 0 || strpos($photo, 'https') === 0) {
                                                        $photo_path = $photo;
                                                        $photo_display = true;
                                                    } else {
                                                        $photo_filename = basename($photo);
                                                        $photo_path = 'serve_profile_image.php?img=' . rawurlencode($photo_filename);
                                                        $photo_display = true;
                                                    }
                                                }
                                                
                                                if ($photo_display): 
                                                ?>
                                                    <img src="<?= htmlspecialchars($photo_path) ?>" alt="" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                    <span style="display:none"><?= strtoupper(substr($row['firstname'] ?? '', 0, 1) . substr($row['lastname'] ?? '', 0, 1)) ?></span>
                                                <?php else: ?>
                                                    <?= strtoupper(substr($row['firstname'] ?? '', 0, 1) . substr($row['lastname'] ?? '', 0, 1)) ?>
                                                <?php endif; ?>
                                        </div>
                                        <div class="name-stack">
                                            <div class="nm"><?= htmlspecialchars($row['firstname'] . ' ' . $row['lastname']) ?></div>
                                            <div class="em"><?= htmlspecialchars($row['email'] ?? 'N/A') ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($row['program']) ?></td>
                                <td><?= htmlspecialchars($row['year_graduated']) ?></td>
                                <td class="hidden sm:table-cell"><?= htmlspecialchars($row['personal_contact'] ?? $row['contact_number'] ?? 'N/A') ?></td>
                                <td class="hidden sm:table-cell"><span class="badge badge-active">Active</span></td>
                                <td>
                                    <div class="card-actions" style="border:0;background:transparent;padding:0;justify-content:flex-start">
                                        <a href="ad_viewprofile.php?id=<?= $row['id'] ?>" class="card-btn card-btn-view" title="View Profile"><i class="fas fa-eye"></i></a>
                                        <button type="button" class="card-btn card-btn-arch" title="Archive" onclick="confirmArchive(<?= $row['id'] ?>, '<?= htmlspecialchars($row['firstname'] . ' ' . $row['lastname'], ENT_QUOTES, 'UTF-8') ?>')"><i class="fas fa-archive"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <?php
            $paginate_q = 'search=' . urlencode($search) . '&program=' . urlencode($program) . '&batch=' . urlencode($batch) . '&college=' . urlencode($college_filter) . '&month_graduated=' . urlencode($month_graduated_filter) . '&employment=' . urlencode($employment_filter);
            ?>
            <?php if ($total_pages > 1): ?>
            <nav class="pagination fade-in" aria-label="Pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page-1 ?>&<?= $paginate_q ?>" class="page-btn" aria-label="Previous"><i class="fas fa-chevron-left"></i></a>
                    <?php endif; ?>
                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?= $i ?>&<?= $paginate_q ?>" class="page-btn<?= $i == $page ? ' active' : '' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page+1 ?>&<?= $paginate_q ?>" class="page-btn" aria-label="Next"><i class="fas fa-chevron-right"></i></a>
                    <?php endif; ?>
            </nav>
            <?php endif; ?>
        </div>
    </main>

    <!-- Archive Confirmation Modal -->
    <div id="archiveModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-orange-100 mb-4">
                    <i class="fas fa-archive text-orange-600 text-xl"></i>
                </div>
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-2">Archive Alumni Profile</h3>
                <div class="mt-2 px-7 py-3">
                    <p class="text-sm text-gray-500" id="archiveModalText">
                        Are you sure you want to archive this alumni's profile? This will mark the profile as archived.
                    </p>
                </div>
                <div class="flex justify-center space-x-4 mt-4">
                    <button id="cancelArchive" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-400">
                        Cancel
                    </button>
                    <button id="confirmArchive" class="px-4 py-2 bg-orange-600 text-white rounded-md hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500">
                        Archive
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>

        // View Toggle
        document.addEventListener('DOMContentLoaded', function() {
            const gridViewBtn = document.getElementById('gridViewBtn');
            const tableViewBtn = document.getElementById('tableViewBtn');
            const gridView = document.getElementById('gridView');
            const tableView = document.getElementById('tableView');

            // Function to set active view
            function setActiveView(view) {
                if (view === 'grid') {
                    gridView.classList.remove('hidden');
                    tableView.classList.add('hidden');
                    gridViewBtn.classList.add('active');
                    tableViewBtn.classList.remove('active');
                    localStorage.setItem('preferredView', 'grid');
                } else {
                    tableView.classList.remove('hidden');
                    gridView.classList.add('hidden');
                    tableViewBtn.classList.add('active');
                    gridViewBtn.classList.remove('active');
                    localStorage.setItem('preferredView', 'table');
                }
            }

            // Set initial view from localStorage or default to grid
            const preferredView = localStorage.getItem('preferredView') || 'grid';
            setActiveView(preferredView);

            // Add click event listeners
            gridViewBtn.addEventListener('click', () => setActiveView('grid'));
            tableViewBtn.addEventListener('click', () => setActiveView('table'));
        });


        // Archive Confirmation Modal
        let archiveModal = document.getElementById('archiveModal');
        let cancelArchive = document.getElementById('cancelArchive');
        let confirmArchiveBtn = document.getElementById('confirmArchive');
        let archiveModalText = document.getElementById('archiveModalText');
        let alumniToArchive = null;

        function confirmArchive(id, name) {
            alumniToArchive = id;
            archiveModalText.textContent = `Are you sure you want to archive ${name}'s profile? This will mark the profile as archived.`;
            archiveModal.classList.remove('hidden');
        }

        function closeArchiveModal() {
            archiveModal.classList.add('hidden');
            alumniToArchive = null;
        }

        cancelArchive.addEventListener('click', closeArchiveModal);

        confirmArchiveBtn.addEventListener('click', function() {
            if (alumniToArchive) {
                window.location.href = `ad_user_archive.php?id=${alumniToArchive}`;
            }
        });

        // Close modal when clicking outside
        archiveModal.addEventListener('click', function(e) {
            if (e.target === archiveModal) {
                closeArchiveModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && !archiveModal.classList.contains('hidden')) {
                closeArchiveModal();
            }
        });

        // Notification handling
        const notificationBtn = document.getElementById('notificationBtn');
        const notificationDropdown = document.getElementById('notificationDropdown');
        const notificationBadge = document.getElementById('notificationBadge');
        const notificationList = document.getElementById('notificationList');

        // Toggle notification dropdown
        notificationBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            notificationDropdown.classList.toggle('hidden');
            if (!notificationDropdown.classList.contains('hidden')) {
                loadNotifications();
            }
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!notificationBtn.contains(e.target) && !notificationDropdown.contains(e.target)) {
                notificationDropdown.classList.add('hidden');
            }
        });

        // Function to load notifications
        function loadNotifications() {
            // Fetch notifications from the server
            fetch('get_notifications.php')
                .then(response => response.json())
                .then(data => {
                    updateNotificationBadge(data.unread_count);
                    updateNotificationList(data.notifications);
                })
                .catch(error => console.error('Error loading notifications:', error));
        }

        // Function to update notification badge
        function updateNotificationBadge(count) {
            if (count > 0) {
                notificationBadge.textContent = count;
                notificationBadge.classList.remove('hidden');
            } else {
                notificationBadge.classList.add('hidden');
            }
        }

        // Function to update notification list
        function updateNotificationList(notifications) {
            if (notifications.length === 0) {
                notificationList.innerHTML = `
                    <div class="px-4 py-3 text-sm text-gray-500 text-center">
                        No new notifications
                    </div>
                `;
                return;
            }

            notificationList.innerHTML = notifications.map(notification => {
                // Determine the link based on notification type
                let link = notification.link;
                let icon = 'fa-bell';
                
                if (notification.type === 'job_post') {
                    link = 'ad_content_management.php';
                    icon = 'fa-briefcase';
                } else if (notification.type === 'job_approved') {
                    icon = 'fa-check-circle';
                }

                return `
                    <a href="${link}" class="block px-4 py-3 hover:bg-gray-50 ${notification.read ? '' : 'bg-green-50'}">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <i class="fas ${icon} text-green-600"></i>
                            </div>
                            <div class="ml-3 w-0 flex-1">
                                <p class="text-sm font-medium text-gray-900">${notification.title}</p>
                                <p class="text-sm text-gray-500">${notification.message}</p>
                                <p class="mt-1 text-xs text-gray-400">${notification.time}</p>
                            </div>
                        </div>
                    </a>
                `;
            }).join('');
        }

        // Load notifications periodically
        setInterval(loadNotifications, 30000); // Check every 30 seconds
        loadNotifications(); // Initial load
    </script>
</body>
</html>

<?php
$conn->close();
?>
