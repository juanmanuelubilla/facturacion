import tkinter as tk
from tkinter import ttk, messagebox, filedialog
from PIL import Image, ImageTk
import os
from datetime import datetime

from productos import (
    obtener_productos,
    crear_producto,
    buscar_producto_por_codigo
)
from db import get_connection


class GestionGUI:
    def __init__(self, root):
        self.root = root
        self.root.title("Sistema de Gestión de Productos")
        self.root.geometry("1300x750")
        
        # Variables
        self.producto_editando = None
        self.imagen_ruta = None
        self.imagen_actual = None
        self.imagenes_cache = {}  # Cache de imágenes para evitar recargar
        
        # Configurar estilo
        self.configurar_estilo()
        
        # Crear interfaz
        self.crear_widgets()
        
        # Cargar productos
        self.cargar_productos()
        
    def configurar_estilo(self):
        """Configurar estilo y colores"""
        estilo = ttk.Style()
        estilo.theme_use('clam')
        
        # Colores personalizados
        estilo.configure('TButton', font=('Arial', 10), padding=5)
        estilo.configure('TLabel', font=('Arial', 10))
        estilo.configure('TEntry', font=('Arial', 10))
        estilo.configure('Treeview', font=('Arial', 9), rowheight=30)
        estilo.configure('Treeview.Heading', font=('Arial', 10, 'bold'))
        
        # Botones especiales
        estilo.configure('Crear.TButton', background='#4CAF50', foreground='white')
        estilo.configure('Editar.TButton', background='#2196F3', foreground='white')
        estilo.configure('Eliminar.TButton', background='#f44336', foreground='white')
        
    def crear_widgets(self):
        """Crear todos los widgets de la interfaz"""
        
        # Frame principal
        main_frame = ttk.Frame(self.root, padding="10")
        main_frame.grid(row=0, column=0, sticky=(tk.W, tk.E, tk.N, tk.S))
        
        # Configurar grid
        self.root.columnconfigure(0, weight=1)
        self.root.rowconfigure(0, weight=1)
        main_frame.columnconfigure(0, weight=3)  # Tabla
        main_frame.columnconfigure(1, weight=2)  # Formulario
        main_frame.rowconfigure(0, weight=1)
        
        # ========== PANEL IZQUIERDO - TABLA ==========
        panel_tabla = ttk.Frame(main_frame)
        panel_tabla.grid(row=0, column=0, sticky=(tk.W, tk.E, tk.N, tk.S), padx=(0, 5))
        
        # Buscador
        frame_busqueda = ttk.Frame(panel_tabla)
        frame_busqueda.grid(row=0, column=0, sticky=(tk.W, tk.E), pady=(0, 10))
        
        ttk.Label(frame_busqueda, text="🔍 Buscar:").pack(side=tk.LEFT, padx=(0, 5))
        self.buscador = ttk.Entry(frame_busqueda, font=('Arial', 10))
        self.buscador.pack(side=tk.LEFT, fill=tk.X, expand=True, padx=(0, 5))
        self.buscador.bind('<KeyRelease>', self.buscar_productos)
        
        ttk.Button(frame_busqueda, text="Limpiar", command=self.limpiar_buscador).pack(side=tk.LEFT)
        
        # Tabla de productos
        frame_tabla = ttk.Frame(panel_tabla)
        frame_tabla.grid(row=1, column=0, sticky=(tk.W, tk.E, tk.N, tk.S))
        
        # Scrollbars
        scroll_y = ttk.Scrollbar(frame_tabla)
        scroll_y.pack(side=tk.RIGHT, fill=tk.Y)
        
        scroll_x = ttk.Scrollbar(frame_tabla, orient=tk.HORIZONTAL)
        scroll_x.pack(side=tk.BOTTOM, fill=tk.X)
        
        # Treeview (tabla) con columna de imagen
        columnas = ("Imagen", "Código", "Nombre", "Precio", "Stock", "Estado")
        self.tabla = ttk.Treeview(frame_tabla, columns=columnas, show='headings',
                                  yscrollcommand=scroll_y.set, xscrollcommand=scroll_x.set,
                                  height=20)
        
        scroll_y.config(command=self.tabla.yview)
        scroll_x.config(command=self.tabla.xview)
        
        # Configurar columnas
        self.tabla.heading("Imagen", text="Imagen")
        self.tabla.heading("Código", text="Código")
        self.tabla.heading("Nombre", text="Nombre")
        self.tabla.heading("Precio", text="Precio")
        self.tabla.heading("Stock", text="Stock")
        self.tabla.heading("Estado", text="Estado")
        
        self.tabla.column("Imagen", width=60, anchor="center")
        self.tabla.column("Código", width=100)
        self.tabla.column("Nombre", width=250)
        self.tabla.column("Precio", width=100)
        self.tabla.column("Stock", width=80)
        self.tabla.column("Estado", width=100)
        
        self.tabla.pack(fill=tk.BOTH, expand=True)
        
        # Bind selección
        self.tabla.bind('<<TreeviewSelect>>', self.on_seleccionar_producto)
        
        # ========== PANEL DERECHO - FORMULARIO ==========
        panel_form = ttk.Frame(main_frame, relief=tk.RIDGE, padding="10")
        panel_form.grid(row=0, column=1, sticky=(tk.W, tk.E, tk.N, tk.S), padx=(5, 0))
        
        # Título
        ttk.Label(panel_form, text="📝 FORMULARIO DE PRODUCTO", 
                 font=('Arial', 12, 'bold')).grid(row=0, column=0, columnspan=2, pady=(0, 15))
        
        # Campos del formulario
        campos = [
            ("Código:", "codigo", 1),
            ("Nombre:", "nombre", 2),
            ("Descripción:", "descripcion", 3),
            ("Precio:", "precio", 4),
            ("Costo:", "costo", 5),
            ("Stock:", "stock", 6),
        ]
        
        self.entries = {}
        
        for label, key, row in campos:
            ttk.Label(panel_form, text=label).grid(row=row, column=0, sticky=tk.W, pady=5)
            if key == "descripcion":
                entry = tk.Text(panel_form, height=4, width=30, font=('Arial', 10))
                entry.grid(row=row, column=1, sticky=(tk.W, tk.E), pady=5, padx=(10, 0))
                self.entries[key] = entry
            else:
                entry = ttk.Entry(panel_form, font=('Arial', 10), width=30)
                entry.grid(row=row, column=1, sticky=(tk.W, tk.E), pady=5, padx=(10, 0))
                self.entries[key] = entry
        
        # Imagen
        ttk.Label(panel_form, text="Imagen:").grid(row=7, column=0, sticky=tk.W, pady=5)
        frame_imagen = ttk.Frame(panel_form)
        frame_imagen.grid(row=7, column=1, sticky=(tk.W, tk.E), pady=5, padx=(10, 0))
        
        self.label_imagen = ttk.Label(frame_imagen, text="Sin imagen", relief=tk.SUNKEN, width=30)
        self.label_imagen.pack(side=tk.LEFT, fill=tk.X, expand=True)
        
        ttk.Button(frame_imagen, text="📁 Seleccionar", command=self.seleccionar_imagen).pack(side=tk.RIGHT, padx=(5, 0))
        
        # Vista previa de la imagen seleccionada
        ttk.Label(panel_form, text="Vista previa:").grid(row=8, column=0, sticky=tk.W, pady=5)
        self.preview_label = ttk.Label(panel_form, text="No hay imagen", relief=tk.SUNKEN)
        self.preview_label.grid(row=8, column=1, sticky=(tk.W, tk.E), pady=5, padx=(10, 0))
        
        # Botones de acción
        frame_botones = ttk.Frame(panel_form)
        frame_botones.grid(row=9, column=0, columnspan=2, pady=20)
        
        self.btn_crear = ttk.Button(frame_botones, text="📝 CREAR", command=self.crear_producto)
        self.btn_crear.pack(side=tk.LEFT, padx=5)
        
        self.btn_editar = ttk.Button(frame_botones, text="✏️ EDITAR", command=self.editar_producto, state=tk.DISABLED)
        self.btn_editar.pack(side=tk.LEFT, padx=5)
        
        self.btn_eliminar = ttk.Button(frame_botones, text="🗑️ ELIMINAR", command=self.eliminar_producto, state=tk.DISABLED)
        self.btn_eliminar.pack(side=tk.LEFT, padx=5)
        
        self.btn_limpiar = ttk.Button(frame_botones, text="🧹 LIMPIAR", command=self.limpiar_formulario)
        self.btn_limpiar.pack(side=tk.LEFT, padx=5)
        
        # Barra de estado
        self.status_bar = ttk.Label(self.root, text="Listo", relief=tk.SUNKEN)
        self.status_bar.grid(row=1, column=0, sticky=(tk.W, tk.E))
    
    def redimensionar_imagen(self, ruta, tamaño=(24, 24)):
        """Redimensionar imagen para mostrar en la tabla"""
        try:
            if ruta and os.path.exists(ruta):
                # Usar cache si ya existe
                if ruta in self.imagenes_cache:
                    return self.imagenes_cache[ruta]
                
                img = Image.open(ruta)
                img = img.resize(tamaño, Image.Resampling.LANCZOS)
                photo = ImageTk.PhotoImage(img)
                self.imagenes_cache[ruta] = photo
                return photo
        except Exception as e:
            print(f"Error cargando imagen {ruta}: {e}")
        return None
    
    def cargar_productos(self):
        """Cargar productos en la tabla con imágenes"""
        # Limpiar tabla
        for item in self.tabla.get_children():
            self.tabla.delete(item)
        
        productos = obtener_productos()
        
        for p in productos:
            estado = "🟢 Activo" if p["activo"] else "🔴 Inactivo"
            
            # Cargar imagen
            imagen = None
            ruta_imagen = p.get("imagen")
            if ruta_imagen and os.path.exists(ruta_imagen):
                imagen = self.redimensionar_imagen(ruta_imagen)
            
            # Insertar fila
            item_id = self.tabla.insert("", tk.END, values=(
                "",  # Placeholder para imagen
                p["codigo"],
                p["nombre"],
                f"${float(p['precio']):.2f}",
                str(p["stock"]),
                estado
            ))
            
            # Si hay imagen, asignarla a la celda
            if imagen:
                self.tabla.set(item_id, column="Imagen", value="🖼️")
                # Guardar referencia para evitar garbage collection
                if not hasattr(self, 'imagenes_referencias'):
                    self.imagenes_referencias = {}
                self.imagenes_referencias[item_id] = imagen
        
        self.status_bar.config(text=f"Total: {len(productos)} productos")
    
    def buscar_productos(self, event=None):
        """Buscar productos por código o nombre"""
        texto = self.buscador.get().lower()
        
        if not texto:
            self.cargar_productos()
            return
        
        # Limpiar tabla
        for item in self.tabla.get_children():
            self.tabla.delete(item)
        
        productos = obtener_productos()
        encontrados = 0
        
        for p in productos:
            if texto in p["codigo"].lower() or texto in p["nombre"].lower():
                estado = "🟢 Activo" if p["activo"] else "🔴 Inactivo"
                
                # Cargar imagen
                imagen = None
                ruta_imagen = p.get("imagen")
                if ruta_imagen and os.path.exists(ruta_imagen):
                    imagen = self.redimensionar_imagen(ruta_imagen)
                
                item_id = self.tabla.insert("", tk.END, values=(
                    "",  # Placeholder para imagen
                    p["codigo"],
                    p["nombre"],
                    f"${float(p['precio']):.2f}",
                    str(p["stock"]),
                    estado
                ))
                
                if imagen:
                    self.tabla.set(item_id, column="Imagen", value="🖼️")
                    if not hasattr(self, 'imagenes_referencias'):
                        self.imagenes_referencias = {}
                    self.imagenes_referencias[item_id] = imagen
                
                encontrados += 1
        
        self.status_bar.config(text=f"Encontrados: {encontrados} productos")
    
    def limpiar_buscador(self):
        """Limpiar buscador y recargar todos los productos"""
        self.buscador.delete(0, tk.END)
        self.cargar_productos()
    
    def on_seleccionar_producto(self, event):
        """Cuando se selecciona un producto en la tabla"""
        seleccion = self.tabla.selection()
        if not seleccion:
            return
        
        item = self.tabla.item(seleccion[0])
        codigo = item['values'][1]  # Columna 1 es código
        
        producto = buscar_producto_por_codigo(codigo)
        
        if producto:
            self.producto_editando = producto
            self.llenar_formulario(producto)
            self.btn_editar.config(state=tk.NORMAL)
            self.btn_eliminar.config(state=tk.NORMAL)
            self.btn_crear.config(state=tk.NORMAL)
    
    def llenar_formulario(self, producto):
        """Llenar formulario con datos del producto"""
        self.entries['codigo'].delete(0, tk.END)
        self.entries['codigo'].insert(0, str(producto.get("codigo", "")))
        
        self.entries['nombre'].delete(0, tk.END)
        self.entries['nombre'].insert(0, str(producto.get("nombre", "")))
        
        self.entries['descripcion'].delete(1.0, tk.END)
        self.entries['descripcion'].insert(1.0, str(producto.get("descripcion", "")))
        
        self.entries['precio'].delete(0, tk.END)
        self.entries['precio'].insert(0, str(producto.get("precio", "0")))
        
        self.entries['costo'].delete(0, tk.END)
        self.entries['costo'].insert(0, str(producto.get("costo", "0")))
        
        self.entries['stock'].delete(0, tk.END)
        self.entries['stock'].insert(0, str(producto.get("stock", "0")))
        
        # Mostrar imagen
        imagen = producto.get("imagen")
        if imagen and os.path.exists(imagen):
            self.label_imagen.config(text=os.path.basename(imagen))
            self.imagen_ruta = imagen
            # Mostrar vista previa
            self.mostrar_vista_previa(imagen)
        else:
            self.label_imagen.config(text="Sin imagen")
            self.imagen_ruta = None
            self.preview_label.config(text="No hay imagen", image="")
    
    def mostrar_vista_previa(self, ruta):
        """Mostrar vista previa de la imagen en el formulario"""
        try:
            img = Image.open(ruta)
            img = img.resize((100, 100), Image.Resampling.LANCZOS)
            photo = ImageTk.PhotoImage(img)
            self.preview_label.config(image=photo, text="")
            self.preview_label.image = photo  # Guardar referencia
        except Exception as e:
            self.preview_label.config(text=f"Error: {e}", image="")
    
    def limpiar_formulario(self):
        """Limpiar todos los campos del formulario"""
        self.entries['codigo'].delete(0, tk.END)
        self.entries['nombre'].delete(0, tk.END)
        self.entries['descripcion'].delete(1.0, tk.END)
        self.entries['precio'].delete(0, tk.END)
        self.entries['costo'].delete(0, tk.END)
        self.entries['stock'].delete(0, tk.END)
        
        self.label_imagen.config(text="Sin imagen")
        self.preview_label.config(text="No hay imagen", image="")
        self.imagen_ruta = None
        self.producto_editando = None
        
        self.btn_editar.config(state=tk.DISABLED)
        self.btn_eliminar.config(state=tk.DISABLED)
        self.entries['codigo'].config(state=tk.NORMAL)
    
    def seleccionar_imagen(self):
        """Seleccionar una imagen del sistema"""
        archivo = filedialog.askopenfilename(
            title="Seleccionar imagen",
            filetypes=[
                ("Imágenes", "*.jpg *.jpeg *.png *.gif *.bmp *.ico"),
                ("Todos los archivos", "*.*")
            ]
        )
        
        if archivo:
            self.imagen_ruta = archivo
            self.label_imagen.config(text=os.path.basename(archivo))
            self.mostrar_vista_previa(archivo)
    
    def crear_producto(self):
        """Crear un nuevo producto"""
        try:
            codigo = self.entries['codigo'].get().strip()
            nombre = self.entries['nombre'].get().strip()
            descripcion = self.entries['descripcion'].get(1.0, tk.END).strip()
            precio = float(self.entries['precio'].get())
            costo = float(self.entries['costo'].get())
            stock = int(self.entries['stock'].get())
            
            if not codigo or not nombre:
                messagebox.showerror("Error", "Código y nombre son obligatorios")
                return
            
            # Copiar imagen a carpeta del proyecto
            imagen_db = None
            if self.imagen_ruta:
                import shutil
                os.makedirs("imagenes", exist_ok=True)
                ext = os.path.splitext(self.imagen_ruta)[1]
                nombre_imagen = f"{codigo}{ext}"
                destino = os.path.join("imagenes", nombre_imagen)
                shutil.copy2(self.imagen_ruta, destino)
                imagen_db = destino
            
            crear_producto(codigo, nombre, precio, costo, stock, imagen_db, descripcion)
            
            messagebox.showinfo("Éxito", "Producto creado exitosamente")
            self.limpiar_formulario()
            self.cargar_productos()
            self.status_bar.config(text="✅ Producto creado")
            
        except ValueError:
            messagebox.showerror("Error", "Precio, costo y stock deben ser números")
        except Exception as e:
            messagebox.showerror("Error", f"Error al crear: {str(e)}")
    
    def editar_producto(self):
        """Editar el producto seleccionado"""
        if not self.producto_editando:
            messagebox.showwarning("Advertencia", "Seleccione un producto primero")
            return
        
        try:
            nombre = self.entries['nombre'].get().strip()
            descripcion = self.entries['descripcion'].get(1.0, tk.END).strip()
            precio = float(self.entries['precio'].get())
            costo = float(self.entries['costo'].get())
            stock = int(self.entries['stock'].get())
            codigo = self.entries['codigo'].get().strip()
            
            if not nombre:
                messagebox.showerror("Error", "Nombre es obligatorio")
                return
            
            # Manejar imagen
            imagen_db = self.producto_editando.get("imagen")
            if self.imagen_ruta and self.imagen_ruta != imagen_db:
                import shutil
                os.makedirs("imagenes", exist_ok=True)
                ext = os.path.splitext(self.imagen_ruta)[1]
                nombre_imagen = f"{codigo}{ext}"
                destino = os.path.join("imagenes", nombre_imagen)
                shutil.copy2(self.imagen_ruta, destino)
                imagen_db = destino
            
            conn = get_connection()
            with conn.cursor() as cursor:
                cursor.execute("""
                    UPDATE productos 
                    SET nombre=%s, descripcion=%s, precio=%s, costo=%s, stock=%s, imagen=%s
                    WHERE codigo=%s
                """, (nombre, descripcion, precio, costo, stock, imagen_db, codigo))
            conn.commit()
            
            # Limpiar cache de imágenes
            self.imagenes_cache.clear()
            
            messagebox.showinfo("Éxito", "Producto actualizado exitosamente")
            self.limpiar_formulario()
            self.cargar_productos()
            self.status_bar.config(text="✅ Producto actualizado")
            
        except ValueError:
            messagebox.showerror("Error", "Precio, costo y stock deben ser números")
        except Exception as e:
            messagebox.showerror("Error", f"Error al editar: {str(e)}")
    
    def eliminar_producto(self):
        """Eliminar (desactivar) el producto seleccionado"""
        if not self.producto_editando:
            messagebox.showwarning("Advertencia", "Seleccione un producto primero")
            return
        
        codigo = self.producto_editando["codigo"]
        
        if messagebox.askyesno("Confirmar", f"¿Está seguro de eliminar {codigo}?"):
            conn = get_connection()
            with conn.cursor() as cursor:
                cursor.execute("""
                    UPDATE productos SET activo=0 WHERE codigo=%s
                """, (codigo,))
            conn.commit()
            
            # Limpiar cache
            self.imagenes_cache.clear()
            
            messagebox.showinfo("Éxito", "Producto eliminado exitosamente")
            self.limpiar_formulario()
            self.cargar_productos()
            self.status_bar.config(text=f"✅ {codigo} eliminado")


if __name__ == "__main__":
    root = tk.Tk()
    app = GestionGUI(root)
    root.mainloop()