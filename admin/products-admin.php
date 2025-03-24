<?php
/**
 * Administración de productos, tallas y precios
 *
 * @package ReservaForm
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registra el menú de administración de productos
 */
function reserva_register_productos_menu() {
    add_submenu_page(
        'reserva-lista',
        'Administrar Productos',
        'Productos',
        'manage_options',
        'reserva-productos',
        'reserva_productos_admin_page'
    );
}
add_action('admin_menu', 'reserva_register_productos_menu', 20);

/**
 * Página de administración de productos
 */
function reserva_productos_admin_page() {
    // Verificar permisos
    if (!current_user_can('manage_options')) {
        return;
    }

    // Procesar acciones
    if (isset($_POST['action']) && $_POST['action'] == 'save_product') {
        reserva_save_product();
    }

    // Obtener acción actual
    $current_action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
    
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">
            <?php 
            if ($current_action == 'add') {
                echo 'Añadir nuevo producto';
            } elseif ($current_action == 'edit') {
                echo 'Editar producto';
            } else {
                echo 'Productos';
            }
            ?>
        </h1>
        
        <?php if ($current_action == 'list'): ?>
            <a href="<?php echo admin_url('admin.php?page=reserva-productos&action=add'); ?>" class="page-title-action">Añadir nuevo</a>
        <?php endif; ?>
        
        <hr class="wp-header-end">
        
        <?php
        // Mostrar la vista correspondiente
        if ($current_action == 'add' || $current_action == 'edit') {
            reserva_productos_edit_view();
        } else {
            reserva_productos_list_view();
        }
        ?>
    </div>
    <?php
}

/**
 * Vista de lista de productos
 */
function reserva_productos_list_view() {
    global $wpdb;
    
    // Obtener todos los productos
    $productos = $wpdb->get_results(
        "SELECT p.*, c.nombre as categoria_nombre
         FROM {$wpdb->prefix}reservas_productos p
         LEFT JOIN {$wpdb->prefix}reservas_categorias c ON p.categoria_id = c.id
         ORDER BY p.nombre ASC"
    );
    
    ?>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Slug</th>
                <th>Categoría</th>
                <th>Imagen</th>
                <th>Activo</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($productos)): ?>
                <tr>
                    <td colspan="6">No hay productos disponibles.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($productos as $producto): ?>
                    <tr>
                        <td><?php echo esc_html($producto->nombre); ?></td>
                        <td><?php echo esc_html($producto->slug); ?></td>
                        <td><?php echo esc_html($producto->categoria_nombre); ?></td>
                        <td>
                            <?php if ($producto->imagen_url): ?>
                                <img src="<?php echo esc_url($producto->imagen_url); ?>" alt="<?php echo esc_attr($producto->nombre); ?>" style="max-width: 50px; height: auto;">
                            <?php else: ?>
                                Sin imagen
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo $producto->activo ? '<span style="color:green;">Activo</span>' : '<span style="color:red;">Inactivo</span>'; ?>
                        </td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=reserva-productos&action=edit&id=' . $producto->id); ?>" class="button button-small">Editar</a>
                            <a href="<?php echo admin_url('admin.php?page=reserva-productos&action=manage_prices&id=' . $producto->id); ?>" class="button button-small">Precios</a>
                            <a href="<?php echo admin_url('admin.php?page=reserva-productos&action=toggle&id=' . $producto->id); ?>" class="button button-small">
                                <?php echo $producto->activo ? 'Desactivar' : 'Activar'; ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <?php
}

/**
 * Vista de edición de producto
 */
