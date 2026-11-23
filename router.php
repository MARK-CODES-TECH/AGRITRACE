<?php
/**
 * AgriTrace+ Development Server Router
 * Enables full URL routing for PHP Built-in Server
 */
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = urldecode($uri);

// Strip optional project prefix like /AG/ or /AGRITRACE/
$uri = preg_replace('#^/(AG|AGRITRACE)(/|$)#i', '/', $uri);

$filePath = __DIR__ . $uri;

// Root request -> Landing page
if ($uri === '/' || $uri === '') {
    require __DIR__ . '/pages/index.php';
    exit;
}

// Direct static file or existing PHP file
if (is_file($filePath)) {
    if (pathinfo($filePath, PATHINFO_EXTENSION) === 'php') {
        require $filePath;
        exit;
    }
    // Static asset (css, js, images) -> built-in server handles MIME & transfer
    return false;
}

// Root-level page request (e.g. /login.php -> pages/login.php)
if (preg_match('#^/([a-zA-Z0-9_\-]+)\.php$#', $uri, $matches)) {
    $pageFile = __DIR__ . '/pages/' . $matches[1] . '.php';
    if (is_file($pageFile)) {
        require $pageFile;
        exit;
    }
}

// Friendly URL request (e.g. /login -> pages/login.php)
if (preg_match('#^/([a-zA-Z0-9_\-]+)/?$#', $uri, $matches)) {
    $pageFile = __DIR__ . '/pages/' . $matches[1] . '.php';
    if (is_file($pageFile)) {
        require $pageFile;
        exit;
    }
}

// 404
http_response_code(404);
echo "404 Not Found: " . htmlspecialchars($uri);

