<?php
/**
 * Clase para manejar la lista de reservas
 *
 * @package ReservaPlugin
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase Reserva_List
 */
class Reserva_List {
    /**
     * Tabla de WordPress para las reservas
     *
     * @var string
     */
    private $table_name;
    
    /**
     * Parámetros de búsqueda y filtrado
     *
     * @var array
     */
    private $params;
    
    /**
     * Reservas recuperadas de la base de datos
     *
     * @var array
     */
    private $reservas;
    
    /**
     * Total de reservas que coinciden con los criterios
     *
     * @var int
     */
    private $total_items;
    
    /**
     * Total de páginas para la paginación
     *
     * @var int
     */
    private $total_pages;
    
    /**
     * Página actual
     *
     * @var int
     */
    private $current_page;
    
    /**
     * Mensajes de notificación
     *
     * @var array
     */
    private $notices = array();

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'reservas';
        
        // Inicializar parámetros
        $this->params = array(
            'search_query' => isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '',
            'fecha_desde' => isset($_GET['fecha_desde']) ? sanitize_text_field($_GET['fecha_desde']) : '',
            'fecha_hasta' => isset($_GET['fecha_hasta']) ? sanitize_text_field($_GET['fecha_hasta']) : '',
            'comuna' => isset($_GET['comuna']) ? sanitize_text_field($_GET['comuna']) : '',
            'precio_min' => isset($_GET['precio_min']) ? intval($_GET['precio_min']) : '',
            'precio_max' => isset($_GET['precio_max']) ? intval($_GET['precio_max']) : '',
        );
        
