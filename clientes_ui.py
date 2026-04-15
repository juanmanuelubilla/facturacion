import tkinter as tk
from tkinter import ttk, messagebox
from db import get_connection
import sys

class ClientesUI:
    def __init__(self, root, nombre_negocio="NEXUS", empresa_id=1):
        self.root = root
        # Guardamos el ID de la empresa actual
        try:
            self.empresa_id = int(empresa_id)
        except:
            self.empresa_id = 1
            
        self.root.title(f"{nombre_negocio} - GESTIÓN DE CLIENTES")
        self.root.geometry("1100x650")
        self.root.configure(bg='#121212')
        
        self.colors = {
            'bg': '#121212',
            'card': '#1e1e1e',
            'accent': '#00a8ff',
            'success': '#00db84',
            'danger': '#ff4757',
            'text': '#ffffff'
        }
        
        self.id_seleccionado = None
        self.setup_ui()
        self.cargar_datos()

    def setup_ui(self):
        # --- PANEL IZQUIERDO: FORMULARIO ---
        form_frame = tk.Frame(self.root, bg=self.colors['card'], padx=20, pady=20)
        form_frame.pack(side=tk.LEFT, fill=tk.Y, padx=10, pady=10)
        
        tk.Label(form_frame, text="DATOS DEL CLIENTE", font=('Segoe UI', 12, 'bold'), 
                 bg=self.colors['card'], fg=self.colors['accent']).pack(pady=(0, 20))
        
        self.ent_nombre = self.crear_input(form_frame, "Nombre Completo / Razón Social:")
        self.ent_doc = self.crear_input(form_frame, "Número de Documento / CUIT:")
        
        tk.Label(form_frame, text="Tipo Documento:", bg=self.colors['card'], fg="#888", font=('Segoe UI', 9)).pack(anchor="w")
        self.cb_tipo_doc = ttk.Combobox(form_frame, values=["DNI", "CUIT", "CUIL", "PASAPORTE"], state="readonly")
        self.cb_tipo_doc.pack(fill=tk.X, pady=(0, 15))
        self.cb_tipo_doc.set("DNI")
        
        tk.Label(form_frame, text="Condición IVA:", bg=self.colors['card'], fg="#888", font=('Segoe UI', 9)).pack(anchor="w")
        self.cb_iva = ttk.Combobox(form_frame, values=["Consumidor Final", "Monotributista", "Responsable Inscripto", "Exento"], state="readonly")
        self.cb_iva.pack(fill=tk.X, pady=(0, 25))
        self.cb_iva.set("Consumidor Final")
        
        # Botones de acción
        btn_save = tk.Button(form_frame, text="💾 GUARDAR CLIENTE", bg=self.colors['success'], fg="black", 
                             font=('Segoe UI', 10, 'bold'), relief="flat", command=self.guardar_cliente, cursor="hand2")
        btn_save.pack(fill=tk.X, pady=5, ipady=8)
        
        btn_clear = tk.Button(form_frame, text="LIMPIAR", bg="#333", fg="white", 
                              relief="flat", command=self.limpiar_campos, cursor="hand2")
        btn_clear.pack(fill=tk.X, pady=5)

        # --- PANEL DERECHO: TABLA ---
        table_frame = tk.Frame(self.root, bg=self.colors['bg'], padx=10, pady=10)
        table_frame.pack(side=tk.RIGHT, fill=tk.BOTH, expand=True)
        
        # Cabecera de búsqueda
        search_header = tk.Frame(table_frame, bg=self.colors['bg'])
        search_header.pack(fill=tk.X, pady=(0, 10))
        
        tk.Label(search_header, text=f"Empresa ID: {self.empresa_id}", bg=self.colors['bg'], fg="#555", font=('Consolas', 9)).pack(side=tk.RIGHT)
        tk.Label(search_header, text="🔍 Buscar cliente:", bg=self.colors['bg'], fg="white", font=('Segoe UI', 10)).pack(side=tk.LEFT, padx=5)
        
        self.ent_buscar = tk.Entry(search_header, bg=self.colors['card'], fg="white", borderwidth=0, font=('Segoe UI', 11))
        self.ent_buscar.pack(side=tk.LEFT, fill=tk.X, expand=True, ipady=5, padx=10)
        self.ent_buscar.bind("<KeyRelease>", lambda e: self.cargar_datos(self.ent_buscar.get()))

        # Tabla con estilo personalizado
        style = ttk.Style()
        style.theme_use("clam")
        style.configure("Treeview", background=self.colors['card'], foreground="white", fieldbackground=self.colors['card'], borderwidth=0)
        style.map("Treeview", background=[('selected', self.colors['accent'])])

        columns = ("ID", "Nombre", "Documento", "Tipo", "IVA")
        self.tree = ttk.Treeview(table_frame, columns=columns, show="headings", height=15)
        
        for col in columns:
            self.tree.heading(col, text=col)
            self.tree.column(col, width=100, anchor="center")
        
        self.tree.column("Nombre", width=300, anchor="w")
        self.tree.pack(fill=tk.BOTH, expand=True)
        self.tree.bind("<<TreeviewSelect>>", self.seleccionar_cliente)

        # Footer de tabla
        footer_btn = tk.Frame(table_frame, bg=self.colors['bg'])
        footer_btn.pack(fill=tk.X, pady=10)
        
        tk.Button(footer_btn, text="🗑️ ELIMINAR SELECCIONADO", bg=self.colors['danger'], fg="white",
                  font=('Segoe UI', 9, 'bold'), relief="flat", command=self.eliminar_cliente, cursor="hand2").pack(side=tk.RIGHT)

    def crear_input(self, master, label):
        tk.Label(master, text=label, bg=self.colors['card'], fg="#888", font=('Segoe UI', 9)).pack(anchor="w")
        entry = tk.Entry(master, bg="#2d2d2d", fg="white", insertbackground="white", borderwidth=0, font=('Segoe UI', 11))
        entry.pack(fill=tk.X, pady=(0, 15), ipady=5)
        return entry

    def cargar_datos(self, filtro=""):
        for item in self.tree.get_children():
            self.tree.delete(item)
            
        try:
            conn = get_connection()
            with conn.cursor() as cursor:
                # FILTRO ESTRICTO POR EMPRESA_ID
                sql = "SELECT id, nombre, documento, tipo_documento, condicion_iva FROM clientes WHERE empresa_id = %s"
                params = [self.empresa_id]
                
                if filtro:
                    sql += " AND (nombre LIKE %s OR documento LIKE %s)"
                    params.extend([f'%{filtro}%', f'%{filtro}%'])
                
                cursor.execute(sql, params)
                for row in cursor.fetchall():
                    self.tree.insert("", tk.END, values=(row['id'], row['nombre'], row['documento'], row['tipo_documento'], row['condicion_iva']))
            conn.close()
        except Exception as e:
            print(f"Error al cargar clientes: {e}")

    def seleccionar_cliente(self, event):
        item = self.tree.selection()
        if item:
            valores = self.tree.item(item, "values")
            self.id_seleccionado = valores[0]
            self.ent_nombre.delete(0, tk.END); self.ent_nombre.insert(0, valores[1])
            self.ent_doc.delete(0, tk.END); self.ent_doc.insert(0, valores[2])
            self.cb_tipo_doc.set(valores[3])
            self.cb_iva.set(valores[4])

    def guardar_cliente(self):
        nombre = self.ent_nombre.get().strip()
        doc = self.ent_doc.get().strip()
        tipo = self.cb_tipo_doc.get()
        iva = self.cb_iva.get()

        if not nombre:
            messagebox.showwarning("Faltan datos", "El nombre del cliente es obligatorio.")
            return

        try:
            conn = get_connection()
            with conn.cursor() as cursor:
                if self.id_seleccionado:
                    # Actualizar existente
                    sql = "UPDATE clientes SET nombre=%s, documento=%s, tipo_documento=%s, condicion_iva=%s WHERE id=%s AND empresa_id=%s"
                    cursor.execute(sql, (nombre, doc, tipo, iva, self.id_seleccionado, self.empresa_id))
                else:
                    # Insertar nuevo con el empresa_id actual
                    sql = "INSERT INTO clientes (empresa_id, nombre, documento, tipo_documento, condicion_iva) VALUES (%s, %s, %s, %s, %s)"
                    cursor.execute(sql, (self.empresa_id, nombre, doc, tipo, iva))
            conn.commit()
            conn.close()
            messagebox.showinfo("Éxito", "Cliente guardado correctamente.")
            self.limpiar_campos()
            self.cargar_datos()
        except Exception as e:
            messagebox.showerror("Error DB", f"No se pudo guardar: {e}")

    def eliminar_cliente(self):
        if not self.id_seleccionado:
            messagebox.showwarning("Selección", "Por favor, elija un cliente de la lista.")
            return
        
        if messagebox.askyesno("Confirmar", "¿Eliminar este cliente definitivamente?"):
            try:
                conn = get_connection()
                with conn.cursor() as cursor:
                    # Seguridad: Solo eliminar si pertenece a la empresa actual
                    cursor.execute("DELETE FROM clientes WHERE id=%s AND empresa_id=%s", (self.id_seleccionado, self.empresa_id))
                conn.commit()
                conn.close()
                self.limpiar_campos()
                self.cargar_datos()
            except Exception as e:
                messagebox.showerror("Error", "No se puede eliminar: El cliente tiene ventas registradas.")

    def limpiar_campos(self):
        self.id_seleccionado = None
        self.ent_nombre.delete(0, tk.END)
        self.ent_doc.delete(0, tk.END)
        self.cb_tipo_doc.set("DNI")
        self.cb_iva.set("Consumidor Final")
        self.tree.selection_remove(self.tree.selection())

if __name__ == "__main__":
    root = tk.Tk()
    # Para probar con la empresa 1
    app = ClientesUI(root, "NEXUS", 1)
    root.mainloop()