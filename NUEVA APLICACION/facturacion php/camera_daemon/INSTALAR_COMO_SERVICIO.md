# Instalar Camera Daemon como Servicio Systemd

## Guía Completa de Instalación

Esta guía te permitirá instalar el camera daemon como un servicio del sistema para que se inicie automáticamente al encender el equipo.

---

## 📋 Requisitos Previos

✅ **Daemon funcionando** - Verificado que el daemon funciona manualmente  
✅ **Entorno virtual** - Python venv creado en `/var/www/facturacion/camera_daemon/venv`  
✅ **Dependencias instaladas** - requirements.txt y opencv-python  
✅ **Permisos configurados** - Directorios creados con permisos adecuados  

---

## 🚀 Instalación Paso a Paso

### 1. Crear Archivo de Servicio

```bash
sudo nano /etc/systemd/system/camera-daemon.service
```

**Pegar el siguiente contenido:**

```ini
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
```

**Guardar con `Ctrl+O`, salir con `Ctrl+X`**

---

### 2. Configurar Permisos

```bash
# Dar permisos al script del daemon
sudo chmod +x /var/www/facturacion/camera_daemon/daemon_simple.py

# Asegurar permisos en directorios
sudo chown -R pi:pi /var/www/facturacion/camera_daemon
sudo chmod -R 755 /var/www/facturacion/camera_daemon
sudo chmod -R 777 /var/www/facturacion/videos
```

---

### 3. Habilitar e Iniciar el Servicio

```bash
# Recargar systemd
sudo systemctl daemon-reload

# Habilitar el servicio para que inicie con el sistema
sudo systemctl enable camera-daemon.service

# Iniciar el servicio ahora
sudo systemctl start camera-daemon.service
```

---

### 4. Verificar Estado del Servicio

```bash
# Ver estado del servicio
sudo systemctl status camera-daemon.service

# Ver logs en tiempo real
sudo journalctl -u camera-daemon -f

# Ver últimos logs
sudo journalctl -u camera-daemon --since "1 hour ago"
```

---

## 📊 Comandos de Gestión del Servicio

### Iniciar/Detener/Reiniciar
```bash
# Iniciar
sudo systemctl start camera-daemon

# Detener
sudo systemctl stop camera-daemon

# Reiniciar
sudo systemctl restart camera-daemon

# Recargar configuración
sudo systemctl reload camera-daemon
```

### Estado y Logs
```bash
# Estado completo
sudo systemctl status camera-daemon -l

# Logs detallados
sudo journalctl -u camera-daemon --no-pager

# Logs de errores
sudo journalctl -u camera-daemon -p err
```

### Configuración
```bash
# Deshabilitar inicio automático
sudo systemctl disable camera-daemon

# Habilitar inicio automático
sudo systemctl enable camera-daemon
```

---

## 🔧 Solución de Problemas

### Problema 1: Permiso denegado
```bash
# Verificar permisos
ls -la /var/www/facturacion/camera_daemon/

# Corregir permisos
sudo chown -R pi:pi /var/www/facturacion/camera_daemon
sudo chmod +x /var/www/facturacion/camera_daemon/daemon_simple.py
```

### Problema 2: Error de base de datos
```bash
# Verificar conexión MySQL
mysql -u facturacion -pfacturacion -h localhost facturacion -e "SHOW TABLES;"

# Verificar logs del servicio
sudo journalctl -u camera-daemon -f
```

### Problema 3: Servicio no inicia
```bash
# Verificar configuración
sudo systemd-analyze verify camera-daemon.service

# Verificar entorno virtual
ls -la /var/www/facturacion/camera_daemon/venv/bin/python
```

### Problema 4: Daemon no encuentra cámaras
```bash
# Verificar tabla de cámaras
mysql -u facturacion -pfacturacion -h localhost facturacion -e "SELECT * FROM camaras WHERE activo = 1;"

# Verificar logs específicos
sudo journalctl -u camera-daemon | grep -i camera
```

---

## 📁 Estructura de Archivos del Servicio

```
/etc/systemd/system/camera-daemon.service    # Archivo de configuración
/var/www/facturacion/camera_daemon/          # Directorio del daemon
├── daemon_simple.py                          # Script principal
├── venv/                                     # Entorno virtual Python
├── logs/                                     # Logs del daemon
└── start_daemon_local.sh                     # Script manual
/var/www/facturacion/videos/                  # Grabaciones de cámaras
```

---

## 🎯 Verificación Final

### 1. Verificar que el servicio está activo
```bash
sudo systemctl status camera-daemon
```
**Esperado:** `active (running)`

### 2. Verificar que está grabando
```bash
# Ver logs del daemon
sudo journalctl -u camera-daemon -f

# Ver archivos de video
ls -la /var/www/facturacion/videos/
```

### 3. Verificar integración con dashboard
```bash
# Acceder al dashboard y verificar "Estado de Cámaras"
# URL: http://localhost/facturacion/dashboard.php
```

---

## 🔄 Actualizaciones y Mantenimiento

### Actualizar el daemon
```bash
# Detener servicio
sudo systemctl stop camera-daemon

# Actualizar archivos
# (copiar nuevos archivos a /var/www/facturacion/camera_daemon/)

# Reiniciar servicio
sudo systemctl start camera-daemon
```

### Actualizar dependencias
```bash
# Detener servicio
sudo systemctl stop camera-daemon

# Activar entorno virtual
cd /var/www/facturacion/camera_daemon
source venv/bin/activate

# Actualizar dependencias
pip install -r requirements.txt --upgrade

# Reiniciar servicio
sudo systemctl start camera-daemon
```

---

## 📞 Soporte y Monitoreo

### Monitoreo continuo
```bash
# Script para monitorear el estado
watch -n 30 'sudo systemctl status camera-daemon --no-pager'
```

### Alertas por email (opcional)
```bash
# Crear script de monitoreo
nano /home/pi/check_daemon.sh
```

---

## ✅ Checklist de Instalación Completa

- [ ] Servicio creado en `/etc/systemd/system/camera-daemon.service`
- [ ] Permisos configurados correctamente
- [ ] Servicio habilitado con `systemctl enable`
- [ ] Servicio iniciado con `systemctl start`
- [ ] Verificado estado: `active (running)`
- [ ] Logs funcionando correctamente
- [ ] Dashboard muestra estado de cámaras
- [ ] Grabaciones se guardan en `/var/www/facturacion/videos/`

---

**¡Listo! Tu camera daemon ahora funcionará como un servicio del sistema, iniciando automáticamente cada vez que enciendas el equipo.** 🎯

Para soporte adicional, revisa los logs con `sudo journalctl -u camera-daemon -f` o consulta esta guía.
