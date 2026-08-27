<?php
// Archivo: descargar.php
require_once 'conexion.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Error: ID no válido.");
}

$id_ticket = (int)$_GET['id'];
$tipo_archivo = $_GET['tipo'] ?? 'evidencia'; 

// Elegimos qué columna consultar según el tipo
if ($tipo_archivo === 'causa') {
    $columna = 'ruta_causa_raiz';
} elseif ($tipo_archivo === 'evidencia_apertura') {
    $columna = 'ruta_evidencia_apertura';
} else {
    $columna = 'ruta_evidencia'; // Por defecto, la evidencia de cierre
}

$sql = "SELECT $columna AS archivo FROM registros WHERE id = ?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $id_ticket);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows > 0) {
    $fila = $resultado->fetch_assoc();
    $nombre_archivo = $fila['archivo'];

    if (empty($nombre_archivo)) {
        die("Error: No hay archivo subido en esta categoría.");
    }

    $ruta_archivo = RUTA_NAS_EVIDENCIAS . $nombre_archivo;
    $ruta_archivo = str_replace('/', '\\', $ruta_archivo); // Reparar diagonales para la NAS

    if (!file_exists($ruta_archivo)) {
        die("Error: El archivo no se encontró físicamente.");
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $ruta_archivo);
    finfo_close($finfo);

    // Ajuste de mimes comunes
    if (!$mime_type || $mime_type == 'application/octet-stream') {
        $ext = strtolower(pathinfo($ruta_archivo, PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg'])) $mime_type = 'image/jpeg';
        elseif ($ext == 'png') $mime_type = 'image/png';
        elseif ($ext == 'pdf') $mime_type = 'application/pdf';
        elseif (in_array($ext, ['doc', 'docx'])) $mime_type = 'application/msword';
    }

    header('Content-Type: ' . $mime_type);
    header('Content-Disposition: inline; filename="' . basename($ruta_archivo) . '"');
    header('Content-Length: ' . filesize($ruta_archivo));
    readfile($ruta_archivo);
    exit;
}
?>