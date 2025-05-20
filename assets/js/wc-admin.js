/**
 * JavaScript para la administración de productos WooCommerce
 */
jQuery(document).ready(function($) {
    // Seleccionar/deseleccionar todos los checkboxes
    $('#cb-select-all-1, #cb-select-all-2').on('change', function() {
        var isChecked = $(this).prop('checked');
        $('input[name="product_ids[]"]').prop('checked', isChecked);
        
        // Sincronizar el otro checkbox "seleccionar todos"
        var id = $(this).attr('id');
        if (id === 'cb-select-all-1') {
            $('#cb-select-all-2').prop('checked', isChecked);
        } else {
            $('#cb-select-all-1').prop('checked', isChecked);
        }
    });
    
    // Acciones en lote
    $('#doaction, #doaction2').on('click', function() {
        var selectId = ($(this).attr('id') === 'doaction') ? 'bulk-action-selector-top' : 'bulk-action-selector-bottom';
        var action = $('#' + selectId).val();
        
        if (action === '-1') {
            alert('Por favor, seleccione una acción.');
            return false;
        }
        
        var checkedProducts = $('input[name="product_ids[]"]:checked');
        if (checkedProducts.length === 0) {
            alert('Por favor, seleccione al menos un producto.');
            return false;
        }
        
        // Aplicar la acción seleccionada
        if (action === 'enable') {
            checkedProducts.each(function() {
                var productId = $(this).val();
                $('input[name="reservable[' + productId + ']"]').prop('checked', true);
            });
        } else if (action === 'disable') {
            checkedProducts.each(function() {
                var productId = $(this).val();
                $('input[name="reservable[' + productId + ']"]').prop('checked', false);
            });
        }
        
        return false;
    });
    
    // Hacer que los mensajes de notificación sean descartables
    $('.notice-success').on('click', '.notice-dismiss', function() {
        $(this).parent().fadeOut(300, function() { $(this).remove(); });
    });
    
    // Añadir botón para descartar a las notificaciones si no lo tienen
    $('.notice-success').each(function() {
        if (!$(this).hasClass('is-dismissible')) {
            $(this).addClass('is-dismissible');
        }
    });
    
    // Confirmar antes de enviar el formulario
    $('#reserva-products-form').on('submit', function(e) {
        var changedProducts = 0;
        
        // Contar productos seleccionados
        $('input[name="product_ids[]"]:checked').each(function() {
            changedProducts++;
        });
        
        if (changedProducts === 0) {
            alert('No ha seleccionado ningún producto para actualizar.');
            e.preventDefault();
            return false;
        }
        
        return true;
    });
});
