<?php
    session_start();

    if (!isset($_SESSION['usuario']) || empty($_SESSION['usuario']) || !isset($_SESSION['user_id'])) {
        header("Location: ../auth/login.php");
        exit();
    }

    require_once __DIR__ . '/../../config/database.php';
    $userId = $_SESSION['user_id']; 

    $usuario = [];
    $errorMessage = '';
    $successMessage = '';
    $totalPublicaciones = 0;

    // Obtener datos del usuario
    try {
        $conn = new mysqli(SERVER_NAME, DB_USER, DB_PASS, DB_NAME);
        $conn->set_charset("utf8mb4");
        
        if ($conn->connect_error) {
            throw new Exception("Error de conexión");
        }

        $userStmt = $conn->prepare("SELECT * FROM Usuarios WHERE idUsuario = ?");
        $userStmt->bind_param("i", $userId);
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        
        if ($userResult->num_rows === 0) {
            throw new Exception("Usuario no encontrado");
        }
        
        $usuario = $userResult->fetch_assoc();

        // Contar publicaciones
        $pubStmt = $conn->prepare("SELECT COUNT(*) as total FROM Publicaciones WHERE idUsuario = ?");
        $pubStmt->bind_param("i", $userId);
        $pubStmt->execute();
        $pubResult = $pubStmt->get_result();
        $totalPublicaciones = $pubResult->fetch_assoc()['total'];

        // Contar seguidores
        $segStmt = $conn->prepare("SELECT COUNT(*) as total FROM Seguidores WHERE idUsuarioSeguido = ?");
        $segStmt->bind_param("i", $userId);
        $segStmt->execute();
        $totalSeguidores = $segStmt->get_result()->fetch_assoc()['total'];

        // Contar seguidos
        $sigStmt = $conn->prepare("SELECT COUNT(*) as total FROM Seguidores WHERE idUsuarioSeguidor = ?");
        $sigStmt->bind_param("i", $userId);
        $sigStmt->execute();
        $totalSiguiendo = $sigStmt->get_result()->fetch_assoc()['total'];

        // Contar amigos
        $amigosStmt = $conn->prepare("
            SELECT COUNT(*) as total FROM Amistades 
            WHERE (idUsuarioSolicitante = ? OR idUsuarioReceptor = ?) 
            AND estado = 'aceptada'
        ");
        $amigosStmt->bind_param("ii", $userId, $userId);
        $amigosStmt->execute();
        $totalAmigos = $amigosStmt->get_result()->fetch_assoc()['total'];

        $conn->close();

    } catch (Exception $e) {
        $errorMessage = "Error al cargar datos: " . $e->getMessage();
    }

    // Mensajes de éxito/error
    if (isset($_GET['success'])) {
        $successMessage = $_SESSION['success_message'] ?? "Perfil actualizado correctamente";
        unset($_SESSION['success_message']);
    }

    if (isset($_GET['error'])) {
        $errorMessage = $_SESSION['error_message'] ?? $_GET['error'];
        unset($_SESSION['error_message']);
    }

    function formatearFecha($fecha) {
        if (empty($fecha) || $fecha === '0000-00-00') {
            return 'No especificada';
        }
        return date('d/m/Y', strtotime($fecha));
    }
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil | RELEE</title>
    <style>
        :root {
            --primary-brown: #6b4226;
            --secondary-brown: #8b5a3c;
            --light-brown: #d6c1b2;
            --cream-bg: #f8f6f3;
            --green-primary: #a3b18a;
            --green-secondary: #588157;
            --text-primary: #2c2016;
            --text-secondary: #6f5c4d;
            --text-muted: #888;
            --card-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--cream-bg);
            color: var(--text-primary);
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            box-shadow: 0 0 30px rgba(0,0,0,0.1);
        }

        .top-header {
            padding: 15px 20px;
            background: white;
            border-bottom: 1px solid #e0e0e0;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 600;
            padding: 8px 16px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .back-button:hover {
            background: rgba(163, 177, 138, 0.1);
            color: var(--green-secondary);
        }

        .cover-photo {
            width: 100%;
            height: 400px;
            background: linear-gradient(135deg, var(--light-brown) 0%, #c4a68a 100%);
            position: relative;
            overflow: hidden;
        }

        .cover-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .cover-photo::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 100px;
            background: linear-gradient(to top, rgba(0,0,0,0.3), transparent);
        }

        .change-cover-btn {
            position: absolute;
            bottom: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            z-index: 10;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        .change-cover-btn:hover {
            background: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.3);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .profile-header {
            background: white;
            padding: 0 40px 20px;
            border-bottom: 1px solid #e0e0e0;
            position: relative;
        }

        .profile-header-content {
            display: flex;
            align-items: flex-end;
            gap: 30px;
            margin-top: -80px;
        }

        .profile-picture-container {
            position: relative;
        }

        .profile-picture {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            border: 6px solid white;
            background: linear-gradient(135deg, var(--green-primary) 0%, var(--green-secondary) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4em;
            font-weight: 700;
            color: white;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }

        .profile-picture img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .change-photo-btn {
            position: absolute;
            bottom: 10px;
            right: 10px;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--green-secondary);
            border: 3px solid white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .change-photo-btn:hover {
            transform: scale(1.1);
            background: var(--green-primary);
        }

        .profile-info {
            flex: 1;
            padding-bottom: 20px;
        }

        .profile-name {
            font-size: 2em;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .profile-username {
            color: var(--text-muted);
            font-size: 1.1em;
            margin-bottom: 10px;
        }

        .profile-stats {
            display: flex;
            gap: 30px;
            margin-top: 15px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            display: block;
            font-size: 1.3em;
            font-weight: 700;
            color: var(--green-secondary);
        }

        .stat-label {
            font-size: 0.9em;
            color: var(--text-muted);
        }

        .profile-nav {
            background: white;
            border-bottom: 1px solid #e0e0e0;
            padding: 0 40px;
        }

        .nav-tabs {
            display: flex;
            gap: 20px;
        }

        .nav-tab {
            padding: 15px 20px;
            border: none;
            background: none;
            font-weight: 600;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
        }

        .nav-tab.active {
            color: var(--green-secondary);
            border-bottom-color: var(--green-secondary);
        }

        .nav-tab:hover {
            color: var(--green-secondary);
            background: rgba(163, 177, 138, 0.05);
        }

        .profile-content {
            display: grid;
            grid-template-columns: 480px 1fr;
            gap: 20px;
            padding: 20px 40px;
        }

        .profile-sidebar {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .info-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: var(--card-shadow);
        }

        .card-title {
            font-size: 1.2em;
            font-weight: 700;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-item {
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-icon {
            color: var(--green-secondary);
            margin-top: 2px;
        }

        .info-content {
            flex: 1;
        }

        .info-label {
            font-size: 0.85em;
            color: var(--text-muted);
            margin-bottom: 3px;
        }

        .info-value {
            font-weight: 600;
            color: var(--text-primary);
        }

        .profile-main {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--green-secondary) 0%, var(--green-primary) 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(88, 129, 87, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(88, 129, 87, 0.4);
        }

        .btn-secondary {
            background: white;
            color: var(--text-primary);
            border: 2px solid #e0e0e0;
        }

        .btn-secondary:hover {
            border-color: var(--green-secondary);
            color: var(--green-secondary);
        }

        .btn svg {
            flex-shrink: 0;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            padding: 30px;
            max-width: 500px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .modal-title {
            font-size: 1.5em;
            font-weight: 700;
        }

        .close-modal {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            border: none;
            background: #f0f0f0;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .close-modal:hover {
            background: #e0e0e0;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .form-input, .form-textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1em;
            transition: all 0.3s ease;
        }

        .form-input:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--green-secondary);
        }

        .form-textarea {
            min-height: 100px;
            resize: vertical;
            font-family: inherit;
        }

        .file-upload-area {
            border: 2px dashed #e0e0e0;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #fafafa;
        }

        .file-upload-area:hover {
            border-color: var(--green-secondary);
            background: rgba(163, 177, 138, 0.05);
        }

        .file-upload-area.dragging {
            border-color: var(--green-primary);
            background: rgba(163, 177, 138, 0.1);
        }

        .preview-image {
            max-width: 100%;
            max-height: 200px;
            margin-top: 15px;
            border-radius: 8px;
            display: none;
        }

        .message {
            padding: 15px 20px;
            border-radius: 8px;
            margin: 20px 40px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        @media (max-width: 1024px) {
            .profile-content {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .cover-photo {
                height: 250px;
            }

            .profile-header {
                padding: 0 20px 20px;
            }

            .profile-header-content {
                flex-direction: column;
                align-items: center;
                text-align: center;
                margin-top: -60px;
            }

            .profile-picture {
                width: 140px;
                height: 140px;
                font-size: 3em;
            }

            .profile-stats {
                justify-content: center;
            }

            .profile-nav, .profile-content {
                padding: 15px 20px;
            }

            .nav-tabs {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            .nav-tab {
                flex-shrink: 0;
                padding: 10px 16px;
                font-size: 14px;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .info-card {
            animation: fadeIn 0.5s ease forwards;
        }

        /* Estilos para la Topbar */
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
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
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
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
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

        @media (max-width: 480px) {
            .logo-icon {
                width: 60px;
                height: 60px;
            }
        }

        /* Ajustar el contenedor para que no quede detrás de la topbar */
        .container {
            margin-top: 80px;
        }

        .top-header {
            margin-top: 0;
            position: relative;
            top: 0;
        }
    </style>
</head>
<body>
    <!-- Barra superior (Topbar) -->
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
            <div class="topbar-icon chatbot-icon chat-icon" title="Chat">
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

    <div class="top-header">
        <a href="../home.php" class="back-button">
            <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.42-1.41L7.83 13H20v-2z"/>
            </svg>
            Volver al inicio
        </a>
    </div>

    <div class="container">
        <?php if ($successMessage): ?>
            <div class="message success">
                <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                </svg>
                <?php echo htmlspecialchars($successMessage); ?>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="message error">
                <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                </svg>
                <?php echo htmlspecialchars($errorMessage); ?>
            </div>
        <?php endif; ?>

        <!-- Foto de portada -->
        <div class="cover-photo">
            <?php 
            $coverPath = '../../uploads/' . ($usuario['fotoPortada'] ?? '');
            $realPath = __DIR__ . '/../../uploads/' . ($usuario['fotoPortada'] ?? '');
            if (!empty($usuario['fotoPortada']) && file_exists($realPath)): 
            ?>
                <img src="<?php echo htmlspecialchars($coverPath); ?>" alt="Portada">
            <?php endif; ?>
            <button class="change-cover-btn" onclick="openModal('coverModal')">
                <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
                </svg>
                Cambiar portada
            </button>
        </div>

        <!-- Header del perfil -->
        <div class="profile-header">
            <div class="profile-header-content">
                <div class="profile-picture-container">
                    <div class="profile-picture">
                        <?php if (!empty($usuario['fotoPerfil'])): ?>
                            <img src="../../uploads/<?php echo htmlspecialchars($usuario['fotoPerfil']); ?>" alt="Foto de perfil">
                        <?php else: ?>
                            <?php echo strtoupper(substr($usuario['nombre'] ?? 'U', 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <button class="change-photo-btn" onclick="openModal('photoModal')">
                        <svg width="20" height="20" fill="white" viewBox="0 0 24 24">
                            <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
                        </svg>
                    </button>
                </div>

                <div class="profile-info">
                    <h1 class="profile-name"><?php echo htmlspecialchars($usuario['nombre']); ?></h1>
                    <div class="profile-username">@<?php echo htmlspecialchars($usuario['userName']); ?></div>
                    
                    <div class="profile-stats">
                        <div class="stat-item">
                            <span class="stat-number"><?php echo $totalPublicaciones; ?></span>
                            <span class="stat-label">Publicaciones</span>
                        </div>
                        <div class="stat-item" onclick="window.location.href='../lista_usuarios.php?type=seguidores&id=<?php echo $userId; ?>'" style="cursor: pointer;">
                            <span class="stat-number"><?php echo $totalSeguidores; ?></span>
                            <span class="stat-label">Seguidores</span>
                        </div>
                        <div class="stat-item" onclick="window.location.href='../lista_usuarios.php?type=siguiendo&id=<?php echo $userId; ?>'" style="cursor: pointer;">
                            <span class="stat-number"><?php echo $totalSiguiendo; ?></span>
                            <span class="stat-label">Siguiendo</span>
                        </div>
                        <div class="stat-item" onclick="window.location.href='../lista_usuarios.php?type=amigos&id=<?php echo $userId; ?>'" style="cursor: pointer;">
                            <span class="stat-number"><?php echo $totalAmigos; ?></span>
                            <span class="stat-label">Amigos</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navegación CON TAB DE FOTOS -->
        <div class="profile-nav">
            <div class="nav-tabs">
                <button class="nav-tab active">Acerca de</button>
                <button class="nav-tab" onclick="window.location.href='../products/publicaciones.php'">Publicaciones</button>
                <button class="nav-tab" onclick="window.location.href='galeria_fotos.php'">
                    <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24" style="vertical-align: middle; margin-right: 5px;">
                        <path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/>
                    </svg>
                    Fotos
                </button>
            </div>
        </div>

        <!-- Contenido -->
        <div class="profile-content">
            <!-- Sidebar -->
            <div class="profile-sidebar">
                <div class="info-card">
                    <h2 class="card-title">
                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/>
                        </svg>
                        Información
                    </h2>

                    <?php if (!empty($usuario['biografia'])): ?>
                    <div class="info-item">
                        <svg class="info-icon" width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/>
                        </svg>
                        <div class="info-content">
                            <div class="info-label">Biografía</div>
                            <div class="info-value"><?php echo nl2br(htmlspecialchars($usuario['biografia'])); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="info-item">
                        <svg class="info-icon" width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                        </svg>
                        <div class="info-content">
                            <div class="info-label">Correo electrónico</div>
                            <div class="info-value"><?php echo htmlspecialchars($usuario['correo']); ?></div>
                        </div>
                    </div>

                    <?php if (!empty($usuario['telefono'])): ?>
                    <div class="info-item">
                        <svg class="info-icon" width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/>
                        </svg>
                        <div class="info-content">
                            <div class="info-label">Teléfono</div>
                            <div class="info-value"><?php echo htmlspecialchars($usuario['telefono']); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="info-item">
                        <svg class="info-icon" width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M9 11H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2zm2-7h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V9h14v11z"/>
                        </svg>
                        <div class="info-content">
                            <div class="info-label">Fecha de nacimiento</div>
                            <div class="info-value"><?php echo formatearFecha($usuario['fechaNacimiento']); ?></div>
                        </div>
                    </div>

                    <div class="info-item">
                        <svg class="info-icon" width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                        </svg>
                        <div class="info-content">
                            <div class="info-label">Ubicación</div>
                            <div class="info-value"><?php echo !empty($usuario['ubicacion']) ? htmlspecialchars($usuario['ubicacion']) : 'No especificada'; ?></div>
                        </div>
                    </div>

                    <?php if (!empty($usuario['sitioWeb'])): ?>
                    <div class="info-item">
                        <svg class="info-icon" width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M3.9 12c0-1.71 1.39-3.1 3.1-3.1h4V7H7c-2.76 0-5 2.24-5 5s2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1zM8 13h8v-2H8v2zm9-6h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1s-1.39 3.1-3.1 3.1h-4V17h4c2.76 0 5-2.24 5-5s-2.24-5-5-5z"/>
                        </svg>
                        <div class="info-content">
                            <div class="info-label">Sitio web</div>
                            <div class="info-value">
                                <a href="<?php echo htmlspecialchars($usuario['sitioWeb']); ?>" target="_blank" style="color: var(--green-secondary);">
                                    <?php echo htmlspecialchars($usuario['sitioWeb']); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Contenido principal CON BOTÓN DE FOTOS -->
            <div class="profile-main">
                <div class="info-card">
                    <h2 class="card-title">Acciones rápidas</h2>
                    <div class="action-buttons">
                        <button class="btn btn-primary" onclick="openModal('editModal')">
                            <svg width="18" height="18" fill="white" viewBox="0 0 24 24">
                                <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
                            </svg>
                            Editar información
                        </button>
                        
                        <a href="galeria_fotos.php" class="btn btn-secondary">
                            <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/>
                            </svg>
                            Ver mis fotos
                        </a>
                        
                        <a href="../products/publicaciones.php" class="btn btn-secondary">
                            <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M18 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 4h5v8l-2.5-1.5L6 12V4z"/>
                            </svg>
                            Mis publicaciones
                        </a>
                        
                        <a href="../chat/chat.php" class="btn btn-secondary">
                            <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M20 2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h4l4 4 4-4h4c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/>
                            </svg>
                            Mis chats
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Cambiar foto de perfil -->
    <div id="photoModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Cambiar foto de perfil</h3>
                <button class="close-modal" onclick="closeModal('photoModal')">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                    </svg>
                </button>
            </div>
            <form id="photoForm" enctype="multipart/form-data">
                <div class="form-group">
                    <div class="file-upload-area" id="photoDropZone">
                        <svg width="48" height="48" fill="var(--green-secondary)" viewBox="0 0 24 24">
                            <path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/>
                        </svg>
                        <p style="margin: 10px 0; font-weight: 600;">Arrastra una imagen aquí</p>
                        <p style="color: var(--text-muted); font-size: 0.9em;">o haz clic para seleccionar</p>
                        <input type="file" id="profileImageInput" name="profileImage" accept="image/*" style="display: none;">
                    </div>
                    <img id="photoPreview" class="preview-image" alt="Vista previa">
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">
                    <svg width="18" height="18" fill="white" viewBox="0 0 24 24">
                        <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                    </svg>
                    Guardar foto de perfil
                </button>
                <button type="button" class="btn btn-secondary" onclick="deleteProfilePhoto()" style="width: 100%; justify-content: center; margin-top: 10px; background: #e74c3c; color: white; border: none;">
                    <svg width="18" height="18" fill="white" viewBox="0 0 24 24">
                        <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                    </svg>
                    Eliminar foto actual
                </button>
            </form>
        </div>
    </div>

    <!-- Modal: Cambiar foto de portada -->
    <div id="coverModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Cambiar foto de portada</h3>
                <button class="close-modal" onclick="closeModal('coverModal')">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                    </svg>
                </button>
            </div>
            <form id="coverForm" enctype="multipart/form-data">
                <div class="form-group">
                    <div class="file-upload-area" id="coverDropZone">
                        <svg width="48" height="48" fill="var(--green-secondary)" viewBox="0 0 24 24">
                            <path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/>
                        </svg>
                        <p style="margin: 10px 0; font-weight: 600;">Arrastra una imagen aquí</p>
                        <p style="color: var(--text-muted); font-size: 0.9em;">o haz clic para seleccionar</p>
                        <input type="file" id="coverImageInput" name="coverImage" accept="image/*" style="display: none;">
                    </div>
                    <img id="coverPreview" class="preview-image" alt="Vista previa">
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">
                    <svg width="18" height="18" fill="white" viewBox="0 0 24 24">
                        <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                    </svg>
                    Guardar foto de portada
                </button>
            </form>
        </div>
    </div>

    <!-- Modal: Editar información -->
    <div id="editModal" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h3 class="modal-title">Editar información del perfil</h3>
                <button class="close-modal" onclick="closeModal('editModal')">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                    </svg>
                </button>
            </div>
            <form id="editForm" method="POST" action="update_profile.php">
                <!-- Información Personal -->
                <div style="margin-bottom: 25px;">
                    <h4 style="color: var(--text-primary); margin-bottom: 15px; font-size: 1.1em; display: flex; align-items: center; gap: 8px;">
                        <svg width="20" height="20" fill="var(--green-secondary)" viewBox="0 0 24 24">
                            <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                        </svg>
                        Información Personal
                    </h4>
                    
                    <div class="form-group">
                        <label class="form-label">Nombre completo *</label>
                        <input type="text" class="form-input" name="nombre" value="<?php echo htmlspecialchars($usuario['nombre'] ?? ''); ?>" placeholder="Tu nombre completo" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Nombre de usuario *</label>
                        <input type="text" class="form-input" name="userName" value="<?php echo htmlspecialchars($usuario['userName'] ?? ''); ?>" placeholder="usuario123" required>
                        <small style="color: var(--text-muted); font-size: 0.85em;">Sin espacios ni caracteres especiales</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Fecha de nacimiento</label>
                        <input type="date" class="form-input" name="fechaNacimiento" value="<?php echo $usuario['fechaNacimiento'] ?? ''; ?>">
                    </div>
                </div>

                <!-- Información de Contacto -->
                <div style="margin-bottom: 25px;">
                    <h4 style="color: var(--text-primary); margin-bottom: 15px; font-size: 1.1em; display: flex; align-items: center; gap: 8px;">
                        <svg width="20" height="20" fill="var(--green-secondary)" viewBox="0 0 24 24">
                            <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                        </svg>
                        Información de Contacto
                    </h4>

                    <div class="form-group">
                        <label class="form-label">Correo electrónico *</label>
                        <input type="email" class="form-input" name="correo" value="<?php echo htmlspecialchars($usuario['correo'] ?? ''); ?>" placeholder="correo@ejemplo.com" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Teléfono</label>
                        <input type="tel" class="form-input" name="telefono" value="<?php echo htmlspecialchars($usuario['telefono'] ?? ''); ?>" placeholder="1234567890" maxlength="10" pattern="[0-9]{10}">
                        <small style="color: var(--text-muted); font-size: 0.85em;">10 dígitos sin espacios</small>
                    </div>
                </div>

                <!-- Información Adicional -->
                <div style="margin-bottom: 25px;">
                    <h4 style="color: var(--text-primary); margin-bottom: 15px; font-size: 1.1em; display: flex; align-items: center; gap: 8px;">
                        <svg width="20" height="20" fill="var(--green-secondary)" viewBox="0 0 24 24">
                            <path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/>
                        </svg>
                        Información Adicional
                    </h4>

                    <div class="form-group">
                        <label class="form-label">Biografía</label>
                        <textarea class="form-textarea" name="biografia" placeholder="Cuéntanos sobre ti..." maxlength="500"><?php echo htmlspecialchars($usuario['biografia'] ?? ''); ?></textarea>
                        <small style="color: var(--text-muted); font-size: 0.85em;">Máximo 500 caracteres</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Ubicación</label>
                        <input type="text" class="form-input" name="ubicacion" value="<?php echo htmlspecialchars($usuario['ubicacion'] ?? ''); ?>" placeholder="Ciudad, País">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Sitio web</label>
                        <input type="url" class="form-input" name="sitioWeb" value="<?php echo htmlspecialchars($usuario['sitioWeb'] ?? ''); ?>" placeholder="https://tu-sitio.com">
                    </div>
                </div>

                <!-- Cambiar Contraseña (Opcional) -->
                <div style="margin-bottom: 25px; padding: 20px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid var(--green-secondary);">
                    <h4 style="color: var(--text-primary); margin-bottom: 15px; font-size: 1.1em; display: flex; align-items: center; gap: 8px;">
                        <svg width="20" height="20" fill="var(--green-secondary)" viewBox="0 0 24 24">
                            <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                        </svg>
                        Cambiar Contraseña (Opcional)
                    </h4>
                    <p style="color: var(--text-muted); font-size: 0.9em; margin-bottom: 15px;">Deja estos campos en blanco si no quieres cambiar tu contraseña</p>

                    <div class="form-group">
                        <label class="form-label">Nueva contraseña</label>
                        <input type="password" class="form-input" name="nueva_password" id="nueva_password" placeholder="Mínimo 6 caracteres" minlength="6">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Confirmar nueva contraseña</label>
                        <input type="password" class="form-input" name="confirmar_password" id="confirmar_password" placeholder="Repite la contraseña">
                    </div>

                    <div id="password-error" style="display: none; color: #dc3545; font-size: 0.9em; margin-top: 10px; padding: 10px; background: #f8d7da; border-radius: 5px;">
                        Las contraseñas no coinciden
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">
                    <svg width="18" height="18" fill="white" viewBox="0 0 24 24">
                        <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                    </svg>
                    Guardar todos los cambios
                </button>
            </form>
        </div>
    </div>

    <script>
        // Funciones para abrir/cerrar modales
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        // Cerrar modal al hacer clic fuera
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal(this.id);
                }
            });
        });

        // Configurar upload de foto de perfil
        setupImageUpload('photoDropZone', 'profileImageInput', 'photoPreview', 'photoForm', 'profile');

        // Configurar upload de foto de portada
        setupImageUpload('coverDropZone', 'coverImageInput', 'coverPreview', 'coverForm', 'cover');

        function deleteProfilePhoto() {
            if (!confirm('¿Estás seguro de que quieres eliminar tu foto de perfil?')) return;
            
            fetch('eliminar_foto.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'tipo=perfil'
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'No se pudo eliminar'));
                }
            })
            .catch(err => console.error(err));
        }

        function setupImageUpload(dropZoneId, inputId, previewId, formId, type) {
            const dropZone = document.getElementById(dropZoneId);
            const input = document.getElementById(inputId);
            const preview = document.getElementById(previewId);
            const form = document.getElementById(formId);

            // Click en la zona de drop abre el selector
            dropZone.addEventListener('click', () => input.click());

            // Drag & Drop
            dropZone.addEventListener('dragover', (e) => {
                e.preventDefault();
                dropZone.classList.add('dragging');
            });

            dropZone.addEventListener('dragleave', () => {
                dropZone.classList.remove('dragging');
            });

            dropZone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropZone.classList.remove('dragging');
                
                if (e.dataTransfer.files.length) {
                    input.files = e.dataTransfer.files;
                    handleFileSelect(e.dataTransfer.files[0], preview);
                }
            });

            // Cambio de archivo
            input.addEventListener('change', (e) => {
                if (e.target.files.length) {
                    handleFileSelect(e.target.files[0], preview);
                }
            });

            // Submit del formulario
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                
                if (!input.files.length) {
                    alert('Por favor selecciona una imagen');
                    return;
                }

                const formData = new FormData();
                formData.append(type === 'profile' ? 'profileImage' : 'coverImage', input.files[0]);
                formData.append('imageType', type);

                const submitBtn = form.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<svg width="18" height="18" fill="white" viewBox="0 0 24 24" class="spinning"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" opacity="0.3"/><path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/></svg> Subiendo...';

                try {
                    const response = await fetch('upload_profile_image.php', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();

                    if (result.success) {
                        alert('Imagen actualizada correctamente');
                        location.reload();
                    } else {
                        alert('Error: ' + result.message);
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                    }
                } catch (error) {
                    alert('Error al subir la imagen');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            });
        }

        function handleFileSelect(file, previewElement) {
            if (!file.type.startsWith('image/')) {
                alert('Por favor selecciona una imagen válida');
                return;
            }

            if (file.size > 5 * 1024 * 1024) {
                alert('La imagen es demasiado grande. Máximo 5MB');
                return;
            }

            const reader = new FileReader();
            reader.onload = (e) => {
                previewElement.src = e.target.result;
                previewElement.style.display = 'block';
            };
            reader.readAsDataURL(file);
        }

        // Animación de spinning
        const style = document.createElement('style');
        style.textContent = `
            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
            .spinning {
                animation: spin 1s linear infinite;
            }
        `;
        document.head.appendChild(style);

        // Validación del formulario de edición
        document.getElementById('editForm').addEventListener('submit', function(e) {
            const nuevaPassword = document.getElementById('nueva_password').value;
            const confirmarPassword = document.getElementById('confirmar_password').value;
            const errorDiv = document.getElementById('password-error');

            // Si se está intentando cambiar la contraseña
            if (nuevaPassword || confirmarPassword) {
                if (nuevaPassword !== confirmarPassword) {
                    e.preventDefault();
                    errorDiv.style.display = 'block';
                    document.getElementById('confirmar_password').focus();
                    return false;
                }

                if (nuevaPassword.length < 6) {
                    e.preventDefault();
                    errorDiv.textContent = 'La contraseña debe tener al menos 6 caracteres';
                    errorDiv.style.display = 'block';
                    document.getElementById('nueva_password').focus();
                    return false;
                }
            }

            errorDiv.style.display = 'none';

            // Validación de teléfono
            const telefono = document.querySelector('input[name="telefono"]').value;
            if (telefono && !/^\d{10}$/.test(telefono)) {
                e.preventDefault();
                alert('El teléfono debe tener exactamente 10 dígitos');
                return false;
            }

            // Validación de nombre de usuario
            const userName = document.querySelector('input[name="userName"]').value;
            if (!/^[a-zA-Z0-9_]+$/.test(userName)) {
                e.preventDefault();
                alert('El nombre de usuario solo puede contener letras, números y guión bajo');
                return false;
            }

            // Mostrar indicador de carga
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<svg width="18" height="18" fill="white" viewBox="0 0 24 24" class="spinning"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" opacity="0.3"/><path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/></svg> Guardando...';
        });

        // Ocultar error de contraseña cuando se escribe
        document.getElementById('confirmar_password').addEventListener('input', function() {
            document.getElementById('password-error').style.display = 'none';
        });

        document.getElementById('nueva_password').addEventListener('input', function() {
            document.getElementById('password-error').style.display = 'none';
        });

        // Validación en tiempo real del teléfono
        document.querySelector('input[name="telefono"]').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);
        });

        // Validación en tiempo real del nombre de usuario
        document.querySelector('input[name="userName"]').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^a-zA-Z0-9_]/g, '');
        });

        // Contador de caracteres para biografía
        const biografiaTextarea = document.querySelector('textarea[name="biografia"]');
        if (biografiaTextarea) {
            const counterDiv = document.createElement('div');
            counterDiv.style.cssText = 'text-align: right; color: var(--text-muted); font-size: 0.85em; margin-top: 5px;';
            biografiaTextarea.parentNode.appendChild(counterDiv);

            function updateCounter() {
                const length = biografiaTextarea.value.length;
                const max = biografiaTextarea.maxLength;
                counterDiv.textContent = `${length}/${max} caracteres`;
                counterDiv.style.color = length > max * 0.9 ? '#dc3545' : 'var(--text-muted)';
            }

            biografiaTextarea.addEventListener('input', updateCounter);
            updateCounter();
        }

        // Auto-ocultar mensajes después de 5 segundos
        setTimeout(() => {
            document.querySelectorAll('.message').forEach(msg => {
                msg.style.transition = 'opacity 0.5s ease';
                msg.style.opacity = '0';
                setTimeout(() => msg.remove(), 500);
            });
        }, 5000);

        console.log('✅ Perfil cargado correctamente');
    </script>
</body>
</html>