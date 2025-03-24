<?php
// Evitar acceso directo
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Crea las tablas necesarias para el plugin de reservas.
 * Estructura normalizada para mejor escalabilidad.
 */
function reserva_form_instalar() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // Incluir archivo para usar dbDelta
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

    // Tabla principal de reservas
    $table_reservas = $wpdb->prefix . 'reservas';
    $sql_reservas = "CREATE TABLE $table_reservas (
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
    dbDelta( $sql_reservas );

    // Tabla de productos (modelos de uniformes)
    $table_productos = $wpdb->prefix . 'reservas_productos';
    $sql_productos = "CREATE TABLE $table_productos (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        nombre varchar(100) NOT NULL,
        slug varchar(100) NOT NULL,
        descripcion text,
        imagen_url varchar(255),
        categoria_id mediumint(9),
        activo tinyint(1) DEFAULT 1,
        fecha_creacion datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY slug (slug)
    ) $charset_collate;";
    dbDelta( $sql_productos );

    // Tabla de categorías de productos
    $table_categorias = $wpdb->prefix . 'reservas_categorias';
    $sql_categorias = "CREATE TABLE $table_categorias (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        nombre varchar(100) NOT NULL,
        slug varchar(100) NOT NULL,
        descripcion text,
        PRIMARY KEY (id),
        UNIQUE KEY slug (slug)
    ) $charset_collate;";
    dbDelta( $sql_categorias );

    // Tabla de tallas disponibles
    $table_tallas = $wpdb->prefix . 'reservas_tallas';
    $sql_tallas = "CREATE TABLE $table_tallas (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        talla varchar(20) NOT NULL,
        orden smallint(5) NOT NULL DEFAULT 0,
        categoria_id mediumint(9),
        PRIMARY KEY (id)
    ) $charset_collate;";
    dbDelta( $sql_tallas );

    // Tabla de precios (relaciona productos con tallas)
    $table_precios = $wpdb->prefix . 'reservas_precios';
    $sql_precios = "CREATE TABLE $table_precios (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        producto_id mediumint(9) NOT NULL,
        talla_id mediumint(9) NOT NULL,
        precio decimal(10,2) NOT NULL DEFAULT '0.00',
        disponible tinyint(1) DEFAULT 1,
        fecha_actualizacion datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY producto_talla (producto_id, talla_id)
    ) $charset_collate;";
    dbDelta( $sql_precios );

    // Tabla de items de reserva (detalle de cada reserva)
    $table_items = $wpdb->prefix . 'reservas_items';
    $sql_items = "CREATE TABLE $table_items (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        reserva_id mediumint(9) NOT NULL,
        producto_id mediumint(9) NOT NULL,
        talla_id mediumint(9) NOT NULL,
        cantidad smallint(5) NOT NULL DEFAULT 1,
        precio_unitario decimal(10,2) NOT NULL DEFAULT '0.00',
        subtotal decimal(10,2) NOT NULL DEFAULT '0.00',
        PRIMARY KEY (id),
        KEY reserva_id (reserva_id)
    ) $charset_collate;";
    dbDelta( $sql_items );

    // Migrar datos existentes si la tabla antigua existe
    $table_old = $wpdb->prefix . 'reservas';
    $check_table = $wpdb->get_var("SHOW TABLES LIKE '$table_old'");
    
    if ($check_table) {
        // Solo verificamos si hay datos y continuamos sin error
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_old");
        if ($count > 0) {
            // Hay datos, pero la migración se hará manualmente después
            // para evitar el error fatal durante la activación
            update_option('reserva_form_migrar_datos', 'pendiente');
        }
    } else {
        // Insertar datos iniciales
        insertar_datos_iniciales();
    }
}

/**
 * Migra los datos de la tabla antigua a la nueva estructura
 * Esta función debe ser llamada manualmente después de la activación
 */
