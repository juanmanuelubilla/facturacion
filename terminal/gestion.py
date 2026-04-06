from textual.app import App, ComposeResult
from textual.widgets import Header, Footer, DataTable, Input, Static, Button
from textual.containers import Vertical, Horizontal
from textual import events
from textual.screen import Screen
import os
from datetime import datetime

from productos import (
    obtener_productos,
    crear_producto,
    buscar_producto_por_codigo,
    actualizar_producto
)
from db import get_connection


class FormularioScreen(Screen):
    """Pantalla de formulario para crear/editar productos"""
    
    CSS = """
    FormularioScreen {
        align: center middle;
    }
    
    #form-container {
        width: 60;
        height: auto;
        border: solid $accent;
        background: $surface;
        padding: 2;
    }
    
    #titulo {
        text-style: bold;
        content-align: center middle;
        height: 3;
        background: $primary;
        margin-bottom: 1;
    }
    
    Input {
        margin: 1 0;
    }
    
    #botonera {
        margin-top: 2;
        align: center middle;
    }
    
    Button {
        margin: 0 1;
    }
    
    .info-text {
        color: $text;
        margin-top: 1;
        content-align: center middle;
    }
    
    #imagen-info {
        color: $text-muted;
        margin-top: 0;
    }
    """
    
    def __init__(self, modo, producto=None):
        super().__init__()
        self.modo = modo
        self.producto = producto
        
    def compose(self):
        with Vertical(id="form-container"):
            if self.modo == "crear":
                yield Static("📝 CREAR NUEVO PRODUCTO", id="titulo")
            else:
                yield Static("✏️ EDITAR PRODUCTO", id="titulo")
            
            self.input_codigo = Input(placeholder="Código *", id="codigo")
            yield self.input_codigo
            
            self.input_nombre = Input(placeholder="Nombre *", id="nombre")
            yield self.input_nombre
            
            self.input_descripcion = Input(placeholder="Descripción", id="descripcion")
            yield self.input_descripcion
            
            self.input_precio = Input(placeholder="Precio de venta *", id="precio")
            yield self.input_precio
            
            self.input_costo = Input(placeholder="Costo *", id="costo")
            yield self.input_costo
            
            self.input_stock = Input(placeholder="Stock *", id="stock")
            yield self.input_stock
            
            self.input_imagen = Input(placeholder="Ruta de la imagen (ej: imagenes/coca.jpg)", id="imagen")
            yield self.input_imagen
            
            yield Static("💡 La imagen puede ser una ruta local o URL", id="imagen-info")
            
            self.info = Static("", classes="info-text")
            yield self.info
            
            with Horizontal(id="botonera"):
                yield Button("💾 GUARDAR", id="btn_guardar", variant="primary")
                yield Button("❌ CANCELAR", id="btn_cancelar", variant="default")
    
    def on_mount(self):
        if self.modo == "editar" and self.producto:
            self.input_codigo.value = self.producto["codigo"]
            self.input_nombre.value = self.producto["nombre"]
            self.input_descripcion.value = self.producto.get("descripcion", "")
            self.input_precio.value = str(self.producto["precio"])
            self.input_costo.value = str(self.producto.get("costo", 0))
            self.input_stock.value = str(self.producto["stock"])
            self.input_imagen.value = self.producto.get("imagen", "")
            self.input_codigo.disabled = True
        else:
            self.input_codigo.disabled = False
            
        if self.modo == "crear":
            self.input_codigo.focus()
        else:
            self.input_nombre.focus()
    
    def on_button_pressed(self, event: Button.Pressed):
        if event.button.id == "btn_guardar":
            self.guardar()
        elif event.button.id == "btn_cancelar":
            self.cancelar()
    
    def on_key(self, event: events.Key):
        if event.key == "f1":
            self.guardar()
        elif event.key == "escape":
            self.cancelar()
    
    def guardar(self):
        try:
            codigo = self.input_codigo.value.strip()
            nombre = self.input_nombre.value.strip()
            descripcion = self.input_descripcion.value.strip()
            precio = float(self.input_precio.value)
            costo = float(self.input_costo.value)
            stock = int(self.input_stock.value)
            imagen = self.input_imagen.value.strip() if self.input_imagen.value.strip() else None
            
            if not codigo or not nombre:
                self.info.update("❌ Código y nombre son obligatorios")
                return
            
            conn = get_connection()
            with conn.cursor() as cursor:
                if self.modo == "crear":
                    cursor.execute("""
                        INSERT INTO productos (codigo, nombre, descripcion, precio, costo, stock, imagen, activo, creado_en)
                        VALUES (%s, %s, %s, %s, %s, %s, %s, 1, %s)
                    """, (codigo, nombre, descripcion, precio, costo, stock, imagen, datetime.now()))
                    self.app.mensaje_info.update("✅ Producto creado")
                else:
                    cursor.execute("""
                        UPDATE productos 
                        SET nombre=%s, descripcion=%s, precio=%s, costo=%s, stock=%s, imagen=%s
                        WHERE codigo=%s
                    """, (nombre, descripcion, precio, costo, stock, imagen, codigo))
                    self.app.mensaje_info.update("✅ Producto actualizado")
            
            conn.commit()
            self.app.recargar_tabla()
            self.dismiss()
            
        except ValueError:
            self.info.update("❌ Error: Precio, costo y stock deben ser números")
        except Exception as e:
            self.info.update(f"❌ Error: {str(e)}")
    
    def cancelar(self):
        self.app.mensaje_info.update("Operación cancelada")
        self.dismiss()


