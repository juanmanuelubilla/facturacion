#!/bin/bash

# Script de instalación automática para Camera Daemon como servicio systemd
# Uso: sudo ./install_service.sh

set -e

# Colores para salida
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Funciones de logging
log() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')] $1${NC}"
}

error() {
    echo -e "${RED}[ERROR] $1${NC}"
}

warning() {
    echo -e "${YELLOW}[WARNING] $1${NC}"
}

info() {
    echo -e "${BLUE}[INFO] $1${NC}"
}

# Verificar si se ejecuta como root
if [[ $EUID -ne 0 ]]; then
   error "Este script debe ejecutarse como root (use sudo)"
   exit 1
fi

log "🚀 Iniciando instalación de Camera Daemon como servicio..."

# Variables
DAEMON_DIR="/var/www/facturacion/camera_daemon"
SERVICE_FILE="/etc/systemd/system/camera-daemon.service"
SERVICE_USER="pi"

# Verificar que el daemon existe
if [ ! -f "$DAEMON_DIR/daemon_simple.py" ]; then
    error "No se encuentra daemon_simple.py en $DAEMON_DIR"
    exit 1
fi

# Verificar entorno virtual
if [ ! -d "$DAEMON_DIR/venv" ]; then
    error "No se encuentra el entorno virtual en $DAEMON_DIR/venv"
    exit 1
fi

# Crear archivo de servicio
log "📝 Creando archivo de servicio systemd..."

cat > "$SERVICE_FILE" << 'EOF'
[Unit]
Description=Camera Daemon - Sistema de Grabación y Monitoreo
After=network.target mysql.service
Wants=mysql.service

[Service]
Type=simple
User=pi
Group=pi
WorkingDirectory=/var/www/facturacion/camera_daemon
Environment="PATH=/var/www/facturacion/camera_daemon/venv/bin"
ExecStart=/var/www/facturacion/camera_daemon/venv/bin/python daemon_simple.py
ExecReload=/bin/kill -HUP $MAINPID
Restart=always
RestartSec=10
StandardOutput=journal
StandardError=journal
SyslogIdentifier=camera-daemon

# Variables de entorno para la base de datos
Environment="DB_HOST=localhost"
Environment="DB_NAME=facturacion"
Environment="DB_USER=facturacion"
Environment="DB_PASS=facturacion"

[Install]
WantedBy=multi-user.target
EOF

log "✅ Archivo de servicio creado en $SERVICE_FILE"

# Configurar permisos
log "🔧 Configurando permisos..."

chmod +x "$DAEMON_DIR/daemon_simple.py"
chown -R $SERVICE_USER:$SERVICE_USER "$DAEMON_DIR"
chmod -R 755 "$DAEMON_DIR"

# Crear directorio de videos si no existe
if [ ! -d "/var/www/facturacion/videos" ]; then
    mkdir -p "/var/www/facturacion/videos"
    chmod 777 "/var/www/facturacion/videos"
    log "📁 Directorio de videos creado"
fi

# Recargar systemd
log "🔄 Recargando systemd..."
systemctl daemon-reload

# Habilitar servicio
log "🔌 Habilitando servicio para inicio automático..."
systemctl enable camera-daemon.service

# Iniciar servicio
log "▶️ Iniciando servicio..."
systemctl start camera-daemon.service

# Esperar un momento para que el servicio inicie
sleep 3

# Verificar estado
if systemctl is-active --quiet camera-daemon.service; then
    log "✅ Servicio iniciado correctamente"
    
    # Mostrar estado
    echo ""
    info "Estado del servicio:"
    systemctl status camera-daemon.service --no-pager -l
    
    echo ""
    log "🎯 Instalación completada exitosamente!"
    echo ""
    info "Comandos útiles:"
    echo "  Ver estado:     sudo systemctl status camera-daemon"
    echo "  Ver logs:        sudo journalctl -u camera-daemon -f"
    echo "  Reiniciar:       sudo systemctl restart camera-daemon"
    echo "  Detener:         sudo systemctl stop camera-daemon"
    echo ""
    info "El daemon se iniciará automáticamente con el sistema."
    
else
    error "El servicio no pudo iniciarse. Verificando logs..."
    echo ""
    journalctl -u camera-daemon --since "1 minute ago" --no-pager
    echo ""
    error "Revise los errores anteriores y corrija el problema."
    exit 1
fi

log "🏁 Instalación finalizada"
