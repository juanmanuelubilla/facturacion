import tkinter as tk
from tkinter import ttk, messagebox, filedialog
from PIL import Image, ImageTk
import os
import sys

# Importaciones de tu lógica
from productos import obtener_productos, buscar_producto_por_codigo
from db import get_connection

def obtener_categorias(empresa_id):
    """Obtiene todas las categorías de la empresa"""
    conn = get_connection()
    try:
        with conn.cursor() as cursor:
            cursor.execute("SELECT id, nombre FROM categorias WHERE empresa_id=%s ORDER BY nombre", (empresa_id,))
            return cursor.fetchall()
    finally:
        conn.close()

def crear_categoria(nombre, empresa_id):
    """Crea una nueva categoría"""
    conn = get_connection()
    try:
        with conn.cursor() as cursor:
            cursor.execute("INSERT INTO categorias (nombre, empresa_id) VALUES (%s, %s)", (nombre, empresa_id))
            conn.commit()
            return cursor.lastrowid
    finally:
        conn.close()

class GestionGUI:
    def __init__(self, root, nombre_negocio="NEXUS", empresa_id=1, usuario_id=1):
        self.root = root
        
        # Aseguramos que los IDs sean enteros limpios
        try:
            self.empresa_id = int(str(empresa_id).strip())
            self.usuario_id = int(str(usuario_id).strip())
        except:
            self.empresa_id = 1
            self.usuario_id = 1
        
        # 1. Cargamos configuración fiscal con lógica de rescate
        self.config_fiscal = self.obtener_config_fiscal()
        
        self.root.title(f"{nombre_negocio.upper()} - Gestión de Inventario")
        # Maximizar ventana con múltiples intentos y más tiempo
        self.root.after(500, self.maximizar_ventana)
        self.root.after(1000, self.maximizar_ventana)  # Segundo intento
        
        self.colors = {
            'bg_main': '#121212', 'bg_panel': '#1e1e1e', 'accent': '#00a8ff',
            'success': '#00db84', 'danger': '#ff4757', 'text_main': '#ffffff',
            'text_dim': '#a0a0a0', 'border': '#333333'
        }

        self.imagenes_cache = {}
        self.imagen_ruta = None
        self.iconos_cache = {}  # Cache para iconos de productos
        self.modo_formulario = "NUEVO"
        self.orden_asc = {col: False for col in ("codigo", "nombre", "costo", "precio", "stock", "agrupar", "tags")}
        
        self.configurar_estilo()
        self.crear_widgets(nombre_negocio)
        self.panel_form.grid_remove() 
        self.cargar_productos()

    @staticmethod
    def _fmt_stock(stock, es_pesable=False):
        try:
            s = float(stock or 0)
        except Exception:
            s = 0.0
        return f"{s:.3f}" if es_pesable else f"{int(s)}"

    def obtener_config_fiscal(self):
        """Busca porcentajes. Prioridad: 1. Empresa actual, 2. Registro ID=1."""
        try:
            conn = get_connection()
            with conn.cursor() as cursor:
                # Buscamos la configuración de esta empresa o la configuración maestra (ID 1)
                sql = """
                    SELECT impuesto, ingresos_brutos, ganancia_sugerida 
                    FROM nombre_negocio 
                    WHERE empresa_id = %s OR id = 1 
                    ORDER BY (empresa_id = %s) DESC 
                    LIMIT 1
                """
                cursor.execute(sql, (self.empresa_id, self.empresa_id))
                res = cursor.fetchone()
                if res:
                    iva = float(res.get('impuesto') or 0)
                    iibb = float(res.get('ingresos_brutos') or 0)
                    gan = float(res.get('ganancia_sugerida') or 0)
                    total = iva + iibb + gan
                    # Si los tres están en 0, devolvemos un log para saberlo
                    return {'total': total}
            return {'total': 0}
        except Exception:
            return {'total': 0}
        finally: 
            if 'conn' in locals(): conn.close()

    def calcular_precio_dinamico(self, event=None):
        """Calcula el precio final en base al recargo obtenido de la DB."""
        try:
            texto = self.entries['costo'].get().replace("$", "").strip()
            if texto:
                costo = float(texto)
                porcentaje = self.config_fiscal['total']
                
                # Si por alguna razón es 0, intentamos una re-lectura rápida del ID 1
                if porcentaje == 0:
                    self.config_fiscal = self.obtener_config_fiscal()
                    porcentaje = self.config_fiscal['total']

                precio_final = costo * (1 + (porcentaje / 100))
                
                self.entries['precio'].delete(0, tk.END)
                self.entries['precio'].insert(0, f"{precio_final:.2f}")
        except ValueError:
            pass

    def configurar_estilo(self):
        style = ttk.Style()
        style.theme_use('clam')
        self.root.configure(bg=self.colors['bg_main'])
        style.configure('Custom.Treeview', background=self.colors['bg_panel'], 
                        foreground=self.colors['text_main'], fieldbackground=self.colors['bg_panel'], 
                        borderwidth=0, font=('Segoe UI', 10), rowheight=45)
        style.configure('Custom.Treeview.Heading', font=('Segoe UI', 10, 'bold'), 
                        background="#252525", foreground="white", relief="flat")

    def crear_widgets(self, nombre_negocio):
        self.main_container = tk.Frame(self.root, bg=self.colors['bg_main'], padx=20, pady=20)
        self.main_container.pack(fill=tk.BOTH, expand=True)
        self.main_container.columnconfigure(0, weight=1) 
        self.main_container.rowconfigure(0, weight=1)

        # --- TABLA ---
        self.panel_tabla = tk.Frame(self.main_container, bg=self.colors['bg_main'])
        self.panel_tabla.grid(row=0, column=0, sticky="nsew", padx=(0, 10))
        self.panel_tabla.columnconfigure(0, weight=1); self.panel_tabla.rowconfigure(1, weight=1)

        tools = tk.Frame(self.panel_tabla, bg=self.colors['bg_main'])
        tools.grid(row=0, column=0, sticky="ew", pady=(0, 15))
        
        tk.Label(tools, text=f"{nombre_negocio.upper()}", font=('Segoe UI', 18, 'bold'), bg=self.colors['bg_main'], fg="white").pack(side=tk.LEFT)
        
        search_frame = tk.Frame(tools, bg=self.colors['bg_main'])
        search_frame.pack(side=tk.LEFT, fill=tk.X, expand=True, padx=30)
        self.buscador = tk.Entry(search_frame, font=('Segoe UI', 12), bg="#252525", fg="white", insertbackground="white")
        self.buscador.pack(fill=tk.X, ipady=8)
        self.buscador.insert(0, " 🔍 Buscar producto...")
        self.buscador.bind('<KeyRelease>', lambda e: self.cargar_productos(self.buscador.get().replace(" 🔍 Buscar producto...", "").lower()))

        tk.Button(tools, text="+ NUEVO", bg=self.colors['accent'], fg="white", command=self.preparar_nuevo_producto, padx=15).pack(side=tk.RIGHT)

        tree_container = tk.Frame(self.panel_tabla, bg=self.colors['bg_panel'], highlightbackground=self.colors['border'], highlightthickness=1)
        tree_container.grid(row=1, column=0, sticky="nsew")
        tree_container.columnconfigure(0, weight=1); tree_container.rowconfigure(0, weight=1)

        self.cols = ("codigo", "nombre", "costo", "precio", "stock", "agrupar", "tags", "activo")
        self.tabla = ttk.Treeview(tree_container, columns=self.cols, show='tree headings', style="Custom.Treeview")
        self.tabla.heading("#0", text="IMG"); self.tabla.column("#0", width=60)
        # Configurar encabezados personalizados
        self.tabla.heading("codigo", text="CÓDIGO", command=lambda c="codigo": self.ordenar_columna(c))
        self.tabla.heading("nombre", text="PRODUCTO", command=lambda c="nombre": self.ordenar_columna(c))
        self.tabla.heading("costo", text="COSTO", command=lambda c="costo": self.ordenar_columna(c))
        self.tabla.heading("precio", text="PRECIO", command=lambda c="precio": self.ordenar_columna(c))
        self.tabla.heading("stock", text="STOCK", command=lambda c="stock": self.ordenar_columna(c))
        self.tabla.heading("agrupar", text="AGRUPAR", command=lambda c="agrupar": self.ordenar_columna(c))
        self.tabla.heading("tags", text="TAGS", command=lambda c="tags": self.ordenar_columna(c))
        self.tabla.heading("activo", text="ACTIVO", command=lambda c="activo": self.ordenar_columna(c))
        
        for col in self.cols:
            self.tabla.column(col, width=100, anchor="center")
        self.tabla.column("nombre", width=200)
        self.tabla.column("agrupar", width=120)
        self.tabla.column("tags", width=120)
        self.tabla.column("activo", width=80)
        self.tabla.grid(row=0, column=0, sticky="nsew")
        self.tabla.bind('<<TreeviewSelect>>', self.on_seleccionar_producto)
        self.tabla.bind('<Button-3>', self.expandir_contraer_categoria)  # Click derecho para expandir/contraer
        
        # Configurar estilo para aumentar altura de filas
        style = ttk.Style()
        style.configure("Custom.Treeview", rowheight=40)  # Aumentar altura de filas
        style.configure("Custom.Treeview.Heading", font=('Segoe UI', 10, 'bold'))

        # --- FORMULARIO ---
        self.panel_form = tk.Frame(self.main_container, bg=self.colors['bg_panel'], highlightbackground=self.colors['border'], highlightthickness=1, padx=25)
        self.panel_form.grid(row=0, column=1, sticky="nsew")

        # Indicador visual del recargo cargado
        lbl_recargo = f"Recargo: +{self.config_fiscal['total']}%"
        tk.Label(self.panel_form, text=lbl_recargo, font=('Segoe UI', 8), bg=self.colors['bg_panel'], fg=self.colors['success']).pack()

        self.entries = {}
        campos = [("CÓDIGO", "codigo"), ("NOMBRE", "nombre"), ("COSTO ($)", "costo"), ("PRECIO ($)", "precio"), ("STOCK", "stock")]
        for label, key in campos:
            f = tk.Frame(self.panel_form, bg=self.colors['bg_panel'])
            f.pack(fill=tk.X, pady=5)
            tk.Label(f, text=label, font=('Segoe UI', 8), bg=self.colors['bg_panel'], fg=self.colors['text_dim']).pack(anchor="w")
            e = tk.Entry(f, font=('Segoe UI', 11), bg="#252525", fg="white", insertbackground="white")
            e.pack(fill=tk.X, ipady=5)
            self.entries[key] = e
            if key == "costo":
                e.bind("<KeyRelease>", self.calcular_precio_dinamico)

        # Campo de Descripción
        f_desc = tk.Frame(self.panel_form, bg=self.colors['bg_panel'])
        f_desc.pack(fill=tk.X, pady=5)
        tk.Label(f_desc, text="DESCRIPCIÓN", font=('Segoe UI', 8), bg=self.colors['bg_panel'], fg=self.colors['text_dim']).pack(anchor="w")
        self.txt_descripcion = tk.Text(f_desc, font=('Segoe UI', 11), bg="#252525", fg="white", insertbackground="white", height=3)
        self.txt_descripcion.pack(fill=tk.X, ipady=5)

        # Campo de Categoría
        f_cat = tk.Frame(self.panel_form, bg=self.colors['bg_panel'])
        f_cat.pack(fill=tk.X, pady=5)
        tk.Label(f_cat, text="CATEGORÍA", font=('Segoe UI', 8), bg=self.colors['bg_panel'], fg=self.colors['text_dim']).pack(anchor="w")
        
        # Frame para categoría con combobox y botón de nueva categoría
        cat_frame = tk.Frame(f_cat, bg=self.colors['bg_panel'])
        cat_frame.pack(fill=tk.X)
        
        self.combo_categoria = ttk.Combobox(cat_frame, values=[], state="readonly", font=('Segoe UI', 10))
        self.combo_categoria.pack(side=tk.LEFT, fill=tk.X, expand=True, ipady=2)
        
        tk.Button(cat_frame, text="+", bg=self.colors['accent'], fg="white", font=('Segoe UI', 8, 'bold'), 
                 width=3, command=self.nueva_categoria).pack(side=tk.RIGHT, padx=(5, 0))
        
        # Campo de Tags
        f_tags = tk.Frame(self.panel_form, bg=self.colors['bg_panel'])
        f_tags.pack(fill=tk.X, pady=5)
        tk.Label(f_tags, text="TAGS", font=('Segoe UI', 8), bg=self.colors['bg_panel'], fg=self.colors['text_dim']).pack(anchor="w")
        self.ent_tags = tk.Entry(f_tags, font=('Segoe UI', 11), bg="#252525", fg="white", insertbackground="white")
        self.ent_tags.pack(fill=tk.X, ipady=5)
        
        # Frame para checkboxes en la misma línea
        frame_checkboxes = tk.Frame(self.panel_form, bg=self.colors['bg_panel'])
        frame_checkboxes.pack(fill=tk.X, pady=(10, 8))
        
        # Campo ACTIVO (izquierda)
        self.var_activo = tk.BooleanVar(value=True)
        tk.Checkbutton(
            frame_checkboxes,
            text="PRODUCTO ACTIVO",
            variable=self.var_activo,
            bg=self.colors['bg_panel'],
            fg="white",
            selectcolor="#252525",
            activebackground=self.colors['bg_panel'],
            activeforeground="white"
        ).pack(side=tk.LEFT, anchor="w")
        
        # VENDER POR FRACCIÓN / KILO (derecha)
        self.var_venta_por_peso = tk.BooleanVar(value=False)
        tk.Checkbutton(
            frame_checkboxes,
            text="VENDER POR FRACCIÓN / KILO",
            variable=self.var_venta_por_peso,
            bg=self.colors['bg_panel'],
            fg="white",
            selectcolor="#252525",
            activebackground=self.colors['bg_panel'],
            activeforeground="white"
        ).pack(side=tk.RIGHT, anchor="e")
        
        # Cargar categorías
        self.cargar_categorias()

        # Frame para la imagen con tamaño fijo
        frame_imagen = tk.Frame(self.panel_form, bg="#252525", height=150, relief="solid", borderwidth=1)
        frame_imagen.pack(fill=tk.X, pady=10)
        frame_imagen.pack_propagate(False)  # Para que mantenga el tamaño fijo
        
        self.preview_label = tk.Label(frame_imagen, text="SIN IMAGEN", bg="#252525")
        self.preview_label.pack(expand=True, fill=tk.BOTH)
        
        # Frame para botones de imagen
        frame_botones_img = tk.Frame(self.panel_form, bg=self.colors['bg_panel'])
        frame_botones_img.pack(fill=tk.X, pady=5)
        
        tk.Button(frame_botones_img, text="FOTO", command=self.seleccionar_imagen, bg=self.colors['accent'], fg="white", font=('Segoe UI', 8, 'bold'), pady=10).pack(side=tk.LEFT, fill=tk.X, expand=True, padx=(0, 2))
        self.btn_eliminar_imagen = tk.Button(frame_botones_img, text="ELIMINAR FOTO", command=self.eliminar_foto, bg=self.colors['danger'], fg="white", font=('Segoe UI', 8, 'bold'), pady=10)
        self.btn_eliminar_imagen.pack(side=tk.LEFT, fill=tk.X, expand=True, padx=(2, 0))
        self.btn_eliminar_imagen.config(state='disabled')  # Deshabilitado hasta que haya una imagen

        # Frame para botones inferiores
        frame_botones = tk.Frame(self.panel_form, bg=self.colors['bg_panel'])
        frame_botones.pack(fill=tk.X, side=tk.BOTTOM, pady=(0, 20))
        
        tk.Button(frame_botones, text="GUARDAR", bg=self.colors['success'], fg="white", command=self.guardar_cambios, pady=10).pack(fill=tk.X, pady=(0, 5))
        self.btn_del_producto = tk.Button(frame_botones, text="ELIMINAR PRODUCTO", bg="#e84393", fg="white", command=self.eliminar_producto_completo, pady=10)
        self.btn_del_producto.pack(fill=tk.X)

    def cargar_productos(self, filtro=None):
        """Carga productos agrupados por categorías en vista jerárquica"""
        from productos import obtener_categorias
        conn = get_connection()
        try:
            # Obtener categorías
            categorias = obtener_categorias(self.empresa_id)
            categorias_dict = {cat['id']: cat['nombre'] for cat in categorias}
            
            # Obtener productos
            if filtro:
                cursor = conn.cursor()
                cursor.execute("""
                    SELECT id, codigo, nombre, descripcion, precio, costo, stock, activo, imagen, venta_por_peso, categoria_id, tags
                    FROM productos
                    WHERE activo = 1 AND empresa_id = %s AND (codigo LIKE %s OR nombre LIKE %s OR tags LIKE %s)
                    ORDER BY categoria_id, nombre
                """, (self.empresa_id, f"%{filtro}%", f"%{filtro}%", f"%{filtro}%"))
                productos = cursor.fetchall()
                cursor.close()
            else:
                cursor = conn.cursor()
                cursor.execute("""
                    SELECT id, codigo, nombre, descripcion, precio, costo, stock, activo, imagen, venta_por_peso, categoria_id, tags
                    FROM productos
                    WHERE activo = 1 AND empresa_id = %s
                    ORDER BY categoria_id, nombre
                """, (self.empresa_id,))
                productos = cursor.fetchall()
                cursor.close()
            
            # Agrupar productos por categoría
            productos_por_categoria = {}
            productos_sin_categoria = []
            
            for p in productos:
                cat_id = p.get("categoria_id")
                if cat_id and cat_id in categorias_dict:
                    if cat_id not in productos_por_categoria:
                        productos_por_categoria[cat_id] = []
                    productos_por_categoria[cat_id].append(p)
                else:
                    productos_sin_categoria.append(p)
            
            # Limpiar tabla
            for item in self.tabla.get_children():
                self.tabla.delete(item)
            
            # Insertar categorías y productos
            categoria_items = {}
            
            # Primero insertar productos con categoría
            for cat_id, productos_cat in productos_por_categoria.items():
                cat_nombre = categorias_dict[cat_id]
                
                # Insertar categoría como nodo padre
                cat_item = self.tabla.insert("", tk.END, text=f"📁 {cat_nombre} ({len(productos_cat)})", 
                                           values=("", "", "", "", "", cat_nombre, ""), open=True)
                categoria_items[cat_id] = cat_item
                
                # Insertar productos de esta categoría
                for p in productos_cat:
                    icono = self.obtener_icono(p.get("imagen"))
                    tags_text = p.get("tags", "")[:30] + "..." if p.get("tags") and len(p.get("tags")) > 30 else (p.get("tags") or "")
                    es_pesable = bool(int(p.get('venta_por_peso') or 0))
                    stock_txt = self._fmt_stock(p.get('stock', 0), es_pesable)
                    
                    activo_text = "✅" if p.get('activo', 1) == 1 else "❌"
                    self.tabla.insert(cat_item, tk.END, text="", image=icono if icono else "", values=(
                        p["codigo"], p["nombre"], f"${float(p.get('costo', 0)):.2f}", 
                        f"${float(p.get('precio', 0)):.2f}", stock_txt, cat_nombre, tags_text, activo_text
                    ))
            
            # Luego insertar productos sin categoría
            if productos_sin_categoria:
                cat_item = self.tabla.insert("", tk.END, text=f"📁 Sin categoría ({len(productos_sin_categoria)})", 
                                           values=("", "", "", "", "", "Sin categoría", ""), open=True)
                
                for p in productos_sin_categoria:
                    icono = self.obtener_icono(p.get("imagen"))
                    tags_text = p.get("tags", "")[:30] + "..." if p.get("tags") and len(p.get("tags")) > 30 else (p.get("tags") or "")
                    es_pesable = bool(int(p.get('venta_por_peso') or 0))
                    stock_txt = self._fmt_stock(p.get('stock', 0), es_pesable)
                    
                    self.tabla.insert(cat_item, tk.END, text="", image=icono if icono else "", values=(
                        p["codigo"], p["nombre"], f"${float(p.get('costo', 0)):.2f}", 
                        f"${float(p.get('precio', 0)):.2f}", stock_txt, "Sin categoría", tags_text
                    ))
            
            # Guardar referencia para expandir/contraer
            self.categoria_items = categoria_items
            
        finally:
            conn.close()

    def expandir_contraer_categoria(self, event):
        """Expande o contrae una categoría con click derecho"""
        try:
            # Obtener el item bajo el cursor
            item = self.tabla.identify_row(event.y)
            if not item:
                return
            
            # Verificar si es una categoría (no tiene valores de producto)
            values = self.tabla.item(item)['values']
            if values and values[0] == "":  # Es una categoría
                # Alternar estado de expansión
                if self.tabla.item(item)['open']:
                    self.tabla.item(item, open=False)
                else:
                    self.tabla.item(item, open=True)
        except Exception as e:
            pass  # Error al expandir/contraer

    def on_seleccionar_producto(self, event):
        sel = self.tabla.selection()
        if not sel: return
        p = buscar_producto_por_codigo(self.tabla.set(sel[0], "codigo"), self.empresa_id)
        if p:
            self.modo_formulario = "EDITAR"; self.llenar_formulario(p); self.panel_form.grid()

    def guardar_cambios(self):
        try:
            d = {k: v.get().strip() for k, v in self.entries.items()}
            desc = self.txt_descripcion.get("1.0", tk.END).strip()
            stock_normalizado = self._fmt_stock(d['stock'], bool(int(self.var_venta_por_peso.get())))
            
            # Obtener categoría y tags
            categoria_seleccionada = self.combo_categoria.get()
            categoria_id = None
            if categoria_seleccionada:
                categoria_id = self.categoria_datos.get(categoria_seleccionada)
            
            tags = self.ent_tags.get().strip()
            activo = 1 if self.var_activo.get() else 0
            
            conn = get_connection()
            with conn.cursor() as cursor:
                if self.modo_formulario == "NUEVO":
                    cursor.execute("INSERT INTO productos (codigo, nombre, descripcion, precio, costo, stock, imagen, ultimo_usuario_id, empresa_id, activo, venta_por_peso, categoria_id, tags) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)", 
                                    (d['codigo'], d['nombre'], desc, d['precio'], d['costo'], stock_normalizado, self.imagen_ruta, self.usuario_id, self.empresa_id, activo, int(self.var_venta_por_peso.get()), categoria_id, tags))
                else:
                    cursor.execute("UPDATE productos SET nombre=%s, descripcion=%s, precio=%s, costo=%s, stock=%s, imagen=%s, ultimo_usuario_id=%s, venta_por_peso=%s, categoria_id=%s, tags=%s, activo=%s WHERE codigo=%s AND empresa_id=%s", 
                                    (d['nombre'], desc, d['precio'], d['costo'], stock_normalizado, self.imagen_ruta, self.usuario_id, int(self.var_venta_por_peso.get()), categoria_id, tags, activo, d['codigo'], self.empresa_id))
            conn.commit(); conn.close(); messagebox.showinfo("OK", "Guardado"); self.cargar_productos(); self.panel_form.grid_remove()
        except Exception as e: messagebox.showerror("Error", str(e))

    def obtener_icono(self, ruta):
        if not ruta:
            return None
        
        # Usar caché para evitar cargar la misma imagen múltiples veces
        if ruta in self.iconos_cache:
            return self.iconos_cache[ruta]
        
        # Verificar si la ruta existe
        if not os.path.exists(ruta):
            return None
            
        try:
            img = Image.open(ruta).resize((32, 32), Image.Resampling.LANCZOS)
            icono = ImageTk.PhotoImage(img)
            self.iconos_cache[ruta] = icono  # Guardar en caché
            return icono
        except Exception as e:
            return None

    def llenar_formulario(self, p):
        self.limpiar_formulario()
        self.entries['codigo'].insert(0, p["codigo"]); self.entries['codigo'].config(state='readonly')
        self.entries['nombre'].insert(0, p["nombre"])
        self.entries['costo'].insert(0, p["costo"])
        self.entries['precio'].insert(0, p["precio"])
        self.entries['stock'].insert(0, self._fmt_stock(p.get("stock", 0), bool(int(p.get("venta_por_peso") or 0))))
        self.txt_descripcion.insert("1.0", p.get("descripcion") or "")
        self.var_venta_por_peso.set(bool(int(p.get("venta_por_peso") or 0)))
        
        # Cargar categoría
        if p.get("categoria_id"):
            for texto, cat_id in self.categoria_datos.items():
                if cat_id == p["categoria_id"]:
                    self.combo_categoria.set(texto)
                    break
        
        # Cargar tags
        if p.get("tags"):
            self.ent_tags.insert(0, p["tags"])
        
        # Cargar estado activo
        self.var_activo.set(bool(p.get('activo', 1)))
        
        # Cargar imagen del producto si existe
        if p.get("imagen"):
            self.imagen_ruta = p["imagen"]
            if os.path.exists(p["imagen"]):
                try:
                    img = Image.open(p["imagen"]).resize((150, 150), Image.Resampling.LANCZOS)
                    ph = ImageTk.PhotoImage(img)
                    self.preview_label.config(image=ph, text="")
                    self.preview_label.image = ph  # Mantener referencia
                    self.imagenes_cache[p["imagen"]] = ph  # Guardar en caché
                    self.btn_eliminar_imagen.config(state='normal')  # Habilitar botón ELIMINAR FOTO
                except Exception as e:
                    self.preview_label.config(image="", text="SIN IMAGEN")
            else:
                self.preview_label.config(image="", text="SIN IMAGEN")
        else:
            self.preview_label.config(image="", text="SIN IMAGEN")

    def seleccionar_imagen(self):
        """Abre diálogo para seleccionar una imagen del producto"""
        ruta = filedialog.askopenfilename(
            title="Seleccionar imagen del producto",
            filetypes=[
                ("Archivos de imagen", "*.jpg *.jpeg *.png *.gif *.bmp"),
                ("Todos los archivos", "*.*")
            ]
        )
        if ruta:
            self.imagen_ruta = ruta
            try:
                img = Image.open(ruta).resize((150, 150), Image.Resampling.LANCZOS)
                photo = ImageTk.PhotoImage(img)
                self.preview_label.config(image=photo, text="")
                self.preview_label.image = photo
                self.btn_eliminar_imagen.config(state='normal')  # Habilitar botón ELIMINAR FOTO
            except Exception as e:
                messagebox.showerror("Error", f"No se pudo cargar la imagen: {e}")

    def eliminar_producto_completo(self):
        """Elimina completamente el producto y todo lo asociado (excepto facturas y ventas)"""
        if not self.entries['codigo'].get():
            messagebox.showwarning("Aviso", "Seleccione un producto")
            return
        
        if messagebox.askyesno("Confirmar Eliminación", 
            f"¿Eliminar permanentemente el producto {self.entries['nombre'].get()}?\n\n"
            "Esta acción eliminará:\n"
            "• El producto y sus datos\n"
            "• Imágenes asociadas\n"
            "• Referencias en inventario\n"
            "• Historial de stock\n\n"
            "NO se eliminarán:\n"
            "• Facturas emitidas\n"
            "• Historial de ventas\n"
            "• Registros contables"):
            
            from db import get_connection
            conn = get_connection()
            try:
                with conn.cursor() as cursor:
                    producto_codigo = self.entries['codigo'].get()
                    
                    # 1. Eliminar de tablas relacionadas (excepto facturas/ventas)
                    # Eliminar de detalles de ventas (mantener cabeceras de facturas)
                    cursor.execute("DELETE FROM detalle_ventas WHERE producto_codigo = %s AND empresa_id = %s", 
                                 (producto_codigo, self.empresa_id))
                    
                    # Eliminar de movimientos de stock/inventario
                    cursor.execute("DELETE FROM movimientos_stock WHERE producto_codigo = %s AND empresa_id = %s", 
                                 (producto_codigo, self.empresa_id))
                    
                    # Eliminar de promociones/cupones si existen
                    cursor.execute("DELETE FROM promociones WHERE producto_codigo = %s AND empresa_id = %s", 
                                 (producto_codigo, self.empresa_id))
                    
                    # Eliminar de reportes o estadísticas si existen
                    cursor.execute("DELETE FROM reportes_productos WHERE producto_codigo = %s AND empresa_id = %s", 
                                 (producto_codigo, self.empresa_id))
                    
                    # 2. Finalmente eliminar el producto
                    cursor.execute("DELETE FROM productos WHERE codigo=%s AND empresa_id=%s", 
                                 (producto_codigo, self.empresa_id))
                    
                conn.commit()
                messagebox.showinfo("Eliminado", f"El producto {self.entries['nombre'].get()} ha sido eliminado permanentemente.\nSe mantuvieron las facturas y registros de ventas.")
                self.cargar_productos()
                self.panel_form.grid_remove()
            except Exception as e:
                conn.rollback()
                messagebox.showerror("Error", f"No se pudo eliminar el producto: {e}")
            finally:
                conn.close()

    def eliminar_foto(self):
        """Elimina la imagen del producto con confirmación"""
        if self.imagen_ruta:
            resultado = messagebox.askyesno("Confirmar", "¿Desea eliminar la foto descriptiva del producto?")
            if resultado:
                # Limpiar imagen del formulario
                self.imagen_ruta = None
                self.preview_label.config(image="", text="SIN IMAGEN")
                self.btn_eliminar_imagen.config(state='disabled')
                messagebox.showinfo("Eliminado", "La foto del producto ha sido eliminada")
        else:
            messagebox.showinfo("Aviso", "No hay foto para eliminar")

    def preparar_nuevo_producto(self):
        self.modo_formulario = "NUEVO"; self.limpiar_formulario()
        self.entries['codigo'].config(state='normal'); self.panel_form.grid()

    def limpiar_formulario(self):
        for e in self.entries.values(): e.delete(0, tk.END)
        self.var_venta_por_peso.set(False)
        self.txt_descripcion.delete("1.0", tk.END); self.preview_label.config(image="", text="SIN IMAGEN"); self.imagen_ruta = None
        self.btn_eliminar_imagen.config(state='disabled')  # Deshabilitar botón ELIMINAR FOTO
        self.combo_categoria.set("")
        self.ent_tags.delete(0, tk.END)

    def cargar_categorias(self):
        """Carga las categorías en el combobox"""
        try:
            categorias = obtener_categorias(self.empresa_id)
            valores = [(f"{cat['id']} - {cat['nombre']}", cat['id']) for cat in categorias]
            self.combo_categoria['values'] = [v[0] for v in valores]
            self.categoria_datos = dict(valores)
        except Exception as e:
            pass  # Error cargando categorías

    def nueva_categoria(self):
        """Abre diálogo para crear nueva categoría"""
        dialog = tk.Toplevel(self.root)
        dialog.title("Nueva Categoría")
        dialog.geometry("300x120")
        dialog.configure(bg=self.colors['bg_panel'])
        dialog.transient(self.root)
        dialog.grab_set()
        
        tk.Label(dialog, text="Nombre de la categoría:", font=('Segoe UI', 10), 
                bg=self.colors['bg_panel'], fg="white").pack(pady=10)
        
        entry = tk.Entry(dialog, font=('Segoe UI', 12), bg="#252525", fg="white", insertbackground="white")
        entry.pack(padx=20, fill=tk.X)
        entry.focus()
        
        def guardar():
            nombre = entry.get().strip()
            if nombre:
                try:
                    crear_categoria(nombre, self.empresa_id)
                    self.cargar_categorias()
                    messagebox.showinfo("OK", "Categoría creada")
                    dialog.destroy()
                except Exception as e:
                    messagebox.showerror("Error", str(e))
            else:
                messagebox.showwarning("Aviso", "Ingrese un nombre")
        
        tk.Button(dialog, text="GUARDAR", bg=self.colors['success'], fg="white", 
                 command=guardar).pack(pady=10)
        
        entry.bind('<Return>', lambda e: guardar())

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

    def ordenar_columna(self, col):
        """Ordena productos dentro de cada categoría manteniendo la estructura jerárquica"""
        self.orden_asc[col] = not self.orden_asc[col]
        
        # Obtener todas las categorías (nodos padres)
        categorias = []
        for item in self.tabla.get_children(''):
            values = self.tabla.item(item)['values']
            # Las categorías tienen todos los valores vacíos excepto el campo agrupar (ahora 8 valores con activo)
            if values and len(values) == 8 and values[0] == "" and values[1] == "" and values[2] == "" and values[3] == "" and values[4] == "" and values[6] == "" and values[7] == "":
                categorias.append(item)
        
        # Para cada categoría, ordenar sus productos
        for categoria_item in categorias:
            # Obtener todos los productos de esta categoría
            productos = []
            for producto_item in self.tabla.get_children(categoria_item):
                # Obtener el valor de la columna para ordenamiento
                valor = self.tabla.set(producto_item, col)
                
                # Manejar valores numéricos para costo, precio y stock
                if col in ['costo', 'precio']:
                    try:
                        # Quitar símbolo $ y convertir a float
                        valor_num = float(str(valor).replace('$', '').replace(',', '').strip())
                        productos.append((valor_num, producto_item))
                    except:
                        productos.append((0, producto_item))
                elif col == 'stock':
                    try:
                        # Manejar valores de stock que pueden tener formato especial
                        stock_str = str(valor)
                        if 'kg' in stock_str.lower():
                            valor_num = float(stock_str.replace('kg', '').strip())
                        else:
                            valor_num = float(stock_str.replace(',', '').strip())
                        productos.append((valor_num, producto_item))
                    except:
                        productos.append((0, producto_item))
                elif col == 'nombre':
                    # Para columna PRODUCTO, ordenar por el nombre desde los values
                    nombre_producto = self.tabla.set(producto_item, "nombre")
                    productos.append((str(nombre_producto).lower(), producto_item))
                elif col == 'agrupar':
                    # Para columna AGRUPAR, ordenar por nombre del producto (columna #0)
                    nombre_producto = self.tabla.item(producto_item)['text']
                    productos.append((str(nombre_producto).lower(), producto_item))
                else:
                    # Para código, tags - ordenamiento alfabético
                    productos.append((str(valor).lower(), producto_item))
            
            # Ordenar productos según la columna y dirección
            productos.sort(key=lambda x: x[0], reverse=not self.orden_asc[col])
            
            # Reordenar los productos dentro de la categoría
            for index, (valor_ordenado, producto_item) in enumerate(productos):
                self.tabla.move(producto_item, categoria_item, index)

if __name__ == "__main__":
    negocio = sys.argv[1] if len(sys.argv) > 1 else "NEXUS"
    emp_id = sys.argv[2] if len(sys.argv) > 2 else 1
    usu_id = sys.argv[3] if len(sys.argv) > 3 else 1
    root = tk.Tk(); app = GestionGUI(root, negocio, emp_id, usu_id); root.mainloop()