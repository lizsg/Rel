<?php
session_start();

if(!isset($_SESSION['usuario']) || empty($_SESSION['usuario']) || !isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");

    exit();
}

require_once __DIR__ . '/../../config/database.php';

// Verificar que se recibió el ID de la publicación
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['mensaje_error'] = "ID de publicación no válido";
    header("Location: publicaciones.php");
    exit();
}

$publicacion_id = (int)$_GET['id'];
$publicacion = null;
$hashtags = [];
$errorMessage = '';

try {
    $conn = new mysqli(SERVER_NAME, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset("utf8mb4");

    if ($conn->connect_error) {
        throw new Exception("Error de conexión: " . $conn->connect_error);
    }

    // Verificar que la publicación pertenece al usuario actual
    $stmt = $conn->prepare("
        SELECT 
            p.idPublicacion,
            p.idLibro,
            p.precio,
            p.fechaCreacion,
            p.idUsuario,
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
            l.fechaPublicacion
        FROM Publicaciones p
        JOIN Libros l ON p.idLibro = l.idLibro
        WHERE p.idPublicacion = ? AND p.idUsuario = ?
    ");
    
    $stmt->bind_param("ii", $publicacion_id, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['mensaje_error'] = "Publicación no encontrada o no tienes permisos para verla";
        header("Location: publicaciones.php");
        exit();
    }
    
    $publicacion = $result->fetch_assoc();

    // Obtener hashtags del libro
    $hashtagStmt = $conn->prepare("
        SELECT h.texto as hashtag
        FROM LibroHashtags lh
        INNER JOIN Hashtags h ON lh.idHashtag = h.idHashtag
        WHERE lh.idLibro = ?
    ");
    
    $hashtagStmt->bind_param("i", $publicacion['idLibro']);
    $hashtagStmt->execute();
    $hashtagResult = $hashtagStmt->get_result();
    
    while ($hashtagRow = $hashtagResult->fetch_assoc()) {
        $hashtags[] = $hashtagRow['hashtag'];
    }

    $stmt->close();
    $hashtagStmt->close();
    $conn->close();

} catch (Exception $e) {
    $errorMessage = "Error al cargar los detalles: " . $e->getMessage();
    error_log($errorMessage);
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $publicacion ? htmlspecialchars($publicacion['titulo']) : 'Detalle'; ?> | RELEE</title>
    <link rel="stylesheet" href="../../assets/css/home-styles.css">
    <link rel="stylesheet" href="../../assets/css/chat-styles.css">
    <style>
    :root {
        --primary-brown: #6b4226;
        --secondary-brown: #8b5a3c;
        --light-brown: #d6c1b2;
        --cream-bg: #f8f6f3;
        --green-primary: #a3b18a;
        --green-secondary: #588157;
        --green-dark: #3a5a40;
        --text-primary: #2c2016;
        --text-secondary: #6f5c4d;
        --card-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
        --border-radius: 20px;
        --transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }

    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background: linear-gradient(135deg, var(--cream-bg) 0%, #f0ede8 100%);
        color: var(--text-primary);
        margin: 0;
        padding: 20px;
        padding-bottom: 50px; /* Espacio para la barra inferior */
        padding-top: 50px; /* Espacio para la topbar */
        min-height: 100vh;
    }

    /* Video Container */
    .video-container {
        width: 50%;
        max-width: 50px; /* Máximo tamaño del video */
        margin: 0 auto; /* Centrado horizontal */
        background: #000; /* Fondo negro */
        border-radius: 10px; /* Bordes redondeados */
        overflow: hidden; /* Esconde los elementos fuera del contorno */
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2); /* Sombra */
        margin-top: 20px; /* Separación superior */
        margin-bottom: 20px; /* Separación inferior */
    }

    /* Estilo para el video dentro del contenedor */
   .video-container video {
    width: 100%;
            max-height: 550px;
            border-radius: 15px;
            object-fit: cover;
}

    /* Topbar */
    .topbar {
        background: linear-gradient(135deg, #f5f0ea 0%, #ede6dd 100%);
        backdrop-filter: blur(10px);
        padding: 8px 25px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 15px;
        border-bottom: 1px solid rgba(211, 197, 184, 0.3);
        box-shadow: 0 2px 20px rgba(0, 0, 0, 0.05);
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 1000;
    }

    /* Logo styles */
    .logo-container {
        display: flex;
        align-items: center;
    }

    .logo-icon {
        width: 65px;
        height: 65px;
        position: relative;
        animation: logoFloat 3s ease-in-out infinite;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 3px 10px rgba(107, 66, 38, 0.25);
        transition: all 0.3s ease;
    }

    @keyframes logoFloat {
        0%, 100% { transform: translateY(0px); }
        50% { transform: translateY(-2px); }
    }

    .logo-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: center;
        transition: transform 0.3s ease;
    }

    .logo-icon:hover {
        transform: translateY(-2px) scale(1.05);
        box-shadow: 0 8px 20px rgba(107, 66, 38, 0.35);
    }

    .logo-icon:hover .logo-image {
        transform: scale(1.1);
    }

    /* Right side icons container */
    .topbar-right {
        display: flex;
        align-items: center;
        gap: 15px;
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
        text-decoration: none;
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

    .detail-container {
        max-width: 1200px;
        margin: 0 auto;
        background: rgba(255, 253, 252, 0.95);
        backdrop-filter: blur(20px);
        border-radius: var(--border-radius);
        box-shadow: var(--card-shadow);
        overflow: hidden;
    }

    .detail-header {
        background: linear-gradient(135deg, var(--green-secondary) 0%, var(--green-dark) 100%);
        color: white;
        padding: 30px 40px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .back-button {
        background: rgba(255, 255, 255, 0.2);
        color: white;
        border: none;
        padding: 12px 20px;
        border-radius: 25px;
        text-decoration: none;
        font-weight: 600;
        transition: var(--transition);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .back-button:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: translateY(-2px);
    }

    .detail-title {
        font-size: 2.5em;
        font-weight: 800;
        margin: 0;
        text-align: center;
        flex: 1;
        margin: 0 20px;
    }

    .detail-content {
        display: grid;
        grid-template-columns: 1fr 2fr;
        gap: 40px;
        padding: 40px;
    }

    .image-section {
        position: sticky;
        top: 20px;
        height: fit-content;
    }

    .main-image-container {
        width: 100%;
        height: 400px;
        border-radius: var(--border-radius);
        overflow: hidden;
        box-shadow: var(--card-shadow);
        margin-bottom: 20px;
        background: linear-gradient(135deg, var(--light-brown) 0%, #c4a68a 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
    }

    .main-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s ease;
    }

    .main-image:hover {
        transform: scale(1.05);
    }

    .thumbnail-container {
        display: flex;
        gap: 10px;
        justify-content: center;
    }

    .thumbnail {
        width: 80px;
        height: 80px;
        border-radius: 12px;
        cursor: pointer;
        border: 3px solid transparent;
        transition: var(--transition);
        object-fit: cover;
    }

    .thumbnail.active {
        border-color: var(--green-secondary);
        transform: scale(1.1);
    }

    .thumbnail:hover {
        border-color: var(--green-primary);
    }

    .info-section {
        display: flex;
        flex-direction: column;
        gap: 30px;
    }

    .book-info {
        background: linear-gradient(135deg, rgba(163, 177, 138, 0.1) 0%, rgba(88, 129, 87, 0.1) 100%);
        padding: 30px;
        border-radius: var(--border-radius);
        border-left: 5px solid var(--green-secondary);
    }

    .book-title {
        font-size: 2.2em;
        font-weight: 800;
        color: var(--text-primary);
        margin-bottom: 10px;
    }

    .book-author {
        font-size: 1.3em;
        color: var(--text-secondary);
        font-weight: 600;
        margin-bottom: 20px;
    }

    .price-display {
        display: inline-block;
        background: linear-gradient(135deg, var(--primary-brown) 0%, var(--secondary-brown) 100%);
        color: white;
        padding: 15px 25px;
        border-radius: 30px;
        font-weight: 700;
        font-size: 1.2em;
        margin-bottom: 20px;
        box-shadow: 0 4px 15px rgba(107, 66, 38, 0.3);
    }

    .price-display.free {
        background: linear-gradient(135deg, var(--green-secondary) 0%, var(--green-dark) 100%);
    }

    .description-section {
        background: rgba(255, 255, 255, 0.7);
        padding: 25px;
        border-radius: var(--border-radius);
        border: 1px solid rgba(163, 177, 138, 0.2);
    }

    .description-title {
        font-size: 1.3em;
        font-weight: 700;
        color: var(--green-dark);
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .description-text {
        line-height: 1.8;
        color: var(--text-secondary);
        font-size: 1.1em;
    }

    .details-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
    }

    .detail-card {
        background: rgba(255, 255, 255, 0.8);
        padding: 20px;
        border-radius: 15px;
        border-left: 4px solid var(--green-secondary);
        transition: var(--transition);
    }

    .detail-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    }

    .detail-card-label {
        font-weight: 700;
        color: var(--green-dark);
        font-size: 0.9em;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 5px;
    }

    .detail-card-value {
        color: var(--text-primary);
        font-weight: 600;
        font-size: 1.1em;
    }

    .hashtags-section {
        background: rgba(255, 255, 255, 0.7);
        padding: 25px;
        border-radius: var(--border-radius);
    }

    .hashtags-title {
        font-size: 1.2em;
        font-weight: 700;
        color: var(--green-dark);
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .hashtag {
        display: inline-block;
        background: linear-gradient(135deg, rgba(163, 177, 138, 0.2) 0%, rgba(88, 129, 87, 0.2) 100%);
        color: var(--green-dark);
        padding: 8px 16px;
        border-radius: 20px;
        font-weight: 600;
        margin: 5px 5px 5px 0;
        border: 1px solid rgba(163, 177, 138, 0.3);
        transition: var(--transition);
    }

    .hashtag:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(163, 177, 138, 0.3);
    }

    .actions-section {
        background: linear-gradient(135deg, rgba(107, 66, 38, 0.1) 0%, rgba(139, 90, 60, 0.1) 100%);
        padding: 30px;
        border-radius: var(--border-radius);
        display: flex;
        gap: 15px;
        justify-content: center;
    }

    .action-button {
        padding: 15px 25px;
        border: none;
        border-radius: 12px;
        font-weight: 600;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 10px;
        transition: var(--transition);
        font-size: 1em;
        cursor: pointer;
    }

    .edit-btn {
        background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);
        color: white;
        box-shadow: 0 4px 15px rgba(237, 137, 54, 0.3);
    }

    .edit-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(237, 137, 54, 0.4);
    }

    .delete-btn {
        background: linear-gradient(135deg, #f56565 0%, #e53e3e 100%);
        color: white;
        box-shadow: 0 4px 15px rgba(245, 101, 101, 0.3);
    }

    .delete-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(245, 101, 101, 0.4);
    }

    .video-section {
        background: rgba(255, 255, 255, 0.7);
        padding: 25px;
        border-radius: var(--border-radius);
        text-align: center;
    }

    .video-link {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        background: linear-gradient(135deg, var(--primary-brown) 0%, var(--secondary-brown) 100%);
        color: white;
        padding: 15px 25px;
        border-radius: 25px;
        text-decoration: none;
        font-weight: 600;
        transition: var(--transition);
        cursor: pointer;
        border: none;
        font-size: 1em;
    }

    .video-link:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(107, 66, 38, 0.4);
    }

    .publication-meta {
        background: rgba(255, 255, 255, 0.7);
        padding: 20px;
        border-radius: var(--border-radius);
        text-align: center;
        border: 1px solid rgba(163, 177, 138, 0.2);
    }

    .publication-date {
        color: var(--text-secondary);
        font-weight: 600;
        font-size: 1.1em;
    }

    .error-message {
        background: linear-gradient(135deg, rgba(245, 101, 101, 0.9) 0%, rgba(229, 62, 62, 0.9) 100%);
        color: white;
        padding: 20px;
        border-radius: var(--border-radius);
        margin: 20px 0;
        text-align: center;
        font-weight: 600;
    }

    /* Video overlay styles - MEJORADOS */
    .video-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.9);
        z-index: 2000;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 20px;
        backdrop-filter: blur(5px);
        animation: fadeIn 0.3s ease-out;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }

    .video-container {
        /* Inicialmente oculto */
        /* El resto del estilo para el contenedor del video */

        position: relative;
        width: 90%;
        max-width: 900px;
        background: white;
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
        animation: scaleIn 0.3s ease-out;
    }

    @keyframes scaleIn {
        from {
            transform: scale(0.8);
            opacity: 0;
        }
        to {
            transform: scale(1);
            opacity: 1;
        }
    }

    .video-header {
        background: var(--primary-brown);
        color: white;
        padding: 15px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .video-title {
        font-weight: 600;
        margin: 0;
        font-size: 1.1em;
    }

    .close-video {
        background: rgba(255, 255, 255, 0.1);
        border: none;
        color: white;
        font-size: 24px;
        cursor: pointer;
        padding: 8px 12px;
        border-radius: 8px;
        transition: background 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
    }

    .close-video:hover {
        background: rgba(255, 255, 255, 0.2);
        transform: scale(1.1);
    }

    .video-content {
        position: relative;
        width: 100%;
        height: 500px;
        background: #000;
    }

    .video-content iframe {
        width: 100%;
        height: 100%;
        border: none;
    }
     .rating-section {
            margin-top: 25px;
            padding: 25px;
            background: linear-gradient(135deg, rgba(163, 177, 138, 0.08) 0%, rgba(163, 177, 138, 0.12) 100%);
            border-radius: 18px;
            border: 2px solid rgba(163, 177, 138, 0.15);
            position: relative;
            overflow: hidden;
        }
        .rating-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, var(--green-primary) 0%, var(--green-secondary) 100%);
            opacity: 0.8;
        }
         .current-rating-display {
            display: flex;
            align-items: center;
            gap: 12px;
            background: rgba(255, 255, 255, 0.7);
            padding: 12px 18px;
            border-radius: 12px;
            border: 1px solid rgba(163, 177, 138, 0.2);
        }
         .current-rating-display {
                align-self: stretch;
            }
            .rating-info {
            color: var(--text-secondary);
            font-size: 0.9em;
            font-weight: 600;
        }
