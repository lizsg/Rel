<?php

?>

<div id="chatDropdown" class="chat-dropdown">
  <div class="chat-header">
    <div class="chat-title">
      <svg width="18" height="18" fill="white" viewBox="0 0 24 24">
        <path d="M12 2C6.48 2 2 6.48 2 12c0 1.54.36 2.98.97 4.29L1 23l6.71-1.97C9.02 21.64 10.46 22 12 22c5.52 0 10-4.48 10-10S17.52 2 12 2z"/>
      </svg>
      Asistente ReL
    </div>
    <button class="chat-close" onclick="toggleChat()">
      <svg width="16" height="16" fill="white" viewBox="0 0 24 24">
        <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
      </svg>
    </button>
  </div>

  <div id="chatMessages" class="chat-messages">
    <div class="chat-welcome">
      ¡Hola! Soy tu asistente virtual de ReL. ¿En qué puedo ayudarte hoy?
    </div>
  </div>

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

<style>
.topbar-icon.chat-active {
  background: linear-gradient(135deg, #588157 0%, #3a5a40 100%);
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(88, 129, 87, 0.4);
}
</style>