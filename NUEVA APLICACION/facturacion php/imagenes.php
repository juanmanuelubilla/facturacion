<?php
require_once 'config.php';
requireLogin();

$user = getUser();
$empresa_id = $user['empresa_id'];

// Cargar productos
$productos = fetchAll("SELECT id, nombre, precio FROM productos WHERE empresa_id = ? AND activo = 1", [$empresa_id]);

// Cargar proveedores IA (de la empresa + globales como en Python)
// Los de la empresa tienen prioridad (aparecen primero)
$proveedores_ia = fetchAll("SELECT id, nombre, url_api, url_web, api_key FROM proveedores_ia WHERE (empresa_id = ? OR empresa_id IS NULL) AND activo = 1 ORDER BY (empresa_id = ?) DESC, nombre", [$empresa_id, $empresa_id]);

// Config del negocio para ruta de imágenes y proveedor IA
$config = fetch("SELECT ruta_imagenes, ia_proveedor, ia_ruta_imagenes FROM nombre_negocio WHERE empresa_id = ? OR id = 1 ORDER BY (empresa_id = ?) DESC LIMIT 1", [$empresa_id, $empresa_id]);
$ruta_imagenes = $config['ruta_imagenes'] ?? '/facturacion/imagenes';
$ia_ruta = $config['ia_ruta_imagenes'] ?? './imagenes_generadas';
$ia_proveedor_default = $config['ia_proveedor'] ?? '';

// Buscar el ID del proveedor por defecto
$proveedor_default_id = '';
if (!empty($ia_proveedor_default)) {
    foreach ($proveedores_ia as $prov) {
        if ($prov['nombre'] === $ia_proveedor_default) {
            $proveedor_default_id = $prov['id'];
            break;
        }
    }
}

// Si no hay proveedor por defecto o no existe, usar el primero
if (empty($proveedor_default_id) && !empty($proveedores_ia)) {
    $proveedor_default_id = $proveedores_ia[0]['id'];
}

// Obtener datos del proveedor seleccionado para usar en JS
$proveedor_seleccionado = null;
foreach ($proveedores_ia as $prov) {
    if ($prov['id'] == $proveedor_default_id) {
        $proveedor_seleccionado = $prov;
        break;
    }
}

// Debug: mostrar qué proveedor se encontró
error_log("Proveedor por defecto desde config: '$ia_proveedor_default'");
error_log("Proveedor por defecto ID: $proveedor_default_id");
error_log("Proveedor seleccionado: " . json_encode($proveedor_seleccionado));

// Cargar promociones activas
$promociones = [];
$cupones = fetchAll("SELECT id, codigo_qr, descuento_porcentaje FROM cupones WHERE empresa_id = ? AND activo = 1", [$empresa_id]);
foreach ($cupones as $c) {
    $promociones[] = ['id' => $c['id'], 'tipo' => 'CUPÓN', 'texto' => "CUPÓN: {$c['codigo_qr']} - {$c['descuento_porcentaje']}% OFF"];
}
$combos = fetchAll("SELECT id, nombre_promo, descuento_porcentaje FROM promociones_combos WHERE empresa_id = ? AND activo = 1", [$empresa_id]);
foreach ($combos as $c) {
    $promociones[] = ['id' => $c['id'], 'tipo' => 'COMBO', 'texto' => "COMBO: {$c['nombre_promo']} - {$c['descuento_porcentaje']}% OFF"];
}
$mayoristas = fetchAll("SELECT v.id, p.nombre, v.cantidad_minima, v.descuento_porcentaje FROM promociones_volumen v JOIN productos p ON v.producto_id = p.id WHERE v.empresa_id = ? AND v.activo = 1", [$empresa_id]);
foreach ($mayoristas as $m) {
    $promociones[] = ['id' => $m['id'], 'tipo' => 'MAYORISTA', 'texto' => "MAYORISTA: {$m['nombre']} - {$m['cantidad_minima']}+ unidades {$m['descuento_porcentaje']}% OFF"];
}
?>

