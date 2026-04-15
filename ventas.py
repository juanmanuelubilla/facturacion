from db import get_connection
from productos import descontar_stock

def crear_venta(empresa_id, usuario_id, cliente_id=None):
    """
    Crea un registro de venta. 
    Se agregó cliente_id para coincidir con la llamada desde el GUI.
    """
    conn = get_connection()
    try:
        # Si el cliente_id es 0 (Consumidor Final), lo guardamos como None (NULL en DB)
        cid = cliente_id if cliente_id != 0 else None
        
        with conn.cursor() as cursor:
            # Se agregó la columna cliente_id al INSERT
            cursor.execute("""
                INSERT INTO ventas (total, ganancia, usuario_id, empresa_id, cliente_id, fecha, estado) 
                VALUES (0, 0, %s, %s, %s, NOW(), 'COMPLETADA')
            """, (usuario_id, empresa_id, cid))
            venta_id = cursor.lastrowid
        conn.commit()
        return venta_id
    finally:
        conn.close()

def agregar_item(venta_id, item, cantidad):
    conn = get_connection()
    try:
        with conn.cursor() as cursor:
            precio = float(item.get("precio", 0))
            costo = float(item.get("costo", 0))
            subtotal = precio * cantidad

            cursor.execute("""
                INSERT INTO venta_items 
                (venta_id, producto_id, cantidad, precio_unitario, costo_unitario, subtotal)
                VALUES (%s, %s, %s, %s, %s, %s)
            """, (venta_id, item["id"], cantidad, precio, costo, subtotal))
        conn.commit()
    finally:
        conn.close()

def cerrar_venta(venta_id, total, items, empresa_id, ganancia=None):
    """
    Merge corregido: Ahora acepta 'ganancia' del POS para evitar errores técnicos.
    Mantiene el descuento de stock y la integridad de la empresa.
    """
    conn = get_connection()
    try:
        with conn.cursor() as cursor:
            # Si el POS ya nos manda la ganancia calculada (ganancia_real), la usamos.
            if ganancia is not None:
                ganancia_total = ganancia
            else:
                ganancia_total = 0
                for item in items:
                    precio = float(item.get("precio", 0))
                    costo = float(item.get("costo", 0))
                    cantidad = item.get("cantidad", 0)
                    ganancia_total += (precio - costo) * cantidad

            # Siempre descontamos el stock independientemente de cómo venga la ganancia
            for item in items:
                cantidad = item.get("cantidad", 0)
                descontar_stock(item["id"], cantidad, empresa_id)

            cursor.execute("""
                UPDATE ventas 
                SET total=%s, ganancia=%s
                WHERE id=%s AND empresa_id=%s
            """, (total, ganancia_total, venta_id, empresa_id))
        conn.commit()
    finally:
        conn.close()

def registrar_pago(venta_id, monto, metodo_pago, entregado, vuelto, empresa_id):
    conn = get_connection()
    try:
        with conn.cursor() as cursor:
            cursor.execute("""
                INSERT INTO pagos (venta_id, empresa_id, metodo, monto, entregado, vuelto, estado)
                VALUES (%s, %s, %s, %s, %s, %s, 'completado')
            """, (venta_id, empresa_id, metodo_pago, monto, entregado, vuelto))
        conn.commit()
    finally:
        conn.close()