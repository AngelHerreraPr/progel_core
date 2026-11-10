<?php
// Footer común: scripts y cierre de html
$base_path = $base_path ?? (in_array(basename(dirname($_SERVER['SCRIPT_FILENAME'] ?? '')), ['tools', 'admin', 'acciones', 'exportaciones']) ? '../' : '');
?>
</main>
<script src="<?= $base_path ?>assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<script src="<?= $base_path ?>assets/vendor/sweetalert2/sweetalert2.all.min.js"></script>

<!-- ANIMACIONES PERSONALIZADAS PARA LAS ALERTAS -->
<style>
    /* Efecto de entrada tipo "golpe" y vibración */
    @keyframes golpeYVibra {
        0% { transform: scale(0.5); opacity: 0; }
        40% { transform: scale(1.05); opacity: 1; }
        60% { transform: scale(0.95); }
        70% { transform: scale(1.02) translateX(-8px) rotate(-1deg); }
        80% { transform: scale(1) translateX(8px) rotate(1deg); }
        90% { transform: translateX(-4px) rotate(-0.5deg); }
        100% { transform: translateX(0) rotate(0); }
    }
    
    .alerta-animada-progel {
        animation: golpeYVibra 0.6s cubic-bezier(0.25, 0.8, 0.25, 1) forwards !important;
    }
</style>

<!-- SCRIPT GLOBAL DE PROTECCIÓN PARA OBSERVACIONES -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const cajasObservaciones = document.querySelectorAll('textarea[name^="obs_"], textarea[name="acciones"], textarea[name="que_se_hizo"], textarea[name="solucion_definitiva"]');

    cajasObservaciones.forEach(caja => {
        // 1. FILTRO EN TIEMPO REAL: Bloquear caracteres especiales
        caja.addEventListener('input', function() {
            this.value = this.value.replace(/[^a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s.,]/g, '');
        });

        // 2. FILTRO ANTI-TRAMPAS: Alerta Animada
        caja.addEventListener('blur', function() {
            let textoReal = this.value.replace(/[\s.,]/g, ''); 
            
            if (this.value.length > 0 && textoReal.length < 4) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: '¡JUSTIFICACIÓN INVÁLIDA!',
                        html: '<span style="font-size: 1.05rem; color: #475569;">No se permiten campos vacíos, puntos o respuestas cortas.<br><br><b class="text-dark">Por favor, describe la situación detalladamente.</b></span>',
                        icon: 'error',
                        background: '#ffffff',
                        backdrop: `rgba(15, 23, 42, 0.85)`,
                        allowOutsideClick: false,
                        confirmButtonText: '<i class="bi bi-pencil-square me-2"></i>Entendido, corregiré',
                        buttonsStyling: false,
                        showClass: {
                            popup: 'alerta-animada-progel' // Aquí conectamos la animación CSS
                        },
                        hideClass: {
                            popup: 'swal2-hide' // Animación de salida por defecto
                        },
                        customClass: {
                            popup: 'border-top border-5 border-danger rounded-4 shadow-lg',
                            title: 'fw-black text-danger',
                            confirmButton: 'btn btn-danger btn-lg fw-bold rounded-pill px-4 shadow-sm mt-3'
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            caja.focus(); 
                        }
                    });
                } else {
                    alert("¡ALERTA!\nDebes escribir una justificación real detallada.");
                    caja.focus();
                }
                
                this.value = ''; 
            }
        });
    });

    
    // Cierre automático de sesión tras 5 minutos DESACTIVADO temporalmente a petición
    /*
    const TIEMPO_LIMITE_INACTIVIDAD_MS = 5 * 60 * 1000;
    let temporizadorInactividad;

    function cerrarSesionPorInactividad() {
        window.location.href = '<?= $base_path ?>acciones/logout.php?timeout=1';
    }

    function reiniciarTemporizadorInactividad() {
        clearTimeout(temporizadorInactividad);
        temporizadorInactividad = setTimeout(cerrarSesionPorInactividad, TIEMPO_LIMITE_INACTIVIDAD_MS);
    }

    ['mousemove', 'mousedown', 'keydown', 'touchstart', 'touchend', 'touchmove', 'scroll', 'click'].forEach(evento => {
        document.addEventListener(evento, reiniciarTemporizadorInactividad, { passive: true });
    });

    reiniciarTemporizadorInactividad();
    */
});
</script>

</body>
</html>