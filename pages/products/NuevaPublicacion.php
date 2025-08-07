<?php
session_start();

if(!isset($_SESSION['usuario']) || empty($_SESSION['usuario']) || !isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$userId = $_SESSION['user_id'];
$carpetaUploads = __DIR__ . '/../../uploads/';

if (!is_dir($carpetaUploads)) {
    if (!mkdir($carpetaUploads, 0755, true)) {
        die("Error: No se pudo crear el directorio de subida");
    }
}

$extensionesPermitidasIMG = ['jpg', 'jpeg', 'png', 'gif'];
$extensionesPermitidasVID = ['mp4', 'mov', 'webm', 'avi'];
$maxTamanoImagen = 10 * 1024 * 1024;
$maxTamanoVideo = 500 * 1024 * 1024;

$videoSubido = null;
$imagen1Subida = null;
$imagen2Subida = null;
$imagen3Subida = null;

$errores = [];
$mensaje_exito = '';

function procesarArchivo($nombreInput, $extensionesPermitidas, $carpetaDestino, $tamanoMaximo, $userId) {
    if (isset($_FILES[$nombreInput]) && $_FILES[$nombreInput]['error'] === UPLOAD_ERR_OK) {
        $archivo = $_FILES[$nombreInput];
        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));

        if (!in_array($extension, $extensionesPermitidas)) {
            return ['error' => "La extensión .$extension no es permitida"];
        }

        if ($archivo['size'] > $tamanoMaximo) {
            return ['error' => "El archivo excede el tamaño máximo de " . ($tamanoMaximo / (1024 * 1024)) . "MB"];
        }

        $nuevoNombre = $userId . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $rutaFinal = $carpetaDestino . $nuevoNombre;

        if (move_uploaded_file($archivo['tmp_name'], $rutaFinal)) {
            return ['nombre' => $nuevoNombre];
        } else {
            return ['error' => "Error al mover el archivo"];
        }
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['titulo'] ?? '');
    $autor = trim($_POST['autor'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    
    if (empty($titulo)) $errores[] = "El título es obligatorio";
    if (empty($autor)) $errores[] = "El autor es obligatorio";
    if (empty($descripcion)) $errores[] = "La descripción es obligatoria";

    $resImagen1 = procesarArchivo('uploadImagen1', $extensionesPermitidasIMG, $carpetaUploads, $maxTamanoImagen, $userId);
    if ($resImagen1 && isset($resImagen1['error'])) $errores[] = $resImagen1['error'];
    elseif ($resImagen1) $imagen1Subida = $resImagen1['nombre'];
    else $errores[] = "La imagen de portada es obligatoria";

    $resImagen2 = procesarArchivo('uploadImagen2', $extensionesPermitidasIMG, $carpetaUploads, $maxTamanoImagen, $userId);
    if ($resImagen2 && isset($resImagen2['error'])) $errores[] = $resImagen2['error'];
    elseif ($resImagen2) $imagen2Subida = $resImagen2['nombre'];

    $resImagen3 = procesarArchivo('uploadImagen3', $extensionesPermitidasIMG, $carpetaUploads, $maxTamanoImagen, $userId);
    if ($resImagen3 && isset($resImagen3['error'])) $errores[] = $resImagen3['error'];
    elseif ($resImagen3) $imagen3Subida = $resImagen3['nombre'];

    $resVideo = procesarArchivo('uploadVideo', $extensionesPermitidasVID, $carpetaUploads, $maxTamanoVideo, $userId);
    if ($resVideo && isset($resVideo['error'])) $errores[] = $resVideo['error'];
    elseif ($resVideo) $videoSubido = $resVideo['nombre'];

    if (empty($errores)) {
        try {
            $conn = new mysqli(SERVER_NAME, DB_USER, DB_PASS, DB_NAME);
            $conn->begin_transaction();

            $insertLibro = $conn->prepare("
                INSERT INTO Libros (
                    titulo, autor, descripcion, editorial, edicion, categoria, tipoPublico, 
                    base, altura, paginas, fechaPublicacion, linkVideo, linkImagen1, linkImagen2, linkImagen3
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $editorial = substr($_POST['editorial'] ?? '', 0, 50);
            $edicion = substr($_POST['edicion'] ?? '', 0, 20);
            $categoria = substr($_POST['categoria'] ?? 'General', 0, 20);
            $tipoPublico = substr($_POST['tipoPublico'] ?? 'General', 0, 20);
            $base = !empty($_POST['base']) ? (float)$_POST['base'] : null;
            $altura = !empty($_POST['altura']) ? (float)$_POST['altura'] : null;
            $paginas = !empty($_POST['paginas']) ? (int)$_POST['paginas'] : null;
            $fechaPublicacion = isset($_POST['fechaPublicacion']) && !empty($_POST['fechaPublicacion']) ? $_POST['fechaPublicacion'] : NULL;
            $precio = !empty($_POST['precio']) ? (float)$_POST['precio'] : null;

            $insertLibro->bind_param("sssssssddiissss",
                $titulo, $autor, $descripcion, $editorial, $edicion, $categoria, $tipoPublico,
                $base, $altura, $paginas, $fechaPublicacion, $videoSubido, 
                $imagen1Subida, $imagen2Subida, $imagen3Subida
            );

            if (!$insertLibro->execute()) {
                throw new Exception("Error al insertar libro: " . $insertLibro->error);
            }

            $libroId = $conn->insert_id;
            $insertLibro->close();

            $hashtagIds = [];
            if (!empty($_POST['etiquetas'])) {
                $etiquetas = array_filter(array_map('trim', explode(',', $_POST['etiquetas'])));
                $etiquetas = array_unique($etiquetas);
                
                foreach ($etiquetas as $etiqueta) {
                    if (!empty($etiqueta) && strlen($etiqueta) <= 50) {
                        $etiqueta = htmlspecialchars($etiqueta);
                        
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

            if (!empty($hashtagIds)) {
                $insertRelacion = $conn->prepare("INSERT INTO LibroHashtags (idLibro, idHashtag) VALUES (?, ?)");
                foreach ($hashtagIds as $hashtagId) {
                    $insertRelacion->bind_param("ii", $libroId, $hashtagId);
                    if (!$insertRelacion->execute()) {
                        throw new Exception("Error al insertar relación libro-hashtag");
                    }
                }
                $insertRelacion->close();
            }

            $insertPublicacion = $conn->prepare("
                INSERT INTO Publicaciones (idUsuario, idLibro, precio, fechaCreacion)
                VALUES (?, ?, ?, NOW())
            ");
            $insertPublicacion->bind_param("iid", $userId, $libroId, $precio);

            if (!$insertPublicacion->execute()) {
                throw new Exception("Error al insertar publicación: " . $insertPublicacion->error);
            }

            $conn->commit();
            $_SESSION['mensaje_exito'] = "¡Publicación creada exitosamente!";
            header("Location: publicaciones.php");
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            $errores[] = "Error al guardar: " . $e->getMessage();
            error_log("Error en NuevaPublicacion: " . $e->getMessage());
        } finally {
            if (isset($conn)) $conn->close();
        }
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
        /* LOGO IMAGEN*/
        .logo-container {
            display: flex;
            align-items: center;
        }

        .logo-icon {
            width: 85px;
            height: 85px;
            position: relative;
            animation: logoFloat 3s ease-in-out infinite;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(107, 66, 38, 0.25);
            transition: all 0.3s ease;
        }

        @keyframes logoFloat {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-3px); }
        }

        .logo-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            transition: transform 0.3s ease;
        }

        .logo-icon:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 10px 25px rgba(107, 66, 38, 0.35);
        }

        .logo-icon:hover .logo-image {
            transform: scale(1.1);
        }

        /* Ajustes para responsive */
        @media (max-width: 768px) {
            .logo-icon {
                width: 70px;
                height: 70px;
            }
            
            header {
                flex-direction: column;
                gap: 15px;
                padding: 15px 20px;
            }
            
            .logo-container {
                order: -1;
            }
        }

        @media (max-width: 480px) {
            .logo-icon {
                width: 60px;
                height: 60px;
                border-radius: 12px;
            }
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

        <a href="../auth/perfil.php" class="topbar-icon" title="Perfil">
            <svg width="16" height="16" fill="white" viewBox="0 0 24 24">
                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
            </svg>
    </a>

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
<div class="logo-container">
    <a href="../home.php" class="logo-link" title="Ir al inicio">
        <div class="logo-icon">
            <img src="../../assets/images/REELEE.jpeg" alt="RELEE Logo" class="logo-image" />
        </div>
    </a>
</div>        <div class="search-bar">
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
                <input type="text" id="titulo" name="titulo" required value="<?php echo htmlspecialchars($_POST['titulo'] ?? ''); ?>" maxlength="70">
                <small>Máximo 70 caracteres</small>
            </div>

            <div class="form-group">
                <label for="autor">Autor:</label>
                <input type="text" id="autor" name="autor" required value="<?php echo htmlspecialchars($_POST['autor'] ?? ''); ?>" maxlength="70">
                <small>Máximo 70 caracteres</small>
            </div>

            <div class="form-group">
                <label for="editorial">Editorial:</label>
                <input type="text" id="editorial" name="editorial" value="<?php echo htmlspecialchars($_POST['editorial'] ?? ''); ?>" maxlength="50">
                <div class="char-counter" id="editorial-counter">0/50 caracteres</div>
            </div>

            <div class="form-group full-width">
                <label for="descripcion">Descripción:</label>
                <textarea id="descripcion" name="descripcion" rows="4" required><?php echo htmlspecialchars($_POST['descripcion'] ?? ''); ?></textarea>
                <small>Descripción detallada del libro</small>
            </div>

            <div class="form-group">
                <label for="categoria">Categoría:</label>
                <input type="text" id="categoria" name="categoria" value="<?php echo htmlspecialchars($_POST['categoria'] ?? ''); ?>" maxlength="20" placeholder="ej: Ficción, Romance">
                <div class="char-counter" id="categoria-counter">0/20 caracteres</div>
            </div>

            <div class="form-group">
                <label for="tipoPublico">Público:</label>
                <select id="tipoPublico" name="tipoPublico">
                    <option value="">Seleccione...</option>
                    <option value="General" <?php echo (($_POST['tipoPublico'] ?? '') === 'General' ? 'selected' : ''); ?>>General</option>
                    <option value="Infantil" <?php echo (($_POST['tipoPublico'] ?? '') === 'Infantil' ? 'selected' : ''); ?>>Infantil</option>
                    <option value="Juvenil" <?php echo (($_POST['tipoPublico'] ?? '') === 'Juvenil' ? 'selected' : ''); ?>>Juvenil</option>
                    <option value="Adultos" <?php echo (($_POST['tipoPublico'] ?? '') === 'Adultos' ? 'selected' : ''); ?>>Adultos</option>
                </select>
                <small>Opcional - Por defecto: General</small>
            </div>

            <div class="form-group">
                <label for="edicion">Edición:</label>
                <input type="text" id="edicion" name="edicion" value="<?php echo htmlspecialchars($_POST['edicion'] ?? ''); ?>" maxlength="20" placeholder="ej: 1ra, 2da">
                <div class="char-counter" id="edicion-counter">0/20 caracteres</div>
            </div>

            <div class="form-group">
                <label for="paginas">Páginas:</label>
                <input type="number" id="paginas" name="paginas" min="1" value="<?php echo htmlspecialchars($_POST['paginas'] ?? ''); ?>">
                <small>Opcional</small>
            </div>

            <div class="form-group dimensions-group">
                <label>Dimensiones (cm):</label>
                <div class="dimension-inputs">
                    <label for="base">Base:</label>
                    <input type="number" id="base" name="base" step="0.1" min="0" value="<?php echo htmlspecialchars($_POST['base'] ?? ''); ?>">
                    <label for="altura">Altura:</label>
                    <input type="number" id="altura" name="altura" step="0.1" min="0" value="<?php echo htmlspecialchars($_POST['altura'] ?? ''); ?>">
                </div>
                <small>Opcional</small>
            </div>
            <div class="form-group">
                <label for="fechaPublicacion">Fecha de Publicación:</label>
                <input type="date" id="fechaPublicacion" name="fechaPublicacion" 
                       value="<?php echo htmlspecialchars($datosPublicacion['fechaPublicacion'] ?? ''); ?>">
                <small>Opcional - Fecha original de publicación del libro</small>
            </div>


            <div class="form-group">
                <label for="precio">Precio:</label>
                <input type="number" id="precio" name="precio" step="0.01" min="0" value="<?php echo htmlspecialchars($_POST['precio'] ?? ''); ?>">
                <small>Opcional</small>
            </div>
            
            <div class="form-group">
                <label for="etiquetas">Etiquetas (separadas por comas):</label>
                <input type="text" id="etiquetas" name="etiquetas" placeholder="ej: ficción, fantasía, aventura" value="<?php echo htmlspecialchars($_POST['etiquetas'] ?? ''); ?>">
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
    </div>

    <script src="../../assets/js/home-script.js"></script>
    <script src="../../assets/js/chat-script.js"></script>
</body>
</html>
