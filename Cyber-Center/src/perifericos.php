<?php
require_once __DIR__ . "/../conexion.php";
global $conexion;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_brand') {
    $brand = trim($_POST['brand_name'] ?? '');
    header('Content-Type: application/json');
    if ($brand === '') {
        echo json_encode(['success' => false, 'message' => 'Nombre de marca vacío.']);
        exit;
    }
    $chk = $conexion->prepare("SELECT COUNT(*) FROM marca WHERE nombremarca = ?");
    if ($chk) {
        $chk->bind_param('s', $brand);
        $chk->execute();
        $chk->bind_result($cntm);
        $chk->fetch();
        $chk->close();
        if ($cntm > 0) { 
            echo json_encode(['success' => false, 'message' => 'La marca ya está registrada.']); 
            exit; 
        }
    }
    $insm = $conexion->prepare("INSERT INTO marca (nombremarca) VALUES (?)");
    if ($insm) {
        $insm->bind_param('s', $brand);
        if ($insm->execute()) { 
            $insm->close();
            echo json_encode(['success' => true, 'message' => 'Marca registrada con éxito.']); 
            exit; 
        } else { 
            $insm->close();
            echo json_encode(['success' => false, 'message' => 'Error al insertar marca.']); 
            exit; 
        }
    }
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor.']); 
    exit;
}

include_once "includes/header.php";

$mensaje = '';

$nombre = $_SESSION['nombre'] ?? 'Usuario';
$ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$fecha_hora = date('Y-m-d H:i:s');
$sector_acceso = 'Periféricos';
$accion_acceso = 'Acceso a la sección de gestión de periféricos';

$hstmt = $conexion->prepare("INSERT INTO historial (usuario, ip, fyh, sector, acciones) VALUES (?, ?, ?, ?, ?)");
if ($hstmt) {
    $hstmt->bind_param('sssss', $nombre, $ip, $fecha_hora, $sector_acceso, $accion_acceso);
    $hstmt->execute();
    $hstmt->close();
}

$res_marcas = mysqli_query($conexion, "SELECT * FROM marca ORDER BY nombremarca ASC");
$marcas_list = [];
while($m = mysqli_fetch_assoc($res_marcas)) $marcas_list[] = $m;

//Crear Periférico
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_periferico') {
    $tipo_id = intval($_POST['tipo_periferico_id'] ?? 0);
    $computadora_id = (isset($_POST['computadora_id']) && $_POST['computadora_id'] === '') ? null : intval($_POST['computadora_id'] ?? 0);
    $codigo_bien = trim($_POST['codigo_bien_nacional'] ?? '');
    $serial = trim($_POST['numero_serial_fabrica'] ?? '');
    $marca = trim($_POST['marca'] ?? '');
    $modelo = trim($_POST['modelo'] ?? '');
    $color = trim($_POST['color'] ?? '');
    $estado = in_array($_POST['estado_fisico'] ?? '', ['excelente','bueno','regular','dañado']) ? $_POST['estado_fisico'] : 'excelente';

    if ($tipo_id <= 0 || $codigo_bien === '' || $serial === '' || $marca === '' || $modelo === '') {
        $mensaje = 'Complete los campos obligatorios: tipo, código, serial, marca y modelo.';
    } else {
        //Validar Bien Nacional individualmente
        $chkBien = $conexion->prepare("SELECT id FROM perifericos WHERE codigo_bien_nacional = ? LIMIT 1");
        $chkBien->bind_param('s', $codigo_bien);
        $chkBien->execute();
        $chkBien->store_result();
        $existeBien = $chkBien->num_rows > 0;
        $chkBien->close();

        // Validar Serial individualmente
        $chkSerial = $conexion->prepare("SELECT id FROM perifericos WHERE numero_serial_fabrica = ? LIMIT 1");
        $chkSerial->bind_param('s', $serial);
        $chkSerial->execute();
        $chkSerial->store_result();
        $existeSerial = $chkSerial->num_rows > 0;
        $chkSerial->close();

        if ($existeBien) {
            $mensaje = 'Error: El código de Bien Nacional ya está registrado en el sistema.';
        } elseif ($existeSerial) {
            $mensaje = 'Error: El número serial de fábrica ya está registrado en el sistema.';
        } else {
            if ($computadora_id === null || $computadora_id === 0) {
                $insertSql = "INSERT INTO perifericos (computadora_id, tipo_periferico_id, codigo_bien_nacional, numero_serial_fabrica, marca, modelo, color, estado_fisico) VALUES (NULL, ?, ?, ?, ?, ?, ?, ?)";
                $ins = $conexion->prepare($insertSql);
                if ($ins) { $ins->bind_param('issssss', $tipo_id, $codigo_bien, $serial, $marca, $modelo, $color, $estado); }
            } else {
                $insertSql = "INSERT INTO perifericos (computadora_id, tipo_periferico_id, codigo_bien_nacional, numero_serial_fabrica, marca, modelo, color, estado_fisico) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $ins = $conexion->prepare($insertSql);
                if ($ins) { $ins->bind_param('iissssss', $computadora_id, $tipo_id, $codigo_bien, $serial, $marca, $modelo, $color, $estado); }
            }

            if (isset($ins) && $ins) {
                if ($ins->execute()) {
                    $sector_hist = 'Periféricos';
                    $accion_hist = "Registró periférico: $codigo_bien / $serial";
                    $h = $conexion->prepare("INSERT INTO historial (usuario, ip, fyh, sector, acciones) VALUES (?, ?, ?, ?, ?)");
                    if ($h) { $h->bind_param('sssss', $nombre, $ip, $fecha_hora, $sector_hist, $accion_hist); $h->execute(); $h->close(); }
                    echo "<script>window.location.href='perifericos.php';</script>";
                    exit;
                } else { $mensaje = 'Error al insertar periférico: ' . $conexion->error; }
                $ins->close();
            }
        }
    }
}