<?php
// Procesar formulario POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    if ($accion === 'guardar_imagen_generada') {
        require_once 'lib/ImageProcessor.php';
        require_once 'lib/empresa_files.php';
        
        $empresa_files = new EmpresaFiles($empresa_id);
        $upload_dir = $empresa_files->getImagenesGeneradasPathAbsoluta();
        
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
            $processor = new ImageProcessor();
            $filename = $processor->generateUniqueFilename('imagen_generada_' . date('Y-m-d_H-i-s') . '.png');
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['imagen']['tmp_name'], $filepath)) {
                $result = $processor->processImage($filepath, $filepath, false);
                
                if ($result['success']) {
                    $ruta_relativa = $empresa_files->getImagenesGeneradasPath() . $filename;
                    echo json_encode([
                        'success' => true,
                        'ruta' => $ruta_relativa,
                        'mensaje' => 'Imagen guardada exitosamente'
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'error' => 'Error al procesar la imagen'
                    ]);
                }
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Error al subir la imagen'
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'No se recibió ninguna imagen'
            ]);
        }
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imágenes IA - NEXUS POS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-gray-900 to-gray-800 text-gray-100">
    <header class="bg-gray-900 shadow-lg">
        <div class="container mx-auto px-6 py-4 flex justify-between items-center">
            <h1 class="text-2xl font-bold text-red-400">🎨 GENERADOR DE IMÁGENES IA</h1>
            <a href="dashboard.php" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded">VOLVER</a>
        </div>
    </header>
    <main class="container mx-auto px-6 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Panel de Configuración -->
            <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
                <h3 class="text-xl font-bold text-white mb-4">Configuración de Generación</h3>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Producto</label>
                        <select id="producto" class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                            <option value="">Seleccionar producto...</option>
                            <?php foreach ($productos as $p): ?>
                            <option value="<?= $p['id'] ?>" data-nombre="<?= htmlspecialchars($p['nombre']) ?>" data-precio="<?= $p['precio'] ?>"><?= htmlspecialchars($p['nombre']) ?> - $<?= $p['precio'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Promoción Activa</label>
                        <select id="promocion" class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                            <option value="">Seleccionar promoción...</option>
                            <?php foreach ($promociones as $promo): ?>
                            <option value="<?= $promo['id'] ?>" data-tipo="<?= $promo['tipo'] ?>" data-texto="<?= htmlspecialchars($promo['texto']) ?>"><?= htmlspecialchars($promo['texto']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Tipo de Contenido</label>
                        <div class="flex gap-4">
                            <label class="flex items-center gap-2">
                                <input type="radio" name="tipo_contenido" value="imagen" checked class="w-4 h-4">
                                <span class="text-gray-400 text-sm">🖼️ Imagen</span>
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="radio" name="tipo_contenido" value="video" class="w-4 h-4">
                                <span class="text-gray-400 text-sm">🎥 Video</span>
                            </label>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Estilo</label>
                        <select id="estilo" class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                            <option value="Modern">Modern</option>
                            <option value="Vintage">Vintage</option>
                            <option value="Minimalist">Minimalist</option>
                            <option value="Bold">Bold</option>
                            <option value="Elegant">Elegant</option>
                            <option value="Playful">Playful</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Tamaño</label>
                        <select id="tamaño" class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                            <option value="1080x1080">Cuadrado (1080x1080)</option>
                            <option value="1920x1080">Horizontal (1920x1080)</option>
                            <option value="1080x1920">Vertical (1080x1920)</option>
                            <option value="1200x630">Facebook Ad (1200x630)</option>
                            <option value="800x800">Pequeño (800x800)</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Proveedor IA</label>
                        <select id="proveedor" class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600" onchange="actualizarProveedorSeleccionado()">
                            <?php if (empty($proveedores_ia)): ?>
                            <option value="">No hay proveedores configurados</option>
                            <?php else: ?>
                            <?php foreach ($proveedores_ia as $prov): ?>
                            <option value="<?= $prov['id'] ?>"
                                    data-nombre="<?= htmlspecialchars($prov['nombre']) ?>"
                                    data-url-api="<?= htmlspecialchars($prov['url_api'] ?? '') ?>"
                                    data-url-web="<?= htmlspecialchars($prov['url_web'] ?? '') ?>"
                                    data-api-key="<?= htmlspecialchars($prov['api_key'] ?? '') ?>"
                                    <?= $prov['id'] == $proveedor_default_id ? 'selected' : '' ?>>
                                <?= htmlspecialchars($prov['nombre']) ?>
                            </option>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <input type="hidden" id="ia_ruta" value="<?= htmlspecialchars($ia_ruta) ?>">
                    </div>
                    
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Prompt Personalizado (opcional)</label>
                        <textarea id="prompt" rows="3"
                                  class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600"
                                  placeholder="Descripción adicional para la imagen..."></textarea>
                    </div>
                    
                    <button onclick="generarPrompt()" class="w-full bg-purple-600 hover:bg-purple-700 text-white py-3 rounded font-bold mb-2">
                        🎨 GENERAR PROMPT
                    </button>
                    <button onclick="copiarPrompt()" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 rounded font-bold mb-2">
                        📋 COPIAR PROMPT
                    </button>
                    <button onclick="abrirIAExterna()" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-3 rounded font-bold mb-2">
                        🌐 ABRIR IA EXTERNA
                    </button>
                    <button onclick="enviarABanners()" class="w-full bg-orange-600 hover:bg-orange-700 text-white py-3 rounded font-bold">
                        📤 ENVIAR A BANNERS
                    </button>
                </div>
            </div>
            <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
                <h3 class="text-xl font-bold text-white mb-4">Vista Previa</h3>
                <div id="previewContainer" class="bg-gray-900 p-4 rounded mb-4 min-h-64 text-center text-gray-500 flex items-center justify-center">
                    <div id="previewPlaceholder">🖼️<br>La imagen aparecerá aquí</div>
                    <img id="previewImagen" class="hidden max-w-full max-h-64 object-contain" alt="Vista previa">
                </div>
                <button onclick="cargarImagen()" class="w-full bg-purple-600 hover:bg-purple-700 text-white py-3 rounded mb-2">📥 CARGAR IMAGEN</button>
                <button onclick="guardarImagen()" class="w-full bg-green-600 hover:bg-green-700 text-white py-3 rounded">💾 GUARDAR</button>
            </div>
        </div>
    </main>

    <script>
        // Configuración del proveedor actual (inicializado desde PHP)
        let proveedorActual = {
            id: <?= json_encode($proveedor_default_id) ?>,
            nombre: <?= json_encode($proveedor_seleccionado['nombre'] ?? '') ?>,
            url_api: <?= json_encode($proveedor_seleccionado['url_api'] ?? '') ?>,
            url_web: <?= json_encode($proveedor_seleccionado['url_web'] ?? '') ?>,
            api_key: <?= json_encode($proveedor_seleccionado['api_key'] ?? '') ?>,
            ruta: document.getElementById('ia_ruta')?.value || './imagenes_generadas'
        };

        console.log('Proveedor inicial cargado:', proveedorActual);
        console.log('ID por defecto:', <?= json_encode($proveedor_default_id) ?>);
        console.log('Nombre config:', <?= json_encode($ia_proveedor_default) ?>);

        function actualizarProveedorSeleccionado() {
            const select = document.getElementById('proveedor');
            if (!select || select.selectedIndex < 0) {
                console.log('No hay proveedor seleccionado');
                return;
            }
            const option = select.options[select.selectedIndex];

            proveedorActual = {
                id: option.value,
                nombre: option.dataset.nombre || '',
                url_api: option.dataset.urlApi || '',
                url_web: option.dataset.urlWeb || '',
                api_key: option.dataset.apiKey || '',
                ruta: document.getElementById('ia_ruta')?.value || './imagenes_generadas'
            };

            console.log('Proveedor actualizado:', proveedorActual);
        }

        function generarPrompt() {
            const productoSelect = document.getElementById('producto');
            const promocionSelect = document.getElementById('promocion');
            const estiloSelect = document.getElementById('estilo');
            const tipoContenido = document.querySelector('input[name="tipo_contenido"]:checked').value;

            const productoOption = productoSelect.options[productoSelect.selectedIndex];
            const promocionOption = promocionSelect.options[promocionSelect.selectedIndex];

            let prompt = '';

            if (tipoContenido === 'imagen') {
                prompt = `Crea una imagen promocional profesional para el producto "${productoOption.text}". `;
            } else {
                prompt = `Crea un video promocional animado para el producto "${productoOption.text}". `;
            }

            if (promocionOption.value) {
                prompt += `Destaca la promoción: ${promocionOption.dataset.texto}. `;
            }

            prompt += `Estilo visual: ${estiloSelect.value}. `;
            prompt += `La imagen debe ser atractiva, llamativa y profesional para uso en marketing digital y redes sociales. `;
            prompt += `Incluye el producto de forma destacada con buena iluminación y composición. `;

            document.getElementById('prompt').value = prompt;
        }

        function copiarPrompt() {
            const promptTextarea = document.getElementById('prompt');
            if (!promptTextarea.value.trim()) {
                alert('Primero genera un prompt');
                return;
            }

            promptTextarea.select();
            document.execCommand('copy');

            // Deseleccionar
            window.getSelection().removeAllRanges();

            alert('Prompt copiado al portapapeles');
        }

        function abrirIAExterna() {
            console.log('=== ABRIR IA EXTERNA ===');
            console.log('Proveedor actual:', proveedorActual);
            console.log('URL web:', proveedorActual.url_web);
            
            if (!proveedorActual || !proveedorActual.url_web) {
                alert('No hay URL web configurada para el proveedor seleccionado');
                console.log('Error: No hay proveedor o URL web');
                return;
            }

            // Copiar prompt primero si existe
            const prompt = document.getElementById('prompt').value;
            console.log('Prompt a copiar:', prompt);
            
            if (prompt) {
                navigator.clipboard.writeText(prompt).then(() => {
                    console.log('Prompt copiado exitosamente');
                    console.log('Abriendo URL:', proveedorActual.url_web);
                    window.open(proveedorActual.url_web, '_blank');
                    alert('Prompt copiado. Se abrirá el sitio de IA externa.');
                }).catch((err) => {
                    console.error('Error copiando prompt:', err);
                    console.log('Abriendo URL directamente:', proveedorActual.url_web);
                    window.open(proveedorActual.url_web, '_blank');
                });
            } else {
                console.log('No hay prompt, abriendo directamente');
                window.open(proveedorActual.url_web, '_blank');
            }
        }

        function enviarABanners() {
            const productoSelect = document.getElementById('producto');
            const prompt = document.getElementById('prompt').value;
            const img = document.getElementById('previewImagen');

            if (!productoSelect.value) {
                alert('Selecciona un producto primero');
                return;
            }

            const productoNombre = productoSelect.options[productoSelect.selectedIndex].dataset.nombre;

            // Verificar si hay una imagen en el preview
            let imagenData = '';
            if (!img.classList.contains('hidden') && img.src) {
                // Si la imagen viene de un archivo local (data:), usarla directamente
                if (img.src.startsWith('data:')) {
                    imagenData = img.src;
                } else {
                    // Si es una URL, guardarla primero
                    imagenData = img.src;
                }
            }

            // Redirigir a banners.php con los datos
            const params = new URLSearchParams({
                from: 'imagenes',
                producto_id: productoSelect.value,
                producto_nombre: productoNombre,
                prompt: prompt || '',
                imagen: imagenData || ''
            });

            window.location.href = 'banners.php?' + params.toString();
        }

        function cargarImagen() {
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = 'image/*';
            input.onchange = function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(event) {
                        const img = document.getElementById('previewImagen');
                        const placeholder = document.getElementById('previewPlaceholder');

                        img.src = event.target.result;
                        img.classList.remove('hidden');
                        placeholder.classList.add('hidden');
                    };
                    reader.readAsDataURL(file);
                }
            };
            input.click();
        }

        function guardarImagen() {
            const img = document.getElementById('previewImagen');
            if (img.classList.contains('hidden') || !img.src) {
                alert('No hay imagen para guardar');
                return;
            }

            // Mostrar loading
            const btn = event.target;
            const textoOriginal = btn.textContent;
            btn.textContent = 'Guardando...';
            btn.disabled = true;

            // Convertir la imagen de data URL a blob
            fetch(img.src)
                .then(res => res.blob())
                .then(blob => {
                    // Crear FormData para enviar la imagen
                    const formData = new FormData();
                    formData.append('imagen', blob, 'imagen_generada.png');
                    formData.append('accion', 'guardar_imagen_generada');

                    // Enviar al servidor para guardar
                    fetch('imagenes.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Imagen guardada exitosamente en: ' + data.ruta);
                            // Opcional: mostrar la imagen guardada en el preview
                            img.src = data.ruta + '?t=' + Date.now();
                        } else {
                            alert('Error al guardar imagen: ' + (data.error || 'Error desconocido'));
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error al guardar imagen: ' + error.message);
                    })
                    .finally(() => {
                        // Restaurar botón
                        btn.textContent = textoOriginal;
                        btn.disabled = false;
                    });
                })
                .catch(error => {
                    console.error('Error convirtiendo imagen:', error);
                    alert('Error al procesar la imagen: ' + error.message);
                    btn.textContent = textoOriginal;
                    btn.disabled = false;
                });
        }

        // Inicializar
        document.addEventListener('DOMContentLoaded', function() {
            console.log('=== Inicialización de Generador de Imágenes ===');
            console.log('Proveedor inicial desde PHP:', proveedorActual);

            const select = document.getElementById('proveedor');
            if (select) {
                console.log('Select de proveedor encontrado');
                console.log('Índice seleccionado:', select.selectedIndex);
                console.log('Opción seleccionada:', select.options[select.selectedIndex]?.text);
                console.log('Data attributes:', select.options[select.selectedIndex]?.dataset);
            } else {
                console.error('No se encontró el select de proveedor');
            }

            actualizarProveedorSeleccionado();
        });
    </script>
</body>
</html>
