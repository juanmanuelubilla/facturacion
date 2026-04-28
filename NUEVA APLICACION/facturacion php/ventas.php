<?php
require_once 'config.php';
requireLogin();

$user = getUser();
$db = getDB();
$empresa_id = $user['empresa_id'];

$stmt = $db->prepare("SELECT id, codigo, nombre, precio, stock, imagen, venta_por_peso, costo FROM productos WHERE activo = 1 AND empresa_id = ? AND stock > 0 ORDER BY nombre");
$stmt->execute([$empresa_id]);
$productos = $stmt->fetchAll();

// Cargar reglas mayoristas (promociones_volumen)
$stmt = $db->prepare("SELECT producto_id, cantidad_minima, descuento_porcentaje FROM promociones_volumen WHERE empresa_id = ? AND activo = 1");
$stmt->execute([$empresa_id]);
$reglas_mayoristas = $stmt->fetchAll();

// Cargar combos (promociones_combos)
$stmt = $db->prepare("SELECT productos_ids, descuento_porcentaje FROM promociones_combos WHERE empresa_id = ? AND activo = 1");
$stmt->execute([$empresa_id]);
$combos = $stmt->fetchAll();

// Cargar categorías para filtros
$stmt = $db->prepare("SELECT id, nombre FROM categorias WHERE empresa_id = ? ORDER BY nombre");
$stmt->execute([$empresa_id]);
$categorias = $stmt->fetchAll();

// Cargar tags únicos para filtros
$stmt = $db->prepare("SELECT DISTINCT tags FROM productos WHERE empresa_id = ? AND tags IS NOT NULL AND tags != ''");
$stmt->execute([$empresa_id]);
$tags_result = $stmt->fetchAll();
$tags = [];
foreach ($tags_result as $t) {
    $tags_array = explode(',', $t['tags']);
    foreach ($tags_array as $tag) {
        $tag = trim($tag);
        if ($tag && !in_array($tag, $tags)) {
            $tags[] = $tag;
        }
    }
}
sort($tags);

