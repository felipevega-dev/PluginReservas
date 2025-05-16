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
 * @param bool $html_format Si es verdadero, retorna la fecha con formato HTML
 * @return string Fecha formateada
 */
function format_fecha($fecha_db, $html_format = true) {
    if (empty($fecha_db)) return $html_format ? '-' : '';
    
    $timestamp = strtotime($fecha_db);
    $meses = array('Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic');
    
    $dia = date('d', $timestamp);
    $mes = $meses[date('n', $timestamp) - 1];
    $año = date('Y', $timestamp);
    
    if ($html_format) {
        return '<span class="fecha-formateada">' . $dia . ' ' . $mes . ' ' . $año . '</span>';
    } else {
        return $dia . ' ' . $mes . ' ' . $año;
    }
}

/**
 * Formatea la fecha en español completo
 *
 * @param string $fecha_db Fecha en formato de base de datos
 * @param bool $html_format Si es verdadero, aplica formato HTML
 * @return string Fecha formateada
 */
function format_fecha_completa($fecha_db, $html_format = true) {
    if (empty($fecha_db)) return $html_format ? 'No especificada' : '';
    
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

/**
 * Verifica si PhpSpreadsheet está disponible
 *
 * @param bool $check_dir También verifica si el directorio de libs existe
 * @return array|bool True si está disponible, o array con información si $check_dir es true
 */
function reserva_check_phpspreadsheet($check_dir = false) {
    $available = class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet');
    
    if (!$available) {
        // Intentar cargar la librería automáticamente si no está disponible
        $plugin_dir = plugin_dir_path(dirname(__FILE__));
        $posibles_rutas = array(
            $plugin_dir . 'includes/libs/autoload.php',
            $plugin_dir . 'includes/libs/vendor/autoload.php',
            $plugin_dir . 'includes/libs/PhpSpreadsheet/bootstrap.php',
        );
        
        foreach ($posibles_rutas as $ruta) {
            if (file_exists($ruta)) {
                try {
                    require_once $ruta;
                    $available = class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet');
                    if ($available) {
                        break;
                    }
                } catch (Exception $e) {
                    continue;
                }
            }
        }
    }
    
    if (!$check_dir) {
        return $available;
    }
    
    // Comprobar si el directorio de libs existe
    $plugin_dir = plugin_dir_path(dirname(__FILE__));
    $libs_dir = $plugin_dir . 'includes/libs';
    $vendor_dir_exists = is_dir($libs_dir);
    $composer_json_exists = file_exists($plugin_dir . 'composer.json');
    
    return array(
        'available' => $available,
        'vendor_dir_exists' => $vendor_dir_exists,
        'composer_json_exists' => $composer_json_exists,
        'libs_dir' => $libs_dir,
        'plugin_dir' => $plugin_dir
    );
} 