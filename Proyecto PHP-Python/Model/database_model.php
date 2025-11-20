<?php
require_once __DIR__ . '/conexion.php';

class DatabaseModel {
    private $pdo;

    public function __construct() {
        $conexion = new Conexion();
        $this->pdo = $conexion->getConexion();
    }

    // Obtener todos los elementos del inventario
    public function obtenerInventario() {
        $sql = "SELECT id, nombre, responsable, estado, fecha FROM inventario";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
?>
