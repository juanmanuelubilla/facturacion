#!/usr/bin/env python3
"""
Script para ejecutar la migración de impresoras
"""

from db import get_connection

def ejecutar_migracion_impresoras():
    """Ejecuta la migración SQL para agregar campos de impresoras"""
    try:
        # Leer el archivo SQL
        with open('/home/pi/facturacion/db/migracion_impresoras.sql', 'r') as f:
            sql_content = f.read()
        
        # Conectar a la base de datos y ejecutar
        conn = get_connection()
        with conn.cursor() as cursor:
            # Dividir el SQL en sentencias individuales
            statements = [stmt.strip() for stmt in sql_content.split(';') if stmt.strip()]
            
            for statement in statements:
                if statement:
                    print(f"Ejecutando: {statement[:50]}...")
                    cursor.execute(statement)
            
            conn.commit()
            print("✅ Migración de impresoras ejecutada correctamente")
            
    except Exception as e:
        print(f"❌ Error al ejecutar migración: {e}")
    finally:
        if 'conn' in locals():
            conn.close()

if __name__ == "__main__":
    ejecutar_migracion_impresoras()
