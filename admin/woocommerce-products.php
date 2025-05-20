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
        'Productos WooCommerce para Reservas',
        'Productos WooCommerce',
        'manage_options',
        'reserva-woocommerce',
        'reserva_woocommerce_admin_page'
    );
}

// Registrar scripts y estilos para la página de admin
function reserva_woocommerce_admin_scripts() {
    $screen = get_current_screen();
    error_log('Screen ID: ' . ($screen ? $screen->id : 'null'));
    
    // Comprobar si estamos en la página de WooCommerce del plugin
    if (isset($_GET['page']) && $_GET['page'] === 'reserva-woocommerce') {
        error_log('Cargando scripts de WooCommerce para Reservas');
        
        // Asegurar que jQuery está cargado
        wp_enqueue_script('jquery');
        
        // Cargar estilos y scripts
        wp_enqueue_style('reserva-admin-styles', plugin_dir_url(__FILE__) . '../assets/css/admin-style.css', array(), '1.0.1');
        wp_enqueue_script('reserva-wc-admin', plugin_dir_url(__FILE__) . '../assets/js/wc-admin.js', array('jquery'), '1.0.1', true);
        
        // Pasar variables a JavaScript
        wp_localize_script('reserva-wc-admin', 'reservaWC', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('reserva_wc_ajax_nonce'),
            'debug' => true
        ));
        
        // Debug - Verificar que se están cargando los scripts
        error_log('Scripts y estilos de WooCommerce cargados');
    }
}
add_action('admin_menu', 'reserva_register_woocommerce_products_menu', 21);
add_action('admin_enqueue_scripts', 'reserva_woocommerce_admin_scripts');

/**
 * Add a direct link to WooCommerce products page in the plugin action links
 */