// Config del negocio
$stmt = $db->prepare("SELECT nombre_negocio FROM nombre_negocio WHERE empresa_id = ?");
$stmt->execute([$empresa_id]);
$config = $stmt->fetch();
$nombre_negocio = $config['nombre_negocio'] ?? 'NEXUS POS';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ventas - NEXUS POS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-gray-900 to-gray-800 text-gray-100">
    <header class="bg-gray-900 shadow-lg">
        <div class="container mx-auto px-6 py-4 flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-green-400">🛒 VENTAS</h1>
                <p class="text-sm text-gray-400">Usuario: <?= strtoupper($user['nombre']) ?> | <?= strtoupper($user['rol']) ?></p>
            </div>
            <div class="flex gap-2">
                <button onclick="abrirCupon()" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded">🎫 CUPÓN (F5)</button>
                <button onclick="abrirCliente()" class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded">👤 CLIENTE</button>
                <a href="dashboard.php" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded">VOLVER</a>
            </div>
        </div>
    </header>
    <main class="container mx-auto px-6 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Panel de Productos -->
            <div class="lg:col-span-2 bg-gray-800 rounded-lg border border-gray-700 p-6">
                <div class="flex gap-4 mb-4">
                    <input type="text" id="inputCodigo" placeholder="Código o nombre (escriba para filtrar, Enter para agregar)" 
                           class="flex-1 bg-gray-700 text-white p-3 rounded border border-gray-600 focus:border-green-500 focus:outline-none"
                           oninput="filtrarGrid()" onkeypress="if(event.key==='Enter') buscarProducto()">
                    <button onclick="buscarProducto()" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded">➕ AGREGAR</button>
                </div>
                
                <!-- Filtros -->
                <div class="flex gap-2 mb-4 flex-wrap">
                    <select id="filtroCategoria" onchange="aplicarFiltros()" class="bg-gray-700 text-white p-2 rounded border border-gray-600 text-sm">
                        <option value="">Todas las categorías</option>
                        <?php foreach ($categorias as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select id="filtroTag" onchange="aplicarFiltros()" class="bg-gray-700 text-white p-2 rounded border border-gray-600 text-sm">
                        <option value="">Todos los tags</option>
                        <?php foreach ($tags as $tag): ?>
                        <option value="<?= htmlspecialchars($tag) ?>"><?= htmlspecialchars($tag) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button onclick="limpiarFiltros()" class="bg-yellow-600 hover:bg-yellow-700 text-white px-3 py-2 rounded text-sm">🧹 LIMPIAR</button>
                </div>
                
                <div class="grid grid-cols-3 md:grid-cols-4 gap-3 max-h-96 overflow-y-auto" id="gridProductos">
                    <?php foreach ($productos as $p): ?>
                    <div class="bg-gray-700 p-3 rounded hover:bg-gray-600 cursor-pointer transition-colors" 
                         onclick="agregarProducto(<?= $p['id'] ?>, '<?= htmlspecialchars($p['nombre']) ?>', <?= $p['precio'] ?>, '<?= htmlspecialchars($p['imagen'] ?? '') ?>', <?= $p['venta_por_peso'] ?>, <?= $p['stock'] ?>, <?= $p['costo'] ?>)"
                         data-codigo="<?= htmlspecialchars($p['codigo'] ?? '') ?>"
                         data-categoria="<?= $p['categoria_id'] ?? '' ?>"
                         data-tags="<?= htmlspecialchars($p['tags'] ?? '') ?>">
                        <?php if (!empty($p['imagen'])): ?>
                        <div class="w-full h-20 mb-2 rounded overflow-hidden">
                            <img src="<?= htmlspecialchars($p['imagen']) ?>" alt="<?= htmlspecialchars($p['nombre']) ?>" 
                                 class="w-full h-full object-cover">
                        </div>
                        <?php endif; ?>
                        <h4 class="text-white font-bold text-xs truncate"><?= htmlspecialchars($p['nombre']) ?></h4>
                        <p class="text-green-400 font-bold text-sm">$<?= number_format($p['precio'], 2) ?></p>
                        <p class="text-gray-400 text-xs">Stock: <?= number_format($p['stock'], $p['venta_por_peso'] ? 3 : 0) ?> <?= $p['venta_por_peso'] ? 'kg' : 'u' ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Panel de Carrito -->
            <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold text-white">CARRITO</h3>
                    <button onclick="nuevaVenta()" class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-sm">🔄 NUEVA</button>
                </div>
                
                <div class="bg-gray-700 rounded p-3 mb-4 min-h-64 max-h-80 overflow-y-auto" id="carritoItems">
                    <p class="text-gray-400 text-center py-8">Carrito vacío</p>
                </div>
                
                <div class="border-t border-gray-700 pt-4 space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-400">Usuario:</span>
                        <span class="text-green-400"><?= strtoupper($user['nombre']) ?></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-400">Cliente:</span>
                        <span class="text-orange-400" id="clienteDisplay">CONSUMIDOR FINAL</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-400">Descuento cupón:</span>
                        <span class="text-purple-400" id="descuentoDisplay">0%</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Subtotal:</span>
                        <span class="text-white" id="subtotalDisplay">$0.00</span>
                    </div>
                    <div class="flex justify-between text-xl">
                        <span class="text-gray-400">Total:</span>
                        <span class="text-green-400 font-bold" id="totalDisplay">$0.00</span>
                    </div>
                </div>
                
                <div class="grid grid-cols-3 gap-2 mt-4">
                    <button onclick="quitarUltimo()" class="bg-red-600 hover:bg-red-700 text-white py-3 rounded font-bold">❌ QUITAR (F3)</button>
                    <button onclick="vaciarCarrito()" class="bg-yellow-600 hover:bg-yellow-700 text-white py-3 rounded font-bold">🗑️ VACIAR (F4)</button>
                    <button onclick="abrirPagos()" class="bg-green-600 hover:bg-green-700 text-white py-3 rounded font-bold">💰 COBRAR (F2)</button>
                </div>
                <div class="grid grid-cols-2 gap-2 mt-2">
                    <button onclick="abrirPantallaCliente()" class="bg-blue-600 hover:bg-blue-700 text-white py-3 rounded">📺 PANTALLA CLIENTE (F8)</button>
                    <a href="logout.php" class="bg-gray-600 hover:bg-gray-700 text-white py-3 rounded text-center">SALIR (ESC)</a>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal de Pagos -->
    <div id="modalPagos" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-gray-800 rounded-lg p-6 w-full max-w-md">
            <h3 class="text-xl font-bold text-white mb-4">Seleccionar Método de Pago</h3>
            <div class="grid grid-cols-2 gap-3">
                <button onclick="abrirEfectivo()" class="bg-green-600 hover:bg-green-700 text-white py-4 rounded font-bold">💵 EFECTIVO</button>
                <button onclick="procesarPago('TARJETA')" class="bg-blue-600 hover:bg-blue-700 text-white py-4 rounded font-bold">💳 TARJETA</button>
                <button onclick="procesarPago('TRANSFERENCIA')" class="bg-purple-600 hover:bg-purple-700 text-white py-4 rounded font-bold">🏦 TRANSFERENCIA</button>
                <button onclick="abrirQR()" class="bg-orange-600 hover:bg-orange-700 text-white py-4 rounded font-bold">📱 QR</button>
            </div>
            <button onclick="cerrarModal('modalPagos')" class="w-full mt-4 bg-gray-600 hover:bg-gray-700 text-white py-2 rounded">❌ CANCELAR</button>
        </div>
    </div>

    <!-- Modal de Efectivo -->
    <div id="modalEfectivo" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-gray-800 rounded-lg p-6 w-full max-w-md">
            <h3 class="text-xl font-bold text-white mb-4">Pago en Efectivo</h3>
            <div class="space-y-4">
                <div class="bg-gray-700 rounded-lg p-4 mb-4">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-400">Total a pagar:</span>
                        <span class="text-2xl font-bold text-green-400" id="efectivoTotal">$0.00</span>
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-4 mb-6" id="seccionEfectivo" style="display: none;">
                    <div class="bg-gray-800 rounded-lg border border-gray-700 p-4">
                        <h3 class="text-lg font-bold text-white mb-4">EFECTIVO</h3>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-gray-400 text-sm mb-2">Monto Entregado</label>
                                <input type="number" step="0.01" id="montoEntregado" 
                                       class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600"
                                       placeholder="0.00" oninput="calcularVuelto()">
                            </div>
                            <div>
                                <label class="block text-gray-400 text-sm mb-2">Vuelto</label>
                                <p class="text-3xl font-bold text-yellow-400" id="vueltoDisplay">$0.00</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <button onclick="procesarPago('EFECTIVO')" class="bg-green-600 hover:bg-green-700 text-white py-3 rounded font-bold">✅ CONFIRMAR</button>
                    <button onclick="cerrarModal('modalEfectivo')" class="bg-gray-600 hover:bg-gray-700 text-white py-3 rounded">❌ CANCELAR</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de QR -->
    <div id="modalQR" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-gray-800 rounded-lg p-6 w-full max-w-md">
            <h3 class="text-xl font-bold text-white mb-4">Pagar con QR</h3>
            <div class="grid grid-cols-1 gap-3">
                <button onclick="procesarQR('MP')" class="bg-blue-500 hover:bg-blue-600 text-white py-3 rounded">📱 QR MERCADO PAGO</button>
                <button onclick="procesarQR('PW')" class="bg-red-500 hover:bg-red-600 text-white py-3 rounded">📱 QR PAYWAY</button>
                <button onclick="procesarQR('MODO')" class="bg-green-500 hover:bg-green-600 text-white py-3 rounded">📱 QR MODO</button>
            </div>
            <button onclick="cerrarModal('modalQR')" class="w-full mt-4 bg-gray-600 hover:bg-gray-700 text-white py-2 rounded">❌ CANCELAR</button>
        </div>
    </div>

    <!-- Modal de Cupón -->
    <div id="modalCupon" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-gray-800 rounded-lg p-6 w-full max-w-md">
            <h3 class="text-xl font-bold text-white mb-4">Escanear Cupón QR</h3>
            <input type="text" id="inputCupon" placeholder="Ingrese código del cupón" 
                   class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600 mb-4">
            <button onclick="validarCupon()" class="w-full bg-purple-600 hover:bg-purple-700 text-white py-3 rounded">✅ VALIDAR</button>
            <button onclick="cerrarModal('modalCupon')" class="w-full mt-2 bg-gray-600 hover:bg-gray-700 text-white py-2 rounded">❌ CANCELAR</button>
        </div>
    </div>

    <!-- Modal de Cliente -->
    <div id="modalCliente" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-gray-800 rounded-lg p-6 w-full max-w-lg">
            <h3 class="text-xl font-bold text-white mb-4">Buscar Cliente</h3>
            <input type="text" id="inputCliente" placeholder="Nombre o documento del cliente" 
                   class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600 mb-4"
                   onkeypress="if(event.key==='Enter') buscarCliente()">
            <div id="listaClientes" class="max-h-64 overflow-y-auto mb-4 space-y-2"></div>
            <div class="flex gap-2">
                <button onclick="buscarCliente()" class="flex-1 bg-orange-600 hover:bg-orange-700 text-white py-3 rounded">🔍 BUSCAR</button>
                <button onclick="cerrarModal('modalCliente')" class="flex-1 bg-gray-600 hover:bg-gray-700 text-white py-3 rounded">❌ CANCELAR</button>
            </div>
        </div>
    </div>

    <!-- Modal de Cantidad Fraccional -->
    <div id="modalCantidad" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-gray-800 rounded-lg p-6 w-full max-w-md">
            <h3 class="text-xl font-bold text-white mb-4">Producto Fraccional</h3>
            <p class="text-white mb-2" id="cantidadProductoNombre"></p>
            <p class="text-green-400 mb-4" id="cantidadProductoPrecio"></p>
            <label class="block text-gray-400 text-sm mb-2">Cantidad (kg):</label>
            <input type="number" step="0.001" id="inputCantidad" 
                   class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600 mb-4"
                   placeholder="Ingrese cantidad">
            <div class="flex gap-2">
                <button onclick="confirmarCantidad()" class="flex-1 bg-green-600 hover:bg-green-700 text-white py-3 rounded">✅ ACEPTAR</button>
                <button onclick="cerrarModal('modalCantidad')" class="flex-1 bg-gray-600 hover:bg-gray-700 text-white py-3 rounded">❌ CANCELAR</button>
            </div>
        </div>
    </div>

    <!-- Modal de Editar Cantidad -->
    <div id="modalEditarCantidad" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-gray-800 rounded-lg p-6 w-full max-w-md">
            <h3 class="text-xl font-bold text-white mb-4">Editar Cantidad</h3>
            <p class="text-white mb-2" id="editarProductoNombre"></p>
            <label class="block text-gray-400 text-sm mb-2">Nueva cantidad:</label>
            <input type="number" step="0.001" id="inputEditarCantidad" 
                   class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600 mb-4">
            <div class="flex gap-2">
                <button onclick="confirmarEditarCantidad()" class="flex-1 bg-green-600 hover:bg-green-700 text-white py-3 rounded">✅ ACEPTAR</button>
                <button onclick="cerrarModal('modalEditarCantidad')" class="flex-1 bg-gray-600 hover:bg-gray-700 text-white py-3 rounded">❌ CANCELAR</button>
            </div>
        </div>
    </div>

    <script>
        let carrito = [];
        let descuentoCupon = 0;
        let clienteActual = {id: 0, nombre: 'CONSUMIDOR FINAL', documento: '0'};
        let ventanaCliente = null;
        let pagoEnProceso = {metodo: null, montoEntregado: 0, vuelto: 0};
        let nombreNegocioJS = '<?= $nombre_negocio ?>';
        
        // Datos del servidor
        const reglasMayoristas = <?= json_encode($reglas_mayoristas) ?>;
        const combos = <?= json_encode($combos) ?>;
        const productosDB = <?= json_encode($productos) ?>;
        
        let productoPendiente = null; // Para productos fraccionales
        let indiceEdicion = null; // Para edición de cantidad

        function agregarProducto(id, nombre, precio, imagen, esPesable, stock, costo, cantidadManual = 1) {
            // Validación de stock
            const existente = carrito.find(item => item.id === id);
            const cantidadEnCarrito = existente ? existente.cantidad : 0;
            const cantidadTotal = cantidadEnCarrito + cantidadManual;
            
            if (stock <= 0 || cantidadTotal > stock) {
                alert(`Stock insuficiente. Disponible: ${stock} ${esPesable ? 'kg' : 'u'}`);
                return;
            }
            
            if (existente) {
                existente.cantidad += cantidadManual;
            } else {
                carrito.push({
                    id, nombre, precio, cantidad: cantidadManual, imagen, 
                    esPesable, precioOriginal: precio, costo: costo || 0
                });
            }
            recalcularPrecios();
            actualizarCarrito();
            actualizarPantallaCliente();
        }

        function recalcularPrecios() {
            // Aplicar reglas mayoristas
            carrito.forEach(item => {
                let precioFinal = item.precioOriginal;
                const regla = reglasMayoristas.find(r => r.producto_id === item.id);
                if (regla && item.cantidad >= regla.cantidad_minima) {
                    precioFinal = item.precioOriginal * (1 - regla.descuento_porcentaje / 100);
                }
                item.precio = precioFinal;
                item.subtotal = precioFinal * item.cantidad;
            });
            
            // Aplicar combos (promociones_combos)
            combos.forEach(combo => {
                const idsNecesarios = combo.productos_ids.split(',').map(Number);
                const idsCarrito = carrito.map(i => i.id);
                if (idsNecesarios.every(id => idsCarrito.includes(id))) {
                    const factor = combo.descuento_porcentaje / 100;
                    idsNecesarios.forEach(id => {
                        const item = carrito.find(i => i.id === id);
                        if (item) {
                            item.subtotal = item.subtotal * (1 - factor);
                        }
                    });
                }
            });
        }

        function actualizarCarrito() {
            const container = document.getElementById('carritoItems');
            if (carrito.length === 0) {
                container.innerHTML = '<p class="text-gray-400 text-center py-8">Carrito vacío</p>';
            } else {
                container.innerHTML = carrito.map((item, index) =>
                    '<div class="flex justify-between items-center py-2 border-b border-gray-600 cursor-pointer" ondblclick="editarCantidad(' + index + ')" oncontextmenu="mostrarMenuContextual(event, ' + index + ')">' +
                    '<div class="flex items-center gap-3">' +
                    (item.imagen ? '<img src="' + item.imagen + '" alt="" class="w-12 h-12 object-cover rounded">' : '<div class="w-12 h-12 bg-gray-600 rounded flex items-center justify-center text-gray-400">🖼️</div>') +
                    '<div>' +
                    '<p class="text-white text-sm">' + item.nombre + '</p>' +
                    '<p class="text-gray-400 text-xs">$' + item.precio.toFixed(2) + ' x ' + (item.esPesable ? item.cantidad.toFixed(3) : item.cantidad) + (item.esPesable ? ' kg' : ' u') + '</p>' +
                    '</div>' +
                    '</div>' +
                    '<div class="flex items-center gap-2">' +
                    '<span class="text-green-400 font-bold">$' + (item.subtotal || (item.precio * item.cantidad)).toFixed(2) + '</span>' +
                    '<button onclick="event.stopPropagation(); eliminarItem(' + item.id + ')" class="text-red-400 hover:text-red-300">✕</button>' +
                    '</div>' +
                    '</div>'
                ).join('');
            }

            let subtotal = carrito.reduce((sum, item) => sum + (item.subtotal || (item.precio * item.cantidad)), 0);
            const descuento = subtotal * (descuentoCupon / 100);
            const total = subtotal - descuento;

            document.getElementById('subtotalDisplay').textContent = '$' + subtotal.toFixed(2);
            document.getElementById('descuentoDisplay').textContent = descuentoCupon + '%';
            document.getElementById('totalDisplay').textContent = '$' + total.toFixed(2);
        }

        function eliminarItem(id) {
            carrito = carrito.filter(item => item.id !== id);
            actualizarCarrito();
        }

        function quitarUltimo() {
            if (carrito.length > 0) {
                const ultimo = carrito.pop();
                actualizarCarrito();
                actualizarPantallaCliente();
            }
        }

        function abrirModalCantidad(producto) {
            productoPendiente = producto;
            document.getElementById('cantidadProductoNombre').textContent = producto.nombre;
            document.getElementById('cantidadProductoPrecio').textContent = 'Precio: $' + producto.precio.toFixed(2);
            document.getElementById('inputCantidad').value = '';
            document.getElementById('modalCantidad').classList.remove('hidden');
            document.getElementById('modalCantidad').classList.add('flex');
            document.getElementById('inputCantidad').focus();
        }

        function confirmarCantidad() {
            const cantidad = parseFloat(document.getElementById('inputCantidad').value);
            if (cantidad && cantidad > 0 && productoPendiente) {
                agregarProducto(
                    productoPendiente.id, productoPendiente.nombre, productoPendiente.precio,
                    productoPendiente.imagen, productoPendiente.esPesable, productoPendiente.stock,
                    productoPendiente.costo, cantidad
                );
            }
            cerrarModal('modalCantidad');
            productoPendiente = null;
        }

        function editarCantidad(index) {
            indiceEdicion = index;
            const item = carrito[index];
            document.getElementById('editarProductoNombre').textContent = item.nombre;
            document.getElementById('inputEditarCantidad').value = item.esPesable ? item.cantidad.toFixed(3) : item.cantidad;
            document.getElementById('modalEditarCantidad').classList.remove('hidden');
            document.getElementById('modalEditarCantidad').classList.add('flex');
            document.getElementById('inputEditarCantidad').focus();
        }

        function confirmarEditarCantidad() {
            const nuevaCantidad = parseFloat(document.getElementById('inputEditarCantidad').value);
            if (nuevaCantidad && nuevaCantidad > 0 && indiceEdicion !== null) {
                const item = carrito[indiceEdicion];
                // Validar stock
                const productoDB = productosDB.find(p => p.id === item.id);
                if (productoDB && nuevaCantidad > productoDB.stock) {
                    alert('Stock insuficiente');
                    return;
                }
                item.cantidad = nuevaCantidad;
                recalcularPrecios();
                actualizarCarrito();
                actualizarPantallaCliente();
            } else if (nuevaCantidad === 0 && indiceEdicion !== null) {
                // Si la cantidad es 0, eliminar el item
                carrito.splice(indiceEdicion, 1);
                recalcularPrecios();
                actualizarCarrito();
                actualizarPantallaCliente();
            }
            cerrarModal('modalEditarCantidad');
            indiceEdicion = null;
        }

        function mostrarMenuContextual(event, index) {
            event.preventDefault();
            if (confirm('¿Eliminar este item?')) {
                carrito.splice(index, 1);
                recalcularPrecios();
                actualizarCarrito();
                actualizarPantallaCliente();
            }
        }

        function buscarProducto() {
            const texto = document.getElementById('inputCodigo').value.trim();
            if (!texto) return;

            let cantidadManual = 1;
            let codigoBusqueda = texto;

            // Verificar formato cantidad*codigo
            if (texto.includes('*')) {
                const partes = texto.split('*');
                cantidadManual = parseFloat(partes[0]);
                codigoBusqueda = partes[1];
                if (isNaN(cantidadManual) || cantidadManual <= 0) {
                    cantidadManual = 1;
                }
            }

            // Buscar por código primero
            let producto = productosDB.find(p => p.codigo === codigoBusqueda);

            // Si no encuentra, buscar por nombre
            if (!producto) {
                producto = productosDB.find(p => p.nombre.toLowerCase().includes(codigoBusqueda.toLowerCase()));
            }

            if (producto) {
                // Si es pesable y no se ingresó cantidad manual, pedir cantidad
                if (producto.venta_por_peso && !texto.includes('*')) {
                    abrirModalCantidad({
                        id: producto.id,
                        nombre: producto.nombre,
                        precio: producto.precio,
                        imagen: producto.imagen,
                        esPesable: true,
                        stock: producto.stock,
                        costo: producto.costo
                    });
                } else {
                    agregarProducto(
                        producto.id, producto.nombre, producto.precio,
                        producto.imagen, producto.venta_por_peso, producto.stock,
                        producto.costo, cantidadManual
                    );
                }
                document.getElementById('inputCodigo').value = '';
            } else {
                alert('Producto no encontrado');
            }
        }

        function filtrarGrid() {
            aplicarFiltros();
        }

        function aplicarFiltros() {
            const termino = document.getElementById('inputCodigo').value.toLowerCase();
            const categoriaId = document.getElementById('filtroCategoria').value;
            const tagFiltro = document.getElementById('filtroTag').value.toLowerCase();
            
            const cards = document.querySelectorAll('#gridProductos > div');
            cards.forEach(card => {
                const nombre = card.querySelector('h4').textContent.toLowerCase();
                const codigo = card.dataset.codigo?.toLowerCase() || '';
                const categoria = card.dataset.categoria || '';
                const tags = card.dataset.tags?.toLowerCase() || '';
                
                const coincideTexto = nombre.includes(termino) || codigo.includes(termino);
                const coincideCategoria = !categoriaId || categoria === categoriaId;
                const coincideTag = !tagFiltro || tags.includes(tagFiltro);
                
                card.style.display = (coincideTexto && coincideCategoria && coincideTag) ? 'block' : 'none';
            });
        }

        function limpiarFiltros() {
            document.getElementById('inputCodigo').value = '';
            document.getElementById('filtroCategoria').value = '';
            document.getElementById('filtroTag').value = '';
            aplicarFiltros();
        }

        function vaciarCarrito() {
            if (carrito.length === 0) return;
            if (confirm('¿Vaciar el carrito?')) {
                carrito = [];
                descuentoCupon = 0;
                actualizarCarrito();
                actualizarPantallaCliente();
            }
        }

        function nuevaVenta() {
            carrito = [];
            descuentoCupon = 0;
            clienteActual = {id: 0, nombre: 'CONSUMIDOR FINAL', documento: '0'};
            actualizarCarrito();
            actualizarPantallaCliente();
        }

        function abrirPagos() {
            if (carrito.length === 0) {
                alert('El carrito está vacío');
                return;
            }
            document.getElementById('modalPagos').classList.remove('hidden');
            document.getElementById('modalPagos').classList.add('flex');
        }

        function abrirEfectivo() {
            const subtotal = carrito.reduce((sum, item) => sum + (item.precio * item.cantidad), 0);
            const descuento = subtotal * (descuentoCupon / 100);
            const total = subtotal - descuento;
            
            document.getElementById('efectivoTotal').textContent = '$' + total.toFixed(2);
            document.getElementById('montoEntregado').value = '';
            document.getElementById('vueltoDisplay').textContent = '$0.00';
            
            // Actualizar variables de pago
            pagoEnProceso = {metodo: 'EFECTIVO', montoEntregado: 0, vuelto: 0};
            
            // Mostrar sección de efectivo y vuelto
            document.getElementById('seccionEfectivo').style.display = 'block';
            
            document.getElementById('modalPagos').classList.add('hidden');
            document.getElementById('modalEfectivo').classList.remove('hidden');
            document.getElementById('modalEfectivo').classList.add('flex');
            document.getElementById('montoEntregado').focus();
            
            // Actualizar pantalla cliente con información de pago
            actualizarPantallaCliente();
        }

        function calcularVuelto() {
            const subtotal = carrito.reduce((sum, item) => sum + (item.precio * item.cantidad), 0);
            const descuento = subtotal * (descuentoCupon / 100);
            const total = subtotal - descuento;
            const entregado = parseFloat(document.getElementById('montoEntregado').value) || 0;
            const vuelto = entregado - total;
            
            // Actualizar variables de pago
            pagoEnProceso.montoEntregado = entregado;
            pagoEnProceso.vuelto = vuelto;
            
            document.getElementById('vueltoDisplay').textContent = '$' + vuelto.toFixed(2);
            document.getElementById('vueltoDisplay').className = vuelto >= 0 ? 'text-3xl font-bold text-green-400' : 'text-3xl font-bold text-red-400';
            
            // Actualizar pantalla cliente con información de pago
            actualizarPantallaCliente();
        }

        function abrirQR() {
            document.getElementById('modalPagos').classList.add('hidden');
            document.getElementById('modalQR').classList.remove('hidden');
            document.getElementById('modalQR').classList.add('flex');
        }

        function abrirCupon() {
            document.getElementById('modalCupon').classList.remove('hidden');
            document.getElementById('modalCupon').classList.add('flex');
            document.getElementById('inputCupon').focus();
        }

        function validarCupon() {
            const codigo = document.getElementById('inputCupon').value.toUpperCase();
            // Aquí iría la validación con el backend
            descuentoCupon = 10; // Ejemplo: 10% de descuento
            actualizarCarrito();
            cerrarModal('modalCupon');
            alert('¡Cupón aplicado! Descuento: 10%');
        }

        async function procesarPago(metodo) {
            if (carrito.length === 0) return;

            const subtotal = carrito.reduce((sum, item) => sum + (item.subtotal || (item.precio * item.cantidad)), 0);
            const descuento = subtotal * (descuentoCupon / 100);
            const total = subtotal - descuento;

            let montoEntregado = total;
            let vuelto = 0;

            if (metodo === 'EFECTIVO') {
                montoEntregado = parseFloat(document.getElementById('montoEntregado').value) || total;
                vuelto = montoEntregado - total;
                
                // Validar que el monto entregado sea suficiente
                if (montoEntregado < total) {
                    alert('El monto entregado es insuficiente. Total: $' + total.toFixed(2) + ', Entregado: $' + montoEntregado.toFixed(2));
                    document.getElementById('montoEntregado').focus();
                    return;
                }
            }

            const ventaData = {
                cliente_id: clienteActual.id,
                total: total,
                metodo: metodo,
                entregado: montoEntregado,
                vuelto: vuelto,
                items: carrito.map(item => ({
                    producto_id: item.id,
                    cantidad: item.cantidad,
                    precio: item.precio,
                    subtotal: item.subtotal || (item.precio * item.cantidad)
                }))
            };

            try {
                const response = await fetch('api_procesar_venta.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(ventaData)
                });

                const responseText = await response.text();
                console.log('Response status:', response.status);
                console.log('Response text:', responseText);

                let result;
                try {
                    result = JSON.parse(responseText);
                } catch (e) {
                    alert('Error al procesar respuesta del servidor. Respuesta cruda: ' + responseText.substring(0, 500));
                    return;
                }

                if (result.success) {
                    // Actualizar variables de pago para la pantalla cliente
                    if (metodo === 'EFECTIVO') {
                        pagoEnProceso.montoEntregado = montoEntregado;
                        pagoEnProceso.vuelto = vuelto;
                    }
                    
                    // Enviar confirmación a pantalla cliente
                    if (ventanaCliente && !ventanaCliente.closed) {
                        try {
                            ventanaCliente.postMessage({
                                type: 'ventaCompletada',
                                venta_id: result.venta_id,
                                total: total,
                                metodo: metodo,
                                exito: true
                            }, '*');
                        } catch (e) {
                            console.log('Error enviando confirmación a pantalla cliente:', e);
                        }
                    }
                    
                    alert(`Venta #${result.venta_id} completada. Total: $${total.toFixed(2)}`);
                    nuevaVenta();
                    cerrarModal('modalPagos');
                    cerrarModal('modalEfectivo');
                    // Recargar productos para actualizar stock
                    location.reload();
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (e) {
                alert('Error al procesar venta: ' + e.message);
            }
        }

        function procesarQR(tipo) {
            alert(`QR generado para ${tipo}`);
            nuevaVenta();
            cerrarModal('modalQR');
        }

        function cerrarModal(id) {
            document.getElementById(id).classList.add('hidden');
            document.getElementById(id).classList.remove('flex');
        }

        function buscarProducto() {
            const termino = document.getElementById('inputCodigo').value.toLowerCase();
            const cards = document.querySelectorAll('#gridProductos > div');
            cards.forEach(card => {
                const nombre = card.querySelector('h4').textContent.toLowerCase();
                card.style.display = nombre.includes(termino) ? 'block' : 'none';
            });
        }

        function abrirCliente() {
            document.getElementById('modalCliente').classList.remove('hidden');
            document.getElementById('modalCliente').classList.add('flex');
            document.getElementById('inputCliente').focus();
        }

        async function buscarCliente() {
            const termino = document.getElementById('inputCliente').value.trim();
            const lista = document.getElementById('listaClientes');

            if (termino.length < 2) {
                lista.innerHTML = '<p class="text-gray-400 text-sm">Ingrese al menos 2 caracteres</p>';
                return;
            }

            try {
                const response = await fetch(`api_buscar_clientes.php?termino=${encodeURIComponent(termino)}`);
                const clientes = await response.json();

                if (clientes.length === 0) {
                    lista.innerHTML = '<p class="text-gray-400 text-sm">No se encontraron clientes</p>';
                    return;
                }

                lista.innerHTML = clientes.map(c =>
                    '<div class="bg-gray-700 p-3 rounded cursor-pointer hover:bg-gray-600" onclick="seleccionarCliente(' + c.id + ', \'' + (c.nombre + ' ' + c.apellido).replace(/'/g, "\\'") + '\', \'' + (c.documento || '0') + '\')">' +
                    '<p class="text-white font-bold">' + (c.nombre + ' ' + c.apellido) + '</p>' +
                    '<p class="text-gray-400 text-sm">Documento: ' + (c.documento || '0') + '</p>' +
                    '</div>'
                ).join('');
            } catch (e) {
                lista.innerHTML = '<p class="text-red-400 text-sm">Error al buscar clientes</p>';
            }
        }

        function seleccionarCliente(id, nombre, documento) {
            clienteActual = {id, nombre, documento};
            document.getElementById('clienteDisplay').textContent = nombre;
            cerrarModal('modalCliente');
            actualizarPantallaCliente();
        }

        function abrirPantallaCliente() {
            if (ventanaCliente && !ventanaCliente.closed) {
                ventanaCliente.focus();
                return;
            }

            const width = screen.width / 2;
            const height = screen.height;
            const left = screen.width / 2;

            ventanaCliente = window.open('pantalla_cliente.php', 'PantallaCliente',
                'width=' + width + ',height=' + height + ',left=' + left + ',top=0');

            actualizarPantallaCliente();
        }

        function actualizarPantallaCliente() {
            if (!ventanaCliente || ventanaCliente.closed) return;

            try {
                const subtotal = carrito.reduce((sum, item) => sum + (item.precio * item.cantidad), 0);
                const descuento = subtotal * (descuentoCupon / 100);
                const total = subtotal - descuento;

                const ultimo = carrito.length > 0 ? carrito[carrito.length - 1] : null;

                ventanaCliente.postMessage({
                    type: 'actualizar',
                    total: total,
                    cliente: clienteActual.nombre,
                    nombreNegocio: nombreNegocioJS,
                    ultimo: ultimo,
                    items: carrito,
                    pago: pagoEnProceso
                }, '*');
            } catch (e) {
                console.log('Error actualizando pantalla cliente:', e);
            }
        }

        // Atajos de teclado
        document.addEventListener('keydown', (e) => {
            if (e.key === 'F2') {
                e.preventDefault();
                abrirPagos();
            }
            if (e.key === 'F3') {
                e.preventDefault();
                quitarUltimo();
            }
            if (e.key === 'F4') {
                e.preventDefault();
                vaciarCarrito();
            }
            if (e.key === 'F5') {
                e.preventDefault();
                abrirCupon();
            }
            if (e.key === 'F6') {
                e.preventDefault();
                abrirCliente();
            }
            if (e.key === 'F8') {
                e.preventDefault();
                abrirPantallaCliente();
            }
            if (e.key === 'Escape') {
                e.preventDefault();
                if (confirm('¿Salir del sistema?')) {
                    window.location.href = 'logout.php';
                }
            }
        });
    </script>
</body>
</html>
