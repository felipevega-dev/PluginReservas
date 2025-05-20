<?php
/**
 * Dashboard de administración
 *
 * @package ReservaForm
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Función para mostrar el dashboard principal
 */
function reserva_admin_dashboard() {
    // Verificar permisos
    if (function_exists('reserva_verify_admin_capabilities')) {
        reserva_verify_admin_capabilities();
    } else if (!current_user_can('manage_options')) {
        wp_die(__('No tienes suficientes permisos para acceder a esta página.'));
    }
    
    // Cargar scripts necesarios para gráficos
    wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.7.0', true);
    
    // Contenido del dashboard
    echo '<div class="wrap">';
    echo '<h1><span class="dashicons dashicons-analytics"></span> Dashboard de Reservas</h1>';
    
    // Header con contador y enlaces rápidos
    echo '<div class="reserva-header-container">';
    
    // Estadísticas básicas
    global $wpdb;
    $table_name = $wpdb->prefix . 'reservas';
    $total_reservas = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    
    // Obtener estadísticas de productos
    $product_stats = reserva_get_product_stats();
    $total_dinero = array_sum(array_column($product_stats, 'total'));
    
    // Tarjeta de total de reservas con monto acumulado - OCUPA EL ESPACIO PRINCIPAL
    echo '<div class="reserva-stat-main reserva-total-card">';
    echo '<div class="card-icon"><span class="dashicons dashicons-cart"></span></div>';
    echo '<div class="card-content">';
    echo '<h2>Número de Reservas</h2>';
    echo '<p class="reserva-stat-number">' . esc_html($total_reservas) . '</p>';
    echo '<p class="reserva-stat-subtitle">Total Recaudado: $' . number_format($total_dinero, 0, ',', '.') . '</p>';
    echo '</div>';
    echo '</div>';
    
    // Enlaces rápidos a la derecha
    echo '<div class="reserva-quick-links">';
    echo '<h2><span class="dashicons dashicons-admin-links"></span> Enlaces Rápidos</h2>';
    echo '<div class="quick-links-buttons">';
    echo '<a href="' . esc_url(admin_url('admin.php?page=reserva-lista')) . '" class="button button-primary"><span class="dashicons dashicons-list-view"></span> Ver todas las reservas</a> ';
    echo '<a href="' . esc_url(admin_url('admin.php?page=reserva-lista&export=1')) . '" class="button"><span class="dashicons dashicons-media-spreadsheet"></span> Exportar a CSV</a>';
    echo '</div>';
    echo '</div>';
    
    echo '</div>'; // .reserva-header-container
    
    // Contenedor de productos - 3 columnas
    echo '<div class="reserva-dashboard-stats">';
    
    // Mostrar estadísticas de productos
    foreach ($product_stats as $slug => $info) {
        // Obtener información del producto (WooCommerce o base de datos)
        $product_info = reserva_get_product_info_for_dashboard($slug);
        
        if (!empty($product_info)) {
            echo '<div class="reserva-stat-card reserva-product-card">';
            echo '<div class="card-header">';
            echo '<div class="card-img"><img src="' . esc_url($product_info['imagen_url']) . '" alt="' . esc_attr($product_info['nombre']) . '"></div>';
            echo '</div>';
            echo '<div class="card-content">';
            echo '<h2>' . esc_html($product_info['nombre']) . '</h2>';
            echo '<div class="card-stats">';
            echo '<p class="reserva-stat-number">' . esc_html($info['cantidad']) . ' <span class="reserva-stat-unit">unidades</span></p>';
            echo '<p class="reserva-stat-subtitle">Valor Total: $' . number_format($info['total'], 0, ',', '.') . '</p>';
            echo '</div>';
            echo '<a href="#" class="button button-primary view-details" data-product="' . esc_attr($slug) . '">Ver Detalle por Tallas</a>';
            echo '</div>';
            echo '</div>';
        }
    }
    
    echo '</div>'; // .reserva-dashboard-stats
    
    // Contenedor para el detalle de tallas (inicialmente oculto)
    echo '<div id="size-details-container" class="size-details-container"></div>';
    
    // Gráficos en layout optimizado
    echo '<div class="reserva-charts-section">';
    echo '<h2>Análisis de Datos</h2>';
    
    // Layout de gráficos en grid
    echo '<div class="reserva-charts-grid">';
    
    // Gráfico de distribución de productos
    echo '<div class="reserva-chart-container chart-pie">';
    echo '<h3><span class="dashicons dashicons-chart-pie"></span> Distribución de Productos</h3>';
    echo '<div class="chart-wrapper">';
    echo '<canvas id="productDistribution"></canvas>';
    echo '</div>';
    echo '</div>';
    
    // Gráfico: Comparativa de valor total por producto
    echo '<div class="reserva-chart-container chart-bar">';
    echo '<h3><span class="dashicons dashicons-chart-bar"></span> Valor Total por Producto</h3>';
    echo '<div class="chart-wrapper">';
    echo '<canvas id="productValues"></canvas>';
    echo '</div>';
    echo '</div>';
    
    // Gráfico: Tendencia de ventas (simulada)
    echo '<div class="reserva-chart-container chart-line">';
    echo '<h3><span class="dashicons dashicons-chart-line"></span> Tendencia de Ventas (Últimos 7 días)</h3>';
    echo '<div class="chart-wrapper chart-wrapper-line">';
    echo '<canvas id="salesTrend"></canvas>';
    echo '</div>';
    echo '</div>';
    
    echo '</div>'; // .reserva-charts-grid
    
    echo '</div>'; // .reserva-charts-section
    
    // Pie de página con créditos
    echo '<div class="reserva-footer-credits">';
    echo '<p>Desarrollado por <a href="https://github.com/felipevega-dev" target="_blank"><span class="dashicons dashicons-github"></span> Felipe Vega</a></p>';
    echo '</div>';
    
    echo '</div>'; // .wrap
    
    // Diálogo modal para detalles
    echo '<div id="size-modal" class="reserva-modal">
        <div class="reserva-modal-content">
            <div class="reserva-modal-header">
                <h2><span class="dashicons dashicons-list-view"></span> Detalle de Tallas: <span id="product-title"></span></h2>
                <span class="reserva-modal-close">&times;</span>
            </div>
            <div class="reserva-modal-body">
                <div class="reserva-modal-flex">
                    <div class="size-details-table">
                        <div id="size-data"></div>
                    </div>
                    <div class="size-details-chart">
                        <canvas id="sizeDistribution"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>';
    
    // Estilos inline para el dashboard
    echo '<style>
        .wrap h1, .wrap h2, .wrap h3 {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        /* Header container con reservas y enlaces */
        .reserva-header-container {
            display: flex;
            gap: 20px;
            margin: 20px 0;
        }
        
        /* Tarjeta de reservas principal */
        .reserva-stat-main {
            flex: 3;
            background-color: #fff;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            display: flex;
            align-items: center;
        }
        .reserva-total-card {
            background: linear-gradient(135deg, #4b6cb7 0%, #182848 100%);
            color: white;
        }
        .reserva-total-card h2, .reserva-total-card p {
            color: white !important;
        }
        
        /* Enlaces rápidos a la derecha */
        .reserva-quick-links {
            flex: 1;
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
        }
        .reserva-quick-links h2 {
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 18px;
        }
        .quick-links-buttons {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: auto;
        }
        .quick-links-buttons .button {
            display: flex;
            align-items: center;
            gap: 5px;
            justify-content: center;
            padding: 10px 15px;
            font-size: 14px;
            width: 100%;
        }
        
        /* Grid de productos */
        .reserva-dashboard-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        .reserva-stat-card {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            display: flex;
            flex-direction: column;
        }
        .reserva-stat-card:hover, .reserva-stat-main:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.15);
        }
        
        /* Tarjetas de productos */
        .reserva-product-card {
            position: relative;
            overflow: hidden;
        }
        .card-header {
            display: flex;
            justify-content: center;
            margin-bottom: 15px;
        }
        .card-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            margin-right: 20px;
        }
        .reserva-total-card .card-icon {
            background-color: rgba(255, 255, 255, 0.2);
        }
        .card-icon .dashicons {
            font-size: 28px;
            width: 28px;
            height: 28px;
            color: white;
        }
        .card-img {
            width: 150px;
            height: 150px;
            overflow: hidden;
            border-radius: 6px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        .card-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .card-content {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .reserva-total-card .card-content {
            margin-left: 20px;
        }
        .card-stats {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-bottom: 15px;
        }
        .reserva-stat-number {
            font-size: 28px;
            font-weight: bold;
            color: #0073aa;
            margin: 5px 0;
            line-height: 1.2;
        }
        .reserva-stat-unit {
            font-size: 16px;
            font-weight: normal;
            opacity: 0.7;
        }
        .reserva-stat-subtitle {
            margin: 5px 0 0;
            font-size: 16px;
            color: #2c3e50;
        }
        .reserva-product-card .button {
            margin-top: auto;
        }
        
        /* Contenedor de detalles de tallas */
        .size-details-container {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin: 0 0 20px 0;
            display: none;
            animation: slideDown 0.3s ease-out;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Sección de gráficos */
        .reserva-charts-section {
            margin: 30px 0;
        }
        .reserva-charts-section h2 {
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        
        /* Grid para gráficos */
        .reserva-charts-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            grid-template-rows: auto auto;
            gap: 20px;
        }
        .chart-pie, .chart-bar {
            grid-column: span 1;
        }
        .chart-line {
            grid-column: span 2;
        }
        .reserva-chart-container {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .chart-wrapper {
            height: 280px;
            margin-top: 15px;
        }
        .chart-wrapper-line {
            height: 250px;
        }
        
        /* Tablas */
        .size-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .size-table th, .size-table td {
            border: 1px solid #e0e0e0;
            padding: 10px;
            text-align: center;
        }
        .size-table thead th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        .size-table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .size-table tfoot {
            font-weight: bold;
            background-color: #f0f0f0;
        }
        .size-table .size-subtotal {
            text-align: right;
            color: #0073aa;
        }
        
        /* Modal */
        .reserva-modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.5);
            animation: fadeIn 0.3s;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .reserva-modal-content {
            background-color: #fefefe;
            margin: 50px auto;
            padding: 0;
            border-radius: 8px;
            width: 80%;
            max-width: 1000px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            animation: slideUp 0.3s;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(50px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .reserva-modal-header {
            padding: 15px 20px;
            background-color: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 8px 8px 0 0;
        }
        .reserva-modal-header h2 {
            margin: 0;
        }
        .reserva-modal-close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.2s;
        }
        .reserva-modal-close:hover {
            color: #555;
        }
        .reserva-modal-body {
            padding: 20px;
        }
        .reserva-modal-flex {
            display: flex;
            gap: 20px;
        }
        .size-details-table, .size-details-chart {
            flex: 1;
        }
        .size-details-chart {
            min-height: 300px;
        }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .reserva-charts-grid {
                grid-template-columns: 1fr;
            }
            .chart-line {
                grid-column: span 1;
            }
        }
        
        @media (max-width: 992px) {
            .reserva-header-container {
                flex-direction: column;
            }
            .reserva-dashboard-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            .quick-links-buttons {
                flex-direction: row;
                flex-wrap: wrap;
            }
            .quick-links-buttons .button {
                width: auto;
            }
            .reserva-modal-flex {
                flex-direction: column;
            }
        }
        
        @media (max-width: 768px) {
            .reserva-dashboard-stats {
                grid-template-columns: 1fr;
            }
            .reserva-total-card {
                flex-direction: column;
                align-items: flex-start;
                text-align: center;
            }
            .reserva-total-card .card-icon {
                margin: 0 auto 15px;
            }
            .reserva-total-card .card-content {
                margin-left: 0;
                text-align: center;
                width: 100%;
            }
            .card-stats {
                flex-direction: column;
            }
            .reserva-modal-content {
                width: 95%;
                margin: 20px auto;
            }
        }
    </style>';
    
    // Preparar datos para los gráficos
    $product_labels = array();
    $product_data = array();
    $product_values = array();
    $product_colors = array('#4e73df', '#1cc88a', '#36b9cc');
    
    $i = 0;
    foreach ($product_stats as $slug => $info) {
        $product_info = reserva_get_product_info_for_dashboard($slug);
        if (!empty($product_info)) {
            $product_labels[] = $product_info['nombre'];
            $product_data[] = $info['cantidad'];
            $product_values[] = $info['total'];
            $i++;
        }
    }
    
    // Datos para el gráfico de tendencia (simulado)
    $trend_labels = array();
    $trend_data = array();
    
    // Obtener los últimos 7 días
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime('-' . $i . ' days'));
        $trend_labels[] = date('d/m', strtotime($date));
        
        // Contar reservas para ese día
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}reservas WHERE DATE(fecha_creacion) = %s",
            $date
        ));
        
        $trend_data[] = $count ? $count : 0;
    }
    
    // JavaScript para los gráficos
    echo '<script>
        jQuery(document).ready(function($) {
            // Gráfico de distribución de productos
            var productCtx = document.getElementById("productDistribution").getContext("2d");
            var productChart = new Chart(productCtx, {
                type: "doughnut",
                data: {
                    labels: ' . json_encode($product_labels) . ',
                    datasets: [{
                        data: ' . json_encode($product_data) . ',
                        backgroundColor: ' . json_encode($product_colors) . ',
                        hoverBackgroundColor: ["#2e59d9", "#17a673", "#2c9faf"],
                        hoverBorderColor: "rgba(234, 236, 244, 1)",
                    }],
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: "right",
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    var label = context.label || "";
                                    var value = context.raw || 0;
                                    var total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    var percentage = Math.round((value / total) * 100);
                                    return label + ": " + value + " (" + percentage + "%)";
                                }
                            }
                        }
                    },
                    cutout: "70%"
                }
            });
            
            // Gráfico de valores totales por producto
            var valuesCtx = document.getElementById("productValues").getContext("2d");
            var valuesChart = new Chart(valuesCtx, {
                type: "bar",
                data: {
                    labels: ' . json_encode($product_labels) . ',
                    datasets: [{
                        label: "Valor en $",
                        data: ' . json_encode($product_values) . ',
                        backgroundColor: ' . json_encode($product_colors) . ',
                        hoverBackgroundColor: ["#2e59d9", "#17a673", "#2c9faf"],
                        borderWidth: 0
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return "$" + value.toLocaleString();
                                }
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return "$" + context.raw.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
            
            // Gráfico de tendencia de ventas
            var trendCtx = document.getElementById("salesTrend").getContext("2d");
            var trendChart = new Chart(trendCtx, {
                type: "line",
                data: {
                    labels: ' . json_encode($trend_labels) . ',
                    datasets: [{
                        label: "Reservas",
                        lineTension: 0.3,
                        backgroundColor: "rgba(78, 115, 223, 0.05)",
                        borderColor: "rgba(78, 115, 223, 1)",
                        pointRadius: 3,
                        pointBackgroundColor: "rgba(78, 115, 223, 1)",
                        pointBorderColor: "rgba(78, 115, 223, 1)",
                        pointHoverRadius: 5,
                        pointHoverBackgroundColor: "rgba(78, 115, 223, 1)",
                        pointHoverBorderColor: "rgba(78, 115, 223, 1)",
                        pointHitRadius: 10,
                        pointBorderWidth: 2,
                        data: ' . json_encode($trend_data) . ',
                    }],
                },
                options: {
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
            
            // Ventana modal
            var modal = document.getElementById("size-modal");
            var closeBtn = document.getElementsByClassName("reserva-modal-close")[0];
            
            // Cerrar la modal cuando se hace clic en la X
            closeBtn.onclick = function() {
                modal.style.display = "none";
            }
            
            // Cerrar la modal cuando se hace clic fuera de ella
            window.onclick = function(event) {
                if (event.target == modal) {
                    modal.style.display = "none";
                }
            }
            
            // Manejar clic en "Ver Detalle por Tallas"
            $(".view-details").on("click", function(e) {
                e.preventDefault();
                var product = $(this).data("product");
                var productTitle = $(this).closest(".reserva-product-card").find("h2").text();
                $("#product-title").text(productTitle);
                
                // Hacer una llamada AJAX para obtener los detalles de tallas
                $.ajax({
                    url: ajaxurl,
                    type: "POST",
                    data: {
                        action: "get_product_size_details",
                        product: product,
                        security: "' . wp_create_nonce('get_product_size_details_nonce') . '"
                    },
                    success: function(response) {
                        if (response.success) {
                            $("#size-data").html(response.data.html);
                            
                            // Mostrar la modal
                            modal.style.display = "block";
                            
                            // Crear gráfico de distribución de tallas
                            var sizeCtx = document.getElementById("sizeDistribution").getContext("2d");
                            var sizeChart;
                            
                            // Destruir el gráfico anterior si existe
                            if (window.sizeChart) {
                                window.sizeChart.destroy();
                            }
                            
                            window.sizeChart = new Chart(sizeCtx, {
                                type: "bar",
                                data: {
                                    labels: response.data.labels,
                                    datasets: [{
                                        label: "Cantidad por Talla",
                                        backgroundColor: "#36b9cc",
                                        hoverBackgroundColor: "#2c9faf",
                                        borderColor: "#36b9cc",
                                        data: response.data.values,
                                    }],
                                },
                                options: {
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: {
                                            display: false
                                        }
                                    },
                                    scales: {
                                        y: {
                                            beginAtZero: true,
                                            ticks: {
                                                stepSize: 1
                                            }
                                        }
                                    }
                                }
                            });
                        } else {
                            alert("Error al obtener los detalles: " + response.data.message);
                        }
                    },
                    error: function() {
                        alert("Error en la comunicación con el servidor");
                    }
                });
            });
        });
    </script>';
}

/**
 * Función para obtener estadísticas de productos
 */
function reserva_get_product_stats() {
    global $wpdb;
    
    $stats = array();
    
    // Obtener productos reservados desde la tabla de items
    $sql = "SELECT ri.producto_slug, ri.talla, SUM(ri.cantidad) as cantidad_total, SUM(ri.precio * ri.cantidad) as total 
           FROM {$wpdb->prefix}reservas_items ri 
           GROUP BY ri.producto_slug, ri.talla";
    
    $resultados = $wpdb->get_results($sql);
    
    // Organizar resultados por producto
    foreach ($resultados as $resultado) {
        $slug = $resultado->producto_slug;
        $talla = $resultado->talla;
        
        if (!isset($stats[$slug])) {
            $stats[$slug] = array(
                'cantidad' => 0,
                'total' => 0,
                'tallas' => array()
            );
        }
        
        $stats[$slug]['cantidad'] += $resultado->cantidad_total;
        $stats[$slug]['total'] += $resultado->total;
        
        if (!isset($stats[$slug]['tallas'][$talla])) {
            $stats[$slug]['tallas'][$talla] = array(
                'cantidad' => 0,
                'total' => 0
            );
        }
        
        $stats[$slug]['tallas'][$talla]['cantidad'] += $resultado->cantidad_total;
        $stats[$slug]['tallas'][$talla]['total'] += $resultado->total;
    }
    
    // Obtener datos de las reservas directamente (para productos de WooCommerce)
    $reservas = $wpdb->get_results("SELECT id, productos, product_details FROM {$wpdb->prefix}reservas");
    
    foreach ($reservas as $reserva) {
        // Intentar primero con product_details (formato más nuevo)
        $detalles = json_decode($reserva->product_details, true);
        
        // Si no hay datos en product_details, intentar con productos
        if (empty($detalles) || !is_array($detalles)) {
            $detalles = json_decode($reserva->productos, true);
        }
        
        if (is_array($detalles)) {
            foreach ($detalles as $detalle) {
                // Verificar si es un producto de WooCommerce
                $is_wc_product = isset($detalle['producto_wc']) && $detalle['producto_wc'] === true;
                
                // Obtener el slug del producto
                $slug = isset($detalle['producto_slug']) ? $detalle['producto_slug'] : '';
                
                // Si no hay slug pero hay ID de producto WooCommerce, intentar obtenerlo
                if (empty($slug) && $is_wc_product && isset($detalle['producto_id']) && function_exists('wc_get_product')) {
                    $product = wc_get_product($detalle['producto_id']);
                    if ($product) {
                        $slug = $product->get_slug();
                    }
                }
                
                // Si aún no tenemos slug, usar el nombre del producto como identificador
                if (empty($slug) && isset($detalle['producto'])) {
                    $slug = sanitize_title($detalle['producto']);
                }
                
                if (!empty($slug)) {
                    $talla = isset($detalle['talla']) ? $detalle['talla'] : 'Sin talla';
                    $cantidad = isset($detalle['cantidad']) ? intval($detalle['cantidad']) : 0;
                    $precio = isset($detalle['precio']) ? floatval($detalle['precio']) : 0;
                    $subtotal = isset($detalle['subtotal']) ? floatval($detalle['subtotal']) : ($precio * $cantidad);
                    
                    // Inicializar el producto en las estadísticas si no existe
                    if (!isset($stats[$slug])) {
                        $stats[$slug] = array(
                            'cantidad' => 0,
                            'total' => 0,
                            'tallas' => array(),
                            'woocommerce' => $is_wc_product
                        );
                    }
                    
                    // Actualizar estadísticas generales del producto
                    $stats[$slug]['cantidad'] += $cantidad;
                    $stats[$slug]['total'] += $subtotal;
                    $stats[$slug]['woocommerce'] = $stats[$slug]['woocommerce'] || $is_wc_product;
                    
                    // Actualizar estadísticas por talla
                    if (!isset($stats[$slug]['tallas'][$talla])) {
                        $stats[$slug]['tallas'][$talla] = array(
                            'cantidad' => 0,
                            'total' => 0
                        );
                    }
                    
                    $stats[$slug]['tallas'][$talla]['cantidad'] += $cantidad;
                    $stats[$slug]['tallas'][$talla]['total'] += $subtotal;
                }
            }
        }
    }
    
    return $stats;
}

/**
 * Obtener información de un producto para el dashboard
 */
function reserva_get_product_info_for_dashboard($slug) {
    // Primero intentar obtener desde WooCommerce
    if (function_exists('reserva_is_woocommerce_active') && reserva_is_woocommerce_active()) {
        // Intentar obtener el producto por slug primero
        error_log("Buscando producto WooCommerce con slug: {$slug}");
        
        if (function_exists('reserva_get_woocommerce_product_by_slug')) {
            $product = reserva_get_woocommerce_product_by_slug($slug);
            
            if ($product) {
                error_log("Producto WooCommerce encontrado por slug: " . $product->get_name());
                return array(
                    'nombre' => $product->get_name(),
                    'imagen_url' => wp_get_attachment_url($product->get_image_id()) ?: plugins_url('assets/images/product-placeholder.jpg', dirname(__FILE__))
                );
            }
        }
        
        // Intentar buscar por la función estándar de WooCommerce
        $wc_products = wc_get_products(array(
            'status' => 'publish',
            'limit' => 1,
            'slug' => $slug
        ));
        
        if (!empty($wc_products)) {
            $product = $wc_products[0];
            error_log("Producto WooCommerce encontrado por wc_get_products: " . $product->get_name());
            return array(
                'nombre' => $product->get_name(),
                'imagen_url' => wp_get_attachment_url($product->get_image_id()) ?: plugins_url('assets/images/product-placeholder.jpg', dirname(__FILE__))
            );
        }
    }
    
    // Si no se encuentra en WooCommerce, buscar en la base de datos antigua
    global $wpdb;
    $producto = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}reservas_productos WHERE slug = %s",
        $slug
    ));
    
    if ($producto) {
        return array(
            'nombre' => $producto->nombre,
            'imagen_url' => $producto->imagen_url ?: plugins_url('assets/images/product-placeholder.jpg', dirname(__FILE__))
        );
    }
    
    // Fallback para productos conocidos
    $productos_conocidos = array(
        'pantalon-buzo' => array(
            'nombre' => 'Pantalón Buzo Alianza Francesa',
            'imagen_url' => plugins_url('assets/images/poleronypantalon.jpg', dirname(__FILE__))
        ),
        'polera' => array(
            'nombre' => 'Polera Deporte M/C Alianza Francesa',
            'imagen_url' => plugins_url('assets/images/polera.jpg', dirname(__FILE__))
        ),
        'poleron' => array(
            'nombre' => 'Polerón Buzo Alianza Francesa',
            'imagen_url' => plugins_url('assets/images/poleronypantalon.jpg', dirname(__FILE__))
        )
    );
    
    return isset($productos_conocidos[$slug]) ? $productos_conocidos[$slug] : array();
}

