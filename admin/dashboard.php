<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

include('../includes/config.php');

$admin_name  = $_SESSION['admin_name'];
$admin_user  = $_SESSION['admin_user'];
$admin_id    = $_SESSION['admin_id'];

$message     = "";
$msg_type    = "";
$active_page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

// ─────────────────────────────────────────
//  HANDLE ADMIN REPLY + STATUS UPDATE
// ─────────────────────────────────────────
if (isset($_POST['submit_reply'])) {
    $complaint_id   = (int)$_POST['complaint_id'];
    $admin_response = trim($_POST['admin_response']);
    $new_status     = trim($_POST['new_status']);

    $allowed_statuses = ['Pending', 'In Review', 'Resolved', 'Rejected'];
    if (!in_array($new_status, $allowed_statuses)) {
        $new_status = 'Pending';
    }

    if (empty($admin_response)) {
        $message  = "Reply cannot be empty.";
        $msg_type = "error";
    } elseif ($complaint_id <= 0) {
        $message  = "Invalid complaint ID.";
        $msg_type = "error";
    } else {
        $safe_response = mysqli_real_escape_string($conn, $admin_response);
        $safe_status   = mysqli_real_escape_string($conn, $new_status);

        $ok = mysqli_query($conn,
            "UPDATE complaints
             SET admin_response='$safe_response',
                 status='$safe_status',
                 updated_at=NOW()
             WHERE id=$complaint_id"
        );
             
        if ($ok && mysqli_affected_rows($conn) >= 0) {
            $message  = "Reply saved and status updated successfully.";
            $msg_type = "success";
        } else {
            $message  = "Database error: " . mysqli_error($conn);
            $msg_type = "error";
        }
    }
    $active_page = 'reports';
}

// ─────────────────────────────────────────
//  HANDLE ADMIN PASSWORD CHANGE
// ─────────────────────────────────────────
if (isset($_POST['change_admin_password'])) {
    $current = $_POST['current_password'] ?? '';
    $new_pwd = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $result = mysqli_query($conn, "SELECT password FROM admins WHERE id=" . (int)$admin_id);
    $row    = $result ? mysqli_fetch_assoc($result) : null;

    if (!$row) {
        $message  = "Session error. Please log in again.";
        $msg_type = "error";
    } elseif (!password_verify($current, $row['password'])) {
        $message  = "Current password is incorrect.";
        $msg_type = "error";
    } elseif (strlen($new_pwd) < 8) {
        $message  = "New password must be at least 8 characters.";
        $msg_type = "error";
    } elseif ($new_pwd !== $confirm) {
        $message  = "New passwords do not match.";
        $msg_type = "error";
    } else {
        $hashed = password_hash($new_pwd, PASSWORD_DEFAULT);
        $safe_h = mysqli_real_escape_string($conn, $hashed);
        $ok     = mysqli_query($conn, "UPDATE admins SET password='$safe_h' WHERE id=" . (int)$admin_id);
        $message  = $ok ? "Password changed successfully." : "Failed to update password: " . mysqli_error($conn);
        $msg_type = $ok ? "success" : "error";
    }
    $active_page = 'settings';
}

// ─────────────────────────────────────────
//  FETCH STATS
// ─────────────────────────────────────────
function countWhere($conn, $table, $where = '') {
    $q = "SELECT COUNT(*) AS c FROM $table" . ($where ? " WHERE $where" : '');
    $r = mysqli_query($conn, $q);
    return $r ? (int)(mysqli_fetch_assoc($r)['c'] ?? 0) : 0;
}

$total    = countWhere($conn, 'complaints');
$pending  = countWhere($conn, 'complaints', "status='Pending'");
$inreview = countWhere($conn, 'complaints', "status='In Review'");
$resolved = countWhere($conn, 'complaints', "status='Resolved'");
$rejected = countWhere($conn, 'complaints', "status='Rejected'");
$students = countWhere($conn, 'students');

// ─────────────────────────────────────────
//  FETCH ALL COMPLAINTS (with filters)
// ─────────────────────────────────────────
$filter_status = $_GET['status'] ?? 'all';
$search_q      = trim($_GET['q'] ?? '');

$where_parts = [];
if ($filter_status !== 'all' && in_array($filter_status, ['Pending','In Review','Resolved','Rejected'])) {
    $fs = mysqli_real_escape_string($conn, $filter_status);
    $where_parts[] = "c.status = '$fs'";
}
if (!empty($search_q)) {
    $sq = mysqli_real_escape_string($conn, $search_q);
    $where_parts[] = "(c.title LIKE '%$sq%' OR c.category LIKE '%$sq%' OR s.full_name LIKE '%$sq%' OR s.matric_number LIKE '%$sq%')";
}
$where_sql = $where_parts ? "WHERE " . implode(" AND ", $where_parts) : "";

$complaints_result = mysqli_query($conn,
    "SELECT c.*, s.full_name, s.matric_number, s.level, s.email, s.department
     FROM complaints c
     LEFT JOIN students s ON c.student_id = s.id
     $where_sql
     ORDER BY c.created_at DESC"
);
$complaints_arr = [];
if ($complaints_result) {
    while ($row = mysqli_fetch_assoc($complaints_result)) {
        $complaints_arr[] = $row;
    }
}

// ─────────────────────────────────────────
//  RECENT 5 FOR DASHBOARD
// ─────────────────────────────────────────
$recent_result = mysqli_query($conn,
    "SELECT c.*, s.full_name, s.matric_number, s.level
     FROM complaints c
     LEFT JOIN students s ON c.student_id = s.id
     ORDER BY c.created_at DESC LIMIT 5"
);
$recent_arr = [];
if ($recent_result) {
    while ($row = mysqli_fetch_assoc($recent_result)) {
        $recent_arr[] = $row;
    }
}

