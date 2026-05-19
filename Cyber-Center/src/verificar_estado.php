<?php
// verificar_estado.php
// Endpoint JSON que las terminales del Cyber consultan periódicamente.
// Responde {"accion":"permitir"} o {"accion":"bloquear","motivo":"..."}

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . "/../conexion.php"; // $conexion (procedimental)

// -----------------------------------------------------------------------------
// NOTAS: Ajusta nombres de tablas y columnas según tu esquema.
// - Tabla de computadoras: 'computadoras' (columnas: id, direccion_ip, estado_operativo)
// - Tabla de sesiones: 'sesiones' (columnas: id, computadora_id, estado_transaccion, hora_fin_estimada, hora_fin)
// Valores esperados:
// - estado_operativo: 'disponible' | 'ocupado' | 'mantenimiento' | 'desincorporado'
// - estado_transaccion: 'en_curso' | 'finalizado' | 'anulado'
// -----------------------------------------------------------------------------

$remote_ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

// Buscar la computadora por su IP
$sql_comp = "SELECT id, estado_operativo, numero_puesto FROM computadoras WHERE direccion_ip = ? LIMIT 1";
$stmt = mysqli_prepare($conexion, $sql_comp);
if (!$stmt) {
    echo json_encode(['accion' => 'bloquear', 'motivo' => 'Error interno: fallo al preparar consulta de equipo.']);
    exit;
}
mysqli_stmt_bind_param($stmt, 's', $remote_ip);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $comp_id, $estado_operativo, $numero_puesto);
$found = mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

if (!$found) {
    // Equipo no registrado -> instrucción para bloquear/expulsar al usuario
    echo json_encode(['accion' => 'bloquear', 'motivo' => 'Equipo no registrado en el sistema.']);
    exit;
}

// Si el operador liberó manualmente la computadora (cambió a 'disponible'), instruir bloqueo
if (strtolower($estado_operativo) === 'disponible') {
    echo json_encode(['accion' => 'bloquear', 'motivo' => 'Operador liberó el equipo.']);
    exit;
}

// Buscar sesión activa para esta computadora
$sql_ses = "SELECT id, hora_fin_estimada FROM sesiones WHERE computadora_id = ? AND estado_transaccion = 'en_curso' ORDER BY id DESC LIMIT 1";
$stmt2 = mysqli_prepare($conexion, $sql_ses);
if (!$stmt2) {
    echo json_encode(['accion' => 'bloquear', 'motivo' => 'Error interno: fallo al preparar consulta de sesión.']);
    exit;
}
mysqli_stmt_bind_param($stmt2, 'i', $comp_id);
mysqli_stmt_execute($stmt2);
mysqli_stmt_bind_result($stmt2, $ses_id, $ses_comp_id, $hora_fin_estimada);
$has_session = mysqli_stmt_fetch($stmt2);
mysqli_stmt_close($stmt2);

if (!$has_session) {
    // No hay sesión en curso -> bloquear
    echo json_encode(['accion' => 'bloquear', 'motivo' => 'No hay sesión activa para esta máquina.']);
    exit;
}

// Comparar hora actual con hora_fin_estimada
$now_ts = time();
$fin_ts = strtotime($hora_fin_estimada);
if ($fin_ts === false) {
    // Si no se puede parsear la fecha de la BD, bloquear por seguridad
    echo json_encode(['accion' => 'bloquear', 'motivo' => 'Error en la fecha de fin estimada.']);
    exit;
}

if ($now_ts > $fin_ts) {
    // Tiempo excedido: marcar sesión como finalizada y bloquear la máquina
    $sql_up = "UPDATE sesiones SET estado_transaccion = 'finalizado', hora_fin = NOW() WHERE id = ?";
    $stmt_up = mysqli_prepare($conexion, $sql_up);
    if ($stmt_up) {
        mysqli_stmt_bind_param($stmt_up, 'i', $ses_id);
        mysqli_stmt_execute($stmt_up);
        mysqli_stmt_close($stmt_up);
    }

    // Opcional: marcar la computadora como 'disponible' para el operador
    $sql_upc = "UPDATE computadoras SET estado_operativo = 'disponible' WHERE id = ?";
    $stmt_upc = mysqli_prepare($conexion, $sql_upc);
    if ($stmt_upc) {
        mysqli_stmt_bind_param($stmt_upc, 'i', $comp_id);
        mysqli_stmt_execute($stmt_upc);
        mysqli_stmt_close($stmt_upc);
    }

    echo json_encode(['accion' => 'bloquear', 'motivo' => 'Tiempo de sesión excedido.']);
    exit;
}

// Si llega hasta aquí, todo está OK -> permitir el uso
echo json_encode(['accion' => 'permitir']);
exit;

// FIN verificar_estado.php
