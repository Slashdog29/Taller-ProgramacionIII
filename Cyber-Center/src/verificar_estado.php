<?php
// verificar_estado.php
// Endpoint JSON que las terminales del Cyber consultan periódicamente.
// Responde {"accion":"permitir"} o {"accion":"bloquear","motivo":"..."}
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . "/../conexion.php";

date_default_timezone_set('America/Caracas');
mysqli_query($conexion, "SET time_zone = '-04:00'");

$remote_ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

// 1. Buscar computadora por IP
$sql_comp = "SELECT id, estado_operativo, numero_puesto FROM computadoras WHERE direccion_ip = ? LIMIT 1";
$stmt = mysqli_prepare($conexion, $sql_comp);
mysqli_stmt_bind_param($stmt, 's', $remote_ip);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $comp_id, $estado_operativo, $numero_puesto);
$found = mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

if (!$found) {
    echo json_encode(['accion' => 'bloquear', 'motivo' => 'Equipo no registrado en el sistema.']);
    exit;
}

// 2. Si el operador liberó manualmente -> bloquear
if (strtolower($estado_operativo) === 'disponible') {
    echo json_encode(['accion' => 'bloquear', 'motivo' => 'Operador liberó el equipo.']);
    exit;
}

// 3. Buscar sesión activa
$sql_ses = "SELECT id, hora_fin_estimada FROM sesiones 
            WHERE computadora_id = ? AND estado_transaccion = 'en_curso' 
            ORDER BY id DESC LIMIT 1";
$stmt2 = mysqli_prepare($conexion, $sql_ses);
mysqli_stmt_bind_param($stmt2, 'i', $comp_id);
mysqli_stmt_execute($stmt2);
mysqli_stmt_bind_result($stmt2, $ses_id, $hora_fin_estimada);
$has_session = mysqli_stmt_fetch($stmt2);
mysqli_stmt_close($stmt2);

if (!$has_session) {
    echo json_encode(['accion' => 'bloquear', 'motivo' => 'No hay sesión activa para esta máquina.']);
    exit;
}

// 4. VERIFICAR AUTOMÁTICAMENTE SI EL TIEMPO EXPIRÓ (nueva lógica)
$ahora = new DateTime();
$fin = new DateTime($hora_fin_estimada);
if ($ahora > $fin) {
    // 1. Finalizar la sesión en la base de datos
    mysqli_query($conexion, "UPDATE sesiones SET estado_transaccion = 'finalizado', hora_fin = NOW() WHERE id = $ses_id");
    
    // 2. Liberar la computadora
    mysqli_query($conexion, "UPDATE computadoras SET estado_operativo = 'disponible' WHERE id = $comp_id");
    
    // 3. Registrar en historial el cierre automático
    $accion_hist = "Sesión ID $ses_id finalizada automáticamente por tiempo agotado (PC-$numero_puesto)";
    $hist_stmt = $conexion->prepare("INSERT INTO historial (usuario, ip, fyh, sector, acciones) VALUES ('Sistema', ?, NOW(), 'Sesiones', ?)");
    if ($hist_stmt) {
        $hist_stmt->bind_param("ss", $remote_ip, $accion_hist);
        $hist_stmt->execute();
        $hist_stmt->close();
    }

    echo json_encode(['accion' => 'bloquear', 'motivo' => 'Tiempo de sesión agotado.']);
    exit;
}

// Si todo está correcto
echo json_encode(['accion' => 'permitir']);
exit;