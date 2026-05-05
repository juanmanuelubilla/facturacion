<?php
require_once 'config.php';
requireLogin();

$empresa_id = $_SESSION['empresa_id'] ?? 1;
$accion = $_GET['accion'] ?? 'listar';
$aviso_id = $_GET['id'] ?? null;

// Cargar proveedores IA (de la empresa + globales como en imagenes.php)
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

// Obtener datos del proveedor seleccionado
$proveedor_seleccionado = null;
foreach ($proveedores_ia as $prov) {
    if ($prov['id'] == $proveedor_default_id) {
        $proveedor_seleccionado = $prov;
        break;
    }
}

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_accion = $_POST['accion'] ?? 'guardar';
    
    if ($accion === 'crear' || $accion === 'editar') {
        $titulo = $_POST['titulo'] ?? '';
        $descripcion = $_POST['descripcion'] ?? '';
        $tipo_aviso = $_POST['tipo_aviso'] ?? 'otro';
        $cliente_id = !empty($_POST['cliente_id']) ? $_POST['cliente_id'] : null;
        $telefono = $_POST['telefono'] ?? '';
        $email = $_POST['email'] ?? '';
        $fecha_expiracion = !empty($_POST['fecha_expiracion']) ? $_POST['fecha_expiracion'] : null;
        $prompt_ia = $_POST['prompt_ia'] ?? '';
        $proveedor_id = $_POST['proveedor_ia'] ?? $proveedor_default_id;
        
        // Procesar imagen subida manualmente
        $imagen = '';
        if (!empty($_FILES['imagen']['tmp_name'])) {
            try {
                require_once 'lib/empresa_files.php';
                $empresa_files = new EmpresaFiles($empresa_id);
                
                $upload_dir = $empresa_files->getAvisosPathAbsoluta();
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Generar nombre único preservando extensión
                $original_name = $_FILES['imagen']['name'];
                $extension = pathinfo($original_name, PATHINFO_EXTENSION);
                $extension = strtolower($extension) ?: 'jpg';
                $filename = 'aviso_' . date('Y-m-d_H-i-s') . '_' . uniqid() . '.' . $extension;
                $filepath = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['imagen']['tmp_name'], $filepath)) {
                    $imagen = $empresa_files->getAvisosPath() . $filename;
                }
            } catch (Exception $e) {
                $error = "Error subiendo imagen: " . $e->getMessage();
            }
        }
        
        // Guardar aviso
        if ($accion === 'crear') {
            $sql = "INSERT INTO avisos (empresa_id, cliente_id, titulo, descripcion, tipo_aviso, imagen, prompt_ia, generador_ia, telefono_contacto, email_contacto, fecha_expiracion) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            query($sql, [$empresa_id, $cliente_id, $titulo, $descripcion, $tipo_aviso, $imagen, $prompt_ia, $proveedor_id, $telefono, $email, $fecha_expiracion]);
            $db = getDB();
            $aviso_id = $db->lastInsertId();
        } else {
            $aviso_id = $_GET['id'] ?? null;
            // Si hay nueva imagen, actualizarla, sino mantener la actual
            if ($imagen) {
                $sql = "UPDATE avisos SET titulo=?, descripcion=?, tipo_aviso=?, cliente_id=?, imagen=?, prompt_ia=?, generador_ia=?, telefono_contacto=?, email_contacto=?, fecha_expiracion=? 
                        WHERE id=? AND empresa_id=?";
                query($sql, [$titulo, $descripcion, $tipo_aviso, $cliente_id, $imagen, $prompt_ia, $proveedor_id, $telefono, $email, $fecha_expiracion, $aviso_id, $empresa_id]);
            } else {
                $sql = "UPDATE avisos SET titulo=?, descripcion=?, tipo_aviso=?, cliente_id=?, prompt_ia=?, generador_ia=?, telefono_contacto=?, email_contacto=?, fecha_expiracion=? 
                        WHERE id=? AND empresa_id=?";
                query($sql, [$titulo, $descripcion, $tipo_aviso, $cliente_id, $prompt_ia, $proveedor_id, $telefono, $email, $fecha_expiracion, $aviso_id, $empresa_id]);
            }
        }
        
        // Si se presionó "GUARDAR Y ENVIAR A BANNERS", redirigir a banners.php
        if ($form_accion === 'guardar_enviar_banner') {
            // Obtener el aviso recién creado/actualizado
            $aviso = fetch("SELECT * FROM avisos WHERE id = ? AND empresa_id = ?", [$aviso_id, $empresa_id]);
            
            if ($aviso && !empty($aviso['imagen'])) {
                $params = http_build_query([
                    'from' => 'avisos',
                    'aviso_id' => $aviso_id,
                    'aviso_titulo' => $aviso['titulo'],
                    'aviso_descripcion' => $aviso['descripcion'],
                    'aviso_tipo' => $aviso['tipo_aviso'],
                    'imagen' => $aviso['imagen']
                ]);
                
                header('Location: banners.php?' . $params);
                exit;
            } else {
                // Si no hay imagen, mostrar error
                $error = "No se puede enviar a banners sin una imagen. Sube una imagen primero.";
                header('Location: avisos.php?accion=editar&id=' . $aviso_id . '&error=' . urlencode($error));
                exit;
            }
        }
        
        header('Location: avisos.php');
        exit;
    }
    
}

