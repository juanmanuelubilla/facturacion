from productos import crear_producto
import sys

def pedir_datos():
    print("=== ALTA DE PRODUCTO (MODO CONSOLA) ===")
    codigo = input("Código: ").strip()
    nombre = input("Nombre: ").strip()
    precio = float(input("Precio: "))
    costo = float(input("Costo: "))
    stock = int(input("Stock: "))
    emp_id = int(input("Empresa ID destno: ")) # Pedimos el ID manualmente en consola
    return codigo, nombre, precio, costo, stock, None, None, 1, emp_id

def main():
    try:
        datos = pedir_datos()
        crear_producto(*datos)
        print("\n✅ Producto creado en la empresa correspondiente")
    except Exception as e:
        print(f"\n❌ Error: {e}")

if __name__ == "__main__":
    main()