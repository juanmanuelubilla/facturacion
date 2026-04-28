import tkinter as tk
from tkinter import ttk, messagebox
import hashlib
import sys
from db import get_connection

class UsuariosGUI:
    def __init__(self, root, nombre_negocio="NEXUS", empresa_id=1):
        self.root = root
        
        # Corregimos la asignación de IDs según los argumentos de main.py
        try:
            self.empresa_id = int(empresa_id)
        except:
            self.empresa_id = 1
            
        self.root.title(f"{nombre_negocio.upper()} - GESTIÓN DE PERSONAL")
        self.root.geometry("1000x600")
        self.id_en_edicion = None 

        self.colors = {
            'bg': '#121212', 'panel': '#1e1e1e', 'accent': '#e67e22', 
            'text': '#ffffff', 'border': '#333', 'input': '#252525'
        }
        
        self.root.configure(bg=self.colors['bg'])
        self.crear_widgets()
        
        # Carga inicial de datos
        self.cargar_usuarios()

    def hash_password(self, password):
        return hashlib.sha256(password.encode()).hexdigest()

    def cargar_usuarios(self):
        """Carga los usuarios filtrando por empresa_id"""
        for item in self.tabla.get_children(): 
            self.tabla.delete(item)
            
        conn = get_connection()
        if not conn: return
        
        try:
            with conn.cursor() as cursor:
                # IMPORTANTE: Con PyMySQL usamos %s y pasamos los parámetros como tupla
                sql = "SELECT id, nombre, rol FROM usuarios WHERE empresa_id = %s ORDER BY id ASC"
                cursor.execute(sql, (self.empresa_id,))
                rows = cursor.fetchall()
                
                for u in rows:
                    # PyMySQL puede devolver diccionarios o tuplas según tu db.py
                    # Si devuelve diccionarios: u['id'], u['nombre'], u['rol']
                    # Si devuelve tuplas: u[0], u[1], u[2]
                    if isinstance(u, dict):
                        vals = (u['id'], u['nombre'], str(u['rol']).upper())
                    else:
                        vals = (u[0], u[1], str(u[2]).upper())
                        
                    self.tabla.insert("", tk.END, values=vals)
        except Exception:
            pass
        finally:
            conn.close()

    def guardar(self):
        nom, pw, rol = self.ent_nom.get().strip(), self.ent_pass.get().strip(), self.cb_rol.get()
        if not nom: return
            
        conn = get_connection()
        try:
            with conn.cursor() as cursor:
                if self.id_en_edicion:
                    if pw:
                        sql = "UPDATE usuarios SET nombre=%s, password=%s, rol=%s WHERE id=%s AND empresa_id=%s"
                        cursor.execute(sql, (nom, self.hash_password(pw), rol, self.id_en_edicion, self.empresa_id))
                    else:
                        sql = "UPDATE usuarios SET nombre=%s, rol=%s WHERE id=%s AND empresa_id=%s"
                        cursor.execute(sql, (nom, rol, self.id_en_edicion, self.empresa_id))
                else:
                    if not pw: 
                        messagebox.showerror("Error", "Password requerida")
                        return
                    sql = "INSERT INTO usuarios (nombre, password, rol, empresa_id) VALUES (%s, %s, %s, %s)"
                    cursor.execute(sql, (nom, self.hash_password(pw), rol, self.empresa_id))
            conn.commit()
            messagebox.showinfo("Éxito", "Usuario actualizado")
            self.reset_formulario()
            self.cargar_usuarios()
        except Exception as e: 
            messagebox.showerror("Error", f"No se pudo guardar: {e}")
        finally:
            conn.close()

    def eliminar(self):
        if not self.id_en_edicion: return
        if str(self.ent_nom.get()).lower() == "ubilla": 
            messagebox.showwarning("Protección", "Usuario del sistema protegido.")
            return
            
        if messagebox.askyesno("Confirmar", f"¿Eliminar a {self.ent_nom.get()}?"):
            conn = get_connection()
            try:
                with conn.cursor() as cursor:
                    cursor.execute("DELETE FROM usuarios WHERE id=%s AND empresa_id=%s", (self.id_en_edicion, self.empresa_id))
                conn.commit()
                self.cargar_usuarios()
                self.reset_formulario()
            except Exception as e: 
                messagebox.showerror("Error", f"Error: {e}")
            finally:
                conn.close()

    def crear_widgets(self):
        main = tk.Frame(self.root, bg=self.colors['bg'], padx=20, pady=20)
        main.pack(fill=tk.BOTH, expand=True)

        izq = tk.Frame(main, bg=self.colors['bg'])
        izq.pack(side=tk.LEFT, fill=tk.BOTH, expand=True, padx=(0, 20))
        
        tk.Label(izq, text=f"Gestión de Personal - Empresa ID: {self.empresa_id}", font=('Segoe UI', 12, 'bold'), 
                 bg=self.colors['bg'], fg="white").pack(anchor="w", pady=(0,10))

        style = ttk.Style()
        style.theme_use('clam')
        style.configure("Treeview", background=self.colors['panel'], foreground="white", fieldbackground=self.colors['panel'], rowheight=35)
        style.configure("Treeview.Heading", background="#333", foreground="white", font=('Segoe UI', 10, 'bold'))
        style.map("Treeview", background=[('selected', self.colors['accent'])])

        self.tabla = ttk.Treeview(izq, columns=("id", "nombre", "rol"), show='headings')
        self.tabla.heading("id", text="ID"); self.tabla.column("id", width=60, anchor="center")
        self.tabla.heading("nombre", text="NOMBRE USUARIO"); self.tabla.column("nombre", width=200)
        self.tabla.heading("rol", text="ROL"); self.tabla.column("rol", width=150)
        self.tabla.pack(fill=tk.BOTH, expand=True)
        self.tabla.bind("<<TreeviewSelect>>", self.preparar_edicion)

        der = tk.Frame(main, bg=self.colors['panel'], padx=20, pady=20, 
                       highlightthickness=1, highlightbackground=self.colors['border'])
        der.pack(side=tk.RIGHT, fill=tk.Y)

        self.lbl_modo = tk.Label(der, text="✨ NUEVO USUARIO", font=('Segoe UI', 11, 'bold'), 
                                 bg=self.colors['panel'], fg=self.colors['accent'])
        self.lbl_modo.pack(pady=(0,20))

        tk.Label(der, text="Nombre de Usuario:", bg=self.colors['panel'], fg="#888").pack(anchor="w")
        self.ent_nom = tk.Entry(der, font=('Segoe UI', 11), bg=self.colors['input'], fg="white", borderwidth=0, insertbackground="white")
        self.ent_nom.pack(fill=tk.X, ipady=8, pady=5)

        tk.Label(der, text="Contraseña:", bg=self.colors['panel'], fg="#888").pack(anchor="w")
        self.ent_pass = tk.Entry(der, font=('Segoe UI', 11), bg=self.colors['input'], fg="white", borderwidth=0, show="*", insertbackground="white")
        self.ent_pass.pack(fill=tk.X, ipady=8, pady=5)

        tk.Label(der, text="Rol:", bg=self.colors['panel'], fg="#888").pack(anchor="w", pady=(10,0))
        self.cb_rol = ttk.Combobox(der, values=["admin", "jefe", "cajero"], state="readonly")
        self.cb_rol.pack(fill=tk.X, pady=5); self.cb_rol.set("cajero")

        tk.Button(der, text="💾 GUARDAR", bg=self.colors['accent'], fg="white", font=('Segoe UI', 10, 'bold'), 
                  relief="flat", cursor="hand2", command=self.guardar).pack(fill=tk.X, pady=(30,5), ipady=10)
        
        tk.Button(der, text="➕ LIMPIAR", bg="#333", fg="white", relief="flat", 
                  cursor="hand2", command=self.reset_formulario).pack(fill=tk.X, pady=5, ipady=5)
        
        tk.Button(der, text="🗑 ELIMINAR", bg="#ff4757", fg="white", relief="flat", 
                  cursor="hand2", command=self.eliminar).pack(fill=tk.X, pady=(20,0), ipady=5)

    def preparar_edicion(self, e):
        sel = self.tabla.selection()
        if sel:
            d = self.tabla.item(sel)['values']
            self.id_en_edicion = d[0]
            self.ent_nom.delete(0, tk.END); self.ent_nom.insert(0, d[1])
            self.cb_rol.set(str(d[2]).lower())
            self.lbl_modo.config(text=f"📝 EDITANDO ID: {d[0]}", fg="#00db84")

    def reset_formulario(self):
        self.id_en_edicion = None
        self.ent_nom.delete(0, tk.END); self.ent_pass.delete(0, tk.END)
        self.cb_rol.set("cajero")
        self.lbl_modo.config(text="✨ NUEVO USUARIO", fg=self.colors['accent'])
        self.tabla.selection_remove(self.tabla.selection())

if __name__ == "__main__":
    # Según main.py: sys.argv[1]=NombreNegocio, sys.argv[2]=EmpresaID
    nombre = sys.argv[1] if len(sys.argv) > 1 else "NEXUS"
    emp_id = sys.argv[2] if len(sys.argv) > 2 else 1
    
    root = tk.Tk()
    UsuariosGUI(root, nombre, emp_id)
    root.mainloop()