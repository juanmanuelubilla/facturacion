#!/usr/bin/env python3
import os
import sys
sys.path.append('/home/pi/facturacion')

from db import get_connection

# Usar la conexión del sistema
conn = get_connection()
cursor = conn.cursor()

# Actualizar imagen de Coca-Cola a JPG
cursor.execute("UPDATE productos SET imagen = 'imagenes/123.jpg' WHERE nombre LIKE '%Coca-Cola%'")

# Verificar el cambio
cursor.execute("SELECT id, nombre, imagen FROM productos WHERE nombre LIKE '%Coca-Cola%'")
results = cursor.fetchall()

print("Productos actualizados:")
for row in results:
    print(f"ID: {row[0]}, Nombre: {row[1]}, Imagen: {row[2]}")

conn.commit()
conn.close()

print("✅ Imagen actualizada a JPG para prueba")
