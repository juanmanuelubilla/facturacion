import tkinter as tk
from tkinter import ttk, messagebox
from decimal import Decimal, ROUND_HALF_UP
from PIL import Image, ImageTk
import os
import sys
try:
    import qrcode
except ImportError:
    qrcode = None
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
except ImportError:
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
        
        # Manejar cierre de ventana para evitar problemas
        self.root.protocol("WM_DELETE_WINDOW", self.cerrar_ventana)
        
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
        # Maximizar ventana con múltiples intentos y más tiempo
        self.root.after(500, self.maximizar_ventana)
        self.root.after(1000, self.maximizar_ventana)  # Segundo intento
        
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
        self.cliente_display_win = None
        self.cliente_display_img_cache = {}
        self.cliente_display_last_img = None
        
        self.setup_styles()
        self.create_widgets()
        self.nueva_venta()                  
        self.cargar_filtros()
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

    @staticmethod
    def _db_flag_activo(valor):
        """Convierte flags de DB (0/1, bool, str) a bool real."""
        if isinstance(valor, bool):
            return valor
        if valor is None:
            return False
        if isinstance(valor, (int, float, Decimal)):
            return int(valor) == 1
        return str(valor).strip().lower() in ("1", "true", "t", "si", "sí", "yes", "y", "on")

    @staticmethod
    def _fmt_stock(stock, es_pesable=False):
        try:
            s = float(stock or 0)
        except Exception:
            s = 0.0
        return f"{s:.3f}" if es_pesable else f"{int(s)}"

    def _fmt_cantidad(self, item):
        if item.get("es_pesable") and self.permite_fraccion_global:
            return f"{float(item['cantidad']):.3f}"
        return f"{int(item['cantidad'])}"

    def _obtener_imagen_cliente(self, ruta):
        if not ruta or not os.path.exists(ruta):
            return None
        # Si ya existe en cache, usar la existente
        if ruta in self.cliente_display_img_cache:
            return self.cliente_display_img_cache[ruta]
        try:
            # Crear nueva imagen solo si no existe en cache
            img = Image.open(ruta).resize((460, 460), Image.Resampling.LANCZOS)
            photo = ImageTk.PhotoImage(img)
            # Guardar en cache con referencia fuerte
            self.cliente_display_img_cache[ruta] = photo
            # También guardar como atributo de la instancia para mayor seguridad
            if not hasattr(self, '_img_refs'):
                self._img_refs = {}
            self._img_refs[ruta] = photo
            return photo
        except Exception as e:
            print(f"Error cargando imagen {ruta}: {e}")
            return None

    def _crear_pantalla_cliente(self):
        if self.cliente_display_win and self.cliente_display_win.winfo_exists():
            return

        win = tk.Toplevel(self.root)
        win.title(f"{self.nombre_negocio} - Pantalla Cliente")
        win.configure(bg="#0f0f0f")
        win.protocol("WM_DELETE_WINDOW", self.cerrar_pantalla_cliente)

        # Intento de posicionamiento en segundo monitor cuando hay escritorio extendido.
        self.root.update_idletasks()
        total_w = self.root.winfo_vrootwidth()
        total_h = self.root.winfo_vrootheight()
        prim_w = self.root.winfo_screenwidth()
        if total_w > prim_w:
            sec_w = max(total_w - prim_w, 800)
            win.geometry(f"{sec_w}x{total_h}+{prim_w}+0")
        else:
            win.geometry("1200x760+80+80")

        top = tk.Frame(win, bg="#0f0f0f", padx=20, pady=16)
        top.pack(fill=tk.X)
        self.cd_titulo = tk.Label(top, text=self.nombre_negocio, font=('Segoe UI', 26, 'bold'), bg="#0f0f0f", fg="#00db84")
        self.cd_titulo.pack(side=tk.LEFT)
        self.cd_total = tk.Label(top, text="$ 0.00", font=('Segoe UI', 30, 'bold'), bg="#0f0f0f", fg="#ffffff")
        self.cd_total.pack(side=tk.RIGHT)

        body = tk.Frame(win, bg="#0f0f0f")
        body.pack(fill=tk.BOTH, expand=True, padx=20, pady=(0, 20))

        left = tk.Frame(body, bg="#171717", highlightthickness=1, highlightbackground="#2a2a2a")
        left.pack(side=tk.LEFT, fill=tk.BOTH, expand=True, padx=(0, 10))
        right = tk.Frame(body, bg="#171717", highlightthickness=1, highlightbackground="#2a2a2a")
        right.pack(side=tk.LEFT, fill=tk.BOTH, expand=True, padx=(10, 0))

        tk.Label(left, text="ULTIMO PRODUCTO", font=('Segoe UI', 14, 'bold'), bg="#171717", fg="#a0a0a0").pack(pady=(16, 8))
        self.cd_img = tk.Label(left, text="SIN IMAGEN", font=('Segoe UI', 14), bg="#202020", fg="#7a7a7a", width=34, height=14)
        self.cd_img.pack(padx=20, pady=8, fill=tk.BOTH, expand=True)
        self.cd_nombre = tk.Label(left, text="-", font=('Segoe UI', 24, 'bold'), bg="#171717", fg="#ffffff", wraplength=580, justify="center")
        self.cd_nombre.pack(pady=(4, 4), padx=16)
        self.cd_detalle = tk.Label(left, text="", font=('Segoe UI', 16), bg="#171717", fg="#00a8ff")
        self.cd_detalle.pack(pady=(0, 16))

        tk.Label(right, text="COMPRA EN CURSO", font=('Segoe UI', 14, 'bold'), bg="#171717", fg="#a0a0a0").pack(pady=(16, 8))
        self.cd_items_container = tk.Frame(right, bg="#171717")
        self.cd_items_container.pack(fill=tk.BOTH, expand=True, padx=14, pady=8)
        self.cd_cliente = tk.Label(right, text="CLIENTE: CONSUMIDOR FINAL", font=('Segoe UI', 12, 'bold'), bg="#171717", fg="#f39c12")
        self.cd_cliente.pack(fill=tk.X, padx=14, pady=(0, 8))

        self.cliente_display_win = win
        self._actualizar_pantalla_cliente()

    def _actualizar_pantalla_cliente(self):
        win = self.cliente_display_win
        if not win or not win.winfo_exists():
            return

        try:
            self.cd_total.config(text=f"$ {float(self.total):.2f}")
            self.cd_cliente.config(text=f"CLIENTE: {self.cliente_actual.get('nombre', 'CONSUMIDOR FINAL')}")
        except Exception as e:
            print(f"Error actualizando pantalla cliente: {e}")
            return

        # Verificar que los widgets existan antes de usarlos
        if not hasattr(self, 'cd_img') or not hasattr(self, 'cd_nombre') or not hasattr(self, 'cd_detalle'):
            return

        try:
            ultimo = None
            while self.orden:
                sku = self.orden[-1]
                if sku in self.items:
                    ultimo = self.items[sku]
                    break
                self.orden.pop()
            if not ultimo and self.items:
                ultimo = list(self.items.values())[-1]

            if ultimo:
                self.cd_nombre.config(text=str(ultimo.get("nombre", "-")).upper())
                cant_txt = self._fmt_cantidad(ultimo)
                self.cd_detalle.config(text=f"{cant_txt} x $ {float(ultimo.get('precio', 0)):.2f} = $ {float(ultimo.get('subtotal', 0)):.2f}")
                
                # Cargar imagen con sistema mejorado de referencias
                foto = self._obtener_imagen_cliente(ultimo.get("imagen"))
                if foto:
                    self.cd_img.config(image=foto, text="")
                    self.cd_img.image = foto
                else:
                    self.cd_img.config(image="", text="SIN IMAGEN")
                    self.cd_img.image = None
            else:
                self.cd_nombre.config(text="SIN PRODUCTOS")
                self.cd_detalle.config(text="")
                self.cd_img.config(image="", text="SIN IMAGEN")
                self.cd_img.image = None
        except Exception as e:
            print(f"Error actualizando contenido de pantalla cliente: {e}")

        for w in self.cd_items_container.winfo_children():
            w.destroy()
        if not self.items:
            tk.Label(self.cd_items_container, text="Esperando productos...", bg="#171717", fg="#7a7a7a", font=('Segoe UI', 12)).pack(anchor="w")
        else:
            for item in self.items.values():
                cant_txt = self._fmt_cantidad(item)
                fila = tk.Frame(self.cd_items_container, bg="#171717")
                fila.pack(fill=tk.X, pady=2)
                tk.Label(fila, text=str(item["nombre"])[:28], bg="#171717", fg="#ffffff", font=('Segoe UI', 11)).pack(side=tk.LEFT)
                tk.Label(fila, text=f"{cant_txt}  $ {float(item['subtotal']):.2f}", bg="#171717", fg="#00db84", font=('Consolas', 11, 'bold')).pack(side=tk.RIGHT)

    def abrir_pantalla_cliente(self):
        self._crear_pantalla_cliente()

    def cerrar_pantalla_cliente(self):
        if self.cliente_display_win and self.cliente_display_win.winfo_exists():
            self.cliente_display_win.destroy()
        self.cliente_display_win = None

    def toggle_pantalla_cliente(self):
        if self.cliente_display_win and self.cliente_display_win.winfo_exists():
            self.cerrar_pantalla_cliente()
        else:
            self.abrir_pantalla_cliente()

    def buscar_cliente_en_tiempo_real(self, event=None):
        """Busca clientes en tiempo real mientras el usuario escribe"""
        texto = self.ent_buscar_cliente.get().strip()
        
        if len(texto) < 2:  # No buscar si hay menos de 2 caracteres
            self.clientes_encontrados = []
            self.indice_cliente_actual = -1
            return
        
        try:
            conn = get_connection()
            with conn.cursor() as cursor:
                sql = """
                    SELECT id, nombre, documento 
                    FROM clientes 
                    WHERE empresa_id=%s 
                    AND (nombre LIKE %s OR documento LIKE %s) 
                    ORDER BY nombre 
                    LIMIT 10
                """
                cursor.execute(sql, (self.empresa_id, f"%{texto}%", f"%{texto}%"))
                self.clientes_encontrados = cursor.fetchall()
                self.indice_cliente_actual = 0 if self.clientes_encontrados else -1
            conn.close()
        except Exception:
            self.clientes_encontrados = []
            self.indice_cliente_actual = -1
    
    def seleccionar_primer_cliente(self, event=None):
        """Selecciona el primer cliente encontrado al presionar Enter"""
        if self.clientes_encontrados and self.indice_cliente_actual >= 0:
            cliente = self.clientes_encontrados[self.indice_cliente_actual]
            self.cliente_actual = {
                "id": cliente['id'], 
                "nombre": cliente['nombre'], 
                "documento": cliente['documento']
            }
            self.lbl_cliente.config(text=f"CLIENTE: {cliente['nombre'].upper()}")
            self._actualizar_pantalla_cliente()
            self.ent_buscar_cliente.delete(0, tk.END)
            self.clientes_encontrados = []
            self.indice_cliente_actual = -1
            self.input_codigo.focus()  # Volver al campo de código
    
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

        # Configurar estilo para este Treeview específico
        style = ttk.Style()
        style.theme_use("clam")
        style.configure("Dialog.Treeview", 
                        background=self.colors['bg_panel'], 
                        foreground="white", 
                        fieldbackground=self.colors['bg_panel'], 
                        borderwidth=0)
        style.configure("Dialog.Treeview.Heading", 
                        font=('Segoe UI', 10, 'bold'), 
                        background="#252525", 
                        foreground="white", 
                        relief="flat")
        style.map("Dialog.Treeview",
                  background=[('selected', '#00a8ff'), ('focus', '#00a8ff')],
                  foreground=[('selected', 'white'), ('focus', 'white')])

        tree = ttk.Treeview(tree_frame, columns=("ID", "Nombre", "Doc"), show="headings", height=8, style="Dialog.Treeview")
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
                self._actualizar_pantalla_cliente()
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
                cursor.execute("""
                    SELECT permitir_fraccion
                    FROM nombre_negocio
                    WHERE empresa_id=%s OR id=1
                    ORDER BY (empresa_id=%s) DESC
                    LIMIT 1
                """, (self.empresa_id, self.empresa_id))
                res = cursor.fetchone()
                return self._db_flag_activo(res.get('permitir_fraccion')) if res else False
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

    def cargar_productos_stock(self):
        for item in self.tabla.get_children(): self.tabla.delete(item)
        prods = obtener_productos(self.empresa_id)
        for p in prods:
            # --- FILTRO DE STOCK: OCULTAR SI ES 0 O MENOR ---
            stock_actual = float(p.get("stock", 0))
            if stock_actual <= 0:
                continue
            # --- FILTRO DE ACTIVO: OCULTAR SI NO ESTÁ ACTIVO ---
            if not self._db_flag_activo(p.get("activo", 1)):
                continue
                
            icono = self.obtener_icono(p.get("imagen"))
            precio_p = float(p['precio']) if p['precio'] else 0.0
            self.tabla.insert("", tk.END, image=icono if icono else "", values=(p["codigo"], p["nombre"], f"$ {precio_p:.2f}", p["stock"]))

    def _db_flag_activo(self, valor):
        """Convierte valor de DB a boolean"""
        if valor is None:
            return False
        if isinstance(valor, bool):
            return valor
        if isinstance(valor, int):
            return valor == 1
        if isinstance(valor, str):
            return valor.lower() in ('1', 'true', 'si', 'yes')
        return bool(valor)

    def _fmt_stock(self, stock, es_pesable):
        """Formatea el stock según si es pesable o no"""
        try:
            stock_val = float(stock)
            if es_pesable:
                return f"{stock_val:.3f} m"
            else:
                return f"{int(stock_val)} u"
        except:
            return "0 u"

    def setup_styles(self):
        style = ttk.Style()
        style.theme_use('clam')
        self.root.configure(bg=self.colors['bg_main'])
        
        # Configuración completa para Treeview con fondo negro
        style.configure("Custom.Treeview", 
                        background=self.colors['bg_panel'], 
                        foreground=self.colors['text_main'], 
                        fieldbackground=self.colors['bg_panel'], 
                        borderwidth=0, 
                        font=('Segoe UI', 10), 
                        rowheight=45)
        
        # Configuración para encabezados
        style.configure("Custom.Treeview.Heading", 
                        font=('Segoe UI', 10, 'bold'), 
                        background="#252525", 
                        foreground="white", 
                        relief="flat")
        
        # Mapeo para selección y hover
        style.map("Custom.Treeview",
                  background=[('selected', '#00a8ff'), ('focus', '#00a8ff')],
                  foreground=[('selected', 'white'), ('focus', 'white')])
        
        # FORZAR ACTUALIZACIÓN DEL ESTILO
        style.configure("TTreeview", 
                        background=self.colors['bg_panel'], 
                        foreground=self.colors['text_main'], 
                        fieldbackground=self.colors['bg_panel'], 
                        borderwidth=0, 
                        font=('Segoe UI', 10), 
                        rowheight=45)
        
        style.configure("TTreeview.Heading", 
                        font=('Segoe UI', 10, 'bold'), 
                        background="#252525", 
                        foreground="white", 
                        relief="flat")
        
        style.map("TTreeview",
                  background=[('selected', '#00a8ff'), ('focus', '#00a8ff')],
                  foreground=[('selected', 'white'), ('focus', 'white')])

    def create_widgets(self):
        main_container = tk.Frame(self.root, bg=self.colors['bg_main'], padx=20, pady=20)
        main_container.pack(fill=tk.BOTH, expand=True)

        header = tk.Frame(main_container, bg=self.colors['bg_main'])
        header.pack(fill=tk.X, pady=(0, 20))
        
        tk.Label(header, text=self.nombre_negocio, font=('Segoe UI', 22, 'bold'), bg=self.colors['bg_main'], fg=self.colors['accent']).pack(side=tk.LEFT)
        
        info_panel = tk.Frame(header, bg=self.colors['bg_main'])
        info_panel.pack(side=tk.RIGHT)

        # --- PANEL DE CLIENTE Y USUARIO EN UNA SOLA LÍNEA ---
        cliente_usuario_frame = tk.Frame(info_panel, bg=self.colors['bg_main'])
        cliente_usuario_frame.pack(side=tk.TOP, fill=tk.X)
        
        # Campo de búsqueda de clientes
        self.ent_buscar_cliente = tk.Entry(cliente_usuario_frame, font=('Segoe UI', 9), bg="#252525", fg="white", insertbackground="white", highlightthickness=1, highlightbackground=self.colors['accent'], width=20)
        self.ent_buscar_cliente.pack(side=tk.LEFT, padx=(0, 5))
        self.ent_buscar_cliente.bind('<KeyRelease>', self.buscar_cliente_en_tiempo_real)
        self.ent_buscar_cliente.bind('<Return>', self.seleccionar_primer_cliente)
        
        # Botón de búsqueda
        btn_buscar = tk.Button(cliente_usuario_frame, text="🔍", bg=self.colors['accent'], fg="white", font=('Segoe UI', 9, 'bold'), relief="flat", padx=6, pady=3, command=self.abrir_selector_cliente, cursor="hand2")
        btn_buscar.pack(side=tk.LEFT, padx=(0, 10))
        
        # Label del cliente seleccionado
        self.lbl_cliente = tk.Label(cliente_usuario_frame, text="CLIENTE: CONSUMIDOR FINAL", font=('Segoe UI', 10, 'bold'), bg='#252525', fg=self.colors['warning'], padx=12, pady=6, highlightthickness=1, highlightbackground=self.colors['border'])
        self.lbl_cliente.pack(side=tk.LEFT, padx=(0, 10))
        
        # Frame del usuario
        u_frame = tk.Frame(cliente_usuario_frame, bg='#252525', padx=12, pady=6, highlightthickness=1, highlightbackground=self.colors['border'])
        u_frame.pack(side=tk.LEFT)
        tk.Label(u_frame, text=f"USUARIO: {self.usuario_nombre}", font=('Segoe UI', 9, 'bold'), bg='#252525', fg=self.colors['success']).pack()

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
        
        # --- PANEL DE FILTROS ---
        filtros_frame = tk.Frame(cat_frame, bg=self.colors['bg_panel'], pady=10)
        filtros_frame.pack(fill=tk.X, padx=10)
        
        tk.Label(filtros_frame, text="FILTROS:", font=('Segoe UI', 10, 'bold'), 
                bg=self.colors['bg_panel'], fg=self.colors['accent']).pack(anchor="w")
        
        # Frame para filtros
        filtros_contenido = tk.Frame(filtros_frame, bg=self.colors['bg_panel'])
        filtros_contenido.pack(fill=tk.X, pady=5)
        
        # Filtro por categoría
        cat_filtro_frame = tk.Frame(filtros_contenido, bg=self.colors['bg_panel'])
        cat_filtro_frame.pack(side=tk.LEFT, fill=tk.X, expand=True, padx=(0, 5))
        
        tk.Label(cat_filtro_frame, text="Categoría:", font=('Segoe UI', 8), 
                bg=self.colors['bg_panel'], fg="white").pack(anchor="w")
        
        self.combo_filtro_categoria = ttk.Combobox(cat_filtro_frame, values=["Todas"], state="readonly", 
                                                   font=('Segoe UI', 9), width=20)
        self.combo_filtro_categoria.pack(fill=tk.X)
        self.combo_filtro_categoria.set("Todas")
        self.combo_filtro_categoria.bind("<<ComboboxSelected>>", self.filtrar_productos)
        
        # Filtro por tags
        tag_filtro_frame = tk.Frame(filtros_contenido, bg=self.colors['bg_panel'])
        tag_filtro_frame.pack(side=tk.LEFT, fill=tk.X, expand=True, padx=(5, 0))
        
        tk.Label(tag_filtro_frame, text="Tags:", font=('Segoe UI', 8), 
                bg=self.colors['bg_panel'], fg="white").pack(anchor="w")
        
        self.combo_filtro_tag = ttk.Combobox(tag_filtro_frame, values=["Todos"], state="readonly", 
                                            font=('Segoe UI', 9), width=20)
        self.combo_filtro_tag.pack(fill=tk.X)
        self.combo_filtro_tag.set("Todos")
        self.combo_filtro_tag.bind("<<ComboboxSelected>>", self.filtrar_productos)
        
        # Botón para limpiar filtros
        btn_limpiar_frame = tk.Frame(filtros_contenido, bg=self.colors['bg_panel'])
        btn_limpiar_frame.pack(side=tk.LEFT, padx=(5, 0))
        
        tk.Button(btn_limpiar_frame, text="LIMPIAR", bg=self.colors['warning'], fg="white", 
                 font=('Segoe UI', 8, 'bold'), command=self.limpiar_filtros, 
                 width=8).pack(pady=(15, 0))
        
        # Separador
        tk.Frame(cat_frame, height=2, bg=self.colors['border']).pack(fill=tk.X, pady=5)
        
        self.tabla = ttk.Treeview(cat_frame, columns=("SKU", "Nombre", "Precio", "Stock"), show="tree headings", style="Custom.Treeview")
        self.tabla.heading("#0", text="IMG"); self.tabla.column("#0", width=60)
        self.tabla.heading("SKU", text="SKU"); self.tabla.column("SKU", width=100)
        self.tabla.heading("Nombre", text="PRODUCTO"); self.tabla.column("Nombre", width=250)
        self.tabla.heading("Precio", text="PRECIO"); self.tabla.column("Precio", width=100)
        self.tabla.heading("Stock", text="STOCK"); self.tabla.column("Stock", width=80)
        self.tabla.pack(fill=tk.BOTH, expand=True)
        
        # --- DOBLE CLIC PARA AGREGAR PRODUCTO ---
        self.tabla.bind("<Double-Button-1>", self.agregar_producto_seleccionado)
        self.tabla.bind("<Double-Button-3>", self.agregar_producto_seleccionado)  # Doble clic derecho también

        car_frame = tk.Frame(content, bg=self.colors['bg_panel'], highlightbackground=self.colors['border'], highlightthickness=1)
        car_frame.pack(side=tk.LEFT, fill=tk.BOTH, expand=True, padx=(10, 0))
        self.carrito = ttk.Treeview(car_frame, columns=("Prod", "Cant", "Precio", "Sub"), show="tree headings", style="Custom.Treeview")
        self.carrito.heading("#0", text="IMG"); self.carrito.column("#0", width=60)
        self.carrito.heading("Prod", text="PRODUCTO"); self.carrito.column("Prod", width=200)
        self.carrito.heading("Cant", text="CANT."); self.carrito.column("Cant", width=80)
        self.carrito.heading("Precio", text="UNIT."); self.carrito.column("Precio", width=90)
        self.carrito.heading("Sub", text="SUBTOTAL"); self.carrito.column("Sub", width=90)
        self.carrito.pack(fill=tk.BOTH, expand=True)
        
        # --- MENÚ CONTEXTUAL PARA EDITAR CANTIDADES ---
        self.carrito.bind("<Button-3>", self.mostrar_menu_contextual_carrito)
        self.carrito.bind("<Double-Button-1>", self.editar_cantidad_seleccionado)  # Doble clic izquierdo - solo edita
        self.carrito.bind("<Double-Button-3>", self.editar_cantidad_seleccionado)  # Doble clic derecho - solo edita

        footer = tk.Frame(main_container, bg=self.colors['bg_main'], pady=20)
        footer.pack(fill=tk.X)
        
        self.input_codigo = tk.Entry(footer, font=('Segoe UI', 18), bg="#252525", fg="white", borderwidth=0, highlightthickness=1, highlightbackground=self.colors['border'])
        self.input_codigo.pack(side=tk.LEFT, fill=tk.X, expand=True, ipady=8)
        self.input_codigo.bind('<Return>', self.on_input_submitted)

        btn_container = tk.Frame(footer, bg=self.colors['bg_main'])
        btn_container.pack(side=tk.RIGHT, padx=(20, 0))
        
        self.crear_boton(btn_container, "COBRAR (F2)", self.colors['success'], self.key_f2)
        self.crear_boton(btn_container, "🔍 BUSCAR CLIENTE (F6)", self.colors['accent'], self.abrir_selector_cliente)
        self.crear_boton(btn_container, "QUITAR (F3)", self.colors['danger'], self.borrar_item)
        self.crear_boton(btn_container, "VACIAR (F4)", self.colors['warning'], self.limpiar_carrito)
        self.crear_boton(btn_container, "PROMOCIÓN/QR (F5)", self.colors['promo'], self.abrir_lector_qr)
        self.crear_boton(btn_container, "PANTALLA CLIENTE (F8)", "#2d8cff", self.toggle_pantalla_cliente)
        self.crear_boton(btn_container, "SALIR (ESC)", "#444", self.confirm_exit)

    def maximizar_ventana(self):
        """Intenta maximizar la ventana con múltiples métodos"""
        try:
            self.root.state('zoomed')
        except:
            try:
                self.root.attributes('-zoomed', True)
            except:
                # Si no funciona, establecer tamaño grande
                self.root.geometry("1600x900+0+0")

    def crear_boton(self, master, texto, color, comando):
        # Botones con el mismo tamaño que en main.py
        btn = tk.Button(master, text=texto, bg=color, fg="white", font=('Segoe UI', 8, 'bold'), relief="flat", padx=20, pady=8, command=comando, cursor="hand2")
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
        
        if qrcode:
            qr_img = qrcode.make(qr_string).resize((300, 300))
            qr_photo = ImageTk.PhotoImage(qr_img)
            lbl_img = tk.Label(qr_win, image=qr_photo, bg="white")
            lbl_img.image = qr_photo
            lbl_img.pack(pady=10)
        else:
            tk.Label(qr_win, text="Módulo QR no disponible\nInstale: pip install qrcode", 
                     font=("Arial", 10), bg="white", fg="red").pack(pady=50)

        tk.Button(qr_win, text="CONFIRMAR PAGO REALIZADO", bg=self.colors['success'], fg="white", font=("Arial", 10, "bold"),
                  command=lambda: [qr_win.destroy(), self.finalizar_venta_qr(temp_v_id, tipo)]).pack(pady=20)

    def registrar_pago_qr(self, venta_id, total, tipo):
        """Registra el pago QR en la base de datos con estado pendiente"""
        try:
            conn = get_connection()
            with conn.cursor() as cursor:
                # Generar referencia externa única
                external_ref = f"QR_{tipo}_{venta_id}_{datetime.datetime.now().strftime('%Y%m%d%H%M%S')}"
                
                cursor.execute("""
                    INSERT INTO pagos (venta_id, metodo, monto, entregado, vuelto, estado, empresa_id, external_reference, qr_data)
                    VALUES (%s, %s, %s, %s, 'pendiente', %s, %s, %s)
                """, (venta_id, f"QR_{tipo}", total, total, 0.0, self.empresa_id, external_ref, f"QR_GENERADO_{tipo}"))
                conn.commit()
                conn.close()
                return True
        except Exception as e:
            print(f"Error al registrar pago QR: {e}")
            return False
    
    def iniciar_validacion_pago(self, venta_id, tipo):
        """Inicia el monitoreo de validación del pago QR"""
        try:
            # Aquí podrías implementar un hilo para verificar el estado del pago
            # Por ahora, solo mostramos mensaje al usuario
            messagebox.showinfo(
                "Validación de Pago",
                f"El pago QR {tipo} está siendo monitoreado.\n\n"
                f"Venta ID: {venta_id}\n"
                f"Estado actual: Pendiente de validación\n\n"
                f"El sistema verificará automáticamente cuando se confirme el pago.",
                icon="info"
            )
        except Exception as e:
            print(f"Error al iniciar validación: {e}")
    
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
            dni = self.cliente_actual.get('documento', '0')
            self.facturador.emitir_factura_c(v_id, 1, dni, total_f)
        
        # --- REGISTRAR PAGO QR CON ESTADO PENDIENTE ---
        self.registrar_pago_qr(v_id, total_f, tipo)
        
        # --- OFRECER ENVÍO DE FACTURA POR WHATSAPP ---
        self.ofrecer_enviar_factura_whatsapp(v_id, conn)
        
        # --- GENERAR TICKET ---
        txt = generar_ticket(conn, items_limpios, total_f, v_id, f"QR_{tipo}", 0.0, self.empresa_id)
        guardar_ticket(conn, txt, v_id, self.empresa_id)
        conn.close()
        
        messagebox.showinfo("Venta", f"Pago por QR {tipo} registrado. Estado: pendiente de validación.")
        self.nueva_venta()
        self.cargar_productos_stock()
        
        # --- INICIAR MONITOREO DE VALIDACIÓN ---
        self.iniciar_validacion_pago(v_id, tipo)

    def pedir_cantidad_fraccional(self, producto):
        """Muestra un diálogo para ingresar cantidad de producto fraccional"""
        dialog = tk.Toplevel(self.root)
        dialog.title("INGRESAR CANTIDAD")
        dialog.geometry("400x250")
        dialog.configure(bg=self.colors['bg_panel'])
        dialog.grab_set()
        dialog.transient(self.root)
        
        # Centrar el diálogo
        dialog.update_idletasks()
        x = (dialog.winfo_screenwidth() // 2) - (dialog.winfo_width() // 2)
        y = (dialog.winfo_screenheight() // 2) - (dialog.winfo_height() // 2)
        dialog.geometry(f"+{x}+{y}")
        
        # Información del producto
        tk.Label(dialog, text="PRODUCTO FRACCIONAL", font=('Segoe UI', 12, 'bold'), 
                 bg=self.colors['bg_panel'], fg=self.colors['accent']).pack(pady=(20, 10))
        
        tk.Label(dialog, text=producto['nombre'], font=('Segoe UI', 11, 'bold'), 
                 bg=self.colors['bg_panel'], fg="white").pack(pady=(0, 5))
        
        tk.Label(dialog, text=f"Precio: $ {float(producto['precio']):.2f}", font=('Segoe UI', 10), 
                 bg=self.colors['bg_panel'], fg=self.colors['success']).pack(pady=(0, 20))
        
        # Campo de cantidad
        frame_cantidad = tk.Frame(dialog, bg=self.colors['bg_panel'])
        frame_cantidad.pack(pady=10)
        
        tk.Label(frame_cantidad, text="Cantidad:", font=('Segoe UI', 10, 'bold'), 
                 bg=self.colors['bg_panel'], fg="white").pack(side=tk.LEFT, padx=(0, 10))
        
        ent_cantidad = tk.Entry(frame_cantidad, font=('Segoe UI', 12, 'bold'), 
                              bg="#252525", fg="white", insertbackground="white", 
                              width=15, justify="center")
        ent_cantidad.pack(side=tk.LEFT)
        ent_cantidad.focus()
        ent_cantidad.select_range(0, tk.END)
        
        # Variable para almacenar resultado
        resultado = {'cantidad': None}
        
        def aceptar():
            try:
                cantidad = Decimal(ent_cantidad.get().replace(',', '.'))
                if cantidad <= 0:
                    messagebox.showwarning("Cantidad inválida", "La cantidad debe ser mayor a 0")
                    return
                resultado['cantidad'] = cantidad
                dialog.destroy()
            except:
                messagebox.showerror("Error", "Ingrese una cantidad válida")
        
        def cancelar():
            resultado['cantidad'] = None
            dialog.destroy()
        
        # Botones
        frame_botones = tk.Frame(dialog, bg=self.colors['bg_panel'])
        frame_botones.pack(pady=20)
        
        btn_aceptar = tk.Button(frame_botones, text="ACEPTAR", bg=self.colors['success'], 
                               fg="black", font=('Segoe UI', 10, 'bold'), 
                               relief="flat", padx=20, pady=8, command=aceptar, cursor="hand2")
        btn_aceptar.pack(side=tk.LEFT, padx=5)
        
        btn_cancelar = tk.Button(frame_botones, text="CANCELAR", bg="#666", 
                                fg="white", font=('Segoe UI', 10, 'bold'), 
                                relief="flat", padx=20, pady=8, command=cancelar, cursor="hand2")
        btn_cancelar.pack(side=tk.LEFT, padx=5)
        
        # Bindings de teclado
        ent_cantidad.bind('<Return>', lambda e: aceptar())
        ent_cantidad.bind('<Escape>', lambda e: cancelar())
        
        # Esperar a que se cierre el diálogo
        dialog.wait_window()
        
        return resultado['cantidad']
    
    def agregar_producto(self, texto):
        print(f"Intentando agregar producto con texto: '{texto}'")  # Depuración
        cantidad_input = Decimal('1')
        es_entrada_manual = False
        
        # Verificar si ya incluye cantidad (formato: cantidad*codigo)
        if "*" in texto:
            try:
                partes = texto.split("*")
                cantidad_input = Decimal(partes[0].replace(',', '.'))
                texto = partes[1]
                es_entrada_manual = True
            except: pass

        producto = buscar_producto_por_codigo(texto, self.empresa_id)
        print(f"Resultado de búsqueda por código: {producto}")  # Depuración
        if not producto:
            print("Producto no encontrado por código, buscando por nombre...")  # Depuración
            res = buscar_productos_por_nombre(texto, self.empresa_id)
            print(f"Resultado de búsqueda por nombre: {res}")  # Depuración
            if not res: 
                print("Producto no encontrado en ninguna búsqueda")  # Depuración
                beep(); self.input_codigo.delete(0, tk.END); return
            producto = res[0]
            
        sku = producto["codigo"]
        es_pesable_prod = self._db_flag_activo(producto.get('venta_por_peso', False))
        
        # Si el producto es fraccional y no se ingresó cantidad manualmente,
        # pedir la cantidad en un diálogo
        if es_pesable_prod and self.permite_fraccion_global and not es_entrada_manual:
            cantidad_input = self.pedir_cantidad_fraccional(producto)
            if cantidad_input is None:  # Usuario canceló
                self.input_codigo.delete(0, tk.END)
                return
        
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

    def mostrar_menu_contextual_carrito(self, event):
        """Muestra menú contextual para editar o eliminar items del carrito"""
        item = self.carrito.identify('item', event.x, event.y)
        if not item:
            return
            
        menu = tk.Menu(self.root, tearoff=0)
        menu.add_command(label="✏️ Editar Cantidad", command=lambda: self.editar_cantidad_item(item))
        menu.add_command(label="🗑️ Eliminar Item", command=lambda: self.eliminar_item_carrito(item))
        
        try:
            menu.tk_popup(event.x_root, event.y_root)
        except:
                pass
    
    def editar_cantidad_seleccionado(self, event):
        """Edita cantidad con doble clic directamente en la línea"""
        item = self.carrito.identify('item', event.x, event.y)
        if item:
            # Obtener valores actuales del item
            valores = self.carrito.item(item, 'values')
            nombre_producto = valores[0]
            
            # Buscar el SKU del producto
            sku_encontrado = None
            for sku, item_data in self.items.items():
                if item_data['nombre'] == nombre_producto:
                    sku_encontrado = sku
                    break
            
            if sku_encontrado:
                item_data = self.items[sku_encontrado]
                es_pesable = item_data.get('es_pesable', False)
                
                # Abrir diálogo de edición inline (como el botón derecho)
                self.editar_cantidad_item_inline(item, sku_encontrado, es_pesable)
    
    def editar_cantidad_item_inline(self, item, sku_encontrado, es_pesable):
        """Edita cantidad inline directamente en la línea"""
        try:
            # Obtener valores actuales
            valores = self.carrito.item(item, 'values')
            cantidad_actual = float(self.items[sku_encontrado]['cantidad'])
            
            # Diálogo simple para nueva cantidad
            dialog = tk.Toplevel(self.root)
            dialog.title("EDITAR CANTIDAD")
            dialog.geometry("300x150")
            dialog.configure(bg=self.colors['bg_panel'])
            
            # Mostrar la ventana primero antes de hacer grab_set
            dialog.update()
            dialog.grab_set()
            dialog.transient(self.root)
            
            # Centrar
            dialog.update_idletasks()
            x = (dialog.winfo_screenwidth() // 2) - (dialog.winfo_width() // 2)
            y = (dialog.winfo_screenheight() // 2) - (dialog.winfo_height() // 2)
            dialog.geometry(f"+{x}+{y}")
            
            tk.Label(dialog, text="Nueva Cantidad:", font=('Segoe UI', 10, 'bold'),
                     bg=self.colors['bg_panel'], fg="white").pack(pady=10)
            
            ent_cantidad = tk.Entry(dialog, font=('Segoe UI', 12, 'bold'),
                                  bg="#252525", fg="white", insertbackground="white",
                                  width=15, justify="center")
            ent_cantidad.pack(pady=5)
            
            # Establecer cantidad actual
            if es_pesable:
                ent_cantidad.insert(0, f"{cantidad_actual:.3f}")
            else:
                ent_cantidad.insert(0, str(int(cantidad_actual)))
            
            ent_cantidad.select_range(0, tk.END)
            ent_cantidad.focus()
            
            def aceptar():
                try:
                    cantidad = Decimal(ent_cantidad.get().replace(',', '.'))
                    if cantidad <= 0:
                        messagebox.showwarning("Cantidad inválida", "La cantidad debe ser mayor a 0")
                        return
                    
                    # Si no es fraccional, redondear a entero
                    if not es_pesable:
                        cantidad = Decimal(str(int(cantidad.to_integral_value(rounding=ROUND_HALF_UP))))
                    
                    # Actualizar cantidad
                    self.items[sku_encontrado]['cantidad'] = cantidad
                    self.recalcular_precios()
                    self.actualizar_carrito_ui()
                    dialog.destroy()
                except:
                    messagebox.showerror("Error", "Ingrese una cantidad válida")
            
            def cancelar():
                dialog.destroy()
            
            # Botones
            frame_botones = tk.Frame(dialog, bg=self.colors['bg_panel'])
            frame_botones.pack(pady=15)
            
            tk.Button(frame_botones, text="ACEPTAR", bg='#00db84',
                           fg="black", font=('Segoe UI', 10, 'bold'),
                           relief="flat", padx=20, pady=8, command=aceptar, cursor="hand2").pack(side=tk.LEFT, padx=5)
            
            tk.Button(frame_botones, text="CANCELAR", bg="#666",
                            fg="white", font=('Segoe UI', 10, 'bold'),
                            relief="flat", padx=20, pady=8, command=cancelar, cursor="hand2").pack(side=tk.LEFT, padx=5)
            
            # Bindings
            ent_cantidad.bind('<Return>', lambda e: aceptar())
            ent_cantidad.bind('<Escape>', lambda e: cancelar())
            
            dialog.wait_window()
            
        except Exception as e:
            print(f"Error al editar cantidad inline: {e}")
            messagebox.showerror("Error", f"Error al editar cantidad: {e}")

    def editar_cantidad_item(self, item):
        """Abre diálogo para editar cantidad de un item"""
        if not item:
            return
            
        valores = self.carrito.item(item, 'values')
        nombre_producto = valores[0]  # Primer columna es el nombre
        
        # Buscar el item correspondiente en self.items
        sku_encontrado = None
        for sku, item_data in self.items.items():
            if item_data['nombre'] == nombre_producto:
                sku_encontrado = sku
                break
        
        if not sku_encontrado:
            messagebox.showerror("Error", "No se encontró el producto")
            return
        
        item_data = self.items[sku_encontrado]
        es_pesable = item_data.get('es_pesable', False)
        
        # Diálogo para editar cantidad
        dialog = tk.Toplevel(self.root)
        dialog.title("EDITAR CANTIDAD")
        dialog.geometry("450x300")
        dialog.configure(bg=self.colors['bg_panel'])  # Usar colores del sistema
        dialog.grab_set()
        dialog.transient(self.root)
        
        # Centrar el diálogo
        dialog.update_idletasks()
        x = (dialog.winfo_screenwidth() // 2) - (dialog.winfo_width() // 2)
        y = (dialog.winfo_screenheight() // 2) - (dialog.winfo_height() // 2)
        dialog.geometry(f"+{x}+{y}")
        
        # Información del producto
        tk.Label(dialog, text="EDITAR CANTIDAD", font=('Segoe UI', 12, 'bold'), 
                 bg='#1e1e1e', fg='#00a8ff').pack(pady=(20, 10))
        
        tk.Label(dialog, text=nombre_producto, font=('Segoe UI', 11, 'bold'), 
                 bg='#1e1e1e', fg="white").pack(pady=(0, 5))
        
        # Mostrar tipo de producto
        tipo_text = "FRACCIONAL" if es_pesable else "UNIDADES"
        tk.Label(dialog, text=f"Tipo: {tipo_text}", font=('Segoe UI', 9), 
                 bg='#1e1e1e', fg='#ffc107').pack(pady=(0, 10))
        
        tk.Label(dialog, text=f"Precio: $ {float(item_data['precio']):.2f}", font=('Segoe UI', 10), 
                 bg='#1e1e1e', fg='#00db84').pack(pady=(0, 20))
        
        # Campo de cantidad
        frame_cantidad = tk.Frame(dialog, bg='#1e1e1e')
        frame_cantidad.pack(pady=10)
        
        tk.Label(frame_cantidad, text="Nueva Cantidad:", font=('Segoe UI', 10, 'bold'), 
                 bg='#1e1e1e', fg="white").pack(side=tk.LEFT, padx=(0, 10))
        
        ent_cantidad = tk.Entry(frame_cantidad, font=('Segoe UI', 12, 'bold'), 
                              bg="#252525", fg="white", insertbackground="white", 
                              width=15, justify="center")
        ent_cantidad.pack(side=tk.LEFT)
        
        # Establecer cantidad actual
        cantidad_actual = float(item_data['cantidad'])
        if es_pesable:
            ent_cantidad.insert(0, f"{cantidad_actual:.3f}")
        else:
            ent_cantidad.insert(0, str(int(cantidad_actual)))
        
        ent_cantidad.select_range(0, tk.END)
        ent_cantidad.focus()
        
        # Variable para almacenar resultado
        resultado = {'cantidad': None}
        
        def aceptar():
            try:
                cantidad = Decimal(ent_cantidad.get().replace(',', '.'))
                if cantidad <= 0:
                    messagebox.showwarning("Cantidad inválida", "La cantidad debe ser mayor a 0")
                    return
                
                # Si no es fraccional, redondear a entero
                if not es_pesable:
                    cantidad = Decimal(str(int(cantidad.to_integral_value(rounding=ROUND_HALF_UP))))
                
                resultado['cantidad'] = cantidad
                dialog.destroy()
            except:
                messagebox.showerror("Error", "Ingrese una cantidad válida")
        
        def cancelar():
            resultado['cantidad'] = None
            dialog.destroy()
        
        # Botones
        frame_botones = tk.Frame(dialog, bg=self.colors['bg_panel'])
        frame_botones.pack(pady=20)
        
        btn_aceptar = tk.Button(frame_botones, text="ACEPTAR", bg='#00db84', 
                               fg="black", font=('Segoe UI', 12, 'bold'), 
                               relief="flat", padx=25, pady=12, command=aceptar, cursor="hand2")
        btn_aceptar.pack(side=tk.LEFT, padx=5)
        
        btn_cancelar = tk.Button(frame_botones, text="CANCELAR", bg="#666", 
                                fg="white", font=('Segoe UI', 12, 'bold'), 
                                relief="flat", padx=25, pady=12, command=cancelar, cursor="hand2")
        btn_cancelar.pack(side=tk.LEFT, padx=5)
        
        # Bindings de teclado
        ent_cantidad.bind('<Return>', lambda e: aceptar())
        ent_cantidad.bind('<Escape>', lambda e: cancelar())
        
        # Esperar a que se cierre el diálogo
        dialog.wait_window()
        
        # Actualizar cantidad si se aceptó
        if resultado['cantidad'] is not None:
            if resultado['cantidad'] == 0:
                # Si la cantidad es 0, eliminar el item
                self.eliminar_item_carrito(item)
            else:
                # Actualizar cantidad
                self.items[sku_encontrado]['cantidad'] = resultado['cantidad']
                self.recalcular_precios()
                self.actualizar_carrito_ui()
    
    def eliminar_item_carrito(self, item):
        """Elimina un item del carrito"""
        if not item:
            return
            
        valores = self.carrito.item(item, 'values')
        nombre_producto = valores[0]  # Primer columna es el nombre
        
        # Buscar el item correspondiente en self.items
        sku_encontrado = None
        for sku, item_data in self.items.items():
            if item_data['nombre'] == nombre_producto:
                sku_encontrado = sku
                break
        
        if sku_encontrado and messagebox.askyesno("Eliminar Item", 
                                           f"¿Desea eliminar '{nombre_producto}' del carrito?"):
            del self.items[sku_encontrado]
            # Remover de la orden también
            if sku_encontrado in self.orden:
                self.orden.remove(sku_encontrado)
            self.recalcular_precios()
            self.actualizar_carrito_ui()
    
    def actualizar_carrito_ui(self):
        for item in self.carrito.get_children(): self.carrito.delete(item)
        for i in self.items.values():
            icono = self.obtener_icono(i.get("imagen"))
            if i.get("es_pesable") and self.permite_fraccion_global:
                cant_fmt = f"{float(i['cantidad']):.3f} m"
            else:
                cant_fmt = f"{int(i['cantidad'])} u"
            self.carrito.insert("", tk.END, image=icono if icono else "", 
                                values=(i['nombre'], cant_fmt, f"$ {float(i['precio']):.2f}", f"$ {float(i['subtotal']):.2f}"))
        self.total_label.config(text=f"$ {float(self.total):.2f}")
        self._actualizar_pantalla_cliente()

    def obtener_icono(self, ruta):
        if not ruta or not os.path.exists(ruta):
            return None
        if ruta in self.imagenes_cache:
            return self.imagenes_cache[ruta]
        try:
            img = Image.open(ruta).resize((32, 32), Image.Resampling.LANCZOS)
            photo = ImageTk.PhotoImage(img)
            self.imagenes_cache[ruta] = photo
            return photo
        except Exception as e:
            print(f"Error cargando imagen {ruta}: {e}")
            return None

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
            
            # --- IMPRESIÓN AUTOMÁTICA DE TICKET ---
            self.imprimir_ticket_automaticamente(txt, v_id)
            
            # --- ENVIAR FACTURA POR WHATSAPP (OPCIONAL) ---
            self.ofrecer_enviar_factura_whatsapp(v_id, conn)
            
            conn.close()
            
            self.vuelto_label.config(text=f"$ {vuelto_f:.2f}")
            messagebox.showinfo("Venta", f"Venta #{v_id} completada.")
            self.nueva_venta()
            self.cargar_productos_stock()
        except Exception as e: 
            messagebox.showerror("Error", f"Error al procesar: {e}")

    def ofrecer_enviar_factura_whatsapp(self, venta_id, conn):
        """Ofrece enviar la factura por WhatsApp si el cliente tiene número"""
        try:
            # Verificar si el cliente tiene WhatsApp
            with conn.cursor() as cursor:
                cursor.execute("""
                    SELECT c.nombre, c.whatsapp, c.acepta_whatsapp,
                           ca.cae, ca.nro_cbte, ca.tipo_cbte
                    FROM ventas v
                    INNER JOIN clientes c ON v.cliente_id = c.id
                    LEFT JOIN comprobante_afip ca ON v.id = ca.venta_id
                    WHERE v.id = %s AND v.empresa_id = %s
                """, (venta_id, self.empresa_id))
                resultado = cursor.fetchone()
            
            if not resultado:
                return  # No hay cliente o comprobante
            
            cliente_nombre = resultado['nombre']
            cliente_whatsapp = resultado['whatsapp']
            acepta_whatsapp = resultado['acepta_whatsapp']
            cae = resultado['cae']
            nro_cbte = resultado['nro_cbte']
            tipo_cbte = resultado['tipo_cbte']
            
            # Verificar si tiene factura electrónica
            if not cae or not nro_cbte:
                return  # No hay factura electrónica
            
            # Verificar si tiene WhatsApp y acepta mensajes
            if not cliente_whatsapp or not acepta_whatsapp:
                return  # No tiene WhatsApp o no acepta mensajes
            
            # Ofrecer envío por WhatsApp
            respuesta = messagebox.askyesno(
                "Enviar Factura por WhatsApp",
                f"¿Desea enviar la factura #{venta_id} por WhatsApp al cliente {cliente_nombre}?\n\n"
                f"Cliente: {cliente_nombre}\n"
                f"WhatsApp: {cliente_whatsapp}\n"
                f"Factura: {tipo_cbte} {nro_cbte}\n"
                f"CAE: {cae}",
                icon="question"
            )
            
            if respuesta:
                self.enviar_factura_whatsapp(venta_id, cliente_nombre, cliente_whatsapp, cae, nro_cbte, tipo_cbte)
                
        except Exception as e:
            # Si hay error, no mostrar al usuario, solo loguear
            print(f"Error al ofrecer envío de factura por WhatsApp: {e}")
    
    def enviar_factura_whatsapp(self, venta_id, cliente_nombre, cliente_whatsapp, cae, nro_cbte, tipo_cbte):
        """Envía la factura por WhatsApp"""
        try:
            # Generar mensaje personalizado
            mensaje = (
                f"🧾 *FACTURA ELECTRÓNICA* 🧾\n\n"
                f"Estimado/a *{cliente_nombre}*\n\n"
                f"Le enviamos su factura:\n\n"
                f"📄 *Comprobante*: {tipo_cbte} {nro_cbte}\n"
                f"🔐 *CAE*: {cae}\n"
                f"🆔 *Venta*: #{venta_id}\n\n\n"
                f"Gracias por su compra\n"
                f"*{self.nombre_negocio}*"
            )
            
            # Normalizar número de WhatsApp
            whatsapp_normalizado = "".join(ch for ch in str(cliente_whatsapp) if ch.isdigit())
            
            if not whatsapp_normalizado:
                messagebox.showerror("Error", "El número de WhatsApp no es válido")
                return
            
            # Crear URL de WhatsApp
            url = f"https://wa.me/{whatsapp_normalizado}?text={quote(mensaje)}"
            
            # Abrir WhatsApp Web
            import webbrowser
            webbrowser.open(url)
            
            messagebox.showinfo(
                "WhatsApp Abierto",
                f"Se ha abierto WhatsApp con el mensaje para {cliente_nombre}\n\n"
                f"Por favor envíe el mensaje manualmente si no se envía automáticamente."
            )
            
        except Exception as e:
            messagebox.showerror("Error", f"No se pudo enviar la factura por WhatsApp: {e}")
    
    def nueva_venta(self):
        self.items = {}; self.orden = []
        # Variables para búsqueda de clientes
        self.clientes_encontrados = []
        self.indice_cliente_actual = -1
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

    def agregar_producto_seleccionado(self, event):
        """Agrega el producto seleccionado de la tabla al carrito"""
        try:
            print(f"Evento doble clic en posición: y={event.y}")  # Depuración
            
            # Obtener el item en la posición del cursor (más confiable que selection())
            item = self.tabla.identify_row(event.y)
            print(f"Item identificado: {item}")  # Depuración
            
            if not item:
                print("No se pudo identificar el item en la posición del cursor")
                # Intentar obtener el item seleccionado como fallback
                seleccion = self.tabla.selection()
                if seleccion:
                    item = seleccion[0]
                    print(f"Usando selección como fallback: {item}")
                else:
                    print("No hay item seleccionado como fallback")
                    return
            
            # Si no hay selección, seleccionar el item donde se hizo clic
            if not self.tabla.selection():
                self.tabla.selection_set(item)
            
            valores = self.tabla.item(item)['values']
            print(f"Valores obtenidos: {valores}")  # Depuración
            
            if valores and len(valores) > 0:
                codigo_raw = valores[0]
                # Manejo especial para códigos que podrían ser interpretados como 0
                if codigo_raw == 0 or codigo_raw == "0" or codigo_raw == "000000":
                    codigo = "000000"
                else:
                    codigo = str(codigo_raw)
                print(f"Código raw: {codigo_raw} (tipo: {type(codigo_raw)})")  # Depuración
                print(f"Código a agregar: '{codigo}' (longitud: {len(codigo)})")  # Depuración
                self.agregar_producto(codigo)
            else:
                print("No hay valores o están vacíos")
        except Exception as e:
            print(f"Error al agregar producto seleccionado: {e}")
            messagebox.showerror("Error", f"Error al agregar producto: {e}")

    def imprimir_ticket_automaticamente(self, txt_ticket, venta_id):
        """Imprime el ticket automáticamente si está configurado"""
        try:
            # Obtener configuración de impresoras
            conn = get_connection()
            with conn.cursor() as cursor:
                cursor.execute("""
                    SELECT impresora_auto, impresora_ticket 
                    FROM nombre_negocio 
                    WHERE empresa_id=%s OR id=1
                    ORDER BY (empresa_id=%s) DESC
                    LIMIT 1
                """, (self.empresa_id, self.empresa_id))
                config = cursor.fetchone()
            
            if config and config.get('impresora_auto', False):
                impresora = config.get('impresora_ticket', 'Default')
                
                # Importar librerías de impresión
                import subprocess
                import os
                
                # Comando de impresión según el sistema operativo
                if os.name == 'nt':  # Windows
                    comando = f'type "{txt_ticket}" > "{impresora}"'
                else:  # Linux/Mac
                    comando = f'lp -d "{impresora}" "{txt_ticket}"'
                
                try:
                    subprocess.run(comando, shell=True, check=True)
                    print(f"Ticket impreso automáticamente en {impresora}")
                except Exception as e:
                    print(f"Error al imprimir ticket automáticamente: {e}")
            
        except Exception as e:
            print(f"Error en impresión automática: {e}")
        finally:
            if 'conn' in locals() and conn: conn.close()

    def setup_keyboard_bindings(self):
        self.root.bind('<F2>', lambda e: self.key_f2())
        self.root.bind('<F3>', lambda e: self.borrar_item())
        self.root.bind('<F4>', lambda e: self.limpiar_carrito())
        self.root.bind('<F5>', lambda e: self.abrir_lector_qr())
        self.root.bind('<F6>', lambda e: self.abrir_selector_cliente())
        self.root.bind('<F8>', lambda e: self.toggle_pantalla_cliente())
        self.root.bind('<Escape>', lambda e: self.confirm_exit())

    def confirm_exit(self):
        if messagebox.askyesno("Salir", "¿Cerrar el terminal POS?"):
            self.root.destroy()

    def cargar_filtros(self):
        """Carga las categorías y tags disponibles en los filtros"""
        try:
            # Importar funciones de productos
            from productos import obtener_categorias, obtener_tags_unicos
            
            # Cargar categorías
            categorias = obtener_categorias(self.empresa_id)
            cat_values = ["Todas"] + [f"{cat['id']} - {cat['nombre']}" for cat in categorias]
            self.combo_filtro_categoria['values'] = cat_values
            self.categorias_datos = {"Todas": None}
            for cat in categorias:
                self.categorias_datos[f"{cat['id']} - {cat['nombre']}"] = cat['id']
            
            # Cargar tags
            tags = obtener_tags_unicos(self.empresa_id)
            tag_values = ["Todos"] + tags
            self.combo_filtro_tag['values'] = tag_values
            
        except Exception as e:
            print(f"Error cargando filtros: {e}")

    def filtrar_productos(self, event=None):
        """Filtra los productos según categoría y tag seleccionados"""
        try:
            categoria_sel = self.combo_filtro_categoria.get()
            tag_sel = self.combo_filtro_tag.get()
            
            # Limpiar tabla
            for item in self.tabla.get_children():
                self.tabla.delete(item)
            
            # Determinar qué productos cargar
            if categoria_sel != "Todas" and tag_sel != "Todos":
                # Filtrar por ambos
                categoria_id = self.categorias_datos[categoria_sel]
                from productos import buscar_productos_por_categoria, buscar_productos_por_tag
                productos_cat = buscar_productos_por_categoria(categoria_id, self.empresa_id)
                productos_tag = buscar_productos_por_tag(tag_sel, self.empresa_id)
                
                # Productos que cumplen ambos filtros
                ids_tag = {p['id'] for p in productos_tag}
                productos_filtrados = [p for p in productos_cat if p['id'] in ids_tag]
                
            elif categoria_sel != "Todas":
                # Filtrar solo por categoría
                categoria_id = self.categorias_datos[categoria_sel]
                from productos import buscar_productos_por_categoria
                productos_filtrados = buscar_productos_por_categoria(categoria_id, self.empresa_id)
                
            elif tag_sel != "Todos":
                # Filtrar solo por tag
                from productos import buscar_productos_por_tag
                productos_filtrados = buscar_productos_por_tag(tag_sel, self.empresa_id)
                
            else:
                # Mostrar todos los productos
                from productos import obtener_productos
                productos_filtrados = obtener_productos(self.empresa_id)
            
            # Cargar productos filtrados en la tabla
            self.cargar_productos_en_tabla(productos_filtrados)
            
        except Exception as e:
            print(f"Error filtrando productos: {e}")

    def limpiar_filtros(self):
        """Limpia todos los filtros y muestra todos los productos"""
        self.combo_filtro_categoria.set("Todas")
        self.combo_filtro_tag.set("Todos")
        self.cargar_productos_stock()

    def cargar_productos_en_tabla(self, productos):
        """Carga una lista específica de productos en la tabla"""
        for item in self.tabla.get_children(): self.tabla.delete(item)
        for p in productos:
            # --- FILTRO DE STOCK: OCULTAR SI ES 0 O MENOR ---
            stock_actual = float(p.get("stock", 0))
            if stock_actual <= 0:
                continue
            # --- FILTRO DE ACTIVO: OCULTAR SI NO ESTÁ ACTIVO ---
            if not self._db_flag_activo(p.get("activo", 1)):
                continue
                
            icono = self.obtener_icono(p.get("imagen"))
            precio_p = float(p['precio']) if p['precio'] else 0.0
            self.tabla.insert("", tk.END, image=icono if icono else "", values=(p["codigo"], p["nombre"], f"$ {precio_p:.2f}", p["stock"]))

    def cerrar_ventana(self):
        """Manejar el cierre de ventana de forma segura"""
        try:
            # Verificar si hay una venta en curso
            if hasattr(self, 'venta_en_curso') and self.venta_en_curso:
                if messagebox.askyesno("Venta en curso", "¿Desea cancelar la venta actual y salir?"):
                    self.cancelar_venta()
                    self.root.quit()
                    self.root.destroy()
            else:
                self.root.quit()
                self.root.destroy()
        except:
            pass

def ejecutar_ventas(nombre_negocio="NEXUS", empresa_id=1, usuario_id=1):
    """Función principal para ejecutar el módulo de ventas (POS)"""
    try:
        root = tk.Tk()
        app = POSApp(root, nombre_negocio, empresa_id, usuario_id)
        
        # Manejar cierre de ventana
        def on_closing():
            try:
                # Llamar al método de cerrar ventana que maneja todo correctamente
                app.cerrar_ventana()
            except:
                root.destroy()
        
        root.protocol("WM_DELETE_WINDOW", on_closing)
        root.mainloop()
        
    except KeyboardInterrupt:
        print("\n🛒 Módulo de ventas cerrado por el usuario")
    except Exception as e:
        print(f"❌ Error en módulo de ventas: {e}")
    finally:
        # Limpiar recursos si es necesario
        pass

if __name__ == "__main__":
    ejecutar_ventas("NEXUS", 1, 1)