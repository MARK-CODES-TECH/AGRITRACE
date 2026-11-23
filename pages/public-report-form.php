<?php
/**
 * AgriTrace+ | Public Report Form
 */
require_once __DIR__ . '/init.php';
require_once dirname(__DIR__) . '/helpers.php';
require_once __DIR__ . '/db_connect.php';

$isTagalog = (current_lang() === 'tl');

if (!function_exists('handleUpload')) {
    function handleUpload(string $fieldName, string $subDir = 'public_reports'): ?string {
        if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) return null;
        $uploadDir = dirname(__DIR__) . "/uploads/{$subDir}/";
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $file = $_FILES[$fieldName];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','gif','webp','mp4','mov','avi','pdf'], true)) return null;
        if ($file['size'] > 10 * 1024 * 1024) return null;
        $filename = uniqid('rpt_', true) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) return null;
        return "uploads/{$subDir}/{$filename}";
    }
}

$success = false;
$errors  = [];
$newId   = null;

// Report tracking lookup
$trackResult = null;
$trackError = '';
$trackQuery = trim($_GET['track'] ?? $_POST['track_query'] ?? '');
if ($trackQuery !== '') {
    $cleanId = preg_replace('/[^0-9]/', '', $trackQuery);
    try {
        $stmt = $pdo->prepare("
            SELECT id, reportType, description, status, createdAt,
                   COALESCE(updatedAt, createdAt) as last_update
            FROM public_reports
            WHERE id = ? OR contactPhone = ?
            ORDER BY createdAt DESC LIMIT 10
        ");
        $stmt->execute([$cleanId ?: -1, $trackQuery]);
        $trackResult = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($trackResult)) {
            $trackError = $isTagalog ? 'Walang nahanap na ulat para sa binigay na ID o numero.' : 'No reports found for that Reference ID or phone number.';
        }
    } catch (PDOException $e) {
        $trackError = 'Query error: ' . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== 'track_report') {
    $reportType  = trim($_POST['report_type']   ?? '');
    $otherType   = trim($_POST['other_type']    ?? '');
    $description = trim($_POST['description']   ?? '');
    $phone       = trim($_POST['contact_phone'] ?? '');
    $email       = trim($_POST['contact_email'] ?? '');
    $latRaw      = trim($_POST['latitude']      ?? '');
    $lngRaw      = trim($_POST['longitude']     ?? '');
    $terms       = isset($_POST['terms']);

    $validTypes = ['Sick','Dead','Stray','Disease','Others'];
    if (!in_array($reportType, $validTypes, true))           $errors[] = t('Please select a report type.');
    if ($reportType === 'Others' && $otherType === '')        $errors[] = t('Please specify the report type.');
    if ($description === '')                                  $errors[] = t('Description is required.');
    if ($phone === '')                                        $errors[] = t('Contact phone is required.');
    elseif (!preg_match('/^[\d\s\+\-\(\)]{7,20}$/', $phone)) $errors[] = t('Invalid phone number.');
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = t('Invalid email address.');
    if (!$terms)                                              $errors[] = t('You must accept the terms and conditions.');

    $latitude  = ($latRaw  !== '' && is_numeric($latRaw))  ? (float)$latRaw  : null;
    $longitude = ($lngRaw  !== '' && is_numeric($lngRaw))  ? (float)$lngRaw  : null;

    $idPhotoUrl = handleUpload('id_photo') ?? handleUpload('id_cam');
    $faceUrl    = handleUpload('face_photo') ?? handleUpload('face_cam');
    $mediaUrl   = handleUpload('media_file') ?? handleUpload('media_cam');

    // Strict validation: Citizen must upload both an ID photo and a Face photo before submitting
    if (!$idPhotoUrl) {
        $errors[] = t('Valid ID Photo is required before submitting the report.');
    }
    if (!$faceUrl) {
        $errors[] = t('Face Photo is required before submitting the report.');
    }

    if (empty($errors)) {
        try {
            // Ensure media/photo columns exist in public_reports table
            $hasMediaCol = false;
            try {
                $cols = $pdo->query("SHOW COLUMNS FROM public_reports")->fetchAll(PDO::FETCH_COLUMN);
                if (!in_array('idPhotoUrl', $cols, true)) {
                    @$pdo->exec("ALTER TABLE public_reports ADD COLUMN idPhotoUrl VARCHAR(500) NULL AFTER contactEmail");
                }
                if (!in_array('facePhotoUrl', $cols, true)) {
                    @$pdo->exec("ALTER TABLE public_reports ADD COLUMN facePhotoUrl VARCHAR(500) NULL AFTER idPhotoUrl");
                }
                if (!in_array('mediaUrl', $cols, true)) {
                    @$pdo->exec("ALTER TABLE public_reports ADD COLUMN mediaUrl VARCHAR(500) NULL AFTER facePhotoUrl");
                }
                $refreshed = $pdo->query("SHOW COLUMNS FROM public_reports")->fetchAll(PDO::FETCH_COLUMN);
                $hasMediaCol = in_array('mediaUrl', $refreshed, true);
            } catch (Exception $eCol) {
                $hasMediaCol = false;
            }

            if ($hasMediaCol) {
                $stmt = $pdo->prepare("
                    INSERT INTO public_reports
                        (reportType, otherType, description, contactPhone, contactEmail,
                         idPhotoUrl, facePhotoUrl, mediaUrl, latitude, longitude, status, createdAt)
                    VALUES
                        (:rt, :ot, :desc, :phone, :email, :id, :face, :media, :lat, :lng, 'Pending', NOW())
                ");
                $stmt->execute([
                    ':rt'    => $reportType,
                    ':ot'    => $reportType === 'Others' ? $otherType : null,
                    ':desc'  => $description,
                    ':phone' => $phone,
                    ':email' => $email !== '' ? $email : null,
                    ':id'    => $idPhotoUrl,
                    ':face'  => $faceUrl,
                    ':media' => $mediaUrl,
                    ':lat'   => $latitude,
                    ':lng'   => $longitude,
                ]);
            } else {
                $descToSave = $mediaUrl ? ($description . "\n[Attachment: " . $mediaUrl . "]") : $description;
                $stmt = $pdo->prepare("
                    INSERT INTO public_reports
                        (reportType, otherType, description, contactPhone, contactEmail,
                         idPhotoUrl, facePhotoUrl, latitude, longitude, status, createdAt)
                    VALUES
                        (:rt, :ot, :desc, :phone, :email, :id, :face, :lat, :lng, 'Pending', NOW())
                ");
                $stmt->execute([
                    ':rt'    => $reportType,
                    ':ot'    => $reportType === 'Others' ? $otherType : null,
                    ':desc'  => $descToSave,
                    ':phone' => $phone,
                    ':email' => $email !== '' ? $email : null,
                    ':id'    => $idPhotoUrl,
                    ':face'  => $faceUrl,
                    ':lat'   => $latitude,
                    ':lng'   => $longitude,
                ]);
            }
            $newId   = $pdo->lastInsertId();
            $success = true;

            // Notify active DA Agriculture Officials of the new public report
            try {
                $officers = $pdo->query("SELECT id FROM users WHERE role = 'Agriculture Official' AND status = 'Active'")->fetchAll(PDO::FETCH_ASSOC);
                if ($officers) {
                    $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, priority, created_at) VALUES (?, 'WARNING', ?, ?, 'Medium', NOW())");
                    $shortDesc = mb_substr($description, 0, 80);
                    foreach ($officers as $officer) {
                        $notifStmt->execute([
                            $officer['id'],
                            "📢 New Public Report #{$newId}",
                            "Public report ({$reportType}): {$shortDesc}"
                        ]);
                    }
                }
            } catch (Exception $ne) {
                // Ignore notification failure if table differs
            }
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $isTagalog ? 'tl' : 'en' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AgriTrace+ | <?= $isTagalog ? 'Pampublikong Ulat ng Insidente' : 'Public Incident Report' ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<style>
:root {
    --green:#00a86b; --green-d:#006b44; --green-glow:rgba(0,168,107,.22);
    --blue:#3b82f6;  --red:#ef4444;
    --glass:rgba(255,255,255,.07); --gb:rgba(255,255,255,.14);
    --muted:rgba(255,255,255,.5);  --ease:cubic-bezier(.16,1,.3,1);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent;}
html{overflow-x:hidden;width:100%;-webkit-text-size-adjust:100%;text-size-adjust:100%;scroll-behavior:smooth;}
body{font-family:'DM Sans',sans-serif;min-height:100vh;color:#fff;overflow-x:hidden;width:100%;max-width:100vw;
    background:linear-gradient(rgba(0,0,0,.54),rgba(0,0,0,.62)),
    url('https://images.unsplash.com/photo-1500382017468-9049fed747ef?auto=format&fit=crop&w=1920&q=80') center/cover fixed}
h1,h2,h3,h4,h5,h6,p,span,a,label,strong,em,td,th{overflow-wrap:break-word;word-wrap:break-word;}

/* NAV */
.nav{display:flex;justify-content:space-between;align-items:center;padding:16px 44px;
    background:rgba(0,0,0,.2);backdrop-filter:blur(14px);border-bottom:1px solid var(--gb);
    position:sticky;top:0;z-index:200}
.logo{font-family:'Syne',sans-serif;font-size:1.3rem;font-weight:800;letter-spacing:-.5px}
.logo span{color:var(--green)}
.nav-links a{color:var(--muted);text-decoration:none;margin-left:20px;font-size:.87rem;font-weight:500;transition:color .2s}
.nav-links a:hover{color:#fff}
.nav-links .lbtn{background:var(--green);color:#fff;padding:7px 15px;border-radius:8px;font-weight:700}

/* PAGE */
.page{display:flex;justify-content:center;padding:36px 16px 60px}
.card{width:100%;max-width:640px;background:var(--glass);backdrop-filter:blur(18px);
    -webkit-backdrop-filter:blur(18px);border:1px solid var(--gb);border-radius:20px;
    padding:36px 36px 30px;box-shadow:0 20px 60px rgba(0,0,0,.45);position:relative;
    animation:fadeUp .5s var(--ease) both}
@keyframes fadeUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
.close-btn{position:absolute;right:18px;top:18px;color:var(--muted);text-decoration:none;
    font-size:1.25rem;transition:color .2s,transform .2s;line-height:1}
.close-btn:hover{color:#fff;transform:scale(1.15)}

/* HEADER */
.header{text-align:center;margin-bottom:26px}
.header h1{font-family:'Syne',sans-serif;font-size:1.9rem;font-weight:800;letter-spacing:-1px}
.header h1 span{color:var(--green)}
.badge{display:inline-flex;align-items:center;gap:5px;
    background:rgba(59,130,246,.2);border:1px solid rgba(59,130,246,.38);color:#93c5fd;
    font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;
    padding:4px 12px;border-radius:20px;margin:8px 0 5px}
.header p{font-size:.83rem;color:var(--muted)}

/* ALERTS */
.alert{padding:12px 15px;border-radius:10px;margin-bottom:20px;font-size:.875rem;
    display:flex;align-items:flex-start;gap:10px;animation:fadeUp .35s var(--ease) both}
.alert-s{background:rgba(0,168,107,.16);border:1px solid rgba(0,168,107,.38);color:#6ee7b7}
.alert-e{background:rgba(239,68,68,.13);border:1px solid rgba(239,68,68,.32);color:#fca5a5}
.alert i{font-size:1.05rem;flex-shrink:0;margin-top:2px}
.alert ul{margin:5px 0 0 15px}.alert ul li{margin-bottom:2px}

/* FORM */
.fg{margin-bottom:18px}
.lbl{display:block;font-size:.78rem;font-weight:600;letter-spacing:.05em;text-transform:uppercase;
    color:var(--muted);margin-bottom:8px}
.req{color:var(--green);margin-left:2px}

/* RADIO GRID */
.rg{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.rc{display:flex;align-items:center;gap:10px;background:rgba(255,255,255,.06);
    border:1px solid var(--gb);border-radius:10px;padding:11px 14px;cursor:pointer;
    transition:background .2s,border-color .2s;font-size:.89rem}
.rc:hover{background:rgba(255,255,255,.11)}
.rc input[type=radio]{width:16px;height:16px;accent-color:var(--green);flex-shrink:0}
.rc:has(input:checked){background:rgba(0,168,107,.13);border-color:rgba(0,168,107,.5);
    box-shadow:0 0 0 1px rgba(0,168,107,.2)}
.rc.fw{grid-column:1/-1}

#other-wrap{display:none;margin-top:9px;animation:fadeUp .25s var(--ease)}

/* INPUTS */
.field{width:100%;background:rgba(255,255,255,.93);border:none;border-radius:9px;
    padding:11px 14px;font-family:'DM Sans',sans-serif;font-size:.92rem;color:#1a1a1a;
    outline:none;transition:box-shadow .2s}
.field:focus{box-shadow:0 0 0 3px var(--green-glow)}
.field::placeholder{color:#999}
textarea.field{resize:vertical;min-height:85px}
.iw{position:relative}
.iw .ico{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#888;font-size:.95rem;pointer-events:none}
.iw .field{padding-left:36px}

/* FILE UPLOAD ROWS */
.fu-wrap{background:rgba(255,255,255,.06);border:1px solid var(--gb);border-radius:10px;
    padding:13px 14px;transition:background .2s;overflow:hidden;min-width:0}
.fu-wrap:hover{background:rgba(255,255,255,.10)}
.fu-row{display:flex;align-items:center;gap:10px;min-width:0}
.fu-ico{font-size:1.15rem;color:var(--green);flex-shrink:0}
.fu-name{flex:1;min-width:0;font-size:.84rem;color:var(--muted);
    white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:block}
.fu-arr{font-size:.8rem;color:var(--muted);flex-shrink:0}
input[type=file].hf{display:none}

/* GPS */
.gps-row{display:flex;align-items:center;gap:10px;background:rgba(255,255,255,.06);
    border:1px solid var(--gb);border-radius:10px;padding:11px 14px;font-size:.84rem;color:var(--muted)}
.gps-row i{color:var(--green);font-size:1.05rem;flex-shrink:0}
.gps-status{flex:1}
.gps-det{background:none;border:1px solid var(--gb);color:var(--muted);
    padding:5px 12px;border-radius:7px;cursor:pointer;font-size:.8rem;white-space:nowrap;
    transition:all .2s;font-family:'DM Sans',sans-serif}
.gps-det:hover{background:rgba(255,255,255,.1);color:#fff}

/* GRID 2 */
.g2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.g2 > *{min-width:0}

/* DIVIDER */
hr.div{border:none;border-top:1px solid var(--gb);margin:20px 0}

/* TERMS CHECKBOX */
.terms-row{display:flex;align-items:flex-start;gap:11px;
    background:rgba(255,255,255,.05);border:1px solid var(--gb);
    border-radius:10px;padding:13px 14px;margin:16px 0 12px;cursor:pointer}
.terms-row input[type=checkbox]{width:17px;height:17px;flex-shrink:0;margin-top:2px;accent-color:var(--green)}
.terms-row .terms-text{font-size:.84rem;color:rgba(255,255,255,.75);line-height:1.5}
.terms-link{color:var(--green);text-decoration:underline;cursor:pointer;font-weight:600;background:none;border:none;font-size:.84rem;padding:0}

/* SUBMIT */
.submit-btn{width:100%;background:linear-gradient(135deg,var(--green),var(--green-d));
    color:#fff;border:none;padding:15px;border-radius:11px;
    font-family:'Syne',sans-serif;font-weight:700;font-size:.97rem;letter-spacing:.04em;
    cursor:pointer;transition:filter .2s,transform .15s,box-shadow .2s;
    box-shadow:0 4px 20px rgba(0,168,107,.32);position:relative;overflow:hidden}
.submit-btn:hover{filter:brightness(1.1);transform:translateY(-2px);box-shadow:0 8px 28px rgba(0,168,107,.42)}
.spinner{display:none;width:17px;height:17px;border:2px solid rgba(255,255,255,.35);
    border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;
    position:absolute;right:18px;top:50%;margin-top:-8.5px}
@keyframes spin{to{transform:rotate(360deg)}}
.submit-btn.loading .spinner{display:block}

.bot{display:flex;justify-content:space-between;margin-top:14px;font-size:.77rem;color:var(--muted)}
.bot a{color:var(--muted);text-decoration:none;transition:color .2s}.bot a:hover{color:#fff}
.footer{text-align:center;margin-top:32px;font-size:.7rem;color:rgba(255,255,255,.22)}

/* ── MODAL ── */
.modal-bg{position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(6px);
    z-index:1000;display:none;align-items:center;justify-content:center;padding:20px}
.modal-bg.open{display:flex;animation:fadeUp .3s var(--ease)}
.modal{background:#0d2b1e;border:1px solid rgba(0,168,107,.3);border-radius:18px;
    width:100%;max-width:560px;max-height:88vh;display:flex;flex-direction:column;
    box-shadow:0 24px 64px rgba(0,0,0,.6)}
.modal-head{display:flex;justify-content:space-between;align-items:center;
    padding:20px 24px 16px;border-bottom:1px solid rgba(255,255,255,.08);flex-shrink:0}
.modal-head h2{font-family:'Syne',sans-serif;font-size:1.05rem;font-weight:700;color:#fff}
.modal-close{background:none;border:none;color:var(--muted);font-size:1.2rem;cursor:pointer;
    transition:color .2s;line-height:1}
.modal-close:hover{color:#fff}
.modal-body{overflow-y:auto;padding:20px 24px;flex:1;
    scrollbar-width:thin;scrollbar-color:rgba(0,168,107,.4) transparent}
.modal-body::-webkit-scrollbar{width:5px}
.modal-body::-webkit-scrollbar-thumb{background:rgba(0,168,107,.4);border-radius:3px}
.modal-section{margin-bottom:20px}
.modal-section h3{font-family:'Syne',sans-serif;font-size:.85rem;font-weight:700;
    color:var(--green);text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px;
    display:flex;align-items:center;gap:7px}
.modal-section p,.modal-section li{font-size:.84rem;color:rgba(255,255,255,.72);line-height:1.65}
.modal-section ul{padding-left:16px;margin-top:4px}
.modal-section ul li{margin-bottom:4px}
.law-box{background:rgba(0,168,107,.1);border:1px solid rgba(0,168,107,.28);
    border-radius:10px;padding:12px 14px;margin-top:8px}
.law-box p{font-size:.82rem;color:#6ee7b7;line-height:1.6}
.modal-foot{padding:16px 24px;border-top:1px solid rgba(255,255,255,.08);flex-shrink:0}
.accept-btn{width:100%;background:linear-gradient(135deg,var(--green),var(--green-d));
    color:#fff;border:none;padding:12px;border-radius:9px;
    font-family:'Syne',sans-serif;font-weight:700;font-size:.9rem;cursor:pointer;
    transition:filter .2s}
.accept-btn:hover{filter:brightness(1.1)}

/* SOURCE CHOICE SHEET */
.src-sheet{position:fixed;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(5px);
    z-index:1100;display:none;align-items:flex-end;justify-content:center;padding:0}
.src-sheet.open{display:flex;animation:fadeUp .28s var(--ease)}
.src-inner{background:#0d2b1e;border:1px solid rgba(0,168,107,.25);
    border-radius:18px 18px 0 0;width:100%;max-width:540px;padding:24px 24px 32px}
.src-inner h3{font-family:'Syne',sans-serif;font-size:.95rem;font-weight:700;
    text-align:center;margin-bottom:18px;color:#fff}
.src-btns{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.src-btn{display:flex;flex-direction:column;align-items:center;gap:8px;
    background:rgba(255,255,255,.07);border:1px solid var(--gb);border-radius:12px;
    padding:18px 12px;cursor:pointer;transition:background .2s,border-color .2s;color:#fff;
    font-family:'DM Sans',sans-serif;font-size:.85rem;font-weight:600}
.src-btn:hover{background:rgba(0,168,107,.14);border-color:rgba(0,168,107,.4)}
.src-btn i{font-size:1.6rem;color:var(--green)}
.src-cancel{width:100%;background:none;border:1px solid var(--gb);color:var(--muted);
    border-radius:9px;padding:10px;margin-top:12px;cursor:pointer;
    font-family:'DM Sans',sans-serif;font-size:.85rem;transition:all .2s}
.src-cancel:hover{color:#fff;border-color:rgba(255,255,255,.3)}

@media(max-width:580px){
    * { scrollbar-width: none !important; -ms-overflow-style: none !important; }
    *::-webkit-scrollbar, html::-webkit-scrollbar, body::-webkit-scrollbar, ::-webkit-scrollbar { display: none !important; width: 0 !important; height: 0 !important; background: transparent !important; }
    .nav{padding:12px 14px} 
    .nav-links{gap:8px;}
    .nav-links a:not(.lbtn){display:none;}
    .card{padding:22px 16px;border-radius:16px;}
    .g2{grid-template-columns:1fr} 
    .rg{grid-template-columns:1fr}
    .rc.fw{grid-column:auto}
    .field, input, select, textarea{font-size:16px !important;}
    .header h1{font-size:clamp(1.5rem, 6vw, 1.85rem);}
}
@media(max-width:380px){
    .nav{padding:10px 10px;}
    .logo{font-size:1.15rem;}
    .lang-switch-btn .lang-label{display:none;}
    .card{padding:18px 12px;}
}
</style>
</head>
<body>

<nav class="nav">
    <div class="logo">Agri<span>Trace+</span></div>
    <div class="nav-links" style="display:flex;align-items:center;gap:12px;">
        <button type="button" class="lang-switch-btn <?= $isTagalog ? 'active-tl' : '' ?>" onclick="toggleLanguage()" title="<?= $isTagalog ? 'Kasalukuyan: Tagalog • I-click para bumalik sa English' : 'Current: English • Click to switch to Tagalog' ?>" style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:999px;background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.2);color:#fff;cursor:pointer;font-size:0.8rem;font-weight:700;"><span class="lang-flag"><?= $isTagalog ? '🇺🇸' : '🇵🇭' ?></span> <span class="lang-label"><?= $isTagalog ? 'English' : 'Tagalog' ?></span></button>
        <a href="index.php"><?= t('Home') ?></a>
        <a href="login.php" class="lbtn"><?= t('Log In') ?></a>
    </div>
</nav>

<div class="page">
<div class="card">
    <a href="index.php" class="close-btn" title="<?= t('Back') ?>"><i class="bi bi-x-lg"></i></a>

    <div class="header">
        <h1>Agri<span>Trace+</span></h1>
        <div class="badge"><i class="bi bi-globe"></i> <?= t('Public Access') ?></div>
        <p><?= t('Submit anonymous reports for livestock health and safety') ?></p>
    </div>

    <!-- REPORT & TRACK SWITCHER -->
    <?php $isTrackingView = (!empty($trackQuery) || ($_GET['view'] ?? '') === 'track'); ?>
    <div style="display:flex;gap:8px;background:rgba(255,255,255,.06);padding:5px;border-radius:12px;margin:16px 0 22px;border:1px solid var(--gb);">
        <a href="public-report-form.php" style="flex:1;text-align:center;padding:10px 14px;border-radius:8px;font-size:0.83rem;font-weight:700;text-decoration:none;transition:all .2s;<?= !$isTrackingView ? 'background:var(--green);color:#fff;box-shadow:0 2px 8px rgba(0,168,107,.4);' : 'color:var(--muted);' ?>">
            <i class="bi bi-file-earmark-plus"></i> <?= $isTagalog ? 'Magsumite ng Ulat' : 'Submit Report' ?>
        </a>
        <a href="public-report-form.php?view=track" style="flex:1;text-align:center;padding:10px 14px;border-radius:8px;font-size:0.83rem;font-weight:700;text-decoration:none;transition:all .2s;<?= $isTrackingView ? 'background:var(--green);color:#fff;box-shadow:0 2px 8px rgba(0,168,107,.4);' : 'color:var(--muted);' ?>">
            <i class="bi bi-search"></i> <?= $isTagalog ? 'Subaybayan ang Ulat' : 'Track Your Report' ?>
        </a>
    </div>

    <?php if ($isTrackingView): ?>
    <!-- TRACKING SECTION -->
    <div style="background:rgba(255,255,255,.05);border:1px solid var(--gb);border-radius:14px;padding:22px;margin-bottom:20px;">
        <h3 style="font-family:'Syne',sans-serif;font-size:1.1rem;margin-bottom:6px;display:flex;align-items:center;gap:8px;color:#fff;">
            <i class="bi bi-shield-check" style="color:var(--green);"></i> <?= $isTagalog ? 'Subaybayan ang Katayuan ng Ulat' : 'Citizen Report Tracker' ?>
        </h3>
        <p style="font-size:0.82rem;color:var(--muted);margin-bottom:16px;">
            <?= $isTagalog ? 'Ipasok ang inyong Report Reference ID (hal. #12) o Contact Phone Number upang makita ang pinakabagong katayuan ng inyong ulat.' : 'Enter your Report Reference ID (e.g. #12) or Contact Phone Number to view real-time investigation status.' ?>
        </p>

        <form method="GET" action="public-report-form.php" style="display:flex;gap:8px;margin-bottom:18px;">
            <input type="hidden" name="view" value="track">
            <input type="text" name="track" value="<?= htmlspecialchars($trackQuery) ?>" placeholder="<?= $isTagalog ? 'Reference ID o Telepono...' : 'Reference ID or Phone (e.g. 15 or 09...)' ?>" class="txt" style="flex:1;background:rgba(255,255,255,.08);border:1px solid var(--gb);border-radius:9px;padding:12px 14px;color:#fff;font-size:.9rem;" required>
            <button type="submit" class="submit-btn" style="width:auto;padding:12px 20px;white-space:nowrap;">
                <i class="bi bi-search"></i> <?= $isTagalog ? 'Hanapin' : 'Track' ?>
            </button>
        </form>

        <?php if ($trackError): ?>
        <div class="alert alert-e" style="margin-bottom:0;">
            <i class="bi bi-exclamation-circle-fill"></i>
            <div><?= htmlspecialchars($trackError) ?></div>
        </div>
        <?php endif; ?>

        <?php if (!empty($trackResult)): ?>
        <div style="display:flex;flex-direction:column;gap:12px;margin-top:12px;">
            <?php foreach($trackResult as $tr): 
                $st = $tr['status'] ?? 'Pending';
                $stColor = match($st) {
                    'Resolved', 'Closed' => '#10b981',
                    'Reviewed' => '#3b82f6',
                    'In Progress' => '#f59e0b',
                    default => '#94a3b8'
                };
            ?>
            <div style="background:rgba(0,0,0,.25);border:1px solid rgba(255,255,255,.08);border-radius:10px;padding:14px;position:relative;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                    <span style="font-weight:700;color:var(--green);font-size:0.9rem;">
                        <i class="bi bi-hash"></i> Report #<?= $tr['id'] ?> &bull; <?= htmlspecialchars($tr['reportType'] ?? 'General') ?>
                    </span>
                    <span style="background:<?= $stColor ?>22;color:<?= $stColor ?>;border:1px solid <?= $stColor ?>55;padding:3px 9px;border-radius:6px;font-size:0.75rem;font-weight:700;">
                        <?= htmlspecialchars($st) ?>
                    </span>
                </div>
                <p style="font-size:0.83rem;color:rgba(255,255,255,.8);margin-bottom:8px;line-height:1.4;">
                    <?= nl2br(htmlspecialchars($tr['description'] ?? '')) ?>
                </p>
                <div style="font-size:0.72rem;color:var(--muted);display:flex;gap:16px;">
                    <span><i class="bi bi-calendar3"></i> Submitted: <?= date('M d, Y h:i A', strtotime($tr['createdAt'])) ?></span>
                    <span><i class="bi bi-clock-history"></i> Updated: <?= date('M d, Y h:i A', strtotime($tr['last_update'])) ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <div style="text-align:center;padding-bottom:16px;">
        <a href="public-report-form.php" style="color:var(--muted);font-size:0.83rem;text-decoration:underline;">
            <i class="bi bi-arrow-left"></i> <?= $isTagalog ? 'Bumalik sa Pagsumite ng Ulat' : 'Return to Submit New Report' ?>
        </a>
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="alert alert-s">
        <i class="bi bi-check-circle-fill"></i>
        <div>
            <strong><?= t('Report submitted successfully!') ?></strong><br>
            <?= t('Your report has been logged and will be reviewed by an agriculture officer.') ?>
            <?php if ($newId): ?> <?= t('Reference ID:') ?> <strong>#<?= htmlspecialchars((string)$newId) ?></strong><?php endif; ?>
        </div>
    </div>
    <div style="text-align:center;padding:8px 0 16px">
        <a href="public-report-form.php" style="display:inline-flex;align-items:center;gap:8px;
            background:rgba(0,168,107,.14);border:1px solid rgba(0,168,107,.38);color:#6ee7b7;
            padding:11px 22px;border-radius:10px;text-decoration:none;font-weight:600;font-size:.88rem">
            <i class="bi bi-plus-circle"></i> <?= t('Submit Another Report') ?>
        </a>
    </div>
    <?php else: ?>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-e">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <div><strong><?= t('Please fix:') ?></strong><ul><?php foreach($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
    </div>
    <?php endif; ?>

    <?php if (!$isTrackingView): ?>
    <form id="pubForm" method="POST" enctype="multipart/form-data" novalidate>

        <!-- REPORT TYPE -->
        <div class="fg">
            <label class="lbl"><?= t('Report Type') ?> <span class="req">*</span></label>
            <div class="rg">
                <?php $prev = $_POST['report_type'] ?? ''; ?>
                <?php foreach([
                    ['Sick','bi-thermometer-half','Sick livestock'],
                    ['Dead','bi-heartbreak','Dead animals'],
                    ['Stray','bi-arrow-left-right','Stray livestock'],
                    ['Others','bi-three-dots','Others'],
                    ['Disease','bi-virus2','Suspected disease outbreak'],
                ] as [$v,$ic,$lb]): ?>
                <label class="rc <?= $v==='Disease'?'fw':'' ?>">
                    <input type="radio" name="report_type" value="<?= $v ?>" <?= $prev===$v?'checked':'' ?> required>
                    <i class="bi <?= $ic ?>"></i> <?= t($lb) ?>
                </label>
                <?php endforeach; ?>
            </div>
            <div id="other-wrap">
                <input type="text" name="other_type" id="other_type" class="field"
                       placeholder="<?= t('Please specify…') ?>"
                       value="<?= htmlspecialchars($_POST['other_type'] ?? '') ?>">
            </div>
        </div>

        <!-- PHOTOS/VIDEOS -->
        <div class="fg">
            <label class="lbl"><?= t('Photos / Videos') ?> <span style="text-transform:none;font-weight:400;color:var(--muted)">(<?= t('Incident evidence • max 10 MB') ?>)</span></label>
            <div class="fu-wrap" id="wrap-media" onclick="openSheet('media')" style="cursor:pointer">
                <div class="fu-row">
                    <i class="bi bi-camera-fill fu-ico"></i>
                    <span class="fu-name" id="media-name"><?= t('No file chosen') ?></span>
                    <i class="bi bi-chevron-right fu-arr"></i>
                </div>
            </div>
            <input type="file" name="media_file" id="media_file" class="hf" accept="image/*,video/*">
            <input type="file" name="media_cam"  id="media_cam"  class="hf" accept="image/*,video/*" capture="environment">
        </div>

        <!-- DESCRIPTION -->
        <div class="fg">
            <label class="lbl" for="desc"><?= t('Description') ?> <span class="req">*</span></label>
            <textarea name="description" id="desc" class="field" rows="4"
                      placeholder="<?= t('Describe the issue, approximate location, number of animals affected…') ?>"
                      required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
        </div>

        <!-- GPS -->
        <div class="fg">
            <label class="lbl"><?= t('Location (GPS)') ?></label>
            <div class="gps-row">
                <i class="bi bi-geo-alt-fill"></i>
                <span class="gps-status" id="gps-status"><?= t('Location not captured yet') ?></span>
                <button type="button" class="gps-det" id="gps-btn">
                    <i class="bi bi-crosshair"></i> <?= t('Detect') ?>
                </button>
            </div>
            <input type="hidden" name="latitude"  id="lat_f" value="<?= htmlspecialchars($_POST['latitude']  ?? '') ?>">
            <input type="hidden" name="longitude" id="lng_f" value="<?= htmlspecialchars($_POST['longitude'] ?? '') ?>">
        </div>

        <hr class="div">

        <!-- CONTACT -->
        <div class="fg">
            <label class="lbl" for="cphone"><?= t('Contact Phone') ?> <span class="req">*</span></label>
            <div class="iw">
                <i class="bi bi-telephone ico"></i>
                <input type="tel" name="contact_phone" id="cphone" class="field"
                       placeholder="e.g. 09171234567"
                       value="<?= htmlspecialchars($_POST['contact_phone'] ?? '') ?>" required>
            </div>
        </div>

        <div class="fg">
            <label class="lbl" for="cemail"><?= t('Contact Email') ?> <span style="text-transform:none;font-weight:400;color:var(--muted)">(<?= t('(optional)') ?>)</span></label>
            <div class="iw">
                <i class="bi bi-envelope ico"></i>
                <input type="email" name="contact_email" id="cemail" class="field"
                       placeholder="you@example.com"
                       value="<?= htmlspecialchars($_POST['contact_email'] ?? '') ?>">
            </div>
        </div>

        <!-- ID + FACE PHOTO (BOTH REQUIRED) -->
        <div class="g2">
            <div class="fg">
                <label class="lbl"><?= t('ID Photo') ?> <span class="req">*</span></label>
                <div class="fu-wrap" id="wrap-id" onclick="openSheet('id')" style="cursor:pointer">
                    <div class="fu-row">
                        <i class="bi bi-person-badge fu-ico" id="id-ico"></i>
                        <span class="fu-name" id="id-name"><?= t('No file chosen') ?></span>
                        <i class="bi bi-chevron-right fu-arr"></i>
                    </div>
                </div>
                <input type="file" name="id_photo" id="id_photo" class="hf" accept="image/*">
                <input type="file" name="id_cam"   id="id_cam"   class="hf" accept="image/*" capture="environment">
            </div>
            <div class="fg">
                <label class="lbl"><?= t('Face Photo') ?> <span class="req">*</span></label>
                <div class="fu-wrap" id="wrap-face" onclick="openSheet('face')" style="cursor:pointer">
                    <div class="fu-row">
                        <i class="bi bi-person-circle fu-ico" id="face-ico"></i>
                        <span class="fu-name" id="face-name"><?= t('No file chosen') ?></span>
                        <i class="bi bi-chevron-right fu-arr"></i>
                    </div>
                </div>
                <input type="file" name="face_photo" id="face_photo" class="hf" accept="image/*">
                <input type="file" name="face_cam"   id="face_cam"   class="hf" accept="image/*" capture="user">
            </div>
        </div>

        <!-- DATA PRIVACY ACT OF 2012 NOTICE -->
        <div style="background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.28);border-radius:12px;padding:16px 18px;margin:20px 0 14px;">
            <div style="display:flex;align-items:flex-start;gap:12px;">
                <i class="bi bi-shield-lock-fill" style="color:#10b981;font-size:1.4rem;flex-shrink:0;margin-top:2px;"></i>
                <div>
                    <h4 style="font-family:'Syne',sans-serif;font-size:0.88rem;color:#6ee7b7;font-weight:700;margin-bottom:4px;">
                        <?= $isTagalog ? 'Proteksyon sa Privacy ng Impormante (Republic Act No. 10173)' : 'Whistleblower & Citizen Privacy Protection (RA 10173)' ?>
                    </h4>
                    <p style="font-size:0.79rem;color:rgba(255,255,255,.76);line-height:1.45;margin-bottom:8px;">
                        <?= $isTagalog 
                            ? 'Ang inyong contact details at larawan ng Valid ID at Mukha ay kinokolekta LAMANG para mapatunayan ang katotohanan ng ulat at maiwasan ang panlilinlang. Ito ay ligtas na naka-encrypt at MAA-ACCESS LAMANG ng mga otorisadong opisyal ng Department of Agriculture. HINDI kailanman ilalantad ang inyong mukha o personal na impormasyon sa publiko o sa mapa.' 
                            : 'Your contact details and uploaded ID / facial photos are required strictly to authenticate incident reports and eliminate malicious hoaxes. They are securely encrypted and accessed EXCLUSIVELY by accredited Department of Agriculture investigators. Personal identifying records are NEVER displayed publicly or visible on open maps.' ?>
                    </p>
                    <div style="display:flex;gap:16px;flex-wrap:wrap;font-size:0.73rem;color:#a7f3d0;font-weight:600;">
                        <span><i class="bi bi-lock-fill"></i> <?= $isTagalog ? 'Naka-encrypt na Datos' : '256-bit Encrypted' ?></span>
                        <span><i class="bi bi-eye-slash-fill"></i> <?= $isTagalog ? 'Lihim sa Publiko' : 'Redacted from Public' ?></span>
                        <span><i class="bi bi-shield-shaded"></i> <?= $isTagalog ? 'Proteksyon sa ilalim ng Batas' : 'Protected under RA 10173' ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- TERMS CHECKBOX -->
        <label class="terms-row">
            <input type="checkbox" name="terms" id="terms" required <?= isset($_POST['terms'])?'checked':'' ?>>
            <span class="terms-text">
                <?= t('I confirm this report is accurate and genuine. I have read and agree to the') ?>
                <button type="button" class="terms-link" onclick="event.preventDefault();openTerms()">
                    <?= t('Terms & Conditions') ?>
                </button>
                <?= t('and understand the applicable laws on filing reports.') ?>
            </span>
        </label>

        <button type="submit" class="submit-btn" id="submitBtn">
            <i class="bi bi-send-fill"></i> <?= t('SUBMIT REPORT') ?>
            <span class="spinner"></span>
        </button>

        <div class="bot">
            <a href="login.php"><i class="bi bi-lock"></i> <?= t('Log in for full access') ?></a>
            <a href="mailto:support@agritrace.ph"><?= t('Need help?') ?></a>
        </div>
    </form>
    <?php endif; ?>
    <?php endif; // end !isTrackingView ?>

    <div class="footer">&copy; 2026 AgriTrace Technologies</div>
</div>
</div>

<!-- ═══════════════════════════════════════════════
     TERMS & CONDITIONS MODAL
═══════════════════════════════════════════════ -->
<div class="modal-bg" id="termsModal">
<div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <div class="modal-head">
        <h2 id="modalTitle"><i class="bi bi-shield-check" style="color:var(--green);margin-right:7px"></i>Terms &amp; Conditions for Report Submission</h2>
        <button class="modal-close" onclick="closeTerms()" aria-label="Close">&times;</button>
    </div>
    <div class="modal-body">

        <div class="modal-section">
            <h3><i class="bi bi-info-circle"></i> Purpose of This Form</h3>
            <p>This public reporting form allows citizens to submit anonymous reports on livestock health concerns, disease outbreaks, stray animals, and related issues to the Municipal Agriculture Office (MAO) of Camarines Sur — 1st District.</p>
        </div>

        <div class="modal-section">
            <h3><i class="bi bi-exclamation-triangle"></i> Accuracy of Reports</h3>
            <ul>
                <li>All information submitted must be truthful and based on personal observation or credible knowledge.</li>
                <li>Do not submit duplicate, fabricated, or malicious reports intended to harm individuals or businesses.</li>
                <li>Providing false information may expose you to legal liability under applicable Philippine laws.</li>
            </ul>
        </div>

        <div class="modal-section">
            <h3><i class="bi bi-file-earmark-text"></i> Applicable Laws — Filing False Reports</h3>
            <div class="law-box">
                <p><strong>Revised Penal Code of the Philippines (Art. 183 — Perjury &amp; Art. 154 — Alarms and Scandals):</strong> Deliberately filing a false complaint or fabricated report may constitute perjury or alarm under the RPC and carry penalties of imprisonment or fines.</p>
            </div>
            <div class="law-box" style="margin-top:8px">
                <p><strong>Republic Act No. 10175 — Cybercrime Prevention Act of 2012:</strong> Submitting fraudulent reports through electronic means (including web forms) may be considered cyber-libel or online fraud, punishable by imprisonment of up to 12 years.</p>
            </div>
            <div class="law-box" style="margin-top:8px">
                <p><strong>Republic Act No. 9003 — Ecological Solid Waste Management Act &amp; related agriculture regulations:</strong> Misreporting livestock disease or animal mortality can disrupt official response operations and carries administrative sanctions.</p>
            </div>
        </div>

        <div class="modal-section">
            <h3><i class="bi bi-lock"></i> Privacy &amp; Reporter Protection</h3>
            <ul>
                <li>Your identity and contact details are kept strictly confidential and are accessible only to authorized Agriculture Officials.</li>
                <li>Reports are processed anonymously in public dashboards — your name, phone, and photos are never displayed publicly.</li>
                <li>You are protected under <strong>Republic Act No. 10173 — Data Privacy Act of 2012</strong>. Your personal data will not be shared with third parties without your consent.</li>
                <li>Whistleblower protections may apply under <strong>RA 6713 (Code of Conduct and Ethical Standards)</strong> for reports filed in good faith.</li>
            </ul>
        </div>

        <div class="modal-section">
            <h3><i class="bi bi-camera"></i> Photo &amp; Media Requirements</h3>
            <ul>
                <li>Photos submitted should clearly depict the reported animal(s) or condition.</li>
                <li>ID photos are required to verify reporter identity and reduce fraudulent submissions — they are stored securely and never published.</li>
                <li>Uploading photos of other individuals without their consent may violate the Data Privacy Act.</li>
            </ul>
        </div>

        <div class="modal-section">
            <h3><i class="bi bi-geo-alt"></i> Location Data</h3>
            <p>GPS coordinates captured via your device are used solely to dispatch agriculture officers to the correct location. Location data is not sold or shared with external parties.</p>
        </div>

        <div class="modal-section">
            <h3><i class="bi bi-check2-circle"></i> By Submitting You Agree That</h3>
            <ul>
                <li>The information provided is true and correct to the best of your knowledge.</li>
                <li>You consent to the MAO contacting you on the provided phone/email for follow-up.</li>
                <li>You understand that filing false reports is a punishable offense under Philippine law.</li>
                <li>Your identity will be protected and handled in accordance with RA 10173.</li>
            </ul>
        </div>

    </div>
    <div class="modal-foot">
        <button class="accept-btn" onclick="acceptTerms()">
            <i class="bi bi-check-circle-fill"></i> I Understand &amp; Accept
        </button>
    </div>
</div>
</div>

<!-- ═══════════════════════════════════════════════
     SOURCE CHOOSER BOTTOM SHEET
═══════════════════════════════════════════════ -->
<div class="src-sheet" id="srcSheet">
<div class="src-inner">
    <h3 id="src-title">Choose Source</h3>
    <div class="src-btns">
        <button class="src-btn" id="src-cam-btn">
            <i class="bi bi-camera-fill"></i>
            Open Camera<br><small style="font-weight:400;color:var(--muted)">+ auto-pin location</small>
        </button>
        <button class="src-btn" id="src-file-btn">
            <i class="bi bi-image"></i>
            Choose from Files<br><small style="font-weight:400;color:var(--muted)">Photo / video</small>
        </button>
    </div>
    <button class="src-cancel" onclick="closeSheet()">Cancel</button>
</div>
</div>

<script>
// ── GPS ──────────────────────────────────────────────────────────────────
const latF = document.getElementById('lat_f');
const lngF = document.getElementById('lng_f');
const gpsSt = document.getElementById('gps-status');
const gpsBtn = document.getElementById('gps-btn');

if (latF.value && lngF.value) {
    gpsSt.innerHTML = `<span style="color:#6ee7b7">📍 ${parseFloat(latF.value).toFixed(5)}, ${parseFloat(lngF.value).toFixed(5)}</span>`;
}

function detectGPS(cb) {
    if (!navigator.geolocation) { gpsSt.textContent = 'Geolocation not supported.'; return; }
    gpsSt.textContent = 'Detecting…';
    navigator.geolocation.getCurrentPosition(pos => {
        const lat = pos.coords.latitude.toFixed(7);
        const lng = pos.coords.longitude.toFixed(7);
        latF.value = lat; lngF.value = lng;
        gpsSt.innerHTML = `<span style="color:#6ee7b7">📍 ${lat}, ${lng}</span>`;
        if (cb) cb();
    }, err => {
        gpsSt.textContent = 'Location error: ' + err.message;
    }, { enableHighAccuracy: true, timeout: 10000 });
}

gpsBtn.addEventListener('click', () => detectGPS());

// ── FILE SOURCE SHEET ─────────────────────────────────────────────────────
let currentTarget = '';
const sheet = document.getElementById('srcSheet');

function getSheetTitle(target) {
    const isTl = (window.getAgriTraceLanguage && window.getAgriTraceLanguage() === 'tl') || document.documentElement.getAttribute('lang') === 'tl';
    if (target === 'media') return isTl ? 'Magdagdag ng Larawan / Video' : 'Add Photo / Video';
    if (target === 'id')    return isTl ? 'Mag-upload ng Larawan ng ID' : 'Upload ID Photo';
    if (target === 'face')  return isTl ? 'Mag-upload ng Larawan ng Mukha' : 'Upload Face Photo';
    return isTl ? 'Pumili ng Pagmumulan' : 'Choose Source';
}

// Map: target → { camId, fileId, labelId }
const srcMap = {
    media: { camId:'media_cam', fileId:'media_file', labelId:'media-name' },
    id:    { camId:'id_cam',    fileId:'id_photo',   labelId:'id-name' },
    face:  { camId:'face_cam',  fileId:'face_photo', labelId:'face-name' },
};

function openSheet(target) {
    currentTarget = target;
    document.getElementById('src-title').textContent = getSheetTitle(target);
    sheet.classList.add('open');
}
function openSheetFile(target) {
    currentTarget = target;
    document.getElementById(srcMap[target].fileId).click();
}
function closeSheet() { sheet.classList.remove('open'); }

document.getElementById('src-cam-btn').addEventListener('click', () => {
    const cfg = srcMap[currentTarget];
    closeSheet();
    detectGPS(() => {});
    // Use the capture input for camera
    document.getElementById(cfg.camId).click();
});

document.getElementById('src-file-btn').addEventListener('click', () => {
    const cfg = srcMap[currentTarget];
    closeSheet();
    document.getElementById(cfg.fileId).click();
});

// Close sheet when clicking backdrop
sheet.addEventListener('click', e => { if (e.target === sheet) closeSheet(); });

// File label binding & highlight
function bindLabel(inputId, labelId, wrapId) {
    document.getElementById(inputId)?.addEventListener('change', function () {
        if (this.files && this.files.length) {
            const f = this.files[0];
            const sz = (f.size / (1024 * 1024)).toFixed(2);
            document.getElementById(labelId).textContent = `✓ ${f.name} (${sz} MB)`;
            const wrap = document.getElementById(wrapId);
            if (wrap) {
                wrap.style.borderColor = '#10b981';
                wrap.style.background = 'rgba(16, 185, 129, 0.14)';
                wrap.style.boxShadow = '0 0 0 1px rgba(16, 185, 129, 0.3)';
            }
            // If both mandatory ID & Face photos are now provided, clear error box
            const hasId = (document.getElementById('id_photo')?.files?.length > 0) || (document.getElementById('id_cam')?.files?.length > 0);
            const hasFace = (document.getElementById('face_photo')?.files?.length > 0) || (document.getElementById('face_cam')?.files?.length > 0);
            if (hasId && hasFace) {
                const errBox = document.getElementById('client-upload-error');
                if (errBox) errBox.remove();
            }
        }
    });
}
bindLabel('media_file', 'media-name', 'wrap-media');
bindLabel('media_cam',  'media-name', 'wrap-media');
bindLabel('id_photo',   'id-name',    'wrap-id');
bindLabel('id_cam',     'id-name',    'wrap-id');
bindLabel('face_photo', 'face-name',  'wrap-face');
bindLabel('face_cam',   'face-name',  'wrap-face');

// ── RADIO OTHERS TOGGLE ───────────────────────────────────────────────────
const otherWrap  = document.getElementById('other-wrap');
const otherInput = document.getElementById('other_type');
function toggleOther() {
    const sel = document.querySelector('input[name="report_type"]:checked');
    const isOther = sel && sel.value === 'Others';
    otherWrap.style.display = isOther ? 'block' : 'none';
    otherInput.required = isOther;
    if (!isOther) otherInput.value = '';
}
document.querySelectorAll('input[name="report_type"]').forEach(r => r.addEventListener('change', toggleOther));
toggleOther();

// ── TERMS MODAL ───────────────────────────────────────────────────────────
const modal    = document.getElementById('termsModal');
const termsChk = document.getElementById('terms');

function openTerms()  { modal.classList.add('open'); }
function closeTerms() { modal.classList.remove('open'); }
function acceptTerms(){ termsChk.checked = true; closeTerms(); }

modal.addEventListener('click', e => { if (e.target === modal) closeTerms(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeTerms(); closeSheet(); } });

// ── SUBMIT VALIDATION & SPINNER (STRICT ID + FACE MANDATORY) ──────────────
document.getElementById('pubForm')?.addEventListener('submit', function (e) {
    const hasId    = (document.getElementById('id_photo')?.files?.length > 0) || (document.getElementById('id_cam')?.files?.length > 0);
    const hasFace  = (document.getElementById('face_photo')?.files?.length > 0) || (document.getElementById('face_cam')?.files?.length > 0);

    if (!hasId || !hasFace) {
        e.preventDefault();
        e.stopPropagation();

        const isTl = (window.getAgriTraceLanguage && window.getAgriTraceLanguage() === 'tl') || document.documentElement.getAttribute('lang') === 'tl';
        const missing = [];
        if (!hasId)   missing.push(isTl ? 'Larawan ng ID (ID Photo)' : 'ID Photo');
        if (!hasFace) missing.push(isTl ? 'Larawan ng Mukha (Face Photo)' : 'Face Photo');

        let errBox = document.getElementById('client-upload-error');
        if (!errBox) {
            errBox = document.createElement('div');
            errBox.id = 'client-upload-error';
            errBox.className = 'alert alert-e';
            const form = document.getElementById('pubForm');
            form.insertBefore(errBox, form.firstChild);
        }

        const titleText = isTl ? 'Kailangan ang Upload:' : 'Upload Required:';
        const descText  = isTl
            ? 'Kinakailangang mag-upload ng parehong <strong>Larawan ng ID</strong> at <strong>Larawan ng Mukha</strong> bago maipasa ang ulat.<br>Kulang: ' + missing.join(' at ')
            : 'Both a valid <strong>ID Photo</strong> and <strong>Face Photo</strong> are strictly required before submitting this report.<br>Missing: ' + missing.join(' and ');

        errBox.innerHTML = `<i class="bi bi-exclamation-triangle-fill"></i><div><strong>${titleText}</strong> ${descText}</div>`;
        errBox.scrollIntoView({ behavior: 'smooth', block: 'center' });

        const wrapId   = document.getElementById('wrap-id');
        const wrapFace = document.getElementById('wrap-face');
        if (!hasId && wrapId) {
            wrapId.style.borderColor = '#ef4444';
            wrapId.style.boxShadow = '0 0 0 2px rgba(239, 68, 68, 0.35)';
        }
        if (!hasFace && wrapFace) {
            wrapFace.style.borderColor = '#ef4444';
            wrapFace.style.boxShadow = '0 0 0 2px rgba(239, 68, 68, 0.35)';
        }

        const btn = document.getElementById('submitBtn');
        btn.classList.remove('loading');
        btn.disabled = false;
        return false;
    }

    const btn = document.getElementById('submitBtn');
    btn.classList.add('loading');
    btn.disabled = true;
});
</script>
<script src="<?= function_exists('app_url') ? app_url('assets/js/translator.js') : 'assets/js/translator.js' ?>"></script>
<script>
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