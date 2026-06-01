<?php
session_start();
require_once 'db_config.php';
alumni_otp_gate_after_session();
$conn = getDBConnection();

if (!isset($_SESSION['user_id'])) {
    header('Location: al_homepage.php');
    exit;
}

// Get user data
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM itcp WHERE id = ?";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    error_log('Prepare failed for user query: ' . $conn->error);
    $user = [];
} else {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
}

// Fetch notifications
$sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    error_log('Prepare failed for notifications query: ' . $conn->error);
    $notifications = [];
} else {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
$notification_count = count(array_filter($notifications, function($n) { return !$n['is_read']; }));

// Get ticket ID from URL
$ticket_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($ticket_id <= 0) {
    header('Location: al_my_tickets.php');
    exit;
}

// Fetch ticket details
$ticket = null;
$sql = "SELECT id, name, email, subject, message, status, is_read, submitted_at 
        FROM contact_messages 
        WHERE id = ? AND email = ?";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("is", $ticket_id, $user['email']);
    $stmt->execute();
    $result = $stmt->get_result();
    $ticket = $result->fetch_assoc();
}

if (!$ticket) {
    header('Location: al_my_tickets.php');
    exit;
}

// Fetch ticket replies from messages table
$replies = [];
$sql = "SELECT m.*, u.firstname, u.lastname 
        FROM messages m 
        LEFT JOIN itcp u ON m.sender_id = u.id 
        WHERE m.subject LIKE ? 
        ORDER BY m.created_at ASC";
$search_term = '%' . $ticket['subject'] . '%';
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("s", $search_term);
    $stmt->execute();
    $result = $stmt->get_result();
    $replies = $result->fetch_all(MYSQLI_ASSOC);
}

// Helper function for status badges
function getStatusBadge($status) {
    switch($status) {
        case 'New':
            return 'bg-red-100 text-red-800';
        case 'In Progress':
            return 'bg-yellow-100 text-yellow-800';
        case 'Resolved':
            return 'bg-green-100 text-green-800';
        case 'Spam':
            return 'bg-gray-100 text-gray-800';
        default:
            return 'bg-blue-100 text-blue-800';
    }
}

// Helper function for time formatting
function formatTimeAgo($datetime) {
    if (!$datetime) return '';
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return 'just now';
    if ($diff < 3600) { $m = floor($diff/60); return $m . ' min' . ($m>1?'s':'') . ' ago'; }
    if ($diff < 86400) { $h = floor($diff/3600); return $h . ' hour' . ($h>1?'s':'') . ' ago'; }
    if ($diff < 604800) { $d = floor($diff/86400); return $d . ' day' . ($d>1?'s':'') . ' ago'; }
    return date('M d, Y g:i A', $time);
}

