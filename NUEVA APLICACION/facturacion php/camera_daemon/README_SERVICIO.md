# Camera Daemon como Servicio - Guía Rápida

## 📋 Opciones de Instalación

### Opción 1: Instalación Automática (Recomendada)
```bash
cd /var/www/facturacion/camera_daemon
sudo ./install_service.sh
```

### Opción 2: Instalación Manual
Sigue la guía completa en `INSTALAR_COMO_SERVICIO.md`

---

## 🚀 Comandos Básicos

### Verificar Estado
```bash
sudo systemctl status camera-daemon
```

### Ver Logs en Tiempo Real
```bash
sudo journalctl -u camera-daemon -f
```

### Reiniciar Servicio
```bash
sudo systemctl restart camera-daemon
```

### Detener/Iniciar
```bash
sudo systemctl stop camera-daemon
sudo systemctl start camera-daemon
```

---

## ✅ Verificación

El servicio está funcionando correctamente si:
- ✅ `systemctl status camera-daemon` muestra `active (running)`
- ✅ Dashboard muestra estado de cámaras
- ✅ Logs muestran "Daemon iniciado con X cámaras"
- ✅ Se crean archivos de video en `/var/www/facturacion/videos/`

---

## 🔧 Solución Rápida de Problemas

### Si el servicio no inicia:
```bash
# Ver logs de error
sudo journalctl -u camera-daemon -p err

# Verificar permisos
ls -la /var/www/facturacion/camera_daemon/

# Probar manualmente
cd /var/www/facturacion/camera_daemon
source venv/bin/activate
python3 daemon_simple.py
```

### Si las cámaras no aparecen:
```bash
# Verificar base de datos
mysql -u facturacion -pfacturacion -h localhost facturacion -e "SELECT * FROM camaras WHERE activo = 1;"
```

---

## 📁 Archivos Importantes

- `/etc/systemd/system/camera-daemon.service` - Configuración del servicio
- `/var/www/facturacion/camera_daemon/daemon_simple.py` - Script del daemon
- `/var/www/facturacion/camera_daemon/logs/daemon.log` - Logs del daemon
- `/var/www/facturacion/videos/` - Grabaciones de cámaras

---

## 🎯 Resultado Final

Una vez instalado, el daemon:
- ✅ Se inicia automáticamente al encender el equipo
- ✅ Se reinicia si hay errores
- ✅ Registra eventos en la base de datos
- ✅ Integra con el dashboard web

**¡Listo para producción!** 🚀
