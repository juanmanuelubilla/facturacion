import os
from datetime import datetime

def generar_ticket(conn, items, total, venta_id, metodo_pago, vuelto=0):
    conf = {}
    cbte = None
    
    try:
        with conn.cursor() as cursor:
            # 1. Leer Configuración del Negocio
            try:
                cursor.execute("SELECT * FROM nombre_negocio WHERE id=1")
                conf = cursor.fetchone() or {}
            except: pass
            
            # 2. Leer de la tabla CORRECTA: comprobante_afip
            try:
                cursor.execute("SELECT * FROM comprobante_afip WHERE venta_id=%s", (venta_id,))
                cbte = cursor.fetchone()
            except Exception as e:
                print(f"No se pudo leer comprobante_afip: {e}")

        linea = "=" * 40
        sep = "-" * 40
        t = []

        # --- CABECERA ---
        t.append(linea)
        t.append(f"{conf.get('nombre_negocio', 'MI NEGOCIO').upper():^40}")
        if conf.get('eslogan'): t.append(f"{conf.get('eslogan'):^40}")
        t.append(f"{conf.get('direccion', 'Direccion no seteada'):^40}")
        t.append(f"CUIT: {conf.get('cuit', '00-00000000-0'):^40}")
        t.append(f"IVA: {conf.get('condicion_iva', 'Resp. Inscripto'):^40}")
        t.append(linea)

        # --- DATOS FACTURA ---
        # Si hay datos en comprobante_afip, es Factura C
        tipo = "FACTURA C" if cbte else "TIQUET NO FISCAL"
        # Usamos nro_cbte de la tabla afip
        nro = f"{cbte['nro_cbte']:08}" if cbte and cbte.get('nro_cbte') else f"{venta_id:08}"
        
        t.append(f"{tipo:^40}")
        t.append(f"P.V.: 00001 - Nro: {nro}")
        t.append(f"Fecha: {datetime.now().strftime('%d/%m/%Y %H:%M')}")
        t.append(sep)

        # --- ITEMS ---
        mon = conf.get('moneda', '$')
        t.append(f"{'CANT':<5} {'DETALLE':<23} {'SUBT':>10}")
        for item in items:
            nom = str(item.get('nombre', 'Prod'))[:22]
            cant = item.get('cantidad', 1)
            sub = f"{mon}{float(item.get('subtotal', 0)):.2f}"
            t.append(f"{cant:<5} {nom:<23} {sub:>10}")
        
        t.append(sep)
        t.append(f"{'TOTAL:':<15} {mon} {float(total):>22.2f}")
        t.append(f"{'PAGO:':<15} {metodo_pago:>23}")
        if float(vuelto) > 0:
            t.append(f"{'VUELTO:':<15} {mon} {float(vuelto):>22.2f}")
        t.append(linea)

        # --- PIE AFIP ---
        if cbte and cbte.get('cae'):
            t.append(f"CAE: {cbte['cae']}")
            # OJO: Aquí usamos el nombre exacto de tu SQL: fecha_vto_cae
            t.append(f"Vto. CAE: {cbte.get('fecha_vto_cae', 'N/A')}")
            
            cuit_limpio = str(conf.get('cuit','')).replace('-','')
            cb_afip = f"{cuit_limpio}110001{cbte['cae']}"
            t.append(f"\n{cb_afip:^40}")
        
        t.append(f"\n{'GRACIAS POR SU COMPRA':^40}")
        t.append(linea)

        return "\n".join(t)

    except Exception as e:
        return f"ERROR AL GENERAR TICKET: {e}"

def guardar_ticket(conn, texto, venta_id):
    try:
        with conn.cursor() as cursor:
            cursor.execute("SELECT ruta_tickets FROM nombre_negocio WHERE id=1")
            res = cursor.fetchone()
            ruta = res.get('ruta_tickets', 'tickets') if res else 'tickets'
        
        if not ruta or ruta == 'None': ruta = 'tickets'
        if not os.path.exists(ruta): os.makedirs(ruta)

        archivo = os.path.join(ruta, f"Factura_{venta_id}.txt")
        with open(archivo, "w", encoding="utf-8") as f:
            f.write(texto)
        return archivo
    except:
        return None