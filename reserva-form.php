<?php
/*
Plugin Name: Formulario de reserva
Description: Plugin para gestionar reservas de uniformes escolares (Scolari).
Version: 2.9.9
Author: Felipe Vega
Text Domain: reserva-form
*/

// Evitar acceso directo
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Incluir archivos necesarios
require_once plugin_dir_path( __FILE__ ) . 'includes/database/install.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/shortcodes.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/process.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/data/productos-manager.php';

if ( is_admin() ) {
    require_once plugin_dir_path( __FILE__ ) . 'admin/admin-menu.php';
    require_once plugin_dir_path( __FILE__ ) . 'admin/reserva-lista.php';
    require_once plugin_dir_path( __FILE__ ) . 'admin/products-admin.php';
}

// Registrar el hook de activación utilizando el archivo principal
register_activation_hook( __FILE__, 'reserva_form_instalar' );

// Verificar y forzar verificación de datos al cargar el plugin
function reserva_form_verificar_forzado() {
    if (function_exists('reserva_verificar_y_reparar_datos')) {
        $resultado = reserva_verificar_y_reparar_datos();
        if ($resultado) {
            // Si hubo reparación, borrar opción de error
            delete_option('reserva_form_datos_error');
        }
    }
}
add_action('plugins_loaded', 'reserva_form_verificar_forzado', 5);

// Verificar si hay datos en las tablas después de cargar el plugin
function reserva_verificar_datos_iniciales() {
    // Obtener opción para verificar si ya se ha ejecutado recientemente (prevenir ejecuciones redundantes)
    $ultima_verificacion = get_option('reserva_form_ultima_verificacion', 0);
    $tiempo_actual = time();
    
    // Solo ejecutar una vez al día como máximo
    if (($tiempo_actual - $ultima_verificacion) > 86400) { // 86400 segundos = 1 día
        if (function_exists('reserva_verificar_y_reparar_datos')) {
            reserva_verificar_y_reparar_datos();
            
            // Actualizar tiempo de última verificación
            update_option('reserva_form_ultima_verificacion', $tiempo_actual);
        }
    }
}
add_action('plugins_loaded', 'reserva_verificar_datos_iniciales');

// Mostrar advertencia en el admin si hay problemas con los datos
function reserva_mostrar_advertencia_datos() {
    if (is_admin() && get_option('reserva_form_datos_error') === 'si') {
        ?>
        <div class="notice notice-error is-dismissible">
            <p>
                <strong>Advertencia:</strong> Se han detectado problemas con los datos de productos en el plugin de Reservas. 
                <a href="<?php echo admin_url('admin.php?page=reserva-productos&action=verificar'); ?>">Haga clic aquí para reparar automáticamente</a>.
            </p>
        </div>
        <?php
    }
}
add_action('admin_notices', 'reserva_mostrar_advertencia_datos');

// Encolar archivos CSS y JS del plugin
function reserva_scolari_enqueue_scripts() {
    global $post;
    
    // Solo cargar en páginas que tengan el shortcode
    if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'reserva_form')) {
        // Asegurarte de incluir jQuery como dependencia
        wp_enqueue_script('jquery');
        
        // Cargar tus estilos con prioridad alta
        $version = filemtime(plugin_dir_path(__FILE__) . 'assets/css/reserva-form.css');
        wp_enqueue_style('reserva-form-css', plugin_dir_url(__FILE__) . 'assets/css/reserva-form.css', array(), $version, 'all');
        
        // Asegurarte de que se cargue con la prioridad más alta posible
        add_action('wp_footer', 'reserva_form_add_inline_css', 999);
        
        // Obtener productos y precios desde la base de datos
        $productos_con_precios = reserva_get_productos_con_precios();
        
        // Debug mejorado - mostrar productos en pantalla
        echo '<!-- DEBUG: Cantidad de productos cargados: ' . count($productos_con_precios) . ' -->';
        if (empty($productos_con_precios)) {
            error_log('ERROR: No se encontraron productos para el formulario de reserva');
            echo '<!-- ERROR: No se encontraron productos en la base de datos -->';
        }
        
        // Cargar el script principal - Asegurarse de que se carga en el footer
        wp_enqueue_script('reserva-form-js', plugin_dir_url(__FILE__) . 'assets/js/reserva-form.js', array('jquery'), time(), true);
        
        // Pasar las variables a JavaScript
        wp_localize_script('reserva-form-js', 'reserva_form_config', array(
            'auto_init' => true, // Cambiar a true para asegurar la inicialización
            'productos' => $productos_con_precios,
            'plugin_url' => plugin_dir_url(__FILE__)
        ));
    }
}
add_action('wp_enqueue_scripts', 'reserva_scolari_enqueue_scripts');

