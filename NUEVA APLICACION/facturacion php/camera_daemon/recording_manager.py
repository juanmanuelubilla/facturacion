#!/usr/bin/env python3
"""
Recording Manager for Advanced Camera System
Handles video recording, storage management, and FFmpeg operations
"""

import subprocess
import os
import threading
import time
import logging
from datetime import datetime, timedelta
from pathlib import Path
from typing import Dict, Optional, List
from queue import Queue
import cv2
import ffmpeg
import psutil

class RecordingSession:
    """Individual recording session for a camera"""
    
    def __init__(self, camera_id: int, camera_config: Dict, output_path: Path):
        self.camera_id = camera_id
        self.camera_config = camera_config
        self.output_path = output_path
        self.start_time = datetime.now()
        
        self.process = None
        self.is_recording = False
        self.frame_count = 0
        self.file_size = 0
        
        self.logger = logging.getLogger(f"recording.{camera_id}")
    
    def start(self) -> bool:
        """Start recording session"""
        try:
            # Build FFmpeg command
            rtsp_url = self._build_rtsp_url()
            output_file = self.output_path / f"camera_{self.camera_id}_{self.start_time.strftime('%Y%m%d_%H%M%S')}.mp4"
            
            # FFmpeg command for RTSP to MP4 conversion
            cmd = [
                'ffmpeg',
                '-rtsp_transport', 'tcp',
                '-i', rtsp_url,
                '-c:v', 'libx264',
                '-preset', 'fast',
                '-crf', '23',
                '-c:a', 'aac',
                '-r', '15',  # 15 FPS
                '-segment_time', '300',  # 5 minutes segments
                '-segment_format', 'mp4',
                '-f', 'segment',
                '-segment_list', 'none',
                str(output_file).replace('.mp4', '_%03d.mp4')
            ]
            
            self.process = subprocess.Popen(
                cmd,
                stdout=subprocess.PIPE,
                stderr=subprocess.PIPE,
                stdin=subprocess.PIPE
            )
            
            self.is_recording = True
            self.logger.info(f"Recording started: {output_file}")
            return True
            
        except Exception as e:
            self.logger.error(f"Error starting recording: {e}")
            return False
    
    def stop(self) -> bool:
        """Stop recording session"""
        try:
            if self.process and self.is_recording:
                self.process.terminate()
                self.process.wait(timeout=10)
                self.is_recording = False
                
                duration = datetime.now() - self.start_time
                self.logger.info(f"Recording stopped. Duration: {duration}")
                return True
                
        except Exception as e:
            self.logger.error(f"Error stopping recording: {e}")
            if self.process:
                self.process.kill()
            return False
    
    def _build_rtsp_url(self) -> str:
        """Build RTSP URL from camera configuration"""
        url = f"rtsp://"
        
        if self.camera_config.get('usuario'):
            url += f"{self.camera_config['usuario']}"
            if self.camera_config.get('password'):
                url += f":{self.camera_config['password']}"
            url += "@"
        
        url += f"{self.camera_config['ip']}:{self.camera_config['puerto']}{self.camera_config['ruta_stream']}"
        return url

