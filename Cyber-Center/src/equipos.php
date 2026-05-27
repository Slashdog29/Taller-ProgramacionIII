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
        $modelo_id = intval($_POST['modelo_id'] ?? 0);
        $color = trim($_POST['color'] ?? '');
        $perifericos_ids = $_POST['perifericos'] ?? [];

        $numero_puesto = intval(preg_replace('/[^0-9]/', '', $nombre));

        if (empty($nombre) || empty($ip_address) || empty($serial)) {
            echo json_encode(['success' => false, 'message' => 'El número de puesto, la IP y el Serial son obligatorios.']);
            exit;
        }

        if (empty($bien_nacional)) $bien_nacional = $serial;

        if ($action === 'create') {
            $stmt = $conexion->prepare("INSERT INTO computadoras (numero_puesto, direccion_ip, estado_operativo, codigo_bien_nacional, numero_serial_chasis, marca, modelo_id, color, fecha_incorporacion) VALUES (?, ?, 'disponible', ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("isssiis", $numero_puesto, $ip_address, $bien_nacional, $serial, $marca_id, $modelo_id, $color);
            $msg = "Equipo registrado correctamente.";
            $accion_historial = "Registró nuevo equipo: $nombre ($ip_address)";
        } else {
            $stmt = $conexion->prepare("UPDATE computadoras SET numero_puesto = ?, direccion_ip = ?, codigo_bien_nacional = ?, numero_serial_chasis = ?, marca = ?, modelo_id = ?, color = ? WHERE id = ?");
            $stmt->bind_param("isssiisi", $numero_puesto, $ip_address, $bien_nacional, $serial, $marca_id, $modelo_id, $color, $id);
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
    } elseif ($action === 'register_maintenance') {
        $fecha = $_POST['fecha_mantenimiento'] ?? date('Y-m-d H:i');
        $tipo = $_POST['tipo_mantenimiento'] ?? 'preventivo';
        $razon = trim($_POST['razon'] ?? '');
        $diagnostico = trim($_POST['diagnostico_correccion'] ?? '');

        if (empty($razon)) {
            echo json_encode(['success' => false, 'message' => 'La razón del mantenimiento es obligatoria.']);
            exit;
        }

        $stmt = $conexion->prepare("INSERT INTO mantenimientos (equipo_id, fecha_mantenimiento, tipo_mantenimiento, razon, diagnostico_correccion) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $id, $fecha, $tipo, $razon, $diagnostico);
        $msg = "Mantenimiento registrado correctamente.";
        $accion_historial = "Registró mantenimiento ($tipo) para equipo ID $id";
    } elseif ($action === 'create_model') {
        $nombre_modelo = trim($_POST['nombre_modelo'] ?? '');
        if (empty($nombre_modelo)) {
            echo json_encode(['success' => false, 'message' => 'El nombre del modelo es obligatorio.']);
            exit;
        }
        $chk = $conexion->prepare("SELECT id_modelo FROM modelo WHERE nombre_modelo = ?");
        $chk->bind_param("s", $nombre_modelo);
        $chk->execute(); $chk->store_result();
        if ($chk->num_rows > 0) { echo json_encode(['success' => false, 'message' => 'Este modelo ya existe.']); exit; }
        $chk->close();
        $stmt = $conexion->prepare("INSERT INTO modelo (nombre_modelo) VALUES (?)");
        $stmt->bind_param("s", $nombre_modelo);
        $msg = "Modelo registrado correctamente.";
        $accion_historial = "Registró nuevo modelo: $nombre_modelo";
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

// --- LÓGICA DE PAGINACIÓN ---
$por_pagina = 10; // Registros por página
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$offset = ($pagina_actual - 1) * $por_pagina;

// 1. Conteo total de registros
$total_res = mysqli_query($conexion, "SELECT COUNT(*) as total FROM vista_inventario_computadoras v JOIN computadoras c ON v.compu_id = c.id");
$total_registros = mysqli_fetch_assoc($total_res)['total'];
$total_paginas = ceil($total_registros / $por_pagina);

// 2. Consulta principal con LIMIT y OFFSET usando sentencias preparadas
$query = "SELECT v.*, c.numero_serial_chasis, c.marca as marca_id,
          (SELECT GROUP_CONCAT(id) FROM perifericos WHERE computadora_id = v.compu_id) as perifericos_ids,
          COUNT(m.id) AS total_mantenimientos
          FROM vista_inventario_computadoras v
          JOIN computadoras c ON v.compu_id = c.id
          LEFT JOIN mantenimientos m ON v.compu_id = m.equipo_id
          GROUP BY v.compu_id
          ORDER BY v.numero_puesto ASC LIMIT ? OFFSET ?";

$stmt_query = mysqli_prepare($conexion, $query);
mysqli_stmt_bind_param($stmt_query, "ii", $por_pagina, $offset);
mysqli_stmt_execute($stmt_query);
$resultado = mysqli_stmt_get_result($stmt_query);

// --- LÓGICA DE ALERTAS DE PERIFÉRICOS FALTANTES ---
// Definimos los nombres exactos de las categorías esenciales
$esenciales = ["Monitor", "Teclado", "Mouse"];
$equipos_incompletos = [];

/**
 * Esta consulta genera una matriz de todas las PCs activas contra los 3 tipos esenciales.
 * Si no existe un registro coincidente en la tabla 'perifericos', significa que falta ese componente.
 */
$query_alertas = "
    SELECT c.id as compu_id, c.numero_puesto, tp.nombre_componente
    FROM computadoras c
    CROSS JOIN (
        SELECT id, nombre_componente 
        FROM tipos_periferico 
        WHERE nombre_componente IN ('Monitor', 'Teclado', 'Mouse')
    ) tp
    LEFT JOIN perifericos p ON p.computadora_id = c.id AND p.tipo_periferico_id = tp.id
    WHERE p.id IS NULL AND c.estado_operativo != 'desincorporado'
    ORDER BY c.numero_puesto ASC, tp.nombre_componente ASC";

$res_alertas = mysqli_query($conexion, $query_alertas);
if ($res_alertas) {
    while ($row_a = mysqli_fetch_assoc($res_alertas)) {
        $equipos_incompletos[] = $row_a;
    }
}

$res_marcas = mysqli_query($conexion, "SELECT * FROM marca ORDER BY nombremarca ASC");
$marcas_list = [];
while($m = mysqli_fetch_assoc($res_marcas)) $marcas_list[] = $m;

$res_modelos = mysqli_query($conexion, "SELECT * FROM modelo ORDER BY nombre_modelo ASC");
$modelos_list = [];
while($mod = mysqli_fetch_assoc($res_modelos)) $modelos_list[] = $mod;

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

    /* Estilos de Paginación Glass-Dark */
    .pagination .page-link {
        background: rgba(15, 23, 42, 0.7) !important;
        border: 1px solid rgba(255, 255, 255, 0.1) !important;
        color: var(--primary-light) !important;
        transition: all 0.3s ease;
    }
    .pagination .page-item.active .page-link {
        background: #0d6efd !important;
        border-color: #0d6efd !important;
        color: white !important;
    }
    .pagination .page-item.disabled .page-link { background: rgba(0, 0, 0, 0.3) !important; color: rgba(255, 255, 255, 0.2) !important; border-color: rgba(255, 255, 255, 0.05) !important; }

    /* Buscador Adaptado */
    .search-wrapper { position: relative; min-width: 250px; }
    .search-wrapper i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: rgba(255, 255, 255, 0.4); z-index: 5; }
    .search-wrapper .form-control { 
        background: rgba(15, 23, 42, 0.6) !important; border: 1px solid rgba(255, 255, 255, 0.1) !important; 
        border-radius: 12px !important; padding-left: 45px !important; color: #ffffff !important; 
    }
    .search-wrapper .form-control::placeholder { color: rgba(255, 255, 255, 0.6) !important; }
    .search-wrapper .form-control:focus { border-color: var(--primary-light) !important; box-shadow: 0 0 15px rgba(13, 110, 253, 0.1) !important; }
</style>

<style>
    /* Timeline styles for maintenance history */
    .timeline {
        position: relative;
        padding: 20px 0;
        list-style: none;
    }
    .timeline:before {
        content: '';
        position: absolute;
        top: 0;
        bottom: 0;
        left: 50%;
        width: 2px;
        margin-left: -1px;
        background-color: rgba(255, 255, 255, 0.1);
    }
    .timeline-item {
        margin-bottom: 20px;
        position: relative;
    }
    .timeline-item:before, .timeline-item:after {
        content: " ";
        display: table;
    }
    .timeline-item:after {
        clear: both;
    }
    .timeline-badge {
        color: #fff;
        width: 24px;
        height: 24px;
        line-height: 24px;
        font-size: 1.4em;
        text-align: center;
        position: absolute;
        top: 16px;
        left: 50%;
        margin-left: -12px;
        background-color: #999999;
        z-index: 100;
        border-radius: 50%;
        border: 2px solid rgba(255, 255, 255, 0.2);
    }
    .timeline-panel {
        width: 45%;
        float: left;
        border-radius: 12px;
        position: relative;
        border: 1px solid rgba(255, 255, 255, 0.1);
        background: rgba(15, 23, 42, 0.7); /* glass-modal background */
        backdrop-filter: blur(10px);
    }
    .timeline-item.timeline-inverted .timeline-panel {
        float: right;
    }
</style>
    <div class="container main-content pb-5">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
            <div>
                <h2 class="fw-bold mb-0 text-white">Gestión de Equipos</h2>
                <p class="text-white-50">Inventario y estado de estaciones de trabajo</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <div class="search-wrapper">
                    <i class="fas fa-search"></i>
                    <input type="text" id="tableSearch" class="form-control" placeholder="Buscar equipo...">
                </div>
                <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#addModeloModal">
                    <i class="fas fa-microchip me-2"></i>Registrar Modelo
                </button>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEquipoModal" onclick="prepareAddModal()">
                    <i class="fas fa-plus me-2"></i>Registrar Equipo
                </button>
            </div>
        </div>

        <!-- Bloque de Alertas Automáticas (Glassmorphism Warning) -->
        <?php if (!empty($equipos_incompletos)): ?>
            <div class="alert mb-4 py-3 px-4" style="background: rgba(255, 193, 7, 0.05); border: 1px solid rgba(255, 193, 7, 0.2); backdrop-filter: blur(10px); border-radius: 15px;">
                <div class="d-flex align-items-start">
                    <div class="me-3 mt-1">
                        <i class="fas fa-bell text-warning animate__animated animate__pulse animate__infinite" style="font-size: 1.2rem;"></i>
                    </div>
                    <div>
                        <h6 class="text-warning fw-bold mb-2" style="letter-spacing: 0.5px;">COMPONENTES FALTANTES DETECTADOS</h6>
                        <div class="row row-cols-1 row-cols-md-2 g-2">
                            <?php foreach ($equipos_incompletos as $alerta): ?>
                                <div class="col">
                                    <span class="text-white-50 small">
                                        <i class="fas fa-exclamation-circle me-1 text-warning" style="font-size: 0.7rem;"></i>
                                        El <strong>PC-<?= str_pad($alerta['numero_puesto'], 2, '0', STR_PAD_LEFT) ?></strong> no tiene un <strong><?= htmlspecialchars($alerta['nombre_componente']) ?></strong> asignado. Rendimiento no óptimo.
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

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
                            <th>MANTENIMIENTOS</th>
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
                            data-modelo-id="<?php echo $row['modelo_id']; ?>"
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
                                <?php
                                    $maint_count = intval($row['total_mantenimientos']);
                                    $maint_text_class = ($maint_count > 3) ? 'text-warning' : 'text-white-50';
                                ?>
                                <span class="badge bg-dark border border-secondary <?php echo $maint_text_class; ?>">
                                    <?php echo $maint_count; ?> veces
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
                                <div class="btn-group" role="group" aria-label="Acciones del equipo">
                                    <button type="button" class="btn btn-sm btn-outline-info rounded-circle me-2 maint-btn" data-id="<?php echo $row['compu_id']; ?>" title="Registrar Mantenimiento">
                                        <i class="fas fa-wrench"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-light rounded-circle me-2 view-history-btn" 
                                            data-id="<?php echo $row['compu_id']; ?>" 
                                            data-type="equipo" 
                                            data-name="<?php echo htmlspecialchars('PC-'.$row['numero_puesto']); ?>" 
                                            title="Ver Historial">
                                        <i class="fas fa-history"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-light rounded-circle me-2 edit-btn" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-warning rounded-circle status-btn" title="Estado">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Componente de Paginación Bootstrap 5 -->
        <nav class="d-flex justify-content-center mt-3">
            <ul class="pagination pagination-sm">
                <li class="page-item <?= ($pagina_actual <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= ($pagina_actual > 1) ? '?' . http_build_query(array_merge($_GET, ['pagina' => $pagina_actual - 1])) : '#' ?>">Anterior</a>
                </li>
                <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                    <li class="page-item <?= ($i == $pagina_actual) ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $i])) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= ($pagina_actual >= $total_paginas) ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= ($pagina_actual < $total_paginas) ? '?' . http_build_query(array_merge($_GET, ['pagina' => $pagina_actual + 1])) : '#' ?>">Siguiente</a>
                </li>
            </ul>
        </nav>
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
                            <select name="modelo_id" class="form-select" required>
                                <option value="">Seleccione Modelo</option>
                                <?php foreach($modelos_list as $mod): ?>
                                    <option value="<?php echo $mod['id_modelo']; ?>"><?php echo htmlspecialchars($mod['nombre_modelo']); ?></option>
                                <?php endforeach; ?>
                            </select>
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
                            <select name="modelo_id" id="edit_modelo_id" class="form-select" required>
                                <option value="">Seleccione Modelo</option>
                                <?php foreach($modelos_list as $mod): ?>
                                    <option value="<?php echo $mod['id_modelo']; ?>"><?php echo htmlspecialchars($mod['nombre_modelo']); ?></option>
                                <?php endforeach; ?>
                            </select>
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

<div class="modal fade" id="addModeloModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content glass-modal">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Nuevo Modelo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addModeloForm">
                    <input type="hidden" name="action" value="create_model">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <div class="mb-3">
                        <label class="form-label">Nombre del Modelo</label>
                        <input type="text" name="nombre_modelo" class="form-control" placeholder="Ej: Optiplex 3080" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Guardar Modelo</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalMantenimiento" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-modal text-white">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-wrench me-2"></i> Registro de Mantenimiento</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="maintForm">
                    <input type="hidden" name="action" value="register_maintenance">
                    <input type="hidden" name="id" id="maint_entity_id">
                    <input type="hidden" name="tipo_entidad" value="equipo">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <p class="text-white-50">Componente: <strong id="maint_display_name" class="text-white"></strong></p>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold">FECHA DE MANTENIMIENTO</label>
                        <input type="date" name="fecha_mantenimiento" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold">TIPO DE MANTENIMIENTO</label>
                        <select name="tipo_mantenimiento" id="tipo_mantenimiento" class="form-select" required>
                            <option value="preventivo">Preventivo</option>
                            <option value="correctivo">Correctivo</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold">RAZÓN / MOTIVO</label>
                        <textarea name="razon" class="form-control" rows="2" required placeholder="Motivo del ingreso a revisión..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label id="label_diagnostico" class="form-label small fw-bold">DIAGNÓSTICO / CORRECCIÓN</label>
                        <textarea name="diagnostico_correccion" id="diagnostico_correccion" class="form-control" rows="3" placeholder="Detalle las reparaciones aplicadas..."></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-info w-100 fw-bold">GUARDAR REGISTRO</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Historial de Mantenimiento -->
<div class="modal fade" id="modalHistorialMantenimiento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content glass-modal text-white">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-history me-2"></i> Historial de Mantenimientos: <span id="history_entity_name"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="history_content" class="p-3">
                    <!-- Contenido cargado dinámicamente -->
                    <div class="text-center text-white-50 py-5">
                        <i class="fas fa-spinner fa-spin me-2"></i> Cargando historial...
                    </div>
                </div>
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
    document.addEventListener('DOMContentLoaded', function() {
        // Lógica del Buscador
        const searchInput = document.getElementById('tableSearch');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const query = this.value.toLowerCase();
                const rows = document.querySelectorAll('table.table tbody tr');
                rows.forEach(row => {
                    if (row.cells.length === 1) return;
                    row.style.display = row.textContent.toLowerCase().includes(query) ? '' : 'none';
                });
            });
        }

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
    execAction('maintForm', 'modalMantenimiento');
    execAction('addModeloForm', 'addModeloModal');

    const typeSelect = document.getElementById('tipo_mantenimiento');
    const descField = document.getElementById('diagnostico_correccion');
    const descLabel = document.getElementById('label_diagnostico');

    typeSelect?.addEventListener('change', function() {
        if (this.value === 'correctivo') {
            descField.setAttribute('required', 'required');
            descField.classList.add('border-info');
            descLabel.innerHTML = 'DIAGNÓSTICO / CORRECCIÓN <span class="text-danger">*</span>';
        } else {
            descField.removeAttribute('required');
            descField.classList.remove('border-info');
            descLabel.innerText = 'DIAGNÓSTICO / CORRECCIÓN';
        }
    });

    document.querySelectorAll('.edit-btn').forEach(b => b.addEventListener('click', () => { 
        const r = b.closest('tr').dataset; 
        document.getElementById('edit_id').value = r.id; 
        document.getElementById('edit_nombre').value = r.nombre; 
        document.getElementById('edit_ip').value = r.ip; 
        document.getElementById('edit_serial').value = r.serial;
        document.getElementById('edit_bien').value = r.bien;
        document.getElementById('edit_marca').value = r.marca;
        document.getElementById('edit_modelo_id').value = r.modeloId;
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

    document.querySelectorAll('.maint-btn').forEach(b => b.addEventListener('click', () => { 
        const r = b.closest('tr').dataset; 
        document.getElementById('maint_entity_id').value = b.dataset.id; 
        document.getElementById('maint_display_name').innerText = r.nombre; 
        new bootstrap.Modal(document.getElementById('modalMantenimiento')).show(); 
    }));

    // Lógica para el botón "Ver Historial"
    document.querySelectorAll('.view-history-btn').forEach(button => {
        button.addEventListener('click', async function() {
            const entityId = this.dataset.id;
            const entityType = this.dataset.type;
            const entityName = this.dataset.name;
            const historyModal = new bootstrap.Modal(document.getElementById('modalHistorialMantenimiento'));
            const historyContent = document.getElementById('history_content');
            const historyEntityName = document.getElementById('history_entity_name');

            historyEntityName.innerText = entityName;
            historyContent.innerHTML = '<div class="text-center text-white-50 py-5"><i class="fas fa-spinner fa-spin me-2"></i> Cargando historial...</div>';
            historyModal.show();

            try {
                const response = await fetch(`get_mantenimientos_ajax.php?id=${entityId}&tipo=${entityType}`);
                const data = await response.json();

                if (data.success && data.mantenimientos.length > 0) {
                    let html = '<div class="timeline">';
                    data.mantenimientos.forEach((maint, index) => {
                        const typeBadge = maint.tipo_mantenimiento === 'preventivo' ? 'bg-success' : 'bg-danger';
                        const invertedClass = index % 2 === 1 ? 'timeline-inverted' : ''; // Alternar lados
                        html += `
                            <div class="timeline-item ${invertedClass}">
                                <div class="timeline-badge ${typeBadge}"></div>
                                <div class="timeline-panel glass-card p-3 mb-3">
                                    <div class="timeline-heading">
                                        <h6 class="timeline-title text-white">${maint.tipo_mantenimiento.toUpperCase()} - ${maint.fecha_mantenimiento}</h6>
                                    </div>
                                    <div class="timeline-body">
                                        <p class="text-white-50 mb-1"><strong>Razón:</strong> ${maint.razon}</p>
                                        <p class="text-white-50"><strong>Diagnóstico/Corrección:</strong> ${maint.diagnostico_correccion || 'N/A'}</p>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    html += '</div>';
                    historyContent.innerHTML = html;
                } else {
                    historyContent.innerHTML = '<div class="text-center text-white-50 py-5"><i class="fas fa-info-circle me-2"></i> Este dispositivo no registra mantenimientos previos.</div>';
                }
            } catch (error) {
                console.error('Error fetching maintenance history:', error);
                historyContent.innerHTML = '<div class="text-center text-danger py-5"><i class="fas fa-exclamation-triangle me-2"></i> Error al cargar el historial.</div>';
            }
        });
    });
</script>

<?php include_once "includes/footer.php";

ob_end_flush();
?>
