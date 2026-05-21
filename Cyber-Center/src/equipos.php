<?php
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "../conexion.php";
global $conexion;

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    ob_clean();
    header('Content-Type: application/json');

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido.']);
        exit;
    }

    $action = $_POST['action'] ?? '';
    $stmt = null;
    $id = intval($_POST['id'] ?? 0);
    $usuario_sesion = $_SESSION['nombre'] ?? 'Sistema';
    $ip_remota = $_SERVER['REMOTE_ADDR'];
    $fecha_hora = date('Y-m-d H:i:s');
    $sector = "Equipos";

    /* INICIO FUNCION REGISTRO Y EDITAR */
    if ($action === 'create' || $action === 'update') {
        $nombre = trim($_POST['nombre'] ?? '');
        $ip_address = trim($_POST['ip_address'] ?? '');
        $serial = trim($_POST['serial'] ?? '');
        $bien_nacional = trim($_POST['bien_nacional'] ?? '');
        $marca_id = intval($_POST['marca_id'] ?? 0);
        $modelo = trim($_POST['modelo'] ?? '');
        $color = trim($_POST['color'] ?? '');
        $perifericos_ids = $_POST['perifericos'] ?? [];

        $numero_puesto = intval(preg_replace('/[^0-9]/', '', $nombre));

        if (empty($nombre) || empty($ip_address) || empty($serial)) {
            echo json_encode(['success' => false, 'message' => 'El número de puesto, la IP y el Serial son obligatorios.']);
            exit;
        }

        if (empty($bien_nacional)) $bien_nacional = $serial;

        if ($action === 'create') {
            $stmt = $conexion->prepare("INSERT INTO computadoras (numero_puesto, direccion_ip, estado_operativo, codigo_bien_nacional, numero_serial_chasis, marca, modelo, color, fecha_incorporacion) VALUES (?, ?, 'disponible', ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("isssiss", $numero_puesto, $ip_address, $bien_nacional, $serial, $marca_id, $modelo, $color);
            $msg = "Equipo registrado correctamente.";
            $accion_historial = "Registró nuevo equipo: $nombre ($ip_address)";
        } else {
            $stmt = $conexion->prepare("UPDATE computadoras SET numero_puesto = ?, direccion_ip = ?, codigo_bien_nacional = ?, numero_serial_chasis = ?, marca = ?, modelo = ?, color = ? WHERE id = ?");
            $stmt->bind_param("isssissi", $numero_puesto, $ip_address, $bien_nacional, $serial, $marca_id, $modelo, $color, $id);
            $msg = "Equipo actualizado con éxito.";
            $accion_historial = "Editó equipo ID $id: $nombre ($ip_address)";
        }
    /* FIN FUNCION REGISTRO Y EDITAR */
    } elseif ($action === 'change_status') {
        $nuevo_estado = trim($_POST['estado'] ?? '');
        $stmt = $conexion->prepare("UPDATE computadoras SET estado_operativo = ? WHERE id = ?");
        $stmt->bind_param("si", $nuevo_estado, $id);
        $msg = "Estado del equipo actualizado.";
        $accion_historial = "Cambió estado de equipo ID $id a: $nuevo_estado";
    }

    if (isset($stmt) && $stmt->execute()) {
        /* INICIO GESTIÓN PERIFÉRICOS PARA REGISTRO Y EDITAR */
        if ($action === 'create' || $action === 'update') {
            $compu_id = ($action === 'create') ? $conexion->insert_id : $id;
            $conexion->query("UPDATE perifericos SET computadora_id = NULL WHERE computadora_id = $compu_id");
            if (!empty($perifericos_ids) && is_array($perifericos_ids)) {
                foreach ($perifericos_ids as $p_id) {
                    $stmt_p = $conexion->prepare("UPDATE perifericos SET computadora_id = ? WHERE id = ?");
                    $p_id_int = intval($p_id);
                    $stmt_p->bind_param("ii", $compu_id, $p_id_int);
                    $stmt_p->execute();
                    $stmt_p->close();
                }
            }
        }
        /* FIN GESTIÓN PERIFÉRICOS PARA REGISTRO Y EDITAR */

        $stmt_h = $conexion->prepare("INSERT INTO historial (usuario, ip, fyh, sector, acciones) VALUES (?, ?, ?, ?, ?)");
        $stmt_h->bind_param("sssss", $usuario_sesion, $ip_remota, $fecha_hora, $sector, $accion_historial);
        $stmt_h->execute();
        echo json_encode(['success' => true, 'message' => $msg]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error en la base de datos: ' . $conexion->error]);
    }
    if ($stmt) $stmt->close();
    exit;
}

include_once "includes/header.php";

$nombre = $_SESSION['nombre'];
$ip = $_SERVER['REMOTE_ADDR'];
$fecha_hora = date('Y-m-d H:i:s');
$sector = "Equipos";
$accion = "Acceso a la sección de gestión de equipos";
$stmt = $conexion->prepare("INSERT INTO historial (usuario, ip, fyh, sector, acciones) VALUES (?, ?, ?, ?, ?)");
if ($stmt) {
    $stmt->bind_param("sssss", $nombre, $ip, $fecha_hora, $sector, $accion);
    $stmt->execute();
    $stmt->close();
}

$query = "SELECT v.*, c.numero_serial_chasis, c.marca as marca_id, 
          (SELECT GROUP_CONCAT(id) FROM perifericos WHERE computadora_id = v.compu_id) as perifericos_ids 
          FROM vista_inventario_computadoras v 
          JOIN computadoras c ON v.compu_id = c.id 
          ORDER BY v.numero_puesto ASC";
$resultado = mysqli_query($conexion, $query);

$res_marcas = mysqli_query($conexion, "SELECT * FROM marca ORDER BY nombremarca ASC");
$marcas_list = [];
while($m = mysqli_fetch_assoc($res_marcas)) $marcas_list[] = $m;

$perifericos_disponibles = [];

$query_p = "SELECT p.*, tp.nombre_componente 
            FROM perifericos p 
            LEFT JOIN tipos_periferico tp ON p.tipo_periferico_id = tp.id 
            ORDER BY tp.nombre_componente ASC, p.marca ASC";
$res_p = mysqli_query($conexion, $query_p);

if ($res_p) {
    while($p = mysqli_fetch_assoc($res_p)) $perifericos_disponibles[] = $p;
}
?>

<style>
    .table { color: var(--text-light); margin-bottom: 0; border-collapse: separate; border-spacing: 0; }
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
    .table tbody tr { transition: all 0.3s ease; }
    .table tbody tr:hover { background: rgba(0, 0, 0, 0.3) !important; }
    .status-badge { border-radius: 20px; padding: 0.45rem 0.85rem; font-size: 0.65rem; text-transform: uppercase; font-weight: 800; letter-spacing: 0.5px; }
    .glass-card { padding: 0 !important; overflow: hidden; border: 1px solid rgba(255, 255, 255, 0.1); }
    .table-responsive { border-radius: 24px; }
    
    .table tbody td { color: #ffffff !important; } 
    .text-white-50 { color: rgba(255, 255, 255, 0.85) !important; } 
    .text-muted { color: rgba(255, 255, 255, 0.75) !important; }
    
    .bg-dark.border-secondary { background-color: rgba(45, 55, 72, 0.9) !important; border-color: rgba(255, 255, 255, 0.3) !important; }

    .perifericos-container { max-height: 250px; overflow-y: auto; scrollbar-width: thin; border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; }
    .perifericos-container::-webkit-scrollbar { width: 6px; }
    .perifericos-container::-webkit-scrollbar-thumb { background: var(--primary-light); border-radius: 10px; }
    .table-perif { font-size: 0.85rem; margin-bottom: 0; color: #fff; }
    .table-perif thead th { position: sticky; top: 0; background: #1a202c; z-index: 10; border-bottom: 2px solid var(--primary-light); padding: 8px; }
    .table-perif td { padding: 8px; border-bottom: 1px solid rgba(255,255,255,0.05); }

    /* Consistencia de selects */
    .glass-modal .form-select {
        background-color: rgba(15, 23, 42, 0.9) !important;
        border: 1px solid rgba(255, 255, 255, 0.1) !important;
        color: #fff !important;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23ffffff' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") !important;
    }
    .glass-modal option { background: #1a202c; }
</style>

    <div class="container main-content pb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-0 text-white">Gestión de Equipos</h2>
                <p class="text-white-50">Inventario y estado de estaciones de trabajo</p>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEquipoModal" onclick="prepareAddModal()">
                <i class="fas fa-plus me-2"></i>Registrar Equipo
            </button>
        </div>

        <div class="glass-card p-4">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th># PUESTO</th>
                            <th>MARCA / MODELO</th>
                            <th>SERIAL / CHASIS</th>
                            <th>DIRECCIÓN IP</th>
                            <th>ESTADO</th>
                            <th>PERIFÉRICOS</th>
                            <th class="text-end">ACCIONES</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($resultado)): 
                            $badge_class = match($row['estado_operativo']) {
                                'disponible' => 'bg-success',
                                'ocupado' => 'bg-primary',
                                'mantenimiento' => 'bg-warning text-dark',
                                'desincorporado' => 'bg-danger',
                                default => 'bg-secondary'
                            };
                        ?>
                        <tr data-id="<?php echo $row['compu_id']; ?>" 
                            data-nombre="<?php echo htmlspecialchars($row['nombre'] ?? 'PC-'.$row['numero_puesto']); ?>" 
                            data-ip="<?php echo htmlspecialchars($row['direccion_ip'] ?? ''); ?>"
                            data-estado="<?php echo $row['estado_operativo']; ?>"
                            data-serial="<?php echo htmlspecialchars($row['numero_serial_chasis'] ?? ''); ?>"
                            data-bien="<?php echo htmlspecialchars($row['bien_nacional_pc'] ?? ''); ?>"
                            data-marca="<?php echo $row['marca_id']; ?>"
                            data-modelo="<?php echo htmlspecialchars($row['pc_modelo'] ?? ''); ?>"
                            data-color="<?php echo htmlspecialchars($row['pc_color'] ?? ''); ?>"
                            data-perifericos="<?php echo $row['perifericos_ids']; ?>">
                            
                            <td class="fw-bold" style="color: var(--primary-light);">PC-<?php echo str_pad($row['numero_puesto'], 2, '0', STR_PAD_LEFT); ?></td>
                            <td>
                                <div class="fw-semibold"><?php echo $row['pc_marca']; ?></div>
                                <small class="text-white-50"><?php echo $row['pc_modelo']; ?></small>
                            </td>
                            <td><code style="color: #00f2ff; font-size: 0.9rem; font-weight: 700;"><?php echo $row['numero_serial_chasis']; ?></code></td>
                            <td class="fw-medium"><?php echo $row['direccion_ip'] ?? '<span class="text-white-50"><i>No asignada</i></span>'; ?></td>
                            <td>
                                <span class="badge status-badge <?php echo $badge_class; ?>">
                                    <?php echo $row['estado_operativo']; ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-dark border border-secondary" 
                                      data-bs-toggle="tooltip" 
                                      data-bs-placement="top" 
                                      data-bs-title="<?php echo htmlspecialchars($row['detalle_perifericos'] ?? 'Sin periféricos'); ?>" 
                                      style="cursor: help;">
                                    <i class="fas fa-plug me-1" style="color: var(--primary-light);"></i> <?php echo $row['perifericos_asignados']; ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-light rounded-pill me-2 edit-btn" title="Editar"><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-sm btn-outline-warning rounded-pill status-btn" title="Estado"><i class="fas fa-sync-alt"></i></button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<div class="modal fade" id="addEquipoModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-modal">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Nuevo Equipo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addEquipoForm">
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <div class="mb-3">
                        <label class="form-label">Nombre/Etiqueta</label>
                        <input type="text" name="nombre" class="form-control" placeholder="Ej: Estación 01" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Dirección IP</label>
                        <input type="text" name="ip_address" class="form-control" placeholder="192.168.1.XX" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Serial / Chasis</label>
                            <input type="text" name="serial" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Bien Nacional</label>
                            <input type="text" name="bien_nacional" class="form-control">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Marca</label>
                        <select name="marca_id" class="form-select" required>
                            <option value="">Seleccione Marca</option>
                            <?php foreach($marcas_list as $m): ?>
                                <option value="<?php echo $m['id_marca']; ?>"><?php echo htmlspecialchars($m['nombremarca']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Modelo</label>
                            <input type="text" name="modelo" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Color</label>
                            <input type="text" name="color" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-white">Seleccionar Periféricos</label>
                        <div class="perifericos-container bg-dark bg-opacity-25">
                            <table class="table table-perif">
                                <thead>
                                    <tr>
                                        <th style="width: 40px;"></th>
                                        <th>Tipo</th>
                                        <th>Marca / Modelo / Serial</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($perifericos_disponibles)): ?>
                                        <tr><td colspan="3" class="text-center text-muted p-3"><i>No hay periféricos registrados en el sistema</i></td></tr>
                                    <?php else: ?>
                                        <?php foreach($perifericos_disponibles as $p):
                                            $is_busy = ($p['computadora_id'] != 0 && $p['computadora_id'] !== null);
                                        ?>
                                        <tr class="perif-row-add <?php echo $is_busy ? 'opacity-50' : ''; ?>" data-tipo="<?php echo $p['tipo_periferico_id']; ?>">
                                            <td class="text-center">
                                                <input type="checkbox" name="perifericos[]" value="<?php echo $p['id']; ?>" 
                                                       class="form-check-input perif-check-add" id="add_p_<?php echo $p['id']; ?>" <?php echo $is_busy ? 'disabled' : ''; ?>>
                                            </td>
                                            <td class="fw-bold text-info">
                                                <label for="add_p_<?php echo $p['id']; ?>" style="display: block; cursor: <?php echo $is_busy ? 'default' : 'pointer'; ?>;"><?php echo htmlspecialchars($p['nombre_componente'] ?: 'Sin tipo'); ?></label>
                                            </td>
                                            <td>
                                                <label for="add_p_<?php echo $p['id']; ?>" style="display: block; cursor: <?php echo $is_busy ? 'default' : 'pointer'; ?>;">
                                                    <?php echo htmlspecialchars($p['marca'] . " " . $p['modelo']); ?>
                                                    <br><small class="text-white-50">S/N: <?php echo htmlspecialchars($p['numero_serial_fabrica']); ?></small>
                                                </label>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Registrar</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editEquipoModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-modal">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Editar Equipo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editEquipoForm">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="edit_id">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <div class="mb-3">
                        <label class="form-label">Nombre/Etiqueta</label>
                        <input type="text" name="nombre" id="edit_nombre" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Dirección IP</label>
                        <input type="text" name="ip_address" id="edit_ip" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Serial / Chasis</label>
                            <input type="text" name="serial" id="edit_serial" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Bien Nacional</label>
                            <input type="text" name="bien_nacional" id="edit_bien" class="form-control">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Marca</label>
                        <select name="marca_id" id="edit_marca" class="form-select" required>
                            <option value="">Seleccione Marca</option>
                            <?php foreach($marcas_list as $m): ?>
                                <option value="<?php echo $m['id_marca']; ?>"><?php echo htmlspecialchars($m['nombremarca']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Modelo</label>
                            <input type="text" name="modelo" id="edit_modelo" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Color</label>
                            <input type="text" name="color" id="edit_color" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-white">Editar Periféricos</label>
                        <div class="perifericos-container bg-dark bg-opacity-25">
                            <table class="table table-perif">
                                <thead>
                                    <tr>
                                        <th style="width: 40px;"></th>
                                        <th>Tipo</th>
                                        <th>Marca / Modelo / Serial</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($perifericos_disponibles)): ?>
                                        <tr><td colspan="3" class="text-center text-muted p-3"><i>No hay periféricos registrados</i></td></tr>
                                    <?php else: ?>
                                        <?php foreach($perifericos_disponibles as $p): ?>
                                        <tr class="perif-row-edit" data-tipo="<?php echo $p['tipo_periferico_id']; ?>">
                                            <td class="text-center">
                                                <input type="checkbox" name="perifericos[]" value="<?php echo $p['id']; ?>" 
                                                       class="form-check-input perif-check-edit" id="edit_p_<?php echo $p['id']; ?>" 
                                                       data-owner="<?php echo $p['computadora_id'] ?? '0'; ?>"
                                                       data-tipo="<?php echo $p['tipo_periferico_id']; ?>">
                                            </td>
                                            <td class="fw-bold text-info">
                                                <label for="edit_p_<?php echo $p['id']; ?>" style="display: block; cursor: pointer;"><?php echo htmlspecialchars($p['nombre_componente'] ?: 'Sin tipo'); ?></label>
                                            </td>
                                            <td>
                                                <label for="edit_p_<?php echo $p['id']; ?>" style="display: block; cursor: pointer;">
                                                    <?php echo htmlspecialchars($p['marca'] . " " . $p['modelo']); ?>
                                                    <br>
                                                    <small class="text-white-50">S/N: <?php echo htmlspecialchars($p['numero_serial_fabrica']); ?></small>
                                                    <span class="badge bg-secondary ms-1 owner-badge" style="display:none; font-size: 0.6rem;">Ocupado</span>
                                                </label>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Guardar Cambios</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-modal">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-sync-alt"></i> Cambiar Estado</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="statusForm">
                    <input type="hidden" name="action" value="change_status">
                    <input type="hidden" name="id" id="status_id">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <div class="mb-3">
                        <label class="form-label">Nuevo estado para <strong id="status_name"></strong></label>
                        <select name="estado" id="status_select" class="form-select" required>
                            <option value="disponible">Disponible</option>
                            <option value="mantenimiento">Mantenimiento</option>
                            <option value="desincorporado">Desincorporado</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-warning w-100">Actualizar Estado</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="messageModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content glass-modal"><div class="modal-header"><h5 class="modal-title" id="messageModalTitle">Aviso</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body" id="messageModalBody"></div><div class="modal-footer"><button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button></div></div></div></div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })
    });

    function showMsg(title, msg) {
        document.getElementById('messageModalTitle').innerText = title;
        document.getElementById('messageModalBody').innerHTML = msg;
        new bootstrap.Modal(document.getElementById('messageModal')).show();
    }

    async function execAction(formId, modalId) {
        const form = document.getElementById(formId);
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            try {
                const res = await fetch(window.location.href, { method: 'POST', body: new FormData(form), headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await res.json();
                bootstrap.Modal.getInstance(document.getElementById(modalId)).hide();
                if (data.success) { showMsg('Éxito', data.message); setTimeout(() => location.reload(), 1000); }
                else showMsg('Error', data.message);
            } catch (error) {
                showMsg('Error', 'Ocurrió un problema procesando la respuesta del servidor.');
            }
        });
    }

    execAction('addEquipoForm', 'addEquipoModal');
    execAction('editEquipoForm', 'editEquipoModal');
    execAction('statusForm', 'statusModal');

    document.querySelectorAll('.edit-btn').forEach(b => b.addEventListener('click', () => { 
        const r = b.closest('tr').dataset; 
        document.getElementById('edit_id').value = r.id; 
        document.getElementById('edit_nombre').value = r.nombre; 
        document.getElementById('edit_ip').value = r.ip; 
        document.getElementById('edit_serial').value = r.serial;
        document.getElementById('edit_bien').value = r.bien;
        document.getElementById('edit_marca').value = r.marca;
        document.getElementById('edit_modelo').value = r.modelo;
        document.getElementById('edit_color').value = r.color;
        
        const currentId = r.id;
        const valuesP = r.perifericos ? r.perifericos.split(',') : [];
        
        document.querySelectorAll('.perif-check-edit').forEach(cb => {
            const ownerId = cb.dataset.owner;
            cb.checked = valuesP.includes(cb.value);
            
            const isOccupiedByOther = ownerId && ownerId != "0" && ownerId != currentId; 
            const row = cb.closest('tr');
            
            cb.disabled = isOccupiedByOther;
            row.classList.toggle('opacity-50', isOccupiedByOther);

            const badge = row.querySelector('.owner-badge');
            badge.style.display = isOccupiedByOther ? 'inline-block' : 'none';
        });

        new bootstrap.Modal(document.getElementById('editEquipoModal')).show(); 
    }));

    function prepareAddModal() {
        document.getElementById('addEquipoForm').reset();
    }

    document.querySelectorAll('.perifericos-container').forEach(container => {
        container.addEventListener('change', function(e) {
            if (e.target.type === 'checkbox' && e.target.checked) {
                const currentType = e.target.closest('tr').dataset.tipo;
                const siblings = container.querySelectorAll(`tr[data-tipo="${currentType}"] input[type="checkbox"]`);
                siblings.forEach(cb => { if (cb !== e.target) cb.checked = false; });
            }
        });
    });

    document.querySelectorAll('.status-btn').forEach(b => b.addEventListener('click', () => { const r = b.closest('tr').dataset; document.getElementById('status_id').value = r.id; document.getElementById('status_name').innerText = r.nombre; document.getElementById('status_select').value = r.estado; new bootstrap.Modal(document.getElementById('statusModal')).show(); }));
</script>

<?php include_once "includes/footer.php";

ob_end_flush();
?>
