<?php
require_once 'config.php';
requireLogin();

$user = getUser();
$empresa_id = $user['empresa_id'];
$usuario_id = $user['id'];

// Verificar si el usuario tiene permisos de cajero
$es_cajero = ($user['rol'] ?? '') === 'cajero';

// Procesar acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    require_once 'lib/PresupuestoService.php';
    $presupuesto_service = new PresupuestoService($empresa_id, $usuario_id);
    
    switch ($accion) {
        case 'crear_presupuesto':
            // Agregar logging para depuración en archivo específico
            $log_file = '/tmp/presupuestos_debug.log';
            $log_content = date('Y-m-d H:i:s') . " === INICIO CREAR PRESUPUESTO ===\n";
            $log_content .= "POST data: " . print_r($_POST, true) . "\n";
            
            // Validar datos requeridos
            if (empty($_POST['cliente_id'])) {
                $log_content .= "ERROR: No se recibió cliente_id\n";
                file_put_contents($log_file, $log_content, FILE_APPEND);
                echo json_encode(['success' => false, 'error' => 'Debe seleccionar un cliente']);
                exit;
            }
            
            // Procesar productos del formulario
            $datos_procesados = $_POST;
            $datos_procesados['detalles'] = [];
            
            // Extraer productos del formulario
            if (!empty($_POST['productos'])) {
                $log_content .= "Productos encontrados: " . count($_POST['productos']) . "\n";
                foreach ($_POST['productos'] as $key => $producto) {
                    $datos_procesados['detalles'][] = [
                        'producto_id' => $producto['producto_id'] ?? null,
                        'producto_nombre' => $producto['producto_nombre'] ?? ($producto['producto_id'] ? 'Producto ID ' . $producto['producto_id'] : 'Producto Manual'),
                        'cantidad' => $producto['cantidad'] ?? 1,
                        'precio_unitario' => $producto['precio_unitario'] ?? 0,
                        'iva_porcentaje' => 0, // Ya está incluido en el precio
                        'descripcion' => $producto['descripcion'] ?? ''
                    ];
                }
            } else {
                $log_content .= "ERROR: No se encontraron productos en el formulario\n";
                file_put_contents($log_file, $log_content, FILE_APPEND);
                echo json_encode(['success' => false, 'error' => 'Debe agregar al menos un producto']);
                exit;
            }
            
            $log_content .= "Datos procesados: " . print_r($datos_procesados, true) . "\n";
            file_put_contents($log_file, $log_content, FILE_APPEND);
            
            $resultado = $presupuesto_service->crearPresupuesto($datos_procesados);
            $log_content .= "Resultado: " . print_r($resultado, true) . "\n";
            $log_content .= "=== FIN CREAR PRESUPUESTO ===\n\n";
            file_put_contents($log_file, $log_content, FILE_APPEND);
            
            echo json_encode($resultado);
            exit;
            
        case 'actualizar_estado':
            $resultado = $presupuesto_service->actualizarEstado(
                $_POST['presupuesto_id'], 
                $_POST['estado'], 
                $_POST['observaciones'] ?? ''
            );
            echo json_encode($resultado);
            exit;
            
        case 'eliminar_presupuesto':
            $resultado = $presupuesto_service->eliminarPresupuesto($_POST['presupuesto_id']);
            echo json_encode($resultado);
            exit;
            
        case 'convertir_en_venta':
            $resultado = $presupuesto_service->convertirEnVenta($_POST['presupuesto_id']);
            echo json_encode($resultado);
            exit;
            
        case 'generar_pdf':
            $resultado = $presupuesto_service->generarPDF($_POST['presupuesto_id']);
            if ($resultado['success']) {
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . basename($resultado['archivo']) . '"');
                readfile($resultado['archivo']);
                unlink($resultado['archivo']); // Eliminar archivo temporal
            } else {
                echo json_encode($resultado);
            }
            exit;
    }
}

// Obtener lista de presupuestos
require_once 'lib/PresupuestoService.php';
$presupuesto_service = new PresupuestoService($empresa_id, $usuario_id);

$filtros = [
    'estado' => $_GET['estado'] ?? '',
    'cliente_id' => $_GET['cliente_id'] ?? '',
    'fecha_desde' => $_GET['fecha_desde'] ?? '',
    'fecha_hasta' => $_GET['fecha_hasta'] ?? ''
];

$presupuestos = $presupuesto_service->obtenerPresupuestos($filtros);

// Obtener clientes para el selector
$clientes = fetchAll("SELECT id, nombre, apellido FROM clientes WHERE empresa_id = ? ORDER BY nombre, apellido", [$empresa_id]);

// Obtener productos para el selector (solo activos con stock)
$productos = fetchAll("SELECT id, nombre, descripcion, precio, codigo_barra, stock, permite_fracciones FROM productos WHERE empresa_id = ? AND activo = 1 AND stock > 0 ORDER BY nombre", [$empresa_id]);

