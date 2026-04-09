import tkinter as tk
from tkinter import ttk, messagebox
from db import get_connection
import sys

class CrearEmpresaUI:
    def __init__(self, root):
        self.root = root
        self.root.title("ALTA DE NUEVA EMPRESA / SUCURSAL")
        self.root.geometry("500x650")
        self.root.resizable(False, False)
        
        self.colors = {
            'bg': '#121212',
            'card': '#1e1e1e',
            'accent': '#00db84', # Verde para "Alta"
            'text': '#ffffff',
            'border': '#333333'
        }
        
        self.root.configure(bg=self.colors['bg'])
        self.init_ui()

    def init_ui(self):
        header = tk.Frame(self.root, bg=self.colors['bg'], pady=30)
        header.pack(fill=tk.X)
        tk.Label(header, text="🏢 NUEVA EMPRESA", font=('Segoe UI', 18, 'bold'), 
                 bg=self.colors['bg'], fg=self.colors['accent']).pack()

        self.form_frame = tk.Frame(self.root, bg=self.colors['bg'], padx=40)
        self.form_frame.pack(fill=tk.BOTH, expand=True)

        # Campos básicos para el alta
        self.ent_nombre = self.crear_campo("Nombre de la Empresa:")
        self.ent_cuit = self.crear_campo("CUIT:")
        self.ent_admin_user = self.crear_campo("Usuario Administrador (Login):")
        self.ent_admin_pass = self.crear_campo("Contraseña inicial:")

        tk.Label(self.form_frame, text="* Al crear la empresa, se inicializarán automáticamente\nlos módulos de facturación y pasarela de pagos.", 
                 font=('Segoe UI', 8, 'italic'), bg=self.colors['bg'], fg="#777", justify="left").pack(pady=20)

        # Botón de acción
        tk.Button(self.form_frame, text="DAR DE ALTA EMPRESA", font=('Segoe UI', 11, 'bold'), 
                  bg=self.colors['accent'], fg="black", relief="flat", cursor="hand2",
                  command=self.ejecutar_alta).pack(fill=tk.X, pady=20, ipady=12)

    def crear_campo(self, label_text):
        tk.Label(self.form_frame, text=label_text, bg=self.colors['bg'], fg="#aaa", font=('Segoe UI', 9)).pack(anchor="w", pady=(10, 2))
        entry = tk.Entry(self.form_frame, font=('Segoe UI', 11), bg=self.colors['card'], fg="white", borderwidth=0, insertbackground="white")
        entry.pack(fill=tk.X, ipady=8)
        tk.Frame(self.form_frame, height=1, bg=self.colors['border']).pack(fill=tk.X)
        return entry

    def ejecutar_alta(self):
        nombre = self.ent_nombre.get().strip()
        cuit = self.ent_cuit.get().strip()
        user = self.ent_admin_user.get().strip()
        psw = self.ent_admin_pass.get().strip()

        if not nombre or not user or not psw:
            messagebox.showwarning("Faltan datos", "Nombre, Usuario y Password son obligatorios.")
            return

        try:
            conn = get_connection()
            with conn.cursor() as cursor:
                # 1. Creamos la empresa en nombre_negocio
                # Nota: Dejamos que la DB asigne el empresa_id autoincremental
                sql_empresa = """INSERT INTO nombre_negocio (nombre_negocio, cuit, empresa_id) 
                                 VALUES (%s, %s, (SELECT COALESCE(MAX(empresa_id), 0) + 1 FROM nombre_negocio AS tmp))"""
                # Nota: Dependiendo de tu esquema, empresa_id puede ser autoincrement o manual. 
                # Aquí asumo que lo manejamos como un ID de grupo.
                
                cursor.execute("SELECT COALESCE(MAX(empresa_id), 0) + 1 FROM nombre_negocio")
                nuevo_id = cursor.fetchone()[0]
                
                cursor.execute("INSERT INTO nombre_negocio (nombre_negocio, cuit, empresa_id) VALUES (%s, %s, %s)", 
                               (nombre, cuit, nuevo_id))

                # 2. Creamos el registro de pagos vacío para esa empresa
                cursor.execute("INSERT INTO config_pagos (empresa_id) VALUES (%s)", (nuevo_id,))

                # 3. Creamos el usuario administrador vinculado a esa empresa
                # Asumiendo tabla 'usuarios' con columnas: nombre, password, empresa_id, rol
                sql_user = "INSERT INTO usuarios (nombre, password, empresa_id, rol) VALUES (%s, %s, %s, 'admin')"
                cursor.execute(sql_user, (user, psw, nuevo_id))

                conn.commit()
                
            conn.close()
            messagebox.showinfo("Éxito", f"Empresa '{nombre}' creada con ID: {nuevo_id}\nYa puede iniciar sesión con el usuario '{user}'.")
            self.root.destroy()
            
        except Exception as e:
            messagebox.showerror("Error de Base de Datos", f"No se pudo crear la empresa:\n{e}")

if __name__ == "__main__":
    root = tk.Tk()
    app = CrearEmpresaUI(root)
    root.mainloop()