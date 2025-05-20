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
 * Add a custom column to the products list
 */
function reserva_add_product_column($columns) {
    $columns['reservable'] = __('Reservable', 'reserva-form');
    return $columns;
}
add_filter('manage_product_posts_columns', 'reserva_add_product_column');

/**
 * Display content for the custom column
 */
function reserva_product_column_content($column, $post_id) {
    if ($column === 'reservable') {
        $reservable = get_post_meta($post_id, '_reservable', true);
        echo ($reservable === 'yes') ? '<span style="color:green;">✓</span>' : '<span style="color:red;">✗</span>';
    }
}
add_action('manage_product_posts_custom_column', 'reserva_product_column_content', 10, 2);

/**
 * Get a WooCommerce product by its slug
 * 
 * @param string $slug Product slug
 * @return WC_Product|false Product object or false if not found
 */
function reserva_get_woocommerce_product_by_slug($slug) {
    // Check if WooCommerce is active
    if (!reserva_is_woocommerce_active()) {
        error_log('WooCommerce no está activo. No se pueden obtener productos.');
        return false;
    }
    
    // Get products with the given slug
    $args = array(
        'status' => 'publish',
        'limit' => 1,
        'slug' => $slug
    );
    
    $products = wc_get_products($args);
    
    // Check if any product was found
    if (!empty($products)) {
        $product = $products[0];
        
        // Check if the product is marked as reservable
        $reservable = get_post_meta($product->get_id(), '_reservable', true);
        
        if ($reservable === 'yes') {
            return $product;
        } else {
            error_log('El producto con slug: ' . $slug . ' no está marcado como reservable');
        }
    } else {
        error_log('No se encontró ningún producto con slug: ' . $slug);
    }
    
    return false;
}
