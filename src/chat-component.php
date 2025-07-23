<?php
// chat-component.php - Componente del chat desplegable
?>

<!-- Componente del Chat Desplegable -->
<div id="chatDropdown" class="chat-dropdown">
  <!-- Header del Chat -->
  <div class="chat-header">
    <div class="chat-title">
      <svg width="18" height="18" fill="white" viewBox="0 0 24 24">
        <path d="M12 2C6.48 2 2 6.48 2 12c0 1.54.36 2.98.97 4.29L1 23l6.71-1.97C9.02 21.64 10.46 22 12 22c5.52 0 10-4.48 10-10S17.52 2 12 2z"/>
      </svg>
      Asistente RELEE
    </div>
    <button class="chat-close" onclick="toggleChat()">
      <svg width="16" height="16" fill="white" viewBox="0 0 24 24">
        <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
      </svg>
    </button>
  </div>

  <!-- Mensajes del Chat -->
  <div id="chatMessages" class="chat-messages">
    <div class="chat-welcome">
      ¡Hola! Soy tu asistente virtual de RELEE. ¿En qué puedo ayudarte hoy?
    </div>
  </div>

  <!-- Input del Chat -->
  <div class="chat-input-container">
    <div class="chat-input-wrapper">
      <textarea 
        id="chatInput" 
        class="chat-input" 
        placeholder="Escribe tu mensaje aquí..."
        rows="1"
        onkeypress="handleChatKeyPress(event)"
      ></textarea>
      <button id="chatSend" class="chat-send" onclick="sendMessage()">
        <svg width="18" height="18" fill="white" viewBox="0 0 24 24">
          <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
        </svg>
      </button>
    </div>
  </div>
</div>

<script>
// Variables globales del chat
let chatIsOpen = false;
let isTyping = false;

// Función para abrir/cerrar el chat
function toggleChat() {
  const chatDropdown = document.getElementById('chatDropdown');
  chatIsOpen = !chatIsOpen;
  
  if (chatIsOpen) {
    chatDropdown.classList.add('active');
    document.getElementById('chatInput').focus();
  } else {
    chatDropdown.classList.remove('active');
  }
}

// Función para enviar mensaje
function sendMessage() {
  const input = document.getElementById('chatInput');
  const message = input.value.trim();
  
  if (!message || isTyping) return;
  
  // Agregar mensaje del usuario
  addMessage(message, 'user');
  
  // Limpiar input
  input.value = '';
  resizeTextarea();
  
  // Mostrar indicador de escritura
  showTypingIndicator();
  
  // Simular respuesta del bot (aquí puedes integrar con tu backend)
  setTimeout(() => {
    hideTypingIndicator();
    
    // Respuestas predefinidas del bot
    const responses = getBotResponse(message);
    addMessage(responses, 'bot');
  }, Math.random() * 2000 + 1000); // Respuesta aleatoria entre 1-3 segundos
}

// Función para agregar mensaje al chat
function addMessage(text, sender) {
  const messagesContainer = document.getElementById('chatMessages');
  const messageDiv = document.createElement('div');
  messageDiv.className = `message ${sender}`;
  
  const now = new Date();
  const timeString = now.getHours().toString().padStart(2, '0') + ':' + 
                    now.getMinutes().toString().padStart(2, '0');
  
  messageDiv.innerHTML = `
    <div>${text}</div>
    <div class="message-time">${timeString}</div>
  `;
  
  // Remover mensaje de bienvenida si es el primer mensaje
  const welcome = messagesContainer.querySelector('.chat-welcome');
  if (welcome && sender === 'user') {
    welcome.remove();
  }
  
  messagesContainer.appendChild(messageDiv);
  messagesContainer.scrollTop = messagesContainer.scrollHeight;
}

// Función para mostrar indicador de escritura
function showTypingIndicator() {
  if (isTyping) return;
  
  isTyping = true;
  const messagesContainer = document.getElementById('chatMessages');
  const typingDiv = document.createElement('div');
  typingDiv.className = 'typing-indicator';
  typingDiv.id = 'typingIndicator';
  
  typingDiv.innerHTML = `
    <div class="typing-dots">
      <div class="typing-dot"></div>
      <div class="typing-dot"></div>
      <div class="typing-dot"></div>
    </div>
  `;
  
  messagesContainer.appendChild(typingDiv);
  messagesContainer.scrollTop = messagesContainer.scrollHeight;
}

