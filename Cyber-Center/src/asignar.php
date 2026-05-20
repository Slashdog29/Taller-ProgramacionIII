<?php
include_once "includes/header.php";
require_once __DIR__ . "/../conexion.php"; // $conexion es la conexión mysqli procedimental

$mensaje = '';

$usuario_operador_id = intval($_SESSION['id'] ?? $_SESSION['id_usuario'] ?? $_SESSION['usuario_id'] ?? 0);

// Obtener id de cliente
$id_cliente = intval($_GET['id_cliente'] ?? 0);

// Procesamiento del POST cuando se envía el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_cliente = intval($_POST['id_cliente'] ?? 0);
    $id_computadora = intval($_POST['id_computadora'] ?? 0);
    $minutos = intval($_POST['minutos_duracion'] ?? 0);

    if ($id_cliente <= 0 || $id_computadora <= 0 || $minutos <= 0) {
        $mensaje = "Error: datos incompletos o inválidos.";
    } else {
        // Validación antirreingreso
        $sql_check = "SELECT COUNT(*) as cnt FROM sesiones WHERE cliente_id = ? AND DATE(hora_inicio) = CURDATE()";
        $stmt = mysqli_prepare($conexion, $sql_check);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $id_cliente);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_bind_result($stmt, $cnt);
            mysqli_stmt_fetch($stmt);
            mysqli_stmt_close($stmt);

            if ($cnt > 0) {
                $mensaje = "El cliente ya tiene una sesión registrada hoy. No se permite reingreso.";
            } else {
                $check_col_sql = "SHOW COLUMNS FROM sesiones LIKE 'hora_fin_estimada'";
                $col_exists = false;
                $res_col = mysqli_query($conexion, $check_col_sql);
                if ($res_col) {
                    if (mysqli_num_rows($res_col) > 0) $col_exists = true;
                    mysqli_free_result($res_col);
                }
                if (!$col_exists) {
                    @mysqli_query($conexion, "ALTER TABLE sesiones ADD COLUMN hora_fin_estimada TIMESTAMP NULL DEFAULT NULL AFTER hora_inicio");
                }

                if ($usuario_operador_id <= 0) {
                    $usuario_operador_id = 1;
                }
                // Obtener tarifa del tipo de cliente (si existe)
                $tarifa = 0.00;
                $tipoSql = "SELECT COALESCE(t.tarifa_por_hora,0) AS tarifa, COALESCE(t.exento_pago,0) AS exento
                            FROM clientes c
                            LEFT JOIN tipos_cliente t ON c.tipo_cliente_id = t.id
                            WHERE c.id = ? LIMIT 1";
                $tipoStmt = mysqli_prepare($conexion, $tipoSql);
                if ($tipoStmt) {
                    mysqli_stmt_bind_param($tipoStmt, 'i', $id_cliente);
                    mysqli_stmt_execute($tipoStmt);
                    mysqli_stmt_bind_result($tipoStmt, $tarifa_val, $exento_val);
                    if (mysqli_stmt_fetch($tipoStmt)) {
                        $tarifa = floatval($tarifa_val);
                        if (intval($exento_val) === 1) $tarifa = 0.00;
                    }
                    mysqli_stmt_close($tipoStmt);
                }

                $sql_insert = "INSERT INTO sesiones (cliente_id, computadora_id, usuario_operador_id, hora_inicio, hora_fin_estimada, monto_tarifa_aplicada, estado_transaccion) VALUES (?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? MINUTE), ?, 'en_curso')";
                $stmt2 = mysqli_prepare($conexion, $sql_insert);
                if ($stmt2) {
                    mysqli_stmt_bind_param($stmt2, 'iiiid', $id_cliente, $id_computadora, $usuario_operador_id, $minutos, $tarifa);
                    $ok = mysqli_stmt_execute($stmt2);
                    if ($ok) {
    
                        $sql_up = "UPDATE computadoras SET estado_operativo = 'ocupado' WHERE id = ?";
                        $stmt_up = mysqli_prepare($conexion, $sql_up);
                        if ($stmt_up) {
                            mysqli_stmt_bind_param($stmt_up, 'i', $id_computadora);
                            mysqli_stmt_execute($stmt_up);
                            mysqli_stmt_close($stmt_up);
                        }

                        $mensaje = "Asignación completada correctamente.";
                    } else {
                        $mensaje = "Error al insertar la sesión: " . mysqli_error($conexion);
                    }
                    mysqli_stmt_close($stmt2);
                } else {
                    $mensaje = "Error al preparar la inserción: " . mysqli_error($conexion);
                }
            }
        } else {
            $mensaje = "Error al preparar la verificación: " . mysqli_error($conexion);
        }
    }
}

