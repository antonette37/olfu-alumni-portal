<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'login_errors.log');

session_start();

require_once 'db_config.php';
require_once 'mail.php';

$isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
if ($isAdmin) {
    header('Location: ad_dashboard.php');
    exit;
}

$conn = getDBConnection();
if ($conn->connect_error) {
	error_log('DB error (forgot): ' . $conn->connect_error);
	http_response_code(500);
	die('Internal server error');
}

// Ensure password_resets table exists
$conn->query("CREATE TABLE IF NOT EXISTS password_resets (
	id INT AUTO_INCREMENT PRIMARY KEY,
	email VARCHAR(255) NOT NULL,
	token_hash CHAR(64) NOT NULL,
	expires_at DATETIME NOT NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	INDEX (email),
	INDEX (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$statusMsg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$email = strtolower(trim($_POST['email'] ?? ''));
	if ($email === '') {
		$errorMsg = 'Please enter your email address.';
	} else {
		// Check if user exists
		$exists = false;
		$stmt = $conn->prepare('SELECT id, firstname, lastname FROM itcp WHERE LOWER(email) = ? LIMIT 1');
		if ($stmt) {
			$stmt->bind_param('s', $email);
			if ($stmt->execute()) {
				$stmt->store_result();
				if ($stmt->num_rows === 1) { $exists = true; }
			}
			$stmt->close();
		}

		// Always respond similarly to avoid user enumeration, but only send if exists
		$statusMsg = 'If this email is registered, a password reset link has been sent.';

		if ($exists) {
			try {
				// Invalidate old tokens for this email
				$conn->query("DELETE FROM password_resets WHERE email='" . $conn->real_escape_string($email) . "'");

				// Create new token
				$token = bin2hex(random_bytes(32));
				$tokenHash = hash('sha256', $token);
				$expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour

				$ins = $conn->prepare('INSERT INTO password_resets (email, token_hash, expires_at) VALUES (?, ?, ?)');
				if (!$ins) { throw new Exception('Prepare failed: ' . $conn->error); }
				$ins->bind_param('sss', $email, $tokenHash, $expiresAt);
				if (!$ins->execute()) { throw new Exception('Execute failed: ' . $ins->error); }
				$ins->close();

				// Build reset link
				$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
				$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
				$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
				$resetUrl = $scheme . '://' . $host . $basePath . '/al_reset_password.php?email=' . urlencode($email) . '&token=' . urlencode($token);

				$subject = 'Reset your Alumni Portal password';
				$body = '<p>Hello,</p>' .
						'<p>We received a request to reset your password. Click the link below to set a new password:</p>' .
						'<p><a href="' . htmlspecialchars($resetUrl) . '">' . htmlspecialchars($resetUrl) . '</a></p>' .
						'<p>This link will expire in 1 hour. If you did not request this, please ignore this email.</p>' .
						'<p>Regards,<br/>OLFU Alumni Portal</p>';

				// Send email (ignore result to avoid leaking existence)
				sendEmail($email, $subject, $body);
			} catch (Exception $e) {
				error_log('Forgot password error: ' . $e->getMessage());
			}
		}
	}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<meta content="width=device-width, initial-scale=1" name="viewport" />
	<title>Forgot Password</title>
	<script src="https://cdn.tailwindcss.com"></script>
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet" />
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
	<style>
		body { font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial; }
	</style>
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
				<h1 class="text-2xl font-extrabold text-center text-green-800">Forgot your password?</h1>
				<p class="text-center text-gray-600 mt-1">Enter your email and we’ll send a reset link</p>

				<?php if ($errorMsg): ?>
					<div class="mt-4 p-3 rounded-md bg-red-50 text-red-700 text-sm border border-red-200" role="alert">
						<i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($errorMsg); ?>
					</div>
				<?php endif; ?>
				<?php if ($statusMsg): ?>
					<div class="mt-4 p-3 rounded-md bg-green-50 text-green-700 text-sm border border-green-200" role="status">
						<i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($statusMsg); ?>
					</div>
				<?php endif; ?>

				<form class="mt-6 space-y-5" method="POST" action="al_forgot_password.php">
					<div>
						<label for="email" class="block text-sm font-medium text-gray-700">Email address</label>
						<input type="email" id="email" name="email" autocomplete="email" required placeholder="you@example.com" class="mt-1 w-full border border-gray-300 rounded-lg p-2 focus:outline-none focus:ring-2 focus:ring-green-600 focus:border-green-600" />
					</div>
					<button type="submit" class="w-full bg-green-700 hover:bg-green-800 text-white py-2 rounded-lg font-semibold transition">Send reset link</button>
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
?>
