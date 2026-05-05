<?php
require_once 'config.php';
requireLogin();

$user = getUser();
$empresa_id = $user['empresa_id'];

// Filtros por defecto
$fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-d');
$fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');
$tipo_reporte = $_GET['tipo_reporte'] ?? 'ganancias';
$filtro_rapido = $_GET['filtro_rapido'] ?? 'hoy';

// Aplicar filtro rápido
if ($filtro_rapido === 'hoy') {
    $fecha_desde = date('Y-m-d 00:00:00');
    $fecha_hasta = date('Y-m-d 23:59:59');
} elseif ($filtro_rapido === 'ayer') {
    $fecha_desde = date('Y-m-d 00:00:00', strtotime('-1 day'));
    $fecha_hasta = date('Y-m-d 23:59:59', strtotime('-1 day'));
} elseif ($filtro_rapido === 'semana') {
    $fecha_desde = date('Y-m-d 00:00:00', strtotime('monday this week'));
    $fecha_hasta = date('Y-m-d 23:59:59');
} elseif ($filtro_rapido === 'mes') {
    $fecha_desde = date('Y-m-01 00:00:00');
    $fecha_hasta = date('Y-m-t 23:59:59');
} elseif ($filtro_rapido === 'mes_anterior') {
    $fecha_desde = date('Y-m-01 00:00:00', strtotime('-1 month'));
    $fecha_hasta = date('Y-m-t 23:59:59', strtotime('-1 month'));
} elseif ($filtro_rapido === 'año') {
    $fecha_desde = date('Y-01-01 00:00:00');
    $fecha_hasta = date('Y-12-31 23:59:59');
} else {
    // Para fechas personalizadas, agregar hora si no viene
    if (strlen($fecha_desde) === 10) {
        $fecha_desde .= ' 00:00:00';
    }
    if (strlen($fecha_hasta) === 10) {
        $fecha_hasta .= ' 23:59:59';
    }
}

// Obtener datos según tipo de reporte
$resultados = [];
$estadisticas = [];

