import tkinter as tk
from tkinter import ttk, messagebox
from decimal import Decimal
from PIL import Image, ImageTk
import os
import qrcode  # Nueva librería para el QR

# Importaciones de lógica propia
from productos import obtener_productos, buscar_producto_por_codigo, buscar_productos_por_nombre, validar_cupon
from ventas import crear_venta, agregar_item, cerrar_venta, registrar_pago
from ticket import generar_ticket, guardar_ticket 
from db import get_connection
# Importamos la función de MP (asegurate de crear el archivo pagos_mp.py)
try:
    from pagos_mp import generar_qr_mercadopago
except ImportError:
    generar_qr_mercadopago = None

def beep():
    print("\a", end="", flush=True)

class POSApp:
    def __init__(self, root, nombre_negocio="NEXUS", usuario_id=1):
        self.root = root
        self.usuario_id = usuario_id  
        self.nombre_negocio = nombre_negocio 
        
        user_data = self.obtener_datos_usuario(usuario_id)
        self.usuario_nombre = user_data['nombre']
        self.usuario_rol = user_data['rol']
        
        self.root.title(f"{nombre_negocio.upper()} - Terminal de Ventas")
        self.root.geometry("1450x900")
        
        self.colors = {
            'bg_main': '#121212', 'bg_panel': '#1e1e1e', 'accent': '#00a8ff',       
            'success': '#00db84', 'danger': '#ff4757', 'warning': '#f39c12',
            'promo': '#9c27b0', 'mp_blue': '#009ee3', 'text_main': '#ffffff', 
            'text_dim': '#a0a0a0', 'border': '#333333'        
        }

        self.imagenes_cache = {}
        self.venta_id = None
        self.total = Decimal('0')
        self.items = {}
        self.orden = []
        self.vuelto = Decimal('0')
        self.combos_aplicados = set() 
        
        self.setup_styles()
        self.create_widgets(nombre_negocio)
        self.nueva_venta()                  
        self.cargar_productos()             
        self.setup_keyboard_bindings()
        self.input_codigo.focus()

    def obtener_datos_usuario(self, uid):
        try:
            conn = get_connection()
            with conn.cursor() as cursor:
                cursor.execute("SELECT nombre, rol FROM usuarios WHERE id=%s", (uid,))
                res = cursor.fetchone()
                if res: return {'nombre': res['nombre'].upper(), 'rol': res['rol'].lower()}
            return {'nombre': 'DESCONOCIDO', 'rol': 'cajero'}
        except: return {'nombre': 'CAJERO', 'rol': 'cajero'}
        finally:
            if 'conn' in locals() and conn: conn.close()

    def setup_styles(self):
        style = ttk.Style()
        style.theme_use('clam')
        self.root.configure(bg=self.colors['bg_main'])
        style.configure("Custom.Treeview", background=self.colors['bg_panel'], foreground=self.colors['text_main'], fieldbackground=self.colors['bg_panel'], borderwidth=0, font=('Segoe UI', 10), rowheight=45)
        style.configure("Custom.Treeview.Heading", font=('Segoe UI', 10, 'bold'), background="#252525", foreground="white", relief="flat")

    def create_widgets(self, nombre_negocio):
        main_container = tk.Frame(self.root, bg=self.colors['bg_main'], padx=20, pady=20)
        main_container.pack(fill=tk.BOTH, expand=True)

        header = tk.Frame(main_container, bg=self.colors['bg_main'])
        header.pack(fill=tk.X, pady=(0, 20))
        tk.Label(header, text=nombre_negocio.upper(), font=('Segoe UI', 22, 'bold'), bg=self.colors['bg_main'], fg=self.colors['accent']).pack(side=tk.LEFT)
        
        u_frame = tk.Frame(header, bg='#252525', padx=15, pady=8, highlightthickness=1, highlightbackground=self.colors['border'])
        u_frame.pack(side=tk.RIGHT)
        tk.Label(u_frame, text=f"USUARIO: {self.usuario_nombre}", font=('Segoe UI', 10, 'bold'), bg='#252525', fg=self.colors['success']).pack()

        indicadores = tk.Frame(main_container, bg=self.colors['bg_main'])
        indicadores.pack(fill=tk.X, pady=(0, 20))
        
        card_total = tk.Frame(indicadores, bg=self.colors['bg_panel'], highlightbackground=self.colors['border'], highlightthickness=1)
        card_total.pack(side=tk.LEFT, fill=tk.BOTH, expand=True, padx=(0, 10))
        tk.Label(card_total, text="TOTAL A COBRAR", font=('Segoe UI', 11, 'bold'), bg=self.colors['bg_panel'], fg=self.colors['text_dim']).pack(pady=(15, 0))
        self.total_label = tk.Label(card_total, text="$ 0.00", font=('Segoe UI', 48, 'bold'), bg=self.colors['bg_panel'], fg=self.colors['success'])
        self.total_label.pack(pady=(0, 15))

        card_vuelto = tk.Frame(indicadores, bg=self.colors['bg_panel'], highlightbackground=self.colors['border'], highlightthickness=1)
        card_vuelto.pack(side=tk.LEFT, fill=tk.BOTH, expand=True, padx=(10, 0))
        tk.Label(card_vuelto, text="VUELTO / CAMBIO", font=('Segoe UI', 11, 'bold'), bg=self.colors['bg_panel'], fg=self.colors['text_dim']).pack(pady=(15, 0))
        self.vuelto_label = tk.Label(card_vuelto, text="", font=('Segoe UI', 48, 'bold'), bg=self.colors['bg_panel'], fg='#ffc107')
        self.vuelto_label.pack(pady=(0, 15))

        content = tk.Frame(main_container, bg=self.colors['bg_main'])
        content.pack(fill=tk.BOTH, expand=True)
        
        cat_frame = tk.Frame(content, bg=self.colors['bg_panel'], highlightbackground=self.colors['border'], highlightthickness=1)
        cat_frame.pack(side=tk.LEFT, fill=tk.BOTH, expand=True, padx=(0, 10))
        self.tabla = ttk.Treeview(cat_frame, columns=("SKU", "Nombre", "Precio", "Stock"), show="tree headings", style="Custom.Treeview")
        self.tabla.heading("#0", text="IMG"); self.tabla.column("#0", width=60, anchor="center")
        self.tabla.heading("SKU", text="SKU"); self.tabla.column("SKU", width=100, anchor="center")
        self.tabla.heading("Nombre", text="PRODUCTO"); self.tabla.column("Nombre", width=250)
        self.tabla.heading("Precio", text="PRECIO"); self.tabla.column("Precio", width=100, anchor="e")
        self.tabla.heading("Stock", text="STOCK"); self.tabla.column("Stock", width=80, anchor="center")
        self.tabla.pack(fill=tk.BOTH, expand=True, padx=2, pady=2)

        car_frame = tk.Frame(content, bg=self.colors['bg_panel'], highlightbackground=self.colors['border'], highlightthickness=1)
        car_frame.pack(side=tk.LEFT, fill=tk.BOTH, expand=True, padx=(10, 0))
        self.carrito = ttk.Treeview(car_frame, columns=("Prod", "Cant", "Sub"), show="tree headings", style="Custom.Treeview")
        self.carrito.heading("#0", text="IMG"); self.carrito.column("#0", width=60, anchor="center")
        self.carrito.heading("Prod", text="PRODUCTO"); self.carrito.column("Prod", width=220)
        self.carrito.heading("Cant", text="CANT."); self.carrito.column("Cant", width=70, anchor="center")
        self.carrito.heading("Sub", text="SUBTOTAL"); self.carrito.column("Sub", width=110, anchor="e")
        self.carrito.pack(fill=tk.BOTH, expand=True, padx=2, pady=2)

        footer = tk.Frame(main_container, bg=self.colors['bg_main'], pady=20)
        footer.pack(fill=tk.X)
        self.input_codigo = tk.Entry(footer, font=('Segoe UI', 18), bg="#252525", fg="white", insertbackground="white", borderwidth=0, highlightthickness=1, highlightbackground=self.colors['border'])
        self.input_codigo.pack(side=tk.LEFT, fill=tk.X, expand=True, ipady=8)
        self.input_codigo.bind('<Return>', self.on_input_submitted)

        btn_container = tk.Frame(footer, bg=self.colors['bg_main'])
        btn_container.pack(side=tk.RIGHT, padx=(20, 0))
        self.crear_boton(btn_container, "COBRAR (F2)", self.colors['success'], self.key_f2)
        self.crear_boton(btn_container, "QUITAR (F3)", self.colors['danger'], self.borrar_item)
        self.crear_boton(btn_container, "VACIAR (F4)", self.colors['warning'], self.limpiar_carrito)
        
        if self.usuario_rol in ['admin', 'jefe']:
            self.crear_boton(btn_container, "CUPON (F5)", self.colors['promo'], self.aplicar_descuento_qr)
        
        self.crear_boton(btn_container, "SALIR (ESC)", "#444", self.confirm_exit)

    def crear_boton(self, master, texto, color, comando):
        btn = tk.Button(master, text=texto, bg=color, fg="white", font=('Segoe UI', 9, 'bold'), relief="flat", padx=15, pady=10, command=comando, cursor="hand2")
        btn.pack(side=tk.LEFT, padx=3)

    def obtener_icono(self, ruta):
        if not ruta or not os.path.exists(ruta): return None
        if ruta in self.imagenes_cache: return self.imagenes_cache[ruta]
        try:
            img = Image.open(ruta).resize((32, 32), Image.Resampling.LANCZOS)
            photo = ImageTk.PhotoImage(img)
            self.imagenes_cache[ruta] = photo
            return photo
        except: return None

    def cargar_productos(self):
        for item in self.tabla.get_children(): self.tabla.delete(item)
        for p in obtener_productos():
            icono = self.obtener_icono(p.get("imagen"))
            precio_f = float(p.get('precio', 0))
            self.tabla.insert("", tk.END, image=icono if icono else "", 
                             values=(p["codigo"], p["nombre"], f"$ {precio_f:.2f}", p["stock"]))

    def on_input_submitted(self, event):
        texto = self.input_codigo.get().strip()
        if texto: self.agregar_producto(texto)

    def agregar_producto(self, texto):
        producto = buscar_producto_por_codigo(texto)
        if not producto:
            res = buscar_productos_por_nombre(texto)
            if not res: beep(); self.input_codigo.delete(0, tk.END); return
            producto = res[0]
        
        if int(producto["stock"]) <= 0:
            messagebox.showwarning("Stock", "Sin existencias."); self.input_codigo.delete(0, tk.END); return
        
        sku = producto["codigo"]
        precio = Decimal(str(producto["precio"]))
        
        if sku in self.items:
            self.items[sku]["cantidad"] += 1
            self.items[sku]["subtotal"] += precio
        else:
            self.items[sku] = {"id": producto["id"], "nombre": producto["nombre"], "cantidad": 1, "precio": precio, "subtotal": precio, "imagen": producto.get("imagen")}
        
        self.orden.append(sku); self.total += precio
        self.actualizar_carrito()
        beep(); self.input_codigo.delete(0, tk.END)

    def actualizar_carrito(self):
        for item in self.carrito.get_children(): self.carrito.delete(item)
        for i in self.items.values():
            icono = self.obtener_icono(i.get("imagen"))
            self.carrito.insert("", tk.END, image=icono if icono else "", values=(i['nombre'], i['cantidad'], f"$ {float(i['subtotal']):.2f}"))
        self.total_label.config(text=f"$ {float(self.total):.2f}")

    def borrar_item(self):
        if not self.orden: return
        sku = self.orden.pop(); item = self.items[sku]
        item["cantidad"] -= 1; item["subtotal"] -= item["precio"]; self.total -= item["precio"]
        if item["cantidad"] <= 0: del self.items[sku]
        self.actualizar_carrito(); beep()

    def limpiar_carrito(self):
        if messagebox.askyesno("Vaciar", "¿Vaciar todo?"):
            self.items = {}; self.orden = []; self.total = Decimal('0')
            self.actualizar_carrito(); beep()

    def setup_keyboard_bindings(self):
        self.root.bind('<F2>', lambda e: self.key_f2())
        self.root.bind('<F3>', lambda e: self.borrar_item())
        self.root.bind('<F4>', lambda e: self.limpiar_carrito())
        self.root.bind('<Escape>', lambda e: self.confirm_exit())
        if self.usuario_rol in ['admin', 'jefe']: 
            self.root.bind('<F5>', lambda e: self.aplicar_descuento_qr())

    def aplicar_descuento_qr(self):
        if not self.items: return
        dialog = tk.Toplevel(self.root); dialog.geometry("350x180"); dialog.configure(bg=self.colors['bg_panel'])
        ent = tk.Entry(dialog, font=('Segoe UI', 14), justify='center'); ent.pack(pady=30); ent.focus()
        def proc():
            cupon = validar_cupon(ent.get().strip())
            if cupon:
                self.total -= (self.total * Decimal(str(cupon['descuento_porcentaje'])) / 100)
                self.actualizar_carrito(); dialog.destroy()
        ent.bind('<Return>', lambda e: proc())

    def key_f2(self):
        if self.items: self.mostrar_dialogo_metodo_pago()

    def mostrar_dialogo_metodo_pago(self):
        dialog = tk.Toplevel(self.root)
        dialog.title("Finalizar Venta")
        dialog.geometry("450x500")
        dialog.configure(bg=self.colors['bg_panel'])
        dialog.transient(self.root); dialog.grab_set()

        tk.Label(dialog, text="METODO DE PAGO", font=('Segoe UI', 14, 'bold'), bg=self.colors['bg_panel'], fg="white").pack(pady=30)

        def pay(m):
            dialog.destroy()
            if m == "EFECTIVO": self.mostrar_dialogo_efectivo()
            elif m == "QR": self.mostrar_ventana_qr()
            else: self.procesar_pago(m)

        opciones = [("EFECTIVO", self.colors['success']), ("TARJETA", self.colors['accent']), ("QR", self.colors['mp_blue']), ("TRANSFERENCIA", "#0097e6")]
        for texto, color in opciones:
            tk.Button(dialog, text=f"PAGAR CON {texto}", command=lambda met=texto: pay(met), 
                      bg=color, fg="white", font=('Segoe UI', 11, 'bold'), relief="flat", pady=12, cursor="hand2").pack(fill=tk.X, padx=50, pady=8)

    def mostrar_dialogo_efectivo(self):
        dialog = tk.Toplevel(self.root); dialog.geometry("400x300"); dialog.configure(bg=self.colors['bg_panel'])
        tk.Label(dialog, text="MONTO RECIBIDO", bg=self.colors['bg_panel'], fg="white", font=('Segoe UI', 12)).pack(pady=20)
        ent = tk.Entry(dialog, font=('Segoe UI', 24), justify='center'); ent.pack(pady=20); ent.focus()
        def confirm():
            try:
                rec = Decimal(ent.get().replace(',', '.')); 
                if rec < self.total: return
                self.vuelto = rec - self.total
                dialog.destroy(); self.procesar_pago("EFECTIVO")
            except: pass
        ent.bind('<Return>', lambda e: confirm())
        tk.Button(dialog, text="CONFIRMAR", command=confirm, bg=self.colors['success'], fg="white", font=('Segoe UI', 12, 'bold')).pack(pady=20)

    # --- NUEVA FUNCIÓN MERCADO PAGO ---
    def mostrar_ventana_qr(self):
        if not generar_qr_mercadopago:
            messagebox.showerror("Error", "Módulo pagos_mp.py no encontrado")
            return

        qr_data = generar_qr_mercadopago(self.venta_id, self.total)
        if not qr_data:
            messagebox.showerror("Error", "No se pudo conectar con Mercado Pago")
            return

        win = tk.Toplevel(self.root)
        win.title("Mercado Pago QR")
        win.geometry("400x550")
        win.configure(bg=self.colors['mp_blue'])
        win.grab_set()

        tk.Label(win, text="ESCANEA EL QR", font=("Segoe UI", 16, "bold"), bg=self.colors['mp_blue'], fg="white").pack(pady=20)

        # Generar imagen QR
        qr_img = qrcode.make(qr_data).resize((300, 300))
        self.img_qr_tk = ImageTk.PhotoImage(qr_img)
        tk.Label(win, image=self.img_qr_tk, bg="white").pack(pady=10)

        tk.Button(win, text="CONFIRMAR PAGO RECIBIDO", command=lambda: [win.destroy(), self.procesar_pago("QR")],
                  bg=self.colors['success'], fg="white", font=("Segoe UI", 11, "bold"), pady=15).pack(fill=tk.X, padx=40, pady=25)

    def procesar_pago(self, metodo):
        try:
            if self.venta_id is None: self.venta_id = crear_venta()
            
            for item in self.items.values(): 
                agregar_item(self.venta_id, item, item["cantidad"])
            
            cerrar_venta(self.venta_id, float(self.total), self.items.values())
            registrar_pago(self.venta_id, float(self.total), metodo, float(self.total + self.vuelto), float(self.vuelto))
            
            # DB: Comprobante AFIP
            try:
                conn = get_connection()
                with conn.cursor() as cursor:
                    cursor.execute("""INSERT INTO comprobante_afip 
                        (venta_id, tipo_cbte, punto_vta, nro_cbte, cae, fecha_vto_cae, estado) 
                        VALUES (%s, %s, %s, %s, %s, %s, %s)""",
                        (self.venta_id, 11, 1, 100 + self.venta_id, "74123456789012", "2026-12-31", "APROBADO"))
                    conn.commit()
                conn.close()
            except Exception as e: print(f"Error tabla afip: {e}")

            # TICKET
            try:
                conn_t = get_connection()
                txt = generar_ticket(conn_t, list(self.items.values()), float(self.total), self.venta_id, metodo, float(self.vuelto))
                guardar_ticket(conn_t, txt, self.venta_id)
                conn_t.close()
            except Exception as e: print(f"Error Ticket: {e}")

            if hasattr(self, 'vuelto_label'):
                self.vuelto_label.config(text=f"$ {float(self.vuelto):.2f}")
            
            messagebox.showinfo("EXITO", f"Venta {self.venta_id} Finalizada")
            self.nueva_venta()
        except Exception as e: 
            messagebox.showerror("Error", str(e))

    def nueva_venta(self):
        try:
            self.venta_id = crear_venta()
            self.total = Decimal('0'); self.items = {}; self.orden = []; self.vuelto = Decimal('0')
            if hasattr(self, 'vuelto_label'): self.vuelto_label.config(text="")
            self.actualizar_carrito()
        except Exception as e: print(f"Error nueva venta: {e}")

    def confirm_exit(self):
        if messagebox.askyesno("Salir", "¿Desea cerrar?"): self.root.destroy()

if __name__ == "__main__":
    root = tk.Tk()
    app = POSApp(root)
    root.mainloop()