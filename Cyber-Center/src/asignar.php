<?php
// asignar.php
// Interfaz y Lógica de Asignación de dispositivo a cliente
// Requiere: conexión MySQL procedimental con $conexion definido en ../conexion.php

include_once "includes/header.php";
require_once __DIR__ . "/../conexion.php"; // $conexion es la conexión mysqli procedimental

// -----------------------------------------------------------------------------
// NOTAS: Cambia los nombres de tablas/columnas abajo según tu esquema de BD.
// - Tabla de computadoras: 'computadoras' (columnas: id, nombre, ip_address, estado_operativo)
// - Tabla de sesiones: 'sesiones' (columnas: id, id_cliente, id_computadora, hora_inicio, hora_fin_estimada, estado_transaccion, hora_fin)
// -----------------------------------------------------------------------------

$mensaje = '';

// Obtener id_cliente (viene por GET cuando se hace clic en el botón desde clientes.php)
$id_cliente = intval($_GET['id_cliente'] ?? 0);

// Procesamiento del POST cuando se envía el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recibir campos esperados
    $id_cliente = intval($_POST['id_cliente'] ?? 0);
    $id_computadora = intval($_POST['id_computadora'] ?? 0);
    $minutos = intval($_POST['minutos_duracion'] ?? 0);

    // Validaciones básicas
    if ($id_cliente <= 0 || $id_computadora <= 0 || $minutos <= 0) {
        $mensaje = "Error: datos incompletos o inválidos.";
    } else {
        // Validación antirreingreso: verificar si el cliente ya tiene una sesión hoy
        $sql_check = "SELECT COUNT(*) as cnt FROM sesiones WHERE id_cliente = ? AND DATE(hora_inicio) = CURDATE()";
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
                // Insertar sesión con hora_inicio = NOW() y hora_fin_estimada calculada por DATE_ADD
                $sql_insert = "INSERT INTO sesiones (id_cliente, id_computadora, hora_inicio, hora_fin_estimada, estado_transaccion) VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? MINUTE), 'en_curso')";
                $stmt2 = mysqli_prepare($conexion, $sql_insert);
                if ($stmt2) {
                    mysqli_stmt_bind_param($stmt2, 'iii', $id_cliente, $id_computadora, $minutos);
                    $ok = mysqli_stmt_execute($stmt2);
                    if ($ok) {
                        // Opcional: marcar la computadora como 'ocupada' para que no aparezca en la lista
                        $sql_up = "UPDATE computadoras SET estado_operativo = 'ocupada' WHERE id = ?";
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

// Consulta de computadoras disponibles para el selector (estado_operativo = 'disponible')
// Usamos SELECT * para evitar errores si la tabla no tiene una columna 'nombre'.
// Construimos la etiqueta de forma dinámica según las columnas disponibles.
$sql_comp = "SELECT * FROM computadoras WHERE estado_operativo = 'disponible' ORDER BY id ASC";
try {
    $res_comp = mysqli_query($conexion, $sql_comp);
} catch (mysqli_sql_exception $ex) {
    // Manejo seguro en caso de que mysqli esté configurado para lanzar excepciones
    $res_comp = false;
    $mensaje = "Error al obtener las computadoras: " . $ex->getMessage();
}


?>

<div class="container main-content py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="fw-bold mb-0 text-white">Asignar dispositivo</h3>
            <p class="text-white-50">Asignación de equipos - Interfaz glass coherente</p>
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
                            if (!empty($row['nombre'])) $label_parts[] = $row['nombre'];
                            elseif (!empty($row['alias'])) $label_parts[] = $row['alias'];
                            elseif (!empty($row['hostname'])) $label_parts[] = $row['hostname'];
                            if (!empty($row['ip_address'])) $label_parts[] = '(' . $row['ip_address'] . ')';

                            $label = empty($label_parts) ? ('Equipo #' . $id_comp) : implode(' ', $label_parts);
                            echo '<option value="' . $id_comp . '">' . htmlspecialchars($label) . '</option>';
                        }
                    } else {
                        echo '<option value="">No hay computadoras disponibles</option>';
                    }
                    ?>
                </select>
                <div class="form-text">Si no aparecen equipos, verifica el campo <strong>estado_operativo</strong> en la tabla de computadoras.</div>
            </div>

            <div class="col-md-4">
                <label class="form-label">Duración (minutos)</label>
                <select name="minutos_duracion" class="form-select" required>
                    <option value="">--  Seleccione minutos --</option>
                    <?php for ($m = 15; $m <= 240; $m += 15): ?>
                        <option value="<?php echo $m; ?>"><?php echo $m; ?> minutos</option>
                    <?php endfor; ?>
                </select>
                <div class="form-text">Los bloques incrementan de 15 en 15 hasta 240 minutos (4 horas).</div>
            </div>

            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-success w-100">Asignar</button>
            </div>
        </form>
    </div>
</div>

<?php include_once "includes/footer.php"; ?>

<!-- FIN archivo asignar.php -->
