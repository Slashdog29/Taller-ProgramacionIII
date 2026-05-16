<?php
include_once "includes/header.php";
require "../conexion.php";

$nombre = $_SESSION['nombre'];
$ip = $_SERVER['REMOTE_ADDR'];
$fecha_hora = date('Y-m-d H:i:s');

$sector = "Dashboard";
$accion = "Acceso al panel principal";
$stmt = $conexion->prepare("INSERT INTO historial (usuario, ip, fyh, sector, acciones) VALUES (?, ?, ?, ?, ?)");
if ($stmt) {
    $stmt->bind_param("sssss", $nombre, $ip, $fecha_hora, $sector, $accion);
    $stmt->execute();
    $stmt->close();
}

$totalUsuarios = $conexion->query("SELECT COUNT(*) as t FROM usuarios WHERE activo=1")->fetch_assoc()['t'] ?? 0;
$totalClientes = $conexion->query("SELECT COUNT(*) as t FROM clientes WHERE estado_cuenta='activo'")->fetch_assoc()['t'] ?? 0;
$totalEquipos = $conexion->query("SELECT COUNT(*) as t FROM computadoras WHERE estado_operativo IN ('disponible','ocupado')")->fetch_assoc()['t'] ?? 0;
$disponibles = $conexion->query("SELECT COUNT(*) as t FROM computadoras WHERE estado_operativo='disponible'")->fetch_assoc()['t'] ?? 0;
$sesionesActivas = $conexion->query("SELECT COUNT(*) as t FROM sesiones WHERE estado_transaccion='en_curso'")->fetch_assoc()['t'] ?? 0;
$ingresosHoy = $conexion->query("SELECT COALESCE(SUM(monto_total_pagado),0) as t FROM sesiones WHERE DATE(hora_fin)=CURDATE() AND estado_transaccion='finalizado'")->fetch_assoc()['t'] ?? 0;
$ingresosMes = $conexion->query("SELECT COALESCE(SUM(monto_total_pagado),0) as t FROM sesiones WHERE MONTH(hora_fin)=MONTH(CURDATE()) AND YEAR(hora_fin)=YEAR(CURDATE()) AND estado_transaccion='finalizado'")->fetch_assoc()['t'] ?? 0;

$labels = $sesionesData = $ingresosData = [];
for ($i = 6; $i >= 0; $i--) {
    $fecha = date('Y-m-d', strtotime("-$i days"));
    $labels[] = date('d M', strtotime($fecha));
    $sesionesData[] = $conexion->query("SELECT COUNT(*) as t FROM sesiones WHERE DATE(hora_inicio)='$fecha'")->fetch_assoc()['t'] ?? 0;
    $ingresosData[] = $conexion->query("SELECT COALESCE(SUM(monto_total_pagado),0) as t FROM sesiones WHERE DATE(hora_fin)='$fecha' AND estado_transaccion='finalizado'")->fetch_assoc()['t'] ?? 0;
}

$topClientes = $topGastos = [];
$resTop = $conexion->query("SELECT CONCAT(nombre,' ',apellido) as nombre, SUM(s.monto_total_pagado) as total FROM clientes c JOIN sesiones s ON c.id=s.cliente_id WHERE s.estado_transaccion='finalizado' GROUP BY c.id ORDER BY total DESC LIMIT 5");
if ($resTop) {
    while ($row = $resTop->fetch_assoc()) {
        $topClientes[] = $row['nombre'];
        $topGastos[] = $row['total'];
    }
}

$actividad = [];
$resAct = $conexion->query("SELECT usuario, acciones, fyh FROM historial ORDER BY fyh DESC LIMIT 5");
if ($resAct) {
    while ($row = $resAct->fetch_assoc()) $actividad[] = $row;
}
?>

