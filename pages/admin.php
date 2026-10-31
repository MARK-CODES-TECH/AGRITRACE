<?php
require_once __DIR__ . '/init.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['PHP_SELF']));
    exit;
}

if (($_SESSION['user_role'] ?? '') !== 'Admin') {
    header('Location: dashboard.php');
    exit;
}

$adminId = (int)($_SESSION['user_id'] ?? 0);
$adminName = trim(($_SESSION['firstName'] ?? '') . ' ' . ($_SESSION['lastName'] ?? ''));
$adminName = $adminName !== '' ? $adminName : 'Admin';
$adminInitial = strtoupper(substr($adminName, 0, 1));

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

function h($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function districtAddressCondition(string $alias): string {
    $address = "LOWER(COALESCE({$alias}.address, ''))";
    return "({$address} LIKE '%cabusao%'
        OR {$address} LIKE '%del gallego%'
        OR {$address} LIKE '%lupi%'
        OR {$address} LIKE '%ragay%'
        OR {$address} LIKE '%sipocot%')";
}

function districtCoordinateCondition(string $alias): string {
    return "({$alias}.latitude BETWEEN 13.645 AND 13.955 AND {$alias}.longitude BETWEEN 122.505 AND 123.180)";
}

function districtMunicipalitySql(string $alias): string {
    $address = "LOWER(COALESCE({$alias}.address, ''))";
    return "CASE
        WHEN {$address} LIKE '%cabusao%' THEN 'Cabusao'
        WHEN {$address} LIKE '%del gallego%' THEN 'Del Gallego'
        WHEN {$address} LIKE '%lupi%' THEN 'Lupi'
        WHEN {$address} LIKE '%ragay%' THEN 'Ragay'
        WHEN {$address} LIKE '%sipocot%' THEN 'Sipocot'
        ELSE '1st District'
    END";
}

function setFlash(string $type, string $message): void {
    $_SESSION['admin_flash'] = ['type' => $type, 'message' => $message];
}

function addAudit(PDO $pdo, int $userId, string $action, string $table, int $recordId, array $details = []): void {
    $stmt = $pdo->prepare('INSERT INTO audit_log (userId, action, tableName, recordId, ipAddress, userAgent, details) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $userId > 0 ? $userId : null,
        $action,
        $table,
        $recordId,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null,
        json_encode($details, JSON_UNESCAPED_SLASHES),
    ]);
}

function redirectAdmin(string $section): void {
    header('Location: admin.php#' . $section);
    exit;
}

function sendCsv(string $filename, array $rows): void {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    if (!empty($rows)) {
        fputcsv($output, array_keys($rows[0]));
        foreach ($rows as $row) {
            fputcsv($output, $row);
        }
    } else {
        fputcsv($output, ['No records found']);
    }
    fclose($output);
    exit;
}

