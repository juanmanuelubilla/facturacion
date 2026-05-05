<?php
require_once 'config.php';
requireLogin();

$user = getUser();
$empresa_id = $user['empresa_id'];

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    if ($accion === 'generar_codigo') {
        $producto_id = intval($_POST['producto_id'] ?? 0);
        
        require_once 'lib/PDFGenerator.php';
        
        try {
            $nuevo_codigo = PDFGenerator::generateAutoBarcode($empresa_id);
            query("UPDATE productos SET codigo_barra = ? WHERE id = ?", [$nuevo_codigo, $producto_id]);
            
            header('Location: etiquetas.php');
            exit;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
    
    if ($accion === 'generar_lote') {
        $producto_id = intval($_POST['producto_id'] ?? 0);
        $cantidad = intval($_POST['cantidad'] ?? 1);
        
        require_once 'lib/PDFGenerator.php';
        
        try {
            $html = PDFGenerator::generateBatchLabels($producto_id, $cantidad);
            
            // Guardar como archivo temporal
            $filename = 'etiquetas_' . date('YmdHis') . '.html';
            $filepath = '/tmp/' . $filename;
            file_put_contents($filepath, $html);
            
            header('Content-Type: text/html');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            readfile($filepath);
            unlink($filepath);
            exit;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Obtener productos para los selects
$productos = fetchAll("SELECT id, nombre, codigo, codigo_barra, precio FROM productos WHERE empresa_id = ? AND activo = 1 ORDER BY nombre", [$empresa_id]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🏷️ Etiquetas y Códigos de Barras - NEXUS POS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-gray-900 to-gray-800 text-gray-100">
    <header class="bg-gray-900 shadow-lg">
        <div class="container mx-auto px-6 py-4 flex justify-between items-center">
            <h1 class="text-2xl font-bold text-blue-400">🏷️ ETIQUETAS</h1>
            <a href="dashboard.php" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded">VOLVER</a>
        </div>
    </header>
    <main class="container mx-auto px-6 py-8">
        <!-- Tabs -->
        <div class="flex gap-2 mb-6">
            <button onclick="showTab('generar')" id="tabGenerar" class="px-6 py-3 rounded font-bold bg-blue-600 text-white">🏷️ GENERAR CÓDIGOS</button>
            <button onclick="showTab('imprimir')" id="tabImprimir" class="px-6 py-3 rounded font-bold bg-gray-700 text-white">🖨️ IMPRIMIR ETIQUETAS</button>
        </div>
        
        <!-- Tab Generar Códigos -->
        <div id="panelGenerar" class="bg-gray-800 rounded-lg border border-gray-700 p-6">
            <h3 class="text-xl font-bold text-white mb-4">Generar Código de Barras Automático</h3>
            <form method="POST">
                <input type="hidden" name="accion" value="generar_codigo">
                <div class="space-y-4">
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Seleccionar Producto</label>
                        <select name="producto_id" required class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                            <option value="">-- Seleccionar --</option>
                            <?php foreach ($productos as $producto): ?>
                            <option value="<?= $producto['id'] ?>">
                                <?= htmlspecialchars($producto['nombre']) ?> (<?= $producto['codigo'] ?>)<?= $producto['codigo_barra'] ? ' - Código: ' . $producto['codigo_barra'] : ' - Sin código' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 rounded font-bold">
                        🏷️ GENERAR CÓDIGO DE BARRAS
                    </button>
                </div>
            </form>
            
            <?php if (isset($error)): ?>
            <div class="mt-4 bg-red-600 text-white p-3 rounded">
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Tab Imprimir Etiquetas -->
        <div id="panelImprimir" class="hidden bg-gray-800 rounded-lg border border-gray-700 p-6">
            <h3 class="text-xl font-bold text-white mb-4">Imprimir Etiquetas en Lote</h3>
            <form method="POST">
                <input type="hidden" name="accion" value="generar_lote">
                <div class="space-y-4">
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Seleccionar Producto</label>
                        <select name="producto_id" required class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                            <option value="">-- Seleccionar --</option>
                            <?php foreach ($productos as $producto): ?>
                            <option value="<?= $producto['id'] ?>">
                                <?= htmlspecialchars($producto['nombre']) ?> (<?= $producto['codigo'] ?>) - $<?= number_format($producto['precio'], 2) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Cantidad de Etiquetas</label>
                        <input type="number" name="cantidad" value="1" min="1" max="100" required
                               class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Tamaño de Etiqueta</label>
                        <select name="tamano" class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                            <option value="50x30">50mm x 30mm (Estándar)</option>
                            <option value="100x50">100mm x 50mm (Grande)</option>
                            <option value="30x20">30mm x 20mm (Pequeña)</option>
                        </select>
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="checkbox" name="show_price" checked class="w-4 h-4">
                        <label class="text-gray-400 text-sm">Mostrar Precio</label>
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="checkbox" name="show_code" checked class="w-4 h-4">
                        <label class="text-gray-400 text-sm">Mostrar Código de Barras</label>
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="checkbox" name="show_name" checked class="w-4 h-4">
                        <label class="text-gray-400 text-sm">Mostrar Nombre</label>
                    </div>
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 rounded font-bold">
                        🖨️ GENERAR ETIQUETAS
                    </button>
                </div>
            </form>
            
            <div class="mt-8">
                <h3 class="text-xl font-bold text-white mb-4">Productos con Código de Barras</h3>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-700">
                                <th class="text-left text-gray-400 py-2">Código</th>
                                <th class="text-left text-gray-400 py-2">Nombre</th>
                                <th class="text-left text-gray-400 py-2">Código de Barras</th>
                                <th class="text-left text-gray-400 py-2">Precio</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($productos as $producto): ?>
                            <tr class="border-b border-gray-700">
                                <td class="py-2"><?= htmlspecialchars($producto['codigo']) ?></td>
                                <td class="py-2"><?= htmlspecialchars($producto['nombre']) ?></td>
                                <td class="py-2">
                                    <?= !empty($producto['codigo_barra']) 
                                        ? '<span class="text-green-400">' . htmlspecialchars($producto['codigo_barra']) . '</span>' 
                                        : '<span class="text-red-400">Sin código</span>' ?>
                                </td>
                                <td class="py-2">$<?= number_format($producto['precio'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
    
    <script>
        function showTab(tab) {
            document.getElementById('panelGenerar').classList.add('hidden');
            document.getElementById('panelImprimir').classList.add('hidden');
            
            document.getElementById('tabGenerar').classList.remove('bg-blue-600');
            document.getElementById('tabGenerar').classList.add('bg-gray-700');
            document.getElementById('tabImprimir').classList.remove('bg-blue-600');
            document.getElementById('tabImprimir').classList.add('bg-gray-700');
            
            document.getElementById('panel' + tab.charAt(0).toUpperCase() + tab.slice(1)).classList.remove('hidden');
            document.getElementById('tab' + tab.charAt(0).toUpperCase() + tab.slice(1)).classList.remove('bg-gray-700');
            document.getElementById('tab' + tab.charAt(0).toUpperCase() + tab.slice(1)).classList.add('bg-blue-600');
        }
    </script>
</body>
</html>
