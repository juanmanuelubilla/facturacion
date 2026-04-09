import tkinter as tk
from tkinter import ttk, messagebox, filedialog
from db import get_connection
import sys

class ConfigUI:
    def __init__(self, root, nombre_negocio_actual="SISTEMA", empresa_id=1):
        self.root = root
        try:
            self.empresa_id = int(str(empresa_id).strip())
        except:
            self.empresa_id = 1

        self.root.title(f"Configuración - {nombre_negocio_actual}")
        self.root.geometry("550x900")
        self.root.resizable(False, False)
        
        self.colors = {
            'bg': '#121212',
            'card': '#1e1e1e',
            'accent': '#9c27b0', 
            'text': '#ffffff',
            'border': '#333333',
            'success': '#00db84',
            'mp_blue': '#009ee3',
            'pw_red': '#ee2e24'
        }
        
        self.root.configure(bg=self.colors['bg'])
        self.configurar_estilo()
        self.init_ui()
        self.cargar_datos_generales()
        self.cargar_config_pagos()

    def configurar_estilo(self):
        style = ttk.Style()
        style.theme_use('clam')
        style.configure("TNotebook", background=self.colors['bg'], borderwidth=0)
        style.configure("TNotebook.Tab", background=self.colors['card'], foreground="#888", padding=[15, 5])
        style.map("TNotebook.Tab", background=[("selected", self.colors['accent'])], foreground=[("selected", "white")])
        style.configure("TSeparator", background=self.colors['border'])

    def init_ui(self):
        # Header
        header = tk.Frame(self.root, bg=self.colors['bg'], pady=20)
        header.pack(fill=tk.X)
        tk.Label(header, text="⚙️ PANEL DE CONTROL", font=('Segoe UI', 18, 'bold'), 
                 bg=self.colors['bg'], fg=self.colors['accent']).pack()
        
        # Notebook para separar General de Pagos
        self.notebook = ttk.Notebook(self.root)
        self.notebook.pack(fill=tk.BOTH, expand=True, padx=20, pady=10)

        # --- TAB 1: GENERAL Y FISCAL ---
        self.tab_general = tk.Frame(self.notebook, bg=self.colors['bg'], padx=20, pady=20)
        self.notebook.add(self.tab_general, text=" GENERAL / FISCAL ")
        self.setup_tab_general()

        # --- TAB 2: PASARELA DE PAGOS ---
        self.tab_pagos = tk.Frame(self.notebook, bg=self.colors['bg'], padx=20, pady=20)
        self.notebook.add(self.tab_pagos, text=" PASARELA PAGOS ")
        self.setup_tab_pagos()

        # --- BOTÓN GUARDAR TODO ---
        btn_save_all = tk.Button(self.root, text="GUARDAR TODA LA CONFIGURACIÓN", font=('Segoe UI', 10, 'bold'), 
                                 bg=self.colors['success'], fg="black", relief="flat", cursor="hand2",
                                 command=self.guardar_todo)
        btn_save_all.pack(fill=tk.X, padx=40, pady=20, ipady=12)

    def setup_tab_general(self):
        self.ent_nombre = self.crear_campo(self.tab_general, "Nombre del Negocio:")
        self.ent_eslogan = self.crear_campo(self.tab_general, "Eslogan / Subtítulo:")
        self.ent_moneda = self.crear_campo(self.tab_general, "Símbolo de Moneda (ej: $):")

        tk.Label(self.tab_general, text="CARPETA DE TICKETS", font=('Segoe UI', 9, 'bold'), 
                 bg=self.colors['bg'], fg=self.colors['accent']).pack(anchor="w", pady=(15, 2))
        ruta_cont = tk.Frame(self.tab_general, bg=self.colors['bg'])
        ruta_cont.pack(fill=tk.X)
        self.ent_ruta = tk.Entry(ruta_cont, font=('Segoe UI', 10), bg=self.colors['card'], fg="white", borderwidth=0)
        self.ent_ruta.pack(side=tk.LEFT, fill=tk.X, expand=True, ipady=8)
        tk.Button(ruta_cont, text="...", bg=self.colors['border'], fg="white", command=self.seleccionar_ruta, width=3).pack(side=tk.RIGHT, padx=5)

        tk.Label(self.tab_general, text="DATOS FISCALES", font=('Segoe UI', 9, 'bold'), 
                 bg=self.colors['bg'], fg=self.colors['accent']).pack(anchor="w", pady=(20, 5))
        self.ent_cuit = self.crear_campo(self.tab_general, "CUIT:")
        self.ent_direccion = self.crear_campo(self.tab_general, "Dirección Comercial:")
        self.ent_iva_cond = self.crear_campo(self.tab_general, "Condición IVA:")
        
        tax_frame = tk.Frame(self.tab_general, bg=self.colors['bg'])
        tax_frame.pack(fill=tk.X, pady=10)
        self.ent_iva = self.crear_campo_pequeno(tax_frame, "IVA (%)", side=tk.LEFT)
        self.ent_iibb = self.crear_campo_pequeno(tax_frame, "IIBB (%)", side=tk.LEFT)
        self.ent_ganancia = self.crear_campo_pequeno(tax_frame, "Margen (%)", side=tk.RIGHT)

    def setup_tab_pagos(self):
        # Mercado Pago
        tk.Label(self.tab_pagos, text="MERCADO PAGO", font=('Segoe UI', 11, 'bold'), 
                 bg=self.colors['bg'], fg=self.colors['mp_blue']).pack(anchor="w", pady=(10, 5))
        
        self.ent_mp_token = self.crear_campo(self.tab_pagos, "Access Token (Production):")
        self.ent_mp_id = self.crear_campo(self.tab_pagos, "Collector ID (User ID):")
        self.ent_mp_caja = self.crear_campo(self.tab_pagos, "External ID Caja:")

        ttk.Separator(self.tab_pagos, orient='horizontal').pack(fill=tk.X, pady=25)

        # Payway
        tk.Label(self.tab_pagos, text="PAYWAY / PRISMA", font=('Segoe UI', 11, 'bold'), 
                 bg=self.colors['bg'], fg=self.colors['pw_red']).pack(anchor="w", pady=(5, 5))
        
        self.ent_pw_key = self.crear_campo(self.tab_pagos, "API Key (Public):")
        self.ent_pw_merch = self.crear_campo(self.tab_pagos, "Merchant ID:")

    def crear_campo(self, master, label_text):
        tk.Label(master, text=label_text, bg=self.colors['bg'], fg="#aaa", font=('Segoe UI', 9)).pack(anchor="w", pady=(10, 2))
        entry = tk.Entry(master, font=('Segoe UI', 11), bg=self.colors['card'], fg="white", borderwidth=0, insertbackground="white")
        entry.pack(fill=tk.X, ipady=8)
        tk.Frame(master, height=1, bg=self.colors['border']).pack(fill=tk.X)
        return entry

    def crear_campo_pequeno(self, master, label_text, side):
        frame = tk.Frame(master, bg=self.colors['bg'])
        frame.pack(side=side, fill=tk.X, expand=True, padx=5)
        tk.Label(frame, text=label_text, bg=self.colors['bg'], fg="#aaa", font=('Segoe UI', 9)).pack(anchor="w")
        entry = tk.Entry(frame, font=('Segoe UI', 11, 'bold'), bg=self.colors['card'], fg=self.colors['success'], borderwidth=0, justify="center")
        entry.pack(fill=tk.X, ipady=8)
        return entry

    def seleccionar_ruta(self):
        directorio = filedialog.askdirectory()
        if directorio:
            self.ent_ruta.delete(0, tk.END); self.ent_ruta.insert(0, directorio)

    def cargar_datos_generales(self):
        try:
            conn = get_connection()
            with conn.cursor() as cursor:
                cursor.execute("SELECT * FROM nombre_negocio WHERE empresa_id=%s", (self.empresa_id,))
                res = cursor.fetchone()
                if res:
                    # Llenar campos Identity/Fiscal
                    self.ent_nombre.insert(0, str(res.get('nombre_negocio') or ""))
                    self.ent_eslogan.insert(0, str(res.get('eslogan') or ""))
                    self.ent_moneda.insert(0, str(res.get('moneda') or "$"))
                    self.ent_cuit.insert(0, str(res.get('cuit') or ""))
                    self.ent_direccion.insert(0, str(res.get('direccion') or ""))
                    self.ent_iva_cond.insert(0, str(res.get('condicion_iva') or ""))
                    self.ent_iva.insert(0, str(res.get('impuesto') or "0.00"))
                    self.ent_iibb.insert(0, str(res.get('ingresos_brutos') or "0.00"))
                    self.ent_ganancia.insert(0, str(res.get('ganancia_sugerida') or "0.00"))
                    self.ent_ruta.insert(0, str(res.get('ruta_tickets') or ""))
            conn.close()
        except Exception as e: print(f"Error cargar generales: {e}")

    def cargar_config_pagos(self):
        try:
            conn = get_connection()
            with conn.cursor() as cursor:
                cursor.execute("SELECT * FROM config_pagos WHERE empresa_id=%s", (self.empresa_id,))
                cp = cursor.fetchone()
                if cp:
                    self.ent_mp_token.insert(0, cp.get('mp_access_token') or "")
                    self.ent_mp_id.insert(0, cp.get('mp_user_id') or "")
                    self.ent_mp_caja.insert(0, cp.get('mp_external_id') or "")
                    self.ent_pw_key.insert(0, cp.get('pw_api_key') or "")
                    self.ent_pw_merch.insert(0, cp.get('pw_merchant_id') or "")
            conn.close()
        except Exception as e: print(f"Error cargar pagos: {e}")

    def guardar_todo(self):
        try:
            conn = get_connection()
            with conn.cursor() as cursor:
                # 1. Guardar Identidad y Fiscal
                sql_gen = """UPDATE nombre_negocio SET 
                             nombre_negocio=%s, eslogan=%s, moneda=%s, 
                             cuit=%s, direccion=%s, condicion_iva=%s,
                             impuesto=%s, ingresos_brutos=%s, ganancia_sugerida=%s,
                             ruta_tickets=%s WHERE empresa_id=%s"""
                
                data_gen = (
                    self.ent_nombre.get(), self.ent_eslogan.get(), self.ent_moneda.get(),
                    self.ent_cuit.get(), self.ent_direccion.get(), self.ent_iva_cond.get(),
                    float(self.ent_iva.get() or 0), float(self.ent_iibb.get() or 0),
                    float(self.ent_ganancia.get() or 0), self.ent_ruta.get(), self.empresa_id
                )
                cursor.execute(sql_gen, data_gen)

                # 2. Guardar Pagos
                sql_pay = """UPDATE config_pagos SET 
                             mp_access_token=%s, mp_user_id=%s, mp_external_id=%s,
                             pw_api_key=%s, pw_merchant_id=%s WHERE empresa_id=%s"""
                
                data_pay = (
                    self.ent_mp_token.get(), self.ent_mp_id.get(), self.ent_mp_caja.get(),
                    self.ent_pw_key.get(), self.ent_pw_merch.get(), self.empresa_id
                )
                cursor.execute(sql_pay, data_pay)
                
                conn.commit()
            conn.close()
            messagebox.showinfo("Éxito", "Toda la configuración ha sido actualizada.")
            self.root.destroy()
        except Exception as e:
            messagebox.showerror("Error", f"Fallo al guardar: {e}")

if __name__ == "__main__":
    root = tk.Tk()
    negocio = sys.argv[1] if len(sys.argv) > 1 else "NEXUS"
    emp_id = sys.argv[2] if len(sys.argv) > 2 else 1
    app = ConfigUI(root, negocio, emp_id)
    root.mainloop()