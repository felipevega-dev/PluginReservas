<?php
// Evitar acceso directo
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Incluir archivos necesarios
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/templates/email-template.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/data/productos-manager.php';

/**
 * Procesar los datos del formulario de reserva
 */
function procesar_reserva() {
    // Verificar nonce de seguridad
    if ( ! isset( $_POST['reserva_form_nonce'] ) ||
         ! wp_verify_nonce( $_POST['reserva_form_nonce'], 'reserva_form_action' ) ) {
        wp_die( 'Error de seguridad. Intenta de nuevo.' );
    }
    
    // Verificar campos requeridos
    if ( ! isset($_POST['nombre'], $_POST['email'], $_POST['telefono'], $_POST['fecha']) ) {
        wp_die( 'Datos incompletos. Por favor, completa todos los campos requeridos.' );
    }

    // Sanitizar datos personales
    $nombre    = sanitize_text_field( $_POST['nombre'] );
    $email     = sanitize_email( $_POST['email'] );
    $telefono  = sanitize_text_field( $_POST['telefono'] ?? '' );
    $fecha     = sanitize_text_field( $_POST['fecha'] );
    $direccion = isset($_POST['direccion']) ? sanitize_text_field($_POST['direccion']) : '';
    $comuna    = isset($_POST['comuna']) ? sanitize_text_field($_POST['comuna']) : '';

    // Validar teléfono (solo dígitos)
    if ( ! empty($telefono) && ! preg_match('/^[0-9]+$/', $telefono) ) {
        wp_die('El teléfono solo puede contener dígitos.');
    }

    // Validar fecha
    $time_fecha = strtotime($fecha);
    if ($time_fecha === false) {
        wp_die('Fecha inválida.');
    }
    if ($time_fecha > strtotime('2050-12-31')) {
        wp_die('La fecha no puede superar el año 2050.');
    }
    if ($time_fecha < strtotime('today')) {
        wp_die('La fecha no puede ser anterior a hoy.');
    }

    // Mejorar la validación de email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        wp_die('El correo electrónico no es válido.');
    }

    // Procesar datos de productos
    $items_data = array();
    $totalPrecio = 0;

    // Verificar si tenemos datos de producto en formato JSON (desde versión con JS)
    if ( isset($_POST['producto_data']) && !empty($_POST['producto_data']) ) {
        $json_raw = $_POST['producto_data'];
        
        // Registrar los datos recibidos para diagnóstico
        error_log('Datos de productos recibidos (raw): ' . $json_raw);
        
        // Limpiar el JSON antes de procesarlo
        $json_raw = stripslashes($json_raw);
        $json_raw = trim($json_raw);
        
        // Verificar formato JSON
        $first_char = substr($json_raw, 0, 1);
        $last_char = substr($json_raw, -1);
        
        error_log('Primer/último carácter del JSON: ' . $first_char . '/' . $last_char);
        
        if ($first_char != '[' || $last_char != ']') {
            error_log('JSON con formato incorrecto, añadiendo corchetes');
            // Intentar corregir JSON en formato incorrecto
            if ($first_char != '[') $json_raw = '[' . $json_raw;
            if ($last_char != ']') $json_raw = $json_raw . ']';
        }
        
        // Intentar decodificar
        $json_data = json_decode($json_raw, true);
        
        // Si falla, intentar decodificar después de escapar caracteres
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Error al decodificar JSON: ' . json_last_error_msg() . '. Intentando escape alternativo');
            $json_data = json_decode(preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $json_raw), true);
        }
        
        // Si sigue fallando, registrar el error
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Error definitivo al decodificar JSON: ' . json_last_error_msg());
            error_log('JSON crudo después de limpieza: ' . $json_raw);
        } else {
            error_log('JSON decodificado exitosamente con ' . (is_array($json_data) ? count($json_data) : 'NULL') . ' elementos');
        }
        
        if ( is_array($json_data) && !empty($json_data) ) {
            error_log('Productos decodificados: ' . count($json_data));
            
            foreach ($json_data as $item) {
                // Registrar cada item para diagnóstico
                error_log('Procesando item: ' . print_r($item, true));
                
                // Validar los datos necesarios
                if ( isset($item['slug'], $item['talla'], $item['cantidad'], $item['unitPrice']) ) {
                    $prod_slug  = sanitize_text_field($item['slug']);
                    $talla      = sanitize_text_field($item['talla']);
                    $cant       = intval($item['cantidad']);
                    $unitPrice  = floatval($item['unitPrice']);
                    $subtotal   = isset($item['subtotal']) ? floatval($item['subtotal']) : ($unitPrice * $cant);
                    
                    // Obtener el ID del producto
                    $producto = reserva_get_producto_by_slug($prod_slug);
                    
                    if ($producto && $cant > 0) {
                        // Registrar éxito del producto encontrado
                        error_log('Producto encontrado: ' . $producto->nombre);
                        
                        // Obtener ID de la talla
                        $talla_id = reserva_get_talla_id_by_nombre($talla);
                        
                        if ($talla_id) {
                            error_log('Talla encontrada con ID: ' . $talla_id);
                            
                            // Verificar precio correcto
                            $precios = reserva_get_precios_producto($producto->id);
                            
                            if (isset($precios[$talla])) {
                                $unitPrice = $precios[$talla];
                                $subtotal = $unitPrice * $cant;
                                $totalPrecio += $subtotal;
                                
                                $items_data[] = array(
                                    'producto_id' => $producto->id,
                                    'talla_id' => $talla_id,
                                    'cantidad' => $cant,
                                    'precio_unitario' => $unitPrice
                                );
                                
                                error_log('Item agregado correctamente');
                            } else {
                                error_log('No se encontró precio para talla: ' . $talla);
                                error_log('Precios disponibles: ' . print_r($precios, true));
                            }
                        } else {
                            error_log('No se encontró ID para la talla: ' . $talla);
                        }
                    } else {
                        if (!$producto) {
                            error_log('No se encontró el producto con slug: ' . $prod_slug);
                        }
                        if ($cant <= 0) {
                            error_log('Cantidad inválida: ' . $cant);
                        }
                    }
                } else {
                    error_log('Faltan campos requeridos en el item: ' . 
                             (isset($item['slug']) ? 'slug OK' : 'falta slug') . ', ' . 
                             (isset($item['talla']) ? 'talla OK' : 'falta talla') . ', ' . 
                             (isset($item['cantidad']) ? 'cantidad OK' : 'falta cantidad') . ', ' . 
                             (isset($item['unitPrice']) ? 'unitPrice OK' : 'falta unitPrice'));
                }
            }
        } else {
            error_log('JSON no es un array o está vacío');
        }
    } else {
        error_log('No se recibieron datos de producto_data');
    }

    // Verificar que hay productos válidos
    if ( empty($items_data) ) {
        wp_die('Debe seleccionar al menos un producto válido.');
    }

    // Preparar datos para guardar en BD
    $reserva_data = array(
        'nombre'    => $nombre,
        'direccion' => $direccion,
        'email'     => $email,
        'telefono'  => $telefono,
        'fecha'     => $fecha,
        'total'     => $totalPrecio,
        'fecha_registro' => current_time( 'mysql' ),
        'estado'    => 'pendiente',
        'comuna'    => $comuna
    );

    // Depuración antes de guardar
    error_log('PROCESS.PHP - Datos a guardar: ' . json_encode($reserva_data));
    error_log('PROCESS.PHP - Items a guardar: ' . json_encode($items_data));
    
    // Guardar la reserva
    $reserva_id = reserva_guardar_reserva($reserva_data, $items_data);
    
    // Depuración después de guardar
    error_log('PROCESS.PHP - Resultado de la inserción: ' . ($reserva_id ? 'ID: ' . $reserva_id : 'ERROR'));

    // Verificar inserción exitosa
    if (false === $reserva_id) {
        error_log('PROCESS.PHP - Error al guardar la reserva en la base de datos.');
        wp_die('Error al guardar la reserva. Por favor, inténtalo de nuevo.');
    }

    // Enviar correo al cliente
    $to = sanitize_email($email);
    $subject = 'Confirmación de Reserva - Scolari #' . $reserva_id;
    $headers = array('Content-Type: text/html; charset=UTF-8');

    // Obtener los detalles completos para el correo directamente desde la base de datos
    $reserva_completa = reserva_get_reserva_completa($reserva_id);
    
    if ($reserva_completa) {
        // Asegurar que tenemos todos los datos necesarios para el email
        $reserva_email = array(
            'id' => $reserva_id,
            'nombre' => $nombre,
            'email' => $email,
            'telefono' => $telefono,
            'direccion' => $direccion,
            'comuna' => $comuna,
            'fecha' => $fecha,
            'total' => $totalPrecio,
            'fecha_registro' => current_time('mysql')
        );
        
        $detalles_productos = $reserva_completa['items'];

        // Usar la plantilla HTML con todos los datos completos
        $email_body = reserva_form_get_email_template((object)$reserva_email, $detalles_productos);

        wp_mail($to, $subject, $email_body, $headers);
        
        // Enviar notificación al administrador
        $admin_email = get_option('admin_email');
        if ($admin_email) {
            $admin_subject = 'Nueva Reserva #' . $reserva_id;
            $admin_message = '<html><body>';
            $admin_message .= '<h2>Se ha recibido una nueva reserva</h2>';
            $admin_message .= '<p><strong>Número:</strong> ' . $reserva_id . '</p>';
            $admin_message .= '<p><strong>Cliente:</strong> ' . esc_html($nombre) . '</p>';
            $admin_message .= '<p><strong>Email:</strong> ' . esc_html($email) . '</p>';
            $admin_message .= '<p><strong>Teléfono:</strong> ' . esc_html($telefono) . '</p>';
            $admin_message .= '<p><strong>Dirección:</strong> ' . esc_html($direccion) . ($comuna ? ', '.esc_html($comuna) : '') . '</p>';
            $admin_message .= '<p><strong>Fecha de recogida:</strong> ' . date('d/m/Y', strtotime($fecha)) . '</p>';
            $admin_message .= '<p><strong>Total:</strong> $' . number_format($totalPrecio, 0, ',', '.') . '</p>';
            $admin_message .= '<p><a href="' . admin_url('admin.php?page=reserva-lista&action=edit&id=' . $reserva_id) . '">Ver detalles en el panel de administración</a></p>';
            $admin_message .= '</body></html>';
            
            wp_mail($admin_email, $admin_subject, $admin_message, $headers);
        }
    }

    // Redireccionar a la página de éxito
    wp_redirect(add_query_arg(
        'reserva_id', $reserva_id,
        home_url('/reserva-exitosa/')
    ));
    exit;
}
add_action('admin_post_nopriv_procesar_reserva', 'procesar_reserva');
add_action('admin_post_procesar_reserva', 'procesar_reserva');