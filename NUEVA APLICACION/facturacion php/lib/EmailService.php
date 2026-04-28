<?php
require_once 'config.php';

class EmailService {
    private $config;
    private $empresa_id;
    
    public function __construct($empresa_id) {
        $this->empresa_id = $empresa_id;
        $this->config = $this->getEmailConfig();
    }
    
    /**
     * Obtener configuración de email de la base de datos
     */
    private function getEmailConfig() {
        $config = fetch("SELECT email_host, email_port, email_username, email_password, 
                              email_encryption, email_from_name, email_from_email 
                        FROM nombre_negocio WHERE empresa_id = ?", [$this->empresa_id]);
        
        if (!$config || empty($config['email_host']) || empty($config['email_username'])) {
            return null;
        }
        
        return $config;
    }
    
    /**
     * Verificar si el email está configurado
     */
    public function isConfigured() {
        return $this->config !== null;
    }
    
    /**
     * Enviar email usando PHPMailer
     */
    public function enviarEmail($destinatario, $asunto, $contenido, $adjuntos = []) {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Email no configurado'];
        }
        
        try {
            // Incluir PHPMailer (necesitarás agregar esto al proyecto)
            require_once 'PHPMailer/PHPMailer.php';
            require_once 'PHPMailer/SMTP.php';
            require_once 'PHPMailer/Exception.php';
            
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
            // Configuración del servidor
            $mail->isSMTP();
            $mail->Host = $this->config['email_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->config['email_username'];
            $mail->Password = $this->config['email_password'];
            $mail->SMTPSecure = $this->config['email_encryption'] === 'ssl' ? 'ssl' : 'tls';
            $mail->Port = $this->config['email_port'];
            
            // Remitente
            $mail->setFrom($this->config['email_from_email'], $this->config['email_from_name']);
            $mail->addAddress($destinatario);
            
            // Contenido
            $mail->isHTML(true);
            $mail->Subject = $asunto;
            $mail->Body = $contenido;
            $mail->AltBody = strip_tags($contenido);
            
            // Adjuntos
            foreach ($adjuntos as $archivo) {
                if (file_exists($archivo)) {
                    $mail->addAttachment($archivo);
                }
            }
            
            $mail->send();
            
            return ['success' => true, 'message' => 'Email enviado exitosamente'];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Enviar presupuesto por email
     */
    public function enviarPresupuesto($presupuesto_id, $destinatario_email) {
        try {
            // Obtener datos del presupuesto
            $presupuesto_service = new PresupuestoService($this->empresa_id, $_SESSION['user_id']);
            $presupuesto = $presupuesto_service->obtenerPresupuestoCompleto($presupuesto_id);
            
            if (!$presupuesto) {
                return ['success' => false, 'error' => 'Presupuesto no encontrado'];
            }
            
            // Generar PDF del presupuesto
            $pdf_generator = new PDFGenerator();
            $pdf_result = $pdf_generator->generarPresupuestoPDF($presupuesto);
            
            if (!$pdf_result['success']) {
                return ['success' => false, 'error' => 'Error al generar PDF'];
            }
            
            // Preparar contenido del email
            $asunto = "Presupuesto #{$presupuesto['numero_presupuesto']} - {$presupuesto['titulo']}";
            
            $contenido = $this->generarContenidoPresupuesto($presupuesto);
            
            // Enviar email con PDF adjunto
            $resultado = $this->enviarEmail($destinatario_email, $asunto, $contenido, [$pdf_result['archivo']]);
            
            // Eliminar archivo temporal
            if (file_exists($pdf_result['archivo'])) {
                unlink($pdf_result['archivo']);
            }
            
            return $resultado;
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Generar contenido HTML del presupuesto
     */
    private function generarContenidoPresupuesto($presupuesto) {
        $nombre_cliente = $presupuesto['cliente_nombre'] . ' ' . $presupuesto['cliente_apellido'];
        $fecha_creacion = date('d/m/Y H:i', strtotime($presupuesto['fecha_creacion']));
        $fecha_vencimiento = $presupuesto['fecha_vencimiento'] ? date('d/m/Y', strtotime($presupuesto['fecha_vencimiento'])) : 'Sin vencimiento';
        
        $html = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { background: #2c3e50; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .details { margin: 20px 0; }
                .details table { width: 100%; border-collapse: collapse; }
                .details th, .details td { padding: 10px; border: 1px solid #ddd; text-align: left; }
                .details th { background: #34495e; color: white; }
                .total { font-weight: bold; background: #ecf0f1; }
                .footer { text-align: center; padding: 20px; color: #7f8c8d; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>📋 PRESUPUESTO</h1>
                <h2>N° {$presupuesto['numero_presupuesto']}</h2>
            </div>
            
            <div class='content'>
                <h3>{$presupuesto['titulo']}</h3>
                
                <div class='details'>
                    <table>
                        <tr>
                            <th>Cliente:</th>
                            <td>{$nombre_cliente}</td>
                            <th>Fecha:</th>
                            <td>{$fecha_creacion}</td>
                        </tr>
                        <tr>
                            <th>Vencimiento:</th>
                            <td>{$fecha_vencimiento}</td>
                            <th>Estado:</th>
                            <td>" . ucfirst($presupuesto['estado']) . "</td>
                        </tr>
                    </table>
                </div>
                
                <h4>Productos/Servicios</h4>
                <table class='details'>
                    <thead>
                        <tr>
                            <th>Descripción</th>
                            <th>Cantidad</th>
                            <th>Precio Unitario</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>";
        
        foreach ($presupuesto['detalles'] as $detalle) {
            $subtotal = $detalle['cantidad'] * $detalle['precio_unitario'];
            $html .= "
                        <tr>
                            <td>{$detalle['descripcion']}</td>
                            <td>" . number_format($detalle['cantidad'], 2) . "</td>
                            <td>$" . number_format($detalle['precio_unitario'], 2) . "</td>
                            <td>$" . number_format($subtotal, 2) . "</td>
                        </tr>";
        }
        
        $html .= "
                    </tbody>
                    <tfoot>
                        <tr class='total'>
                            <td colspan='3'>Subtotal:</td>
                            <td>$" . number_format($presupuesto['subtotal'], 2) . "</td>
                        </tr>
                        <tr class='total'>
                            <td colspan='3'>Total:</td>
                            <td>$" . number_format($presupuesto['total'], 2) . "</td>
                        </tr>
                    </tfoot>
                </table>
                
                " . (!empty($presupuesto['observaciones']) ? "<h4>Observaciones</h4><p>" . nl2br(htmlspecialchars($presupuesto['observaciones'])) . "</p>" : "") . "
                
                <p><strong>Validez del presupuesto:</strong> {$presupuesto['validez_dias']} días</p>
            </div>
            
            <div class='footer'>
                <p>Este presupuesto fue generado automáticamente por NEXUS POS</p>
                <p>Para cualquier consulta, contacte con nosotros</p>
            </div>
        </body>
        </html>";
        
        return $html;
    }
    
    /**
     * Guardar configuración de email
     */
    public static function guardarConfiguracion($empresa_id, $config) {
        $sql = "UPDATE nombre_negocio SET 
                email_host = ?, 
                email_port = ?, 
                email_username = ?, 
                email_password = ?, 
                email_encryption = ?, 
                email_from_name = ?, 
                email_from_email = ? 
                WHERE empresa_id = ?";
        
        return query($sql, [
            $config['email_host'],
            $config['email_port'],
            $config['email_username'],
            $config['email_password'],
            $config['email_encryption'],
            $config['email_from_name'],
            $config['email_from_email'],
            $empresa_id
        ]);
    }
    
    /**
     * Probar conexión de email
     */
    public function probarConexion() {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Email no configurado'];
        }
        
        try {
            require_once 'PHPMailer/PHPMailer.php';
            require_once 'PHPMailer/SMTP.php';
            require_once 'PHPMailer/Exception.php';
            
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
            // Configuración del servidor
            $mail->isSMTP();
            $mail->Host = $this->config['email_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->config['email_username'];
            $mail->Password = $this->config['email_password'];
            $mail->SMTPSecure = $this->config['email_encryption'] === 'ssl' ? 'ssl' : 'tls';
            $mail->Port = $this->config['email_port'];
            
            // Probar conexión SMTP
            $mail->SMTPDebug = 0; // Desactivar debug
            $mail->smtpConnect();
            
            return ['success' => true, 'message' => 'Conexión SMTP exitosa'];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
?>
