from productos import obtener_productos
from tabulate import tabulate

def main():
    productos = obtener_productos()

    tabla = []
    for p in productos:
        tabla.append([
            p["codigo"],
            p["nombre"],
            p["precio"],
            p["stock"]
        ])

    print(tabulate(tabla, headers=["Código", "Nombre", "Precio", "Stock"]))


if __name__ == "__main__":
    main()