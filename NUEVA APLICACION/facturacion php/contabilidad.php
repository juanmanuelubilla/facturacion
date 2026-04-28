<?php
require_once 'config.php';
require_once 'contabilidad_motor.php';
requireLogin();

$user = getUser();
$empresa_id = $user['empresa_id'];
$db = getDB();

// Verificar si tiene plan de cuentas cargado
$stmt = $db->prepare("SELECT COUNT(*) as total FROM plan_cuentas WHERE empresa_id = ?");
$stmt->execute([$empresa_id]);
$cuentas_existentes = $stmt->fetch()['total'];

// Obtener resumen de cuentas
$motor = new MotorContable($empresa_id, $db);
$resumen_cuentas = $motor->getResumenCuentas();

// Calcular totales por tipo
$totales = [
    'ACTIVO' => ['debe' => 0, 'haber' => 0, 'saldo' => 0],
    'PASIVO' => ['debe' => 0, 'haber' => 0, 'saldo' => 0],
    'PATRIMONIO_NETO' => ['debe' => 0, 'haber' => 0, 'saldo' => 0],
    'INGRESO' => ['debe' => 0, 'haber' => 0, 'saldo' => 0],
    'GASTO' => ['debe' => 0, 'haber' => 0, 'saldo' => 0]
];

// Obtener saldo específico de caja
$saldo_caja = 0;

foreach ($resumen_cuentas as $cuenta) {
    if (isset($totales[$cuenta['tipo']])) {
        $totales[$cuenta['tipo']]['debe'] += $cuenta['total_debe'];
        $totales[$cuenta['tipo']]['haber'] += $cuenta['total_haber'];
        $totales[$cuenta['tipo']]['saldo'] += $cuenta['saldo'];
    }
    
    // Saldo específico de caja (código 1.01.01)
    if ($cuenta['codigo'] === '1.01.01') {
        $saldo_caja = $cuenta['saldo'];
    }
}

// Obtener saldo real de clientes deudores desde ctacte_clientes
$stmt_clientes = $db->prepare("SELECT COALESCE(SUM(saldo), 0) as total_deuda FROM ctacte_clientes WHERE empresa_id = ? AND saldo > 0");
$stmt_clientes->execute([$empresa_id]);
$resultado_clientes = $stmt_clientes->fetch();
$saldo_clientes = $resultado_clientes['total_deuda'];

// Calcular ganancia real: Ventas - Costo Productos - Gastos Operativos
$ventas_totales = $totales['INGRESO']['saldo'];
$costo_venta = 0; // Buscar costo de productos vendidos
$gastos_operativos = 0; // Todos los gastos del negocio (excepto costo de ventas)

// Buscar cuenta de costo de ventas (6.01.01)
foreach ($resumen_cuentas as $cuenta) {
    if ($cuenta['codigo'] === '6.01.01') { // Costo Mercaderías Vendidas
        $costo_venta = $cuenta['saldo'];
    }
}

// Sumar todos los gastos (tipo GASTO) EXCEPTO costo de ventas
foreach ($resumen_cuentas as $cuenta) {
    if ($cuenta['tipo'] === 'GASTO' && $cuenta['codigo'] !== '6.01.01') {
        $gastos_operativos += $cuenta['saldo'];
    }
}

