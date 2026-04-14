import mercadopago
import requests
from db import get_connection

def obtener_config_pagos():
    """Trae todas las credenciales desde la DB."""
    try:
        conn = get_connection()
        with conn.cursor() as cursor:
            # Seleccionamos la configuración de la empresa
            cursor.execute("SELECT * FROM config_pagos WHERE id=1")
            return cursor.fetchone()
    except Exception as e:
        print(f"Error DB: {e}")
        return None
    finally:
        if 'conn' in locals() and conn: conn.close()

# --- LÓGICA MERCADO PAGO ---
def generar_qr_mercadopago(venta_id, total):
    config = obtener_config_pagos()
    if not config or not config.get('mp_access_token'): return None

    try:
        sdk = mercadopago.SDK(config['mp_access_token'])
        orden_data = {
            "external_reference": f"V_{venta_id}",
            "total_amount": float(total),
            "items": [{"title": "Venta POS", "quantity": 1, "unit_price": float(total)}]
        }
        result = sdk.instore_order().create(config['mp_user_id'], config['mp_external_id'], orden_data)
        return result["response"].get("qr_data") if result["status"] in [200, 201] else None
    except:
        return None

# --- LÓGICA PAYWAY (PRISMA) ---
def generar_qr_payway(venta_id, total):
    config = obtener_config_pagos()
    api_key = config.get('pw_api_key') 
    if not api_key: return None

    url = "https://api.payway.com.ar/checkout/v1/qr/generar" 
    headers = {
        "Authorization": f"Bearer {api_key}",
        "Content-Type": "application/json"
    }
    payload = {
        "amount": float(total),
        "external_id": str(venta_id),
        "notification_url": "https://tudominio.com/webhook",
        "description": f"Venta {venta_id}"
    }

    try:
        response = requests.post(url, json=payload, headers=headers, timeout=10)
        if response.status_code == 200:
            return response.json().get("qr_string") 
        return None
    except Exception as e:
        print(f"Error Payway: {e}")
        return None

# --- LÓGICA MODO ---
def generar_qr_modo(venta_id, total):
    config = obtener_config_pagos()
    api_key = config.get('modo_api_key') # Campo esperado en tu DB
    if not api_key: return None

    url = "https://api.modo.com.ar/checkout/v1/qr" 
    headers = {
        "Authorization": f"Bearer {api_key}",
        "Content-Type": "application/json"
    }
    payload = {
        "amount": float(total),
        "externalId": str(venta_id),
        "callbackUrl": "https://tudominio.com/modo_webhook"
    }

    try:
        response = requests.post(url, json=payload, headers=headers, timeout=10)
        if response.status_code == 200:
            return response.json().get("qrData")
        return None
    except Exception as e:
        print(f"Error MODO: {e}")
        return None