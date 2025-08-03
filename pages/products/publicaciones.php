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

    // SOLUCIÓN: Verificar primero la estructura real de la tabla Usuarios
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

    // Consulta corregida - Basada en tu estructura real
    // Nota: Según tu diagrama, parece que Usuarios es realmente la tabla Publicaciones
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
        // Si estamos usando ID como nombre, formatearlo mejor
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
        /* Variables CSS para personalización fácil */
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --card-shadow: 0 10px 30px rgba(0,0,0,0.1);
            --hover-shadow: 0 20px 40px rgba(0,0,0,0.15);
            --border-radius: 20px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding-bottom: 80px;
            min-height: 100vh;
            color: #2d3748;
            position: relative;
        }

        /* Overlay pattern */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(circle at 20% 80%, rgba(120, 119, 198, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255, 119, 198, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(120, 219, 255, 0.3) 0%, transparent 50%);
            z-index: -1;
        }

        .page-header {
            text-align: center;
            padding: 40px 20px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 3em;
            font-weight: 800;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 0 0 15px 0;
            letter-spacing: -2px;
        }

        .page-header p {
            color: #718096;
            font-size: 1.2em;
            margin: 0;
            font-weight: 500;
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            max-width: 1200px;
            margin: 0 auto 40px auto;
            padding: 0 20px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius);
            padding: 30px;
            text-align: center;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--hover-shadow);
        }

        .stat-number {
            font-size: 2.5em;
            font-weight: 800;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            display: block;
            margin-bottom: 10px;
        }

        .stat-label {
            color: #718096;
            font-weight: 600;
            font-size: 1.1em;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

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
            background: var(--secondary-gradient);
            color: white;
            padding: 18px 35px;
            border: none;
            border-radius: 50px;
            font-size: 16px;
            font-weight: 700;
            text-decoration: none;
            box-shadow: 0 8px 25px rgba(245, 87, 108, 0.4);
            transition: var(--transition);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .new-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(245, 87, 108, 0.6);
        }

        .gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 30px;
            padding: 0 20px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .publication-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: var(--transition);
            position: relative;
        }

        .publication-card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: var(--hover-shadow);
        }

        .card-images {
            position: relative;
            height: 280px;
            overflow: hidden;
            background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
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
            background: var(--secondary-gradient);
            transform: scale(1.2);
        }

        .video-indicator {
            position: absolute;
            top: 15px;
            left: 15px;
            background: var(--success-gradient);
            color: white;
            padding: 8px 15px;
            border-radius: 25px;
            font-size: 0.85em;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 4px 15px rgba(79, 172, 254, 0.4);
        }

        .card-content {
            padding: 30px;
        }

        .publication-date {
            background: var(--primary-gradient);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            text-align: center;
            margin-bottom: 20px;
            display: inline-block;
        }

        .card-title {
            font-size: 1.5em;
            font-weight: 800;
            color: #2d3748;
            margin-bottom: 8px;
            line-height: 1.3;
        }

        .card-author {
            color: #718096;
            font-weight: 600;
            margin-bottom: 15px;
            font-size: 1.1em;
        }

        .price-badge {
            display: inline-block;
            background: var(--secondary-gradient);
            color: white;
            padding: 8px 20px;
            border-radius: 25px;
            font-weight: 700;
            font-size: 1em;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(245, 87, 108, 0.3);
        }

        .price-badge.free {
            background: var(--success-gradient);
            box-shadow: 0 4px 15px rgba(79, 172, 254, 0.3);
        }

        .card-description {
            color: #4a5568;
            line-height: 1.6;
            margin-bottom: 20px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .book-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }

        .detail-item {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            padding: 12px 16px;
            border-radius: 12px;
            border-left: 4px solid;
            border-image: var(--primary-gradient) 1;
        }

        .detail-label {
            font-weight: 700;
            color: #4c51bf;
            display: block;
            margin-bottom: 4px;
            font-size: 0.85em;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .detail-value {
            color: #2d3748;
            font-weight: 500;
        }

        .hashtags {
            margin-bottom: 25px;
        }

        .hashtag {
            display: inline-block;
            background: linear-gradient(135deg, rgba(79, 172, 254, 0.2) 0%, rgba(0, 242, 254, 0.2) 100%);
            color: #3182ce;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            margin: 3px 6px 3px 0;
            border: 1px solid rgba(79, 172, 254, 0.3);
            transition: var(--transition);
        }

        .hashtag:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(79, 172, 254, 0.3);
        }

        .card-actions {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 12px;
            margin-top: 25px;
        }

        .card-button {
            padding: 14px 20px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 700;
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
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        .view-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6);
        }

        .edit-button {
            background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(237, 137, 54, 0.4);
        }

        .edit-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(237, 137, 54, 0.6);
        }

        .delete-button {
            background: linear-gradient(135deg, #f56565 0%, #e53e3e 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(245, 101, 101, 0.4);
        }

        .delete-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(245, 101, 101, 0.6);
        }

        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 100px 20px;
            color: #718096;
        }

        .empty-state-icon {
            font-size: 5em;
            margin-bottom: 30px;
            opacity: 0.6;
        }

        .empty-state h3 {
            font-size: 2em;
            margin-bottom: 15px;
            color: #4a5568;
            font-weight: 800;
        }

        .empty-state p {
            font-size: 1.2em;
            margin-bottom: 40px;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.6;
        }

        .success-message, .error-display {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px 30px;
            border-radius: var(--border-radius);
            font-weight: 600;
            text-align: center;
            box-shadow: var(--card-shadow);
            animation: slideInDown 0.5s ease;
        }

        .success-message {
            background: linear-gradient(135deg, #68d391 0%, #38a169 100%);
            color: white;
        }

        .error-display {
            background: linear-gradient(135deg, #fc8181 0%, #e53e3e 100%);
            color: white;
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

        .publication-card {
            animation: fadeInUp 0.6s ease forwards;
        }

        @media (max-width: 768px) {
            .gallery {
                grid-template-columns: 1fr;
                padding: 0 15px;
            }
            
            .page-header h1 {
                font-size: 2.2em;
            }
            
            .stats-container {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .card-actions {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .book-details {
                grid-template-columns: 1fr;
            }
        }

        /* Debug info styles */
        .debug-info {
            background: rgba(255, 255, 255, 0.95);
            border: 2px solid #ed8936;
            border-radius: var(--border-radius);
            padding: 20px;
            margin: 20px auto;
            max-width: 1200px;
            color: #2d3748;
        }

        .debug-info h4 {
            color: #dd6b20;
            margin-top: 0;
        }

        .debug-info pre {
            background: #f7fafc;
            padding: 15px;
            border-radius: 8px;
            overflow-x: auto;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <!-- Tu topbar existente -->
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

    <!-- Tu header existente -->
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

    <div class="page-header">
        <h1>📚 Mis Publicaciones</h1>
        <p>Gestiona y visualiza toda tu biblioteca digital</p>
    </div>

    <!-- Estadísticas mejoradas -->
    <?php if (!empty($publicaciones)): ?>
    <div class="stats-container">
        <div class="stat-card">
            <span class="stat-number"><?php echo count($publicaciones); ?></span>
            <span class="stat-label">Total Publicaciones</span>
        </div>
        <div class="stat-card">
            <span class="stat-number"><?php echo count(array_filter($publicaciones, function($p) { return $p['precio'] == 0; })); ?></span>
            <span class="stat-label">Libros Gratis</span>
        </div>
        <div class="stat-card">
            <span class="stat-number"><?php echo count(array_filter($publicaciones, function($p) { return !empty($p['linkVideo']); })); ?></span>
            <span class="stat-label">Con Video</span>
        </div>
        <div class="stat-card">
            <span class="stat-number"><?php echo count(array_filter($publicaciones, function($p) { return $p['precio'] > 0; })); ?></span>
            <span class="stat-label">De Pago</span>
        </div>
    </div>
    <?php endif; ?>

    <div class="new-button-container">
        <a href="NuevaPublicacion.php" class="new-button">
            <svg width="24" height="24" fill="white" viewBox="0 0 24 24">
                <path d="M19 13H13V19H11V13H5V11H11V5H13V11H19V13Z"/>
            </svg>
            <span>Nueva Publicación</span>
        </a>
    </div>

    <!-- Mensajes de éxito y error -->
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
                        <?php else: ?>
                            <div style="display: flex; align-items: center; justify-content: center; height: 100%; background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);">
                                <svg width="100" height="100" fill="rgba(0,0,0,0.2)" viewBox="0 0 24 24">
                                    <path d="M18 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 4h5v8l-2.5-1.5L6 12V4z"/>
                                </svg>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($publicacion['linkVideo'])): ?>
                            <div class="video-indicator">
                                <svg width="12" height="12" fill="white" viewBox="0 0 24 24">
                                    <path d="M8 5v14l11-7z"/>
                                </svg>
                                Video Disponible
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
                        <div class="publication-date">
                            📅 <?php echo formatearFecha($publicacion['fechaCreacion']); ?>
                        </div>
                        
                        <div class="card-header">
                            <h3 class="card-title"><?php echo htmlspecialchars($publicacion['titulo']); ?></h3>
                            <div class="card-author">
                                <strong>✍️ <?php echo htmlspecialchars($publicacion['autor']); ?></strong>
                            </div>
                            <div class="card-author" style="font-size: 0.9em; opacity: 0.8;">
                                👤 <?php echo htmlspecialchars($publicacion['nombreUsuario']); ?>
                            </div>
                            <div class="price-badge <?php echo ($publicacion['precio'] == 0) ? 'free' : ''; ?>">
                                <?php echo formatearPrecio($publicacion['precio']); ?>
                            </div>
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
                            <a href="ver_publicacion.php?id=<?php echo htmlspecialchars($publicacion['idPublicacion']); ?>" class="card-button view-button">
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

    <!-- Tu bottombar existente -->
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
        document.addEventListener('DOMContentLoaded', function() {
            // Funcionalidad de cambio de imágenes
            document.querySelectorAll('.publication-card').forEach(card => {
                const indicators = card.querySelectorAll('.image-indicator');
                const mainImage = card.querySelector('.main-image');
                
                if (indicators.length > 1 && mainImage) {
                    indicators.forEach((indicator, index) => {
                        indicator.addEventListener('click', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            
                            // Remover clase active de todos los indicadores
                            indicators.forEach(ind => ind.classList.remove('active'));
                            
                            // Agregar clase active al clickeado
                            this.classList.add('active');
                            
                            // Cambiar imagen
                            const newSrc = this.dataset.src;
                            if (newSrc) {
                                mainImage.style.opacity = '0.5';
                                setTimeout(() => {
                                    mainImage.src = '../../uploads/' + newSrc;
                                    mainImage.style.opacity = '1';
                                }, 200);
                            }
                        });
                        
                        // Efecto hover en indicadores
                        indicator.addEventListener('mouseenter', function() {
                            this.style.transform = 'scale(1.3)';
                        });
                        
                        indicator.addEventListener('mouseleave', function() {
                            if (!this.classList.contains('active')) {
                                this.style.transform = 'scale(1)';
                            }
                        });
                    });
                }
            });

            // Animación de las estadísticas con conteo
            const observerOptions = {
                threshold: 0.5,
                rootMargin: '0px 0px -10px 0px'
            };

            const statsObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const statNumber = entry.target.querySelector('.stat-number');
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
                        
                        statsObserver.unobserve(entry.target);
                    }
                });
            }, observerOptions);

            // Observar las tarjetas de estadísticas
            document.querySelectorAll('.stat-card').forEach(card => {
                statsObserver.observe(card);
            });

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
            document.querySelectorAll('.publication-card').forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(50px)';
                card.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
                card.style.transitionDelay = `${index * 0.1}s`;
                
                cardObserver.observe(card);
            });

            // Efecto parallax sutil en las tarjetas
            window.addEventListener('scroll', () => {
                const scrolled = window.pageYOffset;
                const parallaxElements = document.querySelectorAll('.publication-card');
                
                parallaxElements.forEach((element, index) => {
                    const rate = scrolled * -0.02 * (index % 3 + 1);
                    element.style.transform = `translateY(${rate}px)`;
                });
            });

            // Mejorar la confirmación de eliminación
            document.querySelectorAll('form[action="eliminar_publicacion.php"]').forEach(form => {
                form.addEventListener('submit', function(e) {
                    const titulo = this.closest('.publication-card').querySelector('.card-title').textContent;
                    
                    // Crear modal de confirmación personalizado
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

            // Búsqueda en tiempo real (si tienes implementada la funcionalidad)
            const searchInput = document.querySelector('.search-bar input');
            if (searchInput) {
                let searchTimeout;
                
                searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        const searchTerm = this.value.toLowerCase().trim();
                        const cards = document.querySelectorAll('.publication-card');
                        let visibleCount = 0;
                        
                        cards.forEach(card => {
                            const title = card.querySelector('.card-title').textContent.toLowerCase();
                            const author = card.querySelector('.card-author').textContent.toLowerCase();
                            const description = card.querySelector('.card-description')?.textContent.toLowerCase() || '';
                            const hashtags = Array.from(card.querySelectorAll('.hashtag')).map(tag => tag.textContent.toLowerCase()).join(' ');
                            
                            const matches = title.includes(searchTerm) || 
                                          author.includes(searchTerm) || 
                                          description.includes(searchTerm) ||
                                          hashtags.includes(searchTerm);
                            
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
                    }, 300);
                });
            }

            // Mensaje de carga automático al crear nueva publicación
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

            // Auto-hide mensajes de éxito/error después de 8 segundos
            const messages = document.querySelectorAll('.success-message, .error-display');
            messages.forEach(message => {
                setTimeout(() => {
                    message.style.animation = 'slideOutUp 0.5s ease forwards';
                    setTimeout(() => {
                        message.remove();
                    }, 500);
                }, 8000);
            });

            console.log('✅ Página de publicaciones cargada correctamente');
            console.log(`📚 Se encontraron ${document.querySelectorAll('.publication-card').length} publicaciones`);
        });

        // CSS para animaciones adicionales
        const additionalStyles = document.createElement('style');
        additionalStyles.textContent = `
            @keyframes spinning {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
            
            .spinning {
                animation: spinning 1s linear infinite;
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
            
            .no-results-message {
                grid-column: 1 / -1;
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(20px);
                border-radius: var(--border-radius);
                padding: 60px 40px;
                text-align: center;
                border: 2px dashed rgba(102, 126, 234, 0.3);
                animation: fadeInUp 0.5s ease forwards;
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
        `;
        document.head.appendChild(additionalStyles);
    </script>
</body>
</html>