        $this->current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        
        // Cargar los datos
        $this->load_data();
    }
    
    /**
     * Carga las reservas según los filtros
     */
    private function load_data() {
        global $wpdb;
        $items_per_page = 20;
        $offset = ($this->current_page - 1) * $items_per_page;
        
        // Verifica si la tabla existe
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(1) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
            DB_NAME,
            $this->table_name
        ));
        
        // Si no existe la tabla, no hay reservas
        if (!$table_exists) {
            $this->reservas = array();
            $this->total_items = 0;
            $this->total_pages = 0;
            return;
        }
        
        // Construir la consulta con los filtros para la tabla
        $where_conditions = array();
        $where_values = array();
        
        if (!empty($this->params['search_query'])) {
            $like = '%' . $wpdb->esc_like($this->params['search_query']) . '%';
            $where_conditions[] = '(nombre LIKE %s OR email LIKE %s OR productos LIKE %s)';
            $where_values[] = $like;
            $where_values[] = $like;
            $where_values[] = $like;
        }

        if (!empty($this->params['fecha_desde'])) {
            $where_conditions[] = 'fecha >= %s';
            $where_values[] = $this->params['fecha_desde'];
        }

        if (!empty($this->params['fecha_hasta'])) {
            $where_conditions[] = 'fecha <= %s';
            $where_values[] = $this->params['fecha_hasta'];
        }

        if (!empty($this->params['comuna'])) {
            $where_conditions[] = 'comuna = %s';
            $where_values[] = $this->params['comuna'];
        }

        if (!empty($this->params['precio_min'])) {
            $where_conditions[] = 'total >= %d';
            $where_values[] = $this->params['precio_min'];
        }

        if (!empty($this->params['precio_max'])) {
            $where_conditions[] = 'total <= %d';
            $where_values[] = $this->params['precio_max'];
        }
        
        // Ejecutar consulta para contar el total de items
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
            $count_values = $where_values;
            array_unshift($count_values, "SELECT COUNT(*) FROM $this->table_name $where_clause");
            $this->total_items = $wpdb->get_var($wpdb->prepare(...$count_values));
        } else {
            $this->total_items = $wpdb->get_var("SELECT COUNT(*) FROM $this->table_name");
        }
        
        // Consulta para obtener las reservas con filtros
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
            $query = "SELECT * FROM $this->table_name $where_clause ORDER BY fecha_registro DESC LIMIT %d OFFSET %d";
            $query_values = $where_values;
            $query_values[] = $items_per_page;
            $query_values[] = $offset;
            array_unshift($query_values, $query);
            $this->reservas = $wpdb->get_results($wpdb->prepare(...$query_values));
        } else {
            $this->reservas = $wpdb->get_results(
                $wpdb->prepare("SELECT * FROM $this->table_name ORDER BY fecha_registro DESC LIMIT %d OFFSET %d", 
                    $items_per_page, $offset)
            );
        }
        
        $this->total_pages = ceil($this->total_items / $items_per_page);
    }
    
    /**
     * Procesa acciones (eliminar)
     */
    public function process_actions() {
        global $wpdb;
        
        if (isset($_GET['delete_reserva'])) {
            $reserva_id = intval($_GET['delete_reserva']);
            $nonce = isset($_GET['_wpnonce']) ? $_GET['_wpnonce'] : '';
            
            if (wp_verify_nonce($nonce, 'delete_reserva_' . $reserva_id)) {
                $deleted = $wpdb->delete( 
                    $this->table_name, 
                    array('id' => $reserva_id), 
                    array('%d') 
                );
                
                if ($deleted) {
                    // Crear la URL de redirección para actualizar la lista después de eliminar
                    $redirect_url = add_query_arg(
                        array(
                            'page' => 'reserva-lista',
                            'deleted' => 'true',
                            'id' => $reserva_id
                        ),
                        admin_url('admin.php')
                    );
                    
                    // Mantener los parámetros de paginación y filtros
                    if (isset($_GET['paged'])) {
                        $redirect_url = add_query_arg('paged', intval($_GET['paged']), $redirect_url);
                    }
                    if (isset($_GET['s'])) {
                        $redirect_url = add_query_arg('s', sanitize_text_field($_GET['s']), $redirect_url);
                    }
                    
                    // Redirigir a la misma página para actualizar la vista
                    wp_redirect($redirect_url);
                    exit;
                } else {
                    $this->add_notice('error', 'Error al eliminar la reserva #' . $reserva_id . '. Por favor, intenta nuevamente.');
                }
            } else {
                $this->add_notice('error', 'Error de seguridad. No se pudo verificar la solicitud.');
            }
        }
        
        // Mostrar mensaje después de eliminar con éxito
        if (isset($_GET['deleted']) && $_GET['deleted'] === 'true' && !empty($_GET['id'])) {
            $this->add_notice('success', 'Reserva #' . intval($_GET['id']) . ' eliminada correctamente.');
        }
    }
    
    /**
     * Renderiza la vista de lista
     */
    public function render() {
        include plugin_dir_path(dirname(__FILE__)) . 'views/list.php';
    }
    
    /**
     * Obtiene estadísticas para mostrar en el header
     *
     * @return array
     */
    public function get_stats() {
        global $wpdb;
        $stats = array(
            'total_reservas' => $this->total_items,
            'total_ingresos' => 0,
            'total_productos' => 0,
        );
        
        // Verificar si la tabla existe
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(1) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
            DB_NAME,
            $this->table_name
        ));
        
        if (!$table_exists) {
            return $stats;
        }
        
        // Calcular ingresos totales
        $stats['total_ingresos'] = $wpdb->get_var("SELECT COALESCE(SUM(total), 0) FROM {$this->table_name}");
        
        // Calcular total productos usando el campo JSON
        $productos_json = $wpdb->get_results("SELECT productos FROM {$this->table_name} WHERE productos IS NOT NULL");
        $total_productos = 0;
        
        foreach ($productos_json as $prod) {
            $detalles = json_decode($prod->productos, true);
            if (is_array($detalles)) {
                foreach ($detalles as $detalle) {
                    $total_productos += intval($detalle['cantidad']);
                }
            }
        }
        
        $stats['total_productos'] = $total_productos;
        
        return $stats;
    }
    
    /**
     * Agrega un mensaje de notificación
     *
     * @param string $type Tipo de notificación ('success', 'error', 'warning')
     * @param string $message Mensaje a mostrar
     */
    private function add_notice($type, $message) {
        $this->notices[] = array(
            'type' => $type,
            'message' => $message
        );
    }
    
    /**
     * Obtiene las notificaciones para mostrar
     *
     * @return array
     */
    public function get_notices() {
        return $this->notices;
    }
    
    /**
     * Obtiene las comunas para el filtro
     *
     * @return array
     */
    public function get_comunas() {
        global $wpdb;
        return $wpdb->get_col("SELECT DISTINCT comuna FROM {$this->table_name} WHERE comuna != '' ORDER BY comuna");
    }
    
    /**
     * Obtiene las reservas cargadas
     *
     * @return array
     */
    public function get_reservas() {
        return $this->reservas;
    }
    
    /**
     * Devuelve la información de paginación
     *
     * @return array
     */
    public function get_pagination_info() {
        return array(
            'current_page' => $this->current_page,
            'total_pages' => $this->total_pages,
            'total_items' => $this->total_items
        );
    }
    
    /**
     * Devuelve la información de filtros
     *
     * @return array
     */
    public function get_filters() {
        return array(
            'search_query' => $this->params['search_query'],
            'filters' => $this->params
        );
    }
} 