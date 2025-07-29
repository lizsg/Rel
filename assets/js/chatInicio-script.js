document.addEventListener('DOMContentLoaded', function() {
  // Manejar clic en botones de enviar mensaje
  document.addEventListener('click', function(e) {
    if (e.target.classList.contains('enviar-mensaje')) {
      const userId = e.target.getAttribute('data-userid');
      const userName = e.target.getAttribute('data-username');
      abrirChat(userId, userName);
    }
  });

  function abrirChat(userId, userName) {
    // Redireccionar a la página de chat con el usuario seleccionado
    window.location.href = `chat.php?user_id=${userId}`;
    
    // Alternativa si quieres abrir en una nueva pestaña:
    // window.open(`chat.php?user_id=${userId}`, '_blank');
  }
  
});