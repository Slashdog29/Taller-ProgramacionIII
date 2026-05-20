<?php
session_start();
require_once "../conexion.php";
header('Content-Type: application/json');

if (empty($_SESSION['active'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no válida']);
    exit;
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido']);
    exit;
}

$id = intval($_POST['id'] ?? 0);
$nuevo_estado = trim($_POST['estado'] ?? ''); // Ejemplo: 'mantenimiento', 'disponible', 'fuera_de_servicio'

if ($id <= 0 || empty($nuevo_estado)) {
    echo json_encode(['success' => false, 'message' => 'Faltan parámetros']);
    exit;
}

$stmt = $conexion->prepare("UPDATE computadoras SET estado_operativo = ? WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("si", $nuevo_estado, $id);
    if ($stmt->execute()) {
        // Registro en historial
        $accion = "Cambió estado de equipo ID $id a: $nuevo_estado";
        $hist = $conexion->prepare("INSERT INTO historial (usuario, ip, fyh, sector, acciones) VALUES (?, ?, NOW(), 'Equipos', ?)");
        $user_session = $_SESSION['nombre'];
        $remote_ip = $_SERVER['REMOTE_ADDR'];
        $hist->bind_param("sss", $user_session, $remote_ip, $accion);
        $hist->execute();

        echo json_encode(['success' => true, 'message' => 'Estado del equipo actualizado']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error en la base de datos']);
    }
    $stmt->close();
}