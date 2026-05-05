<?php
class RTSPStreamService {
    private $empresa_id;
    
    public function __construct($empresa_id) {
        $this->empresa_id = $empresa_id;
    }
    
    /**
     * Generar stream WebRTC desde RTSP usando FFmpeg
     */
    public function generateWebRTCStream($camara) {
        $rtsp_url = $this->buildRTSPUrl($camara);
        $stream_key = $this->generateStreamKey($camara['id']);
        
        // Iniciar proceso FFmpeg para transcodificar RTSP a WebRTC
        $command = $this->buildFFmpegCommand($rtsp_url, $stream_key);
        
        // Ejecutar en background
        $this->startBackgroundProcess($command, $stream_key);
        
        return [
            'stream_key' => $stream_key,
            'webrtc_url' => "webrtc://localhost:8080/{$stream_key}",
            'hls_url' => "/streams/{$stream_key}.m3u8"
        ];
    }
    
    /**
     * Construir URL RTSP completa
     */
    private function buildRTSPUrl($camara) {
        $url = 'rtsp://';
        
        if (!empty($camara['usuario'])) {
            $url .= $camara['usuario'];
            if (!empty($camara['password'])) {
                $url .= ':' . $camara['password'];
            }
            $url .= '@';
        }
        
        $url .= $camara['ip'] . ':' . $camara['puerto'] . $camara['ruta_stream'];
        
        return $url;
    }
    
    /**
     * Generar clave única para stream
     */
    private function generateStreamKey($camara_id) {
        return 'stream_' . $this->empresa_id . '_' . $camara_id . '_' . md5(time() . $camara_id);
    }
    
    /**
     * Construir comando FFmpeg para RTSP a HLS/WebRTC
     */
    private function buildFFmpegCommand($rtsp_url, $stream_key) {
        $output_dir = __DIR__ . '/../streams/' . $stream_key;
        
        // Crear directorio si no existe
        if (!is_dir($output_dir)) {
            mkdir($output_dir, 0755, true);
        }
        
        $hls_path = $output_dir . '/output.m3u8';
        
        // Comando FFmpeg para RTSP a HLS (compatible con navegadores)
        $command = sprintf(
            'ffmpeg -rtsp_transport tcp -i "%s" -c:v libx264 -preset ultrafast -tune zerolatency ' .
            '-c:a aac -b:a 128k -f hls -hls_time 2 -hls_list_size 3 -hls_flags delete_segments ' .
            '-hls_segment_filename "%s/segment%%03d.ts" "%s" > /dev/null 2>&1 &',
            $rtsp_url,
            $output_dir,
            $hls_path
        );
        
        return $command;
    }
    
    /**
     * Iniciar proceso en background
     */
    private function startBackgroundProcess($command, $stream_key) {
        // Guardar información del proceso
        $process_info = [
            'stream_key' => $stream_key,
            'command' => $command,
            'pid' => null,
            'started_at' => date('Y-m-d H:i:s'),
            'empresa_id' => $this->empresa_id
        ];
        
        // Ejecutar comando
        $process = popen($command, 'r');
        if ($process) {
            $process_info['pid'] = getmypid(); // Approximate
            
            // Guardar en archivo para control
            file_put_contents(__DIR__ . '/../streams/' . $stream_key . '.json', json_encode($process_info));
        }
        
        return $process_info;
    }
    
    /**
     * Verificar si stream está activo
     */
    public function isStreamActive($stream_key) {
        $info_file = __DIR__ . '/../streams/' . $stream_key . '.json';
        $hls_file = __DIR__ . '/../streams/' . $stream_key . '/output.m3u8';
        
        if (!file_exists($info_file) || !file_exists($hls_file)) {
            return false;
        }
        
        $info = json_decode(file_get_contents($info_file), true);
        $started = strtotime($info['started_at']);
        
        // Considerar activo si tiene menos de 5 minutos y el archivo HLS se actualizó
        $hls_mtime = filemtime($hls_file);
        return (time() - $started < 300) && (time() - $hls_mtime < 30);
    }
    
    /**
     * Detener stream
     */
    public function stopStream($stream_key) {
        $info_file = __DIR__ . '/../streams/' . $stream_key . '.json';
        
        if (file_exists($info_file)) {
            $info = json_decode(file_get_contents($info_file), true);
            
            // Matar proceso si tenemos PID
            if (!empty($info['pid'])) {
                exec("kill -9 {$info['pid']} 2>/dev/null");
            }
            
            // Eliminar archivos
            unlink($info_file);
            $this->deleteDirectory(__DIR__ . '/../streams/' . $stream_key);
        }
    }
    
    /**
     * Eliminar directorio recursivamente
     */
    private function deleteDirectory($dir) {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
    
    /**
     * Obtener URL HLS para reproductor HTML5
     */
    public function getHLSUrl($camara) {
        $stream_key = 'stream_' . $this->empresa_id . '_' . $camara['id'];
        
        // Si no está activo, iniciar stream
        if (!$this->isStreamActive($stream_key)) {
            $this->generateWebRTCStream($camara);
            
            // Esperar un momento a que se genere el primer segmento
            sleep(2);
        }
        
        return "/streams/{$stream_key}/output.m3u8";
    }
}
