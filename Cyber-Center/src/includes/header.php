<?php
session_start();
if (empty($_SESSION['active'])) {
    header('location: ../');
    exit;
}
$nombre_usuario = $_SESSION['nombre'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CyberCenter | Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg fixed-top glass-nav">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="fas fa-microchip"></i> CYBER<span class="text-primary">CENTER</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="clientes.php"><i class="fas fa-users"></i> Clientes</a></li>
                    <li class="nav-item"><a class="nav-link" href="usuarios.php"><i class="fas fa-user-shield"></i> Usuarios</a></li>
                    <li class="nav-item"><a class="nav-link" href="equipos.php"><i class="fas fa-desktop"></i> Equipos</a></li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($nombre_usuario); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end glass-dropdown">
                            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#cambiarPassModal"><i class="fas fa-key"></i> Cambiar contraseña</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="#" id="btnLogout"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container-fluid main-content" style="margin-top: 80px;">
        <div class="row">
            <div class="col-12">
