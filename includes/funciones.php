<?php
// Funciones compartidas para semáforos y utilidades

// 1. Calcula si el valor cayó en Verde, Amarillo o Rojo
function calcular_semaforo($valor, $param) {
    if (!is_numeric($valor)) return 'N'; 

    $v = floatval($valor);
    $minA = isset($param['amarillo_min']) && $param['amarillo_min'] !== '' ? floatval($param['amarillo_min']) : null;
    $minV = isset($param['verde_min']) && $param['verde_min'] !== '' ? floatval($param['verde_min']) : null;
    $maxV = isset($param['verde_max']) && $param['verde_max'] !== '' ? floatval($param['verde_max']) : null;
    $maxA = isset($param['amarillo_max']) && $param['amarillo_max'] !== '' ? floatval($param['amarillo_max']) : null;

    if ($minV === null || $maxV === null) return 'N'; 

    if ($v >= $minV && $v <= $maxV) return 'V'; 
    if (($minA !== null && $v >= $minA && $v < $minV) || ($maxA !== null && $v > $maxV && $v <= $maxA)) return 'A'; 
    return 'R'; 
}

// 2. Devuelve la clase CSS (Por si la usas en otro lado)
function obtener_clase_semaforo($valor, $param) {
    $nivel = calcular_semaforo($valor, $param);
    if ($nivel == 'V') return 'semaforo-v';
    if ($nivel == 'A') return 'semaforo-a';
    if ($nivel == 'R') return 'semaforo-r';
    return ''; 
}

// 3. Dibuja el recuadro negro con los 3 focos LED
function render_badge_valor($valor, $param) {
    $nivel = calcular_semaforo($valor, $param);
    
    // Si no aplica semáforo, solo mostramos un guion
    if ($nivel == 'N') {
        return "<span class='badge bg-secondary text-white px-3'>-</span>";
    }

    // Colores base (Apagados / Opacos)
    $c_verde = "background-color: #00cc00; opacity: 0.15;";
    $c_amari = "background-color: #ffcc00; opacity: 0.15;";
    $c_rojo  = "background-color: #ff0000; opacity: 0.15;";

    // Encendemos la luz correspondiente dándole brillo y sombra
    if ($nivel == 'V') $c_verde = "background-color: #00ff00; box-shadow: 0 0 8px #00ff00; opacity: 1;";
    if ($nivel == 'A') $c_amari = "background-color: #ffcc00; box-shadow: 0 0 8px #ffcc00; opacity: 1;";
    if ($nivel == 'R') $c_rojo  = "background-color: #ff0000; box-shadow: 0 0 8px #ff0000; opacity: 1;";

    // Dibujamos el cuadrito negro con los 3 focos adentro
    return "
    <div style='background-color: #1a1a1a; padding: 4px 6px; border-radius: 6px; display: inline-flex; gap: 5px; border: 1px solid #444; align-items-center;' title='Estado: $nivel'>
        <div style='width: 14px; height: 14px; border-radius: 50%; border: 1px solid #000; $c_verde'></div>
        <div style='width: 14px; height: 14px; border-radius: 50%; border: 1px solid #000; $c_amari'></div>
        <div style='width: 14px; height: 14px; border-radius: 50%; border: 1px solid #000; $c_rojo'></div>
    </div>
    ";
}

?>