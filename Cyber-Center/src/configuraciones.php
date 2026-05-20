<?php
session_start();
require_once __DIR__ . '/../conexion.php';

function escapeIdentifier($name) {
    return '`' . str_replace('`', '``', $name) . '`';
}

function safeFilename($name) {
    $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
    if (stripos($name, '.sql') === false) {
        $name .= '.sql';
    }
    return $name;
}

function execute_sql_script($conexion, $sql) {
    $delimiter = ';';
    $statement = '';
    $lines = preg_split('/\r\n|\r|\n/', $sql);
    $lineNumber = 0;

    foreach ($lines as $line) {
        $lineNumber++;
        $trimmed = trim($line);

        if ($trimmed === '' || preg_match('/^(--|#)/', $trimmed)) {
            continue;
        }

        if (preg_match('/^DELIMITER\s+(.+)$/i', $trimmed, $matches)) {
            if (trim($statement) !== '') {
                $query = trim($statement);
                if ($query !== '' && $query !== $delimiter) {
                    if (!$conexion->query($query)) {
                        return "Line $lineNumber: " . mysqli_error($conexion) . ' | ' . $query;
                    }
                }
                $statement = '';
            }
            $delimiter = trim($matches[1]);
            continue;
        }

        $statement .= $line . "\n";
        $current = trim($statement);
        if ($delimiter !== '' && strlen($current) >= strlen($delimiter) && substr($current, -strlen($delimiter)) === $delimiter) {
            $query = trim(substr($current, 0, -strlen($delimiter)));
            if ($query !== '') {
                if (!$conexion->query($query)) {
                    return "Line $lineNumber: " . mysqli_error($conexion) . ' | ' . $query;
                }
            }
            $statement = '';
        }
    }

    if (trim($statement) !== '') {
        $query = trim($statement);
        if ($query !== '' && $query !== $delimiter) {
            if (!$conexion->query($query)) {
                return "EOF: " . mysqli_error($conexion) . ' | ' . $query;
            }
        }
    }

    return true;
}

function appendRoutineBackup(&$output, $conexion, $db, $type) {
    $type = strtoupper($type);
    $result = mysqli_query($conexion, "SELECT ROUTINE_NAME FROM information_schema.routines WHERE routine_schema = '" . mysqli_real_escape_string($conexion, $db) . "' AND routine_type = '" . $type . "' ORDER BY ROUTINE_NAME ASC");
    while ($row = mysqli_fetch_assoc($result)) {
        $name = $row['ROUTINE_NAME'];
        $createResult = mysqli_query($conexion, "SHOW CREATE " . $type . " " . escapeIdentifier($name));
        $createRow = mysqli_fetch_assoc($createResult);
        $key = $type === 'FUNCTION' ? 'Create Function' : 'Create Procedure';
        if (!isset($createRow[$key])) {
            continue;
        }
        $output[] = "\n-- --------------------------------------------------------";
        $output[] = "-- Estructura de " . strtolower($type) . " " . escapeIdentifier($name);
        $output[] = "-- --------------------------------------------------------";
        $output[] = ($type === 'FUNCTION' ? 'DROP FUNCTION IF EXISTS ' : 'DROP PROCEDURE IF EXISTS ') . escapeIdentifier($name) . ";";
        $output[] = "DELIMITER $$";
        $output[] = $createRow[$key] . "$$";
        $output[] = "DELIMITER ;";
        $output[] = "";
    }
}

function appendViewBackup(&$output, $conexion) {
    $viewsResult = mysqli_query($conexion, "SHOW FULL TABLES WHERE Table_type = 'VIEW'");
    while ($viewRow = mysqli_fetch_row($viewsResult)) {
        $view = $viewRow[0];
        $createResult = mysqli_query($conexion, "SHOW CREATE VIEW " . escapeIdentifier($view));
        $createRow = mysqli_fetch_assoc($createResult);
        $output[] = "\n-- --------------------------------------------------------";
        $output[] = "-- Estructura de la vista " . escapeIdentifier($view);
        $output[] = "-- --------------------------------------------------------";
        $output[] = "DROP VIEW IF EXISTS " . escapeIdentifier($view) . ";";
        $output[] = $createRow['Create View'] . ";";
        $output[] = "";
    }
}

