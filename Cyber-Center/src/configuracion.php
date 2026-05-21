<?php
include_once "includes/header.php";
require_once __DIR__ . '/../conexion.php';

date_default_timezone_set('America/Caracas');
$conexion->query("SET time_zone = '-04:00'");

$config = null;
$result = $conexion->query("SELECT id, nombre, telefono, email, direccion FROM configuracion ORDER BY id LIMIT 1");
if ($result) {
    $config = $result->fetch_assoc();
}
?>

<div class="container px-0">
    <div class="glass-card p-3 p-md-4 mb-4 d-flex flex-wrap justify-content-between align-items-center">
        <div>
            <h1 class="h3 mb-1"><i class="fas fa-cog me-2"></i> Configuración</h1>
            <p class="mb-0 text-white-50">Datos generales de la empresa y contacto almacenados en la base de datos.</p>
        </div>
        <div class="mt-2 mt-sm-0">
            <a href="index.php" class="btn btn-outline-light btn-sm">Volver al dashboard</a>
        </div>
    </div>

    <div class="glass-card p-4">
        <?php if ($config): ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <h5 class="text-white mb-3">Información general</h5>
                    <table class="table table-dark table-striped">
                        <tbody>
                            <tr>
                                <th>Nombre</th>
                                <td><?php echo htmlspecialchars($config['nombre']); ?></td>
                            </tr>
                            <tr>
                                <th>Teléfono</th>
                                <td><?php echo htmlspecialchars($config['telefono']); ?></td>
                            </tr>
                            <tr>
                                <th>Correo electrónico</th>
                                <td><?php echo htmlspecialchars($config['email']); ?></td>
                            </tr>
                            <tr>
                                <th>Dirección</th>
                                <td><?php echo nl2br(htmlspecialchars($config['direccion'])); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-warning" role="alert">
                No se encontró ninguna configuración. Asegúrate de que la tabla <code>configuracion</code> tenga datos.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include_once "includes/footer.php"; ?>
