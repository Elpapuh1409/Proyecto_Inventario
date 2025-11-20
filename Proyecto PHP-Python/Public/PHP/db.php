<?php
declare(strict_types=1);

function db_config(): array {
    $file = __DIR__ . DIRECTORY_SEPARATOR . 'config.php';
    if (is_file($file)) {
        $cfg = include $file;
        if (is_array($cfg)) return $cfg;
    }
    $fallback = __DIR__ . DIRECTORY_SEPARATOR . 'config.example.php';
    if (is_file($fallback)) {
        $cfg = include $fallback;
        if (is_array($cfg)) return $cfg;
    }
    return [];
}

function db_dsn(array $cfg): string {
    $driver = $cfg['DB_DRIVER'] ?? 'mysql';
    if ($driver === 'sqlite') {
        $path = $cfg['DB_PATH'] ?? (__DIR__ . '/../data/app.sqlite');
        // Asegura el directorio para SQLite
        $dir = dirname($path);
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        return 'sqlite:' . $path;
    }
    $host = $cfg['DB_HOST'] ?? '127.0.0.1';
    $port = $cfg['DB_PORT'] ?? '3306';
    $name = $cfg['DB_NAME'] ?? 'test';
    $charset = $cfg['DB_CHARSET'] ?? 'utf8mb4';
    return sprintf('%s:host=%s;port=%s;dbname=%s;charset=%s', $driver, $host, $port, $name, $charset);
}

/**
 * Obtiene una conexión PDO única para toda la petición.
 * @return PDO
 */
function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $cfg = db_config();
    $dsn = db_dsn($cfg);
    $user = $cfg['DB_USER'] ?? null;
    $pass = $cfg['DB_PASS'] ?? null;

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_PERSISTENT => false,
    ];

    $pdo = new PDO($dsn, $user, $pass, $options);
    if (($cfg['DB_DRIVER'] ?? 'mysql') === 'sqlite') {
        $pdo->exec('PRAGMA foreign_keys = ON;');
    }
    return $pdo;
}
