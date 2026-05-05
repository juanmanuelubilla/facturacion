#!/usr/bin/env python3
"""
Servicio de Reconocimiento Facial Real con OpenCV
Integrado con PHP a través de llamada al sistema
"""

import cv2
import numpy as np
import json
import sys
import os
import base64
from typing import Dict, List, Optional, Tuple

class RealFaceRecognitionService:
    def __init__(self):
        # Inicializar detectores de OpenCV
        haar_cascade_path = '/usr/share/opencv4/haarcascades/haarcascade_frontalface_default.xml'
        self.face_cascade = cv2.CascadeClassifier(haar_cascade_path)
        
        # Intentar crear el reconocedor LBPH si está disponible
        try:
            self.recognizer = cv2.face.LBPHFaceRecognizer_create()
        except AttributeError:
            # Si cv2.face no está disponible, usar un placeholder
            self.recognizer = None
        
        # Umbral de confianza por defecto
        self.default_threshold = 80.0
        
        # Cargar modelos entrenados si existen
        self.load_trained_models()
    
    def load_trained_models(self):
        """Cargar modelos entrenados si existen"""
        model_path = "models/face_model.yml"
        if os.path.exists(model_path):
            try:
                self.recognizer.read(model_path)
                print("Modelo facial cargado correctamente")
            except Exception as e:
                print(f"Error cargando modelo: {e}")
    
    def detect_faces(self, image_path: str) -> List[Dict]:
        """Detectar rostros en una imagen"""
        try:
            # Cargar imagen
            if image_path.startswith('data:image'):
                # Imagen en base64
                header, encoded = image_path.split(',', 1)
                image_data = base64.b64decode(encoded)
                nparr = np.frombuffer(image_data, np.uint8)
                img = cv2.imdecode(nparr, cv2.IMREAD_COLOR)
            else:
                # Ruta de archivo
                img = cv2.imread(image_path)
            
            if img is None:
                return []
            
            # Convertir a escala de grises
            gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
            
            # Detectar rostros
            faces = self.face_cascade.detectMultiScale(gray, 1.1, 4)
            
            face_data = []
            for (x, y, w, h) in faces:
                face_roi = gray[y:y+h, x:x+w]
                
                # Extraer características faciales
                face_encoding = self.extract_face_features(face_roi)
                
                face_data.append({
                    'x': int(x),
                    'y': int(y), 
                    'width': int(w),
                    'height': int(h),
                    'encoding': face_encoding.tolist() if face_encoding is not None else None
                })
            
            return face_data
            
        except Exception as e:
            print(f"Error detectando rostros: {e}")
            return []
    
    def extract_face_features(self, face_roi: np.ndarray) -> Optional[np.ndarray]:
        """Extraer características faciales usando LBP"""
        try:
            # Redimensionar a tamaño estándar
            face_roi = cv2.resize(face_roi, (100, 100))
            
            # Extraer características LBP
            lbp = self.calculate_lbp(face_roi)
            
            return lbp
            
        except Exception as e:
            print(f"Error extrayendo características: {e}")
            return None
    
    def calculate_lbp(self, image: np.ndarray, radius: int = 1, n_points: int = 8) -> np.ndarray:
        """Calcular Local Binary Pattern"""
        try:
            # Implementación simple de LBP
            h, w = image.shape
            lbp = np.zeros((h, w), dtype=np.uint8)
            
            for i in range(radius, h - radius):
                for j in range(radius, w - radius):
                    center = image[i, j]
                    
                    binary = []
                    for n in range(n_points):
                        # Calcular coordenadas del vecino
                        angle = 2 * np.pi * n / n_points
                        x = i + radius * np.cos(angle)
                        y = j + radius * np.sin(angle)
                        
                        # Interpolación bilineal
                        x1, y1 = int(x), int(y)
                        x2, y2 = x1 + 1, y1 + 1
                        
                        if x2 < h and y2 < w:
                            dx, dy = x - x1, y - y1
                            neighbor = (1-dx) * (1-dy) * image[x1, y1] + \
                                     dx * (1-dy) * image[x2, y1] + \
                                     (1-dx) * dy * image[x1, y2] + \
                                     dx * dy * image[x2, y2]
                        else:
                            neighbor = image[x1, y1]
                        
                        binary.append(1 if neighbor >= center else 0)
                    
                    # Convertir binario a decimal
                    lbp_value = 0
                    for bit in binary:
                        lbp_value = (lbp_value << 1) | bit
                    
                    lbp[i, j] = lbp_value
            
            # Calcular histograma LBP
            hist, _ = np.histogram(lbp, bins=256, range=(0, 256))
            
            return hist
            
        except Exception as e:
            print(f"Error calculando LBP: {e}")
            return np.zeros(256)
    
    def compare_faces(self, face1_encoding: np.ndarray, face2_encoding: np.ndarray) -> float:
        """Comparar dos rostros y devolver similitud (0-100)"""
        try:
            if face1_encoding is None or face2_encoding is None:
                return 0.0
            
            # Asegurar que ambos arrays tengan el mismo tamaño
            min_size = min(len(face1_encoding), len(face2_encoding))
            face1_encoding = face1_encoding[:min_size]
            face2_encoding = face2_encoding[:min_size]
            
            # Calcular correlación
            correlation = cv2.compareHist(face1_encoding.astype(np.float32), 
                                        face2_encoding.astype(np.float32), 
                                        cv2.HISTCMP_CORREL)
            
            # Convertir a porcentaje (0-100)
            similarity = max(0, min(100, (correlation + 1) * 50))
            
            return similarity
            
        except Exception as e:
            print(f"Error comparando rostros: {e}")
            return 0.0
    
    def recognize_face(self, image_path: str, known_faces: List[Dict], threshold: float = 80.0) -> Dict:
        """Reconocer rostro en imagen contra rostros conocidos"""
        try:
            # Detectar rostros en la imagen
            detected_faces = self.detect_faces(image_path)
            
            if not detected_faces:
                return {
                    'success': False,
                    'message': 'No se detectaron rostros',
                    'type': 'NO_FACE'
                }
            
            # Tomar el primer rostro detectado
            face_encoding = detected_faces[0]['encoding']
            
            if face_encoding is None:
                return {
                    'success': False,
                    'message': 'Error extrayendo características faciales',
                    'type': 'ERROR'
                }
            
            # Comparar con rostros conocidos
            best_match = None
            best_confidence = 0.0
            
            for known_face in known_faces:
                if 'encoding' not in known_face or known_face['encoding'] is None:
                    continue
                
                known_encoding = np.array(known_face['encoding'])
                confidence = self.compare_faces(face_encoding, known_encoding)
                
                if confidence > best_confidence:
                    best_confidence = confidence
                    best_match = known_face
            
            if best_match and best_confidence >= threshold:
                return {
                    'success': True,
                    'type': 'CLIENTE',
                    'confidence': best_confidence,
                    'data': best_match,
                    'message': f'Cliente reconocido: {best_match.get("nombre", "Desconocido")} con {best_confidence:.1f}% de confianza'
                }
            else:
                return {
                    'success': True,
                    'type': 'DESCONOCIDO',
                    'confidence': best_confidence,
                    'message': f'Rostro no reconocido (mejor coincidencia: {best_confidence:.1f}%)'
                }
                
        except Exception as e:
            return {
                'success': False,
                'message': f'Error en reconocimiento: {str(e)}',
                'type': 'ERROR'
            }

