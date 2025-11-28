<?php
require_once __DIR__ . '/config.php';

try {
    // Check if Railway provides DATABASE_URL (format: mysql://user:pass@host:port/dbname)
    $databaseUrl = getenv('DATABASE_URL');
    
    if ($databaseUrl) {
        // Parse Railway's DATABASE_URL
        $url = parse_url($databaseUrl);
        $dsn = 'mysql:host=' . $url['host'] . ';port=' . ($url['port'] ?? 3306) . ';dbname=' . ltrim($url['path'], '/') . ';charset=utf8mb4';
        $user = $url['user'];
        $pass = $url['pass'];
    } else {
        // Use individual environment variables (local or custom)
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $user = DB_USER;
        $pass = DB_PASS;
    }
    
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}
