<?php
// Archivo: includes/scripts.php
?>
<script>
    function empezarReporte() {
        document.getElementById('modal-apertura').style.display = 'block';
        let ahora = new Date();
        let opcionesFecha = { year: 'numeric', month: '2-digit', day: '2-digit' };
        document.getElementById('reloj-apertura').innerText = ahora.toLocaleDateString('es-MX', opcionesFecha) + " a las " + ahora.toLocaleTimeString('es-MX');
    }

    function ocultarFormularioApertura() { document.getElementById('modal-apertura').style.display = 'none'; }

    function abrirModalCierre(id_reporte) {
        document.getElementById('modal-folio').innerText = id_reporte;
        document.getElementById('modal-id-input').value = id_reporte;
        document.getElementById('modal-cierre').style.display = 'block';
    }
    function ocultarModalCierre() { document.getElementById('modal-cierre').style.display = 'none'; }

    // ====== NUEVAS FUNCIONES DEL BOTÓN PREVENTIVOS ======
    function abrirModalPreventivos(id) {
        const fila = document.querySelector(`tr[data-id='${id}']`);
        const textoActual = fila.getAttribute('data-preventivos');
        document.getElementById('modal-prev-folio').innerText = id;
        document.getElementById('modal-prev-id').value = id;
        document.getElementById('modal-prev-texto').value = (textoActual && textoActual !== '-') ? textoActual : '';
        document.getElementById('modal-preventivos').style.display = 'block';
    }
    function ocultarModalPreventivos() { document.getElementById('modal-preventivos').style.display = 'none'; }
    // ====================================================

    function abrirModalSubirFoto(id) {
        document.getElementById('modal-foto-folio').innerText = id;
        document.getElementById('modal-foto-id').value = id;
        document.getElementById('modal-subir-foto').style.display = 'block';
    }
    function ocultarModalSubirFoto() { document.getElementById('modal-subir-foto').style.display = 'none'; }

    function abrirModalSubirDoc(id) {
        document.getElementById('modal-doc-folio').innerText = id;
        document.getElementById('modal-doc-id').value = id;
        document.getElementById('modal-subir-doc').style.display = 'block';
    }
    function ocultarModalSubirDoc() { document.getElementById('modal-subir-doc').style.display = 'none'; }

    function abrirDetallesFila(id) {
        const fila = document.querySelector(`tr[data-id='${id}']`);
        abrirDetalles(fila);
    }

    function abrirDetalles(fila) {
        document.getElementById('det-folio').innerText = fila.getAttribute('data-id');
        document.getElementById('det-icono').innerHTML = fila.getAttribute('data-icono');
        
        document.getElementById('det-ubicacion').innerHTML = "<strong>" + fila.getAttribute('data-zona') + "</strong>" +
            "<br>Área: " + fila.getAttribute('data-equipo') +
            "<br>Equipo: " + fila.getAttribute('data-equipo-nombre');
        document.getElementById('det-apertura').innerText = fila.getAttribute('data-apertura');
        document.getElementById('det-cierre').innerText = fila.getAttribute('data-cierre');
        document.getElementById('det-incidente').innerText = fila.getAttribute('data-incidente');
        document.getElementById('det-que-se-hizo').innerText = fila.getAttribute('data-que-se-hizo');
        document.getElementById('det-solucion-definitiva').innerText = fila.getAttribute('data-solucion-definitiva');
        document.getElementById('det-preventivos').innerText = fila.getAttribute('data-preventivos') || '-';
        document.getElementById('det-fecha-compromiso').innerText = fila.getAttribute('data-fecha-compromiso');
        document.getElementById('det-responsable').innerText = fila.getAttribute('data-responsable');
        document.getElementById('det-numero-nomina').innerText = fila.getAttribute('data-numero-nomina');

        document.getElementById('modal-detalles').style.display = 'block';
    }
    function ocultarModalDetalles() { document.getElementById('modal-detalles').style.display = 'none'; }

    function verEvidencia(event, url, rutaArchivo) {
        event.preventDefault(); event.stopPropagation();
        const extension = rutaArchivo.split('.').pop().toLowerCase();
        const esImagen = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'].includes(extension);

        if (esImagen) {
            document.getElementById('img-evidencia').src = url;
            document.getElementById('modal-evidencia').style.display = 'block';
        } else {
            window.open(url, '_blank');
        }
    }
    function ocultarModalEvidencia() {
        document.getElementById('modal-evidencia').style.display = 'none';
        document.getElementById('img-evidencia').src = '';
    }

    function abrirVisorDoble(event, urlFalla, urlSolucion) {
        event.stopPropagation();
        // Asignamos las URLs a las imágenes del nuevo modal
        document.getElementById('img-visor-falla').src = urlFalla;
        document.getElementById('img-visor-solucion').src = urlSolucion;
        // Mostramos el modal de comparativa
        document.getElementById('modal-visor-doble').style.display = 'block';
    }

    function abrirModalGaleria(event, id_ticket, rutasJson) {
        event.stopPropagation();
        let rutas = JSON.parse(rutasJson);
        let contenedor = document.getElementById('galeria-links');
        contenedor.innerHTML = '';
        rutas.forEach((ruta, index) => {
            let url = `descargar.php?id=${id_ticket}&idx=${index}`;
            contenedor.innerHTML += `<a href="#" onclick="verEvidencia(event, '${url}', '${ruta}')" class="btn-principal" style="display: block; width: 100%; box-sizing: border-box; margin-bottom: 10px; text-decoration: none; text-align: center;">📄 Abrir Archivo ${index + 1}</a>`;
        });
        document.getElementById('modal-galeria').style.display = 'block';
    }
    function ocultarModalGaleria() { document.getElementById('modal-galeria').style.display = 'none'; }


    // FUNCIÓN DE ACCESO RÁPIDO DESDE EL TABLERO
    function filtrarPorTarjeta(estadoObjetivo) {
        const selectorEstado = document.getElementById('filtro-estado');
        if (selectorEstado) {
            selectorEstado.value = estadoObjetivo;
            filtrarTabla();
        }
    }

    function filtrarTabla() {
        const filtroTipo = document.getElementById('filtro-tipo') ? document.getElementById('filtro-tipo').value.toLowerCase() : '';
        const filtroEstado = document.getElementById('filtro-estado') ? document.getElementById('filtro-estado').value : '';

        const tablas = document.querySelectorAll('.tabla-contenedor table');

        tablas.forEach(tabla => {
            const filas = tabla.querySelectorAll('tbody tr');

            filas.forEach(fila => {
                if (fila.classList.contains('sin-resultados')) return;
                if (fila.cells.length === 1 && fila.textContent.trim() !== '') return;

                const rowTipo = (fila.getAttribute('data-tipo') || '').toLowerCase();
                const rowEstado = fila.getAttribute('data-estado') || '';
                const coincideCodigo = !filtroTipo || filtroTipo === 'todos' || rowTipo === filtroTipo;

                // Lógica de filtro de estado mejorada
                let coincideEstado = false;
                if (!filtroEstado || filtroEstado === 'todos') {
                    coincideEstado = true;
                } else {
                    coincideEstado = (rowEstado === filtroEstado);
                }

                if (coincideCodigo && coincideEstado) {
                    fila.style.display = '';
                } else {
                    fila.style.display = 'none';
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        const status = urlParams.get('status');
        let message = '';
        let type = '';

        switch (status) {
            case 'success_add': message = 'Nuevo incidente registrado con éxito.'; type = 'success'; break;
            case 'success_close': message = 'Actualizado correctamente.'; type = 'success'; break;
            case 'success_reenvio': message = 'Notificación reenviada a Teams y WhatsApp.'; type = 'success'; break;
            case 'error_reenvio': message = 'Error al encontrar el ticket para reenviar.'; type = 'error'; break;
            case 'success_finalized': message = 'Ticket finalizado y cerrado correctamente.'; type = 'success'; break;
            case 'error_finalized': message = 'Error al intentar finalizar el ticket.'; type = 'error'; break;
            case 'success_rejection': message = 'Causa Raíz Rechazada. El ticket ha sido re-abierto.'; type = 'success'; break;
            case 'error_rejection_db': message = 'Hubo un problema al actualizar la base de datos al rechazar.'; type = 'error'; break;
            case 'error_rejection_invalid': message = 'El ticket no está en un estado válido para rechazar la Causa Raíz.'; type = 'error'; break;
            case 'error_rejection_param': message = 'ID de ticket no proporcionado para rechazar Causa Raíz.'; type = 'error'; break;
            case 'error_add': case 'error_upload': case 'error_folder': case 'error_db': case 'error_access': message = 'Ocurrió un error. Inténtalo de nuevo.'; type = 'error'; break;
            case 'error_evidence': message = 'El Código Rojo requiere evidencia obligatoria.'; type = 'error'; break;
            case 'error_nomina': message = 'El número de nómina de quien reporta es obligatorio.'; type = 'error'; break;
        }

        if (message) {
            showToast(message, type);
            history.replaceState(null, '', window.location.pathname);
        }

        ['filtro-tipo', 'filtro-estado'].forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('change', filtrarTabla);
            }
        });

        filtrarTabla();
    });

    function showToast(message, type = 'success') {
        const toast = document.getElementById('toast-notification');
        if (!toast) return;
        const icon = toast.querySelector('.toast-icon');
        const title = toast.querySelector('.toast-title');
        const msg = toast.querySelector('.toast-message');
        const progress = toast.querySelector('.toast-progress');

        progress.style.animation = 'none';
        if (type === 'success') {
            icon.innerHTML = '<i class="fas fa-check-circle"></i>';
            title.textContent = 'Éxito';
        } else {
            icon.innerHTML = '<i class="fas fa-times-circle"></i>';
            title.textContent = 'Error';
        }
        msg.textContent = message;
        toast.className = 'toast show ' + type;
        void progress.offsetWidth;
        progress.style.animation = 'progress 4s linear forwards';
        setTimeout(() => { toast.className = toast.className.replace('show', ''); }, 4000);
    }

    // Catálogo general de Zonas -> Áreas -> Equipos
const estructuraPlanta = {
    "Zona negra": {
        "QUÍMICOS BAJA": [
            "TANQUE 1 DE ÁCIDO SULFÚRICO", "TANQUE 2 DE ÁCIDO SULFÚRICO", "BOMBA DE ÁCIDO SULFÚRICO",
            "TANQUE DE HIDRÓXIDO DE SODIO", "BOMBA DE HIDRÓXIDO DE SODIO", "TANQUE 1 DE PERÓXIDO DE HIDRÓGENO",
            "TANQUE 2 DE PERÓXIDO DE HIDRÓGENO", "BOMBA DE PERÓXIDO DE HIDRÓGENO"
        ],
        "QUÍMICOS ALTA": [
            "TANQUE DE ÁCIDO SULFÚRICO", "TANQUE DE HIDRÓXIDO DE SODIO", "TANQUE DE PERÓXIDO DE HIDRÓGENO"
        ],
        "TORRES DE ENFRIAMIENTO": [
            "TORRE DE ENFRIAMIENTO 1", "MOTOR DE TORRE DE ENFRIAMIENTO 1", "TORRE DE ENFRIAMIENTO 2",
            "MOTOR DE TORRE DE ENFRIAMIENTO 2", "TORRE DE ENFRIAMIENTO 3", "MOTOR DE TORRE DE ENFRIAMIENTO 3",
            "BOMBA 1 AGUA LIMPIA PREPARADORES", "BOMBA 2 AGUA LIMPIA PREPARADORES", "BOMBA 3 AGUA LIMPIA PREPARADORES",
            "BOMBA 4 AGUA LIMPIA PREPARADORES", "BOMBA 5 AGUA LIMPIA PREPARADORES", "BOMBA 6 AGUA LIMPIA PREPARADORES",
            "CISTERNA DE PREPARACIÓN", "BOMBA 1 AGUA LIMPIA MOLINOS", "BOMBA 2 AGUA LIMPIA MOLINOS"
        ],
        "MOLINOS": [
            "GRÚA FREIGHLITNER", "GRÚA INTERNATIONAL", "GRÚA DE MOLINOS 1", "GRÚA DE MOLINOS 2",
            "MOLINO DE CUERO 1", "MOLINO DE CUERO 2", "MOLINO DE CUERO 3",
            "BOMBA 1 DE CUERO DE MOLINOS", "BOMBA 2 DE CUERO DE MOLINOS"
        ],
        "PREPARADORES": [
            "BOMBA 1 DE CUERO DE PREPARADORES", "BOMBA 2 DE CUERO DE PREPARADORES", "PREPARADOR 1", "PREPARADOR 2",
            "PREPARADOR 5", "PREPARADOR 6", "PREPARADOR 7", "PREPARADOR 8", "PREPARADOR 9", "PREPARADOR 10",
            "PREPARADOR 11", "BOMBA 3 DE CUERO DE PREPARADORES", "BOMBA 4 DE CUERO DE PREPARADORES", "PREPARADOR 12",
            "PREPARADOR 13", "PREPARADOR 14", "PREPARADOR 15", "PREPARADOR 16", "PREPARADOR 17", "PREPARADOR 18",
            "PREPARADOR 19", "PREPARADOR 20", "BOMBA RECUPERACIÓN DE ENZIMA 1", "BOMBA RECUPERACIÓN DE ENZIMA 2"
        ]
    },
    "Zona gris": {
        "CALDERAS": [
            "SUAVIZADOR 1", "SUAVIZADOR 2", "SUAVIZADOR 3", "SUAVIZADOR 4", "BOMBA 1 RETORNO A CALDERAS",
            "BOMBA 2 RETORNO A CALDERAS", "TANQUE DE CONDENSADOS CALDERA CLEAVER", "BOMBA 1 DE ALIMENTACIÓN A CALDERA CLEAVER",
            "BOMBA 2 DE ALIMENTACIÓN A CALDERA CLEAVER", "CALDERA CLEAVER", "TANQUE DE CONDENSADOS CALDERA MYRGGO",
            "BOMBA 1 DE ALIMENTACIÓN DE CALDERA MYRGGO", "BOMBA 2 DE ALIMENTACIÓN A CALDERA MYRGGO", "CALDERA MYRGGO", "CISTERNA DE AGUA SUAVIZADA"
        ],
        "PRODUCCIÓN": ["COMPRESOR 50 HP", "COMPRESOR 30 HP", "SECADOR DE AIRE"],
        "EXTRACCIÓN": ["BOMBA SUMERGIBLE DE CÁRCAMO 1", "BOMBA SUMERGIBLE DE CÁRCAMO 2", "TANQUE DE GRASA", "BOMBA DE TANQUE DE GRASA"],
        "RECEPTOR": [
            "BOMBA DE CUERO 1 DE RECEPTOR", "BOMBA DE CUERO 2 DE RECEPTOR", "TOLVA DE CUERO",
            "COLADOR ROTATORIO 1 DE TOLVA DE CUERO", "MOTOR REDUCTOR DE COLADOR ROTATORIO 1 DE TOLVA DE CUERO",
            "COLADOR ROTATORIO 2 DE TOLVA DE CUERO", "MOTOR REDUCTOR DE COLADOR ROTATORIO 2 DE TOLVA DE CUERO",
            "MOTOR REDUCTOR DE COLADOR DE TOLVA DE CUERO DE RECHAZO", "GUSANO DE CUERO 1", "MOTOR REDUCTOR DE GUSANO DE CUERO 1",
            "GUSANO DE CUERO 2", "MOTOR REDUCTOR DE GUSANO DE CUERO 2"
        ],
        "COCEDORES": [
            "COCEDOR CONTINUO 1", "MOTOR REDUCTOR DE AGITADOR DE COCEDOR CONTINUO 1", "MOTOR REDUCTOR DE FELPA COCEDOR CONTINUO 1",
            "COCEDOR CONTINUO 2", "MOTOR REDUCTOR DE AGITADOR DE COCEDOR CONTINUO 2", "MOTOR REDUCTOR DE FELPA COCEDOR CONTINUO 2",
            "COCEDOR CONTINUO 3", "MOTOR REDUCTOR DE AGITADOR DE COCEDOR CONTINUO 3", "MOTOR REDUCTOR DE FELPA COCEDOR CONTINUO 3",
            "COCEDOR CONTINUO 4", "MOTOR REDUCTOR DE AGITADOR DE COCEDOR CONTINUO 4", "MOTOR REDUCTOR DE FELPA COCEDOR CONTINUO 4",
            "COCEDOR CONTINUO 5", "MOTOR REDUCTOR DE AGITADOR DE COCEDOR CONTINUO 5", "MOTOR REDUCTOR DE FELPA COCEDOR CONTINUO 5",
            "COCEDOR CONTINUO 6", "MOTOR REDUCTOR DE AGITADOR DE COCEDOR CONTINUO 6", "MOTOR REDUCTOR DE FELPA COCEDOR CONTINUO 6",
            "COCEDOR CONTINUO 7", "MOTOR REDUCTOR DE AGITADOR DE COCEDOR CONTINUO 7", "MOTOR REDUCTOR DE FELPA COCEDOR CONTINUO 7",
            "COCEDOR CONTINUO 8", "MOTOR REDUCTOR DE AGITADOR DE COCEDOR CONTINUO 8", "MOTOR REDUCTOR DE FELPA COCEDOR CONTINUO 8",
            "COCEDOR CONTINUO 9", "MOTOR REDUCTOR DE AGITADOR DE COCEDOR CONTINUO 9", "MOTOR REDUCTOR DE FELPA COCEDOR CONTINUO 9",
            "BOMBA DE PURGA DE COCEDORES", "TANQUE DE AGUA DE COCIMIENTO", "BOMBA 1 DE ALIMENTACIÓN A COCEDORES",
            "BOMBA 2 DE ALIMENTACIÓN A COCEDORES", "BOMBA DE AGUA DE LAVADO", "INTERCAMBIADOR DE PLACAS",
            "BOMBA 1 INTERCAMBIADOR DE PLACAS", "BOMBA 2 INTERCAMBIADOR DE PLACAS", "BOMBA 1 INTERCAMBIADOR DE CALANDRIA",
            "BOMBA 2 INTERCAMBIADOR DE CALANDRIA", "INTERCAMBIADOR DE AGUA CALIENTE", "BOMBA 1 DE AGUA CALIENTE SUAVIZADA",
            "BOMBA 2 DE AGUA CALIENTE SUAVIZADA", "OLLA 1", "MOTOR REDUCTOR DE AGITADOR DE OLLA 1", "OLLA 2",
            "MOTOR REDUCTOR DE AGITADOR DE OLLA 2", "OLLA 3", "MOTOR REDUCTOR DE AGITADOR DE OLLA 3",
            "COLADOR DE PURGA DE OLLAS", "MOTOR REDUCTOR DE COLADOR DE PURGA DE OLLAS", "BOMBA DE RETORNO DE OLLAS"
        ],
        "CLARIFICADO": [
            "CLARIFICADOR 1", "MOTOR REDUCTOR DE BRAZO DE CLARIFICADOR 1", "BOMBA NIKUNI 1 A CLARIFICADOR 1",
            "BOMBA NIKUNI 1 B CLARIFICADOR 1", "TANQUE DE LODOS DE CLARIFICADOR 1", "BOMBA DE TANQUE DE LODOS DE CLARIFICADOR 1",
            "CLARIFICADOR 2", "MOTOR REDUCTOR DE BRAZO DE CLARIFICADOR 2", "BOMBA NIKUNI 2 CLARIFICADOR 2",
            "TANQUE DE LODOS DE CLARIFICADOR 2", "BOMBA DE TANQUE DE LODOS DE CLARIFICADOR 2", "MOTOR REDUCTOR DEL DESNATADOR",
            "COLADOR ROTATORIO DE CALDO 1", "MOTOR REDUCTOR DE COLADOR ROTATORIO DE CALDO 1", "BOMBA DE RECHAZO DE COLADOR ROTATORIO DE CALDO 1",
            "COLADOR ROTATORIO DE CALDO 2", "MOTOR REDUCTOR DE COLADOR ROTATORIO DE CALDO 2", "BOMBA DE RECHAZO DE COLADOR ROTATORIO DE CALDO 2",
            "TANQUE DE DESECHO DE COLADORES DE CALDO", "BOMBA DE DESECHO DE COLADORES DE CALDO", "TANQUE DE BALANCE",
            "BOMBA 1 DE TANQUE DE BALANCE", "BOMBA 2 DE TANQUE DE BALANCE", "TANQUE DE POLÍMERO 1", "MOTOR REDUCTOR DE TANQUE DE POLÍMERO 1",
            "TANQUE DE POLÍMERO 2", "MOTOR REDUCTOR DE TANQUE DE POLÍMERO 2", "BOMBA MOYNO DE POLÍMERO 1", "BOMBA MOYNO DE POLÍMERO 2",
            "TANQUE 5000", "BOMBA 1 DE TANQUE 5000", "TANQUE DE RECUPERACIÓN DE CALDO"
        ],
        "MEMBRANAS": [
            "TANQUE PREUFF", "BOMBA 1 DE TANQUE PREUFF", "BOMBA 2 DE TANQUE PREUFF", "BOMBA DE TANQUE SEPARADOR HORIZONTAL (BOMBA DE ELEVADO)",
            "PAQUETE 1", "MOTOBOMBA DE PAQUETE DE MEMBRANAS 1", "PAQUETE 2", "MOTOBOMBA DE PAQUETE DE MEMBRANAS 2",
            "PAQUETE 3", "MOTOBOMBA DE PAQUETE DE MEMBRANAS 3", "PAQUETE 4", "MOTOBOMBA DE PAQUETE DE MEMBRANAS 4",
            "PAQUETE 5", "MOTOBOMBA DE PAQUETE DE MEMBRANAS 5", "PAQUETE 6", "MOTOBOMBA DE PAQUETE DE MEMBRANAS 6",
            "PAQUETE 7", "MOTOBOMBA DE PAQUETE DE MEMBRANAS 7", "PAQUETE 8", "MOTOBOMBA DE PAQUETE DE MEMBRANAS 8",
            "PAQUETE 9", "MOTOBOMBA DE PAQUETE DE MEMBRANAS 9", "PAQUETE 10", "MOTOBOMBA 1 DE PAQUETE DE MEMBRANAS 10",
            "MOTOBOMBA 2 DE PAQUETE DE MEMBRANAS 10", "PAQUETE 11", "MOTOBOMBA DE PAQUETE DE MEMBRANAS 11",
            "PAQUETE 12", "MOTOBOMBA DE PAQUETE DE MEMBRANAS 12", "PAQUETE 13", "MOTOBOMBA 1 DE PAQUETE DE MEMBRANAS 13",
            "MOTOBOMBA 2 DE PAQUETE DE MEMBRANAS 13", "TANQUE 1 LAVADO DE MEMBRANAS", "BOMBA DE TANQUE 1 LAVADO DE MEMBRANAS",
            "TANQUE 2 LAVADO DE MEMBRANAS", "BOMBA DE TANQUE 2 LAVADO DE MEMBRANAS", "TANQUE FILTRO DE PULIDO",
            "MOTOR REDUCTOR DE AGITADOR DE TANQUE FILTRO DE PULIDO", "BOMBA 1 DE TANQUE DE FILTRO DE PULIDO",
            "BOMBA 2 DE TANQUE DE FILTRO DE PULIDO", "FILTRO DE PULIDO 1", "FILTRO DE PULIDO 2"
        ],
        "CONCENTRADORES": [
            "TANQUE ALIMENTACIÓN A CONCENTRADORES", "BOMBA 1 DE TANQUE ALIMENTACIÓN A CONCENTRADORES", "BOMBA 2 DE TANQUE ALIMENTACIÓN A CONCENTRADORES",
            "CONCENTRADOR 1", "MOTOR DE CONCENTRADOR 1", "BOMBA MOYNO DE CONCENTRADOR 1", "BOMBA DE VACÍO DE CONCENTRADOR 1",
            "BOMBA AUXILIAR DE VACÍO DE CONCENTRADOR 1", "BOMBA 1 DE CONCENTADOR 1 DE RETORNO A TORRES DE ENFRIAMIENTO",
            "BOMBA 2 DE CONCENTADOR 1 DE RETORNO A TORRES DE ENFRIAMIENTO", "CONCENTRADOR 2", "MOTOR DE CONCENTRADOR 2",
            "BOMBA MOYNO DE CONCENTRADOR 2", "BOMBA DE VACÍO DE CONCENTRADOR 2", "BOMBA AUXILIAR DE VACÍO DE CONCENTRADOR 2",
            "BOMBA DE CONCENTADOR 2 DE RETORNO A TORRES DE ENFRIAMIENTO", "CONCENTRADOR 3", "MOTOR DE CONCENTRADOR 3",
            "BOMBA MOYNO DE CONCENTRADOR 3", "BOMBA DE VACÍO DE CONCENTRADOR 3", "BOMBA AUXILIAR DE VACÍO DE CONCENTRADOR 3",
            "BOMBA DE CONCENTADOR 3 DE RETORNO A TORRES DE ENFRIAMIENTO", "CONCENTRADOR 4", "MOTOR DE CONCENTRADOR 4",
            "BOMBA MOYNO DE CONCENTRADOR 4", "BOMBA 1 DE VACÍO DE CONCENTRADOR 4", "BOMBA 2 DE VACÍO DE CONCENTRADOR 4",
            "BOMBA DE CONCENTADOR 4 DE RETORNO A TORRES DE ENFRIAMIENTO", "TANQUE DE CALDO DE CONCENTRADO 1",
            "MOTOR REDUCTOR DE AGITADOR DE TANQUE DE CALDO CONCENTRADO 1", "TANQUE DE CALDO DE CONCENTRADO 2",
            "MOTOR REDUCTOR DE AGITADOR DE TANQUE DE CALDO CONCENTRADO 2", "TANQUE DE LAVADO DE CONCENTRADORES",
            "BOMBA DE LAVADO DE CONCENTRADORES", "BOMBA DE LUBRICACIÓN DE SELLOS 1", "BOMBA DE LUBRICACIÓN DE SELLOS 2",
            "BOMBA DE LUBRICACIÓN DE SELLOS 3", "BOMBA DE ALIMENTACIÓN DE EVAPORADOR INVERTIDO", "BOMBA DE PASO 1 A 2 DE EVAPORADOR INVERTIDO",
            "BOMBA DE RECIRCULACIÓN PASO 2 DE EVAPORADOR INVERTIDO", "BOMBA DE SALIDA DE PASO 2 DE EVAPORADOR INVERTIDO",
            "BOMBA DE SALIDA DE PASO 3 DE EVAPORADOR INVERTIDO", "BOMBA DE CONDENSADOS DE EVAPORADOR INVERTIDO",
            "BOMBA DE VACÍO DE EVAPORADOR INVERTIDO", "INTERCAMBIADOR DE PLACAS DE ALIMENTACIÓN A EVAPORADOR INVERTIDO"
        ]
    },
    "Zona blanca": {
        "SECADO": [
            "TANQUE DE ALIMENTACIÓN DE CALDO DE SECADOR 1", "MOTOR REDUCTOR DE AGITADOR TANQUE DE ALIMENTACIÓN DE CALDO DE SECADOR 1",
            "BOMBA MOYNO 1 DE TANQUE DE ALIMENTACIÓN DE CALDO DE SECADOR 1", "BOMBA MOYNO 2 DE TANQUE DE ALIMENTACIÓN DE CALDO DE SECADOR 1",
            "BOMBA MOYNO AUXILIAR DE TANQUE DE ALIMENTACIÓN DE CALDO DE SECADOR 1", "VOTATOR 1 A", "MOTOR REDUCTOR OSCILADOR DE VOTATOR 1 A",
            "MOTOR REDUCTOR EXTRUSOR DE VOTATOR 1 A", "VOTATOR 1 B", "MOTOR REDUCTOR OSCILADOR DE VOTATOR 1 B", "MOTOR REDUCTOR EXTRUSOR DE VOTATOR 1 B",
            "TANQUE DE ALIMENTACIÓN DE CALDO DE SECADOR 2", "MOTOR REDUCTOR DE AGITADOR TANQUE DE ALIMENTACIÓN DE CALDO DE SECADOR 2",
            "BOMBA MOYNO 1 DE TANQUE DE ALIMENTACIÓN DE CALDO DE SECADOR 2", "BOMBA MOYNO 2 DE TANQUE DE ALIMENTACIÓN DE CALDO DE SECADOR 2",
            "BOMBA MOYNO AUXILIAR DE TANQUE DE ALIMENTACIÓN DE CALDO DE SECADOR 2", "VOTATOR 2 A", "MOTOR REDUCTOR OSCILADOR DE VOTATOR 2 A",
            "MOTOR REDUCTOR EXTRUSOR DE VOTATOR 2 A", "VOTATOR 2 B", "MOTOR REDUCTOR OSCILADOR DE VOTATOR 2 B", "MOTOR REDUCTOR EXTRUSOR DE VOTATOR 2 B",
            "VOTATOR 3", "MOTOR REDUCTOR OSCILADOR DE VOTATOR 3", "MOTOR REDUCTOR EXTRUSOR DE VOTATOR 3", "VOTATOR 4",
            "MOTOR REDUCTOR OSCILADOR DE VOTATOR 4", "MOTOR REDUCTOR EXTRUSOR DE VOTATOR 4", "VOTATOR 5",
            "MOTOR REDUCTOR OSCILADOR DE VOTATOR 5", "MOTOR REDUCTOR EXTRUSOR DE VOTATOR 5", "TAPETE SECADOR 1",
            "MOTOR REDUCTOR DE TAPETE SECADOR 1", "BATERÍA RECÁMARA 1 SECADOR 1", "VENTILADOR A RECÁMARA 1 SECADOR 1",
            "VENTILADOR B RECÁMARA 1 SECADOR 1", "VENTILADOR C RECÁMARA 1 SECADOR 1", "BATERÍA RECÁMARA 2 SECADOR 1",
            "BATERÍA RECÁMARA 3 SECADOR 1", "VENTILADOR A RECÁMARA 3 SECADOR 1", "VENTILADOR B RECÁMARA 3 SECADOR 1",
            "VENTILADOR C RECÁMARA 3 SECADOR 1", "BATERÍA RECÁMARA 4 SECADOR 1", "BATERÍA RECÁMARA 5 SECADOR 1",
            "VENTILADOR A RECÁMARA 5 SECADOR 1", "VENTILADOR B RECÁMARA 5 SECADOR 1", "VENTILADOR C RECÁMARA 5 SECADOR 1",
            "BATERÍA RECÁMARA 6 SECADOR 1", "BATERÍA RECÁMARA 7 SECADOR 1", "VENTILADOR A RECÁMARA 7 SECADOR 1",
            "VENTILADOR B RECÁMARA 7 SECADOR 1", "VENTILADOR C RECÁMARA 7 SECADOR 1", "BATERÍA RECÁMARA 8 SECADOR 1",
            "BATERÍA RECÁMARA 9 SECADOR 1", "RODILLO PREQUEBRADOR DE SECADOR 1", "MOTOR REDUCTOR DE RODILLO PREQUEBRADOR DE SECADOR 1",
            "VIBRADOR SECADOR 1", "SISTEMA NEUMÁTICA PARA VIBRADOR SECADOR 1", "MOLINO DE MUELAS SECADOR 1",
            "MOTOR DE MOLINO DE MUELAS SECADOR 1", "SILO SECADOR 1", "SOPLADOR DE MOLINO SECADOR 1", "SOPLADOR DE SISTEMA DE TRANSPORTE SECADOR 1",
            "ROTATIVA 1 DE SECADOR 1", "ROTATIVA 2 DE SECADOR 1", "TAPETE SECADOR 2", "MOTOR REDUCTOR DE TAPETE SECADOR 2",
            "BATERÍA RECÁMARA 1 SECADOR 2", "VENTILADOR A RECÁMARA 1 SECADOR 2", "VENTILADOR B RECÁMARA 1 SECADOR 2",
            "VENTILADOR C RECÁMARA 1 SECADOR 2", "BATERÍA RECÁMARA 2 SECADOR 2", "BATERÍA RECÁMARA 3 SECADOR 2",
            "VENTILADOR A RECÁMARA 3 SECADOR 2", "VENTILADOR B RECÁMARA 3 SECADOR 2", "VENTILADOR C RECÁMARA 3 SECADOR 2",
            "BATERÍA RECÁMARA 4 SECADOR 2", "BATERÍA RECÁMARA 5 SECADOR 2", "VENTILADOR A RECÁMARA 5 SECADOR 2",
            "VENTILADOR B RECÁMARA 5 SECADOR 2", "VENTILADOR C RECÁMARA 5 SECADOR 2", "BATERÍA RECÁMARA 6 SECADOR 2",
            "BATERÍA RECÁMARA 7 SECADOR 2", "VENTILADOR A RECÁMARA 7 SECADOR 2", "VENTILADOR B RECÁMARA 7 SECADOR 2",
            "VENTILADOR C RECÁMARA 7 SECADOR 2", "BATERÍA RECÁMARA 8 SECADOR 2", "BATERÍA RECÁMARA 9 SECADOR 2",
            "SISTEMA DE ENFRIAMIENTO DE SECADOR 2", "RODILLO 1 PREQUEBRADOR SECADOR 2", "MOTOR REDUCTOR DE RODILLO 1 PREQUEBRADOR SECADOR 2",
            "RODILLO 2 PREQUEBRADOR SECADOR 2", "MOTOR REDUCTOR DE RODILLO 2 PREQUEBRADOR SECADOR 2", "VIBRADOR SECADOR 2",
            "SISTEMA NEUMÁTICO SECADOR 2", "MOLINO DE GRANO SECADOR 2", "MOTOR DE MOLINO DE GRANO SECADOR 2",
            "SOPLADOR SECADOR 2", "SISTEMA DE RECOLECTOR DE POLVOS", "SISTEMA DE TRANSPORTE HVAC", "SILO 1", "SILO 2"
        ],
        "MOLIENDA": [
            "SILO 3", "SILO 4", "VÁLVULA ROTATIVA DE SILOS", "MOLINO DE GRANO", "ROTATIVA DE MOLINO DE GRANO",
            "SOPLADOR DE MOLINO DE GRANO", "BANDA TRANSPORTADORA DE MOLINO DE GRANO", "MOLINO STEDMAN",
            "ROTATIVA DE MOLINO STEDMAN", "SOPLADOR DE MOLINO STEDMAN", "TAMIZ DE MOLIENDA", "MOTOR DE TAMIZ DE MOLIENDA"
        ],
        "REVOLTURAS": [
            "MEZCLADORA 1", "TRANSMISIÓN MEZCLADORA 1", "SISTEMA DE TRANSPORTE MEZCLADORA 1", "TAMIZ 1 DE REVOLTURAS",
            "MOTOR DE TAMIZ 1 DE REVOLTURAS", "DETECTOR DE METALES DE TAMIZ 1", "MEZCLADORA 2", "TRANSMISIÓN MEZCLADORA 2",
            "SISTEMA DE TRANSPORTE MEZCLADORA 2", "TAMIZ 2 DE REVOLTURAS", "MOTOR DE TAMIZ 2 DE REVOLTURAS",
            "DETECTOR DE METALES DE TAMIZ 2", "ENSACADORA 1", "MOTOR DE BANDA DE ENSACADORA 1", "ENSACADORA 2",
            "MOTOR DE BANDA DE ENSACADORA 2", "MOTOR DE COCEDORA DE ENSACADORAS", "EMBOLSADORA 1",
            "MOTOR DE BANDA TRANSPORTADORA DE EMBOLSADORA 1", "EMBOLSADORA 2", "MOTOR DE BANDA TRANSPORTADORA DE EMBOLSADORA 2",
            "ENCINTADORA", "POLIPASTO 1 1 TON", "POLIPASTO 2 1 TON", "POLIPASTO 2 TON", "EMPLAYADORA", "COLECTOR DE POLVOS"
        ]
    },
    "PTAR": {
        "CÁRCAMOS": [
            "CÁRCAMO 1", "BOMBA SUMERGIBLE CÁRCAMO 1", "BOMBA CENTRIFUGA CÁRCAMO 1",
            "CÁRCAMO 2", "BOMBA SUMERGIBLE CÁRCAMO 2", "BOMBA CENTRIFUGA CÁRCAMO 2"
        ],
        "PILAS DE AGUA": [
            "PILA 4", "PILA 5", "BOMBA DE LAVADOS PILA 4 Y 5", "PILA 9", "BOMBA SUMERGIBLE DE PILA 9", "PILA 8",
            "BOMBA SUMERGIBLE DE PILA 8", "BOMBA DE LODOS PILA 8", "SECCIÓN 1", "SECCIÓN 2", "BOMBA AGITADORA 1",
            "BOMBA AGITADORA 2", "SECCIÓN 3", "SECCIÓN 4", "BOMBA CENTRIFUGA SECCIÓN 4", "SECCIÓN 5",
            "BOMBA SUMERGIBLE SECCIÓN 5", "PILA DE AGUA LIMPIA", "BOMBA PILA DE AGUA LIMPIA", "LECHO DE SECADO 1",
            "LECHO DE SECADO 2", "LECHO DE SECADO 3"
        ],
        "PLANTA ALTA": [
            "FILTRO DE TORNILLO", "TAMIZ 1", "TAMIZ 2", "CLARIFICADOR",
            "MOTOR REDUCTOR DE CLARIFICADOR CUCHARA", "MOTOR REDUCTOR DE CLARIFICADOR BRAZO"
        ],
        "PLANTA BAJA": [
            "TANQUE DE DESECHO DE CLARIFICADOR", "MOTOR REDUCTOR AGITADOR TANQUE DE DESECHO CLARIFICADOR",
            "BOMBA TANQUE DE DESECHO DE CLARIFICADOR", "SERPENTÍN", "TANQUE DE POLÍMERO A", "MOTOR REDUCTOR AGITADOR TANQUE DE POLÍMERO A",
            "TANQUE DE POLÍMERO B", "MOTOR REDUCTOR AGITADOR TANQUE DE POLÍMERO B", "BOMBA TANQUES DE POLÍMERO",
            "TANQUE RETORNO CLARIFICADOR", "BOMBA TANQUE RETORNO ADT", "ADT", "BOMBA FILTRO DE PLACAS", "FILTROS DE PLACAS",
            "TANQUE ALIMENTACIÓN FILTROS DE ARENA", "BOMBA TANQUE ALIMENTACIÓN FILTRO DE ARENA", "FILTRO DE ARENA 1",
            "FILTRO DE ARENA 2", "FILTRO DE ARENA 3", "FILTRO DE ARENA 4", "COMPRESOR DE AIRE", "MOTOR COMPRESOR DE AIRE"
        ]
    }
};

// 1. INICIALIZAR LA LIBRERÍA SELECT2
$(document).ready(function() {
    $('.select2-busqueda').select2({
        tags: true, // ¡ESTO PERMITE ESCRIBIR TEXTO LIBRE SI NO EXISTE!
        width: '100%',
        dropdownParent: $('#modal-apertura'), // Asegura que no se oculte detrás del modal
        language: {
            noResults: function() {
                return "No encontrado. Escribe el nombre y presiona Enter.";
            }
        }
    });
});

// 2. Al cambiar la Zona -> Llenar las Áreas
/*
function actualizarAreas() {
    const zonaSel = document.getElementById('modal_zona').value;
    const $areaSelect = $('#modal_area');
    const $equipoSelect = $('#modal_equipo_especifico');

    // Limpiamos los desplegables
    $areaSelect.empty().append('<option value="">-- Selecciona o escribe un Área --</option>');
    $equipoSelect.empty().append('<option value="">-- Primero selecciona un Área --</option>');

    // Llenamos las áreas
    if (zonaSel && estructuraPlanta[zonaSel]) {
        const areas = Object.keys(estructuraPlanta[zonaSel]);
        areas.forEach(area => {
            const option = new Option(area, area, false, false);
            $areaSelect.append(option);
        });
    }
    
    // Le avisamos a la librería Select2 que los datos cambiaron
    $areaSelect.trigger('change');
    $equipoSelect.trigger('change');
}
*/

// 3. Al cambiar el Área -> Llenar los Equipos
/*
function actualizarEquipos() {
    const zonaSel = document.getElementById('modal_zona').value;
    const areaSel = document.getElementById('modal_area').value;
    const $equipoSelect = $('#modal_equipo_especifico');

    $equipoSelect.empty().append('<option value="">-- Selecciona o escribe un Equipo --</option>');

    // Llenamos los equipos correspondientes
    if (zonaSel && areaSel && estructuraPlanta[zonaSel] && estructuraPlanta[zonaSel][areaSel]) {
        const equipos = estructuraPlanta[zonaSel][areaSel];
        equipos.forEach(eq => {
            const option = new Option(eq, eq, false, false);
            $equipoSelect.append(option);
        });
    }

    // Le avisamos a la librería Select2 que los datos cambiaron
    $equipoSelect.trigger('change');
}
*/
</script>