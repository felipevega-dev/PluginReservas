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
 * Exporta las reservas a un archivo CSV
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
    
    // Definir el nombre del archivo
    $filename = 'reservas-' . date('Y-m-d') . '.csv';
    
    // Asegurarse de que no hay nada en el búffer de salida
    if (ob_get_length()) {
        ob_clean();
    }
    
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
    
    // Definir correctamente todas las columnas en el orden correcto
    fputcsv($output, array(
        'ID', 
        'Productos', 
        'Nombre', 
        'Dirección', 
        'Email', 
        'Teléfono',
        'Comuna', 
        'Fecha Necesidad', 
        'Total', 
        'Fecha Registro',
        'Observaciones'
    ));
    
    foreach ($reservas as $reserva) {
        fputcsv($output, array(
            $reserva['id'],
            $reserva['productos'],
            $reserva['nombre'],
            $reserva['direccion'],
            $reserva['email'],
            $reserva['telefono'],
            $reserva['comuna'],
            $reserva['fecha'],
            $reserva['total'],
            $reserva['fecha_registro'],
            isset($reserva['observaciones']) ? $reserva['observaciones'] : ''
        ));
    }
    
    fclose($output);
    exit; // Asegurarse de terminar la ejecución
} 