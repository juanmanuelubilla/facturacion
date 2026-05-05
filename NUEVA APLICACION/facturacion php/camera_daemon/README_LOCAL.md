# Camera Daemon - Configuración Local

## Estado Actual
✅ **Daemon funcionando correctamente**
✅ **Base de datos conectada**
✅ **Dependencias instaladas**
✅ **Cámaras cargadas desde BD**

## Problemas Resueltos
- ❌ Credenciales de BD incorrectas → ✅ Actualizadas a `facturacion/facturacion`
- ❌ Dependencias faltantes → ✅ Entorno virtual con requirements_minimal.txt
- ❌ Rutas incorrectas → ✅ Configuradas para entorno local `/var/www/facturacion`
- ❌ Permisos de directorios → ✅ Directorios creados con permisos adecuados
- ❌ Nombres de columnas incorrectos → ✅ Actualizados a estructura real de BD

## Estructura de Directorios
```
/var/www/facturacion/
├── camera_daemon/
│   ├── venv/              # Entorno virtual Python
│   ├── logs/              # Logs del daemon
│   ├── daemon_simple.py   # Daemon simplificado
│   ├── config.py          # Configuración actualizada
│   └── start_daemon_local.sh  # Script de inicio
├── videos/                # Grabaciones de cámaras
└── config.php            # Configuración principal
```

## Uso

### Iniciar Daemon
```bash
cd /var/www/facturacion/camera_daemon
./start_daemon_local.sh
```

### Iniciar Manualmente
```bash
cd /var/www/facturacion/camera_daemon
source venv/bin/activate
python3 daemon_simple.py
```

### Ver Logs
```bash
tail -f logs/daemon.log
```

## Configuración de Cámaras
El daemon lee las cámaras desde la tabla `camaras` de la base de datos:

```sql
SELECT id, nombre, ruta_stream, ip, puerto, usuario, password, tipo, activo 
FROM camaras 
WHERE activo = 1
```

### Campos Importantes
- `ruta_stream`: URL RTSP completa (prioridad)
- `ip`, `puerto`, `usuario`, `password`: Para construir URL si no hay `ruta_stream`
- `activo`: Solo cámaras activas se procesan

## Funcionalidades Actuales
- ✅ Conexión a base de datos MySQL
- ✅ Carga dinámica de cámaras
- ✅ Grabación con FFmpeg (5 minutos por archivo)
- ✅ Registro de eventos en BD
- ✅ Logging detallado
- ✅ Manejo de señales (graceful shutdown)
- ✅ Reconexión automática

## Próximos Pasos Opcionales
1. **Configurar cámaras IP** reales en la base de datos
2. **Instalar FFmpeg** si no está disponible: `sudo apt install ffmpeg`
3. **Crear servicio systemd** para inicio automático
4. **Habilitar análisis IA** con daemon completo (requiere más dependencias)

## Solución de Problemas

### Error: "Cámara no accesible"
- Verificar que la cámara IP esté en la red
- Comprobar credenciales de la cámara
- Validar URL RTSP manualmente

### Error: "Permission denied"
```bash
sudo chmod 777 /var/www/facturacion/videos
```

### Error: "No module named"
```bash
cd /var/www/facturacion/camera_daemon
source venv/bin/activate
pip install [modulo_faltante]
```

## Monitoreo
El daemon genera logs en `logs/daemon.log` y registra eventos en las tablas:
- `camera_recording_events`: Eventos de grabación
- `eventos_camara`: Eventos del sistema

**Estado: ✅ FUNCIONANDO**
