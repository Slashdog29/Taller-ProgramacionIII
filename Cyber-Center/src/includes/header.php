            </div>
        </div>
    </div>

    <!-- Modal Cambiar Contraseña -->
    <div class="modal fade" id="cambiarPassModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content glass-modal">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-key"></i> Cambiar contraseña</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formCambiarPass">
                        <div class="mb-3">
                            <label class="form-label">Contraseña actual</label>
                            <input type="password" class="form-control" id="actualPass" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nueva contraseña</label>
                            <input type="password" class="form-control" id="nuevaPass" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Actualizar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Respaldar BD -->
    <div class="modal fade" id="respaldarModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content glass-modal">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-database"></i> Respaldar BD</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Genera una copia de seguridad de la base de datos de CyberCenter. El respaldo incluirá la información de usuarios, equipos, periféricos, historial y demás registros.</p>
                    <div class="mb-3">
                        <label class="form-label">Nombre del archivo de respaldo</label>
                        <input type="text" class="form-control" id="backupFileName" value="respaldo_cybercenter_<?php echo date('Ymd_His'); ?>.sql">
                    </div>
                    <button type="button" id="btnRespaldar" class="btn btn-success w-100">Generar respaldo</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Restaurar BD -->
    <div class="modal fade" id="restaurarModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content glass-modal">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-upload"></i> Restaurar BD</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Selecciona un archivo SQL válido para restaurar la base de datos. Ten en cuenta que la restauración reemplazará los datos actuales.</p>
                    <div class="mb-3">
                        <label class="form-label">Archivo de respaldo</label>
                        <input type="file" class="form-control" id="restoreFile" accept=".sql">
                    </div>
                    <button type="button" id="btnRestaurar" class="btn btn-warning w-100">Restaurar ahora</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Historial -->
    <div class="modal fade" id="historialModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content glass-modal">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-history"></i> Historial</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Consulta las últimas acciones registradas en el sistema.</p>
                    <div class="table-responsive">
                        <table class="table table-dark table-striped">
                            <thead>
                                <tr>
                                    <th>Fecha / Hora</th>
                                    <th>Usuario</th>
                                    <th>Sector</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody id="historialTableBody">
                                <tr>
                                    <td colspan="4" class="text-center text-white-50">Cargando historial...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Transacciones -->
    <div class="modal fade" id="transaccionesModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content glass-modal">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-exchange-alt"></i> Transacciones</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Revisa las transacciones recientes del sistema, incluyendo cambios de datos, importaciones y exportaciones.</p>
                    <div class="table-responsive">
                        <table class="table table-dark table-striped">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Tipo</th>
                                    <th>Descripción</th>
                                    <th>Usuario</th>
                                    <th>Fecha</th>
                                </tr>
                            </thead>
                            <tbody id="transaccionesTableBody">
                                <tr>
                                    <td colspan="5" class="text-center text-white-50">Cargando transacciones...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../assets/js/dashboard.js"></script>
</body>
</html>
