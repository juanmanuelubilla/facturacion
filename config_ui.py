import tkinter as tk
from tkinter import ttk, messagebox, filedialog
from db import get_connection
import os

class ConfigUI:
    def __init__(self, root, nombre_negocio_actual="SISTEMA"):
        self.root = root
        self.root.title(f"Configuración - {nombre_negocio_actual}")
        self.root.geometry("500x850")
        self.root.resizable(False, False)
        
        self.colors = {
            'bg': '#121212',
            'card': '#1e1e1e',
            'accent': '#9c27b0', 
            'text': '#ffffff',
            'border': '#333333',
            'success': '#00db84',
            'warning': '#f1c40f'
        }
        
        self.root.configure(bg=self.colors['bg'])
        self.init_ui()
        self.cargar_datos()

    def seleccionar_ruta(self):
        # Abre el explorador de carpetas de Windows
        directorio = filedialog.askdirectory()
        if directorio:
            self.ent_ruta.delete(0, tk.END)
            self.ent_ruta.insert(0, directorio)

    def init_ui(self):
        # Header
        header = tk.Frame(self.root, bg=self.colors['bg'], pady=20)
        header.pack(fill=tk.X)
        tk.Label(header, text="⚙️ CONFIGURACIÓN", font=('Segoe UI', 18, 'bold'), 
                 bg=self.colors['bg'], fg=self.colors['accent']).pack()
        
        # Contenedor con scroll (opcional) o simple frame
        self.form_frame = tk.Frame(self.root, bg=self.colors['bg'], padx=40)
        self.form_frame.pack(fill=tk.BOTH, expand=True)

        # --- IDENTIDAD ---
        self.ent_nombre = self.crear_campo("Nombre del Negocio:")
        self.ent_eslogan = self.crear_campo("Eslogan / Subtítulo:")
        self.ent_moneda = self.crear_campo("Símbolo de Moneda (ej: $):")

        # --- RUTA TICKETS (NUEVO) ---
        tk.Label(self.form_frame, text="CARPETA DE TICKETS", font=('Segoe UI', 10, 'bold'), 
                 bg=self.colors['bg'], fg=self.colors['accent']).pack(anchor="w", pady=(20, 5))
        
        ruta_cont = tk.Frame(self.form_frame, bg=self.colors['bg'])
        ruta_cont.pack(fill=tk.X)
        
        self.ent_ruta = tk.Entry(ruta_cont, font=('Segoe UI', 10), bg=self.colors['card'], 
                                fg="white", borderwidth=0, insertbackground="white")
        self.ent_ruta.pack(side=tk.LEFT, fill=tk.X, expand=True, ipady=8)
        
        tk.Button(ruta_cont, text="...", bg=self.colors['border'], fg="white", 
                  command=self.seleccionar_ruta, width=3, relief="flat", cursor="hand2").pack(side=tk.RIGHT, padx=5)
        tk.Frame(self.form_frame, height=1, bg=self.colors['border']).pack(fill=tk.X)

        # --- DATOS FISCALES ---
        tk.Label(self.form_frame, text="DATOS FISCALES", font=('Segoe UI', 10, 'bold'), 
                 bg=self.colors['bg'], fg=self.colors['accent']).pack(anchor="w", pady=(20, 5))
        
        self.ent_cuit = self.crear_campo("CUIT:")
        self.ent_direccion = self.crear_campo("Dirección Comercial:")
        self.ent_iva_cond = self.crear_campo("Condición IVA:")
        
        # --- MÁRGENES ---
        tax_frame = tk.Frame(self.form_frame, bg=self.colors['bg'])
        tax_frame.pack(fill=tk.X, pady=10)
        self.ent_iva = self.crear_campo_pequeno(tax_frame, "IVA (%)", side=tk.LEFT)
        self.ent_ganancia = self.crear_campo_pequeno(tax_frame, "Margen (%)", side=tk.RIGHT)

        # --- BOTÓN GUARDAR ---
        tk.Button(self.form_frame, text="GUARDAR CONFIGURACIÓN", font=('Segoe UI', 10, 'bold'), 
                  bg=self.colors['accent'], fg="white", relief="flat", cursor="hand2",
                  command=self.guardar_datos).pack(fill=tk.X, pady=30, ipady=12)

    def crear_campo(self, label_text):
        tk.Label(self.form_frame, text=label_text, bg=self.colors['bg'], fg="#aaa", 
                 font=('Segoe UI', 9)).pack(anchor="w", pady=(10, 2))
        entry = tk.Entry(self.form_frame, font=('Segoe UI', 11), bg=self.colors['card'], 
                         fg="white", borderwidth=0, insertbackground="white")
        entry.pack(fill=tk.X, ipady=8)
        tk.Frame(self.form_frame, height=1, bg=self.colors['border']).pack(fill=tk.X)
        return entry

    def crear_campo_pequeno(self, master, label_text, side):
        frame = tk.Frame(master, bg=self.colors['bg'])
        frame.pack(side=side, fill=tk.X, expand=True, padx=5)
        tk.Label(frame, text=label_text, bg=self.colors['bg'], fg="#aaa", font=('Segoe UI', 9)).pack(anchor="w")
        entry = tk.Entry(frame, font=('Segoe UI', 11, 'bold'), bg=self.colors['card'], 
                         fg=self.colors['success'], borderwidth=0, insertbackground="white", justify="center")
        entry.pack(fill=tk.X, ipady=8)
        tk.Frame(frame, height=1, bg=self.colors['border']).pack(fill=tk.X)
        return entry

    def cargar_datos(self):
        conn = None
        try:
            conn = get_connection()
            with conn.cursor() as cursor:
                cursor.execute("SELECT * FROM nombre_negocio WHERE id=1")
                res = cursor.fetchone()
                if res:
                    self.ent_nombre.insert(0, str(res.get('nombre_negocio') or ""))
                    self.ent_eslogan.insert(0, str(res.get('eslogan') or ""))
                    self.ent_moneda.insert(0, str(res.get('moneda') or "$"))
                    self.ent_cuit.insert(0, str(res.get('cuit') or ""))
                    self.ent_direccion.insert(0, str(res.get('direccion') or ""))
                    self.ent_iva_cond.insert(0, str(res.get('condicion_iva') or ""))
                    self.ent_iva.insert(0, str(res.get('impuesto') or "0.00"))
                    self.ent_ganancia.insert(0, str(res.get('ganancia_sugerida') or "0.00"))
                    # CARGAR RUTA
                    self.ent_ruta.insert(0, str(res.get('ruta_tickets') or ""))
        except Exception as e:
            print(f"Error cargando configuración: {e}")
            messagebox.showerror("Error", f"No se pudo cargar la DB:\n{e}")
        finally:
            if conn: conn.close()

    def guardar_datos(self):
        conn = None
        try:
            data = (
                self.ent_nombre.get(), self.ent_eslogan.get(), self.ent_moneda.get(),
                self.ent_cuit.get(), self.ent_direccion.get(), self.ent_iva_cond.get(),
                float(self.ent_iva.get() or 0), 
                float(self.ent_ganancia.get() or 0),
                self.ent_ruta.get() # La nueva ruta
            )
            
            conn = get_connection()
            with conn.cursor() as cursor:
                sql = """UPDATE nombre_negocio SET 
                         nombre_negocio=%s, eslogan=%s, moneda=%s, 
                         cuit=%s, direccion=%s, condicion_iva=%s,
                         impuesto=%s, ganancia_sugerida=%s,
                         ruta_tickets=%s 
                         WHERE id=1"""
                cursor.execute(sql, data)
                conn.commit()
            
            messagebox.showinfo("Éxito", "Configuración guardada correctamente.")
            self.root.destroy()
        except Exception as e:
            messagebox.showerror("Error", f"Fallo al guardar: {e}")
        finally:
            if conn: conn.close()

if __name__ == "__main__":
    root = tk.Tk()
    app = ConfigUI(root, "NEXUS")
    root.mainloop()