document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.card').forEach(card => {
    card.addEventListener('click', function() {
      console.log('Card clicked:', this.querySelector('.card-title').textContent);
    });
  });

  const searchForm = document.querySelector('.search-bar');
  if (searchForm) {
    searchForm.addEventListener('submit', function(e) {
      e.preventDefault();
      const searchTerm = this.querySelector('input').value.trim();
      console.log('Buscando:', searchTerm);
    });
  }

  const advancedSearchBtn = document.querySelector('.user-button');
  if (advancedSearchBtn) {
    advancedSearchBtn.addEventListener('click', function() {
      console.log('Búsqueda avanzada clickeada');
    });
  }
});