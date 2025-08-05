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

    try {
        $conn = new mysqli(SERVER_NAME, DB_USER, DB_PASS, DB_NAME);
        $conn->set_charset("utf8mb4");
        
        if ($conn->connect_error) {
            throw new Exception("Error de conexión: " . $conn->connect_error);
        }

        // Obtener datos del usuario
        $userStmt = $conn->prepare("SELECT * FROM Usuarios WHERE idUsuario = ?");
        $userStmt->bind_param("i", $userId);
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        
        if ($userResult->num_rows === 0) {
            throw new Exception("Usuario no encontrado");
        }
        
        $usuario = $userResult->fetch_assoc();
        $conn->close();

    } catch (Exception $e) {
        $errorMessage = "Error al cargar datos del usuario: " . $e->getMessage();
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
            --card-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
            --border-radius: 20px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, var(--cream-bg) 0%, #f0ede8 100%);
            color: var(--text-primary);
            padding: 20px;
            min-height: 100vh;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 20px;
            padding: 10px 15px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.5);
            transition: all 0.3s ease;
        }

        .back-button:hover {
            color: var(--green-secondary);
            background: rgba(255, 255, 255, 0.8);
            transform: translateX(-5px);
        }

        .profile-card {
            background: rgba(255, 253, 252, 0.95);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius);
            padding: 40px;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .profile-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .profile-avatar {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, var(--green-primary) 0%, var(--green-secondary) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px auto;
            box-shadow: 0 8px 32px rgba(163, 177, 138, 0.3);
            color: white;
            font-size: 2.5em;
            font-weight: 700;
        }

        .profile-title {
            font-size: 2.2em;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary-brown) 0%, var(--secondary-brown) 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }

        .profile-subtitle {
            color: var(--text-secondary);
            font-size: 1.1em;
            font-weight: 600;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
        }

        .info-item {
            background: rgba(255, 255, 255, 0.6);
            padding: 20px;
            border-radius: 15px;
            border: 1px solid rgba(224, 214, 207, 0.3);
        }

        .info-label {
            color: var(--text-secondary);
            font-size: 0.9em;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-value {
            color: var(--text-primary);
            font-size: 1.1em;
            font-weight: 600;
            word-break: break-all;
        }

        .info-value.empty {
            color: var(--text-muted);
            font-style: italic;
        }

        .error-message {
            background: linear-gradient(135deg, rgba(245, 101, 101, 0.9) 0%, rgba(229, 62, 62, 0.9) 100%);
            color: white;
            padding: 20px 30px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            font-weight: 600;
            text-align: center;
            box-shadow: var(--card-shadow);
        }

        .actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.95em;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--green-secondary) 0%, var(--green-primary) 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(88, 129, 87, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(88, 129, 87, 0.4);
        }

        .btn-secondary {
            background: linear-gradient(135deg, var(--primary-brown) 0%, var(--secondary-brown) 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(107, 66, 38, 0.3);
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(107, 66, 38, 0.4);
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }

            .profile-card {
                padding: 25px;
            }

            .profile-title {
                font-size: 1.8em;
            }

            .info-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .actions {
                flex-direction: column;
                align-items: center;
            }

            .btn {
                width: 100%;
                max-width: 250px;
                justify-content: center;
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

        .info-item {
            animation: fadeInUp 0.6s ease forwards;
        }

        .info-item:nth-child(1) { animation-delay: 0.1s; }
        .info-item:nth-child(2) { animation-delay: 0.2s; }
        .info-item:nth-child(3) { animation-delay: 0.3s; }
        .info-item:nth-child(4) { animation-delay: 0.4s; }
        .info-item:nth-child(5) { animation-delay: 0.5s; }
        .info-item:nth-child(6) { animation-delay: 0.6s; }
    </style>
</head>
<body>
    <div class="container">
        <a href="../home.php" class="back-button">
            <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.42-1.41L7.83 13H20v-2z"/>
            </svg>
            Volver al inicio
        </a>

        <?php if ($errorMessage): ?>
            <div class="error-message">
                ❌ <?php echo htmlspecialchars($errorMessage); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($usuario)): ?>
            <div class="profile-card">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($usuario['nombre'] ?? 'U', 0, 1)); ?>
                    </div>
                    <h1 class="profile-title">Mi Perfil</h1>
                    <p class="profile-subtitle">Información de la cuenta</p>
                </div>

                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2c.55 0 1 .45 1 1v1h4a2 2 0 0 1 2 2v2h1a1 1 0 1 1 0 2h-1v6a3 3 0 0 1-3 3h-1v1a1 1 0 1 1-2 0v-1H9v1a1 1 0 1 1-2 0v-1H6a3 3 0 0 1-3-3v-6H2a1 1 0 1 1 0-2h1V6a2 2 0 0 1 2-2h4V3c0-.55.45-1 1-1z"/>
                            </svg>
                            ID Usuario
                        </div>
                        <div class="info-value">#<?php echo htmlspecialchars($usuario['idUsuario'] ?? 'N/A'); ?></div>
                    </div>

                    <div class="info-item">
                        <div class="info-label">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                            </svg>
                            Nombre
                        </div>
                        <div class="info-value <?php echo empty($usuario['nombre']) ? 'empty' : ''; ?>">
                            <?php echo !empty($usuario['nombre']) ? htmlspecialchars($usuario['nombre']) : 'No especificado'; ?>
                        </div>
                    </div>

                    <div class="info-item">
                        <div class="info-label">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2C13.1 2 14 2.9 14 4C14 5.1 13.1 6 12 6C10.9 6 10 5.1 10 4C10 2.9 10.9 2 12 2ZM21 9V7L15 5.5V10.5L21 9ZM5 7V9L11 10.5V5.5L5 7Z"/>
                            </svg>
                            Usuario
                        </div>
                        <div class="info-value <?php echo empty($usuario['userName']) ? 'empty' : ''; ?>">
                            <?php echo !empty($usuario['userName']) ? '@' . htmlspecialchars($usuario['userName']) : 'No especificado'; ?>
                        </div>
                    </div>

                    <div class="info-item">
                        <div class="info-label">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                            </svg>
                            Correo
                        </div>
                        <div class="info-value <?php echo empty($usuario['correo']) ? 'empty' : ''; ?>">
                            <?php echo !empty($usuario['correo']) ? htmlspecialchars($usuario['correo']) : 'No especificado'; ?>
                        </div>
                    </div>

                    <div class="info-item">
                        <div class="info-label">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/>
                            </svg>
                            Teléfono
                        </div>
                        <div class="info-value <?php echo empty($usuario['telefono']) ? 'empty' : ''; ?>">
                            <?php echo !empty($usuario['telefono']) ? htmlspecialchars($usuario['telefono']) : 'No especificado'; ?>
                        </div>
                    </div>

                    <div class="info-item">
                        <div class="info-label">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M9 11H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2zm2-7h-1V2h-2v2H8V2H6v2H5c-1.1 0-1.99.9-1.99 2L3 20c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V9h14v11z"/>
                            </svg>
                            Fecha Nacimiento
                        </div>
                        <div class="info-value <?php echo empty($usuario['fechaNacimiento']) || $usuario['fechaNacimiento'] === '0000-00-00' ? 'empty' : ''; ?>">
                            <?php echo formatearFecha($usuario['fechaNacimiento'] ?? ''); ?>
                        </div>
                    </div>

                    <?php if (isset($usuario['contraseña'])): ?>
                    <div class="info-item">
                        <div class="info-label">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                            </svg>
                            Contraseña
                        </div>
                        <div class="info-value">••••••••••</div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="actions">
                    <a href="../products/publicaciones.php" class="btn btn-primary">
                        <svg width="16" height="16" fill="white" viewBox="0 0 24 24">
                            <path d="M18 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 4h5v8l-2.5-1.5L6 12V4z"/>
                        </svg>
                        Mis Publicaciones
                    </a>
                    
                    <a href="../chat/chat.php" class="btn btn-secondary">
                        <svg width="16" height="16" fill="white" viewBox="0 0 24 24">
                            <path d="M20 2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h4l4 4 4-4h4c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/>
                        </svg>
                        Mis Chats
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('✅ Perfil de usuario cargado');
            
            const errorMessage = document.querySelector('.error-message');
            if (errorMessage) {
                setTimeout(() => {
                    errorMessage.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    errorMessage.style.opacity = '0';
                    errorMessage.style.transform = 'translateY(-20px)';
                    setTimeout(() => {
                        errorMessage.remove();
                    }, 500);
                }, 5000);
            }
        });
    </script>
</body>
</html>