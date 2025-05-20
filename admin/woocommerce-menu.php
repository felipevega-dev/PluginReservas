<?php
/**
 * Integración con el menú de WooCommerce
 *
 * @package ReservaForm
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Añadir enlace en el menú de WooCommerce para gestionar productos reservables
 */
function reserva_add_woocommerce_menu_link() {
    // Verificar si WooCommerce está activo
    if (function_exists('reserva_is_woocommerce_active') && reserva_is_woocommerce_active()) {
        add_submenu_page(
            'woocommerce',
            'Productos para Reservas',
            'Productos para Reservas',
            'manage_woocommerce',
            'admin.php?page=reserva-woocommerce',
            null
        );
    }
}
add_action('admin_menu', 'reserva_add_woocommerce_menu_link', 99);

/**
 * Añadir enlace directo en la lista de productos de WooCommerce
 */
function reserva_add_woocommerce_products_button($views) {
    $url = admin_url('admin.php?page=reserva-woocommerce');
    $views['reservable_products'] = '<a href="' . esc_url($url) . '" class="button button-primary" style="margin: 0 10px;">Configurar Productos Reservables</a>';
    return $views;
}
add_filter('views_edit-product', 'reserva_add_woocommerce_products_button');
