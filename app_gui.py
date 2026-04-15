import tkinter as tk
from tkinter import ttk, messagebox
from decimal import Decimal, ROUND_HALF_UP
from PIL import Image, ImageTk
import os
import sys
import qrcode
import datetime

# Importaciones de lógica
from productos import (obtener_productos, buscar_producto_por_codigo, 
                       buscar_productos_por_nombre, validar_cupon, 
                       obtener_regla_mayorista, obtener_combos_activos)
from ventas import crear_venta, agregar_item, cerrar_venta, registrar_pago
from ticket import generar_ticket, guardar_ticket 
from db import get_connection

# IMPORTACIÓN DE FACTURACIÓN ARCA (Con manejo de error de importación)
try:
    from facturacion_arca import FacturadorARCA
except ImportError as e:
    print(f"Advertencia: No se pudo cargar el módulo de facturación: {e}")
    FacturadorARCA = None

# IMPORTACIÓN DE PAGOS QR
from pagos_py import generar_qr_mercadopago, generar_qr_payway, generar_qr_modo

def beep():
    print("\a", end="", flush=True)

class POSApp:
    def __init__(self, root, nombre_negocio="NEXUS", empresa_id=1, usuario_id=1):
        self.root = root
        self.empresa_id = int(empresa_id)
        self.usuario_id = int(usuario_id)
        
        # 1. CARGAR CONFIGURACIÓN REAL DEL NEGOCIO DESDE DB
        self.config_negocio = self.cargar_datos_negocio()
        self.nombre_negocio = self.config_negocio.get('nombre_negocio', nombre_negocio).upper()
        
        # DATOS DE CLIENTE (Por defecto Consumidor Final)
        self.cliente_actual = {"id": 0, "nombre": "CONSUMIDOR FINAL", "documento": "0"}
        
        user_data = self.obtener_datos_usuario(self.usuario_id)
        self.usuario_nombre = user_data['nombre']
        self.usuario_rol = user_data['rol']
        
        self.permite_fraccion_global = self.cargar_permiso_fraccion()
        self.config_pagos = self.cargar_config_pagos()
        
        # INICIALIZAR FACTURADOR
        if FacturadorARCA:
            self.facturador = FacturadorARCA(self.empresa_id)
        else:
            self.facturador = None
        
        self.root.title(f"{self.nombre_negocio} - Terminal POS")
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
        self.create_widgets()
        self.nueva_venta()                  
        self.cargar_productos_stock() 
        self.setup_keyboard_bindings()
        self.input_codigo.focus()

    def cargar_datos_negocio(self):
        try:
            conn = get_connection()
            with conn.cursor() as cursor:
                cursor.execute("SELECT * FROM nombre_negocio WHERE empresa_id=%s OR id=1 LIMIT 1", (self.empresa_id,))
                res = cursor.fetchone()
                return res if res else {}
        except: return {}
        finally:
            if 'conn' in locals() and conn: conn.close()

    def abrir_selector_cliente(self):
        dialog = tk.Toplevel(self.root)
        dialog.title("BUSCAR CLIENTE")
        dialog.geometry("500x450")
        dialog.configure(bg=self.colors['bg_panel'])
        dialog.grab_set()

        tk.Label(dialog, text="BUSCAR POR NOMBRE O DOCUMENTO:", font=('Segoe UI', 10, 'bold'), bg=self.colors['bg_panel'], fg="white").pack(pady=10)
        ent_bus = tk.Entry(dialog, font=('Segoe UI', 12), bg="#252525", fg="white", insertbackground="white")
        ent_bus.pack(fill=tk.X, padx=20, pady=5)
        ent_bus.focus()

        tree_frame = tk.Frame(dialog, bg=self.colors['bg_panel'])
        tree_frame.pack(fill=tk.BOTH, expand=True, padx=20, pady=10)

        tree = ttk.Treeview(tree_frame, columns=("ID", "Nombre", "Doc"), show="headings", height=8)
        tree.heading("ID", text="ID"); tree.column("ID", width=40)
        tree.heading("Nombre", text="NOMBRE"); tree.column("Nombre", width=250)
        tree.heading("Doc", text="DOCUMENTO"); tree.column("Doc", width=120)
        tree.pack(side=tk.LEFT, fill=tk.BOTH, expand=True)
        
        scroll = ttk.Scrollbar(tree_frame, orient="vertical", command=tree.yview)
        tree.configure(yscrollcommand=scroll.set)
        scroll.pack(side=tk.RIGHT, fill=tk.Y)

        def buscar(e=None):
            for i in tree.get_children(): tree.delete(i)
            filtro = ent_bus.get()
            try:
                conn = get_connection()
                with conn.cursor() as cursor:
                    sql = "SELECT id, nombre, documento FROM clientes WHERE empresa_id=%s AND (nombre LIKE %s OR documento LIKE %s) LIMIT 30"
                    cursor.execute(sql, (self.empresa_id, f"%{filtro}%", f"%{filtro}%"))
                    for r in cursor.fetchall():
                        tree.insert("", tk.END, values=(r['id'], r['nombre'], r['documento']))
                conn.close()
            except: pass

        ent_bus.bind("<KeyRelease>", buscar)

        def seleccionar(e=None):
            item = tree.selection()
            if item:
                val = tree.item(item, "values")
                self.cliente_actual = {"id": val[0], "nombre": val[1], "documento": val[2]}
                self.lbl_cliente.config(text=f"CLIENTE: {str(val[1]).upper()}")
                dialog.destroy()
            else:
                messagebox.showwarning("Atención", "Seleccione un cliente de la lista")

        tk.Button(dialog, text="SELECCIONAR CLIENTE (ENTER)", font=('Segoe UI', 10, 'bold'), bg=self.colors['success'], fg="white", command=seleccionar).pack(pady=15)
        tree.bind("<Double-1>", seleccionar)
        dialog.bind("<Return>", seleccionar)
        buscar()

    def cargar_permiso_fraccion(self):
        try:
            conn = get_connection()
            with conn.cursor() as cursor:
                cursor.execute("SELECT permitir_fraccion FROM nombre_negocio WHERE empresa_id=%s OR id=1 LIMIT 1", (self.empresa_id,))
                res = cursor.fetchone()
                return bool(res['permitir_fraccion']) if res else False
        except: return False
        finally: 
            if 'conn' in locals() and conn: conn.close()

    def cargar_config_pagos(self):
        try:
            conn = get_connection()
            with conn.cursor() as cursor:
                cursor.execute("SELECT * FROM config_pagos WHERE empresa_id=%s OR id=1 LIMIT 1", (self.empresa_id,))
                return cursor.fetchone() or {}
        except: return {}
        finally:
            if 'conn' in locals() and conn: conn.close()

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

    def create_widgets(self):
        main_container = tk.Frame(self.root, bg=self.colors['bg_main'], padx=20, pady=20)
        main_container.pack(fill=tk.BOTH, expand=True)

        header = tk.Frame(main_container, bg=self.colors['bg_main'])
        header.pack(fill=tk.X, pady=(0, 20))
        
        tk.Label(header, text=self.nombre_negocio, font=('Segoe UI', 22, 'bold'), bg=self.colors['bg_main'], fg=self.colors['accent']).pack(side=tk.LEFT)
        
        info_panel = tk.Frame(header, bg=self.colors['bg_main'])
        info_panel.pack(side=tk.RIGHT)

        self.lbl_cliente = tk.Label(info_panel, text="CLIENTE: CONSUMIDOR FINAL", font=('Segoe UI', 10, 'bold'), bg='#252525', fg=self.colors['warning'], padx=15, pady=8, highlightthickness=1, highlightbackground=self.colors['border'])
        self.lbl_cliente.pack(side=tk.LEFT, padx=5)

        u_frame = tk.Frame(info_panel, bg='#252525', padx=15, pady=8, highlightthickness=1, highlightbackground=self.colors['border'])
        u_frame.pack(side=tk.LEFT)
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
        self.carrito.heading("Cant", text="CANT."); self.carrito.column("Cant", width=80)
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
        self.crear_boton(btn_container, "CLIENTE (F6)", self.colors['accent'], self.abrir_selector_cliente)
        self.crear_boton(btn_container, "QUITAR (F3)", self.colors['danger'], self.borrar_item)
        self.crear_boton(btn_container, "VACIAR (F4)", self.colors['warning'], self.limpiar_carrito)
        self.crear_boton(btn_container, "PROMOCIÓN/QR (F5)", self.colors['promo'], self.abrir_lector_qr)
        self.crear_boton(btn_container, "SALIR (ESC)", "#444", self.confirm_exit)

    def crear_boton(self, master, texto, color, comando):
        btn = tk.Button(master, text=texto, bg=color, fg="white", font=('Segoe UI', 9, 'bold'), relief="flat", padx=15, pady=10, command=comando, cursor="hand2")
        btn.pack(side=tk.LEFT, padx=3)
        return btn

    def mostrar_dialogo_pago(self):
        self.config_pagos = self.cargar_config_pagos()
        dialog = tk.Toplevel(self.root); dialog.geometry("400x650"); dialog.configure(bg=self.colors['bg_panel'])
        dialog.title("Seleccionar Pago")
        tk.Label(dialog, text="FORMA DE PAGO", font=('Segoe UI', 14, 'bold'), bg=self.colors['bg_panel'], fg="white").pack(pady=20)
        btn_opts = {"font": ('Segoe UI', 11, 'bold'), "width": 25, "pady": 10, "fg": "white"}
        
        tk.Button(dialog, text="EFECTIVO", command=lambda: [dialog.destroy(), self.mostrar_dialogo_efectivo()], bg=self.colors['success'], **btn_opts).pack(pady=5)
        tk.Button(dialog, text="TARJETA", command=lambda: [dialog.destroy(), self.procesar_pago("TARJETA")], bg=self.colors['accent'], **btn_opts).pack(pady=5)
        tk.Button(dialog, text="TRANSFERENCIA", command=lambda: [dialog.destroy(), self.procesar_pago("TRANSFERENCIA")], bg="#6c5ce7", **btn_opts).pack(pady=5)

        if self.config_pagos.get('mp_access_token'):
            tk.Button(dialog, text="QR MERCADO PAGO", command=lambda: [dialog.destroy(), self.mostrar_qr_pago("MP")], bg="#009ee3", **btn_opts).pack(pady=5)
        if self.config_pagos.get('pw_api_key'):
            tk.Button(dialog, text="QR PAYWAY", command=lambda: [dialog.destroy(), self.mostrar_qr_pago("PW")], bg="#ee3d2f", **btn_opts).pack(pady=5)
        if self.config_pagos.get('modo_api_key'):
            tk.Button(dialog, text="QR MODO", command=lambda: [dialog.destroy(), self.mostrar_qr_pago("MODO")], bg="#5cb85c", **btn_opts).pack(pady=5)

    def mostrar_qr_pago(self, tipo):
        if not self.items: return
        temp_v_id = crear_venta(self.empresa_id, self.usuario_id, self.cliente_actual["id"])
        
        qr_string = ""
        total_f = float(self.total)
        try:
            if tipo == "MP": qr_string = generar_qr_mercadopago(temp_v_id, total_f)
            elif tipo == "PW": qr_string = generar_qr_payway(temp_v_id, total_f)
            elif tipo == "MODO": qr_string = generar_qr_modo(temp_v_id, total_f)
        except Exception as e:
            messagebox.showerror("Error Pasarela", f"Error al conectar con {tipo}: {e}")
            return

        if not qr_string:
            messagebox.showerror("Error", f"La pasarela {tipo} no devolvió un código válido.")
            return

        qr_win = tk.Toplevel(self.root); qr_win.geometry("400x550"); qr_win.title(f"Pagar con QR - {tipo}")
        qr_win.configure(bg="white")
        tk.Label(qr_win, text=f"ESCANEA PARA PAGAR $ {total_f:.2f}", font=("Arial", 12, "bold"), bg="white").pack(pady=10)
        
        qr_img = qrcode.make(qr_string).resize((300, 300))
        qr_photo = ImageTk.PhotoImage(qr_img)
        lbl_img = tk.Label(qr_win, image=qr_photo, bg="white")
        lbl_img.image = qr_photo
        lbl_img.pack(pady=10)

        tk.Button(qr_win, text="CONFIRMAR PAGO REALIZADO", bg=self.colors['success'], fg="white", font=("Arial", 10, "bold"),
                  command=lambda: [qr_win.destroy(), self.finalizar_venta_qr(temp_v_id, tipo)]).pack(pady=20)

    def finalizar_venta_qr(self, v_id, tipo):
        total_f = float(self.total)
        total_costo = 0.0
        items_limpios = []
        for it in self.items.values():
            it_cl = {
                "id": int(it["id"]), "nombre": str(it["nombre"]), "cantidad": float(it["cantidad"]),
                "precio": float(it["precio"]), "subtotal": float(it["subtotal"]), "costo": float(it.get("costo", 0))
            }
            agregar_item(v_id, it_cl, it_cl["cantidad"])
            items_limpios.append(it_cl)
            total_costo += (it_cl["costo"] * it_cl["cantidad"])
            
        ganancia_real = total_f - total_costo
        cerrar_venta(v_id, total_f, items_limpios, self.empresa_id, ganancia=ganancia_real)
        
        # --- DISPARAR FACTURACIÓN AFIP PARA QR (MERGEADO) ---
        if self.facturador:
            print(f"DEBUG: Facturando venta QR #{v_id}")
            dni = self.cliente_actual.get('documento', '0')
            self.facturador.emitir_factura_c(v_id, 1, dni, total_f)
        
        registrar_pago(v_id, total_f, f"QR_{tipo}", total_f, 0.0, self.empresa_id)
        
        try:
            conn = get_connection()
            with conn.cursor() as cursor:
                cursor.execute("""
                    INSERT INTO finanzas (empresa_id, tipo, categoria, monto, descripcion, metodo_pago, usuario_id) 
                    VALUES (%s, 'INGRESO', 'Ventas', %s, %s, %s, %s)
                """, (self.empresa_id, total_f, f"Venta QR #{v_id} (Cliente ID {self.cliente_actual['id']})", f"QR_{tipo}", self.usuario_id))
            conn.commit()
            txt = generar_ticket(conn, items_limpios, total_f, v_id, f"QR_{tipo}", 0.0, self.empresa_id)
            guardar_ticket(conn, txt, v_id, self.empresa_id)
            conn.close()
        except: pass

        messagebox.showinfo("Venta", f"Pago por QR {tipo} registrado.")
        self.nueva_venta()
        self.cargar_productos_stock()

    def cargar_productos_stock(self):
        for item in self.tabla.get_children(): self.tabla.delete(item)
        prods = obtener_productos(self.empresa_id)
        for p in prods:
            # --- FILTRO DE STOCK: OCULTAR SI ES 0 O MENOR ---
            stock_actual = float(p.get("stock", 0))
            if stock_actual <= 0:
                continue
                
            icono = self.obtener_icono(p.get("imagen"))
            precio_p = float(p['precio']) if p['precio'] else 0.0
            self.tabla.insert("", tk.END, image=icono if icono else "", values=(p["codigo"], p["nombre"], f"$ {precio_p:.2f}", p["stock"]))

    def agregar_producto(self, texto):
        cantidad_input = Decimal('1')
        es_entrada_manual = False
        if "*" in texto:
            try:
                partes = texto.split("*")
                cantidad_input = Decimal(partes[0].replace(',', '.'))
                texto = partes[1]
                es_entrada_manual = True
            except: pass

        producto = buscar_producto_por_codigo(texto, self.empresa_id)
        if not producto:
            res = buscar_productos_por_nombre(texto, self.empresa_id)
            if not res: beep(); self.input_codigo.delete(0, tk.END); return
            producto = res[0]
            
        sku = producto["codigo"]
        
        # --- VALIDACIÓN DE STOCK: BLOQUEAR SI NO HAY DISPONIBLE ---
        stock_disponible = Decimal(str(producto.get("stock", 0)))
        cantidad_en_carrito = self.items[sku]["cantidad"] if sku in self.items else Decimal('0')
        
        if stock_disponible <= 0 or (cantidad_en_carrito + cantidad_input) > stock_disponible:
            beep()
            messagebox.showwarning("Stock Insuficiente", 
                                f"No hay stock suficiente de: {producto['nombre']}\n"
                                f"Disponible: {stock_disponible}")
            self.input_codigo.delete(0, tk.END)
            return

        precio_base = Decimal(str(producto["precio"]))
        costo_base = Decimal(str(producto.get("costo", 0)))
        es_pesable_prod = bool(producto.get('venta_por_peso', False))

        if not es_pesable_prod or not self.permite_fraccion_global:
            if es_entrada_manual:
                cantidad_input = Decimal(str(int(cantidad_input.to_integral_value(rounding=ROUND_HALF_UP))))
                if cantidad_input <= 0: cantidad_input = Decimal('1')
            else:
                cantidad_input = Decimal('1')

        if sku in self.items: 
            self.items[sku]["cantidad"] += cantidad_input
        else:
            self.items[sku] = {
                "id": producto["id"], "nombre": producto["nombre"], "cantidad": cantidad_input, 
                "precio_original": precio_base, "costo": costo_base, "precio": precio_base, 
                "subtotal": precio_base * cantidad_input, 
                "imagen": producto.get("imagen"), "es_pesable": es_pesable_prod
            }
        self.orden.append(sku)
        self.recalcular_precios()
        beep()
        self.input_codigo.delete(0, tk.END)

    def recalcular_precios(self):
        self.total = Decimal('0')
        for sku, item in self.items.items():
            regla = obtener_regla_mayorista(item["id"], self.empresa_id)
            p_final = item["precio_original"]
            if regla and item["cantidad"] >= regla["cantidad_minima"]:
                p_final = item["precio_original"] * (1 - Decimal(str(regla["descuento_porcentaje"])) / 100)
            item["precio"] = p_final
            item["subtotal"] = item["precio"] * item["cantidad"]
            self.total += item["subtotal"]

        combos = obtener_combos_activos(self.empresa_id)
        for c in combos:
            ids_necesarios = [int(x) for x in c['productos_ids'].split(',')]
            ids_carrito = [i['id'] for i in self.items.values()]
            if all(pid in ids_carrito for pid in ids_necesarios):
                factor = Decimal(str(c['descuento_porcentaje'])) / 100
                for pid in ids_necesarios:
                    for i in self.items.values():
                        if i['id'] == pid: self.total -= (i['subtotal'] * factor)

        if self.descuento_cupon_actual > 0: 
            self.total -= (self.total * self.descuento_cupon_actual / 100)
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
            if self.items[sku].get("es_pesable"):
                del self.items[sku]
            else:
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
            cant_fmt = f"{float(i['cantidad']):.3f}" if i.get("es_pesable") and self.permite_fraccion_global else f"{int(i['cantidad'])}"
            self.carrito.insert("", tk.END, image=icono if icono else "", 
                                values=(i['nombre'], cant_fmt, f"$ {float(i['precio']):.2f}", f"$ {float(i['subtotal']):.2f}"))
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
            v_id = crear_venta(self.empresa_id, self.usuario_id, self.cliente_actual["id"])
            total_f = float(self.total)
            vuelto_f = float(self.vuelto)
            monto_recibido_f = total_f + vuelto_f
            
            total_costo = 0.0
            items_finales_float = []
            for sku, it in self.items.items():
                it_f = {
                    "id": int(it["id"]), "nombre": str(it["nombre"]), "cantidad": float(it["cantidad"]),
                    "precio": float(it["precio"]), "subtotal": float(it["subtotal"]), "costo": float(it.get("costo", 0))
                }
                items_finales_float.append(it_f)
                agregar_item(v_id, it_f, it_f["cantidad"])
                total_costo += (it_f["costo"] * it_f["cantidad"])
            
            ganancia_real = total_f - total_costo
            cerrar_venta(v_id, total_f, items_finales_float, self.empresa_id, ganancia=ganancia_real)
            
            # --- DISPARAR FACTURACIÓN AFIP PARA TODOS LOS MÉTODOS (MERGEADO) ---
            if self.facturador:
                print(f"DEBUG: Facturando venta #{v_id} ({metodo})")
                dni = self.cliente_actual.get('documento', '0')
                self.facturador.emitir_factura_c(v_id, 1, dni, total_f)
            
            registrar_pago(v_id, total_f, metodo, monto_recibido_f, vuelto_f, self.empresa_id)
            
            conn = get_connection()
            with conn.cursor() as cursor:
                cursor.execute("""
                    INSERT INTO finanzas (empresa_id, tipo, categoria, monto, descripcion, metodo_pago, usuario_id) 
                    VALUES (%s, 'INGRESO', 'Ventas', %s, %s, %s, %s)
                """, (self.empresa_id, total_f, f"Venta #{v_id} (Cliente: {self.cliente_actual['nombre']})", metodo, self.usuario_id))
            conn.commit()
            
            txt = generar_ticket(conn, items_finales_float, total_f, v_id, metodo, vuelto_f, self.empresa_id)
            guardar_ticket(conn, txt, v_id, self.empresa_id)
            conn.close()
            
            self.vuelto_label.config(text=f"$ {vuelto_f:.2f}")
            messagebox.showinfo("Venta", f"Venta #{v_id} completada.")
            self.nueva_venta()
            self.cargar_productos_stock()
        except Exception as e: 
            messagebox.showerror("Error", f"Error al procesar: {e}")

    def nueva_venta(self):
        self.items = {}; self.orden = []
        self.total = Decimal('0'); self.vuelto = Decimal('0')
        self.descuento_cupon_actual = Decimal('0')
        self.cliente_actual = {"id": 0, "nombre": "CONSUMIDOR FINAL", "documento": "0"}
        self.lbl_cliente.config(text="CLIENTE: CONSUMIDOR FINAL")
        self.vuelto_label.config(text="")
        self.actualizar_carrito_ui()

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
                rec_val = Decimal(ent.get().replace(',', '.'))
                if rec_val >= self.total:
                    self.vuelto = rec_val - self.total
                    dialog.destroy(); self.procesar_pago("EFECTIVO")
                else: messagebox.showwarning("Pago", "Monto insuficiente")
            except: pass
        ent.bind('<Return>', lambda e: confirm())

    def setup_keyboard_bindings(self):
        self.root.bind('<F2>', lambda e: self.key_f2())
        self.root.bind('<F3>', lambda e: self.borrar_item())
        self.root.bind('<F4>', lambda e: self.limpiar_carrito())
        self.root.bind('<F5>', lambda e: self.abrir_lector_qr())
        self.root.bind('<F6>', lambda e: self.abrir_selector_cliente())
        self.root.bind('<Escape>', lambda e: self.confirm_exit())

    def confirm_exit(self):
        if messagebox.askyesno("Salir", "¿Cerrar terminal de ventas?"): self.root.destroy()

if __name__ == "__main__":
    root = tk.Tk()
    app = POSApp(root, "NEXUS", 1, 1)
    root.mainloop()