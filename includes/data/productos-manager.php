<?php
/**
 * Gestión de productos, tallas y precios
 *
 * @package ReservaForm
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Obtiene los productos activos, opcionalmente filtrados por categoría
 * 
 * @param int $categoria_id ID de la categoría (opcional)
 * @return array Array de objetos producto
 */
function reserva_get_productos($categoria_id = null) {
    global $wpdb;
    
    $sql = "SELECT * FROM {$wpdb->prefix}reservas_productos WHERE activo = 1";
    
    if ($categoria_id) {
        $sql .= $wpdb->prepare(" AND categoria_id = %d", $categoria_id);
    }
    
    $sql .= " ORDER BY nombre ASC";
    
    return $wpdb->get_results($sql);
}

/**
 * Obtiene un producto por su slug
 * 
 * @param string $slug Slug único del producto
 * @return object|false Objeto producto o false si no existe
 */
function reserva_get_producto_by_slug($slug) {
    global $wpdb;
    
    return $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}reservas_productos WHERE slug = %s AND activo = 1",
            $slug
        )
    );
}

/**
 * Obtiene las tallas disponibles, opcionalmente filtradas por categoría
 * 
 * @param int $categoria_id ID de la categoría (opcional)
 * @return array Array de objetos talla
 */
function reserva_get_tallas($categoria_id = null) {
    global $wpdb;
    
    $sql = "SELECT * FROM {$wpdb->prefix}reservas_tallas";
    
    if ($categoria_id) {
        $sql .= $wpdb->prepare(" WHERE categoria_id = %d", $categoria_id);
    }
    
    $sql .= " ORDER BY orden ASC";
    
    return $wpdb->get_results($sql);
}

/**
 * Obtiene los precios disponibles para un producto por su ID
 * 
 * @param int $producto_id ID del producto
 * @return array Array asociativo de tallas y precios
 */
function reserva_get_precios_producto($producto_id) {
    global $wpdb;
    
    $precios = array();
    
    $resultados = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT p.precio, t.talla as talla_nombre 
             FROM {$wpdb->prefix}reservas_precios p
             JOIN {$wpdb->prefix}reservas_tallas t ON p.talla_id = t.id
             WHERE p.producto_id = %d AND p.disponible = 1
             ORDER BY t.orden ASC",
            $producto_id
        )
    );
    
    foreach ($resultados as $resultado) {
        $precios[$resultado->talla_nombre] = floatval($resultado->precio);
    }
    
    return $precios;
}

/**
 * Obtiene todos los productos con sus precios para usar en el formulario
 * 
 * @return array Array asociativo de productos con sus precios
 */
function reserva_get_productos_con_precios() {
    global $wpdb;
    
    $productos = reserva_get_productos();
    $resultado = array();
    
    foreach ($productos as $producto) {
        $precios = reserva_get_precios_producto($producto->id);
        
        if (!empty($precios)) {
            // Usar el slug como clave del array para que JavaScript pueda acceder fácilmente
            $resultado[$producto->slug] = array(
                'id' => $producto->id,
                'nombre' => $producto->nombre,
                'slug' => $producto->slug,
                'descripcion' => $producto->descripcion,
                'imagen_url' => $producto->imagen_url,
                'img' => $producto->imagen_url,
                'precios' => $precios,
                'categoria_id' => $producto->categoria_id
            );
        }
    }
    
    if (empty($resultado)) {
        error_log('No se encontraron productos con precios en la base de datos');
        error_log('Productos encontrados: ' . count($productos));
        
        foreach ($productos as $producto) {
            error_log('Producto: ' . $producto->nombre . ', Imagen: ' . $producto->imagen_url);
        }
    }
    
    return $resultado;
}

/**
 * Obtiene la información de una talla por su ID
 * 
 * @param int $talla_id ID de la talla
 * @return object|null Objeto talla o null si no existe
 */
function reserva_get_talla_by_id($talla_id) {
    global $wpdb;
    
    return $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}reservas_tallas WHERE id = %d",
            $talla_id
        )
    );
}

