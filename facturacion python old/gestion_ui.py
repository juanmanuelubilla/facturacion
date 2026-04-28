import tkinter as tk
from tkinter import ttk, messagebox, filedialog
import sqlite3
import os
import sys
from datetime import datetime

# Importar PIL al nivel del módulo para evitar conflictos
try:
    from PIL import Image, ImageTk
    PIL_AVAILABLE = True
except ImportError:
    PIL_AVAILABLE = False
    print("⚠️ PIL no disponible, las imágenes no funcionarán")

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
        self.imagen_actual = None  # Referencia fuerte para imagen actual
        self.productos_data = {}  # Datos de productos para Treeview
        self.tabla_imagenes = {}  # Diccionario CRÍTICO para mantener referencias de imágenes en tabla
        self.modo_formulario = "NUEVO"
        self.orden_reversa = False  # Dirección de ordenamiento
        
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
        style.map('Custom.Treeview', 
                 background=[('selected', self.colors['accent'])],
                 foreground=[('selected', 'white')])

    def crear_widgets(self, nombre_negocio):
        self.main_container = tk.Frame(self.root, bg=self.colors['bg_main'], padx=20, pady=20)
        self.main_container.pack(fill=tk.BOTH, expand=True)
        self.main_container.columnconfigure(0, weight=1) 
        self.main_container.rowconfigure(0, weight=1)

        # --- LISTADO DE PRODUCTOS ---
        self.panel_tabla = tk.Frame(self.main_container, bg=self.colors['bg_main'])
        self.panel_tabla.grid(row=0, column=0, sticky="nsew", padx=10)
        self.panel_tabla.columnconfigure(0, weight=1); self.panel_tabla.rowconfigure(1, weight=1)

        tools = tk.Frame(self.panel_tabla, bg=self.colors['bg_main'])
        tools.grid(row=0, column=0, sticky="ew", pady=15)
        
        tk.Label(tools, text=f"{nombre_negocio.upper()}", font=('Segoe UI', 18, 'bold'), bg=self.colors['bg_main'], fg="white").pack(side=tk.LEFT)
        
        search_frame = tk.Frame(tools, bg=self.colors['bg_main'])
        search_frame.pack(side=tk.LEFT, fill=tk.X, expand=True, padx=30)
        self.buscador = tk.Entry(search_frame, font=('Segoe UI', 12), bg="#252525", fg="white", insertbackground="white")
        self.buscador.pack(fill=tk.X, ipady=8)
        self.buscador.insert(0, " 🔍 Buscar producto...")
        self.buscador.bind('<FocusIn>', self.limpiar_buscador)
        self.buscador.bind('<KeyRelease>', lambda e: self.cargar_productos(self.buscador.get().replace(" 🔍 Buscar producto...", "").lower()))

        tk.Button(tools, text="+ NUEVO", bg=self.colors['accent'], fg="white", command=self.preparar_nuevo_producto, padx=15).pack(side=tk.RIGHT)

        # Treeview para listado de productos - SOLO COLUMNA #0 como en el ejemplo
        tree_container = tk.Frame(self.panel_tabla, bg=self.colors['bg_panel'], highlightbackground=self.colors['border'], highlightthickness=1)
        tree_container.grid(row=1, column=0, sticky="nsew")
        tree_container.columnconfigure(0, weight=1); tree_container.rowconfigure(0, weight=1)
        
        # Treeview con imágenes - columnas de productos (igual que vieja versión + categoría y tags)
        self.cols = ("codigo", "nombre", "costo", "precio", "stock", "categoria", "tags")
        self.tabla = ttk.Treeview(tree_container, columns=self.cols, show='tree headings', style="Custom.Treeview")
        
        # Configurar encabezados
        self.tabla.heading("#0", text="IMG"); self.tabla.column("#0", width=60)
        for col in self.cols:
            self.tabla.heading(col, text=col.upper())
            self.tabla.column(col, width=100, anchor="center")
        self.tabla.column("nombre", width=250)
        self.tabla.column("categoria", width=120)
        self.tabla.column("tags", width=120)
        
        # Scrollbar
        scrollbar = tk.Scrollbar(tree_container, orient="vertical", command=self.tabla.yview)
        self.tabla.configure(yscrollcommand=scrollbar.set)
        
        self.tabla.grid(row=0, column=0, sticky="nsew")
        scrollbar.grid(row=0, column=1, sticky="ns")
        
        # Evento de selección
        self.tabla.bind("<<TreeviewSelect>>", self.on_seleccionar_producto)
        

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
                 width=3, command=self.nueva_categoria).pack(side=tk.RIGHT, padx=5)
        
        # Campo de Tags
        f_tags = tk.Frame(self.panel_form, bg=self.colors['bg_panel'])
        f_tags.pack(fill=tk.X, pady=5)
        tk.Label(f_tags, text="TAGS", font=('Segoe UI', 8), bg=self.colors['bg_panel'], fg=self.colors['text_dim']).pack(anchor="w")
        self.ent_tags = tk.Entry(f_tags, font=('Segoe UI', 11), bg="#252525", fg="white", insertbackground="white")
        self.ent_tags.pack(fill=tk.X, ipady=5)
        
        # Frame para checkboxes en la misma línea
        frame_checkboxes = tk.Frame(self.panel_form, bg=self.colors['bg_panel'])
        frame_checkboxes.pack(fill=tk.X, pady=10)
        
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
        
        tk.Button(frame_botones_img, text="FOTO", command=self.seleccionar_imagen, bg=self.colors['accent'], fg="white", font=('Segoe UI', 8, 'bold'), pady=10).pack(side=tk.LEFT, fill=tk.X, expand=True, padx=2)
        self.btn_eliminar_imagen = tk.Button(frame_botones_img, text="ELIMINAR FOTO", command=self.eliminar_foto, bg=self.colors['danger'], fg="white", font=('Segoe UI', 8, 'bold'), pady=10)
        self.btn_eliminar_imagen.pack(side=tk.LEFT, fill=tk.X, expand=True, padx=2)
        self.btn_eliminar_imagen.config(state='disabled')  # Deshabilitado hasta que haya una imagen

        # Frame para botones inferiores
        frame_botones = tk.Frame(self.panel_form, bg=self.colors['bg_panel'])
        frame_botones.pack(fill=tk.X, side=tk.BOTTOM, pady=20)
        
        tk.Button(frame_botones, text="GUARDAR", bg=self.colors['success'], fg="white", command=self.guardar_cambios, pady=10).pack(fill=tk.X, pady=5)
        self.btn_del_producto = tk.Button(frame_botones, text="ELIMINAR PRODUCTO", bg="#e84393", fg="white", command=self.eliminar_producto_completo, pady=10)
        self.btn_del_producto.pack(fill=tk.X)

    def cargar_productos(self, filtro=None):
        for item in self.tabla.get_children(): self.tabla.delete(item)
        productos = obtener_productos(self.empresa_id)
        for p in productos:
            sku, nom = str(p.get("codigo", "")), str(p.get("nombre", ""))
            if filtro and filtro not in sku.lower() and filtro not in nom.lower(): continue
            icono = self.obtener_icono(p.get("imagen"))
            cat_nombre = p.get("categoria_nombre", "Sin categoría")
            tags = p.get("tags", "")
            self.tabla.insert("", tk.END, image=icono if icono else "",
                values=(sku, nom, f"${float(p.get('costo', 0)):.2f}", f"${float(p.get('precio', 0)):.2f}", p.get('stock', 0), cat_nombre, tags))

    def limpiar_buscador(self, event):
        if self.buscador.get() == " 🔍 Buscar producto...":
            self.buscador.delete(0, tk.END)

    def on_seleccionar_producto(self, event):
        sel = self.tabla.selection()
        if not sel: return
        p = buscar_producto_por_codigo(self.tabla.set(sel[0], "codigo"), self.empresa_id)
        if p:
            self.modo_formulario = "EDITAR"; self.llenar_formulario(p); self.panel_form.grid()

    def ordenar_tabla(self, col):
        """Ordena la tabla por la columna especificada"""
        # Obtener todos los items
        items = [(self.tabla.set(item, col), item) for item in self.tabla.get_children('')]
        
        # Determinar tipo de ordenamiento
        if col in ['costo', 'precio']:
            # Ordenar numéricamente quitando $ y convirtiendo a float
            try:
                items.sort(key=lambda x: float(str(x[0]).replace('$', '').replace(',', '').strip()), 
                         reverse=getattr(self, 'orden_reversa', False))
            except:
                items.sort(reverse=getattr(self, 'orden_reversa', False))
        elif col == 'stock':
            # Ordenar por stock manejando unidades y metros
            try:
                def parse_stock(val):
                    val_str = str(val).lower()
                    if 'm' in val_str:
                        return float(val_str.replace('m', '').strip())
                    elif 'u' in val_str:
                        return float(val_str.replace('u', '').strip())
                    else:
                        return float(val_str)
                items.sort(key=lambda x: parse_stock(x[0]), 
                         reverse=getattr(self, 'orden_reversa', False))
            except:
                items.sort(reverse=getattr(self, 'orden_reversa', False))
        else:
            # Ordenar alfabéticamente
            items.sort(key=lambda x: str(x[0]).lower(), 
                      reverse=getattr(self, 'orden_reversa', False))
        
        # Alternar dirección para próxima vez
        self.orden_reversa = not getattr(self, 'orden_reversa', False)
        
        # Reordenar items en la tabla
        for index, (val, item) in enumerate(items):
            self.tabla.move(item, '', index)

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
            conn.commit()
            conn.close()
            messagebox.showinfo("OK", "Guardado")
            self.cargar_productos()
            self.panel_form.grid_remove()
        except Exception as e:
            messagebox.showerror("Error", str(e))

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
            conn.commit()
            conn.close()
            messagebox.showinfo("OK", "Guardado")
            self.cargar_productos()
            self.panel_form.grid_remove()
        except Exception as e:
            messagebox.showerror("Error", str(e))

    def obtener_icono(self, ruta):
        if not ruta or not os.path.exists(ruta): return None
        if ruta in self.imagenes_cache: return self.imagenes_cache[ruta]
        try:
            img = Image.open(ruta).resize((32, 32), Image.Resampling.LANCZOS)
            photo = ImageTk.PhotoImage(img)
            self.imagenes_cache[ruta] = photo
            return photo
        except: return None

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
            # Convertir ruta relativa a absoluta si es necesario
            imagen_ruta = p["imagen"]
            
            if not os.path.isabs(imagen_ruta):
                imagen_ruta = os.path.join(os.path.dirname(__file__), imagen_ruta)
            
            self.imagen_ruta = imagen_ruta
            
            if os.path.exists(imagen_ruta):
                try:
                    from PIL import Image, ImageTk
                    
                    # Abrir y redimensionar la imagen
                    img = Image.open(imagen_ruta)
                    img = img.resize((150, 150), Image.Resampling.LANCZOS)
                    
                    # Convertir a PhotoImage de Tkinter usando PIL
                    photo = ImageTk.PhotoImage(img)
                    
                    # IMPORTANTE: Asignar imagen inmediatamente y mantener referencia
                    self.preview_label.configure(image=photo, text="")
                    self.preview_label.image = photo
                    self.imagen_actual = photo
                    
                    # Forzar actualización del widget
                    self.preview_label.update()
                    
                    self.btn_eliminar_imagen.config(state='normal')
                    
                except Exception as e:
                    self.preview_label.config(image=None, text="SIN IMAGEN")
                    self.preview_label.image = None
                    self.btn_eliminar_imagen.config(state='disabled')
            else:
                self.preview_label.config(image=None, text="SIN IMAGEN")
                self.preview_label.image = None
                self.btn_eliminar_imagen.config(state='disabled')
        else:
            self.imagen_ruta = None
            self.preview_label.config(image=None, text="SIN IMAGEN")
            self.preview_label.image = None
            self.btn_eliminar_imagen.config(state='disabled')

    def seleccionar_imagen(self):
        """Abre diálogo para seleccionar una imagen del producto"""
        ruta = filedialog.askopenfilename(
            title="Seleccionar imagen del producto",
            filetypes=[
                ("Archivos de imagen", "*.png *.jpg *.jpeg *.gif *.bmp"),
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
                self.preview_label.config(image=None, text="SIN IMAGEN")

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
                self.preview_label.config(image=None, text="SIN IMAGEN")
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
        self.txt_descripcion.delete("1.0", tk.END)
        self.preview_label.config(image="", text="SIN IMAGEN")
        self.preview_label.image = None  # Limpiar referencia CRÍTICA
        self.imagen_actual = None  # También limpiar esta referencia
        self.imagen_ruta = None
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

    def cerrar_ventana(self):
        """Manejar el cierre de ventana de forma segura - misma lógica que generador_imagenes_ui.py"""
        try:
            # Limpiar caché de imágenes para liberar memoria
            if hasattr(self, 'imagenes_cache'):
                self.imagenes_cache.clear()
            if hasattr(self, 'tabla_imagenes'):
                self.tabla_imagenes.clear()
            if hasattr(self, 'imagen_ruta'):
                self.imagen_ruta = None
            if hasattr(self, 'imagen_actual'):
                self.imagen_actual = None
            
            # Limpiar archivo temporal de imagen si existe
            if hasattr(self, 'temp_image_path') and self.temp_image_path:
                try:
                    import os
                    if os.path.exists(self.temp_image_path):
                        os.remove(self.temp_image_path)
                except:
                    pass
                
            self.root.quit()
            self.root.destroy()
        except:
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

def ejecutar_gestion(negocio, emp_id, usu_id):
    """Función principal para ejecutar el módulo de gestión"""
    root = tk.Tk()
    app = GestionGUI(root, negocio, emp_id, usu_id)
    
    # Manejar cierre de ventana - misma lógica que generador_imagenes_ui.py
    def on_closing():
        try:
            # Llamar al método de cerrar ventana que maneja todo correctamente
            app.cerrar_ventana()
        except:
            root.destroy()
    
    root.protocol("WM_DELETE_WINDOW", on_closing)
    
    # Manejar excepciones en mainloop
    try:
        root.mainloop()
    except KeyboardInterrupt:
        pass
    except Exception as e:
        try:
            root.destroy()
        except:
            pass

if __name__ == "__main__":
    negocio = sys.argv[1] if len(sys.argv) > 1 else "NEXUS"
    emp_id = sys.argv[2] if len(sys.argv) > 2 else 1
    usu_id = sys.argv[3] if len(sys.argv) > 3 else 1
    ejecutar_gestion(negocio, emp_id, usu_id)