/**
 * JavaScript para la administración de productos WooCommerce
 * Version: 1.0.2
 */
(function($) {
    // Ejecutar cuando el DOM esté completamente cargado
    $(document).ready(function() {
        console.log('=== Reserva WooCommerce Admin JavaScript ===');
        console.log('Verificando elementos en la página...');
        
        // Contar checkboxes para diagnóstico
        var allCheckboxes = $('input[type="checkbox"]').length;
        var reservaCheckboxes = $('table.products input[type="checkbox"]').not('#cb-select-all-1, #cb-select-all-2').length;
        var checkedCheckboxes = $('table.products input[type="checkbox"]:checked').not('#cb-select-all-1, #cb-select-all-2').length;
        
        console.log('Total checkboxes: ' + allCheckboxes);
        console.log('Checkboxes de productos: ' + reservaCheckboxes);
        console.log('Checkboxes marcados: ' + checkedCheckboxes);
        console.log('Formulario presente:', $('#reserva-products-form').length > 0);
        
        // Colorear filas de productos seleccionados
        function actualizarVisualizacion() {
            console.log('Actualizando visualización de productos seleccionados...');
            var totalMarcados = 0;
            
            // Revisar todos los checkboxes de productos (excluyendo los "seleccionar todos")
            $('table.products tbody input[type="checkbox"]').each(function() {
                var $fila = $(this).closest('tr');
                var estaMarcado = $(this).is(':checked');
                
                if (estaMarcado) {
                    totalMarcados++;
                    $fila.addClass('reservable-row');
                    console.log('Producto marcado: ' + $(this).attr('id'));
                } else {
                    $fila.removeClass('reservable-row');
                }
            });
            
            console.log('Total productos seleccionados: ' + totalMarcados);
            return totalMarcados;
        }
        
        // Ejecutar al cargar la página
        actualizarVisualizacion();
        
        // Manejar cambios en los checkboxes de productos
        $('table.products tbody input[type="checkbox"]').on('change', function() {
            console.log('Checkbox cambiado: ' + $(this).attr('id') + ' - Estado: ' + ($(this).is(':checked') ? 'Marcado' : 'Desmarcado'));
            actualizarVisualizacion();
        });
        
        // Manejar los checkboxes "seleccionar todos"
        $('#cb-select-all-1, #cb-select-all-2').on('change', function() {
            var estaMarcado = $(this).is(':checked');
            console.log('Seleccionar todos: ' + (estaMarcado ? 'Marcado' : 'Desmarcado'));
            
            // Marcar/desmarcar todos los checkboxes de productos
            $('table.products tbody input[type="checkbox"]').prop('checked', estaMarcado);
            
            // Sincronizar entre los dos checkboxes "seleccionar todos"
            $('#cb-select-all-1, #cb-select-all-2').prop('checked', estaMarcado);
            
            // Actualizar visualización
            var totalMarcados = actualizarVisualizacion();
            console.log('Total productos seleccionados después de "seleccionar todos": ' + totalMarcados);
        });
        
        // Acciones en lote (botones "Aplicar")
        $('#doaction, #doaction2').on('click', function(e) {
            e.preventDefault();
            var selectorId = ($(this).attr('id') === 'doaction') ? '#bulk-action-selector-top' : '#bulk-action-selector-bottom';
            var accion = $(selectorId).val();
            
            console.log('Acción en lote seleccionada: ' + accion);
            
            if (accion === '-1') {
                alert('Por favor, seleccione una acción.');
                return;
            }
            
            var productosSeleccionados = $('table.products tbody input[type="checkbox"]:checked').length;
            console.log('Productos seleccionados para acción en lote: ' + productosSeleccionados);
            
            if (productosSeleccionados === 0) {
                alert('Por favor, seleccione al menos un producto.');
                return;
            }
            
            // Ejecutar la acción seleccionada
            if (accion === 'enable') {
                $('table.products tbody input[type="checkbox"]:checked').prop('checked', true);
                actualizarVisualizacion();
                alert('Se han marcado ' + productosSeleccionados + ' productos para reserva. Haga clic en "Guardar cambios" para aplicar.');
            } else if (accion === 'disable') {
                $('table.products tbody input[type="checkbox"]:checked').prop('checked', false);
                actualizarVisualizacion();
                alert('Se han desmarcado ' + productosSeleccionados + ' productos para reserva. Haga clic en "Guardar cambios" para aplicar.');
            }
        });
        
        // Manipular el envío del formulario para debugging
        $('#reserva-products-form').on('submit', function(e) {
            console.log('Formulario enviado');
            console.log('Total productos seleccionados: ' + $('table.products tbody input[type="checkbox"]:checked').length);
            
            // Este es un hook para debugging, no detiene el envío
            $('table.products tbody input[type="checkbox"]:checked').each(function() {
                console.log('Producto a guardar: ' + $(this).attr('name') + ' = ' + $(this).val());
            });
        });
        
        console.log('JavaScript de administración WooCommerce inicializado correctamente');
    });
})(jQuery);
