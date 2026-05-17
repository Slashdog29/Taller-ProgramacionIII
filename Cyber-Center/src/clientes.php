<?php
include_once "includes/header.php";
require_once __DIR__ . "/../conexion.php";

global $conexion;

// Verificación de seguridad para la conexión
if (!$conexion) {
    die("<div class='alert alert-danger'>Error crítico: La conexión a la base de datos no está disponible.</div>");
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Token CSRF inválido o sesión expirada. Por favor, recarga la página.']);
        exit;
    }

    $action = $_POST['action'] ?? '';
    $usuario_sesion = $_SESSION['nombre'] ?? 'Sistema';
    $ip = $_SERVER['REMOTE_ADDR'];
    $fecha_hora = date('Y-m-d H:i:s');
    $sector = 'Clientes';

    if ($action === 'create') {
        $nombre_cliente = trim($_POST['nombre'] ?? '');
        $apellido = trim($_POST['apellido'] ?? '');
        $cedula = trim($_POST['cedula_o_codigo'] ?? '');
        $correo = trim($_POST['correo'] ?? '');
        $tipo_cliente_id = intval($_POST['tipo_cliente_id'] ?? 0);
        $estado_cuenta = trim($_POST['estado_cuenta'] ?? 'activo');

        if (empty($nombre_cliente) || empty($apellido) || empty($cedula) || empty($correo) || $tipo_cliente_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Todos los campos son obligatorios.']);
            exit;
        }

        $stmt = $conexion->prepare("INSERT INTO clientes (nombre, apellido, cedula_o_codigo, correo, tipo_cliente_id, estado_cuenta, creado_en) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param('ssssiss', $nombre_cliente, $apellido, $cedula, $correo, $tipo_cliente_id, $estado_cuenta, $fecha_hora);
            if ($stmt->execute()) {
                $accion_historial = "Registró al cliente: " . $nombre_cliente . ' ' . $apellido;
                $stmt_h = $conexion->prepare("INSERT INTO historial (usuario, ip, fyh, sector, acciones) VALUES (?, ?, ?, ?, ?)");
                if ($stmt_h) {
                    $stmt_h->bind_param('sssss', $usuario_sesion, $ip, $fecha_hora, $sector, $accion_historial);
                    $stmt_h->execute();
                    $stmt_h->close();
                }
                echo json_encode(['success' => true, 'message' => 'Cliente creado exitosamente.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error de Base de Datos: ' . $stmt->error]);
            }
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al preparar la consulta SQL: ' . $conexion->error]);
        }
        exit;
    }
}

// Registro de actividad en el historial
$nombre = $_SESSION['nombre'] ?? 'Usuario';
$ip = $_SERVER['REMOTE_ADDR'];
$fecha_hora = date('Y-m-d H:i:s');
$sector = "clientes";
$accion = "Acceso a la sección de gestión de clientes";
$stmt = $conexion->prepare("INSERT INTO historial (usuario, ip, fyh, sector, acciones) VALUES (?, ?, ?, ?, ?)");
if ($stmt) {
    $stmt->bind_param("sssss", $nombre, $ip, $fecha_hora, $sector, $accion);
    $stmt->execute();
    $stmt->close();
}

// Consulta para obtener los clientes con su tipo de rol
$query = "SELECT c.*, t.nombre_rol, t.tarifa_por_hora 
          FROM clientes c 
          INNER JOIN tipos_cliente t ON c.tipo_cliente_id = t.id 
          ORDER BY c.id ASC";
$resultado = mysqli_query($conexion, $query);

$typeQuery = "SELECT id, nombre_rol FROM tipos_cliente ORDER BY nombre_rol ASC";
$typeResult = mysqli_query($conexion, $typeQuery);

// Validación de errores en la consulta
if (!$resultado || !$typeResult) {
    $errorMessage = mysqli_error($conexion);
    die("<div class='alert alert-danger'>Error en la consulta SQL: " . $errorMessage . "</div>");
}
?>

<style>
    .table {
        color: var(--text-light);
        margin-bottom: 0;
        border-collapse: separate;
        border-spacing: 0;
    }

    .table thead th {
        background: rgba(0, 0, 0, 0.4);
        border-bottom: 1px solid var(--glass-border);
        color: var(--primary-light);
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 1.2px;
        padding: 1.25rem 1rem;
        font-weight: 800;
    }

    .table td {
        vertical-align: middle;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        padding: 1.25rem 1rem;
        background: transparent;
    }

    .table tbody tr {
        transition: all 0.3s ease;
    }

    .table tbody tr:hover {
        background: rgba(0, 0, 0, 0.3) !important;
    }

    .status-badge {
        border-radius: 20px;
        padding: 0.45rem 0.85rem;
        font-size: 0.65rem;
        text-transform: uppercase;
        font-weight: 800;
        letter-spacing: 0.5px;
    }

    .glass-card {
        padding: 0 !important;
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 24px;
    }

    .table-responsive {
        border-radius: 24px;
    }

    /* Máximo contraste para legibilidad en fondo oscuro */
    .table tbody td {
        color: #ffffff !important;
    }

    .text-white-50 {
        color: rgba(255, 255, 255, 0.85) !important;
    }

    .text-muted {
        color: rgba(255, 255, 255, 0.75) !important;
    }

    /* Brillo para elementos específicos */
    .bg-dark.border-secondary {
        background-color: rgba(45, 55, 72, 0.9) !important;
        border-color: rgba(255, 255, 255, 0.3) !important;
    }