// Actualizar Periférico
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_periferico') {
    $id = intval($_POST['id'] ?? 0);
    $tipo_id = intval($_POST['tipo_periferico_id'] ?? 0);
    $computadora_id = (isset($_POST['computadora_id']) && $_POST['computadora_id'] === '') ? null : intval($_POST['computadora_id'] ?? 0);
    $codigo_bien = trim($_POST['codigo_bien_nacional'] ?? '');
    $serial = trim($_POST['numero_serial_fabrica'] ?? '');
    $marca = trim($_POST['marca'] ?? '');
    $modelo = trim($_POST['modelo'] ?? '');
    $color = trim($_POST['color'] ?? '');
    $estado = in_array($_POST['estado_fisico'] ?? '', ['excelente','bueno','regular','dañado']) ? $_POST['estado_fisico'] : 'excelente';

    if ($id <= 0 || $tipo_id <= 0 || $codigo_bien === '' || $serial === '' || $marca === '' || $modelo === '') {
        $mensaje = 'Complete los campos obligatorios para actualizar.';
    } else {
        $chkBien = $conexion->prepare("SELECT id FROM perifericos WHERE codigo_bien_nacional = ? AND id != ? LIMIT 1");
        $chkBien->bind_param('si', $codigo_bien, $id);
        $chkBien->execute();
        $chkBien->store_result();
        $existeBien = $chkBien->num_rows > 0;
        $chkBien->close();

        $chkSerial = $conexion->prepare("SELECT id FROM perifericos WHERE numero_serial_fabrica = ? AND id != ? LIMIT 1");
        $chkSerial->bind_param('si', $serial, $id);
        $chkSerial->execute();
        $chkSerial->store_result();
        $existeSerial = $chkSerial->num_rows > 0;
        $chkSerial->close();

        if ($existeBien) {
            $mensaje = 'Error: El código de Bien Nacional ya pertenece a otro periférico registrado.';
        } elseif ($existeSerial) {
            $mensaje = 'Error: El número serial ya pertenece a otro periférico registrado.';
        } else {
            if ($computadora_id === null || $computadora_id === 0) {
                $updateSql = "UPDATE perifericos SET computadora_id = NULL, tipo_periferico_id = ?, codigo_bien_nacional = ?, numero_serial_fabrica = ?, marca = ?, modelo = ?, color = ?, estado_fisico = ? WHERE id = ?";
                $upd = $conexion->prepare($updateSql);
                if ($upd) { $upd->bind_param('issssssi', $tipo_id, $codigo_bien, $serial, $marca, $modelo, $color, $estado, $id); }
            } else {
                $updateSql = "UPDATE perifericos SET computadora_id = ?, tipo_periferico_id = ?, codigo_bien_nacional = ?, numero_serial_fabrica = ?, marca = ?, modelo = ?, color = ?, estado_fisico = ? WHERE id = ?";
                $upd = $conexion->prepare($updateSql);
                if ($upd) { $upd->bind_param('iissssssi', $computadora_id, $tipo_id, $codigo_bien, $serial, $marca, $modelo, $color, $estado, $id); }
            }

            if (isset($upd) && $upd) {
                if ($upd->execute()) {
                    $sector_hist = 'Periféricos';
                    $accion_hist = "Modificó periférico ID $id: $codigo_bien / $serial";
                    $h = $conexion->prepare("INSERT INTO historial (usuario, ip, fyh, sector, acciones) VALUES (?, ?, ?, ?, ?)");
                    if ($h) { $h->bind_param('sssss', $nombre, $ip, $fecha_hora, $sector_hist, $accion_hist); $h->execute(); $h->close(); }
                    echo "<script>window.location.href='perifericos.php';</script>";
                    exit;
                } else { $mensaje = 'Error al actualizar periférico: ' . $conexion->error; }
                $upd->close();
            }
        }
    }
}

