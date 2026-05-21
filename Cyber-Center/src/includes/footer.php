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
</style>

<footer class="py-3 fixed-bottom glass-footer">
    <div class="container-fluid px-4">
        <div class="d-flex align-items-center justify-content-between small text-white-50">
            <div>
                Copyright &copy; 2026 | <strong>Equipo de Desarrollo de la Web</strong>
            </div>
            <div>
                <a href="#" class="text-white-50 text-decoration-none">Política de Privacidad</a>
                &middot;
                <a href="#" class="text-white-50 text-decoration-none">Términos &amp; Condiciones</a>
            </div>
        </div>
    </div>
</footer>