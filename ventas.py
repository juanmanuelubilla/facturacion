from db import get_connection
from productos import descontar_stock

# =========================
# CREAR VENTA (ACTUALIZADO)
# =========================
def crear_venta(usuario_id=None):
    conn = get_connection()
    try:
        with conn.cursor() as cursor:
            # Ahora incluimos el usuario_id que viene de la sesión
            cursor.execute("INSERT INTO ventas (total, ganancia, usuario_id) VALUES (0, 0, %s)", (usuario_id,))
            venta_id = cursor.lastrowid
        conn.commit()
        return venta_id
    finally:
        conn.close()

# =========================
# AGREGAR ITEM
# =========================
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

# =========================
# CERRAR VENTA
# =========================
def cerrar_venta(venta_id, total, items):
    conn = get_connection()
    try:
        with conn.cursor() as cursor:
            ganancia_total = 0
            for item in items:
                precio = float(item.get("precio", 0))
                costo = float(item.get("costo", 0))
                cantidad = item.get("cantidad", 0)
                ganancia_total += (precio - costo) * cantidad
                descontar_stock(item["id"], cantidad)

            cursor.execute("""
                UPDATE ventas 
                SET total=%s, ganancia=%s
                WHERE id=%s
            """, (total, ganancia_total, venta_id))
        conn.commit()
    finally:
        conn.close()

# =========================
# REGISTRAR PAGO
# =========================
def registrar_pago(venta_id, monto, metodo_pago='EFECTIVO', entregado=0, vuelto=0):
    conn = get_connection()
    try:
        with conn.cursor() as cursor:
            cursor.execute("""
                INSERT INTO pagos (venta_id, metodo, monto, entregado, vuelto, estado)
                VALUES (%s, %s, %s, %s, %s, 'completado')
            """, (venta_id, metodo_pago, monto, entregado, vuelto))
        conn.commit()
    finally:
        conn.close()