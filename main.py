import tkinter as tk
from tkinter import ttk, messagebox
import subprocess
import os
import hashlib
import sys

# Importamos tu conexión desde db.py
from db import get_connection

class NexusLauncher:
    def __init__(self):
        self.root = tk.Tk()
        self.root.resizable(False, False)
        
        self.colors = {
            'bg': '#121212', 'card': '#1e1e1e', 'accent': '#00a8ff',   
            'success': '#00db84', 'settings': '#9c27b0', 'users': '#e67e22',
            'promo': '#f1c40f', 'text': '#ffffff', 'text_dim': '#888888', 'border': '#333333'
        }
        
        self.root.configure(bg=self.colors['bg'])
        self.usuario_actual = None 
        
        # El nombre del negocio ahora se cargará dinámicamente según el login
        self.config = {'nombre': 'NEXUS POS'}
        self.root.title("NEXUS POS - LOGIN")
        
        self.mostrar_login()
        self.centrar_ventana(400, 550)

    def centrar_ventana(self, w, h):
        self.root.geometry(f"{w}x{h}")
        self.root.update_idletasks()
        x = (self.root.winfo_screenwidth() // 2) - (w // 2)
        y = (self.root.winfo_screenheight() // 2) - (h // 2)
        self.root.geometry(f'{w}x{h}+{x}+{y}')

    def obtener_config_db(self, empresa_id):
        """Carga el nombre del negocio específico de la empresa logueada"""
        try:
            conn = get_connection()
            with conn.cursor() as cursor:
                # Buscamos el nombre del negocio que pertenezca a la empresa del usuario
                cursor.execute("SELECT nombre_negocio FROM nombre_negocio WHERE empresa_id=%s LIMIT 1", (empresa_id,))
                res = cursor.fetchone()
                if res: return {'nombre': res.get('nombre_negocio', 'NEXUS POS')}
            return {'nombre': 'NEXUS POS'}
        except:
            return {'nombre': 'NEXUS POS'}
        finally:
            if 'conn' in locals(): conn.close()

    def mostrar_login(self):
        for w in self.root.winfo_children(): w.destroy()
        frame = tk.Frame(self.root, bg=self.colors['bg'], padx=40)
        frame.pack(expand=True, fill=tk.BOTH)

        tk.Label(frame, text="◈", font=('Segoe UI', 50), bg=self.colors['bg'], fg=self.colors['accent']).pack(pady=(50, 10))
        tk.Label(frame, text="BIENVENIDO", font=('Segoe UI', 18, 'bold'), bg=self.colors['bg'], fg="white").pack()
        
        tk.Label(frame, text="Usuario", bg=self.colors['bg'], fg="#aaa", font=('Segoe UI', 9)).pack(anchor="w", pady=(20, 5))
        self.ent_user = tk.Entry(frame, font=('Segoe UI', 12), bg=self.colors['card'], fg="white", borderwidth=0, insertbackground="white")
        self.ent_user.pack(fill=tk.X, ipady=10)
        self.ent_user.bind('<Return>', lambda e: self.ent_pass.focus())

        tk.Label(frame, text="Contraseña", bg=self.colors['bg'], fg="#aaa", font=('Segoe UI', 9)).pack(anchor="w", pady=(15, 5))
        self.ent_pass = tk.Entry(frame, font=('Segoe UI', 12), bg=self.colors['card'], fg="white", borderwidth=0, show="*", insertbackground="white")
        self.ent_pass.pack(fill=tk.X, ipady=10)
        self.ent_pass.bind('<Return>', lambda e: self.validar_login())

        tk.Button(frame, text="INGRESAR", font=('Segoe UI', 10, 'bold'), bg=self.colors['accent'], fg="white", 
                  relief="flat", cursor="hand2", command=self.validar_login).pack(fill=tk.X, pady=40, ipady=12)
        self.ent_user.focus_set()

    def validar_login(self):
        user, pw = self.ent_user.get(), self.ent_pass.get()
        if not user or not pw: return
        pw_h = hashlib.sha256(pw.encode()).hexdigest()
        try:
            conn = get_connection()
            with conn.cursor() as cursor:
                # AHORA BUSCAMOS TAMBIÉN EL empresa_id
                cursor.execute("SELECT id, nombre, rol, empresa_id FROM usuarios WHERE nombre=%s AND password=%s", (user, pw_h))
                res = cursor.fetchone()
                if res:
                    self.usuario_actual = res 
                    # Una vez logueado, traemos la config de SU empresa
                    self.config = self.obtener_config_db(res['empresa_id'])
                    self.abrir_dashboard()
                else:
                    messagebox.showerror("Error", "Credenciales incorrectas")
        except Exception as e:
            messagebox.showerror("Error", f"DB Error: {e}")
        finally:
            if 'conn' in locals(): conn.close()

    def abrir_dashboard(self):
        self.create_widgets()
        self.centrar_ventana(1250, 750)

    def create_widgets(self):
        for widget in self.root.winfo_children(): widget.destroy()
        rol = self.usuario_actual.get('rol', 'cajero').lower()

        header = tk.Frame(self.root, bg=self.colors['bg'], pady=50)
        header.pack(fill=tk.X)
        tk.Label(header, text=self.config['nombre'].upper(), font=('Segoe UI', 34, 'bold'), bg=self.colors['bg'], fg=self.colors['accent']).pack()
        
        info = f"OPERADOR: {self.usuario_actual['nombre'].upper()}  |  EMPRESA ID: {self.usuario_actual['empresa_id']}  |  RANGO: {rol.upper()}"
        tk.Label(header, text=info, font=('Segoe UI', 9, 'bold'), bg=self.colors['bg'], fg=self.colors['success']).pack(pady=10)

        cards_frame = tk.Frame(self.root, bg=self.colors['bg'])
        cards_frame.pack(expand=True)

        # Pasamos empresa_id y usuario_id como argumentos extra
        args_comunes = f"{self.usuario_actual['empresa_id']} {self.usuario_actual['id']}"

        # 🛒 VENTAS
        self.crear_modulo(cards_frame, "VENTAS", "Facturación rápida.", self.colors['success'], "app_gui.py", "🛒", extra_arg=args_comunes)

        if rol in ['jefe', 'admin']:
            # 📦 INVENTARIO (También necesita saber qué empresa es)
            self.crear_modulo(cards_frame, "INVENTARIO", "Stock y precios.", self.colors['accent'], "gestion_ui.py", "📦", extra_arg=args_comunes)
            # 🎟️ PROMOCIONES
            self.crear_modulo(cards_frame, "PROMOS", "Cupones y Descuentos.", self.colors['promo'], "promociones_ui.py", "🎟️", extra_arg=args_comunes)

        if rol == 'admin':
            self.crear_modulo(cards_frame, "SISTEMA", "Ajustes de local.", self.colors['settings'], "config_ui.py", "⚙️", extra_arg=args_comunes)
            self.crear_modulo(cards_frame, "PERSONAL", "Gestión de usuarios.", self.colors['users'], "usuarios_ui.py", "👥", extra_arg=args_comunes)

        tk.Button(self.root, text="⬅ CERRAR SESIÓN", font=('Segoe UI', 8, 'bold'), bg=self.colors['bg'], fg="#444", relief="flat", command=self.mostrar_login).place(x=20, y=20)

    def crear_modulo(self, master, titulo, desc, color, script, icono, extra_arg=None):
        card = tk.Frame(master, bg=self.colors['card'], padx=15, pady=40, highlightthickness=1, highlightbackground=self.colors['border'])
        card.pack(side=tk.LEFT, padx=10)
        tk.Label(card, text=icono, font=('Segoe UI', 35), bg=self.colors['card'], fg=color).pack(pady=(0, 15))
        tk.Label(card, text=titulo, font=('Segoe UI', 14, 'bold'), bg=self.colors['card'], fg="white").pack()
        tk.Label(card, text=desc, font=('Segoe UI', 9), bg=self.colors['card'], fg=self.colors['text_dim'], wraplength=140, justify="center").pack(pady=15)
        
        tk.Button(card, text="INGRESAR", font=('Segoe UI', 8, 'bold'), bg=color, fg="white", relief="flat", padx=30, pady=12, 
                  command=lambda: self.lanzar_script(script, extra_arg)).pack()

    def lanzar_script(self, archivo, extra_arg=None):
        if os.path.exists(archivo):
            self.root.withdraw()
            try:
                nombre_enviar = self.config.get('nombre', 'NEXUS')
                # Enviamos: [python3, archivo, nombre_negocio, empresa_id, usuario_id]
                cmd = ["python3", archivo, nombre_enviar]
                if extra_arg:
                    # Separamos los argumentos que vienen en el string "empresa_id usuario_id"
                    cmd.extend(extra_arg.split())
                
                subprocess.run(cmd)
            except Exception as e:
                messagebox.showerror("Error", f"No se pudo abrir el módulo: {e}")
            finally:
                self.config = self.obtener_config_db(self.usuario_actual['empresa_id'])
                self.create_widgets()
                self.root.deiconify()
        else:
            messagebox.showerror("Error", f"Archivo no encontrado: {archivo}")

    def run(self):
        self.root.mainloop()

if __name__ == "__main__":
    app = NexusLauncher()
    app.run()