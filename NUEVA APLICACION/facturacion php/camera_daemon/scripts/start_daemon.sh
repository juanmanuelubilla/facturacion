#!/bin/bash

# Start Camera Daemon Script
# Simple script to start the camera daemon service

echo "🚀 Starting Advanced Camera Daemon..."

# Check if running as root
if [[ $EUID -ne 0 ]]; then
   echo "This script must be run as root (use sudo)"
   exit 1
fi

# Start the service
systemctl start camera-daemon

# Wait a moment for service to start
sleep 2

# Check status
if systemctl is-active --quiet camera-daemon; then
    echo "✅ Camera daemon started successfully"
    
    # Show status
    echo ""
    echo "📊 Service Status:"
    systemctl status camera-daemon --no-pager -l
    
    echo ""
    echo "📋 Quick Commands:"
    echo "  camera-daemon status - Check detailed status"
    echo "  camera-daemon logs   - View recent logs"
    echo "  camera-daemon stop   - Stop the daemon"
    
else
    echo "❌ Failed to start camera daemon"
    echo ""
    echo "🔍 Troubleshooting:"
    echo "  1. Check logs: journalctl -u camera-daemon -n 20"
    echo "  2. Check configuration: /home/pi/facturacion_php/camera_daemon/.env"
    echo "  3. Check dependencies: pip list"
    echo "  4. Check database connection"
    echo "  5. Check Redis connection"
fi
