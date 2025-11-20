<?php
declare(strict_types=1);
// Simple automated tests for inventory_export_csv.php and inventory_export_xlsx.php
// Run: php test_exports.php

require __DIR__ . '/db.php';
$pdo = db();

function countExpected(PDO $pdo, array $p): int {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $sql = 'SELECT COUNT(*) FROM inventario';
    $where = [];
    $params = [];
    if (!empty($p['q'])) {
        $castId = ($driver === 'mysql') ? 'CAST(id AS CHAR)' : 'CAST(id AS TEXT)';
        $where[] = "($castId LIKE :q OR LOWER(nombre) LIKE :q OR LOWER(responsable) LIKE :q)";
        $params[':q'] = '%' . mb_strtolower($p['q'],'UTF-8') . '%';
    }
    if (!empty($p['estado'])) { $where[]='estado = :estado'; $params[':estado']=$p['estado']; }
    if (!empty($p['responsable_exact'])) { $where[]='responsable = :respExact'; $params[':respExact']=$p['responsable_exact']; }
    if (!empty($p['date_from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/',$p['date_from'])) { $where[]='fecha >= :df'; $params[':df']=$p['date_from']; }
    if (!empty($p['date_to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/',$p['date_to'])) { $where[]='fecha <= :dt'; $params[':dt']=$p['date_to']; }
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $stmt=$pdo->prepare($sql);
    foreach ($params as $k=>$v) $stmt->bindValue($k,$v);
    $stmt->execute();
    return (int)$stmt->fetchColumn();
}

$tests = [
    ['name'=>'Basic all','params'=>[]],
    ['name'=>'Filter estado Disponible','params'=>['estado'=>'Disponible']],
    ['name'=>'Search q router','params'=>['q'=>'router']],
    ['name'=>'Responsable exact sample','params'=>['responsable_exact'=>'Juan']],
    ['name'=>'Date range (year)','params'=>['date_from'=>date('Y').'-01-01','date_to'=>date('Y').'-12-31']],
    ['name'=>'Combined filters','params'=>['q'=>'a','estado'=>'En uso','responsable_exact'=>'Maria','sort'=>'fecha','dir'=>'DESC']],
];

$summary = [];
foreach ($tests as $t) {
    $p = $t['params'];
    $expected = countExpected($pdo,$p);
    // CSV test
    $_GET = $p;
    ob_start();
    include __DIR__ . '/inventory_export_csv.php';
    $csvOutput = ob_get_clean();
    $csvLines = preg_split('/\r?\n/', trim($csvOutput));
    // Remove BOM for processing
    if (str_starts_with($csvLines[0] ?? '', "\xEF\xBB\xBF")) {
        $csvLines[0] = substr($csvLines[0],3);
    }
    // Identify and exclude TOTAL row (last non-empty line containing 'TOTAL')
    $csvDataLines = [];
    foreach ($csvLines as $line){
        if (trim($line) === '') continue;
        $cols = str_getcsv($line);
        if (isset($cols[0]) && strtoupper($cols[0]) === 'TOTAL') continue; // skip summary
        $csvDataLines[] = $line;
    }
    // First data line is header
    $csvCountData = max(count($csvDataLines)-1,0);
    $csvOk = ($csvCountData === $expected);
    $bomOk = str_starts_with($csvOutput, "\xEF\xBB\xBF");

    // XLSX test (SpreadsheetML)
    $_GET = $p;
    ob_start();
    include __DIR__ . '/inventory_export_xlsx.php';
    $xmlOutput = ob_get_clean();
    $xmlHasWorkbook = strpos($xmlOutput,'<Workbook') !== false;
    $xmlHasRows = substr_count($xmlOutput,'<Row');
    // XML rows include header + data + total summary => should be expected + 2
    $xmlOk = $xmlHasWorkbook && $xmlHasRows >= ($expected + 2);

    $summary[] = [
        'test'=>$t['name'],
        'expected'=>$expected,
        'csv_rows'=>$csvCountData,
        'csv_ok'=>$csvOk,
        'csv_bom'=>$bomOk,
        'xml_rows'=>$xmlHasRows - 2,
        'xml_ok'=>$xmlOk
    ];
}

echo "Export Tests Summary\n";
echo str_repeat('=',60)."\n";
foreach ($summary as $s) {
    echo sprintf(
        "%s | expected:%d CSV:%d %s BOM:%s XLS:%d %s\n",
        str_pad($s['test'],25),
        $s['expected'],
        $s['csv_rows'],
        $s['csv_ok']?'OK':'FAIL',
        $s['csv_bom']?'OK':'NO',
        $s['xml_rows'],
        $s['xml_ok']?'OK':'FAIL'
    );
}

// Simple exit status: fail if any not OK
$fail = false;
foreach ($summary as $s){ if (!$s['csv_ok'] || !$s['xml_ok']) { $fail=true; break; } }
if ($fail) { fwrite(STDERR, "One or more tests failed.\n"); exit(1);} else { echo "All tests passed.\n"; }