</style>

</head>
<script>
    displayUserStats(stats) {
            const rating = parseFloat(stats.puntuacionPromedio) || 0;
            const totalRatings = parseInt(stats.totalCalificaciones) || 0;

            // Mostrar estrellas
            this.elements.starsDisplay.innerHTML = this.generateStarsHTML(rating);

            // Mostrar información
            if (totalRatings > 0) {
                this.elements.ratingInfo.innerHTML = `
                    <strong>${rating.toFixed(1)}/5</strong> 
                    (${totalRatings} calificación${totalRatings !== 1 ? 'es' : ''})
                `;
            } else {
                this.elements.ratingInfo.innerHTML = 'Sin calificaciones aún';
            }
        }

</script>
<body>
    <!-- Barra superior -->
    <div class="topbar">
        <!-- Logo en el lado izquierdo -->
        <div class="logo-container">
            <a href="../home.php" class="logo-link" title="Ir al inicio">
                <div class="logo-icon">
                    <img src="../../assets/images/REELEE.jpeg" alt="RELEE Logo" class="logo-image" />
                </div>
            </a>
        </div>

        <!-- Iconos del lado derecho -->
        <div class="topbar-right">
            <div class="topbar-icon" title="Chat">
                <svg width="16" height="16" fill="white" viewBox="0 0 24 24">
                    <path d="M12 2c.55 0 1 .45 1 1v1h4a2 2 0 0 1 2 2v2h1a1 1 0 1 1 0 2h-1v6a3 3 0 0 1-3 3h-1v1a1 1 0 1 1-2 0v-1H9v1a1 1 0 1 1-2 0v-1H6a3 3 0 0 1-3-3v-6H2a1 1 0 1 1 0-2h1V6a2 2 0 0 1 2-2h4V3c0-.55.45-1 1-1zm-5 9a1 1 0 1 0 0 2 1 1 0 0 0 0-2zm10 0a1 1 0 1 0 0 2 1 1 0 0 0 0-2z"/>
                </svg>
            </div>

            <a href="../chat/chat.php" class="topbar-icon" title="Chat 2">
                <svg width="16" height="16" fill="white" viewBox="0 0 24 24">
                    <path d="M20 2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h4l4 4 4-4h4c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/>
                </svg>
            </a>

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
    </div>

    <!-- Incluir componente de chat -->
    <?php include '../../includes/chat-component.php'; ?>

    <!-- Video overlay -->
    <div class="video-overlay" id="videoOverlay">
        <div class="video-container">
            <div class="video-header">
                <h3 class="video-title">Video del Libro</h3>
                <button class="close-video" onclick="closeVideo()">&times;</button>
            </div>
            <div class="video-content">
                <iframe id="videoFrame" src="" allowfullscreen></iframe>
            </div>
        </div>
    </div>

    <?php if (!empty($errorMessage)): ?>
        <div class="error-message">
            ❌ <?php echo htmlspecialchars($errorMessage); ?>
        </div>
        <div style="text-align: center; margin: 20px;">
            <a href="publicaciones.php" class="back-button">Volver a Publicaciones</a>
        </div>
    <?php elseif ($publicacion): ?>
        <div class="detail-container">
            <div class="detail-header">
                <a href="publicaciones.php" class="back-button">
                    <svg width="16" height="16" fill="white" viewBox="0 0 24 24">
                        <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.42-1.41L7.83 13H20v-2z"/>
                    </svg>
                    Volver
                </a>
                <h1 class="detail-title">Detalles de Publicación</h1>
                <div style="width: 100px;"></div> 
            </div>

            <div class="detail-content">
                <div class="image-section">
                    <div class="main-image-container">
                        <?php if (!empty($publicacion['linkImagen1'])): ?>
                            <img id="mainImage" src="../../uploads/<?php echo htmlspecialchars($publicacion['linkImagen1']); ?>" 
                                 alt="<?php echo htmlspecialchars($publicacion['titulo']); ?>" class="main-image">
                        <?php else: ?>
                            <div style="font-size: 4em; opacity: 0.5;">📚</div>
                        <?php endif; ?>
                    </div>

                    <?php 
                    $imagenes = array_filter([
                        $publicacion['linkImagen1'],
                        $publicacion['linkImagen2'],
                        $publicacion['linkImagen3']
                    ]);
                    if (count($imagenes) > 1): ?>
                        <div class="thumbnail-container">
                            <?php foreach ($imagenes as $index => $imagen): ?>
                                <img src="../../uploads/<?php echo htmlspecialchars($imagen); ?>" 
                                     alt="Imagen <?php echo $index + 1; ?>" 
                                     class="thumbnail <?php echo $index === 0 ? 'active' : ''; ?>"
                                     onclick="changeMainImage(this, '<?php echo htmlspecialchars($imagen); ?>')">
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($publicacion['linkVideo'])): ?>
                        <div class="video-container">
                            <video controls>
                                <source src="../../uploads/<?php echo htmlspecialchars($publicacion['linkVideo']); ?>" type="video/mp4">
                                Tu navegador no soporta videos HTML5.
                            </video>
                        </div>
                    <?php endif; ?>
                </div>
                

                <div class="info-section">
                    <div class="book-info">
                        <h2 class="book-title"><?php echo htmlspecialchars($publicacion['titulo']); ?></h2>
                        <div class="book-author">✍️ <?php echo htmlspecialchars($publicacion['autor']); ?></div>
                        <div class="price-display <?php echo ($publicacion['precio'] == 0) ? 'free' : ''; ?>">
                            <?php echo formatearPrecio($publicacion['precio']); ?>
                        </div>
                        <div class="publication-meta">
                            <div class="publication-date">
                                📅 Publicado <?php echo tiempoTranscurrido($publicacion['fechaCreacion']); ?>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($publicacion['descripcion'])): ?>
                        <div class="description-section">
                            <h3 class="description-title">
                                📖 Descripción
                            </h3>
                            <div class="description-text">
                                <?php echo nl2br(htmlspecialchars($publicacion['descripcion'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="details-grid">
                        <?php if (!empty($publicacion['editorial'])): ?>
                            <div class="detail-card">
                                <div class="detail-card-label">🏢 Editorial</div>
                                <div class="detail-card-value"><?php echo htmlspecialchars($publicacion['editorial']); ?></div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($publicacion['categoria'])): ?>
                            <div class="detail-card">
                                <div class="detail-card-label">📚 Categoría</div>
                                <div class="detail-card-value"><?php echo htmlspecialchars($publicacion['categoria']); ?></div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($publicacion['tipoPublico'])): ?>
                            <div class="detail-card">
                                <div class="detail-card-label">👥 Público Objetivo</div>
                                <div class="detail-card-value"><?php echo htmlspecialchars($publicacion['tipoPublico']); ?></div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($publicacion['paginas'])): ?>
                            <div class="detail-card">
                                <div class="detail-card-label">📄 Páginas</div>
                                <div class="detail-card-value"><?php echo htmlspecialchars($publicacion['paginas']); ?></div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($publicacion['edicion'])): ?>
                            <div class="detail-card">
                                <div class="detail-card-label">🔢 Edición</div>
                                <div class="detail-card-value"><?php echo htmlspecialchars($publicacion['edicion']); ?></div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($publicacion['base']) || !empty($publicacion['altura'])): ?>
                            <div class="detail-card">
                                <div class="detail-card-label">📏 Dimensiones</div>
                                <div class="detail-card-value">
                                    <?php 
                                    if (!empty($publicacion['base']) && !empty($publicacion['altura'])) {
                                        echo htmlspecialchars($publicacion['base']) . ' x ' . htmlspecialchars($publicacion['altura']) . ' cm';
                                    } elseif (!empty($publicacion['base'])) {
                                        echo 'Base: ' . htmlspecialchars($publicacion['base']) . ' cm';
                                    } else {
                                        echo 'Altura: ' . htmlspecialchars($publicacion['altura']) . ' cm';
                                    }
                                    ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($publicacion['fechaPublicacion'])): ?>
                            <div class="detail-card">
                                <div class="detail-card-label">📅 Fecha de Publicación</div>
                                <div class="detail-card-value"><?php echo formatearFecha($publicacion['fechaPublicacion']); ?></div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($hashtags)): ?>
                        <div class="hashtags-section">
                            <h3 class="hashtags-title">
                                🏷️ Hashtags
                            </h3>
                            <?php foreach ($hashtags as $hashtag): ?>
                                <span class="hashtag">#<?php echo htmlspecialchars($hashtag); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                     
                    <!-- Mostrar formulario de calificación si no ha calificado -->



