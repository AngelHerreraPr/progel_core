<?php
// Progel_corese/equipos/bloque_captura.php
// ROUTER INTELIGENTE DE PANTALLAS

if (in_array($equipo_id, [81, 82])) {
    // ❄️ Redirige a Chillers Votator (Con Doble Delta Maestro/Esclavo)
    include __DIR__ . '/bloque_chillers.php';

} elseif (in_array($equipo_id, [84, 85, 86, 87, 88, 89])) {
    // 🔄 Redirige a Votators del 1 al 6 (Con Delta Simple Automático)
    include __DIR__ . '/bloque_votators_todos.php';

} elseif (in_array($equipo_id, [91, 92])) {
    // ❄️ Chillers Normales
    include __DIR__ . '/bloque_chillers_normales.php';

} elseif (in_array($equipo_id, [14, 15, 16, 17, 18])) {
    // 🍯 Concentradores
    include __DIR__ . '/bloque_concentradores_todos.php';

} elseif (in_array($equipo_id, [21, 23])) {
    // 💨 Secadores
    include __DIR__ . '/bloque_secadores.php';

} elseif (in_array($equipo_id, [12, 13])) {
    // ⚙️ Compresores
    include __DIR__ . '/bloque_compresores.php';

} else {
    // 📋 Diseño genérico para el resto de equipos
    include __DIR__ . '/bloque_lista_normal.php';
}
?>