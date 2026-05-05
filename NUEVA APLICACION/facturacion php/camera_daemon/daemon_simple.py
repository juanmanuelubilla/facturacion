#!/usr/bin/env python3
"""
Simple Camera Daemon - Sin dependencias de OpenCV
Gestiona grabación y eventos básicos sin IA
"""

import os
import sys
import time
import json
import signal
import logging
import subprocess
from datetime import datetime
from threading import Thread, Event

# Configuración de logging
import os
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
LOGS_DIR = os.path.join(BASE_DIR, 'logs')

logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler(os.path.join(LOGS_DIR, 'daemon.log')),
        logging.StreamHandler()
    ]
)
logger = logging.getLogger(__name__)

class SimpleCameraDaemon:
    def __init__(self):
        self.running = False
        self.stop_event = Event()
        self.cameras = []
        self.threads = []
        
        # Señales para shutdown limpio
        signal.signal(signal.SIGINT, self.signal_handler)
        signal.signal(signal.SIGTERM, self.signal_handler)
        
    def signal_handler(self, signum, frame):
        logger.info(f"Señal {signum} recibida, deteniendo daemon...")
        self.stop()
        
    def load_cameras(self):
        """Cargar cámaras desde la base de datos"""
        try:
            import pymysql
            
            conn = pymysql.connect(
                host='localhost',
                user='facturacion',
                password='facturacion',
                database='facturacion'
            )
            
            cursor = conn.cursor(pymysql.cursors.DictCursor)
            cursor.execute("""
                SELECT id, nombre, ruta_stream, ip, puerto, usuario, password, tipo, activo 
                FROM camaras 
                WHERE activo = 1
            """)
            
            self.cameras = cursor.fetchall()
            conn.close()
            
            logger.info(f"Cargadas {len(self.cameras)} cámaras activas")
            
        except Exception as e:
            logger.error(f"Error cargando cámaras: {e}")
            
    def start_recording(self, camera):
        """Iniciar grabación con FFmpeg"""
        while self.running and not self.stop_event.is_set():
            try:
                # Construir URL RTSP
                if camera['ruta_stream']:
                    rtsp_url = camera['ruta_stream']
                else:
                    # Construir URL básica si no hay ruta_stream
                    rtsp_url = f"rtsp://{camera['ip']}:{camera.get('puerto', 554)}/"
                    if camera['usuario']:
                        rtsp_url = f"rtsp://{camera['usuario']}:{camera['password']}@{camera['ip']}:{camera.get('puerto', 554)}/"
                
                # Verificar si la cámara está accesible
                if not self.test_camera_connection(rtsp_url):
                    logger.warning(f"Cámara {camera['nombre']} no accesible, esperando...")
                    time.sleep(30)
                    continue
                
                # Crear archivo de grabación
                timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
                filename = f"cam_{camera['id']}_{timestamp}.mp4"
                videos_dir = os.path.join(BASE_DIR, '..', 'videos')
                os.makedirs(videos_dir, exist_ok=True)
                filepath = os.path.join(videos_dir, filename)
                
                # Comando FFmpeg para grabar
                cmd = [
                    'ffmpeg',
                    '-i', rtsp_url,
                    '-c:v', 'copy',
                    '-c:a', 'aac',
                    '-t', '300',  # 5 minutos por archivo
                    '-y',  # Sobreescribir
                    filepath
                ]
                
                logger.info(f"Iniciando grabación de {camera['nombre']}: {filename}")
                
                # Ejecutar FFmpeg
                process = subprocess.Popen(
                    cmd,
                    stdout=subprocess.PIPE,
                    stderr=subprocess.PIPE,
                    text=True
                )
                
                # Esperar a que termine o se detenga
                try:
                    process.wait(timeout=310)  # 5 min + 10 seg buffer
                except subprocess.TimeoutExpired:
                    process.terminate()
                    process.wait()
                
                # Registrar evento en base de datos
                self.record_recording_event(camera['id'], filename, filepath)
                
                logger.info(f"Grabación completada: {filename}")
                
            except Exception as e:
                logger.error(f"Error en grabación de {camera['nombre']}: {e}")
                time.sleep(60)  # Esperar 1 minuto antes de reintentar
                
    def test_camera_connection(self, url):
        """Probar conexión RTSP simple"""
        try:
            # Usar FFprobe para verificar stream
            cmd = ['ffprobe', '-v', 'error', '-show_entries', 'stream=codec_name', url]
            result = subprocess.run(cmd, capture_output=True, text=True, timeout=10)
            return result.returncode == 0
        except:
            return False
            
    def record_recording_event(self, camera_id, filename, filepath):
        """Registrar evento de grabación en base de datos"""
        try:
            import pymysql
            
            conn = pymysql.connect(
                host='localhost',
                user='facturacion',
                password='facturacion',
                database='facturacion'
            )
            
            cursor = conn.cursor()
            cursor.execute("""
                INSERT INTO camera_recording_events 
                (camera_id, event_type, file_path, timestamp)
                VALUES (%s, 'schedule', %s, NOW())
            """, (camera_id, filepath))
            
            conn.commit()
            conn.close()
            
        except Exception as e:
            logger.error(f"Error registrando evento: {e}")
            
    def start(self):
        """Iniciar el daemon"""
        logger.info("Iniciando Simple Camera Daemon...")
        
        # Crear directorios necesarios
        os.makedirs(LOGS_DIR, exist_ok=True)
        videos_dir = os.path.join(BASE_DIR, '..', 'videos')
        os.makedirs(videos_dir, exist_ok=True)
        
        # Cargar cámaras
        self.load_cameras()
        
        if not self.cameras:
            logger.warning("No hay cámaras configuradas, daemon esperando...")
            # Esperar y recargar periódicamente
            while self.running and not self.stop_event.is_set():
                time.sleep(300)  # 5 minutos
                self.load_cameras()
                if self.cameras:
                    break
                    
        if not self.cameras:
            logger.error("No se encontraron cámaras, deteniendo daemon")
            return
            
        # Iniciar thread para cada cámara
        self.running = True
        for camera in self.cameras:
            thread = Thread(target=self.start_recording, args=(camera,))
            thread.daemon = True
            thread.start()
            self.threads.append(thread)
            logger.info(f"Thread iniciado para cámara: {camera['nombre']}")
            
        logger.info(f"Daemon iniciado con {len(self.threads)} cámaras")
        
        # Mantener daemon corriendo
        try:
            while self.running and not self.stop_event.is_set():
                time.sleep(60)
                # Recargar cámaras cada minuto
                self.load_cameras()
                
        except KeyboardInterrupt:
            logger.info("Interrupción por teclado")
        finally:
            self.stop()
            
    def stop(self):
        """Detener el daemon"""
        logger.info("Deteniendo Simple Camera Daemon...")
        self.running = False
        self.stop_event.set()
        
        # Esperar a que terminen los threads
        for thread in self.threads:
            thread.join(timeout=5)
            
        logger.info("Simple Camera Daemon detenido")

def main():
    """Función principal"""
    daemon = SimpleCameraDaemon()
    
    try:
        daemon.start()
    except Exception as e:
        logger.error(f"Error fatal en daemon: {e}")
        sys.exit(1)

if __name__ == "__main__":
    main()
