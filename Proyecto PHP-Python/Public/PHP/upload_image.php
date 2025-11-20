<?php
// Proceso para guardar la imagen en la carpeta uploads y devolver la ruta.
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }
try {
    $input = file_get_contents('php://input');
    if (!$input) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Sin cuerpo de petición']);
        exit;
    }
    $data = json_decode($input, true);
    if (!is_array($data) || empty($data['image'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Campo "image" requerido']);
        exit;
    }
    $image = $data['image'];
    if (!preg_match('/^data:image\/(png|jpeg);base64,/', $image, $m)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Formato de imagen no soportado']);
        exit;
    }
    $ext = $m[1] === 'jpeg' ? 'jpg' : 'png';
    $base64 = substr($image, strpos($image, ',') + 1);
    $binary = base64_decode($base64, true);
    if ($binary === false) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Imagen base64 inválida']);
        exit;
    }
    // Falta crear la conexión hacía la base de datos.

    $publicDir = dirname(__DIR__); // Carpeta Public
    $uploadsDir = $publicDir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
    if (!is_dir($uploadsDir)) {
        if (!mkdir($uploadsDir, 0775, true)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'No se pudo crear uploads']);
            exit;
        }
    }

    $filename = 'captura_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $filePath = $uploadsDir . $filename;
    if (file_put_contents($filePath, $binary) === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'No se pudo guardar la imagen']);
        exit;
    }

    // Ruta pública relativa del proyecto base.
    $publicPath = 'Public/uploads/' . $filename;

    echo json_encode([
        'success' => true,
        'filename' => $filename,
        'path' => $publicPath,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
