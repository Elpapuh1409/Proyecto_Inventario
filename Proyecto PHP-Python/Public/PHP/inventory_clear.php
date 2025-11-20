<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success'=>false,'error'=>'Método no permitido']); exit; }
require __DIR__ . '/db.php';
try {
    $pdo = db();
    $pdo->exec('DELETE FROM inventario');
    // Opcional: optimizar SQLite
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        try { $pdo->exec('VACUUM'); } catch (Throwable $e) { /* ignorar */ }
    }
    echo json_encode(['success'=>true,'message'=>'Tabla limpiada']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
