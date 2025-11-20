<?php
declare(strict_types=1);
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405); header('Content-Type: application/json'); echo json_encode(['success'=>false,'error'=>'Método no permitido']); exit; }
require __DIR__ . '/db.php';
try {
    $pdo = db();
    $q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
    $estado = isset($_GET['estado']) ? trim((string)$_GET['estado']) : '';
    $responsableExact = isset($_GET['responsable_exact']) ? trim((string)$_GET['responsable_exact']) : '';
    $dateFrom = isset($_GET['date_from']) ? trim((string)$_GET['date_from']) : '';
    $dateTo   = isset($_GET['date_to']) ? trim((string)$_GET['date_to']) : '';
    $sort = isset($_GET['sort']) ? trim((string)$_GET['sort']) : 'id';
    $dir  = isset($_GET['dir']) ? strtoupper(trim((string)$_GET['dir'])) : 'ASC';
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $sql = 'SELECT id,nombre,responsable,estado,fecha FROM inventario';
    $where = [];
    $params = [];
    if ($q !== '') {
        $castId = ($driver === 'mysql') ? 'CAST(id AS CHAR)' : 'CAST(id AS TEXT)';
        $where[] = "($castId LIKE :q OR LOWER(nombre) LIKE :q OR LOWER(responsable) LIKE :q)";
        $params[':q'] = '%' . mb_strtolower($q,'UTF-8') . '%';
    }
    if ($estado !== '') { $where[] = 'estado = :estado'; $params[':estado']=$estado; }
    if ($responsableExact !== '') { $where[] = 'responsable = :respExact'; $params[':respExact']=$responsableExact; }
    if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$dateFrom)) { $where[]='fecha >= :df'; $params[':df']=$dateFrom; }
    if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$dateTo)) { $where[]='fecha <= :dt'; $params[':dt']=$dateTo; }
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $allowedSort = ['id','nombre','responsable','estado','fecha'];
    if (!in_array($sort,$allowedSort,true)) { $sort = 'id'; }
    if ($dir !== 'ASC' && $dir !== 'DESC') { $dir = 'ASC'; }
    $sql .= ' ORDER BY ' . $sort . ' ' . $dir;
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k=>$v) $stmt->bindValue($k,$v);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $out = fopen('php://temp','r+');
    fputcsv($out, ['ID','Nombre','Responsable','Estado','Fecha']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['id'],$r['nombre'],$r['responsable'],$r['estado'],$r['fecha']]);
    }
    // Summary total row
    fputcsv($out, ['TOTAL', count($rows), '', '', '']);
    rewind($out);
    $csv = stream_get_contents($out);
    fclose($out);
    header('Content-Type: text/csv; charset=utf-8');
    $suffix = [];
    if ($q) $suffix[]='q';
    if ($estado) $suffix[]='estado';
    if ($dateFrom || $dateTo) $suffix[]='rango';
    if ($responsableExact) $suffix[]='resp';
    if ($sort !== 'id' || $dir !== 'ASC') $suffix[]='ord';
    $suffixStr = $suffix ? '_' . implode('-', $suffix) : '';
    header('Content-Disposition: attachment; filename="inventario_export'.$suffixStr.'.csv"');
    echo "\xEF\xBB\xBF" . $csv; // UTF-8 BOM for Excel compatibility
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
