document.addEventListener('DOMContentLoaded', function() {
    const conversationItems = document.querySelectorAll('.conversation-item');
    const messagesContainer = document.getElementById('messages-container');
    const messageInput = document.getElementById('message-input');
    const sendButton = document.getElementById('send-button');
    
    let currentConversationId = null;
    let messageInterval = null;
    
    // Manejar clic en conversaciones
    conversationItems.forEach(item => {
        item.addEventListener('click', function() {
            const conversationId = this.getAttribute('data-conversation');
            openConversation(conversationId);
            
            // Marcar como activa visualmente
            conversationItems.forEach(i => i.classList.remove('active'));
            this.classList.add('active');
        });
    });
    
    function openConversation(conversationId) {
        currentConversationId = conversationId;
        
        // Limpiar intervalo anterior si existe
        if (messageInterval) {
            clearInterval(messageInterval);
        }
        
        // Cargar mensajes inmediatamente
        loadMessages();
        
        // Configurar polling para nuevos mensajes cada 2 segundos
        messageInterval = setInterval(loadMessages, 2000);
    }
    
    function loadMessages() {
        if (!currentConversationId) return;
        
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
        .catch(error => console.error('Error:', error));
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
        if (!currentConversationId) {
            alert('Selecciona una conversación primero');
            return;
        }
        
        const content = messageInput.value.trim();
        if (!content) return;
        
        // Deshabilitar botón temporalmente
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
                messageInput.value = '';
                messageInput.style.height = 'auto';
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
        const now = new Date();
        const diffTime = Math.abs(now - date);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        
        if (diffDays === 1) {
            return 'Hoy ' + date.toLocaleTimeString('es-ES', { 
                hour: '2-digit', 
                minute: '2-digit' 
            });
        } else if (diffDays === 2) {
            return 'Ayer ' + date.toLocaleTimeString('es-ES', { 
                hour: '2-digit', 
                minute: '2-digit' 
            });
        } else if (diffDays <= 7) {
            return date.toLocaleDateString('es-ES', { 
                weekday: 'short' 
            }) + ' ' + date.toLocaleTimeString('es-ES', { 
                hour: '2-digit', 
                minute: '2-digit' 
            });
        } else {
            return date.toLocaleDateString('es-ES') + ' ' + date.toLocaleTimeString('es-ES', { 
                hour: '2-digit', 
                minute: '2-digit' 
            });
        }
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Auto-resize textarea
    if (messageInput) {
        messageInput.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        });
        
        // Send message events
        messageInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });
    }
    
    if (sendButton) {
        sendButton.addEventListener('click', sendMessage);
    }
    
    // Limpiar intervalo cuando se salga de la página
    window.addEventListener('beforeunload', function() {
        if (messageInterval) {
            clearInterval(messageInterval);
        }
    });
});