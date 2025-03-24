<?php
// Evitar acceso directo
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Incluir gestor de productos
require_once plugin_dir_path( __FILE__ ) . 'data/productos-manager.php';

/**
 * Shortcode del formulario de reserva.
 */
function reserva_form_shortcode() {
    ob_start();

    // Obtener productos con sus precios desde la base de datos
    $productos_con_precios = reserva_get_productos_con_precios();

    // Verificar si hay productos disponibles
    if (empty($productos_con_precios)) {
        echo '<div class="reserva-form-error">
              <h3>No hay productos disponibles en este momento</h3>
              <p>Lo sentimos, no podemos mostrar el formulario de reserva porque no hay productos configurados.</p>
              <p>Por favor, intente más tarde o contacte con el administrador.</p>
              </div>';
        return ob_get_clean();
    }

    // Lista completa de comunas de la Región Metropolitana
    $comunas_chile = array(
        'Cerrillos','Cerro Navia','Conchalí','El Bosque','Estación Central','Huechuraba','Independencia',
        'La Cisterna','La Florida','La Granja','La Pintana','La Reina','Las Condes','Lo Barnechea','Lo Espejo',
        'Lo Prado','Macul','Maipú','Ñuñoa','Pedro Aguirre Cerda','Peñalolén','Providencia','Pudahuel',
        'Quilicura','Quinta Normal','Recoleta','Renca','San Joaquín','San Miguel','San Ramón','Santiago',
        'Vitacura','Puente Alto','Pirque','San José de Maipo','Colina','Lampa','Tiltil','San Bernardo','Buin',
        'Calera de Tango','Paine','Melipilla','Alhué','Curacaví','María Pinto','San Pedro','Talagante','El Monte',
        'Isla de Maipo','Padre Hurtado','Peñaflor'
    );
    
    // Ordenar las comunas alfabéticamente
    sort($comunas_chile);

    // En la sección de guías de tallas
    $guia_pantalon_url = 'https://www.scolari.cl/wp-content/uploads/2025/01/3.png';
    $guia_polera_url = 'https://www.scolari.cl/wp-content/uploads/2025/01/1.png';
    $guia_poleron_url = 'https://www.scolari.cl/wp-content/uploads/2025/01/4.png';
    ?>
    <!-- El CSS y JS se cargarán desde assets/css/reserva-form.css y assets/js/reserva-form.js -->
    <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" class="reserva-form reserva-scolari-form" id="reservaForm">

        <?php wp_nonce_field( 'reserva_form_action', 'reserva_form_nonce' ); ?>
        <input type="hidden" name="action" value="procesar_reserva">
        <input type="hidden" name="producto_data" id="producto_data" value="">
        
        <div class="form-container">
            <!-- Sección 1: Datos personales -->
            <div class="seccion-form seccion-datos-personales">
                    <div class="seccion-encabezado">
                    <div class="seccion-numero">1</div>
                    <h3>Tus Datos Personales</h3>
                    </div>
                    <div class="seccion-contenido">
                        <div class="form-row">
                        <div class="form-col">
                            <label for="nombre">Nombre completo*</label>
                            <input type="text" id="nombre" name="nombre" required>
                        </div>
                        </div>
                    <div class="form-row">
                        <div class="form-col form-col-half">
                            <label for="email">Email*</label>
                            <input type="email" id="email" name="email" required>
                            </div>
                        <div class="form-col form-col-half">
                            <label for="telefono">Teléfono*</label>
                            <input type="tel" id="telefono" name="telefono" required>
                        </div>
                        </div>
                        <div class="form-row">
                        <div class="form-col">
                            <label for="direccion">Dirección</label>
                            <input type="text" id="direccion" name="direccion">
                        </div>
                        </div>
                        <div class="form-row">
                        <div class="form-col">
                            <label for="comuna">Comuna</label>
                            <select id="comuna" name="comuna">
                                <option value="">Selecciona tu comuna</option>
                                <?php foreach ($comunas_chile as $comuna) : ?>
                                    <option value="<?php echo esc_attr($comuna); ?>"><?php echo esc_html($comuna); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        </div>
                        <div class="form-row">
                        <div class="form-col form-col-half">
                            <label for="fecha_necesidad">Fecha estimada de recogida*</label>
                            <input type="date" id="fecha_necesidad" name="fecha" required>
                        </div>
                        </div>
                    </div>
                </div>

            <!-- Sección 2: Selección de productos y Confirmación (unificados) -->
            <div class="seccion-form seccion-productos-confirmacion">
                <div class="seccion-encabezado">
                    <div class="seccion-numero">2</div>
                    <h3>Uniformes a Reservar</h3>
                </div>
                <div class="seccion-contenido">
                    <p class="instruccion">Selecciona los productos, tallas y cantidades que deseas reservar.</p>
                    
                    <div id="productos-container" class="productos-container">
                        <!-- Aquí se cargarán dinámicamente las líneas de productos -->
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" id="btn-agregar-producto" class="btn-agregar-producto">
                            <span class="icono-mas">+</span> Agregar otro producto
                        </button>
                    </div>
                    
                    <!-- Resumen integrado aquí -->
                    <div class="resumen-confirmacion">
                        <!-- Aquí se mostrará el resumen de productos seleccionados -->
                    </div>
                    
                    <div class="form-actions center-button">
                        <button type="submit" class="boton-reservar">Hacer mi Reserva</button>
                    </div>
                </div>
            </div>
            
            <!-- Sección 3: Guía de tallas -->
            <div class="seccion-form seccion-guia-tallas">
                    <div class="seccion-encabezado">
                    <div class="seccion-numero">3</div>
                    <h3>Guía de Tallas</h3>
                    </div>
                    <div class="seccion-contenido">
                        <div class="guia-botones">
                            <button type="button" class="boton-guia" data-target="guiaPantalon">Ver Guía Pantalón</button>
                            <button type="button" class="boton-guia" data-target="guiaPolera">Ver Guía Polera</button>
                            <button type="button" class="boton-guia" data-target="guiaPoleron">Ver Guía Polerón</button>
                        </div>
                        
                        <div id="guiaPantalon" class="guia-contenedor" style="display:none;">
                            <img src="<?php echo esc_url($guia_pantalon_url); ?>" alt="Guía de Tallas Pantalón" style="max-width:100%;">
                        </div>

                        <div id="guiaPolera" class="guia-contenedor" style="display:none;">
                            <img src="<?php echo esc_url($guia_polera_url); ?>" alt="Guía de Tallas Polera" style="max-width:100%;">
                        </div>

                        <div id="guiaPoleron" class="guia-contenedor" style="display:none;">
                            <img src="<?php echo esc_url($guia_poleron_url); ?>" alt="Guía de Tallas Polerón" style="max-width:100%;">
                    </div>
                </div>
            </div>
        </div>
    </form>

    <!-- IMPORTANTE: NO AGREGAR SCRIPT INLINE AQUÍ. TODO SE MANEJA EN RESERVA-FORM.JS -->
    <?php
    
    // La configuración de productos se maneja en reserva-form.php
    // No usar wp_localize_script aquí para evitar duplicación
    
    return ob_get_clean();
}
add_shortcode( 'reserva_form', 'reserva_form_shortcode' );

