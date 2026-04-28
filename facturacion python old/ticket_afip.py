from afip import obtener_comprobante_por_venta, formatear_numero_comprobante, tipo_cbte_to_texto

def generar_ticket_fiscal(db_conn, venta_id, productos, total_venta):
    cursor = db_conn.cursor()
    
    # 1. Buscamos los datos fiscales guardados previamente por afip.py
    comp = obtener_comprobante_por_venta(cursor, venta_id)
    
    if not comp:
        return "ERROR: No hay datos fiscales para esta venta."

    # 2. Formateamos los datos
    nro_oficial = formatear_numero_comprobante(comp["punto_vta"], comp["nro_cbte"])
    tipo_nombre = tipo_cbte_to_texto(comp["tipo_cbte"])
    
    # 3. Diseño del Ticket
    print("=" * 40)
    print(f"{'NEXUS TECH':^40}") # Nombre de tu negocio
    print(f"{'CUIT: 20-XXXXXXXX-9':^40}")
    print(f"{'Ing. Brutos: 20-XXXXXXXX-9':^40}")
    print(f"{'Inicio Actividades: 01/01/2024':^40}")
    print(f"{'IVA EXENTO / RESP. MONOTRIBUTO':^40}")
    print("-" * 40)
    
    # Letra de Factura (A, B o C) centrada en un recuadro
    letra = tipo_nombre.split()[-1] # Toma la 'A', 'B' o 'C'
    print(f"{'[' + letra + ']':^40}")
    print(f"{tipo_nombre:^40}")
    print(f"Comp. Nro: {nro_oficial}")
    print(f"Fecha: {datetime.now().strftime('%d/%m/%Y %H:%M')}")
    print("-" * 40)

    # 4. Detalle de productos
    print(f"{'Producto':<20} {'Cant':>5} {'Subtotal':>12}")
    for p in productos:
        nombre = p['nombre'][:18] # Truncar si es muy largo
        print(f"{nombre:<20} {p['cantidad']:>5} ${p['subtotal']:>11.2f}")

    print("-" * 40)
    print(f"{'TOTAL:':<20} {'$':>5} {total_venta:>12.2f}")
    print("-" * 40)

    # 5. Pie de página AFIP (Lo más importante)
    print(f"CAE: {comp['cae']}")
    print(f"Vto. CAE: {comp['fecha_vto']}")
    print("-" * 40)
    print(f"{'GRACIAS POR SU COMPRA':^40}")
    print("=" * 40)