<?php
session_start();

// Redireccionar si el usuario no ha iniciado sesión o no tiene un user_id
if (!isset($_SESSION['usuario']) || empty($_SESSION['usuario']) || !isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$userId = $_SESSION['user_id'];

// Directorio donde se guardarán los archivos subidos
$carpetaUploads = __DIR__ . '/../../uploads/';

// Crear la carpeta de uploads si no existe con permisos de escritura
if (!is_dir($carpetaUploads)) {
    if (!mkdir($carpetaUploads, 0755, true)) {
        die("Error: No se pudo crear el directorio de subida: " . $carpetaUploads . ". Verifique los permisos.");
    }
}

// Extensiones de archivo permitidas
$extensionesPermitidasIMG = ['jpg', 'jpeg', 'png', 'gif'];
$extensionesPermitidasVID = ['mp4', 'mov', 'webm', 'avi'];
$maxTamanoImagen = 10 * 1024 * 1024; // 10 MB en bytes para imágenes
$maxTamanoVideo = 500 * 1024 * 1024; // 500 MB en bytes para videos

// Variables para almacenar los nombres de archivo subidos
$videoSubido = null;
$imagen1Subida = null;
$imagen2Subida = null;
$imagen3Subida = null;

$errores = [];
$mensaje_exito = '';

// Función para procesar la subida de archivos
function procesarArchivo($nombreInput, $extensionesPermitidas, $carpetaDestino, $tamanoMaximo, $userId) {
    if (isset($_FILES[$nombreInput]) && $_FILES[$nombreInput]['error'] === UPLOAD_ERR_OK) {
        $archivo = $_FILES[$nombreInput];
        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));

        // Validación de extensión
        if (!in_array($extension, $extensionesPermitidas)) {
            return ['error' => "Error: La extensión .$extension para $nombreInput no es permitida. Solo se permiten " . implode(', ', $extensionesPermitidas) . "."];
        }

        // Validación de tamaño
        if ($archivo['size'] > $tamanoMaximo) {
            return ['error' => "Error: El archivo de $nombreInput excede el tamaño máximo permitido de " . ($tamanoMaximo / (1024 * 1024)) . "MB."];
        }

        // Generar un nombre único para el archivo
        $nuevoNombre = sprintf(
            '%d_%d_%s.%s',
            $userId,
            time(),
            bin2hex(random_bytes(8)),
            $extension
        );
        $rutaFinal = $carpetaDestino . $nuevoNombre;

        // Mover el archivo subido del directorio temporal al destino final
        if (move_uploaded_file($archivo['tmp_name'], $rutaFinal)) {
            return ['nombre' => $nuevoNombre];
        } else {
            return ['error' => "Error al mover el archivo subido para $nombreInput. Verifique los permisos de la carpeta de destino."];
        }
    } elseif (isset($_FILES[$nombreInput]) && $_FILES[$nombreInput]['error'] !== UPLOAD_ERR_NO_FILE) {
        switch ($_FILES[$nombreInput]['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return ['error' => "Error: El archivo de $nombreInput es demasiado grande (excede límites del servidor/formulario)."];
            case UPLOAD_ERR_PARTIAL:
                return ['error' => "Error: El archivo de $nombreInput se subió solo parcialmente."];
            case UPLOAD_ERR_NO_TMP_DIR:
                return ['error' => "Error interno: Falta una carpeta temporal en el servidor para $nombreInput."];
            case UPLOAD_ERR_CANT_WRITE:
                return ['error' => "Error: Fallo al escribir el archivo de $nombreInput en el disco."];
            case UPLOAD_ERR_EXTENSION:
                return ['error' => "Error: Una extensión de PHP detuvo la subida de $nombreInput."];
            default:
                return ['error' => "Error desconocido al subir el archivo para $nombreInput. Código: " . $_FILES[$nombreInput]['error']];
        }
    }
    return null;
}

