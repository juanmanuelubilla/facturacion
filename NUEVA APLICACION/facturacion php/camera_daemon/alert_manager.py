#!/usr/bin/env python3
"""
Alert Manager for Advanced Camera System
Handles security alerts, notifications, and intelligent alert processing
"""

import redis
import json
import logging
import threading
import time
from datetime import datetime, timedelta
from typing import Dict, List, Optional, Any
from dataclasses import dataclass, asdict
from enum import Enum
import requests
import smtplib
from email.mime.text import MIMEText
from email.mime.multipart import MIMEMultipart

class AlertSeverity(Enum):
    LOW = "low"
    MEDIUM = "medium"
    HIGH = "high"
    CRITICAL = "critical"

class AlertType(Enum):
    UNKNOWN_FACE = "unknown_face"
    INTRUSION = "intrusion"
    CROWD_DENSITY = "crowd_density"
    ABANDONED_OBJECT = "abandoned_object"
    VIOLENCE = "violence"
    MOTION_OUTSIDE_HOURS = "motion_outside_hours"
    SYSTEM_ERROR = "system_error"
    STORAGE_FULL = "storage_full"
    CAMERA_OFFLINE = "camera_offline"

@dataclass
class Alert:
    """Alert data structure"""
    id: str
    camera_id: int
    alert_type: AlertType
    severity: AlertSeverity
    title: str
    description: str
    timestamp: datetime
    details: Dict[str, Any]
    acknowledged: bool = False
    acknowledged_by: Optional[str] = None
    acknowledged_at: Optional[datetime] = None
    resolved: bool = False
    resolved_at: Optional[datetime] = None
    metadata: Dict[str, Any] = None