// Obtener promociones de volumen
$promociones_volumen = fetchAll("SELECT pv.producto_id, pv.cantidad_minima, pv.descuento_porcentaje, p.nombre, p.descripcion, p.precio, p.codigo_barra, p.permite_fracciones
                                   FROM promociones_volumen pv 
                                   JOIN productos p ON pv.producto_id = p.id 
                                   WHERE pv.empresa_id = ? AND pv.activo = 1 AND p.activo = 1 AND p.stock > 0 
                                   ORDER BY p.nombre", [$empresa_id]);

// Obtener promociones de combos
$promociones_combos = fetchAll("SELECT pc.productos_ids, pc.descuento_porcentaje 
                                 FROM promociones_combos pc 
                                 WHERE pc.empresa_id = ? AND pc.activo = 1", [$empresa_id]);

// Obtener presupuesto específico si se solicita
$presupuesto_detalle = null;
if (isset($_GET['id'])) {
    $presupuesto_detalle = $presupuesto_service->obtenerPresupuestoCompleto($_GET['id']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Presupuestos - NEXUS POS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="min-h-screen bg-gradient-to-br from-gray-900 to-gray-800 text-gray-100">
    <header class="bg-gray-900 shadow-lg">
        <div class="container mx-auto px-6 py-4 flex justify-between items-center">
            <h1 class="text-2xl font-bold text-blue-400">📋 PRESUPUESTOS</h1>
            <a href="dashboard.php" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded">VOLVER</a>
        </div>
    </header>
    <main class="container mx-auto px-6 py-8">
        <?php if (!$presupuesto_detalle): ?>
        <!-- Vista principal de presupuestos -->
        <div class="bg-gray-800 rounded-lg border border-gray-700 p-6 mb-6">
            <h2 class="text-xl font-bold text-white mb-4">Nuevo Presupuesto</h2>
            <form id="formPresupuesto" class="space-y-4">
                <input type="hidden" name="accion" value="crear_presupuesto">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Cliente</label>
                        <select name="cliente_id" id="clienteSelect" required class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                            <option value="">Seleccionar cliente...</option>
                            <?php foreach ($clientes as $cliente): ?>
                                <option value="<?= $cliente['id'] ?>"><?= htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellido']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Título</label>
                        <input type="text" name="titulo" required placeholder="Ej: Presupuesto para remodelación" 
                               class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                    </div>
                </div>
                
                <div>
                    <label class="block text-gray-400 text-sm mb-2">Descripción</label>
                    <textarea name="descripcion" rows="3" placeholder="Descripción detallada del presupuesto..." 
                              class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600"></textarea>
                </div>
                
                <div>
                    <label class="block text-gray-400 text-sm mb-2">Validez (días)</label>
                    <input type="number" name="validez_dias" value="30" min="1" max="365" 
                           class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                </div>
                
                <div>
                    <label class="block text-gray-400 text-sm mb-2">Productos del Presupuesto</label>
                    <div class="mb-4">
                        <div class="flex gap-2 mb-2">
                            <input type="text" id="buscadorProducto" placeholder="Buscar producto o promoción por nombre o código..." 
                                   class="flex-1 bg-gray-700 text-white p-3 rounded border border-gray-600"
                                   onkeyup="buscarProducto()">
                            <button type="button" onclick="limpiarBusqueda()" 
                                    class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-3 rounded">
                                🧹 Limpiar
                            </button>
                        </div>
                        <div id="sugerenciasProductos" class="max-h-48 overflow-y-auto mb-2 space-y-1 hidden"></div>
                    </div>
                    
                    <div id="productosContainer" class="space-y-3 mb-4">
                        <!-- Los productos se agregarán aquí dinámicamente -->
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Subtotal</label>
                        <input type="number" id="subtotal" step="0.01" readonly 
                               class="w-full bg-gray-600 text-white p-3 rounded border border-gray-600">
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Total</label>
                        <input type="number" id="total" step="0.01" readonly 
                               class="w-full bg-gray-600 text-white p-3 rounded border border-gray-600">
                    </div>
                </div>
                
                <div>
                    <label class="block text-gray-400 text-sm mb-2">Observaciones</label>
                    <textarea name="observaciones" rows="2" placeholder="Observaciones adicionales..." 
                              class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600"></textarea>
                </div>
                
                <button type="submit" form="formPresupuesto" 
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 rounded font-bold">
                    💾 Crear Presupuesto
                </button>
            </form>
        </div>
        
        <!-- Filtros -->
        <div class="bg-gray-800 rounded-lg border border-gray-700 p-6 mb-6">
            <h3 class="text-lg font-bold text-white mb-4">Filtros</h3>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-gray-400 text-sm mb-2">Estado</label>
                    <select onchange="filtrarPresupuestos()" id="filtroEstado" class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                        <option value="">Todos</option>
                        <option value="pendiente">Pendientes</option>
                        <option value="aprobado">Aprobados</option>
                        <option value="rechazado">Rechazados</option>
                        <option value="vencido">Vencidos</option>
                        <option value="convertido">Convertidos</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-gray-400 text-sm mb-2">Cliente</label>
                    <select onchange="filtrarPresupuestos()" id="filtroCliente" class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                        <option value="">Todos</option>
                        <?php foreach ($clientes as $cliente): ?>
                            <option value="<?= $cliente['id'] ?>"><?= htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellido']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-gray-400 text-sm mb-2">Desde</label>
                    <input type="date" onchange="filtrarPresupuestos()" id="filtroDesde" 
                           class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                </div>
                
                <div>
                    <label class="block text-gray-400 text-sm mb-2">Hasta</label>
                    <input type="date" onchange="filtrarPresupuestos()" id="filtroHasta" 
                           class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                </div>
            </div>
        </div>
        
        <!-- Lista de presupuestos -->
        <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
            <h3 class="text-lg font-bold text-white mb-4">Lista de Presupuestos</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-white">
                    <thead class="bg-gray-700">
                        <tr>
                            <th class="p-3 text-left">N°</th>
                            <th class="p-3 text-left">Cliente</th>
                            <th class="p-3 text-left">Título</th>
                            <th class="p-3 text-left">Total</th>
                            <th class="p-3 text-left">Estado</th>
                            <th class="p-3 text-left">Vencimiento</th>
                            <th class="p-3 text-left">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tablaPresupuestos">
                        <?php foreach ($presupuestos as $presupuesto): ?>
                        <tr class="border-b border-gray-700 hover:bg-gray-700">
                            <td class="p-3"><?= htmlspecialchars($presupuesto['numero_presupuesto']) ?></td>
                            <td class="p-3"><?= htmlspecialchars($presupuesto['cliente_nombre'] . ' ' . $presupuesto['cliente_apellido']) ?></td>
                            <td class="p-3"><?= htmlspecialchars($presupuesto['titulo']) ?></td>
                            <td class="p-3">$<?= number_format($presupuesto['total'], 2) ?></td>
                            <td class="p-3">
                                <span class="px-2 py-1 rounded text-xs font-bold
                                    <?php
                                    switch ($presupuesto['estado']) {
                                        case 'pendiente': echo 'bg-yellow-600'; break;
                                        case 'aprobado': echo 'bg-green-600'; break;
                                        case 'rechazado': echo 'bg-red-600'; break;
                                        case 'vencido': echo 'bg-orange-600'; break;
                                        case 'convertido': echo 'bg-blue-600'; break;
                                        default: echo 'bg-gray-600'; break;
                                    }
                                    ?>">
                                    <?= ucfirst($presupuesto['estado']) ?>
                                </span>
                            </td>
                            <td class="p-3"><?= $presupuesto['fecha_vencimiento'] ? date('d/m/Y', strtotime($presupuesto['fecha_vencimiento'])) : 'N/A' ?></td>
                            <td class="p-3">
                                <div class="flex gap-2">
                                    <button onclick="verPresupuesto(<?= $presupuesto['id'] ?>)" 
                                            class="bg-blue-600 hover:bg-blue-700 text-white px-2 py-1 rounded text-sm">
                                        👁️
                                    </button>
                                    
                                    <?php if ($es_cajero): ?>
                                        <button onclick="imprimirPresupuesto(<?= $presupuesto['id'] ?>)" 
                                                class="bg-green-600 hover:bg-green-700 text-white px-2 py-1 rounded text-sm">
                                            🖨️
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($presupuesto['estado'] === 'pendiente'): ?>
                                        <button onclick="actualizarEstado(<?= $presupuesto['id'] ?>, 'aprobado')" 
                                                class="bg-green-600 hover:bg-green-700 text-white px-2 py-1 rounded text-sm">
                                            ✅
                                        </button>
                                        
                                        <button onclick="actualizarEstado(<?= $presupuesto['id'] ?>, 'rechazado')" 
                                                class="bg-red-600 hover:bg-red-700 text-white px-2 py-1 rounded text-sm">
                                            ❌
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($presupuesto['estado'] === 'aprobado'): ?>
                                        <button onclick="convertirEnVenta(<?= $presupuesto['id'] ?>)" 
                                                class="bg-purple-600 hover:bg-purple-700 text-white px-2 py-1 rounded text-sm">
                                            🛒
                                        </button>
                                    <?php endif; ?>
                                    
                                    <button onclick="eliminarPresupuesto(<?= $presupuesto['id'] ?>)" 
                                            class="bg-red-600 hover:bg-red-700 text-white px-2 py-1 rounded text-sm"
                                            onclick="return confirm('¿Eliminar este presupuesto?')">
                                        🗑️
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <?php else: ?>
        <!-- Vista detallada del presupuesto -->
        <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
            <div class="flex justify-between items-start mb-6">
                <div>
                    <h2 class="text-xl font-bold text-white">Presupuesto N°: <?= htmlspecialchars($presupuesto_detalle['numero_presupuesto']) ?></h2>
                    <p class="text-gray-400">Fecha: <?= date('d/m/Y H:i', strtotime($presupuesto_detalle['fecha_creacion'])) ?></p>
                    <p class="text-gray-400">Válido por: <?= $presupuesto_detalle['validez_dias'] ?> días</p>
                </div>
                <button onclick="window.location.href='presupuestos.php'" 
                        class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded">
                    🏠 Volver
                </button>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h3 class="text-lg font-bold text-white mb-4">Datos del Cliente</h3>
                    <div class="space-y-2">
                        <p><strong>Nombre:</strong> <?= htmlspecialchars($presupuesto_detalle['cliente_nombre'] . ' ' . $presupuesto_detalle['cliente_apellido']) ?></p>
                        <p><strong>Teléfono:</strong> <?= htmlspecialchars($presupuesto_detalle['cliente_telefono']) ?></p>
                        <p><strong>Email:</strong> <?= htmlspecialchars($presupuesto_detalle['cliente_email']) ?></p>
                    </div>
                    
                    <h3 class="text-lg font-bold text-white mb-4 mt-6">Descripción</h3>
                    <p class="text-gray-300"><?= nl2br(htmlspecialchars($presupuesto_detalle['descripcion'])) ?></p>
                    
                    <h3 class="text-lg font-bold text-white mb-4 mt-6">Observaciones</h3>
                    <p class="text-gray-300"><?= nl2br(htmlspecialchars($presupuesto_detalle['observaciones'])) ?></p>
                </div>
                
                <div>
                    <h3 class="text-lg font-bold text-white mb-4">Detalles del Presupuesto</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-white">
                            <thead class="bg-gray-700">
                                <tr>
                                    <th class="p-3 text-left">Producto</th>
                                    <th class="p-3 text-left">Cantidad</th>
                                    <th class="p-3 text-left">Precio Unitario</th>
                                    <th class="p-3 text-left">Subtotal</th>
                                    <th class="p-3 text-left">IVA</th>
                                    <th class="p-3 text-left">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($presupuesto_detalle['detalles'] as $detalle): ?>
                                <tr class="border-b border-gray-700">
                                    <td class="p-3"><?= htmlspecialchars($detalle['producto_nombre']) ?></td>
                                    <td class="p-3"><?= number_format($detalle['cantidad'], 2) ?></td>
                                    <td class="p-3">$<?= number_format($detalle['precio_unitario'], 2) ?></td>
                                    <td class="p-3">$<?= number_format($detalle['subtotal'], 2) ?></td>
                                    <td class="p-3"><?= number_format($detalle['iva_porcentaje'], 2) ?>%</td>
                                    <td class="p-3">$<?= number_format($detalle['total'], 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="bg-gray-700">
                                <tr>
                                    <th colspan="3" class="p-3 text-right">Subtotal:</th>
                                    <td class="p-3 text-right">$<?= number_format($presupuesto_detalle['subtotal'], 2) ?></td>
                                    <td class="p-3"></td>
                                    <td class="p-3"></td>
                                </tr>
                                <tr>
                                    <th colspan="3" class="p-3 text-right">IVA (<?= $presupuesto_detalle['iva_porcentaje'] ?>%):</th>
                                    <td class="p-3 text-right">$<?= number_format($presupuesto_detalle['iva_total'], 2) ?></td>
                                    <td class="p-3"></td>
                                    <td class="p-3"></td>
                                </tr>
                                <tr>
                                    <th colspan="3" class="p-3 text-right">TOTAL:</th>
                                    <td class="p-3 text-right text-lg font-bold">$<?= number_format($presupuesto_detalle['total'], 2) ?></td>
                                    <td class="p-3"></td>
                                    <td class="p-3"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    
                    <div class="mt-6 space-y-4">
                        <h3 class="text-lg font-bold text-white">Estado y Seguimiento</h3>
                        <div class="flex items-center gap-4 mb-4">
                            <span class="px-3 py-1 rounded font-bold
                                <?php
                                switch ($presupuesto_detalle['estado']) {
                                    case 'pendiente': echo 'bg-yellow-600'; break;
                                    case 'aprobado': echo 'bg-green-600'; break;
                                    case 'rechazado': echo 'bg-red-600'; break;
                                    case 'vencido': echo 'bg-orange-600'; break;
                                    case 'convertido': echo 'bg-blue-600'; break;
                                    default: echo 'bg-gray-600'; break;
                                }
                                ?>">
                                <?= ucfirst($presupuesto_detalle['estado']) ?>
                            </span>
                            
                            <?php if ($es_cajero): ?>
                                <button onclick="imprimirPresupuesto(<?= $presupuesto_detalle['id'] ?>)" 
                                        class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">
                                    🖨️ Imprimir
                                </button>
                            <?php endif; ?>
                            
                            <?php if ($presupuesto_detalle['estado'] === 'aprobado'): ?>
                                <button onclick="enviarPorWhatsApp(<?= $presupuesto_detalle['id'] ?>)" 
                                        class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">
                                    📱 Enviar por WhatsApp
                                </button>
                            <?php endif; ?>
                        </div>
                        
                        <div class="space-y-2">
                            <h4 class="text-white font-bold">Historial de Seguimiento</h4>
                            <?php foreach ($presupuesto_detalle['seguimiento'] as $seguimiento): ?>
                                <div class="bg-gray-700 rounded p-3 mb-2">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <span class="font-bold text-blue-400"><?= ucfirst($seguimiento['tipo_accion']) ?></span>
                                            <p class="text-gray-300 text-sm mt-1"><?= htmlspecialchars($seguimiento['descripcion']) ?></p>
                                        </div>
                                        <span class="text-gray-400 text-xs">
                                            <?= date('d/m/Y H:i', strtotime($seguimiento['fecha_accion'])) ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </main>
    
    <script>
        let contadorProductos = 0;
        const productosDisponibles = <?= json_encode($productos) ?>;
        const promocionesVolumen = <?= json_encode($promociones_volumen ?? []) ?>;
        const promocionesCombos = <?= json_encode($promociones_combos ?? []) ?>;
        
        // Función de búsqueda de productos con autocompletar (incluye promociones)
        function buscarProducto() {
            const termino = document.getElementById('buscadorProducto').value.trim().toLowerCase();
            const sugerenciasDiv = document.getElementById('sugerenciasProductos');
            
            if (termino.length < 2) {
                sugerenciasDiv.classList.add('hidden');
                return;
            }
            
            // Buscar productos normales
            const productosFiltrados = productosDisponibles.filter(p => 
                p.nombre.toLowerCase().includes(termino) || 
                (p.codigo_barra && p.codigo_barra.toLowerCase().includes(termino))
            );
            
            // Buscar promociones de volumen
            const promocionesFiltradas = promocionesVolumen.filter(p => 
                p.nombre.toLowerCase().includes(termino) || 
                (p.codigo_barra && p.codigo_barra.toLowerCase().includes(termino))
            );
            
            let html = '';
            
            // Mostrar productos normales
            if (productosFiltrados.length > 0) {
                html += '<div class="text-xs text-gray-400 mb-1 font-bold">PRODUCTOS</div>';
                productosFiltrados.forEach(p => {
                    html += `
                        <div class="bg-gray-700 p-2 rounded cursor-pointer hover:bg-gray-600 mb-1" 
                             onclick="seleccionarProducto(${p.id}, '${p.nombre.replace(/'/g, "\\'")}', '${(p.descripcion || '').replace(/'/g, "\\'")}', ${p.precio}, '${(p.codigo_barra || '').replace(/'/g, "\\'")}', 'normal')">
                            <div class="font-bold text-white">${p.nombre}</div>
                            <div class="text-gray-400 text-xs">${p.codigo_barra || 'Sin código'} - Stock: ${p.stock}</div>
                            <div class="text-green-400 text-xs">Precio: $${parseFloat(p.precio).toFixed(2)}</div>
                            <div class="text-gray-500 text-xs">${(p.descripcion || '').substring(0, 50)}${(p.descripcion && p.descripcion.length > 50 ? '...' : '')}</div>
                        </div>
                    `;
                });
            }
            
            // Mostrar promociones de volumen
            if (promocionesFiltradas.length > 0) {
                html += '<div class="text-xs text-yellow-400 mb-1 font-bold mt-2">PROMOCIONES POR VOLUMEN</div>';
                promocionesFiltradas.forEach(p => {
                    const precioConDescuento = p.precio * (1 - p.descuento_porcentaje / 100);
                    html += `
                        <div class="bg-yellow-900 p-2 rounded cursor-pointer hover:bg-yellow-800 mb-1 border border-yellow-700" 
                             onclick="seleccionarPromocionVolumen(${p.producto_id}, '${p.nombre.replace(/'/g, "\\'")}', '${(p.descripcion || '').replace(/'/g, "\\'")}', ${precioConDescuento}, ${p.descuento_porcentaje}, ${p.cantidad_minima}, '${(p.codigo_barra || '').replace(/'/g, "\\'")}')">
                            <div class="font-bold text-yellow-300">${p.nombre}</div>
                            <div class="text-yellow-400 text-xs">${p.codigo_barra || 'Sin código'} - Stock: ${p.stock}</div>
                            <div class="text-yellow-200 text-xs">Desde ${p.cantidad_minima} uds: ${p.descuento_porcentaje}% OFF</div>
                            <div class="text-green-400 text-xs">Precio: $${parseFloat(precioConDescuento).toFixed(2)}</div>
                            <div class="text-gray-500 text-xs">Base: $${parseFloat(p.precio).toFixed(2)} → $${parseFloat(precioConDescuento).toFixed(2)}</div>
                        </div>
                    `;
                });
            }
            
            // Mostrar combos (si existen)
            if (promocionesCombos.length > 0 && termino.includes('combo')) {
                html += '<div class="text-xs text-green-400 mb-1 font-bold mt-2">COMBOS</div>';
                promocionesCombos.forEach(pc => {
                    html += `
                        <div class="bg-green-900 p-2 rounded cursor-pointer hover:bg-green-800 mb-1 border border-green-700" 
                             onclick="alert('Los combos deben configurarse desde el módulo de ventas. Contacte al administrador.')">
                            <div class="font-bold text-green-300">Combo Disponible</div>
                            <div class="text-green-400 text-xs">${pc.descuento_porcentaje}% OFF en productos seleccionados</div>
                        </div>
                    `;
                });
            }
            
            if (html === '') {
                html = '<div class="p-2 text-gray-400 text-sm">No se encontraron productos o promociones</div>';
            }
            
            sugerenciasDiv.innerHTML = html;
            sugerenciasDiv.classList.remove('hidden');
        }
        
        // Función para limpiar búsqueda
        function limpiarBusqueda() {
            document.getElementById('buscadorProducto').value = '';
            document.getElementById('sugerenciasProductos').classList.add('hidden');
        }
        
        // Función para seleccionar producto normal
        function seleccionarProducto(id, nombre, descripcion, precio, codigoBarra, tipo) {
            limpiarBusqueda();
            // Buscar el producto para obtener si permite fracciones
            const producto = productosDisponibles.find(p => p.id == id);
            const permiteFracciones = producto ? producto.permite_fracciones : false;
            agregarProductoSeleccionado(id, nombre, descripcion, precio, codigoBarra, tipo, permiteFracciones);
        }
        
        // Función para seleccionar promoción de volumen
        function seleccionarPromocionVolumen(id, nombre, descripcion, precio, descuento, cantidadMinima, codigoBarra) {
            limpiarBusqueda();
            // Buscar la promoción para obtener si permite fracciones
            const promocion = promocionesVolumen.find(p => p.producto_id == id);
            const permiteFracciones = promocion ? promocion.permite_fracciones : false;
            agregarPromocionVolumenSeleccionada(id, nombre, descripcion, precio, descuento, cantidadMinima, codigoBarra, permiteFracciones);
        }
        
        // Función para agregar producto seleccionado
        function agregarProductoSeleccionado(id, nombre, descripcion, precio, codigoBarra, tipo, permiteFracciones) {
            contadorProductos++;
            const container = document.getElementById('productosContainer');
            
            // Determinar configuración de cantidad según si permite fracciones
            const step = permiteFracciones ? "0.01" : "1";
            const min = permiteFracciones ? "0.01" : "1";
            const placeholder = permiteFracciones ? "Ej: 1.5" : "Ej: 1, 2, 3";
            const labelCantidad = permiteFracciones ? "Cantidad (fracciones)" : "Cantidad (enteros)";
            
            const productoDiv = document.createElement('div');
            productoDiv.className = 'bg-gray-700 p-3 rounded border border-gray-600';
            productoDiv.innerHTML = `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="md:col-span-2">
                        <label class="block text-gray-400 text-xs mb-1">Producto</label>
                        <input type="text" value="${nombre}" readonly 
                               class="w-full bg-gray-600 text-white p-2 rounded text-sm">
                        <input type="hidden" name="productos[${contadorProductos}][producto_id]" value="${id}">
                        <input type="hidden" name="productos[${contadorProductos}][tipo]" value="${tipo}">
                        <input type="hidden" name="productos[${contadorProductos}][permite_fracciones]" value="${permiteFracciones}">
                    </div>
                    <div>
                        <label class="block text-gray-400 text-xs mb-1">${labelCantidad}</label>
                        <input type="number" name="productos[${contadorProductos}][cantidad]" value="1" min="${min}" step="${step}" required 
                               placeholder="${placeholder}"
                               class="w-full bg-gray-600 text-white p-2 rounded text-sm"
                               onchange="calcularTotales()">
                        <div class="text-xs text-gray-500 mt-1">${permiteFracciones ? 'Permite fracciones' : 'Solo números enteros'}</div>
                    </div>
                    <div>
                        <label class="block text-gray-400 text-xs mb-1">Precio Unitario</label>
                        <input type="number" name="productos[${contadorProductos}][precio_unitario]" value="${precio}" step="0.01" required 
                               class="w-full bg-gray-600 text-white p-2 rounded text-sm"
                               onchange="calcularTotales()">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-gray-400 text-xs mb-1">Descripción</label>
                        <textarea name="productos[${contadorProductos}][descripcion]" rows="2" 
                                  placeholder="Descripción del producto..." 
                                  class="w-full bg-gray-600 text-white p-2 rounded text-sm">${descripcion || ''}</textarea>
                    </div>
                </div>
                <div class="flex justify-end mt-2">
                    <button type="button" onclick="this.parentElement.parentElement.remove(); calcularTotales()" 
                            class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-sm">
                        ❌ Eliminar
                    </button>
                </div>
            `;
            
            container.appendChild(productoDiv);
            calcularTotales();
        }
        
        // Función para agregar promoción de volumen
        function agregarPromocionVolumenSeleccionada(id, nombre, descripcion, precio, descuento, cantidadMinima, codigoBarra, permiteFracciones) {
            contadorProductos++;
            const container = document.getElementById('productosContainer');
            const precioConDescuento = precio;
            
            // Determinar configuración de cantidad según si permite fracciones
            const step = permiteFracciones ? "0.01" : "1";
            const min = permiteFracciones ? "0.01" : cantidadMinima;
            const placeholder = permiteFracciones ? "Ej: 1.5" : `Mínimo: ${cantidadMinima}`;
            const labelCantidad = permiteFracciones ? "Cantidad (fracciones)" : "Cantidad (enteros)";
            
            const productoDiv = document.createElement('div');
            productoDiv.className = 'bg-yellow-900 p-3 rounded border border-yellow-700';
            productoDiv.innerHTML = `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="md:col-span-2">
                        <label class="block text-yellow-400 text-xs mb-1">Promoción por Volumen</label>
                        <input type="text" value="${nombre} (${descuento}% OFF desde ${cantidadMinima} uds)" readonly 
                               class="w-full bg-yellow-800 text-yellow-200 p-2 rounded text-sm">
                        <input type="hidden" name="productos[${contadorProductos}][producto_id]" value="${id}">
                        <input type="hidden" name="productos[${contadorProductos}][tipo]" value="promocion_volumen">
                        <input type="hidden" name="productos[${contadorProductos}][descuento_porcentaje]" value="${descuento}">
                        <input type="hidden" name="productos[${contadorProductos}][cantidad_minima]" value="${cantidadMinima}">
                        <input type="hidden" name="productos[${contadorProductos}][permite_fracciones]" value="${permiteFracciones}">
                    </div>
                    <div>
                        <label class="block text-yellow-400 text-xs mb-1">${labelCantidad}</label>
                        <input type="number" name="productos[${contadorProductos}][cantidad]" value="${cantidadMinima}" min="${min}" step="${step}" required 
                               placeholder="${placeholder}"
                               class="w-full bg-yellow-800 text-yellow-200 p-2 rounded text-sm"
                               onchange="calcularTotales()">
                        <div class="text-xs text-yellow-600 mt-1">${permiteFracciones ? 'Permite fracciones' : 'Solo números enteros'}</div>
                    </div>
                    <div>
                        <label class="block text-yellow-400 text-xs mb-1">Precio Unitario</label>
                        <input type="number" name="productos[${contadorProductos}][precio_unitario]" value="${precioConDescuento}" step="0.01" required 
                               class="w-full bg-yellow-800 text-yellow-200 p-2 rounded text-sm"
                               onchange="calcularTotales()">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-yellow-400 text-xs mb-1">Descripción</label>
                        <textarea name="productos[${contadorProductos}][descripcion]" rows="2" 
                                  placeholder="Descripción de la promoción..." 
                                  class="w-full bg-yellow-800 text-yellow-200 p-2 rounded text-sm">Promoción por volumen: ${descuento}% de descuento desde ${cantidadMinima} unidades. ${descripcion || ''}</textarea>
                    </div>
                </div>
                <div class="flex justify-end mt-2">
                    <button type="button" onclick="this.parentElement.parentElement.remove(); calcularTotales()" 
                            class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-sm">
                        ❌ Eliminar
                    </button>
                </div>
            `;
            
            container.appendChild(productoDiv);
            calcularTotales();
        }
        
        function calcularTotales() {
            const productosContainer = document.getElementById('productosContainer');
            const productos = productosContainer.querySelectorAll('div.border');
            let subtotal = 0;
            
            productos.forEach(div => {
                const cantidadInput = div.querySelector('input[name*="cantidad"]');
                const precioInput = div.querySelector('input[name*="precio_unitario"]');
                
                if (cantidadInput && precioInput) {
                    const cantidad = parseFloat(cantidadInput.value) || 0;
                    const precio = parseFloat(precioInput.value) || 0;
                    subtotal += cantidad * precio;
                }
            });
            
            document.getElementById('subtotal').value = subtotal.toFixed(2);
            document.getElementById('total').value = subtotal.toFixed(2);
        }
        
        function verPresupuesto(id) {
            window.location.href = `presupuestos.php?id=${id}`;
        }
        
        function imprimirPresupuesto(id) {
            window.open(`presupuestos.php?accion=imprimir&id=${id}`, '_blank');
        }
        
        function enviarPorWhatsApp(id) {
            if (confirm('¿Enviar este presupuesto por WhatsApp?')) {
                fetch('presupuestos.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `accion=enviar_whatsapp&id=${id}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Presupuesto enviado por WhatsApp correctamente');
                    } else {
                        alert('Error al enviar el presupuesto: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error de conexión: ' + error.message);
                });
            }
        }
        
        function actualizarEstado(id, estado, observaciones = '') {
            if (confirm(`¿Cambiar estado a "${estado}"?`)) {
                fetch('presupuestos.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `accion=actualizar_estado&id=${id}&estado=${estado}&observaciones=${encodeURIComponent(observaciones)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error al actualizar estado: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error de conexión: ' + error.message);
                });
            }
        }
        
        function convertirEnVenta(id) {
            if (confirm('¿Convertir este presupuesto en una venta?')) {
                fetch('presupuestos.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `accion=convertir_venta&id=${id}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Presupuesto convertido en venta correctamente');
                        window.location.href = 'ventas.php';
                    } else {
                        alert('Error al convertir: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error de conexión: ' + error.message);
                });
            }
        }
        
        function eliminarPresupuesto(id) {
            if (confirm('¿Eliminar este presupuesto? Esta acción no se puede deshacer.')) {
                fetch('presupuestos.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `accion=eliminar&id=${id}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error al eliminar: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error de conexión: ' + error.message);
                });
            }
        }
        
        function filtrarPresupuestos() {
            const estado = document.getElementById('filtroEstado').value;
            const clienteId = document.getElementById('filtroCliente').value;
            const fechaDesde = document.getElementById('filtroDesde').value;
            const fechaHasta = document.getElementById('filtroHasta').value;
            
            const params = new URLSearchParams();
            if (estado) params.append('estado', estado);
            if (clienteId) params.append('cliente_id', clienteId);
            if (fechaDesde) params.append('fecha_desde', fechaDesde);
            if (fechaHasta) params.append('fecha_hasta', fechaHasta);
            
            window.location.href = 'presupuestos.php?' + params.toString();
        }
        
        // Event listener para cambios en IVA (eliminado - ya incluido en precios)
        // Event listener para el formulario principal
        document.getElementById('formPresupuesto').addEventListener('submit', function(e) {
            e.preventDefault(); // Prevenir envío normal del formulario
            
            const productosContainer = document.getElementById('productosContainer');
            const productos = productosContainer.querySelectorAll('div.border');
            
            if (productos.length === 0) {
                alert('Debe agregar al menos un producto al presupuesto');
                return;
            }
            
            // Validar que todos los productos tengan cantidad y precio válidos
            let valido = true;
            productos.forEach(div => {
                const cantidadInput = div.querySelector('input[name*="cantidad"]');
                const precioInput = div.querySelector('input[name*="precio_unitario"]');
                
                if (cantidadInput && precioInput) {
                    const cantidad = parseFloat(cantidadInput.value);
                    const precio = parseFloat(precioInput.value);
                    
                    if (cantidad <= 0 || precio <= 0) {
                        valido = false;
                    }
                }
            });
            
            if (!valido) {
                alert('Todos los productos deben tener cantidad y precio mayores a cero');
                return;
            }
            
            // Enviar formulario vía AJAX
            const formData = new FormData(this);
            formData.append('accion', 'crear_presupuesto');
            
            // Debug: mostrar qué se está enviando
            console.log('=== DEBUG FORM DATA ===');
            for (let [key, value] of formData.entries()) {
                console.log(key, value);
            }
            
            fetch('presupuestos.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                console.log('Respuesta del servidor:', data);
                if (data.success) {
                    alert('Presupuesto creado exitosamente');
                    location.reload(); // Recargar para mostrar el nuevo presupuesto
                } else {
                    alert('Error al crear presupuesto: ' + (data.error || 'Error desconocido'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error de conexión: ' + error.message);
            });
        });
        
        // Cerrar sugerencias al hacer clic fuera
        document.addEventListener('click', function(e) {
            if (!e.target.closest('#buscadorProducto') && !e.target.closest('#sugerenciasProductos')) {
                document.getElementById('sugerenciasProductos').classList.add('hidden');
            }
        });
    </script>
</body>
</html>
