<?php
require_once 'db_config.php';
$conn = getDBConnection();

session_start();
alumni_otp_gate_after_session();

$host = 'localhost';
$user = 'root';
$pass = '';  // Default XAMPP MySQL root password is empty
$db = 'itcp_db';

// Add error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (!isset($_SESSION['user_id'])) {
    header("Location: al_homepage.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user data
$sql = "SELECT * FROM itcp WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Create enhanced user_logs table if it doesn't exist
$create_table_sql = "CREATE TABLE IF NOT EXISTS `user_logs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `activity_type` varchar(50) NOT NULL,
    `activity_description` text NOT NULL,
    `ip_address` varchar(45) NOT NULL,
    `user_agent` text DEFAULT NULL,
    `device_type` varchar(50) DEFAULT NULL,
    `browser` varchar(100) DEFAULT NULL,
    `operating_system` varchar(100) DEFAULT NULL,
    `location` varchar(255) DEFAULT NULL,
    `timestamp` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `activity_type` (`activity_type`),
    KEY `timestamp` (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

$conn->query($create_table_sql);

// Check if columns exist and add them if they don't
$columns_to_add = [
    'user_agent' => 'text DEFAULT NULL',
    'device_type' => 'varchar(50) DEFAULT NULL',
    'browser' => 'varchar(100) DEFAULT NULL',
    'operating_system' => 'varchar(100) DEFAULT NULL',
    'location' => 'varchar(255) DEFAULT NULL'
];

foreach ($columns_to_add as $column_name => $column_definition) {
    // Check if column exists
    $check_sql = "SHOW COLUMNS FROM `user_logs` LIKE '$column_name'";
    $result = $conn->query($check_sql);
    
    if ($result->num_rows == 0) {
        // Column doesn't exist, add it
        $alter_sql = "ALTER TABLE `user_logs` ADD COLUMN `$column_name` $column_definition";
        $conn->query($alter_sql);
    }
}

// Fetch user logs with enhanced information
$sql = "SELECT * FROM user_logs WHERE user_id = ? ORDER BY timestamp DESC LIMIT 50";
$stmt = $conn->prepare($sql);

if ($stmt !== false) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $logs = [];
}

// Fetch notifications
$sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$notification_count = count(array_filter($notifications, function($n) { return !$n['is_read']; }));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1" name="viewport" />
    <title>Activity Logs - Fatima Alumni</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
    <style>
        body::-webkit-scrollbar {
            width: 8px;
        }
        body::-webkit-scrollbar-thumb {
            background-color: #8b0a1c;
            border-radius: 4px;
        }
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 3px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include __DIR__ . '/al_header_universal.php'; ?>

    <!-- MAIN CONTENT -->
    <main class="pt-28 px-4 sm:px-6 md:px-10 max-w-7xl mx-auto">
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Login Activity Logs</h1>
                <div class="text-sm text-gray-500">
                    Showing last 50 activities
                </div>
            </div>
            
            <?php if (empty($logs)): ?>
                <div class="text-center py-12">
                    <i class="fas fa-history text-6xl text-gray-300 mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No Activity Logs Found</h3>
                    <p class="text-gray-500">Your login activities will appear here once you start using the system.</p>
                </div>
            <?php else: ?>
                <div class="space-y-6">
                    <?php foreach ($logs as $log): ?>
                        <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-3 mb-2">
                                        <?php
                                        $icon = 'fa-info-circle';
                                        $color = 'text-blue-500';
                                        $bgColor = 'bg-blue-100';
                                        
                                        switch ($log['activity_type']) {
                                            case 'login':
                                                $icon = 'fa-sign-in-alt';
                                                $color = 'text-green-600';
                                                $bgColor = 'bg-green-100';
                                                break;
                                            case 'logout':
                                                $icon = 'fa-sign-out-alt';
                                                $color = 'text-red-600';
                                                $bgColor = 'bg-red-100';
                                                break;
                                            case 'profile_update':
                                                $icon = 'fa-user-edit';
                                                $color = 'text-purple-600';
                                                $bgColor = 'bg-purple-100';
                                                break;
                                            case 'search':
                                                $icon = 'fa-search';
                                                $color = 'text-yellow-600';
                                                $bgColor = 'bg-yellow-100';
                                                break;
                                        }
                                        ?>
                                        <div class="flex items-center justify-center w-10 h-10 rounded-full <?php echo $bgColor; ?>">
                                            <i class="fas <?php echo $icon; ?> <?php echo $color; ?>"></i>
                                        </div>
                                        <div>
                                            <p class="text-gray-900 font-medium"><?php echo htmlspecialchars($log['activity_description']); ?></p>
                                            <p class="text-sm text-gray-500">
                                                <?php echo date('F d, Y g:i A', strtotime($log['timestamp'])); ?>
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <!-- Device and Location Information -->
                                    <div class="ml-13 grid grid-cols-1 md:grid-cols-2 gap-4 mt-3">
                                        <div class="space-y-2">
                                            <div class="flex items-center space-x-2">
                                                <i class="fas fa-globe text-gray-400 text-sm"></i>
                                                <span class="text-sm text-gray-600">IP Address:</span>
                                                <span class="text-sm font-mono text-gray-800"><?php echo htmlspecialchars($log['ip_address']); ?></span>
                                            </div>
                                            
                                            <?php if (!empty($log['device_type'])): ?>
                                            <div class="flex items-center space-x-2">
                                                <i class="fas fa-mobile-alt text-gray-400 text-sm"></i>
                                                <span class="text-sm text-gray-600">Device:</span>
                                                <span class="text-sm text-gray-800"><?php echo htmlspecialchars($log['device_type']); ?></span>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($log['browser'])): ?>
                                            <div class="flex items-center space-x-2">
                                                <i class="fas fa-browser text-gray-400 text-sm"></i>
                                                <span class="text-sm text-gray-600">Browser:</span>
                                                <span class="text-sm text-gray-800"><?php echo htmlspecialchars($log['browser']); ?></span>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="space-y-2">
                                            <?php if (!empty($log['operating_system'])): ?>
                                            <div class="flex items-center space-x-2">
                                                <i class="fas fa-desktop text-gray-400 text-sm"></i>
                                                <span class="text-sm text-gray-600">OS:</span>
                                                <span class="text-sm text-gray-800"><?php echo htmlspecialchars($log['operating_system']); ?></span>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($log['location'])): ?>
                                            <div class="flex items-center space-x-2">
                                                <i class="fas fa-map-marker-alt text-gray-400 text-sm"></i>
                                                <span class="text-sm text-gray-600">Location:</span>
                                                <span class="text-sm text-gray-800"><?php echo htmlspecialchars($log['location']); ?></span>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <div class="flex items-center space-x-2">
                                                <i class="fas fa-clock text-gray-400 text-sm"></i>
                                                <span class="text-sm text-gray-600">Time:</span>
                                                <span class="text-sm text-gray-800"><?php echo date('H:i:s', strtotime($log['timestamp'])); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Status indicator -->
                                <div class="flex flex-col items-end space-y-2">
                                    <?php if ($log['activity_type'] === 'login'): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            <i class="fas fa-check-circle mr-1"></i>
                                            Successful Login
                                        </span>
                                    <?php elseif ($log['activity_type'] === 'logout'): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                            <i class="fas fa-sign-out-alt mr-1"></i>
                                            Logout
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Summary Statistics -->
                <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <?php
                    $loginCount = count(array_filter($logs, function($log) { return $log['activity_type'] === 'login'; }));
                    $logoutCount = count(array_filter($logs, function($log) { return $log['activity_type'] === 'logout'; }));
                    $uniqueIPs = count(array_unique(array_column($logs, 'ip_address')));
                    ?>
                    <div class="bg-green-50 rounded-lg p-4">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-sign-in-alt text-green-600 text-xl"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-green-800">Total Logins</p>
                                <p class="text-2xl font-bold text-green-900"><?php echo $loginCount; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-blue-50 rounded-lg p-4">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-globe text-blue-600 text-xl"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-blue-800">Unique IPs</p>
                                <p class="text-2xl font-bold text-blue-900"><?php echo $uniqueIPs; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-purple-50 rounded-lg p-4">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-sign-out-alt text-purple-600 text-xl"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-purple-800">Total Logouts</p>
                                <p class="text-2xl font-bold text-purple-900"><?php echo $logoutCount; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const profileDropdownBtn = document.getElementById('profileDropdownBtn');
            const profileDropdown = document.getElementById('profileDropdown');
            if (profileDropdownBtn && profileDropdown) {
                profileDropdownBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    profileDropdown.classList.toggle('hidden');
                });
                document.addEventListener('click', (e) => {
                    if (!profileDropdownBtn.contains(e.target) && !profileDropdown.contains(e.target)) {
                        profileDropdown.classList.add('hidden');
                    }
                });
            }
        });
    </script>
<?php if (file_exists(__DIR__ . '/al_footer_universal.php')) { include 'al_footer_universal.php'; } ?>
</body>
</html> 