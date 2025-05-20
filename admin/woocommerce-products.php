<?php
/**
 * WooCommerce Products Admin Page
 *
 * @package ReservaForm
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register WooCommerce products admin menu
 */
function reserva_register_woocommerce_products_menu() {
    add_submenu_page(
        'reserva-lista',
        'Productos WooCommerce',
        'Productos WC',
        'manage_options',
        'reserva-woocommerce',
        'reserva_woocommerce_admin_page'
    );
}
add_action('admin_menu', 'reserva_register_woocommerce_products_menu', 21);

/**
 * WooCommerce products admin page
 */
function reserva_woocommerce_admin_page() {
    // Check permissions
    if (!current_user_can('manage_options')) {
        return;
    }

    // Check if WooCommerce is active
    if (!function_exists('reserva_is_woocommerce_active') || !reserva_is_woocommerce_active()) {
        ?>
        <div class="wrap">
            <h1>Productos WooCommerce para Reservas</h1>
            <div class="notice notice-error">
                <p><strong>Error:</strong> WooCommerce no está activo. Por favor, active WooCommerce para usar esta funcionalidad.</p>
            </div>
        </div>
        <?php
        return;
    }

    // Process bulk actions
    if (isset($_POST['action']) && $_POST['action'] == 'update_reservable_status') {
        if (isset($_POST['product_ids']) && is_array($_POST['product_ids'])) {
            foreach ($_POST['product_ids'] as $product_id) {
                $reservable = isset($_POST['reservable'][$product_id]) ? 'yes' : 'no';
                update_post_meta($product_id, '_reservable', $reservable);
            }
            echo '<div class="notice notice-success is-dismissible"><p>Estado de productos actualizado correctamente.</p></div>';
        }
    }

    // Get all published WooCommerce products
    $args = array(
        'status' => 'publish',
        'limit' => -1,
    );
    $products = wc_get_products($args);
    ?>
    <div class="wrap">
        <h1>Productos WooCommerce para Reservas</h1>
        <p>Seleccione los productos de WooCommerce que estarán disponibles en el formulario de reservas.</p>
        
        <form method="post" action="">
            <input type="hidden" name="action" value="update_reservable_status">
            <?php wp_nonce_field('reserva_update_wc_products', 'reserva_wc_nonce'); ?>
            
            <div class="tablenav top">
                <div class="alignleft actions bulkactions">
                    <input type="submit" class="button action" value="Guardar cambios">
                </div>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th class="check-column"><input type="checkbox" id="cb-select-all"></th>
                        <th>Producto</th>
                        <th>SKU</th>
                        <th>Precio</th>
                        <th>Tipo</th>
                        <th>Disponible para reserva</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="6">No hay productos disponibles en WooCommerce.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $product): 
                            $product_id = $product->get_id();
                            $is_reservable = get_post_meta($product_id, '_reservable', true) === 'yes';
                            ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="product_ids[]" value="<?php echo esc_attr($product_id); ?>" <?php checked(true); ?>>
                                </td>
                                <td>
                                    <strong><a href="<?php echo get_edit_post_link($product_id); ?>"><?php echo esc_html($product->get_name()); ?></a></strong>
                                </td>
                                <td><?php echo esc_html($product->get_sku()); ?></td>
                                <td><?php echo wc_price($product->get_price()); ?></td>
                                <td><?php echo ucfirst($product->get_type()); ?></td>
                                <td>
                                    <input type="checkbox" name="reservable[<?php echo esc_attr($product_id); ?>]" <?php checked($is_reservable); ?>>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <div class="tablenav bottom">
                <div class="alignleft actions bulkactions">
                    <input type="submit" class="button action" value="Guardar cambios">
                </div>
            </div>
        </form>
        
        <script>
        jQuery(document).ready(function($) {
            // Select all checkbox functionality
            $('#cb-select-all').on('change', function() {
                var isChecked = $(this).prop('checked');
                $('input[name="product_ids[]"]').prop('checked', isChecked);
            });
        });
        </script>
    </div>
    <?php
}
