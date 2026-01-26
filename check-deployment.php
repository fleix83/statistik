<?php
/**
 * Deployment Check Script
 * Access this file to verify the deployment structure
 * DELETE THIS FILE after verifying deployment
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Deployment Check</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .check { padding: 10px; margin: 10px 0; background: white; border-left: 4px solid #ccc; }
        .ok { border-color: #4CAF50; }
        .error { border-color: #f44336; }
        pre { background: #f0f0f0; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔍 Deployment Structure Check</h1>

    <?php
    $checks = [
        'index.html exists' => file_exists(__DIR__ . '/index.html'),
        'assets/ directory exists' => is_dir(__DIR__ . '/assets'),
        '.htaccess exists' => file_exists(__DIR__ . '/.htaccess'),
        'api/ directory exists' => is_dir(__DIR__ . '/api'),
        'db/ directory exists' => is_dir(__DIR__ . '/db')
    ];

    foreach ($checks as $check => $result) {
        $class = $result ? 'ok' : 'error';
        $icon = $result ? '✅' : '❌';
        echo "<div class='check $class'>$icon $check</div>";
    }
    ?>

    <h2>📂 Files in Root Directory:</h2>
    <pre><?php
    $files = scandir(__DIR__);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            $type = is_dir(__DIR__ . '/' . $file) ? '[DIR]' : '[FILE]';
            echo "$type $file\n";
        }
    }
    ?></pre>

    <?php if (is_dir(__DIR__ . '/assets')): ?>
    <h2>📂 Files in /assets Directory:</h2>
    <pre><?php
    $files = scandir(__DIR__ . '/assets');
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            echo "[FILE] $file\n";
        }
    }
    ?></pre>
    <?php endif; ?>

    <h2>🌍 Server Info:</h2>
    <pre><?php
    echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
    echo "Script Filename: " . $_SERVER['SCRIPT_FILENAME'] . "\n";
    echo "Request URI: " . $_SERVER['REQUEST_URI'] . "\n";
    echo "HTTP Host: " . $_SERVER['HTTP_HOST'] . "\n";
    ?></pre>

    <hr>
    <p><strong>⚠️ DELETE THIS FILE after checking!</strong></p>
</body>
</html>