class GestionApp(App):
    
    ENABLE_MOUSE = True
    
    CSS = """
    Vertical {
        height: 100%;
    }
    
    #mensaje-info {
        height: 1;
        content-align: center middle;
        text-style: bold;
    }
    
    #buscador {
        margin: 0 2;
    }
    
    #botonera {
        height: 3;
        margin: 0 2;
        align: center middle;
    }
    
    Button {
        margin: 0 2;
        padding: 0 2;
    }
    
    Button:hover {
        background: $accent;
    }
    
    DataTable {
        height: 1fr;
    }
    """
    
    def compose(self) -> ComposeResult:
        yield Header()
        
        with Vertical():
            self.mensaje_info = Static("", id="mensaje-info")
            yield self.mensaje_info
            
            self.buscador = Input(placeholder="🔍 Buscar por código o nombre...", id="buscador")
            yield self.buscador
            
            with Horizontal(id="botonera"):
                yield Button("📝 CREAR", id="btn_crear", variant="primary")
                yield Button("✏️ EDITAR", id="btn_editar", variant="default")
                yield Button("🗑️ ELIMINAR", id="btn_eliminar", variant="error")
                yield Button("🚪 SALIR", id="btn_salir", variant="default")
            
            self.tabla = DataTable()
            self.tabla.add_columns("📷", "Código", "Nombre", "Precio", "Stock", "Estado")
            yield self.tabla
        
        yield Footer()
    
    def on_mount(self):
        self.recargar_tabla()
        self.buscador.focus()
    
    def recargar_tabla(self):
        """Recarga todos los productos desde la BD y aplica el filtro actual"""
        self.todos_los_productos = obtener_productos()
        self.aplicar_filtro()
    
    def aplicar_filtro(self):
        """Aplica el filtro del buscador a los productos"""
        texto_busqueda = self.buscador.value.strip()
        
        if not texto_busqueda:
            productos_mostrar = self.todos_los_productos
        else:
            texto_busqueda = texto_busqueda.lower()
            productos_mostrar = []
            for p in self.todos_los_productos:
                if (texto_busqueda in p["codigo"].lower() or 
                    texto_busqueda in p["nombre"].lower()):
                    productos_mostrar.append(p)
        
        self.mostrar_productos_en_tabla(productos_mostrar)
        
        # Actualizar contador
        if texto_busqueda:
            self.mensaje_info.update(f"🔍 Encontrados: {len(productos_mostrar)} de {len(self.todos_los_productos)} productos")
        else:
            self.mensaje_info.update(f"📦 Total: {len(productos_mostrar)} productos")
    
    def mostrar_productos_en_tabla(self, productos):
        """Muestra la lista de productos en la tabla"""
        self.tabla.clear()
        
        for p in productos:
            estado = "🟢" if p["activo"] else "🔴"
            # Icono de imagen si tiene imagen asignada
            icono_imagen = "🖼️" if p.get("imagen") else "📦"
            
            self.tabla.add_row(
                icono_imagen,
                p["codigo"],
                p["nombre"],
                f"${float(p['precio']):.2f}",
                str(p["stock"]),
                estado
            )
    
    def on_input_changed(self, event: Input.Changed):
        """Cuando el usuario escribe en el buscador - SOLO FILTRA"""
        if event.input.id == "buscador":
            self.aplicar_filtro()
    
    def on_input_submitted(self, event: Input.Submitted):
        """Cuando el usuario presiona ENTER en el buscador"""
        if event.input.id == "buscador":
            self.mensaje_info.update("✅ Filtro aplicado")
    
    def on_button_pressed(self, event: Button.Pressed):
        if event.button.id == "btn_crear":
            self.key_f1()
        elif event.button.id == "btn_editar":
            self.key_f2()
        elif event.button.id == "btn_eliminar":
            self.key_f3()
        elif event.button.id == "btn_salir":
            self.key_f4()
    
    def key_f1(self):
        """Crear nuevo producto"""
        self.push_screen(FormularioScreen("crear"))
    
    def key_f2(self):
        """Editar producto seleccionado"""
        if self.tabla.row_count == 0:
            self.mensaje_info.update("❌ No hay productos para editar")
            return
        
        if self.tabla.cursor_row is None:
            self.mensaje_info.update("❌ Seleccione un producto (click o ↑/↓)")
            return
        
        codigo = self.tabla.get_row_at(self.tabla.cursor_row)[1]  # Columna 1 es código
        producto = buscar_producto_por_codigo(codigo)
        
        if not producto:
            self.mensaje_info.update("❌ Producto no encontrado")
            return
        
        self.push_screen(FormularioScreen("editar", producto))
    
    def key_f3(self):
        """Eliminar producto seleccionado"""
        if self.tabla.row_count == 0:
            self.mensaje_info.update("❌ No hay productos para eliminar")
            return
        
        if self.tabla.cursor_row is None:
            self.mensaje_info.update("❌ Seleccione un producto (click o ↑/↓)")
            return
        
        codigo = self.tabla.get_row_at(self.tabla.cursor_row)[1]  # Columna 1 es código
        
        if not hasattr(self, '_confirm_delete'):
            self._confirm_delete = codigo
            self.mensaje_info.update(f"⚠️ Presione ELIMINAR nuevamente para borrar {codigo}")
            return
        
        if self._confirm_delete == codigo:
            conn = get_connection()
            with conn.cursor() as cursor:
                cursor.execute("""
                    UPDATE productos SET activo=0 WHERE codigo=%s
                """, (codigo,))
            conn.commit()
            
            self.recargar_tabla()
            self.mensaje_info.update(f"✅ {codigo} eliminado")
            self._confirm_delete = None
        else:
            self._confirm_delete = None
            self.mensaje_info.update("❌ Cancelado")
    
    def key_f4(self):
        """Salir"""
        self.exit()
    
    def on_key(self, event: events.Key):
        if event.key == "f1":
            self.key_f1()
        elif event.key == "f2":
            self.key_f2()
        elif event.key == "f3":
            self.key_f3()
        elif event.key == "f4":
            self.key_f4()
        elif event.key == "escape":
            if hasattr(self, '_confirm_delete'):
                self._confirm_delete = None
                self.mensaje_info.update("Cancelado")
            else:
                self.buscador.value = ""
                self.recargar_tabla()


if __name__ == "__main__":
    GestionApp().run()