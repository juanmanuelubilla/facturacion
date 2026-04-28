<?php
require_once 'config.php';
requireLogin();

$user = getUser();
$empresa_id = $user['empresa_id'];

// Función helper para subir imágenes
function subirImagen($input_name, $empresa_id) {
    if (!isset($_FILES[$input_name]) || $_FILES[$input_name]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    require_once 'lib/ImageProcessor.php';
    require_once 'lib/empresa_files.php';
    
    $empresa_files = new EmpresaFiles($empresa_id);
    $upload_dir = $empresa_files->getEmpresaPathAbsoluta() . 'clientes/';
    
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $processor = new ImageProcessor();
    $filename = $processor->generateUniqueFilename($_FILES[$input_name]['name']);
    $filepath = $upload_dir . $filename;

    if (move_uploaded_file($_FILES[$input_name]['tmp_name'], $filepath)) {
        // Procesar imagen
        $result = $processor->processImage($filepath, $filepath, false);
        
        return $empresa_files->getEmpresaPath() . 'clientes/' . $filename;
    }
    return null;
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $id = $_POST['id'] ?? null;
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $documento = trim($_POST['documento'] ?? '');
    $tipo_documento = trim($_POST['tipo_documento'] ?? '');
    $condicion_iva = trim($_POST['condicion_iva'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $whatsapp = trim($_POST['whatsapp'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $acepta_whatsapp = isset($_POST['acepta_whatsapp']) ? 1 : 0;
    $comentarios = trim($_POST['comentarios'] ?? '');
    
    // Procesar imágenes subidas
    $foto_cliente_path = subirImagen('foto_cliente_file', $empresa_id);
    $foto_opcional_path = subirImagen('foto_opcional_file', $empresa_id);
    
    // Si no se subió nueva imagen, mantener la existente
    $foto_cliente = $foto_cliente_path ?? trim($_POST['foto_cliente'] ?? '');
    $foto_opcional = $foto_opcional_path ?? trim($_POST['foto_opcional'] ?? '');
    
    if ($accion === 'guardar') {
        if ($id) {
            query("UPDATE clientes SET nombre=?, apellido=?, documento=?, tipo_documento=?, condicion_iva=?, telefono=?, whatsapp=?, email=?, acepta_whatsapp=?, comentarios=?, foto_cliente=?, foto_opcional=? WHERE id=? AND empresa_id=?",
                  [$nombre, $apellido, $documento, $tipo_documento, $condicion_iva, $telefono, $whatsapp, $email, $acepta_whatsapp, $comentarios, $foto_cliente, $foto_opcional, $id, $empresa_id]);
        } else {
            query("INSERT INTO clientes (nombre, apellido, documento, tipo_documento, condicion_iva, telefono, whatsapp, email, acepta_whatsapp, comentarios, foto_cliente, foto_opcional, empresa_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                  [$nombre, $apellido, $documento, $tipo_documento, $condicion_iva, $telefono, $whatsapp, $email, $acepta_whatsapp, $comentarios, $foto_cliente, $foto_opcional, $empresa_id]);
        }
        header('Location: clientes.php');
        exit;
    }
    
    if ($accion === 'eliminar' && $id) {
        query("DELETE FROM clientes WHERE id=? AND empresa_id=?", [$id, $empresa_id]);
        header('Location: clientes.php');
        exit;
    }
}

$clientes = fetchAll("SELECT * FROM clientes WHERE empresa_id = ? ORDER BY nombre, apellido", [$empresa_id]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes - NEXUS POS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-gray-900 to-gray-800 text-gray-100">
    <header class="bg-gray-900 shadow-lg">
        <div class="container mx-auto px-6 py-4 flex justify-between items-center">
            <h1 class="text-2xl font-bold text-yellow-400">👥 CLIENTES</h1>
            <a href="dashboard.php" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded">VOLVER</a>
        </div>
    </header>
    <main class="container mx-auto px-6 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Formulario -->
            <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
                <h3 class="text-xl font-bold text-white mb-4" id="formTitle">Nuevo Cliente</h3>
                <form method="POST" id="clienteForm" enctype="multipart/form-data">
                    <input type="hidden" name="accion" value="guardar">
                    <input type="hidden" name="id" id="clienteId" value="">
                    
                    <div class="space-y-3">
                        <div>
                            <label class="block text-gray-400 text-sm mb-1">Nombre</label>
                            <input type="text" name="nombre" id="nombre" required
                                   class="w-full bg-gray-700 text-white p-2 rounded border border-gray-600">
                        </div>
                        <div>
                            <label class="block text-gray-400 text-sm mb-1">Apellido</label>
                            <input type="text" name="apellido" id="apellido"
                                   class="w-full bg-gray-700 text-white p-2 rounded border border-gray-600">
                        </div>
                        <div>
                            <label class="block text-gray-400 text-sm mb-1">Documento</label>
                            <input type="text" name="documento" id="documento"
                                   class="w-full bg-gray-700 text-white p-2 rounded border border-gray-600">
                        </div>
                        <div>
                            <label class="block text-gray-400 text-sm mb-1">Tipo Documento</label>
                            <select name="tipo_documento" id="tipo_documento"
                                    class="w-full bg-gray-700 text-white p-2 rounded border border-gray-600">
                                <option value="DNI">DNI</option>
                                <option value="CUIT">CUIT</option>
                                <option value="CUIL">CUIL</option>
                                <option value="PASS">Pasaporte</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-gray-400 text-sm mb-1">Condición IVA</label>
                            <select name="condicion_iva" id="condicion_iva"
                                    class="w-full bg-gray-700 text-white p-2 rounded border border-gray-600">
                                <option value="Consumidor Final">Consumidor Final</option>
                                <option value="Responsable Inscripto">Responsable Inscripto</option>
                                <option value="Monotributo">Monotributo</option>
                                <option value="Exento">Exento</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">Foto de Perfil (para reconocimiento facial)</label>
                            <input type="file" name="foto_perfil" accept="image/*" 
                                   class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                            <input type="hidden" name="foto_perfil" id="foto_perfil">
                            <div id="preview_foto_perfil" class="mt-2 hidden">
                                <img src="" alt="Preview" class="w-20 h-20 rounded object-cover border border-gray-600">
                                <button type="button" onclick="limpiarImagen('foto_perfil', 'preview_foto_perfil', 'foto_perfil')"
                                        class="text-red-400 text-sm mt-1 hover:text-red-300">🗑️ Eliminar imagen</button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-gray-400 text-sm mb-1">Email</label>
                            <input type="email" name="email" id="email"
                                   class="w-full bg-gray-700 text-white p-2 rounded border border-gray-600">
                        </div>
                        <div>
                            <label class="block text-gray-400 text-sm mb-1">Teléfono</label>
                            <input type="text" name="telefono" id="telefono"
                                   class="w-full bg-gray-700 text-white p-2 rounded border border-gray-600">
                        </div>
                        <div>
                            <label class="block text-gray-400 text-sm mb-1">WhatsApp</label>
                            <input type="text" name="whatsapp" id="whatsapp"
                                   class="w-full bg-gray-700 text-white p-2 rounded border border-gray-600">
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="checkbox" name="acepta_whatsapp" id="acepta_whatsapp" class="w-4 h-4">
                            <label class="text-gray-400 text-sm">Acepta WhatsApp</label>
                        </div>
                        <div>
                            <label class="block text-gray-400 text-sm mb-1">Comentarios</label>
                            <textarea name="comentarios" id="comentarios" rows="2"
                                      class="w-full bg-gray-700 text-white p-2 rounded border border-gray-600"></textarea>
                        </div>
                        <!-- Foto del Cliente -->
                        <div>
                            <label class="block text-gray-400 text-sm mb-1">Foto del Cliente</label>
                            <input type="file" name="foto_cliente_file" id="input_foto_cliente" accept="image/*"
                                   class="w-full bg-gray-700 text-white p-2 rounded border border-gray-600 text-sm"
                                   onchange="previewImagen(this, 'preview_foto_cliente', 'foto_cliente')">
                            <input type="hidden" name="foto_cliente" id="foto_cliente">
                            <div id="preview_foto_cliente" class="mt-2 hidden">
                                <img src="" alt="Preview" class="w-32 h-32 object-cover rounded border border-gray-600">
                                <button type="button" onclick="limpiarImagen('input_foto_cliente', 'preview_foto_cliente', 'foto_cliente')"
                                        class="text-red-400 text-sm mt-1 hover:text-red-300">🗑️ Eliminar imagen</button>
                            </div>
                        </div>

                        <!-- Foto Opcional -->
                        <div>
                            <label class="block text-gray-400 text-sm mb-1">Foto Opcional</label>
                            <input type="file" name="foto_opcional_file" id="input_foto_opcional" accept="image/*"
                                   class="w-full bg-gray-700 text-white p-2 rounded border border-gray-600 text-sm"
                                   onchange="previewImagen(this, 'preview_foto_opcional', 'foto_opcional')">
                            <input type="hidden" name="foto_opcional" id="foto_opcional">
                            <div id="preview_foto_opcional" class="mt-2 hidden">
                                <img src="" alt="Preview" class="w-32 h-32 object-cover rounded border border-gray-600">
                                <button type="button" onclick="limpiarImagen('input_foto_opcional', 'preview_foto_opcional', 'foto_opcional')"
                                        class="text-red-400 text-sm mt-1 hover:text-red-300">🗑️ Eliminar imagen</button>
                            </div>
                        </div>
                        <button type="submit" class="w-full bg-yellow-600 hover:bg-yellow-700 text-white py-3 rounded font-bold">
                            💾 GUARDAR CLIENTE
                        </button>
                        <button type="button" onclick="cargarConsumidorFinal()" class="w-full bg-orange-600 hover:bg-orange-700 text-white py-2 rounded">
                            👤 CONSUMIDOR FINAL
                        </button>
                        <button type="button" onclick="limpiarFormulario()" class="w-full bg-gray-600 hover:bg-gray-700 text-white py-2 rounded">
                            🧹 LIMPIAR
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Tabla -->
            <div class="lg:col-span-2 bg-gray-800 rounded-lg border border-gray-700 p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold text-white">Agenda de Clientes</h3>
                    <input type="text" placeholder="Buscar..." id="buscador"
                           class="bg-gray-700 text-white p-2 rounded border border-gray-600"
                           onkeyup="filtrarClientes()">
                </div>
                <div class="overflow-x-auto max-h-64 overflow-y-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-700 sticky top-0 bg-gray-800">
                                <th class="text-left py-2 px-3 text-gray-400">Nombre Completo</th>
                                <th class="text-left py-2 px-3 text-gray-400">Documento</th>
                                <th class="text-left py-2 px-3 text-gray-400">Teléfono</th>
                                <th class="text-left py-2 px-3 text-gray-400">WhatsApp</th>
                                <th class="text-left py-2 px-3 text-gray-400">Condición IVA</th>
                                <th class="text-left py-2 px-3 text-gray-400">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tablaClientes">
                            <?php foreach ($clientes as $c): ?>
                            <tr class="border-b border-gray-700 hover:bg-gray-700 cliente-row cursor-pointer" data-nombre="<?= strtolower(htmlspecialchars($c['nombre'])) ?>" data-id="<?= $c['id'] ?>" onclick="cargarHistorial(<?= $c['id'] ?>)">
                                <td class="py-2 px-3 text-sm"><?= htmlspecialchars($c['nombre'] . ' ' . $c['apellido']) ?></td>
                                <td class="py-2 px-3 text-sm"><?= htmlspecialchars($c['documento']) ?></td>
                                <td class="py-2 px-3 text-sm"><?= htmlspecialchars($c['telefono']) ?></td>
                                <td class="py-2 px-3 text-sm"><?= htmlspecialchars($c['whatsapp']) ?></td>
                                <td class="py-2 px-3 text-sm text-gray-400"><?= htmlspecialchars($c['condicion_iva'] ?? '-') ?></td>
                                <td class="py-2 px-3 text-sm">
                                    <button onclick="event.stopPropagation(); editarCliente(<?= $c['id'] ?>, '<?= htmlspecialchars($c['nombre']) ?>', '<?= htmlspecialchars($c['apellido']) ?>', '<?= htmlspecialchars($c['documento']) ?>', '<?= htmlspecialchars($c['tipo_documento']) ?>', '<?= htmlspecialchars($c['condicion_iva']) ?>', '<?= htmlspecialchars($c['telefono']) ?>', '<?= htmlspecialchars($c['whatsapp']) ?>', '<?= htmlspecialchars($c['email'] ?? '') ?>', <?= $c['acepta_whatsapp'] ?>, '<?= htmlspecialchars($c['comentarios']) ?>', '<?= htmlspecialchars($c['foto_cliente'] ?? '') ?>', '<?= htmlspecialchars($c['foto_opcional'] ?? '') ?>')"
                                            class="text-blue-400 hover:text-blue-300 mr-2">✏️ Editar</button>
                                    <button onclick="event.stopPropagation(); enviarWhatsApp(<?= $c['id'] ?>, '<?= htmlspecialchars($c['nombre']) ?>', '<?= htmlspecialchars($c['whatsapp']) ?>', <?= $c['acepta_whatsapp'] ?>)"
                                            class="text-green-400 hover:text-green-300 mr-2">📱 WhatsApp</button>
                                    <button onclick="event.stopPropagation(); eliminarCliente(<?= $c['id'] ?>)"
                                            class="text-red-400 hover:text-red-300">🗑️ Eliminar</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Historial de compras -->
                <div class="mt-4 pt-4 border-t border-gray-700">
                    <h4 class="text-white font-bold mb-2">Historial de compras del cliente seleccionado</h4>
                    <div id="historialCompras" class="bg-gray-700 p-3 rounded text-gray-400 text-sm min-h-24">
                        Seleccione un cliente para ver su historial de compras.
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        function editarCliente(id, nombre, apellido, documento, tipoDoc, condicionIva, telefono, whatsapp, email, aceptaWhatsapp, comentarios, fotoCliente, fotoOpcional) {
            document.getElementById('formTitle').textContent = 'Editar Cliente';
            document.getElementById('clienteId').value = id;
            document.getElementById('nombre').value = nombre;
            document.getElementById('apellido').value = apellido;
            document.getElementById('documento').value = documento;
            document.getElementById('tipo_documento').value = tipoDoc;
            document.getElementById('condicion_iva').value = condicionIva;
            document.getElementById('email').value = email || '';
            document.getElementById('telefono').value = telefono;
            document.getElementById('whatsapp').value = whatsapp;
            document.getElementById('acepta_whatsapp').checked = aceptaWhatsapp;
            document.getElementById('comentarios').value = comentarios;
            document.getElementById('foto_cliente').value = fotoCliente || '';
            document.getElementById('foto_opcional').value = fotoOpcional || '';
            
            // Mostrar previews de imágenes existentes
            mostrarImagenExistente(fotoCliente, 'preview_foto_cliente', 'foto_cliente');
            mostrarImagenExistente(fotoOpcional, 'preview_foto_opcional', 'foto_opcional');
            
            cargarHistorial(id);
        }
        
        function eliminarCliente(id) {
            if (confirm('¿Eliminar este cliente?')) {
                const form = document.getElementById('clienteForm');
                form.action.value = 'eliminar';
                form.id.value = id;
                form.submit();
            }
        }
        
        function limpiarFormulario() {
            document.getElementById('formTitle').textContent = 'Nuevo Cliente';
            document.getElementById('clienteId').value = '';
            document.getElementById('nombre').value = '';
            document.getElementById('apellido').value = '';
            document.getElementById('documento').value = '';
            document.getElementById('tipo_documento').value = 'DNI';
            document.getElementById('condicion_iva').value = 'Consumidor Final';
            document.getElementById('email').value = '';
            document.getElementById('telefono').value = '';
            document.getElementById('whatsapp').value = '';
            document.getElementById('acepta_whatsapp').checked = false;
            document.getElementById('comentarios').value = '';
            document.getElementById('foto_cliente').value = '';
            document.getElementById('foto_opcional').value = '';
            document.getElementById('historialCompras').textContent = 'Seleccione un cliente para ver su historial de compras.';
        }
        
        function cargarConsumidorFinal() {
            document.getElementById('formTitle').textContent = 'Nuevo Cliente';
            document.getElementById('clienteId').value = '';
            document.getElementById('nombre').value = 'CONSUMIDOR FINAL';
            document.getElementById('documento').value = '0';
            document.getElementById('tipo_documento').value = 'DNI';
            document.getElementById('condicion_iva').value = 'Consumidor Final';
            document.getElementById('telefono').value = '';
            document.getElementById('whatsapp').value = '';
            document.getElementById('acepta_whatsapp').checked = false;
            document.getElementById('comentarios').value = 'Cliente de mostrador sin datos fiscales adicionales.';
            document.getElementById('foto_cliente').value = '';
            document.getElementById('foto_opcional').value = '';
        }
        
        function enviarWhatsApp(id, nombre, apellido, whatsapp, acepta) {
            if (!whatsapp) {
                alert('El cliente no tiene número de WhatsApp cargado.');
                return;
            }
            if (!acepta) {
                alert('El cliente no acepta mensajes por WhatsApp.');
                return;
            }
            const whatsappClean = whatsapp.replace(/\D/g, '');
            const nombreCompleto = nombre + ' ' + apellido;
            const mensaje = `Hola ${nombreCompleto}, muchas gracias por tu compra en NEXUS POS! Esperamos verte pronto.`;
            const url = `https://wa.me/${whatsappClean}?text=${encodeURIComponent(mensaje)}`;
            window.open(url, '_blank');
        }
        
        function cargarHistorial(clienteId) {
            fetch(`api_historial_cliente.php?id=${clienteId}`)
                .then(r => r.json())
                .then(data => {
                    const div = document.getElementById('historialCompras');
                    if (!data || data.length === 0) {
                        div.textContent = 'Este cliente aún no tiene ventas registradas.';
                        return;
                    }
                    let html = '';
                    data.forEach(v => {
                        html += `<div class="mb-2">Venta #${v.id} | ${v.fecha} | $${parseFloat(v.total).toFixed(2)}</div>`;
                        html += `<div class="text-gray-500 text-xs">Productos: ${v.productos || 'Sin detalle'}</div>`;
                    });
                    div.innerHTML = html;
                })
                .catch(() => {
                    document.getElementById('historialCompras').textContent = 'No se pudo cargar el historial.';
                });
        }
        
        // Funciones para manejo de imágenes
        function previewImagen(input, previewId, hiddenId) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const previewDiv = document.getElementById(previewId);
                    previewDiv.querySelector('img').src = e.target.result;
                    previewDiv.classList.remove('hidden');
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        function limpiarImagen(inputId, previewId, hiddenId) {
            document.getElementById(inputId).value = '';
            document.getElementById(hiddenId).value = '';
            const previewDiv = document.getElementById(previewId);
            previewDiv.querySelector('img').src = '';
            previewDiv.classList.add('hidden');
        }

        // Mostrar imágenes existentes al editar
        function mostrarImagenExistente(ruta, previewId, hiddenId) {
            if (ruta) {
                const previewDiv = document.getElementById(previewId);
                previewDiv.querySelector('img').src = ruta;
                previewDiv.classList.remove('hidden');
                document.getElementById(hiddenId).value = ruta;
            }
        }

        function filtrarClientes() {
            const termino = document.getElementById('buscador').value.toLowerCase();
            const filas = document.querySelectorAll('.cliente-row');
            filas.forEach(fila => {
                const nombre = fila.dataset.nombre;
                fila.style.display = nombre.includes(termino) ? '' : 'none';
            });
        }
    </script>
</body>
</html>
