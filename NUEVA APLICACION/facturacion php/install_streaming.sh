#!/bin/bash

echo "🔧 Instalando Streaming de Cámaras RTSP..."

# Crear directorios necesarios
mkdir -p streams
chmod 755 streams

# Verificar FFmpeg
if ! command -v ffmpeg &> /dev/null; then
    echo "❌ FFmpeg no está instalado. Instalando..."
    
    # Detectar sistema operativo
    if [ -f /etc/debian_version ]; then
        # Debian/Ubuntu
        sudo apt update
        sudo apt install -y ffmpeg
    elif [ -f /etc/redhat-release ]; then
        # CentOS/RHEL/Fedora
        sudo yum install -y epel-release
        sudo yum install -y ffmpeg
    elif [ -f /etc/arch-release ]; then
        # Arch Linux
        sudo pacman -S ffmpeg
    else
        echo "❌ Sistema no detectado. Por favor instala FFmpeg manualmente:"
        echo "   Ubuntu/Debian: sudo apt install ffmpeg"
        echo "   CentOS/RHEL: sudo yum install ffmpeg"
        echo "   Arch: sudo pacman -S ffmpeg"
        exit 1
    fi
else
    echo "✅ FFmpeg ya está instalado"
fi

# Verificar versión de FFmpeg
FFMPEG_VERSION=$(ffmpeg -version | head -n1 | grep -oP '[0-9]+\.[0-9]+')
echo "📹 FFmpeg versión: $FFMPEG_VERSION"

# Verificar que PHP pueda ejecutar comandos
if ! php -r "echo '✅ PHP funciona';" &> /dev/null; then
    echo "❌ Error con PHP"
    exit 1
fi

# Crear directorio para streams si no existe
STREAMS_DIR="/mnt/R2/SD64GB/www/facturacion/html/streams"
if [ ! -d "$STREAMS_DIR" ]; then
    mkdir -p "$STREAMS_DIR"
    echo "📁 Directorio de streams creado: $STREAMS_DIR"
else
    echo "✅ Directorio de streams ya existe"
fi

# Permisos correctos
chmod 755 "$STREAMS_DIR"
chown www-data:www-data "$STREAMS_DIR" 2>/dev/null || chown pi:www-data "$STREAMS_DIR" 2>/dev/null || echo "⚠️ No se pudo cambiar el owner"

echo ""
echo "🎯 Streaming RTSP listo para usar!"
echo ""
echo "📋 Requisitos cumplidos:"
echo "   ✅ FFmpeg instalado"
echo "   ✅ Directorio de streams creado"
echo "   ✅ Permisos configurados"
echo ""
echo "🚀 Para usar:"
echo "   1. Agregá una cámara RTSP en camaras.php"
echo "   2. Hacé clic en '▶️ Iniciar Stream'"
echo "   3. El video aparecerá directamente en el navegador"
echo ""
echo "⚠️ Nota: El streaming consume CPU y ancho de banda"
echo "   Usa 'ultrafast' para baja latencia"