def main():
    """Función principal para llamada desde PHP"""
    if len(sys.argv) < 4:
        print(json.dumps({
            'success': False,
            'message': 'Uso: python3 FaceRecognitionService.py <accion> <parametros>',
            'type': 'ERROR'
        }))
        return
    
    action = sys.argv[1]
    service = RealFaceRecognitionService()
    
    try:
        if action == 'recognize':
            # python3 FaceRecognitionService.py recognize <imagen> <rostros_conocidos_json> <umbral>
            image_path = sys.argv[2]
            known_faces_json = sys.argv[3]
            threshold = float(sys.argv[4]) if len(sys.argv) > 4 else 80.0
            
            known_faces = json.loads(known_faces_json)
            
            result = service.recognize_face(image_path, known_faces, threshold)
            print(json.dumps(result))
            
        elif action == 'detect':
            # python3 FaceRecognitionService.py detect <imagen>
            image_path = sys.argv[2]
            
            faces = service.detect_faces(image_path)
            print(json.dumps({
                'success': True,
                'faces': faces,
                'count': len(faces)
            }))
            
        else:
            print(json.dumps({
                'success': False,
                'message': f'Acción desconocida: {action}',
                'type': 'ERROR'
            }))
            
    except Exception as e:
        print(json.dumps({
            'success': False,
            'message': f'Error: {str(e)}',
            'type': 'ERROR'
        }))

if __name__ == '__main__':
    main()
