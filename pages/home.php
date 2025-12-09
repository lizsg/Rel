<?php
    session_start();

    if(!isset($_SESSION['usuario']) || empty($_SESSION['usuario'])) {
        header("Location: auth/login.php");
        exit(); 
    }

    require_once __DIR__ . '/../config/database.php';
    $userId = $_SESSION['user_id']; 

    // --- LOGICA PARA NUEVA PUBLICACION ---
    $carpetaUploads = __DIR__ . '/../uploads/';

    if (!is_dir($carpetaUploads)) {
        if (!mkdir($carpetaUploads, 0755, true)) {
            error_log("Error: No se pudo crear el directorio de subida");
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

    /*
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_publicacion'])) {
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
                header("Location: home.php");
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
    */
    // --- FIN LOGICA NUEVA PUBLICACION ---

    $publicaciones = [];
    $hashtags = [];
    $errorMessage = '';

    try {
        $conn = new mysqli(SERVER_NAME, DB_USER, DB_PASS, DB_NAME);
        $conn->set_charset("utf8mb4");
        
        if ($conn->connect_error) {
            throw new Exception("Error de conexión: " . $conn->connect_error);
        }

        // Verificar primero la estructura real de la tabla Usuarios
        $checkColumns = $conn->query("SHOW COLUMNS FROM Usuarios");
        $columnas_usuarios = [];
        
        while ($col = $checkColumns->fetch_assoc()) {
            $columnas_usuarios[] = $col['Field'];
        }
        
        // Determinar qué columna usar para el nombre de usuario
        $columna_nombre_usuario = null;
        $posibles_columnas = ['usuario', 'nombre', 'username', 'email', 'user_name', 'nombre_usuario'];
        
        foreach ($posibles_columnas as $columna) {
            if (in_array($columna, $columnas_usuarios)) {
                $columna_nombre_usuario = $columna;
                break;
            }
        }
        
        // Si no encontramos una columna específica, usamos el ID
        if (!$columna_nombre_usuario) {
            $columna_nombre_usuario = 'idUsuario';
            $usar_id_como_nombre = true;
        } else {
            $usar_id_como_nombre = false;
        }

        // Consulta para obtener estados (PublicacionesSocial)
        $queryEstados = "
            SELECT 
                ps.idPublicacionSocial as id,
                ps.idUsuario,
                ps.fechaCreacion,
                'status' as type,
                ps.contenido as descripcion,
                ps.imagen as linkImagen1,
                ps.tipo as subtipo,
                (SELECT COUNT(*) FROM LikesSocial ls WHERE ls.idPublicacionSocial = ps.idPublicacionSocial) as totalLikes,
                (SELECT COUNT(*) FROM LikesSocial ls WHERE ls.idPublicacionSocial = ps.idPublicacionSocial AND ls.idUsuario = $userId) as likedByMe,
                (SELECT COUNT(*) FROM ComentariosSocial cs WHERE cs.idPublicacionSocial = ps.idPublicacionSocial) as totalComentarios,
                (SELECT u.fotoPerfil FROM Usuarios u WHERE u.idUsuario = ps.idUsuario LIMIT 1) as fotoPerfil,
                CASE 
                    WHEN EXISTS (SELECT 1 FROM Usuarios u WHERE u.idUsuario = ps.idUsuario) THEN 
                        COALESCE(
                            (SELECT u.$columna_nombre_usuario FROM Usuarios u WHERE u.idUsuario = ps.idUsuario LIMIT 1),
                            CONCAT('Usuario #', ps.idUsuario)
                        )
                    ELSE CONCAT('Usuario #', ps.idUsuario)
                END as nombreUsuario
            FROM PublicacionesSocial ps
            ORDER BY ps.fechaCreacion DESC
            LIMIT 50
        ";

        $resultEstados = $conn->query($queryEstados);
        if ($resultEstados) {
            while ($row = $resultEstados->fetch_assoc()) {
                if ($usar_id_como_nombre && is_numeric($row['nombreUsuario'])) {
                    $row['nombreUsuario'] = 'Usuario #' . $row['nombreUsuario'];
                }
                // Campos vacíos para compatibilidad
                $row['titulo'] = '';
                $row['autor'] = '';
                $row['precio'] = null;
                $row['idLibro'] = null;
                $publicaciones[] = $row;
            }
        }

        // Ordenar todas las publicaciones por fecha
        usort($publicaciones, function($a, $b) {
            return strtotime($b['fechaCreacion']) - strtotime($a['fechaCreacion']);
        });

        // Obtener hashtags para cada libro si hay publicaciones (DESACTIVADO)
        /*
        if (!empty($publicaciones)) {
            $libroIds = [];
            foreach ($publicaciones as $pub) {
                if ($pub['type'] === 'book' && !empty($pub['idLibro'])) {
                    $libroIds[] = $pub['idLibro'];
                }
            }
            
            if (!empty($libroIds)) {
                $placeholders = implode(',', array_fill(0, count($libroIds), '?'));
                
                $hashtagStmt = $conn->prepare("
                    SELECT lh.idLibro, h.texto as hashtag
                    FROM LibroHashtags lh
                    INNER JOIN Hashtags h ON lh.idHashtag = h.idHashtag
                    WHERE lh.idLibro IN ($placeholders)
                ");
                
                if ($hashtagStmt) {
                    $types = str_repeat('i', count($libroIds));
                    $hashtagStmt->bind_param($types, ...$libroIds);
                    $hashtagStmt->execute();
                    $hashtagResult = $hashtagStmt->get_result();
                    
                    while ($hashtagRow = $hashtagResult->fetch_assoc()) {
                        $hashtags[$hashtagRow['idLibro']][] = $hashtagRow['hashtag'];
                    }
                    $hashtagStmt->close();
                }
            }
        }
        */

        $conn->close();

    } catch (Exception $e) {
        $errorMessage = "Error al cargar publicaciones: " . $e->getMessage();
        error_log($errorMessage);
        
        // Información de debug para desarrollo
        if (isset($_GET['debug']) && $_GET['debug'] == '1') {
            $errorMessage .= "\nColumnas encontradas en Usuarios: " . implode(', ', $columnas_usuarios ?? []);
            $errorMessage .= "\nColumna seleccionada: " . ($columna_nombre_usuario ?? 'ninguna');
        }
    }

    // Funciones auxiliares
    function formatearPrecio($precio) {
        return ($precio == 0 || $precio === null) ? 'Gratis' : '$' . number_format($precio, 2);
    }

    function formatearFecha($fecha) {
        return empty($fecha) ? 'No especificada' : date('d/m/Y', strtotime($fecha));
    }

    function tiempoTranscurrido($fecha) {
        $tiempo = time() - strtotime($fecha);
        
        if ($tiempo < 60) return 'Hace un momento';
        if ($tiempo < 3600) return 'Hace ' . floor($tiempo/60) . ' minutos';
        if ($tiempo < 86400) return 'Hace ' . floor($tiempo/3600) . ' horas';
        if ($tiempo < 604800) return 'Hace ' . floor($tiempo/86400) . ' días';
        if ($tiempo < 2592000) return 'Hace ' . floor($tiempo/604800) . ' semanas';
        return 'Hace ' . floor($tiempo/2592000) . ' meses';
    }
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Inicio | RELEE</title>
    <link rel="stylesheet" href="../assets/css/home-styles.css">
    <link rel="stylesheet" href="../assets/css/chat-styles.css">
    <style>
        :root {
            --primary-brown: #6b4226;
            --secondary-brown: #8b5a3c;
            --light-brown: #d6c1b2;
            --cream-bg: #f8f6f3;
            --cream-light: #fffdfb;
            --green-primary: #a3b18a;
            --green-secondary: #588157;
            --green-dark: #3a5a40;
            --text-primary: #2c2016;
            --text-secondary: #6f5c4d;
            --text-muted: #888;
            --card-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
            --hover-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
            --border-radius: 20px;
            --transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, var(--cream-bg) 0%, #f0ede8 100%);
            color: var(--text-primary);
            margin: 0;
            padding-bottom: 80px;
            min-height: 100vh;
            position: relative;
        }

        /* Patrón de fondo sutil */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(circle at 20% 80%, rgba(163, 177, 138, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(107, 66, 38, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(214, 193, 178, 0.1) 0%, transparent 50%);
            z-index: -1;
        }

        .topbar {
            background: linear-gradient(135deg, #f5f0ea 0%, #ede6dd 100%);
            backdrop-filter: blur(10px);
            padding: 8px 25px;
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 15px;
            border-bottom: 1px solid rgba(211, 197, 184, 0.3);
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.05);
        }

        .topbar-icon {
            background: linear-gradient(135deg, var(--green-primary) 0%, var(--green-secondary) 100%);
            width: 35px;
            height: 35px;
            border-radius: 10px;
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: 0 3px 10px rgba(163, 177, 138, 0.3);
            position: relative;
            overflow: hidden;
        }

        .topbar-icon::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .topbar-icon:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(163, 177, 138, 0.4);
        }

        .topbar-icon:hover::before {
            left: 100%;
        }

        header {
            background: rgba(255, 253, 251, 0.95);
            backdrop-filter: blur(20px);
            padding: 25px 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(224, 214, 207, 0.5);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.08);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        /* DISEÑO DE LA IMAGEN DE NUESTRO LOGO */
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

        .search-bar {
            flex: 1;
            margin: 0 30px;
            display: flex;
            border: 2px solid transparent;
            border-radius: 50px;
            overflow: visible;
            background: linear-gradient(white, white) padding-box,
                        linear-gradient(135deg, var(--green-primary), var(--green-secondary)) border-box;
            box-shadow: 0 8px 32px rgba(163, 177, 138, 0.15);
            transition: var(--transition);
            position: relative;
        }

        .search-bar:focus-within {
            transform: translateY(-1px);
            box-shadow: 0 12px 40px rgba(163, 177, 138, 0.25);
        }

        .search-bar input {
            flex: 1;
            padding: 15px 25px;
            border: none;
            outline: none;
            font-size: 16px;
            background-color: transparent;
            color: var(--text-primary);
            border-radius: 50px 0 0 50px;
        }

        .search-bar input::placeholder {
            color: var(--text-muted);
        }

        .search-bar button {
            background: linear-gradient(135deg, var(--green-secondary) 0%, var(--green-dark) 100%);
            color: white;
            padding: 0 25px;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            border-radius: 0 50px 50px 0;
        }

        .search-bar button:hover {
            background: linear-gradient(135deg, var(--green-dark) 0%, #2d4732 100%);
        }

        .user-button {
            background: linear-gradient(135deg, var(--primary-brown) 0%, #5b4a3e 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: var(--transition);
            box-shadow: 0 6px 20px rgba(107, 66, 38, 0.3);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .user-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(107, 66, 38, 0.4);
        }

        /* Hero Section */
        .hero-section {
            background: rgba(255, 253, 252, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(224, 214, 207, 0.2);
            padding: 50px 20px;
            text-align: center;
            margin-bottom: 40px;
        }

        .hero-title {
            font-size: 3em;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary-brown) 0%, var(--secondary-brown) 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 0 0 20px 0;
            letter-spacing: -2px;
        }

        .hero-subtitle {
            color: var(--text-secondary);
            font-size: 1.2em;
            margin: 0 0 30px 0;
            font-weight: 500;
        }

        /* Galleria */
        .gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 30px;
            padding: 0 40px 60px 40px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .card {
            background: rgba(255, 253, 252, 0.95);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(255, 255, 255, 0.2);
            overflow: hidden;
            transition: var(--transition);
            position: relative;
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(163, 177, 138, 0.05) 0%, rgba(107, 66, 38, 0.05) 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
            border-radius: var(--border-radius);
        }

        .card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: var(--hover-shadow);
        }

        .card:hover::before {
            opacity: 1;
        }

        .card-image {
            width: 100%;
            height: 240px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--light-brown) 0%, #c4a68a 100%);
            position: relative;
        }

        .card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .card:hover .card-image img {
            transform: scale(1.1);
        }

        .card-image::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 60px;
            opacity: 0.3;
            filter: grayscale(0.2);
            display: var(--book-icon-display, block);
        }

        .card-image img + * {
            --book-icon-display: none;
        }

        .publication-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            background: linear-gradient(135deg, var(--green-secondary) 0%, var(--green-dark) 100%);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(88, 129, 87, 0.4);
        }

        .video-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: linear-gradient(135deg, var(--primary-brown) 0%, var(--secondary-brown) 100%);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 4px 15px rgba(107, 66, 38, 0.4);
        }

        .card-content {
            padding: 30px;
            position: relative;
            z-index: 2;
        }

        .card-title {
            font-size: 1.5em;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 12px;
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .card-author {
            color: var(--text-secondary);
            font-weight: 600;
            margin-bottom: 15px;
            font-size: 1.1em;
        }

        .card-publisher {
            color: var(--text-muted);
            font-size: 0.95em;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-price {
            display: inline-block;
            background: linear-gradient(135deg, var(--primary-brown) 0%, var(--secondary-brown) 100%);
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: 700;
            font-size: 1em;
            margin-bottom: 18px;
            box-shadow: 0 4px 12px rgba(107, 66, 38, 0.3);
        }

        .card-price.free {
            background: linear-gradient(135deg, var(--green-secondary) 0%, var(--green-dark) 100%);
            box-shadow: 0 4px 12px rgba(88, 129, 87, 0.3);
        }

        .card-description {
            color: var(--text-secondary);
            line-height: 1.6;
            margin-bottom: 20px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            font-size: 0.95em;
        }

        .card-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-top: 18px;
            border-top: 1px solid rgba(224, 214, 207, 0.3);
        }

        .publication-time {
            color: var(--text-muted);
            font-size: 0.9em;
            font-weight: 500;
        }

        .publisher-name {
            color: var(--green-dark);
            font-size: 0.95em;
            font-weight: 600;
        }

        .hashtags {
            margin-bottom: 25px;
        }

        .hashtag {
            display: inline-block;
            background: linear-gradient(135deg, rgba(163, 177, 138, 0.15) 0%, rgba(88, 129, 87, 0.15) 100%);
            color: var(--green-dark);
            padding: 6px 14px;
            border-radius: 15px;
            font-size: 0.85em;
            font-weight: 600;
            margin: 3px 6px 3px 0;
            border: 1px solid rgba(163, 177, 138, 0.2);
            transition: var(--transition);
        }

        .hashtag:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(163, 177, 138, 0.3);
            background: linear-gradient(135deg, rgba(163, 177, 138, 0.25) 0%, rgba(88, 129, 87, 0.25) 100%);
        }

        .card-actions {
            display: flex;
            gap: 12px;
        }

        .card-button {
            flex: 1;
            padding: 14px 18px;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            transition: var(--transition);
            text-align: center;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .view-button {
            background: linear-gradient(135deg, var(--green-secondary) 0%, var(--green-dark) 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(88, 129, 87, 0.3);
        }

        .view-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(88, 129, 87, 0.4);
        }

        .contact-button {
            background: linear-gradient(135deg, var(--primary-brown) 0%, var(--secondary-brown) 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(107, 66, 38, 0.3);
        }

        .contact-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(107, 66, 38, 0.4);
        }

        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 100px 20px;
            color: var(--text-secondary);
            background: rgba(255, 253, 252, 0.6);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius);
            border: 2px dashed rgba(163, 177, 138, 0.3);
        }

        .empty-state-icon {
            font-size: 5em;
            margin-bottom: 30px;
            opacity: 0.6;
        }

        .empty-state h3 {
            font-size: 2em;
            margin-bottom: 20px;
            color: var(--text-primary);
            font-weight: 700;
        }

        .empty-state p {
            font-size: 1.2em;
            margin-bottom: 40px;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.6;
        }

        /* Messages */
        .error-message, .success-message {
            background: linear-gradient(135deg, rgba(245, 101, 101, 0.9) 0%, rgba(229, 62, 62, 0.9) 100%);
            color: white;
            padding: 20px 30px;
            border-radius: var(--border-radius);
            margin: 20px auto;
            max-width: 1200px;
            text-align: center;
            font-weight: 600;
            box-shadow: var(--card-shadow);
            backdrop-filter: blur(10px);
        }

        .success-message {
            background: linear-gradient(135deg, rgba(163, 177, 138, 0.9) 0%, rgba(88, 129, 87, 0.9) 100%);
        }

        /* Bottom bar */
        .bottombar {
            position: fixed;
            bottom: 0;
            width: 100%;
            height: 65px;
            background: linear-gradient(135deg, rgba(245, 240, 234, 0.95) 0%, rgba(237, 230, 221, 0.95) 100%);
            backdrop-filter: blur(20px);
            display: flex;
            justify-content: space-around;
            align-items: center;
            border-top: 1px solid rgba(224, 214, 207, 0.3);
            box-shadow: 0 -6px 25px rgba(0, 0, 0, 0.1);
            z-index: 1000;
        }

        .bottom-button {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--green-primary) 0%, var(--green-secondary) 100%);
            color: white;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: 0 4px 15px rgba(163, 177, 138, 0.3);
            position: relative;
            overflow: hidden;
            text-decoration: none;
        }

        .bottom-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .bottom-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(163, 177, 138, 0.4);
        }

        .bottom-button:hover::before {
            left: 100%;
        }

        .bottom-button span {
            font-size: 10px;
            margin-top: 3px;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
            letter-spacing: 0.3px;
        }

        .bottom-button-wide {
            width: 110px;
            height: 50px;
            font-size: 11px;
            padding: 5px;
        }

        .bottom-button-wide span {
            font-size: 11px;
            margin-top: 0;
            text-align: center;
            line-height: 1.1;
        }

        .logout-button {
            background: linear-gradient(135deg, var(--primary-brown) 0%, #5b4a3e 100%);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: var(--transition);
            box-shadow: 0 2px 8px rgba(107, 66, 38, 0.3);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .logout-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 25px rgba(107, 66, 38, 0.4);
        }

        form.logout-form {
            margin: 0;
        }
        /* Enlace del video badge - SIN líneas ni subrayado */
a[href*="detalle_publicacion.php"] {
    text-decoration: none !important;
    border: none !important;
    outline: none !important;
    color: inherit !important;
    display: inline-block;
}

/* Video badge */
.video-badge {
    display: flex;
    align-items: center;
    gap: 4px;
    background-color: rgba(255, 254, 254, 1);
    color: white !important;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    user-select: none;
    text-decoration: none !important;
}

.video-badge:hover {
    background-color: rgba(255, 255, 255, 1);
    transform: scale(1.05);
    text-decoration: none !important;
}

.video-badge svg {
    flex-shrink: 0;
}

/* Asegurar que no herede estilos de enlaces */
a .video-badge {
    color: white !important;
    text-decoration: none !important;
}

        /* Debug info styles */
        .debug-info {
            background: rgba(255, 253, 252, 0.95);
            border: 2px solid #ed8936;
            border-radius: var(--border-radius);
            padding: 20px;
            margin: 20px auto;
            max-width: 1200px;
            color: var(--text-primary);
            backdrop-filter: blur(10px);
        }

        .debug-info h4 {
            color: #dd6b20;
            margin-top: 0;
        }

        .debug-info pre {
            background: rgba(247, 250, 252, 0.8);
            padding: 15px;
            border-radius: 12px;
            overflow-x: auto;
            font-size: 0.9em;
            border: 1px solid rgba(224, 214, 207, 0.3);
        }

        /* Animaciones */
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

        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideOutUp {
            from {
                opacity: 1;
                transform: translateY(0);
            }
            to {
                opacity: 0;
                transform: translateY(-30px);
            }
        }

        @keyframes ripple {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }

        .card {
            animation: fadeInUp 0.6s ease forwards;
        }

        .success-message, .error-message {
            animation: slideInDown 0.5s ease;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .gallery {
                grid-template-columns: 1fr;
                padding: 0 20px 60px 20px;
            }
            
            .hero-title {
                font-size: 2.2em;
            }
            
            .hero-subtitle {
                font-size: 1.1em;
            }
            
            .stats-banner {
                flex-direction: column;
                gap: 20px;
                align-items: center;
            }
            
            .stat-item {
                width: 100%;
                max-width: 300px;
                flex-direction: row;
                justify-content: space-between;
                padding: 20px 25px;
            }
            
            .card-actions {
                flex-direction: column;
                gap: 10px;
            }
            
            header {
                flex-direction: column;
                gap: 20px;
                padding: 20px;
            }
            
            .search-bar {
                width: 100%;
                margin: 0;
            }

            .topbar {
                padding: 8px 15px;
                gap: 10px;
            }

            .logo {
                font-size: 24px;
            }

            .bottom-button-wide {
                width: 95px;
                font-size: 10px;
            }

            .bottom-button-wide span {
                font-size: 10px;
            }

            .card-content {
                padding: 25px;
            }

            .card-title {
                font-size: 1.3em;
            }
        }

        @media (max-width: 480px) {
            .hero-section {
                padding: 40px 15px;
            }
            
            .hero-title {
                font-size: 1.8em;
            }
            
            .gallery {
                padding: 0 15px 60px 15px;
            }
            
            .card-content {
                padding: 20px;
            }
            
            .stat-item {
                padding: 15px 20px;
            }
        }

        /* Adicionales */
        .no-results-message {
            grid-column: 1 / -1;
            background: rgba(255, 253, 252, 0.95);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius);
            padding: 60px 40px;
            text-align: center;
            border: 2px dashed rgba(163, 177, 138, 0.3);
            animation: fadeInUp 0.5s ease forwards;
            color: var(--text-secondary);
        }

        .no-results-message h3 {
            color: var(--text-primary);
            font-weight: 700;
            margin-bottom: 15px;
        }

        /* Efectos hover */
        .hashtag:hover {
            background: linear-gradient(135deg, rgba(163, 177, 138, 0.25) 0%, rgba(88, 129, 87, 0.25) 100%);
        }

        /* Loading states */
        .card-button {
            position: relative;
            overflow: hidden;
        }

        @keyframes spinning {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .spinning {
            animation: spinning 1s linear infinite;
        }

        /* Lazy loading */
        .lazy {
            opacity: 0;
            transition: opacity 0.3s;
        }

        /* Scrollbar personalizado */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(224, 214, 207, 0.2);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, var(--green-primary) 0%, var(--green-secondary) 100%);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, var(--green-secondary) 0%, var(--green-dark) 100%);
        }
        /* Estilos para el chatbot con tus colores */
