// Función para inicializar los tooltips de Bootstrap
function initTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

// Función para inicializar los popovers de Bootstrap
function initPopovers() {
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
}

// Función para manejar la validación de formularios
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return;

    form.addEventListener('submit', function(event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        form.classList.add('was-validated');
    });
}

// Función para manejar la carga de imágenes
function handleImageUpload(inputId, previewId) {
    const input = document.getElementById(inputId);
    const preview = document.getElementById(previewId);
    if (!input || !preview) return;

    input.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            }
            reader.readAsDataURL(file);
        }
    });
}

// Función para manejar la paginación dinámica
function handlePagination() {
    const paginationLinks = document.querySelectorAll('.pagination .page-link');
    paginationLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            if (!this.getAttribute('href')) {
                e.preventDefault();
            }
        });
    });
}

// Función para manejar la búsqueda en tiempo real
function handleLiveSearch(inputId, resultsId) {
    const searchInput = document.getElementById(inputId);
    const resultsContainer = document.getElementById(resultsId);
    if (!searchInput || !resultsContainer) return;

    let timeoutId;
    searchInput.addEventListener('input', function() {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => {
            const searchTerm = this.value.trim();
            if (searchTerm.length >= 2) {
                fetch(`/search?q=${encodeURIComponent(searchTerm)}`)
                    .then(response => response.json())
                    .then(data => {
                        resultsContainer.innerHTML = '';
                        data.forEach(item => {
                            const div = document.createElement('div');
                            div.className = 'search-result-item';
                            div.innerHTML = `
                                <a href="${item.url}" class="d-block p-2">
                                    <strong>${item.title}</strong>
                                    <small class="text-muted d-block">${item.description}</small>
                                </a>
                            `;
                            resultsContainer.appendChild(div);
                        });
                        resultsContainer.style.display = 'block';
                    })
                    .catch(error => console.error('Error:', error));
            } else {
                resultsContainer.style.display = 'none';
            }
        }, 300);
    });

    // Cerrar resultados al hacer clic fuera
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !resultsContainer.contains(e.target)) {
            resultsContainer.style.display = 'none';
        }
    });
}

// Función para manejar el filtrado dinámico
function handleDynamicFilter(filterId, targetClass) {
    const filter = document.getElementById(filterId);
    if (!filter) return;

    filter.addEventListener('change', function() {
        const selectedValue = this.value;
        const items = document.querySelectorAll(targetClass);

        items.forEach(item => {
            if (selectedValue === '' || item.dataset.category === selectedValue) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
    });
}

// Función para manejar la carga perezosa de imágenes
function handleLazyLoading() {
    const lazyImages = document.querySelectorAll('img[data-src]');
    
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.removeAttribute('data-src');
                observer.unobserve(img);
            }
        });
    });

    lazyImages.forEach(img => imageObserver.observe(img));
}

// Función para manejar el modo oscuro
function handleDarkMode() {
    const darkModeToggle = document.getElementById('darkModeToggle');
    if (!darkModeToggle) return;

    const isDarkMode = localStorage.getItem('darkMode') === 'true';
    document.body.classList.toggle('dark-mode', isDarkMode);
    darkModeToggle.checked = isDarkMode;

    darkModeToggle.addEventListener('change', function() {
        document.body.classList.toggle('dark-mode');
        localStorage.setItem('darkMode', this.checked);
    });
}

// Función para manejar notificaciones
function handleNotifications() {
    const notificationBell = document.querySelector('.notification-bell');
    if (!notificationBell) return;

    // Verificar notificaciones cada minuto
    setInterval(() => {
        fetch('/api/notifications/unread')
            .then(response => response.json())
            .then(data => {
                const count = data.count;
                const badge = notificationBell.querySelector('.badge');
                if (count > 0) {
                    badge.textContent = count;
                    badge.style.display = 'block';
                } else {
                    badge.style.display = 'none';
                }
            })
            .catch(error => console.error('Error:', error));
    }, 60000);
}

// Inicializar todas las funcionalidades cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    initTooltips();
    initPopovers();
    handlePagination();
    handleLazyLoading();
    handleDarkMode();
    handleNotifications();

    // Inicializar validación de formularios
    validateForm('registrationForm');
    validateForm('loginForm');
    validateForm('eventForm');

    // Inicializar carga de imágenes
    handleImageUpload('profileImage', 'imagePreview');
    handleImageUpload('eventImage', 'eventImagePreview');

    // Inicializar búsqueda en tiempo real
    handleLiveSearch('searchInput', 'searchResults');

    // Inicializar filtrado dinámico
    handleDynamicFilter('categoryFilter', '.event-card');
}); 