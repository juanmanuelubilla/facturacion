import mercadopago
from db import get_connection

def obtener_credenciales():
    """Busca las credenciales de MP en la base de datos."""
    try:
        conn = get_connection()
        with conn.cursor() as cursor:
            cursor.execute("SELECT * FROM config_pagos WHERE id=1")
            return cursor.fetchone()
    except:
        return None
    finally:
        if 'conn' in locals(): conn.close()

def generar_qr_mercadopago(venta_id, total):
    config = obtener_credenciales()
    
    # Validamos que existan datos configurados
    if not config or not config['mp_access_token']:
        print("Error: No se ha configurado el Access Token en el panel.")
        return None

    try:
        sdk = mercadopago.SDK(config['mp_access_token'])
        
        orden_data = {
            "external_reference": str(venta_id),
            "title": f"Venta Nro {venta_id}",
            "total_amount": float(total),
            "items": [
                {
                    "title": "Venta General",
                    "unit_price": float(total),
                    "quantity": 1,
                    "unit_measure": "unit",
                    "total_amount": float(total)
                }
            ],
            "cash_out": {"amount": 0}
        }

        # Usamos el user_id y external_id de la DB
        result = sdk.instore_order().create(config['mp_user_id'], config['mp_external_id'], orden_data)
        
        if result["status"] in [200, 201]:
            return result["response"].get("qr_data")
        return None
            
    except Exception as e:
        print(f"Error MP: {e}")
        return None