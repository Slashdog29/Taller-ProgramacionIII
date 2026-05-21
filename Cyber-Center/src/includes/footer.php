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
                            <a href="https://github.com/bellomoreno225-droid" target="_blank" class="text-white text-decoration-none"><img src="https://github.com/bellomoreno225-droid.png" class="avatar-colaborador me-2"> Tupapa</a>
                        </li>
                        <li class="mb-3">
                            <a href="https://github.com/thejoker-afk" target="_blank" class="text-white text-decoration-none"><img src="https://github.com/thejoker-afk.png" class="avatar-colaborador me-2"> TheJoker-afk</a>
                        </li>
                        <li>
                            <span class="text-white"><img src="https://ui-avatars.com/api/?name=Jorge&background=0d6efd&color=fff" class="avatar-colaborador me-2"> Jorge</span>
                        </li>
                    </ul>
                </div>
                <div class="border-top border-secondary pt-3 mt-2">
                    <a href="https://github.com/Slashdog29/Taller-ProgramacionIII" target="_blank" class="text-white-50 text-decoration-none small">
                        <i class="fab fa-github me-1"></i> Ver Repositorio del Proyecto
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>