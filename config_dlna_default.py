# -*- coding: utf-8 -*-
"""
Configuración por Defecto del Servidor DLNA
Archivo para facilitar la configuración inicial del servidor DLNA remoto
"""

# ========================================
# CONFIGURACIÓN DE SERVIDOR DLNA
# ========================================

# Tipo de servidor (local/remoto)
TIPO_SERVIDOR = "remoto"  # Cambiar a "local" si el servidor está en esta máquina

# Configuración de servidor remoto
IP_SERVIDOR = "192.168.1.100"  # IP por defecto del servidor DLNA
PUERTO_SERVIDOR = "8080"         # Puerto por defecto del servidor DLNA

# Configuración de miniDLNA
RUTA_BANNERS = "/var/lib/minidlna/banners"  # Ruta donde se guardan los banners
INTERVALO_ESCANEO = 30                     # Segundos entre escaneos de banners

# Opciones avanzadas
INICIAR_CON_SISTEMA = True    # Iniciar DLNA automáticamente con el sistema
LOG_ACTIVADO = True            # Activar registro de eventos del servidor

# ========================================
# ENDPOINTS DE API REST
# ========================================

# URLs base para la API del servidor DLNA
BASE_URL = "http://{ip}:{puerto}"

# Endpoints disponibles
ENDPOINTS = {
    "health": "/api/health",           # Verificar estado del servidor
    "iniciar": "/api/dlna/iniciar",    # Iniciar servidor DLNA
    "pausar": "/api/dlna/pausar",     # Pausar servidor DLNA
    "detener": "/api/dlna/detener",     # Detener servidor DLNA
    "estado": "/api/dlna/estado",      # Obtener estado actual
    "banners": "/api/dlna/banners"     # Gestionar banners
}

# ========================================
# EJEMPLOS DE USO
# ========================================

# Ejemplo de cómo construir URLs
def construir_url(endpoint, ip=IP_SERVIDOR, puerto=PUERTO_SERVIDOR):
    """Construye URL completa para un endpoint específico"""
    return BASE_URL.format(ip=ip, puerto=puerto) + endpoint

# Ejemplos de URLs
EJEMPLOS_URLS = {
    "health_check": construir_url(ENDPOINTS["health"]),
    "iniciar_servidor": construir_url(ENDPOINTS["iniciar"]),
    "pausar_servidor": construir_url(ENDPOINTS["pausar"]),
    "detener_servidor": construir_url(ENDPOINTS["detener"]),
    "estado_actual": construir_url(ENDPOINTS["estado"]),
    "gestion_banners": construir_url(ENDPOINTS["banners"])
}

# ========================================
# INSTRUCCIONES DE CONFIGURACIÓN
# ========================================

INSTRUCCIONES = """
PARA CONFIGURAR EL SERVIDOR DLNA REMOTO:

1. INSTALACIÓN DEL SERVIDOR:
   - Ejecutar: ./install_dlna.sh
   - Esto instala miniDLNA y configura el servicio

2. CONFIGURACIÓN DE RED:
   - Asegurarse de que el puerto 8080 esté abierto
   - Configurar firewall si es necesario
   - Verificar conectividad desde el cliente

3. API REST ENDPOINTS:
   - GET  /api/health - Verificar estado
   - POST /api/dlna/iniciar - Iniciar servidor
   - POST /api/dlna/pausar - Pausar servidor
   - POST /api/dlna/detener - Detener servidor
   - POST /api/dlna/banners - Gestionar banners

4. EJEMPLO DE USO DESDE CLIENTE:
   import requests
   
   # Iniciar servidor
   response = requests.post('http://192.168.1.100:8080/api/dlna/iniciar', 
                           json={'action': 'iniciar'})
   
   # Verificar estado
   response = requests.get('http://192.168.1.100:8080/api/health')
   print(f"Estado del servidor: {response.json()}")

5. VALORES POR DEFECTO:
   - IP: 192.168.1.100
   - Puerto: 8080
   - Ruta banners: /var/lib/minidlna/banners
   - Intervalo: 30 segundos
"""

# ========================================
# CONFIGURACIÓN DE SEGURIDAD
# ========================================

# Tokens de API para autenticación (opcional)
API_TOKENS = {
    "admin": "DLNA_ADMIN_TOKEN_2024",
    "readonly": "DLNA_READONLY_TOKEN_2024"
}

# Headers por defecto para peticiones
DEFAULT_HEADERS = {
    "Content-Type": "application/json",
    "User-Agent": "NEXUS-DLNA-Client/1.0"
}

if __name__ == "__main__":
    print("📺 CONFIGURACIÓN POR DEFECTO DEL SERVIDOR DLNA")
    print("=" * 50)
    print(f"Tipo de servidor: {TIPO_SERVIDOR}")
    print(f"IP del servidor: {IP_SERVIDOR}")
    print(f"Puerto del servidor: {PUERTO_SERVIDOR}")
    print(f"Ruta de banners: {RUTA_BANNERS}")
    print(f"Intervalo de escaneo: {INTERVALO_ESCANEO} segundos")
    print(f"Iniciar con sistema: {INICIAR_CON_SISTEMA}")
    print(f"Log activado: {LOG_ACTIVADO}")
    print("=" * 50)
    print("\n📋 ENDPOINTS DISPONIBLES:")
    for endpoint, path in ENDPOINTS.items():
        print(f"  {endpoint}: {path}")
    print("\n📡 EJEMPLOS DE URLs:")
    for name, url in EJEMPLOS_URLS.items():
        print(f"  {name}: {url}")
    print("\n" + INSTRUCCIONES)
