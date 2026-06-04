<style>
    .glass-footer {
        background: rgba(15, 23, 42, 0.8) !important;
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        z-index: 1030;
    }
    /* Añade espacio al final de la página para compensar el footer fijo */
    body {
        padding-bottom: 70px;
    }
    .avatar-colaborador {
        width: 35px;
        height: 35px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid var(--primary-light);
    }
</style>

<footer class="py-3 fixed-bottom glass-footer">
    <div class="container-fluid px-4">
        <div class="d-flex align-items-center justify-content-between small text-white-50">
            <div>
                Copyright &copy; 2026 | <a href="#" class="text-white-50 text-decoration-none fw-bold" data-bs-toggle="modal" data-bs-target="#modalColaboradores">Equipo de Desarrollo de la Web</a>
            </div>
            <div>
                <a href="#" class="text-white-50 text-decoration-none">Política de Privacidad</a>
                &middot;
                <a href="#" class="text-white-50 text-decoration-none">Términos &amp; Condiciones</a>
            </div>
        </div>
    </div>
</footer>

<!-- Modal Colaboradores -->
<div class="modal fade" id="modalColaboradores" tabindex="-1" aria-labelledby="modalColaboradoresLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-modal text-white">
            <div class="modal-header">
                <h5 class="modal-title" id="modalColaboradoresLabel"><i class="fas fa-code me-2"></i>Colaboradores del Proyecto</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <div class="py-3">
                    <ul class="list-unstyled mb-0 fs-5 text-start d-inline-block">
                        <li class="mb-3">
                            <a href="https://github.com/Slashdog29" target="_blank" class="text-white text-decoration-none"><img src="https://github.com/Slashdog29.png" class="avatar-colaborador me-2"> Slashdog29</a>
                        </li>
                        <li class="mb-3">
                            <a href="https://github.com/Don-Gato700" target="_blank" class="text-white text-decoration-none"><img src="https://github.com/Don-Gato700.png" class="avatar-colaborador me-2"> Don-Gato700</a>
                        </li>
                        <li class="mb-3">
                            <a href="https://github.com/tupapa456" target="_blank" class="text-white text-decoration-none"><img src="https://github.com/tupapa456.png" class="avatar-colaborador me-2"> Tupapa</a>
                        </li>
                        <li class="mb-3">
                            <a href="https://github.com/thejoker-afk" target="_blank" class="text-white text-decoration-none"><img src="https://github.com/thejoker-afk.png" class="avatar-colaborador me-2"> TheJoker-afk</a>
                        </li>
                        <li class="mb-3">
                            <a href="https://github.com/ZedZero12" target="_blank" class="text-white text-decoration-none"><img src="https://github.com/ZedZero12.png" class="avatar-colaborador me-2"> ZedZero12</a>
                        </li>
                    </ul>
                </div>
                <div class="border-top border-secondary pt-3 mt-2">
                    <a href="https://github.com/Slashdog29/Taller-ProgramacionIII" target="_blank" class="text-white-50 text-decoration-none small">
                        <i class="fab fa-github me-1"></i> Ver Repositorio del Proyecto
                    </a>
                </div>
                
                <!-- Sección de Lenguajes Utilizados -->
                <div class="mt-4 pt-3 border-top border-secondary">
                    <h6 class="text-white-50 mb-3"><i class="fas fa-code me-1"></i> Lenguajes Utilizados</h6>
                    
                    <!-- Contenedores dinámicos -->
                    <div id="github-languages-progress" class="progress mb-2" style="height: 15px; background: rgba(0,0,0,0.3);">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-secondary" style="width: 100%"></div>
                    </div>
                    <div id="github-languages-list" class="d-flex justify-content-between flex-wrap small text-white-50">
                        <span>Cargando datos...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modalColaboradores = document.getElementById('modalColaboradores');
    
    // Solo cargamos los datos cuando se abra el modal para ahorrar recursos
    modalColaboradores.addEventListener('show.bs.modal', function () {
        fetch('https://api.github.com/repos/Slashdog29/Taller-ProgramacionIII/languages')
            .then(response => response.json())
            .then(data => {
                const progressContainer = document.getElementById('github-languages-progress');
                const listContainer = document.getElementById('github-languages-list');
                
                // Limpiar contenedores
                progressContainer.innerHTML = '';
                listContainer.innerHTML = '';

                const totalBytes = Object.values(data).reduce((a, b) => a + b, 0);
                
                // Mapa de colores para los lenguajes principales
                const langColors = {
                    'PHP': 'bg-primary',
                    'JavaScript': 'bg-warning',
                    'HTML': 'bg-info',
                    'CSS': 'bg-danger',
                    'Shell': 'bg-success'
                };
                const textColors = {
                    'PHP': 'text-primary',
                    'JavaScript': 'text-warning',
                    'HTML': 'text-info',
                    'CSS': 'text-danger',
                    'Shell': 'text-success'
                };

                Object.entries(data).forEach(([lang, bytes]) => {
                    const percentage = ((bytes / totalBytes) * 100).toFixed(1);
                    const colorClass = langColors[lang] || 'bg-secondary';
                    const textColorClass = textColors[lang] || 'text-white';

                    // Añadir a la barra de progreso
                    progressContainer.innerHTML += `<div class="progress-bar ${colorClass}" role="progressbar" style="width: ${percentage}%" aria-valuenow="${percentage}" aria-valuemin="0" aria-valuemax="100" title="${lang}: ${percentage}%"></div>`;
                    
                    // Añadir a la lista de texto
                    listContainer.innerHTML += `<span><span class="${textColorClass} me-1">•</span> ${lang} ${percentage}%</span>`;
                });
            })
            .catch(error => {
                console.error('Error cargando lenguajes de GitHub:', error);
                document.getElementById('github-languages-list').innerHTML = '<span class="text-danger">Error al conectar con GitHub</span>';
            });
    }, { once: true }); // Solo ejecutar una vez por sesión de página
});
</script>