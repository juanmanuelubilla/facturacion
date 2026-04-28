<?php
require_once 'config.php';
requireLogin();

$user = getUser();
$db = getDB();
$empresa_id = $user['empresa_id'];

$categorias_por_tipo = [
    "INGRESO" => ["Ventas POS", "Aporte de Capital", "Devolución Proveedor", "Préstamo Bancario", "Intereses Ganados", "Otros Ingresos"],
    "GASTO" => ["Mercadería (Compra)", "Sueldos y Jornales", "Leyes Sociales / Impuestos", "Arriendo de Local", "Servicios: Luz", "Servicios: Agua", "Servicios: Internet/Teléfono", "Marketing y Publicidad", "Mantenimiento y Reparaciones", "Limpieza e Higiene", "Retiro de Socio", "Pago de Préstamos", "Otros Gastos"]
];

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $tipo = $_POST['tipo'] ?? '';
    $categoria = $_POST['categoria'] ?? '';
    $monto = floatval($_POST['monto'] ?? 0);
    $descripcion = trim($_POST['descripcion'] ?? '');
    
    if ($accion === 'guardar' && $monto > 0) {
        $fecha = date('Y-m-d');
        $hora = date('H:i:s');
        query("INSERT INTO finanzas (empresa_id, fecha, hora, tipo, categoria, monto, descripcion) VALUES (?, ?, ?, ?, ?, ?, ?)", 
              [$empresa_id, $fecha, $hora, $tipo, $categoria, $monto, $descripcion]);
        header('Location: finanzas.php');
        exit;
    }
    
    if ($accion === 'eliminar' && !empty($_POST['id'])) {
        query("DELETE FROM finanzas WHERE id=? AND empresa_id=?", [$_POST['id'], $empresa_id]);
        header('Location: finanzas.php');
        exit;
    }
}

// Movimientos del día actual (excluyendo 'Ventas' que se calculan desde tabla ventas)
$hoy = date('Y-m-d');
$finanzas = fetchAll("SELECT * FROM finanzas WHERE empresa_id = ? AND DATE(fecha) = ? AND categoria != 'Ventas' ORDER BY hora DESC", [$empresa_id, $hoy]);

// Calcular totales de movimientos extra (no ventas)
$total_ingresos_extra = 0;
$total_gastos = 0;

foreach ($finanzas as $f) {
    if ($f['tipo'] === 'INGRESO') {
        $total_ingresos_extra += $f['monto'];
    } else {
        $total_gastos += $f['monto'];
    }
}

// Calcular ventas brutas y utilidad desde tabla ventas
$stmt = $db->prepare("SELECT IFNULL(SUM(total), 0) as ventas_brutas, IFNULL(SUM(ganancia), 0) as utilidad_ventas FROM ventas WHERE empresa_id = ? AND DATE(fecha) = CURRENT_DATE");
$stmt->execute([$empresa_id]);
$ventas_hoy = $stmt->fetch();
$ventas_brutas = $ventas_hoy['ventas_brutas'] ?? 0;
$utilidad_ventas = $ventas_hoy['utilidad_ventas'] ?? 0;

