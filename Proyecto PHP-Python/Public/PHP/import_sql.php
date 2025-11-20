<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require __DIR__ . '/db.php';

try {
    $pdo = db();
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $base = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'SQL' . DIRECTORY_SEPARATOR;
    $file = ($driver === 'mysql') ? 'inventario.mysql.sql' : 'inventario.sql';
    $sqlPath = $base . $file;
    if (!is_file($sqlPath)) {
        http_response_code(404);
        echo json_encode(['success'=>false,'error'=>'SQL file not found','path'=>$sqlPath,'driver'=>$driver], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        exit;
    }
    $sql = file_get_contents($sqlPath);
    if ($sql === false || trim($sql) === '') {
        http_response_code(400);
        echo json_encode(['success'=>false,'error'=>'SQL file is empty','path'=>$sqlPath], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        exit;
    }

    $pdo->beginTransaction();
    // Ejecutar statements separados por ; evitando líneas vacías y comentarios simples
    $statements = array_filter(array_map('trim', preg_split('/;\s*\n|;\r?\n|;$/m', $sql)));
    foreach ($statements as $stmt) {
        if ($stmt === '' || strpos(ltrim($stmt), '--') === 0) continue;
        $pdo->exec($stmt);
    }
    $pdo->commit();

    $count = (int)$pdo->query('SELECT COUNT(*) FROM inventario')->fetchColumn();
    echo json_encode(['success'=>true,'rows'=>$count,'driver'=>$driver,'file'=>$file], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}
