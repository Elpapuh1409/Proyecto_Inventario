<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
require __DIR__ . '/db.php';

$limit = isset($_GET['limit']) ? max(1, min(200, (int)$_GET['limit'])) : 50;
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$field = isset($_GET['field']) ? trim((string)$_GET['field']) : '';
try {
  $pdo = db();
  $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
  // asegurar tabla existe (idempotente)
  if ($driver === 'mysql') {
    $pdo->exec('CREATE TABLE IF NOT EXISTS input_logs (id INT AUTO_INCREMENT PRIMARY KEY, field TEXT NOT NULL, value TEXT NOT NULL, context TEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
  } else {
    $pdo->exec('CREATE TABLE IF NOT EXISTS input_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, field TEXT NOT NULL, value TEXT NOT NULL, context TEXT NULL, created_at TEXT NOT NULL)');
  }
  $sql = 'SELECT id, field, value, context, created_at FROM input_logs';
  $where = [];$params=[];
  if ($field !== '') { $where[] = 'field = :f'; $params[':f'] = $field; }
  if ($q !== '') {
    $like = '%'.mb_strtolower($q,'UTF-8').'%';
    $where[] = '(LOWER(field) LIKE :q OR LOWER(value) LIKE :q OR LOWER(context) LIKE :q)';
    $params[':q'] = $like;
  }
  if ($where) { $sql .= ' WHERE ' . implode(' AND ', $where); }
  $sql .= ' ORDER BY id DESC LIMIT '.(int)$limit;
  $stmt = $pdo->prepare($sql);
  foreach ($params as $k=>$v) { $stmt->bindValue($k,$v); }
  $stmt->execute();
  $rows = $stmt->fetchAll();
  echo json_encode(['success'=>true,'items'=>$rows], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
