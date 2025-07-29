// chatUsuarios-script.js
class ChatApp {
    constructor(currentUserId) {
        this.currentUserId = currentUserId;
        this.currentConversationId = null;
        this.currentOtherUserId = null;
        this.currentOtherUserName = '';
        this.messageInterval = null;
        this.loadingMessages = false;
        this.isMobile = window.innerWidth <= 768;
        
        this.initializeElements();
        this.bindEvents();
        this.handleResize();
    }

    initializeElements() {
        // Referencias a elementos DOM
        this.newChatBtn = document.getElementById('newChatBtn');
        this.searchSection = document.getElementById('searchSection');
        this.conversationsList = document.getElementById('conversationsList');
        this.cancelSearch = document.getElementById('cancelSearch');
        this.chatPlaceholder = document.getElementById('chatPlaceholder');
        this.activeChat = document.getElementById('activeChat');
        this.messagesContainer = document.getElementById('messagesContainer');
        this.messageText = document.getElementById('messageText');
        this.sendButton = document.getElementById('sendButton');
        this.backButton = document.getElementById('backButton');
        this.chatUserName = document.getElementById('chatUserName');
        this.chatUserAvatar = document.getElementById('chatUserAvatar');
        this.startChatBtn = document.getElementById('startChatBtn');
        this.chatApp = document.querySelector('.chat-app');
        this.chatSidebar = document.querySelector('.chat-sidebar');
        this.chatArea = document.querySelector('.chat-area');
    }

