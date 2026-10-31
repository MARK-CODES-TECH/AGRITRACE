<?php
require_once __DIR__ . '/init.php';
$googleMapsApiKey = defined('GOOGLE_MAPS_API_KEY') ? GOOGLE_MAPS_API_KEY : '';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['PHP_SELF']));
    exit;
}

$user_role = $_SESSION['user_role'] ?? '';
if (!in_array($user_role, ['Admin', 'Agriculture Official'], true)) {
    header('Location: dashboard.php');
    exit;
}

$user_id = (int)($_SESSION['user_id'] ?? 0);

$stats = [
    'approved_farms'  => $pdo->query("SELECT COUNT(*) FROM farms WHERE status = 'Approved'")->fetchColumn(),
    'pending_farms'   => $pdo->query("SELECT COUNT(*) FROM farms WHERE status = 'Pending'")->fetchColumn(),
    'active_incidents'=> $pdo->query("SELECT COUNT(*) FROM incidents WHERE status IN ('Pending', 'In Progress')")->fetchColumn(),
    'pending_reports' => $pdo->query("SELECT COUNT(*) FROM public_reports WHERE status = 'Pending'")->fetchColumn(),
    'total_livestock' => $pdo->query("SELECT COALESCE(SUM(qty), 0) FROM livestock")->fetchColumn(),
];

$livestock_by_type = $pdo->query("
    SELECT type, COALESCE(SUM(qty), 0) as total
    FROM livestock
    GROUP BY type
    ORDER BY total DESC
")->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query("
    SELECT i.*, f.name as farm_name, u.firstName, u.lastName
    FROM incidents i
    LEFT JOIN farms f ON i.farmId = f.id
    LEFT JOIN users u ON i.reporterId = u.id
    ORDER BY i.createdAt DESC LIMIT 5
");
$recent_incidents = $stmt->fetchAll();

$stmt = $pdo->query("
    SELECT f.*, u.firstName, u.lastName,
           (SELECT COALESCE(SUM(qty), 0) FROM livestock WHERE farmId = f.id) as total_livestock
    FROM farms f
    LEFT JOIN users u ON f.ownerId = u.id
    ORDER BY f.createdAt DESC
");
$all_farms = $stmt->fetchAll();

if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST" && isset($_POST['action'])) {
    header('Content-Type: application/json');
    try {
        $pdo->beginTransaction();
        if ($_POST['action'] === 'approve_farm') {
            $farm_id = (int)$_POST['farm_id'];
            $stmt = $pdo->prepare("UPDATE farms SET status = 'Approved', approved_by = ?, approved_at = CURRENT_TIMESTAMP WHERE id = ? AND status = 'Pending'");
            $result = $stmt->execute([$user_id, $farm_id]);
            if ($result && $stmt->rowCount() > 0) {
                // Notify the farm owner
                $fStmt = $pdo->prepare("SELECT ownerId, name FROM farms WHERE id = ?");
                $fStmt->execute([$farm_id]);
                $fRow = $fStmt->fetch(PDO::FETCH_ASSOC);
                if ($fRow && !empty($fRow['ownerId'])) {
                    try {
                        $pdo->prepare("INSERT INTO notifications(user_id, type, title, message, farm_id, priority, is_read, created_at) VALUES (?, 'FARM_APPROVED', ?, ?, ?, 'Medium', 0, NOW())")
                            ->execute([
                                $fRow['ownerId'],
                                "✅ Farm Registration Approved",
                                "Your farm application for \"" . $fRow['name'] . "\" has been officially APPROVED by the Department of Agriculture.",
                                $farm_id
                            ]);
                    } catch(Exception $eN) {}
                }
                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Farm approved successfully!']);
            }
            else throw new Exception('Farm not found or already processed');
        } elseif ($_POST['action'] === 'reject_farm') {
            $farm_id = (int)$_POST['farm_id'];
            $stmt = $pdo->prepare("UPDATE farms SET status = 'Rejected', rejected_by = ?, rejected_at = CURRENT_TIMESTAMP WHERE id = ? AND status = 'Pending'");
            $result = $stmt->execute([$user_id, $farm_id]);
            if ($result && $stmt->rowCount() > 0) {
                $fStmt = $pdo->prepare("SELECT ownerId, name FROM farms WHERE id = ?");
                $fStmt->execute([$farm_id]);
                $fRow = $fStmt->fetch(PDO::FETCH_ASSOC);
                if ($fRow && !empty($fRow['ownerId'])) {
                    try {
                        $pdo->prepare("INSERT INTO notifications(user_id, type, title, message, farm_id, priority, is_read, created_at) VALUES (?, 'FARM_REJECTED', ?, ?, ?, 'High', 0, NOW())")
                            ->execute([
                                $fRow['ownerId'],
                                "⚠️ Farm Registration Update",
                                "Your farm registration for \"" . $fRow['name'] . "\" requires attention or was not approved. Please consult your municipal DA officer.",
                                $farm_id
                            ]);
                    } catch(Exception $eN) {}
                }
                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Farm rejected successfully!']);
            }
            else throw new Exception('Farm not found or already processed');
        } elseif ($_POST['action'] === 'update_incident_priority') {
            $incident_id = (int)$_POST['incident_id'];
            $new_priority = $_POST['priority'];
            if (!in_array($new_priority, ['Low','Medium','High','Critical'])) throw new Exception('Invalid priority');
            $stmt = $pdo->prepare("UPDATE incidents SET priority = ?, updatedAt = CURRENT_TIMESTAMP WHERE id = ?");
            $result = $stmt->execute([$new_priority, $incident_id]);
            if ($result && $stmt->rowCount() > 0) { $pdo->commit(); echo json_encode(['success' => true, 'message' => 'Priority updated!']); }
            else throw new Exception('Incident not found');
        } elseif ($_POST['action'] === 'update_report_priority') {
            $report_id = (int)$_POST['report_id'];
            $new_status = $_POST['status'];
            if (!in_array($new_status, ['Pending','Reviewed','Resolved','Closed'])) throw new Exception('Invalid status');
            $stmt = $pdo->prepare("UPDATE public_reports SET status = ? WHERE id = ?");
            $result = $stmt->execute([$new_status, $report_id]);
            if ($result && $stmt->rowCount() > 0) { $pdo->commit(); echo json_encode(['success' => true, 'message' => 'Report status updated!']); }
            else throw new Exception('Report not found');
        } elseif ($_POST['action'] === 'resolve_incident') {
            $incident_id = (int)$_POST['incident_id'];
            $stmt = $pdo->prepare("UPDATE incidents SET status = 'Resolved', resolvedAt = CURRENT_TIMESTAMP WHERE id = ?");
            $result = $stmt->execute([$incident_id]);
            if ($result && $stmt->rowCount() > 0) {
                $iStmt = $pdo->prepare("SELECT reporterId, title FROM incidents WHERE id = ?");
                $iStmt->execute([$incident_id]);
                $iRow = $iStmt->fetch(PDO::FETCH_ASSOC);
                if ($iRow && !empty($iRow['reporterId'])) {
                    try {
                        $pdo->prepare("INSERT INTO notifications(user_id, type, title, message, incident_id, priority, is_read, created_at) VALUES (?, 'INFO', ?, ?, ?, 'Medium', 0, NOW())")
                            ->execute([
                                $iRow['reporterId'],
                                "🛡️ Incident Resolved: " . substr($iRow['title'], 0, 30),
                                "Your reported incident \"" . $iRow['title'] . "\" has been resolved and closed by the DA team.",
                                $incident_id
                            ]);
                    } catch(Exception $eN) {}
                }
                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Incident resolved!']);
            }
            else throw new Exception('Incident not found');
        } elseif ($_POST['action'] === 'mark_da_notif_read') {
            $nid = (int)($_POST['notif_id'] ?? 0);
            if ($nid > 0) {
                $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?")->execute([$nid, $user_id]);
            }
            $pdo->commit();
            $rem = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id=$user_id AND is_read=0")->fetchColumn();
            echo json_encode(['success' => true, 'unread_count' => $rem]);
            exit;
        } elseif ($_POST['action'] === 'mark_all_da_notifs_read') {
            $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$user_id]);
            $pdo->commit();
            echo json_encode(['success' => true, 'unread_count' => 0]);
            exit;
        } elseif ($_POST['action'] === 'update_profile') {
            $uid = (int)$_POST['user_id'];
            $profile_pic_path = $user_profile['profile_pix'] ?? null;
            if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/profiles/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg','jpeg','png','gif'])) {
                    $fn = $uid . '_' . time() . '.' . $ext;
                    if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $upload_dir . $fn)) {
                        $profile_pic_path = $upload_dir . $fn;
                    }
                }
            }
            $stmt = $pdo->prepare("UPDATE users SET firstName=?, lastName=?, mobile=?, updatedAt=CURRENT_TIMESTAMP WHERE id=?");
            $stmt->execute([$_POST['firstName'], $_POST['lastName'], $_POST['mobile'] ?? null, $uid]);
            $stmt = $pdo->prepare("INSERT INTO officer_profiles (user_id,gov_id,department,position,office,assigned_region,municipality,province,profile_pix) VALUES (?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE gov_id=VALUES(gov_id),department=VALUES(department),position=VALUES(position),office=VALUES(office),assigned_region=VALUES(assigned_region),municipality=VALUES(municipality),province=VALUES(province),profile_pix=VALUES(profile_pix),updated_at=CURRENT_TIMESTAMP");
            $stmt->execute([$uid,$_POST['gov_id']??null,$_POST['department']??null,$_POST['position']??null,$_POST['office']??null,$_POST['assigned_region']??null,$_POST['municipality']??null,$_POST['province']??null,$profile_pic_path]);
            if (!empty($_POST['new_password'])) {
                $h = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$h, $uid]);
            }
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Profile updated successfully!']);
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

