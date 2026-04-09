import mercadopago
from db import get_connection

def obtener_config_mp(empresa_id): # <--- Filtramos por empresa
    """Trae la configuración de MP específica de la empresa."""
    try:
        conn = get_connection()
        with conn.cursor() as cursor:
            # Buscamos las credenciales que pertenezcan a esta empresa
            cursor.execute("""
                SELECT mp_access_token, mp_user_id, mp_external_id 
                FROM config_pagos 
                WHERE empresa_id = %s
            """, (empresa_id,))
            return cursor.fetchone()
    except Exception as e:
        print(f"Error accediendo a la DB de configuración MP: {e}")
        return None
    finally:
        if 'conn' in locals(): conn.close()

def generar_qr_mercadopago(venta_id, total, empresa_id): # <--- Recibe empresa_id
    config = obtener_config_mp(empresa_id)
    
    if not config or not config['mp_access_token'] or not config['mp_user_id']:
        print(f"ERROR: Credenciales de MP no configuradas para la empresa {empresa_id}")
        return None

    try:
        sdk = mercadopago.SDK(config['mp_access_token'])
        
        orden_data = {
            "external_reference": f"V_{empresa_id}_{venta_id}", # Referencia única
            "title": f"Pago Venta #{venta_id}",
            "total_amount": float(total),
            "description": "Cobro desde Sistema POS Nexus",
            "items": [
                {
                    "sku_number": str(venta_id),
                    "category": "POS_SALE",
                    "title": "Venta General",
                    "unit_price": float(total),
                    "quantity": 1,
                    "unit_measure": "unit",
                    "total_amount": float(total)
                }
            ],
            "cash_out": {"amount": 0}
        }

        # Usamos el User ID y External ID (Caja) de esta empresa
        result = sdk.instore_order().create(
            config['mp_user_id'], 
            config['mp_external_id'], 
            orden_data
        )
        
        if result["status"] in [200, 201]:
            return result["response"].get("qr_data")
        else:
            print(f"Error API MP Empresa {empresa_id}: {result['response']}")
            return None
            
    except Exception as e:
        print(f"Fallo crítico Mercado Pago: {e}")
        return None