    bindEvents() {
        // Event listeners principales
        this.newChatBtn?.addEventListener('click', () => this.showSearch());
        this.startChatBtn?.addEventListener('click', () => this.showSearch());
        this.cancelSearch?.addEventListener('click', () => this.hideSearch());
        this.backButton?.addEventListener('click', () => this.closeChat());
        this.sendButton?.addEventListener('click', () => this.sendMessage());

        // Auto-resize textarea
        this.messageText?.addEventListener('input', (e) => {
            e.target.style.height = 'auto';
            e.target.style.height = Math.min(e.target.scrollHeight, 120) + 'px';
        });

        // Send message on Enter (not Shift+Enter)
        this.messageText?.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });

        // Event delegation para conversaciones y resultados de búsqueda
        document.addEventListener('click', (e) => {
            // Click en conversación existente
            if (e.target.closest('.conversation-item')) {
                const item = e.target.closest('.conversation-item');
                const conversationId = item.dataset.conversationId;
                const otherUserId = item.dataset.otherUserId;
                const otherUserName = item.dataset.otherUserName;
                this.openConversation(conversationId, otherUserId, otherUserName);
            }

            // Click en resultado de búsqueda
            if (e.target.closest('.user-result')) {
                const item = e.target.closest('.user-result');
                const userId = item.dataset.userid;
                const userName = item.dataset.username;
                this.startNewConversation(userId, userName);
            }
        });

        // Resize handler
        window.addEventListener('resize', () => this.handleResize());
        
        // Cleanup al salir
        window.addEventListener('beforeunload', () => {
            if (this.messageInterval) {
                clearInterval(this.messageInterval);
            }
        });
    }

    handleResize() {
        const wasMobile = this.isMobile;
        this.isMobile = window.innerWidth <= 768;
        
        // Si cambió de desktop a mobile o viceversa
        if (wasMobile !== this.isMobile) {
            if (this.isMobile && this.currentConversationId) {
                // En móvil, si hay chat activo, ocultar sidebar
                this.showMobileChat();
            } else if (!this.isMobile) {
                // En desktop, mostrar ambos
                this.showDesktopLayout();
            }
        }
    }

    showMobileChat() {
        if (this.isMobile && this.currentConversationId) {
            this.chatApp.classList.add('chat-active');
            this.chatSidebar.style.display = 'none';
            this.chatArea.style.display = 'flex';
            this.chatArea.style.height = 'calc(100vh - 70px)';
        }
    }

    showMobileSidebar() {
        if (this.isMobile) {
            this.chatApp.classList.remove('chat-active');
            this.chatSidebar.style.display = 'flex';
            this.chatArea.style.display = 'flex';
            this.activeChat.style.display = 'none';
            this.chatPlaceholder.style.display = 'flex';
        }
    }

    showDesktopLayout() {
        if (!this.isMobile) {
            this.chatApp.classList.remove('chat-active');
            this.chatSidebar.style.display = 'flex';
            this.chatArea.style.display = 'flex';
            this.chatSidebar.style.height = 'auto';
            this.chatArea.style.height = 'auto';
        }
    }

    showSearch() {
        this.searchSection.style.display = 'block';
        this.conversationsList.style.display = 'none';
        document.getElementById('searchInput')?.focus();
    }

    hideSearch() {
        this.searchSection.style.display = 'none';
        this.conversationsList.style.display = 'block';
        // Limpiar formulario de búsqueda
        document.getElementById('searchForm')?.reset();
        // Recargar la página para limpiar resultados
        if (window.location.search) {
            window.location.href = window.location.pathname;
        }
    }

    openConversation(conversationId, otherUserId, otherUserName) {
        this.currentConversationId = conversationId;
        this.currentOtherUserId = otherUserId;
        this.currentOtherUserName = otherUserName;

        // Actualizar UI
        this.chatUserName.textContent = otherUserName;
        this.chatUserAvatar.textContent = otherUserName.charAt(0).toUpperCase();
        
        // Mostrar chat
        this.chatPlaceholder.style.display = 'none';
        this.activeChat.style.display = 'flex';
        
        // En móvil, ocultar sidebar cuando se abre chat
        if (this.isMobile) {
            this.showMobileChat();
        }
        
        // Marcar conversación como activa
        document.querySelectorAll('.conversation-item').forEach(item => {
            item.classList.remove('active');
        });
        document.querySelector(`[data-conversation-id="${conversationId}"]`)?.classList.add('active');

        // Cargar mensajes
        this.loadMessages();
        
        // Configurar polling cada 3 segundos
        if (this.messageInterval) clearInterval(this.messageInterval);
        this.messageInterval = setInterval(() => this.loadMessages(), 3000);
    }

    startNewConversation(userId, userName) {
        // Verificar que no sea el mismo usuario
        if (userId == this.currentUserId) {
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
                this.hideSearch();
                this.openConversation(data.conversationId, userId, userName);
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

    closeChat() {
        this.activeChat.style.display = 'none';
        this.chatPlaceholder.style.display = 'flex';
        
        // En móvil, mostrar sidebar
        if (this.isMobile) {
            this.showMobileSidebar();
        }
        
        // Limpiar polling
        if (this.messageInterval) {
            clearInterval(this.messageInterval);
            this.messageInterval = null;
        }
        
        // Limpiar selección activa
        document.querySelectorAll('.conversation-item').forEach(item => {
            item.classList.remove('active');
        });
        
        this.currentConversationId = null;
        this.currentOtherUserId = null;
        this.currentOtherUserName = '';
    }

    loadMessages() {
        if (!this.currentConversationId || this.loadingMessages) return;
        
        this.loadingMessages = true;
        
        fetch('../../api/get_messages.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'conversacion_id=' + this.currentConversationId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.displayMessages(data.messages);
            }
        })
        .catch(error => console.error('Error loading messages:', error))
        .finally(() => {
            this.loadingMessages = false;
        });
    }

    displayMessages(messages) {
        // Guardar posición de scroll para evitar parpadeo
        const wasAtBottom = this.messagesContainer.scrollHeight - this.messagesContainer.scrollTop <= this.messagesContainer.clientHeight + 50;
        
        // Obtener mensajes existentes para comparar
        const existingMessages = Array.from(this.messagesContainer.querySelectorAll('.message')).map(msg => 
            msg.querySelector('.message-content').textContent.trim()
        );
        
        if (messages.length === 0) {
            if (this.messagesContainer.innerHTML.indexOf('no-messages') === -1) {
                this.messagesContainer.innerHTML = '<div class="no-messages">No hay mensajes aún. ¡Envía el primero!</div>';
            }
            return;
        }
        
        // Solo actualizar si hay cambios
        const newMessageContents = messages.map(msg => msg.contenido.trim());
        const hasChanges = JSON.stringify(existingMessages) !== JSON.stringify(newMessageContents);
        
        if (!hasChanges) return;
        
        // Limpiar solo si hay cambios reales
        this.messagesContainer.innerHTML = '';
        
        messages.forEach((message, index) => {
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${message.idRemitente == this.currentUserId ? 'sent' : 'received'}`;
            messageDiv.setAttribute('data-message-id', message.idMensaje);
            
            messageDiv.innerHTML = `
                <div class="message-content">
                    ${this.escapeHtml(message.contenido)}
                </div>
                <div class="message-time">
                    ${this.formatTime(message.fechaEnvio)}
                </div>
            `;
            
            this.messagesContainer.appendChild(messageDiv);
        });
        
        // Solo hacer scroll si estaba al final o es un mensaje nuevo
        if (wasAtBottom || newMessageContents.length > existingMessages.length) {
            setTimeout(() => {
                this.messagesContainer.scrollTop = this.messagesContainer.scrollHeight;
            }, 50);
        }
    }

    sendMessage() {
        if (!this.currentConversationId) return;
        
        const content = this.messageText.value.trim();
        if (!content) return;
        
        // Deshabilitar botón y cambiar texto
        this.sendButton.disabled = true;
        const originalSendButton = this.sendButton.innerHTML;
        this.sendButton.innerHTML = '<div style="width: 20px; height: 20px; border: 2px solid #fff; border-top: 2px solid transparent; border-radius: 50%; animation: spin 1s linear infinite;"></div>';
        
        // Agregar mensaje temporalmente para feedback inmediato
        const tempMessage = document.createElement('div');
        tempMessage.className = 'message sent';
        tempMessage.style.opacity = '0.7';
        tempMessage.innerHTML = `
            <div class="message-content">
                ${this.escapeHtml(content)}
            </div>
            <div class="message-time">Enviando...</div>
        `;
        this.messagesContainer.appendChild(tempMessage);
        this.messagesContainer.scrollTop = this.messagesContainer.scrollHeight;
        
        fetch('../../api/send_message.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `conversacion_id=${this.currentConversationId}&remitente_id=${this.currentUserId}&contenido=${encodeURIComponent(content)}`
        })
        .then(response => response.json())
        .then(data => {
            // Remover mensaje temporal
            tempMessage.remove();
            
            if (data.success) {
                this.messageText.value = '';
                this.messageText.style.height = 'auto';
                // Cargar mensajes inmediatamente
                this.loadMessages();
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
            this.sendButton.disabled = false;
            this.sendButton.innerHTML = originalSendButton;
        });
    }

    formatTime(datetime) {
        const date = new Date(datetime);
        return date.toLocaleTimeString('es-ES', { 
            hour: '2-digit', 
            minute: '2-digit' 
        });
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Inicializar la aplicación cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    // Obtener el currentUserId desde PHP (debe estar disponible globalmente)
    if (typeof currentUserId !== 'undefined') {
        window.chatApp = new ChatApp(currentUserId);
    } else {
        console.error('currentUserId no está definido');
    }
});