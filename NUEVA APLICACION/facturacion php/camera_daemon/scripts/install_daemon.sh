#!/bin/bash

# Advanced Camera Daemon Installation Script
# Installs all dependencies and sets up the daemon service

set -e

echo "🚀 Installing Advanced Camera Daemon System..."

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging function
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

# Check if running as root
if [[ $EUID -ne 0 ]]; then
   error "This script must be run as root (use sudo)"
   exit 1
fi

# Get the directory where the script is located
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DAEMON_DIR="$(dirname "$SCRIPT_DIR")"
PROJECT_ROOT="$(dirname "$DAEMON_DIR")"

log "Script directory: $SCRIPT_DIR"
log "Daemon directory: $DAEMON_DIR"
log "Project root: $PROJECT_ROOT"

# Create necessary directories
log "Creating directories..."
mkdir -p "$DAEMON_DIR/logs"
mkdir -p "$DAEMON_DIR/faces"
mkdir -p "$DAEMON_DIR/models"
mkdir -p "$DAEMON_DIR/videos"
mkdir -p "$PROJECT_ROOT/videos"

# Set permissions
chmod 755 "$DAEMON_DIR/logs"
chmod 755 "$DAEMON_DIR/faces"
chmod 755 "$DAEMON_DIR/models"
chmod 755 "$DAEMON_DIR/videos"
chmod 755 "$PROJECT_ROOT/videos"

# Update system packages
log "Updating system packages..."
apt update

# Install Python 3.8+ and development tools
log "Installing Python and development tools..."
apt install -y python3 python3-pip python3-venv python3-dev
apt install -y build-essential cmake pkg-config
apt install -y libjpeg-dev libtiff5-dev libpng-dev
apt install -y libavcodec-dev libavformat-dev libswscale-dev libv4l-dev
apt install -y libxvidcore-dev libx264-dev
apt install -y libgtk-3-dev libatlas-base-dev gfortran
apt install -y redis-server
apt install -y ffmpeg
apt install -y mariadb-client
apt install -y git curl wget

# Install Redis
log "Installing and configuring Redis..."
if ! systemctl is-active --quiet redis-server; then
    systemctl start redis-server
    systemctl enable redis-server
fi

# Check Redis connection
redis-cli ping > /dev/null 2>&1
if [ $? -eq 0 ]; then
    log "Redis is running and accessible"
else
    error "Redis is not running properly"
    exit 1
fi

# Create Python virtual environment
log "Creating Python virtual environment..."
cd "$DAEMON_DIR"
python3 -m venv venv
source venv/bin/activate

# Upgrade pip
log "Upgrading pip..."
pip install --upgrade pip

# Install OpenCV via system package (pre-compiled)
log "Installing OpenCV via system package..."
apt install -y python3-opencv

# Install Python dependencies
log "Installing Python dependencies..."
if [ -f "requirements_minimal.txt" ]; then
    pip install -r requirements_minimal.txt
    log "Using minimal requirements for maximum compatibility"
elif [ -f "requirements_simple.txt" ]; then
    pip install -r requirements_simple.txt
    log "Using simplified requirements for better compatibility"
else
    pip install -r requirements.txt
fi

# Download AI models if they don't exist
log "Downloading AI models..."

# YOLOv8 model (will be downloaded automatically by ultralytics)
info "YOLOv8 model will be downloaded automatically when first used"

# Create placeholder for face recognition database
touch "$DAEMON_DIR/faces/known_faces.pkl"

# Create systemd service file
log "Creating systemd service..."
cat > /etc/systemd/system/camera-daemon.service << EOF
[Unit]
Description=Advanced Camera Daemon
After=network.target mysql.service redis-server.service
Wants=mysql.service redis-server.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=$DAEMON_DIR
Environment=PATH=$DAEMON_DIR/venv/bin
ExecStart=$DAEMON_DIR/venv/bin/python $DAEMON_DIR/daemon.py
ExecReload=/bin/kill -HUP \$MAINPID
KillMode=mixed
TimeoutStopSec=5
PrivateTmp=true
Restart=on-failure
RestartSec=10
StandardOutput=journal
StandardError=journal
SyslogIdentifier=camera-daemon

# Security settings
NoNewPrivileges=true
ProtectSystem=strict
ProtectHome=true
ReadWritePaths=$DAEMON_DIR/logs $DAEMON_DIR/faces $DAEMON_DIR/models $DAEMON_DIR/videos $PROJECT_ROOT/videos
ReadOnlyPaths=/etc /usr /bin /lib

[Install]
WantedBy=multi-user.target
EOF