// Función para procesar hashtags
function procesarHashtags($etiquetasTexto, $conn) {
    $hashtagIds = [];
    
    if (!empty(trim($etiquetasTexto))) {
        $etiquetas = array_map('trim', explode(',', $etiquetasTexto));
        $etiquetas = array_filter($etiquetas);
        $etiquetas = array_unique($etiquetas);
        
        foreach ($etiquetas as $etiqueta) {
            if (!empty($etiqueta) && strlen($etiqueta) <= 50) {
                $etiqueta = htmlspecialchars(trim($etiqueta));
                
                $checkHashtag = $conn->prepare("SELECT idHashtag FROM Hashtags WHERE texto = ?");
                $checkHashtag->bind_param("s", $etiqueta);
                $checkHashtag->execute();
                $result = $checkHashtag->get_result();
                
                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $hashtagIds[] = $row['idHashtag'];
                } else {
                    $insertHashtag = $conn->prepare("INSERT INTO Hashtags (texto, fechaCreacion) VALUES (?, NOW())");
                    $insertHashtag->bind_param("s", $etiqueta);
                    
                    if ($insertHashtag->execute()) {
                        $hashtagIds[] = $conn->insert_id;
                    }
                    $insertHashtag->close();
                }
                $checkHashtag->close();
            }
        }
    }
    
    return $hashtagIds;
}

// Inicializar variables para mantener los valores en el formulario
$titulo = htmlspecialchars($_POST['titulo'] ?? '');
$autor = htmlspecialchars($_POST['autor'] ?? '');
$descripcion = htmlspecialchars($_POST['descripcion'] ?? '');
$precio = htmlspecialchars($_POST['precio'] ?? '');
$etiquetas = htmlspecialchars($_POST['etiquetas'] ?? '');
$editorial = htmlspecialchars($_POST['editorial'] ?? '');
$edicion = htmlspecialchars($_POST['edicion'] ?? '');
$categoria = htmlspecialchars($_POST['categoria'] ?? '');
$tipoPublico = htmlspecialchars($_POST['tipoPublico'] ?? '');
$base = htmlspecialchars($_POST['base'] ?? '');
$altura = htmlspecialchars($_POST['altura'] ?? '');
$paginas = htmlspecialchars($_POST['paginas'] ?? '');
$fechaPublicacion = htmlspecialchars($_POST['fechaPublicacion'] ?? '');

