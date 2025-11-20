<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }

function respond($code, $data) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $publicDir = dirname(__DIR__);
    $dataDir = $publicDir . DIRECTORY_SEPARATOR . 'data';
    if (!is_dir($dataDir)) { mkdir($dataDir, 0775, true); }
    $file = $dataDir . DIRECTORY_SEPARATOR . 'inventory.json';

    if (!file_exists($file)) {
        $seed = [
            [ 'id' => 1, 'nombre' => 'Balón de fútbol', 'responsable' => 'Juan Pérez', 'estado' => 'Disponible', 'fecha' => '2025-11-01' ],
            [ 'id' => 2, 'nombre' => 'Uniforme', 'responsable' => 'María Gómez', 'estado' => 'En uso', 'fecha' => '2025-11-10' ],
            [ 'id' => 3, 'nombre' => 'Botines', 'responsable' => 'Carlos Ruiz', 'estado' => 'En reparación', 'fecha' => '2025-10-28' ],
        ];
        file_put_contents($file, json_encode($seed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX);
    }

    $raw = file_get_contents($file);
    $list = json_decode($raw, true);
    if (!is_array($list)) $list = [];

    // Asegurar al menos 20 elementos con datos de ejemplo
    $currentCount = count($list);
    if ($currentCount < 20) {
        $names = [
            'Balón de fútbol','Uniforme','Botines','Conos','Chalecos','Cronómetro','Bebidas','Guantes',
            'Pechera','Aros','Colchoneta','Balón de baloncesto','Red portátil','Bomba de aire','Cinta métrica',
            'Cámara','Trípode','Tableta','Silbato','Botiquín','Balón de voleibol','Red de voleibol','Cinta kinesio'
        ];
        $responsables = ['Juan Pérez','María Gómez','Carlos Ruiz','Ana Torres','Luis Fernández','Sofía López','Diego Castro','Laura Méndez'];
        $estados = ['Disponible','En uso','En reparación'];
        $maxId = 0; foreach ($list as $r) { if ((int)$r['id'] > $maxId) $maxId = (int)$r['id']; }
        $need = 20 - $currentCount;
        for ($i = 0; $i < $need; $i++) {
            $id = $maxId + 1 + $i;
            $name = $names[($i + $currentCount) % count($names)];
            $resp = $responsables[($i + $currentCount) % count($responsables)];
            $estado = $estados[($i + $currentCount) % count($estados)];
            $daysAgo = rand(0, 60);
            $fecha = date('Y-m-d', strtotime("-{$daysAgo} days"));
            $list[] = [ 'id' => $id, 'nombre' => $name, 'responsable' => $resp, 'estado' => $estado, 'fecha' => $fecha ];
        }
        file_put_contents($file, json_encode($list, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX);
    }

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        $q = isset($_GET['q']) ? trim($_GET['q']) : '';
        $estado = isset($_GET['estado']) ? trim($_GET['estado']) : '';
        $out = array_values(array_filter($list, function($row) use ($q, $estado) {
            $ok = true;
            if ($q !== '') {
                $s = mb_strtolower($q);
                $ok = $ok && (
                    mb_strpos(mb_strtolower($row['nombre']), $s) !== false ||
                    mb_strpos(mb_strtolower($row['responsable']), $s) !== false ||
                    mb_strpos((string)$row['id'], $s) !== false
                );
            }
            if ($estado !== '') {
                $ok = $ok && (mb_strtolower($row['estado']) === mb_strtolower($estado));
            }
            return $ok;
        }));
        respond(200, [ 'success' => true, 'items' => $out ]);
    }

    if ($method === 'POST') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        if (!is_array($data)) $data = [];
        $action = $data['action'] ?? '';

        if ($action === 'add') {
            $nombre = trim($data['nombre'] ?? '');
            $responsable = trim($data['responsable'] ?? '');
            $estado = trim($data['estado'] ?? '');
            $fecha = trim($data['fecha'] ?? date('Y-m-d'));
            if ($nombre === '' || $responsable === '' || $estado === '') {
                respond(400, [ 'success' => false, 'error' => 'Campos requeridos: nombre, responsable, estado' ]);
            }
            $maxId = 0; foreach ($list as $r) { if ((int)$r['id'] > $maxId) $maxId = (int)$r['id']; }
            $item = [ 'id' => $maxId + 1, 'nombre' => $nombre, 'responsable' => $responsable, 'estado' => $estado, 'fecha' => $fecha ];
            $list[] = $item;
            file_put_contents($file, json_encode($list, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX);
            respond(200, [ 'success' => true, 'item' => $item ]);
        }

        respond(400, [ 'success' => false, 'error' => 'Acción no soportada' ]);
    }

    respond(405, [ 'success' => false, 'error' => 'Método no permitido' ]);
} catch (Throwable $e) {
    respond(500, [ 'success' => false, 'error' => $e->getMessage() ]);
}
