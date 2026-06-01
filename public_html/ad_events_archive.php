<?php
require_once 'db_config.php';
$conn = getDBConnection();

session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: al_login.php');
    exit();
}

// Set timezone
date_default_timezone_set('Asia/Manila');

// Handle restore action
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'restore_event') {
        $id = (int)$_POST['event_id'];

        $sql = "UPDATE events SET archived = 0 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            $message = "Error preparing restore query: " . $conn->error;
            $messageType = "error";
        }
        else {
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $message = "Event restored successfully!";
                $messageType = "success";
            }
            else {
                $message = "Error restoring event: " . $conn->error;
                $messageType = "error";
            }
            $stmt->close();
        }
    }
}

// Get message from URL if redirected
if (isset($_GET['message']) && isset($_GET['type'])) {
    $message = htmlspecialchars($_GET['message']);
    $messageType = htmlspecialchars($_GET['type']);
}

// Ensure archived column exists
$check_archived = $conn->query("SHOW COLUMNS FROM events LIKE 'archived'");
if ($check_archived->num_rows == 0) {
    $alter_result = $conn->query("ALTER TABLE events ADD COLUMN archived TINYINT(1) DEFAULT 0");
    if (!$alter_result) {
        error_log("Error adding archived column: " . $conn->error);
        $column_error = "Error: Could not create archived column. " . $conn->error;
    }
    else {
        error_log("Successfully added archived column to events table");
    }
}

// Debug: Check all events and their archived status
$debug_sql = "SELECT id, title, archived FROM events ORDER BY id DESC LIMIT 20";
$debug_result = $conn->query($debug_sql);
if ($debug_result) {
    error_log("Sample events and their archived status:");
    while ($debug_row = $debug_result->fetch_assoc()) {
        $archived_val = isset($debug_row['archived']) ? $debug_row['archived'] : 'NULL';
        error_log("Event ID: {$debug_row['id']}, Title: {$debug_row['title']}, Archived: $archived_val");
    }
}

// Fetch archived events with registration counts
// First, let's check if there are any events with archived = 1
$check_archived_events = $conn->query("SELECT COUNT(*) as count FROM events WHERE archived = 1");
$archived_count_check = 0;
if ($check_archived_events) {
    $count_row = $check_archived_events->fetch_assoc();
    $archived_count_check = $count_row['count'] ?? 0;
    error_log("Total archived events in database: $archived_count_check");

    // Also get the actual archived event IDs for debugging
    $debug_archived = $conn->query("SELECT id, title, archived FROM events WHERE archived = 1");
    if ($debug_archived) {
        error_log("Archived events details:");
        while ($debug_row = $debug_archived->fetch_assoc()) {
            error_log("  - ID: {$debug_row['id']}, Title: {$debug_row['title']}, Archived: {$debug_row['archived']}");
        }
    }
}

$sql = "SELECT e.*, 
        (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id) as registered_count
        FROM events e 
        WHERE e.archived = 1
        ORDER BY e.event_date DESC";
$result = $conn->query($sql);

// Debug: Check if query executed successfully
if (!$result) {
    error_log("Error fetching archived events: " . $conn->error);
    // Show error message on page
    $query_error = $conn->error;
}
else {
    $archived_count = $result->num_rows;
    error_log("Query returned $archived_count archived events");

    // Log first few archived events for debugging
    if ($archived_count > 0) {
        $result->data_seek(0);
        $first_few = [];
        $count = 0;
        while (($row = $result->fetch_assoc()) && $count < 3) {
            $first_few[] = "ID: {$row['id']}, Title: {$row['title']}";
            $count++;
        }
        error_log("First archived events: " . implode("; ", $first_few));
        $result->data_seek(0); // Reset pointer
    }
}

// Function to format date
function formatDate($date)
{
    if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
        return 'N/A';
    }
    return date('M d, Y', strtotime($date));
}

// Function to format datetime
function formatDateTime($date, $time = null)
{
    if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
        return 'N/A';
    }
    $datetime = $date;
    if ($time) {
        $datetime = $date . ' ' . $time;
    }
    return date('M d, Y h:i A', strtotime($datetime));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archived Events - Admin Panel</title>
    <link rel="icon" href="olfulogo.png" type="image/png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            @apply bg-gray-100 text-gray-800 font-sans;
        }
    </style>
