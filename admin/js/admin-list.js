/**
 * JavaScript para la vista de lista de reservas
 */
jQuery(document).ready(function($) {
    // Confirmación de eliminación (una sola vez)
    $(".delete-button").on("click", function(e) {
        if (!confirm("¿Estás seguro de que deseas eliminar esta reserva? Esta acción no se puede deshacer.")) {
            e.preventDefault();
        }
    });
    
    // Manejo del panel de filtros
    $(".filtros-toggle-btn").on("click", function() {
        $(".filtros-panel").slideToggle(300);
        $(this).toggleClass("active");
        
        if ($(this).hasClass("active")) {
            $(this).html('<span class="dashicons dashicons-dismiss"></span> Ocultar filtros');
        } else {
            $(this).html('<span class="dashicons dashicons-filter"></span> Filtros avanzados');
        }
    });
    
    // Si hay filtros activos, mostrar el panel automáticamente
    if ($(".filtros-activos").length) {
        $(".filtros-panel").show();
        $(".filtros-toggle-btn").addClass("active").html('<span class="dashicons dashicons-dismiss"></span> Ocultar filtros');
    }
    
    // Mejora en la interfaz de filtros
    $(".filtro-group input[type='date']").on("change", function() {
        $(this).css("border-color", $(this).val() ? "#0073aa" : "#ddd");
    });
    
    $(".filtro-group select, .filtro-group input[type='number']").on("change", function() {
        $(this).css("border-color", $(this).val() ? "#0073aa" : "#ddd");
    });
    
    // Destacar filtros que ya tienen valores
    $(".filtro-group input[type='date'], .filtro-group select, .filtro-group input[type='number']").each(function() {
        if ($(this).val()) {
            $(this).css("border-color", "#0073aa");
        }
    });
}); 