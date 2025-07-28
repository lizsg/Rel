document.addEventListener('DOMContentLoaded', function() {
  let currentConversation = null;
  let socket = null;
  
  // Conectar al WebSocket
  function connectWebSocket() {
    socket = new WebSocket('ws://tuservidor:puerto/chat');
    
    socket.onopen = function(e) {
      console.log("Conexión WebSocket establecida");
      // Autenticar al usuario
      socket.send(JSON.stringify({
        type: 'auth',
        userId: currentUserId
      }));
    };
    
    socket.onmessage = function(event) {
      const message = JSON.parse(event.data);
      
      if(message.type === 'message') {
        addMessageToChat(message);
        // Marcar como leído si es el usuario actual
        if(message.senderId !== currentUserId) {
          markMessageAsRead(message.id);
        }
      }
    };
    
    socket.onclose = function(event) {
      console.log("Conexión cerrada, reconectando...");
      setTimeout(connectWebSocket, 5000);
    };
  }
  
  // Cargar mensajes de una conversación
  function loadConversation(conversationId) {
    currentConversation = conversationId;
    fetch(`/api/messages?conversation=${conversationId}`)
      .then(response => response.json())
      .then(messages => {
        const container = document.getElementById('messages-container');
        container.innerHTML = '';
        messages.forEach(message => {
          addMessageToChat(message);
        });
      });
  }
  
  // Añadir mensaje al chat
  function addMessageToChat(message) {
    const container = document.getElementById('messages-container');
    const messageElement = document.createElement('div');
    messageElement.className = `message ${message.senderId === currentUserId ? 'sent' : 'received'}`;
    messageElement.innerHTML = `
      <div class="message-content">${message.content}</div>
      <div class="message-time">${new Date(message.timestamp).toLocaleTimeString()}</div>
    `;
    container.appendChild(messageElement);
    container.scrollTop = container.scrollHeight;
  }
  
  // Enviar mensaje
  document.getElementById('send-button').addEventListener('click', function() {
    const input = document.getElementById('message-input');
    const content = input.value.trim();
    
    if(content && currentConversation) {
      const message = {
        type: 'message',
        conversationId: currentConversation,
        senderId: currentUserId,
        content: content,
        timestamp: new Date().toISOString()
      };
      
      // Enviar por WebSocket
      socket.send(JSON.stringify(message));
      
      // También enviar al servidor para guardar en BD
      fetch('/api/messages', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(message)
      });
      
      input.value = '';
    }
  });
  
  // Seleccionar conversación
  document.querySelectorAll('.conversation-item').forEach(item => {
    item.addEventListener('click', function() {
      const conversationId = this.dataset.conversation;
      loadConversation(conversationId);
    });
  });
  
  // Iniciar conexión WebSocket
  connectWebSocket();
});