function reserva_productos_edit_view() {
    global $wpdb;
    
    // Determinar si es edición o nuevo
    $product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $is_edit = $product_id > 0;
    
    // Obtener datos del producto si es edición
    $producto = null;
    if ($is_edit) {
        $producto = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}reservas_productos WHERE id = %d",
            $product_id
        ));
        
        if (!$producto) {
            echo '<div class="notice notice-error"><p>Producto no encontrado.</p></div>';
            return;
        }
    }
    
    // Obtener categorías para el select
    $categorias = $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}reservas_categorias ORDER BY nombre ASC"
    );
    
    ?>
    <form method="post" action="">
        <?php wp_nonce_field('reserva_save_product', 'reserva_product_nonce'); ?>
        <input type="hidden" name="action" value="save_product">
        <?php if ($is_edit): ?>
            <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
        <?php endif; ?>
        
        <table class="form-table">
            <tr>
                <th><label for="nombre">Nombre</label></th>
                <td>
                    <input type="text" name="nombre" id="nombre" class="regular-text" value="<?php echo $is_edit ? esc_attr($producto->nombre) : ''; ?>" required>
                </td>
            </tr>
            <tr>
                <th><label for="slug">Slug</label></th>
                <td>
                    <input type="text" name="slug" id="slug" class="regular-text" value="<?php echo $is_edit ? esc_attr($producto->slug) : ''; ?>" <?php echo $is_edit ? 'readonly' : ''; ?> required>
                    <p class="description">Identificador único para el producto. Se usa en URLs y código. Solo letras, números y guiones.</p>
                </td>
            </tr>
            <tr>
                <th><label for="descripcion">Descripción</label></th>
                <td>
                    <textarea name="descripcion" id="descripcion" class="large-text" rows="4"><?php echo $is_edit ? esc_textarea($producto->descripcion) : ''; ?></textarea>
                </td>
            </tr>
            <tr>
                <th><label for="categoria_id">Categoría</label></th>
                <td>
                    <select name="categoria_id" id="categoria_id">
                        <option value="">Seleccionar categoría</option>
                        <?php foreach ($categorias as $categoria): ?>
                            <option value="<?php echo $categoria->id; ?>" <?php selected($is_edit && $producto->categoria_id == $categoria->id); ?>>
                                <?php echo esc_html($categoria->nombre); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="imagen_url">URL de imagen</label></th>
                <td>
                    <input type="text" name="imagen_url" id="imagen_url" class="regular-text" value="<?php echo $is_edit ? esc_attr($producto->imagen_url) : ''; ?>">
                    <button type="button" class="button" id="upload_image_button">Seleccionar imagen</button>
                    <div id="image_preview" style="margin-top: 10px;">
                        <?php if ($is_edit && $producto->imagen_url): ?>
                            <img src="<?php echo esc_url($producto->imagen_url); ?>" style="max-width: 200px; height: auto;">
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <tr>
                <th><label for="activo">Estado</label></th>
                <td>
                    <label>
                        <input type="checkbox" name="activo" id="activo" value="1" <?php checked($is_edit ? $producto->activo : true); ?>>
                        Producto activo
                    </label>
                </td>
            </tr>
        </table>
        
        <?php submit_button($is_edit ? 'Actualizar producto' : 'Añadir producto'); ?>
    </form>
    
    <script>
        jQuery(document).ready(function($) {
            // Generador de slug automático
            $('#nombre').on('blur', function() {
                var $slug = $('#slug');
                // Solo generar slug si el campo está vacío y no estamos en modo edición
                if ($slug.val() === '' && !$slug.attr('readonly')) {
                    var nombre = $(this).val();
                    var slug = nombre.toLowerCase()
                        .replace(/[^a-z0-9]+/g, '-')  // Reemplazar caracteres especiales con guiones
                        .replace(/^-+|-+$/g, '');     // Quitar guiones al inicio y final
                    
                    $slug.val(slug);
                }
            });
            
            // Selector de imagen de WordPress
            $('#upload_image_button').click(function(e) {
                e.preventDefault();
                
                var image_frame;
                
                if (image_frame) {
                    image_frame.open();
                    return;
                }
                
                image_frame = wp.media({
                    title: 'Seleccionar imagen',
                    multiple: false,
                    library: {
                        type: 'image'
                    }
                });
                
                image_frame.on('select', function() {
                    var attachment = image_frame.state().get('selection').first().toJSON();
                    $('#imagen_url').val(attachment.url);
                    $('#image_preview').html('<img src="' + attachment.url + '" style="max-width: 200px; height: auto;">');
                });
                
                image_frame.open();
            });
        });
    </script>
    <?php
}

/**
 * Guarda o actualiza un producto
 */
function reserva_save_product() {
    // Verificar nonce
    if (!isset($_POST['reserva_product_nonce']) || !wp_verify_nonce($_POST['reserva_product_nonce'], 'reserva_save_product')) {
        wp_die('Acción no autorizada');
    }
    
    // Verificar permisos
    if (!current_user_can('manage_options')) {
        wp_die('No tienes permisos para realizar esta acción');
    }
    
    global $wpdb;
    
    // Obtener datos del formulario
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $nombre = sanitize_text_field($_POST['nombre']);
    $slug = sanitize_title($_POST['slug']);
    $descripcion = sanitize_textarea_field($_POST['descripcion']);
    $categoria_id = !empty($_POST['categoria_id']) ? intval($_POST['categoria_id']) : null;
    $imagen_url = esc_url_raw($_POST['imagen_url']);
    $activo = isset($_POST['activo']) ? 1 : 0;
    
    // Preparar datos para la base de datos
    $datos = array(
        'nombre' => $nombre,
        'slug' => $slug,
        'descripcion' => $descripcion,
        'imagen_url' => $imagen_url,
        'activo' => $activo
    );
    
    // Añadir categoría si está seleccionada
    if ($categoria_id) {
        $datos['categoria_id'] = $categoria_id;
    }
    
    $formatos = array('%s', '%s', '%s', '%s', '%d');
    if ($categoria_id) {
        $formatos[] = '%d';
    }
    
    // Verificar si ya existe un producto con el mismo slug (solo para nuevos productos)
    if (!$product_id) {
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}reservas_productos WHERE slug = %s",
            $slug
        ));
        
        if ($existing) {
            // Mostrar error y redirigir
            add_settings_error(
                'reserva_productos',
                'slug_exists',
                'Ya existe un producto con el mismo slug. Por favor, usa un slug diferente.',
                'error'
            );
            set_transient('settings_errors', get_settings_errors(), 30);
            wp_redirect(admin_url('admin.php?page=reserva-productos&action=add'));
            exit;
        }
    }
    
    // Guardar o actualizar
    if ($product_id) {
        // Actualizar producto existente
        $wpdb->update(
            $wpdb->prefix . 'reservas_productos',
            $datos,
            array('id' => $product_id),
            $formatos,
            array('%d')
        );
        
        $message = 'Producto actualizado correctamente.';
    } else {
        // Insertar nuevo producto
        $wpdb->insert(
            $wpdb->prefix . 'reservas_productos',
            $datos,
            $formatos
        );
        
        $product_id = $wpdb->insert_id;
        $message = 'Producto añadido correctamente.';
    }
    
    // Redirigir con mensaje de éxito
    add_settings_error(
        'reserva_productos',
        'product_saved',
        $message,
        'success'
    );
    
    set_transient('settings_errors', get_settings_errors(), 30);
    wp_redirect(admin_url('admin.php?page=reserva-productos'));
    exit;
}

/**
 * Enqueues media scripts
 */
function reserva_admin_enqueue_media_scripts($hook) {
    if (strpos($hook, 'reserva-productos') !== false) {
        wp_enqueue_media();
    }
}
add_action('admin_enqueue_scripts', 'reserva_admin_enqueue_media_scripts'); 