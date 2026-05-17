<?php
include_once "includes/header.php";
require_once "../conexion.php";

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$id_usuario_sesion = $_SESSION['id'] ?? $_SESSION['id_usuario'] ?? $_SESSION['usuario_id'] ?? 0;


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Token CSRF inválido o sesión expirada. Por favor, recarga la página.']);
        exit;
    }

    $action = $_POST['action'] ?? '';
    $id = intval($_POST['id'] ?? 0); 
    $usuario_sesion = $_SESSION['nombre'] ?? 'Sistema';
    $ip = $_SERVER['REMOTE_ADDR'];
    $fecha_hora = date('Y-m-d H:i:s');
    $sector = "Usuarios";

    //REGISTRAR NUEVO USUARIO
    if ($action === 'create') {
        $nombre_completo = trim($_POST['nombre_completo'] ?? '');
        $cedula_identidad = trim($_POST['cedula_identidad'] ?? '');
        $correo_institucional = trim($_POST['correo_institucional'] ?? '');
        $rol = trim($_POST['rol'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($nombre_completo) || empty($cedula_identidad) || empty($correo_institucional) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Todos los campos son obligatorios.']);
            exit;
        }

        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        $activo = 1;

        $stmt = $conexion->prepare("INSERT INTO usuarios (nombre_completo, cedula_identidad, correo_institucional, password_hash, rol, activo, creado_en) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        if ($stmt) {
            $stmt->bind_param("sssssis", $nombre_completo, $cedula_identidad, $correo_institucional, $password_hash, $rol, $activo, $fecha_hora);
            
            if ($stmt->execute()) {
                $accion_historial = "Registró al usuario: " . $nombre_completo;
                $stmt_h = $conexion->prepare("INSERT INTO historial (usuario, ip, fyh, sector, acciones) VALUES (?, ?, ?, ?, ?)");
                if ($stmt_h) {
                    $stmt_h->bind_param("sssss", $usuario_sesion, $ip, $fecha_hora, $sector, $accion_historial);
                    $stmt_h->execute();
                    $stmt_h->close();
                }
                echo json_encode(['success' => true, 'message' => 'Usuario registrado exitosamente.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error de Base de Datos: ' . $stmt->error]);
            }
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al preparar la consulta SQL: ' . $conexion->error]);
        }
        exit;
    }

    // ACTUALIZAR USUARIO EXISTENTE
    if ($action === 'update') {
        $nombre_completo = trim($_POST['nombre_completo'] ?? '');
        $cedula_identidad = trim($_POST['cedula_identidad'] ?? '');
        $correo_institucional = trim($_POST['correo_institucional'] ?? '');
        $rol = trim($_POST['rol'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($id <= 0 || empty($nombre_completo) || empty($cedula_identidad) || empty($correo_institucional)) {
            echo json_encode(['success' => false, 'message' => 'Datos insuficientes para actualizar.']);
            exit;
        }

        if (!empty($password)) {
            $password_hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $conexion->prepare("UPDATE usuarios SET nombre_completo = ?, cedula_identidad = ?, correo_institucional = ?, password_hash = ?, rol = ? WHERE id = ?");
            $stmt->bind_param("sssssi", $nombre_completo, $cedula_identidad, $correo_institucional, $password_hash, $rol, $id);
        } else {
            $stmt = $conexion->prepare("UPDATE usuarios SET nombre_completo = ?, cedula_identidad = ?, correo_institucional = ?, rol = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $nombre_completo, $cedula_identidad, $correo_institucional, $rol, $id);
        }

        if ($stmt && $stmt->execute()) {
            $accion_historial = "Modificó los datos del usuario ID: " . $id;
            $stmt_h = $conexion->prepare("INSERT INTO historial (usuario, ip, fyh, sector, acciones) VALUES (?, ?, ?, ?, ?)");
            if ($stmt_h) {
                $stmt_h->bind_param("sssss", $usuario_sesion, $ip, $fecha_hora, $sector, $accion_historial);
                $stmt_h->execute();
                $stmt_h->close();
            }
            echo json_encode(['success' => true, 'message' => 'Usuario actualizado con éxito.']);
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al actualizar el usuario.']);
        }
        exit;
    }

    //DESACTIVAR USUARIO
    if ($action === 'deactivate') {
        if (($id_usuario_sesion > 0 && $id === $id_usuario_sesion) || (isset($_POST['current_logged_id']) && $id === intval($_POST['current_logged_id']))) {
            echo json_encode(['success' => false, 'message' => 'No puedes desactivar tu propia cuenta de usuario en sesión.']);
            exit;
        }

        $stmt = $conexion->prepare("UPDATE usuarios SET activo = 0 WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt && $stmt->execute()) {
            $accion_historial = "Desactivó al usuario ID: " . $id;
            $stmt_h = $conexion->prepare("INSERT INTO historial (usuario, ip, fyh, sector, acciones) VALUES (?, ?, ?, ?, ?)");
            if ($stmt_h) {
                $stmt_h->bind_param("sssss", $usuario_sesion, $ip, $fecha_hora, $sector, $accion_historial);
                $stmt_h->execute();
                $stmt_h->close();
            }
            echo json_encode(['success' => true, 'message' => 'Usuario desactivado correctamente.']);
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'No se pudo desactivar al usuario.']);
        }
        exit;
    }

    // ACTIVAR USUARIO
    if ($action === 'activate') {
        $stmt = $conexion->prepare("UPDATE usuarios SET activo = 1 WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt && $stmt->execute()) {
            $accion_historial = "Activó al usuario ID: " . $id;
            $stmt_h = $conexion->prepare("INSERT INTO historial (usuario, ip, fyh, sector, acciones) VALUES (?, ?, ?, ?, ?)");
            if ($stmt_h) {
                $stmt_h->bind_param("sssss", $usuario_sesion, $ip, $fecha_hora, $sector, $accion_historial);
                $stmt_h->execute();
                $stmt_h->close();
            }
            echo json_encode(['success' => true, 'message' => 'Usuario activado correctamente.']);
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'No se pudo activar al usuario.']);
        }
        exit;
    }
}

