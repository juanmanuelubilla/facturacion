#!/bin/bash

# Script de instalación de dependencias para WARP POS
# Reconocimiento facial con OpenCV y sistema completo

echo "🚀 Instalando dependencias para WARP POS con Reconocimiento Facial..."
echo "=================================================================="

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Función para verificar si un comando existe
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Función para imprimir mensajes
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Verificar si se ejecuta como root
if [[ $EUID -eq 0 ]]; then
    print_error "No ejecutar como root. Ejecutar como usuario normal con sudo cuando sea necesario."
    exit 1
fi

print_status "Verificando sistema operativo..."

# Detectar distribución
if command_exists apt-get; then
    DISTRO="debian"
    print_success "Sistema Debian/Ubuntu detectado"
elif command_exists yum; then
    DISTRO="rhel"
    print_success "Sistema RHEL/CentOS detectado"
elif command_exists pacman; then
    DISTRO="arch"
    print_success "Sistema Arch Linux detectado"
else
    print_error "Sistema operativo no soportado"
    exit 1
fi

# Actualizar sistema
print_status "Actualizando paquetes del sistema..."
if [ "$DISTRO" = "debian" ]; then
    sudo apt update && sudo apt upgrade -y
elif [ "$DISTRO" = "rhel" ]; then
    sudo yum update -y
elif [ "$DISTRO" = "arch" ]; then
    sudo pacman -Syu --noconfirm
fi

# Instalar dependencias básicas
print_status "Instalando dependencias básicas..."

if [ "$DISTRO" = "debian" ]; then
    sudo apt install -y \
        apache2 \
        mysql-server \
        php8.4 \
        php8.4-cli \
        php8.4-fpm \
        php8.4-mysql \
        php8.4-curl \
        php8.4-gd \
        php8.4-json \
        php8.4-mbstring \
        php8.4-xml \
        php8.4-zip \
        php8.4-opcache \
        php8.4-readline \
        unzip \
        git \
        curl \
        wget \
        htop \
        vim \
        nano

elif [ "$DISTRO" = "rhel" ]; then
    sudo yum install -y \
        httpd \
        mariadb-server \
        php \
        php-cli \
        php-fpm \
        php-mysql \
        php-curl \
        php-gd \
        php-json \
        php-mbstring \
        php-xml \
        php-zip \
        php-opcache \
        unzip \
        git \
        curl \
        wget \
        htop \
        vim \
        nano

elif [ "$DISTRO" = "arch" ]; then
    sudo pacman -S --noconfirm \
        apache \
        mysql \
        php \
        php-cli \
        php-fpm \
        php-mysql \
        php-curl \
        php-gd \
        php-json \
        php-mbstring \
        php-xml \
        php-zip \
        php-opcache \
        unzip \
        git \
        curl \
        wget \
        htop \
        vim \
        nano
fi

# Instalar Python y OpenCV
print_status "Instalando Python y OpenCV para reconocimiento facial..."

if [ "$DISTRO" = "debian" ]; then
    sudo apt install -y \
        python3 \
        python3-pip \
        python3-opencv \
        python3-numpy \
        python3-pil \
        python3-requests

elif [ "$DISTRO" = "rhel" ]; then
    sudo yum install -y \
        python3 \
        python3-pip \
        opencv-python \
        numpy \
        pillow \
        requests

elif [ "$DISTRO" = "arch" ]; then
    sudo pacman -S --noconfirm \
        python \
        python-pip \
        opencv \
        python-numpy \
        python-pillow \
        python-requests
fi

# Instalar dependencias de Python adicionales
print_status "Instalando librerías Python adicionales..."
pip3 install --user \
    opencv-python \
    numpy \
    pillow \
    requests \
    flask \
    redis \
    scikit-image \
    matplotlib \
    pandas

# Instalar dependencias adicionales del sistema
print_status "Instalando dependencias adicionales del sistema..."

if [ "$DISTRO" = "debian" ]; then
    sudo apt install -y \
        redis-server \
        imagemagick \
        ghostscript \
        ffmpeg \
        v4l-utils \
        libv4l-dev \
        python3-dev \
        build-essential \
        cmake \
        pkg-config \
        libjpeg-dev \
        libtiff5-dev \
        libpng-dev \
        libavcodec-dev \
        libavformat-dev \
        libswscale-dev \
        libv4l-dev \
        libxvidcore-dev \
        libx264-dev \
        libgtk-3-dev \
        libatlas-base-dev \
        gfortran

elif [ "$DISTRO" = "rhel" ]; then
    sudo yum install -y \
        redis \
        ImageMagick \
        ghostscript \
        ffmpeg \
        v4l-utils \
        python3-devel \
        gcc \
        gcc-c++ \
        cmake \
        pkgconfig \
        libjpeg-turbo-devel \
        libtiff-devel \
        libpng-devel \
        libavcodec-devel \
        libavformat-devel \
        libswscale-devel \
        libv4l-devel \
        libxvidcore-devel \
        libx264-devel \
        gtk3-devel \
        atlas-devel \
        gfortran

