<?php
// Archivo: includes/modales.php
?>
<div id="modal-apertura" class="modal-fondo">
    <div class="modal-contenido">
        <h3 class="modal-titulo">Abrir Nuevo Incidente</h3>
        <div class="alerta-tiempo">Tiempo iniciado:<br><strong id="reloj-apertura"></strong></div>
        <form method="POST" action="" enctype="multipart/form-data">
            <label>Tipo de Alerta:</label>
            <select name="tipo_codigo" required style="font-weight: bold; background-color: #fff9e6;">
                <option value="Rojo">Código Rojo (Urgente / 60 min)</option>
                <option value="Naranja">Código Naranja (Informativo / Sin límite)</option>
                <option value="Amarillo">Código Amarillo (BMP / Fugas)</option>
            </select>

            <div class="campo-formulario" style="margin-bottom: 15px;">
                <label style="font-weight: bold; color: #2c3e50;">Zona:</label>
                <select name="zona" id="modal_zona" class="form-control" required>
                    <option value="">-- Selecciona una Zona --</option>
                    <option value="Zona negra">Zona Negra</option>
                    <option value="Zona gris">Zona Gris</option>
                    <option value="Zona blanca">Zona Blanca</option>
                    <option value="PTAR">PTAR</option>
                </select>
            </div>

            <div class="campo-formulario" style="margin-bottom: 15px;">
                <label style="font-weight: bold; color: #2c3e50;">Área:</label>
                <select name="equipo" id="modal_area" class="form-control select2-busqueda" required>
                    <option value="">-- Escribe o selecciona un Área --</option>
                </select>
            </div>

            <div class="campo-formulario" style="margin-bottom: 15px;">
                <label style="font-weight: bold; color: #2c3e50;">Equipo Afectado:</label>
                <select name="equipo_nombre" id="modal_equipo_especifico" class="form-control select2-busqueda" required>
                    <option value="">-- Escribe o selecciona un Equipo --</option>
                </select>
            </div>

            <label>Descripción del Incidente:</label>
            <textarea name="incidente" rows="4" required placeholder="Describe cuál es la falla o el problema en el equipo..."></textarea>

            <label>Número de Nómina (Quien reporta):</label>
            <input type="number" name="numero_nomina" required placeholder="Ej. 3767" min="1">

            <div class="campo-formulario" style="margin-top: 15px; margin-bottom: 15px; padding: 10px; background-color: #f8f9fa; border-radius: 5px; border: 1px dashed #bdc3c7;">
                <label style="font-weight: bold; color: #2c3e50;">📸 Foto de la falla (Opcional):</label>
                <p style="font-size: 12px; color: #7f8c8d; margin-bottom: 5px;">Si tienes la foto súbela ahora, así ya no será obligatoria al cerrar el reporte.</p>
                <input type="file" name="evidencia_apertura" accept="image/*" class="form-control">
            </div>

            <button type="submit" name="agregar" class="btn-guardar">Registrar Incidente</button>
            <button type="button" class="btn-cancelar" onclick="ocultarFormularioApertura()">Cancelar</button>
        </form>
    </div>
</div>

