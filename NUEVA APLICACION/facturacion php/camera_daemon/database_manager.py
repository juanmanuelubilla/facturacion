#!/usr/bin/env python3
"""
Database Manager for Advanced Camera System
Handles all database operations for camera analysis, events, and BI data
"""

import pymysql
import json
import logging
from datetime import datetime, timedelta
from typing import Dict, List, Optional, Any
from contextlib import contextmanager

class DatabaseManager:
    """Database manager for camera system"""
    
    def __init__(self, db_config: Dict):
        self.db_config = db_config
        self.logger = logging.getLogger("database")
        
        # Test connection
        try:
            with self.get_connection() as conn:
                cursor = conn.cursor()
                cursor.execute("SELECT 1")
            self.logger.info("Database connection established")
        except Exception as e:
            self.logger.error(f"Database connection failed: {e}")
            raise
    
    @contextmanager
    def get_connection(self):
        """Get database connection with context manager"""
        conn = None
        try:
            conn = pymysql.connect(**self.db_config)
            yield conn
        except Exception as e:
            if conn:
                conn.rollback()
            raise e
        finally:
            if conn:
                conn.close()
    
    def get_active_cameras(self) -> List[Dict]:
        """Get all active cameras"""
        try:
            with self.get_connection() as conn:
                cursor = conn.cursor(pymysql.cursors.DictCursor)
                cursor.execute("""
                    SELECT id, nombre, ip, puerto, usuario, password, tipo, 
                           marca, modelo, ruta_stream, url_completa, activo, empresa_id
                    FROM camaras 
                    WHERE activo = 1 
                    ORDER BY nombre
                """)
                return cursor.fetchall()
        except Exception as e:
            self.logger.error(f"Error getting active cameras: {e}")
            return []
    
    def get_camera_config(self) -> Dict:
        """Get camera configuration for current company"""
        try:
            with self.get_connection() as conn:
                cursor = conn.cursor(pymysql.cursors.DictCursor)
                cursor.execute("""
                    SELECT grabar_ventas, deteccion_movimiento, calidad_video, 
                           duracion_grabacion, almacenamiento_maximo, 
                           horario_inicio, horario_fin, alertas_fuera_horario
                    FROM config_camara 
                    WHERE empresa_id = 1
                    ORDER BY id DESC 
                    LIMIT 1
                """)
                result = cursor.fetchone()
                return result or {}
        except Exception as e:
            self.logger.error(f"Error getting camera config: {e}")
            return {}
    
    def store_face_detection(self, camera_id: int, face_data: Dict) -> int:
        """Store face detection event"""
        try:
            with self.get_connection() as conn:
                cursor = conn.cursor()
                cursor.execute("""
                    INSERT INTO face_detections 
                    (camera_id, face_id, name, confidence, bbox_x, bbox_y, 
                     bbox_width, bbox_height, timestamp, image_path)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                """, (
                    camera_id,
                    face_data.get('face_id'),
                    face_data.get('name'),
                    face_data.get('confidence'),
                    face_data.get('bbox', [0, 0, 0, 0])[0],
                    face_data.get('bbox', [0, 0, 0, 0])[1],
                    face_data.get('bbox', [0, 0, 0, 0])[2],
                    face_data.get('bbox', [0, 0, 0, 0])[3],
                    datetime.now(),
                    face_data.get('image_path')
                ))
                
                detection_id = cursor.lastrowid
                conn.commit()
                return detection_id
                
        except Exception as e:
            self.logger.error(f"Error storing face detection: {e}")
            return 0
    
    def store_object_detection(self, camera_id: int, object_data: Dict) -> int:
        """Store object detection event"""
        try:
            with self.get_connection() as conn:
                cursor = conn.cursor()
                cursor.execute("""
                    INSERT INTO object_detections 
                    (camera_id, class_name, confidence, bbox_x, bbox_y, 
                     bbox_width, bbox_height, timestamp)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                """, (
                    camera_id,
                    object_data.get('class_name'),
                    object_data.get('confidence'),
                    object_data.get('bbox', [0, 0, 0, 0])[0],
                    object_data.get('bbox', [0, 0, 0, 0])[1],
                    object_data.get('bbox', [0, 0, 0, 0])[2],
                    object_data.get('bbox', [0, 0, 0, 0])[3],
                    datetime.now()
                ))
                
                detection_id = cursor.lastrowid
                conn.commit()
                return detection_id
                
        except Exception as e:
            self.logger.error(f"Error storing object detection: {e}")
            return 0
    
    def store_action_detection(self, camera_id: int, action_data: Dict) -> int:
        """Store action detection event"""
        try:
            with self.get_connection() as conn:
                cursor = conn.cursor()
                cursor.execute("""
                    INSERT INTO action_detections 
                    (camera_id, action, confidence, person_id, bbox_x, bbox_y, 
                     bbox_width, bbox_height, timestamp)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                """, (
                    camera_id,
                    action_data.get('action'),
                    action_data.get('confidence'),
                    action_data.get('person_id'),
                    action_data.get('bbox', [0, 0, 0, 0])[0],
                    action_data.get('bbox', [0, 0, 0, 0])[1],
                    action_data.get('bbox', [0, 0, 0, 0])[2],
                    action_data.get('bbox', [0, 0, 0, 0])[3],
                    datetime.now()
                ))
                
                detection_id = cursor.lastrowid
                conn.commit()
                return detection_id
                
        except Exception as e:
            self.logger.error(f"Error storing action detection: {e}")
            return 0
    
    def store_security_alert(self, camera_id: int, alert_data: Dict) -> int:
        """Store security alert"""
        try:
            with self.get_connection() as conn:
                cursor = conn.cursor()
                cursor.execute("""
                    INSERT INTO security_alerts 
                    (camera_id, alert_type, description, severity, 
                     details_json, timestamp, acknowledged)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                """, (
                    camera_id,
                    alert_data.get('alert_type'),
                    alert_data.get('description'),
                    alert_data.get('severity', 'medium'),
                    json.dumps(alert_data.get('details', {})),
                    datetime.now(),
                    False
                ))
                
                alert_id = cursor.lastrowid
                conn.commit()
                return alert_id
                
        except Exception as e:
            self.logger.error(f"Error storing security alert: {e}")
            return 0
    
    def store_bi_insight(self, insight_data: Dict) -> int:
        """Store business intelligence insight"""
        try:
            with self.get_connection() as conn:
                cursor = conn.cursor()
                cursor.execute("""
                    INSERT INTO bi_insights 
                    (camera_id, insight_type, value, metadata_json, timestamp)
                    VALUES (?, ?, ?, ?, ?)
                """, (
                    insight_data.get('camera_id'),
                    insight_data.get('insight_type'),
                    insight_data.get('value'),
                    json.dumps(insight_data.get('metadata', {})),
                    datetime.now()
                ))
                
                insight_id = cursor.lastrowid
                conn.commit()
                return insight_id
                
        except Exception as e:
            self.logger.error(f"Error storing BI insight: {e}")
            return 0
    
    def get_recent_detections(self, camera_id: int = None, hours: int = 24) -> Dict:
        """Get recent detections summary"""
        try:
            with self.get_connection() as conn:
                cursor = conn.cursor(pymysql.cursors.DictCursor)
                
                since_time = datetime.now() - timedelta(hours=hours)
                
                # Face detections
                face_query = """
                    SELECT COUNT(*) as face_count, 
                           COUNT(DISTINCT name) as unique_faces,
                           AVG(confidence) as avg_confidence
                    FROM face_detections 
                    WHERE timestamp >= ?
                """
                params = [since_time]
                
                if camera_id:
                    face_query += " AND camera_id = ?"
                    params.append(camera_id)
                
                cursor.execute(face_query, params)
                face_stats = cursor.fetchone()
                
                # Object detections
                object_query = """
                    SELECT class_name, COUNT(*) as count
                    FROM object_detections 
                    WHERE timestamp >= ?
                """
                object_params = [since_time]
                
                if camera_id:
                    object_query += " AND camera_id = ?"
                    object_params.append(camera_id)
                
                object_query += " GROUP BY class_name"
                cursor.execute(object_query, object_params)
                object_stats = cursor.fetchall()
                
                # Action detections
                action_query = """
                    SELECT action, COUNT(*) as count
                    FROM action_detections 
                    WHERE timestamp >= ?
                """
                action_params = [since_time]
                
                if camera_id:
                    action_query += " AND camera_id = ?"
                    action_params.append(camera_id)
                
                action_query += " GROUP BY action"
                cursor.execute(action_query, action_params)
                action_stats = cursor.fetchall()
                
                return {
                    'face_stats': face_stats or {},
                    'object_stats': object_stats or [],
                    'action_stats': action_stats or [],
                    'period_hours': hours
                }
                
        except Exception as e:
            self.logger.error(f"Error getting recent detections: {e}")
            return {}
    
    def get_people_count_history(self, camera_id: int = None, hours: int = 24) -> List[Dict]:
        """Get people count history over time"""
        try:
            with self.get_connection() as conn:
                cursor = conn.cursor(pymysql.cursors.DictCursor)
                
                query = """
                    SELECT DATE_FORMAT(timestamp, '%Y-%m-%d %H:00:00') as hour_bucket,
                           AVG(CASE WHEN class_name = 'person' THEN 1 ELSE 0 END) as avg_people_count,
                           COUNT(CASE WHEN class_name = 'person' THEN 1 END) as total_detections
                    FROM object_detections 
                    WHERE timestamp >= DATE_SUB(NOW(), INTERVAL ? HOUR)
                """
                params = [hours]
                
                if camera_id:
                    query += " AND camera_id = ?"
                    params.append(camera_id)
                
                query += " GROUP BY hour_bucket ORDER BY hour_bucket"
                cursor.execute(query, params)
                return cursor.fetchall()
                
        except Exception as e:
            self.logger.error(f"Error getting people count history: {e}")
            return []
    
    def get_security_alerts(self, camera_id: int = None, hours: int = 24, 
                           acknowledged: bool = None) -> List[Dict]:
        """Get security alerts"""
        try:
            with self.get_connection() as conn:
                cursor = conn.cursor(pymysql.cursors.DictCursor)
                
                query = """
                    SELECT id, camera_id, alert_type, description, severity,
                           details_json, timestamp, acknowledged
                    FROM security_alerts 
                    WHERE timestamp >= DATE_SUB(NOW(), INTERVAL ? HOUR)
                """
                params = [hours]
                
                if camera_id:
                    query += " AND camera_id = ?"
                    params.append(camera_id)
                
                if acknowledged is not None:
                    query += " AND acknowledged = ?"
                    params.append(acknowledged)
                
                query += " ORDER BY timestamp DESC"
                cursor.execute(query, params)
                return cursor.fetchall()
                
        except Exception as e:
            self.logger.error(f"Error getting security alerts: {e}")
            return []
    
    def acknowledge_alert(self, alert_id: int) -> bool:
        """Acknowledge security alert"""
        try:
            with self.get_connection() as conn:
                cursor = conn.cursor()
                cursor.execute("""
                    UPDATE security_alerts 
                    SET acknowledged = TRUE, acknowledged_at = NOW()
                    WHERE id = ?
                """, (alert_id,))
                conn.commit()
                return cursor.rowcount > 0
                
        except Exception as e:
            self.logger.error(f"Error acknowledging alert: {e}")
            return False
    
    def get_bi_summary(self, camera_id: int = None, days: int = 7) -> Dict:
        """Get business intelligence summary"""
        try:
            with self.get_connection() as conn:
                cursor = conn.cursor(pymysql.cursors.DictCursor)
                
                since_date = datetime.now() - timedelta(days=days)
                
                # Customer flow analysis
                cursor.execute("""
                    SELECT insight_type, AVG(value) as avg_value, 
                           COUNT(*) as data_points
                    FROM bi_insights 
                    WHERE timestamp >= ? AND insight_type IN ('customer_flow', 'dwell_time', 'engagement')
                """ + (f" AND camera_id = {camera_id}" if camera_id else "") + """
                    GROUP BY insight_type
                """, [since_date])
                
                bi_data = cursor.fetchall()
                
                # Peak hours analysis
                cursor.execute("""
                    SELECT HOUR(timestamp) as hour, COUNT(*) as alert_count
                    FROM security_alerts 
                    WHERE timestamp >= ?
                """ + (f" AND camera_id = {camera_id}" if camera_id else "") + """
                    GROUP BY HOUR(timestamp)
                    ORDER BY alert_count DESC
                    LIMIT 5
                """, [since_date])
                
                peak_hours = cursor.fetchall()
                
                return {
                    'bi_metrics': {row['insight_type']: row['avg_value'] for row in bi_data},
                    'peak_hours': peak_hours,
                    'period_days': days
                }
                
        except Exception as e:
            self.logger.error(f"Error getting BI summary: {e}")
            return {}
    
    def cleanup_old_records(self, days: int = 30) -> int:
        """Clean up old records to manage database size"""
        try:
            with self.get_connection() as conn:
                cursor = conn.cursor()
                
                cutoff_date = datetime.now() - timedelta(days=days)
                
                # Clean up old detections
                tables = ['face_detections', 'object_detections', 'action_detections']
                total_deleted = 0
                
                for table in tables:
                    cursor.execute(f"DELETE FROM {table} WHERE timestamp < ?", (cutoff_date,))
                    total_deleted += cursor.rowcount
                
                # Clean up old BI insights
                cursor.execute("DELETE FROM bi_insights WHERE timestamp < ?", (cutoff_date,))
                total_deleted += cursor.rowcount
                
                conn.commit()
                self.logger.info(f"Cleaned up {total_deleted} old records")
                return total_deleted
                
        except Exception as e:
            self.logger.error(f"Error cleaning up old records: {e}")
            return 0
