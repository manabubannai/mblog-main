<?php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rtrim($uri, '/');

// Serve existing files directly
if ($uri !== '' && file_exists(__DIR__ . $uri)) {
    return false;
}

// Route clean slugs to post files: /slug → /posts/slug.php
if (preg_match('#^/([a-z0-9_-]+)$#', $uri, $m)) {
    $file = __DIR__ . '/posts/' . $m[1] . '.php';
    if (file_exists($file)) {
        require $file;
        return;
    }
}

// Default to index.php
require __DIR__ . '/index.php';
