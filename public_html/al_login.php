<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'login_errors.log');

session_start();

require_once 'db_config.php';
require_once __DIR__ . '/includes/mysqli_compat.php';
alumni_otp_gate_after_session();
$conn = getDBConnection();
if ($conn->connect_error) {
    error_log('Database connection failed: ' . $conn->connect_error);
    http_response_code(500);
    die('Internal server error');
}

function get_safe_redirect($candidate, $default = 'al_homepage.php') {
    $candidate = trim((string)$candidate);
    if ($candidate === '') return $default;
    if (preg_match('/^([a-zA-Z]+:)?\/\//', $candidate)) return $default;
    if (strpos($candidate, '..') !== false) return $default;
    return $candidate;
}

$error = '';
$redirect = get_safe_redirect($_GET['redirect'] ?? $_POST['redirect'] ?? 'al_homepage.php');

if (isset($_SESSION['user_id'])) {
    header('Location: ' . $redirect);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailInput = trim($_POST['email'] ?? '');
    $email = strtolower($emailInput);
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['rememberMe']);

    if ($email === '' || $password === '') {
        $error = 'Please enter both email and password.';
    } else {
        $admin_username = "admin";
        $admin_password = getenv('ADMIN_PASSWORD') ?: ''; // Must be set in environment

        if ($emailInput === $admin_username && !empty($admin_password) && $password === $admin_password) {
            $_SESSION['admin_logged_in'] = true;
            header("Location: ad_dashboard.php");
            exit();
        } else {
            try {
                $stmt = $conn->prepare('SELECT id, email, password, firstname, lastname, status FROM itcp WHERE LOWER(email) = ?');
                if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
                $stmt->bind_param('s', $email);
                if (!$stmt->execute()) throw new Exception('Execute failed: ' . $stmt->error);

                $users = mysqli_stmt_fetch_all_assoc_compat($stmt);
                $stmt->close();

                $user = null;
                $password_issue = null;

                foreach ($users as $candidate) {
                    if (!isset($candidate['password']) || empty($candidate['password'])) {
                        $password_issue = 'Password not set for this account.';
                        continue;
                    }
                    if (!is_string($candidate['password'])) {
                        $password_issue = 'Password field format invalid.';
                        continue;
                    }
                    if (password_verify($password, $candidate['password'])) {
                        $user = $candidate;
                        break;
                    }
                }

                if ($user) {
                    $status = strtolower(trim((string)$user['status']));
                    if ($status === 'pending') {
                        $error = 'Your account is pending approval. Please wait for coordinator review.';
                    } elseif ($status === 'rejected') {
                        $error = 'Your registration has been rejected. Please contact Alumni Affairs.';
                    } elseif ($status === 'active' || $status === 'approved') {
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['firstname'] = $user['firstname'];
                        $_SESSION['lastname'] = $user['lastname'];
                        $_SESSION['user_type'] = 'alumni';
                        $_SESSION['auth_at'] = time();
                        if ($remember && session_status() === PHP_SESSION_ACTIVE) {
                            $params = session_get_cookie_params();
                            setcookie(session_name(), session_id(), time() + (86400 * 30), $params['path'], $params['domain'], $params['secure'], $params['httponly']);
                        }
                        @require_once 'user_logging.php';
                        if (function_exists('logUserLogin')) {
                            logUserLogin($conn, $user['id'], $user['firstname'] . ' ' . $user['lastname']);
                        }
                        if (!defined('SKIP_ALUMNI_OTP_GATE') || !constant('SKIP_ALUMNI_OTP_GATE')) {
                            require_once __DIR__ . '/includes/otp_device_lib.php';
                            otp_device_ensure_schema($conn);
                            if (otp_device_needs_verification($conn, (int)$user['id'])) {
                                $_SESSION['otp_required_after_login'] = true;
                                header('Location: al_otp_verify.php?redirect=' . rawurlencode($redirect));
                                exit;
                            }
                        }
                        header('Location: ' . $redirect);
                        exit;
                    } else {
                        $error = 'Your account status is invalid. Please contact support.';
                    }
                } else {
                    if ($password_issue) {
                        $error = 'Account issue detected. Please contact support for assistance.';
                    } else {
                        if (count($users) > 0) {
                            $fu = $users[0];
                            $fus = strtolower(trim((string)$fu['status']));
                            $hp = !empty($fu['password']) && is_string($fu['password']);
                            if (!$hp) $error = 'Your account password is not set. Please contact support.';
                            elseif ($fus === 'pending') $error = 'Your account is pending approval. Please wait for coordinator review.';
                            elseif ($fus === 'rejected') $error = 'Your registration has been rejected. Please contact Alumni Affairs.';
                            else $error = 'Invalid email or password. Please check your credentials and try again.';
                        } else {
                            $error = 'Invalid email or password.';
                        }
                    }
                }
            } catch (Exception $e) {
                error_log('Login error: ' . $e->getMessage());
                $error = 'An error occurred during login. Please try again later.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Sign In — CCS Alumni Portal</title>
  <link rel="icon" href="olfulogo.png" type="image/png" />
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400;1,500&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --forest:  #0d2e18;
      --pine:    #133d23;
      --leaf:    #1b5e35;
      --moss:    #2d7a4f;
      --fern:    #3d9966;
      --sage:    #a8c9b0;
      --mist:    #e8f2ec;
      --snow:    #f5f9f6;
      --white:   #ffffff;
      --gold:    #b8922a;
      --gold-lt: #e0b84a;
      --ink:     #0c1a10;
      --charcoal:#2a3d30;
      --slate:   #4a6355;
      --silver:  #8aab96;
      --fog:     #c8ddd2;
      --ease:    cubic-bezier(.22,.68,0,1.2);
    }

    html, body { height: 100%; font-family: 'Outfit', system-ui, sans-serif; background: var(--forest); overflow: hidden; }

    .stage {
      display: grid;
      grid-template-columns: 1fr 470px;
      height: 100vh;
    }
    @media (max-width: 860px) {
      html, body { overflow: auto; }
      .stage { grid-template-columns: 1fr; height: auto; }
      .hero-col { display: none; }
    }

    /* ── HERO ── */
    .hero-col {
      position: relative;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      justify-content: flex-end;
    }
    .hero-bg {
      position: absolute; inset: 0;
      background-image: url('LOGINBG.jpg');
      background-size: cover;
      background-position: center top;
      animation: ken-burns 22s ease-in-out infinite alternate;
    }
    @keyframes ken-burns {
      from { transform: scale(1.04) translate(0,0); }
      to   { transform: scale(1.11) translate(-1%,-1.5%); }
    }
    .hero-col::before {
      content: ''; position: absolute; inset: 0; z-index: 1;
      background:
        linear-gradient(to bottom,  rgba(13,46,24,.6) 0%,  transparent 40%),
        linear-gradient(to top,     rgba(13,46,24,.95) 0%, rgba(13,46,24,.3) 55%, transparent 75%),
        linear-gradient(to right,   rgba(13,46,24,.35) 0%, transparent 55%);
    }
    /* Grain */
    .hero-col::after {
      content: ''; position: absolute; inset: 0; z-index: 2;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='180' height='180'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.8' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='180' height='180' filter='url(%23n)' opacity='.04'/%3E%3C/svg%3E");
      pointer-events: none;
    }

    .hero-logos {
      position: absolute; top: 2.25rem; left: 2.75rem; z-index: 4;
      display: flex; align-items: center; gap: .875rem;
    }
    .hero-logos img { height: 44px; width: auto; filter: drop-shadow(0 2px 10px rgba(0,0,0,.5)); }
    .hero-divider   { width: 1px; height: 30px; background: rgba(255,255,255,.2); }
    .hero-school    { font-size: .73rem; font-weight: 500; letter-spacing: .1em; text-transform: uppercase; line-height: 1.55; color: rgba(255,255,255,.55); }

    /* watermark center quote */
    .hero-watermark {
      position: absolute; inset: 0; z-index: 3;
      display: flex; align-items: center; justify-content: center;
      pointer-events: none;
    }
    .hero-watermark-text {
      font-family: 'Cormorant Garamond', serif;
      font-size: clamp(1.5rem, 2.5vw, 2.1rem);
      font-style: italic; font-weight: 400;
      color: rgba(255,255,255,.11);
      letter-spacing: .06em;
      text-align: center;
      line-height: 1.5;
    }

    .hero-content {
      position: relative; z-index: 4;
      padding: 2.75rem 3rem;
    }
    .hero-badge {
      display: inline-flex; align-items: center; gap: .5rem;
      font-size: .62rem; font-weight: 600; letter-spacing: .2em; text-transform: uppercase;
      color: var(--gold-lt); margin-bottom: 1.1rem;
    }
    .hero-badge-dot { width: 5px; height: 5px; border-radius: 50%; background: var(--gold-lt); }
    .hero-h2 {
      font-family: 'Cormorant Garamond', serif;
      font-size: clamp(2.4rem, 3.8vw, 3.2rem); font-weight: 500;
      color: #fff; line-height: 1.1; margin-bottom: 1.1rem;
    }
    .hero-h2 em { font-style: italic; color: var(--gold-lt); }
    .hero-p {
      font-size: .855rem; font-weight: 300;
      color: rgba(255,255,255,.58); line-height: 1.85;
      max-width: 25rem; margin-bottom: 2rem;
    }
    .stats {
      display: flex; gap: 0;
      border-top: 1px solid rgba(255,255,255,.1);
      padding-top: 1.6rem;
    }
    .stat { flex: 1; }
    .stat + .stat { padding-left: 1.5rem; border-left: 1px solid rgba(255,255,255,.1); }
    .stat-n {
      font-family: 'Cormorant Garamond', serif;
      font-size: 2rem; font-weight: 500; color: #fff; line-height: 1;
    }
    .stat-n sup { font-size: .9rem; color: var(--gold-lt); font-style: italic; }
    .stat-l { font-size: .62rem; font-weight: 500; letter-spacing: .12em; text-transform: uppercase; color: rgba(255,255,255,.38); margin-top: .3rem; }

    /* ── FORM COLUMN ── */
    .form-col {
      background: var(--snow);
      display: flex; flex-direction: column; justify-content: center;
      overflow-y: auto; position: relative;
    }
    /* Gold pinline top */
    .form-col::before {
      content: ''; position: absolute; top: 0; left: 0; right: 0;
      height: 3px;
      background: linear-gradient(90deg, var(--leaf) 0%, var(--gold) 50%, var(--leaf) 100%);
    }
    /* Subtle radial corner warmth */
    .form-col::after {
      content: ''; position: absolute; bottom: 0; right: 0;
      width: 240px; height: 240px;
      background: radial-gradient(circle at bottom right, rgba(184,146,42,.07) 0%, transparent 70%);
      pointer-events: none;
    }

    .form-inner {
      padding: 3rem 3rem 2.5rem;
      position: relative; z-index: 1;
      width: 100%; max-width: 410px;
      margin: 0 auto;
    }

    .mobile-brand {
      display: none; align-items: center; gap: .75rem;
      margin-bottom: 1.75rem;
    }
    .mobile-brand img { height: 40px; }
    @media (max-width: 860px) {
      .mobile-brand { display: flex; }
      .form-inner { padding: 2.5rem 1.75rem; max-width: 100%; }
    }

    .back-btn {
      display: inline-flex; align-items: center; gap: .45rem;
      font-size: .7rem; font-weight: 600; letter-spacing: .1em; text-transform: uppercase;
      color: var(--silver); text-decoration: none; margin-bottom: 2.25rem;
      transition: color .2s;
    }
    .back-btn:hover { color: var(--leaf); }
    .back-btn i { font-size: .65rem; }

    .form-kicker {
      display: flex; align-items: center; gap: .6rem;
      font-size: .62rem; font-weight: 700; letter-spacing: .22em; text-transform: uppercase;
      color: var(--moss); margin-bottom: .55rem;
    }
    .form-kicker::before { content: ''; width: 20px; height: 2px; background: var(--gold); border-radius: 1px; display: inline-block; }

    .form-title {
      font-family: 'Cormorant Garamond', serif;
      font-size: 2.6rem; font-weight: 500;
      color: var(--forest); line-height: .95;
      margin-bottom: .45rem;
    }
    .form-sub { font-size: .84rem; font-weight: 300; color: var(--slate); margin-bottom: 2rem; }

    /* Alert */
    .alert {
      display: flex; align-items: flex-start; gap: .65rem;
      padding: .85rem 1rem; border-radius: 8px;
      font-size: .81rem; line-height: 1.55;
      margin-bottom: 1.5rem;
      border-left: 3px solid;
    }
    .alert-error   { background: #fdf3f2; color: #c0392b; border-color: #c0392b; }
    .alert-success { background: #f0faf3; color: #1b6e3a; border-color: #1b6e3a; }
    .alert i { flex-shrink: 0; margin-top: .1rem; }

    /* Field */
    .field { margin-bottom: 1.15rem; }
    .field-label {
      display: block;
      font-size: .68rem; font-weight: 700;
      letter-spacing: .1em; text-transform: uppercase;
      color: var(--charcoal); margin-bottom: .45rem;
    }
    .field-wrap { position: relative; }
    .field-input {
      width: 100%; height: 50px;
      background: var(--white);
      border: 1.5px solid var(--fog);
      border-radius: 9px;
      padding: 0 1rem 0 3rem;
      font-family: 'Outfit', sans-serif;
      font-size: .88rem; font-weight: 400;
      color: var(--ink); outline: none;
      transition: border-color .2s, box-shadow .2s;
    }
    .field-input::placeholder { color: var(--silver); font-weight: 300; }
    .field-input:hover { border-color: var(--sage); }
    .field-input:focus { border-color: var(--leaf); box-shadow: 0 0 0 3px rgba(27,94,53,.09); }
    .field-icon {
      position: absolute; left: 1rem; top: 50%; transform: translateY(-50%);
      color: var(--silver); font-size: .8rem; pointer-events: none; transition: color .2s;
    }
    .field-wrap:focus-within .field-icon { color: var(--leaf); }
    .pw-toggle {
      position: absolute; right: 1rem; top: 50%; transform: translateY(-50%);
      background: none; border: none; cursor: pointer;
      color: var(--silver); font-size: .8rem; padding: .25rem; transition: color .2s;
    }
    .pw-toggle:hover { color: var(--charcoal); }

    /* Options */
    .options-row {
      display: flex; align-items: center; justify-content: space-between;
      margin-bottom: 1.6rem;
    }
    .check-label {
      display: flex; align-items: center; gap: .5rem;
      font-size: .81rem; color: var(--slate); cursor: pointer;
    }
    .check-box {
      appearance: none; -webkit-appearance: none;
      width: 17px; height: 17px;
      border: 1.5px solid var(--fog); border-radius: 4px;
      background: var(--white); cursor: pointer;
      position: relative; flex-shrink: 0; transition: all .15s;
    }
    .check-box:checked { background: var(--leaf); border-color: var(--leaf); }
    .check-box:checked::after {
      content: ''; position: absolute;
      left: 4px; top: 1.5px; width: 5px; height: 9px;
      border: 2px solid #fff; border-top: none; border-left: none;
      transform: rotate(42deg);
    }
    .forgot-link { font-size: .81rem; font-weight: 500; color: var(--moss); text-decoration: none; transition: color .2s; }
    .forgot-link:hover { color: var(--forest); text-decoration: underline; }

    /* Submit */
    .btn-submit {
      width: 100%; height: 52px;
      background: var(--forest);
      color: var(--white); border: none; border-radius: 9px;
      font-family: 'Outfit', sans-serif;
      font-size: .85rem; font-weight: 600; letter-spacing: .1em; text-transform: uppercase;
      cursor: pointer; position: relative; overflow: hidden;
      transition: background .25s, transform .15s, box-shadow .25s;
      box-shadow: 0 4px 18px rgba(13,46,24,.24);
    }
    .btn-submit:hover { background: var(--pine); box-shadow: 0 6px 26px rgba(13,46,24,.34); transform: translateY(-1px); }
    .btn-submit:active { transform: translateY(0); }
    /* Gold pinline bottom of button */
    .btn-submit::after {
      content: ''; position: absolute; bottom: 0; left: 20%; right: 20%;
      height: 2px; background: linear-gradient(90deg, transparent, var(--gold-lt), transparent);
      opacity: .5;
    }
    /* Shine */
    .btn-submit::before {
      content: ''; position: absolute; inset: 0;
      background: linear-gradient(108deg, transparent 36%, rgba(255,255,255,.12) 50%, transparent 64%);
      transform: translateX(-100%); transition: transform .5s;
    }
    .btn-submit:hover::before { transform: translateX(100%); }
    .btn-inner { position: relative; display: flex; align-items: center; justify-content: center; gap: .6rem; }

    /* Or row */
    .or-row {
      display: flex; align-items: center; gap: .75rem;
      margin: 1.4rem 0; font-size: .75rem; color: var(--fog);
    }
    .or-row::before, .or-row::after { content: ''; flex: 1; height: 1px; background: var(--fog); }

    /* Signup */
    .signup-text { text-align: center; font-size: .83rem; color: var(--slate); }
    .signup-text a { color: var(--leaf); font-weight: 600; text-decoration: none; transition: color .2s; }
    .signup-text a:hover { color: var(--forest); text-decoration: underline; }

    .form-footer { text-align: center; margin-top: 1.6rem; }
    .form-footer a { font-size: .72rem; color: var(--silver); text-decoration: none; transition: color .2s; }
    .form-footer a:hover { color: var(--slate); }

    /* Animations */
    @keyframes slide-up { from { opacity:0; transform:translateY(18px); } to { opacity:1; transform:translateY(0); } }
    .form-inner > * { animation: slide-up .48s var(--ease) both; }
    .form-inner > *:nth-child(1) { animation-delay:.07s; }
    .form-inner > *:nth-child(2) { animation-delay:.13s; }
    .form-inner > *:nth-child(3) { animation-delay:.19s; }
    .form-inner > *:nth-child(4) { animation-delay:.25s; }
    .form-inner > *:nth-child(5) { animation-delay:.31s; }
    .form-inner > *:nth-child(6) { animation-delay:.37s; }
    .form-inner > *:nth-child(7) { animation-delay:.43s; }
    .form-inner > *:nth-child(8) { animation-delay:.49s; }
    .form-inner > *:nth-child(9) { animation-delay:.55s; }

    @keyframes hero-up { from { opacity:0; transform:translateY(24px); } to { opacity:1; transform:translateY(0); } }
    .hero-logos  { animation: hero-up .7s var(--ease) .1s both; }
    .hero-watermark { animation: hero-up 1s var(--ease) .2s both; }
    .hero-content { animation: hero-up .8s var(--ease) .35s both; }
  </style>
</head>
<body>

<div class="stage">

  <!-- ══ HERO ══ -->
  <div class="hero-col">
    <div class="hero-bg"></div>

    <div class="hero-logos">
      <img src="olfulogo.png" alt="OLFU" />
      <div class="hero-divider"></div>
      <div class="hero-school">
        Alumni Portal<br>
        <span style="color:rgba(255,255,255,.35);font-weight:300;font-size:.68rem;">Our Lady of Fatima University</span>
      </div>
    </div>

    <div class="hero-watermark">
      <div class="hero-watermark-text">
        Respect. Integrity.<br>Excellence. Service.
      </div>
    </div>

    <div class="hero-content">
      <div class="hero-badge">
        <span class="hero-badge-dot"></span>
        Alumni Tracking System
      </div>
      <h2 class="hero-h2">
        Reconnect with<br>your <em>story.</em>
      </h2>
      <p class="hero-p">
        Access your alumni profile, track career milestones, discover upcoming events,
        and stay connected with fellow graduates.
      </p>
      <div class="stats">
        <div class="stat">
          <div class="stat-n">5K<sup>+</sup></div>
          <div class="stat-l">Graduates</div>
        </div>
        <div class="stat">
          <div class="stat-n">12<sup>+</sup></div>
          <div class="stat-l">Batch Years</div>
        </div>
        <div class="stat">
          <div class="stat-n">3<sup>+</sup></div>
          <div class="stat-l">Programs</div>
        </div>
      </div>
    </div>
  </div>

  <!-- ══ FORM ══ -->
  <div class="form-col">
    <div class="form-inner">

      <div class="mobile-brand">
        <img src="olfulogo.png" alt="OLFU" />
        <img src="ccs_logo.png" alt="CCS" />
      </div>

      <a href="al_homepage.php" class="back-btn">
        <i class="fas fa-arrow-left"></i> Back to Home
      </a>

      <div class="form-kicker">Alumni Portal</div>
      <h1 class="form-title">Welcome back</h1>
      <p class="form-sub">Sign in to continue to your account.</p>

      <?php if (isset($_GET['reset']) && $_GET['reset'] === 'success' && !$error): ?>
        <div class="alert alert-success">
          <i class="fas fa-check-circle"></i>
          Your password has been reset. Please sign in with your new credentials.
        </div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="alert alert-error">
          <i class="fas fa-exclamation-circle"></i>
          <?php echo htmlspecialchars($error); ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="al_login.php" autocomplete="on">
        <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>" />

        <div class="field">
          <label class="field-label" for="email">Email or Admin Username</label>
          <div class="field-wrap">
            <i class="fas fa-envelope field-icon"></i>
            <input type="text" id="email" name="email" class="field-input"
              autocomplete="username" required placeholder="you@example.com" />
          </div>
        </div>

        <div class="field">
          <label class="field-label" for="password">Password</label>
          <div class="field-wrap">
            <i class="fas fa-lock field-icon"></i>
            <input type="password" id="password" name="password" class="field-input"
              autocomplete="current-password" required placeholder="••••••••"
              style="padding-right:2.75rem;" />
            <button type="button" class="pw-toggle" id="pw-toggle" aria-label="Toggle password visibility">
              <i class="fas fa-eye" id="pw-icon"></i>
            </button>
          </div>
        </div>

        <div class="options-row">
          <label class="check-label">
            <input type="checkbox" class="check-box" name="rememberMe" />
            Remember me
          </label>
          <a href="al_forgot_password.php" class="forgot-link">Forgot password?</a>
        </div>

        <button type="submit" class="btn-submit">
          <span class="btn-inner">
            <i class="fas fa-sign-in-alt"></i>
            Sign In
          </span>
        </button>
      </form>

      <div class="or-row">or</div>

      <p class="signup-text">
        Don't have an account? <a href="registration_entry.php">Create one now</a>
      </p>

      <div class="form-footer">
        <a href="al_homepage.php">← Return to Homepage</a>
      </div>

    </div>
  </div>

</div>

<script>
  (function () {
    var input = document.getElementById('password');
    var btn   = document.getElementById('pw-toggle');
    var icon  = document.getElementById('pw-icon');
    if (!btn || !input || !icon) return;
    btn.addEventListener('click', function () {
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      icon.className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
      input.focus();
    });
  })();
</script>
</body>
</html>
