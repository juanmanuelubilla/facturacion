from productos import obtener_productos
from tabulate import tabulate

def main():
    productos = obtener_productos()
    
    if not productos:
        print("\n[!] No hay productos registrados.\n")
        return

    tabla = []
    for p in productos:
        # Calculamos margen real (Precio Venta - Costo)
        margen = float(p['precio']) - float(p['costo'])
        
        tabla.append([
            p["codigo"],
            p["nombre"],
            f"${p['costo']:.2f}",
            f"${p['precio']:.2f}", # Este es el precio que guardaste (con tu redondeo)
            f"${margen:.2f}",
            p["stock"]
        ])

    headers = ["Código", "Nombre", "Costo", "Precio Venta", "Margen $", "Stock"]
    print("\n--- INVENTARIO ACTUAL ---")
    print(tabulate(tabla, headers=headers, tablefmt="fancy_grid"))

if __name__ == "__main__":
    main()