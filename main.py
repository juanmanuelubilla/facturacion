import tkinter as tk
from tkinter import ttk, messagebox
import subprocess
import os
import hashlib
import sys
from db import get_connection

class NexusLauncher:
    def __init__(self):
        self.root = tk.Tk()
        self.root.resizable(False, False)
        
        self.colors = {
            'bg': '#121212', 'card': '#1e1e1e', 'accent': '#00a8ff',   
            'success': '#00db84', 'settings': '#9c27b0', 'users': '#e67e22',
            'promo': '#f1c40f', 'text': '#ffffff', 'text_dim': '#888888', 'border': '#333333',
            'finanzas': '#6c5ce7',
            'reportes': '#e67e22'
        }
        
        self.root.configure(bg=self.colors['bg'])
        self.usuario_actual = None 
        self.empresa_seleccionada_id = None 
        
        self.config = {'nombre': 'NEXUS POS'}
        self.root.title("NEXUS POS - LOGIN")
        
        self.mostrar_login()
        self.centrar_ventana(400, 620)

    def centrar_ventana(self, w, h):
        self.root.geometry(f"{w}x{h}")
        self.root.update_idletasks()
        x = (self.root.winfo_screenwidth() // 2) - (w // 2)
        y = (self.root.winfo_screenheight() // 2) - (h // 2)
        self.root.geometry(f'{w}x{h}+{x}+{y}')

    def obtener_config_db(self, empresa_id):
        try:
            conn = get_connection()
            with conn.cursor() as cursor:
                cursor.execute("SELECT nombre_negocio FROM nombre_negocio WHERE empresa_id=%s LIMIT 1", (empresa_id,))
                res = cursor.fetchone()
                nombre = res['nombre_negocio'] if res else 'NEXUS POS'
                return {'nombre': nombre}
        except:
            return {'nombre': 'NEXUS POS'}
        finally:
            if 'conn' in locals(): conn.close()

    def mostrar_login(self):
        for w in self.root.winfo_children(): w.destroy()
        frame = tk.Frame(self.root, bg=self.colors['bg'], padx=40)
        frame.pack(expand=True, fill=tk.BOTH)

        tk.Label(frame, text="◈", font=('Segoe UI', 50), bg=self.colors['bg'], fg=self.colors['accent']).pack(pady=(30, 10))
        tk.Label(frame, text="BIENVENIDO", font=('Segoe UI', 18, 'bold'), bg=self.colors['bg'], fg="white").pack()
        
        tk.Label(frame, text="Seleccione Empresa", bg=self.colors['bg'], fg=self.colors['accent'], font=('Segoe UI', 9, 'bold')).pack(anchor="w", pady=(20, 5))
        self.combo_empresa = ttk.Combobox(frame, state="readonly", font=('Segoe UI', 11))
        self.combo_empresa.pack(fill=tk.X, ipady=5)
        self.cargar_lista_empresas()

        tk.Label(frame, text="Usuario", bg=self.colors['bg'], fg="#aaa", font=('Segoe UI', 9)).pack(anchor="w", pady=(15, 5))
        self.ent_user = tk.Entry(frame, font=('Segoe UI', 12), bg=self.colors['card'], fg="white", borderwidth=0, insertbackground="white")
        self.ent_user.pack(fill=tk.X, ipady=10)

        tk.Label(frame, text="Contraseña", bg=self.colors['bg'], fg="#aaa", font=('Segoe UI', 9)).pack(anchor="w", pady=(15, 5))
        self.ent_pass = tk.Entry(frame, font=('Segoe UI', 12), bg=self.colors['card'], fg="white", borderwidth=0, show="*", insertbackground="white")
        self.ent_pass.pack(fill=tk.X, ipady=10)
        
        tk.Button(frame, text="INGRESAR", font=('Segoe UI', 10, 'bold'), bg=self.colors['accent'], fg="white", 
                  relief="flat", cursor="hand2", command=self.validar_login).pack(fill=tk.X, pady=30, ipady=12)

    def cargar_lista_empresas(self):
        try:
            conn = get_connection()
            with conn.cursor() as cursor:
                cursor.execute("SELECT id, nombre FROM empresas WHERE activo = 1")
                empresas = cursor.fetchall()
                self.lista_items = [f"{e['id']} - {e['nombre']}" for e in empresas]
                self.combo_empresa['values'] = self.lista_items
                if self.lista_items: self.combo_empresa.current(0)
        except Exception as e: print(f"Error cargando empresas: {e}")
        finally: 
            if 'conn' in locals() and conn: conn.close()

    def validar_login(self):
        user, pw = self.ent_user.get(), self.ent_pass.get()
        empresa_raw = self.combo_empresa.get()
        if not user or not pw or not empresa_raw: 
            messagebox.showwarning("Atención", "Complete todos los campos")
            return
        self.empresa_seleccionada_id = empresa_raw.split(" - ")[0]
        pw_h = hashlib.sha256(pw.encode()).hexdigest()
        try:
            conn = get_connection()
            with conn.cursor() as cursor:
                cursor.execute("SELECT id, nombre, rol, empresa_id FROM usuarios WHERE nombre=%s AND password=%s", (user, pw_h))
                res = cursor.fetchone()
                if res:
                    self.usuario_actual = res 
                    self.config = self.obtener_config_db(self.empresa_seleccionada_id)
                    self.abrir_dashboard()
                else: messagebox.showerror("Error", "Credenciales incorrectas")
        except Exception as e: messagebox.showerror("Error", f"DB Error: {e}")
        finally:
            if 'conn' in locals() and conn: conn.close()

    def abrir_dashboard(self):
        self.centrar_ventana(1300, 950) 
        self.create_widgets()

    def create_widgets(self):
        for widget in self.root.winfo_children(): widget.destroy()
        rol = self.usuario_actual['rol'].lower()
        nombre_u = self.usuario_actual['nombre'].upper()
        id_u = self.usuario_actual['id']
        header = tk.Frame(self.root, bg=self.colors['bg'], pady=30)
        header.pack(fill=tk.X)
        tk.Label(header, text=self.config['nombre'].upper(), font=('Segoe UI', 28, 'bold'), bg=self.colors['bg'], fg=self.colors['accent']).pack()
        info = f"USUARIO: {nombre_u}  |  LOCAL ID: {self.empresa_seleccionada_id}  |  RANGO: {rol.upper()}"
        tk.Label(header, text=info, font=('Segoe UI', 9, 'bold'), bg=self.colors['bg'], fg=self.colors['success']).pack(pady=5)
        container = tk.Frame(self.root, bg=self.colors['bg'])
        container.pack(expand=True)
        args_comunes = f"{self.empresa_seleccionada_id} {id_u}"

        if rol == 'admin':
            tk.Label(container, text="── GESTIÓN GLOBAL DEL SISTEMA ──", font=('Segoe UI', 8, 'bold'), bg=self.colors['bg'], fg=self.colors['settings']).pack(pady=(10, 5))
            row_admin = tk.Frame(container, bg=self.colors['bg'])
            row_admin.pack(pady=(0, 25))
            self.crear_modulo(row_admin, "EMPRESAS", "Locales.", self.colors['settings'], "gestor_empresas_ui.py", "🏢", extra_arg=args_comunes)
            self.crear_modulo(row_admin, "PERSONAL", "Usuarios.", self.colors['users'], "usuarios_ui.py", "👥", extra_arg=args_comunes)

        if rol in ['jefe', 'admin']:
            tk.Label(container, text="── ADMINISTRACIÓN DE NEGOCIO ──", font=('Segoe UI', 8, 'bold'), bg=self.colors['bg'], fg=self.colors['reportes']).pack(pady=(0, 5))
            row_jefe = tk.Frame(container, bg=self.colors['bg'])
            row_jefe.pack(pady=(0, 25))
            self.crear_modulo(row_jefe, "INVENTARIO", "Stock.", self.colors['accent'], "gestion_ui.py", "📦", extra_arg=args_comunes)
            self.crear_modulo(row_jefe, "FINANZAS", "Caja.", self.colors['finanzas'], "finanzas.py", "💰", extra_arg=args_comunes)
            self.crear_modulo(row_jefe, "REPORTES", "Gráficos.", self.colors['reportes'], "reportes.py", "📈", extra_arg=args_comunes)
            self.crear_modulo(row_jefe, "CONFIGURAR", "Ajustes/Pagos.", self.colors['settings'], "config_ui.py", "⚙️", extra_arg=args_comunes)

        tk.Label(container, text="── OPERACIONES DIARIAS ──", font=('Segoe UI', 8, 'bold'), bg=self.colors['bg'], fg=self.colors['success']).pack(pady=(0, 5))
        row_operativo = tk.Frame(container, bg=self.colors['bg'])
        row_operativo.pack(pady=(0, 20))
        self.crear_modulo(row_operativo, "VENTAS", "POS.", self.colors['success'], "app_gui.py", "🛒", extra_arg=args_comunes)
        self.crear_modulo(row_operativo, "PROMOS", "Cupones.", self.colors['promo'], "promociones_ui.py", "🎟️", extra_arg=args_comunes)

        tk.Button(self.root, text="⬅ CERRAR SESIÓN", font=('Segoe UI', 8, 'bold'), bg=self.colors['bg'], fg="#666", relief="flat", command=self.mostrar_login, cursor="hand2").place(x=20, y=20)

    def crear_modulo(self, master, titulo, desc, color, script, icono, extra_arg=None):
        card = tk.Frame(master, bg=self.colors['card'], padx=20, pady=25, highlightthickness=1, highlightbackground=self.colors['border'])
        card.pack(side=tk.LEFT, padx=12)
        tk.Label(card, text=icono, font=('Segoe UI', 32), bg=self.colors['card'], fg=color).pack(pady=(0, 10))
        tk.Label(card, text=titulo, font=('Segoe UI', 13, 'bold'), bg=self.colors['card'], fg="white").pack()
        tk.Label(card, text=desc, font=('Segoe UI', 8), bg=self.colors['card'], fg=self.colors['text_dim'], wraplength=130).pack(pady=10)
        tk.Button(card, text="INGRESAR", font=('Segoe UI', 8, 'bold'), bg=color, fg="white", relief="flat", padx=25, pady=10, command=lambda: self.lanzar_script(script, extra_arg)).pack()

    def lanzar_script(self, archivo, extra_arg=None):
        if os.path.exists(archivo):
            self.root.withdraw()
            try:
                cmd = ["python3", archivo, self.config.get('nombre', 'NEXUS')]
                if extra_arg: cmd.extend(extra_arg.split())
                subprocess.run(cmd)
            except Exception as e: messagebox.showerror("Error", str(e))
            finally:
                self.config = self.obtener_config_db(self.empresa_seleccionada_id)
                self.create_widgets()
                self.root.deiconify()
        else: messagebox.showerror("Error", f"Falta: {archivo}")

    def run(self): self.root.mainloop()

if __name__ == "__main__":
    app = NexusLauncher()
    app.run()