function migrar_datos_antiguos() {
    global $wpdb;
    
    // Verificar si la tabla antigua existe - Corregir nombre de tabla
    $tabla_antigua = $wpdb->prefix . 'reservas';
    
    // Verificar si hay datos en la tabla de items - si hay, no necesitamos migrar
    $tabla_items = $wpdb->prefix . 'reservas_items';
    $items_tiene_datos = false;
    
    if ($wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(1) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
        DB_NAME,
        $tabla_items
    ))) {
        $items_tiene_datos = $wpdb->get_var("SELECT COUNT(*) FROM $tabla_items") > 0;
    }
    
    if ($items_tiene_datos) {
        error_log('Migración: La tabla de items ya tiene datos, no es necesario migrar');
        update_option('reserva_form_migrar_datos', 'datos-existentes');
        return true;
    }
    
    $tabla_existe = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(1) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
        DB_NAME,
        $tabla_antigua
    ));
    
    if (!$tabla_existe) {
        // Intentar con otro nombre de tabla antigua
        $tabla_antigua = $wpdb->prefix . 'reserva_reservaciones';
        
        $tabla_existe = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(1) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
            DB_NAME,
            $tabla_antigua
        ));
        
        if (!$tabla_existe) {
            error_log('Migración: No se encontró ninguna tabla antigua para migrar datos');
            update_option('reserva_form_migrar_datos', 'no-tabla-antigua');
            return false;
        }
    }
    
    // Log para depuración
    error_log('Migración: Usando tabla antigua: ' . $tabla_antigua);
    
    // Obtener todas las reservas de la tabla antigua
    $reservas_antiguas = $wpdb->get_results("SELECT * FROM $tabla_antigua");
    if (empty($reservas_antiguas)) {
        error_log('Migración: No hay datos en la tabla antigua');
        update_option('reserva_form_migrar_datos', 'sin-datos');
        return false;
    }
    
    // Tablas nuevas
    $tabla_reservas = $wpdb->prefix . 'reservas';
    $tabla_productos = $wpdb->prefix . 'reservas_productos';
    $tabla_tallas = $wpdb->prefix . 'reservas_tallas';
    
    // Verificar que existan las tablas nuevas
    $tablas_nuevas_existen = true;
    foreach ([$tabla_reservas, $tabla_productos, $tabla_tallas] as $tabla) {
        $existe = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(1) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
            DB_NAME,
            $tabla
        ));
        if (!$existe) {
            error_log('Migración: La tabla ' . $tabla . ' no existe');
            $tablas_nuevas_existen = false;
            break;
        }
    }
    
    if (!$tablas_nuevas_existen) {
        update_option('reserva_form_migrar_datos', 'error-tablas-nuevas');
        return false;
    }
    
    // Contador de registros migrados
    $reservas_migradas = 0;
    $productos_migrados = 0;
    
    // Mapa para asociar nombres de productos a IDs de productos nuevos
    $mapa_productos = [];
    $productos_existentes = $wpdb->get_results("SELECT id, nombre FROM $tabla_productos");
    foreach ($productos_existentes as $producto) {
        $mapa_productos[$producto->nombre] = $producto->id;
    }
    
    // Mapa para asociar tallas a IDs de tallas nuevas
    $mapa_tallas = [];
    $tallas_existentes = $wpdb->get_results("SELECT id, talla FROM $tabla_tallas");
    foreach ($tallas_existentes as $talla) {
        $mapa_tallas[$talla->talla] = $talla->id;
    }
    
    // Migrar cada reserva
    foreach ($reservas_antiguas as $reserva_antigua) {
        // Verificar si esta reserva ya fue migrada
        $reserva_existe = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $tabla_reservas WHERE id = %d",
            $reserva_antigua->id
        ));
        
        if ($reserva_existe) {
            continue; // Ya fue migrada, saltar a la siguiente
        }
        
        // Obtener campos de la reserva antigua con manejo de diferentes nombres de campos
        $fecha_recogida = isset($reserva_antigua->fecha_recogida) ? $reserva_antigua->fecha_recogida : 
                         (isset($reserva_antigua->fecha) ? $reserva_antigua->fecha : date('Y-m-d'));
                         
        $fecha_creacion = isset($reserva_antigua->fecha_creacion) ? $reserva_antigua->fecha_creacion : 
                         (isset($reserva_antigua->fecha_registro) ? $reserva_antigua->fecha_registro : current_time('mysql'));
        
        // Preparar los datos para la nueva reserva con comprobación de campos existentes
        $datos_reserva = [
            'id' => $reserva_antigua->id,
            'nombre' => isset($reserva_antigua->nombre) ? $reserva_antigua->nombre : '',
            'direccion' => isset($reserva_antigua->direccion) ? $reserva_antigua->direccion : '',
            'email' => isset($reserva_antigua->email) ? $reserva_antigua->email : '',
            'telefono' => isset($reserva_antigua->telefono) ? $reserva_antigua->telefono : '',
            'fecha' => $fecha_recogida,
            'total' => isset($reserva_antigua->total) ? $reserva_antigua->total : 0,
            'fecha_registro' => $fecha_creacion,
            'comuna' => isset($reserva_antigua->comuna) ? $reserva_antigua->comuna : '',
            'estado' => 'pendiente'
        ];
        
        // Log para depuración
        error_log('Migración: Insertando reserva ID: ' . $reserva_antigua->id);
        
        // Insertar la reserva
        $resultado = $wpdb->insert($tabla_reservas, $datos_reserva);
        if (!$resultado) {
            error_log('Error al migrar reserva #' . $reserva_antigua->id . ': ' . $wpdb->last_error);
            continue;
        }
        
        $reservas_migradas++;
        
        // Procesar los productos (si existen)
        $productos_data = null;
        
        // Intentar obtener productos desde diferentes campos
        if (!empty($reserva_antigua->productos)) {
            $productos_data = $reserva_antigua->productos;
        } elseif (!empty($reserva_antigua->product_details)) {
            $productos_data = $reserva_antigua->product_details;
        }
        
        if ($productos_data) {
            $productos_json = json_decode($productos_data, true);
            
            if (is_array($productos_json) && !empty($productos_json)) {
                foreach ($productos_json as $producto) {
                    // Verificar estructura del producto
                    if (!isset($producto['producto']) || !isset($producto['talla'])) {
                        error_log('Estructura de producto inválida para reserva #' . $reserva_antigua->id);
                        continue;
                    }
                    
                    // Verificar si el producto existe, si no, crearlo
                    $producto_nombre = $producto['producto'];
                    $producto_id = $mapa_productos[$producto_nombre] ?? null;
                    
                    if (!$producto_id) {
                        // Crear producto nuevo
                        $slug = sanitize_title($producto_nombre);
                        $wpdb->insert(
                            $tabla_productos,
                            [
                                'nombre' => $producto_nombre,
                                'slug' => $slug,
                                'descripcion' => $producto_nombre,
                                'categoria_id' => 1 // Categoría por defecto
                            ]
                        );
                        $producto_id = $wpdb->insert_id;
                        $mapa_productos[$producto_nombre] = $producto_id;
                        
                        // Log de nuevo producto
                        error_log('Migración: Nuevo producto creado: ' . $producto_nombre . ' (ID: ' . $producto_id . ')');
                    }
                    
                    // Verificar si la talla existe, si no, crearla
                    $talla_nombre = $producto['talla'];
                    $talla_id = $mapa_tallas[$talla_nombre] ?? null;
                    
                    if (!$talla_id) {
                        // Crear talla nueva
                        $wpdb->insert(
                            $tabla_tallas,
                            [
                                'talla' => $talla_nombre,
                                'orden' => 99, // Orden por defecto
                                'categoria_id' => 1 // Categoría por defecto
                            ]
                        );
                        $talla_id = $wpdb->insert_id;
                        $mapa_tallas[$talla_nombre] = $talla_id;
                    }
                    
                    // Insertar el item de la reserva
                    $cantidad = intval($producto['cantidad']);
                    $precio_unitario = isset($producto['precio']) ? intval($producto['precio']) : 0;
                    $subtotal = isset($producto['subtotal']) ? intval($producto['subtotal']) : ($precio_unitario * $cantidad);
                    
                    $wpdb->insert(
                        $tabla_items,
                        [
                            'reserva_id' => $reserva_antigua->id,
                            'producto_id' => $producto_id,
                            'talla_id' => $talla_id,
                            'cantidad' => $cantidad,
                            'precio_unitario' => $precio_unitario,
                            'subtotal' => $subtotal
                        ]
                    );
                    
                    $productos_migrados++;
                }
            }
        }
    }
    
    // Guardar estadísticas y marcar como completado
    update_option('reserva_form_migrados_reservas', $reservas_migradas);
    update_option('reserva_form_migrados_productos', $productos_migrados);
    update_option('reserva_form_migrar_datos', 'completado');
    
    error_log("Migración completada: $reservas_migradas reservas y $productos_migrados productos migrados");
    
    return true;
}

