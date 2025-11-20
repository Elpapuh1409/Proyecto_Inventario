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
    $input = json_decode(file_get_contents('php://input'), true);
    $requested = isset($input['file']) ? basename((string)$input['file']) : '';
    $dir = __DIR__ . '/../backups';
    if ($requested !== '') {
        $backupFile = $dir . '/' . $requested;
    } else {
        // Seleccionar el más reciente
        $files = glob($dir . '/inventory_backup_*.json');
        usort($files, function($a,$b){ return filemtime($b) <=> filemtime($a); });
        $backupFile = $files[0] ?? '';
    }
    if (!$backupFile || !is_file($backupFile)) { http_response_code(404); echo json_encode(['success'=>false,'error'=>'Backup no encontrado']); exit; }
    $raw = file_get_contents($backupFile);
    $json = json_decode($raw, true);
    if (!is_array($json) || !isset($json['items']) || !is_array($json['items'])) { throw new RuntimeException('Formato de backup inválido'); }
    $items = $json['items'];
    $pdo = db();
    $pdo->beginTransaction();
    $pdo->exec('DELETE FROM inventario');
    $ins = $pdo->prepare('INSERT INTO inventario (id,nombre,responsable,estado,fecha) VALUES (?,?,?,?,?)');
    foreach ($items as $it) {
        $ins->execute([
            $it['id'],
            $it['nombre'],
            $it['responsable'],
            $it['estado'],
            $it['fecha']
        ]);
    }
    // Ajustar autoincrement si es MySQL
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        $maxId = (int)$pdo->query('SELECT MAX(id) FROM inventario')->fetchColumn();
        $pdo->exec('ALTER TABLE inventario AUTO_INCREMENT=' . ($maxId+1));
    }
    $pdo->commit();
    echo json_encode(['success'=>true,'restored'=>count($items),'file'=>basename($backupFile)]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