$nombre = $_SESSION['nombre'] ?? 'Sistema';
$ip = $_SERVER['REMOTE_ADDR'];
$fecha_hora = date('Y-m-d H:i:s');
$sector = "Usuarios";
$accion = "Acceso a la sección de gestión de usuarios";
$stmt = $conexion->prepare("INSERT INTO historial (usuario, ip, fyh, sector, acciones) VALUES (?, ?, ?, ?, ?)");
if ($stmt) {
    $stmt->bind_param("sssss", $nombre, $ip, $fecha_hora, $sector, $accion);
    $stmt->execute();
    $stmt->close();
}

$query = "SELECT * FROM usuarios ORDER BY id ASC";
$resultado = mysqli_query($conexion, $query);
?>

<style>
    .table { color: var(--text-light); margin-bottom: 0; border-collapse: separate; border-spacing: 0; }
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
    .table tbody tr { transition: all 0.3s ease; }
    .table tbody tr:hover { background: rgba(0, 0, 0, 0.3) !important; }
    .status-badge { border-radius: 20px; padding: 0.45rem 0.85rem; font-size: 0.65rem; text-transform: uppercase; font-weight: 800; letter-spacing: 0.5px; }
    .glass-card { padding: 0 !important; overflow: hidden; border: 1px solid rgba(255, 255, 255, 0.1); }
    .table-responsive { border-radius: 24px; }
    .table tbody td { color: #ffffff !important; } 
    .text-white-50 { color: rgba(255, 255, 255, 0.85) !important; } 
    .text-muted { color: rgba(255, 255, 255, 0.75) !important; }
</style>

    <div class="container main-content pb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-0 text-white">Gestión de Usuarios</h2>
                <p class="text-white-50">Administración y control de usuarios del sistema</p>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="fas fa-plus me-2"></i>Registrar Usuario
            </button>
        </div>

        <div class="glass-card p-4">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Correo Electrónico</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th>Creado</th>
                            <th class="text-end">ACCIONES</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($resultado)) { 
                            $es_usuario_actual = false;
                            if ($id_usuario_sesion > 0 && $row['id'] == $id_usuario_sesion) {
                                $es_usuario_actual = true;
                            } elseif (isset($_SESSION['nombre']) && $row['nombre_completo'] === $_SESSION['nombre']) {
                                $es_usuario_actual = true;
                            }
                        ?>
                            <tr data-id="<?php echo htmlspecialchars($row['id']); ?>" 
                                data-nombre="<?php echo htmlspecialchars($row['nombre_completo']); ?>" 
                                data-cedula="<?php echo htmlspecialchars($row['cedula_identidad']); ?>" 
                                data-correo="<?php echo htmlspecialchars($row['correo_institucional']); ?>" 
                                data-rol="<?php echo htmlspecialchars($row['rol']); ?>"
                                <?php if($es_usuario_actual) echo 'data-session="true"'; ?>>
                                
                                <td><?php echo htmlspecialchars($row['id']); ?></td>
                                <td><?php echo htmlspecialchars($row['nombre_completo']); ?></td>
                                <td><?php echo htmlspecialchars($row['correo_institucional']); ?></td>
                                <td><?php echo htmlspecialchars($row['rol']); ?></td>
                                <td>
                                    <?php if ($row['activo'] == 1) { ?>
                                        <span class="status-badge bg-success">Activo</span>
                                    <?php } else { ?>
                                        <span class="status-badge bg-danger">Inactivo</span>
                                    <?php } ?>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($row['creado_en'])); ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-light me-2 edit-user"><i class="fas fa-edit"></i> </button>
                                    
                                    <?php if ($row['activo'] == 1) { ?>
                                        <?php if ($es_usuario_actual) { ?>
                                            <button class="btn btn-sm btn-outline-secondary self-block-btn" disabled title="No puedes desactivar tu propio usuario"><i class="fas fa-ban"></i> Fijo</button>
                                        <?php } else { ?>
                                            <button class="btn btn-sm btn-outline-danger delete-user"><i class="fas fa-trash-alt"></i></button>
                                        <?php } ?>
                                    <?php } else { ?>
                                        <button class="btn btn-sm btn-outline-success activate-user"><i class="fas fa-check"></i></button>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-modal">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-plus"></i> Nuevo Usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addUserForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                    <div class="mb-3">
                        <label class="form-label">Nombre completo</label>
                        <input type="text" name="nombre_completo" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Cédula de Identidad</label>
                        <input type="text" name="cedula_identidad" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Correo institucional</label>
                        <input type="email" name="correo_institucional" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Rol</label>
                        <select name="rol" class="form-select" required>
                            <option value="super_admin">Super Administrador</option>
                            <option value="auditor">Auditor</option>
                            <option value="operador">Operador</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contraseña</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Crear Usuario</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-modal">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-edit"></i> Editar Usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editForm">
                    <input type="hidden" name="id" id="edit_id">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                    <div class="mb-3">
                        <label class="form-label">Nombre completo</label>
                        <input type="text" name="nombre_completo" id="edit_nombre" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Cédula</label>
                        <input type="text" name="cedula_identidad" id="edit_cedula" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Correo</label>
                        <input type="email" name="correo_institucional" id="edit_correo" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Rol</label>
                        <select name="rol" id="edit_rol" class="form-select" required>
                            <option value="super_admin">Super Administrador</option>
                            <option value="auditor">Auditor</option>
                            <option value="operador">Operador</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nueva contraseña <small class="text-muted">(dejar vacío para mantener)</small></label>
                        <input type="password" name="password" id="edit_password" class="form-control">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Guardar cambios</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content glass-modal"><div class="modal-header"><h5 class="modal-title" id="confirmModalTitle">Confirmar</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body" id="confirmModalBody"></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="button" class="btn btn-primary" id="confirmModalBtn">Aceptar</button></div></div></div></div>
<div class="modal fade" id="messageModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content glass-modal"><div class="modal-header"><h5 class="modal-title" id="messageModalTitle">Aviso</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body" id="messageModalBody"></div><div class="modal-footer"><button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button></div></div></div></div>

<script>
    // Para localizar dinámicamente la ID basándonos en la fila marcada con data-session
    const rowSesion = document.querySelector('tr[data-session="true"]');
    const CURRENT_USER_ID = rowSesion ? parseInt(rowSesion.dataset.id) : <?= intval($id_usuario_sesion) ?>;

    function escapeHtml(text) {
        if (!text) return '';
        return text.toString().replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

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

    async function sendAction(action, userId, formData = null) {
        let data = new FormData();
        data.append('action', action);
        data.append('id', userId);
        data.append('csrf_token', '<?= $_SESSION['csrf_token'] ?? '' ?>');
        data.append('current_logged_id', CURRENT_USER_ID);
        
        if (formData) {
            for (let [key, val] of formData.entries()) {
                if(key !== 'action' && key !== 'id' && key !== 'csrf_token') data.append(key, val);
            }
        }
        try {
            const res = await fetch(window.location.href, {
                method: 'POST',
                body: data,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            
            const responseText = await res.text();
            try {
                return JSON.parse(responseText);
            } catch(e) {
                console.error("Error analizando JSON. Respuesta del servidor:", responseText);
                return { success: false, message: 'Respuesta inválida del servidor. Revisa los registros.' };
            }
        } catch (error) {
            console.error("Error en petición fetch:", error);
            return { success: false, message: 'Error de comunicación con el servidor.' };
        }
    }

    //Agregar usuario
    document.getElementById('addUserForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        bootstrap.Modal.getInstance(document.getElementById('addUserModal')).hide();
        
        const result = await sendAction('create', 0, formData);
        if (result.success) {
            showMessageModal('Éxito', result.message);
            setTimeout(() => location.reload(), 1200);
        } else {
            showMessageModal('Error en Registro', result.message);
        }
    });

    //Editar usuario
    document.querySelectorAll('.edit-user').forEach(btn => {
        btn.addEventListener('click', () => {
            const row = btn.closest('tr');
            document.getElementById('edit_id').value = row.dataset.id;
            document.getElementById('edit_nombre').value = row.dataset.nombre;
            document.getElementById('edit_cedula').value = row.dataset.cedula;
            document.getElementById('edit_correo').value = row.dataset.correo;
            document.getElementById('edit_rol').value = row.dataset.rol;
            document.getElementById('edit_password').value = '';
            new bootstrap.Modal(document.getElementById('editModal')).show();
        });
    });

    document.getElementById('editForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const userId = formData.get('id');
        bootstrap.Modal.getInstance(document.getElementById('editModal')).hide();
        
        const result = await sendAction('update', userId, formData);
        if (result.success) {
            showMessageModal('Éxito', result.message);
            setTimeout(() => location.reload(), 1200);
        } else {
            showMessageModal('Error al Editar', result.message);
        }
    });

    //Desactivar usuario
    document.querySelectorAll('.delete-user').forEach(btn => {
        btn.addEventListener('click', () => {
            const row = btn.closest('tr');
            const userId = parseInt(row.dataset.id);
            const nombre = row.dataset.nombre;

            if (userId === CURRENT_USER_ID || row.hasAttribute('data-session')) {
                showMessageModal('Operación denegada', 'No puedes desactivar la cuenta con la que estás logueado en este momento.');
                return;
            }

            showConfirmModal(
                'Confirmar desactivación',
                `¿Está seguro de desactivar al usuario <strong>${escapeHtml(nombre)}</strong>? Perderá acceso al sistema.`,
                async () => {
                    const result = await sendAction('deactivate', userId);
                    if (result.success) {
                        showMessageModal('Éxito', result.message);
                        setTimeout(() => location.reload(), 1200);
                    } else {
                        showMessageModal('Operación denegada', result.message);
                    }
                }
            );
        });
    });

    //Activar usuario
    document.querySelectorAll('.activate-user').forEach(btn => {
        btn.addEventListener('click', () => {
            const row = btn.closest('tr');
            const userId = row.dataset.id;
            const nombre = row.dataset.nombre;
            showConfirmModal(
                'Confirmar activación',
                `¿Activar al usuario <strong>${escapeHtml(nombre)}</strong>? Podrá iniciar sesión nuevamente.`,
                async () => {
                    const result = await sendAction('activate', userId);
                    if (result.success) {
                        showMessageModal('Éxito', result.message);
                        setTimeout(() => location.reload(), 1200);
                    } else {
                        showMessageModal('Error al Activar', result.message);
                    }
                }
            );
        });
    });
</script>

<?php include_once "includes/footer.php"; ?>
