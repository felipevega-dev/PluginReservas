<?php
/**
 * Clase para manejar el detalle de una reserva
 *
 * @package ReservaPlugin
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase Reserva_Detail
 */
class Reserva_Detail {
    /**
     * ID de la reserva
     *
     * @var int
     */
    private $id;
    
    /**
     * Datos de la reserva
     *
     * @var object
     */
    private $reserva;
    
    /**
     * Constructor
     *
     * @param int $id ID de la reserva
     */
    public function __construct($id) {
        $this->id = intval($id);
        $this->load_reserva();
    }
    
    /**
     * Carga los datos de la reserva
     */
    private function load_reserva() {
        global $wpdb;
        
        // Buscar en la tabla de reservas
        $table = $wpdb->prefix . 'reservas';
        $this->reserva = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $this->id));
        
        // Registrar resultado en log para depuración
        if ($this->reserva !== null) {
            error_log('Reserva #' . $this->id . ' encontrada en la tabla ' . $table);
        } else {
            error_log('No se encontró la reserva #' . $this->id . ' en la tabla ' . $table);
        }
    }
    
    /**
     * Verifica si la reserva existe
     *
     * @return bool
     */
    public function exists() {
        return $this->reserva !== null;
    }
    
    /**
     * Renderiza la vista de detalle
     */
    public function render() {
        if (!$this->exists()) {
            echo '<div class="reserva-notice reserva-notice-error"><p><span class="dashicons dashicons-no"></span> No se encontró la reserva solicitada.</p></div>';
            echo '<a href="' . esc_url(admin_url('admin.php?page=reserva-lista')) . '" class="button button-primary"><span class="dashicons dashicons-arrow-left-alt"></span> Volver a la lista</a>';
            return;
        }
        
        include plugin_dir_path(dirname(__FILE__)) . 'views/detail.php';
    }
    
    /**
     * Obtiene los datos de la reserva
     *
     * @return object
     */
    public function get_reserva() {
        return $this->reserva;
    }
    
    /**
     * Obtiene los productos formateados
     *
     * @return array
     */
    public function get_productos() {
        $productos = array();
        
        // Mapa de imágenes de productos
        $mapa_productos = array(
            'Pantalón Buzo Alianza Francesa' => plugins_url('assets/images/poleronypantalon.jpg', dirname(dirname(__FILE__))),
            'Polera Deporte M/C Alianza Francesa' => plugins_url('assets/images/polera.jpg', dirname(dirname(__FILE__))),
            'Polerón Buzo Alianza Francesa' => plugins_url('assets/images/poleronypantalon.jpg', dirname(dirname(__FILE__))),
        );
        
        // Placeholder para imagen por defecto
        $placeholder_img = plugins_url('assets/images/product-placeholder.jpg', dirname(dirname(__FILE__)));
        
        // Obtener los productos del campo JSON
        $detalles = json_decode($this->reserva->productos ?? '', true);
        
        if(is_array($detalles) && !empty($detalles)) {
            foreach($detalles as $detalle) {
                $img_url = isset($detalle['img']) ? $detalle['img'] : 
                           ($mapa_productos[$detalle['producto']] ?? $placeholder_img);
                
                $precio_unitario = isset($detalle['precio']) ? intval($detalle['precio']) : 0;
                $cantidad = intval($detalle['cantidad']);
                $subtotal = isset($detalle['subtotal']) ? intval($detalle['subtotal']) : ($precio_unitario * $cantidad);
                
                $productos[] = array(
                    'nombre' => $detalle['producto'],
                    'talla' => $detalle['talla'],
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precio_unitario,
                    'subtotal' => $subtotal,
                    'img_url' => $img_url
                );
            }
        }
        
        return $productos;
    }
    
    /**
     * Obtiene el total calculado
     *
     * @return int
     */
    public function get_total() {
        // Si tenemos un total almacenado, lo usamos
        $total_almacenado = isset($this->reserva->total) ? intval($this->reserva->total) : 0;
        
        // Calcular total desde productos
        $total_calculado = 0;
        $productos = $this->get_productos();
        foreach($productos as $producto) {
            $total_calculado += $producto['subtotal'];
        }
        
        // Descuento
        $descuento = !empty($this->reserva->descuento) ? intval($this->reserva->descuento) : 0;
        $total_calculado -= $descuento;
        
        // Usar el total almacenado si está disponible y es válido, sino el calculado
        return ($total_almacenado > 0) ? $total_almacenado : $total_calculado;
    }
    
    /**
     * Obtiene el descuento
     *
     * @return int
     */
    public function get_descuento() {
        return !empty($this->reserva->descuento) ? intval($this->reserva->descuento) : 0;
    }
    
    /**
     * Obtiene el subtotal (sin descuento)
     *
     * @return int
     */
    public function get_subtotal() {
        $total_calculado = 0;
        $productos = $this->get_productos();
        foreach($productos as $producto) {
            $total_calculado += $producto['subtotal'];
        }
        return $total_calculado;
    }
} 