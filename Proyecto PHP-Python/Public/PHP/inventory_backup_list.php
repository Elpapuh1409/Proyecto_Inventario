<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405); echo json_encode(['success'=>false,'error'=>'Método no permitido']); exit; }
$dir = __DIR__ . '/../backups';
if (!is_dir($dir)) { echo json_encode(['success'=>true,'backups'=>[]]); exit; }
$files = glob($dir . '/inventory_backup_*.json');
usort($files, function($a,$b){ return filemtime($b) <=> filemtime($a); });
$list = [];
foreach ($files as $f) {
    $list[] = [
        'file' => basename($f),
        'size' => filesize($f),
        'modified' => date('c', filemtime($f))
    ];
}
echo json_encode(['success'=>true,'backups'=>$list], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
