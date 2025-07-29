<?php
session_start();

if (!isset($_SESSION['usuario']) || empty($_SESSION['usuario'])) {
    header("Location: ../auth/login.php");
    exit();
}

require_once __DIR__ . '/../../config/database.php';

// Manejar diferentes formatos de sesión
if (isset($_SESSION['usuario']['idUsuario'])) {
    $userId = $_SESSION['usuario']['idUsuario'];
} else {
    $userId = $_SESSION['usuario'];
}

$chatUserId = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;
$chatUserName = '';

if (!$chatUserId) {
    header("Location: chatInicio.php");
    exit();
}

try {
    $conn = new mysqli(SERVER_NAME, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception("Conexión fallida: " . $conn->connect_error);
    }

    // Obtener información del usuario con quien chatear
    $stmt = $conn->prepare("SELECT userName FROM Usuarios WHERE idUsuario = ?");
    $stmt->bind_param("i", $chatUserId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        header("Location: chatInicio.php");
        exit();
    }
    
    $chatUser = $result->fetch_assoc();
    $chatUserName = $chatUser['userName'];
    
    // Verificar si existe una conversación entre estos usuarios
    $stmt = $conn->prepare("SELECT idConversacion FROM Conversaciones 
                           WHERE (idUsuario1 = ? AND idUsuario2 = ?) 
                           OR (idUsuario1 = ? AND idUsuario2 = ?)");
    $stmt->bind_param("iiii", $userId, $chatUserId, $chatUserId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Crear nueva conversación
        $stmt = $conn->prepare("INSERT INTO Conversaciones (idUsuario1, idUsuario2, fechaCreacion, ultimoMensaje) 
                               VALUES (?, ?, NOW(), NOW())");
        $stmt->bind_param("ii", $userId, $chatUserId);
        $stmt->execute();
        $conversacionId = $conn->insert_id;
    } else {
        $conversacion = $result->fetch_assoc();
        $conversacionId = $conversacion['idConversacion'];
    }
    
    $conn->close();
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Chat con <?php echo htmlspecialchars($chatUserName); ?> | RELEE</title>
    <link rel="stylesheet" href="../../assets/css/chat-individual-styles.css">
</head>
<body>
    <div class="chat-container">
        <div class="chat-header">
            <button class="back-button" onclick="window.location.href='chatInicio.php'">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/>
                </svg>
            </button>
            <div class="user-info">
                <h2><?php echo htmlspecialchars($chatUserName); ?></h2>
                <span class="status">En línea</span>
            </div>
        </div>

        <div class="messages-container" id="messages-container">
            <!-- Los mensajes se cargarán aquí -->
        </div>

        <div class="message-input-container">
            <div class="message-input">
                <textarea id="message-text" placeholder="Escribe tu mensaje..." rows="1"></textarea>
                <button id="send-button" type="button">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <script>
        // Variables globales
        const currentUserId = <?php echo $userId; ?>;
        const chatUserId = <?php echo $chatUserId; ?>;
        const conversacionId = <?php echo $conversacionId; ?>;
        const messagesContainer = document.getElementById('messages-container');
        const messageText = document.getElementById('message-text');
        const sendButton = document.getElementById('send-button');
        
        // Cargar mensajes cada 2 segundos (polling simple)
        let loadingMessages = false;
        
        function loadMessages() {
            if (loadingMessages) return;
            loadingMessages = true;
            
            fetch('../../api/get_messages.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'conversacion_id=' + conversacionId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayMessages(data.messages);
                }
            })
            .catch(error => console.error('Error:', error))
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
            const content = messageText.value.trim();
            if (!content) return;
            
            sendButton.disabled = true;
            
            fetch('../../api/send_message.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `conversacion_id=${conversacionId}&remitente_id=${currentUserId}&contenido=${encodeURIComponent(content)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    messageText.value = '';
                    messageText.style.height = 'auto';
                    loadMessages(); // Recargar mensajes inmediatamente
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
        
        // Auto-resize textarea
        messageText.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        });
        
        // Send message events
        sendButton.addEventListener('click', sendMessage);
        messageText.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });
        
        // Cargar mensajes inicialmente y luego cada 2 segundos
        loadMessages();
        setInterval(loadMessages, 2000);
    </script>
</body>
</html>