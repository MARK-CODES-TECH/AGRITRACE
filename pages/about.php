<?php
require_once dirname(__DIR__) . '/init.php';
require_once dirname(__DIR__) . '/helpers.php';
$isTagalog = (current_lang() === 'tl');
?>
<!DOCTYPE html>
<html lang="<?= $isTagalog ? 'tl' : 'en' ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>About AgriTrace+ | <?= $isTagalog ? 'Tungkol sa Sistema' : 'Project Objectives, Users & Coverage' ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    :root {
      --primary-green: #10b981;
      --primary-dark: #059669;
      --dark-bg: #062c23;
      --glass: rgba(255, 255, 255, 0.05);
      --glass-border: rgba(255, 255, 255, 0.12);
      --text-muted: rgba(255, 255, 255, 0.72);
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }
    html, body { overflow-x: hidden; width: 100%; min-height: 100%; }
    body { font-family: 'DM Sans', sans-serif; background-color: var(--dark-bg); color: white; line-height: 1.6; }

    .hero-bg {
      position: fixed; top: 0; left: 0; width: 100%; height: 100%;
      background: linear-gradient(rgba(6, 44, 35, 0.82), rgba(6, 44, 35, 0.94)), 
                  url('https://images.unsplash.com/photo-1500382017468-9049fed747ef?q=80&w=2000') center/cover;
      z-index: -1;
    }

    .navbar {
      position: fixed; top: 0; width: 100%; display: flex; justify-content: space-between; align-items: center;
      padding: 15px 8%; background: rgba(6, 44, 35, 0.95); backdrop-filter: blur(12px); z-index: 1001;
      border-bottom: 1px solid var(--glass-border);
    }
    .navbar-logo { font-family: 'Syne', sans-serif; font-size: 1.5rem; font-weight: 800; color: white; text-decoration: none; }
    .navbar-logo span { color: var(--primary-green); }
    .navbar-links { display: flex; list-style: none; align-items: center; gap: 24px; }
    .navbar-links a { text-decoration: none; color: rgba(255,255,255,0.7); font-weight: 500; font-size: 0.92rem; transition: 0.3s; }
    .navbar-links a:hover, .navbar-links a.active { color: white; font-weight: 700; }
    .btn-nav { background: var(--primary-green) !important; color: white !important; padding: 9px 20px !important; border-radius: 10px; font-weight: 700; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3); }
    .menu-toggle { display: none; font-size: 1.8rem; color: white; cursor: pointer; }

    .content-page-wrap { padding-top: 105px; padding-bottom: 60px; min-height: 100vh; }
    .container { width: 90%; max-width: 1120px; margin: 0 auto; }
    
    .content-header { text-align: center; margin-bottom: 45px; padding: 0 10px; }
    .content-header h1 { font-family: 'Syne', sans-serif; font-size: clamp(2.2rem, 6vw, 3.2rem); font-weight: 800; margin-bottom: 12px; }
    .badge-scope {
      display: inline-flex; align-items: center; gap: 6px;
      background: rgba(16, 185, 129, 0.16); border: 1px solid rgba(16, 185, 129, 0.38);
      color: #6ee7b7; font-size: 0.8rem; font-weight: 700; padding: 5px 14px; border-radius: 99px;
      margin-bottom: 16px; text-transform: uppercase; letter-spacing: 0.05em;
    }

    .glass-card {
      background: var(--glass); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
      border: 1px solid var(--glass-border); padding: 32px; border-radius: 22px; margin-bottom: 28px;
      box-shadow: 0 12px 36px rgba(0,0,0,0.25);
    }
    .card-title {
      font-family: 'Syne', sans-serif; font-size: 1.38rem; font-weight: 800; color: #fff;
      display: flex; align-items: center; gap: 12px; margin-bottom: 18px;
    }
    .card-title i {
      width: 40px; height: 40px; border-radius: 10px; display: inline-flex;
      align-items: center; justify-content: center; font-size: 1.25rem;
      background: rgba(16,185,129,.18); color: var(--primary-green);
    }

    .objectives-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 18px; }
    .obj-item {
      background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 14px; padding: 20px; transition: transform .2s;
    }
    .obj-item:hover { transform: translateY(-3px); border-color: rgba(16,185,129,.4); }
    .obj-num { font-size: 1.5rem; font-weight: 800; font-family: 'Syne', sans-serif; color: var(--primary-green); margin-bottom: 6px; }
    .obj-title { font-weight: 700; font-size: 1rem; color: #fff; margin-bottom: 6px; }
    .obj-desc { font-size: 0.85rem; color: var(--text-muted); line-height: 1.5; }

    .users-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 18px; }
    .user-pill {
      background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 14px; padding: 20px;
    }
    .user-pill h4 { font-size: 1.02rem; font-weight: 700; color: #fff; margin-bottom: 6px; display: flex; align-items: center; gap: 8px; }
    .user-pill p { font-size: 0.84rem; color: var(--text-muted); line-height: 1.5; }

    .coverage-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 14px; margin-top: 14px; }
    .muni-card {
      background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.25);
      border-radius: 12px; padding: 16px; text-align: center;
    }
    .muni-card h5 { font-family: 'Syne', sans-serif; font-size: 1.1rem; font-weight: 700; color: #6ee7b7; margin-bottom: 4px; }
    .muni-card span { font-size: 0.78rem; color: var(--text-muted); }

    .site-footer { text-align: center; padding: 40px; color: rgba(255,255,255,0.4); font-size: 0.88rem; }

    @media (max-width: 768px) {
      .menu-toggle { display: block; }
      .navbar-links {
        position: absolute; top: 70px; right: -100%; width: 75%; height: 100vh;
        background: rgba(6, 44, 35, 0.98); flex-direction: column; padding: 40px 24px;
        transition: 0.4s ease-in-out; backdrop-filter: blur(20px); border-left: 1px solid var(--glass-border);
      }
      .navbar-links.active { right: 0; }
      .navbar-links li { width: 100%; text-align: center; margin: 12px 0; }
      .btn-nav { display: inline-block; width: 100%; }
    }
  </style>
</head>
<body>
  <div class="hero-bg"></div>

  <nav class="navbar">
    <a href="index.php" class="navbar-logo">
      <i class="bi bi-leaf-fill"></i> Agri<span>Trace+</span>
    </a>
    <div class="menu-toggle" id="mobile-menu">
      <i class="bi bi-list"></i>
    </div>
    <ul class="navbar-links" id="nav-list">
      <li><a href="index.php"><?= t('Home') ?></a></li>
      <li><a href="about.php" class="active"><?= t('About') ?></a></li>
      <li><a href="contact.php"><?= t('Contact') ?></a></li>
      <li><a href="login.php" class="btn-nav"><?= t('Login') ?></a></li>
    </ul>
  </nav>

  <div class="content-page-wrap">
    <div class="container">
      
      <!-- HEADER -->
      <div class="content-header">
        <span class="badge-scope"><i class="bi bi-geo-alt-fill"></i> Camarines Sur &bull; 1st Congressional District</span>
        <h1><?= $isTagalog ? 'Tungkol sa' : 'About' ?> <span style="color:var(--primary-green);">AgriTrace+</span></h1>
        <p style="color:var(--text-muted); font-size:1.1rem; max-width:750px; margin:0 auto;">
          <?= $isTagalog 
            ? 'Ang opisyal na digital na plataporma para sa rehistrasyon ng hayop, pagsubaybay sa biosecurity, at serbisyong beterinaryo sa Unang Distrito ng Camarines Sur.' 
            : 'The official digital platform for geospatial livestock registration, real-time biosecurity disease surveillance, and veterinary services in Camarines Sur 1st District.' ?>
        </p>
      </div>

      <!-- 1. ACTUAL PROJECT OBJECTIVES -->
      <div class="glass-card">
        <div class="card-title">
          <i class="bi bi-bullseye"></i>
          <span><?= $isTagalog ? 'Mga Tunay na Layunin ng Proyekto' : 'Actual Project Objectives' ?></span>
        </div>
        <div class="objectives-grid">
          
          <div class="obj-item">
            <div class="obj-num">01</div>
            <div class="obj-title"><?= $isTagalog ? 'Geospatial Livestock Registry' : 'Geospatial Livestock Registry' ?></div>
            <div class="obj-desc">
              <?= $isTagalog 
                ? 'Itatag ang kumpleto at geo-tagged na talaan ng bawat sakahan, bakuran, at alagang hayop (baka, baboy, manok, kambing, kalabaw) upang masubaybayan ang kabuuang populasyon.' 
                : 'Establish an authenticated, geo-referenced spatial directory of every livestock holding facility and animal population across the district for accurate census and resource planning.' ?>
            </div>
          </div>

          <div class="obj-item">
            <div class="obj-num">02</div>
            <div class="obj-title"><?= $isTagalog ? 'Real-Time Biosecurity Surveillance' : 'Real-Time Biosecurity Surveillance' ?></div>
            <div class="obj-desc">
              <?= $isTagalog 
                ? 'Agad na matukoy ang mga sakit ng hayop gamit ang GIS map na may awtomatikong 1km quarantine containment ring at 7km surveillance buffer zone upang mapigilan ang ASF at bird flu.' 
                : 'Rapidly monitor livestock health and automate 1km quarantine containment perimeters with 7km surveillance observation zones to contain African Swine Fever (ASF) and Avian Flu.' ?>
            </div>
          </div>

          <div class="obj-item">
            <div class="obj-num">03</div>
            <div class="obj-title"><?= $isTagalog ? 'Traceable Chain of Custody' : 'Traceable Movement & Slaughter' ?></div>
            <div class="obj-desc">
              <?= $isTagalog 
                ? 'Itala ang bawat paglipat, benta, transportasyon, at pagkakatay alinsunod sa NMIS at BAI guidelines upang matiyak ang kaligtasan ng karne at pigilan ang nakaw na hayop.' 
                : 'Log livestock sales, ownership transfers, transport clearances, and slaughter activities in strict compliance with NMIS food safety and BAI biosecurity transit protocols.' ?>
            </div>
          </div>

          <div class="obj-item">
            <div class="obj-num">04</div>
            <div class="obj-title"><?= $isTagalog ? 'Direktang Tulong Pampamahalaan' : 'Farmer & Insurance Support' ?></div>
            <div class="obj-desc">
              <?= $isTagalog 
                ? 'Ihatid ang libreng tulong-beterinaryo, regular na bakuna, pagpupurga, at mabilis na referral sa Philippine Crop Insurance Corporation (PCIC) para sa indemnity claims.' 
                : 'Empower raisers with direct municipal vet requests, free vaccination drives, and fast-track Philippine Crop Insurance Corporation (PCIC) casualty compensation endorsements.' ?>
            </div>
          </div>

        </div>
      </div>

      <!-- 2. TARGET USERS -->
      <div class="glass-card">
        <div class="card-title">
          <i class="bi bi-people-fill"></i>
          <span><?= $isTagalog ? 'Mga Target na Gumagamit (Target Users)' : 'Target Users & Stakeholders' ?></span>
        </div>
        <div class="users-grid">
          
          <div class="user-pill">
            <h4><i class="bi bi-person-badge-fill" style="color:var(--primary-green);"></i> <?= $isTagalog ? 'Mga Magsasaka at Maghahayop' : 'Livestock Raisers & Farmers' ?></h4>
            <p>
              <?= $isTagalog 
                ? 'Mga may-ari ng alagang baka, baboy, kambing, o poultry na nagrerehistro ng kanilang sakahan, nag-a-update ng medical health card, humihiling ng tulong-beterinaryo, at nag-a-apply ng PCIC insurance.' 
                : 'Smallholder backyard raisers and commercial farm owners who manage digital animal health histories, request veterinary services, declare slaughters, and access PCIC insurance.' ?>
            </p>
          </div>

          <div class="user-pill">
            <h4><i class="bi bi-building-check" style="color:#60a5fa;"></i> <?= $isTagalog ? 'DA RFO V at MAO Officers' : 'DA Officials & MAO Staff' ?></h4>
            <p>
              <?= $isTagalog 
                ? 'Mga opisyal ng Department of Agriculture at Municipal Agriculture Offices na nagbe-beripika ng lokasyon ng bukid, nag-aapruba ng account, at sumusubaybay sa mga ulat ng sakit.' 
                : 'Department of Agriculture Regional Field Office V and Municipal Agriculture Officers (MAO) who verify farm coordinates, approve farmer registrations, and monitor district biosecurity.' ?>
            </p>
          </div>

          <div class="user-pill">
            <h4><i class="bi bi-bandaid-fill" style="color:#f59e0b;"></i> <?= $isTagalog ? 'Beterinaryo at Livestock Technicians' : 'Veterinarians & Technicians' ?></h4>
            <p>
              <?= $isTagalog 
                ? 'Mga lisensyadong beterinaryo at municipal livestock inspectors na nagsasagawa ng checkup sa bukid, nagbabakuna, nagpupurga, at naglalabas ng health certificates.' 
                : 'Accredited field veterinarians and livestock inspectors conducting on-site clinical checkups, emergency treatments, mass immunizations, and meat safety evaluations.' ?>
            </p>
          </div>

          <div class="user-pill">
            <h4><i class="bi bi-megaphone-fill" style="color:#ec4899;"></i> <?= $isTagalog ? 'Pangkalahatang Publiko at Mamamayan' : 'General Public & Citizens' ?></h4>
            <p>
              <?= $isTagalog 
                ? 'Mga residente at mamamayan na nag-uulat ng mga pagala, may sakit, o patay na hayop, at pinaghihinalaang ilegal na katayan na may proteksyon sa ilalim ng Data Privacy Act.' 
                : 'Concerned community members submitting confidential geo-tagged incident reports regarding sick, stray, or deceased animals and suspected unauthorized slaughter.' ?>
            </p>
          </div>

        </div>
      </div>

      <!-- 3. COVERAGE AREA -->
      <div class="glass-card">
        <div class="card-title">
          <i class="bi bi-map-fill"></i>
          <span><?= $isTagalog ? 'Saklaw na Lugar (Coverage Area)' : 'Coverage Area & Jurisdiction' ?></span>
        </div>
        <p style="color:var(--text-muted); font-size:0.92rem; margin-bottom:12px;">
          <?= $isTagalog 
            ? 'Ang AgriTrace+ ay eksklusibong idinisenyo at ipinapatupad para sa Unang Lehislatibong Distrito ng Camarines Sur, sumasaklaw sa limang (5) estratehikong munisipalidad ng agrikultura:' 
            : 'AgriTrace+ is purpose-built and deployed across the 1st Congressional District of Camarines Sur, encompassing five (5) strategic agricultural municipalities:' ?>
        </p>

        <div class="coverage-grid">
          
          <div class="muni-card">
            <h5>Cabusao</h5>
            <span><?= $isTagalog ? 'Baybayin at sakahang pamayanan ng kalabaw at baboy' : 'Coastal agricultural lowland & carabao raisers' ?></span>
          </div>

          <div class="muni-card">
            <h5>Del Gallego</h5>
            <span><?= $isTagalog ? 'Hilagang hangganan na may malawak na pastulan' : 'Northern border corridor & extensive grazing lands' ?></span>
          </div>

          <div class="muni-card">
            <h5>Lupi</h5>
            <span><?= $isTagalog ? 'Pang-agrikulturang lupain ng iba\'t ibang alagang hayop' : 'Diversified agrarian heartland & poultry/swine raisers' ?></span>
          </div>

          <div class="muni-card">
            <h5>Ragay</h5>
            <span><?= $isTagalog ? 'Pangunahing koridor ng produksyon at biyahe ng hayop' : 'Major livestock transit corridor & quarantine checkpoints' ?></span>
          </div>

          <div class="muni-card">
            <h5>Sipocot</h5>
            <span><?= $isTagalog ? 'Sentro ng kalakalan ng hayop sa Unang Distrito' : 'Central commercial & livestock trading hub' ?></span>
          </div>

        </div>
      </div>

    </div>

    <footer class="site-footer">
      &copy; 2026 AgriTrace Technologies &bull; Department of Agriculture RFO V &bull; 1st District of Camarines Sur
    </footer>
  </div>

  <script>
    const menuToggle = document.getElementById('mobile-menu');
    const navList = document.getElementById('nav-list');
    menuToggle.addEventListener('click', () => {
      navList.classList.toggle('active');
      const icon = menuToggle.querySelector('i');
      icon.classList.toggle('bi-list');
      icon.classList.toggle('bi-x-lg');
    });
  </script>
</body>
</html>
