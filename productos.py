from db import get_connection

def obtener_productos(empresa_id):
    conn = get_connection()
    try:
        with conn.cursor() as cursor:
            cursor.execute("""
                SELECT id, codigo, nombre, descripcion, precio, costo, stock, activo, imagen, ultimo_usuario_id
                FROM productos
                WHERE activo = 1 AND empresa_id = %s
                ORDER BY id DESC
            """, (empresa_id,))
            return cursor.fetchall()
    finally:
        conn.close()

def buscar_producto_por_codigo(codigo, empresa_id):
    conn = get_connection()
    try:
        with conn.cursor() as cursor:
            cursor.execute("""
                SELECT id, codigo, nombre, descripcion, precio, costo, stock, activo, imagen
                FROM productos
                WHERE codigo=%s AND activo = 1 AND empresa_id = %s
                LIMIT 1
            """, (codigo, empresa_id))
            return cursor.fetchone()
    finally:
        conn.close()

def buscar_productos_por_nombre(nombre, empresa_id):
    conn = get_connection()
    try:
        with conn.cursor() as cursor:
            cursor.execute("""
                SELECT id, codigo, nombre, descripcion, precio, costo, stock, activo, imagen
                FROM productos
                WHERE nombre LIKE %s AND activo = 1 AND empresa_id = %s
                ORDER BY nombre ASC
            """, (f"%{nombre}%", empresa_id))
            return cursor.fetchall()
    finally:
        conn.close()

def descontar_stock(producto_id, cantidad, empresa_id):
    conn = get_connection()
    try:
        with conn.cursor() as cursor:
            cursor.execute("""
                UPDATE productos 
                SET stock = stock - %s 
                WHERE id = %s AND empresa_id = %s
            """, (cantidad, producto_id, empresa_id))
            conn.commit()
    finally:
        conn.close()

def crear_producto(codigo, nombre, precio, costo, stock, imagen=None, descripcion=None, usuario_id=1, empresa_id=1):
    conn = get_connection()
    try:
        with conn.cursor() as cursor:
            cursor.execute("""
                INSERT INTO productos (codigo, nombre, descripcion, precio, costo, stock, imagen, activo, ultimo_usuario_id, empresa_id)
                VALUES (%s, %s, %s, %s, %s, %s, %s, 1, %s, %s)
            """, (codigo, nombre, descripcion, precio, costo, stock, imagen, usuario_id, empresa_id))
            conn.commit()
    finally:
        conn.close()

def validar_cupon(codigo_qr, empresa_id):
    conn = get_connection()
    try:
        with conn.cursor() as cursor:
            # Buscamos el cupón escaneado (QR) que esté activo para esta empresa
            sql = """SELECT * FROM cupones 
                     WHERE codigo_qr = %s AND activo = 1 AND empresa_id = %s"""
            cursor.execute(sql, (codigo_qr, empresa_id))
            return cursor.fetchone()
    finally:
        conn.close()

def obtener_regla_mayorista(producto_id, empresa_id):
    conn = get_connection()
    try:
        with conn.cursor() as cursor:
            cursor.execute("""
                SELECT cantidad_minima, descuento_porcentaje 
                FROM promociones_volumen 
                WHERE producto_id = %s AND empresa_id = %s AND activo = 1
            """, (producto_id, empresa_id))
            return cursor.fetchone()
    finally:
        conn.close()

def obtener_combos_activos(empresa_id):
    conn = get_connection()
    try:
        with conn.cursor() as cursor:
            cursor.execute("""
                SELECT productos_ids, descuento_porcentaje 
                FROM promociones_combos 
                WHERE empresa_id = %s AND activo = 1
            """, (empresa_id,))
            return cursor.fetchall()
    finally:
        conn.close()