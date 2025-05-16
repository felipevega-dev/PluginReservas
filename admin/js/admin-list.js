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
    
    // Manejo del menú desplegable de exportación
    $(".export-button").on("click", function(e) {
        e.preventDefault();
        $(".export-dropdown-content").toggle();
    });
    
    // Cerrar el menú de exportación cuando se hace clic fuera de él
    $(document).on("click", function(e) {
        if (!$(e.target).closest(".export-dropdown").length) {
            $(".export-dropdown-content").hide();
        }
    });
    
    // Aplicar los filtros actuales a los enlaces de exportación
    function updateExportLinks() {
        // Obtener todos los parámetros de filtro de la URL actual
        var currentUrl = new URL(window.location.href);
        var params = new URLSearchParams(currentUrl.search);
        
        // Quitar parámetros relacionados con la exportación
        params.delete('export');
        params.delete('export_type');
        
        // Convertir los parámetros a una cadena de consulta
        var queryString = params.toString();
        
        // Actualizar cada enlace de exportación con los filtros actuales
        $(".export-dropdown-content a").each(function() {
            var baseUrl = $(this).attr('href').split('?')[0];
            var exportParams = new URLSearchParams($(this).attr('href').split('?')[1]);
            
            // Preservar parámetros de exportación
            var exportType = exportParams.get('export_type');
            
            // Crear nuevo enlace con filtros actuales
            var newHref = baseUrl + '?' + queryString;
            if (queryString) {
                newHref += '&';
            }
            newHref += 'export=1&export_type=' + exportType;
            
            $(this).attr('href', newHref);
        });
    }
    
    // Actualizar enlaces al cargar la página
    updateExportLinks();
}); 