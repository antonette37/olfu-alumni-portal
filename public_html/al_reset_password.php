<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'login_errors.log');

session_start();

require_once 'db_config.php';
$isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
if ($isAdmin) {
    header('Location: ad_dashboard.php');
    exit;
}
$conn = getDBConnection();
if ($conn->connect_error) {
    error_log('DB error (reset): ' . $conn->connect_error);
    http_response_code(500);
    die('Internal server error');
}

$email = strtolower(trim($_GET['email'] ?? $_POST['email'] ?? ''));
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$error = '';
$status = '';
$tokenValid = false;

function strong_password($p) {
    if (strlen($p) < 8) return false;
    $hasUpper = preg_match('/[A-Z]/', $p);
    $hasLower = preg_match('/[a-z]/', $p);
    $hasDigit = preg_match('/\d/', $p);
    $hasSpec  = preg_match('/[^A-Za-z0-9]/', $p);
    return $hasUpper && $hasLower && $hasDigit && $hasSpec;
}

// Pre-validate token on first load to decide whether to show the form
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $email !== '' && $token !== '') {
    try {
        $tokenHash = hash('sha256', $token);
        $stmt = $conn->prepare('SELECT id FROM password_resets WHERE email = ? AND token_hash = ? AND expires_at > NOW() LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('ss', $email, $tokenHash);
            if ($stmt->execute()) {
                $stmt->store_result();
                $tokenValid = ($stmt->num_rows === 1);
            }
            $stmt->close();
        }
        if (!$tokenValid) {
            $error = 'Invalid or expired password reset link.';
        }
    } catch (Exception $e) {
        error_log('Reset precheck error: ' . $e->getMessage());
        $error = 'An error occurred. Please try again later.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    if ($email === '' || $token === '') {
        $error = 'Invalid password reset link.';
    } elseif ($password === '' || $confirm === '' || $password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (!strong_password($password)) {
        $error = 'Password must be at least 8 chars and include uppercase, lowercase, number, and symbol.';
    } else {
        try {
            $tokenHash = hash('sha256', $token);
            $stmt = $conn->prepare('SELECT id FROM password_resets WHERE email = ? AND token_hash = ? AND expires_at > NOW() LIMIT 1');
            if (!$stmt) { throw new Exception('Prepare failed: ' . $conn->error); }
            $stmt->bind_param('ss', $email, $tokenHash);
            if (!$stmt->execute()) { throw new Exception('Execute failed: ' . $stmt->error); }
            $stmt->store_result();
            if ($stmt->num_rows === 1) {
                $stmt->close();
                // Update password
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $up = $conn->prepare('UPDATE itcp SET password = ? WHERE LOWER(email) = ? LIMIT 1');
                if (!$up) { throw new Exception('Prepare failed: ' . $conn->error); }
                $up->bind_param('ss', $newHash, $email);
                if (!$up->execute()) { throw new Exception('Execute failed: ' . $up->error); }
                $up->close();
                // Consume token(s)
                $del = $conn->prepare('DELETE FROM password_resets WHERE email = ?');
                if ($del) { $del->bind_param('s', $email); $del->execute(); $del->close(); }
                // Redirect to login with success message
                header('Location: al_login.php?reset=success');
                exit;
            } else {
                $error = 'Invalid or expired password reset link.';
                $stmt->close();
            }
        } catch (Exception $e) {
            error_log('Reset error: ' . $e->getMessage());
            $error = 'An error occurred. Please try again later.';
        }
    }
}
?>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta content="width=device-width, initial-scale=1" name="viewport" />
  <title>Reset Password</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
  <style>
    body { font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial; }
  </style>
<?php /* Optional header include */ ?>
</head>
<body class="min-h-screen bg-gradient-to-br from-green-50 to-white">
  <div class="absolute inset-0">
    <img src="ccsbg.png" alt="Background" class="w-full h-full object-cover opacity-10" />
  </div>
  <div class="relative min-h-screen flex items-center justify-center px-4">
    <div class="w-full max-w-3xl grid grid-cols-1 md:grid-cols-2 bg-white/90 backdrop-blur rounded-2xl shadow-xl ring-1 ring-green-100 overflow-hidden">
      <div class="hidden md:block relative">
        <img src="LOGINBG.jpg" alt="Reset Password" class="w-full h-full object-cover" />
      </div>
      <div class="p-6 sm:p-8">
        <div class="mb-3">
          <a href="al_login.php" class="inline-flex items-center text-gray-600 hover:text-green-800 text-sm">
            <i class="fas fa-arrow-left mr-2"></i>
            Back to Login
          </a>
        </div>
        <div class="flex items-center justify-center mb-4">
          <img src="olfulogo.png" alt="OLFU" class="h-12 w-auto mr-2" />
          <img src="ccs_logo.png" alt="CCS" class="h-12 w-auto" />
        </div>
        <h1 class="text-2xl font-extrabold text-center text-green-800">Set a new password</h1>
        <p class="text-center text-gray-600 mt-1">Enter and confirm your new password</p>

        <?php if ($error): ?>
          <div class="mt-4 p-3 rounded-md bg-red-50 text-red-700 text-sm border border-red-200" role="alert">
            <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
          </div>
        <?php endif; ?>
        <?php if ($status): ?>
          <div class="mt-4 p-3 rounded-md bg-green-50 text-green-700 text-sm border border-green-200" role="status">
            <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($status); ?>
          </div>
        <?php endif; ?>

        <form class="mt-6 space-y-5" method="POST" action="al_reset_password.php">
          <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>" />
          <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>" />
          <div>
            <label for="password" class="block text-sm font-medium text-gray-700">New password</label>
            <input type="password" id="password" name="password" required placeholder="********" class="mt-1 w-full border border-gray-300 rounded-lg p-2 focus:outline-none focus:ring-2 focus:ring-green-600 focus:border-green-600" />
            <p class="mt-1 text-xs text-gray-500">At least 8 characters with uppercase, lowercase, number, and symbol.</p>
          </div>
          <div>
            <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm password</label>
            <input type="password" id="confirm_password" name="confirm_password" required placeholder="********" class="mt-1 w-full border border-gray-300 rounded-lg p-2 focus:outline-none focus:ring-2 focus:ring-green-600 focus:border-green-600" />
          </div>
          <button type="submit" class="w-full bg-green-700 hover:bg-green-800 text-white py-2 rounded-lg font-semibold transition">Reset password</button>
        </form>

        <div class="mt-6 text-center">
          <a href="al_login.php" class="text-xs text-gray-500 hover:text-gray-700">Back to Login</a>
        </div>
      </div>
    </div>
  </div>
<?php if (file_exists(__DIR__ . '/al_footer_universal.php')) { include 'al_footer_universal.php'; } ?>
</body>
</html>


