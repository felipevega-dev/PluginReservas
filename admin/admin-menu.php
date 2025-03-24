<?php
// Evitar acceso directo
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Función para agregar el menú de administración
 */
function reserva_admin_menu() {
    // Añadir menú principal
    add_menu_page(
        'Reservas', // Título de la página
        'Reservas', // Texto del menú
        'manage_options', // Capacidad requerida
        'reservas', // Slug del menú
        'reserva_admin_dashboard', // Función de callback
        'dashicons-calendar-alt', // Icono
        30 // Posición
    );
    
    // Añadir submenú para listar reservas
    add_submenu_page(
        'reservas', // Parent slug
        'Lista de Reservas', // Título de la página
        'Lista de Reservas', // Texto del menú
        'manage_options', // Capacidad requerida
        'reserva-lista', // Slug del menú
        'mostrar_lista_reservas' // Función de callback
    );
}
add_action( 'admin_menu', 'reserva_admin_menu' );

/**
 * Función para verificar las capacidades del administrador
 */
function reserva_verify_admin_capabilities() {
    // Verificar si el usuario tiene los permisos necesarios
    if (!current_user_can('manage_options')) {
        wp_die(__('No tienes suficientes permisos para acceder a esta página.'));
    }
}

/**
 * Función para mostrar el dashboard principal
 */
