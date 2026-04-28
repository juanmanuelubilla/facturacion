<?php
// Logging agresivo al inicio
file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Inicio del script\n", FILE_APPEND);

// Iniciar output buffering
ob_start();

// Deshabilitar display_errors
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Log al inicio
error_log("API Venta - Inicio del script");
file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Después de error_log\n", FILE_APPEND);

require_once 'config.php';
file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Después de require config\n", FILE_APPEND);

requireLogin();
file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Después de requireLogin\n", FILE_APPEND);

error_log("API Venta - Después de requireLogin");

// Limpiar buffer
ob_clean();
file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Después de ob_clean\n", FILE_APPEND);

header('Content-Type: application/json');
file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Después de header\n", FILE_APPEND);

$user = getUser();
file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Después de getUser\n", FILE_APPEND);
$empresa_id = $user['empresa_id'];
$usuario_id = $user['id'];

error_log("API Venta - Usuario: $usuario_id, Empresa: $empresa_id");
file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Usuario: $usuario_id, Empresa: $empresa_id\n", FILE_APPEND);

try {
    file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Entrando al try\n", FILE_APPEND);
    $raw_input = file_get_contents('php://input');
    file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Raw input length: " . strlen($raw_input) . "\n", FILE_APPEND);
    
    error_log("API Venta - Raw input length: " . strlen($raw_input));
    
    if (empty($raw_input)) {
        echo json_encode(['success' => false, 'error' => 'No se recibieron datos']);
        exit;
    }
    
    $input = json_decode($raw_input, true);
    file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Después de json_decode\n", FILE_APPEND);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("API Venta - JSON error: " . json_last_error_msg());
        file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: JSON error: " . json_last_error_msg() . "\n", FILE_APPEND);
        echo json_encode(['success' => false, 'error' => 'JSON inválido: ' . json_last_error_msg()]);
        exit;
    }
    file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: JSON válido\n", FILE_APPEND);

    if (!$input || !isset($input['items']) || empty($input['items'])) {
        file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Error - No hay items\n", FILE_APPEND);
        echo json_encode(['success' => false, 'error' => 'No hay items en la venta']);
        exit;
    }
    file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Items válidos, cantidad: " . count($input['items']) . "\n", FILE_APPEND);

    $cliente_id = $input['cliente_id'] ?? 0;
    $total = floatval($input['total'] ?? 0);
    $metodo = $input['metodo'] ?? 'EFECTIVO';
    $items = $input['items'];
    $entregado = floatval($input['entregado'] ?? $total);
    $vuelto = floatval($input['vuelto'] ?? 0);

    // Iniciar transacción
    file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Antes de getDB\n", FILE_APPEND);
    $db = getDB();
    file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Después de getDB\n", FILE_APPEND);
    $db->beginTransaction();
    file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Después de beginTransaction\n", FILE_APPEND);

    // Paso 1: Crear venta (como en crear_venta de ventas.py)
    $cid = ($cliente_id && $cliente_id != 0) ? $cliente_id : null;
    $fecha_venta = date('Y-m-d H:i:s');
    error_log("API Venta - Fecha de venta: $fecha_venta");
    file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Antes de INSERT venta\n", FILE_APPEND);
    $stmt = $db->prepare("INSERT INTO ventas (total, ganancia, usuario_id, empresa_id, cliente_id, fecha, estado) VALUES (0, 0, ?, ?, ?, ?, 'COMPLETADA')");
    $stmt->execute([$usuario_id, $empresa_id, $cid, $fecha_venta]);
    file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Después de INSERT venta\n", FILE_APPEND);
    $venta_id = $db->lastInsertId();
    file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Venta ID: $venta_id\n", FILE_APPEND);
    error_log("API Venta - Venta creada con ID: $venta_id");

    // Paso 2: Agregar items y calcular costo total (como en agregar_item de ventas.py)
    $stmtItem = $db->prepare("INSERT INTO venta_items (venta_id, producto_id, cantidad, precio_unitario, costo_unitario, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
    $stmtCosto = $db->prepare("SELECT costo FROM productos WHERE id = ? AND empresa_id = ?");

    $total_costo = 0;
    file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Iniciando foreach items\n", FILE_APPEND);
    foreach ($items as $item) {
        file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Procesando item\n", FILE_APPEND);
        $producto_id = intval($item['producto_id']);
        $cantidad = floatval($item['cantidad']);
        $precio = floatval($item['precio']);
        $subtotal = floatval($item['subtotal']);

        // Obtener costo del producto
        $stmtCosto->execute([$producto_id, $empresa_id]);
        $costo_row = $stmtCosto->fetch();
        $costo = $costo_row ? floatval($costo_row['costo']) : 0;

        $stmtItem->execute([$venta_id, $producto_id, $cantidad, $precio, $costo, $subtotal]);
        $total_costo += ($costo * $cantidad);
    }
    file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Después de foreach items, total_costo: $total_costo\n", FILE_APPEND);

    // Paso 3: Cerrar venta (como en cerrar_venta de ventas.py)
    $ganancia = $total - $total_costo;
    file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Ganancia calculada: $ganancia\n", FILE_APPEND);

    // Actualizar venta con total y ganancia
    file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Antes de UPDATE venta\n", FILE_APPEND);
    $stmtUpdateVenta = $db->prepare("UPDATE ventas SET total=?, ganancia=? WHERE id=? AND empresa_id=?");
    $stmtUpdateVenta->execute([$total, $ganancia, $venta_id, $empresa_id]);
    file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Después de UPDATE venta\n", FILE_APPEND);

    // Descuento de stock
    file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Iniciando descuento de stock\n", FILE_APPEND);
    $stmtStock = $db->prepare("UPDATE productos SET stock = stock - ? WHERE id = ? AND empresa_id = ?");
    foreach ($items as $item) {
        $producto_id = intval($item['producto_id']);
        $cantidad = floatval($item['cantidad']);
        $stmtStock->execute([$cantidad, $producto_id, $empresa_id]);
    }
    file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Después de descuento de stock\n", FILE_APPEND);

    // Paso 4: Registrar pago (como en registrar_pago de ventas.py)
    file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Antes de INSERT pago\n", FILE_APPEND);
    $stmtPago = $db->prepare("INSERT INTO pagos (venta_id, empresa_id, metodo, monto, entregado, vuelto, estado) VALUES (?, ?, ?, ?, ?, ?, 'completado')");
    $stmtPago->execute([$venta_id, $empresa_id, $metodo, $total, $entregado, $vuelto]);
    file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Después de INSERT pago\n", FILE_APPEND);

    // Paso 5: Registrar en finanzas
    file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Antes de INSERT finanzas\n", FILE_APPEND);
    error_log("API Venta - Registrando en finanzas");
    $stmtFinanzas = $db->prepare("INSERT INTO finanzas (empresa_id, tipo, categoria, monto, descripcion, metodo_pago, usuario_id, fecha) VALUES (?, 'INGRESO', 'Ventas', ?, ?, ?, ?, NOW())");
    $stmtFinanzas->execute([$empresa_id, $total, "Venta #$venta_id", $metodo, $usuario_id]);
    file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Después de INSERT finanzas\n", FILE_APPEND);
    error_log("API Venta - Finanzas registradas exitosamente");

    // Paso 6: FACTURACIÓN AFIP AUTOMÁTICA (como en ventas.php registrar_pago)
    // Solo facturar si está configurado "siempre_fiscal"
    file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Verificando configuración AFIP\n", FILE_APPEND);
    $factura_result = null;
    
    // Obtener configuración de AFIP
    $config_afip = fetch("SELECT siempre_fiscal FROM nombre_negocio WHERE empresa_id = ?", [$empresa_id]);
    $siempre_fiscal = $config_afip ? ($config_afip['siempre_fiscal'] ?? 0) : 0;
    
    if ($siempre_fiscal) {
        try {
            file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Requiriendo FacturadorARCA\n", FILE_APPEND);
            require_once __DIR__ . '/lib/FacturadorARCA.php';
            file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Creando instancia FacturadorARCA\n", FILE_APPEND);
            $facturador = new FacturadorARCA($empresa_id, $db);
            
            // Obtener DNI del cliente si existe
            $dni_cliente = null;
            if ($cliente_id && $cliente_id != 0) {
                $stmtCliente = $db->prepare("SELECT documento FROM clientes WHERE id = ? AND empresa_id = ?");
                $stmtCliente->execute([$cliente_id, $empresa_id]);
                $cliente_row = $stmtCliente->fetch();
                if ($cliente_row) {
                    $dni_cliente = preg_replace('/[^0-9]/', '', $cliente_row['documento'] ?? '');
                }
            }
            
            file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Llamando emitirFacturaC\n", FILE_APPEND);
            $factura_result = $facturador->emitirFacturaC($venta_id, 1, $dni_cliente, $total);
            file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Factura emitida\n", FILE_APPEND);
        } catch (Exception $e) {
            // No fallar la venta si la facturación falla, solo registrar el error
            file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Error en facturación: " . $e->getMessage() . "\n", FILE_APPEND);
            $factura_result = ['status' => 'ERROR', 'message' => $e->getMessage()];
        }
    } else {
        file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Facturación AFIP deshabilitada (siempre_fiscal=0)\n", FILE_APPEND);
    }
    file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Después de facturación AFIP\n", FILE_APPEND);

    // Paso 7: GENERAR Y GUARDAR TICKET (como en app_gui.py procesar_pago)
    file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Iniciando generación de ticket\n", FILE_APPEND);
    $ticket_path = null;
    try {
        file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Requiriendo TicketGenerator\n", FILE_APPEND);
        require_once __DIR__ . '/lib/TicketGenerator.php';
        file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Creando instancia TicketGenerator\n", FILE_APPEND);
        $ticketGen = new TicketGenerator($db, $empresa_id);
        
        // Preparar items con nombres de productos
        file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Preparando items para ticket\n", FILE_APPEND);
        $itemsConNombre = [];
        foreach ($items as $item) {
            $stmtProd = $db->prepare("SELECT nombre FROM productos WHERE id = ? AND empresa_id = ?");
            $stmtProd->execute([$item['producto_id'], $empresa_id]);
            $prod = $stmtProd->fetch();
            
            $itemsConNombre[] = [
                'nombre' => $prod['nombre'] ?? 'Producto',
                'cantidad' => $item['cantidad'],
                'subtotal' => $item['subtotal']
            ];
        }
        
        file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Generando texto del ticket\n", FILE_APPEND);
        $ticket_texto = $ticketGen->generarTicket($itemsConNombre, $total, $venta_id, $metodo, $vuelto);
        file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Guardando ticket\n", FILE_APPEND);
        $ticket_path = $ticketGen->guardarTicket($ticket_texto, $venta_id);
        file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Ticket generado exitosamente\n", FILE_APPEND);
    } catch (Exception $e) {
        // No fallar la venta si el ticket falla
        file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Error en ticket: " . $e->getMessage() . "\n", FILE_APPEND);
        $ticket_path = null;
    }
    file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Después de generación de ticket\n", FILE_APPEND);

    // Paso 8: Generar asiento contable (motor contable)
    file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Iniciando generación de asiento contable\n", FILE_APPEND);
    error_log("API Venta - Generando asiento contable");
    $asiento_result = null;
    try {
        file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Requiriendo MotorContable\n", FILE_APPEND);
        require_once __DIR__ . '/lib/MotorContable.php';
        file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Creando instancia MotorContable\n", FILE_APPEND);
        $motor = new MotorContable($db, $empresa_id);
        file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Llamando generarAsientoVenta\n", FILE_APPEND);
        $asiento_result = $motor->generarAsientoVenta($venta_id);
        file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Asiento contable generado exitosamente\n", FILE_APPEND);
        error_log("API Venta - Asiento contable generado exitosamente");
    } catch (Exception $e) {
        // No fallar la venta si el asiento falla, solo registrar el error
        file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Error en asiento contable: " . $e->getMessage() . "\n", FILE_APPEND);
        error_log("API Venta - Error en asiento contable: " . $e->getMessage());
        $asiento_result = ['success' => false, 'error' => $e->getMessage()];
    }
    file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Después de asiento contable\n", FILE_APPEND);

    // Commit de la transacción (todo salió bien)
    try {
        $db->commit();
        file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Commit exitoso\n", FILE_APPEND);
    } catch (Exception $e) {
        file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Error en commit: " . $e->getMessage() . "\n", FILE_APPEND);
        // Si hay error en commit, intentar rollback
        try {
            $db->rollBack();
            // Guardar ticket como archivo
            $ticket_path = '/tmp/ticket_' . $venta_id . '.png';
            imagepng($rotated_image, $ticket_path);
            imagedestroy($source_image);
            imagedestroy($rotated_image);

            // Enviar a impresora si está configurada
            $impresora_auto = $config_general['impresora_auto'] ?? 0;
            $ticket_impreso = false;

            if ($impresora_ticket && $impresora_ticket !== 'Default') {
                $cmd = "lp -d " . escapeshellarg($impresora_ticket) . " " . escapeshellarg($ticket_path) . " 2>&1";
                exec($cmd, $output, $return_var);
                $ticket_impreso = ($return_var === 0);
                if (!$ticket_impreso) error_log("Error imprimiendo: " . implode("\n", $output));
            } else {
                $cmd = "lp " . escapeshellarg($ticket_path) . " 2>&1";
                exec($cmd, $output, $return_var);
                $ticket_impreso = ($return_var === 0);
                if (!$ticket_impreso) error_log("Error imprimiendo: " . implode("\n", $output));
            }

            $response['debug']['ticket_generado'] = true;
            $response['debug']['ticket_path'] = $ticket_path;
            $response['debug']['ticket_impreso'] = $ticket_impreso;
            $response['debug']['impresora_auto'] = $impresora_auto;

            // Flag para frontend si impresión automática está activada
            if ($impresora_auto) {
                $response['imprimir_automatico'] = true;
                $response['ticket_url'] = $ticket_url ?? null;
            }
        } catch (Exception $e2) {
            file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: Error en rollback: " . $e2->getMessage() . "\n", FILE_APPEND);
        }
        throw $e;
    }

    $response = [
        'success' => true, 
        'venta_id' => $venta_id,
        'ticket' => $ticket_path
    ];
    if ($factura_result) {
        $response['factura'] = $factura_result;
    }
    if ($asiento_result) {
        $response['asiento'] = $asiento_result;
    }
    
    echo json_encode($response);

} catch (Exception $e) {
    file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: EXCEPTION - " . $e->getMessage() . "\n", FILE_APPEND);
    file_put_contents('/tmp/api_venta_debug.log', date('Y-m-d H:i:s') . " - API Venta: EXCEPTION - " . $e->getTraceAsString() . "\n", FILE_APPEND);
    if (isset($db)) $db->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
