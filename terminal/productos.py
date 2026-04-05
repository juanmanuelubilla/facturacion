from db import get_connection

# =========================
# OBTENER PRODUCTOS
# =========================
def obtener_productos():
    conn = get_connection()
    with conn.cursor() as cursor:
        cursor.execute("""
            SELECT * FROM productos 
            WHERE activo = 1
        """)
        return cursor.fetchall()


# =========================
# BUSCAR POR CÓDIGO
# =========================
def buscar_producto_por_codigo(codigo):
    conn = get_connection()
    with conn.cursor() as cursor:
        cursor.execute("""
            SELECT * FROM productos 
            WHERE codigo=%s AND activo=1
        """, (codigo,))
        return cursor.fetchone()


# =========================
# BUSCAR POR NOMBRE
# =========================
def buscar_productos_por_nombre(texto):
    conn = get_connection()
    with conn.cursor() as cursor:
        cursor.execute("""
            SELECT * FROM productos
            WHERE nombre LIKE %s AND activo=1
        """, (f"%{texto}%",))
        return cursor.fetchall()


# =========================
# CREAR PRODUCTO
# =========================
def crear_producto(codigo, nombre, precio, stock, imagen=None):
    conn = get_connection()
    with conn.cursor() as cursor:
        cursor.execute("""
            INSERT INTO productos (codigo, nombre, precio, stock, imagen, activo)
            VALUES (%s, %s, %s, %s, %s, 1)
        """, (codigo, nombre, precio, stock, imagen))
    conn.commit()


# =========================
# DESCONTAR STOCK
# =========================
def descontar_stock(id_producto, cantidad):
    conn = get_connection()
    with conn.cursor() as cursor:
        cursor.execute("""
            UPDATE productos 
            SET stock = stock - %s
            WHERE id = %s AND stock >= %s
        """, (cantidad, id_producto, cantidad))
    conn.commit()