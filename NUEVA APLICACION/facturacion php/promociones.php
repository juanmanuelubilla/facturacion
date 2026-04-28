<?php
require_once 'config.php';
requireLogin();

$user = getUser();
$empresa_id = $user['empresa_id'];

// Cargar productos para combos
$productos = fetchAll("SELECT id, nombre FROM productos WHERE empresa_id = ? AND activo = 1", [$empresa_id]);

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $tipo = $_POST['tipo'] ?? '';
    
    if ($tipo === 'cupon' && $accion === 'guardar') {
        $codigo = trim($_POST['codigo'] ?? '');
        $descuento = floatval($_POST['descuento'] ?? 0);
        $activo = isset($_POST['activo']) ? 1 : 0;

        if ($codigo && $descuento > 0) {
            query("INSERT INTO cupones (empresa_id, codigo_qr, descuento_porcentaje, activo) VALUES (?, ?, ?, ?)",
                  [$empresa_id, strtoupper($codigo), $descuento, $activo]);
        }
        header('Location: promociones.php');
        exit;
    }
    
    if ($tipo === 'cupon' && $accion === 'eliminar' && !empty($_POST['id'])) {
        query("DELETE FROM cupones WHERE id=? AND empresa_id=?", [$_POST['id'], $empresa_id]);
        header('Location: promociones.php');
        exit;
    }

    if ($tipo === 'cupon' && $accion === 'editar' && !empty($_POST['id'])) {
        $codigo = trim($_POST['codigo'] ?? '');
        $descuento = floatval($_POST['descuento'] ?? 0);
        $activo = isset($_POST['activo']) ? 1 : 0;
        query("UPDATE cupones SET codigo_qr=?, descuento_porcentaje=?, activo=? WHERE id=? AND empresa_id=?",
              [strtoupper($codigo), $descuento, $activo, $_POST['id'], $empresa_id]);
        header('Location: promociones.php');
        exit;
    }
    
    if ($tipo === 'combo' && $accion === 'guardar') {
        $nombre = trim($_POST['nombre'] ?? '');
        $productos_ids = $_POST['productos_ids'] ?? '';
        $descuento = floatval($_POST['descuento'] ?? 0);

        if ($nombre && $productos_ids && $descuento > 0) {
            query("INSERT INTO promociones_combos (empresa_id, nombre_promo, productos_ids, descuento_porcentaje, activo) VALUES (?, ?, ?, ?, 1)",
                  [$empresa_id, $nombre, $productos_ids, $descuento]);
        }
        header('Location: promociones.php');
        exit;
    }

    if ($tipo === 'combo' && $accion === 'eliminar' && !empty($_POST['id'])) {
        query("DELETE FROM promociones_combos WHERE id=? AND empresa_id=?", [$_POST['id'], $empresa_id]);
        header('Location: promociones.php');
        exit;
    }

    if ($tipo === 'combo' && $accion === 'editar' && !empty($_POST['id'])) {
        $nombre = trim($_POST['nombre'] ?? '');
        $productos_ids = $_POST['productos_ids'] ?? '';
        $descuento = floatval($_POST['descuento'] ?? 0);
        query("UPDATE promociones_combos SET nombre_promo=?, productos_ids=?, descuento_porcentaje=? WHERE id=? AND empresa_id=?",
              [$nombre, $productos_ids, $descuento, $_POST['id'], $empresa_id]);
        header('Location: promociones.php');
        exit;
    }

    if ($tipo === 'mayorista' && $accion === 'guardar') {
        $producto_id = $_POST['producto_id'] ?? null;
        $cantidad_minima = floatval($_POST['cantidad_minima'] ?? 0);
        $descuento = floatval($_POST['descuento'] ?? 0);

        if ($producto_id && $cantidad_minima > 0 && $descuento > 0) {
            query("INSERT INTO promociones_volumen (empresa_id, producto_id, cantidad_minima, descuento_porcentaje, activo) VALUES (?, ?, ?, ?, 1)",
                  [$empresa_id, $producto_id, $cantidad_minima, $descuento]);
        }
        header('Location: promociones.php');
        exit;
    }

    if ($tipo === 'mayorista' && $accion === 'eliminar' && !empty($_POST['id'])) {
        query("DELETE FROM promociones_volumen WHERE id=? AND empresa_id=?", [$_POST['id'], $empresa_id]);
        header('Location: promociones.php');
        exit;
    }

    if ($tipo === 'mayorista' && $accion === 'editar' && !empty($_POST['id'])) {
        $producto_id = $_POST['producto_id'] ?? null;
        $cantidad_minima = floatval($_POST['cantidad_minima'] ?? 0);
        $descuento = floatval($_POST['descuento'] ?? 0);
        query("UPDATE promociones_volumen SET producto_id=?, cantidad_minima=?, descuento_porcentaje=? WHERE id=? AND empresa_id=?",
              [$producto_id, $cantidad_minima, $descuento, $_POST['id'], $empresa_id]);
        header('Location: promociones.php');
        exit;
    }
}