function reserva_add_action_links($links) {
    $custom_links = array(
        '<a href="' . admin_url('admin.php?page=reserva-woocommerce') . '">Configurar Productos WC</a>',
    );
    return array_merge($custom_links, $links);
}
add_filter('plugin_action_links_reserva-form/reserva-form.php', 'reserva_add_action_links');

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
        <div class="wrap reserva-admin-wrap">
            <h1 class="wp-heading-inline">Productos WooCommerce para Reservas</h1>
            <div class="notice notice-error">
                <p><strong>Error:</strong> WooCommerce no está activo. Por favor, active WooCommerce para usar esta funcionalidad.</p>
            </div>
        </div>
        <?php
        return;
    }

    // Process form submissions
    error_log('Procesando envío de formulario');
    
    // Depurar todos los datos POST
    error_log('Contenido completo de POST: ' . print_r($_POST, true));
    error_log('Contenido completo de REQUEST: ' . print_r($_REQUEST, true));
    
    if (isset($_POST['action'])) {
        error_log('Action presente: ' . $_POST['action']);
    } else {
        error_log('Action no encontrado en POST');
    }
    
    if (isset($_POST['reserva_wc_nonce'])) {
        error_log('Nonce presente: ' . $_POST['reserva_wc_nonce']);
        $nonce_valid = wp_verify_nonce($_POST['reserva_wc_nonce'], 'reserva_update_wc_products');
        error_log('Nonce válido: ' . ($nonce_valid ? 'Sí' : 'No'));
    } else {
        error_log('Nonce no encontrado en POST');
    }
    
    if (isset($_POST['reservable'])) {
        error_log('Array reservable presente con ' . count($_POST['reservable']) . ' productos seleccionados');
        error_log('Contenido de reservable: ' . print_r($_POST['reservable'], true));
    } else {
        error_log('Array reservable no encontrado en POST');
    }
    
    // Verificar si se ha enviado el formulario
    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // MÉTODO ALTERNATIVO: Procesar directamente el arreglo reservable
        if (isset($_POST['reservable']) && is_array($_POST['reservable'])) {
            // Paso 1: Marcar todos los productos como no reservables
            $args = array(
                'status' => 'publish',
                'limit' => -1,
                'return' => 'ids',
            );
            $all_product_ids = wc_get_products($args);
            error_log('Total de productos WooCommerce: ' . count($all_product_ids));
            
            foreach ($all_product_ids as $product_id) {
                update_post_meta($product_id, '_reservable', 'no');
                error_log("Producto ID {$product_id} marcado como NO reservable");
            }
            
            // Paso 2: Marcar solo los productos seleccionados como reservables
            $updated = 0;
            $selected_count = 0;
            
            foreach ($_POST['reservable'] as $product_id => $value) {
                if ($value === 'yes') {
                    update_post_meta($product_id, '_reservable', 'yes');
                    error_log("Producto ID {$product_id} marcado como reservable");
                    $updated++;
                    $selected_count++;
                }
            }
            
            error_log("Total de productos marcados como reservables: {$selected_count}");
        } else {
            // No hay productos seleccionados, marcar todos como no reservables
            $args = array(
                'status' => 'publish',
                'limit' => -1,
                'return' => 'ids',
            );
            $all_product_ids = wc_get_products($args);
            $updated = 0;
            
            foreach ($all_product_ids as $product_id) {
                $current_value = get_post_meta($product_id, '_reservable', true);
                if ($current_value === 'yes') {
                    update_post_meta($product_id, '_reservable', 'no');
                    $updated++;
                }
            }
            
            error_log("No hay productos seleccionados, se han desmarcado {$updated} productos");
            $selected_count = 0;
        }
        
        if ($updated > 0) {
            echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(_n('%d producto actualizado correctamente.', '%d productos actualizados correctamente.', $updated, 'reserva-form'), $updated) . '</p></div>';
        } else {
            echo '<div class="notice notice-info is-dismissible"><p>No se realizaron cambios en los productos.</p></div>';
        }
        
        if ($selected_count === 0) {
            echo '<div class="notice notice-warning is-dismissible"><p><strong>Advertencia:</strong> No hay productos seleccionados para reserva. Asegúrese de marcar al menos un producto para que esté disponible en el formulario de reservas.</p></div>';
        }
    }

    // Parámetros para la paginación y filtrado
    $paged = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
    $per_page = 20; // Productos por página
    $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    $category = isset($_GET['category']) ? absint($_GET['category']) : 0;
    $reservable_filter = isset($_GET['reservable_filter']) ? sanitize_text_field($_GET['reservable_filter']) : '';

    // Preparar argumentos para la consulta de productos
    $args = array(
        'status' => 'publish',
        'limit' => $per_page,
        'page' => $paged,
    );

    // Añadir filtro por categoría si está seleccionado
    if ($category > 0) {
        // Usar tax_query para filtrar por categoría de forma más precisa
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'product_cat',
                'field'    => 'term_id',
                'terms'    => $category,
            ),
        );
    }

    // Añadir búsqueda si hay término (buscar en título y SKU)
    if (!empty($search)) {
        // Usar el parámetro de búsqueda estándar de WooCommerce
        $args['s'] = $search;
        
        // También buscar por SKU
        $args['meta_query'] = array(
            'relation' => 'OR',
            array(
                'key'     => '_sku',
                'value'   => $search,
                'compare' => 'LIKE'
            )
        );
    }
    
    // Filtrar por productos reservables o no reservables
    $filtered_by_reservable = false;
    if ($reservable_filter === 'yes' || $reservable_filter === 'no') {
        $filtered_by_reservable = true;
    }

    // Obtener productos según los filtros
    $products = wc_get_products($args);
    
    // Filtrar productos por estado reservable si es necesario
    if ($filtered_by_reservable) {
        $filtered_products = array();
        foreach ($products as $product) {
            $product_id = $product->get_id();
            $is_reservable = get_post_meta($product_id, '_reservable', true) === 'yes';
            
            if (($reservable_filter === 'yes' && $is_reservable) || 
                ($reservable_filter === 'no' && !$is_reservable)) {
                $filtered_products[] = $product;
            }
        }
        $products = $filtered_products;
    }

    // Obtener el total de productos para la paginación
    // Si estamos filtrando por reservable, necesitamos contar manualmente
    if ($filtered_by_reservable) {
        $all_products = wc_get_products(array(
            'status' => 'publish',
            'limit' => -1,
        ));
        
        $total_products = 0;
        foreach ($all_products as $product) {
            $product_id = $product->get_id();
            $is_reservable = get_post_meta($product_id, '_reservable', true) === 'yes';
            
            if (($reservable_filter === 'yes' && $is_reservable) || 
                ($reservable_filter === 'no' && !$is_reservable)) {
                $total_products++;
            }
        }
    } else {
        $total_products_args = $args;
        $total_products_args['limit'] = -1;
        $total_products_args['return'] = 'ids';
        $total_products = count(wc_get_products($total_products_args));
    }
    
    $total_pages = ceil($total_products / $per_page);

    // Obtener categorías de productos para el filtro
    $product_categories = get_terms(array(
        'taxonomy' => 'product_cat',
        'hide_empty' => true,
    ));
    ?>
    <div class="wrap reserva-admin-wrap reserva-wc-products">
        <h1 class="wp-heading-inline">Productos WooCommerce para Reservas</h1>
        <hr class="wp-header-end">

        <div class="reserva-admin-header">
            <div class="reserva-admin-header-info">
                <p class="reserva-admin-description">Seleccione los productos de WooCommerce que estarán disponibles en el formulario de reservas. Los productos marcados como "Disponible para reserva" aparecerán en el formulario de reservas.</p>
            </div>
            <div class="reserva-admin-header-actions">
                <button type="submit" form="reserva-products-form" class="button button-primary">Guardar cambios</button>
            </div>
        </div>

        <div class="reserva-admin-content">
            <!-- Instrucciones -->
            <div class="reserva-admin-filters">
                <p><strong>Instrucciones:</strong> Marque las casillas de los productos que desea habilitar para reserva. Los productos marcados aparecerán en el formulario de reservas.</p>
                
                <!-- Filtros y búsqueda -->
                <div class="reserva-search-filters">
                    <form method="get" class="search-filter-form">
                        <input type="hidden" name="page" value="reserva-woocommerce">
                        
                        <!-- Búsqueda -->
                        <div class="search-box">
                            <label for="product-search" class="screen-reader-text">Buscar productos:</label>
                            <input type="search" id="product-search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Buscar productos...">
                            <input type="submit" id="search-submit" class="button" value="Buscar">
                        </div>
                        
                        <!-- Filtro de categorías -->
                        <div class="category-filter">
                            <label for="category-filter">Filtrar por categoría:</label>
                            <select name="category" id="category-filter">
                                <option value="0">Todas las categorías</option>
                                <?php foreach ($product_categories as $cat) : ?>
                                    <option value="<?php echo esc_attr($cat->term_id); ?>" <?php selected($category, $cat->term_id); ?>>
                                        <?php echo esc_html($cat->name); ?> (<?php echo esc_html($cat->count); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Filtro de productos reservables -->
                        <div class="reservable-filter">
                            <label for="reservable-filter">Estado de reserva:</label>
                            <select name="reservable_filter" id="reservable-filter">
                                <option value="" <?php selected($reservable_filter, ''); ?>>Todos</option>
                                <option value="yes" <?php selected($reservable_filter, 'yes'); ?>>Reservables</option>
                                <option value="no" <?php selected($reservable_filter, 'no'); ?>>No reservables</option>
                            </select>
                        </div>
                        
                        <input type="submit" class="button" value="Filtrar">
                        <?php if (!empty($search) || $category > 0 || !empty($reservable_filter)) : ?>
                            <a href="<?php echo admin_url('admin.php?page=reserva-woocommerce'); ?>" class="button">Limpiar filtros</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Formulario de productos -->
            <form method="post" action="" id="reserva-products-form">
                <input type="hidden" name="action" value="update_reservable_status">
                <?php wp_nonce_field('reserva_update_wc_products', 'reserva_wc_nonce'); ?>
                
                <div class="tablenav top">
                    <div class="alignleft actions bulkactions">
                        <label for="bulk-action-selector-top" class="screen-reader-text">Seleccionar acción en lote</label>
                        <select name="bulk_action" id="bulk-action-selector-top">
                            <option value="-1">Acciones en lote</option>
                            <option value="enable">Habilitar para reserva</option>
                            <option value="disable">Deshabilitar para reserva</option>
                        </select>
                        <button type="button" class="button action" id="doaction">Aplicar</button>
                    </div>
                    
                    <div class="tablenav-pages">
                        <?php if ($total_pages > 1) : ?>
                            <span class="displaying-num"><?php echo sprintf(_n('%s producto', '%s productos', $total_products, 'reserva-form'), number_format_i18n($total_products)); ?></span>
                            <span class="pagination-links">
                                <?php
                                echo paginate_links(array(
                                    'base' => add_query_arg('paged', '%#%'),
                                    'format' => '',
                                    'prev_text' => '&laquo;',
                                    'next_text' => '&raquo;',
                                    'total' => $total_pages,
                                    'current' => $paged,
                                ));
                                ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <br class="clear">
                </div>
                
                <table class="wp-list-table widefat fixed striped products">
                    <thead>
                        <tr>
                            <td class="manage-column column-cb check-column">
                                <input type="checkbox" id="cb-select-all-1">
                                <span class="reserva-checkbox-help">Marcar para habilitar reserva</span>
                            </td>
                            <th scope="col" class="manage-column column-image">Imagen</th>
                            <th scope="col" class="manage-column column-name">Producto</th>
                            <th scope="col" class="manage-column column-sku">SKU</th>
                            <th scope="col" class="manage-column column-price">Precio</th>
                            <th scope="col" class="manage-column column-categories">Categorías</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)) : ?>
                            <tr>
                                <td colspan="6" class="colspanchange">
                                    <p class="no-items">No se encontraron productos que coincidan con los criterios.</p>
                                </td>
                            </tr>
                        <?php else : ?>
                            <?php 
                            // Ordenar productos para que los reservables aparezcan primero
                            $reservable_products = array();
                            $non_reservable_products = array();
                            
                            foreach ($products as $product) {
                                $product_id = $product->get_id();
                                $is_reservable = get_post_meta($product_id, '_reservable', true) === 'yes';
                                
                                if ($is_reservable) {
                                    $reservable_products[] = $product;
                                } else {
                                    $non_reservable_products[] = $product;
                                }
                            }
                            
                            // Combinar los arrays para mostrar primero los reservables
                            $sorted_products = array_merge($reservable_products, $non_reservable_products);
                            
                            foreach ($sorted_products as $product) : 
                                $product_id = $product->get_id();
                                $is_reservable = get_post_meta($product_id, '_reservable', true) === 'yes';
                                $categories = get_the_terms($product_id, 'product_cat');
                                $category_names = array();
                                if (!empty($categories) && !is_wp_error($categories)) {
                                    foreach ($categories as $category) {
                                        $category_names[] = $category->name;
                                    }
                                }
                                
                                // Añadir clase para productos reservables
                                $row_class = $is_reservable ? 'reservable-row' : '';
                            ?>
                                <tr class="<?php echo esc_attr($row_class); ?>">
                                    <th scope="row" class="check-column">
                                        <?php 
                                        // Debug - mostrar datos del producto
                                        error_log("Renderizando checkbox para producto ID: {$product_id}, Reservable: " . ($is_reservable ? 'yes' : 'no')); 
                                        ?>
                                        <input type="checkbox" name="reservable[<?php echo esc_attr($product_id); ?>]" value="yes" <?php checked($is_reservable); ?> id="cb-select-<?php echo esc_attr($product_id); ?>">
                                    </th>
                                    <td class="column-image">
                                        <?php echo $product->get_image(array(50, 50)); ?>
                                    </td>
                                    <td class="column-name">
                                        <strong>
                                            <a href="<?php echo get_edit_post_link($product_id); ?>" class="row-title">
                                                <?php echo esc_html($product->get_name()); ?>
                                            </a>
                                        </strong>
                                        <div class="row-actions">
                                            <span class="edit"><a href="<?php echo get_edit_post_link($product_id); ?>">Editar</a> | </span>
                                            <span class="view"><a href="<?php echo get_permalink($product_id); ?>" target="_blank">Ver</a></span>
                                        </div>
                                    </td>
                                    <td class="column-sku"><?php echo esc_html($product->get_sku()); ?></td>
                                    <td class="column-price"><?php echo $product->get_price_html(); ?></td>
                                    <td class="column-categories">
                                        <?php echo !empty($category_names) ? esc_html(implode(', ', $category_names)) : '—'; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td class="manage-column column-cb check-column">
                                <input type="checkbox" id="cb-select-all-2">
                                <span class="reserva-checkbox-help">Marcar para habilitar reserva</span>
                            </td>
                            <th scope="col" class="manage-column column-image">Imagen</th>
                            <th scope="col" class="manage-column column-name">Producto</th>
                            <th scope="col" class="manage-column column-sku">SKU</th>
                            <th scope="col" class="manage-column column-price">Precio</th>
                            <th scope="col" class="manage-column column-categories">Categorías</th>
                        </tr>
                    </tfoot>
                </table>
                
                <div class="tablenav bottom">
                    <div class="alignleft actions bulkactions">
                        <label for="bulk-action-selector-bottom" class="screen-reader-text">Seleccionar acción en lote</label>
                        <select name="bulk_action_bottom" id="bulk-action-selector-bottom">
                            <option value="-1">Acciones en lote</option>
                            <option value="enable">Habilitar para reserva</option>
                            <option value="disable">Deshabilitar para reserva</option>
                        </select>
                        <button type="button" class="button action" id="doaction2">Aplicar</button>
                    </div>
                    
                    <div class="tablenav-pages">
                        <?php if ($total_pages > 1) : ?>
                            <span class="displaying-num"><?php echo sprintf(_n('%s producto', '%s productos', $total_products, 'reserva-form'), number_format_i18n($total_products)); ?></span>
                            <span class="pagination-links">
                                <?php
                                echo paginate_links(array(
                                    'base' => add_query_arg('paged', '%#%'),
                                    'format' => '',
                                    'prev_text' => '&laquo;',
                                    'next_text' => '&raquo;',
                                    'total' => $total_pages,
                                    'current' => $paged,
                                ));
                                ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <br class="clear">
                </div>
                
                <div class="reserva-admin-actions">
                    <input type="submit" class="button button-primary" value="Guardar cambios">
                </div>
            </form>
            
            <div class="reserva-admin-help">
                <h3>Instrucciones</h3>
                <ul>
                    <li><strong>Filtrar productos:</strong> Use los filtros en la parte superior para encontrar productos específicos.</li>
                    <li><strong>Habilitar para reserva:</strong> Active el interruptor en la columna "Disponible para reserva" para los productos que desea incluir en el formulario de reservas.</li>
                    <li><strong>Acciones en lote:</strong> Seleccione varios productos y use las acciones en lote para habilitar o deshabilitar múltiples productos a la vez.</li>
                    <li><strong>Guardar cambios:</strong> Después de realizar sus selecciones, haga clic en "Guardar cambios" para aplicar los cambios.</li>
                </ul>
            </div>
        </div>
    </div>
    <?php
}
