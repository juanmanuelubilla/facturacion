#!/bin/bash

# =============================================================================
# SCRIPT DE CONFIGURACIÓN AUTOMÁTICA DLNA REMOTO
# NEXUS POS - Sistema de Gestión Comercial
# =============================================================================

# Colores para salida
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Función para mostrar mensajes
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Función para verificar si un comando existe
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Función para verificar conexión
check_connection() {
    local host=$1
    local port=$2
    
    if command_exists nc; then
        nc -z -w3 "$host" "$port" 2>/dev/null
    elif command_exists telnet; then
        timeout 3 telnet "$host" "$port" </dev/null >/dev/null 2>&1
    else
        ping -c 1 -W 3 "$host" >/dev/null 2>&1
    fi
}

# Mostrar banner
echo "=================================================================="
echo "🔧 CONFIGURACIÓN AUTOMÁTICA DLNA REMOTO - NEXUS POS"
echo "=================================================================="
echo ""

# Verificar si se ejecuta como root
if [[ $EUID -ne 0 ]]; then
   log_error "Este script debe ejecutarse como root o con sudo"
   echo "Uso: sudo bash $0"
   exit 1
fi

# Detectar tipo de instalación
if [[ -f "/var/www/html/facturacion_php/config.php" ]]; then
    TIPO="WEB_SERVER"
    log_info "Detectado: Servidor Web (NEXUS POS)"
elif [[ -f "/etc/minidlna.conf" ]] || command_exists minidlna; then
    TIPO="DLNA_SERVER"
    log_info "Detectado: Servidor DLNA (MiniDLNA)"
else
    log_error "No se pudo detectar el tipo de servidor"
    echo "Este script debe ejecutarse en:"
    echo "  - Servidor web con NEXUS POS instalado"
    echo "  - Servidor DLNA con MiniDLNA"
    exit 1
fi

# Configuración por defecto
SERVIDOR_WEB=${SERVIDOR_WEB:-"192.168.31.102"}
SERVIDOR_DLNA=${SERVIDOR_DLNA:-"192.168.31.101"}
TIPO_COMPARTICION=${TIPO_COMPARTICION:-"nfs"}

# Función para configurar servidor web
configurar_servidor_web() {
    log_info "Configurando servidor web para compartir carpetas..."
    
    case $TIPO_COMPARTICION in
        "nfs")
            log_info "Instalando y configurando NFS..."
            
            # Actualizar paquetes
            apt update && apt install -y nfs-kernel-server
            
            # Crear archivo de exportación si no existe
            if ! grep -q "/var/www/html/facturacion_php/empresas" /etc/exports; then
                echo "/var/www/html/facturacion_php/empresas $SERVIDOR_DLNA(rw,sync,no_subtree_check)" >> /etc/exports
                log_success "Carpeta de empresas exportada via NFS"
            else
                log_warning "La carpeta ya está exportada"
            fi
            
            # Reiniciar y habilitar servicios
            systemctl restart nfs-kernel-server
            systemctl enable nfs-kernel-server
            exportfs -a
            
            log_success "NFS configurado correctamente"
            ;;
            
        "samba")
            log_info "Instalando y configurando Samba..."
            
            # Actualizar paquetes
            apt update && apt install -y samba
            
            # Hacer backup de configuración
            cp /etc/samba/smb.conf /etc/samba/smb.conf.backup
            
            # Agregar configuración si no existe
            if ! grep -q "\[empresas\]" /etc/samba/smb.conf; then
                cat >> /etc/samba/smb.conf << EOF

[empresas]
   path = /var/www/html/facturacion_php/empresas
   browseable = yes
   writable = yes
   guest ok = yes
   read only = no
   force user = www-data
   force group = www-data
EOF
                log_success "Carpeta de empresas compartida via Samba"
            else
                log_warning "La carpeta ya está compartida"
            fi
            
            # Reiniciar y habilitar servicios
            systemctl restart smbd nmbd
            systemctl enable smbd nmbd
            
            log_success "Samba configurado correctamente"
            ;;
    esac
    
    # Verificar permisos
    chown -R www-data:www-data /var/www/html/facturacion_php/empresas
    chmod -R 755 /var/www/html/facturacion_php/empresas
    
    log_success "Permisos configurados correctamente"
}

# Función para configurar servidor DLNA
configurar_servidor_dlna() {
    log_info "Configurando servidor DLNA..."
    
    case $TIPO_COMPARTICION in
        "nfs")
            log_info "Instalando cliente NFS y MiniDLNA..."
            
            # Actualizar paquetes
            apt update && apt install -y nfs-common minidlna
            
            # Crear punto de montaje
            mkdir -p /mnt/banners_empresa
            
            # Montar carpeta compartida
            if ! grep -q "/mnt/banners_empresa" /etc/fstab; then
                echo "$SERVIDOR_WEB:/var/www/html/facturacion_php/empresas /mnt/banners_empresa nfs defaults 0 0" >> /etc/fstab
                mount -a
                log_success "Carpeta montada via NFS"
            else
                log_warning "La carpeta ya está montada"
            fi
            ;;
            
        "samba")
            log_info "Instalando cliente CIFS y MiniDLNA..."
            
            # Actualizar paquetes
            apt update && apt install -y cifs-utils minidlna
            
            # Crear punto de montaje
            mkdir -p /mnt/banners_empresa
            
            # Montar carpeta compartida
            if ! grep -q "/mnt/banners_empresa" /etc/fstab; then
                echo "//$SERVIDOR_WEB/empresas /mnt/banners_empresa cifs guest 0 0" >> /etc/fstab
                mount -a
                log_success "Carpeta montada via Samba"
            else
                log_warning "La carpeta ya está montada"
            fi
            ;;
    esac
    
    # Configurar MiniDLNA
    log_info "Configurando MiniDLNA..."
    
    # Crear configuración
    cat > /tmp/minidlna.conf << EOF
