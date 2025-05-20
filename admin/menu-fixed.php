<?php
/**
 * Configuración del menú de administración
 *
 * @package ReservaForm
 */

if (!defined('ABSPATH')) {
    exit;
}

// Incluir el archivo del dashboard
require_once plugin_dir_path( __FILE__ ) . 'dashboard.php';

/**
 * Función para verificar las capacidades del administrador
 */
function reserva_verify_admin_capabilities() {
    // Verificar si el usuario tiene los permisos necesarios
    if (!current_user_can('manage_options')) {
        wp_die(__('No tienes suficientes permisos para acceder a esta página.'));
    }
}

/**
 * Función para agregar el menú de administración
 */
function reserva_admin_menu() {
    // Añadir menú principal
    add_menu_page(
        'Reservas', // Título de la página
        'Reservas', // Texto del menú
        'manage_options', // Capacidad requerida
        'reservas', // Slug del menú
        'reserva_admin_dashboard', // Función de callback
        'dashicons-calendar-alt', // Icono
        30 // Posición
    );
    
    // Añadir submenú para el dashboard (primera opción)
    add_submenu_page(
        'reservas', // Parent slug
        'Dashboard', // Título de la página
        'Dashboard', // Texto del menú
        'manage_options', // Capacidad requerida
        'reservas', // Slug del menú
        'reserva_admin_dashboard' // Función de callback
    );
    
    // Añadir submenú para listar reservas
    add_submenu_page(
        'reservas', // Parent slug
        'Lista de Reservas', // Título de la página
        'Lista de Reservas', // Texto del menú
        'manage_options', // Capacidad requerida
        'reserva-lista', // Slug del menú
        'mostrar_lista_reservas' // Función de callback
    );
    
    // Añadir submenú para productos WooCommerce
    if (function_exists('reserva_is_woocommerce_active') && reserva_is_woocommerce_active()) {
        add_submenu_page(
            'reservas', // Parent slug
            'Productos para Reservas', // Título de la página
            'Productos para Reservas', // Texto del menú
            'manage_options', // Capacidad requerida
            'reserva-woocommerce', // Slug del menú
            'reserva_woocommerce_admin_page' // Función de callback
        );
    }
}
add_action('admin_menu', 'reserva_admin_menu');
