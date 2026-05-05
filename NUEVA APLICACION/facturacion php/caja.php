<?php
require_once 'config.php';
requireLogin();

$user = getUser();
$empresa_id = $user['empresa_id'];

// Config del negocio
$config = fetch("SELECT nombre_negocio FROM nombre_negocio WHERE empresa_id = ?", [$empresa_id]);
$nombre_negocio = $config['nombre_negocio'] ?? 'WARP POS';

$error = '';
$success = '';

// Crear tabla de cajas si no existe
$db = getDB();
$db->exec("
    CREATE TABLE IF NOT EXISTS cajas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        empresa_id INT NOT NULL,
        usuario_id INT NOT NULL,
        numero_caja INT NOT NULL,
        fecha_apertura TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        fecha_cierre TIMESTAMP NULL,
        monto_apertura DECIMAL(10,2) DEFAULT 0,
        monto_cierre DECIMAL(10,2) DEFAULT 0,
        monto_esperado DECIMAL(10,2) DEFAULT 0,
        diferencia DECIMAL(10,2) DEFAULT 0,
        estado ENUM('abierta', 'cerrada') DEFAULT 'abierta',
        observaciones TEXT,
        FOREIGN KEY (empresa_id) REFERENCES empresas(id),
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    )
");

// Crear tabla de movimientos de caja
$db->exec("
    CREATE TABLE IF NOT EXISTS movimientos_caja (
        id INT AUTO_INCREMENT PRIMARY KEY,
        empresa_id INT NOT NULL,
        caja_id INT NOT NULL,
        tipo ENUM('venta', 'gasto', 'ingreso', 'retiro', 'deposito', 'ajuste') NOT NULL,
        monto DECIMAL(10,2) NOT NULL,
        metodo_pago VARCHAR(50),
        descripcion TEXT,
        venta_id INT NULL,
        usuario_id INT,
        fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (empresa_id) REFERENCES empresas(id),
        FOREIGN KEY (caja_id) REFERENCES cajas(id),
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    )
");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    if ($accion === 'abrir_caja') {
        $monto_apertura = floatval($_POST['monto_apertura'] ?? 0);
        $observaciones = trim($_POST['observaciones'] ?? '');
        
        try {
            $db->beginTransaction();
            
            // Obtener número de caja
            $numero_caja = fetch("SELECT COALESCE(MAX(numero_caja), 0) + 1 as siguiente FROM cajas WHERE empresa_id = ?", [$empresa_id])['siguiente'];
            
            // Abrir caja
            $stmt = $db->prepare("INSERT INTO cajas (empresa_id, usuario_id, numero_caja, monto_apertura, observaciones, estado) VALUES (?, ?, ?, ?, ?, 'abierta')");
            $stmt->execute([$empresa_id, $user['id'], $numero_caja, $monto_apertura, $observaciones]);
            $caja_id = $db->lastInsertId();
            
            // Registrar movimiento de apertura
            $stmt = $db->prepare("INSERT INTO movimientos_caja (empresa_id, caja_id, tipo, monto, descripcion, usuario_id) VALUES (?, ?, 'ingreso', ?, 'Apertura de caja', ?)");
            $stmt->execute([$empresa_id, $caja_id, $monto_apertura, $user['id']]);
            
            $db->commit();
            $success = "✅ Caja #$numero_caja abierta correctamente";
        } catch (Exception $e) {
            $db->rollback();
            $error = "❌ Error al abrir caja: " . $e->getMessage();
        }
    }
    
    if ($accion === 'cerrar_caja') {
        $caja_id = intval($_POST['caja_id'] ?? 0);
        $monto_cierre = floatval($_POST['monto_cierre'] ?? 0);
        $observaciones = trim($_POST['observaciones'] ?? '');
        
        if ($caja_id > 0) {
            try {
                $db->beginTransaction();
                
                // Calcular monto esperado
                $monto_esperado = fetch("SELECT SUM(monto) as total FROM movimientos_caja WHERE caja_id = ? AND tipo IN ('venta', 'ingreso', 'deposito')", [$caja_id])['total'] ?? 0;
                $monto_esperado -= fetch("SELECT SUM(monto) as total FROM movimientos_caja WHERE caja_id = ? AND tipo IN ('gasto', 'retiro', 'ajuste')", [$caja_id])['total'] ?? 0;
                
                $diferencia = $monto_cierre - $monto_esperado;
                
                // Cerrar caja
                $stmt = $db->prepare("UPDATE cajas SET fecha_cierre = NOW(), monto_cierre = ?, monto_esperado = ?, diferencia = ?, observaciones = ?, estado = 'cerrada' WHERE id = ? AND empresa_id = ?");
                $stmt->execute([$monto_cierre, $monto_esperado, $diferencia, $observaciones, $caja_id, $empresa_id]);
                
                $db->commit();
                $success = "✅ Caja cerrada correctamente. Diferencia: $" . number_format($diferencia, 2);
            } catch (Exception $e) {
                $db->rollback();
                $error = "❌ Error al cerrar caja: " . $e->getMessage();
            }
        }
    }
    
    if ($accion === 'registrar_movimiento') {
        $caja_id = intval($_POST['caja_id'] ?? 0);
        $tipo = $_POST['tipo'] ?? '';
        $monto = floatval($_POST['monto'] ?? 0);
        $metodo_pago = trim($_POST['metodo_pago'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        
        if ($caja_id > 0 && $monto > 0 && in_array($tipo, ['gasto', 'ingreso', 'retiro', 'deposito', 'ajuste'])) {
            try {
                $stmt = $db->prepare("INSERT INTO movimientos_caja (empresa_id, caja_id, tipo, monto, metodo_pago, descripcion, usuario_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$empresa_id, $caja_id, $tipo, $monto, $metodo_pago, $descripcion, $user['id']]);
                $success = "✅ Movimiento registrado correctamente";
            } catch (Exception $e) {
                $error = "❌ Error al registrar movimiento: " . $e->getMessage();
            }
        }
    }
}

// Obtener caja abierta actual
$caja_abierta = fetch("SELECT * FROM cajas WHERE empresa_id = ? AND estado = 'abierta' ORDER BY id DESC LIMIT 1", [$empresa_id]);

// Obtener movimientos de la caja actual
$movimientos_caja = [];
if ($caja_abierta) {
    $movimientos_caja = fetchAll("
        SELECT m.*, u.nombre as usuario_nombre
        FROM movimientos_caja m
        LEFT JOIN usuarios u ON m.usuario_id = u.id
        WHERE m.caja_id = ?
        ORDER BY m.fecha DESC
    ", [$caja_abierta['id']]);
}

// Obtener cajas cerradas recientes
$cajas_cerradas = fetchAll("
    SELECT c.*, u.nombre as usuario_nombre
    FROM cajas c
    JOIN usuarios u ON c.usuario_id = u.id
    WHERE c.empresa_id = ? AND c.estado = 'cerrada'
    ORDER BY c.fecha_cierre DESC
    LIMIT 10
", [$empresa_id]);

// Calcular totales de la caja actual
$totales = [
    'ventas' => 0,
    'ingresos' => 0,
    'gastos' => 0,
    'retiros' => 0,
    'total' => 0
];

if ($caja_abierta) {
    foreach ($movimientos_caja as $movimiento) {
        switch ($movimiento['tipo']) {
            case 'venta':
            case 'ingreso':
            case 'deposito':
                $totales['ventas'] += $movimiento['monto'];
                $totales['ingresos'] += $movimiento['monto'];
                break;
            case 'gasto':
            case 'retiro':
            case 'ajuste':
                $totales['gastos'] += $movimiento['monto'];
                $totales['retiros'] += $movimiento['monto'];
                break;
        }
    }
    $totales['total'] = $totales['ingresos'] - $totales['gastos'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Caja - WARP POS</title>
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
        <h2 class="text-3xl font-bold text-green-400 mb-2">💵 CAJA / DIARIO</h2>
        
        <?php if ($error): ?>
            <div class="bg-red-500 text-white p-3 rounded mb-4"><?= $error ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="bg-green-500 text-white p-3 rounded mb-4"><?= $success ?></div>
        <?php endif; ?>
        
        <!-- Estado de Caja -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <?php if ($caja_abierta): ?>
                <!-- Caja Abierta -->
                <div class="bg-gray-800 rounded-lg border border-green-500 p-6">
                    <h3 class="text-lg font-bold text-white mb-4">🟢 Caja #<?= $caja_abierta['numero_caja'] ?> - Abierta</h3>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-gray-400">Apertura:</span>
                            <span class="text-white"><?= date('d/m/Y H:i', strtotime($caja_abierta['fecha_apertura'])) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Monto Apertura:</span>
                            <span class="text-green-400">$<?= number_format($caja_abierta['monto_apertura'], 2) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Ventas/Ingresos:</span>
                            <span class="text-green-400">$<?= number_format($totales['ingresos'], 2) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Gastos/Retiros:</span>
                            <span class="text-red-400">$<?= number_format($totales['gastos'], 2) ?></span>
                        </div>
                        <div class="flex justify-between border-t border-gray-700 pt-2">
                            <span class="text-gray-400 font-bold">Total Actual:</span>
                            <span class="text-2xl font-bold text-blue-400">$<?= number_format($totales['total'], 2) ?></span>
                        </div>
                    </div>
                    
                    <form method="POST" class="mt-4">
                        <input type="hidden" name="accion" value="cerrar_caja">
                        <input type="hidden" name="caja_id" value="<?= $caja_abierta['id'] ?>">
                        <div class="grid grid-cols-2 gap-2 mb-2">
                            <input type="number" name="monto_cierre" step="0.01" placeholder="Monto Cierre" required
                                   class="bg-gray-700 text-white p-2 rounded border border-gray-600">
                            <input type="text" name="observaciones" placeholder="Observaciones"
                                   class="bg-gray-700 text-white p-2 rounded border border-gray-600">
                        </div>
                        <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-2 rounded">
                            🔒 Cerrar Caja
                        </button>
                    </form>
                </div>
                
                <!-- Registrar Movimiento -->
                <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
                    <h3 class="text-lg font-bold text-white mb-4">➕ Registrar Movimiento</h3>
                    <form method="POST" class="space-y-3">
                        <input type="hidden" name="accion" value="registrar_movimiento">
                        <input type="hidden" name="caja_id" value="<?= $caja_abierta['id'] ?>">
                        
                        <select name="tipo" class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                            <option value="gasto">💸 Gasto</option>
                            <option value="ingreso">💰 Ingreso</option>
                            <option value="retiro">🏧 Retiro</option>
                            <option value="deposito">🏦 Depósito</option>
                            <option value="ajuste">🔄 Ajuste</option>
                        </select>
                        
                        <input type="number" name="monto" step="0.01" placeholder="Monto" required
                               class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                        
                        <input type="text" name="metodo_pago" placeholder="Método de pago (ej: Efectivo, Transferencia)"
                               class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                        
                        <input type="text" name="descripcion" placeholder="Descripción"
                               class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                        
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded">
                            💾 Registrar
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <!-- Abrir Nueva Caja -->
                <div class="bg-gray-800 rounded-lg border border-gray-700 p-6 col-span-2">
                    <h3 class="text-lg font-bold text-white mb-4">🔓 Abrir Nueva Caja</h3>
                    <form method="POST" class="max-w-md">
                        <input type="hidden" name="accion" value="abrir_caja">
                        
                        <div class="mb-4">
                            <label class="block text-gray-400 text-sm mb-2">Monto de Apertura</label>
                            <input type="number" name="monto_apertura" step="0.01" placeholder="0.00" required
                                   class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-gray-400 text-sm mb-2">Observaciones</label>
                            <input type="text" name="observaciones" placeholder="Opcional"
                                   class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                        </div>
                        
                        <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 rounded">
                            🔓 Abrir Caja
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Movimientos de la Caja Actual -->
        <?php if ($caja_abierta && !empty($movimientos_caja)): ?>
        <div class="bg-gray-800 rounded-lg border border-gray-700 p-4 mb-6">
            <h3 class="text-lg font-bold text-white mb-3">📊 Movimientos de la Caja #<?= $caja_abierta['numero_caja'] ?></h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-400 border-b border-gray-700">
                            <th class="pb-2">Fecha</th>
                            <th class="pb-2">Tipo</th>
                            <th class="pb-2">Monto</th>
                            <th class="pb-2">Método</th>
                            <th class="pb-2">Descripción</th>
                            <th class="pb-2">Usuario</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($movimientos_caja as $movimiento): ?>
                            <tr class="border-b border-gray-700">
                                <td class="py-2"><?= date('d/m/Y H:i', strtotime($movimiento['fecha'])) ?></td>
                                <td class="py-2">
                                    <?php if ($movimiento['tipo'] === 'venta'): ?>
                                        <span class="text-green-400">🛒 Venta</span>
                                    <?php elseif ($movimiento['tipo'] === 'ingreso'): ?>
                                        <span class="text-green-400">💰 Ingreso</span>
                                    <?php elseif ($movimiento['tipo'] === 'gasto'): ?>
                                        <span class="text-red-400">💸 Gasto</span>
                                    <?php elseif ($movimiento['tipo'] === 'retiro'): ?>
                                        <span class="text-red-400">🏧 Retiro</span>
                                    <?php elseif ($movimiento['tipo'] === 'deposito'): ?>
                                        <span class="text-blue-400">🏦 Depósito</span>
                                    <?php else: ?>
                                        <span class="text-yellow-400">🔄 Ajuste</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-2 font-bold <?= in_array($movimiento['tipo'], ['venta', 'ingreso', 'deposito']) ? 'text-green-400' : 'text-red-400' ?>">
                                    $<?= number_format($movimiento['monto'], 2) ?>
                                </td>
                                <td class="py-2"><?= htmlspecialchars($movimiento['metodo_pago'] ?? '-') ?></td>
                                <td class="py-2"><?= htmlspecialchars($movimiento['descripcion'] ?? '-') ?></td>
                                <td class="py-2"><?= htmlspecialchars($movimiento['usuario_nombre'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Cajas Cerradas Recientes -->
        <?php if (!empty($cajas_cerradas)): ?>
        <div class="bg-gray-800 rounded-lg border border-gray-700 p-4">
            <h3 class="text-lg font-bold text-white mb-3">📋 Cajas Cerradas Recientes</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-400 border-b border-gray-700">
                            <th class="pb-2">Caja</th>
                            <th class="pb-2">Usuario</th>
                            <th class="pb-2">Apertura</th>
                            <th class="pb-2">Cierre</th>
                            <th class="pb-2">Monto Cierre</th>
                            <th class="pb-2">Esperado</th>
                            <th class="pb-2">Diferencia</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cajas_cerradas as $caja): ?>
                            <tr class="border-b border-gray-700">
                                <td class="py-2 font-bold">#<?= $caja['numero_caja'] ?></td>
                                <td class="py-2"><?= htmlspecialchars($caja['usuario_nombre']) ?></td>
                                <td class="py-2"><?= date('d/m/Y H:i', strtotime($caja['fecha_apertura'])) ?></td>
                                <td class="py-2"><?= date('d/m/Y H:i', strtotime($caja['fecha_cierre'])) ?></td>
                                <td class="py-2">$<?= number_format($caja['monto_cierre'], 2) ?></td>
                                <td class="py-2">$<?= number_format($caja['monto_esperado'], 2) ?></td>
                                <td class="py-2 font-bold <?= $caja['diferencia'] == 0 ? 'text-green-400' : 'text-red-400' ?>">
                                    $<?= number_format($caja['diferencia'], 2) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </main>
</body>
</html>
