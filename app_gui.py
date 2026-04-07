import tkinter as tk
from tkinter import ttk, messagebox
from decimal import Decimal
from PIL import Image, ImageTk
import os
import sys

# Importaciones de lógica
from productos import obtener_productos, buscar_producto_por_codigo, buscar_productos_por_nombre
from ventas import crear_venta, agregar_item, cerrar_venta, registrar_pago
from ticket import generar_ticket, guardar_ticket

def beep():
    print("\a", end="", flush=True)

class POSApp:
    def __init__(self, root, nombre_negocio="NEXUS", usuario_id=1):
        self.root = root
        self.usuario_id = usuario_id  # ID del usuario que hizo login
        self.root.title(f"{nombre_negocio.upper()} - Terminal de Ventas")
        self.root.geometry("1450x900")
        
        # Paleta de Colores
        self.colors = {
            'bg_main': '#121212',      
            'bg_panel': '#1e1e1e',     
            'accent': '#00a8ff',       
            'success': '#00db84',      
            'danger': '#ff4757',       
            'text_main': '#ffffff',    
            'text_dim': '#a0a0a0',     
            'border': '#333333'        
        }

        self.imagenes_cache = {}
        self.venta_id = None
        self.total = Decimal('0')
        self.items = {}
        self.orden = []
        self.vuelto = Decimal('0')
        
        # --- INICIALIZACIÓN ---
        self.setup_styles()
        self.create_widgets(nombre_negocio)
        self.nueva_venta()                  
        self.cargar_productos()             
        self.setup_keyboard_bindings()
        self.input_codigo.focus()
        
    def setup_styles(self):
        style = ttk.Style()
        style.theme_use('clam')
        self.root.configure(bg=self.colors['bg_main'])
        
        style.configure("Custom.Treeview",
                       background=self.colors['bg_panel'],
                       foreground=self.colors['text_main'],
                       fieldbackground=self.colors['bg_panel'],
                       borderwidth=0,
                       font=('Segoe UI', 10),
                       rowheight=45)
        
        style.configure("Custom.Treeview.Heading", 
                       font=('Segoe UI', 10, 'bold'),
                       background="#252525",
                       foreground="white",
                       relief="flat")

    def create_widgets(self, nombre_negocio):
        main_container = tk.Frame(self.root, bg=self.colors['bg_main'], padx=20, pady=20)
        main_container.pack(fill=tk.BOTH, expand=True)

        # --- HEADER ---
        header = tk.Frame(main_container, bg=self.colors['bg_main'])
        header.pack(fill=tk.X, pady=(0, 20))
        
        tk.Label(header, text=nombre_negocio.upper(), font=('Segoe UI', 22, 'bold'), 
                 bg=self.colors['bg_main'], fg=self.colors['accent']).pack(side=tk.LEFT)
        tk.Label(header, text="POINT OF SALE", font=('Segoe UI', 14), 
                 bg=self.colors['bg_main'], fg=self.colors['text_dim']).pack(side=tk.LEFT, padx=10, pady=(5,0))

        # --- INDICADORES ---
        indicadores = tk.Frame(main_container, bg=self.colors['bg_main'])
        indicadores.pack(fill=tk.X, pady=(0, 20))

        card_total = tk.Frame(indicadores, bg=self.colors['bg_panel'], highlightbackground=self.colors['border'], highlightthickness=1)
        card_total.pack(side=tk.LEFT, fill=tk.BOTH, expand=True, padx=(0, 10))
        tk.Label(card_total, text="TOTAL A COBRAR", font=('Segoe UI', 11, 'bold'), bg=self.colors['bg_panel'], fg=self.colors['text_dim']).pack(pady=(15, 0))
        self.total_label = tk.Label(card_total, text="$ 0.00", font=('Segoe UI', 48, 'bold'), bg=self.colors['bg_panel'], fg=self.colors['success'])
        self.total_label.pack(pady=(0, 15))

        card_vuelto = tk.Frame(indicadores, bg=self.colors['bg_panel'], highlightbackground=self.colors['border'], highlightthickness=1)
        card_vuelto.pack(side=tk.LEFT, fill=tk.BOTH, expand=True, padx=(10, 0))
        tk.Label(card_vuelto, text="VUELTO / CAMBIO", font=('Segoe UI', 11, 'bold'), bg=self.colors['bg_panel'], fg=self.colors['text_dim']).pack(pady=(15, 0))
        self.vuelto_label = tk.Label(card_vuelto, text="", font=('Segoe UI', 48, 'bold'), bg=self.colors['bg_panel'], fg='#ffc107')
        self.vuelto_label.pack(pady=(0, 15))

        # --- TABLAS ---
        content = tk.Frame(main_container, bg=self.colors['bg_main'])
        content.pack(fill=tk.BOTH, expand=True)

        cat_frame = tk.Frame(content, bg=self.colors['bg_panel'], highlightbackground=self.colors['border'], highlightthickness=1)
        cat_frame.pack(side=tk.LEFT, fill=tk.BOTH, expand=True, padx=(0, 10))
        self.tabla = ttk.Treeview(cat_frame, columns=("SKU", "Nombre", "Precio", "Stock"), show="tree headings", style="Custom.Treeview")
        self.tabla.heading("#0", text="IMG"); self.tabla.column("#0", width=60, anchor="center")
        self.tabla.heading("SKU", text="SKU"); self.tabla.column("SKU", width=100, anchor="center")
        self.tabla.heading("Nombre", text="PRODUCTO"); self.tabla.column("Nombre", width=250)
        self.tabla.heading("Precio", text="PRECIO"); self.tabla.column("Precio", width=100, anchor="e")
        self.tabla.heading("Stock", text="STOCK"); self.tabla.column("Stock", width=80, anchor="center")
        self.tabla.pack(fill=tk.BOTH, expand=True, padx=2, pady=2)

        car_frame = tk.Frame(content, bg=self.colors['bg_panel'], highlightbackground=self.colors['border'], highlightthickness=1)
        car_frame.pack(side=tk.LEFT, fill=tk.BOTH, expand=True, padx=(10, 0))
        self.carrito = ttk.Treeview(car_frame, columns=("Prod", "Cant", "Sub"), show="tree headings", style="Custom.Treeview")
        self.carrito.heading("#0", text="IMG"); self.carrito.column("#0", width=60, anchor="center")
        self.carrito.heading("Prod", text="PRODUCTO"); self.carrito.column("Prod", width=220)
        self.carrito.heading("Cant", text="CANT."); self.carrito.column("Cant", width=70, anchor="center")
        self.carrito.heading("Sub", text="SUBTOTAL"); self.carrito.column("Sub", width=110, anchor="e")
        self.carrito.pack(fill=tk.BOTH, expand=True, padx=2, pady=2)

        # --- FOOTER ---
        footer = tk.Frame(main_container, bg=self.colors['bg_main'], pady=20)
        footer.pack(fill=tk.X)
        search_container = tk.Frame(footer, bg=self.colors['bg_main'])
        search_container.pack(side=tk.LEFT, fill=tk.X, expand=True)
        self.input_codigo = tk.Entry(search_container, font=('Segoe UI', 18), bg="#252525", fg="white", insertbackground="white", borderwidth=0, highlightthickness=1, highlightbackground=self.colors['border'])
        self.input_codigo.pack(fill=tk.X, pady=(5, 0), ipady=8)
        self.input_codigo.bind('<Return>', self.on_input_submitted)

        btn_container = tk.Frame(footer, bg=self.colors['bg_main'])
        btn_container.pack(side=tk.RIGHT, padx=(20, 0))
        self.crear_boton(btn_container, "COBRAR (F2)", self.colors['success'], self.key_f2)
        self.crear_boton(btn_container, "ELIMINAR (F3)", self.colors['danger'], self.borrar_item)
        self.crear_boton(btn_container, "SALIR (ESC)", "#444", self.confirm_exit)

    def crear_boton(self, master, texto, color, comando):
        btn = tk.Button(master, text=texto, bg=color, fg="white", font=('Segoe UI', 10, 'bold'), relief="flat", padx=25, pady=12, command=comando)
        btn.pack(side=tk.LEFT, padx=5)

    def obtener_icono(self, ruta):
        if not ruta or not os.path.exists(ruta): return None
        if ruta in self.imagenes_cache: return self.imagenes_cache[ruta]
        try:
            img = Image.open(ruta).resize((32, 32), Image.Resampling.LANCZOS)
            photo = ImageTk.PhotoImage(img)
            self.imagenes_cache[ruta] = photo
            return photo
        except: return None

    def cargar_productos(self):
        for item in self.tabla.get_children(): self.tabla.delete(item)
        productos = obtener_productos()
        for p in productos:
            icono = self.obtener_icono(p.get("imagen"))
            self.tabla.insert("", tk.END, image=icono if icono else "", values=(p["codigo"], p["nombre"], f"$ {float(p['precio']):.2f}", p["stock"]))

    def on_input_submitted(self, event):
        texto = self.input_codigo.get().strip()
        if texto: self.agregar_producto(texto)

    def agregar_producto(self, texto):
        producto = buscar_producto_por_codigo(texto)
        if not producto:
            res = buscar_productos_por_nombre(texto)
            if not res: beep(); self.input_codigo.delete(0, tk.END); return
            producto = res[0]
        if producto["stock"] <= 0:
            messagebox.showwarning("Stock Agotado", "Sin existencias."); self.input_codigo.delete(0, tk.END); return
        sku, precio = producto["codigo"], Decimal(str(producto["precio"]))
        if sku in self.items:
            self.items[sku]["cantidad"] += 1
            self.items[sku]["subtotal"] += precio
        else:
            self.items[sku] = {"id": producto["id"], "nombre": producto["nombre"], "cantidad": 1, "precio": precio, "subtotal": precio, "imagen": producto.get("imagen")}
        self.orden.append(sku); self.total += precio; self.actualizar_carrito(); beep(); self.input_codigo.delete(0, tk.END)

    def actualizar_carrito(self):
        for item in self.carrito.get_children(): self.carrito.delete(item)
        for i in self.items.values():
            icono = self.obtener_icono(i.get("imagen"))
            self.carrito.insert("", tk.END, image=icono if icono else "", values=(i['nombre'], i['cantidad'], f"$ {float(i['subtotal']):.2f}"))
        self.total_label.config(text=f"$ {float(self.total):.2f}")

    def borrar_item(self):
        if not self.orden: return
        sku = self.orden.pop()
        item = self.items[sku]
        item["cantidad"] -= 1; item["subtotal"] -= item["precio"]; self.total -= item["precio"]
        if item["cantidad"] <= 0: del self.items[sku]
        self.actualizar_carrito(); beep()

    def setup_keyboard_bindings(self):
        self.root.bind('<F2>', lambda e: self.key_f2())
        self.root.bind('<F3>', lambda e: self.borrar_item())
        self.root.bind('<Escape>', lambda e: self.confirm_exit())

    def key_f2(self):
        if not self.items: return
        self.mostrar_dialogo_metodo_pago()

    def mostrar_dialogo_metodo_pago(self):
        dialog = tk.Toplevel(self.root)
        dialog.title("Finalizar Venta"); dialog.geometry("450x550"); dialog.configure(bg=self.colors['bg_panel'])
        dialog.transient(self.root); dialog.grab_set()
        tk.Label(dialog, text="MÉTODO DE PAGO", font=('Segoe UI', 14, 'bold'), bg=self.colors['bg_panel'], fg="white").pack(pady=30)
        
        def pay(metodo):
            if metodo == "EFECTIVO":
                dialog.destroy()
                self.mostrar_dialogo_efectivo()
            elif metodo == "TRANSFERENCIA":
                self.mostrar_dialogo_transferencia(dialog)
            else:
                dialog.destroy()
                self.procesar_pago(metodo)

        colores_btn = {
            "EFECTIVO": self.colors['success'], 
            "TARJETA": self.colors['accent'], 
            "TRANSFERENCIA": "#0097e6",
            "QR": "#9c27b0"
        }
        for m, c in colores_btn.items():
            tk.Button(dialog, text=f"PAGAR CON {m}", command=lambda met=m: pay(met), bg=c, fg="white", font=('Segoe UI', 11, 'bold'), relief="flat", pady=12).pack(fill=tk.X, padx=50, pady=8)

    def mostrar_dialogo_transferencia(self, parent_dialog):
        trans_win = tk.Toplevel(parent_dialog)
        trans_win.title("Datos de Transferencia")
        trans_win.geometry("400x300")
        trans_win.configure(bg=self.colors['bg_panel'])
        trans_win.transient(parent_dialog)
        trans_win.grab_set()

        tk.Label(trans_win, text="N° DE OPERACIÓN / REFERENCIA", font=('Segoe UI', 10, 'bold'), bg=self.colors['bg_panel'], fg="white").pack(pady=(30, 10))
        entry_ref = tk.Entry(trans_win, font=('Segoe UI', 16), justify='center', bg="#252525", fg="white", insertbackground="white")
        entry_ref.pack(pady=10, padx=50, fill=tk.X)
        entry_ref.focus()

        def confirmar():
            ref = entry_ref.get().strip()
            if not ref:
                messagebox.showwarning("Atención", "Ingrese el número de referencia.")
                return
            trans_win.destroy()
            parent_dialog.destroy()
            self.procesar_pago("TRANSFERENCIA", referencia=ref)

        tk.Button(trans_win, text="CONFIRMAR PAGO", command=confirmar, bg=self.colors['success'], fg="white", font=('Segoe UI', 11, 'bold'), relief="flat", pady=12).pack(pady=30, fill=tk.X, padx=50)
        entry_ref.bind('<Return>', lambda e: confirmar())

    def mostrar_dialogo_efectivo(self):
        dialog = tk.Toplevel(self.root); dialog.geometry("400x350"); dialog.configure(bg=self.colors['bg_panel'])
        tk.Label(dialog, text="MONTO RECIBIDO", font=('Segoe UI', 12, 'bold'), bg=self.colors['bg_panel'], fg=self.colors['text_dim']).pack(pady=(30, 10))
        entry = tk.Entry(dialog, font=('Segoe UI', 24), justify='center', bg="#252525", fg=self.colors['success'], borderwidth=0)
        entry.pack(pady=10, padx=50, fill=tk.X); entry.focus()
        
        def confirmar():
            try:
                recibido = Decimal(entry.get().replace(',', '.'))
                if recibido < self.total: 
                    messagebox.showwarning("Monto Insuficiente", "El monto recibido es menor al total.")
                    return
                self.vuelto = recibido - self.total; dialog.destroy(); self.procesar_pago("EFECTIVO")
            except: pass
            
        entry.bind('<Return>', lambda e: confirmar())
        tk.Button(dialog, text="CONFIRMAR PAGO", command=confirmar, bg=self.colors['success'], fg="white", font=('Segoe UI', 12, 'bold'), relief="flat", pady=15).pack(pady=30, fill=tk.X, padx=50)

    def procesar_pago(self, metodo, referencia=None):
        try:
            # 1. Grabar items
            for item in self.items.values(): 
                agregar_item(self.venta_id, item, item["cantidad"])
            
            # 2. Cerrar venta (Calculo de ganancias/stock)
            cerrar_venta(self.venta_id, float(self.total), self.items.values())
            
            # 3. Registrar Pago
            metodo_final = f"{metodo} (Ref: {referencia})" if referencia else metodo
            registrar_pago(
                self.venta_id, 
                float(self.total), 
                metodo_final, 
                entregado=float(self.total + self.vuelto), 
                vuelto=float(self.vuelto)
            )
            
            self.vuelto_label.config(text=f"$ {float(self.vuelto):.2f}")
            messagebox.showinfo("Venta", f"Venta Completada con {metodo_final}")
            
            self.nueva_venta()
            self.actualizar_carrito()
            self.cargar_productos()
        except Exception as e: 
            messagebox.showerror("Error", str(e))

    def nueva_venta(self):
        try:
            self.venta_id = crear_venta(self.usuario_id) # Se asocia al ID de usuario
            self.total = Decimal('0')
            self.items = {}
            self.orden = []
            self.vuelto = Decimal('0')
            if hasattr(self, 'vuelto_label'): self.vuelto_label.config(text="")
            if hasattr(self, 'total_label'): self.total_label.config(text="$ 0.00")
        except Exception as e:
            print(f"Error al crear venta: {e}")

    def confirm_exit(self):
        if messagebox.askyesno("Salir", "¿Desea cerrar Ventas?"): self.root.destroy()

# --- SEGURIDAD Y EJECUCIÓN ---
if __name__ == "__main__":
    # Verificamos argumentos: Nombre de Negocio [1] e ID de Usuario [2]
    if len(sys.argv) < 2:
        root_error = tk.Tk(); root_error.withdraw()
        messagebox.showerror("Acceso Denegado", "Inicie sesión desde el Dashboard.")
        sys.exit()
    
    negocio = sys.argv[1]
    u_id = sys.argv[2] if len(sys.argv) > 2 else 1 # Fallback a 1 si no hay ID
    
    root = tk.Tk()
    app = POSApp(root, negocio, u_id)
    root.mainloop()