// Procesar acciones GET (eliminar, enviar a banners)
if ($accion === 'eliminar' && $aviso_id) {
    // Primero obtener el aviso para saber qué imagen eliminar
    $aviso = fetch("SELECT * FROM avisos WHERE id = ? AND empresa_id = ?", [$aviso_id, $empresa_id]);
    
    if ($aviso) {
        // Eliminar imagen física si existe
        if (!empty($aviso['imagen'])) {
            $ruta_imagen = $aviso['imagen'];
            // Convertir ruta relativa a absoluta
            if (strpos($ruta_imagen, '/') === 0) {
                $ruta_absoluta = $_SERVER['DOCUMENT_ROOT'] . $ruta_imagen;
            } else {
                $ruta_absoluta = dirname(__DIR__) . '/' . $ruta_imagen;
            }
            
            if (file_exists($ruta_absoluta)) {
                unlink($ruta_absoluta);
                error_log("Imagen de aviso eliminada: $ruta_absoluta");
            }
        }
        
        // Eliminar registro de la base de datos
        query("DELETE FROM avisos WHERE id = ? AND empresa_id = ?", [$aviso_id, $empresa_id]);
    }
    
    header('Location: avisos.php');
    exit;
}

if ($accion === 'enviar_banner' && $aviso_id) {
    $aviso = fetch("SELECT * FROM avisos WHERE id = ? AND empresa_id = ?", [$aviso_id, $empresa_id]);
    
    if ($aviso && !empty($aviso['imagen'])) {
        // Redirigir a banners.php con los datos del aviso
        $params = http_build_query([
            'from' => 'avisos',
            'aviso_id' => $aviso_id,
            'aviso_titulo' => $aviso['titulo'],
            'aviso_descripcion' => $aviso['descripcion'],
            'aviso_tipo' => $aviso['tipo_aviso'],
            'imagen' => $aviso['imagen']
        ]);
        
        header('Location: banners.php?' . $params);
        exit;
    } else {
        // Si no hay imagen, mostrar error
        $error = "No se puede enviar a banners sin una imagen.";
    }
}

// Cargar datos según acción
$aviso = null;
if ($accion === 'editar' && $aviso_id) {
    $aviso = fetch("SELECT * FROM avisos WHERE id = ? AND empresa_id = ?", [$aviso_id, $empresa_id]);
}

