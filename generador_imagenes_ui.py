#!/usr/bin/env python3
# -*- coding: utf-8 -*-

"""
Módulo de Generación de Imágenes con IA para Promociones
Este archivo solo debe ejecutarse desde el dashboard principal
"""

import tkinter as tk
from tkinter import ttk, messagebox, filedialog
from PIL import Image, ImageTk
import requests
import json
import os
import threading
import webbrowser
from datetime import datetime
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

class GeneradorImagenesUI:
    def __init__(self, root, empresa_id, nombre_negocio):
        self.root = root
        self.empresa_id = empresa_id
        self.nombre_negocio = nombre_negocio
        self.colors = COLORS
        self.imagen_generada = None
        self.ruta_imagen_guardada = None
        
        # Variables de selección
        self.producto_seleccionado = None
        self.promocion_seleccionada = None
        self.prompt_generado = tk.StringVar()
        self.estilo_seleccionado = tk.StringVar(value="Modern")
        self.tamano_seleccionado = tk.StringVar(value="1080x1080")
        self.tipo_contenido = tk.StringVar(value="imagen")
        self.duracion_video = tk.StringVar(value="10")
        
        self.setup_ui()
        
    def setup_ui(self):
        # Ventana principal
        self.root.title(f"🎨 Generador de Contenido IA | {self.nombre_negocio.upper()}")
        self.root.geometry("1000x700")
        self.root.configure(bg=self.colors['bg_main'])
        
        # Manejar cierre de ventana para evitar problemas
        self.root.protocol("WM_DELETE_WINDOW", self.cerrar_ventana)
        
        # Intentar maximizar ventana una sola vez
        self.maximizar_ventana()
    
    def maximizar_ventana(self):
        """Intentar maximizar la ventana con múltiples métodos"""
        try:
            # Método 1: state('zoomed')
            self.root.state('zoomed')
        except:
            try:
                # Método 2: attributes('-zoomed')
                self.root.attributes('-zoomed', True)
            except:
                try:
                    # Método 3: Tamaño grande
                    screen_width = self.root.winfo_screenwidth()
                    screen_height = self.root.winfo_screenheight()
                    self.root.geometry(f"{screen_width-100}x{screen_height-100}+0+0")
                except:
                    # Método 4: Tamaño fijo grande
                    self.root.geometry("1600x1000+0+0")
        
        # Cargar configuración de IA
        self.cargar_configuracion_ia()
        
        # Header
        header = tk.Frame(self.root, bg=self.colors['bg_main'])
        header.pack(fill=tk.X, pady=(0, 20))
        
        # Header dinámico que cambia según tipo de contenido
        self.header_label = tk.Label(header, text="🎨 GENERADOR DE CONTENIDO IA", 
                                    font=('Segoe UI', 18, 'bold'), bg=self.colors['bg_main'], fg=self.colors['accent'])
        self.header_label.pack(side=tk.LEFT)
        
        # Contenido principal
        main_container = tk.Frame(self.root, bg=self.colors['bg_main'])
        main_container.pack(fill=tk.BOTH, expand=True, padx=20)
        
        # Panel izquierdo - Selección y configuración
        left_panel = tk.Frame(main_container, bg=self.colors['bg_main'])
        left_panel.pack(side=tk.LEFT, fill=tk.BOTH, expand=True, padx=(0, 10))
        
        # Panel derecho - Preview
        right_panel = tk.Frame(main_container, bg=self.colors['bg_main'], width=400)
        right_panel.pack(side=tk.RIGHT, fill=tk.BOTH, expand=True)
        right_panel.pack_propagate(False)
        
        self.setup_panel_izquierdo(left_panel)
        self.setup_panel_derecho(right_panel)
    
    def cargar_configuracion_ia(self):
        """Cargar configuración de IA desde la base de datos"""
        try:
            conn = get_connection()
            with conn.cursor() as cursor:
                cursor.execute("SELECT * FROM config_ia WHERE empresa_id=%s", (self.empresa_id,))
                config = cursor.fetchone()
                
                if config:
                    # Aplicar configuración cargada
                    self.estilo_seleccionado.set(config.get('estilo_defecto', 'Modern'))
                    self.tamano_seleccionado.set(config.get('tamano_defecto', '1080x1080'))
                    self.ruta_guardado = config.get('ruta_imagenes', './imagenes_generadas')
                else:
                    # Valores por defecto
                    self.ruta_guardado = './imagenes_generadas'
                    
            conn.close()
        except Exception as e:
            # Si hay error, usar valores por defecto
            self.ruta_guardado = './imagenes_generadas'
            
        # Crear carpeta si no existe
        import os
        if not os.path.exists(self.ruta_guardado):
            os.makedirs(self.ruta_guardado, exist_ok=True)
        
    def setup_panel_izquierdo(self, parent):
        # SELECCIÓN DE PRODUCTO
        producto_frame = tk.LabelFrame(parent, text="📦 SELECCIÓN DE PRODUCTO", 
                                     font=('Segoe UI', 12, 'bold'), bg=self.colors['bg_panel'], 
                                     fg=self.colors['accent'], relief="raised", borderwidth=2)
        producto_frame.pack(fill=tk.X, pady=(0, 15))
        
        tk.Label(producto_frame, text="Producto:", bg=self.colors['bg_panel'], fg="white",
                 font=('Segoe UI', 10)).pack(anchor="w", padx=15, pady=(10, 5))
        
        self.producto_combo = ttk.Combobox(producto_frame, font=('Segoe UI', 10), state="readonly")
        self.producto_combo.pack(fill=tk.X, padx=15, pady=(0, 10))
        self.producto_combo.bind("<<ComboboxSelected>>", self.on_producto_seleccionado)
        
        self.cargar_productos()
        
        # SELECCIÓN DE PROMOCIÓN
        promo_frame = tk.LabelFrame(parent, text="🎟️ TIPO DE PROMOCIÓN", 
                                   font=('Segoe UI', 12, 'bold'), bg=self.colors['bg_panel'], 
                                   fg=self.colors['accent'], relief="raised", borderwidth=2)
        promo_frame.pack(fill=tk.X, pady=(0, 15))
        
        tk.Label(promo_frame, text="Promoción:", bg=self.colors['bg_panel'], fg="white",
                 font=('Segoe UI', 10)).pack(anchor="w", padx=15, pady=(10, 5))
        
        self.promo_combo = ttk.Combobox(promo_frame, font=('Segoe UI', 10), state="readonly")
        self.promo_combo.pack(fill=tk.X, padx=15, pady=(0, 10))
        self.promo_combo.bind("<<ComboboxSelected>>", self.on_promocion_seleccionada)
        
        self.cargar_promociones()
        
        # PANEL DE TIPO DE CONTENIDO
        contenido_frame = tk.LabelFrame(parent, text="🎬 TIPO DE CONTENIDO", 
                                       font=('Segoe UI', 12, 'bold'), bg=self.colors['bg_panel'], 
                                       fg=self.colors['accent'], relief="raised", borderwidth=2)
        contenido_frame.pack(fill=tk.X, pady=(0, 15))
        
        # Radio buttons para tipo de contenido
        radio_frame = tk.Frame(contenido_frame, bg=self.colors['bg_panel'])
        radio_frame.pack(fill=tk.X, padx=15, pady=10)
        
        tk.Radiobutton(radio_frame, text="🖼️ Imagen Estática", variable=self.tipo_contenido, 
                       value="imagen", bg=self.colors['bg_panel'], fg="white",
                       font=('Segoe UI', 10), selectcolor=self.colors['accent'],
                       command=self.on_tipo_contenido_cambio).pack(anchor="w")
        
        tk.Radiobutton(radio_frame, text="🎥 Video Animado", variable=self.tipo_contenido, 
                       value="video", bg=self.colors['bg_panel'], fg="white",
                       font=('Segoe UI', 10), selectcolor=self.colors['accent'],
                       command=self.on_tipo_contenido_cambio).pack(anchor="w", pady=(5, 0))
        
        # Panel de configuración de video (inicialmente oculto)
        if not hasattr(self, 'video_config_frame'):
            self.video_config_frame = tk.Frame(contenido_frame, bg=self.colors['bg_panel'])
        
        tk.Label(self.video_config_frame, text="Duración (segundos):", bg=self.colors['bg_panel'], fg="white",
                 font=('Segoe UI', 10)).pack(anchor="w", padx=15, pady=(10, 5))
        
        duraciones = ["5", "10", "15", "30"]
        self.combo_duracion = ttk.Combobox(self.video_config_frame, textvariable=self.duracion_video, 
                                           values=duraciones, font=('Segoe UI', 9), state="readonly")
        self.combo_duracion.pack(fill=tk.X, padx=15, pady=(0, 10))

        # PANEL DE CONFIGURACIÓN
        config_frame = tk.LabelFrame(parent, text="⚙️ CONFIGURACIÓN DE IA", 
                                   font=('Segoe UI', 12, 'bold'), bg=self.colors['bg_panel'], 
                                   fg=self.colors['accent'], relief="raised", borderwidth=2)
        config_frame.pack(fill=tk.X, pady=(0, 15))
        
        tk.Label(config_frame, text="Estilo:", bg=self.colors['bg_panel'], fg="white",
                 font=('Segoe UI', 10)).pack(anchor="w", padx=15, pady=(10, 5))
        
        estilos = ["Modern", "Vintage", "Minimalist", "Bold", "Elegant", "Playful"]
        self.combo_estilo = ttk.Combobox(config_frame, textvariable=self.estilo_seleccionado, 
                                         values=estilos, font=('Segoe UI', 9), state="readonly")
        self.combo_estilo.pack(fill=tk.X, padx=15, pady=(0, 10))
        
        tk.Label(config_frame, text="Tamaño:", bg=self.colors['bg_panel'], fg="white",
                 font=('Segoe UI', 10)).pack(anchor="w", padx=15, pady=(10, 5))
        
        tamaños = ["1080x1080", "1920x1080", "1080x1920", "1200x630", "800x800"]
        self.combo_tamano = ttk.Combobox(config_frame, textvariable=self.tamano_seleccionado, 
                                         values=tamaños, font=('Segoe UI', 9), state="readonly")
        self.combo_tamano.pack(fill=tk.X, padx=15, pady=(0, 10))
        
        # PROMPT GENERADO
        prompt_frame = tk.LabelFrame(parent, text="📝 PROMPT GENERADO", 
                                    font=('Segoe UI', 12, 'bold'), bg=self.colors['bg_panel'], 
                                    fg=self.colors['accent'], relief="raised", borderwidth=2)
        prompt_frame.pack(fill=tk.BOTH, expand=True, pady=(0, 15))
        
        self.prompt_text = tk.Text(prompt_frame, font=('Segoe UI', 10), bg="#252525", fg="white",
                                  height=8, wrap=tk.WORD)
        self.prompt_text.pack(fill=tk.BOTH, expand=True, padx=15, pady=15)
        
        # Botones de acción
        botones_frame = tk.Frame(parent, bg=self.colors['bg_main'])
        botones_frame.pack(fill=tk.X)
        
        tk.Button(botones_frame, text="🔄 GENERAR PROMPT", bg=self.colors['accent'], fg="white",
                 font=('Segoe UI', 10, 'bold'), command=self.generar_prompt,
                 width=15).pack(side=tk.LEFT, padx=(0, 5))
        
        tk.Button(botones_frame, text="📋 COPIAR", bg=self.colors['bg_panel'], fg="white",
                 font=('Segoe UI', 10, 'bold'), command=self.copiar_prompt,
                 width=10).pack(side=tk.LEFT, padx=5)
        
        tk.Button(botones_frame, text="🎨 GENERAR IMAGEN", bg=self.colors['success'], fg="white",
                 font=('Segoe UI', 10, 'bold'), command=self.generar_imagen,
                 width=15).pack(side=tk.RIGHT, padx=(0, 5))
        
        tk.Button(botones_frame, text="🖼️ ENVIAR A BANNERS", bg=self.colors['accent'], fg="white",
                 font=('Segoe UI', 10, 'bold'), command=self.enviar_a_banners,
                 width=15).pack(side=tk.RIGHT, padx=(0, 5))
        
        tk.Button(botones_frame, text="🤖 ABRIR IA EXTERNA", bg=self.colors['accent'], fg="white",
                 font=('Segoe UI', 10, 'bold'), command=self.abrir_ia_externa,
                 width=15).pack(side=tk.RIGHT)
        
    def setup_panel_derecho(self, parent):
        # PREVIEW DE IMAGEN
        preview_frame = tk.LabelFrame(parent, text="🖼️ VISTA PREVIA", 
                                    font=('Segoe UI', 12, 'bold'), bg=self.colors['bg_panel'], 
                                    fg=self.colors['accent'], relief="raised", borderwidth=2)
        preview_frame.pack(fill=tk.BOTH, expand=True)
        
        # Canvas para la imagen
        self.canvas_imagen = tk.Canvas(preview_frame, bg="#1a1a1a", width=350, height=350)
        self.canvas_imagen.pack(pady=20)
        
        # Placeholder inicial
        self.canvas_imagen.create_text(175, 175, text="🖼️", font=('Segoe UI', 48), 
                                       fill=self.colors['text_dim'])
        self.canvas_imagen.create_text(175, 220, text="La imagen generada aparecerá aquí", 
                                       font=('Segoe UI', 10), fill=self.colors['text_dim'])
        
        # Botones de imagen
        imagen_botones_frame = tk.Frame(preview_frame, bg=self.colors['bg_panel'])
        imagen_botones_frame.pack(pady=10)
        
        self.btn_descargar = tk.Button(imagen_botones_frame, text="💾 GUARDAR", 
                                       bg=self.colors['success'], fg="white",
                                       font=('Segoe UI', 10, 'bold'), command=self.guardar_imagen,
                                       width=12, state=tk.DISABLED)
        self.btn_descargar.pack(side=tk.LEFT, padx=5)
        
        self.btn_compartir = tk.Button(imagen_botones_frame, text="📤 COMPARTIR", 
                                       bg=self.colors['accent'], fg="white",
                                       font=('Segoe UI', 10, 'bold'), command=self.compartir_imagen,
                                       width=12, state=tk.DISABLED)
        self.btn_compartir.pack(side=tk.LEFT, padx=5)
        
        # Estado de generación
        self.estado_label = tk.Label(preview_frame, text="Listo para generar", 
                                     bg=self.colors['bg_panel'], fg=self.colors['text_dim'],
                                     font=('Segoe UI', 9))
        self.estado_label.pack(pady=5)
        
    def cargar_productos(self):
        """Cargar productos desde la base de datos"""
        try:
            conn = get_connection()
            with conn.cursor() as cursor:
                cursor.execute("SELECT id, nombre, precio FROM productos WHERE empresa_id=%s ORDER BY nombre", 
                              (self.empresa_id,))
                productos = cursor.fetchall()
                
                self.productos_data = {f"{p['nombre']} - ${p['precio']}": p for p in productos}
                self.producto_combo['values'] = list(self.productos_data.keys())
                
        except Exception as e:
            messagebox.showerror("Error", f"No se pudieron cargar los productos: {e}")
        finally:
            conn.close()
            
    def cargar_promociones(self):
        """Cargar promociones activas desde la base de datos"""
        try:
            conn = get_connection()
            promociones = []
            
            # Cupones
            with conn.cursor() as cursor:
                cursor.execute("SELECT id, codigo_qr, descuento_porcentaje FROM cupones WHERE empresa_id=%s AND activo=1", 
                              (self.empresa_id,))
                cupones = cursor.fetchall()
                for cupon in cupones:
                    promociones.append(f"CUPÓN: {cupon['codigo_qr']} - {cupon['descuento_porcentaje']}% OFF")
            
            # Combos
            with conn.cursor() as cursor:
                cursor.execute("SELECT id, nombre_promo, descuento_porcentaje FROM promociones_combos WHERE empresa_id=%s AND activo=1", 
                              (self.empresa_id,))
                combos = cursor.fetchall()
                for combo in combos:
                    promociones.append(f"COMBO: {combo['nombre_promo']} - {combo['descuento_porcentaje']}% OFF")
            
            # Mayorista
            with conn.cursor() as cursor:
                cursor.execute("""
                    SELECT v.id, p.nombre, v.cantidad_minima, v.descuento_porcentaje 
                    FROM promociones_volumen v 
                    JOIN productos p ON v.producto_id = p.id 
                    WHERE v.empresa_id=%s AND v.activo=1
                """, (self.empresa_id,))
                mayoristas = cursor.fetchall()
                for mayorista in mayoristas:
                    promociones.append(f"MAYORISTA: {mayorista['nombre']} - {mayorista['cantidad_minima']}+ unidades {mayorista['descuento_porcentaje']}% OFF")
            
            self.promociones_data = {}
            for promo in promociones:
                promo_id = len(self.promociones_data) + 1
                self.promociones_data[promo] = {'id': promo_id, 'texto': promo}
            
            self.promo_combo['values'] = list(self.promociones_data.keys())
            
        except Exception as e:
            messagebox.showerror("Error", f"No se pudieron cargar las promociones: {e}")
        finally:
            conn.close()
            
    def on_producto_seleccionado(self, event=None):
        """Manejar selección de producto"""
        seleccion = self.producto_combo.get()
        if seleccion in self.productos_data:
            self.producto_seleccionado = self.productos_data[seleccion]
            # Intentar generar prompt automáticamente si también hay promoción seleccionada
            if self.promocion_seleccionada:
                self.generar_prompt()
            
    def on_promocion_seleccionada(self, event=None):
        """Manejar selección de promoción"""
        seleccion = self.promo_combo.get()
        if seleccion in self.promociones_data:
            self.promocion_seleccionada = self.promociones_data[seleccion]
            # Intentar generar prompt automáticamente si también hay producto seleccionado
            if self.producto_seleccionado:
                self.generar_prompt()
            
    def generar_prompt(self):
        """Generar prompt personalizado basado en producto y promoción"""
        if not self.producto_seleccionado:
            messagebox.showwarning("Aviso", "Por favor selecciona un producto")
            return
            
        if not self.promocion_seleccionada:
            messagebox.showwarning("Aviso", "Por favor selecciona una promoción")
            return
            
        # Extraer tipo de promoción
        promo_texto = self.promocion_seleccionada['texto']
        if "CUPÓN:" in promo_texto:
            tipo_promo = "CUPÓN"
            # Extraer descuento después del guion
            partes = promo_texto.split("-")
            descuento = partes[1].strip() if len(partes) > 1 else ""
        elif "COMBO:" in promo_texto:
            tipo_promo = "COMBO"
            partes = promo_texto.split("-")
            descuento = partes[1].strip() if len(partes) > 1 else ""
        elif "MAYORISTA:" in promo_texto:
            tipo_promo = "MAYORISTA"
            partes = promo_texto.split("-")
            descuento = partes[1].strip() if len(partes) > 1 else ""
        else:
            tipo_promo = "GENERAL"
            descuento = ""
            
        # Generar prompt según tipo
        prompt = self.crear_prompt_personalizado(
            producto=self.producto_seleccionado,
            tipo_promo=tipo_promo,
            descuento=descuento,
            estilo=self.estilo_seleccionado.get(),
            tamano=self.tamano_seleccionado.get()
        )
        
        self.prompt_text.delete(1.0, tk.END)
        self.prompt_text.insert(1.0, prompt)
        
    def on_tipo_contenido_cambio(self):
        """Manejar el cambio de tipo de contenido"""
        if self.tipo_contenido.get() == "video":
            # Mostrar configuración de video
            self.video_config_frame.pack(fill=tk.X, padx=15, pady=(0, 10))
            # Actualizar título de ventana y header
            self.root.title(f"🎥 Generador de Videos IA | {self.nombre_negocio.upper()}")
            self.header_label.config(text="🎥 GENERADOR DE VIDEOS IA")
        else:
            # Ocultar configuración de video
            self.video_config_frame.pack_forget()
            # Actualizar título de ventana y header
            self.root.title(f"🎨 Generador de Imágenes IA | {self.nombre_negocio.upper()}")
            self.header_label.config(text="🎨 GENERADOR DE IMÁGENES IA")
        
        # Regenerar prompt si hay producto y promoción seleccionados
        if self.producto_seleccionado and self.promocion_seleccionada:
            self.generar_prompt()

    def crear_prompt_personalizado(self, producto, tipo_promo, descuento, estilo, tamano):
        """Crear prompt personalizado según tipo de promoción con texto en español"""
        nombre_producto = producto['nombre']
        precio = producto['precio']
        duracion = self.duracion_video.get() if self.tipo_contenido.get() == "video" else ""
        
        if self.tipo_contenido.get() == "video":
            return self.crear_prompt_video(nombre_producto, precio, tipo_promo, descuento, estilo, tamano, duracion)
        else:
            return self.crear_prompt_imagen(nombre_producto, precio, tipo_promo, descuento, estilo, tamano)
    
    def crear_prompt_video(self, nombre_producto, precio, tipo_promo, descuento, estilo, tamano, duracion):
        """Crear prompt para generación de videos"""
        prompts_base = {
            "CUPÓN": f"""CREA UN VIDEO: Promoción animada para {nombre_producto} con {descuento} de descuento.
Producto: {nombre_producto}
Precio: ${precio}
Descuento: {descuento}
Duración: {duracion} segundos
Estilo: {estilo.lower()}, animación 3D moderna, colores vibrantes
Texto en español: "{descuento} DE DESCUENTO" + "{nombre_producto}" + "OFERTA POR TIEMPO LIMITADO" + "APROVECHA AHORA"
Elementos visuales: Producto rotando, efectos de partículas, texto emergente animado, burbujas o efectos de luz
Música: Sin música, solo efectos visuales y animación
Formato: MP4 {tamano}, 30fps, alta calidad
Enfoque: Promoción dinámica para redes sociales, texto en español claro y legible
Idioma: Todo el texto debe estar en español""",
            
            "COMBO": f"""CREA UN VIDEO: Promoción combo animada para {nombre_producto}.
Producto: {nombre_producto}
Precio: ${precio}
Descuento: {descuento}
Duración: {duracion} segundos
Estilo: {estilo.lower()}, animación energética y dinámica, colores llamativos
Texto en español: "PACK COMBO" + "{nombre_producto}" + "{descuento} DE DESCUENTO" + "AHORRA GRANDE" + "COMPRA AHORA"
Elementos visuales: Múltiples productos apareciendo, efectos de empaquetado, precios animados, savings destacados
Música: Sin música, solo efectos visuales y animación
Formato: MP4 {tamano}, 30fps, alta calidad
Enfoque: Promoción de paquete dinámica, propuesta de valor visual
Idioma: Todo el texto debe estar en español""",
            
            "MAYORISTA": f"""CREA UN VIDEO: Oferta mayorista profesional para {nombre_producto}.
Producto: {nombre_producto}
Precio: ${precio}
Descuento: {descuento}
Duración: {duracion} segundos
Estilo: {estilo.lower()}, animación corporativa elegante, colores profesionales
Texto en español: "VENTA POR MAYOR" + "{nombre_producto}" + "{descuento} DE DESCUENTO" + "PRECIO POR VOLUMEN" + "COMPRAR AL POR MAYOR"
Elementos visuales: Apilamiento de productos, efectos de volumen, precios mayoristas animados, gráficos de ahorro
Música: Sin música, solo efectos visuales y animación
Formato: MP4 {tamano}, 30fps, alta calidad
Enfoque: Promoción B2B profesional, incentivo de pedidos grandes
Idioma: Todo el texto debe estar en español""",
            
            "GENERAL": f"""CREA UN VIDEO: Publicidad profesional para {nombre_producto}.
Producto: {nombre_producto}
Precio: ${precio}
Duración: {duracion} segundos
Estilo: {estilo.lower()}, animación de alta calidad, presentación premium
Texto en español: "{nombre_producto}" + "SÓLO ${precio}" + "COMPRA AHORA" + "PRODUCTO DISPONIBLE" + "CALIDAD GARANTIZADA"
Elementos visuales: Producto destacado, efectos de lujo, texto elegante, animación suave
Música: Sin música, solo efectos visuales y animación
Formato: MP4 {tamano}, 30fps, alta calidad
Enfoque: Promoción general de producto, imagen de marca premium
Idioma: Todo el texto debe estar en español"""
        }
        
        return prompts_base.get(tipo_promo, prompts_base["GENERAL"])
    
    def crear_prompt_imagen(self, nombre_producto, precio, tipo_promo, descuento, estilo, tamano):
        """Crear prompt para generación de imágenes (método original)"""
        prompts_base = {
            "CUPÓN": f"""CREA UNA IMAGEN: Cupón de descuento profesional para {nombre_producto}.
Producto: {nombre_producto}
Precio: ${precio}
Descuento: {descuento}
Estilo: {estilo.lower()}, fotografía comercial, colores vibrantes
Incluye: Imagen del producto + etiqueta de precio + badge de descuento + texto de cupón
Fondo: Iluminación profesional limpia con colores de marca
Texto en español: "{descuento} DE DESCUENTO" + "OFERTA POR TIEMPO LIMITADO" + "APROVECHA AHORA"
Resolución: {tamano} píxeles, alta calidad, detalles nítidos
Enfoque de marketing: Promoción e-commerce, lista para redes sociales
Idioma: Todo el texto debe estar en español""",
            
            "COMBO": f"""CREA UNA IMAGEN: Promoción combo atractiva para {nombre_producto}.
Producto: {nombre_producto}
Precio: ${precio}
Descuento: {descuento}
Estilo: {estilo.lower()}, composición dinámica, llamativo
Incluye: Vistas múltiples del producto + precios combo + destacado de ahorros
Fondo: Moderno, enérgico, atmósfera promocional
Texto en español: "PACK COMBO" + "{descuento} DE DESCUENTO" + "AHORRA GRANDE" + "COMPRA AHORA"
Resolución: {tamano} píxeles, calidad comercial, listo para imprimir
Enfoque de marketing: Promoción de paquete, propuesta de valor
Idioma: Todo el texto debe estar en español""",
            
            "MAYORISTA": f"""CREA UNA IMAGEN: Oferta mayorista profesional para {nombre_producto}.
Producto: {nombre_producto}
Precio: ${precio}
Descuento: {descuento}
Estilo: {estilo.lower()}, profesional de negocios, venta por mayor
Incluye: Exhibición del producto + precios mayoristas + descuento por volumen
Fondo: Corporativo, confiable, entorno de negocios
Texto en español: "VENTA POR MAYOR" + "{descuento} DE DESCUENTO" + "PRECIO POR VOLUMEN" + "COMPRAR AL POR MAYOR"
Resolución: {tamano} píxeles, calidad presentación de negocios
Enfoque de marketing: Promoción B2B, incentivo de pedidos grandes
Idioma: Todo el texto debe estar en español""",
            
            "GENERAL": f"""CREA UNA IMAGEN: Publicidad profesional para {nombre_producto}.
Producto: {nombre_producto}
Precio: ${precio}
Estilo: {estilo.lower()}, fotografía comercial de alta gama
Incluye: Toma heroica del producto + display de precio + elementos promocionales
Fondo: Limpio, minimalista, presentación premium
Texto en español: "{nombre_producto}" + "SÓLO ${precio}" + "COMPRA AHORA" + "PRODUCTO DISPONIBLE"
Resolución: {tamano} píxeles, calidad de marketing
Enfoque de marketing: Promoción general de producto, conciencia de marca
Idioma: Todo el texto debe estar en español"""
        }
        
        return prompts_base.get(tipo_promo, prompts_base["GENERAL"])
        
    def copiar_prompt(self):
        """Copiar prompt al portapapeles"""
        prompt = self.prompt_text.get(1.0, tk.END).strip()
        if prompt:
            self.root.clipboard_clear()
            self.root.clipboard_append(prompt)
            messagebox.showinfo("Éxito", "Prompt copiado al portapapeles")
        else:
            messagebox.showwarning("Aviso", "No hay prompt para copiar")
            
    def generar_imagen(self):
        """Generar imagen usando API de IA"""
        prompt = self.prompt_text.get(1.0, tk.END).strip()
        if not prompt:
            messagebox.showwarning("Aviso", "Por favor genera un prompt primero")
            return
            
        self.estado_label.config(text="Generando imagen...", fg=self.colors['accent'])
        
        # Ejecutar en hilo separado para no bloquear la UI
        threading.Thread(target=self._generar_imagen_thread, args=(prompt,), daemon=True).start()
        
    def abrir_ia_externa(self):
        """Abrir IA externa (Ideogram AI) en el navegador"""
        prompt = self.prompt_text.get(1.0, tk.END).strip()
        if not prompt:
            messagebox.showwarning("Aviso", "Por favor genera un prompt primero")
            return
            
        # Copiar prompt al portapapeles
        self.root.clipboard_clear()
        self.root.clipboard_append(prompt)
        
        # Abrir Ideogram AI en el navegador
        import webbrowser
        webbrowser.open("https://ideogram.ai")
        
        messagebox.showinfo("IA Externa Abierta", 
                          "✅ Prompt copiado al portapapeles\n🌐 Ideogram AI abierto en el navegador\n\n"
                          "1. Pega el prompt en Ideogram AI\n"
                          "2. Genera la imagen\n"
                          "3. Descarga la imagen y guárdala en tu sistema")
        
    def _generar_imagen_thread(self, prompt):
        """Hilo para generación de imagen"""
        try:
            # Simulación de generación de imagen (mejorada)
            import time
            self.root.after(0, lambda: self.estado_label.config(text="Generando imagen...", fg=self.colors['accent']))
            time.sleep(2)  # Simular tiempo de procesamiento
            
            # Aquí iría la llamada real a la API de IA
            # Por ahora, creamos una imagen de placeholder mejorada
            self.crear_imagen_placeholder_mejorado(prompt)
            
            # Actualizar UI en el hilo principal
            self.root.after(0, self._imagen_generada_exitosamente)
            
        except Exception as e:
            self.root.after(0, lambda: messagebox.showerror("Error", f"No se pudo generar la imagen: {e}"))
            self.root.after(0, lambda: self.estado_label.config(text="Error en generación", fg=self.colors['danger']))
            
    def crear_imagen_placeholder_mejorado(self, prompt):
        """Crear imagen de placeholder mejorada (simulación)"""
        # Crear una imagen más profesional como placeholder
        from PIL import Image, ImageDraw, ImageFont
        
        width, height = map(int, self.tamano_seleccionado.get().split('x'))
        img = Image.new('RGB', (width, height), color='#2c3e50')
        draw = ImageDraw.Draw(img)
        
        # Gradiente simple
        for i in range(height):
            color_value = int(44 + (52 * i / height))
            draw.line([(0, i), (width, i)], fill=(color_value, color_value + 20, color_value + 30))
        
        # Texto principal
        try:
            font_large = ImageFont.truetype("arial.ttf", 60)
            font_medium = ImageFont.truetype("arial.ttf", 40)
            font_small = ImageFont.truetype("arial.ttf", 30)
        except:
            font_large = ImageFont.load_default()
            font_medium = ImageFont.load_default()
            font_small = ImageFont.load_default()
            
        # Producto
        producto_text = self.producto_seleccionado['nombre']
        bbox = draw.textbbox((0, 0), producto_text, font=font_large)
        text_width = bbox[2] - bbox[0]
        x = (width - text_width) // 2
        y = height // 3
        draw.text((x, y), producto_text, fill='white', font=font_large)
        
        # Promoción en español
        promo_text = self.promocion_seleccionado['texto']
        # Convertir texto de promoción a español
        if "CUPÓN:" in promo_text:
            promo_espanol = f"🎟️ CUPÓN: {promo_text.split('-')[1].strip()} DE DESCUENTO"
        elif "COMBO:" in promo_text:
            promo_espanol = f"📦 PACK COMBO: {promo_text.split('-')[1].strip()} DE DESCUENTO"
        elif "MAYORISTA:" in promo_text:
            promo_espanol = f"🏢 VENTA POR MAYOR: {promo_text.split('-')[1].strip()} DE DESCUENTO"
        else:
            promo_espanol = f"🎉 PROMOCIÓN: {promo_text.split('-')[1].strip()} DE DESCUENTO"
            
        bbox = draw.textbbox((0, 0), promo_espanol, font=font_medium)
        text_width = bbox[2] - bbox[0]
        x = (width - text_width) // 2
        y = height // 2
        draw.text((x, y), promo_espanol, fill='#f39c12', font=font_medium)
        
        # Precio en español
        precio_text = f"SÓLO ${self.producto_seleccionado['precio']}"
        bbox = draw.textbbox((0, 0), precio_text, font=font_small)
        text_width = bbox[2] - bbox[0]
        x = (width - text_width) // 2
        y = height * 2 // 3
        draw.text((x, y), precio_text, fill='#2ecc71', font=font_small)
        
        # Logo IA
        draw.text((20, 20), "🤖 IA", fill='#ecf0f1', font=font_small)
        
        # Guardar imagen en la ruta configurada
        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        nombre_archivo = f"promo_{self.producto_seleccionado['nombre'].replace(' ', '_')}_{timestamp}.png"
        self.ruta_imagen_guardada = os.path.join(self.ruta_guardado, nombre_archivo)
        img.save(self.ruta_imagen_guardada)
        
        # Cargar para mostrar
        self.imagen_generada = ImageTk.PhotoImage(img.resize((350, 350), Image.Resampling.LANCZOS))
        
    def crear_imagen_placeholder(self, prompt):
        """Crear imagen de placeholder (simulación) - método legacy"""
        self.crear_imagen_placeholder_mejorado(prompt)
        
    def _imagen_generada_exitosamente(self):
        """Manejar éxito en generación de imagen"""
        # Mostrar imagen en canvas
        self.canvas_imagen.delete("all")
        self.canvas_imagen.create_image(175, 175, image=self.imagen_generada)
        
        # Habilitar botones
        self.btn_descargar.config(state=tk.NORMAL)
        self.btn_compartir.config(state=tk.NORMAL)
        
        self.estado_label.config(text="¡Imagen generada con éxito!", fg=self.colors['success'])
        
    def guardar_imagen(self):
        """Guardar imagen en ubicación seleccionada"""
        if not self.ruta_imagen_guardada:
            messagebox.showwarning("Aviso", "No hay imagen para guardar")
            return
            
        ruta = filedialog.asksaveasfilename(
            defaultextension=".png",
            filetypes=[("PNG files", "*.png"), ("JPEG files", "*.jpg"), ("All files", "*.*")],
            initialfile=f"promo_{self.producto_seleccionado['nombre'].replace(' ', '_')}.png"
        )
        
        if ruta:
            try:
                import shutil
                shutil.copy2(self.ruta_imagen_guardada, ruta)
                messagebox.showinfo("Éxito", f"Imagen guardada en: {ruta}")
            except Exception as e:
                messagebox.showerror("Error", f"No se pudo guardar la imagen: {e}")
                
    def enviar_a_banners(self):
        """Enviar imagen generada directamente al módulo de banners"""
        if not self.ruta_imagen_guardada:
            messagebox.showwarning("Aviso", "Por favor genera una imagen primero")
            return
            
        try:
            # Guardar información de la imagen para el módulo de banners
            banner_info = {
                'ruta_imagen': self.ruta_imagen_guardada,
                'nombre': f"IA-{self.producto_seleccionado['nombre']}",
                'producto': self.producto_seleccionado['nombre'],
                'promocion': self.promocion_seleccionada['texto']
            }
            
            # Guardar en archivo temporal para que banners lo lea
            import json
            with open('temp_banner_info.json', 'w') as f:
                json.dump(banner_info, f)
            
            messagebox.showinfo("Enviado a Banners", 
                              "✅ Imagen enviada al módulo de banners\n\n"
                              "1. El módulo de banners se abrirá automáticamente\n"
                              "2. La imagen ya estará precargada\n"
                              "3. Solo configura nombre y fecha de caducidad")
            
            # Cerrar este módulo y abrir banners
            self.root.destroy()
            from banners_ui import ejecutar_banners
            ejecutar_banners(self.empresa_id, self.nombre_negocio)
            
        except Exception as e:
            messagebox.showerror("Error", f"No se pudo enviar a banners: {e}")
    
    def compartir_imagen(self):
        """Compartir imagen (simulación)"""
        messagebox.showinfo("Compartir", "Función de compartir en desarrollo. La imagen está lista para usar en redes sociales.")
        
    def intentar_maximizar(self):
        """Intentar maximizar ventana nuevamente"""
        try:
            self.root.state('zoomed')
        except:
            try:
                self.root.attributes('-zoomed', True)
            except:
                pass  # Si no funciona, ya se estableció un tamaño grande
    
    def cerrar_ventana(self):
        """Manejar el cierre de ventana de forma segura"""
        try:
            # Detener cualquier proceso en ejecución
            if hasattr(self, 'dlna_server_running') and self.dlna_server_running:
                self.dlna_server_running = False
            
            # Limpiar archivos temporales si existen
            import os
            if os.path.exists('temp_banner_info.json'):
                os.remove('temp_banner_info.json')
                
            self.root.quit()
            self.root.destroy()
        except:
            pass

