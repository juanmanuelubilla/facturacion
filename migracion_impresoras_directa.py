#!/usr/bin/env python3
"""
Script para ejecutar migración de impresoras usando subprocess
"""

import subprocess
import sys

def ejecutar_comandos_sql():
    """Ejecuta los comandos SQL para agregar campos de impresoras"""
    
    comandos = [
        # Agregar campo impresora_auto
        'mysql -u root -p facturacion -e "ALTER TABLE nombre_negocio ADD COLUMN IF NOT EXISTS impresora_auto BOOLEAN DEFAULT FALSE COMMENT \'Imprimir ticket automáticamente después de cada venta\'"',
        
        # Agregar campo impresora_ticket  
        'mysql -u root -p facturacion -e "ALTER TABLE nombre_negocio ADD COLUMN IF NOT EXISTS impresora_ticket VARCHAR(255) DEFAULT \'Default\' COMMENT \'Nombre o IP de la impresora de tickets\'"',
        
        # Agregar campo impresora_factura
        'mysql -u root -p facturacion -e "ALTER TABLE nombre_negocio ADD COLUMN IF NOT EXISTS impresora_factura VARCHAR(255) DEFAULT \'Default\' COMMENT \'Nombre o IP de la impresora de facturas\'"',
        
        # Crear índices
        'mysql -u root -p facturacion -e "CREATE INDEX IF NOT EXISTS idx_impresora_auto ON nombre_negocio(impresora_auto)"',
        'mysql -u root -p facturacion -e "CREATE INDEX IF NOT EXISTS idx_impresora_ticket ON nombre_negocio(impresora_ticket)"',
        'mysql -u root -p facturacion -e "CREATE INDEX IF NOT EXISTS idx_impresora_factura ON nombre_negocio(impresora_factura)"'
    ]
    
    print("🔧 Ejecutando migración de impresoras...")
    
    for i, cmd in enumerate(comandos, 1):
        print(f"📝 Paso {i}/{len(comandos)}: Agregando campos de impresoras...")
        try:
            result = subprocess.run(cmd, shell=True, capture_output=True, text=True)
            if result.returncode == 0:
                print(f"✅ Paso {i} completado")
            else:
                print(f"❌ Error en paso {i}: {result.stderr}")
        except Exception as e:
            print(f"❌ Error ejecutando paso {i}: {e}")
    
    print("🎉 Migración de impresoras finalizada")

if __name__ == "__main__":
    ejecutar_comandos_sql()
