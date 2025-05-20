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
    if ($screen && $screen->id === 'reservas_page_reserva-woocommerce') {
        wp_enqueue_style('reserva-admin-styles', plugin_dir_url(__FILE__) . '../assets/css/admin-style.css', array(), '1.0.0');
        wp_enqueue_script('reserva-wc-admin', plugin_dir_url(__FILE__) . '../assets/js/wc-admin.js', array('jquery'), '1.0.0', true);
        wp_localize_script('reserva-wc-admin', 'reservaWC', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('reserva_wc_ajax_nonce')
        ));
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

    // Process bulk actions
    if (isset($_POST['action']) && $_POST['action'] == 'update_reservable_status' && isset($_POST['reserva_wc_nonce']) && wp_verify_nonce($_POST['reserva_wc_nonce'], 'reserva_update_wc_products')) {
        if (isset($_POST['product_ids']) && is_array($_POST['product_ids'])) {
            $updated = 0;
            foreach ($_POST['product_ids'] as $product_id) {
                $reservable = isset($_POST['reservable'][$product_id]) ? 'yes' : 'no';
                update_post_meta($product_id, '_reservable', $reservable);
                $updated++;
            }
            echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(_n('%d producto actualizado correctamente.', '%d productos actualizados correctamente.', $updated, 'reserva-form'), $updated) . '</p></div>';
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
        $args['category'] = array($category);
    }

    // Añadir búsqueda si hay término
    if (!empty($search)) {
        $args['search'] = $search;
    }

    // Obtener productos según los filtros
    $products = wc_get_products($args);

    // Obtener el total de productos para la paginación
    $total_products_args = $args;
    $total_products_args['limit'] = -1;
    $total_products_args['return'] = 'ids';
    $total_products = count(wc_get_products($total_products_args));
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
        </div>

        <div class="reserva-admin-content">
            <!-- Filtros y búsqueda -->
            <div class="reserva-admin-filters">
                <form method="get" class="search-form">
                    <input type="hidden" name="page" value="reserva-woocommerce">
                    
                    <div class="filter-row">
                        <div class="filter-item search-box">
                            <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Buscar productos...">
                            <button type="submit" class="button">Buscar</button>
                        </div>
                        
                        <div class="filter-item">
                            <select name="category" class="postform">
                                <option value="0">Todas las categorías</option>
                                <?php foreach ($product_categories as $cat) : ?>
                                    <option value="<?php echo esc_attr($cat->term_id); ?>" <?php selected($category, $cat->term_id); ?>>
                                        <?php echo esc_html($cat->name); ?> (<?php echo esc_html($cat->count); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-item">
                            <select name="reservable_filter">
                                <option value="" <?php selected($reservable_filter, ''); ?>>Todos los productos</option>
                                <option value="yes" <?php selected($reservable_filter, 'yes'); ?>>Reservables</option>
                                <option value="no" <?php selected($reservable_filter, 'no'); ?>>No reservables</option>
                            </select>
                        </div>
                        
                        <div class="filter-item">
                            <button type="submit" class="button">Filtrar</button>
                            <?php if (!empty($search) || $category > 0 || !empty($reservable_filter)) : ?>
                                <a href="<?php echo admin_url('admin.php?page=reserva-woocommerce'); ?>" class="button">Limpiar filtros</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
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
                            </td>
                            <th scope="col" class="manage-column column-image">Imagen</th>
                            <th scope="col" class="manage-column column-name">Producto</th>
                            <th scope="col" class="manage-column column-sku">SKU</th>
                            <th scope="col" class="manage-column column-price">Precio</th>
                            <th scope="col" class="manage-column column-categories">Categorías</th>
                            <th scope="col" class="manage-column column-reservable">Disponible para reserva</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)) : ?>
                            <tr>
                                <td colspan="7" class="colspanchange">
                                    <p class="no-items">No se encontraron productos que coincidan con los criterios.</p>
                                </td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($products as $product) : 
                                $product_id = $product->get_id();
                                $is_reservable = get_post_meta($product_id, '_reservable', true) === 'yes';
                                $categories = get_the_terms($product_id, 'product_cat');
                                $category_names = array();
                                if (!empty($categories) && !is_wp_error($categories)) {
                                    foreach ($categories as $category) {
                                        $category_names[] = $category->name;
                                    }
                                }
                                ?>
                                <tr>
                                    <th scope="row" class="check-column">
                                        <input type="checkbox" name="product_ids[]" value="<?php echo esc_attr($product_id); ?>" id="cb-select-<?php echo esc_attr($product_id); ?>">
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
                                    <td class="column-reservable">
                                        <label class="reserva-switch">
                                            <input type="checkbox" name="reservable[<?php echo esc_attr($product_id); ?>]" <?php checked($is_reservable); ?> class="reserva-toggle">
                                            <span class="reserva-slider round"></span>
                                        </label>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td class="manage-column column-cb check-column">
                                <input type="checkbox" id="cb-select-all-2">
                            </td>
                            <th scope="col" class="manage-column column-image">Imagen</th>
                            <th scope="col" class="manage-column column-name">Producto</th>
                            <th scope="col" class="manage-column column-sku">SKU</th>
                            <th scope="col" class="manage-column column-price">Precio</th>
                            <th scope="col" class="manage-column column-categories">Categorías</th>
                            <th scope="col" class="manage-column column-reservable">Disponible para reserva</th>
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
