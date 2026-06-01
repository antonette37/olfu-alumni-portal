<?php
session_start();
require_once 'db_config.php';
$conn = getDBConnection();

if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: al_login.php');
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$inquiry = null;
if ($id > 0) {
  // Simple direct query first (most robust across environments)
  $id_i = (int)$id;
  $res0 = $conn->query("SELECT id, name, email, subject, message, status, is_read, submitted_at FROM contact_messages WHERE id = $id_i LIMIT 1");
  if ($res0 && $res0->num_rows > 0) {
    $inquiry = $res0->fetch_assoc();
  }
  // Prepared fallback if direct query failed unexpectedly
  if (!$inquiry) {
    $stmt = $conn->prepare('SELECT id, name, email, subject, message, status, is_read, submitted_at FROM contact_messages WHERE id = ?');
    if ($stmt) {
      $stmt->bind_param('i', $id);
      $stmt->execute();
      if (method_exists($stmt, 'get_result')) {
        $res = $stmt->get_result();
        if ($res) { $inquiry = $res->fetch_assoc(); }
      }
      if (!$inquiry) {
        $stmt->store_result();
        $stmt->bind_result($rid, $rname, $remail, $rsubject, $rmessage, $rstatus, $ris_read, $rsubmitted_at);
        if ($stmt->fetch()) {
          $inquiry = [
            'id' => $rid,
            'name' => $rname,
            'email' => $remail,
            'subject' => $rsubject,
            'message' => $rmessage,
            'status' => $rstatus,
            'is_read' => $ris_read,
            'submitted_at' => $rsubmitted_at,
          ];
        }
      }
      $stmt->close();
    }
  }
}

if (!$inquiry) {
  ?><!DOCTYPE html>
  <html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta content="width=device-width, initial-scale=1" name="viewport" />
    <title>Inquiry Not Found • Admin Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
  </head>
  <body class="bg-gray-50">
    <?php include 'ad_header_universal.php'; ?>
    <?php include 'ad_sidebar_universal.php'; ?>
    <main class="pt-24 ml-16 px-4 md:px-6 max-w-3xl mx-auto">
      <div class="glassmorphism p-8 text-center">
        <div class="text-red-500 text-4xl mb-3"><i class="fas fa-exclamation-triangle"></i></div>
        <h1 class="text-xl font-semibold text-gray-900 mb-2">Inquiry not found</h1>
        <p class="text-gray-600 mb-6">The inquiry you're looking for may have been removed or the link is invalid.</p>
        <a href="ad_contactmessages.php" class="inline-flex items-center px-5 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700"><i class="fas fa-arrow-left mr-2"></i>Back to Inquiries</a>
      </div>
    </main>
  </body>
  </html><?php
  exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta content="width=device-width, initial-scale=1" name="viewport" />
  <title>Inquiry #<?= (int)$inquiry['id'] ?> • Admin Portal</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet" />
  <style>
    body {
      font-family: "Inter", sans-serif;
      background: #f8fafc;
      color: #1e293b;
      min-height: 100vh;
    }
    .glassmorphism {
      background: rgba(255, 255, 255, 0.9);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      border-radius: 1rem;
      border: 1px solid rgba(0, 0, 0, 0.1);
      box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.1);
      transition: box-shadow 0.3s ease;
    }
    .glassmorphism:hover,
    .glassmorphism:focus-within {
      box-shadow: 0 12px 48px 0 rgba(0, 0, 0, 0.15);
      border-color: rgba(0, 0, 0, 0.15);
    }
    .card-hover:hover {
      box-shadow: 0 12px 24px rgba(128, 0, 0, 0.3);
      transform: translateY(-2px);
      transition: all 0.3s ease;
    }
    .status-badge {
      display: inline-flex;
      align-items: center;
      padding: 0.25rem 0.75rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 600;
      border: 1px solid;
    }
    .status-new {
      background-color: #fef2f2;
      color: #dc2626;
      border-color: #fecaca;
    }
    .status-in-progress {
      background-color: #fffbeb;
      color: #d97706;
      border-color: #fed7aa;
    }
    .status-resolved {
      background-color: #f0fdf4;
      color: #16a34a;
      border-color: #bbf7d0;
    }
    .status-spam {
      background-color: #f9fafb;
      color: #6b7280;
      border-color: #d1d5db;
    }
    .message-content {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 0.5rem;
      padding: 1.5rem;
      font-size: 0.875rem;
      line-height: 1.6;
      white-space: pre-wrap;
      word-wrap: break-word;
    }
    .info-card {
      background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
      border: 1px solid #e2e8f0;
      border-radius: 0.75rem;
      padding: 1.5rem;
    }
    .action-buttons {
      display: flex;
      flex-wrap: wrap;
      gap: 0.75rem;
      align-items: center;
    }
    @media (max-width: 768px) {
      .action-buttons {
        flex-direction: column;
        align-items: stretch;
      }
      .action-buttons button,
      .action-buttons a {
        width: 100%;
        justify-content: center;
      }
    }
  </style>