// Utilidad real = utilidad de ventas + ingresos extra - gastos
$utilidad_real = $utilidad_ventas + $total_ingresos_extra - $total_gastos;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finanzas - NEXUS POS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-gray-900 to-gray-800 text-gray-100">
    <header class="bg-gray-900 shadow-lg">
        <div class="container mx-auto px-6 py-4 flex justify-between items-center">
            <h1 class="text-2xl font-bold text-green-400">💰 FINANZAS</h1>
            <a href="dashboard.php" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded">VOLVER</a>
        </div>
    </header>
    <main class="container mx-auto px-6 py-8">
        <!-- Panel de Resumen -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
                <h3 class="text-gray-400 text-sm mb-2">VENTAS TOTALES (BRUTO)</h3>
                <p class="text-3xl font-bold text-white">$<?= number_format($ventas_brutas, 2) ?></p>
            </div>
            <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
                <h3 class="text-gray-400 text-sm mb-2">TOTAL GASTOS</h3>
                <p class="text-3xl font-bold text-red-400">$<?= number_format($total_gastos, 2) ?></p>
            </div>
            <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
                <h3 class="text-gray-400 text-sm mb-2">UTILIDAD (GANANCIA REAL)</h3>
                <p class="text-3xl font-bold text-green-400">$<?= number_format($utilidad_real, 2) ?></p>
            </div>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Formulario -->
            <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
                <h3 class="text-xl font-bold text-white mb-4">Registrar Movimiento de Caja</h3>
                <form method="POST" id="finanzaForm">
                    <input type="hidden" name="accion" value="guardar">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">Tipo de Movimiento</label>
                            <select name="tipo" id="tipo" required onchange="actualizarCategorias()"
                                    class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                                <option value="">Seleccionar...</option>
                                <option value="INGRESO">INGRESO</option>
                                <option value="GASTO">GASTO</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">Categoría</label>
                            <select name="categoria" id="categoria" required
                                    class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                                <option value="">Seleccione tipo primero</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">Monto ($)</label>
                            <input type="number" step="0.01" name="monto" id="monto" required
                                   class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                        </div>
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">Descripción / Nota</label>
                            <input type="text" name="descripcion" id="descripcion"
                                   class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                        </div>
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 rounded font-bold">
                            💾 GUARDAR MOVIMIENTO
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Tabla -->
            <div class="lg:col-span-2 bg-gray-800 rounded-lg border border-gray-700 p-6">
                <h3 class="text-xl font-bold text-white mb-4">Historial de Movimientos</h3>
                <div class="overflow-x-auto max-h-96 overflow-y-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-700 sticky top-0 bg-gray-800">
                                <th class="text-left py-2 px-3 text-gray-400 cursor-pointer hover:text-white" onclick="ordenarTabla(0)">HORA ⬍</th>
                                <th class="text-left py-2 px-3 text-gray-400 cursor-pointer hover:text-white" onclick="ordenarTabla(1)">Tipo ⬍</th>
                                <th class="text-left py-2 px-3 text-gray-400 cursor-pointer hover:text-white" onclick="ordenarTabla(2)">Categoría ⬍</th>
                                <th class="text-left py-2 px-3 text-gray-400 cursor-pointer hover:text-white" onclick="ordenarTabla(3)">Monto ⬍</th>
                                <th class="text-left py-2 px-3 text-gray-400 cursor-pointer hover:text-white" onclick="ordenarTabla(4)">Descripción ⬍</th>
                                <th class="text-left py-2 px-3 text-gray-400">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($finanzas as $f): ?>
                            <tr class="border-b border-gray-700 hover:bg-gray-700">
                                <td class="py-2 px-3 text-sm"><?= substr($f['hora'], 0, 5) ?></td>
                                <td class="py-2 px-3 text-sm">
                                    <span class="<?= $f['tipo'] === 'INGRESO' ? 'bg-green-600' : 'bg-red-600' ?> px-2 py-1 rounded text-xs">
                                        <?= $f['tipo'] ?>
                                    </span>
                                </td>
                                <td class="py-2 px-3 text-sm"><?= htmlspecialchars($f['categoria']) ?></td>
                                <td class="py-2 px-3 text-sm <?= $f['tipo'] === 'INGRESO' ? 'text-green-400' : 'text-red-400' ?>">
                                    $<?= number_format($f['monto'], 2) ?>
                                </td>
                                <td class="py-2 px-3 text-sm text-gray-400"><?= htmlspecialchars($f['descripcion']) ?></td>
                                <td class="py-2 px-3 text-sm">
                                    <button onclick="eliminarFinanza(<?= $f['id'] ?>)"
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
        const categoriasPorTipo = <?= json_encode($categorias_por_tipo) ?>;

        function actualizarCategorias() {
            const tipo = document.getElementById('tipo').value;
            const select = document.getElementById('categoria');
            select.innerHTML = '<option value="">Seleccionar...</option>';

            if (tipo && categoriasPorTipo[tipo]) {
                categoriasPorTipo[tipo].forEach(cat => {
                    const option = document.createElement('option');
                    option.value = cat;
                    option.textContent = cat;
                    select.appendChild(option);
                });
            }
        }

        function eliminarFinanza(id) {
            if (confirm('¿Eliminar este movimiento?')) {
                const form = document.getElementById('finanzaForm');
                form.action.value = 'eliminar';
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'id';
                idInput.value = id;
                form.appendChild(idInput);
                form.submit();
            }
        }

        let ordenColumna = -1;
        let ordenAscendente = true;

        function ordenarTabla(columna) {
            const tabla = document.querySelector('tbody');
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
            const headers = document.querySelector('thead').querySelectorAll('th');
            headers.forEach((header, index) => {
                if (index === columna) {
                    header.textContent = header.textContent.replace(/[⬍⬆⬇]/, '') + (ordenAscendente ? ' ⬆' : ' ⬇');
                } else {
                    header.textContent = header.textContent.replace(/[⬍⬆⬇]/, ' ⬍');
                }
            });
        }
    </script>
</body>
</html>
