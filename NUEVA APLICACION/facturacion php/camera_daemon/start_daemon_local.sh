#!/bin/bash

# Script para iniciar el Camera Daemon en entorno local
# Uso: ./start_daemon_local.sh

echo "🚀 Iniciando Camera Daemon en entorno local..."

# Obtener directorio del script
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DAEMON_DIR="$SCRIPT_DIR"

echo "📁 Directorio del daemon: $DAEMON_DIR"

# Verificar entorno virtual
if [ ! -d "$DAEMON_DIR/venv" ]; then
    echo "❌ Entorno virtual no encontrado. Creándolo..."
    cd "$DAEMON_DIR"
    python3 -m venv venv
    source venv/bin/activate
    pip install -r requirements_minimal.txt
    pip install opencv-python
else
    echo "✅ Entorno virtual encontrado"
fi

# Crear directorios necesarios
mkdir -p "$DAEMON_DIR/logs"
mkdir -p "$DAEMON_DIR/../videos"
mkdir -p "$DAEMON_DIR/faces"
mkdir -p "$DAEMON_DIR/models"

# Verificar permisos
chmod +x "$DAEMON_DIR/venv/bin/activate"

# Iniciar daemon
cd "$DAEMON_DIR"
echo "🎬 Iniciando daemon simple..."

source venv/bin/activate
python3 daemon_simple.py

echo "🛑 Daemon detenido"
