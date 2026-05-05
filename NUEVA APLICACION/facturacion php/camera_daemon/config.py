#!/usr/bin/env python3
"""
Configuration file for Advanced Camera Daemon
"""

import os
from pathlib import Path

# Paths
BASE_DIR = Path(__file__).parent
PROJECT_ROOT = BASE_DIR.parent
VIDEOS_DIR = PROJECT_ROOT / "videos"
FACES_DIR = BASE_DIR / "faces"
LOGS_DIR = BASE_DIR / "logs"
MODELS_DIR = BASE_DIR / "models"

# Database Configuration
DB_CONFIG = {
    'host': 'localhost',
    'user': 'facturacion',
    'password': 'facturacion',
    'database': 'facturacion',
    'charset': 'utf8mb4'
}

# Redis Configuration
REDIS_CONFIG = {
    'host': 'localhost',
    'port': 6379,
    'db': 0,
    'decode_responses': True
}

# Camera Configuration
CAMERA_CONFIG = {
    'max_concurrent_streams': 8,
    'recording_fps': 15,
    'detection_fps': 5,
    'motion_threshold': 0.3,
    'face_confidence': 0.6,
    'object_confidence': 0.5
}

# AI Models
AI_MODELS = {
    'face_detector': 'models/face_detector.tflite',
    'face_recognition': 'models/face_recognition.pkl',
    'object_detector': 'models/yolov8n.pt',
    'pose_estimator': 'models/pose_landmarker.task',
    'action_classifier': 'models/action_classifier.pth'
}

# Recording Configuration
RECORDING_CONFIG = {
    'max_storage_gb': 100,
    'cleanup_days': 30,
    'segment_duration_minutes': 5,
    'quality_presets': {
        'low': {'resolution': '640x480', 'bitrate': '500k'},
        'medium': {'resolution': '1280x720', 'bitrate': '2000k'},
        'high': {'resolution': '1920x1080', 'bitrate': '5000k'}
    }
}

# Analysis Configuration
ANALYSIS_CONFIG = {
    'people_counting': True,
    'face_recognition': True,
    'action_detection': True,
    'object_detection': True,
    'emotion_analysis': True,
    'behavior_tracking': True,
    'dwell_time_analysis': True
}

# Alert Configuration
ALERT_CONFIG = {
    'unknown_face_alert': True,
    'intrusion_alert': True,
    'crowd_density_alert': True,
    'abandoned_object_alert': True,
    'violence_detection': True,
    'business_hours_only': True,
    'alert_cooldown_minutes': 5
}

# Business Intelligence
BI_CONFIG = {
    'customer_journey_tracking': True,
    'heatmap_generation': True,
    'conversion_analysis': True,
    'queue_analysis': True,
    'staff_performance': True
}

# Logging Configuration
LOG_CONFIG = {
    'level': 'INFO',
    'rotation': '1 day',
    'retention': '30 days',
    'format': '{time:YYYY-MM-DD HH:mm:ss} | {level} | {name}:{function}:{line} | {message}'
}

# API Configuration
API_CONFIG = {
    'host': '0.0.0.0',
    'port': 8081,
    'debug': False,
    'cors_enabled': True
}

# Performance Configuration
PERFORMANCE_CONFIG = {
    'max_workers': 4,
    'batch_size': 32,
    'gpu_acceleration': True,
    'memory_limit_gb': 4
}
