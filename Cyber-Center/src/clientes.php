<?php
include_once "includes/header.php";
require "../conexion.php"; // Cambiamos a require para asegurar que la variable se defina en este archivo

// Declaramos la variable como global por si el script se ejecuta en un entorno de funciones
global $conexion;

// Registro de actividad en el historial
$nombre = $_SESSION['nombre'];
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
