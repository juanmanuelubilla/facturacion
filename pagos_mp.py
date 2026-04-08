import mercadopago
from db import get_connection

def obtener_config_mp():
    """Trae la configuración desde la base de datos."""
    try:
        conn = get_connection()
        with conn.cursor() as cursor:
            cursor.execute("SELECT mp_access_token, mp_user_id, mp_external_id FROM config_pagos WHERE id=1")
            return cursor.fetchone()
    except Exception as e:
        print(f"Error accediendo a la DB de configuración: {e}")
        return None
    finally:
        if 'conn' in locals(): conn.close()

def generar_qr_mercadopago(venta_id, total):
    config = obtener_config_mp()
    
    # 1. Validación de seguridad
    if not config or not config['mp_access_token'] or not config['mp_user_id']:
        print("ERROR: Credenciales de Mercado Pago incompletas en la configuración.")
        return None

    try:
        # 2. Inicializar SDK con el token de la DB
        sdk = mercadopago.SDK(config['mp_access_token'])
        
        # 3. Estructura de la orden (QR Dinámico)
        # external_reference es la clave para vincular MP con TU base de datos
        orden_data = {
            "external_reference": f"VENTA_{venta_id}",
            "title": f"Pago Venta #{venta_id}",
            "total_amount": float(total),
            "description": "Cobro desde Sistema POS Nexus",
            "items": [
                {
                    "sku_number": str(venta_id),
                    "category": "POS_SALE",
                    "title": "Venta General de Productos",
                    "unit_price": float(total),
                    "quantity": 1,
                    "unit_measure": "unit",
                    "total_amount": float(total)
                }
            ],
            "cash_out": {"amount": 0}
        }

        # 4. Llamada a la API de Instore Orders
        # Requiere el User ID y el ID de la caja (External ID)
        result = sdk.instore_order().create(
            config['mp_user_id'], 
            config['mp_external_id'], 
            orden_data
        )
        
        # 5. Respuesta
        if result["status"] in [200, 201]:
            return result["response"].get("qr_data")
        else:
            # Imprime el error real de MP para poder arreglarlo (Ej: Token vencido)
            print(f"Error API MP: Status {result['status']} - {result['response']}")
            return None
            
    except Exception as e:
        print(f"Fallo crítico en conexión con Mercado Pago: {e}")
        return None