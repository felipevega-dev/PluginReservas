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
    
    // Verificar si se requiere formato Excel avanzado
    if ($format === 'excel') {
        if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            // Revertir a formato CSV si la librería no está disponible
            if ($export_type === 'summary') {
                export_product_summary_csv($reservas);
            } else {
                export_detailed_reservations_csv($reservas);
            }
        } else {
            enhanced_excel_export($reservas, $export_type);
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
    
    // Preparar el archivo CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // BOM para UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Separador para Excel
    fputcsv($output, array("sep=,"));
    
    // Cabeceras del resumen
    fputcsv($output, array(
        'Producto',
        'Talla',
        'Cantidad Total',
        'Precio Unitario Promedio',
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
    
    fputcsv($output, array(''));
    fputcsv($output, array('RESUMEN GENERAL', '', '', ''));
    fputcsv($output, array('Total Unidades Vendidas', $total_global_cantidad, '', ''));
    fputcsv($output, array('Monto Total Recaudado', '$' . number_format($total_global_monto, 0, ',', '.'), '', ''));
    
    fclose($output);
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
    
    // Preparar el archivo CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // BOM para UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Separador para Excel
    fputcsv($output, array("sep=,"));
    
    // Primera sección: Resumen por productos y tallas
    // Encabezado para la sección de productos
    fputcsv($output, array("RESUMEN DE PRODUCTOS POR TALLA"));
    fputcsv($output, array(""));
    
    // Cabeceras del resumen de productos
    fputcsv($output, array(
        'Producto',
        'Talla',
        'Cantidad Total',
        'Precio Unitario Promedio',
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
                if ($index > 0) {
                    $productos_formateados .= " | ";
                }
                $productos_formateados .= $detalle['producto'] . " - Talla: " . $detalle['talla'] . " - Cant: " . $detalle['cantidad'];
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
            formato_fecha($reserva['fecha']),
            '$' . number_format($reserva['total'], 0, ',', '.'),
            formato_fecha($reserva['fecha_registro']),
            $productos_formateados,
            isset($reserva['observaciones']) ? $reserva['observaciones'] : ''
        ));
    }
    
    fclose($output);
}

/**
 * Formatea fecha para CSV
 */
function formato_fecha($fecha_str) {
    if (empty($fecha_str)) return '';
    
    $fecha = strtotime($fecha_str);
    return date('d/m/Y', $fecha);
}

/**
 * Crea un reporte Excel mejorado con formateo visual
 */
function enhanced_excel_export($reservas, $export_type) {
    // Asegurarse de que no haya salida antes de enviar los encabezados
    if (ob_get_level()) {
        ob_end_clean();
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
        $sheet = $spreadsheet->getActiveSheet();
        
        // Estilos comunes
        $titleStyle = [
            'font' => [
                'bold' => true,
                'size' => 16,
                'color' => ['rgb' => '4b6cb7'],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'color' => ['rgb' => 'e8f0ff'],
            ],
        ];
        
        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'color' => ['rgb' => '4b6cb7'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                ],
            ],
        ];
        
        $totalRowStyle = [
            'font' => [
                'bold' => true,
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'color' => ['rgb' => 'e8f0ff'],
            ],
        ];
        
        $dataStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                ],
            ],
        ];
        
        $currencyFormat = '_($* #,##0.00_);_($* (#,##0.00);_($* "-"??_);_(@_)';
        
        // Configurar columnas
        $sheet->getColumnDimension('A')->setWidth(30);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(20);
        $sheet->getColumnDimension('E')->setWidth(20);
        
        // Título del informe
        $sheet->setCellValue('A1', ($export_type === 'summary' ? 'RESUMEN DE PRODUCTOS POR TALLA' : 'REPORTE DETALLADO DE RESERVAS'));
        $sheet->mergeCells('A1:E1');
        $sheet->getStyle('A1:E1')->applyFromArray($titleStyle);
        
        // Fecha del informe
        $sheet->setCellValue('A2', 'Fecha del informe: ' . date('d/m/Y'));
        $sheet->mergeCells('A2:E2');
        
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
        $sheet->setCellValue('D' . $currentRow, 'Precio Promedio');
        $sheet->setCellValue('E' . $currentRow, 'Monto Total');
        $sheet->getStyle('A' . $currentRow . ':E' . $currentRow)->applyFromArray($headerStyle);
        $currentRow++;
        
        // Escribir los datos resumidos de productos
        $total_global_cantidad = 0;
        $total_global_monto = 0;
        
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
            
            // Detalle por tallas
            foreach ($tallas_orden as $talla) {
                if (isset($tallas[$talla]) && $tallas[$talla]['cantidad'] > 0) {
                    $precio_unitario_promedio = $tallas[$talla]['cantidad'] > 0 ? 
                        $tallas[$talla]['total'] / $tallas[$talla]['cantidad'] : 0;
                    
                    $sheet->setCellValue('A' . $currentRow, ($currentRow == $first_row) ? $producto : '');
                    $sheet->setCellValue('B' . $currentRow, $talla);
                    $sheet->setCellValue('C' . $currentRow, $tallas[$talla]['cantidad']);
                    $sheet->setCellValue('D' . $currentRow, $precio_unitario_promedio);
                    $sheet->setCellValue('E' . $currentRow, $tallas[$talla]['total']);
                    
                    // Formato para moneda
                    $sheet->getStyle('D' . $currentRow)->getNumberFormat()->setFormatCode($currencyFormat);
                    $sheet->getStyle('E' . $currentRow)->getNumberFormat()->setFormatCode($currencyFormat);
                    
                    $currentRow++;
                }
            }
            
            // Fila total del producto
            $sheet->setCellValue('A' . $currentRow, $producto . ' - TOTAL');
            $sheet->setCellValue('B' . $currentRow, '');
            $sheet->setCellValue('C' . $currentRow, $producto_total_cantidad);
            $sheet->setCellValue('D' . $currentRow, $producto_total_cantidad > 0 ? $producto_total_monto / $producto_total_cantidad : 0);
            $sheet->setCellValue('E' . $currentRow, $producto_total_monto);
            
            // Aplicar estilo a la fila de total de producto
            $sheet->getStyle('A' . $currentRow . ':E' . $currentRow)->applyFromArray($totalRowStyle);
            $sheet->getStyle('D' . $currentRow)->getNumberFormat()->setFormatCode($currencyFormat);
            $sheet->getStyle('E' . $currentRow)->getNumberFormat()->setFormatCode($currencyFormat);
            
            $currentRow += 2; // Agregar espacio entre productos
        }
        
        // Total general
        $sheet->setCellValue('A' . $currentRow, 'TOTAL GENERAL');
        $sheet->setCellValue('B' . $currentRow, '');
        $sheet->setCellValue('C' . $currentRow, $total_global_cantidad);
        $sheet->setCellValue('D' . $currentRow, '');
        $sheet->setCellValue('E' . $currentRow, $total_global_monto);
        
        // Aplicar estilo al total general
        $sheet->getStyle('A' . $currentRow . ':E' . $currentRow)->applyFromArray($totalRowStyle);
        $sheet->getStyle('A' . $currentRow . ':E' . $currentRow)->getFont()->setSize(14); // Texto más grande
        $sheet->getStyle('E' . $currentRow)->getNumberFormat()->setFormatCode($currencyFormat);
        
        // Si es reporte detallado, agregar sección de clientes
        if ($export_type === 'detailed') {
            $currentRow += 3;
            
            // Título de la sección de clientes
            $sheet->setCellValue('A' . $currentRow, 'DETALLE DE CLIENTES Y RESERVAS');
            $sheet->mergeCells('A' . $currentRow . ':K' . $currentRow);
            $sheet->getStyle('A' . $currentRow . ':K' . $currentRow)->applyFromArray($titleStyle);
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
            
            // Ajustar ancho de columnas
            $sheet->getColumnDimension('A')->setWidth(8);
            $sheet->getColumnDimension('B')->setWidth(25);
            $sheet->getColumnDimension('C')->setWidth(25);
            $sheet->getColumnDimension('D')->setWidth(15);
            $sheet->getColumnDimension('E')->setWidth(25);
            $sheet->getColumnDimension('F')->setWidth(15);
            $sheet->getColumnDimension('G')->setWidth(15);
            $sheet->getColumnDimension('H')->setWidth(15);
            $sheet->getColumnDimension('I')->setWidth(15);
            $sheet->getColumnDimension('J')->setWidth(40);
            $sheet->getColumnDimension('K')->setWidth(30);
            
            $sheet->getStyle('A' . $currentRow . ':K' . $currentRow)->applyFromArray($headerStyle);
            $currentRow++;
            
            // Datos de clientes
            $firstClientRow = $currentRow;
            foreach ($reservas as $reserva) {
                // Formatear productos para mejor legibilidad
                $productos_formateados = '';
                $detalles_productos = json_decode($reserva['productos'], true);
                
                if (is_array($detalles_productos)) {
                    foreach ($detalles_productos as $index => $detalle) {
                        if ($index > 0) {
                            $productos_formateados .= " | ";
                        }
                        $productos_formateados .= $detalle['producto'] . " - Talla: " . $detalle['talla'] . " - Cant: " . $detalle['cantidad'];
                    }
                } else {
                    $productos_formateados = $reserva['productos'];
                }
                
                $sheet->setCellValue('A' . $currentRow, $reserva['id']);
                $sheet->setCellValue('B' . $currentRow, $reserva['nombre']);
                $sheet->setCellValue('C' . $currentRow, $reserva['email']);
                $sheet->setCellValue('D' . $currentRow, $reserva['telefono']);
                $sheet->setCellValue('E' . $currentRow, isset($reserva['direccion']) ? $reserva['direccion'] : '');
                $sheet->setCellValue('F' . $currentRow, isset($reserva['comuna']) ? $reserva['comuna'] : '');
                $sheet->setCellValue('G' . $currentRow, formato_fecha($reserva['fecha']));
                $sheet->setCellValue('H' . $currentRow, $reserva['total']);
                $sheet->setCellValue('I' . $currentRow, isset($reserva['fecha_registro']) ? formato_fecha($reserva['fecha_registro']) : '');
                $sheet->setCellValue('J' . $currentRow, $productos_formateados);
                $sheet->setCellValue('K' . $currentRow, isset($reserva['observaciones']) ? $reserva['observaciones'] : '');
                
                // Formato para moneda
                $sheet->getStyle('H' . $currentRow)->getNumberFormat()->setFormatCode($currencyFormat);
                
                $currentRow++;
            }
            
            // Aplicar estilos a la tabla de clientes
            $sheet->getStyle('A' . $firstClientRow . ':K' . ($currentRow - 1))->applyFromArray($dataStyle);
            
            // Auto ajustar filas
            $sheet->getRowDimension('J')->setRowHeight(-1);
        }
        
        // Configurar primera hoja
        $sheet->setTitle('Reporte de Reservas');
        
        // Crear el archivo Excel
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        
        // Limpiar cualquier buffer de salida
        if (ob_get_length()) ob_end_clean();
        
        // Enviar al navegador - Usando buffers para evitar problemas
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="reporte-reservas-' . date('Y-m-d') . '.xlsx"');
        header('Cache-Control: max-age=0');
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