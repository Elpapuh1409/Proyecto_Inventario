<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success'=>false,'error'=>'Método no permitido']); exit; }
require __DIR__ . '/db.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;
$id = (int)($input['id'] ?? 0);
if ($id <= 0) { http_response_code(400); echo json_encode(['success'=>false,'error'=>'ID inválido']); exit; }

$fields = ['nombre','responsable','estado','fecha'];
$sets = [];$params=[];
foreach ($fields as $f) {
  if (array_key_exists($f,$input)) {
    $val = trim((string)$input[$f]);
    if ($val==='') continue; // ignorar vacíos
    $sets[] = "$f = ?"; $params[] = $val;
  }
}
if (!$sets) { http_response_code(400); echo json_encode(['success'=>false,'error'=>'Sin campos para actualizar']); exit; }
$params[] = $id;
try {
  $pdo = db();
  $sql = 'UPDATE inventario SET '.implode(',',$sets).' WHERE id = ?';
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  // devolver fila actualizada
  $rowStmt = $pdo->prepare('SELECT id,nombre,responsable,estado,fecha FROM inventario WHERE id=?');
  $rowStmt->execute([$id]);
  $item = $rowStmt->fetch();
  echo json_encode(['success'=>true,'item'=>$item], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
