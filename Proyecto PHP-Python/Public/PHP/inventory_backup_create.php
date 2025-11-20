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
    $rows = $pdo->query('SELECT id,nombre,responsable,estado,fecha FROM inventario ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
    $data = ['generated_at'=>date('c'),'count'=>count($rows),'items'=>$rows];
    $backupDir = __DIR__ . '/../backups';
    if (!is_dir($backupDir)) {
        if (!mkdir($backupDir, 0777, true) && !is_dir($backupDir)) {
            throw new RuntimeException('No se pudo crear directorio de backups');
        }
    }
    $ts = date('Ymd_His');
    $file = $backupDir . '/inventory_backup_' . $ts . '.json';
    file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
    echo json_encode(['success'=>true,'file'=>'backups/' . basename($file),'count'=>count($rows),'timestamp'=>$ts]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
