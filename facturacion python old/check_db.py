#!/usr/bin/env python3
import pymysql

def check_productos():
    try:
        conn = pymysql.connect(
            host='r2.local',
            user='facturacion', 
            password='juanmanuel',
            database='facturacion',
            cursorclass=pymysql.cursors.DictCursor,
            autocommit=False
        )
        
        cursor = conn.cursor()
        
        # Consulta directa para ver todos los productos
        cursor.execute('SELECT id, codigo, nombre, activo, empresa_id, stock, precio FROM productos ORDER BY id')
        productos = cursor.fetchall()
        
        print('📦 ESTADO REAL DE PRODUCTOS EN BASE DE DATOS:')
        print('=' * 70)
        for p in productos:
            print(f'ID: {p["id"]} | Código: {p["codigo"]} | Nombre: {p["nombre"]} | Activo: {p["activo"]} | Empresa: {p["empresa_id"]} | Stock: {p["stock"]} | Precio: ${p["precio"]}')
        
        print(f'\n📊 Total productos: {len(productos)}')
        
        cursor.close()
        conn.close()
        
    except Exception as e:
        print(f'❌ Error: {e}')

if __name__ == "__main__":
    check_productos()
