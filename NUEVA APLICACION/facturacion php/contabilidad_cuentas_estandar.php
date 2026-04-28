<?php
require_once 'config.php';
requireLogin();

/**
 * Cargar plan de cuentas estándar para Argentina
 * Este script crea las cuentas contables básicas para una PyME argentina
 */

function cargarPlanCuentasEstandar($empresa_id, $db) {
    $cuentas = [
        // ACTIVO CORRIENTE
        ['codigo' => '1.01.01', 'nombre' => 'Caja', 'tipo' => 'ACTIVO', 'subtipo' => 'Caja y Bancos', 'imputable' => true],
        ['codigo' => '1.01.02', 'nombre' => 'Banco Cuenta Corriente', 'tipo' => 'ACTIVO', 'subtipo' => 'Caja y Bancos', 'imputable' => true],
        ['codigo' => '1.01.03', 'nombre' => 'Banco Caja de Ahorro', 'tipo' => 'ACTIVO', 'subtipo' => 'Caja y Bancos', 'imputable' => true],
        ['codigo' => '1.02.01', 'nombre' => 'Clientes', 'tipo' => 'ACTIVO', 'subtipo' => 'Créditos', 'imputable' => true],
        ['codigo' => '1.02.02', 'nombre' => 'Deudores Varios', 'tipo' => 'ACTIVO', 'subtipo' => 'Créditos', 'imputable' => true],
        ['codigo' => '1.03.01', 'nombre' => 'IVA Crédito Fiscal', 'tipo' => 'ACTIVO', 'subtipo' => 'Créditos Fiscales', 'imputable' => true],
        ['codigo' => '1.03.02', 'nombre' => 'Retenciones de IIBB a Recuperar', 'tipo' => 'ACTIVO', 'subtipo' => 'Créditos Fiscales', 'imputable' => true],
        ['codigo' => '1.03.03', 'nombre' => 'Retenciones de Ganancias a Recuperar', 'tipo' => 'ACTIVO', 'subtipo' => 'Créditos Fiscales', 'imputable' => true],
        ['codigo' => '1.04.01', 'nombre' => 'Mercaderías', 'tipo' => 'ACTIVO', 'subtipo' => 'Bienes de Cambio', 'imputable' => true],
        ['codigo' => '1.04.02', 'nombre' => 'Materias Primas', 'tipo' => 'ACTIVO', 'subtipo' => 'Bienes de Cambio', 'imputable' => true],
        
        // ACTIVO NO CORRIENTE
        ['codigo' => '2.01.01', 'nombre' => 'Rodados', 'tipo' => 'ACTIVO', 'subtipo' => 'Bienes de Uso', 'imputable' => true],
        ['codigo' => '2.01.02', 'nombre' => 'Mobiliario', 'tipo' => 'ACTIVO', 'subtipo' => 'Bienes de Uso', 'imputable' => true],
        ['codigo' => '2.01.03', 'nombre' => 'Equipos de Computación', 'tipo' => 'ACTIVO', 'subtipo' => 'Bienes de Uso', 'imputable' => true],
        ['codigo' => '2.02.01', 'nombre' => 'Amortización Acumulada Rodados', 'tipo' => 'ACTIVO', 'subtipo' => 'Amortizaciones Acumuladas', 'imputable' => true],
        ['codigo' => '2.02.02', 'nombre' => 'Amortización Acumulada Mobiliario', 'tipo' => 'ACTIVO', 'subtipo' => 'Amortizaciones Acumuladas', 'imputable' => true],
        
        // PASIVO CORRIENTE
        ['codigo' => '3.01.01', 'nombre' => 'Proveedores', 'tipo' => 'PASIVO', 'subtipo' => 'Deudas Comerciales', 'imputable' => true],
        ['codigo' => '3.01.02', 'nombre' => 'Acreedores Varios', 'tipo' => 'PASIVO', 'subtipo' => 'Deudas Comerciales', 'imputable' => true],
        ['codigo' => '3.02.01', 'nombre' => 'IVA Débito Fiscal', 'tipo' => 'PASIVO', 'subtipo' => 'Deudas Fiscales', 'imputable' => true],
        ['codigo' => '3.02.02', 'nombre' => 'Retenciones de IIBB a Pagar', 'tipo' => 'PASIVO', 'subtipo' => 'Deudas Fiscales', 'imputable' => true],
        ['codigo' => '3.02.03', 'nombre' => 'Retenciones de Ganancias a Pagar', 'tipo' => 'PASIVO', 'subtipo' => 'Deudas Fiscales', 'imputable' => true],
        ['codigo' => '3.02.04', 'nombre' => 'SUSS a Pagar', 'tipo' => 'PASIVO', 'subtipo' => 'Deudas Fiscales', 'imputable' => true],
        ['codigo' => '3.03.01', 'nombre' => 'Sueldos a Pagar', 'tipo' => 'PASIVO', 'subtipo' => 'Remuneraciones', 'imputable' => true],
        ['codigo' => '3.03.02', 'nombre' => 'Cargas Sociales a Pagar', 'tipo' => 'PASIVO', 'subtipo' => 'Remuneraciones', 'imputable' => true],
        ['codigo' => '3.04.01', 'nombre' => 'Bancos Préstamos', 'tipo' => 'PASIVO', 'subtipo' => 'Deudas Financieras', 'imputable' => true],
        
        // PATRIMONIO NETO
        ['codigo' => '4.01.01', 'nombre' => 'Capital Social', 'tipo' => 'PATRIMONIO_NETO', 'subtipo' => 'Capital', 'imputable' => false],
        ['codigo' => '4.02.01', 'nombre' => 'Reservas', 'tipo' => 'PATRIMONIO_NETO', 'subtipo' => 'Reservas', 'imputable' => false],
        ['codigo' => '4.03.01', 'nombre' => 'Resultado del Ejercicio Anterior', 'tipo' => 'PATRIMONIO_NETO', 'subtipo' => 'Resultados Acumulados', 'imputable' => false],
        ['codigo' => '4.03.02', 'nombre' => 'Resultado del Ejercicio Actual', 'tipo' => 'PATRIMONIO_NETO', 'subtipo' => 'Resultados Acumulados', 'imputable' => false],
        
        // INGRESOS
        ['codigo' => '5.01.01', 'nombre' => 'Ventas', 'tipo' => 'INGRESO', 'subtipo' => 'Ventas', 'imputable' => true],
        ['codigo' => '5.01.02', 'nombre' => 'Ventas Exportaciones', 'tipo' => 'INGRESO', 'subtipo' => 'Ventas', 'imputable' => true],
        ['codigo' => '5.02.01', 'nombre' => 'Intereses Ganados', 'tipo' => 'INGRESO', 'subtipo' => 'Ingresos Financieros', 'imputable' => true],
        ['codigo' => '5.03.01', 'nombre' => 'Otros Ingresos', 'tipo' => 'INGRESO', 'subtipo' => 'Otros Ingresos', 'imputable' => true],
        
        // GASTOS
        ['codigo' => '6.01.01', 'nombre' => 'Costo Mercaderías Vendidas', 'tipo' => 'GASTO', 'subtipo' => 'Costo de Ventas', 'imputable' => true],
        ['codigo' => '6.02.01', 'nombre' => 'Sueldos y Jornales', 'tipo' => 'GASTO', 'subtipo' => 'Gastos de Personal', 'imputable' => true],
        ['codigo' => '6.02.02', 'nombre' => 'Cargas Sociales', 'tipo' => 'GASTO', 'subtipo' => 'Gastos de Personal', 'imputable' => true],
        ['codigo' => '6.03.01', 'nombre' => 'Alquiler Local Comercial', 'tipo' => 'GASTO', 'subtipo' => 'Alquileres', 'imputable' => true],
        ['codigo' => '6.04.01', 'nombre' => 'Servicios Luz', 'tipo' => 'GASTO', 'subtipo' => 'Servicios', 'imputable' => true],
        ['codigo' => '6.04.02', 'nombre' => 'Servicios Agua', 'tipo' => 'GASTO', 'subtipo' => 'Servicios', 'imputable' => true],
        ['codigo' => '6.04.03', 'nombre' => 'Servicios Teléfono/Internet', 'tipo' => 'GASTO', 'subtipo' => 'Servicios', 'imputable' => true],
        ['codigo' => '6.05.01', 'nombre' => 'Publicidad y Marketing', 'tipo' => 'GASTO', 'subtipo' => 'Gastos Comerciales', 'imputable' => true],
        ['codigo' => '6.06.01', 'nombre' => 'Intereses Pagados', 'tipo' => 'GASTO', 'subtipo' => 'Gastos Financieros', 'imputable' => true],
        ['codigo' => '6.07.01', 'nombre' => 'Amortización Rodados', 'tipo' => 'GASTO', 'subtipo' => 'Amortizaciones', 'imputable' => true],
        ['codigo' => '6.07.02', 'nombre' => 'Amortización Mobiliario', 'tipo' => 'GASTO', 'subtipo' => 'Amortizaciones', 'imputable' => true],
        ['codigo' => '6.08.01', 'nombre' => 'Impuestos y Tasas', 'tipo' => 'GASTO', 'subtipo' => 'Impuestos', 'imputable' => true],
        ['codigo' => '6.09.01', 'nombre' => 'Mantenimiento y Reparaciones', 'tipo' => 'GASTO', 'subtipo' => 'Mantenimiento', 'imputable' => true],
        ['codigo' => '6.10.01', 'nombre' => 'Honorarios Profesionales', 'tipo' => 'GASTO', 'subtipo' => 'Servicios Profesionales', 'imputable' => true],
    ];
    
    $stmt = $db->prepare("INSERT INTO plan_cuentas (empresa_id, codigo, nombre, tipo, subtipo, imputable) VALUES (?, ?, ?, ?, ?, ?)");
    
    foreach ($cuentas as $cuenta) {
        try {
            $stmt->execute([$empresa_id, $cuenta['codigo'], $cuenta['nombre'], $cuenta['tipo'], $cuenta['subtipo'], $cuenta['imputable']]);
        } catch (Exception $e) {
            // Si ya existe, continuar
            continue;
        }
    }
    
    return count($cuentas);
}