// ─────────────────────────────────────────
//  HELPERS
// ─────────────────────────────────────────
function statusClass($s) {
    $map = ['Pending'=>'pending','In Review'=>'review','Resolved'=>'resolved','Rejected'=>'rejected'];
    return $map[$s] ?? 'pending';
}
function priorityClass($p) {
    return 'priority-' . strtolower($p ?? 'low');
}
function esc($v) {
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Admin Panel — SCMS</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=Epilogue:wght@300;400;500;600&display=swap" rel="stylesheet">
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
:root,[data-theme="dark"]{
  --bg:#060d1a;
  --sidebar:#080f1e;
  --card:#0c1627;
  --surface:#101e34;
  --surface2:#172540;
  --accent:#3d8ef0;
  --accent2:#63d0f5;
  --gold:#f5c842;
  --text:#ddeeff;
  --text2:#98b4d4;
  --muted:#5a7a9a;
  --border:rgba(61,142,240,0.14);
  --glow:rgba(61,142,240,0.28);
  --shadow:rgba(0,0,0,0.5);
  --p-bg:rgba(245,200,66,0.1);  --p-c:#f5c842;
  --r-bg:rgba(61,142,240,0.1);  --r-c:#63d0f5;
  --g-bg:rgba(34,197,94,0.1);   --g-c:#4ade80;
  --e-bg:rgba(239,68,68,0.1);   --e-c:#f87171;
  --input:#0a1828;
  --hover:rgba(61,142,240,0.07);
}
[data-theme="light"]{
  --bg:#f2f6fc;
  --sidebar:#ffffff;
  --card:#ffffff;
  --surface:#f8fbff;
  --surface2:#eef4fd;
  --accent:#2563eb;
  --accent2:#0ea5e9;
  --gold:#ca8a04;
  --text:#0d1b2e;
  --text2:#334e6e;
  --muted:#7a96b5;
  --border:rgba(37,99,235,0.13);
  --glow:rgba(37,99,235,0.18);
  --shadow:rgba(0,0,0,0.07);
  --p-bg:rgba(202,138,4,0.1);   --p-c:#92400e;
  --r-bg:rgba(37,99,235,0.08);  --r-c:#1d4ed8;
  --g-bg:rgba(22,163,74,0.1);   --g-c:#166534;
  --e-bg:rgba(220,38,38,0.08);  --e-c:#991b1b;
  --input:#f0f6ff;
  --hover:rgba(37,99,235,0.05);
}

*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
html{height:100%;scroll-behavior:smooth;}
body{min-height:100vh;background:var(--bg);color:var(--text);font-family:'Epilogue',sans-serif;display:flex;transition:background .3s,color .3s;}

/* ── SIDEBAR ── */
.sidebar{
  width:256px;min-height:100vh;
  background:var(--sidebar);
  border-right:1px solid var(--border);
  display:flex;flex-direction:column;
  position:fixed;top:0;left:0;bottom:0;
  z-index:200;overflow-y:auto;
  transition:transform .3s cubic-bezier(.4,0,.2,1),background .3s;
}
.brand{display:flex;align-items:center;gap:11px;padding:26px 20px 18px;border-bottom:1px solid var(--border);text-decoration:none;}
.brand-icon{width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,var(--accent),var(--accent2));display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-size:17px;font-weight:800;color:#fff;flex-shrink:0;}
.brand-name{font-family:'Syne',sans-serif;font-size:17px;font-weight:800;color:var(--text);line-height:1.1;}
.brand-sub{font-size:9px;color:var(--muted);letter-spacing:1.2px;text-transform:uppercase;}
.admin-chip{display:inline-block;font-size:8px;font-weight:700;letter-spacing:1px;text-transform:uppercase;background:var(--e-bg);color:var(--e-c);border:1px solid rgba(239,68,68,.2);padding:2px 7px;border-radius:20px;margin-top:2px;}

.sidebar-user{padding:16px 20px;border-bottom:1px solid var(--border);}
.user-av{width:42px;height:42px;border-radius:11px;background:linear-gradient(135deg,#ef4444,#b91c1c);display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-size:17px;font-weight:800;color:#fff;margin-bottom:8px;box-shadow:0 4px 14px rgba(239,68,68,.3);}
.user-name{font-size:13px;font-weight:600;}
.user-role{font-size:10px;color:var(--muted);margin-top:1px;}

.nav{flex:1;padding:14px 10px;}
.nav-label{font-size:9px;font-weight:700;letter-spacing:1.8px;text-transform:uppercase;color:var(--muted);padding:0 10px;margin:14px 0 6px;}
.nav-a{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:9px;font-size:13px;font-weight:500;color:var(--muted);text-decoration:none;transition:all .2s;margin-bottom:1px;border:1px solid transparent;position:relative;}
.nav-a:hover{background:var(--hover);color:var(--text);}
.nav-a.active{background:rgba(61,142,240,.12);color:var(--accent);border-color:rgba(61,142,240,.18);font-weight:600;}
.nav-icon{font-size:16px;width:22px;text-align:center;flex-shrink:0;}
.nav-badge{margin-left:auto;font-size:9px;font-weight:700;background:var(--p-c);color:#111;padding:2px 6px;border-radius:20px;}

.sidebar-foot{padding:12px 10px;border-top:1px solid var(--border);}
.logout-a{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:9px;font-size:13px;font-weight:500;color:var(--e-c);text-decoration:none;transition:all .2s;border:1px solid transparent;background:none;width:100%;cursor:pointer;}
.logout-a:hover{background:var(--e-bg);border-color:rgba(239,68,68,.2);}

/* ── MAIN ── */
.main{margin-left:256px;flex:1;display:flex;flex-direction:column;min-height:100vh;}
.topbar{position:sticky;top:0;z-index:100;height:64px;background:var(--sidebar);border-bottom:1px solid var(--border);padding:0 28px;display:flex;align-items:center;justify-content:space-between;backdrop-filter:blur(10px);}
.hamburger{display:none;flex-direction:column;gap:5px;background:none;border:none;cursor:pointer;padding:3px;}
.hamburger span{width:20px;height:2px;background:var(--text);border-radius:2px;display:block;}
.page-ttl{font-family:'Syne',sans-serif;font-size:17px;font-weight:700;}
.topbar-r{display:flex;align-items:center;gap:10px;}
.theme-tog{width:42px;height:22px;border-radius:20px;background:var(--surface2);border:1px solid var(--border);position:relative;cursor:pointer;flex-shrink:0;}
.tog-knob{width:16px;height:16px;border-radius:50%;background:var(--accent);position:absolute;top:2px;left:2px;transition:transform .3s;display:flex;align-items:center;justify-content:center;font-size:9px;}
[data-theme="light"] .tog-knob{transform:translateX(20px);}
.top-av{width:34px;height:34px;border-radius:9px;background:linear-gradient(135deg,#ef4444,#b91c1c);display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-size:13px;font-weight:800;color:#fff;}

/* ── CONTENT ── */
.content{flex:1;padding:28px;max-width:1280px;width:100%;animation:fadein .35s ease;}
@keyframes fadein{from{opacity:0;transform:translateY(10px);}to{opacity:1;transform:translateY(0);}}

/* ── ALERT ── */
.alert{display:flex;align-items:flex-start;gap:10px;padding:13px 16px;border-radius:11px;margin-bottom:22px;font-size:13px;}
.alert-err{background:var(--e-bg);border:1px solid rgba(239,68,68,.25);color:var(--e-c);}
.alert-ok{background:var(--g-bg);border:1px solid rgba(34,197,94,.25);color:var(--g-c);}
.alert-x{margin-left:auto;background:none;border:none;cursor:pointer;color:inherit;font-size:15px;opacity:.7;line-height:1;}

/* ── SECTION HEADER ── */
.sec-hdr{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;flex-wrap:wrap;gap:10px;}
.sec-ttl{font-family:'Syne',sans-serif;font-size:21px;font-weight:800;letter-spacing:-.4px;}
.sec-sub{font-size:12px;color:var(--muted);margin-top:2px;}

/* ── STATS ── */
.stats{display:grid;grid-template-columns:repeat(6,1fr);gap:12px;margin-bottom:24px;}
.stat{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:18px 14px;display:flex;align-items:center;gap:12px;transition:all .3s;position:relative;overflow:hidden;}
.stat::after{content:'';position:absolute;bottom:0;left:0;height:2px;width:0;background:linear-gradient(90deg,var(--accent),var(--accent2));transition:width .4s;}
.stat:hover::after{width:100%;}
.stat:hover{transform:translateY(-2px);box-shadow:0 10px 28px var(--shadow);}
.stat-ico{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;}
.ico-b{background:rgba(61,142,240,.1);border:1px solid rgba(61,142,240,.18);}
.ico-y{background:var(--p-bg);border:1px solid rgba(245,200,66,.18);}
.ico-g{background:var(--g-bg);border:1px solid rgba(34,197,94,.18);}
.ico-t{background:var(--r-bg);border:1px solid rgba(99,208,245,.18);}
.ico-r{background:var(--e-bg);border:1px solid rgba(239,68,68,.18);}
.ico-p{background:rgba(168,85,247,.1);border:1px solid rgba(168,85,247,.18);}
.stat-n{font-family:'Syne',sans-serif;font-size:22px;font-weight:800;line-height:1;}
.stat-l{font-size:10px;color:var(--muted);margin-top:2px;}

/* ── PANEL ── */
.panel{background:var(--card);border:1px solid var(--border);border-radius:14px;overflow:hidden;margin-bottom:22px;}
.panel-hdr{padding:16px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;}
.panel-ttl{font-family:'Syne',sans-serif;font-size:14px;font-weight:700;}
.panel-body{padding:18px 22px;}

/* ── CONTROLS BAR ── */
.ctrl-bar{display:flex;gap:10px;margin-bottom:18px;flex-wrap:wrap;align-items:center;}
.srch-wrap{position:relative;flex:1;min-width:200px;}
.srch-ico{position:absolute;left:11px;top:50%;transform:translateY(-50%);font-size:13px;color:var(--muted);}
.srch-inp{width:100%;padding:9px 12px 9px 34px;background:var(--input);border:1px solid var(--border);border-radius:9px;color:var(--text);font-family:'Epilogue',sans-serif;font-size:13px;outline:none;transition:all .25s;}
.srch-inp:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(61,142,240,.1);}
.srch-inp::placeholder{color:var(--muted);}
.flt-btn{font-size:11px;font-weight:600;padding:7px 14px;border-radius:20px;border:1px solid var(--border);background:var(--card);color:var(--muted);cursor:pointer;transition:all .2s;text-decoration:none;display:inline-block;white-space:nowrap;}
.flt-btn:hover,.flt-btn.active{border-color:var(--accent);color:var(--accent);background:rgba(61,142,240,.09);}

/* ── TABLE ── */
.ct{width:100%;border-collapse:collapse;}
.ct th{text-align:left;font-size:9px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:var(--muted);padding:11px 13px;border-bottom:1px solid var(--border);background:var(--surface2);white-space:nowrap;}
.ct td{padding:12px 13px;font-size:13px;border-bottom:1px solid var(--border);vertical-align:middle;}
.ct tbody tr:last-child td{border-bottom:none;}
.ct tbody tr:hover td{background:var(--hover);}
.ttl-cell{font-weight:600;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:block;}

/* ── STATUS / PRIORITY PILLS ── */
.pill{font-size:9px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;padding:3px 9px;border-radius:20px;white-space:nowrap;}
.pill-pending{background:var(--p-bg);color:var(--p-c);}
.pill-review{background:var(--r-bg);color:var(--r-c);}
.pill-resolved{background:var(--g-bg);color:var(--g-c);}
.pill-rejected{background:var(--e-bg);color:var(--e-c);}
.priority-high{background:var(--e-bg);color:var(--e-c);}
.priority-medium{background:var(--p-bg);color:var(--p-c);}
.priority-low{background:var(--g-bg);color:var(--g-c);}

/* ── BUTTONS ── */
.btn-sm{font-size:11px;font-weight:600;padding:5px 11px;border-radius:7px;border:1px solid var(--border);background:none;color:var(--accent);cursor:pointer;transition:all .2s;white-space:nowrap;}
.btn-sm:hover,.btn-sm.open{background:rgba(61,142,240,.1);border-color:var(--accent);}
.btn-primary{display:inline-flex;align-items:center;gap:7px;padding:11px 22px;border-radius:10px;background:linear-gradient(135deg,var(--accent),#1e5fc7);color:#fff;font-family:'Syne',sans-serif;font-size:13px;font-weight:700;border:none;cursor:pointer;box-shadow:0 5px 20px var(--glow);transition:all .3s;}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 9px 28px var(--glow);}
.btn-primary:disabled{opacity:.6;cursor:not-allowed;transform:none;}
.btn-danger{background:linear-gradient(135deg,#ef4444,#b91c1c) !important;box-shadow:0 5px 20px rgba(239,68,68,.25) !important;}
.btn-danger:hover{box-shadow:0 9px 28px rgba(239,68,68,.4) !important;}

/* ── DETAIL ROW ── */
.detail-row{display:none;}
.detail-row.open{display:table-row;}
.detail-box{background:var(--surface2);border:1px solid var(--border);border-radius:12px;padding:20px;margin:4px 0 10px;}
.detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;}
.df label{font-size:9px;font-weight:700;letter-spacing:.9px;text-transform:uppercase;color:var(--muted);display:block;margin-bottom:4px;}
.df .val{font-size:13px;color:var(--text2);}
.desc-box{background:rgba(61,142,240,.04);border:1px solid var(--border);border-radius:9px;padding:13px;font-size:13px;color:var(--text2);line-height:1.7;margin-bottom:14px;}
.divider{height:1px;background:var(--border);margin:14px 0;}
.reply-ta{width:100%;padding:11px 13px;background:var(--input);border:1px solid var(--border);border-radius:9px;color:var(--text);font-family:'Epilogue',sans-serif;font-size:13px;outline:none;transition:all .3s;resize:vertical;min-height:90px;line-height:1.6;}
.reply-ta:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(61,142,240,.1);}
.reply-ta::placeholder{color:var(--muted);}
.status-sel{padding:9px 13px;background:var(--input);border:1px solid var(--border);border-radius:9px;color:var(--text);font-family:'Epilogue',sans-serif;font-size:13px;outline:none;cursor:pointer;transition:all .3s;min-width:150px;-webkit-appearance:none;}
.status-sel:focus{border-color:var(--accent);}
.status-sel option{background:var(--surface);color:var(--text);}
.rlbl{font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--muted);margin-bottom:7px;display:block;}
.reply-acts{display:flex;gap:10px;align-items:flex-end;margin-top:11px;flex-wrap:wrap;}
.exist-reply{background:rgba(34,197,94,.05);border:1px solid rgba(34,197,94,.2);border-radius:9px;padding:13px;font-size:13px;color:var(--text2);line-height:1.6;margin-bottom:11px;}
.exist-reply-lbl{font-size:9px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--g-c);margin-bottom:5px;}
.av-sm{width:30px;height:30px;border-radius:8px;background:linear-gradient(135deg,var(--accent),var(--accent2));display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-size:12px;font-weight:800;color:#fff;flex-shrink:0;}
.ev-link{display:inline-flex;align-items:center;gap:5px;color:var(--accent);font-size:12px;text-decoration:none;border:1px solid var(--border);padding:4px 9px;border-radius:6px;background:var(--card);transition:all .2s;}
.ev-link:hover{border-color:var(--accent);background:rgba(61,142,240,.08);}

/* ── COMPLAINT ITEM (dashboard) ── */
.ci{display:flex;align-items:flex-start;gap:12px;padding:13px 0;border-bottom:1px solid var(--border);}
.ci:last-child{border-bottom:none;padding-bottom:0;}
.ci:first-child{padding-top:0;}
.ci-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;margin-top:5px;}

/* ── FORM ELEMENTS ── */
.fg{margin-bottom:16px;}
.fl{display:block;font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--muted);margin-bottom:7px;}
.fi{width:100%;padding:11px 13px;background:var(--input);border:1px solid var(--border);border-radius:9px;color:var(--text);font-family:'Epilogue',sans-serif;font-size:13px;outline:none;transition:all .3s;}
.fi:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(61,142,240,.1);}
.fi::placeholder{color:var(--muted);}
.fi.with-eye{padding-right:44px;}
.iw{position:relative;}
.eye{position:absolute;right:11px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--muted);font-size:15px;cursor:pointer;padding:0;transition:color .2s;line-height:1;}
.eye:hover{color:var(--text);}
.fdivider{height:1px;background:var(--border);margin:22px 0;}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:18px;}

/* ── EMPTY STATE ── */
.empty{text-align:center;padding:44px 22px;}
.empty-ico{font-size:44px;margin-bottom:14px;}
.empty-ttl{font-family:'Syne',sans-serif;font-size:17px;font-weight:700;margin-bottom:7px;}
.empty-desc{font-size:12px;color:var(--muted);}

/* ── SPINNER ── */
.spin{display:inline-block;width:12px;height:12px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:sp .7s linear infinite;}
@keyframes sp{to{transform:rotate(360deg);}}

/* ── OVERLAY & RESPONSIVE ── */
.overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:199;}
.overlay.open{display:block;}
@media(max-width:1200px){.stats{grid-template-columns:repeat(3,1fr);}}
@media(max-width:900px){
  .sidebar{transform:translateX(-100%);}
  .sidebar.open{transform:translateX(0);}
  .main{margin-left:0;}
  .hamburger{display:flex;}
  .grid2{grid-template-columns:1fr;}
  .detail-grid{grid-template-columns:1fr;}
}
@media(max-width:700px){
  .stats{grid-template-columns:repeat(2,1fr);}
  .content{padding:18px 12px;}
  .topbar{padding:0 14px;}
  .ct th:nth-child(4),.ct td:nth-child(4),
  .ct th:nth-child(5),.ct td:nth-child(5){display:none;}
}
</style>
</head>
<body>

