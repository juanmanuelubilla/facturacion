import tkinter as tk
from tkinter import ttk, messagebox, filedialog
from PIL import Image, ImageTk
import os
import sys

# Importaciones de tu lógica
from productos import obtener_productos, buscar_producto_por_codigo
from db import get_connection

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
        
        # Imprimir en consola para depuración técnica
        print(f"--- SISTEMA INICIADO ---")
        print(f"Negocio: {nombre_negocio} | Empresa ID: {self.empresa_id}")
        print(f"Recargo total cargado: {self.config_fiscal['total']}%")
        
        self.root.title(f"{nombre_negocio.upper()} - Gestión de Inventario")
        self.root.geometry("1450x850")
        
        self.colors = {
            'bg_main': '#121212', 'bg_panel': '#1e1e1e', 'accent': '#00a8ff',
            'success': '#00db84', 'danger': '#ff4757', 'text_main': '#ffffff',
            'text_dim': '#a0a0a0', 'border': '#333333'
        }

        self.imagenes_cache = {}
        self.imagen_ruta = None
        self.modo_formulario = "NUEVO"
        self.orden_asc = {col: False for col in ("codigo", "nombre", "costo", "precio", "stock")}
        
        self.configurar_estilo()
        self.crear_widgets(nombre_negocio)
        self.panel_form.grid_remove() 
        self.cargar_productos()

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
        except Exception as e:
            print(f"Error DB Fiscal: {e}")
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

        self.cols = ("codigo", "nombre", "costo", "precio", "stock")
        self.tabla = ttk.Treeview(tree_container, columns=self.cols, show='tree headings', style="Custom.Treeview")
        self.tabla.heading("#0", text="IMG"); self.tabla.column("#0", width=60)
        for col in self.cols:
            self.tabla.heading(col, text=col.upper(), command=lambda c=col: self.ordenar_columna(c))
            self.tabla.column(col, width=120, anchor="center")
        self.tabla.column("nombre", width=300)
        self.tabla.grid(row=0, column=0, sticky="nsew")
        self.tabla.bind('<<TreeviewSelect>>', self.on_seleccionar_producto)

        # --- FORMULARIO ---
        self.panel_form = tk.Frame(self.main_container, bg=self.colors['bg_panel'], highlightbackground=self.colors['border'], highlightthickness=1, padx=25)
        self.panel_form.grid(row=0, column=1, sticky="nsew")

        tk.Label(self.panel_form, text="EDICIÓN", font=('Segoe UI', 13, 'bold'), bg=self.colors['bg_panel'], fg=self.colors['accent']).pack(pady=(20, 5))

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

        tk.Label(self.panel_form, text="DESCRIPCIÓN", bg=self.colors['bg_panel'], fg=self.colors['text_dim']).pack(anchor="w", pady=(5,0))
        self.txt_descripcion = tk.Text(self.panel_form, height=3, bg="#252525", fg="white")
        self.txt_descripcion.pack(fill=tk.X, pady=5)

        self.preview_label = tk.Label(self.panel_form, text="SIN IMAGEN", bg="#252525", height=5)
        self.preview_label.pack(fill=tk.X, pady=10)
        tk.Button(self.panel_form, text="FOTO", command=self.seleccionar_imagen).pack(fill=tk.X)

        tk.Button(self.panel_form, text="GUARDAR", bg=self.colors['success'], fg="white", command=self.guardar_cambios, pady=10).pack(fill=tk.X, side=tk.BOTTOM, pady=20)
        self.btn_del = tk.Button(self.panel_form, text="ELIMINAR", bg=self.colors['danger'], fg="white", command=self.eliminar_producto)
        self.btn_del.pack(fill=tk.X, side=tk.BOTTOM)

    def cargar_productos(self, filtro=None):
        for item in self.tabla.get_children(): self.tabla.delete(item)
        productos = obtener_productos(self.empresa_id)
        for p in productos:
            sku, nom = str(p.get("codigo", "")), str(p.get("nombre", ""))
            if filtro and filtro not in sku.lower() and filtro not in nom.lower(): continue
            icono = self.obtener_icono(p.get("imagen"))
            self.tabla.insert("", tk.END, image=icono if icono else "",
                values=(sku, nom, f"${float(p.get('costo', 0)):.2f}", f"${float(p.get('precio', 0)):.2f}", p.get('stock', 0)))

    def on_seleccionar_producto(self, event):
        sel = self.tabla.selection()
        if not sel: return
        p = buscar_producto_por_codigo(self.tabla.set(sel[0], "codigo"), self.empresa_id)
        if p:
            self.modo_formulario = "EDITAR"; self.llenar_formulario(p); self.panel_form.grid()

    def guardar_cambios(self):
        try:
            d = {k: v.get() for k, v in self.entries.items()}
            desc = self.txt_descripcion.get("1.0", tk.END).strip()
            conn = get_connection()
            with conn.cursor() as cursor:
                if self.modo_formulario == "NUEVO":
                    cursor.execute("INSERT INTO productos (codigo, nombre, descripcion, precio, costo, stock, imagen, ultimo_usuario_id, empresa_id, activo) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,1)", 
                                    (d['codigo'], d['nombre'], desc, d['precio'], d['costo'], d['stock'], self.imagen_ruta, self.usuario_id, self.empresa_id))
                else:
                    cursor.execute("UPDATE productos SET nombre=%s, descripcion=%s, precio=%s, costo=%s, stock=%s, imagen=%s, ultimo_usuario_id=%s WHERE codigo=%s AND empresa_id=%s", 
                                    (d['nombre'], desc, d['precio'], d['costo'], d['stock'], self.imagen_ruta, self.usuario_id, d['codigo'], self.empresa_id))
            conn.commit(); conn.close()
            messagebox.showinfo("OK", "Guardado"); self.cargar_productos(); self.panel_form.grid_remove()
        except Exception as e: messagebox.showerror("Error", str(e))

    def eliminar_producto(self):
        if messagebox.askyesno("Confirmar", "¿Baja?"):
            conn = get_connection()
            with conn.cursor() as cursor:
                cursor.execute("UPDATE productos SET activo=0 WHERE codigo=%s AND empresa_id=%s", (self.entries['codigo'].get(), self.empresa_id))
            conn.commit(); conn.close(); self.cargar_productos(); self.panel_form.grid_remove()

    def seleccionar_imagen(self):
        ruta = filedialog.askopenfilename()
        if ruta:
            self.imagen_ruta = ruta
            img = Image.open(ruta).resize((150, 150), Image.Resampling.LANCZOS)
            photo = ImageTk.PhotoImage(img); self.preview_label.config(image=photo, text=""); self.preview_label.image = photo

    def obtener_icono(self, ruta):
        if not ruta or not os.path.exists(ruta): return None
        try:
            img = Image.open(ruta).resize((32, 32), Image.Resampling.LANCZOS)
            return ImageTk.PhotoImage(img)
        except: return None

    def llenar_formulario(self, p):
        self.limpiar_formulario()
        self.entries['codigo'].insert(0, p["codigo"]); self.entries['codigo'].config(state='readonly')
        self.entries['nombre'].insert(0, p["nombre"])
        self.entries['costo'].insert(0, p["costo"])
        self.entries['precio'].insert(0, p["precio"])
        self.entries['stock'].insert(0, p["stock"])
        self.txt_descripcion.insert("1.0", p.get("descripcion") or "")
        if p.get("imagen") and os.path.exists(p["imagen"]):
            self.imagen_ruta = p["imagen"]
            img = Image.open(p["imagen"]).resize((150, 150), Image.Resampling.LANCZOS)
            ph = ImageTk.PhotoImage(img); self.preview_label.config(image=ph, text=""); self.preview_label.image = ph

    def preparar_nuevo_producto(self):
        self.modo_formulario = "NUEVO"; self.limpiar_formulario()
        self.entries['codigo'].config(state='normal'); self.panel_form.grid()

    def limpiar_formulario(self):
        for e in self.entries.values(): e.delete(0, tk.END)
        self.txt_descripcion.delete("1.0", tk.END); self.preview_label.config(image="", text="SIN IMAGEN"); self.imagen_ruta = None

    def ordenar_columna(self, col):
        datos = [(self.tabla.set(item, col), item) for item in self.tabla.get_children('')]
        self.orden_asc[col] = not self.orden_asc[col]
        datos.sort(reverse=not self.orden_asc[col])
        for index, (val, item) in enumerate(datos): self.tabla.move(item, '', index)

if __name__ == "__main__":
    negocio = sys.argv[1] if len(sys.argv) > 1 else "NEXUS"
    emp_id = sys.argv[2] if len(sys.argv) > 2 else 1
    usu_id = sys.argv[3] if len(sys.argv) > 3 else 1
    root = tk.Tk(); app = GestionGUI(root, negocio, emp_id, usu_id); root.mainloop()