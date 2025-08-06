<?php
    session_start();

    // Redireccionar si el usuario no ha iniciado sesión
    if (!isset($_SESSION['usuario']) || empty($_SESSION['usuario']) || !isset($_SESSION['user_id'])) {
        header("Location: ../auth/login.php");
        exit();
    }

    require_once __DIR__ . '/../../config/database.php';

    $userId = $_SESSION['user_id']; 
    $publicacionId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $publicacion = null;
    $error = '';

    if ($publicacionId <= 0) {
        header("Location: buscador.php");
        exit();
    }

    try {
        $conn = new mysqli(SERVER_NAME, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            throw new Exception("Conexión fallida: " . $conn->connect_error);
        }

        // Obtener los detalles completos de la publicación
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
            l.linkImagen1,
            l.linkImagen2,
            l.linkImagen3,
            l.linkVideo,
            l.editorial,
            l.edicion,
            l.categoria,
            l.tipoPublico,
            l.base,
            l.altura,
            l.paginas,
            l.fechaPublicacion,
            u.userName as nombreUsuario,
            u.nombre
        FROM Publicaciones p
        JOIN Libros l ON p.idLibro = l.idLibro
        JOIN Usuarios u ON p.idUsuario = u.idUsuario
        WHERE p.idPublicacion = ?
    ";

        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $publicacionId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $publicacion = $result->fetch_assoc();
            
            // Verificar si es su propia publicación DESPUÉS de obtener los datos
            if ($publicacion['idUsuario'] == $userId) {
                $error = "No puedes ver los detalles de tus propias publicaciones aquí.";
                $publicacion = null;
            }
        } else {
            $error = "Publicación no encontrada. ID: " . $publicacionId;
            error_log("Publicación no encontrada. ID: " . $publicacionId . " | Usuario: " . $userId);
        }

        $stmt->close();
        $conn->close();

    } catch (Exception $e) {
        $error = "Error al cargar la publicación: " . $e->getMessage();
        error_log("Error en detalle_publicacion.php: " . $e->getMessage());
    }

    if (!$publicacion) {
        header("Location: buscador.php");
        exit();
    }
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php echo htmlspecialchars($publicacion['titulo']); ?> | RELEE</title>
    
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

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f8f6f3 0%, #f0ede8 100%);
            color: var(--text-primary);
            position: relative;
            padding-top: 85px;
            padding-bottom: 80px;
            min-height: 100vh;
            line-height: 1.6;
        }

        /* Topbar mejorada */
        .topbar {
            background: linear-gradient(135deg, rgba(245, 240, 234, 0.95) 0%, rgba(237, 230, 221, 0.95) 100%);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            padding: 12px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            border-bottom: 1px solid rgba(211, 197, 184, 0.3);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.08);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
        }

        .logo-container {
            display: flex;
            align-items: center;
        }

        .logo-icon {
            width: 60px;
            height: 60px;
            position: relative;
            animation: logoFloat 3s ease-in-out infinite;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(107, 66, 38, 0.25);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @keyframes logoFloat {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-3px) rotate(1deg); }
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
            box-shadow: 0 10px 25px rgba(107, 66, 38, 0.4);
        }
    

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .topbar-icon {
            background: linear-gradient(135deg, var(--green-primary) 0%, var(--green-secondary) 100%);
            width: 42px;
            height: 42px;
            border-radius: 12px;
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: 0 4px 15px rgba(163, 177, 138, 0.3);
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
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.6s;
        }

        .topbar-icon:hover {
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 8px 30px rgba(163, 177, 138, 0.5);
        }

        .topbar-icon:hover::before {
            left: 100%;
        }

        .logout-button {
            background: linear-gradient(135deg, var(--primary-brown) 0%, #5b4a3e 100%);
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 12px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: var(--transition);
            box-shadow: 0 4px 15px rgba(107, 66, 38, 0.3);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .logout-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(107, 66, 38, 0.5);
        }

        /* Contenedor principal mejorado */
        .detail-container {
            background: rgba(255, 253, 252, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 25px;
            padding: 45px;
            width: 95%;
            max-width: 1400px;
            margin: 30px auto;
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            position: relative;
            overflow: hidden;
        }

        .detail-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(163, 177, 138, 0.03) 0%, rgba(88, 129, 87, 0.03) 100%);
            border-radius: 25px;
            z-index: -1;
        }

        /* Botón de regreso mejorado */
        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: var(--green-secondary);
            text-decoration: none;
            margin-bottom: 30px;
            font-weight: 600;
            font-size: 15px;
            padding: 12px 20px;
            background: rgba(88, 129, 87, 0.1);
            border-radius: 15px;
            transition: var(--transition);
            border: 1px solid rgba(88, 129, 87, 0.2);
        }

        .back-button:hover {
            color: var(--green-dark);
            background: rgba(88, 129, 87, 0.15);
            transform: translateX(-8px);
            box-shadow: 0 5px 20px rgba(88, 129, 87, 0.2);
        }

        /* Layout principal mejorado */
        .book-detail {
            display: grid;
            grid-template-columns: 1fr 1.2fr;
            gap: 50px;
            margin-bottom: 20px;
            align-items: start;
        }

        /* Sección de medios mejorada */
        .book-media {
            display: flex;
            flex-direction: column;
            gap: 25px;
            position: sticky;
            top: 120px;
        }

        .main-image-container {
            position: relative;
            border-radius: 20px;
            overflow: hidden;
            background: linear-gradient(135deg, #f5f2ee 0%, #ebe6e0 100%);
            aspect-ratio: 3/4;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            transition: var(--transition);
        }

        .main-image-container:hover {
            transform: scale(1.02);
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.15);
        }

        .main-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .main-image-container:hover .main-image {
            transform: scale(1.05);
        }

        .image-gallery {
            display: flex;
            gap: 12px;
            overflow-x: auto;
            padding: 15px 5px;
            scroll-behavior: smooth;
        }

        .image-gallery::-webkit-scrollbar {
            height: 6px;
        }

        .image-gallery::-webkit-scrollbar-track {
            background: rgba(163, 177, 138, 0.1);
            border-radius: 3px;
        }

        .image-gallery::-webkit-scrollbar-thumb {
            background: var(--green-primary);
            border-radius: 3px;
        }

        .gallery-thumb {
            width: 85px;
            height: 85px;
            border-radius: 12px;
            cursor: pointer;
            transition: var(--transition);
            object-fit: cover;
            border: 3px solid transparent;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            flex-shrink: 0;
        }

        .gallery-thumb:hover {
            border-color: var(--green-primary);
            transform: scale(1.08);
            box-shadow: 0 8px 25px rgba(88, 129, 87, 0.3);
        }

        .gallery-thumb.active {
            border-color: var(--green-secondary);
            transform: scale(1.05);
            box-shadow: 0 8px 25px rgba(88, 129, 87, 0.4);
        }

        .video-container {
            margin-top: 20px;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            max-width: 500px;
        }

        .video-container video {
            width: 100%;
            max-height: 550px;
            border-radius: 15px;
            object-fit: cover;
        }

        /* Información del libro mejorada */
        .book-info {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        /* Contenedor combinado para specs y descripción */
        .book-info-combined {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }

        .book-specs-inline {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .description-inline {
            background: rgba(248, 246, 243, 0.7);
            padding: 25px;
            border-radius: 15px;
            border-left: 4px solid var(--green-primary);
            margin-top: 20px;
        }

        .description-inline h3 {
            color: var(--primary-brown);
            font-size: 1.4em;
            margin-bottom: 15px;
            font-weight: 700;
        }

        .description-inline .description-text {
            color: var(--text-primary);
            line-height: 1.7;
            font-size: 1em;
            background: none;
            padding: 0;
            border: none;
        }

        .book-title {
            font-size: 2.8em;
            font-weight: 800;
            color: var(--primary-brown);
            line-height: 1.1;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(107, 66, 38, 0.1);
        }

        .book-author {
            font-size: 1.4em;
            color: var(--secondary-brown);
            font-weight: 600;
            margin-bottom: 15px;
            opacity: 0.9;
        }

        .book-price {
            font-size: 2.4em;
            font-weight: 800;
            color: var(--green-secondary);
            margin-bottom: 25px;
            text-shadow: 0 2px 4px rgba(88, 129, 87, 0.1);
            background: linear-gradient(135deg, var(--green-primary) 0%, var(--green-secondary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

.seller-info {
    background: linear-gradient(135deg, rgba(163, 177, 138, 0.08) 0%, rgba(163, 177, 138, 0.12) 100%);
    padding: 30px;
    border-radius: 20px;
    margin-bottom: 25px;
    border: 2px solid rgba(163, 177, 138, 0.15);
    transition: var(--transition);
    position: relative;
    overflow: hidden;
}

.seller-info::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(135deg, var(--green-primary) 0%, var(--green-secondary) 100%);
    opacity: 0.8;
}

.seller-info:hover {
    background: linear-gradient(135deg, rgba(163, 177, 138, 0.12) 0%, rgba(163, 177, 138, 0.16) 100%);
    transform: translateY(-3px);
    box-shadow: 0 12px 35px rgba(163, 177, 138, 0.2);
    border-color: rgba(163, 177, 138, 0.25);
}

.seller-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 20px;
}

.seller-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--green-primary) 0%, var(--green-secondary) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 24px;
    box-shadow: 0 4px 15px rgba(88, 129, 87, 0.3);
    transition: var(--transition);
}

.seller-avatar:hover {
    transform: scale(1.05);
    box-shadow: 0 6px 20px rgba(88, 129, 87, 0.4);
}
.seller-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--green-primary) 0%, var(--green-secondary) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white !important;
    font-weight: 700;
    font-size: 24px;
    box-shadow: 0 4px 15px rgba(88, 129, 87, 0.3);
    transition: var(--transition);
    text-transform: uppercase;
    line-height: 1;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}
