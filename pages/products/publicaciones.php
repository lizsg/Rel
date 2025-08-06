<?php
session_start();

if(!isset($_SESSION['usuario']) || empty($_SESSION['usuario']) || !isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$publicaciones = [];
$hashtags = [];
$errorMessage = '';

try {
    $conn = new mysqli(SERVER_NAME, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset("utf8mb4");

    if ($conn->connect_error) {
        throw new Exception("Error de conexión: " . $conn->connect_error);
    }

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

    $stmt = $conn->prepare("
        SELECT 
            p.idPublicacion,
            p.idLibro,
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
        WHERE p.idUsuario = ?
        ORDER BY p.fechaCreacion DESC
    ");
    
    if (!$stmt) {
        throw new Exception("Error preparando consulta: " . $conn->error);
    }
    
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        // Si estamos usando ID como nombre
        if ($usar_id_como_nombre && $row['nombreUsuario'] == $_SESSION['user_id']) {
            $row['nombreUsuario'] = 'Mi Usuario (#' . $_SESSION['user_id'] . ')';
        }
        $publicaciones[] = $row;
    }

    // Obtener hashtags para cada libro
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

    $stmt->close();
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

function formatearPrecio($precio) {
    return ($precio == 0 || $precio === null) ? 'Gratis' : '$' . number_format($precio, 2);
}

function formatearFecha($fecha) {
    return empty($fecha) ? 'No especificada' : date('d/m/Y', strtotime($fecha));
}

function formatearDimensiones($base, $altura) {
    if (empty($base) && empty($altura)) return 'No especificadas';
    if (empty($base)) return "Altura: {$altura} cm";
    if (empty($altura)) return "Base: {$base} cm";
    return "{$base} x {$altura} cm";
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
    <title>Mis Publicaciones | RELEE</title>
    <link rel="stylesheet" href="../../assets/css/home-styles.css">
    <link rel="stylesheet" href="../../assets/css/chat-styles.css">
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

        /* Topbar */
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

        /* Header */
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
            text-decoration: none;
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

       

        /* Botón de nueva publicación */
        .new-button-container {
            display: flex;
            justify-content: center;
            padding: 20px;
            margin-bottom: 30px;
        }

        .new-button {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            background: linear-gradient(135deg, var(--green-secondary) 0%, var(--green-dark) 100%);
            color: white;
            padding: 18px 35px;
            border: none;
            border-radius: 50px;
            font-size: 16px;
            font-weight: 700;
            text-decoration: none;
            box-shadow: 0 8px 25px rgba(88, 129, 87, 0.4);
            transition: var(--transition);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .new-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(88, 129, 87, 0.6);
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

        .publication-card {
            background: rgba(255, 253, 252, 0.95);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(255, 255, 255, 0.2);
            overflow: hidden;
            transition: var(--transition);
            position: relative;
        }

        .publication-card::before {
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

        .publication-card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: var(--hover-shadow);
        }

        .publication-card:hover::before {
            opacity: 1;
        }

        .card-images {
            width: 100%;
            height: 240px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--light-brown) 0%, #c4a68a 100%);
            position: relative;
        }

        .main-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .publication-card:hover .main-image {
            transform: scale(1.1);
        }

        .card-images::after {
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

        .card-images img + * {
            --book-icon-display: none;
        }

        .image-indicators {
            position: absolute;
            bottom: 15px;
            right: 15px;
            display: flex;
            gap: 8px;
        }

        .image-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.6);
            border: 2px solid rgba(255, 255, 255, 0.9);
            cursor: pointer;
            transition: var(--transition);
        }

        .image-indicator.active {
            background: linear-gradient(135deg, var(--green-secondary) 0%, var(--green-dark) 100%);
            transform: scale(1.2);
        }

        .video-indicator {
            position: absolute;
            top: 15px;
            left: 15px;
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

        .publication-date {
            position: absolute;
            top: 15px;
            right: 15px;
            background: linear-gradient(135deg, var(--green-secondary) 0%, var(--green-dark) 100%);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(88, 129, 87, 0.4);
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

        .price-badge {
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

        .price-badge.free {
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

        .book-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }

        .detail-item {
            background: linear-gradient(135deg, rgba(163, 177, 138, 0.15) 0%, rgba(88, 129, 87, 0.15) 100%);
            padding: 12px 16px;
            border-radius: 12px;
            border-left: 4px solid var(--green-secondary);
        }

        .detail-label {
            font-weight: 700;
            color: var(--green-dark);
            display: block;
            margin-bottom: 4px;
            font-size: 0.85em;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .detail-value {
            color: var(--text-primary);
            font-weight: 500;
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
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 12px;
            margin-top: 25px;
        }

        .card-button {
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
            cursor: pointer;
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

        .edit-button {
            background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(237, 137, 54, 0.3);
        }

        .edit-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(237, 137, 54, 0.4);
        }

        .delete-button {
            background: linear-gradient(135deg, #f56565 0%, #e53e3e 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(245, 101, 101, 0.3);
        }

        .delete-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(245, 101, 101, 0.4);
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
        .error-display, .success-message {
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

        @keyframes spinning {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .publication-card {
            animation: fadeInUp 0.6s ease forwards;
        }

        .success-message, .error-display {
            animation: slideInDown 0.5s ease;
        }

        .spinning {
            animation: spinning 1s linear infinite;
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
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .book-details {
                grid-template-columns: 1fr;
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
<div class="logo-container">
    <a href="../home.php" class="logo-link" title="Ir al inicio">
        <div class="logo-icon">
            <img src="../../assets/images/REELEE.jpeg" alt="RELEE Logo" class="logo-image" />
        </div>
    </a>
</div>
    <div class="search-bar">
        <input type="text" id="search-input" placeholder="Buscar en mis publicaciones...">
        <button type="button" id="search-button">
            <svg width="18" height="18" fill="white" viewBox="0 0 24 24">
                <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
            </svg>
        </button>
    </div>
    <a href="buscador.php" class="user-button">Búsqueda Avanzada</a>
</header>

    
    <div class="hero-section">
        <h1 class="hero-title">📚 Mis Publicaciones</h1>
        <p class="hero-subtitle">Gestiona y visualiza toda tu biblioteca digital personal</p>
        

    </div>

    
    <div class="new-button-container">
        <a href="NuevaPublicacion.php" class="new-button">
            <svg width="24" height="24" fill="white" viewBox="0 0 24 24">
                <path d="M19 13H13V19H11V13H5V11H11V5H13V11H19V13Z"/>
            </svg>
            <span>Nueva Publicación</span>
        </a>
    </div>


    <?php if (isset($_SESSION['mensaje_exito'])): ?>
        <div class="success-message">
            ✅ <?php echo htmlspecialchars($_SESSION['mensaje_exito']); unset($_SESSION['mensaje_exito']); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errorMessage)): ?>
        <div class="error-display">
            ❌ <?php echo htmlspecialchars($errorMessage); ?>
        </div>
    <?php endif; ?>

    
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
                <h3>¡Comienza tu biblioteca digital!</h3>
                <p>Aún no tienes publicaciones. Comparte tus libros favoritos con la comunidad y comienza a construir tu biblioteca personal.</p>
                <a href="NuevaPublicacion.php" class="new-button">
                    <svg width="20" height="20" fill="white" viewBox="0 0 24 24">
                        <path d="M19 13H13V19H11V13H5V11H11V5H13V11H19V13Z"/>
                    </svg>
                    <span>Crear Primera Publicación</span>
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($publicaciones as $index => $publicacion): ?>
                <article class="publication-card" style="animation-delay: <?php echo $index * 0.1; ?>s;">
                    <div class="card-images">
                        <?php if (!empty($publicacion['linkImagen1'])): ?>
                            <img src="../../uploads/<?php echo htmlspecialchars($publicacion['linkImagen1']); ?>" 
                                 alt="<?php echo htmlspecialchars($publicacion['titulo']); ?>" 
                                 class="main-image"
                                 loading="lazy">
                        <?php endif; ?>
                        
                        <div class="publication-date">
                            ⏰ <?php echo tiempoTranscurrido($publicacion['fechaCreacion']); ?>
                        </div>
                        
                        <?php if (!empty($publicacion['linkVideo'])): ?>
                            <div class="video-indicator">
                                <svg width="12" height="12" fill="white" viewBox="0 0 24 24">
                                    <path d="M8 5v14l11-7z"/>
                                </svg>
                                Video
                            </div>
                        <?php endif; ?>
                        
                        <div class="image-indicators">
                            <?php 
                            $imagenes_disponibles = 0;
                            if (!empty($publicacion['linkImagen1'])) $imagenes_disponibles++;
                            if (!empty($publicacion['linkImagen2'])) $imagenes_disponibles++;
                            if (!empty($publicacion['linkImagen3'])) $imagenes_disponibles++;
                            
                            for ($i = 1; $i <= $imagenes_disponibles; $i++): ?>
                                <div class="image-indicator <?php echo $i === 1 ? 'active' : ''; ?>" 
                                     data-image="<?php echo $i; ?>"
                                     data-src="<?php echo htmlspecialchars($publicacion["linkImagen$i"]); ?>"></div>
                            <?php endfor; ?>
                        </div>
                    </div>
                    
                    <div class="card-content">
                        <h3 class="card-title"><?php echo htmlspecialchars($publicacion['titulo']); ?></h3>
                        <div class="card-author">✍️ <?php echo htmlspecialchars($publicacion['autor']); ?></div>
                        <div class="card-author" style="font-size: 0.9em; opacity: 0.8;">
                            👤 <?php echo htmlspecialchars($publicacion['nombreUsuario']); ?>
                        </div>
                        
                        <div class="price-badge <?php echo ($publicacion['precio'] == 0) ? 'free' : ''; ?>">
                            <?php echo formatearPrecio($publicacion['precio']); ?>
                        </div>

                        <?php if (!empty($publicacion['descripcion'])): ?>
                            <div class="card-description">
                                <?php echo htmlspecialchars($publicacion['descripcion']); ?>
                            </div>
                        <?php endif; ?>

                        <div class="book-details">
                            <?php if (!empty($publicacion['editorial'])): ?>
                                <div class="detail-item">
                                    <span class="detail-label">🏢 Editorial</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($publicacion['editorial']); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($publicacion['categoria'])): ?>
                                <div class="detail-item">
                                    <span class="detail-label">📖 Categoría</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($publicacion['categoria']); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($publicacion['tipoPublico'])): ?>
                                <div class="detail-item">
                                    <span class="detail-label">👥 Público</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($publicacion['tipoPublico']); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($publicacion['paginas'])): ?>
                                <div class="detail-item">
                                    <span class="detail-label">📄 Páginas</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($publicacion['paginas']); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($publicacion['edicion'])): ?>
                                <div class="detail-item">
                                    <span class="detail-label">🔢 Edición</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($publicacion['edicion']); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($publicacion['base']) || !empty($publicacion['altura'])): ?>
                                <div class="detail-item">
                                    <span class="detail-label">📏 Dimensiones</span>
                                    <span class="detail-value"><?php echo formatearDimensiones($publicacion['base'], $publicacion['altura']); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($publicacion['fechaPublicacion'])): ?>
                                <div class="detail-item">
                                    <span class="detail-label">📅 Publicación</span>
                                    <span class="detail-value"><?php echo formatearFecha($publicacion['fechaPublicacion']); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($hashtags[$publicacion['idLibro']])): ?>
                            <div class="hashtags">
                                <?php foreach ($hashtags[$publicacion['idLibro']] as $hashtag): ?>
                                    <span class="hashtag">#<?php echo htmlspecialchars($hashtag); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="card-actions">
                            <a href="verdetallespublicacion.php?id=<?php echo htmlspecialchars($publicacion['idPublicacion']); ?>" class="card-button view-button">
                                <svg width="16" height="16" fill="white" viewBox="0 0 24 24">
                                    <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                                </svg>
                                Ver Detalles
                            </a>
                            <a href="editar_publicacion.php?id=<?php echo htmlspecialchars($publicacion['idPublicacion']); ?>" class="card-button edit-button">
                                <svg width="16" height="16" fill="white" viewBox="0 0 24 24">
                                    <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
                                </svg>
                                Editar
                            </a>
                            <form action="eliminar_publicacion.php" method="POST" 
                                  onsubmit="return confirm('¿Estás seguro de eliminar \"<?php echo htmlspecialchars($publicacion['titulo']); ?>\"?\n\n⚠️ Esta acción no se puede deshacer.');" 
                                  style="display:contents;">
                                <input type="hidden" name="publicacion_id" value="<?php echo htmlspecialchars($publicacion['idPublicacion']); ?>">
                                <button type="submit" class="card-button delete-button">
                                    <svg width="16" height="16" fill="white" viewBox="0 0 24 24">
                                        <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                                    </svg>
                                    Eliminar
                                </button>
                            </form>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
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
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            
            function realizarBusquedaPersonal() {
                const searchInput = document.querySelector('.search-bar input');
                const searchTerm = searchInput ? searchInput.value.toLowerCase().trim() : '';
                const cards = document.querySelectorAll('.publication-card');
                let visibleCount = 0;

               
                const previousNoResults = document.querySelector('.no-results-personal');
                if (previousNoResults) {
                    previousNoResults.remove();
                }

                cards.forEach(card => {
                    const title = card.querySelector('.card-title')?.textContent.toLowerCase() || '';
                    const author = card.querySelector('.card-author')?.textContent.toLowerCase() || '';
                    const description = card.querySelector('.card-description')?.textContent.toLowerCase() || '';
                    const hashtags = Array.from(card.querySelectorAll('.hashtag')).map(tag => tag.textContent.toLowerCase()).join(' ');
                    const details = Array.from(card.querySelectorAll('.detail-value')).map(detail => detail.textContent.toLowerCase()).join(' ');

                    const matches = searchTerm === '' || 
                                title.includes(searchTerm) || 
                                author.includes(searchTerm) || 
                                description.includes(searchTerm) ||
                                hashtags.includes(searchTerm) ||
                                details.includes(searchTerm);

                    if (matches) {
                        card.style.display = 'block';
                        card.style.animation = 'fadeInUp 0.5s ease forwards';
                        visibleCount++;
                    } else {
                        card.style.display = 'none';
                    }
                });

                
                if (visibleCount === 0 && searchTerm !== '') {
                    const noResultsMsg = document.createElement('div');
                    noResultsMsg.className = 'no-results-personal';
                    noResultsMsg.innerHTML = `
                        <div class="empty-state-icon">🔍</div>
                        <h3>No se encontró en tus publicaciones</h3>
                        <p>No encontramos publicaciones tuyas que coincidan con "<strong>${searchTerm}</strong>"</p>
                        <p>Intenta con otros términos o <a href="NuevaPublicacion.php" style="color: var(--green-dark); text-decoration: underline;">crea una nueva publicación</a>.</p>
                    `;
                    document.querySelector('.gallery').appendChild(noResultsMsg);
                }

                
                updatePersonalStats(visibleCount, searchTerm);
            }

            function updatePersonalStats(visibleCount, searchTerm) {
                const statsContainer = document.querySelector('.stats-banner');
                if (!statsContainer) return;

                if (searchTerm !== '') {
                   
                    const visibleCards = Array.from(document.querySelectorAll('.publication-card')).filter(card => 
                        card.style.display !== 'none'
                    );
                    
                    const freeCount = visibleCards.filter(card => 
                        card.querySelector('.price-badge')?.classList.contains('free')
                    ).length;
                    
                    const videoCount = visibleCards.filter(card => 
                        card.querySelector('.video-indicator')
                    ).length;
                    
                    const paidCount = visibleCards.filter(card => 
                        !card.querySelector('.price-badge')?.classList.contains('free')
                    ).length;

                    
                    const statNumbers = statsContainer.querySelectorAll('.stat-number');
                    const statLabels = statsContainer.querySelectorAll('.stat-label');
                    
                    if (statNumbers[0]) {
                        statNumbers[0].textContent = visibleCount;
                        statLabels[0].textContent = 'Encontradas';
                    }
                    if (statNumbers[1]) {
                        statNumbers[1].textContent = freeCount;
                        statLabels[1].textContent = 'Gratis';
                    }
                    if (statNumbers[2]) {
                        statNumbers[2].textContent = videoCount;
                        statLabels[2].textContent = 'Con Video';
                    }
                    if (statNumbers[3]) {
                        statNumbers[3].textContent = paidCount;
                        statLabels[3].textContent = 'De Pago';
                    }
                } else {
             
                    if (window.location.search.includes('search=')) {
                        window.location.href = window.location.pathname;
                    }
                }
            }

          
            const headerSearchInput = document.querySelector('.search-bar input');
            const headerSearchButton = document.querySelector('.search-bar button');

            if (headerSearchInput && headerSearchButton) {
                let searchTimeout;
                
                
                headerSearchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(realizarBusquedaPersonal, 300);
                });
                
         
                headerSearchButton.addEventListener('click', realizarBusquedaPersonal);
                
                headerSearchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        realizarBusquedaPersonal();
                    }
                });
            }

            document.querySelectorAll('.publication-card').forEach(card => {
                const indicators = card.querySelectorAll('.image-indicator');
                const mainImage = card.querySelector('.main-image');
                
                if (indicators.length > 1 && mainImage) {
                    indicators.forEach((indicator, index) => {
                        indicator.addEventListener('click', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            
                            indicators.forEach(ind => ind.classList.remove('active'));
                            this.classList.add('active');
                            
                            const newSrc = this.dataset.src;
                            if (newSrc) {
                                mainImage.style.opacity = '0.5';
                                setTimeout(() => {
                                    mainImage.src = '../../uploads/' + newSrc;
                                    mainImage.style.opacity = '1';
                                }, 200);
                            }
                        });
                    });
                }
            });

        
            const observerOptions = {
                threshold: 0.5,
                rootMargin: '0px 0px -10px 0px'
            };

            const statsObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const statNumber = entry.target.querySelector('.stat-number');
                        if (statNumber) {
                            const finalValue = parseInt(statNumber.textContent);
                            let currentValue = 0;
                            const increment = Math.ceil(finalValue / 30);
                            
                            const countAnimation = setInterval(() => {
                                currentValue += increment;
                                if (currentValue >= finalValue) {
                                    statNumber.textContent = finalValue;
                                    clearInterval(countAnimation);
                                } else {
                                    statNumber.textContent = currentValue;
                                }
                            }, 50);
                        }
                        
                        statsObserver.unobserve(entry.target);
                    }
                });
            }, observerOptions);

            document.querySelectorAll('.stat-item').forEach(item => {
                statsObserver.observe(item);
            });

         
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

            
            document.querySelectorAll('.publication-card').forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(50px)';
                card.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
                card.style.transitionDelay = `${index * 0.1}s`;
                
                cardObserver.observe(card);
            });

          
            let ticking = false;
            
            function updateParallax() {
                const scrolled = window.pageYOffset;
                const parallaxElements = document.querySelectorAll('.publication-card');
                
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

       
            document.querySelectorAll('form[action="eliminar_publicacion.php"]').forEach(form => {
                form.addEventListener('submit', function(e) {
                    const titulo = this.closest('.publication-card').querySelector('.card-title').textContent;
                    
                    
                    const confirmDelete = confirm(
                        `🗑️ ¿Eliminar "${titulo}"?\n\n` +
                        `⚠️ Esta acción es permanente y no se puede deshacer.\n` +
                        `📚 Se eliminará el libro y toda su información.\n\n` +
                        `¿Continuar con la eliminación?`
                    );
                    
                    if (!confirmDelete) {
                        e.preventDefault();
                    }
                });
            });

   
            const newButton = document.querySelector('.new-button');
            if (newButton) {
                newButton.addEventListener('click', function(e) {
                    this.innerHTML = `
                        <svg width="24" height="24" fill="white" viewBox="0 0 24 24" class="spinning">
                            <path d="M12 4V1L8 5l4 4V6c3.31 0 6 2.69 6 6 0 1.01-.25 1.97-.7 2.8l1.46 1.46C19.54 15.03 20 13.57 20 12c0-4.42-3.58-8-8-8z"/>
                            <path d="m4 12c0-1.01.25-1.97.7-2.8L3.24 7.74C2.46 8.97 2 10.43 2 12c0 4.42 3.58 8 8 8v3l4-4-4-4v3c-3.31 0-6-2.69-6-6z"/>
                        </svg>
                        <span>Cargando...</span>
                    `;
                    this.style.pointerEvents = 'none';
                    this.style.opacity = '0.8';
                });
            }

    
            const messages = document.querySelectorAll('.success-message, .error-display');
            messages.forEach(message => {
                setTimeout(() => {
                    message.style.animation = 'slideOutUp 0.5s ease forwards';
                    setTimeout(() => {
                        message.remove();
                    }, 500);
                }, 6000);
            });

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

            document.querySelectorAll('img').forEach(img => {
                img.addEventListener('error', function() {
                    // Mostrar placeholder si la imagen no carga
                    this.style.display = 'none';
                    const cardImage = this.closest('.card-images');
                    if (cardImage) {
                        cardImage.style.setProperty('--book-icon-display', 'block');
                    }
                });
                
                img.addEventListener('load', function() {
                    
                    this.style.opacity = '1';
                    const cardImage = this.closest('.card-images');
                    if (cardImage) {
                        cardImage.style.setProperty('--book-icon-display', 'none');
                    }
                });
            });

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

            console.log('✅ Página de publicaciones cargada correctamente');
            console.log(`📚 Se encontraron ${document.querySelectorAll('.publication-card').length} publicaciones personales`);
            console.log('🎨 Tema aplicado: Colores tierra y naturales (consistente con home.php)');
        });

        // animaciones
        const additionalStyles = document.createElement('style');
        additionalStyles.textContent = `
            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
            
            .no-results-personal {
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
            
            .no-results-personal h3 {
                color: var(--text-primary);
                font-weight: 700;
                margin-bottom: 15px;
                font-size: 1.8em;
            }
            
            .no-results-personal p {
                margin: 10px 0;
                line-height: 1.6;
                font-size: 1.1em;
            }
            
            .no-results-personal strong {
                color: var(--green-dark);
                font-weight: 700;
            }
            
            .no-results-personal a:hover {
                color: var(--primary-brown) !important;
            }
            
            .main-image {
                transition: opacity 0.3s ease, transform 0.5s ease;
            }
            
            .image-indicator {
                transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            }
            
            .image-indicator.active {
                transform: scale(1.2);
            }
            
            /* Mejoras para dispositivos móviles */
            @media (max-width: 768px) {
                .no-results-personal {
                    padding: 40px 20px;
                }
                
                .no-results-personal h3 {
                    font-size: 1.5em;
                }
                
                .no-results-personal p {
                    font-size: 1em;
                }
            }
        `;
        document.head.appendChild(additionalStyles);

        // Detectar si el usuario prefiere animaciones reducidas
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
        
        if (prefersReducedMotion.matches) {
            // Reducir animaciones para usuarios que lo prefieren
            document.documentElement.style.setProperty('--transition', 'all 0.1s ease');
            document.querySelectorAll('.publication-card').forEach(card => {
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
            document.querySelectorAll('.publication-card').forEach(card => {
                card.style.willChange = 'auto';
            });
        }

        // Gestión de rendimiento
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