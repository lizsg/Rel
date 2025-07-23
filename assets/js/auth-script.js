function togglePassword() {
  const pass = document.getElementById("pass");
  pass.type = pass.type === "password" ? "text" : "password";
}

document.addEventListener('DOMContentLoaded', function() {
  const loginForm = document.querySelector('.login-container');
  
  if (loginForm) {
    loginForm.addEventListener('submit', function(e) {
      const usuario = this.querySelector('input[name="usuario"]').value.trim();
      const contrasena = this.querySelector('input[name="contrasena"]').value.trim();
      
      if (!usuario || !contrasena) {
        e.preventDefault();
        alert('Por favor complete todos los campos');
      }
      
    });
  }

  // Efecto hover para botones
  const buttons = document.querySelectorAll('.btn-login');
  buttons.forEach(button => {
    button.addEventListener('mouseenter', function() {
      this.style.transform = 'translateY(-1px)';
    });
    
    button.addEventListener('mouseleave', function() {
      this.style.transform = 'translateY(0)';
    });
  });
});