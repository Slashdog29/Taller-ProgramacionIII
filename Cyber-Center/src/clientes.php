<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . "/../conexion.php";

// Habilitar el reporte de errores de mysqli para que lance excepciones (útil en PHP 8+)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

date_default_timezone_set('America/Caracas');
$conexion->query("SET time_zone = '-04:00'");

global $conexion;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    header('Content-Type: application/json');
    // Limpiamos cualquier salida previa (como espacios accidentales o warnings)
    if (ob_get_length()) ob_clean();

    try {

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

    if ($action === 'assign_device') {
        $id_cliente = intval($_POST['id_cliente'] ?? 0);
        $id_computadora = intval($_POST['id_computadora'] ?? 0);
        $duracion = intval($_POST['duracion'] ?? 0);

        // Intentamos capturar el ID desde POST o desde las diferentes claves de sesión posibles
        $usuario_operador_id = 0;
        if (isset($_POST['usuario_operador_id']) && intval($_POST['usuario_operador_id']) > 0) {
            $usuario_operador_id = intval($_POST['usuario_operador_id']);
        } else {
            $usuario_operador_id = intval($_SESSION['id'] ?? $_SESSION['id_usuario'] ?? $_SESSION['usuario_id'] ?? 0);
        }

        if ($id_cliente <= 0 || $id_computadora <= 0 || $duracion <= 0) {
            echo json_encode(['success' => false, 'message' => 'Datos incompletos para la asignación.']);
            exit;
        }

        // 1. Verificar que el cliente no esté suspendido
        $sql_estado = "SELECT estado_cuenta FROM clientes WHERE id = ? LIMIT 1";
        $estadoStmt = mysqli_prepare($conexion, $sql_estado);
        if ($estadoStmt) {
            mysqli_stmt_bind_param($estadoStmt, 'i', $id_cliente);
            mysqli_stmt_execute($estadoStmt);
            mysqli_stmt_bind_result($estadoStmt, $estado_cuenta);
            if (mysqli_stmt_fetch($estadoStmt)) {
                if ($estado_cuenta === 'suspendido') {
                    mysqli_stmt_close($estadoStmt);
                    echo json_encode(['success' => false, 'message' => 'El cliente está suspendido y no puede usar las máquinas.']);
                    exit;
                }
            } else {
                mysqli_stmt_close($estadoStmt);
                echo json_encode(['success' => false, 'message' => 'Cliente no encontrado.']);
                exit;
            }
            mysqli_stmt_close($estadoStmt);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al verificar estado del cliente: ' . mysqli_error($conexion)]);
            exit;
        }

        // 2. Verificar que el cliente no tenga sesión activa hoy
        $validarSql = "SELECT COUNT(*) AS cnt FROM sesiones WHERE cliente_id = ? AND DATE(hora_inicio) = CURDATE()";
        $validarStmt = mysqli_prepare($conexion, $validarSql);
        if ($validarStmt) {
            mysqli_stmt_bind_param($validarStmt, 'i', $id_cliente);
            mysqli_stmt_execute($validarStmt);
            mysqli_stmt_bind_result($validarStmt, $cnt);
            mysqli_stmt_fetch($validarStmt);
            mysqli_stmt_close($validarStmt);
            if ($cnt > 0) {
                echo json_encode(['success' => false, 'message' => 'El cliente ya tiene una sesión registrada hoy. No se permite reingreso.']);
                exit;
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al validar sesión existente: ' . mysqli_error($conexion)]);
            exit;
        }

        // 3. Verificar que la computadora siga disponible (evita race condition)
        $checkPc = "SELECT id FROM computadoras WHERE id = ? AND estado_operativo = 'disponible' LIMIT 1";
        $checkStmt = mysqli_prepare($conexion, $checkPc);
        if ($checkStmt) {
            mysqli_stmt_bind_param($checkStmt, 'i', $id_computadora);
            mysqli_stmt_execute($checkStmt);
            mysqli_stmt_store_result($checkStmt);
            if (mysqli_stmt_num_rows($checkStmt) == 0) {
                mysqli_stmt_close($checkStmt);
                echo json_encode(['success' => false, 'message' => 'La computadora seleccionada ya no está disponible.']);
                exit;
            }
            mysqli_stmt_close($checkStmt);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al verificar disponibilidad de la PC: ' . mysqli_error($conexion)]);
            exit;
        }

        // 4. Obtener tarifa del cliente
        $tarifa = 0.00;
        $tipoSql = "SELECT COALESCE(t.tarifa_por_hora, 0) AS tarifa, COALESCE(t.exento_pago, 0) AS exento
                    FROM clientes c
                    LEFT JOIN tipos_cliente t ON c.tipo_cliente_id = t.id
                    WHERE c.id = ? LIMIT 1";
        $tipoStmt = mysqli_prepare($conexion, $tipoSql);
        if ($tipoStmt) {
            mysqli_stmt_bind_param($tipoStmt, 'i', $id_cliente);
            mysqli_stmt_execute($tipoStmt);
            mysqli_stmt_bind_result($tipoStmt, $tarifa_val, $exento_val);
            if (mysqli_stmt_fetch($tipoStmt)) {
                $tarifa = floatval($tarifa_val);
                if (intval($exento_val) === 1) {
                    $tarifa = 0.00;
                }
            }
            mysqli_stmt_close($tipoStmt);
        }

        // VALIDACIÓN DE INTEGRIDAD: Verificar que el operador existe en la base de datos
        $checkUser = "SELECT id FROM usuarios WHERE id = ? AND activo = 1 LIMIT 1";
        $checkUserStmt = mysqli_prepare($conexion, $checkUser);
        $userExists = false;
        if ($checkUserStmt) {
            mysqli_stmt_bind_param($checkUserStmt, 'i', $usuario_operador_id);
            mysqli_stmt_execute($checkUserStmt);
            mysqli_stmt_store_result($checkUserStmt);
            if (mysqli_stmt_num_rows($checkUserStmt) > 0) {
                $userExists = true;
            }
            mysqli_stmt_close($checkUserStmt);
        }

        if (!$userExists) {
            echo json_encode(['success' => false, 'message' => 'Error: El operador (ID: '.$usuario_operador_id.') no es válido o no está activo.']);
            exit;
        }

        // 5. Insertar sesión
        $insertSql = "INSERT INTO sesiones (cliente_id, computadora_id, usuario_operador_id, hora_inicio, monto_tarifa_aplicada, estado_transaccion)
                      VALUES (?, ?, ?, NOW(), ?, 'en_curso')";
        $insertStmt = mysqli_prepare($conexion, $insertSql);
        if ($insertStmt) {
            mysqli_stmt_bind_param($insertStmt, 'iiid', $id_cliente, $id_computadora, $usuario_operador_id, $tarifa);
            if (mysqli_stmt_execute($insertStmt)) {
                // 6. Actualizar estado de la computadora a ocupado
                $updateCompSql = "UPDATE computadoras SET estado_operativo = 'ocupado' WHERE id = ?";
                $updateCompStmt = mysqli_prepare($conexion, $updateCompSql);
                if ($updateCompStmt) {
                    mysqli_stmt_bind_param($updateCompStmt, 'i', $id_computadora);
                    mysqli_stmt_execute($updateCompStmt);
                    mysqli_stmt_close($updateCompStmt);
                }

                // 7. Registrar en historial
                $accion_historial = "Asignó dispositivo al cliente ID {$id_cliente} en computadora ID {$id_computadora} por {$duracion} minutos";
                $histStmt = $conexion->prepare("INSERT INTO historial (usuario, ip, fyh, sector, acciones) VALUES (?, ?, ?, ?, ?)");
                if ($histStmt) {
                    $histStmt->bind_param('sssss', $usuario_sesion, $ip, $fecha_hora, $sector, $accion_historial);
                    $histStmt->execute();
                    $histStmt->close();
                }

                echo json_encode(['success' => true, 'message' => 'Dispositivo asignado correctamente.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al registrar la sesión: ' . mysqli_stmt_error($insertStmt)]);
            }
            mysqli_stmt_close($insertStmt);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al preparar la inserción de sesión: ' . mysqli_error($conexion)]);
        }
        exit;
    }

    if ($action === 'update') {
        $id = intval($_POST['id'] ?? 0);
        $nombre_cliente = trim($_POST['nombre'] ?? '');
        $apellido = trim($_POST['apellido'] ?? '');
        $cedula = trim($_POST['cedula_o_codigo'] ?? '');
        $correo = trim($_POST['correo'] ?? '');
        $tipo_cliente_id = intval($_POST['tipo_cliente_id'] ?? 0);

        if ($id <= 0 || empty($nombre_cliente) || empty($apellido) || empty($cedula) || empty($correo) || $tipo_cliente_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Datos insuficientes para actualizar.']);
            exit;
        }

        $stmt = $conexion->prepare("UPDATE clientes SET nombre = ?, apellido = ?, cedula_o_codigo = ?, correo = ?, tipo_cliente_id = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('ssssii', $nombre_cliente, $apellido, $cedula, $correo, $tipo_cliente_id, $id);
            if ($stmt->execute()) {
                $accion_historial = "Modificó los datos del cliente ID: " . $id;
                $stmt_h = $conexion->prepare("INSERT INTO historial (usuario, ip, fyh, sector, acciones) VALUES (?, ?, ?, ?, ?)");
                if ($stmt_h) {
                    $stmt_h->bind_param('sssss', $usuario_sesion, $ip, $fecha_hora, $sector, $accion_historial);
                    $stmt_h->execute();
                    $stmt_h->close();
                }
                echo json_encode(['success' => true, 'message' => 'Cliente actualizado con éxito.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al actualizar: ' . $stmt->error]);
            }
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al preparar la consulta SQL: ' . $conexion->error]);
        }
        exit;
    }

    if ($action === 'deactivate') {
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID de cliente inválido.']);
            exit;
        }

        $stmt = $conexion->prepare("UPDATE clientes SET estado_cuenta = 'suspendido' WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $id);
            if ($stmt->execute()) {
                $accion_historial = "Desactivó al cliente ID: " . $id;
                $stmt_h = $conexion->prepare("INSERT INTO historial (usuario, ip, fyh, sector, acciones) VALUES (?, ?, ?, ?, ?)");
                if ($stmt_h) {
                    $stmt_h->bind_param('sssss', $usuario_sesion, $ip, $fecha_hora, $sector, $accion_historial);
                    $stmt_h->execute();
                    $stmt_h->close();
                }
                echo json_encode(['success' => true, 'message' => 'Cliente desactivado correctamente.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al desactivar el cliente: ' . $stmt->error]);
            }
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al preparar la consulta SQL: ' . $conexion->error]);
        }
        exit;
    }

    if ($action === 'activate') {
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID de cliente inválido.']);
            exit;
        }

        $stmt = $conexion->prepare("UPDATE clientes SET estado_cuenta = 'activo' WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $id);
            if ($stmt->execute()) {
                $accion_historial = "Activó al cliente ID: " . $id;
                $stmt_h = $conexion->prepare("INSERT INTO historial (usuario, ip, fyh, sector, acciones) VALUES (?, ?, ?, ?, ?)");
                if ($stmt_h) {
                    $stmt_h->bind_param('sssss', $usuario_sesion, $ip, $fecha_hora, $sector, $accion_historial);
                    $stmt_h->execute();
                    $stmt_h->close();
                }
                echo json_encode(['success' => true, 'message' => 'Cliente activado correctamente.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al activar el cliente: ' . $stmt->error]);
            }
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al preparar la consulta SQL: ' . $conexion->error]);
        }
        exit;
    }

    if ($action === 'finish_session') {
        $id_sesion = intval($_POST['id_sesion'] ?? 0);
        $id_computadora = intval($_POST['id_computadora'] ?? 0);

        if ($id_sesion <= 0 || $id_computadora <= 0) {
            echo json_encode(['success' => false, 'message' => 'Información de sesión insuficiente.']);
            exit;
        }

        // Iniciamos transacción para asegurar consistencia
        mysqli_begin_transaction($conexion);
        try {
            // 1. Finalizar la sesión en la base de datos
            $sql_sesion = "UPDATE sesiones SET estado_transaccion = 'finalizado', hora_fin = NOW() WHERE id = ?";
            $stmt_sesion = mysqli_prepare($conexion, $sql_sesion);
            mysqli_stmt_bind_param($stmt_sesion, 'i', $id_sesion);
            mysqli_stmt_execute($stmt_sesion);
            mysqli_stmt_close($stmt_sesion);

            // 2. Liberar la computadora para que aparezca como disponible
            $sql_comp = "UPDATE computadoras SET estado_operativo = 'disponible' WHERE id = ?";
            $stmt_comp = mysqli_prepare($conexion, $sql_comp);
            mysqli_stmt_bind_param($stmt_comp, 'i', $id_computadora);
            mysqli_stmt_execute($stmt_comp);
            mysqli_stmt_close($stmt_comp);

            // 3. Registrar la acción en el historial
            $accion_historial = "Finalizó manualmente la sesión ID: $id_sesion";
            $stmt_h = mysqli_prepare($conexion, "INSERT INTO historial (usuario, ip, fyh, sector, acciones) VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt_h, 'sssss', $usuario_sesion, $ip, $fecha_hora, $sector, $accion_historial);
            mysqli_stmt_execute($stmt_h);
            mysqli_stmt_close($stmt_h);

            mysqli_commit($conexion);
            echo json_encode(['success' => true, 'message' => 'Sesión finalizada y equipo liberado correctamente.']);
        } catch (Exception $e) {
            mysqli_rollback($conexion);
            echo json_encode(['success' => false, 'message' => 'Error al finalizar la sesión: ' . $e->getMessage()]);
        }
        exit;
    }

    // ==================================================
    // NUEVA ACCIÓN: Actualizar tarifa de un tipo de cliente
    // ==================================================
    if ($action === 'update_tarifa') {
        $tipo_id = intval($_POST['tipo_id'] ?? 0);
        $nueva_tarifa = floatval($_POST['tarifa_por_hora'] ?? 0);

        if ($tipo_id <= 0 || $nueva_tarifa < 0) {
            echo json_encode(['success' => false, 'message' => 'Datos inválidos para actualizar la tarifa.']);
            exit;
        }

        $stmt = $conexion->prepare("UPDATE tipos_cliente SET tarifa_por_hora = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('di', $nueva_tarifa, $tipo_id);
            if ($stmt->execute()) {
                // Registrar en historial
                $accion_historial = "Actualizó tarifa del tipo de cliente ID {$tipo_id} a {$nueva_tarifa} USD/hora";
                $stmt_h = $conexion->prepare("INSERT INTO historial (usuario, ip, fyh, sector, acciones) VALUES (?, ?, ?, ?, ?)");
                if ($stmt_h) {
                    $stmt_h->bind_param('sssss', $usuario_sesion, $ip, $fecha_hora, $sector, $accion_historial);
                    $stmt_h->execute();
                    $stmt_h->close();
                }
                echo json_encode(['success' => true, 'message' => 'Tarifa actualizada correctamente.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al actualizar la tarifa: ' . $stmt->error]);
            }
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al preparar la consulta SQL: ' . $conexion->error]);
        }
        exit;
    }

    // IMPORTANTE: Si es una petición AJAX pero ninguna acción coincidió, devolvemos error y salimos.
    // Esto evita que el servidor devuelva el HTML de la página (header.php) y rompa el JSON.
    echo json_encode(['success' => false, 'message' => 'Acción no reconocida: ' . $action]);
    exit;

    } catch (Throwable $e) {
        // En caso de cualquier error, devolvemos un JSON válido para evitar que el frontend rompa
        echo json_encode(['success' => false, 'message' => 'Error en el servidor: ' . $e->getMessage()]);
        exit;
    }
} else {
    // --- BLOQUE PROTEGIDO: SOLO SE EJECUTA SI NO ES UNA PETICIÓN AJAX ---
    // Esto garantiza que el JSON nunca se ensucie con el HTML del panel

include_once "includes/header.php";

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Registro de actividad en el historial (acceso a la página)
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
$query = "SELECT c.*, COALESCE(t.nombre_rol, 'Sin tipo') AS nombre_rol, COALESCE(t.tarifa_por_hora, 0) AS tarifa_por_hora 
          FROM clientes c 
          LEFT JOIN tipos_cliente t ON c.tipo_cliente_id = t.id 
          ORDER BY c.id ASC";
$resultado = mysqli_query($conexion, $query);

$typeQuery = "SELECT id, nombre_rol FROM tipos_cliente ORDER BY nombre_rol ASC";
$typeResult = mysqli_query($conexion, $typeQuery);

$tiposClientes = [];
if ($typeResult) {
    while ($type = mysqli_fetch_assoc($typeResult)) {
        $tiposClientes[] = $type;
    }
}

// Consulta de computadoras disponibles para el modal de asignación
$computerQuery = "SELECT comp.id, comp.numero_puesto, comp.codigo_bien_nacional, comp.direccion_ip, comp.modelo, m.nombremarca
                  FROM computadoras comp
                  LEFT JOIN marca m ON m.id_marca = comp.marca
                  WHERE comp.estado_operativo = 'disponible'
                  ORDER BY comp.numero_puesto ASC";
$computadorasDisponibles = mysqli_query($conexion, $computerQuery);

// Validación de errores en la consulta
if (!$resultado || $typeResult === false || $computadorasDisponibles === false) {
    $errorMessage = mysqli_error($conexion);
    die("<div class='alert alert-danger'>Error en la consulta SQL: " . $errorMessage . "</div>");
}

// ==================================================
// OBTENER TIPOS DE CLIENTE CON SUS TARIFAS PARA EL MODAL DE TARIFAS
// ==================================================
$tiposTarifasQuery = "SELECT id, nombre_rol, tarifa_por_hora, exento_pago FROM tipos_cliente ORDER BY nombre_rol ASC";
$tiposTarifasResult = mysqli_query($conexion, $tiposTarifasQuery);
$tiposTarifas = [];
if ($tiposTarifasResult) {
    while ($tipo = mysqli_fetch_assoc($tiposTarifasResult)) {
        $tiposTarifas[] = $tipo;
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
    .table tbody td {
        color: #ffffff !important;
    }
    .text-white-50 {
        color: rgba(255, 255, 255, 0.85) !important;
    }
    .text-muted {
        color: rgba(255, 255, 255, 0.75) !important;
    }
    .bg-dark.border-secondary {
        background-color: rgba(45, 55, 72, 0.9) !important;
        border-color: rgba(255, 255, 255, 0.3) !important;
    }
    /* Mejora de selects en modales */
    .glass-modal .form-select {
        background-color: rgba(15, 23, 42, 0.8) !important;
        border: 1px solid rgba(255, 255, 255, 0.15) !important;
        color: #fff !important;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23ffffff' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") !important;
    }
    .glass-modal .form-select option {
        background-color: #1a202c;
    }
    
    /* ========== MEJORAS VISUALES PARA BOTÓN TARIFAS Y MODAL DE TARIFAS ========== */
    .btn-tarifas {
        background: rgba(13, 110, 253, 0.2);
        border: 1px solid rgba(13, 110, 253, 0.5);
        backdrop-filter: blur(5px);
        transition: all 0.3s ease;
        color: #fff;
    }
    .btn-tarifas:hover {
        background: rgba(13, 110, 253, 0.4);
        border-color: #0d6efd;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    }
    /* Mejora del modal de tarifas para efecto glass más definido */
    .glass-modal .modal-content {
        background: rgba(15, 23, 42, 0.95) !important;
        backdrop-filter: blur(12px);
        border-radius: 24px;
        border: 1px solid rgba(255, 255, 255, 0.2);
    }
    /* Badges redondeados para tipos exento/normal */
    .badge-rounded {
        border-radius: 40px;
        padding: 0.4rem 1rem;
        font-weight: 600;
        letter-spacing: 0.3px;
    }
    /* Separadores sutiles en tabla del modal de tarifas */
    .tarifas-table tbody tr {
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    }
    .tarifas-table tbody tr:last-child {
        border-bottom: none;
    }
    /* Botón editar tarifa consistente */
    .btn-outline-primary {
        border-radius: 40px;
        padding: 0.3rem 1rem;
        transition: all 0.2s;
    }
    .btn-outline-primary:hover {
        background: #0d6efd;
        color: white;
        transform: scale(1.02);
    }
</style>

<div class="container main-content pb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0 text-white">Gestión de Clientes</h2>
            <p class="text-white-50">Administración de usuarios y cuentas</p>
        </div>
        <div>
            <!-- Botón Tarifas con estilo glass mejorado -->
            <button class="btn btn-tarifas me-2" data-bs-toggle="modal" data-bs-target="#modalTarifas">
                <i class="fas fa-dollar-sign me-2"></i>Tarifas
            </button>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addClientModal">
                <i class="fas fa-user-plus me-2"></i>Nuevo Cliente
            </button>
        </div>
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
                            switch ($row['estado_cuenta']) {
                                case 'activo':
                                    $badge_class = 'bg-success';
                                    break;
                                case 'suspendido':
                                    $badge_class = 'bg-danger';
                                    break;
                                default:
                                    $badge_class = 'bg-secondary';
                            }
                        ?>
                            <tr data-id="<?= intval($row['id']) ?>" data-nombre="<?= htmlspecialchars($row['nombre']) ?>" data-apellido="<?= htmlspecialchars($row['apellido']) ?>" data-cedula="<?= htmlspecialchars($row['cedula_o_codigo']) ?>" data-correo="<?= htmlspecialchars($row['correo']) ?>" data-tipoid="<?= intval($row['tipo_cliente_id']) ?>" data-tarifa="<?= floatval($row['tarifa_por_hora'] ?? 0) ?>">
                                <td class="fw-bold" style="color: var(--primary-light);">#<?php echo $row['id']; ?></td>
                                <td>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($row['nombre'] . ' ' . $row['apellido']); ?></div>
                                    <small class="text-white-50">Registrado: <?php echo date('d/m/Y', strtotime($row['creado_en'])); ?></small>
                                </td>
                                <td><code style="color: #00f2ff; font-size: 0.9rem; font-weight: 700;"><?php echo htmlspecialchars($row['cedula_o_codigo']); ?></code></td>
                                <td>
                                    <span class="badge bg-dark border border-secondary text-info"><?php echo htmlspecialchars($row['nombre_rol']); ?></span>
                                    <div class="small text-muted mt-1">$<?php echo number_format($row['tarifa_por_hora'] ?? 0, 2); ?>/h</div>
                                </td>
                                <td><?php echo $row['correo'] ? htmlspecialchars($row['correo']) : '<i class="text-white-50">N/A</i>'; ?></td>
                                <td>
                                    <span class="badge status-badge <?php echo $badge_class; ?>">
                                        <?php echo htmlspecialchars($row['estado_cuenta']); ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-outline-info me-2 assign-device" data-bs-toggle="modal" data-bs-target="#modalAsignarDispositivo" data-id-cliente="<?php echo intval($row['id']); ?>" title="Asignar dispositivo">
                                            <i class="fas fa-desktop"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-light me-2 edit-client"><i class="fas fa-pen"></i></button>
                                        <?php if ($row['estado_cuenta'] === 'activo') { ?>
                                            <button class="btn btn-sm btn-outline-danger delete-client"><i class="fas fa-trash-alt"></i></button>
                                        <?php } else { ?>
                                            <button class="btn btn-sm btn-outline-success activate-client"><i class="fas fa-check"></i></button>
                                        <?php } ?>
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

<!-- Modal Nuevo Cliente -->
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
                            <?php foreach ($tiposClientes as $type): ?>
                                <option value="<?= htmlspecialchars($type['id']) ?>"><?= htmlspecialchars($type['nombre_rol']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Crear Cliente</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Editar Cliente -->
<div class="modal fade" id="editClientModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-modal">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-edit"></i> Editar Cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editClientForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="edit_client_id">
                    <div class="mb-3">
                        <label class="form-label">Nombre</label>
                        <input type="text" name="nombre" id="edit_nombre" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Apellido</label>
                        <input type="text" name="apellido" id="edit_apellido" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Cédula o Código</label>
                        <input type="text" name="cedula_o_codigo" id="edit_cedula" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Correo</label>
                        <input type="email" name="correo" id="edit_correo" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tipo de cliente</label>
                        <select name="tipo_cliente_id" id="edit_tipo" class="form-select" required>
                            <option value="">Selecciona un tipo</option>
                            <?php foreach ($tiposClientes as $type): ?>
                                <option value="<?= htmlspecialchars($type['id']) ?>"><?= htmlspecialchars($type['nombre_rol']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Guardar cambios</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Confirmación -->
<div class="modal fade" id="confirmModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-modal">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmModalTitle">Confirmar</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="confirmModalBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="confirmModalBtn">Aceptar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Mensaje -->
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

<!-- Modal Asignar Dispositivo -->
<div class="modal fade" id="modalAsignarDispositivo" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-modal">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-desktop"></i> Asignar Dispositivo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="assignDeviceForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                    <input type="hidden" name="action" value="assign_device">
                    <input type="hidden" name="id_cliente" id="modal_id_cliente" value="">
                    <input type="hidden" name="usuario_operador_id" value="<?= intval($_SESSION['id'] ?? $_SESSION['id_usuario'] ?? $_SESSION['usuario_id'] ?? 0) ?>">
                    <div class="mb-3">
                        <label class="form-label">Seleccionar computadora disponible</label>
                        <select name="id_computadora" class="form-select" required>
                            <option value="">Seleccione computadora</option>
                            <?php if ($computadorasDisponibles && mysqli_num_rows($computadorasDisponibles) > 0): ?>
                                <?php while ($comp = mysqli_fetch_assoc($computadorasDisponibles)): ?>
                                    <?php
                                        $labelParts = [];
                                        $labelParts[] = 'PC-' . str_pad(intval($comp['numero_puesto'] ?? 0), 2, '0', STR_PAD_LEFT);
                                        if (!empty($comp['nombremarca'])) $labelParts[] = $comp['nombremarca'];
                                        if (!empty($comp['modelo'])) $labelParts[] = $comp['modelo'];
                                        if (!empty($comp['codigo_bien_nacional'])) $labelParts[] = '[' . $comp['codigo_bien_nacional'] . ']';
                                        if (!empty($comp['direccion_ip'])) $labelParts[] = '(' . $comp['direccion_ip'] . ')';
                                        $compLabel = implode(' ', $labelParts);
                                    ?>
                                    <option value="<?= intval($comp['id']) ?>"><?= htmlspecialchars($compLabel) ?></option>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <option value="">No hay computadoras disponibles</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tarifa por hora</label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark border-secondary text-white">$</span>
                                <input type="text" id="modal_tarifa_display" class="form-control" readonly>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Costo estimado</label>
                            <input type="text" id="modal_total_estimado" class="form-control fw-bold text-success" readonly value="$ 0.00">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Duración</label>
                        <select name="duracion" class="form-select" required>
                            <option value="">Seleccione duración</option>
                            <?php for ($m = 15; $m <= 240; $m += 15): ?>
                                <option value="<?= $m ?>"><?= $m ?> minutos</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-success w-100">Asignar</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ================================================== -->
<!-- MODAL PARA VER Y EDITAR TARIFAS POR TIPO DE CLIENTE (MEJORADO VISUALMENTE) -->
<!-- ================================================== -->
<div class="modal fade" id="modalTarifas" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content glass-modal">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-dollar-sign"></i> Tarifas por Tipo de Cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-sm table-borderless text-white tarifas-table">
                        <thead>
                            <tr>
                                <th>Tipo de Cliente</th>
                                <th>Tarifa (USD/hora)</th>
                                <th>Exento de pago</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tiposTarifas as $tipo): ?>
                                <tr id="tarifa-row-<?= $tipo['id'] ?>">
                                    <td class="align-middle"><?= htmlspecialchars($tipo['nombre_rol']) ?></td>
                                    <td class="align-middle">
                                        <span class="tarifa-valor" data-id="<?= $tipo['id'] ?>">$<?= number_format($tipo['tarifa_por_hora'], 2) ?></span>
                                    </td>
                                    <td class="align-middle">
                                        <?php if ($tipo['exento_pago'] == 1): ?>
                                            <span class="badge badge-rounded bg-warning text-dark">Exento</span>
                                        <?php else: ?>
                                            <span class="badge badge-rounded bg-secondary">Normal</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="align-middle">
                                        <button class="btn btn-sm btn-outline-primary edit-tarifa-btn" data-id="<?= $tipo['id'] ?>" data-nombre="<?= htmlspecialchars($tipo['nombre_rol']) ?>" data-tarifa="<?= $tipo['tarifa_por_hora'] ?>">
                                            <i class="fas fa-edit"></i> Editar tarifa
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para editar una tarifa específica (con estilo glass mejorado) -->
<div class="modal fade" id="modalEditTarifa" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-modal">
            <div class="modal-header">
                <h5 class="modal-title">Editar tarifa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editTarifaForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                    <input type="hidden" name="action" value="update_tarifa">
                    <input type="hidden" name="tipo_id" id="edit_tarifa_tipo_id">
                    <div class="mb-3">
                        <label class="form-label">Tipo de cliente</label>
                        <input type="text" id="edit_tarifa_tipo_nombre" class="form-control" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nueva tarifa (USD/hora)</label>
                        <input type="number" step="0.01" min="0" name="tarifa_por_hora" id="edit_tarifa_valor" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Actualizar tarifa</button>
                </form>
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

    function showConfirmModal(title, message, onConfirm) {
        document.getElementById('confirmModalTitle').innerText = title;
        document.getElementById('confirmModalBody').innerHTML = message;
        const confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
        const confirmBtn = document.getElementById('confirmModalBtn');
        const newBtn = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newBtn, confirmBtn);
        newBtn.addEventListener('click', () => {
            confirmModal.hide();
            onConfirm();
        });
        confirmModal.show();
    }

    function updateEstimatedTotal() {
        const tarifaVal = document.getElementById('modal_tarifa_display')?.value || 0;
        const durationSelect = document.querySelector('#modalAsignarDispositivo select[name="duracion"]');
        const durationVal = durationSelect ? durationSelect.value : 0;
        const total = (parseFloat(tarifaVal) / 60) * parseInt(durationVal);
        const totalDisplay = document.getElementById('modal_total_estimado');
        if (totalDisplay) {
            totalDisplay.value = '$ ' + (isNaN(total) ? '0.00' : total.toFixed(2));
        }
    }

    async function sendAction(action, clientId) {
        const data = new FormData();
        data.append('action', action);
        data.append('id', clientId);
        data.append('csrf_token', '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>');
        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: data
            });
            const text = await response.text();
            return JSON.parse(text);
        } catch (error) {
            console.error('Error en petición fetch:', error);
            return { success: false, message: 'Error de comunicación con el servidor.' };
        }
    }

    document.getElementById('addClientForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                credentials: 'same-origin',
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

    document.querySelectorAll('.assign-device').forEach(btn => {
        btn.addEventListener('click', () => {
            const row = btn.closest('tr');
            const clienteId = row.dataset.id;
            const tarifa = row.dataset.tarifa;

            const modalInput = document.getElementById('modal_id_cliente');
            if (modalInput) modalInput.value = clienteId;

            const tarifaInput = document.getElementById('modal_tarifa_display');
            if (tarifaInput) {
                tarifaInput.value = tarifa;
                updateEstimatedTotal();
            }
        });
    });

    document.querySelector('#modalAsignarDispositivo select[name="duracion"]')?.addEventListener('change', updateEstimatedTotal);

    document.getElementById('assignDeviceForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                credentials: 'same-origin',
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
                bootstrap.Modal.getInstance(document.getElementById('modalAsignarDispositivo')).hide();
                Swal.fire({
                    icon: 'success',
                    title: 'Asignación exitosa',
                    text: result.message,
                    timer: 1500,
                    showConfirmButton: false
                });
                setTimeout(() => location.reload(), 1500);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: result.message
                });
            }
        } catch (error) {
            console.error('Error en la petición:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'No se pudo asignar el dispositivo. Intenta de nuevo.'
            });
        }
    });

    document.querySelectorAll('.edit-client').forEach(btn => {
        btn.addEventListener('click', () => {
            const row = btn.closest('tr');
            const id = row.dataset.id;
            document.getElementById('edit_client_id').value = id;
            document.getElementById('edit_nombre').value = row.dataset.nombre || '';
            document.getElementById('edit_apellido').value = row.dataset.apellido || '';
            document.getElementById('edit_cedula').value = row.dataset.cedula || '';
            document.getElementById('edit_correo').value = row.dataset.correo || '';
            const tipo = row.dataset.tipoid || '';
            document.getElementById('edit_tipo').value = tipo;
            new bootstrap.Modal(document.getElementById('editClientModal')).show();
        });
    });

    document.getElementById('editClientForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            });
            const text = await response.text();
            let result;
            try { result = JSON.parse(text); } catch (error) { result = { success: false, message: 'Respuesta inválida del servidor.' }; }
            if (result.success) {
                bootstrap.Modal.getInstance(document.getElementById('editClientModal')).hide();
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

    document.querySelectorAll('.delete-client').forEach(btn => {
        btn.addEventListener('click', () => {
            const row = btn.closest('tr');
            const clientId = row.dataset.id;
            const clientName = row.dataset.nombre + ' ' + row.dataset.apellido;
            showConfirmModal('Confirmar desactivación', `¿Deseas desactivar al cliente <strong>${clientName}</strong>? Esta acción impedirá su uso en el sistema.`, async () => {
                const result = await sendAction('deactivate', clientId);
                if (result.success) {
                    showMessageModal('Éxito', result.message);
                    setTimeout(() => location.reload(), 1200);
                } else {
                    showMessageModal('Error', result.message);
                }
            });
        });
    });

    document.querySelectorAll('.activate-client').forEach(btn => {
        btn.addEventListener('click', () => {
            const row = btn.closest('tr');
            const clientId = row.dataset.id;
            const clientName = row.dataset.nombre + ' ' + row.dataset.apellido;
            showConfirmModal('Confirmar activación', `¿Deseas activar al cliente <strong>${clientName}</strong>? Podrá volver a estar activo en el sistema.`, async () => {
                const result = await sendAction('activate', clientId);
                if (result.success) {
                    showMessageModal('Éxito', result.message);
                    setTimeout(() => location.reload(), 1200);
                } else {
                    showMessageModal('Error', result.message);
                }
            });
        });
    });

    // ==================================================
    // Lógica para editar tarifas (sin cambios funcionales)
    // ==================================================
    const tarifaModal = new bootstrap.Modal(document.getElementById('modalEditTarifa'));
    document.querySelectorAll('.edit-tarifa-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const tipoId = btn.getAttribute('data-id');
            const tipoNombre = btn.getAttribute('data-nombre');
            const tarifaActual = btn.getAttribute('data-tarifa');
            document.getElementById('edit_tarifa_tipo_id').value = tipoId;
            document.getElementById('edit_tarifa_tipo_nombre').value = tipoNombre;
            document.getElementById('edit_tarifa_valor').value = tarifaActual;
            tarifaModal.show();
        });
    });

    document.getElementById('editTarifaForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            });
            const text = await response.text();
            let result;
            try { result = JSON.parse(text); } catch (error) { result = { success: false, message: 'Respuesta inválida del servidor.' }; }
            if (result.success) {
                tarifaModal.hide();
                showMessageModal('Éxito', result.message);
                // Actualizar la tarifa mostrada en el modal principal sin recargar la página
                const tipoId = document.getElementById('edit_tarifa_tipo_id').value;
                const nuevaTarifa = document.getElementById('edit_tarifa_valor').value;
                const spanTarifa = document.querySelector(`.tarifa-valor[data-id="${tipoId}"]`);
                if (spanTarifa) {
                    spanTarifa.innerText = `$${parseFloat(nuevaTarifa).toFixed(2)}`;
                }
                // También actualizar el atributo data-tarifa del botón correspondiente
                const botonEditar = document.querySelector(`.edit-tarifa-btn[data-id="${tipoId}"]`);
                if (botonEditar) {
                    botonEditar.setAttribute('data-tarifa', nuevaTarifa);
                }
                // Opcional: recargar la página después de unos segundos para reflejar cambios en toda la interfaz
                setTimeout(() => location.reload(), 1500);
            } else {
                showMessageModal('Error', result.message);
            }
        } catch (error) {
            console.error('Error en la petición:', error);
            showMessageModal('Error', 'No se pudo actualizar la tarifa. Intenta de nuevo.');
        }
    });
</script>

<?php include_once "includes/footer.php"; 
} // Fin del bloque else AJAX ?>