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
        $this->Image(__DIR__ . '/../assets/img/logo_unerg.png', 12, 12, 22);
        $this->Image(__DIR__ . '/../assets/img/logo_ais.png', 176, 12, 22);

        // Encabezado Institucional Académico
        $this->SetY(10);
        $this->SetFont('helvetica', 'B', 8);
        $this->Cell(0, 4, 'REPÚBLICA BOLIVARIANA DE VENEZUELA', 0, 1, 'C');
        $this->Cell(0, 4, 'UNIVERSIDAD NACIONAL EXPERIMENTAL DE LOS LLANOS CENTRALES "RÓMULO GALLEGOS"', 0, 1, 'C');
        $this->Cell(0, 4, 'ÁREA DE INGENIERÍA EN SISTEMAS', 0, 1, 'C');
        $this->Cell(0, 4, 'PROGRAMA DE INGENIERÍA EN INFORMÁTICA - NÚCLEO CALABOZO', 0, 1, 'C');

        $this->Ln(10);
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
        $stmt = $conexion->prepare("SELECT c.*, m.nombremarca, mdl.nombre_modelo 
                                    FROM computadoras c 
                                    JOIN marca m ON c.marca = m.id_marca 
                                    JOIN modelos mdl ON c.modelo_id = mdl.id 
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
            
            $p_html = '<table border="1" cellpadding="5" style="width:100%;"><thead><tr bgcolor="#e6e6e6" style="font-weight:bold; text-align:center;"><th width="30%">Componente</th><th width="40%">Marca/Modelo</th><th width="30%">Serial</th></tr></thead><tbody>';
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

    $query = "SELECT m.nombremarca, mdl.nombre_modelo, COUNT(c.id) as total_equipos, c.estado_operativo 
              FROM computadoras c 
              JOIN marca m ON c.marca = m.id_marca 
              JOIN modelos mdl ON c.modelo_id = mdl.id
              GROUP BY m.nombremarca, mdl.nombre_modelo, c.estado_operativo";
    $res = $conexion->query($query);

    $tbl = '<table border="1" cellpadding="5" style="width:100%;">
                <thead><tr bgcolor="#d9d9d9" style="font-weight:bold; text-align:center;"><th width="25%">Marca</th><th width="40%">Modelo</th><th width="15%">Cantidad</th><th width="20%">Estado</th></tr></thead>';
    while($row = $res->fetch_assoc()){
        $tbl .= '<tr><td>'.$row['nombremarca'].'</td><td>'.$row['nombre_modelo'].'</td><td align="center">'.$row['total_equipos'].'</td><td>'.$row['estado_operativo'].'</td></tr>';
    }
    $tbl .= '</table>';
    $pdf->writeHTML($tbl, true, false, true, false, '');

} elseif ($tipo_reporte === 'mantenimiento') {
    // --- 1. BLOQUE DE TÍTULO Y AUDITORÍA (REESTRUCTURADO) ---
    $pdf->SetY(45); 
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'HISTORIAL GENERAL DE MANTENIMIENTOS', 0, 1, 'C');
    
    $pdf->Ln(1); 
    
    $pdf->SetFont('helvetica', 'I', 8);
    $audit_info = 'Sistema: Cyber-Center | Operador: ' . ($_SESSION['nombre'] ?? 'Administrador') . ' | Emisión: ' . date('d/m/Y h:i A');
    $pdf->Cell(0, 6, $audit_info, 0, 1, 'R');

    // --- 2. LÍNEA DIVISORIA UNIFICADA ---
    // Coordenadas: X1=40 (Margen Izq), X2=180 (Ancho A4 - Margen Der 30)
    $pdf->Line(40, $pdf->GetY(), 180, $pdf->GetY());
    $pdf->Ln(8);

    $sql = "SELECT m.* FROM mantenimientos m ORDER BY m.fecha_mantenimiento DESC";
    $res = $conexion->query($sql);

    // --- 3. MAQUETACIÓN DE TABLA (PADDING Y SIMETRÍA VERTICAL) ---
    // Configuración de anchos para un total de 140mm (ancho disponible entre márgenes)
    $w_fecha   = 25;
    $w_entidad = 25;
    $w_tipo    = 25;
    $w_falla   = 40;
    $w_tec     = 25;
    $h_min     = 10; // Altura mínima de celda (Padding vertical)

    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor(230, 230, 230);
    
    // Encabezados centrados horizontalmente
    $pdf->Cell($w_fecha, $h_min, 'Fecha', 1, 0, 'C', 1);
    $pdf->Cell($w_entidad, $h_min, 'Entidad', 1, 0, 'C', 1);
    $pdf->Cell($w_tipo, $h_min, 'Tipo', 1, 0, 'C', 1);
    $pdf->Cell($w_falla, $h_min, 'Descripción Falla', 1, 0, 'C', 1);
    $pdf->Cell($w_tec, $h_min, 'Técnico', 1, 1, 'C', 1);

    $pdf->SetFont('helvetica', '', 8);
    $pdf->setCellHeightRatio(1.3); // Ajuste de interlineado académico

    while ($row = $res->fetch_assoc()) {
        // Cálculo dinámico de altura para MultiCell
        $height = $pdf->getStringHeight($w_falla, $row['descripcion_falla']);
        $h_fila = max($height, $h_min);

        // Salto de página preventivo
        if ($pdf->GetY() + $h_fila > ($pdf->getPageHeight() - 30)) {
            $pdf->AddPage();
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell($w_fecha, $h_min, 'Fecha', 1, 0, 'C', 1);
            $pdf->Cell($w_entidad, $h_min, 'Entidad', 1, 0, 'C', 1);
            $pdf->Cell($w_tipo, $h_min, 'Tipo', 1, 0, 'C', 1);
            $pdf->Cell($w_falla, $h_min, 'Descripción Falla', 1, 0, 'C', 1);
            $pdf->Cell($w_tec, $h_min, 'Técnico', 1, 1, 'C', 1);
            $pdf->SetFont('helvetica', '', 8);
        }

        // Dibujo de fila sincronizada
        $current_x = $pdf->GetX();
        $current_y = $pdf->GetY();

        // Celdas de datos cortos (Centradas)
        $pdf->MultiCell($w_fecha, $h_fila, date('d/m/Y', strtotime($row['fecha_mantenimiento'])), 1, 'C', 0, 0);
        $pdf->MultiCell($w_entidad, $h_fila, $row['tipo_entidad'] . "\nID: " . $row['entidad_id'], 1, 'C', 0, 0);
        $pdf->MultiCell($w_tipo, $h_fila, $row['tipo_mantenimiento'], 1, 'C', 0, 0);
        
        // Celda descriptiva (Justificada/Izquierda)
        $pdf->MultiCell($w_falla, $h_fila, $row['descripcion_falla'], 1, 'J', 0, 0);
        
        $pdf->MultiCell($w_tec, $h_fila, $row['tecnico_responsable'], 1, 'C', 0, 1);
    }

} else {
    // Reporte General Básico de Control Rápido
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'INVENTARIO GENERAL DE CONTROL RÁPIDO', 0, 1, 'C');
    $pdf->Ln(5);
    
    $pdf->SetFont('helvetica', 'B', 9);

    $tbl_gen = '<table border="1" cellpadding="5" style="width:100%;">
                <thead><tr bgcolor="#d9d9d9" style="font-weight:bold; text-align:center;">
                    <th width="10%">ID</th>
                    <th width="30%">MARCA</th>
                    <th width="35%">MODELO</th>
                    <th width="25%">ESTADO</th>
                </tr></thead><tbody>';
    
    $sql = "SELECT c.id, m.nombremarca, mdl.nombre_modelo, c.estado_operativo 
            FROM computadoras c 
            JOIN marca m ON c.marca = m.id_marca
            JOIN modelos mdl ON c.modelo_id = mdl.id";
    $res = $conexion->query($sql);
    while($row = $res->fetch_assoc()) {
        $tbl_gen .= '<tr>
                        <td align="center">'.$row['id'].'</td>
                        <td>'.$row['nombremarca'].'</td>
                        <td>'.$row['nombre_modelo'].'</td>
                        <td align="center">'.$row['estado_operativo'].'</td>
                      </tr>';
    }
    $tbl_gen .= '</tbody></table>';
    $pdf->writeHTML($tbl_gen, true, false, true, false, '');
}

// Limpiar buffer de salida para evitar corrupción del PDF
if (ob_get_length()) ob_end_clean();
$pdf->Output('Reporte_CyberCenter_' . date('Ymd_His') . '.pdf', 'I');