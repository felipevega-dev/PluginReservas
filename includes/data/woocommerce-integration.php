<?php
/**
 * WooCommerce Integration for Reservation Form
 *
 * @package ReservaForm
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check if WooCommerce is active
 *
 * @return bool
 */
function reserva_is_woocommerce_active() {
    return class_exists('WooCommerce');
}

/**
 * Get WooCommerce products that are marked as reservable
 * 
 * This function gets products that have a specific meta field or category
 * that marks them as available for reservation
 * 
 * @return array Array of WooCommerce products
 */
function reserva_get_woocommerce_products() {
    // Check if WooCommerce is active
    if (!reserva_is_woocommerce_active()) {
        error_log('WooCommerce no está activo. No se pueden obtener productos.');
        return array();
    }
    
    // Get products marked as reservable
    // We'll use product meta to identify reservable products
    $args = array(
        'status' => 'publish',
        'limit' => -1,
        'meta_key' => '_reservable',
        'meta_value' => 'yes',
    );
    
    // Get the products
    $products = wc_get_products($args);
    
    return $products;
}

/**
 * Get WooCommerce products with their prices in the format needed for the reservation form
 * 
 * @return array Array of products with prices in the format expected by the JS
 */
function reserva_get_woocommerce_productos_con_precios() {
    // Get WooCommerce products
    $wc_products = reserva_get_woocommerce_products();
    
    // Format for the reservation form
    $resultado = array();
    
    foreach ($wc_products as $product) {
        $product_id = $product->get_id();
        $slug = $product->get_slug();
        
        // Get product attributes (for sizes)
        $attributes = $product->get_attributes();
        $sizes = array();
        
        // Check if it's a variable product with size variations
        if ($product->is_type('variable')) {
            $variations = $product->get_available_variations();
            
            foreach ($variations as $variation) {
                $variation_obj = wc_get_product($variation['variation_id']);
                $attributes = $variation['attributes'];
                
                // Look for size attribute
                $size = '';
                foreach ($attributes as $key => $value) {
                    if (strpos($key, 'size') !== false || strpos($key, 'talla') !== false) {
                        $size = $value;
                        break;
                    }
                }
                
                if (!empty($size)) {
                    $sizes[$size] = floatval($variation_obj->get_price());
                }
            }
        } else {
            // Simple product - use single price
            // For simple products, we'll use a default size "Única"
            $sizes['Única'] = floatval($product->get_price());
        }
        
        // Only add products that have sizes and prices
        if (!empty($sizes)) {
            $resultado[$slug] = array(
                'id' => $product_id,
                'nombre' => $product->get_name(),
                'slug' => $slug,
                'descripcion' => $product->get_short_description(),
                'imagen_url' => wp_get_attachment_url($product->get_image_id()),
                'img' => wp_get_attachment_url($product->get_image_id()),
                'precios' => $sizes,
                'categoria_id' => $product->get_category_ids()[0] ?? 0
            );
        }
    }
    
    if (empty($resultado)) {
        error_log('No se encontraron productos WooCommerce marcados como reservables');
    }
    
    return $resultado;
}

/**
 * Add a checkbox to WooCommerce product data panel to mark products as reservable
 */
function reserva_add_product_reservable_option() {
    echo '<div class="options_group">';
    
    woocommerce_wp_checkbox(
        array(
            'id'          => '_reservable',
            'label'       => __('Disponible para reserva', 'reserva-form'),
            'description' => __('Marcar este producto como disponible para el formulario de reservas', 'reserva-form'),
        )
    );
    
    echo '</div>';
}
add_action('woocommerce_product_options_general_product_data', 'reserva_add_product_reservable_option');

/**
 * Save the reservable option when the product is saved
 * 
 * @param int $product_id Product ID
 */
function reserva_save_product_reservable_option($product_id) {
    $reservable = isset($_POST['_reservable']) ? 'yes' : 'no';
    update_post_meta($product_id, '_reservable', $reservable);
}
add_action('woocommerce_process_product_meta', 'reserva_save_product_reservable_option');

/**
 * Add a bulk action to mark products as reservable
 */
function reserva_register_bulk_actions($bulk_actions) {
    $bulk_actions['mark_reservable'] = __('Marcar como reservable', 'reserva-form');
    $bulk_actions['unmark_reservable'] = __('Desmarcar como reservable', 'reserva-form');
    return $bulk_actions;
}
add_filter('bulk_actions-edit-product', 'reserva_register_bulk_actions');

/**
 * Handle bulk action to mark products as reservable
 */
function reserva_handle_bulk_actions($redirect_to, $action, $post_ids) {
    if ($action !== 'mark_reservable' && $action !== 'unmark_reservable') {
        return $redirect_to;
    }

    $value = ($action === 'mark_reservable') ? 'yes' : 'no';
    $processed_ids = array();

    foreach ($post_ids as $post_id) {
        update_post_meta($post_id, '_reservable', $value);
        $processed_ids[] = $post_id;
    }

    return add_query_arg(array(
        'bulk_action' => $action,
        'processed_count' => count($processed_ids),
        'processed_ids' => implode(',', $processed_ids),
    ), $redirect_to);
}
add_filter('handle_bulk_actions-edit-product', 'reserva_handle_bulk_actions', 10, 3);

