<?php
require_once 'config.php';
requireLogin();

$user = getUser();

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $id = $_POST['id'] ?? null;
    $nombre = trim($_POST['nombre'] ?? '');
    $cuit = trim($_POST['cuit'] ?? '');
    
    if ($accion === 'guardar') {
        if ($id) {
            query("UPDATE empresas SET nombre=?, cuit=? WHERE id=?", [$nombre, $cuit, $id]);
        } else {
            query("INSERT INTO empresas (nombre, cuit, activo) VALUES (?, ?, 1)", [$nombre, $cuit]);
            $empresa_id = $db->lastInsertId();
            
            // Crear estructura de archivos para la nueva empresa
            require_once 'lib/empresa_files.php';
            $empresa_files = new EmpresaFiles($empresa_id);
            $empresa_files->crearEstructura();
            $empresa_files->configurarRutasEnDB($db);
        }
        header('Location: empresas.php');
        exit;
    }
    
    if ($accion === 'toggle_estado' && $id) {
        $db = getDB();
        try {
            $db->beginTransaction();
            $stmt = $db->prepare("SELECT activo FROM empresas WHERE id=?");
            $stmt->execute([$id]);
            $empresa = $stmt->fetch();

            if ($empresa) {
                $nuevo_estado = $empresa['activo'] ? 0 : 1;
                $stmt = $db->prepare("UPDATE empresas SET activo=? WHERE id=?");
                $stmt->execute([$nuevo_estado, $id]);
            }
            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
        }
        header('Location: empresas.php');
        exit;
    }
    
    if ($accion === 'eliminar' && $id) {
        if (confirm('¿Eliminar esta empresa definitivamente?')) {
            query("DELETE FROM empresas WHERE id=?", [$id]);
            header('Location: empresas.php');
            exit;
        }
    }
}

