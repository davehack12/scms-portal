<?php
session_start();

// Auth guard
if (!isset($_SESSION['student_id'])) {
    header("Location: ../login.php");
    exit();
}

include("../includes/config.php");

$student_id    = $_SESSION['student_id'];
$student_name  = $_SESSION['student_name'];
$student_email = $_SESSION['student_email'];
$student_matric= $_SESSION['student_matric'];
$student_dept  = $_SESSION['student_dept'];
$student_level = $_SESSION['student_level'];

$message  = "";
$msg_type = "";
$active_page = isset($_GET['page']) ? $_GET['page'] : (isset($_POST['page']) ? $_POST['page'] : 'dashboard');

// ─────────────────────────────────────────
//  HANDLE SUBMIT COMPLAINT
// ─────────────────────────────────────────
if (isset($_POST['submit_complaint'])) {
    $title       = trim(mysqli_real_escape_string($conn, $_POST['title']));
    $category    = trim(mysqli_real_escape_string($conn, $_POST['category']));
    $description = trim(mysqli_real_escape_string($conn, $_POST['description']));
    $priority    = trim(mysqli_real_escape_string($conn, $_POST['priority']));
    $evidence    = "";

    // Validate required fields first
    if (empty($title) || empty($category) || empty($description) || empty($priority)) {
        $message  = "Please fill in all required fields.";
        $msg_type = "error";
        $active_page = 'submit';
    } else {
        // Handle optional file upload
        if (!empty($_FILES['evidence']['name']) && $_FILES['evidence']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['jpg','jpeg','png','pdf','doc','docx'];
            $ext     = strtolower(pathinfo($_FILES['evidence']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed)) {
                $message  = "Invalid file type. Allowed: JPG, PNG, PDF, DOC, DOCX.";
                $msg_type = "error";
                $active_page = 'submit';
            } else {
                // Use absolute path — relative paths often fail with move_uploaded_file
                $upload_dir = dirname(__DIR__) . '/uploads/evidence/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($_FILES['evidence']['name']));
                if (move_uploaded_file($_FILES['evidence']['tmp_name'], $upload_dir . $filename)) {
                    $evidence = $filename;
                } else {
                    // Upload failed but don't block complaint — save without evidence
                    $evidence = "";
                    $message  = "Note: File upload failed (check folder permissions), but your complaint was saved.";
                    $msg_type = "error";
                }
            }
        } elseif (!empty($_FILES['evidence']['name']) && $_FILES['evidence']['error'] !== UPLOAD_ERR_NO_FILE) {
            // A file was chosen but had a PHP upload error
            $php_upload_errors = [
                UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit.',
                UPLOAD_ERR_FORM_SIZE  => 'File exceeds form upload limit.',
                UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder on server.',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                UPLOAD_ERR_EXTENSION  => 'A PHP extension blocked the upload.',
            ];
            $err_code = $_FILES['evidence']['error'];
            $message  = "Upload error: " . ($php_upload_errors[$err_code] ?? "Unknown error code $err_code");
            $msg_type = "error";
        }

        // Only insert if no upload error occurred
        if (empty($message)) {
            $insert = mysqli_query($conn,
                "INSERT INTO complaints (student_id, title, category, description, priority, evidence, status, created_at)
                 VALUES ('$student_id','$title','$category','$description','$priority','$evidence','Pending', NOW())"
            );
            if ($insert) {
                $message  = "Complaint submitted successfully! We'll get back to you soon.";
                $msg_type = "success";
                $active_page = 'complaints';
            } else {
                $message  = "Failed to submit complaint. Error: " . mysqli_error($conn);
                $msg_type = "error";
                $active_page = 'submit';
            }
        }
    }
}

// ─────────────────────────────────────────
//  HANDLE PROFILE UPDATE
// ─────────────────────────────────────────
if (isset($_POST['update_profile'])) {
    $new_name  = trim(mysqli_real_escape_string($conn, $_POST['full_name']));
    $new_dept  = trim(mysqli_real_escape_string($conn, $_POST['department']));
    $new_level = trim(mysqli_real_escape_string($conn, $_POST['level']));

    if (empty($new_name)) {
        $message  = "Full name cannot be empty.";
        $msg_type = "error";
    } else {
        $update = mysqli_query($conn,
            "UPDATE students SET full_name='$new_name', department='$new_dept', level='$new_level' WHERE id='$student_id'"
        );
        if ($update) {
            $_SESSION['student_name']  = $new_name;
            $_SESSION['student_dept']  = $new_dept;
            $_SESSION['student_level'] = $new_level;
            $student_name  = $new_name;
            $student_dept  = $new_dept;
            $student_level = $new_level;
            $message  = "Profile updated successfully!";
            $msg_type = "success";
        } else {
            $message  = "Failed to update profile. Please try again.";
            $msg_type = "error";
        }
    }
    $active_page = 'profile';
}

// ─────────────────────────────────────────
//  HANDLE PASSWORD CHANGE
// ─────────────────────────────────────────
if (isset($_POST['change_password'])) {
    $current = $_POST['current_password'];
    $new_pwd = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT password FROM students WHERE id='$student_id'"));

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
        $ok = mysqli_query($conn, "UPDATE students SET password='$hashed' WHERE id='$student_id'");
        if ($ok) {
            $message  = "Password changed successfully!";
            $msg_type = "success";
        } else {
            $message  = "Failed to change password. Please try again.";
            $msg_type = "error";
        }
    }
    $active_page = 'profile';
}

// ─────────────────────────────────────────
//  FETCH DATA
// ─────────────────────────────────────────
$all_complaints = mysqli_query($conn,
    "SELECT * FROM complaints WHERE student_id='$student_id' ORDER BY created_at DESC"
);
$complaints_arr = [];
while ($row = mysqli_fetch_assoc($all_complaints)) {
    $complaints_arr[] = $row;
}

$total    = count($complaints_arr);
$pending  = count(array_filter($complaints_arr, fn($c) => $c['status'] === 'Pending'));
$resolved = count(array_filter($complaints_arr, fn($c) => $c['status'] === 'Resolved'));
$inreview = count(array_filter($complaints_arr, fn($c) => $c['status'] === 'In Review'));
$rejected = count(array_filter($complaints_arr, fn($c) => $c['status'] === 'Rejected'));

$recent     = array_slice($complaints_arr, 0, 3);
$first_name = explode(' ', $student_name)[0];