.chatbot-icon {
    background: linear-gradient(135deg, var(--green-primary) 0%, var(--green-secondary) 100%) !important;
    box-shadow: 0 3px 10px rgba(163, 177, 138, 0.3) !important;
    position: relative;
}

.chatbot-icon:hover {
    box-shadow: 0 8px 25px rgba(163, 177, 138, 0.4) !important;
}

/* Animación de parpadeo para los ojos verdes */
@keyframes robotBlink {
    0%, 90%, 100% { opacity: 1; }
    95% { opacity: 0.3; }
}

.chatbot-icon svg circle[fill="#a3b18a"] {
    animation: robotBlink 3s infinite;
}

/* Create Post Section */
.create-post-section {
    background: rgba(255, 253, 252, 0.95);
    backdrop-filter: blur(20px);
    border-radius: var(--border-radius);
    box-shadow: var(--card-shadow);
    border: 1px solid rgba(255, 255, 255, 0.2);
    padding: 20px;
    margin: 0 auto 30px auto;
    max-width: 800px;
    display: flex;
    gap: 15px;
    align-items: center;
}

.user-avatar-small {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--light-brown);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
}

.create-post-input {
    flex: 1;
    background: #f0ede8;
    border-radius: 20px;
    padding: 10px 20px;
    color: var(--text-secondary);
    cursor: pointer;
    transition: background 0.3s;
}

