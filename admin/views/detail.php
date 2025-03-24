<?php
/**
 * Vista del detalle de una reserva
 *
 * @package ReservaPlugin
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Se asume que $this es una instancia de Reserva_Detail
$reserva = $this->get_reserva();
// Eliminamos las variables que ya no usamos porque ahora calculamos todo directamente
// $productos = $this->get_productos();
// $subtotal = $this->get_subtotal();
// $descuento = $this->get_descuento();
// $total = $this->get_total();

// Definir logo para imprimir
$logo_url = plugins_url('assets/images/logo.jpg', dirname(dirname(__FILE__)));
?>

<div class="reserva-admin-wrap reserva-detail-page">
    
    <!-- Barra superior con botones de acción -->
    <div class="reserva-detail-actions">
        <div class="action-buttons-left">
            <a href="<?php echo esc_url(admin_url('admin.php?page=reserva-lista')); ?>" class="button button-secondary">
                <span class="dashicons dashicons-arrow-left-alt"></span> Volver a la lista
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=reservas')); ?>" class="button button-secondary">
                <span class="dashicons dashicons-dashboard"></span> Dashboard
            </a>
        </div>
        
        <div class="action-buttons-right">
            <a href="#" class="button button-secondary" onclick="window.print();return false;">
                <span class="dashicons dashicons-printer"></span> Imprimir
            </a>
            
            <?php
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
            <a href="<?php echo esc_url($delete_url); ?>" class="button button-delete reserva-delete-button">
                <span class="dashicons dashicons-trash"></span> Eliminar
            </a>
        </div>
    </div>
    
    <!-- Contenido principal -->
    <div class="reserva-detail-container">
        
        <!-- Encabezado de la reserva -->
        <div class="reserva-detail-header">
            <div class="header-branding">
                <img src="<?php echo esc_url($logo_url); ?>" alt="Scolari" class="reserva-logo">
            </div>
            <div class="header-info">
                <h1>Detalle de Reserva #<?php echo esc_html($reserva->id); ?></h1>
                <p class="reservation-date">
                    Creada el: <?php echo date('d/m/Y H:i', strtotime($reserva->fecha_registro)); ?>
                </p>
            </div>
            <div class="header-status">
                <div class="status-badge status-active">Reserva Activa</div>
            </div>
        </div>
        
        <!-- Contenido de la reserva en dos columnas -->
        <div class="reserva-detail-content">
            
            <!-- Columna izquierda: Datos del cliente y fechas -->
            <div class="reserva-detail-column cliente-info">
                
                <!-- Datos del cliente -->
                <div class="detail-section">
                    <h2><span class="dashicons dashicons-admin-users"></span> Datos del Cliente</h2>
                    <div class="detail-card">
                        <div class="detail-row"><span class="detail-label">Nombre:</span><span class="detail-value"><?php echo esc_html($reserva->nombre); ?></span></div>
                        <div class="detail-row"><span class="detail-label">Email:</span><span class="detail-value"><a href="mailto:<?php echo esc_attr($reserva->email); ?>"><?php echo esc_html($reserva->email); ?></a></span></div>
                        <div class="detail-row"><span class="detail-label">Teléfono:</span><span class="detail-value"><a href="tel:<?php echo esc_attr($reserva->telefono); ?>"><?php echo esc_html($reserva->telefono); ?></a></span></div>
                        <div class="detail-row"><span class="detail-label">Dirección:</span><span class="detail-value"><?php echo esc_html($reserva->direccion); ?></span></div>
                        <div class="detail-row"><span class="detail-label">Comuna:</span><span class="detail-value"><?php echo esc_html($reserva->comuna); ?></span></div>
                    </div>
                </div>
                
                <!-- Fechas importantes -->
                <div class="detail-section">
                    <h2><span class="dashicons dashicons-calendar-alt"></span> Información de Fechas</h2>
                    <div class="detail-card">
                        <div class="detail-row"><span class="detail-label">Fecha de Necesidad:</span><span class="detail-value fecha-highlight">
                            <?php 
                            $fecha_mostrar = isset($reserva->fecha_recogida) ? $reserva->fecha_recogida : $reserva->fecha;
                            echo format_fecha_completa($fecha_mostrar); 
                            ?>
                        </span></div>
                        <div class="detail-row"><span class="detail-label">Fecha de Registro:</span><span class="detail-value">
                            <?php 
                            $fecha_registro = isset($reserva->fecha_creacion) ? $reserva->fecha_creacion : $reserva->fecha_registro;
                            echo format_fecha_completa($fecha_registro); 
                            ?>
                        </span></div>
                    </div>
                </div>
                
                <!-- Agregar alguna información adicional o comentarios si existen -->
                <?php if (!empty($reserva->observaciones)): ?>
                <div class="detail-section">
                    <h2><span class="dashicons dashicons-editor-alignleft"></span> Observaciones</h2>
                    <div class="detail-card">
                        <p><?php echo nl2br(esc_html($reserva->observaciones)); ?></p>
                    </div>
                </div>
                <?php endif; ?>
                
            </div>
            
            <!-- Columna derecha: Productos y total -->
            <div class="reserva-detail-column productos-info">
                <h2><span class="dashicons dashicons-cart"></span> Productos Reservados</h2>
                
                <?php 
                // Obtener productos directamente para diagnosticar
                $productos_mostrar = array();
                
                // Obtener productos de JSON
                $json_productos = json_decode($reserva->productos, true);
                
                if (is_array($json_productos) && !empty($json_productos)) {
                    $placeholder_img = plugins_url('assets/images/product-placeholder.jpg', dirname(dirname(__FILE__)));
                    
                    $mapa_productos = array(
                        'Pantalón Buzo Alianza Francesa' => plugins_url('assets/images/poleronypantalon.jpg', dirname(dirname(__FILE__))),
                        'Polera Deporte M/C Alianza Francesa' => plugins_url('assets/images/polera.jpg', dirname(dirname(__FILE__))),
                        'Polerón Buzo Alianza Francesa' => plugins_url('assets/images/poleronypantalon.jpg', dirname(dirname(__FILE__))),
                    );
                    
                    foreach ($json_productos as $producto) {
                        $img_url = isset($producto['img']) ? $producto['img'] : 
                                ($mapa_productos[$producto['producto']] ?? $placeholder_img);
                        
                        $precio_unitario = isset($producto['precio']) ? intval($producto['precio']) : 0;
                        $cantidad = intval($producto['cantidad']);
                        $subtotal = isset($producto['subtotal']) ? intval($producto['subtotal']) : ($precio_unitario * $cantidad);
                        
                        $productos_mostrar[] = array(
                            'nombre' => $producto['producto'],
                            'talla' => $producto['talla'],
                            'cantidad' => $cantidad,
                            'precio_unitario' => $precio_unitario,
                            'subtotal' => $subtotal,
                            'img_url' => $img_url
                        );
                    }
                }
                
                // Calcular subtotal y total
                $subtotal_calculado = 0;
                foreach($productos_mostrar as $producto) {
                    $subtotal_calculado += $producto['subtotal'];
                }
                
                $descuento = isset($reserva->descuento) ? intval($reserva->descuento) : 0;
                $total_calculado = $subtotal_calculado - $descuento;
                
                // Usar total almacenado si está disponible
                $total_mostrar = isset($reserva->total) && intval($reserva->total) > 0 ? 
                                intval($reserva->total) : $total_calculado;
                ?>
                
                <?php if (!empty($productos_mostrar)): ?>
                    <div class="reserva-productos">
                        <div class="productos-grid">
                            <?php foreach ($productos_mostrar as $producto): ?>
                                <div class="producto-card">
                                    <div class="producto-imagen">
                                        <img src="<?php echo esc_url($producto['img_url']); ?>" alt="<?php echo esc_attr($producto['nombre']); ?>">
                                    </div>
                                    <div class="producto-info">
                                        <h4><?php echo esc_html($producto['nombre']); ?></h4>
                                        <p class="producto-talla"><strong>Talla:</strong> <?php echo esc_html($producto['talla']); ?></p>
                                        <p class="producto-cantidad"><strong>Cantidad:</strong> <?php echo esc_html($producto['cantidad']); ?></p>
                                        <p class="producto-precio"><strong>Precio unitario:</strong> $<?php echo number_format($producto['precio_unitario'], 0, ',', '.'); ?></p>
                                        <p class="producto-subtotal"><strong>Subtotal:</strong> $<?php echo number_format($producto['subtotal'], 0, ',', '.'); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Resumen de la compra -->
                    <div class="reserva-summary">
                        <div class="summary-item"><span>Subtotal:</span><span>$<?php echo number_format($subtotal_calculado, 0, ',', '.'); ?></span></div>
                        
                        <?php if ($descuento > 0): ?>
                            <div class="summary-item summary-discount"><span>Descuento:</span><span>-$<?php echo number_format($descuento, 0, ',', '.'); ?></span></div>
                        <?php endif; ?>
                        
                        <div class="summary-item summary-total"><span>Total:</span><span>$<?php echo number_format($total_mostrar, 0, ',', '.'); ?></span></div>
                    </div>
                    
                <?php else: ?>
                    <!-- Si no hay detalles estructurados, mostrar la información básica -->
                    <div class="detail-card no-productos">
                        <p class="no-details-message"><span class="dashicons dashicons-info"></span> No hay detalles estructurados disponibles para esta reserva.</p>
                        
                        <?php if (!empty($reserva->productos)): ?>
                            <p><strong>Productos registrados:</strong> <?php echo esc_html($reserva->productos); ?></p>
                        <?php endif; ?>
                        
                        <p><strong>Total registrado:</strong> $<?php echo number_format($reserva->total, 0, ',', '.'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Pie de página -->
        <div class="reserva-detail-footer">
            <div class="footer-info">
                <p>Sistema de Reservas de Uniformes - Scolari</p>
            </div>
            <div class="footer-id">
                <p>ID de Reserva: <?php echo esc_html($reserva->id); ?> | Generado el: <?php echo date('d/m/Y H:i'); ?></p>
            </div>
            <div class="footer-credits">
                <p>Desarrollado por <a href="https://github.com/felipevega-dev" target="_blank">Felipe Vega <span class="dashicons dashicons-github"></span></a></p>
            </div>
        </div>
    </div>
</div> 