function appendTriggerBackup(&$output, $conexion, $db) {
    $triggersResult = mysqli_query($conexion, "SHOW TRIGGERS FROM " . escapeIdentifier($db));
    while ($triggerRow = mysqli_fetch_assoc($triggersResult)) {
        $trigger = $triggerRow['Trigger'];
        $createResult = mysqli_query($conexion, "SHOW CREATE TRIGGER " . escapeIdentifier($trigger));
        $createRow = mysqli_fetch_assoc($createResult);
        if (!$createRow) {
            continue;
        }

        $triggerSql = '';
        if (isset($createRow['SQL Original Statement'])) {
            $triggerSql = trim($createRow['SQL Original Statement']);
        }
        if ($triggerSql === '') {
            continue;
        }

        $output[] = "\n-- --------------------------------------------------------";
        $output[] = "-- Estructura del trigger " . escapeIdentifier($trigger);
        $output[] = "-- --------------------------------------------------------";
        $output[] = "DROP TRIGGER IF EXISTS " . escapeIdentifier($trigger) . ";";
        $output[] = "DELIMITER $$";
        $output[] = $triggerSql . "$$";
        $output[] = "DELIMITER ;";
        $output[] = "";
    }
}

function appendEventBackup(&$output, $conexion, $db) {
    $eventsResult = mysqli_query($conexion, "SELECT EVENT_NAME FROM information_schema.EVENTS WHERE EVENT_SCHEMA = '" . mysqli_real_escape_string($conexion, $db) . "' ORDER BY EVENT_NAME ASC");
    while ($eventRow = mysqli_fetch_assoc($eventsResult)) {
        $event = $eventRow['EVENT_NAME'];
        $createResult = mysqli_query($conexion, "SHOW CREATE EVENT " . escapeIdentifier($event));
        $createRow = mysqli_fetch_assoc($createResult);
        if (!$createRow || !isset($createRow['Create Event'])) {
            continue;
        }
        $eventSql = trim($createRow['Create Event']);
        if ($eventSql === '') {
            continue;
        }

        $output[] = "\n-- --------------------------------------------------------";
        $output[] = "-- Estructura del evento " . escapeIdentifier($event);
        $output[] = "-- --------------------------------------------------------";
        $output[] = "DROP EVENT IF EXISTS " . escapeIdentifier($event) . ";";
        $output[] = "DELIMITER $$";
        $output[] = $eventSql . "$$";
        $output[] = "DELIMITER ;";
        $output[] = "";
    }
}

function buildBackupSql($conexion) {
    $database = mysqli_query($conexion, 'SELECT DATABASE()')->fetch_row()[0];
    if (!$database) {
        return false;
    }
    $output = [];
    $output[] = "-- Respaldo completo de la base de datos: $database";
    $output[] = "-- Fecha: " . date('Y-m-d H:i:s');
    $output[] = "SET NAMES utf8mb4;";
    $output[] = "SET CHARACTER SET utf8mb4;";
    $output[] = "SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, @OLD_SQL_MODE=@@SQL_MODE;";
    $output[] = "SET UNIQUE_CHECKS = 0;";
    $output[] = "SET FOREIGN_KEY_CHECKS = 0;";
    $output[] = "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';";
    $output[] = "";
    $output[] = "DROP DATABASE IF EXISTS " . escapeIdentifier($database) . ";";
    $output[] = "CREATE DATABASE IF NOT EXISTS " . escapeIdentifier($database) . " CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;";
    $output[] = "USE " . escapeIdentifier($database) . ";";
    $output[] = "";

    $tablesResult = mysqli_query($conexion, "SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
    while ($tableRow = mysqli_fetch_row($tablesResult)) {
        $table = $tableRow[0];
        $output[] = "-- --------------------------------------------------------";
        $output[] = "-- Estructura de la tabla " . escapeIdentifier($table);
        $output[] = "-- --------------------------------------------------------";

        $createResult = mysqli_query($conexion, "SHOW CREATE TABLE " . escapeIdentifier($table));
        $createRow = mysqli_fetch_assoc($createResult);
        $output[] = "DROP TABLE IF EXISTS " . escapeIdentifier($table) . ";";
        $output[] = $createRow['Create Table'] . ";";
        $output[] = "";

        $dataResult = mysqli_query($conexion, "SELECT * FROM " . escapeIdentifier($table));
        if ($dataResult && mysqli_num_rows($dataResult) > 0) {
            $output[] = "-- Datos para la tabla " . escapeIdentifier($table);
            while ($row = mysqli_fetch_assoc($dataResult)) {
                $columns = array_map(function ($col) {
                    return escapeIdentifier($col);
                }, array_keys($row));
                $values = array_map(function ($value) use ($conexion) {
                    if (is_null($value)) {
                        return 'NULL';
                    }
                    return "'" . mysqli_real_escape_string($conexion, $value) . "'";
                }, array_values($row));
                $output[] = "INSERT INTO " . escapeIdentifier($table) . " (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ");";
            }
            $output[] = "";
        }
    }

    appendRoutineBackup($output, $conexion, $database, 'FUNCTION');
    appendRoutineBackup($output, $conexion, $database, 'PROCEDURE');
    appendViewBackup($output, $conexion);
    appendTriggerBackup($output, $conexion, $database);
    appendEventBackup($output, $conexion, $database);

    $output[] = "SET FOREIGN_KEY_CHECKS = @OLD_FOREIGN_KEY_CHECKS;";
    $output[] = "SET UNIQUE_CHECKS = @OLD_UNIQUE_CHECKS;";
    $output[] = "SET SQL_MODE = @OLD_SQL_MODE;";

    return implode("\n", $output);
}

