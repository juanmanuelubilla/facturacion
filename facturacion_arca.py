import datetime
import os
import random
import json
from db import get_connection
import afip 

class FacturadorARCA:
    def __init__(self, empresa_id):
        self.empresa_id = empresa_id
        self.config = self._cargar_config_desde_db()
        self.mock_mode = self.config.get('mock', False)
        self.afip_instance = None
        
        if not self.mock_mode:
            try:
                from afip import Afip
                if os.path.exists(self.config['cert']) and os.path.exists(self.config['key']):
                    self.afip_instance = Afip({
                        'CUIT': self.config['cuit'],
                        'cert': self.config['cert'],
                        'key': self.config['key'],
                        'production': self.config['produccion']
                    })
            except Exception as e:
                print(f"❌ Error al iniciar AFIP Real, se usará modo manual/mock: {e}")
                self.mock_mode = True

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
                    return {'cuit': 20123456789, 'cert': '', 'key': '', 'produccion': False, 'mock': True}
                
                cuit_limpio = str(res.get('cuit', '20123456789')).replace('-', '').strip()
                
                return {
                    'cuit': int(cuit_limpio) if cuit_limpio.isdigit() else 20123456789,
                    'cert': res.get('afip_cert', ''),
                    'key': res.get('afip_key', ''),
                    'produccion': bool(res.get('afip_prod', False)),
                    'mock': bool(res.get('afip_mock', True))
                }
        finally:
            conn.close()

    def emitir_factura_c(self, venta_id, punto_venta, dni_cliente, total):
        # --- LÓGICA DE TU AFIP.PY (MANUAL/DEV) ---
        if self.mock_mode or not self.afip_instance:
            print(f"🧪 [MODO MANUAL] Procesando Venta ID: {venta_id} mediante afip.py")
            conn = get_connection()
            try:
                # Llamamos a la función 'facturar' de tu archivo afip.py
                res = afip.facturar(conn, venta_id, total, self.empresa_id, tipo_cbte=11)
                
                # --- CORRECCIÓN CRÍTICA: HACER EL COMMIT ---
                conn.commit() 
                print(f"✅ Datos confirmados en la DB para venta {venta_id}")

                # 🔹 NUEVO: guardar también en comprobante_afip
                cae = res.get('cae') if isinstance(res, dict) else None
                nro_cbte = res.get('nro_cbte') if isinstance(res, dict) else None

                return self._guardar_en_db(
                    venta_id=venta_id,
                    tipo=11,
                    punto=punto_venta,
                    nro=nro_cbte or random.randint(1, 99999999),
                    cae=cae,
                    vto=datetime.datetime.now().strftime('%Y%m%d'),
                    estado="APROBADO",
                    response=json.dumps(res)
                )

            except Exception as e:
                print(f"🔥 Error en lógica manual: {e}")
                if conn: conn.rollback()

                # 🔹 NUEVO: guardar error también en comprobante_afip
                return self._guardar_en_db(
                    venta_id=venta_id,
                    tipo=11,
                    punto=punto_venta,
                    nro=0,
                    cae=None,
                    vto=None,
                    estado="ERROR",
                    response=str(e)
                )

            finally:
                if conn: conn.close()

        # --- LÓGICA AFIP REAL ---
        try:
            tipo_cbte = 11
            last_voucher = self.afip_instance.ElectronicBilling.get_last_voucher(punto_venta, tipo_cbte)
            nro_cbte = int(last_voucher) + 1
            
            data = {
                'CantReg': 1, 'PtoVta': punto_venta, 'CbteTipo': tipo_cbte, 'Concepto': 1,
                'DocTipo': 96 if int(dni_cliente or 0) > 0 else 99, 
                'DocNro': int(dni_cliente or 0), 'CbteDesde': nro_cbte, 'CbteHasta': nro_cbte,
                'CbteFch': datetime.datetime.now().strftime('%Y%m%d'),
                'ImpTotal': total, 'ImpTotConc': 0, 'ImpNeto': total, 'ImpOpEx': 0,
                'ImpIVA': 0, 'ImpTrib': 0, 'MonId': 'PES', 'MonCotiz': 1,
            }

            res = self.afip_instance.ElectronicBilling.create_voucher(data)

            return self._guardar_en_db(
                venta_id,
                tipo_cbte,
                punto_venta,
                nro_cbte,
                res['CAE'],
                res['CAEVto'],
                "APROBADO",
                str(res)
            )

        except Exception as e:
            print(f"❌ Error Real ARCA: {e}")
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

    def _guardar_en_db(self, venta_id, tipo, punto, nro, cae, vto, estado, response):
        conn = get_connection()
        try:
            with conn.cursor() as cursor:
                fecha_vto = None
                if vto:
                    try:
                        fecha_vto = datetime.datetime.strptime(str(vto), '%Y%m%d').date()
                    except:
                        fecha_vto = None

                entorno_db = "PROD" if self.config.get('produccion') else "DEV"

                sql = """
                    INSERT INTO comprobante_afip 
                    (empresa_id, venta_id, tipo_cbte, punto_vta, nro_cbte, cae, fecha_vto_cae, estado, response_afip, entorno)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                """

                cursor.execute(sql, (
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
                ))

            conn.commit()
            return {'status': 'OK', 'cae': cae, 'nro': nro}

        except Exception as e:
            print(f"🔥 ERROR AL GUARDAR EN DB: {e}")
            return {'status': 'ERROR_DB', 'message': str(e)}

        finally:
            conn.close()