</style>

<div class="container main-content pb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0 text-white">Gestión de Clientes</h2>
            <p class="text-white-50">Administración de usuarios y cuentas</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addClientModal">
            <i class="fas fa-user-plus me-2"></i>Nuevo Cliente
        </button>
    </div>

    <div class="glass-card">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>CLIENTE</th>
                        <th>IDENTIFICACIÓN</th>
                        <th>TIPO</th>
                        <th>CORREO</th>
                        <th>ESTADO</th>
                        <th class="text-end">ACCIONES</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($resultado) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($resultado)):
                            // Compatibilidad con versiones de PHP anteriores a 8.0
                            switch ($row['estado_cuenta']) {
                                case 'activo':
                                    $badge_class = 'bg-success';
                                    break;
                                case 'suspendido':
                                    $badge_class = 'bg-danger';
                                    break;
                                default:
                                    $badge_class = 'bg-secondary';
                                    break;
                            }
                        ?>
                            <tr>
                                <td class="fw-bold" style="color: var(--primary-light);">#<?php echo $row['id']; ?></td>
                                <td>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($row['nombre'] . ' ' . $row['apellido']); ?></div>
                                    <small class="text-white-50">Registrado: <?php echo date('d/m/Y', strtotime($row['creado_en'])); ?></small>
                                </td>
                                <td><code style="color: #00f2ff; font-size: 0.9rem; font-weight: 700;"><?php echo htmlspecialchars($row['cedula_o_codigo']); ?></code></td>
                                <td>
                                    <span class="badge bg-dark border border-secondary text-info"><?php echo htmlspecialchars($row['nombre_rol']); ?></span>
                                    <div class="small text-muted mt-1">$<?php echo number_format($row['tarifa_por_hora'], 2); ?>/h</div>
                                </td>
                                <td><?php echo $row['correo'] ? htmlspecialchars($row['correo']) : '<i class="text-white-50">N/A</i>'; ?></td>
                                <td>
                                    <span class="badge status-badge <?php echo $badge_class; ?>">
                                        <?php echo htmlspecialchars($row['estado_cuenta']); ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-outline-light rounded-pill me-2"><i class="fas fa-edit"></i></button>
                                        <button class="btn btn-sm btn-outline-info rounded-pill"><i class="fas fa-eye"></i></button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-white-50">
                                <i class="fas fa-users-slash fa-3x mb-3 d-block"></i>
                                No se encontraron clientes en la base de datos.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addClientModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-modal">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-plus"></i> Nuevo Cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addClientForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                    <input type="hidden" name="action" value="create">

                    <div class="mb-3">
                        <label class="form-label">Nombre</label>
                        <input type="text" name="nombre" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Apellido</label>
                        <input type="text" name="apellido" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Cédula o Código</label>
                        <input type="text" name="cedula_o_codigo" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Correo</label>
                        <input type="email" name="correo" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tipo de cliente</label>
                        <select name="tipo_cliente_id" class="form-select" required>
                            <option value="">Selecciona un tipo</option>
                            <?php while ($type = mysqli_fetch_assoc($typeResult)): ?>
                                <option value="<?= htmlspecialchars($type['id']) ?>"><?= htmlspecialchars($type['nombre_rol']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <!-- Campo 'Estado de cuenta' eliminado por solicitud -->
                    <button type="submit" class="btn btn-primary w-100">Crear Cliente</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="messageModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-modal">
            <div class="modal-header">
                <h5 class="modal-title" id="messageModalTitle">Aviso</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="messageModalBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>

<script>
    function showMessageModal(title, message) {
        document.getElementById('messageModalTitle').innerText = title;
        document.getElementById('messageModalBody').innerHTML = message;
        new bootstrap.Modal(document.getElementById('messageModal')).show();
    }

    document.getElementById('addClientForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);

        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            });

            const text = await response.text();
            let result;
            try {
                result = JSON.parse(text);
            } catch (error) {
                console.error('Error analizando JSON:', text);
                result = { success: false, message: 'Respuesta inválida del servidor.' };
            }

            if (result.success) {
                bootstrap.Modal.getInstance(document.getElementById('addClientModal')).hide();
                showMessageModal('Éxito', result.message);
                setTimeout(() => location.reload(), 1200);
            } else {
                showMessageModal('Error', result.message);
            }
        } catch (error) {
            console.error('Error en la petición:', error);
            showMessageModal('Error', 'No se pudo enviar el formulario. Intenta de nuevo.');
        }
    });
</script>

<?php include_once "includes/footer.php"; ?>