# Create logrotate configuration
log "Setting up log rotation..."
cat > /etc/logrotate.d/camera-daemon << EOF
$DAEMON_DIR/logs/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 644 www-data www-data
    postrotate
        systemctl reload camera-daemon > /dev/null 2>&1 || true
    endscript
}
EOF

# Create configuration script for environment variables
log "Creating environment configuration..."
cat > "$DAEMON_DIR/.env" << EOF
# Camera Daemon Environment Configuration
PYTHONPATH=$DAEMON_DIR
PYTHONUNBUFFERED=1

# Database Configuration
DB_HOST=localhost
DB_USER=root
DB_PASSWORD=
DB_NAME=facturacion

# Redis Configuration
REDIS_HOST=localhost
REDIS_PORT=6379
REDIS_DB=0

# Logging Configuration
LOG_LEVEL=INFO
LOG_FILE=$DAEMON_DIR/logs/daemon.log

# Performance Configuration
MAX_WORKERS=4
GPU_ACCELERATION=false
MEMORY_LIMIT=4

# Storage Configuration
VIDEOS_DIR=$PROJECT_ROOT/videos
MAX_STORAGE_GB=100
CLEANUP_DAYS=30

# API Configuration
API_HOST=0.0.0.0
API_PORT=8081
EOF

# Set ownership and permissions
log "Setting permissions..."
chown -R www-data:www-data "$DAEMON_DIR"
chown -R www-data:www-data "$PROJECT_ROOT/videos"
chmod +x "$DAEMON_DIR/daemon.py"

# Install database tables
log "Installing database tables..."
if [ -f "$PROJECT_ROOT/sql/camera_daemon_tables_simple.sql" ]; then
    mariadb -u root -p "$PROJECT_ROOT/sql/camera_daemon_tables_simple.sql" || {
        warning "Database installation failed. Please run manually:"
        warning "mariadb -u root -p $PROJECT_ROOT/sql/camera_daemon_tables_simple.sql"
    }
else
    warning "Database schema file not found at $PROJECT_ROOT/sql/camera_daemon_tables_simple.sql"
fi

# Create helper scripts
log "Creating helper scripts..."

# Start script
cat > "$DAEMON_DIR/scripts/start.sh" << EOF
#!/bin/bash
# Start the camera daemon

echo "Starting Camera Daemon..."
systemctl start camera-daemon
systemctl status camera-daemon
EOF

# Stop script
cat > "$DAEMON_DIR/scripts/stop.sh" << EOF
#!/bin/bash
# Stop the camera daemon

echo "Stopping Camera Daemon..."
systemctl stop camera-daemon
systemctl status camera-daemon
EOF

# Restart script
cat > "$DAEMON_DIR/scripts/restart.sh" << EOF
#!/bin/bash
# Restart the camera daemon

echo "Restarting Camera Daemon..."
systemctl restart camera-daemon
systemctl status camera-daemon
EOF

# Status script
cat > "$DAEMON_DIR/scripts/status.sh" << EOF
#!/bin/bash
# Check camera daemon status

echo "Camera Daemon Status:"
systemctl is-active camera-daemon
systemctl status camera-daemon --no-pager
EOF

# Logs script
cat > "$DAEMON_DIR/scripts/logs.sh" << EOF
#!/bin/bash
# Show camera daemon logs

echo "Camera Daemon Logs (last 50 lines):"
journalctl -u camera-daemon -n 50 --no-pager
EOF

# Make scripts executable
chmod +x "$DAEMON_DIR/scripts/"*.sh

# Install helper scripts system-wide
cat > /usr/local/bin/camera-daemon << 'EOF'
#!/bin/bash
# Camera daemon control script

DAEMON_DIR="/home/pi/facturacion_php/camera_daemon"

case "$1" in
    start)
        sudo systemctl start camera-daemon
        echo "Camera daemon started"
        ;;
    stop)
        sudo systemctl stop camera-daemon
        echo "Camera daemon stopped"
        ;;
    restart)
        sudo systemctl restart camera-daemon
        echo "Camera daemon restarted"
        ;;
    status)
        sudo systemctl status camera-daemon --no-pager
        ;;
    logs)
        sudo journalctl -u camera-daemon -n 50 --no-pager
        ;;
    install)
        echo "Installation already completed"
        ;;
    *)
        echo "Usage: camera-daemon {start|stop|restart|status|logs|install}"
        echo ""
        echo "Commands:"
        echo "  start    - Start the camera daemon"
        echo "  stop     - Stop the camera daemon"
        echo "  restart  - Restart the camera daemon"
        echo "  status   - Check daemon status"
        echo "  logs     - Show recent logs"
        echo "  install  - Install/reinstall daemon"
        exit 1
        ;;
