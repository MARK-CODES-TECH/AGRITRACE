<?php
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method not allowed';
    exit;
}

require_once __DIR__ . '/../db_connect.php';

$input = json_decode(file_get_contents('php://input'), true);
$format = strtolower(trim($input['format'] ?? 'csv'));
$reportType = strtolower(trim($input['report_type'] ?? 'complete'));

$allowedFormats = ['csv', 'json', 'pdf'];
if (!in_array($format, $allowedFormats, true)) {
    $format = 'csv';
}

$datasets = [];

if ($reportType === 'complete' || $reportType === 'farms') {
    $stmt = $pdo->query("SELECT id, name, ownerName, type, status, createdAt FROM farms ORDER BY id DESC");
    $datasets['farms'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($reportType === 'complete' || $reportType === 'livestock') {
    $stmt = $pdo->query("SELECT id, farmId, type, qty, healthStatus, createdAt FROM livestock ORDER BY id DESC");
    $datasets['livestock'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($reportType === 'complete' || $reportType === 'incidents') {
    $stmt = $pdo->query("SELECT id, farmId, type, title, status, priority, createdAt FROM incidents ORDER BY id DESC");
    $datasets['incidents'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$filenameBase = 'agritrace_' . $reportType . '_' . date('Ymd_His');

if ($format === 'json') {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $filenameBase . '.json"');
    echo json_encode(['generatedAt' => date('c'), 'reportType' => $reportType, 'data' => $datasets], JSON_PRETTY_PRINT);
    exit;
}

if ($format === 'pdf') {
    // Fallback text-based export with PDF MIME for compatibility with existing UI flow.
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filenameBase . '.pdf"');

    $content = "AgriTrace Report\n";
    $content .= "Generated: " . date('Y-m-d H:i:s') . "\n";
    $content .= "Type: " . strtoupper($reportType) . "\n\n";

    foreach ($datasets as $name => $rows) {
        $content .= strtoupper($name) . "\n";
        $content .= str_repeat('-', 50) . "\n";
        foreach ($rows as $row) {
            $content .= implode(' | ', array_map(static fn($v) => (string)$v, $row)) . "\n";
        }
        $content .= "\n";
    }

    echo $content;
    exit;
}

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filenameBase . '.csv"');

$output = fopen('php://output', 'w');
foreach ($datasets as $name => $rows) {
    fputcsv($output, [strtoupper($name)]);
    if (!empty($rows)) {
        fputcsv($output, array_keys($rows[0]));
        foreach ($rows as $row) {
            fputcsv($output, $row);
        }
    } else {
        fputcsv($output, ['No data']);
    }
    fputcsv($output, []);
}
fclose($output);
