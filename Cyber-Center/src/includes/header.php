<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['active'])) {
    header('Location: ../');
    exit;
}
$nombre_usuario = $_SESSION['nombre'] ?? 'Usuario';
$current_page = basename($_SERVER['PHP_SELF']);
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
                    <li class="nav-item"><a class="nav-link <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>" href="index.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo ($current_page == 'clientes.php') ? 'active' : ''; ?>" href="clientes.php"><i class="fas fa-users"></i> Clientes</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo ($current_page == 'sesiones.php') ? 'active' : ''; ?>" href="sesiones.php"><i class="fas fa-clock"></i> Sesiones</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo ($current_page == 'usuarios.php') ? 'active' : ''; ?>" href="usuarios.php"><i class="fas fa-user-shield"></i> Usuarios</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo ($current_page == 'perifericos.php') ? 'active' : ''; ?>" href="perifericos.php"><i class="fas fa-plug"></i> Periféricos</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo ($current_page == 'equipos.php') ? 'active' : ''; ?>" href="equipos.php"><i class="fas fa-desktop"></i> Equipos</a></li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($nombre_usuario); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end glass-dropdown">
                            <li>
                                <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#cambiarPassModal">
                                    <i class="fas fa-key"></i> Cambiar contraseña
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item dropdown-toggle" href="#" data-bs-toggle="collapse" data-bs-target="#configuracionesSubmenu" aria-expanded="false">
                                    <span><i class="fas fa-cog"></i> Configuraciones</span>
                                    <i class="fas fa-chevron-down ms-2"></i>
                                </a>
                                <div class="collapse ps-3" id="configuracionesSubmenu">
                                    <ul class="list-unstyled mb-0">
                                        <li><a class="dropdown-item" href="configuracion.php"><i class="fas fa-info-circle"></i> Ver configuración</a></li>
                                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#respaldarModal"><i class="fas fa-database"></i> Respaldar BD</a></li>
                                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#restaurarModal"><i class="fas fa-upload"></i> Restaurar BD</a></li>
                                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#historialModal"><i class="fas fa-history"></i> Historial</a></li>
                                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#ingresosMesModal"><i class="fas fa-hand-holding-usd"></i> Ingresos del Mes</a></li>
                                    </ul>
                                </div>
                            </li>
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
            </div>
        </div>
    </div>

    <!-- Modal Cambiar Contraseña -->
    <div class="modal fade" id="cambiarPassModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content glass-modal">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-key"></i> Cambiar contraseña</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formCambiarPass">
                        <div class="mb-3">
                            <label class="form-label">Contraseña actual</label>
                            <input type="password" class="form-control" id="actualPass" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nueva contraseña</label>
                            <input type="password" class="form-control" id="nuevaPass" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Actualizar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Respaldar BD -->
    <div class="modal fade" id="respaldarModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content glass-modal">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-database"></i> Respaldar BD</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Genera una copia de seguridad de la base de datos de CyberCenter. El respaldo incluirá la información de usuarios, equipos, periféricos, historial y demás registros.</p>
                    <div class="mb-3">
                        <label class="form-label">Nombre del archivo de respaldo</label>
                        <input type="text" class="form-control" id="backupFileName" value="respaldo_cybercenter_<?php echo date('Ymd_His'); ?>.sql">
                    </div>
                    <button type="button" id="btnRespaldar" class="btn btn-success w-100">Generar respaldo</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Restaurar BD -->
    <div class="modal fade" id="restaurarModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content glass-modal">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-upload"></i> Restaurar BD</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Selecciona un archivo SQL válido para restaurar la base de datos. Ten en cuenta que la restauración reemplazará los datos actuales.</p>
                    <div class="mb-3">
                        <label class="form-label">Archivo de respaldo</label>
                        <input type="file" class="form-control" id="restoreFile" accept=".sql">
                    </div>
                    <button type="button" id="btnRestaurar" class="btn btn-warning w-100">Restaurar ahora</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Historial -->
    <div class="modal fade" id="historialModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content glass-modal">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-history"></i> Historial</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Consulta las últimas acciones registradas en el sistema.</p>
                    <div class="table-responsive">
                        <table class="table table-dark table-striped">
                            <thead>
                                <tr>
                                    <th>Fecha / Hora</th>
                                    <th>Usuario</th>
                                    <th>Sector</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody id="historialTableBody">
                                <tr>
                                    <td colspan="4" class="text-center text-white-50">Cargando historial...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Ingresos del Mes -->
    <div class="modal fade" id="ingresosMesModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content glass-modal">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-dollar-sign"></i> Reporte de Ingresos Mensuales</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3 g-3">
                        <div class="col-md-4">
                            <div class="p-3 rounded-4" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);">
                                <label class="form-label text-white-50 small fw-bold">FILTRAR POR MES:</label>
                                <input type="month" id="mesFiltroIngresos" class="form-control bg-dark text-white border-secondary" value="<?php echo date('Y-m'); ?>">
                            </div>
                        </div>
                    </div>
                    <div class="row mb-4 g-3">
                        <div class="col-md-6">
                            <div class="p-3 rounded-4 text-center" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1);">
                                <span class="d-block small text-info fw-bold text-uppercase mb-1" style="font-size: 0.7rem; letter-spacing: 1px;">Ingresos de Hoy</span>
                                <h3 class="mb-0 fw-bold text-info" id="totalIngresosHoy">$0.00</h3>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 rounded-4 text-center" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1);">
                                <span class="d-block small text-success fw-bold text-uppercase mb-1" style="font-size: 0.7rem; letter-spacing: 1px;">Ingresos del Mes</span>
                                <h3 class="mb-0 fw-bold text-success" id="totalIngresosMes">$0.00</h3>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-dark table-striped">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Fecha</th>
                                    <th>Cliente</th>
                                    <th>Equipo</th>
                                    <th>Operador</th>
                                    <th class="text-end">Monto</th>
                                </tr>
                            </thead>
                            <tbody id="ingresosTableBody">
                                <tr>
                                    <td colspan="6" class="text-center text-white-50">Cargando ingresos...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../assets/js/dashboard.js"></script>
</body>
</html>