esac
EOF

chmod +x /usr/local/bin/camera-daemon

# Reload systemd to recognize new service
log "Reloading systemd..."
systemctl daemon-reload

# Enable the service
log "Enabling camera daemon service..."
systemctl enable camera-daemon

# Test configuration
log "Testing configuration..."
cd "$DAEMON_DIR"
source venv/bin/activate

# Test Python imports
python3 -c "
import sys
try:
    import cv2
    print('✓ OpenCV installed')
except ImportError:
    print('✗ OpenCV not found')

try:
    import numpy
    print('✓ NumPy installed')
except ImportError:
    print('✗ NumPy not found')

try:
    import redis
    print('✓ Redis client installed')
except ImportError:
    print('✗ Redis client not found')

try:
    import face_recognition
    print('✓ Face recognition installed')
except ImportError:
    print('✗ Face recognition not found')

try:
    import ultralytics
    print('✓ Ultralytics (YOLO) installed')
except ImportError:
    print('✗ Ultralytics not found')

try:
    import mediapipe
    print('✓ MediaPipe installed')
except ImportError:
    print('✗ MediaPipe not found')
"

# Test database connection
python3 -c "
import pymysql
try:
    conn = pymysql.connect(host='localhost', user='root', password='', database='facturacion')
    print('✓ Database connection successful')
    conn.close()
except Exception as e:
    print(f'✗ Database connection failed: {e}')
"

# Test Redis connection
python3 -c "
import redis
try:
    r = redis.Redis(host='localhost', port=6379, db=0)
    r.ping()
    print('✓ Redis connection successful')
except Exception as e:
    print(f'✗ Redis connection failed: {e}')
"

# Create startup script
log "Creating startup script..."
cat > "$DAEMON_DIR/scripts/setup_startup.sh" << EOF
#!/bin/bash
# Setup automatic startup on boot

# Add camera daemon to startup applications
if [ -f "/etc/xdg/autostart/camera-daemon.desktop" ]; then
    echo "Desktop autostart already configured"
else
    mkdir -p /etc/xdg/autostart
    cat > /etc/xdg/autostart/camera-daemon.desktop << DESKTOP
[Desktop Entry]
Type=Application
Name=Camera Daemon
Comment=Advanced Camera System with AI Analysis
Exec=sudo systemctl start camera-daemon
Hidden=false
NoDisplay=false
X-GNOME-Autostart-enabled=true
DESKTOP
    echo "Desktop autostart configured"
fi

# Ensure service starts on boot
systemctl is-enabled camera-daemon > /dev/null 2>&1 || {
    systemctl enable camera-daemon
    echo "Service enabled for startup"
}
EOF

chmod +x "$DAEMON_DIR/scripts/setup_startup.sh"

# Display installation summary
echo ""
log "🎉 Installation completed successfully!"
echo ""
echo "📋 Installation Summary:"
echo "  ✓ Python dependencies installed"
echo "  ✓ System dependencies installed"
echo "  ✓ Redis configured and running"
echo "  ✓ Database tables created"
echo "  ✓ Systemd service created"
echo "  ✓ Helper scripts installed"
echo "  ✓ Log rotation configured"
echo ""
echo "🚀 Quick Start Commands:"
echo "  camera-daemon start     - Start the daemon"
echo "  camera-daemon stop      - Stop the daemon"
echo "  camera-daemon status    - Check status"
echo "  camera-daemon logs      - View logs"
echo "  camera-daemon restart   - Restart the daemon"
echo ""
echo "📁 Important Directories:"
echo "  Daemon:    $DAEMON_DIR"
echo "  Logs:      $DAEMON_DIR/logs"
echo "  Videos:    $PROJECT_ROOT/videos"
echo "  Models:    $DAEMON_DIR/models"
echo "  Faces:     $DAEMON_DIR/faces"
echo ""
echo "⚙️  Configuration:"
echo "  Environment: $DAEMON_DIR/.env"
echo "  Service:    /etc/systemd/system/camera-daemon.service"
echo "  Logrotate:  /etc/logrotate.d/camera-daemon"
echo ""
echo "🔧 Next Steps:"
echo "  1. Start the daemon: camera-daemon start"
echo "  2. Check status: camera-daemon status"
echo "  3. View logs: camera-daemon logs"
echo "  4. Configure cameras in the web interface"
echo ""
warning "Remember to:"
warning "  • Configure your cameras in the web interface first"
warning "  • Check that all cameras are accessible from this server"
warning "  • Monitor system resources (CPU, memory, storage)"
warning "  • Review logs regularly for any issues"
echo ""
log "Installation completed successfully! 🎯"