// --- LÓGICA DE PAGINACIÓN ---
$por_pagina = 10;
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$offset = ($pagina_actual - 1) * $por_pagina;

// 1. Conteo total de registros
$total_res = mysqli_query($conexion, "SELECT COUNT(*) as total FROM perifericos");
$total_registros = mysqli_fetch_assoc($total_res)['total'];
$total_paginas = ceil($total_registros / $por_pagina);

// 2. Consulta principal con LIMIT y OFFSET
$query = "SELECT p.*, tp.nombre_componente, comp.numero_puesto, comp.codigo_bien_nacional, comp.direccion_ip
          FROM perifericos p
          LEFT JOIN tipos_periferico tp ON tp.id = p.tipo_periferico_id
          LEFT JOIN computadoras comp ON comp.id = p.computadora_id
          ORDER BY p.fecha_registro DESC LIMIT ? OFFSET ?";

$stmt_query = mysqli_prepare($conexion, $query);
mysqli_stmt_bind_param($stmt_query, "ii", $por_pagina, $offset);
mysqli_stmt_execute($stmt_query);
$resultado = mysqli_stmt_get_result($stmt_query);

$tiposRes = mysqli_query($conexion, "SELECT id, nombre_componente FROM tipos_periferico ORDER BY nombre_componente ASC");
$computadorasRes = mysqli_query($conexion, "SELECT id, numero_puesto, direccion_ip FROM computadoras ORDER BY numero_puesto ASC");
$marcasRes = mysqli_query($conexion, "SELECT nombremarca FROM marca ORDER BY nombremarca ASC");
?>

