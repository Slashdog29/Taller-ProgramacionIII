<?php
include_once "includes/header.php";
require_once "../conexion.php";
global $conexion;
// Registro de actividad en el historial
$nombre = $_SESSION['nombre'];
$ip = $_SERVER['REMOTE_ADDR'];
$fecha_hora = date('Y-m-d H:i:s');
$sector = "Equipos";
$accion = "Acceso a la sección de gestión de equipos";
$stmt = $conexion->prepare("INSERT INTO historial (usuario, ip, fyh, sector, acciones) VALUES (?, ?, ?, ?, ?)");
if ($stmt) {
    $stmt->bind_param("sssss", $nombre, $ip, $fecha_hora, $sector, $accion);
    $stmt->execute();
    $stmt->close();
}

// Consulta a la vista de inventario definida en el script SQL
$query = "SELECT * FROM vista_inventario_computadoras ORDER BY numero_puesto ASC";
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
    
    /* Máximo contraste para legibilidad en fondo oscuro */
    .table tbody td { color: #ffffff !important; } 
    .text-white-50 { color: rgba(255, 255, 255, 0.85) !important; } 
    .text-muted { color: rgba(255, 255, 255, 0.75) !important; }
    
    /* Brillo para elementos específicos */
    .bg-dark.border-secondary { background-color: rgba(45, 55, 72, 0.9) !important; border-color: rgba(255, 255, 255, 0.3) !important; }
</style>

    <div class="container main-content pb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-0 text-white">Gestión de Equipos</h2>
                <p class="text-white-50">Inventario y estado de estaciones de trabajo</p>
            </div>
            <button class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Registrar Equipo
            </button>
        </div>

        <!-- Tarjeta Principal (Glass Card) -->
        <div class="glass-card p-4">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th># PUESTO</th>
                            <th>MARCA / MODELO</th>
                            <th>BIEN NACIONAL</th>
                            <th>DIRECCIÓN IP</th>
                            <th>ESTADO</th>
                            <th>PERIFÉRICOS</th>
                            <th class="text-end">ACCIONES</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($resultado)): 
                            $badge_class = match($row['estado_operativo']) {
                                'disponible' => 'bg-success',
                                'ocupado' => 'bg-primary',
                                'mantenimiento' => 'bg-warning text-dark',
                                'desincorporado' => 'bg-danger',
                                default => 'bg-secondary'
                            };
                        ?>
                        <tr>
                            <td class="fw-bold" style="color: var(--primary-light);">PC-<?php echo str_pad($row['numero_puesto'], 2, '0', STR_PAD_LEFT); ?></td>
                            <td>
                                <div class="fw-semibold"><?php echo $row['pc_marca']; ?></div>
                                <small class="text-white-50"><?php echo $row['pc_modelo']; ?></small>
                            </td>
                            <td><code style="color: #00f2ff; font-size: 0.9rem; font-weight: 700;"><?php echo $row['bien_nacional_pc']; ?></code></td>
                            <td class="fw-medium"><?php echo $row['direccion_ip'] ?? '<span class="text-white-50"><i>No asignada</i></span>'; ?></td>
                            <td>
                                <span class="badge status-badge <?php echo $badge_class; ?>">
                                    <?php echo $row['estado_operativo']; ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-dark border border-secondary" title="<?php echo $row['detalle_perifericos']; ?>" style="cursor: help;">
                                    <i class="fas fa-plug me-1" style="color: var(--primary-light);"></i> <?php echo $row['perifericos_asignados']; ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-light rounded-pill me-2"><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-sm btn-outline-info rounded-pill"><i class="fas fa-eye"></i></button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php include_once "includes/footer.php"; ?>