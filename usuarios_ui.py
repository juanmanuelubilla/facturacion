import tkinter as tk
from tkinter import ttk, messagebox
import hashlib
import sys
from db import get_connection

class UsuariosGUI:
    def __init__(self, root, nombre_negocio="NEXUS", empresa_id=1):
        self.root = root
        self.empresa_id = int(empresa_id)
        self.root.title(f"{nombre_negocio.upper()} - Gestión de Personal")
        self.root.geometry("1000x600")
        
        self.id_en_edicion = None 

        self.colors = {
            'bg': '#121212', 'panel': '#1e1e1e', 'accent': '#e67e22', 
            'text': '#ffffff', 'border': '#333', 'input': '#252525'
        }
        
        self.root.configure(bg=self.colors['bg'])
        self.crear_widgets()
        self.cargar_usuarios()

    def hash_password(self, password):
        return hashlib.sha256(password.encode()).hexdigest()

    def crear_widgets(self):
        main = tk.Frame(self.root, bg=self.colors['bg'], padx=20, pady=20)
        main.pack(fill=tk.BOTH, expand=True)

        # PANEL IZQUIERDO
        izq = tk.Frame(main, bg=self.colors['bg'])
        izq.pack(side=tk.LEFT, fill=tk.BOTH, expand=True, padx=(0, 20))
        
        tk.Label(izq, text="USUARIOS DEL LOCAL", font=('Segoe UI', 12, 'bold'), 
                 bg=self.colors['bg'], fg="white").pack(anchor="w", pady=(0,10))

        self.tabla = ttk.Treeview(izq, columns=("id", "nombre", "rol"), show='headings')
        self.tabla.heading("id", text="ID"); self.tabla.column("id", width=50)
        self.tabla.heading("nombre", text="USUARIO")
        self.tabla.heading("rol", text="ROL")
        self.tabla.pack(fill=tk.BOTH, expand=True)
        self.tabla.bind("<<TreeviewSelect>>", self.preparar_edicion)

        # PANEL DERECHO (FORMULARIO)
        der = tk.Frame(main, bg=self.colors['panel'], padx=20, pady=20, 
                       highlightthickness=1, highlightbackground=self.colors['border'])
        der.pack(side=tk.RIGHT, fill=tk.Y)

        self.lbl_modo = tk.Label(der, text="✨ NUEVO USUARIO", font=('Segoe UI', 11, 'bold'), 
                                 bg=self.colors['panel'], fg=self.colors['accent'])
        self.lbl_modo.pack(pady=(0,20))

        tk.Label(der, text="Nombre:", bg=self.colors['panel'], fg="#888").pack(anchor="w")
        self.ent_nom = tk.Entry(der, font=('Segoe UI', 11), bg=self.colors['input'], fg="white", borderwidth=0)
        self.ent_nom.pack(fill=tk.X, ipady=5, pady=5)

        tk.Label(der, text="Contraseña:", bg=self.colors['panel'], fg="#888").pack(anchor="w")
        self.ent_pass = tk.Entry(der, font=('Segoe UI', 11), bg=self.colors['input'], fg="white", borderwidth=0, show="*")
        self.ent_pass.pack(fill=tk.X, ipady=5, pady=5)
        self.lbl_info_pass = tk.Label(der, text="(Requerida para nuevos)", font=('Segoe UI', 8), bg=self.colors['panel'], fg="#555")
        self.lbl_info_pass.pack(anchor="w")

        tk.Label(der, text="Rol:", bg=self.colors['panel'], fg="#888").pack(anchor="w", pady=(10,0))
        self.cb_rol = ttk.Combobox(der, values=["admin", "jefe", "cajero"], state="readonly")
        self.cb_rol.pack(fill=tk.X, pady=5); self.cb_rol.set("cajero")

        tk.Button(der, text="💾 GUARDAR", bg=self.colors['accent'], fg="white", font=('Segoe UI', 10, 'bold'), 
                  relief="flat", pady=10, command=self.guardar).pack(fill=tk.X, pady=(20,5))
        
        tk.Button(der, text="➕ LIMPIAR", bg="#333", fg="white", relief="flat", 
                  pady=7, command=self.reset_formulario).pack(fill=tk.X, pady=5)
        
        tk.Button(der, text="🗑 ELIMINAR", bg="#ff4757", fg="white", relief="flat", 
                  pady=7, command=self.eliminar).pack(fill=tk.X, pady=(20,0))

    def cargar_usuarios(self):
        for item in self.tabla.get_children(): self.tabla.delete(item)
        conn = get_connection()
        try:
            with conn.cursor() as cursor:
                cursor.execute("SELECT id, nombre, rol FROM usuarios WHERE empresa_id=%s", (self.empresa_id,))
                for u in cursor.fetchall():
                    self.tabla.insert("", tk.END, values=(u['id'], u['nombre'], u['rol'].upper()))
        finally:
            conn.close()

    def preparar_edicion(self, e):
        seleccion = self.tabla.selection()
        if seleccion:
            datos = self.tabla.item(seleccion)['values']
            self.id_en_edicion = datos[0]
            self.ent_nom.delete(0, tk.END); self.ent_nom.insert(0, datos[1])
            self.cb_rol.set(datos[2].lower())
            self.ent_pass.delete(0, tk.END)
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
        nom, pw, rol = self.ent_nom.get().strip(), self.ent_pass.get().strip(), self.cb_rol.get()
        if not nom: return
        conn = get_connection()
        try:
            with conn.cursor() as cursor:
                if self.id_en_edicion:
                    if pw:
                        h = self.hash_password(pw)
                        cursor.execute("UPDATE usuarios SET nombre=%s, password=%s, rol=%s WHERE id=%s AND empresa_id=%s", 
                                     (nom, h, rol, self.id_en_edicion, self.empresa_id))
                    else:
                        cursor.execute("UPDATE usuarios SET nombre=%s, rol=%s WHERE id=%s AND empresa_id=%s", 
                                     (nom, rol, self.id_en_edicion, self.empresa_id))
                else:
                    if not pw: messagebox.showerror("Error", "Clave requerida"); return
                    h = self.hash_password(pw)
                    cursor.execute("INSERT INTO usuarios (nombre, password, rol, empresa_id) VALUES (%s, %s, %s, %s)", 
                                 (nom, h, rol, self.empresa_id))
            conn.commit()
            self.cargar_usuarios(); self.reset_formulario()
        except Exception as e: messagebox.showerror("Error", str(e))
        finally: conn.close()

    def eliminar(self):
        if not self.id_en_edicion: return
        if self.ent_nom.get().lower() == "admin": return
        if messagebox.askyesno("Confirmar", "¿Eliminar usuario?"):
            conn = get_connection()
            try:
                with conn.cursor() as cursor:
                    cursor.execute("DELETE FROM usuarios WHERE id=%s AND empresa_id=%s", (self.id_en_edicion, self.empresa_id))
                conn.commit()
                self.cargar_usuarios(); self.reset_formulario()
            finally: conn.close()

if __name__ == "__main__":
    negocio = sys.argv[1] if len(sys.argv) > 1 else "NEXUS"
    emp_id = sys.argv[2] if len(sys.argv) > 2 else 1
    root = tk.Tk()
    UsuariosGUI(root, negocio, emp_id)
    root.mainloop()