<?php
require_once 'config.php';
requireLogin();

$user = getUser();
$empresa_id = $user['empresa_id'];

// Config del negocio
$config = fetch("SELECT nombre_negocio FROM nombre_negocio WHERE empresa_id = ?", [$empresa_id]);
$nombre_negocio = $config['nombre_negocio'] ?? 'NEXUS POS';

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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - NEXUS POS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-gray-900 to-gray-800 text-gray-100">
    <header class="bg-gray-900 shadow-lg">
        <div class="container mx-auto px-6 py-4 flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-purple-400"><?= strtoupper($nombre_negocio) ?></h1>
                <p class="text-sm text-gray-400"><?= strtoupper($user['nombre']) ?> | ID: <?= $empresa_id ?> | <?= strtoupper($user['rol']) ?></p>
            </div>
            <a href="logout.php" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded">⬅ SALIR</a>
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

        <!-- Menú -->
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
            <?php if ($user['rol'] === 'admin'): ?>
                <a href="empresas.php" class="bg-gray-800 p-6 rounded-lg border border-gray-700 hover:border-green-500 text-center">
                    <div class="text-4xl mb-2">🏢</div>
                    <p class="text-white font-bold">EMPRESAS</p>
                </a>
                <a href="usuarios.php" class="bg-gray-800 p-6 rounded-lg border border-gray-700 hover:border-orange-500 text-center">
                    <div class="text-4xl mb-2">👥</div>
                    <p class="text-white font-bold">USUARIOS</p>
                </a>
                <a href="configurar.php" class="bg-gray-800 p-6 rounded-lg border border-gray-700 hover:border-purple-500 text-center">
                    <div class="text-4xl mb-2">⚙️</div>
                    <p class="text-white font-bold">CONFIGURAR</p>
                </a>
            <?php endif; ?>
            
            <?php if ($user['rol'] === 'admin' || $user['rol'] === 'jefe'): ?>
                <a href="productos.php" class="bg-gray-800 p-6 rounded-lg border border-gray-700 hover:border-blue-500 text-center">
                    <div class="text-4xl mb-2">📦</div>
                    <p class="text-white font-bold">PRODUCTOS</p>
                </a>
                <a href="finanzas.php" class="bg-gray-800 p-6 rounded-lg border border-gray-700 hover:border-purple-500 text-center">
                    <div class="text-4xl mb-2">💰</div>
                    <p class="text-white font-bold">FINANZAS</p>
                </a>
                <a href="contabilidad.php" class="bg-gray-800 p-6 rounded-lg border border-gray-700 hover:border-green-500 text-center">
                    <div class="text-4xl mb-2">🧮</div>
                    <p class="text-white font-bold">CONTABILIDAD</p>
                </a>
                <a href="clientes.php" class="bg-gray-800 p-6 rounded-lg border border-gray-700 hover:border-teal-500 text-center">
                    <div class="text-4xl mb-2">👤</div>
                    <p class="text-white font-bold">CLIENTES</p>
                </a>
                <a href="promociones.php" class="bg-gray-800 p-6 rounded-lg border border-gray-700 hover:border-pink-500 text-center">
                    <div class="text-4xl mb-2">🎟️</div>
                    <p class="text-white font-bold">PROMOS</p>
                </a>
                <a href="imagenes.php" class="bg-gray-800 p-6 rounded-lg border border-gray-700 hover:border-red-500 text-center">
                    <div class="text-4xl mb-2">🎨</div>
                    <p class="text-white font-bold">IMÁGENES</p>
                </a>
                <a href="banners.php" class="bg-gray-800 p-6 rounded-lg border border-gray-700 hover:border-purple-500 text-center">
                    <div class="text-4xl mb-2">🖼️</div>
                    <p class="text-white font-bold">BANNERS</p>
                </a>
                <a href="reportes.php" class="bg-gray-800 p-6 rounded-lg border border-gray-700 hover:border-purple-500 text-center">
                    <div class="text-4xl mb-2">📊</div>
                    <p class="text-white font-bold">REPORTES</p>
                </a>
                <a href="whatsapp.php" class="bg-gray-800 p-6 rounded-lg border border-gray-700 hover:border-green-500 text-center">
                    <div class="text-4xl mb-2">💬</div>
                    <p class="text-white font-bold">WHATSAPP</p>
                </a>
                <a href="etiquetas.php" class="bg-gray-800 p-6 rounded-lg border border-gray-700 hover:border-blue-500 text-center">
                    <div class="text-4xl mb-2">🏷️</div>
                    <p class="text-white font-bold">ETIQUETAS</p>
                </a>
            <?php endif; ?>
            
            <a href="camaras.php" class="bg-gray-800 p-6 rounded-lg border border-gray-700 hover:border-blue-500 text-center">
                <div class="text-4xl mb-2">📹</div>
                <p class="text-white font-bold">CÁMARAS</p>
            </a>
            
            <a href="reconocimiento_facial.php" class="bg-gray-800 p-6 rounded-lg border border-gray-700 hover:border-purple-500 text-center">
                <div class="text-4xl mb-2">🤖</div>
                <p class="text-white font-bold">RECONOCIMIENTO</p>
            </a>
            
            <a href="presupuestos.php" class="bg-gray-800 p-6 rounded-lg border border-gray-700 hover:border-blue-500 text-center">
                    <div class="text-4xl mb-2">📋</div>
                    <p class="text-white font-bold">PRESUPUESTOS</p>
                </a>
                <a href="avisos.php" class="bg-gray-800 p-6 rounded-lg border border-gray-700 hover:border-yellow-500 text-center">
                    <div class="text-4xl mb-2">📢</div>
                    <p class="text-white font-bold">AVISOS</p>
                </a>
                <a href="ventas.php" class="bg-gray-800 p-6 rounded-lg border border-gray-700 hover:border-green-500 text-center">
                    <div class="text-4xl mb-2">🛒</div>
                    <p class="text-white font-bold">VENTAS</p>
                </a>
        </div>
    </main>
</body>
</html>
