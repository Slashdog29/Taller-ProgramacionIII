<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . "/../conexion.php";

date_default_timezone_set('America/Caracas');
$conexion->query("SET time_zone = '-04:00'");

global $conexion;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    
    // 1. Limpiamos cualquier "basura" o Warning en el buffer antes de imprimir JSON
    if (ob_get_length()) ob_clean(); 
    header('Content-Type: application/json');

    $action = $_POST['action'] ?? '';
    $sessionId = intval($_POST['id'] ?? 0);
    $csrfToken = $_POST['csrf_token'] ?? '';
    $response = ['success' => false, 'message' => 'Acción inválida.'];

    if (!isset($_SESSION['csrf_token']) || $csrfToken !== $_SESSION['csrf_token']) {
        $response['message'] = 'Token CSRF inválido o sesión expirada.';
        echo json_encode($response);
        exit;
    }

    if ($sessionId <= 0) {
        $response['message'] = 'ID de sesión inválido.';
        echo json_encode($response);
        exit;
    }

    if ($action === 'finalize' || $action === 'annul') {
        $newState = $action === 'finalize' ? 'finalizado' : 'anulado';
        $stmt = $conexion->prepare("UPDATE sesiones SET hora_fin = NOW(), estado_transaccion = ? WHERE id = ? AND estado_transaccion = 'en_curso'");
        if ($stmt) {
            $stmt->bind_param('si', $newState, $sessionId);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                // Obtener datos actualizados (calculados por triggers o columnas generadas)
                $resData = mysqli_query($conexion, "SELECT hora_fin, minutos_consumidos, monto_total_pagado, comprobante_factura FROM sesiones WHERE id = $sessionId");
                $updatedRow = mysqli_fetch_assoc($resData);

                $response = [
                    'success' => true, 
                    'message' => "Sesión marcada como $newState correctamente.",
                    'data' => $updatedRow
                ];
                $accion_historial = "Sesion ID $sessionId marcada como $newState";
                
                $hist = $conexion->prepare("INSERT INTO historial (usuario, ip, fyh, sector, acciones) VALUES (?, ?, ?, ?, ?)");
                if ($hist) {
                    // 2. CORRECCIÓN: Manejar si $_SESSION['nombre'] no existe para evitar el Warning
                    $usuario_sesion = $_SESSION['nombre'] ?? 'Sistema'; 
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $fecha_h = date('Y-m-d H:i:s');
                    $sector = 'Sesiones';
                    
                    $hist->bind_param('sssss', $usuario_sesion, $ip, $fecha_h, $sector, $accion_historial);
                    $hist->execute();
                    $hist->close();
                }
            } else {
                $response['message'] = 'No se pudo actualizar la sesión. Comprueba que esté en curso.';
            }
            $stmt->close();
        } else {
            $response['message'] = 'Error al preparar la acción: ' . $conexion->error;
        }
    }

    echo json_encode($response);
    exit;
}

include_once "includes/header.php";

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$nombre = $_SESSION['nombre'] ?? 'Usuario';
$ip = $_SERVER['REMOTE_ADDR'];
$fecha_hora = date('Y-m-d H:i:s');
$sector = "Sesiones";
$accion = "Acceso a la sección de gestión de sesiones";
$stmt = $conexion->prepare("INSERT INTO historial (usuario, ip, fyh, sector, acciones) VALUES (?, ?, ?, ?, ?)");
if ($stmt) {
    $stmt->bind_param("sssss", $nombre, $ip, $fecha_hora, $sector, $accion);
    $stmt->execute();
    $stmt->close();
}

