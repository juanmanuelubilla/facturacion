#!/usr/bin/env python3
"""
Advanced Camera Daemon with AI Analysis
Real-time camera monitoring, recording, and intelligent analysis
"""

import asyncio
import cv2
import numpy as np
import redis
import json
import logging
import signal
import sys
from datetime import datetime, timedelta
from pathlib import Path
from typing import Dict, List, Optional
import threading
import time
import subprocess
from concurrent.futures import ThreadPoolExecutor
import psutil

from config import *
from ai_analyzer import AIAnalyzer, DetectionResult
from database_manager import DatabaseManager
from recording_manager import RecordingManager
from alert_manager import AlertManager
from bi_analyzer import BIAnalyzer

class CameraStream:
    """Individual camera stream handler"""
    
    def __init__(self, camera_config: Dict, ai_analyzer: AIAnalyzer, 
                 recording_manager: RecordingManager, alert_manager: AlertManager):
        self.camera_config = camera_config
        self.ai_analyzer = ai_analyzer
        self.recording_manager = recording_manager
        self.alert_manager = alert_manager
        
        self.camera_id = camera_config['id']
        self.rtsp_url = self._build_rtsp_url(camera_config)
        
        self.cap = None
        self.is_running = False
        self.recording_active = False
        self.last_frame = None
        self.frame_count = 0
        self.analysis_interval = 5  # Analyze every N frames
        
        # Statistics
        self.stats = {
            'frames_processed': 0,
            'faces_detected': 0,
            'objects_detected': 0,
            'actions_detected': 0,
            'alerts_triggered': 0,
            'recording_events': 0,
            'start_time': datetime.now()
        }
        
        self.logger = logging.getLogger(f"camera.{self.camera_id}")
    
    def _build_rtsp_url(self, camera_config: Dict) -> str:
        """Build RTSP URL from camera configuration"""
        url = f"rtsp://"
        
        if camera_config.get('usuario'):
            url += f"{camera_config['usuario']}"
            if camera_config.get('password'):
                url += f":{camera_config['password']}"
            url += "@"
        
        url += f"{camera_config['ip']}:{camera_config['puerto']}{camera_config['ruta_stream']}"
        return url
    
    def start(self):
        """Start camera stream processing"""
        try:
            self.logger.info(f"Starting camera stream: {self.rtsp_url}")
            
            # Initialize video capture
            self.cap = cv2.VideoCapture(self.rtsp_url)
            self.cap.set(cv2.CAP_PROP_FPS, CAMERA_CONFIG['recording_fps'])
            
            if not self.cap.isOpened():
                self.logger.error(f"Failed to open camera stream: {self.rtsp_url}")
                return False
            
            self.is_running = True
            self.logger.info(f"Camera stream started successfully")
            return True
            
        except Exception as e:
            self.logger.error(f"Error starting camera stream: {e}")
            return False
    
    def stop(self):
        """Stop camera stream processing"""
        self.is_running = False
        if self.cap:
            self.cap.release()
            self.cap = None
        
        self.logger.info(f"Camera stream stopped")
    
    def process_stream(self):
        """Main processing loop for camera stream"""
        if not self.is_running or not self.cap:
            return
        
        try:
            ret, frame = self.cap.read()
            if not ret:
                self.logger.warning("Failed to read frame from camera")
                return
            
            self.last_frame = frame.copy()
            self.frame_count += 1
            self.stats['frames_processed'] += 1
            
            # AI Analysis (every N frames to save resources)
            if self.frame_count % self.analysis_interval == 0:
                self._analyze_frame(frame)
            
            # Recording management
            self._handle_recording(frame)
            
        except Exception as e:
            self.logger.error(f"Error processing frame: {e}")
    
    def _analyze_frame(self, frame: np.ndarray):
        """Perform AI analysis on frame"""
        try:
            analysis_results = self.ai_analyzer.analyze_frame(frame, self.camera_id)
            
            # Update statistics
            self.stats['faces_detected'] += len(analysis_results['faces'])
            self.stats['objects_detected'] += len(analysis_results['objects'])
            self.stats['actions_detected'] += len(analysis_results['actions'])
            
            # Handle alerts
            if analysis_results['analysis_summary']['security_alerts']:
                self._handle_security_alerts(analysis_results)
            
            # Store analysis results
            self._store_analysis_results(analysis_results)
            
        except Exception as e:
            self.logger.error(f"Error in frame analysis: {e}")
    
    def _handle_recording(self, frame: np.ndarray):
        """Handle recording logic"""
        try:
            # Check if recording should be active
            should_record = self._should_record()
            
            if should_record and not self.recording_active:
                self._start_recording()
            elif not should_record and self.recording_active:
                self._stop_recording()
            
            # Record frame if active
            if self.recording_active:
                self.recording_manager.write_frame(self.camera_id, frame)
                
        except Exception as e:
            self.logger.error(f"Error in recording handling: {e}")
    
    def _should_record(self) -> bool:
        """Determine if recording should be active"""
        # Check business hours
        if self._is_outside_business_hours():
            return False
        
        # Check for motion/people
        if self.last_frame is not None:
            # Simple motion detection
            gray = cv2.cvtColor(self.last_frame, cv2.COLOR_BGR2GRAY)
            if hasattr(self, 'prev_gray'):
                diff = cv2.absdiff(gray, self.prev_gray)
                motion = np.mean(diff) > 30
                self.prev_gray = gray
                return motion
            else:
                self.prev_gray = gray
        
        return False
    
    def _is_outside_business_hours(self) -> bool:
        """Check if current time is outside business hours"""
        try:
            now = datetime.now().time()
            start_time = datetime.strptime("08:00", "%H:%M").time()
            end_time = datetime.strptime("22:00", "%H:%M").time()
            
            return now < start_time or now > end_time
            
        except Exception:
            return False
    
    def _start_recording(self):
        """Start recording"""
        try:
            self.recording_active = True
            self.recording_manager.start_recording(self.camera_id, self.camera_config)
            self.stats['recording_events'] += 1
            self.logger.info("Recording started")
            
        except Exception as e:
            self.logger.error(f"Error starting recording: {e}")
    
    def _stop_recording(self):
        """Stop recording"""
        try:
            self.recording_active = False
            self.recording_manager.stop_recording(self.camera_id)
            self.logger.info("Recording stopped")
            
        except Exception as e:
            self.logger.error(f"Error stopping recording: {e}")
    
    def _handle_security_alerts(self, analysis_results: Dict):
        """Handle security alerts from analysis"""
        try:
            alerts = analysis_results['analysis_summary']['security_alerts']
            
            for alert in alerts:
                self.alert_manager.trigger_alert(
                    camera_id=self.camera_id,
                    alert_type=alert,
                    details=analysis_results,
                    timestamp=datetime.now()
                )
                self.stats['alerts_triggered'] += 1
                
        except Exception as e:
            self.logger.error(f"Error handling security alerts: {e}")
    
    def _store_analysis_results(self, analysis_results: Dict):
        """Store analysis results in database"""
        try:
            # This would store in database via DatabaseManager
            # For now, just log the results
            if analysis_results['faces']:
                self.logger.debug(f"Faces detected: {len(analysis_results['faces'])}")
            if analysis_results['actions']:
                self.logger.debug(f"Actions detected: {analysis_results['actions']}")
                
        except Exception as e:
            self.logger.error(f"Error storing analysis results: {e}")
    
    def get_stats(self) -> Dict:
        """Get camera statistics"""
        uptime = datetime.now() - self.stats['start_time']
        
        return {
            **self.stats,
            'uptime_seconds': uptime.total_seconds(),
            'fps': self.stats['frames_processed'] / uptime.total_seconds() if uptime.total_seconds() > 0 else 0,
            'is_recording': self.recording_active,
            'last_frame_time': datetime.now()
        }

