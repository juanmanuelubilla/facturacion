from db import get_connection
from productos import descontar_stock

# 🔹 NUEVO: import del facturador
from facturacion_arca import FacturadorARCA


def crear_venta(empresa_id, usuario_id, cliente_id=None):
    conn = get_connection()
    try:
        cid = cliente_id if (cliente_id and cliente_id != 0) else None
        with conn.cursor() as cursor:
            cursor.execute("""
                INSERT INTO ventas (total, ganancia, usuario_id, empresa_id, cliente_id, fecha, estado) 
                VALUES (0, 0, %s, %s, %s, NOW(), 'COMPLETADA')
            """, (usuario_id, empresa_id, cid))
            venta_id = cursor.lastrowid
        conn.commit()
        return venta_id
    finally:
        conn.close()


def agregar_item(venta_id, item, cantidad):
    conn = get_connection()
    try:
        with conn.cursor() as cursor:
            precio = float(item.get("precio", 0))
            costo = float(item.get("costo", 0))
            subtotal = precio * cantidad
            cursor.execute("""
                INSERT INTO venta_items 
                (venta_id, producto_id, cantidad, precio_unitario, costo_unitario, subtotal)
                VALUES (%s, %s, %s, %s, %s, %s)
            """, (venta_id, item["id"], cantidad, precio, costo, subtotal))
        conn.commit()
    finally:
        conn.close()


def cerrar_venta(venta_id, total, items, empresa_id, ganancia=None):
    conn = get_connection()
    try:
        with conn.cursor() as cursor:
            if ganancia is not None:
                ganancia_total = ganancia
            else:
                ganancia_total = 0
                for item in items:
                    precio = float(item.get("precio", 0))
                    costo = float(item.get("costo", 0))
                    cantidad = item.get("cantidad", 0)
                    ganancia_total += (precio - costo) * cantidad

            for item in items:
                descontar_stock(item["id"], item.get("cantidad", 0), empresa_id)

            cursor.execute("""
                UPDATE ventas 
                SET total=%s, ganancia=%s
                WHERE id=%s AND empresa_id=%s
            """, (total, ganancia_total, venta_id, empresa_id))
        conn.commit()
    finally:
        conn.close()


def registrar_pago(venta_id, monto, metodo_pago, entregado, vuelto, empresa_id):
    conn = get_connection()
    try:
        with conn.cursor() as cursor:
            cursor.execute("""
                INSERT INTO pagos (venta_id, empresa_id, metodo, monto, entregado, vuelto, estado)
                VALUES (%s, %s, %s, %s, %s, %s, 'completado')
            """, (venta_id, empresa_id, metodo_pago, monto, entregado, vuelto))
        conn.commit()

        # 🔹 NUEVO: FACTURACIÓN AUTOMÁTICA AFIP
        try:
            facturador = FacturadorARCA(empresa_id)

            # Obtener total real de la venta
            with conn.cursor() as cursor:
                cursor.execute("SELECT total, cliente_id FROM ventas WHERE id=%s", (venta_id,))
                row = cursor.fetchone()

                total = float(row[0])
                cliente_id = row[1]

            # Si no hay cliente → consumidor final
            dni_cliente = None

            resultado = facturador.emitir_factura_c(
                venta_id=venta_id,
                punto_venta=1,
                dni_cliente=dni_cliente,
                total=total
            )

            print("AFIP OK:", resultado)

        except Exception as e:
            print("ERROR AFIP:", e)

    finally:
        conn.close()