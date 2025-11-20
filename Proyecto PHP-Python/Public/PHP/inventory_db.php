<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require __DIR__ . '/db.php';

try {
    $pdo = db();
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
    $estado = isset($_GET['estado']) ? trim((string)$_GET['estado']) : '';
    $responsableExact = isset($_GET['responsable_exact']) ? trim((string)$_GET['responsable_exact']) : '';
    $dateFrom = isset($_GET['date_from']) ? trim((string)$_GET['date_from']) : '';
    $dateTo   = isset($_GET['date_to']) ? trim((string)$_GET['date_to']) : '';

    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $perPage = isset($_GET['per_page']) ? max(1, min(100, (int)$_GET['per_page'])) : 20;
    $offset = ($page - 1) * $perPage;

    $sqlBase = 'FROM inventario';
    $sqlSelect = 'SELECT id, nombre, responsable, estado, fecha ';
    $allowedSort = ['id','nombre','responsable','estado','fecha'];
    $sort = isset($_GET['sort']) ? strtolower((string)$_GET['sort']) : 'id';
    if (!in_array($sort, $allowedSort, true)) $sort = 'id';
    $dir = isset($_GET['dir']) ? strtoupper((string)$_GET['dir']) : 'DESC';
    if (!in_array($dir, ['ASC','DESC'], true)) $dir = 'DESC';
    $where = [];
    $params = [];

    if ($q !== '') {
        $castId = ($driver === 'mysql') ? 'CAST(id AS CHAR)' : 'CAST(id AS TEXT)';
        $where[] = "($castId LIKE :q OR LOWER(nombre) LIKE :q OR LOWER(responsable) LIKE :q)";
        $params[':q'] = '%' . mb_strtolower($q, 'UTF-8') . '%';
    }
    if ($estado !== '') {
        $where[] = 'estado = :estado';
        $params[':estado'] = $estado;
    }
    if ($responsableExact !== '') {
        $where[] = 'LOWER(responsable) = :respExact';
        $params[':respExact'] = mb_strtolower($responsableExact, 'UTF-8');
    }
    if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
        $where[] = 'fecha >= :dateFrom';
        $params[':dateFrom'] = $dateFrom;
    }
    if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
        $where[] = 'fecha <= :dateTo';
        $params[':dateTo'] = $dateTo;
    }
    $whereSql = '';
    if ($where) $whereSql = ' WHERE ' . implode(' AND ', $where);
    $sqlCount = 'SELECT COUNT(*) ' . $sqlBase . $whereSql;
    $sql = $sqlSelect . $sqlBase . $whereSql . " ORDER BY $sort $dir LIMIT :limit OFFSET :offset";

    $countStmt = $pdo->prepare($sqlCount);
    foreach ($params as $k=>$v) $countStmt->bindValue($k, $v);
    $countStmt->execute();
    $total = (int)$countStmt->fetchColumn();

    $stmt = $pdo->prepare($sql);
    foreach ($params as $k=>$v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll();

    // Counts por estado (global filtrado, no paginado)
    $counts = ['Disponible'=>0,'En uso'=>0,'En reparación'=>0];
    $countEstadoSql = 'SELECT estado, COUNT(*) c ' . $sqlBase . $whereSql . ' GROUP BY estado';
    $estStmt = $pdo->prepare($countEstadoSql);
    foreach ($params as $k=>$v) $estStmt->bindValue($k,$v);
    $estStmt->execute();
    foreach ($estStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $counts[$row['estado']] = (int)$row['c'];
    }
    echo json_encode([
        'success'=>true,
        'items'=>$items,
        'page'=>$page,
        'per_page'=>$perPage,
        'total'=>$total,
        'pages'=>($perPage>0? (int)ceil($total/$perPage):1),
        'sort'=>$sort,
        'dir'=>$dir,
        'counts'=>$counts
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false, 'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
