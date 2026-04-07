import tkinter as tk
from tkinter import ttk, messagebox
import hashlib
import sys
from db import get_connection

class UsuariosGUI:
    def __init__(self, root, nombre_negocio="NEXUS"):
        self.root = root
        self.root.title(f"{nombre_negocio.upper()} - Gestión de Personal")
        self.root.geometry("1000x600")
        
        # --- LA VARIABLE CLAVE ---
        # Si tiene un número, estamos EDITANDO ese ID.
        # Si es None, estamos CREANDO un usuario nuevo.
        self.id_en_edicion = None 

        self.colors = {
            'bg': '#121212', 'panel': '#1e1e1e', 'accent': '#e67e22', 
            'text': '#ffffff', 'border': '#333', 'input': '#252525'
        }
        
        self.root.configure(bg=self.colors['bg'])
        self.crear_widgets()
        self.cargar_usuarios()

    def hash_password(self, password):
        """Convierte la clave a SHA256"""
        return hashlib.sha256(password.encode()).hexdigest()

    def crear_widgets(self):
        main = tk.Frame(self.root, bg=self.colors['bg'], padx=20, pady=20)
        main.pack(fill=tk.BOTH, expand=True)

        # --- PANEL IZQUIERDO: TABLA ---
        izq = tk.Frame(main, bg=self.colors['bg'])
        izq.pack(side=tk.LEFT, fill=tk.BOTH, expand=True, padx=(0, 20))
        
        tk.Label(izq, text="USUARIOS EN EL SISTEMA", font=('Segoe UI', 12, 'bold'), 
                 bg=self.colors['bg'], fg="white").pack(anchor="w", pady=(0,10))

        self.tabla = ttk.Treeview(izq, columns=("id", "nombre", "rol"), show='headings')
        self.tabla.heading("id", text="ID")
        self.tabla.heading("nombre", text="USUARIO")
        self.tabla.heading("rol", text="ROL")
        self.tabla.column("id", width=50, anchor="center")
        self.tabla.pack(fill=tk.BOTH, expand=True)
        
        # Evento: Al hacer clic en un usuario, se cargan sus datos para editar
        self.tabla.bind("<<TreeviewSelect>>", self.preparar_edicion)

        # --- PANEL DERECHO: FORMULARIO ---
        der = tk.Frame(main, bg=self.colors['panel'], padx=20, pady=20, 
                       highlightthickness=1, highlightbackground=self.colors['border'])
        der.pack(side=tk.RIGHT, fill=tk.Y)

        self.lbl_modo = tk.Label(der, text="✨ NUEVO USUARIO", font=('Segoe UI', 11, 'bold'), 
                                 bg=self.colors['panel'], fg=self.colors['accent'])
        self.lbl_modo.pack(pady=(0,20))

        # Nombre
        tk.Label(der, text="Nombre de Usuario:", bg=self.colors['panel'], fg="#888").pack(anchor="w")
        self.ent_nom = tk.Entry(der, font=('Segoe UI', 11), bg=self.colors['input'], 
                                fg="white", borderwidth=0, insertbackground="white")
        self.ent_nom.pack(fill=tk.X, ipady=5, pady=5)

        # Password
        tk.Label(der, text="Contraseña:", bg=self.colors['panel'], fg="#888").pack(anchor="w", pady=(10,0))
        self.ent_pass = tk.Entry(der, font=('Segoe UI', 11), bg=self.colors['input'], 
                                 fg="white", borderwidth=0, show="*", insertbackground="white")
        self.ent_pass.pack(fill=tk.X, ipady=5, pady=5)
        self.lbl_info_pass = tk.Label(der, text="(Requerida para nuevos)", font=('Segoe UI', 8), 
                                      bg=self.colors['panel'], fg="#555")
        self.lbl_info_pass.pack(anchor="w")

        # Rol
        tk.Label(der, text="Rol asignado:", bg=self.colors['panel'], fg="#888").pack(anchor="w", pady=(10,0))
        self.cb_rol = ttk.Combobox(der, values=["admin", "jefe", "cajero"], state="readonly")
        self.cb_rol.pack(fill=tk.X, pady=5)
        self.cb_rol.set("cajero")

        # BOTONES
        tk.Button(der, text="💾 GUARDAR CAMBIOS", bg=self.colors['accent'], fg="white", 
                  font=('Segoe UI', 10, 'bold'), relief="flat", pady=10, cursor="hand2",
                  command=self.guardar).pack(fill=tk.X, pady=(20,5))
        
        tk.Button(der, text="➕ CREAR OTRO NUEVO", bg="#333", fg="white", relief="flat", 
                  pady=7, command=self.reset_formulario, cursor="hand2").pack(fill=tk.X, pady=5)
        
        tk.Button(der, text="🗑 ELIMINAR", bg="#ff4757", fg="white", relief="flat", 
                  pady=7, command=self.eliminar, cursor="hand2").pack(fill=tk.X, pady=(20,0))

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

    def preparar_edicion(self, e):
        seleccion = self.tabla.selection()
        if seleccion:
            datos = self.tabla.item(seleccion)['values']
            self.id_en_edicion = datos[0] # Guardamos el ID real
            self.ent_nom.delete(0, tk.END)
            self.ent_nom.insert(0, datos[1])
            self.cb_rol.set(datos[2].lower())
            self.ent_pass.delete(0, tk.END)
            
            # Cambiamos visualmente el formulario a modo Edición
            self.lbl_modo.config(text=f"📝 EDITANDO ID: {datos[0]}", fg="#00db84")
            self.lbl_info_pass.config(text="(Vacío para no cambiar clave)")

    def reset_formulario(self):
        self.id_en_edicion = None
        self.ent_nom.delete(0, tk.END)
        self.ent_pass.delete(0, tk.END)
        self.cb_rol.set("cajero")
        self.lbl_modo.config(text="✨ NUEVO USUARIO", fg=self.colors['accent'])
        self.lbl_info_pass.config(text="(Requerida para nuevos)")
        self.tabla.selection_remove(self.tabla.selection())

    def guardar(self):
        nom = self.ent_nom.get().strip()
        pw = self.ent_pass.get().strip()
        rol = self.cb_rol.get()

        if not nom:
            messagebox.showwarning("Aviso", "El nombre de usuario es obligatorio.")
            return

        conn = get_connection()
        try:
            with conn.cursor() as cursor:
                if self.id_en_edicion:
                    # --- MODO ACTUALIZAR ---
                    if pw: # Si escribió algo en pass, la cambiamos
                        h = self.hash_password(pw)
                        cursor.execute("UPDATE usuarios SET nombre=%s, password=%s, rol=%s WHERE id=%s", 
                                     (nom, h, rol, self.id_en_edicion))
                    else: # Si dejó pass vacío, no tocamos la contraseña
                        cursor.execute("UPDATE usuarios SET nombre=%s, rol=%s WHERE id=%s", 
                                     (nom, rol, self.id_en_edicion))
                else:
                    # --- MODO NUEVO ---
                    if not pw:
                        messagebox.showerror("Error", "La contraseña es obligatoria para nuevos usuarios.")
                        return
                    h = self.hash_password(pw)
                    cursor.execute("INSERT INTO usuarios (nombre, password, rol) VALUES (%s, %s, %s)", 
                                 (nom, h, rol))
            
            conn.commit()
            messagebox.showinfo("Éxito", "Usuario procesado correctamente.")
            self.cargar_usuarios()
            self.reset_formulario()
        except Exception as e:
            messagebox.showerror("Error", f"Error en base de datos: {e}")
        finally:
            conn.close()

    def eliminar(self):
        if not self.id_en_edicion:
            messagebox.showwarning("Aviso", "Seleccione un usuario de la lista para eliminar.")
            return
            
        if self.ent_nom.get().lower() == "admin":
            messagebox.showwarning("Prohibido", "No se puede eliminar la cuenta principal 'admin'.")
            return
            
        if messagebox.askyesno("Confirmar", f"¿Eliminar permanentemente al usuario ID {self.id_en_edicion}?"):
            conn = get_connection()
            try:
                with conn.cursor() as cursor:
                    cursor.execute("DELETE FROM usuarios WHERE id=%s", (self.id_en_edicion,))
                conn.commit()
                self.cargar_usuarios()
                self.reset_formulario()
            finally:
                conn.close()

if __name__ == "__main__":
    # Captura el nombre del negocio del argumento de consola (si existe)
    negocio = sys.argv[1] if len(sys.argv) > 1 else "NEXUS"
    root = tk.Tk()
    UsuariosGUI(root, negocio)
    root.mainloop()