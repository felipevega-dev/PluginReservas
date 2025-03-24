<?php
/**
 * Funciones auxiliares para el plugin Reserva
 *
 * @package ReservaPlugin
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Formatea la fecha en español abreviado
 *
 * @param string $fecha_db Fecha en formato de base de datos
 * @return string Fecha formateada
 */
function format_fecha($fecha_db) {
    if (empty($fecha_db)) return '-';
    
    $timestamp = strtotime($fecha_db);
    $meses = array('Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic');
    
    $dia = date('d', $timestamp);
    $mes = $meses[date('n', $timestamp) - 1];
    $año = date('Y', $timestamp);
    
    return '<span class="fecha-formateada">' . $dia . ' ' . $mes . ' ' . $año . '</span>';
}

/**
 * Formatea la fecha en español completo
 *
 * @param string $fecha_db Fecha en formato de base de datos
 * @return string Fecha formateada
 */
function format_fecha_completa($fecha_db) {
    if (empty($fecha_db)) return 'No especificada';
    
    $timestamp = strtotime($fecha_db);
    $meses_completos = array(
        'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
    );
    
    $dia = date('d', $timestamp);
    $mes = $meses_completos[date('n', $timestamp) - 1];
    $año = date('Y', $timestamp);
    
    return $dia . ' de ' . $mes . ' de ' . $año;
}

/**
 * Obtiene los productos formateados como string HTML
 *
 * @param string $productos_json
 * @param int $reserva_id
 * @param string $table_origin
 * @return string
 */
function get_formatted_productos($productos_json, $reserva_id = null, $table_origin = '') {
    $output = '';
    
    // Decodificar el JSON de productos
    $detalles = json_decode($productos_json, true);
    
    if(is_array($detalles) && !empty($detalles)) {
        $output = '<ul>';
        foreach($detalles as $detalle) {
            if (isset($detalle['producto']) && isset($detalle['talla']) && isset($detalle['cantidad'])) {
                $output .= sprintf(
                    '<li>%s - %s (Cant: %s)</li>',
                    esc_html($detalle['producto']),
                    esc_html($detalle['talla']),
                    esc_html($detalle['cantidad'])
                );
            }
        }
        $output .= '</ul>';
        return $output;
    }
    
    // Si no hay datos de productos
    return '<span class="no-disponible">Información no disponible</span>';
} 