// Ganancia neta real: Ventas - Costo productos - Gastos operativos (sin duplicar costo)
$ganancia_neta = $ventas_totales - $costo_venta - $gastos_operativos;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contabilidad - NEXUS POS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="min-h-screen bg-gradient-to-br from-gray-900 to-gray-800 text-gray-100">
    <header class="bg-gray-900 shadow-lg">
        <div class="container mx-auto px-6 py-4 flex justify-between items-center">
            <div class="flex items-center gap-4">
                <h1 class="text-2xl font-bold text-green-400">📊 CONTABILIDAD</h1>
                <span class="text-gray-400">|</span>
                <span class="text-gray-300"><?= $user['nombre'] ?></span>
            </div>
            <div class="flex gap-2">
                <a href="dashboard.php" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded">
                    🚪 VOLVER
                </a>
                <button onclick="toggleModo()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                    🔄 CAMBIAR MODO
                </button>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-6 py-8">
        <!-- Alerta si no hay plan de cuentas -->
        <?php if ($cuentas_existentes == 0): ?>
        <div class="bg-yellow-900 border border-yellow-700 rounded-lg p-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-yellow-400 font-bold text-lg mb-2">
                        <i class="fas fa-exclamation-triangle mr-2"></i>Plan de Cuentas No Configurado
                    </h3>
                    <p class="text-yellow-200">Para empezar a usar el módulo contable, necesitas cargar el plan de cuentas.</p>
                </div>
                <button onclick="cargarPlanCuentas()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">
                    📊 CARGAR PLAN
                </button>
            </div>
        </div>
        <?php endif; ?>

        <!-- Vista Dueño (Simplificada) -->
        <div id="vistaDueño" class="space-y-6">
            <!-- Resumen Financiero -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-gray-400 text-sm">EFECTIVO EN CAJA</h3>
                        <i class="fas fa-money-bill-wave text-green-400"></i>
                    </div>
                    <p class="text-2xl font-bold text-white">
                        $<?= number_format($saldo_caja, 2) ?>
                    </p>
                    <p class="text-gray-400 text-xs mt-1">Saldo en cuenta caja</p>
                </div>
                
                <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-gray-400 text-sm">CLIENTES DEUDORES</h3>
                        <i class="fas fa-users text-blue-400"></i>
                    </div>
                    <p class="text-2xl font-bold text-white">$<?= number_format($saldo_clientes, 2) ?></p>
                    <p class="text-gray-400 text-xs mt-1">Saldo a cobrar</p>
                </div>
                
                <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-gray-400 text-sm">VENTAS MES</h3>
                        <i class="fas fa-chart-line text-green-400"></i>
                    </div>
                    <p class="text-2xl font-bold text-white">
                        $<?= number_format($totales['INGRESO']['saldo'], 2) ?>
                    </p>
                    <p class="text-gray-400 text-xs mt-1">Ingresos del período</p>
                </div>
                
                <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-gray-400 text-sm">GANANCIA NETA</h3>
                        <i class="fas fa-chart-pie text-purple-400"></i>
                    </div>
                    <p class="text-2xl font-bold <?= $ganancia_neta >= 0 ? 'text-green-400' : 'text-red-400' ?>">
                        $<?= number_format($ganancia_neta, 2) ?>
                    </p>
                    <p class="text-gray-400 text-xs mt-1">Ventas - Costo - Gastos</p>
                </div>
            </div>

            <!-- Acciones Rápidas -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
                    <h3 class="text-lg font-bold text-white mb-4">
                        <i class="fas fa-file-invoice-dollar mr-2"></i>Últimas Ventas
                    </h3>
                    <div class="space-y-2">
                        <?php
                        $stmt = $db->prepare("SELECT id, total, fecha FROM ventas WHERE empresa_id = ? ORDER BY fecha DESC LIMIT 5");
                        $stmt->execute([$empresa_id]);
                        $ultimas_ventas = $stmt->fetchAll();
                        
                        foreach ($ultimas_ventas as $venta):
                        ?>
                        <div class="flex justify-between items-center py-2 border-b border-gray-700">
                            <div>
                                <p class="text-white text-sm">Venta #<?= $venta['id'] ?></p>
                                <p class="text-gray-400 text-xs"><?= date('d/m/Y', strtotime($venta['fecha'])) ?></p>
                            </div>
                            <div class="text-right">
                                <p class="text-green-400 font-bold">$<?= number_format($venta['total'], 2) ?></p>
                                <button onclick="generarAsientoVenta(<?= $venta['id'] ?>)" class="text-blue-400 text-xs hover:text-blue-300">
                                    📝 Generar
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
                    <h3 class="text-lg font-bold text-white mb-4">
                        <i class="fas fa-hand-holding-usd mr-2"></i>Cuentas por Cobrar
                    </h3>
                    <div class="space-y-2">
                        <div class="text-gray-400 text-center py-8">
                            <i class="fas fa-users text-4xl mb-2"></i>
                            <p>No hay deudas registradas</p>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
                    <h3 class="text-lg font-bold text-white mb-4">
                        <i class="fas fa-receipt mr-2"></i>IVA del Período
                    </h3>
                    <div class="space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-400">IVA Débito Fiscal:</span>
                            <span class="text-red-400 font-bold">$0.00</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-400">IVA Crédito Fiscal:</span>
                            <span class="text-green-400 font-bold">$0.00</span>
                        </div>
                        <div class="border-t border-gray-700 pt-3">
                            <div class="flex justify-between items-center">
                                <span class="text-white font-bold">Saldo a pagar:</span>
                                <span class="text-orange-400 font-bold">$0.00</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Vista Contador (Completa) -->
        <div id="vistaContador" class="space-y-6 hidden">
            <!-- Balance de Sumas y Saldos -->
            <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold text-white">
                        <i class="fas fa-balance-scale mr-2"></i>Balance de Sumas y Saldos
                    </h3>
                    <div class="flex gap-2">
                        <input type="date" id="fechaBalance" class="bg-gray-700 text-white px-3 py-2 rounded" value="<?= date('Y-m-d') ?>">
                        <button onclick="actualizarBalance()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                            🔄 ACTUALIZAR
                        </button>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-700">
                                <th class="text-left py-2 px-3 text-gray-400">Código</th>
                                <th class="text-left py-2 px-3 text-gray-400">Cuenta</th>
                                <th class="text-right py-2 px-3 text-gray-400">Debe</th>
                                <th class="text-right py-2 px-3 text-gray-400">Haber</th>
                                <th class="text-right py-2 px-3 text-gray-400">Saldo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($resumen_cuentas as $cuenta): ?>
                            <tr class="border-b border-gray-700 hover:bg-gray-700">
                                <td class="py-2 px-3 font-mono text-xs"><?= $cuenta['codigo'] ?></td>
                                <td class="py-2 px-3"><?= $cuenta['nombre'] ?></td>
                                <td class="py-2 px-3 text-right">$<?= number_format($cuenta['total_debe'], 2) ?></td>
                                <td class="py-2 px-3 text-right">$<?= number_format($cuenta['total_haber'], 2) ?></td>
                                <td class="py-2 px-3 text-right font-bold <?= $cuenta['saldo'] >= 0 ? 'text-green-400' : 'text-red-400' ?>">
                                    $<?= number_format(abs($cuenta['saldo']), 2) ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <!-- Totales -->
                            <tr class="bg-gray-700 font-bold">
                                <td colspan="2" class="py-2 px-3">TOTALES</td>
                                <td class="py-2 px-3 text-right">$<?= number_format(array_sum(array_column($resumen_cuentas, 'total_debe')), 2) ?></td>
                                <td class="py-2 px-3 text-right">$<?= number_format(array_sum(array_column($resumen_cuentas, 'total_haber')), 2) ?></td>
                                <td class="py-2 px-3 text-right">-</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Libro Diario -->
            <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold text-white">
                        <i class="fas fa-book mr-2"></i>Libro Diario
                    </h3>
                    <div class="flex gap-2">
                        <input type="date" id="fechaDesde" class="bg-gray-700 text-white px-3 py-2 rounded" value="<?= date('Y-m-01') ?>">
                        <input type="date" id="fechaHasta" class="bg-gray-700 text-white px-3 py-2 rounded" value="<?= date('Y-m-d') ?>">
                        <button onclick="cargarLibroDiario()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                            📖 LIBRO DIARIO
                        </button>
                    </div>
                </div>
                
                <div id="libroDiarioContenido" class="text-gray-400 text-center py-8">
                    <i class="fas fa-search text-4xl mb-2"></i>
                    <p>Selecciona un período para ver el libro diario</p>
                </div>
            </div>
        </div>
    </main>

    <!-- Scripts -->
    <script src="contabilidad_motor.php"></script>
    <script>
        let modoContador = false;

        function toggleModo() {
            modoContador = !modoContador;
            const vistaDueño = document.getElementById('vistaDueño');
            const vistaContador = document.getElementById('vistaContador');
            const btnModo = document.getElementById('btnModo');

            if (modoContador) {
                vistaDueño.classList.add('hidden');
                vistaContador.classList.remove('hidden');
                btnModo.textContent = 'VISTA DUEÑO';
            } else {
                vistaDueño.classList.remove('hidden');
                vistaContador.classList.add('hidden');
                btnModo.textContent = 'VISTA CONTADOR';
            }
        }

        function cargarPlanCuentas() {
            if (confirm('¿Deseas cargar el plan de cuentas estándar para Argentina?')) {
                window.location.href = 'contabilidad_cuentas_estandar.php?empresa_id=<?= $empresa_id ?>';
            }
        }

        async function generarAsientoVenta(venta_id) {
            try {
                const response = await fetch('contabilidad_motor.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `accion=generar_asiento_venta&venta_id=${venta_id}`
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert(`Asiento #${result.numero} generado correctamente`);
                    location.reload();
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (e) {
                alert('Error al generar asiento: ' + e.message);
            }
        }

        function actualizarBalance() {
            const fecha = document.getElementById('fechaBalance').value;
            location.href = `contabilidad.php?fecha_hasta=${fecha}`;
        }

        async function cargarLibroDiario() {
            const fechaDesde = document.getElementById('fechaDesde').value;
            const fechaHasta = document.getElementById('fechaHasta').value;
            const contenido = document.getElementById('libroDiarioContenido');

            contenido.innerHTML = '<i class="fas fa-spinner fa-spin text-4xl mb-2"></i><p>Cargando...</p>';

            try {
                const response = await fetch(`contabilidad_motor.php?fecha_desde=${fechaDesde}&fecha_hasta=${fechaHasta}`);
                const asientos = await response.json();

                if (asientos.length === 0) {
                    contenido.innerHTML = '<p>No hay asientos en el período seleccionado</p>';
                    return;
                }

                let html = '<div class="space-y-4">';
                let numeroActual = null;

                asientos.forEach(asiento => {
                    if (asiento.numero !== numeroActual) {
                        if (numeroActual !== null) {
                            html += '</div></div>';
                        }
                        html += `
                            <div class="border border-gray-700 rounded-lg p-4">
                                <div class="flex justify-between items-center mb-3">
                                    <div>
                                        <span class="font-bold text-white">Asiento #${asiento.numero}</span>
                                        <span class="text-gray-400 ml-2">${asiento.fecha}</span>
                                    </div>
                                    <div>
                                        <span class="text-gray-400">${asiento.tipo_comprobante} ${asiento.nro_comprobante}</span>
                                    </div>
                                </div>
                                <p class="text-gray-300 mb-3">${asiento.descripcion}</p>
                                <div class="space-y-1">
                        `;
                        numeroActual = asiento.numero;
                    }

                    html += `
                        <div class="flex justify-between items-center py-1">
                            <span class="text-gray-400">${asiento.cuenta_codigo} - ${asiento.cuenta_nombre}</span>
                            <div class="flex gap-4">
                                <span class="text-red-400 w-20 text-right">$${parseFloat(asiento.debe).toFixed(2)}</span>
                                <span class="text-green-400 w-20 text-right">$${parseFloat(asiento.haber).toFixed(2)}</span>
                            </div>
                        </div>
                    `;
                });

                html += '</div></div>';
                contenido.innerHTML = html;

            } catch (e) {
                contenido.innerHTML = '<p class="text-red-400">Error al cargar el libro diario</p>';
            }
        }
    </script>
</body>
</html>
