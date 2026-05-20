<?php
// perifericos.php - Módulo de gestión e inventario de periféricos
// Procedural PHP 8 + MySQLi + AJAX

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "../conexion.php";
global $conexion;

// Generar token CSRF si aún no existe
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Procesamiento AJAX para registro de periférico
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');

    $response = ['success' => false, 'message' => 'Solicitud no válida.'];

    // Validar token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $response['message'] = 'Token CSRF inválido.';
        echo json_encode($response);
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $tipo_periferico = intval($_POST['tipo_periferico'] ?? 0);
        $codigo_bien_nacional = trim($_POST['codigo_bien_nacional'] ?? '');
        $numero_serial_fabrica = trim($_POST['numero_serial_fabrica'] ?? '');
        $marca = trim($_POST['marca'] ?? '');
        $modelo = trim($_POST['modelo'] ?? '');
        $color = trim($_POST['color'] ?? '');

        if ($tipo_periferico <= 0 || empty($codigo_bien_nacional) || empty($numero_serial_fabrica) || empty($marca) || empty($modelo) || empty($color)) {
            $response['message'] = 'Todos los campos son obligatorios.';
            echo json_encode($response);
            exit;
        }

        // Inserción en tabla perifericos según el esquema real
        $query = "INSERT INTO perifericos (tipo_periferico_id, codigo_bien_nacional, numero_serial_fabrica, marca, modelo, color) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conexion, $query);

        if ($stmt === false) {
            $response['message'] = 'Error al preparar la consulta: ' . mysqli_error($conexion);
            echo json_encode($response);
            exit;
        }

        mysqli_stmt_bind_param($stmt, 'isssss', $tipo_periferico, $codigo_bien_nacional, $numero_serial_fabrica, $marca, $modelo, $color);
        $execute_result = mysqli_stmt_execute($stmt);

        if ($execute_result) {
            $usuario = $_SESSION['nombre'] ?? 'Sistema';
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $fyh = date('Y-m-d H:i:s');
            $sector = 'Periféricos';
            $acciones = "Registró periférico: $marca $modelo (Código: $codigo_bien_nacional, Serial: $numero_serial_fabrica)";

            $historial_query = "INSERT INTO historial (usuario, ip, fyh, sector, acciones) VALUES (?, ?, ?, ?, ?)";
            $stmt_hist = mysqli_prepare($conexion, $historial_query);
            if ($stmt_hist) {
                mysqli_stmt_bind_param($stmt_hist, 'sssss', $usuario, $ip, $fyh, $sector, $acciones);
                mysqli_stmt_execute($stmt_hist);
                mysqli_stmt_close($stmt_hist);
            }

            $response['success'] = true;
            $response['message'] = 'Periférico registrado con éxito.';
        } else {
            $response['message'] = 'Error al insertar el periférico: ' . mysqli_stmt_error($stmt);
        }

        mysqli_stmt_close($stmt);
    } else {
        $response['message'] = 'Acción no reconocida.';
    }

    echo json_encode($response);
    exit;
}

// Cargar datos para la vista
include_once "includes/header.php";

$token_csrf = $_SESSION['csrf_token'];

// Consulta para tipos de periférico
$tipos_periferico = [];
$result_tipos = mysqli_query($conexion, "SELECT id, nombre_componente FROM tipos_periferico ORDER BY nombre_componente ASC");
if ($result_tipos) {
    while ($row = mysqli_fetch_assoc($result_tipos)) {
        $tipos_periferico[] = $row;
    }
}

// Consulta para inventario de periféricos con tipo de periférico
$bienes = [];
$query_bienes = "SELECT p.id, p.marca, p.modelo AS nombre, p.modelo, p.codigo_bien_nacional, p.numero_serial_fabrica AS serial, p.color, p.estado_fisico AS estado_bien, tp.nombre_componente AS tipo_nombre
                 FROM perifericos p
                 LEFT JOIN tipos_periferico tp ON p.tipo_periferico_id = tp.id
                 ORDER BY p.id ASC";