/**
 * Función AJAX para obtener detalles de tallas de productos
 */
function reserva_ajax_get_product_size_details() {
    // Verificar nonce
    check_ajax_referer('get_product_size_details_nonce', 'security');
    
    // Verificar permisos
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Permisos insuficientes'));
        return;
    }
    
    $product = isset($_POST['product']) ? sanitize_text_field($_POST['product']) : '';
    
    if (empty($product)) {
        wp_send_json_error(array('message' => 'Producto no especificado'));
        return;
    }
    
    error_log("Obteniendo detalles de tallas para producto: {$product}");
    
    global $wpdb;
    
    // Estructura para almacenar conteo de tallas y valores
    $tallas = array();
    $total_general = 0;
    
    // 1. Buscar primero en la tabla de reservas usando product_details (campo JSON)
    // Esto captura productos de WooCommerce y productos antiguos en el nuevo formato
    $reservas = $wpdb->get_results(
        "SELECT id, productos, product_details FROM {$wpdb->prefix}reservas"
    );
    
    error_log("Total de reservas encontradas: " . count($reservas));
    
    foreach ($reservas as $reserva) {
        // Intentar primero con product_details (formato más nuevo)
        $detalles = json_decode($reserva->product_details, true);
        
        // Si no hay datos en product_details, intentar con productos
        if (empty($detalles) || !is_array($detalles)) {
            $detalles = json_decode($reserva->productos, true);
        }
        
        if (is_array($detalles)) {
            foreach ($detalles as $detalle) {
                // Verificar si corresponde al producto buscado
                $es_este_producto = false;
                
                if (isset($detalle['producto_slug']) && $detalle['producto_slug'] === $product) {
                    $es_este_producto = true;
                }
                
                if ($es_este_producto && isset($detalle['talla'], $detalle['cantidad'], $detalle['subtotal'])) {
                    $talla = $detalle['talla'];
                    $cantidad = intval($detalle['cantidad']);
                    $subtotal = floatval($detalle['subtotal']);
                    
                    if (!isset($tallas[$talla])) {
                        $tallas[$talla] = array(
                            'cantidad' => 0,
                            'total' => 0
                        );
                    }
                    
                    $tallas[$talla]['cantidad'] += $cantidad;
                    $tallas[$talla]['total'] += $subtotal;
                    $total_general += $subtotal;
                    
                    error_log("Detalle encontrado para {$product}: Talla {$talla}, Cantidad {$cantidad}, Subtotal {$subtotal}");
                }
            }
        }
    }
    
    // 2. Buscar también en la tabla antigua de items para compatibilidad
    $sql = $wpdb->prepare(
        "SELECT ri.talla, SUM(ri.cantidad) as cantidad_total, SUM(ri.precio * ri.cantidad) as total 
         FROM {$wpdb->prefix}reservas_items ri 
         WHERE ri.producto_slug = %s
         GROUP BY ri.talla",
        $product
    );
    
    $resultados = $wpdb->get_results($sql);
    error_log("Resultados de tabla antigua: " . count($resultados));
    
    foreach ($resultados as $resultado) {
        $talla = $resultado->talla;
        $cantidad = $resultado->cantidad_total;
        $total = $resultado->total;
        
        $tallas[$talla] = array(
            'cantidad' => $cantidad,
            'total' => $total
        );
        
        $total_general += $total;
    }
    
    // Generar HTML para la tabla de tallas
    $html = '<table class="size-table">';
    $html .= '<thead>
                <tr>
                    <th>Talla</th>
                    <th>Cantidad</th>
                    <th>Precio Unitario</th>
                    <th>Subtotal</th>
                </tr>
              </thead>';
    $html .= '<tbody>';
    
    // Arrays para el gráfico
    $labels = array();
    $values = array();
    
    // Mostrar tallas en orden
    $tallas_ordenadas = array('4', '6', '8', '10', '12', '14', 'XS', 'S', 'M', 'L', 'XL');
    
    foreach ($tallas_ordenadas as $talla) {
        if (isset($tallas[$talla]) && $tallas[$talla]['cantidad'] > 0) {
            $precio_unitario = $tallas[$talla]['cantidad'] > 0 ? $tallas[$talla]['total'] / $tallas[$talla]['cantidad'] : 0;
            
            $html .= '<tr>';
            $html .= '<td>' . esc_html($talla) . '</td>';
            $html .= '<td>' . esc_html($tallas[$talla]['cantidad']) . '</td>';
            $html .= '<td>$' . number_format($precio_unitario, 0, ',', '.') . '</td>';
            $html .= '<td class="size-subtotal">$' . number_format($tallas[$talla]['total'], 0, ',', '.') . '</td>';
            $html .= '</tr>';
            
            $labels[] = 'Talla ' . $talla;
            $values[] = $tallas[$talla]['cantidad'];
        }
    }
    
    // Agregar fila de total
    $html .= '</tbody>';
    $html .= '<tfoot>';
    $html .= '<tr>';
    $html .= '<td colspan="3">Total General:</td>';
    $html .= '<td class="size-subtotal">$' . number_format($total_general, 0, ',', '.') . '</td>';
    $html .= '</tr>';
    $html .= '</tfoot>';
    $html .= '</table>';
    
    wp_send_json_success(array(
        'html' => $html,
        'labels' => $labels,
        'values' => $values
    ));
}
add_action('wp_ajax_get_product_size_details', 'reserva_ajax_get_product_size_details');