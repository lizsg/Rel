<?php
session_start();

if (!isset($_SESSION['usuario']) || empty($_SESSION['usuario'])) {
    header("Location: ../auth/login.php");
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    header("Location: ../auth/login.php");
    exit();
}

// Variables para la búsqueda
$usuariosEncontrados = [];
$error = null;
$searchTerm = '';

try {
    $conn = new mysqli(SERVER_NAME, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception("Conexión fallida: " . $conn->connect_error);
    }

    // Procesar búsqueda de usuarios
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["buscador"])) {
        $searchTerm = trim($_POST["buscador"]);
        if (!empty($searchTerm)) {
            $parametroBusqueda = "%" . $searchTerm . "%";
            
            $stmt = $conn->prepare("SELECT idUsuario, userName FROM Usuarios WHERE userName LIKE ? AND idUsuario != ?");
            $stmt->bind_param("si", $parametroBusqueda, $userId);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $usuariosEncontrados[] = $row;
                }
            } else {
                $error = "No se encontró ningún usuario con ese nombre";
            }
            $stmt->close();
        } else {
            $error = "Ingresa un nombre para buscar";
        }
    }

    // Obtener conversaciones existentes
    $conversaciones = [];
    $stmt = $conn->prepare("
        SELECT c.idConversacion, c.idUsuario1, c.idUsuario2, c.ultimoMensaje,
               u1.userName as userName1, u2.userName as userName2,
               m.contenido as ultimoMensajeTexto, m.fechaEnvio as fechaUltimoMensaje
        FROM Conversaciones c
        LEFT JOIN Usuarios u1 ON c.idUsuario1 = u1.idUsuario
        LEFT JOIN Usuarios u2 ON c.idUsuario2 = u2.idUsuario
        LEFT JOIN Mensajes m ON m.idConversacion = c.idConversacion 
            AND m.fechaEnvio = (
                SELECT MAX(fechaEnvio) 
                FROM Mensajes 
                WHERE idConversacion = c.idConversacion
            )
        WHERE c.idUsuario1 = ? OR c.idUsuario2 = ?
        ORDER BY COALESCE(m.fechaEnvio, c.fechaCreacion) DESC
    ");
    $stmt->bind_param("ii", $userId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $otherUserName = ($row['idUsuario1'] == $userId) ? $row['userName2'] : $row['userName1'];
        $otherUserId = ($row['idUsuario1'] == $userId) ? $row['idUsuario2'] : $row['idUsuario1'];
        
        $conversaciones[] = [
            'idConversacion' => $row['idConversacion'],
            'otherUserId' => $otherUserId,
            'otherUserName' => $otherUserName,
            'ultimoMensaje' => $row['ultimoMensajeTexto'] ?? 'Nueva conversación',
            'fechaUltimoMensaje' => $row['fechaUltimoMensaje'] ?? $row['ultimoMensaje']
        ];
    }
    
    $conn->close();
} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Chat | RELEE</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            background: linear-gradient(135deg, #f8f6f3 0%, #f0ede8 100%);
            color: #2c2016;
            height: 100vh;
            overflow: hidden;
            padding-bottom: 65px;
        }

        /* Layout principal */
        .chat-app {
            display: flex;
            height: calc(100vh - 65px);
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }

        /* Sidebar */
        .chat-sidebar {
            width: 350px;
            background: #f8f9fa;
            border-right: 1px solid #e9ecef;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .sidebar-header {
            background: linear-gradient(135deg, #a3b18a 0%, #8fa377 100%);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            flex-shrink: 0;
        }

        .logo {
            font-size: 20px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .new-chat-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .new-chat-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.1);
        }

        /* Sección de búsqueda */
        .search-section {
            background: white;
            border-bottom: 1px solid #e9ecef;
            padding: 15px;
            flex-shrink: 0;
        }

        .search-bar {
            display: flex;
            background: #f5f5f5;
            border-radius: 25px;
            overflow: hidden;
            margin-bottom: 10px;
        }

        .search-bar input {
            flex: 1;
            padding: 12px 15px;
            border: none;
            background: transparent;
            outline: none;
            font-size: 14px;
        }

        .search-bar button {
            background: linear-gradient(135deg, #a3b18a 0%, #8fa377 100%);
            border: none;
            padding: 0 15px;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .search-bar button:hover {
            background: linear-gradient(135deg, #8fa377 0%, #7a8f67 100%);
        }

        .cancel-search {
            background: #6c757d;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 15px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s ease;
        }

        .cancel-search:hover {
            background: #5a6268;
        }

        /* Resultados de búsqueda */
        .search-results {
            margin-top: 15px;
        }

        .search-results h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .user-result {
            display: flex;
            align-items: center;
            padding: 12px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 5px;
        }

        .user-result:hover {
            background: #e9ecef;
        }

        .user-avatar,
        .conversation-avatar,
        .chat-user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #a3b18a 0%, #8fa377 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 16px;
            margin-right: 12px;
            flex-shrink: 0;
        }

        .user-info {
            flex: 1;
        }

        .user-name {
            font-weight: 500;
            font-size: 14px;
        }

        .search-error {
            text-align: center;
            color: #dc3545;
            font-size: 14px;
            padding: 20px;
            font-style: italic;
        }

        /* Lista de conversaciones */
        .conversations-list {
            flex: 1;
            overflow-y: auto;
            min-height: 0;
        }

        .no-conversations {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            padding: 40px 20px;
            text-align: center;
            color: #6c757d;
        }

        .start-chat-btn {
            background: linear-gradient(135deg, #a3b18a 0%, #8fa377 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 500;
            margin-top: 15px;
            transition: all 0.3s ease;
        }

        .start-chat-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(163, 177, 138, 0.4);
        }

        .conversation-item {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .conversation-item:hover {
            background: #f8f9fa;
        }

        .conversation-item.active {
            background: linear-gradient(135deg, #a3b18a 0%, #8fa377 100%);
            color: white;
        }

        .conversation-item.active .conversation-info {
            color: white;
        }

        .conversation-item.active .conversation-preview,
        .conversation-item.active .conversation-time {
            color: rgba(255, 255, 255, 0.8);
        }

        .conversation-info {
            flex: 1;
            min-width: 0;
            margin-right: 10px;
        }

        .conversation-name {
            font-weight: 600;
            font-size: 15px;
            margin-bottom: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .conversation-preview {
            font-size: 13px;
            color: #6c757d;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .conversation-time {
            font-size: 11px;
            color: #adb5bd;
            flex-shrink: 0;
        }

        /* CORRECCION PRINCIPAL: Área de chat con layout fijo */
        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: white;
            height: calc(100vh - 65px);
            overflow: hidden;
        }

        .chat-placeholder {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fafafa;
        }

        .placeholder-content {
            text-align: center;
            color: #6c757d;
        }

        .placeholder-content svg {
            margin-bottom: 20px;
        }

        .placeholder-content h3 {
            margin: 0 0 10px 0;
            font-size: 18px;
        }

        .placeholder-content p {
            margin: 0;
            font-size: 14px;
        }

        /* CORRECCION: Chat activo con layout correcto */
        .active-chat {
            display: flex;
            flex-direction: column;
            height: 100%;
            overflow: hidden;
        }

        .chat-header {
            background: linear-gradient(135deg, #a3b18a 0%, #8fa377 100%);
            color: white;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            flex-shrink: 0;
            height: 70px;
        }

        .back-button {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .back-button:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .chat-user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .chat-user-name {
            font-size: 16px;
            font-weight: 600;
            margin: 0;
        }

        .chat-user-status {
            font-size: 12px;
            opacity: 0.8;
        }

        /* CORRECCION PRINCIPAL: Contenedor de mensajes */
        .messages-container {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background: #f5f5f5;
            scroll-behavior: smooth;
            min-height: 0;
        }

        .no-messages {
            text-align: center;
            color: #6c757d;
            font-style: italic;
            padding: 40px 20px;
        }

        .message {
            margin-bottom: 15px;
            display: flex;
            flex-direction: column;
            max-width: 70%;
            animation: fadeIn 0.3s ease;
        }

        .message.sent {
            align-self: flex-end;
            align-items: flex-end;
        }

        .message.received {
            align-self: flex-start;
            align-items: flex-start;
        }

        .message-content {
            background: white;
            padding: 12px 16px;
            border-radius: 18px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            word-wrap: break-word;
            line-height: 1.4;
            max-width: 100%;
        }

        .message.sent .message-content {
            background: linear-gradient(135deg, #a3b18a 0%, #8fa377 100%);
            color: white;
        }

        .message.received .message-content {
            background: white;
            color: #2c2016;
            border: 1px solid #e0e0e0;
        }

        .message-time {
            font-size: 11px;
            color: #888;
            margin-top: 4px;
            opacity: 0.7;
        }

        /* CORRECCION: Input de mensaje con altura fija */
        .message-input-container {
            background: white;
            border-top: 1px solid #e0e0e0;
            padding: 15px 20px;
            flex-shrink: 0;
            min-height: 70px;
        }

        .message-input {
            display: flex;
            align-items: flex-end;
            gap: 10px;
            background: #f5f5f5;
            border-radius: 25px;
            padding: 10px 15px;
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }

        .message-input:focus-within {
            border-color: #a3b18a;
            background: white;
            box-shadow: 0 0 0 3px rgba(163, 177, 138, 0.1);
        }

        #messageText {
            flex: 1;
            border: none;
            outline: none;
            background: transparent;
            resize: none;
            font-family: inherit;
            font-size: 14px;
            line-height: 1.4;
            max-height: 120px;
            min-height: 20px;
        }

        #messageText::placeholder {
            color: #999;
        }

        #sendButton {
            background: linear-gradient(135deg, #a3b18a 0%, #8fa377 100%);
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            flex-shrink: 0;
        }

        #sendButton:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(163, 177, 138, 0.4);
        }

        #sendButton:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        /* Barra inferior */
        .bottombar {
            position: fixed;
            bottom: 0;
            width: 100%;
            height: 55px;
            background: linear-gradient(135deg, rgba(216, 226, 220, 0.95) 0%, rgba(196, 188, 178, 0.95) 100%);
            backdrop-filter: blur(20px);
            display: flex;
            justify-content: space-around;
            align-items: center;
            border-top: 1px solid rgba(196, 188, 178, 0.3);
            box-shadow: 0 -6px 25px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            flex-shrink: 0;
        }

        .bottom-button {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #a3b18a 0%, #8fa377 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 18px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 15px rgba(163, 177, 138, 0.3);
            position: relative;
            overflow: hidden;
            text-decoration: none;
        }

        .bottom-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(163, 177, 138, 0.4);
        }

        .bottom-button span {
            font-size: 9px;
            margin-top: 2px;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
            letter-spacing: 0.3px;
        }

        .bottom-button-wide {
            width: 100px;
            height: 45px;
            font-size: 11px;
            padding: 5px;
        }

        .bottom-button-wide span {
            font-size: 11px;
            margin-top: 0;
            text-align: center;
            line-height: 1.1;
        }

        /* Animaciones */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Scrollbar personalizada */
        .conversations-list::-webkit-scrollbar,
        .messages-container::-webkit-scrollbar {
            width: 6px;
        }

        .conversations-list::-webkit-scrollbar-track,
        .messages-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }

        .conversations-list::-webkit-scrollbar-thumb,
        .messages-container::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }

        .conversations-list::-webkit-scrollbar-thumb:hover,
        .messages-container::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .chat-app {
                flex-direction: column;
                height: calc(100vh - 65px);
            }
            
            .chat-sidebar {
                width: 100%;
                height: 300px;
                border-right: none;
                border-bottom: 1px solid #e9ecef;
                flex-shrink: 0;
            }
            
            .chat-area {
                height: calc(100vh - 365px);
            }
            
            .active-chat .chat-header .back-button {
                display: flex;
            }
            
            .message {
                max-width: 85%;
            }
            
            .chat-placeholder {
                display: none;
            }
            
            .active-chat {
                display: flex !important;
            }
        }

        @media (max-width: 480px) {
            .chat-sidebar {
                height: 250px;
            }
            
            .chat-area {
                height: calc(100vh - 315px);
            }
            
            .sidebar-header {
                padding: 15px;
            }
            
            .logo {
                font-size: 18px;
            }
            
            .message-input-container {
                padding: 12px 15px;
            }
            
            .messages-container {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="chat-app">
        <!-- Sidebar con conversaciones -->
        <div class="chat-sidebar">
            <!-- Header del sidebar -->
            <div class="sidebar-header">
                <div class="logo">RELEE Chat</div>
                <button class="new-chat-btn" id="newChatBtn">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                    </svg>
                </button>
            </div>

            <!-- Buscador (inicialmente oculto) -->
            <div class="search-section" id="searchSection" style="display: none;">
                <form method="POST" action="" id="searchForm">
                    <div class="search-bar">
                        <input type="text" name="buscador" placeholder="Buscar usuario..." 
                               value="<?php echo htmlspecialchars($searchTerm); ?>" id="searchInput">
                        <button type="submit">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                            </svg>
                        </button>
                    </div>
                    <button type="button" class="cancel-search" id="cancelSearch">Cancelar</button>
                </form>

                <!-- Resultados de búsqueda -->
                <?php if (!empty($usuariosEncontrados)): ?>
                    <div class="search-results">
                        <h3>Usuarios encontrados</h3>
                        <?php foreach($usuariosEncontrados as $usuario): ?>
                            <div class="user-result" data-userid="<?php echo $usuario['idUsuario']; ?>" 
                                 data-username="<?php echo htmlspecialchars($usuario['userName']); ?>">
                                <div class="user-avatar">
                                    <?php echo strtoupper(substr($usuario['userName'], 0, 1)); ?>
                                </div>
                                <div class="user-info">
                                    <span class="user-name"><?php echo htmlspecialchars($usuario['userName']); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php elseif(isset($error) && !empty($searchTerm)): ?>
                    <div class="search-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
            </div>

            <!-- Lista de conversaciones -->
            <div class="conversations-list" id="conversationsList">
                <?php if (empty($conversaciones)): ?>
                    <div class="no-conversations">
                        <p>No tienes conversaciones aún</p>
                        <button class="start-chat-btn" id="startChatBtn">Iniciar nueva conversación</button>
                    </div>
                <?php else: ?>
                    <?php foreach($conversaciones as $conv): ?>
                        <div class="conversation-item" 
                             data-conversation-id="<?php echo $conv['idConversacion']; ?>"
                             data-other-user-id="<?php echo $conv['otherUserId']; ?>"
                             data-other-user-name="<?php echo htmlspecialchars($conv['otherUserName']); ?>">
                            <div class="conversation-avatar">
                                <?php echo strtoupper(substr($conv['otherUserName'], 0, 1)); ?>
                            </div>
                            <div class="conversation-info">
                                <div class="conversation-name"><?php echo htmlspecialchars($conv['otherUserName']); ?></div>
                                <div class="conversation-preview">
                                    <?php echo htmlspecialchars(substr($conv['ultimoMensaje'], 0, 30)); ?>
                                    <?php if (strlen($conv['ultimoMensaje']) > 30) echo '...'; ?>
                                </div>
                            </div>
                            <div class="conversation-time">
                                <?php 
                                if ($conv['fechaUltimoMensaje']) {
                                    $date = new DateTime($conv['fechaUltimoMensaje']);
                                    echo $date->format('H:i');
                                }
                                ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Área de chat -->
        <div class="chat-area">
            <div class="chat-placeholder" id="chatPlaceholder">
                <div class="placeholder-content">
                    <svg width="80" height="80" viewBox="0 0 24 24" fill="currentColor" opacity="0.3">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                    </svg>
                    <h3>Selecciona una conversación</h3>
                    <p>Elige una conversación existente o inicia una nueva</p>
                </div>
            </div>

            <!-- Chat activo (inicialmente oculto) -->
            <div class="active-chat" id="activeChat" style="display: none;">
                <div class="chat-header">
                    <button class="back-button" id="backButton">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/>
                        </svg>
                    </button>
                    <div class="chat-user-info">
                        <div class="chat-user-avatar" id="chatUserAvatar"></div>
                        <div>
                            <div class="chat-user-name" id="chatUserName"></div>
                            <div class="chat-user-status">En línea</div>
                        </div>
                    </div>
                </div>

                <div class="messages-container" id="messagesContainer">
                    <!-- Los mensajes se cargarán aquí -->
                </div>

                <div class="message-input-container">
                    <div class="message-input">
                        <textarea id="messageText" placeholder="Escribe tu mensaje..." rows="1"></textarea>
                        <button id="sendButton" type="button">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bottom navigation -->
    <div class="bottombar">
        <a href="../home.php" class="bottom-button">
            <svg width="22" height="22" fill="white" viewBox="0 0 24 24">
                <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>
            </svg>
            <span>Inicio</span>
        </a>
        <a href="../products/publicaciones.php" class="bottom-button bottom-button-wide">
            <span>Mis Publicaciones</span>
        </a>
        <button class="bottom-button">
            <svg width="22" height="22" fill="white" viewBox="0 0 24 24">
                <path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/>
            </svg>
            <span>Menú</span>
        </button>
    </div>

    <script>
        const currentUserId = <?php echo $userId; ?>;
        let currentConversationId = null;
        let currentOtherUserId = null;
        let currentOtherUserName = '';
        let messageInterval = null;
        let loadingMessages = false;

        // Referencias a elementos DOM
        const newChatBtn = document.getElementById('newChatBtn');
        const searchSection = document.getElementById('searchSection');
        const conversationsList = document.getElementById('conversationsList');
        const cancelSearch = document.getElementById('cancelSearch');
        const chatPlaceholder = document.getElementById('chatPlaceholder');
        const activeChat = document.getElementById('activeChat');
        const messagesContainer = document.getElementById('messagesContainer');
        const messageText = document.getElementById('messageText');
        const sendButton = document.getElementById('sendButton');
        const backButton = document.getElementById('backButton');
        const chatUserName = document.getElementById('chatUserName');
        const chatUserAvatar = document.getElementById('chatUserAvatar');
        const startChatBtn = document.getElementById('startChatBtn');

        // Event listeners
        newChatBtn.addEventListener('click', showSearch);
        if (startChatBtn) startChatBtn.addEventListener('click', showSearch);
        cancelSearch.addEventListener('click', hideSearch);
        backButton.addEventListener('click', closeChat);
        sendButton.addEventListener('click', sendMessage);

        // Auto-resize textarea
        messageText.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        });

        // Send message on Enter (not Shift+Enter)
        messageText.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });

        // Event delegation para conversaciones y resultados de búsqueda
        document.addEventListener('click', function(e) {
            // Click en conversación existente
            if (e.target.closest('.conversation-item')) {
                const item = e.target.closest('.conversation-item');
                const conversationId = item.dataset.conversationId;
                const otherUserId = item.dataset.otherUserId;
                const otherUserName = item.dataset.otherUserName;
                openConversation(conversationId, otherUserId, otherUserName);
            }

            // Click en resultado de búsqueda
            if (e.target.closest('.user-result')) {
                const item = e.target.closest('.user-result');
                const userId = item.dataset.userid;
                const userName = item.dataset.username;
                startNewConversation(userId, userName);
            }
        });

        function showSearch() {
            searchSection.style.display = 'block';
            conversationsList.style.display = 'none';
            document.getElementById('searchInput').focus();
        }

        function hideSearch() {
            searchSection.style.display = 'none';
            conversationsList.style.display = 'block';
            // Limpiar formulario de búsqueda
            document.getElementById('searchForm').reset();
            // Recargar la página para limpiar resultados
            if (window.location.search) {
                window.location.href = window.location.pathname;
            }
        }

        function openConversation(conversationId, otherUserId, otherUserName) {
            currentConversationId = conversationId;
            currentOtherUserId = otherUserId;
            currentOtherUserName = otherUserName;

            // Actualizar UI
            chatUserName.textContent = otherUserName;
            chatUserAvatar.textContent = otherUserName.charAt(0).toUpperCase();
            
            // Mostrar chat
            chatPlaceholder.style.display = 'none';
            activeChat.style.display = 'flex';
            
            // Marcar conversación como activa
            document.querySelectorAll('.conversation-item').forEach(item => {
                item.classList.remove('active');
            });
            document.querySelector(`[data-conversation-id="${conversationId}"]`)?.classList.add('active');

            // Cargar mensajes
            loadMessages();
            
            // Configurar polling cada 3 segundos
            if (messageInterval) clearInterval(messageInterval);
            messageInterval = setInterval(loadMessages, 3000);
        }

        function startNewConversation(userId, userName) {
            // Verificar que no sea el mismo usuario
            if (userId == currentUserId) {
                alert('No puedes iniciar una conversación contigo mismo');
                return;
            }

            // Crear o encontrar conversación
            fetch('../../api/create_conversation.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `other_user_id=${userId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    hideSearch();
                    openConversation(data.conversationId, userId, userName);
                    // Recargar la página para mostrar la nueva conversación en la lista
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    alert('Error al crear conversación: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error de conexión');
            });
        }

        function closeChat() {
            activeChat.style.display = 'none';
            chatPlaceholder.style.display = 'flex';
            
            // Limpiar polling
            if (messageInterval) {
                clearInterval(messageInterval);
                messageInterval = null;
            }
            
            // Limpiar selección activa
            document.querySelectorAll('.conversation-item').forEach(item => {
                item.classList.remove('active');
            });
            
            currentConversationId = null;
            currentOtherUserId = null;
            currentOtherUserName = '';
        }

        function loadMessages() {
            if (!currentConversationId || loadingMessages) return;
            
            loadingMessages = true;
            
            fetch('../../api/get_messages.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'conversacion_id=' + currentConversationId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayMessages(data.messages);
                }
            })
            .catch(error => console.error('Error loading messages:', error))
            .finally(() => {
                loadingMessages = false;
            });
        }

        function displayMessages(messages) {
            // Guardar posición de scroll para evitar parpadeo
            const wasAtBottom = messagesContainer.scrollHeight - messagesContainer.scrollTop <= messagesContainer.clientHeight + 50;
            
            // Obtener mensajes existentes para comparar
            const existingMessages = Array.from(messagesContainer.querySelectorAll('.message')).map(msg => 
                msg.querySelector('.message-content').textContent.trim()
            );
            
            if (messages.length === 0) {
                if (messagesContainer.innerHTML.indexOf('no-messages') === -1) {
                    messagesContainer.innerHTML = '<div class="no-messages">No hay mensajes aún. ¡Envía el primero!</div>';
                }
                return;
            }
            
            // Solo actualizar si hay cambios
            const newMessageContents = messages.map(msg => msg.contenido.trim());
            const hasChanges = JSON.stringify(existingMessages) !== JSON.stringify(newMessageContents);
            
            if (!hasChanges) return;
            
            // Limpiar solo si hay cambios reales
            messagesContainer.innerHTML = '';
            
            messages.forEach((message, index) => {
                const messageDiv = document.createElement('div');
                messageDiv.className = `message ${message.idRemitente == currentUserId ? 'sent' : 'received'}`;
                messageDiv.setAttribute('data-message-id', message.idMensaje);
                
                messageDiv.innerHTML = `
                    <div class="message-content">
                        ${escapeHtml(message.contenido)}
                    </div>
                    <div class="message-time">
                        ${formatTime(message.fechaEnvio)}
                    </div>
                `;
                
                messagesContainer.appendChild(messageDiv);
            });
            
            // Solo hacer scroll si estaba al final o es un mensaje nuevo
            if (wasAtBottom || newMessageContents.length > existingMessages.length) {
                setTimeout(() => {
                    messagesContainer.scrollTop = messagesContainer.scrollHeight;
                }, 50);
            }
        }

        function sendMessage() {
            if (!currentConversationId) return;
            
            const content = messageText.value.trim();
            if (!content) return;
            
            // Deshabilitar botón y cambiar texto
            sendButton.disabled = true;
            const originalSendButton = sendButton.innerHTML;
            sendButton.innerHTML = '<div style="width: 20px; height: 20px; border: 2px solid #fff; border-top: 2px solid transparent; border-radius: 50%; animation: spin 1s linear infinite;"></div>';
            
            // Agregar mensaje temporalmente para feedback inmediato
            const tempMessage = document.createElement('div');
            tempMessage.className = 'message sent';
            tempMessage.style.opacity = '0.7';
            tempMessage.innerHTML = `
                <div class="message-content">
                    ${escapeHtml(content)}
                </div>
                <div class="message-time">Enviando...</div>
            `;
            messagesContainer.appendChild(tempMessage);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
            
            fetch('../../api/send_message.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `conversacion_id=${currentConversationId}&remitente_id=${currentUserId}&contenido=${encodeURIComponent(content)}`
            })
            .then(response => response.json())
            .then(data => {
                // Remover mensaje temporal
                tempMessage.remove();
                
                if (data.success) {
                    messageText.value = '';
                    messageText.style.height = 'auto';
                    // Cargar mensajes inmediatamente
                    loadMessages();
                } else {
                    alert('Error al enviar mensaje: ' + data.message);
                }
            })
            .catch(error => {
                // Remover mensaje temporal en caso de error
                tempMessage.remove();
                console.error('Error:', error);
                alert('Error de conexión');
            })
            .finally(() => {
                sendButton.disabled = false;
                sendButton.innerHTML = originalSendButton;
            });
        }

        function formatTime(datetime) {
            const date = new Date(datetime);
            return date.toLocaleTimeString('es-ES', { 
                hour: '2-digit', 
                minute: '2-digit' 
            });
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Limpiar interval al salir
        window.addEventListener('beforeunload', function() {
            if (messageInterval) {
                clearInterval(messageInterval);
            }
        });
    </script>
</body>
</html>