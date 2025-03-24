<?php
/**
 * Gestión de Reservas - Archivo Principal
 *
 * @package ReservaPlugin
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Cargar archivos principales
require_once plugin_dir_path(__FILE__) . '../includes/helpers.php';
require_once plugin_dir_path(__FILE__) . 'classes/class-reserva-list.php';
require_once plugin_dir_path(__FILE__) . 'classes/class-reserva-detail.php';
require_once plugin_dir_path(__FILE__) . 'export.php';

/**
 * Función principal para mostrar la página de administración de reservas
 */
function mostrar_lista_reservas() {
    // Verificar si es exportación - mover esto ANTES de cualquier output
    if (isset($_GET['export']) && $_GET['export'] == 1) {
        if (ob_get_level()) {
            ob_end_clean(); // Limpiar cualquier búffer
        }
        reserva_export_csv();
        exit; // Asegurar que no continúe la ejecución
    }
    
    // Vista detalle
    if (isset($_GET['action']) && $_GET['action'] === 'edit' && !empty($_GET['id'])) {
        $detail = new Reserva_Detail(intval($_GET['id']));
        $detail->render();
        return;
    }
    
    // Vista por defecto - Lista de reservas
    $lista = new Reserva_List();
    $lista->process_actions();
    $lista->render();
}

/**
 * Registrar los estilos y scripts
 */
function reserva_admin_register_assets() {
    // Registrar CSS
    wp_register_style(
        'reserva-admin-list-css',
        plugin_dir_url(__FILE__) . 'css/admin-list.css',
        array(),
        filemtime(plugin_dir_path(__FILE__) . 'css/admin-list.css')
    );
    
    wp_register_style(
        'reserva-admin-detail-css',
        plugin_dir_url(__FILE__) . 'css/admin-detail.css',
        array(),
        filemtime(plugin_dir_path(__FILE__) . 'css/admin-detail.css')
    );
    
    // Registrar JavaScript
    wp_register_script(
        'reserva-admin-list-js',
        plugin_dir_url(__FILE__) . 'js/admin-list.js',
        array('jquery'),
        filemtime(plugin_dir_path(__FILE__) . 'js/admin-list.js'),
        true
    );
    
    wp_register_script(
        'reserva-admin-detail-js',
        plugin_dir_url(__FILE__) . 'js/admin-detail.js',
        array('jquery'),
        filemtime(plugin_dir_path(__FILE__) . 'js/admin-detail.js'),
        true
    );
    
    // Determinar qué estilos/scripts cargar
    $screen = get_current_screen();
    if ($screen && strpos($screen->id, 'page_reserva-lista') !== false) {
        if (isset($_GET['action']) && $_GET['action'] === 'edit') {
            wp_enqueue_style('reserva-admin-detail-css');
            wp_enqueue_script('reserva-admin-detail-js');
        } else {
            wp_enqueue_style('reserva-admin-list-css');
            wp_enqueue_script('reserva-admin-list-js');
        }
    }
}
add_action('admin_enqueue_scripts', 'reserva_admin_register_assets'); 