.create-post-input:hover {
    background: #e0ddd8;
}

.create-post-actions {
    display: flex;
    gap: 10px;
}

.action-btn {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 8px 15px;
    border-radius: 15px;
    border: none;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.9em;
    transition: transform 0.2s;
}

.action-btn:hover {
    transform: translateY(-2px);
}

.btn-status {
    background: rgba(163, 177, 138, 0.2);
    color: var(--green-dark);
}

.btn-book {
    background: rgba(107, 66, 38, 0.1);
    color: var(--primary-brown);
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 2000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    backdrop-filter: blur(5px);
    overflow-y: auto;
}

.modal-content {
    background-color: #fefefe;
    margin: 5% auto;
    padding: 0;
    border: 1px solid #888;
    width: 90%;
    max-width: 800px;
    border-radius: 20px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    animation: slideInDown 0.3s ease-out;
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h2 {
    margin: 0;
    color: var(--primary-brown);
}

.close-modal {
    color: #aaa;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    transition: color 0.2s;
}

.close-modal:hover {
    color: var(--primary-brown);
}

.modal-body {
    padding: 20px;
    max-height: 70vh;
    overflow-y: auto;
}

.modal-footer {
    padding: 20px;
    border-top: 1px solid #eee;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

/* Form Styles inside Modal */
.modal-form-group {
    margin-bottom: 15px;
}

.modal-form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: var(--text-primary);
}

.modal-form-group input,
.modal-form-group textarea,
.modal-form-group select {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-family: inherit;
}

.modal-form-group textarea {
    resize: vertical;
    min-height: 100px;
}

.search-results-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    margin-top: 5px;
    max-height: 300px;
    overflow-y: auto;
    display: none;
    z-index: 1000;
}
.search-result-item {
    display: flex;
    align-items: center;
    padding: 10px 15px;
    cursor: pointer;
    transition: background 0.2s;
    text-decoration: none;
    color: inherit;
}
.search-result-item:hover {
    background: #f5f5f5;
}
.search-result-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    margin-right: 12px;
    object-fit: cover;
    background: #ddd;
}
.search-result-info {
    display: flex;
    flex-direction: column;
}
.search-result-name {
    font-weight: 600;
    font-size: 0.95em;
    color: #333;
}
.search-result-username {
    font-size: 0.85em;
    color: #666;
}

    </style>
</head>