<!-- Video Overlay (inicialmente oculto) -->
<div class="video-overlay" id="videoOverlay">
    <div class="video-container">
        <div class="video-header">
            <h3 class="video-title">Video del Libro</h3>
            <button class="close-video" onclick="closeVideo()">&times;</button>
        </div>
        <div class="video-content">
            <iframe id="videoFrame" src="" allowfullscreen></iframe>
        </div>
    </div>
</div>

                    <div class="actions-section">
                        <a href="editar_publicacion.php?id=<?php echo htmlspecialchars($publicacion['idPublicacion']); ?>" 
                           class="action-button edit-btn">
                            <svg width="18" height="18" fill="white" viewBox="0 0 24 24">
                                <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
                            </svg>
                            Editar Publicación
                        </a>
                        
                        <form action="eliminar_publicacion.php" method="POST" 
                              onsubmit="return confirm('¿Estás seguro de eliminar esta publicación?\n\n⚠️ Esta acción no se puede deshacer.');" 
                              style="display: inline;">
                            <input type="hidden" name="publicacion_id" value="<?php echo htmlspecialchars($publicacion['idPublicacion']); ?>">
                            <button type="submit" class="action-button delete-btn">
                                <svg width="18" height="18" fill="white" viewBox="0 0 24 24">
                                    <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                                </svg>
                                Eliminar Publicación
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Barra inferior -->
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
        function changeMainImage(thumbnail, imageSrc) {
            // Remover clase active de todas las miniaturas
            document.querySelectorAll('.thumbnail').forEach(thumb => {
                thumb.classList.remove('active');
            });
            
            // Agregar clase active a la miniatura clickeada
            thumbnail.classList.add('active');
            
            // Cambiar imagen principal con efecto
            const mainImage = document.getElementById('mainImage');
            if (mainImage) {
                mainImage.style.opacity = '0.5';
                setTimeout(() => {
                    mainImage.src = '../../uploads/' + imageSrc;
                    mainImage.style.opacity = '1';
                }, 200);
            }
        }

        function showVideo(videoUrl) {
            // Convertir URL de YouTube a formato embed si es necesario
            let embedUrl = videoUrl;
            
            // Si es un link normal de YouTube, convertirlo a embed
            if (videoUrl.includes('youtube.com/watch?v=')) {
                const videoId = videoUrl.split('v=')[1].split('&')[0];
                embedUrl = `https://www.youtube.com/embed/${videoId}`;
            } else if (videoUrl.includes('youtu.be/')) {
                const videoId = videoUrl.split('youtu.be/')[1].split('?')[0];
                embedUrl = `https://www.youtube.com/embed/${videoId}`;
            }
            
            // Establecer la URL del iframe
            document.getElementById('videoFrame').src = embedUrl;
            
            // Mostrar overlay
            const overlay = document.getElementById('videoOverlay');
            overlay.style.display = 'flex';
            
            // Prevenir scroll del body
            document.body.style.overflow = 'hidden';
        }

        function closeVideo() {
            // Ocultar overlay
            document.getElementById('videoOverlay').style.display = 'none';
            
            // Limpiar iframe
            document.getElementById('videoFrame').src = '';
            
            // Restaurar scroll del body
            document.body.style.overflow = 'auto';
        }

        // Cerrar video al hacer clic fuera del contenedor
        document.getElementById('videoOverlay').addEventListener('click', function(e) {
            if (e.target === this) {
                closeVideo();
            }
        });

        // Cerrar video con tecla Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeVideo();
            }
        });

        // Manejo de errores de imágenes
        document.querySelectorAll('img').forEach(img => {
            img.addEventListener('error', function() {
                if (this.classList.contains('main-image')) {
                    this.closest('.main-image-container').innerHTML = '<div style="font-size: 4em; opacity: 0.5;">📚</div>';
                } else if (this.classList.contains('thumbnail')) {
                    this.style.display = 'none';
                }
            });
        });

        // Animación de entrada
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.querySelector('.detail-container');
            if (container) {
                container.style.opacity = '0';
                container.style.transform = 'translateY(30px)';
                container.style.transition = 'all 0.6s ease';
                
                setTimeout(() => {
                    container.style.opacity = '1';
                    container.style.transform = 'translateY(0)';
                }, 100);
            }
        });
    </script>
</body>
</html>