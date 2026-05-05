#!/usr/bin/env python3
"""
Advanced AI Analyzer for Camera System
Includes face recognition, object detection, action analysis, and behavior tracking
"""

import cv2
import numpy as np
import face_recognition
import mediapipe as mp
from ultralytics import YOLO
import pickle
import logging
from datetime import datetime
from typing import Dict, List, Tuple, Any
from dataclasses import dataclass
from pathlib import Path

@dataclass
class DetectionResult:
    """Base class for all detection results"""
    confidence: float
    timestamp: datetime
    bbox: Tuple[int, int, int, int]  # x, y, width, height

@dataclass
class FaceDetection(DetectionResult):
    """Face detection result"""
    face_id: str = None
    name: str = "Unknown"
    encoding: np.ndarray = None
    emotion: str = None
    age_group: str = None
    gender: str = None

@dataclass
class ObjectDetection(DetectionResult):
    """Object detection result"""
    class_id: int
    class_name: str
    tracking_id: int = None

@dataclass
class PoseDetection(DetectionResult):
    """Pose detection result"""
    landmarks: List[Tuple[float, float, float]]
    pose_class: str = None
    action_confidence: float = 0.0

@dataclass
class ActionDetection:
    """Action detection result"""
    action: str
    confidence: float
    timestamp: datetime
    person_id: str = None
    bbox: Tuple[int, int, int, int] = None

