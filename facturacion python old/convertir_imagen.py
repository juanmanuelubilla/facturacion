#!/usr/bin/env python3
from PIL import Image
import os

# Convertir PNG a JPG
try:
    # Abrir la imagen PNG
    img = Image.open('imagenes/123.png')
    
    # Convertir a RGB (JPG no soporta transparencia)
    if img.mode in ('RGBA', 'LA', 'P'):
        img = img.convert('RGB')
    
    # Guardar como JPG
    img.save('imagenes/123.jpg', 'JPEG', quality=95)
    
    print("✅ Imagen convertida de PNG a JPG: imagenes/123.jpg")
    
    # Verificar que el archivo existe
    if os.path.exists('imagenes/123.jpg'):
        size = os.path.getsize('imagenes/123.jpg')
        print(f"📁 Archivo JPG creado: {size} bytes")
    else:
        print("❌ No se pudo crear el archivo JPG")
        
except Exception as e:
    print(f"❌ Error convirtiendo imagen: {e}")