function getDistrictFarms(PDO $pdo): array {
    $addressCondition = districtAddressCondition('f');
    $coordinateCondition = districtCoordinateCondition('f');
    $municipalitySql = districtMunicipalitySql('f');

    $stmt = $pdo->query("
        SELECT
            f.*,
            {$municipalitySql} AS municipality,
            u.firstName,
            u.lastName,
            u.email,
            COUNT(l.id) AS livestock_records,
            COALESCE(SUM(l.qty), 0) AS total_livestock,
            GROUP_CONCAT(DISTINCT l.type ORDER BY l.type SEPARATOR ', ') AS livestock_types
        FROM farms f
        LEFT JOIN users u ON f.ownerId = u.id
        LEFT JOIN livestock l ON l.farmId = f.id
        WHERE {$addressCondition} OR {$coordinateCondition}
        GROUP BY
            f.id, f.name, f.ownerId, f.ownerName, f.type, f.address, f.latitude, f.longitude,
            f.area, f.status, f.createdAt, f.updatedAt, u.firstName, u.lastName, u.email
        ORDER BY FIELD(f.status, 'Pending', 'Approved', 'Rejected'), municipality, f.name
    ");

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getDistrictLivestock(PDO $pdo): array {
    $addressCondition = districtAddressCondition('f');
    $coordinateCondition = districtCoordinateCondition('f');
    $municipalitySql = districtMunicipalitySql('f');

    $stmt = $pdo->query("
        SELECT
            l.*,
            f.name AS farm_name,
            f.status AS farm_status,
            {$municipalitySql} AS municipality,
            u.firstName,
            u.lastName
        FROM livestock l
        INNER JOIN farms f ON l.farmId = f.id
        LEFT JOIN users u ON f.ownerId = u.id
        WHERE {$addressCondition} OR {$coordinateCondition}
        ORDER BY l.createdAt DESC, l.id DESC
    ");

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getCombinedIncidents(PDO $pdo): array {
    $farmAddressCondition = districtAddressCondition('f');
    $farmCoordinateCondition = districtCoordinateCondition('f');
    $incidentCoordinateCondition = districtCoordinateCondition('i');
    $municipalitySql = districtMunicipalitySql('f');

    $farmIncidents = $pdo->query("
        SELECT
            i.id,
            'incidents' AS source_table,
            'Farmer Incident' AS source,
            i.title,
            i.type,
            i.description,
            i.priority,
            i.status,
            COALESCE(i.latitude, f.latitude) AS latitude,
            COALESCE(i.longitude, f.longitude) AS longitude,
            i.createdAt,
            {$municipalitySql} AS municipality,
            f.name AS farm_name,
            CONCAT(COALESCE(u.firstName, ''), ' ', COALESCE(u.lastName, '')) AS reporter,
            u.mobile AS contactPhone,
            u.email AS contactEmail
        FROM incidents i
        LEFT JOIN farms f ON i.farmId = f.id
        LEFT JOIN users u ON i.reporterId = u.id
        WHERE {$incidentCoordinateCondition} OR {$farmAddressCondition} OR {$farmCoordinateCondition}
    ")->fetchAll(PDO::FETCH_ASSOC);

    $publicReports = $pdo->query("
        SELECT
            pr.id,
            'public_reports' AS source_table,
            'Public Report' AS source,
            CONCAT(pr.reportType, ' Report') AS title,
            pr.reportType AS type,
            pr.description,
            'Public' AS priority,
            pr.status,
            pr.latitude,
            pr.longitude,
            pr.createdAt,
            '1st District' AS municipality,
            NULL AS farm_name,
            'Public reporter' AS reporter,
            pr.contactPhone,
            pr.contactEmail
        FROM public_reports pr
        WHERE " . districtCoordinateCondition('pr') . "
    ")->fetchAll(PDO::FETCH_ASSOC);

    $rows = array_merge($farmIncidents, $publicReports);
    usort($rows, static function (array $a, array $b): int {
        return strcmp((string)$b['createdAt'], (string)$a['createdAt']);
    });

    return $rows;
}

if (isset($_GET['export'])) {
    $export = strtolower((string)$_GET['export']);
    if ($export === 'farmers') {
        $rows = $pdo->query("
            SELECT u.id, u.firstName, u.lastName, u.email, u.mobile, u.status, u.createdAt,
                   COUNT(DISTINCT f.id) AS farms,
                   COALESCE(SUM(l.qty), 0) AS livestock
            FROM users u
            LEFT JOIN farms f ON f.ownerId = u.id
            LEFT JOIN livestock l ON l.farmId = f.id
            WHERE u.role = 'Farmer'
            GROUP BY u.id
            ORDER BY u.createdAt DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
        sendCsv('agritrace_farmers_' . date('Ymd_His') . '.csv', $rows);
    }

    if ($export === 'farms') {
        sendCsv('agritrace_first_district_farms_' . date('Ymd_His') . '.csv', getDistrictFarms($pdo));
    }

    if ($export === 'livestock') {
        sendCsv('agritrace_livestock_' . date('Ymd_His') . '.csv', getDistrictLivestock($pdo));
    }

    if ($export === 'incidents') {
        sendCsv('agritrace_incidents_' . date('Ymd_His') . '.csv', getCombinedIncidents($pdo));
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!hash_equals($csrfToken, (string)($_POST['csrf_token'] ?? ''))) {
        setFlash('error', 'Security token expired. Please try again.');
        redirectAdmin('dashboard');
    }

    $action = (string)($_POST['action'] ?? '');
    $recordId = (int)($_POST['id'] ?? 0);

    try {
        switch ($action) {
            case 'approve_farmer':
                $stmt = $pdo->prepare("UPDATE users SET status = 'Active', updatedAt = CURRENT_TIMESTAMP WHERE id = ? AND role = 'Farmer'");
                $stmt->execute([$recordId]);
                addAudit($pdo, $adminId, 'APPROVE_FARMER', 'users', $recordId);
                setFlash('success', 'Farmer account approved.');
                redirectAdmin('farmers');

            case 'reject_farmer':
                $stmt = $pdo->prepare("UPDATE users SET status = 'Inactive', updatedAt = CURRENT_TIMESTAMP WHERE id = ? AND role = 'Farmer'");
                $stmt->execute([$recordId]);
                addAudit($pdo, $adminId, 'REJECT_FARMER', 'users', $recordId);
                setFlash('success', 'Farmer account marked inactive.');
                redirectAdmin('farmers');
            
            case 'approve_official':
                $stmt = $pdo->prepare("UPDATE users SET status = 'Active', updatedAt = CURRENT_TIMESTAMP WHERE id = ? AND role = 'Agriculture Official'");
                $stmt->execute([$recordId]);
                addAudit($pdo, $adminId, 'APPROVE_OFFICIAL', 'users', $recordId);
                setFlash('success', 'Agriculture Official account approved.');
                redirectAdmin('users');

            case 'reject_official':
                $stmt = $pdo->prepare("UPDATE users SET status = 'Inactive', updatedAt = CURRENT_TIMESTAMP WHERE id = ? AND role = 'Agriculture Official'");
                $stmt->execute([$recordId]);
                addAudit($pdo, $adminId, 'REJECT_OFFICIAL', 'users', $recordId);
                setFlash('success', 'Agriculture Official account rejected.');
                redirectAdmin('users');

            case 'activate_farmer':
                $stmt = $pdo->prepare("UPDATE users SET status = 'Active', updatedAt = CURRENT_TIMESTAMP WHERE id = ? AND role = 'Farmer'");
                $stmt->execute([$recordId]);
                addAudit($pdo, $adminId, 'ACTIVATE_FARMER', 'users', $recordId);
                setFlash('success', 'Farmer account activated.');
                redirectAdmin('farmers');

            case 'deactivate_farmer':
                $stmt = $pdo->prepare("UPDATE users SET status = 'Inactive', updatedAt = CURRENT_TIMESTAMP WHERE id = ? AND role = 'Farmer'");
                $stmt->execute([$recordId]);
                addAudit($pdo, $adminId, 'DEACTIVATE_FARMER', 'users', $recordId);
                setFlash('success', 'Farmer account deactivated.');
                redirectAdmin('farmers');

            case 'approve_farm':
                $stmt = $pdo->prepare("UPDATE farms SET status = 'Approved', updatedAt = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$recordId]);
                addAudit($pdo, $adminId, 'APPROVE_FARM', 'farms', $recordId);
                setFlash('success', 'Farm registration approved.');
                redirectAdmin('farms');

            case 'reject_farm':
                $reason = trim((string)($_POST['reason'] ?? ''));
                $stmt = $pdo->prepare("UPDATE farms SET status = 'Rejected', updatedAt = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$recordId]);
                addAudit($pdo, $adminId, 'REJECT_FARM', 'farms', $recordId, ['reason' => $reason]);
                setFlash('success', 'Farm registration rejected.');
                redirectAdmin('farms');

            case 'update_incident_status':
                $sourceTable = (string)($_POST['source_table'] ?? '');
                $status = (string)($_POST['status'] ?? '');
                if ($sourceTable === 'incidents' && in_array($status, ['Pending', 'In Progress', 'Resolved', 'Closed'], true)) {
                    $resolvedSql = in_array($status, ['Resolved', 'Closed'], true) ? ', resolvedAt = CURRENT_TIMESTAMP' : '';
                    $stmt = $pdo->prepare("UPDATE incidents SET status = ?, updatedAt = CURRENT_TIMESTAMP {$resolvedSql} WHERE id = ?");
                    $stmt->execute([$status, $recordId]);
                    addAudit($pdo, $adminId, 'UPDATE_INCIDENT_STATUS', 'incidents', $recordId, ['status' => $status]);
                    setFlash('success', 'Incident status updated.');
                } elseif ($sourceTable === 'public_reports' && in_array($status, ['Pending', 'Reviewed', 'Resolved', 'Closed'], true)) {
                    $reviewedSql = in_array($status, ['Reviewed', 'Resolved', 'Closed'], true) ? ', reviewedAt = CURRENT_TIMESTAMP' : '';
                    $stmt = $pdo->prepare("UPDATE public_reports SET status = ? {$reviewedSql} WHERE id = ?");
                    $stmt->execute([$status, $recordId]);
                    addAudit($pdo, $adminId, 'UPDATE_PUBLIC_REPORT_STATUS', 'public_reports', $recordId, ['status' => $status]);
                    setFlash('success', 'Public report status updated.');
                } else {
                    setFlash('error', 'Invalid report status.');
                }
                redirectAdmin('incidents');

            default:
                setFlash('error', 'Unknown admin action.');
                redirectAdmin('dashboard');
        }
    } catch (Throwable $e) {
        setFlash('error', 'Action failed: ' . $e->getMessage());
        redirectAdmin('dashboard');
    }
}

$farmers = $pdo->query("
    SELECT u.id, u.firstName, u.lastName, u.email, u.mobile, u.status, u.createdAt, u.updatedAt,
           COUNT(DISTINCT f.id) AS farm_count,
           COALESCE(SUM(l.qty), 0) AS total_livestock
    FROM users u
    LEFT JOIN farms f ON f.ownerId = u.id
    LEFT JOIN livestock l ON l.farmId = f.id
    WHERE u.role = 'Farmer'
    GROUP BY u.id
    ORDER BY FIELD(u.status, 'Pending', 'Active', 'Inactive'), u.createdAt DESC
")->fetchAll(PDO::FETCH_ASSOC);

$farms = getDistrictFarms($pdo);
$livestock = getDistrictLivestock($pdo);
$incidents = getCombinedIncidents($pdo);

$auditLogs = $pdo->query("
    SELECT al.*, u.firstName, u.lastName, u.email
    FROM audit_log al
    LEFT JOIN users u ON al.userId = u.id
    ORDER BY al.createdAt DESC
    LIMIT 100
")->fetchAll(PDO::FETCH_ASSOC);

// All users data for User Management
$allUsers = $pdo->query("
    SELECT u.*, 
           COUNT(DISTINCT f.id) AS farm_count,
           COALESCE(SUM(l.qty), 0) AS total_livestock,
           op.department,
           op.position,
           op.municipality
    FROM users u
    LEFT JOIN farms f ON f.ownerId = u.id
    LEFT JOIN livestock l ON l.farmId = f.id
    LEFT JOIN officer_profiles op ON u.id = op.user_id
    GROUP BY u.id, u.firstName, u.lastName, u.email, u.password, u.mobile, u.role, 
             u.status, u.createdAt, u.updatedAt, u.resetToken, u.resetExpires,
             op.department, op.position, op.municipality
    ORDER BY FIELD(u.role, 'Admin', 'Agriculture Official', 'Farmer'), 
             FIELD(u.status, 'Pending', 'Active', 'Inactive'), u.createdAt DESC
")->fetchAll(PDO::FETCH_ASSOC);

$municipalities = ['Cabusao', 'Del Gallego', 'Lupi', 'Ragay', 'Sipocot'];
$municipalitySummary = [];
foreach ($municipalities as $name) {
    $municipalitySummary[$name] = ['farms' => 0, 'approved' => 0, 'pending' => 0, 'livestock' => 0];
}
foreach ($farms as $farm) {
    $name = $farm['municipality'] ?? '1st District';
    if (!isset($municipalitySummary[$name])) {
        $municipalitySummary[$name] = ['farms' => 0, 'approved' => 0, 'pending' => 0, 'livestock' => 0];
    }
    $municipalitySummary[$name]['farms']++;
    if ($farm['status'] === 'Approved') {
        $municipalitySummary[$name]['approved']++;
    }
    if ($farm['status'] === 'Pending') {
        $municipalitySummary[$name]['pending']++;
    }
    $municipalitySummary[$name]['livestock'] += (int)$farm['total_livestock'];
}

$stats = [
    'farmers' => count($farmers),
    'pending_farmers' => count(array_filter($farmers, static fn($row) => $row['status'] === 'Pending')),
    'approved_farms' => count(array_filter($farms, static fn($row) => $row['status'] === 'Approved')),
    'pending_farms' => count(array_filter($farms, static fn($row) => $row['status'] === 'Pending')),
    'livestock' => array_sum(array_map(static fn($row) => (int)$row['qty'], $livestock)),
    'active_reports' => count(array_filter($incidents, static fn($row) => in_array($row['status'], ['Pending', 'In Progress', 'Reviewed'], true))),
    'high_priority' => count(array_filter($incidents, static fn($row) => in_array($row['priority'], ['High', 'Critical'], true))),
    'total_users' => count($allUsers),
    'officials' => count(array_filter($allUsers, static fn($row) => $row['role'] === 'Agriculture Official')),
];

// ── Dashboard analytics (modern overview) ────────────────────────────────────
$lvByType = [];
$lvHealth = ['Healthy' => 0, 'Sick' => 0, 'Recovered' => 0, 'Deceased' => 0];
foreach ($livestock as $lv) {
    $qty = max(1, (int)($lv['qty'] ?? 1));
    $t = $lv['type'] ?? 'Other';
    $lvByType[$t] = ($lvByType[$t] ?? 0) + $qty;
    $hs = $lv['healthStatus'] ?? 'Healthy';
    if (isset($lvHealth[$hs])) { $lvHealth[$hs] += $qty; }
}
arsort($lvByType);
$lvTotal = array_sum($lvHealth);
$healthyPct = $lvTotal > 0 ? round(($lvHealth['Healthy'] + $lvHealth['Recovered']) / $lvTotal * 100) : 100;

$incByStatus = ['Pending' => 0, 'In Progress' => 0, 'Reviewed' => 0, 'Resolved' => 0, 'Closed' => 0];
foreach ($incidents as $inc) {
    if (isset($incByStatus[$inc['status']])) { $incByStatus[$inc['status']]++; }
}

$weekBars = [];
for ($i = 6; $i >= 0; $i--) {
    $weekBars[date('Y-m-d', strtotime("-{$i} day"))] = ['label' => date('D', strtotime("-{$i} day")), 'count' => 0];
}
foreach ($farms as $f) {
    $d = !empty($f['createdAt']) ? date('Y-m-d', strtotime($f['createdAt'])) : null;
    if ($d && isset($weekBars[$d])) { $weekBars[$d]['count']++; }
}
$weekMax = max(1, max(array_column($weekBars, 'count')));

$pendingFarmList = array_slice(array_values(array_filter($farms, static fn($r) => $r['status'] === 'Pending')), 0, 5);
$hour = (int)date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');


$flash = $_SESSION['admin_flash'] ?? null;
unset($_SESSION['admin_flash']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgriTrace+ | Modern Admin Command Center</title>
    
    <!-- Clean Corporate Typography (DM Sans Exclusively) & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        :root {
            /* Clean Corporate Typography: DM Sans Exclusively */
            --font-family: 'DM Sans', system-ui, -apple-system, sans-serif;
            --font-heading: 'DM Sans', system-ui, -apple-system, sans-serif;
            --font-body: 'DM Sans', system-ui, -apple-system, sans-serif;

            /* Palette: Enterprise Obsidian Emerald & Crisp Light Canvas */
            --sidebar-bg: #031b14;
            --sidebar-surface: rgba(255, 255, 255, 0.05);
            --sidebar-border: rgba(255, 255, 255, 0.08);
            --sidebar-text: #94a3b8;
            --sidebar-text-active: #ffffff;
            
            --bg-main: #f8fafc;
            --panel-bg: #ffffff;
            --panel-border: #e2e8f0;
            --panel-hover: #f1f5f9;
            
            --primary: #059669;
            --primary-light: #10b981;
            --primary-dark: #047857;
            --primary-soft: #ecfdf5;
            
            --accent: #0284c7;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #6366f1;
            
            --text-primary: #0f172a;
            --text-secondary: #334155;
            --text-muted: #64748b;
            --text-dim: #94a3b8;
            
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.04);
            --shadow-card: 0 4px 20px -2px rgba(15, 23, 42, 0.05), 0 2px 6px -1px rgba(15, 23, 42, 0.02);
            --shadow-elevated: 0 12px 30px -4px rgba(15, 23, 42, 0.08), 0 4px 10px -2px rgba(15, 23, 42, 0.03);
            --radius-card: 16px;
            --radius-pill: 9999px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            -webkit-tap-highlight-color: transparent;
        }

        html {
            scroll-behavior: smooth;
            -webkit-text-size-adjust: 100%;
            text-size-adjust: 100%;
            overflow-x: hidden;
            width: 100%;
        }

        body {
            min-height: 100vh;
            display: flex;
            background: var(--bg-main);
            color: var(--text-primary);
            font-family: var(--font-body);
            letter-spacing: -0.011em;
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
            width: 100%;
            max-width: 100vw;
        }

        h1, h2, h3, h4, h5, h6, p, span, a, label, strong, em, td, th {
            overflow-wrap: break-word;
            word-wrap: break-word;
        }

        a { color: inherit; text-decoration: none; }
        button, input, select, textarea { font: inherit; }

        /* Custom Modern Scrollbars for main content only */
        html, body, .main-canvas, .table-responsive {
            scrollbar-width: thin;
            scrollbar-color: rgba(148, 163, 184, 0.3) transparent;
        }
        html::-webkit-scrollbar,
        body::-webkit-scrollbar,
        .main-canvas::-webkit-scrollbar,
        .table-responsive::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        html::-webkit-scrollbar-track,
        body::-webkit-scrollbar-track,
        .main-canvas::-webkit-scrollbar-track,
        .table-responsive::-webkit-scrollbar-track {
            background: transparent;
        }
        html::-webkit-scrollbar-thumb,
        body::-webkit-scrollbar-thumb,
        .main-canvas::-webkit-scrollbar-thumb,
        .table-responsive::-webkit-scrollbar-thumb {
            background: rgba(148, 163, 184, 0.3);
            border-radius: 9999px;
        }
        html::-webkit-scrollbar-thumb:hover,
        body::-webkit-scrollbar-thumb:hover,
        .main-canvas::-webkit-scrollbar-thumb:hover,
        .table-responsive::-webkit-scrollbar-thumb:hover {
            background: rgba(148, 163, 184, 0.5);
        }

        /* ── COMPLETE SCROLLBAR ERADICATION FOR SIDEBAR ─────────────────── */
        .sidebar,
        #sidebar,
        .sidebar-nav,
        nav[aria-label="Admin Navigation"] {
            scrollbar-width: none !important;
            -ms-overflow-style: none !important;
        }
        .sidebar::-webkit-scrollbar,
        #sidebar::-webkit-scrollbar,
        .sidebar-nav::-webkit-scrollbar,
        nav[aria-label="Admin Navigation"]::-webkit-scrollbar {
            display: none !important;
            width: 0 !important;
            height: 0 !important;
            background: transparent !important;
            -webkit-appearance: none !important;
        }
        .sidebar::-webkit-scrollbar-thumb,
        #sidebar::-webkit-scrollbar-thumb,
        .sidebar-nav::-webkit-scrollbar-thumb,
        nav[aria-label="Admin Navigation"]::-webkit-scrollbar-thumb {
            display: none !important;
            width: 0 !important;
            height: 0 !important;
            background: transparent !important;
        }
        .sidebar::-webkit-scrollbar-track,
        #sidebar::-webkit-scrollbar-track,
        .sidebar-nav::-webkit-scrollbar-track,
        nav[aria-label="Admin Navigation"]::-webkit-scrollbar-track {
            display: none !important;
            background: transparent !important;
        }
        .sidebar::-webkit-scrollbar-button,
        #sidebar::-webkit-scrollbar-button,
        .sidebar-nav::-webkit-scrollbar-button,
        nav[aria-label="Admin Navigation"]::-webkit-scrollbar-button {
            display: none !important;
            width: 0 !important;
            height: 0 !important;
        }

        /* ── MODERN SLEEK SIDEBAR ────────────────────────────────────────── */
        .sidebar {
            position: fixed;
            inset: 0 auto 0 0;
            width: 280px;
            display: flex;
            flex-direction: column;
            background: linear-gradient(180deg, #022c22 0%, #064e3b 60%, #021e17 100%);
            color: #ffffff;
            border-right: 1px solid rgba(255, 255, 255, 0.08);
            z-index: 50;
            transition: transform 0.28s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.28s ease;
            overflow: hidden !important;
            user-select: none;
        }

        .brand-container {
            padding: 22px 22px 20px;
            border-bottom: 1px solid var(--sidebar-border);
            display: flex;
            align-items: center;
            gap: 14px;
            flex-shrink: 0;
            position: relative;
        }

        .btn-sidebar-pin {
            margin-left: auto;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: #a7f3d0;
            display: grid;
            place-items: center;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-sidebar-pin:hover {
            background: rgba(255, 255, 255, 0.22);
            color: #ffffff;
            transform: scale(1.05);
        }

        body:not(.sidebar-autohide) .btn-sidebar-pin {
            color: #34d399;
            background: rgba(52, 211, 153, 0.18);
            border-color: rgba(52, 211, 153, 0.35);
        }

        .brand-icon {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.35), rgba(5, 150, 105, 0.65));
            border: 1px solid rgba(52, 211, 153, 0.45);
            display: grid;
            place-items: center;
            color: #34d399;
            font-size: 1.35rem;
            box-shadow: 0 0 16px rgba(16, 185, 129, 0.25);
            flex-shrink: 0;
        }

        .brand-text h1 {
            font-family: var(--font-heading);
            font-size: 1.28rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            line-height: 1.15;
            color: #ffffff;
        }

        .brand-text h1 span {
            color: #34d399;
        }

        .district-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: rgba(255, 255, 255, 0.65);
            margin-top: 4px;
        }

        .district-tag span {
            color: #34d399;
        }

        /* Nav Links */
        .sidebar-nav {
            flex: 1;
            padding: 14px 14px 20px;
            overflow-y: auto;
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
            gap: 5px;
            scrollbar-width: none !important;
            -ms-overflow-style: none !important;
        }

        @media (max-height: 820px) {
            .brand-container { padding: 14px 18px 12px; }
            .brand-icon { width: 38px; height: 38px; font-size: 1.15rem; }
            .brand-text h1 { font-size: 1.15rem; }
            .sidebar-nav { padding: 8px 12px 14px; gap: 3px; }
            .nav-section-label { padding: 8px 10px 3px; }
            .nav-link { padding: 8px 12px; font-size: 0.84rem; }
            .sidebar-footer { padding: 12px; }
            .admin-profile-card { padding: 7px 10px; }
        }

        .nav-section-label {
            font-size: 0.66rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: rgba(255, 255, 255, 0.4);
            padding: 14px 14px 6px;
            margin-top: 6px;
        }

        .sidebar-nav .nav-section-label:first-child {
            padding-top: 4px;
            margin-top: 0;
        }

        .nav-link {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 11px 15px;
            border: 1px solid transparent;
            border-radius: 14px;
            background: transparent;
            color: #94a3b8;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.89rem;
            line-height: 1.4;
            transition: all 0.18s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            outline: none;
            text-align: left;
            user-select: none;
        }

        .nav-link-content {
            display: flex;
            align-items: center;
            gap: 13px;
            min-width: 0;
        }

        .nav-link-content span {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .nav-link i {
            font-size: 1.15rem;
            color: rgba(255, 255, 255, 0.55);
            transition: color 0.18s, transform 0.18s;
            flex-shrink: 0;
            display: grid;
            place-items: center;
            width: 22px;
        }

        .nav-link:hover:not(.active) {
            background: rgba(255, 255, 255, 0.08);
            color: #ffffff;
            transform: translateX(4px);
            border-color: rgba(255, 255, 255, 0.06);
            border-radius: 14px;
        }

        .nav-link:hover:not(.active) i {
            color: #34d399;
            transform: scale(1.08);
        }

        .nav-link:active {
            transform: scale(0.98);
        }

        .nav-link.active {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #ffffff;
            font-weight: 700;
            box-shadow: 0 8px 18px -8px rgba(16, 185, 129, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: 14px;
        }

        .nav-link.active i {
            color: #ffffff;
        }

        .nav-link.active .nav-badge {
            background: rgba(255, 255, 255, 0.25) !important;
            color: #ffffff !important;
            border-color: rgba(255, 255, 255, 0.4) !important;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.15);
        }

        .nav-badge {
            font-size: 0.68rem;
            font-weight: 700;
            padding: 2.5px 8px;
            border-radius: 9999px;
            background: rgba(239, 68, 68, 0.22);
            color: #fca5a5;
            border: 1px solid rgba(239, 68, 68, 0.35);
            flex-shrink: 0;
            line-height: 1.3;
            transition: all 0.18s;
        }

        .nav-badge.live-pulse {
            background: rgba(16, 185, 129, 0.2);
            color: #6ee7b7;
            border-color: rgba(16, 185, 129, 0.4);
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .nav-badge.live-pulse::before {
            content: '';
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: #34d399;
            animation: pulse-dot 1.5s infinite;
        }

        @keyframes pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.8); }
        }

        /* Sidebar Footer (Admin Profile) */
        .sidebar-footer {
            padding: 16px;
            border-top: 1px solid var(--sidebar-border);
            background: rgba(0, 0, 0, 0.22);
            flex-shrink: 0;
        }

        .admin-profile-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 9px 12px;
            border-radius: 14px;
            background: var(--sidebar-surface);
            border: 1px solid var(--sidebar-border);
        }

        .admin-avatar-group {
            display: flex;
            align-items: center;
            gap: 11px;
        }

        .avatar-circle {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: linear-gradient(135deg, #10b981, #047857);
            color: #ffffff;
            font-weight: 800;
            font-size: 1.05rem;
            display: grid;
            place-items: center;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
            position: relative;
        }

        .avatar-pulse {
            position: absolute;
            bottom: -2px;
            right: -2px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #10b981;
            border: 2px solid #031b14;
        }

        .admin-meta strong {
            display: block;
            font-size: 0.88rem;
            font-weight: 700;
            color: #ffffff;
            line-height: 1.25;
        }

        .admin-meta span {
            font-size: 0.72rem;
            color: rgba(255, 255, 255, 0.6);
        }

        .btn-sidebar-logout {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.7);
            display: grid;
            place-items: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-sidebar-logout:hover {
            background: rgba(239, 68, 68, 0.2);
            color: #fca5a5;
            border-color: rgba(239, 68, 68, 0.4);
        }

        /* ── MAIN CONTENT CANVAS ─────────────────────────────────────────── */
        .main-canvas {
            width: calc(100% - 280px);
            margin-left: 280px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            min-width: 0;
            max-width: 100%;
            overflow-x: hidden;
            transition: width 0.28s cubic-bezier(0.16, 1, 0.3, 1), margin-left 0.28s cubic-bezier(0.16, 1, 0.3, 1);
        }

        /* Top Navigation Header */
        .topbar-header {
            position: sticky;
            top: 0;
            z-index: 40;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 16px 32px;
            background: rgba(255, 255, 255, 0.88);
            border-bottom: 1px solid var(--panel-border);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
        }

        .topbar-title-group {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .topbar-title-group h2 {
            font-family: var(--font-heading);
            font-size: 1.25rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: var(--text-primary);
        }

        .topbar-scope {
            font-size: 0.78rem;
            color: var(--text-muted);
            margin-top: 2px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .lang-switch-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6.5px 14px;
            border-radius: var(--radius-pill);
            background: #ffffff;
            border: 1px solid var(--panel-border);
            color: var(--text-primary);
            cursor: pointer;
            font-size: 0.82rem;
            font-weight: 700;
            box-shadow: var(--shadow-sm);
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            user-select: none;
            outline: none;
        }

        .lang-switch-btn:hover {
            background: var(--panel-hover);
            border-color: #cbd5e1;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
        }

        .lang-switch-btn:active {
            transform: scale(0.97);
        }

        .lang-switch-btn.active-tl {
            background: #ecfdf5;
            border-color: rgba(16, 185, 129, 0.4);
            color: #047857;
        }

        .pulse-live-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: var(--radius-pill);
            background: #ecfdf5;
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #065f46;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .pulse-live-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #10b981;
            box-shadow: 0 0 8px #10b981;
            animation: livePulse 2s infinite;
        }

        @keyframes livePulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.6); opacity: 0.4; }
            100% { transform: scale(1); opacity: 1; }
        }

        /* Action Buttons */
        .btn-top {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 8px 14px;
            border-radius: 10px;
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            border: 1px solid transparent;
        }

        .btn-top-primary {
            background: linear-gradient(135deg, #10b981, #059669);
            color: #ffffff;
            border-color: #059669;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.25);
        }

        .btn-top-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35);
            color: #ffffff;
        }

        .btn-top-ghost {
            background: #ffffff;
            border-color: var(--panel-border);
            color: var(--text-secondary);
        }

        .btn-top-ghost:hover {
            background: var(--panel-hover);
            color: var(--text-primary);
        }

        /* Content Area */
        .content-area {
            flex: 1;
            max-width: 1440px;
            width: 100%;
            margin: 0 auto;
            padding: 32px;
        }

        /* Section Transition */
        .section {
            display: none !important;
        }

        .section.active {
            display: block !important;
            animation: sectionSlideFade 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }

        #dashboard.section.active {
            display: flex !important;
            flex-direction: column;
            gap: 1.25rem;
            animation: sectionSlideFade 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes sectionSlideFade {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Section Headings */
        .section-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 24px;
        }

        .section-header h3 {
            font-family: var(--font-heading);
            font-size: 1.65rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: var(--text-primary);
        }

        .section-header p {
            font-size: 0.9rem;
            color: var(--text-muted);
            margin-top: 4px;
        }

        /* Flash Message */
        .flash-card {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 0.88rem;
            font-weight: 600;
            box-shadow: var(--shadow-sm);
        }

        .flash-card.success {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .flash-card.error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        /* ── STATS CARDS (ENTERPRISE GRID) ───────────────────────────────── */
        .stats-deck {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }

        .stat-card-modern {
            background: var(--panel-bg);
            border: 1px solid var(--panel-border);
            border-radius: var(--radius-card);
            padding: 20px 18px;
            box-shadow: var(--shadow-card);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            overflow: hidden;
        }

        .stat-card-modern:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-elevated);
            border-color: rgba(16, 185, 129, 0.4);
        }

        .stat-top-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .stat-icon-wrapper {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            font-size: 1.25rem;
            color: #ffffff;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .icon-emerald { background: linear-gradient(135deg, #10b981, #059669); }
        .icon-amber { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .icon-teal { background: linear-gradient(135deg, #14b8a6, #0f766e); }
        .icon-orange { background: linear-gradient(135deg, #f97316, #c2410c); }
        .icon-indigo { background: linear-gradient(135deg, #6366f1, #4338ca); }
        .icon-rose { background: linear-gradient(135deg, #f43f5e, #be123c); }
        .icon-purple { background: linear-gradient(135deg, #a855f7, #7e22ce); }

        .stat-tag {
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 3px 8px;
            border-radius: 20px;
            background: #f1f5f9;
            color: var(--text-muted);
        }

        .stat-card-modern strong {
            font-family: var(--font-heading);
            font-size: 2.1rem;
            font-weight: 800;
            line-height: 1.1;
            letter-spacing: -0.02em;
            color: var(--text-primary);
        }

        .stat-card-modern span {
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--text-muted);
            margin-top: 6px;
        }

        /* ── TWO COLUMN WIDGET SECTION ───────────────────────────────────── */
        .two-col-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.2fr) minmax(360px, 0.8fr);
            gap: 24px;
            margin-bottom: 28px;
        }

        .card-panel {
            background: var(--panel-bg);
            border: 1px solid var(--panel-border);
            border-radius: var(--radius-card);
            box-shadow: var(--shadow-card);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .card-panel-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--panel-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #ffffff;
        }

        .card-panel-header h4 {
            font-family: var(--font-heading);
            font-size: 1.05rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-primary);
        }

        .card-panel-body {
            padding: 24px;
            flex: 1;
        }

        /* Municipality Cards */
        .muni-deck {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .muni-item {
            padding: 14px 16px;
            border-radius: 12px;
            border: 1px solid var(--panel-border);
            background: #f8fafc;
            display: flex;
            flex-direction: column;
            gap: 10px;
            transition: all 0.2s;
        }

        .muni-item:hover {
            background: #ffffff;
            border-color: rgba(16, 185, 129, 0.4);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
        }

        .muni-item-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .muni-name {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .muni-chips-row {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .muni-chip {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 8px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            color: var(--text-secondary);
        }

        .muni-chip strong {
            color: var(--text-primary);
            font-weight: 700;
        }

        /* Recent Activity / Reports */
        .recent-reports-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .recent-report-row {
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid var(--panel-border);
            background: #ffffff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            transition: all 0.2s;
        }

        .recent-report-row:hover {
            border-color: #cbd5e1;
            transform: translateX(2px);
        }

        .report-info-left strong {
            display: block;
            font-size: 0.88rem;
            color: var(--text-primary);
        }

        .report-info-left span {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        /* ── MODERN TABLES ───────────────────────────────────────────────── */
        .table-panel {
            background: var(--panel-bg);
            border: 1px solid var(--panel-border);
            border-radius: var(--radius-card);
            box-shadow: var(--shadow-card);
            overflow: hidden;
            margin-bottom: 24px;
        }

        .table-toolbar {
            padding: 16px 20px;
            border-bottom: 1px solid var(--panel-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            background: #ffffff;
            flex-wrap: wrap;
        }

        .search-container {
            position: relative;
            max-width: 360px;
            width: 100%;
        }

        .search-container i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        .search-container input {
            width: 100%;
            padding: 9px 12px 9px 38px;
            border: 1px solid var(--panel-border);
            border-radius: 10px;
            background: #f8fafc;
            font-size: 0.84rem;
            outline: none;
            transition: all 0.2s;
        }

        .search-container input:focus {
            background: #ffffff;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
        }

        .table-scroll {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        table.modern-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            table-layout: auto;
        }

        table.modern-table th {
            padding: 9px 10px;
            background: #f8fafc;
            color: #475569;
            font-size: 0.7rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            border-bottom: 1px solid var(--panel-border);
            white-space: nowrap;
        }

        table.modern-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.8rem;
            color: var(--text-secondary);
            vertical-align: middle;
        }

        table.modern-table tbody tr:hover {
            background: #fbfdfc;
        }

        table.modern-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Identity Cell */
        .user-id-group {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }

        .user-avatar-tiny {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: linear-gradient(135deg, #10b981, #047857);
            color: #ffffff;
            display: grid;
            place-items: center;
            font-weight: 700;
            font-size: 0.8rem;
            flex-shrink: 0;
        }

        .user-name-text {
            font-weight: 700;
            color: var(--text-primary);
            font-size: 0.82rem;
            line-height: 1.25;
            white-space: nowrap;
        }

        .user-subtext {
            font-size: 0.72rem;
            color: var(--text-muted);
            line-height: 1.2;
        }

        /* Column Compression & Layout Protection */
        .cell-contact {
            display: flex;
            flex-direction: column;
            gap: 2px;
            max-width: 175px;
            min-width: 0;
        }

        .contact-email {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.8rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: block;
        }

        .cell-scope {
            font-size: 0.78rem;
            color: var(--text-secondary);
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .cell-scope strong {
            color: var(--text-primary);
            font-weight: 700;
        }

        .cell-official {
            max-width: 175px;
            min-width: 0;
        }

        .official-pos {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.8rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .cell-official .user-subtext {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Soft Modern Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2.5px 8px;
            border-radius: 999px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: capitalize;
            border: 1px solid transparent;
            white-space: nowrap;
        }

        .badge.Active, .badge.Approved, .badge.Resolved, .badge.Complete, .badge.Healthy {
            background: #ecfdf5;
            color: #065f46;
            border-color: #a7f3d0;
        }

        .badge.Pending, .badge.Reviewed, .badge.Partial {
            background: #fffbeb;
            color: #92400e;
            border-color: #fde68a;
        }

        .badge.Inactive, .badge.Rejected, .badge.Closed, .badge.Sick, .badge.Critical, .badge.High {
            background: #fef2f2;
            color: #991b1b;
            border-color: #fecaca;
        }

        .badge.Farmer { background: #ecfdf5; color: #047857; border-color: #a7f3d0; }
        .badge.AgricultureOfficial, .badge[class*="Agriculture"] { background: #eff6ff; color: #1e40af; border-color: #bfdbfe; }
        .badge.Admin { background: #f5f3ff; color: #6b21a8; border-color: #ddd6fe; }

        /* Action Buttons & Button Groups */
        .action-flex {
            display: flex;
            align-items: center;
            gap: 5px;
            flex-wrap: nowrap;
            white-space: nowrap;
        }

        .btn-act {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 5px 9px;
            border-radius: 7px;
            font-size: 0.74rem;
            font-weight: 700;
            cursor: pointer;
            border: 1px solid transparent;
            transition: all 0.18s cubic-bezier(0.16, 1, 0.3, 1);
            white-space: nowrap;
        }

        .btn-act-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 0.95rem;
            border: 1px solid transparent;
            transition: all 0.18s cubic-bezier(0.16, 1, 0.3, 1);
            outline: none;
            background: transparent;
            padding: 0;
            flex-shrink: 0;
        }

        .btn-act-icon:hover, .btn-act:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
        }

        .btn-act-icon:active, .btn-act:active {
            transform: scale(0.95);
        }

        .btn-act-approve, .btn-act-icon.btn-act-approve {
            background: #ecfdf5;
            color: #047857;
            border-color: #a7f3d0;
        }
        .btn-act-approve:hover, .btn-act-icon.btn-act-approve:hover {
            background: #059669;
            color: #ffffff;
            border-color: #059669;
        }

        .btn-act-reject, .btn-act-icon.btn-act-reject {
            background: #fef2f2;
            color: #b91c1c;
            border-color: #fecaca;
        }
        .btn-act-reject:hover, .btn-act-icon.btn-act-reject:hover {
            background: #dc2626;
            color: #ffffff;
            border-color: #dc2626;
        }

        .btn-act-warning, .btn-act-icon.btn-act-warning {
            background: #fffbeb;
            color: #b45309;
            border-color: #fde68a;
        }
        .btn-act-warning:hover, .btn-act-icon.btn-act-warning:hover {
            background: #d97706;
            color: #ffffff;
            border-color: #d97706;
        }

        .badge-icon-protected {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #f5f3ff;
            color: #6b21a8;
            border: 1px solid #ddd6fe;
            font-size: 0.92rem;
            cursor: default;
            flex-shrink: 0;
        }

        .inline-select {
            padding: 5px 8px;
            border-radius: 8px;
            border: 1px solid var(--panel-border);
            font-size: 0.76rem;
            background: #ffffff;
            color: var(--text-primary);
            outline: none;
        }

        /* ── REPORT DOWNLOAD TILES ───────────────────────────────────────── */
        .report-tiles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 20px;
        }

        .report-tile-card {
            background: #ffffff;
            border: 1px solid var(--panel-border);
            border-radius: var(--radius-card);
            padding: 24px;
            box-shadow: var(--shadow-card);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .report-tile-card:hover {
            transform: translateY(-3px);
            border-color: rgba(16, 185, 129, 0.4);
            box-shadow: var(--shadow-elevated);
        }

        .report-tile-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }

        .report-tile-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: #ecfdf5;
            color: #059669;
            border: 1px solid rgba(16, 185, 129, 0.3);
            display: grid;
            place-items: center;
            font-size: 1.4rem;
        }

        .format-badge {
            font-size: 0.7rem;
            font-weight: 800;
            padding: 3px 8px;
            border-radius: 6px;
            background: #f1f5f9;
            color: var(--text-muted);
            border: 1px solid #e2e8f0;
        }

        .report-tile-card h4 {
            font-family: var(--font-heading);
            font-size: 1.15rem;
            font-weight: 700;
            margin-bottom: 6px;
            color: var(--text-primary);
        }

        .report-tile-card p {
            font-size: 0.84rem;
            color: var(--text-muted);
            line-height: 1.4;
            margin-bottom: 20px;
        }

        .btn-download-csv {
            width: 100%;
            padding: 10px;
            border-radius: 10px;
            background: #f8fafc;
            border: 1px solid var(--panel-border);
            color: var(--text-primary);
            font-size: 0.82rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .btn-download-csv:hover {
            background: var(--primary);
            color: #ffffff;
            border-color: var(--primary);
        }

        /* ── EMBEDDED MAP CONTAINER ──────────────────────────────────────── */
        .map-section-card {
            background: #ffffff;
            border: 1px solid var(--panel-border);
            border-radius: var(--radius-card);
            overflow: hidden;
            box-shadow: var(--shadow-card);
        }

        .map-top-toolbar {
            padding: 14px 20px;
            background: #f8fafc;
            border-bottom: 1px solid var(--panel-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .map-frame-view {
            width: 100%;
            height: 640px;
            border: none;
            display: block;
            background: #f8fafc;
        }

        /* Unified Navigation / Full View Toggle Button */
        .mobile-nav-toggle,
        .sidebar-toggle-btn {
            display: grid;
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: #ffffff;
            border: 1px solid var(--panel-border);
            color: var(--text-primary);
            cursor: pointer;
            place-items: center;
            font-size: 1.15rem;
            box-shadow: var(--shadow-sm);
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            user-select: none;
            outline: none;
        }

        .mobile-nav-toggle:hover,
        .sidebar-toggle-btn:hover {
            background: var(--panel-hover);
            border-color: #cbd5e1;
            color: var(--brand);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .mobile-nav-toggle.active,
        .sidebar-toggle-btn.active {
            background: #ecfdf5;
            color: #059669;
            border-color: rgba(16, 185, 129, 0.4);
        }

        /* Mobile Overlay Backdrop */
        .sidebar-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            z-index: 45;
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: opacity 0.25s ease, visibility 0.25s ease;
        }

        .sidebar-overlay.open {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
        }

        /* ── AUTO-HIDE & FULL VIEW MODE (Desktop & Tablets) ─────────────── */
        body.sidebar-autohide .sidebar {
            transform: translateX(-100%);
            box-shadow: 0 0 50px rgba(0, 0, 0, 0.55);
        }

        body.sidebar-autohide .sidebar.hover-open,
        body.sidebar-autohide .sidebar:hover,
        body.sidebar-autohide .sidebar.open {
            transform: translateX(0);
        }

        body.sidebar-autohide .main-canvas {
            width: 100% !important;
            margin-left: 0 !important;
        }

        /* Left Edge Hover Detection Zone */
        .sidebar-hover-zone {
            display: none;
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            width: 18px;
            z-index: 48;
        }
        body.sidebar-autohide .sidebar-hover-zone {
            display: block;
        }

        /* Floating Edge Trigger Tab */
        .sidebar-edge-trigger {
            display: none;
            position: fixed;
            top: 50%;
            left: 0;
            transform: translateY(-50%);
            width: 20px;
            height: 52px;
            background: linear-gradient(180deg, #065f46, #022c22);
            color: #34d399;
            border-radius: 0 10px 10px 0;
            box-shadow: 2px 0 12px rgba(0, 0, 0, 0.3);
            z-index: 49;
            cursor: pointer;
            place-items: center;
            font-size: 0.85rem;
            border: 1px solid rgba(52, 211, 153, 0.3);
            border-left: none;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            user-select: none;
        }
        .sidebar-edge-trigger:hover {
            width: 26px;
            background: #059669;
            color: #ffffff;
            box-shadow: 4px 0 16px rgba(5, 150, 105, 0.4);
        }
        body.sidebar-autohide .sidebar-edge-trigger {
            display: grid;
        }

        /* Baseline Mobile Overlay and Canvas Setup */
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.open {
                transform: translateX(0);
                box-shadow: 0 0 40px rgba(0, 0, 0, 0.5);
            }
            .main-canvas {
                width: 100% !important;
                margin-left: 0 !important;
            }
            .sidebar-hover-zone,
            .sidebar-edge-trigger {
                display: none !important;
            }
            .two-col-layout {
                grid-template-columns: 1fr;
            }
            .content-area {
                padding: 20px 18px;
            }
        }

/* ═══ MODERN DASHBOARD (dx-) ═══════════════════════════════════════════ */
#dashboard.active { display: flex !important; flex-direction: column; gap: 1.25rem; }
.dx-hero {
    position: relative; overflow: hidden; display: flex; align-items: center; justify-content: space-between; gap: 2rem;
    padding: 2rem 2.25rem; border-radius: 24px; color: #fff;
    background: radial-gradient(circle at 85% 20%, rgba(52,211,153,.35), transparent 45%),
                linear-gradient(135deg, #022c22 0%, #065f46 55%, #059669 100%);
    box-shadow: 0 20px 40px -12px rgba(5,150,105,.45);
}
.dx-hero::after { content:''; position:absolute; right:-60px; bottom:-90px; width:260px; height:260px; border-radius:50%; background:rgba(255,255,255,.06); }
.dx-hero-text { position: relative; z-index: 1; min-width: 0; }
.dx-eyebrow { display:inline-flex; align-items:center; gap:.4rem; font-size:.75rem; font-weight:600; padding:.3rem .75rem; border-radius:999px; background:rgba(255,255,255,.14); backdrop-filter:blur(6px); }
.dx-hero h3 { font-size: clamp(1.4rem, 3vw, 2rem); font-weight: 800; margin: .75rem 0 .4rem; letter-spacing:-.02em; }
.dx-hero p { font-size:.92rem; line-height:1.6; opacity:.9; max-width: 560px; }
.dx-hero-actions { display:flex; flex-wrap:wrap; gap:.6rem; margin-top:1.25rem; }
.dx-btn { display:inline-flex; align-items:center; gap:.45rem; border:0; cursor:pointer; font:inherit; font-size:.82rem; font-weight:700; padding:.6rem 1.05rem; border-radius:12px; transition:.2s; }
.dx-btn-light { background:#fff; color:#047857; }
.dx-btn-light:hover { transform: translateY(-2px); box-shadow:0 8px 18px rgba(0,0,0,.2); }
.dx-btn-glass { background:rgba(255,255,255,.14); color:#fff; border:1px solid rgba(255,255,255,.25); }
.dx-btn-glass:hover { background:rgba(255,255,255,.24); }
.dx-hero-ring { position:relative; z-index:1; flex-shrink:0; width:150px; height:150px; border-radius:50%;
    background: conic-gradient(#6ee7b7 calc(var(--p) * 1%), rgba(255,255,255,.18) 0); display:grid; place-items:center; }
.dx-ring-inner { width:118px; height:118px; border-radius:50%; background:#064e3b; display:flex; flex-direction:column; align-items:center; justify-content:center; }
.dx-ring-inner strong { font-size:1.9rem; font-weight:800; line-height:1; }
.dx-ring-inner span { font-size:.68rem; opacity:.8; margin-top:.25rem; text-transform:uppercase; letter-spacing:.06em; }

.dx-kpis { display:grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap:1rem; }
.dx-kpi { display:flex; align-items:center; gap:1rem; padding:1.15rem 1.25rem; background:#fff; border:1px solid var(--panel-border); border-radius:20px; box-shadow:var(--shadow-card); transition:.25s; min-width:0; }
.dx-kpi:hover { transform:translateY(-3px); box-shadow:var(--shadow-elevated); }
.dx-kpi-icon { flex-shrink:0; width:52px; height:52px; border-radius:16px; display:grid; place-items:center; font-size:1.4rem; }
.dx-c-emerald { background:#d1fae5; color:#047857; } .dx-c-teal { background:#ccfbf1; color:#0f766e; }
.dx-c-indigo { background:#e0e7ff; color:#4338ca; } .dx-c-rose { background:#ffe4e6; color:#be123c; }
.dx-kpi-body { display:flex; flex-direction:column; min-width:0; }
.dx-kpi-body span { font-size:.74rem; font-weight:600; color:var(--text-muted); }
.dx-kpi-body strong { font-size:1.75rem; font-weight:800; color:var(--text-primary); line-height:1.15; letter-spacing:-.02em; }
.dx-kpi-body em { font-style:normal; font-size:.72rem; font-weight:600; display:inline-flex; align-items:center; gap:.25rem; margin-top:.15rem; }
.dx-ok { color:#059669; } .dx-warn { color:#d97706; } .dx-bad { color:#dc2626; }

.dx-grid { display:grid; gap:1rem; }
.dx-grid-3 { grid-template-columns: repeat(3, minmax(0,1fr)); }
.dx-grid-2 { grid-template-columns: repeat(2, minmax(0,1fr)); }
.dx-card { background:#fff; border:1px solid var(--panel-border); border-radius:20px; padding:1.25rem 1.35rem; box-shadow:var(--shadow-card); min-width:0; }
.dx-card-head { display:flex; align-items:center; justify-content:space-between; gap:.75rem; margin-bottom:1rem; }
.dx-card-head h4 { display:flex; align-items:center; gap:.5rem; font-size:.95rem; font-weight:800; color:var(--text-primary); }
.dx-card-head h4 i { color:var(--primary); }
.dx-pill { font-size:.68rem; font-weight:700; padding:.25rem .65rem; border-radius:999px; background:var(--primary-soft); color:var(--primary-dark); white-space:nowrap; }
.dx-link { background:none; border:0; cursor:pointer; font:inherit; font-size:.78rem; font-weight:700; color:var(--primary); display:inline-flex; align-items:center; gap:.3rem; }
.dx-link:hover { color:var(--primary-dark); }
.dx-empty { text-align:center; padding:1.25rem .5rem; color:var(--text-muted); font-size:.85rem; }
.dx-empty i { display:block; font-size:1.5rem; color:#10b981; margin-bottom:.35rem; }

.dx-donut-wrap { display:flex; align-items:center; gap:1.25rem; flex-wrap:wrap; justify-content:center; }
.dx-donut { width:140px; height:140px; border-radius:50%; display:grid; place-items:center; flex-shrink:0; }
.dx-donut-hole { width:98px; height:98px; border-radius:50%; background:#fff; display:flex; flex-direction:column; align-items:center; justify-content:center; }
.dx-donut-hole strong { font-size:1.35rem; font-weight:800; color:var(--text-primary); }
.dx-donut-hole span { font-size:.66rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:.05em; }
.dx-legend { list-style:none; display:flex; flex-direction:column; gap:.5rem; flex:1; min-width:120px; }
.dx-legend li { display:flex; align-items:center; gap:.5rem; font-size:.8rem; color:var(--text-secondary); }
.dx-legend li i { width:10px; height:10px; border-radius:50%; flex-shrink:0; }
.dx-legend li span { flex:1; } .dx-legend li b { color:var(--text-primary); }

.dx-bars { display:flex; flex-direction:column; gap:.85rem; }
.dx-bar-label { display:flex; justify-content:space-between; font-size:.8rem; font-weight:600; color:var(--text-secondary); margin-bottom:.3rem; }
.dx-bar-label b { color:var(--text-primary); }
.dx-bar-track { height:8px; border-radius:999px; background:#eef2f6; overflow:hidden; }
.dx-bar-fill { height:100%; border-radius:999px; background:linear-gradient(90deg,#10b981,#34d399); transition:width .8s cubic-bezier(.16,1,.3,1); }

.dx-spark { display:flex; align-items:flex-end; gap:.6rem; height:150px; padding-top:.5rem; }
.dx-spark-col { flex:1; height:100%; display:flex; flex-direction:column; align-items:center; justify-content:flex-end; gap:.3rem; }
.dx-spark-bar { width:100%; max-width:34px; border-radius:10px 10px 4px 4px; background:linear-gradient(180deg,#34d399,#059669); transition:.2s; }
.dx-spark-col:hover .dx-spark-bar { filter:brightness(1.1); transform:scaleY(1.03); }
.dx-spark-val { font-size:.7rem; font-weight:700; color:var(--text-secondary); }
.dx-spark-lbl { font-size:.68rem; color:var(--text-muted); }

.dx-list, .dx-timeline { display:flex; flex-direction:column; }
.dx-list-row, .dx-timeline-item { display:flex; align-items:center; gap:.85rem; padding:.7rem 0; border-bottom:1px solid #f1f5f9; }
.dx-list-row:last-child, .dx-timeline-item:last-child { border-bottom:0; }
.dx-list-main { flex:1; min-width:0; display:flex; flex-direction:column; }
.dx-list-main strong { font-size:.85rem; color:var(--text-primary); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.dx-list-main span { font-size:.73rem; color:var(--text-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.dx-avatar { width:38px; height:38px; flex-shrink:0; border-radius:12px; display:grid; place-items:center; font-weight:800; color:#fff; background:linear-gradient(135deg,#10b981,#0f766e); }
.dx-mini-btn { border:1px solid var(--panel-border); background:#fff; font:inherit; font-size:.74rem; font-weight:700; color:var(--primary-dark); padding:.35rem .8rem; border-radius:10px; cursor:pointer; transition:.15s; }
.dx-mini-btn:hover { background:var(--primary); color:#fff; border-color:var(--primary); }
.dx-t-dot { width:10px; height:10px; border-radius:50%; flex-shrink:0; box-shadow:0 0 0 4px rgba(148,163,184,.15); }
.dx-t-red { background:#ef4444; box-shadow:0 0 0 4px rgba(239,68,68,.15); } .dx-t-amber { background:#f59e0b; box-shadow:0 0 0 4px rgba(245,158,11,.15); } .dx-t-blue { background:#3b82f6; box-shadow:0 0 0 4px rgba(59,130,246,.15); }
.dx-status-strip { display:grid; grid-template-columns:repeat(4,1fr); gap:.5rem; margin-bottom:.5rem; }
.dx-status-chip { background:#f8fafc; border-radius:12px; padding:.55rem .4rem; text-align:center; }
.dx-status-chip b { display:block; font-size:1.1rem; color:var(--text-primary); }
.dx-status-chip span { font-size:.65rem; color:var(--text-muted); font-weight:600; }

.dx-muni-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(200px,1fr)); gap:.85rem; }
.dx-muni { border:1px solid var(--panel-border); border-radius:16px; padding:1rem; background:linear-gradient(180deg,#fff,#f8fafc); transition:.2s; }
.dx-muni:hover { border-color:#6ee7b7; box-shadow:var(--shadow-card); }
.dx-muni-top { display:flex; align-items:center; justify-content:space-between; gap:.5rem; margin-bottom:.75rem; }
.dx-muni-top strong { font-size:.9rem; color:var(--text-primary); }
.dx-muni-nums { display:grid; grid-template-columns:repeat(3,1fr); gap:.4rem; margin-bottom:.75rem; text-align:center; }
.dx-muni-nums b { display:block; font-size:1.05rem; color:var(--text-primary); }
.dx-muni-nums span { font-size:.65rem; color:var(--text-muted); font-weight:600; }

/* ═══════════════════════════════════════════════════════════════════════
   UNIFIED RESPONSIVE ADAPTIVE ENGINE (MOBILE, TABLET, LAPTOP, DESKTOP)
   ═══════════════════════════════════════════════════════════════════════ */

/* Laptops & Standard Desktops (<= 1200px) */
@media (max-width: 1200px) {
    .dx-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
    .dx-grid-3 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

/* Tablets Landscape & Small Laptops (<= 1024px) */
@media (max-width: 1024px) {
    /* Clean App-Like Experience: Remove Visible Scrollbars on Mobile */
    * {
        scrollbar-width: none !important;
        -ms-overflow-style: none !important;
    }
    *::-webkit-scrollbar,
    html::-webkit-scrollbar,
    body::-webkit-scrollbar,
    .main-canvas::-webkit-scrollbar,
    .table-responsive::-webkit-scrollbar,
    .content-area::-webkit-scrollbar,
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

    .topbar-header {
        padding: 12px 20px;
    }
    .topbar-title-group h2 {
        font-size: 1.15rem;
    }
    .topbar-scope {
        max-width: 280px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .content-area {
        padding: 20px 16px;
        gap: 18px;
    }
}

/* Tablets Portrait (<= 900px) */
@media (max-width: 900px) {
    .dx-grid-2, .dx-grid-3 {
        grid-template-columns: 1fr;
    }
    .dx-hero {
        padding: 1.6rem 1.4rem;
        gap: 1.25rem;
    }
    .two-col-layout {
        grid-template-columns: 1fr;
    }
}

/* Tablets / Large Phablets (<= 768px) */
@media (max-width: 768px) {
    .content-area {
        padding: 16px 12px;
        gap: 14px;
    }
    .topbar-header {
        padding: 10px 12px;
        gap: 8px;
    }
    .topbar-title-group {
        gap: 8px;
        min-width: 0;
        flex: 1;
    }
    .topbar-title-group h2 {
        font-size: clamp(0.92rem, 3.2vw, 1.1rem);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .topbar-scope {
        display: none;
    }
    .topbar-actions {
        gap: 6px;
    }
    .btn-top {
        padding: 7px 11px;
        font-size: 0.78rem;
    }
    .section-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 6px;
        margin-bottom: 16px;
    }
    .section-header h3 {
        font-size: clamp(1.2rem, 4.5vw, 1.45rem);
    }
    .section-header p {
        font-size: 0.8rem;
    }
    .table-panel {
        border-radius: 14px;
        margin-bottom: 16px;
    }
    .table-toolbar {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
        padding: 12px;
    }
    .search-container {
        max-width: 100%;
        width: 100%;
    }
    table.modern-table {
        min-width: 620px;
    }
    table.modern-table th,
    table.modern-table td {
        padding: 9px 12px;
        font-size: 0.8rem;
    }
    .map-frame-view {
        height: 480px;
    }
    /* Stop iOS Safari automatic form zoom */
    input, select, textarea, .inline-select, .search-container input {
        font-size: 16px !important;
    }
}

/* Mobile Screens (<= 640px) */
@media (max-width: 640px) {
    .pulse-live-badge span:not(.pulse-live-dot) {
        display: none;
    }
    .pulse-live-badge {
        padding: 8px;
        border-radius: 50%;
        min-width: 24px;
        height: 24px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .btn-top span {
        display: none;
    }
    .btn-top {
        min-width: 36px;
        height: 36px;
        padding: 0;
        justify-content: center;
        border-radius: 10px;
    }
    .btn-top i {
        font-size: 1.05rem;
        margin: 0;
    }
    .dx-hero {
        padding: 1.35rem 1.15rem;
        flex-direction: column;
        align-items: stretch;
        gap: 1rem;
        border-radius: 20px;
    }
    .dx-hero-actions {
        width: 100%;
        margin-top: 0.85rem;
    }
    .dx-hero-actions .dx-btn {
        flex: 1 1 calc(50% - 6px);
        justify-content: center;
        min-height: 40px;
        font-size: 0.78rem;
    }
    .dx-hero-ring {
        align-self: center;
        margin: 0.5rem 0 0;
        width: 108px;
        height: 108px;
    }
    .dx-ring-inner {
        width: 84px;
        height: 84px;
    }
    .dx-ring-inner strong {
        font-size: 1.35rem;
    }
    .dx-kpis {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
    }
    .dx-kpi {
        padding: 0.85rem 0.85rem;
        border-radius: 14px;
        gap: 0.65rem;
    }
    .dx-kpi-icon {
        width: 38px;
        height: 38px;
        font-size: 1.1rem;
        border-radius: 11px;
    }
    .dx-kpi-body strong {
        font-size: 1.3rem;
    }
    .dx-kpi-body span {
        font-size: 0.67rem;
    }
    .dx-kpi-body em {
        font-size: 0.64rem;
    }
    .dx-card {
        padding: 0.95rem;
        border-radius: 16px;
    }
    .dx-status-strip {
        grid-template-columns: repeat(2, 1fr);
        gap: 6px;
    }
    .dx-donut-wrap {
        flex-direction: column;
        align-items: center;
        gap: 12px;
    }
    .dx-muni-grid {
        grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
        gap: 8px;
    }
    .report-tiles-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    .report-tile-card {
        padding: 16px;
    }
}

/* Compact Phones (<= 420px) */
@media (max-width: 420px) {
    .content-area {
        padding: 10px 8px;
        gap: 12px;
    }
    .topbar-header {
        padding: 8px 8px;
        gap: 6px;
    }
    .mobile-nav-toggle {
        width: 34px;
        height: 34px;
        font-size: 0.95rem;
    }
    .lang-switch-btn .lang-label {
        display: none;
    }
    .lang-switch-btn {
        padding: 5px 8px;
        font-size: 0.78rem;
    }
    .pulse-live-badge {
        display: none;
    }
    .btn-top {
        min-width: 32px;
        height: 32px;
    }
    .dx-kpis {
        grid-template-columns: 1fr;
        gap: 8px;
    }
    .dx-hero-actions .dx-btn {
        flex: 1 1 100%;
    }
    .dx-hero h3 {
        font-size: 1.25rem;
    }
    .dx-muni-grid {
        grid-template-columns: 1fr;
    }
    .action-flex {
        flex-direction: column;
        align-items: stretch;
    }
    .action-flex .btn-act {
        justify-content: center;
        width: 100%;
    }
}

    </style>
</head>
<body>

    <!-- Auto-hide Edge Trigger Strip and Zone -->
    <div class="sidebar-hover-zone" id="sidebarHoverZone" aria-hidden="true"></div>
    <div class="sidebar-edge-trigger" id="sidebarEdgeTrigger" title="Hover or click for navigation">
        <i class="bi bi-chevron-right"></i>
    </div>

    <!-- Mobile Drawer Backdrop Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- ── MODERN OBSIDIAN-EMERALD SIDEBAR ─────────────────────────────────── -->
    <aside class="sidebar" id="sidebar">
        <div class="brand-container">
            <div class="brand-icon">
                <i class="bi bi-shield-lock-fill"></i>
            </div>
            <div class="brand-text">
                <h1>Agri<span>Trace+</span></h1>
                <div class="district-tag">
                    <i class="bi bi-geo-alt-fill"></i>
                    <span>1st District</span> • Admin Console
                </div>
            </div>
            <button type="button" class="btn-sidebar-pin" id="sidebarPinBtn" title="Pin / Unpin Sidebar">
                <i class="bi bi-pin-angle"></i>
            </button>
        </div>

        <nav class="sidebar-nav" aria-label="Admin Navigation">
            <div class="nav-section-label">Command Center</div>
            <button type="button" class="nav-link active" data-section="dashboard">
                <div class="nav-link-content">
                    <i class="bi bi-grid-1x2-fill"></i>
                    <span>Dashboard Overview</span>
                </div>
            </button>
            <button type="button" class="nav-link" data-section="map">
                <div class="nav-link-content">
                    <i class="bi bi-map-fill"></i>
                    <span>GIS Geo-Monitoring</span>
                </div>
                <span class="nav-badge live-pulse">LIVE</span>
            </button>

            <div class="nav-section-label">District Operations</div>
            <button type="button" class="nav-link" data-section="farmers">
                <div class="nav-link-content">
                    <i class="bi bi-people-fill"></i>
                    <span>Farmer Registry</span>
                </div>
                <?php if ($stats['pending_farmers'] > 0): ?>
                    <span class="nav-badge"><?= $stats['pending_farmers'] ?></span>
                <?php endif; ?>
            </button>
            <button type="button" class="nav-link" data-section="farms">
                <div class="nav-link-content">
                    <i class="bi bi-houses-fill"></i>
                    <span>Farm Holdings</span>
                </div>
                <?php if ($stats['pending_farms'] > 0): ?>
                    <span class="nav-badge"><?= $stats['pending_farms'] ?></span>
                <?php endif; ?>
            </button>
            <button type="button" class="nav-link" data-section="livestock">
                <div class="nav-link-content">
                    <i class="bi bi-tags-fill"></i>
                    <span>Livestock Inventory</span>
                </div>
            </button>
            <button type="button" class="nav-link" data-section="incidents">
                <div class="nav-link-content">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span>Incident Alerts</span>
                </div>
                <?php if ($stats['active_reports'] > 0): ?>
                    <span class="nav-badge"><?= $stats['active_reports'] ?></span>
                <?php endif; ?>
            </button>

            <div class="nav-section-label">System & Governance</div>
            <button type="button" class="nav-link" data-section="users">
                <div class="nav-link-content">
                    <i class="bi bi-person-gear"></i>
                    <span>User Management</span>
                </div>
            </button>
            <button type="button" class="nav-link" data-section="reports">
                <div class="nav-link-content">
                    <i class="bi bi-file-earmark-spreadsheet-fill"></i>
                    <span>Export Reports</span>
                </div>
            </button>
            <button type="button" class="nav-link" data-section="audit">
                <div class="nav-link-content">
                    <i class="bi bi-activity"></i>
                    <span>System Audit Logs</span>
                </div>
            </button>
        </nav>

        <div class="sidebar-footer">
            <div class="admin-profile-card">
                <div class="admin-avatar-group">
                    <div class="avatar-circle">
                        <?= h($adminInitial) ?>
                        <span class="avatar-pulse"></span>
                    </div>
                    <div class="admin-meta">
                        <strong><?= h($adminName) ?></strong>
                        <span>System Admin</span>
                    </div>
                </div>
                <a href="logout.php" class="btn-sidebar-logout" title="Sign Out">
                    <i class="bi bi-box-arrow-right"></i>
                </a>
            </div>
        </div>
    </aside>

    <!-- ── MAIN CONTENT CANVAS ─────────────────────────────────────────── -->
    <main class="main-canvas">
        
        <!-- Sticky Top Navigation -->
        <header class="topbar-header">
            <div class="topbar-title-group">
                <button type="button" class="sidebar-toggle-btn" id="sidebarToggle" aria-label="Toggle navigation" title="Toggle Navigation (Auto-Hide / Full View)">
                    <i class="bi bi-layout-sidebar-inset"></i>
                </button>
                <div>
                    <h2 id="topbarPageTitle">District Command &amp; Surveillance</h2>
                    <div class="topbar-scope">
                        <i class="bi bi-geo-alt"></i> <span id="topbarPageSub">Camarines Sur 1st District Jurisdiction • Cabusao, Del Gallego, Lupi, Ragay, Sipocot</span>
                    </div>
                </div>
            </div>

            <div class="topbar-actions">
                <button type="button" class="lang-switch-btn" onclick="toggleLanguage()" title="Palitan ang Wika / Switch Language">
                    <span class="lang-flag">🇵🇭</span>
                    <span class="lang-label">Tagalog</span>
                </button>

                <div class="pulse-live-badge">
                    <span class="pulse-live-dot"></span>
                    <span>System Active</span>
                </div>

                <a href="maps/farm-map.php" target="_blank" rel="noopener" class="btn-top btn-top-primary">
                    <i class="bi bi-map"></i>
                    <span>Live Map View</span>
                </a>
                <a href="logout.php" class="btn-top btn-top-ghost">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Logout</span>
                </a>
            </div>
        </header>

        <div class="content-area">

            <!-- Flash Notification -->
            <?php if ($flash): ?>
                <div class="flash-card <?= h($flash['type']) ?>">
                    <i class="bi bi-<?= $flash['type'] === 'success' ? 'check-circle-fill' : 'exclamation-circle-fill' ?>"></i>
                    <span><?= h($flash['message']) ?></span>
                </div>
            <?php endif; ?>

            <!-- ── SECTION 1: DASHBOARD OVERVIEW ───────────────────────────── -->
            <section id="dashboard" class="section active">

                <!-- HERO -->
                <div class="dx-hero">
                    <div class="dx-hero-text">
                        <span class="dx-eyebrow"><i class="bi bi-stars"></i> <?= date('l, F j, Y') ?></span>
                        <h3><?= h($greeting) ?>, <?= h(explode(' ', $adminName)[0]) ?> 👋</h3>
                        <p>
                            <?php if ($stats['pending_farms'] + $stats['pending_farmers'] > 0): ?>
                                You have <strong><?= $stats['pending_farms'] ?></strong> farm<?= $stats['pending_farms'] === 1 ? '' : 's' ?> and
                                <strong><?= $stats['pending_farmers'] ?></strong> farmer<?= $stats['pending_farmers'] === 1 ? '' : 's' ?> waiting for review.
                            <?php else: ?>
                                Everything is reviewed — the district queue is clear.
                            <?php endif; ?>
                            <?= $stats['active_reports'] ?> active incident report<?= $stats['active_reports'] === 1 ? '' : 's' ?> being monitored.
                        </p>
                        <div class="dx-hero-actions">
                            <button type="button" class="dx-btn dx-btn-light" data-section-jump="farms"><i class="bi bi-clipboard-check"></i> Review Farms</button>
                            <button type="button" class="dx-btn dx-btn-glass" data-section-jump="incidents"><i class="bi bi-exclamation-triangle"></i> View Alerts</button>
                            <button type="button" class="dx-btn dx-btn-glass" data-section-jump="reports"><i class="bi bi-download"></i> Export</button>
                        </div>
                    </div>
                    <div class="dx-hero-ring" style="--p: <?= (int)$healthyPct ?>;">
                        <div class="dx-ring-inner">
                            <strong><?= (int)$healthyPct ?>%</strong>
                            <span>Herd Health</span>
                        </div>
                    </div>
                </div>

                <!-- KPI CARDS -->
                <div class="dx-kpis">
                    <div class="dx-kpi">
                        <div class="dx-kpi-icon dx-c-emerald"><i class="bi bi-people-fill"></i></div>
                        <div class="dx-kpi-body">
                            <span>Registered Farmers</span>
                            <strong><?= number_format($stats['farmers']) ?></strong>
                            <em class="<?= $stats['pending_farmers'] ? 'dx-warn' : 'dx-ok' ?>"><i class="bi bi-<?= $stats['pending_farmers'] ? 'hourglass-split' : 'check2-circle' ?>"></i> <?= $stats['pending_farmers'] ?> pending</em>
                        </div>
                    </div>
                    <div class="dx-kpi">
                        <div class="dx-kpi-icon dx-c-teal"><i class="bi bi-houses-fill"></i></div>
                        <div class="dx-kpi-body">
                            <span>Approved Farms</span>
                            <strong><?= number_format($stats['approved_farms']) ?></strong>
                            <em class="<?= $stats['pending_farms'] ? 'dx-warn' : 'dx-ok' ?>"><i class="bi bi-<?= $stats['pending_farms'] ? 'hourglass-split' : 'check2-circle' ?>"></i> <?= $stats['pending_farms'] ?> awaiting approval</em>
                        </div>
                    </div>
                    <div class="dx-kpi">
                        <div class="dx-kpi-icon dx-c-indigo"><i class="bi bi-tags-fill"></i></div>
                        <div class="dx-kpi-body">
                            <span>Livestock Heads</span>
                            <strong><?= number_format($stats['livestock']) ?></strong>
                            <em class="dx-ok"><i class="bi bi-heart-pulse"></i> <?= (int)$healthyPct ?>% healthy</em>
                        </div>
                    </div>
                    <div class="dx-kpi">
                        <div class="dx-kpi-icon dx-c-rose"><i class="bi bi-exclamation-triangle-fill"></i></div>
                        <div class="dx-kpi-body">
                            <span>Active Incidents</span>
                            <strong><?= number_format($stats['active_reports']) ?></strong>
                            <em class="<?= $stats['high_priority'] ? 'dx-bad' : 'dx-ok' ?>"><i class="bi bi-broadcast"></i> <?= $stats['high_priority'] ?> high priority</em>
                        </div>
                    </div>
                </div>

                <!-- ROW: analytics -->
                <div class="dx-grid dx-grid-3">
                    <!-- Herd health donut -->
                    <div class="dx-card">
                        <div class="dx-card-head"><h4><i class="bi bi-heart-pulse-fill"></i> Herd Health</h4></div>
                        <?php
                            $hc = ['Healthy' => '#10b981', 'Recovered' => '#38bdf8', 'Sick' => '#f59e0b', 'Deceased' => '#ef4444'];
                            $acc = 0; $stops = [];
                            foreach ($hc as $k => $col) {
                                $pct = $lvTotal > 0 ? $lvHealth[$k] / $lvTotal * 100 : 0;
                                if ($pct > 0) { $stops[] = "$col {$acc}% " . ($acc + $pct) . "%"; $acc += $pct; }
                            }
                            $donut = $stops ? implode(',', $stops) : '#e2e8f0 0% 100%';
                        ?>
                        <div class="dx-donut-wrap">
                            <div class="dx-donut" style="background: conic-gradient(<?= $donut ?>);">
                                <div class="dx-donut-hole"><strong><?= number_format($lvTotal) ?></strong><span>total</span></div>
                            </div>
                            <ul class="dx-legend">
                                <?php foreach ($hc as $k => $col): ?>
                                    <li><i style="background:<?= $col ?>"></i><span><?= $k ?></span><b><?= number_format($lvHealth[$k]) ?></b></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>

                    <!-- Livestock by type -->
                    <div class="dx-card">
                        <div class="dx-card-head"><h4><i class="bi bi-bar-chart-fill"></i> Livestock by Type</h4></div>
                        <div class="dx-bars">
                            <?php $typeMax = max(1, (int)max($lvByType ?: [0])); ?>
                            <?php foreach (array_slice($lvByType, 0, 6, true) as $type => $qty): ?>
                                <div class="dx-bar-row">
                                    <div class="dx-bar-label"><span><?= h($type) ?></span><b><?= number_format($qty) ?></b></div>
                                    <div class="dx-bar-track"><div class="dx-bar-fill" style="width: <?= round($qty / $typeMax * 100) ?>%"></div></div>
                                </div>
                            <?php endforeach; ?>
                            <?php if (!$lvByType): ?><p class="dx-empty">No livestock registered yet.</p><?php endif; ?>
                        </div>
                    </div>

                    <!-- New farms this week -->
                    <div class="dx-card">
                        <div class="dx-card-head"><h4><i class="bi bi-graph-up-arrow"></i> Farm Registrations</h4><span class="dx-pill">Last 7 days</span></div>
                        <div class="dx-spark">
                            <?php foreach ($weekBars as $day): ?>
                                <div class="dx-spark-col" title="<?= (int)$day['count'] ?> farm(s)">
                                    <span class="dx-spark-val"><?= (int)$day['count'] ?></span>
                                    <div class="dx-spark-bar" style="height: <?= max(6, round($day['count'] / $weekMax * 100)) ?>%"></div>
                                    <span class="dx-spark-lbl"><?= h($day['label']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- ROW: queue + incidents -->
                <div class="dx-grid dx-grid-2">
                    <div class="dx-card">
                        <div class="dx-card-head">
                            <h4><i class="bi bi-clipboard2-check-fill"></i> Approval Queue</h4>
                            <button type="button" class="dx-link" data-section-jump="farms">View all <i class="bi bi-arrow-right"></i></button>
                        </div>
                        <div class="dx-list">
                            <?php foreach ($pendingFarmList as $pf): ?>
                                <div class="dx-list-row">
                                    <div class="dx-avatar"><?= h(strtoupper(substr($pf['name'] ?? 'F', 0, 1))) ?></div>
                                    <div class="dx-list-main">
                                        <strong><?= h($pf['name']) ?></strong>
                                        <span><?= h(trim(($pf['firstName'] ?? '') . ' ' . ($pf['lastName'] ?? ''))) ?> • <?= h($pf['municipality'] ?? '1st District') ?></span>
                                    </div>
                                    <button type="button" class="dx-mini-btn" data-section-jump="farms">Review</button>
                                </div>
                            <?php endforeach; ?>
                            <?php if (!$pendingFarmList): ?>
                                <p class="dx-empty"><i class="bi bi-check-circle-fill"></i> No farms waiting for approval.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="dx-card">
                        <div class="dx-card-head">
                            <h4><i class="bi bi-bell-fill"></i> Recent Incident Alerts</h4>
                            <button type="button" class="dx-link" data-section-jump="incidents">View all <i class="bi bi-arrow-right"></i></button>
                        </div>
                        <div class="dx-status-strip">
                            <?php foreach ($incByStatus as $s => $n): if ($s === 'Closed') continue; ?>
                                <div class="dx-status-chip"><b><?= $n ?></b><span><?= h($s) ?></span></div>
                            <?php endforeach; ?>
                        </div>
                        <div class="dx-timeline">
                            <?php foreach (array_slice($incidents, 0, 5) as $incident):
                                $pr = $incident['priority'] ?? 'Medium';
                                $dot = in_array($pr, ['Critical', 'High'], true) ? 'dx-t-red' : ($pr === 'Public' ? 'dx-t-blue' : 'dx-t-amber'); ?>
                                <div class="dx-timeline-item">
                                    <span class="dx-t-dot <?= $dot ?>"></span>
                                    <div class="dx-list-main">
                                        <strong><?= h($incident['title']) ?></strong>
                                        <span><?= h($incident['source']) ?> • <?= h($incident['municipality']) ?></span>
                                    </div>
                                    <span class="badge <?= h(str_replace(' ', '', $incident['status'])) ?>"><?= h($incident['status']) ?></span>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($incidents)): ?>
                                <p class="dx-empty"><i class="bi bi-check-circle-fill"></i> No active incident reports in the district.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Municipalities -->
                <div class="dx-card">
                    <div class="dx-card-head"><h4><i class="bi bi-geo-alt-fill"></i> Municipality Overview</h4><span class="dx-pill">1st District</span></div>
                    <div class="dx-muni-grid">
                        <?php foreach ($municipalitySummary as $municipality => $row):
                            $pct = $row['farms'] > 0 ? round($row['approved'] / $row['farms'] * 100) : 0; ?>
                            <div class="dx-muni">
                                <div class="dx-muni-top">
                                    <strong><?= h($municipality) ?></strong>
                                    <?php if ($row['pending'] > 0): ?><span class="badge Pending"><?= (int)$row['pending'] ?> pending</span>
                                    <?php else: ?><span class="badge Approved"><i class="bi bi-check2"></i> verified</span><?php endif; ?>
                                </div>
                                <div class="dx-muni-nums">
                                    <div><b><?= number_format($row['farms']) ?></b><span>Farms</span></div>
                                    <div><b><?= number_format($row['livestock']) ?></b><span>Heads</span></div>
                                    <div><b><?= $pct ?>%</b><span>Approved</span></div>
                                </div>
                                <div class="dx-bar-track"><div class="dx-bar-fill" style="width: <?= $pct ?>%"></div></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <!-- ── SECTION 2: FARMERS MANAGEMENT ───────────────────────────── -->
            <section id="farmers" class="section">
                <div class="section-header">
                    <div>
                        <h3>Farmer Directory &amp; Verification</h3>
                        <p>Inspect farmer accounts, approve new registrations, and track associated farms.</p>
                    </div>
                    <a class="btn-top btn-top-ghost" href="?export=farmers"><i class="bi bi-download"></i> Export CSV</a>
                </div>

                <div class="table-panel">
                    <div class="table-toolbar">
                        <div class="search-container">
                            <i class="bi bi-search"></i>
                            <input data-search="farmers-table" placeholder="Search farmers by name, email, or mobile...">
                        </div>
                        <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">
                            Total: <?= count($farmers) ?> Farmers
                        </div>
                    </div>
                    <div class="table-scroll">
                        <table class="modern-table" id="farmers-table">
                            <thead>
                                <tr>
                                    <th>Farmer Identity</th>
                                    <th>Contact Details</th>
                                    <th>Account Status</th>
                                    <th>Registered Farms</th>
                                    <th>Total Livestock</th>
                                    <th>Date Registered</th>
                                    <th>Administrative Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($farmers as $farmer): ?>
                                    <tr>
                                        <td>
                                            <div class="user-id-group">
                                                <div class="user-avatar-tiny"><?= strtoupper(substr($farmer['firstName'] ?? 'F', 0, 1)) ?></div>
                                                <div>
                                                    <div class="user-name-text"><?= h($farmer['firstName'] . ' ' . $farmer['lastName']) ?></div>
                                                    <div class="user-subtext">Farmer ID #<?= (int)$farmer['id'] ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="cell-contact" title="<?= h($farmer['email']) ?>">
                                                <span class="contact-email"><?= h($farmer['email']) ?></span>
                                                <span class="user-subtext"><?= h($farmer['mobile'] ?: '—') ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge <?= h($farmer['status']) ?>">
                                                <?= h($farmer['status']) ?>
                                            </span>
                                        </td>
                                        <td style="font-weight: 700; color: var(--text-primary);"><?= number_format((int)$farmer['farm_count']) ?></td>
                                        <td style="font-weight: 700; color: var(--primary);"><?= number_format((int)$farmer['total_livestock']) ?> heads</td>
                                        <td style="white-space: nowrap; font-size:0.78rem;"><?= h(date('M d, Y', strtotime($farmer['createdAt']))) ?></td>
                                        <td>
                                            <div class="action-flex" style="justify-content: flex-end;">
                                                <?php if ($farmer['status'] === 'Pending'): ?>
                                                    <form method="post" style="display:inline;">
                                                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                                        <input type="hidden" name="action" value="approve_farmer">
                                                        <input type="hidden" name="id" value="<?= (int)$farmer['id'] ?>">
                                                        <button class="btn-act-icon btn-act-approve" type="submit" title="Approve Farmer"><i class="bi bi-check2"></i></button>
                                                    </form>
                                                    <form method="post" style="display:inline;">
                                                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                                        <input type="hidden" name="action" value="reject_farmer">
                                                        <input type="hidden" name="id" value="<?= (int)$farmer['id'] ?>">
                                                        <button class="btn-act-icon btn-act-reject" type="submit" title="Reject Farmer"><i class="bi bi-x-lg"></i></button>
                                                    </form>
                                                <?php elseif ($farmer['status'] === 'Active'): ?>
                                                    <form method="post" style="display:inline;">
                                                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                                        <input type="hidden" name="action" value="deactivate_farmer">
                                                        <input type="hidden" name="id" value="<?= (int)$farmer['id'] ?>">
                                                        <button class="btn-act-icon btn-act-warning" type="submit" title="Suspend Farmer"><i class="bi bi-pause-fill"></i></button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="post" style="display:inline;">
                                                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                                        <input type="hidden" name="action" value="activate_farmer">
                                                        <input type="hidden" name="id" value="<?= (int)$farmer['id'] ?>">
                                                        <button class="btn-act-icon btn-act-approve" type="submit" title="Reactivate Farmer"><i class="bi bi-play-fill"></i></button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- ── SECTION 3: FARMS MANAGEMENT ─────────────────────────────── -->
            <section id="farms" class="section">
                <div class="section-header">
                    <div>
                        <h3>Farm Holdings &amp; Status</h3>
                        <p>Approve agricultural properties across Cabusao, Del Gallego, Lupi, Ragay, and Sipocot.</p>
                    </div>
                    <div style="display:flex; gap:10px;">
                        <a class="btn-top btn-top-ghost" href="?export=farms"><i class="bi bi-download"></i> Export CSV</a>
                        <a class="btn-top btn-top-primary" href="maps/farm-map.php" target="_blank" rel="noopener"><i class="bi bi-map"></i> View Map</a>
                    </div>
                </div>

                <div class="table-panel">
                    <div class="table-toolbar">
                        <div class="search-container">
                            <i class="bi bi-search"></i>
                            <input data-search="farms-table" placeholder="Search farms by name, location, or owner...">
                        </div>
                    </div>
                    <div class="table-scroll">
                        <table class="modern-table" id="farms-table">
                            <thead>
                                <tr>
                                    <th>Farm Name &amp; Address</th>
                                    <th>Owner</th>
                                    <th>Municipality</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Livestock Density</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($farms as $farm): ?>
                                    <tr>
                                        <td>
                                            <strong style="color:var(--text-primary); font-size:0.9rem;"><?= h($farm['name']) ?></strong>
                                            <div class="user-subtext"><?= h($farm['address']) ?></div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600; color: var(--text-primary);">
                                                <?= h(trim(($farm['firstName'] ?? '') . ' ' . ($farm['lastName'] ?? '')) ?: $farm['ownerName']) ?>
                                            </div>
                                            <div class="user-subtext"><?= h($farm['email']) ?></div>
                                        </td>
                                        <td>
                                            <span style="font-weight: 700; color: var(--text-primary);">
                                                <i class="bi bi-geo-alt-fill text-emerald-600"></i> <?= h($farm['municipality']) ?>
                                            </span>
                                        </td>
                                        <td><span class="badge"><?= h($farm['type']) ?></span></td>
                                        <td><span class="badge <?= h($farm['status']) ?>"><?= h($farm['status']) ?></span></td>
                                        <td>
                                            <strong style="color:var(--primary);"><?= number_format((int)$farm['total_livestock']) ?> heads</strong>
                                            <div class="user-subtext"><?= h($farm['livestock_types'] ?: 'No records') ?></div>
                                        </td>
                                        <td>
                                            <div class="action-flex">
                                                <?php if ($farm['status'] === 'Pending'): ?>
                                                    <form method="post" style="display:inline;">
                                                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                                        <input type="hidden" name="action" value="approve_farm">
                                                        <input type="hidden" name="id" value="<?= (int)$farm['id'] ?>">
                                                        <button class="btn-act-icon btn-act-approve" type="submit" title="Approve Farm"><i class="bi bi-check2"></i></button>
                                                    </form>
                                                    <form method="post" style="display:inline;">
                                                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                                        <input type="hidden" name="action" value="reject_farm">
                                                        <input type="hidden" name="id" value="<?= (int)$farm['id'] ?>">
                                                        <input type="hidden" name="reason" value="Rejected from admin panel">
                                                        <button class="btn-act-icon btn-act-reject" type="submit" title="Reject Farm"><i class="bi bi-x-lg"></i></button>
                                                    </form>
                                                <?php else: ?>
                                                    <span style="font-size:0.75rem; color: var(--text-muted);"><i class="bi bi-shield-check text-emerald-600"></i> Verified</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- ── SECTION 4: LIVESTOCK MONITORING ─────────────────────────── -->
            <section id="livestock" class="section">
                <div class="section-header">
                    <div>
                        <h3>District Livestock Inventory</h3>
                        <p>Complete traceability of cattle, swine, poultry, and goats with vaccination statuses.</p>
                    </div>
                    <a class="btn-top btn-top-ghost" href="?export=livestock"><i class="bi bi-download"></i> Export CSV</a>
                </div>

                <div class="table-panel">
                    <div class="table-toolbar">
                        <div class="search-container">
                            <i class="bi bi-search"></i>
                            <input data-search="livestock-table" placeholder="Search by tag, breed, or farm...">
                        </div>
                    </div>
                    <div class="table-scroll">
                        <table class="modern-table" id="livestock-table">
                            <thead>
                                <tr>
                                    <th>Tag Identification</th>
                                    <th>Holding Farm</th>
                                    <th>Municipality</th>
                                    <th>Species</th>
                                    <th>Head Count</th>
                                    <th>Health State</th>
                                    <th>Vaccination</th>
                                    <th>Last Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($livestock as $row): ?>
                                    <tr>
                                        <td>
                                            <strong style="color:var(--text-primary); font-family:monospace;"><?= h($row['tagId']) ?></strong>
                                            <div class="user-subtext"><?= h($row['breed']) ?><?php if(!empty($row['age'])): ?> &bull; <?= (int)$row['age'] ?> mos<?php endif; ?><?php if(!empty($row['weight']) && (float)$row['weight'] > 0): ?> &bull; <?= number_format((float)$row['weight'], 1) ?> kg<?php endif; ?></div>
                                            <?php if(!empty($row['notes'])): ?>
                                                <div class="user-subtext" style="font-style:italic; font-size:0.7rem; color:var(--text-muted); max-width:240px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?= h($row['notes']) ?>">
                                                    <i class="bi bi-info-circle"></i> <?= h($row['notes']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600; color: var(--text-primary);"><?= h($row['farm_name']) ?></div>
                                            <div class="user-subtext"><?= h(trim(($row['firstName'] ?? '') . ' ' . ($row['lastName'] ?? ''))) ?></div>
                                        </td>
                                        <td><?= h($row['municipality']) ?></td>
                                        <td><span class="badge"><?= h($row['type']) ?></span></td>
                                        <td><strong style="color:var(--primary);"><?= number_format((int)$row['qty']) ?></strong></td>
                                        <td><span class="badge <?= h($row['healthStatus']) ?>"><?= h($row['healthStatus']) ?></span></td>
                                        <td><span class="badge <?= h($row['vaccinationStatus']) ?>"><?= h($row['vaccinationStatus']) ?></span></td>
                                        <td style="white-space:nowrap;"><?= h(date('M d, Y', strtotime($row['updatedAt'] ?? $row['createdAt']))) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- ── SECTION 5: USER MANAGEMENT ──────────────────────────────── -->
            <section id="users" class="section">
                <div class="section-header">
                    <div>
                        <h3>User Access &amp; Governance</h3>
                        <p>Complete control of administrative, agricultural official, and farmer credentials.</p>
                    </div>
                </div>

                <div class="table-panel">
                    <div class="table-toolbar">
                        <div class="search-container">
                            <i class="bi bi-search"></i>
                            <input data-search="users-table" placeholder="Search users by name, role, or email...">
                        </div>
                    </div>
                    <div class="table-scroll">
                        <table class="modern-table" id="users-table">
                            <thead>
                                <tr>
                                    <th>User Identity</th>
                                    <th>Contact</th>
                                    <th>Assigned Role</th>
                                    <th>Status</th>
                                    <th>Scope</th>
                                    <th>Official Details</th>
                                    <th>Registered</th>
                                    <th style="text-align:right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allUsers as $user): ?>
                                    <tr>
                                        <td>
                                            <div class="user-id-group">
                                                <div class="user-avatar-tiny" style="background: <?= $user['role'] === 'Admin' ? 'linear-gradient(135deg, #7c3aed, #4c1d95)' : ($user['role'] === 'Agriculture Official' ? 'linear-gradient(135deg, #2563eb, #1d4ed8)' : 'linear-gradient(135deg, #10b981, #059669)') ?>;">
                                                    <?= strtoupper(substr($user['firstName'] ?? 'U', 0, 1)) ?>
                                                </div>
                                                <div>
                                                    <div class="user-name-text"><?= h($user['firstName'] . ' ' . $user['lastName']) ?></div>
                                                    <div class="user-subtext">ID #<?= (int)$user['id'] ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="cell-contact" title="<?= h($user['email']) ?>">
                                                <span class="contact-email"><?= h($user['email']) ?></span>
                                                <span class="user-subtext"><?= h($user['mobile'] ?: '—') ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge <?= h(str_replace(' ', '', $user['role'])) ?>">
                                                <?= h($user['role']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?= h($user['status']) ?>">
                                                <?= h($user['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($user['role'] === 'Farmer'): ?>
                                                <div class="cell-scope" title="<?= number_format((int)$user['farm_count']) ?> farms • <?= number_format((int)$user['total_livestock']) ?> livestock">
                                                    <span><strong><?= number_format((int)$user['farm_count']) ?></strong> farms &bull; <strong><?= number_format((int)$user['total_livestock']) ?></strong> head</span>
                                                </div>
                                            <?php elseif ($user['role'] === 'Agriculture Official'): ?>
                                                <div class="cell-scope" title="Assigned District Oversight">
                                                    <span style="display:inline-flex; align-items:center; gap:4px; font-weight:600; font-size:0.75rem; color:#2563eb; background:rgba(37,99,235,0.08); padding:3px 8px; border-radius:6px;">
                                                        <i class="bi bi-geo-alt-fill"></i> <?= h($user['municipality'] ?: '1st District') ?> Scope
                                                    </span>
                                                </div>
                                            <?php else: ?>
                                                <div class="cell-scope" title="Full System Administrator Governance">
                                                    <span style="display:inline-flex; align-items:center; gap:4px; font-weight:600; font-size:0.75rem; color:#7c3aed; background:rgba(124,58,237,0.08); padding:3px 8px; border-radius:6px;">
                                                        <i class="bi bi-shield-lock-fill"></i> Central Admin
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($user['role'] === 'Agriculture Official' && $user['department']): ?>
                                                <div class="cell-official" title="<?= h($user['position']) ?> • <?= h($user['department']) ?> • <?= h($user['municipality']) ?>">
                                                    <div class="official-pos"><?= h($user['position']) ?></div>
                                                    <div class="user-subtext"><?= h($user['department']) ?> &bull; <?= h($user['municipality']) ?></div>
                                                </div>
                                            <?php elseif ($user['role'] === 'Admin'): ?>
                                                <div class="cell-official" title="Department of Agriculture Regional ICT & System Governance">
                                                    <div class="official-pos" style="color:var(--text-primary); font-weight:600;">System Administrator</div>
                                                    <div class="user-subtext">DA ICT &bull; Full District Access</div>
                                                </div>
                                            <?php else: ?>
                                                <span class="user-subtext">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="white-space:nowrap; font-size:0.78rem;"><?= h(date('M d, Y', strtotime($user['createdAt']))) ?></td>
                                        <td>
                                            <div class="action-flex" style="justify-content: flex-end;">
                                                <?php if ($user['role'] === 'Farmer'): ?>
                                                    <?php if ($user['status'] === 'Pending'): ?>
                                                        <form method="post" style="display:inline;">
                                                            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                                            <input type="hidden" name="action" value="approve_farmer">
                                                            <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
                                                            <button class="btn-act-icon btn-act-approve" type="submit" title="Approve Farmer"><i class="bi bi-check2"></i></button>
                                                        </form>
                                                        <form method="post" style="display:inline;">
                                                            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                                            <input type="hidden" name="action" value="reject_farmer">
                                                            <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
                                                            <button class="btn-act-icon btn-act-reject" type="submit" title="Reject Farmer"><i class="bi bi-x-lg"></i></button>
                                                        </form>
                                                    <?php elseif ($user['status'] === 'Active'): ?>
                                                        <form method="post" style="display:inline;">
                                                            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                                            <input type="hidden" name="action" value="deactivate_farmer">
                                                            <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
                                                            <button class="btn-act-icon btn-act-warning" type="submit" title="Suspend User Account"><i class="bi bi-pause-fill"></i></button>
                                                        </form>
                                                    <?php else: ?>
                                                        <form method="post" style="display:inline;">
                                                            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                                            <input type="hidden" name="action" value="activate_farmer">
                                                            <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
                                                            <button class="btn-act-icon btn-act-approve" type="submit" title="Reactivate User Account"><i class="bi bi-play-fill"></i></button>
                                                        </form>
                                                    <?php endif; ?>
                                                <?php elseif ($user['role'] === 'Agriculture Official'): ?>
                                                    <?php if ($user['status'] === 'Pending'): ?>
                                                        <form method="post" style="display:inline;">
                                                            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                                            <input type="hidden" name="action" value="approve_official">
                                                            <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
                                                            <button class="btn-act-icon btn-act-approve" type="submit" title="Approve Official"><i class="bi bi-check2"></i></button>
                                                        </form>
                                                        <form method="post" style="display:inline;">
                                                            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                                            <input type="hidden" name="action" value="reject_official">
                                                            <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
                                                            <button class="btn-act-icon btn-act-reject" type="submit" title="Reject Official"><i class="bi bi-x-lg"></i></button>
                                                        </form>
                                                    <?php elseif ($user['status'] === 'Active'): ?>
                                                        <form method="post" style="display:inline;">
                                                            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                                            <input type="hidden" name="action" value="reject_official">
                                                            <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
                                                            <button class="btn-act-icon btn-act-warning" type="submit" title="Suspend Official Account"><i class="bi bi-pause-fill"></i></button>
                                                        </form>
                                                    <?php else: ?>
                                                        <form method="post" style="display:inline;">
                                                            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                                            <input type="hidden" name="action" value="approve_official">
                                                            <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
                                                            <button class="btn-act-icon btn-act-approve" type="submit" title="Reactivate Official Account"><i class="bi bi-play-fill"></i></button>
                                                        </form>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="badge-icon-protected" title="Protected System Administrator Account">
                                                        <i class="bi bi-shield-lock-fill"></i>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- ── SECTION 6: INCIDENTS & REPORTS ──────────────────────────── -->
            <section id="incidents" class="section">
                <div class="section-header">
                    <div>
                        <h3>Incident Management &amp; Public Reports</h3>
                        <p>Track disease outbreaks, sick animal alerts, and community incident reports.</p>
                    </div>
                    <a class="btn-top btn-top-ghost" href="?export=incidents"><i class="bi bi-download"></i> Export CSV</a>
                </div>

                <div class="table-panel">
                    <div class="table-toolbar">
                        <div class="search-container">
                            <i class="bi bi-search"></i>
                            <input data-search="incidents-table" placeholder="Search incidents by title, location, or status...">
                        </div>
                    </div>
                    <div class="table-scroll">
                        <table class="modern-table" id="incidents-table">
                            <thead>
                                <tr>
                                    <th>Incident Title &amp; Description</th>
                                    <th>Report Source</th>
                                    <th>Location</th>
                                    <th>Current Status</th>
                                    <th>Priority Level</th>
                                    <th>Reporter</th>
                                    <th>Update Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($incidents as $incident): ?>
                                    <tr>
                                        <td>
                                            <strong style="color:var(--text-primary);"><?= h($incident['title']) ?></strong>
                                            <div class="user-subtext"><?= h($incident['description']) ?></div>
                                        </td>
                                        <td>
                                            <span style="font-weight:700; color:var(--text-primary);"><?= h($incident['source']) ?></span>
                                            <div class="user-subtext"><?= h($incident['type']) ?></div>
                                        </td>
                                        <td>
                                            <div style="font-weight:600; color:var(--text-primary);"><i class="bi bi-geo-alt-fill text-rose-500"></i> <?= h($incident['municipality']) ?></div>
                                            <div class="user-subtext"><?= h($incident['farm_name'] ?: 'Public Area') ?></div>
                                        </td>
                                        <td>
                                            <span class="badge <?= h(str_replace(' ', '', $incident['status'])) ?>">
                                                <?= h($incident['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?= h($incident['priority']) ?>">
                                                <?= h($incident['priority']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="font-weight:600; color:var(--text-primary);"><?= h($incident['reporter']) ?></div>
                                            <div class="user-subtext"><?= h($incident['contactPhone'] ?: $incident['contactEmail']) ?></div>
                                        </td>
                                        <td>
                                            <form method="post" style="display:flex; gap:6px; align-items:center;">
                                                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                                <input type="hidden" name="action" value="update_incident_status">
                                                <input type="hidden" name="id" value="<?= (int)$incident['id'] ?>">
                                                <input type="hidden" name="source_table" value="<?= h($incident['source_table']) ?>">
                                                <select name="status" class="inline-select">
                                                    <?php
                                                        $statuses = $incident['source_table'] === 'public_reports'
                                                            ? ['Pending', 'Reviewed', 'Resolved', 'Closed']
                                                            : ['Pending', 'In Progress', 'Resolved', 'Closed'];
                                                        foreach ($statuses as $status):
                                                    ?>
                                                        <option value="<?= h($status) ?>" <?= $incident['status'] === $status ? 'selected' : '' ?>><?= h($status) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button class="btn-act-icon btn-act-approve" type="submit" title="Save Incident Status" aria-label="Save"><i class="bi bi-check2"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- ── SECTION 7: GIS GEO-MONITORING ───────────────────────────── -->
            <section id="map" class="section">
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

            <!-- ── SECTION 8: REPORTS & EXPORTS ────────────────────────────── -->
            <section id="reports" class="section">
                <div class="section-header">
                    <div>
                        <h3>Document Exports &amp; Presentation Data</h3>
                        <p>Generate clean, defense-ready CSV spreadsheets for analysis, grading, and reporting.</p>
                    </div>
                </div>

                <div class="report-tiles-grid">
                    <div class="report-tile-card">
                        <div>
                            <div class="report-tile-top">
                                <div class="report-tile-icon"><i class="bi bi-people-fill"></i></div>
                                <span class="format-badge">CSV &bull; UTF-8</span>
                            </div>
                            <h4>Farmers Roster</h4>
                            <p>Complete directory of farmers with phone, email, registration timestamp, and farm count.</p>
                        </div>
                        <a class="btn-download-csv" href="?export=farmers">
                            <i class="bi bi-download"></i> Download Farmers CSV
                        </a>
                    </div>

                    <div class="report-tile-card">
                        <div>
                            <div class="report-tile-top">
                                <div class="report-tile-icon"><i class="bi bi-houses-fill"></i></div>
                                <span class="format-badge">CSV &bull; UTF-8</span>
                            </div>
                            <h4>Farm Properties</h4>
                            <p>First-district agricultural holdings categorized by municipality and approval status.</p>
                        </div>
                        <a class="btn-download-csv" href="?export=farms">
                            <i class="bi bi-download"></i> Download Farms CSV
                        </a>
                    </div>

                    <div class="report-tile-card">
                        <div>
                            <div class="report-tile-top">
                                <div class="report-tile-icon"><i class="bi bi-tags-fill"></i></div>
                                <span class="format-badge">CSV &bull; UTF-8</span>
                            </div>
                            <h4>Livestock Inventory</h4>
                            <p>Itemized headcounts, breed, health statuses, and vaccination progress across all herds.</p>
                        </div>
                        <a class="btn-download-csv" href="?export=livestock">
                            <i class="bi bi-download"></i> Download Livestock CSV
                        </a>
                    </div>

                    <div class="report-tile-card">
                        <div>
                            <div class="report-tile-top">
                                <div class="report-tile-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
                                <span class="format-badge">CSV &bull; UTF-8</span>
                            </div>
                            <h4>Incidents &amp; Hazards</h4>
                            <p>Unified dataset of farm health incidents and citizen public sickness reports.</p>
                        </div>
                        <a class="btn-download-csv" href="?export=incidents">
                            <i class="bi bi-download"></i> Download Incidents CSV
                        </a>
                    </div>
                </div>
            </section>

            <!-- ── SECTION 9: AUDIT LOGS ───────────────────────────────────── -->
            <section id="audit" class="section">
                <div class="section-header">
                    <div>
                        <h3>System Accountability &amp; Audit Logs</h3>
                        <p>Immutable record of administrative approvals, status modifications, and security events.</p>
                    </div>
                </div>

                <div class="table-panel">
                    <div class="table-toolbar">
                        <div class="search-container">
                            <i class="bi bi-search"></i>
                            <input data-search="audit-table" placeholder="Search audit trail by user, action, or table...">
                        </div>
                    </div>
                    <div class="table-scroll">
                        <table class="modern-table" id="audit-table">
                            <thead>
                                <tr>
                                    <th>Timestamp</th>
                                    <th>Actor</th>
                                    <th>Action Performed</th>
                                    <th>Target Table</th>
                                    <th>Record ID</th>
                                    <th>Payload Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($auditLogs as $log): ?>
                                    <tr>
                                        <td style="white-space:nowrap; font-weight:600; color:var(--text-primary);">
                                            <?= h(date('M d, Y H:i:s', strtotime($log['createdAt']))) ?>
                                        </td>
                                        <td>
                                            <div style="font-weight:700; color:var(--text-primary);">
                                                <?= h(trim(($log['firstName'] ?? '') . ' ' . ($log['lastName'] ?? '')) ?: ($log['email'] ?? 'System Core')) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge" style="background:#f1f5f9; color:#334155; font-family:monospace;">
                                                <?= h($log['action']) ?>
                                            </span>
                                        </td>
                                        <td><strong><?= h($log['tableName']) ?></strong></td>
                                        <td><span style="font-family:monospace; color:var(--text-muted);">#<?= (int)$log['recordId'] ?></span></td>
                                        <td style="font-size:0.75rem; color:var(--text-muted); max-width:320px; word-break:break-word;">
                                            <?= h($log['details']) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

        </div>
    </main>

    <!-- ── INTERACTIVE JAVASCRIPT ENGINE ───────────────────────────────────── -->
    <script>
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const navButtons = document.querySelectorAll('[data-section]');
        const sections = document.querySelectorAll('.section');
        const pageTitle = document.getElementById('topbarPageTitle');
        const pageSub = document.getElementById('topbarPageSub');

        const sectionMeta = {
            'dashboard': {
                title: 'District Command & Surveillance',
                sub: 'Camarines Sur 1st District Jurisdiction • Cabusao, Del Gallego, Lupi, Ragay, Sipocot'
            },
            'map': {
                title: 'GIS Geo-Monitoring & Spatial Surveillance',
                sub: 'Interactive Parcellary Geospatial Registry • Real-Time Spatial Sync'
            },
            'farmers': {
                title: 'Farmer Registry & Identity Management',
                sub: 'Accredited District Producer Directory • Camarines Sur 1st District'
            },
            'farms': {
                title: 'Farm Holdings & Geographic Parcellary',
                sub: 'Cadastral Boundaries, Commodity Classifications & Verification'
            },
            'livestock': {
                title: 'Livestock Inventory & Biosecurity Trace',
                sub: 'Ear-Tag Allocation, Species Census & Vaccination Records'
            },
            'incidents': {
                title: 'Incident Alerts & Biosecurity Reports',
                sub: 'Rapid Outbreak Surveillance & Emergency Incident Response'
            },
            'users': {
                title: 'System User & Access Governance',
                sub: 'Role-Based Access Control, Field Officer & Admin Credentials'
            },
            'reports': {
                title: 'Export Reports & Analytical Summaries',
                sub: 'Standardized Department of Agriculture District Reports & CSV Exports'
            },
            'audit': {
                title: 'System Audit Trail & Security Logs',
                sub: 'Immutable Activity Chronology & Authenticated Operations'
            }
        };

        function activateSection(sectionId) {
            const targetSection = document.getElementById(sectionId);
            if (!targetSection) return;

            sections.forEach((section) => section.classList.toggle('active', section.id === sectionId));
            navButtons.forEach((button) => button.classList.toggle('active', button.dataset.section === sectionId));

            if (sectionMeta[sectionId]) {
                if (pageTitle) pageTitle.textContent = sectionMeta[sectionId].title;
                if (pageSub) pageSub.textContent = sectionMeta[sectionId].sub;
                if (window.getAgriTraceLanguage && window.getAgriTraceLanguage() === 'tl' && window.setAgriTraceLanguage) {
                    window.setAgriTraceLanguage('tl');
                }
            }

            if (location.hash !== '#' + sectionId) {
                history.replaceState(null, '', '#' + sectionId);
            }

            sidebar?.classList.remove('open', 'hover-open');
            sidebarOverlay?.classList.remove('open');
            window.scrollTo({ top: 0, behavior: 'smooth' });

            if (sectionId === 'map') {
                const mapFrame = document.querySelector('.map-frame-view');
                if (mapFrame && mapFrame.contentWindow) {
                    try { mapFrame.contentWindow.postMessage({ action: 'resize' }, '*'); } catch (e) {}
                    setTimeout(() => { try { mapFrame.contentWindow.postMessage({ action: 'resize' }, '*'); } catch (e) {} }, 150);
                    setTimeout(() => { try { mapFrame.contentWindow.postMessage({ action: 'resize' }, '*'); } catch (e) {} }, 350);
                }
            }
        }
        window.activateSection = activateSection;

        // Listen for navigation messages from embedded iframes (e.g. Map "Dashboard" button)
        window.addEventListener('message', (event) => {
            if (event.data && event.data.action === 'nav' && event.data.section) {
                activateSection(event.data.section);
            }
        });

        // Nav Button Direct Click Listeners
        navButtons.forEach((button) => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                activateSection(button.dataset.section);
            });
        });

        // Event Delegation on Sidebar Nav (fail-safe for any nested element clicks)
        document.querySelector('.sidebar-nav')?.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-section]');
            if (btn && btn.dataset.section) {
                e.preventDefault();
                activateSection(btn.dataset.section);
            }
        });

        // Dashboard Card Quick Jump Buttons
        document.addEventListener('click', (e) => {
            const jumpBtn = e.target.closest('[data-section-jump]');
            if (jumpBtn && jumpBtn.dataset.sectionJump) {
                e.preventDefault();
                activateSection(jumpBtn.dataset.sectionJump);
            }
        });

        // ── AUTO-HIDE / FULL VIEW ENGINE ─────────────────────────────────────
        const hoverZone = document.getElementById('sidebarHoverZone');
        const edgeTrigger = document.getElementById('sidebarEdgeTrigger');
        const pinBtn = document.getElementById('sidebarPinBtn');
        const sidebarToggle = document.getElementById('sidebarToggle') || document.getElementById('mobileToggle');

        // Check stored preference - default to TRUE (autohide for full view)
        let isAutoHide = localStorage.getItem('agritrace_sidebar_autohide') !== 'false';

        function applyAutoHideMode(enabled) {
            isAutoHide = enabled;
            localStorage.setItem('agritrace_sidebar_autohide', enabled ? 'true' : 'false');
            if (enabled) {
                document.body.classList.add('sidebar-autohide');
                sidebarToggle?.classList.add('active');
                if (pinBtn) pinBtn.innerHTML = '<i class="bi bi-pin-angle" title="Click to Pin Sidebar Open"></i>';
            } else {
                document.body.classList.remove('sidebar-autohide');
                sidebar?.classList.remove('hover-open');
                sidebarToggle?.classList.remove('active');
                if (pinBtn) pinBtn.innerHTML = '<i class="bi bi-pin-fill" title="Click to Auto-Hide for Full View"></i>';
            }
            setTimeout(() => {
                window.dispatchEvent(new Event('resize'));
                const mapFrame = document.querySelector('.map-frame-view');
                if (mapFrame && mapFrame.contentWindow) {
                    try { mapFrame.contentWindow.postMessage({ action: 'resize' }, '*'); } catch (e) {}
                }
            }, 300);
        }

        // Initialize Auto-Hide State
        applyAutoHideMode(isAutoHide);

        // Pin Button in Sidebar Header
        pinBtn?.addEventListener('click', (e) => {
            e.stopPropagation();
            applyAutoHideMode(!isAutoHide);
        });

        // Topbar Sidebar / Full View Toggle Button
        sidebarToggle?.addEventListener('click', (e) => {
            e.stopPropagation();
            if (window.innerWidth <= 1024) {
                const isOpen = sidebar?.classList.toggle('open');
                sidebarOverlay?.classList.toggle('open', !!isOpen);
            } else {
                if (sidebar?.classList.contains('hover-open') || sidebar?.classList.contains('open')) {
                    sidebar.classList.remove('hover-open', 'open');
                } else {
                    sidebar.classList.add('hover-open');
                }
            }
        });

        // Edge Hover Trigger
        let hoverTimeout = null;
        function openSidebarHover() {
            if (!document.body.classList.contains('sidebar-autohide') || window.innerWidth <= 1024) return;
            clearTimeout(hoverTimeout);
            sidebar?.classList.add('hover-open');
        }
        function closeSidebarHover() {
            if (!document.body.classList.contains('sidebar-autohide') || window.innerWidth <= 1024) return;
            clearTimeout(hoverTimeout);
            hoverTimeout = setTimeout(() => {
                sidebar?.classList.remove('hover-open');
            }, 300);
        }

        hoverZone?.addEventListener('mouseenter', openSidebarHover);
        edgeTrigger?.addEventListener('mouseenter', openSidebarHover);
        edgeTrigger?.addEventListener('click', (e) => {
            e.stopPropagation();
            sidebar?.classList.toggle('hover-open');
        });

        sidebar?.addEventListener('mouseenter', () => {
            clearTimeout(hoverTimeout);
        });
        sidebar?.addEventListener('mouseleave', closeSidebarHover);

        // Screen edge cursor detection fallback
        document.addEventListener('mousemove', (e) => {
            if (!document.body.classList.contains('sidebar-autohide') || window.innerWidth <= 1024) return;
            if (e.clientX <= 14) {
                openSidebarHover();
            }
        });

        // Overlay click closes on both mobile and desktop
        sidebarOverlay?.addEventListener('click', () => {
            sidebar?.classList.remove('open', 'hover-open');
            sidebarOverlay?.classList.remove('open');
        });

        // Keyboard Shortcut: [ or Ctrl+B to toggle full view
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey && e.key.toLowerCase() === 'b') || (e.key === '[' && !['INPUT','TEXTAREA','SELECT'].includes(document.activeElement.tagName))) {
                e.preventDefault();
                applyAutoHideMode(!isAutoHide);
            }
        });

        // Hash Navigation & PopState
        window.addEventListener('hashchange', () => {
            const h = location.hash ? location.hash.substring(1) : 'dashboard';
            if (document.getElementById(h)) {
                activateSection(h);
            }
        });

        // Initialize Section from URL Hash
        const initialSection = location.hash ? location.hash.substring(1) : 'dashboard';
        if (document.getElementById(initialSection)) {
            activateSection(initialSection);
        }

        // Live Table Search Engine
        document.querySelectorAll('[data-search]').forEach((input) => {
            input.addEventListener('input', () => {
                const table = document.getElementById(input.dataset.search);
                const query = input.value.trim().toLowerCase();
                table?.querySelectorAll('tbody tr').forEach((row) => {
                    row.style.display = row.innerText.toLowerCase().includes(query) ? '' : 'none';
                });
            });
        });
        try { localStorage.removeItem('agritrace_admin_font'); } catch (e) {}
    </script>
    <script src="../assets/js/translator.js"></script>
</body>
</html>
