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
        this.newChatBtn?.addEventListener('click', () => this.showSearch());
        this.startChatBtn?.addEventListener('click', () => this.showSearch());
        this.cancelSearch?.addEventListener('click', () => this.hideSearch());
        this.backButton?.addEventListener('click', () => this.closeChat());
        this.sendButton?.addEventListener('click', () => this.sendMessage());

        this.messageText?.addEventListener('input', (e) => {
            e.target.style.height = 'auto';
            e.target.style.height = Math.min(e.target.scrollHeight, 120) + 'px';
        });

        this.messageText?.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });

        document.addEventListener('click', (e) => {
            if (e.target.closest('.conversation-item')) {
                const item = e.target.closest('.conversation-item');
                const conversationId = item.dataset.conversationId;
                const otherUserId = item.dataset.otherUserId;
                const otherUserName = item.dataset.otherUserName;
                this.openConversation(conversationId, otherUserId, otherUserName);
            }

            if (e.target.closest('.user-result')) {
                const item = e.target.closest('.user-result');
                const userId = item.dataset.userid;
                const userName = item.dataset.username;
                this.startNewConversation(userId, userName);
            }
        });

        window.addEventListener('resize', () => this.handleResize());
        
        window.addEventListener('beforeunload', () => {
            if (this.messageInterval) {
                clearInterval(this.messageInterval);
            }
        });
    }

    handleResize() {
        const wasMobile = this.isMobile;
        this.isMobile = window.innerWidth <= 768;
        
        if (wasMobile !== this.isMobile) {
            if (this.isMobile && this.currentConversationId) {
                this.showMobileChat();
            } else if (!this.isMobile) {
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
            this.activeChat.style.display = 'flex';
            this.chatPlaceholder.style.display = 'none';
            
            const messageInputContainer = document.querySelector('.message-input-container');
            if (messageInputContainer) {
                messageInputContainer.style.display = 'flex';
                messageInputContainer.style.position = 'relative';
                messageInputContainer.style.bottom = '0';
                messageInputContainer.style.width = '100%';
            }
            
            setTimeout(() => {
                if (this.messagesContainer) {
                    this.messagesContainer.scrollTop = this.messagesContainer.scrollHeight;
                }
            }, 100);
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
        document.getElementById('searchForm')?.reset();
        if (window.location.search) {
            window.location.href = window.location.pathname;
        }
    }

    openConversation(conversationId, otherUserId, otherUserName) {
        this.currentConversationId = conversationId;
        this.currentOtherUserId = otherUserId;
        this.currentOtherUserName = otherUserName;

        this.chatUserName.textContent = otherUserName;
        this.chatUserAvatar.textContent = otherUserName.charAt(0).toUpperCase();
        
        this.chatPlaceholder.style.display = 'none';
        this.activeChat.style.display = 'flex';
        
        if (this.isMobile) {
            this.showMobileChat();
            
            setTimeout(() => {
                const messagesContainer = this.messagesContainer;
                const chatHeader = document.querySelector('.chat-header');
                const messageInput = document.querySelector('.message-input-container');
                
                if (messagesContainer && chatHeader && messageInput) {
                    const headerHeight = chatHeader.offsetHeight;
                    const inputHeight = messageInput.offsetHeight;
                    const availableHeight = window.innerHeight - 70 - headerHeight - inputHeight; // 70px del bottombar
                    
                    messagesContainer.style.height = availableHeight + 'px';
                    messagesContainer.style.maxHeight = availableHeight + 'px';
                }
            }, 50);
        }
        
        document.querySelectorAll('.conversation-item').forEach(item => {
            item.classList.remove('active');
        });
        document.querySelector(`[data-conversation-id="${conversationId}"]`)?.classList.add('active');

        this.loadMessages();
        
        if (this.messageInterval) clearInterval(this.messageInterval);
        this.messageInterval = setInterval(() => this.loadMessages(), 3000);
    }

    startNewConversation(userId, userName) {
        if (userId == this.currentUserId) {
            alert('No puedes iniciar una conversación contigo mismo');
            return;
        }

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
        const wasAtBottom = this.messagesContainer.scrollHeight - this.messagesContainer.scrollTop <= this.messagesContainer.clientHeight + 50;
        
        const existingMessages = Array.from(this.messagesContainer.querySelectorAll('.message')).map(msg => 
            msg.querySelector('.message-content').textContent.trim()
        );
        
        if (messages.length === 0) {
            if (this.messagesContainer.innerHTML.indexOf('no-messages') === -1) {
                this.messagesContainer.innerHTML = '<div class="no-messages">No hay mensajes aún. ¡Envía el primero!</div>';
            }
            return;
        }
        
        const newMessageContents = messages.map(msg => msg.contenido.trim());
        const hasChanges = JSON.stringify(existingMessages) !== JSON.stringify(newMessageContents);
        
        if (!hasChanges) return;
        
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
        
        this.sendButton.disabled = true;
        const originalSendButton = this.sendButton.innerHTML;
        this.sendButton.innerHTML = '<div style="width: 20px; height: 20px; border: 2px solid #fff; border-top: 2px solid transparent; border-radius: 50%; animation: spin 1s linear infinite;"></div>';
        
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
            tempMessage.remove();
            
            if (data.success) {
                this.messageText.value = '';
                this.messageText.style.height = 'auto';
                this.loadMessages();
            } else {
                alert('Error al enviar mensaje: ' + data.message);
            }
        })
        .catch(error => {
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

    ensureInputVisible() {
    if (this.isMobile && this.currentConversationId) {
        const messageInputContainer = document.querySelector('.message-input-container');
        if (messageInputContainer) {
            messageInputContainer.scrollIntoView({ 
                behavior: 'smooth', 
                block: 'end' 
            });
        }
    }
}
}

document.addEventListener('DOMContentLoaded', function() {
    if (typeof currentUserId !== 'undefined') {
        window.chatApp = new ChatApp(currentUserId);
    } else {
        console.error('currentUserId no está definido');
    }


});
