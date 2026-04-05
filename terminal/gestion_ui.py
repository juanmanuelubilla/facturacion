from textual.app import App, ComposeResult
from textual.widgets import Header, Footer, DataTable, Static
from textual.containers import Horizontal

from productos import obtener_productos, eliminar_producto


class GestionApp(App):

    BINDINGS = [
        ("d", "eliminar", "Eliminar"),
        ("escape", "salir", "Salir"),
    ]

    def compose(self) -> ComposeResult:
        yield Header()

        with Horizontal():
            self.tabla = DataTable()
            self.tabla.add_columns("ID", "Código", "Nombre", "Precio", "Stock")
            yield self.tabla

            self.detalle = Static("Seleccioná un producto")
            yield self.detalle

        self.info = Static("D=Eliminar | ESC=Salir")
        yield self.info

        yield Footer()

    def on_mount(self):
        self.cargar_productos()
        self.tabla.focus()

    def cargar_productos(self):
        self.tabla.clear()
        self.productos = obtener_productos()

        for p in self.productos:
            self.tabla.add_row(
                str(p["id"]),
                p["codigo"],
                p["nombre"],
                str(p["precio"]),
                str(p["stock"])
            )

    def get_producto_actual(self):
        row = self.tabla.cursor_row

        if row is None or row >= len(self.productos):
            return None

        return self.productos[row]

    def on_data_table_row_highlighted(self, event):
        p = self.get_producto_actual()
        if not p:
            return

        self.detalle.update(
            f"{p['nombre']} | ${p['precio']} | Stock: {p['stock']}"
        )

    def action_eliminar(self):
        p = self.get_producto_actual()
        if not p:
            return

        eliminar_producto(p["id"])
        self.cargar_productos()
        self.info.update("🗑️ Eliminado")

    def action_salir(self):
        self.exit()


if __name__ == "__main__":
    GestionApp().run()