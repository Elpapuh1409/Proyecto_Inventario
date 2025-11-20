//Funciones del aplicativo Gestión de inventarios Win Sports.

document.addEventListener('DOMContentLoaded', function() {
    // Animación 1.
    document.querySelectorAll('.animate').forEach(function(el, i) {
        el.style.opacity = 0;
        setTimeout(function() {
            el.style.transition = 'opacity 0.7s';
            el.style.opacity = 1;
        }, 100 + i * 100);
    });

    // Filtrado en base de datos.
    var filterToggle = document.getElementById('filterToggle');
    var filterControls = document.getElementById('filterControls');
    if (filterToggle && filterControls) {
        filterToggle.addEventListener('click', function() {
            if (filterControls.style.display === 'none' || filterControls.style.display === '') {
                filterControls.style.display = 'block';
                filterToggle.textContent = 'Ocultar Filtros';
            } else {
                filterControls.style.display = 'none';
                filterToggle.textContent = 'Mostrar Filtros';
            }
        });
    }

    // Exportación CSV.
    document.querySelectorAll('.btn.secondary').forEach(function(btn) {
        if (btn.textContent.includes('Exportar CSV')) {
            btn.addEventListener('click', function() {
                alert('Exportar CSV próximamente.');
            });
        }
    });

    // Enviar datos a un PHP.
    function enviarDatosInventario(datos) {
        fetch('../PHP/Agregar_Datos.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(datos)
        })
        .then(res => res.json())
        .then(respuesta => {
            alert('Datos guardados correctamente');
            // Actualización de la tabla.
        })
        .catch(err => {
            alert('Error al guardar los datos');
        });
    }

    // Obtener los datos para la base de datos (PHP).
    function obtenerDatosInventario() {
        fetch('../PHP/Base_de_Datos.php')
            .then(res => res.json())
            .then(datos => {
                // Renderización de los datos en la tabla.
                console.log(datos);
            })
            .catch(err => {
                alert('Error al obtener los datos');
            });
    }
});