port=8200
friendly_name=NEXUS POS Banners
media_dir=V,/mnt/banners_empresa/videos
media_dir=P,/mnt/banners_empresa/imagenes
media_dir=A,/mnt/banners_empresa/banners
db_dir=/var/cache/minidlna
log_dir=/var/log
inotify=yes
notify_interval=60
network_interface=eth0
listen_ip=$SERVIDOR_DLNA
EOF
    
    # Aplicar configuración
    mv /tmp/minidlna.conf /etc/minidlna.conf
    
    # Crear directorios de cache y logs
    mkdir -p /var/cache/minidlna
    mkdir -p /var/log/minidlna
    
    # Reiniciar y habilitar MiniDLNA
    systemctl restart minidlna
    systemctl enable minidlna
    
    log_success "MiniDLNA configurado correctamente"
}

# Función para verificar configuración
verificar_configuracion() {
    log_info "Verificando configuración..."
    
    case $TIPO in
        "WEB_SERVER")
            # Verificar servicios
            if systemctl is-active --quiet nfs-kernel-server 2>/dev/null; then
                log_success "NFS Server está activo"
            elif systemctl is-active --quiet smbd 2>/dev/null; then
                log_success "Samba está activo"
            else
                log_error "No se encontraron servicios de compartición activos"
            fi
            
            # Verificar exportaciones
            if [[ $TIPO_COMPARTICION == "nfs" ]]; then
                exportfs -v | grep "/var/www/html/facturacion_php/empresas" && log_success "Exportación NFS verificada"
            fi
            ;;
            
        "DLNA_SERVER")
            # Verificar montaje
            if mountpoint -q /mnt/banners_empresa; then
                log_success "Carpeta montada correctamente"
                log_info "Contenido de banners:"
                ls -la /mnt/banners_empresa/banners/ 2>/dev/null || log_warning "No se encontraron banners"
            else
                log_error "La carpeta no está montada"
            fi
            
            # Verificar MiniDLNA
            if systemctl is-active --quiet minidlna; then
                log_success "MiniDLNA está activo"
                log_info "Accede a: http://$SERVIDOR_DLNA:8200"
            else
                log_error "MiniDLNA no está activo"
            fi
            ;;
    esac
}

# Función para mostrar ayuda
mostrar_ayuda() {
    echo "Uso: $0 [OPCIONES]"
    echo ""
    echo "Opciones:"
    echo "  --server-web IP      IP del servidor DLNA (default: 192.168.31.101)"
    echo "  --server-dlna IP     IP del servidor web (default: 192.168.31.102)"
    echo "  --tipo nfs|samba     Tipo de compartición (default: nfs)"
    echo "  --help              Muestra esta ayuda"
    echo ""
    echo "Ejemplos:"
    echo "  $0                                          # Configuración automática"
    echo "  $0 --server-web 192.168.1.100              # Especificar IP DLNA"
    echo "  $0 --tipo samba                             # Usar Samba en lugar de NFS"
    echo ""
}

# Parsear argumentos
while [[ $# -gt 0 ]]; do
    case $1 in
        --server-web)
            SERVIDOR_WEB="$2"
            shift 2
            ;;
        --server-dlna)
            SERVIDOR_DLNA="$2"
            shift 2
            ;;
        --tipo)
            TIPO_COMPARTICION="$2"
            shift 2
            ;;
        --help)
            mostrar_ayuda
            exit 0
            ;;
        *)
            log_error "Opción desconocida: $1"
            mostrar_ayuda
            exit 1
            ;;
    esac
done

# Mostrar configuración
echo "Configuración detectada:"
echo "  Tipo de servidor: $TIPO"
echo "  Servidor Web: $SERVIDOR_WEB"
echo "  Servidor DLNA: $SERVIDOR_DLNA"
echo "  Tipo de compartición: $TIPO_COMPARTICION"
echo ""

# Confirmar configuración
read -p "¿Continuar con esta configuración? (y/N): " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    log_info "Configuración cancelada"
    exit 0
fi

# Ejecutar configuración según tipo de servidor
case $TIPO in
    "WEB_SERVER")
        configurar_servidor_web
        ;;
    "DLNA_SERVER")
        configurar_servidor_dlna
        ;;
esac

# Verificar configuración
verificar_configuracion

echo ""
echo "=================================================================="
log_success "¡Configuración completada!"
echo "=================================================================="

if [[ $TIPO == "WEB_SERVER" ]]; then
    echo ""
    echo "Próximos pasos:"
    echo "1. Ejecuta este script en el servidor DLNA ($SERVIDOR_DLNA)"
    echo "2. Verifica que MiniDLNA esté activo"
    echo "3. Accede a http://$SERVIDOR_DLNA:8200"
else
    echo ""
    echo "Próximos pasos:"
    echo "1. Verifica que los banners aparezcan en http://$SERVIDOR_DLNA:8200"
    echo "2. Configura tu TV/dispositivo DLNA para conectarse"
    echo "3. Los banners deberían rotar automáticamente"
fi

echo ""
echo "Si necesitas ayuda, ejecuta: $0 --help"
