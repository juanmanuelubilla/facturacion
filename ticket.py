import os
from datetime import datetime
from db import get_connection

def generar_ticket(conn, items, total, venta_id, metodo_pago, vuelto=0, empresa_id=1):
    """
    Genera el texto del ticket basado en la configuración de ConfigUI guardada en la DB.
    """
    conf = {}
    cbte = None
    
    try:
        with conn.cursor() as cursor:
            # 1. Leer Configuración de la Empresa
            cursor.execute("SELECT * FROM nombre_negocio WHERE empresa_id=%s OR id=1 LIMIT 1", (empresa_id,))
            conf = cursor.fetchone() or {}
            
            # 2. Leer comprobante AFIP si existe
            try:
                cursor.execute("SELECT * FROM comprobante_afip WHERE venta_id=%s AND empresa_id=%s", (venta_id, empresa_id))
                cbte = cursor.fetchone()
            except:
                pass

        linea = "=" * 40
        sep = "-" * 40
        t = []

        # --- CABECERA ---
        t.append(linea)
        t.append(f"{conf.get('nombre_negocio', 'MI NEGOCIO').upper():^40}")
        if conf.get('eslogan'): 
            t.append(f"{conf.get('eslogan'):^40}")
        t.append(f"{conf.get('direccion', 'Direccion no seteada'):^40}")
        t.append(f"CUIT: {conf.get('cuit', '00-00000000-0'):^40}")
        t.append(f"IVA: {conf.get('condicion_iva', 'Consumidor Final'):^40}")
        t.append(linea)

        # --- DATOS VENTA ---
        tipo = "FACTURA C" if cbte else "TIQUET NO FISCAL"
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
            
            # Formato de cantidad (decimales para pesables)
            try:
                cant_val = float(cant)
                cant_str = f"{cant_val:.3f}" if cant_val % 1 != 0 else f"{int(cant_val)}"
            except:
                cant_str = str(cant)
                
            sub = f"{mon}{float(item.get('subtotal', 0)):.2f}"
            t.append(f"{cant_str:<5} {nom:<23} {sub:>10}")
        
        t.append(sep)
        t.append(f"{'TOTAL:':<15} {mon} {float(total):>22.2f}")
        t.append(f"{'PAGO:':<15} {metodo_pago:>23}")

        # --- CORRECCIÓN DEL VUELTO ---
        vuelto_val = float(vuelto)
        if "EFECTIVO" in str(metodo_pago).upper() and vuelto_val > 0.01:
            t.append(f"{'VUELTO:':<15} {mon} {vuelto_val:>22.2f}")
            
        t.append(linea)

        # --- PIE AFIP ---
        if cbte and cbte.get('cae'):
            t.append(f"CAE: {cbte['cae']}")
            t.append(f"Vto. CAE: {cbte.get('fecha_vto_cae', 'N/A')}")
            cuit_limpio = str(conf.get('cuit','')).replace('-','')
            cb_afip = f"{cuit_limpio}110001{cbte['cae']}"
            t.append(f"\n{cb_afip:^40}")
        
        t.append(f"\n{'GRACIAS POR SU COMPRA':^40}")
        t.append(linea)

        return "\n".join(t)

    except Exception as e:
        return f"ERROR AL GENERAR TICKET: {e}"

def guardar_ticket(conn, texto, venta_id, empresa_id):
    """
    Guarda el archivo en la ruta configurada en ConfigUI.
    """
    try:
        with conn.cursor() as cursor:
            # Buscamos la ruta de tickets configurada
            cursor.execute("SELECT ruta_tickets FROM nombre_negocio WHERE empresa_id=%s OR id=1 LIMIT 1", (empresa_id,))
            res = cursor.fetchone()
            ruta_base = res.get('ruta_tickets') if res and res.get('ruta_tickets') else 'tickets'
        
        # Validar ruta vacía o nula
        if not ruta_base or str(ruta_base).strip() in ["", "None"]:
            ruta_base = 'tickets'
            
        # Crear la ruta final con la subcarpeta de empresa
        ruta_final = os.path.abspath(os.path.join(ruta_base, f"empresa_{empresa_id}"))
        
        # Crear directorios si no existen
        if not os.path.exists(ruta_final): 
            os.makedirs(ruta_final, exist_ok=True)

        archivo_path = os.path.join(ruta_final, f"Ticket_{venta_id}.txt")
        
        with open(archivo_path, "w", encoding="utf-8") as f:
            f.write(texto)
        
        print(f"Ticket guardado con éxito en: {archivo_path}")
        return archivo_path

    except Exception as e:
        print(f"Error al guardar ticket físico: {e}")
        return None  # CORREGIDO: Se eliminó la coma que causaba el error de tupla