<body>
    <div class="topbar">
        <div class="topbar-icon chat-icon" title="Chat">
            <svg width="18" height="18" fill="white" viewBox="0 0 24 24">
                <!-- Cabeza del robot -->
                <rect x="6" y="4" width="12" height="10" rx="2" ry="2"/>
                <!-- Antenas -->
                <circle cx="9" cy="2" r="1"/>
                <circle cx="15" cy="2" r="1"/>
                <line x1="9" y1="3" x2="9" y2="4" stroke="white" stroke-width="1"/>
                <line x1="15" y1="3" x2="15" y2="4" stroke="white" stroke-width="1"/>
                <!-- Ojos con colores de tu tema -->
                <circle cx="9.5" cy="8" r="1.5" fill="#a3b18a"/>
                <circle cx="14.5" cy="8" r="1.5" fill="#a3b18a"/>
                <circle cx="9.5" cy="8" r="0.8" fill="white"/>
                <circle cx="14.5" cy="8" r="0.8" fill="white"/>
                <!-- Boca -->
                <rect x="10" y="11" width="4" height="1" rx="0.5"/>
                <!-- Cuerpo -->
                <rect x="8" y="14" width="8" height="6" rx="1"/>
                <!-- Brazos -->
                <rect x="4" y="16" width="3" height="1" rx="0.5"/>
                <rect x="17" y="16" width="3" height="1" rx="0.5"/>
                <!-- Piernas -->
                <rect x="9" y="20" width="2" height="2" rx="1"/>
                <rect x="13" y="20" width="2" height="2" rx="1"/>
            </svg>
        </div>

        <!-- Wrapper for Notifications to avoid overflow:hidden clipping -->
        <div style="position: relative; display: flex; align-items: center;">
            <div class="topbar-icon" title="Notificaciones" id="notif-btn">
                <svg width="18" height="18" fill="white" viewBox="0 0 24 24">
                    <path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6v-5c0-3.07-1.63-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.64 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2zm-2 1H8v-6c0-2.48 1.51-4.5 4-4.5s4 2.02 4 4.5v6z"/>
                </svg>
            </div>
            <span id="notif-badge" class="notification-badge">0</span>
            
            <div id="notif-dropdown">
                <div class="notif-header">
                    <span>Notificaciones</span>
                    <span style="font-size: 0.8em; color: var(--green-secondary); cursor: pointer;" onclick="markAllRead()">Marcar leídas</span>
                </div>
                <div id="notif-list"></div>
            </div>
        </div>

        <!-- Wrapper for Chat to avoid overflow:hidden clipping -->
        <div style="position: relative; display: flex; align-items: center;">
            <div class="topbar-icon" title="Chat">
                <a href="chat/chat.php" class="bottom-button" title="Chat" style="display: flex; align-items: center; justify-content: center; width: 100%; height: 100%;">
                    <svg width="16" height="16" fill="white" viewBox="0 0 24 24">
                        <path d="M20 2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h4l4 4 4-4h4c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/>
                    </svg>
                </a>
            </div>
            <span id="msg-badge" class="notification-badge" style="top: -5px; right: -5px;">0</span>
        </div>

        <div class="topbar-icon" title="Perfil">
            <a href="auth/perfil.php" class="topbar-icon" title="Mi Perfil">
                <svg width="16" height="16" fill="white" viewBox="0 0 24 24">
                    <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                </svg>
            </a>
        </div>

        <form action="auth/logout.php" method="post" class="logout-form">
            <button type="submit" class="logout-button">
                <svg width="14" height="14" fill="white" viewBox="0 0 24 24">
                    <path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.59L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/>
                </svg>
                Cerrar sesión
            </button>
        </form>
    </div>

    <?php include '../includes/chat-component.php'; ?>

    <header>
         <div class="logo-container">
        <div class="logo-icon">
            <img src="../assets/images/REELEE.jpeg" alt="RELEE Logo" class="logo-image" />
        </div>
    </div>
    
    <div class="search-bar" style="position: relative;">
        <input type="text" id="search-input" placeholder="Buscar usuarios..." autocomplete="off">
        <button type="button" id="search-button">
            <svg width="18" height="18" fill="white" viewBox="0 0 24 24">
                <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
            </svg>
        </button>
        <div id="search-results" class="search-results-dropdown"></div>
    </div>
