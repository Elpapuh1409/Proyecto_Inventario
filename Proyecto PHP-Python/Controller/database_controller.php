<?php
// Ruta HTML (renderizado del lado del servidor)
if (isset($_GET['view']) && $_GET['view'] === 'html') {
	require_once __DIR__ . '/../Views/Bases_de_Datos/Base_de_Datos_DB.php';
	exit;
}

// API JSON (MVC)
require_once __DIR__ . '/../Model/database_model.php';
header('Content-Type: application/json; charset=utf-8');

try {
	$model = new DatabaseModel();
	$datos = $model->obtenerInventario();
	echo json_encode(['success'=>true, 'items'=>$datos], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
	http_response_code(500);
	echo json_encode(['success'=>false, 'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
?>
