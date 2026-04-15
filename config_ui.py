import tkinter as tk
from tkinter import ttk, messagebox, filedialog
from db import get_connection
import sys
import os

class ConfigUI:
    def __init__(self, root, nombre_negocio_actual="SISTEMA", empresa_id=1):
        self.root = root
        try: 
            self.empresa_id = int(str(empresa_id).strip())
        except: 
            self.empresa_id = 0

        self.root.title(f"Configuración - {nombre_negocio_actual}")
        self.root.geometry("600x850") 
        self.root.resizable(False, False)
        
        self.colors = {
            'bg': '#121212', 'card': '#1e1e1e', 'accent': '#9c27b0', 
            'text': '#ffffff', 'border': '#333333', 'success': '#00db84',
            'mp_blue': '#009ee3', 'pw_red': '#ee2e24', 'modo_green': '#5cb85c',
            'warning': '#f39c12'
        }
        
        self.root.configure(bg=self.colors['bg'])
        self.configurar_estilo()
        self.init_ui()
        self.cargar_datos()

    def configurar_estilo(self):
        style = ttk.Style()
        style.theme_use('clam')
        style.configure("TNotebook", background=self.colors['bg'], borderwidth=0)
        style.configure("TNotebook.Tab", background=self.colors['card'], foreground="#888", padding=[12, 8], font=('Segoe UI', 9))
        style.map("TNotebook.Tab", background=[("selected", self.colors['accent'])], foreground=[("selected", "white")])

    def init_ui(self):
        # Header
        header = tk.Frame(self.root, bg=self.colors['bg'], pady=15)
        header.pack(fill=tk.X)
        tk.Label(header, text="⚙️ PANEL DE CONTROL", font=('Segoe UI', 16, 'bold'), bg=self.colors['bg'], fg=self.colors['accent']).pack()
        
        self.notebook = ttk.Notebook(self.root)
        self.notebook.pack(fill=tk.BOTH, expand=True, padx=15, pady=5)

        # PESTAÑA 1: EMPRESA
        self.tab_empresa = tk.Frame(self.notebook, bg=self.colors['bg'], padx=20, pady=20)
        self.notebook.add(self.tab_empresa, text=" 🏢 EMPRESA ")
        self.setup_tab_empresa()

        # PESTAÑA 2: VENTAS
        self.tab_ventas = tk.Frame(self.notebook, bg=self.colors['bg'], padx=20, pady=20)
        self.notebook.add(self.tab_ventas, text=" 🛒 VENTAS ")
        self.setup_tab_ventas()

        # PESTAÑA 3: RUTAS
        self.tab_paths = tk.Frame(self.notebook, bg=self.colors['bg'], padx=20, pady=20)
        self.notebook.add(self.tab_paths, text=" 📂 RUTAS ")
        self.setup_tab_paths()

        # PESTAÑA 4: PAGOS
        self.tab_pagos = tk.Frame(self.notebook, bg=self.colors['bg'], padx=20, pady=20)
        self.notebook.add(self.tab_pagos, text=" 💳 PAGOS ")
        self.setup_tab_pagos()

        # Botón Guardar (Fijo al final)
        btn_frame = tk.Frame(self.root, bg=self.colors['bg'], pady=20)
        btn_frame.pack(fill=tk.X)
        btn_save_all = tk.Button(btn_frame, text="GUARDAR CAMBIOS CONFIGURACIÓN", font=('Segoe UI', 11, 'bold'), 
                                 bg=self.colors['success'], fg="black", relief="flat", cursor="hand2",
                                 command=self.guardar_todo)
        btn_save_all.pack(fill=tk.X, padx=40, ipady=12)

    def setup_tab_empresa(self):
        self.ent_nombre = self.crear_campo(self.tab_empresa, "Nombre del Negocio:")
        self.ent_eslogan = self.crear_campo(self.tab_empresa, "Eslogan / Subtítulo:")
        self.ent_cuit = self.crear_campo(self.tab_empresa, "CUIT:")
        self.ent_direccion = self.crear_campo(self.tab_empresa, "Dirección Comercial:")
        self.ent_iva_cond = self.crear_campo(self.tab_empresa, "Condición IVA (Ej: Resp. Insc.):")
        
        tk.Label(self.tab_empresa, text="IMPUESTOS Y MÁRGENES", bg=self.colors['bg'], fg=self.colors['accent'], font=('Segoe UI', 9, 'bold')).pack(anchor="w", pady=(20, 5))
        tax_frame = tk.Frame(self.tab_empresa, bg=self.colors['bg'])
        tax_frame.pack(fill=tk.X)
        self.ent_iva = self.crear_campo_pequeno(tax_frame, "IVA (%)", side=tk.LEFT)
        self.ent_iibb = self.crear_campo_pequeno(tax_frame, "IIBB (%)", side=tk.LEFT)
        self.ent_ganancia = self.crear_campo_pequeno(tax_frame, "Margen (%)", side=tk.RIGHT)

    def setup_tab_ventas(self):
        self.ent_moneda = self.crear_campo(self.tab_ventas, "Símbolo de Moneda (ej: $):")
        
        tk.Frame(self.tab_ventas, height=20, bg=self.colors['bg']).pack()
        
        group_peso = tk.LabelFrame(self.tab_ventas, text=" Configuración de Balanza / Peso ", bg=self.colors['bg'], fg=self.colors['success'], font=('Segoe UI', 10, 'bold'), padx=15, pady=15)
        group_peso.pack(fill=tk.X)
        
        self.var_fraccion = tk.BooleanVar()
        chk_fraccion = tk.Checkbutton(
            group_peso, text="HABILITAR VENTAS POR PESO (Decimales)",
            variable=self.var_fraccion, bg=self.colors['bg'], fg="white",
            selectcolor=self.colors['card'], activebackground=self.colors['bg'],
            font=('Segoe UI', 10)
        )
        chk_fraccion.pack(anchor="w")

    def setup_tab_paths(self):
        tk.Label(self.tab_paths, text="📁 ALMACENAMIENTO DE TICKETS", bg=self.colors['bg'], fg=self.colors['accent'], font=('Segoe UI', 10, 'bold')).pack(anchor="w", pady=(0, 5))
        self.ent_ruta_tickets = self.crear_campo_archivo(self.tab_paths)
        
        tk.Frame(self.tab_paths, height=30, bg=self.colors['bg']).pack()
        
        tk.Label(self.tab_paths, text="🖼️ CARPETA DE IMÁGENES DE PRODUCTOS", bg=self.colors['bg'], fg=self.colors['accent'], font=('Segoe UI', 10, 'bold')).pack(anchor="w", pady=(0, 5))
        self.ent_ruta_imgs = self.crear_campo_archivo(self.tab_paths)

    def setup_tab_pagos(self):
        # Mercado Pago
        group_mp = tk.LabelFrame(self.tab_pagos, text=" MERCADO PAGO ", bg=self.colors['bg'], fg=self.colors['mp_blue'], font=('Segoe UI', 9, 'bold'), padx=10, pady=10)
        group_mp.pack(fill=tk.X, pady=5)
        self.ent_mp_token = self.crear_campo(group_mp, "Access Token:")
        self.ent_mp_id = self.crear_campo(group_mp, "User ID:")
        self.ent_mp_caja = self.crear_campo(group_mp, "External ID (Caja):")

        # PayWay
        group_pw = tk.LabelFrame(self.tab_pagos, text=" PAYWAY ", bg=self.colors['bg'], fg=self.colors['pw_red'], font=('Segoe UI', 9, 'bold'), padx=10, pady=10)
        group_pw.pack(fill=tk.X, pady=5)
        self.ent_pw_key = self.crear_campo(group_pw, "PayWay API Key:")

        # MODO
        group_modo = tk.LabelFrame(self.tab_pagos, text=" MODO ", bg=self.colors['bg'], fg=self.colors['modo_green'], font=('Segoe UI', 9, 'bold'), padx=10, pady=10)
        group_modo.pack(fill=tk.X, pady=5)
        self.ent_modo_key = self.crear_campo(group_modo, "MODO API Key:")

    def crear_campo(self, master, label_text):
        tk.Label(master, text=label_text, bg=self.colors['bg'], fg="#aaa", font=('Segoe UI', 9)).pack(anchor="w", pady=(10, 2))
        entry = tk.Entry(master, font=('Segoe UI', 11), bg=self.colors['card'], fg="white", borderwidth=0, insertbackground="white")
        entry.pack(fill=tk.X, ipady=8); tk.Frame(master, height=1, bg=self.colors['border']).pack(fill=tk.X)
        return entry

    def crear_campo_pequeno(self, master, label_text, side):
        frame = tk.Frame(master, bg=self.colors['bg'])
        frame.pack(side=side, fill=tk.X, expand=True, padx=5)
        tk.Label(frame, text=label_text, bg=self.colors['bg'], fg="#aaa", font=('Segoe UI', 9)).pack(anchor="w")
        entry = tk.Entry(frame, font=('Segoe UI', 11, 'bold'), bg=self.colors['card'], fg=self.colors['success'], borderwidth=0, justify="center")
        entry.pack(fill=tk.X, ipady=8)
        return entry

    def crear_campo_archivo(self, master):
        container = tk.Frame(master, bg=self.colors['bg'])
        container.pack(fill=tk.X)
        entry = tk.Entry(container, font=('Segoe UI', 10), bg=self.colors['card'], fg="white", borderwidth=0)
        entry.pack(side=tk.LEFT, fill=tk.X, expand=True, ipady=8)
        tk.Button(container, text=" EXPLORAR ", bg=self.colors['border'], fg="white", font=('Segoe UI', 8, 'bold'), relief="flat",
                  command=lambda e=entry: self.seleccionar_ruta(e)).pack(side=tk.RIGHT, padx=5)
        return entry

    def seleccionar_ruta(self, entry_widget):
        directorio = filedialog.askdirectory()
        if directorio:
            entry_widget.delete(0, tk.END)
            entry_widget.insert(0, directorio)

    def cargar_datos(self):
        try:
            conn = get_connection()
            with conn.cursor() as cursor:
                cursor.execute("SELECT * FROM nombre_negocio WHERE empresa_id=%s OR id=1 LIMIT 1", (self.empresa_id,))
                res = cursor.fetchone()
                if res:
                    self.ent_nombre.insert(0, res.get('nombre_negocio') or "")
                    self.ent_eslogan.insert(0, res.get('eslogan') or "")
                    self.ent_moneda.insert(0, res.get('moneda') or "$")
                    self.ent_cuit.insert(0, res.get('cuit') or "")
                    self.ent_direccion.insert(0, res.get('direccion') or "")
                    self.ent_iva_cond.insert(0, res.get('condicion_iva') or "")
                    self.ent_iva.insert(0, str(res.get('impuesto') or "0.00"))
                    self.ent_iibb.insert(0, str(res.get('ingresos_brutos') or "0.00"))
                    self.ent_ganancia.insert(0, str(res.get('ganancia_sugerida') or "0.00"))
                    self.ent_ruta_tickets.insert(0, res.get('ruta_tickets') or "")
                    self.ent_ruta_imgs.insert(0, res.get('ruta_imagenes') or "")
                    self.var_fraccion.set(bool(res.get('permitir_fraccion', False)))

                cursor.execute("SELECT * FROM config_pagos WHERE empresa_id=%s OR id=1 LIMIT 1", (self.empresa_id,))
                cp = cursor.fetchone()
                if cp:
                    self.ent_mp_token.insert(0, cp.get('mp_access_token') or "")
                    self.ent_mp_id.insert(0, cp.get('mp_user_id') or "")
                    self.ent_mp_caja.insert(0, cp.get('mp_external_id') or "")
                    self.ent_pw_key.insert(0, cp.get('pw_api_key') or "")
                    self.ent_modo_key.insert(0, cp.get('modo_api_key') or "")
            conn.close()
        except Exception as e: print(f"Error cargar datos: {e}")

    def guardar_todo(self):
        try:
            conn = get_connection()
            with conn.cursor() as cursor:
                sql_gen = """UPDATE nombre_negocio SET 
                             nombre_negocio=%s, eslogan=%s, moneda=%s, cuit=%s, direccion=%s, 
                             condicion_iva=%s, impuesto=%s, ingresos_brutos=%s, ganancia_sugerida=%s, 
                             ruta_tickets=%s, ruta_imagenes=%s, permitir_fraccion=%s 
                             WHERE empresa_id=%s OR id=1"""
                
                cursor.execute(sql_gen, (
                    self.ent_nombre.get(), self.ent_eslogan.get(), self.ent_moneda.get(), 
                    self.ent_cuit.get(), self.ent_direccion.get(), self.ent_iva_cond.get(), 
                    float(self.ent_iva.get() or 0), float(self.ent_iibb.get() or 0),
                    float(self.ent_ganancia.get() or 0), self.ent_ruta_tickets.get(), 
                    self.ent_ruta_imgs.get(), self.var_fraccion.get(), self.empresa_id
                ))

                sql_pay = """UPDATE config_pagos SET 
                             mp_access_token=%s, mp_user_id=%s, mp_external_id=%s, 
                             pw_api_key=%s, modo_api_key=%s 
                             WHERE empresa_id=%s OR id=1"""
                
                cursor.execute(sql_pay, (
                    self.ent_mp_token.get(), self.ent_mp_id.get(), self.ent_mp_caja.get(),
                    self.ent_pw_key.get(), self.ent_modo_key.get(), self.empresa_id
                ))
                conn.commit()
            conn.close()
            messagebox.showinfo("Éxito", "Configuración global actualizada correctamente.")
            self.root.destroy()
        except Exception as e:
            messagebox.showerror("Error", f"Fallo al guardar: {e}")

if __name__ == "__main__":
    root = tk.Tk()
    app = ConfigUI(root, "NEXUS", 1)
    root.mainloop()