$totalSesiones = $conexion->query("SELECT COUNT(*) as t FROM sesiones")->fetch_assoc()['t'] ?? 0;
$sesionesActivas = $conexion->query("SELECT COUNT(*) as t FROM sesiones WHERE estado_transaccion='en_curso'")->fetch_assoc()['t'] ?? 0;
$sesionesFinalizadas = $conexion->query("SELECT COUNT(*) as t FROM sesiones WHERE estado_transaccion='finalizado'")->fetch_assoc()['t'] ?? 0;
$sesionesAnuladas = $conexion->query("SELECT COUNT(*) as t FROM sesiones WHERE estado_transaccion='anulado'")->fetch_assoc()['t'] ?? 0;

$query = "SELECT s.*, c.nombre AS cliente_nombre, c.apellido AS cliente_apellido, comp.numero_puesto, comp.direccion_ip, u.nombre_completo AS operador_nombre
          FROM sesiones s
          LEFT JOIN clientes c ON s.cliente_id = c.id
          LEFT JOIN computadoras comp ON s.computadora_id = comp.id
          LEFT JOIN usuarios u ON s.usuario_operador_id = u.id
          ORDER BY s.hora_inicio DESC";
$resultado = mysqli_query($conexion, $query);
if (!$resultado) {
    die("<div class='alert alert-danger'>Error en la consulta SQL: " . mysqli_error($conexion) . "</div>");
}
?>

