#!/bin/bash

#================================================================================
#                    SCRIPT DE INSTALACIÓN DLNA PARA NEXUS
#================================================================================
# Este script instala y configura automáticamente todo lo necesario para DLNA
#================================================================================

echo "🎯 INICIANDO INSTALACIÓN AUTOMÁTICA DE DLNA PARA NEXUS"
echo "================================================================"

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Función para imprimir con colores
print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_header() {
    echo -e "${BLUE}[SETUP]${NC} $1"
}

# Verificar si se ejecuta como root
if [ "$EUID" -eq 0 ]; then
    print_error "No ejecute este script como root. Ejecute como usuario normal."
    exit 1
fi

# Función para verificar si un comando existe
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Función para verificar la distribución
detect_distro() {
    if [ -f /etc/debian_version ]; then
        echo "debian"
    elif [ -f /etc/lsb-release ]; then
        echo "ubuntu"
    elif [ -f /etc/fedora-release ]; then
        echo "fedora"
    elif [ -f /etc/arch-release ]; then
        echo "arch"
    else
        echo "unknown"
    fi
}

# Función para instalar en Debian/Ubuntu
install_debian() {
    print_header "Detectado sistema Debian/Ubuntu"
    
    print_status "Actualizando paquetes del sistema..."
    sudo apt update
    
    print_status "Instalando paquetes DLNA..."
    sudo apt install -y minidlna ffmpeg imagemagick
    
    if [ $? -eq 0 ]; then
        print_status "✅ Paquetes instalados correctamente"
    else
        print_error "❌ Error al instalar paquetes"
        exit 1
    fi
}

# Función para instalar en Fedora
install_fedora() {
    print_header "Detectado sistema Fedora"
    
    print_status "Actualizando paquetes del sistema..."
    sudo dnf update -y
    
    print_status "Instalando paquetes DLNA..."
    sudo dnf install -y minidlna ffmpeg ImageMagick
    
    if [ $? -eq 0 ]; then
        print_status "✅ Paquetes instalados correctamente"
    else
        print_error "❌ Error al instalar paquetes"
        exit 1
    fi
}

# Función para instalar en Arch
install_arch() {
    print_header "Detectado sistema Arch Linux"
    
    print_status "Actualizando paquetes del sistema..."
    sudo pacman -Syu --noconfirm
    
    print_status "Instalando paquetes DLNA..."
    sudo pacman -S --noconfirm minidlna ffmpeg imagemagick
    
    if [ $? -eq 0 ]; then
        print_status "✅ Paquetes instalados correctamente"
    else
        print_error "❌ Error al instalar paquetes"
        exit 1
    fi
}

# Función para instalar paquetes Python
install_python_packages() {
    print_header "Instalando paquetes Python para NEXUS..."
    
    # Verificar si pip está instalado
    if ! command_exists pip3 && ! command_exists pip; then
        print_status "Instalando pip..."
        if [ "$(detect_distro)" = "debian" ]; then
            sudo apt install -y python3-pip python3-dev
        elif [ "$(detect_distro)" = "fedora" ]; then
            sudo dnf install -y python3-pip python3-devel
        elif [ "$(detect_distro)" = "arch" ]; then
            sudo pacman -S --noconfirm python-pip
        fi
    fi
    
    # Instalar paquetes Python
    pip_cmd="pip3"
    if ! command_exists pip3; then
        pip_cmd="pip"
    fi
    
    print_status "Instalando dependencias principales de NEXUS..."
    
    # Paquetes esenciales para la aplicación
    $pip_cmd install --user \
        Pillow \
        requests \
        mysql-connector-python \
        dlna \
        flask \
        webbrowser \
        tk \
        python-dateutil \
        numpy \
        matplotlib \
        reportlab \
        openpyxl \
        pandas \
        beautifulsoup4 \
        PyQt5 \
        cryptography \
        pillow-heif \
        imageio \
        scikit-image
    
    if [ $? -eq 0 ]; then
        print_status "✅ Paquetes Python de NEXUS instalados correctamente"
    else
        print_warning "⚠️ Algunos paquetes Python no se pudieron instalar (opcional)"
    fi
    
    # Instalar paquetes específicos para imágenes y DLNA
    print_status "Instalando paquetes adicionales para imágenes y DLNA..."
    $pip_cmd install --user \
        dlna \
        flask \
        pillow \
        imageio \
        scikit-image
    
    if [ $? -eq 0 ]; then
        print_status "✅ Paquetes de imágenes y DLNA instalados correctamente"
    fi
    
    # Verificar instalación de tkinter (generalmente viene con Python)
    print_status "Verificando tkinter..."
    python3 -c "import tkinter" 2>/dev/null
    if [ $? -eq 0 ]; then
        print_status "✅ tkinter está disponible"
    else
        print_warning "⚠️ tkinter no está disponible - instalando..."
        if [ "$(detect_distro)" = "debian" ]; then
            sudo apt install -y python3-tk
        elif [ "$(detect_distro)" = "fedora" ]; then
            sudo dnf install -y python3-tkinter
        elif [ "$(detect_distro)" = "arch" ]; then
            sudo pacman -S --noconfirm tk
        fi
    fi
}

