<?php
/**
 * Cyber-Center - Generador de Reportes Académicos
 * Implementación bajo estándares UNERG Núcleo Calabozo.
 * Librería Utilizada: TCPDF
 */
require_once __DIR__ . '/../TCPDF/tcpdf.php';
require_once __DIR__ . '/../conexion.php';

if (session_status() === PHP_SESSION_NONE) session_start();

class PDF_UNERG extends TCPDF {
    public function Header() {
        // Configuración de márgenes rígidos (40mm Izq, 30mm Sup, 30mm Der)
        $this->SetMargins(40, 30, 30);

        // Logos institucionales (UNERG y AIS)
        $this->Image(__DIR__ . '/../assets/img/logo_unerg.png', 10, 10, 22);
        $this->Image(__DIR__ . '/../assets/img/logo_ais.png', 175, 10, 22);

        // Encabezado Institucional Académico
        $this->SetY(10);
        $this->SetFont('helvetica', 'B', 8);
        $this->Cell(0, 4, 'REPÚBLICA BOLIVARIANA DE VENEZUELA', 0, 1, 'C');
        $this->Cell(0, 4, 'UNIVERSIDAD NACIONAL EXPERIMENTAL "RÓMULO GALLEGOS"', 0, 1, 'C');
        $this->Cell(0, 4, 'ÁREA DE INGENIERÍA EN SISTEMAS', 0, 1, 'C');
        $this->Cell(0, 4, 'PROGRAMA DE INGENIERÍA EN INFORMÁTICA - NÚCLEO CALABOZO', 0, 1, 'C');

        $this->Ln(10);

        // Bloque de Auditoría Dinámica
        $this->SetFont('helvetica', 'I', 7);
        $this->Cell(0, 4, 'Sistema: Cyber-Center | Operador: ' . ($_SESSION['nombre'] ?? 'Sistema Central'), 0, 1, 'R');
        $this->Cell(0, 4, 'Fecha de Emisión: ' . date('d/m/Y H:i:s'), 0, 1, 'R');
        $this->Line(40, $this->GetY(), 180, $this->GetY()); 
        $this->Ln(5);
    }

