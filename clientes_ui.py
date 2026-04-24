import tkinter as tk
from tkinter import ttk, messagebox, filedialog
from db import get_connection
import sys
import webbrowser
from urllib.parse import quote
import os
import subprocess
import tempfile
from PIL import Image, ImageTk

class ClientesUI:
    def __init__(self, root, nombre_negocio="NEXUS", empresa_id=1):
        self.root = root
        # Guardamos el ID de la empresa actual
        try:
            self.empresa_id = int(empresa_id)
        except:
            self.empresa_id = 1
        
        # Cargar configuración incluyendo el icono
        self.cargar_configuracion()
            
        self.root.title(f"{self.nombre_negocio} - GESTIÓN DE CLIENTES")
        self.root.geometry("1500x800")
        self.root.configure(bg='#121212')
        
        # Establecer icono de la aplicación si está configurado
        if self.icono_app and os.path.exists(self.icono_app):
            try:
                self.root.iconbitmap(self.icono_app)
            except:
                # Si falla el .ico, intentar con otras imágenes
                try:
                    from PIL import Image, ImageTk
                    img = Image.open(self.icono_app)
                    img.save(tempfile.gettempdir() + "/temp_icon.ico")
                    self.root.iconbitmap(tempfile.gettempdir() + "/temp_icon.ico")
                except:
                    pass
        
        self.colors = {
            'bg': '#121212',
            'card': '#1e1e1e',
            'accent': '#00a8ff',
            'success': '#00db84',
            'danger': '#ff4757',
            'text': '#ffffff',
            'warning': '#f39c12'
        }
        
        self.id_seleccionado = None
        self.foto_cliente_path = None
        self.foto_opcional_path = None
        self.setup_ui()
        self.cargar_datos()
    
    def cargar_configuracion(self):
        """Carga la configuración desde la base de datos incluyendo icono"""
        try:
            conn = get_connection()
            with conn.cursor() as cursor:
                cursor.execute("""
                    SELECT * FROM nombre_negocio 
                    WHERE empresa_id=%s OR id=1 
                    ORDER BY (empresa_id=%s) DESC 
                    LIMIT 1
                """, (self.empresa_id, self.empresa_id))
                res = cursor.fetchone()
                if res:
                    self.nombre_negocio = res.get('nombre_negocio', 'NEXUS')
                    self.icono_app = res.get('icono_app')
                else:
                    self.nombre_negocio = 'NEXUS'
                    self.icono_app = None
            conn.close()
        except Exception:
            self.nombre_negocio = 'NEXUS'
            self.icono_app = None

    def setup_ui(self):
        # --- PANEL IZQUIERDO: FORMULARIO ---
        # Crear un frame principal para el panel izquierdo con scrollbar (40% de ancho)
        left_frame = tk.Frame(self.root, bg=self.colors['bg'])
        left_frame.pack(side=tk.LEFT, fill=tk.BOTH, expand=False, padx=10, pady=10)
        
        # Configurar el panel izquierdo al 40% del ancho de la ventana
        self.root.update()  # Forzar actualización para obtener dimensiones
        window_width = self.root.winfo_width()
        left_width = int(window_width * 0.4)
        left_frame.config(width=left_width)
        left_frame.pack_propagate(False)  # Evitar que el frame se redimensione automáticamente
        
        # Crear un canvas con scrollbar solo para el panel izquierdo
        canvas = tk.Canvas(left_frame, bg=self.colors['bg'], highlightthickness=0)
        scrollbar = ttk.Scrollbar(left_frame, orient="vertical", command=canvas.yview)
        scrollable_frame = tk.Frame(canvas, bg=self.colors['bg'])
        
        scrollable_frame.bind(
            "<Configure>",
            lambda e: canvas.configure(scrollregion=canvas.bbox("all"))
        )
        
        canvas.create_window((0, 0), window=scrollable_frame, anchor="nw")
        canvas.configure(yscrollcommand=scrollbar.set)
        
        # Form frame dentro del scrollable frame
        form_frame = tk.Frame(scrollable_frame, bg=self.colors['card'], padx=20, pady=20)
        form_frame.pack(side=tk.LEFT, fill=tk.Y, padx=10, pady=10)
        
        # Empaquetar canvas y scrollbar en el panel izquierdo
        canvas.pack(side=tk.LEFT, fill=tk.BOTH, expand=True)
        scrollbar.pack(side=tk.RIGHT, fill=tk.Y)
        
        tk.Label(form_frame, text="DATOS DEL CLIENTE", font=('Segoe UI', 12, 'bold'), 
                 bg=self.colors['card'], fg=self.colors['accent']).pack(pady=(0, 20))
        
        self.ent_nombre = self.crear_input(form_frame, "Nombre Completo / Razón Social:")
        self.ent_doc = self.crear_input(form_frame, "Número de Documento / CUIT:")
        self.ent_tel = self.crear_input(form_frame, "Teléfono:")
        self.ent_whatsapp = self.crear_input(form_frame, "Celular WhatsApp:")
        self.var_acepta_ws = tk.BooleanVar(value=True)
        tk.Checkbutton(
            form_frame,
            text="Acepta mensajes por WhatsApp",
            variable=self.var_acepta_ws,
            bg=self.colors['card'],
            fg="white",
            selectcolor="#2d2d2d",
            activebackground=self.colors['card'],
            activeforeground="white",
            cursor="hand2"
        ).pack(anchor="w", pady=(0, 12))
        
        tk.Label(form_frame, text="Tipo Documento:", bg=self.colors['card'], fg="#888", font=('Segoe UI', 9)).pack(anchor="w")
        self.cb_tipo_doc = ttk.Combobox(form_frame, values=["DNI", "CUIT", "CUIL", "PASAPORTE"], state="readonly")
        self.cb_tipo_doc.pack(fill=tk.X, pady=(0, 15))
        self.cb_tipo_doc.set("DNI")
        
        tk.Label(form_frame, text="Condición IVA:", bg=self.colors['card'], fg="#888", font=('Segoe UI', 9)).pack(anchor="w")
        self.cb_iva = ttk.Combobox(form_frame, values=["Consumidor Final", "Monotributista", "Responsable Inscripto", "Exento"], state="readonly")
        self.cb_iva.pack(fill=tk.X, pady=(0, 25))
        self.cb_iva.set("Consumidor Final")

        tk.Label(form_frame, text="Comentarios del cliente:", bg=self.colors['card'], fg="#888", font=('Segoe UI', 9)).pack(anchor="w")
        self.txt_comentarios = tk.Text(form_frame, height=4, bg="#2d2d2d", fg="white", insertbackground="white", borderwidth=0, font=('Segoe UI', 10))
        self.txt_comentarios.pack(fill=tk.X, pady=(0, 15))
        
        # --- SECCIÓN DE FOTOS ---
        tk.Label(form_frame, text="FOTOS DEL CLIENTE", font=('Segoe UI', 11, 'bold'), 
                 bg=self.colors['card'], fg=self.colors['accent']).pack(pady=(15, 10))
        
        # Foto principal del cliente
        foto_frame = tk.Frame(form_frame, bg=self.colors['card'])
        foto_frame.pack(fill=tk.X, pady=(0, 10))
        
        self.ent_foto_cliente = self.crear_input(foto_frame, "Foto del cliente (path):")
        
        btn_foto_cliente_frame = tk.Frame(foto_frame, bg=self.colors['card'])
        btn_foto_cliente_frame.pack(fill=tk.X, pady=(0, 10))
        
        btn_seleccionar_foto = tk.Button(btn_foto_cliente_frame, text="📷 SELECCIONAR FOTO", bg=self.colors['accent'], fg="white", 
                                       font=('Segoe UI', 9, 'bold'), relief="flat", command=self.seleccionar_foto_cliente, cursor="hand2")
        btn_seleccionar_foto.pack(side=tk.LEFT, fill=tk.X, expand=True, ipady=5)
        
        btn_ver_foto = tk.Button(btn_foto_cliente_frame, text="👁️ VER FOTO", bg="#666", fg="white", 
                               font=('Segoe UI', 9, 'bold'), relief="flat", command=self.ver_foto_cliente, cursor="hand2")
        btn_ver_foto.pack(side=tk.RIGHT, fill=tk.X, expand=True, ipady=5, padx=(5, 0))
        
        # Preview de la foto principal
        self.foto_preview_label = tk.Label(foto_frame, text="[Sin imagen]", bg=self.colors['card'], fg="#888", 
                                         font=('Segoe UI', 9), height=8, relief="ridge", bd=2)
        self.foto_preview_label.pack(fill=tk.X, pady=(0, 10))
        
        # Foto opcional
        self.ent_foto_opcional = self.crear_input(foto_frame, "Foto opcional (path):")
        
        btn_foto_opcional_frame = tk.Frame(foto_frame, bg=self.colors['card'])
        btn_foto_opcional_frame.pack(fill=tk.X, pady=(0, 10))
        
        btn_seleccionar_foto_opcional = tk.Button(btn_foto_opcional_frame, text="📷 SELECCIONAR FOTO OPCIONAL", bg=self.colors['accent'], fg="white", 
                                                font=('Segoe UI', 9, 'bold'), relief="flat", command=self.seleccionar_foto_opcional, cursor="hand2")
        btn_seleccionar_foto_opcional.pack(side=tk.LEFT, fill=tk.X, expand=True, ipady=5)
        
        btn_ver_foto_opcional = tk.Button(btn_foto_opcional_frame, text="👁️ VER FOTO", bg="#666", fg="white", 
                                        font=('Segoe UI', 9, 'bold'), relief="flat", command=self.ver_foto_opcional, cursor="hand2")
        btn_ver_foto_opcional.pack(side=tk.RIGHT, fill=tk.X, expand=True, ipady=5, padx=(5, 0))
        
        # Preview de la foto opcional
        self.foto_opcional_preview_label = tk.Label(foto_frame, text="[Sin imagen]", bg=self.colors['card'], fg="#888", 
                                                   font=('Segoe UI', 9), height=8, relief="ridge", bd=2)
        self.foto_opcional_preview_label.pack(fill=tk.X, pady=(0, 15))
        
        # Botones de acción
        btn_save = tk.Button(form_frame, text="💾 GUARDAR CLIENTE", bg=self.colors['success'], fg="black", 
                             font=('Segoe UI', 10, 'bold'), relief="flat", command=self.guardar_cliente, cursor="hand2")
        btn_save.pack(fill=tk.X, pady=5, ipady=8)

        btn_cf = tk.Button(form_frame, text="👤 CARGAR CONSUMIDOR FINAL", bg=self.colors['warning'], fg="black",
                           font=('Segoe UI', 9, 'bold'), relief="flat", command=self.cargar_consumidor_final, cursor="hand2")
        btn_cf.pack(fill=tk.X, pady=5, ipady=6)
        
        btn_clear = tk.Button(form_frame, text="LIMPIAR", bg="#333", fg="white", 
                              relief="flat", command=self.limpiar_campos, cursor="hand2")
        btn_clear.pack(fill=tk.X, pady=5)

        # --- PANEL DERECHO: TABLA ---
        # El panel derecho ocupará automáticamente el 60% restante
        table_frame = tk.Frame(self.root, bg=self.colors['bg'], padx=10, pady=10)
        table_frame.pack(side=tk.RIGHT, fill=tk.BOTH, expand=True)
        
        # Cabecera de búsqueda
        search_header = tk.Frame(table_frame, bg=self.colors['bg'])
        search_header.pack(fill=tk.X, pady=(0, 10))
        
        tk.Label(search_header, text=f"Empresa ID: {self.empresa_id}", bg=self.colors['bg'], fg="#555", font=('Consolas', 9)).pack(side=tk.RIGHT)
        tk.Label(search_header, text="🔍 Buscar cliente:", bg=self.colors['bg'], fg="white", font=('Segoe UI', 10)).pack(side=tk.LEFT, padx=5)
        
        self.ent_buscar = tk.Entry(search_header, bg=self.colors['card'], fg="white", borderwidth=0, font=('Segoe UI', 11))
        self.ent_buscar.pack(side=tk.LEFT, fill=tk.X, expand=True, ipady=5, padx=10)
        self.ent_buscar.bind("<KeyRelease>", lambda e: self.cargar_datos(self.ent_buscar.get()))

        # Tabla con estilo personalizado
        style = ttk.Style()
        style.theme_use("clam")
        style.configure("Treeview", background=self.colors['card'], foreground="white", fieldbackground=self.colors['card'], borderwidth=0)
        style.map("Treeview", background=[('selected', self.colors['accent'])])

        columns = ("Foto", "ID", "Nombre", "Documento", "Tipo", "IVA", "Telefono", "WhatsApp")
        self.tree = ttk.Treeview(table_frame, columns=columns, show="headings", height=15)
        
        for col in columns:
            self.tree.heading(col, text=col)
            self.tree.column(col, width=100, anchor="center")
        
        self.tree.column("Foto", width=40, anchor="center")
        self.tree.column("Nombre", width=200, anchor="w")
        self.tree.column("Telefono", width=110, anchor="center")
        self.tree.column("WhatsApp", width=130, anchor="center")
        self.tree.pack(fill=tk.BOTH, expand=True)
        self.tree.bind("<<TreeviewSelect>>", self.seleccionar_cliente)

        historial_frame = tk.Frame(table_frame, bg=self.colors['card'], padx=10, pady=10)
        historial_frame.pack(fill=tk.X, pady=(10, 0))
        tk.Label(historial_frame, text="Historial de compras del cliente seleccionado", bg=self.colors['card'], fg=self.colors['accent'], font=('Segoe UI', 10, 'bold')).pack(anchor="w")
        self.txt_historial = tk.Text(historial_frame, height=7, bg="#2d2d2d", fg="white", borderwidth=0, font=('Consolas', 9))
        self.txt_historial.pack(fill=tk.X, pady=(6, 0))
        self.txt_historial.insert("1.0", "Seleccione un cliente para ver que se le vendio.")
        self.txt_historial.config(state="disabled")

        # Footer de tabla
        footer_btn = tk.Frame(table_frame, bg=self.colors['bg'])
        footer_btn.pack(fill=tk.X, pady=10)
        
        tk.Button(footer_btn, text="📲 ENVIAR WHATSAPP", bg=self.colors['accent'], fg="white",
                  font=('Segoe UI', 9, 'bold'), relief="flat", command=self.enviar_whatsapp_agradecimiento, cursor="hand2").pack(side=tk.LEFT)
        tk.Button(footer_btn, text="🗑️ ELIMINAR SELECCIONADO", bg=self.colors['danger'], fg="white",
                  font=('Segoe UI', 9, 'bold'), relief="flat", command=self.eliminar_cliente, cursor="hand2").pack(side=tk.RIGHT)

    def crear_input(self, master, label):
        tk.Label(master, text=label, bg=self.colors['card'], fg="#888", font=('Segoe UI', 9)).pack(anchor="w")
        entry = tk.Entry(master, bg="#2d2d2d", fg="white", insertbackground="white", borderwidth=0, font=('Segoe UI', 11))
        entry.pack(fill=tk.X, pady=(0, 15), ipady=5)
        return entry

    def cargar_datos(self, filtro=""):
        for item in self.tree.get_children():
            self.tree.delete(item)
            
        try:
            conn = get_connection()
            with conn.cursor() as cursor:
                # FILTRO ESTRICTO POR EMPRESA_ID
                sql = """
                    SELECT id, nombre, documento, tipo_documento, condicion_iva, telefono, whatsapp, acepta_whatsapp, comentarios, foto_cliente, foto_opcional
                    FROM clientes
                    WHERE empresa_id = %s
                """
                params = [self.empresa_id]
                
                if filtro:
                    sql += " AND (nombre LIKE %s OR documento LIKE %s)"
                    params.extend([f'%{filtro}%', f'%{filtro}%'])
                
                cursor.execute(sql, params)
                for row in cursor.fetchall():
                    foto_icon = "📷" if row.get('foto_cliente') else ""
                    self.tree.insert("", tk.END, values=(
                        foto_icon, row['id'], row['nombre'], row['documento'], row['tipo_documento'], row['condicion_iva'],
                        row.get('telefono') or "", row.get('whatsapp') or ""
                    ))
            conn.close()
        except Exception:
            pass

    def seleccionar_cliente(self, event):
        item = self.tree.selection()
        if item:
            valores = self.tree.item(item, "values")
            self.id_seleccionado = valores[1]  # ID ahora está en la posición 1
            self.ent_nombre.delete(0, tk.END); self.ent_nombre.insert(0, valores[2])
            self.ent_doc.delete(0, tk.END); self.ent_doc.insert(0, valores[3])
            self.cb_tipo_doc.set(valores[4])
            self.cb_iva.set(valores[5])
            self.ent_tel.delete(0, tk.END); self.ent_tel.insert(0, valores[6])
            self.ent_whatsapp.delete(0, tk.END); self.ent_whatsapp.insert(0, valores[7])
            # Cargar el estado de acepta_whatsapp desde la base de datos
            self.cargar_acepta_whatsapp(self.id_seleccionado)
            self.cargar_comentarios_cliente(self.id_seleccionado)
            self.cargar_historial_cliente(self.id_seleccionado)
            self.cargar_fotos_cliente(self.id_seleccionado)

    def guardar_cliente(self):
        nombre = self.ent_nombre.get().strip()
        doc = self.ent_doc.get().strip()
        tipo = self.cb_tipo_doc.get()
        iva = self.cb_iva.get()
        telefono = self.ent_tel.get().strip()
        whatsapp = self.normalizar_whatsapp(self.ent_whatsapp.get().strip())
        acepta_whatsapp = 1 if self.var_acepta_ws.get() else 0
        comentarios = self.txt_comentarios.get("1.0", tk.END).strip()
        foto_cliente = self.ent_foto_cliente.get().strip() or None
        foto_opcional = self.ent_foto_opcional.get().strip() or None

        if not nombre:
            messagebox.showwarning("Faltan datos", "El nombre del cliente es obligatorio.")
            return

        try:
            conn = get_connection()
            with conn.cursor() as cursor:
                if self.id_seleccionado:
                    # Actualizar existente
                    sql = """
                        UPDATE clientes
                        SET nombre=%s, documento=%s, tipo_documento=%s, condicion_iva=%s, telefono=%s, whatsapp=%s, acepta_whatsapp=%s, comentarios=%s, foto_cliente=%s, foto_opcional=%s
                        WHERE id=%s AND empresa_id=%s
                    """
                    cursor.execute(sql, (nombre, doc, tipo, iva, telefono, whatsapp, acepta_whatsapp, comentarios, foto_cliente, foto_opcional, self.id_seleccionado, self.empresa_id))
                else:
                    # Insertar nuevo con el empresa_id actual
                    sql = """
                        INSERT INTO clientes (empresa_id, nombre, documento, tipo_documento, condicion_iva, telefono, whatsapp, acepta_whatsapp, comentarios, foto_cliente, foto_opcional)
                        VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                    """
                    cursor.execute(sql, (self.empresa_id, nombre, doc, tipo, iva, telefono, whatsapp, acepta_whatsapp, comentarios, foto_cliente, foto_opcional))
            conn.commit()
            conn.close()
            messagebox.showinfo("Éxito", "Cliente guardado correctamente.")
            self.limpiar_campos()
            self.cargar_datos()
        except Exception as e:
            messagebox.showerror("Error DB", f"No se pudo guardar: {e}")

    def eliminar_cliente(self):
        if not self.id_seleccionado:
            messagebox.showwarning("Selección", "Por favor, elija un cliente de la lista.")
            return
        
        if messagebox.askyesno("Confirmar", "¿Eliminar este cliente definitivamente?"):
            try:
                conn = get_connection()
                with conn.cursor() as cursor:
                    # Seguridad: Solo eliminar si pertenece a la empresa actual
                    cursor.execute("DELETE FROM clientes WHERE id=%s AND empresa_id=%s", (self.id_seleccionado, self.empresa_id))
                conn.commit()
                conn.close()
                self.limpiar_campos()
                self.cargar_datos()
            except Exception as e:
                messagebox.showerror("Error", "No se puede eliminar: El cliente tiene ventas registradas.")

    def limpiar_campos(self):
        self.id_seleccionado = None
        self.ent_nombre.delete(0, tk.END)
        self.ent_doc.delete(0, tk.END)
        self.ent_tel.delete(0, tk.END)
        self.ent_whatsapp.delete(0, tk.END)
        self.txt_comentarios.delete("1.0", tk.END)
        self.ent_foto_cliente.delete(0, tk.END)
        self.ent_foto_opcional.delete(0, tk.END)
        self.foto_cliente_path = None
        self.foto_opcional_path = None
        self.var_acepta_ws.set(True)
        self.cb_tipo_doc.set("DNI")
        self.cb_iva.set("Consumidor Final")
        self.tree.selection_remove(self.tree.selection())
        self._set_historial_texto("Seleccione un cliente para ver que se le vendio.")
        self.limpiar_previews()

    @staticmethod
    def normalizar_whatsapp(numero):
        return "".join(ch for ch in str(numero) if ch.isdigit())

    def cargar_consumidor_final(self):
        self.ent_nombre.delete(0, tk.END)
        self.ent_nombre.insert(0, "CONSUMIDOR FINAL")
        self.ent_doc.delete(0, tk.END)
        self.ent_doc.insert(0, "0")
        self.cb_tipo_doc.set("DNI")
        self.cb_iva.set("Consumidor Final")
        self.ent_tel.delete(0, tk.END)
        self.ent_whatsapp.delete(0, tk.END)
        self.txt_comentarios.delete("1.0", tk.END)
        self.txt_comentarios.insert("1.0", "Cliente de mostrador sin datos fiscales adicionales.")
        self.ent_foto_cliente.delete(0, tk.END)
        self.ent_foto_opcional.delete(0, tk.END)
        self.foto_cliente_path = None
        self.foto_opcional_path = None
        self.var_acepta_ws.set(False)

    def enviar_whatsapp_agradecimiento(self):
        item = self.tree.selection()
        if not item:
            messagebox.showwarning("Selección", "Selecciona un cliente primero.")
            return

        valores = self.tree.item(item, "values")
        nombre = str(valores[1]).strip()
        whatsapp = self.normalizar_whatsapp(valores[6])
        optin = str(valores[7]).upper() == "SI"

        if not whatsapp:
            messagebox.showwarning("WhatsApp", "El cliente no tiene número de WhatsApp cargado.")
            return
        if not optin:
            messagebox.showwarning("Consentimiento", "El cliente no acepta mensajes por WhatsApp.")
            return

        mensaje = (
            f"Hola {nombre}, muchas gracias por tu compra en {self.root.title().split(' - ')[0]}! "
            "Esperamos verte pronto."
        )
        url = f"https://wa.me/{whatsapp}?text={quote(mensaje)}"
        webbrowser.open(url)

    def cargar_acepta_whatsapp(self, cliente_id):
        """Carga el estado de acepta_whatsapp desde la base de datos"""
        try:
            conn = get_connection()
            with conn.cursor() as cursor:
                cursor.execute("SELECT acepta_whatsapp FROM clientes WHERE id=%s AND empresa_id=%s", (cliente_id, self.empresa_id))
                row = cursor.fetchone()
            conn.close()
            
            if row:
                self.var_acepta_ws.set(bool(row.get('acepta_whatsapp', 0)))
        except Exception:
            pass

    def cargar_comentarios_cliente(self, cliente_id):
        try:
            conn = get_connection()
            with conn.cursor() as cursor:
                cursor.execute("SELECT comentarios FROM clientes WHERE id=%s AND empresa_id=%s", (cliente_id, self.empresa_id))
                row = cursor.fetchone()
            conn.close()
            self.txt_comentarios.delete("1.0", tk.END)
            if row and row.get("comentarios"):
                self.txt_comentarios.insert("1.0", row.get("comentarios"))
        except Exception:
            pass

    def _set_historial_texto(self, texto):
        self.txt_historial.config(state="normal")
        self.txt_historial.delete("1.0", tk.END)
        self.txt_historial.insert("1.0", texto)
        self.txt_historial.config(state="disabled")

    def cargar_historial_cliente(self, cliente_id):
        try:
            conn = get_connection()
            with conn.cursor() as cursor:
                cursor.execute("""
                    SELECT
                        v.id,
                        DATE_FORMAT(v.fecha, '%%d/%%m/%%Y %%H:%%i') AS fecha,
                        v.total,
                        GROUP_CONCAT(CONCAT(COALESCE(p.nombre, 'ITEM'), ' x', CAST(vi.cantidad AS CHAR)) SEPARATOR ', ') AS productos
                    FROM ventas v
                    LEFT JOIN venta_items vi ON vi.venta_id = v.id
                    LEFT JOIN productos p ON p.id = vi.producto_id
                    WHERE v.empresa_id = %s AND v.cliente_id = %s
                    GROUP BY v.id, v.fecha, v.total
                    ORDER BY v.fecha DESC
                    LIMIT 10
                """, (self.empresa_id, cliente_id))
                filas = cursor.fetchall()
            conn.close()

            if not filas:
                self._set_historial_texto("Este cliente aun no tiene ventas registradas.")
                return

            lineas = []
            for f in filas:
                lineas.append(f"Venta #{f['id']} | {f['fecha']} | $ {float(f['total'] or 0):.2f}")
                lineas.append(f"  Productos: {f.get('productos') or 'Sin detalle'}")
                lineas.append("")
            self._set_historial_texto("\n".join(lineas).strip())
        except Exception:
            self._set_historial_texto("No se pudo cargar el historial de compras.")

    # --- MÉTODOS PARA MANEJO DE FOTOS ---
    
    def seleccionar_foto_cliente(self):
        """Abre un diálogo para seleccionar la foto principal del cliente"""
        archivo = filedialog.askopenfilename(
            title="Seleccionar foto del cliente",
            filetypes=[
                ("Archivos de imagen", "*.jpg *.jpeg *.png *.gif *.bmp *.webp"),
                ("Todos los archivos", "*.*")
            ]
        )
        if archivo:
            self.ent_foto_cliente.delete(0, tk.END)
            self.ent_foto_cliente.insert(0, archivo)
            self.foto_cliente_path = archivo
            self.actualizar_preview_foto(archivo, self.foto_preview_label)
    
    def seleccionar_foto_opcional(self):
        """Abre un diálogo para seleccionar la foto opcional del cliente"""
        archivo = filedialog.askopenfilename(
            title="Seleccionar foto opcional del cliente",
            filetypes=[
                ("Archivos de imagen", "*.jpg *.jpeg *.png *.gif *.bmp *.webp"),
                ("Todos los archivos", "*.*")
            ]
        )
        if archivo:
            self.ent_foto_opcional.delete(0, tk.END)
            self.ent_foto_opcional.insert(0, archivo)
            self.foto_opcional_path = archivo
            self.actualizar_preview_foto(archivo, self.foto_opcional_preview_label)
    
    def ver_foto_cliente(self):
        """Abre la foto principal del cliente con el visor de imágenes del sistema"""
        path = self.ent_foto_cliente.get().strip()
        if path and os.path.exists(path):
            try:
                if sys.platform == "win32":
                    os.startfile(path)
                elif sys.platform == "darwin":
                    subprocess.run(["open", path])
                else:
                    subprocess.run(["xdg-open", path])
            except Exception as e:
                messagebox.showerror("Error", f"No se pudo abrir la foto: {e}")
        else:
            messagebox.showwarning("Foto no encontrada", "No hay una foto seleccionada o el archivo no existe.")
    
    def ver_foto_opcional(self):
        """Abre la foto opcional del cliente con el visor de imágenes del sistema"""
        path = self.ent_foto_opcional.get().strip()
        if path and os.path.exists(path):
            try:
                if sys.platform == "win32":
                    os.startfile(path)
                elif sys.platform == "darwin":
                    subprocess.run(["open", path])
                else:
                    subprocess.run(["xdg-open", path])
            except Exception as e:
                messagebox.showerror("Error", f"No se pudo abrir la foto: {e}")
        else:
            messagebox.showwarning("Foto no encontrada", "No hay una foto seleccionada o el archivo no existe.")
    
    def cargar_fotos_cliente(self, cliente_id):
        """Carga los paths de las fotos del cliente desde la base de datos"""
        try:
            conn = get_connection()
            with conn.cursor() as cursor:
                cursor.execute("SELECT foto_cliente, foto_opcional FROM clientes WHERE id=%s AND empresa_id=%s", (cliente_id, self.empresa_id))
                row = cursor.fetchone()
            conn.close()
            
            self.ent_foto_cliente.delete(0, tk.END)
            self.ent_foto_opcional.delete(0, tk.END)
            
            if row:
                if row.get("foto_cliente"):
                    self.ent_foto_cliente.insert(0, row["foto_cliente"])
                    self.foto_cliente_path = row["foto_cliente"]
                    self.actualizar_preview_foto(row["foto_cliente"], self.foto_preview_label)
                else:
                    self.foto_cliente_path = None
                    self.limpiar_preview(self.foto_preview_label)
                
                if row.get("foto_opcional"):
                    self.ent_foto_opcional.insert(0, row["foto_opcional"])
                    self.foto_opcional_path = row["foto_opcional"]
                    self.actualizar_preview_foto(row["foto_opcional"], self.foto_opcional_preview_label)
                else:
                    self.foto_opcional_path = None
                    self.limpiar_preview(self.foto_opcional_preview_label)
        except Exception:
            pass
    
    def actualizar_preview_foto(self, path, label):
        """Actualiza el preview de una foto"""
        try:
            if path and os.path.exists(path):
                # Cargar y redimensionar la imagen
                image = Image.open(path)
                image.thumbnail((200, 150), Image.Resampling.LANCZOS)
                photo = ImageTk.PhotoImage(image)
                
                # Actualizar el label
                label.configure(image=photo, text="")
                label.image = photo  # Mantener referencia
            else:
                self.limpiar_preview(label)
        except Exception:
            self.limpiar_preview(label)
    
    def limpiar_preview(self, label):
        """Limpia el preview de una foto"""
        label.configure(image="", text="[Sin imagen]")
        label.image = None
    
    def limpiar_previews(self):
        """Limpia todos los previews de fotos"""
        self.limpiar_preview(self.foto_preview_label)
        self.limpiar_preview(self.foto_opcional_preview_label)

if __name__ == "__main__":
    root = tk.Tk()
    # Para probar con la empresa 1
    app = ClientesUI(root, "NEXUS", 1)
    root.mainloop()