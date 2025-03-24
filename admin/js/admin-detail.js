/**
 * JavaScript para la vista de detalle de reserva
 */
jQuery(document).ready(function($) {
    // Confirmación para el botón de eliminar en la página de detalle
    $('.reserva-delete-button').on('click', function(e) {
        e.preventDefault();
        var deleteUrl = $(this).attr('href');
        
        if (confirm('¿Estás seguro de que deseas eliminar esta reserva? Esta acción no se puede deshacer.')) {
            window.location.href = deleteUrl;
        }
    });
}); 