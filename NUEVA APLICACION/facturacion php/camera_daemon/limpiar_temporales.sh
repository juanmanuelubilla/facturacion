#!/bin/bash

# Script para limpiar archivos temporales y liberar espacio
# Uso: ./limpiar_temporales.sh

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

warning() {
    echo -e "${YELLOW}[WARNING] $1${NC}"
}

info() {
    echo -e "${BLUE}[INFO] $1${NC}"
}

log "🧹 Iniciando limpieza de archivos temporales..."

# Variables
DAEMON_DIR="/var/www/facturacion/camera_daemon"
PROJECT_ROOT="/var/www/facturacion"

# Espacio antes de la limpieza
log "📊 Espacio antes de la limpieza:"
df -h | grep -E "(/$|/var)"

# 1. Limpiar __pycache__ del entorno virtual (mayor ahorro de espacio)
if [ -d "$DAEMON_DIR/venv" ]; then
    log "🔍 Buscando archivos __pycache__ en el entorno virtual..."
    
    # Contar archivos antes
    PYC_COUNT=$(find "$DAEMON_DIR/venv" -name "*.pyc" | wc -l)
    CACHE_SIZE=$(du -sh "$DAEMON_DIR/venv"/*/__pycache__ 2>/dev/null | awk '{sum+=$1} END {print sum}' || echo "0")
    
    info "Archivos .pyc encontrados: $PYC_COUNT"
    info "Espacio en __pycache__: ${CACHE_SIZE}K"
    
    if [ "$PYC_COUNT" -gt 0 ]; then
        log "🗑️ Eliminando archivos __pycache__ del entorno virtual..."
        find "$DAEMON_DIR/venv" -type d -name "__pycache__" -exec rm -rf {} + 2>/dev/null || true
        find "$DAEMON_DIR/venv" -name "*.pyc" -delete 2>/dev/null || true
        
        PYC_AFTER=$(find "$DAEMON_DIR/venv" -name "*.pyc" | wc -l)
        info "Archivos .pyc eliminados: $((PYC_COUNT - PYC_AFTER))"
    fi
fi

# 2. Limpiar __pycache__ del proyecto (no del venv)
log "🔍 Buscando __pycache__ en el proyecto..."
PROJECT_CACHE=$(find "$PROJECT_ROOT" -maxdepth 2 -type d -name "__pycache__" ! -path "*/venv/*" | wc -l)

if [ "$PROJECT_CACHE" -gt 0 ]; then
    log "🗑️ Eliminando __pycache__ del proyecto (excluyendo venv)..."
    find "$PROJECT_ROOT" -maxdepth 2 -type d -name "__pycache__" ! -path "*/venv/*" -exec rm -rf {} + 2>/dev/null || true
    info "Directorios __pycache__ del proyecto eliminados: $PROJECT_CACHE"
fi

# 3. Limpiar logs antiguos (mantener solo últimos 7 días)
log "🔍 Revisando logs antiguos..."
if [ -d "$DAEMON_DIR/logs" ]; then
    find "$DAEMON_DIR/logs" -name "*.log" -mtime +7 -delete 2>/dev/null || true
    info "Logs antiguos (más de 7 días) eliminados"
fi

# 4. Limpiar archivos de sesión temporales de PHP
log "🔍 Limpiando archivos de sesión PHP..."
if [ -d "/var/lib/php/sessions" ]; then
    SESSIONS_OLD=$(find /var/lib/php/sessions -name "sess_*" -mtime +1 | wc -l)
    if [ "$SESSIONS_OLD" -gt 0 ]; then
        find /var/lib/php/sessions -name "sess_*" -mtime +1 -delete 2>/dev/null || true
        info "Sesiones PHP antiguas eliminadas: $SESSIONS_OLD"
    fi
fi

# 5. Limpiar cache del sistema si es posible
log "🔍 Limpiando cache del sistema..."
if command -v apt-get >/dev/null 2>&1; then
    apt-get clean 2>/dev/null || true
    info "Cache de apt limpiada"
fi

# 6. Verificar si hay archivos de video temporales o corruptos
if [ -d "$PROJECT_ROOT/videos" ]; then
    log "🔍 Revisando archivos de video..."
    # Buscar archivos de video vacíos o muy pequeños (menos de 1KB)
    EMPTY_VIDEOS=$(find "$PROJECT_ROOT/videos" -name "*.mp4" -size -1k 2>/dev/null | wc -l)
    if [ "$EMPTY_VIDEOS" -gt 0 ]; then
        log "🗑️ Eliminando videos vacíos o corruptos: $EMPTY_VIDEOS"
        find "$PROJECT_ROOT/videos" -name "*.mp4" -size -1k -delete 2>/dev/null || true
    fi
fi

# 7. Espacio después de la limpieza
log "📊 Espacio después de la limpieza:"
df -h | grep -E "(/$|/var)"

# 8. Resumen
VENV_SIZE=$(du -sh "$DAEMON_DIR/venv" 2>/dev/null | cut -f1)
PROJECT_SIZE=$(du -sh "$PROJECT_ROOT" 2>/dev/null | cut -f1)

log "✅ Limpieza completada!"
info "Tamaño del entorno virtual: $VENV_SIZE"
info "Tamaño total del proyecto: $PROJECT_SIZE"

# 9. Recomendaciones
echo ""
log "💡 Recomendaciones adicionales:"
echo "  - Para limpiar cache del navegador: borra manualmente la cache de tu navegador"
echo "  - Para liberar más espacio: considera mover videos antiguos a un backup"
echo "  - Ejecuta este script mensualmente para mantenimiento"

log "🏁 Limpieza finalizada"
