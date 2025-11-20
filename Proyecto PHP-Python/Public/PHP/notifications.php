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
    $publicDir = dirname(__DIR__); // .../Public
    $dataDir = $publicDir . DIRECTORY_SEPARATOR . 'data';
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0775, true);
    }
    $file = $dataDir . DIRECTORY_SEPARATOR . 'notifications.json';

    if (!file_exists($file)) {
        $seed = [
            [ 'id' => uniqid('n', true), 'type' => 'success', 'title' => 'Nuevo elemento agregado', 'message' => 'Uniforme (por María Gómez)', 'time' => date('c', strtotime('-1 hour')), 'read' => false ],
            [ 'id' => uniqid('n', true), 'type' => 'info', 'title' => 'Elemento actualizado', 'message' => 'Balón de fútbol (por Juan Pérez)', 'time' => date('c', strtotime('-22 hours')), 'read' => false ],
            [ 'id' => uniqid('n', true), 'type' => 'warning', 'title' => 'Revisión pendiente', 'message' => 'Inventario semanal pendiente', 'time' => date('c', strtotime('-2 days')), 'read' => true ],
        ];
        file_put_contents($file, json_encode($seed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX);
    }

    $raw = file_get_contents($file);
    $list = json_decode($raw, true);
    if (!is_array($list)) $list = [];

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        // Optional filter by type or unread
        $type = isset($_GET['type']) ? strtolower($_GET['type']) : '';
        $unread = isset($_GET['unread']) ? ($_GET['unread'] === '1') : false;
        $out = array_values(array_filter($list, function($n) use ($type, $unread) {
            $ok = true;
            if ($type) $ok = $ok && (strtolower($n['type']) === $type);
            if ($unread) $ok = $ok && (empty($n['read']));
            return $ok;
        }));
        respond(200, [ 'success' => true, 'items' => $out ]);
    }

    if ($method === 'POST') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        if (!is_array($data)) $data = [];
        $action = $data['action'] ?? '';

        if ($action === 'mark_all_read') {
            foreach ($list as &$n) { $n['read'] = true; }
            file_put_contents($file, json_encode($list, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX);
            respond(200, [ 'success' => true ]);
        }

        if ($action === 'clear_all') {
            $list = [];
            file_put_contents($file, json_encode($list, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX);
            respond(200, [ 'success' => true ]);
        }

        if ($action === 'add') {
            $type = isset($data['type']) ? strtolower($data['type']) : 'info';
            if (!in_array($type, ['info','success','warning','error'])) $type = 'info';
            $title = trim($data['title'] ?? 'Notificación');
            $message = trim($data['message'] ?? '');
            $item = [
                'id' => uniqid('n', true),
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'time' => date('c'),
                'read' => false,
            ];
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