$result_bienes = mysqli_query($conexion, $query_bienes);
if ($result_bienes) {
    while ($row = mysqli_fetch_assoc($result_bienes)) {
        $bienes[] = $row;
    }
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
            <h2 class="fw-bold mb-0 text-white">Gestión de Periféricos</h2>
            <p class="text-white-50">Inventario y control de periféricos</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAgregarPeriferico">
            <i class="fas fa-plug me-2"></i>Nuevo Periférico
        </button>
    </div>

    <div class="glass-card">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>PERIFÉRICO</th>
                        <th>TIPO</th>
                        <th>CÓDIGO</th>
                        <th>SERIAL</th>
                        <th>COLOR</th>
                        <th>ESTADO</th>
                        <th class="text-end">ACCIONES</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bienes)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-white-50">
                                <i class="fas fa-plug-circle fa-3x mb-3 d-block"></i>
                                No hay periféricos registrados.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($bienes as $bien): ?>
                            <?php
                                $badgeClass = 'bg-secondary';
                                if ($bien['estado_bien'] === 'excelente' || $bien['estado_bien'] === 'bueno') {
                                    $badgeClass = 'bg-success';
                                } elseif ($bien['estado_bien'] === 'regular') {
                                    $badgeClass = 'bg-warning text-dark';
                                } elseif ($bien['estado_bien'] === 'dañado') {
                                    $badgeClass = 'bg-danger';
                                }
                            ?>
                            <tr data-id="<?= intval($bien['id']) ?>">
                                <td class="fw-bold" style="color: var(--primary-light);">#<?php echo htmlspecialchars($bien['id']); ?></td>
                                <td>
                                    <div class="fw-semibold text-white"><?php echo htmlspecialchars($bien['nombre']); ?></div>
                                    <small class="text-white-50"><?php echo htmlspecialchars($bien['marca']); ?> / <?php echo htmlspecialchars($bien['modelo']); ?></small>
                                </td>
                                <td><span class="badge bg-dark border border-secondary text-info"><?php echo htmlspecialchars($bien['tipo_nombre'] ?? 'Sin tipo'); ?></span></td>
                                <td><code style="color: #00f2ff; font-size: 0.9rem; font-weight: 700;"><?php echo htmlspecialchars($bien['codigo_bien_nacional'] ?? ''); ?></code></td>
                                <td><code style="color: #00f2ff; font-size: 0.9rem; font-weight: 700;"><?php echo htmlspecialchars($bien['serial'] ?? ''); ?></code></td>
                                <td class="text-white"><?php echo htmlspecialchars($bien['color'] ?? ''); ?></td>
                                <td>
                                    <span class="badge status-badge <?php echo $badgeClass; ?>">
                                        <?php echo htmlspecialchars(ucfirst($bien['estado_bien'])); ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group" role="group" aria-label="Acciones periférico">
                                        <button type="button" class="btn btn-sm btn-outline-light rounded-pill me-2 edit-peripheral" data-id="<?php echo htmlspecialchars($bien['id']); ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill me-2 assign-peripheral" data-id="<?php echo htmlspecialchars($bien['id']); ?>">
                                            <i class="fas fa-desktop"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-warning rounded-pill me-2 damage-peripheral" data-id="<?php echo htmlspecialchars($bien['id']); ?>">
                                            <i class="fas fa-tools"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger rounded-pill delete-peripheral" data-id="<?php echo htmlspecialchars($bien['id']); ?>">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal agregar periférico -->
<div class="modal fade" id="modalAgregarPeriferico" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-modal">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-keyboard"></i> Nuevo Periférico</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formPeriferico">
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($token_csrf); ?>">

                    <div class="mb-3">
                        <label class="form-label">Tipo de periférico</label>
                        <select name="tipo_periferico" class="form-select" required>
                            <option value="">Seleccione tipo</option>
                            <?php foreach ($tipos_periferico as $tipo): ?>
                                <option value="<?php echo htmlspecialchars($tipo['id']); ?>"><?php echo htmlspecialchars($tipo['nombre_componente']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Código bien nacional</label>
                        <input type="text" name="codigo_bien_nacional" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Número serial fábrica</label>
                        <input type="text" name="numero_serial_fabrica" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Marca</label>
                        <input type="text" name="marca" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Modelo</label>
                        <input type="text" name="modelo" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Color</label>
                        <input type="text" name="color" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Guardar periférico</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const formPeriferico = document.getElementById('formPeriferico');

        formPeriferico.addEventListener('submit', async function(event) {
            event.preventDefault();

            const formData = new FormData(formPeriferico);

            try {
                const response = await fetch('perifericos.php', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Registrado',
                        text: data.message,
                        timer: 1500,
                        timerProgressBar: true,
                        showConfirmButton: false
                    });
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'No se pudo guardar el periférico.'
                    });
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error de red',
                    text: 'No se pudo conectar con el servidor. Inténtalo de nuevo.'
                });
            }
        });
    });
</script>

<?php include_once "includes/footer.php"; ?>
