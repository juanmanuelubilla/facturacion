<?php
require_once 'config.php';
requireLogin();

$user = getUser();
$empresa_id = $user['empresa_id'];

// Config del negocio
$config = fetch("SELECT nombre_negocio FROM nombre_negocio WHERE empresa_id = ?", [$empresa_id]);
$nombre_negocio = $config['nombre_negocio'] ?? 'WARP POS';

// Stats
$hoy = date('Y-m-d');
error_log("Dashboard - Fecha hoy: $hoy");
error_log("Dashboard - Empresa ID: $empresa_id");
$stats = [
    'ventas_hoy' => fetch("SELECT COUNT(*) as total, SUM(total) as monto FROM ventas WHERE DATE(fecha) = ? AND empresa_id = ?", [$hoy, $empresa_id]),
    'productos' => fetch("SELECT COUNT(*) as total FROM productos WHERE activo = 1 AND empresa_id = ?", [$empresa_id]),
    'clientes' => fetch("SELECT COUNT(*) as total FROM clientes WHERE empresa_id = ?", [$empresa_id])
];
error_log("Dashboard - Ventas hoy: " . ($stats['ventas_hoy']['total'] ?? 0) . ", Monto: " . ($stats['ventas_hoy']['monto'] ?? 0));

// Cámaras Stats (solo admin)
if ($user['rol'] === 'admin') {
    $camera_stats = [
        'total_camaras' => fetch("SELECT COUNT(*) as total FROM camaras WHERE empresa_id = ?", [$empresa_id]),
        'activas' => fetch("SELECT COUNT(*) as total FROM camaras WHERE empresa_id = ? AND activo = 1", [$empresa_id]),
        'eventos_hoy' => fetch("SELECT COUNT(*) as total FROM eventos_camara WHERE DATE(fecha) = ? AND empresa_id = ?", [$hoy, $empresa_id]),
        'alertas_hoy' => fetch("SELECT COUNT(*) as total FROM security_alerts WHERE DATE(timestamp) = ? AND camera_id IN (SELECT id FROM camaras WHERE empresa_id = ?)", [$hoy, $empresa_id])
    ];
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - WARP POS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Drag & Drop styles */
        .dragging {
            opacity: 0.5;
            transform: scale(0.95);
        }
        
        .drag-over {
            border: 2px dashed #3b82f6;
            background: #1e3a8a;
        }
        
        .edit-mode {
            cursor: move !important;
            border: 2px solid #3b82f6 !important;
        }
        
        .notification {
            position: fixed;
            top: 1rem;
            right: 1rem;
            padding: 1rem 1.5rem;
            border-radius: 0.5rem;
            color: white;
            font-weight: 500;
            z-index: 1000;
            animation: slideIn 0.3s ease-out;
        }
        
        .notification.success { background: #10b981; }
        .notification.error { background: #ef4444; }
        .notification.info { background: #3b82f6; }
        
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-gray-900 to-gray-800 text-gray-100">
    <header class="bg-gray-900 shadow-lg">
        <div class="container mx-auto px-6 py-4 flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-purple-400"><?= strtoupper($nombre_negocio) ?></h1>
                <p class="text-sm text-gray-400"><?= strtoupper($user['nombre']) ?> | ID: <?= $empresa_id ?> | <?= strtoupper($user['rol']) ?></p>
            </div>
            <div class="flex items-center gap-4">
                <button id="edit-mode-btn" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm" onclick="toggleEditMode()">
                    ✏️ Editar Orden
                </button>
                <a href="logout.php" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded">⬅ SALIR</a>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-6 py-8">
        <h2 class="text-3xl font-bold text-white mb-2">DASHBOARD</h2>
        
        <!-- Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-gray-800 p-6 rounded-lg border border-gray-700">
                <p class="text-gray-400">Ventas Hoy</p>
                <p class="text-3xl font-bold text-green-400">$<?= number_format($stats['ventas_hoy']['monto'] ?? 0, 2) ?></p>
            </div>
            <div class="bg-gray-800 p-6 rounded-lg border border-gray-700">
                <p class="text-gray-400">Productos</p>
                <p class="text-3xl font-bold text-blue-400"><?= $stats['productos']['total'] ?? 0 ?></p>
            </div>
            <div class="bg-gray-800 p-6 rounded-lg border border-gray-700">
                <p class="text-gray-400">Clientes</p>
                <p class="text-3xl font-bold text-purple-400"><?= $stats['clientes']['total'] ?? 0 ?></p>
            </div>
        </div>

        
        <!-- Análisis de Cámaras (solo admin) -->
        <?php if ($user['rol'] === 'admin'): ?>
        <div class="bg-gray-800 p-6 rounded-lg border border-gray-700 mb-8">
            <h3 class="text-xl font-bold text-white mb-4">📹 ANÁLISIS DE CÁMARAS</h3>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-gray-700 p-4 rounded">
                    <p class="text-gray-400 text-sm">Total Cámaras</p>
                    <p class="text-2xl font-bold text-blue-400"><?= $camera_stats['total_camaras']['total'] ?? 0 ?></p>
                </div>
                <div class="bg-gray-700 p-4 rounded">
                    <p class="text-gray-400 text-sm">Activas</p>
                    <p class="text-2xl font-bold text-green-400"><?= $camera_stats['activas']['total'] ?? 0 ?></p>
                </div>
                <div class="bg-gray-700 p-4 rounded">
                    <p class="text-gray-400 text-sm">Eventos Hoy</p>
                    <p class="text-2xl font-bold text-purple-400"><?= $camera_stats['eventos_hoy']['total'] ?? 0 ?></p>
                </div>
                <div class="bg-gray-700 p-4 rounded">
                    <p class="text-gray-400 text-sm">Alertas Hoy</p>
                    <p class="text-2xl font-bold text-orange-400"><?= $camera_stats['alertas_hoy']['total'] ?? 0 ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Menú -->
        <div id="dashboard-modules" class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
            <?php if ($user['rol'] === 'admin'): ?>
                <a href="empresas.php" class="dashboard-module bg-gray-800 p-6 rounded-lg border border-gray-700 hover:border-green-500 text-center" data-module="empresas">
                    <div class="text-4xl mb-2">🏢</div>
                    <p class="text-white font-bold">EMPRESAS</p>
                </a>
                <a href="usuarios.php" class="dashboard-module bg-gray-800 p-6 rounded-lg border border-gray-700 hover:border-orange-500 text-center" data-module="usuarios">
                    <div class="text-4xl mb-2">👥</div>
                    <p class="text-white font-bold">USUARIOS</p>
                </a>
                <a href="configurar.php" class="dashboard-module bg-gray-800 p-6 rounded-lg border border-gray-700 hover:border-purple-500 text-center" data-module="configurar">
                    <div class="text-4xl mb-2">⚙️</div>
                    <p class="text-white font-bold">CONFIGURAR</p>
                </a>
                <a href="configuracion_urls.php" class="dashboard-module bg-gray-800 p-6 rounded-lg border border-gray-700 hover:border-blue-500 text-center" data-module="urls">
                    <div class="text-4xl mb-2">🌐</div>
                    <p class="text-white font-bold">URLS</p>
                </a>
                <a href="permisos.php" class="dashboard-module bg-gray-800 p-6 rounded-lg border border-gray-700 hover:border-indigo-500 text-center" data-module="permisos">
                    <div class="text-4xl mb-2">🔐</div>
                    <p class="text-white font-bold">PERMISOS</p>
                </a>
            <?php endif; ?>
            
            <?php if ($user['rol'] === 'admin' || $user['rol'] === 'jefe'): ?>
                <a href="productos.php" class="dashboard-module bg-gray-800 p-6 rounded-lg border border-gray-700 hover:border-blue-500 text-center" data-module="productos">
                    <div class="text-4xl mb-2">📦</div>
                    <p class="text-white font-bold">PRODUCTOS</p>
                </a>
                <a href="finanzas.php" class="dashboard-module bg-gray-800 p-6 rounded-lg border border-gray-700 hover:border-purple-500 text-center" data-module="finanzas">
                    <div class="text-4xl mb-2">💰</div>
                    <p class="text-white font-bold">FINANZAS</p>
                </a>
                <a href="contabilidad.php" class="dashboard-module bg-gray-800 p-6 rounded-lg border border-gray-700 hover:border-green-500 text-center" data-module="contabilidad">
                    <div class="text-4xl mb-2">🧮</div>
                    <p class="text-white font-bold">CONTABILIDAD</p>
                </a>
            <?php endif; ?>

            <?php if ($user['rol'] === 'admin' || $user['rol'] === 'jefe' || $user['rol'] === 'cajero'): ?>
                <a href="inventario.php" class="dashboard-module bg-gray-800 p-6 rounded-lg border border-gray-700 hover:border-orange-500 text-center" data-module="inventario">
                    <div class="text-4xl mb-2">🗃️</div>
                    <p class="text-white font-bold">INVENTARIO</p>
                </a>
                <a href="caja.php" class="dashboard-module bg-gray-800 p-6 rounded-lg border border-gray-700 hover:border-green-500 text-center" data-module="caja">
                    <div class="text-4xl mb-2">💵</div>
                    <p class="text-white font-bold">CAJA</p>
                </a>
            <?php endif; ?>

            <?php if ($user['rol'] === 'admin' || $user['rol'] === 'jefe'): ?>
                <a href="imagenes.php" class="dashboard-module bg-gray-800 p-6 rounded-lg border border-gray-700 hover:border-red-500 text-center" data-module="imagenes">
                    <div class="text-4xl mb-2">🎨</div>
                    <p class="text-white font-bold">IMÁGENES</p>
                </a>
                <a href="banners.php" class="dashboard-module bg-gray-800 p-6 rounded-lg border border-gray-700 hover:border-purple-500 text-center" data-module="banners">
                    <div class="text-4xl mb-2">🖼️</div>
                    <p class="text-white font-bold">BANNERS</p>
                </a>
                <a href="reportes.php" class="dashboard-module bg-gray-800 p-6 rounded-lg border border-gray-700 hover:border-purple-500 text-center" data-module="reportes">
                    <div class="text-4xl mb-2">📊</div>
                    <p class="text-white font-bold">REPORTES</p>
                </a>
                <a href="whatsapp.php" class="dashboard-module bg-gray-800 p-6 rounded-lg border border-gray-700 hover:border-green-500 text-center" data-module="whatsapp">
                    <div class="text-4xl mb-2">💬</div>
                    <p class="text-white font-bold">WHATSAPP</p>
                </a>
                <a href="etiquetas.php" class="dashboard-module bg-gray-800 p-6 rounded-lg border border-gray-700 hover:border-blue-500 text-center" data-module="etiquetas">
                    <div class="text-4xl mb-2">🏷️</div>
                    <p class="text-white font-bold">ETIQUETAS</p>
                </a>
            <?php endif; ?>
            
            <a href="camaras.php" class="dashboard-module bg-gray-800 p-6 rounded-lg border border-gray-700 hover:border-blue-500 text-center" data-module="camaras">
                <div class="text-4xl mb-2">📹</div>
                <p class="text-white font-bold">CÁMARAS</p>
            </a>
            
            <a href="reconocimiento_facial.php" class="dashboard-module bg-gray-800 p-6 rounded-lg border border-gray-700 hover:border-purple-500 text-center" data-module="reconocimiento">
                <div class="text-4xl mb-2">🤖</div>
                <p class="text-white font-bold">RECONOCIMIENTO</p>
            </a>
            
            <a href="presupuestos.php" class="dashboard-module bg-gray-800 p-6 rounded-lg border border-gray-700 hover:border-blue-500 text-center" data-module="presupuestos">
                <div class="text-4xl mb-2">📋</div>
                <p class="text-white font-bold">PRESUPUESTOS</p>
            </a>
            <a href="avisos.php" class="dashboard-module bg-gray-800 p-6 rounded-lg border border-gray-700 hover:border-yellow-500 text-center" data-module="avisos">
                <div class="text-4xl mb-2">📢</div>
                <p class="text-white font-bold">AVISOS</p>
            </a>
            <a href="ventas.php" class="dashboard-module bg-gray-800 p-6 rounded-lg border border-gray-700 hover:border-green-500 text-center" data-module="ventas">
                <div class="text-4xl mb-2">🛒</div>
                <p class="text-white font-bold">VENTAS</p>
            </a>
        </div>
    </main>

    <script>
        let isEditMode = false;
        let draggedElement = null;

        // Toggle edit mode
        function toggleEditMode() {
            const btn = document.getElementById('edit-mode-btn');
            const modules = document.querySelectorAll('.dashboard-module');
            
            isEditMode = !isEditMode;
            
            if (isEditMode) {
                btn.textContent = '💾 Guardar Orden';
                btn.classList.remove('bg-blue-600', 'hover:bg-blue-700');
                btn.classList.add('bg-green-600', 'hover:bg-green-700');
                
                modules.forEach(module => {
                    module.draggable = true;
                    module.classList.add('edit-mode');
                });
                
                showNotification('Modo edición activado. Arrastra los módulos para reordenar.', 'info');
            } else {
                btn.textContent = '✏️ Editar Orden';
                btn.classList.remove('bg-green-600', 'hover:bg-green-700');
                btn.classList.add('bg-blue-600', 'hover:bg-blue-700');
                
                modules.forEach(module => {
                    module.draggable = false;
                    module.classList.remove('edit-mode');
                });
                
                saveDashboardLayout();
            }
        }

        // Drag and drop handlers
        document.addEventListener('DOMContentLoaded', function() {
            const modules = document.querySelectorAll('.dashboard-module');
            const container = document.getElementById('dashboard-modules');
            
            modules.forEach(module => {
                module.addEventListener('dragstart', handleDragStart);
                module.addEventListener('dragend', handleDragEnd);
            });
            
            container.addEventListener('dragover', handleDragOver);
            container.addEventListener('drop', handleDrop);
            
            // Load saved layout
            loadUserLayout();
            
                    });

        function handleDragStart(e) {
            if (!isEditMode) return;
            
            draggedElement = this;
            this.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/html', this.innerHTML);
        }

        function handleDragEnd(e) {
            if (!isEditMode) return;
            
            this.classList.remove('dragging');
            draggedElement = null;
        }

        function handleDragOver(e) {
            if (!isEditMode) return;
            
            if (e.preventDefault) {
                e.preventDefault();
            }
            
            e.dataTransfer.dropEffect = 'move';
            
            const afterElement = getDragAfterElement(document.getElementById('dashboard-modules'), e.clientX, e.clientY);
            if (afterElement == null) {
                document.getElementById('dashboard-modules').appendChild(draggedElement);
            } else {
                document.getElementById('dashboard-modules').insertBefore(draggedElement, afterElement);
            }
        }

        function handleDrop(e) {
            if (!isEditMode) return;
            
            if (e.stopPropagation) {
                e.stopPropagation();
            }
            
            return false;
        }

        function getDragAfterElement(container, x, y) {
            const draggableElements = [...container.querySelectorAll('.dashboard-module:not(.dragging)')];
            
            return draggableElements.reduce((closest, child) => {
                const box = child.getBoundingClientRect();
                const offset = y - box.top - box.height / 2;
                
                if (offset < 0 && offset > closest.offset) {
                    return { offset: offset, element: child };
                } else {
                    return closest;
                }
            }, { offset: Number.NEGATIVE_INFINITY }).element;
        }

        // Save and load layout
        function saveDashboardLayout() {
            const modules = document.querySelectorAll('.dashboard-module');
            const layout = Array.from(modules).map((module, index) => ({
                module_key: module.dataset.module,
                module_order: index,
                is_visible: true
            }));
            
            fetch('api/dashboard_layout.php?action=save', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ layout: layout })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Orden guardado correctamente', 'success');
                } else {
                    showNotification('Error al guardar orden', 'error');
                }
            })
            .catch(error => {
                console.error('Error saving layout:', error);
                showNotification('Error al guardar orden', 'error');
            });
        }

        function loadUserLayout() {
            fetch('api/dashboard_layout.php?action=load')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.layout) {
                        applyLayout(data.layout);
                    }
                })
                .catch(error => {
                    console.error('Error loading layout:', error);
                });
        }

        function applyLayout(layout) {
            const container = document.getElementById('dashboard-modules');
            const modules = Array.from(container.querySelectorAll('.dashboard-module'));
            
            // Ordenar los módulos según el layout guardado
            layout.sort((a, b) => a.module_order - b.module_order);
            
            // Limpiar el contenedor y reordenar
            layout.forEach(item => {
                const module = modules.find(m => m.dataset.module === item.module_key);
                if (module) {
                    container.appendChild(module);
                }
            });
        }

                
                
                
        function getTimeAgo(timestamp) {
            const now = new Date();
            const time = new Date(timestamp);
            const diff = Math.floor((now - time) / 1000); // segundos
            
            if (diff < 60) return 'Ahora';
            if (diff < 3600) return `Hace ${Math.floor(diff / 60)} min`;
            if (diff < 86400) return `Hace ${Math.floor(diff / 3600)} h`;
            return `Hace ${Math.floor(diff / 86400)} d`;
        }
        
        
        // Notification system
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.textContent = message;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }
        
        // Cargar layout guardado al iniciar
        document.addEventListener('DOMContentLoaded', function() {
            loadUserLayout();
        });
    </script>
    
    <!-- Popup de Reconocimiento Facial -->
    <?php include 'components/popup_cliente_reconocido.html'; ?>
</body>
</html>