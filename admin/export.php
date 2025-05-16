<?php
/**
 * Funcionalidad de exportación para reservas
 *
 * @package ReservaPlugin
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Exporta las reservas a un archivo CSV o Excel
 */
function reserva_export_csv() {
    // Asegúrate de que no haya salida antes de enviar los encabezados
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Establecer codificación UTF-8 para todo el proceso
    if (function_exists('mb_internal_encoding')) {
        mb_internal_encoding('UTF-8');
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'reservas';
    
    // Aplicar filtros si están presentes
    $where_conditions = array();
    $where_values = array();
    
    if (isset($_GET['s']) && !empty($_GET['s'])) {
        $search_query = sanitize_text_field($_GET['s']);
        $like = '%' . $wpdb->esc_like($search_query) . '%';
        $where_conditions[] = '(nombre LIKE %s OR email LIKE %s OR productos LIKE %s)';
        $where_values[] = $like;
        $where_values[] = $like;
        $where_values[] = $like;
    }
    
    if (isset($_GET['fecha_desde']) && !empty($_GET['fecha_desde'])) {
        $where_conditions[] = 'fecha >= %s';
        $where_values[] = sanitize_text_field($_GET['fecha_desde']);
    }
    
    if (isset($_GET['fecha_hasta']) && !empty($_GET['fecha_hasta'])) {
        $where_conditions[] = 'fecha <= %s';
        $where_values[] = sanitize_text_field($_GET['fecha_hasta']);
    }
    
    if (isset($_GET['comuna']) && !empty($_GET['comuna'])) {
        $where_conditions[] = 'comuna = %s';
        $where_values[] = sanitize_text_field($_GET['comuna']);
    }
    
    if (isset($_GET['precio_min']) && !empty($_GET['precio_min'])) {
        $where_conditions[] = 'total >= %d';
        $where_values[] = intval($_GET['precio_min']);
    }
    
    if (isset($_GET['precio_max']) && !empty($_GET['precio_max'])) {
        $where_conditions[] = 'total <= %d';
        $where_values[] = intval($_GET['precio_max']);
    }
    
    // Ejecutar la consulta con los filtros aplicados
    if (!empty($where_conditions)) {
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        array_unshift($where_values, "SELECT * FROM $table_name $where_clause ORDER BY fecha_registro DESC");
        $reservas = $wpdb->get_results($wpdb->prepare(...$where_values), ARRAY_A);
    } else {
        $reservas = $wpdb->get_results("SELECT * FROM $table_name ORDER BY fecha_registro DESC", ARRAY_A);
    }
    
    // Definir el tipo de exportación (detallada o resumida)
    $export_type = isset($_GET['export_type']) ? sanitize_text_field($_GET['export_type']) : 'detailed';
    $format = isset($_GET['format']) ? sanitize_text_field($_GET['format']) : 'csv';
    
    // Log para depuración (opcional)
    error_log('Exportando reservas en formato: ' . $format . ', tipo: ' . $export_type);
    
    // Verificar si se requiere formato Excel avanzado
    if ($format === 'excel') {
        // Comprobar si PhpSpreadsheet está disponible
        $phpspreadsheet_info = reserva_check_phpspreadsheet(true);
        
        if ($phpspreadsheet_info['available']) {
            error_log('PhpSpreadsheet está disponible, usando formato Excel avanzado');
            enhanced_excel_export($reservas, $export_type);
        } else {
            error_log('PhpSpreadsheet no está disponible, revirtiendo a CSV: ' . print_r($phpspreadsheet_info, true));
            // Revertir a formato CSV si la librería no está disponible
            if ($export_type === 'summary') {
                export_product_summary_csv($reservas);
            } else {
                export_detailed_reservations_csv($reservas);
            }
        }
    } else {
        // Formato CSV estándar
        if ($export_type === 'summary') {
            export_product_summary_csv($reservas);
        } else {
            export_detailed_reservations_csv($reservas);
        }
    }
    
    exit; // Asegurarse de terminar la ejecución
}

/**
 * Exporta un resumen por productos y tallas
 */
function export_product_summary_csv($reservas) {
    // Definir el nombre del archivo
    $filename = 'resumen-productos-' . date('Y-m-d') . '.csv';
    
    // Prevenir caché
    nocache_headers();
    
    // Limpiar cualquier búfer de salida
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Establecer la codificación interna
    if (function_exists('mb_internal_encoding')) {
        mb_internal_encoding('UTF-8');
    }
    
    // Preparar el archivo CSV
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Abrir archivo de salida
    $output = fopen('php://output', 'w');
    
    // BOM para UTF-8 - Esto es crítico para Excel y caracteres especiales
    fwrite($output, "\xEF\xBB\xBF");
    
    // Separador para Excel
    fputcsv($output, array("sep=,"));
    
    // Titulo del informe
    fputcsv($output, array("RESUMEN DE PRODUCTOS POR TALLA"));
    fputcsv($output, array("Fecha del informe: " . date('d/m/Y')));
    fputcsv($output, array("")); // Línea en blanco
    
    // Cabeceras del resumen
    fputcsv($output, array(
        'Producto',
        'Talla',
        'Cantidad',
        'Precio Unitario',
        'Monto Total',
    ));
    
    // Estructura para productos
    $productos_data = array(
        'Pantalón Buzo Alianza Francesa' => array(),
        'Polera Deporte M/C Alianza Francesa' => array(),
        'Polerón Buzo Alianza Francesa' => array()
    );
    
    // Procesar todas las reservas
    foreach ($reservas as $reserva) {
        $detalles = json_decode($reserva['productos'], true);
        
        if (is_array($detalles)) {
            foreach ($detalles as $detalle) {
                if (!isset($detalle['producto'], $detalle['talla'], $detalle['cantidad'])) {
                    continue;
                }
                
                $producto = $detalle['producto'];
                $talla = $detalle['talla'];
                $cantidad = intval($detalle['cantidad']);
                $precio_unitario = isset($detalle['precio']) ? floatval($detalle['precio']) : 0;
                $subtotal = isset($detalle['subtotal']) ? floatval($detalle['subtotal']) : ($precio_unitario * $cantidad);
                
                // Inicializar datos si no existen
                if (!isset($productos_data[$producto][$talla])) {
                    $productos_data[$producto][$talla] = array(
                        'cantidad' => 0,
                        'total' => 0,
                    );
                }
                
                // Actualizar datos
                $productos_data[$producto][$talla]['cantidad'] += $cantidad;
                $productos_data[$producto][$talla]['total'] += $subtotal;
            }
        }
    }
    
    // Tallas a mostrar en orden
    $tallas_orden = array('4', '6', '8', '10', '12', '14', 'XS', 'S', 'M', 'L', 'XL');
    
    // Escribir los datos resumidos
    foreach ($productos_data as $producto => $tallas) {
        $producto_total_cantidad = 0;
        $producto_total_monto = 0;
        
        // Calcular totales del producto
        foreach ($tallas as $talla_data) {
            $producto_total_cantidad += $talla_data['cantidad'];
            $producto_total_monto += $talla_data['total'];
        }
        
        // Escribir fila de resumen del producto
        fputcsv($output, array(
            $producto,
            'TOTAL',
            $producto_total_cantidad,
            ($producto_total_cantidad > 0) ? number_format($producto_total_monto / $producto_total_cantidad, 0, ',', '.') : 0,
            number_format($producto_total_monto, 0, ',', '.')
        ));
        
        // Escribir detalle por tallas en orden
        foreach ($tallas_orden as $talla) {
            if (isset($tallas[$talla]) && $tallas[$talla]['cantidad'] > 0) {
                $precio_unitario_promedio = $tallas[$talla]['cantidad'] > 0 ? 
                    $tallas[$talla]['total'] / $tallas[$talla]['cantidad'] : 0;
                
                fputcsv($output, array(
                    '', // Producto vacío para indentar
                    $talla,
                    $tallas[$talla]['cantidad'],
                    number_format($precio_unitario_promedio, 0, ',', '.'),
                    number_format($tallas[$talla]['total'], 0, ',', '.')
                ));
            }
        }
        
        // Línea en blanco entre productos
        fputcsv($output, array(''));
    }
    
    // Añadir resumen general
    $total_global_cantidad = 0;
    $total_global_monto = 0;
    
    foreach ($productos_data as $producto => $tallas) {
        foreach ($tallas as $talla_data) {
            $total_global_cantidad += $talla_data['cantidad'];
            $total_global_monto += $talla_data['total'];
        }
    }
    
    fputcsv($output, array('TOTAL GENERAL', '', $total_global_cantidad, '', '$' . number_format($total_global_monto, 0, ',', '.')));
    
    fclose($output);
    exit;
}

/**
 * Exporta todas las reservas detalladas
 */
function export_detailed_reservations_csv($reservas) {
    // Definir el nombre del archivo
    $filename = 'reservas-detalladas-' . date('Y-m-d') . '.csv';
    
    // Prevenir caché
    nocache_headers();
    
    // Limpiar cualquier buffer de salida
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Establecer la codificación interna
    if (function_exists('mb_internal_encoding')) {
        mb_internal_encoding('UTF-8');
    }
    
    // Preparar el archivo CSV
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // BOM para UTF-8 - Esto es crítico para Excel y caracteres especiales
    fwrite($output, "\xEF\xBB\xBF");
    
    // Separador para Excel
    fputcsv($output, array("sep=,"));
    
    // Primera sección: Resumen por productos y tallas
    // Encabezado para la sección de productos
    fputcsv($output, array("RESUMEN DE PRODUCTOS POR TALLA"));
    fputcsv($output, array("Fecha del informe: " . date('d/m/Y')));
    fputcsv($output, array(""));
    
    // Cabeceras del resumen de productos
    fputcsv($output, array(
        'Producto',
        'Talla',
        'Cantidad',
        'Precio Unitario',
        'Monto Total',
    ));
    
    // Estructura para productos
    $productos_data = array(
        'Pantalón Buzo Alianza Francesa' => array(),
        'Polera Deporte M/C Alianza Francesa' => array(),
        'Polerón Buzo Alianza Francesa' => array()
    );
    
    // Procesar todas las reservas para el resumen de productos
    foreach ($reservas as $reserva) {
        $detalles = json_decode($reserva['productos'], true);
        
        if (is_array($detalles)) {
            foreach ($detalles as $detalle) {
                if (!isset($detalle['producto'], $detalle['talla'], $detalle['cantidad'])) {
                    continue;
                }
                
                $producto = $detalle['producto'];
                $talla = $detalle['talla'];
                $cantidad = intval($detalle['cantidad']);
                $precio_unitario = isset($detalle['precio']) ? floatval($detalle['precio']) : 0;
                $subtotal = isset($detalle['subtotal']) ? floatval($detalle['subtotal']) : ($precio_unitario * $cantidad);
                
                // Inicializar datos si no existen
                if (!isset($productos_data[$producto][$talla])) {
                    $productos_data[$producto][$talla] = array(
                        'cantidad' => 0,
                        'total' => 0,
                    );
                }
                
                // Actualizar datos
                $productos_data[$producto][$talla]['cantidad'] += $cantidad;
                $productos_data[$producto][$talla]['total'] += $subtotal;
            }
        }
    }
    
    // Tallas a mostrar en orden
    $tallas_orden = array('4', '6', '8', '10', '12', '14', 'XS', 'S', 'M', 'L', 'XL');
    
    // Escribir los datos resumidos de productos
    foreach ($productos_data as $producto => $tallas) {
        $producto_total_cantidad = 0;
        $producto_total_monto = 0;
        
        // Calcular totales del producto
        foreach ($tallas as $talla_data) {
            $producto_total_cantidad += $talla_data['cantidad'];
            $producto_total_monto += $talla_data['total'];
        }
        
        // Escribir fila de resumen del producto
        fputcsv($output, array(
            $producto,
            'TOTAL',
            $producto_total_cantidad,
            ($producto_total_cantidad > 0) ? number_format($producto_total_monto / $producto_total_cantidad, 0, ',', '.') : 0,
            number_format($producto_total_monto, 0, ',', '.')
        ));
        
        // Escribir detalle por tallas en orden
        foreach ($tallas_orden as $talla) {
            if (isset($tallas[$talla]) && $tallas[$talla]['cantidad'] > 0) {
                $precio_unitario_promedio = $tallas[$talla]['cantidad'] > 0 ? 
                    $tallas[$talla]['total'] / $tallas[$talla]['cantidad'] : 0;
                
                fputcsv($output, array(
                    '', // Producto vacío para indentar
                    $talla,
                    $tallas[$talla]['cantidad'],
                    number_format($precio_unitario_promedio, 0, ',', '.'),
                    number_format($tallas[$talla]['total'], 0, ',', '.')
                ));
            }
        }
        
        // Línea en blanco entre productos
        fputcsv($output, array(''));
    }
    
    // Añadir resumen general de productos
    $total_global_cantidad = 0;
    $total_global_monto = 0;
    
    foreach ($productos_data as $producto => $tallas) {
        foreach ($tallas as $talla_data) {
            $total_global_cantidad += $talla_data['cantidad'];
            $total_global_monto += $talla_data['total'];
        }
    }
    
    fputcsv($output, array('TOTAL GENERAL', '', $total_global_cantidad, '', '$' . number_format($total_global_monto, 0, ',', '.')));
    
    // Separador entre secciones
    fputcsv($output, array(''));
    fputcsv($output, array(''));
    fputcsv($output, array('DETALLE DE CLIENTES Y RESERVAS'));
    fputcsv($output, array(''));
    
    // Segunda sección: Detalle de reservas de clientes
    // Definir cabeceras para detalles de clientes
    fputcsv($output, array(
        'ID', 
        'Nombre', 
        'Email', 
        'Teléfono',
        'Dirección', 
        'Comuna', 
        'Fecha Necesidad', 
        'Total', 
        'Fecha Registro',
        'Productos',
        'Observaciones'
    ));
    
    foreach ($reservas as $reserva) {
        // Formatear productos para mejor legibilidad
        $productos_formateados = '';
        $detalles_productos = json_decode($reserva['productos'], true);
        
        if (is_array($detalles_productos)) {
            foreach ($detalles_productos as $index => $detalle) {
                // Convertir explícitamente a UTF-8
                $producto_nombre = html_entity_decode($detalle['producto'], ENT_QUOTES, 'UTF-8');
                $talla_nombre = html_entity_decode($detalle['talla'], ENT_QUOTES, 'UTF-8');
                
                if ($index > 0) {
                    $productos_formateados .= " | ";
                }
                $productos_formateados .= $producto_nombre . " - Talla: " . $talla_nombre . " - Cant: " . $detalle['cantidad'];
            }
        } else {
            $productos_formateados = $reserva['productos'];
        }
        
        fputcsv($output, array(
            $reserva['id'],
            $reserva['nombre'],
            $reserva['email'],
            $reserva['telefono'],
            $reserva['direccion'],
            $reserva['comuna'],
            export_format_fecha($reserva['fecha'], false),
            '$' . number_format($reserva['total'], 0, ',', '.'),
            export_format_fecha($reserva['fecha_registro'], false),
            $productos_formateados,
            isset($reserva['observaciones']) ? $reserva['observaciones'] : ''
        ));
    }
    
    // Pie de página
    fputcsv($output, array(''));
    fputcsv($output, array('Informe generado el ' . date('d/m/Y') . ' a las ' . date('H:i:s')));
    
    fclose($output);
    exit;
}

/**
 * Formatea fecha para CSV (implementación interna)
 */
function export_format_fecha($fecha_str, $html_format = true) {
    // Verificar si la función original está disponible
    if (function_exists('format_fecha') && $fecha_str != format_fecha($fecha_str, false)) {
        // Usar la implementación de helpers.php que maneja meses en español
        return format_fecha($fecha_str, false);
    }
    
    if (empty($fecha_str)) return '';
    
    $timestamp = strtotime($fecha_str);
    
    // Formatear con el patrón d/m/Y para asegurar consistencia
    return date('d/m/Y', $timestamp);
}

/**
 * Crea un reporte Excel mejorado con formateo visual
 */
function enhanced_excel_export($reservas, $export_type) {
    // Asegurarse de que no haya salida antes de enviar los encabezados
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Establecer codificación interna en UTF-8 para manejar correctamente caracteres especiales
    if (function_exists('mb_internal_encoding')) {
        mb_internal_encoding('UTF-8');
    }
    
    // Verificar si la librería PhpSpreadsheet está disponible
    if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
        try {
            // Cargar la librería
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/libs/autoload.php';
        } catch (Exception $e) {
            // Si hay un error, intentar otra ruta
            try {
                require_once plugin_dir_path(dirname(__FILE__)) . 'includes/libs/vendor/autoload.php';
            } catch (Exception $e) {
                // Último intento
                try {
                    require_once plugin_dir_path(dirname(__FILE__)) . 'includes/libs/PhpSpreadsheet/bootstrap.php';
                } catch (Exception $e) {
                    // Si no se puede cargar, revertir a CSV
                    if ($export_type === 'summary') {
                        export_product_summary_csv($reservas);
                    } else {
                        export_detailed_reservations_csv($reservas);
                    }
                    return;
                }
            }
        }
    }
    
    // Si después de intentar cargar, aún no está disponible, revertir al CSV
    if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
        if ($export_type === 'summary') {
            export_product_summary_csv($reservas);
        } else {
            export_detailed_reservations_csv($reservas);
        }
        return;
    }
    
    try {
        // Iniciar PhpSpreadsheet
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        
        // Configuración específica para UTF-8
        // \PhpOffice\PhpSpreadsheet\Settings::setLocale('es'); // Temporarily comment out
        
        // Configurar codificación a UTF-8 para PhpSpreadsheet
        $spreadsheet->getProperties()
            // ->setCodepage(65001) // Código para UTF-8 // Temporarily comment out
            ->setCreator('Reserva Form Plugin')
            ->setTitle('Informe de Reservas')
            ->setDescription('Generado con caracteres UTF-8')
            ->setLastModifiedBy('Reserva Form Plugin')
            ->setCategory('Informes');
            
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'Prueba con Ñandú y Pingüino áéíóú'); // Test string
        
        // Colores corporativos para el informe
        $colorPrimario = '4b6cb7'; // Azul principal
        $colorSecundario = '182848'; // Azul oscuro
        $colorFondo = 'e8f0ff'; // Azul claro para fondos
        $colorResaltado = '36b9cc'; // Color para resaltar datos
        
        // Estilos comunes
        $titleStyle = [
            'font' => [
                'bold' => true,
                'size' => 18,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'color' => ['rgb' => $colorPrimario],
            ],
            'borders' => [
                'bottom' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM,
                    'color' => ['rgb' => $colorSecundario],
                ],
            ],
        ];
        
        $subtitleStyle = [
            'font' => [
                'bold' => true,
                'size' => 12,
                'color' => ['rgb' => $colorSecundario],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'color' => ['rgb' => $colorFondo],
            ],
        ];
        
        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 11,
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'color' => ['rgb' => $colorPrimario],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => 'CCCCCC'],
                ],
                'outline' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM,
                    'color' => ['rgb' => $colorSecundario],
                ],
            ],
        ];
        
        $totalRowStyle = [
            'font' => [
                'bold' => true,
                'size' => 11,
                'color' => ['rgb' => $colorSecundario],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT,
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'color' => ['rgb' => $colorFondo],
            ],
            'borders' => [
                'outline' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM,
                    'color' => ['rgb' => $colorSecundario],
                ],
                'top' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => $colorSecundario],
                ],
            ],
        ];
        
        $dataStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => 'CCCCCC'],
                ],
            ],
            'alignment' => [
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
        ];
        
        $alternatingRowStyle = [
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'color' => ['rgb' => 'F8F9FA'],
            ],
        ];
        
        $currencyFormat = '_($* #,##0_);[Red]_($* (#,##0);_($* "-"_);_(@_)';
        $quantityFormat = '#,##0';
        
        // Configurar columnas para la sección de productos
        $sheet->getColumnDimension('A')->setWidth(35);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(18);
        $sheet->getColumnDimension('D')->setWidth(20);
        $sheet->getColumnDimension('E')->setWidth(20);
        
        // Título del informe y ajustar altura
        // Usar mb_strtoupper para convertir a mayúsculas respetando caracteres especiales
        $titulo = $export_type === 'summary' ? 'Resumen de Productos por Talla' : 'Reporte Detallado de Reservas';
        if (function_exists('mb_strtoupper')) {
            $titulo = mb_strtoupper($titulo, 'UTF-8');
        }
        
        $sheet->setCellValue('A1', $titulo);
        $sheet->mergeCells('A1:E1');
        $sheet->getStyle('A1:E1')->applyFromArray($titleStyle);
        $sheet->getRowDimension('1')->setRowHeight(30);
        
        // Fecha del informe
        $sheet->setCellValue('A2', 'Fecha del informe: ' . date('d/m/Y'));
        $sheet->mergeCells('A2:E2');
        $sheet->getStyle('A2:E2')->applyFromArray($subtitleStyle);
        $sheet->getRowDimension('2')->setRowHeight(20);
        
        // Estructura para productos
        $productos_data = array(
            'Pantalón Buzo Alianza Francesa' => array(),
            'Polera Deporte M/C Alianza Francesa' => array(),
            'Polerón Buzo Alianza Francesa' => array()
        );
        
        // Procesar todas las reservas para el resumen de productos
        foreach ($reservas as $reserva) {
            $detalles = json_decode($reserva['productos'], true);
            
            if (is_array($detalles)) {
                foreach ($detalles as $detalle) {
                    if (!isset($detalle['producto'], $detalle['talla'], $detalle['cantidad'])) {
                        continue;
                    }
                    
                    $producto = $detalle['producto'];
                    $talla = $detalle['talla'];
                    $cantidad = intval($detalle['cantidad']);
                    $precio_unitario = isset($detalle['precio']) ? floatval($detalle['precio']) : 0;
                    $subtotal = isset($detalle['subtotal']) ? floatval($detalle['subtotal']) : ($precio_unitario * $cantidad);
                    
                    // Inicializar datos si no existen
                    if (!isset($productos_data[$producto][$talla])) {
                        $productos_data[$producto][$talla] = array(
                            'cantidad' => 0,
                            'total' => 0,
                        );
                    }
                    
                    // Actualizar datos
                    $productos_data[$producto][$talla]['cantidad'] += $cantidad;
                    $productos_data[$producto][$talla]['total'] += $subtotal;
                }
            }
        }
        
        // Tallas a mostrar en orden
        $tallas_orden = array('4', '6', '8', '10', '12', '14', 'XS', 'S', 'M', 'L', 'XL');
        
        // Cabeceras para la sección de productos
        $currentRow = 4;
        $sheet->setCellValue('A' . $currentRow, 'Producto');
        $sheet->setCellValue('B' . $currentRow, 'Talla');
        $sheet->setCellValue('C' . $currentRow, 'Cantidad');
        $sheet->setCellValue('D' . $currentRow, 'Precio Unitario');
        $sheet->setCellValue('E' . $currentRow, 'Monto Total');
        $sheet->getStyle('A' . $currentRow . ':E' . $currentRow)->applyFromArray($headerStyle);
        $sheet->getRowDimension($currentRow)->setRowHeight(22);
        $currentRow++;
        
        // Escribir los datos resumidos de productos
        $total_global_cantidad = 0;
        $total_global_monto = 0;
        $producto_idx = 0;
        
        foreach ($productos_data as $producto => $tallas) {
            $producto_total_cantidad = 0;
            $producto_total_monto = 0;
            $first_row = $currentRow;
            
            // Calcular totales del producto
            foreach ($tallas as $talla_data) {
                $producto_total_cantidad += $talla_data['cantidad'];
                $producto_total_monto += $talla_data['total'];
            }
            
            // Actualizar totales globales
            $total_global_cantidad += $producto_total_cantidad;
            $total_global_monto += $producto_total_monto;
            
            // Color para alternar productos
            $productoColor = ($producto_idx % 2 == 0) ? 'EDF2FF' : 'E5EDFF';
            
            // Detalle por tallas
            $rowIndex = 0;
            foreach ($tallas_orden as $talla) {
                if (isset($tallas[$talla]) && $tallas[$talla]['cantidad'] > 0) {
                    $precio_unitario_promedio = $tallas[$talla]['cantidad'] > 0 ? 
                        $tallas[$talla]['total'] / $tallas[$talla]['cantidad'] : 0;
                    
                    // Color de fondo para fila de producto
                    $filaStyle = [
                        'fill' => [
                            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'color' => ['rgb' => ($rowIndex % 2 == 0) ? 'FFFFFF' : 'F8F9FA'],
                        ],
                    ];
                    $sheet->getStyle('A' . $currentRow . ':E' . $currentRow)->applyFromArray($filaStyle);
                    
                    // Asegurar que el nombre del producto esté en UTF-8
                    // $producto_nombre = html_entity_decode($producto, ENT_QUOTES, 'UTF-8'); // Temporarily removed
                    
                    $sheet->setCellValue('A' . $currentRow, ($currentRow == $first_row) ? $producto : ''); // Use original $producto
                    $sheet->setCellValue('B' . $currentRow, $talla);
                    $sheet->setCellValue('C' . $currentRow, $tallas[$talla]['cantidad']);
                    $sheet->setCellValue('D' . $currentRow, $precio_unitario_promedio);
                    $sheet->setCellValue('E' . $currentRow, $tallas[$talla]['total']);
                    
                    // Formatos numéricos
                    $sheet->getStyle('C' . $currentRow)->getNumberFormat()->setFormatCode($quantityFormat);
                    $sheet->getStyle('D' . $currentRow)->getNumberFormat()->setFormatCode($currencyFormat);
                    $sheet->getStyle('E' . $currentRow)->getNumberFormat()->setFormatCode($currencyFormat);
                    
                    // Centramos algunas columnas
                    $sheet->getStyle('B' . $currentRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle('C' . $currentRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                    
                    $currentRow++;
                    $rowIndex++;
                }
            }
            
            // Fila total del producto
            $sheet->setCellValue('A' . $currentRow, 'TOTAL ' . $producto); // Use original $producto
            $sheet->mergeCells('A' . $currentRow . ':C' . $currentRow);
            $sheet->setCellValue('D' . $currentRow, $producto_total_cantidad > 0 ? $producto_total_monto / $producto_total_cantidad : 0);
            $sheet->setCellValue('E' . $currentRow, $producto_total_monto);
            
            // Aplicar estilo a la fila de total de producto
            $sheet->getStyle('A' . $currentRow . ':E' . $currentRow)->applyFromArray($totalRowStyle);
            $sheet->getStyle('D' . $currentRow)->getNumberFormat()->setFormatCode($currencyFormat);
            $sheet->getStyle('E' . $currentRow)->getNumberFormat()->setFormatCode($currencyFormat);
            
            $currentRow += 2; // Agregar espacio entre productos
            $producto_idx++;
        }
        
        // Total general - con estilo destacado
        $sheet->setCellValue('A' . $currentRow, 'TOTAL GENERAL');
        $sheet->mergeCells('A' . $currentRow . ':C' . $currentRow);
        $sheet->getStyle('A' . $currentRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->setCellValue('D' . $currentRow, $total_global_cantidad);
        $sheet->setCellValue('E' . $currentRow, $total_global_monto);
        
        // Estilo destacado para el total general
        $totalGeneralStyle = [
            'font' => [
                'bold' => true,
                'size' => 12,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'color' => ['rgb' => $colorSecundario],
            ],
            'borders' => [
                'outline' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM,
                    'color' => ['rgb' => $colorPrimario],
                ],
            ],
        ];
        
        $sheet->getStyle('A' . $currentRow . ':E' . $currentRow)->applyFromArray($totalGeneralStyle);
        $sheet->getStyle('D' . $currentRow)->getNumberFormat()->setFormatCode($quantityFormat);
        $sheet->getStyle('E' . $currentRow)->getNumberFormat()->setFormatCode($currencyFormat);
        $sheet->getRowDimension($currentRow)->setRowHeight(24);
        
        // Si es reporte detallado, agregar sección de clientes
        if ($export_type === 'detailed') {
            $currentRow += 3;
            
            // Título de la sección de clientes
            $sheet->setCellValue('A' . $currentRow, 'DETALLE DE CLIENTES Y RESERVAS');
            $sheet->mergeCells('A' . $currentRow . ':K' . $currentRow);
            $sheet->getStyle('A' . $currentRow . ':K' . $currentRow)->applyFromArray($titleStyle);
            $sheet->getRowDimension($currentRow)->setRowHeight(30);
            $currentRow += 2;
            
            // Cabeceras para clientes
            $sheet->setCellValue('A' . $currentRow, 'ID');
            $sheet->setCellValue('B' . $currentRow, 'Nombre');
            $sheet->setCellValue('C' . $currentRow, 'Email');
            $sheet->setCellValue('D' . $currentRow, 'Teléfono');
            $sheet->setCellValue('E' . $currentRow, 'Dirección');
            $sheet->setCellValue('F' . $currentRow, 'Comuna');
            $sheet->setCellValue('G' . $currentRow, 'Fecha Necesidad');
            $sheet->setCellValue('H' . $currentRow, 'Total');
            $sheet->setCellValue('I' . $currentRow, 'Fecha Registro');
            $sheet->setCellValue('J' . $currentRow, 'Productos');
            $sheet->setCellValue('K' . $currentRow, 'Observaciones');
            
            // Ajustar ancho de columnas para sección de clientes
            $sheet->getColumnDimension('A')->setWidth(8);
            $sheet->getColumnDimension('B')->setWidth(25);
            $sheet->getColumnDimension('C')->setWidth(25);
            $sheet->getColumnDimension('D')->setWidth(15);
            $sheet->getColumnDimension('E')->setWidth(30);
            $sheet->getColumnDimension('F')->setWidth(15);
            $sheet->getColumnDimension('G')->setWidth(15);
            $sheet->getColumnDimension('H')->setWidth(15);
            $sheet->getColumnDimension('I')->setWidth(15);
            $sheet->getColumnDimension('J')->setWidth(50);
            $sheet->getColumnDimension('K')->setWidth(30);
            
            $sheet->getStyle('A' . $currentRow . ':K' . $currentRow)->applyFromArray($headerStyle);
            $sheet->getRowDimension($currentRow)->setRowHeight(22);
            $currentRow++;
            
            // Datos de clientes
            $firstClientRow = $currentRow;
            $clientIdx = 0;
            
            foreach ($reservas as $reserva) {
                // Alternar colores de filas
                $rowStyle = ($clientIdx % 2 == 0) ? [] : $alternatingRowStyle;
                $sheet->getStyle('A' . $currentRow . ':K' . $currentRow)->applyFromArray($rowStyle);
                
                // Formatear productos para mejor legibilidad
                $productos_formateados = '';
                $detalles_productos = json_decode($reserva['productos'], true);
                
                if (is_array($detalles_productos)) {
                    foreach ($detalles_productos as $index => $detalle) {
                        // Convertir explícitamente a UTF-8
                        // $producto_nombre = html_entity_decode($detalle['producto'], ENT_QUOTES, 'UTF-8'); // Temporarily removed
                        // $talla_nombre = html_entity_decode($detalle['talla'], ENT_QUOTES, 'UTF-8'); // Temporarily removed
                        
                        if ($index > 0) {
                            $productos_formateados .= " | ";
                        }
                        $productos_formateados .= $detalle['producto'] . " - Talla: " . $detalle['talla'] . " - Cant: " . $detalle['cantidad']; // Use original
                    }
                } else {
                    $productos_formateados = $reserva['productos']; // Use original
                }
                
                $sheet->setCellValue('A' . $currentRow, $reserva['id']);
                $sheet->setCellValue('B' . $currentRow, $reserva['nombre']);
                $sheet->setCellValue('C' . $currentRow, $reserva['email']);
                $sheet->setCellValue('D' . $currentRow, $reserva['telefono']);
                $sheet->setCellValue('E' . $currentRow, isset($reserva['direccion']) ? $reserva['direccion'] : '');
                $sheet->setCellValue('F' . $currentRow, isset($reserva['comuna']) ? $reserva['comuna'] : '');
                $sheet->setCellValue('G' . $currentRow, export_format_fecha($reserva['fecha'], false));
                $sheet->setCellValue('H' . $currentRow, $reserva['total']);
                $sheet->setCellValue('I' . $currentRow, export_format_fecha($reserva['fecha_registro'], false));
                $sheet->setCellValue('J' . $currentRow, $productos_formateados);
                $sheet->setCellValue('K' . $currentRow, isset($reserva['observaciones']) ? $reserva['observaciones'] : '');
                
                // Alineaciones y formatos
                $sheet->getStyle('A' . $currentRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('D' . $currentRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('G' . $currentRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('I' . $currentRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                
                // Formato para moneda
                $sheet->getStyle('H' . $currentRow)->getNumberFormat()->setFormatCode($currencyFormat);
                
                $currentRow++;
                $clientIdx++;
            }
            
            // Aplicar estilos a la tabla de clientes
            $sheet->getStyle('A' . $firstClientRow . ':K' . ($currentRow - 1))->applyFromArray($dataStyle);
            
            // Ajustar altura de filas automáticamente
            for ($row = $firstClientRow; $row < $currentRow; $row++) {
                $sheet->getRowDimension($row)->setRowHeight(-1);
            }
            
            // Autofilter para la tabla de clientes
            $sheet->setAutoFilter('A' . ($firstClientRow - 1) . ':K' . ($currentRow - 1));
        }
        
        // Agregar pie de página con información
        $currentRow += 2;
        $sheet->setCellValue('A' . $currentRow, 'Informe generado el ' . date('d/m/Y') . ' a las ' . date('H:i:s'));
        $sheet->mergeCells('A' . $currentRow . ':K' . $currentRow);
        $sheet->getStyle('A' . $currentRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle('A' . $currentRow)->getFont()->setItalic(true);
        $sheet->getStyle('A' . $currentRow)->getFont()->setSize(8);
        $sheet->getStyle('A' . $currentRow)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_DARKBLUE));
        
        // Seguridad y propiedades del documento
        $spreadsheet->getProperties()
            ->setCreator('Plugin Reserva Form')
            ->setLastModifiedBy('Plugin Reserva Form')
            ->setTitle('Reporte de Reservas')
            ->setSubject('Informe de Reservas de Uniformes')
            ->setDescription('Generado automáticamente desde el plugin Reserva Form')
            ->setKeywords('reservas uniformes excel')
            ->setCategory('Reportes');
        
        // Configurar primera hoja
        $sheet->setTitle('Reporte de Reservas');
        
        // Proteger la hoja (solo lectura)
        $sheet->getProtection()->setSheet(true);
        
        // Crear el archivo Excel y configurar opciones para UTF-8
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->setOffice2003Compatibility(false); // Usar formato moderno
        $writer->setPreCalculateFormulas(true);
        
        // Configuraciones específicas para Excel y UTF-8
        if (method_exists($writer, 'setUseDiskCaching')) {
            $writer->setUseDiskCaching(true);
        }
        
        // Limpiar cualquier buffer de salida
        if (ob_get_length()) ob_end_clean();
        
        // Enviar al navegador
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet; charset=UTF-8');
        header('Content-Disposition: attachment; filename="reporte-reservas-' . date('Y-m-d') . '.xlsx"');
        header('Cache-Control: max-age=0');
        header('Cache-Control: max-age=1'); // IE 9
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Fecha en el pasado
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        header('Cache-Control: cache, must-revalidate');
        header('Pragma: public');
        
        $writer->save('php://output');
        exit;
        
    } catch (Exception $e) {
        // Si algo falla, revertir a CSV
        error_log('Error al generar Excel: ' . $e->getMessage());
        
        if ($export_type === 'summary') {
            export_product_summary_csv($reservas);
        } else {
            export_detailed_reservations_csv($reservas);
        }
        return;
    }
} 