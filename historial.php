<?php
// Progel_cores/historial.php
include 'config/db.php';

// Recibimos qué equipo queremos revisar
$equipo_id = $_GET['id'] ?? 0;

// Buscamos el nombre del equipo para el título
$q_equipo = mysqli_query($conn, "SELECT nombre FROM equipos WHERE id = $equipo_id");
$equipo_nombre = ($row = mysqli_fetch_assoc($q_equipo)) ? $row['nombre'] : 'Equipo Desconocido';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial - <?= htmlspecialchars($equipo_nombre) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background-color: #f4f6f9; }
        .table-hover tbody tr:hover { background-color: #e9ecef; }
    </style>
</head>
<body>

<div class="container-fluid py-4 px-4">
    <div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 rounded shadow-sm border-start border-5 border-warning">
        <h2 class="m-0 fw-bold text-dark">
            <i class="bi bi-clock-history text-warning me-2"></i> Historial de Registro: <span class="text-primary"><?= htmlspecialchars($equipo_nombre) ?></span>
        </h2>
        <div class="d-flex gap-2">
            <a href="reporte_general.php?equipo_id=<?= $equipo_id ?>" class="btn btn-outline-success fw-bold">
                <i class="bi bi-grid-3x3-gap me-1"></i> Vista Matricial
            </a>
            <button onclick="window.history.back();" class="btn btn-outline-secondary fw-bold">
                <i class="bi bi-arrow-left me-1"></i> Volver
            </button>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle text-center m-0">
                    <thead class="table-dark">
                        <tr style="height: 50px;">
                            <th class="bg-primary text-white"><i class="bi bi-calendar-event"></i> Fecha y Hora</th>
                            <th><i class="bi bi-person-badge"></i> Nómina</th>
                            <th class="text-start ps-3"><i class="bi bi-list-check"></i> Parámetro Medido</th>
                            <th style="width: 150px;"><i class="bi bi-speedometer2"></i> Lectura</th>
                            <th><i class="bi bi-chat-square-text"></i> Observaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Consulta MAESTRA: Cruzamos las lecturas con el nombre del parámetro
                        $query = "SELECT b.fecha_registro, b.numero_nomina, p.nombre_parametro, b.valor_capturado, b.observaciones,
                                         p.tipo_dato, p.rojo_bajo, p.amarillo_bajo, p.amarillo_alto, p.rojo_alto
                                  FROM bitacora_lecturas b
                                  INNER JOIN parametros p ON b.parametro_id = p.id
                                  WHERE b.equipo_id = ?
                                  ORDER BY b.fecha_registro DESC"; // DESC para que lo más nuevo salga primero

                        $stmt = mysqli_prepare($conn, $query);
                        mysqli_stmt_bind_param($stmt, "i", $equipo_id);
                        mysqli_stmt_execute($stmt);
                        $result = mysqli_stmt_get_result($stmt);

                        if(mysqli_num_rows($result) > 0):
                            while($r = mysqli_fetch_assoc($result)):
                                // Formatear fecha para que se lea fácil (Ej. 16/07/2026 10:30 AM)
                                $fecha_bonita = date("d/m/Y - h:i A", strtotime($r['fecha_registro']));

                                // --- Lógica de Semáforo e Íconos ---
                                $valor = $r['valor_capturado'];
                                $class = '';
                                $icon_class = '';
                                $icon_color_class = 'text-secondary';
                                $lower_val = strtolower(trim($valor));

                                if ($r['tipo_dato'] == 'NUMERICO' && is_numeric($valor)) {
                                    $v = floatval($valor);
                                    if (isset($r['rojo_bajo'])) {
                                        if ($v <= floatval($r['rojo_bajo']) || $v >= floatval($r['rojo_alto'])) $class = 'semaforo-r';
                                        else if (($v > floatval($r['rojo_bajo']) && $v <= floatval($r['amarillo_bajo'])) || ($v >= floatval($r['amarillo_alto']) && $v < floatval($r['rojo_alto']))) $class = 'semaforo-a';
                                        else $class = 'semaforo-v';
                                    }
                                } else { 
                                    if (in_array($lower_val, ['no', 'no realizado', 'no cumple', 'fideo plastoso', 'sí presenta', 'quemada', 'húmeda'])) $class = 'semaforo-r';
                                    else if (in_array($lower_val, ['deformación en el fideo', 'deformación', 'pendiente', 'sobresecada', 'plástica'])) $class = 'semaforo-a';
                                    else if (in_array($lower_val, ['sí', 'realizado', 'cumple', 'fideo continuo', 'firme', 'no presenta'])) $class = 'semaforo-v';
                                }

                                if ($class === 'semaforo-v') {
                                    $icon_class = 'bi-check-circle-fill';
                                    $icon_color_class = 'text-success';
                                } elseif ($class === 'semaforo-a') {
                                    $icon_class = 'bi-exclamation-triangle-fill';
                                    $icon_color_class = 'text-warning';
                                } elseif ($class === 'semaforo-r') {
                                    $icon_class = 'bi-x-circle-fill';
                                    $icon_color_class = 'text-danger';
                                }
                        ?>
                        <tr class="<?= $class ?>" style="height: 60px;">
                            <td class="fw-bold text-secondary"><?= $fecha_bonita ?></td>
                            <td><span class="badge bg-dark fs-6 rounded-pill px-3 py-2"><?= htmlspecialchars($r['numero_nomina']) ?></span></td>
                            <td class="text-start fw-bold text-dark ps-3"><?= htmlspecialchars($r['nombre_parametro']) ?></td>
                            <td class="fs-5 fw-bold">
                                <?php if($icon_class): ?><i class="bi <?= $icon_class ?> <?= $icon_color_class ?> me-2"></i><?php endif; ?>
                                <?= htmlspecialchars($r['valor_capturado']) ?>
                            </td>
                            <td class="text-muted text-start" style="font-size: 0.9rem;"><?= htmlspecialchars($r['observaciones']) ?: '<em class="text-light-50">Sin notas</em>' ?></td>
                        </tr>
                        <?php
                            endwhile;
                        else:
                        ?>
                        <tr>
                            <td colspan="5" class="py-5 text-muted fs-4">
                                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                Aún no hay registros en el historial de este equipo.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