class AdvancedCameraDaemon:
    """Main daemon class for advanced camera system"""
    
    def __init__(self):
        self.logger = logging.getLogger("daemon")
        
        # Initialize components
        self.db_manager = DatabaseManager(DB_CONFIG)
        self.redis_client = redis.Redis(**REDIS_CONFIG)
        self.ai_analyzer = AIAnalyzer(config)
        self.recording_manager = RecordingManager(config)
        self.alert_manager = AlertManager(config, self.redis_client)
        self.bi_analyzer = BIAnalyzer(config, self.db_manager)
        
        # Camera streams
        self.camera_streams = {}
        self.executor = ThreadPoolExecutor(max_workers=PERFORMANCE_CONFIG['max_workers'])
        
        # Daemon state
        self.is_running = False
        self.start_time = datetime.now()
        
        # Statistics
        self.global_stats = {
            'cameras_active': 0,
            'total_faces_detected': 0,
            'total_objects_detected': 0,
            'total_actions_detected': 0,
            'total_alerts_triggered': 0,
            'total_recording_events': 0,
            'daemon_uptime': 0
        }
        
        # Setup signal handlers
        signal.signal(signal.SIGINT, self._signal_handler)
        signal.signal(signal.SIGTERM, self._signal_handler)
    
    def _signal_handler(self, signum, frame):
        """Handle shutdown signals"""
        self.logger.info(f"Received signal {signum}, shutting down...")
        self.stop()
    
    def start(self):
        """Start the daemon"""
        try:
            self.logger.info("Starting Advanced Camera Daemon...")
            
            # Load configuration from database
            self._load_configuration()
            
            # Initialize camera streams
            self._initialize_cameras()
            
            # Start main processing loop
            self.is_running = True
            self.start_time = datetime.now()
            
            self.logger.info(f"Daemon started with {len(self.camera_streams)} cameras")
            
            # Main loop
            self._main_loop()
            
        except Exception as e:
            self.logger.error(f"Error starting daemon: {e}")
            self.stop()
    
    def stop(self):
        """Stop the daemon"""
        self.logger.info("Stopping Advanced Camera Daemon...")
        
        self.is_running = False
        
        # Stop all camera streams
        for camera_stream in self.camera_streams.values():
            camera_stream.stop()
        
        # Shutdown executor
        self.executor.shutdown(wait=True)
        
        # Cleanup
        self.recording_manager.cleanup()
        self.ai_analyzer.save_faces_database()
        
        self.logger.info("Daemon stopped")
        sys.exit(0)
    
    def _load_configuration(self):
        """Load configuration from database"""
        try:
            cameras = self.db_manager.get_active_cameras()
            camera_config = self.db_manager.get_camera_config()
            
            self.logger.info(f"Loaded {len(cameras)} cameras and configuration")
            
        except Exception as e:
            self.logger.error(f"Error loading configuration: {e}")
            raise
    
    def _initialize_cameras(self):
        """Initialize camera streams"""
        try:
            cameras = self.db_manager.get_active_cameras()
            
            for camera in cameras:
                camera_stream = CameraStream(
                    camera, 
                    self.ai_analyzer, 
                    self.recording_manager, 
                    self.alert_manager
                )
                
                if camera_stream.start():
                    self.camera_streams[camera['id']] = camera_stream
                    self.logger.info(f"Camera {camera['id']} initialized")
                else:
                    self.logger.error(f"Failed to initialize camera {camera['id']}")
            
            self.global_stats['cameras_active'] = len(self.camera_streams)
            
        except Exception as e:
            self.logger.error(f"Error initializing cameras: {e}")
            raise
    
    def _main_loop(self):
        """Main processing loop"""
        self.logger.info("Entering main processing loop")
        
        while self.is_running:
            try:
                # Process all camera streams
                futures = []
                for camera_stream in self.camera_streams.values():
                    future = self.executor.submit(camera_stream.process_stream)
                    futures.append(future)
                
                # Wait for all frames to be processed
                for future in futures:
                    future.result(timeout=1.0)
                
                # Update global statistics
                self._update_global_stats()
                
                # Process Redis events
                self._process_redis_events()
                
                # Generate BI insights
                self._generate_bi_insights()
                
                # Cleanup old recordings
                self._cleanup_old_recordings()
                
                # Sleep to prevent high CPU usage
                time.sleep(0.033)  # ~30 FPS
                
            except KeyboardInterrupt:
                break
            except Exception as e:
                self.logger.error(f"Error in main loop: {e}")
                time.sleep(1)
    
    def _update_global_stats(self):
        """Update global statistics"""
        try:
            total_faces = 0
            total_objects = 0
            total_actions = 0
            total_alerts = 0
            total_recordings = 0
            
            for camera_stream in self.camera_streams.values():
                stats = camera_stream.get_stats()
                total_faces += stats['faces_detected']
                total_objects += stats['objects_detected']
                total_actions += stats['actions_detected']
                total_alerts += stats['alerts_triggered']
                total_recordings += stats['recording_events']
            
            self.global_stats.update({
                'total_faces_detected': total_faces,
                'total_objects_detected': total_objects,
                'total_actions_detected': total_actions,
                'total_alerts_triggered': total_alerts,
                'total_recording_events': total_recordings,
                'daemon_uptime': (datetime.now() - self.start_time).total_seconds()
            })
            
        except Exception as e:
            self.logger.error(f"Error updating global stats: {e}")
    
    def _process_redis_events(self):
        """Process events from Redis queue"""
        try:
            # Check for sale events
            sale_events = self.redis_client.lrange("camera_events:sales", 0, 10)
            if sale_events:
                for event in sale_events:
                    self._handle_sale_event(json.loads(event))
                self.redis_client.ltrim("camera_events:sales", len(sale_events), -1)
            
            # Check for manual recording events
            recording_events = self.redis_client.lrange("camera_events:recording", 0, 10)
            if recording_events:
                for event in recording_events:
                    self._handle_recording_event(json.loads(event))
                self.redis_client.ltrim("camera_events:recording", len(recording_events), -1)
                
        except Exception as e:
            self.logger.error(f"Error processing Redis events: {e}")
    
    def _handle_sale_event(self, event: Dict):
        """Handle sale event - trigger recording"""
        try:
            self.logger.info(f"Handling sale event: {event}")
            
            # Start recording on all active cameras
            for camera_stream in self.camera_streams.values():
                camera_stream._start_recording()
                
                # Schedule stop recording after configured duration
                duration = RECORDING_CONFIG.get('segment_duration_minutes', 5) * 60
                threading.Timer(duration, camera_stream._stop_recording).start()
                
        except Exception as e:
            self.logger.error(f"Error handling sale event: {e}")
    
    def _handle_recording_event(self, event: Dict):
        """Handle manual recording event"""
        try:
            camera_id = event.get('camera_id')
            action = event.get('action')  # 'start' or 'stop'
            
            if camera_id in self.camera_streams:
                if action == 'start':
                    self.camera_streams[camera_id]._start_recording()
                elif action == 'stop':
                    self.camera_streams[camera_id]._stop_recording()
                    
        except Exception as e:
            self.logger.error(f"Error handling recording event: {e}")
    
    def _generate_bi_insights(self):
        """Generate business intelligence insights"""
        try:
            # Generate insights every 5 minutes
            if int(time.time()) % 300 == 0:
                insights = self.bi_analyzer.generate_insights(self.camera_streams)
                
                # Store insights in Redis for dashboard
                self.redis_client.setex(
                    "bi_insights:latest", 
                    3600,  # 1 hour
                    json.dumps(insights, default=str)
                )
                
        except Exception as e:
            self.logger.error(f"Error generating BI insights: {e}")
    
    def _cleanup_old_recordings(self):
        """Clean up old recordings based on storage limits"""
        try:
            # Run cleanup every hour
            if int(time.time()) % 3600 == 0:
                self.recording_manager.cleanup_old_recordings()
                
        except Exception as e:
            self.logger.error(f"Error in cleanup: {e}")
    
    def get_status(self) -> Dict:
        """Get daemon status"""
        return {
            'is_running': self.is_running,
            'uptime_seconds': (datetime.now() - self.start_time).total_seconds(),
            'cameras_active': len(self.camera_streams),
            'global_stats': self.global_stats,
            'system_info': {
                'cpu_percent': psutil.cpu_percent(),
                'memory_percent': psutil.virtual_memory().percent,
                'disk_usage': psutil.disk_usage('/').percent
            }
        }

def main():
    """Main entry point"""
    # Setup logging
    logging.basicConfig(
        level=LOG_CONFIG['level'],
        format=LOG_CONFIG['format'],
        handlers=[
            logging.StreamHandler(),
            logging.FileHandler(LOGS_DIR / "daemon.log")
        ]
    )
    
    # Create necessary directories
    for directory in [VIDEOS_DIR, FACES_DIR, LOGS_DIR, MODELS_DIR]:
        directory.mkdir(parents=True, exist_ok=True)
    
    # Start daemon
    daemon = AdvancedCameraDaemon()
    daemon.start()

if __name__ == "__main__":
    main()
