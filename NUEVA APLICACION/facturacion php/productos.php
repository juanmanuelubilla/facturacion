<?php
require_once 'config.php';
requireLogin();

$user = getUser();
$empresa_id = $user['empresa_id'];

// Cargar configuración fiscal
$config_fiscal = fetch("SELECT impuesto, ingresos_brutos, ganancia_sugerida FROM nombre_negocio WHERE empresa_id = ? OR id = 1 ORDER BY (empresa_id = ?) DESC LIMIT 1", [$empresa_id, $empresa_id]);
$recargo_total = ($config_fiscal['impuesto'] ?? 0) + ($config_fiscal['ingresos_brutos'] ?? 0) + ($config_fiscal['ganancia_sugerida'] ?? 0);

// Cargar categorías
$categorias = fetchAll("SELECT id, nombre FROM categorias WHERE empresa_id = ? ORDER BY nombre", [$empresa_id]);

// Cargar tags únicos
$tags_unicos = fetchAll("SELECT DISTINCT tags FROM productos WHERE empresa_id = ? AND tags IS NOT NULL AND tags != ''", [$empresa_id]);
$tags_lista = [];
foreach ($tags_unicos as $t) {
    $tags_producto = explode(',', $t['tags']);
    foreach ($tags_producto as $tag) {
        $tag = trim($tag);
        if ($tag && !in_array($tag, $tags_lista)) {
            $tags_lista[] = $tag;
        }
    }
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $id = $_POST['id'] ?? null;
    $codigo = trim($_POST['codigo'] ?? '');
    $codigo_barra = trim($_POST['codigo_barra'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $precio = floatval($_POST['precio'] ?? 0);
    $stock = floatval($_POST['stock'] ?? 0);
    $categoria_id = $_POST['categoria_id'] ?? null;
    if ($categoria_id === '') $categoria_id = null;
    $tags = trim($_POST['tags'] ?? '');
    $costo = floatval($_POST['costo'] ?? 0);
    $venta_por_peso = isset($_POST['venta_por_peso']) ? 1 : 0;
    $activo = isset($_POST['activo']) ? 1 : 0;
    $imagen_ruta = $_POST['imagen_ruta'] ?? '';

    // Procesar subida de imagen
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        require_once 'lib/ImageProcessor.php';
        require_once 'lib/empresa_files.php';
        
        $user = getUser();
        $empresa_id = $user['empresa_id'];
        
        // Obtener ruta de la empresa
        $empresa_files = new EmpresaFiles($empresa_id);
        $empresa_path = $empresa_files->getEmpresaPathAbsoluta();
        $upload_dir = $empresa_path . 'productos/';
        
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Procesar imagen con ImageProcessor
        $processor = new ImageProcessor();
        
        // Generar nombre único con fecha/hora
        $filename = $processor->generateUniqueFilename($_FILES['imagen']['name']);
        $filepath = $upload_dir . $filename;
        
        // Mover archivo temporal
        if (move_uploaded_file($_FILES['imagen']['tmp_name'], $filepath)) {
            $result = $processor->processImage($filepath, $filepath, true);
            
            if ($result['success']) {
                $imagen_ruta = $empresa_files->getEmpresaPath() . 'productos/' . $filename;
            } else {
                $imagen_ruta = $empresa_files->getEmpresaPath() . 'productos/' . $filename;
            }
        }
    }

    if ($accion === 'guardar') {
        if ($id) {
            query("UPDATE productos SET codigo=?, codigo_barra=?, nombre=?, descripcion=?, precio=?, stock=?, categoria_id=?, tags=?, costo=?, venta_por_peso=?, activo=?, imagen=? WHERE id=? AND empresa_id=?",
                  [$codigo, $codigo_barra, $nombre, $descripcion, $precio, $stock, $categoria_id, $tags, $costo, $venta_por_peso, $activo, $imagen_ruta, $id, $empresa_id]);
        } else {
            query("INSERT INTO productos (codigo, codigo_barra, nombre, descripcion, precio, stock, categoria_id, tags, costo, venta_por_peso, empresa_id, activo, imagen) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                  [$codigo, $codigo_barra, $nombre, $descripcion, $precio, $stock, $categoria_id, $tags, $costo, $venta_por_peso, $empresa_id, $activo, $imagen_ruta]);
        }
        header('Location: productos.php');
        exit;
    }
    
    if ($accion === 'eliminar' && $id) {
        query("UPDATE productos SET activo=0 WHERE id=? AND empresa_id=?", [$id, $empresa_id]);
        header('Location: productos.php');
        exit;
    }
    
    if ($accion === 'crear_categoria' && !empty($_POST['nueva_categoria'])) {
        query("INSERT INTO categorias (nombre, empresa_id) VALUES (?, ?)", [trim($_POST['nueva_categoria']), $empresa_id]);
        header('Location: productos.php');
        exit;
    }
}

$productos = fetchAll("SELECT p.*, c.nombre as categoria FROM productos p LEFT JOIN categorias c ON p.categoria_id = c.id WHERE p.empresa_id = ? ORDER BY p.nombre", [$empresa_id]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productos - NEXUS POS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #1f2937;
        }
        ::-webkit-scrollbar-thumb {
            background: #4b5563;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #6b7280;
        }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-gray-900 to-gray-800 text-gray-100">
    <header class="bg-gray-900 shadow-lg">
        <div class="container mx-auto px-6 py-4 flex justify-between items-center">
            <h1 class="text-2xl font-bold text-blue-400">📦 PRODUCTOS</h1>
            <a href="dashboard.php" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded">VOLVER</a>
        </div>
    </header>
    <main class="container mx-auto px-6 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Formulario -->
            <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
                <div class="text-green-400 text-sm mb-2">Recargo: +<?= number_format($recargo_total, 2) ?>%</div>
                <h3 class="text-xl font-bold text-white mb-4" id="formTitle">Nuevo Producto</h3>
                <form method="POST" id="productoForm" enctype="multipart/form-data">
                    <input type="hidden" name="accion" value="guardar">
                    <input type="hidden" name="id" id="productoId" value="">

                    <div class="space-y-3">
                        <div>
                            <label class="block text-gray-400 text-sm mb-1">Código</label>
                            <input type="text" name="codigo" id="codigo"
                                   class="w-full bg-gray-700 text-white p-2 rounded border border-gray-600">
                        </div>
                        <div>
                            <label class="block text-gray-400 text-sm mb-1">Código de Barras</label>
                            <input type="text" name="codigo_barra" id="codigo_barra"
                                   class="w-full bg-gray-700 text-white p-2 rounded border border-gray-600"
                                   placeholder="EAN-13 o dejar vacío para generar automáticamente">
                        </div>
                        <div>
                            <label class="block text-gray-400 text-sm mb-1">Nombre</label>
                            <input type="text" name="nombre" id="nombre" required
                                   class="w-full bg-gray-700 text-white p-2 rounded border border-gray-600">
                        </div>
                        <div>
                            <label class="block text-gray-400 text-sm mb-1">Descripción</label>
                            <textarea name="descripcion" id="descripcion" rows="2"
                                      class="w-full bg-gray-700 text-white p-2 rounded border border-gray-600"></textarea>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-gray-400 text-sm mb-1">Precio</label>
                                <input type="number" step="0.01" name="precio" id="precio" required
                                       class="w-full bg-gray-700 text-white p-2 rounded border border-gray-600">
                            </div>
                            <div>
                                <label class="block text-gray-400 text-sm mb-1">Costo</label>
                                <input type="number" step="0.01" name="costo" id="costo"
                                       class="w-full bg-gray-700 text-white p-2 rounded border border-gray-600">
                            </div>
                        </div>
                        <div>
                            <label class="block text-gray-400 text-sm mb-1">Stock</label>
                            <input type="number" step="0.001" name="stock" id="stock" required
                                   class="w-full bg-gray-700 text-white p-2 rounded border border-gray-600">
                        </div>
                        <div>
                            <label class="block text-gray-400 text-sm mb-1">Categoría</label>
                            <select name="categoria_id" id="categoria_id"
                                    class="w-full bg-gray-700 text-white p-2 rounded border border-gray-600">
                                <option value="">Sin categoría</option>
                                <?php foreach ($categorias as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-gray-400 text-sm mb-1">Tags</label>
                            <input type="text" name="tags" id="tags"
                                   class="w-full bg-gray-700 text-white p-2 rounded border border-gray-600"
                                   placeholder="Separados por coma">
                        </div>
                        <div>
                            <label class="block text-gray-400 text-sm mb-1">Imagen del Producto</label>
                            <input type="file" name="imagen" id="imagen" accept="image/*"
                                   class="w-full bg-gray-700 text-white p-2 rounded border border-gray-600"
                                   onchange="previewImagen()">
                            <input type="hidden" name="imagen_ruta" id="imagen_ruta" value="">
                            <div id="imagenPreview" class="mt-2 hidden">
                                <img id="previewImg" src="" alt="Preview" class="w-32 h-32 object-cover rounded">
                                <button type="button" onclick="eliminarImagen()" class="mt-2 w-full bg-red-600 hover:bg-red-700 text-white py-3 rounded font-bold">🗑️ ELIMINAR IMAGEN</button>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="checkbox" name="venta_por_peso" id="venta_por_peso" class="w-4 h-4">
                            <label class="text-gray-400 text-sm">Venta por peso</label>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="checkbox" name="activo" id="activo" class="w-4 h-4" checked>
                            <label class="text-gray-400 text-sm">Producto Activo</label>
                        </div>
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 rounded font-bold">
                            💾 GUARDAR PRODUCTO
                        </button>
                        <button type="button" onclick="limpiarFormulario()" class="w-full bg-gray-600 hover:bg-gray-700 text-white py-2 rounded">
                            🧹 LIMPIAR
                        </button>
                    </div>
                </form>
                
                <!-- Crear categoría -->
                <div class="mt-4 pt-4 border-t border-gray-700">
                    <h4 class="text-white font-bold mb-2">Nueva Categoría</h4>
                    <form method="POST">
                        <input type="hidden" name="accion" value="crear_categoria">
                        <div class="flex gap-2">
                            <input type="text" name="nueva_categoria" required
                                   class="flex-1 bg-gray-700 text-white p-2 rounded border border-gray-600"
                                   placeholder="Nombre categoría">
                            <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded">
                                🔍 FILTRAR
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Tabla -->
            <div class="lg:col-span-2 bg-gray-800 rounded-lg border border-gray-700 p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold text-white">Inventario</h3>
                    <div class="flex gap-2">
                        <select id="filtroCategoria" onchange="filtrarProductos()"
                                class="bg-gray-700 text-white p-2 rounded border border-gray-600">
                            <option value="">Todas las categorías</option>
                            <?php foreach ($categorias as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select id="filtroTag" onchange="filtrarProductos()"
                                class="bg-gray-700 text-white p-2 rounded border border-gray-600">
                            <option value="">Todos los tags</option>
                            <?php foreach ($tags_lista as $t): ?>
                            <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select id="filtroEstado" onchange="filtrarProductos()"
                                class="bg-gray-700 text-white p-2 rounded border border-gray-600">
                            <option value="">Todos los estados</option>
                            <option value="1">Activo</option>
                            <option value="0">Inactivo</option>
                        </select>
                        <input type="text" placeholder="Buscar..." id="buscador"
                               class="bg-gray-700 text-white p-2 rounded border border-gray-600"
                               onkeyup="filtrarProductos()">
                        <button onclick="limpiarFiltros()" class="bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded">🧹 LIMPIAR</button>
                    </div>
                </div>
                <div class="overflow-x-auto flex-1 overflow-y-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-700 sticky top-0 bg-gray-800">
                                <th class="text-left py-2 px-3 text-gray-400">Imagen</th>
                                <th class="text-left py-2 px-3 text-gray-400 cursor-pointer hover:text-white" onclick="ordenarTabla(1)">Código ⬍</th>
                                <th class="text-left py-2 px-3 text-gray-400 cursor-pointer hover:text-white" onclick="ordenarTabla(2)">Código Barras ⬍</th>
                                <th class="text-left py-2 px-3 text-gray-400 cursor-pointer hover:text-white" onclick="ordenarTabla(3)">Nombre ⬍</th>
                                <th class="text-left py-2 px-3 text-gray-400 cursor-pointer hover:text-white" onclick="ordenarTabla(4)">Costo ⬍</th>
                                <th class="text-left py-2 px-3 text-gray-400 cursor-pointer hover:text-white" onclick="ordenarTabla(5)">Precio ⬍</th>
                                <th class="text-left py-2 px-3 text-gray-400 cursor-pointer hover:text-white" onclick="ordenarTabla(6)">Stock ⬍</th>
                                <th class="text-left py-2 px-3 text-gray-400 cursor-pointer hover:text-white" onclick="ordenarTabla(7)">Categoría ⬍</th>
                                <th class="text-left py-2 px-3 text-gray-400 cursor-pointer hover:text-white" onclick="ordenarTabla(8)">Tags ⬍</th>
                                <th class="text-left py-2 px-3 text-gray-400 cursor-pointer hover:text-white" onclick="ordenarTabla(9)">Estado ⬍</th>
                                <th class="text-left py-2 px-3 text-gray-400">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tablaProductos">
                            <?php foreach ($productos as $p): ?>
                            <tr class="border-b border-gray-700 hover:bg-gray-700 producto-row"
                                data-nombre="<?= strtolower(htmlspecialchars($p['nombre'])) ?>"
                                data-categoria="<?= $p['categoria_id'] ?? '' ?>"
                                data-tags="<?= strtolower(htmlspecialchars($p['tags'] ?? '')) ?>"
                                data-activo="<?= $p['activo'] ?? 1 ?>">
                                <td class="py-2 px-3 text-sm">
                                    <?php if ($p['imagen']): ?>
                                    <img src="<?= htmlspecialchars($p['imagen']) ?>" alt="<?= htmlspecialchars($p['nombre']) ?>" class="w-12 h-12 object-cover rounded">
                                    <?php endif; ?>
                                </td>
                                <td class="py-2 px-3 text-sm"><?= htmlspecialchars($p['codigo']) ?></td>
                                <td class="py-2 px-3 text-sm">
                                    <?php if ($p['codigo_barra']): ?>
                                        <span class="text-blue-400"><?= htmlspecialchars($p['codigo_barra']) ?></span>
                                    <?php else: ?>
                                        <span class="text-gray-500">Sin código</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-2 px-3 text-sm"><?= htmlspecialchars($p['nombre']) ?></td>
                                <td class="py-2 px-3 text-sm text-yellow-400">$<?= number_format($p['costo'], 2) ?></td>
                                <td class="py-2 px-3 text-sm text-green-400">$<?= number_format($p['precio'], 2) ?></td>
                                <td class="py-2 px-3 text-sm"><?= number_format($p['stock'], $p['venta_por_peso'] ? 3 : 0) ?></td>
                                <td class="py-2 px-3 text-sm text-gray-400"><?= htmlspecialchars($p['categoria'] ?? '-') ?></td>
                                <td class="py-2 px-3 text-sm text-gray-400"><?= htmlspecialchars($p['tags'] ?? '-') ?></td>
                                <td class="py-2 px-3 text-sm">
                                    <span class="<?= $p['activo'] ? 'bg-green-600' : 'bg-red-600' ?> px-2 py-1 rounded text-xs">
                                        <?= $p['activo'] ? 'Activo' : 'Inactivo' ?>
                                    </span>
                                </td>
                                <td class="py-2 px-3 text-sm">
                                    <button onclick="editarProducto(<?= $p['id'] ?>, '<?= htmlspecialchars($p['codigo']) ?>', '<?= htmlspecialchars($p['nombre']) ?>', '<?= htmlspecialchars($p['descripcion']) ?>', <?= $p['precio'] ?>, <?= $p['stock'] ?>, <?= $p['categoria_id'] ?? 'null' ?>, '<?= htmlspecialchars($p['tags'] ?? '') ?>', <?= $p['costo'] ?>, <?= $p['venta_por_peso'] ?>, <?= $p['activo'] ?? 1 ?>, '<?= htmlspecialchars($p['imagen'] ?? '') ?>')"
                                            class="text-blue-400 hover:text-blue-300 mr-2">✏️ Editar</button>
                                    <button onclick="eliminarProducto(<?= $p['id'] ?>)"
                                            class="text-red-400 hover:text-red-300">🗑️ Eliminar</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script>
        const recargoTotal = <?= $recargo_total ?>;

        function calcularPrecioDinamico() {
            const costo = parseFloat(document.getElementById('costo').value.replace('$', '').trim());
            if (!isNaN(costo)) {
                const precioFinal = costo * (1 + (recargoTotal / 100));
                document.getElementById('precio').value = precioFinal.toFixed(2);
            }
        }

        document.getElementById('costo').addEventListener('input', calcularPrecioDinamico);

        function editarProducto(id, codigo, nombre, descripcion, precio, stock, categoriaId, tags, costo, ventaPorPeso, activo, imagenRuta) {
            document.getElementById('formTitle').textContent = 'Editar Producto';
            document.getElementById('productoId').value = id;
            document.getElementById('codigo').value = codigo;
            document.getElementById('nombre').value = nombre;
            document.getElementById('descripcion').value = descripcion;
            document.getElementById('precio').value = precio;
            document.getElementById('stock').value = stock;
            document.getElementById('categoria_id').value = categoriaId || '';
            document.getElementById('tags').value = tags;
            document.getElementById('costo').value = costo;
            document.getElementById('venta_por_peso').checked = ventaPorPeso;
            document.getElementById('activo').checked = activo;
            document.getElementById('imagen_ruta').value = imagenRuta || '';

            // Mostrar imagen existente si hay
            if (imagenRuta) {
                console.log('Imagen ruta:', imagenRuta);
                document.getElementById('imagenPreview').classList.remove('hidden');
                // Si la ruta es relativa, convertirla a absoluta
                const rutaAbsoluta = imagenRuta.startsWith('/') ? imagenRuta : '/' + imagenRuta;
                document.getElementById('previewImg').src = rutaAbsoluta;
            } else {
                console.log('Sin imagen');
                document.getElementById('imagenPreview').classList.add('hidden');
            }
        }

        function eliminarProducto(id) {
            if (confirm('¿Eliminar este producto?')) {
                const form = document.getElementById('productoForm');
                form.action.value = 'eliminar';
                form.id.value = id;
                form.submit();
            }
        }

        function limpiarFormulario() {
            document.getElementById('formTitle').textContent = 'Nuevo Producto';
            document.getElementById('productoId').value = '';
            document.getElementById('codigo').value = '';
            document.getElementById('nombre').value = '';
            document.getElementById('descripcion').value = '';
            document.getElementById('precio').value = '';
            document.getElementById('stock').value = '';
            document.getElementById('categoria_id').value = '';
            document.getElementById('tags').value = '';
            document.getElementById('costo').value = '';
            document.getElementById('venta_por_peso').checked = false;
            document.getElementById('activo').checked = true;
            document.getElementById('imagen_ruta').value = '';
            document.getElementById('imagen').value = '';
            document.getElementById('imagenPreview').classList.add('hidden');
        }

        function previewImagen() {
            const file = document.getElementById('imagen').files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('previewImg').src = e.target.result;
                    document.getElementById('imagenPreview').classList.remove('hidden');
                };
                reader.readAsDataURL(file);
            }
        }

        function eliminarImagen() {
            document.getElementById('imagen').value = '';
            document.getElementById('imagen_ruta').value = '';
            document.getElementById('imagenPreview').classList.add('hidden');
        }

        let ordenColumna = -1;
        let ordenAscendente = true;

        function ordenarTabla(columna) {
            const tabla = document.getElementById('tablaProductos');
            const filas = Array.from(tabla.querySelectorAll('tr'));

            // Si es la misma columna, invertir el orden
            if (ordenColumna === columna) {
                ordenAscendente = !ordenAscendente;
            } else {
                ordenColumna = columna;
                ordenAscendente = true;
            }

            filas.sort((a, b) => {
                const celdaA = a.cells[columna].textContent.trim();
                const celdaB = b.cells[columna].textContent.trim();

                // Intentar ordenar como número
                const numA = parseFloat(celdaA.replace(/[$,]/g, ''));
                const numB = parseFloat(celdaB.replace(/[$,]/g, ''));

                if (!isNaN(numA) && !isNaN(numB)) {
                    return ordenAscendente ? numA - numB : numB - numA;
                }

                // Ordenar como texto
                return ordenAscendente ? celdaA.localeCompare(celdaB) : celdaB.localeCompare(celdaA);
            });

            // Reinsertar filas ordenadas
            filas.forEach(fila => tabla.appendChild(fila));

            // Actualizar indicadores de orden
            const headers = tabla.previousElementSibling.querySelectorAll('th');
            headers.forEach((header, index) => {
                if (index === columna) {
                    header.textContent = header.textContent.replace(/[⬍⬆]/, '') + (ordenAscendente ? ' ⬆' : ' ⬇');
                } else {
                    header.textContent = header.textContent.replace(/[⬍⬆⬇]/, ' ⬍');
                }
            });
        }

        function filtrarProductos() {
            const termino = document.getElementById('buscador').value.toLowerCase();
            const categoria = document.getElementById('filtroCategoria').value;
            const tag = document.getElementById('filtroTag').value.toLowerCase();
            const estado = document.getElementById('filtroEstado').value;
            const filas = document.querySelectorAll('.producto-row');

            filas.forEach(fila => {
                const nombre = fila.dataset.nombre;
                const filaCategoria = fila.dataset.categoria;
                const filaTags = fila.dataset.tags;
                const filaActivo = fila.dataset.activo;

                const coincideNombre = nombre.includes(termino);
                const coincideCategoria = !categoria || filaCategoria === categoria;
                const coincideTag = !tag || filaTags.includes(tag);
                const coincideEstado = !estado || filaActivo === estado;

                fila.style.display = (coincideNombre && coincideCategoria && coincideTag && coincideEstado) ? '' : 'none';
            });
        }

        function limpiarFiltros() {
            document.getElementById('buscador').value = '';
            document.getElementById('filtroCategoria').value = '';
            document.getElementById('filtroTag').value = '';
            document.getElementById('filtroEstado').value = '';
            filtrarProductos();
        }
    </script>
</body>
</html>