# Función para configurar minidlna
configure_minidlna() {
    print_header "Configurando miniDLNA..."
    
    # Crear directorio para banners
    BANNERS_DIR="/var/lib/minidlna/banners"
    print_status "Creando directorio para banners: $BANNERS_DIR"
    sudo mkdir -p "$BANNERS_DIR"
    
    # Obtener nombre de usuario
    USER_NAME=$(whoami)
    print_status "Configurando permisos para usuario: $USER_NAME"
    sudo chown -R "$USER_NAME:$USER_NAME" "$BANNERS_DIR"
    
    # Hacer backup de configuración existente
    if [ -f /etc/minidlna.conf ]; then
        print_status "Haciendo backup de configuración existente..."
        sudo cp /etc/minidlna.conf /etc/minidlna.conf.backup
    fi
    
    # Crear configuración
    print_status "Creando configuración de miniDLNA..."
    sudo tee /etc/minidlna.conf > /dev/null <<EOF
# Configuración de miniDLNA para NEXUS Banners
# Generada automáticamente por install_dlna.sh

# Directorio de medios para banners
media_dir=$BANNERS_DIR

# Nombre del servidor (aparece en la TV)
friendly_name=NEXUS_Banners

# Interface de red (detectar automáticamente)
# network_interface=eth0

# Base de datos de medios
db_dir=/var/lib/minidlna

# Directorio de logs
log_dir=/var/log/minidlna

# Escanear cada 5 minutos
inotify=yes

# Tipos de archivo a indexar
# A=audio, V=video, P=pictures
media_dir=P,$BANNERS_DIR

# Puerto del servidor
port=8200

# Intervalo de escaneo (en segundos)
notify_interval=900

# Nivel de log (0=error, 1=warning, 2=info, 3=debug)
log_level=1
EOF
    
    if [ $? -eq 0 ]; then
        print_status "✅ Configuración creada correctamente"
    else
        print_error "❌ Error al crear configuración"
        exit 1
    fi
}

# Función para iniciar y habilitar el servicio
setup_service() {
    print_header "Configurando servicio miniDLNA..."
    
    # Recargar configuración de systemd
    sudo systemctl daemon-reload
    
    # Iniciar el servicio
    print_status "Iniciando servicio miniDLNA..."
    sudo systemctl start minidlna
    
    # Habilitar para que inicie con el sistema
    print_status "Habilitando servicio para inicio automático..."
    sudo systemctl enable minidlna
    
    # Esperar un momento
    sleep 2
    
    # Verificar estado
    print_status "Verificando estado del servicio..."
    if sudo systemctl is-active --quiet minidlna; then
        print_status "✅ Servicio miniDLNA está activo"
    else
        print_error "❌ Servicio miniDLNA no está activo"
        sudo systemctl status minidlna
        exit 1
    fi
}

# Función para crear directorio de banners local
create_local_banners_dir() {
    print_header "Creando directorio local de banners..."
    
    LOCAL_BANNERS_DIR="$HOME/imagenes_generadas"
    
    if [ ! -d "$LOCAL_BANNERS_DIR" ]; then
        mkdir -p "$LOCAL_BANNERS_DIR"
        print_status "✅ Directorio local creado: $LOCAL_BANNERS_DIR"
    else
        print_status "✅ Directorio local ya existe: $LOCAL_BANNERS_DIR"
    fi
    
    # Crear enlace simbólico al directorio de DLNA
    if [ ! -L "/var/lib/minidlna/banners/enlace_local" ]; then
        ln -sf "$LOCAL_BANNERS_DIR" "/var/lib/minidlna/banners/enlace_local"
        print_status "✅ Enlace simbólico creado para sincronización automática"
    fi
}

