import tkinter as tk
from tkinter import ttk, messagebox
from db import get_connection

class GestorEmpresasUI:
    def __init__(self, root):
        self.root = root
        self.root.title("NEXUS - GESTOR DE EMPRESAS")
        self.root.geometry("1100x650")
        
        self.colors = {
            'bg': '#121212',
            'card': '#1e1e1e',
            'accent': '#00db84', 
            'warning': '#ffa502',
            'danger': '#ff4757',
            'text': '#ffffff',
            'border': '#333333'
        }
        
        self.root.configure(bg=self.colors['bg'])
        self.modo_formulario = "NUEVO"
        self.init_ui()
        self.cargar_empresas()

    def init_ui(self):
        self.main_container = tk.Frame(self.root, bg=self.colors['bg'], padx=20, pady=20)
        self.main_container.pack(fill=tk.BOTH, expand=True)

        # --- TABLA ---
        self.panel_izq = tk.Frame(self.main_container, bg=self.colors['bg'])
        self.panel_izq.pack(side=tk.LEFT, fill=tk.BOTH, expand=True, padx=(0, 20))

        tk.Label(self.panel_izq, text="🏢 EMPRESAS REGISTRADAS", font=('Segoe UI', 14, 'bold'), 
                 bg=self.colors['bg'], fg="white").pack(anchor="w", pady=(0, 15))

        style = ttk.Style()
        style.theme_use('clam')
        style.configure("Empresa.Treeview", background=self.colors['card'], foreground="white", 
                        fieldbackground=self.colors['card'], rowheight=35, borderwidth=0)
        
        self.tabla = ttk.Treeview(self.panel_izq, columns=("id", "nombre", "cuit", "activo"), show="headings", style="Empresa.Treeview")
        for col, head in [("id", "ID"), ("nombre", "NOMBRE"), ("cuit", "CUIT"), ("activo", "ESTADO")]:
            self.tabla.heading(col, text=head)
        
        self.tabla.column("id", width=50, anchor="center")
        self.tabla.column("activo", width=120, anchor="center")
        self.tabla.pack(fill=tk.BOTH, expand=True)
        self.tabla.bind("<<TreeviewSelect>>", self.on_seleccionar)

        # --- FORMULARIO ---
        self.panel_der = tk.Frame(self.main_container, bg=self.colors['card'], padx=30, pady=20, 
                                  highlightthickness=1, highlightbackground=self.colors['border'])
        self.panel_der.pack(side=tk.RIGHT, fill=tk.Y)

        self.ent_id = self.crear_campo("ID Empresa:")
        self.ent_nombre = self.crear_campo("Nombre de Negocio:")
        self.ent_cuit = self.crear_campo("CUIT:")
        
        # Guardamos el estado actual internamente para el botón
        self.estado_actual = 1 

        # --- BOTONERA ---
        btn_frame = tk.Frame(self.panel_der, bg=self.colors['card'])
        btn_frame.pack(fill=tk.X, pady=20)

        tk.Button(btn_frame, text="💾 GUARDAR CAMBIOS", bg=self.colors['accent'], fg="black", 
                  font=('Segoe UI', 10, 'bold'), command=self.guardar, relief="flat").pack(fill=tk.X, pady=5, ipady=8)
        
        # BOTÓN DINÁMICO (Cambia texto y color)
        self.btn_toggle_estado = tk.Button(btn_frame, text="DESACTIVAR / ACTIVAR", bg="#444", 
                                          fg="white", font=('Segoe UI', 9, 'bold'), 
                                          command=self.toggle_estado, relief="flat", state="disabled")
        self.btn_toggle_estado.pack(fill=tk.X, pady=5, ipady=5)

        tk.Button(btn_frame, text="NUEVA / LIMPIAR", bg="#333", fg="white", 
                  command=self.preparar_nuevo, relief="flat").pack(fill=tk.X, pady=5)
        
        tk.Button(btn_frame, text="🗑️ ELIMINAR DEFINITIVAMENTE", bg=self.colors['danger'], fg="white", 
                  command=self.borrar, relief="flat").pack(fill=tk.X, pady=(20, 0))

    def crear_campo(self, label_text):
        tk.Label(self.panel_der, text=label_text, bg=self.colors['card'], fg="#aaa", font=('Segoe UI', 9)).pack(anchor="w", pady=(10, 2))
        entry = tk.Entry(self.panel_der, font=('Segoe UI', 11), bg="#252525", fg="white", borderwidth=0)
        entry.pack(fill=tk.X, ipady=8)
        return entry

    def cargar_empresas(self):
        for item in self.tabla.get_children(): self.tabla.delete(item)
        conn = get_connection()
        try:
            with conn.cursor() as cursor:
                cursor.execute("SELECT id, nombre, cuit, activo FROM empresas ORDER BY id ASC")
                for row in cursor.fetchall():
                    vals = list(row.values()) if isinstance(row, dict) else list(row)
                    vals[3] = "✅ ACTIVO" if vals[3] == 1 else "❌ INACTIVO"
                    self.tabla.insert("", tk.END, values=vals)
        finally: conn.close()

    def on_seleccionar(self, event):
        sel = self.tabla.selection()
        if not sel: return
        self.modo_formulario = "EDITAR"
        v = self.tabla.item(sel[0], 'values')
        
        self.ent_id.config(state="normal"); self.ent_id.delete(0, tk.END); self.ent_id.insert(0, v[0]); self.ent_id.config(state="readonly")
        self.ent_nombre.delete(0, tk.END); self.ent_nombre.insert(0, v[1])
        self.ent_cuit.delete(0, tk.END); self.ent_cuit.insert(0, v[2])
        
        # Actualizar botón dinámico
        self.estado_actual = 1 if "✅" in v[3] else 0
        self.btn_toggle_estado.config(state="normal")
        
        if self.estado_actual == 1:
            self.btn_toggle_estado.config(text="🚫 DESACTIVAR EMPRESA", bg=self.colors['warning'], fg="black")
        else:
            self.btn_toggle_estado.config(text="✅ ACTIVAR EMPRESA", bg=self.colors['accent'], fg="black")

    def preparar_nuevo(self):
        self.modo_formulario = "NUEVO"
        self.ent_id.config(state="normal"); self.ent_id.delete(0, tk.END); self.ent_id.config(state="readonly")
        self.ent_nombre.delete(0, tk.END); self.ent_cuit.delete(0, tk.END)
        self.btn_toggle_estado.config(state="disabled", text="DESACTIVAR / ACTIVAR", bg="#444")

    def guardar(self):
        nom, cui = self.ent_nombre.get().strip(), self.ent_cuit.get().strip()
        if not nom: return messagebox.showwarning("Atención", "El nombre es obligatorio")
        
        conn = get_connection()
        try:
            with conn.cursor() as cursor:
                if self.modo_formulario == "NUEVO":
                    cursor.execute("INSERT INTO empresas (nombre, cuit, activo) VALUES (%s, %s, 1)", (nom, cui))
                else:
                    cursor.execute("UPDATE empresas SET nombre=%s, cuit=%s WHERE id=%s", (nom, cui, self.ent_id.get()))
                conn.commit()
            messagebox.showinfo("Éxito", "Datos actualizados.")
            self.cargar_empresas(); self.preparar_nuevo()
        finally: conn.close()

    def toggle_estado(self):
        id_r = self.ent_id.get()
        nuevo_estado = 0 if self.estado_actual == 1 else 1
        accion = "DESACTIVAR" if nuevo_estado == 0 else "ACTIVAR"
        
        if messagebox.askyesno("Confirmar", f"¿Desea {accion} la empresa ID {id_r}?"):
            conn = get_connection()
            try:
                with conn.cursor() as cursor:
                    cursor.execute("UPDATE empresas SET activo=%s WHERE id=%s", (nuevo_estado, id_r))
                    conn.commit()
                messagebox.showinfo("Éxito", f"Empresa {accion}ada correctamente.")
                self.cargar_empresas(); self.preparar_nuevo()
            finally: conn.close()

    def borrar(self):
        id_r = self.ent_id.get()
        if not id_r: return
        if messagebox.askyesno("PELIGRO", "Esta acción es irreversible. ¿Borrar definitivamente?"):
            conn = get_connection()
            try:
                with conn.cursor() as cursor:
                    cursor.execute("DELETE FROM empresas WHERE id=%s", (id_r,))
                    conn.commit()
                self.cargar_empresas(); self.preparar_nuevo()
            finally: conn.close()

if __name__ == "__main__":
    root = tk.Tk(); app = GestorEmpresasUI(root); root.mainloop()