class AlertManager:
    """Advanced alert management system"""
    
    def __init__(self, config: Dict, redis_client: redis.Redis):
        self.config = config
        self.redis_client = redis_client
        self.logger = logging.getLogger("alerts")
        
        # Alert configuration
        self.alert_config = config.get('ALERT_CONFIG', {})
        self.business_hours_only = self.alert_config.get('business_hours_only', True)
        self.alert_cooldown = timedelta(minutes=self.alert_config.get('alert_cooldown_minutes', 5))
        
        # Alert cooldown tracking
        self.last_alerts = {}  # (camera_id, alert_type) -> datetime
        
        # Notification channels
        self.notification_channels = []
        self._setup_notification_channels()
        
        # Alert processing thread
        self.processing_thread = threading.Thread(target=self._alert_processing_loop, daemon=True)
        self.processing_thread.start()
        
        # Statistics
        self.stats = {
            'total_alerts': 0,
            'alerts_by_type': {},
            'alerts_by_severity': {},
            'acknowledged_alerts': 0,
            'resolved_alerts': 0,
            'false_positives': 0
        }
        
        # Alert patterns for intelligent filtering
        self.alert_patterns = self._load_alert_patterns()
    
    def _setup_notification_channels(self):
        """Setup notification channels based on configuration"""
        # Email notifications
        if self.config.get('NOTIFICATION_CONFIG', {}).get('email_enabled', False):
            self.notification_channels.append('email')
        
        # SMS notifications
        if self.config.get('NOTIFICATION_CONFIG', {}).get('sms_enabled', False):
            self.notification_channels.append('sms')
        
        # Webhook notifications
        if self.config.get('NOTIFICATION_CONFIG', {}).get('webhook_enabled', False):
            self.notification_channels.append('webhook')
        
        # Redis notifications (for real-time dashboard)
        self.notification_channels.append('redis')
    
    def _load_alert_patterns(self) -> Dict:
        """Load alert patterns for intelligent filtering"""
        patterns = {
            'unknown_face': {
                'min_confidence': 0.7,
                'min_duration': 2,  # seconds
                'business_hours_multiplier': 0.8,
                'crowd_context_factor': 0.9
            },
            'intrusion': {
                'min_confidence': 0.8,
                'min_duration': 3,
                'business_hours_multiplier': 1.2,
                'crowd_context_factor': 0.7
            },
            'crowd_density': {
                'threshold_people': 10,
                'duration_seconds': 30,
                'area_threshold': 0.7  # 70% of frame area
            },
            'violence': {
                'min_confidence': 0.9,
                'min_duration': 1,
                'require_multiple_detections': True
            }
        }
        
        return patterns
    
    def trigger_alert(self, camera_id: int, alert_type: str, details: Dict, timestamp: datetime = None) -> Optional[str]:
        """Trigger a new alert"""
        try:
            if timestamp is None:
                timestamp = datetime.now()
            
            # Check cooldown
            alert_key = (camera_id, alert_type)
            if alert_key in self.last_alerts:
                time_since_last = timestamp - self.last_alerts[alert_key]
                if time_since_last < self.alert_cooldown:
                    self.logger.debug(f"Alert {alert_type} for camera {camera_id} in cooldown")
                    return None
            
            # Check business hours if required
            if self.business_hours_only and self._is_business_hours(timestamp):
                # Reduce alert priority during business hours for certain types
                if alert_type in ['unknown_face', 'motion_outside_hours']:
                    self.logger.debug(f"Alert {alert_type} suppressed during business hours")
                    return None
            
            # Intelligent alert filtering
            if not self._should_trigger_alert(camera_id, alert_type, details, timestamp):
                return None
            
            # Create alert
            alert = self._create_alert(camera_id, alert_type, details, timestamp)
            
            # Store alert
            alert_id = self._store_alert(alert)
            
            # Update cooldown
            self.last_alerts[alert_key] = timestamp
            
            # Update statistics
            self._update_stats(alert)
            
            # Process notifications
            self._queue_notifications(alert)
            
            self.logger.info(f"Alert triggered: {alert_type} for camera {camera_id}")
            return alert_id
            
        except Exception as e:
            self.logger.error(f"Error triggering alert: {e}")
            return None
    
    def _should_trigger_alert(self, camera_id: int, alert_type: str, details: Dict, timestamp: datetime) -> bool:
        """Intelligent alert filtering"""
        try:
            if alert_type not in self.alert_patterns:
                return True
            
            pattern = self.alert_patterns[alert_type]
            
            # Check confidence
            confidence = details.get('confidence', 0)
            if confidence < pattern.get('min_confidence', 0.5):
                return False
            
            # Check duration (if available)
            duration = details.get('duration', 0)
            if duration < pattern.get('min_duration', 0):
                return False
            
            # Context-aware filtering
            context = self._get_alert_context(camera_id, timestamp)
            
            # Crowd density adjustment
            if 'crowd_context_factor' in pattern:
                crowd_factor = context.get('crowd_density', 0)
                if crowd_factor > 0.7 and alert_type == 'unknown_face':
                    confidence *= pattern['crowd_context_factor']
            
            # Business hours adjustment
            if self._is_business_hours(timestamp) and 'business_hours_multiplier' in pattern:
                confidence *= pattern['business_hours_multiplier']
            
            # Final confidence check
            return confidence >= pattern.get('min_confidence', 0.5)
            
        except Exception as e:
            self.logger.error(f"Error in alert filtering: {e}")
            return True  # Default to triggering alert
    
    def _get_alert_context(self, camera_id: int, timestamp: datetime) -> Dict:
        """Get context for alert decision making"""
        try:
            context = {
                'time_of_day': timestamp.hour,
                'day_of_week': timestamp.weekday(),
                'is_business_hours': self._is_business_hours(timestamp),
                'recent_alerts': 0,
                'crowd_density': 0
            }
            
            # Get recent alerts for this camera
            recent_alerts = self.redis_client.lrange(f"alerts:camera:{camera_id}", 0, 10)
            context['recent_alerts'] = len(recent_alerts)
            
            # Get current people count (if available)
            people_count = self.redis_client.get(f"people_count:camera:{camera_id}")
            if people_count:
                context['crowd_density'] = min(1.0, int(people_count) / 10.0)  # Normalize to 0-1
            
            return context
            
        except Exception as e:
            self.logger.error(f"Error getting alert context: {e}")
            return {}
    
    def _is_business_hours(self, timestamp: datetime) -> bool:
        """Check if timestamp is within business hours"""
        try:
            # Default business hours: 8 AM to 10 PM
            start_time = 8
            end_time = 22
            
            return start_time <= timestamp.hour < end_time
            
        except Exception:
            return True
    
    def _create_alert(self, camera_id: int, alert_type: str, details: Dict, timestamp: datetime) -> Alert:
        """Create alert object"""
        try:
            # Determine severity
            severity = self._determine_severity(alert_type, details)
            
            # Generate title and description
            title, description = self._generate_alert_content(alert_type, details, camera_id)
            
            # Create alert ID
            alert_id = f"alert_{camera_id}_{int(timestamp.timestamp())}"
            
            return Alert(
                id=alert_id,
                camera_id=camera_id,
                alert_type=AlertType(alert_type),
                severity=severity,
                title=title,
                description=description,
                timestamp=timestamp,
                details=details,
                metadata={
                    'context': self._get_alert_context(camera_id, timestamp),
                    'pattern_matched': alert_type in self.alert_patterns
                }
            )
            
        except Exception as e:
            self.logger.error(f"Error creating alert: {e}")
            raise
    
    def _determine_severity(self, alert_type: str, details: Dict) -> AlertSeverity:
        """Determine alert severity based on type and details"""
        severity_map = {
            'violence': AlertSeverity.CRITICAL,
            'intrusion': AlertSeverity.HIGH,
            'abandoned_object': AlertSeverity.HIGH,
            'unknown_face': AlertSeverity.MEDIUM,
            'crowd_density': AlertSeverity.MEDIUM,
            'motion_outside_hours': AlertSeverity.LOW,
            'system_error': AlertSeverity.HIGH,
            'storage_full': AlertSeverity.HIGH,
            'camera_offline': AlertSeverity.MEDIUM
        }
        
        base_severity = severity_map.get(alert_type, AlertSeverity.MEDIUM)
        
        # Adjust severity based on confidence
        confidence = details.get('confidence', 0.5)
        if confidence > 0.9 and base_severity == AlertSeverity.MEDIUM:
            return AlertSeverity.HIGH
        elif confidence < 0.3 and base_severity == AlertSeverity.HIGH:
            return AlertSeverity.MEDIUM
        
        return base_severity
    
    def _generate_alert_content(self, alert_type: str, details: Dict, camera_id: int) -> tuple[str, str]:
        """Generate alert title and description"""
        content_templates = {
            'unknown_face': (
                "Unknown Face Detected",
                f"Camera {camera_id}: Unknown person detected with {details.get('confidence', 0):.1%} confidence"
            ),
            'intrusion': (
                "Potential Intrusion",
                f"Camera {camera_id}: Suspicious activity detected outside business hours"
            ),
            'crowd_density': (
                "High Crowd Density",
                f"Camera {camera_id}: Unusually high number of people detected ({details.get('people_count', 0)})"
            ),
            'violence': (
                "Violence Detected",
                f"Camera {camera_id}: Violent behavior detected with high confidence"
            ),
            'motion_outside_hours': (
                "Motion Outside Hours",
                f"Camera {camera_id}: Motion detected during closed hours"
            ),
            'system_error': (
                "System Error",
                f"Camera {camera_id}: System error detected - {details.get('error', 'Unknown error')}"
            ),
            'storage_full': (
                "Storage Full",
                f"Camera {camera_id}: Storage capacity reached, recording may be affected"
            ),
            'camera_offline': (
                "Camera Offline",
                f"Camera {camera_id}: Camera connection lost"
            )
        }
        
        return content_templates.get(alert_type, (
            "Alert Triggered",
            f"Camera {camera_id}: {alert_type} alert triggered"
        ))
    
    def _store_alert(self, alert: Alert) -> str:
        """Store alert in Redis and database"""
        try:
            # Store in Redis for real-time access
            alert_data = asdict(alert)
            alert_data['timestamp'] = alert.timestamp.isoformat()
            alert_data['alert_type'] = alert.alert_type.value
            alert_data['severity'] = alert.severity.value
            
            # Store in alert list
            self.redis_client.lpush("alerts:active", json.dumps(alert_data))
            self.redis_client.ltrim("alerts:active", 0, 999)  # Keep last 1000 alerts
            
            # Store in camera-specific list
            self.redis_client.lpush(f"alerts:camera:{alert.camera_id}", json.dumps(alert_data))
            self.redis_client.ltrim(f"alerts:camera:{alert.camera_id}", 0, 99)  # Keep last 100 per camera
            
            # Store by alert type
            self.redis_client.lpush(f"alerts:type:{alert.alert_type.value}", json.dumps(alert_data))
            self.redis_client.ltrim(f"alerts:type:{alert.alert_type.value}", 0, 99)
            
            # Set expiration for camera-specific alerts (7 days)
            self.redis_client.expire(f"alerts:camera:{alert.camera_id}", 604800)
            
            return alert.id
            
        except Exception as e:
            self.logger.error(f"Error storing alert: {e}")
            return alert.id
    
    def _queue_notifications(self, alert: Alert):
        """Queue notifications for alert"""
        try:
            notification_data = {
                'alert_id': alert.id,
                'alert_type': alert.alert_type.value,
                'severity': alert.severity.value,
                'title': alert.title,
                'description': alert.description,
                'camera_id': alert.camera_id,
                'timestamp': alert.timestamp.isoformat(),
                'details': alert.details
            }
            
            # Queue for each notification channel
            for channel in self.notification_channels:
                self.redis_client.lpush(f"notifications:{channel}", json.dumps(notification_data))
            
        except Exception as e:
            self.logger.error(f"Error queuing notifications: {e}")
    
    def _alert_processing_loop(self):
        """Background thread for processing notifications"""
        while True:
            try:
                # Process notifications for each channel
                for channel in self.notification_channels:
                    self._process_notifications(channel)
                
                # Sleep before next iteration
                time.sleep(1)
                
            except Exception as e:
                self.logger.error(f"Error in alert processing loop: {e}")
                time.sleep(5)
    
    def _process_notifications(self, channel: str):
        """Process notifications for a specific channel"""
        try:
            # Get notifications from queue
            notifications = self.redis_client.lrange(f"notifications:{channel}", 0, 10)
            
            if notifications:
                for notification_json in notifications:
                    notification = json.loads(notification_json)
                    self._send_notification(channel, notification)
                
                # Remove processed notifications
                self.redis_client.ltrim(f"notifications:{channel}", len(notifications), -1)
                
        except Exception as e:
            self.logger.error(f"Error processing {channel} notifications: {e}")
    
    def _send_notification(self, channel: str, notification: Dict):
        """Send notification through specific channel"""
        try:
            if channel == 'email':
                self._send_email_notification(notification)
            elif channel == 'sms':
                self._send_sms_notification(notification)
            elif channel == 'webhook':
                self._send_webhook_notification(notification)
            elif channel == 'redis':
                self._send_redis_notification(notification)
                
        except Exception as e:
            self.logger.error(f"Error sending {channel} notification: {e}")
    
    def _send_email_notification(self, notification: Dict):
        """Send email notification"""
        try:
            email_config = self.config.get('NOTIFICATION_CONFIG', {}).get('email', {})
            
            if not email_config.get('enabled', False):
                return
            
            # Create email
            msg = MIMEMultipart()
            msg['From'] = email_config['from']
            msg['To'] = email_config['to']
            msg['Subject'] = f"Security Alert: {notification['title']}"
            
            body = f"""
            Alert Details:
            - Type: {notification['alert_type']}
            - Severity: {notification['severity']}
            - Camera: {notification['camera_id']}
            - Time: {notification['timestamp']}
            - Description: {notification['description']}
            
            Additional Details:
            {json.dumps(notification['details'], indent=2)}
            """
            
            msg.attach(MIMEText(body, 'plain'))
            
            # Send email
            server = smtplib.SMTP(email_config['smtp_server'], email_config['smtp_port'])
            server.starttls()
            server.login(email_config['username'], email_config['password'])
            server.send_message(msg)
            server.quit()
            
            self.logger.info(f"Email notification sent for alert {notification['alert_id']}")
            
        except Exception as e:
            self.logger.error(f"Error sending email notification: {e}")
    
    def _send_sms_notification(self, notification: Dict):
        """Send SMS notification"""
        try:
            sms_config = self.config.get('NOTIFICATION_CONFIG', {}).get('sms', {})
            
            if not sms_config.get('enabled', False):
                return
            
            # This would integrate with an SMS service like Twilio
            # For now, just log the notification
            self.logger.info(f"SMS notification would be sent for alert {notification['alert_id']}")
            
        except Exception as e:
            self.logger.error(f"Error sending SMS notification: {e}")
    
    def _send_webhook_notification(self, notification: Dict):
        """Send webhook notification"""
        try:
            webhook_config = self.config.get('NOTIFICATION_CONFIG', {}).get('webhook', {})
            
            if not webhook_config.get('enabled', False):
                return
            
            webhook_url = webhook_config['url']
            
            # Send webhook
            response = requests.post(
                webhook_url,
                json=notification,
                timeout=10,
                headers={'Content-Type': 'application/json'}
            )
            
            if response.status_code == 200:
                self.logger.info(f"Webhook notification sent for alert {notification['alert_id']}")
            else:
                self.logger.error(f"Webhook notification failed: {response.status_code}")
                
        except Exception as e:
            self.logger.error(f"Error sending webhook notification: {e}")
    
    def _send_redis_notification(self, notification: Dict):
        """Send Redis notification for real-time dashboard"""
        try:
            # Store in Redis for dashboard
            self.redis_client.setex(
                f"latest_alert:{notification['camera_id']}",
                3600,  # 1 hour
                json.dumps(notification)
            )
            
            # Publish to Redis pub/sub for real-time updates
            self.redis_client.publish("alerts:realtime", json.dumps(notification))
            
        except Exception as e:
            self.logger.error(f"Error sending Redis notification: {e}")
    
    def acknowledge_alert(self, alert_id: str, acknowledged_by: str = None) -> bool:
        """Acknowledge an alert"""
        try:
            # Update alert in Redis
            alerts = self.redis_client.lrange("alerts:active", 0, -1)
            
            for i, alert_json in enumerate(alerts):
                alert_data = json.loads(alert_json)
                if alert_data['id'] == alert_id:
                    alert_data['acknowledged'] = True
                    alert_data['acknowledged_by'] = acknowledged_by
                    alert_data['acknowledged_at'] = datetime.now().isoformat()
                    
                    # Update in Redis list
                    self.redis_client.lset("alerts:active", i, json.dumps(alert_data))
                    
                    # Update statistics
                    self.stats['acknowledged_alerts'] += 1
                    
                    self.logger.info(f"Alert {alert_id} acknowledged by {acknowledged_by}")
                    return True
            
            return False
            
        except Exception as e:
            self.logger.error(f"Error acknowledging alert: {e}")
            return False
    
    def get_active_alerts(self, camera_id: int = None, alert_type: str = None, 
                         severity: str = None, limit: int = 100) -> List[Dict]:
        """Get active alerts with filtering"""
        try:
            alerts = []
            alert_data_list = self.redis_client.lrange("alerts:active", 0, -1)
            
            for alert_json in alert_data_list:
                alert_data = json.loads(alert_json)
                
                # Apply filters
                if camera_id and alert_data['camera_id'] != camera_id:
                    continue
                
                if alert_type and alert_data['alert_type'] != alert_type:
                    continue
                
                if severity and alert_data['severity'] != severity:
                    continue
                
                alerts.append(alert_data)
            
            # Sort by timestamp (newest first)
            alerts.sort(key=lambda x: x['timestamp'], reverse=True)
            
            return alerts[:limit]
            
        except Exception as e:
            self.logger.error(f"Error getting active alerts: {e}")
            return []
    
    def get_alert_statistics(self) -> Dict:
        """Get alert statistics"""
        try:
            # Get recent alerts for statistics
            recent_alerts = self.redis_client.lrange("alerts:active", 0, -1)
            
            stats = {
                'total_alerts': len(recent_alerts),
                'alerts_by_type': {},
                'alerts_by_severity': {},
                'acknowledged_alerts': 0,
                'unacknowledged_alerts': 0,
                'recent_alerts': []
            }
            
            for alert_json in recent_alerts:
                alert_data = json.loads(alert_json)
                
                # Count by type
                alert_type = alert_data['alert_type']
                stats['alerts_by_type'][alert_type] = stats['alerts_by_type'].get(alert_type, 0) + 1
                
                # Count by severity
                severity = alert_data['severity']
                stats['alerts_by_severity'][severity] = stats['alerts_by_severity'].get(severity, 0) + 1
                
                # Count acknowledged vs unacknowledged
                if alert_data.get('acknowledged', False):
                    stats['acknowledged_alerts'] += 1
                else:
                    stats['unacknowledged_alerts'] += 1
            
            # Get recent alerts (last 10)
            recent_alerts = sorted(recent_alerts, 
                                 key=lambda x: json.loads(x)['timestamp'], 
                                 reverse=True)[:10]
            stats['recent_alerts'] = [json.loads(alert) for alert in recent_alerts]
            
            return stats
            
        except Exception as e:
            self.logger.error(f"Error getting alert statistics: {e}")
            return {}
    
    def _update_stats(self, alert: Alert):
        """Update alert statistics"""
        try:
            self.stats['total_alerts'] += 1
            
            alert_type = alert.alert_type.value
            self.stats['alerts_by_type'][alert_type] = self.stats['alerts_by_type'].get(alert_type, 0) + 1
            
            severity = alert.severity.value
            self.stats['alerts_by_severity'][severity] = self.stats['alerts_by_severity'].get(severity, 0) + 1
            
        except Exception as e:
            self.logger.error(f"Error updating stats: {e}")
    
    def cleanup_old_alerts(self, days: int = 7) -> int:
        """Clean up old alerts"""
        try:
            cutoff_time = datetime.now() - timedelta(days=days)
            cutoff_timestamp = cutoff_time.timestamp()
            
            alerts = self.redis_client.lrange("alerts:active", 0, -1)
            alerts_to_keep = []
            
            for alert_json in alerts:
                alert_data = json.loads(alert_json)
                alert_timestamp = datetime.fromisoformat(alert_data['timestamp']).timestamp()
                
                if alert_timestamp > cutoff_timestamp:
                    alerts_to_keep.append(alert_json)
            
            # Update Redis list
            self.redis_client.delete("alerts:active")
            if alerts_to_keep:
                self.redis_client.lpush("alerts:active", *alerts_to_keep)
            
            cleaned_count = len(alerts) - len(alerts_to_keep)
            self.logger.info(f"Cleaned up {cleaned_count} old alerts")
            
            return cleaned_count
            
        except Exception as e:
            self.logger.error(f"Error cleaning up old alerts: {e}")
            return 0
