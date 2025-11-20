<?php
declare(strict_types=1);
require __DIR__ . '/../../Public/PHP/db.php';

$pdo = db();
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$estado = isset($_GET['estado']) ? trim((string)$_GET['estado']) : '';

$sql = 'SELECT id, nombre, responsable, estado, fecha FROM inventario';
$where = [];
$params = [];
if ($q !== '') {
    $castId = ($driver === 'mysql') ? 'CAST(id AS CHAR)' : 'CAST(id AS TEXT)';
    $where[] = "($castId LIKE :q OR LOWER(nombre) LIKE :q OR LOWER(responsable) LIKE :q)";
    $params[':q'] = '%' . mb_strtolower($q, 'UTF-8') . '%';
}
if ($estado !== '') {
    $where[] = 'estado = :estado';
    $params[':estado'] = $estado;
}
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY id DESC';

$stmt = $pdo->prepare($sql);
foreach ($params as $k=>$v) $stmt->bindValue($k, $v);
$stmt->execute();
$items = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Base de Datos (DB) | Inventario Win Sports</title>
    <link rel="stylesheet" href="../../CSS/styles.css">
    <style>
        .db-toolbar { display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-top:10px; }
        .table-wrap { margin:24px 0; overflow-x:auto; }
    </style>
    </head>
<body>
    <header class="site-header">
        <div class="container header-inner">
            <h1 class="brand">Win Sports <span class="accent">Inventario</span></h1>
            <nav class ="nav">
                <a href="../index.html">Inicio</a>
                <a href="../Identificadores/Lectores.html">Lectores</a>
                <a href="../Guia_Rapida.html">Guía Rápida</a>
                <a href="../Notificaciones.html">Notificaciones</a>
            </nav>
        </div>
    </header>
    <main class="site-main">
        <section class="database-section" style="margin-top: 40px;">
            <div class="section-header">
                <h2>Vista de Base de Datos (Servidor)</h2>
                <form class="db-toolbar" method="get">
                    <input name="q" class="filter-input" type="text" placeholder="Buscar por ID, nombre o responsable" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" />
                    <select name="estado" class="filter-input" style="min-width:180px;">
                        <option value="">Todos los estados</option>
                        <?php
                        $estados=['Disponible','En uso','En reparación'];
                        foreach($estados as $e){
                            $sel = $estado===$e ? ' selected' : '';
                            echo "<option$sel>".htmlspecialchars($e,ENT_QUOTES,'UTF-8')."</option>";
                        }
                        ?>
                    </select>
                    <button class="btn" type="submit">Filtrar</button>
                    <a class="btn secondary" href="?">Limpiar</a>
                </form>
            </div>
            <div class="table-wrap">
                <table class="inventory-table">
                    <thead>
                        <tr>
                            <th>ID</th><th>Nombre</th><th>Responsable</th><th>Estado</th><th>Fecha de Ingreso</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($items as $r): ?>
                        <tr>
                            <td><?= (int)$r['id'] ?></td>
                            <td><?= htmlspecialchars($r['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($r['responsable'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($r['estado'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($r['fecha'], ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (!$items): ?>
                        <tr><td colspan="5" style="text-align:center;opacity:.7;">Sin resultados</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>
