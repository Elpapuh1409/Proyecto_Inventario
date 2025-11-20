<?php
// Configuración por defecto: SQLite local
return [
    'DB_DRIVER'  => 'sqlite',
    'DB_PATH'    => __DIR__ . '/../data/app.sqlite',
    // Valores MySQL opcionales si decides cambiar a MySQL
    'DB_HOST'    => '127.0.0.1',
    'DB_PORT'    => '3306',
    'DB_NAME'    => 'winsports',
    'DB_USER'    => 'root',
    'DB_PASS'    => '',
    'DB_CHARSET' => 'utf8mb4',
];
