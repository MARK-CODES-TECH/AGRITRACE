<?php require_once __DIR__ . '/init.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>AgriTrace+ | Digital Livestock Registration & Reporting System</title>
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
      width: 100px; height: 100px; border-radius: 24px; display: flex; align-items: center; justify-content: center;
      transition: 0.3s;
    }
    .feature-item i { font-size: 2rem; color: var(--primary); }
    .feature-item:hover { border-color: var(--primary); transform: scale(1.05); }

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
      <li><a href="index.php">Home</a></li>
      <li><a href="about.php">About</a></li>
      <li><a href="contact.php">Contact</a></li>
      <li>
        <button type="button" class="lang-switch-btn" onclick="toggleLanguage()" title="Palitan ang Wika / Switch Language"><span class="lang-flag">🇵🇭</span> <span class="lang-label">Tagalog</span></button>
      </li>
      <li>
        <div class="login-dropdown" id="login-dropdown">
          <button class="login-btn" onclick="toggleLoginDropdown(event)">
            <i class="bi bi-box-arrow-in-right"></i>
            <span>Login</span>
          </button>
          <div class="dropdown-menu">
            <a href="farmer.php" class="dropdown-item">
              <i class="bi bi-person-agriculture"></i>
              <span>Farmer Portal</span>
            </a>
            <a href="agriDA.php" class="dropdown-item">
              <i class="bi bi-building-check"></i>
              <span>Official Portal</span>
            </a>
            <a href="admin.php" class="dropdown-item">
              <i class="bi bi-shield-lock-fill"></i>
              <span>Admin Console</span>
            </a>
          </div>
        </div>
      </li>
    </ul>
  </nav>

  <main class="hero-section">
    <div style="max-width: 850px; margin: 0 auto;">
      <h1 class="hero-title">Welcome to <br><span class="text-gradient">AgriTrace+</span></h1>
      <p style="color: var(--text-muted); font-size: 1.25rem; margin-bottom: 20px; font-weight: 400;">Digital Livestock Registration & Reporting System</p>
      
      <div class="hero-buttons">
        <a href="login.php" class="btn btn-primary">Get Started</a>
        <a href="public-report-form.php" class="btn btn-outline">Public Report</a>
      </div>

      <div class="hero-features">
        <div class="feature-item" title="Geo-Tagging">
          <i class="bi bi-geo-alt"></i>
        </div>
        <div class="feature-item" title="Secure Data">
          <i class="bi bi-shield-lock"></i>
        </div>
        <div class="feature-item" title="Real-time Alerts">
          <i class="bi bi-broadcast"></i>
        </div>
      </div>
    </div>
  </main>

  <footer class="site-footer">
    © 2026 AgriTrace Technologies | <a href="about.php" style="color: var(--primary); text-decoration: none;">About</a> | <a href="contact.php" style="color: var(--primary); text-decoration: none;">Contact</a>
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
  <script src="../assets/js/translator.js"></script>
</body>
</html>