$cupones = fetchAll("SELECT * FROM cupones WHERE empresa_id = ? ORDER BY codigo_qr", [$empresa_id]);
$combos = fetchAll("SELECT * FROM promociones_combos WHERE empresa_id = ? ORDER BY nombre_promo", [$empresa_id]);
$mayoristas = fetchAll("SELECT pv.*, p.nombre as producto_nombre FROM promociones_volumen pv LEFT JOIN productos p ON pv.producto_id = p.id WHERE pv.empresa_id = ? ORDER BY p.nombre", [$empresa_id]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Promociones - NEXUS POS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-gray-900 to-gray-800 text-gray-100">
    <header class="bg-gray-900 shadow-lg">
        <div class="container mx-auto px-6 py-4 flex justify-between items-center">
            <h1 class="text-2xl font-bold text-yellow-400">🎟️ CENTRO DE PROMOCIONES</h1>
            <a href="dashboard.php" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded">VOLVER</a>
        </div>
    </header>
    <main class="container mx-auto px-6 py-8">
        <!-- Tabs -->
        <div class="flex gap-2 mb-6">
            <button onclick="showTab('cupon')" id="tabCupon" class="px-6 py-3 rounded font-bold bg-yellow-600 text-white">CUPÓN</button>
            <button onclick="showTab('combo')" id="tabCombo" class="px-6 py-3 rounded font-bold bg-gray-700 text-white">COMBOS</button>
            <button onclick="showTab('mayorista')" id="tabMayorista" class="px-6 py-3 rounded font-bold bg-gray-700 text-white">MAYORISTA</button>
        </div>
        
        <!-- Tab Cupón -->
        <div id="panelCupon" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
                <h3 class="text-xl font-bold text-white mb-4">Nuevo Cupón</h3>
                <form method="POST">
                    <input type="hidden" name="accion" value="guardar">
                    <input type="hidden" name="tipo" value="cupon">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">Código QR</label>
                            <input type="text" name="codigo" required
                                   class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600"
                                   placeholder="Ej: PROMO10">
                        </div>
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">Descuento (%)</label>
                            <input type="number" step="0.01" name="descuento" required
                                   class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="checkbox" name="activo" checked class="w-4 h-4">
                            <label class="text-gray-400 text-sm">Activo</label>
                        </div>
                        <button type="submit" class="w-full bg-yellow-600 hover:bg-yellow-700 text-white py-3 rounded font-bold">
                            GUARDAR
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="lg:col-span-2 bg-gray-800 rounded-lg border border-gray-700 p-6">
                <h3 class="text-xl font-bold text-white mb-4">Cupones Activos</h3>
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-700">
                            <th class="text-left py-2 px-3 text-gray-400">Código</th>
                            <th class="text-left py-2 px-3 text-gray-400">Descuento</th>
                            <th class="text-left py-2 px-3 text-gray-400">Estado</th>
                            <th class="text-left py-2 px-3 text-gray-400">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cupones as $c): ?>
                        <tr class="border-b border-gray-700 hover:bg-gray-700">
                            <td class="py-2 px-3 text-sm font-bold"><?= htmlspecialchars($c['codigo_qr']) ?></td>
                            <td class="py-2 px-3 text-sm text-green-400"><?= $c['descuento_porcentaje'] ?>%</td>
                            <td class="py-2 px-3 text-sm">
                                <span class="<?= $c['activo'] ? 'bg-green-600' : 'bg-red-600' ?> px-2 py-1 rounded text-xs">
                                    <?= $c['activo'] ? 'Activo' : 'Inactivo' ?>
                                </span>
                            </td>
                            <td class="py-2 px-3 text-sm">
                                <button onclick="eliminarCupon(<?= $c['id'] ?>)" class="text-red-400 hover:text-red-300">Eliminar</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Tab Combo -->
        <div id="panelCombo" class="hidden grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
                <h3 class="text-xl font-bold text-white mb-4">Nuevo Combo</h3>
                <form method="POST">
                    <input type="hidden" name="accion" value="guardar">
                    <input type="hidden" name="tipo" value="combo">

                    <div class="space-y-4">
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">Nombre del Combo</label>
                            <input type="text" name="nombre" required
                                   class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                        </div>
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">IDs de Productos (separados por coma)</label>
                            <input type="text" name="productos_ids" required
                                   class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600"
                                   placeholder="Ej: 1,2,3">
                        </div>
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">Descuento (%)</label>
                            <input type="number" step="0.01" name="descuento" required
                                   class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                        </div>
                        <button type="submit" class="w-full bg-pink-600 hover:bg-pink-700 text-white py-3 rounded font-bold">
                            GUARDAR
                        </button>
                    </div>
                </form>
            </div>

            <div class="lg:col-span-2 bg-gray-800 rounded-lg border border-gray-700 p-6">
                <h3 class="text-xl font-bold text-white mb-4">Combos Activos</h3>
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-700">
                            <th class="text-left py-2 px-3 text-gray-400">Nombre</th>
                            <th class="text-left py-2 px-3 text-gray-400">Productos IDs</th>
                            <th class="text-left py-2 px-3 text-gray-400">Descuento</th>
                            <th class="text-left py-2 px-3 text-gray-400">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($combos as $c): ?>
                        <tr class="border-b border-gray-700 hover:bg-gray-700">
                            <td class="py-2 px-3 text-sm font-bold"><?= htmlspecialchars($c['nombre_promo']) ?></td>
                            <td class="py-2 px-3 text-sm"><?= htmlspecialchars($c['productos_ids']) ?></td>
                            <td class="py-2 px-3 text-sm text-green-400"><?= $c['descuento_porcentaje'] ?>%</td>
                            <td class="py-2 px-3 text-sm">
                                <button onclick="editarCombo(<?= $c['id'] ?>, '<?= htmlspecialchars($c['nombre_promo']) ?>', '<?= htmlspecialchars($c['productos_ids']) ?>', <?= $c['descuento_porcentaje'] ?>)" class="text-blue-400 hover:text-blue-300 mr-2">Editar</button>
                                <button onclick="eliminarCombo(<?= $c['id'] ?>)" class="text-red-400 hover:text-red-300">Eliminar</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tab Mayorista -->
        <div id="panelMayorista" class="hidden grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
                <h3 class="text-xl font-bold text-white mb-4">Nueva Regla Mayorista</h3>
                <form method="POST">
                    <input type="hidden" name="accion" value="guardar">
                    <input type="hidden" name="tipo" value="mayorista">

                    <div class="space-y-4">
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">Producto</label>
                            <select name="producto_id" required
                                    class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                                <?php foreach ($productos as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">Cantidad Mínima</label>
                            <input type="number" step="0.001" name="cantidad_minima" required
                                   class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                        </div>
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">Descuento (%)</label>
                            <input type="number" step="0.01" name="descuento" required
                                   class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                        </div>
                        <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white py-3 rounded font-bold">
                            GUARDAR
                        </button>
                    </div>
                </form>
            </div>

            <div class="lg:col-span-2 bg-gray-800 rounded-lg border border-gray-700 p-6">
                <h3 class="text-xl font-bold text-white mb-4">Reglas Mayoristas Activas</h3>
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-700">
                            <th class="text-left py-2 px-3 text-gray-400">Producto</th>
                            <th class="text-left py-2 px-3 text-gray-400">Cantidad Mínima</th>
                            <th class="text-left py-2 px-3 text-gray-400">Descuento</th>
                            <th class="text-left py-2 px-3 text-gray-400">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mayoristas as $m): ?>
                        <tr class="border-b border-gray-700 hover:bg-gray-700">
                            <td class="py-2 px-3 text-sm font-bold"><?= htmlspecialchars($m['producto_nombre']) ?></td>
                            <td class="py-2 px-3 text-sm"><?= $m['cantidad_minima'] ?></td>
                            <td class="py-2 px-3 text-sm text-green-400"><?= $m['descuento_porcentaje'] ?>%</td>
                            <td class="py-2 px-3 text-sm">
                                <button onclick="editarMayorista(<?= $m['id'] ?>, <?= $m['producto_id'] ?>, <?= $m['cantidad_minima'] ?>, <?= $m['descuento_porcentaje'] ?>)" class="text-blue-400 hover:text-blue-300 mr-2">Editar</button>
                                <button onclick="eliminarMayorista(<?= $m['id'] ?>)" class="text-red-400 hover:text-red-300">Eliminar</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        function showTab(tab) {
            document.getElementById('panelCupon').classList.add('hidden');
            document.getElementById('panelCombo').classList.add('hidden');
            document.getElementById('panelMayorista').classList.add('hidden');
            document.getElementById('tabCupon').classList.remove('bg-yellow-600');
            document.getElementById('tabCupon').classList.add('bg-gray-700');
            document.getElementById('tabCombo').classList.remove('bg-pink-600');
            document.getElementById('tabCombo').classList.add('bg-gray-700');
            document.getElementById('tabMayorista').classList.remove('bg-green-600');
            document.getElementById('tabMayorista').classList.add('bg-gray-700');

            if (tab === 'cupon') {
                document.getElementById('panelCupon').classList.remove('hidden');
                document.getElementById('tabCupon').classList.add('bg-yellow-600');
                document.getElementById('tabCupon').classList.remove('bg-gray-700');
            } else if (tab === 'combo') {
                document.getElementById('panelCombo').classList.remove('hidden');
                document.getElementById('tabCombo').classList.add('bg-pink-600');
                document.getElementById('tabCombo').classList.remove('bg-gray-700');
            } else if (tab === 'mayorista') {
                document.getElementById('panelMayorista').classList.remove('hidden');
                document.getElementById('tabMayorista').classList.add('bg-green-600');
                document.getElementById('tabMayorista').classList.remove('bg-gray-700');
            }
        }

        function eliminarCupon(id) {
            if (confirm('¿Eliminar este cupón?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="accion" value="eliminar"><input type="hidden" name="tipo" value="cupon"><input type="hidden" name="id" value="' + id + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }

        function editarCupon(id, codigo, descuento, activo) {
            const form = document.querySelector('#panelCupon form');
            form.action.value = 'editar';
            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'id';
            idInput.value = id;
            form.appendChild(idInput);
            form.codigo.value = codigo;
            form.descuento.value = descuento;
            form.activo.checked = activo;
            document.querySelector('#panelCupon h3').textContent = 'Editar Cupón';
        }

        function eliminarCombo(id) {
            if (confirm('¿Eliminar este combo?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="accion" value="eliminar"><input type="hidden" name="tipo" value="combo"><input type="hidden" name="id" value="' + id + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }

        function editarCombo(id, nombre, productosIds, descuento) {
            const form = document.querySelector('#panelCombo form');
            form.action.value = 'editar';
            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'id';
            idInput.value = id;
            form.appendChild(idInput);
            form.nombre.value = nombre;
            form.productos_ids.value = productosIds;
            form.descuento.value = descuento;
            document.querySelector('#panelCombo h3').textContent = 'Editar Combo';
        }

        function eliminarMayorista(id) {
            if (confirm('¿Eliminar esta regla mayorista?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="accion" value="eliminar"><input type="hidden" name="tipo" value="mayorista"><input type="hidden" name="id" value="' + id + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }

        function editarMayorista(id, productoId, cantidadMinima, descuento) {
            const form = document.querySelector('#panelMayorista form');
            form.action.value = 'editar';
            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'id';
            idInput.value = id;
            form.appendChild(idInput);
            form.producto_id.value = productoId;
            form.cantidad_minima.value = cantidadMinima;
            form.descuento.value = descuento;
            document.querySelector('#panelMayorista h3').textContent = 'Editar Regla Mayorista';
        }
    </script>
</body>
</html>
