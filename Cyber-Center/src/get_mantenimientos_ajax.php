<?php
/**
 * Script backend para obtener el historial de mantenimientos de un equipo o periférico.
 * Recibe el ID y el tipo de entidad (equipo o periferico) y devuelve los mantenimientos en formato JSON.
 */

require_once __DIR__ . "/../conexion.php";
global $conexion;

header('Content-Type: application/json');

// Sanitizar y validar las entradas
$entity_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$entity_type = filter_input(INPUT_GET, 'tipo', FILTER_SANITIZE_STRING);

// Verificar que los parámetros sean válidos
if (!$entity_id || $entity_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de entidad inválido.']);
    exit;
}

if (!in_array($entity_type, ['equipo', 'periferico'])) {
    echo json_encode(['success' => false, 'message' => 'Tipo de entidad inválido.']);
    exit;
}

$sql = "SELECT DATE_FORMAT(fecha_mantenimiento, '%d/%m/%Y %H:%i') as fecha_mantenimiento, tipo_mantenimiento, descripcion_falla as razon, accion_realizada as diagnostico_correccion FROM mantenimientos WHERE entidad_id = ? AND tipo_entidad = ? ORDER BY fecha_mantenimiento DESC";

$mantenimientos = [];
try {
    $stmt = $conexion->prepare($sql);
    if ($stmt) {
        $type_param = ($entity_type === 'equipo') ? 'Equipo' : 'Periferico';
        $stmt->bind_param('is', $entity_id, $type_param);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $mantenimientos[] = $row;
        }
        $stmt->close();
        echo json_encode(['success' => true, 'mantenimientos' => $mantenimientos]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al preparar la consulta: ' . $conexion->error]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()]);
}
?>