<style>
    .table { color: var(--text-light); margin-bottom: 0; border-collapse: separate; border-spacing: 0; }
    .table thead th { background: rgba(0, 0, 0, 0.4); border-bottom: 1px solid var(--glass-border); color: var(--primary-light); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1.2px; padding: 1.25rem 1rem; font-weight: 800; }
    .table td { vertical-align: middle; border-bottom: 1px solid rgba(255, 255, 255, 0.05); padding: 1.25rem 1rem; background: transparent; }
    .table tbody tr { transition: all 0.3s ease; }
    .table tbody tr:hover { background: rgba(0, 0, 0, 0.3) !important; }
    .status-badge { border-radius: 20px; padding: 0.45rem 0.85rem; font-size: 0.65rem; text-transform: uppercase; font-weight: 800; letter-spacing: 0.5px; }
    .glass-card { padding: 0 !important; overflow: hidden; border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 24px; }
    .table-responsive { border-radius: 24px; }
    .table tbody td { color: #ffffff !important; }
    .text-white-50 { color: rgba(255, 255, 255, 0.85) !important; }

    /* Customización de selects y controles para coherencia con otros archivos */
    .glass-modal .form-select, .glass-modal .form-control {
        background-color: rgba(15, 23, 42, 0.8) !important;
        border: 1px solid rgba(255, 255, 255, 0.15) !important;
        color: #fff !important;
    }
    .glass-modal .form-select {
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23ffffff' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") !important;
    }
    .glass-modal option { background-color: #1a202c; }

    /* Paginación Coherente */
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
    .pagination .page-item.disabled .page-link { background: rgba(0, 0, 0, 0.3) !important; color: rgba(255, 255, 255, 0.2) !important; }

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

<div class="container main-content pb-5">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="fw-bold mb-0 text-white">Gestión de Periféricos</h2>
            <p class="text-white-50">Control de hardware y componentes externos</p>
            <?php if (!empty($mensaje)): ?>
                <div class="alert alert-danger mt-2 py-2 px-3 fw-bold"><?php echo htmlspecialchars($mensaje); ?></div>
            <?php endif; ?>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <div class="search-wrapper">
                <i class="fas fa-search"></i>
                <input type="text" id="tableSearch" class="form-control" placeholder="Buscar periférico...">
            </div>
            <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#perifericoModal"><i class="fas fa-plus me-1"></i> Nuevo periférico</button>
            <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#marcaModal"><i class="fas fa-building me-1"></i> Nueva marca</button>
        </div>
    </div>

    <!-- Modal REGISTRAR Periférico -->
    <div class="modal fade" id="perifericoModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content glass-modal text-white">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Registrar Periférico</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="post" id="perifericoForm" class="row g-3">
                        <input type="hidden" name="action" value="create_periferico">
                        <div class="col-md-6">
                            <label class="form-label text-white-50 small fw-bold">TIPO</label>
                            <select name="tipo_periferico_id" class="form-select" required>
                                <option value="">-- Seleccione --</option>
                                <?php if ($tiposRes): mysqli_data_seek($tiposRes, 0); while ($t = mysqli_fetch_assoc($tiposRes)): ?>
                                    <option value="<?php echo intval($t['id']); ?>"><?php echo htmlspecialchars($t['nombre_componente']); ?></option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-white-50 small fw-bold">EQUIPO (OPCIONAL)</label>
                            <select name="computadora_id" class="form-select">
                                <option value="">-- Ninguno --</option>
                                <?php if ($computadorasRes): mysqli_data_seek($computadorasRes, 0); while ($c = mysqli_fetch_assoc($computadorasRes)): ?>
                                    <option value="<?php echo intval($c['id']); ?>"><?php echo 'PC-'.str_pad(intval($c['numero_puesto']),2,'0',STR_PAD_LEFT) . ' ' . htmlspecialchars($c['direccion_ip'] ?? ''); ?></option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-white-50 small fw-bold">CÓDIGO BIEN</label>
                            <input name="codigo_bien_nacional" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-white-50 small fw-bold">SERIAL</label>
                            <input name="numero_serial_fabrica" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-white-50 small fw-bold">MARCA</label>
                            <input name="marca" id="perifericoMarcaInput" class="form-control" list="marcasDataList" required autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-white-50 small fw-bold">MODELO</label>
                            <input name="modelo" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-white-50 small fw-bold">COLOR</label>
                            <input name="color" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-white-50 small fw-bold">ESTADO</label>
                            <select name="estado_fisico" class="form-select">
                                <option value="excelente">Excelente</option>
                                <option value="bueno">Bueno</option>
                                <option value="regular">Regular</option>
                                <option value="dañado">Dañado</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button class="btn btn-primary w-100 fw-bold" type="submit">GUARDAR PERIFÉRICO</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal EDITAR Periférico -->
    <div class="modal fade" id="editPerifericoModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content glass-modal text-white">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Editar Periférico</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="post" id="editPerifericoForm" class="row g-3">
                        <input type="hidden" name="action" value="update_periferico">
                        <input type="hidden" name="id" id="edit_id">
                        
                        <div class="col-md-6">
                            <label class="form-label text-white-50 small fw-bold">TIPO</label>
                            <select name="tipo_periferico_id" id="edit_tipo" class="form-select" required>
                                <option value="">-- Seleccione --</option>
                                <?php if ($tiposRes): mysqli_data_seek($tiposRes, 0); while ($t = mysqli_fetch_assoc($tiposRes)): ?>
                                    <option value="<?php echo intval($t['id']); ?>"><?php echo htmlspecialchars($t['nombre_componente']); ?></option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-white-50 small fw-bold">EQUIPO (OPCIONAL)</label>
                            <select name="computadora_id" id="edit_computadora" class="form-select">
                                <option value="">-- Ninguno --</option>
                                <?php if ($computadorasRes): mysqli_data_seek($computadorasRes, 0); while ($c = mysqli_fetch_assoc($computadorasRes)): ?>
                                    <option value="<?php echo intval($c['id']); ?>"><?php echo 'PC-'.str_pad(intval($c['numero_puesto']),2,'0',STR_PAD_LEFT) . ' ' . htmlspecialchars($c['direccion_ip'] ?? ''); ?></option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-white-50 small fw-bold">CÓDIGO BIEN</label>
                            <input name="codigo_bien_nacional" id="edit_codigo" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-white-50 small fw-bold">SERIAL</label>
                            <input name="numero_serial_fabrica" id="edit_serial" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-white-50 small fw-bold">MARCA</label>
                            <input name="marca" id="edit_marca" class="form-control" list="marcasDataList" required autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-white-50 small fw-bold">MODELO</label>
                            <input name="modelo" id="edit_modelo" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-white-50 small fw-bold">COLOR</label>
                            <input name="color" id="edit_color" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-white-50 small fw-bold">ESTADO</label>
                            <select name="estado_fisico" id="edit_estado" class="form-select">
                                <option value="excelente">Excelente</option>
                                <option value="bueno">Bueno</option>
                                <option value="regular">Regular</option>
                                <option value="dañado">Dañado</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button class="btn btn-warning w-100 text-dark fw-bold" type="submit">ACTUALIZAR CAMBIOS</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Datalist global para marcas -->
    <datalist id="marcasDataList">
        <?php if ($marcasRes): mysqli_data_seek($marcasRes, 0); while ($m = mysqli_fetch_assoc($marcasRes)): ?>
            <option value="<?php echo htmlspecialchars($m['nombremarca']); ?>"></option>
        <?php endwhile; endif; ?>
    </datalist>

    <!-- Modal Marca -->
    <div class="modal fade" id="marcaModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content glass-modal text-white">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-building me-2"></i>Registrar Marca</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="marcaForm" onsubmit="return false;">
                        <div class="mb-3">
                            <label class="form-label text-white-50 small fw-bold">NOMBRE DE MARCA</label>
                            <input id="brandName" class="form-control" required autocomplete="off">
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="button" class="btn btn-outline-light me-2" data-bs-dismiss="modal">Cerrar</button>
                            <button type="button" id="saveBrandBtn" class="btn btn-primary">Guardar</button>
                        </div>
                        <div id="brandFeedback" class="mt-2"></div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla de resultados -->
    <div class="glass-card">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>TIPO</th>
                        <th>EQUIPO</th>
                        <th>BIEN NACIONAL</th>
                        <th>SERIAL</th>
                        <th>MARCA</th>
                        <th>MODELO</th>
                        <th>ESTADO</th>
                        <th class="text-end">ACCIONES</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($resultado) === 0): ?>
                        <tr><td colspan="9" class="text-center text-white-50 py-5">
                            <i class="fas fa-plug fa-3x mb-3 d-block opacity-25"></i>
                            No hay periféricos registrados.
                        </td></tr>
                    <?php else: ?>
                        <?php while ($row = mysqli_fetch_assoc($resultado)): ?>
                            <tr>
                                <td class="fw-bold" style="color: var(--primary-light);">#<?php echo intval($row['id']); ?></td>
                                <td class="fw-semibold"><?php echo htmlspecialchars($row['nombre_componente'] ?? 'Sin tipo'); ?></td>
                                <td>
                                    <?php if(!empty($row['numero_puesto'])): ?>
                                        <span class="badge bg-dark border border-secondary text-info">PC-<?php echo str_pad($row['numero_puesto'], 2, '0', STR_PAD_LEFT); ?></span>
                                    <?php else: ?>
                                        <span class="text-white-50 small"><i>No asignado</i></span>
                                    <?php endif; ?>
                                </td>
                                <td><code style="color: #00f2ff; font-size: 0.9rem; font-weight: 700;"><?php echo htmlspecialchars($row['codigo_bien_nacional'] ?? '-'); ?></code></td>
                                <td><?php echo htmlspecialchars($row['numero_serial_fabrica'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($row['marca'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($row['modelo'] ?? '-'); ?></td>
                                <td>
                                    <span class="badge status-badge <?php echo (($row['estado_fisico'] ?? '') === 'dañado' ? 'bg-danger' : (($row['estado_fisico'] ?? '') === 'regular' ? 'bg-warning text-dark' : 'bg-success')); ?>">
                                        <?php echo htmlspecialchars($row['estado_fisico'] ?? 'excelente'); ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-light rounded-pill btn-edit-periferico" 
                                            data-id="<?php echo $row['id']; ?>"
                                            data-tipo="<?php echo $row['tipo_periferico_id']; ?>"
                                            data-computadora="<?php echo $row['computadora_id'] ?? ''; ?>"
                                            data-codigo="<?php echo htmlspecialchars($row['codigo_bien_nacional'] ?? ''); ?>"
                                            data-serial="<?php echo htmlspecialchars($row['numero_serial_fabrica'] ?? ''); ?>"
                                            data-marca="<?php echo htmlspecialchars($row['marca'] ?? ''); ?>"
                                            data-modelo="<?php echo htmlspecialchars($row['modelo'] ?? ''); ?>"
                                            data-color="<?php echo htmlspecialchars($row['color'] ?? ''); ?>"
                                            data-estado="<?php echo htmlspecialchars($row['estado_fisico'] ?? 'excelente'); ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Paginación -->
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

<script>
document.addEventListener('DOMContentLoaded', function(){
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

    const editButtons = document.querySelectorAll('.btn-edit-periferico');
    editButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('edit_id').value = this.getAttribute('data-id');
            document.getElementById('edit_tipo').value = this.getAttribute('data-tipo');
            document.getElementById('edit_computadora').value = this.getAttribute('data-computadora');
            document.getElementById('edit_codigo').value = this.getAttribute('data-codigo');
            document.getElementById('edit_serial').value = this.getAttribute('data-serial');
            document.getElementById('edit_marca').value = this.getAttribute('data-marca');
            document.getElementById('edit_modelo').value = this.getAttribute('data-modelo');
            document.getElementById('edit_color').value = this.getAttribute('data-color');
            document.getElementById('edit_estado').value = this.getAttribute('data-estado');

            const editModal = new bootstrap.Modal(document.getElementById('editPerifericoModal'));
            editModal.show();
        });
    });

    // Manejo de marcas
    const saveBtn = document.getElementById('saveBrandBtn');
    const brandInput = document.getElementById('brandName');
    const fb = document.getElementById('brandFeedback');
    const marcasDatalist = document.getElementById('marcasDataList');
    const perifericoMarcaInput = document.getElementById('perifericoMarcaInput');

    if (saveBtn && brandInput && fb) {
        saveBtn.addEventListener('click', function(e){
            e.preventDefault();
            const name = brandInput.value.trim();
            fb.innerHTML = '';

            if (!name) { 
                fb.innerHTML = '<div class="text-danger">El nombre no puede estar vacío.</div>'; 
                return; 
            }

            saveBtn.disabled = true;
            saveBtn.innerText = 'Guardando...';

            fetch(window.location.href, {
                method: 'POST',
                headers: {'Content-Type':'application/x-www-form-urlencoded'},
                body: 'action=create_brand&brand_name=' + encodeURIComponent(name)
            })
            .then(r => r.json())
            .then(j => {
                if (j.success) {
                    fb.innerHTML = '<div class="text-success">' + j.message + '</div>';
                    
                    if(marcasDatalist) {
                        const opt = document.createElement('option');
                        opt.value = name;
                        marcasDatalist.appendChild(opt);
                    }
                    if(perifericoMarcaInput) {
                        perifericoMarcaInput.value = name;
                    }

                    setTimeout(() => {
                        brandInput.value = '';
                        fb.innerHTML = '';
                        saveBtn.disabled = false;
                        saveBtn.innerText = 'Guardar';
                        
                        const modalEl = document.getElementById('marcaModal');
                        const modalInstance = bootstrap.Modal.getInstance(modalEl);
                        if(modalInstance) modalInstance.hide();
                    }, 1000);
                } else {
                    fb.innerHTML = '<div class="text-danger">' + j.message + '</div>';
                    saveBtn.disabled = false;
                    saveBtn.innerText = 'Guardar';
                }
            })
            .catch(e => { 
                fb.innerHTML = '<div class="text-danger">Error al procesar la marca.</div>'; 
                saveBtn.disabled = false;
                saveBtn.innerText = 'Guardar';
            });
        });
    }
});
</script>

<?php include_once "includes/footer.php"; ?>
