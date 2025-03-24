/**
 * JavaScript para la página de reserva exitosa
 */
document.addEventListener('DOMContentLoaded', function() {
    // Verificar si estamos en la página de reserva exitosa
    if (document.querySelector('.reserva-exitosa-container')) {
        initPrintButton();
        addAnimations();
    }
});

/**
 * Inicializa el botón de impresión y su comportamiento
 */
function initPrintButton() {
    const printButton = document.querySelector('.btn-imprimir');
    
    if (printButton) {
        printButton.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Añadir clase para estilos específicos de impresión
            document.body.classList.add('printing');
            
            // Esperar un momento para que se apliquen los estilos
            setTimeout(function() {
                window.print();
                
                // Remover la clase después de imprimir
                setTimeout(function() {
                    document.body.classList.remove('printing');
                }, 500);
            }, 300);
        });
    }
}

/**
 * Añade animaciones a los elementos de la página
 */
function addAnimations() {
    // Animación para los detalles
    const detallesSecciones = document.querySelectorAll('.detalles-seccion');
    
    if (detallesSecciones.length) {
        detallesSecciones.forEach(function(seccion, index) {
            seccion.style.animationDelay = (0.3 + (index * 0.1)) + 's';
            seccion.classList.add('fade-in');
        });
    }
    
    // Animación para la tabla de productos
    const productosTable = document.querySelector('.productos-table');
    if (productosTable) {
        productosTable.classList.add('slide-in');
    }
} 