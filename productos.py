from db import get_connection

# =========================
# OBTENER PRODUCTOS
# =========================
def obtener_productos():
    conn = get_connection()
    try:
        with conn.cursor() as cursor:
            # Incluimos 'activo' y 'ultimo_usuario_id'
            cursor.execute("""
                SELECT id, codigo, nombre, descripcion, precio, costo, stock, activo, imagen, ultimo_usuario_id
                FROM productos
                WHERE activo = 1
                ORDER BY id DESC
            """)
            return cursor.fetchall()
    finally:
        conn.close()

# =========================
# BUSCAR POR CÓDIGO
# =========================
def buscar_producto_por_codigo(codigo):
    conn = get_connection()
    try:
        with conn.cursor() as cursor:
            cursor.execute("""
                SELECT id, codigo, nombre, descripcion, precio, costo, stock, activo, imagen
                FROM productos
                WHERE codigo=%s AND activo = 1
                LIMIT 1
            """, (codigo,))
            return cursor.fetchone()
    finally:
        conn.close()

# =========================
# BUSCAR POR NOMBRE (La que faltaba)
# =========================
def buscar_productos_por_nombre(nombre):
    conn = get_connection()
    try:
        with conn.cursor() as cursor:
            cursor.execute("""
                SELECT id, codigo, nombre, descripcion, precio, costo, stock, activo, imagen
                FROM productos
                WHERE nombre LIKE %s AND activo = 1
                ORDER BY nombre ASC
            """, (f"%{nombre}%",))
            return cursor.fetchall()
    finally:
        conn.close()

# =========================
# CREAR PRODUCTO (Sincronizado con usuario_id)
# =========================
def crear_producto(codigo, nombre, precio, costo, stock, imagen=None, descripcion=None, usuario_id=1):
    conn = get_connection()
    try:
        with conn.cursor() as cursor:
            cursor.execute("""
                INSERT INTO productos (codigo, nombre, descripcion, precio, costo, stock, imagen, activo, ultimo_usuario_id)
                VALUES (%s, %s, %s, %s, %s, %s, %s, 1, %s)
            """, (codigo, nombre, descripcion, precio, costo, stock, imagen, usuario_id))
            conn.commit()
    finally:
        conn.close()

# =========================
# ACTUALIZAR PRODUCTO (Sincronizado con usuario_id)
# =========================
def actualizar_producto(producto_id, nombre, precio, costo, stock, descripcion=None, imagen=None, codigo=None, usuario_id=1):
    conn = get_connection()
    try:
        with conn.cursor() as cursor:
            # Si pasamos el código también lo actualizamos, si no, mantenemos el resto
            sql = """
                UPDATE productos
                SET nombre=%s, descripcion=%s, precio=%s, costo=%s, stock=%s, imagen=%s, ultimo_usuario_id=%s
            """
            params = [nombre, descripcion, precio, costo, stock, imagen, usuario_id]
            
            if codigo:
                sql += ", codigo=%s"
                params.append(codigo)
            
            sql += " WHERE id=%s"
            params.append(producto_id)
            
            cursor.execute(sql, tuple(params))
            conn.commit()
    finally:
        conn.close()

# =========================
# DESACTIVAR PRODUCTO (SOFT DELETE)
# =========================
def desactivar_producto(codigo):
    conn = get_connection()
    try:
        with conn.cursor() as cursor:
            cursor.execute("""
                UPDATE productos
                SET activo = 0
                WHERE codigo=%s
            """, (codigo,))
            conn.commit()
    finally:
        conn.close()

# =========================
# DESCONTAR STOCK
# =========================
def descontar_stock(producto_id, cantidad):
    conn = get_connection()
    try:
        with conn.cursor() as cursor:
            cursor.execute("""
                UPDATE productos
                SET stock = stock - %s
                WHERE id = %s
            """, (cantidad, producto_id))
            conn.commit()
    finally:
        conn.close()

# =========================
# LÓGICA DE CUPONES
# =========================
def validar_cupon(codigo_qr):
    conn = get_connection()
    try:
        with conn.cursor() as cursor:
            sql = """SELECT * FROM cupones 
                     WHERE codigo_qr = %s AND activo = 1 
                     AND (fecha_expiracion IS NULL OR fecha_expiracion >= CURDATE())
                     AND usos_actuales < usos_maximos"""
            cursor.execute(sql, (codigo_qr,))
            return cursor.fetchone()
    finally:
        conn.close()

def aplicar_uso_cupon(cupon_id):
    conn = get_connection()
    try:
        with conn.cursor() as cursor:
            cursor.execute("UPDATE cupones SET usos_actuales = usos_actuales + 1 WHERE id = %s", (cupon_id,))
            conn.commit()
    finally:
        conn.close()

# =========================
# GESTIÓN DE PROMOS (Para la UI)
# =========================
def crear_cupon(codigo, descuento, max_usos, expiracion):
    conn = get_connection()
    try:
        with conn.cursor() as cursor:
            sql = """INSERT INTO cupones (codigo_qr, descuento_porcentaje, usos_maximos, fecha_expiracion) 
                     VALUES (%s, %s, %s, %s)"""
            cursor.execute(sql, (codigo, descuento, max_usos, expiracion))
            conn.commit()
    finally:
        conn.close()
