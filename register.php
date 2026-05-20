<?php
include("includes/config.php");

$message = "";
$msg_type = "";

if (isset($_POST['register'])) {

    // Sanitize inputs
    $full_name     = trim(mysqli_real_escape_string($conn, $_POST['full_name']));
    $matric_number = trim(mysqli_real_escape_string($conn, $_POST['matric_number']));
    $department    = trim(mysqli_real_escape_string($conn, $_POST['department']));
    $level         = trim(mysqli_real_escape_string($conn, $_POST['level']));
    $email         = trim(mysqli_real_escape_string($conn, $_POST['email']));
    $raw_password  = $_POST['password'];
    $confirm_pass  = $_POST['confirm_password'];

    // Server-side validation
    if (empty($full_name) || empty($matric_number) || empty($department) || empty($level) || empty($email) || empty($raw_password)) {
        $message  = "All fields are required.";
        $msg_type = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message  = "Please enter a valid email address.";
        $msg_type = "error";
    } elseif (strlen($raw_password) < 8) {
        $message  = "Password must be at least 8 characters long.";
        $msg_type = "error";
    } elseif ($raw_password !== $confirm_pass) {
        $message  = "Passwords do not match.";
        $msg_type = "error";
    } else {
        // Hash password securely
        $password = password_hash($raw_password, PASSWORD_DEFAULT);

        // Check for existing email
        $check = mysqli_query($conn, "SELECT id FROM students WHERE email='$email' LIMIT 1");
        if (mysqli_num_rows($check) > 0) {
            $message  = "An account with this email already exists.";
            $msg_type = "error";
        } else {
            // Check for duplicate matric number
            $check2 = mysqli_query($conn, "SELECT id FROM students WHERE matric_number='$matric_number' LIMIT 1");
            if (mysqli_num_rows($check2) > 0) {
                $message  = "This matric number is already registered.";
                $msg_type = "error";
            } else {
                $insert = mysqli_query($conn,
                    "INSERT INTO students (full_name, matric_number, department, level, email, password, created_at)
                     VALUES ('$full_name','$matric_number','$department','$level','$email','$password', NOW())"
                );
                  if ($insert) {
                    $message  = "Registration successful! You can now log in.";
                   $msg_type = "success";
                   header("Location: login.php?registered=1");
                    exit();
                } else {
                    $message  = "Something went wrong. Please try again.";
                    $msg_type = "error";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Register — SCMS</title>
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
      --bg:       #050b18;
      --surface:  #0b1628;
      --surface2: #111f3a;
      --accent:   #2f80ed;
      --accent2:  #56ccf2;
      --gold:     #f2c94c;
      --text:     #e8eef8;
      --muted:    #7a90b3;
      --border:   rgba(47,128,237,0.18);
      --glow:     rgba(47,128,237,0.35);
      --error:    #eb5757;
      --success:  #27ae60;
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
      content:'';
      position:absolute; inset:0;
      background: radial-gradient(ellipse at 30% 20%, rgba(47,128,237,0.12) 0%, transparent 60%),
                  radial-gradient(ellipse at 80% 80%, rgba(86,204,242,0.07) 0%, transparent 55%);
      pointer-events:none;
    }

    /* animated grid overlay */
    .grid-overlay {
      position:absolute; inset:0;
      background-image:
        linear-gradient(rgba(47,128,237,0.04) 1px, transparent 1px),
        linear-gradient(90deg, rgba(47,128,237,0.04) 1px, transparent 1px);
      background-size: 48px 48px;
      pointer-events:none;
    }

    .brand {
      display:flex; align-items:center; gap:14px;
      position:relative; z-index:1;
    }
    .brand-badge {
      width:44px; height:44px; border-radius:12px;
      background: linear-gradient(135deg, var(--accent), var(--accent2));
      display:flex; align-items:center; justify-content:center;
      font-family:'Syne',sans-serif; font-size:20px; font-weight:800;
      color:white;
      box-shadow: 0 0 24px var(--glow);
    }
    .brand-name {
      font-family:'Syne',sans-serif;
      font-size:20px; font-weight:800; letter-spacing:-0.5px;
    }

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
      font-size:clamp(34px,3.5vw,52px);
      font-weight:800; letter-spacing:-2px;
      line-height:1.07; margin-bottom:20px;
    }
    .panel-title span {
      background: linear-gradient(90deg, var(--accent), var(--accent2));
      -webkit-background-clip:text; -webkit-text-fill-color:transparent;
      background-clip:text;
    }
    .panel-desc {
      font-size:15px; color:var(--muted); line-height:1.8;
      max-width:340px;
    }

    .perks { display:flex; flex-direction:column; gap:14px; margin-top:40px; }
    .perk {
      display:flex; align-items:center; gap:14px;
      padding:14px 18px;
      background:var(--surface2);
      border:1px solid var(--border);
      border-radius:12px;
      font-size:13px; color:var(--muted);
      transition:all 0.3s;
    }
    .perk:hover { border-color:var(--accent); color:var(--text); transform:translateX(4px); }
    .perk-icon {
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

    /* ── RIGHT PANEL (FORM) ── */
    .right-panel {
      flex:1;
      display:flex; align-items:center; justify-content:center;
      padding: 40px 5%;
      overflow-y: auto;
    }

    .form-box {
      width:100%; max-width:520px;
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

    /* ── ALERT ── */
    .alert-box {
      display:flex; align-items:flex-start; gap:12px;
      padding:14px 18px; border-radius:12px;
      margin-bottom:28px; font-size:13px;
      animation: fadeIn 0.4s ease;
    }
    @keyframes fadeIn { from{opacity:0;transform:translateY(-8px);} to{opacity:1;transform:translateY(0);} }
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

    /* ── FORM GRID ── */
    .form-row {
      display:grid; grid-template-columns:1fr 1fr;
      gap:16px;
    }
    .form-group { margin-bottom:18px; }
    .form-group.full { grid-column:1/-1; }

    .form-label {
      display:block; font-size:12px; font-weight:600;
      letter-spacing:0.8px; text-transform:uppercase;
      color:var(--muted); margin-bottom:8px;
    }

    .input-wrap { position:relative; }
    .input-icon {
      position:absolute; left:14px; top:50%; transform:translateY(-50%);
      font-size:15px; pointer-events:none;
      transition:opacity 0.3s;
    }
    .form-input {
      width:100%; padding:13px 14px 13px 42px;
      background:var(--surface2);
      border:1px solid var(--border);
      border-radius:10px;
      color:var(--text); font-family:'DM Sans',sans-serif;
      font-size:14px;
      transition:all 0.3s;
      outline:none;
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

    /* select styling */
    select.form-input { cursor:pointer; }
    select.form-input option { background:var(--surface2); color:var(--text); }

    /* password toggle */
    .pwd-toggle {
      position:absolute; right:14px; top:50%; transform:translateY(-50%);
      background:none; border:none;
      color:var(--muted); font-size:17px;
      cursor:pointer; padding:0;
      transition:color 0.3s;
    }
    .pwd-toggle:hover { color:var(--text); }

    /* ── PASSWORD STRENGTH ── */
    .pwd-strength { margin-top:8px; }
    .strength-bar {
      height:3px; border-radius:2px;
      background:var(--surface2);
      overflow:hidden; margin-bottom:5px;
    }
    .strength-fill {
      height:100%; border-radius:2px;
      width:0; transition:width 0.4s ease, background 0.4s ease;
    }
    .strength-label {
      font-size:11px; color:var(--muted); font-weight:500;
    }

    /* ── FIELD HINT ── */
    .field-hint {
      font-size:11px; color:var(--muted);
      margin-top:5px; display:none;
    }
    .form-input:focus + .field-hint,
    .input-wrap:focus-within ~ .field-hint { display:block; }

    /* ── DIVIDER ── */
    .form-divider {
      height:1px;
      background: linear-gradient(90deg, transparent, var(--border), transparent);
      margin:24px 0;
    }

    /* ── SUBMIT BUTTON ── */
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

    .login-link {
      text-align:center; font-size:13px; color:var(--muted);
    }
    .login-link a {
      color:var(--accent2); font-weight:600;
      text-decoration:none;
      transition:color 0.2s;
    }
    .login-link a:hover { color:var(--text); }

    /* ── RESPONSIVE ── */
    @media (max-width:900px) {
      .left-panel { display:none; }
      .right-panel { justify-content:center; padding:40px 24px; }
    }
    @media (max-width:480px) {
      .form-row { grid-template-columns:1fr; }
    }
  </style>
</head>
<body>

<!-- ── LEFT PANEL ── -->
<div class="left-panel">
  <div class="grid-overlay"></div>

  <div class="brand">
    <div class="brand-badge">S</div>
    <div class="brand-name">SCMS</div>
  </div>

  <div class="panel-content">
    <div class="panel-eyebrow">
      <span class="eyebrow-dot"></span>
      Student Portal
    </div>
    <h1 class="panel-title">
      Join the<br><span>Platform.</span><br>Be Heard.
    </h1>
    <p class="panel-desc">
      Create your account to submit, track, and manage complaints — all in one transparent, student-first system.
    </p>
    <div class="perks">
      <div class="perk">
        <div class="perk-icon">📡</div>
        Real-time complaint tracking at every stage
      </div>
      <div class="perk">
        <div class="perk-icon">🔒</div>
        Secure, encrypted account with role-based access
      </div>
      <div class="perk">
        <div class="perk-icon">⚡</div>
        Average resolution time under 24 hours
      </div>
      <div class="perk">
        <div class="perk-icon">🛡️</div>
        Anonymous submission mode available
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
      <h2>Create Your Account</h2>
      <p>Fill in your details below to get started</p>
    </div>

    <!-- ALERT MESSAGE -->
    <?php if (!empty($message)): ?>
      <div class="alert-box <?= $msg_type === 'success' ? 'alert-success' : 'alert-error' ?>">
        <div class="alert-icon"><?= $msg_type === 'success' ? '✅' : '⚠️' ?></div>
        <div><?= htmlspecialchars($message) ?></div>
      </div>
    <?php endif; ?>

    <form method="POST" id="registerForm" novalidate>

      <!-- Row 1: Full Name -->
      <div class="form-group">
        <label class="form-label">Full Name</label>
        <div class="input-wrap">
          <span class="input-icon">👤</span>
          <input
            type="text" name="full_name" class="form-input" id="fullName"
            placeholder="e.g. Chukwuemeka Obi"
            value="<?= isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : '' ?>"
            required autocomplete="name"
          >
        </div>
      </div>

      <!-- Row 2: Matric + Department -->
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Matric Number</label>
          <div class="input-wrap">
            <span class="input-icon">🎫</span>
            <input
              type="text" name="matric_number" class="form-input" id="matricNo"
              placeholder="e.g. CSC/2021/001"
              value="<?= isset($_POST['matric_number']) ? htmlspecialchars($_POST['matric_number']) : '' ?>"
              required
            >
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Level</label>
          <div class="input-wrap">
            <span class="input-icon">📶</span>
            <select name="level" class="form-input" id="level" required>
              <option value="" disabled <?= !isset($_POST['level']) ? 'selected' : '' ?>>Select Level</option>
              <?php
                $levels = ['ND1','ND2','HND1','HND2'];
                foreach ($levels as $lvl) {
                  $sel = (isset($_POST['level']) && $_POST['level'] === $lvl) ? 'selected' : '';
                  echo "<option value='$lvl' $sel>$lvl Level</option>";
                }
              ?>
            </select>
          </div>
        </div>
      </div>

      <!-- Department -->
      <div class="form-group">
        <label class="form-label">Department</label>
        <div class="input-wrap">
          <span class="input-icon">🏛️</span>
          <select name="department" class="form-input" id="dept" required>
            <option value="" disabled <?= !isset($_POST['department']) ? 'selected' : '' ?>>Select Department</option>
            <?php
              $depts = [
                'Computer Science','Information Technology','Electrical Engineering',
                'Civil Engineering','Mechanical Engineering','Business Administration',
                'Accounting','Mass Communication','Law','Medicine & Surgery',
                'Nursing Science','Architecture','Mathematics','Physics','Chemistry'
              ];
              foreach ($depts as $d) {
                $sel = (isset($_POST['department']) && $_POST['department'] === $d) ? 'selected' : '';
                echo "<option value='".htmlspecialchars($d)."' $sel>".htmlspecialchars($d)."</option>";
              }
            ?>
          </select>
        </div>
      </div>

      <!-- Email -->
      <div class="form-group">
        <label class="form-label">Email Address</label>
        <div class="input-wrap">
          <span class="input-icon">✉️</span>
          <input
            type="email" name="email" class="form-input" id="email"
            placeholder="your@university.edu"
            value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
            required autocomplete="email"
          >
        </div>
      </div>

      <div class="form-divider"></div>

      <!-- Password -->
      <div class="form-group">
        <label class="form-label">Password</label>
        <div class="input-wrap">
          <span class="input-icon">🔑</span>
          <input
            type="password" name="password" class="form-input" id="password"
            placeholder="Min. 8 characters"
            required autocomplete="new-password"
          >
          <button type="button" class="pwd-toggle" onclick="togglePwd('password','eye1')" id="eye1">👁️</button>
        </div>
        <div class="pwd-strength">
          <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
          <span class="strength-label" id="strengthLabel">Enter a password</span>
        </div>
      </div>

      <!-- Confirm Password -->
      <div class="form-group">
        <label class="form-label">Confirm Password</label>
        <div class="input-wrap">
          <span class="input-icon">🔐</span>
          <input
            type="password" name="confirm_password" class="form-input" id="confirmPwd"
            placeholder="Repeat your password"
            required autocomplete="new-password"
          >
          <button type="button" class="pwd-toggle" onclick="togglePwd('confirmPwd','eye2')" id="eye2">👁️</button>
        </div>
        <div class="field-hint" id="matchHint" style="display:none;"></div>
      </div>

      <!-- Submit -->
      <button type="submit" name="register" class="btn-submit" id="submitBtn">
        <span>🚀</span> Create My Account
      </button>

      <div class="login-link">
        Already have an account? <a href="login.php">Sign in here →</a>
      </div>

    </form>
  </div>
</div>

<script>
// ── PASSWORD TOGGLE ──
function togglePwd(inputId, btnId) {
  const inp = document.getElementById(inputId);
  const btn = document.getElementById(btnId);
  if (inp.type === 'password') {
    inp.type = 'text'; btn.textContent = '🙈';
  } else {
    inp.type = 'password'; btn.textContent = '👁️';
  }
}

// ── PASSWORD STRENGTH ──
const pwdInput = document.getElementById('password');
const fill     = document.getElementById('strengthFill');
const lbl      = document.getElementById('strengthLabel');

pwdInput.addEventListener('input', function () {
  const v = this.value;
  let score = 0;
  if (v.length >= 8) score++;
  if (/[A-Z]/.test(v)) score++;
  if (/[0-9]/.test(v)) score++;
  if (/[^A-Za-z0-9]/.test(v)) score++;

  const configs = [
    { pct:'0%',   color:'transparent', text:'Enter a password' },
    { pct:'25%',  color:'#eb5757',     text:'Weak' },
    { pct:'50%',  color:'#f2994a',     text:'Fair' },
    { pct:'75%',  color:'#f2c94c',     text:'Good' },
    { pct:'100%', color:'#27ae60',     text:'Strong ✓' },
  ];
  const c = configs[score];
  fill.style.width      = c.pct;
  fill.style.background = c.color;
  lbl.textContent       = c.text;
  lbl.style.color       = c.color === 'transparent' ? 'var(--muted)' : c.color;
});

// ── CONFIRM PASSWORD MATCH ──
const confirmPwd = document.getElementById('confirmPwd');
const matchHint  = document.getElementById('matchHint');

confirmPwd.addEventListener('input', function () {
  if (!this.value) { matchHint.style.display = 'none'; return; }
  matchHint.style.display = 'block';
  if (this.value === pwdInput.value) {
    matchHint.textContent = '✓ Passwords match';
    matchHint.style.color = '#6fcf97';
    this.classList.remove('invalid'); this.classList.add('valid');
  } else {
    matchHint.textContent = '✗ Passwords do not match';
    matchHint.style.color = '#eb5757';
    this.classList.remove('valid'); this.classList.add('invalid');
  }
});

// ── LIVE FIELD VALIDATION ──
function validateOnBlur(id, fn) {
  const el = document.getElementById(id);
  if (!el) return;
  el.addEventListener('blur', function () {
    if (fn(this.value)) {
      this.classList.remove('invalid'); this.classList.add('valid');
    } else if (this.value.length > 0) {
      this.classList.remove('valid'); this.classList.add('invalid');
    }
  });
  el.addEventListener('input', function () {
    this.classList.remove('valid','invalid');
  });
}

validateOnBlur('fullName', v => v.trim().length >= 2);
validateOnBlur('matricNo', v => v.trim().length >= 4);
validateOnBlur('email',    v => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v));
validateOnBlur('dept',     v => v !== '');
validateOnBlur('level',    v => v !== '');

// ── FORM SUBMIT ──
document.getElementById('registerForm').addEventListener('submit', function (e) {
  if (pwdInput.value !== confirmPwd.value) {
    e.preventDefault();
    matchHint.textContent   = '✗ Passwords do not match';
    matchHint.style.display = 'block';
    matchHint.style.color   = '#eb5757';
    confirmPwd.classList.add('invalid');
    return;
  }
  document.getElementById('submitBtn').innerHTML = '<span>⏳</span> Creating Account...';
});
</script>
</body>
</html>