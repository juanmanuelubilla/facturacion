import tkinter as tk
from tkinter import ttk, messagebox, filedialog
from db import get_connection
import os

class ConfigUI:
    def __init__(self, root, nombre_negocio_actual="SISTEMA"):
        self.root = root
        self.root.title(f"Ajustes del Sistema - {nombre_negocio_actual}")
        self.root.geometry("600x750")
        self.root.resizable(False, False)
        
        self.colors = {
            'bg': '#121212',
            'card': '#1e1e1e',
            'accent': '#9c27b0', 
            'mp_blue': '#009ee3',
            'pw_red': '#ee2e24', # Color distintivo Payway
            'text': '#ffffff',
            'border': '#333333',
            'success': '#00db84'
        }
        
        self.root.configure(bg=self.colors['bg'])
        self.setup_styles()
        self.init_ui()
        self.cargar_datos_completos()

    def setup_styles(self):
        style = ttk.Style()
        style.theme_use('clam')
        style.configure("TNotebook", background=self.colors['bg'], borderwidth=0)
        style.configure("TNotebook.Tab", background=self.colors['card'], foreground="#888", 
                        padding=[12, 5], font=('Segoe UI', 9, 'bold'))
        style.map("TNotebook.Tab", background=[("selected", self.colors['accent'])], 
                  foreground=[("selected", "white")])

    def seleccionar_ruta(self):
        directorio = filedialog.askdirectory()
        if directorio:
            self.ent_ruta.delete(0, tk.END)
            self.ent_ruta.insert(0, directorio)

    def init_ui(self):
        header = tk.Frame(self.root, bg=self.colors['bg'], pady=20)
        header.pack(fill=tk.X)
        tk.Label(header, text="⚙️ CONFIGURACIÓN GLOBAL", font=('Segoe UI', 18, 'bold'), 
                 bg=self.colors['bg'], fg="white").pack()

        self.notebook = ttk.Notebook(self.root)
        self.notebook.pack(fill=tk.BOTH, expand=True, padx=20, pady=(0, 10))

        # --- PESTAÑA 1: NEGOCIO ---
        self.tab_negocio = tk.Frame(self.notebook, bg=self.colors['bg'], padx=20, pady=20)
        self.notebook.add(self.tab_negocio, text="  🏬 NEGOCIO  ")
        self.ent_nombre = self.crear_campo(self.tab_negocio, "Nombre Comercial:")
        self.ent_eslogan = self.crear_campo(self.tab_negocio, "Eslogan o Mensaje en Ticket:")
        self.ent_moneda = self.crear_campo(self.tab_negocio, "Símbolo de Moneda (ej: $):")
        
        tk.Label(self.tab_negocio, text="Destino de Tickets PDF/TXT:", bg=self.colors['bg'], 
                 fg="#aaa", font=('Segoe UI', 9)).pack(anchor="w", pady=(15, 2))
        f_ruta = tk.Frame(self.tab_negocio, bg=self.colors['bg'])
        f_ruta.pack(fill=tk.X)
        self.ent_ruta = tk.Entry(f_ruta, font=('Segoe UI', 11), bg=self.colors['card'], 
                                fg="white", borderwidth=0, insertbackground="white")
        self.ent_ruta.pack(side=tk.LEFT, fill=tk.X, expand=True, ipady=8)
        tk.Button(f_ruta, text="📁", bg=self.colors['border'], fg="white", command=self.seleccionar_ruta, 
                  relief="flat", width=4, cursor="hand2").pack(side=tk.RIGHT, padx=(5, 0))

        # --- PESTAÑA 2: FISCAL ---
        self.tab_fiscal = tk.Frame(self.notebook, bg=self.colors['bg'], padx=20, pady=20)
        self.notebook.add(self.tab_fiscal, text="  📄 FISCAL  ")
        self.ent_cuit = self.crear_campo(self.tab_fiscal, "CUIT / Identificación Tributaria:")
        self.ent_direccion = self.crear_campo(self.tab_fiscal, "Dirección Legal:")
        self.ent_iva_cond = self.crear_campo(self.tab_fiscal, "Condición frente al IVA:")
        f_nums = tk.Frame(self.tab_fiscal, bg=self.colors['bg'])
        f_nums.pack(fill=tk.X, pady=20)
        self.ent_iva = self.crear_campo_pequeno(f_nums, "IVA General (%)", tk.LEFT)
        self.ent_ganancia = self.crear_campo_pequeno(f_nums, "Margen Sugerido (%)", tk.RIGHT)

        # --- PESTAÑA 3: MERCADO PAGO ---
        self.tab_mp = tk.Frame(self.notebook, bg=self.colors['bg'], padx=20, pady=20)
        self.notebook.add(self.tab_mp, text="  💳 MERCADO PAGO  ")
        tk.Label(self.tab_mp, text="Integración QR Dinámico (Instore)", font=('Segoe UI', 10, 'bold'), bg=self.colors['bg'], fg=self.colors['mp_blue']).pack(anchor="w")
        self.ent_mp_token = self.crear_campo(self.tab_mp, "Access Token (Secret Key):")
        self.ent_mp_user = self.crear_campo(self.tab_mp, "User ID (Collector ID):")
        self.ent_mp_caja = self.crear_campo(self.tab_mp, "External ID de la Caja:")

        # --- PESTAÑA 4: PAYWAY ---
        self.tab_pw = tk.Frame(self.notebook, bg=self.colors['bg'], padx=20, pady=20)
        self.notebook.add(self.tab_pw, text="  💳 PAYWAY  ")
        tk.Label(self.tab_pw, text="Integración QR Interoperable (Prisma)", font=('Segoe UI', 10, 'bold'), bg=self.colors['bg'], fg=self.colors['pw_red']).pack(anchor="w")
        self.ent_pw_key = self.crear_campo(self.tab_pw, "API Key (Public Key):")
        self.ent_pw_merchant = self.crear_campo(self.tab_pw, "Merchant ID:")

        btn_save = tk.Button(self.root, text="GUARDAR CONFIGURACIÓN TOTAL", font=('Segoe UI', 10, 'bold'), 
                            bg=self.colors['success'], fg="#000", relief="flat", cursor="hand2",
                            command=self.guardar_todo, pady=12)
        btn_save.pack(fill=tk.X, padx=20, pady=20)

    def crear_campo(self, master, label_text):
        tk.Label(master, text=label_text, bg=self.colors['bg'], fg="#aaa", font=('Segoe UI', 9)).pack(anchor="w", pady=(12, 2))
        entry = tk.Entry(master, font=('Segoe UI', 11), bg=self.colors['card'], fg="white", borderwidth=0, insertbackground="white")
        entry.pack(fill=tk.X, ipady=8)
        tk.Frame(master, height=1, bg=self.colors['border']).pack(fill=tk.X)
        return entry

    def crear_campo_pequeno(self, master, label_text, side):
        f = tk.Frame(master, bg=self.colors['bg'])
        f.pack(side=side, fill=tk.X, expand=True, padx=5)
        tk.Label(f, text=label_text, bg=self.colors['bg'], fg="#aaa", font=('Segoe UI', 8)).pack(anchor="w")
        entry = tk.Entry(f, font=('Segoe UI', 11, 'bold'), bg=self.colors['card'], fg=self.colors['success'], 
                         borderwidth=0, justify="center")
        entry.pack(fill=tk.X, ipady=8)
        tk.Frame(f, height=1, bg=self.colors['border']).pack(fill=tk.X)
        return entry

    def cargar_datos_completos(self):
        try:
            conn = get_connection()
            with conn.cursor() as cursor:
                cursor.execute("SELECT * FROM nombre_negocio WHERE id=1")
                n = cursor.fetchone()
                if n:
                    self.ent_nombre.insert(0, str(n.get('nombre_negocio') or ""))
                    self.ent_eslogan.insert(0, str(n.get('eslogan') or ""))
                    self.ent_moneda.insert(0, str(n.get('moneda') or "$"))
                    self.ent_cuit.insert(0, str(n.get('cuit') or ""))
                    self.ent_direccion.insert(0, str(n.get('direccion') or ""))
                    self.ent_iva_cond.insert(0, str(n.get('condicion_iva') or ""))
                    self.ent_iva.insert(0, str(n.get('impuesto') or "0.00"))
                    self.ent_ganancia.insert(0, str(n.get('ganancia_sugerida') or "0.00"))
                    self.ent_ruta.insert(0, str(n.get('ruta_tickets') or ""))

                cursor.execute("SELECT * FROM config_pagos WHERE id=1")
                mp = cursor.fetchone()
                if mp:
                    self.ent_mp_token.insert(0, str(mp.get('mp_access_token') or ""))
                    self.ent_mp_user.insert(0, str(mp.get('mp_user_id') or ""))
                    self.ent_mp_caja.insert(0, str(mp.get('mp_external_id') or "CAJA_01"))
                    self.ent_pw_key.insert(0, str(mp.get('pw_api_key') or ""))
                    self.ent_pw_merchant.insert(0, str(mp.get('pw_merchant_id') or ""))
            conn.close()
        except Exception as e:
            print(f"Error carga: {e}")

    def guardar_todo(self):
        try:
            conn = get_connection()
            with conn.cursor() as cursor:
                cursor.execute("""UPDATE nombre_negocio SET 
                    nombre_negocio=%s, eslogan=%s, moneda=%s, cuit=%s, direccion=%s, 
                    condicion_iva=%s, impuesto=%s, ganancia_sugerida=%s, ruta_tickets=%s 
                    WHERE id=1""", (
                    self.ent_nombre.get(), self.ent_eslogan.get(), self.ent_moneda.get(),
                    self.ent_cuit.get(), self.ent_direccion.get(), self.ent_iva_cond.get(),
                    float(self.ent_iva.get() or 0), float(self.ent_ganancia.get() or 0),
                    self.ent_ruta.get()
                ))
                cursor.execute("""UPDATE config_pagos SET 
                    mp_access_token=%s, mp_user_id=%s, mp_external_id=%s,
                    pw_api_key=%s, pw_merchant_id=%s
                    WHERE id=1""", (
                    self.ent_mp_token.get().strip(), self.ent_mp_user.get().strip(), self.ent_mp_caja.get().strip(),
                    self.ent_pw_key.get().strip(), self.ent_pw_merchant.get().strip()
                ))
                conn.commit()
            messagebox.showinfo("Éxito", "Todos los cambios fueron guardados.")
            self.root.destroy()
        except Exception as e:
            messagebox.showerror("Error", f"No se pudo guardar: {e}")
        finally:
            if 'conn' in locals(): conn.close()

if __name__ == "__main__":
    root = tk.Tk()
    app = ConfigUI(root, "NEXUS")
    root.mainloop()