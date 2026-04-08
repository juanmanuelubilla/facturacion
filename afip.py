import json
import random
from datetime import datetime, timedelta

# =========================
# CONFIGURACIÓN
# =========================

class AFIPConfig:
    MODO = "DEV"  # DEV / PROD
    PUNTO_VENTA = 1

# =========================
# API PÚBLICA
# =========================

def facturar(db_conn, venta_id, total, tipo_cbte=11, cliente=None):
    """
    Función principal para facturar.

    tipo_cbte:
        1  = Factura A
        6  = Factura B
        11 = Factura C
    """

    if AFIPConfig.MODO == "DEV":
        return _facturar_dev(db_conn, venta_id, total, tipo_cbte, cliente)
    else:
        return _facturar_prod(db_conn, venta_id, total, tipo_cbte, cliente)

# =========================
# MODO DEV (SIMULACIÓN)
# =========================

def _facturar_dev(db_conn, venta_id, total, tipo_cbte, cliente):
    cursor = db_conn.cursor()

    nro_cbte = _obtener_siguiente_numero(cursor, tipo_cbte)

    cae = _generar_cae_fake()
    fecha_vto = datetime.now() + timedelta(days=10)

    response = {
        "cae": cae,
        "nro_cbte": nro_cbte,
        "punto_vta": AFIPConfig.PUNTO_VENTA,
        "resultado": "A",
        "obs": None,
        "cliente": cliente,
        "total": float(total),
        "modo": "DEV"
    }

    _guardar_comprobante(
        cursor=cursor,
        venta_id=venta_id,
        tipo_cbte=tipo_cbte,
        punto_vta=AFIPConfig.PUNTO_VENTA,
        nro_cbte=nro_cbte,
        cae=cae,
        fecha_vto=fecha_vto,
        estado="APROBADO",
        response=response
    )

    db_conn.commit()

    return response

# =========================
# UTILIDADES DEV
# =========================

def _generar_cae_fake():
    return str(random.randint(10000000000000, 99999999999999))

# =========================
# NUMERACIÓN
# =========================

def _obtener_siguiente_numero(cursor, tipo_cbte):
    cursor.execute("""
        SELECT MAX(nro_cbte)
        FROM comprobante_afip
        WHERE tipo_cbte = %s AND punto_vta = %s
    """, (tipo_cbte, AFIPConfig.PUNTO_VENTA))

    row = cursor.fetchone()

    if not row or row[0] is None:
        return 1

    return int(row[0]) + 1

# =========================
# GUARDADO
# =========================

def _guardar_comprobante(cursor, venta_id, tipo_cbte, punto_vta,
                        nro_cbte, cae, fecha_vto, estado, response):

    cursor.execute("""
        INSERT INTO comprobante_afip
        (venta_id, tipo_cbte, punto_vta, nro_cbte, cae,
         fecha_vto_cae, estado, response_afip, entorno)
        VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)
    """, (
        venta_id,
        tipo_cbte,
        punto_vta,
        nro_cbte,
        cae,
        fecha_vto.strftime("%Y-%m-%d"),
        estado,
        json.dumps(response),
        AFIPConfig.MODO
    ))

# =========================
# PRODUCCIÓN (FUTURO)
# =========================

def _facturar_prod(db_conn, venta_id, total, tipo_cbte, cliente):
    """
    Acá va la integración real con AFIP (WSFE).
    """

    raise NotImplementedError("Integración con AFIP no implementada aún")

# =========================
# CONSULTAS (ÚTIL PARA TICKET)
# =========================

def obtener_comprobante_por_venta(cursor, venta_id):
    cursor.execute("""
        SELECT tipo_cbte, punto_vta, nro_cbte, cae, fecha_vto_cae
        FROM comprobante_afip
        WHERE venta_id = %s
        ORDER BY id DESC
        LIMIT 1
    """, (venta_id,))

    row = cursor.fetchone()

    if not row:
        return None

    return {
        "tipo_cbte": row[0],
        "punto_vta": row[1],
        "nro_cbte": row[2],
        "cae": row[3],
        "fecha_vto": row[4]
    }

# =========================
# FORMATOS
# =========================

def formatear_numero_comprobante(punto_vta, nro_cbte):
    return f"{punto_vta:04d}-{nro_cbte:08d}"

def tipo_cbte_to_texto(tipo):
    return {
        1: "Factura A",
        6: "Factura B",
        11: "Factura C"
    }.get(tipo, "Comprobante")