<?php
// progel_core/equipos/bloque_captura.php
// ROUTER INTELIGENTE DE PANTALLAS

if (in_array($equipo_id, [81, 82])) {
    // ❄️ Redirige a Chillers Votator (Con Doble Delta Maestro/Esclavo)
    include __DIR__ . '/bloque_chillers.php';

} elseif (in_array($equipo_id, [8, 83, 84, 85, 86, 87, 88, 89])) {
    // 🔄 Registro antiguo de Votators obsoleto -> redirige siempre a Sis_Extracción
    header("Location: http://192.168.1.105/sis_extraccion/index.php?view=votators");
    exit();

} elseif (in_array($equipo_id, [91, 92])) {
    // ❄️ Chillers Normales
    include __DIR__ . '/bloque_chillers_normales.php';

} elseif (in_array($equipo_id, [14, 15, 16, 17, 18])) {
    // 🍯 Concentradores
    include __DIR__ . '/bloque_concentradores_todos.php';

} elseif ($equipo_id == 100) {
    // 🧪 Sistema de Membranas (4 Etapas - 13 Paquetes)
    include __DIR__ . '/bloque_membranas.php';

} elseif (in_array($equipo_id, [21, 22, 23, 24])) {
    // 💨 Secadores (1 al 4)
    include __DIR__ . '/bloque_secadores.php';

} elseif (in_array($equipo_id, [12, 13])) {
    // ⚙️ Compresores
    include __DIR__ . '/bloque_compresores.php';

} else {
    // 📋 Diseño genérico para el resto de equipos
    include __DIR__ . '/bloque_lista_normal.php';
}
?>