/**
 * Display admin notice after bulk action
 */
function reserva_bulk_action_admin_notice() {
    if (empty($_REQUEST['bulk_action'])) {
        return;
    }

    $count = intval($_REQUEST['processed_count']);

    if ($_REQUEST['bulk_action'] === 'mark_reservable') {
        $message = sprintf(_n('%s producto marcado como reservable.', '%s productos marcados como reservables.', $count, 'reserva-form'), $count);
    } else {
        $message = sprintf(_n('%s producto desmarcado como reservable.', '%s productos desmarcados como reservables.', $count, 'reserva-form'), $count);
    }

    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
}
add_action('admin_notices', 'reserva_bulk_action_admin_notice');

/**
 * Añadir columna "Reservable" a la lista de productos
 */
function reserva_add_product_column($columns) {
    $new_columns = array();
    
    foreach ($columns as $key => $column) {
        $new_columns[$key] = $column;
        
        // Añadir columna después del precio
        if ($key === 'price') {
            $new_columns['reservable'] = __('Reservable', 'reserva-form');
        }
    }
    
    return $new_columns;
}
add_filter('manage_product_posts_columns', 'reserva_add_product_column', 20);

/**
 * Mostrar contenido de la columna "Reservable"
 */
function reserva_product_column_content($column, $product_id) {
    if ($column === 'reservable') {
        $is_reservable = get_post_meta($product_id, '_reservable', true);
        $checked = $is_reservable === 'yes' ? 'checked' : '';
        
        echo '<label class="reserva-switch">';
        echo '<input type="checkbox" class="reserva-toggle" data-product="' . esc_attr($product_id) . '" ' . $checked . '>';
        echo '<span class="reserva-slider"></span>';
        echo '</label>';
    }
}
add_action('manage_product_posts_custom_column', 'reserva_product_column_content', 10, 2);

/**
 * Agregar estilos para el toggle switch
 */
function reserva_admin_styles() {
    // Solo agregar en la página de productos
    $screen = get_current_screen();
    if ($screen->id !== 'edit-product') {
        return;
    }
    
    ?>
    <style>
    .reserva-switch {
        position: relative;
        display: inline-block;
        width: 50px;
        height: 24px;
    }
    
    .reserva-switch input { 
        opacity: 0;
        width: 0;
        height: 0;
    }
    
    .reserva-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        transition: .4s;
        border-radius: 24px;
    }
    
    .reserva-slider:before {
        position: absolute;
        content: "";
        height: 16px;
        width: 16px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
    }
    
    input:checked + .reserva-slider {
        background-color: #2196F3;
    }
    
    input:focus + .reserva-slider {
        box-shadow: 0 0 1px #2196F3;
    }
    
    input:checked + .reserva-slider:before {
        transform: translateX(26px);
    }
    
    /* Columna de ancho fijo */
    .column-reservable {
        width: 80px;
        text-align: center;
    }
    </style>
    <?php
}
add_action('admin_head', 'reserva_admin_styles');

/**
 * Agregar script para manejar el toggle con AJAX
 */
function reserva_admin_scripts() {
    // Solo agregar en la página de productos
    $screen = get_current_screen();
    if ($screen->id !== 'edit-product') {
        return;
    }
    
    ?>
    <script>
    jQuery(document).ready(function($) {
        $('.reserva-toggle').change(function() {
            var product_id = $(this).data('product');
            var is_checked = $(this).is(':checked');
            var value = is_checked ? 'yes' : 'no';
            
            // Mostrar indicador visual de carga
            var $switch = $(this).closest('.reserva-switch');
            $switch.css('opacity', '0.5');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'toggle_product_reservable',
                    product_id: product_id,
                    value: value,
                    nonce: '<?php echo wp_create_nonce("reserva_toggle_nonce"); ?>'
                },
                success: function(response) {
                    $switch.css('opacity', '1');
                    if (response.success) {
                        // Mostrar notificación de éxito
                        var message = is_checked ? 
                            'Producto marcado como reservable' : 
                            'Producto desmarcado como reservable';
                            
                        $('<div class="notice notice-success is-dismissible"><p>' + message + '</p></div>')
                            .insertAfter('.wp-header-end')
                            .delay(3000)
                            .fadeOut(function() {
                                $(this).remove();
                            });
                    } else {
                        // Si hay error, revertir el toggle
                        $(this).prop('checked', !is_checked);
                        alert('Error al actualizar el estado: ' + response.data);
                    }
                }.bind(this),
                error: function() {
                    $switch.css('opacity', '1');
                    $(this).prop('checked', !is_checked);
                    alert('Error de conexión al actualizar el estado');
                }.bind(this)
            });
        });
    });
    </script>
    <?php
}
add_action('admin_footer', 'reserva_admin_scripts');

/**
 * Endpoint AJAX para toggle de producto reservable
 */
