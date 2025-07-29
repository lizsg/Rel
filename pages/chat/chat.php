<?php
session_start();

if (!isset($_SESSION['usuario']) || empty($_SESSION['usuario'])) {
    header("Location: ../auth/login.php");
    exit();
}

require_once __DIR__ . '/../../config/database.php';

// Manejar diferentes formatos de sesión de forma más robusta
$userId = null;
if (is_array($_SESSION['usuario'])) {
    $userId = $_SESSION['usuario']['idUsuario'] ?? null;
} else {
    $userId = $_SESSION['usuario'];
}

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
            
            // Obtener el nombre de usuario actual para excluirlo
            $stmtCurrentUser = $conn->prepare("SELECT userName FROM Usuarios WHERE idUsuario = ?");
            $stmtCurrentUser->bind_param("i", $userId);
            $stmtCurrentUser->execute();
            $currentUserResult = $stmtCurrentUser->get_result();
            $currentUserName = $currentUserResult->fetch_assoc()['userName'] ?? '';
            
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
    <link rel="stylesheet" href="../../assets/css/chatUsuarios-styles.css">
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
            
            // Configurar polling
            if (messageInterval) clearInterval(messageInterval);
            messageInterval = setInterval(loadMessages, 2000);
        }

        function startNewConversation(userId, userName) {
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
            messagesContainer.innerHTML = '';
            
            if (messages.length === 0) {
                messagesContainer.innerHTML = '<div class="no-messages">No hay mensajes aún. ¡Envía el primero!</div>';
                return;
            }
            
            messages.forEach(message => {
                const messageDiv = document.createElement('div');
                messageDiv.className = `message ${message.idRemitente == currentUserId ? 'sent' : 'received'}`;
                
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
            
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        function sendMessage() {
            if (!currentConversationId) return;
            
            const content = messageText.value.trim();
            if (!content) return;
            
            sendButton.disabled = true;
            
            fetch('../../api/send_message.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `conversacion_id=${currentConversationId}&remitente_id=${currentUserId}&contenido=${encodeURIComponent(content)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    messageText.value = '';
                    messageText.style.height = 'auto';
                    loadMessages();
                } else {
                    alert('Error al enviar mensaje: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error de conexión');
            })
            .finally(() => {
                sendButton.disabled = false;
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