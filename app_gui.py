import tkinter as tk
from tkinter import ttk, messagebox
from decimal import Decimal
from PIL import Image, ImageTk
import os
import sys
import qrcode  # Asegúrate de tener: pip install qrcode

# Importaciones de lógica
from productos import (obtener_productos, buscar_producto_por_codigo, 
                       buscar_productos_por_nombre, validar_cupon, 
                       obtener_regla_mayorista, obtener_combos_activos)
from ventas import crear_venta, agregar_item, cerrar_venta, registrar_pago
from ticket import generar_ticket, guardar_ticket 
from db import get_connection

# IMPORTACIÓN DE PAGOS QR
from pagos_py import generar_qr_mercadopago, generar_qr_payway, generar_qr_modo

def beep():
    print("\a", end="", flush=True)

class POSApp:
    def __init__(self, root, nombre_negocio="NEXUS", empresa_id=1, usuario_id=1):
        self.root = root
        self.empresa_id = int(empresa_id)
        self.usuario_id = int(usuario_id)
        self.nombre_negocio = nombre_negocio 
        
        user_data = self.obtener_datos_usuario(self.usuario_id)
        self.usuario_nombre = user_data['nombre']
        self.usuario_rol = user_data['rol']
        
        self.root.title(f"{nombre_negocio.upper()} - Terminal POS")
        self.root.geometry("1450x900")
        
        self.colors = {
            'bg_main': '#121212', 'bg_panel': '#1e1e1e', 'accent': '#00a8ff',       
            'success': '#00db84', 'danger': '#ff4757', 'warning': '#f39c12',
            'promo': '#9c27b0', 'text_main': '#ffffff', 'text_dim': '#a0a0a0', 'border': '#333333'        
        }

        self.imagenes_cache = {}
        self.items = {} 
        self.orden = [] 
        self.total = Decimal('0')
        self.vuelto = Decimal('0')
        self.descuento_cupon_actual = Decimal('0')
        
        self.setup_styles()
        self.create_widgets(nombre_negocio)
        self.nueva_venta()                  
        self.cargar_productos_stock() 
        self.setup_keyboard_bindings()
        self.input_codigo.focus()

    def obtener_datos_usuario(self, uid):
        try:
            conn = get_connection()
            with conn.cursor() as cursor:
                cursor.execute("SELECT nombre, rol FROM usuarios WHERE id=%s", (uid,))
                res = cursor.fetchone()
                if res: return {'nombre': str(res['nombre']).upper(), 'rol': str(res['rol']).lower()}
            return {'nombre': f'OPERADOR {uid}', 'rol': 'cajero'}
        except: return {'nombre': 'USUARIO LOCAL', 'rol': 'cajero'}
        finally: 
            if 'conn' in locals() and conn: conn.close()

    def setup_styles(self):
        style = ttk.Style()
        style.theme_use('clam')
        self.root.configure(bg=self.colors['bg_main'])
        style.configure("Custom.Treeview", background=self.colors['bg_panel'], foreground=self.colors['text_main'], 
                        fieldbackground=self.colors['bg_panel'], borderwidth=0, font=('Segoe UI', 10), rowheight=45)
        style.configure("Custom.Treeview.Heading", font=('Segoe UI', 10, 'bold'), background="#252525", foreground="white")

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
        
        f_total = tk.Frame(indicadores, bg=self.colors['bg_panel'], highlightbackground=self.colors['border'], highlightthickness=1)
        f_total.pack(side=tk.LEFT, fill=tk.BOTH, expand=True, padx=(0, 10))
        tk.Label(f_total, text="TOTAL A COBRAR", font=('Segoe UI', 11, 'bold'), bg=self.colors['bg_panel'], fg=self.colors['text_dim']).pack(pady=(15, 0))
        self.total_label = tk.Label(f_total, text="$ 0.00", font=('Segoe UI', 48, 'bold'), bg=self.colors['bg_panel'], fg=self.colors['success'])
        self.total_label.pack(pady=(0, 15))

        f_vuelto = tk.Frame(indicadores, bg=self.colors['bg_panel'], highlightbackground=self.colors['border'], highlightthickness=1)
        f_vuelto.pack(side=tk.LEFT, fill=tk.BOTH, expand=True, padx=(10, 0))
        tk.Label(f_vuelto, text="VUELTO / CAMBIO", font=('Segoe UI', 11, 'bold'), bg=self.colors['bg_panel'], fg=self.colors['text_dim']).pack(pady=(15, 0))
        self.vuelto_label = tk.Label(f_vuelto, text="", font=('Segoe UI', 48, 'bold'), bg=self.colors['bg_panel'], fg='#ffc107')
        self.vuelto_label.pack(pady=(0, 15))

        content = tk.Frame(main_container, bg=self.colors['bg_main'])
        content.pack(fill=tk.BOTH, expand=True)
        
        cat_frame = tk.Frame(content, bg=self.colors['bg_panel'], highlightbackground=self.colors['border'], highlightthickness=1)
        cat_frame.pack(side=tk.LEFT, fill=tk.BOTH, expand=True, padx=(0, 10))
        self.tabla = ttk.Treeview(cat_frame, columns=("SKU", "Nombre", "Precio", "Stock"), show="tree headings", style="Custom.Treeview")
        self.tabla.heading("#0", text="IMG"); self.tabla.column("#0", width=60)
        self.tabla.heading("SKU", text="SKU"); self.tabla.column("SKU", width=100)
        self.tabla.heading("Nombre", text="PRODUCTO"); self.tabla.column("Nombre", width=250)
        self.tabla.heading("Precio", text="PRECIO"); self.tabla.column("Precio", width=100)
        self.tabla.heading("Stock", text="STOCK"); self.tabla.column("Stock", width=80)
        self.tabla.pack(fill=tk.BOTH, expand=True)

        car_frame = tk.Frame(content, bg=self.colors['bg_panel'], highlightbackground=self.colors['border'], highlightthickness=1)
        car_frame.pack(side=tk.LEFT, fill=tk.BOTH, expand=True, padx=(10, 0))
        self.carrito = ttk.Treeview(car_frame, columns=("Prod", "Cant", "Precio", "Sub"), show="tree headings", style="Custom.Treeview")
        self.carrito.heading("#0", text="IMG"); self.carrito.column("#0", width=60)
        self.carrito.heading("Prod", text="PRODUCTO"); self.carrito.column("Prod", width=200)
        self.carrito.heading("Cant", text="CANT."); self.carrito.column("Cant", width=60)
        self.carrito.heading("Precio", text="UNIT."); self.carrito.column("Precio", width=90)
        self.carrito.heading("Sub", text="SUBTOTAL"); self.carrito.column("Sub", width=90)
        self.carrito.pack(fill=tk.BOTH, expand=True)

        footer = tk.Frame(main_container, bg=self.colors['bg_main'], pady=20)
        footer.pack(fill=tk.X)
        
        self.input_codigo = tk.Entry(footer, font=('Segoe UI', 18), bg="#252525", fg="white", borderwidth=0, highlightthickness=1, highlightbackground=self.colors['border'])
        self.input_codigo.pack(side=tk.LEFT, fill=tk.X, expand=True, ipady=8)
        self.input_codigo.bind('<Return>', self.on_input_submitted)

        btn_container = tk.Frame(footer, bg=self.colors['bg_main'])
        btn_container.pack(side=tk.RIGHT, padx=(20, 0))
        
        self.crear_boton(btn_container, "COBRAR (F2)", self.colors['success'], self.key_f2)
        self.crear_boton(btn_container, "QUITAR (F3)", self.colors['danger'], self.borrar_item)
        self.crear_boton(btn_container, "VACIAR (F4)", self.colors['warning'], self.limpiar_carrito)
        self.crear_boton(btn_container, "PROMOCIÓN/QR (F5)", self.colors['promo'], self.abrir_lector_qr)
        self.crear_boton(btn_container, "SALIR (ESC)", "#444", self.confirm_exit)

    def crear_boton(self, master, texto, color, comando):
        tk.Button(master, text=texto, bg=color, fg="white", font=('Segoe UI', 9, 'bold'), relief="flat", padx=15, pady=10, command=comando, cursor="hand2").pack(side=tk.LEFT, padx=3)

    # --- LOGICA DE COBRO Y QR ---
    def mostrar_dialogo_pago(self):
        dialog = tk.Toplevel(self.root); dialog.geometry("400x650"); dialog.configure(bg=self.colors['bg_panel'])
        tk.Label(dialog, text="FORMA DE PAGO", font=('Segoe UI', 14, 'bold'), bg=self.colors['bg_panel'], fg="white").pack(pady=20)
        btn_opts = {"font": ('Segoe UI', 11, 'bold'), "width": 25, "pady": 10, "fg": "white"}
        
        tk.Button(dialog, text="EFECTIVO", command=lambda: [dialog.destroy(), self.mostrar_dialogo_efectivo()], bg=self.colors['success'], **btn_opts).pack(pady=5)
        tk.Button(dialog, text="TARJETA", command=lambda: [dialog.destroy(), self.procesar_pago("TARJETA")], bg=self.colors['accent'], **btn_opts).pack(pady=5)
        tk.Button(dialog, text="QR MERCADO PAGO", command=lambda: [dialog.destroy(), self.mostrar_qr_pago("MP")], bg="#009ee3", **btn_opts).pack(pady=5)
        tk.Button(dialog, text="QR PAYWAY", command=lambda: [dialog.destroy(), self.mostrar_qr_pago("PW")], bg="#ee3d2f", **btn_opts).pack(pady=5)
        tk.Button(dialog, text="QR MODO", command=lambda: [dialog.destroy(), self.mostrar_qr_pago("MODO")], bg="#5cb85c", **btn_opts).pack(pady=5)
        tk.Button(dialog, text="TRANSFERENCIA", command=lambda: [dialog.destroy(), self.procesar_pago("TRANSFERENCIA")], bg="#6c5ce7", **btn_opts).pack(pady=5)

    def mostrar_qr_pago(self, tipo):
        if not self.items: return
        # Creamos una venta temporal para el external_reference
        temp_v_id = crear_venta(self.empresa_id, self.usuario_id)
        
        qr_string = ""
        if tipo == "MP":
            qr_string = generar_qr_mercadopago(temp_v_id, self.total)
        elif tipo == "PW":
            qr_string = generar_qr_payway(temp_v_id, self.total)
        elif tipo == "MODO":
            qr_string = generar_qr_modo(temp_v_id, self.total)

        if not qr_string:
            messagebox.showerror("Error", f"No se pudo conectar con la pasarela de pago {tipo}.")
            return

        qr_win = tk.Toplevel(self.root); qr_win.geometry("400x520"); qr_win.title(f"Pagar con QR - {tipo}")
        qr_win.configure(bg="white")
        
        tk.Label(qr_win, text=f"ESCANEA PARA PAGAR $ {float(self.total):.2f}", font=("Arial", 12, "bold"), bg="white").pack(pady=10)
        
        # Generar imagen QR
        qr_img = qrcode.make(qr_string).resize((300, 300))
        qr_photo = ImageTk.PhotoImage(qr_img)
        lbl_img = tk.Label(qr_win, image=qr_photo, bg="white")
        lbl_img.image = qr_photo
        lbl_img.pack(pady=10)

        tk.Button(qr_win, text="CONFIRMAR PAGO REALIZADO", bg=self.colors['success'], fg="white", 
                  command=lambda: [qr_win.destroy(), self.finalizar_venta_qr(temp_v_id, tipo)]).pack(pady=20)

    def finalizar_venta_qr(self, v_id, tipo):
        # Como el v_id ya fue creado al generar el QR, solo agregamos items y cerramos
        for it in self.items.values(): agregar_item(v_id, it, it["cantidad"])
        cerrar_venta(v_id, float(self.total), self.items.values(), self.empresa_id)
        registrar_pago(v_id, float(self.total), f"QR_{tipo}", float(self.total), 0, self.empresa_id)
        
        messagebox.showinfo("Venta", f"Pago por QR {tipo} registrado.")
        self.nueva_venta()
        self.cargar_productos_stock()

    # --- RESTO DE FUNCIONES (STOCK, PRODUCTOS, DESCUENTOS) ---
    def cargar_productos_stock(self):
        for item in self.tabla.get_children(): self.tabla.delete(item)
        prods = obtener_productos(self.empresa_id)
        for p in prods:
            icono = self.obtener_icono(p.get("imagen"))
            self.tabla.insert("", tk.END, image=icono if icono else "", values=(p["codigo"], p["nombre"], f"$ {float(p['precio']):.2f}", p["stock"]))

    def agregar_producto(self, texto):
        producto = buscar_producto_por_codigo(texto, self.empresa_id)
        if not producto:
            res = buscar_productos_por_nombre(texto, self.empresa_id)
            if not res: beep(); self.input_codigo.delete(0, tk.END); return
            producto = res[0]
        sku = producto["codigo"]; precio_base = Decimal(str(producto["precio"]))
        if sku in self.items: self.items[sku]["cantidad"] += 1
        else:
            self.items[sku] = {"id": producto["id"], "nombre": producto["nombre"], "cantidad": 1, 
                               "precio_original": precio_base, "precio": precio_base, "subtotal": precio_base, 
                               "imagen": producto.get("imagen")}
        self.orden.append(sku); self.recalcular_precios(); beep(); self.input_codigo.delete(0, tk.END)

    def recalcular_precios(self):
        self.total = Decimal('0')
        for sku, item in self.items.items():
            regla = obtener_regla_mayorista(item["id"], self.empresa_id)
            p_final = item["precio_original"]
            if regla and item["cantidad"] >= regla["cantidad_minima"]:
                p_final = item["precio_original"] * (1 - Decimal(str(regla["descuento_porcentaje"])) / 100)
            item["precio"] = p_final; item["subtotal"] = item["precio"] * item["cantidad"]; self.total += item["subtotal"]

        combos = obtener_combos_activos(self.empresa_id)
        for c in combos:
            ids_necesarios = [int(x) for x in c['productos_ids'].split(',')]
            ids_carrito = [i['id'] for i in self.items.values()]
            if all(pid in ids_carrito for pid in ids_necesarios):
                factor = Decimal(str(c['descuento_porcentaje'])) / 100
                for pid in ids_necesarios:
                    for i in self.items.values():
                        if i['id'] == pid: self.total -= (i['subtotal'] * factor)

        if self.descuento_cupon_actual > 0: self.total -= (self.total * self.descuento_cupon_actual / 100)
        self.actualizar_carrito_ui()

    def abrir_lector_qr(self):
        dialog = tk.Toplevel(self.root); dialog.title("Escanear Cupón QR"); dialog.geometry("400x200")
        dialog.configure(bg=self.colors['bg_panel']); dialog.transient(self.root); dialog.grab_set()
        tk.Label(dialog, text="ESCANEE EL CÓDIGO QR", font=('Segoe UI', 12, 'bold'), bg=self.colors['bg_panel'], fg="white").pack(pady=20)
        entry_qr = tk.Entry(dialog, font=('Segoe UI', 16), justify='center'); entry_qr.pack(pady=10); entry_qr.focus()
        def validar():
            cupon = validar_cupon(entry_qr.get().strip().upper(), self.empresa_id)
            if cupon:
                self.descuento_cupon_actual = Decimal(str(cupon['descuento_porcentaje']))
                self.recalcular_precios(); beep(); dialog.destroy()
                messagebox.showinfo("Promoción", f"¡Cupón de {cupon['descuento_porcentaje']}% aplicado!")
            else: messagebox.showerror("Error", "Cupón inválido."); entry_qr.delete(0, tk.END)
        entry_qr.bind('<Return>', lambda e: validar())

    def borrar_item(self):
        if not self.orden: return
        sku = self.orden.pop()
        if sku in self.items:
            self.items[sku]["cantidad"] -= 1
            if self.items[sku]["cantidad"] <= 0: del self.items[sku]
        self.recalcular_precios()

    def limpiar_carrito(self):
        if self.items and messagebox.askyesno("Vaciar", "¿Desea limpiar el carrito?"):
            self.items = {}; self.orden = []; self.descuento_cupon_actual = Decimal('0'); self.recalcular_precios()

    def actualizar_carrito_ui(self):
        for item in self.carrito.get_children(): self.carrito.delete(item)
        for i in self.items.values():
            icono = self.obtener_icono(i.get("imagen"))
            self.carrito.insert("", tk.END, image=icono if icono else "", 
                                values=(i['nombre'], i['cantidad'], f"$ {float(i['precio']):.2f}", f"$ {float(i['subtotal']):.2f}"))
        self.total_label.config(text=f"$ {float(self.total):.2f}")

    def obtener_icono(self, ruta):
        if not ruta or not os.path.exists(ruta): return None
        if ruta in self.imagenes_cache: return self.imagenes_cache[ruta]
        try:
            img = Image.open(ruta).resize((32, 32), Image.Resampling.LANCZOS)
            photo = ImageTk.PhotoImage(img)
            self.imagenes_cache[ruta] = photo
            return photo
        except: return None

    def procesar_pago(self, metodo):
        if not self.items: return
        try:
            v_id = crear_venta(self.empresa_id, self.usuario_id)
            for it in self.items.values(): agregar_item(v_id, it, it["cantidad"])
            cerrar_venta(v_id, float(self.total), self.items.values(), self.empresa_id)
            registrar_pago(v_id, float(self.total), metodo, float(self.total + self.vuelto), float(self.vuelto), self.empresa_id)
            conn = get_connection()
            with conn.cursor() as cursor:
                cursor.execute("INSERT INTO finanzas (empresa_id, tipo, categoria, monto, descripcion, metodo_pago, usuario_id) VALUES (%s, 'INGRESO', 'Ventas', %s, %s, %s, %s)",
                               (self.empresa_id, float(self.total), f"Venta POS #{v_id}", metodo, self.usuario_id))
            conn.commit()
            txt = generar_ticket(conn, list(self.items.values()), float(self.total), v_id, metodo, float(self.vuelto), self.empresa_id)
            guardar_ticket(conn, txt, v_id); conn.close()
            self.vuelto_label.config(text=f"$ {float(self.vuelto):.2f}")
            messagebox.showinfo("Venta", f"Completada con éxito. Venta #{v_id}")
            self.nueva_venta(); self.cargar_productos_stock()
        except Exception as e: messagebox.showerror("Error", str(e))

    def nueva_venta(self):
        self.items = {}; self.orden = []; self.total = Decimal('0'); self.vuelto = Decimal('0'); self.descuento_cupon_actual = Decimal('0')
        self.vuelto_label.config(text=""); self.actualizar_carrito_ui()

    def on_input_submitted(self, event):
        t = self.input_codigo.get().strip()
        if t: self.agregar_producto(t)

    def key_f2(self):
        if self.items: self.mostrar_dialogo_pago()

    def mostrar_dialogo_efectivo(self):
        dialog = tk.Toplevel(self.root); dialog.geometry("350x250"); dialog.configure(bg=self.colors['bg_panel'])
        tk.Label(dialog, text="PAGA CON:", bg=self.colors['bg_panel'], fg="white", font=('Segoe UI', 12)).pack(pady=20)
        ent = tk.Entry(dialog, font=('Segoe UI', 22), justify='center'); ent.pack(pady=10); ent.focus()
        def confirm():
            try:
                rec = Decimal(ent.get().replace(',', '.'))
                if rec >= self.total:
                    self.vuelto = rec - self.total
                    dialog.destroy(); self.procesar_pago("EFECTIVO")
            except: pass
        ent.bind('<Return>', lambda e: confirm())

    def setup_keyboard_bindings(self):
        self.root.bind('<F2>', lambda e: self.key_f2())
        self.root.bind('<F3>', lambda e: self.borrar_item())
        self.root.bind('<F4>', lambda e: self.limpiar_carrito())
        self.root.bind('<F5>', lambda e: self.abrir_lector_qr())
        self.root.bind('<Escape>', lambda e: self.confirm_exit())

    def confirm_exit(self):
        if messagebox.askyesno("Salir", "¿Cerrar terminal de ventas?"): self.root.destroy()

if __name__ == "__main__":
    args = sys.argv
    negocio_nom = args[1] if len(args) > 1 else "NEXUS"
    emp_id = args[2] if len(args) > 2 else 1
    usu_id = args[3] if len(args) > 3 else 1
    root = tk.Tk()
    app = POSApp(root, negocio_nom, emp_id, usu_id)
    root.mainloop()