// Status -> CSS class map (used multiple times below)
function statusClass($status) {
    $map = ['Pending'=>'pending','In Review'=>'review','Resolved'=>'resolved','Rejected'=>'rejected'];
    return $map[$status] ?? 'pending';
}
function priorityClass($p) {
    return 'priority-' . strtolower($p);
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Dashboard — SCMS</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
  <style>
    [data-theme="dark"]{--bg:#050b18;--sidebar:#0b1628;--surface:#0f1e35;--surface2:#152741;--card:#0d1a2e;--accent:#2f80ed;--accent2:#56ccf2;--gold:#f2c94c;--text:#e8eef8;--text2:#b0c4de;--muted:#7a90b3;--border:rgba(47,128,237,0.18);--glow:rgba(47,128,237,0.3);--shadow:rgba(0,0,0,0.4);--pending:rgba(242,201,76,0.12);--pending-c:#f2c94c;--review:rgba(47,128,237,0.12);--review-c:#56ccf2;--resolved:rgba(39,174,96,0.12);--resolved-c:#6fcf97;--rejected:rgba(235,87,87,0.12);--rejected-c:#eb5757;--input-bg:#111f3a;--hover:rgba(47,128,237,0.08);}
    [data-theme="light"]{--bg:#f0f4fb;--sidebar:#ffffff;--surface:#ffffff;--surface2:#f8faff;--card:#ffffff;--accent:#2f80ed;--accent2:#1a6fd4;--gold:#d4960f;--text:#0d1a2e;--text2:#3a5070;--muted:#8a9bb5;--border:rgba(47,128,237,0.15);--glow:rgba(47,128,237,0.2);--shadow:rgba(0,0,0,0.08);--pending:rgba(242,201,76,0.15);--pending-c:#b8860b;--review:rgba(47,128,237,0.1);--review-c:#1a6fd4;--resolved:rgba(39,174,96,0.12);--resolved-c:#1e8a4a;--rejected:rgba(235,87,87,0.1);--rejected-c:#c0392b;--input-bg:#f4f7fd;--hover:rgba(47,128,237,0.05);}

    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
    html{height:100%;scroll-behavior:smooth;}
    body{min-height:100vh;background:var(--bg);color:var(--text);font-family:'DM Sans',sans-serif;display:flex;transition:background 0.3s,color 0.3s;}

    /* ── SIDEBAR ── */
    .sidebar{width:260px;min-height:100vh;background:var(--sidebar);border-right:1px solid var(--border);display:flex;flex-direction:column;position:fixed;top:0;left:0;bottom:0;z-index:100;transition:transform 0.3s ease,background 0.3s;overflow-y:auto;}
    .sidebar-brand{display:flex;align-items:center;gap:12px;padding:28px 24px 20px;border-bottom:1px solid var(--border);text-decoration:none;}
    .brand-badge{width:40px;height:40px;border-radius:11px;background:linear-gradient(135deg,var(--accent),var(--accent2));display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-size:18px;font-weight:800;color:white;flex-shrink:0;}
    .brand-text{font-family:'Syne',sans-serif;font-size:18px;font-weight:800;color:var(--text);}
    .brand-sub{font-size:10px;color:var(--muted);letter-spacing:1px;text-transform:uppercase;}
    .sidebar-user{padding:20px 24px;border-bottom:1px solid var(--border);}
    .user-avatar{width:48px;height:48px;border-radius:14px;background:linear-gradient(135deg,var(--accent),var(--accent2));display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-size:20px;font-weight:800;color:white;margin-bottom:10px;}
    .user-name{font-size:14px;font-weight:600;color:var(--text);}
    .user-matric{font-size:11px;color:var(--muted);margin-top:2px;}
    .sidebar-nav{flex:1;padding:16px 12px;}
    .nav-section-label{font-size:10px;font-weight:600;letter-spacing:1.5px;text-transform:uppercase;color:var(--muted);padding:0 12px;margin:16px 0 8px;}
    .nav-item{display:flex;align-items:center;gap:12px;padding:11px 14px;border-radius:10px;font-size:14px;font-weight:500;color:var(--muted);text-decoration:none;cursor:pointer;transition:all 0.2s;margin-bottom:2px;border:1px solid transparent;}
    .nav-item:hover{background:var(--hover);color:var(--text);}
    .nav-item.active{background:rgba(47,128,237,0.12);color:var(--accent);border-color:rgba(47,128,237,0.2);font-weight:600;}
    .nav-icon{font-size:18px;width:24px;text-align:center;flex-shrink:0;}
    .nav-badge{margin-left:auto;font-size:10px;font-weight:700;background:var(--accent);color:white;padding:2px 7px;border-radius:100px;}
    .sidebar-footer{padding:16px 12px;border-top:1px solid var(--border);}
    .logout-btn{display:flex;align-items:center;gap:12px;padding:11px 14px;border-radius:10px;font-size:14px;font-weight:500;color:var(--rejected-c);text-decoration:none;cursor:pointer;transition:all 0.2s;width:100%;border:1px solid transparent;background:none;}
    .logout-btn:hover{background:var(--rejected);border-color:rgba(235,87,87,0.2);}

    /* ── MAIN ── */
    .main{margin-left:260px;flex:1;display:flex;flex-direction:column;min-height:100vh;}
    .topbar{position:sticky;top:0;z-index:50;background:var(--sidebar);border-bottom:1px solid var(--border);padding:0 32px;height:68px;display:flex;align-items:center;justify-content:space-between;backdrop-filter:blur(12px);transition:background 0.3s;}
    .topbar-left{display:flex;align-items:center;gap:16px;}
    .hamburger{display:none;flex-direction:column;gap:5px;background:none;border:none;cursor:pointer;padding:4px;}
    .hamburger span{width:22px;height:2px;background:var(--text);border-radius:2px;display:block;transition:0.3s;}
    .page-title{font-family:'Syne',sans-serif;font-size:18px;font-weight:700;}
    .topbar-right{display:flex;align-items:center;gap:12px;}
    .theme-toggle{width:44px;height:24px;border-radius:100px;background:var(--surface2);border:1px solid var(--border);position:relative;cursor:pointer;transition:background 0.3s;flex-shrink:0;}
    .theme-toggle-knob{width:18px;height:18px;border-radius:50%;background:var(--accent);position:absolute;top:2px;left:2px;transition:transform 0.3s ease;display:flex;align-items:center;justify-content:center;font-size:10px;}
    [data-theme="light"] .theme-toggle-knob{transform:translateX(20px);}
    .theme-label{font-size:12px;color:var(--muted);}
    .topbar-avatar{width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,var(--accent),var(--accent2));display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-size:14px;font-weight:800;color:white;}

    /* ── CONTENT ── */
    .content{flex:1;padding:32px;max-width:1200px;width:100%;animation:fadeIn 0.4s ease;}
    @keyframes fadeIn{from{opacity:0;transform:translateY(12px);}to{opacity:1;transform:translateY(0);}}

    /* ── ALERT ── */
    .alert{display:flex;align-items:flex-start;gap:12px;padding:14px 18px;border-radius:12px;margin-bottom:24px;font-size:13px;animation:fadeIn 0.4s ease;}
    .alert-error{background:var(--rejected);border:1px solid rgba(235,87,87,0.3);color:var(--rejected-c);}
    .alert-success{background:var(--resolved);border:1px solid rgba(39,174,96,0.3);color:var(--resolved-c);}
    .alert-icon{font-size:17px;flex-shrink:0;}
    .alert-close{margin-left:auto;background:none;border:none;cursor:pointer;color:inherit;font-size:16px;opacity:0.7;line-height:1;}
    .alert-close:hover{opacity:1;}

    /* ── SECTION HEADER ── */
    .section-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px;}
    .section-title{font-family:'Syne',sans-serif;font-size:22px;font-weight:800;letter-spacing:-0.5px;}
    .section-sub{font-size:13px;color:var(--muted);margin-top:2px;}

    /* ── STAT CARDS ── */
    .stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:28px;}
    .stat-card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:22px 20px;display:flex;align-items:center;gap:16px;transition:all 0.3s;position:relative;overflow:hidden;}
    .stat-card::after{content:'';position:absolute;bottom:0;left:0;height:2px;width:0;background:linear-gradient(90deg,var(--accent),var(--accent2));transition:width 0.4s;}
    .stat-card:hover::after{width:100%;}
    .stat-card:hover{transform:translateY(-3px);box-shadow:0 12px 32px var(--shadow);}
    .stat-icon{width:48px;height:48px;border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;}
    .si-blue{background:rgba(47,128,237,0.12);border:1px solid rgba(47,128,237,0.2);}
    .si-yellow{background:var(--pending);border:1px solid rgba(242,201,76,0.2);}
    .si-green{background:var(--resolved);border:1px solid rgba(39,174,96,0.2);}
    .si-teal{background:var(--review);border:1px solid rgba(86,204,242,0.2);}
    .stat-num{font-family:'Syne',sans-serif;font-size:28px;font-weight:800;line-height:1;}
    .stat-label{font-size:12px;color:var(--muted);margin-top:3px;}

    /* ── PANELS ── */
    .panel{background:var(--card);border:1px solid var(--border);border-radius:16px;overflow:hidden;transition:background 0.3s;}
    .panel-header{padding:20px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
    .panel-title-sm{font-family:'Syne',sans-serif;font-size:15px;font-weight:700;}
    .panel-body{padding:20px 24px;}
    .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:20px;}

    /* ── COMPLAINT ITEMS (dashboard) ── */
    .complaint-item{display:flex;align-items:flex-start;gap:14px;padding:16px 0;border-bottom:1px solid var(--border);transition:all 0.2s;}
    .complaint-item:last-child{border-bottom:none;padding-bottom:0;}
    .complaint-item:first-child{padding-top:0;}
    .ci-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0;margin-top:5px;}
    .complaint-info{flex:1;}
    .complaint-title-text{font-size:14px;font-weight:600;margin-bottom:4px;color:var(--text);}
    .complaint-meta{font-size:11px;color:var(--muted);display:flex;gap:12px;flex-wrap:wrap;align-items:center;}

    /* ── PILLS ── */
    .pill{font-size:10px;font-weight:700;letter-spacing:0.5px;text-transform:uppercase;padding:3px 10px;border-radius:100px;flex-shrink:0;}
    .pill-pending{background:var(--pending);color:var(--pending-c);}
    .pill-review{background:var(--review);color:var(--review-c);}
    .pill-resolved{background:var(--resolved);color:var(--resolved-c);}
    .pill-rejected{background:var(--rejected);color:var(--rejected-c);}
    .priority-high{background:rgba(235,87,87,0.12);color:#eb5757;}
    .priority-medium{background:rgba(242,201,76,0.12);color:var(--pending-c);}
    .priority-low{background:rgba(39,174,96,0.12);color:var(--resolved-c);}

    /* ── TABLE ── */
    .complaints-table{width:100%;border-collapse:collapse;}
    .complaints-table th{text-align:left;font-size:11px;font-weight:600;letter-spacing:0.8px;text-transform:uppercase;color:var(--muted);padding:12px 16px;border-bottom:1px solid var(--border);background:var(--surface2);}
    .complaints-table td{padding:14px 16px;font-size:13px;border-bottom:1px solid var(--border);vertical-align:middle;color:var(--text);}
    .complaints-table tr:last-child td{border-bottom:none;}
    .complaints-table tbody tr[data-status]:hover td{background:var(--hover);}
    .response-row{display:none;}
    .response-row.open{display:table-row;}
    .response-box{background:rgba(47,128,237,0.05);border:1px solid var(--border);border-radius:10px;padding:14px 16px;font-size:13px;color:var(--text2);line-height:1.6;margin:4px 0;}
    .response-label{font-size:11px;font-weight:600;letter-spacing:0.5px;text-transform:uppercase;color:var(--muted);margin-bottom:6px;}
    .view-btn{background:none;border:1px solid var(--border);color:var(--accent);font-size:11px;font-weight:600;padding:4px 10px;border-radius:6px;cursor:pointer;transition:all 0.2s;}
    .view-btn:hover{background:rgba(47,128,237,0.1);}
    .view-btn.open{background:rgba(47,128,237,0.12);border-color:var(--accent);}

    /* ── EMPTY STATE ── */
    .empty-state{text-align:center;padding:48px 24px;}
    .empty-icon{font-size:48px;margin-bottom:16px;}
    .empty-title{font-family:'Syne',sans-serif;font-size:18px;font-weight:700;margin-bottom:8px;}
    .empty-desc{font-size:13px;color:var(--muted);}

    /* ── FORMS ── */
    .form-group{margin-bottom:20px;}
    .form-row-2{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
    .form-label{display:block;font-size:11px;font-weight:600;letter-spacing:0.8px;text-transform:uppercase;color:var(--muted);margin-bottom:8px;}
    .form-input,.form-select,.form-textarea{width:100%;padding:12px 14px;background:var(--input-bg);border:1px solid var(--border);border-radius:10px;color:var(--text);font-family:'DM Sans',sans-serif;font-size:14px;outline:none;transition:all 0.3s;-webkit-appearance:none;}
    .form-input::placeholder,.form-textarea::placeholder{color:var(--muted);}
    .form-input:focus,.form-select:focus,.form-textarea:focus{border-color:var(--accent);background:var(--hover);box-shadow:0 0 0 3px rgba(47,128,237,0.1);}
    .form-select{cursor:pointer;}
    .form-select option{background:var(--surface);color:var(--text);}
    .form-textarea{resize:vertical;min-height:120px;line-height:1.6;}
    .form-hint{font-size:11px;color:var(--muted);margin-top:5px;}

    /* ── FILE UPLOAD ── */
    .file-upload-zone{border:2px dashed var(--border);border-radius:12px;padding:28px;text-align:center;cursor:pointer;transition:all 0.3s;position:relative;}
    .file-upload-zone:hover{border-color:var(--accent);background:var(--hover);}
    .file-upload-zone.has-file{border-color:var(--accent);background:rgba(47,128,237,0.05);}
    .file-upload-zone input[type="file"]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;}
    .upload-icon{font-size:28px;margin-bottom:8px;}
    .upload-text{font-size:13px;color:var(--muted);}
    .upload-text strong{color:var(--accent);}
    .file-name-display{font-size:13px;color:var(--accent);margin-top:6px;font-weight:500;}

    /* ── PASSWORD ── */
    .input-wrap{position:relative;}
    .pwd-toggle{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--muted);font-size:16px;cursor:pointer;transition:color 0.2s;padding:0;line-height:1;}
    .pwd-toggle:hover{color:var(--text);}
    .form-input.has-toggle{padding-right:42px;}

    /* ── BUTTONS ── */
    .btn-primary{display:inline-flex;align-items:center;gap:8px;padding:13px 28px;border-radius:11px;background:linear-gradient(135deg,var(--accent),#1e5fc7);color:white;font-family:'Syne',sans-serif;font-size:14px;font-weight:700;border:none;cursor:pointer;box-shadow:0 6px 24px var(--glow);transition:all 0.3s;position:relative;overflow:hidden;}
    .btn-primary::before{content:'';position:absolute;inset:0;background:linear-gradient(90deg,transparent,rgba(255,255,255,0.15),transparent);transform:translateX(-100%);transition:transform 0.5s;}
    .btn-primary:hover::before{transform:translateX(100%);}
    .btn-primary:hover{transform:translateY(-2px);box-shadow:0 10px 32px var(--glow);}
    .btn-primary:disabled{opacity:0.6;cursor:not-allowed;transform:none;}
    .btn-secondary{display:inline-flex;align-items:center;gap:8px;padding:13px 28px;border-radius:11px;background:transparent;border:1px solid var(--border);color:var(--text);font-family:'Syne',sans-serif;font-size:14px;font-weight:600;cursor:pointer;transition:all 0.3s;}
    .btn-secondary:hover{background:var(--hover);border-color:var(--accent);}
    .btn-danger{background:linear-gradient(135deg,#eb5757,#c0392b);box-shadow:0 6px 24px rgba(235,87,87,0.25);}
    .btn-danger:hover{box-shadow:0 10px 32px rgba(235,87,87,0.4);}
    .form-divider{height:1px;background:var(--border);margin:28px 0;}

    /* ── PROFILE ── */
    .profile-avatar-big{width:80px;height:80px;border-radius:20px;background:linear-gradient(135deg,var(--accent),var(--accent2));display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-size:32px;font-weight:800;color:white;margin-bottom:16px;box-shadow:0 8px 24px var(--glow);}
    .profile-name-big{font-family:'Syne',sans-serif;font-size:22px;font-weight:800;margin-bottom:4px;}
    .profile-detail{font-size:13px;color:var(--muted);margin-bottom:3px;}

    /* ── QUICK ACTIONS ── */
    .quick-actions{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:24px;}
    .quick-card{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:20px;display:flex;align-items:center;gap:14px;cursor:pointer;transition:all 0.3s;text-decoration:none;color:var(--text);}
    .quick-card:hover{transform:translateY(-3px);border-color:var(--accent);box-shadow:0 12px 32px var(--shadow);}
    .quick-icon{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;}
    .quick-title{font-size:14px;font-weight:600;}
    .quick-desc{font-size:11px;color:var(--muted);margin-top:2px;}

    /* ── FILTER BAR ── */
    .filter-bar{display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;}
    .filter-btn{font-size:12px;font-weight:600;padding:6px 14px;border-radius:100px;border:1px solid var(--border);background:var(--card);color:var(--muted);cursor:pointer;transition:all 0.2s;}
    .filter-btn:hover{border-color:var(--accent);color:var(--accent);}
    .filter-btn.active{border-color:var(--accent);color:var(--accent);background:rgba(47,128,237,0.1);}

    /* ── MOBILE OVERLAY ── */
    .sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:99;}
    .sidebar-overlay.open{display:block;}

    /* ── LOADING SPINNER ── */
    .btn-spinner{display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,0.3);border-top-color:white;border-radius:50%;animation:spin 0.7s linear infinite;}
    @keyframes spin{to{transform:rotate(360deg);}}

    /* ── CHAR COUNTER ── */
    .char-counter{font-size:11px;color:var(--muted);text-align:right;margin-top:4px;}
    .char-counter.warn{color:var(--pending-c);}
    .char-counter.over{color:var(--rejected-c);}

    /* ── RESPONSIVE ── */
    @media(max-width:1100px){.stats-grid{grid-template-columns:1fr 1fr;}}
    @media(max-width:900px){
      .sidebar{transform:translateX(-100%);}
      .sidebar.open{transform:translateX(0);}
      .main{margin-left:0;}
      .hamburger{display:flex;}
      .grid-2{grid-template-columns:1fr;}
      .form-row-2{grid-template-columns:1fr;}
      .quick-actions{grid-template-columns:1fr;}
    }
    @media(max-width:600px){
      .stats-grid{grid-template-columns:1fr 1fr;}
      .content{padding:20px 16px;}
      .topbar{padding:0 16px;}
      .complaints-table th:nth-child(3),.complaints-table td:nth-child(3),
      .complaints-table th:nth-child(4),.complaints-table td:nth-child(4){display:none;}
    }
  </style>
</head>
<body>
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- ══ SIDEBAR ══ -->
<aside class="sidebar" id="sidebar">
  <a class="sidebar-brand" href="../index.php">
    <div class="brand-badge">S</div>
    <div>
      <div class="brand-text">SCMS</div>
      <div class="brand-sub">Student Portal</div>
    </div>
  </a>

  <div class="sidebar-user">
    <div class="user-avatar"><?= strtoupper(substr($student_name, 0, 1)) ?></div>
    <div class="user-name"><?= htmlspecialchars($student_name) ?></div>
    <div class="user-matric"><?= htmlspecialchars($student_matric) ?> · <?= htmlspecialchars($student_level) ?> Level</div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-label">Main</div>

    <a class="nav-item <?= $active_page==='dashboard'?'active':'' ?>" href="dashboard.php?page=dashboard">
      <span class="nav-icon">🏠</span> Dashboard
    </a>
    <a class="nav-item <?= $active_page==='submit'?'active':'' ?>" href="dashboard.php?page=submit">
      <span class="nav-icon">✏️</span> Submit Complaint
    </a>
    <a class="nav-item <?= $active_page==='complaints'?'active':'' ?>" href="dashboard.php?page=complaints">
      <span class="nav-icon">📋</span> My Complaints
      <?php if($pending > 0): ?>
        <span class="nav-badge"><?= $pending ?></span>
      <?php endif; ?>
    </a>

    <div class="nav-section-label">Account</div>
    <a class="nav-item <?= $active_page==='profile'?'active':'' ?>" href="dashboard.php?page=profile">
      <span class="nav-icon">👤</span> Profile
    </a>
  </nav>

  <div class="sidebar-footer">
    <a class="logout-btn" href="../logout.php">
      <span class="nav-icon">🚪</span> Logout
    </a>
  </div>
</aside>

<!-- ══ MAIN ══ -->
<div class="main">

  <!-- TOPBAR -->
  <header class="topbar">
    <div class="topbar-left">
      <button class="hamburger" onclick="openSidebar()" aria-label="Open menu">
        <span></span><span></span><span></span>
      </button>
      <div class="page-title" id="pageTitle">
        <?php
          $titles = ['dashboard'=>'Dashboard','submit'=>'Submit Complaint','complaints'=>'My Complaints','profile'=>'My Profile'];
          echo $titles[$active_page] ?? 'Dashboard';
        ?>
      </div>
    </div>
    <div class="topbar-right">
      <span class="theme-label" id="themeLabel">🌙</span>
      <div class="theme-toggle" onclick="toggleTheme()" title="Toggle dark/light mode">
        <div class="theme-toggle-knob" id="themeKnob">🌙</div>
      </div>
      <div class="topbar-avatar"><?= strtoupper(substr($student_name, 0, 1)) ?></div>
    </div>
  </header>

  <!-- CONTENT -->
  <div class="content">

    <?php if (!empty($message)): ?>
    <div class="alert <?= $msg_type==='success'?'alert-success':'alert-error' ?>" id="alertBox">
      <div class="alert-icon"><?= $msg_type==='success'?'✅':'⚠️' ?></div>
      <div><?= htmlspecialchars($message) ?></div>
      <button class="alert-close" onclick="this.parentElement.remove()" aria-label="Dismiss">✕</button>
    </div>
    <?php endif; ?>

    <!-- ════════ DASHBOARD ════════ -->
    <?php if($active_page === 'dashboard'): ?>

    <div class="section-header">
      <div>
        <div class="section-title">Good <?= date('H')<12?'Morning':(date('H')<17?'Afternoon':'Evening') ?>, <?= htmlspecialchars($first_name) ?> 👋</div>
        <div class="section-sub">Here's an overview of your complaint activity</div>
      </div>
    </div>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon si-blue">📝</div>
        <div><div class="stat-num"><?= $total ?></div><div class="stat-label">Total Submitted</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon si-yellow">⏳</div>
        <div><div class="stat-num"><?= $pending ?></div><div class="stat-label">Pending</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon si-teal">🔍</div>
        <div><div class="stat-num"><?= $inreview ?></div><div class="stat-label">In Review</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon si-green">✅</div>
        <div><div class="stat-num"><?= $resolved ?></div><div class="stat-label">Resolved</div></div>
      </div>
    </div>

    <div class="quick-actions">
      <a class="quick-card" href="dashboard.php?page=submit">
        <div class="quick-icon si-blue">✏️</div>
        <div>
          <div class="quick-title">Submit New Complaint</div>
          <div class="quick-desc">Report an issue to the school administration</div>
        </div>
      </a>
      <a class="quick-card" href="dashboard.php?page=complaints">
        <div class="quick-icon si-teal">📡</div>
        <div>
          <div class="quick-title">Track My Complaints</div>
          <div class="quick-desc">Check status and admin responses</div>
        </div>
      </a>
    </div>

    <div class="panel">
      <div class="panel-header">
        <div class="panel-title-sm">📋 Recent Complaints</div>
        <a class="view-btn" href="dashboard.php?page=complaints">View All →</a>
      </div>
      <div class="panel-body">
        <?php if (empty($recent)): ?>
          <div class="empty-state">
            <div class="empty-icon">📭</div>
            <div class="empty-title">No complaints yet</div>
            <div class="empty-desc">Submit your first complaint using the button above</div>
          </div>
        <?php else: ?>
          <?php foreach($recent as $c):
            $dotColor = ['Pending'=>'#f2c94c','Resolved'=>'#27ae60','In Review'=>'#56ccf2','Rejected'=>'#eb5757'][$c['status']] ?? '#7a90b3';
          ?>
          <div class="complaint-item">
            <div class="ci-dot" style="background:<?= $dotColor ?>"></div>
            <div class="complaint-info">
              <div class="complaint-title-text"><?= htmlspecialchars($c['title']) ?></div>
              <div class="complaint-meta">
                <span>📁 <?= htmlspecialchars($c['category']) ?></span>
                <span>📅 <?= date('M d, Y', strtotime($c['created_at'])) ?></span>
                <span class="pill pill-<?= statusClass($c['status']) ?>"><?= $c['status'] ?></span>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- ════════ SUBMIT COMPLAINT ════════ -->
    <?php elseif($active_page === 'submit'): ?>

    <div class="section-header">
      <div>
        <div class="section-title">Submit a Complaint</div>
        <div class="section-sub">Describe your issue clearly so we can help you faster</div>
      </div>
    </div>

    <div class="panel">
      <div class="panel-header">
        <div class="panel-title-sm">✏️ New Complaint Form</div>
      </div>
      <div class="panel-body">
        <form method="POST" action="dashboard.php" enctype="multipart/form-data" id="complaintForm">
          <!-- hidden field keeps active page after POST -->
          <input type="hidden" name="page" value="submit">

          <div class="form-group">
            <label class="form-label" for="complaintTitle">Complaint Title <span style="color:var(--rejected-c)">*</span></label>
            <input type="text" id="complaintTitle" name="title" class="form-input"
              placeholder="e.g. Broken projector in Hall B"
              maxlength="150" required
              value="<?= isset($_POST['title']) ? htmlspecialchars($_POST['title']) : '' ?>">
            <div class="char-counter" id="titleCounter">0 / 150</div>
          </div>

          <div class="form-row-2">
            <div class="form-group">
              <label class="form-label" for="complaintCategory">Category <span style="color:var(--rejected-c)">*</span></label>
              <select id="complaintCategory" name="category" class="form-select" required>
                <option value="" disabled <?= !isset($_POST['category'])?'selected':'' ?>>Select category</option>
                <?php
                $cats = ['Academic'=>'📚','Facility'=>'🏫','Financial'=>'💰','Health & Safety'=>'🏥','Hostel'=>'🛏️','Library'=>'📖','Security'=>'🔒','Staff Conduct'=>'👔','Transportation'=>'🚌','Other'=>'💬'];
                foreach($cats as $cat => $icon):
                  $sel = (isset($_POST['category']) && $_POST['category']===$cat) ? 'selected' : '';
                ?>
                <option value="<?= $cat ?>" <?= $sel ?>><?= $icon ?> <?= $cat ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label" for="complaintPriority">Priority Level <span style="color:var(--rejected-c)">*</span></label>
              <select id="complaintPriority" name="priority" class="form-select" required>
                <option value="" disabled <?= !isset($_POST['priority'])?'selected':'' ?>>Select priority</option>
                <?php foreach(['Low'=>'🟢 Low — Not urgent','Medium'=>'🟡 Medium — Needs attention','High'=>'🔴 High — Urgent matter'] as $val=>$label):
                  $sel = (isset($_POST['priority']) && $_POST['priority']===$val)?'selected':'';
                ?>
                <option value="<?= $val ?>" <?= $sel ?>><?= $label ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="complaintDesc">Complaint Description <span style="color:var(--rejected-c)">*</span></label>
            <textarea id="complaintDesc" name="description" class="form-textarea"
              placeholder="Describe your complaint in detail. Include: what happened, when it happened, where it happened, and who was involved (if applicable)."
              required minlength="20" maxlength="2000"><?= isset($_POST['description']) ? htmlspecialchars($_POST['description']) : '' ?></textarea>
            <div class="char-counter" id="descCounter">0 / 2000</div>
          </div>

          <div class="form-group">
            <label class="form-label">Upload Evidence <span style="color:var(--muted);font-weight:400;text-transform:none;letter-spacing:0;">(Optional)</span></label>
            <div class="file-upload-zone" id="uploadZone">
              <input type="file" name="evidence" id="evidenceFile" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" onchange="handleFileSelect(this)">
              <div class="upload-icon" id="uploadIcon">📎</div>
              <div class="upload-text"><strong>Click to upload</strong> or drag and drop</div>
              <div class="file-name-display" id="fileNameDisplay">JPG, PNG, PDF, DOC, DOCX — max 5MB</div>
            </div>
            <div id="fileError" style="font-size:12px;color:var(--rejected-c);margin-top:6px;display:none;"></div>
          </div>

          <div class="form-divider"></div>

          <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
            <button type="submit" name="submit_complaint" class="btn-primary" id="submitComplaintBtn">
              🚀 Submit Complaint
            </button>
            <button type="button" class="btn-secondary" onclick="resetComplaintForm()">
              🗑️ Clear Form
            </button>
          </div>

        </form>
      </div>
    </div>

    <!-- ════════ MY COMPLAINTS ════════ -->
    <?php elseif($active_page === 'complaints'): ?>

    <div class="section-header">
      <div>
        <div class="section-title">My Complaints</div>
        <div class="section-sub"><?= $total ?> total complaint<?= $total!==1?'s':'' ?> submitted</div>
      </div>
      <a class="btn-primary" href="dashboard.php?page=submit">✏️ New Complaint</a>
    </div>

    <!-- Filter bar -->
    <div class="filter-bar" id="filterBar">
      <button class="filter-btn active" data-filter="all" onclick="filterTable('all', this)">All (<?= $total ?>)</button>
      <button class="filter-btn" data-filter="Pending" onclick="filterTable('Pending', this)">⏳ Pending (<?= $pending ?>)</button>
      <button class="filter-btn" data-filter="In Review" onclick="filterTable('In Review', this)">🔍 In Review (<?= $inreview ?>)</button>
      <button class="filter-btn" data-filter="Resolved" onclick="filterTable('Resolved', this)">✅ Resolved (<?= $resolved ?>)</button>
      <?php if($rejected > 0): ?>
      <button class="filter-btn" data-filter="Rejected" onclick="filterTable('Rejected', this)">❌ Rejected (<?= $rejected ?>)</button>
      <?php endif; ?>
    </div>

    <div class="panel">
      <?php if(empty($complaints_arr)): ?>
        <div class="empty-state">
          <div class="empty-icon">📭</div>
          <div class="empty-title">No complaints submitted yet</div>
          <div class="empty-desc">When you submit a complaint, it'll appear here</div>
        </div>
      <?php else: ?>
      <div style="overflow-x:auto;">
        <table class="complaints-table" id="complaintsTable">
          <thead>
            <tr>
              <th>#</th>
              <th>Title</th>
              <th>Category</th>
              <th>Priority</th>
              <th>Date</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($complaints_arr as $i => $c): ?>
            <tr data-status="<?= htmlspecialchars($c['status']) ?>" data-id="<?= $c['id'] ?>">
              <td style="color:var(--muted);font-size:12px;"><?= $i+1 ?></td>
              <td style="font-weight:600;max-width:200px;" title="<?= htmlspecialchars($c['title']) ?>">
                <?= htmlspecialchars(mb_strlen($c['title'])>40 ? mb_substr($c['title'],0,40).'…' : $c['title']) ?>
              </td>
              <td><span style="font-size:12px;color:var(--muted);"><?= htmlspecialchars($c['category']) ?></span></td>
              <td><span class="pill <?= priorityClass($c['priority']) ?>"><?= htmlspecialchars($c['priority']) ?></span></td>
              <td style="font-size:12px;color:var(--muted);white-space:nowrap;"><?= date('M d, Y', strtotime($c['created_at'])) ?></td>
              <td><span class="pill pill-<?= statusClass($c['status']) ?>"><?= $c['status'] ?></span></td>
              <td>
                <button class="view-btn" id="btn-<?= $c['id'] ?>" onclick="toggleResponse(<?= $c['id'] ?>)">
                  View ▼
                </button>
              </td>
            </tr>
            <!-- Detail / response row -->
            <tr class="response-row" id="resp-<?= $c['id'] ?>">
              <td colspan="7" style="padding:0 16px 16px;">
                <div style="background:var(--surface2);border:1px solid var(--border);border-radius:12px;padding:18px;margin-top:4px;">

                  <div style="margin-bottom:14px;">
                    <div class="response-label">📝 Description</div>
                    <div class="response-box"><?= nl2br(htmlspecialchars($c['description'])) ?></div>
                  </div>

                  <?php if(!empty($c['admin_response'])): ?>
                  <div style="margin-bottom:14px;">
                    <div class="response-label">💬 Admin Response</div>
                    <div class="response-box" style="background:rgba(39,174,96,0.05);border-color:rgba(39,174,96,0.25);">
                      <?= nl2br(htmlspecialchars($c['admin_response'])) ?>
                    </div>
                  </div>
                  <?php else: ?>
                  <div style="margin-bottom:14px;">
                    <div class="response-label" style="color:var(--muted);font-style:italic;text-transform:none;letter-spacing:0;font-size:12px;">
                      💬 No admin response yet — you'll be notified when there's an update.
                    </div>
                  </div>
                  <?php endif; ?>

                  <?php if(!empty($c['evidence'])): ?>
                  <div>
                    <div class="response-label">📎 Attached Evidence</div>
                    <a href="../uploads/evidence/<?= htmlspecialchars($c['evidence']) ?>" target="_blank" rel="noopener"
                       style="display:inline-flex;align-items:center;gap:6px;color:var(--accent);font-size:13px;text-decoration:none;border:1px solid var(--border);padding:6px 12px;border-radius:8px;background:var(--card);transition:all 0.2s;"
                       onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='var(--border)'">
                      📄 <?= htmlspecialchars($c['evidence']) ?> <span style="font-size:11px;opacity:0.7;">↗</span>
                    </a>
                  </div>
                  <?php endif; ?>

                  <!-- Meta info row -->
                  <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border);display:flex;gap:20px;flex-wrap:wrap;">
                    <span style="font-size:11px;color:var(--muted);">
                      📅 Submitted: <strong style="color:var(--text2);"><?= date('M d, Y \a\t h:i A', strtotime($c['created_at'])) ?></strong>
                    </span>
                    <?php if(!empty($c['updated_at']) && $c['updated_at'] !== $c['created_at']): ?>
                    <span style="font-size:11px;color:var(--muted);">
                      🔄 Updated: <strong style="color:var(--text2);"><?= date('M d, Y \a\t h:i A', strtotime($c['updated_at'])) ?></strong>
                    </span>
                    <?php endif; ?>
                  </div>

                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <!-- No-results row (shown by JS when filter has 0 matches) -->
    <div id="noFilterResults" style="display:none;text-align:center;padding:40px;color:var(--muted);font-size:14px;">
      No complaints match this filter.
    </div>

    <!-- ════════ PROFILE ════════ -->
    <?php elseif($active_page === 'profile'): ?>

    <div class="section-header">
      <div>
        <div class="section-title">My Profile</div>
        <div class="section-sub">Manage your account information and security</div>
      </div>
    </div>

    <div class="grid-2">

      <!-- Left col: info + stats -->
      <div>
        <div class="panel" style="margin-bottom:20px;">
          <div class="panel-header"><div class="panel-title-sm">👤 Personal Information</div></div>
          <div class="panel-body">
            <div style="display:flex;align-items:center;gap:20px;margin-bottom:28px;">
              <div class="profile-avatar-big"><?= strtoupper(substr($student_name,0,1)) ?></div>
              <div>
                <div class="profile-name-big"><?= htmlspecialchars($student_name) ?></div>
                <div class="profile-detail">✉️ <?= htmlspecialchars($student_email) ?></div>
                <div class="profile-detail">🎫 <?= htmlspecialchars($student_matric) ?></div>
                <div class="profile-detail">🏛️ <?= htmlspecialchars($student_dept) ?> · <?= htmlspecialchars($student_level) ?> Level</div>
              </div>
            </div>

            <form method="POST" action="dashboard.php" id="profileForm">
              <input type="hidden" name="page" value="profile">
              <div class="form-group">
                <label class="form-label" for="fullName">Full Name</label>
                <input type="text" id="fullName" name="full_name" class="form-input"
                  value="<?= htmlspecialchars($student_name) ?>" required>
              </div>
              <div class="form-row-2">
                <div class="form-group">
                  <label class="form-label" for="levelSel">Level</label>
                  <select id="levelSel" name="level" class="form-select">
                    <?php foreach(['ND1','ND2','HND1','HND2'] as $l): ?>
                      <option value="<?= $l ?>" <?= $student_level===$l?'selected':'' ?>><?= $l ?> Level</option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label" for="deptInput">Department</label>
                  <input type="text" id="deptInput" name="department" class="form-input"
                    value="<?= htmlspecialchars($student_dept) ?>">
                </div>
              </div>
              <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" class="form-input"
                  value="<?= htmlspecialchars($student_email) ?>" disabled
                  style="opacity:0.5;cursor:not-allowed;">
                <div class="form-hint">🔒 Email address cannot be changed</div>
              </div>
              <div class="form-divider"></div>
              <button type="submit" name="update_profile" class="btn-primary">💾 Save Changes</button>
            </form>
          </div>
        </div>

        <!-- Complaint stats summary -->
        <div class="panel">
          <div class="panel-header"><div class="panel-title-sm">📊 Complaint History</div></div>
          <div class="panel-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
              <?php $stats = [['Total',$total,'var(--accent)','var(--surface2)','var(--border)'],['Pending',$pending,'var(--pending-c)','var(--pending)','rgba(242,201,76,0.2)'],['In Review',$inreview,'var(--review-c)','var(--review)','rgba(86,204,242,0.2)'],['Resolved',$resolved,'var(--resolved-c)','var(--resolved)','rgba(39,174,96,0.2)']];
              foreach($stats as [$label,$num,$col,$bg,$br]): ?>
              <div style="text-align:center;padding:16px;background:<?= $bg ?>;border-radius:12px;border:1px solid <?= $br ?>;">
                <div style="font-family:'Syne',sans-serif;font-size:28px;font-weight:800;color:<?= $col ?>;"><?= $num ?></div>
                <div style="font-size:12px;color:var(--muted);margin-top:3px;"><?= $label ?></div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Right col: change password -->
      <div>
        <div class="panel">
          <div class="panel-header"><div class="panel-title-sm">🔒 Change Password</div></div>
          <div class="panel-body">
            <form method="POST" action="dashboard.php" id="pwdForm">
              <input type="hidden" name="page" value="profile">

              <div class="form-group">
                <label class="form-label" for="currentPwd">Current Password</label>
                <div class="input-wrap">
                  <input type="password" id="currentPwd" name="current_password"
                    class="form-input has-toggle" placeholder="Enter current password" required>
                  <button type="button" class="pwd-toggle" onclick="togglePwd('currentPwd', this)" aria-label="Show/hide password">👁️</button>
                </div>
              </div>

              <div class="form-group">
                <label class="form-label" for="newPwd">New Password</label>
                <div class="input-wrap">
                  <input type="password" id="newPwd" name="new_password"
                    class="form-input has-toggle" placeholder="Minimum 8 characters" required minlength="8">
                  <button type="button" class="pwd-toggle" onclick="togglePwd('newPwd', this)" aria-label="Show/hide password">👁️</button>
                </div>
                <div style="margin-top:8px;">
                  <div style="height:3px;border-radius:2px;background:var(--surface2);overflow:hidden;">
                    <div id="pwdStrengthBar" style="height:100%;width:0;border-radius:2px;transition:width 0.4s,background 0.4s;"></div>
                  </div>
                  <div id="pwdStrengthLabel" style="font-size:11px;color:var(--muted);margin-top:4px;">Enter a new password</div>
                </div>
              </div>

              <div class="form-group">
                <label class="form-label" for="confirmNewPwd">Confirm New Password</label>
                <div class="input-wrap">
                  <input type="password" id="confirmNewPwd" name="confirm_password"
                    class="form-input has-toggle" placeholder="Repeat new password" required>
                  <button type="button" class="pwd-toggle" onclick="togglePwd('confirmNewPwd', this)" aria-label="Show/hide password">👁️</button>
                </div>
                <div id="pwdMatchHint" style="font-size:11px;margin-top:5px;display:none;"></div>
              </div>

              <div class="form-divider"></div>
              <button type="submit" name="change_password" class="btn-primary btn-danger" id="changePwdBtn">
                🔐 Change Password
              </button>
            </form>
          </div>
        </div>
      </div>

    </div>
    <?php endif; ?>

  </div><!-- /content -->
</div><!-- /main -->

<script>
// ── THEME ──────────────────────────────────
const html = document.documentElement;
const saved = localStorage.getItem('scms_theme') || 'dark';
html.setAttribute('data-theme', saved);
updateThemeUI(saved);

function toggleTheme() {
  const next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
  html.setAttribute('data-theme', next);
  localStorage.setItem('scms_theme', next);
  updateThemeUI(next);
}
function updateThemeUI(theme) {
  const knob  = document.getElementById('themeKnob');
  const label = document.getElementById('themeLabel');
  const icon  = theme === 'light' ? '☀️' : '🌙';
  knob.textContent  = icon;
  label.textContent = icon;
}

// ── SIDEBAR (mobile) ────────────────────────
function openSidebar() {
  document.getElementById('sidebar').classList.add('open');
  document.getElementById('sidebarOverlay').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeSidebar() {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sidebarOverlay').classList.remove('open');
  document.body.style.overflow = '';
}

// ── TOGGLE COMPLAINT RESPONSE ROW ──────────
function toggleResponse(id) {
  const respRow = document.getElementById('resp-' + id);
  const btn     = document.getElementById('btn-' + id);
  const isOpen  = respRow.classList.contains('open');

  // Close all others first
  document.querySelectorAll('.response-row.open').forEach(r => r.classList.remove('open'));
  document.querySelectorAll('.view-btn.open').forEach(b => {
    b.classList.remove('open');
    b.textContent = 'View ▼';
  });

  if (!isOpen) {
    respRow.classList.add('open');
    btn.classList.add('open');
    btn.textContent = 'Hide ▲';
    // Smooth scroll to the row
    setTimeout(() => respRow.scrollIntoView({ behavior: 'smooth', block: 'nearest' }), 50);
  }
}

// ── FILTER TABLE ────────────────────────────
function filterTable(status, clickedBtn) {
  // Update active button state
  document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
  if (clickedBtn) clickedBtn.classList.add('active');

  const tbody = document.querySelector('#complaintsTable tbody');
  if (!tbody) return;

  let visibleCount = 0;

  tbody.querySelectorAll('tr[data-status]').forEach(row => {
    const id      = row.dataset.id;
    const respRow = document.getElementById('resp-' + id);
    const match   = status === 'all' || row.dataset.status === status;

    row.style.display = match ? '' : 'none';

    // Collapse hidden response rows
    if (!match && respRow) {
      respRow.classList.remove('open');
      const btn = document.getElementById('btn-' + id);
      if (btn) { btn.classList.remove('open'); btn.textContent = 'View ▼'; }
      respRow.style.display = 'none';
    } else if (match && respRow) {
      respRow.style.display = '';
    }

    if (match) visibleCount++;
  });

  // Show/hide empty-filter message
  const noResults = document.getElementById('noFilterResults');
  if (noResults) noResults.style.display = visibleCount === 0 ? 'block' : 'none';
}

// ── FILE UPLOAD HANDLER ─────────────────────
function handleFileSelect(input) {
  const zone    = document.getElementById('uploadZone');
  const display = document.getElementById('fileNameDisplay');
  const icon    = document.getElementById('uploadIcon');
  const errDiv  = document.getElementById('fileError');

  errDiv.style.display = 'none';
  errDiv.textContent   = '';

  if (!input.files || !input.files[0]) {
    display.textContent = 'JPG, PNG, PDF, DOC, DOCX — max 5MB';
    zone.classList.remove('has-file');
    icon.textContent = '📎';
    return;
  }

  const file     = input.files[0];
  const maxBytes = 5 * 1024 * 1024; // 5MB
  const allowed  = ['jpg','jpeg','png','pdf','doc','docx'];
  const ext      = file.name.split('.').pop().toLowerCase();

  if (!allowed.includes(ext)) {
    errDiv.textContent   = '⚠️ Invalid file type. Allowed: JPG, PNG, PDF, DOC, DOCX';
    errDiv.style.display = 'block';
    input.value          = '';
    zone.classList.remove('has-file');
    icon.textContent = '📎';
    return;
  }

  if (file.size > maxBytes) {
    errDiv.textContent   = '⚠️ File is too large. Maximum size is 5MB.';
    errDiv.style.display = 'block';
    input.value          = '';
    zone.classList.remove('has-file');
    icon.textContent = '📎';
    return;
  }

  // All good
  icon.textContent = '✅';
  display.textContent = '📄 ' + file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
  zone.classList.add('has-file');
}

// Drag-and-drop visual feedback
(function(){
  const zone = document.getElementById('uploadZone');
  if (!zone) return;
  zone.addEventListener('dragover', e => { e.preventDefault(); zone.style.borderColor = 'var(--accent)'; });
  zone.addEventListener('dragleave', ()=> { zone.style.borderColor = ''; });
  zone.addEventListener('drop', ()=> { zone.style.borderColor = ''; });
})();

function resetComplaintForm() {
  document.getElementById('complaintForm').reset();
  document.getElementById('fileNameDisplay').textContent = 'JPG, PNG, PDF, DOC, DOCX — max 5MB';
  document.getElementById('uploadIcon').textContent = '📎';
  document.getElementById('uploadZone').classList.remove('has-file');
  document.getElementById('fileError').style.display = 'none';
  document.getElementById('titleCounter').textContent = '0 / 150';
  document.getElementById('descCounter').textContent  = '0 / 2000';
}

// ── CHARACTER COUNTERS ──────────────────────
function setupCounter(inputId, counterId, max) {
  const el = document.getElementById(inputId);
  const counter = document.getElementById(counterId);
  if (!el || !counter) return;
  function update() {
    const len = el.value.length;
    counter.textContent = len + ' / ' + max;
    counter.className = 'char-counter' + (len > max*0.9 ? (len >= max ? ' over' : ' warn') : '');
  }
  el.addEventListener('input', update);
  update();
}
setupCounter('complaintTitle', 'titleCounter', 150);
setupCounter('complaintDesc',  'descCounter',  2000);

// ── SUBMIT BUTTON LOADING STATE ─────────────
const complaintForm = document.getElementById('complaintForm');
if (complaintForm) {
  complaintForm.addEventListener('submit', function(e) {

    let hasError = false;
    ['complaintTitle','complaintCategory','complaintPriority','complaintDesc'].forEach(id => {
      const el = document.getElementById(id);
      if (!el) return;
      if (el.value === '' || el.value.trim() === '') {
        el.style.borderColor = '#eb5757';
        el.style.boxShadow   = '0 0 0 3px rgba(235,87,87,0.15)';
        hasError = true;
      } else {
        el.style.borderColor = '';
        el.style.boxShadow   = '';
      }
    });

    if (hasError) {
      e.preventDefault();
      const first = complaintForm.querySelector('[style*="eb5757"]');
      if (first) first.scrollIntoView({ behavior:'smooth', block:'center' });
      return;
    }

    // IMPORTANT: never use btn.disabled = true
    // Disabled buttons are NOT included in POST data, so
    // isset($_POST['submit_complaint']) would always be false.
    // Just change visuals, let the POST go through.
    const btn = document.getElementById('submitComplaintBtn');
    btn.style.opacity = '0.7';
    btn.style.cursor  = 'not-allowed';
    btn.innerHTML     = '<span class="btn-spinner"></span> Submitting…';
  });

  complaintForm.querySelectorAll('.form-input, .form-select, .form-textarea').forEach(el => {
    el.addEventListener('input',  function() { this.style.borderColor = ''; this.style.boxShadow = ''; });
    el.addEventListener('change', function() { this.style.borderColor = ''; this.style.boxShadow = ''; });
  });
}

// ── PASSWORD TOGGLE ─────────────────────────
function togglePwd(fieldId, btn) {
  const inp = document.getElementById(fieldId);
  if (inp.type === 'password') {
    inp.type = 'text';
    btn.textContent = '🙈';
  } else {
    inp.type = 'password';
    btn.textContent = '👁️';
  }
}

// ── PASSWORD STRENGTH ───────────────────────
const newPwdEl = document.getElementById('newPwd');
if (newPwdEl) {
  newPwdEl.addEventListener('input', function () {
    const v = this.value;
    let score = 0;
    if (v.length >= 8) score++;
    if (/[A-Z]/.test(v)) score++;
    if (/[0-9]/.test(v)) score++;
    if (/[^A-Za-z0-9]/.test(v)) score++;

    const cfg = [
      { pct:'0%',   color:'transparent', text:'Enter a new password' },
      { pct:'25%',  color:'#eb5757',     text:'Weak' },
      { pct:'50%',  color:'#f2994a',     text:'Fair' },
      { pct:'75%',  color:'#f2c94c',     text:'Good' },
      { pct:'100%', color:'#27ae60',     text:'Strong ✓' },
    ];
    const bar = document.getElementById('pwdStrengthBar');
    const lbl = document.getElementById('pwdStrengthLabel');
    bar.style.width      = cfg[score].pct;
    bar.style.background = cfg[score].color;
    lbl.textContent      = cfg[score].text;
    lbl.style.color      = score === 0 ? 'var(--muted)' : cfg[score].color;

    checkPwdMatch();
  });
}

// ── PASSWORD MATCH ──────────────────────────
const confirmPwdEl = document.getElementById('confirmNewPwd');
if (confirmPwdEl) {
  confirmPwdEl.addEventListener('input', checkPwdMatch);
}
function checkPwdMatch() {
  const hint    = document.getElementById('pwdMatchHint');
  const newVal  = document.getElementById('newPwd')?.value || '';
  const confVal = document.getElementById('confirmNewPwd')?.value || '';
  if (!hint) return;
  if (!confVal) { hint.style.display = 'none'; return; }
  hint.style.display = 'block';
  if (confVal === newVal) {
    hint.textContent = '✓ Passwords match';
    hint.style.color = '#6fcf97';
  } else {
    hint.textContent = '✗ Passwords do not match';
    hint.style.color = '#eb5757';
  }
}

// Prevent change-password submit if passwords don't match
const pwdFormEl = document.getElementById('pwdForm');

if (pwdFormEl) {

  pwdFormEl.addEventListener('submit', function(e) {

    const np = document.getElementById('np').value;
    const cp = document.getElementById('cfp').value;

    if (np !== cp) {

      e.preventDefault();

      const hint = document.getElementById('mHint');

      hint.style.display = 'block';
      hint.textContent = '✗ Passwords do not match';
      hint.style.color = '#eb5757';

      document.getElementById('cfp').focus();

      return;
    }

    const btn = document.getElementById('cpBtn');

    btn.disabled = true;
    btn.innerHTML = 'Changing...';

  });

}

// ── AUTO-DISMISS ALERT ──────────────────────
const alertBox = document.getElementById('alertBox');
if (alertBox) {
  setTimeout(() => {
    alertBox.style.transition = 'opacity 0.5s';
    alertBox.style.opacity    = '0';
    setTimeout(() => alertBox.remove(), 500);
  }, 5000);
}
</script>
</body>
</html>