from productos import crear_producto

def pedir_datos():
    print("=== ALTA DE PRODUCTO ===")

    codigo = input("Código (barcode/SKU): ").strip()
    nombre = input("Nombre: ").strip()

    while True:
        try:
            precio = float(input("Precio: ").strip())
            break
        except ValueError:
            print("⚠️ Precio inválido")

    while True:
        try:
            stock = int(input("Stock: ").strip())
            break
        except ValueError:
            print("⚠️ Stock inválido")

    imagen = input("Ruta imagen (opcional): ").strip()
    if imagen == "":
        imagen = None

    return codigo, nombre, precio, stock, imagen


def main():
    try:
        datos = pedir_datos()
        crear_producto(*datos)

        print("\n✅ Producto creado correctamente")

    except Exception as e:
        print("\n❌ Error:")
        print(e)


if __name__ == "__main__":
    main()