try {
    $conn = new mysqli(SERVER_NAME, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset("utf8mb4");
    
    if ($conn->connect_error) {
        throw new Exception("Conexión fallida: " . $conn->connect_error);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validaciones básicas
        if (empty(trim($titulo))) {
            $errores[] = "El título es obligatorio.";
        }
        if (empty(trim($autor))) {
            $errores[] = "El autor es obligatorio.";
        }
        if (empty(trim($descripcion))) {
            $errores[] = "La descripción es obligatoria.";
        }
        
        // Validación específica para edición (máximo 20 caracteres según tu BD)
        if (!empty($edicion) && strlen($edicion) > 20) {
            $errores[] = "La edición no puede exceder los 20 caracteres. Actual: " . strlen($edicion) . " caracteres.";
        }
        
        // Validación específica para editorial (máximo 50 caracteres)
        if (!empty($editorial) && strlen($editorial) > 50) {
            $errores[] = "La editorial no puede exceder los 50 caracteres. Actual: " . strlen($editorial) . " caracteres.";
        }
        
        // Validación específica para categoría (máximo 20 caracteres según tu BD)
        if (!empty($categoria) && strlen($categoria) > 20) {
            $errores[] = "La categoría no puede exceder los 20 caracteres. Actual: " . strlen($categoria) . " caracteres.";
        }
        
        // Validación específica para tipoPublico (máximo 20 caracteres según tu BD)
        if (!empty($tipoPublico) && strlen($tipoPublico) > 20) {
            $errores[] = "El tipo de público no puede exceder los 20 caracteres. Actual: " . strlen($tipoPublico) . " caracteres.";
        }
        
        // Validaciones numéricas
        if (!empty($precio)) {
            if (!is_numeric($precio) || (float)$precio < 0) {
                $errores[] = "El precio debe ser un número válido y no negativo.";
            }
        }
        if (!empty($paginas)) {
            if (!filter_var($paginas, FILTER_VALIDATE_INT, array("options" => array("min_range"=>1)))) {
                $errores[] = "El número de páginas debe ser un número entero positivo.";
            }
        }
        if (!empty($base)) {
            if (!is_numeric($base) || (float)$base < 0) {
                $errores[] = "La base debe ser un número válido y no negativo.";
            }
        }
        if (!empty($altura)) {
            if (!is_numeric($altura) || (float)$altura < 0) {
                $errores[] = "La altura debe ser un número válido y no negativo.";
            }
        }

        // Procesar subidas de archivos
        
        // Procesar video (opcional)
        $resVideo = procesarArchivo('uploadVideo', $extensionesPermitidasVID, $carpetaUploads, $maxTamanoVideo, $userId);
        if ($resVideo && isset($resVideo['error'])) {
            $errores[] = $resVideo['error'];
        } else if ($resVideo) {
            $videoSubido = $resVideo['nombre'];
        }

        $resImagen1 = procesarArchivo('uploadImagen1', $extensionesPermitidasIMG, $carpetaUploads, $maxTamanoImagen, $userId);
        if ($resImagen1 && isset($resImagen1['error'])) {
            $errores[] = $resImagen1['error'];
        } else if ($resImagen1) {
            $imagen1Subida = $resImagen1['nombre'];
        } else {
            $errores[] = "La imagen de portada es obligatoria.";
        }

        $resImagen2 = procesarArchivo('uploadImagen2', $extensionesPermitidasIMG, $carpetaUploads, $maxTamanoImagen, $userId);
        if ($resImagen2 && isset($resImagen2['error'])) {
            $errores[] = $resImagen2['error'];
        } else if ($resImagen2) {
            $imagen2Subida = $resImagen2['nombre'];
        }

        $resImagen3 = procesarArchivo('uploadImagen3', $extensionesPermitidasIMG, $carpetaUploads, $maxTamanoImagen, $userId);
        if ($resImagen3 && isset($resImagen3['error'])) {
            $errores[] = $resImagen3['error'];
        } else if ($resImagen3) {
            $imagen3Subida = $resImagen3['nombre'];
        }

        // Si no hay errores de validación, insertar en la base de datos
        if (empty($errores)) {
            $conn->begin_transaction();
            
            try {
                // Convertir a null los campos vacíos para la DB
                $precioDB = !empty($precio) ? (float)$precio : null;
                $baseDB = !empty($base) ? (float)$base : null;
                $alturaDB = !empty($altura) ? (float)$altura : null;
                $paginasDB = !empty($paginas) ? (int)$paginas : null;
                $fechaPublicacionDB = !empty($fechaPublicacion) ? $fechaPublicacion : null;
                
                // Truncar campos de texto para que no excedan los límites de la BD
                $editorialDB = !empty($editorial) ? substr($editorial, 0, 50) : null;
                $edicionDB = !empty($edicion) ? substr($edicion, 0, 20) : null;
                $categoriaDB = !empty($categoria) ? substr($categoria, 0, 20) : 'General';
                $tipoPublicoDB = !empty($tipoPublico) ? substr($tipoPublico, 0, 20) : 'General';

                // **1. INSERTAR EN TABLA LIBROS** 
                $insertLibro = $conn->prepare("INSERT INTO Libros (
                    titulo, autor, descripcion, editorial, edicion, categoria, tipoPublico, 
                    base, altura, paginas, fechaPublicacion, linkVideo, linkImagen1, linkImagen2, linkImagen3
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                $insertLibro->bind_param("sssssssddisssss",
                    $titulo,
                    $autor,
                    $descripcion,
                    $editorialDB,
                    $edicionDB,
                    $categoriaDB,
                    $tipoPublicoDB,
                    $baseDB,
                    $alturaDB,
                    $paginasDB,
                    $fechaPublicacionDB,
                    $videoSubido,
                    $imagen1Subida,
                    $imagen2Subida,
                    $imagen3Subida
                );

                if (!$insertLibro->execute()) {
                    throw new Exception("Error al insertar el libro: " . $insertLibro->error);
                }

                $libroId = $conn->insert_id;
                $insertLibro->close();

                // **2. PROCESAR HASHTAGS Y CREAR RELACIONES**
                $hashtagIds = procesarHashtags($etiquetas, $conn);
                
                // **3. INSERTAR EN TABLA LIBRO_HASHTAGS**
                if (!empty($hashtagIds)) {
                    $insertRelacion = $conn->prepare("INSERT INTO LibroHashtags (idLibro, idHashtag) VALUES (?, ?)");
                    
                    foreach ($hashtagIds as $hashtagId) {
                        $insertRelacion->bind_param("ii", $libroId, $hashtagId);
                        if (!$insertRelacion->execute()) {
                            throw new Exception("Error al insertar relación libro-hashtag: " . $insertRelacion->error);
                        }
                    }
                    $insertRelacion->close();
                }

                // **4. INSERTAR EN TABLA PUBLICACIONES**
                $insertPublicacion = $conn->prepare("INSERT INTO Publicaciones (
                    idUsuario, idLibro, precio, fechaCreacion
                ) VALUES (?, ?, ?, NOW())");

                $insertPublicacion->bind_param("iid", $userId, $libroId, $precioDB);

                if (!$insertPublicacion->execute()) {
                    throw new Exception("Error al insertar la publicación: " . $insertPublicacion->error);
                }

                $publicacionId = $conn->insert_id;
                $insertPublicacion->close();

                $conn->commit();

                error_log("Publicación creada exitosamente - Libro ID: $libroId, Publicación ID: $publicacionId, Usuario ID: $userId");

                $_SESSION['mensaje_exito'] = "¡Publicación guardada exitosamente! Se crearon " . count($hashtagIds) . " hashtags.";
                header("Location: publicaciones.php");
                exit();

            } catch (Exception $e) {
                $conn->rollback();
                $errores[] = "Error al guardar la publicación: " . $e->getMessage();
                error_log("Error en transacción de publicación: " . $e->getMessage());
            }
        }
    }
} catch (Exception $e) {
    $errores[] = "Error en el servidor: " . $e->getMessage();
    error_log("Error en NuevaPublicacion.php: " . $e->getMessage());
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Nueva Publicación | RELEE</title>
    
    <link rel="stylesheet" href="../../assets/css/home-styles.css">
    <link rel="stylesheet" href="../../assets/css/chat-styles.css">
    
    <style>
        :root {
            --primary-color: #6a994e;
            --secondary-color: #a7c957;
            --background-color: #f2e9e4;
            --form-background: #ffffff;
            --text-color: #4a4e69;
            --border-color: #ced4da;
            --button-bg-color: #d8a47f;
            --button-text-color: #ffffff;
            --hover-color: #c98c5a;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            background: linear-gradient(135deg, #f8f6f3 0%, #f0ede8 100%);
            color: #2c2016;
            position: relative;
            padding-bottom: 65px;
            min-height: 100vh;
        }

        .add-publication-container {
            background: rgba(255, 253, 252, 0.9);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 40px;
            width: 90%;
            max-width: 800px;
            margin: 40px auto;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
        }

        .add-publication-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(163, 177, 138, 0.05) 0%, rgba(88, 129, 87, 0.05) 100%);
            border-radius: 20px;
            z-index: -1;
        }

        .add-publication-container h1 {
            text-align: center;
            margin-bottom: 30px;
            font-size: 2.2em;
            font-weight: 800;
            background: linear-gradient(135deg, #6b4226 0%, #8b5a3c 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -0.5px;
        }

        .publication-form {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
        }

        @media (min-width: 768px) {
            .publication-form {
                grid-template-columns: repeat(2, 1fr);
            }
            .form-group.full-width {
                grid-column: 1 / -1;
            }
        }

        .form-group {
            margin-bottom: 10px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c2016;
            font-size: 0.95em;
        }

        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group input[type="date"],
        .form-group input[type="url"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 20px;
            border: 2px solid transparent;
            border-radius: 15px;
            font-size: 1em;
            color: #2c2016;
            background: linear-gradient(white, white) padding-box,
                        linear-gradient(135deg, #a3b18a, #588157) border-box;
            transition: all 0.3s ease;
            box-sizing: border-box;
            box-shadow: 0 4px 15px rgba(163, 177, 138, 0.1);
        }

        .form-group input[type="text"]:focus,
        .form-group input[type="number"]:focus,
        .form-group input[type="date"]:focus,
        .form-group input[type="url"]:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            transform: translateY(-1px);
            box-shadow: 0 8px 25px rgba(163, 177, 138, 0.2);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .dimensions-group .dimension-inputs {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .dimensions-group .dimension-inputs label {
            margin-bottom: 0;
            white-space: nowrap;
            font-weight: 500;
        }

        .dimensions-group .dimension-inputs input {
            flex-grow: 1;
        }

        .file-upload-wrapper {
            position: relative;
            overflow: hidden;
            display: block;
            background: rgba(163, 177, 138, 0.05);
            border: 2px dashed #a3b18a;
            border-radius: 15px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }

        .file-upload-wrapper:hover {
            background: rgba(163, 177, 138, 0.1);
            transform: translateY(-1px);
        }

        .file-upload-wrapper input[type="file"] {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            cursor: pointer;
            width: 100%;
            height: 100%;
        }

        .file-upload-wrapper .file-label {
            display: block;
            color: #2c2016;
            font-weight: 500;
        }

        .file-upload-wrapper.video-upload {
            background: rgba(138, 177, 163, 0.05);
            border-color: #8ab1a3;
        }

        .file-upload-wrapper.video-upload:hover {
            background: rgba(138, 177, 163, 0.1);
        }

        .form-group small {
            display: block;
            margin-top: 5px;
            color: #6f5c4d;
            font-size: 0.85em;
            opacity: 0.8;
        }

        .form-actions {
            grid-column: 1 / -1;
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 30px;
        }

        .submit-button, .cancel-button {
            padding: 15px 30px;
            border: none;
            border-radius: 25px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            letter-spacing: 0.5px;
            text-transform: uppercase;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .submit-button {
            background: linear-gradient(135deg, #588157 0%, #3a5a40 100%);
            color: white;
            box-shadow: 0 6px 20px rgba(88, 129, 87, 0.3);
        }

        .submit-button:hover {
            background: linear-gradient(135deg, #3a5a40 0%, #2d4732 100%);
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(88, 129, 87, 0.4);
        }

        .cancel-button {
            background: linear-gradient(135deg, #6c584c 0%, #5b4a3e 100%);
            color: white;
            box-shadow: 0 6px 20px rgba(108, 88, 76, 0.3);
        }

        .cancel-button:hover {
            background: linear-gradient(135deg, #5b4a3e 0%, #4a3d32 100%);
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(108, 88, 76, 0.4);
        }

        .errores {
            background-color: #ffebee;
            border: 1px solid #ef9a9a;
            color: #c62828;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            grid-column: 1 / -1;
        }
        
        .errores p {
            margin: 5px 0;
            font-weight: 500;
        }
        
        .exito {
            background-color: #e8f5e8;
            border: 1px solid #4caf50;
            color: #2e7d32;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            grid-column: 1 / -1;
        }

        .char-counter {
            font-size: 0.8em;
            color: #666;
            margin-top: 2px;
        }

        .char-counter.warning {
            color: #ff9800;
        }

        .char-counter.error {
            color: #f44336;
        }

        @media (max-width: 768px) {
            .add-publication-container {
                padding: 30px 20px;
                margin: 20px 15px;
            }
            
            .add-publication-container h1 {
                font-size: 1.8em;
            }
            
            .form-actions {
                flex-direction: column;
                align-items: stretch;
            }
            
            .submit-button, .cancel-button {
                width: 100%;
                padding: 12px 20px;
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .add-publication-container {
            animation: fadeInUp 0.6s ease forwards;
        }
    </style>
</head>
<body>
    <div class="topbar">
        <div class="topbar-icon" title="Chat">
            <svg width="16" height="16" fill="white" viewBox="0 0 24 24">
                <path d="M12 2c.55 0 1 .45 1 1v1h4a2 2 0 0 1 2 2v2h1a1 1 0 1 1 0 2h-1v6a3 3 0 0 1-3 3h-1v1a1 1 0 1 1-2 0v-1H9v1a1 1 0 1 1-2 0v-1H6a3 3 0 0 1-3-3v-6H2a1 1 0 1 1 0-2h1V6a2 2 0 0 1 2-2h4V3c0-.55.45-1 1-1zm-5 9a1 1 0 1 0 0 2 1 1 0 0 0 0-2zm10 0a1 1 0 1 0 0 2 1 1 0 0 0 0-2z"/>
            </svg>
        </div>

        <div class="topbar-icon" title="Chat 2">
            <a href="../chat/chat.php" class="bottom-button" title="Chat">
                <svg width="16" height="16" fill="white" viewBox="0 0 24 24">
                    <path d="M20 2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h4l4 4 4-4h4c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/>
                </svg>
            </a>
        </div>

        <div class="topbar-icon" title="Perfil">
            <svg width="16" height="16" fill="white" viewBox="0 0 24 24">
                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
            </svg>
        </div>

        <form action="../auth/logout.php" method="post" class="logout-form">
            <button type="submit" class="logout-button">
                <svg width="14" height="14" fill="white" viewBox="0 0 24 24">
                    <path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.59L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/>
                </svg>
                Cerrar sesión
            </button>
        </form>
    </div>

    <?php include '../../includes/chat-component.php'; ?>

    <header>
        <div class="logo">RELEE</div>
        <div class="search-bar">
            <input type="text" placeholder="Buscar libros, autores, géneros...">
            <button>
                <svg width="18" height="18" fill="white" viewBox="0 0 24 24">
                    <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                </svg>
            </button>
        </div>
        <button class="user-button">Búsqueda Avanzada</button>
    </header>

    <main class="add-publication-container">
        <h1>Agregar Nueva Publicación</h1>
        
        <?php if (!empty($errores)): ?>
            <div class="errores">
                <?php foreach ($errores as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($mensaje_exito)): ?>
            <div class="exito">
                <p><?php echo htmlspecialchars($mensaje_exito); ?></p>
            </div>
        <?php endif; ?>
        
        <form action="" method="POST" class="publication-form" enctype="multipart/form-data">
            
            <div class="form-group full-width">
                <label for="titulo">Título:</label>
                <input type="text" id="titulo" name="titulo" required value="<?php echo $titulo; ?>" maxlength="70">
                <small>Máximo 70 caracteres</small>
            </div>

            <div class="form-group">
                <label for="autor">Autor:</label>
                <input type="text" id="autor" name="autor" required value="<?php echo $autor; ?>" maxlength="70">
                <small>Máximo 70 caracteres</small>
            </div>

            <div class="form-group">
                <label for="editorial">Editorial:</label>
                <input type="text" id="editorial" name="editorial" value="<?php echo $editorial; ?>" maxlength="50">
                <div class="char-counter" id="editorial-counter">0/50 caracteres</div>
            </div>

            <div class="form-group full-width">
                <label for="descripcion">Descripción:</label>
                <textarea id="descripcion" name="descripcion" rows="4" required><?php echo $descripcion; ?></textarea>
                <small>Descripción detallada del libro</small>
            </div>

            <div class="form-group">
                <label for="categoria">Categoría:</label>
                <input type="text" id="categoria" name="categoria" value="<?php echo $categoria; ?>" maxlength="20" placeholder="ej: Ficción, Romance">
                <div class="char-counter" id="categoria-counter">0/20 caracteres</div>
            </div>

            <div class="form-group">
                <label for="tipoPublico">Público:</label>
                <select id="tipoPublico" name="tipoPublico">
                    <option value="">Seleccione...</option>
                    <option value="General" <?php echo ($tipoPublico === 'General' ? 'selected' : ''); ?>>General</option>
                    <option value="Infantil" <?php echo ($tipoPublico === 'Infantil' ? 'selected' : ''); ?>>Infantil</option>
                    <option value="Juvenil" <?php echo ($tipoPublico === 'Juvenil' ? 'selected' : ''); ?>>Juvenil</option>
                    <option value="Adultos" <?php echo ($tipoPublico === 'Adultos' ? 'selected' : ''); ?>>Adultos</option>
                </select>
                <small>Opcional - Por defecto: General</small>
            </div>

            <div class="form-group">
                <label for="edicion">Edición:</label>
                <input type="text" id="edicion" name="edicion" value="<?php echo $edicion; ?>" maxlength="20" placeholder="ej: 1ra, 2da">
                <div class="char-counter" id="edicion-counter">0/20 caracteres</div>
            </div>

            <div class="form-group">
                <label for="paginas">Páginas:</label>
                <input type="number" id="paginas" name="paginas" min="1" value="<?php echo $paginas; ?>">
                <small>Opcional</small>
            </div>

            <div class="form-group dimensions-group">
                <label>Dimensiones (cm):</label>
                <div class="dimension-inputs">
                    <label for="base">Base:</label>
                    <input type="number" id="base" name="base" step="0.1" min="0" value="<?php echo $base; ?>">
                    <label for="altura">Altura:</label>
                    <input type="number" id="altura" name="altura" step="0.1" min="0" value="<?php echo $altura; ?>">
                </div>
                <small>Opcional</small>
            </div>

            <div class="form-group">
                <label for="fechaPublicacion">Fecha de Publicación:</label>
                <input type="date" id="fechaPublicacion" name="fechaPublicacion" value="<?php echo $fechaPublicacion; ?>">
                <small>Opcional - Fecha original de publicación del libro</small>
            </div>

            <div class="form-group">
                <label for="precio">Precio:</label>
                <input type="number" id="precio" name="precio" step="0.01" min="0" value="<?php echo $precio; ?>">
                <small>Opcional</small>
            </div>
            
            <div class="form-group">
                <label for="etiquetas">Etiquetas (separadas por comas):</label>
                <input type="text" id="etiquetas" name="etiquetas" placeholder="ej: ficción, fantasía, aventura" value="<?php echo $etiquetas; ?>">
                <small>Opcional - Máximo 50 caracteres por etiqueta</small>
            </div>
            
            <div class="form-group full-width">
                <label for="uploadVideo">Video del Libro:</label>
                <div class="file-upload-wrapper video-upload">
                    <span class="file-label">Seleccionar video del libro (Max 500MB)</span>
                    <input type="file" id="uploadVideo" name="uploadVideo" accept="video/mp4,video/mov,video/webm,video/avi">
                </div>
                <small>Opcional - Formatos permitidos: MP4, MOV, WEBM, AVI</small>
            </div>

            <div class="form-group full-width">
                <label for="uploadImagen1">Imagen de Portada (Obligatoria):</label>
                <div class="file-upload-wrapper">
                    <span class="file-label">Seleccionar imagen de portada (Max 10MB)</span>
                    <input type="file" id="uploadImagen1" name="uploadImagen1" accept="image/*" required>
                </div>
                <small>Esta imagen es obligatoria</small>
            </div>

            <div class="form-group full-width">
                <label for="uploadImagen2">Imagen Adicional 1:</label>
                <div class="file-upload-wrapper">
                    <span class="file-label">Seleccionar imagen adicional (Max 10MB)</span>
                    <input type="file" id="uploadImagen2" name="uploadImagen2" accept="image/*">
                </div>
                <small>Si no sabes qué subir aquí, recomendamos una imagen de la contraportada</small>
            </div>

            <div class="form-group full-width">
                <label for="uploadImagen3">Imagen Adicional 2:</label>
                <div class="file-upload-wrapper">
                    <span class="file-label">Seleccionar imagen adicional (Max 10MB)</span>
                    <input type="file" id="uploadImagen3" name="uploadImagen3" accept="image/*">
                </div>
                <small>Si no sabes qué subir aquí, recomendamos una foto que muestre el estado del libro</small>
            </div>

            <div class="form-actions">
                <a href="../home.php" class="cancel-button">Cancelar</a>
                <button type="submit" class="submit-button">Guardar Publicación</button>
            </div>
        </form>
    </main>

    <div class="bottombar">
        <a href="../home.php" class="bottom-button" title="Inicio">
            <svg width="22" height="22" fill="white" viewBox="0 0 24 24">
                <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>
            </svg>
            <span>Inicio</span>
        </a>
        <a href="publicaciones.php" class="bottom-button bottom-button-wide" title="Mis Publicaciones">
            <span>Mis Publicaciones</span>
        </a>
        <button class="bottom-button" title="Menú">
            <svg width="22" height="22" fill="white" viewBox="0 0 24 24">
                <path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/>
            </svg>
            <span>Menú</span>
        </button>
    </div>

    <script src="../../assets/js/home-script.js"></script>
    <script src="../../assets/js/chat-script.js"></script>
    <script>
        // Función para actualizar contadores de caracteres
        function updateCharCounter(inputId, counterId, maxLength) {
            const input = document.getElementById(inputId);
            const counter = document.getElementById(counterId);
            
            function updateCounter() {
                const currentLength = input.value.length;
                counter.textContent = `${currentLength}/${maxLength} caracteres`;
                
                if (currentLength > maxLength * 0.8) {
                    counter.className = 'char-counter warning';
                } else if (currentLength >= maxLength) {
                    counter.className = 'char-counter error';
                } else {
                    counter.className = 'char-counter';
                }
            }
            
            input.addEventListener('input', updateCounter);
            updateCounter(); // Actualizar al cargar
        }

        // Inicializar contadores
        document.addEventListener('DOMContentLoaded', function() {
            updateCharCounter('editorial', 'editorial-counter', 50);
            updateCharCounter('edicion', 'edicion-counter', 20);
            updateCharCounter('categoria', 'categoria-counter', 20);
        });

        // Actualizar nombres de archivos seleccionados
        document.querySelectorAll('input[type="file"]').forEach(input => {
            input.addEventListener('change', function() {
                const label = this.parentElement.querySelector('.file-label');
                if (this.files.length > 0) {
                    const fileName = this.files[0].name;
                    const fileSize = (this.files[0].size / (1024 * 1024)).toFixed(2);
                    
                    if (this.id === 'uploadVideo') {
                        label.textContent = `Video seleccionado: ${fileName} (${fileSize}MB)`;
                    } else {
                        label.textContent = `Imagen seleccionada: ${fileName} (${fileSize}MB)`;
                    }
                    label.style.color = '#588157';
                } else {
                    // Resetear texto original
                    if (this.id === 'uploadVideo') {
                        label.textContent = 'Seleccionar video del libro (Max 500MB)';
                    } else if (this.id === 'uploadImagen1') {
                        label.textContent = 'Seleccionar imagen de portada (Max 10MB)';
                    } else {
                        label.textContent = 'Seleccionar imagen adicional (Max 10MB)';
                    }
                    label.style.color = '';
                }
            });
        });

        function openChatModal() {
            console.log('Abriendo chat principal...');
        }

        function openChatModal2() {
            console.log('Abriendo chat secundario...');
        }

        // Validación del formulario
        document.querySelector('.publication-form').addEventListener('submit', function(e) {
            const titulo = document.getElementById('titulo').value.trim();
            const autor = document.getElementById('autor').value.trim();
            const descripcion = document.getElementById('descripcion').value.trim();
            const imagen1 = document.getElementById('uploadImagen1');
            
            let erroresJS = [];

            if (!titulo) {
                erroresJS.push('El título es obligatorio.');
            }
            if (!autor) {
                erroresJS.push('El autor es obligatorio.');
            }
            if (!descripcion) {
                erroresJS.push('La descripción es obligatoria.');
            }
            if (imagen1.files.length === 0) {
                erroresJS.push('La imagen de portada es obligatoria.');
            }

            // Validar longitudes de campos
            const editorial = document.getElementById('editorial').value;
            const edicion = document.getElementById('edicion').value;
            const categoria = document.getElementById('categoria').value;

            if (editorial.length > 50) {
                erroresJS.push('La editorial no puede exceder los 50 caracteres.');
            }
            if (edicion.length > 20) {
                erroresJS.push('La edición no puede exceder los 20 caracteres.');
            }
            if (categoria.length > 20) {
                erroresJS.push('La categoría no puede exceder los 20 caracteres.');
            }

            const precio = document.getElementById('precio').value.trim();
            if (precio !== '' && (isNaN(precio) || parseFloat(precio) < 0)) {
                erroresJS.push('El precio debe ser un número válido y no negativo.');
            }

            const paginas = document.getElementById('paginas').value.trim();
            if (paginas !== '' && (isNaN(paginas) || parseInt(paginas) < 1)) {
                erroresJS.push('El número de páginas debe ser un número entero positivo.');
            }

            const base = document.getElementById('base').value.trim();
            if (base !== '' && (isNaN(base) || parseFloat(base) < 0)) {
                erroresJS.push('La base debe ser un número válido y no negativo.');
            }

            const altura = document.getElementById('altura').value.trim();
            if (altura !== '' && (isNaN(altura) || parseFloat(altura) < 0)) {
                erroresJS.push('La altura debe ser un número válido y no negativo.');
            }

            // Validar tamaño de archivos
            const video = document.getElementById('uploadVideo');
            if (video.files.length > 0) {
                const videoSize = video.files[0].size;
                const maxVideoSize = 500 * 1024 * 1024; // 500MB
                if (videoSize > maxVideoSize) {
                    erroresJS.push('El video no puede exceder los 500MB.');
                }
                
                // Validar extensión de video
                const videoExtension = video.files[0].name.split('.').pop().toLowerCase();
                const allowedVideoExtensions = ['mp4', 'mov', 'webm', 'avi'];
                if (!allowedVideoExtensions.includes(videoExtension)) {
                    erroresJS.push('El formato de video no es válido. Solo se permiten: MP4, MOV, WEBM, AVI.');
                }
            }

            // Validar tamaño de imágenes
            const imagenes = ['uploadImagen1', 'uploadImagen2', 'uploadImagen3'];
            const maxImageSize = 10 * 1024 * 1024; // 10MB
            
            imagenes.forEach(imagenId => {
                const imagen = document.getElementById(imagenId);
                if (imagen.files.length > 0) {
                    const imageSize = imagen.files[0].size;
                    if (imageSize > maxImageSize) {
                        erroresJS.push(`La imagen ${imagenId.replace('upload', '').replace('Imagen', ' ')} no puede exceder los 10MB.`);
                    }
                }
            });

            if (erroresJS.length > 0) {
                e.preventDefault();
                alert('Por favor corrige los siguientes errores:\n\n' + erroresJS.join('\n'));
                return false;
            }
        });

        // Efectos visuales de foco para inputs
        document.querySelectorAll('input, select, textarea').forEach(element => {
            element.addEventListener('focus', function() {
                this.style.transform = 'translateY(-1px)';
            });
            
            element.addEventListener('blur', function() {
                this.style.transform = 'translateY(0)';
            });
        });
    </script>
</body>
</html>