/**
 * Shortcode para la página de éxito.
 */
function shortcode_reserva_exitosa($atts) {
    // Cargar estilos específicos para la página de éxito
    wp_enqueue_style('reserva-exitosa-css', plugin_dir_url(dirname(__FILE__)) . 'assets/css/reserva-exitosa.css', array(), time(), 'all');
    
    // Cargar script para la página de éxito
    wp_enqueue_script('reserva-exitosa-js', plugin_dir_url(dirname(__FILE__)) . 'assets/js/reserva-exitosa.js', array('jquery'), time(), true);
    
    $reserva_id = isset($_GET['reserva_id']) ? intval($_GET['reserva_id']) : 0;
    
    if (empty($reserva_id)) {
        return '<div class="reserva-no-encontrada">
            <h1>Reserva no encontrada</h1>
            <p>No se ha proporcionado un ID de reserva válido.</p>
            <a href="' . home_url() . '" class="btn-volver">Volver al inicio</a>
        </div>';
    }
    
    global $wpdb;
    
    // Buscar la reserva directamente en la tabla wp_reservas
    $tabla = $wpdb->prefix . 'reservas';
    $reserva = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tabla WHERE id = %d", $reserva_id));
    
    if (!$reserva) {
        return '<div class="reserva-no-encontrada">
            <h1>Reserva no encontrada</h1>
            <p>La reserva solicitada no existe o ha sido eliminada.</p>
            <a href="' . home_url() . '" class="btn-volver">Volver al inicio</a>
        </div>';
    }
    
    // Formatear fecha
    $fecha_creacion = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($reserva->fecha_registro));
    
    // Construir la salida HTML
    $output = '<div class="reserva-exitosa-container">';
    $output .= '<div class="reserva-exitosa-card">';
    
    // Header con ícono y título
    $output .= '<div class="reserva-exitosa-header">';
    $output .= '<div class="reserva-exitosa-icon">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="100%" height="100%" fill="#c52f2e">
            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
        </svg>
    </div>';
    $output .= '<h1>¡Reserva realizada con éxito!</h1>';
    $output .= '<div class="reserva-numero">Reserva #' . $reserva_id . '</div>';
    $output .= '</div>';
    
    // Mensaje de confirmación
    $output .= '<div class="reserva-exitosa-mensaje">';
    $output .= '<p>Tu reserva ha sido registrada correctamente el <strong>' . $fecha_creacion . '</strong>.</p>';
    $output .= '<p>Hemos enviado los detalles de tu reserva a <strong>' . esc_html($reserva->email) . '</strong>.</p>';
    $output .= '</div>';
    
    // Botones de acción
    $output .= '<div class="reserva-exitosa-actions">';
    $output .= '<button onclick="window.print();" class="btn-imprimir">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="currentColor">
            <path d="M19 8h-1V3H6v5H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zM8 5h8v3H8V5zm8 12v2H8v-4h8v2zm2-2v-2H6v2H4v-4c0-.55.45-1 1-1h14c.55 0 1 .45 1 1v4h-2z"/>
        </svg>
        Imprimir reserva
    </button>';
    $output .= '<a href="' . home_url() . '" class="btn-volver">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="currentColor">
            <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/>
        </svg>
        Volver al inicio
    </a>';
    $output .= '</div>';
    
    // Secciones de detalles
    $output .= '<div class="reserva-exitosa-detalles">';
    $output .= '<h2>Detalles de tu reserva</h2>';
    
    $output .= '<div class="detalles-grid">';
    
    // Datos personales
    $output .= '<div class="detalles-seccion">';
    $output .= '<h3>Datos personales</h3>';
    $output .= '<ul class="datos-personales">';
    $output .= '<li><strong>Nombre:</strong> ' . esc_html($reserva->nombre) . '</li>';
    $output .= '<li><strong>Email:</strong> ' . esc_html($reserva->email) . '</li>';
    $output .= '<li><strong>Teléfono:</strong> ' . esc_html($reserva->telefono) . '</li>';
    
    // Si existe dirección
    if (property_exists($reserva, 'direccion')) {
        $output .= '<li><strong>Dirección:</strong> ' . esc_html($reserva->direccion) . '</li>';
    }
    
    // Si existe comuna
    if (property_exists($reserva, 'comuna')) {
        $output .= '<li><strong>Comuna:</strong> ' . esc_html($reserva->comuna) . '</li>';
    }
    
    $output .= '<li><strong>Fecha de recogida:</strong> ' . esc_html(date_i18n(get_option('date_format'), strtotime($reserva->fecha))) . '</li>';
    $output .= '</ul>';
    $output .= '</div>';
    
    // Datos de contacto de Scolari
    $output .= '<div class="detalles-seccion">';
    $output .= '<h3>Contacto de Scolari</h3>';
    $output .= '<ul class="datos-personales">';
    $output .= '<li><strong>Dirección 1:</strong> Balmoral 163, Las Condes.</li>';
    $output .= '<li><strong>Dirección 2:</strong> Camino Chicureo 1700, Local 16. Terrazas de Chicureo.</li>';
    $output .= '<li><strong>Teléfono:</strong>  +569 57881632</li>';
    $output .= '<li><strong>Email:</strong> tiendavirtual@scolari.cl</li>';
    $output .= '<li><strong>Horario:</strong> Lunes a Jueves de 10:00 a 19:00 Viernes de 10:00 a 18:00</li>';
    $output .= '</ul>';
    $output .= '</div>';
    
    $output .= '</div>'; // Fin detalles-grid
    
    // Tabla de productos desde JSON
    $output .= '<h2>Productos reservados</h2>';
    
    $productos_json = json_decode($reserva->productos, true);
    
    if (is_array($productos_json) && !empty($productos_json)) {
        $output .= '<table class="productos-table">';
        $output .= '<thead>
            <tr>
                <th>Producto</th>
                <th>Talla</th>
                <th>Cantidad</th>
                <th>Precio unitario</th>
                <th>Subtotal</th>
            </tr>
        </thead>';
        $output .= '<tbody>';
        
        $total = 0;
        
        foreach ($productos_json as $producto) {
            $nombre = isset($producto['producto']) ? $producto['producto'] : 'Producto sin nombre';
            $talla = isset($producto['talla']) ? $producto['talla'] : '-';
            $cantidad = isset($producto['cantidad']) ? intval($producto['cantidad']) : 0;
            $precio_unitario = isset($producto['precio']) ? intval($producto['precio']) : 0;
            $subtotal = isset($producto['subtotal']) ? intval($producto['subtotal']) : ($precio_unitario * $cantidad);
            
            $total += $subtotal;
            
            $output .= '<tr>';
            $output .= '<td>' . esc_html($nombre) . '</td>';
            $output .= '<td>' . esc_html($talla) . '</td>';
            $output .= '<td>' . esc_html($cantidad) . '</td>';
            $output .= '<td>$' . number_format($precio_unitario, 0, ',', '.') . '</td>';
            $output .= '<td>$' . number_format($subtotal, 0, ',', '.') . '</td>';
            $output .= '</tr>';
        }
        
        $output .= '</tbody>';
        $output .= '<tfoot>
            <tr>
                <td colspan="4">Total</td>
                <td>$' . number_format($total, 0, ',', '.') . '</td>
            </tr>
        </tfoot>';
        $output .= '</table>';
    } else {
        $output .= '<div class="no-productos">
            <p>No hay detalles de productos disponibles para esta reserva.</p>
            <p>Monto total registrado: $' . number_format($reserva->total, 0, ',', '.') . '</p>
        </div>';
    }
    
    $output .= '</div>'; // Fin reserva-exitosa-detalles
    
    // Información adicional
    $output .= '<div class="reserva-exitosa-info">';
    $output .= '<h3>Información importante</h3>';
    $output .= '<ol>';
    $output .= '<li>Para recoger tu pedido, deberás presentar tu número de reserva y un documento de identidad.</li>';
    $output .= '<li>El periodo para recoger los uniformes es de 15 días a partir de la fecha de reserva.</li>';
    $output .= '<li>Si necesitas hacer cambios en tu pedido, por favor contacta con la administración del colegio lo antes posible.</li>';
    $output .= '</ol>';
    $output .= '</div>';
    
    $output .= '</div>'; // Fin reserva-exitosa-card
    $output .= '</div>'; // Fin reserva-exitosa-container
    
    return $output;
}
add_shortcode( 'reserva_exitosa', 'shortcode_reserva_exitosa' );