let chatIsOpen = false;
let isTyping = false;

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

function sendMessage() {
  const input = document.getElementById('chatInput');
  const message = input.value.trim();
  
  if (!message || isTyping) return;
  
  addMessage(message, 'user');

  input.value = '';
  resizeTextarea();
  
  showTypingIndicator();
  
  setTimeout(() => {
    hideTypingIndicator();
    
    const responses = getBotResponse(message);
    addMessage(responses, 'bot');
  }, Math.random() * 2000 + 1000);
}

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

  if (lowerMessage.includes('hola') || lowerMessage.includes('holi') || lowerMessage.includes('buenos') || lowerMessage.includes('saludos')) {
    return '¡Hola! ¿En qué puedo ayudarte con RELEE hoy? Puedo ayudarte a encontrar libros, explicar cómo funciona la plataforma o resolver dudas.';
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
    
  if (lowerMessage.includes('libro') || lowerMessage.includes('buscar') || lowerMessage.includes('encontrar')) {
    return 'Puedes buscar libros usando la barra de búsqueda en la parte superior. También puedes usar filtros avanzados para encontrar exactamente lo que buscas por género, autor o año.';
  }
  
  if (lowerMessage.includes('adios') || lowerMessage.includes('bye') || lowerMessage.includes('hasta')) {
    return '¡Hasta luego! Que tengas una excelente experiencia leyendo en RELEE. 📚';
  }
  
  return 'Interesante pregunta. Como asistente de RELEE, puedo ayudarte con búsquedas de libros, navegación de la plataforma, subida de publicaciones y más. ¿Podrías ser más específico sobre lo que necesitas?';
}

function handleChatKeyPress(event) {
  if (event.key === 'Enter' && !event.shiftKey) {
    event.preventDefault();
    sendMessage();
  }
}

function resizeTextarea() {
  const textarea = document.getElementById('chatInput');
  textarea.style.height = 'auto';
  textarea.style.height = Math.min(textarea.scrollHeight, 80) + 'px';
}

document.addEventListener('DOMContentLoaded', function() {
  const chatInput = document.getElementById('chatInput');
  if (chatInput) {
    chatInput.addEventListener('input', resizeTextarea);
  }
});

document.addEventListener('click', function(event) {
  const chatDropdown = document.getElementById('chatDropdown');
  const chatButton = document.querySelector('.topbar-icon[title="Chat"]');
  
  if (chatIsOpen && 
      !chatDropdown.contains(event.target) && 
      !chatButton.contains(event.target)) {
    toggleChat();
  }
});

function initChatButton() {
  const chatButton = document.querySelector('.topbar-icon[title="Chat"]');
  if (chatButton) {
    chatButton.addEventListener('click', function(event) {
      event.stopPropagation();
      toggleChat();
    });
  }
}

document.addEventListener('DOMContentLoaded', initChatButton);