// Process reply submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_message'])) {
    $reply_message = htmlspecialchars(trim($_POST['reply_message']));
    
    if (!empty($reply_message)) {
        // Insert reply into messages table
        $sql = "INSERT INTO messages (sender_id, receiver_id, subject, message, is_read, created_at) 
                VALUES (?, 0, ?, ?, 0, NOW())";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $subject = 'Re: ' . $ticket['subject'];
            $stmt->bind_param("iss", $user_id, $subject, $reply_message);
            if ($stmt->execute()) {
                // Update ticket status to "In Progress" if it was "New"
                if ($ticket['status'] === 'New') {
                    $update_sql = "UPDATE contact_messages SET status = 'In Progress' WHERE id = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    if ($update_stmt) {
                        $update_stmt->bind_param("i", $ticket_id);
                        $update_stmt->execute();
                        $update_stmt->close();
                    }
                }
                
                // Refresh page to show new reply
                header('Location: al_ticket_view.php?id=' . $ticket_id);
                exit;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket #<?= $ticket['id'] ?> • Alumni Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="al_styles.css">
</head>
<body class="bg-gradient-to-br from-green-50 to-blue-50 min-h-screen">
    <?php include 'al_header_universal.php'; ?>
    
    <main class="pt-24 px-4 md:px-6 max-w-4xl mx-auto">
        <!-- Breadcrumb -->
        <div class="mb-6">
            <nav class="flex items-center space-x-2 text-sm text-gray-600">
                <a href="al_dashboard.php" class="hover:text-green-600">Dashboard</a>
                <i class="fas fa-chevron-right text-gray-400"></i>
                <a href="al_my_tickets.php" class="hover:text-green-600">My Tickets</a>
                <i class="fas fa-chevron-right text-gray-400"></i>
                <span class="text-gray-900">Ticket #<?= $ticket['id'] ?></span>
            </nav>
        </div>

        <!-- Ticket Header -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 mb-2">
                        <?= htmlspecialchars($ticket['subject'] ?: 'Support Request #' . $ticket['id']) ?>
                    </h1>
                    <p class="text-gray-600">
                        Created <?= formatTimeAgo($ticket['submitted_at']) ?>
                    </p>
                </div>
                <div class="flex items-center space-x-3">
                    <span class="px-3 py-1 rounded-full text-sm font-medium <?= getStatusBadge($ticket['status']) ?>">
                        <?= htmlspecialchars($ticket['status']) ?>
                    </span>
                    <a href="al_my_tickets.php" 
                       class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Tickets
                    </a>
                </div>
            </div>
            
            <!-- Ticket Info -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-4 border-t border-gray-200">
                <div>
                    <h4 class="font-semibold text-gray-900 mb-2">Ticket Details</h4>
                    <p class="text-sm text-gray-600">
                        <strong>Ticket ID:</strong> #<?= $ticket['id'] ?>
                    </p>
                    <p class="text-sm text-gray-600">
                        <strong>Status:</strong> <?= htmlspecialchars($ticket['status']) ?>
                    </p>
                    <p class="text-sm text-gray-600">
                        <strong>Created:</strong> <?= date('M d, Y g:i A', strtotime($ticket['submitted_at'])) ?>
                    </p>
                </div>
                <div>
                    <h4 class="font-semibold text-gray-900 mb-2">Your Information</h4>
                    <p class="text-sm text-gray-600">
                        <strong>Name:</strong> <?= htmlspecialchars($ticket['name']) ?>
                    </p>
                    <p class="text-sm text-gray-600">
                        <strong>Email:</strong> <?= htmlspecialchars($ticket['email']) ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Conversation Thread -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-6">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-900">Conversation</h2>
                <p class="text-gray-600 mt-1">Full conversation history for this ticket</p>
            </div>
            
            <div class="p-6 space-y-6">
                <!-- Original Message -->
                <div class="flex items-start space-x-4">
                    <div class="flex-shrink-0">
                        <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-user text-green-600"></i>
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="text-sm font-semibold text-gray-900">You</h4>
                                <span class="text-xs text-gray-500"><?= formatTimeAgo($ticket['submitted_at']) ?></span>
                            </div>
                            <p class="text-sm text-gray-700 whitespace-pre-wrap"><?= nl2br(htmlspecialchars($ticket['message'])) ?></p>
                        </div>
                    </div>
                </div>

                <!-- Admin Replies -->
                <?php foreach ($replies as $reply): ?>
                    <div class="flex items-start space-x-4">
                        <div class="flex-shrink-0">
                            <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-user-tie text-blue-600"></i>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="bg-blue-50 rounded-lg p-4">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="text-sm font-semibold text-gray-900">
                                        <?= htmlspecialchars($reply['firstname'] . ' ' . $reply['lastname']) ?: 'Support Team' ?>
                                    </h4>
                                    <span class="text-xs text-gray-500"><?= formatTimeAgo($reply['created_at']) ?></span>
                                </div>
                                <p class="text-sm text-gray-700 whitespace-pre-wrap"><?= nl2br(htmlspecialchars($reply['message'])) ?></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if (empty($replies)): ?>
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-comments text-4xl mb-4"></i>
                        <p>No replies yet. Our support team will respond soon.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Reply Form -->
        <?php if ($ticket['status'] !== 'Resolved'): ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-xl font-semibold text-gray-900">Add Reply</h2>
                    <p class="text-gray-600 mt-1">Send a follow-up message to our support team</p>
                </div>
                
                <form method="POST" class="p-6">
                    <div class="mb-4">
                        <label for="reply_message" class="block text-sm font-medium text-gray-700 mb-2">
                            Your Message
                        </label>
                        <textarea 
                            id="reply_message" 
                            name="reply_message" 
                            rows="6" 
                            required
                            placeholder="Type your reply here..."
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 resize-none"
                        ></textarea>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-gray-600">
                            <i class="fas fa-info-circle mr-1"></i>
                            Your reply will be sent to our support team
                        </p>
                        <button type="submit" 
                                class="btn-primary inline-flex items-center px-6 py-3 text-white rounded-lg shadow-md">
                            <i class="fas fa-paper-plane mr-2"></i>
                            Send Reply
                        </button>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <div class="bg-green-50 border border-green-200 rounded-xl p-6 text-center">
                <i class="fas fa-check-circle text-green-600 text-4xl mb-4"></i>
                <h3 class="text-lg font-semibold text-green-800 mb-2">Ticket Resolved</h3>
                <p class="text-green-700">This ticket has been marked as resolved. If you need further assistance, please create a new ticket.</p>
                <a href="al_contact.php#support-request" 
                   class="inline-flex items-center px-6 py-3 mt-4 bg-green-600 text-white rounded-lg hover:bg-green-700">
                    <i class="fas fa-plus mr-2"></i>
                    Create New Ticket
                </a>
            </div>
        <?php endif; ?>
    </main>

    <script>
        // Auto-resize textarea
        const textarea = document.getElementById('reply_message');
        if (textarea) {
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = this.scrollHeight + 'px';
            });
        }
    </script>
</body>
</html>
