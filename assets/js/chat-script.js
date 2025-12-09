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

  if (lowerMessage.includes('hola') || lowerMessage.includes('holi') || lowerMessage.includes('buenos') || lowerMessage.includes('saludos') || lowerMessage.includes('ola')) {
    return '¡Hola! ¿En qué puedo ayudarte con ReL hoy? Puedo ayudarte a conectar con otros usuarios, compartir tus momentos o resolver dudas sobre la plataforma.';
  }

  if (lowerMessage.includes('editorial') || lowerMessage.includes('edicion') || lowerMessage.includes('isbn')) {
    return 'Aunque ReL es ahora una red social, ¡seguimos amando los libros! Si estás compartiendo una lectura, no olvides mencionar la editorial o edición en tu publicación para que otros lectores sepan de qué hablas.';
  }

  if (lowerMessage.includes('descripcion') || lowerMessage.includes('descripción')) {
    return 'Para tus publicaciones en ReL, te recomendamos ser auténtico. Describe lo que sientes, lo que estás haciendo o comparte una cita que te guste.';
  }

  if (lowerMessage.includes('comprar') || lowerMessage.includes('vender') || lowerMessage.includes('precio')) {
    return 'ReL es una red social para conectar personas. Si ves algo que te interesa en una publicación de otro usuario, te sugerimos contactarlo directamente por el chat privado para acordar cualquier detalle.';
  }

  if (lowerMessage.includes('subir') || lowerMessage.includes('publicar') || lowerMessage.includes('agregar') || lowerMessage.includes('postear')) {
    return '¡Es muy fácil! Usa el recuadro "¿Qué estás pensando?" en el inicio para compartir estados, o el botón de nueva publicación para subir fotos y momentos especiales con tus seguidores.';
  }

  if (lowerMessage.includes('ayuda') || lowerMessage.includes('como') || lowerMessage.includes('funciona')) {
    return 'ReL es tu nueva red social. Aquí puedes seguir a tus amigos, compartir fotos y estados, dar like a lo que te gusta y chatear en tiempo real. ¡Explora y diviértete!';
  }
  
  if (lowerMessage.includes('perfil') || lowerMessage.includes('cuenta') || lowerMessage.includes('usuario')) {
    return 'Tu perfil es tu carta de presentación en ReL. Desde el menú superior puedes acceder a "Mi Perfil" para cambiar tu foto, ver tus seguidores y gestionar tus publicaciones.';
  }
  
  if (lowerMessage.includes('gracias') || lowerMessage.includes('thank')) {
    return '¡De nada! Me encanta ayudarte. Disfruta de tu tiempo en ReL.';
  }
    
  if (lowerMessage.includes('buscar') || lowerMessage.includes('encontrar') || lowerMessage.includes('amigos')) {
    return 'Usa la barra de búsqueda en la parte superior para encontrar a tus amigos y nuevos usuarios interesantes para seguir en ReL.';
  }
  
  if (lowerMessage.includes('adios') || lowerMessage.includes('bye') || lowerMessage.includes('hasta')) {
    return '¡Hasta pronto! Sigue compartiendo y conectando en ReL. 👋';
  }
  
  return 'Interesante. Como asistente de ReL, estoy aprendiendo cada día. Puedo ayudarte con temas sobre tu perfil, cómo publicar o cómo encontrar amigos. ¿Podrías reformular tu pregunta?';
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