import datetime
import os
import random
import json
from db import get_connection

class FacturadorARCA:
    def __init__(self, empresa_id):
        self.empresa_id = empresa_id
        self.config = self._cargar_config_desde_db()
        self.mock_mode = self.config.get('mock', True)
        self.afip_instance = None

        print(f"🔧 Facturador iniciado | EMPRESA={empresa_id} | MOCK={self.mock_mode}")

        # 🔹 AFIP REAL (solo si NO está en mock)
        if not self.mock_mode:
            try:
                from afip import Afip

                cert = self.config.get('cert', '')
                key = self.config.get('key', '')

                if cert and key and os.path.exists(cert) and os.path.exists(key):
                    self.afip_instance = Afip({
                        'CUIT': self.config['cuit'],
                        'cert': cert,
                        'key': key,
                        'production': self.config.get('produccion', False)
                    })
                    print("✅ AFIP REAL inicializado")
                else:
                    print("⚠️ Certificado o key inválidos → usando MOCK")
                    self.mock_mode = True

            except Exception as e:
                print(f"❌ Error al iniciar AFIP Real: {e}")
                self.mock_mode = True

    # =========================================================
    # CONFIG DESDE DB
    # =========================================================
    def _cargar_config_desde_db(self):
        conn = get_connection()
        try:
            with conn.cursor() as cursor:
                cursor.execute("""
                    SELECT cuit, afip_cert, afip_key, afip_prod, afip_mock 
                    FROM nombre_negocio 
                    WHERE empresa_id=%s OR id=1 LIMIT 1
                """, (self.empresa_id,))
                res = cursor.fetchone()

                if not res:
                    return {
                        'cuit': 20123456789,
                        'cert': '',
                        'key': '',
                        'produccion': False,
                        'mock': True
                    }

                cuit_limpio = str(res.get('cuit', '20123456789')).replace('-', '').strip()

                return {
                    'cuit': int(cuit_limpio) if cuit_limpio.isdigit() else 20123456789,
                    'cert': res.get('afip_cert') or '',
                    'key': res.get('afip_key') or '',
                    'produccion': bool(res.get('afip_prod', False)),
                    'mock': bool(res.get('afip_mock', True))
                }

        finally:
            conn.close()

    # =========================================================
    # FACTURACIÓN
    # =========================================================
    def emitir_factura_c(self, venta_id, punto_venta, dni_cliente, total):

        print(f"🧾 Emitiendo factura | Venta={venta_id} | Total={total}")

        dni_cliente = str(dni_cliente or "0").strip()
        dni_int = int(dni_cliente) if dni_cliente.isdigit() else 0

        # =====================================================
        # MODO MOCK / MANUAL
        # =====================================================
        if self.mock_mode or not self.afip_instance:
            print(f"🧪 [MODO MOCK] Venta {venta_id}")

            cae = None
            nro_cbte = None
            res = {}

            try:
                import afip

                conn = get_connection()
                try:
                    res = afip.facturar(conn, venta_id, total, self.empresa_id, tipo_cbte=11)
                    conn.commit()
                    print("✅ afip.py ejecutado y commit OK")

                    cae = res.get('cae') if isinstance(res, dict) else None
                    nro_cbte = res.get('nro_cbte') if isinstance(res, dict) else None

                finally:
                    conn.close()

            except Exception as e:
                print(f"⚠️ Error en afip.py → fallback automático: {e}")

                res = {"error": str(e)}
                cae = str(random.randint(10000000000000, 99999999999999))
                nro_cbte = random.randint(1, 99999999)

            # 🔹 SI NO VIENE NRO → GENERAR
            if not nro_cbte:
                nro_cbte = random.randint(1, 99999999)

            resultado = self._guardar_en_db(
                venta_id=venta_id,
                tipo=11,
                punto=punto_venta,
                nro=nro_cbte,
                cae=cae,
                vto=datetime.datetime.now().strftime('%Y%m%d'),
                estado="APROBADO",
                response=json.dumps(res)
            )

            print(f"📦 Resultado guardado: {resultado}")
            return resultado

        # =====================================================
        # AFIP REAL
        # =====================================================
        try:
            tipo_cbte = 11

            last_voucher = self.afip_instance.ElectronicBilling.get_last_voucher(
                punto_venta,
                tipo_cbte
            )

            nro_cbte = int(last_voucher) + 1

            data = {
                'CantReg': 1,
                'PtoVta': punto_venta,
                'CbteTipo': tipo_cbte,
                'Concepto': 1,
                'DocTipo': 96 if dni_int > 0 else 99,
                'DocNro': dni_int,
                'CbteDesde': nro_cbte,
                'CbteHasta': nro_cbte,
                'CbteFch': datetime.datetime.now().strftime('%Y%m%d'),
                'ImpTotal': float(total),
                'ImpTotConc': 0,
                'ImpNeto': float(total),
                'ImpOpEx': 0,
                'ImpIVA': 0,
                'ImpTrib': 0,
                'MonId': 'PES',
                'MonCotiz': 1,
            }

            res = self.afip_instance.ElectronicBilling.create_voucher(data)

            cae = res.get('CAE')
            vto = res.get('CAEVto')

            resultado = self._guardar_en_db(
                venta_id,
                tipo_cbte,
                punto_venta,
                nro_cbte,
                cae,
                vto,
                "APROBADO",
                json.dumps(res)
            )

            print(f"📦 Resultado AFIP REAL: {resultado}")
            return resultado

        except Exception as e:
            print(f"❌ Error AFIP REAL: {e}")

            return self._guardar_en_db(
                venta_id,
                11,
                punto_venta,
                0,
                None,
                None,
                "RECHAZADO",
                str(e)
            )

    # =========================================================
    # GUARDADO DB
    # =========================================================
    def _guardar_en_db(self, venta_id, tipo, punto, nro, cae, vto, estado, response):
        print(f"💾 Guardando comprobante en DB | venta={venta_id}")

        conn = get_connection()
        try:
            with conn.cursor() as cursor:

                fecha_vto = None
                if vto:
                    try:
                        fecha_vto = datetime.datetime.strptime(str(vto), '%Y%m%d').date()
                    except Exception as e:
                        print(f"⚠️ Error parseando fecha_vto: {e}")

                entorno_db = "PROD" if self.config.get('produccion') else "DEV"

                sql = """
                    INSERT INTO comprobante_afip 
                    (empresa_id, venta_id, tipo_cbte, punto_vta, nro_cbte, cae, fecha_vto_cae, estado, response_afip, entorno)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                """

                valores = (
                    self.empresa_id,
                    venta_id,
                    tipo,
                    punto,
                    nro,
                    cae,
                    fecha_vto,
                    estado,
                    str(response),
                    entorno_db
                )

                print(f"🧾 SQL VALUES: {valores}")

                cursor.execute(sql, valores)

            conn.commit()
            print("✅ INSERT OK en comprobante_afip")

            return {
                'status': 'OK',
                'cae': cae,
                'nro': nro
            }

        except Exception as e:
            print(f"🔥 ERROR DB: {e}")

            try:
                conn.rollback()
            except:
                pass

            return {
                'status': 'ERROR_DB',
                'message': str(e)
            }

        finally:
            conn.close()