.seller-main-info h3 {
    color: var(--primary-brown);
    margin-bottom: 5px;
    font-size: 1.1em;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 8px;
}

.seller-username {
    font-weight: 600;
    color: var(--text-primary);
    font-size: 1.2em;
    margin-bottom: 3px;
}

.seller-fullname {
    color: var(--text-secondary);
    font-size: 0.95em;
    font-style: italic;
}

.seller-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid rgba(163, 177, 138, 0.2);
}

.seller-detail-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 15px;
    background: rgba(255, 255, 255, 0.6);
    border-radius: 12px;
    transition: var(--transition);
    border: 1px solid rgba(163, 177, 138, 0.1);
}

.seller-detail-item:hover {
    background: rgba(255, 255, 255, 0.8);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.seller-detail-icon {
    width: 20px;
    height: 20px;
    color: var(--green-secondary);
    flex-shrink: 0;
}

.seller-detail-info {
    flex: 1;
}

.seller-detail-label {
    font-size: 0.8em;
    color: var(--text-secondary);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 2px;
}

.seller-detail-value {
    color: var(--text-primary);
    font-weight: 600;
    font-size: 0.9em;
}

.seller-rating {
    display: flex;
    align-items: center;
    gap: 5px;
    margin-top: 15px;
    padding: 12px 15px;
    background: rgba(255, 255, 255, 0.7);
    border-radius: 12px;
    border: 1px solid rgba(163, 177, 138, 0.1);
}

.stars {
    display: flex;
    gap: 2px;
}

.star {
    color: #ffd700;
    font-size: 16px;
}

.star.empty {
    color: rgba(255, 215, 0, 0.3);
}

.rating-text {
    color: var(--text-secondary);
    font-size: 0.85em;
    margin-left: 5px;
}

/* Responsive para la sección del vendedor */
@media (max-width: 768px) {
    .seller-details {
        grid-template-columns: 1fr;
        gap: 10px;
    }
    
    .seller-header {
        flex-direction: column;
        text-align: center;
        gap: 10px;
    }
    
    .seller-avatar {
        width: 50px;
        height: 50px;
        font-size: 20px;
    }
}

        .contact-button {
            background: linear-gradient(135deg, var(--green-secondary) 0%, var(--green-dark) 100%);
            color: white;
            border: none;
            padding: 18px 35px;
            border-radius: 18px;
            font-size: 1.15em;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            width: 100%;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            box-shadow: 0 8px 30px rgba(88, 129, 87, 0.3);
            position: relative;
            overflow: hidden;
        }

        .contact-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.6s;
        }

        .contact-button:hover {
            background: linear-gradient(135deg, var(--green-dark) 0%, #2d4732 100%);
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(88, 129, 87, 0.4);
        }

        .contact-button:hover::before {
            left: 100%;
        }

        /* Especificaciones mejoradas - ocultar las originales */
        .book-specs {
            display: none;
        }

        /* Sección de descripción mejorada - ocultar la original */
        .description-section {
            display: none;
        }

        .spec-card-inline {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.8) 0%, rgba(255, 255, 255, 0.6) 100%);
            padding: 18px;
            border-radius: 12px;
            border: 1px solid rgba(163, 177, 138, 0.15);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .spec-card-inline::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(135deg, var(--green-primary) 0%, var(--green-secondary) 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .spec-card-inline:hover {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(255, 255, 255, 0.8) 100%);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .spec-card-inline:hover::before {
            opacity: 1;
        }

        .spec-card-inline h4 {
            color: var(--primary-brown);
            margin-bottom: 8px;
            font-size: 0.95em;
            font-weight: 700;
        }

        .spec-card-inline p {
            color: var(--text-primary);
            margin: 0;
            font-weight: 500;
            font-size: 0.9em;
        }

        .error-message {
            background: linear-gradient(135deg, #ffebee 0%, #fce4ec 100%);
            border: 1px solid #ef9a9a;
            color: #c62828;
            padding: 30px;
            border-radius: 20px;
            text-align: center;
            margin: 40px auto;
            max-width: 600px;
            box-shadow: 0 10px 30px rgba(198, 40, 40, 0.1);
        }

        .placeholder-image {
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f5f2ee 0%, #ebe6e0 100%);
            color: rgba(0,0,0,0.3);
        }

        /* Animaciones mejoradas */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .detail-container {
            animation: fadeInUp 0.8s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }

        /* Responsive mejorado */
        @media (max-width: 1024px) {
            .detail-container {
                padding: 35px;
                margin: 25px 20px;
            }

            .book-detail {
                grid-template-columns: 1fr;
                gap: 40px;
            }

            .book-media {
                position: static;
            }

            .book-title {
                font-size: 2.2em;
            }

            .book-price {
                font-size: 2em;
            }
        }

        @media (max-width: 768px) {
            .topbar {
                padding: 10px 20px;
            }

            .logo-icon {
                width: 50px;
                height: 50px;
            }

            .topbar-icon {
                width: 38px;
                height: 38px;
            }

            .detail-container {
                padding: 25px;
                margin: 20px 15px;
            }

            .book-title {
                font-size: 1.8em;
            }

            .book-specs {
                grid-template-columns: 1fr;
            }

            .image-gallery {
                justify-content: flex-start;
            }

            .contact-button {
                padding: 15px 25px;
                font-size: 1em;
            }
        }

        @media (max-width: 480px) {
            .topbar-right {
                gap: 8px;
            }

            .logout-button {
                padding: 8px 12px;
                font-size: 12px;
            }

            .book-title {
                font-size: 1.6em;
            }

            .book-price {
                font-size: 1.8em;
            }

            .spec-card {
                padding: 20px;
            }
        }
    </style>
</head>
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
                <svg width="18" height="18" fill="white" viewBox="0 0 24 24">
                    <path d="M12 2c.55 0 1 .45 1 1v1h4a2 2 0 0 1 2 2v2h1a1 1 0 1 1 0 2h-1v6a3 3 0 0 1-3 3h-1v1a1 1 0 1 1-2 0v-1H9v1a1 1 0 1 1-2 0v-1H6a3 3 0 0 1-3-3v-6H2a1 1 0 1 1 0-2h1V6a2 2 0 0 1 2-2h4V3c0-.55.45-1 1-1zm-5 9a1 1 0 1 0 0 2 1 1 0 0 0 0-2zm10 0a1 1 0 1 0 0 2 1 1 0 0 0 0-2z"/>
                </svg>
            </div>

            <a href="../chat/chat.php" class="topbar-icon" title="Chat">
                <svg width="18" height="18" fill="white" viewBox="0 0 24 24">
                    <path d="M20 2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h4l4 4 4-4h4c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/>
                </svg>
            </a>

            <div class="topbar-icon" title="Perfil">
                <svg width="18" height="18" fill="white" viewBox="0 0 24 24">
                    <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                </svg>
            </div>

            <form action="../auth/logout.php" method="post" class="logout-form">
                <button type="submit" class="logout-button">
                    <svg width="16" height="16" fill="white" viewBox="0 0 24 24">
                        <path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.59L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/>
                    </svg>
                    Cerrar sesión
                </button>
            </form>
        </div>
    </div>

    <?php include '../../includes/chat-component.php'; ?>

    <?php if (!empty($error)): ?>
        <div class="error-message">
            <h3>Error</h3>
            <p><?php echo htmlspecialchars($error); ?></p>
            <a href="home.php" style="color: #588157; text-decoration: none; font-weight: 600;">← Volver</a>
        </div>
    <?php else: ?>
        <main class="detail-container">
<a href="../home.php" class="back-button">
    <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
        <path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/>
    </svg>
    Volver al inicio
</a>

            <div class="book-detail">
                <div class="book-media">
                    <div class="main-image-container">
                        <?php if (!empty($publicacion['linkImagen1'])): ?>
                            <img src="../../uploads/<?php echo htmlspecialchars($publicacion['linkImagen1']); ?>" 
                                 alt="<?php echo htmlspecialchars($publicacion['titulo']); ?>" 
                                 class="main-image" id="mainImage">
                        <?php else: ?>
                            <div class="placeholder-image">
                                <svg width="120" height="120" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M18 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 4h5v8l-2.5-1.5L6 12V4z"/>
                                </svg>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php 
                    $imagenes = array_filter([
                        $publicacion['linkImagen1'], 
                        $publicacion['linkImagen2'], 
                        $publicacion['linkImagen3']
                    ]);
                    if (count($imagenes) > 1): 
                    ?>
                        <div class="image-gallery">
                            <?php foreach ($imagenes as $index => $imagen): ?>
                                <img src="../../uploads/<?php echo htmlspecialchars($imagen); ?>" 
                                     alt="Imagen <?php echo $index + 1; ?>" 
                                     class="gallery-thumb <?php echo $index === 0 ? 'active' : ''; ?>"
                                     onclick="cambiarImagenPrincipal('../../uploads/<?php echo htmlspecialchars($imagen); ?>', this)">
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

                <div class="book-info">
                    <div class="book-info-combined">
                        <div class="basic-info">
                            <h1 class="book-title"><?php echo htmlspecialchars($publicacion['titulo']); ?></h1>
                            <p class="book-author">por <?php echo htmlspecialchars($publicacion['autor']); ?></p>
                            <div class="book-price">$<?php echo number_format($publicacion['precio'], 2); ?></div>

                            <div class="seller-info">
    <div class="seller-header">
        <div class="seller-avatar">
    <?php 
    // Intentar obtener la inicial de diferentes campos disponibles
    $inicial = '';
    
    // Primero intentar con el nombre completo
    if (!empty($publicacion['nombre'])) {
        $inicial = strtoupper(substr(trim($publicacion['nombre']), 0, 1));
    }
    // Si no hay nombre, usar userName
    elseif (!empty($publicacion['userName'])) {
        $inicial = strtoupper(substr(trim($publicacion['userName']), 0, 1));
    }
    // Si no hay userName, usar nombreUsuario
    elseif (!empty($publicacion['nombreUsuario'])) {
        $inicial = strtoupper(substr(trim($publicacion['nombreUsuario']), 0, 1));
    }
    // Como último recurso, usar 'U' de Usuario
    else {
        $inicial = 'U';
    }
    
    echo $inicial;
    ?>
</div>
        <div class="seller-main-info">
            <h3>
                <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                </svg>
                Vendido por
            </h3>
            <?php if (!empty($publicacion['nombre'])): ?>
                <div class="seller-fullname"><?php echo htmlspecialchars($publicacion['nombre']); ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="seller-details">
        <div class="seller-detail-item">
            <div class="seller-detail-icon">
                <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                </svg>
            </div>
            <div class="seller-detail-info">
                <div class="seller-detail-label">Usuario Verificado</div>
                <div class="seller-detail-value">Cuenta Activa</div>
            </div>
        </div>

        <div class="seller-detail-item">
            <div class="seller-detail-icon">
                <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                </svg>
            </div>
            <div class="seller-detail-info">
                <div class="seller-detail-label">Publicación</div>
                <div class="seller-detail-value">
                    <?php echo date('d/m/Y', strtotime($publicacion['fechaCreacion'])); ?>
                </div>
            </div>
        </div>

        <div class="seller-detail-item">
            <div class="seller-detail-icon">
                <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                </svg>
            </div>
            <div class="seller-detail-info">
                <div class="seller-detail-label">Contacto</div>
                <div class="seller-detail-value">Disponible</div>
            </div>
        </div>
    </div>

    <!-- Calificación del vendedor (simulada por ahora) -->
    <div class="seller-rating">
        <div class="stars">
            <span class="star">★</span>
            <span class="star">★</span>
            <span class="star">★</span>
            <span class="star">★</span>
            <span class="star empty">★</span>
        </div>
        <span class="rating-text">4.2/5 - Vendedor confiable</span>
    </div>
</div>

                            <button class="contact-button" onclick="abrirChat(<?php echo $publicacion['idUsuario']; ?>, '<?php echo htmlspecialchars($publicacion['userName']); ?>')">
                                <svg width="22" height="22" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M20 2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h4l4 4 4-4h4c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/>
                                </svg>
                                Contactar Vendedor
                            </button>
                        </div>

                        <!-- Especificaciones inline -->
                        <div class="book-specs-inline">
                            <?php if (!empty($publicacion['editorial'])): ?>
                                <div class="spec-card-inline">
                                    <h4>Editorial</h4>
                                    <p><?php echo htmlspecialchars($publicacion['editorial']); ?></p>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($publicacion['edicion'])): ?>
                                <div class="spec-card-inline">
                                    <h4>Edición</h4>
                                    <p><?php echo htmlspecialchars($publicacion['edicion']); ?></p>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($publicacion['categoria'])): ?>
                                <div class="spec-card-inline">
                                    <h4>Categoría</h4>
                                    <p><?php echo htmlspecialchars($publicacion['categoria']); ?></p>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($publicacion['tipoPublico'])): ?>
                                <div class="spec-card-inline">
                                    <h4>Tipo de Público</h4>
                                    <p><?php echo htmlspecialchars($publicacion['tipoPublico']); ?></p>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($publicacion['paginas'])): ?>
                                <div class="spec-card-inline">
                                    <h4>Número de Páginas</h4>
                                    <p><?php echo htmlspecialchars($publicacion['paginas']); ?> páginas</p>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($publicacion['base']) && !empty($publicacion['altura'])): ?>
                                <div class="spec-card-inline">
                                    <h4>Dimensiones</h4>
                                    <p><?php echo htmlspecialchars($publicacion['base']); ?> × <?php echo htmlspecialchars($publicacion['altura']); ?> cm</p>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($publicacion['fechaPublicacion'])): ?>
                                <div class="spec-card-inline">
                                    <h4>Fecha de Publicación</h4>
                                    <p><?php echo date('d/m/Y', strtotime($publicacion['fechaPublicacion'])); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Descripción inline -->
                        <?php if (!empty($publicacion['descripcion'])): ?>
                            <div class="description-inline">
                                <h3>Descripción</h3>
                                <div class="description-text">
                                    <?php echo nl2br(htmlspecialchars($publicacion['descripcion'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    <?php endif; ?>

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
        // Función para cambiar la imagen principal con efectos mejorados
        function cambiarImagenPrincipal(imagenSrc, thumbnail) {
            const mainImage = document.getElementById('mainImage');
            if (mainImage) {
                // Efecto de transición suave
                mainImage.style.opacity = '0.7';
                mainImage.style.transform = 'scale(0.95)';
                
                setTimeout(() => {
                    mainImage.src = imagenSrc;
                    mainImage.style.opacity = '1';
                    mainImage.style.transform = 'scale(1)';
                }, 200);
            }

            // Remover clase active de todas las miniaturas
            document.querySelectorAll('.gallery-thumb').forEach(thumb => {
                thumb.classList.remove('active');
            });

            // Agregar clase active a la miniatura clickeada
            thumbnail.classList.add('active');
        }

        // Función mejorada para abrir chat
        function abrirChat(userId, userName) {
            // Mostrar indicador de carga en el botón
            const contactButton = document.querySelector('.contact-button');
            const originalContent = contactButton.innerHTML;
            
            contactButton.innerHTML = `
                <svg width="22" height="22" fill="currentColor" viewBox="0 0 24 24" style="animation: spin 1s linear infinite;">
                    <path d="M12 2v4m0 12v4m10-10h-4M6 12H2m15.364-6.364l-2.828 2.828M9.464 14.536l-2.828 2.828m12.728 0l-2.828-2.828M9.464 9.464L6.636 6.636"/>
                </svg>
                Conectando...
            `;
            
            contactButton.disabled = true;

            fetch('../../api/create_conversation.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'other_user_id=' + userId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = '../chat/chat.php?conversacion=' + data.conversationId + '&other_user_id=' + userId + '&other_user_name=' + encodeURIComponent(userName);
                } else {
                    alert('Error al abrir el chat: ' + data.message);
                    // Restaurar botón
                    contactButton.innerHTML = originalContent;
                    contactButton.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al conectar con el servidor');
                // Restaurar botón
                contactButton.innerHTML = originalContent;
                contactButton.disabled = false;
            });
        }

                    // Efectos adicionales para mejorar la experiencia
        document.addEventListener('DOMContentLoaded', function() {
            // Efecto parallax sutil para las tarjetas de especificaciones inline
            const specCards = document.querySelectorAll('.spec-card-inline');
            
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.animation = 'fadeInUp 0.6s ease forwards';
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, observerOptions);

            specCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';
                card.style.animationDelay = `${index * 0.1}s`;
                observer.observe(card);
            });

            // Efecto de hover mejorado para la imagen principal
            const mainImageContainer = document.querySelector('.main-image-container');
            if (mainImageContainer) {
                mainImageContainer.addEventListener('mouseenter', function() {
                    this.style.boxShadow = '0 20px 60px rgba(0, 0, 0, 0.2)';
                });
                
                mainImageContainer.addEventListener('mouseleave', function() {
                    this.style.boxShadow = '0 10px 40px rgba(0, 0, 0, 0.1)';
                });
            }

            // Smooth scroll para la galería de imágenes
            const imageGallery = document.querySelector('.image-gallery');
            if (imageGallery) {
                let isDown = false;
                let startX;
                let scrollLeft;

                imageGallery.addEventListener('mousedown', (e) => {
                    isDown = true;
                    startX = e.pageX - imageGallery.offsetLeft;
                    scrollLeft = imageGallery.scrollLeft;
                });

                imageGallery.addEventListener('mouseleave', () => {
                    isDown = false;
                });

                imageGallery.addEventListener('mouseup', () => {
                    isDown = false;
                });

                imageGallery.addEventListener('mousemove', (e) => {
                    if (!isDown) return;
                    e.preventDefault();
                    const x = e.pageX - imageGallery.offsetLeft;
                    const walk = (x - startX) * 2;
                    imageGallery.scrollLeft = scrollLeft - walk;
                });
            }
        });

        // Añadir estilos de animación para el spinner
        const style = document.createElement('style');
        style.textContent = `
            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
            
            .contact-button:disabled {
                opacity: 0.7;
                cursor: not-allowed;
            }
            
            .spec-card-inline {
                transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>