function reserva_toggle_product_reservable() {
    // Verificar nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'reserva_toggle_nonce')) {
        wp_send_json_error('Verificación de seguridad fallida');
        return;
    }
    
    // Verificar permisos
    if (!current_user_can('edit_products')) {
        wp_send_json_error('Permisos insuficientes');
        return;
    }
    
    $product_id = intval($_POST['product_id']);
    $value = sanitize_text_field($_POST['value']);
    
    // Actualizar meta
    update_post_meta($product_id, '_reservable', $value);
    
    // Registrar en log
    $product = wc_get_product($product_id);
    $product_name = $product ? $product->get_name() : 'Producto #' . $product_id;
    $action = $value === 'yes' ? 'marcado' : 'desmarcado';
    error_log("Producto '{$product_name}' {$action} como reservable por " . wp_get_current_user()->user_login);
    
    wp_send_json_success();
}
add_action('wp_ajax_toggle_product_reservable', 'reserva_toggle_product_reservable');

// Nota: La función reserva_product_column_content ya está definida anteriormente
// y ya tiene su add_action correspondiente

/**
 * Cargar scripts y estilos en páginas de administración específicas
 */
function reserva_admin_wc_scripts() {
    // Detectar la página actual
    global $pagenow, $typenow;
    $page = isset($_GET['page']) ? $_GET['page'] : '';
    $screen = get_current_screen();
    
    // Cargar en la página específica de WooCommerce para Reservas
    if ($page === 'reserva-woocommerce') {
        error_log('Cargando scripts para página reserva-woocommerce');
        
        // Cargar jQuery explícitamente
        wp_enqueue_script('jquery');
        
        // Cargar los scripts y estilos necesarios
        wp_enqueue_style('reserva-admin-css', plugin_dir_url(__FILE__) . '../../assets/css/admin-style.css', array(), time());
        wp_enqueue_script('reserva-wc-admin-js', plugin_dir_url(__FILE__) . '../../assets/js/wc-admin.js', array('jquery'), time(), true);
        
        // Agregar variables para el script
        wp_localize_script('reserva-wc-admin-js', 'reservaWC', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('reserva_wc_nonce')
        ));
    }
}
add_action('admin_enqueue_scripts', 'reserva_admin_wc_scripts');

/**
 * Imprimir JavaScript de depuración directamente en el pie de página
 */
function reserva_print_debug_js() {
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        console.log('*** SCRIPT DE DEPURACIÓN DIRECTA ***');
        console.log('Formulario presente:', $('#reserva-products-form').length);
        console.log('Total checkboxes:', $('input[type="checkbox"]').length);
        console.log('Checkboxes reservable:', $('input[name^="reservable"]').length);
        
        // Rastrear eventos de checkboxes
        $('input[type="checkbox"]').on('change', function() {
            console.log('Checkbox cambiado:', $(this).attr('name'), 'Valor:', $(this).is(':checked'));
        });
        
        // Rastrear envío de formulario
        $('#reserva-products-form').on('submit', function(e) {
            console.log('FORMULARIO ENVIÁNDOSE');
            var data = [];
            $('input[name^="reservable"]:checked').each(function() {
                data.push($(this).attr('name'));
            });
            console.log('Datos a enviar:', data);
        });
    });
    </script>
    <?php
}

/**
 * Get a WooCommerce product by its slug
 * 
 * @param string $slug Product slug
 * @return WC_Product|false Product object or false if not found
 */
function reserva_get_woocommerce_product_by_slug($slug) {
    // Check if WooCommerce is active
    if (!reserva_is_woocommerce_active()) {
        error_log("WooCommerce no está activo al intentar obtener producto por slug: {$slug}");
        return false;
    }
    
    // Sanity check - validar que el slug no esté vacío
    if (empty($slug)) {
        error_log("Se intentó buscar un producto con slug vacío");
        return false;
    }
    
    // Depuración
    error_log("Buscando producto WooCommerce con slug: {$slug}");
    
    // Obtener productos con este slug
    $args = array(
        'status' => 'publish',
        'limit' => 1,
        'slug' => $slug
    );
    
    $products = wc_get_products($args);
    
    if (!empty($products)) {
        error_log("Producto WooCommerce encontrado con slug '{$slug}': " . $products[0]->get_name());
        return $products[0];
    }
    
    // Si no se encuentra por slug, intentar buscar por ID
    if (is_numeric($slug)) {
        $product = wc_get_product(intval($slug));
        if ($product && $product->get_status() === 'publish') {
            error_log("Producto WooCommerce encontrado por ID {$slug}: " . $product->get_name());
            return $product;
        }
    }
    
    // Intentar buscar productos similares por nombre
    $products = wc_get_products(array(
        'status' => 'publish',
        'limit' => 1,
        's' => $slug
    ));
    
    if (!empty($products)) {
        error_log("Producto WooCommerce encontrado por búsqueda '{$slug}': " . $products[0]->get_name());
        return $products[0];
    }
    
    error_log('No se encontró ningún producto con slug: ' . $slug);
    
    return false;
}
