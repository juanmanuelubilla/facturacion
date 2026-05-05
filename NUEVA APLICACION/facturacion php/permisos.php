<?php
require_once 'config.php';
requireLogin();
require_once 'lib/PermissionManagerSimple.php';

$user = getUser();
$empresa_id = $user['empresa_id'];

// Verificar permisos para gestionar usuarios y permisos (solo admin)
if ($user['rol'] !== 'admin') {
    header('Location: dashboard.php?error=permission_denied');
    exit;
}

// Inicializar variables
$error = '';
$success = '';
$selected_user = null;
$pm = new PermissionManagerSimple();

// Procesar acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'select_user':
            $user_id = $_POST['user_id'] ?? 0;
            $selected_user = fetch("SELECT id, nombre, rol FROM usuarios WHERE id = ? AND empresa_id = ?", [$user_id, $empresa_id]);
            break;
            
        case 'update_permissions':
            $user_id = $_POST['user_id'] ?? 0;
            $capabilities = $_POST['capabilities'] ?? [];
            
            try {
                // Eliminar todos los permisos existentes del usuario
                $db = getDB();
                $db->prepare("DELETE FROM user_capabilities WHERE user_id = ?")->execute([$user_id]);
                
                // Asignar nuevos permisos
                foreach ($capabilities as $capability_id) {
                    $pm->grantCapability($user_id, $capability_id, $user['id']);
                }
                
                $success = "Permisos actualizados correctamente";
                $selected_user = fetch("SELECT id, nombre, rol FROM usuarios WHERE id = ? AND empresa_id = ?", [$user_id, $empresa_id]);
                
            } catch (Exception $e) {
                $error = "Error al actualizar permisos: " . $e->getMessage();
            }
            break;
            
        case 'initialize_role_permissions':
            $user_id = $_POST['user_id'] ?? 0;
            $role = $_POST['role'] ?? '';
            
            try {
                $pm->initializeUserPermissions($user_id, $role);
                $success = "Permisos por defecto asignados correctamente para rol: $role";
                $selected_user = fetch("SELECT id, nombre, rol FROM usuarios WHERE id = ? AND empresa_id = ?", [$user_id, $empresa_id]);
            } catch (Exception $e) {
                $error = "Error al inicializar permisos: " . $e->getMessage();
            }
            break;
            
        case 'create_role':
            $role_name = $_POST['role_name'] ?? '';
            $role_description = $_POST['role_description'] ?? '';
            $capabilities = $_POST['role_capabilities'] ?? [];
            
            try {
                if (empty($role_name) || empty($role_description)) {
                    throw new Exception("El nombre y descripción del rol son obligatorios");
                }
                
                $template_id = $pm->createRoleTemplate($role_name, $role_description, $capabilities);
                if ($template_id) {
                    $success = "Rol '$role_name' creado correctamente";
                } else {
                    throw new Exception("Error al crear el rol");
                }
            } catch (Exception $e) {
                $error = "Error al crear rol: " . $e->getMessage();
            }
            break;
    }
}

// Obtener datos para la vista
$usuarios = fetchAll("SELECT id, nombre, rol FROM usuarios WHERE empresa_id = ? ORDER BY nombre", [$empresa_id]);
$available_capabilities = $pm->getCapabilitiesByCategory();
$stats = $pm->getCapabilityStats();
$role_templates = $pm->getRoleTemplates();