$avisos = fetchAll("SELECT a.*, c.nombre, c.apellido 
                    FROM avisos a 
                    LEFT JOIN clientes c ON a.cliente_id = c.id 
                    WHERE a.empresa_id = ? 
                    ORDER BY a.fecha_creacion DESC", [$empresa_id]);

$clientes = fetchAll("SELECT id, nombre, apellido FROM clientes WHERE empresa_id = ? ORDER BY nombre, apellido", [$empresa_id]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📢 Avisos - Sistema de Facturación</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-yellow-400">📢 AVISOS</h1>
            <div class="space-x-2">
                <?php if ($accion !== 'crear'): ?>
                    <a href="avisos.php?accion=crear" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded font-bold">
                        ➕ NUEVO AVISO
                    </a>
                <?php endif; ?>
                <a href="dashboard.php" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded font-bold">
                    VOLVER
                </a>
            </div>
        </div>

        <?php if (isset($_GET['mensaje']) && $_GET['mensaje'] === 'enviado'): ?>
            <div class="bg-green-600 text-white p-4 rounded mb-4">
                Aviso enviado a banners exitosamente.
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="bg-red-600 text-white p-4 rounded mb-4">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($accion === 'crear' || $accion === 'editar'): ?>
            <!-- Formulario de crear/editar aviso -->
            <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
                <h2 class="text-xl font-bold mb-4">
                    <?= $accion === 'crear' ? 'Crear Nuevo Aviso' : 'Editar Aviso' ?>
                </h2>
                <form method="POST" enctype="multipart/form-data">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">Título *</label>
                            <input type="text" name="titulo" value="<?= htmlspecialchars($aviso['titulo'] ?? '') ?>" required
                                   class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                        </div>
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">Tipo de Aviso</label>
                            <select name="tipo_aviso" class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                                <option value="perdido" <?= ($aviso['tipo_aviso'] ?? '') === 'perdido' ? 'selected' : '' ?>>🔍 Perdido</option>
                                <option value="encontrado" <?= ($aviso['tipo_aviso'] ?? '') === 'encontrado' ? 'selected' : '' ?>>✅ Encontrado</option>
                                <option value="mascota" <?= ($aviso['tipo_aviso'] ?? '') === 'mascota' ? 'selected' : '' ?>>🐾 Mascota</option>
                                <option value="servicio" <?= ($aviso['tipo_aviso'] ?? '') === 'servicio' ? 'selected' : '' ?>>🔧 Servicio</option>
                                <option value="otro" <?= ($aviso['tipo_aviso'] ?? '') === 'otro' ? 'selected' : '' ?>>📋 Otro</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-gray-400 text-sm mb-2">Descripción</label>
                            <textarea name="descripcion" rows="3" class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600"><?= htmlspecialchars($aviso['descripcion'] ?? '') ?></textarea>
                        </div>
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">Cliente (opcional)</label>
                            <select name="cliente_id" class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                                <option value="">-- Sin cliente --</option>
                                <?php foreach ($clientes as $cliente): ?>
                                    <option value="<?= $cliente['id'] ?>" <?= ($aviso['cliente_id'] ?? '') == $cliente['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellido']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">Teléfono Contacto (opcional)</label>
                            <input type="text" name="telefono" value="<?= htmlspecialchars($aviso['telefono_contacto'] ?? '') ?>"
                                   class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                        </div>
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">Email Contacto (opcional)</label>
                            <input type="email" name="email" value="<?= htmlspecialchars($aviso['email_contacto'] ?? '') ?>"
                                   class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                        </div>
                    </div>
                    
                    <div class="mt-6 border-t border-gray-700 pt-6">
                        <h3 class="text-lg font-bold mb-4">🎨 Generar Imagen con IA</h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-gray-400 text-sm mb-2">Prompt Personalizado (opcional)</label>
                                <textarea id="prompt_ia" name="prompt_ia" rows="3"
                                          class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600"
                                          placeholder="Describe la imagen que quieres generar..."><?= htmlspecialchars($aviso['prompt_ia'] ?? '') ?></textarea>
                            </div>
                            <div>
                                <label class="block text-gray-400 text-sm mb-2">Proveedor IA</label>
                                <select id="proveedor_ia" name="proveedor_ia" class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                                    <?php if (empty($proveedores_ia)): ?>
                                    <option value="">No hay proveedores configurados</option>
                                    <?php else: ?>
                                    <?php foreach ($proveedores_ia as $prov): ?>
                                    <option value="<?= $prov['id'] ?>" data-url_web="<?= htmlspecialchars($prov['url_web'] ?? '') ?>" <?= ($aviso['generador_ia'] ?? $proveedor_default_id) == $prov['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($prov['nombre']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
                                <button type="button" onclick="generarPromptAviso()" class="bg-purple-600 hover:bg-purple-700 text-white py-3 rounded font-bold">
                                    🎨 GENERAR PROMPT
                                </button>
                                <button type="button" onclick="copiarPromptAviso()" class="bg-blue-600 hover:bg-blue-700 text-white py-3 rounded font-bold">
                                    📋 COPIAR PROMPT
                                </button>
                                <button type="button" onclick="abrirIAExternaAviso()" class="bg-indigo-600 hover:bg-indigo-700 text-white py-3 rounded font-bold">
                                    🌐 ABRIR IA EXTERNA
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-6 border-t border-gray-700 pt-6">
                        <h3 class="text-lg font-bold mb-4">📤 Subir Imagen Generada</h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-gray-400 text-sm mb-2">Imagen del Aviso</label>
                                <input type="file" id="imagen_aviso" name="imagen" accept="image/*"
                                       class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                                <p class="text-xs text-gray-500 mt-1">Sube la imagen generada por la IA externa</p>
                            </div>
                            <div id="preview_container" class="hidden">
                                <label class="block text-gray-400 text-sm mb-2">Vista Previa</label>
                                <img id="preview_imagen" class="max-w-xs rounded border border-gray-600">
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($aviso['imagen'])): ?>
                        <div class="mt-4">
                            <label class="block text-gray-400 text-sm mb-2">Imagen Actual</label>
                            <img src="<?= $aviso['imagen'] ?>" alt="Imagen del aviso" class="max-w-xs rounded border border-gray-600">
                        </div>
                    <?php endif; ?>
                    
                    <div class="mt-6 flex flex-wrap gap-2">
                        <button type="submit" name="accion" value="guardar" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded font-bold">
                            💾 GUARDAR
                        </button>
                        <button type="submit" name="accion" value="guardar_enviar_banner" class="bg-orange-600 hover:bg-orange-700 text-white px-6 py-3 rounded font-bold">
                            📤 GUARDAR Y ENVIAR A BANNERS
                        </button>
                        <a href="avisos.php" class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-3 rounded font-bold">
                            ❌ CANCELAR
                        </a>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <!-- Lista de avisos -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($avisos as $aviso): ?>
                    <div class="bg-gray-800 rounded-lg border border-gray-700 hover:border-yellow-500 overflow-hidden">
                        <?php if (!empty($aviso['imagen'])): ?>
                            <img src="<?= $aviso['imagen'] ?>" alt="<?= htmlspecialchars($aviso['titulo']) ?>" class="w-full h-48 object-cover">
                        <?php else: ?>
                            <div class="w-full h-48 bg-gray-700 flex items-center justify-center">
                                <span class="text-gray-500 text-4xl">📢</span>
                            </div>
                        <?php endif; ?>
                        <div class="p-4">
                            <div class="flex justify-between items-start mb-2">
                                <h3 class="font-bold text-lg"><?= htmlspecialchars($aviso['titulo']) ?></h3>
                                <span class="text-xs bg-<?= $aviso['tipo_aviso'] === 'perdido' ? 'red' : ($aviso['tipo_aviso'] === 'encontrado' ? 'green' : 'blue') ?>-600 px-2 py-1 rounded">
                                    <?= strtoupper($aviso['tipo_aviso']) ?>
                                </span>
                            </div>
                            <p class="text-gray-400 text-sm mb-2"><?= htmlspecialchars(substr($aviso['descripcion'], 0, 100)) ?>...</p>
                            <?php if ($aviso['cliente_id']): ?>
                                <p class="text-gray-500 text-xs mb-2">Cliente: <?= htmlspecialchars($aviso['nombre'] . ' ' . $aviso['apellido']) ?></p>
                            <?php endif; ?>
                            <p class="text-gray-500 text-xs mb-4">Creado: <?= date('d/m/Y H:i', strtotime($aviso['fecha_creacion'])) ?></p>
                            <div class="flex space-x-2">
                                <?php if (!$aviso['enviado_banner'] && !empty($aviso['imagen'])): ?>
                                    <a href="avisos.php?accion=enviar_banner&id=<?= $aviso['id'] ?>" 
                                       class="flex-1 bg-purple-600 hover:bg-purple-700 text-white text-center px-3 py-2 rounded text-sm font-bold">
                                        📺 ENVIAR A BANNERS
                                    </a>
                                <?php endif; ?>
                                <a href="avisos.php?accion=editar&id=<?= $aviso['id'] ?>" 
                                   class="flex-1 bg-blue-600 hover:bg-blue-700 text-white text-center px-3 py-2 rounded text-sm font-bold">
                                    ✏️ EDITAR
                                </a>
                                <a href="avisos.php?accion=eliminar&id=<?= $aviso['id'] ?>" 
                                   onclick="return confirm('¿Eliminar este aviso?')"
                                   class="flex-1 bg-red-600 hover:bg-red-700 text-white text-center px-3 py-2 rounded text-sm font-bold">
                                    🗑️ ELIMINAR
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (empty($avisos)): ?>
                <div class="text-center py-12 text-gray-500">
                    <p class="text-4xl mb-4">📢</p>
                    <p>No hay avisos creados</p>
                    <a href="avisos.php?accion=crear" class="mt-4 inline-block bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded font-bold">
                        Crear primer aviso
                    </a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <script>
        // Funciones para el flujo de IA similar a imagenes.php
        function generarPromptAviso() {
            const titulo = document.querySelector('input[name="titulo"]').value;
            const descripcion = document.querySelector('textarea[name="descripcion"]').value;
            const tipoAviso = document.querySelector('select[name="tipo_aviso"]').value;
            const telefono = document.querySelector('input[name="telefono"]').value;
            const email = document.querySelector('input[name="email"]').value;
            const clienteSelect = document.querySelector('select[name="cliente_id"]');
            const clienteNombre = clienteSelect.selectedIndex > 0 ? clienteSelect.options[clienteSelect.selectedIndex].text : '';
            
            if (!titulo) {
                alert('Primero ingresa un título para el aviso');
                return;
            }
            
            let prompt = `Crea una imagen para un aviso de tipo "${tipoAviso}". `;
            prompt += `Título: "${titulo}". `;
            
            if (descripcion) {
                prompt += `Descripción: ${descripcion}. `;
            }
            
            // Incluir información de contacto si está disponible (opcional)
            if (clienteNombre) {
                prompt += `Contacto: ${clienteNombre}. `;
            }
            if (telefono) {
                prompt += `Teléfono: ${telefono}. `;
            }
            if (email) {
                prompt += `Email: ${email}. `;
            }
            
            prompt += `La imagen debe ser atractiva, llamativa y profesional para uso en marketing digital. `;
            prompt += `Estilo visual: moderno, limpio, con buena iluminación y composición. `;
            prompt += `Incluye elementos visuales relacionados con el tipo de aviso. `;
            
            document.getElementById('prompt_ia').value = prompt;
        }
        
        function copiarPromptAviso() {
            const promptTextarea = document.getElementById('prompt_ia');
            if (!promptTextarea.value.trim()) {
                alert('Primero genera un prompt');
                return;
            }
            
            promptTextarea.select();
            document.execCommand('copy');
            window.getSelection().removeAllRanges();
            
            alert('✅ Prompt copiado al portapapeles');
        }
        
        function abrirIAExternaAviso() {
            const proveedorSelect = document.getElementById('proveedor_ia');
            const selectedOption = proveedorSelect.options[proveedorSelect.selectedIndex];
            const urlWeb = selectedOption.getAttribute('data-url_web');
            
            if (!urlWeb) {
                alert('El proveedor seleccionado no tiene URL web configurada');
                return;
            }
            
            // Generar prompt automáticamente si no existe
            const promptTextarea = document.getElementById('prompt_ia');
            if (!promptTextarea.value.trim()) {
                generarPromptAviso();
            }
            
            // Copiar prompt al portapapeles
            const prompt = promptTextarea.value;
            navigator.clipboard.writeText(prompt).then(() => {
                window.open(urlWeb, '_blank');
                alert('✅ Prompt copiado. Se abrirá el sitio de IA externa en una nueva ventana.');
            }).catch((err) => {
                window.open(urlWeb, '_blank');
                alert('⚠️ No se pudo copiar el prompt, pero se abrirá el sitio de IA externa.');
            });
        }
        
        // Vista previa de imagen
        document.getElementById('imagen_aviso').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('preview_imagen').src = e.target.result;
                    document.getElementById('preview_container').classList.remove('hidden');
                };
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>
