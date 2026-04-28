<?php
require_once 'config.php';

class CustomerProfiler {
    private $empresa_id;
    
    public function __construct($empresa_id) {
        $this->empresa_id = $empresa_id;
    }
    
    /**
     * Analizar compras frecuentes de un cliente
     */
    public function analizarComprasFrecuentes($cliente_id) {
        $analisis = [];
        
        // Productos más comprados
        $productos = fetchAll("
            SELECT p.nombre, COUNT(*) as frecuencia, SUM(vi.cantidad) as total_cantidad,
                   AVG(vi.precio_unitario) as precio_promedio, MAX(v.fecha) as ultima_compra
            FROM venta_items vi
            JOIN ventas v ON vi.venta_id = v.id
            JOIN productos p ON vi.producto_id = p.id
            WHERE v.cliente_id = ? AND v.empresa_id = ?
            GROUP BY vi.producto_id, p.nombre
            ORDER BY frecuencia DESC, total_cantidad DESC
            LIMIT 10
        ", [$cliente_id, $this->empresa_id]);
        
        $analisis['productos_frecuentes'] = $productos;
        
        // Patrones temporales
        $patrones = fetchAll("
            SELECT DAYOFWEEK(v.fecha) as dia_semana, 
                   HOUR(v.fecha) as hora,
                   COUNT(*) as frecuencia
            FROM ventas v
            WHERE v.cliente_id = ? AND v.empresa_id = ?
            GROUP BY DAYOFWEEK(v.fecha), HOUR(v.fecha)
            HAVING frecuencia > 1
            ORDER BY frecuencia DESC
            LIMIT 5
        ", [$cliente_id, $this->empresa_id]);
        
        $analisis['patrones_temporales'] = $patrones;
        
        // Ticket promedio
        $ticket_promedio = fetch("
            SELECT AVG(v.total) as promedio, 
                   MIN(v.total) as minimo, 
                   MAX(v.total) as maximo,
                   COUNT(*) as total_ventas
            FROM ventas v
            WHERE v.cliente_id = ? AND v.empresa_id = ?
        ", [$cliente_id, $this->empresa_id]);
        
        $analisis['ticket_promedio'] = $ticket_promedio;
        
        // Últimas compras
        $ultimas_compras = fetchAll("
            SELECT v.fecha, v.total, COUNT(vi.id) as cantidad_items,
                   GROUP_CONCAT(p.nombre SEPARATOR ', ') as productos
            FROM ventas v
            LEFT JOIN venta_items vi ON v.id = vi.venta_id
            LEFT JOIN productos p ON vi.producto_id = p.id
            WHERE v.cliente_id = ? AND v.empresa_id = ?
            GROUP BY v.id, v.fecha, v.total
            ORDER BY v.fecha DESC
            LIMIT 5
        ", [$cliente_id, $this->empresa_id]);
        
        $analisis['ultimas_compras'] = $ultimas_compras;
        
        // Categorías preferidas
        $categorias = fetchAll("
            SELECT p.categoria, COUNT(*) as frecuencia, SUM(vi.cantidad) as total_unidades
            FROM venta_items vi
            JOIN ventas v ON vi.venta_id = v.id
            JOIN productos p ON vi.producto_id = p.id
            WHERE v.cliente_id = ? AND v.empresa_id = ? AND p.categoria IS NOT NULL
            GROUP BY p.categoria
            ORDER BY frecuencia DESC
            LIMIT 5
        ", [$cliente_id, $this->empresa_id]);
        
        $analisis['categorias_preferidas'] = $categorias;
        
        return $analisis;
    }
    
    /**
     * Generar recomendaciones personalizadas
     */
    public function generarRecomendaciones($cliente_id) {
        $analisis = $this->analizarComprasFrecuentes($cliente_id);
        $recomendaciones = [];
        
        // Basado en productos frecuentes
        if (!empty($analisis['productos_frecuentes'])) {
            $producto_top = $analisis['productos_frecuentes'][0];
            $recomendaciones['producto_sugerido'] = [
                'nombre' => $producto_top['nombre'],
                'motivo' => 'Lo compraste ' . $producto_top['frecuencia'] . ' veces',
                'frecuencia' => $producto_top['frecuencia']
            ];
            
            // Buscar productos similares
            $productos_similares = fetchAll("
                SELECT p.nombre, p.descripcion, p.precio
                FROM productos p
                WHERE p.categoria = (
                    SELECT categoria FROM productos WHERE nombre = ?
                ) AND p.nombre != ? AND p.activo = 1 AND p.empresa_id = ?
                ORDER BY RAND()
                LIMIT 3
            ", [$producto_top['nombre'], $producto_top['nombre'], $this->empresa_id]);
            
            $recomendaciones['productos_similares'] = $productos_similares;
        }
        
        // Basado en patrones temporales
        if (!empty($analisis['patrones_temporales'])) {
            $patron = $analisis['patrones_temporales'][0];
            $dias_semana = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
            $dia_nombre = $dias_semana[$patron['dia_semana'] - 1] ?? 'Día ' . $patron['dia_semana'];
            
            $recomendaciones['patron_temporal'] = [
                'mensaje' => 'Sueles comprar los ' . $dia_nombre . 's sobre las ' . $patron['hora'] . ':00',
                'frecuencia' => $patron['frecuencia']
            ];
        }
        
        // Basado en ticket promedio
        if ($analisis['ticket_promedio']['total_ventas'] > 0) {
            $promedio = $analisis['ticket_promedio']['promedio'];
            $recomendaciones['rango_gasto'] = [
                'promedio' => $promedio,
                'mensaje' => 'Tu gasto promedio es $' . number_format($promedio, 2)
            ];
        }
        
        // Frases de empatía personalizadas
        $recomendaciones['frases_empatia'] = $this->generarFrasesEmpatia($analisis);
        
        return $recomendaciones;
    }
    
    /**
     * Generar frases de empatía personalizadas
     */
    private function generarFrasesEmpatia($analisis) {
        $frases = [];
        
        // Basado en producto más frecuente
        if (!empty($analisis['productos_frecuentes'])) {
            $producto = $analisis['productos_frecuentes'][0];
            $frases[] = "¿" . htmlspecialchars($producto['nombre']) . " de siempre hoy?";
            
            if ($producto['frecuencia'] >= 5) {
                $frases[] = "¡Tu " . htmlspecialchars($producto['nombre']) . " favorito te espera!";
            }
        }
        
        // Basado en categoría preferida
        if (!empty($analisis['categorias_preferidas'])) {
            $categoria = $analisis['categorias_preferidas'][0]['categoria'];
            $frases[] = "¿Algo de la sección de " . htmlspecialchars($categoria) . " hoy?";
        }
        
        // Basado en horario
        if (!empty($analisis['patrones_temporales'])) {
            $frases[] = "¡Qué bueno verte! Ya estábamos esperándote.";
            $frases[] = "¡Bienvenido de nuevo! ¿Lo de siempre?";
        }
        
        // Frases generales
        $frases[] = "¡Hola! ¿Qué te gustaría hoy?";
        $frases[] = "¿Probamos algo nuevo o seguimos con tus favoritos?";
        $frases[] = "¡Qué bueno verte! ¿Te preparo lo habitual?";
        
        return $frases;
    }
    
    /**
     * Obtener perfil completo del cliente
     */
    public function getPerfilCompleto($cliente_id) {
        $cliente = fetch("SELECT * FROM clientes WHERE id = ? AND empresa_id = ?", [$cliente_id, $this->empresa_id]);
        
        if (!$cliente) {
            return null;
        }
        
        $analisis = $this->analizarComprasFrecuentes($cliente_id);
        $recomendaciones = $this->generarRecomendaciones($cliente_id);
        
        return [
            'cliente' => $cliente,
            'analisis' => $analisis,
            'recomendaciones' => $recomendaciones,
            'fecha_perfil' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Detectar si es cliente frecuente
     */
    public function esClienteFrecuente($cliente_id) {
        $stats = fetch("
            SELECT COUNT(*) as total_ventas, 
                   MAX(v.fecha) as ultima_compra,
                   DATEDIFF(NOW(), MAX(v.fecha)) as dias_desde_ultima
            FROM ventas v
            WHERE v.cliente_id = ? AND v.empresa_id = ?
        ", [$cliente_id, $this->empresa_id]);
        
        if (!$stats || $stats['total_ventas'] == 0) {
            return ['frecuente' => false, 'razon' => 'Sin compras registradas'];
        }
        
        // Criterios para cliente frecuente
        $frecuente = false;
        $razon = '';
        
        if ($stats['total_ventas'] >= 10) {
            $frecuente = true;
            $razon = 'Más de 10 compras';
        } elseif ($stats['total_ventas'] >= 5 && $stats['dias_desde_ultima'] <= 30) {
            $frecuente = true;
            $razon = 'Más de 5 compras en los últimos 30 días';
        } elseif ($stats['dias_desde_ultima'] <= 7 && $stats['total_ventas'] >= 3) {
            $frecuente = true;
            $razon = 'Compra semanal regular';
        }
        
        return [
            'frecuente' => $frecuente,
            'razon' => $razon,
            'total_ventas' => $stats['total_ventas'],
            'ultima_compra' => $stats['ultima_compra'],
            'dias_desde_ultima' => $stats['dias_desde_ultima']
        ];
    }
    
    /**
     * Obtener clientes VIP (más valiosos)
     */
    public function getClientesVIP($limite = 20) {
        return fetchAll("
            SELECT c.*, COUNT(v.id) as total_ventas, SUM(v.total) as total_gastado,
                   AVG(v.total) as ticket_promedio, MAX(v.fecha) as ultima_compra,
                   DATEDIFF(NOW(), MAX(v.fecha)) as dias_desde_ultima
            FROM clientes c
            JOIN ventas v ON c.id = v.cliente_id
            WHERE c.empresa_id = ? AND v.empresa_id = ?
            GROUP BY c.id
            HAVING total_ventas >= 3 AND total_gastado > 0
            ORDER BY total_gastado DESC, total_ventas DESC
            LIMIT " . intval($limite)
        ", [$this->empresa_id, $this->empresa_id]);
    }
    
    /**
     * Obtener clientes inactivos (para reactivar)
     */
    public function getClientesInactivos($dias = 30, $limite = 20) {
        return fetchAll("
            SELECT c.*, COUNT(v.id) as total_ventas, SUM(v.total) as total_gastado,
                   MAX(v.fecha) as ultima_compra,
                   DATEDIFF(NOW(), MAX(v.fecha)) as dias_desde_ultima
            FROM clientes c
            LEFT JOIN ventas v ON c.id = v.cliente_id AND v.empresa_id = ?
            WHERE c.empresa_id = ? AND 
                  (v.id IS NULL OR DATEDIFF(NOW(), MAX(v.fecha)) > ?)
            GROUP BY c.id
            HAVING total_ventas >= 2
            ORDER BY dias_desde_ultima DESC, total_gastado DESC
            LIMIT " . intval($limite)
        ", [$this->empresa_id, $this->empresa_id, $dias]);
    }
    
    /**
     * Generar insights de negocio
     */
    public function generarInsights() {
        $insights = [];
        
        // Mejores clientes por gasto
        $top_clientes = fetchAll("
            SELECT c.nombre, c.apellido, SUM(v.total) as total_gastado, COUNT(v.id) as total_ventas
            FROM clientes c
            JOIN ventas v ON c.id = v.cliente_id
            WHERE c.empresa_id = ? AND v.empresa_id = ? AND v.fecha >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY c.id
            ORDER BY total_gastado DESC
            LIMIT 5
        ", [$this->empresa_id, $this->empresa_id]);
        
        $insights['top_clientes_mes'] = $top_clientes;
        
        // Productos más populares
        $productos_populares = fetchAll("
            SELECT p.nombre, SUM(vi.cantidad) as total_vendido, COUNT(DISTINCT v.cliente_id) as clientes_distintos
            FROM venta_items vi
            JOIN ventas v ON vi.venta_id = v.id
            JOIN productos p ON vi.producto_id = p.id
            WHERE v.empresa_id = ? AND v.fecha >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY p.id
            ORDER BY total_vendido DESC
            LIMIT 5
        ", [$this->empresa_id]);
        
        $insights['productos_populares_mes'] = $productos_populares;
        
        // Horarios pico
        $horarios_pico = fetchAll("
            SELECT HOUR(v.fecha) as hora, COUNT(*) as total_ventas, SUM(v.total) as total_facturado
            FROM ventas v
            WHERE v.empresa_id = ? AND v.fecha >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY HOUR(v.fecha)
            ORDER BY total_ventas DESC
        ", [$this->empresa_id]);
        
        $insights['horarios_pico_semana'] = $horarios_pico;
        
        return $insights;
    }
}
?>
