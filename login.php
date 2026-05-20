<?php
session_start();

// If already logged in, go straight to dashboard
if (isset($_SESSION['student_id'])) {
    header("Location: student/dashboard.php");
    exit();
}

include("includes/config.php");

$message  = "";
$msg_type = "";

if (isset($_POST['login'])) {
    $email    = trim(mysqli_real_escape_string($conn, $_POST['email']));
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $message  = "Please enter both email and password.";
        $msg_type = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message  = "Please enter a valid email address.";
        $msg_type = "error";
    } else {
        $query  = mysqli_query($conn, "SELECT * FROM students WHERE email='$email' LIMIT 1");
        $student = mysqli_fetch_assoc($query);

        if ($student && password_verify($password, $student['password'])) {
            // Set session variables
            $_SESSION['student_id']     = $student['id'];
            $_SESSION['student_name']   = $student['full_name'];
            $_SESSION['student_email']  = $student['email'];
            $_SESSION['student_matric'] = $student['matric_number'];
            $_SESSION['student_dept']   = $student['department'];
            $_SESSION['student_level']  = $student['level'];

            // Redirect to dashboard
            header("Location: student/dashboard.php");
            exit();
        } else {
            $message  = "Invalid email or password. Please try again.";
            $msg_type = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Login — SCMS</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
  <style>

      *{
    max-width:100%;
    box-sizing:border-box;
}

html,
body{
    overflow-x:hidden;
    width:100%;
} 
    :root {
      --bg:      #050b18;
      --surface: #0b1628;
      --surface2:#111f3a;
      --accent:  #2f80ed;
      --accent2: #56ccf2;
      --gold:    #f2c94c;
      --text:    #e8eef8;
      --muted:   #7a90b3;
      --border:  rgba(47,128,237,0.18);
      --glow:    rgba(47,128,237,0.35);
      --error:   #eb5757;
      --success: #27ae60;
    }

    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
    html { height:100%; }

    body {
      min-height: 100vh;
      background: var(--bg);
      color: var(--text);
      font-family: 'DM Sans', sans-serif;
      display: flex;
      overflow-x: hidden;
    }

    /* ── LEFT PANEL ── */
    .left-panel {
      width: 44%;
      background: var(--surface);
      border-right: 1px solid var(--border);
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      padding: 48px 52px;
      position: relative;
      overflow: hidden;
    }
    .left-panel::before {
      content:''; position:absolute; inset:0;
      background:
        radial-gradient(ellipse at 20% 30%, rgba(47,128,237,0.13) 0%, transparent 60%),
        radial-gradient(ellipse at 85% 75%, rgba(86,204,242,0.07) 0%, transparent 55%);
      pointer-events:none;
    }
    .grid-overlay {
      position:absolute; inset:0;
      background-image:
        linear-gradient(rgba(47,128,237,0.04) 1px, transparent 1px),
        linear-gradient(90deg, rgba(47,128,237,0.04) 1px, transparent 1px);
      background-size: 48px 48px;
      pointer-events:none;
    }

    /* brand */
    .brand {
      display:flex; align-items:center; gap:14px;
      position:relative; z-index:1;
      text-decoration:none;
    }
    .brand-badge {
      width:44px; height:44px; border-radius:12px;
      background: linear-gradient(135deg, var(--accent), var(--accent2));
      display:flex; align-items:center; justify-content:center;
      font-family:'Syne',sans-serif; font-size:20px; font-weight:800;
      color:white; box-shadow:0 0 24px var(--glow);
    }
    .brand-name {
      font-family:'Syne',sans-serif;
      font-size:20px; font-weight:800; letter-spacing:-0.5px;
      color: var(--text);
    }

    /* panel content */
    .panel-content { position:relative; z-index:1; }
    .panel-eyebrow {
      display:inline-flex; align-items:center; gap:8px;
      background: rgba(47,128,237,0.1);
      border: 1px solid rgba(47,128,237,0.25);
      padding: 5px 14px; border-radius:100px;
      font-size:11px; font-weight:600; letter-spacing:1.5px;
      color:var(--accent2); text-transform:uppercase;
      margin-bottom:24px;
    }
    .eyebrow-dot {
      width:6px; height:6px; border-radius:50%;
      background:var(--accent2);
      animation: pulse 2s ease infinite;
    }
    @keyframes pulse {
      0%,100%{ opacity:1; transform:scale(1); }
      50%    { opacity:0.4; transform:scale(1.5); }
    }
    .panel-title {
      font-family:'Syne',sans-serif;
      font-size: clamp(34px,3.5vw,52px);
      font-weight:800; letter-spacing:-2px;
      line-height:1.07; margin-bottom:20px;
    }
    .panel-title span {
      background: linear-gradient(90deg, var(--accent), var(--accent2));
      -webkit-background-clip:text; -webkit-text-fill-color:transparent;
      background-clip:text;
    }
    .panel-desc {
      font-size:15px; color:var(--muted);
      line-height:1.8; max-width:340px;
    }

    /* info cards */
    .info-cards { display:flex; flex-direction:column; gap:12px; margin-top:36px; }
    .info-card {
      display:flex; align-items:center; gap:14px;
      padding:14px 18px;
      background:var(--surface2);
      border:1px solid var(--border);
      border-radius:12px;
      font-size:13px; color:var(--muted);
      transition:all 0.3s;
    }
    .info-card:hover {
      border-color:var(--accent);
      color:var(--text);
      transform:translateX(4px);
    }
    .info-icon {
      width:36px; height:36px; border-radius:9px;
      display:flex; align-items:center; justify-content:center;
      font-size:17px; flex-shrink:0;
      background:rgba(47,128,237,0.12);
      border:1px solid rgba(47,128,237,0.2);
    }

    .panel-footer {
      position:relative; z-index:1;
      font-size:12px; color:var(--muted);
    }

    /* ── RIGHT PANEL ── */
    .right-panel {
      flex:1;
      display:flex; align-items:center; justify-content:center;
      padding:40px 5%;
      overflow-y:auto;
    }

    .form-box {
      width:100%; max-width:460px;
      animation: slideIn 0.6s ease both;
    }
    @keyframes slideIn {
      from { opacity:0; transform:translateY(24px); }
      to   { opacity:1; transform:translateY(0); }
    }

    .form-header { margin-bottom:36px; }
    .form-header h2 {
      font-family:'Syne',sans-serif;
      font-size:28px; font-weight:800;
      letter-spacing:-0.8px; margin-bottom:6px;
    }
    .form-header p { font-size:14px; color:var(--muted); }

    /* alert */
    .alert-box {
      display:flex; align-items:flex-start; gap:12px;
      padding:14px 18px; border-radius:12px;
      margin-bottom:28px; font-size:13px;
      animation: fadeIn 0.4s ease;
    }
    @keyframes fadeIn {
      from{opacity:0;transform:translateY(-8px);}
      to  {opacity:1;transform:translateY(0);}
    }
    .alert-error {
      background:rgba(235,87,87,0.08);
      border:1px solid rgba(235,87,87,0.3);
      color:#ff8080;
    }
    .alert-success {
      background:rgba(39,174,96,0.08);
      border:1px solid rgba(39,174,96,0.3);
      color:#6fcf97;
    }
    .alert-icon { font-size:17px; flex-shrink:0; margin-top:1px; }

    /* form elements */
    .form-group { margin-bottom:20px; }
    .form-label {
      display:block; font-size:12px; font-weight:600;
      letter-spacing:0.8px; text-transform:uppercase;
      color:var(--muted); margin-bottom:8px;
    }
    .input-wrap { position:relative; }
    .input-icon {
      position:absolute; left:14px; top:50%;
      transform:translateY(-50%);
      font-size:15px; pointer-events:none;
    }
    .form-input {
      width:100%; padding:13px 14px 13px 42px;
      background:var(--surface2);
      border:1px solid var(--border);
      border-radius:10px;
      color:var(--text); font-family:'DM Sans',sans-serif;
      font-size:14px; outline:none;
      transition:all 0.3s;
      -webkit-appearance:none;
    }
    .form-input::placeholder { color:var(--muted); }
    .form-input:focus {
      border-color:var(--accent);
      background:rgba(47,128,237,0.06);
      box-shadow:0 0 0 3px rgba(47,128,237,0.12);
    }
    .form-input.valid {
      border-color:var(--success);
      box-shadow:0 0 0 3px rgba(39,174,96,0.1);
    }
    .form-input.invalid {
      border-color:var(--error);
      box-shadow:0 0 0 3px rgba(235,87,87,0.1);
    }

    /* password toggle */
    .pwd-toggle {
      position:absolute; right:14px; top:50%;
      transform:translateY(-50%);
      background:none; border:none;
      color:var(--muted); font-size:17px;
      cursor:pointer; padding:0;
      transition:color 0.3s;
    }
    .pwd-toggle:hover { color:var(--text); }

    /* forgot password */
    .forgot-row {
      display:flex; justify-content:flex-end;
      margin-top:-12px; margin-bottom:24px;
    }
    .forgot-link {
      font-size:12px; color:var(--accent2);
      text-decoration:none; font-weight:500;
      transition:color 0.2s;
    }
    .forgot-link:hover { color:var(--text); }

    /* divider */
    .form-divider {
      display:flex; align-items:center; gap:12px;
      margin:24px 0; color:var(--muted); font-size:12px;
    }
    .form-divider::before,
    .form-divider::after {
      content:''; flex:1; height:1px;
      background:var(--border);
    }

    /* submit button */
    .btn-submit {
      width:100%; padding:15px;
      border-radius:12px;
      background: linear-gradient(135deg, var(--accent), #1e5fc7);
      color:white; font-family:'Syne',sans-serif;
      font-size:15px; font-weight:700;
      border:none; cursor:pointer;
      display:flex; align-items:center; justify-content:center; gap:10px;
      position:relative; overflow:hidden;
      box-shadow:0 8px 32px var(--glow);
      transition:all 0.3s;
      margin-bottom:20px;
    }
    .btn-submit::before {
      content:''; position:absolute; inset:0;
      background:linear-gradient(90deg,transparent,rgba(255,255,255,0.15),transparent);
      transform:translateX(-100%);
      transition:transform 0.6s ease;
    }
    .btn-submit:hover::before { transform:translateX(100%); }
    .btn-submit:hover {
      transform:translateY(-2px);
      box-shadow:0 14px 42px var(--glow);
    }
    .btn-submit:active { transform:translateY(0); }

    /* register link */
    .register-link {
      text-align:center; font-size:13px; color:var(--muted);
      margin-bottom:16px;
    }
    .register-link a {
      color:var(--accent2); font-weight:600;
      text-decoration:none; transition:color 0.2s;
    }
    .register-link a:hover { color:var(--text); }

    /* admin link */
    .admin-link-box {
      display:flex; align-items:center; justify-content:center; gap:8px;
      padding:12px; border-radius:10px;
      border:1px solid var(--border);
      background:rgba(255,255,255,0.02);
      font-size:12px; color:var(--muted);
      text-decoration:none;
      transition:all 0.3s;
    }
    .admin-link-box:hover {
      border-color:var(--gold);
      color:var(--gold);
      background:rgba(242,201,76,0.05);
    }

    /* ── RESPONSIVE ── */
    @media (max-width:900px) {
      .left-panel { display:none; }
      .right-panel { padding:40px 24px; }
    }
  </style>
</head>
<body>

<!-- ── LEFT PANEL ── -->
<div class="left-panel">
  <div class="grid-overlay"></div>

  <a class="brand" href="index.html">
    <div class="brand-badge">S</div>
    <div class="brand-name">SCMS</div>
  </a>

  <div class="panel-content">
    <div class="panel-eyebrow">
      <span class="eyebrow-dot"></span>
      Student Portal
    </div>
    <h1 class="panel-title">
      Welcome<br>Back to<br><span>SCMS.</span>
    </h1>
    <p class="panel-desc">
      Sign in to your student account to submit complaints, track their status, and stay updated on resolutions — all in one place.
    </p>

    <div class="info-cards">
      <div class="info-card">
        <div class="info-icon">📋</div>
        Submit complaints in under 60 seconds
      </div>
      <div class="info-card">
        <div class="info-icon">📡</div>
        Track your complaint status in real time
      </div>
      <div class="info-card">
        <div class="info-icon">🔔</div>
        Get notified the moment things change
      </div>
      <div class="info-card">
        <div class="info-icon">🔒</div>
        Your data is secure and private
      </div>
    </div>
  </div>

  <div class="panel-footer">
    © 2026 SCMS · Final Year Project
  </div>
</div>

<!-- ── RIGHT PANEL ── -->
<div class="right-panel">
  <div class="form-box">

    <div class="form-header">
      <h2>Sign In</h2>
      <p>Enter your registered email and password</p>
    </div>

    <!-- ALERT -->
    <?php if (!empty($message)): ?>
      <div class="alert-box <?= $msg_type === 'success' ? 'alert-success' : 'alert-error' ?>">
        <div class="alert-icon"><?= $msg_type === 'success' ? '✅' : '⚠️' ?></div>
        <div><?= htmlspecialchars($message) ?></div>
      </div>
    <?php endif; ?>

    <form method="POST" action="login.php" id="loginForm">

      <!-- Email -->
      <div class="form-group">
        <label class="form-label">Email Address</label>
        <div class="input-wrap">
          <span class="input-icon">✉️</span>
          <input
            type="email" name="email" class="form-input" id="emailInput"
            placeholder="your@university.edu"
            value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
            required autocomplete="email"
          >
        </div>
      </div>

      <!-- Password -->
      <div class="form-group">
        <label class="form-label">Password</label>
        <div class="input-wrap">
          <span class="input-icon">🔑</span>
          <input
            type="password" name="password" class="form-input" id="passwordInput"
            placeholder="Enter your password"
            required autocomplete="current-password"
          >
          <button type="button" class="pwd-toggle" onclick="togglePwd()" id="eyeBtn">👁️</button>
        </div>
      </div>

      <!-- Forgot Password -->
      <div class="forgot-row">
        <a href="#" class="forgot-link">Forgot password?</a>
      </div>

      <!-- Submit -->
      <button type="submit" name="login" class="btn-submit" id="submitBtn">
        <span>🚀</span> Sign In to My Account
      </button>

      <div class="register-link">
        Don't have an account? <a href="register.php">Register here →</a>
      </div>

      <div class="form-divider">or</div>

      <a href="admin/login.php" class="admin-link-box">
        🛡️ Admin? Sign in to the Admin Portal instead
      </a>

    </form>
  </div>
</div>

<script>
// ── PASSWORD TOGGLE ──
function togglePwd() {
  const inp = document.getElementById('passwordInput');
  const btn = document.getElementById('eyeBtn');
  if (inp.type === 'password') {
    inp.type = 'text'; btn.textContent = '🙈';
  } else {
    inp.type = 'password'; btn.textContent = '👁️';
  }
}

// ── LIVE EMAIL VALIDATION ──
const emailInput = document.getElementById('emailInput');
emailInput.addEventListener('blur', function () {
  const valid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.value);
  if (this.value.length > 0) {
    this.classList.toggle('valid',   valid);
    this.classList.toggle('invalid', !valid);
  }
});
emailInput.addEventListener('input', function () {
  this.classList.remove('valid','invalid');
});

// ── FORM SUBMIT ──
document.getElementById('loginForm').addEventListener('submit', function () {
  document.getElementById('submitBtn').innerHTML = '<span>⏳</span> Signing In...';
});
</script>
</body>
</html>