<div class="overlay" id="overlay" onclick="closeSidebar()"></div>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <a class="brand" href="dashboard.php">
    <div class="brand-icon">S</div>
    <div>
      <div class="brand-name">SCMS</div>
      <div class="brand-sub">Admin Panel</div>
      <div class="admin-chip">Admin</div>
    </div>
  </a>
  <div class="sidebar-user">
    <div class="user-av"><?= strtoupper(substr($admin_name,0,1)) ?></div>
    <div class="user-name"><?= esc($admin_name) ?></div>
    <div class="user-role">@<?= esc($admin_user) ?> · Administrator</div>
  </div>
  <nav class="nav">
    <div class="nav-label">Management</div>
    <a class="nav-a <?= $active_page==='dashboard'?'active':'' ?>" href="dashboard.php?page=dashboard">
      <span class="nav-icon">🏠</span> Dashboard
    </a>
    <a class="nav-a <?= $active_page==='reports'?'active':'' ?>" href="dashboard.php?page=reports">
      <span class="nav-icon">📋</span> All Reports
      <?php if($pending>0): ?><span class="nav-badge"><?= $pending ?></span><?php endif; ?>
    </a>
    <a class="nav-a <?= $active_page==='students'?'active':'' ?>" href="dashboard.php?page=students">
      <span class="nav-icon">🎓</span> Students
    </a>
    <div class="nav-label">Account</div>
    <a class="nav-a <?= $active_page==='settings'?'active':'' ?>" href="dashboard.php?page=settings">
      <span class="nav-icon">⚙️</span> Settings
    </a>
  </nav>
  <div class="sidebar-foot">
    <a class="logout-a" href="logout.php"><span class="nav-icon">🚪</span> Logout</a>
  </div>
