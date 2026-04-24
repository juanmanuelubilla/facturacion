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
        self.root.geometry("600x980") 
        self.root.resizable(False, False)
        
        self.colors = {
            'bg': '#121212', 'card': '#1e1e1e', 'accent': '#9c27b0', 
            'text': '#ffffff', 'border': '#333333', 'success': '#00db84',
            'mp_blue': '#009ee3', 'pw_red': '#ee2e24', 'modo_green': '#5cb85c',
            'warning': '#f39c12', 'afip_blue': '#005b96'
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
        header = tk.Frame(self.root, bg=self.colors['bg'], pady=15)
        header.pack(fill=tk.X)
        tk.Label(header, text="⚙️ PANEL DE CONTROL", font=('Segoe UI', 16, 'bold'), bg=self.colors['bg'], fg=self.colors['accent']).pack()
        
        self.notebook = ttk.Notebook(self.root)
        self.notebook.pack(fill=tk.BOTH, expand=True, padx=15, pady=5)

        self.tab_empresa = tk.Frame(self.notebook, bg=self.colors['bg'], padx=20, pady=20)
        self.notebook.add(self.tab_empresa, text=" 🏢 EMPRESA ")
        self.setup_tab_empresa()

        self.tab_afip = tk.Frame(self.notebook, bg=self.colors['bg'], padx=20, pady=20)
        self.notebook.add(self.tab_afip, text=" ⚖️ AFIP/ARCA ")
        self.setup_tab_afip()

        self.tab_ventas = tk.Frame(self.notebook, bg=self.colors['bg'], padx=20, pady=20)
        self.notebook.add(self.tab_ventas, text=" 🛒 VENTAS ")
        self.setup_tab_ventas()

        self.tab_paths = tk.Frame(self.notebook, bg=self.colors['bg'], padx=20, pady=20)
        self.notebook.add(self.tab_paths, text=" 📂 RUTAS ")
        self.setup_tab_paths()

        self.tab_pagos = tk.Frame(self.notebook, bg=self.colors['bg'], padx=20, pady=20)
        self.notebook.add(self.tab_pagos, text=" 💳 PAGOS ")
        self.setup_tab_pagos()

        btn_frame = tk.Frame(self.root, bg=self.colors['bg'], pady=20)
        btn_frame.pack(fill=tk.X)
        tk.Button(btn_frame, text="GUARDAR CONFIGURACIÓN GENERAL", font=('Segoe UI', 11, 'bold'), 
                  bg=self.colors['success'], fg="black", relief="flat", cursor="hand2",
                  command=self.guardar_todo).pack(fill=tk.X, padx=40, ipady=12)

    def setup_tab_empresa(self):
        self.ent_nombre = self.crear_campo(self.tab_empresa, "Nombre del Negocio:")
        self.ent_eslogan = self.crear_campo(self.tab_empresa, "Eslogan / Subtítulo:")
        self.ent_direccion = self.crear_campo(self.tab_empresa, "Dirección Comercial:")
        self.ent_iva_cond = self.crear_campo(self.tab_empresa, "Condición IVA:")
        
        tax_frame = tk.Frame(self.tab_empresa, bg=self.colors['bg'])
        tax_frame.pack(fill=tk.X)
        self.ent_iva = self.crear_campo_pequeno(tax_frame, "IVA (%)", side=tk.LEFT)
        self.ent_iibb = self.crear_campo_pequeno(tax_frame, "IIBB (%)", side=tk.LEFT)
        self.ent_ganancia = self.crear_campo_pequeno(tax_frame, "Margen (%)", side=tk.RIGHT)

    def setup_tab_afip(self):
        tk.Label(self.tab_afip, text="MODO DE OPERACIÓN", font=('Segoe UI', 9, 'bold'), bg=self.colors['bg'], fg=self.colors['afip_blue']).pack(anchor="w")
        pref_frame = tk.Frame(self.tab_afip, bg=self.colors['card'], padx=10, pady=10)
        pref_frame.pack(fill=tk.X, pady=(5, 15))
        self.var_siempre_fiscal = tk.BooleanVar()
        tk.Checkbutton(pref_frame, text="SOLICITAR FACTURA ARCA POR DEFECTO", variable=self.var_siempre_fiscal, 
                       bg=self.colors['card'], fg="white", selectcolor=self.colors['bg']).pack(anchor="w")

        tk.Label(self.tab_afip, text="CREDENCIALES FISCALES", font=('Segoe UI', 9, 'bold'), bg=self.colors['bg'], fg=self.colors['afip_blue']).pack(anchor="w")
        self.ent_cuit = self.crear_campo(self.tab_afip, "CUIT (solo números):")
        self.ent_pto_vta = self.crear_campo(self.tab_afip, "Punto de Venta:")
        
        tk.Label(self.tab_afip, text="Certificado AFIP (.crt):", bg=self.colors['bg'], fg="#aaa").pack(anchor="w", pady=(10, 0))
        self.ent_afip_cert = self.crear_campo_archivo_especifico(self.tab_afip, [("Certificado", "*.crt")])
        tk.Label(self.tab_afip, text="Llave Privada (.key):", bg=self.colors['bg'], fg="#aaa").pack(anchor="w", pady=(10, 0))
        self.ent_afip_key = self.crear_campo_archivo_especifico(self.tab_afip, [("Llave Privada", "*.key")])
        
        env_frame = tk.Frame(self.tab_afip, bg=self.colors['bg'], pady=10)
        env_frame.pack(fill=tk.X)
        self.var_afip_prod = tk.BooleanVar()
        tk.Checkbutton(env_frame, text="MODO PRODUCCIÓN", variable=self.var_afip_prod, bg=self.colors['bg'], fg=self.colors['success']).pack(side=tk.LEFT)
        self.var_afip_mock = tk.BooleanVar()
        tk.Checkbutton(env_frame, text="USAR MOCK (SIMULADO)", variable=self.var_afip_mock, bg=self.colors['bg'], fg=self.colors['warning']).pack(side=tk.RIGHT)

    def setup_tab_ventas(self):
        self.ent_moneda = self.crear_campo(self.tab_ventas, "Moneda ($):")
        self.var_fraccion = tk.BooleanVar()
        tk.Checkbutton(self.tab_ventas, text="HABILITAR VENTAS POR PESO", variable=self.var_fraccion, bg=self.colors['bg'], fg="white").pack(anchor="w", pady=10)

    def setup_tab_paths(self):
        tk.Label(self.tab_paths, text="Ruta Tickets PDF:", bg=self.colors['bg'], fg="#aaa").pack(anchor="w")
        self.ent_ruta_tickets = self.crear_campo_archivo(self.tab_paths)
        tk.Label(self.tab_paths, text="Ruta Imágenes Productos:", bg=self.colors['bg'], fg="#aaa").pack(anchor="w", pady=(15,0))
        self.ent_ruta_imgs = self.crear_campo_archivo(self.tab_paths)

    def setup_tab_pagos(self):
        group_mp = tk.LabelFrame(self.tab_pagos, text=" MERCADO PAGO ", bg=self.colors['bg'], fg=self.colors['mp_blue'], padx=10, pady=5)
        group_mp.pack(fill=tk.X, pady=5)
        self.ent_mp_token = self.crear_campo(group_mp, "Access Token:")
        self.ent_mp_user = self.crear_campo(group_mp, "User ID:")
        self.ent_mp_external = self.crear_campo(group_mp, "External ID (Caja):")
        
        group_modo = tk.LabelFrame(self.tab_pagos, text=" MODO ", bg=self.colors['bg'], fg=self.colors['modo_green'], padx=10, pady=5)
        group_modo.pack(fill=tk.X, pady=5)
        self.ent_modo_key = self.crear_campo(group_modo, "Modo API Key:")
        self.var_modo_sandbox = tk.BooleanVar()
        tk.Checkbutton(group_modo, text="Modo Sandbox", variable=self.var_modo_sandbox, bg=self.colors['bg'], fg="white").pack(anchor="w")

        group_pw = tk.LabelFrame(self.tab_pagos, text=" PAYWAY ", bg=self.colors['bg'], fg=self.colors['pw_red'], padx=10, pady=5)
        group_pw.pack(fill=tk.X, pady=5)
        self.ent_pw_key = self.crear_campo(group_pw, "PayWay API Key:")
        self.ent_pw_merchant = self.crear_campo(group_pw, "Merchant ID:")

    def crear_campo(self, master, label_text):
        tk.Label(master, text=label_text, bg=master['bg'], fg="#aaa", font=('Segoe UI', 9)).pack(anchor="w", pady=(5, 2))
        entry = tk.Entry(master, font=('Segoe UI', 10), bg=self.colors['card'], fg="white", borderwidth=0, insertbackground="white")
        entry.pack(fill=tk.X, ipady=6); tk.Frame(master, height=1, bg=self.colors['border']).pack(fill=tk.X)
        return entry

    def crear_campo_archivo(self, master):
        container = tk.Frame(master, bg=master['bg']); container.pack(fill=tk.X, pady=5)
        entry = tk.Entry(container, font=('Segoe UI', 10), bg=self.colors['card'], fg="white", borderwidth=0)
        entry.pack(side=tk.LEFT, fill=tk.X, expand=True, ipady=6)
        tk.Button(container, text=" ... ", bg=self.colors['border'], fg="white", command=lambda e=entry: self.seleccionar_directorio(e)).pack(side=tk.RIGHT, padx=5)
        return entry

    def crear_campo_archivo_especifico(self, master, tipos):
        container = tk.Frame(master, bg=master['bg']); container.pack(fill=tk.X, pady=5)
        entry = tk.Entry(container, font=('Segoe UI', 10), bg=self.colors['card'], fg="white", borderwidth=0)
        entry.pack(side=tk.LEFT, fill=tk.X, expand=True, ipady=6)
        tk.Button(container, text=" 📁 Buscar ", font=('Segoe UI', 8), bg=self.colors['border'], fg="white", command=lambda e=entry, t=tipos: self.seleccionar_archivo(e, t)).pack(side=tk.RIGHT, padx=5)
        return entry

    def seleccionar_directorio(self, entry):
        d = filedialog.askdirectory()
        if d: entry.delete(0, tk.END); entry.insert(0, d)

    def seleccionar_archivo(self, entry, tipos):
        f = filedialog.askopenfilename(filetypes=tipos)
        if f: entry.delete(0, tk.END); entry.insert(0, f)

    def crear_campo_pequeno(self, master, label_text, side):
        frame = tk.Frame(master, bg=master['bg'])
        frame.pack(side=side, fill=tk.X, expand=True, padx=5)
        tk.Label(frame, text=label_text, bg=master['bg'], fg="#aaa", font=('Segoe UI', 9)).pack(anchor="w")
        entry = tk.Entry(frame, font=('Segoe UI', 10, 'bold'), bg=self.colors['card'], fg=self.colors['success'], borderwidth=0, justify="center")
        entry.pack(fill=tk.X, ipady=6)
        return entry

    def cargar_datos(self):
        try:
            conn = get_connection()
            with conn.cursor() as cursor:
                cursor.execute("SELECT * FROM nombre_negocio WHERE empresa_id=%s OR id=1 LIMIT 1", (self.empresa_id,))
                res = cursor.fetchone()
                if res:
                    self.ent_nombre.insert(0, res.get('nombre_negocio', ""))
                    self.ent_eslogan.insert(0, res.get('eslogan', ""))
                    self.ent_cuit.insert(0, res.get('cuit', ""))
                    self.ent_direccion.insert(0, res.get('direccion', ""))
                    self.ent_iva_cond.insert(0, res.get('condicion_iva', ""))
                    self.ent_iva.insert(0, str(res.get('impuesto', "21.0")))
                    self.ent_iibb.insert(0, str(res.get('ingresos_brutos', "0.0"))) # Respetamos columna
                    self.ent_ganancia.insert(0, str(res.get('ganancia_sugerida', "0.0"))) # Respetamos columna
                    
                    pv = res.get('punto_vta') if res.get('punto_vta') is not None else res.get('punto_venta', "1")
                    self.ent_pto_vta.insert(0, str(pv))
                    
                    self.ent_afip_cert.insert(0, res.get('afip_cert', ""))
                    self.ent_afip_key.insert(0, res.get('afip_key', ""))
                    self.var_afip_prod.set(bool(res.get('afip_prod', False)))
                    self.var_afip_mock.set(bool(res.get('afip_mock', False)))
                    self.var_siempre_fiscal.set(bool(res.get('siempre_fiscal', False)))
                    self.ent_moneda.insert(0, res.get('moneda', "$"))
                    self.var_fraccion.set(bool(res.get('permitir_fraccion', False))) # Respetamos columna
                    self.ent_ruta_tickets.insert(0, res.get('ruta_tickets', ""))
                    self.ent_ruta_imgs.insert(0, res.get('ruta_imagenes', ""))

                cursor.execute("SELECT * FROM config_pagos WHERE empresa_id=%s OR id=1 LIMIT 1", (self.empresa_id,))
                pagos = cursor.fetchone()
                if pagos:
                    self.ent_mp_token.insert(0, pagos.get('mp_access_token', ""))
                    self.ent_mp_user.insert(0, pagos.get('mp_user_id', ""))
                    self.ent_mp_external.insert(0, pagos.get('mp_external_id', "CAJA_01"))
                    self.ent_modo_key.insert(0, pagos.get('modo_api_key', ""))
                    self.var_modo_sandbox.set(bool(pagos.get('modo_sandbox', True)))
                    self.ent_pw_key.insert(0, pagos.get('pw_api_key', ""))
                    self.ent_pw_merchant.insert(0, pagos.get('pw_merchant_id', ""))
            conn.close()
        except Exception as e: print(f"Error carga: {e}")

    def guardar_todo(self):
        try:
            conn = get_connection()
            with conn.cursor() as cursor:
                sql_negocio = """UPDATE nombre_negocio SET 
                         nombre_negocio=%s, eslogan=%s, cuit=%s, direccion=%s, 
                         condicion_iva=%s, impuesto=%s, ingresos_brutos=%s, ganancia_sugerida=%s,
                         punto_vta=%s, afip_cert=%s, afip_key=%s, afip_prod=%s, afip_mock=%s,
                         siempre_fiscal=%s, moneda=%s, permitir_fraccion=%s, ruta_tickets=%s, ruta_imagenes=%s
                         WHERE empresa_id=%s OR id=1"""
                cursor.execute(sql_negocio, (
                    self.ent_nombre.get(), self.ent_eslogan.get(), self.ent_cuit.get(),
                    self.ent_direccion.get(), self.ent_iva_cond.get(), float(self.ent_iva.get() or 0),
                    float(self.ent_iibb.get() or 0), float(self.ent_ganancia.get() or 0),
                    int(self.ent_pto_vta.get() or 1), self.ent_afip_cert.get(),
                    self.ent_afip_key.get(), self.var_afip_prod.get(), self.var_afip_mock.get(),
                    self.var_siempre_fiscal.get(), self.ent_moneda.get(), self.var_fraccion.get(), 
                    self.ent_ruta_tickets.get(), self.ent_ruta_imgs.get(), self.empresa_id
                ))

                sql_pagos = """UPDATE config_pagos SET 
                         mp_access_token=%s, mp_user_id=%s, mp_external_id=%s, 
                         modo_api_key=%s, modo_sandbox=%s, pw_api_key=%s, pw_merchant_id=%s
                         WHERE empresa_id=%s OR id=1"""
                cursor.execute(sql_pagos, (
                    self.ent_mp_token.get(), self.ent_mp_user.get(), self.ent_mp_external.get(),
                    self.ent_modo_key.get(), self.var_modo_sandbox.get(),
                    self.ent_pw_key.get(), self.ent_pw_merchant.get(), self.empresa_id
                ))
                
                conn.commit()
            conn.close()
            messagebox.showinfo("Éxito", "Configuración completa guardada.")
            self.root.destroy()
        except Exception as e: messagebox.showerror("Error", f"No se pudo guardar: {str(e)}")

if __name__ == "__main__":
    root = tk.Tk()
    app = ConfigUI(root, "NEXUS", 1)
    root.mainloop()