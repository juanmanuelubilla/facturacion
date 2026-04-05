from datetime import datetime

def generar_ticket(items, total, venta_id):
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
        precio = item["precio"]
        subtotal = item["subtotal"]

        lineas.append(f"{nombre}")
        lineas.append(f"{cantidad} x ${precio} = ${subtotal}")

    lineas.append("--------------------------------")
    lineas.append(f"TOTAL: ${total}")
    lineas.append("--------------------------------")
    lineas.append("      ¡GRACIAS POR SU COMPRA!")
    lineas.append("================================")

    return "\n".join(lineas)


def guardar_ticket(texto, venta_id):
    nombre_archivo = f"ticket_{venta_id}.txt"

    with open(nombre_archivo, "w") as f:
        f.write(texto)

    return nombre_archivo