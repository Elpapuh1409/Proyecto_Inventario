<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require __DIR__ . '/db.php';

try {
    $cfg = db_config();
    $pdo = db();
    // Prueba simple y portable
    $stmt = $pdo->query('SELECT 1');
    $ok = $stmt !== false ? (int)$stmt->fetchColumn() === 1 : false;
    $driver = $cfg['DB_DRIVER'] ?? $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $version = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
    echo json_encode([
        'success' => true,
        'ok' => $ok,
        'driver' => $driver,
        'server_version' => $version,
        'php_time' => date('c'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
