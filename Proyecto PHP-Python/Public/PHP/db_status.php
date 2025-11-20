<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require __DIR__ . '/db.php';

$status = [
  'success' => false,
  'driver' => null,
  'has_table' => false,
  'row_count' => 0,
  'error' => null,
];
try {
    $pdo = db();
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $status['driver'] = $driver;
    // Detectar tabla inventario
    if ($driver === 'sqlite') {
        $res = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='inventario'");
        $status['has_table'] = (bool)$res->fetchColumn();
    } else {
        // MySQL
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'inventario'");
        $stmt->execute();
        $status['has_table'] = (bool)$stmt->fetchColumn();
    }
    if ($status['has_table']) {
        $status['row_count'] = (int)$pdo->query('SELECT COUNT(*) FROM inventario')->fetchColumn();
    }
    $status['success'] = true;
} catch (Throwable $e) {
    $status['error'] = $e->getMessage();
    http_response_code(500);
}
echo json_encode($status, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);