// Consulta de computadoras disponibles con marca y modelo reales
$sql_comp = "SELECT comp.id, comp.numero_puesto, comp.codigo_bien_nacional, comp.direccion_ip, comp.modelo, m.nombremarca
             FROM computadoras comp
             LEFT JOIN marca m ON m.id_marca = comp.marca
             WHERE comp.estado_operativo = 'disponible'
             ORDER BY comp.numero_puesto ASC";
$res_comp = mysqli_query($conexion, $sql_comp);
if ($res_comp === false) {
    $mensaje = "Error al obtener las computadoras: " . mysqli_error($conexion);
}
?>

<div class="container main-content py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="fw-bold mb-0 text-white">Asignar dispositivo</h3>
            <p class="text-white-50">Asignación de equipos</p>
        </div>
        <a href="clientes.php" class="btn btn-secondary">Volver a Clientes</a>
    </div>

    <div class="glass-card p-4">
        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-info"><?php echo htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>

        <form method="post" class="row g-3" novalidate>
            <input type="hidden" name="id_cliente" value="<?php echo intval($id_cliente); ?>">

            <div class="col-md-6">
                <label class="form-label">Seleccionar computadora (disponible)</label>
                <select name="id_computadora" class="form-select" required>
                    <option value="">-- Seleccione --</option>
                    <?php
                    if ($res_comp && mysqli_num_rows($res_comp) > 0) {
                        while ($row = mysqli_fetch_assoc($res_comp)) {
                            $id_comp = intval($row['id'] ?? 0);
                            $label_parts = [];
                            $label_parts[] = 'PC-' . str_pad(intval($row['numero_puesto'] ?? $id_comp), 2, '0', STR_PAD_LEFT);
                            if (!empty($row['nombremarca'])) $label_parts[] = $row['nombremarca'];
                            if (!empty($row['modelo'])) $label_parts[] = $row['modelo'];
                            if (!empty($row['codigo_bien_nacional'])) $label_parts[] = '[' . $row['codigo_bien_nacional'] . ']';
                            if (!empty($row['direccion_ip'])) $label_parts[] = '(' . $row['direccion_ip'] . ')';

                            $label = implode(' ', $label_parts);
                            echo '<option value="' . $id_comp . '">' . htmlspecialchars($label) . '</option>';
                        }
                    } else {
                        echo '<option value="">No hay computadoras disponibles</option>';
                    }
                    ?>
                </select>
                <div class="form-text text-white">Si no aparecen equipos, verifica el campo <strong>estado_operativo</strong> en la tabla de computadoras.</div>
            </div>

            <div class="col-md-4">
                <label class="form-label">Duración (minutos)</label>
                <select name="minutos_duracion" class="form-select" required>
                    <option value="">--  Seleccione minutos --</option>
                    <?php for ($m = 15; $m <= 240; $m += 15): ?>
                        <option value="<?php echo $m; ?>"><?php echo $m; ?> minutos</option>
                    <?php endfor; ?>
                </select>
                <div class="form-text text-white">Los bloques incrementan de 15 en 15 hasta 240 minutos (4 horas).</div>
            </div>

            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-success w-100">Asignar</button>
            </div>
        </form>
    </div>
</div>

<?php include_once "includes/footer.php"; ?>