elif [ "$DISTRO" = "arch" ]; then
    sudo pacman -S --noconfirm \
        redis \
        imagemagick \
        ghostscript \
        ffmpeg \
        v4l-utils \
        python \
        gcc \
        cmake \
        pkgconf \
        libjpeg-turbo \
        libtiff \
        libpng \
        ffmpeg \
        gtk3 \
        lapack \
        gfortran
fi

# Instalar extensión Redis para PHP
print_status "Instalando extensión Redis para PHP..."
if [ "$DISTRO" = "debian" ]; then
    sudo apt install -y php8.4-redis
elif [ "$DISTRO" = "rhel" ]; then
    sudo yum install -y php-redis
elif [ "$DISTRO" = "arch" ]; then
    sudo pacman -S --noconfirm php-redis
fi

# Instalar extensión Imagick para PHP (opcional)
print_status "Instalando extensión Imagick para PHP..."
if [ "$DISTRO" = "debian" ]; then
    sudo apt install -y php8.4-imagick imagick
elif [ "$DISTRO" = "rhel" ]; then
    sudo yum install -y php-pecl-imagick ImageMagick-devel
elif [ "$DISTRO" = "arch" ]; then
    sudo pacman -S --noconfirm php-imagick imagemagick
fi

# Configurar servicios
print_status "Configurando servicios..."

if [ "$DISTRO" = "debian" ]; then
    # Apache
    sudo systemctl enable apache2
    sudo systemctl start apache2
    
    # MySQL
    sudo systemctl enable mysql
    sudo systemctl start mysql
    
    # PHP-FPM
    sudo systemctl enable php8.4-fpm
    sudo systemctl start php8.4-fpm
    
    # Redis
    sudo systemctl enable redis-server
    sudo systemctl start redis-server

elif [ "$DISTRO" = "rhel" ]; then
    # Apache
    sudo systemctl enable httpd
    sudo systemctl start httpd
    
    # MariaDB
    sudo systemctl enable mariadb
    sudo systemctl start mariadb
    
    # PHP-FPM
    sudo systemctl enable php-fpm
    sudo systemctl start php-fpm
    
    # Redis
    sudo systemctl enable redis
    sudo systemctl start redis

elif [ "$DISTRO" = "arch" ]; then
    # Apache
    sudo systemctl enable httpd
    sudo systemctl start httpd
    
    # MySQL
    sudo systemctl enable mysqld
    sudo systemctl start mysqld
    
    # PHP-FPM
    sudo systemctl enable php-fpm
    sudo systemctl start php-fpm
    
    # Redis
    sudo systemctl enable redis
    sudo systemctl start redis
fi

# Configurar PHP para OpenCV
print_status "Configurando PHP para reconocimiento facial..."

# Verificar configuración de PHP
PHP_INI="/etc/php/8.4/cli/php.ini"
if [ ! -f "$PHP_INI" ]; then
    PHP_INI="/etc/php/cli/php.ini"
fi

if [ -f "$PHP_INI" ]; then
    print_status "Configurando php.ini..."
    
    # Aumentar límites de memoria y ejecución
    sudo sed -i 's/memory_limit = .*/memory_limit = 512M/' "$PHP_INI"
    sudo sed -i 's/max_execution_time = .*/max_execution_time = 300/' "$PHP_INI"
    sudo sed -i 's/upload_max_filesize = .*/upload_max_filesize = 100M/' "$PHP_INI"
    sudo sed -i 's/post_max_size = .*/post_max_size = 100M/' "$PHP_INI"
    
    # Habilitar funciones necesarias
    sudo sed -i 's/disable_functions = .*/disable_functions = /' "$PHP_INI"
    
    print_success "PHP configurado correctamente"
fi

# Configurar Apache
print_status "Configurando Apache para PHP..."

APACHE_CONF="/etc/apache2/sites-available/000-default.conf"
if [ -f "$APACHE_CONF" ]; then
    # Habilitar mod_rewrite
    sudo a2enmod rewrite
    
    # Configurar AllowOverride
    sudo sed -i 's/AllowOverride None/AllowOverride All/' "$APACHE_CONF"
    
    # Reiniciar Apache
    sudo systemctl restart apache2
    
    print_success "Apache configurado correctamente"
fi

# Verificar instalación de OpenCV
print_status "Verificando instalación de OpenCV..."

if python3 -c "import cv2; print('OpenCV version:', cv2.__version__)" 2>/dev/null; then
    OPENCV_VERSION=$(python3 -c "import cv2; print(cv2.__version__)" 2>/dev/null)
    print_success "OpenCV $OPENCV_VERSION instalado correctamente"
else
    print_error "OpenCV no está instalado correctamente"
fi

# Verificar Haar Cascade
print_status "Verificando archivos Haar Cascade..."