/**
 * Inserta datos iniciales para las categorías, productos y tallas
 */
function insertar_datos_iniciales() {
    global $wpdb;
    
    // Insertar categorías
    $categoria_id = $wpdb->insert(
        $wpdb->prefix . 'reservas_categorias',
        array(
            'nombre' => 'Uniformes Escolares',
            'slug' => 'uniformes-escolares',
            'descripcion' => 'Uniformes para el colegio Alianza Francesa'
        ),
        array('%s', '%s', '%s')
    );
    
    // ID de la categoría recién insertada
    $categoria_id = $wpdb->insert_id;
    
    // Insertar productos iniciales (los 3 productos actuales)
    $productos = array(
        array(
            'nombre' => 'Pantalón Buzo Alianza Francesa',
            'slug' => 'pantalon-buzo',
            'descripcion' => 'Pantalón de buzo oficial del colegio',
            'imagen_url' => 'https://www.scolari.cl/wp-content/uploads/2025/03/SPF0093-Mejorado-NR-Editar-scaled-1-e1741710539135.jpg',
            'categoria_id' => $categoria_id
        ),
        array(
            'nombre' => 'Polera Deporte M/C Alianza Francesa',
            'slug' => 'polera',
            'descripcion' => 'Polera de deporte manga corta oficial del colegio',
            'imagen_url' => 'https://www.scolari.cl/wp-content/uploads/2025/03/AlianzaFrancesa_Polera-Educacion-Fisica-Unisex-Manga-Corta-1-scaled-1-e1741711369724.jpg',
            'categoria_id' => $categoria_id
        ),
        array(
            'nombre' => 'Polerón Buzo Alianza Francesa',
            'slug' => 'poleron',
            'descripcion' => 'Polerón de buzo oficial del colegio',
            'imagen_url' => 'https://www.scolari.cl/wp-content/uploads/2025/03/SPF0093-Mejorado-NR-Editar-scaled-1-e1741710539135.jpg',
            'categoria_id' => $categoria_id
        ),
    );
    
    // Insertar productos
    foreach ($productos as $producto) {
        $wpdb->insert(
            $wpdb->prefix . 'reservas_productos',
            $producto,
            array('%s', '%s', '%s', '%s', '%d')
        );
    }
    
    // Insertar tallas
    $tallas = array("4", "6", "8", "10", "12", "14", "XS", "S", "M", "L", "XL");
    $tallas_ids = array();
    
    foreach ($tallas as $index => $talla) {
        $wpdb->insert(
            $wpdb->prefix . 'reservas_tallas',
            array(
                'talla' => $talla,
                'orden' => $index,
                'categoria_id' => $categoria_id
            ),
            array('%s', '%d', '%d')
        );
        $tallas_ids[$talla] = $wpdb->insert_id;
    }
    
    // Obtener IDs de productos insertados
    $pantalon_id = $wpdb->get_var("SELECT id FROM " . $wpdb->prefix . "reservas_productos WHERE slug = 'pantalon-buzo'");
    $polera_id = $wpdb->get_var("SELECT id FROM " . $wpdb->prefix . "reservas_productos WHERE slug = 'polera'");
    $poleron_id = $wpdb->get_var("SELECT id FROM " . $wpdb->prefix . "reservas_productos WHERE slug = 'poleron'");
    
    // Precios para pantalón buzo
    $precios_pantalon = array(
        "4" => 15000, "6" => 15000, "8" => 15500, "10" => 15500, "12" => 16000, "14" => 16000,
        "XS" => 16500, "S" => 16500, "M" => 16500, "L" => 16500, "XL" => 16500
    );
    
    // Precios para polera
    $precios_polera = array(
        "4" => 13500, "6" => 13500, "8" => 14000, "10" => 14000, "12" => 14500, "14" => 14500,
        "XS" => 15000, "S" => 15000, "M" => 15000, "L" => 15000, "XL" => 15000
    );
    
    // Precios para polerón
    $precios_poleron = array(
        "4" => 24500, "6" => 24500, "8" => 25500, "10" => 25500, "12" => 26500, "14" => 26500,
        "XS" => 27500, "S" => 27500, "M" => 27500, "L" => 27500, "XL" => 27500
    );
    
    // Insertar precios para pantalón
    foreach ($precios_pantalon as $talla => $precio) {
        if (isset($tallas_ids[$talla])) {
            $wpdb->insert(
                $wpdb->prefix . 'reservas_precios',
                array(
                    'producto_id' => $pantalon_id,
                    'talla_id' => $tallas_ids[$talla],
                    'precio' => $precio,
                ),
                array('%d', '%d', '%f')
            );
        }
    }
    
    // Insertar precios para polera
    foreach ($precios_polera as $talla => $precio) {
        if (isset($tallas_ids[$talla])) {
            $wpdb->insert(
                $wpdb->prefix . 'reservas_precios',
                array(
                    'producto_id' => $polera_id,
                    'talla_id' => $tallas_ids[$talla],
                    'precio' => $precio,
                ),
                array('%d', '%d', '%f')
            );
        }
    }
    
    // Insertar precios para polerón
    foreach ($precios_poleron as $talla => $precio) {
        if (isset($tallas_ids[$talla])) {
            $wpdb->insert(
                $wpdb->prefix . 'reservas_precios',
                array(
                    'producto_id' => $poleron_id,
                    'talla_id' => $tallas_ids[$talla],
                    'precio' => $precio,
                ),
                array('%d', '%d', '%f')
            );
        }
    }
}