<style>
    .table { color: var(--text-light); margin-bottom: 0; border-collapse: separate; border-spacing: 0; }
    .table thead th { background: rgba(0, 0, 0, 0.4); border-bottom: 1px solid var(--glass-border); color: var(--primary-light); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1.2px; padding: 1.25rem 1rem; font-weight: 800; }
    .table td { vertical-align: middle; border-bottom: 1px solid rgba(255, 255, 255, 0.05); padding: 1.25rem 1rem; background: transparent; }
    .table tbody tr { transition: all 0.3s ease; }
    .table tbody tr:hover { background: rgba(0, 0, 0, 0.25) !important; }
    .status-badge { border-radius: 20px; padding: 0.45rem 0.85rem; font-size: 0.65rem; text-transform: uppercase; font-weight: 800; letter-spacing: 0.5px; }
    .glass-card { background: var(--dark-bg); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 24px; }
    .table-responsive { border-radius: 24px; }
    .table tbody td { color: #ffffff !important; }
    .text-white-50 { color: rgba(255, 255, 255, 0.85) !important; }
    .text-muted { color: rgba(255, 255, 255, 0.75) !important; }
    .stat-card { padding: 1.5rem; border-radius: 24px; border: 1px solid rgba(255,255,255,0.08); background: rgba(15,23,42,0.7); }
    .stat-title { font-size: 0.85rem; color: #94a3b8; margin-bottom: 0.5rem; }
    .stat-value { font-size: 2rem; font-weight: 700; color: #f8fafc; }
    .stat-icon { font-size: 1.75rem; color: #7c3aed; }
    .clickable-card { cursor: pointer; transition: transform .12s ease, box-shadow .12s ease; }
    .clickable-card:hover { transform: translateY(-4px); box-shadow: 0 10px 30px rgba(0,0,0,0.4); }
    tr.highlighted { background: rgba(124,58,237,0.12) !important; }
    tr.dimmed { opacity: 0.28; }
    .clickable-card.active-filter { outline: 2px solid rgba(124,58,237,0.18); box-shadow: 0 8px 24px rgba(124,58,237,0.12); }
</style>

<div class="container main-content pb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0 text-white">Gestión de Sesiones</h2>
            <p class="text-white-50">Listado completo de sesiones: activas, finalizadas y anuladas.</p>
        </div>
    </div>

    <div class="row g-3 mb-4 align-items-stretch">
        <div class="col-md-3 col-6">
            <div class="glass-card stat-card h-100 clickable-card" data-filter="all" title="Mostrar todas las sesiones">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-title">Total Sesiones</div>
                        <div class="stat-value" id="stat-total"><?php echo $totalSesiones; ?></div>
                    </div>
                    <div class="stat-icon"><i class="fas fa-list"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="glass-card stat-card h-100 clickable-card" data-filter="en_curso" title="Mostrar sesiones activas">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-title">Activas</div>
                        <div class="stat-value" id="stat-activas"><?php echo $sesionesActivas; ?></div>
                    </div>
                    <div class="stat-icon"><i class="fas fa-play-circle"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="glass-card stat-card h-100 clickable-card" data-filter="finalizado" title="Mostrar sesiones finalizadas">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-title">Finalizadas</div>
                        <div class="stat-value" id="stat-finalizadas"><?php echo $sesionesFinalizadas; ?></div>
                    </div>
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="glass-card stat-card h-100 clickable-card" data-filter="anulado" title="Mostrar sesiones anuladas">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-title">Anuladas</div>
                        <div class="stat-value" id="stat-anuladas"><?php echo $sesionesAnuladas; ?></div>
                    </div>
                    <div class="stat-icon"><i class="fas fa-ban"></i></div>
                </div>
            </div>
        </div>
    </div>

    <div class="glass-card p-4">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Cliente</th>
                        <th>Equipo</th>
                        <th>Inicio</th>
                        <th>Fin</th>
                        <th>Minutos</th>
                        <th>Tarifa</th>
                        <th>Total</th>
                        <th>Comprobante</th>
                        <th>Estado</th>
                        <th>Operador</th>
                        <th class="text-end">ACCIONES</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($resultado) === 0): ?>
                        <tr><td colspan="12" class="text-center text-white-50 py-4">No hay sesiones registradas.</td></tr>
                    <?php else: ?>
                        <?php while ($row = mysqli_fetch_assoc($resultado)): 
                            if ($row['estado_transaccion'] === 'en_curso') {
                                $estado_class = 'bg-success';
                                $actionButton = '<button type="button" class="btn btn-sm btn-outline-info action-session" data-action="finalize" title="Finalizar sesión"><i class="fas fa-check"></i></button>';
                                $actionButton .= '<button type="button" class="btn btn-sm btn-outline-danger action-session ms-1" data-action="annul" title="Anular sesión"><i class="fas fa-ban"></i></button>';
                            } elseif ($row['estado_transaccion'] === 'finalizado') {
                                $estado_class = 'bg-primary';
                                $actionButton = '<button type="button" class="btn btn-sm btn-outline-secondary disabled" title="Sesión finalizada" disabled><i class="fas fa-lock"></i></button>';
                            } elseif ($row['estado_transaccion'] === 'anulado') {
                                $estado_class = 'bg-danger';
                                $actionButton = '<button type="button" class="btn btn-sm btn-outline-secondary disabled" title="Sesión anulada" disabled><i class="fas fa-lock"></i></button>';
                            } else {
                                $estado_class = 'bg-secondary';
                                $actionButton = '<button type="button" class="btn btn-sm btn-outline-secondary disabled" title="Estado no válido" disabled><i class="fas fa-lock"></i></button>';
                            }
                            $cliente = trim($row['cliente_nombre'] . ' ' . $row['cliente_apellido']);
                            $equipo = 'PC-' . str_pad($row['numero_puesto'] ?? $row['computadora_id'], 2, '0', STR_PAD_LEFT);
                            $equipo .= ' ' . ($row['direccion_ip'] ? '(' . $row['direccion_ip'] . ')' : '');
                        ?>
                        <tr data-session-id="<?php echo $row['id']; ?>"
                            data-session-state="<?php echo $row['estado_transaccion']; ?>"
                            data-session-client="<?php echo htmlspecialchars($cliente); ?>"
                            data-session-equipo="<?php echo htmlspecialchars($equipo); ?>"
                            data-session-inicio="<?php echo htmlspecialchars($row['hora_inicio']); ?>"
                            data-session-fin="<?php echo htmlspecialchars($row['hora_fin'] ?? '-'); ?>"
                            data-session-minutos="<?php echo htmlspecialchars($row['minutos_consumidos'] ?? '-'); ?>"
                            data-session-tarifa="<?php echo htmlspecialchars(number_format($row['monto_tarifa_aplicada'], 2)); ?>"
                            data-session-total="<?php echo htmlspecialchars(number_format($row['monto_total_pagado'] ?? 0, 2)); ?>"
                            data-session-comprobante="<?php echo htmlspecialchars($row['comprobante_factura'] ?? '-'); ?>"
                            data-session-operador="<?php echo htmlspecialchars($row['operador_nombre'] ?? 'Sistema'); ?>">
                            <td><?php echo $row['id']; ?></td>
                            <td><?php echo htmlspecialchars($cliente ?: 'Cliente desconocido'); ?></td>
                            <td><?php echo htmlspecialchars($equipo); ?></td>
                            <td><?php echo $row['hora_inicio']; ?></td>
                            <td><?php echo $row['hora_fin'] ?? '<span class="text-white-50">-</span>'; ?></td>
                            <td><?php echo $row['minutos_consumidos'] ?? '-'; ?></td>
                            <td>$ <?php echo number_format($row['monto_tarifa_aplicada'], 2); ?></td>
                            <td>$ <?php echo number_format($row['monto_total_pagado'] ?? 0, 2); ?></td>
                            <td><?php echo htmlspecialchars($row['comprobante_factura'] ?? '-'); ?></td>
                            <td><span class="badge status-badge <?php echo $estado_class; ?>"><?php echo $row['estado_transaccion']; ?></span></td>
                            <td><?php echo htmlspecialchars($row['operador_nombre'] ?? 'Sistema'); ?></td>
                            <td class="text-end">
                                <div class="btn-group">
                                    <button type="button" class="btn btn-sm btn-outline-light view-session" title="Ver detalles de la sesión"><i class="fas fa-eye"></i></button>
                                    <?php echo $actionButton; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="sessionDetailModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content glass-modal">
            <div class="modal-header">
                <h5 class="modal-title">Detalles de Sesión</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-white">
                <dl class="row">
                    <dt class="col-sm-4">Cliente</dt>
                    <dd class="col-sm-8" id="sessionDetailCliente"></dd>
                    <dt class="col-sm-4">Equipo</dt>
                    <dd class="col-sm-8" id="sessionDetailEquipo"></dd>
                    <dt class="col-sm-4">Inicio</dt>
                    <dd class="col-sm-8" id="sessionDetailInicio"></dd>
                    <dt class="col-sm-4">Fin</dt>
                    <dd class="col-sm-8" id="sessionDetailFin"></dd>
                    <dt class="col-sm-4">Minutos</dt>
                    <dd class="col-sm-8" id="sessionDetailMinutos"></dd>
                    <dt class="col-sm-4">Tarifa</dt>
                    <dd class="col-sm-8" id="sessionDetailTarifa"></dd>
                    <dt class="col-sm-4">Total</dt>
                    <dd class="col-sm-8" id="sessionDetailTotal"></dd>
                    <dt class="col-sm-4">Comprobante</dt>
                    <dd class="col-sm-8" id="sessionDetailComprobante"></dd>
                    <dt class="col-sm-4">Operador</dt>
                    <dd class="col-sm-8" id="sessionDetailOperador"></dd>
                    <dt class="col-sm-4">Estado</dt>
                    <dd class="col-sm-8" id="sessionDetailEstado"></dd>
                </dl>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmActionModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-modal">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmActionTitle"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-white" id="confirmActionBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="confirmActionButton">Confirmar</button>
            </div>
        </div>
    </div>
</div>

<script>
    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        return text.toString().replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function showConfirmModal(title, message, onConfirm) {
        const modalEl = document.getElementById('confirmActionModal');
        const modal = new bootstrap.Modal(modalEl);
        document.getElementById('confirmActionTitle').innerText = title;
        document.getElementById('confirmActionBody').innerHTML = message;

        const button = document.getElementById('confirmActionButton');
        const newButton = button.cloneNode(true);
        button.parentNode.replaceChild(newButton, button);
        newButton.addEventListener('click', () => {
            modal.hide();
            onConfirm();
        });

        modal.show();
    }

    function showMessage(title, message) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: title,
                text: message,
                icon: 'info',
                confirmButtonText: 'Cerrar',
                background: '#141414',
                color: '#ffffff'
            });
            return;
        }

        const modalEl = document.getElementById('sessionDetailModal');
        const modal = new bootstrap.Modal(modalEl);
        document.querySelector('#sessionDetailModal .modal-title').innerText = title;
        document.querySelector('#sessionDetailModal .modal-body').innerHTML = '<p>' + escapeHtml(message) + '</p>';
        modal.show();
    }

    function updateSessionRow(row, newState, data = null) {
        const statusBadge = row.querySelector('span.status-badge');
        if (!statusBadge) return;

        const oldState = row.dataset.sessionState;

        let badgeClass = 'bg-secondary';
        if (newState === 'en_curso') badgeClass = 'bg-success';
        if (newState === 'finalizado') badgeClass = 'bg-primary';
        if (newState === 'anulado') badgeClass = 'bg-danger';

        // Actualizar estadísticas superiores si el estado cambió desde "en_curso"
        if (oldState === 'en_curso' && (newState === 'finalizado' || newState === 'anulado')) {
            const statActivas = document.getElementById('stat-activas');
            if (statActivas) {
                statActivas.textContent = Math.max(0, parseInt(statActivas.textContent) - 1);
            }
            
            if (newState === 'finalizado') {
                const statFinalizadas = document.getElementById('stat-finalizadas');
                if (statFinalizadas) statFinalizadas.textContent = parseInt(statFinalizadas.textContent) + 1;
            } else if (newState === 'anulado') {
                const statAnuladas = document.getElementById('stat-anuladas');
                if (statAnuladas) statAnuladas.textContent = parseInt(statAnuladas.textContent) + 1;
            }
        }

        statusBadge.className = 'badge status-badge ' + badgeClass;
        statusBadge.textContent = newState;
        row.dataset.sessionState = newState;

        if (data) {
            // Actualizar celdas de la tabla (Fin, Minutos, Total, Comprobante)
            if (row.cells[4]) row.cells[4].innerText = data.hora_fin || '-';
            if (row.cells[5]) row.cells[5].innerText = data.minutos_consumidos || '0';
            if (row.cells[7]) row.cells[7].innerText = '$ ' + parseFloat(data.monto_total_pagado || 0).toFixed(2);
            if (row.cells[8]) row.cells[8].innerText = data.comprobante_factura || '-';

            // Actualizar atributos data para que el modal de detalles también refleje los cambios
            row.dataset.sessionFin = data.hora_fin || '-';
            row.dataset.sessionMinutos = data.minutos_consumidos || '0';
            row.dataset.sessionTotal = parseFloat(data.monto_total_pagado || 0).toFixed(2);
            row.dataset.sessionComprobante = data.comprobante_factura || '-';
        }

        const actionGroup = row.querySelector('.btn-group');
        if (actionGroup) {
            actionGroup.innerHTML = '<button type="button" class="btn btn-sm btn-outline-secondary disabled" title="Sesión ' + escapeHtml(newState) + '" disabled><i class="fas fa-lock"></i></button>';
        }
    }

    async function sendSessionAction(action, sessionId) {
        const data = new FormData();
        data.append('action', action);
        data.append('id', sessionId);
        data.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');

        const response = await fetch(window.location.href, {
            method: 'POST',
            credentials: 'same-origin',
            body: data,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });

        const text = await response.text();
        try {
            return JSON.parse(text);
        } catch (error) {
            return { success: false, message: 'Respuesta inválida del servidor.' };
        }
    }

    function attachSessionHandlers() {
        document.querySelectorAll('.view-session').forEach(btn => {
            btn.addEventListener('click', () => {
                const row = btn.closest('tr');
                if (!row) return;
                document.getElementById('sessionDetailCliente').innerText = row.dataset.sessionClient;
                document.getElementById('sessionDetailEquipo').innerText = row.dataset.sessionEquipo;
                document.getElementById('sessionDetailInicio').innerText = row.dataset.sessionInicio;
                document.getElementById('sessionDetailFin').innerText = row.dataset.sessionFin;
                document.getElementById('sessionDetailMinutos').innerText = row.dataset.sessionMinutos;
                document.getElementById('sessionDetailTarifa').innerText = '$ ' + row.dataset.sessionTarifa;
                document.getElementById('sessionDetailTotal').innerText = '$ ' + row.dataset.sessionTotal;
                document.getElementById('sessionDetailComprobante').innerText = row.dataset.sessionComprobante;
                document.getElementById('sessionDetailOperador').innerText = row.dataset.sessionOperador;
                document.getElementById('sessionDetailEstado').innerText = row.dataset.sessionState;
                new bootstrap.Modal(document.getElementById('sessionDetailModal')).show();
            });
        });

        document.querySelectorAll('.action-session').forEach(btn => {
            btn.addEventListener('click', () => {
                const row = btn.closest('tr');
                if (!row) return;
                const sessionId = row.dataset.sessionId;
                const action = btn.dataset.action === 'annul' ? 'annul' : 'finalize';
                const title = action === 'annul' ? 'Anular sesión' : 'Finalizar sesión';
                const message = action === 'annul'
                    ? `¿Deseas anular la sesión de <strong>${escapeHtml(row.dataset.sessionClient)}</strong> en el equipo <strong>${escapeHtml(row.dataset.sessionEquipo)}</strong>?`
                    : `¿Deseas finalizar la sesión de <strong>${escapeHtml(row.dataset.sessionClient)}</strong> en el equipo <strong>${escapeHtml(row.dataset.sessionEquipo)}</strong>?`;

                showConfirmModal(title, message, async () => {
                    const result = await sendSessionAction(action, sessionId);
                    if (result.success) {
                        updateSessionRow(row, action === 'annul' ? 'anulado' : 'finalizado', result.data);
                        showMessage('Éxito', result.message);
                    } else {
                        showMessage('Error', result.message);
                    }
                });
            });
        });
    }

    function attachCardFilters() {
        document.querySelectorAll('.clickable-card').forEach(card => {
                card.addEventListener('click', function(){
                    const filter = this.dataset.filter;
                    const alreadyActive = this.classList.contains('active-filter');
                const rows = document.querySelectorAll('table.table tbody tr');
                let firstVisible = null;
                    if(alreadyActive){
                        // clear filter
                        document.querySelectorAll('.clickable-card').forEach(c => c.classList.remove('active-filter'));
                        rows.forEach(r => { r.classList.remove('dimmed'); r.classList.remove('highlighted'); });
                        return;
                    }

                    document.querySelectorAll('.clickable-card').forEach(c => c.classList.remove('active-filter'));
                    this.classList.add('active-filter');

                    rows.forEach(r => {
                        const s = r.dataset.sessionState || r.getAttribute('data-session-state') || '';
                        if(filter === 'all' || s === filter){
                            r.classList.remove('dimmed');
                            r.classList.add('highlighted');
                            if(!firstVisible) firstVisible = r;
                        } else {
                            r.classList.remove('highlighted');
                            r.classList.add('dimmed');
                        }
                    });

                    if(firstVisible){
                        firstVisible.scrollIntoView({behavior: 'smooth', block: 'center'});
                    }
                    setTimeout(() => { rows.forEach(r => r.classList.remove('highlighted')); }, 4000);
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function(){
        attachSessionHandlers();
        attachCardFilters();
    });
</script>

<?php include_once "includes/footer.php"; ?>