// Función para ocultar indicador de escritura
function hideTypingIndicator() {
  const typing = document.getElementById('typingIndicator');
  if (typing) {
    typing.remove();
  }
  isTyping = false;
}

// Función para obtener respuesta del bot
function getBotResponse(message) {
  const lowerMessage = message.toLowerCase();
  
  // Respuestas específicas para RELEE
  if (lowerMessage.includes('hola') || lowerMessage.includes('buenos') || lowerMessage.includes('saludos')) {
    return '¡Hola! ¿En qué puedo ayudarte con RELEE hoy? Puedo ayudarte a encontrar libros, explicar cómo funciona la plataforma o resolver dudas.';
  }
  
  if (lowerMessage.includes('libro') || lowerMessage.includes('buscar') || lowerMessage.includes('encontrar')) {
    return 'Puedes buscar libros usando la barra de búsqueda en la parte superior. También puedes usar filtros avanzados para encontrar exactamente lo que buscas por género, autor o año.';
  }
  
  if (lowerMessage.includes('subir') || lowerMessage.includes('publicar') || lowerMessage.includes('agregar')) {
    return 'Para subir un libro, ve a "Mis Publicaciones" en la barra inferior y selecciona "Agregar nueva publicación". Asegúrate de tener los derechos necesarios antes de publicar.';
  }
  
  if (lowerMessage.includes('ayuda') || lowerMessage.includes('como') || lowerMessage.includes('funciona')) {
    return 'RELEE es una plataforma para compartir y descubrir libros. Puedes buscar libros, leer reseñas, subir tus propias publicaciones y conectar con otros lectores. ¿Hay algo específico que te gustaría saber?';
  }
  
  if (lowerMessage.includes('perfil') || lowerMessage.includes('cuenta') || lowerMessage.includes('usuario')) {
    return 'Puedes acceder a tu perfil desde el ícono de usuario en la barra superior. Allí podrás editar tu información, ver tu historial de lecturas y gestionar tus publicaciones.';
  }
  
  if (lowerMessage.includes('gracias') || lowerMessage.includes('thank')) {
    return '¡De nada! Estoy aquí para ayudarte. Si tienes más preguntas sobre RELEE, no dudes en preguntarme.';
  }
  
  if (lowerMessage.includes('adios') || lowerMessage.includes('bye') || lowerMessage.includes('hasta')) {
    return '¡Hasta luego! Que tengas una excelente experiencia leyendo en RELEE. 📚';
  }
  
  // Respuesta por defecto
  return 'Interesante pregunta. Como asistente de RELEE, puedo ayudarte con búsquedas de libros, navegación de la plataforma, subida de publicaciones y más. ¿Podrías ser más específico sobre lo que necesitas?';
}

// Función para manejar Enter en el textarea
function handleChatKeyPress(event) {
  if (event.key === 'Enter' && !event.shiftKey) {
    event.preventDefault();
    sendMessage();
  }
}

// Auto-resize del textarea
function resizeTextarea() {
  const textarea = document.getElementById('chatInput');
  textarea.style.height = 'auto';
  textarea.style.height = Math.min(textarea.scrollHeight, 80) + 'px';
}

// Event listener para auto-resize
document.addEventListener('DOMContentLoaded', function() {
  const chatInput = document.getElementById('chatInput');
  if (chatInput) {
    chatInput.addEventListener('input', resizeTextarea);
  }
});

// Cerrar chat al hacer clic fuera
document.addEventListener('click', function(event) {
  const chatDropdown = document.getElementById('chatDropdown');
  const chatButton = document.querySelector('.topbar-icon[title="Chat"]');
  
  if (chatIsOpen && 
      !chatDropdown.contains(event.target) && 
      !chatButton.contains(event.target)) {
    toggleChat();
  }
});

// Función para el botón de chat en el topbar
function initChatButton() {
  const chatButton = document.querySelector('.topbar-icon[title="Chat"]');
  if (chatButton) {
    chatButton.addEventListener('click', function(event) {
      event.stopPropagation();
      toggleChat();
    });
  }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', initChatButton);
</script>

<style>
/* Estilos específicos para el botón activo */
.topbar-icon.chat-active {
  background: linear-gradient(135deg, #588157 0%, #3a5a40 100%);
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(88, 129, 87, 0.4);
}
</style>