<div class="container px-0">
    <!-- Encabezado glass -->
    <div class="glass-card p-3 p-md-4 mb-4 d-flex flex-wrap justify-content-between align-items-center">
        <div>
            <h1 class="h3 mb-1"><i class="fas fa-chart-line me-2"></i>Panel de Control</h1>
            <p class="mb-0 text-white-50">Bienvenido, <strong><?php echo htmlspecialchars($nombre); ?></strong></p>
        </div>
        <div class="mt-2 mt-sm-0">
            <span class="badge bg-primary bg-opacity-25 p-2 me-2"><i class="far fa-calendar-alt"></i> <?php echo date('d/m/Y'); ?></span>
            <span class="badge bg-primary bg-opacity-25 p-2"><i class="fas fa-clock"></i> <?php echo date('h:i A'); ?></span>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="glass-card stat-card">
                <div class="d-flex justify-content-between">
                    <div><div class="stat-title">Usuarios Activos</div><div class="stat-value"><?php echo $totalUsuarios; ?></div><div class="small text-white-50">Con acceso</div></div>
                    <div class="stat-icon"><i class="fas fa-user-shield"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="glass-card stat-card">
                <div class="d-flex justify-content-between">
                    <div><div class="stat-title">Clientes Activos</div><div class="stat-value"><?php echo $totalClientes; ?></div><div class="small text-white-50">Habilitados</div></div>
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <a href="equipos.php" class="text-decoration-none">
                <div class="glass-card stat-card">
                    <div class="d-flex justify-content-between">
                        <div><div class="stat-title">Equipos en Red</div><div class="stat-value text-white"><?php echo $totalEquipos; ?></div><div class="small text-white-50"><?php echo $disponibles; ?> disponibles</div></div>
                        <div class="stat-icon"><i class="fas fa-desktop"></i></div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-3 col-6">
            <div class="glass-card stat-card">
                <div class="d-flex justify-content-between">
                    <div><div class="stat-title">Sesiones Activas</div><div class="stat-value"><?php echo $sesionesActivas; ?></div><div class="small text-white-50">En curso</div></div>
                    <div class="stat-icon"><i class="fas fa-play-circle"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Ingresos -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="glass-card stat-card" style="background: linear-gradient(135deg, rgba(16,185,129,0.15), transparent);">
                <div class="d-flex justify-content-between">
                    <div><div class="stat-title">Ingresos Hoy</div><div class="stat-value">$ <?php echo number_format($ingresosHoy,2); ?></div><div class="small text-white-50"><?php echo date('d/m/Y'); ?></div></div>
                    <div class="stat-icon"><i class="fas fa-coins"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="glass-card stat-card" style="background: linear-gradient(135deg, rgba(79,70,229,0.15), transparent);">
                <div class="d-flex justify-content-between">
                    <div><div class="stat-title">Ingresos del Mes</div><div class="stat-value">$ <?php echo number_format($ingresosMes,2); ?></div><div class="small text-white-50"><?php echo date('F Y'); ?></div></div>
                    <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráficos -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="glass-card chart-container">
                <h5 class="mb-3"><i class="fas fa-chart-simple"></i> Sesiones por día</h5>
                <canvas id="sessionsChart" style="height: 250px; width: 100%;"></canvas>
            </div>
        </div>
        <div class="col-md-6">
            <div class="glass-card chart-container">
                <h5 class="mb-3"><i class="fas fa-dollar-sign"></i> Ingresos por día</h5>
                <canvas id="revenueChart" style="height: 250px; width: 100%;"></canvas>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="glass-card chart-container">
                <h5 class="mb-3"><i class="fas fa-trophy"></i> Top 5 Clientes</h5>
                <canvas id="topClientsChart" style="height: 220px; width: 100%;"></canvas>
            </div>
        </div>
        <div class="col-md-6">
            <div class="glass-card p-3">
                <h5 class="mb-3"><i class="fas fa-history"></i> Actividad Reciente</h5>
                <ul class="list-unstyled">
                    <?php if(empty($actividad)): ?>
                        <li>No hay actividad reciente</li>
                    <?php else: ?>
                        <?php foreach($actividad as $act): ?>
                        <li class="activity-item">
                            <div class="activity-icon"><i class="fas fa-user-circle"></i></div>
                            <div>
                                <div class="fw-semibold"><?php echo htmlspecialchars($act['usuario']); ?></div>
                                <div class="small text-white-50"><?php echo htmlspecialchars($act['acciones']); ?></div>
                                <div class="small text-white-50 opacity-50"><?php echo date('d/m/Y H:i', strtotime($act['fyh'])); ?></div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    // Datos desde PHP
    const labels = <?php echo json_encode($labels); ?>;
    const sessionsData = <?php echo json_encode($sesionesData); ?>;
    const revenueData = <?php echo json_encode($ingresosData); ?>;
    const topNames = <?php echo json_encode($topClientes); ?>;
    const topSpent = <?php echo json_encode($topGastos); ?>;

    new Chart(document.getElementById('sessionsChart'), {
        type: 'bar',
        data: { labels, datasets: [{ label: 'Sesiones', data: sessionsData, backgroundColor: 'rgba(79,70,229,0.6)', borderRadius: 8 }] },
        options: { responsive: true, maintainAspectRatio: true, scales: { y: { ticks: { color: '#cbd5e1' }, grid: { color: 'rgba(255,255,255,0.05)' } }, x: { ticks: { color: '#cbd5e1' } } }, plugins: { legend: { labels: { color: '#e2e8f0' } } } }
    });

    new Chart(document.getElementById('revenueChart'), {
        type: 'line',
        data: { labels, datasets: [{ label: 'Ingresos ($)', data: revenueData, borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.1)', fill: true, tension: 0.3 }] },
        options: { responsive: true, scales: { y: { ticks: { color: '#cbd5e1', callback: v => '$ '+v }, grid: { color: 'rgba(255,255,255,0.05)' } }, x: { ticks: { color: '#cbd5e1' } } }, plugins: { tooltip: { callbacks: { label: ctx => `$ ${ctx.raw.toFixed(2)}` } }, legend: { labels: { color: '#e2e8f0' } } } }
    });

    if(topNames.length) {
        new Chart(document.getElementById('topClientsChart'), {
            type: 'bar',
            data: { labels: topNames, datasets: [{ label: 'Total gastado ($)', data: topSpent, backgroundColor: 'rgba(245,158,11,0.7)', borderRadius: 8 }] },
            options: { indexAxis: 'y', responsive: true, maintainAspectRatio: true, scales: { x: { ticks: { color: '#cbd5e1', callback: v => '$ '+v } }, y: { ticks: { color: '#cbd5e1' } } }, plugins: { legend: { labels: { color: '#e2e8f0' } } } }
        });
    }
</script>

<?php include_once "includes/footer.php"; ?>
