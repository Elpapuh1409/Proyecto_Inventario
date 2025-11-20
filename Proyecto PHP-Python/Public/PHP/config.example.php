<?php
// Copia este archivo como config.php y ajusta las credenciales
return [
    // MySQL por defecto
    'DB_DRIVER'  => 'mysql',      // mysql | sqlite
    'DB_HOST'    => '127.0.0.1',
    'DB_PORT'    => '3306',
    'DB_NAME'    => 'winsports',
    'DB_USER'    => 'root',
    'DB_PASS'    => '',
    'DB_CHARSET' => 'utf8mb4',

    // Si usas SQLite, cambia DB_DRIVER a 'sqlite' y define DB_PATH
    // 'DB_DRIVER' => 'sqlite',
    // 'DB_PATH'   => __DIR__ . '/../data/app.sqlite',
];
