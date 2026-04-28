#!/bin/bash
# Script de instalación para el daemon de reconocimiento facial

set -e

echo "=== Instalación del Daemon de Reconocimiento Facial ==="

# Verificar si se ejecuta como root
if [ "$EUID" -ne 0 ]; then 
    echo "Por favor, ejecuta como root (sudo)"
    exit 1
fi

# Directorio de instalación
DAEMON_DIR="/home/pi/facturacion_python"
SERVICE_FILE="$DAEMON_DIR/face-recognition-daemon.service"
SYSTEMD_DIR="/etc/systemd/system/"

# Instalar dependencias de Python
echo "Instalando dependencias de Python..."
pip3 install -r "$DAEMON_DIR/requirements.txt"

# Crear directorio de logs
echo "Creando directorio de logs..."
mkdir -p /var/log
touch /var/log/face_recognition_daemon.log
chown pi:pi /var/log/face_recognition_daemon.log

# Copiar servicio systemd
echo "Instalando servicio systemd..."
cp "$SERVICE_FILE" "$SYSTEMD_DIR"

# Recargar systemd
echo "Recargando systemd..."
systemctl daemon-reload

# Habilitar servicio
echo "Habilitando servicio..."
systemctl enable face-recognition-daemon.service

# Iniciar servicio
echo "Iniciando servicio..."
systemctl start face-recognition-daemon.service

# Verificar estado
echo "Verificando estado del servicio..."
systemctl status face-recognition-daemon.service

echo ""
echo "=== Instalación completada ==="
echo "Comandos útiles:"
echo "  - Ver estado: sudo systemctl status face-recognition-daemon"
echo "  - Ver logs: sudo journalctl -u face-recognition-daemon -f"
echo "  - Reiniciar: sudo systemctl restart face-recognition-daemon"
echo "  - Detener: sudo systemctl stop face-recognition-daemon"
