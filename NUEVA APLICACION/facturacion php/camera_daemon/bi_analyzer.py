#!/usr/bin/env python3
"""
Business Intelligence Analyzer for Advanced Camera System
Provides customer insights, traffic analysis, and business metrics
"""

import logging
import json
import numpy as np
from datetime import datetime, timedelta
from typing import Dict, List, Optional, Any, Tuple
from dataclasses import dataclass
from collections import defaultdict, deque
import redis

@dataclass
class CustomerJourney:
    """Customer journey tracking data"""
    customer_id: str
    camera_id: int
    entry_time: datetime
    exit_time: Optional[datetime] = None
    path_points: List[Tuple[float, float]] = None
    dwell_time: float = 0.0
    interactions: List[str] = None
    conversion_detected: bool = False

@dataclass
class HeatmapData:
    """Heatmap data for spatial analysis"""
    camera_id: int
    timestamp: datetime
    heatmap_matrix: np.ndarray
    total_detections: int
    peak_areas: List[Tuple[int, int, float]]  # x, y, intensity

@dataclass
class QueueAnalysis:
    """Queue analysis data"""
    camera_id: int
    timestamp: datetime
    queue_length: int
    avg_wait_time: float
    service_rate: float
    abandonment_rate: float

class BIAnalyzer:
    """Business Intelligence Analyzer"""
    
    def __init__(self, config: Dict, database_manager):
        self.config = config
        self.db_manager = database_manager
        self.logger = logging.getLogger("bi_analyzer")
        
        # BI configuration
        self.bi_config = config.get('BI_CONFIG', {})
        
        # Customer journey tracking
        self.customer_journeys = {}  # customer_id -> CustomerJourney
        self.active_customers = {}  # camera_id -> set of customer_ids
        
        # Heatmap data
        self.heatmap_data = defaultdict(deque)  # camera_id -> deque of HeatmapData
        
        # Queue analysis
        self.queue_data = defaultdict(deque)  # camera_id -> deque of QueueAnalysis
        
        # Performance metrics
        self.metrics = {
            'customer_count': 0,
            'avg_dwell_time': 0.0,
            'conversion_rate': 0.0,
            'peak_hour': None,
            'busiest_area': None,
            'queue_efficiency': 0.0
        }
        
        # Time-based analysis windows
        self.analysis_windows = {
            'hourly': deque(maxlen=24),
            'daily': deque(maxlen=30),
            'weekly': deque(maxlen=4)
        }
    
    def process_frame_analysis(self, camera_id: int, analysis_results: Dict):
        """Process frame analysis results for BI insights"""
        try:
            timestamp = analysis_results.get('timestamp', datetime.now())
            
            # Process face detections for customer tracking
            self._process_face_detections(camera_id, analysis_results.get('faces', []), timestamp)
            
            # Process object detections for traffic analysis
            self._process_object_detections(camera_id, analysis_results.get('objects', []), timestamp)
            
            # Process pose detections for behavior analysis
            self._process_pose_detections(camera_id, analysis_results.get('poses', []), timestamp)
            
            # Process actions for engagement analysis
            self._process_actions(camera_id, analysis_results.get('actions', []), timestamp)
            
            # Update metrics
            self._update_metrics(camera_id, timestamp)
            
        except Exception as e:
            self.logger.error(f"Error processing frame analysis: {e}")
    
    def _process_face_detections(self, camera_id: int, faces: List[Dict], timestamp: datetime):
        """Process face detections for customer tracking"""
        try:
            for face in faces:
                face_id = face.get('face_id')
                name = face.get('name', 'Unknown')
                bbox = face.get('bbox', [0, 0, 0, 0])
                
                # Generate customer ID if not exists
                if not face_id:
                    face_id = f"customer_{camera_id}_{timestamp.timestamp()}"
                
                # Track customer journey
                if face_id not in self.customer_journeys:
                    self.customer_journeys[face_id] = CustomerJourney(
                        customer_id=face_id,
                        camera_id=camera_id,
                        entry_time=timestamp,
                        path_points=[],
                        interactions=[]
                    )
                    self.active_customers.setdefault(camera_id, set()).add(face_id)
                
                # Update customer journey
                journey = self.customer_journeys[face_id]
                journey.path_points.append(self._get_center_point(bbox))
                
                # Track known customers vs unknown
                if name != 'Unknown':
                    journey.interactions.append(f"known_customer_{name}")
                
        except Exception as e:
            self.logger.error(f"Error processing face detections: {e}")
    
    def _process_object_detections(self, camera_id: int, objects: List[Dict], timestamp: datetime):
        """Process object detections for traffic analysis"""
        try:
            people_count = sum(1 for obj in objects if obj.get('class_name') == 'person')
            
            # Store people count for heatmap
            if people_count > 0:
                self._update_heatmap(camera_id, objects, timestamp)
            
            # Analyze crowd density
            if people_count > 5:
                self._analyze_crowd_behavior(camera_id, people_count, timestamp)
            
            # Queue analysis
            self._analyze_queue(camera_id, objects, timestamp)
            
        except Exception as e:
            self.logger.error(f"Error processing object detections: {e}")
    
    def _process_pose_detections(self, camera_id: int, poses: List[Dict], timestamp: datetime):
        """Process pose detections for behavior analysis"""
        try:
            for pose in poses:
                landmarks = pose.get('landmarks', [])
                bbox = pose.get('bbox', [0, 0, 0, 0])
                
                if landmarks:
                    # Analyze pose for engagement indicators
                    engagement_score = self._calculate_engagement_score(landmarks)
                    
                    # Update customer journey with engagement data
                    center_point = self._get_center_point(bbox)
                    nearby_customers = self._find_nearby_customers(camera_id, center_point, 50)
                    
                    for customer_id in nearby_customers:
                        journey = self.customer_journeys.get(customer_id)
                        if journey:
                            journey.interactions.append(f"engagement_{engagement_score:.2f}")
            
        except Exception as e:
            self.logger.error(f"Error processing pose detections: {e}")
    
    def _process_actions(self, camera_id: int, actions: List[Dict], timestamp: datetime):
        """Process actions for business insights"""
        try:
            for action in actions:
                action_type = action.get('action')
                bbox = action.get('bbox', [0, 0, 0, 0])
                
                # Map actions to business events
                business_events = self._map_action_to_business_event(action_type)
                
                if business_events:
                    center_point = self._get_center_point(bbox)
                    nearby_customers = self._find_nearby_customers(camera_id, center_point, 100)
                    
                    for customer_id in nearby_customers:
                        journey = self.customer_journeys.get(customer_id)
                        if journey:
                            for event in business_events:
                                journey.interactions.append(event)
                                
                                # Mark conversion if detected
                                if event == 'purchase':
                                    journey.conversion_detected = True
            
        except Exception as e:
            self.logger.error(f"Error processing actions: {e}")
    
    def _get_center_point(self, bbox: List[int]) -> Tuple[float, float]:
        """Get center point from bounding box"""
        if len(bbox) >= 4:
            x, y, w, h = bbox[:4]
            return (x + w/2, y + h/2)
        return (0, 0)
    
    def _find_nearby_customers(self, camera_id: int, point: Tuple[float, float], 
                            radius: float) -> List[str]:
        """Find customers near a specific point"""
        nearby_customers = []
        
        for customer_id, journey in self.customer_journeys.items():
            if journey.camera_id == camera_id and journey.path_points:
                last_point = journey.path_points[-1]
                distance = np.sqrt((point[0] - last_point[0])**2 + (point[1] - last_point[1])**2)
                
                if distance <= radius:
                    nearby_customers.append(customer_id)
        
        return nearby_customers
    
    def _calculate_engagement_score(self, landmarks: List[Tuple[float, float, float]]) -> float:
        """Calculate engagement score from pose landmarks"""
        try:
            if len(landmarks) < 33:
                return 0.0
            
            # Key landmarks for engagement
            nose = landmarks[0]
            left_eye = landmarks[2]
            right_eye = landmarks[5]
            left_shoulder = landmarks[11]
            right_shoulder = landmarks[12]
            
            # Calculate face orientation (simplified)
            eye_center = ((left_eye[0] + right_eye[0])/2, (left_eye[1] + right_eye[1])/2)
            face_angle = np.arctan2(nose[1] - eye_center[1], nose[0] - eye_center[0])
            
            # Calculate shoulder orientation
            shoulder_angle = np.arctan2(right_shoulder[1] - left_shoulder[1], 
                                      right_shoulder[0] - left_shoulder[0])
            
            # Engagement indicators
            facing_forward = abs(face_angle) < np.pi/4  # Within 45 degrees
            upright_posture = abs(shoulder_angle) < np.pi/6  # Within 30 degrees
            
            # Calculate score
            score = 0.0
            if facing_forward:
                score += 0.5
            if upright_posture:
                score += 0.3
            
            # Add confidence based on landmark visibility
            visible_landmarks = sum(1 for lm in landmarks if lm[2] > 0.5)
            confidence = visible_landmarks / len(landmarks)
            score *= confidence
            
            return min(1.0, score)
            
        except Exception as e:
            self.logger.error(f"Error calculating engagement score: {e}")
            return 0.0
    
    def _map_action_to_business_event(self, action: str) -> List[str]:
        """Map detected actions to business events"""
        action_mapping = {
            'waving': ['attention_seeking', 'customer_service'],
            'pointing': ['product_interest', 'information_seeking'],
            'raising_hand': ['purchase_intent', 'customer_service'],
            'standing': ['browsing', 'waiting'],
            'walking': ['exploring', 'navigation']
        }
        
        return action_mapping.get(action, [])
    
    def _update_heatmap(self, camera_id: int, objects: List[Dict], timestamp: datetime):
        """Update heatmap data for spatial analysis"""
        try:
            # Create heatmap matrix (assuming 640x480 resolution)
            heatmap = np.zeros((48, 64))  # Downsampled for efficiency
            
            for obj in objects:
                if obj.get('class_name') == 'person':
                    bbox = obj.get('bbox', [0, 0, 0, 0])
                    confidence = obj.get('confidence', 1.0)
                    
                    # Add to heatmap
                    x, y, w, h = bbox[:4]
                    grid_x = int(x / 10)  # 10-pixel grid
                    grid_y = int(y / 10)
                    
                    if 0 <= grid_x < 64 and 0 <= grid_y < 48:
                        heatmap[grid_y, grid_x] += confidence
            
            # Find peak areas
            peak_areas = []
            for y in range(0, 48, 4):  # Sample every 4 pixels
                for x in range(0, 64, 4):
                    if heatmap[y, x] > 0:
                        peak_areas.append((x, y, heatmap[y, x]))
            
            # Sort by intensity and keep top 10
            peak_areas.sort(key=lambda x: x[2], reverse=True)
            peak_areas = peak_areas[:10]
            
            # Store heatmap data
            heatmap_data = HeatmapData(
                camera_id=camera_id,
                timestamp=timestamp,
                heatmap_matrix=heatmap,
                total_detections=len([obj for obj in objects if obj.get('class_name') == 'person']),
                peak_areas=peak_areas
            )
            
            self.heatmap_data[camera_id].append(heatmap_data)
            
            # Keep only last 100 heatmaps per camera
            if len(self.heatmap_data[camera_id]) > 100:
                self.heatmap_data[camera_id].popleft()
            
        except Exception as e:
            self.logger.error(f"Error updating heatmap: {e}")
    
    def _analyze_crowd_behavior(self, camera_id: int, people_count: int, timestamp: datetime):
        """Analyze crowd behavior patterns"""
        try:
            # Store crowd density data
            crowd_data = {
                'camera_id': camera_id,
                'timestamp': timestamp,
                'people_count': people_count,
                'density_score': min(1.0, people_count / 20.0)  # Normalize to 0-1
            }
            
            # Store in database for analysis
            self.db_manager.store_bi_insight({
                'camera_id': camera_id,
                'insight_type': 'crowd_density',
                'value': people_count,
                'metadata': crowd_data
            })
            
        except Exception as e:
            self.logger.error(f"Error analyzing crowd behavior: {e}")
    
    def _analyze_queue(self, camera_id: int, objects: List[Dict], timestamp: datetime):
        """Analyze queue patterns"""
        try:
            people = [obj for obj in objects if obj.get('class_name') == 'person']
            
            if len(people) >= 2:  # Only analyze if there's a potential queue
                # Simple queue analysis based on spatial clustering
                positions = []
                for person in people:
                    bbox = person.get('bbox', [0, 0, 0, 0])
                    center = self._get_center_point(bbox)
                    positions.append(center)
                
                # Calculate queue metrics
                queue_length = len(positions)
                avg_spacing = self._calculate_avg_spacing(positions)
                
                queue_analysis = QueueAnalysis(
                    camera_id=camera_id,
                    timestamp=timestamp,
                    queue_length=queue_length,
                    avg_wait_time=avg_spacing * 2,  # Estimate wait time
                    service_rate=0.5,  # Placeholder
                    abandonment_rate=0.1  # Placeholder
                )
                
                self.queue_data[camera_id].append(queue_analysis)
                
                # Keep only last 50 queue analyses
                if len(self.queue_data[camera_id]) > 50:
                    self.queue_data[camera_id].popleft()
                
        except Exception as e:
            self.logger.error(f"Error analyzing queue: {e}")
    
    def _calculate_avg_spacing(self, positions: List[Tuple[float, float]]) -> float:
        """Calculate average spacing between people in queue"""
        try:
            if len(positions) < 2:
                return 0.0
            
            total_distance = 0
            count = 0
            
            for i in range(len(positions) - 1):
                distance = np.sqrt(
                    (positions[i+1][0] - positions[i][0])**2 + 
                    (positions[i+1][1] - positions[i][1])**2
                )
                total_distance += distance
                count += 1
            
            return total_distance / count if count > 0 else 0.0
            
        except Exception as e:
            self.logger.error(f"Error calculating average spacing: {e}")
            return 0.0
    
    def _update_metrics(self, camera_id: int, timestamp: datetime):
        """Update BI metrics"""
        try:
            # Customer count
            self.metrics['customer_count'] = len(self.customer_journeys)
            
            # Average dwell time
            completed_journeys = [
                j for j in self.customer_journeys.values() 
                if j.exit_time is not None
            ]
            
            if completed_journeys:
                avg_dwell = np.mean([j.dwell_time for j in completed_journeys])
                self.metrics['avg_dwell_time'] = avg_dwell
            
            # Conversion rate
            if completed_journeys:
                conversions = sum(1 for j in completed_journeys if j.conversion_detected)
                self.metrics['conversion_rate'] = conversions / len(completed_journeys)
            
            # Peak hour analysis
            current_hour = timestamp.hour
            hourly_customers = len([
                j for j in self.customer_journeys.values() 
                if j.entry_time.hour == current_hour
            ])
            
            if hourly_customers > self.metrics.get('peak_hour_customers', 0):
                self.metrics['peak_hour'] = current_hour
                self.metrics['peak_hour_customers'] = hourly_customers
            
        except Exception as e:
            self.logger.error(f"Error updating metrics: {e}")
    
    def generate_insights(self, camera_streams: Dict) -> Dict:
        """Generate comprehensive BI insights"""
        try:
            insights = {
                'timestamp': datetime.now(),
                'customer_analytics': self._generate_customer_analytics(),
                'traffic_analysis': self._generate_traffic_analysis(),
                'spatial_analysis': self._generate_spatial_analysis(),
                'queue_analysis': self._generate_queue_analysis(),
                'performance_metrics': self._generate_performance_metrics(),
                'recommendations': self._generate_recommendations()
            }
            
            # Store insights in database
            for camera_id in camera_streams.keys():
                self.db_manager.store_bi_insight({
                    'camera_id': camera_id,
                    'insight_type': 'comprehensive',
                    'value': json.dumps(insights, default=str),
                    'metadata': insights
                })
            
            return insights
            
        except Exception as e:
            self.logger.error(f"Error generating insights: {e}")
            return {}
    
    def _generate_customer_analytics(self) -> Dict:
        """Generate customer analytics"""
        try:
            active_customers = len(self.customer_journeys)
            completed_journeys = [
                j for j in self.customer_journeys.values() 
                if j.exit_time is not None
            ]
            
            analytics = {
                'active_customers': active_customers,
                'completed_journeys': len(completed_journeys),
                'avg_dwell_time': 0.0,
                'conversion_rate': 0.0,
                'repeat_customers': 0,
                'new_customers': 0
            }
            
            if completed_journeys:
                dwell_times = [j.dwell_time for j in completed_journeys]
                analytics['avg_dwell_time'] = np.mean(dwell_times)
                
                conversions = sum(1 for j in completed_journeys if j.conversion_detected)
                analytics['conversion_rate'] = conversions / len(completed_journeys)
                
                # Analyze customer types
                known_customers = set()
                for j in completed_journeys:
                    for interaction in j.interactions:
                        if interaction.startswith('known_customer_'):
                            known_customers.add(interaction)
                
                analytics['repeat_customers'] = len(known_customers)
                analytics['new_customers'] = len(completed_journeys) - len(known_customers)
            
            return analytics
            
        except Exception as e:
            self.logger.error(f"Error generating customer analytics: {e}")
            return {}
    
    def _generate_traffic_analysis(self) -> Dict:
        """Generate traffic analysis"""
        try:
            # Get hourly traffic patterns
            hourly_traffic = defaultdict(int)
            
            for journey in self.customer_journeys.values():
                hour = journey.entry_time.hour
                hourly_traffic[hour] += 1
            
            # Find peak hours
            if hourly_traffic:
                peak_hour = max(hourly_traffic.items(), key=lambda x: x[1])
                traffic_analysis = {
                    'peak_hour': peak_hour[0],
                    'peak_traffic': peak_hour[1],
                    'hourly_distribution': dict(hourly_traffic),
                    'total_visitors': sum(hourly_traffic.values()),
                    'avg_hourly_traffic': np.mean(list(hourly_traffic.values()))
                }
            else:
                traffic_analysis = {
                    'peak_hour': None,
                    'peak_traffic': 0,
                    'hourly_distribution': {},
                    'total_visitors': 0,
                    'avg_hourly_traffic': 0
                }
            
            return traffic_analysis
            
        except Exception as e:
            self.logger.error(f"Error generating traffic analysis: {e}")
            return {}
    
    def _generate_spatial_analysis(self) -> Dict:
        """Generate spatial analysis"""
        try:
            spatial_analysis = {
                'heatmap_data': {},
                'busiest_areas': {},
                'traffic_flows': []
            }
            
            for camera_id, heatmap_deque in self.heatmap_data.items():
                if heatmap_deque:
                    # Combine recent heatmaps
                    combined_heatmap = np.zeros((48, 64))
                    for heatmap_data in heatmap_deque:
                        combined_heatmap += heatmap_data.heatmap_matrix
                    
                    # Find busiest areas
                    busiest_areas = []
                    for y in range(0, 48, 4):
                        for x in range(0, 64, 4):
                            if combined_heatmap[y, x] > 0:
                                busiest_areas.append((x, y, combined_heatmap[y, x]))
                    
                    busiest_areas.sort(key=lambda x: x[2], reverse=True)
                    busiest_areas = busiest_areas[:5]
                    
                    spatial_analysis['heatmap_data'][camera_id] = {
                        'matrix': combined_heatmap.tolist(),
                        'total_detections': np.sum(combined_heatmap)
                    }
                    
                    spatial_analysis['busiest_areas'][camera_id] = busiest_areas
            
            return spatial_analysis
            
        except Exception as e:
            self.logger.error(f"Error generating spatial analysis: {e}")
            return {}
    
    def _generate_queue_analysis(self) -> Dict:
        """Generate queue analysis"""
        try:
            queue_analysis = {
                'avg_queue_length': 0.0,
                'avg_wait_time': 0.0,
                'service_efficiency': 0.0,
                'peak_queue_times': []
            }
            
            all_queue_data = []
            for camera_id, queue_deque in self.queue_data.items():
                all_queue_data.extend(list(queue_deque))
            
            if all_queue_data:
                queue_lengths = [q.queue_length for q in all_queue_data]
                wait_times = [q.avg_wait_time for q in all_queue_data]
                
                queue_analysis['avg_queue_length'] = np.mean(queue_lengths)
                queue_analysis['avg_wait_time'] = np.mean(wait_times)
                
                # Find peak queue times
                peak_queues = sorted(all_queue_data, key=lambda x: x.queue_length, reverse=True)[:5]
                queue_analysis['peak_queue_times'] = [
                    {
                        'timestamp': q.timestamp.isoformat(),
                        'queue_length': q.queue_length,
                        'camera_id': q.camera_id
                    }
                    for q in peak_queues
                ]
                
                # Calculate service efficiency
                if wait_times:
                    target_wait_time = 300  # 5 minutes
                    efficiency = min(1.0, target_wait_time / np.mean(wait_times))
                    queue_analysis['service_efficiency'] = efficiency
            
            return queue_analysis
            
        except Exception as e:
            self.logger.error(f"Error generating queue analysis: {e}")
            return {}
    
    def _generate_performance_metrics(self) -> Dict:
        """Generate performance metrics"""
        try:
            return {
                **self.metrics,
                'system_efficiency': self._calculate_system_efficiency(),
                'customer_satisfaction': self._estimate_customer_satisfaction(),
                'staff_productivity': self._estimate_staff_productivity()
            }
            
        except Exception as e:
            self.logger.error(f"Error generating performance metrics: {e}")
            return {}
    
    def _calculate_system_efficiency(self) -> float:
        """Calculate overall system efficiency"""
        try:
            # Factors: customer flow, queue efficiency, conversion rate
            customer_flow_score = min(1.0, self.metrics.get('customer_count', 0) / 100)
            queue_score = self._generate_queue_analysis().get('service_efficiency', 0)
            conversion_score = self.metrics.get('conversion_rate', 0)
            
            efficiency = (customer_flow_score + queue_score + conversion_score) / 3
            return efficiency
            
        except Exception as e:
            self.logger.error(f"Error calculating system efficiency: {e}")
            return 0.0
    
    def _estimate_customer_satisfaction(self) -> float:
        """Estimate customer satisfaction based on metrics"""
        try:
            # Factors: wait time, dwell time, conversion rate
            queue_analysis = self._generate_queue_analysis()
            avg_wait_time = queue_analysis.get('avg_wait_time', 0)
            avg_dwell_time = self.metrics.get('avg_dwell_time', 0)
            conversion_rate = self.metrics.get('conversion_rate', 0)
            
            # Calculate satisfaction score
            wait_score = max(0, 1.0 - (avg_wait_time / 600))  # 10 minutes max
            dwell_score = min(1.0, avg_dwell_time / 1800)  # 30 minutes optimal
            conversion_score = conversion_rate
            
            satisfaction = (wait_score + dwell_score + conversion_score) / 3
            return satisfaction
            
        except Exception as e:
            self.logger.error(f"Error estimating customer satisfaction: {e}")
            return 0.0
    
    def _estimate_staff_productivity(self) -> float:
        """Estimate staff productivity"""
        try:
            # Placeholder for staff productivity estimation
            # This would require staff tracking data
            return 0.8  # Default productivity score
            
        except Exception as e:
            self.logger.error(f"Error estimating staff productivity: {e}")
            return 0.0
    
    def _generate_recommendations(self) -> List[Dict]:
        """Generate actionable recommendations"""
        try:
            recommendations = []
            
            # Customer flow recommendations
            customer_analytics = self._generate_customer_analytics()
            if customer_analytics.get('avg_dwell_time', 0) > 1800:  # 30 minutes
                recommendations.append({
                    'type': 'customer_flow',
                    'priority': 'medium',
                    'title': 'High Dwell Time Detected',
                    'description': 'Customers are spending more than 30 minutes on average. Consider improving store layout or service speed.',
                    'action_items': ['Optimize store layout', 'Increase staff during peak hours']
                })
            
            # Queue recommendations
            queue_analysis = self._generate_queue_analysis()
            if queue_analysis.get('avg_wait_time', 0) > 300:  # 5 minutes
                recommendations.append({
                    'type': 'queue_management',
                    'priority': 'high',
                    'title': 'Long Wait Times',
                    'description': 'Average wait time exceeds 5 minutes. This may impact customer satisfaction.',
                    'action_items': ['Add more staff', 'Implement queue management system', 'Optimize service process']
                })
            
            # Conversion recommendations
            if customer_analytics.get('conversion_rate', 0) < 0.3:  # 30%
                recommendations.append({
                    'type': 'conversion',
                    'priority': 'medium',
                    'title': 'Low Conversion Rate',
                    'description': 'Conversion rate is below 30%. Consider improving customer engagement.',
                    'action_items': ['Train staff on sales techniques', 'Improve product placement', 'Enhance customer service']
                })
            
            return recommendations
            
        except Exception as e:
            self.logger.error(f"Error generating recommendations: {e}")
            return []
    
    def cleanup_old_data(self, hours: int = 24):
        """Clean up old BI data"""
        try:
            cutoff_time = datetime.now() - timedelta(hours=hours)
            
            # Clean up old customer journeys
            completed_journeys = [
                customer_id for customer_id, journey in self.customer_journeys.items()
                if journey.exit_time and journey.exit_time < cutoff_time
            ]
            
            for customer_id in completed_journeys:
                del self.customer_journeys[customer_id]
            
            # Clean up old heatmap data
            for camera_id in list(self.heatmap_data.keys()):
                while (self.heatmap_data[camera_id] and 
                       self.heatmap_data[camera_id][0].timestamp < cutoff_time):
                    self.heatmap_data[camera_id].popleft()
            
            # Clean up old queue data
            for camera_id in list(self.queue_data.keys()):
                while (self.queue_data[camera_id] and 
                       self.queue_data[camera_id][0].timestamp < cutoff_time):
                    self.queue_data[camera_id].popleft()
            
            self.logger.info(f"Cleaned up BI data older than {hours} hours")
            
        except Exception as e:
            self.logger.error(f"Error cleaning up old BI data: {e}")