</aside>

<!-- MAIN -->
<div class="main">
  <header class="topbar">
    <div style="display:flex;align-items:center;gap:14px;">
      <button class="hamburger" onclick="openSidebar()"><span></span><span></span><span></span></button>
      <div class="page-ttl">
        <?= ['dashboard'=>'Dashboard','reports'=>'All Reports','students'=>'Students','settings'=>'Settings'][$active_page] ?? 'Dashboard' ?>
      </div>
    </div>
    <div class="topbar-r">
      <span id="thmLbl" style="font-size:12px;color:var(--muted);">🌙</span>
      <div class="theme-tog" onclick="toggleTheme()">
        <div class="tog-knob" id="thmKnob">🌙</div>
      </div>
      <div class="top-av"><?= strtoupper(substr($admin_name,0,1)) ?></div>
    </div>
  </header>

  <div class="content">

    <?php if(!empty($message)): ?>
    <div class="alert <?= $msg_type==='success'?'alert-ok':'alert-err' ?>" id="alertBox">
      <div><?= $msg_type==='success'?'✅':'⚠️' ?></div>
      <div><?= esc($message) ?></div>
      <button class="alert-x" onclick="this.parentElement.remove()">✕</button>
    </div>
    <?php endif; ?>

    <!-- ══════ DASHBOARD ══════ -->
    <?php if($active_page==='dashboard'): ?>
    <div class="sec-hdr">
      <div>
        <div class="sec-ttl">Welcome back, <?= esc(explode(' ',$admin_name)[0]) ?> 👋</div>
        <div class="sec-sub"><?= date('l, F j Y') ?></div>
      </div>
    </div>
    <div class="stats">
      <div class="stat"><div class="stat-ico ico-b">📊</div><div><div class="stat-n"><?= $total ?></div><div class="stat-l">Total</div></div></div>
      <div class="stat"><div class="stat-ico ico-y">⏳</div><div><div class="stat-n"><?= $pending ?></div><div class="stat-l">Pending</div></div></div>
      <div class="stat"><div class="stat-ico ico-t">🔍</div><div><div class="stat-n"><?= $inreview ?></div><div class="stat-l">In Review</div></div></div>
      <div class="stat"><div class="stat-ico ico-g">✅</div><div><div class="stat-n"><?= $resolved ?></div><div class="stat-l">Resolved</div></div></div>
      <div class="stat"><div class="stat-ico ico-r">❌</div><div><div class="stat-n"><?= $rejected ?></div><div class="stat-l">Rejected</div></div></div>
      <div class="stat"><div class="stat-ico ico-p">🎓</div><div><div class="stat-n"><?= $students ?></div><div class="stat-l">Students</div></div></div>
    </div>
    <div class="panel">
      <div class="panel-hdr">
        <div class="panel-ttl">🕐 Recent Reports</div>
        <a class="btn-sm" href="dashboard.php?page=reports">View All →</a>
      </div>
      <div class="panel-body">
        <?php if(empty($recent_arr)): ?>
          <div class="empty"><div class="empty-ico">📭</div><div class="empty-ttl">No reports yet</div><div class="empty-desc">Reports will appear here once students submit them.</div></div>
        <?php else: ?>
          <?php foreach($recent_arr as $c):
            $dc=['Pending'=>'#f5c842','Resolved'=>'#4ade80','In Review'=>'#63d0f5','Rejected'=>'#f87171'][$c['status']]??'#5a7a9a';
          ?>
          <div class="ci">
            <div class="ci-dot" style="background:<?= $dc ?>"></div>
            <div style="flex:1;">
              <div style="font-size:14px;font-weight:600;margin-bottom:3px;"><?= esc($c['title']) ?></div>
              <div style="font-size:11px;color:var(--muted);display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                <span>👤 <?= esc($c['full_name']??'Unknown') ?></span>
                <span>🎫 <?= esc($c['matric_number']??'—') ?></span>
                <span>📁 <?= esc($c['category']) ?></span>
                <span>📅 <?= date('M d, Y',strtotime($c['created_at'])) ?></span>
                <span class="pill pill-<?= statusClass($c['status']) ?>"><?= esc($c['status']) ?></span>
              </div>
            </div>
            <a class="btn-sm" href="dashboard.php?page=reports&open=<?= $c['id'] ?>">View</a>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- ══════ REPORTS ══════ -->
    <?php elseif($active_page==='reports'): ?>
    <div class="sec-hdr">
      <div>
        <div class="sec-ttl">All Reports</div>
        <div class="sec-sub"><?= count($complaints_arr) ?> report<?= count($complaints_arr)!==1?'s':'' ?> found</div>
      </div>
    </div>
    <div class="ctrl-bar">
      <div class="srch-wrap">
        <span class="srch-ico">🔍</span>
        <form method="GET" action="dashboard.php" id="searchForm" style="display:contents;">
          <input type="hidden" name="page" value="reports">
          <input type="hidden" name="status" value="<?= esc($filter_status) ?>">
          <input type="text" name="q" value="<?= esc($search_q) ?>"
            class="srch-inp" placeholder="Search title, student, matric…"
            oninput="debSearch()">
        </form>
      </div>
      <?php
        $sf=['all'=>"All ($total)",'Pending'=>"⏳ Pending ($pending)",'In Review'=>"🔍 In Review ($inreview)",'Resolved'=>"✅ Resolved ($resolved)",'Rejected'=>"❌ Rejected ($rejected)"];
        foreach($sf as $v=>$l):
      ?>
      <a class="flt-btn <?= $filter_status===$v?'active':'' ?>"
         href="dashboard.php?page=reports&status=<?= urlencode($v) ?>&q=<?= urlencode($search_q) ?>"><?= $l ?></a>
      <?php endforeach; ?>
    </div>
    <div class="panel">
      <?php if(empty($complaints_arr)): ?>
        <div class="empty"><div class="empty-ico">🔎</div><div class="empty-ttl">No reports found</div><div class="empty-desc">Adjust your filters or search term.</div></div>
      <?php else: ?>
      <div style="overflow-x:auto;">
        <table class="ct">
          <thead>
            <tr><th>#</th><th>Student</th><th>Title</th><th>Category</th><th>Priority</th><th>Date</th><th>Status</th><th>Action</th></tr>
          </thead>
          <tbody>
            <?php foreach($complaints_arr as $i=>$c):
              $open_id=(int)($_GET['open']??0);
              $is_open=$open_id===(int)$c['id'];
            ?>
            <tr>
              <td style="color:var(--muted);font-size:11px;"><?= $i+1 ?></td>
              <td>
                <div style="display:flex;align-items:center;gap:7px;">
                  <div class="av-sm"><?= strtoupper(substr($c['full_name']??'U',0,1)) ?></div>
                  <div>
                    <div style="font-size:12px;font-weight:600;"><?= esc($c['full_name']??'Unknown') ?></div>
                    <div style="font-size:10px;color:var(--muted);"><?= esc($c['matric_number']??'—') ?></div>
                  </div>
                </div>
              </td>
              <td><span class="ttl-cell" title="<?= esc($c['title']) ?>"><?= esc(mb_strlen($c['title'])>36?mb_substr($c['title'],0,36).'…':$c['title']) ?></span></td>
              <td style="font-size:12px;color:var(--muted);"><?= esc($c['category']) ?></td>
              <td><span class="pill <?= priorityClass($c['priority']) ?>"><?= esc($c['priority']) ?></span></td>
              <td style="font-size:11px;color:var(--muted);white-space:nowrap;"><?= date('M d, Y',strtotime($c['created_at'])) ?></td>
              <td><span class="pill pill-<?= statusClass($c['status']) ?>"><?= esc($c['status']) ?></span></td>
              <td>
                <button class="btn-sm <?= $is_open?'open':'' ?>" id="btn-<?= $c['id'] ?>" onclick="toggleDetail(<?= $c['id'] ?>)">
                  <?= $is_open?'Hide ▲':'Manage ▼' ?>
                </button>
              </td>
            </tr>
            <!-- DETAIL / REPLY ROW -->
            <tr class="detail-row <?= $is_open?'open':'' ?>" id="detail-<?= $c['id'] ?>">
              <td colspan="8" style="padding:0 13px 16px;">
                <div class="detail-box">
                  <div class="detail-grid">
                    <div class="df">
                      <label>Student</label>
                      <div class="val" style="display:flex;align-items:center;gap:8px;">
                        <div class="av-sm" style="width:34px;height:34px;font-size:13px;"><?= strtoupper(substr($c['full_name']??'U',0,1)) ?></div>
                        <div>
                          <div style="font-weight:600;font-size:13px;"><?= esc($c['full_name']??'Unknown') ?></div>
                          <div style="font-size:11px;color:var(--muted);"><?= esc($c['matric_number']??'—') ?> · <?= esc($c['department']??'') ?> · <?= esc($c['level']??'—') ?> Level</div>
                          <?php if(!empty($c['email'])): ?><div style="font-size:11px;color:var(--muted);">✉️ <?= esc($c['email']) ?></div><?php endif; ?>
                        </div>
                      </div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                      <div class="df"><label>ID</label><div class="val" style="font-family:'Syne',sans-serif;font-weight:700;">#<?= $c['id'] ?></div></div>
                      <div class="df"><label>Category</label><div class="val"><?= esc($c['category']) ?></div></div>
                      <div class="df"><label>Priority</label><span class="pill <?= priorityClass($c['priority']) ?>"><?= esc($c['priority']) ?></span></div>
                      <div class="df"><label>Status</label><span class="pill pill-<?= statusClass($c['status']) ?>"><?= esc($c['status']) ?></span></div>
                      <div class="df"><label>Submitted</label><div class="val"><?= date('M d, Y h:i A',strtotime($c['created_at'])) ?></div></div>
                    </div>
                  </div>

                  <div class="df" style="margin-bottom:11px;">
                    <label>Title</label>
                    <div style="font-size:15px;font-weight:700;font-family:'Syne',sans-serif;"><?= esc($c['title']) ?></div>
                  </div>
                  <div class="df" style="margin-bottom:13px;">
                    <label>Description</label>
                    <div class="desc-box"><?= nl2br(esc($c['description'])) ?></div>
                  </div>
                  <?php if(!empty($c['evidence'])): ?>
                  <div class="df" style="margin-bottom:13px;">
                    <label>Evidence</label>
                    <a class="ev-link" href="../uploads/evidence/<?= esc($c['evidence']) ?>" target="_blank">📎 <?= esc($c['evidence']) ?></a>
                  </div>
                  <?php endif; ?>

                  <div class="divider"></div>

                  <?php if(!empty($c['admin_response'])): ?>
                  <div style="margin-bottom:13px;">
                    <div class="exist-reply-lbl">✅ Current Response</div>
                    <div class="exist-reply"><?= nl2br(esc($c['admin_response'])) ?></div>
                  </div>
                  <?php endif; ?>

                  <!-- REPLY FORM — posts to THIS page -->
                  <form method="POST"
                        action="dashboard.php?page=reports&status=<?= urlencode($filter_status) ?>&q=<?= urlencode($search_q) ?>"
                        id="rf-<?= $c['id'] ?>">
                    <input type="hidden" name="complaint_id" value="<?= (int)$c['id'] ?>">

                    <div style="margin-bottom:11px;">
                      <label class="rlbl"><?= !empty($c['admin_response'])?'✏️ Edit Reply':'💬 Write Reply' ?></label>
                      <textarea name="admin_response" class="reply-ta"
                        placeholder="Write your response to the student…"
                        required><?= esc($c['admin_response']??'') ?></textarea>
                    </div>

                    <div class="reply-acts">
                      <div>
                        <label class="rlbl" style="margin-bottom:5px;">Update Status</label>
                        <select name="new_status" class="status-sel">
                          <?php foreach(['Pending','In Review','Resolved','Rejected'] as $s): ?>
                          <option value="<?= $s ?>" <?= $c['status']===$s?'selected':'' ?>><?= $s ?></option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <button type="submit" name="submit_reply" value="1" class="btn-primary" id="rb-<?= $c['id'] ?>">
                        💾 Save &amp; Update
                      </button>
                    </div>
                  </form>

                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <!-- ══════ STUDENTS ══════ -->
    <?php elseif($active_page==='students'): ?>
    <div class="sec-hdr">
      <div>
        <div class="sec-ttl">Students</div>
        <div class="sec-sub"><?= $students ?> registered student<?= $students!==1?'s':'' ?></div>
      </div>
    </div>
    <?php
      $sr = mysqli_query($conn,
        "SELECT s.*,
                COUNT(c.id) AS total_reports,
                SUM(c.status='Pending') AS pc,
                SUM(c.status='Resolved') AS rc,
                MAX(c.created_at) AS last_r
         FROM students s
         LEFT JOIN complaints c ON c.student_id=s.id
         GROUP BY s.id
         ORDER BY last_r DESC, s.full_name ASC"
      );
      $sarr=[];
      if($sr) while($r=mysqli_fetch_assoc($sr)) $sarr[]=$r;
    ?>
    <div class="panel">
      <?php if(empty($sarr)): ?>
        <div class="empty"><div class="empty-ico">🎓</div><div class="empty-ttl">No students registered</div></div>
      <?php else: ?>
      <div style="overflow-x:auto;">
        <table class="ct">
          <thead><tr><th>#</th><th>Student</th><th>Matric No.</th><th>Department</th><th>Level</th><th>Reports</th><th>Pending</th><th>Resolved</th><th>Last Report</th></tr></thead>
          <tbody>
            <?php foreach($sarr as $i=>$st): ?>
            <tr>
              <td style="color:var(--muted);font-size:11px;"><?= $i+1 ?></td>
              <td>
                <div style="display:flex;align-items:center;gap:7px;">
                  <div class="av-sm"><?= strtoupper(substr($st['full_name'],0,1)) ?></div>
                  <div>
                    <div style="font-size:13px;font-weight:600;"><?= esc($st['full_name']) ?></div>
                    <?php if(!empty($st['email'])): ?><div style="font-size:10px;color:var(--muted);"><?= esc($st['email']) ?></div><?php endif; ?>
                  </div>
                </div>
              </td>
              <td style="font-family:'Syne',sans-serif;font-size:13px;font-weight:600;"><?= esc($st['matric_number']) ?></td>
              <td style="font-size:12px;color:var(--muted);"><?= esc($st['department']??'—') ?></td>
              <td style="font-size:12px;"><?= esc($st['level']??'—') ?></td>
              <td><span style="font-family:'Syne',sans-serif;font-weight:700;font-size:16px;"><?= (int)$st['total_reports'] ?></span></td>
              <td><?php if((int)$st['pc']>0): ?><span class="pill pill-pending"><?= (int)$st['pc'] ?></span><?php else: ?><span style="color:var(--muted);font-size:12px;">—</span><?php endif; ?></td>
              <td><?php if((int)$st['rc']>0): ?><span class="pill pill-resolved"><?= (int)$st['rc'] ?></span><?php else: ?><span style="color:var(--muted);font-size:12px;">—</span><?php endif; ?></td>
              <td style="font-size:11px;color:var(--muted);"><?= $st['last_r']?date('M d, Y',strtotime($st['last_r'])):'—' ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <!-- ══════ SETTINGS ══════ -->
    <?php elseif($active_page==='settings'): ?>
    <div class="sec-hdr">
      <div><div class="sec-ttl">Settings</div><div class="sec-sub">Manage your admin account</div></div>
    </div>
    <div class="grid2">
      <!-- Admin info panel -->
      <div class="panel">
        <div class="panel-hdr"><div class="panel-ttl">👤 Admin Account</div></div>
        <div class="panel-body">
          <div style="display:flex;align-items:center;gap:14px;margin-bottom:22px;">
            <div style="width:60px;height:60px;border-radius:14px;background:linear-gradient(135deg,#ef4444,#b91c1c);display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-size:24px;font-weight:800;color:#fff;box-shadow:0 5px 18px rgba(239,68,68,.3);"><?= strtoupper(substr($admin_name,0,1)) ?></div>
            <div>
              <div style="font-family:'Syne',sans-serif;font-size:17px;font-weight:800;"><?= esc($admin_name) ?></div>
              <div style="font-size:12px;color:var(--muted);">@<?= esc($admin_user) ?></div>
              <div class="admin-chip" style="margin-top:5px;">Administrator</div>
            </div>
          </div>
          <div style="background:var(--surface2);border:1px solid var(--border);border-radius:11px;padding:14px;display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <?php foreach([['Total Reports',$total,'var(--accent)'],['Resolved',$resolved,'var(--g-c)'],['Pending',$pending,'var(--p-c)'],['Students',$students,'#a855f7']] as [$lbl,$num,$col]): ?>
            <div style="text-align:center;padding:12px;background:var(--card);border-radius:9px;border:1px solid var(--border);">
              <div style="font-family:'Syne',sans-serif;font-size:22px;font-weight:800;color:<?= $col ?>;"><?= $num ?></div>
              <div style="font-size:11px;color:var(--muted);margin-top:2px;"><?= $lbl ?></div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Password change panel -->
      <div class="panel">
        <div class="panel-hdr"><div class="panel-ttl">🔒 Change Password</div></div>
        <div class="panel-body">
          <form method="POST" action="dashboard.php?page=settings" id="pwdForm">
            <div class="fg">
              <label class="fl">Current Password</label>
              <div class="iw">
                <input type="password" name="current_password" id="cp" class="fi with-eye" placeholder="Enter current password" required autocomplete="current-password">
                <button type="button" class="eye" onclick="eyeTog('cp',this)">👁️</button>
              </div>
            </div>
            <div class="fg">
              <label class="fl">New Password</label>
              <div class="iw">
                <input type="password" name="new_password" id="np" class="fi with-eye" placeholder="Minimum 8 characters" required minlength="8" autocomplete="new-password">
                <button type="button" class="eye" onclick="eyeTog('np',this)">👁️</button>
              </div>
              <div style="margin-top:7px;">
                <div style="height:3px;background:var(--surface2);border-radius:2px;overflow:hidden;"><div id="sBar" style="height:100%;width:0;border-radius:2px;transition:width .4s,background .4s;"></div></div>
                <div id="sLbl" style="font-size:11px;color:var(--muted);margin-top:3px;">Enter a new password</div>
              </div>
            </div>
            <div class="fg">
              <label class="fl">Confirm New Password</label>
              <div class="iw">
                <input type="password" name="confirm_password" id="cfp" class="fi with-eye" placeholder="Repeat new password" required autocomplete="new-password">
                <button type="button" class="eye" onclick="eyeTog('cfp',this)">👁️</button>
              </div>
              <div id="mHint" style="font-size:11px;margin-top:4px;display:none;"></div>
            </div>
            <div class="fdivider"></div>
            <button type="submit" name="change_admin_password" value="1" class="btn-primary btn-danger" id="cpBtn">
              🔐 Change Password
            </button>
          </form>
        </div>
      </div>
    </div>
    <?php endif; ?>

  </div><!-- /content -->
