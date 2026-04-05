from textual.app import App, ComposeResult
from textual.widgets import Header, Footer, DataTable, Input, Static
from textual.containers import Horizontal, VerticalScroll

from productos import (
    obtener_productos,
    buscar_producto_por_codigo,
    buscar_productos_por_nombre
)

from ventas import (
    crear_venta,
    agregar_item,
    cerrar_venta,
    registrar_pago
)

from ticket import generar_ticket, guardar_ticket


# =========================
# 🔊 BEEP SIMPLE
# =========================
def beep():
    print("\a", end="", flush=True)


class POSApp(App):

    CSS = """
    #total {
        height: 5;
        content-align: center middle;
        text-style: bold;
        background: black;
        color: green;
        border: solid green;
    }

    #info {
        height: 1;
    }

    DataTable {
        height: 100%;
    }
    """

    def compose(self) -> ComposeResult:
        yield Header()

        # 💰 TOTAL GRANDE
        self.total_display = Static("TOTAL: $0", id="total")
        yield self.total_display

        with Horizontal():

            # 📦 PRODUCTOS
            with VerticalScroll():
                self.tabla = DataTable()
                self.tabla.add_columns("Código", "Nombre", "Precio", "Stock")
                yield self.tabla

            # 🛒 CARRITO
            self.carrito = DataTable()
            self.carrito.add_columns("Producto", "Cant", "Precio", "Subtotal")
            yield self.carrito

        self.info = Static("", id="info")
        yield self.info

        self.input_codigo = Input(placeholder="Escanear código o escribir nombre...")
        yield self.input_codigo

        yield Footer()

    def on_mount(self):
        self.cargar_productos()
        self.nueva_venta()
        self._confirm_exit = False
        self.input_codigo.focus()
        self.actualizar_info()

    # =========================
    # INFO
    # =========================
    def actualizar_info(self):
        self.info.update(
            f"Items: {len(self.items)} | ENTER/F2 COBRAR | F3 BORRAR | ESC SALIR"
        )

        self.total_display.update(
            f"[bold green on black]   💰 TOTAL: ${self.total}   [/]"
        )

    def mensaje_temp(self, texto, segundos=2):
        self.info.update(texto)
        self.set_timer(segundos, self.actualizar_info)

    # =========================
    # VENTA
    # =========================
    def nueva_venta(self):
        self.venta_id = crear_venta()
        self.total = 0
        self.items = {}
        self.orden = []

    # =========================
    # PRODUCTOS
    # =========================
    def cargar_productos(self):
        self.tabla.clear()
        productos = obtener_productos()

        for p in productos:
            stock = int(p["stock"])

            if stock <= 0:
                color = "red"
            elif stock <= 5:
                color = "yellow"
            else:
                color = "green"

            self.tabla.add_row(
                p["codigo"],
                f"[{color}]{p['nombre']}[/{color}]",
                f"[bold]${p['precio']}[/]",
                f"[{color}]{p['stock']}[/{color}]"
            )

    # =========================
    # CARRITO
    # =========================
    def actualizar_carrito(self):
        self.carrito.clear()

        for item in self.items.values():
            self.carrito.add_row(
                f"[bold]{item['nombre']}[/]",
                str(item["cantidad"]),
                f"[bold]${item['precio']}[/]",
                f"[bold green]${item['subtotal']}[/]"
            )

        self.actualizar_info()

    # =========================
    # INPUT
    # =========================
    def on_input_submitted(self, event: Input.Submitted):
        texto = event.value.strip()

        producto = buscar_producto_por_codigo(texto)

        if not producto:
            resultados = buscar_productos_por_nombre(texto)

            if not resultados:
                beep()
                self.mensaje_temp("❌ No encontrado")
                self.input_codigo.value = ""
                return

            producto = resultados[0]

        if producto["stock"] <= 0:
            beep()
            self.mensaje_temp("⚠️ Sin stock")
            self.input_codigo.value = ""
            return

        codigo = producto["codigo"]

        if codigo in self.items:
            self.items[codigo]["cantidad"] += 1
            self.items[codigo]["subtotal"] += producto["precio"]
        else:
            self.items[codigo] = {
                "id": producto["id"],
                "nombre": producto["nombre"],
                "cantidad": 1,
                "precio": producto["precio"],
                "subtotal": producto["precio"]
            }

        self.orden.append(codigo)
        self.total += producto["precio"]

        self.actualizar_carrito()

        beep()

        if producto["stock"] <= 5:
            self.mensaje_temp(f"⚠️ Stock bajo: {producto['nombre']}")
        else:
            self.mensaje_temp(f"✔ {producto['nombre']} agregado")

        self.input_codigo.value = ""
        self.input_codigo.focus()
        self._confirm_exit = False

    # =========================
    # COBRAR
    # =========================
    def key_f2(self):
        self.cobrar()

    def key_enter(self):
        if self.items:
            self.cobrar()

    def cobrar(self):
        if not self.items:
            beep()
            self.mensaje_temp("⚠️ No hay productos")
            return

        for item in self.items.values():
            agregar_item(self.venta_id, item, item["cantidad"])

        cerrar_venta(self.venta_id, self.total, self.items.values())
        registrar_pago(self.venta_id, self.total)

        ticket_texto = generar_ticket(self.items.values(), self.total, self.venta_id)
        archivo = guardar_ticket(ticket_texto, self.venta_id)

        print("\n" + ticket_texto + "\n")

        beep()

        self.mensaje_temp(f"✅ COBRADO: ${self.total}", 3)

        self.nueva_venta()
        self.carrito.clear()
        self.cargar_productos()

    # =========================
    # BORRAR
    # =========================
    def key_f3(self):
        if not self.orden:
            return

        codigo = self.orden.pop()
        item = self.items[codigo]

        item["cantidad"] -= 1
        item["subtotal"] -= item["precio"]
        self.total -= item["precio"]

        if item["cantidad"] <= 0:
            del self.items[codigo]

        self.actualizar_carrito()

    # =========================
    # SALIR
    # =========================
    def key_escape(self):
        if self._confirm_exit:
            self.exit()
        else:
            self._confirm_exit = True
            self.mensaje_temp("⚠️ ESC otra vez para salir")

    def on_key(self, event):
        if event.key != "escape":
            self._confirm_exit = False

        self.input_codigo.focus()


if __name__ == "__main__":
    POSApp().run()