$stmt = $pdo->query("
    SELECT i.*, f.name as farm_name
    FROM incidents i
    LEFT JOIN farms f ON i.farmId = f.id
    ORDER BY i.priority DESC, i.createdAt DESC
");
$all_incidents = $stmt->fetchAll();

$stmt = $pdo->query("SELECT * FROM public_reports ORDER BY createdAt DESC LIMIT 10");
$public_reports = $stmt->fetchAll();

// Public reports with GPS for map pins
try {
    $stmt = $pdo->query("SELECT id, reportType, description, contactPhone, status, latitude, longitude, createdAt FROM public_reports WHERE latitude IS NOT NULL AND longitude IS NOT NULL ORDER BY createdAt DESC");
    $map_public_reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $map_public_reports = [];
}

$stmt = $pdo->prepare("
    SELECT u.*, p.gov_id, p.department, p.position, p.office,
           p.assigned_region, p.municipality, p.province, p.profile_pix
    FROM users u
    LEFT JOIN officer_profiles p ON u.id = p.user_id
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$user_profile = $stmt->fetch();
$user_profile['mobile'] = $user_profile['mobile'] ?? '';
$user_name    = $user_profile ? ($user_profile['firstName'] . ' ' . substr($user_profile['lastName'], 0, 1)) : 'G';
$user_initial = strtoupper(substr($user_name, 0, 1));
$user_fullname= $user_profile['firstName'] ?? 'Guest User';

$analytics = [
    'livestock_by_type' => [],
    'regional_stats'    => [],
    'totals'            => ['total_farms' => 0, 'total_livestock' => 0, 'total_incidents' => 0, 'active_incidents' => 0],
];
try {
    $analytics['totals']['total_farms']      = $pdo->query("SELECT COUNT(*) FROM farms")->fetchColumn();
    $analytics['totals']['total_livestock']  = $pdo->query("SELECT COALESCE(SUM(qty), 0) FROM livestock")->fetchColumn();
    $analytics['totals']['total_incidents']  = $pdo->query("SELECT COUNT(*) FROM incidents")->fetchColumn();
    $analytics['totals']['active_incidents'] = $pdo->query("SELECT COUNT(*) FROM incidents WHERE status IN ('Pending', 'In Progress')")->fetchColumn();
    $analytics['livestock_by_type'] = $pdo->query("
        SELECT type, SUM(qty) as total_qty, COUNT(DISTINCT farmId) as farm_count
        FROM livestock GROUP BY type ORDER BY total_qty DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    $analytics['regional_stats'] = $pdo->query("
        SELECT COALESCE(op.assigned_region, 'General') as region,
               COUNT(DISTINCT f.id) as farm_count,
               COALESCE(SUM(l.qty), 0) as livestock_count
        FROM farms f
        LEFT JOIN officer_profiles op ON f.ownerId = op.user_id
        LEFT JOIN livestock l ON f.id = l.farmId
        GROUP BY region ORDER BY livestock_count DESC LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log($e->getMessage()); }

// ── EXTRA ANALYTICS (farm status, incident status/priority, top farms) ─────────
$an_extra = ['farm_status' => [], 'incident_status' => [], 'incident_priority' => [], 'top_farms' => []];
try {
    foreach ($pdo->query("SELECT status, COUNT(*) AS c FROM farms GROUP BY status")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $an_extra['farm_status'][$r['status'] ?: 'Unknown'] = (int)$r['c'];
    }
    foreach ($pdo->query("SELECT status, COUNT(*) AS c FROM incidents GROUP BY status")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $an_extra['incident_status'][$r['status'] ?: 'Unknown'] = (int)$r['c'];
    }
    foreach ($pdo->query("SELECT priority, COUNT(*) AS c FROM incidents GROUP BY priority")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $an_extra['incident_priority'][$r['priority'] ?: 'Unknown'] = (int)$r['c'];
    }
    $an_extra['top_farms'] = $pdo->query("
        SELECT f.id, f.name, f.status, COALESCE(SUM(l.qty), 0) AS heads
        FROM farms f LEFT JOIN livestock l ON l.farmId = f.id
        GROUP BY f.id, f.name, f.status
        ORDER BY heads DESC LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log($e->getMessage()); }
// Emojis matching farm registration animal picker exactly
$type_emojis = [
  'Cattle'  => '🐄', 'Swine' => '🐖', 'Poultry' => '🐓',
  'Goat'    => '🐐', 'Sheep' => '🐑', 'Horse'   => '🐴',
  'Rabbit'  => '🐇', 'Carabao' => '🐃', 'Buffalo' => '🐃',
  'Other'   => '🐾',
];
$type_colors = [
    'Cattle'  => '#3B82F6', 'Swine'   => '#F59E0B', 'Poultry' => '#EC4899',
    'Goat'    => '#8B5CF6', 'Sheep'   => '#10B981',  'Horse'   => '#F97316',
    'Rabbit'  => '#EF4444', 'Carabao' => '#6B7280',  'Buffalo' => '#6B7280',
    'Other'   => '#64748B',
];
$type_display = ['Buffalo' => 'Carabao'];

// ── YEARLY LIVESTOCK TREND (for line chart) ───────────────────────────────────
$yearly_trend = [];
try {
  $trend_stmt = $pdo->query("
    SELECT
      YEAR(f.createdAt) as yr,
      l.type,
      COALESCE(SUM(l.qty),0) as total
    FROM livestock l
    JOIN farms f ON l.farmId = f.id
    WHERE YEAR(f.createdAt) >= YEAR(CURDATE()) - 5
    GROUP BY yr, l.type
    ORDER BY yr ASC, total DESC
  ");
  foreach ($trend_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $yearly_trend[$row['yr']][$row['type']] = (int)$row['total'];
  }
} catch (PDOException $e) { error_log($e->getMessage()); }
 
// ── ARCHIVE — resolved/closed incidents & reports ─────────────────────────────
$archived_incidents = $pdo->query("
  SELECT i.*, f.name as farm_name
  FROM incidents i
  LEFT JOIN farms f ON i.farmId = f.id
  WHERE i.status IN ('Resolved','Closed')
  ORDER BY i.resolvedAt DESC LIMIT 50
")->fetchAll();
 
$archived_reports = $pdo->query("
  SELECT * FROM public_reports
  WHERE status IN ('Resolved','Closed')
  ORDER BY createdAt DESC LIMIT 50
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>AgriTrace+ | Official Panel</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800;1,9..40,400&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    :root {
      --c-brand-dark: #1A4731; --c-brand-mid: #2D6A4F; --c-brand-accent: #10B981;
      --c-bg-page: #F0F4F2; --c-bg-card: #ffffff; --c-slate-50: #F1F5F9;
      --c-slate-100: #E2E8F0; --c-slate-200: #CBD5E1; --c-text-main: #1E293B;
      --c-text-sub: #64748B; --c-danger: #EF4444; --c-warning: #F59E0B;
      --c-success: #10B981; --sidebar-width: 280px; --topbar-height: 64px;
    }
    * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; margin: 0; padding: 0; }
    html {
      scroll-behavior: smooth;
      -webkit-text-size-adjust: 100%;
      text-size-adjust: 100%;
      overflow-x: hidden;
      width: 100%;
    }
    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--c-bg-page);
      color: var(--c-text-main);
      overflow-x: hidden;
      width: 100%;
      max-width: 100vw;
      -webkit-font-smoothing: antialiased;
    }
    h1, h2, h3, h4, h5, h6, p, span, a, label, strong, em, td, th {
      overflow-wrap: break-word;
      word-wrap: break-word;
    }

    /* SIDEBAR */
    .panel-sidebar { width: var(--sidebar-width); height: 100vh; position: fixed; top: 0; left: calc(-1 * var(--sidebar-width)); background: var(--c-brand-dark); display: flex; flex-direction: column; transition: transform 0.3s cubic-bezier(0.4,0,0.2,1); z-index: 1000; }
    .panel-sidebar.open { transform: translateX(var(--sidebar-width)); box-shadow: 10px 0 30px rgba(0,0,0,0.2); }
    .sidebar-header { padding: 0 24px; height: var(--topbar-height); display: flex; align-items: center; flex-shrink: 0; border-bottom: 1px solid rgba(255,255,255,0.08); }
    .sidebar-logo { font-family: 'DM Sans', sans-serif; font-size: 1.5rem; font-weight: 800; color: #fff; letter-spacing: -1px; }
    .sidebar-logo span { color: var(--c-brand-accent); }
    .sidebar-sub { font-size: 0.65rem; color: rgba(255,255,255,0.5); text-transform: uppercase; letter-spacing: 1.5px; font-weight: 700; margin-top: 2px; }
    .panel-sidebar, .sidebar-nav, #sidebar { scrollbar-width: none !important; -ms-overflow-style: none !important; }
    .panel-sidebar::-webkit-scrollbar, .sidebar-nav::-webkit-scrollbar, #sidebar::-webkit-scrollbar { display: none !important; width: 0 !important; height: 0 !important; background: transparent !important; -webkit-appearance: none !important; }
    .panel-sidebar::-webkit-scrollbar-thumb, .sidebar-nav::-webkit-scrollbar-thumb, #sidebar::-webkit-scrollbar-thumb { display: none !important; width: 0 !important; height: 0 !important; background: transparent !important; }
    .panel-sidebar::-webkit-scrollbar-track, .sidebar-nav::-webkit-scrollbar-track, #sidebar::-webkit-scrollbar-track { display: none !important; background: transparent !important; }
    .panel-sidebar::-webkit-scrollbar-button, .sidebar-nav::-webkit-scrollbar-button, #sidebar::-webkit-scrollbar-button { display: none !important; width: 0 !important; height: 0 !important; }
    .sidebar-nav { flex: 1; overflow-y: auto; overflow-x: hidden; padding: 14px 12px; scrollbar-width: none !important; -ms-overflow-style: none !important; }
    .nav-section-label { font-size: 0.65rem; color: rgba(255,255,255,0.35); text-transform: uppercase; letter-spacing: 1.5px; font-weight: 700; padding: 10px 14px 4px; }
    .nav-item { display: flex; align-items: center; gap: 12px; padding: 9px 12px; margin-bottom: 3px; border-radius: 11px; color: rgba(255,255,255,0.65); text-decoration: none; cursor: pointer; transition: all 0.18s; font-size: 0.88rem; font-weight: 600; outline: none; border: 1px solid transparent; }
    .nav-item i { font-size: 1.1rem; flex-shrink: 0; width: 20px; text-align: center; }
    .nav-item:hover:not(.active) { background: rgba(255,255,255,0.08); color: #fff; transform: translateX(3px); }
    .nav-item.active { background: linear-gradient(135deg, #10b981, #059669); color: #fff; box-shadow: 0 8px 18px -8px rgba(16,185,129,.7); font-weight: 700; border-color: rgba(255,255,255,0.15); }
    .nav-divider { height: 1px; background: rgba(255,255,255,0.08); margin: 12px; }
    .nav-item.logout { color: rgba(239,68,68,0.8); }
    .nav-item.logout:hover { background: rgba(239,68,68,0.15); color: #EF4444; }
    .sidebar-footer { padding: 16px; background: rgba(0,0,0,0.2); border-top: 1px solid rgba(255,255,255,0.08); flex-shrink: 0; }
    .user-box { display: flex; align-items: center; gap: 12px; }
    .user-avatar { width: 40px; height: 40px; background: var(--c-brand-accent); color: #fff; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.95rem; flex-shrink: 0; }
    .user-name { font-weight: 700; font-size: 0.88rem; color: #fff; }
    .user-role { font-size: 0.72rem; color: rgba(255,255,255,0.5); font-weight: 500; }

    /* TOPBAR */
    .topbar { position: fixed; top: 0; right: 0; left: 0; height: var(--topbar-height); background: var(--c-brand-dark); display: flex; align-items: center; justify-content: space-between; padding: 0 20px; z-index: 900; }
    .topbar-title { font-family: 'DM Sans', sans-serif; font-size: 1rem; font-weight: 700; color: #fff; letter-spacing: 0.3px; }
    .topbar-right { display: flex; align-items: center; gap: 12px; }
    .topbar-avatar { width: 36px; height: 36px; background: var(--c-brand-accent); color: #fff; border-radius: 9px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.9rem; }
    .menu-btn { background: none; border: none; font-size: 1.5rem; color: #fff; cursor: pointer; display: flex; align-items: center; }
    .sidebar-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 999; display: none; }
    .sidebar-overlay.open { display: block; }
    .main-content { padding: 24px 24px 32px; margin-top: var(--topbar-height); transition: margin-left 0.3s; }

    /* SECTIONS */
    .section { display: none; animation: fadeUp 0.35s ease; }
    .section.active { display: block; }
    @keyframes fadeUp { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }

    .page-heading { margin-bottom: 20px; }
    .page-heading h1 { font-family: 'DM Sans', sans-serif; font-size: 1.5rem; font-weight: 800; color: var(--c-brand-dark); margin-bottom: 2px; }
    .page-heading p { font-size: 0.85rem; color: var(--c-text-sub); }

    .card { background: #fff; padding: 22px; border-radius: 16px; border: 1px solid var(--c-slate-100); box-shadow: 0 2px 8px rgba(0,0,0,0.04); }

    /* STAT CARDS */
    .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 14px; margin-bottom: 24px; }
    .stat-card { background: #fff; border-radius: 14px; border: 1px solid var(--c-slate-100); box-shadow: 0 2px 8px rgba(0,0,0,0.04); padding: 18px 20px; display: flex; flex-direction: column; gap: 6px; }
    .stat-card .s-icon { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; margin-bottom: 4px; }
    .stat-card .s-val {
  font-family: 'DM Sans', sans-serif;
  font-size: 1.5rem;
  font-weight: 800;
  color: var(--c-brand-dark);
  line-height: 1;
  letter-spacing: -0.5px;
}
.map-stat-num {
  font-family: 'DM Sans', sans-serif;
  font-size: 1.25rem;
  font-weight: 800;
  color: var(--c-brand-dark);
  line-height: 1;
  letter-spacing: -0.3px;
}
.at-val {
  font-family: 'DM Sans', sans-serif;
  font-size: 1.5rem;
  font-weight: 800;
  color: var(--c-brand-dark);
  line-height: 1;
  letter-spacing: -0.5px;
}
 
/* ── ANIMAL CARDS (match farm-reg picker style) ── */
.animal-type-card {
  background: #fff;
  border: 1.5px solid var(--c-slate-100);
  border-radius: 14px;
  padding: 16px 12px 12px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
  cursor: default;
}
.animal-type-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 8px 20px rgba(0,0,0,0.09);
  border-color: var(--c-brand-accent);
}
.animal-type-card .at-img {
  width: 56px;
  height: 56px;
  object-fit: contain;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2.4rem;
  line-height: 1;
  background: #f0fdf4;
  border-radius: 50%;
  border: 2px solid #d1fae5;
}
.animal-type-card .at-count {
  font-family: 'DM Sans', sans-serif;
  font-size: 1.25rem;
  font-weight: 800;
  color: var(--c-brand-dark);
  letter-spacing: -0.3px;
  line-height: 1;
}
.animal-type-card .at-label {
  font-size: 0.72rem;
  font-weight: 700;
  color: var(--c-text-sub);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.animal-total-card {
  background: linear-gradient(135deg, var(--c-brand-dark), var(--c-brand-mid));
  border-radius: 14px;
  padding: 16px 12px 12px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  box-shadow: 0 4px 14px rgba(26,71,49,0.2);
}
.animal-total-card .at-img { font-size: 2.4rem; line-height: 1; background: rgba(255,255,255,0.15); border-radius: 50%; width: 56px; height: 56px; display: flex; align-items: center; justify-content: center; border: 2px solid rgba(255,255,255,0.25); }
.animal-total-card .at-count { font-family: 'DM Sans', sans-serif; font-size: 1.25rem; font-weight: 800; color: #fff; letter-spacing: -0.3px; }
.animal-total-card .at-label { font-size: 0.72rem; font-weight: 700; color: rgba(255,255,255,0.75); text-transform: uppercase; letter-spacing: 0.5px; }
 
/* ── ARCHIVE TABLE ── */
.archive-badge { padding: 3px 10px; border-radius: 20px; font-size: 0.72rem; font-weight: 700; background: #F1F5F9; color: #64748B; display: inline-block; }
 
/* ── FARM REVIEW MODAL (wide) ── */
#farmReviewModal .modal-content { max-width: 720px; }
.review-section { margin-bottom: 18px; }
.review-section h4 { font-size: 0.78rem; font-weight: 800; color: var(--c-text-sub); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px; padding-bottom: 6px; border-bottom: 1px solid var(--c-slate-100); }
.review-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.review-item { background: var(--c-slate-50); border-radius: 8px; padding: 8px 12px; }
.review-item .r-label { font-size: 0.7rem; color: var(--c-text-sub); font-weight: 600; margin-bottom: 2px; }
.review-item .r-value { font-size: 0.85rem; font-weight: 700; color: var(--c-text-main); }
.review-photo { width: 100%; border-radius: 10px; max-height: 200px; object-fit: cover; border: 2px solid var(--c-slate-100); margin-bottom: 12px; }
.review-livestock-row { display: flex; align-items: center; gap: 10px; padding: 8px 12px; background: var(--c-slate-50); border-radius: 8px; margin-bottom: 6px; }
 
/* ── LINE CHART ── */
.chart-canvas-wrap { position: relative; height: 320px; }
    .stat-card .s-lbl { font-size: 0.72rem; color: var(--c-text-sub); text-transform: uppercase; font-weight: 600; letter-spacing: 0.4px; }

    /* LIVESTOCK GRID */
    .livestock-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 12px; margin-bottom: 24px; }
    .livestock-type-card { background: #fff; border-radius: 14px; border: 1px solid var(--c-slate-100); box-shadow: 0 1px 4px rgba(0,0,0,0.05); padding: 16px 14px; display: flex; flex-direction: column; align-items: flex-start; transition: transform 0.2s, box-shadow 0.2s; }
    .livestock-type-card:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,0,0,0.08); }

    /* ICON BOX */
    .lt-icon {
      width: 52px; height: 52px; border-radius: 14px;
      display: flex; align-items: center; justify-content: center;
      font-size: 2.1rem; flex-shrink: 0; margin-bottom: 12px;
      background: var(--c-slate-50); line-height: 1;
      border: 1px solid var(--c-slate-100);
    }
    .lt-total-card .lt-icon { background: rgba(255,255,255,0.18); border: none; }

    .lt-info { width: 100%; }

    /* ── FIXED: livestock number — clean, readable, not oversized ── */
    .lt-info .lt-count {
      font-family: 'DM Sans', sans-serif;
      font-size: 1.3rem;
      font-weight: 700;
      line-height: 1.1;
      color: var(--c-text-main);
      letter-spacing: -0.1px;
    }
    .lt-info .lt-name { font-size: 0.78rem; color: var(--c-text-sub); font-weight: 500; margin-top: 4px; }

    .lt-total-card {
      background: linear-gradient(135deg, var(--c-brand-dark), var(--c-brand-mid));
      color: #fff; border-radius: 14px; padding: 16px 14px;
      display: flex; flex-direction: column; align-items: flex-start;
      box-shadow: 0 4px 14px rgba(26,71,49,0.2);
    }
    .lt-total-card .lt-count { font-family: 'DM Sans', sans-serif; font-size: 1.3rem; font-weight: 700; line-height: 1.1; color: #fff; letter-spacing: -0.1px; }
    .lt-total-card .lt-name { font-size: 0.78rem; color: rgba(255,255,255,0.75); font-weight: 500; margin-top: 4px; }

    /* TABLE */
    .table-container { background: #fff; border-radius: 16px; border: 1px solid var(--c-slate-100); box-shadow: 0 2px 8px rgba(0,0,0,0.04); overflow: hidden; margin-bottom: 24px; }
    .table-header { background: linear-gradient(135deg, var(--c-brand-mid), var(--c-brand-dark)); color: white; padding: 18px 22px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
    .table-header h2 { margin: 0; font-size: 1.1rem; font-weight: 700; font-family: 'DM Sans', sans-serif; }
    .table-scroll { overflow-x: auto; width: 100%; -webkit-overflow-scrolling: touch; }
    table { width: 100%; border-collapse: collapse; table-layout: auto; }
    th { background: var(--c-slate-50); padding: 9px 12px; text-align: left; font-weight: 700; color: var(--c-text-main); font-size: 0.74rem; text-transform: uppercase; letter-spacing: 0.04em; border-bottom: 2px solid var(--c-slate-100); white-space: nowrap; }
    td { padding: 8px 12px; border-bottom: 1px solid var(--c-slate-200); vertical-align: middle; font-size: 0.82rem; }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: #F8FAFC; }

    /* BADGES */
    .status-badge { padding: 3px 10px; border-radius: 20px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px; display: inline-block; }
    .status-approved { background: #D1FAE5; color: #059669; }
    .status-pending { background: #FEF3C7; color: #D97706; }
    .status-rejected { background: #FEE2E2; color: #DC2626; }
    .status-reviewed { background: #DBEAFE; color: #2563EB; }
    .status-resolved { background: #D1FAE5; color: #059669; }
    .status-closed { background: #F1F5F9; color: #64748B; }
    .priority-low { background: #F0FDF4; color: #16A34A; }
    .priority-medium { background: #FEF3C7; color: #D97706; }
    .priority-high { background: #FFF3CD; color: #C05621; }
    .priority-critical { background: #FEE2E2; color: #DC2626; }

    /* BUTTONS */
    .btn { padding: 7px 14px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 0.82rem; transition: all 0.2s; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; font-family: inherit; }
    .btn-primary { background: var(--c-brand-accent); color: white; }
    .btn-primary:hover { background: #059669; transform: translateY(-1px); }
    .btn-danger { background: var(--c-danger); color: white; }
    .btn-danger:hover { background: #DC2626; }
    .btn-ghost { background: var(--c-slate-100); color: var(--c-text-main); }
    .btn-ghost:hover { background: var(--c-slate-200); }

    .priority-select, .status-select { padding: 4px 8px; border-radius: 8px; border: 1px solid var(--c-slate-200); font-size: 0.8rem; font-weight: 600; font-family: inherit; cursor: pointer; background: #fff; color: var(--c-text-main); transition: border-color 0.2s; }
    .priority-select:focus, .status-select:focus { outline: none; border-color: var(--c-brand-accent); }
    .search-box { padding: 9px 16px; border: 1.5px solid var(--c-slate-200); border-radius: 10px; font-size: 0.88rem; font-family: inherit; background: rgba(255,255,255,0.15); color: #fff; }
    .search-box::placeholder { color: rgba(255,255,255,0.6); }
    .search-box:focus { outline: none; border-color: rgba(255,255,255,0.5); }

    /* ── MAP CONTAINER & TOOLBAR (ADMIN GIS STYLE) ─────────────────── */
    .section-header {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 20px;
      margin-bottom: 24px;
      flex-wrap: wrap;
    }
    .section-header h3 {
      font-size: 1.55rem;
      font-weight: 800;
      letter-spacing: -0.02em;
      color: var(--c-brand-dark);
      margin: 0;
    }
    .section-header p {
      font-size: 0.88rem;
      color: var(--c-text-sub);
      margin-top: 4px;
    }
    .btn-top {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 9px 16px;
      border-radius: 10px;
      font-size: 0.84rem;
      font-weight: 700;
      text-decoration: none;
      transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
      cursor: pointer;
      font-family: inherit;
    }
    .btn-top-primary {
      background: linear-gradient(135deg, #10b981, #059669);
      color: #ffffff;
      border: 1px solid #059669;
      box-shadow: 0 2px 8px rgba(16, 185, 129, 0.25);
    }
    .btn-top-primary:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35);
      color: #ffffff;
    }
    .pulse-live-dot {
      width: 7px;
      height: 7px;
      border-radius: 50%;
      background: #10b981;
      box-shadow: 0 0 8px #10b981;
      animation: livePulse 2s infinite;
      display: inline-block;
    }
    @keyframes livePulse {
      0% { transform: scale(1); opacity: 1; }
      50% { transform: scale(1.6); opacity: 0.4; }
      100% { transform: scale(1); opacity: 1; }
    }
    .map-section-card {
      background: #ffffff;
      border: 1px solid var(--c-slate-100);
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
    }
    .map-top-toolbar {
      padding: 14px 20px;
      background: #f8fafc;
      border-bottom: 1px solid var(--c-slate-100);
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 10px;
    }
    .map-frame-view {
      width: 100%;
      height: 640px;
      border: none;
      display: block;
      background: #f8fafc;
      overscroll-behavior: contain;
    }
    @media (max-width: 768px) {
      .map-frame-view {
        height: 480px;
      }
    }
    .map-stats-bar {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
      background: #ffffff;
      border-top: 1px solid var(--c-slate-100);
    }
    .map-stat-item {
      padding: 16px 18px;
      text-align: center;
      border-right: 1px solid var(--c-slate-100);
    }
    .map-stat-item:last-child {
      border-right: none;
    }
    .map-stat-num {
      font-family: 'DM Sans', sans-serif;
      font-size: 1.4rem;
      font-weight: 800;
      color: var(--c-brand-dark);
      line-height: 1;
      letter-spacing: -0.3px;
    }
    .map-stat-lbl {
      font-size: 0.68rem;
      color: var(--c-text-sub);
      text-transform: uppercase;
      font-weight: 700;
      letter-spacing: 0.5px;
      margin-top: 5px;
    }

    /* ══════════ ANALYTICS — MODERN INSIGHTS DASHBOARD ══════════ */
    .an-hero { position: relative; display: flex; align-items: center; justify-content: space-between; gap: 20px; flex-wrap: wrap; padding: 26px 30px; margin-bottom: 22px; border-radius: 20px; color: #fff; overflow: hidden; background: linear-gradient(120deg, #0F3D2A 0%, #1A4731 45%, #10B981 140%); box-shadow: 0 12px 32px rgba(16,185,129,0.22); }
    .an-hero::before { content: ''; position: absolute; right: -60px; top: -90px; width: 280px; height: 280px; border-radius: 50%; background: radial-gradient(circle, rgba(255,255,255,0.18), transparent 70%); }
    .an-hero::after { content: ''; position: absolute; left: 38%; bottom: -120px; width: 260px; height: 260px; border-radius: 50%; background: radial-gradient(circle, rgba(16,185,129,0.35), transparent 70%); }
    .an-hero-text, .an-hero-actions { position: relative; z-index: 1; }
    .an-eyebrow { display: inline-flex; align-items: center; gap: 6px; font-size: 0.7rem; font-weight: 700; letter-spacing: 1.2px; text-transform: uppercase; padding: 5px 12px; border-radius: 20px; background: rgba(255,255,255,0.14); border: 1px solid rgba(255,255,255,0.22); margin-bottom: 10px; }
    .an-hero h1 { font-size: 1.85rem; font-weight: 800; letter-spacing: -0.03em; line-height: 1.15; margin-bottom: 6px; color: #fff; }
    .an-hero p { font-size: 0.9rem; color: rgba(255,255,255,0.78); max-width: 520px; }
    .an-hero-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .an-date-chip { display: inline-flex; align-items: center; gap: 7px; font-size: 0.8rem; font-weight: 600; padding: 9px 14px; border-radius: 10px; background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.2); margin-right: 4px; }
    .an-btn { display: inline-flex; align-items: center; gap: 7px; padding: 9px 15px; border-radius: 10px; font-family: inherit; font-size: 0.82rem; font-weight: 700; cursor: pointer; transition: all .2s ease; border: 1px solid transparent; }
    .an-btn-primary { background: #fff; color: #0F3D2A; }
    .an-btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 18px rgba(0,0,0,0.2); }
    .an-btn-ghost { background: rgba(255,255,255,0.12); color: #fff; border-color: rgba(255,255,255,0.28); }
    .an-btn-ghost:hover { background: rgba(255,255,255,0.24); transform: translateY(-2px); }

    .an-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 16px; margin-bottom: 22px; }
    .an-kpi { position: relative; background: #fff; border: 1px solid var(--c-slate-100); border-radius: 18px; padding: 20px 22px; overflow: hidden; transition: transform .25s ease, box-shadow .25s ease; box-shadow: 0 2px 10px rgba(15,23,42,0.04); }
    .an-kpi::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: var(--kc); }
    .an-kpi::after { content: ''; position: absolute; right: -40px; top: -40px; width: 130px; height: 130px; border-radius: 50%; background: var(--kb); opacity: .55; }
    .an-kpi:hover { transform: translateY(-4px); box-shadow: 0 14px 30px rgba(15,23,42,0.1); }
    .an-kpi > * { position: relative; z-index: 1; }
    .an-kpi-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
    .an-kpi-icon { width: 44px; height: 44px; border-radius: 13px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; background: var(--kb); color: var(--kc); }
    .an-kpi-chip { display: inline-flex; align-items: center; gap: 5px; font-size: 0.68rem; font-weight: 700; padding: 4px 9px; border-radius: 20px; background: #fff; color: var(--kc); border: 1px solid var(--kb); }
    .an-kpi-val { font-size: 2.1rem; font-weight: 800; letter-spacing: -0.04em; line-height: 1; color: var(--c-text-main); }
    .an-kpi-lbl { margin-top: 6px; font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .6px; color: var(--c-text-sub); }
    .an-kpi-foot { margin-top: 12px; padding-top: 12px; border-top: 1px dashed var(--c-slate-100); font-size: 0.78rem; color: var(--c-text-sub); font-weight: 500; }

    .an-grid { display: grid; gap: 18px; margin-bottom: 22px; }
    .an-grid-5-7 { grid-template-columns: minmax(0, 5fr) minmax(0, 7fr); }
    .an-grid-7-5 { grid-template-columns: minmax(0, 7fr) minmax(0, 5fr); }
    .an-grid-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .an-card { background: #fff; border: 1px solid var(--c-slate-100); border-radius: 18px; padding: 22px; box-shadow: 0 2px 10px rgba(15,23,42,0.04); min-width: 0; }
    .an-card-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 18px; }
    .an-card-head h3 { display: flex; align-items: center; gap: 9px; font-size: 1rem; font-weight: 800; letter-spacing: -0.01em; color: var(--c-brand-dark); }
    .an-card-head h3 i { display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; border-radius: 9px; font-size: .9rem; background: #ECFDF5; color: var(--c-brand-accent); }
    .an-card-head p { margin: 4px 0 0 39px; font-size: 0.78rem; color: var(--c-text-sub); }

    .an-donut-wrap { display: flex; align-items: center; gap: 22px; flex-wrap: wrap; justify-content: center; }
    .an-donut-canvas { position: relative; width: 220px; height: 220px; flex-shrink: 0; }
    .an-donut-center { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; pointer-events: none; }
    .an-donut-center strong { font-size: 1.7rem; font-weight: 800; letter-spacing: -0.03em; color: var(--c-text-main); line-height: 1; }
    .an-donut-center span { font-size: 0.68rem; text-transform: uppercase; letter-spacing: .8px; color: var(--c-text-sub); font-weight: 700; margin-top: 4px; }
    .an-legend { flex: 1; min-width: 150px; display: flex; flex-direction: column; gap: 8px; }
    .an-legend-row { display: flex; align-items: center; gap: 9px; padding: 7px 10px; border-radius: 10px; background: #F8FAFC; font-size: 0.82rem; }
    .an-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
    .an-legend-name { flex: 1; font-weight: 600; color: var(--c-text-main); }
    .an-legend-pct { font-weight: 800; color: var(--c-text-main); }
    .an-trend-canvas { position: relative; height: 290px; }

    .an-rank-list { display: flex; flex-direction: column; gap: 14px; }
    .an-rank-row { display: flex; align-items: center; gap: 12px; }
    .an-rank-no { width: 22px; text-align: center; font-size: 0.8rem; font-weight: 800; color: #94A3B8; }
    .an-rank-emoji { width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; flex-shrink: 0; }
    .an-rank-body { flex: 1; min-width: 0; }
    .an-rank-top { display: flex; align-items: baseline; justify-content: space-between; gap: 10px; margin-bottom: 7px; flex-wrap: wrap; }
    .an-rank-top strong { font-size: 0.88rem; font-weight: 700; color: var(--c-text-main); }
    .an-rank-top span { font-size: 0.74rem; color: var(--c-text-sub); }
    .an-rank-top span b { color: var(--c-text-main); font-weight: 800; }
    .an-rank-share { min-width: 48px; text-align: right; font-size: 0.82rem; font-weight: 800; color: var(--c-text-main); }
    .an-bar-track { height: 9px; background: #F1F5F9; border-radius: 20px; overflow: hidden; }
    .an-bar-thin { height: 7px; }
    .an-bar-fill { height: 100%; border-radius: 20px; transition: width 1.1s cubic-bezier(0.22, 1, 0.36, 1); }

    .an-region-list { display: flex; flex-direction: column; gap: 12px; }
    .an-region { padding: 14px 16px; border-radius: 14px; background: #F8FAFC; border: 1px solid #F1F5F9; transition: border-color .2s, transform .2s; }
    .an-region:hover { border-color: #A7F3D0; transform: translateX(3px); }
    .an-region-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
    .an-region-name { display: flex; align-items: center; gap: 9px; font-weight: 700; font-size: 0.88rem; color: var(--c-text-main); }
    .an-region-pin { width: 26px; height: 26px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; background: #D1FAE5; color: #059669; font-size: .75rem; }
    .an-region-pct { font-weight: 800; font-size: 0.85rem; color: #059669; }
    .an-region-meta { display: flex; gap: 16px; margin-top: 9px; font-size: 0.74rem; color: var(--c-text-sub); }
    .an-region-meta i { margin-right: 4px; }

    .an-seg { display: flex; height: 12px; border-radius: 20px; overflow: hidden; background: #F1F5F9; gap: 2px; margin-bottom: 16px; }
    .an-seg span { display: block; height: 100%; transition: width 1s ease; }
    .an-stat-list { display: flex; flex-direction: column; gap: 4px; }
    .an-stat-row { display: flex; align-items: center; gap: 10px; padding: 9px 4px; border-bottom: 1px solid #F1F5F9; font-size: 0.84rem; }
    .an-stat-row:last-child { border-bottom: none; }
    .an-stat-name { flex: 1; font-weight: 600; color: var(--c-text-main); }
    .an-stat-row strong { font-weight: 800; color: var(--c-text-main); }
    .an-stat-row em { font-style: normal; min-width: 38px; text-align: right; font-size: 0.74rem; font-weight: 700; color: var(--c-text-sub); }
    .an-chips { display: flex; flex-wrap: wrap; gap: 7px; margin-top: 14px; padding-top: 14px; border-top: 1px dashed var(--c-slate-100); }
    .an-chip { display: inline-flex; align-items: center; gap: 6px; padding: 5px 11px; border-radius: 20px; font-size: 0.72rem; font-weight: 600; color: var(--c-text-sub); background: #F8FAFC; border: 1px solid #E2E8F0; }
    .an-chip i { width: 7px; height: 7px; border-radius: 50%; background: var(--cc); }
    .an-chip b { color: var(--c-text-main); font-weight: 800; }

    .an-top-list { display: flex; flex-direction: column; gap: 14px; }
    .an-top-row { display: flex; align-items: center; gap: 12px; }
    .an-medal { width: 30px; height: 30px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.82rem; flex-shrink: 0; background: #F1F5F9; color: #64748B; }
    .an-medal-1 { background: linear-gradient(135deg, #FDE68A, #F59E0B); color: #78350F; }
    .an-medal-2 { background: linear-gradient(135deg, #E2E8F0, #94A3B8); color: #1E293B; }
    .an-medal-3 { background: linear-gradient(135deg, #FDBA74, #EA580C); color: #7C2D12; }
    .an-top-body { flex: 1; min-width: 0; }
    .an-top-name { font-size: 0.85rem; font-weight: 700; color: var(--c-text-main); margin-bottom: 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .an-top-val { font-size: 0.9rem; font-weight: 800; color: var(--c-text-main); }

    .an-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; padding: 36px 10px; color: var(--c-text-sub); text-align: center; }
    .an-empty i { font-size: 2rem; color: #CBD5E1; }
    .an-empty p { font-size: 0.85rem; }
    .an-empty-sm { padding: 18px 10px; }

    @media (max-width: 1180px) { .an-grid-3 { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 960px) {
      .an-grid-5-7, .an-grid-7-5 { grid-template-columns: minmax(0, 1fr); }
    }
    @media (max-width: 680px) {
      .an-hero { padding: 20px 18px; border-radius: 16px; }
      .an-hero h1 { font-size: 1.45rem; }
      .an-hero-actions { width: 100%; }
      .an-date-chip { width: 100%; justify-content: center; margin: 0 0 4px; }
      .an-btn { flex: 1; justify-content: center; }
      .an-grid-3 { grid-template-columns: minmax(0, 1fr); }
      .an-card { padding: 18px 16px; border-radius: 16px; }
      .an-kpi { padding: 18px; }
      .an-kpi-val { font-size: 1.8rem; }
      .an-donut-canvas { width: 190px; height: 190px; }
      .an-trend-canvas { height: 240px; }
      .an-rank-no { display: none; }
      .an-rank-top { flex-direction: column; gap: 2px; }
    }

    /* ══════════ DASHBOARD — MODERN OVERVIEW ══════════ */
    .an-kpis.dn-five { grid-template-columns: repeat(auto-fit, minmax(176px, 1fr)); }
    .an-hero h1 strong, .an-hero p strong { color: #fff; font-weight: 800; }
    .dn-total-pill { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 20px; background: #ECFDF5; border: 1px solid #A7F3D0; color: #047857; font-size: 0.78rem; font-weight: 800; white-space: nowrap; }

    .dn-type-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 12px; }
    .dn-type { position: relative; padding: 14px 15px 15px; border-radius: 16px; background: #F8FAFC; border: 1px solid #F1F5F9; transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease; overflow: hidden; }
    .dn-type::before { content: ''; position: absolute; left: 0; top: 0; right: 0; height: 3px; background: var(--tc); opacity: .85; }
    .dn-type:hover { transform: translateY(-3px); box-shadow: 0 10px 22px rgba(15,23,42,0.08); border-color: var(--tc); background: #fff; }
    .dn-type-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
    .dn-type-emoji { width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; background: color-mix(in srgb, var(--tc) 14%, #fff); }
    .dn-type-share { font-size: 0.7rem; font-weight: 800; padding: 3px 8px; border-radius: 20px; background: #fff; color: var(--tc); border: 1px solid color-mix(in srgb, var(--tc) 28%, #fff); }
    .dn-type-count { font-size: 1.45rem; font-weight: 800; letter-spacing: -0.03em; color: var(--c-text-main); line-height: 1.1; }
    .dn-type-name { font-size: 0.78rem; font-weight: 600; color: var(--c-text-sub); margin: 3px 0 11px; }

    .dn-actions { display: flex; flex-direction: column; gap: 10px; }
    .dn-action { display: flex; align-items: center; gap: 13px; width: 100%; padding: 12px 14px; border-radius: 14px; background: #F8FAFC; border: 1px solid #F1F5F9; cursor: pointer; text-align: left; font-family: inherit; transition: all .2s ease; }
    .dn-action:hover { background: #fff; border-color: var(--ac); transform: translateX(4px); box-shadow: 0 8px 18px rgba(15,23,42,0.07); }
    .dn-action-icon { width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.05rem; background: var(--ab); color: var(--ac); flex-shrink: 0; }
    .dn-action-body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
    .dn-action-body strong { font-size: 0.86rem; font-weight: 700; color: var(--c-text-main); }
    .dn-action-body small { font-size: 0.74rem; color: var(--c-text-sub); }
    .dn-action-count { min-width: 32px; height: 28px; padding: 0 10px; border-radius: 20px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 800; background: var(--ac); color: #fff; }
    .dn-action-count-soft { background: var(--ab); color: var(--ac); font-size: 0.85rem; }
    .dn-action-go { color: #CBD5E1; font-size: 0.8rem; transition: transform .2s, color .2s; }
    .dn-action:hover .dn-action-go { color: var(--ac); transform: translateX(3px); }

    .dn-table-card { padding-bottom: 8px; margin-bottom: 24px; }
    .dn-link-btn { display: inline-flex; align-items: center; gap: 6px; padding: 7px 13px; border-radius: 10px; border: 1px solid #E2E8F0; background: #fff; color: var(--c-brand-mid); font-family: inherit; font-size: 0.78rem; font-weight: 700; cursor: pointer; transition: all .2s; white-space: nowrap; }
    .dn-link-btn:hover { background: #ECFDF5; border-color: #A7F3D0; color: #047857; }
    .dn-link-btn:hover i { transform: translateX(3px); }
    .dn-link-btn i { transition: transform .2s; }
    .dn-table { width: 100%; table-layout: auto; }
    .dn-table th { background: transparent; padding: 8px 10px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: .7px; color: #94A3B8; border-bottom: 1px solid #F1F5F9; }
    .dn-table td { padding: 9px 10px; border-bottom: 1px solid #F1F5F9; font-size: 0.82rem; }
    .dn-table tr:hover td { background: #F8FAFC; }
    .dn-inc-title { font-weight: 700; font-size: 0.9rem; color: var(--c-text-main); margin-bottom: 3px; }
    .dn-inc-sub { font-size: 0.75rem; color: var(--c-text-sub); }
    .dn-inc-sub i { margin-right: 2px; }
    .dn-pill { display: inline-flex; align-items: center; gap: 6px; padding: 4px 11px; border-radius: 20px; font-size: 0.72rem; font-weight: 700; color: var(--pc); background: color-mix(in srgb, var(--pc) 11%, #fff); border: 1px solid color-mix(in srgb, var(--pc) 22%, #fff); white-space: nowrap; }
    .dn-pill i { width: 6px; height: 6px; border-radius: 50%; background: var(--pc); }
    .dn-date { font-size: 0.84rem; font-weight: 600; color: var(--c-text-main); }
    .dn-date-sub { font-size: 0.72rem; color: var(--c-text-sub); margin-top: 2px; }
    .dn-resolve { display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: 10px; border: none; background: linear-gradient(135deg, #10B981, #059669); color: #fff; font-family: inherit; font-size: 0.78rem; font-weight: 700; cursor: pointer; box-shadow: 0 2px 8px rgba(16,185,129,0.25); transition: all .2s; }
    .dn-resolve:hover { transform: translateY(-1px); box-shadow: 0 6px 14px rgba(16,185,129,0.35); }
    .dn-resolve:disabled { background: #E2E8F0; color: #64748B; box-shadow: none; cursor: default; transform: none; }
    .dn-done { display: inline-flex; align-items: center; gap: 6px; font-size: 0.78rem; font-weight: 700; color: #059669; }

    @media (max-width: 680px) {
      .an-kpis.dn-five { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
      .an-kpis.dn-five .an-kpi { padding: 16px 14px; }
      .an-kpis.dn-five .an-kpi-val { font-size: 1.6rem; }
      .an-kpis.dn-five .an-kpi-chip { display: none; }
      .dn-type-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .dn-action-go { display: none; }
    }

    /* ANALYTICS */
    .analytics-section { background: #fff; border-radius: 16px; border: 1px solid var(--c-slate-100); box-shadow: 0 2px 8px rgba(0,0,0,0.04); overflow: hidden; margin-bottom: 24px; }
    .analytics-header { padding: 18px 22px; border-bottom: 1px solid var(--c-slate-100); display: flex; justify-content: space-between; align-items: center; }
    .analytics-header h3 { margin: 0; font-size: 1rem; font-weight: 700; color: var(--c-brand-dark); display: flex; align-items: center; gap: 8px; font-family: 'DM Sans', sans-serif; }
    .analytics-header h3 i { color: var(--c-brand-accent); }
    .chart-wrap { padding: 22px; overflow-x: auto; }
    .bar-chart { display: flex; flex-direction: column; gap: 12px; min-width: 300px; }
    .bar-row { display: flex; align-items: center; gap: 12px; }
    .bar-label { width: 85px; font-size: 0.82rem; font-weight: 700; color: var(--c-text-main); text-align: right; flex-shrink: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .bar-track { flex: 1; background: var(--c-slate-50); border-radius: 8px; height: 34px; overflow: hidden; }
    .bar-fill { height: 100%; border-radius: 8px; transition: width 1s cubic-bezier(0.4,0,0.2,1); display: flex; align-items: center; padding-left: 12px; font-size: 0.78rem; font-weight: 700; color: #fff; white-space: nowrap; min-width: 44px; }
    .bar-count { width: 64px; font-size: 0.88rem; font-weight: 800; color: var(--c-text-main); text-align: right; flex-shrink: 0; }

    /* ANALYTICS TOTALS */
    .analytics-totals { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 14px; margin-bottom: 24px; }
    .at-card { background: #fff; border-radius: 14px; border: 1px solid var(--c-slate-100); padding: 18px 20px; display: flex; flex-direction: column; gap: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
    .at-card .at-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1rem; margin-bottom: 6px; }
    .at-card .at-val { font-family: 'DM Sans', sans-serif; font-size: 1.9rem; font-weight: 800; color: var(--c-brand-dark); line-height: 1; letter-spacing: -1px; }
    .at-card .at-lbl { font-size: 0.72rem; color: var(--c-text-sub); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; }

    /* PROFILE */
    .profile-container { max-width: 680px; margin: 0 auto; }
    .profile-card { background: #fff; border-radius: 18px; box-shadow: 0 4px 24px rgba(0,0,0,0.07); overflow: hidden; margin-bottom: 20px; border: 1px solid var(--c-slate-100); }
    .profile-header { background: linear-gradient(135deg, var(--c-brand-mid), var(--c-brand-dark)); color: white; padding: 36px 28px 28px; text-align: center; }
    .avatar-circle { width: 90px; height: 90px; background: rgba(255,255,255,0.18); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2.2rem; font-weight: 800; margin: 0 auto 14px; border: 3px solid rgba(255,255,255,0.3); overflow: hidden; }
    .profile-info { padding: 26px; }
    .info-section { margin-bottom: 24px; }
    .info-section h3 { font-size: 0.95rem; color: var(--c-brand-dark); margin-bottom: 16px; display: flex; align-items: center; gap: 8px; font-weight: 700; }
    .info-section h3 i { color: var(--c-brand-accent); }
    .info-item { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid var(--c-slate-100); }
    .info-label { color: var(--c-text-sub); font-weight: 600; font-size: 0.87rem; }
    .info-value { font-weight: 600; color: var(--c-text-main); text-align: right; font-size: 0.87rem; }
    .profile-actions { padding: 0 26px 26px; text-align: center; }
    .btn-edit { background: linear-gradient(135deg, var(--c-brand-accent), #059669); color: white; border: none; padding: 12px 26px; border-radius: 12px; font-weight: 700; font-size: 0.92rem; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s; font-family: inherit; box-shadow: 0 4px 14px rgba(16,185,129,0.3); }
    .btn-edit:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(16,185,129,0.4); }

    /* MODAL */
    .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(6px); z-index: 2000; display: flex; align-items: center; justify-content: center; padding: 20px; opacity: 0; visibility: hidden; transition: all 0.3s; }
    .modal-overlay.active { opacity: 1; visibility: visible; }
    .modal-content { background: #fff; border-radius: 18px; max-width: 500px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.25); transform: scale(0.9) translateY(16px); transition: all 0.3s; }
    .modal-overlay.active .modal-content { transform: scale(1) translateY(0); }
    .modal-header { padding: 22px 26px 14px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--c-slate-100); }
    .modal-header h3 { margin: 0; font-size: 1.1rem; color: var(--c-brand-dark); font-family: 'DM Sans', sans-serif; }
    .modal-close { background: none; border: none; font-size: 1.4rem; color: var(--c-text-sub); cursor: pointer; padding: 4px 8px; border-radius: 8px; line-height: 1; }
    .modal-close:hover { background: var(--c-slate-100); }
    .modal-body { padding: 18px 26px; }
    .form-group { margin-bottom: 16px; }
    .form-label { display: block; font-weight: 700; color: var(--c-text-main); margin-bottom: 6px; font-size: 0.88rem; }
    .form-input { width: 100%; padding: 11px 14px; border: 1.5px solid var(--c-slate-200); border-radius: 10px; font-size: 0.92rem; font-family: inherit; transition: all 0.2s; }
    .form-input:focus { outline: none; border-color: var(--c-brand-accent); box-shadow: 0 0 0 3px rgba(16,185,129,0.1); }
    .form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .modal-actions { padding: 14px 26px 22px; border-top: 1px solid var(--c-slate-100); display: flex; gap: 10px; justify-content: flex-end; }
    .btn-cancel { padding: 11px 18px; border-radius: 10px; background: var(--c-slate-100); color: var(--c-text-main); border: none; font-weight: 700; cursor: pointer; font-family: inherit; }
    .btn-save { padding: 11px 22px; border-radius: 10px; background: linear-gradient(135deg, var(--c-brand-accent), #059669); color: #fff; border: none; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px; font-family: inherit; }

    /* LOADING */
    #loadingOverlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255,255,255,0.85); display: none; align-items: center; justify-content: center; z-index: 3000; }
    .spinner { width: 42px; height: 42px; border: 3px solid var(--c-slate-200); border-top: 3px solid var(--c-brand-accent); border-radius: 50%; animation: spin 0.8s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* ── AUTO-HIDE & FULL VIEW (Official DA Panel) ─────────────────── */
    body.sidebar-autohide .panel-sidebar {
      left: calc(-1 * var(--sidebar-width)) !important;
      box-shadow: 20px 0 50px rgba(0,0,0,0.45) !important;
      transition: left 0.28s cubic-bezier(0.16, 1, 0.3, 1), transform 0.28s cubic-bezier(0.16, 1, 0.3, 1) !important;
      z-index: 1000 !important;
    }

    body.sidebar-autohide .panel-sidebar.hover-open,
    body.sidebar-autohide .panel-sidebar:hover,
    body.sidebar-autohide .panel-sidebar.open {
      left: 0 !important;
      transform: none !important;
    }

    body.sidebar-autohide .main-content {
      margin-left: 0 !important;
      width: 100% !important;
      max-width: 100% !important;
      transition: margin-left 0.28s cubic-bezier(0.16, 1, 0.3, 1) !important;
    }

    body.sidebar-autohide .topbar {
      left: 0 !important;
      width: 100% !important;
      transition: left 0.28s cubic-bezier(0.16, 1, 0.3, 1) !important;
    }

    .menu-btn {
      display: flex !important;
      align-items: center;
      justify-content: center;
      cursor: pointer;
    }

    /* Edge Hover Detection Zone */
    .da-hover-zone {
      display: none;
      position: fixed;
      left: 0;
      top: 0;
      bottom: 0;
      width: 20px;
      z-index: 998;
    }
    body.sidebar-autohide .da-hover-zone {
      display: block;
    }

    /* Floating Edge Trigger Tab */
    .da-edge-trigger {
      display: none;
      position: fixed;
      top: 50%;
      left: 0;
      transform: translateY(-50%);
      width: 22px;
      height: 54px;
      background: linear-gradient(180deg, #065f46, #022c22);
      color: #34d399;
      border-radius: 0 10px 10px 0;
      box-shadow: 2px 0 12px rgba(0, 0, 0, 0.3);
      z-index: 999;
      cursor: pointer;
      place-items: center;
      font-size: 0.85rem;
      border: 1px solid rgba(52, 211, 153, 0.3);
      border-left: none;
      transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
      user-select: none;
    }
    .da-edge-trigger:hover {
      width: 28px;
      background: #059669;
      color: #ffffff;
    }
    body.sidebar-autohide .da-edge-trigger {
      display: grid;
    }

    .btn-da-pin {
      margin-left: auto;
      width: 30px;
      height: 30px;
      border-radius: 8px;
      background: rgba(255, 255, 255, 0.08);
      border: 1px solid rgba(255, 255, 255, 0.12);
      color: #a7f3d0;
      display: grid;
      place-items: center;
      font-size: 0.9rem;
      cursor: pointer;
      transition: all 0.2s ease;
    }
    .btn-da-pin:hover {
      background: rgba(255, 255, 255, 0.2);
      color: #fff;
    }
    body:not(.sidebar-autohide) .btn-da-pin {
      color: #34d399;
      background: rgba(52, 211, 153, 0.18);
      border-color: rgba(52, 211, 153, 0.35);
    }

    @media (min-width: 1024px) {
      .panel-sidebar { left: 0; transition: left 0.28s cubic-bezier(0.16, 1, 0.3, 1); }
      .main-content { margin-left: var(--sidebar-width); transition: margin-left 0.28s cubic-bezier(0.16, 1, 0.3, 1); }
      .topbar { left: var(--sidebar-width); transition: left 0.28s cubic-bezier(0.16, 1, 0.3, 1); }
      .sidebar-overlay { display: none !important; }
    }
    @media (max-width: 1023px) {
      .da-hover-zone,
      .da-edge-trigger {
        display: none !important;
      }
    }
    @media (max-width: 600px) {
      .form-row-2 { grid-template-columns: 1fr; }
      .info-item { flex-direction: column; gap: 3px; }
      .info-value { text-align: left; }
      .main-content { padding: 16px; }
    }
  
    /* ═══ SHARED MODERN THEME (matches Admin & Farmer panels) ═══════════ */
    :root { --c-brand-dark: #064e3b; --c-brand-mid: #059669; --c-brand-accent: #10b981; --c-bg-page: #f1f5f9; }
    body { background: radial-gradient(1200px 500px at 90% -10%, rgba(16,185,129,.10), transparent 60%), var(--c-bg-page); }
    .panel-sidebar { background: linear-gradient(180deg, #022c22 0%, #064e3b 100%); box-shadow: 20px 0 50px -20px rgba(0,0,0,.4); }
    .sidebar-header { border-bottom-color: rgba(255,255,255,.08); }
    .nav-item { border-radius: 14px; padding: 12px 16px; font-weight: 600; }
    .nav-item.active { background: linear-gradient(135deg, #10b981, #059669); box-shadow: 0 8px 18px -8px rgba(16,185,129,.7); }
    .sidebar-footer { background: rgba(0,0,0,.18); }
    .user-avatar, .topbar-avatar { background: linear-gradient(135deg, #10b981, #065f46); border-radius: 50%; }
    .topbar { background: rgba(255,255,255,.82); backdrop-filter: saturate(180%) blur(14px); -webkit-backdrop-filter: saturate(180%) blur(14px); border-bottom: 1px solid var(--c-slate-100); }
    .topbar-title { color: var(--c-text-main); font-size: 1.05rem; font-weight: 800; letter-spacing: -.01em; }
    .menu-btn { color: #064e3b; }
    .tb-date { display: inline-flex; align-items: center; gap: 6px; font-size: .78rem; font-weight: 600; color: var(--c-text-sub); padding: 7px 12px; border-radius: 999px; background: #fff; border: 1px solid var(--c-slate-100); }
    .tb-name { font-size: .82rem; font-weight: 700; color: var(--c-text-main); }
    .an-hero { border-radius: 26px; padding: 32px 36px;
      background: radial-gradient(circle at 85% 20%, rgba(52,211,153,.35), transparent 45%), linear-gradient(135deg, #022c22 0%, #065f46 55%, #059669 100%) !important;
      box-shadow: 0 22px 44px -18px rgba(5,150,105,.55); }
    .an-kpi, .an-card, .card, .stat-card { border-radius: 20px; box-shadow: 0 8px 24px -14px rgba(15,23,42,.18); }
    .an-card-head h3 { color: var(--c-text-main); }
    .page-heading h1 { color: #0f172a; }
    @media (min-width: 1024px) { 
      .topbar { padding: 0 28px; } 
      .main-content { padding: 28px 32px 36px; } 
    }
    @media (max-width: 1023px) { 
      .tb-date, .tb-name { display: none; }
      .main-content { padding: 20px 18px 28px; }

      /* Remove scrollbars on mobile */
      * {
        scrollbar-width: none !important;
        -ms-overflow-style: none !important;
      }
      *::-webkit-scrollbar,
      html::-webkit-scrollbar,
      body::-webkit-scrollbar,
      .main-content::-webkit-scrollbar,
      .table-responsive::-webkit-scrollbar,
      ::-webkit-scrollbar {
        display: none !important;
        width: 0 !important;
        height: 0 !important;
        background: transparent !important;
        -webkit-appearance: none !important;
      }
      *::-webkit-scrollbar-thumb,
      *::-webkit-scrollbar-track,
      ::-webkit-scrollbar-thumb,
      ::-webkit-scrollbar-track {
        display: none !important;
        width: 0 !important;
        height: 0 !important;
      }
    }
    @media (max-width: 768px) {
      .main-content { padding: 14px 12px 24px; }
      .topbar { padding: 0 14px; height: 58px; }
      .topbar-title { font-size: clamp(0.92rem, 3.5vw, 1.05rem); max-width: 190px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
      .an-hero { padding: 22px 18px; border-radius: 18px; }
      .an-hero h1 { font-size: clamp(1.2rem, 4.5vw, 1.6rem); }
      .an-hero p { font-size: 0.85rem; }
      .an-kpis.dn-five { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
      .an-kpis.dn-five .an-kpi { padding: 14px 12px; border-radius: 14px; }
      .an-kpis.dn-five .an-kpi-val { font-size: 1.45rem; }
      .page-heading h1 { font-size: 1.3rem; }
      .table-responsive, .table-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; width: 100%; }
      table { min-width: 580px; }
      input, select, textarea { font-size: 16px !important; }
    }
    @media (max-width: 480px) {
      .main-content { padding: 10px 8px 20px; }
      .topbar { padding: 0 10px; }
      .topbar-title { max-width: 140px; }
      .lang-switch-btn .lang-label { display: none; }
      .lang-switch-btn { padding: 5px 8px; }
      .an-kpis.dn-five { grid-template-columns: 1fr; }
      .dn-type-grid { grid-template-columns: 1fr; }
      .card { padding: 16px 12px; }
    }
  </style>
</head>
<body class="sidebar-autohide">

<div class="da-hover-zone" id="daHoverZone" aria-hidden="true"></div>
<div class="da-edge-trigger" id="daEdgeTrigger" title="Hover or click for navigation">
  <i class="bi bi-chevron-right"></i>
</div>

<div class="sidebar-overlay" id="overlay" onclick="toggleSidebar()"></div>
<div id="loadingOverlay"><div class="spinner"></div></div>

<aside class="panel-sidebar" id="sidebar">
  <div class="sidebar-header">
    <div>
      <div class="sidebar-logo">Agri<span>Trace+</span></div>
      <div class="sidebar-sub">Official Portal</div>
    </div>
    <button type="button" class="btn-da-pin" id="daPinBtn" title="Pin / Unpin Sidebar">
      <i class="bi bi-pin-angle"></i>
    </button>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-section-label">Main</div>
    <div class="nav-item active" onclick="navTo('dashboard', this)"><i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span></div>
    <div class="nav-section-label">Management</div>
    <div class="nav-item" onclick="navTo('farms', this)"><i class="bi bi-house-check-fill"></i><span>Farm Inspection</span></div>
    <div class="nav-item" onclick="navTo('incidents', this)"><i class="bi bi-exclamation-triangle-fill"></i><span>Incidents</span></div>
    <div class="nav-item" onclick="navTo('public', this)"><i class="bi bi-file-earmark-text-fill"></i><span>Public Reports</span></div>
    <div class="nav-section-label">Monitoring</div>
    <div class="nav-item" onclick="navTo('map', this)"><i class="bi bi-map-fill"></i><span>Geo-Monitoring</span></div>
    <div class="nav-item" onclick="navTo('reports', this)"><i class="bi bi-bar-chart-line-fill"></i><span>Analytics</span></div>
    <div class="nav-divider"></div>
    <div class="nav-item" onclick="navTo('profile', this)"><i class="bi bi-person-circle"></i><span>My Profile</span></div>
    <div class="nav-item logout" onclick="window.location.href='index.php'"><i class="bi bi-power"></i><span>Logout</span></div>
  </nav>
  <div class="sidebar-footer">
    <div class="user-box">
      <div class="user-avatar"><?= htmlspecialchars($user_initial) ?></div>
      <div>
        <div class="user-name"><?= htmlspecialchars($user_fullname) ?></div>
        <div class="user-role"><?= htmlspecialchars($user_role) ?></div>
      </div>
    </div>
  </div>
</aside>

<header class="topbar">
  <div style="display:flex;align-items:center;gap:14px;">
    <button class="menu-btn" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>
    <span class="topbar-title" id="page-title">Dashboard</span>
  </div>
  <div class="topbar-right">
    <button type="button" class="lang-switch-btn" onclick="toggleLanguage()" title="Palitan ang Wika / Switch Language"><span class="lang-flag">🇵🇭</span> <span class="lang-label">Tagalog</span></button>
    <span class="tb-date"><i class="bi bi-calendar3"></i> <?= date('D, M j') ?></span><span class="tb-name"><?= htmlspecialchars($user_fullname) ?></span><div class="topbar-avatar"><?= htmlspecialchars($user_initial) ?></div>
  </div>
</header>

<main class="main-content">

  <!-- ===== DASHBOARD ===== -->
  <section id="sec-dashboard" class="section active">
  <?php
    $dn_hour      = (int)date('G');
    $dn_greet     = $dn_hour < 12 ? 'Good morning' : ($dn_hour < 18 ? 'Good afternoon' : 'Good evening');
    $dn_heads     = (int)$stats['total_livestock'];
    $dn_farm_cnt  = max(1, count($all_farms));
    $dn_appr_pct  = round(((int)$stats['approved_farms'] / $dn_farm_cnt) * 100);
    $dn_attention = (int)$stats['pending_farms'] + (int)$stats['pending_reports'] + (int)$stats['active_incidents'];
    $dn_max_type  = !empty($livestock_by_type) ? max(1, (int)max(array_column($livestock_by_type, 'total'))) : 1;
    $dn_status_clr = ['pending' => '#D97706', 'in_progress' => '#2563EB', 'resolved' => '#059669', 'closed' => '#64748B'];
    $dn_prio_clr   = ['low' => '#16A34A', 'medium' => '#D97706', 'high' => '#C2410C', 'critical' => '#DC2626'];
  ?>

  <!-- Hero -->
  <div class="an-hero">
    <div class="an-hero-text">
      <span class="an-eyebrow"><i class="bi bi-grid-1x2-fill"></i> Official Portal</span>
      <h1><?= $dn_greet ?>, <?= htmlspecialchars($user_fullname) ?></h1>
      <p><?= $dn_attention > 0
          ? 'You have <strong>' . number_format($dn_attention) . '</strong> item' . ($dn_attention === 1 ? '' : 's') . ' waiting for your attention today.'
          : 'Everything is up to date. Great work keeping the district running smoothly.' ?></p>
    </div>
    <div class="an-hero-actions">
      <span class="an-date-chip"><i class="bi bi-calendar3"></i> <?= date('l, F j, Y') ?></span>
      <button onclick="activateSection('farms')" class="an-btn an-btn-primary"><i class="bi bi-house-check-fill"></i> Review Farms</button>
      <button onclick="activateSection('map')" class="an-btn an-btn-ghost"><i class="bi bi-map-fill"></i> Open Map</button>
    </div>
  </div>

  <!-- KPI cards -->
  <div class="an-kpis dn-five">
    <div class="an-kpi" style="--kc:#10B981;--kb:#D1FAE5;">
      <div class="an-kpi-top">
        <div class="an-kpi-icon"><i class="bi bi-house-check-fill"></i></div>
        <span class="an-kpi-chip"><?= $dn_appr_pct ?>%</span>
      </div>
      <div class="an-kpi-val"><?= number_format($stats['approved_farms']) ?></div>
      <div class="an-kpi-lbl">Approved Farms</div>
      <div class="an-kpi-foot">of <?= number_format(count($all_farms)) ?> registered</div>
    </div>
    <div class="an-kpi" style="--kc:#3B82F6;--kb:#DBEAFE;">
      <div class="an-kpi-top">
        <div class="an-kpi-icon"><i class="bi bi-tags-fill"></i></div>
        <span class="an-kpi-chip"><?= count($livestock_by_type) ?> types</span>
      </div>
      <div class="an-kpi-val"><?= number_format($dn_heads) ?></div>
      <div class="an-kpi-lbl">Total Livestock</div>
      <div class="an-kpi-foot">Across all farms</div>
    </div>
    <div class="an-kpi" style="--kc:#F59E0B;--kb:#FEF3C7;">
      <div class="an-kpi-top">
        <div class="an-kpi-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
        <span class="an-kpi-chip"><i class="bi bi-broadcast"></i> Live</span>
      </div>
      <div class="an-kpi-val"><?= number_format($stats['active_incidents']) ?></div>
      <div class="an-kpi-lbl">Active Incidents</div>
      <div class="an-kpi-foot">Pending &amp; in progress</div>
    </div>
    <div class="an-kpi" style="--kc:#EF4444;--kb:#FEE2E2;">
      <div class="an-kpi-top">
        <div class="an-kpi-icon"><i class="bi bi-file-earmark-text-fill"></i></div>
        <span class="an-kpi-chip">Public</span>
      </div>
      <div class="an-kpi-val"><?= number_format($stats['pending_reports']) ?></div>
      <div class="an-kpi-lbl">Pending Reports</div>
      <div class="an-kpi-foot">Awaiting review</div>
    </div>
    <div class="an-kpi" style="--kc:#8B5CF6;--kb:#EDE9FE;">
      <div class="an-kpi-top">
        <div class="an-kpi-icon"><i class="bi bi-hourglass-split"></i></div>
        <span class="an-kpi-chip">Queue</span>
      </div>
      <div class="an-kpi-val"><?= number_format($stats['pending_farms']) ?></div>
      <div class="an-kpi-lbl">Pending Farms</div>
      <div class="an-kpi-foot">Awaiting approval</div>
    </div>
  </div>

  <!-- Livestock overview + Action center -->
  <div class="an-grid an-grid-7-5">
    <div class="an-card">
      <div class="an-card-head">
        <div><h3><i class="bi bi-diagram-3-fill"></i> Livestock Overview</h3><p>Heads registered per animal type</p></div>
        <span class="dn-total-pill">&#x1F43E; <?= number_format($dn_heads) ?> total</span>
      </div>
      <?php if (!empty($livestock_by_type)): ?>
      <div class="dn-type-grid">
        <?php foreach ($livestock_by_type as $lt):
          $t       = $lt['type'] ?: 'Other';
          $col     = $type_colors[$t] ?? '#64748B';
          $emoji   = $type_emojis[$t] ?? '🐾';
          $display = $type_display[$t] ?? $t;
          $share   = $dn_heads > 0 ? round(($lt['total'] / $dn_heads) * 100, 1) : 0;
          $bar     = round(($lt['total'] / $dn_max_type) * 100);
        ?>
        <div class="dn-type" style="--tc:<?= $col ?>;">
          <div class="dn-type-top">
            <span class="dn-type-emoji"><?= $emoji ?></span>
            <span class="dn-type-share"><?= $share ?>%</span>
          </div>
          <div class="dn-type-count"><?= number_format($lt['total']) ?></div>
          <div class="dn-type-name"><?= htmlspecialchars($display) ?></div>
          <div class="an-bar-track an-bar-thin"><div class="an-bar-fill" data-pct="<?= $bar ?>" style="width:0%;background:linear-gradient(90deg,<?= $col ?>,<?= $col ?>CC);"></div></div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="an-empty"><i class="bi bi-inbox"></i><p>No livestock data recorded yet.</p></div>
      <?php endif; ?>
    </div>

    <div class="an-card">
      <div class="an-card-head">
        <div><h3><i class="bi bi-lightning-charge-fill"></i> Action Center</h3><p>Jump straight to what needs you</p></div>
      </div>
      <div class="dn-actions">
        <button type="button" class="dn-action" style="--ac:#8B5CF6;--ab:#EDE9FE;" onclick="activateSection('farms')">
          <span class="dn-action-icon"><i class="bi bi-hourglass-split"></i></span>
          <span class="dn-action-body"><strong>Farms awaiting approval</strong><small>Review &amp; approve registrations</small></span>
          <span class="dn-action-count"><?= number_format($stats['pending_farms']) ?></span>
          <i class="bi bi-chevron-right dn-action-go"></i>
        </button>
        <button type="button" class="dn-action" style="--ac:#F59E0B;--ab:#FEF3C7;" onclick="activateSection('incidents')">
          <span class="dn-action-icon"><i class="bi bi-exclamation-triangle-fill"></i></span>
          <span class="dn-action-body"><strong>Active incidents</strong><small>Respond &amp; resolve alerts</small></span>
          <span class="dn-action-count"><?= number_format($stats['active_incidents']) ?></span>
          <i class="bi bi-chevron-right dn-action-go"></i>
        </button>
        <button type="button" class="dn-action" style="--ac:#EF4444;--ab:#FEE2E2;" onclick="activateSection('public')">
          <span class="dn-action-icon"><i class="bi bi-file-earmark-text-fill"></i></span>
          <span class="dn-action-body"><strong>Public reports</strong><small>Citizen reports to review</small></span>
          <span class="dn-action-count"><?= number_format($stats['pending_reports']) ?></span>
          <i class="bi bi-chevron-right dn-action-go"></i>
        </button>
        <button type="button" class="dn-action" style="--ac:#10B981;--ab:#D1FAE5;" onclick="activateSection('map')">
          <span class="dn-action-icon"><i class="bi bi-map-fill"></i></span>
          <span class="dn-action-body"><strong>Geo-Monitoring</strong><small>Live district map</small></span>
          <span class="dn-action-count dn-action-count-soft"><i class="bi bi-arrow-up-right"></i></span>
          <i class="bi bi-chevron-right dn-action-go"></i>
        </button>
        <button type="button" class="dn-action" style="--ac:#3B82F6;--ab:#DBEAFE;" onclick="activateSection('reports')">
          <span class="dn-action-icon"><i class="bi bi-bar-chart-line-fill"></i></span>
          <span class="dn-action-body"><strong>Analytics</strong><small>Trends &amp; insights</small></span>
          <span class="dn-action-count dn-action-count-soft"><i class="bi bi-arrow-up-right"></i></span>
          <i class="bi bi-chevron-right dn-action-go"></i>
        </button>
      </div>
    </div>
  </div>

  <!-- Recent incidents -->
  <div class="an-card dn-table-card">
    <div class="an-card-head">
      <div><h3><i class="bi bi-shield-exclamation"></i> Recent Incidents</h3><p>Latest 5 reports from the field</p></div>
      <button type="button" class="dn-link-btn" onclick="activateSection('incidents')">View all <i class="bi bi-arrow-right"></i></button>
    </div>
    <div class="table-scroll">
      <table class="dn-table">
        <thead><tr><th>Incident</th><th>Status</th><th>Priority</th><th>Reported</th><th style="text-align:right;">Action</th></tr></thead>
        <tbody>
          <?php foreach ($recent_incidents as $incident):
            $i_status_raw = (string)($incident['status'] ?? 'Pending');
            $i_status_key = strtolower(str_replace(' ', '_', $i_status_raw));
            $i_status_lbl = ucwords(str_replace('_', ' ', $i_status_key));
            $i_prio_raw   = (string)($incident['priority'] ?? 'Low');
            $i_prio_key   = strtolower($i_prio_raw);
            $i_reporter   = trim(($incident['firstName'] ?? '') . ' ' . ($incident['lastName'] ?? '')) ?: 'Unknown reporter';
            $i_open       = !in_array($i_status_key, ['resolved', 'closed'], true);
            $i_ts         = strtotime($incident['createdAt'] ?? 'now');
          ?>
          <tr id="dash-inc-<?= (int)$incident['id'] ?>">
            <td>
              <div class="dn-inc-title"><?= htmlspecialchars($incident['title'] ?? 'Untitled incident') ?></div>
              <div class="dn-inc-sub"><i class="bi bi-house-door"></i> <?= htmlspecialchars($incident['farm_name'] ?? 'N/A') ?> &middot; <i class="bi bi-person"></i> <?= htmlspecialchars($i_reporter) ?></div>
            </td>
            <td class="dash-status"><span class="dn-pill" style="--pc:<?= $dn_status_clr[$i_status_key] ?? '#64748B' ?>;"><i></i><?= htmlspecialchars($i_status_lbl) ?></span></td>
            <td><span class="dn-pill" style="--pc:<?= $dn_prio_clr[$i_prio_key] ?? '#64748B' ?>;"><i></i><?= htmlspecialchars(ucfirst($i_prio_raw)) ?></span></td>
            <td><div class="dn-date"><?= date('M j, Y', $i_ts) ?></div><div class="dn-date-sub"><?= date('g:i A', $i_ts) ?></div></td>
            <td style="text-align:right;">
              <?php if ($i_open): ?>
              <button class="dn-resolve" onclick="dashResolve(<?= (int)$incident['id'] ?>, this)"><i class="bi bi-check2-circle"></i> Resolve</button>
              <?php else: ?>
              <span class="dn-done"><i class="bi bi-check-circle-fill"></i> Resolved</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($recent_incidents)): ?>
          <tr><td colspan="5"><div class="an-empty an-empty-sm"><i class="bi bi-shield-check"></i><p>No recent incidents &mdash; all clear!</p></div></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  </section>

  <!-- ===== FARM INSPECTION ===== -->
  <section id="sec-farms" class="section">
  <div class="page-heading">
    <h1>Farm Inspection</h1>
    <p>Review full registration details before approving or rejecting.</p>
  </div>
  <div class="table-container">
    <div class="table-header">
      <h2><i class="bi bi-house-check"></i> All Farms</h2>
      <input type="text" class="search-box" placeholder="Search farms..." onkeyup="searchFarms(this.value)" style="max-width:260px;">
    </div>
    <div class="table-scroll">
      <table id="farmsTable">
        <thead><tr><th>Farm Name</th><th>Owner</th><th>Type</th><th>Status</th><th>Livestock</th><th>Actions</th></tr></thead>
        <tbody id="farmsTableBody">
          <?php foreach ($all_farms as $farm): ?>
          <tr data-farm-id="<?= $farm['id'] ?>">
            <td style="font-weight:600;"><?= htmlspecialchars($farm['name']) ?></td>
            <td><?= htmlspecialchars(($farm['firstName']??'').' '.($farm['lastName']??'')) ?></td>
            <td><?= ucfirst(str_replace('_',' ',$farm['type'])) ?></td>
            <td><span class="status-badge status-<?= strtolower($farm['status']) ?>"><?= ucfirst($farm['status']) ?></span></td>
            <td><?= number_format($farm['total_livestock'] ?? 0) ?> heads</td>
            <td style="display:flex;gap:6px;flex-wrap:wrap;">
              <!-- REVIEW button always shown -->
              <button class="btn btn-ghost" style="font-size:0.78rem;" onclick="openFarmReview(<?= $farm['id'] ?>)">
                <i class="bi bi-eye"></i> Review
              </button>
              <?php if ($farm['status'] === 'Pending'): ?>
              <button class="btn btn-primary" onclick="approveFarm(<?= $farm['id'] ?>)"><i class="bi bi-check-circle"></i> Approve</button>
              <button class="btn btn-danger" onclick="rejectFarm(<?= $farm['id'] ?>)"><i class="bi bi-x-circle"></i> Reject</button>
              <?php else: ?>
              <span style="color:var(--c-text-sub);font-size:0.82rem;font-weight:600;"><?= $farm['status'] === 'Approved' ? '✓ Approved' : '✗ Rejected' ?></span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>

<!-- FARM REVIEW MODAL -->
<div id="farmReviewModal" class="modal-overlay">
  <div class="modal-content" style="max-width:720px;">
    <div class="modal-header">
      <h3><i class="bi bi-clipboard2-check"></i> Farm Registration Review</h3>
      <button class="modal-close" onclick="closeFarmReview()">&times;</button>
    </div>
    <div class="modal-body" id="farmReviewBody">
      <div style="text-align:center;padding:40px;color:var(--c-text-sub);">Loading...</div>
    </div>
    <div class="modal-actions" id="farmReviewActions"></div>
  </div>
</div>

  <!-- ===== INCIDENTS ===== -->
  <section id="sec-incidents" class="section">
  <div class="page-heading">
    <h1>Incident Management</h1>
    <p>Active incidents. Resolved/closed items move to Archive automatically.</p>
  </div>
 
  <!-- Active incidents table (same as before, but JS removes row on resolve) -->
  <div class="table-container" style="margin-bottom:24px;">
    <div class="table-header"><h2><i class="bi bi-exclamation-triangle"></i> Active Incidents</h2></div>
    <div class="table-scroll">
      <table>
        <thead><tr><th>Title</th><th>Farm</th><th>Type</th><th>Status</th><th>Priority</th><th>Actions</th></tr></thead>
        <tbody id="activeIncidentsBody">
          <?php foreach ($all_incidents as $incident):
            if (in_array($incident['status'], ['Resolved','Closed'])) continue; ?>
          <tr id="inc-row-<?= $incident['id'] ?>">
            <td style="font-weight:600;"><?= htmlspecialchars($incident['title']) ?></td>
            <td><?= htmlspecialchars($incident['farm_name'] ?? 'N/A') ?></td>
            <td><?= ucfirst($incident['type']) ?></td>
            <td><span class="status-badge status-<?= strtolower($incident['status']) ?>"><?= ucfirst(str_replace('_',' ',$incident['status'])) ?></span></td>
            <td>
              <select class="priority-select" onchange="updateIncidentPriority(<?= $incident['id'] ?>, this.value, this)" data-orig="<?= htmlspecialchars($incident['priority']) ?>">
                <option value="Low" <?= $incident['priority']==='Low'?'selected':'' ?>>🟢 Low</option>
                <option value="Medium" <?= $incident['priority']==='Medium'?'selected':'' ?>>🟡 Medium</option>
                <option value="High" <?= $incident['priority']==='High'?'selected':'' ?>>🟠 High</option>
                <option value="Critical" <?= $incident['priority']==='Critical'?'selected':'' ?>>🔴 Critical</option>
              </select>
            </td>
            <td>
              <button class="btn btn-primary" onclick="resolveAndArchive(<?= $incident['id'] ?>, this)">
                <i class="bi bi-check2"></i> Resolve
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
 
  <!-- Archive -->
  <div class="table-container">
    <div class="table-header" style="background:linear-gradient(135deg,#374151,#1f2937);">
      <h2><i class="bi bi-archive"></i> Archived Incidents</h2>
      <button class="btn btn-ghost" style="font-size:0.78rem;color:#fff;background:rgba(255,255,255,0.15);" onclick="downloadArchive('incidents')">
        <i class="bi bi-download"></i> Export
      </button>
    </div>
    <div class="table-scroll">
      <table id="archiveIncTable">
        <thead><tr><th>Title</th><th>Farm</th><th>Type</th><th>Priority</th><th>Resolved</th></tr></thead>
        <tbody id="archiveIncBody">
          <?php foreach ($archived_incidents as $inc): ?>
          <tr>
            <td style="font-weight:600;"><?= htmlspecialchars($inc['title']) ?></td>
            <td><?= htmlspecialchars($inc['farm_name'] ?? 'N/A') ?></td>
            <td><?= ucfirst($inc['type']) ?></td>
            <td><span class="status-badge priority-<?= strtolower($inc['priority']) ?>"><?= $inc['priority'] ?></span></td>
            <td style="color:var(--c-text-sub);font-size:0.82rem;"><?= $inc['resolvedAt'] ? date('M j, Y', strtotime($inc['resolvedAt'])) : '—' ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($archived_incidents)): ?>
          <tr><td colspan="5" style="text-align:center;padding:24px;color:var(--c-text-sub);">No archived incidents yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>

  <!-- ===== PUBLIC REPORTS ===== -->
  <section id="sec-public" class="section">
  <div class="page-heading">
    <h1>Public Reports</h1>
    <p>Pending/Reviewed reports below. Resolved/Closed move to Archive.</p>
  </div>
  <div class="table-container" style="margin-bottom:24px;">
    <div class="table-header"><h2><i class="bi bi-file-earmark-text"></i> Active Reports</h2></div>
    <div class="table-scroll">
      <table>
        <thead><tr><th>Type</th><th>Description</th><th>Contact</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($public_reports as $report):
            if (in_array($report['status'], ['Resolved','Closed'])) continue; ?>
          <tr id="rpt-row-<?= $report['id'] ?>">
            <td><span class="status-badge" style="background:#EDE9FE;color:#7C3AED;"><?= ucfirst($report['reportType']) ?></span></td>
            <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($report['description']) ?>"><?= htmlspecialchars(substr($report['description'], 0, 60)) ?>...</td>
            <td style="font-size:0.85rem;"><?= htmlspecialchars($report['contactPhone']) ?></td>
            <td>
              <select class="status-select" onchange="updateReportStatusAndArchive(<?= $report['id'] ?>, this.value, this)">
                <option value="Pending" <?= $report['status']==='Pending'?'selected':'' ?>>🟡 Pending</option>
                <option value="Reviewed" <?= $report['status']==='Reviewed'?'selected':'' ?>>🔵 Reviewed</option>
                <option value="Resolved" <?= $report['status']==='Resolved'?'selected':'' ?>>🟢 Resolved</option>
                <option value="Closed" <?= $report['status']==='Closed'?'selected':'' ?>>⚫ Closed</option>
              </select>
            </td>
            <td style="color:var(--c-text-sub);font-size:0.82rem;"><?= date('M j, Y', strtotime($report['createdAt'])) ?></td>
            <td><button class="btn btn-ghost" style="font-size:0.78rem;" onclick="showToast('Report #<?= $report['id'] ?> marked for investigation.','info')"><i class="bi bi-search"></i> Investigate</button></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
 
  <div class="table-container">
    <div class="table-header" style="background:linear-gradient(135deg,#374151,#1f2937);">
      <h2><i class="bi bi-archive"></i> Archived Reports</h2>
      <button class="btn btn-ghost" style="font-size:0.78rem;color:#fff;background:rgba(255,255,255,0.15);" onclick="downloadArchive('reports')">
        <i class="bi bi-download"></i> Export
      </button>
    </div>
    <div class="table-scroll">
      <table id="archiveRptTable">
        <thead><tr><th>Type</th><th>Description</th><th>Contact</th><th>Status</th><th>Date</th></tr></thead>
        <tbody id="archiveRptBody">
          <?php foreach ($archived_reports as $rpt): ?>
          <tr>
            <td><span class="status-badge" style="background:#EDE9FE;color:#7C3AED;"><?= ucfirst($rpt['reportType']) ?></span></td>
            <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars(substr($rpt['description'],0,60)) ?>...</td>
            <td style="font-size:0.85rem;"><?= htmlspecialchars($rpt['contactPhone']) ?></td>
            <td><span class="status-badge status-<?= strtolower($rpt['status']) ?>"><?= $rpt['status'] ?></span></td>
            <td style="color:var(--c-text-sub);font-size:0.82rem;"><?= date('M j, Y', strtotime($rpt['createdAt'])) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($archived_reports)): ?>
          <tr><td colspan="5" style="text-align:center;padding:24px;color:var(--c-text-sub);">No archived reports yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>

  <!-- ===== GEO-MONITORING ===== -->
  <section id="sec-map" class="section">
    <div class="section-header">
      <div>
        <h3>Camarines Sur 1st District GIS Monitoring</h3>
        <p>Live spatial tracking of registered farms, disease alerts, and livestock density.</p>
      </div>
      <a class="btn-top btn-top-primary" href="maps/farm-map.php" target="_blank" rel="noopener">
        <i class="bi bi-arrows-fullscreen"></i>
        <span>Full Map View</span>
      </a>
    </div>

    <div class="map-section-card">
      <iframe class="map-frame-view" src="maps/farm-map.php" title="Camarines Sur 1st District GIS Map" allow="fullscreen" allowfullscreen></iframe>
    </div>
  </section>

  <!-- ===== ANALYTICS ===== -->
  <section id="sec-reports" class="section">
  <?php
    $an_farms     = (int)$analytics['totals']['total_farms'];
    $an_heads     = (int)$analytics['totals']['total_livestock'];
    $an_inc_total = (int)$analytics['totals']['total_incidents'];
    $an_inc_act   = (int)$analytics['totals']['active_incidents'];
    $an_avg_heads = $an_farms > 0 ? round($an_heads / $an_farms, 1) : 0;
    $an_res_rate  = $an_inc_total > 0 ? round((($an_inc_total - $an_inc_act) / $an_inc_total) * 100) : 100;
    $an_types     = $analytics['livestock_by_type'];
    $an_max_type  = !empty($an_types) ? max(1, (int)max(array_column($an_types, 'total_qty'))) : 1;
    $an_fs        = $an_extra['farm_status'];
    $an_fs_total  = max(1, array_sum($an_fs));
    $an_is        = $an_extra['incident_status'];
    $an_is_total  = max(1, array_sum($an_is));
    $an_ip        = $an_extra['incident_priority'];
    $an_top_max   = !empty($an_extra['top_farms']) ? max(1, (int)max(array_column($an_extra['top_farms'], 'heads'))) : 1;
    $an_fs_colors = ['Approved' => '#10B981', 'Pending' => '#F59E0B', 'Rejected' => '#EF4444'];
    $an_is_colors = ['Pending' => '#F59E0B', 'In Progress' => '#3B82F6', 'Resolved' => '#10B981', 'Closed' => '#94A3B8'];
    $an_ip_colors = ['Low' => '#FBBF24', 'Medium' => '#F97316', 'High' => '#EF4444', 'Critical' => '#B91C1C'];
  ?>

  <!-- Hero header -->
  <div class="an-hero">
    <div class="an-hero-text">
      <span class="an-eyebrow"><i class="bi bi-bar-chart-line-fill"></i> Insights Center</span>
      <h1>Reports &amp; Analytics</h1>
      <p>Livestock population, distribution, incident health and yearly trends across the district.</p>
    </div>
    <div class="an-hero-actions">
      <span class="an-date-chip"><i class="bi bi-calendar3"></i> <?= date('F j, Y') ?></span>
      <button onclick="downloadReport('pdf')" class="an-btn an-btn-primary"><i class="bi bi-file-earmark-pdf-fill"></i> PDF</button>
      <button onclick="downloadReport('excel')" class="an-btn an-btn-ghost"><i class="bi bi-file-earmark-excel-fill"></i> Excel</button>
      <button onclick="downloadReport('csv')" class="an-btn an-btn-ghost"><i class="bi bi-filetype-csv"></i> CSV</button>
    </div>
  </div>

  <!-- KPI cards -->
  <div class="an-kpis">
    <div class="an-kpi" style="--kc:#10B981;--kb:#D1FAE5;">
      <div class="an-kpi-top">
        <div class="an-kpi-icon"><i class="bi bi-house-heart-fill"></i></div>
        <span class="an-kpi-chip"><i class="bi bi-graph-up-arrow"></i> Registered</span>
      </div>
      <div class="an-kpi-val"><?= number_format($an_farms) ?></div>
      <div class="an-kpi-lbl">Total Farms</div>
      <div class="an-kpi-foot"><?= number_format($an_fs['Approved'] ?? 0) ?> approved &middot; <?= number_format($an_fs['Pending'] ?? 0) ?> pending</div>
    </div>
    <div class="an-kpi" style="--kc:#3B82F6;--kb:#DBEAFE;">
      <div class="an-kpi-top">
        <div class="an-kpi-icon"><i class="bi bi-tags-fill"></i></div>
        <span class="an-kpi-chip"><i class="bi bi-diagram-3-fill"></i> <?= count($an_types) ?> types</span>
      </div>
      <div class="an-kpi-val"><?= number_format($an_heads) ?></div>
      <div class="an-kpi-lbl">Total Livestock</div>
      <div class="an-kpi-foot">Avg. <?= $an_avg_heads ?> heads per farm</div>
    </div>
    <div class="an-kpi" style="--kc:#F59E0B;--kb:#FEF3C7;">
      <div class="an-kpi-top">
        <div class="an-kpi-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
        <span class="an-kpi-chip"><i class="bi bi-check2-circle"></i> <?= $an_res_rate ?>% resolved</span>
      </div>
      <div class="an-kpi-val"><?= number_format($an_inc_total) ?></div>
      <div class="an-kpi-lbl">Total Incidents</div>
      <div class="an-kpi-foot"><?= number_format($an_inc_total - $an_inc_act) ?> closed out</div>
    </div>
    <div class="an-kpi" style="--kc:#EF4444;--kb:#FEE2E2;">
      <div class="an-kpi-top">
        <div class="an-kpi-icon"><i class="bi bi-activity"></i></div>
        <span class="an-kpi-chip"><i class="bi bi-broadcast"></i> Live</span>
      </div>
      <div class="an-kpi-val"><?= number_format($an_inc_act) ?></div>
      <div class="an-kpi-lbl">Active Incidents</div>
      <div class="an-kpi-foot">Need attention now</div>
    </div>
  </div>

  <!-- Composition + Trend -->
  <div class="an-grid an-grid-5-7">
    <div class="an-card">
      <div class="an-card-head">
        <div><h3><i class="bi bi-pie-chart-fill"></i> Livestock Composition</h3><p>Share of heads by animal type</p></div>
      </div>
      <?php if (!empty($an_types)): ?>
      <div class="an-donut-wrap">
        <div class="an-donut-canvas">
          <canvas id="animalDonutChart"></canvas>
          <div class="an-donut-center"><strong><?= number_format($an_heads) ?></strong><span>Total heads</span></div>
        </div>
        <div class="an-legend">
          <?php foreach ($an_types as $lt):
            $t   = $lt['type'];
            $col = $type_colors[$t] ?? '#64748B';
            $pc  = $an_heads > 0 ? round(($lt['total_qty'] / $an_heads) * 100, 1) : 0;
          ?>
          <div class="an-legend-row">
            <span class="an-dot" style="background:<?= $col ?>;"></span>
            <span class="an-legend-name"><?= htmlspecialchars($type_display[$t] ?? $t) ?></span>
            <span class="an-legend-pct"><?= $pc ?>%</span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php else: ?>
      <div class="an-empty"><i class="bi bi-inbox"></i><p>No livestock data available yet.</p></div>
      <?php endif; ?>
    </div>

    <div class="an-card">
      <div class="an-card-head">
        <div><h3><i class="bi bi-graph-up-arrow"></i> Yearly Registration Trend</h3><p>Per animal type &middot; last 5 years</p></div>
      </div>
      <div class="an-trend-canvas"><canvas id="livestockTrendChart"></canvas></div>
    </div>
  </div>

  <!-- Ranked breakdown + Regional -->
  <div class="an-grid an-grid-7-5">
    <div class="an-card">
      <div class="an-card-head">
        <div><h3><i class="bi bi-bar-chart-horizontal-fill"></i> Livestock by Animal Type</h3><p>Ranked by total heads</p></div>
      </div>
      <?php if (!empty($an_types)): ?>
      <div class="an-rank-list">
        <?php foreach ($an_types as $i => $lt):
          $t     = $lt['type'];
          $col   = $type_colors[$t] ?? '#64748B';
          $emoji = $type_emojis[$t] ?? '🐾';
          $pct   = round(($lt['total_qty'] / $an_max_type) * 100);
          $share = $an_heads > 0 ? round(($lt['total_qty'] / $an_heads) * 100, 1) : 0;
        ?>
        <div class="an-rank-row">
          <span class="an-rank-no"><?= $i + 1 ?></span>
          <span class="an-rank-emoji" style="background:<?= $col ?>1A;"><?= $emoji ?></span>
          <div class="an-rank-body">
            <div class="an-rank-top">
              <strong><?= htmlspecialchars($type_display[$t] ?? $t) ?></strong>
              <span><b><?= number_format($lt['total_qty']) ?></b> heads &middot; <?= number_format($lt['farm_count']) ?> farms</span>
            </div>
            <div class="an-bar-track"><div class="an-bar-fill" data-pct="<?= $pct ?>" style="width:0%;background:linear-gradient(90deg,<?= $col ?>,<?= $col ?>CC);"></div></div>
          </div>
          <span class="an-rank-share"><?= $share ?>%</span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="an-empty"><i class="bi bi-inbox"></i><p>No livestock data available yet.</p></div>
      <?php endif; ?>
    </div>

    <div class="an-card">
      <div class="an-card-head">
        <div><h3><i class="bi bi-geo-alt-fill"></i> Regional Distribution</h3><p>Top areas by livestock</p></div>
      </div>
      <?php if (!empty($analytics['regional_stats'])): ?>
      <div class="an-region-list">
        <?php foreach ($analytics['regional_stats'] as $region):
          $rp = $an_heads > 0 ? round(($region['livestock_count'] / $an_heads) * 100, 1) : 0;
        ?>
        <div class="an-region">
          <div class="an-region-top">
            <div class="an-region-name"><span class="an-region-pin"><i class="bi bi-geo-alt-fill"></i></span><?= htmlspecialchars($region['region']) ?></div>
            <span class="an-region-pct"><?= $rp ?>%</span>
          </div>
          <div class="an-bar-track an-bar-thin"><div class="an-bar-fill" data-pct="<?= min(100, $rp) ?>" style="width:0%;background:linear-gradient(90deg,#10B981,#059669);"></div></div>
          <div class="an-region-meta"><span><i class="bi bi-house-door"></i> <?= number_format($region['farm_count']) ?> farms</span><span><i class="bi bi-tags"></i> <?= number_format($region['livestock_count']) ?> heads</span></div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="an-empty"><i class="bi bi-inbox"></i><p>No regional data available.</p></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Health overview: farms, incidents, top farms -->
  <div class="an-grid an-grid-3">
    <div class="an-card">
      <div class="an-card-head"><div><h3><i class="bi bi-patch-check-fill"></i> Farm Approval Status</h3><p><?= number_format(array_sum($an_fs)) ?> farms total</p></div></div>
      <div class="an-seg">
        <?php foreach ($an_fs as $k => $v): ?>
        <span style="width:<?= round(($v / $an_fs_total) * 100, 2) ?>%;background:<?= $an_fs_colors[$k] ?? '#94A3B8' ?>;" title="<?= htmlspecialchars($k) ?>: <?= $v ?>"></span>
        <?php endforeach; ?>
      </div>
      <div class="an-stat-list">
        <?php foreach ($an_fs as $k => $v): ?>
        <div class="an-stat-row"><span class="an-dot" style="background:<?= $an_fs_colors[$k] ?? '#94A3B8' ?>;"></span><span class="an-stat-name"><?= htmlspecialchars($k) ?></span><strong><?= number_format($v) ?></strong><em><?= round(($v / $an_fs_total) * 100) ?>%</em></div>
        <?php endforeach; ?>
        <?php if (empty($an_fs)): ?><div class="an-empty an-empty-sm"><p>No farms registered.</p></div><?php endif; ?>
      </div>
    </div>

    <div class="an-card">
      <div class="an-card-head"><div><h3><i class="bi bi-shield-exclamation"></i> Incident Overview</h3><p><?= $an_res_rate ?>% resolution rate</p></div></div>
      <div class="an-seg">
        <?php foreach ($an_is as $k => $v): ?>
        <span style="width:<?= round(($v / $an_is_total) * 100, 2) ?>%;background:<?= $an_is_colors[$k] ?? '#94A3B8' ?>;" title="<?= htmlspecialchars($k) ?>: <?= $v ?>"></span>
        <?php endforeach; ?>
      </div>
      <div class="an-stat-list">
        <?php foreach ($an_is as $k => $v): ?>
        <div class="an-stat-row"><span class="an-dot" style="background:<?= $an_is_colors[$k] ?? '#94A3B8' ?>;"></span><span class="an-stat-name"><?= htmlspecialchars($k) ?></span><strong><?= number_format($v) ?></strong><em><?= round(($v / $an_is_total) * 100) ?>%</em></div>
        <?php endforeach; ?>
        <?php if (empty($an_is)): ?><div class="an-empty an-empty-sm"><p>No incidents recorded.</p></div><?php endif; ?>
      </div>
      <?php if (!empty($an_ip)): ?>
      <div class="an-chips">
        <?php foreach ($an_ip as $k => $v): ?>
        <span class="an-chip" style="--cc:<?= $an_ip_colors[$k] ?? '#64748B' ?>;"><i></i><?= htmlspecialchars($k) ?> <b><?= number_format($v) ?></b></span>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <div class="an-card">
      <div class="an-card-head"><div><h3><i class="bi bi-trophy-fill"></i> Top Farms</h3><p>Largest herds registered</p></div></div>
      <div class="an-top-list">
        <?php foreach ($an_extra['top_farms'] as $i => $tf):
          $tp = round(($tf['heads'] / $an_top_max) * 100);
        ?>
        <div class="an-top-row">
          <span class="an-medal an-medal-<?= min($i + 1, 4) ?>"><?= $i + 1 ?></span>
          <div class="an-top-body">
            <div class="an-top-name"><?= htmlspecialchars($tf['name'] ?? 'Unnamed Farm') ?></div>
            <div class="an-bar-track an-bar-thin"><div class="an-bar-fill" data-pct="<?= $tp ?>" style="width:0%;background:linear-gradient(90deg,#3B82F6,#2563EB);"></div></div>
          </div>
          <strong class="an-top-val"><?= number_format($tf['heads']) ?></strong>
        </div>
        <?php endforeach; ?>
        <?php if (empty($an_extra['top_farms'])): ?><div class="an-empty an-empty-sm"><p>No farms yet.</p></div><?php endif; ?>
      </div>
    </div>
  </div>
</section>

  <!-- ===== PROFILE ===== -->
  <section id="sec-profile" class="section">
    <div class="page-heading">
      <h1>My Profile</h1>
      <p>Manage your official account and assignment details.</p>
    </div>
    <div class="profile-container">
      <div class="profile-card">
        <div class="profile-header">
          <div class="avatar-circle">
            <?php if (!empty($user_profile['profile_pix'])): ?>
              <img src="<?= htmlspecialchars($user_profile['profile_pix']) ?>" alt="Profile" style="width:100%;height:100%;object-fit:cover;">
            <?php else: ?>
              <?= strtoupper(substr($user_profile['firstName'] ?? 'U', 0, 1)) ?>
            <?php endif; ?>
          </div>
          <h2 style="margin:0;font-family:'Syne',sans-serif;font-size:1.4rem;"><?= htmlspecialchars(($user_profile['firstName']??'').' '.($user_profile['lastName']??'')) ?></h2>
          <p style="opacity:0.8;margin:4px 0 0;font-size:0.88rem;"><?= htmlspecialchars($user_role) ?></p>
        </div>
        <div class="profile-info">
          <div class="info-section">
            <h3><i class="bi bi-person-fill"></i> Personal Information</h3>
            <div class="info-item"><span class="info-label">Email</span><span class="info-value"><?= htmlspecialchars($user_profile['email']??'&mdash;') ?></span></div>
            <div class="info-item"><span class="info-label">Mobile</span><span class="info-value"><?= htmlspecialchars($user_profile['mobile']??'&mdash;') ?></span></div>
          </div>
          <div class="info-section">
            <h3><i class="bi bi-briefcase-fill"></i> Work Information</h3>
            <div class="info-item"><span class="info-label">Gov't ID</span><span class="info-value"><?= htmlspecialchars($user_profile['gov_id']??'&mdash;') ?></span></div>
            <div class="info-item"><span class="info-label">Department</span><span class="info-value"><?= htmlspecialchars($user_profile['department']??'&mdash;') ?></span></div>
            <div class="info-item"><span class="info-label">Position</span><span class="info-value"><?= htmlspecialchars($user_profile['position']??'&mdash;') ?></span></div>
            <div class="info-item"><span class="info-label">Office</span><span class="info-value"><?= htmlspecialchars($user_profile['office']??'&mdash;') ?></span></div>
          </div>
          <div class="info-section">
            <h3><i class="bi bi-geo-alt-fill"></i> Assignment</h3>
            <div class="info-item"><span class="info-label">Region</span><span class="info-value"><?= htmlspecialchars($user_profile['assigned_region']??'&mdash;') ?></span></div>
            <div class="info-item"><span class="info-label">Province</span><span class="info-value"><?= htmlspecialchars($user_profile['province']??'&mdash;') ?></span></div>
            <div class="info-item"><span class="info-label">Municipality</span><span class="info-value"><?= htmlspecialchars($user_profile['municipality']??'&mdash;') ?></span></div>
          </div>
        </div>
        <div class="profile-actions">
          <button class="btn-edit" onclick="openEditModal()"><i class="bi bi-pencil-square"></i> Edit Profile</button>
        </div>
      </div>
    </div>

    <div id="editProfileModal" class="modal-overlay">
      <div class="modal-content">
        <div class="modal-header">
          <h3><i class="bi bi-pencil-square"></i> Edit Profile</h3>
          <button class="modal-close" onclick="closeEditModal()">&times;</button>
        </div>
        <div class="modal-body">
          <form id="profileForm" enctype="multipart/form-data">
            <input type="hidden" name="action" value="update_profile">
            <input type="hidden" name="user_id" value="<?= $user_id ?>">
            <div class="form-row-2">
              <div class="form-group">
                <label class="form-label">First Name</label>
                <input class="form-input" type="text" name="firstName" value="<?= htmlspecialchars($user_profile['firstName']??'') ?>" required>
              </div>
              <div class="form-group">
                <label class="form-label">Last Name</label>
                <input class="form-input" type="text" name="lastName" value="<?= htmlspecialchars($user_profile['lastName']??'') ?>" required>
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Mobile</label>
              <input class="form-input" type="text" name="mobile" value="<?= htmlspecialchars($user_profile['mobile']??'') ?>">
            </div>
            <div class="form-row-2">
              <div class="form-group">
                <label class="form-label">Gov't ID</label>
                <input class="form-input" type="text" name="gov_id" value="<?= htmlspecialchars($user_profile['gov_id']??'') ?>">
              </div>
              <div class="form-group">
                <label class="form-label">Department</label>
                <input class="form-input" type="text" name="department" value="<?= htmlspecialchars($user_profile['department']??'') ?>">
              </div>
            </div>
            <div class="form-row-2">
              <div class="form-group">
                <label class="form-label">Position</label>
                <input class="form-input" type="text" name="position" value="<?= htmlspecialchars($user_profile['position']??'') ?>">
              </div>
              <div class="form-group">
                <label class="form-label">Office</label>
                <input class="form-input" type="text" name="office" value="<?= htmlspecialchars($user_profile['office']??'') ?>">
              </div>
            </div>
            <div class="form-row-2">
              <div class="form-group">
                <label class="form-label">Region</label>
                <input class="form-input" type="text" name="assigned_region" value="<?= htmlspecialchars($user_profile['assigned_region']??'') ?>">
              </div>
              <div class="form-group">
                <label class="form-label">Province</label>
                <input class="form-input" type="text" name="province" value="<?= htmlspecialchars($user_profile['province']??'') ?>">
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Municipality</label>
              <input class="form-input" type="text" name="municipality" value="<?= htmlspecialchars($user_profile['municipality']??'') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">New Password <span style="color:var(--c-text-sub);font-weight:400;">(leave blank to keep)</span></label>
              <input class="form-input" type="password" name="new_password" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;">
            </div>
            <div class="form-group">
              <label class="form-label">Profile Photo</label>
              <input class="form-input" type="file" name="profile_pic" accept="image/*">
            </div>
          </form>
        </div>
        <div class="modal-actions">
          <button class="btn-cancel" onclick="closeEditModal()">Cancel</button>
          <button class="btn-save" onclick="submitProfile()"><i class="bi bi-check2"></i> Save Changes</button>
        </div>
      </div>
    </div>
  </section>

</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<?php if (!empty($googleMapsApiKey)): ?>
<script src="https://maps.googleapis.com/maps/api/js?key=<?= htmlspecialchars($googleMapsApiKey) ?>&callback=initGoogleOfficialMap" async defer></script>
<?php endif; ?>
<script>
const farmsData         = <?= json_encode($all_farms) ?>;
const incidentsData     = <?= json_encode($all_incidents) ?>;
const publicReportsData = <?= json_encode($map_public_reports ?? []) ?>;

function closeSidebar() {
  const sb = document.getElementById('sidebar');
  const ov = document.getElementById('overlay');
  if (sb) sb.classList.remove('open', 'hover-open');
  if (ov) ov.classList.remove('open');
}

function toggleSidebar() {
  const sb = document.getElementById('sidebar');
  const ov = document.getElementById('overlay');
  if (window.innerWidth <= 1024) {
    const isOpen = sb.classList.toggle('open');
    ov.classList.toggle('open', isOpen);
  } else {
    if (sb.classList.contains('hover-open') || sb.classList.contains('open')) {
      sb.classList.remove('hover-open', 'open');
      ov.classList.remove('open');
    } else {
      sb.classList.add('hover-open');
    }
  }
}

function navTo(id, el) {
  document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
  const targetSec = document.getElementById('sec-' + id);
  if (targetSec) targetSec.classList.add('active');
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  if (el) {
    el.classList.add('active');
    const span = el.querySelector('span');
    if (span) document.getElementById('page-title').innerText = span.innerText;
  }
  closeSidebar();
  if (id === 'reports') {
    animateBars();
    ['livestockTrendChart', 'animalDonutChart'].forEach(cid => { const c = (typeof Chart !== 'undefined') ? Chart.getChart(cid) : null; if (c) c.resize(); });
  }
  if (id === 'map') {
    const mapFrame = document.querySelector('.map-frame-view');
    if (mapFrame && mapFrame.contentWindow) {
      try { mapFrame.contentWindow.postMessage({ action: 'resize' }, '*'); } catch (e) {}
      setTimeout(() => { try { mapFrame.contentWindow.postMessage({ action: 'resize' }, '*'); } catch (e) {} }, 150);
      setTimeout(() => { try { mapFrame.contentWindow.postMessage({ action: 'resize' }, '*'); } catch (e) {} }, 350);
    }
  }
}

// ── OFFICIAL AUTO-HIDE / FULL VIEW ENGINE ─────────────────────────
const daSidebar     = document.getElementById('sidebar');
const daHoverZone   = document.getElementById('daHoverZone');
const daEdgeTrigger = document.getElementById('daEdgeTrigger');
const daPinBtn      = document.getElementById('daPinBtn');
const daOverlay     = document.getElementById('overlay');

let isDaAutoHide = localStorage.getItem('agritrace_da_autohide') !== 'false';

function applyDaAutoHide(enabled) {
  isDaAutoHide = enabled;
  localStorage.setItem('agritrace_da_autohide', enabled ? 'true' : 'false');
  if (enabled) {
    document.body.classList.add('sidebar-autohide');
    if (daPinBtn) daPinBtn.innerHTML = '<i class="bi bi-pin-angle" title="Click to Pin Sidebar Open"></i>';
  } else {
    document.body.classList.remove('sidebar-autohide');
    if (daSidebar) daSidebar.classList.remove('hover-open');
    if (daPinBtn) daPinBtn.innerHTML = '<i class="bi bi-pin-fill" title="Click to Auto-Hide for Full View"></i>';
  }
  setTimeout(() => {
    window.dispatchEvent(new Event('resize'));
    ['livestockTrendChart', 'animalDonutChart'].forEach(cid => { 
      const c = (typeof Chart !== 'undefined') ? Chart.getChart(cid) : null; 
      if (c) c.resize(); 
    });
    const mapFrame = document.querySelector('.map-frame-view');
    if (mapFrame && mapFrame.contentWindow) {
      try { mapFrame.contentWindow.postMessage({ action: 'resize' }, '*'); } catch (e) {}
    }
  }, 300);
}

applyDaAutoHide(isDaAutoHide);

daPinBtn?.addEventListener('click', (e) => {
  e.stopPropagation();
  applyDaAutoHide(!isDaAutoHide);
});

let daHoverTimer = null;
function openDaHover() {
  if (!document.body.classList.contains('sidebar-autohide') || window.innerWidth <= 1024) return;
  clearTimeout(daHoverTimer);
  daSidebar?.classList.add('hover-open');
}
function closeDaHover() {
  if (!document.body.classList.contains('sidebar-autohide') || window.innerWidth <= 1024) return;
  clearTimeout(daHoverTimer);
  daHoverTimer = setTimeout(() => {
    daSidebar?.classList.remove('hover-open');
  }, 300);
}

daHoverZone?.addEventListener('mouseenter', openDaHover);
daEdgeTrigger?.addEventListener('mouseenter', openDaHover);
daEdgeTrigger?.addEventListener('click', (e) => {
  e.stopPropagation();
  daSidebar?.classList.toggle('hover-open');
});

daSidebar?.addEventListener('mouseenter', () => clearTimeout(daHoverTimer));
daSidebar?.addEventListener('mouseleave', closeDaHover);

document.addEventListener('mousemove', (e) => {
  if (!document.body.classList.contains('sidebar-autohide') || window.innerWidth <= 1024) return;
  if (e.clientX <= 20) {
    openDaHover();
  } else if (e.clientX > 320 && daSidebar?.classList.contains('hover-open')) {
    closeDaHover();
  }
});

daOverlay?.addEventListener('click', closeSidebar);

document.addEventListener('keydown', (e) => {
  if ((e.ctrlKey && e.key.toLowerCase() === 'b') || (e.key === '[' && !['INPUT','TEXTAREA','SELECT'].includes(document.activeElement?.tagName))) {
    e.preventDefault();
    applyDaAutoHide(!isDaAutoHide);
  }
});

function activateSection(id) {
  const navEl = document.querySelector(`.nav-item[onclick*="'${id}'"]`);
  navTo(id, navEl);
}
window.activateSection = activateSection;

// Listen for cross-frame messages (e.g. Map "Dashboard" return button)
window.addEventListener('message', (event) => {
  if (event.data && event.data.action === 'nav' && event.data.section) {
    activateSection(event.data.section);
  }
});

function searchFarms(q) {
  document.querySelectorAll('#farmsTable tbody tr').forEach(r => {
    r.style.display = r.textContent.toLowerCase().includes(q.toLowerCase()) ? '' : 'none';
  });
}

async function approveFarm(farmId) {
  if (!confirm('Approve this farm?')) return;
  showLoading(true);
  try {
    const res = await fetch(window.location.href, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`action=approve_farm&farm_id=${farmId}` });
    const r = await res.json();
    if (r.success) { updateFarmRow(farmId, 'Approved'); showToast('Farm approved!', 'success'); }
    else showToast(r.message, 'error');
  } catch { showToast('Network error', 'error'); }
  finally { showLoading(false); }
}

async function rejectFarm(farmId) {
  if (!confirm('Reject this farm?')) return;
  showLoading(true);
  try {
    const res = await fetch(window.location.href, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`action=reject_farm&farm_id=${farmId}` });
    const r = await res.json();
    if (r.success) { updateFarmRow(farmId, 'Rejected'); showToast('Farm rejected.', 'success'); }
    else showToast(r.message, 'error');
  } catch { showToast('Network error', 'error'); }
  finally { showLoading(false); }
}

function updateFarmRow(id, status) {
  const row = document.querySelector(`#farmsTable tr[data-farm-id="${id}"]`);
  if (!row) return;
  row.cells[3].innerHTML = `<span class="status-badge status-${status.toLowerCase()}">${status}</span>`;
  row.cells[5].innerHTML = `<span style="color:var(--c-text-sub);font-size:0.82rem;font-weight:600;">${status === 'Approved' ? '\u2713 Approved' : '\u2717 Rejected'}</span>`;
}

async function updateIncidentPriority(incidentId, priority, selectEl) {
  showLoading(true);
  try {
    const res = await fetch(window.location.href, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`action=update_incident_priority&incident_id=${incidentId}&priority=${encodeURIComponent(priority)}` });
    const r = await res.json();
    if (r.success) { selectEl.setAttribute('data-orig', priority); showToast(`Priority updated to ${priority}`, 'success'); }
    else { selectEl.value = selectEl.getAttribute('data-orig'); showToast(r.message, 'error'); }
  } catch { selectEl.value = selectEl.getAttribute('data-orig'); showToast('Network error', 'error'); }
  finally { showLoading(false); }
}

async function updateReportStatus(reportId, status, selectEl) {
  showLoading(true);
  try {
    const res = await fetch(window.location.href, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`action=update_report_priority&report_id=${reportId}&status=${encodeURIComponent(status)}` });
    const r = await res.json();
    if (r.success) { showToast(`Status updated to ${status}`, 'success'); }
    else { showToast(r.message, 'error'); }
  } catch { showToast('Network error', 'error'); }
  finally { showLoading(false); }
}

async function resolveIncident(id, btn) {
  if (!confirm('Mark this incident as resolved?')) return;
  showLoading(true);
  try {
    const res = await fetch(window.location.href, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`action=resolve_incident&incident_id=${id}` });
    const r = await res.json();
    if (r.success) {
      showToast('Incident resolved.', 'success');
      const row = document.getElementById('inc-row-' + id);
      if (row) {
        row.cells[3].innerHTML = `<span class="status-badge status-resolved">Resolved</span>`;
        row.cells[5].innerHTML = `<span style="color:var(--c-success);font-size:0.82rem;font-weight:700;"><i class="bi bi-check-circle-fill"></i> Resolved</span>`;
      }
      if (btn) { btn.disabled = true; btn.innerHTML = '<i class="bi bi-check2-circle"></i> Done'; }
    } else showToast(r.message, 'error');
  } catch { showToast('Network error', 'error'); }
  finally { showLoading(false); }
}

async function dashResolve(id, btn) {
  await resolveIncident(id, btn);
  if (btn && btn.disabled) {
    const row = document.getElementById('dash-inc-' + id);
    const cell = row ? row.querySelector('.dash-status') : null;
    if (cell) cell.innerHTML = '<span class="dn-pill" style="--pc:#059669;"><i></i>Resolved</span>';
  }
}
// ── MAP (Google Maps Engine) ────────────────────────────────────────────────
let officialMap = null;
let officialInfoWindow = null;
const mapMarkers = { farms: [], livestock: [], incidents: [], reports: [] };

function getLegendHTML(layer) {
  if (layer === 'farms') return `<h4><i class="bi bi-house-door" style="color:var(--c-brand-accent);"></i> Farm Legend</h4><div class="leg-item"><div class="leg-dot" style="background:#10B981;"></div> Approved</div><div class="leg-item"><div class="leg-dot" style="background:#EF4444;"></div> Rejected</div><div class="leg-item"><div class="leg-dot" style="background:#F59E0B;"></div> Pending</div>`;
  if (layer === 'livestock') return `<h4><i class="bi bi-tags" style="color:#3B82F6;"></i> Livestock Density</h4><div class="leg-item"><div class="leg-dot" style="background:#10B981;"></div> Low (&lt;10)</div><div class="leg-item"><div class="leg-dot" style="background:#F59E0B;"></div> Medium (10-20)</div><div class="leg-item"><div class="leg-dot" style="background:#EF4444;"></div> High (&gt;20)</div>`;
  if (layer === 'incidents') return `<h4><i class="bi bi-exclamation-triangle" style="color:#EF4444;"></i> Incident Priority</h4><div class="leg-item"><div class="leg-dot" style="background:#FBBF24;"></div> Low</div><div class="leg-item"><div class="leg-dot" style="background:#F97316;"></div> Medium</div><div class="leg-item"><div class="leg-dot" style="background:#EF4444;"></div> High/Critical</div><div class="leg-item"><div class="leg-dot" style="background:#10B981;"></div> Resolved</div>`;
  if (layer === 'reports') return `<h4><i class="bi bi-file-earmark-text" style="color:#3B82F6;"></i> Public Reports</h4><div class="leg-item"><div class="leg-dot" style="background:#F59E0B;"></div> Pending</div><div class="leg-item"><div class="leg-dot" style="background:#3B82F6;"></div> Reviewed</div><div class="leg-item"><div class="leg-dot" style="background:#10B981;"></div> Resolved</div><div class="leg-item"><div class="leg-dot" style="background:#6B7280;"></div> Closed</div>`;
  return '';
}

function initOfficialMap() {
  if (officialMap) {
    if (typeof google !== 'undefined' && google.maps) {
      google.maps.event.trigger(officialMap, 'resize');
    }
    return;
  }
  if (typeof google === 'undefined' || !google.maps) {
    const el = document.getElementById('officialMap');
    if (el && !el.querySelector('iframe')) {
      el.innerHTML = '<iframe src="maps/farm-map.php" style="width:100%;height:100%;min-height:540px;border:none;border-radius:12px;" title="Camarines Sur 1st District GIS Map"></iframe>';
      const leg = document.getElementById('mapLegend');
      if (leg) leg.style.display = 'none';
    }
    return;
  }
  const el = document.getElementById('officialMap');
  if (!el) return;

  officialMap = new google.maps.Map(el, {
    center: { lat: 13.803, lng: 122.858 },
    zoom: 10,
    mapTypeId: 'roadmap',
    mapTypeControl: true,
    fullscreenControl: true,
    streetViewControl: false
  });

  officialInfoWindow = new google.maps.InfoWindow();

  buildFarmsLayer();
  buildIncidentsLayer();
  buildPublicReportsLayer();
  buildLivestockLayer();

  showLayerMarkers('farms');
  updateLegend('farms');
}

window.initGoogleOfficialMap = function() {
  const mapSec = document.getElementById('sec-map');
  if (mapSec && mapSec.classList.contains('active')) {
    initOfficialMap();
  }
};

function buildFarmsLayer() {
  farmsData.forEach(f => {
    if (!f.latitude || !f.longitude) return;
    const color = f.status === 'Approved' ? '#10B981' : f.status === 'Pending' ? '#F59E0B' : '#EF4444';
    const marker = new google.maps.Marker({
      position: { lat: parseFloat(f.latitude), lng: parseFloat(f.longitude) },
      title: f.name,
      icon: {
        path: google.maps.SymbolPath.CIRCLE,
        scale: 7,
        fillColor: color,
        fillOpacity: 0.9,
        strokeColor: '#ffffff',
        strokeWeight: 2
      }
    });
    marker.addListener('click', () => {
      officialInfoWindow.setContent(
        `<div style="min-width:180px;font-family:'DM Sans',sans-serif;padding:6px;">` +
        `<b style="font-size:0.95rem;">${escapeHtml(f.name)}</b><br>` +
        `<span style="color:#64748b;font-size:0.8rem;">Owner:</span> ${(f.firstName||'')+' '+(f.lastName||'')}<br>` +
        `<span style="color:#64748b;font-size:0.8rem;">Livestock:</span> ${f.total_livestock||0} heads<br>` +
        `<span style="color:#64748b;font-size:0.8rem;">Status:</span> <b style="color:${color}">${f.status}</b>` +
        `</div>`
      );
      officialInfoWindow.open(officialMap, marker);
    });
    mapMarkers.farms.push(marker);
  });
}

function buildIncidentsLayer() {
  incidentsData.forEach(i => {
    if (!i.latitude || !i.longitude) return;
    const color = (i.status === 'Resolved' || i.status === 'Closed') ? '#10B981' : (i.priority === 'Critical' || i.priority === 'High') ? '#EF4444' : i.priority === 'Medium' ? '#F97316' : '#FBBF24';
    const marker = new google.maps.Marker({
      position: { lat: parseFloat(i.latitude), lng: parseFloat(i.longitude) },
      title: i.title,
      icon: {
        path: 'M -8,6 L 0,-10 L 8,6 Z',
        scale: 1.1,
        fillColor: color,
        fillOpacity: 0.9,
        strokeColor: '#ffffff',
        strokeWeight: 1.5
      }
    });
    marker.addListener('click', () => {
      officialInfoWindow.setContent(
        `<div style="font-family:'DM Sans',sans-serif;padding:6px;min-width:180px;">` +
        `<b>${escapeHtml(i.title)}</b><br>` +
        `<span style="color:#64748b;">Priority:</span> <b>${escapeHtml(i.priority)}</b><br>` +
        `<span style="color:#64748b;">Status:</span> ${escapeHtml(i.status)}` +
        `</div>`
      );
      officialInfoWindow.open(officialMap, marker);
    });
    mapMarkers.incidents.push(marker);
  });
}

function buildPublicReportsLayer() {
  const statusColors = { 'Pending': '#F59E0B', 'Reviewed': '#3B82F6', 'Resolved': '#10B981', 'Closed': '#6B7280' };
  publicReportsData.forEach(r => {
    if (!r.latitude || !r.longitude) return;
    const color = statusColors[r.status] || '#F59E0B';
    const desc = (r.description || '').substring(0, 80) + ((r.description || '').length > 80 ? '…' : '');
    const dateStr = r.createdAt ? new Date(r.createdAt).toLocaleDateString('en-PH', {month:'short',day:'numeric',year:'numeric'}) : '';
    const marker = new google.maps.Marker({
      position: { lat: parseFloat(r.latitude), lng: parseFloat(r.longitude) },
      title: `Report #${r.id}`,
      icon: {
        path: google.maps.SymbolPath.CIRCLE,
        scale: 6,
        fillColor: color,
        fillOpacity: 0.9,
        strokeColor: '#ffffff',
        strokeWeight: 2
      }
    });
    marker.addListener('click', () => {
      officialInfoWindow.setContent(
        `<div style="font-family:'DM Sans',sans-serif;min-width:200px;padding:6px;">` +
        `<div style="font-weight:700;margin-bottom:4px;">📋 Report #${r.id}</div>` +
        `<div style="font-size:0.8rem;margin-bottom:2px;"><b>Type:</b> ${escapeHtml(r.reportType)}</div>` +
        `<div style="font-size:0.78rem;color:#555;margin-bottom:6px;line-height:1.4;">${escapeHtml(desc)}</div>` +
        `<div style="font-size:0.75rem;"><b>Status:</b> <span style="color:${color};font-weight:700;">${escapeHtml(r.status)}</span></div>` +
        `<div style="font-size:0.72rem;color:#888;margin-top:2px;">${dateStr}</div>` +
        `</div>`
      );
      officialInfoWindow.open(officialMap, marker);
    });
    mapMarkers.reports.push(marker);
  });
}

function buildLivestockLayer() {
  farmsData.forEach(f => {
    if (!f.latitude || !f.longitude) return;
    const qty = parseInt(f.total_livestock) || 0;
    const color = qty < 10 ? '#10B981' : qty <= 20 ? '#F59E0B' : '#EF4444';
    const r = Math.min(Math.max(5, Math.sqrt(qty) * 2), 16);
    const marker = new google.maps.Marker({
      position: { lat: parseFloat(f.latitude), lng: parseFloat(f.longitude) },
      title: `${f.name}: ${qty} heads`,
      icon: {
        path: google.maps.SymbolPath.CIRCLE,
        scale: r,
        fillColor: color,
        fillOpacity: 0.82,
        strokeColor: '#ffffff',
        strokeWeight: 2
      }
    });
    marker.addListener('click', () => {
      officialInfoWindow.setContent(
        `<div style="font-family:'DM Sans',sans-serif;padding:6px;">` +
        `<b>${escapeHtml(f.name)}</b><br>` +
        `Livestock: <b style="color:${color};">${qty} heads</b>` +
        `</div>`
      );
      officialInfoWindow.open(officialMap, marker);
    });
    mapMarkers.livestock.push(marker);
  });
}

function showLayerMarkers(type) {
  Object.keys(mapMarkers).forEach(k => {
    mapMarkers[k].forEach(m => m.setMap(null));
  });
  if (mapMarkers[type]) {
    mapMarkers[type].forEach(m => m.setMap(officialMap));
  }
}

function switchMapLayer(type, btn) {
  if (!officialMap) return;
  document.querySelectorAll('.toggle-map-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  showLayerMarkers(type);
  updateLegend(type);
}

function updateLegend(layer) { document.getElementById('mapLegend').innerHTML = getLegendHTML(layer); }

function fitMapPH() {
  if (!officialMap) return;
  const bounds = new google.maps.LatLngBounds(
    new google.maps.LatLng(13.645, 122.505),
    new google.maps.LatLng(13.955, 123.18)
  );
  officialMap.fitBounds(bounds);
}

function escapeHtml(value) {
  return String(value ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

function animateBars() {
  document.querySelectorAll('.bar-fill, .an-bar-fill').forEach(bar => {
    const pct = bar.getAttribute('data-pct');
    setTimeout(() => { bar.style.width = pct + '%'; }, 100);
  });
}

function openEditModal() { document.getElementById('editProfileModal').classList.add('active'); document.body.style.overflow = 'hidden'; }
function closeEditModal() { document.getElementById('editProfileModal').classList.remove('active'); document.body.style.overflow = ''; }
document.getElementById('editProfileModal').addEventListener('click', function(e) { if (e.target === this) closeEditModal(); });

async function submitProfile() {
  showLoading(true);
  try {
    const fd = new FormData(document.getElementById('profileForm'));
    const res = await fetch(window.location.href, { method:'POST', body: fd });
    const r = await res.json();
    if (r.success) { showToast('Profile updated!', 'success'); closeEditModal(); setTimeout(() => location.reload(), 1000); }
    else showToast(r.message, 'error');
  } catch { showToast('Update failed', 'error'); }
  finally { showLoading(false); }
}

function downloadReport(format) {
  showToast(`Generating ${format.toUpperCase()} report...`, 'info');
  fetch('../api/generate_report.php', {
    method: 'POST', headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({ format, user_id: <?= $user_id ?> })
  }).then(r => r.blob()).then(blob => {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a'); a.href = url;
    a.download = `AgriTrace_Report_${new Date().toISOString().slice(0,10)}.${format}`;
    document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
    showToast('Downloaded!', 'success');
  }).catch(() => showToast('Download failed.', 'error'));
}

function showLoading(v) { document.getElementById('loadingOverlay').style.display = v ? 'flex' : 'none'; }

function showToast(msg, type) {
  const bg = type === 'success' ? '#10B981' : type === 'error' ? '#EF4444' : '#3B82F6';
  const t = document.createElement('div');
  t.style.cssText = `position:fixed;top:80px;right:20px;background:${bg};color:#fff;padding:12px 20px;border-radius:10px;font-weight:700;z-index:9999;transform:translateX(420px);transition:transform 0.3s ease;box-shadow:0 8px 24px rgba(0,0,0,0.2);font-family:'DM Sans',sans-serif;font-size:0.88rem;max-width:300px;`;
  t.textContent = msg;
  document.body.appendChild(t);
  setTimeout(() => t.style.transform = 'translateX(0)', 50);
  setTimeout(() => { t.style.transform = 'translateX(420px)'; setTimeout(() => t.remove(), 400); }, 3000);
}

// ── YEARLY TREND DATA from PHP ─────────────────────────────────
const yearlyTrend = <?= json_encode($yearly_trend) ?>;
const typeColors = {
  Cattle:'#3B82F6', Swine:'#F59E0B', Poultry:'#EC4899',
  Goat:'#8B5CF6',   Sheep:'#10B981', Horse:'#F97316',
  Rabbit:'#EF4444', Carabao:'#6B7280', Other:'#64748B'
};
const typeEmojis = {
  Cattle:'🐄', Swine:'🐖', Poultry:'🐓', Goat:'🐐',
  Sheep:'🐑',  Horse:'🐴', Rabbit:'🐇', Carabao:'🐃', Other:'🐾'
};
 
const anComposition = <?= json_encode(array_map(fn($lt) => [
  'label' => $type_display[$lt['type']] ?? $lt['type'],
  'value' => (int)$lt['total_qty'],
  'color' => $type_colors[$lt['type']] ?? '#64748B',
], $analytics['livestock_by_type'])) ?>;

function initTrendChart() {
  if (typeof Chart === 'undefined') return;
  Chart.defaults.font.family = "'DM Sans', sans-serif";
  Chart.defaults.color = '#64748B';
  initDonutChart();
  initLineChart();
}

function initDonutChart() {
  const canvas = document.getElementById('animalDonutChart');
  if (!canvas || !anComposition.length) return;
  const total = anComposition.reduce((s, d) => s + d.value, 0) || 1;
  new Chart(canvas, {
    type: 'doughnut',
    data: {
      labels: anComposition.map(d => d.label),
      datasets: [{
        data: anComposition.map(d => d.value),
        backgroundColor: anComposition.map(d => d.color),
        borderColor: '#fff', borderWidth: 3, hoverOffset: 8, borderRadius: 6, spacing: 1
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false, cutout: '74%',
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: '#0F172A', padding: 12, cornerRadius: 10, displayColors: true, boxPadding: 4,
          callbacks: { label: c => ` ${c.label}: ${c.parsed.toLocaleString()} heads (${((c.parsed / total) * 100).toFixed(1)}%)` }
        }
      }
    }
  });
}

function initLineChart() {
  const canvas = document.getElementById('livestockTrendChart');
  if (!canvas) return;
  const years = Object.keys(yearlyTrend).sort();
  if (!years.length) {
    canvas.parentElement.innerHTML = '<div class="an-empty"><i class="bi bi-graph-up"></i><p>No yearly data yet.</p></div>';
    return;
  }
  const allTypes = new Set();
  Object.values(yearlyTrend).forEach(yr => Object.keys(yr).forEach(t => allTypes.add(t)));

  const datasets = [...allTypes].map(type => {
    const color = typeColors[type] || '#64748B';
    return {
      label: (typeEmojis[type] || '') + ' ' + type,
      data: years.map(yr => yearlyTrend[yr]?.[type] || 0),
      borderColor: color,
      backgroundColor: (c) => {
        const { ctx, chartArea } = c.chart;
        if (!chartArea) return color + '22';
        const g = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
        g.addColorStop(0, color + '33'); g.addColorStop(1, color + '00');
        return g;
      },
      fill: true, tension: 0.4, borderWidth: 2.5,
      pointRadius: 4, pointHoverRadius: 7, pointBackgroundColor: '#fff', pointBorderColor: color, pointBorderWidth: 2.5
    };
  });

  new Chart(canvas, {
    type: 'line',
    data: { labels: years, datasets },
    options: {
      responsive: true, maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { position: 'bottom', labels: { font: { size: 12, weight: '600' }, padding: 16, usePointStyle: true, pointStyle: 'circle', boxWidth: 8 } },
        tooltip: {
          backgroundColor: '#0F172A', padding: 12, cornerRadius: 10, boxPadding: 4,
          titleFont: { weight: 'bold' },
          callbacks: { label: ctx => ` ${ctx.dataset.label}: ${ctx.parsed.y.toLocaleString()} heads` }
        }
      },
      scales: {
        x: { grid: { display: false }, border: { display: false }, ticks: { font: { weight: '600' } } },
        y: { beginAtZero: true, border: { display: false }, grid: { color: '#F1F5F9' }, ticks: { callback: v => v.toLocaleString() } }
      }
    }
  });
}
// ── RESOLVE + AUTO-ARCHIVE (incidents) ────────────────────────
async function resolveAndArchive(id, btn) {
  if (!confirm('Mark this incident as resolved? It will move to Archive.')) return;
  showLoading(true);
  try {
    const res = await fetch(window.location.href, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `action=resolve_incident&incident_id=${id}`
    });
    const r = await res.json();
    if (r.success) {
      showToast('Incident resolved & archived.', 'success');
      // Remove from active list
      const row = document.getElementById('inc-row-' + id);
      if (row) { row.style.transition = 'opacity 0.4s'; row.style.opacity = '0'; setTimeout(() => row.remove(), 420); }
    } else showToast(r.message, 'error');
  } catch { showToast('Network error', 'error'); }
  finally { showLoading(false); }
}
 
// ── UPDATE REPORT STATUS + AUTO-ARCHIVE ───────────────────────
async function updateReportStatusAndArchive(reportId, status, selectEl) {
  showLoading(true);
  try {
    const res = await fetch(window.location.href, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `action=update_report_priority&report_id=${reportId}&status=${encodeURIComponent(status)}`
    });
    const r = await res.json();
    if (r.success) {
      showToast(`Status updated to ${status}`, 'success');
      if (status === 'Resolved' || status === 'Closed') {
        const row = document.getElementById('rpt-row-' + reportId);
        if (row) { row.style.transition = 'opacity 0.4s'; row.style.opacity = '0'; setTimeout(() => row.remove(), 420); }
      }
    } else showToast(r.message, 'error');
  } catch { showToast('Network error', 'error'); }
  finally { showLoading(false); }
}
 
// ── FARM REVIEW MODAL ─────────────────────────────────────────
const farmsFullData = <?= json_encode($all_farms) ?>;
const allIncidentsData = <?= json_encode($all_incidents) ?>;
 
async function openFarmReview(farmId) {
  const modal = document.getElementById('farmReviewModal');
  const body  = document.getElementById('farmReviewBody');
  const actions = document.getElementById('farmReviewActions');
  modal.classList.add('active');
  document.body.style.overflow = 'hidden';
 
  const farm = farmsFullData.find(f => parseInt(f.id) === parseInt(farmId));
  if (!farm) { body.innerHTML = '<p style="color:red;padding:20px;">Farm not found.</p>'; return; }
 
  // Build review HTML
  const yesNo = v => v === 'Yes' ? '<span style="color:#059669;font-weight:700;">✓ Yes</span>' : '<span style="color:#dc2626;font-weight:700;">✗ No</span>';
  const val   = v => v ? `<strong>${v}</strong>` : '<span style="color:#94a3b8;">—</span>';
 
  body.innerHTML = `
    ${farm.photo_url ? `<img src="${farm.photo_url}" class="review-photo" alt="Farm photo">` : ''}
    <div class="review-section">
      <h4>🌾 Farm Information</h4>
      <div class="review-grid">
        <div class="review-item"><div class="r-label">Farm Name</div><div class="r-value">${farm.name}</div></div>
        <div class="review-item"><div class="r-label">Farm Type</div><div class="r-value">${farm.type}</div></div>
        <div class="review-item"><div class="r-label">Address</div><div class="r-value">${farm.address||'—'}</div></div>
        <div class="review-item"><div class="r-label">Area (ha)</div><div class="r-value">${farm.area||'—'}</div></div>
        <div class="review-item"><div class="r-label">Region</div><div class="r-value">${farm.region||'—'}</div></div>
        <div class="review-item"><div class="r-label">Business Type</div><div class="r-value">${farm.business_type||'—'}</div></div>
      </div>
    </div>
    <div class="review-section">
      <h4>👤 Petitioner / Owner</h4>
      <div class="review-grid">
        <div class="review-item"><div class="r-label">Full Name</div><div class="r-value">${farm.petitioner_firstname||''} ${farm.petitioner_surname||''}</div></div>
        <div class="review-item"><div class="r-label">Owner (account)</div><div class="r-value">${(farm.firstName||'')+' '+(farm.lastName||'')}</div></div>
        <div class="review-item"><div class="r-label">Sex</div><div class="r-value">${farm.pet_sex||'—'}</div></div>
        <div class="review-item"><div class="r-label">Civil Status</div><div class="r-value">${farm.pet_civil_status||'—'}</div></div>
        <div class="review-item"><div class="r-label">Date of Birth</div><div class="r-value">${farm.pet_dob||'—'}</div></div>
        <div class="review-item"><div class="r-label">Place of Birth</div><div class="r-value">${farm.pet_pob||'—'}</div></div>
        <div class="review-item"><div class="r-label">Barangay</div><div class="r-value">${farm.pet_barangay||'—'}</div></div>
        <div class="review-item"><div class="r-label">Municipality</div><div class="r-value">${farm.pet_municipality||'—'}</div></div>
        <div class="review-item"><div class="r-label">Education</div><div class="r-value">${farm.pet_education||'—'}</div></div>
        <div class="review-item"><div class="r-label">Religion</div><div class="r-value">${farm.pet_religion||'—'}</div></div>
        <div class="review-item"><div class="r-label">Household Members</div><div class="r-value">M: ${farm.pet_male_members||0} / F: ${farm.pet_female_members||0}</div></div>
        <div class="review-item"><div class="r-label">Household Head?</div><div class="r-value">${yesNo(farm.hh_head)}</div></div>
        <div class="review-item"><div class="r-label">PWD?</div><div class="r-value">${yesNo(farm.pwd)}</div></div>
        <div class="review-item"><div class="r-label">4P's Beneficiary?</div><div class="r-value">${yesNo(farm.fourps)}</div></div>
        <div class="review-item"><div class="r-label">Indigenous Group?</div><div class="r-value">${yesNo(farm.indigenous)}${farm.indigenous_specify?` (${farm.indigenous_specify})`:''}
        </div></div>
        <div class="review-item"><div class="r-label">Gov't ID?</div><div class="r-value">${yesNo(farm.has_gov_id)}${farm.gov_id_number?' — '+farm.gov_id_number:''}</div></div>
        <div class="review-item"><div class="r-label">Farmers Assoc?</div><div class="r-value">${yesNo(farm.farmers_assoc)}${farm.farmers_assoc_name?' — '+farm.farmers_assoc_name:''}</div></div>
        <div class="review-item"><div class="r-label">Emergency Contact</div><div class="r-value">${farm.emergency_contact_name||'—'} ${farm.emergency_contact_no||''}</div></div>
        <div class="review-item"><div class="r-label">Income (Farming)</div><div class="r-value">₱${parseFloat(farm.income_farming||0).toLocaleString()}</div></div>
        <div class="review-item"><div class="r-label">Income (Non-farming)</div><div class="r-value">₱${parseFloat(farm.income_non_farming||0).toLocaleString()}</div></div>
      </div>
    </div>
    <div class="review-section">
      <h4>🐄 Livestock Registered (${parseInt(farm.total_livestock||0).toLocaleString()} heads)</h4>
      <div id="reviewLivestockList"><p style="color:#94a3b8;font-size:0.85rem;">Loading livestock...</p></div>
    </div>
    <div class="review-section">
      <h4>⚠️ Farm Incidents (${allIncidentsData.filter(i=>parseInt(i.farmId)===parseInt(farmId)).length})</h4>
      ${allIncidentsData.filter(i=>parseInt(i.farmId)===parseInt(farmId)).length === 0
        ? '<p style="color:#94a3b8;font-size:0.85rem;">No incidents recorded for this farm.</p>'
        : allIncidentsData.filter(i=>parseInt(i.farmId)===parseInt(farmId)).map(i=>`
          <div class="review-livestock-row">
            <span class="status-badge priority-${i.priority.toLowerCase()}">${i.priority}</span>
            <span style="font-weight:600;font-size:0.85rem;">${i.title}</span>
            <span style="color:#64748b;font-size:0.78rem;margin-left:auto;">${i.status}</span>
          </div>`).join('')
      }
    </div>
    <div class="review-section">
      <h4>📍 GPS Location</h4>
      ${farm.latitude && farm.longitude
        ? `<p style="font-size:0.85rem;color:#374151;font-weight:600;">📍 ${farm.latitude}, ${farm.longitude}
           <a href="https://maps.google.com/?q=${farm.latitude},${farm.longitude}" target="_blank" rel="noopener" style="color:var(--c-brand-accent);margin-left:8px;">Open in Maps ↗</a></p>`
        : '<p style="color:#94a3b8;font-size:0.85rem;">No GPS data recorded.</p>'
      }
    </div>
  `;
 
  // Action buttons
  if (farm.status === 'Pending') {
    actions.innerHTML = `
      <button class="btn-cancel" onclick="closeFarmReview()">Close</button>
      <button class="btn btn-danger" onclick="rejectFarm(${farm.id});closeFarmReview();" style="padding:11px 18px;border-radius:10px;">✗ Reject</button>
      <button class="btn-save" onclick="approveFarm(${farm.id});closeFarmReview();"><i class="bi bi-check2"></i> Approve Farm</button>
    `;
  } else {
    actions.innerHTML = `<button class="btn-cancel" onclick="closeFarmReview()">Close</button>
      <span style="padding:11px 18px;font-weight:700;color:${farm.status==='Approved'?'#059669':'#dc2626'};">${farm.status==='Approved'?'✓ Approved':'✗ Rejected'}</span>`;
  }
 
  // Fetch livestock for this farm
  try {
    const lvRes = await fetch(`../api/get_farm_livestock.php?farm_id=${farmId}`);
    if (lvRes.ok) {
      const lvData = await lvRes.json();
      const lvEl = document.getElementById('reviewLivestockList');
      if (lvEl && lvData.success && lvData.livestock && lvData.livestock.length) {
        lvEl.innerHTML = lvData.livestock.map(lv => `
          <div class="review-livestock-row">
            <span style="font-size:1.2rem;">${{Cattle:'🐄',Swine:'🐖',Poultry:'🐓',Goat:'🐐',Carabao:'🐃',Horse:'🐴',Sheep:'🐑',Rabbit:'🐇'}[lv.type]||'🐾'}</span>
            <div>
              <div style="font-weight:700;font-size:0.85rem;">${lv.tagId} — ${lv.type}${lv.breed?' ('+lv.breed+')':''}</div>
              <div style="font-size:0.75rem;color:#64748b;">Qty: ${parseInt(lv.qty).toLocaleString()} · ${lv.healthStatus} ${lv.age ? '· ' + lv.age + ' mos' : ''} ${lv.weight ? '· ' + lv.weight + ' kg' : ''} ${lv.vaccinationStatus ? '· Vax: ' + lv.vaccinationStatus : ''}</div>
              ${lv.notes ? `<div style="font-size:0.72rem;color:#475569;margin-top:2px;font-style:italic;">${lv.notes}</div>` : ''}
            </div>
          </div>`).join('');
      } else if (lvEl) {
        lvEl.innerHTML = '<p style="color:#94a3b8;font-size:0.85rem;">No livestock records.</p>';
      }
    }
  } catch (e) {
    const lvEl = document.getElementById('reviewLivestockList');
    if (lvEl) lvEl.innerHTML = '<p style="color:#94a3b8;font-size:0.85rem;">Could not load livestock (api/get_farm_livestock.php needed).</p>';
  }
}
 
function closeFarmReview() {
  document.getElementById('farmReviewModal').classList.remove('active');
  document.body.style.overflow = '';
}
 
// ── ARCHIVE DOWNLOAD ──────────────────────────────────────────
function downloadArchive(type) {
  const tableId = type === 'incidents' ? 'archiveIncTable' : 'archiveRptTable';
  const table = document.getElementById(tableId);
  if (!table) return;
  let csv = '';
  table.querySelectorAll('tr').forEach(row => {
    const cols = [...row.querySelectorAll('th,td')].map(c => '"' + c.textContent.trim().replace(/"/g,'""') + '"');
    csv += cols.join(',') + '\n';
  });
  const blob = new Blob([csv], { type: 'text/csv' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = `AgriTrace_${type}_archive_${new Date().toISOString().slice(0,10)}.csv`;
  a.click();
  URL.revokeObjectURL(a.href);
  showToast('Archive exported as CSV!', 'success');
}
 
// ── INIT CHART when analytics tab is shown ────────────────────
const _origNavTo = typeof navTo === 'function' ? navTo : null;
// Patch navTo to init chart
document.querySelectorAll('.nav-item').forEach(el => {
  el.addEventListener('click', () => {
    if (el.querySelector('span')?.textContent?.trim() === 'Analytics') {
      setTimeout(() => { initTrendChart(); animateBars(); }, 300);
    }
  });
});
// Also auto-init if already on analytics
if (document.getElementById('sec-reports')?.classList.contains('active')) {
  setTimeout(initTrendChart, 500);
}
</script>
<script src="../assets/js/translator.js"></script>
</body>
</html>