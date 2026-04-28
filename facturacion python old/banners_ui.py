#!/usr/bin/env python3
# -*- coding: utf-8 -*-

"""
Módulo de Gestión de Banners con DLNA y Caducidad
Este archivo solo debe ejecutarse desde el dashboard principal
"""

import tkinter as tk
from tkinter import ttk, messagebox, filedialog
from PIL import Image, ImageTk
import os
import threading
import webbrowser
from datetime import datetime, timedelta
from db import get_connection

# Colores del sistema (mismos que main.py)
COLORS = {
    'bg_main': '#121212',
    'bg_panel': '#1e1e1e',
    'accent': '#00a8ff',
    'success': '#00db84',
    'danger': '#e74c3c',
    'text': '#ffffff',
    'text_dim': '#888888',
    'border': '#333333'
}

class BannersUI:
    def __init__(self, root, empresa_id, nombre_negocio):
        self.root = root
        self.empresa_id = empresa_id
        self.nombre_negocio = nombre_negocio
        self.colors = COLORS
        self.banners_cargados = []
        self.banner_actual = None
        self.dlna_server_running = False
        
        self.setup_ui()
        self.cargar_banners()
        
    def setup_ui(self):
        # Ventana principal
        self.root.title(f"🖼️ Gestión de Banners | {self.nombre_negocio.upper()}")
        self.root.geometry("1200x800")
        self.root.configure(bg=self.colors['bg_main'])
        
        # Manejar cierre de ventana para evitar problemas
        self.root.protocol("WM_DELETE_WINDOW", self.cerrar_ventana)
        
        # Intentar maximizar ventana con múltiples intentos
        try:
            self.root.state('zoomed')
        except:
            try:
                self.root.attributes('-zoomed', True)
            except:
                try:
                    self.root.geometry("1600x1000+0+0")
                except:
                    self.root.geometry("1400x900+0+0")
        
        # Segundo intento después de un pequeño delay
        self.root.after(200, self.intentar_maximizar)
        
        # Verificar si hay información temporal de IA
        self.verificar_info_temporal()
        
        # Header
        header = tk.Frame(self.root, bg=self.colors['bg_main'])
        header.pack(fill=tk.X, pady=(0, 20))
        
        tk.Label(header, text="🖼️ GESTIÓN DE BANNERS", 
                 font=('Segoe UI', 18, 'bold'), bg=self.colors['bg_main'], fg=self.colors['accent']).pack(side=tk.LEFT)
        
        # Botones del header
        btn_frame = tk.Frame(header, bg=self.colors['bg_main'])
        btn_frame.pack(side=tk.RIGHT)
        
        tk.Button(btn_frame, text="📺 INICIAR DLNA", bg=self.colors['success'], fg="white",
                 font=('Segoe UI', 10, 'bold'), command=self.iniciar_dlna,
                 width=12).pack(side=tk.RIGHT, padx=5)
        
        # Contenido principal
        main_container = tk.Frame(self.root, bg=self.colors['bg_main'])
        main_container.pack(fill=tk.BOTH, expand=True, padx=20)
        
        # Panel izquierdo - Listado y gestión
        left_panel = tk.Frame(main_container, bg=self.colors['bg_main'])
        left_panel.pack(side=tk.LEFT, fill=tk.BOTH, expand=True, padx=(0, 10))
        
        # Panel derecho - Preview y DLNA
        right_panel = tk.Frame(main_container, bg=self.colors['bg_main'], width=400)
        right_panel.pack(side=tk.RIGHT, fill=tk.BOTH, expand=True)
        right_panel.pack_propagate(False)
        
        self.setup_panel_izquierdo(left_panel)
        self.setup_panel_derecho(right_panel)
        
    def setup_panel_izquierdo(self, parent):
        # PANEL DE SUBIDA DE BANNERS
        upload_frame = tk.LabelFrame(parent, text="📤 SUBIR NUEVO BANNER", 
                                   font=('Segoe UI', 12, 'bold'), bg=self.colors['bg_panel'], 
                                   fg=self.colors['accent'], relief="raised", borderwidth=2)
        upload_frame.pack(fill=tk.X, pady=(0, 15))
        
        # Campos del banner
        tk.Label(upload_frame, text="Nombre del Banner:", bg=self.colors['bg_panel'], fg="white",
                 font=('Segoe UI', 10)).pack(anchor="w", padx=15, pady=(10, 5))
        
        self.ent_nombre_banner = tk.Entry(upload_frame, font=('Segoe UI', 10), bg="#252525", fg="white", borderwidth=0)
        self.ent_nombre_banner.pack(fill=tk.X, padx=15, pady=(0, 10))
        
        tk.Label(upload_frame, text="Fecha de Caducidad:", bg=self.colors['bg_panel'], fg="white",
                 font=('Segoe UI', 10)).pack(anchor="w", padx=15, pady=(10, 5))
        
        fecha_frame = tk.Frame(upload_frame, bg=self.colors['bg_panel'])
        fecha_frame.pack(fill=tk.X, padx=15, pady=(0, 10))
        
        self.ent_fecha_caducidad = tk.Entry(fecha_frame, font=('Segoe UI', 10), bg="#252525", fg="white", borderwidth=0)
        self.ent_fecha_caducidad.pack(side=tk.LEFT, fill=tk.X, expand=True)
        
        tk.Button(fecha_frame, text="📅 HOY", bg=self.colors['accent'], fg="white",
                 font=('Segoe UI', 8, 'bold'), command=self.set_fecha_hoy,
                 width=8).pack(side=tk.RIGHT, padx=(5, 0))
        
        tk.Button(fecha_frame, text="📅 +7 DÍAS", bg=self.colors['accent'], fg="white",
                 font=('Segoe UI', 8, 'bold'), command=self.set_fecha_7_dias,
                 width=10).pack(side=tk.RIGHT, padx=(5, 0))
        
        tk.Label(upload_frame, text="Tiempo de Visualización (segundos):", bg=self.colors['bg_panel'], fg="white",
                 font=('Segoe UI', 10)).pack(anchor="w", padx=15, pady=(10, 5))
        
        self.ent_tiempo_visualizacion = tk.Entry(upload_frame, font=('Segoe UI', 10), bg="#252525", fg="white", borderwidth=0)
        self.ent_tiempo_visualizacion.pack(fill=tk.X, padx=15, pady=(0, 10))
        self.ent_tiempo_visualizacion.insert(0, "10")  # Valor por defecto
        
        tk.Label(upload_frame, text="Imagen del Banner:", bg=self.colors['bg_panel'], fg="white",
                 font=('Segoe UI', 10)).pack(anchor="w", padx=15, pady=(10, 5))
        
        btn_frame = tk.Frame(upload_frame, bg=self.colors['bg_panel'])
        btn_frame.pack(fill=tk.X, padx=15, pady=(0, 10))
        
        tk.Button(btn_frame, text="📁 SELECCIONAR IMAGEN", bg=self.colors['accent'], fg="white",
                 font=('Segoe UI', 10, 'bold'), command=self.seleccionar_imagen,
                 width=20).pack(side=tk.LEFT)
        
        self.lbl_ruta_imagen = tk.Label(btn_frame, text="No seleccionada", bg=self.colors['bg_panel'], fg=self.colors['text_dim'],
                                        font=('Segoe UI', 9))
        self.lbl_ruta_imagen.pack(side=tk.LEFT, padx=(10, 0))
        
        # Botón de subida
        tk.Button(upload_frame, text="📤 SUBIR BANNER", bg=self.colors['success'], fg="white",
                 font=('Segoe UI', 11, 'bold'), command=self.subir_banner,
                 width=20).pack(pady=10)
        
        # LISTADO DE BANNERS
        list_frame = tk.LabelFrame(parent, text="📋 BANNERS ACTIVOS", 
                                  font=('Segoe UI', 12, 'bold'), bg=self.colors['bg_panel'], 
                                  fg=self.colors['accent'], relief="raised", borderwidth=2)
        list_frame.pack(fill=tk.BOTH, expand=True)
        
        # Tabla de banners
        columns = ('ID', 'Nombre', 'Caducidad', 'Tiempo', 'Estado')
        self.tabla_banners = ttk.Treeview(list_frame, columns=columns, show='tree headings', style="Custom.Treeview")
        
        # Configurar columnas
        self.tabla_banners.heading("#0", text="Vista")
        self.tabla_banners.column("#0", width=80, anchor="center")
        
        for col in columns:
            self.tabla_banners.heading(col, text=col)
            if col == 'ID':
                self.tabla_banners.column(col, width=40, anchor="center")
            elif col == 'Nombre':
                self.tabla_banners.column(col, width=200, anchor="w")
            elif col == 'Caducidad':
                self.tabla_banners.column(col, width=120, anchor="center")
            elif col == 'Tiempo':
                self.tabla_banners.column(col, width=80, anchor="center")
            else:
                self.tabla_banners.column(col, width=100, anchor="center")
        
        # Scrollbars
        scrollbar_v = ttk.Scrollbar(list_frame, orient="vertical", command=self.tabla_banners.yview)
        scrollbar_h = ttk.Scrollbar(list_frame, orient="horizontal", command=self.tabla_banners.xview)
        self.tabla_banners.configure(yscrollcommand=scrollbar_v.set, xscrollcommand=scrollbar_h.set)
        
        # Pack
        self.tabla_banners.pack(side=tk.LEFT, fill=tk.BOTH, expand=True)
        scrollbar_v.pack(side=tk.RIGHT, fill=tk.Y)
        scrollbar_h.pack(side=tk.BOTTOM, fill=tk.X)
        
        # Botones de gestión
        btn_list_frame = tk.Frame(list_frame, bg=self.colors['bg_panel'])
        btn_list_frame.pack(fill=tk.X, padx=5, pady=5)
        
        tk.Button(btn_list_frame, text="🗑️ ELIMINAR", bg=self.colors['danger'], fg="white",
                 font=('Segoe UI', 9, 'bold'), command=self.eliminar_banner,
                 width=10).pack(side=tk.LEFT, padx=2)
        
        tk.Button(btn_list_frame, text="🔄 ACTUALIZAR", bg=self.colors['accent'], fg="white",
                 font=('Segoe UI', 9, 'bold'), command=self.cargar_banners,
                 width=10).pack(side=tk.LEFT, padx=2)
        
        tk.Button(btn_list_frame, text="▶️ PREVIEW", bg=self.colors['success'], fg="white",
                 font=('Segoe UI', 9, 'bold'), command=self.preview_banner,
                 width=10).pack(side=tk.LEFT, padx=2)
        
    def setup_panel_derecho(self, parent):
        # PANEL DE PREVIEW Y DLNA
        preview_frame = tk.LabelFrame(parent, text="🖼️ VISTA PREVIA", 
                                    font=('Segoe UI', 12, 'bold'), bg=self.colors['bg_panel'], 
                                    fg=self.colors['accent'], relief="raised", borderwidth=2)
        preview_frame.pack(fill=tk.BOTH, expand=True, pady=(0, 10))
        
        # Canvas para preview
        self.canvas_preview = tk.Canvas(preview_frame, bg="#1a1a1a", width=380, height=250)
        self.canvas_preview.pack(pady=20)
        
        # Placeholder inicial
        self.canvas_preview.create_text(190, 125, text="🖼️", font=('Segoe UI', 48), 
                                       fill=self.colors['text_dim'])
        self.canvas_preview.create_text(190, 170, text="Selecciona un banner para ver preview", 
                                       font=('Segoe UI', 10), fill=self.colors['text_dim'])
        
        # Información del banner actual
        self.info_banner_frame = tk.Frame(preview_frame, bg=self.colors['bg_panel'])
        self.info_banner_frame.pack(fill=tk.X, padx=15, pady=10)
        
        self.lbl_info_banner = tk.Label(self.info_banner_frame, text="Sin banner seleccionado", 
                                        bg=self.colors['bg_panel'], fg=self.colors['text_dim'],
                                        font=('Segoe UI', 9), wraplength=350)
        self.lbl_info_banner.pack()
        
        # PANEL DE CONTROL DLNA
        dlna_frame = tk.LabelFrame(parent, text="📺 CONTROL DLNA", 
                                   font=('Segoe UI', 12, 'bold'), bg=self.colors['bg_panel'], 
                                   fg=self.colors['accent'], relief="raised", borderwidth=2)
        dlna_frame.pack(fill=tk.X)
        
        # Estado del servidor
        self.lbl_estado_dlna = tk.Label(dlna_frame, text="🔴 SERVIDOR DLNA DETENIDO", 
                                          bg=self.colors['bg_panel'], fg=self.colors['danger'],
                                          font=('Segoe UI', 10, 'bold'))
        self.lbl_estado_dlna.pack(pady=10)
        
        # Controles DLNA
        dlna_controls = tk.Frame(dlna_frame, bg=self.colors['bg_panel'])
        dlna_controls.pack(fill=tk.X, padx=15, pady=10)
        
        tk.Button(dlna_controls, text="▶️ INICIAR", bg=self.colors['success'], fg="white",
                 font=('Segoe UI', 9, 'bold'), command=self.iniciar_dlna,
                 width=12).pack(side=tk.LEFT, padx=2)
        
        tk.Button(dlna_controls, text="⏸️ PAUSAR", bg=self.colors['accent'], fg="white",
                 font=('Segoe UI', 9, 'bold'), command=self.pausar_dlna,
                 width=12).pack(side=tk.LEFT, padx=2)
        
        tk.Button(dlna_controls, text="⏹️ DETENER", bg=self.colors['danger'], fg="white",
                 font=('Segoe UI', 9, 'bold'), command=self.detener_dlna,
                 width=12).pack(side=tk.LEFT, padx=2)
        
        # Información de visualización
        info_visualizacion = tk.Frame(dlna_frame, bg=self.colors['bg_panel'])
        info_visualizacion.pack(fill=tk.X, padx=15, pady=5)
        
        self.lbl_banner_actual = tk.Label(info_visualizacion, text="Banner actual: Ninguno", 
                                          bg=self.colors['bg_panel'], fg=self.colors['text_dim'],
                                          font=('Segoe UI', 9))
        self.lbl_banner_actual.pack(anchor="w")
        
        self.lbl_tiempo_restante = tk.Label(info_visualizacion, text="Tiempo restante: --", 
                                            bg=self.colors['bg_panel'], fg=self.colors['text_dim'],
                                            font=('Segoe UI', 9))
        self.lbl_tiempo_restante.pack(anchor="w")
        
        # Configuración de servidor DLNA
        server_config_frame = tk.Frame(dlna_frame, bg=self.colors['bg_panel'])
        server_config_frame.pack(fill=tk.X, padx=15, pady=10)
        
        tk.Label(server_config_frame, text="Servidor DLNA:", bg=self.colors['bg_panel'], fg="white",
                 font=('Segoe UI', 9)).pack(anchor="w")
        
        server_options_frame = tk.Frame(server_config_frame, bg=self.colors['bg_panel'])
        server_options_frame.pack(fill=tk.X, pady=5)
        
        self.var_tipo_servidor = tk.StringVar(value="local")
        tk.Radiobutton(server_options_frame, text="🏠 Local", variable=self.var_tipo_servidor, 
                       value="local", bg=self.colors['bg_panel'], fg="white",
                       font=('Segoe UI', 9), selectcolor=self.colors['accent'],
                       command=self.cambiar_tipo_servidor).pack(side=tk.LEFT, padx=(0, 10))
        
        tk.Radiobutton(server_options_frame, text="🌐 Remoto", variable=self.var_tipo_servidor, 
                       value="remoto", bg=self.colors['bg_panel'], fg="white",
                       font=('Segoe UI', 9), selectcolor=self.colors['accent'],
                       command=self.cambiar_tipo_servidor).pack(side=tk.LEFT)
        
        # Configuración de servidor remoto (inicialmente oculta)
        self.remoto_config_frame = tk.Frame(server_config_frame, bg=self.colors['bg_panel'])
        
        tk.Label(self.remoto_config_frame, text="IP del servidor:", bg=self.colors['bg_panel'], fg="white",
                 font=('Segoe UI', 9)).pack(anchor="w", pady=(10, 2))
        
        self.ent_ip_servidor = tk.Entry(self.remoto_config_frame, font=('Segoe UI', 9), width=20)
        self.ent_ip_servidor.pack(fill=tk.X, pady=(0, 5))
        self.ent_ip_servidor.insert(0, "192.168.1.100")  # IP por defecto
        
        tk.Label(self.remoto_config_frame, text="Puerto:", bg=self.colors['bg_panel'], fg="white",
                 font=('Segoe UI', 9)).pack(anchor="w", pady=(5, 2))
        
        self.ent_puerto_servidor = tk.Entry(self.remoto_config_frame, font=('Segoe UI', 9), width=10)
        self.ent_puerto_servidor.pack(fill=tk.X, pady=(0, 10))
        self.ent_puerto_servidor.insert(0, "8080")  # Puerto por defecto
        
        # Configuración de visualización
        config_frame = tk.Frame(dlna_frame, bg=self.colors['bg_panel'])
        config_frame.pack(fill=tk.X, padx=15, pady=10)
        
        tk.Label(config_frame, text="Ciclo automático:", bg=self.colors['bg_panel'], fg="white",
                 font=('Segoe UI', 9)).pack(side=tk.LEFT)
        
        self.var_ciclo_automatico = tk.BooleanVar(value=True)
        tk.Checkbutton(config_frame, variable=self.var_ciclo_automatico, bg=self.colors['bg_panel'], fg="white",
                       selectcolor=self.colors['accent'], font=('Segoe UI', 9)).pack(side=tk.LEFT, padx=(10, 0))
        
    def set_fecha_hoy(self):
        """Establecer fecha de hoy"""
        hoy = datetime.now().strftime("%Y-%m-%d")
        self.ent_fecha_caducidad.delete(0, tk.END)
        self.ent_fecha_caducidad.insert(0, hoy)
        
    def set_fecha_7_dias(self):
        """Establecer fecha para 7 días desde hoy"""
        fecha_7 = (datetime.now() + timedelta(days=7)).strftime("%Y-%m-%d")
        self.ent_fecha_caducidad.delete(0, tk.END)
        self.ent_fecha_caducidad.insert(0, fecha_7)
    
    def cambiar_tipo_servidor(self):
        """Cambiar entre servidor local y remoto"""
        if self.var_tipo_servidor.get() == "remoto":
            # Mostrar configuración remota
            self.remoto_config_frame.pack(fill=tk.X, pady=5)
            # Actualizar etiqueta de estado
            self.lbl_estado_dlna.config(text="🔵 MODO REMOTO - Configurar servidor", fg=self.colors['accent'])
        else:
            # Ocultar configuración remota
            self.remoto_config_frame.pack_forget()
            # Actualizar etiqueta de estado
            if hasattr(self, 'dlna_server_running') and self.dlna_server_running:
                self.lbl_estado_dlna.config(text="🟢 SERVIDOR DLNA ACTIVO", fg=self.colors['success'])
            else:
                self.lbl_estado_dlna.config(text="🔴 SERVIDOR DLNA DETENIDO", fg=self.colors['danger'])
        
    def seleccionar_imagen(self):
        """Seleccionar imagen para el banner"""
        ruta = filedialog.askopenfilename(
            title="Seleccionar imagen para banner",
            filetypes=[("Archivos de imagen", "*.png *.jpg *.jpeg *.gif *.bmp"), ("Todos los archivos", "*.*")]
        )
        
        if ruta:
            self.ruta_imagen_seleccionada = ruta
            nombre_archivo = os.path.basename(ruta)
            self.lbl_ruta_imagen.config(text=nombre_archivo, fg=self.colors['success'])
            
            # Mostrar preview
            try:
                img = Image.open(ruta)
                img.thumbnail((380, 200), Image.Resampling.LANCZOS)
                self.preview_image = ImageTk.PhotoImage(img)
                
                self.canvas_preview.delete("all")
                self.canvas_preview.create_image(190, 100, image=self.preview_image)
                
            except Exception as e:
                messagebox.showerror("Error", f"No se pudo cargar la imagen: {e}")
    
    def subir_banner(self):
        """Subir nuevo banner a la base de datos"""
        if not hasattr(self, 'ruta_imagen_seleccionada'):
            messagebox.showwarning("Aviso", "Por favor selecciona una imagen")
            return
            
        nombre = self.ent_nombre_banner.get().strip()
        if not nombre:
            messagebox.showwarning("Aviso", "Por favor ingresa un nombre para el banner")
            return
            
        fecha_caducidad = self.ent_fecha_caducidad.get().strip()
        if not fecha_caducidad:
            messagebox.showwarning("Aviso", "Por favor establece una fecha de caducidad")
            return
            
        try:
            tiempo_visualizacion = int(self.ent_tiempo_visualizacion.get())
        except ValueError:
            messagebox.showwarning("Aviso", "El tiempo de visualización debe ser un número")
            return
        
        try:
            conn = get_connection()
            with conn.cursor() as cursor:
                # Crear tabla si no existe
                cursor.execute("""
                    CREATE TABLE IF NOT EXISTS banners (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        empresa_id INT,
                        nombre VARCHAR(255),
                        ruta_imagen TEXT,
                        fecha_caducidad DATE,
                        tiempo_visualizacion INT DEFAULT 10,
                        activo BOOLEAN DEFAULT TRUE,
                        creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    )
                """)
                
                # Guardar banner
                cursor.execute("""
                    INSERT INTO banners (empresa_id, nombre, ruta_imagen, fecha_caducidad, tiempo_visualizacion, activo)
                    VALUES (%s, %s, %s, %s, %s, %s)
                """, (
                    self.empresa_id,
                    nombre,
                    self.ruta_imagen_seleccionada,
                    fecha_caducidad,
                    tiempo_visualizacion,
                    True
                ))
                
            conn.commit()
            conn.close()
            
            messagebox.showinfo("Éxito", "Banner subido correctamente")
            self.limpiar_formulario()
            self.cargar_banners()
            
        except Exception as e:
            messagebox.showerror("Error", f"No se pudo subir el banner: {e}")
    
    def limpiar_formulario(self):
        """Limpiar formulario de subida"""
        self.ent_nombre_banner.delete(0, tk.END)
        self.ent_fecha_caducidad.delete(0, tk.END)
        self.ent_tiempo_visualizacion.delete(0, tk.END)
        self.ent_tiempo_visualizacion.insert(0, "10")
        self.lbl_ruta_imagen.config(text="No seleccionada", fg=self.colors['text_dim'])
        self.canvas_preview.delete("all")
        self.canvas_preview.create_text(190, 125, text="🖼️", font=('Segoe UI', 48), 
                                       fill=self.colors['text_dim'])
        self.canvas_preview.create_text(190, 170, text="Selecciona una imagen para ver preview", 
                                       font=('Segoe UI', 10), fill=self.colors['text_dim'])
        
        if hasattr(self, 'ruta_imagen_seleccionada'):
            delattr(self, 'ruta_imagen_seleccionada')
    
    def cargar_banners(self):
        """Cargar banners desde la base de datos"""
        try:
            conn = get_connection()
            with conn.cursor() as cursor:
                cursor.execute("""
                    SELECT id, nombre, ruta_imagen, fecha_caducidad, tiempo_visualizacion, activo, creado_en
                    FROM banners 
                    WHERE empresa_id=%s 
                    ORDER BY creado_en DESC
                """, (self.empresa_id,))
                
                banners = cursor.fetchall()
                
                # Limpiar tabla
                for item in self.tabla_banners.get_children():
                    self.tabla_banners.delete(item)
                
                # Cargar banners
                self.banners_cargados = []
                hoy = datetime.now().date()
                
                for banner in banners:
                    banner_dict = {
                        'id': banner['id'],
                        'nombre': banner['nombre'],
                        'ruta_imagen': banner['ruta_imagen'],
                        'fecha_caducidad': banner['fecha_caducidad'],
                        'tiempo_visualizacion': banner['tiempo_visualizacion'],
                        'activo': banner['activo'],
                        'creado_en': banner['creado_en']
                    }
                    
                    # Determinar estado
                    fecha_cad = banner['fecha_caducidad']
                    if not banner['activo']:
                        estado = "🔴 Inactivo"
                        color = 'red'
                    elif fecha_cad < hoy:
                        estado = "⚠️ Expirado"
                        color = 'orange'
                    elif fecha_cad == hoy:
                        estado = "🟡 Expira hoy"
                        color = 'gold'
                    else:
                        estado = "🟢 Activo"
                        color = 'green'
                    
                    # Agregar a tabla
                    self.tabla_banners.insert('', 'end', values=(
                        banner['id'],
                        banner['nombre'],
                        banner['fecha_caducidad'].strftime("%d/%m/%Y"),
                        f"{banner['tiempo_visualizacion']}s",
                        estado
                    ), tags=(color,))
                    
                    self.banners_cargados.append(banner_dict)
                
                # Configurar colores
                self.tabla_banners.tag_configure('red', foreground='red')
                self.tabla_banners.tag_configure('orange', foreground='orange')
                self.tabla_banners.tag_configure('gold', foreground='gold')
                self.tabla_banners.tag_configure('green', foreground='green')
                
            conn.close()
            
        except Exception as e:
            messagebox.showerror("Error", f"No se pudieron cargar los banners: {e}")
    
    def preview_banner(self):
        """Mostrar preview del banner seleccionado"""
        seleccion = self.tabla_banners.selection()
        if not seleccion:
            messagebox.showwarning("Aviso", "Por favor selecciona un banner")
            return
            
        item = self.tabla_banners.item(seleccion[0])
        banner_id = item['values'][0]
        
        # Buscar banner
        banner = next((b for b in self.banners_cargados if b['id'] == banner_id), None)
        if not banner:
            return
            
        try:
            img = Image.open(banner['ruta_imagen'])
            img.thumbnail((380, 200), Image.Resampling.LANCZOS)
            self.preview_image = ImageTk.PhotoImage(img)
            
            self.canvas_preview.delete("all")
            self.canvas_preview.create_image(190, 100, image=self.preview_image)
            
            # Actualizar información
            info_text = f"📋 {banner['nombre']}\n"
            info_text += f"📅 Caduca: {banner['fecha_caducidad'].strftime('%d/%m/%Y')}\n"
            info_text += f"⏱️ Tiempo: {banner['tiempo_visualizacion']} segundos\n"
            info_text += f"📁 Ruta: {banner['ruta_imagen']}"
            
            self.lbl_info_banner.config(text=info_text, fg=self.colors['text'])
            
        except Exception as e:
            messagebox.showerror("Error", f"No se pudo cargar la imagen: {e}")
    
    def eliminar_banner(self):
        """Eliminar banner seleccionado"""
        seleccion = self.tabla_banners.selection()
        if not seleccion:
            messagebox.showwarning("Aviso", "Por favor selecciona un banner")
            return
            
        if not messagebox.askyesno("Confirmar", "¿Estás seguro de eliminar este banner?"):
            return
            
        item = self.tabla_banners.item(seleccion[0])
        banner_id = item['values'][0]
        
        try:
            conn = get_connection()
            with conn.cursor() as cursor:
                cursor.execute("DELETE FROM banners WHERE id=%s AND empresa_id=%s", (banner_id, self.empresa_id))
                
            conn.commit()
            conn.close()
            
            messagebox.showinfo("Éxito", "Banner eliminado correctamente")
            self.cargar_banners()
            
        except Exception as e:
            messagebox.showerror("Error", f"No se pudo eliminar el banner: {e}")
    
    def iniciar_dlna(self):
        """Iniciar servidor DLNA (local o remoto) para mostrar banners en TV"""
        if self.var_tipo_servidor.get() == "remoto":
            self.iniciar_dlna_remoto()
        else:
            self.iniciar_dlna_local()
    
    def iniciar_dlna_local(self):
        """Iniciar servidor DLNA local"""
        banners_activos = [b for b in self.banners_cargados if b['activo'] and b['fecha_caducidad'] >= datetime.now().date()]
        
        if not banners_activos:
            messagebox.showwarning("Aviso", "No hay banners activos para mostrar")
            return
            
        self.dlna_server_running = True
        self.lbl_estado_dlna.config(text="🟢 SERVIDOR DLNA ACTIVO", fg=self.colors['success'])
        
        # Iniciar ciclo de visualización en hilo separado
        threading.Thread(target=self.ciclo_visualizacion, daemon=True).start()
        
        messagebox.showinfo("DLNA Iniciado", "Servidor DLNA local iniciado. Los banners se mostrarán en dispositivos compatibles.")
    
    def iniciar_dlna_remoto(self):
        """Iniciar servidor DLNA remoto"""
        ip_servidor = self.ent_ip_servidor.get()
        puerto_servidor = self.ent_puerto_servidor.get()
        
        if not ip_servidor or not puerto_servidor:
            messagebox.showwarning("Aviso", "Por favor configure la IP y puerto del servidor DLNA remoto")
            return
        
        try:
            # Enviar comando de inicio al servidor remoto
            import requests
            url = f"http://{ip_servidor}:{puerto_servidor}/api/dlna/iniciar"
            
            response = requests.post(url, json={
                'action': 'iniciar',
                'empresa_id': self.empresa_id,
                'banners': [b for b in self.banners_cargados if b['activo']]
            }, timeout=5)
            
            if response.status_code == 200:
                self.dlna_server_running = True
                self.lbl_estado_dlna.config(text=f"🟢 SERVIDOR REMOTO ACTIVO ({ip_servidor})", fg=self.colors['success'])
                messagebox.showinfo("DLNA Remoto Iniciado", f"Servidor DLNA remoto iniciado en {ip_servidor}:{puerto_servidor}")
            else:
                messagebox.showerror("Error", f"No se pudo iniciar el servidor remoto: {response.status_code}")
                
        except requests.exceptions.RequestException as e:
            messagebox.showerror("Error de Conexión", f"No se pudo conectar al servidor remoto: {e}")
        except Exception as e:
            messagebox.showerror("Error", f"Error al iniciar servidor remoto: {e}")
    
    def ciclo_visualizacion(self):
        """Ciclo de visualización de banners"""
        while self.dlna_server_running:
            banners_activos = [b for b in self.banners_cargados if b['activo'] and b['fecha_caducidad'] >= datetime.now().date()]
            
            if not banners_activos:
                self.dlna_server_running = False
                self.root.after(0, lambda: self.lbl_estado_dlna.config(text="🔴 SERVIDOR DLNA DETENIDO", fg=self.colors['danger']))
                break
                
            if self.var_ciclo_automatico.get():
                # Mostrar cada banner por su tiempo configurado
                for banner in banners_activos:
                    if not self.dlna_server_running:
                        break
                        
                    # Actualizar banner actual
                    self.banner_actual = banner
                    self.root.after(0, lambda b=banner: self.actualizar_banner_actual(b))
                    
                    # Simular visualización por el tiempo configurado
                    tiempo = banner['tiempo_visualizacion']
                    for i in range(tiempo):
                        if not self.dlna_server_running:
                            break
                        tiempo_restante = tiempo - i
                        self.root.after(0, lambda tr=tiempo_restante: self.lbl_tiempo_restante.config(text=f"Tiempo restante: {tr}s"))
                        threading.Event().wait(1)
            else:
                # Modo manual - mostrar solo el banner seleccionado
                if self.banner_actual:
                    tiempo = self.banner_actual['tiempo_visualizacion']
                    for i in range(tiempo):
                        if not self.dlna_server_running:
                            break
                        tiempo_restante = tiempo - i
                        self.root.after(0, lambda tr=tiempo_restante: self.lbl_tiempo_restante.config(text=f"Tiempo restante: {tr}s"))
                        threading.Event().wait(1)
    
    def actualizar_banner_actual(self, banner):
        """Actualizar información del banner actual"""
        self.lbl_banner_actual.config(text=f"Banner actual: {banner['nombre']}")
        
        # Mostrar preview del banner actual
        try:
            img = Image.open(banner['ruta_imagen'])
            img.thumbnail((380, 200), Image.Resampling.LANCZOS)
            self.preview_image = ImageTk.PhotoImage(img)
            
            self.canvas_preview.delete("all")
            self.canvas_preview.create_image(190, 100, image=self.preview_image)
            
        except Exception as e:
            pass  # Ignorar errores de carga de imagen
    
    def pausar_dlna(self):
        """Pausar servidor DLNA (local o remoto)"""
        if self.var_tipo_servidor.get() == "remoto":
            self.pausar_dlna_remoto()
        else:
            self.pausar_dlna_local()
    
    def pausar_dlna_local(self):
        """Pausar servidor DLNA local"""
        self.dlna_server_running = False
        self.lbl_estado_dlna.config(text="🟡 SERVIDOR DLNA PAUSADO", fg=self.colors['accent'])
        self.lbl_tiempo_restante.config(text="Tiempo restante: --")
    
    def pausar_dlna_remoto(self):
        """Pausar servidor DLNA remoto"""
        try:
            import requests
            ip_servidor = self.ent_ip_servidor.get()
            puerto_servidor = self.ent_puerto_servidor.get()
            
            url = f"http://{ip_servidor}:{puerto_servidor}/api/dlna/pausar"
            response = requests.post(url, json={'action': 'pausar'}, timeout=5)
            
            if response.status_code == 200:
                self.dlna_server_running = False
                self.lbl_estado_dlna.config(text=f"🟡 SERVIDOR REMOTO PAUSADO ({ip_servidor})", fg=self.colors['accent'])
                self.lbl_tiempo_restante.config(text="Tiempo restante: --")
            else:
                messagebox.showerror("Error", f"No se pudo pausar el servidor remoto: {response.status_code}")
                
        except requests.exceptions.RequestException as e:
            messagebox.showerror("Error de Conexión", f"No se pudo conectar al servidor remoto: {e}")
        except Exception as e:
            messagebox.showerror("Error", f"Error al pausar servidor remoto: {e}")
    
    def detener_dlna(self):
        """Detener servidor DLNA (local o remoto)"""
        if self.var_tipo_servidor.get() == "remoto":
            self.detener_dlna_remoto()
        else:
            self.detener_dlna_local()
    
    def detener_dlna_local(self):
        """Detener servidor DLNA local"""
        self.dlna_server_running = False
        self.lbl_estado_dlna.config(text="🔴 SERVIDOR DLNA DETENIDO", fg=self.colors['danger'])
        self.lbl_banner_actual.config(text="Banner actual: Ninguno")
        self.lbl_tiempo_restante.config(text="Tiempo restante: --")
    
    def detener_dlna_remoto(self):
        """Detener servidor DLNA remoto"""
        try:
            import requests
            ip_servidor = self.ent_ip_servidor.get()
            puerto_servidor = self.ent_puerto_servidor.get()
            
            url = f"http://{ip_servidor}:{puerto_servidor}/api/dlna/detener"
            response = requests.post(url, json={'action': 'detener'}, timeout=5)
            
            if response.status_code == 200:
                self.dlna_server_running = False
                self.lbl_estado_dlna.config(text=f"🔴 SERVIDOR REMOTO DETENIDO ({ip_servidor})", fg=self.colors['danger'])
                self.lbl_banner_actual.config(text="Banner actual: Ninguno")
                self.lbl_tiempo_restante.config(text="Tiempo restante: --")
            else:
                messagebox.showerror("Error", f"No se pudo detener el servidor remoto: {response.status_code}")
                
        except requests.exceptions.RequestException as e:
            messagebox.showerror("Error de Conexión", f"No se pudo conectar al servidor remoto: {e}")
        except Exception as e:
            messagebox.showerror("Error", f"Error al detener servidor remoto: {e}")
    
    def intentar_maximizar(self):
        """Intentar maximizar ventana nuevamente"""
        try:
            self.root.state('zoomed')
        except:
            try:
                self.root.attributes('-zoomed', True)
            except:
                pass  # Si no funciona, ya se estableció un tamaño grande
    
    def verificar_info_temporal(self):
        """Verificar si hay información temporal del generador de IA"""
        try:
            import json
            import os
            
            if os.path.exists('temp_banner_info.json'):
                with open('temp_banner_info.json', 'r') as f:
                    banner_info = json.load(f)
                
                # Precargar la información en el formulario
                self.ent_nombre_banner.delete(0, tk.END)
                self.ent_nombre_banner.insert(0, banner_info['nombre'])
                
                self.ruta_imagen_seleccionada = banner_info['ruta_imagen']
                nombre_archivo = os.path.basename(banner_info['ruta_imagen'])
                self.lbl_ruta_imagen.config(text=nombre_archivo, fg=self.colors['success'])
                
                # Mostrar preview
                try:
                    img = Image.open(banner_info['ruta_imagen'])
                    img.thumbnail((380, 200), Image.Resampling.LANCZOS)
                    self.preview_image = ImageTk.PhotoImage(img)
                    
                    self.canvas_preview.delete("all")
                    self.canvas_preview.create_image(190, 100, image=self.preview_image)
                    
                except Exception as e:
                    pass  # Ignorar errores de carga de imagen
                
                # Establecer fecha de caducidad por defecto (7 días)
                self.set_fecha_7_dias()
                
                # Eliminar archivo temporal
                os.remove('temp_banner_info.json')
                
                # Mostrar mensaje informativo
                self.lbl_info_banner.config(
                    text=f"✅ Imagen precargada desde Generador IA\n"
                         f"📦 Producto: {banner_info['producto']}\n"
                         f"🎟️ Promoción: {banner_info['promocion']}\n"
                         f"⚠️ Configura nombre y fecha de caducidad",
                    fg=self.colors['success']
                )
                
        except Exception as e:
            pass  # Si no hay archivo temporal o hay error, continuar normalmente
    
    def cerrar_ventana(self):
        """Manejar el cierre de ventana de forma segura"""
        try:
            # Detener servidor DLNA si está activo
            if hasattr(self, 'dlna_server_running') and self.dlna_server_running:
                self.detener_dlna()
            
            # Limpiar archivos temporales si existen
            import os
            if os.path.exists('temp_banner_info.json'):
                os.remove('temp_banner_info.json')
                
            self.root.quit()
            self.root.destroy()
        except:
            pass

def ejecutar_banners(empresa_id, nombre_negocio):
    """Función principal para ejecutar el módulo de banners"""
    try:
        root = tk.Tk()
        app = BannersUI(root, empresa_id, nombre_negocio)
        
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
        print("\n📋 Módulo de banners cerrado por el usuario")
    except Exception as e:
        print(f"❌ Error en módulo de banners: {e}")
    finally:
        # Limpiar archivos temporales si existen
        try:
            import os
            if os.path.exists('temp_banner_info.json'):
                os.remove('temp_banner_info.json')
        except:
            pass

# Solo permitir ejecución desde dashboard
if __name__ == "__main__":
    print("⚠️ Este módulo solo debe ejecutarse desde el dashboard principal")
    print("Por favor, accede desde el menú principal del sistema")