<div id="modal-cierre" class="modal-fondo">
    <div class="modal-contenido">
        <h3 class="modal-titulo">Resolver Reporte #<span id="modal-folio"></span></h3>
        <p style="font-size: 14px; color: #555;">Documenta la acción que tomaste para solucionar el problema físico. Esto detendrá el reloj.</p>
        <p style="font-size: 13px; color: #e74c3c; font-weight: bold; background: #fdf0ed; padding: 8px; border-radius: 5px;">
            ⚠️ IMPORTANTE: Si es Código Rojo debe llevar evidencia. Si es Código Naranja, es obligatorio subir el documento de Causa Raíz (PDF/Word) dentro de un plazo máximo de 3 días para dar el folio por terminado.
        </p>
        <form method="POST" action="subir_evidencia.php" enctype="multipart/form-data">
            <input type="hidden" name="id_registro" id="modal-id-input">

            <div class="campo-formulario" style="margin-bottom: 15px;">
                <label style="font-weight: bold; color: #2c3e50;">Parte Específica Afectada:</label>
                <input type="text" name="parte_especifica" placeholder="Ej: Balero, Banda, Motor, Sensor..." style="width: 100%; padding: 10px; border-radius: 5px; border: 1px solid #ccc;" required>
            </div>

            <label>¿Qué se hizo?</label>
            <textarea name="que_se_hizo" rows="2" required placeholder="Acciones inmediatas tomadas..."></textarea>

            <label>Solución Definitiva:</label>
            <textarea name="solucion_definitiva" rows="2" required placeholder="Solución para que no vuelva a ocurrir..."></textarea>

            <div style="display: flex; gap: 10px;">
                <div style="flex: 1;">
                    <label>Fecha Compromiso:</label>
                    <input type="date" name="fecha_compromiso" required>
                </div>
                <div style="flex: 1;">
                    <label>Responsable:</label>
                    <input type="text" name="responsable" required placeholder="Nombre de responsable"> 
                </div>
            </div>

            <label>Evidencia Fotográfica (Solo imagen):</label>
            <input type="file" name="evidencia" accept="image/*">

            <label>Causa Raíz (Solo PDF o Word):</label>
            <input type="file" name="causa_raiz" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document">

            <button type="submit" class="btn-guardar" style="margin-top: 15px;">Guardar y Cerrar</button>
            <button type="button" class="btn-cancelar" onclick="ocultarModalCierre()">Cancelar</button>
        </form>
    </div>
</div>

<div id="modal-preventivos" class="modal-fondo">
    <div class="modal-contenido">
        <h3 class="modal-titulo" style="color: #8e44ad;">Preventivos Futuros #<span id="modal-prev-folio"></span></h3>
        <p style="font-size: 13px; color: #555;">Esta actualización no enviará notificaciones por Teams/WhatsApp.</p>
        <form method="POST" action="guardar_preventivos.php">
            <input type="hidden" name="id_registro" id="modal-prev-id">
            <label>Acciones Preventivas:</label>
            <textarea name="preventivos_futuros" id="modal-prev-texto" rows="4" required placeholder="Escribe las acciones preventivas que se tomarán..."></textarea>
            <button type="submit" class="btn-guardar" style="margin-top: 15px; background-color: #8e44ad;">Guardar Preventivos</button>
            <button type="button" class="btn-cancelar" onclick="ocultarModalPreventivos()">Cancelar</button>
        </form>
    </div>
</div>

<div id="modal-subir-foto" class="modal-fondo">
    <div class="modal-contenido">
        <h3 class="modal-titulo">Subir Evidencia Fotográfica #<span id="modal-foto-folio"></span></h3>
        <form method="POST" action="guardar_foto_pendiente.php" enctype="multipart/form-data">
            <input type="hidden" name="id_registro" id="modal-foto-id">
            <input type="hidden" name="tipo_archivo" value="evidencia">
            <input type="file" name="archivo_subir" accept="image/*" required>
            <button type="submit" class="btn-guardar" style="margin-top: 15px;">Subir Foto</button>
            <button type="button" class="btn-cancelar" onclick="ocultarModalSubirFoto()">Cancelar</button>
        </form>
    </div>
</div>

<div id="modal-subir-doc" class="modal-fondo">
    <div class="modal-contenido">
        <h3 class="modal-titulo">Subir Documento Causa Raíz #<span id="modal-doc-folio"></span></h3>
        <form method="POST" action="guardar_foto_pendiente.php" enctype="multipart/form-data">
            <input type="hidden" name="id_registro" id="modal-doc-id">
            <input type="hidden" name="tipo_archivo" value="causa">
            <input type="file" name="archivo_subir" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" required>
            <button type="submit" class="btn-guardar" style="margin-top: 15px;">Subir Documento</button>
            <button type="button" class="btn-cancelar" onclick="ocultarModalSubirDoc()">Cancelar</button>
        </form>
    </div>
</div>