class RecordingManager:
    """Manager for all recording operations"""
    
    def __init__(self, config: Dict):
        self.config = config
        self.logger = logging.getLogger("recording")
        
        # Paths
        self.videos_dir = Path(config.get('VIDEOS_DIR', '/home/pi/facturacion_php/videos'))
        self.videos_dir.mkdir(parents=True, exist_ok=True)
        
        # Recording sessions
        self.recording_sessions = {}  # camera_id -> RecordingSession
        
        # Frame queue for writing
        self.frame_queues = {}  # camera_id -> Queue
        self.writer_threads = {}  # camera_id -> Thread
        
        # Storage management
        self.max_storage_gb = config.get('RECORDING_CONFIG', {}).get('max_storage_gb', 100)
        self.cleanup_days = config.get('RECORDING_CONFIG', {}).get('cleanup_days', 30)
        
        # Statistics
        self.stats = {
            'total_recordings': 0,
            'total_duration': 0,
            'storage_used_gb': 0,
            'active_recordings': 0,
            'cleanup_runs': 0
        }
        
        # Start cleanup thread
        self.cleanup_thread = threading.Thread(target=self._cleanup_loop, daemon=True)
        self.cleanup_thread.start()
    
    def start_recording(self, camera_id: int, camera_config: Dict) -> bool:
        """Start recording for a specific camera"""
        try:
            if camera_id in self.recording_sessions:
                self.logger.warning(f"Recording already active for camera {camera_id}")
                return False
            
            # Create recording session
            session = RecordingSession(camera_id, camera_config, self.videos_dir)
            
            if session.start():
                self.recording_sessions[camera_id] = session
                self.stats['active_recordings'] += 1
                self.stats['total_recordings'] += 1
                
                self.logger.info(f"Recording started for camera {camera_id}")
                return True
            else:
                self.logger.error(f"Failed to start recording for camera {camera_id}")
                return False
                
        except Exception as e:
            self.logger.error(f"Error starting recording for camera {camera_id}: {e}")
            return False
    
    def stop_recording(self, camera_id: int) -> bool:
        """Stop recording for a specific camera"""
        try:
            if camera_id not in self.recording_sessions:
                self.logger.warning(f"No active recording for camera {camera_id}")
                return False
            
            session = self.recording_sessions[camera_id]
            
            if session.stop():
                del self.recording_sessions[camera_id]
                self.stats['active_recordings'] -= 1
                
                # Update duration
                duration = datetime.now() - session.start_time
                self.stats['total_duration'] += duration.total_seconds()
                
                self.logger.info(f"Recording stopped for camera {camera_id}")
                return True
            else:
                self.logger.error(f"Failed to stop recording for camera {camera_id}")
                return False
                
        except Exception as e:
            self.logger.error(f"Error stopping recording for camera {camera_id}: {e}")
            return False
    
    def write_frame(self, camera_id: int, frame):
        """Write frame to active recording"""
        try:
            if camera_id not in self.recording_sessions:
                return
            
            session = self.recording_sessions[camera_id]
            if session.is_recording:
                session.frame_count += 1
                # Frame writing is handled by FFmpeg process
                
        except Exception as e:
            self.logger.error(f"Error writing frame for camera {camera_id}: {e}")
    
    def get_recording_status(self, camera_id: int = None) -> Dict:
        """Get recording status"""
        if camera_id:
            if camera_id in self.recording_sessions:
                session = self.recording_sessions[camera_id]
                return {
                    'camera_id': camera_id,
                    'is_recording': session.is_recording,
                    'start_time': session.start_time,
                    'duration_seconds': (datetime.now() - session.start_time).total_seconds(),
                    'frame_count': session.frame_count
                }
            else:
                return {'camera_id': camera_id, 'is_recording': False}
        else:
            return {
                'active_recordings': len(self.recording_sessions),
                'recording_sessions': {
                    cid: self.get_recording_status(cid) 
                    for cid in self.recording_sessions.keys()
                }
            }
    
    def get_storage_info(self) -> Dict:
        """Get storage information"""
        try:
            # Calculate total storage used
            total_size = 0
            for video_file in self.videos_dir.rglob("*.mp4"):
                total_size += video_file.stat().st_size
            
            storage_gb = total_size / (1024 ** 3)
            
            # Update stats
            self.stats['storage_used_gb'] = storage_gb
            
            return {
                'storage_used_gb': storage_gb,
                'storage_available_gb': max(0, self.max_storage_gb - storage_gb),
                'storage_usage_percent': (storage_gb / self.max_storage_gb) * 100,
                'total_files': len(list(self.videos_dir.rglob("*.mp4"))),
                'oldest_file': self._get_oldest_file_date(),
                'newest_file': self._get_newest_file_date()
            }
            
        except Exception as e:
            self.logger.error(f"Error getting storage info: {e}")
            return {}
    
    def _get_oldest_file_date(self) -> Optional[datetime]:
        """Get date of oldest video file"""
        try:
            files = list(self.videos_dir.rglob("*.mp4"))
            if files:
                oldest_file = min(files, key=lambda f: f.stat().st_mtime)
                return datetime.fromtimestamp(oldest_file.stat().st_mtime)
        except Exception:
            pass
        return None
    
    def _get_newest_file_date(self) -> Optional[datetime]:
        """Get date of newest video file"""
        try:
            files = list(self.videos_dir.rglob("*.mp4"))
            if files:
                newest_file = max(files, key=lambda f: f.stat().st_mtime)
                return datetime.fromtimestamp(newest_file.stat().st_mtime)
        except Exception:
            pass
        return None
    
    def cleanup_old_recordings(self) -> int:
        """Clean up old recordings based on storage limits"""
        try:
            files_deleted = 0
            space_freed = 0
            
            # Get all video files with their stats
            video_files = []
            for video_file in self.videos_dir.rglob("*.mp4"):
                stat = video_file.stat()
                video_files.append({
                    'path': video_file,
                    'size': stat.st_size,
                    'mtime': stat.st_mtime,
                    'date': datetime.fromtimestamp(stat.st_mtime)
                })
            
            # Sort by modification time (oldest first)
            video_files.sort(key=lambda x: x['mtime'])
            
            # Check storage usage
            storage_info = self.get_storage_info()
            current_usage = storage_info.get('storage_used_gb', 0)
            
            # Delete files if over limit
            if current_usage > self.max_storage_gb:
                target_freed = current_usage - (self.max_storage_gb * 0.8)  # Free to 80% of limit
                
                for video_file in video_files:
                    if space_freed >= target_freed:
                        break
                    
                    try:
                        file_size = video_file['size']
                        video_file['path'].unlink()
                        space_freed += file_size / (1024 ** 3)  # Convert to GB
                        files_deleted += 1
                        
                        self.logger.debug(f"Deleted old recording: {video_file['path'].name}")
                        
                    except Exception as e:
                        self.logger.error(f"Error deleting file {video_file['path']}: {e}")
            
            # Delete files older than cleanup_days
            cutoff_date = datetime.now() - timedelta(days=self.cleanup_days)
            
            for video_file in video_files:
                if video_file['date'] < cutoff_date:
                    try:
                        video_file['path'].unlink()
                        space_freed += video_file['size'] / (1024 ** 3)
                        files_deleted += 1
                        
                        self.logger.debug(f"Deleted old recording by age: {video_file['path'].name}")
                        
                    except Exception as e:
                        self.logger.error(f"Error deleting file {video_file['path']}: {e}")
            
            self.stats['cleanup_runs'] += 1
            
            if files_deleted > 0:
                self.logger.info(f"Cleanup completed: {files_deleted} files deleted, {space_freed:.2f} GB freed")
            
            return files_deleted
            
        except Exception as e:
            self.logger.error(f"Error in cleanup: {e}")
            return 0
    
    def _cleanup_loop(self):
        """Background cleanup loop"""
        while True:
            try:
                # Run cleanup every hour
                time.sleep(3600)
                self.cleanup_old_recordings()
                
            except Exception as e:
                self.logger.error(f"Error in cleanup loop: {e}")
                time.sleep(300)  # Wait 5 minutes on error
    
    def get_recording_stats(self) -> Dict:
        """Get recording statistics"""
        return {
            **self.stats,
            'storage_info': self.get_storage_info(),
            'recording_status': self.get_recording_status()
        }
    
    def create_recording_schedule(self, camera_id: int, schedule: Dict) -> bool:
        """Create a recording schedule for a camera"""
        try:
            # This would integrate with a scheduler like APScheduler
            # For now, just log the request
            self.logger.info(f"Recording schedule requested for camera {camera_id}: {schedule}")
            return True
            
        except Exception as e:
            self.logger.error(f"Error creating recording schedule: {e}")
            return False
    
    def get_recordings_list(self, camera_id: int = None, 
                           start_date: datetime = None, 
                           end_date: datetime = None,
                           limit: int = 100) -> List[Dict]:
        """Get list of recordings"""
        try:
            recordings = []
            
            for video_file in self.videos_dir.rglob("*.mp4"):
                if camera_id and f"camera_{camera_id}_" not in video_file.name:
                    continue
                
                stat = video_file.stat()
                file_date = datetime.fromtimestamp(stat.mtime)
                
                if start_date and file_date < start_date:
                    continue
                
                if end_date and file_date > end_date:
                    continue
                
                recordings.append({
                    'filename': video_file.name,
                    'path': str(video_file),
                    'size_gb': stat.st_size / (1024 ** 3),
                    'created_at': file_date,
                    'camera_id': self._extract_camera_id_from_filename(video_file.name)
                })
            
            # Sort by creation time (newest first)
            recordings.sort(key=lambda x: x['created_at'], reverse=True)
            
            return recordings[:limit]
            
        except Exception as e:
            self.logger.error(f"Error getting recordings list: {e}")
            return []
    
    def _extract_camera_id_from_filename(self, filename: str) -> Optional[int]:
        """Extract camera ID from filename"""
        try:
            # Expected format: camera_123_20231201_143000.mp4
            parts = filename.split('_')
            if len(parts) >= 2 and parts[0] == 'camera':
                return int(parts[1])
        except Exception:
            pass
        return None
    
    def cleanup(self):
        """Cleanup resources"""
        try:
            # Stop all recordings
            for camera_id in list(self.recording_sessions.keys()):
                self.stop_recording(camera_id)
            
            self.logger.info("Recording manager cleanup completed")
            
        except Exception as e:
            self.logger.error(f"Error in cleanup: {e}")
    
    def get_system_performance(self) -> Dict:
        """Get system performance metrics"""
        try:
            # CPU usage
            cpu_percent = psutil.cpu_percent(interval=1)
            
            # Memory usage
            memory = psutil.virtual_memory()
            
            # Disk usage
            disk = psutil.disk_usage(self.videos_dir)
            
            # FFmpeg processes
            ffmpeg_processes = []
            for proc in psutil.process_iter(['pid', 'name', 'cpu_percent', 'memory_percent']):
                if proc.info['name'] == 'ffmpeg':
                    ffmpeg_processes.append({
                        'pid': proc.info['pid'],
                        'cpu_percent': proc.info['cpu_percent'],
                        'memory_percent': proc.info['memory_percent']
                    })
            
            return {
                'cpu_percent': cpu_percent,
                'memory_percent': memory.percent,
                'memory_available_gb': memory.available / (1024 ** 3),
                'disk_percent': disk.percent,
                'disk_free_gb': disk.free / (1024 ** 3),
                'ffmpeg_processes': ffmpeg_processes,
                'active_recordings': len(self.recording_sessions)
            }
            
        except Exception as e:
            self.logger.error(f"Error getting system performance: {e}")
            return {}
