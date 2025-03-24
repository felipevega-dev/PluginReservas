<?php
/**
 * Vista de la lista de reservas
 *
 * @package ReservaPlugin
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Se asume que $this es una instancia de Reserva_List
$stats = $this->get_stats();
$reservas = $this->get_reservas();
$pagination = $this->get_pagination_info();
$filter_data = $this->get_filters();
$notices = $this->get_notices();
$comunas = $this->get_comunas();
?>
<div class="reserva-admin-wrap">
    
    <?php foreach ($notices as $notice): ?>
    <div class="reserva-notice reserva-notice-<?php echo esc_attr($notice['type']); ?>">
        <span class="dashicons dashicons-<?php echo $notice['type'] === 'success' ? 'yes' : 'no'; ?>"></span>
        <?php echo esc_html($notice['message']); ?>
    </div>
    <?php endforeach; ?>
    
    <!-- Header con título y estadísticas -->
    <div class="reserva-admin-header">
        <div class="header-left">
            <h1><span class="dashicons dashicons-list-view"></span> Listado de Reservas</h1>
            <p class="header-subtitle">Gestiona todas las reservas de uniformes de la plataforma</p>
        </div>
        
        <!-- Estadísticas rápidas -->
        <div class="header-stats">
            <div class="header-stat-item">
                <span class="stat-label">Total:</span>
                <span class="stat-value"><?php echo esc_html($stats['total_reservas']); ?> reservas</span>
            </div>
            
            <div class="header-stat-item">
                <span class="stat-label">Ingresos:</span>
                <span class="stat-value">$<?php echo number_format($stats['total_ingresos'], 0, ',', '.'); ?></span>
            </div>
            
            <div class="header-stat-item">
                <span class="stat-label">Productos:</span>
                <span class="stat-value"><?php echo esc_html($stats['total_productos']); ?> unidades</span>
            </div>
        </div>
    </div>
    
    <!-- Barra de acciones (búsqueda y botones) -->
    <div class="reserva-admin-actions">
        <form method="get" class="reserva-search-form">
            <input type="hidden" name="page" value="reserva-lista">
            <div class="search-input-container">
                <span class="dashicons dashicons-search"></span>
                <input type="search" name="s" value="<?php echo esc_attr($filter_data['search_query']); ?>" placeholder="Buscar reservas por nombre, email o productos...">
            </div>
            <input type="submit" class="button button-primary" value="Buscar">
        </form>
        
        <div class="reserva-admin-buttons">
            <a href="<?php echo esc_url(admin_url('admin.php?page=reservas')); ?>" class="button">
                <span class="dashicons dashicons-dashboard"></span> Dashboard
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=reserva-lista&export=1')); ?>" class="button button-secondary">
                <span class="dashicons dashicons-media-spreadsheet"></span> Exportar a CSV
            </a>
        </div>
    </div>
    
    <!-- Panel de filtros avanzados (desplegable) -->
    <div class="reserva-filtros-avanzados">
        <div class="filtros-toggle">
            <button type="button" class="button filtros-toggle-btn">
                <span class="dashicons dashicons-filter"></span> Filtros avanzados
            </button>
        </div>
        <div class="filtros-panel" style="display: none;">
            <form method="get" class="filtros-form">
                <input type="hidden" name="page" value="reserva-lista">
                <?php if (!empty($filter_data['search_query'])): ?>
                <input type="hidden" name="s" value="<?php echo esc_attr($filter_data['search_query']); ?>">
                <?php endif; ?>

                <div class="filtros-grid">
                    <!-- Filtro por fecha -->
                    <div class="filtro-group">
                        <label>Fecha desde:</label>
                        <input type="date" name="fecha_desde" value="<?php echo esc_attr($filter_data['filters']['fecha_desde']); ?>">
                    </div>

                    <div class="filtro-group">
                        <label>Fecha hasta:</label>
                        <input type="date" name="fecha_hasta" value="<?php echo esc_attr($filter_data['filters']['fecha_hasta']); ?>">
                    </div>

                    <!-- Filtro por comuna -->
                    <div class="filtro-group">
                        <label>Comuna:</label>
                        <select name="comuna">
                            <option value="">-- Todas las comunas --</option>
                            <?php foreach ($comunas as $comuna): ?>
                                <?php $selected = ($comuna == $filter_data['filters']['comuna']) ? 'selected' : ''; ?>
                                <option value="<?php echo esc_attr($comuna); ?>" <?php echo $selected; ?>>
                                    <?php echo esc_html($comuna); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Filtro por precio -->
                    <div class="filtro-group">
                        <label>Precio mínimo:</label>
                        <input type="number" name="precio_min" value="<?php echo esc_attr($filter_data['filters']['precio_min']); ?>" placeholder="Min">
                    </div>

                    <div class="filtro-group">
                        <label>Precio máximo:</label>
                        <input type="number" name="precio_max" value="<?php echo esc_attr($filter_data['filters']['precio_max']); ?>" placeholder="Max">
                    </div>
                </div>

                <div class="filtros-actions">
                    <input type="submit" class="button button-primary" value="Aplicar filtros">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=reserva-lista')); ?>" class="clear-filters">
                        <span class="dashicons dashicons-dismiss"></span> Limpiar todos
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Si hay filtros activos, mostrar un resumen -->
    <?php if (!empty($filter_data['filters']['fecha_desde']) || !empty($filter_data['filters']['fecha_hasta']) || 
              !empty($filter_data['filters']['comuna']) || !empty($filter_data['filters']['precio_min']) || 
              !empty($filter_data['filters']['precio_max'])): ?>
        <div class="filtros-activos">
            <p><span class="dashicons dashicons-filter"></span> <strong>Filtros activos:</strong> 
            <?php
            $filtros_texto = array();
            if (!empty($filter_data['filters']['fecha_desde'])) {
                $filtros_texto[] = 'Desde ' . date('d/m/Y', strtotime($filter_data['filters']['fecha_desde']));
            }
            if (!empty($filter_data['filters']['fecha_hasta'])) {
                $filtros_texto[] = 'Hasta ' . date('d/m/Y', strtotime($filter_data['filters']['fecha_hasta']));
            }
            if (!empty($filter_data['filters']['comuna'])) {
                $filtros_texto[] = 'Comuna: ' . esc_html($filter_data['filters']['comuna']);
            }
            if (!empty($filter_data['filters']['precio_min'])) {
                $filtros_texto[] = 'Precio min: $' . number_format($filter_data['filters']['precio_min'], 0, ',', '.');
            }
            if (!empty($filter_data['filters']['precio_max'])) {
                $filtros_texto[] = 'Precio max: $' . number_format($filter_data['filters']['precio_max'], 0, ',', '.');
            }
            
            echo implode(' | ', $filtros_texto);
            ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=reserva-lista')); ?>" class="clear-filters">
                <span class="dashicons dashicons-dismiss"></span> Limpiar todos
            </a>
            </p>
        </div>
    <?php endif; ?>
    
    <!-- Resultados de búsqueda si hay un término -->
    <?php if (!empty($filter_data['search_query'])): ?>
        <div class="reserva-search-results">
            <p>Mostrando resultados para: <strong><?php echo esc_html($filter_data['search_query']); ?></strong>
            <a href="<?php echo esc_url(admin_url('admin.php?page=reserva-lista')); ?>" class="clear-search">
                <span class="dashicons dashicons-dismiss"></span> Limpiar búsqueda
            </a></p>
        </div>
    <?php endif; ?>
    
    <!-- Tabla de reservas -->
    <div class="reserva-table-container">
        <table class="reserva-table">
            <thead>
                <tr>
                    <th class="column-id">ID</th>
                    <th class="column-productos">Productos</th>
                    <th class="column-nombre">Nombre</th>
                    <th class="column-email">Email</th>
                    <th class="column-telefono">Teléfono</th>
                    <th class="column-comuna">Comuna</th>
                    <th class="column-fecha">Fecha Necesidad</th>
                    <th class="column-total">Total</th>
                    <th class="column-acciones">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($reservas): ?>
                    <?php foreach ($reservas as $reserva): ?>
                        <tr>
                            <td class="column-id"><span class="id-badge"><?php echo esc_html($reserva->id); ?></span></td>
                            <td class="column-productos">
                            <?php 
                                // Mostrar productos desde JSON
                                $json_productos = json_decode($reserva->productos, true);
                                if (is_array($json_productos) && !empty($json_productos)) {
                                    echo '<ul>';
                                    foreach ($json_productos as $prod) {
                                        echo '<li>' . esc_html($prod['producto']) . ' - ' 
                                             . esc_html($prod['talla']) . ' (Cant: ' 
                                             . esc_html($prod['cantidad']) . ')</li>';
                                    }
                                    echo '</ul>';
                                } else {
                                    echo '<span class="no-disponible">Datos JSON no válidos o vacíos</span>';
                                }
                            ?>
                            </td>
                            <td class="column-nombre"><?php echo esc_html($reserva->nombre); ?></td>
                            <td class="column-email"><a href="mailto:<?php echo esc_attr($reserva->email); ?>"><?php echo esc_html($reserva->email); ?></a></td>
                            <td class="column-telefono"><a href="tel:<?php echo esc_attr($reserva->telefono); ?>"><?php echo esc_html($reserva->telefono); ?></a></td>
                            <td class="column-comuna"><?php echo esc_html($reserva->comuna); ?></td>
                            <td class="column-fecha"><?php echo format_fecha($reserva->fecha); ?></td>
                            <td class="column-total"><span class="precio">$<?php echo number_format($reserva->total, 0, ',', '.'); ?></span></td>
                            <td class="column-acciones reserva-actions">
                                <?php
                                $edit_url = add_query_arg(array(
                                    'page'   => 'reserva-lista',
                                    'action' => 'edit',
                                    'id'     => $reserva->id
                                ), admin_url('admin.php'));
                                
                                $delete_url = wp_nonce_url(
                                    add_query_arg(
                                        array(
                                            'page' => 'reserva-lista',
                                            'delete_reserva' => $reserva->id
                                        ),
                                        admin_url('admin.php')
                                    ),
                                    'delete_reserva_' . $reserva->id
                                );
                                ?>
                                <a href="<?php echo esc_url($edit_url); ?>" class="action-button view-button" title="Ver detalles">
                                    <span class="dashicons dashicons-visibility"></span>
                                </a> 
                                <a href="<?php echo esc_url($delete_url); ?>" class="action-button delete-button" title="Eliminar">
                                    <span class="dashicons dashicons-trash"></span>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr class="no-results">
                        <td colspan="9">
                            <div class="empty-state">
                                <span class="dashicons dashicons-format-aside"></span>
                                <?php if (!empty($filter_data['search_query'])): ?>
                                    <p>No se encontraron reservas que coincidan con tu búsqueda.</p>
                                <?php else: ?>
                                    <p>No hay reservas registradas todavía.</p>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Paginación -->
    <?php if ($pagination['total_pages'] > 1): ?>
        <div class="reserva-pagination">
            <?php 
            echo paginate_links(array(
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'prev_text' => '<span class="dashicons dashicons-arrow-left-alt2"></span> Anterior',
                'next_text' => 'Siguiente <span class="dashicons dashicons-arrow-right-alt2"></span>',
                'total' => $pagination['total_pages'],
                'current' => $pagination['current_page'],
                'type' => 'list',
            ));
            ?>
        </div>
    <?php endif; ?>
    
    <!-- Pie de página con créditos -->
    <div class="reserva-footer-credits">
        <p>Desarrollado por <a href="https://github.com/felipevega-dev" target="_blank">Felipe Vega <span class="dashicons dashicons-github"></span></a></p>
    </div>
</div> 