class AIAnalyzer:
    """Advanced AI Analyzer with multiple capabilities"""
    
    def __init__(self, config: Dict):
        self.config = config
        self.logger = logging.getLogger(__name__)
        
        # Initialize models
        self.face_models = {}
        self.object_model = None
        self.pose_model = None
        self.action_classifier = None
        
        # Face recognition database
        self.known_faces = {}
        self.face_embeddings = {}
        
        # Tracking
        self.person_tracker = {}
        self.next_person_id = 1
        
        # Initialize all models
        self._initialize_models()
        
    def _initialize_models(self):
        """Initialize all AI models"""
        try:
            # Face recognition
            self._initialize_face_recognition()
            
            # Object detection
            self._initialize_object_detection()
            
            # Pose estimation
            self._initialize_pose_estimation()
            
            # Action classification
            self._initialize_action_classification()
            
            self.logger.info("All AI models initialized successfully")
            
        except Exception as e:
            self.logger.error(f"Error initializing models: {e}")
            raise
    
    def _initialize_face_recognition(self):
        """Initialize face recognition models"""
        try:
            # Load known faces if available
            faces_file = Path(self.config.get('AI_MODELS', {}).get('face_recognition', 'faces.pkl'))
            if faces_file.exists():
                with open(faces_file, 'rb') as f:
                    self.known_faces = pickle.load(f)
                self.logger.info(f"Loaded {len(self.known_faces)} known faces")
            else:
                self.logger.info("No known faces database found, starting fresh")
                
        except Exception as e:
            self.logger.error(f"Error initializing face recognition: {e}")
    
    def _initialize_object_detection(self):
        """Initialize YOLO object detection"""
        try:
            model_path = self.config.get('AI_MODELS', {}).get('object_detector', 'yolov8n.pt')
            self.object_model = YOLO(model_path)
            self.logger.info("YOLO object detection model loaded")
        except Exception as e:
            self.logger.error(f"Error initializing object detection: {e}")
    
    def _initialize_pose_estimation(self):
        """Initialize MediaPipe pose estimation"""
        try:
            self.pose_model = mp.solutions.pose.Pose(
                static_image_mode=False,
                model_complexity=1,
                enable_segmentation=False,
                min_detection_confidence=0.5,
                min_tracking_confidence=0.5
            )
            self.logger.info("MediaPipe pose estimation initialized")
        except Exception as e:
            self.logger.error(f"Error initializing pose estimation: {e}")
    
    def _initialize_action_classification(self):
        """Initialize action classification model"""
        # Placeholder for action classifier
        # In a real implementation, this would load a trained model
        self.logger.info("Action classification initialized (placeholder)")
    
    def analyze_frame(self, frame: np.ndarray, camera_id: str) -> Dict[str, Any]:
        """
        Analyze a single frame with all available AI models
        
        Args:
            frame: Input frame as numpy array
            camera_id: Camera identifier
            
        Returns:
            Dictionary containing all analysis results
        """
        results = {
            'timestamp': datetime.now(),
            'camera_id': camera_id,
            'frame_shape': frame.shape,
            'faces': [],
            'objects': [],
            'poses': [],
            'actions': [],
            'people_count': 0,
            'analysis_summary': {}
        }
        
        try:
            # Face detection and recognition
            if self.config.get('ANALYSIS_CONFIG', {}).get('face_recognition', True):
                results['faces'] = self._detect_faces(frame)
            
            # Object detection
            if self.config.get('ANALYSIS_CONFIG', {}).get('object_detection', True):
                results['objects'] = self._detect_objects(frame)
            
            # Pose estimation
            if self.config.get('ANALYSIS_CONFIG', {}).get('action_detection', True):
                results['poses'] = self._estimate_poses(frame)
            
            # Action detection
            if results['poses']:
                results['actions'] = self._detect_actions(frame, results['poses'])
            
            # People counting
            results['people_count'] = self._count_people(results)
            
            # Generate summary
            results['analysis_summary'] = self._generate_analysis_summary(results)
            
        except Exception as e:
            self.logger.error(f"Error analyzing frame: {e}")
        
        return results
    
    def _detect_faces(self, frame: np.ndarray) -> List[FaceDetection]:
        """Detect and recognize faces in frame"""
        faces = []
        
        try:
            # Convert to RGB for face_recognition
            rgb_frame = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)
            
            # Detect face locations
            face_locations = face_recognition.face_locations(rgb_frame, model="hog")
            face_encodings = face_recognition.face_encodings(rgb_frame, face_locations)
            
            for (top, right, bottom, left), encoding in zip(face_locations, face_encodings):
                # Compare with known faces
                name = "Unknown"
                face_id = None
                
                if self.known_faces:
                    matches = face_recognition.compare_faces(
                        list(self.known_faces.values()), 
                        encoding, 
                        tolerance=0.6
                    )
                    
                    if True in matches:
                        first_match_index = matches.index(True)
                        face_id = list(self.known_faces.keys())[first_match_index]
                        name = self.known_faces.get(face_id, "Unknown")
                
                face_detection = FaceDetection(
                    confidence=0.9,  # Face detection is usually high confidence
                    timestamp=datetime.now(),
                    bbox=(left, top, right-left, bottom-top),
                    face_id=face_id,
                    name=name,
                    encoding=encoding
                )
                
                faces.append(face_detection)
                
        except Exception as e:
            self.logger.error(f"Error in face detection: {e}")
        
        return faces
    
    def _detect_objects(self, frame: np.ndarray) -> List[ObjectDetection]:
        """Detect objects using YOLO"""
        objects = []
        
        try:
            if self.object_model:
                results = self.object_model(frame, verbose=False)
                
                for result in results:
                    boxes = result.boxes
                    if boxes is not None:
                        for box in boxes:
                            # Extract bounding box
                            x1, y1, x2, y2 = box.xyxy[0].cpu().numpy()
                            confidence = box.conf[0].cpu().numpy()
                            class_id = int(box.cls[0].cpu().numpy())
                            class_name = self.object_model.names[class_id]
                            
                            # Filter for relevant classes
                            if class_name in ['person', 'car', 'truck', 'bicycle', 'motorcycle'] and confidence > 0.5:
                                obj_detection = ObjectDetection(
                                    confidence=float(confidence),
                                    timestamp=datetime.now(),
                                    bbox=(int(x1), int(y1), int(x2-x1), int(y2-y1)),
                                    class_id=class_id,
                                    class_name=class_name
                                )
                                objects.append(obj_detection)
                
        except Exception as e:
            self.logger.error(f"Error in object detection: {e}")
        
        return objects
    
    def _estimate_poses(self, frame: np.ndarray) -> List[PoseDetection]:
        """Estimate human poses using MediaPipe"""
        poses = []
        
        try:
            if self.pose_model:
                rgb_frame = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)
                results = self.pose_model.process(rgb_frame)
                
                if results.pose_landmarks:
                    landmarks = []
                    for landmark in results.pose_landmarks.landmark:
                        landmarks.append((landmark.x, landmark.y, landmark.z))
                    
                    # Get bounding box
                    h, w = frame.shape[:2]
                    x_coords = [lm[0] for lm in landmarks]
                    y_coords = [lm[1] for lm in landmarks]
                    
                    bbox = (
                        int(min(x_coords) * w),
                        int(min(y_coords) * h),
                        int((max(x_coords) - min(x_coords)) * w),
                        int((max(y_coords) - min(y_coords)) * h)
                    )
                    
                    pose_detection = PoseDetection(
                        confidence=results.pose_landmarks.visibility.mean(),
                        timestamp=datetime.now(),
                        bbox=bbox,
                        landmarks=landmarks
                    )
                    
                    poses.append(pose_detection)
                    
        except Exception as e:
            self.logger.error(f"Error in pose estimation: {e}")
        
        return poses
    
    def _detect_actions(self, frame: np.ndarray, poses: List[PoseDetection]) -> List[ActionDetection]:
        """Detect actions based on pose analysis"""
        actions = []
        
        try:
            for pose in poses:
                # Simple action detection based on pose landmarks
                action = self._classify_action(pose.landmarks)
                
                if action != "standing":
                    action_detection = ActionDetection(
                        action=action,
                        confidence=0.7,
                        timestamp=datetime.now(),
                        bbox=pose.bbox
                    )
                    actions.append(action_detection)
                    
        except Exception as e:
            self.logger.error(f"Error in action detection: {e}")
        
        return actions
    
    def _classify_action(self, landmarks: List[Tuple[float, float, float]]) -> str:
        """Simple action classification based on pose landmarks"""
        try:
            # Extract key points
            if len(landmarks) < 33:
                return "standing"
            
            # Get specific landmarks for action detection
            left_shoulder = landmarks[11]
            right_shoulder = landmarks[12]
            left_elbow = landmarks[13]
            right_elbow = landmarks[14]
            left_wrist = landmarks[15]
            right_wrist = landmarks[16]
            
            # Simple heuristics for action detection
            # Arm raised action
            if (left_wrist[1] < left_shoulder[1] - 0.1) or (right_wrist[1] < right_shoulder[1] - 0.1):
                return "raising_hand"
            
            # Waving action
            if (abs(left_wrist[0] - left_shoulder[0]) > 0.3 or 
                abs(right_wrist[0] - right_shoulder[0]) > 0.3):
                return "waving"
            
            # Pointing action
            if (abs(left_wrist[1] - left_shoulder[1]) < 0.1 and 
                abs(left_wrist[0] - left_shoulder[0]) > 0.2):
                return "pointing"
            
        except Exception as e:
            self.logger.error(f"Error in action classification: {e}")
        
        return "standing"
    
    def _count_people(self, results: Dict) -> int:
        """Count people from various detection methods"""
        people_count = 0
        
        # Count from face detections
        people_count += len(results['faces'])
        
        # Count from object detections (person class)
        people_count += len([obj for obj in results['objects'] if obj.class_name == 'person'])
        
        # Count from pose detections
        people_count += len(results['poses'])
        
        # Remove duplicates (take maximum)
        return people_count
    
    def _generate_analysis_summary(self, results: Dict) -> Dict[str, Any]:
        """Generate summary of analysis results"""
        summary = {
            'total_detections': len(results['faces']) + len(results['objects']) + len(results['poses']),
            'known_faces': len([f for f in results['faces'] if f.name != "Unknown"]),
            'unknown_faces': len([f for f in results['faces'] if f.name == "Unknown"]),
            'people_detected': results['people_count'],
            'actions_detected': len(results['actions']),
            'objects_detected': len(results['objects']),
            'security_alerts': self._check_security_alerts(results),
            'business_insights': self._generate_business_insights(results)
        }
        
        return summary
    
    def _check_security_alerts(self, results: Dict) -> List[str]:
        """Check for security-related alerts"""
        alerts = []
        
        # Unknown faces outside business hours
        if results['faces']:
            unknown_faces = [f for f in results['faces'] if f.name == "Unknown"]
            if unknown_faces:
                alerts.append(f"Unknown faces detected: {len(unknown_faces)}")
        
        # Large crowds
        if results['people_count'] > 10:
            alerts.append(f"Large crowd detected: {results['people_count']} people")
        
        # Suspicious actions
        suspicious_actions = [a for a in results['actions'] if a.action in ['waving', 'pointing']]
        if suspicious_actions:
            alerts.append(f"Suspicious actions detected: {len(suspicious_actions)}")
        
        return alerts
    
    def _generate_business_insights(self, results: Dict) -> Dict[str, Any]:
        """Generate business intelligence insights"""
        insights = {
            'customer_engagement': 0,
            'staff_activity': 0,
            'peak_activity': False,
            'dwell_time_analysis': 'normal'
        }
        
        # Simple heuristics for business insights
        if results['people_count'] > 5:
            insights['peak_activity'] = True
        
        # Analyze actions for engagement
        engaging_actions = [a for a in results['actions'] if a.action in ['waving', 'pointing', 'raising_hand']]
        insights['customer_engagement'] = len(engaging_actions)
        
        return insights
    
    def register_face(self, face_encoding: np.ndarray, name: str, face_id: str = None) -> str:
        """Register a new face in the recognition database"""
        try:
            if face_id is None:
                face_id = f"face_{len(self.known_faces) + 1}"
            
            self.known_faces[face_id] = face_encoding
            self.face_embeddings[face_id] = {
                'name': name,
                'encoding': face_encoding,
                'registered_at': datetime.now()
            }
            
            self.logger.info(f"Registered new face: {name} ({face_id})")
            return face_id
            
        except Exception as e:
            self.logger.error(f"Error registering face: {e}")
            return None
    
    def save_faces_database(self, filepath: str = None):
        """Save the faces database to disk"""
        try:
            if filepath is None:
                filepath = Path(self.config.get('AI_MODELS', {}).get('face_recognition', 'faces.pkl'))
            
            with open(filepath, 'wb') as f:
                pickle.dump(self.known_faces, f)
            
            self.logger.info(f"Faces database saved to {filepath}")
            
        except Exception as e:
            self.logger.error(f"Error saving faces database: {e}")