</div><!-- /main -->

<script>
// ── THEME ──────────────────────────────────────────────
const html = document.documentElement;
const saved = localStorage.getItem('scms_admin_theme') || 'dark';
html.setAttribute('data-theme', saved);
applyTheme(saved);

function toggleTheme() {
  const next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
  html.setAttribute('data-theme', next);
  localStorage.setItem('scms_admin_theme', next);
  applyTheme(next);
}
function applyTheme(t) {
  const icon = t === 'light' ? '☀️' : '🌙';
  document.getElementById('thmKnob').textContent = icon;
  document.getElementById('thmLbl').textContent  = icon;
}

// ── SIDEBAR ──────────────────────────────────────────────
function openSidebar()  { document.getElementById('sidebar').classList.add('open'); document.getElementById('overlay').classList.add('open'); }
function closeSidebar() { document.getElementById('sidebar').classList.remove('open'); document.getElementById('overlay').classList.remove('open'); }

// ── TOGGLE COMPLAINT DETAIL ROW ──────────────────────────
function toggleDetail(id) {
  const row = document.getElementById('detail-' + id);
  const btn = document.getElementById('btn-' + id);
  const wasOpen = row.classList.contains('open');

  document.querySelectorAll('.detail-row.open').forEach(r => r.classList.remove('open'));
  document.querySelectorAll('.btn-sm.open').forEach(b => { b.classList.remove('open'); b.textContent = 'Manage ▼'; });

  if (!wasOpen) {
    row.classList.add('open');
    btn.classList.add('open');
    btn.textContent = 'Hide ▲';
    setTimeout(() => row.scrollIntoView({ behavior: 'smooth', block: 'nearest' }), 60);
  }
}
// Auto-open from ?open= param
(function () {
  const id = new URLSearchParams(window.location.search).get('open');
  if (!id) return;
  const row = document.getElementById('detail-' + id);
  const btn = document.getElementById('btn-' + id);
  if (row) { row.classList.add('open'); if (btn) { btn.classList.add('open'); btn.textContent = 'Hide ▲'; } setTimeout(() => row.scrollIntoView({ behavior:'smooth', block:'center' }), 200); }
})();

