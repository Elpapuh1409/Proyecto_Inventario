<?php
// Reconoce placas con Plate Recognizer usando una imagen recibida (base64 o path)
// Config: establecer token en variable de entorno PLATEREC_TOKEN
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }

function respond($code, $data) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

try {
    $token = getenv('PLATEREC_TOKEN');
    if (!$token) {
        respond(500, ['success' => false, 'error' => 'Falta token: defina PLATEREC_TOKEN en el entorno']);
    }

    $raw = file_get_contents('php://input');
    if (!$raw) {
        respond(400, ['success' => false, 'error' => 'Sin cuerpo de petición']);
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        respond(400, ['success' => false, 'error' => 'JSON inválido']);
    }

    $region = isset($data['region']) ? preg_replace('/[^a-z]/i', '', strtolower($data['region'])) : '';
    $tmpPath = null;
    $mime = 'image/png';

    if (!empty($data['image'])) {
        $image = $data['image'];
        if (!preg_match('/^data:image\/(png|jpeg);base64,/', $image, $m)) {
            respond(400, ['success' => false, 'error' => 'Formato de imagen no soportado']);
        }
        $ext = $m[1] === 'jpeg' ? 'jpg' : 'png';
        $mime = $m[1] === 'jpeg' ? 'image/jpeg' : 'image/png';
        $base64 = substr($image, strpos($image, ',') + 1);
        $binary = base64_decode($base64, true);
        if ($binary === false) {
            respond(400, ['success' => false, 'error' => 'Imagen base64 inválida']);
        }
        $tmpPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lpr_' . uniqid('', true) . '.' . $ext;
        if (file_put_contents($tmpPath, $binary) === false) {
            respond(500, ['success' => false, 'error' => 'No se pudo escribir archivo temporal']);
        }
    } elseif (!empty($data['image_path'])) {
        $rel = $data['image_path'];
        // solo permitir rutas dentro de Public/uploads
        $publicDir = dirname(__DIR__);
        $uploadsDir = $publicDir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
        $full = realpath($uploadsDir . basename($rel));
        if (!$full || strpos($full, realpath($uploadsDir)) !== 0 || !is_file($full)) {
            respond(400, ['success' => false, 'error' => 'Ruta de imagen inválida']);
        }
        $tmpPath = $full;
        $ext = strtolower(pathinfo($tmpPath, PATHINFO_EXTENSION));
        $mime = $ext === 'jpg' || $ext === 'jpeg' ? 'image/jpeg' : 'image/png';
    } else {
        respond(400, ['success' => false, 'error' => 'Falta image o image_path']);
    }

    $ch = curl_init('https://api.platerecognizer.com/v1/plate-reader/');
    if ($ch === false) {
        if ($tmpPath && strpos($tmpPath, sys_get_temp_dir()) === 0 && is_file($tmpPath)) @unlink($tmpPath);
        respond(500, ['success' => false, 'error' => 'No se pudo iniciar cURL']);
    }

    $cfile = new CURLFile($tmpPath, $mime, basename($tmpPath));
    $post = ['upload' => $cfile];
    if ($region) { $post['regions'] = $region; }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Token ' . $token
        ],
        CURLOPT_POSTFIELDS => $post,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
    ]);

    $resp = curl_exec($ch);
    $errno = curl_errno($ch);
    $err = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($tmpPath && strpos($tmpPath, sys_get_temp_dir()) === 0 && is_file($tmpPath)) @unlink($tmpPath);

    if ($errno) {
        respond(502, ['success' => false, 'error' => 'cURL error: ' . $err]);
    }

    $json = json_decode($resp, true);
    if ($status < 200 || $status >= 300 || !$json) {
        respond($status ?: 500, ['success' => false, 'error' => 'API LPR error', 'raw' => $resp]);
    }

    // Normalizar respuesta mínima
    $plate = null; $score = null;
    if (isset($json['results'][0]['plate'])) {
        $plate = strtoupper($json['results'][0]['plate']);
        $score = isset($json['results'][0]['score']) ? (float)$json['results'][0]['score'] : null;
    }

    respond(200, [
        'success' => true,
        'provider_status' => $status,
        'plate' => $plate,
        'score' => $score,
        'provider' => 'PlateRecognizer',
        'raw' => $json,
    ]);
} catch (Throwable $e) {
    respond(500, ['success' => false, 'error' => $e->getMessage()]);
}
