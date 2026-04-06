from db import get_connection


# =========================
# OBTENER PRODUCTOS
# =========================
def obtener_productos():
    conn = get_connection()
    with conn.cursor() as cursor:
        cursor.execute("""
            SELECT id, codigo, nombre, descripcion, precio, costo, stock, activo, imagen
            FROM productos
            ORDER BY id DESC
        """)
        return cursor.fetchall()


# =========================
# BUSCAR POR CÓDIGO
# =========================
def buscar_producto_por_codigo(codigo):
    conn = get_connection()
    with conn.cursor() as cursor:
        cursor.execute("""
            SELECT id, codigo, nombre, descripcion, precio, costo, stock, activo, imagen
            FROM productos
            WHERE codigo=%s
            LIMIT 1
        """, (codigo,))
        return cursor.fetchone()


# =========================
# BUSCAR POR NOMBRE
# =========================
def buscar_productos_por_nombre(nombre):
    conn = get_connection()
    with conn.cursor() as cursor:
        cursor.execute("""
            SELECT id, codigo, nombre, descripcion, precio, costo, stock, activo, imagen
            FROM productos
            WHERE nombre LIKE %s
            ORDER BY nombre ASC
        """, (f"%{nombre}%",))
        return cursor.fetchall()


# =========================
# CREAR PRODUCTO
# =========================
def crear_producto(codigo, nombre, precio, costo, stock, imagen=None, descripcion=None):
    conn = get_connection()
    with conn.cursor() as cursor:
        cursor.execute("""
            INSERT INTO productos (codigo, nombre, descripcion, precio, costo, stock, imagen, activo)
            VALUES (%s, %s, %s, %s, %s, %s, %s, 1)
        """, (
            codigo,
            nombre,
            descripcion,
            precio,
            costo,
            stock,
            imagen
        ))
    conn.commit()


# =========================
# ACTUALIZAR PRODUCTO
# =========================
def actualizar_producto(producto_id, nombre, precio, costo, stock, descripcion=None, imagen=None):
    conn = get_connection()
    with conn.cursor() as cursor:
        cursor.execute("""
            UPDATE productos
            SET nombre=%s, descripcion=%s, precio=%s, costo=%s, stock=%s, imagen=%s
            WHERE id=%s
        """, (
            nombre,
            descripcion,
            precio,
            costo,
            stock,
            imagen,
            producto_id
        ))
    conn.commit()


# =========================
# DESACTIVAR PRODUCTO (SOFT DELETE)
# =========================
def desactivar_producto(codigo):
    conn = get_connection()
    with conn.cursor() as cursor:
        cursor.execute("""
            UPDATE productos
            SET activo = 0
            WHERE codigo=%s
        """, (codigo,))
    conn.commit()


# =========================
# DESCONTAR STOCK
# =========================
def descontar_stock(producto_id, cantidad):
    conn = get_connection()
    with conn.cursor() as cursor:
        cursor.execute("""
            UPDATE productos
            SET stock = stock - %s
            WHERE id = %s
        """, (cantidad, producto_id))
    conn.commit()