// Obtener permisos del usuario seleccionado
$user_capabilities = [];
$user_capabilities_ids = [];
if ($selected_user) {
    // Obtener permisos actuales del usuario
    $user_capabilities = $pm->getUserCapabilities($selected_user['id']);
    $user_capabilities_ids = array_column($user_capabilities, 'id');
    
    // Si no tiene permisos asignados, inicializar según su rol
    if (empty($user_capabilities)) {
        $role_permissions = $pm->getDefaultPermissionsForRole($selected_user['rol']);
        // Obtener los IDs de las capabilities del rol
        foreach ($role_permissions as $permission_name) {
            $capability = fetch("SELECT id FROM capabilities WHERE name = ?", [$permission_name]);
            if ($capability) {
                $user_capabilities_ids[] = $capability['id'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Permisos - NEXUS POS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .capability-checkbox {
            transition: all 0.2s ease;
        }
        .capability-checkbox:checked + label {
            background-color: rgb(34 197 94);
            color: white;
        }
        .category-section {
            transition: all 0.3s ease;
        }
        .category-section:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-gray-900 to-gray-800 text-gray-100">
    <header class="bg-gray-900 shadow-lg">
        <div class="container mx-auto px-6 py-4 flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-purple-400">🔐 GESTIÓN DE PERMISOS</h1>
                <p class="text-sm text-gray-400"><?= strtoupper($user['nombre']) ?> | ADMIN</p>
            </div>
            <a href="dashboard.php" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded">⬅ VOLVER</a>
        </div>
    </header>

    <main class="container mx-auto px-6 py-8">
        <!-- Estadísticas -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-gray-800 p-4 rounded-lg border border-gray-700 text-center">
                <p class="text-3xl font-bold text-blue-400"><?= $stats['total_capabilities'] ?? 0 ?></p>
                <p class="text-gray-400 text-sm">Permisos Disponibles</p>
            </div>
            <div class="bg-gray-800 p-4 rounded-lg border border-gray-700 text-center">
                <p class="text-3xl font-bold text-green-400"><?= $stats['total_assignments'] ?? 0 ?></p>
                <p class="text-gray-400 text-sm">Permisos Asignados</p>
            </div>
            <div class="bg-gray-800 p-4 rounded-lg border border-gray-700 text-center">
                <p class="text-3xl font-bold text-purple-400"><?= $stats['users_with_permissions'] ?? 0 ?></p>
                <p class="text-gray-400 text-sm">Usuarios con Permisos</p>
            </div>
            <div class="bg-gray-800 p-4 rounded-lg border border-gray-700 text-center">
                <p class="text-3xl font-bold text-yellow-400"><?= count($usuarios) ?></p>
                <p class="text-gray-400 text-sm">Total Usuarios</p>
            </div>
        </div>

        <!-- Mensajes -->
        <?php if ($error): ?>
            <div class="bg-red-500 text-white p-4 rounded-lg mb-6"><?= $error ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="bg-green-500 text-white p-4 rounded-lg mb-6"><?= $success ?></div>
        <?php endif; ?>

        <!-- Selección de Usuario -->
        <div class="bg-gray-800 rounded-lg border border-gray-700 mb-6">
            <div class="p-6 border-b border-gray-700">
                <h2 class="text-xl font-bold text-white">👥 Seleccionar Usuario</h2>
                <p class="text-gray-400 text-sm mt-1">Elige un usuario para gestionar sus permisos específicos</p>
            </div>
            
            <div class="p-6">
                <form method="POST" class="flex gap-4 items-end">
                    <input type="hidden" name="action" value="select_user">
                    <div class="flex-1">
                        <label class="block text-gray-400 text-sm mb-2">Usuario</label>
                        <select name="user_id" onchange="this.form.submit()" 
                                class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                            <option value="">-- Seleccionar Usuario --</option>
                            <?php foreach ($usuarios as $usuario): ?>
                                <option value="<?= $usuario['id'] ?>" <?= $selected_user && $selected_user['id'] == $usuario['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($usuario['nombre']) ?> (<?= strtoupper($usuario['rol']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
        </div>

        <!-- Gestión de Roles Personalizados -->
        <div class="bg-gray-800 rounded-lg border border-gray-700 mb-6">
            <div class="p-6 border-b border-gray-700">
                <h2 class="text-xl font-bold text-white">🎭 Roles Personalizados</h2>
                <p class="text-gray-400 text-sm mt-1">Crea y gestiona roles personalizados con permisos específicos</p>
            </div>
            
            <div class="p-6">
                <!-- Formulario para crear nuevo rol -->
                <form method="POST" id="create-role-form" class="mb-6">
                    <input type="hidden" name="action" value="create_role">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">Nombre del Rol</label>
                            <input type="text" name="role_name" required
                                   class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600"
                                   placeholder="Ej: Marketing Manager">
                        </div>
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">Descripción</label>
                            <input type="text" name="role_description" required
                                   class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600"
                                   placeholder="Ej: Gestiona banners, imágenes y promociones">
                        </div>
                    </div>
                    
                    <button type="submit" 
                            class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded text-sm">
                        ➕ Crear Nuevo Rol
                    </button>
                </form>
                
                <!-- Lista de roles existentes -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($role_templates as $role): ?>
                        <div class="bg-gray-900 p-4 rounded">
                            <div class="flex justify-between items-start mb-2">
                                <h4 class="text-white font-bold"><?= htmlspecialchars($role['name']) ?></h4>
                                <?php if (!$role['is_system']): ?>
                                    <button type="button" 
                                            onclick="deleteRole(<?= $role['id'] ?>)"
                                            class="text-red-400 hover:text-red-300 text-sm">
                                        🗑️
                                    </button>
                                <?php endif; ?>
                            </div>
                            <p class="text-gray-400 text-sm mb-2"><?= htmlspecialchars($role['description']) ?></p>
                            <div class="flex justify-between text-xs">
                                <span class="text-gray-500"><?= $role['permissions_count'] ?> permisos</span>
                                <span class="text-gray-500"><?= $role['users_count'] ?> usuarios</span>
                            </div>
                            <?php if ($role['is_system']): ?>
                                <span class="text-xs bg-blue-600 text-white px-2 py-1 rounded mt-2 inline-block">
                                    🔒 Sistema
                                </span>
                            <?php else: ?>
                                <span class="text-xs bg-purple-600 text-white px-2 py-1 rounded mt-2 inline-block">
                                    👤 Personalizado
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Gestión de Permisos -->
        <?php if ($selected_user): ?>
        <form method="POST" id="permissions-form">
            <input type="hidden" name="action" value="update_permissions">
            <input type="hidden" name="user_id" value="<?= $selected_user['id'] ?>">
            
            <div class="bg-gray-800 rounded-lg border border-gray-700 mb-6">
                <div class="p-6 border-b border-gray-700 flex justify-between items-center">
                    <div>
                        <h2 class="text-xl font-bold text-white">🔐 Permisos de <?= htmlspecialchars($selected_user['nombre']) ?></h2>
                        <p class="text-gray-400 text-sm mt-1">Rol actual: <?= strtoupper($selected_user['rol']) ?></p>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" onclick="initializeRolePermissions()" 
                                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm">
                            🔄 Inicializar por Rol
                        </button>
                        <button type="submit" 
                                class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded text-sm">
                            💾 Guardar Permisos
                        </button>
                    </div>
                </div>
                
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($available_capabilities as $category => $capabilities): ?>
                            <div class="category-section bg-gray-900 rounded-lg p-4">
                                <h3 class="text-lg font-bold text-white mb-4 flex items-center">
                                    <span class="text-2xl mr-2">
                                        <?php
                                        $icons = [
                                            'usuarios' => '👥',
                                            'productos' => '📦',
                                            'ventas' => '💰',
                                            'clientes' => '👤',
                                            'finanzas' => '📊',
                                            'contabilidad' => '🧮',
                                            'camaras' => '📹',
                                            'configuracion' => '⚙️',
                                            'reportes' => '📈',
                                            'inventario' => '📋',
                                            'caja' => '💵',
                                            'presupuestos' => '📄',
                                            'marketing' => '🎨',
                                            'sistema' => '🔧'
                                        ];
                                        echo $icons[$category] ?? '📌';
                                        ?>
                                    </span>
                                    <?= ucfirst($category) ?>
                                </h3>
                                
                                <div class="space-y-2">
                                    <?php foreach ($capabilities as $capability): ?>
                                        <div class="flex items-center justify-between p-2 bg-gray-800 rounded hover:bg-gray-700 transition-colors">
                                            <div class="flex items-center">
                                                <input type="checkbox" 
                                                       name="capabilities[]" 
                                                       value="<?= $capability['id'] ?>" 
                                                       id="cap_<?= $capability['id'] ?>"
                                                       class="capability-checkbox mr-2"
                                                       <?= in_array($capability['id'], $user_capabilities_ids ?? []) ? 'checked' : '' ?>>
                                                <label for="cap_<?= $capability['id'] ?>" 
                                                       class="text-sm text-gray-300 cursor-pointer flex-1">
                                                    <?= htmlspecialchars($capability['description']) ?>
                                                </label>
                                            </div>
                                            <span class="text-xs text-gray-500">
                                                <?= $capability['module'] ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </form>

        <!-- Resumen de Permisos Actuales -->
        <div class="bg-gray-800 rounded-lg border border-gray-700">
            <div class="p-6 border-b border-gray-700">
                <h3 class="text-lg font-bold text-white">📋 Resumen de Permisos Actuales</h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <?php 
                    $user_perms_by_category = [];
                    foreach ($user_capabilities as $cap) {
                        $user_perms_by_category[$cap['category']][] = $cap;
                    }
                    
                    foreach ($user_perms_by_category as $category => $perms): ?>
                        <div class="bg-gray-900 p-3 rounded">
                            <h4 class="text-sm font-bold text-white mb-2"><?= ucfirst($category) ?></h4>
                            <div class="text-xs text-gray-400">
                                <?= count($perms) ?> permisos
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <script>
        function initializeRolePermissions() {
            if (confirm('¿Deseas inicializar los permisos por defecto para este rol? Esto reemplazará todos los permisos actuales.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="initialize_role_permissions">
                    <input type="hidden" name="user_id" value="<?= $selected_user['id'] ?? '' ?>">
                    <input type="hidden" name="role" value="<?= $selected_user['rol'] ?? '' ?>">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Función para marcar/desmarcar todos los permisos de una categoría
        function toggleCategory(categoryElement, checked) {
            const checkboxes = categoryElement.querySelectorAll('.capability-checkbox');
            checkboxes.forEach(cb => cb.checked = checked);
        }

        // Función para eliminar rol
        function deleteRole(roleId) {
            if (confirm('¿Estás seguro de eliminar este rol personalizado? Esta acción no se puede deshacer.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_role">
                    <input type="hidden" name="role_id" value="${roleId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Mejorar experiencia de usuario
        document.addEventListener('DOMContentLoaded', function() {
            // Contador de permisos seleccionados
            const form = document.getElementById('permissions-form');
            if (form) {
                const checkboxes = form.querySelectorAll('.capability-checkbox');
                const updateCount = () => {
                    const checked = form.querySelectorAll('.capability-checkbox:checked').length;
                    console.log('Permisos seleccionados:', checked);
                };
                
                checkboxes.forEach(cb => cb.addEventListener('change', updateCount));
            }
        });
    </script>
</body>
</html>