</header>
    <div class="hero-section">
    
    </div>

    <div class="create-post-section">
        <div class="user-avatar-small">
            <?php echo substr($_SESSION['usuario'] ?? 'U', 0, 1); ?>
        </div>
        <div class="create-post-input" onclick="openModal('statusModal')">
            ¿Qué estás pensando, <?php echo htmlspecialchars($_SESSION['usuario'] ?? 'Usuario'); ?>?
        </div>
        <div class="create-post-actions">
            <button class="action-btn btn-status" onclick="openModal('statusModal')">
                <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M21 6h-2v9H6v2c0 .55.45 1 1 1h11l4 4V7c0-.55-.45-1-1-1zm-4 6V3c0-.55-.45-1-1-1H3c-.55 0-1 .45-1 1v14l4-4h10c.55 0 1-.45 1-1z"/></svg>
                Estado
            </button>
        </div>
    </div>

    <!-- Status Modal -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Crear Publicación</h2>
                <span class="close-modal" onclick="closeModal('statusModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form id="statusForm">
                    <div class="modal-form-group">
                        <textarea name="contenido" placeholder="¿Qué estás pensando?" required></textarea>
                    </div>
                    <div class="modal-form-group">
                        <label>Imagen (opcional)</label>
                        <input type="file" name="imagen" accept="image/*">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="cancel-button" onclick="closeModal('statusModal')">Cancelar</button>
                <button class="submit-button" onclick="submitStatus()">Publicar</button>
            </div>
        </div>
    </div>

    <!-- Mensajes de éxito y error -->
    <?php if (isset($_SESSION['mensaje_exito'])): ?>
        <div class="success-message">
            ✅ <?php echo htmlspecialchars($_SESSION['mensaje_exito']); unset($_SESSION['mensaje_exito']); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errorMessage)): ?>
        <div class="error-message">
            ❌ <?php echo htmlspecialchars($errorMessage); ?>
        </div>
    <?php endif; ?>

    <!-- Información de debug (solo si hay error) -->
    <?php if (!empty($errorMessage) && isset($_GET['debug'])): ?>
        <div class="debug-info">
            <h4>🔧 Información de Debug</h4>
            <p><strong>Columnas encontradas en tabla Usuarios:</strong></p>
            <pre><?php echo isset($columnas_usuarios) ? implode(', ', $columnas_usuarios) : 'No se pudieron obtener'; ?></pre>
            <p><strong>Columna seleccionada para nombre:</strong> <?php echo $columna_nombre_usuario ?? 'ninguna'; ?></p>
            <p><strong>Usando ID como nombre:</strong> <?php echo ($usar_id_como_nombre ?? false) ? 'Sí' : 'No'; ?></p>
            <p><small>💡 Para quitar este mensaje, elimina ?debug=1 de la URL</small></p>
        </div>
    <?php endif; ?>

    <main class="gallery">
        <?php if (empty($publicaciones)): ?>
            <div class="empty-state">
                <div class="empty-state-icon"></div>
                <h3>¡Bienvenido a ReL!</h3>
            </div>
        <?php else: ?>
            <?php foreach ($publicaciones as $index => $publicacion): ?>
                <article class="card" style="animation-delay: <?php echo $index * 0.1; ?>s;">
                    <?php if (isset($publicacion['type']) && $publicacion['type'] === 'status'): ?>
                        <!-- Diseño para Estados -->
                        <div class="card-content">
                            <div class="card-meta" style="border-top: none; margin-bottom: 15px; padding-top: 0;">
                                <div class="publisher-name" style="font-size: 1.1em; display: flex; justify-content: space-between; align-items: center; width: 100%;">
                                    <a href="perfil_usuario.php?id=<?php echo $publicacion['idUsuario']; ?>" style="text-decoration: none; color: inherit; display: flex; align-items: center;">
                                        <div class="user-avatar-small" style="display: inline-flex; margin-right: 10px; vertical-align: middle; width: 40px; height: 40px; border-radius: 50%; overflow: hidden; background: #e0e0e0; justify-content: center; align-items: center;">
                                            <?php if (!empty($publicacion['fotoPerfil']) && $publicacion['fotoPerfil'] !== 'default-avatar.png'): ?>
                                                <img src="../uploads/<?php echo htmlspecialchars($publicacion['fotoPerfil']); ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                                <span style="display: none; font-weight: bold; color: #555; font-size: 1.2em;"><?php echo substr($publicacion['nombreUsuario'], 0, 1); ?></span>
                                            <?php else: ?>
                                                <span style="font-weight: bold; color: #555; font-size: 1.2em;"><?php echo substr($publicacion['nombreUsuario'], 0, 1); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php echo htmlspecialchars($publicacion['nombreUsuario']); ?>
                                    </a>
                                    <?php if ($publicacion['idUsuario'] == $userId): ?>
                                        <button onclick="deletePost(<?php echo $publicacion['id']; ?>, this)" style="background: none; border: none; cursor: pointer; color: #999; font-size: 1.2em; padding: 5px;" title="Eliminar publicación">
                                            🗑️
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <div class="publication-time">
                                    <?php echo tiempoTranscurrido($publicacion['fechaCreacion']); ?>
                                </div>
                            </div>

                            <div class="card-description" style="font-size: 1.1em; color: var(--text-primary); margin-bottom: 15px; -webkit-line-clamp: unset;">
                                <?php echo nl2br(htmlspecialchars($publicacion['descripcion'])); ?>
                            </div>

                            <?php if (!empty($publicacion['linkImagen1'])): ?>
                                <div class="card-image" style="border-radius: 15px; margin-bottom: 15px;">
                                    <img src="../<?php echo htmlspecialchars($publicacion['linkImagen1']); ?>" 
                                         alt="Imagen de estado" 
                                         loading="lazy"
                                         style="border-radius: 15px;">
                                </div>
                            <?php endif; ?>

                            <!-- Social Actions -->
                            <div class="social-actions" style="display: flex; gap: 15px; padding-top: 10px; border-top: 1px solid #eee;">
                                <button class="action-btn like-btn <?php echo ($publicacion['likedByMe'] > 0) ? 'active' : ''; ?>" 
                                        onclick="toggleLike(<?php echo $publicacion['id']; ?>, this)"
                                        style="background: none; border: none; cursor: pointer; display: flex; align-items: center; gap: 5px; color: <?php echo ($publicacion['likedByMe'] > 0) ? '#e74c3c' : 'var(--text-secondary)'; ?>;">
                                    <svg width="20" height="20" fill="<?php echo ($publicacion['likedByMe'] > 0) ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                                    </svg>
                                    <span class="like-count"><?php echo $publicacion['totalLikes']; ?></span>
                                </button>
                                
                                <button class="action-btn comment-btn" 
                                        onclick="toggleComments(<?php echo $publicacion['id']; ?>)"
                                        style="background: none; border: none; cursor: pointer; display: flex; align-items: center; gap: 5px; color: var(--text-secondary);">
                                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
                                    </svg>
                                    <span class="comment-count"><?php echo $publicacion['totalComentarios']; ?></span>
                                </button>

                                <button class="action-btn share-btn" 
                                        onclick="sharePost(<?php echo $publicacion['id']; ?>)"
                                        style="background: none; border: none; cursor: pointer; display: flex; align-items: center; gap: 5px; color: var(--text-secondary);">
                                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/>
                                        <polyline points="16 6 12 2 8 6"/>
                                        <line x1="12" y1="2" x2="12" y2="15"/>
                                    </svg>
                                    Compartir
                                </button>
                            </div>

                            <!-- Comments Section (Hidden by default) -->
                            <div id="comments-<?php echo $publicacion['id']; ?>" class="comments-section" style="display: none; margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">
                                <div class="comments-list" id="comments-list-<?php echo $publicacion['id']; ?>">
                                    <!-- Comments will be loaded here via AJAX -->
                                </div>
                                <div class="comment-input-wrapper" style="display: flex; gap: 10px; margin-top: 10px;">
                                    <input type="text" id="comment-input-<?php echo $publicacion['id']; ?>" 
                                           placeholder="Escribe un comentario..." 
                                           style="flex: 1; padding: 8px 12px; border: 1px solid #ddd; border-radius: 20px; outline: none;">
                                    <button onclick="postComment(<?php echo $publicacion['id']; ?>)"
                                            style="background: #4a90e2; color: white; border: none; padding: 8px 15px; border-radius: 20px; cursor: pointer; font-weight: 600; transition: background 0.2s;">
                                        Enviar
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Diseño para Libros (Existente) -->
                        <div class="card-image">
                            <?php if (!empty($publicacion['linkImagen1'])): ?>
                                <img src="../uploads/<?php echo htmlspecialchars($publicacion['linkImagen1']); ?>" 
                                     alt="<?php echo htmlspecialchars($publicacion['titulo']); ?>" 
                                     loading="lazy">
                            <?php endif; ?>
                            
                            <div class="publication-badge">
                                ⏰ <?php echo tiempoTranscurrido($publicacion['fechaCreacion']); ?>
                            </div>
                            
                            <?php if (!empty($publicacion['linkVideo'])): ?>
                                <a href="http://143.198.55.50/ReLee/pages/products/detalle_publicacion.php?id=11?id=<?= $publicacion['id'] ?>">
                                <div class="video-badge">
                                    <svg width="12" height="12" fill="white" viewBox="0 0 24 24">
                                        <path d="M8 5v14l11-7z"/>
                                    </svg>
                                    Video
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="card-content">
                            <h3 class="card-title"><?php echo htmlspecialchars($publicacion['titulo']); ?></h3>
                            <div class="card-author">✍️ <?php echo htmlspecialchars($publicacion['autor']); ?></div>
                            
                            <?php if (!empty($publicacion['editorial'])): ?>
                                <div class="card-publisher">
                                    <svg width="14" height="14" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                    </svg>
                                    <?php echo htmlspecialchars($publicacion['editorial']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="card-price <?php echo ($publicacion['precio'] == 0) ? 'free' : ''; ?>">
                                <?php echo formatearPrecio($publicacion['precio']); ?>
                            </div>

                            <?php if (!empty($publicacion['descripcion'])): ?>
                                <div class="card-description">
                                    <?php echo htmlspecialchars($publicacion['descripcion']); ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($hashtags[$publicacion['idLibro']])): ?>
                                <div class="hashtags">
                                    <?php foreach (array_slice($hashtags[$publicacion['idLibro']], 0, 3) as $hashtag): ?>
                                        <span class="hashtag">#<?php echo htmlspecialchars($hashtag); ?></span>
                                    <?php endforeach; ?>
                                    <?php if (count($hashtags[$publicacion['idLibro']]) > 3): ?>
                                        <span class="hashtag">+<?php echo count($hashtags[$publicacion['idLibro']]) - 3; ?> más</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <div class="card-meta">
                                <div class="publication-time">
                                    📅 <?php echo formatearFecha($publicacion['fechaCreacion']); ?>
                                </div>
                                <div class="publisher-name">
                                    <a href="perfil_usuario.php?id=<?php echo $publicacion['idUsuario']; ?>" style="text-decoration: none; color: inherit;">
                                        👤 <?php echo htmlspecialchars($publicacion['nombreUsuario']); ?>
                                    </a>
                                </div>
                            </div>

                            <div class="card-actions">
                                <a href="products/detalle_publicacion.php?id=<?php echo htmlspecialchars($publicacion['idPublicacion']); ?>" 
                                class="card-button view-button">
                                    <svg width="16" height="16" fill="white" viewBox="0 0 24 24">
                                        <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                                    </svg>
                                    Ver Detalles
                                </a>
                                <button class="card-button contact-button" 
                                        onclick="abrirChat(<?php echo $publicacion['idUsuario']; ?>, '<?php echo htmlspecialchars(addslashes($publicacion['nombreUsuario'])); ?>')">
                                    <svg width="16" height="16" fill="white" viewBox="0 0 24 24">
                                        <path d="M20 2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h4l4 4 4-4h4c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/>
                                    </svg>
                                    Contactar
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>

    <script src="../assets/js/home-script.js?v=2.0"></script>
    <script src="../assets/js/chat-script.js?v=2.0"></script>
    <script>


        const searchStyles = document.createElement('style');
        searchStyles.textContent = `
            .no-results-message {
                grid-column: 1 / -1;
                background: rgba(255, 253, 252, 0.95);
                backdrop-filter: blur(20px);
                border-radius: var(--border-radius);
                padding: 60px 40px;
                text-align: center;
                border: 2px dashed rgba(163, 177, 138, 0.3);
                animation: fadeInUp 0.5s ease forwards;
                color: var(--text-secondary);
                margin: 20px 0;
            }
            
            .no-results-message h3 {
                color: var(--text-primary);
                font-weight: 700;
                margin-bottom: 15px;
                font-size: 1.8em;
            }
            
            .no-results-message p {
                margin: 10px 0;
                line-height: 1.6;
            }
            
            .no-results-message strong {
                color: var(--green-dark);
                font-weight: 700;
            }
        `;
        document.head.appendChild(searchStyles);

        // Funcion abrir Chat
        function abrirChat(userId, userName) {
            // Prevenir múltiples clics
            if (window.chatProcessing) {
                return false;
            }
            
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            // Marcar como procesando
            window.chatProcessing = true;
            
            const button = event?.target.closest('.contact-button');
            if (button) {
                if (button.disabled) {
                    window.chatProcessing = false;
                    return false;
                }
                
                // Deshabilitar botón inmediatamente
                button.disabled = true;
                button.style.pointerEvents = 'none';
                button.style.opacity = '0.6';
                
                const originalHTML = button.innerHTML;
                button.innerHTML = `
                    <svg width="16" height="16" fill="white" viewBox="0 0 24 24" class="spinning">
                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" opacity="0.3"/>
                        <path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                    </svg>
                    Conectando...
                `;
                
                // Función para restaurar botón
                const restoreButton = () => {
                    if (button) {
                        button.disabled = false;
                        button.style.pointerEvents = 'auto';
                        button.style.opacity = '1';
                        button.innerHTML = originalHTML;
                    }
                    window.chatProcessing = false;
                };
                
                // Timeout de seguridad
                const timeout = setTimeout(restoreButton, 10000);
                
                fetch('../api/create_conversation.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'other_user_id=' + encodeURIComponent(userId)
                })
                .then(response => {
                    clearTimeout(timeout);
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // Navegar al chat
                        window.location.href = 'chat/chat.php?conversacion=' + data.conversationId;
                    } else {
                        restoreButton();
                        alert('Error al abrir el chat: ' + data.message);
                    }
                })
                .catch(error => {
                    clearTimeout(timeout);
                    restoreButton();
                    console.error('Error:', error);
                    alert('Error al conectar con el servidor');
                });
            }
            
            return false;
        }

        let lastGlobalClick = 0;
        document.addEventListener('click', function(e) {
            const now = Date.now();
            if (now - lastGlobalClick < 300) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }
            lastGlobalClick = now;
        }, true);

        let lastTouchEnd = 0;
        document.addEventListener('touchend', function(event) {
            const now = Date.now();
            if (now - lastTouchEnd <= 300) {
                event.preventDefault();
            }
            lastTouchEnd = now;
        }, false);
        
        let enviandoMensaje = false;

        document.getElementById('sendButton').addEventListener('click', function() {
        if (this.dataset.sending === 'true') {
            return false;
        }
        this.dataset.sending = 'true';

        // Prevenir envío múltiple
        if (enviandoMensaje) {
            return false;
        }
        
        const messageText = document.getElementById('messageText');
        const content = messageText.value.trim();
        
        if (!content || !window.currentConversationId) {
            return false;
        }
        
        // Marcar como enviando
        enviandoMensaje = true;
        
        // Deshabilitar botón y campo de texto
        this.disabled = true;
        messageText.disabled = true;
        
        // Cambiar icono a loading
        const originalIcon = this.innerHTML;
        this.innerHTML = `
            <svg width="20" height="20" fill="white" viewBox="0 0 24 24" class="spinning">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" opacity="0.3"/>
                <path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
            </svg>
        `;
        
        // Limpiar campo inmediatamente para evitar reenvío
        messageText.value = '';
        
        // Función para restaurar elementos
        function restaurarElementos() {
            enviandoMensaje = false;
            document.getElementById('sendButton').disabled = false;
            messageText.disabled = false;
            document.getElementById('sendButton').innerHTML = originalIcon;
            messageText.focus();
        }
        
        fetch('../../api/send_message.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `conversacion_id=${window.currentConversationId}&contenido=${encodeURIComponent(content)}`
        })
        .then(response => response.json())
        .then(data => {
            restaurarElementos();
            if (data.success) {
                loadMessages(window.currentConversationId);
            } else {
                // Si hay error, restaurar el texto
                messageText.value = content;
                alert('Error al enviar mensaje: ' + data.message);
            }
        })
        .catch(error => {
            this.dataset.sending = 'false';
            restaurarElementos();
            messageText.value = content; // Restaurar texto en caso de error
            console.error('Error:', error);
            alert('Error de conexión');
        });
    });

    // Prevenir envio con enter en moviles
    document.getElementById('messageText').addEventListener('keypress', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            
            // Solo enviar si no estamos ya enviando
            if (!enviandoMensaje) {
                document.getElementById('sendButton').click();
            }
        }
    });

    // Debounce para manejar clicks rápidos
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Aplicardebounce a todos los notones
    document.addEventListener('DOMContentLoaded', function() {
        const style = document.createElement('style');
        style.textContent = `
            .spinning {
                animation: spin 1s linear infinite !important;
            }
            
            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
            
            .contact-button:disabled {
                cursor: not-allowed !important;
                pointer-events: none !important;
            }
            
            @media (max-width: 768px) {
                .contact-button:active {
                    transform: scale(0.95);
                }
                
                .contact-button, .card-button {
                    -webkit-user-select: none;
                    -moz-user-select: none;
                    -ms-user-select: none;
                    user-select: none;
                    -webkit-tap-highlight-color: transparent;
                    min-height: 44px;
                }
            }
        `;
        document.head.appendChild(style);
        
        // Limpiar conversaciones duplicadas al cargar
        cleanupDuplicateConversations();
    });

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.contact-button').forEach(button => {
            // Clonar el botón para remover todos los event listeners
            const newButton = button.cloneNode(true);
            button.parentNode.replaceChild(newButton, button);
            
            // Agregar nuevo listener con protección
            newButton.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                if (this.disabled || window.chatProcessing) {
                    return false;
                }
                
                // Extraer datos del onclick original si existe
                const onclickAttr = this.getAttribute('onclick');
                if (onclickAttr) {
                    const userIdMatch = onclickAttr.match(/abrirChat\((\d+)/);
                    const userNameMatch = onclickAttr.match(/'([^']+)'/);
                    
                    if (userIdMatch && userNameMatch) {
                        const userId = parseInt(userIdMatch[1]);
                        const userName = userNameMatch[1];
                        
                        // Crear evento simulado para abrirChat
                        window.event = e;
                        abrirChat(userId, userName);
                    }
                }
                
                return false;
            });
            
            // Limpiar onclick original para evitar conflictos
            newButton.removeAttribute('onclick');
        });
        
        console.log('✅ Event handlers de chat optimizados para móvil');
    });

    function cleanupDuplicateConversations() {
        fetch('../api/clean_duplicates.php', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.cleaned > 0) {
                console.log(`✅ Limpieza automática: ${data.cleaned} conversaciones duplicadas eliminadas`);
            }
        })
        .catch(error => {
            console.log('Limpieza automática no disponible:', error);
        });
    }

    // FeedBack en moviles
    function mejorarFeedbackMovil() {
        // Agregar estilos para mejor feedback táctil
        const style = document.createElement('style');
        style.textContent = `
            /* Mejores estados hover para móviles */
            @media (max-width: 768px) {
                .contact-button:active {
                    transform: scale(0.95);
                    background: linear-gradient(135deg, #5a4a3e 0%, #4a3a2e 100%) !important;
                }
                
                .contact-button.processing {
                    background: linear-gradient(135deg, #888 0%, #666 100%) !important;
                    cursor: not-allowed;
                    pointer-events: none;
                }
                
                .spinning {
                    animation: spin 1s linear infinite;
                }
                
                @keyframes spin {
                    from { transform: rotate(0deg); }
                    to { transform: rotate(360deg); }
                }
                
                /* Prevenir selección de texto en botones */
                .contact-button, .card-button {
                    -webkit-user-select: none;
                    -moz-user-select: none;
                    -ms-user-select: none;
                    user-select: none;
                    -webkit-tap-highlight-color: transparent;
                }
                
                /* Mejorar área de toque */
                .contact-button {
                    min-height: 44px;
                    min-width: 44px;
                }
            }
        `;
        document.head.appendChild(style);
    }

    // Ejecutar mejoras al cargar
    mejorarFeedbackMovil();

    // Limpiar conversaciones automaticamente
    function limpiarConversacionesDuplicadas() {
        fetch('../api/cleanup_conversations.php', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.cleaned > 0) {
                console.log(`✅ Se limpiaron ${data.cleaned} conversaciones duplicadas`);
            }
        })
        .catch(error => {
            console.log('Error en limpieza automática:', error);
        });
    }

    console.log('✅ Protección contra duplicados en móviles activada');

    document.addEventListener('DOMContentLoaded', function() {
        // Funcionalidad de búsqueda en tiempo real
        const searchInput = document.querySelector('.search-bar input');
        if (searchInput) {
            let searchTimeout;
                
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    const searchTerm = this.value.toLowerCase().trim();
                    const cards = document.querySelectorAll('.card');
                    let visibleCount = 0;
                        
                    cards.forEach(card => {
                        const title = card.querySelector('.card-title').textContent.toLowerCase();
                        const author = card.querySelector('.card-author').textContent.toLowerCase();
                        const description = card.querySelector('.card-description')?.textContent.toLowerCase() || '';
                        const hashtags = Array.from(card.querySelectorAll('.hashtag')).map(tag => tag.textContent.toLowerCase()).join(' ');
                        const publisher = card.querySelector('.card-publisher')?.textContent.toLowerCase() || '';
                            
                        const matches = title.includes(searchTerm) || 
                            author.includes(searchTerm) || 
                            description.includes(searchTerm) ||
                            hashtags.includes(searchTerm) ||
                            publisher.includes(searchTerm);
                            
                        if (matches || searchTerm === '') {
                            card.style.display = 'block';
                            card.style.animation = 'fadeInUp 0.5s ease forwards';
                                visibleCount++;
                        } else {
                            card.style.display = 'none';
                        }
                    });
                        
                    // Mostrar mensaje si no hay resultados
                    let noResultsMsg = document.querySelector('.no-results-message');
                    if (visibleCount === 0 && searchTerm !== '') {
                        if (!noResultsMsg) {
                            noResultsMsg = document.createElement('div');
                            noResultsMsg.className = 'no-results-message empty-state';
                            noResultsMsg.innerHTML = `
                                <div class="empty-state-icon">🔍</div>
                                    <h3>No se encontraron resultados</h3>
                                    <p>No encontramos publicaciones que coincidan con "<strong>${searchTerm}</strong>"</p>
                                    <p>Intenta con otros términos de búsqueda.</p>
                                `;
                            document.querySelector('.gallery').appendChild(noResultsMsg);
                        }
                    } else if (noResultsMsg) {
                        noResultsMsg.remove();
                    }
                        
                    // Actualizar estadísticas en tiempo real
                    updateStats(visibleCount, searchTerm);
                }, 300);
            });
        }

        // Función para actualizar estadísticas
        function updateStats(visibleCount, searchTerm) {
            const statsBanner = document.querySelector('.stats-banner');
            if (statsBanner && searchTerm !== '') {
                const originalStats = statsBanner.cloneNode(true);
                originalStats.setAttribute('data-original', 'true');
                
                if (!document.querySelector('[data-original="true"]')) {
                    statsBanner.parentNode.insertBefore(originalStats, statsBanner);
                    originalStats.style.display = 'none';
                }
                    
                    const visibleCards = Array.from(document.querySelectorAll('.card')).filter(card => 
                        card.style.display !== 'none'
                    );
                    
                    const freeCount = visibleCards.filter(card => 
                        card.querySelector('.card-price').classList.contains('free')
                    ).length;
                    
                    const videoCount = visibleCards.filter(card => 
                        card.querySelector('.video-badge')
                    ).length;
                    
                    const uniqueUsers = new Set(visibleCards.map(card => 
                        card.querySelector('.publisher-name').textContent
                    )).size;
                    
                    statsBanner.innerHTML = `
                        <div class="stat-item">
                            <span class="stat-number">${visibleCount}</span>
                            <span class="stat-label">Encontrados</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number">${freeCount}</span>
                            <span class="stat-label">Gratis</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number">${videoCount}</span>
                            <span class="stat-label">Con Video</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number">${uniqueUsers}</span>
                            <span class="stat-label">Usuarios</span>
                        </div>
                    `;
                } else if (searchTerm === '') {
                    const originalStats = document.querySelector('[data-original="true"]');
                    if (originalStats) {
                        statsBanner.innerHTML = originalStats.innerHTML;
                        originalStats.remove();
                    }
                }
            }

            // Animación de las estadísticas con conteo
            const observerOptions = {
                threshold: 0.5,
                rootMargin: '0px 0px -10px 0px'
            };

            const statsObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const statNumbers = entry.target.querySelectorAll('.stat-number');
                        statNumbers.forEach(statNumber => {
                            const finalValue = parseInt(statNumber.textContent);
                            let currentValue = 0;
                            const increment = Math.ceil(finalValue / 20);
                            
                            const countAnimation = setInterval(() => {
                                currentValue += increment;
                                if (currentValue >= finalValue) {
                                    statNumber.textContent = finalValue;
                                    clearInterval(countAnimation);
                                } else {
                                    statNumber.textContent = currentValue;
                                }
                            }, 50);
                        });
                        
                        statsObserver.unobserve(entry.target);
                    }
                });
            }, observerOptions);

            // Observar el banner de estadísticas
            const statsBanner = document.querySelector('.stats-banner');
            if (statsBanner) {
                statsObserver.observe(statsBanner);
            }

            // Animación de entrada escalonada para las tarjetas
            const cardObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                        cardObserver.unobserve(entry.target);
                    }
                });
            }, {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            });

            // Aplicar animación inicial y observar tarjetas
            document.querySelectorAll('.card').forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';
                card.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
                card.style.transitionDelay = `${index * 0.1}s`;
                
                cardObserver.observe(card);
            });

            // Efecto parallax sutil
            let ticking = false;
            
            function updateParallax() {
                const scrolled = window.pageYOffset;
                const parallaxElements = document.querySelectorAll('.card');
                
                parallaxElements.forEach((element, index) => {
                    const rate = scrolled * -0.01 * ((index % 4) + 1);
                    element.style.transform = `translateY(${rate}px)`;
                });
                
                ticking = false;
            }

            window.addEventListener('scroll', () => {
                if (!ticking) {
                    requestAnimationFrame(updateParallax);
                    ticking = true;
                }
            });

            // Auto-hide mensajes de éxito/error después de 6 segundos
            const messages = document.querySelectorAll('.success-message, .error-message');
            messages.forEach(message => {
                setTimeout(() => {
                    message.style.animation = 'slideOutUp 0.5s ease forwards';
                    setTimeout(() => {
                        message.remove();
                    }, 500);
                }, 6000);
            });

            // Lazy loading para imágenes
            if ('IntersectionObserver' in window) {
                const imageObserver = new IntersectionObserver((entries, observer) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            img.classList.remove('lazy');
                            observer.unobserve(img);
                        }
                    });
                });

                document.querySelectorAll('img[loading="lazy"]').forEach(img => {
                    imageObserver.observe(img);
                });
            }

            // Mejorar la experiencia de los botones
            document.querySelectorAll('.card-button').forEach(button => {
                button.addEventListener('click', function(e) {
                    // Efecto de ripple
                    const ripple = document.createElement('span');
                    const rect = this.getBoundingClientRect();
                    const size = Math.max(rect.width, rect.height);
                    const x = e.clientX - rect.left - size / 2;
                    const y = e.clientY - rect.top - size / 2;
                    
                    ripple.style.cssText = `
                        position: absolute;
                        width: ${size}px;
                        height: ${size}px;
                        left: ${x}px;
                        top: ${y}px;
                        background: rgba(255, 255, 255, 0.3);
                        border-radius: 50%;
                        transform: scale(0);
                        animation: ripple 0.6s linear;
                        pointer-events: none;
                    `;
                    
                    this.style.position = 'relative';
                    this.style.overflow = 'hidden';
                    this.appendChild(ripple);
                    
                    setTimeout(() => {
                        ripple.remove();
                    }, 600);
                });
            });

            // Efecto hover
            document.querySelectorAll('.card').forEach(card => {
                card.addEventListener('mouseenter', function() {
                    // Pausar animación parallax mientras se hace hover
                    this.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
                });
                
                card.addEventListener('mouseleave', function() {
                    // Restaurar animación parallax
                    setTimeout(() => {
                        this.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
                    }, 400);
                });
            });

            // Smooth scroll para navegación
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });

            // Preload de imágenes al hacer hover
            document.querySelectorAll('.card').forEach(card => {
                card.addEventListener('mouseenter', function() {
                    const img = this.querySelector('img[loading="lazy"]');
                    if (img && !img.src.includes('uploads/')) {
                        // Preload image if not already loaded
                        const preloadImg = new Image();
                        preloadImg.src = img.src;
                    }
                });
            });

            // Gestión mejorada del estado de carga
            window.addEventListener('beforeunload', function() {
                // Mostrar indicador de carga si es necesario
                const loadingIndicator = document.createElement('div');
                loadingIndicator.innerHTML = `
                    <div style="
                        position: fixed;
                        top: 0;
                        left: 0;
                        width: 100%;
                        height: 100%;
                        background: rgba(248, 246, 243, 0.9);
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        z-index: 9999;
                        backdrop-filter: blur(5px);
                    ">
                        <div style="text-align: center; color: #6b4226;">
                            <div class="spinning" style="width: 40px; height: 40px; border: 3px solid #a3b18a; border-top: 3px solid #6b4226; border-radius: 50%; margin: 0 auto 15px auto;"></div>
                            <p style="font-weight: 600; margin: 0;">Cargando...</p>
                        </div>
                    </div>
                `;
                document.body.appendChild(loadingIndicator);
            });

            console.log('✅ Página de inicio cargada correctamente');
            console.log(`Se encontraron ${document.querySelectorAll('.card').length} publicaciones recientes`);
            console.log('🎨 Tema aplicado: Colores tierra y naturales');
            
            // Verificar funcionalidades
            const features = {
                'Búsqueda en tiempo real': !!searchInput,
                'Animaciones': document.querySelectorAll('.card').length > 0,
                'Estadísticas dinámicas': !!statsBanner,
                'Lazy loading': 'IntersectionObserver' in window,
                'Responsive design': window.matchMedia('(max-width: 768px)').matches
            };
            
            console.log('🔧 Funcionalidades activas:', features);
        });
        
        // Detectar si el usuario prefiere animaciones reducidas
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
        
        if (prefersReducedMotion.matches) {
            // Reducir animaciones para usuarios que lo prefieren
            document.documentElement.style.setProperty('--transition', 'all 0.1s ease');
            document.querySelectorAll('.card').forEach(card => {
                card.style.animation = 'none';
                card.style.opacity = '1';
                card.style.transform = 'none';
            });
        }

        // Mejorar rendimiento en dispositivos móviles
        if (window.innerWidth <= 768) {
            // Deshabilitar parallax en móviles para mejor rendimiento
            window.removeEventListener('scroll', updateParallax);
            
            // Optimizar animaciones para móviles
            document.querySelectorAll('.card').forEach(card => {
                card.style.willChange = 'auto';
            });
        }

        // Gestión de errores de imágenes
        document.querySelectorAll('img').forEach(img => {
            img.addEventListener('error', function() {
                // Mostrar placeholder si la imagen no carga
                this.style.display = 'none';
                const placeholder = this.parentNode.querySelector('::after') || this.parentNode;
                if (placeholder) {
                    placeholder.style.display = 'flex';
                }
            });
            
            img.addEventListener('load', function() {
                // Ocultar placeholder cuando la imagen carga
                this.style.opacity = '1';
                const cardImage = this.closest('.card-image');
                if (cardImage) {
                    cardImage.style.setProperty('--book-icon-display', 'none');
                }
            });
        });

        if (window.performance && window.performance.measure) {
            window.addEventListener('load', function() {
                setTimeout(() => {
                    const navigation = performance.getEntriesByType('navigation')[0];
                    const loadTime = navigation.loadEventEnd - navigation.loadEventStart;
                    
                    if (loadTime > 3000) {
                        console.warn('⚠️ Tiempo de carga lento detectado:', loadTime + 'ms');
                    } else {
                        console.log('⚡ Página cargada rápidamente:', loadTime + 'ms');
                    }
                }, 0);
            });
        }
        let lastClickTime = 0;
        document.addEventListener('click', function(e) {
            const now = Date.now();
            if (now - lastClickTime < 300) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }
            lastClickTime = now;
        }, true);
    </script>
    <script>
        function deletePost(postId, btn) {
            if (!confirm('¿Estás seguro de que quieres eliminar esta publicación?')) return;
            
            fetch('../api/social_action.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=delete&id=${postId}`
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    // Remove the card from DOM
                    const card = btn.closest('.card');
                    card.style.transition = 'opacity 0.5s';
                    card.style.opacity = '0';
                    setTimeout(() => card.remove(), 500);
                } else {
                    alert('Error: ' + (data.message || 'No se pudo eliminar'));
                }
            })
            .catch(err => console.error(err));
        }

        function toggleLike(postId, btn) {
            const isLiked = btn.classList.contains('active');
            const action = isLiked ? 'unlike' : 'like';
            
            fetch('../api/social_action.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=${action}&id=${postId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    btn.classList.toggle('active');
                    btn.querySelector('.like-count').textContent = data.likes;
                    const icon = btn.querySelector('svg');
                    if (data.liked) {
                        btn.style.color = '#e74c3c';
                        icon.setAttribute('fill', 'currentColor');
                    } else {
                        btn.style.color = 'var(--text-secondary)';
                        icon.setAttribute('fill', 'none');
                    }
                }
            })
            .catch(console.error);
        }

        function toggleComments(postId) {
            const section = document.getElementById(`comments-${postId}`);
            const list = document.getElementById(`comments-list-${postId}`);
            
            if (section.style.display === 'none') {
                section.style.display = 'block';
                
                // Mostrar indicador de carga
                list.innerHTML = '<div style="padding:10px; text-align:center; color:#666;">Cargando comentarios...</div>';
                
                fetch('../api/social_action.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=get_comments&id=${postId}`
                })
                .then(res => res.json())
                .then(data => {
                    if(data.success) {
                        list.innerHTML = '';
                        if(data.comments.length === 0) {
                            list.innerHTML = '<div style="padding:10px; text-align:center; color:#999; font-style:italic;">Sé el primero en comentar</div>';
                        }
                        data.comments.forEach(c => {
                            const html = `
                                <div class="comment" style="margin-bottom: 10px; padding: 10px; background: #f0f2f5; border-radius: 12px;">
                                    <div style="font-weight: 700; font-size: 0.9em; color: #333; margin-bottom: 2px;">${c.author}</div>
                                    <div style="font-size: 0.95em; color: #1c1e21; line-height: 1.4;">${c.content}</div>
                                </div>
                            `;
                            list.insertAdjacentHTML('beforeend', html);
                        });
                    }
                })
                .catch(err => {
                    console.error(err);
                    list.innerHTML = '<div style="color:red; padding:10px;">Error al cargar comentarios</div>';
                });
            } else {
                section.style.display = 'none';
            }
        }

        function postComment(postId) {
            const input = document.getElementById(`comment-input-${postId}`);
            const content = input.value.trim();
            
            if (!content) return;
            
            fetch('../api/social_action.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=comment&id=${postId}&content=${encodeURIComponent(content)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    input.value = '';
                    const list = document.getElementById(`comments-list-${postId}`);
                    const commentHtml = `
                        <div class="comment" style="margin-bottom: 10px; padding: 8px; background: #f8f9fa; border-radius: 8px;">
                            <div style="font-weight: 600; font-size: 0.9em;">${data.comment.author}</div>
                            <div style="font-size: 0.95em;">${data.comment.content}</div>
                        </div>
                    `;
                    list.insertAdjacentHTML('beforeend', commentHtml);
                    
                    // Update count
                    const countSpan = document.querySelector(`button[onclick="toggleComments(${postId})"] .comment-count`);
                    countSpan.textContent = parseInt(countSpan.textContent) + 1;
                }
            })
            .catch(console.error);
        }

        function sharePost(postId) {
            if (navigator.share) {
                navigator.share({
                    title: 'Publicación en ReL',
                    text: 'Mira esta publicación en ReL',
                    url: window.location.href
                }).catch(console.error);
            } else {
                // Fallback
                const dummy = document.createElement('input');
                document.body.appendChild(dummy);
                dummy.value = window.location.href;
                dummy.select();
                document.execCommand('copy');
                document.body.removeChild(dummy);
                alert('Enlace copiado al portapapeles');
            }
        }

        function openModal(modalId) {
            document.getElementById(modalId).style.display = "block";
        }

        function goToProfile(userId) {
            console.log('Navigating to profile:', userId);
            if (userId) {
                window.location.href = 'perfil_usuario.php?id=' + userId + '&t=' + new Date().getTime();
            } else {
                console.error('Invalid user ID');
            }
        }

        const searchInput = document.getElementById('search-input');
        const searchResults = document.getElementById('search-results');
        let searchTimeout;

        if (searchInput) {
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                const query = this.value.trim();
                
                if (query.length < 2) {
                    searchResults.style.display = 'none';
                    return;
                }
                
                searchTimeout = setTimeout(() => {
                    fetch(`../api/search_users.php?q=${encodeURIComponent(query)}`)
                        .then(res => res.json())
                        .then(data => {
                            console.log('Search results:', data);
                            if (data.success && data.users.length > 0) {
                                searchResults.innerHTML = data.users.map(user => {
                                    console.log('User found:', user); // Debug
                                    // Fix avatar path logic
                                    let avatarSrc = user.avatar;
                                    if (!avatarSrc || avatarSrc === 'default-avatar.png') {
                                        avatarSrc = '../assets/images/default-avatar.png';
                                    } else if (!avatarSrc.startsWith('http') && !avatarSrc.startsWith('../')) {
                                        avatarSrc = '../uploads/' + avatarSrc;
                                    }
                                    
                                    // Ensure ID is valid
                                    const targetId = user.id || user.idUsuario;
                                    if (!targetId) console.error('User ID missing for:', user);

                                    return `
                                    <a href="perfil_usuario.php?id=${targetId}" class="search-result-item" style="text-decoration: none; color: inherit; display: flex; align-items: center; padding: 10px;">
                                        <img src="${avatarSrc}" onerror="this.src='../assets/images/default-avatar.png'" class="search-result-avatar" style="width: 40px; height: 40px; border-radius: 50%; margin-right: 10px;">
                                        <div class="search-result-info">
                                            <span class="search-result-name" style="font-weight: bold; display: block;">${user.name}</span>
                                            <span class="search-result-username" style="color: #666; font-size: 0.9em;">@${user.username}</span>
                                        </div>
                                    </a>
                                    `;
                                }).join('');
                                searchResults.style.display = 'block';
                            } else {
                                searchResults.innerHTML = '<div style="padding:10px; text-align:center; color:#666;">No se encontraron usuarios</div>';
                                searchResults.style.display = 'block';
                            }
                        })
                        .catch(console.error);
                }, 300);
            });

            document.addEventListener('click', function(e) {
                if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                    searchResults.style.display = 'none';
                }
            });
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = "none";
        }

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = "none";
            }
        }

        function submitStatus() {
            const form = document.getElementById('statusForm');
            const formData = new FormData(form);

            fetch('../api/create_post.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Publicación creada exitosamente');
                    closeModal('statusModal');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al crear la publicación');
            });
        }

        // Notifications Logic
        document.addEventListener('DOMContentLoaded', function() {
            const notifBtn = document.getElementById('notif-btn');
            const notifDropdown = document.getElementById('notif-dropdown');
            const notifList = document.getElementById('notif-list');
            const notifBadge = document.getElementById('notif-badge');
            const msgBadge = document.getElementById('msg-badge');
            
            window.markAllRead = function() {
                fetch('../api/get_notifications.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=mark_read'
                })
                .then(res => res.json())
                .then(data => {
                    if(data.success) {
                        loadNotifications();
                    }
                });
            };

            function loadNotifications() {
                fetch('../api/get_notifications.php')
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        // Notifications Badge
                        if (data.unreadCount > 0) {
                            notifBadge.textContent = data.unreadCount;
                            notifBadge.style.display = 'block';
                        } else {
                            notifBadge.style.display = 'none';
                        }

                        // Messages Badge
                        if (data.unreadMessages > 0) {
                            msgBadge.textContent = data.unreadMessages;
                            msgBadge.style.display = 'block';
                        } else {
                            msgBadge.style.display = 'none';
                        }
                        
                        if (data.notifications.length > 0) {
                            notifList.innerHTML = data.notifications.map(n => `
                                <a href="${n.link}" class="notif-item ${n.read ? '' : 'unread'}">
                                    <img src="${n.senderPhoto}" class="notif-avatar" onerror="this.src='../assets/images/default-avatar.png'">
                                    <div class="notif-content">
                                        <div class="notif-text"><strong>${n.senderName}</strong> ${n.content}</div>
                                        <div class="notif-time">${n.date}</div>
                                    </div>
                                    ${!n.read ? '<div class="notif-dot"></div>' : ''}
                                </a>
                            `).join('');
                        } else {
                            notifList.innerHTML = '<div class="empty-state">No tienes notificaciones</div>';
                        }
                    }
                })
                .catch(err => console.error('Error loading notifications:', err));
            }
            
            notifBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                const isVisible = notifDropdown.style.display === 'block';
                notifDropdown.style.display = isVisible ? 'none' : 'block';
            });
            
            document.addEventListener('click', function() {
                if(notifDropdown) notifDropdown.style.display = 'none';
            });
            
            if(notifDropdown) {
                notifDropdown.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            }
            
            // Poll every 10 seconds
            loadNotifications();
            setInterval(loadNotifications, 10000);
        });
    </script>
</body>
</html>