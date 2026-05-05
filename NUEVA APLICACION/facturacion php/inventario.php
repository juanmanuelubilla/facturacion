<?php
require_once 'config.php';
requireLogin();

$user = getUser();
$empresa_id = $user['empresa_id'];

// Config del negocio
$config = fetch("SELECT nombre_negocio, stock_bajo_entero, stock_bajo_fraccion FROM nombre_negocio WHERE empresa_id = ?", [$empresa_id]);
$nombre_negocio = $config['nombre_negocio'] ?? 'NEXUS POS';
$stock_bajo_entero = $config['stock_bajo_entero'] ?? 5;
$stock_bajo_fraccion = $config['stock_bajo_fraccion'] ?? 1.000;

$error = '';
$success = '';

// Crear tabla de inventario si no existe
$db = getDB();
$db->exec("
    CREATE TABLE IF NOT EXISTS inventario (
        id INT AUTO_INCREMENT PRIMARY KEY,
        empresa_id INT NOT NULL,
        producto_id INT NOT NULL,
        stock_actual INT DEFAULT 0,
        stock_minimo INT DEFAULT 0,
        stock_maximo INT DEFAULT 0,
        ubicacion VARCHAR(100),
        ultima_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (empresa_id) REFERENCES empresas(id),
        FOREIGN KEY (producto_id) REFERENCES productos(id),
        UNIQUE KEY (empresa_id, producto_id)
    )
");

// Crear tabla de movimientos de inventario
$db->exec("
    CREATE TABLE IF NOT EXISTS movimientos_inventario (
        id INT AUTO_INCREMENT PRIMARY KEY,
        empresa_id INT NOT NULL,
        producto_id INT NOT NULL,
        tipo ENUM('entrada', 'salida', 'ajuste', 'transferencia') NOT NULL,
        cantidad INT NOT NULL,
        motivo VARCHAR(255),
        usuario_id INT,
        fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (empresa_id) REFERENCES empresas(id),
        FOREIGN KEY (producto_id) REFERENCES productos(id),
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    )
");

// Crear tabla de alertas de stock
$db->exec("
    CREATE TABLE IF NOT EXISTS alertas_stock (
        id INT AUTO_INCREMENT PRIMARY KEY,
        empresa_id INT NOT NULL,
        producto_id INT NOT NULL,
        tipo_alerta ENUM('bajo', 'critico', 'sobre') NOT NULL,
        mensaje TEXT,
        leida BOOLEAN DEFAULT FALSE,
        fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (empresa_id) REFERENCES empresas(id),
        FOREIGN KEY (producto_id) REFERENCES productos(id)
    )
");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    if ($accion === 'actualizar_stock') {
        $producto_id = intval($_POST['producto_id'] ?? 0);
        $stock_actual = intval($_POST['stock_actual'] ?? 0);
        $stock_minimo = intval($_POST['stock_minimo'] ?? 0);
        $stock_maximo = intval($_POST['stock_maximo'] ?? 0);
        $ubicacion = trim($_POST['ubicacion'] ?? '');
        
        if ($producto_id > 0) {
            try {
                $db->beginTransaction();
                
                // Verificar si ya existe registro de inventario
                $existe = fetch("SELECT id FROM inventario WHERE empresa_id = ? AND producto_id = ?", [$empresa_id, $producto_id]);
                
                if ($existe) {
                    $stmt = $db->prepare("UPDATE inventario SET stock_actual = ?, stock_minimo = ?, stock_maximo = ?, ubicacion = ?, ultima_actualizacion = NOW() WHERE empresa_id = ? AND producto_id = ?");
                    $stmt->execute([$stock_actual, $stock_minimo, $stock_maximo, $ubicacion, $empresa_id, $producto_id]);
                } else {
                    $stmt = $db->prepare("INSERT INTO inventario (empresa_id, producto_id, stock_actual, stock_minimo, stock_maximo, ubicacion) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$empresa_id, $producto_id, $stock_actual, $stock_minimo, $stock_maximo, $ubicacion]);
                }
                
                // Verificar alertas de stock
                if ($stock_actual <= 0) {
                    $stmt = $db->prepare("INSERT INTO alertas_stock (empresa_id, producto_id, tipo_alerta, mensaje) VALUES (?, ?, 'critico', 'Stock crítico: sin unidades disponibles')");
                    $stmt->execute([$empresa_id, $producto_id]);
                } elseif ($stock_actual <= $stock_minimo) {
                    $stmt = $db->prepare("INSERT INTO alertas_stock (empresa_id, producto_id, tipo_alerta, mensaje) VALUES (?, ?, 'bajo', 'Stock bajo: por debajo del mínimo')");
                    $stmt->execute([$empresa_id, $producto_id]);
                } elseif ($stock_actual >= $stock_maximo && $stock_maximo > 0) {
                    $stmt = $db->prepare("INSERT INTO alertas_stock (empresa_id, producto_id, tipo_alerta, mensaje) VALUES (?, ?, 'sobre', 'Stock sobre el máximo establecido')");
                    $stmt->execute([$empresa_id, $producto_id]);
                }
                
                $db->commit();
                $success = "✅ Stock actualizado correctamente";
            } catch (Exception $e) {
                $db->rollback();
                $error = "❌ Error al actualizar stock: " . $e->getMessage();
            }
        }
    }
    
    if ($accion === 'registrar_movimiento') {
        $producto_id = intval($_POST['producto_id'] ?? 0);
        $tipo = $_POST['tipo'] ?? '';
        $cantidad = intval($_POST['cantidad'] ?? 0);
        $motivo = trim($_POST['motivo'] ?? '');
        
        if ($producto_id > 0 && $cantidad > 0 && in_array($tipo, ['entrada', 'salida', 'ajuste'])) {
            try {
                $db->beginTransaction();
                
                // Registrar movimiento
                $stmt = $db->prepare("INSERT INTO movimientos_inventario (empresa_id, producto_id, tipo, cantidad, motivo, usuario_id) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$empresa_id, $producto_id, $tipo, $cantidad, $motivo, $user['id']]);
                
                // Actualizar stock actual
                $signo = ($tipo === 'entrada') ? '+' : '-';
                $stmt = $db->prepare("UPDATE inventario SET stock_actual = stock_actual $signo ?, ultima_actualizacion = NOW() WHERE empresa_id = ? AND producto_id = ?");
                $stmt->execute([$cantidad, $empresa_id, $producto_id]);
                
                $db->commit();
                $success = "✅ Movimiento registrado correctamente";
            } catch (Exception $e) {
                $db->rollback();
                $error = "❌ Error al registrar movimiento: " . $e->getMessage();
            }
        }
    }
    
    if ($accion === 'marcar_alerta_leida') {
        $alerta_id = intval($_POST['alerta_id'] ?? 0);
        if ($alerta_id > 0) {
            $stmt = $db->prepare("UPDATE alertas_stock SET leida = TRUE WHERE id = ? AND empresa_id = ?");
            $stmt->execute([$alerta_id, $empresa_id]);
        }
    }
}

// Obtener productos con inventario
$orden = $_GET['orden'] ?? 'nombre';
$orden_dir = $_GET['dir'] ?? 'ASC';

$ordenes_validos = ['nombre', 'stock', 'precio', 'codigo'];
if (!in_array($orden, $ordenes_validos)) {
    $orden = 'nombre';
}

if ($orden_dir !== 'ASC' && $orden_dir !== 'DESC') {
    $orden_dir = 'ASC';
}

$productos_inventario = fetchAll("
    SELECT p.*, i.stock_minimo, i.stock_maximo, i.ubicacion, i.ultima_actualizacion
    FROM productos p
    LEFT JOIN inventario i ON p.id = i.producto_id AND i.empresa_id = ?
    WHERE p.empresa_id = ? AND p.activo = 1
    ORDER BY $orden $orden_dir
", [$empresa_id, $empresa_id]);

// Obtener alertas de stock no leídas
$alertas_stock = fetchAll("
    SELECT a.*, p.nombre as producto_nombre
    FROM alertas_stock a
    JOIN productos p ON a.producto_id = p.id
    WHERE a.empresa_id = ? AND a.leida = FALSE
    ORDER BY a.fecha DESC
    LIMIT 10
", [$empresa_id]);

// Obtener movimientos recientes
$movimientos = fetchAll("
    SELECT m.*, p.nombre as producto_nombre, u.nombre as usuario_nombre
    FROM movimientos_inventario m
    JOIN productos p ON m.producto_id = p.id
    LEFT JOIN usuarios u ON m.usuario_id = u.id
    WHERE m.empresa_id = ?
    ORDER BY m.fecha DESC
    LIMIT 20
", [$empresa_id]);

// Estadísticas
$stats = [
    'total_productos' => fetch("SELECT COUNT(*) as total FROM productos WHERE empresa_id = ? AND activo = 1", [$empresa_id])['total'],
    'con_inventario' => fetch("SELECT COUNT(*) as total FROM inventario WHERE empresa_id = ?", [$empresa_id])['total'],
    'stock_bajo' => fetch("SELECT COUNT(*) as total FROM inventario WHERE empresa_id = ? AND stock_actual <= stock_minimo", [$empresa_id])['total'],
    'alertas_pendientes' => fetch("SELECT COUNT(*) as total FROM alertas_stock WHERE empresa_id = ? AND leida = FALSE", [$empresa_id])['total']
];

// Función para formatear stock según si permite fracciones
function formatearStock($stock, $permite_fracciones, $venta_por_peso) {
    if ($venta_por_peso || $permite_fracciones) {
        // Si vende por peso o permite fracciones, mostrar con decimales
        return number_format($stock, 3, ',', '.');
    } else {
        // Si es entero, mostrar sin decimales
        return number_format($stock, 0, ',', '.');
    }
}

// Función para obtener el stock mínimo según el tipo de producto
function obtenerStockMinimo($producto, $stock_bajo_entero, $stock_bajo_fraccion) {
    // Si el producto tiene un stock mínimo configurado individualmente, usar ese
    if ($producto['stock_minimo'] !== null && $producto['stock_minimo'] > 0) {
        return $producto['stock_minimo'];
    }
    
    // Si no, usar la configuración global según el tipo de producto
    if ($producto['venta_por_peso'] || $producto['permite_fracciones']) {
        return $stock_bajo_fraccion;
    } else {
        return $stock_bajo_entero;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario - NEXUS POS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-gray-900 to-gray-800 text-gray-100">
    <header class="bg-gray-900 shadow-lg">
        <div class="container mx-auto px-6 py-4 flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-purple-400"><?= strtoupper($nombre_negocio) ?></h1>
                <p class="text-sm text-gray-400"><?= strtoupper($user['nombre']) ?> | ID: <?= $empresa_id ?> | <?= strtoupper($user['rol']) ?></p>
            </div>
            <a href="dashboard.php" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded">⬅ VOLVER</a>
        </div>
    </header>
    
    <main class="container mx-auto px-6 py-8">
        <h2 class="text-3xl font-bold text-orange-400 mb-2">🗃️ INVENTARIO / STOCK</h2>
        
        <?php if ($error): ?>
            <div class="bg-red-500 text-white p-3 rounded mb-4"><?= $error ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <form method="GET" class="flex items-center justify-between bg-gray-800 p-4 rounded mb-6">
            <input type="text" name="buscar" placeholder="Buscar producto..." class="w-full p-2 pl-10 text-sm text-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-gray-600 focus:border-transparent">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Buscar</button>
        </form>
            <div class="bg-green-500 text-white p-3 rounded mb-4"><?= $success ?></div>
        <?php endif; ?>
        
        <!-- Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-gray-800 p-4 rounded border border-gray-700">
                <p class="text-gray-400 text-sm">Total Productos</p>
                <p class="text-2xl font-bold text-blue-400"><?= $stats['total_productos'] ?></p>
            </div>
            <div class="bg-gray-800 p-4 rounded border border-gray-700">
                <p class="text-gray-400 text-sm">Con Inventario</p>
                <p class="text-2xl font-bold text-green-400"><?= $stats['con_inventario'] ?></p>
            </div>
            <div class="bg-gray-800 p-4 rounded border border-gray-700">
                <p class="text-gray-400 text-sm">Stock Bajo</p>
                <p class="text-2xl font-bold text-yellow-400"><?= $stats['stock_bajo'] ?></p>
            </div>
            <div class="bg-gray-800 p-4 rounded border border-gray-700">
                <p class="text-gray-400 text-sm">Alertas Pendientes</p>
                <p class="text-2xl font-bold text-red-400"><?= $stats['alertas_pendientes'] ?></p>
            </div>
        </div>
        
        <!-- Alertas de Stock -->
        <?php if (!empty($alertas_stock)): ?>
        <div class="bg-gray-800 rounded-lg border border-gray-700 p-4 mb-6">
            <h3 class="text-lg font-bold text-white mb-3">⚠️ Alertas de Stock</h3>
            <div class="space-y-2">
                <?php foreach ($alertas_stock as $alerta): ?>
                    <form method="POST" class="flex items-center justify-between bg-gray-700 p-3 rounded">
                        <input type="hidden" name="accion" value="marcar_alerta_leida">
                        <input type="hidden" name="alerta_id" value="<?= $alerta['id'] ?>">
                        <div>
                            <span class="font-bold text-white"><?= htmlspecialchars($alerta['producto_nombre']) ?></span>
                            <span class="text-sm text-gray-400 ml-2"><?= htmlspecialchars($alerta['mensaje']) ?></span>
                        </div>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm">✓ Leída</button>
                    </form>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Tabla de Inventario -->
        <div class="bg-gray-800 rounded-lg border border-gray-700 p-4 mb-6">
            <h3 class="text-lg font-bold text-white mb-3">📋 Inventario de Productos</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-400 border-b border-gray-700">
                            <th class="pb-2">Imagen</th>
                            <th class="pb-2 cursor-pointer hover:text-white" onclick="ordenar('nombre')">Producto <?= $orden === 'nombre' ? ($orden_dir === 'ASC' ? '↑' : '↓') : '' ?></th>
                            <th class="pb-2 cursor-pointer hover:text-white" onclick="ordenar('codigo')">Código <?= $orden === 'codigo' ? ($orden_dir === 'ASC' ? '↑' : '↓') : '' ?></th>
                            <th class="pb-2 cursor-pointer hover:text-white" onclick="ordenar('stock')">Stock Actual <?= $orden === 'stock' ? ($orden_dir === 'ASC' ? '↑' : '↓') : '' ?></th>
                            <th class="pb-2 cursor-pointer hover:text-white" onclick="ordenar('stock_minimo')">Stock Mínimo <?= $orden === 'stock_minimo' ? ($orden_dir === 'ASC' ? '↑' : '↓') : '' ?></th>
                            <th class="pb-2 cursor-pointer hover:text-white" onclick="ordenar('stock_maximo')">Stock Máximo <?= $orden === 'stock_maximo' ? ($orden_dir === 'ASC' ? '↑' : '↓') : '' ?></th>
                            <th class="pb-2">Ubicación</th>
                            <th class="pb-2">Estado</th>
                            <th class="pb-2">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($productos_inventario as $producto): ?>
                            <tr class="border-b border-gray-700">
                                <td class="py-2">
                                    <?php if ($producto['imagen']): ?>
                                        <img src="<?= htmlspecialchars($producto['imagen']) ?>" alt="<?= htmlspecialchars($producto['nombre']) ?>" 
                                             class="w-12 h-12 object-cover rounded border border-gray-600">
                                    <?php else: ?>
                                        <div class="w-12 h-12 bg-gray-700 rounded border border-gray-600 flex items-center justify-center text-gray-500">
                                            📦
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="py-2 font-bold text-white"><?= htmlspecialchars($producto['nombre']) ?></td>
                                <td class="py-2 text-gray-400"><?= htmlspecialchars($producto['codigo'] ?? '-') ?></td>
                                <td class="py-2 font-bold <?= $producto['stock'] <= obtenerStockMinimo($producto, $stock_bajo_entero, $stock_bajo_fraccion) ? 'text-red-400' : 'text-green-400' ?>">
                                    <?= formatearStock($producto['stock'] ?? 0, $producto['permite_fracciones'] ?? 0, $producto['venta_por_peso'] ?? 0) ?>
                                </td>
                                <td class="py-2"><?= $producto['stock_minimo'] !== null ? formatearStock($producto['stock_minimo'], $producto['permite_fracciones'] ?? 0, $producto['venta_por_peso'] ?? 0) : formatearStock(obtenerStockMinimo($producto, $stock_bajo_entero, $stock_bajo_fraccion), $producto['permite_fracciones'] ?? 0, $producto['venta_por_peso'] ?? 0) ?></td>
                                <td class="py-2"><?= $producto['stock_maximo'] !== null ? formatearStock($producto['stock_maximo'], $producto['permite_fracciones'] ?? 0, $producto['venta_por_peso'] ?? 0) : '-' ?></td>
                                <td class="py-2"><?= htmlspecialchars($producto['ubicacion'] ?? '-') ?></td>
                                <td class="py-2">
                                    <?php if ($producto['stock'] <= 0): ?>
                                        <span class="bg-red-600 text-white px-2 py-1 rounded text-xs">CRÍTICO</span>
                                    <?php elseif ($producto['stock'] <= obtenerStockMinimo($producto, $stock_bajo_entero, $stock_bajo_fraccion)): ?>
                                        <span class="bg-yellow-600 text-white px-2 py-1 rounded text-xs">BAJO</span>
                                    <?php else: ?>
                                        <span class="bg-green-600 text-white px-2 py-1 rounded text-xs">OK</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-2">
                                    <a href="productos.php?edit=<?= $producto['id'] ?>" 
                                            class="bg-blue-600 hover:bg-blue-700 text-white px-2 py-1 rounded text-xs inline-block">Editar Producto</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Movimientos Recientes -->
        <div class="bg-gray-800 rounded-lg border border-gray-700 p-4">
            <h3 class="text-lg font-bold text-white mb-3">📊 Movimientos Recientes</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-400 border-b border-gray-700">
                            <th class="pb-2">Fecha</th>
                            <th class="pb-2">Producto</th>
                            <th class="pb-2">Tipo</th>
                            <th class="pb-2">Cantidad</th>
                            <th class="pb-2">Motivo</th>
                            <th class="pb-2">Usuario</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($movimientos as $movimiento): ?>
                            <tr class="border-b border-gray-700">
                                <td class="py-2"><?= date('d/m/Y H:i', strtotime($movimiento['fecha'])) ?></td>
                                <td class="py-2"><?= htmlspecialchars($movimiento['producto_nombre']) ?></td>
                                <td class="py-2">
                                    <?php if ($movimiento['tipo'] === 'entrada'): ?>
                                        <span class="text-green-400">➕ Entrada</span>
                                    <?php elseif ($movimiento['tipo'] === 'salida'): ?>
                                        <span class="text-red-400">➖ Salida</span>
                                    <?php else: ?>
                                        <span class="text-yellow-400">🔄 Ajuste</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-2 font-bold"><?= $movimiento['cantidad'] ?></td>
                                <td class="py-2"><?= htmlspecialchars($movimiento['motivo'] ?? '-') ?></td>
                                <td class="py-2"><?= htmlspecialchars($movimiento['usuario_nombre'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        function ordenar(columna) {
            const urlParams = new URLSearchParams(window.location.search);
            const ordenActual = urlParams.get('orden');
            const dirActual = urlParams.get('dir') || 'ASC';

            if (ordenActual === columna) {
                // Si es la misma columna, invertir dirección
                urlParams.set('dir', dirActual === 'ASC' ? 'DESC' : 'ASC');
            } else {
                urlParams.set('orden', columna);
                urlParams.set('dir', 'ASC');
            }

            window.location.search = urlParams.toString();
        }
    </script>
</body>
</html>
