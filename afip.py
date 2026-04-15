import json
import random
from datetime import datetime, timedelta

def facturar(db_conn, venta_id, total, empresa_id, tipo_cbte=11, cliente=None):
    try:
        res = _facturar_dev(db_conn, venta_id, total, empresa_id, tipo_cbte, cliente)

        # 🔹 NUEVO: validación fuerte
        if not isinstance(res, dict):
            print("⚠️ afip.facturar devolvió algo inválido:", res)
            return {
                "cae": None,
                "nro_cbte": 0,
                "resultado": "E",
                "error": "Respuesta inválida"
            }

        return res

    except Exception as e:
        print("🔥 ERROR EN facturar():", e)
        return {
            "cae": None,
            "nro_cbte": 0,
            "resultado": "E",
            "error": str(e)
        }


def _facturar_dev(db_conn, venta_id, total, empresa_id, tipo_cbte, cliente):
    cursor = db_conn.cursor()

    # 🔹 DEBUG
    print(f"DEBUG AFIP DEV → venta_id={venta_id}, total={total}, empresa={empresa_id}")

    nro_cbte = _obtener_siguiente_numero(cursor, tipo_cbte, empresa_id)
    cae = _generar_cae_fake()
    fecha_vto = datetime.now() + timedelta(days=10)

    response = {
        "cae": cae,
        "nro_cbte": nro_cbte,
        "punto_vta": 1,
        "resultado": "A",
        "empresa_id": empresa_id
    }

    # 🔹 DEBUG
    print("DEBUG RESPONSE AFIP:", response)

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

    # 🔹 IMPORTANTE: aseguramos que siempre devuelva dict válido
    return response


def _obtener_siguiente_numero(cursor, tipo_cbte, empresa_id):
    cursor.execute("""
        SELECT MAX(nro_cbte) FROM comprobante_afip 
        WHERE tipo_cbte = %s AND empresa_id = %s
    """, (tipo_cbte, empresa_id))
    row = cursor.fetchone()

    # 🔹 DEBUG
    print("DEBUG ultimo nro_cbte:", row)

    if row and row[0] is not None:
        return int(row[0]) + 1
    return 1


def _guardar_comprobante(cursor, venta_id, empresa_id, tipo_cbte, punto_vta, 
                        nro_cbte, cae, fecha_vto, estado, response):

    # 🔹 DEBUG
    print("DEBUG guardando comprobante AFIP...")

    cursor.execute("""
        INSERT INTO comprobante_afip 
        (venta_id, empresa_id, tipo_cbte, punto_vta, nro_cbte, cae, fecha_vto_cae, estado, response_afip, entorno)
        VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, 'DEV')
    """, (
        venta_id,
        empresa_id,
        tipo_cbte,
        punto_vta,
        nro_cbte,
        cae,
        fecha_vto.date(),
        estado,
        json.dumps(response)
    ))


def _generar_cae_fake():
    return "".join([str(random.randint(0, 9)) for _ in range(14)])