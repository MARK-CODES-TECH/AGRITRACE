<?php
/**
 * 🔍 Camarines Sur Database Audit Tool
 * Diagnostic dashboard for checking database integrity and data
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../db_connect.php';

$results = [];

try {
    // 1. FARMS IN CAMARINES SUR
    $query = "SELECT 
        f.id,
        f.name,
        f.type,
        f.address,
        f.latitude,
        f.longitude,
        f.status,
        u.firstName,
        u.lastName,
        f.createdAt
    FROM farms f
    LEFT JOIN users u ON f.ownerId = u.id
    WHERE f.address LIKE '%Camarines%' 
       OR f.address LIKE '%CamSur%'
       OR (f.latitude BETWEEN 13.0 AND 14.5 AND f.longitude BETWEEN 120.0 AND 123.5)
    ORDER BY f.createdAt DESC";
    
    $stmt = $pdo->query($query);
    $results['farms'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $results['total_farms'] = count($results['farms']);

    // 2. LIVESTOCK IN FARMS
    $query = "SELECT 
        f.id,
        f.name,
        COUNT(l.id) as livestock_count,
        SUM(l.qty) as total_qty,
        GROUP_CONCAT(DISTINCT l.type SEPARATOR ', ') as livestock_types
    FROM farms f
    LEFT JOIN livestock l ON f.id = l.farmId
    WHERE f.address LIKE '%Camarines%' 
       OR f.address LIKE '%CamSur%'
       OR (f.latitude BETWEEN 13.0 AND 14.5 AND f.longitude BETWEEN 120.0 AND 123.5)
    GROUP BY f.id, f.name";
    
    $stmt = $pdo->query($query);
    $results['livestock_summary'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. INCIDENTS IN CAMARINES SUR
    $query = "SELECT 
        i.id,
        i.title,
        i.type,
        i.priority,
        i.status,
        i.latitude,
        i.longitude,
        i.createdAt,
        f.name as farm_name
    FROM incidents i
    LEFT JOIN farms f ON i.farmId = f.id
    WHERE f.address LIKE '%Camarines%' 
       OR f.address LIKE '%CamSur%'
       OR (i.latitude BETWEEN 13.0 AND 14.5 AND i.longitude BETWEEN 120.0 AND 123.5)
    ORDER BY i.priority DESC, i.createdAt DESC";
    
    $stmt = $pdo->query($query);
    $results['incidents'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $results['total_incidents'] = count($results['incidents']);

    // 4. STATISTICS
    $query = "SELECT 
        COUNT(DISTINCT f.id) as total_farms,
        COUNT(DISTINCT f.ownerId) as unique_owners,
        SUM(COALESCE(l.qty, 0)) as total_livestock,
        COUNT(DISTINCT i.id) as total_incidents,
        SUM(CASE WHEN i.priority = 'Critical' THEN 1 ELSE 0 END) as critical_incidents,
        SUM(CASE WHEN i.status = 'Pending' THEN 1 ELSE 0 END) as pending_incidents
    FROM farms f
    LEFT JOIN livestock l ON f.id = l.farmId
    LEFT JOIN incidents i ON (f.id = i.farmId OR 
        (i.latitude BETWEEN 13.0 AND 14.5 AND i.longitude BETWEEN 120.0 AND 123.5))
    WHERE f.address LIKE '%Camarines%' OR f.address LIKE '%CamSur%'
       OR (f.latitude BETWEEN 13.0 AND 14.5 AND f.longitude BETWEEN 120.0 AND 123.5)";
    
    $stmt = $pdo->query($query);
    $results['stats'] = $stmt->fetch(PDO::FETCH_ASSOC);

    // 5. DATA QUALITY CHECK
    $issues = [];
    
    $query = "SELECT COUNT(*) as count FROM farms f 
              WHERE (f.address LIKE '%Camarines%' OR f.address LIKE '%CamSur%'
                  OR (f.latitude BETWEEN 13.0 AND 14.5 AND f.longitude BETWEEN 120.0 AND 123.5))
              AND (f.latitude IS NULL OR f.latitude = 0)";
    $stmt = $pdo->query($query);
    $missing_lat = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    if ($missing_lat > 0) $issues[] = "⚠️ $missing_lat farms missing latitude";

    $query = "SELECT COUNT(*) as count FROM farms f 
              WHERE (f.address LIKE '%Camarines%' OR f.address LIKE '%CamSur%'
                  OR (f.latitude BETWEEN 13.0 AND 14.5 AND f.longitude BETWEEN 120.0 AND 123.5))
              AND (f.longitude IS NULL OR f.longitude = 0)";
    $stmt = $pdo->query($query);
    $missing_lng = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    if ($missing_lng > 0) $issues[] = "⚠️ $missing_lng farms missing longitude";

    $query = "SELECT COUNT(*) as count FROM farms f 
              WHERE (f.address LIKE '%Camarines%' OR f.address LIKE '%CamSur%'
                  OR (f.latitude BETWEEN 13.0 AND 14.5 AND f.longitude BETWEEN 120.0 AND 123.5))
              AND f.status != 'Approved'";
    $stmt = $pdo->query($query);
    $unapproved = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    if ($unapproved > 0) $issues[] = "⚠️ $unapproved farms not approved";

    $results['issues'] = $issues;
    $results['status'] = 'success';
    $results['timestamp'] = date('Y-m-d H:i:s');

} catch (Exception $e) {
    $results['status'] = 'error';
    $results['error'] = $e->getMessage();
    $results['timestamp'] = date('Y-m-d H:i:s');
}

echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>
