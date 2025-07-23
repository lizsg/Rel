document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.card-button').forEach(button => {
    button.addEventListener('click', function() {
      const action = this.textContent.trim();
      const cardTitle = this.closest('.card').querySelector('.card-title').textContent;
      
      console.log(`${action} ${cardTitle}`);
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
});