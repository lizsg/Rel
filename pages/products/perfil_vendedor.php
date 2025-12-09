<?php
    session_start();

    // Redireccionar si el usuario no ha iniciado sesión
    if (!isset($_SESSION['usuario']) || empty($_SESSION['usuario']) || !isset($_SESSION['user_id'])) {
        header("Location: ../auth/login.php");
        exit();
    }

    require_once __DIR__ . '/../../config/database.php';

    $currentUserId = $_SESSION['user_id']; 
    $vendedorId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $vendedor = null;
    $publicaciones = [];
    $calificaciones = [];
    $estadisticas = null;
    $error = '';

    if ($vendedorId <= 0) {
        header("Location: ../home.php");
        exit();
    }

    // Verificar que no sea su propio perfil
    if ($vendedorId == $currentUserId) {
        header("Location: publicaciones.php");
        exit();
    }

    try {
        $conn = new mysqli(SERVER_NAME, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            throw new Exception("Conexión fallida: " . $conn->connect_error);
        }

        // Obtener información del vendedor
        $vendedorQuery = "
            SELECT 
                u.idUsuario,
                u.userName,
                u.nombre,
                u.telefono,
                COALESCE(AVG(c.puntuacion), 0) as puntuacionPromedio,
                COUNT(c.idCalificacion) as totalCalificaciones,
                COUNT(DISTINCT p.idPublicacion) as totalPublicaciones
            FROM Usuarios u
            LEFT JOIN Calificaciones c ON u.idUsuario = c.idCalificado
            LEFT JOIN Publicaciones p ON u.idUsuario = p.idUsuario
            WHERE u.idUsuario = ?
            GROUP BY u.idUsuario
        ";

        $stmt = $conn->prepare($vendedorQuery);
        $stmt->bind_param("i", $vendedorId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $vendedor = $result->fetch_assoc();
        } else {
            $error = "Vendedor no encontrado.";
        }

        $stmt->close();

        if ($vendedor) {
            // Obtener publicaciones del vendedor
            $publicacionesQuery = "
                SELECT 
                    p.idPublicacion,
                    p.idLibro,
                    p.precio,
                    p.fechaCreacion,
                    l.titulo,
                    l.autor,
                    l.descripcion,
                    l.linkImagen1,
                    l.editorial,
                    l.categoria
                FROM Publicaciones p
                JOIN Libros l ON p.idLibro = l.idLibro
                WHERE p.idUsuario = ?
                ORDER BY p.fechaCreacion DESC
                LIMIT 20
            ";

            $stmt = $conn->prepare($publicacionesQuery);
            $stmt->bind_param("i", $vendedorId);
            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $publicaciones[] = $row;
            }

            $stmt->close();

            // Obtener calificaciones recientes
            $calificacionesQuery = "
                SELECT 
                    c.puntuacion,
                    c.comentario,
                    c.fechaCalificacion,
                    u.userName as nombreCalificador,
                    u.nombre as nombreCompletoCalificador
                FROM Calificaciones c
                JOIN Usuarios u ON c.idCalificador = u.idUsuario
                WHERE c.idCalificado = ?
                ORDER BY c.fechaCalificacion DESC
                LIMIT 10
            ";

            $stmt = $conn->prepare($calificacionesQuery);
            $stmt->bind_param("i", $vendedorId);
            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $calificaciones[] = $row;
            }

            $stmt->close();

            // Obtener estadísticas detalladas
            $estadisticasQuery = "
                SELECT 
                    COUNT(CASE WHEN c.puntuacion = 5 THEN 1 END) as calificaciones_5,
                    COUNT(CASE WHEN c.puntuacion = 4 THEN 1 END) as calificaciones_4,
                    COUNT(CASE WHEN c.puntuacion = 3 THEN 1 END) as calificaciones_3,
                    COUNT(CASE WHEN c.puntuacion = 2 THEN 1 END) as calificaciones_2,
                    COUNT(CASE WHEN c.puntuacion = 1 THEN 1 END) as calificaciones_1
                FROM Calificaciones c
                WHERE c.idCalificado = ?
            ";

            $stmt = $conn->prepare($estadisticasQuery);
            $stmt->bind_param("i", $vendedorId);
            $stmt->execute();
            $result = $stmt->get_result();
            $estadisticas = $result->fetch_assoc();
            $stmt->close();
        }

        $conn->close();

    } catch (Exception $e) {
        $error = "Error al cargar el perfil: " . $e->getMessage();
        error_log("Error en perfil_vendedor.php: " . $e->getMessage());
    }

    if (!$vendedor && empty($error)) {
        $error = "Vendedor no encontrado.";
    }
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php echo $vendedor ? 'Perfil de ' . htmlspecialchars($vendedor['userName']) : 'Perfil no encontrado'; ?> | RELEE</title>
    
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
            padding-top: 85px;
            padding-bottom: 80px;
            min-height: 100vh;
            line-height: 1.6;
        }

        /* Topbar */
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

        /* Contenedor principal */
        .profile-container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }

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

        /* Header del perfil */
        .profile-header {
            background: rgba(255, 253, 252, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 25px;
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .profile-info {
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 30px;
            align-items: start;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--green-primary) 0%, var(--green-secondary) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 48px;
            box-shadow: 0 8px 25px rgba(88, 129, 87, 0.3);
            text-transform: uppercase;
            flex-shrink: 0;
        }

        .profile-details h1 {
            font-size: 2.5em;
            font-weight: 800;
            color: var(--primary-brown);
            margin-bottom: 10px;
        }

        .profile-username {
            font-size: 1.2em;
            color: var(--text-secondary);
            margin-bottom: 20px;
            font-style: italic;
        }

        .profile-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .stat-item {
            background: rgba(248, 246, 243, 0.8);
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            border: 1px solid rgba(163, 177, 138, 0.1);
        }

        .stat-number {
            font-size: 2em;
            font-weight: 800;
            color: var(--green-secondary);
            display: block;
        }

        .stat-label {
            font-size: 0.9em;
            color: var(--text-secondary);
            margin-top: 5px;
        }

        .profile-actions {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .contact-button {
            background: linear-gradient(135deg, var(--green-secondary) 0%, var(--green-dark) 100%);
            color: white;
            border: none;
            padding: 15px 25px;
            border-radius: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            white-space: nowrap;
        }

        .contact-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(88, 129, 87, 0.4);
        }

        /* Rating display */
        .rating-display {
            background: rgba(255, 248, 220, 0.8);
            padding: 20px;
            border-radius: 15px;
            border-left: 4px solid #ffd700;
        }

        .rating-stars {
            display: flex;
            gap: 3px;
            margin-bottom: 8px;
        }

        .star {
            color: #ffd700;
            font-size: 20px;
        }

        .star.empty {
            color: rgba(255, 215, 0, 0.3);
        }

        .rating-text {
            font-size: 0.9em;
            color: var(--text-secondary);
        }

        /* Secciones del contenido */
        .content-sections {
            display: grid;
            grid-template-columns: 1fr;
            gap: 30px;
        }

        .section {
            background: rgba(255, 253, 252, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 25px;
            padding: 35px;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .section-title {
            font-size: 1.8em;
            font-weight: 700;
            color: var(--primary-brown);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        /* Publicaciones grid */
        .publications-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
        }

        .publication-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
            transition: var(--transition);
            border: 1px solid rgba(163, 177, 138, 0.1);
        }

        .publication-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }

        .card-image {
            width: 100%;
            height: 200px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f5f2ee 0%, #ebe6e0 100%);
        }

        .card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .publication-card:hover .card-image img {
            transform: scale(1.05);
        }

        .card-content {
            padding: 20px;
        }

        .card-title {
            font-size: 1.2em;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 8px;
            line-height: 1.2;
        }

        .card-author {
            font-size: 0.9em;
            color: var(--text-secondary);
            margin-bottom: 12px;
        }

        .card-price {
            font-size: 1.3em;
            font-weight: 800;
            color: var(--green-secondary);
            margin-bottom: 15px;
        }

        .card-actions {
            display: flex;
            gap: 10px;
        }

        .btn-details {
            flex: 1;
            padding: 10px 15px;
            background: linear-gradient(135deg, var(--green-secondary) 0%, var(--green-dark) 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            text-align: center;
            transition: var(--transition);
            font-size: 0.9em;
        }

        .btn-details:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(88, 129, 87, 0.3);
        }

        /* Calificaciones */
        .reviews-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .review-item {
            background: rgba(248, 246, 243, 0.8);
            padding: 25px;
            border-radius: 15px;
            border: 1px solid rgba(163, 177, 138, 0.1);
        }

        .review-header {
            display: flex;
            justify-content: between;
            align-items: start;
            margin-bottom: 15px;
            gap: 15px;
        }

        .review-author {
            flex: 1;
        }

        .reviewer-name {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .review-date {
            font-size: 0.85em;
            color: var(--text-secondary);
        }

        .review-rating {
            display: flex;
            gap: 2px;
        }

        .review-comment {
            color: var(--text-primary);
            line-height: 1.6;
            font-style: italic;
            margin-top: 10px;
        }

        /* Rating breakdown */
        .rating-breakdown {
            background: rgba(255, 248, 220, 0.5);
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 25px;
        }

        .breakdown-item {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 10px;
        }

        .star-label {
            min-width: 60px;
            font-weight: 600;
        }

        .progress-bar {
            flex: 1;
            height: 8px;
            background: rgba(255, 215, 0, 0.2);
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: #ffd700;
            transition: width 0.5s ease;
        }

        .count-label {
            min-width: 30px;
            text-align: right;
            font-size: 0.9em;
            color: var(--text-secondary);
        }

        /* Estados vacíos */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }

        .empty-state svg {
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .empty-state h3 {
            margin-bottom: 10px;
            color: var(--text-primary);
        }

        /* Error */
        .error-message {
            background: linear-gradient(135deg, #ffebee 0%, #fce4ec 100%);
            border: 1px solid #ef9a9a;
            color: #c62828;
            padding: 30px;
            border-radius: 20px;
            text-align: center;
            margin: 40px auto;
            max-width: 600px;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .profile-info {
                grid-template-columns: 1fr;
                gap: 25px;
                text-align: center;
            }

            .profile-details {
                order: -1;
            }

            .profile-actions {
                flex-direction: row;
                justify-content: center;
            }
        }

        @media (max-width: 768px) {
            .topbar {
                padding: 10px 20px;
            }

            .profile-container {
                padding: 0 15px;
            }

            .profile-header {
                padding: 25px;
            }

            .profile-avatar {
                width: 80px;
                height: 80px;
                font-size: 32px;
            }

            .profile-details h1 {
                font-size: 2em;
            }

            .section {
                padding: 25px;
            }

            .publications-grid {
                grid-template-columns: 1fr;
            }

            .profile-actions {
                flex-direction: column;
            }
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

        .profile-header,
        .section {
            animation: fadeInUp 0.6s ease forwards;
        }

        .publication-card {
            animation: fadeInUp 0.6s ease forwards;
        }
    </style>
</head>
<body>
    <!-- Topbar -->
    <div class="topbar">
        <div class="logo-container">
            <a href="../home.php" class="logo-link" title="Ir al inicio">
                <div class="logo-icon">
                    <img src="../../assets/images/REELEE.jpeg" alt="RELEE Logo" class="logo-image" />
                </div>
            </a>
        </div>

        <div class="topbar-right">
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

            <form action="../auth/logout.php" method="post">
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
            <a href="../home.php" style="color: #588157; text-decoration: none; font-weight: 600;">← Volver al inicio</a>
        </div>
    <?php else: ?>
        <main class="profile-container">
            <a href="javascript:history.back()" class="back-button">
                <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/>
                </svg>
                Volver
            </a>

            <!-- Header del perfil -->
            <div class="profile-header">
                <div class="profile-info">
                    <div class="profile-avatar">
                        <?php 
                        $inicial = '';
                        if (!empty($vendedor['nombre'])) {
                            $inicial = strtoupper(substr(trim($vendedor['nombre']), 0, 1));
                        } elseif (!empty($vendedor['userName'])) {
                            $inicial = strtoupper(substr(trim($vendedor['userName']), 0, 1));
                        } else {
                            $inicial = 'U';
                        }
                        echo $inicial;
                        ?>
                    </div>

                    <div class="profile-details">
                        <h1><?php echo !empty($vendedor['nombre']) ? htmlspecialchars($vendedor['nombre']) : htmlspecialchars($vendedor['userName']); ?></h1>
                        <?php if (!empty($vendedor['nombre'])): ?>
                            <div class="profile-username">@<?php echo htmlspecialchars($vendedor['userName']); ?></div>
                        <?php endif; ?>

                        <div class="rating-display">
                            <div class="rating-stars">
                                <?php
                                $rating = floatval($vendedor['puntuacionPromedio']);
                                for ($i = 1; $i <= 5; $i++) {
                                    if ($i <= $rating) {
                                        echo '<span class="star">★</span>';
                                    } else {
                                        echo '<span class="star empty">★</span>';
                                    }
                                }
                                ?>
                            </div>
                            <div class="rating-text">
                                <strong><?php echo number_format($rating, 1); ?>/5</strong> 
                                (<?php echo intval($vendedor['totalCalificaciones']); ?> calificación<?php echo intval($vendedor['totalCalificaciones']) !== 1 ? 'es' : ''; ?>)
                            </div>
                        </div>

                        <div class="profile-stats">
                            <div class="stat-item">
                                <span class="stat-number"><?php echo intval($vendedor['totalPublicaciones']); ?></span>
                                <div class="stat-label">Publicaciones</div>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number"><?php echo intval($vendedor['totalCalificaciones']); ?></span>
                                <div class="stat-label">Calificaciones</div>
                            </div>
                        </div>
                    </div>

                    <div class="profile-actions">
                        <button class="contact-button" onclick="abrirChat(<?php echo $vendedor['idUsuario']; ?>, '<?php echo htmlspecialchars($vendedor['userName']); ?>')">
                            <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M20 2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h4l4 4 4-4h4c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/>
                            </svg>
                            Contactar Vendedor
                        </button>
                        <?php if (!empty($vendedor['telefono'])): ?>
                            <div style="text-align: center; padding: 10px; background: rgba(163, 177, 138, 0.1); border-radius: 10px; font-size: 0.9em; color: var(--text-secondary);">
                                <strong>Teléfono:</strong> <?php echo htmlspecialchars($vendedor['telefono']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Secciones de contenido -->
            <div class="content-sections">
                <!-- Publicaciones -->
                <div class="section">
                    <h2 class="section-title">
                        <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M18 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 4h5v8l-2.5-1.5L6 12V4z"/>
                        </svg>
                        Publicaciones (<?php echo count($publicaciones); ?>)
                    </h2>

                    <?php if (!empty($publicaciones)): ?>
                        <div class="publications-grid">
                            <?php foreach ($publicaciones as $publicacion): ?>
                                <div class="publication-card">
                                    <div class="card-image">
                                        <?php if (!empty($publicacion['linkImagen1'])): ?>
                                            <img src="../../uploads/<?php echo htmlspecialchars($publicacion['linkImagen1']); ?>" alt="<?php echo htmlspecialchars($publicacion['titulo']); ?>">
                                        <?php else: ?>
                                            <svg width="60" height="60" fill="rgba(0,0,0,0.1)" viewBox="0 0 24 24">
                                                <path d="M18 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 4h5v8l-2.5-1.5L6 12V4z"/>
                                            </svg>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-content">
                                        <div class="card-title"><?php echo htmlspecialchars($publicacion['titulo']); ?></div>
                                        <div class="card-author">Por: <?php echo htmlspecialchars($publicacion['autor']); ?></div>
                                        <div class="card-price">$<?php echo number_format($publicacion['precio'], 2); ?></div>
                                        
                                        <?php if (!empty($publicacion['editorial'])): ?>
                                            <div style="font-size: 0.85em; color: var(--text-secondary); margin-bottom: 10px;">
                                                Editorial: <?php echo htmlspecialchars($publicacion['editorial']); ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($publicacion['categoria'])): ?>
                                            <div style="font-size: 0.85em; color: var(--text-secondary); margin-bottom: 15px;">
                                                Categoría: <?php echo htmlspecialchars($publicacion['categoria']); ?>
                                            </div>
                                        <?php endif; ?>

                                        <div class="card-actions">
                                            <a href="detalle_publicacion.php?id=<?php echo $publicacion['idPublicacion']; ?>" class="btn-details">
                                                Ver Detalles
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <svg width="80" height="80" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M18 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 4h5v8l-2.5-1.5L6 12V4z"/>
                            </svg>
                            <h3>Sin publicaciones</h3>
                            <p>Este usuario aún no ha publicado ningún libro.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Calificaciones -->
                <?php if (intval($vendedor['totalCalificaciones']) > 0): ?>
                    <div class="section">
                        <h2 class="section-title">
                            <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                            </svg>
                            Calificaciones de Usuarios
                        </h2>

                        <!-- Breakdown de calificaciones -->
                        <?php if ($estadisticas): ?>
                            <div class="rating-breakdown">
                                <h4 style="margin-bottom: 20px; color: var(--primary-brown);">Distribución de Calificaciones</h4>
                                <?php 
                                $totalCalifs = intval($vendedor['totalCalificaciones']);
                                for ($i = 5; $i >= 1; $i--): 
                                    $count = intval($estadisticas["calificaciones_$i"]);
                                    $percentage = $totalCalifs > 0 ? ($count / $totalCalifs) * 100 : 0;
                                ?>
                                    <div class="breakdown-item">
                                        <div class="star-label"><?php echo $i; ?> ★</div>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?php echo $percentage; ?>%"></div>
                                        </div>
                                        <div class="count-label"><?php echo $count; ?></div>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Lista de calificaciones -->
                        <?php if (!empty($calificaciones)): ?>
                            <div class="reviews-list">
                                <?php foreach ($calificaciones as $calificacion): ?>
                                    <div class="review-item">
                                        <div class="review-header">
                                            <div class="review-author">
                                                <div class="reviewer-name">
                                                    <?php 
                                                    echo !empty($calificacion['nombreCalificador']) 
                                                        ? htmlspecialchars($calificacion['nombreCalificador']) 
                                                        : htmlspecialchars($calificacion['nombreCompletoCalificador']); 
                                                    ?>
                                                </div>
                                                <div class="review-date">
                                                    <?php echo date('d/m/Y', strtotime($calificacion['fechaCalificacion'])); ?>
                                                </div>
                                            </div>
                                            <div class="review-rating">
                                                <?php
                                                $rating = intval($calificacion['puntuacion']);
                                                for ($i = 1; $i <= 5; $i++) {
                                                    if ($i <= $rating) {
                                                        echo '<span class="star">★</span>';
                                                    } else {
                                                        echo '<span class="star empty">★</span>';
                                                    }
                                                }
                                                ?>
                                            </div>
                                        </div>
                                        <?php if (!empty($calificacion['comentario'])): ?>
                                            <div class="review-comment">
                                                "<?php echo htmlspecialchars($calificacion['comentario']); ?>"
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    <?php endif; ?>

    <!-- Bottom bar -->
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
        // Función para abrir chat
        function abrirChat(userId, userName) {
            const contactButton = document.querySelector('.contact-button');
            const originalContent = contactButton.innerHTML;
            
            contactButton.innerHTML = `
                <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24" style="animation: spin 1s linear infinite;">
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
                    contactButton.innerHTML = originalContent;
                    contactButton.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al conectar con el servidor');
                contactButton.innerHTML = originalContent;
                contactButton.disabled = false;
            });
        }

        // Animaciones de entrada
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.publication-card');
            const observer = new IntersectionObserver(function(entries) {
                entries.forEach((entry, index) => {
                    if (entry.isIntersecting) {
                        setTimeout(() => {
                            entry.target.style.animationDelay = `${index * 0.1}s`;
                            entry.target.style.opacity = '1';
                            entry.target.style.transform = 'translateY(0)';
                        }, index * 100);
                    }
                });
            }, {
                threshold: 0.1
            });

            cards.forEach(card => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';
                card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                observer.observe(card);
            });

            // Animación para las barras de progreso de calificaciones
            const progressBars = document.querySelectorAll('.progress-fill');
            progressBars.forEach((bar, index) => {
                setTimeout(() => {
                    const width = bar.style.width;
                    bar.style.width = '0%';
                    setTimeout(() => {
                        bar.style.width = width;
                    }, 100);
                }, index * 200);
            });
        });

        // Estilos adicionales para animaciones
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
            
            .progress-fill {
                transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1);
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>