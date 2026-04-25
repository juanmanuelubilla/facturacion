from db import get_connection

def obtener_productos(empresa_id):
    conn = get_connection()
    try:
        with conn.cursor() as cursor:
            cursor.execute("""
                SELECT p.id, p.codigo, p.nombre, p.descripcion, p.precio, p.costo, p.stock, p.activo, 
                       p.imagen, p.ultimo_usuario_id, p.venta_por_peso, p.categoria_id, p.tags,
                       c.nombre as categoria_nombre
                FROM productos p
                LEFT JOIN categorias c ON p.categoria_id = c.id
                WHERE p.activo = 1 AND p.empresa_id = %s
                ORDER BY p.id DESC
            """, (empresa_id,))
            return cursor.fetchall()
    finally:
        conn.close()

def buscar_producto_por_codigo(codigo, empresa_id):
    conn = get_connection()
    try:
        with conn.cursor() as cursor:
            cursor.execute("""
                SELECT p.id, p.codigo, p.nombre, p.descripcion, p.precio, p.costo, p.stock, p.activo, 
                       p.imagen, p.venta_por_peso, p.categoria_id, p.tags,
                       c.nombre as categoria_nombre
                FROM productos p
                LEFT JOIN categorias c ON p.categoria_id = c.id
                WHERE p.codigo=%s AND p.activo = 1 AND p.empresa_id = %s
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
                SELECT p.id, p.codigo, p.nombre, p.descripcion, p.precio, p.costo, p.stock, p.activo, 
                       p.imagen, p.venta_por_peso, p.categoria_id, p.tags,
                       c.nombre as categoria_nombre
                FROM productos p
                LEFT JOIN categorias c ON p.categoria_id = c.id
                WHERE p.nombre LIKE %s AND p.activo = 1 AND p.empresa_id = %s
                ORDER BY p.nombre ASC
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

def crear_producto(codigo, nombre, precio, costo, stock, imagen=None, descripcion=None, usuario_id=1, empresa_id=1, venta_por_peso=0):
    conn = get_connection()
    try:
        with conn.cursor() as cursor:
            cursor.execute("""
                INSERT INTO productos (codigo, nombre, descripcion, precio, costo, stock, imagen, activo, ultimo_usuario_id, empresa_id, venta_por_peso)
                VALUES (%s, %s, %s, %s, %s, %s, %s, 1, %s, %s, %s)
            """, (codigo, nombre, descripcion, precio, costo, stock, imagen, usuario_id, empresa_id, venta_por_peso))
            conn.commit()
    finally:
        conn.close()

def validar_cupon(codigo_qr, empresa_id):
    conn = get_connection()
    try:
        with conn.cursor() as cursor:
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

def buscar_productos_por_categoria(categoria_id, empresa_id):
    """Busca productos por categoría"""
    conn = get_connection()
    try:
        with conn.cursor() as cursor:
            cursor.execute("""
                SELECT p.id, p.codigo, p.nombre, p.descripcion, p.precio, p.costo, p.stock, p.activo, 
                       p.imagen, p.venta_por_peso, p.categoria_id, p.tags,
                       c.nombre as categoria_nombre
                FROM productos p
                LEFT JOIN categorias c ON p.categoria_id = c.id
                WHERE p.categoria_id = %s AND p.activo = 1 AND p.empresa_id = %s
                ORDER BY p.nombre ASC
            """, (categoria_id, empresa_id))
            return cursor.fetchall()
    finally:
        conn.close()

def buscar_productos_por_tag(tag, empresa_id):
    """Busca productos que contengan un tag específico"""
    conn = get_connection()
    try:
        with conn.cursor() as cursor:
            cursor.execute("""
                SELECT p.id, p.codigo, p.nombre, p.descripcion, p.precio, p.costo, p.stock, p.activo, 
                       p.imagen, p.venta_por_peso, p.categoria_id, p.tags,
                       c.nombre as categoria_nombre
                FROM productos p
                LEFT JOIN categorias c ON p.categoria_id = c.id
                WHERE p.tags LIKE %s AND p.activo = 1 AND p.empresa_id = %s
                ORDER BY p.nombre ASC
            """, (f"%{tag}%", empresa_id))
            return cursor.fetchall()
    finally:
        conn.close()

def obtener_categorias(empresa_id):
    """Obtiene todas las categorías de la empresa"""
    conn = get_connection()
    try:
        with conn.cursor() as cursor:
            cursor.execute("""
                SELECT id, nombre 
                FROM categorias 
                WHERE empresa_id = %s 
                ORDER BY nombre
            """, (empresa_id,))
            return cursor.fetchall()
    finally:
        conn.close()

def obtener_tags_unicos(empresa_id):
    """Obtiene todos los tags únicos de los productos"""
    conn = get_connection()
    try:
        with conn.cursor() as cursor:
            cursor.execute("""
                SELECT DISTINCT tags
                FROM productos 
                WHERE tags IS NOT NULL AND tags != '' AND activo = 1 AND empresa_id = %s
            """, (empresa_id,))
            resultados = cursor.fetchall()
            
            # Extraer tags individuales y eliminar duplicados
            todos_los_tags = set()
            for resultado in resultados:
                if resultado['tags']:
                    tags_individuales = [tag.strip() for tag in resultado['tags'].split(',')]
                    todos_los_tags.update(tags_individuales)
            
            return sorted(list(todos_los_tags))
    finally:
        conn.close()