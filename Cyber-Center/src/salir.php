<?php
session_start();
require_once "../conexion.php";

if (isset($_SESSION['nombre'])) {
    $nombre = $_SESSION['nombre'];
    $ip = $_SERVER['REMOTE_ADDR'];
    $fecha = date('Y-m-d H:i:s');
    $sector = "cierre_sesion";
    $accion = "Cierre de sesión";

    $stmt = $conexion->prepare("INSERT INTO historial (usuario, ip, fyh, sector, acciones) VALUES (?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("sssss", $nombre, $ip, $fecha, $sector, $accion);
        $stmt->execute();
        $stmt->close();
    }
}

session_destroy();
header("Location: ../index.php");
exit;
?>
