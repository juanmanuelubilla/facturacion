from datetime import datetime

def generar_ticket(items, total, venta_id, metodo_pago="EFECTIVO", vuelto=None):
    """
    Genera el ticket de venta
    
    Args:
        items: Lista de items vendidos
        total: Total de la venta
        venta_id: ID de la venta
        metodo_pago: Método de pago (EFECTIVO, QR, TRANSFERENCIA, TARJETA)
        vuelto: Vuelto para pagos en efectivo (opcional)
    """
    fecha = datetime.now().strftime("%d/%m/%Y %H:%M:%S")

    lineas = []
    lineas.append("================================")
    lineas.append("        TU NEGOCIO S.A.")
    lineas.append("     CUIT: 30-00000000-0")
    lineas.append("--------------------------------")
    lineas.append(f"Venta N°: {venta_id}")
    lineas.append(f"Fecha: {fecha}")
    lineas.append("--------------------------------")

    for item in items:
        nombre = item["nombre"][:20]
        cantidad = item["cantidad"]
        precio = float(item["precio"])
        subtotal = float(item["subtotal"])

        lineas.append(f"{nombre}")
        lineas.append(f"{cantidad} x ${precio:.2f} = ${subtotal:.2f}")

    lineas.append("--------------------------------")
    lineas.append(f"TOTAL: ${float(total):.2f}")
    lineas.append("--------------------------------")
    
    # Agregar método de pago
    metodo_texto = {
        "EFECTIVO": "💵 EFECTIVO",
        "QR": "📱 QR",
        "TRANSFERENCIA": "💸 TRANSFERENCIA",
        "TARJETA": "💳 TARJETA"
    }.get(metodo_pago, metodo_pago)
    
    lineas.append(f"Método de pago: {metodo_texto}")
    
    # Agregar vuelto si es efectivo
    if metodo_pago == "EFECTIVO" and vuelto is not None:
        lineas.append(f"Vuelto: ${vuelto:.2f}")
    
    lineas.append("--------------------------------")
    lineas.append("      ¡GRACIAS POR SU COMPRA!")
    lineas.append("================================")

    return "\n".join(lineas)


def guardar_ticket(texto, venta_id):
    nombre_archivo = f"ticket_{venta_id}.txt"

    with open(nombre_archivo, "w", encoding="utf-8") as f:
        f.write(texto)

    return nombre_archivo