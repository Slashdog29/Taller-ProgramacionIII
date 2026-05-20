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

    // ACTION: Crear nuevo periférico
    if ($action === 'create') {
        $tipo_periferico = intval($_POST['tipo_periferico'] ?? 0);
        // NOTE: código de bien ahora se genera automáticamente en servidor, no se toma del POST
        $codigo_bien_nacional = '';
        $numero_serial_fabrica = trim($_POST['numero_serial_fabrica'] ?? '');
        $marca = trim($_POST['marca'] ?? '');
        $modelo = trim($_POST['modelo'] ?? '');
        $color = trim($_POST['color'] ?? '');

        // Validar campos requeridos (excepto el código que se genera automáticamente)
        if ($tipo_periferico <= 0 || empty($numero_serial_fabrica) || empty($marca) || empty($modelo) || empty($color)) {
            $response['message'] = 'Todos los campos obligatorios deben completarse.';
            echo json_encode($response);
            exit;
        }

        // === Generación automática de 'codigo_bien_nacional' ===
        // 1) Obtener el nombre del tipo seleccionado para formar el prefijo (primeras 3 letras en mayúsculas)
        // 2) Contar cuántos registros del mismo tipo existen en la tabla 'perifericos' (correlativo)
        // 3) Formatear el número a 3 dígitos con ceros a la izquierda
        // 4) Concatenar en el formato exacto: BIEN-[PREFIJO]-[NNN]

        $prefijo = 'PER'; // valor por defecto si no se encuentra el tipo
        $siguiente_numero = 1;

        $sql_tipo = "SELECT nombre_componente FROM tipos_periferico WHERE id = ? LIMIT 1";
        $stmt_t = mysqli_prepare($conexion, $sql_tipo);
        if ($stmt_t) {
            mysqli_stmt_bind_param($stmt_t, 'i', $tipo_periferico);
            mysqli_stmt_execute($stmt_t);
            $res_t = mysqli_stmt_get_result($stmt_t);
            if ($res_t && $row_t = mysqli_fetch_assoc($res_t)) {
                $prefijo = strtoupper(substr($row_t['nombre_componente'] ?? 'PER', 0, 3));
            }
            mysqli_stmt_close($stmt_t);
        }

        $sql_count = "SELECT COUNT(*) AS total FROM perifericos WHERE tipo_periferico_id = ?";
        $stmt_c = mysqli_prepare($conexion, $sql_count);
        if ($stmt_c) {
            mysqli_stmt_bind_param($stmt_c, 'i', $tipo_periferico);
            mysqli_stmt_execute($stmt_c);
            $res_c = mysqli_stmt_get_result($stmt_c);
            if ($res_c && $row_c = mysqli_fetch_assoc($res_c)) {
                $siguiente_numero = intval($row_c['total']) + 1;
            }
            mysqli_stmt_close($stmt_c);
        }

        $numero_formateado = str_pad($siguiente_numero, 3, '0', STR_PAD_LEFT);
        $codigo_bien_nacional = "BIEN-" . $prefijo . "-" . $numero_formateado;

        // ======================================================

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

    // ACTION: Update (Editar) - SOLO actualiza el estado del periférico
    } elseif ($action === 'update') {
        $id_bien = intval($_POST['id_bien'] ?? 0);
        $estado_bien = trim($_POST['estado_bien'] ?? '');

        if ($id_bien <= 0 || empty($estado_bien)) {
            $response['message'] = 'Datos insuficientes para actualizar estado.';
            echo json_encode($response);
            exit;
        }

        // obtener nombre para historial
        $nombre = '';
        $rq = mysqli_prepare($conexion, "SELECT modelo FROM perifericos WHERE id = ? LIMIT 1");
        if ($rq) {
            mysqli_stmt_bind_param($rq, 'i', $id_bien);
            mysqli_stmt_execute($rq);
            $rr = mysqli_stmt_get_result($rq);
            if ($rr && $row = mysqli_fetch_assoc($rr)) $nombre = $row['modelo'];
            mysqli_stmt_close($rq);
        }

        $update_q = "UPDATE perifericos SET estado_fisico = ? WHERE id = ?";
        $stmt_up = mysqli_prepare($conexion, $update_q);
        if ($stmt_up) {
            mysqli_stmt_bind_param($stmt_up, 'si', $estado_bien, $id_bien);
            $ok = mysqli_stmt_execute($stmt_up);

            if ($ok) {
                $usuario = $_SESSION['nombre'] ?? 'Sistema';
                $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                $fyh = date('Y-m-d H:i:s');
                $sector = 'Periféricos';
                $acciones = "Cambió estado del periférico $nombre a $estado_bien";
                $hist_q = "INSERT INTO historial (usuario, ip, fyh, sector, acciones) VALUES (?, ?, ?, ?, ?)";
                $s = mysqli_prepare($conexion, $hist_q);
                if ($s) {
                    mysqli_stmt_bind_param($s, 'sssss', $usuario, $ip, $fyh, $sector, $acciones);
                    mysqli_stmt_execute($s);
                    mysqli_stmt_close($s);
                }

                $response['success'] = true;
                $response['message'] = 'Estado actualizado.';
            } else {
                $response['message'] = 'Error al actualizar estado: ' . mysqli_stmt_error($stmt_up);
            }
            mysqli_stmt_close($stmt_up);
        } else {
            $response['message'] = 'Error al preparar actualización: ' . mysqli_error($conexion);
        }

    // ACTION: Assign (Asignar) - vincula periférico a una computadora (computadora_id)
    } elseif ($action === 'assign') {
        $id_bien = intval($_POST['id_bien'] ?? 0);
        $id_computadora = intval($_POST['id_computadora'] ?? 0);

        if ($id_bien <= 0 || $id_computadora <= 0) {
            $response['message'] = 'Datos insuficientes para asignar.';
            echo json_encode($response);
            exit;
        }

        // Actualizar columna computadora_id en perifericos
        $upd = "UPDATE perifericos SET computadora_id = ? WHERE id = ?";
        $s_up = mysqli_prepare($conexion, $upd);
        if ($s_up) {
            mysqli_stmt_bind_param($s_up, 'ii', $id_computadora, $id_bien);
            $ok = mysqli_stmt_execute($s_up);
            if ($ok) {
                // obtener numero_puesto para el mensaje
                $pq = mysqli_prepare($conexion, "SELECT numero_puesto FROM computadoras WHERE id = ? LIMIT 1");
                $puesto = $id_computadora;
                if ($pq) {
                    mysqli_stmt_bind_param($pq, 'i', $id_computadora);
                    mysqli_stmt_execute($pq);
                    $res_pq = mysqli_stmt_get_result($pq);
                    if ($res_pq && $rowp = mysqli_fetch_assoc($res_pq)) {
                        $puesto = $rowp['numero_puesto'];
                    }
                    mysqli_stmt_close($pq);
                }

                $usuario = $_SESSION['nombre'] ?? 'Sistema';
                $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                $fyh = date('Y-m-d H:i:s');
                $sector = 'Periféricos';
                $acciones = "Asignó el periférico al puesto $puesto";
                $hist_q = "INSERT INTO historial (usuario, ip, fyh, sector, acciones) VALUES (?, ?, ?, ?, ?)";
                $s = mysqli_prepare($conexion, $hist_q);
                if ($s) {
                    mysqli_stmt_bind_param($s, 'sssss', $usuario, $ip, $fyh, $sector, $acciones);
                    mysqli_stmt_execute($s);
                    mysqli_stmt_close($s);
                }

                $response['success'] = true;
                $response['message'] = 'Periférico asignado.';
            } else {
                $response['message'] = 'Error al asignar: ' . mysqli_stmt_error($s_up);
            }
            mysqli_stmt_close($s_up);
        } else {
            $response['message'] = 'Error al preparar asignación: ' . mysqli_error($conexion);
        }

    // ACTION: set_damaged (Marcar como dañado)
    } elseif ($action === 'set_damaged') {
        $id_bien = intval($_POST['id_bien'] ?? 0);
        if ($id_bien <= 0) {
            $response['message'] = 'ID inválido.';
            echo json_encode($response);
            exit;
        }

        // obtener nombre para historial
        $nombre = '';
        $rq = mysqli_prepare($conexion, "SELECT modelo FROM perifericos WHERE id = ? LIMIT 1");
        if ($rq) {
            mysqli_stmt_bind_param($rq, 'i', $id_bien);
            mysqli_stmt_execute($rq);
            $rr = mysqli_stmt_get_result($rq);
            if ($rr && $row = mysqli_fetch_assoc($rr)) $nombre = $row['modelo'];
            mysqli_stmt_close($rq);
        }

        $upd = mysqli_prepare($conexion, "UPDATE perifericos SET estado_fisico = 'dañado' WHERE id = ?");
        if ($upd) {
            mysqli_stmt_bind_param($upd, 'i', $id_bien);
            $ok = mysqli_stmt_execute($upd);
            if ($ok) {
                $usuario = $_SESSION['nombre'] ?? 'Sistema';
                $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                $fyh = date('Y-m-d H:i:s');
                $sector = 'Periféricos';
                $acciones = "Marcó el periférico $nombre como descompuesto";
                $hist_q = "INSERT INTO historial (usuario, ip, fyh, sector, acciones) VALUES (?, ?, ?, ?, ?)";
                $s = mysqli_prepare($conexion, $hist_q);
                if ($s) {
                    mysqli_stmt_bind_param($s, 'sssss', $usuario, $ip, $fyh, $sector, $acciones);
                    mysqli_stmt_execute($s);
                    mysqli_stmt_close($s);
                }
                $response['success'] = true;
                $response['message'] = 'Periférico marcado como descompuesto.';
            } else {
                $response['message'] = 'Error al marcar como descompuesto: ' . mysqli_stmt_error($upd);
            }
            mysqli_stmt_close($upd);
        } else {
            $response['message'] = 'Error al preparar la petición: ' . mysqli_error($conexion);
        }

    // ACTION: disable (baja lógica)
    } elseif ($action === 'disable') {
        $id_bien = intval($_POST['id_bien'] ?? 0);
        if ($id_bien <= 0) {
            $response['message'] = 'ID inválido.';
            echo json_encode($response);
            exit;
        }

        // Realizamos una baja lógica: desvincular de computadora y marcar como 'dañado' (no eliminar físicamente)
        $upd = mysqli_prepare($conexion, "UPDATE perifericos SET computadora_id = NULL, estado_fisico = 'dañado' WHERE id = ?");
        if ($upd) {
            mysqli_stmt_bind_param($upd, 'i', $id_bien);
            $ok = mysqli_stmt_execute($upd);
            if ($ok) {
                $usuario = $_SESSION['nombre'] ?? 'Sistema';
                $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                $fyh = date('Y-m-d H:i:s');
                $sector = 'Periféricos';
                $acciones = "Dio de baja el periférico del sistema";
                $hist_q = "INSERT INTO historial (usuario, ip, fyh, sector, acciones) VALUES (?, ?, ?, ?, ?)";
                $s = mysqli_prepare($conexion, $hist_q);
                if ($s) {
                    mysqli_stmt_bind_param($s, 'sssss', $usuario, $ip, $fyh, $sector, $acciones);
                    mysqli_stmt_execute($s);
                    mysqli_stmt_close($s);
                }
                $response['success'] = true;
                $response['message'] = 'Periférico dado de baja (lógica).';
            } else {
                $response['message'] = 'Error al dar de baja: ' . mysqli_stmt_error($upd);
            }
            mysqli_stmt_close($upd);
        } else {
            $response['message'] = 'Error al preparar la petición: ' . mysqli_error($conexion);
        }

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

// Consulta para computadoras (usada en modal Asignar)
$computadoras = [];
$res_comp = mysqli_query($conexion, "SELECT id, numero_puesto FROM computadoras ORDER BY numero_puesto ASC");
if ($res_comp) {
    while ($r = mysqli_fetch_assoc($res_comp)) {
        $computadoras[] = $r;
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
                                        <button type="button" class="btn btn-sm btn-outline-warning rounded-pill me-2 damage-peripheral" data-id="<?php echo htmlspecialchars($bien['id']); ?>">
                                            <i class="fas fa-tools"></i>
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
                        <div class="form-text text-white">El código de inventario (Ej: BIEN-MON-001) se asignará automáticamente al guardar según la categoría.</div>
                    </div>
                    <!-- El campo 'Código bien nacional' fue eliminado del formulario porque ahora se genera en el servidor. -->
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

<!-- Modal Editar periférico (solo estado) -->
<div class="modal fade" id="modalEditarPeriferico" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-modal">
            <div class="modal-header">
                <h5 class="modal-title">Cambiar Estado</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formEditarPeriferico">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($token_csrf); ?>">
                    <input type="hidden" name="id_bien" value="">

                    <div class="mb-3">
                        <label class="form-label">Estado</label>
                        <select name="estado_bien" class="form-select" required>
                            <option value="excelente">Excelente</option>
                            <option value="bueno">Bueno</option>
                            <option value="regular">Regular</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Guardar estado</button>
                </form>
            </div>
        </div>
    </div>
</div>


<script>
    document.addEventListener('DOMContentLoaded', function() {
        const csrfToken = '<?php echo htmlspecialchars($token_csrf); ?>';

        // Crear periférico (form existente)
        const formPeriferico = document.getElementById('formPeriferico');
        formPeriferico.addEventListener('submit', async function(event) {
            event.preventDefault();
            const formData = new FormData(formPeriferico);
            try { await postForm(formData); } catch (e) { showError('No se pudo guardar el periférico.'); }
        });

        // Editar: abrir modal (solo estado)
        document.querySelectorAll('.edit-peripheral').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.dataset.id;
                const tr = this.closest('tr');
                const estado = tr.querySelector('td:nth-child(7) .status-badge')?.textContent.trim().toLowerCase() || 'excelente';
                const modal = document.getElementById('modalEditarPeriferico');
                modal.querySelector('input[name="id_bien"]').value = id;
                modal.querySelector('select[name="estado_bien"]').value = estado;
                new bootstrap.Modal(modal).show();
            });
        });

        // Envío formulario Editar (solo estado)
        const formEditar = document.getElementById('formEditarPeriferico');
        formEditar.addEventListener('submit', async function(e) {
            e.preventDefault();
            const fd = new FormData();
            fd.append('action','update');
            fd.append('id_bien', formEditar.querySelector('input[name="id_bien"]').value);
            fd.append('estado_bien', formEditar.querySelector('select[name="estado_bien"]').value);
            fd.append('csrf_token', csrfToken);
            try { await postForm(fd); } catch (err) { showError('No se pudo actualizar el estado del periférico.'); }
        });

        // Acción rápida: marcar como descompuesto
        document.querySelectorAll('.damage-peripheral').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.dataset.id;
                Swal.fire({
                    title: 'Marcar como descompuesto?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, marcar',
                }).then(async (res) => {
                    if (res.isConfirmed) {
                        const fd = new FormData(); fd.append('action','set_damaged'); fd.append('id_bien', id); fd.append('csrf_token', csrfToken);
                        try { await postForm(fd); } catch(e){ showError('No se pudo marcar como descompuesto.'); }
                    }
                });
            });
        });

        // Nota: botón de baja eliminado por decisión de UI — no se añade el handler

        // Helper: postForm centraliza llamadas y muestra mensajes
        async function postForm(formData) {
            const resp = await fetch('perifericos.php', { method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'}, body: formData });
            const data = await resp.json();
            if (data.success) {
                Swal.fire({ icon: 'success', title: 'OK', text: data.message, timer: 1200, showConfirmButton: false });
                setTimeout(() => location.reload(), 1400);
            } else {
                showError(data.message || 'Error en la operación');
                throw new Error(data.message || 'Error');
            }
        }

        function showError(msg) {
            Swal.fire({ icon: 'error', title: 'Error', text: msg });
        }
    });
</script>

<?php include_once "includes/footer.php"; ?>