// ── REPLY FORM: loading state ────────────────────────────


// ── SEARCH DEBOUNCE ──────────────────────────────────────
let st;
function debSearch() { clearTimeout(st); st = setTimeout(() => document.getElementById('searchForm').submit(), 500); }

// ── PASSWORD EYE TOGGLE ──────────────────────────────────
function eyeTog(id, btn) {
  const el = document.getElementById(id);
  el.type = el.type === 'password' ? 'text' : 'password';
  btn.textContent = el.type === 'password' ? '👁️' : '🙈';
}

// ── PASSWORD STRENGTH ────────────────────────────────────
const npEl = document.getElementById('np');
if (npEl) {
  npEl.addEventListener('input', function () {
    const v = this.value; let s = 0;
    if (v.length >= 8) s++;
    if (/[A-Z]/.test(v)) s++;
    if (/[0-9]/.test(v)) s++;
    if (/[^A-Za-z0-9]/.test(v)) s++;
    const cfg = [
      { w: '0%', c: 'transparent', t: 'Enter a new password' },
      { w: '25%', c: '#f87171', t: 'Weak' },
      { w: '50%', c: '#fb923c', t: 'Fair' },
      { w: '75%', c: '#f5c842', t: 'Good' },
      { w: '100%', c: '#4ade80', t: 'Strong ✓' },
    ];
    document.getElementById('sBar').style.width      = cfg[s].w;
    document.getElementById('sBar').style.background = cfg[s].c;
    document.getElementById('sLbl').textContent      = cfg[s].t;
    document.getElementById('sLbl').style.color      = s === 0 ? 'var(--muted)' : cfg[s].c;
    chkMatch();
  });
}
const cfpEl = document.getElementById('cfp');
if (cfpEl) cfpEl.addEventListener('input', chkMatch);