    public function Footer() {
        $this->SetY(-30);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Página ' . $this->getAliasNumPage() . ' de ' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

// Instanciación de objeto PDF con orientación Portrait y unidad de medida mm
$tipo_reporte = $_GET['tipo'] ?? 'general';
$entity_type = $_GET['entity_type'] ?? ''; // Nuevo parámetro para tipo de entidad
$id = intval($_GET['id'] ?? 0); // ID de la entidad
$pdf = new PDF_UNERG(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator('Cyber-Center');
$pdf->SetAuthor('Área de Ingeniería en Sistemas');
$pdf->SetTitle('Reporte de Auditoría - Cyber-Center');

$pdf->SetHeaderData('', 0, '', '');
$pdf->setHeaderFont(Array('helvetica', '', 8));
$pdf->setFooterFont(Array('helvetica', '', 8));
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
$pdf->SetAutoPageBreak(TRUE, 30);

$pdf->AddPage();

if ($tipo_reporte === 'individual' && $id > 0) {
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'FICHA TÉCNICA INDIVIDUAL DE ' . strtoupper($entity_type), 0, 1, 'C');
    $pdf->Ln(5);

    if ($entity_type === 'equipo') {
        $stmt = $conexion->prepare("SELECT c.*, m.nombremarca, mod.nombre_modelo 
                                    FROM computadoras c 
                                    JOIN marca m ON c.marca = m.id_marca 
                                    JOIN modelos mod ON c.modelo_id = mod.id 
                                    WHERE c.id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($equipo = $res->fetch_assoc()) {
            $html = '<table border="1" cellpadding="4">
                        <tr bgcolor="#f2f2f2"><td width="30%"><b>Puesto:</b></td><td width="70%">PC-' . str_pad($equipo['numero_puesto'], 2, '0', STR_PAD_LEFT) . '</td></tr>
                        <tr><td><b>Marca / Modelo:</b></td><td>' . $equipo['nombremarca'] . ' ' . $equipo['nombre_modelo'] . '</td></tr>
                        <tr><td><b>Serial Chasis:</b></td><td>' . $equipo['numero_serial_chasis'] . '</td></tr>
                        <tr><td><b>Estado Operativo:</b></td><td>' . $equipo['estado_operativo'] . '</td></tr>
                        <tr><td><b>Dirección IP:</b></td><td>' . $equipo['direccion_ip'] . '</td></tr>
                     </table>';
            $pdf->writeHTML($html, true, false, true, false, '');

            $pdf->Ln(5);
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell(0, 7, 'PERIFÉRICOS ASOCIADOS:', 0, 1);
            
            $stmt_p = $conexion->prepare("SELECT p.*, tp.nombre_componente FROM perifericos p JOIN tipos_periferico tp ON p.tipo_periferico_id = tp.id WHERE p.computadora_id = ?");
            $stmt_p->bind_param("i", $id);
            $stmt_p->execute();
            $res_p = $stmt_p->get_result();
            
            $p_html = '<table border="1" cellpadding="3"><thead><tr bgcolor="#e6e6e6"><th>Componente</th><th>Marca/Modelo</th><th>Serial</th></tr></thead><tbody>';
            while ($p = $res_p->fetch_assoc()) {
                $p_html .= '<tr><td>' . $p['nombre_componente'] . '</td><td>' . $p['marca'] . ' ' . $p['modelo'] . '</td><td>' . $p['numero_serial_fabrica'] . '</td></tr>';
            }
            $p_html .= '</tbody></table>';
            $pdf->writeHTML($p_html, true, false, true, false, '');
        }
    } elseif ($entity_type === 'periferico') {
        $stmt = $conexion->prepare("SELECT p.*, tp.nombre_componente 
                                    FROM perifericos p 
                                    JOIN tipos_periferico tp ON p.tipo_periferico_id = tp.id 
                                    WHERE p.id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($periferico = $res->fetch_assoc()) {
            $html = '<table border="1" cellpadding="4">
                        <tr bgcolor="#f2f2f2"><td width="30%"><b>Componente:</b></td><td width="70%">' . $periferico['nombre_componente'] . '</td></tr>
                        <tr><td><b>Marca / Modelo:</b></td><td>' . $periferico['marca'] . ' ' . $periferico['modelo'] . '</td></tr>
                        <tr><td><b>Serial Fábrica:</b></td><td>' . $periferico['numero_serial_fabrica'] . '</td></tr>
                        <tr><td><b>Bien Nacional:</b></td><td>' . $periferico['codigo_bien_nacional'] . '</td></tr>
                        <tr><td><b>Estado Físico:</b></td><td>' . $periferico['estado_fisico'] . '</td></tr>
                     </table>';
            $pdf->writeHTML($html, true, false, true, false, '');
        }
    }
} elseif ($tipo_reporte === 'consolidado') {
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'REPORTE CONSOLIDADO DE INVENTARIO', 0, 1, 'C');
    $pdf->Ln(5);

    $query = "SELECT m.nombremarca, mod.nombre_modelo, COUNT(c.id) as total_equipos, c.estado_operativo 
              FROM computadoras c 
              JOIN marca m ON c.marca = m.id_marca 
              JOIN modelos mod ON c.modelo_id = mod.id
              GROUP BY m.nombremarca, mod.nombre_modelo, c.estado_operativo";
    $res = $conexion->query($query);

    $tbl = '<table border="1" cellpadding="4">
                <thead><tr bgcolor="#d9d9d9"><th>Marca</th><th>Modelo</th><th>Cantidad</th><th>Estado</th></tr></thead>';
    while($row = $res->fetch_assoc()){
        $tbl .= '<tr><td>'.$row['nombremarca'].'</td><td>'.$row['nombre_modelo'].'</td><td align="center">'.$row['total_equipos'].'</td><td>'.$row['estado_operativo'].'</td></tr>';
    }
    $tbl .= '</table>';
    $pdf->writeHTML($tbl, true, false, true, false, '');

} elseif ($tipo_reporte === 'mantenimiento') {
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'HISTORIAL GENERAL DE MANTENIMIENTOS', 0, 1, 'C');
    $pdf->Ln(5);

    // Filtrado por marca/modelo si se especifica
    $marca_filtro = $_GET['marca'] ?? '';
    $sql = "SELECT m.* FROM mantenimientos m ORDER BY m.fecha_mantenimiento DESC";
    $res = $conexion->query($sql);

    $maint_tbl = '<table border="1" cellpadding="3" style="font-size: 8pt;">
                    <thead><tr bgcolor="#cccccc">
                        <th width="15%">Fecha</th>
                        <th width="10%">Entidad</th>
                        <th width="15%">Tipo</th>
                        <th width="30%">Descripción Falla</th>
                        <th width="15%">Técnico</th>
                    </tr></thead>';
    while($row = $res->fetch_assoc()){
        $maint_tbl .= '<tr>
                        <td>'.date('d/m/Y', strtotime($row['fecha_mantenimiento'])).'</td>
                        <td>'.$row['tipo_entidad'].' ID:'.$row['entidad_id'].'</td>
                        <td>'.$row['tipo_mantenimiento'].'</td>
                        <td>'.$row['descripcion_falla'].'</td>
                        <td>'.$row['tecnico_responsable'].'</td>
                      </tr>';
    }
    $maint_tbl .= '</table>';
    $pdf->writeHTML($maint_tbl, true, false, true, false, '');

} else {
    // Reporte General Básico de Control Rápido
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'INVENTARIO GENERAL DE CONTROL RÁPIDO', 0, 1, 'C');
    $pdf->Ln(5);
    
    $pdf->SetFont('helvetica', 'B', 9);
    // Encabezados de tabla
    $pdf->SetFillColor(230, 230, 230);
    $pdf->Cell(15, 7, 'ID', 1, 0, 'C', 1); 
    $pdf->Cell(40, 7, 'MARCA', 1, 0, 'C', 1); 
    $pdf->Cell(45, 7, 'MODELO', 1, 0, 'C', 1); 
    $pdf->Cell(40, 7, 'ESTADO', 1, 1, 'C', 1); 
    
    $pdf->SetFont('helvetica', '', 9);
    $sql = "SELECT c.id, m.nombremarca, mod.nombre_modelo, c.estado_operativo 
            FROM computadoras c 
            JOIN marca m ON c.marca = m.id_marca
            JOIN modelos mod ON c.modelo_id = mod.id";
    $res = $conexion->query($sql);
    while($row = $res->fetch_assoc()) {
        $pdf->Cell(15, 6, $row['id'], 1, 0, 'C'); 
        $pdf->Cell(40, 6, $row['nombremarca'], 1); 
        $pdf->Cell(45, 6, $row['nombre_modelo'], 1); 
        $pdf->Cell(40, 6, $row['estado_operativo'], 1, 1, 'C');
    }
}

// Limpiar buffer de salida para evitar corrupción del PDF
if (ob_get_length()) ob_end_clean();
$pdf->Output('Reporte_CyberCenter_' . date('Ymd_His') . '.pdf', 'I');