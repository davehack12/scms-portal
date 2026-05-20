<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit();
}

// FIXED PATH — works on LAMPP
include('../includes/config.php');

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "Enter both username and password.";
    } elseif (!isset($conn)) {
        $error = "Database connection failed. Check config.php.";
    } else {
        $safe = mysqli_real_escape_string($conn, $username);
        $res  = mysqli_query($conn, "SELECT * FROM admins WHERE username='$safe' LIMIT 1");
        $row  = $res ? mysqli_fetch_assoc($res) : null;

        if ($row && password_verify($password, $row['password'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id']   = $row['id'];
            $_SESSION['admin_name'] = $row['full_name'];
            $_SESSION['admin_user'] = $row['username'];
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Invalid username or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Login — SCMS</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
  <style>
    [data-theme="dark"]{--bg:#050b18;--card:#0d1a2e;--accent:#2f80ed;--accent2:#56ccf2;--text:#e8eef8;--muted:#7a90b3;--border:rgba(47,128,237,0.2);--glow:rgba(47,128,237,0.3);--input:#111f3a;}
    [data-theme="light"]{--bg:#f0f4fb;--card:#fff;--accent:#2f80ed;--accent2:#1a6fd4;--text:#0d1a2e;--muted:#8a9bb5;--border:rgba(47,128,237,0.15);--glow:rgba(47,128,237,0.2);--input:#f4f7fd;}
    *{margin:0;padding:0;box-sizing:border-box;}
    body{background:var(--bg);color:var(--text);font-family:'DM Sans',sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;transition:background .3s;}
    body::before{content:'';position:fixed;inset:0;background-image:linear-gradient(rgba(47,128,237,.04) 1px,transparent 1px),linear-gradient(90deg,rgba(47,128,237,.04) 1px,transparent 1px);background-size:40px 40px;pointer-events:none;}
    .wrap{position:relative;z-index:1;width:100%;max-width:420px;padding:20px;}
    .brand{text-align:center;margin-bottom:28px;}
    .badge{width:56px;height:56px;border-radius:16px;background:linear-gradient(135deg,var(--accent),var(--accent2));display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-size:24px;font-weight:800;color:#fff;margin:0 auto 12px;box-shadow:0 8px 28px var(--glow);}
    .brand h1{font-family:'Syne',sans-serif;font-size:22px;font-weight:800;}
    .brand p{font-size:13px;color:var(--muted);margin-top:3px;}
    .card{background:var(--card);border:1px solid var(--border);border-radius:20px;padding:36px 32px;box-shadow:0 20px 60px rgba(0,0,0,.3);position:relative;overflow:hidden;}
    .card::before{content:'';position:absolute;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,var(--accent),var(--accent2),transparent);}
    .admin-tag{display:inline-flex;align-items:center;gap:6px;background:rgba(235,87,87,.12);border:1px solid rgba(235,87,87,.25);color:#eb5757;font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;padding:4px 12px;border-radius:100px;margin-bottom:22px;}
    .err{background:rgba(235,87,87,.1);border:1px solid rgba(235,87,87,.3);color:#eb5757;border-radius:10px;padding:12px 14px;font-size:13px;margin-bottom:18px;display:flex;gap:8px;align-items:flex-start;}
    label{display:block;font-size:11px;font-weight:600;letter-spacing:.8px;text-transform:uppercase;color:var(--muted);margin-bottom:7px;}
    .fg{margin-bottom:18px;position:relative;}
    input[type=text],input[type=password]{width:100%;padding:12px 44px 12px 14px;background:var(--input);border:1px solid var(--border);border-radius:10px;color:var(--text);font-family:'DM Sans',sans-serif;font-size:14px;outline:none;transition:all .3s;}
    input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(47,128,237,.1);}
    input::placeholder{color:var(--muted);}
    .eye{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--muted);cursor:pointer;font-size:16px;padding:0;line-height:1;}
    .btn{width:100%;padding:14px;border-radius:11px;background:linear-gradient(135deg,var(--accent),#1e5fc7);color:#fff;font-family:'Syne',sans-serif;font-size:15px;font-weight:700;border:none;cursor:pointer;box-shadow:0 6px 24px var(--glow);transition:all .3s;margin-top:6px;position:relative;overflow:hidden;}
    .btn::before{content:'';position:absolute;inset:0;background:linear-gradient(90deg,transparent,rgba(255,255,255,0.15),transparent);transform:translateX(-100%);transition:transform 0.5s;}
    .btn:hover::before{transform:translateX(100%);}
    .btn:hover{transform:translateY(-2px);box-shadow:0 10px 32px var(--glow);}
    .theme-row{display:flex;justify-content:center;align-items:center;gap:8px;margin-top:20px;}
    .toggle{width:44px;height:24px;border-radius:100px;background:rgba(47,128,237,.15);border:1px solid var(--border);position:relative;cursor:pointer;flex-shrink:0;}
    .knob{width:18px;height:18px;border-radius:50%;background:var(--accent);position:absolute;top:3px;left:3px;transition:transform .3s;font-size:10px;display:flex;align-items:center;justify-content:center;line-height:1;}
    [data-theme="light"] .knob{transform:translateX(20px);}
    .theme-lbl{font-size:12px;color:var(--muted);}
    .back-link{text-align:center;margin-top:16px;font-size:12px;color:var(--muted);}
    .back-link a{color:var(--accent);text-decoration:none;}
    .back-link a:hover{color:var(--text);}
  </style>
</head>
<body>
<div class="wrap">
  <div class="brand">
    <div class="badge">S</div>
    <h1>SCMS Admin</h1>
    <p>Student Complaint Management System</p>
  </div>

  <div class="card">
    <div class="admin-tag">🛡️ Admin Access Only</div>

    <?php if ($error): ?>
    <div class="err">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
      <div class="fg">
        <label for="u">Username</label>
        <input type="text" id="u" name="username" placeholder="Enter admin username" required
               value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">
      </div>
      <div class="fg">
        <label for="p">Password</label>
        <input type="password" id="p" name="password" placeholder="Enter password" required>
        <button type="button" class="eye" onclick="togglePwd()">👁️</button>
      </div>
      <button class="btn" type="submit" id="loginBtn">🔐 Sign In to Admin Panel</button>
    </form>
  </div>

  <div class="back-link">← <a href="../login.php">Back to Student Login</a></div>

  <div class="theme-row">
    <span class="theme-lbl" id="tl">🌙</span>
    <div class="toggle" onclick="toggleTheme()"><div class="knob" id="tk">🌙</div></div>
    <span class="theme-lbl">Theme</span>
  </div>
</div>

<script>
  const h = document.documentElement;
  const t = localStorage.getItem('scms_theme') || 'dark';
  h.setAttribute('data-theme', t);
  setIcon(t);

  function toggleTheme() {
    const n = h.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    h.setAttribute('data-theme', n);
    localStorage.setItem('scms_theme', n);
    setIcon(n);
  }
  function setIcon(t) {
    const i = t === 'light' ? '☀️' : '🌙';
    document.getElementById('tk').textContent = i;
    document.getElementById('tl').textContent = i;
  }
  function togglePwd() {
    const f = document.getElementById('p');
    const b = document.querySelector('.eye');
    f.type = f.type === 'password' ? 'text' : 'password';
    b.textContent = f.type === 'password' ? '👁️' : '🙈';
  }
  document.querySelector('form').addEventListener('submit', function() {
    const btn = document.getElementById('loginBtn');
    btn.textContent = '⏳ Signing in...';
    btn.disabled = true;
  });
</script>
</body>
</html>