function chkMatch() {
  const hint = document.getElementById('mHint');
  const nv   = document.getElementById('np')?.value  || '';
  const cv   = document.getElementById('cfp')?.value || '';
  if (!hint || !cv) { if (hint) hint.style.display = 'none'; return; }
  hint.style.display = 'block';
  hint.textContent   = cv === nv ? '✓ Passwords match' : '✗ Passwords do not match';
  hint.style.color   = cv === nv ? '#4ade80' : '#f87171';
}

// ── PASSWORD FORM: client-side guard ────────────────────
const pwdFormEl = document.getElementById('pwdForm');
if (pwdFormEl) {
  pwdFormEl.addEventListener('submit', function (e) {
    const nv = document.getElementById('np').value;
    const cv = document.getElementById('cfp').value;
    if (nv !== cv) {
      e.preventDefault();
      chkMatch();
      document.getElementById('cfp').focus();
      return;
    }
    const btn = document.getElementById('cpBtn');
    btn.innerHTML = '<span class="spin"></span> Changing…';
    btn.disabled  = true;
  });
}

// ── AUTO DISMISS ALERT ───────────────────────────────────
const ab = document.getElementById('alertBox');
if (ab) setTimeout(() => { ab.style.transition = 'opacity .5s'; ab.style.opacity = '0'; setTimeout(() => ab.remove(), 500); }, 5000);
</script>
</body>
</html>