$action = $_REQUEST['action'] ?? '';

if ($action !== 'backup') {
    header('Content-Type: application/json; charset=utf-8');
}

if ($action === 'backup' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $filename = isset($_GET['filename']) ? safeFilename($_GET['filename']) : 'respaldo_cybercenter_' . date('Ymd_His') . '.sql';
    $output = buildBackupSql($conexion);
    if ($output === false) {
        http_response_code(500);
        echo 'No se pudo generar el respaldo completo.';
        exit;
    }

    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
    echo $output;
    exit;
}

if ($action === 'restore' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
        $message = 'No se recibió un archivo de respaldo válido.';
        if (isset($_FILES['backup_file']['error'])) {
            $errorCode = $_FILES['backup_file']['error'];
            if ($errorCode === UPLOAD_ERR_INI_SIZE || $errorCode === UPLOAD_ERR_FORM_SIZE) {
                $message = 'El archivo es demasiado grande para ser cargado. Usa un respaldo más pequeño o ajusta la configuración de PHP.';
            } elseif ($errorCode === UPLOAD_ERR_NO_FILE) {
                $message = 'No se seleccionó ningún archivo de respaldo.';
            }
        }
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }

    $file = $_FILES['backup_file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'sql') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'El archivo debe tener extensión .sql.']);
        exit;
    }

    $content = file_get_contents($file['tmp_name']);
    if ($content === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'No se pudo leer el archivo de respaldo.']);
        exit;
    }

    $result = execute_sql_script($conexion, $content);
    if ($result !== true) {
        $logFile = sys_get_temp_dir() . '/cybercenter_restore_error.log';
        file_put_contents($logFile, date('Y-m-d H:i:s') . " ERROR: " . $result . "\n", FILE_APPEND);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al restaurar la base de datos: ' . $result . ' (revisa ' . $logFile . ')']);
        exit;
    }

    $usuario = $_SESSION['nombre'] ?? 'Sistema';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $fecha_hora = date('Y-m-d H:i:s');
    $accion = 'Restauró la base de datos desde respaldo';
    $stmt = $conexion->prepare("INSERT INTO historial (usuario, ip, fyh, sector, acciones) VALUES (?, ?, ?, ?, ?)");
    if ($stmt) {
        $sector = 'Configuraciones';
        $stmt->bind_param('sssss', $usuario, $ip, $fecha_hora, $sector, $accion);
        $stmt->execute();
        $stmt->close();
    }

    echo json_encode(['success' => true, 'message' => 'Base de datos restaurada correctamente.']);
    exit;
}

if ($action === 'historial') {
    $rows = [];
    $result = mysqli_query($conexion, "SELECT usuario, ip, fyh, sector, acciones FROM historial ORDER BY fyh DESC LIMIT 50");
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    echo json_encode(['success' => true, 'data' => $rows]);
    exit;
}

if ($action === 'transacciones') {
    $rows = [];
    $result = mysqli_query($conexion, "SELECT usuario, fyh, sector, acciones FROM historial ORDER BY fyh DESC LIMIT 50");
    while ($row = mysqli_fetch_assoc($result)) {
        $tipo = 'Operación';
        $lower = mb_strtolower($row['acciones']);
        if (strpos($lower, 'respaldo') !== false) {
            $tipo = 'Respaldo';
        } elseif (strpos($lower, 'restaur') !== false) {
            $tipo = 'Restauración';
        } elseif (strpos($lower, 'cambió contraseña') !== false || strpos($lower, 'contraseña') !== false) {
            $tipo = 'Seguridad';
        }
        $rows[] = [
            'tipo' => $tipo,
            'descripcion' => $row['acciones'],
            'usuario' => $row['usuario'],
            'fecha' => $row['fyh'],
        ];
    }
    echo json_encode(['success' => true, 'data' => $rows]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Acción no válida.']);
exit;
