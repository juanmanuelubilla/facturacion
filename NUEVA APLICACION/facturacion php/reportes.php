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
    $fecha_desde = date('Y-m-d');
    $fecha_hasta = date('Y-m-d');
} elseif ($filtro_rapido === 'ayer') {
    $fecha_desde = date('Y-m-d', strtotime('-1 day'));
    $fecha_hasta = date('Y-m-d', strtotime('-1 day'));
} elseif ($filtro_rapido === 'semana') {
    $fecha_desde = date('Y-m-d', strtotime('monday this week'));
    $fecha_hasta = date('Y-m-d');
} elseif ($filtro_rapido === 'mes') {
    $fecha_desde = date('Y-m-01');
    $fecha_hasta = date('Y-m-t');
} elseif ($filtro_rapido === 'mes_anterior') {
    $fecha_desde = date('Y-m-01', strtotime('-1 month'));
    $fecha_hasta = date('Y-m-t', strtotime('-1 month'));
} elseif ($filtro_rapido === 'año') {
    $fecha_desde = date('Y-01-01');
    $fecha_hasta = date('Y-12-31');
}

// Obtener datos según tipo de reporte
$resultados = [];
$estadisticas = [];

if ($tipo_reporte === 'ganancias') {
    $resultados = fetchAll("
        SELECT DATE(fecha) as fecha, SUM(total) as ventas, 
               (SELECT SUM(monto) FROM finanzas WHERE tipo='GASTO' AND empresa_id=? AND DATE(fecha)=DATE(v.fecha)) as gastos
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
        FROM ventas_items vi
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
        WHERE v.empresa_id=? AND v.fecha BETWEEN ? AND ? AND v.estado='completado'
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
        WHERE v.empresa_id=? AND v.fecha BETWEEN ? AND ? AND v.estado='completado'
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
               SUM(v.ganancia) as total_ganancia,
               SUM(v.total * 0.21) as total_iva,
               COUNT(v.id) as cantidad_ventas
        FROM ventas v
        WHERE v.empresa_id=? AND v.fecha BETWEEN ? AND ? AND v.estado='completado'
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - NEXUS POS</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
                            <option value="ganancias" <?= $tipo_reporte === 'ganancias' ? 'selected' : '' ?>>Ganancias</option>
                            <option value="gastos" <?= $tipo_reporte === 'gastos' ? 'selected' : '' ?>>Gastos</option>
                            <option value="ventas_brutas" <?= $tipo_reporte === 'ventas_brutas' ? 'selected' : '' ?>>Ventas Brutas</option>
                            <option value="impuestos" <?= $tipo_reporte === 'impuestos' ? 'selected' : '' ?>>Impuestos IVA</option>
                            <option value="ganancia_neta" <?= $tipo_reporte === 'ganancia_neta' ? 'selected' : '' ?>>Ganancia Neta</option>
                            <option value="ventas" <?= $tipo_reporte === 'ventas' ? 'selected' : '' ?>>Ventas Detalladas</option>
                            <option value="productos" <?= $tipo_reporte === 'productos' ? 'selected' : '' ?>>Productos</option>
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
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Panel de Resultados -->
            <div class="lg:col-span-3 bg-gray-800 rounded-lg border border-gray-700 p-6">
                <h3 class="text-xl font-bold text-white mb-4">Resultados</h3>
                <div class="overflow-x-auto max-h-96 overflow-y-auto">
                    <table class="w-full">
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
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script>
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
