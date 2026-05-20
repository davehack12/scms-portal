<?php
// Fetch real stats from database
include('includes/config.php');

$total    = 0;
$resolved = 0;
$pending  = 0;

if (isset($conn)) {
    $total    = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM complaints"))['c'] ?? 0);
    $resolved = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM complaints WHERE status='Resolved'"))['c'] ?? 0);
    $pending  = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM complaints WHERE status='Pending'"))['c'] ?? 0);
    $students = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM students"))['c'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>SCMS — Student Complaint Management System</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap" rel="stylesheet">
  <style>
    *{ max-width:100%; box-sizing:border-box; }
    html, body{ overflow-x:hidden; width:100%; }
    :root {
      --bg: #050b18; --surface: #0b1628; --surface2: #111f3a;
      --accent: #2f80ed; --accent2: #56ccf2; --gold: #f2c94c;
      --text: #e8eef8; --muted: #7a90b3;
      --border: rgba(47,128,237,0.18); --glow: rgba(47,128,237,0.35);
    }
    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
    html { scroll-behavior: smooth; }
    body { background:var(--bg); color:var(--text); font-family:'DM Sans',sans-serif; overflow-x:hidden; cursor:none; }

    .cursor { width:12px; height:12px; background:var(--accent); border-radius:50%; position:fixed; top:0; left:0; pointer-events:none; z-index:9999; transition:transform 0.1s ease; mix-blend-mode:screen; }
    .cursor-ring { width:36px; height:36px; border:1.5px solid var(--accent2); border-radius:50%; position:fixed; top:0; left:0; pointer-events:none; z-index:9998; transition:transform 0.18s ease, width 0.3s, height 0.3s, opacity 0.3s; opacity:0.6; }
    #particles { position:fixed; inset:0; z-index:0; pointer-events:none; }

    nav { position:fixed; top:0; left:0; right:0; z-index:100; display:flex; align-items:center; justify-content:space-between; padding:20px 6%; backdrop-filter:blur(16px); background:rgba(5,11,24,0.7); border-bottom:1px solid var(--border); transition:background 0.4s; }
    .nav-brand { display:flex; align-items:center; gap:12px; font-family:'Syne',sans-serif; font-weight:800; font-size:22px; color:var(--text); text-decoration:none; letter-spacing:-0.5px; }
    .brand-badge { width:38px; height:38px; background:linear-gradient(135deg,var(--accent),var(--accent2)); border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:18px; font-weight:800; color:white; box-shadow:0 0 20px var(--glow); }
    .nav-links { display:flex; align-items:center; gap:32px; list-style:none; }
    .nav-links a { color:var(--muted); font-size:14px; font-weight:500; text-decoration:none; letter-spacing:0.5px; transition:color 0.3s; position:relative; }
    .nav-links a::after { content:''; position:absolute; bottom:-4px; left:0; width:0; height:1.5px; background:linear-gradient(90deg,var(--accent),var(--accent2)); transition:width 0.3s ease; }
    .nav-links a:hover { color:var(--text); }
    .nav-links a:hover::after { width:100%; }
    .nav-cta { display:flex; gap:12px; align-items:center; }
    .btn-ghost { padding:9px 22px; border-radius:8px; border:1px solid var(--border); color:var(--text); font-size:14px; font-weight:500; text-decoration:none; background:transparent; transition:all 0.3s; }
    .btn-ghost:hover { background:var(--border); border-color:var(--accent); }
    .btn-primary { padding:9px 22px; border-radius:8px; background:linear-gradient(135deg,var(--accent),var(--accent2)); color:white; font-size:14px; font-weight:600; text-decoration:none; box-shadow:0 4px 20px var(--glow); transition:all 0.3s; }
    .btn-primary:hover { transform:translateY(-2px); box-shadow:0 8px 32px var(--glow); }
    .hamburger { display:none; flex-direction:column; gap:5px; cursor:pointer; }
    .hamburger span { width:24px; height:2px; background:var(--text); border-radius:2px; transition:0.3s; }

    .hero { min-height:100vh; display:flex; align-items:center; padding:120px 6% 80px; position:relative; overflow:hidden; z-index:1; }
    .hero-grid { display:grid; grid-template-columns:1fr 1fr; gap:60px; align-items:center; width:100%; max-width:1300px; margin:0 auto; }
    .hero-eyebrow { display:inline-flex; align-items:center; gap:8px; background:rgba(47,128,237,0.12); border:1px solid rgba(47,128,237,0.3); padding:6px 16px; border-radius:100px; font-size:12px; font-weight:500; letter-spacing:1.5px; color:var(--accent2); text-transform:uppercase; margin-bottom:24px; opacity:0; animation:fadeUp 0.8s ease 0.2s forwards; }
    .eyebrow-dot { width:6px; height:6px; border-radius:50%; background:var(--accent2); animation:pulse 2s ease infinite; }
    @keyframes pulse { 0%,100%{opacity:1;transform:scale(1);} 50%{opacity:0.4;transform:scale(1.4);} }
    .hero h1 { font-family:'Syne',sans-serif; font-size:clamp(42px,5vw,72px); font-weight:800; line-height:1.05; letter-spacing:-2px; margin-bottom:24px; opacity:0; animation:fadeUp 0.8s ease 0.4s forwards; }
    .hero h1 .gradient-text { background:linear-gradient(90deg,var(--accent),var(--accent2),var(--gold)); background-size:200%; -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; animation:shimmer 4s linear infinite; }
    @keyframes shimmer { 0%{background-position:0% 50%;} 100%{background-position:200% 50%;} }
    .hero p { font-size:17px; line-height:1.8; color:var(--muted); max-width:520px; margin-bottom:40px; opacity:0; animation:fadeUp 0.8s ease 0.6s forwards; }
    .hero-buttons { display:flex; gap:16px; flex-wrap:wrap; opacity:0; animation:fadeUp 0.8s ease 0.8s forwards; }
    .btn-hero-primary { display:inline-flex; align-items:center; gap:10px; padding:15px 32px; border-radius:12px; background:linear-gradient(135deg,var(--accent),#1e5fc7); color:white; font-size:16px; font-weight:600; text-decoration:none; box-shadow:0 8px 32px var(--glow),inset 0 1px 0 rgba(255,255,255,0.15); transition:all 0.3s; position:relative; overflow:hidden; }
    .btn-hero-primary::before { content:''; position:absolute; inset:0; background:linear-gradient(135deg,transparent 0%,rgba(255,255,255,0.1) 50%,transparent 100%); transform:translateX(-100%); transition:transform 0.5s ease; }
    .btn-hero-primary:hover::before { transform:translateX(100%); }
    .btn-hero-primary:hover { transform:translateY(-3px); box-shadow:0 16px 48px var(--glow); }
    .btn-hero-secondary { display:inline-flex; align-items:center; gap:10px; padding:15px 32px; border-radius:12px; border:1px solid var(--border); color:var(--text); font-size:16px; font-weight:500; text-decoration:none; background:rgba(255,255,255,0.03); backdrop-filter:blur(8px); transition:all 0.3s; }
    .btn-hero-secondary:hover { background:rgba(47,128,237,0.1); border-color:var(--accent); transform:translateY(-3px); }
    .btn-icon { font-size:18px; }

    .hero-visual { opacity:0; animation:fadeLeft 0.8s ease 0.5s forwards; position:relative; }
    @keyframes fadeLeft { from{opacity:0;transform:translateX(40px);} to{opacity:1;transform:translateX(0);} }
    .dashboard-card { background:var(--surface); border:1px solid var(--border); border-radius:20px; padding:28px; position:relative; box-shadow:0 40px 100px rgba(0,0,0,0.5),0 0 0 1px rgba(255,255,255,0.04); overflow:hidden; }
    .dashboard-card::before { content:''; position:absolute; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg,transparent,var(--accent),var(--accent2),transparent); }
    .card-header-row { display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; }
    .card-title { font-family:'Syne',sans-serif; font-weight:700; font-size:16px; }

    /* LIVE indicator */
    .card-badge { font-size:11px; font-weight:600; letter-spacing:1px; text-transform:uppercase; padding:4px 10px; border-radius:100px; background:rgba(39,174,96,0.15); color:#6fcf97; border:1px solid rgba(39,174,96,0.3); display:flex; align-items:center; gap:5px; }
    .live-dot { width:6px; height:6px; border-radius:50%; background:#6fcf97; animation:pulse 1.5s ease infinite; }

    .stat-grid { display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; margin-bottom:24px; }
    .stat-box { background:var(--surface2); border:1px solid var(--border); border-radius:12px; padding:16px; text-align:center; transition:transform 0.3s,box-shadow 0.3s; }
    .stat-box:hover { transform:translateY(-3px); box-shadow:0 8px 32px rgba(47,128,237,0.15); }
    .stat-value { font-family:'Syne',sans-serif; font-size:28px; font-weight:800; background:linear-gradient(135deg,var(--accent),var(--accent2)); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; }
    .stat-label { font-size:11px; color:var(--muted); margin-top:4px; }
    .complaint-list { display:flex; flex-direction:column; gap:10px; }
    .complaint-item { display:flex; align-items:center; gap:14px; padding:14px 16px; background:var(--surface2); border:1px solid var(--border); border-radius:12px; transition:all 0.3s; }
    .complaint-item:hover { border-color:var(--accent); background:rgba(47,128,237,0.06); transform:translateX(4px); }
    .complaint-icon { width:38px; height:38px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:16px; flex-shrink:0; }
    .ci-yellow{background:rgba(242,201,76,0.15);color:var(--gold);} .ci-green{background:rgba(39,174,96,0.15);color:#6fcf97;} .ci-red{background:rgba(235,87,87,0.15);color:#eb5757;} .ci-blue{background:rgba(47,128,237,0.15);color:var(--accent2);}
    .complaint-info { flex:1; }
    .complaint-title-text { font-size:13px; font-weight:500; margin-bottom:3px; }
    .complaint-sub { font-size:11px; color:var(--muted); }
    .status-pill { font-size:10px; font-weight:600; letter-spacing:0.5px; text-transform:uppercase; padding:3px 10px; border-radius:100px; }
    .s-pending{background:rgba(242,201,76,0.15);color:var(--gold);} .s-resolved{background:rgba(39,174,96,0.15);color:#6fcf97;} .s-urgent{background:rgba(235,87,87,0.15);color:#eb5757;} .s-review{background:rgba(47,128,237,0.15);color:var(--accent2);}

    /* last updated tag */
    .last-updated { font-size:10px; color:var(--muted); text-align:right; margin-top:12px; }

    .orb { position:absolute; border-radius:50%; filter:blur(60px); pointer-events:none; z-index:-1; }
    .orb1 { width:300px; height:300px; background:rgba(47,128,237,0.12); top:-80px; right:-80px; }
    .orb2 { width:200px; height:200px; background:rgba(86,204,242,0.08); bottom:-60px; left:-60px; }

    .stats-bar { padding:60px 6%; position:relative; z-index:1; }
    .stats-inner { max-width:1300px; margin:0 auto; display:grid; grid-template-columns:repeat(4,1fr); gap:24px; }
    .stat-card { background:var(--surface); border:1px solid var(--border); border-radius:16px; padding:28px 24px; display:flex; align-items:center; gap:18px; transition:all 0.3s; position:relative; overflow:hidden; }
    .stat-card::after { content:''; position:absolute; bottom:0; left:0; height:2px; width:0; background:linear-gradient(90deg,var(--accent),var(--accent2)); transition:width 0.4s ease; }
    .stat-card:hover::after { width:100%; }
    .stat-card:hover { transform:translateY(-4px); box-shadow:0 16px 48px rgba(0,0,0,0.3); }
    .stat-card-icon { width:52px; height:52px; border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:22px; background:rgba(47,128,237,0.1); border:1px solid rgba(47,128,237,0.2); }
    .stat-card-info { flex:1; }
    .stat-card-num { font-family:'Syne',sans-serif; font-size:34px; font-weight:800; line-height:1; background:linear-gradient(135deg,var(--text),var(--muted)); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; }
    .stat-card-label { font-size:13px; color:var(--muted); margin-top:4px; }

    .features { padding:80px 6% 100px; position:relative; z-index:1; }
    .section-header { text-align:center; max-width:640px; margin:0 auto 64px; }
    .section-tag { display:inline-flex; align-items:center; gap:8px; background:rgba(47,128,237,0.1); border:1px solid rgba(47,128,237,0.25); padding:5px 14px; border-radius:100px; font-size:11px; font-weight:600; letter-spacing:2px; color:var(--accent2); text-transform:uppercase; margin-bottom:20px; }
    .section-title { font-family:'Syne',sans-serif; font-size:clamp(30px,3.5vw,50px); font-weight:800; letter-spacing:-1.5px; line-height:1.1; margin-bottom:16px; }
    .section-desc { font-size:16px; color:var(--muted); line-height:1.7; }
    .features-grid { max-width:1300px; margin:0 auto; display:grid; grid-template-columns:repeat(3,1fr); gap:24px; }
    .feature-card { background:var(--surface); border:1px solid var(--border); border-radius:20px; padding:36px 32px; position:relative; overflow:hidden; transition:all 0.4s; }
    .feature-card::before { content:''; position:absolute; inset:0; background:linear-gradient(135deg,rgba(47,128,237,0.05) 0%,transparent 60%); opacity:0; transition:opacity 0.4s; }
    .feature-card:hover::before { opacity:1; }
    .feature-card:hover { transform:translateY(-6px); border-color:rgba(47,128,237,0.4); box-shadow:0 24px 60px rgba(0,0,0,0.3); }
    .feature-number { position:absolute; top:20px; right:24px; font-family:'Syne',sans-serif; font-size:64px; font-weight:800; color:rgba(255,255,255,0.03); letter-spacing:-3px; }
    .feature-icon-wrap { width:60px; height:60px; border-radius:16px; display:flex; align-items:center; justify-content:center; font-size:26px; margin-bottom:24px; }
    .fi-blue{background:rgba(47,128,237,0.12);border:1px solid rgba(47,128,237,0.25);} .fi-teal{background:rgba(86,204,242,0.12);border:1px solid rgba(86,204,242,0.25);} .fi-gold{background:rgba(242,201,76,0.12);border:1px solid rgba(242,201,76,0.25);} .fi-green{background:rgba(39,174,96,0.12);border:1px solid rgba(39,174,96,0.25);} .fi-purple{background:rgba(155,81,224,0.12);border:1px solid rgba(155,81,224,0.25);} .fi-red{background:rgba(235,87,87,0.12);border:1px solid rgba(235,87,87,0.25);}
    .feature-title { font-family:'Syne',sans-serif; font-size:20px; font-weight:700; margin-bottom:12px; }
    .feature-desc { font-size:14px; color:var(--muted); line-height:1.7; }

    .how-it-works { padding:80px 6% 100px; position:relative; z-index:1; }
    .steps-grid { max-width:1300px; margin:0 auto; display:grid; grid-template-columns:repeat(4,1fr); gap:24px; position:relative; }
    .steps-grid::before { content:''; position:absolute; top:46px; left:calc(12.5% + 24px); right:calc(12.5% + 24px); height:1px; background:linear-gradient(90deg,var(--accent),var(--accent2),var(--gold),var(--accent)); z-index:0; }
    .step-card { text-align:center; padding:32px 20px; position:relative; z-index:1; }
    .step-num-wrap { width:64px; height:64px; border-radius:50%; background:var(--surface); border:2px solid var(--border); display:flex; align-items:center; justify-content:center; margin:0 auto 24px; transition:all 0.3s; }
    .step-card:hover .step-num-wrap { border-color:var(--accent); box-shadow:0 0 32px var(--glow); background:rgba(47,128,237,0.1); }
    .step-num { font-family:'Syne',sans-serif; font-size:22px; font-weight:800; background:linear-gradient(135deg,var(--accent),var(--accent2)); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; }
    .step-title { font-family:'Syne',sans-serif; font-size:18px; font-weight:700; margin-bottom:10px; }
    .step-desc { font-size:13px; color:var(--muted); line-height:1.7; }

    .welcome-section { padding:60px 6% 100px; position:relative; z-index:1; }
    .welcome-inner { max-width:1300px; margin:0 auto; background:var(--surface); border:1px solid var(--border); border-radius:28px; padding:64px; display:grid; grid-template-columns:1fr 1fr; gap:60px; align-items:center; position:relative; overflow:hidden; }
    .welcome-inner::before { content:''; position:absolute; inset:0; border-radius:28px; background:linear-gradient(135deg,rgba(47,128,237,0.08) 0%,transparent 60%); pointer-events:none; }
    .welcome-inner::after { content:''; position:absolute; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg,transparent,var(--accent),var(--accent2),transparent); }
    .welcome-label { display:inline-flex; align-items:center; gap:8px; background:rgba(242,201,76,0.1); border:1px solid rgba(242,201,76,0.25); padding:5px 14px; border-radius:100px; font-size:11px; font-weight:600; letter-spacing:2px; color:var(--gold); text-transform:uppercase; margin-bottom:20px; }
    .welcome-title { font-family:'Syne',sans-serif; font-size:clamp(28px,3vw,42px); font-weight:800; letter-spacing:-1.5px; margin-bottom:20px; line-height:1.15; }
    .welcome-text { font-size:15px; color:var(--muted); line-height:1.8; margin-bottom:32px; }
    .welcome-cta { display:inline-flex; align-items:center; gap:10px; padding:14px 30px; border-radius:12px; background:linear-gradient(135deg,var(--gold),#d4a017); color:#050b18; font-size:15px; font-weight:700; text-decoration:none; box-shadow:0 8px 32px rgba(242,201,76,0.25); transition:all 0.3s; }
    .welcome-cta:hover { transform:translateY(-3px); box-shadow:0 16px 48px rgba(242,201,76,0.4); }
    .welcome-visual { display:flex; flex-direction:column; gap:16px; }
    .info-card { background:var(--surface2); border:1px solid var(--border); border-radius:14px; padding:20px 24px; display:flex; align-items:center; gap:16px; transition:all 0.3s; }
    .info-card:hover { border-color:var(--accent); transform:translateX(6px); }
    .info-card-icon { width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:20px; flex-shrink:0; }
    .info-card-text strong { display:block; font-size:14px; font-weight:600; margin-bottom:3px; }
    .info-card-text span { font-size:12px; color:var(--muted); }

    footer { background:var(--surface); border-top:1px solid var(--border); padding:40px 6%; position:relative; z-index:1; }
    .footer-inner { max-width:1300px; margin:0 auto; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:20px; }
    .footer-brand { font-family:'Syne',sans-serif; font-size:16px; font-weight:700; display:flex; align-items:center; gap:10px; }
    .footer-links { display:flex; gap:24px; }
    .footer-links a { color:var(--muted); font-size:13px; text-decoration:none; transition:color 0.3s; }
    .footer-links a:hover { color:var(--text); }
    .footer-copy { font-size:12px; color:var(--muted); }

    @keyframes fadeUp { from{opacity:0;transform:translateY(30px);} to{opacity:1;transform:translateY(0);} }
    .reveal { opacity:0; transform:translateY(32px); transition:opacity 0.7s ease,transform 0.7s ease; }
    .reveal.visible { opacity:1; transform:translateY(0); }

    @media (max-width:1024px) {
      .hero-grid{grid-template-columns:1fr;} .hero-visual{display:none;}
      .stats-inner{grid-template-columns:1fr 1fr;} .features-grid{grid-template-columns:1fr 1fr;}
      .steps-grid{grid-template-columns:1fr 1fr;} .steps-grid::before{display:none;}
      .welcome-inner{grid-template-columns:1fr;padding:40px 32px;}
    }
    @media (max-width:640px) {
      nav{padding:16px 5%;} .nav-links,.nav-cta{display:none;} .hamburger{display:flex;}
      .stats-inner{grid-template-columns:1fr 1fr;} .features-grid{grid-template-columns:1fr;}
      .steps-grid{grid-template-columns:1fr 1fr;} .footer-inner{flex-direction:column;text-align:center;}
    }
    .mobile-nav { display:none; position:fixed; inset:0; z-index:200; background:rgba(5,11,24,0.97); backdrop-filter:blur(20px); flex-direction:column; align-items:center; justify-content:center; gap:32px; }
    .mobile-nav.open { display:flex; }
    .mobile-nav a { font-family:'Syne',sans-serif; font-size:32px; font-weight:700; color:var(--text); text-decoration:none; transition:color 0.3s; }
    .mobile-nav a:hover { color:var(--accent2); }
    .mobile-close { position:absolute; top:24px; right:6%; background:transparent; border:none; color:var(--text); font-size:28px; cursor:pointer; }
    .glow-sep { width:100%; height:1px; background:linear-gradient(90deg,transparent,var(--border),transparent); position:relative; z-index:1; }
  </style>
</head>
<body>

<div class="cursor" id="cursor"></div>
<div class="cursor-ring" id="cursorRing"></div>
<canvas id="particles"></canvas>

<div class="mobile-nav" id="mobileNav">
  <button class="mobile-close" onclick="closeMobileNav()">✕</button>
  <a href="#" onclick="closeMobileNav()">Home</a>
  <a href="#features" onclick="closeMobileNav()">Features</a>
  <a href="#how" onclick="closeMobileNav()">How It Works</a>
  <a href="#about" onclick="closeMobileNav()">About</a>
  <a href="login.php" class="btn-primary" style="font-size:18px;">Login →</a>
</div>

<nav id="navbar">
  <a class="nav-brand" href="#">
    <div class="brand-badge">S</div>
    SCMS
  </a>
  <ul class="nav-links">
    <li><a href="#">Home</a></li>
    <li><a href="#features">Features</a></li>
    <li><a href="#how">How It Works</a></li>
    <li><a href="#about">About</a></li>
  </ul>
  <div class="nav-cta">
    <a href="login.php" class="btn-ghost">Login</a>
    <a href="register.php" class="btn-primary">Get Started →</a>
  </div>
  <div class="hamburger" onclick="openMobileNav()">
    <span></span><span></span><span></span>
  </div>
</nav>

<section class="hero">
  <div class="hero-grid">
    <div class="hero-content">
      <div class="hero-eyebrow">
        <span class="eyebrow-dot"></span>
        Final Year Project · 2026
      </div>
      <h1>
        Complaints<br>
        <span class="gradient-text">Managed.</span><br>
        Issues <span class="gradient-text">Resolved.</span>
      </h1>
      <p>A next-generation platform built for schools — empowering students to voice concerns and enabling administrators to act swiftly, transparently, and efficiently.</p>
      <div class="hero-buttons">
        <a href="login.php" class="btn-hero-primary"><span class="btn-icon">🚀</span>Launch Portal</a>
        <a href="register.php" class="btn-hero-secondary"><span class="btn-icon">📋</span>Create Account</a>
      </div>
    </div>

    <div class="hero-visual">
      <div class="orb orb1"></div>
      <div class="orb orb2"></div>
      <div class="dashboard-card">
        <div class="card-header-row">
          <div class="card-title">📊 Live Dashboard</div>
          <div class="card-badge"><span class="live-dot"></span> Live Data</div>
        </div>

        <!-- ── REAL DATABASE STATS ── -->
        <div class="stat-grid">
          <div class="stat-box">
            <div class="stat-value" id="count1">0</div>
            <div class="stat-label">Total Submitted</div>
          </div>
          <div class="stat-box">
            <div class="stat-value" id="count2">0</div>
            <div class="stat-label">Resolved</div>
          </div>
          <div class="stat-box">
            <div class="stat-value" id="count3">0</div>
            <div class="stat-label">Pending</div>
          </div>
        </div>

        <!-- ── RECENT COMPLAINTS FROM DB ── -->
        <div class="complaint-list" id="recentList">
          <?php
          if (isset($conn)) {
            $recent = mysqli_query($conn,
              "SELECT c.title, c.status, c.category, s.full_name
               FROM complaints c
               LEFT JOIN students s ON c.student_id = s.id
               ORDER BY c.created_at DESC LIMIT 4"
            );
            $icons = ['Pending'=>['icon'=>'⚠️','class'=>'ci-yellow','pill'=>'s-pending'],
                      'In Review'=>['icon'=>'🔍','class'=>'ci-blue','pill'=>'s-review'],
                      'Resolved'=>['icon'=>'✅','class'=>'ci-green','pill'=>'s-resolved'],
                      'Rejected'=>['icon'=>'❌','class'=>'ci-red','pill'=>'s-urgent']];
            $count = 0;
            while ($r = mysqli_fetch_assoc($recent)) {
              $s = $r['status'] ?? 'Pending';
              $ic = $icons[$s] ?? $icons['Pending'];
              $title = htmlspecialchars(mb_strlen($r['title'])>28 ? mb_substr($r['title'],0,28).'…' : $r['title']);
              $name  = htmlspecialchars($r['full_name'] ?? 'Student');
              echo "
              <div class='complaint-item'>
                <div class='complaint-icon {$ic['class']}'>{$ic['icon']}</div>
                <div class='complaint-info'>
                  <div class='complaint-title-text'>$title</div>
                  <div class='complaint-sub'>$name · {$r['category']}</div>
                </div>
                <div class='status-pill {$ic['pill']}'>$s</div>
              </div>";
              $count++;
            }
            if ($count === 0) {
              echo "<div style='text-align:center;padding:20px;font-size:13px;color:var(--muted);'>No complaints submitted yet</div>";
            }
          }
          ?>
        </div>

        <div class="last-updated">🕐 Last updated: <?= date('M d, Y h:i A') ?></div>
      </div>
    </div>
  </div>
</section>

<div class="glow-sep"></div>

<section class="stats-bar">
  <div class="stats-inner">
    <div class="stat-card reveal">
      <div class="stat-card-icon">🎓</div>
      <div class="stat-card-info">
        <div class="stat-card-num" id="statStudents">0</div>
        <div class="stat-card-label">Students Registered</div>
      </div>
    </div>
    <div class="stat-card reveal" style="transition-delay:0.1s">
      <div class="stat-card-icon">📝</div>
      <div class="stat-card-info">
        <div class="stat-card-num" id="statTotal">0</div>
        <div class="stat-card-label">Complaints Filed</div>
      </div>
    </div>
    <div class="stat-card reveal" style="transition-delay:0.2s">
      <div class="stat-card-icon">✅</div>
      <div class="stat-card-info">
        <div class="stat-card-num" id="statResolved">0</div>
        <div class="stat-card-label">Resolved</div>
      </div>
    </div>
    <div class="stat-card reveal" style="transition-delay:0.3s">
      <div class="stat-card-icon">⏳</div>
      <div class="stat-card-info">
        <div class="stat-card-num" id="statPending">0</div>
        <div class="stat-card-label">Pending</div>
      </div>
    </div>
  </div>
</section>

<div class="glow-sep"></div>

<section class="features" id="features">
  <div class="section-header reveal">
    <div class="section-tag">✦ Platform Features</div>
    <h2 class="section-title">Everything You Need,<br>Nothing You Don't</h2>
    <p class="section-desc">Built from the ground up for school environments — modern, fast, and purpose-driven.</p>
  </div>
  <div class="features-grid">
    <div class="feature-card reveal"><div class="feature-number">01</div><div class="feature-icon-wrap fi-blue">📤</div><h3 class="feature-title">Easy Submission</h3><p class="feature-desc">Students submit complaints in under 60 seconds with an intuitive, guided form — no confusion, no back-and-forth.</p></div>
    <div class="feature-card reveal" style="transition-delay:0.1s"><div class="feature-number">02</div><div class="feature-icon-wrap fi-teal">📡</div><h3 class="feature-title">Real-Time Tracking</h3><p class="feature-desc">Students see live status updates at every stage — submitted, under review, escalated, or resolved.</p></div>
    <div class="feature-card reveal" style="transition-delay:0.2s"><div class="feature-number">03</div><div class="feature-icon-wrap fi-gold">🛡️</div><h3 class="feature-title">Anonymous Mode</h3><p class="feature-desc">Sensitive complaints can be filed anonymously, encouraging honest feedback without fear of retaliation.</p></div>
    <div class="feature-card reveal" style="transition-delay:0.3s"><div class="feature-number">04</div><div class="feature-icon-wrap fi-green">📊</div><h3 class="feature-title">Admin Analytics</h3><p class="feature-desc">Visual dashboards give administrators actionable insights — track trends, identify recurring issues, measure performance.</p></div>
    <div class="feature-card reveal" style="transition-delay:0.4s"><div class="feature-number">05</div><div class="feature-icon-wrap fi-purple">🔔</div><h3 class="feature-title">Instant Notifications</h3><p class="feature-desc">Email and in-app alerts keep both students and administrators informed the moment anything changes.</p></div>
    <div class="feature-card reveal" style="transition-delay:0.5s"><div class="feature-number">06</div><div class="feature-icon-wrap fi-red">🔒</div><h3 class="feature-title">Secure & Private</h3><p class="feature-desc">Role-based access control and encrypted storage ensure data is only visible to the right people, always.</p></div>
  </div>
</section>

<div class="glow-sep"></div>

<section class="how-it-works" id="how">
  <div class="section-header reveal">
    <div class="section-tag">✦ Process</div>
    <h2 class="section-title">Four Steps to<br>Resolution</h2>
    <p class="section-desc">A transparent, structured workflow that ensures no complaint falls through the cracks.</p>
  </div>
  <div class="steps-grid">
    <div class="step-card reveal"><div class="step-num-wrap"><div class="step-num">01</div></div><h4 class="step-title">Register & Login</h4><p class="step-desc">Create your secure account in minutes and access your personalized student portal instantly.</p></div>
    <div class="step-card reveal" style="transition-delay:0.15s"><div class="step-num-wrap"><div class="step-num">02</div></div><h4 class="step-title">Submit Complaint</h4><p class="step-desc">Describe your issue, select the category, attach evidence if needed, and hit submit.</p></div>
    <div class="step-card reveal" style="transition-delay:0.3s"><div class="step-num-wrap"><div class="step-num">03</div></div><h4 class="step-title">Admin Reviews</h4><p class="step-desc">The right department receives your complaint and begins investigation immediately.</p></div>
    <div class="step-card reveal" style="transition-delay:0.45s"><div class="step-num-wrap"><div class="step-num">04</div></div><h4 class="step-title">Issue Resolved</h4><p class="step-desc">You're notified once resolved. Rate the outcome and help improve the school for everyone.</p></div>
  </div>
</section>

<div class="glow-sep"></div>

<section class="welcome-section" id="about">
  <div class="welcome-inner reveal">
    <div>
      <div class="welcome-label">✦ Welcome Message</div>
      <h2 class="welcome-title">Your Voice Deserves to Be Heard</h2>
      <p class="welcome-text">The Student Complaint Management System exists for one reason: to make schools better. Whether it's a broken facility, an unfair policy, or a classroom concern — your feedback drives real change.</p>
      <p class="welcome-text">Built as a Final Year Project with industry-standard practices, SCMS demonstrates how technology can transform communication between students and administration.</p>
      <a href="register.php" class="welcome-cta">🎓 Join the Platform</a>
    </div>
    <div class="welcome-visual">
      <div class="info-card"><div class="info-card-icon fi-blue" style="display:flex;align-items:center;justify-content:center;font-size:22px;">🏫</div><div class="info-card-text"><strong>School-First Design</strong><span>Purpose-built for educational institutions and their unique workflows.</span></div></div>
      <div class="info-card"><div class="info-card-icon fi-gold" style="display:flex;align-items:center;justify-content:center;font-size:22px;">⚡</div><div class="info-card-text"><strong>Lightning Fast Resolution</strong><span>Automated routing gets complaints to the right person on day one.</span></div></div>
      <div class="info-card"><div class="info-card-icon fi-green" style="display:flex;align-items:center;justify-content:center;font-size:22px;">🤝</div><div class="info-card-text"><strong>Two-Way Communication</strong><span>Administrators can respond directly, request more info, or escalate.</span></div></div>
      <div class="info-card"><div class="info-card-icon fi-purple" style="display:flex;align-items:center;justify-content:center;font-size:22px;">📱</div><div class="info-card-text"><strong>Fully Responsive</strong><span>Works perfectly on any device — phone, tablet, or desktop.</span></div></div>
    </div>
  </div>
</section>

<footer>
  <div class="footer-inner">
    <div class="footer-brand"><div class="brand-badge" style="width:32px;height:32px;font-size:14px;">S</div>SCMS</div>
    <div class="footer-links"><a href="#">Home</a><a href="#features">Features</a><a href="#how">Process</a><a href="#about">About</a></div>
    <div class="footer-copy">© 2026 Student Complaint Management System · Final Year Project</div>
  </div>
</footer>

<script>
// Real DB values passed from PHP
const DB_TOTAL    = <?= $total ?>;
const DB_RESOLVED = <?= $resolved ?>;
const DB_PENDING  = <?= $pending ?>;
const DB_STUDENTS = <?= $students ?? 0 ?>;

// Cursor
const cursor = document.getElementById('cursor');
const ring   = document.getElementById('cursorRing');
let mx=0,my=0,rx=0,ry=0;
document.addEventListener('mousemove', e => { mx=e.clientX; my=e.clientY; cursor.style.transform=`translate(${mx-6}px,${my-6}px)`; });
function animateRing() { rx+=(mx-rx)*0.12; ry+=(my-ry)*0.12; ring.style.transform=`translate(${rx-18}px,${ry-18}px)`; requestAnimationFrame(animateRing); }
animateRing();
document.querySelectorAll('a,button').forEach(el => {
  el.addEventListener('mouseenter',()=>{ring.style.width='60px';ring.style.height='60px';ring.style.opacity='0.3';});
  el.addEventListener('mouseleave',()=>{ring.style.width='36px';ring.style.height='36px';ring.style.opacity='0.6';});
});

// Particles
const canvas=document.getElementById('particles');
const ctx=canvas.getContext('2d');
canvas.width=innerWidth; canvas.height=innerHeight;
window.addEventListener('resize',()=>{canvas.width=innerWidth;canvas.height=innerHeight;});
const DOTS=Array.from({length:90},()=>({x:Math.random()*innerWidth,y:Math.random()*innerHeight,vx:(Math.random()-0.5)*0.3,vy:(Math.random()-0.5)*0.3,r:Math.random()*1.5+0.3,o:Math.random()*0.5+0.1}));
function drawParticles(){ctx.clearRect(0,0,canvas.width,canvas.height);DOTS.forEach((d,i)=>{d.x+=d.vx;d.y+=d.vy;if(d.x<0)d.x=canvas.width;if(d.x>canvas.width)d.x=0;if(d.y<0)d.y=canvas.height;if(d.y>canvas.height)d.y=0;ctx.beginPath();ctx.arc(d.x,d.y,d.r,0,Math.PI*2);ctx.fillStyle=`rgba(47,128,237,${d.o})`;ctx.fill();DOTS.slice(i+1).forEach(d2=>{const dist=Math.hypot(d.x-d2.x,d.y-d2.y);if(dist<130){ctx.beginPath();ctx.moveTo(d.x,d.y);ctx.lineTo(d2.x,d2.y);ctx.strokeStyle=`rgba(47,128,237,${0.06*(1-dist/130)})`;ctx.lineWidth=0.5;ctx.stroke();}});});requestAnimationFrame(drawParticles);}
drawParticles();

// Count up animation using REAL DB values
function countUp(el, target, duration) {
  if (!el) return;
  let start=0, startTime=null;
  function step(ts) {
    if(!startTime) startTime=ts;
    const p=Math.min((ts-startTime)/duration,1);
    el.textContent=Math.floor(p*target);
    if(p<1) requestAnimationFrame(step);
    else el.textContent=target;
  }
  requestAnimationFrame(step);
}

// Hero card counters — REAL data
countUp(document.getElementById('count1'), DB_TOTAL,    1800);
countUp(document.getElementById('count2'), DB_RESOLVED, 2000);
countUp(document.getElementById('count3'), DB_PENDING,  1500);

// Stats bar counters — REAL data
countUp(document.getElementById('statStudents'), DB_STUDENTS, 1600);
countUp(document.getElementById('statTotal'),    DB_TOTAL,    1800);
countUp(document.getElementById('statResolved'), DB_RESOLVED, 2000);
countUp(document.getElementById('statPending'),  DB_PENDING,  1500);

// Scroll reveal
const reveals=document.querySelectorAll('.reveal');
const io=new IntersectionObserver(entries=>{entries.forEach(e=>{if(e.isIntersecting){e.target.classList.add('visible');io.unobserve(e.target);}});},{threshold:0.12});
reveals.forEach(r=>io.observe(r));

// Mobile nav
function openMobileNav()  { document.getElementById('mobileNav').classList.add('open'); }
function closeMobileNav() { document.getElementById('mobileNav').classList.remove('open'); }

// Navbar scroll
window.addEventListener('scroll',()=>{ document.getElementById('navbar').style.background=scrollY>40?'rgba(5,11,24,0.95)':'rgba(5,11,24,0.7)'; });
</script>
</body>
</html>