import tkinter as tk
from tkinter import ttk, messagebox, filedialog
from db import get_connection
import os

class ConfigUI:
    def __init__(self, root, nombre_negocio_actual="SISTEMA"):
        self.root = root
        self.root.title(f"Ajustes del Sistema - {nombre_negocio_actual}")
        self.root.geometry("600x700")
        self.root.resizable(False, False)
        
        self.colors = {
            'bg': '#121212',
            'card': '#1e1e1e',
            'accent': '#9c27b0', 
            'mp_blue': '#009ee3',
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
        # Estilo de las pestañas
        style.configure("TNotebook", background=self.colors['bg'], borderwidth=0)
        style.configure("TNotebook.Tab", background=self.colors['card'], foreground="#888", 
                        padding=[15, 5], font=('Segoe UI', 10))
        style.map("TNotebook.Tab", background=[("selected", self.colors['accent'])], 
                  foreground=[("selected", "white")])

    def seleccionar_ruta(self):
        directorio = filedialog.askdirectory()
        if directorio:
            self.ent_ruta.delete(0, tk.END)
            self.ent_ruta.insert(0, directorio)

    def init_ui(self):
        # Header Superior
        header = tk.Frame(self.root, bg=self.colors['bg'], pady=20)
        header.pack(fill=tk.X)
        tk.Label(header, text="⚙️ CONFIGURACIÓN", font=('Segoe UI', 18, 'bold'), 
                 bg=self.colors['bg'], fg="white").pack()

        # Contenedor de Pestañas
        self.notebook = ttk.Notebook(self.root)
        self.notebook.pack(fill=tk.BOTH, expand=True, padx=20, pady=(0, 20))

        # --- PESTAÑA 1: NEGOCIO ---
        self.tab_negocio = tk.Frame(self.notebook, bg=self.colors['bg'], padx=20, pady=20)
        self.notebook.add(self.tab_negocio, text="  🏬 Negocio  ")
        
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
        self.notebook.add(self.tab_fiscal, text="  📄 Datos Fiscales  ")
        
        self.ent_cuit = self.crear_campo(self.tab_fiscal, "CUIT / Identificación Tributaria:")
        self.ent_direccion = self.crear_campo(self.tab_fiscal, "Dirección Legal:")
        self.ent_iva_cond = self.crear_campo(self.tab_fiscal, "Condición frente al IVA:")
        
        f_nums = tk.Frame(self.tab_fiscal, bg=self.colors['bg'])
        f_nums.pack(fill=tk.X, pady=20)
        self.ent_iva = self.crear_campo_pequeno(f_nums, "IVA General (%)", tk.LEFT)
        self.ent_ganancia = self.crear_campo_pequeno(f_nums, "Margen Sugerido (%)", tk.RIGHT)

        # --- PESTAÑA 3: PAGOS (MP) ---
        self.tab_mp = tk.Frame(self.notebook, bg=self.colors['bg'], padx=20, pady=20)
        self.notebook.add(self.tab_mp, text="  💳 Mercado Pago  ")
        
        tk.Label(self.tab_mp, text="Configuración de Integración QR Dinámico", 
                 font=('Segoe UI', 10, 'italic'), bg=self.colors['bg'], fg=self.colors['mp_blue']).pack(pady=(0, 20))
        
        self.ent_mp_token = self.crear_campo(self.tab_mp, "Access Token (Credenciales de Producción/Test):")
        self.ent_mp_user = self.crear_campo(self.tab_mp, "Collector ID (User ID):")
        self.ent_mp_caja = self.crear_campo(self.tab_mp, "External ID de la Caja (Pos ID):")

        # Botón Guardar (Fijo abajo)
        btn_save = tk.Button(self.root, text="GUARDAR TODOS LOS CAMBIOS", font=('Segoe UI', 10, 'bold'), 
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
                # Datos de negocio
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

                # Datos de Mercado Pago
                cursor.execute("SELECT * FROM config_pagos WHERE id=1")
                mp = cursor.fetchone()
                if mp:
                    self.ent_mp_token.insert(0, str(mp.get('mp_access_token') or ""))
                    self.ent_mp_user.insert(0, str(mp.get('mp_user_id') or ""))
                    self.ent_mp_caja.insert(0, str(mp.get('mp_external_id') or "CAJA_01"))
            conn.close()
        except Exception as e:
            print(f"Error carga: {e}")

    def guardar_todo(self):
        try:
            conn = get_connection()
            with conn.cursor() as cursor:
                # Update Negocio
                cursor.execute("""UPDATE nombre_negocio SET 
                    nombre_negocio=%s, eslogan=%s, moneda=%s, cuit=%s, direccion=%s, 
                    condicion_iva=%s, impuesto=%s, ganancia_sugerida=%s, ruta_tickets=%s 
                    WHERE id=1""", (
                    self.ent_nombre.get(), self.ent_eslogan.get(), self.ent_moneda.get(),
                    self.ent_cuit.get(), self.ent_direccion.get(), self.ent_iva_cond.get(),
                    float(self.ent_iva.get() or 0), float(self.ent_ganancia.get() or 0),
                    self.ent_ruta.get()
                ))
                # Update Mercado Pago
                cursor.execute("""UPDATE config_pagos SET 
                    mp_access_token=%s, mp_user_id=%s, mp_external_id=%s 
                    WHERE id=1""", (
                    self.ent_mp_token.get().strip(),
                    self.ent_mp_user.get().strip(),
                    self.ent_mp_caja.get().strip()
                ))
                conn.commit()
            messagebox.showinfo("Éxito", "Configuración actualizada globalmente.")
            self.root.destroy()
        except Exception as e:
            messagebox.showerror("Error", f"No se pudo guardar: {e}")
        finally:
            if 'conn' in locals(): conn.close()

if __name__ == "__main__":
    root = tk.Tk()
    app = ConfigUI(root, "NEXUS")
    root.mainloop()