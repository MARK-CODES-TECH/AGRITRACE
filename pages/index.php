<?php
require_once dirname(__DIR__) . '/init.php';
require_once dirname(__DIR__) . '/helpers.php';
include __DIR__.'/../includes/pwa-head.php';
$isTagalog = (current_lang() === 'tl');
?>
<!DOCTYPE html>
<html lang="<?= $isTagalog ? 'tl' : 'en' ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>AgriTrace+ | <?= $isTagalog ? 'Digital na Sistema sa Rehistrasyon at Pag-uulat ng mga Hayop' : 'Digital Livestock Registration & Reporting System' ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    :root {
      --primary: #10b981;
      --primary-dark: #059669;
      --bg-dark: #041a14;
      --glass: rgba(255, 255, 255, 0.05);
      --glass-border: rgba(255, 255, 255, 0.12);
      --text-main: #ffffff;
      --text-muted: rgba(255, 255, 255, 0.7);
    }

    * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'DM Sans', sans-serif; -webkit-tap-highlight-color: transparent; }

    html { overflow-x: hidden; width: 100%; -webkit-text-size-adjust: 100%; text-size-adjust: 100%; scroll-behavior: smooth; }
    body { background-color: var(--bg-dark); color: var(--text-main); overflow-x: hidden; width: 100%; max-width: 100vw; min-height: 100vh; display: flex; flex-direction: column; }
    h1, h2, h3, h4, h5, h6, p, span, a, label, strong, em { overflow-wrap: break-word; word-wrap: break-word; }

    .hero-bg {
      position: fixed; top: 0; left: 0; width: 100%; height: 100%;
      background: linear-gradient(rgba(4, 26, 20, 0.8), rgba(4, 26, 20, 0.9)), 
                  url('https://images.unsplash.com/photo-1500382017468-9049fed747ef?auto=format&fit=crop&q=80&w=2000');
      background-size: cover; background-position: center; z-index: -1;
    }

    /* HEADER LEVELING FIXES */
    .navbar { 
      display: flex; 
      justify-content: space-between; 
      align-items: center; 
      padding: 0 8%; 
      height: 90px; /* Consistent height for centering */
      z-index: 1000; 
      position: relative; 
    }

    .navbar-logo { 
      font-family: 'Syne', sans-serif; 
      font-size: 1.5rem; 
      font-weight: 800; 
      display: flex; 
      align-items: center; 
      gap: 10px; 
      text-decoration: none;
      color: white;
    }
    .navbar-logo span { color: var(--primary); }

    .navbar-links { 
      display: flex; 
      list-style: none; 
      gap: 30px; 
      align-items: center; /* Aligns links vertically with the button */
      margin: 0;
      padding: 0;
    }
    
    .navbar-links li {
      display: flex;
      align-items: center;
    }

    .navbar-links a { 
      text-decoration: none; 
      color: var(--text-muted); 
      font-weight: 500; 
      transition: 0.3s; 
      font-size: 0.95rem;
    }
    
    .navbar-links a:hover { color: white; }

    /* LOGIN BUTTON ALIGNMENT */
    .login-dropdown {
      position: relative;
      display: flex;
      align-items: center;
    }

    .login-btn {
      background: linear-gradient(135deg, var(--primary), var(--primary-dark));
      color: white;
      padding: 10px 20px;
      border-radius: 10px;
      font-weight: 600;
      cursor: pointer;
      border: none;
      display: flex;
      align-items: center;
      gap: 8px;
      transition: all 0.3s ease;
      box-shadow: 0 4px 15px rgba(16, 185, 129, 0.2);
      font-size: 0.9rem;
      line-height: 1; /* Removes extra vertical padding from text */
    }

    .login-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
    }

    /* DROPDOWN MENU */
    .dropdown-menu {
      position: absolute;
      top: calc(100% + 15px);
      right: 0;
      background: rgba(4, 26, 20, 0.98);
      backdrop-filter: blur(20px);
      border: 1px solid var(--glass-border);
      border-radius: 16px;
      min-width: 220px;
      box-shadow: 0 20px 40px rgba(0,0,0,0.3);
      opacity: 0;
      visibility: hidden;
      transform: translateY(-10px);
      transition: all 0.3s ease;
      z-index: 1001;
      overflow: hidden;
    }

    .login-dropdown.active .dropdown-menu {
      opacity: 1;
      visibility: visible;
      transform: translateY(0);
    }

    .dropdown-item {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 14px 20px;
      color: var(--text-main);
      text-decoration: none;
      font-weight: 500;
      transition: all 0.3s ease;
      border-bottom: 1px solid var(--glass);
    }

    .dropdown-item:last-child { border-bottom: none; }
    .dropdown-item:hover { background: var(--primary); color: white; padding-left: 25px; }
    .dropdown-item i { color: var(--primary); font-size: 1.1rem; }
    .dropdown-item:hover i { color: white; }

    /* MOBILE & HERO STYLES */
    .menu-toggle { display: none; font-size: 1.8rem; color: white; cursor: pointer; background: none; border: none; }

    .hero-section { flex: 1; display: flex; align-items: center; justify-content: center; text-align: center; padding: 40px 20px; }
    .hero-title { font-family: 'Syne', sans-serif; font-size: clamp(2.5rem, 6vw, 4.5rem); margin-bottom: 15px; line-height: 1.1; }
    .text-gradient { color: var(--primary); }
    
    .hero-buttons { display: flex; gap: 15px; justify-content: center; margin: 40px 0; flex-wrap: wrap; }
    .btn { padding: 16px 32px; border-radius: 12px; font-weight: 600; cursor: pointer; border: none; transition: 0.3s; text-decoration: none; }
    .btn-primary { background: var(--primary); color: white; }
    .btn-outline { background: var(--glass); color: white; border: 1px solid var(--glass-border); }
    .btn:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.2); }

    .hero-features { display: flex; gap: 20px; justify-content: center; margin-top: 20px; flex-wrap: wrap; }
    .feature-item {
      background: var(--glass); backdrop-filter: blur(8px); border: 1px solid var(--glass-border);
      width: 115px; height: 110px; border-radius: 24px; display: flex; flex-direction: column; align-items: center; justify-content: center;
      gap: 6px; padding: 10px; transition: 0.3s;
    }
    .feature-item i { font-size: 1.85rem; color: var(--primary); }
    .feature-item:hover { border-color: var(--primary); transform: scale(1.05); }
    .feature-label { font-size: 0.72rem; font-weight: 700; color: var(--text-muted); text-align: center; line-height: 1.25; }

    .site-footer { padding: 30px 16px; text-align: center; color: var(--text-muted); font-size: 0.85rem; border-top: 1px solid var(--glass-border); }

    @media (max-width: 768px) {
      * { scrollbar-width: none !important; -ms-overflow-style: none !important; }
      *::-webkit-scrollbar, html::-webkit-scrollbar, body::-webkit-scrollbar, ::-webkit-scrollbar { display: none !important; width: 0 !important; height: 0 !important; background: transparent !important; }
      .menu-toggle { display: block; }
      .navbar { height: 70px; padding: 0 5%; }
      .navbar-links { 
        position: absolute; top: 100%; left: 5%; right: 5%; 
        background: rgba(4, 26, 20, 0.98); 
        flex-direction: column; padding: 24px; 
        border-radius: 20px; border: 1px solid var(--glass-border); 
        display: none; gap: 16px;
      }
      .navbar-links.active { display: flex; }
      .login-dropdown { width: 100%; }
      .login-btn { width: 100%; justify-content: center; }
      .dropdown-menu { position: static; width: 100%; opacity: 1; visibility: visible; display: none; margin-top: 10px; transform: none; }
      .login-dropdown.active .dropdown-menu { display: block; }
    }

    @media (max-width: 480px) {
      .navbar { height: 64px; padding: 0 4%; }
      .navbar-logo { font-size: 1.25rem; }
      .hero-section { padding: 24px 14px; }
      .hero-title { font-size: clamp(1.85rem, 8.5vw, 2.4rem); }
      .hero-buttons { margin: 24px 0; gap: 10px; }
      .hero-buttons .btn { width: 100%; max-width: 100%; padding: 14px 20px; text-align: center; }
      .hero-features { gap: 10px; }
      .feature-item { width: 72px; height: 72px; border-radius: 16px; }
      .feature-item i { font-size: 1.5rem; }
      .lang-switch-btn { padding: 5px 10px; font-size: 0.78rem; }
    }

    .lang-switch-btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 7px 14px;
      border-radius: 9999px;
      background: rgba(255, 255, 255, 0.08);
      border: 1px solid rgba(255, 255, 255, 0.18);
      color: #ffffff;
      cursor: pointer;
      font-size: 0.85rem;
      font-weight: 700;
      backdrop-filter: blur(8px);
      transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
      user-select: none;
      outline: none;
    }
    .lang-switch-btn:hover {
      background: rgba(255, 255, 255, 0.16);
      transform: translateY(-1px);
    }
    .lang-switch-btn.active-tl {
      background: rgba(16, 185, 129, 0.22);
      border-color: rgba(52, 211, 153, 0.5);
      color: #6ee7b7;
    }
  </style>