/**
 * Configurar tasas de IVA estándar para Argentina
 */
function configurarIVAEstandar($empresa_id, $db) {
    // Obtener IDs de cuentas IVA
    $stmt = $db->prepare("SELECT id, codigo FROM plan_cuentas WHERE empresa_id = ? AND (codigo LIKE '1.03.01' OR codigo LIKE '3.02.01')");
    $stmt->execute([$empresa_id]);
    $cuentas_iva = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $tasas = [
        ['tasa' => 21.00, 'descripcion' => 'IVA 21%'],
        ['tasa' => 10.50, 'descripcion' => 'IVA 10.5%'],
        ['tasa' => 27.00, 'descripcion' => 'IVA 27%'],
        ['tasa' => 0.00, 'descripcion' => 'IVA Exento'],
    ];
    
    $stmt = $db->prepare("INSERT INTO config_iva (empresa_id, tasa, descripcion, tipo_cuenta_ventas, tipo_cuenta_compras) VALUES (?, ?, ?, ?, ?)");
    
    foreach ($tasas as $tasa) {
        try {
            $stmt->execute([
                $empresa_id, 
                $tasa['tasa'], 
                $tasa['descripcion'], 
                $cuentas_iva['3.02.01'] ?? null, // IVA Débito Fiscal
                $cuentas_iva['1.03.01'] ?? null  // IVA Crédito Fiscal
            ]);
        } catch (Exception $e) {
            continue;
        }
    }
}

// Uso
if (isset($_GET['empresa_id'])) {
    $empresa_id = intval($_GET['empresa_id']);
    $db = getDB();
    
    echo "<h2>Cargando Plan de Cuentas Estándar Argentina</h2>";
    
    $cuentas_cargadas = cargarPlanCuentasEstandar($empresa_id, $db);
    echo "<p>Cuentas cargadas: $cuentas_cargadas</p>";
    
    configurarIVAEstandar($empresa_id, $db);
    echo "<p>Configuración IVA completada</p>";
    
    echo "<p><a href='contabilidad.php'>Ir al módulo de contabilidad</a></p>";
}
?>
