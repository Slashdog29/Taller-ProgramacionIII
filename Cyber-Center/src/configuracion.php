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

$updateMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_config') {
    $nombre = trim($_POST['nombre'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $configId = intval($_POST['id'] ?? 0);

    if ($configId > 0) {
        $stmt = $conexion->prepare("UPDATE configuracion SET nombre = ?, telefono = ?, email = ?, direccion = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('ssssi', $nombre, $telefono, $email, $direccion, $configId);
            if ($stmt->execute()) {
                $updateMessage = '<div class="alert alert-success" role="alert">Configuración actualizada correctamente.</div>';
                $config['nombre'] = $nombre;
                $config['telefono'] = $telefono;
                $config['email'] = $email;
                $config['direccion'] = $direccion;
            } else {
                $updateMessage = '<div class="alert alert-danger" role="alert">Error al actualizar la configuración. Intenta de nuevo.</div>';
            }
            $stmt->close();
        } else {
            $updateMessage = '<div class="alert alert-danger" role="alert">No se pudo preparar la consulta de actualización.</div>';
        }
    } else {
        $updateMessage = '<div class="alert alert-warning" role="alert">No se encontró la configuración para actualizar.</div>';
    }
}
?>

<style>
    .config-table {
        color: var(--text-light);
        margin-bottom: 0;
        width: 100%;
        background: transparent;
        border-collapse: separate;
        border-spacing: 0;
    }
    .config-table thead th {
        background: rgba(255, 255, 255, 0.08);
        border-bottom: 1px solid rgba(255, 255, 255, 0.12);
        color: rgba(255, 255, 255, 0.8);
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        padding: 1rem 1rem;
        font-weight: 700;
    }
    .config-table tbody td {
        vertical-align: middle;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        padding: 1rem 1rem;
        background: rgba(255, 255, 255, 0.04);
        color: #f8fafc;
    }
    .config-table tbody tr {
        transition: background 0.25s ease, transform 0.25s ease;
    }
    .config-table tbody tr:hover {
        background: rgba(255, 255, 255, 0.08);
    }
    .glass-card.full-table {
        padding: 0 !important;
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, 0.14);
        border-radius: 24px;
        background: rgba(15, 23, 42, 0.9);
    }
    .table-responsive {
        border-radius: 24px;
    }
    .config-table td code,
    .config-table td .badge,
    .config-table td .text-info {
        color: #5eead4 !important;
    }
    .config-table .text-white-50 {
        color: rgba(255, 255, 255, 0.78) !important;
    }
    .config-table .btn {
        min-width: 42px;
    }
    /* Mejora de controles en modales */
    .glass-modal .form-control,
    .glass-modal .form-select {
        background-color: rgba(15, 23, 42, 0.8) !important;
        border: 1px solid rgba(255, 255, 255, 0.15) !important;
        color: #fff !important;
    }
    .glass-modal .form-control:focus {
        background-color: rgba(15, 23, 42, 0.9) !important;
        border-color: var(--primary-light) !important;
        box-shadow: 0 0 0 0.25rem rgba(129, 140, 248, 0.25);
    }
</style>

<div class="container px-0">
    <div class="glass-card p-3 p-md-4 mb-4 d-flex flex-wrap justify-content-between align-items-center">
        <div>
            <h1 class="h3 mb-1"><i class="fas fa-cog me-2"></i> Configuración</h1>
            <p class="mb-0 text-white-50">Datos generales de la empresa y contacto almacenados en la base de datos.</p>
        </div>
    </div>

    <div class="glass-card full-table">
        <div class="p-4 pb-0">
        <?php if ($updateMessage): ?>
            <?php echo $updateMessage; ?>
        <?php endif; ?>
        </div>

        <?php if ($config): ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0 config-table">
                    <thead>
                        <tr>
                            <th>NOMBRE</th>
                            <th>TELÉFONO</th>
                            <th>CORREO</th>
                            <th>DIRECCIÓN</th>
                            <th class="text-end">ACCIONES</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <div class="fw-bold text-white"><?php echo htmlspecialchars($config['nombre'] ?: '-'); ?></div>
                                <small class="text-white-50">Información de la empresa</small>
                            </td>
                            <td><code style="color: #00f2ff; font-weight: 700;"><?php echo htmlspecialchars($config['telefono'] ?: '-'); ?></code></td>
                            <td><span class="badge bg-dark border border-secondary text-info"><?php echo htmlspecialchars($config['email'] ?: '-'); ?></span></td>
                            <td><span class="text-white-50"><?php echo nl2br(htmlspecialchars($config['direccion'] ?: '-')); ?></span></td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-light" data-bs-toggle="modal" data-bs-target="#editConfigModal" title="Editar configuración">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-warning" role="alert">
                No se encontró ninguna configuración. Asegúrate de que la tabla <code>configuracion</code> tenga datos.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($config): ?>
<div class="modal fade" id="editConfigModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-modal">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Editar configuración</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <form method="post">
                    <input type="hidden" name="action" value="update_config">
                    <input type="hidden" name="id" value="<?php echo intval($config['id']); ?>">
                    <div class="mb-3">
                        <label class="form-label">Nombre</label>
                        <input type="text" name="nombre" class="form-control" value="<?php echo htmlspecialchars($config['nombre']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Teléfono</label>
                        <input type="text" name="telefono" class="form-control" value="<?php echo htmlspecialchars($config['telefono']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Correo electrónico</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($config['email']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Dirección</label>
                        <textarea name="direccion" class="form-control" rows="3" required><?php echo htmlspecialchars($config['direccion']); ?></textarea>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Guardar cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include_once "includes/footer.php"; ?>
