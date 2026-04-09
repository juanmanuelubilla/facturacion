import json
import random
from datetime import datetime, timedelta

def facturar(db_conn, venta_id, total, empresa_id, tipo_cbte=11, cliente=None):
    """
    Función principal para facturar vinculada a una empresa.
    """
    # En MODO DEV o PROD, ahora pasamos el empresa_id
    return _facturar_dev(db_conn, venta_id, total, empresa_id, tipo_cbte, cliente)

def _facturar_dev(db_conn, venta_id, total, empresa_id, tipo_cbte, cliente):
    cursor = db_conn.cursor()

    # Buscamos el último número de factura DE ESTA EMPRESA
    nro_cbte = _obtener_siguiente_numero(cursor, tipo_cbte, empresa_id)

    cae = _generar_cae_fake()
    fecha_vto = datetime.now() + timedelta(days=10)

    response = {
        "cae": cae,
        "nro_cbte": nro_cbte,
        "punto_vta": 1, # Podrías traerlo de una tabla de sucursales
        "resultado": "A",
        "empresa_id": empresa_id
    }

    # Guardamos el comprobante con su empresa_id
    _guardar_comprobante(
        cursor=cursor,
        venta_id=venta_id,
        empresa_id=empresa_id,
        tipo_cbte=tipo_cbte,
        punto_vta=1,
        nro_cbte=nro_cbte,
        cae=cae,
        fecha_vto=fecha_vto,
        estado="APROBADO",
        response=response
    )

    db_conn.commit()
    return response

def _obtener_siguiente_numero(cursor, tipo_cbte, empresa_id):
    # CRITICO: El conteo es por tipo_cbte Y empresa_id
    cursor.execute("""
        SELECT MAX(nro_cbte)
        FROM comprobante_afip
        WHERE tipo_cbte = %s AND empresa_id = %s
    """, (tipo_cbte, empresa_id))

    row = cursor.fetchone()
    if not row or row[0] is None:
        return 1
    return int(row[0]) + 1

def _guardar_comprobante(cursor, venta_id, empresa_id, tipo_cbte, punto_vta,
                        nro_cbte, cae, fecha_vto, estado, response):

    cursor.execute("""
        INSERT INTO comprobante_afip
        (venta_id, empresa_id, tipo_cbte, punto_vta, nro_cbte, cae,
         fecha_vto_cae, estado, response_afip, entorno)
        VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, 'DEV')
    """, (
        venta_id,
        empresa_id,
        tipo_cbte,
        punto_vta,
        nro_cbte,
        cae,
        fecha_vto.strftime("%Y-%m-%d"),
        estado,
        json.dumps(response)
    ))

def _generar_cae_fake():
    return str(random.randint(10000000000000, 99999999999999))