</head>
<body>
  <div class="hero-bg"></div>

  <nav class="navbar">
    <a href="index.php" class="navbar-logo">
      <i class="bi bi-leaf-fill" style="color: var(--primary);"></i> Agri<span>Trace+</span>
    </a>
    
    <button class="menu-toggle" onclick="toggleMenu()">
      <i class="bi bi-list" id="menu-icon"></i>
    </button>

    <ul class="navbar-links" id="nav-links">
      <li><a href="index.php"><?= t('Home') ?></a></li>
      <li><a href="about.php"><?= t('About') ?></a></li>
      <li><a href="contact.php"><?= t('Contact') ?></a></li>
      <li>
        <button type="button" class="lang-switch-btn <?= $isTagalog ? 'active-tl' : '' ?>" onclick="toggleLanguage()" title="<?= $isTagalog ? 'Kasalukuyan: Tagalog • I-click para bumalik sa English' : 'Current: English • Click to switch to Tagalog' ?>"><span class="lang-flag"><?= $isTagalog ? '🇺🇸' : '🇵🇭' ?></span> <span class="lang-label"><?= $isTagalog ? 'English' : 'Tagalog' ?></span></button>
      </li>
      <li>
        <div class="login-dropdown" id="login-dropdown">
          <button class="login-btn" onclick="toggleLoginDropdown(event)">
            <i class="bi bi-box-arrow-in-right"></i>
            <span><?= t('Login') ?></span>
          </button>
          <div class="dropdown-menu">
            <a href="farmer.php" class="dropdown-item">
              <i class="bi bi-person-agriculture"></i>
              <span><?= t('Farmer Portal') ?></span>
            </a>
            <a href="agriDA.php" class="dropdown-item">
              <i class="bi bi-building-check"></i>
              <span><?= t('Official Portal') ?></span>
            </a>
          </div>
        </div>
      </li>
    </ul>
  </nav>

  <main class="hero-section">
    <div style="max-width: 850px; margin: 0 auto;">
      <h1 class="hero-title"><?= t('Welcome to') ?> <br><span class="text-gradient">AgriTrace+</span></h1>
      <p style="color: var(--text-muted); font-size: 1.25rem; margin-bottom: 20px; font-weight: 400;"><?= t('Digital Livestock Registration & Reporting System') ?></p>
      
      <div class="hero-buttons">
        <a href="login.php" class="btn btn-primary"><?= t('Get Started') ?></a>
        <a href="public-report-form.php" class="btn btn-outline"><?= t('Public Report') ?></a>
      </div>

      <div class="hero-features">
        <div class="feature-item" title="<?= t('Geo-Tagging') ?>">
          <i class="bi bi-geo-alt"></i>
          <span class="feature-label"><?= t('Geo-Tagging') ?></span>
        </div>
        <div class="feature-item" title="<?= t('Secure Data') ?>">
          <i class="bi bi-shield-lock"></i>
          <span class="feature-label"><?= t('Secure Data') ?></span>
        </div>
        <div class="feature-item" title="<?= t('Real-time Alerts') ?>">
          <i class="bi bi-broadcast"></i>
          <span class="feature-label"><?= t('Real-time Alerts') ?></span>
        </div>
      </div>
    </div>
  </main>

  <!-- ══════════════════════════════════════════════════════
       FARMER SERVICES & GOVERNMENT ASSISTANCE SECTION
  ══════════════════════════════════════════════════════ -->
  <section class="services-assistance-section" style="padding: 70px 8% 50px; background: rgba(4, 26, 20, 0.94); border-top: 1px solid var(--glass-border); z-index: 10; position: relative;">
    <div style="max-width: 1200px; margin: 0 auto;">
      
      <!-- Section Header -->
      <div style="text-align: center; margin-bottom: 50px;">
        <span style="display: inline-flex; align-items: center; gap: 6px; background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.35); color: #6ee7b7; font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; padding: 5px 14px; border-radius: 99px; margin-bottom: 12px;">
          <i class="bi bi-shield-check"></i> <?= $isTagalog ? 'Mga Serbisyo at Tulong Pampamahalaan' : 'Farmer Services & Official Assistance' ?>
        </span>
        <h2 style="font-family: 'Syne', sans-serif; font-size: clamp(2rem, 5vw, 2.8rem); font-weight: 800; color: #fff; margin-bottom: 14px;">
          <?= $isTagalog ? 'Kumpletong Suporta para sa mga' : 'Empowering Livestock Raisers with' ?> <span style="color: var(--primary);"><?= $isTagalog ? 'Maghahayop at Magsasaka' : 'Digital Services & DA Programs' ?></span>
        </h2>
        <p style="color: var(--text-muted); font-size: 1.05rem; max-width: 760px; margin: 0 auto; line-height: 1.6;">
          <?= $isTagalog 
            ? 'Direktang ikinokonekta ng AgriTrace+ ang mga magsasaka sa Camarines Sur 1st District sa mga serbisyo ng Department of Agriculture, proteksyon ng PCIC, at agarang tulong-beterinaryo.' 
            : 'AgriTrace+ directly bridges livestock raisers across Camarines Sur 1st District with Department of Agriculture extension services, PCIC insurance, and municipal veterinary aid.' ?>
        </p>
      </div>

      <!-- Farmer Services Subhead -->
      <div style="margin-bottom: 24px; display: flex; align-items: center; gap: 12px;">
        <div style="width: 36px; height: 36px; border-radius: 10px; background: rgba(16,185,129,.2); display: flex; align-items: center; justify-content: center; color: var(--primary); font-size: 1.2rem;">
          <i class="bi bi-person-gear"></i>
        </div>
        <div>
          <h3 style="font-family: 'Syne', sans-serif; font-size: 1.35rem; color: #fff; font-weight: 700;">
            <?= $isTagalog ? 'Mga Serbisyo para sa Magsasaka (Farmer Portal)' : 'Direct Farmer Services' ?>
          </h3>
          <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">
            <?= $isTagalog ? 'Mga digital na kagamitan para sa pang-araw-araw na pag-aalaga at pamamahala ng hayop' : 'Everyday digital livestock management and regulatory clearance tools' ?>
          </p>
        </div>
      </div>

      <!-- 6 Farmer Services Cards Grid -->
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; margin-bottom: 60px;">
        
        <!-- Card 1: Health Monitoring -->
        <div class="service-box" style="background: var(--glass); backdrop-filter: blur(12px); border: 1px solid var(--glass-border); border-radius: 18px; padding: 26px; transition: transform .3s, border-color .3s;">
          <div style="width: 48px; height: 48px; border-radius: 14px; background: rgba(16,185,129,.16); display: flex; align-items: center; justify-content: center; color: var(--primary); font-size: 1.5rem; margin-bottom: 16px;">
            <i class="bi bi-heart-pulse-fill"></i>
          </div>
          <h4 style="font-family: 'Syne', sans-serif; font-size: 1.1rem; color: #fff; margin-bottom: 8px;">
            <?= $isTagalog ? 'Pagsubaybay sa Kalusugan ng Hayop' : 'Livestock Health Monitoring' ?>
          </h4>
          <p style="font-size: 0.88rem; color: var(--text-muted); line-height: 1.55; margin-bottom: 14px;">
            <?= $isTagalog 
              ? 'Tingnan ang kumpletong kasaysayang medikal ng bawat rehistradong baka, baboy, kambing, o kalabaw — kalagayan, bakuna, pagpupurga, at iskedyul ng susunod na checkup.' 
              : 'Complete animal health cards for registered livestock — health conditions, vaccination dates, deworming history, treatments, and upcoming veterinary checkups.' ?>
          </p>
          <ul style="font-size: 0.8rem; color: #a7f3d0; list-style: none; padding: 0; display: flex; flex-direction: column; gap: 4px;">
            <li><i class="bi bi-check2-circle"></i> <?= $isTagalog ? 'Komprehensibong medikal history' : 'Comprehensive medical timeline' ?></li>
            <li><i class="bi bi-check2-circle"></i> <?= $isTagalog ? 'Alerto sa susunod na checkup' : 'Scheduled checkup notifications' ?></li>
          </ul>
        </div>

        <!-- Card 2: Request Vet Assistance -->
        <div class="service-box" style="background: var(--glass); backdrop-filter: blur(12px); border: 1px solid var(--glass-border); border-radius: 18px; padding: 26px; transition: transform .3s, border-color .3s;">
          <div style="width: 48px; height: 48px; border-radius: 14px; background: rgba(59,130,246,.16); display: flex; align-items: center; justify-content: center; color: #60a5fa; font-size: 1.5rem; margin-bottom: 16px;">
            <i class="bi bi-bandaid-fill"></i>
          </div>
          <h4 style="font-family: 'Syne', sans-serif; font-size: 1.1rem; color: #fff; margin-bottom: 8px;">
            <?= $isTagalog ? 'Humiling ng Tulong-Beterinaryo' : 'Request Veterinary Assistance' ?>
          </h4>
          <p style="font-size: 0.88rem; color: var(--text-muted); line-height: 1.55; margin-bottom: 14px;">
            <?= $isTagalog 
              ? 'Madaling humiling ng konsultasyon sa beterinaryo, agarang emergency treatment para sa may sakit na hayop, libreng bakuna, pagpupurga, at inspeksyon sa bukid.' 
              : 'One-click requests for veterinary consultation, emergency animal treatment, vaccination, deworming, farm inspections, and breeding assistance from DA technicians.' ?>
          </p>
          <ul style="font-size: 0.8rem; color: #bfdbfe; list-style: none; padding: 0; display: flex; flex-direction: column; gap: 4px;">
            <li><i class="bi bi-check2-circle"></i> <?= $isTagalog ? 'Agarang emergency response' : 'Priority emergency treatment' ?></li>
            <li><i class="bi bi-check2-circle"></i> <?= $isTagalog ? 'Libreng serbisyong munisipal' : 'Municipal LGU veterinary dispatch' ?></li>
          </ul>
        </div>

        <!-- Card 3: Digital Certificate Management -->
        <div class="service-box" style="background: var(--glass); backdrop-filter: blur(12px); border: 1px solid var(--glass-border); border-radius: 18px; padding: 26px; transition: transform .3s, border-color .3s;">
          <div style="width: 48px; height: 48px; border-radius: 14px; background: rgba(245,158,11,.16); display: flex; align-items: center; justify-content: center; color: #fbbf24; font-size: 1.5rem; margin-bottom: 16px;">
            <i class="bi bi-patch-check-fill"></i>
          </div>
          <h4 style="font-family: 'Syne', sans-serif; font-size: 1.1rem; color: #fff; margin-bottom: 8px;">
            <?= $isTagalog ? 'Pamamahala ng Digital na Sertipiko' : 'Digital Certificate Management' ?>
          </h4>
          <p style="font-size: 0.88rem; color: var(--text-muted); line-height: 1.55; margin-bottom: 14px;">
            <?= $isTagalog 
              ? 'I-upload at subaybayan ang mga sertipiko ng pagmamay-ari, veterinary health certificates, vaccination records, shipping permits, at mga resulta ng laboratoryo.' 
              : 'Securely upload and store proof of ownership, veterinary health cards, vaccination certificates, shipping permits, laboratory results, and GAHP clearances.' ?>
          </p>
          <ul style="font-size: 0.8rem; color: #fde68a; list-style: none; padding: 0; display: flex; flex-direction: column; gap: 4px;">
            <li><i class="bi bi-check2-circle"></i> <?= $isTagalog ? 'Mabilis ma-access sa mobile' : '24/7 Mobile paperless access' ?></li>
            <li><i class="bi bi-check2-circle"></i> <?= $isTagalog ? 'Opisyal na GAHP sertipiko' : 'Official GAHP certificate generator' ?></li>
          </ul>
        </div>

        <!-- Card 4: Livestock Movement & Transfers -->
        <div class="service-box" style="background: var(--glass); backdrop-filter: blur(12px); border: 1px solid var(--glass-border); border-radius: 18px; padding: 26px; transition: transform .3s, border-color .3s;">
          <div style="width: 48px; height: 48px; border-radius: 14px; background: rgba(168,85,247,.16); display: flex; align-items: center; justify-content: center; color: #c084fc; font-size: 1.5rem; margin-bottom: 16px;">
            <i class="bi bi-truck"></i>
          </div>
          <h4 style="font-family: 'Syne', sans-serif; font-size: 1.1rem; color: #fff; margin-bottom: 8px;">
            <?= $isTagalog ? 'Paglilipat at Pagbibiyahe ng Hayop' : 'Movement & Transfer Logging' ?>
          </h4>
          <p style="font-size: 0.88rem; color: var(--text-muted); line-height: 1.55; margin-bottom: 14px;">
            <?= $isTagalog 
              ? 'Itala ang benta ng hayop, paglipat ng pagmamay-ari, transfer mula sa ibang bukid, at maglakip ng mga kaukulang shipping permits at veterinary clearances.' 
              : 'Log animal sales, ownership changes, farm-to-farm transfers, transport clearances, and attach BAI shipping permits for uninterrupted biosecure transit.' ?>
          </p>
          <ul style="font-size: 0.8rem; color: #e9d5ff; list-style: none; padding: 0; display: flex; flex-direction: column; gap: 4px;">
            <li><i class="bi bi-check2-circle"></i> <?= $isTagalog ? 'Malinaw na chain of custody' : 'Transparent chain of custody' ?></li>
            <li><i class="bi bi-check2-circle"></i> <?= $isTagalog ? 'Alinsunod sa BAI protocols' : 'BAI quarantine checkpoint compliant' ?></li>
          </ul>
        </div>

        <!-- Card 5: Slaughter Activity Declarations -->
        <div class="service-box" style="background: var(--glass); backdrop-filter: blur(12px); border: 1px solid var(--glass-border); border-radius: 18px; padding: 26px; transition: transform .3s, border-color .3s;">
          <div style="width: 48px; height: 48px; border-radius: 14px; background: rgba(239,68,68,.16); display: flex; align-items: center; justify-content: center; color: #f87171; font-size: 1.5rem; margin-bottom: 16px;">
            <i class="bi bi-receipt-cutoff"></i>
          </div>
          <h4 style="font-family: 'Syne', sans-serif; font-size: 1.1rem; color: #fff; margin-bottom: 8px;">
            <?= $isTagalog ? 'Deklarasyon ng Pagkakatay (Slaughter)' : 'Slaughter Activity Declarations' ?>
          </h4>
          <p style="font-size: 0.88rem; color: var(--text-muted); line-height: 1.55; margin-bottom: 14px;">
            <?= $isTagalog 
              ? 'Opisyal na iulat ang pagkakatay para sa konsumo sa bahay, accredited slaughterhouse, emergency slaughter, o mag-ulat ng pinaghihinalaang ilegal na katayan.' 
              : 'Dedicated declaration form for household consumption, accredited slaughterhouses, emergency slaughters, and reporting suspected unauthorized meat butchering.' ?>
          </p>
          <ul style="font-size: 0.8rem; color: #fecaca; list-style: none; padding: 0; display: flex; flex-direction: column; gap: 4px;">
            <li><i class="bi bi-check2-circle"></i> <?= $isTagalog ? 'Alinsunod sa NMIS standards' : 'Compliant with NMIS safety rules' ?></li>
            <li><i class="bi bi-check2-circle"></i> <?= $isTagalog ? 'Direktang beripikasyon ng inspector' : 'Meat inspection verification notes' ?></li>
          </ul>
        </div>

        <!-- Card 6: Insurance Assistance -->
        <div class="service-box" style="background: var(--glass); backdrop-filter: blur(12px); border: 1px solid var(--glass-border); border-radius: 18px; padding: 26px; transition: transform .3s, border-color .3s;">
          <div style="width: 48px; height: 48px; border-radius: 14px; background: rgba(14,165,233,.16); display: flex; align-items: center; justify-content: center; color: #38bdf8; font-size: 1.5rem; margin-bottom: 16px;">
            <i class="bi bi-shield-shaded"></i>
          </div>
          <h4 style="font-family: 'Syne', sans-serif; font-size: 1.1rem; color: #fff; margin-bottom: 8px;">
            <?= $isTagalog ? 'Tulong sa PCIC Insurance' : 'PCIC Livestock Insurance' ?>
          </h4>
          <p style="font-size: 0.88rem; color: var(--text-muted); line-height: 1.55; margin-bottom: 14px;">
            <?= $isTagalog 
              ? 'Tingnan ang impormasyon sa seguro ng PCIC, alamin ang mga kailangan, magsumite ng dokumento, iulat ang pagkamatay ng hayop, at subaybayan ang claims referral.' 
              : 'Access Philippine Crop Insurance Corporation (PCIC) coverage details, file death casualty notices, upload necropsy photos, and track claim endorsements.' ?>
          </p>
          <ul style="font-size: 0.8rem; color: #bae6fd; list-style: none; padding: 0; display: flex; flex-direction: column; gap: 4px;">
            <li><i class="bi bi-check2-circle"></i> <?= $isTagalog ? 'Tulong sa bayad-pinsala sa sakuna' : 'Financial safety net against losses' ?></li>
            <li><i class="bi bi-check2-circle"></i> <?= $isTagalog ? 'Subaybayan ang endorsement code' : 'Real-time PCIC tracking codes' ?></li>
          </ul>
        </div>

      </div>

      <!-- Government Assistance Subhead -->
      <div style="margin-bottom: 24px; display: flex; align-items: center; gap: 12px;">
        <div style="width: 36px; height: 36px; border-radius: 10px; background: rgba(59,130,246,.2); display: flex; align-items: center; justify-content: center; color: #60a5fa; font-size: 1.2rem;">
          <i class="bi bi-building-check"></i>
        </div>
        <div>
          <h3 style="font-family: 'Syne', sans-serif; font-size: 1.35rem; color: #fff; font-weight: 700;">
            <?= $isTagalog ? 'Tulong Mula sa Pamahalaan (DA &amp; LGU Programs)' : 'Official Government Assistance Programs' ?>
          </h3>
          <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">
            <?= $isTagalog ? 'Mga serbisyo at programa mula sa Department of Agriculture RFO V at mga lokal na pamahalaan' : 'Subsidized programs from the Department of Agriculture Regional Field Office V and Municipal LGUs' ?>
          </p>
        </div>
      </div>

      <!-- Government Assistance Grid (4 Pillars) -->
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 20px;">
        
        <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); border-radius: 16px; padding: 22px;">
          <h5 style="font-size: 1rem; color: #34d399; font-weight: 700; margin-bottom: 8px; display: flex; align-items: center; gap: 8px;">
            <i class="bi bi-eyedropper"></i> <?= $isTagalog ? 'Libreng Bakuna at Pagpupurga' : 'Free Vaccination & Deworming' ?>
          </h5>
          <p style="font-size: 0.84rem; color: var(--text-muted); line-height: 1.5;">
            <?= $isTagalog 
              ? 'Regular na isinasagawa ang libreng pagbabakuna laban sa Hemorrhagic Septicemia, Hog Cholera, Rabies, at Avian Disease sa lahat ng 5 bayan ng 1st District.' 
              : 'Scheduled district-wide mass immunization drives protecting cattle, swine, carabaos, and poultry against major contagious animal diseases at zero cost.' ?>
          </p>
        </div>

        <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); border-radius: 16px; padding: 22px;">
          <h5 style="font-size: 1rem; color: #60a5fa; font-weight: 700; margin-bottom: 8px; display: flex; align-items: center; gap: 8px;">
            <i class="bi bi-shield-virus"></i> <?= $isTagalog ? 'Disease Surveillance at Quarantine' : 'District Disease Surveillance' ?>
          </h5>
          <p style="font-size: 0.84rem; color: var(--text-muted); line-height: 1.5;">
            <?= $isTagalog 
              ? 'Awtomatikong 1km quarantine containment ring at 7km surveillance monitoring buffer sa GIS map upang mabilis mapigilan ang pagkalat ng ASF o bird flu.' 
              : 'Automated 1km quarantine containment perimeters and 7km clinical observation zones on the GIS map to rapidly contain African Swine Fever and Avian Influenza.' ?>
          </p>
        </div>

        <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); border-radius: 16px; padding: 22px;">
          <h5 style="font-size: 1rem; color: #f59e0b; font-weight: 700; margin-bottom: 8px; display: flex; align-items: center; gap: 8px;">
            <i class="bi bi-geo-alt-fill"></i> <?= $isTagalog ? 'Beripikasyon ng Lokasyon ng Bukid' : 'Farm Geo-Verification' ?>
          </h5>
          <p style="font-size: 0.84rem; color: var(--text-muted); line-height: 1.5;">
            <?= $isTagalog 
              ? 'Opisyal na beripikasyon ng mga sakahan ng DA Municipal Agricultural Officers upang maging kwalipikado sa mga subsidiya, pautang, at tulong-kalamidad.' 
              : 'Official validation of farm coordinates and livestock counts by DA officers, qualifying raisers for agricultural livelihood subsidies and calamity rehabilitation.' ?>
          </p>
        </div>

        <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); border-radius: 16px; padding: 22px;">
          <h5 style="font-size: 1rem; color: #ec4899; font-weight: 700; margin-bottom: 8px; display: flex; align-items: center; gap: 8px;">
            <i class="bi bi-cash-coin"></i> <?= $isTagalog ? 'PCIC Indemnity Assistance' : 'PCIC Indemnity Referrals' ?>
          </h5>
          <p style="font-size: 0.84rem; color: var(--text-muted); line-height: 1.5;">
            <?= $isTagalog 
              ? 'Tulong sa mabilisang pag-endorso ng mga ulat ng namatay na alagang hayop upang agad maproseso ang indemnity compensation ng Philippine Crop Insurance Corp.' 
              : 'Official endorsement and documentation processing assisting farmers in recovering financial losses from animal deaths caused by disease, accidents, or typhoons.' ?>
          </p>
        </div>

      </div>

    </div>
  </section>

  <footer class="site-footer">
    © 2026 AgriTrace Technologies | <a href="about.php" style="color: var(--primary); text-decoration: none;"><?= t('About') ?></a> | <a href="contact.php" style="color: var(--primary); text-decoration: none;"><?= t('Contact') ?></a>
  </footer>

  <script>
    function toggleMenu() {
      const links = document.getElementById('nav-links');
      const icon = document.getElementById('menu-icon');
      links.classList.toggle('active');
      icon.classList.toggle('bi-list');
      icon.classList.toggle('bi-x-lg');
    }

    function toggleLoginDropdown(e) {
      e.stopPropagation();
      const dropdown = document.getElementById('login-dropdown');
      dropdown.classList.toggle('active');
    }

    // Close dropdown when clicking anywhere else
    document.addEventListener('click', function(e) {
      const dropdown = document.getElementById('login-dropdown');
      if (!dropdown.contains(e.target)) {
        dropdown.classList.remove('active');
      }
    });

    // Mobile specific interaction
    document.getElementById('login-dropdown').addEventListener('click', function(e) {
      if (window.innerWidth <= 768 && e.target.closest('.login-btn')) {
        // Keeps dropdown open on mobile when button is clicked
      }
    });
  </script>
  <script src="<?= function_exists('app_url') ? app_url('assets/js/translator.js') : 'assets/js/translator.js' ?>"></script>
  <script>
    // Fallback script loader to guarantee translator is active regardless of route or rewrite
    if (typeof window.toggleLanguage !== 'function') {
      (function() {
        var s = document.createElement('script');
        s.src = (window.location.pathname.indexOf('/pages/') !== -1) ? '../assets/js/translator.js' : 'assets/js/translator.js';
        document.body.appendChild(s);
      })();
    }
  </script>
</body>
</html>