HAAR_FILE="/usr/share/opencv4/haarcascades/haarcascade_frontalface_default.xml"
if [ -f "$HAAR_FILE" ]; then
    print_success "Haar Cascade encontrado en $HAAR_FILE"
else
    print_warning "Haar Cascade no encontrado en la ruta estándar"
    
    # Buscar en otras ubicaciones
    HAAR_LOCATIONS=(
        "/usr/share/opencv/haarcascades/haarcascade_frontalface_default.xml"
        "/usr/local/share/opencv4/haarcascades/haarcascade_frontalface_default.xml"
        "/usr/share/opencv/haarcascades/haarcascade_frontalface_default.xml"
    )
    
    for location in "${HAAR_LOCATIONS[@]}"; do
        if [ -f "$location" ]; then
            print_success "Haar Cascade encontrado en $location"
            break
        fi
    done
fi

# Crear directorios necesarios
print_status "Creando directorios para la aplicación..."

DIRECTORIES=(
    "/var/www/facturacion/uploads"
    "/var/www/facturacion/uploads/avatars"
    "/var/www/facturacion/uploads/products"
    "/var/www/facturacion/logs"
    "/var/www/facturacion/models"
    "/var/www/facturacion/temp"
)

for dir in "${DIRECTORIES[@]}"; do
    if [ ! -d "$dir" ]; then
        sudo mkdir -p "$dir"
        sudo chown -R $USER:$USER "$dir"
        sudo chmod 755 "$dir"
        print_success "Directorio creado: $dir"
    fi
done

# Configurar permisos
print_status "Configurando permisos..."
sudo chown -R $USER:$USER /var/www/facturacion
sudo chmod -R 755 /var/www/facturacion

# Verificar servicios
print_status "Verificando estado de los servicios..."

services=("apache2" "mysql" "php8.4-fpm" "redis-server")
for service in "${services[@]}"; do
    if systemctl is-active --quiet "$service"; then
        print_success "$service está activo"
    else
        print_error "$service no está activo"
    fi
done

# Crear script de verificación
print_status "Creando script de verificación..."
cat > /var/www/facturacion/verify_installation.sh << 'EOF'
#!/bin/bash

echo "🔍 Verificando instalación de WARP POS..."
echo "========================================"

# Verificar PHP
if command -v php >/dev/null 2>&1; then
    PHP_VERSION=$(php --version | head -n 1)
    echo "✅ PHP: $PHP_VERSION"
else
    echo "❌ PHP no encontrado"
fi

# Verificar Python
if command -v python3 >/dev/null 2>&1; then
    PYTHON_VERSION=$(python3 --version)
    echo "✅ Python: $PYTHON_VERSION"
else
    echo "❌ Python no encontrado"
fi

# Verificar OpenCV
if python3 -c "import cv2" 2>/dev/null; then
    OPENCV_VERSION=$(python3 -c "import cv2; print(cv2.__version__)" 2>/dev/null)
    echo "✅ OpenCV: $OPENCV_VERSION"
else
    echo "❌ OpenCV no encontrado"
fi

# Verificar Haar Cascade
if [ -f "/usr/share/opencv4/haarcascades/haarcascade_frontalface_default.xml" ]; then
    echo "✅ Haar Cascade encontrado"
else
    echo "❌ Haar Cascade no encontrado"
fi

# Verificar servicios
echo ""
echo "📋 Estado de servicios:"
systemctl is-active apache2 2>/dev/null && echo "✅ Apache2 activo" || echo "❌ Apache2 inactivo"
systemctl is-active mysql 2>/dev/null && echo "✅ MySQL activo" || echo "❌ MySQL inactivo"
systemctl is-active php8.4-fpm 2>/dev/null && echo "✅ PHP-FPM activo" || echo "❌ PHP-FPM inactivo"

echo ""
echo "🎯 Para completar la instalación:"
echo "1. Configurar MySQL: sudo mysql_secure_installation"
echo "2. Crear base de datos: mysql -u root -p"
echo "3. Ejecutar setup.php desde el navegador"
echo "4. Verificar reconocimiento facial con el script de prueba"
EOF

chmod +x /var/www/facturacion/verify_installation.sh

# Resumen final
echo ""
echo "=================================================================="
print_success "¡Instalación completada!"
echo ""
echo "📋 Resumen de lo instalado:"
echo "  • Servidor web: Apache2"
echo "  • Base de datos: MySQL/MariaDB"
echo "  • PHP 8.4 con extensiones necesarias"
echo "  • Python 3 con OpenCV para reconocimiento facial"
echo "  • Librerías adicionales para el sistema"
echo ""
echo "🔍 Para verificar la instalación:"
echo "  ./verify_installation.sh"
echo ""
echo "🌐 Para configurar la base de datos:"
echo "  sudo mysql_secure_installation"
echo ""
echo "🚀 Para iniciar el setup:"
echo "  Accede a http://localhost/setup.php"
echo ""
print_success "¡Sistema listo para usar WARP POS con reconocimiento facial!"
echo "=================================================================="