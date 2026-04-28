<?php
require_once 'config.php';
requireLogin();

$user = getUser();
$empresa_id = $user['empresa_id'];

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $id = $_POST['id'] ?? null;
    $nombre = trim($_POST['nombre'] ?? '');
    $password = $_POST['password'] ?? '';
    $rol = $_POST['rol'] ?? '';
    
    if ($accion === 'guardar') {
        $avatar = '';
        
        // Procesar upload de avatar
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            require_once 'lib/ImageProcessor.php';
            require_once 'lib/empresa_files.php';
            
            $empresa_files = new EmpresaFiles($empresa_id);
            $upload_dir = $empresa_files->getEmpresaPathAbsoluta() . 'avatars/';
            
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $processor = new ImageProcessor();
            $filename = $processor->generateUniqueFilename($_FILES['avatar']['name']);
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $filepath)) {
                // Procesar avatar (redimensionar a 200x200 cuadrado)
                $result = $processor->processImage($filepath, $filepath, false);
                
                if ($result['success']) {
                    $avatar = $empresa_files->getEmpresaPath() . 'avatars/' . $filename;
                } else {
                    $avatar = $empresa_files->getEmpresaPath() . 'avatars/' . $filename;
                }
            }
        }
        
        if ($id) {
            // Editar
            if ($password) {
                $password_hash = hash('sha256', $password);
                if ($avatar) {
                    query("UPDATE usuarios SET nombre=?, password=?, rol=?, avatar=? WHERE id=? AND empresa_id=?", 
                          [$nombre, $password_hash, $rol, $avatar, $id, $empresa_id]);
                } else {
                    query("UPDATE usuarios SET nombre=?, password=?, rol=? WHERE id=? AND empresa_id=?", 
                          [$nombre, $password_hash, $rol, $id, $empresa_id]);
                }
            } else {
                if ($avatar) {
                    query("UPDATE usuarios SET nombre=?, rol=?, avatar=? WHERE id=? AND empresa_id=?", 
                          [$nombre, $rol, $avatar, $id, $empresa_id]);
                } else {
                    query("UPDATE usuarios SET nombre=?, rol=? WHERE id=? AND empresa_id=?", 
                          [$nombre, $rol, $id, $empresa_id]);
                }
            }
        } else {
            // Nuevo
            if (!$password) {
                $error = 'Password requerida';
            } else {
                $password_hash = hash('sha256', $password);
                query("INSERT INTO usuarios (nombre, password, rol, empresa_id, avatar) VALUES (?, ?, ?, ?, ?)", 
                      [$nombre, $password_hash, $rol, $empresa_id, $avatar]);
            }
        }
        header('Location: usuarios.php');
        exit;
    }
    
    if ($accion === 'eliminar' && $id) {
        if (strtolower($nombre) === 'ubilla') {
            $error = 'Usuario del sistema protegido';
        } else {
            query("DELETE FROM usuarios WHERE id=? AND empresa_id=?", [$id, $empresa_id]);
            header('Location: usuarios.php');
            exit;
        }
    }
}

$usuarios = fetchAll("SELECT id, nombre, rol FROM usuarios WHERE empresa_id = ?", [$empresa_id]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuarios - NEXUS POS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-gray-900 to-gray-800 text-gray-100">
    <header class="bg-gray-900 shadow-lg">
        <div class="container mx-auto px-6 py-4 flex justify-between items-center">
            <h1 class="text-2xl font-bold text-orange-400">👥 GESTIÓN DE PERSONAL</h1>
            <a href="dashboard.php" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded">VOLVER</a>
        </div>
    </header>
    <main class="container mx-auto px-6 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Formulario -->
            <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
                <h3 class="text-xl font-bold text-white mb-4" id="formTitle">Nuevo Usuario</h3>
                <form method="POST" id="usuarioForm" enctype="multipart/form-data">
                    <input type="hidden" name="accion" value="guardar">
                    <input type="hidden" name="id" id="usuarioId" value="">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">Nombre</label>
                            <input type="text" name="nombre" id="nombre" required
                                   class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                        </div>
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">Password</label>
                            <input type="password" name="password" id="password"
                                   class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600"
                                   placeholder="Dejar vacío para no cambiar">
                        </div>
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">Rol</label>
                            <select name="rol" id="rol" required
                                    class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                                <option value="admin">Admin</option>
                                <option value="jefe">Jefe</option>
                                <option value="cajero">Cajero</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">Avatar (Opcional)</label>
                            <input type="file" name="avatar" accept="image/*"
                                   class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                        </div>
                        <button type="submit" class="w-full bg-orange-600 hover:bg-orange-700 text-white py-3 rounded font-bold">
                            💾 GUARDAR USUARIO
                        </button>
                        <button type="button" onclick="limpiarFormulario()" class="w-full bg-gray-600 hover:bg-gray-700 text-white py-2 rounded">
                            🧹 LIMPIAR
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Tabla -->
            <div class="lg:col-span-2 bg-gray-800 rounded-lg border border-gray-700 p-6">
                <h3 class="text-xl font-bold text-white mb-4">Usuarios Registrados</h3>
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-700">
                            <th class="text-left py-3 px-4 text-gray-400">ID</th>
                            <th class="text-left py-3 px-4 text-gray-400">Nombre</th>
                            <th class="text-left py-3 px-4 text-gray-400">Rol</th>
                            <th class="text-left py-3 px-4 text-gray-400">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $u): ?>
                        <tr class="border-b border-gray-700 hover:bg-gray-700">
                            <td class="py-3 px-4"><?= $u['id'] ?></td>
                            <td class="py-3 px-4"><?= htmlspecialchars($u['nombre']) ?></td>
                            <td class="py-3 px-4"><span class="bg-purple-600 px-2 py-1 rounded text-sm"><?= strtoupper($u['rol']) ?></span></td>
                            <td class="py-3 px-4">
                                <button onclick="editarUsuario(<?= $u['id'] ?>, '<?= htmlspecialchars($u['nombre']) ?>', '<?= $u['rol'] ?>')" 
                                        class="text-blue-400 hover:text-blue-300 mr-2">✏️ Editar</button>
                                <button onclick="eliminarUsuario(<?= $u['id'] ?>, '<?= htmlspecialchars($u['nombre']) ?>')" 
                                        class="text-red-400 hover:text-red-300">🗑️ Eliminar</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        function editarUsuario(id, nombre, rol) {
            document.getElementById('formTitle').textContent = 'Editar Usuario';
            document.getElementById('usuarioId').value = id;
            document.getElementById('nombre').value = nombre;
            document.getElementById('rol').value = rol;
            document.getElementById('password').value = '';
        }
        
        function eliminarUsuario(id, nombre) {
            if (nombre.toLowerCase() === 'ubilla') {
                alert('Usuario del sistema protegido');
                return;
            }
            if (confirm(`¿Eliminar a ${nombre}?`)) {
                const form = document.getElementById('usuarioForm');
                form.action.value = 'eliminar';
                form.id.value = id;
                form.submit();
            }
        }
        
        function limpiarFormulario() {
            document.getElementById('formTitle').textContent = 'Nuevo Usuario';
            document.getElementById('usuarioId').value = '';
            document.getElementById('nombre').value = '';
            document.getElementById('password').value = '';
            document.getElementById('rol').value = 'cajero';
        }
    </script>
</body>
</html>
