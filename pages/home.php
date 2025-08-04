<?php
    session_start();

    if(!isset($_SESSION['usuario']) || empty($_SESSION['usuario'])) {
        header("Location: auth/login.php");
        exit(); 
    }

    require_once __DIR__ . '/../config/database.php';
    $userId = $_SESSION['user_id']; 

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

        // Consulta mejorada para mostrar publicaciones recientes de TODOS los usuarios
        $query = "
            SELECT 
                p.idPublicacion,
                p.idLibro,
                p.idUsuario,
                p.precio,
                p.fechaCreacion,
                l.titulo,
                l.autor,
                l.descripcion,
                l.editorial,
                l.edicion,
                l.categoria,
                l.tipoPublico,
                l.base,
                l.altura,
                l.paginas,
                l.linkVideo,
                l.linkImagen1,
                l.linkImagen2,
                l.linkImagen3,
                l.fechaPublicacion,
                CASE 
                    WHEN EXISTS (SELECT 1 FROM Usuarios u WHERE u.idUsuario = p.idUsuario) THEN 
                        COALESCE(
                            (SELECT u.$columna_nombre_usuario FROM Usuarios u WHERE u.idUsuario = p.idUsuario LIMIT 1),
                            CONCAT('Usuario #', p.idUsuario)
                        )
                    ELSE CONCAT('Usuario #', p.idUsuario)
                END as nombreUsuario
            FROM Publicaciones p
            JOIN Libros l ON p.idLibro = l.idLibro
            WHERE p.idUsuario != ?
            ORDER BY p.fechaCreacion DESC
            LIMIT 50;
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if (!$result) {
            throw new Exception("Error en la consulta: " . $conn->error);
        }

        while ($row = $result->fetch_assoc()) {
            // Si estamos usando ID como nombre, formatearlo mejor
            if ($usar_id_como_nombre && is_numeric($row['nombreUsuario'])) {
                $row['nombreUsuario'] = 'Usuario #' . $row['nombreUsuario'];
            }
            $publicaciones[] = $row;
        }

        // Obtener hashtags para cada libro si hay publicaciones
        if (!empty($publicaciones)) {
            $libroIds = array_column($publicaciones, 'idLibro');
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
        /* Variables CSS consistentes con tu diseño */
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

        /* Topbar con tu estilo */
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

        /* Header con tu estilo */
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

        .logo {
            font-size: 28px;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary-brown) 0%, var(--secondary-brown) 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -0.5px;
        }

        .search-bar {
            flex: 1;
            margin: 0 30px;
            display: flex;
            border: 2px solid transparent;
            border-radius: 50px;
            overflow: hidden;
            background: linear-gradient(white, white) padding-box,
                        linear-gradient(135deg, var(--green-primary), var(--green-secondary)) border-box;
            box-shadow: 0 8px 32px rgba(163, 177, 138, 0.15);
            transition: var(--transition);
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

        .stats-banner {
            display: flex;
            justify-content: center;
            gap: 40px;
            flex-wrap: wrap;
            margin-top: 30px;
        }

        .stat-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(10px);
            padding: 25px 30px;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: var(--transition);
        }

        .stat-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12);
        }

        .stat-number {
            font-size: 2.2em;
            font-weight: 800;
            background: linear-gradient(135deg, var(--green-secondary) 0%, var(--green-dark) 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 0.95em;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Gallery */
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
            content: '📚';
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

        /* Empty State */
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

        /* Bottom bar con tu estilo */
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

        /* Responsive Design */
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

        /* Mejoras visuales adicionales */
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

        /* Efectos hover mejorados */
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
            <a href="chat/chat.php" class="bottom-button" title="Chat">
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
        <div class="logo">RELEE</div>
        <div class="search-bar">
            <input type="text" id="search-input" placeholder="Buscar en publicaciones">
            <button type="button" id="search-button">
                <svg width="18" height="18" fill="white" viewBox="0 0 24 24">
                    <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                </svg>
            </button>
        </div>
        <a href="products/buscador.php" class="user-button">Búsqueda Avanzada</a>
    </header>

    <div class="hero-section">
        <h1 class="hero-title">📚 Descubre Libros Increíbles</h1>
        <p class="hero-subtitle">Explora las publicaciones más recientes de nuestra comunidad</p>
        
        <?php if (!empty($publicaciones)): ?>
        <div class="stats-banner">
            <div class="stat-item">
                <span class="stat-number"><?php echo count($publicaciones); ?></span>
                <span class="stat-label">Publicaciones</span>
            </div>
            <div class="stat-item">
                <span class="stat-number"><?php echo count(array_filter($publicaciones, function($p) { return $p['precio'] == 0; })); ?></span>
                <span class="stat-label">Gratis</span>
            </div>
            <div class="stat-item">
                <span class="stat-number"><?php echo count(array_filter($publicaciones, function($p) { return !empty($p['linkVideo']); })); ?></span>
                <span class="stat-label">Con Video</span>
            </div>
            <div class="stat-item">
                <span class="stat-number"><?php echo count(array_unique(array_column($publicaciones, 'idUsuario'))); ?></span>
                <span class="stat-label">Usuarios</span>
            </div>
        </div>
        <?php endif; ?>
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

    <!-- Información de debug (solo si hay error y se solicita) -->
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
                <div class="empty-state-icon">📚</div>
                <h3>¡Bienvenido a RELEE!</h3>
                <p>Aún no hay publicaciones recientes. Sé el primero en compartir un libro con la comunidad.</p>
                <a href="products/detalle_publicacion.php?id=<?php echo htmlspecialchars($publicacion['idPublicacion']); ?>" class="card-button view-button">
                    <svg width="20" height="20" fill="white" viewBox="0 0 24 24">
                        <path d="M19 13H13V19H11V13H5V11H11V5H13V11H19V13Z"/>
                    </svg>
                    Crear Primera Publicación
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($publicaciones as $index => $publicacion): ?>
                <article class="card" style="animation-delay: <?php echo $index * 0.1; ?>s;">
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
                                👤 <?php echo htmlspecialchars($publicacion['nombreUsuario']); ?>
                            </div>
                        </div>

                        <article class="card" style="animation-delay: <?php echo $index * 0.1; ?>s;">
                        <!-- Contenido de la tarjeta... -->
                        
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
                    </article>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>

    <div class="bottombar">
        <a href="home.php" class="bottom-button" title="Inicio">
            <svg width="22" height="22" fill="white" viewBox="0 0 24 24">
                <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>
            </svg>
            <span>Inicio</span>
        </a>
        <a href="products/publicaciones.php" class="bottom-button bottom-button-wide" title="Mis Publicaciones">
            <span>Mis Publicaciones</span>
        </a>
        <button class="bottom-button" title="Menú">
            <svg width="22" height="22" fill="white" viewBox="0 0 24 24">
                <path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/>
            </svg>
            <span>Menú</span>
        </button>
    </div>

    <script src="../assets/js/home-script.js"></script>
    <script src="../assets/js/chat-script.js"></script>
    <script>
        function realizarBusqueda() {
            const searchInput = document.getElementById('search-input');
            const searchTerm = searchInput.value.toLowerCase().trim();
            const cards = document.querySelectorAll('.card');
            let visibleCount = 0;

            // Remover mensaje anterior de no resultados
            const previousNoResults = document.querySelector('.no-results-message');
            if (previousNoResults) {
                previousNoResults.remove();
            }

            cards.forEach(card => {
                const title = card.querySelector('.card-title')?.textContent.toLowerCase() || '';
                const author = card.querySelector('.card-author')?.textContent.toLowerCase() || '';
                const description = card.querySelector('.card-description')?.textContent.toLowerCase() || '';
                const hashtags = Array.from(card.querySelectorAll('.hashtag')).map(tag => tag.textContent.toLowerCase()).join(' ');
                const publisher = card.querySelector('.card-publisher')?.textContent.toLowerCase() || '';

                const matches = searchTerm === '' || 
                            title.includes(searchTerm) || 
                            author.includes(searchTerm) || 
                            description.includes(searchTerm) ||
                            hashtags.includes(searchTerm) ||
                            publisher.includes(searchTerm);

                if (matches) {
                    card.style.display = 'block';
                    card.style.animation = 'fadeInUp 0.5s ease forwards';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            // Mostrar mensaje si no hay resultados
            if (visibleCount === 0 && searchTerm !== '') {
                const noResultsMsg = document.createElement('div');
                noResultsMsg.className = 'no-results-message';
                noResultsMsg.innerHTML = `
                    <div class="empty-state-icon">🔍</div>
                    <h3>No se encontraron resultados</h3>
                    <p>No encontramos publicaciones que coincidan con "<strong>${searchTerm}</strong>"</p>
                    <p>Intenta con otros términos de búsqueda.</p>
                `;
                document.querySelector('.gallery').appendChild(noResultsMsg);
            }

            // Actualizar estadísticas
            updateStats(visibleCount, searchTerm);
        }

        // Eventos de búsqueda
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('search-input');
            const searchButton = document.getElementById('search-button');
            
            if (searchInput && searchButton) {
                let searchTimeout;
                
                // Búsqueda en tiempo real mientras escribe
                searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(realizarBusqueda, 300);
                });
                
                // Búsqueda al hacer clic en el botón
                searchButton.addEventListener('click', realizarBusqueda);
                
                // Búsqueda al presionar Enter
                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        realizarBusqueda();
                    }
                });
            }
        });

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

        function abrirChat(userId, userName) {
            // Prevenir múltiples clicks
            const button = event.target.closest('.contact-button');
            if (button.disabled || button.classList.contains('processing')) {
                return false;
            }
            
            // Deshabilitar botón inmediatamente
            button.disabled = true;
            button.classList.add('processing');
            
            // Cambiar texto del botón para dar feedback
            const originalText = button.innerHTML;
            button.innerHTML = `
                <svg width="16" height="16" fill="white" viewBox="0 0 24 24" class="spinning">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" opacity="0.3"/>
                    <path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                </svg>
                Conectando...
            `;
            
            // Función para restaurar botón
            function restaurarBoton() {
                button.disabled = false;
                button.classList.remove('processing');
                button.innerHTML = originalText;
            }
            
            fetch('../api/create_conversation.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'other_user_id=' + userId
            })
            .then(response => response.json())
            .then(data => {
                restaurarBoton();
                if (data.success) {
                    // Redirigir inmediatamente sin permitir más clicks
                    window.location.href = 'chat/chat.php?conversacion=' + data.conversationId;
                } else {
                    alert('Error al abrir el chat: ' + data.message);
                }
            })
            .catch(error => {
                restaurarBoton();
                console.error('Error:', error);
                alert('Error al conectar con el servidor');
            });
            
            return false;
        }
        let enviandoMensaje = false;

        document.getElementById('sendButton').addEventListener('click', function() {
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
            restaurarElementos();
            messageText.value = content; // Restaurar texto en caso de error
            console.error('Error:', error);
            alert('Error de conexión');
        });
    });

    // 3. PREVENIR ENVÍO CON ENTER EN MÓVILES
    document.getElementById('messageText').addEventListener('keypress', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            
            // Solo enviar si no estamos ya enviando
            if (!enviandoMensaje) {
                document.getElementById('sendButton').click();
            }
        }
    });

    // 4. DEBOUNCE PARA CLICKS RÁPIDOS
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

    // 5. APLICAR DEBOUNCE A TODOS LOS BOTONES CRÍTICOS
    document.addEventListener('DOMContentLoaded', function() {
        // Aplicar prevención a todos los botones de contactar
        document.querySelectorAll('.contact-button').forEach(button => {
            // Remover listeners existentes
            button.onclick = null;
            
            // Agregar listener con debounce
            button.addEventListener('click', debounce(function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                // Obtener datos del botón
                const userId = this.getAttribute('onclick').match(/\d+/)[0];
                const userName = this.getAttribute('onclick').match(/'([^']+)'/)[1];
                
                abrirChat(userId, userName);
            }, 300));
        });
        
        // Aplicar a botones de nueva conversación
        document.querySelectorAll('.user-result').forEach(userResult => {
            userResult.addEventListener('click', debounce(function() {
                const userId = this.dataset.userid;
                const userName = this.dataset.username;
                
                if (userId && userName) {
                    abrirChat(userId, userName);
                }
            }, 300));
        });
        
        // Prevenir doble tap en iOS
        let lastTouchEnd = 0;
        document.addEventListener('touchend', function (event) {
            const now = (new Date()).getTime();
            if (now - lastTouchEnd <= 300) {
                event.preventDefault();
            }
            lastTouchEnd = now;
        }, false);
    });

    // 6. MEJORAR FEEDBACK VISUAL EN MÓVILES
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

    // 7. LIMPIAR CONVERSACIONES DUPLICADAS AUTOMÁTICAMENTE
    function limpiarConversacionesDuplicadas() {
        // Esta función la puedes llamar periódicamente o al cargar la página
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

    // Ejecutar limpieza al cargar la página (opcional)
    // limpiarConversacionesDuplicadas();

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

            // Lazy loading mejorado para imágenes
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

            // Efecto hover mejorado para las tarjetas
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

            // Inicialización completa
            console.log('✅ Página de inicio cargada correctamente');
            console.log(`📚 Se encontraron ${document.querySelectorAll('.card').length} publicaciones recientes`);
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

        // Utilidades adicionales para mejorar la experiencia
        
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
    </script>
</body>
</html>