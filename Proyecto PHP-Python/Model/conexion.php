<?php
// Conexión hacia la base de datos con PDO
class Conexion {
    public $pdo;

    public function __construct() {
        try {
            // Usar el mismo archivo SQLite que el resto de la app
            $dbPath = __DIR__ . '/../Public/data/app.sqlite';
            $dir = dirname($dbPath);
            if (!is_dir($dir)) {@mkdir($dir, 0775, true);}    
            $this->pdo = new PDO('sqlite:' . $dbPath);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            // Asegurar claves foráneas activas (si aplica)
            $this->pdo->exec('PRAGMA foreign_keys = ON;');
        } catch (PDOException $e) {
            exit('Error de conexión: ' . $e->getMessage());
        }
    }

    public function getConexion() {
        return $this->pdo;
    }
}
?>
