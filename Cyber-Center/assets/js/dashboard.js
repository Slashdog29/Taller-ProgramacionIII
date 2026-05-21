$(document).ready(function () {
  // Evita que los menús (dropdowns o menús laterales colapsables) se cierren
  // automáticamente al hacer clic en opciones internas (modales o sub-colapsables).
  $(document).ready(function () {
    $('.dropdown-menu').on('click', function (e) {
        e.stopPropagation();
    });

 
    $('.dropdown-item:not(.no-close)').on('click', function () {
        
    });
});

  // Cerrar sesión con confirmación
  $("#btnLogout").on("click", function (e) {
    e.preventDefault();
    Swal.fire({
      title: "¿Cerrar sesión?",
      text: "¿Estás seguro de que deseas salir?",
      icon: "question",
      background: "rgba(15,23,42,0.95)",
      backdrop: "blur(10px)",
      confirmButtonColor: "#ef4444",
      cancelButtonColor: "#4f46e5",
      confirmButtonText: "Sí, salir",
      cancelButtonText: "Cancelar",
      showCancelButton: true,
    }).then((result) => {
      if (result.isConfirmed) {
        window.location.href = "salir.php";
      }
    });
  });

  // Cambio de contraseña
  $("#formCambiarPass").on("submit", function (e) {
    e.preventDefault();
    let actual = $("#actualPass").val();
    let nueva = $("#nuevaPass").val();

    if (actual.length === 0 || nueva.length < 6) {
      Swal.fire(
        "Error",
        "La nueva contraseña debe tener al menos 6 caracteres",
        "error",
      );
      return;
    }

    Swal.fire("Éxito", "Contraseña actualizada correctamente", "success").then(
      () => {
        $("#cambiarPassModal").modal("hide");
        $("#formCambiarPass")[0].reset();
      },
    );
  });

  // Respaldar BD
  $("#btnRespaldar").on("click", function () {
    let filename = $("#backupFileName").val().trim();
    if (filename === "") {
      Swal.fire(
        "Error",
        "Ingrese un nombre para el archivo de respaldo.",
        "error",
      );
      return;
    }

    window.location.href =
      "configuraciones.php?action=backup&filename=" +
      encodeURIComponent(filename);
  });

  // Restaurar BD
  $("#btnRestaurar").on("click", function () {
    let fileInput = $("#restoreFile")[0];
    if (!fileInput.files || fileInput.files.length === 0) {
      Swal.fire("Error", "Selecciona un archivo SQL para restaurar.", "error");
      return;
    }

    Swal.fire({
      title: "Restaurando base de datos",
      text: "El sistema restaurará la base de datos usando el archivo seleccionado.",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Restaurar",
      cancelButtonText: "Cancelar",
    }).then((result) => {
      if (result.isConfirmed) {
        let formData = new FormData();
        formData.append("backup_file", fileInput.files[0]);
        formData.append("action", "restore");

        $.ajax({
          url: "configuraciones.php?action=restore",
          method: "POST",
          data: formData,
          processData: false,
          contentType: false,
          dataType: "json",
          success: function (response) {
            if (response.success) {
              Swal.fire("Restauración exitosa", response.message, "success");
              $("#restaurarModal").modal("hide");
              $("#restoreFile").val("");
            } else {
              Swal.fire(
                "Error",
                response.message || "No se pudo restaurar la base de datos.",
                "error",
              );
            }
          },
          error: function (xhr) {
            let message = "Error al comunicar con el servidor.";
            if (xhr.responseJSON && xhr.responseJSON.message) {
              message = xhr.responseJSON.message;
            } else if (xhr.responseText) {
              message = xhr.responseText;
            }
            Swal.fire("Error", message, "error");
          },
        });
      }
    });
  });

  function loadHistorial() {
    $.getJSON("configuraciones.php?action=historial", function (response) {
      if (!response.success) {
        $("#historialTableBody").html(
          '<tr><td colspan="4" class="text-center text-danger">No se pudo cargar el historial.</td></tr>',
        );
        return;
      }
      if (!response.data.length) {
        $("#historialTableBody").html(
          '<tr><td colspan="4" class="text-center text-white-50">No hay registros en el historial.</td></tr>',
        );
        return;
      }
      let rows = response.data.map(function (item) {
        return (
          "<tr>" +
          "<td>" +
          item.fyh +
          "</td>" +
          "<td>" +
          item.usuario +
          "</td>" +
          "<td>" +
          item.sector +
          "</td>" +
          "<td>" +
          item.acciones +
          "</td>" +
          "</tr>"
        );
      });
      $("#historialTableBody").html(rows.join(""));
    }).fail(function () {
      $("#historialTableBody").html(
        '<tr><td colspan="4" class="text-center text-danger">No se pudo cargar el historial.</td></tr>',
      );
    });
  }

  function loadTransacciones() {
    $.getJSON("configuraciones.php?action=transacciones", function (response) {
      if (!response.success) {
        $("#transaccionesTableBody").html(
          '<tr><td colspan="5" class="text-center text-danger">No se pudo cargar las transacciones.</td></tr>',
        );
        return;
      }
      if (!response.data.length) {
        $("#transaccionesTableBody").html(
          '<tr><td colspan="5" class="text-center text-white-50">No hay transacciones disponibles.</td></tr>',
        );
        return;
      }
      let rows = response.data.map(function (item, index) {
        return (
          "<tr>" +
          "<td>" +
          (index + 1) +
          "</td>" +
          "<td>" +
          item.tipo +
          "</td>" +
          "<td>" +
          item.descripcion +
          "</td>" +
          "<td>" +
          item.usuario +
          "</td>" +
          "<td>" +
          item.fecha +
          "</td>" +
          "</tr>"
        );
      });
      $("#transaccionesTableBody").html(rows.join(""));
    }).fail(function () {
      $("#transaccionesTableBody").html(
        '<tr><td colspan="5" class="text-center text-danger">No se pudo cargar las transacciones.</td></tr>',
      );
    });
  }

  $("#historialModal").on("show.bs.modal", function () {
    $("#historialTableBody").html(
      '<tr><td colspan="4" class="text-center text-white-50">Cargando historial...</td></tr>',
    );
    loadHistorial();
  });

  $("#transaccionesModal").on("show.bs.modal", function () {
    $("#transaccionesTableBody").html(
      '<tr><td colspan="5" class="text-center text-white-50">Cargando transacciones...</td></tr>',
    );
    loadTransacciones();
  });
});