// Función para añadir CSS inline con alta prioridad
function reserva_form_add_inline_css() {
    echo '<style>
    /* Contrarrestar los estilos de CF7 y otros personalizados */
    .reserva-scolari-form input,
    .reserva-scolari-form textarea,
    .reserva-scolari-form select {
        width: 100%;
        padding: 10px 12px;
        margin: 0 0 8px 0;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
        background-color: #f9f9f9;
        box-sizing: border-box;
    }
    
    /* Asegurar que los botones y controles tengan el estilo correcto */
    .reserva-scolari-form button,
    .reserva-scolari-form input[type="button"],
    .reserva-scolari-form input[type="submit"] {
        line-height: normal;
        text-align: center;
        text-transform: uppercase;
        transition: all 0.3s ease;
        border: none;
    }
    
    /* Mantener los colores Scolari */
    .reserva-scolari-form .boton-reservar {
        background-color: #c52f2e;
        color: white;
    }
    
    .reserva-scolari-form .boton-reservar:hover {
        background-color: #a02827;
    }
    
    .reserva-scolari-form .seccion-encabezado {
        background-color: #003366;
    }
    
    .reserva-scolari-form h3 {
        color: #fff;
    }
    
    .reserva-scolari-form .seccion-numero {
        background-color: #c52f2e;
    }
    
    .reserva-scolari-form .boton-guia {
        background-color: #f0bd0e;
        color: #003366;
    }
    
    .reserva-scolari-form .boton-guia:hover {
        background-color: #d8aa0c;
        color: #fff;
    }
    
    /* Estilos para mensaje de error */
    .reserva-form-error,
    .error-message {
        background-color: #ffeaea;
        border: 1px solid #ffb0b0;
        border-left: 4px solid #c52f2e;
        color: #333;
        padding: 15px 20px;
        margin: 20px 0;
        border-radius: 4px;
    }
    
    .reserva-form-error h3,
    .error-message h3 {
        color: #c52f2e;
        margin-top: 0;
        font-size: 18px;
    }
    
    .reserva-form-error p,
    .error-message p {
        margin-bottom: 10px;
        font-size: 14px;
    }
    
    /* Botón deshabilitado */
    .reserva-scolari-form button.disabled {
        opacity: 0.6;
        cursor: not-allowed;
        background-color: #999;
    }
    </style>';
}

// Esta función queda obsoleta ya que reserva_scolari_enqueue_scripts ahora actualizada
// en un futuro se puede eliminar completamente para evitar duplicación
function reserva_form_enqueue_scripts() {
    // Función mantenida para compatibilidad, pero ya no se usa activamente
    // Usar reserva_scolari_enqueue_scripts en su lugar
    reserva_scolari_enqueue_scripts();
}

/**
 * Añadir scripts personalizados para mejorar la experiencia de usuario
 */
function reserva_add_custom_scripts() {
    // Solo enquedar en páginas relacionadas con reservas
    global $post;
    if (!is_a($post, 'WP_Post')) return;
    
    if (has_shortcode($post->post_content, 'reserva_form') || has_shortcode($post->post_content, 'reserva_exitosa')) {
        echo '<script type="text/javascript">
            document.addEventListener("DOMContentLoaded", function() {
                // Verificar que el DOM esté cargado
                console.log("DOM cargado completamente");
                
                // Asegurar que los elementos críticos sean visibles
                var infoBasica = document.querySelector(".reserva-info-basica");
                if (infoBasica) {
                    infoBasica.style.display = "block";
                    console.log("Info básica visible");
                }
                
                // Para móviles: mostrar versión móvil, ocultar escritorio
                if (window.innerWidth < 768) {
                    var mobileView = document.querySelector(".reserva-tabla-mobile");
                    var desktopView = document.querySelector(".reserva-tabla-desktop");
                    
                    if (mobileView) mobileView.style.display = "block";
                    if (desktopView) desktopView.style.display = "none";
                    
                    console.log("Ajustado para móvil: " + window.innerWidth + "px");
                }
            });
        </script>';
    }
}
add_action('wp_footer', 'reserva_add_custom_scripts', 100);
