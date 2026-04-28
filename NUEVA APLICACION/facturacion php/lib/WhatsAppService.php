<?php
require_once __DIR__ . '/../config.php';

class WhatsAppService {
    private $apiKey;
    private $apiSecret;
    private $phoneNumber;
    private $sid;
    private $empresaId;
    
    public function __construct($empresaId) {
        $this->empresaId = $empresaId;
        $this->loadConfig();
    }
    
    private function loadConfig() {
        $config = fetch("SELECT * FROM whatsapp_config WHERE empresa_id = ? AND activo = 1 LIMIT 1", [$this->empresaId]);
        
        if ($config) {
            $this->apiKey = $config['api_key'];
            $this->apiSecret = $config['api_secret'];
            $this->phoneNumber = $config['phone_number'];
            $this->sid = $config['sid'];
        } else {
            throw new Exception("Configuración de WhatsApp no encontrada para la empresa");
        }
    }
    
    /**
     * Enviar mensaje individual
     */
    public function sendMessage($to, $message) {
        $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->sid}/Messages.json";
        
        $data = [
            'From' => "whatsapp:{$this->phoneNumber}",
            'To' => "whatsapp:{$to}",
            'Body' => $message
        ];
        
        $auth = base64_encode("{$this->apiKey}:{$this->apiSecret}");
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Basic {$auth}",
            "Content-Type: application/x-www-form-urlencoded"
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 201) {
            throw new Exception("Error al enviar mensaje: {$response}");
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Verificar estado de mensaje
     */
    public function getMessageStatus($messageSid) {
        $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->sid}/Messages/{$messageSid}.json";
        
        $auth = base64_encode("{$this->apiKey}:{$this->apiSecret}");
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Basic {$auth}"
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
    }
    
    /**
     * Obtener clientes según segmento
     */
    public function getClientesBySegmento($segmento) {
        $where = "empresa_id = ?";
        $params = [$this->empresaId];
        
        switch ($segmento) {
            case 'activos':
                $where .= " AND ultima_compra >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                break;
            case 'inactivos':
                $where .= " AND (ultima_compra IS NULL OR ultima_compra < DATE_SUB(NOW(), INTERVAL 90 DAY))";
                break;
            case 'premium':
                $where .= " AND total_compras >= 100000";
                break;
            case 'con_whatsapp':
                $where .= " AND whatsapp IS NOT NULL AND whatsapp != ''";
                break;
            case 'todos':
            default:
                // Sin filtro adicional
                break;
        }
        
        return fetchAll("SELECT id, nombre, whatsapp FROM clientes WHERE {$where}", $params);
    }
    
    /**
     * Validar número de WhatsApp
     */
    public function validarNumero($numero) {
        // Eliminar caracteres no numéricos
        $numero = preg_replace('/[^0-9]/', '', $numero);
        
        // Validar longitud (Argentina: 10-11 dígitos con código de país)
        if (strlen($numero) < 10 || strlen($numero) > 15) {
            return false;
        }
        
        // Agregar código de país si no tiene
        if (strlen($numero) === 10) {
            $numero = '54' . $numero;
        }
        
        return $numero;
    }
}