/**
 * Obtiene el ID de la talla por su nombre
 * 
 * @param string $talla_nombre Nombre de la talla
 * @return int|null ID de la talla o null si no existe
 */
function reserva_get_talla_id_by_nombre($talla_nombre) {
    global $wpdb;
    
    return $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}reservas_tallas WHERE talla = %s",
            $talla_nombre
        )
    );
}

/**
 * Guarda una nueva reserva con sus items
 * 
 * @param array $reserva_data Datos de la reserva
 * @param array $items_data Array de items de la reserva
 * @return int|false ID de la reserva o false si hay error
 */
function reserva_guardar_reserva($reserva_data, $items_data) {
    global $wpdb;
    
    // Registrar datos para depuración
    error_log('Intentando guardar reserva con datos: ' . json_encode($reserva_data));
    error_log('Items para la reserva: ' . json_encode($items_data));
    
    // Verificar que la tabla exista con la estructura correcta
    $table_name = $wpdb->prefix . 'reservas';
    $tabla_existe = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(1) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
        DB_NAME,
        $table_name
    ));
    
    if (!$tabla_existe) {
        error_log('La tabla ' . $table_name . ' no existe. Intentando crearla...');
        
        // Crear la tabla con la estructura correcta
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            productos text NOT NULL,
            product_details LONGTEXT NULL,
            nombre varchar(100) NOT NULL,
            direccion varchar(255) NOT NULL,
            email varchar(100) NOT NULL,
            telefono varchar(50),
            fecha date NOT NULL,
            total decimal(10,2) NOT NULL DEFAULT '0.00',
            fecha_registro datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            comuna varchar(100),
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        // Verificar si se creó correctamente
        $tabla_existe = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(1) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
            DB_NAME,
            $table_name
        ));
        
        if (!$tabla_existe) {
            error_log('Error: No se pudo crear la tabla ' . $table_name);
            return false;
        }
        
        error_log('Tabla ' . $table_name . ' creada correctamente');
    }
    
    // Crear una matriz para los productos en formato JSON
    $productos_json = array();
    foreach ($items_data as $item) {
        // Obtener nombres de producto y talla
        $producto_nombre = $wpdb->get_var(
            $wpdb->prepare("SELECT nombre FROM {$wpdb->prefix}reservas_productos WHERE id = %d", $item['producto_id'])
        );
        
        $talla_nombre = $wpdb->get_var(
            $wpdb->prepare("SELECT talla FROM {$wpdb->prefix}reservas_tallas WHERE id = %d", $item['talla_id'])
        );
        
        $productos_json[] = array(
            'producto' => $producto_nombre ?? 'Producto #' . $item['producto_id'],
            'talla' => $talla_nombre ?? 'Talla #' . $item['talla_id'],
            'cantidad' => $item['cantidad'],
            'precio' => $item['precio_unitario'],
            'subtotal' => $item['precio_unitario'] * $item['cantidad']
        );
    }
    
    // Preparar datos exactamente según la estructura de la tabla
    $datos_reserva = array(
        'productos' => json_encode($productos_json),
        'product_details' => json_encode($productos_json),
        'nombre' => $reserva_data['nombre'],
        'direccion' => isset($reserva_data['direccion']) ? $reserva_data['direccion'] : '',
        'email' => $reserva_data['email'],
        'telefono' => $reserva_data['telefono'],
        'fecha' => $reserva_data['fecha'] ?? date('Y-m-d'),
        'total' => $reserva_data['total'],
        'fecha_registro' => current_time('mysql'),
        'comuna' => isset($reserva_data['comuna']) ? $reserva_data['comuna'] : ''
    );
    
    // Registrar la estructura final de datos antes de la inserción
    error_log('Datos finales para inserción: ' . json_encode($datos_reserva));
    
    // Intentar insertar en la tabla
    error_log('Intentando insertar en la tabla ' . $table_name);
    
    // Insertar en la tabla 'reservas'
    $result = $wpdb->insert(
        $table_name,
        $datos_reserva,
        array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s')
    );
    
    if ($result === false) {
        error_log('Error al insertar reserva en tabla: ' . $wpdb->last_error);
        error_log('Última consulta: ' . $wpdb->last_query);
        return false;
    }
    
    $reserva_id = $wpdb->insert_id;
    error_log('Reserva insertada correctamente con ID: ' . $reserva_id);
    
    return $reserva_id;
}