function reserva_admin_dashboard() {
    // Verificar permisos
    reserva_verify_admin_capabilities();
    
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
    
    // Imágenes de productos
    $imagenes = array(
        'pantalon-buzo' => plugins_url('assets/images/poleronypantalon.jpg', dirname(__FILE__)),
        'polera' => plugins_url('assets/images/polera.jpg', dirname(__FILE__)),
        'poleron' => plugins_url('assets/images/poleronypantalon.jpg', dirname(__FILE__))
    );
    
    // Mostrar estadísticas de productos
    foreach ($product_stats as $slug => $info) {
        $product_name = '';
        switch ($slug) {
            case 'pantalon-buzo':
                $product_name = 'Pantalón Buzo Alianza Francesa';
                break;
            case 'polera':
                $product_name = 'Polera Deporte M/C Alianza Francesa';
                break;
            case 'poleron':
                $product_name = 'Polerón Buzo Alianza Francesa';    
                break;
        }
        
        if (!empty($product_name)) {
            echo '<div class="reserva-stat-card reserva-product-card">';
            echo '<div class="card-header">';
            echo '<div class="card-img"><img src="' . esc_url($imagenes[$slug]) . '" alt="' . esc_attr($product_name) . '"></div>';
            echo '</div>';
            echo '<div class="card-content">';
            echo '<h2>' . esc_html($product_name) . '</h2>';
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
    
    // Datos para los gráficos
    $product_data = array();
    $product_values = array();
    $product_labels = array();
    $product_colors = array('#4e73df', '#1cc88a', '#36b9cc');
    $i = 0;
    
    foreach ($product_stats as $slug => $info) {
        $product_name = '';
        switch ($slug) {
            case 'pantalon-buzo':
                $product_name = 'Pantalones';
                break;
            case 'polera':
                $product_name = 'Poleras';
                break;
            case 'poleron':
                $product_name = 'Polerones';
                break;
        }
        
        if (!empty($product_name)) {
            $product_data[] = $info['cantidad'];
            $product_values[] = $info['total'];
            $product_labels[] = $product_name;
            $i++;
        }
    }
    
    // Datos simulados para tendencia de ventas
    $trend_labels = array();
    $trend_data = array();
    
    // Crear datos de los últimos 7 días
    for ($i = 6; $i >= 0; $i--) {
        $date = date('d/m', strtotime("-$i days"));
        $trend_labels[] = $date;
        // Simular valores aleatorios entre 1 y 5 para las ventas diarias
        $trend_data[] = rand(1, 5);
    }
    
    // JavaScript para manejar la interacción y gráficos
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
    $table_name = $wpdb->prefix . 'reservas';
    $products = array(
        'pantalon-buzo' => array('cantidad' => 0, 'total' => 0),
        'polera' => array('cantidad' => 0, 'total' => 0),
        'poleron' => array('cantidad' => 0, 'total' => 0)
    );
    
    // Obtener todas las reservas con detalles de productos
    $reservas = $wpdb->get_results("SELECT productos FROM $table_name WHERE productos IS NOT NULL");
    
    foreach ($reservas as $reserva) {
        $detalles = json_decode($reserva->productos, true);
        
        if (is_array($detalles)) {
            foreach ($detalles as $detalle) {
                $producto_nombre = $detalle['producto'];
                $cantidad = intval($detalle['cantidad']);
                $precio_unitario = isset($detalle['precio']) ? floatval($detalle['precio']) : 0;
                $subtotal = isset($detalle['subtotal']) ? floatval($detalle['subtotal']) : ($cantidad * $precio_unitario);
                
                // Determinar el slug del producto basado en el nombre
                $producto_slug = '';
                if (strpos($producto_nombre, 'Pantalón') !== false) {
                    $producto_slug = 'pantalon-buzo';
                } elseif (strpos($producto_nombre, 'Polera') !== false) {
                    $producto_slug = 'polera';
                } elseif (strpos($producto_nombre, 'Polerón') !== false) {
                    $producto_slug = 'poleron';
                }
                
                // Incrementar el contador para este producto
                if (isset($products[$producto_slug])) {
                    $products[$producto_slug]['cantidad'] += $cantidad;
                    $products[$producto_slug]['total'] += $subtotal;
                }
            }
        }
    }
    
    return $products;
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
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'reservas';
    
    // Obtener todas las reservas con detalles de productos
    $reservas = $wpdb->get_results("SELECT product_details FROM $table_name WHERE product_details IS NOT NULL");
    
    // Estructura para almacenar conteo de tallas y valores
    $tallas = array(
        '4' => array('cantidad' => 0, 'total' => 0),
        '6' => array('cantidad' => 0, 'total' => 0),
        '8' => array('cantidad' => 0, 'total' => 0),
        '10' => array('cantidad' => 0, 'total' => 0),
        '12' => array('cantidad' => 0, 'total' => 0),
        '14' => array('cantidad' => 0, 'total' => 0),
        'XS' => array('cantidad' => 0, 'total' => 0),
        'S' => array('cantidad' => 0, 'total' => 0),
        'M' => array('cantidad' => 0, 'total' => 0),
        'L' => array('cantidad' => 0, 'total' => 0),
        'XL' => array('cantidad' => 0, 'total' => 0)
    );
    
    $total_general = 0;
    
    foreach ($reservas as $reserva) {
        $detalles = json_decode($reserva->product_details, true);
        
        if (is_array($detalles)) {
            foreach ($detalles as $detalle) {
                $producto_nombre = $detalle['producto'];
                $talla = $detalle['talla'];
                $cantidad = intval($detalle['cantidad']);
                $precio_unitario = isset($detalle['unitPrice']) ? floatval($detalle['unitPrice']) : 0;
                $subtotal = $cantidad * $precio_unitario;
                
                // Determinar si este producto coincide con el solicitado
                $producto_slug = '';
                if (strpos($producto_nombre, 'Pantalón') !== false) {
                    $producto_slug = 'pantalon-buzo';
                } elseif (strpos($producto_nombre, 'Polera') !== false) {
                    $producto_slug = 'polera';
                } elseif (strpos($producto_nombre, 'Polerón') !== false) {
                    $producto_slug = 'poleron';
                }
                
                // Si este producto coincide con el solicitado, sumar la cantidad y valor a la talla correspondiente
                if ($producto_slug === $product && isset($tallas[$talla])) {
                    $tallas[$talla]['cantidad'] += $cantidad;
                    $tallas[$talla]['total'] += $subtotal;
                    $total_general += $subtotal;
                }
            }
        }
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
    
    // Primero mostrar tallas numéricas en orden
    foreach (['4', '6', '8', '10', '12', '14'] as $talla) {
        if ($tallas[$talla]['cantidad'] > 0) {
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
    
    // Luego mostrar tallas de letras
    foreach (['XS', 'S', 'M', 'L', 'XL'] as $talla) {
        if ($tallas[$talla]['cantidad'] > 0) {
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
