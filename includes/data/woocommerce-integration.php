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