/**
 * Verifica y repara datos automáticamente si es necesario
 * 
 * Esta función puede ser llamada en diferentes momentos para asegurar 
 * que los datos necesarios existan.
 * 
 * @return bool Indica si fue necesario reparar datos
 */
function reserva_verificar_y_reparar_datos() {
    global $wpdb;
    
    $reparado = false;
    
    // 1. Verificar si hay productos
    $productos_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}reservas_productos");
    
    if ($productos_count == 0) {
        // No hay productos, ejecutar la instalación de datos iniciales
        if (function_exists('insertar_datos_iniciales')) {
            insertar_datos_iniciales();
            $reparado = true;
        }
    }
    
    // 2. Verificar si hay tallas
    $tallas_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}reservas_tallas");
    
    if ($tallas_count == 0 && function_exists('insertar_datos_iniciales')) {
        insertar_datos_iniciales();
        $reparado = true;
    }
    
    // 3. Verificar si hay precios para todos los productos
    $productos = $wpdb->get_results("
        SELECT p.id, COUNT(pr.id) as precios_count 
        FROM {$wpdb->prefix}reservas_productos p
        LEFT JOIN {$wpdb->prefix}reservas_precios pr ON p.id = pr.producto_id
        WHERE p.activo = 1
        GROUP BY p.id
    ");
    
    foreach ($productos as $producto) {
        if ($producto->precios_count == 0) {
            // Este producto no tiene precios configurados
            update_option('reserva_form_datos_error', 'si');
            $reparado = true;
        }
    }
    
    return $reparado;
}

/**
 * Obtiene los datos completos de una reserva
 * 
 * @param int $reserva_id ID de la reserva
 * @return array|false Datos de la reserva o false si no existe
 */
function reserva_get_reserva_completa($reserva_id) {
    global $wpdb;
    
    // Obtener datos de la reserva
    $reserva = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}reservas WHERE id = %d",
            $reserva_id
        ),
        ARRAY_A
    );
    
    if (!$reserva) {
        return false;
    }
    
    // Mapa de imágenes de productos
    $mapa_productos = array(
        'Pantalón Buzo Alianza Francesa' => plugins_url('assets/images/poleronypantalon.jpg', dirname(dirname(__FILE__))),
        'Polera Deporte M/C Alianza Francesa' => plugins_url('assets/images/polera.jpg', dirname(dirname(__FILE__))),
        'Polerón Buzo Alianza Francesa' => plugins_url('assets/images/poleronypantalon.jpg', dirname(dirname(__FILE__))),
    );
    
    // Placeholder para imagen por defecto
    $placeholder_img = plugins_url('assets/images/product-placeholder.jpg', dirname(dirname(__FILE__)));
    
    // Procesar productos desde JSON
    $items = array();
    $detalles = json_decode($reserva['productos'], true);
    
    if (is_array($detalles) && !empty($detalles)) {
        foreach ($detalles as $detalle) {
            // Determinar la URL de la imagen
            $imagen_url = isset($detalle['img']) ? $detalle['img'] : 
                         ($mapa_productos[$detalle['producto']] ?? $placeholder_img);
            
            $precio_unitario = isset($detalle['precio']) ? intval($detalle['precio']) : 0;
            $cantidad = intval($detalle['cantidad']);
            $subtotal = isset($detalle['subtotal']) ? intval($detalle['subtotal']) : ($precio_unitario * $cantidad);
            
            $items[] = array(
                'producto' => $detalle['producto'],
                'talla' => $detalle['talla'],
                'cantidad' => $cantidad,
                'precio_unitario' => $precio_unitario,
                'subtotal' => $subtotal,
                'imagen_url' => $imagen_url
            );
        }
    }
    
    // Agregar items a la reserva
    $reserva['items'] = $items;
    
    return $reserva;
} 