# Función para verificar instalación
verify_installation() {
    print_header "Verificando instalación..."
    
    # Verificar que minidlna esté instalado
    if command_exists minidlna; then
        print_status "✅ miniDLNA está instalado"
    else
        print_error "❌ miniDLNA no está instalado"
        return 1
    fi
    
    # Verificar que el servicio esté activo
    if sudo systemctl is-active --quiet minidlna; then
        print_status "✅ Servicio miniDLNA está activo"
    else
        print_error "❌ Servicio miniDLNA no está activo"
        return 1
    fi
    
    # Verificar directorio de banners
    if [ -d "/var/lib/minidlna/banners" ]; then
        print_status "✅ Directorio de banners existe"
    else
        print_error "❌ Directorio de banners no existe"
        return 1
    fi
    
    # Verificar configuración
    if [ -f /etc/minidlna.conf ]; then
        print_status "✅ Archivo de configuración existe"
    else
        print_error "❌ Archivo de configuración no existe"
        return 1
    fi
    
    # Mostrar información de red
    print_header "INFORMACIÓN DE RED:"
    IP_ADDR=$(hostname -I | awk '{print $1}')
    echo -e "${BLUE}[INFO]${NC} IP local: $IP_ADDR"
    echo -e "${BLUE}[INFO]${NC} Nombre del servidor DLNA: NEXUS_Banners"
    echo -e "${BLUE}[INFO]${NC} Puerto: 8200"
    echo -e "${BLUE}[INFO]${NC} Directorio de banners: /var/lib/minidlna/banners"
    
    return 0
}

# Función para mostrar instrucciones post-instalación
show_instructions() {
    print_header "INSTRUCCIONES POST-INSTALACIÓN:"
    echo ""
    echo -e "${GREEN}1. CONFIGURACIÓN EN TU TV:${NC}"
    echo "   - Ve a Apps > Media Server > DLNA"
    echo "   - Busca 'NEXUS_Banners'"
    echo "   - Selecciona y visualiza los banners"
    echo ""
    echo -e "${GREEN}2. AGREGAR BANNERS:${NC}"
    echo "   - Usa el módulo '🎨 Generador de Imágenes IA'"
    echo "   - Genera imágenes y envíalas a banners"
    echo "   - Las imágenes aparecerán automáticamente en la TV"
    echo ""
    echo -e "${GREEN}3. VERIFICAR LOGS:${NC}"
    echo "   sudo tail -f /var/log/minidlna.log"
    echo ""
    echo -e "${GREEN}4. REINICIAR SERVICIO:${NC}"
    echo "   sudo systemctl restart minidlna"
    echo ""
    echo -e "${GREEN}5. ESTADO DEL SERVICIO:${NC}"
    echo "   sudo systemctl status minidlna"
    echo ""
}

# Función principal
main() {
    echo "🔍 Detectando distribución de Linux..."
    DISTRO=$(detect_distro)
    
    case $DISTRO in
        "debian"|"ubuntu")
            install_debian
            ;;
        "fedora")
            install_fedora
            ;;
        "arch")
            install_arch
            ;;
        *)
            print_error "Distribución no soportada: $DISTRO"
            print_warning "Intentando instalación genérica..."
            install_debian
            ;;
    esac
    
    install_python_packages
    configure_minidlna
    setup_service
    create_local_banners_dir
    
    if verify_installation; then
        print_status "🎉 ¡INSTALACIÓN COMPLETADA CON ÉXITO!"
        echo ""
        show_instructions
    else
        print_error "❌ LA INSTALACIÓN FALLÓ"
        print_warning "Revisa los errores arriba y vuelve a intentarlo"
        exit 1
    fi
}

# Ejutar función principal
main

echo "================================================================"
echo "🎯 INSTALACIÓN DLNA PARA NEXUS COMPLETADA"
echo "================================================================"
