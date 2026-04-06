from textual.app import App, ComposeResult
from textual.widgets import Header, Footer, DataTable, Input, Static
from textual.containers import Horizontal, VerticalScroll
from textual import events
from decimal import Decimal

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
        height: 2;
        background: $surface;
        border: solid $primary;
        content-align: center middle;
    }
    
    #info-bar {
        height: 3;
        background: $primary;
        color: $text;
        content-align: center middle;
        text-style: bold;
    }
    
    #vuelto-display {
        height: 5;
        content-align: center middle;
        text-style: bold;
        background: black;
        color: yellow;
        border: solid yellow;
    }

    DataTable {
        height: 100%;
    }
    """

    def compose(self) -> ComposeResult:
        yield Header()
        
        self.total_display = Static("TOTAL: $0", id="total")
        yield self.total_display

        with Horizontal():
            with VerticalScroll():
                self.tabla = DataTable()
                self.tabla.add_columns("Código", "Nombre", "Precio", "Stock")
                yield self.tabla

            self.carrito = DataTable()
            self.carrito.add_columns("Producto", "Cant", "Precio", "Subtotal")
            yield self.carrito

        self.info = Static("", id="info")
        yield self.info
        
        self.info_bar = Static("", id="info-bar")
        yield self.info_bar
        
        self.vuelto_display = Static("", id="vuelto-display")
        yield self.vuelto_display

        self.input_codigo = Input(placeholder="Escanear código o escribir nombre...")
        yield self.input_codigo

        yield Footer()

    def on_mount(self):
        self.cargar_productos()
        self.nueva_venta()
        self._confirm_exit = False
        self.modo_pago = None
        self.monto_recibido_temp = None
        self.input_codigo.focus()
        self.actualizar_info()

    def actualizar_info(self):
        if self.modo_pago == 'esperando_metodo':
            self.info.update("🔔 SELECCIONE MÉTODO DE PAGO:")
            self.info_bar.update("[b]F1[/b] 💵 EFECTIVO     [b]F2[/b] 📱 QR     [b]F3[/b] 💸 TRANSFERENCIA     [b]F4[/b] 💳 TARJETA     [b]ESC[/b] CANCELAR")
            self.vuelto_display.update("")
        elif self.modo_pago == 'esperando_efectivo':
            self.info.update(f"💰 TOTAL A PAGAR: ${float(self.total):.2f}")
            self.info_bar.update("✏️ INGRESE EL MONTO RECIBIDO y presione ENTER:")
            self.input_codigo.placeholder = "💰 Ingrese el monto recibido (ej: 10000)"
            self.input_codigo.value = ""
            self.vuelto_display.update("")
        elif self.modo_pago == 'confirmar_vuelto':
            vuelto_calc = self.monto_recibido_temp - self.total

            # 👉 MODIFICADO: solo texto sin cuadro
            self.vuelto_display.update(
                f"[bold yellow]💵 VUELTO A DEVOLVER: ${float(vuelto_calc):.2f}[/bold yellow]"
            )

            self.info.update(f"💰 Total: ${float(self.total):.2f} | Recibido: ${float(self.monto_recibido_temp):.2f}")
            self.info_bar.update("[b]ENTER[/b] ✅ CONFIRMAR VENTA     [b]ESC[/b] ❌ CANCELAR")
            self.input_codigo.placeholder = "Presione ENTER para confirmar o ESC para cancelar..."
            self.input_codigo.value = ""
        else:
            self.info.update(f"📦 Items: {len(self.items)}")
            self.info_bar.update("[b]F2[/b] 💰 COBRAR     [b]F3[/b] 🗑️ BORRAR     [b]ESC[/b] SALIR")
            self.input_codigo.placeholder = "Escanear código o escribir nombre..."
            self.vuelto_display.update("")

        self.total_display.update(f"[bold green on black]   💰 TOTAL: ${float(self.total):.2f}   [/]")

    def mensaje_temp(self, texto, segundos=2):
        self.info.update(texto)
        self.set_timer(segundos, self.actualizar_info)
    
    def mostrar_mensaje_exito(self, metodo, total, vuelto=None):
        self.vuelto_display.update("")
        if vuelto:
            mensaje = f"✅ ¡VENTA COMPLETADA! ✅\n💰 Total: ${total:.2f} - {metodo}\n💵 Vuelto: ${vuelto:.2f}\n🔄 Volviendo al inicio..."
        else:
            mensaje = f"✅ ¡VENTA COMPLETADA! ✅\n💰 Total: ${total:.2f} - {metodo}\n🔄 Volviendo al inicio..."
        
        self.info.update(mensaje)
        self.info_bar.update("🎉 VENTA EXITOSA 🎉")
        
        beep()
        self.set_timer(0.3, lambda: beep())
        
        self.set_timer(3, self.reiniciar_para_nueva_venta)
    
    def reiniciar_para_nueva_venta(self):
        self.nueva_venta()
        self.carrito.clear()
        self.cargar_productos()
        self.modo_pago = None
        self.monto_recibido_temp = None
        self.vuelto = Decimal('0')
        self.input_codigo.placeholder = "Escanear código o escribir nombre..."
        self.input_codigo.value = ""
        self.input_codigo.focus()
        self.actualizar_info()
        self.mensaje_temp("🛒 Listo para nueva venta", 2)

    def nueva_venta(self):
        self.venta_id = crear_venta()
        self.total = Decimal('0')
        self.items = {}
        self.orden = []
        self.vuelto = Decimal('0')

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
                f"[bold]${float(p['precio']):.2f}[/]",
                f"[{color}]{p['stock']}[/{color}]"
            )

    def actualizar_carrito(self):
        self.carrito.clear()
        for item in self.items.values():
            self.carrito.add_row(
                f"[bold]{item['nombre']}[/]",
                str(item["cantidad"]),
                f"[bold]${float(item['precio']):.2f}[/]",
                f"[bold green]${float(item['subtotal']):.2f}[/]"
            )
        self.actualizar_info()

    def on_input_submitted(self, event: Input.Submitted):
        texto = event.value.strip()
        
        if self.modo_pago == 'confirmar_vuelto':
            self.confirmar_vuelto()
            return
        
        if self.modo_pago == 'esperando_efectivo':
            self.procesar_efectivo(texto)
            return
        
        if self.modo_pago is None:
            self.agregar_producto(texto)
    
    def agregar_producto(self, texto):
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
        precio_dec = Decimal(str(producto["precio"]))

        if codigo in self.items:
            self.items[codigo]["cantidad"] += 1
            self.items[codigo]["subtotal"] += precio_dec
        else:
            self.items[codigo] = {
                "id": producto["id"],
                "nombre": producto["nombre"],
                "cantidad": 1,
                "precio": precio_dec,
                "subtotal": precio_dec
            }

        self.orden.append(codigo)
        self.total += precio_dec
        self.actualizar_carrito()
        beep()

        if producto["stock"] <= 5:
            self.mensaje_temp(f"⚠️ Stock bajo: {producto['nombre']}")
        else:
            self.mensaje_temp(f"✔ {producto['nombre']} agregado")

        self.input_codigo.value = ""
        self.input_codigo.focus()
        self._confirm_exit = False
    
    def procesar_efectivo(self, texto):
        try:
            texto = texto.replace(',', '.')
            monto_recibido = Decimal(texto)
            
            if monto_recibido < self.total:
                falta = self.total - monto_recibido
                self.mensaje_temp(f"❌ Monto insuficiente. Faltan: ${float(falta):.2f}")
                self.input_codigo.value = ""
                self.input_codigo.focus()
            else:
                self.monto_recibido_temp = monto_recibido
                self.modo_pago = 'confirmar_vuelto'
                self.actualizar_info()
                self.input_codigo.value = ""
                self.input_codigo.focus()
        except ValueError:
            self.mensaje_temp("❌ Ingrese un monto válido (ej: 10000 o 10000.50)")
            self.input_codigo.value = ""
            self.input_codigo.focus()
    
    def confirmar_vuelto(self):
        vuelto_calc = self.monto_recibido_temp - self.total
        self.vuelto = vuelto_calc
        self.modo_pago = None
        self.procesar_pago("EFECTIVO")

    def key_f2(self):
        if not self.items:
            beep()
            self.mensaje_temp("⚠️ No hay productos")
            return
        
        self.modo_pago = 'esperando_metodo'
        self.actualizar_info()

    def on_key(self, event: events.Key):
        if self.modo_pago == 'confirmar_vuelto':
            if event.key == "escape":
                self.modo_pago = None
                self.monto_recibido_temp = None
                self.actualizar_info()
                self.mensaje_temp("❌ Pago cancelado")
                self.input_codigo.focus()
            return
        
        if self.modo_pago == 'esperando_metodo':
            if event.key == "f1":
                self.modo_pago = 'esperando_efectivo'
                self.actualizar_info()
                self.input_codigo.focus()
            elif event.key == "f2":
                self.modo_pago = None
                self.procesar_pago("QR")
            elif event.key == "f3":
                self.modo_pago = None
                self.procesar_pago("TRANSFERENCIA")
            elif event.key == "f4":
                self.modo_pago = None
                self.procesar_pago("TARJETA")
            elif event.key == "escape":
                self.modo_pago = None
                self.actualizar_info()
                self.mensaje_temp("❌ Pago cancelado")
                self.input_codigo.focus()
            return
        
        if event.key == "escape" and self.modo_pago is None:
            if self._confirm_exit:
                self.exit()
            else:
                self._confirm_exit = True
                self.mensaje_temp("⚠️ ESC otra vez para salir")
        elif event.key != "escape":
            self._confirm_exit = False
        
        if event.key == "f3" and self.modo_pago is None:
            self.borrar_item()
        
        if event.key != "escape":
            self.input_codigo.focus()
    
    def borrar_item(self):
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
        beep()
    
    def procesar_pago(self, metodo):
        for item in self.items.values():
            agregar_item(self.venta_id, item, item["cantidad"])
        
        cerrar_venta(self.venta_id, float(self.total), self.items.values())
        registrar_pago(self.venta_id, float(self.total), metodo)
        
        ticket_texto = generar_ticket(
            self.items.values(), 
            float(self.total), 
            self.venta_id,
            metodo_pago=metodo,
            vuelto=float(self.vuelto) if metodo == "EFECTIVO" else None
        )
        guardar_ticket(ticket_texto, self.venta_id)
        
        print("\n" + ticket_texto + "\n")
        
        if metodo == "EFECTIVO":
            self.mostrar_mensaje_exito(metodo, float(self.total), float(self.vuelto))
        else:
            self.mostrar_mensaje_exito(metodo, float(self.total))


if __name__ == "__main__":
    POSApp().run()