</head>
<body>
    <?php include 'ad_header_universal.php'; ?>
    <?php include 'ad_sidebar_universal.php'; ?>

    <!-- Main Content -->
    <div class="main-content pt-24 ml-16 p-4 max-w-full">
        <div class="max-w-7xl mx-auto">
            <!-- Page Header -->
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">Archived Events</h2>
                    <p class="text-gray-600 mt-1">View and manage archived events</p>
                </div>
                <a href="ad_events.php" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 font-semibold flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Events
                </a>
            </div>

            <!-- Message Display -->
            <?php if (!empty($message)): ?>
            <div class="mb-4 p-4 rounded-md <?php echo $messageType === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php
endif; ?>
            
            <!-- Debug Info (remove in production) -->
            <?php if (isset($archived_count_check)): ?>
            <div class="mb-4 p-3 bg-blue-100 text-blue-800 rounded-md text-sm">
                <strong>Debug:</strong> Found <?php echo $archived_count_check; ?> archived event(s) in database.
                <?php if (isset($query_error)): ?>
                    <br><strong>Error:</strong> <?php echo htmlspecialchars($query_error); ?>
                <?php
    endif; ?>
            </div>
            <?php
endif; ?>

            <!-- Events Table -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Event Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Venue</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Registrations</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (isset($query_error)): ?>
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-red-500">
                                        <p><strong>Database Error:</strong> <?php echo htmlspecialchars($query_error); ?></p>
                                        <p class="text-sm mt-2">Please check the error logs or contact the administrator.</p>
                                    </td>
                                </tr>
                            <?php
elseif ($result && $result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($row['title'] ?? 'N/A'); ?></div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                            <?php echo htmlspecialchars(ucfirst($row['type'] ?? 'N/A')); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-600">
                                        <?php echo formatDate($row['event_date'] ?? ''); ?>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-600">
                                        <?php echo htmlspecialchars($row['venue'] ?? $row['location'] ?? 'N/A'); ?>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-600">
                                        <?php echo $row['registered_count'] ?? 0; ?> registered
                                    </td>
                                    <td class="px-4 py-4">
                                        <button type="button" onclick="restoreEvent(<?php echo $row['id']; ?>)" 
                                                class="inline-flex items-center px-3 py-2 rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50">
                                            <i class="fas fa-undo mr-1"></i> Restore
                                        </button>
                                    </td>
                                </tr>
                                <?php
    endwhile; ?>
                            <?php
else: ?>
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                        <i class="fas fa-archive text-4xl mb-2"></i>
                                        <p>No archived events found.</p>
                                    </td>
                                </tr>
                            <?php
endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Restore Confirmation Modal -->
    <div id="restoreConfirmModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100 mb-4">
                    <i class="fas fa-check-circle text-green-600 text-xl"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">Restore Event</h3>
                <div class="mt-2 px-7 py-3">
                    <p class="text-sm text-gray-500">
                        Are you sure you want to restore this event? It will be visible in the events list again.
                    </p>
                </div>
                <div class="flex items-center justify-center px-4 py-3 space-x-3">
                    <button id="restoreCancelBtn" class="px-4 py-2 bg-gray-200 text-gray-800 text-base font-medium rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-300">
                        Cancel
                    </button>
                    <button id="restoreConfirmBtn" class="px-4 py-2 bg-green-600 text-white text-base font-medium rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500">
                        Restore
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Restore Event Function with Custom Modal
        let pendingRestoreEventId = null;
        
        function restoreEvent(eventId) {
            pendingRestoreEventId = eventId;
            const modal = document.getElementById('restoreConfirmModal');
            modal.classList.remove('hidden');
        }
        
        // Restore Modal Handlers
        document.getElementById('restoreCancelBtn').addEventListener('click', function() {
            document.getElementById('restoreConfirmModal').classList.add('hidden');
            pendingRestoreEventId = null;
        });
        
        document.getElementById('restoreConfirmBtn').addEventListener('click', function() {
            if (pendingRestoreEventId) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'ad_events_archive.php';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'restore_event';
                
                const eventIdInput = document.createElement('input');
                eventIdInput.type = 'hidden';
                eventIdInput.name = 'event_id';
                eventIdInput.value = pendingRestoreEventId;
                
                form.appendChild(actionInput);
                form.appendChild(eventIdInput);
                document.body.appendChild(form);
                form.submit();
            }
        });
        
        // Close modal when clicking outside
        document.getElementById('restoreConfirmModal').addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.add('hidden');
                pendingRestoreEventId = null;
            }
        });
    </script>
</body>
</html>

<?php
$conn->close();
?>

