<?php
session_start();

// 1. ZONA HORARIA FIJA DE MAZATLÁN
date_default_timezone_set('America/Mazatlan'); 

// ========================================================================
// CONEXIONES A LAS DOS BASES DE DATOS
// ========================================================================
include 'config/db.php'; 

$host_procesos = "localhost";
$user_procesos = "root"; 
$pass_procesos = "";     
$db_procesos   = "Progel_procesos";
$conn_procesos = mysqli_connect($host_procesos, $user_procesos, $pass_procesos, $db_procesos);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fecha = $_POST['fecha'];
    $hora = $_POST['hora'];
    $hora_int = intval(substr($hora, 0, 2));
    $turno_post = $_POST['turno'] ?? 'diurno'; 

    $fecha_real_captura = $fecha;
    if ($turno_post == 'nocturno' && $hora_int >= 0 && $hora_int <= 6) {
        $fecha_real_captura = date('Y-m-d', strtotime($fecha . ' +1 day'));
    }

    $hora_db = $hora . ":00";

    // ========================================================================
    // GUARDADO 1: BD NUEVA (SUP_captura_produccion)
    // ========================================================================
    $consumo = $_POST['consumo_cuero'] ?? null;
    $cocedores_man = $_POST['cocedores_manual'] ?? null;
    $caldo_pre = $_POST['caldo_pre_uf'] ?? null;
    $pre_conc = $_POST['pre_concentrado'] ?? null;
    $caldo_conc = $_POST['caldo_concentrado'] ?? null;
    $votators = $_POST['votators_activos'] ?? null;
    
    $v1 = $_POST['flujo_v1'] ?? null; $v2 = $_POST['flujo_v2'] ?? null;
    $v3 = $_POST['flujo_v3'] ?? null; $v4 = $_POST['flujo_v4'] ?? null;
    $v5 = $_POST['flujo_v5'] ?? null; $v6 = $_POST['flujo_v6'] ?? null;
    
    $hum1 = $_POST['hum_t1'] ?? null; $hum2 = $_POST['hum_t2'] ?? null;
    $hum3 = $_POST['hum_t3'] ?? null; $hum4 = $_POST['hum_t4'] ?? null;
    $hum5 = $_POST['hum_t5'] ?? null;
    
    $vel1 = $_POST['vel_t1'] ?? null; $vel2 = $_POST['vel_t2'] ?? null;
    $vel3 = $_POST['vel_t3'] ?? null; $vel4 = $_POST['vel_t4'] ?? null;
    
    $brix = $_POST['solidos_brix'] ?? null;
    $kg_t = $_POST['kg_teoricos'] ?? null; $kg_r = $_POST['kg_reales'] ?? null;
    $rech = $_POST['rechazo'] ?? null; $remo = $_POST['remoler'] ?? null;
    $efic = $_POST['eficiencia'] ?? null; $acc = $_POST['acciones'] ?? null;

    $sql1 = "INSERT INTO SUP_captura_produccion (
                fecha, hora, consumo_cuero, cocedores_manual, caldo_pre_uf, pre_concentrado, caldo_concentrado, votators_activos,
                flujo_v1, flujo_v2, flujo_v3, flujo_v4, flujo_v5, flujo_v6, 
                hum_t1, hum_t2, hum_t3, hum_t4, hum_t5, vel_t1, vel_t2, vel_t3, vel_t4, solidos_brix,
                kg_teoricos, kg_reales, rechazo, remoler, eficiencia, acciones, fecha_registro_real
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
                consumo_cuero=VALUES(consumo_cuero), cocedores_manual=VALUES(cocedores_manual), caldo_pre_uf=VALUES(caldo_pre_uf), 
                pre_concentrado=VALUES(pre_concentrado), caldo_concentrado=VALUES(caldo_concentrado), votators_activos=VALUES(votators_activos),
                flujo_v1=VALUES(flujo_v1), flujo_v2=VALUES(flujo_v2), flujo_v3=VALUES(flujo_v3), flujo_v4=VALUES(flujo_v4), flujo_v5=VALUES(flujo_v5), flujo_v6=VALUES(flujo_v6), 
                hum_t1=VALUES(hum_t1), hum_t2=VALUES(hum_t2), hum_t3=VALUES(hum_t3), hum_t4=VALUES(hum_t4), hum_t5=VALUES(hum_t5), 
                vel_t1=VALUES(vel_t1), vel_t2=VALUES(vel_t2), vel_t3=VALUES(vel_t3), vel_t4=VALUES(vel_t4), solidos_brix=VALUES(solidos_brix),
                kg_teoricos=VALUES(kg_teoricos), kg_reales=VALUES(kg_reales), rechazo=VALUES(rechazo), remoler=VALUES(remoler), eficiencia=VALUES(eficiencia), acciones=VALUES(acciones),
                fecha_registro_real=NOW()";

    $stmt1 = mysqli_prepare($conn, $sql1);
    $success = false;

    if ($stmt1) {
        $types = str_repeat('s', 30);
        mysqli_stmt_bind_param($stmt1, $types, $fecha_real_captura, $hora_db, $consumo, $cocedores_man, $caldo_pre, $pre_conc, $caldo_conc, $votators, $v1, $v2, $v3, $v4, $v5, $v6, $hum1, $hum2, $hum3, $hum4, $hum5, $vel1, $vel2, $vel3, $vel4, $brix, $kg_t, $kg_r, $rech, $remo, $efic, $acc);
        if (mysqli_stmt_execute($stmt1)) {
            $success = true;
        }
        mysqli_stmt_close($stmt1);
    }
    
    // ========================================================================
    // GUARDADO 2: BD ANTIGUA MATRIZ DE SECADORES (1 AL 4)
    // ========================================================================
    if ($conn_procesos && $success) {
        $operador = $_SESSION['nomina'] ?? 'Desconocido';
        $es_retardo = 0; 

        $sql2 = "INSERT INTO verificacion_secado 
                 (fecha, hora, secador, estado_fo, altura_galleta, flujo, hz, hum_penultima, hum_ultima, rc9, hum_relativa_cam5, textura, operador, es_retardo, creado_en) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE 
                 estado_fo=VALUES(estado_fo), altura_galleta=VALUES(altura_galleta), flujo=VALUES(flujo), hz=VALUES(hz), 
                 hum_penultima=VALUES(hum_penultima), hum_ultima=VALUES(hum_ultima), rc9=VALUES(rc9), 
                 hum_relativa_cam5=VALUES(hum_relativa_cam5), textura=VALUES(textura), operador=VALUES(operador)";

        $stmt2 = mysqli_prepare($conn_procesos, $sql2);
        
        if ($stmt2) {
            for ($i = 1; $i <= 4; $i++) {
                $estado_fo_secado = isset($_POST["estado_fo_$i"]) ? 1 : 0;

                // MAGIA: El truco del -1 para que la BD vieja acepte el F.O.
                $flujo_secado   = ($estado_fo_secado) ? null : ((isset($_POST["flujo_secado_$i"]) && $_POST["flujo_secado_$i"] !== '') ? $_POST["flujo_secado_$i"] : null);
                if ($flujo_secado === 'F.O.') $flujo_secado = -1;

                $hz_secado      = ($estado_fo_secado) ? null : ((isset($_POST["hz_secado_$i"]) && $_POST["hz_secado_$i"] !== '') ? $_POST["hz_secado_$i"] : null);
                if ($hz_secado === 'F.O.') $hz_secado = -1;

                $rc9            = ($estado_fo_secado) ? null : ((isset($_POST["rc9_$i"]) && $_POST["rc9_$i"] !== '') ? $_POST["rc9_$i"] : null);
                if ($rc9 === 'F.O.') $rc9 = -1;

                $hum_penultima  = ($estado_fo_secado) ? null : ((isset($_POST["hum_penultima_$i"]) && $_POST["hum_penultima_$i"] !== '') ? $_POST["hum_penultima_$i"] : null);
                if ($hum_penultima === 'F.O.') $hum_penultima = -1;

                $hum_ultima     = ($estado_fo_secado) ? null : ((isset($_POST["hum_ultima_$i"]) && $_POST["hum_ultima_$i"] !== '') ? $_POST["hum_ultima_$i"] : null);
                if ($hum_ultima === 'F.O.') $hum_ultima = -1;

                $altura_galleta = ($estado_fo_secado) ? null : ((isset($_POST["altura_galleta_$i"]) && $_POST["altura_galleta_$i"] !== '') ? $_POST["altura_galleta_$i"] : null);
                if ($altura_galleta === 'F.O.') $altura_galleta = -1;

                $hum_rel_cam5   = ($estado_fo_secado) ? null : ((isset($_POST["hum_relativa_cam5_$i"]) && $_POST["hum_relativa_cam5_$i"] !== '') ? $_POST["hum_relativa_cam5_$i"] : null);
                if ($hum_rel_cam5 === 'F.O.') $hum_rel_cam5 = -1;

                $textura_secado = ($estado_fo_secado) ? 'F.O.' : ((isset($_POST["textura_secado_$i"]) && $_POST["textura_secado_$i"] !== '') ? $_POST["textura_secado_$i"] : null);

                // Guardamos para cada uno de los 4 secadores
                mysqli_stmt_bind_param($stmt2, "ssiidddddddssi", 
                    $fecha_real_captura, $hora_db, $i, $estado_fo_secado, 
                    $altura_galleta, $flujo_secado, $hz_secado, $hum_penultima, $hum_ultima, $rc9, 
                    $hum_rel_cam5, $textura_secado, $operador, $es_retardo
                );
                mysqli_stmt_execute($stmt2);
            }
            mysqli_stmt_close($stmt2);
        }
    }

    mysqli_close($conn);
    if ($conn_procesos) {
        mysqli_close($conn_procesos);
    }

    if ($success) {
        header("Location: reporte_maestro.php?fecha=" . urlencode($_POST['fecha_original']) . "&turno=" . urlencode($turno_post) . "&status=success&hora_op=" . urlencode($hora));
    } else {
        header("Location: reporte_maestro.php?fecha=" . urlencode($_POST['fecha_original']) . "&turno=" . urlencode($turno_post) . "&status=error&hora_op=" . urlencode($hora));
    }
    exit();
}
?>