$empresas = fetchAll("SELECT id, nombre, cuit, activo FROM empresas ORDER BY id ASC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Empresas - NEXUS POS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-gray-900 to-gray-800 text-gray-100">
    <header class="bg-gray-900 shadow-lg">
        <div class="container mx-auto px-6 py-4 flex justify-between items-center">
            <h1 class="text-2xl font-bold text-green-400">🏢 GESTOR DE EMPRESAS</h1>
            <a href="dashboard.php" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded">VOLVER</a>
        </div>
    </header>
    <main class="container mx-auto px-6 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Formulario -->
            <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
                <h3 class="text-xl font-bold text-white mb-4" id="formTitle">Nueva Empresa</h3>
                <form method="POST" id="empresaForm">
                    <input type="hidden" name="accion" value="guardar">
                    <input type="hidden" name="id" id="empresaId" value="">

                    <div class="space-y-4">
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">ID Empresa</label>
                            <input type="number" id="inputId" readonly
                                   class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                        </div>
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">Nombre de Negocio</label>
                            <input type="text" name="nombre" id="nombre" required
                                   class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                        </div>
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">CUIT</label>
                            <input type="text" name="cuit" id="cuit"
                                   class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                        </div>
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 rounded font-bold">
                            💾 GUARDAR CAMBIOS
                        </button>
                        <button type="button" onclick="toggleEstado()" id="btnToggle" disabled
                                class="w-full bg-gray-600 hover:bg-gray-700 text-white py-2 rounded disabled:opacity-50">
                            DESACTIVAR / ACTIVAR
                        </button>
                        <button type="button" onclick="limpiarFormulario()" class="w-full bg-gray-600 hover:bg-gray-700 text-white py-2 rounded">
                            NUEVA / LIMPIAR
                        </button>
                        <button type="button" onclick="eliminarEmpresa()" id="btnEliminar" disabled
                                class="w-full bg-red-600 hover:bg-red-700 text-white py-2 rounded disabled:opacity-50">
                            🗑️ ELIMINAR DEFINITIVAMENTE
                        </button>
                    </div>
                </form>

                <!-- Formulario dedicado para toggle -->
                <form method="POST" id="toggleForm" style="display:none;">
                    <input type="hidden" name="accion" value="toggle_estado">
                    <input type="hidden" name="id" id="toggleId" value="">
                </form>
            </div>
            
            <!-- Tabla -->
            <div class="lg:col-span-2 bg-gray-800 rounded-lg border border-gray-700 p-6">
                <h3 class="text-xl font-bold text-white mb-4">Empresas Registradas</h3>
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-700">
                            <th class="text-left py-3 px-4 text-gray-400">ID</th>
                            <th class="text-left py-3 px-4 text-gray-400">Nombre</th>
                            <th class="text-left py-3 px-4 text-gray-400">CUIT</th>
                            <th class="text-left py-3 px-4 text-gray-400">Estado</th>
                            <th class="text-left py-3 px-4 text-gray-400">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($empresas as $e): ?>
                        <tr class="border-b border-gray-700 hover:bg-gray-700 cursor-pointer" onclick="seleccionarEmpresa(<?= $e['id'] ?>, '<?= htmlspecialchars($e['nombre']) ?>', '<?= htmlspecialchars($e['cuit']) ?>', <?= $e['activo'] ?>)">
                            <td class="py-3 px-4"><?= $e['id'] ?></td>
                            <td class="py-3 px-4"><?= htmlspecialchars($e['nombre']) ?></td>
                            <td class="py-3 px-4"><?= htmlspecialchars($e['cuit']) ?></td>
                            <td class="py-3 px-4">
                                <span class="<?= $e['activo'] ? 'bg-green-600' : 'bg-red-600' ?> px-2 py-1 rounded text-sm">
                                    <?= $e['activo'] ? '✅ ACTIVO' : '❌ INACTIVO' ?>
                                </span>
                            </td>
                            <td class="py-3 px-4">
                                <button onclick="event.stopPropagation(); toggleEstadoDirecto(<?= $e['id'] ?>)" 
                                        class="text-blue-400 hover:text-blue-300 mr-2">Estado</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        let estadoActual = 1;
        
        function seleccionarEmpresa(id, nombre, cuit, activo) {
            document.getElementById('formTitle').textContent = 'Editar Empresa';
            document.getElementById('empresaId').value = id;
            document.getElementById('inputId').value = id;
            document.getElementById('nombre').value = nombre;
            document.getElementById('cuit').value = cuit;
            estadoActual = activo;
            document.getElementById('btnToggle').disabled = false;
            document.getElementById('btnEliminar').disabled = false;
        }
        
        function toggleEstado() {
            const id = document.getElementById('empresaId').value;
            if (!id) return;

            const accion = estadoActual === 1 ? 'DESACTIVAR' : 'ACTIVAR';
            if (confirm(`¿Desea ${accion} la empresa ID ${id}?`)) {
                document.getElementById('toggleId').value = id;
                document.getElementById('toggleForm').submit();
            }
        }

        function toggleEstadoDirecto(id) {
            const fila = event.target.closest('tr');
            const estadoTexto = fila.querySelector('td:nth-child(4) span').textContent;
            const esActivo = estadoTexto.includes('ACTIVO');
            const accion = esActivo ? 'DESACTIVAR' : 'ACTIVAR';

            if (confirm(`¿Desea ${accion} la empresa ID ${id}?`)) {
                document.getElementById('toggleId').value = id;
                document.getElementById('toggleForm').submit();
            }
        }
        
        function eliminarEmpresa() {
            const id = document.getElementById('empresaId').value;
            if (!id) return;
            
            if (confirm('¿Eliminar esta empresa definitivamente?')) {
                const form = document.getElementById('empresaForm');
                form.action.value = 'eliminar';
                form.submit();
            }
        }
        
        function limpiarFormulario() {
            document.getElementById('formTitle').textContent = 'Nueva Empresa';
            document.getElementById('empresaId').value = '';
            document.getElementById('inputId').value = '';
            document.getElementById('nombre').value = '';
            document.getElementById('cuit').value = '';
            estadoActual = 1;
            document.getElementById('btnToggle').disabled = true;
            document.getElementById('btnEliminar').disabled = true;
        }
    </script>
</body>
</html>
