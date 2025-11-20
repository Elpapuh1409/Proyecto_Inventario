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
try {
  $pdo = db();
  $stmt = $pdo->prepare('DELETE FROM inventario WHERE id=?');
  $stmt->execute([$id]);
  echo json_encode(['success'=>true,'deleted_id'=>$id]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
