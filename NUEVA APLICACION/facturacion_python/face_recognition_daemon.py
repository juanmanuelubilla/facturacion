#!/usr/bin/env python3
"""
Daemon de Reconocimiento Facial en Tiempo Real
Captura frames de cámaras, procesa reconocimiento facial y detecta comportamientos anómalos.
"""

import sys
import os
import time
import json
import logging
import signal
import threading
from datetime import datetime
from typing import Dict, List, Optional, Any
import cv2
import numpy as np
import mysql.connector
from mysql.connector import Error

# Configuración de logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler('/var/log/face_recognition_daemon.log'),
        logging.StreamHandler()
    ]
)
logger = logging.getLogger(__name__)

class FaceRecognitionDaemon:
    def __init__(self):
        self.running = True
        self.db_config = self.load_db_config()
        self.empresa_id = self.get_empresa_id()
        self.config = self.load_config()
        self.cameras = self.load_cameras()
        self.client_profiles = self.load_client_profiles()
        self.risk_profiles = self.load_risk_profiles()
        self.video_captures = {}
        self.last_detection_time = {}
        
        # Configurar handlers de señales
        signal.signal(signal.SIGINT, self.signal_handler)
        signal.signal(signal.SIGTERM, self.signal_handler)
        
    def signal_handler(self, signum, frame):
        """Manejo de señales para apagado graceful"""
        logger.info(f"Recibida señal {signum}, iniciando apagado...")
        self.running = False
        
    def load_db_config(self) -> Dict[str, str]:
        """Cargar configuración de base de datos desde config.php"""
        config_path = '/home/pi/facturacion_php/config.php'
        db_config = {
            'host': 'localhost',
            'user': 'root',
            'password': '',
            'database': 'facturacion'
        }
        
        try:
            with open(config_path, 'r') as f:
                content = f.read()
                # Parsear variables de PHP (simplificado)
                if "'DB_HOST'" in content:
                    # Extraer valores del archivo config.php
                    lines = content.split('\n')
                    for line in lines:
                        if 'DB_HOST' in line and '=' in line:
                            value = line.split('=')[1].strip().strip("'").strip('"')
                            if value and value != '':
                                db_config['host'] = value
                        elif 'DB_USER' in line and '=' in line:
                            value = line.split('=')[1].strip().strip("'").strip('"')
                            if value and value != '':
                                db_config['user'] = value
                        elif 'DB_PASS' in line and '=' in line:
                            value = line.split('=')[1].strip().strip("'").strip('"')
                            if value and value != '':
                                db_config['password'] = value
                        elif 'DB_NAME' in line and '=' in line:
                            value = line.split('=')[1].strip().strip("'").strip('"')
                            if value and value != '':
                                db_config['database'] = value
        except Exception as e:
            logger.warning(f"No se pudo cargar config.php, usando defaults: {e}")
            
        return db_config
    
    def get_empresa_id(self) -> int:
        """Obtener el ID de la empresa activa"""
        try:
            conn = mysql.connector.connect(**self.db_config)
            cursor = conn.cursor(dictionary=True)
            cursor.execute("SELECT id FROM nombre_negocio LIMIT 1")
            result = cursor.fetchone()
            cursor.close()
            conn.close()
            return result['id'] if result else 1
        except Exception as e:
            logger.error(f"Error obteniendo empresa_id: {e}")
            return 1
    
    def load_config(self) -> Dict[str, Any]:
        """Cargar configuración de reconocimiento facial desde base de datos"""
        try:
            conn = mysql.connector.connect(**self.db_config)
            cursor = conn.cursor(dictionary=True)
            cursor.execute("SELECT * FROM config_alertas WHERE empresa_id = %s", (self.empresa_id,))
            result = cursor.fetchone()
            cursor.close()
            conn.close()
            return result if result else {}
        except Exception as e:
            logger.error(f"Error cargando configuración: {e}")
            return {}
    
    def load_cameras(self) -> List[Dict[str, Any]]:
        """Cargar cámaras activas desde base de datos"""
        try:
            conn = mysql.connector.connect(**self.db_config)
            cursor = conn.cursor(dictionary=True)
            cursor.execute("""
                SELECT id, nombre, ip, stream_url, activo 
                FROM camaras 
                WHERE empresa_id = %s AND activo = 1
            """, (self.empresa_id,))
            cameras = cursor.fetchall()
            cursor.close()
            conn.close()
            return cameras
        except Exception as e:
            logger.error(f"Error cargando cámaras: {e}")
            return []
    
    def load_client_profiles(self) -> List[Dict[str, Any]]:
        """Cargar perfiles faciales de clientes"""
        try:
            conn = mysql.connector.connect(**self.db_config)
            cursor = conn.cursor(dictionary=True)
            cursor.execute("""
                SELECT pf.id, pf.cliente_id, pf.face_data, c.nombre, c.apellido, c.telefono
                FROM perfiles_faciales pf
                JOIN clientes c ON pf.cliente_id = c.id
                WHERE pf.empresa_id = %s
            """, (self.empresa_id,))
            profiles = cursor.fetchall()
            cursor.close()
            conn.close()
            return profiles
        except Exception as e:
            logger.error(f"Error cargando perfiles de clientes: {e}")
            return []
    
    def load_risk_profiles(self) -> List[Dict[str, Any]]:
        """Cargar perfiles de personas de riesgo"""
        try:
            conn = mysql.connector.connect(**self.db_config)
            cursor = conn.cursor(dictionary=True)
            cursor.execute("""
                SELECT id, nombre, descripcion, modus_operandi, foto, nivel_riesgo
                FROM personas_riesgo
                WHERE empresa_id = %s
            """, (self.empresa_id,))
            profiles = cursor.fetchall()
            cursor.close()
            conn.close()
            return profiles
        except Exception as e:
            logger.error(f"Error cargando perfiles de riesgo: {e}")
            return []
    
    def initialize_cameras(self):
        """Inicializar capturas de video para todas las cámaras"""
        for camera in self.cameras:
            try:
                camera_id = camera['id']
                stream_url = camera.get('stream_url', '')
                
                if stream_url:
                    # Usar URL de streaming
                    cap = cv2.VideoCapture(stream_url)
                else:
                    # Usar IP directa (RTSP)
                    rtsp_url = f"rtsp://{camera['ip']}/stream1"
                    cap = cv2.VideoCapture(rtsp_url)
                
                if cap.isOpened():
                    self.video_captures[camera_id] = cap
                    logger.info(f"Cámara {camera['nombre']} (ID: {camera_id}) inicializada")
                else:
                    logger.warning(f"No se pudo conectar a cámara {camera['nombre']}")
                    
            except Exception as e:
                logger.error(f"Error inicializando cámara {camera['nombre']}: {e}")
    
    def process_frame(self, frame: np.ndarray, camera_id: int) -> Optional[Dict[str, Any]]:
        """
        Procesar un frame para reconocimiento facial
        Retorna información de detección si encuentra algo
        """
        try:
            # Convertir a escala de grises para detección de rostros
            gray = cv2.cvtColor(frame, cv2.COLOR_BGR2GRAY)
            
            # Aquí iría la detección de rostros con OpenCV o face_recognition
            # Por ahora, simulamos detección
            # En producción, usar face_recognition.face_locations() o cv2.CascadeClassifier
            
            # Simulación: detectar si hay rostros (placeholder)
            # En producción, esto sería:
            # face_locations = face_recognition.face_locations(frame)
            # face_encodings = face_recognition.face_encodings(frame, face_locations)
            
            # Por ahora, retornamos None (sin detección)
            return None
            
        except Exception as e:
            logger.error(f"Error procesando frame: {e}")
            return None
    
    def compare_with_profiles(self, face_encoding: np.ndarray) -> Optional[Dict[str, Any]]:
        """Comparar encoding de rostro con perfiles conocidos"""
        # Placeholder para comparación real
        # En producción, usar face_recognition.compare_faces()
        return None
    
    def detect_suspicious_behavior(self, frame: np.ndarray) -> Optional[Dict[str, Any]]:
        """Detectar comportamientos sospechosos en el frame"""
        # Placeholder para análisis de comportamiento
        # En producción, usar análisis de posturas y movimientos
        return None
    
    def register_detection(self, detection_type: str, camera_id: int, confidence: float, 
                          cliente_id: Optional[int] = None, persona_riesgo_id: Optional[int] = None,
                          descripcion: str = ""):
        """Registrar detección en base de datos"""
        try:
            conn = mysql.connector.connect(**self.db_config)
            cursor = conn.cursor()
            
            sql = """
                INSERT INTO detecciones_faciales 
                (empresa_id, tipo_deteccion, camara_id, cliente_id, persona_riesgo_id, 
                 confianza, descripcion, fecha_hora)
                VALUES (%s, %s, %s, %s, %s, %s, %s, NOW())
            """
            
            cursor.execute(sql, (
                self.empresa_id,
                detection_type,
                camera_id,
                cliente_id,
                persona_riesgo_id,
                confidence,
                descripcion
            ))
            
            conn.commit()
            cursor.close()
            conn.close()
            
            logger.info(f"Detección registrada: {detection_type} en cámara {camera_id}")
            
        except Exception as e:
            logger.error(f"Error registrando detección: {e}")
    
    def check_alert_conditions(self, detection_type: str) -> bool:
        """Verificar si se deben enviar alertas según configuración"""
        if not self.config:
            return False
            
        return self.config.get('alertas_activas', 0) == 1
    
    def send_alert(self, detection_type: str, message: str):
        """Enviar alerta según configuración (sonido, pantalla, email, WhatsApp)"""
        if not self.check_alert_conditions(detection_type):
            return
            
        # Implementar envío de alertas según configuración
        # Por ahora, solo log
        logger.warning(f"ALERTA: {message}")
        
        # En producción:
        # - Sonido: reproducir archivo de audio
        # - Pantalla: guardar en tabla de alertas activas
        # - Email: usar EmailService
        # - WhatsApp: usar WhatsAppService
    
    def process_camera(self, camera: Dict[str, Any]):
        """Procesar frames de una cámara específica"""
        camera_id = camera['id']
        camera_name = camera['nombre']
        
        cap = self.video_captures.get(camera_id)
        if not cap:
            logger.warning(f"Cámara {camera_name} no está inicializada")
            return
        
        logger.info(f"Iniciando procesamiento de cámara {camera_name}")
        
        while self.running:
            try:
                ret, frame = cap.read()
                if not ret:
                    logger.warning(f"No se pudo leer frame de cámara {camera_name}")
                    time.sleep(1)
                    continue
                
                # Procesar frame
                detection = self.process_frame(frame, camera_id)
                
                if detection:
                    # Registrar detección
                    self.register_detection(
                        detection_type=detection['type'],
                        camera_id=camera_id,
                        confidence=detection['confidence'],
                        cliente_id=detection.get('cliente_id'),
                        persona_riesgo_id=detection.get('persona_riesgo_id'),
                        descripcion=detection.get('descripcion', '')
                    )
                    
                    # Enviar alerta si corresponde
                    self.send_alert(
                        detection_type=detection['type'],
                        message=f"{detection['type']} detectado en {camera_name}"
                    )
                
                # Controlar frecuencia de procesamiento
                time.sleep(0.1)  # 10 FPS máximo
                
            except Exception as e:
                logger.error(f"Error procesando cámara {camera_name}: {e}")
                time.sleep(1)
        
        logger.info(f"Detenido procesamiento de cámara {camera_name}")
    
    def run(self):
        """Ejecutar el daemon principal"""
        logger.info("Iniciando daemon de reconocimiento facial...")
        
        # Inicializar cámaras
        self.initialize_cameras()
        
        if not self.video_captures:
            logger.warning("No hay cámaras inicializadas, daemon no hará nada")
            return
        
        # Crear threads para cada cámara
        threads = []
        for camera in self.cameras:
            thread = threading.Thread(target=self.process_camera, args=(camera,))
            thread.daemon = True
            thread.start()
            threads.append(thread)
        
        # Mantener daemon corriendo
        try:
            while self.running:
                # Recargar configuración periódicamente
                time.sleep(60)
                self.config = self.load_config()
                self.cameras = self.load_cameras()
                self.client_profiles = self.load_client_profiles()
                self.risk_profiles = self.load_risk_profiles()
                
        except KeyboardInterrupt:
            logger.info("Interrupción por teclado")
        finally:
            # Limpiar recursos
            logger.info("Limpiando recursos...")
            for camera_id, cap in self.video_captures.items():
                cap.release()
            logger.info("Daemon detenido")

def main():
    """Punto de entrada principal"""
    daemon = FaceRecognitionDaemon()
    daemon.run()

if __name__ == '__main__':
    main()
