<?php
// api/guard.php
require_once __DIR__ . '/../init.php';

// 1. Ensure user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// 2. Global CSRF Protection for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawHeaders = function_exists('apache_request_headers') ? apache_request_headers() : [];
    $headers = array_change_key_case($rawHeaders, CASE_LOWER);
    $csrf_token = $_POST['csrf_token'] ?? $headers['x-csrf-token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    
    if (empty($csrf_token) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf_token)) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'CSRF token validation failed.']);
        exit;
    }
}
