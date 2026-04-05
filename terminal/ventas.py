from db import get_connection
from productos import descontar_stock

# =========================
# CREAR VENTA
# =========================
def crear_venta():
    conn = get_connection()
    with conn.cursor() as cursor:
        cursor.execute("INSERT INTO ventas (total) VALUES (0)")
        venta_id = cursor.lastrowid
    conn.commit()
    return venta_id


# =========================
# AGREGAR ITEM
# =========================
def agregar_item(venta_id, item, cantidad):
    conn = get_connection()
    with conn.cursor() as cursor:
        cursor.execute("""
            INSERT INTO venta_items 
            (venta_id, producto_id, cantidad, precio_unitario, subtotal)
            VALUES (%s, %s, %s, %s, %s)
        """, (
            venta_id,
            item["id"],
            cantidad,
            item["precio"],
            item["precio"] * cantidad
        ))
    conn.commit()


# =========================
# CERRAR VENTA (DESCUENTA STOCK)
# =========================
def cerrar_venta(venta_id, total, items):
    conn = get_connection()
    with conn.cursor() as cursor:

        cursor.execute("""
            UPDATE ventas SET total=%s WHERE id=%s
        """, (total, venta_id))

        for item in items:
            descontar_stock(item["id"], item["cantidad"])

    conn.commit()


# =========================
# REGISTRAR PAGO
# =========================
def registrar_pago(venta_id, monto):
    conn = get_connection()
    with conn.cursor() as cursor:
        cursor.execute("""
            INSERT INTO pagos (venta_id, metodo, monto)
            VALUES (%s, 'EFECTIVO', %s)
        """, (venta_id, monto))
    conn.commit()