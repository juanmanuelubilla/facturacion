import tkinter as tk
from tkinter import ttk, messagebox
import qrcode
from PIL import Image, ImageTk
import os
import sys
from datetime import datetime
from decimal import Decimal

from db import get_connection
from productos import obtener_productos 

class PromocionesGUI:
    def __init__(self, root, nombre_negocio="NEXUS", empresa_id=1, usuario_id=1):
        self.root = root
        self.empresa_id = int(empresa_id) # <--- Inyectado para multiempresa
        self.usuario_id = int(usuario_id)
        self.root.title(f"{nombre_negocio.upper()} - Centro de Promociones")
        # Maximizar ventana con múltiples intentos
        self.root.after(500, self.maximizar_ventana)
        self.root.after(1000, self.maximizar_ventana)  # Segundo intento
        
        # Manejar cierre de ventana para evitar problemas
        self.root.protocol("WM_DELETE_WINDOW", self.cerrar_ventana)
        
        self.colors = {
            'bg_main': '#121212', 'bg_panel': '#1e1e1e', 'accent': '#f1c40f',
            'success': '#00db84', 'danger': '#ff4757', 'text_main': '#ffffff',
            'text_dim': '#a0a0a0', 'border': '#333333', 'volumen': '#3498db'
        }

        self.productos_combo = [] 
        self.prod_vol_id = None 
        self.qr_cache = None
        self.imagenes_tabla = [] # Para que las fotos no desaparezcan
        
        self.vcmd = (self.root.register(self.solo_numeros), '%P')
        
        self.configurar_estilo()
        self.crear_widgets(nombre_negocio)
        self.cargar_promociones()

    def solo_numeros(self, P):
        if P == "" or P.isdigit(): return True
        try:
            float(P)
            return True
        except: return False

    def configurar_estilo(self):
        style = ttk.Style()
        style.theme_use('clam')
        self.root.configure(bg=self.colors['bg_main'])
        
        # Configuración completa para Treeview con fondo negro
        style.configure('Custom.Treeview', 
                        background=self.colors['bg_panel'], 
                        foreground=self.colors['text_main'], 
                        fieldbackground=self.colors['bg_panel'], 
                        borderwidth=0, 
                        font=('Segoe UI', 10), 
                        rowheight=35)
        
        # Configuración para encabezados
        style.configure('Custom.Treeview.Heading', 
                        font=('Segoe UI', 10, 'bold'), 
                        background="#252525", 
                        foreground="white", 
                        relief="flat")
        
        # Mapeo para selección y hover
        style.map('Custom.Treeview',
                  background=[('selected', '#00a8ff'), ('focus', '#00a8ff')],
                  foreground=[('selected', 'white'), ('focus', 'white')])
        
        # Configuración mejorada para Notebook con estilo de ventana
        style.configure("TNotebook", background=self.colors['bg_main'], borderwidth=2, highlightthickness=2)
        style.configure("TNotebook.Tab", padding=[12, 10], font=('Segoe UI', 9, 'bold'))
        
        # Mapeo de colores específicos para cada tipo de pestaña
        tabs_config = {
            "CUPÓN": self.colors['accent'],
            "COMBOS": "#e84393",
            "MAYORISTA": "#00db84"
        }

        for name, color in tabs_config.items():
            style.configure(f"{name}.TNotebook.Tab", 
                            background=self.colors['bg_panel'], 
                            foreground="#888", 
                            padding=[12, 10], 
                            font=('Segoe UI', 9, 'bold'),
                            borderwidth=1,
                            relief="raised")
            
            style.map(f"{name}.TNotebook.Tab", 
                      background=[("selected", color), ("active", "#2d2d2d")],
                      foreground=[("selected", "white"), ("active", "white")],
                      bordercolor=[("selected", color), ("active", "#444444")],
                      relief=[("selected", "sunken"), ("active", "raised")])

    def obtener_imagen_desde_ruta(self, ruta):
        if not ruta or not os.path.exists(ruta):
            return None
        try:
            img = Image.open(ruta).convert("RGBA")
            img = img.resize((24, 24), Image.Resampling.LANCZOS)
            photo = ImageTk.PhotoImage(img)
            self.imagenes_tabla.append(photo) 
            return photo
        except:
            return None

    def crear_widgets(self, nombre_negocio):
        main_container = tk.Frame(self.root, bg=self.colors['bg_main'], padx=20, pady=20)
        main_container.pack(fill=tk.BOTH, expand=True)

        header = tk.Frame(main_container, bg=self.colors['bg_main'])
        header.pack(fill=tk.X, pady=(0, 20))
        tk.Label(header, text=f"🎟️ PANEL DE OFERTAS | {nombre_negocio.upper()}", 
                 font=('Segoe UI', 18, 'bold'), bg=self.colors['bg_main'], fg=self.colors['accent']).pack(side=tk.LEFT)

        body = tk.Frame(main_container, bg=self.colors['bg_main'])
        body.pack(fill=tk.BOTH, expand=True)

        # PANELES VERTICALES: IZQUIERDA (TABLA) | DERECHA (FORMULARIOS)
        
        # PANEL IZQUIERDO - TABLA DE PROMOCIONES
        left_panel = tk.Frame(body, bg=self.colors['bg_main'])
        left_panel.pack(side=tk.LEFT, fill=tk.BOTH, expand=True, padx=(0, 10))
        
        # TABLA CON SCROLL VERTICAL
        self.cols = ("ID", "Tipo", "Detalle", "Desc", "Estado")
        
        # Frame contenedor para la tabla y scrollbar vertical
        tabla_container = tk.Frame(left_panel, bg=self.colors['bg_main'])
        tabla_container.pack(fill=tk.BOTH, expand=True)
        
        # Scrollbar vertical
        tree_scroll_y = ttk.Scrollbar(tabla_container, orient="vertical")
        tree_scroll_y.pack(side=tk.RIGHT, fill=tk.Y)
        
        # Tabla con scroll vertical
        self.tabla = ttk.Treeview(tabla_container, columns=self.cols, show='tree headings', 
                                 style="Custom.Treeview", yscrollcommand=tree_scroll_y.set)
        
        # Configurar scrollbar vertical
        tree_scroll_y.config(command=self.tabla.yview)
        
        self.tabla.heading("#0", text="FOTO")
        self.tabla.column("#0", width=50, anchor="center")
        
        # Configurar encabezados con ordenamiento
        self.tabla.heading("ID", text="ID", command=lambda c="ID": self.ordenar_columna(c))
        self.tabla.heading("Tipo", text="TIPO", command=lambda c="Tipo": self.ordenar_columna(c))
        self.tabla.heading("Detalle", text="DETALLE REGLA", command=lambda c="Detalle": self.ordenar_columna(c))
        self.tabla.heading("Desc", text="DESC %", command=lambda c="Desc": self.ordenar_columna(c))
        self.tabla.heading("Estado", text="ESTADO", command=lambda c="Estado": self.ordenar_columna(c))
        
        self.tabla.column("ID", width=40)
        self.tabla.column("Tipo", width=100)
        self.tabla.column("Detalle", width=350)
        self.tabla.column("Desc", width=80)
        self.tabla.column("Estado", width=100)
        self.tabla.pack(fill=tk.BOTH, expand=True)
        
        # Binding para selección de promociones
        self.tabla.bind('<<TreeviewSelect>>', self.on_seleccionar_promocion)
        
        # Variables para ordenamiento
        self.orden_asc = {col: False for col in self.cols}

        # PANEL DERECHO - TABS DE CREACIÓN
        right_panel = tk.Frame(body, bg=self.colors['bg_panel'], width=450, highlightthickness=1, highlightbackground=self.colors['border'])
        right_panel.pack(side=tk.RIGHT, fill=tk.Y)
        right_panel.pack_propagate(False)

        tabs = ttk.Notebook(right_panel)
        self.tab_qr = tk.Frame(tabs, bg=self.colors['bg_panel'], padx=15)
        self.tab_combo = tk.Frame(tabs, bg=self.colors['bg_panel'], padx=15)
        self.tab_vol = tk.Frame(tabs, bg=self.colors['bg_panel'], padx=15)
        tabs.add(self.tab_qr, text=" 🎫 CUPÓN "); tabs.add(self.tab_combo, text=" 🎁 COMBOS "); tabs.add(self.tab_vol, text=" 📦 MAYORISTA ")
        tabs.pack(fill=tk.BOTH, expand=True)

        self.setup_tab_qr()
        self.setup_tab_combo()
        self.setup_tab_volumen()
        
        # PANEL INFERIOR DESLIZANTE PARA EDICIÓN (dentro del panel izquierdo)
        self.panel_edicion = tk.Frame(left_panel, bg=self.colors['bg_panel'], height=300, relief="raised", borderwidth=1)
        self.panel_edicion.pack(side=tk.BOTTOM, fill=tk.X)
        self.panel_edicion.pack_propagate(False)
        
        # Header del panel de edición
        header_edicion = tk.Frame(self.panel_edicion, bg=self.colors['bg_panel'])
        header_edicion.pack(fill=tk.X, padx=15, pady=(10, 5))
        
        self.lbl_titulo_edicion = tk.Label(header_edicion, text="📝 EDITAR PROMOCIÓN", 
                                           font=('Segoe UI', 11, 'bold'), bg=self.colors['bg_panel'], fg=self.colors['accent'])
        self.lbl_titulo_edicion.pack(side=tk.LEFT)
        
        # Botón para cerrar panel
        self.btn_cerrar_edicion = tk.Button(header_edicion, text="✕", bg=self.colors['danger'], fg="white",
                                           font=('Segoe UI', 10, 'bold'), command=self.cerrar_panel_edicion,
                                           width=3, height=1)
        self.btn_cerrar_edicion.pack(side=tk.RIGHT)
        
        # Contenido del panel de edición
        self.contenido_edicion = tk.Frame(self.panel_edicion, bg=self.colors['bg_panel'])
        self.contenido_edicion.pack(fill=tk.BOTH, expand=True, padx=15, pady=5)
        
        # Variables para la edición
        self.promocion_actual = None
        self.tipo_promocion_actual = None
        
        # Inicialmente oculto
        self.panel_edicion.pack_forget()

    def crear_input(self, master, label, validar_num=False):
        tk.Label(master, text=label, font=('Segoe UI', 8, 'bold'), bg=self.colors['bg_panel'], fg=self.colors['text_dim']).pack(anchor="w", pady=(8, 2))
        e = tk.Entry(master, font=('Segoe UI', 10), bg="#000000", fg="white", borderwidth=0, validate='key' if validar_num else 'none', validatecommand=self.vcmd if validar_num else None)
        e.pack(fill=tk.X, ipady=5); return e

    def actualizar_sugerencias(self, entry, listbox):
        q = entry.get().lower()
        if "OK: " in entry.get(): return 
        listbox.delete(0, tk.END)
        if len(q) < 2: return
        # Filtramos productos por la empresa actual
        for p in obtener_productos(self.empresa_id):
            if q in p['nombre'].lower() or q in str(p['codigo']).lower():
                listbox.insert(tk.END, f"{p['id']} | {p['nombre']}")

    def setup_tab_qr(self):
        tk.Label(self.tab_qr, text="NUEVO CUPÓN", font=('Segoe UI', 11, 'bold'), bg=self.colors['bg_panel'], fg=self.colors['success']).pack(pady=15)
        self.ent_codigo = self.crear_input(self.tab_qr, "CÓDIGO")
        self.ent_porcentaje_qr = self.crear_input(self.tab_qr, "DESCUENTO (%)", True)
        tk.Button(self.tab_qr, text="GUARDAR Y GENERAR QR", bg=self.colors['success'], command=self.guardar_cupon, font=('Segoe UI', 10, 'bold')).pack(fill=tk.X, pady=15)
        self.qr_label = tk.Label(self.tab_qr, bg="#000000", width=180, height=180); self.qr_label.pack()

    def setup_tab_combo(self):
        tk.Label(self.tab_combo, text="COMBO DE PRODUCTOS", font=('Segoe UI', 11, 'bold'), bg=self.colors['bg_panel'], fg=self.colors['accent']).pack(pady=15)
        self.ent_nombre_combo = self.crear_input(self.tab_combo, "NOMBRE COMBO")
        tk.Label(self.tab_combo, text="BUSCAR PRODUCTO", font=('Segoe UI', 8), bg=self.colors['bg_panel'], fg=self.colors['text_dim']).pack(anchor="w")
        self.search_combo = tk.Entry(self.tab_combo, bg="#000000", fg="white", borderwidth=0); self.search_combo.pack(fill=tk.X, ipady=5); self.search_combo.bind("<KeyRelease>", lambda e: self.actualizar_sugerencias(self.search_combo, self.list_sug_combo))
        self.list_sug_combo = tk.Listbox(self.tab_combo, bg="#000000", fg="white", height=4); self.list_sug_combo.pack(fill=tk.X); self.list_sug_combo.bind("<Double-Button-1>", self.seleccionar_para_combo)
        self.list_actual_combo = tk.Listbox(self.tab_combo, bg="#1a1a1a", fg=self.colors['success'], height=4); self.list_actual_combo.pack(fill=tk.X, pady=5)
        self.ent_porcentaje_combo = self.crear_input(self.tab_combo, "DESCUENTO (%)", True)
        tk.Button(self.tab_combo, text="CREAR COMBO", bg=self.colors['accent'], command=self.guardar_combo, font=('Segoe UI', 10, 'bold')).pack(fill=tk.X, pady=15)

    def setup_tab_volumen(self):
        tk.Label(self.tab_vol, text="PRECIO POR CANTIDAD", font=('Segoe UI', 11, 'bold'), bg=self.colors['bg_panel'], fg=self.colors['volumen']).pack(pady=15)
        tk.Label(self.tab_vol, text="BUSCAR PRODUCTO", font=('Segoe UI', 8), bg=self.colors['bg_panel'], fg=self.colors['text_dim']).pack(anchor="w")
        self.search_vol = tk.Entry(self.tab_vol, bg="#000000", fg="white", borderwidth=0); self.search_vol.pack(fill=tk.X, ipady=5); self.search_vol.bind("<KeyRelease>", lambda e: self.actualizar_sugerencias(self.search_vol, self.list_sug_vol))
        self.list_sug_vol = tk.Listbox(self.tab_vol, bg="#000000", fg="white", height=4); self.list_sug_vol.pack(fill=tk.X); self.list_sug_vol.bind("<Double-Button-1>", self.seleccionar_para_volumen)
        self.ent_cant_min = self.crear_input(self.tab_vol, "CANTIDAD MÍNIMA", True)
        self.ent_porcentaje_vol = self.crear_input(self.tab_vol, "DESCUENTO (%)", True)
        tk.Button(self.tab_vol, text="CREAR REGLA MAYORISTA", bg=self.colors['volumen'], fg="white", command=self.guardar_volumen, font=('Segoe UI', 10, 'bold')).pack(fill=tk.X, pady=20)

    def seleccionar_para_combo(self, event=None):
        idx = self.list_sug_combo.curselection()
        if idx:
            sel = self.list_sug_combo.get(idx); pid = sel.split(" | ")[0]
            if pid not in self.productos_combo:
                self.productos_combo.append(pid); self.list_actual_combo.insert(tk.END, sel)
                self.search_combo.delete(0, tk.END); self.list_sug_combo.delete(0, tk.END); self.search_combo.focus()

    def seleccionar_para_volumen(self, event=None):
        idx = self.list_sug_vol.curselection()
        if idx:
            sel = self.list_sug_vol.get(idx); self.prod_vol_id = sel.split(" | ")[0]; nombre = sel.split(" | ")[1]
            self.search_vol.delete(0, tk.END); self.search_vol.insert(0, f"OK: {nombre}")
            self.search_vol.config(fg=self.colors['success']); self.list_sug_vol.delete(0, tk.END)

    def guardar_volumen(self):
        c, p = self.ent_cant_min.get().strip(), self.ent_porcentaje_vol.get().strip()
        if not self.prod_vol_id or not c or not p: return
        try:
            conn = get_connection()
            with conn.cursor() as cursor:
                cursor.execute("""
                    INSERT INTO promociones_volumen (producto_id, empresa_id, cantidad_minima, descuento_porcentaje, activo) 
                    VALUES (%s, %s, %s, %s, 1)
                """, (int(self.prod_vol_id), self.empresa_id, int(c), float(p)))
            conn.commit(); conn.close(); messagebox.showinfo("Éxito", "Regla guardada."); self.cargar_promociones()
        except Exception as e: messagebox.showerror("Error", str(e))

    def guardar_cupon(self):
        c, p = self.ent_codigo.get().upper(), self.ent_porcentaje_qr.get()
        if not c or not p: return
        try:
            conn = get_connection()
            with conn.cursor() as cursor:
                cursor.execute("""
                    INSERT INTO cupones (codigo_qr, empresa_id, descuento_porcentaje, activo) 
                    VALUES (%s, %s, %s, 1)
                """, (c, self.empresa_id, float(p)))
            conn.commit(); conn.close(); self.generar_qr_visual(c); self.cargar_promociones()
        except Exception as e: messagebox.showerror("Error", str(e))

    def guardar_combo(self):
        n, p = self.ent_nombre_combo.get(), self.ent_porcentaje_combo.get()
        if not n or not p or len(self.productos_combo) < 2: return
        try:
            conn = get_connection()
            with conn.cursor() as cursor:
                cursor.execute("""
                    INSERT INTO promociones_combos (nombre_promo, empresa_id, productos_ids, descuento_porcentaje, activo) 
                    VALUES (%s, %s, %s, %s, 1)
                """, (n, self.empresa_id, ",".join(self.productos_combo), float(p)))
            conn.commit(); conn.close(); self.productos_combo = []; self.list_actual_combo.delete(0, tk.END); self.cargar_promociones()
        except Exception as e: messagebox.showerror("Error", str(e))

    def generar_qr_visual(self, texto):
        qr = qrcode.QRCode(box_size=5); qr.add_data(texto); qr.make()
        img = ImageTk.PhotoImage(qr.make_image().resize((180, 180)))
        self.qr_cache = img; self.qr_label.config(image=img)

    def cargar_promociones(self):
        for item in self.tabla.get_children(): self.tabla.delete(item)
        self.imagenes_tabla = [] 
        conn = get_connection()
        try:
            with conn.cursor() as cursor:
                # Obtener categorías para agrupar
                cursor.execute("SELECT id, nombre FROM categorias WHERE empresa_id=%s ORDER BY nombre", (self.empresa_id,))
                categorias_dict = {cat['id']: cat['nombre'] for cat in cursor.fetchall()}
                
                # Obtener datos agrupados por tipo y categoría
                promociones = {
                    'CUPÓN': {},
                    'COMBO': {},
                    'MAYORISTA': {}
                }
                
                # Cupones de esta empresa (agrupar por tipo, sin categoría específica)
                cursor.execute("SELECT id, codigo_qr, descuento_porcentaje FROM cupones WHERE activo=1 AND empresa_id=%s", (self.empresa_id,))
                cupones = cursor.fetchall()
                for r in cupones:
                    categoria = "General"  # Todos los cupones en categoría General
                    if categoria not in promociones['CUPÓN']:
                        promociones['CUPÓN'][categoria] = []
                    promociones['CUPÓN'][categoria].append({
                        'id': r['id'],
                        'detalle': r['codigo_qr'],
                        'desc': f"{r['descuento_porcentaje']}%",
                        'estado': 'ACTIVO',
                        'imagen': None
                    })
                
                # Combos de esta empresa (agrupar por tipo, sin categoría específica)
                cursor.execute("SELECT id, nombre_promo, descuento_porcentaje FROM promociones_combos WHERE activo=1 AND empresa_id=%s", (self.empresa_id,))
                combos = cursor.fetchall()
                for r in combos:
                    categoria = "General"  # Todos los combos en categoría General
                    if categoria not in promociones['COMBO']:
                        promociones['COMBO'][categoria] = []
                    promociones['COMBO'][categoria].append({
                        'id': r['id'],
                        'detalle': r['nombre_promo'],
                        'desc': f"{r['descuento_porcentaje']}%",
                        'estado': 'ACTIVO',
                        'imagen': None
                    })
                
                # Mayorista de esta empresa (agrupar por categoría del producto)
                cursor.execute("""
                    SELECT v.id, p.nombre, p.imagen, p.categoria_id, v.cantidad_minima, v.descuento_porcentaje,
                           COALESCE(cat.nombre, 'Sin categoría') as categoria_nombre
                    FROM promociones_volumen v 
                    JOIN productos p ON v.producto_id = p.id
                    LEFT JOIN categorias cat ON p.categoria_id = cat.id
                    WHERE v.activo = 1 AND v.empresa_id = %s
                """, (self.empresa_id,))
                mayorista = cursor.fetchall()
                for r in mayorista:
                    categoria = r['categoria_nombre'] or 'Sin categoría'
                    if categoria not in promociones['MAYORISTA']:
                        promociones['MAYORISTA'][categoria] = []
                    promociones['MAYORISTA'][categoria].append({
                        'id': r['id'],
                        'detalle': f"{r['nombre']} (x{r['cantidad_minima']})",
                        'desc': f"{r['descuento_porcentaje']}%",
                        'estado': 'ACTIVO',
                        'imagen': r['imagen']
                    })
                
                # Insertar agrupados por tipo y luego por categoría
                for tipo, categorias in promociones.items():
                    if categorias:
                        # Crear nodo padre para el tipo
                        total_promos = sum(len(lista) for lista in categorias.values())
                        icono_tipo = "🎟️" if tipo == "CUPÓN" else "📦" if tipo == "COMBO" else "📊"
                        tipo_item = self.tabla.insert("", tk.END, text=f"{icono_tipo} {tipo} ({total_promos})", 
                                                     values=("", tipo, "", "", ""), open=True)
                        
                        # Insertar categorías dentro de cada tipo
                        for categoria, lista in categorias.items():
                            if lista:
                                # Crear nodo para la categoría
                                cat_item = self.tabla.insert(tipo_item, tk.END, 
                                                           text=f"📁 {categoria} ({len(lista)})", 
                                                           values=("", "", "", "", ""), open=True)
                                
                                # Insertar promociones de esta categoría
                                for promo in lista:
                                    foto = None
                                    try:
                                        foto = self.obtener_imagen_desde_ruta(promo['imagen']) if promo['imagen'] else None
                                    except:
                                        foto = None  # Si hay error al cargar imagen, continuar sin foto
                                    self.tabla.insert(cat_item, tk.END, text="", image=foto if foto else "", 
                                                   values=(promo['id'], tipo, promo['detalle'], promo['desc'], promo['estado']))
                
        except Exception as e:
            print(f"Error cargando promociones: {e}")
            pass
        finally: conn.close()

    def ordenar_columna(self, col):
        """Ordena promociones por la columna seleccionada"""
        self.orden_asc[col] = not self.orden_asc[col]
        
        # Obtener todos los items de la tabla
        items = [(self.tabla.set(item, col), item) for item in self.tabla.get_children('')]
        
        # Ordenar según el tipo de columna
        if col == "ID":
            # Ordenar como número
            try:
                items.sort(key=lambda x: int(x[0]), reverse=not self.orden_asc[col])
            except:
                items.sort(key=lambda x: 0, reverse=not self.orden_asc[col])
        elif col == "Desc":
            # Ordenar como número (quitar %)
            try:
                items.sort(key=lambda x: float(str(x[0]).replace('%', '').strip()), reverse=not self.orden_asc[col])
            except:
                items.sort(key=lambda x: 0, reverse=not self.orden_asc[col])
        else:
            # Ordenar como texto
            items.sort(key=lambda x: str(x[0]).lower(), reverse=not self.orden_asc[col])
        
        # Reordenar en la tabla
        for index, (valor, item) in enumerate(items):
            self.tabla.move(item, '', index)

    def on_seleccionar_promocion(self, event):
        """Maneja la selección de una promoción para edición"""
        sel = self.tabla.selection()
        if not sel: 
            return
        
        # Obtener datos de la selección
        item = sel[0]
        promo_id = self.tabla.set(item, "ID")
        promo_tipo = self.tabla.set(item, "Tipo")
        
        # Verificar que no sea una categoría (nodos padres tienen valores vacíos)
        if not promo_id or promo_id == "":
            return
            
        print(f"Seleccionada promoción {promo_tipo} ID: {promo_id}")
        
        # Abrir panel de edición según el tipo de promoción
        self.abrir_panel_edicion(promo_id, promo_tipo)

    def abrir_panel_edicion(self, promo_id, promo_tipo):
        """Abre el panel inferior deslizante para editar la promoción seleccionada"""
        # Guardar referencia a la promoción actual
        self.promocion_actual = promo_id
        self.tipo_promocion_actual = promo_tipo
        
        # Actualizar título del panel
        self.lbl_titulo_edicion.config(text=f"📝 EDITAR {promo_tipo}")
        
        # Limpiar contenido anterior
        for widget in self.contenido_edicion.winfo_children():
            widget.destroy()
        
        # Crear formulario según el tipo
        if promo_tipo == "CUPÓN":
            self.crear_formulario_cupon_panel(promo_id)
        elif promo_tipo == "COMBO":
            self.crear_formulario_combo_panel(promo_id)
        elif promo_tipo == "MAYORISTA":
            self.crear_formulario_mayorista_panel(promo_id)
        
        # Mostrar panel con animación suave
        self.mostrar_panel_edicion()

    def mostrar_panel_edicion(self):
        """Muestra el panel de edición con animación suave"""
        print("DEBUG: Mostrando panel de edición")
        
        try:
            # Primero quitar el panel si ya está packed
            self.panel_edicion.pack_forget()
            
            # Forzar el panel a ocupar todo el ancho disponible
            self.panel_edicion.pack(side=tk.BOTTOM, fill=tk.X, before=None)
            print("DEBUG: Panel packed con fill=tk.X")
            
            # Forzar actualización para que se vea inmediatamente
            self.panel_edicion.update_idletasks()
            self.panel_edicion.update()
            print("DEBUG: Panel actualizado")
            
            print(f"DEBUG: Panel winfo_manager: {self.panel_edicion.winfo_manager()}")
            print(f"DEBUG: Panel winfo_height: {self.panel_edicion.winfo_height()}")
            print(f"DEBUG: Panel winfo_width: {self.panel_edicion.winfo_width()}")
            
            # Si el ancho sigue siendo 1, intentar solución alternativa
            if self.panel_edicion.winfo_width() <= 1:
                print("DEBUG: Ancho sigue siendo 1, intentando solución alternativa")
                # Forzar un ancho mínimo
                self.panel_edicion.configure(width=800)
                self.panel_edicion.update()
                print(f"DEBUG: Panel con ancho forzado: {self.panel_edicion.winfo_width()}")
            
        except Exception as e:
            print(f"DEBUG: Error mostrando panel: {e}")
            import traceback
            traceback.print_exc()

    def volver_al_dashboard(self):
        """Volver al dashboard principal"""
        try:
            # Cerrar panel de edición si está abierto
            if hasattr(self, 'panel_edicion_visible') and self.panel_edicion_visible:
                self.cerrar_panel_edicion()
            
            # IMPORTANTE: Salir del proceso para que main.py ejecute el finally
            self.root.quit()  # Cierra el mainloop
            self.root.destroy()  # Destruye la ventana
        except Exception as e:
            print(f"Error al volver al dashboard: {e}")
            # En caso de error, igual salir del proceso
            try:
                self.root.quit()
                self.root.destroy()
            except:
                pass

    def cerrar_panel_edicion(self):
        """Cierra el panel de edición"""
        self.panel_edicion.pack_forget()
        self.promocion_actual = None
        self.tipo_promocion_actual = None

    def crear_formulario_cupon(self, dialog, cupon_id):
        """Crea formulario para editar cupón"""
        # Cargar datos del cupón
        conn = get_connection()
        cupon_data = None
        try:
            with conn.cursor() as cursor:
                cursor.execute("SELECT * FROM cupones WHERE id=%s AND empresa_id=%s", (cupon_id, self.empresa_id))
                cupon_data = cursor.fetchone()
        except Exception as e:
            messagebox.showerror("Error", f"No se pudo cargar el cupón: {e}")
            dialog.destroy()
            return
        finally:
            conn.close()
        
        if not cupon_data:
            messagebox.showerror("Error", "Cupón no encontrado")
            dialog.destroy()
            return
        
        # Título
        tk.Label(dialog, text="EDITAR CUPÓN QR", font=('Segoe UI', 12, 'bold'), 
                bg=self.colors['bg_panel'], fg=self.colors['accent']).pack(pady=15)
        
        # Campos del formulario
        frame_campos = tk.Frame(dialog, bg=self.colors['bg_panel'])
        frame_campos.pack(fill=tk.BOTH, expand=True, padx=20, pady=10)
        
        # Código QR
        tk.Label(frame_campos, text="Código QR:", bg=self.colors['bg_panel'], fg="white").pack(anchor="w", pady=(5, 2))
        entry_codigo = tk.Entry(frame_campos, font=('Segoe UI', 10), bg="#000000", fg="white")
        entry_codigo.pack(fill=tk.X, ipady=5)
        entry_codigo.insert(0, cupon_data['codigo_qr'])
        
        # Descuento
        tk.Label(frame_campos, text="Descuento (%):", bg=self.colors['bg_panel'], fg="white").pack(anchor="w", pady=(10, 2))
        entry_descuento = tk.Entry(frame_campos, font=('Segoe UI', 10), bg="#000000", fg="white")
        entry_descuento.pack(fill=tk.X, ipady=5)
        entry_descuento.insert(0, str(cupon_data['descuento_porcentaje']))
        
        # Estado
        var_activo = tk.BooleanVar(value=cupon_data.get('activo', 1))
        tk.Checkbutton(frame_campos, text="Activo", variable=var_activo, 
                      bg=self.colors['bg_panel'], fg="white", selectcolor="#252525").pack(anchor="w", pady=10)
        
        # Botones
        frame_botones = tk.Frame(dialog, bg=self.colors['bg_panel'])
        frame_botones.pack(fill=tk.X, padx=20, pady=20)
        
        def guardar():
            try:
                descuento = float(entry_descuento.get())
                codigo = entry_codigo.get().strip()
                
                if not codigo:
                    messagebox.showwarning("Aviso", "El código QR no puede estar vacío")
                    return
                
                conn = get_connection()
                try:
                    with conn.cursor() as cursor:
                        cursor.execute("""
                            UPDATE cupones SET codigo_qr=%s, descuento_porcentaje=%s, activo=%s 
                            WHERE id=%s AND empresa_id=%s
                        """, (codigo, descuento, var_activo.get(), cupon_id, self.empresa_id))
                    conn.commit()
                    messagebox.showinfo("Éxito", "Cupón actualizado correctamente")
                    self.cargar_promociones()  # Recargar la tabla
                    dialog.destroy()
                except Exception as e:
                    messagebox.showerror("Error", f"No se pudo guardar: {e}")
                finally:
                    conn.close()
            except ValueError:
                messagebox.showerror("Error", "El descuento debe ser un número válido")
        
        def cancelar():
            dialog.destroy()
        
        tk.Button(frame_botones, text="GUARDAR", bg=self.colors['success'], fg="white", 
                 command=guardar, pady=8).pack(side=tk.LEFT, fill=tk.X, expand=True, padx=(0, 5))
        tk.Button(frame_botones, text="CANCELAR", bg=self.colors['danger'], fg="white", 
                 command=cancelar, pady=8).pack(side=tk.LEFT, fill=tk.X, expand=True, padx=(5, 0))

    def crear_formulario_combo(self, dialog, combo_id):
        """Crea formulario para editar combo"""
        # Cargar datos del combo
        conn = get_connection()
        combo_data = None
        try:
            with conn.cursor() as cursor:
                cursor.execute("SELECT * FROM promociones_combos WHERE id=%s AND empresa_id=%s", (combo_id, self.empresa_id))
                combo_data = cursor.fetchone()
        except Exception as e:
            messagebox.showerror("Error", f"No se pudo cargar el combo: {e}")
            dialog.destroy()
            return
        finally:
            conn.close()
        
        if not combo_data:
            messagebox.showerror("Error", "Combo no encontrado")
            dialog.destroy()
            return
        
        # Título
        tk.Label(dialog, text="EDITAR COMBO", font=('Segoe UI', 12, 'bold'), 
                bg=self.colors['bg_panel'], fg=self.colors['accent']).pack(pady=15)
        
        # Campos del formulario
        frame_campos = tk.Frame(dialog, bg=self.colors['bg_panel'])
        frame_campos.pack(fill=tk.BOTH, expand=True, padx=20, pady=10)
        
        # Nombre del combo
        tk.Label(frame_campos, text="Nombre del Combo:", bg=self.colors['bg_panel'], fg="white").pack(anchor="w", pady=(5, 2))
        entry_nombre = tk.Entry(frame_campos, font=('Segoe UI', 10), bg="#000000", fg="white")
        entry_nombre.pack(fill=tk.X, ipady=5)
        entry_nombre.insert(0, combo_data['nombre_promo'])
        
        # Descuento
        tk.Label(frame_campos, text="Descuento (%):", bg=self.colors['bg_panel'], fg="white").pack(anchor="w", pady=(10, 2))
        entry_descuento = tk.Entry(frame_campos, font=('Segoe UI', 10), bg="#000000", fg="white")
        entry_descuento.pack(fill=tk.X, ipady=5)
        entry_descuento.insert(0, str(combo_data['descuento_porcentaje']))
        
        # Estado
        var_activo = tk.BooleanVar(value=combo_data.get('activo', 1))
        tk.Checkbutton(frame_campos, text="Activo", variable=var_activo, 
                      bg=self.colors['bg_panel'], fg="white", selectcolor="#252525").pack(anchor="w", pady=10)
        
        # Botones
        frame_botones = tk.Frame(dialog, bg=self.colors['bg_panel'])
        frame_botones.pack(fill=tk.X, padx=20, pady=20)
        
        def guardar():
            try:
                descuento = float(entry_descuento.get())
                nombre = entry_nombre.get().strip()
                
                if not nombre:
                    messagebox.showwarning("Aviso", "El nombre del combo no puede estar vacío")
                    return
                
                conn = get_connection()
                try:
                    with conn.cursor() as cursor:
                        cursor.execute("""
                            UPDATE promociones_combos SET nombre_promo=%s, descuento_porcentaje=%s, activo=%s 
                            WHERE id=%s AND empresa_id=%s
                        """, (nombre, descuento, var_activo.get(), combo_id, self.empresa_id))
                    conn.commit()
                    messagebox.showinfo("Éxito", "Combo actualizado correctamente")
                    self.cargar_promociones()  # Recargar la tabla
                    dialog.destroy()
                except Exception as e:
                    messagebox.showerror("Error", f"No se pudo guardar: {e}")
                finally:
                    conn.close()
            except ValueError:
                messagebox.showerror("Error", "El descuento debe ser un número válido")
        
        def cancelar():
            dialog.destroy()
        
        tk.Button(frame_botones, text="GUARDAR", bg=self.colors['success'], fg="white", 
                 command=guardar, pady=8).pack(side=tk.LEFT, fill=tk.X, expand=True, padx=(0, 5))
        tk.Button(frame_botones, text="CANCELAR", bg=self.colors['danger'], fg="white", 
                 command=cancelar, pady=8).pack(side=tk.LEFT, fill=tk.X, expand=True, padx=(5, 0))

    def crear_formulario_cupon_panel(self, cupon_id):
        """Crea formulario para editar cupón en el panel inferior"""
        # Cargar datos del cupón
        conn = get_connection()
        cupon_data = None
        try:
            with conn.cursor() as cursor:
                cursor.execute("SELECT * FROM cupones WHERE id=%s AND empresa_id=%s", (cupon_id, self.empresa_id))
                cupon_data = cursor.fetchone()
        except Exception as e:
            messagebox.showerror("Error", f"No se pudo cargar el cupón: {e}")
            return
        finally:
            conn.close()
        
        if not cupon_data:
            messagebox.showerror("Error", "Cupón no encontrado")
            return
        
        # Frame para campos en dos columnas
        frame_campos = tk.Frame(self.contenido_edicion, bg=self.colors['bg_panel'])
        frame_campos.pack(fill=tk.BOTH, expand=True)
        
        # Columna izquierda
        frame_izq = tk.Frame(frame_campos, bg=self.colors['bg_panel'])
        frame_izq.pack(side=tk.LEFT, fill=tk.BOTH, expand=True, padx=(0, 10))
        
        # Columna derecha
        frame_der = tk.Frame(frame_campos, bg=self.colors['bg_panel'])
        frame_der.pack(side=tk.LEFT, fill=tk.BOTH, expand=True)
        
        # Código QR
        tk.Label(frame_izq, text="Código QR:", bg=self.colors['bg_panel'], fg="white").pack(anchor="w")
        self.entry_codigo_panel = tk.Entry(frame_izq, font=('Segoe UI', 10), bg="#000000", fg="white")
        self.entry_codigo_panel.pack(fill=tk.X, ipady=3, pady=(2, 10))
        self.entry_codigo_panel.insert(0, cupon_data['codigo_qr'])
        
        # Descuento
        tk.Label(frame_izq, text="Descuento (%):", bg=self.colors['bg_panel'], fg="white").pack(anchor="w")
        self.entry_descuento_panel = tk.Entry(frame_izq, font=('Segoe UI', 10), bg="#000000", fg="white")
        self.entry_descuento_panel.pack(fill=tk.X, ipady=3, pady=(2, 10))
        self.entry_descuento_panel.insert(0, str(cupon_data['descuento_porcentaje']))
        
        # Estado
        self.var_activo_panel = tk.BooleanVar(value=cupon_data.get('activo', 1))
        tk.Checkbutton(frame_izq, text="Activo", variable=self.var_activo_panel, 
                      bg=self.colors['bg_panel'], fg="white", selectcolor="#252525").pack(anchor="w")
        
        # Botones
        frame_botones = tk.Frame(frame_der, bg=self.colors['bg_panel'])
        frame_botones.pack(fill=tk.X, pady=(20, 0))
        
        tk.Button(frame_botones, text="GUARDAR", bg=self.colors['success'], fg="white", 
                 command=lambda: self.guardar_cupon_panel(cupon_id), pady=5).pack(fill=tk.X, pady=(0, 5))
        tk.Button(frame_botones, text="CANCELAR", bg=self.colors['danger'], fg="white", 
                 command=self.cerrar_panel_edicion, pady=5).pack(fill=tk.X)

    def crear_formulario_combo_panel(self, combo_id):
        """Crea formulario para editar combo en el panel inferior"""
        # Cargar datos del combo
        conn = get_connection()
        combo_data = None
        try:
            with conn.cursor() as cursor:
                cursor.execute("SELECT * FROM promociones_combos WHERE id=%s AND empresa_id=%s", (combo_id, self.empresa_id))
                combo_data = cursor.fetchone()
        except Exception as e:
            messagebox.showerror("Error", f"No se pudo cargar el combo: {e}")
            return
        finally:
            conn.close()
        
        if not combo_data:
            messagebox.showerror("Error", "Combo no encontrado")
            return
        
        # Frame para campos en dos columnas
        frame_campos = tk.Frame(self.contenido_edicion, bg=self.colors['bg_panel'])
        frame_campos.pack(fill=tk.BOTH, expand=True)
        
        # Columna izquierda
        frame_izq = tk.Frame(frame_campos, bg=self.colors['bg_panel'])
        frame_izq.pack(side=tk.LEFT, fill=tk.BOTH, expand=True, padx=(0, 10))
        
        # Columna derecha
        frame_der = tk.Frame(frame_campos, bg=self.colors['bg_panel'])
        frame_der.pack(side=tk.LEFT, fill=tk.BOTH, expand=True)
        
        # Nombre del combo
        tk.Label(frame_izq, text="Nombre Combo:", bg=self.colors['bg_panel'], fg="white").pack(anchor="w")
        self.entry_nombre_combo_panel = tk.Entry(frame_izq, font=('Segoe UI', 10), bg="#000000", fg="white")
        self.entry_nombre_combo_panel.pack(fill=tk.X, ipady=3, pady=(2, 10))
        self.entry_nombre_combo_panel.insert(0, combo_data['nombre_promo'])
        
        # Descuento
        tk.Label(frame_izq, text="Descuento (%):", bg=self.colors['bg_panel'], fg="white").pack(anchor="w")
        self.entry_descuento_combo_panel = tk.Entry(frame_izq, font=('Segoe UI', 10), bg="#000000", fg="white")
        self.entry_descuento_combo_panel.pack(fill=tk.X, ipady=3, pady=(2, 10))
        self.entry_descuento_combo_panel.insert(0, str(combo_data['descuento_porcentaje']))
        
        # Estado
        self.var_activo_combo_panel = tk.BooleanVar(value=combo_data.get('activo', 1))
        tk.Checkbutton(frame_izq, text="Activo", variable=self.var_activo_combo_panel, 
                      bg=self.colors['bg_panel'], fg="white", selectcolor="#252525").pack(anchor="w")
        
        # Botones
        frame_botones = tk.Frame(frame_der, bg=self.colors['bg_panel'])
        frame_botones.pack(fill=tk.X, pady=(20, 0))
        
        tk.Button(frame_botones, text="GUARDAR", bg=self.colors['success'], fg="white", 
                 command=lambda: self.guardar_combo_panel(combo_id), pady=5).pack(fill=tk.X, pady=(0, 5))
        tk.Button(frame_botones, text="CANCELAR", bg=self.colors['danger'], fg="white", 
                 command=self.cerrar_panel_edicion, pady=5).pack(fill=tk.X)

    def crear_formulario_mayorista_panel(self, mayorista_id):
        """Crea formulario para editar promoción mayorista en el panel inferior"""
        # Cargar datos del mayorista
        conn = get_connection()
        mayorista_data = None
        try:
            with conn.cursor() as cursor:
                cursor.execute("""
                    SELECT v.*, p.nombre as producto_nombre 
                    FROM promociones_volumen v 
                    JOIN productos p ON v.producto_id = p.id 
                    WHERE v.id=%s AND v.empresa_id=%s
                """, (mayorista_id, self.empresa_id))
                mayorista_data = cursor.fetchone()
        except Exception as e:
            messagebox.showerror("Error", f"No se pudo cargar la promoción mayorista: {e}")
            return
        finally:
            conn.close()
        
        if not mayorista_data:
            messagebox.showerror("Error", "Promoción mayorista no encontrada")
            return
        
        # Frame para campos en dos columnas
        frame_campos = tk.Frame(self.contenido_edicion, bg=self.colors['bg_panel'])
        frame_campos.pack(fill=tk.BOTH, expand=True)
        
        # Columna izquierda
        frame_izq = tk.Frame(frame_campos, bg=self.colors['bg_panel'])
        frame_izq.pack(side=tk.LEFT, fill=tk.BOTH, expand=True, padx=(0, 10))
        
        # Columna derecha
        frame_der = tk.Frame(frame_campos, bg=self.colors['bg_panel'])
        frame_der.pack(side=tk.LEFT, fill=tk.BOTH, expand=True)
        
        # Producto (solo lectura)
        tk.Label(frame_izq, text="Producto:", bg=self.colors['bg_panel'], fg="white").pack(anchor="w")
        entry_producto = tk.Entry(frame_izq, font=('Segoe UI', 10), bg="#404040", fg="white", state='readonly')
        entry_producto.pack(fill=tk.X, ipady=3, pady=(2, 10))
        entry_producto.insert(0, mayorista_data['producto_nombre'])
        
        # Cantidad mínima
        tk.Label(frame_izq, text="Cantidad Mínima:", bg=self.colors['bg_panel'], fg="white").pack(anchor="w")
        self.entry_cantidad_mayorista_panel = tk.Entry(frame_izq, font=('Segoe UI', 10), bg="#000000", fg="white")
        self.entry_cantidad_mayorista_panel.pack(fill=tk.X, ipady=3, pady=(2, 10))
        self.entry_cantidad_mayorista_panel.insert(0, str(mayorista_data['cantidad_minima']))
        
        # Descuento
        tk.Label(frame_izq, text="Descuento (%):", bg=self.colors['bg_panel'], fg="white").pack(anchor="w")
        self.entry_descuento_mayorista_panel = tk.Entry(frame_izq, font=('Segoe UI', 10), bg="#000000", fg="white")
        self.entry_descuento_mayorista_panel.pack(fill=tk.X, ipady=3, pady=(2, 10))
        self.entry_descuento_mayorista_panel.insert(0, str(mayorista_data['descuento_porcentaje']))
        
        # Estado
        self.var_activo_mayorista_panel = tk.BooleanVar(value=mayorista_data.get('activo', 1))
        tk.Checkbutton(frame_izq, text="Activo", variable=self.var_activo_mayorista_panel, 
                      bg=self.colors['bg_panel'], fg="white", selectcolor="#252525").pack(anchor="w")
        
        # Botones
        frame_botones = tk.Frame(frame_der, bg=self.colors['bg_panel'])
        frame_botones.pack(fill=tk.X, pady=(20, 0))
        
        tk.Button(frame_botones, text="GUARDAR", bg=self.colors['success'], fg="white", 
                 command=lambda: self.guardar_mayorista_panel(mayorista_id), pady=5).pack(fill=tk.X, pady=(0, 5))
        tk.Button(frame_botones, text="CANCELAR", bg=self.colors['danger'], fg="white", 
                 command=self.cerrar_panel_edicion, pady=5).pack(fill=tk.X)

    def guardar_cupon_panel(self, cupon_id):
        """Guarda los cambios del cupón desde el panel"""
        try:
            descuento = float(self.entry_descuento_panel.get())
            codigo = self.entry_codigo_panel.get().strip()
            
            if not codigo:
                messagebox.showwarning("Aviso", "El código QR no puede estar vacío")
                return
            
            conn = get_connection()
            try:
                with conn.cursor() as cursor:
                    cursor.execute("""
                        UPDATE cupones SET codigo_qr=%s, descuento_porcentaje=%s, activo=%s 
                        WHERE id=%s AND empresa_id=%s
                    """, (codigo, descuento, self.var_activo_panel.get(), cupon_id, self.empresa_id))
                conn.commit()
                messagebox.showinfo("Éxito", "Cupón actualizado correctamente")
                self.cargar_promociones()  # Recargar la tabla
                self.cerrar_panel_edicion()
            except Exception as e:
                messagebox.showerror("Error", f"No se pudo guardar: {e}")
            finally:
                conn.close()
        except ValueError:
            messagebox.showerror("Error", "El descuento debe ser un número válido")

    def guardar_combo_panel(self, combo_id):
        """Guarda los cambios del combo desde el panel"""
        try:
            descuento = float(self.entry_descuento_combo_panel.get())
            nombre = self.entry_nombre_combo_panel.get().strip()
            
            if not nombre:
                messagebox.showwarning("Aviso", "El nombre del combo no puede estar vacío")
                return
            
            conn = get_connection()
            try:
                with conn.cursor() as cursor:
                    cursor.execute("""
                        UPDATE promociones_combos SET nombre_promo=%s, descuento_porcentaje=%s, activo=%s 
                        WHERE id=%s AND empresa_id=%s
                    """, (nombre, descuento, self.var_activo_combo_panel.get(), combo_id, self.empresa_id))
                conn.commit()
                messagebox.showinfo("Éxito", "Combo actualizado correctamente")
                self.cargar_promociones()  # Recargar la tabla
                self.cerrar_panel_edicion()
            except Exception as e:
                messagebox.showerror("Error", f"No se pudo guardar: {e}")
            finally:
                conn.close()
        except ValueError:
            messagebox.showerror("Error", "El descuento debe ser un número válido")

    def guardar_mayorista_panel(self, mayorista_id):
        """Guarda los cambios de la promoción mayorista desde el panel"""
        try:
            cantidad = int(self.entry_cantidad_mayorista_panel.get())
            descuento = float(self.entry_descuento_mayorista_panel.get())
            
            if cantidad <= 0:
                messagebox.showwarning("Aviso", "La cantidad mínima debe ser mayor a 0")
                return
            
            conn = get_connection()
            try:
                with conn.cursor() as cursor:
                    cursor.execute("""
                        UPDATE promociones_volumen SET cantidad_minima=%s, descuento_porcentaje=%s, activo=%s 
                        WHERE id=%s AND empresa_id=%s
                    """, (cantidad, descuento, self.var_activo_mayorista_panel.get(), mayorista_id, self.empresa_id))
                conn.commit()
                messagebox.showinfo("Éxito", "Promoción mayorista actualizada correctamente")
                self.cargar_promociones()  # Recargar la tabla
                self.cerrar_panel_edicion()
            except Exception as e:
                messagebox.showerror("Error", f"No se pudo guardar: {e}")
            finally:
                conn.close()
        except ValueError:
            messagebox.showerror("Error", "La cantidad y el descuento deben ser números válidos")

    def crear_formulario_mayorista(self, dialog, mayorista_id):
        """Crea formulario para editar promoción mayorista (método antiguo - obsoleto)"""
        # Este método ya no se usa, pero se mantiene por compatibilidad
        pass

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
    
    def cerrar_ventana(self):
        """Manejar el cierre de ventana de forma segura"""
        try:
            # Cerrar panel de edición si está abierto
            if hasattr(self, 'panel_edicion_visible') and self.panel_edicion_visible:
                self.cerrar_panel_edicion()
            
            self.root.quit()
            self.root.destroy()
        except:
            pass

def ejecutar_promociones(negocio, emp_id, usu_id):
    """Función principal para ejecutar el módulo de promociones"""
    try:
        root = tk.Tk()
        app = PromocionesGUI(root, negocio, emp_id, usu_id)
        
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
        print("\n🎟️ Módulo de promociones cerrado por el usuario")
    except Exception as e:
        print(f"❌ Error en módulo de promociones: {e}")
    finally:
        # Limpiar recursos si es necesario
        pass

if __name__ == "__main__":
    # Soporta el paso de argumentos desde el main.py
    negocio = sys.argv[1] if len(sys.argv) > 1 else "NEXUS"
    emp_id = int(sys.argv[2]) if len(sys.argv) > 2 else 1
    usu_id = int(sys.argv[3]) if len(sys.argv) > 3 else 1
    
    ejecutar_promociones(negocio, emp_id, usu_id)