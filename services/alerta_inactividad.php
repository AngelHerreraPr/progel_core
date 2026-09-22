<?php
// Progel_core/services/alerta_inactividad.php
date_default_timezone_set('America/Mazatlan');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mailer.php';

// =========================================================================
// DESTINATARIOS DE LA ALERTA (REPORTE MAESTRO)
// =========================================================================
$correos_destinatarios_default = [
    'angel.herrera@progel.com.mx', // Cambia por tu correo
    // 'supervisor@progel.com.mx',
    // 'jefatura@progel.com.mx'
];

/**
 * Función principal que verifica el último registro del Reporte Maestro
 * y envía correo cada 2 horas si no hay registros.
 */
function verificarYNotificarInactividadMaestro($conn, $forzar_prueba = false, $destinatarios = null) {
    global $correos_destinatarios_default;
    $destinatarios = $destinatarios ?? $correos_destinatarios_default;

    // 1. Obtener el ÚLTIMO registro guardado en el REPORTE MAESTRO (SUP_captura_produccion)
    $sql = "SELECT id, fecha, hora, fecha_registro_real 
            FROM SUP_captura_produccion 
            ORDER BY fecha DESC, hora DESC 
            LIMIT 1";

    $res = mysqli_query($conn, $sql);
    $ultimo = ($res && mysqli_num_rows($res) > 0) ? mysqli_fetch_assoc($res) : null;

    if (!$ultimo) {
        return [
            'estado' => 'sin_datos',
            'mensaje' => 'No se encontraron registros previos en SUP_captura_produccion.',
            'horas_inactividad' => 0,
            'ultimo' => null,
            'correo_enviado' => false
        ];
    }

    $fecha_ult = $ultimo['fecha'];
    $hora_ult = $ultimo['hora'];
    $hora_ult_corta = substr($hora_ult, 0, 5);

    // Determinar la fecha/hora del último registro
    if (!empty($ultimo['fecha_registro_real'])) {
        $fecha_ultimo_timestamp = strtotime($ultimo['fecha_registro_real']);
    } else {
        $fecha_ultimo_timestamp = strtotime($fecha_ult . ' ' . $hora_ult);
    }

    $ahora_timestamp = time();
    $segundos_diferencia = max(0, $ahora_timestamp - $fecha_ultimo_timestamp);
    $horas_inactividad = round($segundos_diferencia / 3600, 1);

    // =====================================================================
    // 2. BUSCAR EL OPERADOR QUE REALIZÓ EL REGISTRO
    // =====================================================================
    $operador_texto = "No especificado";
    $conn_procesos = @mysqli_connect("localhost", "root", "", "Progel_procesos");
    $nomina_encontrada = null;

    if ($conn_procesos) {
        $q_op = "SELECT operador FROM verificacion_secado WHERE fecha = '$fecha_ult' AND hora = '$hora_ult' LIMIT 1";
        $res_op = @mysqli_query($conn_procesos, $q_op);
        if ($res_op && mysqli_num_rows($res_op) > 0) {
            $r_op = mysqli_fetch_assoc($res_op);
            if (!empty($r_op['operador']) && $r_op['operador'] !== 'Desconocido') {
                $nomina_encontrada = $r_op['operador'];
            }
        }
    }

    // Si no está en verificacion_secado, buscar en bitacora_lecturas en esa fecha y hora
    if (!$nomina_encontrada) {
        $hora_entera = (int)substr($hora_ult, 0, 2);
        $q_bit = "SELECT numero_nomina FROM bitacora_lecturas 
                  WHERE DATE(fecha_registro) = '$fecha_ult' AND HOUR(fecha_registro) = $hora_entera 
                  ORDER BY id DESC LIMIT 1";
        $res_bit = mysqli_query($conn, $q_bit);
        if ($res_bit && mysqli_num_rows($res_bit) > 0) {
            $r_bit = mysqli_fetch_assoc($res_bit);
            $nomina_encontrada = $r_bit['numero_nomina'];
        }
    }

    if ($nomina_encontrada) {
        $q_u = "SELECT nombre, rol FROM usuarios WHERE nomina = '$nomina_encontrada' LIMIT 1";
        $res_u = mysqli_query($conn, $q_u);
        if ($res_u && mysqli_num_rows($res_u) > 0) {
            $u = mysqli_fetch_assoc($res_u);
            $operador_texto = htmlspecialchars($u['nombre']) . " (Nómina: " . htmlspecialchars($nomina_encontrada) . ")";
        } else {
            $operador_texto = "Nómina: " . htmlspecialchars($nomina_encontrada);
        }
    }

    // =====================================================================
    // 3. IDENTIFICAR LAS HORAS QUE NO SE HICIERON ESE DÍA
    // =====================================================================
    $hora_num_ult = (int)substr($hora_ult, 0, 2);
    $es_diurno = ($hora_num_ult >= 7 && $hora_num_ult < 19);
    $turno_nombre = $es_diurno ? 'Diurno (07:00 a 18:00)' : 'Nocturno (19:00 a 06:00)';

    $horas_esperadas = $es_diurno 
        ? ["07:00", "08:00", "09:00", "10:00", "11:00", "12:00", "13:00", "14:00", "15:00", "16:00", "17:00", "18:00"]
        : ["19:00", "20:00", "21:00", "22:00", "23:00", "00:00", "01:00", "02:00", "03:00", "04:00", "05:00", "06:00"];

    // Horas que efectivamente están guardadas en SUP_captura_produccion para ese día
    $q_horas_reg = "SELECT hora FROM SUP_captura_produccion WHERE fecha = '$fecha_ult'";
    $res_horas_reg = mysqli_query($conn, $q_horas_reg);
    $horas_hechas = [];
    if ($res_horas_reg) {
        while ($rh = mysqli_fetch_assoc($res_horas_reg)) {
            $horas_hechas[] = substr($rh['hora'], 0, 5);
        }
    }

    // Si el día evaluado es HOY, solo consideramos horas esperadas que ya hayan transcurrido
    $fecha_hoy = date('Y-m-d');
    $hora_actual_num = (int)date('G');
    if ($fecha_ult == $fecha_hoy) {
        $horas_esperadas = array_filter($horas_esperadas, function($h) use ($hora_actual_num) {
            return (int)substr($h, 0, 2) <= $hora_actual_num;
        });
    }

    $horas_no_hechas = array_values(array_diff($horas_esperadas, $horas_hechas));

    // =====================================================================
    // 4. CONTROL DE ALERTA CADA 2 HORAS Y ANTISPAM
    // =====================================================================
    $umbral_horas = 2.0; // UMBRAL SOLICITADO: CADA 2 HORAS
    $intervalo_antispam = 2 * 3600; // 2 HORAS ENTRE CORREOS

    $archivo_control = __DIR__ . '/ultimo_envio_maestro.txt';
    $ultimo_envio_timestamp = file_exists($archivo_control) ? (int)file_get_contents($archivo_control) : 0;
    $segundos_desde_ultimo_envio = $ahora_timestamp - $ultimo_envio_timestamp;

    $debe_enviar = ($horas_inactividad >= $umbral_horas || $forzar_prueba);
    $puede_enviar = ($segundos_desde_ultimo_envio >= $intervalo_antispam || $forzar_prueba);
    $correo_enviado = false;
    $mensaje = "";

    // Formatear día en español
    $dias_esp = ['Sunday'=>'Domingo','Monday'=>'Lunes','Tuesday'=>'Martes','Wednesday'=>'Miércoles','Thursday'=>'Jueves','Friday'=>'Viernes','Saturday'=>'Sábado'];
    $meses_esp = ['January'=>'Enero','February'=>'Febrero','March'=>'Marzo','April'=>'Abril','May'=>'Mayo','June'=>'Junio','July'=>'Julio','August'=>'Agosto','September'=>'Septiembre','October'=>'Octubre','November'=>'Noviembre','December'=>'Diciembre'];
    $dia_semana_ing = date('l', strtotime($fecha_ult));
    $mes_ing = date('F', strtotime($fecha_ult));
    $dia_texto = ($dias_esp[$dia_semana_ing] ?? $dia_semana_ing) . " " . date('d', strtotime($fecha_ult)) . " de " . ($meses_esp[$mes_ing] ?? $mes_ing) . " de " . date('Y', strtotime($fecha_ult));

    $hora_guardado_real = !empty($ultimo['fecha_registro_real']) 
        ? date('h:i:s A', strtotime($ultimo['fecha_registro_real'])) 
        : "$hora_ult_corta hrs";

    if ($debe_enviar) {
        if ($puede_enviar) {
            $asunto = ($forzar_prueba ? "[PRUEBA] " : "⚠️ ALERTA: ") . "$horas_inactividad hrs sin registro en Reporte Maestro ({$dia_texto})";

            // Armar insignias de horas hechas y no hechas
            $html_horas_hechas = "";
            if (!empty($horas_hechas)) {
                foreach ($horas_hechas as $hh) {
                    $html_horas_hechas .= "<span style='display:inline-block; background:#dcfce7; color:#166534; padding:3px 8px; border-radius:4px; font-weight:bold; font-size:12px; margin:2px;'>$hh ✓</span> ";
                }
            } else {
                $html_horas_hechas = "<span style='color:#64748b;'>Ninguna hora registrada en este turno.</span>";
            }

            $html_horas_no_hechas = "";
            if (!empty($horas_no_hechas)) {
                foreach ($horas_no_hechas as $hnh) {
                    $html_horas_no_hechas .= "<span style='display:inline-block; background:#fee2e2; color:#991b1b; padding:3px 8px; border-radius:4px; font-weight:bold; font-size:12px; margin:2px;'>$hnh ✗</span> ";
                }
            } else {
                $html_horas_no_hechas = "<span style='color:#16a34a; font-weight:bold;'>Todas las horas del turno están al corriente.</span>";
            }

            $cuerpoHTML = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <style>
                    body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #f1f5f9; margin: 0; padding: 20px; color: #0f172a; }
                    .contenedor { max-width: 650px; background: #ffffff; margin: 0 auto; border-radius: 12px; overflow: hidden; border: 1px solid #cbd5e1; box-shadow: 0 6px 18px rgba(0,0,0,0.06); }
                    .header { background: #b91c1c; padding: 20px; text-align: center; color: #ffffff; }
                    .header h1 { margin: 0; font-size: 20px; font-weight: 800; letter-spacing: 0.5px; }
                    .contenido { padding: 25px; }
                    .alerta-box { background: #fff1f2; border-left: 5px solid #dc2626; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
                    .alerta-box p { margin: 0; font-size: 14px; color: #991b1b; line-height: 1.5; }
                    .badge-tiempo { background: #dc2626; color: white; padding: 3px 9px; border-radius: 12px; font-size: 13px; font-weight: bold; }
                    .seccion-titulo { font-size: 15px; font-weight: 800; color: #1e293b; margin-top: 20px; margin-bottom: 8px; border-bottom: 2px solid #e2e8f0; padding-bottom: 4px; }
                    .tabla-datos { width: 100%; border-collapse: collapse; margin-top: 8px; margin-bottom: 15px; }
                    .tabla-datos th, .tabla-datos td { padding: 8px 12px; border-bottom: 1px solid #edf2f7; text-align: left; font-size: 13px; }
                    .tabla-datos th { background: #f8fafc; color: #475569; font-weight: 600; width: 40%; }
                    .tabla-datos td { color: #0f172a; font-weight: 700; }
                    .caja-horas { background: #fafafa; border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px; margin-bottom: 15px; }
                    .footer { background: #f8fafc; padding: 15px; text-align: center; font-size: 12px; color: #64748b; border-top: 1px solid #e2e8f0; }
                </style>
            </head>
            <body>
                <div class='contenedor'>
                    <div class='header'>
                        <h1>⚠️ ALERTA DE INACTIVIDAD DE REGISTRO</h1>
                        <p style='margin: 5px 0 0 0; font-size: 13px; opacity: 0.9;'>Reporte Maestro de Producción &bull; PROGEL Core</p>
                    </div>
                    <div class='contenido'>
                        <div class='alerta-box'>
                            <p><strong>Atención Supervisión de Planta:</strong></p>
                            <p>Han transcurrido <span class='badge-tiempo'>$horas_inactividad horas</span> consecutivas sin que se capture ninguna hora en el Reporte Maestro.</p>
                        </div>

                        <div class='seccion-titulo'>📅 Información de la Última Captura Realizada</div>
                        <table class='tabla-datos'>
                            <tr>
                                <th>Día del Registro:</th>
                                <td>$dia_texto</td>
                            </tr>
                            <tr>
                                <th>Turno Operativo:</th>
                                <td>$turno_nombre</td>
                            </tr>
                            <tr>
                                <th>Hora Nominal Registrada:</th>
                                <td>$hora_ult_corta hrs</td>
                            </tr>
                            <tr>
                                <th>Hora Exacta Real de Guardado:</th>
                                <td>$hora_guardado_real</td>
                            </tr>
                            <tr>
                                <th>Operador que lo realizó:</th>
                                <td><span style='color: #2563eb;'>$operador_texto</span></td>
                            </tr>
                        </table>

                        <div class='seccion-titulo'>⏰ Estado de las Horas del Día ({$fecha_ult})</div>
                        <div class='caja-horas'>
                            <p style='margin: 0 0 6px 0; font-size: 13px;'><strong>✅ Horas que SÍ se capturaron:</strong></p>
                            <div>$html_horas_hechas</div>
                            
                            <hr style='border: none; border-top: 1px solid #e5e7eb; margin: 10px 0;'>
                            
                            <p style='margin: 0 0 6px 0; font-size: 13px; color: #991b1b;'><strong>❌ Horas que NO se hicieron (faltantes):</strong></p>
                            <div>$html_horas_no_hechas</div>
                        </div>

                        <p style='margin-top: 20px; font-size: 13px; color: #64748b; line-height: 1.4;'>
                            * Se envía este aviso preventivo cada 2 horas mientras la captura permanezca inactiva. Favor de dar seguimiento inmediato con el turno.
                        </p>
                    </div>
                    <div class='footer'>
                        Notificación automática del <strong>Reporte Maestro</strong> &bull; Sistema PROGEL Core
                    </div>
                </div>
            </body>
            </html>
            ";

            echo "Enviando correo a: " . implode(', ', $destinatarios) . "...\n";
            $enviado = enviarCorreoAlerta($destinatarios, $asunto, $cuerpoHTML);

            if ($enviado) {
                file_put_contents($archivo_control, $ahora_timestamp);
                $correo_enviado = true;
                $mensaje = "Alerta de 2 horas enviada por correo ({$horas_inactividad} hrs sin capturas).";
            } else {
                $mensaje = "Error al intentar enviar el correo. Revisa SMTP en config/mailer.php.";
            }
        } else {
            $minutos_restantes = round(($intervalo_antispam - $segundos_desde_ultimo_envio) / 60);
            $mensaje = "Alerta activa ({$horas_inactividad} hrs), pero ya se envió un aviso recientemente. Próximo aviso disponible en {$minutos_restantes} min.";
        }
    } else {
        $mensaje = "Reporte Maestro al corriente. Último registro hace {$horas_inactividad} horas (umbral de alerta: 2.0 hrs).";
    }

    return [
        'estado' => $debe_enviar ? 'retraso' : 'al_corriente',
        'horas_inactividad' => $horas_inactividad,
        'fecha_ultimo_timestamp' => $fecha_ultimo_timestamp,
        'ultimo' => $ultimo,
        'dia_texto' => $dia_texto,
        'hora_guardado_real' => $hora_guardado_real,
        'operador_texto' => $operador_texto,
        'horas_hechas' => $horas_hechas,
        'horas_no_hechas' => $horas_no_hechas,
        'correo_enviado' => $correo_enviado,
        'mensaje' => $mensaje
    ];
}

// Si se ejecuta directamente (navegador directo o CLI / Task Scheduler)
if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $es_prueba = (isset($_GET['test']) && $_GET['test'] == '1') || (isset($argv) && in_array('--test', $argv));
    $resultado = verificarYNotificarInactividadMaestro($conn, $es_prueba);
    echo '[' . date('Y-m-d H:i:s') . '] ' . $resultado['mensaje'] . PHP_EOL;
}