<div id="modal-detalles" class="modal-fondo">
    <div class="modal-contenido" style="max-width: 500px;">
        <h3 class="modal-titulo">Detalles del Folio #<span id="det-folio"></span> <span id="det-icono" style="float:right;"></span></h3>

        <div class="dato-label">Ubicación</div>
        <div class="dato-bloque" id="det-ubicacion"></div>

        <div style="display: flex; gap: 10px;">
            <div style="flex: 1;">
                <div class="dato-label">Apertura</div>
                <div class="dato-bloque" id="det-apertura"></div>
            </div>
            <div style="flex: 1;">
                <div class="dato-label">Cierre</div>
                <div class="dato-bloque" id="det-cierre"></div>
            </div>
        </div>

        <div class="dato-label">Problema Reportado</div>
        <div class="dato-bloque" id="det-incidente" style="background-color: #fff5f5; border-color: #ffe3e3;"></div>

        <div class="dato-label">¿Qué se hizo?</div>
        <div class="dato-bloque" id="det-que-se-hizo" style="background-color: #f8f9fa;"></div>

        <div class="dato-label">Solución Definitiva</div>
        <div class="dato-bloque" id="det-solucion-definitiva" style="background-color: #f0fdf4; border-color: #dcfce7;"></div>
        
        <div class="dato-label">Preventivos Futuros</div>
        <div class="dato-bloque" id="det-preventivos" style="background-color: #f5f3ff; border-color: #ede9fe;"></div>

        <div style="display: flex; gap: 10px;">
            <div style="flex: 1;">
                <div class="dato-label">Fecha Compromiso</div>
                <div class="dato-bloque" id="det-fecha-compromiso" style="font-weight: bold; color: #d35400;"></div>
            </div>
            <div style="flex: 1;">
                <div class="dato-label">Responsable</div>
                <div class="dato-bloque" id="det-responsable" style="font-weight: bold; color: #2980b9;"></div>
            </div>
        </div>

        <div class="dato-label">Reportado por (Nómina)</div>
        <div class="dato-bloque" id="det-numero-nomina" style="font-weight: bold; color: #34495e;"></div>

        <button type="button" class="btn-principal" style="width: 100%; margin-bottom: 0;" onclick="ocultarModalDetalles()">Cerrar Detalles</button>
    </div>
</div>

<div id="modal-evidencia" class="modal-fondo" onclick="ocultarModalEvidencia()">
    <div class="modal-contenido">
        <img id="img-evidencia" src="" style="max-width: 100%; max-height: 85vh; display: block; margin: auto; border-radius: 10px;">
        <button type="button" class="btn-cancelar" style="width: auto; padding: 10px 20px; margin: 15px auto; display: block;" onclick="ocultarModalEvidencia()">Cerrar</button>
    </div>
</div>

<div id="modal-galeria" class="modal-fondo">
    <div class="modal-contenido" style="max-width: 400px; text-align: center;">
        <h3 class="modal-titulo">Archivos Adjuntos</h3>
        <p style="font-size: 13px; color: #6c757d; margin-bottom: 20px;">Selecciona el archivo que deseas visualizar o descargar.</p>

        <div id="galeria-links"></div>

        <button type="button" class="btn-cancelar" style="margin-top: 20px;" onclick="ocultarModalGaleria()">Cerrar</button>
    </div>
</div>

<div id="modal-visor-doble" class="modal-fondo">
    <div class="modal-contenido" style="max-width: 90%; text-align: center;">
        <h3 class="modal-titulo">Comparativa de Evidencias</h3>
        <div style="display: flex; justify-content: space-around; align-items: flex-start; gap: 20px; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 300px;">
                <h4 style="color: #f39c12;">Foto de la Falla (Antes)</h4>
                <img id="img-visor-falla" src="" style="max-width: 100%; border-radius: 8px; border: 2px solid #f39c12; min-height: 150px; background-color: #f9f9f9;">
            </div>
            <div style="flex: 1; min-width: 300px;">
                <h4 style="color: #2ecc71;">Foto de la Solución (Después)</h4>
                <img id="img-visor-solucion" src="" style="max-width: 100%; border-radius: 8px; border: 2px solid #2ecc71; min-height: 150px; background-color: #f9f9f9;">
            </div>
        </div>
        <button type="button" class="btn-cancelar" style="margin-top: 20px;" onclick="document.getElementById('modal-visor-doble').style.display = 'none';">Cerrar</button>
    </div>
</div>