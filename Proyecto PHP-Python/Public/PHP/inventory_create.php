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
$nombre = trim((string)($input['nombre'] ?? ''));
$responsable = trim((string)($input['responsable'] ?? ''));
$estado = trim((string)($input['estado'] ?? ''));
$fecha = trim((string)($input['fecha'] ?? ''));

if ($nombre === '' || $responsable === '' || $estado === '' || $fecha === '') {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'Campos requeridos: nombre, responsable, estado, fecha']);
    exit;
}
try {
    $pdo = db();
    $stmt = $pdo->prepare('INSERT INTO inventario (nombre,responsable,estado,fecha) VALUES (?,?,?,?)');
    $stmt->execute([$nombre,$responsable,$estado,$fecha]);
    $id = (int)$pdo->lastInsertId();
    echo json_encode(['success'=>true,'id'=>$id,'item'=>['id'=>$id,'nombre'=>$nombre,'responsable'=>$responsable,'estado'=>$estado,'fecha'=>$fecha]], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
