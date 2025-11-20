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
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
    $estado = isset($_GET['estado']) ? trim((string)$_GET['estado']) : '';
    $dateFrom = isset($_GET['date_from']) ? trim((string)$_GET['date_from']) : '';
    $dateTo   = isset($_GET['date_to']) ? trim((string)$_GET['date_to']) : '';
    $respExact = isset($_GET['responsable_exact']) ? trim((string)$_GET['responsable_exact']) : '';
    $sort = isset($_GET['sort']) ? strtolower((string)$_GET['sort']) : 'id';
    $dir = isset($_GET['dir']) ? strtoupper((string)$_GET['dir']) : 'DESC';
    $allowedSort = ['id','nombre','responsable','estado','fecha'];
    if (!in_array($sort,$allowedSort,true)) $sort='id';
    if (!in_array($dir,['ASC','DESC'],true)) $dir='DESC';

    $sql = 'SELECT id,nombre,responsable,estado,fecha FROM inventario';
    $where = [];
    $params = [];
    if ($q !== '') {
        $castId = ($driver === 'mysql') ? 'CAST(id AS CHAR)' : 'CAST(id AS TEXT)';
        $where[] = "($castId LIKE :q OR LOWER(nombre) LIKE :q OR LOWER(responsable) LIKE :q)";
        $params[':q'] = '%' . mb_strtolower($q,'UTF-8') . '%';
    }
    if ($estado !== '') { $where[]='estado = :estado'; $params[':estado']=$estado; }
    if ($respExact !== '') { $where[]='LOWER(responsable) = :respExact'; $params[':respExact']=mb_strtolower($respExact,'UTF-8'); }
    if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$dateFrom)) { $where[]='fecha >= :df'; $params[':df']=$dateFrom; }
    if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$dateTo)) { $where[]='fecha <= :dt'; $params[':dt']=$dateTo; }
    if ($where) $sql .= ' WHERE ' . implode(' AND ',$where);
    $sql .= " ORDER BY $sort $dir";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k=>$v) $stmt->bindValue($k,$v);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Simple SpreadsheetML (Excel XML) format for compatibility
    $xml = '<?xml version="1.0"?>\n';
    $xml .= '<?mso-application progid="Excel.Sheet"?>\n';
    $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" xmlns:html="http://www.w3.org/TR/REC-html40">';
    $xml .= '<Styles>';
    $xml .= '<Style ss:ID="Default" ss:Name="Normal"><Alignment ss:Vertical="Center"/><Font ss:FontName="Calibri" ss:Size="11"/></Style>';
    $xml .= '<Style ss:ID="hdr"><Font ss:Bold="1"/><Interior ss:Color="#D9E1F2" ss:Pattern="Solid"/></Style>';
    $xml .= '<Style ss:ID="disp"><Interior ss:Color="#E3F7E9" ss:Pattern="Solid"/></Style>';
    $xml .= '<Style ss:ID="uso"><Interior ss:Color="#FFF4D6" ss:Pattern="Solid"/></Style>';
    $xml .= '<Style ss:ID="repa"><Interior ss:Color="#FFE3E3" ss:Pattern="Solid"/></Style>';
    $xml .= '</Styles>';
    $xml .= '<Worksheet ss:Name="Inventario">';
    $xml .= '<Table>';
    // Header
    $xml .= '<Row ss:StyleID="hdr">';
    foreach (['ID','Nombre','Responsable','Estado','Fecha'] as $h){ $xml .= '<Cell><Data ss:Type="String">'.$h.'</Data></Cell>'; }
    $xml .= '</Row>';
    foreach ($rows as $r){
        $style = 'Default';
        switch ($r['estado']) {
            case 'Disponible': $style='disp'; break;
            case 'En uso': $style='uso'; break;
            case 'En reparación': $style='repa'; break;
        }
        $xml .= '<Row ss:StyleID="'.$style.'">';
        $xml .= '<Cell><Data ss:Type="Number">'.(int)$r['id'].'</Data></Cell>';
        $xml .= '<Cell><Data ss:Type="String">'.htmlspecialchars($r['nombre'],ENT_QUOTES|ENT_XML1).'</Data></Cell>';
        $xml .= '<Cell><Data ss:Type="String">'.htmlspecialchars($r['responsable'],ENT_QUOTES|ENT_XML1).'</Data></Cell>';
        $xml .= '<Cell><Data ss:Type="String">'.htmlspecialchars($r['estado'],ENT_QUOTES|ENT_XML1).'</Data></Cell>';
        $xml .= '<Cell><Data ss:Type="String">'.htmlspecialchars($r['fecha'],ENT_QUOTES|ENT_XML1).'</Data></Cell>';
        $xml .= '</Row>';
    }
    // Total summary row style
    $total = count($rows);
    $xml = str_replace('</Styles>','<Style ss:ID="tot"><Font ss:Bold="1"/><Interior ss:Color="#C6EFCE" ss:Pattern="Solid"/></Style></Styles>',$xml);
    $xml .= '<Row ss:StyleID="tot">';
    $xml .= '<Cell><Data ss:Type="String">TOTAL</Data></Cell>';
    $xml .= '<Cell><Data ss:Type="Number">'.$total.'</Data></Cell>';
    $xml .= '<Cell><Data ss:Type="String"></Data></Cell>';
    $xml .= '<Cell><Data ss:Type="String"></Data></Cell>';
    $xml .= '<Cell><Data ss:Type="String"></Data></Cell>';
    $xml .= '</Row>';
    $xml .= '</Table></Worksheet></Workbook>';

    $suffix = [];
    if ($q) $suffix[]='q';
    if ($estado) $suffix[]='estado';
    if ($respExact) $suffix[]='resp';
    if ($dateFrom || $dateTo) $suffix[]='rango';
    if (!($sort==='id' && $dir==='DESC')) $suffix[]='ord';
    $suffixStr = $suffix ? '_'.implode('-', $suffix) : '';

    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="inventario_export'.$suffixStr.'.xls"');
    echo $xml;
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
