<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: al_login.php');
    exit();
}

// Database connection
require_once 'db_config.php';
$conn = getDBConnection();

// Check if system_logs table exists
$table_check = $conn->query("SHOW TABLES LIKE 'system_logs'");
if ($table_check->num_rows == 0) {
    // Create system_logs table if it doesn't exist
    $create_table_sql = "CREATE TABLE system_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        action_type ENUM('login', 'logout', 'register', 'update_profile', 'delete_profile', 'post_job', 'delete_job', 'other') NOT NULL,
        description TEXT NOT NULL,
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES itcp(id) ON DELETE SET NULL
    )";

    if (!$conn->query($create_table_sql)) {
        die("Error creating table: " . $conn->error);
    }
}

// Set timezone
date_default_timezone_set('Asia/Manila');

// Pagination settings
$limit = 20;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Get filter parameters
$action_type = isset($_GET['action_type']) ? $conn->real_escape_string($_GET['action_type']) : '';
$date_from = isset($_GET['date_from']) ? $conn->real_escape_string($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? $conn->real_escape_string($_GET['date_to']) : '';
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

// Build the query
$sql = "SELECT l.*, i.firstname, i.lastname, i.email 
        FROM system_logs l 
        LEFT JOIN itcp i ON l.user_id = i.id 
        WHERE 1=1";

if (!empty($action_type)) {
    $sql .= " AND l.action_type = '$action_type'";
}

if (!empty($date_from)) {
    $sql .= " AND DATE(l.created_at) >= '$date_from'";
}

if (!empty($date_to)) {
    $sql .= " AND DATE(l.created_at) <= '$date_to'";
}

if (!empty($search)) {
    $sql .= " AND (i.firstname LIKE '%$search%' 
              OR i.lastname LIKE '%$search%' 
              OR i.email LIKE '%$search%' 
              OR l.description LIKE '%$search%')";
}

// Get total count for pagination
$count_sql = str_replace("SELECT l.*, i.firstname, i.lastname, i.email", "SELECT COUNT(*) as total", $sql);
$count_result = $conn->query($count_sql);
$total_rows = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

// Add pagination to main query
$sql .= " ORDER BY l.created_at DESC LIMIT $limit OFFSET $offset";

$result = $conn->query($sql);

// Get unique action types for filter
$action_types = $conn->query("SELECT DISTINCT action_type FROM system_logs ORDER BY action_type");

// Function to get action type color
function getActionTypeColor($action_type) {
    switch($action_type) {
        case 'login':
            return 'bg-green-100 text-green-800';
        case 'logout':
            return 'bg-gray-100 text-gray-800';
        case 'register':
            return 'bg-blue-100 text-blue-800';
        case 'update_profile':
            return 'bg-yellow-100 text-yellow-800';
        case 'delete_profile':
            return 'bg-red-100 text-red-800';
        case 'post_job':
            return 'bg-purple-100 text-purple-800';
        case 'delete_job':
            return 'bg-orange-100 text-orange-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

// Function to format timestamp
function formatTimestamp($timestamp) {
    return date('M d, Y h:i A', strtotime($timestamp));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Logs - Admin Panel</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            @apply bg-gray-100 text-gray-800 font-sans;
        }
    </style>
</head>
<body>
    <?php require_once 'ad_header_universal.php'; ?>
    <?php require_once 'ad_sidebar_universal.php'; ?>

    <!-- Main Content -->
    <div class="main-content pt-24 ml-16 p-4 max-w-full">
        <div class="max-w-7xl mx-auto">
            <!-- Page Header -->
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">System Logs</h1>
                    <p class="text-sm text-gray-500">Review and filter admin/user activity records</p>
                </div>
                <button onclick="exportLogs()" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 font-semibold flex items-center">
                    <i class="fas fa-download mr-2"></i> Export Logs
                </button>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-lg shadow-md p-4 mb-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label for="action_type" class="block text-sm font-medium text-gray-700 mb-1">Action Type</label>
                        <select name="action_type" id="action_type" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-500">
                            <option value="">All Actions</option>
                            <?php while($row = $action_types->fetch_assoc()): ?>
                                <option value="<?= $row['action_type'] ?>" <?= $action_type === $row['action_type'] ? 'selected' : '' ?>>
                                    <?= ucfirst($row['action_type']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <label for="date_from" class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                        <input type="date" name="date_from" id="date_from" value="<?= $date_from ?>" 
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>
                    <div>
                        <label for="date_to" class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                        <input type="date" name="date_to" id="date_to" value="<?= $date_to ?>" 
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>
                    <div>
                        <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                        <input type="text" name="search" id="search" value="<?= htmlspecialchars($search) ?>" 
                               placeholder="Search by name, email, or description" 
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>
                    <div class="md:col-span-4 flex justify-end">
                        <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 font-semibold">
                            <i class="fas fa-filter mr-2"></i>Apply Filters
                        </button>
                    </div>
                </form>
            </div>

            <!-- Logs Table -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Timestamp</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP Address</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if ($result->num_rows > 0): ?>
                                <?php while($row = $result->fetch_assoc()): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= formatTimestamp($row['created_at']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?= $row['firstname'] ? htmlspecialchars($row['firstname'] . ' ' . $row['lastname']) : 'System' ?>
                                            </div>
                                            <?php if ($row['email']): ?>
                                                <div class="text-sm text-gray-500">
                                                    <?= htmlspecialchars($row['email']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold <?= getActionTypeColor($row['action_type']) ?>">
                                                <?= ucfirst($row['action_type']) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500">
                                            <?= htmlspecialchars($row['description']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= htmlspecialchars($row['ip_address']) ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-4 text-center text-gray-500">No logs found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="mt-6 flex justify-center">
                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page-1 ?>&action_type=<?= urlencode($action_type) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>&search=<?= urlencode($search) ?>" 
                           class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                            <span class="sr-only">Previous</span>
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?= $i ?>&action_type=<?= urlencode($action_type) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>&search=<?= urlencode($search) ?>" 
                           class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium <?= $i == $page ? 'text-green-600 bg-green-50' : 'text-gray-700 hover:bg-gray-50' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page+1 ?>&action_type=<?= urlencode($action_type) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>&search=<?= urlencode($search) ?>" 
                           class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                            <span class="sr-only">Next</span>
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Export logs function
        function exportLogs() {
            // Get current URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            
            // Add export parameter
            urlParams.set('export', 'true');
            
            // Redirect to export endpoint
            window.location.href = 'export_logs.php?' + urlParams.toString();
        }
    </script>
</body>
</html>

<?php
$conn->close();
?> 