</head>
<body class="bg-gray-50">
  <?php include 'ad_header_universal.php'; ?>
  <?php include 'ad_sidebar_universal.php'; ?>
  <?php 
    // Normalize fields to avoid warnings and compute display values
    $rid = (int)($inquiry['id'] ?? 0);
    $name = isset($inquiry['name']) ? (string)$inquiry['name'] : '';
    $email = isset($inquiry['email']) ? (string)$inquiry['email'] : '';
    $subjectRaw = isset($inquiry['subject']) ? trim((string)$inquiry['subject']) : '';
    $messageRaw = isset($inquiry['message']) ? (string)$inquiry['message'] : '';
    $submittedAt = isset($inquiry['submitted_at']) ? (string)$inquiry['submitted_at'] : '';
    $st = isset($inquiry['status']) ? (string)$inquiry['status'] : 'New';
    $isRead = !empty($inquiry['is_read']);
    $titleSubject = $subjectRaw !== '' ? $subjectRaw : ('Inquiry #' . $rid);
    $submittedDisplay = $submittedAt !== '' ? date('M d, Y g:i A', strtotime($submittedAt)) : '';
  ?>
  <main id="content" class="pt-24 ml-16 px-4 md:px-6 max-w-6xl mx-auto">
    <!-- Header Section -->
    <div class="mb-8">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <a href="ad_contactmessages.php" class="inline-flex items-center text-sm text-gray-600 hover:text-green-700 transition-colors duration-200">
          <i class="fas fa-arrow-left mr-2"></i>Back to Inquiries
        </a>
        <div class="flex items-center gap-3">
          <?php 
            $statusClass = match($st) {
              'New' => 'status-new',
              'In Progress' => 'status-in-progress', 
              'Resolved' => 'status-resolved',
              'Spam' => 'status-spam',
              default => 'status-new'
            };
          ?>
          <span class="status-badge <?= $statusClass ?>">
            <i class="fas fa-circle mr-1 text-xs"></i><?= htmlspecialchars($st) ?>
          </span>
          <?php if (!$isRead): ?>
            <span class="status-badge bg-red-50 text-red-700 border-red-200">
              <i class="fas fa-exclamation-circle mr-1"></i>Unread
            </span>
          <?php else: ?>
            <span class="status-badge bg-gray-100 text-gray-600 border-gray-200">
              <i class="fas fa-check-circle mr-1"></i>Read
            </span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Inquiry Header -->
    <section class="glassmorphism p-8 card-hover mb-8">
      <div class="mb-6">
        <h1 class="text-2xl md:text-3xl font-bold text-gray-900 mb-2">
          <?= htmlspecialchars($titleSubject) ?>
        </h1>
        <div class="flex items-center gap-4 text-sm text-gray-500">
          <span><i class="fas fa-calendar mr-1"></i>Submitted <?= htmlspecialchars($submittedDisplay) ?></span>
          <span><i class="fas fa-hashtag mr-1"></i>Ticket #<?= $rid ?></span>
        </div>
      </div>
      
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Contact Information -->
        <div class="info-card">
          <h4 class="font-semibold text-gray-900 mb-4 flex items-center">
            <i class="fas fa-user mr-2 text-green-600"></i>Contact Information
          </h4>
          <div class="space-y-3">
            <div>
              <span class="text-sm font-medium text-gray-600">Name:</span>
              <p class="text-sm text-gray-900 font-medium"><?= htmlspecialchars($name) ?></p>
            </div>
            <div>
              <span class="text-sm font-medium text-gray-600">Email:</span>
              <p class="text-sm">
                <a href="mailto:<?= htmlspecialchars($email) ?>" class="text-blue-600 hover:text-blue-800 font-medium">
                  <?= htmlspecialchars($email) ?>
                </a>
              </p>
            </div>
          </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="info-card">
          <h4 class="font-semibold text-gray-900 mb-4 flex items-center">
            <i class="fas fa-tools mr-2 text-green-600"></i>Quick Actions
          </h4>
          <div class="space-y-4">
            <form method="post" action="update_inquiry_status.php" class="space-y-3">
              <input type="hidden" name="id" value="<?= $rid ?>">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Update Status</label>
                <div class="action-buttons">
                  <select name="status" class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    <?php foreach(['New','In Progress','Resolved','Spam'] as $s): ?>
                      <option value="<?= $s ?>" <?= $st===$s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700 transition-colors duration-200">
                    <i class="fas fa-save mr-2"></i>Update
                  </button>
                </div>
              </div>
            </form>
            
            <div class="action-buttons">
              <a href="mailto:<?= htmlspecialchars($email) ?>?subject=<?= rawurlencode('Re: ' . $titleSubject) ?>" 
                 class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 transition-colors duration-200">
                <i class="fas fa-reply mr-2"></i>Email Reply
              </a>
              <button onclick="markAsRead()" class="px-4 py-2 bg-purple-600 text-white rounded-lg text-sm hover:bg-purple-700 transition-colors duration-200">
                <i class="fas fa-check mr-2"></i>Mark as Read
              </button>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Message Content -->
    <section class="glassmorphism p-8 card-hover mb-8">
      <h3 class="text-xl font-semibold text-gray-900 mb-6 flex items-center">
        <i class="fas fa-envelope mr-2 text-green-600"></i>Message Content
      </h3>
      <div class="message-content">
        <?= nl2br(htmlspecialchars($messageRaw)) ?>
      </div>
    </section>

    <!-- Reply Section -->
    <section class="glassmorphism p-8 card-hover">
      <h3 class="text-xl font-semibold text-gray-900 mb-6 flex items-center">
        <i class="fas fa-paper-plane mr-2 text-green-600"></i>Send Reply
      </h3>
      <form method="post" action="reply_inquiry.php" class="space-y-6">
        <input type="hidden" name="id" value="<?= $rid ?>">
        <input type="hidden" name="to" value="<?= htmlspecialchars($email) ?>">
        
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Subject</label>
          <input type="text" name="subject" value="<?= htmlspecialchars('Re: ' . $titleSubject) ?>" 
                 class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors duration-200">
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Message</label>
          <textarea name="body" rows="8" 
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors duration-200"
                    placeholder="Type your reply here..."></textarea>
        </div>
        
        <div class="flex flex-col sm:flex-row items-center gap-4">
          <button type="submit" class="w-full sm:w-auto px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors duration-200 font-medium">
            <i class="fas fa-paper-plane mr-2"></i>Send Reply
          </button>
          <a href="ad_contactmessages.php" class="w-full sm:w-auto px-6 py-3 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors duration-200 font-medium text-center">
            <i class="fas fa-times mr-2"></i>Cancel
          </a>
        </div>
      </form>
    </section>
  </main>

  <script>
    function markAsRead() {
      if (confirm('Mark this inquiry as read?')) {
        fetch('mark_inquiry_read.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: 'id=<?= $rid ?>'
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            location.reload();
          } else {
            alert('Failed to mark as read');
          }
        })
        .catch(error => {
          console.error('Error:', error);
          alert('An error occurred');
        });
      }
    }
  </script>