def ejecutar_generador_imagenes(empresa_id, nombre_negocio):
    """Función principal para ejecutar el módulo"""
    try:
        import os
        import subprocess
        import signal
        
        # SOLUCIÓN DRASTICA: Matar cualquier proceso del generador existente
        try:
            # Buscar y matar procesos del generador
            result = subprocess.run(['pkill', '-f', 'generador_imagenes_ui.py'], 
                                  capture_output=True, text=True)
            
            # También buscar procesos Python con títulos del generador
            subprocess.run(['pkill', '-f', 'Generador de Imágenes IA'], 
                          capture_output=True, text=True)
            subprocess.run(['pkill', '-f', 'Generador de Videos IA'], 
                          capture_output=True, text=True)
            subprocess.run(['pkill', '-f', 'Generador de Contenido IA'], 
                          capture_output=True, text=True)
            
            # Esperar un momento para que los procesos mueran
            import time
            time.sleep(0.5)
            
        except Exception as e:
            print(f"Error limpiando procesos: {e}")
        
        # Limpiar lock file
        lock_file = "/tmp/generador_imagenes.lock"
        try:
            if os.path.exists(lock_file):
                os.remove(lock_file)
        except:
            pass
        
        # Crear nuevo lock file
        with open(lock_file, 'w') as f:
            f.write(str(os.getpid()))
        
        root = tk.Tk()
        app = GeneradorImagenesUI(root, empresa_id, nombre_negocio)
        
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
        print("\n🎨 Módulo de generador de imágenes cerrado por el usuario")
    except Exception as e:
        print(f"❌ Error en módulo de generador de imágenes: {e}")
    finally:
        # Limpiar archivos temporales y lock
        try:
            import os
            if os.path.exists('temp_banner_info.json'):
                os.remove('temp_banner_info.json')
            # Eliminar archivo de lock
            lock_file = "/tmp/generador_imagenes.lock"
            if os.path.exists(lock_file):
                os.remove(lock_file)
        except:
            pass

# Solo permitir ejecución desde dashboard
if __name__ == "__main__":
    print("⚠️ Este módulo solo debe ejecutarse desde el dashboard principal")
    print("Por favor, accede desde el menú principal del sistema")