if ($tipo_reporte === 'ganancias') {
    $resultados = fetchAll("
        SELECT DATE(fecha) as fecha, SUM(total) as ventas, 
               COALESCE((SELECT SUM(monto) FROM finanzas WHERE tipo='GASTO' AND empresa_id=? AND DATE(fecha)=DATE(v.fecha)), 0) as gastos
        FROM ventas v 
        WHERE empresa_id=? AND fecha BETWEEN ? AND ?
        GROUP BY DATE(fecha)
        ORDER BY fecha DESC
    ", [$empresa_id, $empresa_id, $fecha_desde, $fecha_hasta]);
    
    $total_ventas = 0;
    $total_gastos = 0;
    foreach ($resultados as $r) {
        $total_ventas += $r['ventas'];
        $total_gastos += $r['gastos'];
    }
    $estadisticas = [
        'total_ventas' => $total_ventas,
        'total_gastos' => $total_gastos,
        'ganancia' => $total_ventas - $total_gastos
    ];
}

if ($tipo_reporte === 'ventas') {
    $resultados = fetchAll("
        SELECT v.id, DATE(v.fecha) as fecha, v.total, c.nombre as cliente, u.nombre as vendedor
        FROM ventas v
        LEFT JOIN clientes c ON v.cliente_id = c.id
        LEFT JOIN usuarios u ON v.usuario_id = u.id
        WHERE v.empresa_id=? AND v.fecha BETWEEN ? AND ?
        ORDER BY v.fecha DESC
    ", [$empresa_id, $fecha_desde, $fecha_hasta]);
    
    $estadisticas = [
        'total_ventas' => array_sum(array_column($resultados, 'total')),
        'cantidad_ventas' => count($resultados)
    ];
}

if ($tipo_reporte === 'productos') {
    $resultados = fetchAll("
        SELECT p.nombre, SUM(vi.cantidad) as cantidad, SUM(vi.subtotal) as total
        FROM venta_items vi
        JOIN ventas v ON vi.venta_id = v.id
        JOIN productos p ON vi.producto_id = p.id
        WHERE v.empresa_id=? AND v.fecha BETWEEN ? AND ?
        GROUP BY p.id
        ORDER BY cantidad DESC
        LIMIT 20
    ", [$empresa_id, $fecha_desde, $fecha_hasta]);

    $estadisticas = [
        'total_productos' => array_sum(array_column($resultados, 'cantidad')),
        'total_ingresos' => array_sum(array_column($resultados, 'total'))
    ];
}

if ($tipo_reporte === 'gastos') {
    $resultados = fetchAll("
        SELECT DATE(f.fecha) as fecha, f.descripcion, SUM(f.monto) as monto, COUNT(f.id) as cantidad
        FROM finanzas f
        WHERE f.empresa_id=? AND f.fecha BETWEEN ? AND ? AND f.tipo='GASTO'
        GROUP BY DATE(f.fecha), f.descripcion
        ORDER BY f.fecha DESC
    ", [$empresa_id, $fecha_desde, $fecha_hasta]);

    $estadisticas = [
        'total_gastos' => array_sum(array_column($resultados, 'monto')),
        'cantidad_registros' => count($resultados)
    ];
}

if ($tipo_reporte === 'ventas_brutas') {
    $resultados = fetchAll("
        SELECT DATE(v.fecha) as fecha, SUM(v.total) as total_ventas, COUNT(v.id) as cantidad_ventas
        FROM ventas v
        WHERE v.empresa_id=? AND v.fecha BETWEEN ? AND ?
        GROUP BY DATE(v.fecha)
        ORDER BY v.fecha DESC
    ", [$empresa_id, $fecha_desde, $fecha_hasta]);

    $estadisticas = [
        'total_ventas_brutas' => array_sum(array_column($resultados, 'total_ventas')),
        'cantidad_ventas' => array_sum(array_column($resultados, 'cantidad_ventas'))
    ];
}

if ($tipo_reporte === 'impuestos') {
    $resultados = fetchAll("
        SELECT DATE(v.fecha) as fecha, SUM(v.total * 0.21) as iva_calculado, COUNT(v.id) as cantidad_ventas
        FROM ventas v
        WHERE v.empresa_id=? AND v.fecha BETWEEN ? AND ?
        GROUP BY DATE(v.fecha)
        ORDER BY v.fecha DESC
    ", [$empresa_id, $fecha_desde, $fecha_hasta]);

    $estadisticas = [
        'total_iva' => array_sum(array_column($resultados, 'iva_calculado')),
        'cantidad_ventas' => array_sum(array_column($resultados, 'cantidad_ventas'))
    ];
}

if ($tipo_reporte === 'ganancia_neta') {
    $resultados = fetchAll("
        SELECT DATE(v.fecha) as fecha,
               SUM(v.total) as total_ventas,
               0 as total_ganancia,
               SUM(v.total * 0.21) as total_iva,
               COUNT(v.id) as cantidad_ventas
        FROM ventas v
        WHERE v.empresa_id=? AND v.fecha BETWEEN ? AND ?
        GROUP BY DATE(v.fecha)
        ORDER BY v.fecha DESC
    ", [$empresa_id, $fecha_desde, $fecha_hasta]);

    $total_ventas_brutas = array_sum(array_column($resultados, 'total_ventas'));
    $total_ganancia = array_sum(array_column($resultados, 'total_ganancia'));
    $total_iva = array_sum(array_column($resultados, 'total_iva'));
    $total_ganancia_neta = $total_ventas_brutas - $total_iva;

    $estadisticas = [
        'total_ventas_brutas' => $total_ventas_brutas,
        'total_ganancia' => $total_ganancia,
        'total_iva' => $total_iva,
        'total_ganancia_neta' => $total_ganancia_neta
    ];
}

if ($tipo_reporte === 'clientes') {
    $resultados = fetchAll("
        SELECT c.nombre, c.apellido, COUNT(v.id) as cantidad_ventas, SUM(v.total) as total_comprado
        FROM clientes c
        LEFT JOIN ventas v ON c.id = v.cliente_id
        WHERE c.empresa_id=? AND (v.fecha BETWEEN ? AND ? OR v.fecha IS NULL)
        GROUP BY c.id
        ORDER BY total_comprado DESC
        LIMIT 20
    ", [$empresa_id, $fecha_desde, $fecha_hasta]);

    $estadisticas = [
        'total_clientes' => count($resultados),
        'clientes_activos' => count(array_filter($resultados, fn($r) => $r['cantidad_ventas'] > 0)),
        'total_ventas_clientes' => array_sum(array_column($resultados, 'total_comprado'))
    ];
}

if ($tipo_reporte === 'stock') {
    $resultados = fetchAll("
        SELECT p.nombre, p.stock, p.precio, (p.stock * p.precio) as valor_inventario
        FROM productos p
        WHERE p.empresa_id=? AND p.activo = 1
        ORDER BY valor_inventario DESC
        LIMIT 50
    ", [$empresa_id]);

    $estadisticas = [
        'total_productos' => count($resultados),
        'valor_total_inventario' => array_sum(array_column($resultados, 'valor_inventario')),
        'stock_total' => array_sum(array_column($resultados, 'stock'))
    ];
}

if ($tipo_reporte === 'vendedores') {
    $resultados = fetchAll("
        SELECT u.nombre as vendedor, COUNT(v.id) as cantidad_ventas, SUM(v.total) as total_ventas
        FROM usuarios u
        LEFT JOIN ventas v ON u.id = v.usuario_id
        WHERE u.empresa_id=? AND (v.fecha BETWEEN ? AND ? OR v.fecha IS NULL)
        GROUP BY u.id
        ORDER BY total_ventas DESC
    ", [$empresa_id, $fecha_desde, $fecha_hasta]);

    $estadisticas = [
        'total_vendedores' => count($resultados),
        'vendedores_activos' => count(array_filter($resultados, fn($r) => $r['cantidad_ventas'] > 0)),
        'total_ventas_vendedores' => array_sum(array_column($resultados, 'total_ventas'))
    ];
}

if ($tipo_reporte === 'metodos_pago') {
    $resultados = fetchAll("
        SELECT metodo_pago, COUNT(*) as cantidad, SUM(total) as monto_total
        FROM ventas 
        WHERE empresa_id=? AND fecha BETWEEN ? AND ? AND metodo_pago IS NOT NULL
        GROUP BY metodo_pago
        ORDER BY monto_total DESC
    ", [$empresa_id, $fecha_desde, $fecha_hasta]);

    $estadisticas = [
        'total_transacciones' => array_sum(array_column($resultados, 'cantidad')),
        'monto_total_operaciones' => array_sum(array_column($resultados, 'monto_total')),
        'metodos_utilizados' => count($resultados)
    ];
}

// Datos para gráficos
$datos_graficos = [];

// Productos más vendidos para gráfico
$datos_graficos['productos'] = fetchAll("
    SELECT p.nombre, SUM(vi.cantidad) as cantidad 
    FROM venta_items vi
    JOIN ventas v ON vi.venta_id = v.id
    JOIN productos p ON vi.producto_id = p.id
    WHERE v.empresa_id=? AND v.fecha BETWEEN ? AND ?
    GROUP BY p.id
    ORDER BY cantidad DESC
    LIMIT 10
", [$empresa_id, $fecha_desde, $fecha_hasta]);

// Ventas por día para gráfico
$datos_graficos['ventas_dia'] = fetchAll("
    SELECT DATE(v.fecha) as fecha, SUM(v.total) as total
    FROM ventas v
    WHERE v.empresa_id=? AND v.fecha BETWEEN ? AND ?
    GROUP BY DATE(v.fecha)
    ORDER BY fecha ASC
", [$empresa_id, $fecha_desde, $fecha_hasta]);

// Métodos de pago para gráfico
$datos_graficos['metodos_pago'] = fetchAll("
    SELECT metodo_pago, COUNT(*) as cantidad, SUM(total) as monto_total
    FROM ventas 
    WHERE empresa_id=? AND fecha BETWEEN ? AND ? AND metodo_pago IS NOT NULL
    GROUP BY metodo_pago
    ORDER BY monto_total DESC
", [$empresa_id, $fecha_desde, $fecha_hasta]);

// Ventas por categoría para gráfico
$datos_graficos['categorias'] = fetchAll("
    SELECT c.nombre as categoria, SUM(vi.cantidad) as cantidad, SUM(vi.subtotal) as total
    FROM venta_items vi
    JOIN ventas v ON vi.venta_id = v.id
    JOIN productos p ON vi.producto_id = p.id
    LEFT JOIN categorias c ON p.categoria_id = c.id
    WHERE v.empresa_id=? AND v.fecha BETWEEN ? AND ?
    GROUP BY COALESCE(c.id, 0), c.nombre
    ORDER BY total DESC
    LIMIT 8
", [$empresa_id, $fecha_desde, $fecha_hasta]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - NEXUS POS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-gray-900 to-gray-800 text-gray-100">
    <header class="bg-gray-900 shadow-lg">
        <div class="container mx-auto px-6 py-4 flex justify-between items-center">
            <h1 class="text-2xl font-bold text-blue-400">📊 PANEL DE REPORTES</h1>
            <a href="dashboard.php" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded">VOLVER</a>
        </div>
    </header>
    <main class="container mx-auto px-6 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <!-- Panel de Filtros -->
            <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
                <h3 class="text-xl font-bold text-white mb-4">Filtros de Reporte</h3>
                <form method="GET" class="space-y-4">
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Tipo de Reporte</label>
                        <select name="tipo_reporte" class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                            <option value="ganancias" <?= $tipo_reporte === 'ganancias' ? 'selected' : '' ?>>📊 Ganancias</option>
                            <option value="ventas" <?= $tipo_reporte === 'ventas' ? 'selected' : '' ?>>💰 Ventas Detalladas</option>
                            <option value="ventas_brutas" <?= $tipo_reporte === 'ventas_brutas' ? 'selected' : '' ?>>📈 Ventas Brutas</option>
                            <option value="productos" <?= $tipo_reporte === 'productos' ? 'selected' : '' ?>>📦 Productos Más Vendidos</option>
                            <option value="clientes" <?= $tipo_reporte === 'clientes' ? 'selected' : '' ?>>👥 Clientes Principales</option>
                            <option value="vendedores" <?= $tipo_reporte === 'vendedores' ? 'selected' : '' ?>>🏪 Rendimiento Vendedores</option>
                            <option value="metodos_pago" <?= $tipo_reporte === 'metodos_pago' ? 'selected' : '' ?>>💳 Métodos de Pago</option>
                            <option value="stock" <?= $tipo_reporte === 'stock' ? 'selected' : '' ?>>📋 Estado de Stock</option>
                            <option value="gastos" <?= $tipo_reporte === 'gastos' ? 'selected' : '' ?>>💸 Gastos</option>
                            <option value="impuestos" <?= $tipo_reporte === 'impuestos' ? 'selected' : '' ?>>🧾 Impuestos IVA</option>
                            <option value="ganancia_neta" <?= $tipo_reporte === 'ganancia_neta' ? 'selected' : '' ?>>🎯 Ganancia Neta</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Filtro Rápido</label>
                        <select name="filtro_rapido" class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                            <option value="hoy" <?= $filtro_rapido === 'hoy' ? 'selected' : '' ?>>Hoy</option>
                            <option value="ayer" <?= $filtro_rapido === 'ayer' ? 'selected' : '' ?>>Ayer</option>
                            <option value="semana" <?= $filtro_rapido === 'semana' ? 'selected' : '' ?>>Esta Semana</option>
                            <option value="mes" <?= $filtro_rapido === 'mes' ? 'selected' : '' ?>>Este Mes</option>
                            <option value="mes_anterior" <?= $filtro_rapido === 'mes_anterior' ? 'selected' : '' ?>>Mes Anterior</option>
                            <option value="año" <?= $filtro_rapido === 'año' ? 'selected' : '' ?>>Este Año</option>
                            <option value="personalizado" <?= $filtro_rapido === 'personalizado' ? 'selected' : '' ?>>Personalizado</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Fecha Desde</label>
                        <input type="date" name="fecha_desde" value="<?= $fecha_desde ?>"
                               class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Fecha Hasta</label>
                        <input type="date" name="fecha_hasta" value="<?= $fecha_hasta ?>"
                               class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                    </div>
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 rounded font-bold">
                        🔍 GENERAR REPORTE
                    </button>
                    <button onclick="exportarCSV()" class="w-full bg-green-600 hover:bg-green-700 text-white py-3 rounded font-bold mt-2">
                        📤 EXPORTAR CSV
                    </button>
                    <button onclick="limpiarResultados()" class="w-full bg-red-600 hover:bg-red-700 text-white py-3 rounded font-bold mt-2">
                        🗑️ LIMPIAR
                    </button>
                </form>
                
                <!-- Estadísticas -->
                <div class="mt-6 pt-6 border-t border-gray-700">
                    <h4 class="text-white font-bold mb-3">Resumen</h4>
                    <?php if ($tipo_reporte === 'ganancias'): ?>
                    <div class="space-y-2">
                        <p class="text-gray-400 text-sm">Ventas: <span class="text-white font-bold">$<?= number_format($estadisticas['total_ventas'] ?? 0, 2) ?></span></p>
                        <p class="text-gray-400 text-sm">Gastos: <span class="text-red-400 font-bold">$<?= number_format($estadisticas['total_gastos'] ?? 0, 2) ?></span></p>
                        <p class="text-gray-400 text-sm">Ganancia: <span class="text-green-400 font-bold">$<?= number_format($estadisticas['ganancia'] ?? 0, 2) ?></span></p>
                    </div>
                    <?php elseif ($tipo_reporte === 'ventas'): ?>
                    <div class="space-y-2">
                        <p class="text-gray-400 text-sm">Cantidad Ventas: <span class="text-white font-bold"><?= $estadisticas['cantidad_ventas'] ?? 0 ?></span></p>
                        <p class="text-gray-400 text-sm">Total: <span class="text-green-400 font-bold">$<?= number_format($estadisticas['total_ventas'] ?? 0, 2) ?></span></p>
                    </div>
                    <?php elseif ($tipo_reporte === 'productos'): ?>
                    <div class="space-y-2">
                        <p class="text-gray-400 text-sm">Productos Vendidos: <span class="text-white font-bold"><?= $estadisticas['total_productos'] ?? 0 ?></span></p>
                        <p class="text-gray-400 text-sm">Ingresos: <span class="text-green-400 font-bold">$<?= number_format($estadisticas['total_ingresos'] ?? 0, 2) ?></span></p>
                    </div>
                    <?php elseif ($tipo_reporte === 'gastos'): ?>
                    <div class="space-y-2">
                        <p class="text-gray-400 text-sm">Registros: <span class="text-white font-bold"><?= $estadisticas['cantidad_registros'] ?? 0 ?></span></p>
                        <p class="text-gray-400 text-sm">Total Gastos: <span class="text-red-400 font-bold">$<?= number_format($estadisticas['total_gastos'] ?? 0, 2) ?></span></p>
                    </div>
                    <?php elseif ($tipo_reporte === 'ventas_brutas'): ?>
                    <div class="space-y-2">
                        <p class="text-gray-400 text-sm">Ventas: <span class="text-white font-bold"><?= $estadisticas['cantidad_ventas'] ?? 0 ?></span></p>
                        <p class="text-gray-400 text-sm">Total: <span class="text-green-400 font-bold">$<?= number_format($estadisticas['total_ventas_brutas'] ?? 0, 2) ?></span></p>
                    </div>
                    <?php elseif ($tipo_reporte === 'impuestos'): ?>
                    <div class="space-y-2">
                        <p class="text-gray-400 text-sm">Ventas: <span class="text-white font-bold"><?= $estadisticas['cantidad_ventas'] ?? 0 ?></span></p>
                        <p class="text-gray-400 text-sm">Total IVA: <span class="text-red-400 font-bold">$<?= number_format($estadisticas['total_iva'] ?? 0, 2) ?></span></p>
                    </div>
                    <?php elseif ($tipo_reporte === 'ganancia_neta'): ?>
                    <div class="space-y-2">
                        <p class="text-gray-400 text-sm">Ventas: <span class="text-white font-bold">$<?= number_format($estadisticas['total_ventas_brutas'] ?? 0, 2) ?></span></p>
                        <p class="text-gray-400 text-sm">IVA: <span class="text-red-400 font-bold">$<?= number_format($estadisticas['total_iva'] ?? 0, 2) ?></span></p>
                        <p class="text-gray-400 text-sm">Ganancia Neta: <span class="text-green-400 font-bold">$<?= number_format($estadisticas['total_ganancia_neta'] ?? 0, 2) ?></span></p>
                    </div>
                    <?php elseif ($tipo_reporte === 'clientes'): ?>
                    <div class="space-y-2">
                        <p class="text-gray-400 text-sm">Total Clientes: <span class="text-white font-bold"><?= $estadisticas['total_clientes'] ?? 0 ?></span></p>
                        <p class="text-gray-400 text-sm">Clientes Activos: <span class="text-green-400 font-bold"><?= $estadisticas['clientes_activos'] ?? 0 ?></span></p>
                        <p class="text-gray-400 text-sm">Compras Totales: <span class="text-blue-400 font-bold">$<?= number_format($estadisticas['total_ventas_clientes'] ?? 0, 2) ?></span></p>
                    </div>
                    <?php elseif ($tipo_reporte === 'stock'): ?>
                    <div class="space-y-2">
                        <p class="text-gray-400 text-sm">Productos: <span class="text-white font-bold"><?= $estadisticas['total_productos'] ?? 0 ?></span></p>
                        <p class="text-gray-400 text-sm">Valor Inventario: <span class="text-green-400 font-bold">$<?= number_format($estadisticas['valor_total_inventario'] ?? 0, 2) ?></span></p>
                        <p class="text-gray-400 text-sm">Stock Total: <span class="text-blue-400 font-bold"><?= $estadisticas['stock_total'] ?? 0 ?></span></p>
                    </div>
                    <?php elseif ($tipo_reporte === 'vendedores'): ?>
                    <div class="space-y-2">
                        <p class="text-gray-400 text-sm">Total Vendedores: <span class="text-white font-bold"><?= $estadisticas['total_vendedores'] ?? 0 ?></span></p>
                        <p class="text-gray-400 text-sm">Vendedores Activos: <span class="text-green-400 font-bold"><?= $estadisticas['vendedores_activos'] ?? 0 ?></span></p>
                        <p class="text-gray-400 text-sm">Ventas Totales: <span class="text-blue-400 font-bold">$<?= number_format($estadisticas['total_ventas_vendedores'] ?? 0, 2) ?></span></p>
                    </div>
                    <?php elseif ($tipo_reporte === 'metodos_pago'): ?>
                    <div class="space-y-2">
                        <p class="text-gray-400 text-sm">Transacciones: <span class="text-white font-bold"><?= $estadisticas['total_transacciones'] ?? 0 ?></span></p>
                        <p class="text-gray-400 text-sm">Monto Total: <span class="text-green-400 font-bold">$<?= number_format($estadisticas['monto_total_operaciones'] ?? 0, 2) ?></span></p>
                        <p class="text-gray-400 text-sm">Métodos Usados: <span class="text-blue-400 font-bold"><?= $estadisticas['metodos_utilizados'] ?? 0 ?></span></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Panel de Gráficos -->
            <div class="lg:col-span-3 bg-gray-800 rounded-lg border border-gray-700 p-6 mb-6">
                <h3 class="text-xl font-bold text-white mb-4">📈 Gráficos</h3>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Gráfico de productos más vendidos -->
                    <div class="bg-gray-700 rounded-lg p-4 h-full flex flex-col">
                        <h4 class="text-lg font-bold text-white mb-3">📦 Productos Más Vendidos</h4>
                        <div class="flex-1 relative min-h-[200px]">
                            <canvas id="chartProductos"></canvas>
                        </div>
                    </div>
                    
                    <!-- Gráfico de ventas por día -->
                    <div class="bg-gray-700 rounded-lg p-4 h-full flex flex-col">
                        <h4 class="text-lg font-bold text-white mb-3">💰 Ventas por Día</h4>
                        <div class="flex-1 relative min-h-[200px]">
                            <canvas id="chartVentas"></canvas>
                        </div>
                    </div>
                    
                    <!-- Gráfico de métodos de pago -->
                    <div class="bg-gray-700 rounded-lg p-4 h-full flex flex-col">
                        <h4 class="text-lg font-bold text-white mb-3">💳 Métodos de Pago</h4>
                        <div class="flex-1 relative min-h-[200px]">
                            <canvas id="chartPagos"></canvas>
                        </div>
                    </div>
                    
                    <!-- Gráfico de categorías -->
                    <div class="bg-gray-700 rounded-lg p-4 h-full flex flex-col">
                        <h4 class="text-lg font-bold text-white mb-3">📋 Ventas por Categoría</h4>
                        <div class="flex-1 relative min-h-[200px]">
                            <canvas id="chartCategorias"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Panel de Resultados -->
            <div class="lg:col-span-3 bg-gray-800 rounded-lg border border-gray-700 p-6">
                <h3 class="text-xl font-bold text-white mb-4">Resultados</h3>
                <div class="overflow-x-auto max-h-96 overflow-y-auto">
                    <table class="w-full" id="tablaResultados">
                        <thead>
                            <tr class="border-b border-gray-700 sticky top-0 bg-gray-800">
                                <?php if ($tipo_reporte === 'ganancias'): ?>
                                <th class="text-left py-2 px-3 text-gray-400">Fecha</th>
                                <th class="text-left py-2 px-3 text-gray-400">Ventas</th>
                                <th class="text-left py-2 px-3 text-gray-400">Gastos</th>
                                <th class="text-left py-2 px-3 text-gray-400">Ganancia</th>
                                <?php elseif ($tipo_reporte === 'ventas'): ?>
                                <th class="text-left py-2 px-3 text-gray-400">ID</th>
                                <th class="text-left py-2 px-3 text-gray-400">Fecha</th>
                                <th class="text-left py-2 px-3 text-gray-400">Total</th>
                                <th class="text-left py-2 px-3 text-gray-400">Cliente</th>
                                <th class="text-left py-2 px-3 text-gray-400">Vendedor</th>
                                <?php elseif ($tipo_reporte === 'productos'): ?>
                                <th class="text-left py-2 px-3 text-gray-400">Producto</th>
                                <th class="text-left py-2 px-3 text-gray-400">Cantidad</th>
                                <th class="text-left py-2 px-3 text-gray-400">Total</th>
                                <?php elseif ($tipo_reporte === 'gastos'): ?>
                                <th class="text-left py-2 px-3 text-gray-400">Fecha</th>
                                <th class="text-left py-2 px-3 text-gray-400">Descripción</th>
                                <th class="text-left py-2 px-3 text-gray-400">Monto</th>
                                <th class="text-left py-2 px-3 text-gray-400">Cantidad</th>
                                <?php elseif ($tipo_reporte === 'ventas_brutas'): ?>
                                <th class="text-left py-2 px-3 text-gray-400">Fecha</th>
                                <th class="text-left py-2 px-3 text-gray-400">Ventas</th>
                                <th class="text-left py-2 px-3 text-gray-400">Cantidad</th>
                                <?php elseif ($tipo_reporte === 'impuestos'): ?>
                                <th class="text-left py-2 px-3 text-gray-400">Fecha</th>
                                <th class="text-left py-2 px-3 text-gray-400">IVA (21%)</th>
                                <th class="text-left py-2 px-3 text-gray-400">Cantidad</th>
                                <?php elseif ($tipo_reporte === 'ganancia_neta'): ?>
                                <th class="text-left py-2 px-3 text-gray-400">Fecha</th>
                                <th class="text-left py-2 px-3 text-gray-400">Ventas</th>
                                <th class="text-left py-2 px-3 text-gray-400">Ganancia</th>
                                <th class="text-left py-2 px-3 text-gray-400">IVA</th>
                                <th class="text-left py-2 px-3 text-gray-400">Ganancia Neta</th>
                                <?php elseif ($tipo_reporte === 'clientes'): ?>
                                <th class="text-left py-2 px-3 text-gray-400">Cliente</th>
                                <th class="text-left py-2 px-3 text-gray-400">Compras</th>
                                <th class="text-left py-2 px-3 text-gray-400">Total Comprado</th>
                                <?php elseif ($tipo_reporte === 'stock'): ?>
                                <th class="text-left py-2 px-3 text-gray-400">Producto</th>
                                <th class="text-left py-2 px-3 text-gray-400">Stock</th>
                                <th class="text-left py-2 px-3 text-gray-400">Precio</th>
                                <th class="text-left py-2 px-3 text-gray-400">Valor Inventario</th>
                                <?php elseif ($tipo_reporte === 'vendedores'): ?>
                                <th class="text-left py-2 px-3 text-gray-400">Vendedor</th>
                                <th class="text-left py-2 px-3 text-gray-400">Ventas</th>
                                <th class="text-left py-2 px-3 text-gray-400">Total Vendido</th>
                                <?php elseif ($tipo_reporte === 'metodos_pago'): ?>
                                <th class="text-left py-2 px-3 text-gray-400">Método de Pago</th>
                                <th class="text-left py-2 px-3 text-gray-400">Transacciones</th>
                                <th class="text-left py-2 px-3 text-gray-400">Monto Total</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($resultados as $r): ?>
                            <tr class="border-b border-gray-700 hover:bg-gray-700">
                                <?php if ($tipo_reporte === 'ganancias'): ?>
                                <td class="py-2 px-3 text-sm"><?= $r['fecha'] ?></td>
                                <td class="py-2 px-3 text-sm text-green-400">$<?= number_format($r['ventas'], 2) ?></td>
                                <td class="py-2 px-3 text-sm text-red-400">$<?= number_format($r['gastos'], 2) ?></td>
                                <td class="py-2 px-3 text-sm font-bold $<?= $r['ventas'] - $r['gastos'] >= 0 ? 'text-green-400' : 'text-red-400' ?>">
                                    $<?= number_format($r['ventas'] - $r['gastos'], 2) ?>
                                </td>
                                <?php elseif ($tipo_reporte === 'ventas'): ?>
                                <td class="py-2 px-3 text-sm"><?= $r['id'] ?></td>
                                <td class="py-2 px-3 text-sm"><?= $r['fecha'] ?></td>
                                <td class="py-2 px-3 text-sm text-green-400">$<?= number_format($r['total'], 2) ?></td>
                                <td class="py-2 px-3 text-sm"><?= htmlspecialchars($r['cliente'] ?? '-') ?></td>
                                <td class="py-2 px-3 text-sm"><?= htmlspecialchars($r['vendedor'] ?? '-') ?></td>
                                <?php elseif ($tipo_reporte === 'productos'): ?>
                                <td class="py-2 px-3 text-sm"><?= htmlspecialchars($r['nombre']) ?></td>
                                <td class="py-2 px-3 text-sm"><?= $r['cantidad'] ?></td>
                                <td class="py-2 px-3 text-sm text-green-400">$<?= number_format($r['total'], 2) ?></td>
                                <?php elseif ($tipo_reporte === 'gastos'): ?>
                                <td class="py-2 px-3 text-sm"><?= $r['fecha'] ?></td>
                                <td class="py-2 px-3 text-sm"><?= htmlspecialchars($r['descripcion']) ?></td>
                                <td class="py-2 px-3 text-sm text-red-400">$<?= number_format($r['monto'], 2) ?></td>
                                <td class="py-2 px-3 text-sm"><?= $r['cantidad'] ?></td>
                                <?php elseif ($tipo_reporte === 'ventas_brutas'): ?>
                                <td class="py-2 px-3 text-sm"><?= $r['fecha'] ?></td>
                                <td class="py-2 px-3 text-sm text-green-400">$<?= number_format($r['total_ventas'], 2) ?></td>
                                <td class="py-2 px-3 text-sm"><?= $r['cantidad_ventas'] ?></td>
                                <?php elseif ($tipo_reporte === 'impuestos'): ?>
                                <td class="py-2 px-3 text-sm"><?= $r['fecha'] ?></td>
                                <td class="py-2 px-3 text-sm text-red-400">$<?= number_format($r['iva_calculado'], 2) ?></td>
                                <td class="py-2 px-3 text-sm"><?= $r['cantidad_ventas'] ?></td>
                                <?php elseif ($tipo_reporte === 'ganancia_neta'): ?>
                                <td class="py-2 px-3 text-sm"><?= $r['fecha'] ?></td>
                                <td class="py-2 px-3 text-sm text-green-400">$<?= number_format($r['total_ventas'], 2) ?></td>
                                <td class="py-2 px-3 text-sm text-green-400">$<?= number_format($r['total_ganancia'], 2) ?></td>
                                <td class="py-2 px-3 text-sm text-red-400">$<?= number_format($r['total_iva'], 2) ?></td>
                                <td class="py-2 px-3 text-sm font-bold text-green-400">$<?= number_format($r['total_ventas'] - $r['total_iva'], 2) ?></td>
                                <?php elseif ($tipo_reporte === 'clientes'): ?>
                                <td class="py-2 px-3 text-sm"><?= htmlspecialchars($r['nombre'] . ' ' . ($r['apellido'] ?? '')) ?></td>
                                <td class="py-2 px-3 text-sm"><?= $r['cantidad_ventas'] ?></td>
                                <td class="py-2 px-3 text-sm text-green-400">$<?= number_format($r['total_comprado'], 2) ?></td>
                                <?php elseif ($tipo_reporte === 'stock'): ?>
                                <td class="py-2 px-3 text-sm"><?= htmlspecialchars($r['nombre']) ?></td>
                                <td class="py-2 px-3 text-sm"><?= $r['stock'] ?></td>
                                <td class="py-2 px-3 text-sm text-blue-400">$<?= number_format($r['precio'], 2) ?></td>
                                <td class="py-2 px-3 text-sm text-green-400">$<?= number_format($r['valor_inventario'], 2) ?></td>
                                <?php elseif ($tipo_reporte === 'vendedores'): ?>
                                <td class="py-2 px-3 text-sm"><?= htmlspecialchars($r['vendedor']) ?></td>
                                <td class="py-2 px-3 text-sm"><?= $r['cantidad_ventas'] ?></td>
                                <td class="py-2 px-3 text-sm text-green-400">$<?= number_format($r['total_ventas'], 2) ?></td>
                                <?php elseif ($tipo_reporte === 'metodos_pago'): ?>
                                <td class="py-2 px-3 text-sm"><?= htmlspecialchars($r['metodo_pago']) ?></td>
                                <td class="py-2 px-3 text-sm"><?= $r['cantidad'] ?></td>
                                <td class="py-2 px-3 text-sm text-green-400">$<?= number_format($r['monto_total'], 2) ?></td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Controles de paginación -->
                <div class="mt-4 flex justify-between items-center">
                    <div class="text-gray-400 text-sm">
                        Mostrando <span id="registrosMostrados"><?= min(50, count($resultados)) ?></span> de <span id="totalRegistros"><?= count($resultados) ?></span> registros
                    </div>
                    <div class="flex gap-2">
                        <button onclick="cambiarPagina(-1)" class="bg-gray-700 hover:bg-gray-600 text-white px-3 py-1 rounded text-sm" id="btnAnterior">
                            ← Anterior
                        </button>
                        <button onclick="cambiarPagina(1)" class="bg-gray-700 hover:bg-gray-600 text-white px-3 py-1 rounded text-sm" id="btnSiguiente">
                            Siguiente →
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Datos para gráficos desde PHP
        const datosProductos = <?= json_encode(array_column($datos_graficos['productos'], 'cantidad', 'nombre')) ?>;
        const datosVentasDia = <?= json_encode(array_column($datos_graficos['ventas_dia'], 'total', 'fecha')) ?>;
        const datosMetodosPago = <?= json_encode(array_column($datos_graficos['metodos_pago'], 'monto_total', 'metodo_pago')) ?>;
        const datosCategorias = <?= json_encode(array_column($datos_graficos['categorias'], 'total', 'categoria')) ?>;

        // Configuración de colores
        const colores = [
            '#8B5CF6', '#3B82F6', '#10B981', '#F59E0B', '#EF4444',
            '#EC4899', '#6366F1', '#14B8A6', '#F97316', '#84CC16'
        ];

        // Gráfico de Productos Más Vendidos
        const ctxProductos = document.getElementById('chartProductos').getContext('2d');
        new Chart(ctxProductos, {
            type: 'bar',
            data: {
                labels: Object.keys(datosProductos).length > 0 ? Object.keys(datosProductos) : ['Sin datos'],
                datasets: [{
                    label: 'Unidades Vendidas',
                    data: Object.values(datosProductos).length > 0 ? Object.values(datosProductos) : [0],
                    backgroundColor: colores[0],
                    borderColor: colores[0],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: { color: '#fff' }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { 
                            color: '#fff',
                            stepSize: 1,
                            callback: function(value) {
                                return Math.floor(value);
                            }
                        },
                        grid: { color: 'rgba(255,255,255,0.1)' }
                    },
                    x: {
                        ticks: { color: '#fff' },
                        grid: { color: 'rgba(255,255,255,0.1)' }
                    }
                }
            }
        });

        // Gráfico de Ventas por Día
        const ctxVentas = document.getElementById('chartVentas').getContext('2d');
        new Chart(ctxVentas, {
            type: 'line',
            data: {
                labels: Object.keys(datosVentasDia).length > 0 ? 
                    Object.keys(datosVentasDia).map(fecha => {
                        const d = new Date(fecha);
                        return d.toLocaleDateString('es-AR', { day: '2-digit', month: 'short' });
                    }) : ['Sin datos'],
                datasets: [{
                    label: 'Ventas ($)',
                    data: Object.values(datosVentasDia).length > 0 ? Object.values(datosVentasDia) : [0],
                    backgroundColor: colores[1] + '33',
                    borderColor: colores[1],
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: { color: '#fff' }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { 
                            color: '#fff',
                            callback: function(value) {
                                return '$' + value.toLocaleString('es-AR');
                            }
                        },
                        grid: { color: 'rgba(255,255,255,0.1)' }
                    },
                    x: {
                        ticks: { color: '#fff' },
                        grid: { color: 'rgba(255,255,255,0.1)' }
                    }
                }
            }
        });

        // Gráfico de Métodos de Pago
        const ctxPagos = document.getElementById('chartPagos').getContext('2d');
        new Chart(ctxPagos, {
            type: 'doughnut',
            data: {
                labels: Object.keys(datosMetodosPago).length > 0 ? Object.keys(datosMetodosPago) : ['Sin datos'],
                datasets: [{
                    data: Object.values(datosMetodosPago).length > 0 ? Object.values(datosMetodosPago) : [1],
                    backgroundColor: Object.values(datosMetodosPago).length > 0 ? colores : [colores[0]],
                    borderWidth: 2,
                    borderColor: '#1F2937'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { 
                            color: '#fff',
                            padding: 20
                        }
                    }
                }
            }
        });

        // Gráfico de Categorías
        const ctxCategorias = document.getElementById('chartCategorias').getContext('2d');
        new Chart(ctxCategorias, {
            type: 'pie',
            data: {
                labels: Object.keys(datosCategorias).length > 0 ? 
                    Object.keys(datosCategorias).map(cat => cat || 'Sin Categoría') : ['Sin datos'],
                datasets: [{
                    data: Object.values(datosCategorias).length > 0 ? Object.values(datosCategorias) : [1],
                    backgroundColor: Object.values(datosCategorias).length > 0 ? colores : [colores[0]],
                    borderWidth: 2,
                    borderColor: '#1F2937'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { 
                            color: '#fff',
                            padding: 20
                        }
                    }
                }
            }
        });

        // Sistema de paginación para evitar scroll infinito
        let paginaActual = 1;
        const registrosPorPagina = 50;
        const todosLosDatos = <?= json_encode($resultados) ?>;
        let datosFiltrados = [...todosLosDatos];

        function cambiarPagina(direccion) {
            const totalPaginas = Math.ceil(datosFiltrados.length / registrosPorPagina);
            paginaActual += direccion;
            
            if (paginaActual < 1) paginaActual = 1;
            if (paginaActual > totalPaginas) paginaActual = totalPaginas;
            
            actualizarTabla();
        }

        function actualizarTabla() {
            const tbody = document.querySelector('#tablaResultados tbody');
            const inicio = (paginaActual - 1) * registrosPorPagina;
            const fin = inicio + registrosPorPagina;
            const paginaDatos = datosFiltrados.slice(inicio, fin);
            
            // Generar HTML de la tabla
            let html = '';
            paginaDatos.forEach(r => {
                html += '<tr class="border-b border-gray-700 hover:bg-gray-700">';
                <?php if ($tipo_reporte === 'ganancias'): ?>
                html += `<td class="py-2 px-3 text-sm">${r.fecha}</td>`;
                html += `<td class="py-2 px-3 text-sm text-green-400">$${parseFloat(r.ventas).toFixed(2)}</td>`;
                html += `<td class="py-2 px-3 text-sm text-red-400">$${parseFloat(r.gastos).toFixed(2)}</td>`;
                html += `<td class="py-2 px-3 text-sm font-bold ${(parseFloat(r.ventas) - parseFloat(r.gastos) >= 0 ? 'text-green-400' : 'text-red-400')}">$${(parseFloat(r.ventas) - parseFloat(r.gastos)).toFixed(2)}</td>`;
                <?php elseif ($tipo_reporte === 'ventas'): ?>
                html += `<td class="py-2 px-3 text-sm">${r.id}</td>`;
                html += `<td class="py-2 px-3 text-sm">${r.fecha}</td>`;
                html += `<td class="py-2 px-3 text-sm text-green-400">$${parseFloat(r.total).toFixed(2)}</td>`;
                html += `<td class="py-2 px-3 text-sm">${r.cliente || '-'}</td>`;
                html += `<td class="py-2 px-3 text-sm">${r.vendedor || '-'}</td>`;
                <?php elseif ($tipo_reporte === 'productos'): ?>
                html += `<td class="py-2 px-3 text-sm">${r.nombre}</td>`;
                html += `<td class="py-2 px-3 text-sm">${r.cantidad}</td>`;
                html += `<td class="py-2 px-3 text-sm text-green-400">$${parseFloat(r.total).toFixed(2)}</td>`;
                <?php elseif ($tipo_reporte === 'gastos'): ?>
                html += `<td class="py-2 px-3 text-sm">${r.fecha}</td>`;
                html += `<td class="py-2 px-3 text-sm">${r.descripcion}</td>`;
                html += `<td class="py-2 px-3 text-sm text-red-400">$${parseFloat(r.monto).toFixed(2)}</td>`;
                html += `<td class="py-2 px-3 text-sm">${r.cantidad}</td>`;
                <?php elseif ($tipo_reporte === 'stock'): ?>
                html += `<td class="py-2 px-3 text-sm">${r.nombre}</td>`;
                html += `<td class="py-2 px-3 text-sm">${r.stock}</td>`;
                html += `<td class="py-2 px-3 text-sm text-blue-400">$${parseFloat(r.precio).toFixed(2)}</td>`;
                html += `<td class="py-2 px-3 text-sm text-green-400">$${parseFloat(r.valor_inventario).toFixed(2)}</td>`;
                <?php elseif ($tipo_reporte === 'metodos_pago'): ?>
                html += `<td class="py-2 px-3 text-sm">${r.metodo_pago}</td>`;
                html += `<td class="py-2 px-3 text-sm">${r.cantidad}</td>`;
                html += `<td class="py-2 px-3 text-sm text-green-400">$${parseFloat(r.monto_total).toFixed(2)}</td>`;
                <?php endif; ?>
                html += '</tr>';
            });
            
            tbody.innerHTML = html;
            
            // Actualizar controles
            const totalPaginas = Math.ceil(datosFiltrados.length / registrosPorPagina);
            document.getElementById('btnAnterior').disabled = paginaActual === 1;
            document.getElementById('btnSiguiente').disabled = paginaActual === totalPaginas;
            
            const inicioMostrado = (paginaActual - 1) * registrosPorPagina + 1;
            const finMostrado = Math.min(paginaActual * registrosPorPagina, datosFiltrados.length);
            document.getElementById('registrosMostrados').textContent = `${inicioMostrado}-${finMostrado}`;
            document.getElementById('totalRegistros').textContent = datosFiltrados.length;
        }

        // Inicializar paginación al cargar
        document.addEventListener('DOMContentLoaded', function() {
            actualizarTabla();
        });

        function exportarCSV() {
            const tabla = document.querySelector('table tbody');
            if (!tabla) return;

            const filas = tabla.querySelectorAll('tr');
            if (filas.length === 0) {
                alert('No hay datos para exportar');
                return;
            }

            let csv = '';
            filas.forEach(fila => {
                const celdas = fila.querySelectorAll('td');
                const datos = Array.from(celdas).map(celda => celda.textContent.trim()).join(',');
                csv += datos + '\n';
            });

            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', 'reporte_' + new Date().toISOString().slice(0,10) + '.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function limpiarResultados() {
            window.location.href = 'reportes.php';
        }
    </script>
</body>
</html>
