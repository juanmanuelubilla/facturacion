from productos import (
    obtener_productos,
    crear_producto,
    buscar_producto_por_codigo
)
from db import get_connection

# =========================
# VER PRODUCTOS
# =========================
def ver_productos():
    productos = obtener_productos()

    if not productos:
        print("\nNo hay productos\n")
        return

    print("\n=== PRODUCTOS ===\n")

    for p in productos:
        print(f"Código: {p['codigo']}")
        print(f"Nombre: {p['nombre']}")
        print(f"Precio: ${p['precio']}")
        print(f"Stock: {p['stock']}")
        print("-" * 30)


# =========================
# CREAR PRODUCTO
# =========================
def alta_producto():
    print("\n=== NUEVO PRODUCTO ===")

    codigo = input("Código: ").strip()
    nombre = input("Nombre: ").strip()
    precio = float(input("Precio: "))
    stock = int(input("Stock: "))
    imagen = input("Imagen (opcional): ").strip() or None

    crear_producto(codigo, nombre, precio, stock, imagen)

    print("✅ Producto creado\n")


# =========================
# EDITAR PRODUCTO
# =========================
def editar_producto():
    codigo = input("\nCódigo del producto a editar: ").strip()
    producto = buscar_producto_por_codigo(codigo)

    if not producto:
        print("❌ Producto no encontrado\n")
        return

    print(f"\nEditando: {producto['nombre']}")

    nuevo_nombre = input(f"Nombre ({producto['nombre']}): ").strip() or producto["nombre"]
    nuevo_precio = input(f"Precio ({producto['precio']}): ").strip()
    nuevo_stock = input(f"Stock ({producto['stock']}): ").strip()

    conn = get_connection()
    with conn.cursor() as cursor:
        cursor.execute("""
            UPDATE productos 
            SET nombre=%s, precio=%s, stock=%s
            WHERE codigo=%s
        """, (
            nuevo_nombre,
            float(nuevo_precio) if nuevo_precio else producto["precio"],
            int(nuevo_stock) if nuevo_stock else producto["stock"],
            codigo
        ))
    conn.commit()

    print("✅ Producto actualizado\n")


# =========================
# ELIMINAR PRODUCTO (SOFT DELETE)
# =========================
def eliminar_producto():
    codigo = input("\nCódigo del producto a eliminar: ").strip()
    producto = buscar_producto_por_codigo(codigo)

    if not producto:
        print("❌ Producto no encontrado\n")
        return

    confirm = input(f"¿Eliminar {producto['nombre']}? (s/n): ").lower()

    if confirm == "s":
        conn = get_connection()
        with conn.cursor() as cursor:
            cursor.execute("""
                UPDATE productos SET activo=0 WHERE codigo=%s
            """, (codigo,))
        conn.commit()

        print("🗑️ Producto eliminado\n")
    else:
        print("Cancelado\n")


# =========================
# MENÚ
# =========================
def menu():
    while True:
        print("""
=== GESTIÓN ===

1. Ver productos
2. Crear producto
3. Editar producto
4. Eliminar producto
0. Salir
""")

        opcion = input("Elegir opción: ").strip()

        if opcion == "1":
            ver_productos()
        elif opcion == "2":
            alta_producto()
        elif opcion == "3":
            editar_producto()
        elif opcion == "4":
            eliminar_producto()
        elif opcion == "0":
            print("Saliendo...")
            break
        else:
            print("Opción inválida\n")


if __name__ == "__main__":
    menu()