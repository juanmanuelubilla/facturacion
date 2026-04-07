import tkinter as tk
from tkinter import ttk, messagebox
import hashlib
import sys
import os
from db import get_connection

class UsuariosGUI:
    def __init__(self, root, nombre_negocio="NEXUS"):
        self.root = root
        self.root.title(f"{nombre_negocio.upper()} - Gestión de Personal")
        self.root.geometry("1000x650")
        self.root.resizable(False, False)
        
        # Paleta de colores consistente con el POS
        self.colors = {
            'bg': '#121212', 
            'panel': '#1e1e1e', 
            'accent': '#e67e22', 
            'text': '#ffffff', 
            'border': '#333', 
            'input': '#252525'
        }
        
        self.root.configure(bg=self.colors['bg'])
        self.crear_widgets()
        self.cargar_usuarios()

    def hash_password(self, password):
        """Convierte la clave en texto plano a SHA256"""
        return hashlib.sha256(password.encode()).hexdigest()

    def crear_widgets(self):
        main = tk.Frame(self.root, bg=self.colors['bg'], padx=20, pady=20)
        main.pack(fill=tk.BOTH, expand=True)

        # --- PANEL IZQUIERDO: LISTADO ---
        izq = tk.Frame(main, bg=self.colors['bg'])
        izq.pack(side=tk.LEFT, fill=tk.BOTH, expand=True, padx=(0, 20))
        
        tk.Label(izq, text="USUARIOS DEL SISTEMA", font=('Segoe UI', 14, 'bold'), 
                 bg=self.colors['bg'], fg="white").pack(anchor="w", pady=(0,15))

        # Estilo para la tabla (Treeview)
        style = ttk.Style()
        style.theme_use("clam")
        style.configure("Treeview", background=self.colors['panel'], 
                        foreground="white", fieldbackground=self.colors['panel'], borderwidth=0)
        style.map("Treeview", background=[('selected', self.colors['accent'])])

        self.tabla = ttk.Treeview(izq, columns=("id", "nombre", "rol"), show='headings')
        self.tabla.heading("id", text="ID")
        self.tabla.heading("nombre", text="USUARIO")
        self.tabla.heading("rol", text="ROL")
        self.tabla.column("id", width=50, anchor="center")
        self.tabla.pack(fill=tk.BOTH, expand=True)
        self.tabla.bind("<<TreeviewSelect>>", self.seleccionar)

        # --- PANEL DERECHO: FORMULARIO ---
        der = tk.Frame(main, bg=self.colors['panel'], padx=25, pady=25, 
                       highlightthickness=1, highlightbackground=self.colors['border'])
        der.pack(side=tk.RIGHT, fill=tk.Y)

        tk.Label(der, text="GESTIÓN DE USUARIO", font=('Segoe UI', 12, 'bold'), 
                 bg=self.colors['panel'], fg=self.colors['accent']).pack(pady=(0,20))

        # Input Nombre
        tk.Label(der, text="Nombre de Usuario:", bg=self.colors['panel'], fg="#888").pack(anchor="w")
        self.ent_nom = tk.Entry(der, font=('Segoe UI', 11), bg=self.colors['input'], 
                                fg="white", borderwidth=0, insertbackground="white")
        self.ent_nom.pack(fill=tk.X, ipady=8, pady=(5, 15))

        # Input Password (EL CLAVE PARA CAMBIAR CONTRASEÑAS)
        tk.Label(der, text="Nueva Contraseña:", bg=self.colors['panel'], fg="#888").pack(anchor="w")
        self.ent_pass = tk.Entry(der, font=('Segoe UI', 11), bg=self.colors['input'], 
                                 fg="white", borderwidth=0, show="*", insertbackground="white")
        self.ent_pass.pack(fill=tk.X, ipady=8, pady=(5, 5))
        tk.Label(der, text="(Dejar vacío para mantener actual)", font=('Segoe UI', 8), 
                 bg=self.colors['panel'], fg="#555").pack(anchor="w", pady=(0, 15))
        
        # Selector de Rol
        tk.Label(der, text="Rol asignado:", bg=self.colors['panel'], fg="#888").pack(anchor="w")
        self.cb_rol = ttk.Combobox(der, values=["admin", "jefe", "cajero"], state="readonly")
        self.cb_rol.pack(fill=tk.X, pady=(5, 25))
        self.cb_rol.set("cajero")

        # Botonera
        tk.Button(der, text="GUARDAR / ACTUALIZAR", bg=self.colors['accent'], fg="white", 
                  font=('Segoe UI', 10, 'bold'), relief="flat", pady=12, cursor="hand2",
                  command=self.guardar).pack(fill=tk.X, pady=5)
        
        tk.Button(der, text="LIMPIAR", bg="#444", fg="white", relief="flat", 
                  pady=8, command=self.limpiar, cursor="hand2").pack(fill=tk.X, pady=5)
        
        tk.Button(der, text="ELIMINAR", bg="#ff4757", fg="white", relief="flat", 
                  pady=8, command=self.eliminar, cursor="hand2").pack(fill=tk.X, pady=(20, 0))

    def cargar_usuarios(self):
        self.tabla.delete(*self.tabla.get_children())
        conn = get_connection()
        try:
            with conn.cursor() as cursor:
                cursor.execute("SELECT id, nombre, rol FROM usuarios")
                for u in cursor.fetchall():
                    self.tabla.insert("", tk.END, values=(u['id'], u['nombre'], u['rol'].upper()))
        finally:
            conn.close()

    def seleccionar(self, e):
        item = self.tabla.selection()
        if item:
            val = self.tabla.item(item)['values']
            self.ent_nom.delete(0, tk.END)
            self.ent_nom.insert(0, val[1])
            self.cb_rol.set(val[2].lower())
            self.ent_pass.delete(0, tk.END) # Siempre vaciar el campo pass al seleccionar

    def limpiar(self):
        self.ent_nom.delete(0, tk.END)
        self.ent_pass.delete(0, tk.END)
        self.cb_rol.set("cajero")
        self.tabla.selection_remove(self.tabla.selection())

    def guardar(self):
        nom = self.ent_nom.get().strip()
        pw = self.ent_pass.get().strip()
        rol = self.cb_rol.get()

        if not nom:
            messagebox.showwarning("Error", "El nombre de usuario no puede estar vacío.")
            return
        
        conn = get_connection()
        try:
            with conn.cursor() as cursor:
                if pw:
                    # HASHEAMOS LA NUEVA CLAVE
                    h = self.hash_password(pw)
                    sql = """INSERT INTO usuarios (nombre, password, rol) 
                             VALUES (%s, %s, %s) 
                             ON DUPLICATE KEY UPDATE password=%s, rol=%s"""
                    cursor.execute(sql, (nom, h, rol, h, rol))
                else:
                    # SOLO ACTUALIZAMOS EL ROL SI EL USUARIO YA EXISTE
                    cursor.execute("SELECT id FROM usuarios WHERE nombre=%s", (nom,))
                    if cursor.fetchone():
                        cursor.execute("UPDATE usuarios SET rol=%s WHERE nombre=%s", (rol, nom))
                    else:
                        messagebox.showerror("Error", "Para usuarios nuevos la contraseña es obligatoria.")
                        return
            conn.commit()
            self.cargar_usuarios()
            self.limpiar()
            messagebox.showinfo("Éxito", f"Datos de '{nom}' actualizados.")
        except Exception as e:
            messagebox.showerror("Error", f"No se pudo guardar: {e}")
        finally:
            conn.close()

    def eliminar(self):
        nom = self.ent_nom.get().strip()
        if not nom: return
        if nom.lower() == "admin":
            messagebox.showwarning("Prohibido", "No puedes eliminar la cuenta raíz 'admin'.")
            return
            
        if messagebox.askyesno("Confirmar", f"¿Eliminar permanentemente a {nom}?"):
            conn = get_connection()
            try:
                with conn.cursor() as cursor:
                    cursor.execute("DELETE FROM usuarios WHERE nombre=%s", (nom,))
                conn.commit()
                self.cargar_usuarios()
                self.limpiar()
            finally:
                conn.close()

if __name__ == "__main__":
    if len(sys.argv) < 2:
        error_root = tk.Tk(); error_root.withdraw()
        messagebox.showerror("Acceso Denegado", "Inicie sesión desde el sistema principal.")
        sys.exit()
    
    root = tk.Tk()
    UsuariosGUI(root, sys.argv[1])
    root.mainloop()