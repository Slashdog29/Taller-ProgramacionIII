$(document).ready(function() {
    // Cerrar sesión con confirmación
    $('#btnLogout').on('click', function(e) {
        e.preventDefault();
        Swal.fire({
            title: '¿Cerrar sesión?',
            text: '¿Estás seguro de que deseas salir?',
            icon: 'question',
            background: 'rgba(15,23,42,0.95)',
            backdrop: 'blur(10px)',
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#4f46e5',
            confirmButtonText: 'Sí, salir',
            cancelButtonText: 'Cancelar',
            showCancelButton: true
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '../salir.php';
            }
        });
    });

    // Cambio de contraseña
    $('#formCambiarPass').on('submit', function(e) {
        e.preventDefault();
        let actual = $('#actualPass').val();
        let nueva = $('#nuevaPass').val();

        if (actual.length === 0 || nueva.length < 6) {
            Swal.fire('Error', 'La nueva contraseña debe tener al menos 6 caracteres', 'error');
            return;
        }

        // Simulamos éxito temporal
        Swal.fire('Éxito', 'Contraseña actualizada correctamente', 'success').then(() => {
            $('#cambiarPassModal').modal('hide');
            $('#formCambiarPass')[0].reset();
        });
    });
});