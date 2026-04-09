import tkinter as tk
from tkinter import ttk, messagebox
import qrcode
from PIL import Image, ImageTk
import os
import sys
from datetime import datetime
from decimal import Decimal

from db import get_connection
from productos import obtener_productos 

class PromocionesGUI:
    def __init__(self, root, nombre_negocio="NEXUS", empresa_id=1, usuario_id=1):
        self.root = root
        self.empresa_id = int(empresa_id) # <--- Inyectado para multiempresa
        self.usuario_id = int(usuario_id)
        self.root.title(f"{nombre_negocio.upper()} - Centro de Promociones")
        self.root.geometry("1400x850")
        
        self.colors = {
            'bg_main': '#121212', 'bg_panel': '#1e1e1e', 'accent': '#f1c40f',
            'success': '#00db84', 'danger': '#ff4757', 'text_main': '#ffffff',
            'text_dim': '#a0a0a0', 'border': '#333333', 'volumen': '#3498db'
        }

        self.productos_combo = [] 
        self.prod_vol_id = None 
        self.qr_cache = None
        self.imagenes_tabla = [] # Para que las fotos no desaparezcan
        
        self.vcmd = (self.root.register(self.solo_numeros), '%P')
        
        self.configurar_estilo()
        self.crear_widgets(nombre_negocio)
        self.cargar_promociones()

    def solo_numeros(self, P):
        if P == "" or P.isdigit(): return True
        try:
            float(P)
            return True
        except: return False

    def configurar_estilo(self):
        style = ttk.Style()
        style.theme_use('clam')
        self.root.configure(bg=self.colors['bg_main'])
        style.configure('Custom.Treeview', background=self.colors['bg_panel'], 
                        foreground=self.colors['text_main'], fieldbackground=self.colors['bg_panel'], 
                        borderwidth=0, font=('Segoe UI', 10), rowheight=35)
        style.configure('Custom.Treeview.Heading', font=('Segoe UI', 10, 'bold'), 
                        background="#252525", foreground="white", relief="flat")

    def obtener_imagen_desde_ruta(self, ruta):
        if not ruta or not os.path.exists(ruta):
            return None
        try:
            img = Image.open(ruta).convert("RGBA")
            img = img.resize((24, 24), Image.Resampling.LANCZOS)
            photo = ImageTk.PhotoImage(img)
            self.imagenes_tabla.append(photo) 
            return photo
        except:
            return None

    def crear_widgets(self, nombre_negocio):
        main_container = tk.Frame(self.root, bg=self.colors['bg_main'], padx=20, pady=20)
        main_container.pack(fill=tk.BOTH, expand=True)

        header = tk.Frame(main_container, bg=self.colors['bg_main'])
        header.pack(fill=tk.X, pady=(0, 20))
        tk.Label(header, text=f"🎟️ PANEL DE OFERTAS | {nombre_negocio.upper()}", 
                 font=('Segoe UI', 18, 'bold'), bg=self.colors['bg_main'], fg=self.colors['accent']).pack(side=tk.LEFT)

        body = tk.Frame(main_container, bg=self.colors['bg_main'])
        body.pack(fill=tk.BOTH, expand=True)

        # TABLA
        self.tabla = ttk.Treeview(body, columns=("ID", "Tipo", "Detalle", "Desc", "Estado"), show='tree headings', style="Custom.Treeview")
        self.tabla.heading("#0", text="FOTO")
        self.tabla.column("#0", width=50, anchor="center")
        self.tabla.heading("ID", text="ID"); self.tabla.column("ID", width=40)
        self.tabla.heading("Tipo", text="TIPO"); self.tabla.column("Tipo", width=100)
        self.tabla.heading("Detalle", text="DETALLE REGLA"); self.tabla.column("Detalle", width=350)
        self.tabla.heading("Desc", text="DESC %"); self.tabla.column("Desc", width=80)
        self.tabla.heading("Estado", text="ESTADO"); self.tabla.column("Estado", width=100)
        self.tabla.pack(side=tk.LEFT, fill=tk.BOTH, expand=True, padx=(0, 10))

        # PANEL DERECHO
        right_panel = tk.Frame(body, bg=self.colors['bg_panel'], width=450, highlightthickness=1, highlightbackground=self.colors['border'])
        right_panel.pack(side=tk.RIGHT, fill=tk.Y)
        right_panel.pack_propagate(False)

        tabs = ttk.Notebook(right_panel)
        self.tab_qr = tk.Frame(tabs, bg=self.colors['bg_panel'], padx=15)
        self.tab_combo = tk.Frame(tabs, bg=self.colors['bg_panel'], padx=15)
        self.tab_vol = tk.Frame(tabs, bg=self.colors['bg_panel'], padx=15)
        tabs.add(self.tab_qr, text=" CUPÓN QR "); tabs.add(self.tab_combo, text=" COMBOS "); tabs.add(self.tab_vol, text=" MAYORISTA ")
        tabs.pack(fill=tk.BOTH, expand=True)

        self.setup_tab_qr()
        self.setup_tab_combo()
        self.setup_tab_volumen()

    def crear_input(self, master, label, validar_num=False):
        tk.Label(master, text=label, font=('Segoe UI', 8, 'bold'), bg=self.colors['bg_panel'], fg=self.colors['text_dim']).pack(anchor="w", pady=(8, 2))
        e = tk.Entry(master, font=('Segoe UI', 10), bg="#252525", fg="white", borderwidth=0, validate='key' if validar_num else 'none', validatecommand=self.vcmd if validar_num else None)
        e.pack(fill=tk.X, ipady=5); return e

    def actualizar_sugerencias(self, entry, listbox):
        q = entry.get().lower()
        if "OK: " in entry.get(): return 
        listbox.delete(0, tk.END)
        if len(q) < 2: return
        # Filtramos productos por la empresa actual
        for p in obtener_productos(self.empresa_id):
            if q in p['nombre'].lower() or q in str(p['codigo']).lower():
                listbox.insert(tk.END, f"{p['id']} | {p['nombre']}")

    def setup_tab_qr(self):
        tk.Label(self.tab_qr, text="NUEVO CUPÓN", font=('Segoe UI', 11, 'bold'), bg=self.colors['bg_panel'], fg=self.colors['success']).pack(pady=15)
        self.ent_codigo = self.crear_input(self.tab_qr, "CÓDIGO")
        self.ent_porcentaje_qr = self.crear_input(self.tab_qr, "DESCUENTO (%)", True)
        tk.Button(self.tab_qr, text="GUARDAR Y GENERAR QR", bg=self.colors['success'], command=self.guardar_cupon, font=('Segoe UI', 10, 'bold')).pack(fill=tk.X, pady=15)
        self.qr_label = tk.Label(self.tab_qr, bg="#252525", width=180, height=180); self.qr_label.pack()

    def setup_tab_combo(self):
        tk.Label(self.tab_combo, text="COMBO DE PRODUCTOS", font=('Segoe UI', 11, 'bold'), bg=self.colors['bg_panel'], fg=self.colors['accent']).pack(pady=15)
        self.ent_nombre_combo = self.crear_input(self.tab_combo, "NOMBRE COMBO")
        tk.Label(self.tab_combo, text="BUSCAR PRODUCTO", font=('Segoe UI', 8), bg=self.colors['bg_panel'], fg=self.colors['text_dim']).pack(anchor="w")
        self.search_combo = tk.Entry(self.tab_combo, bg="#252525", fg="white", borderwidth=0); self.search_combo.pack(fill=tk.X, ipady=5); self.search_combo.bind("<KeyRelease>", lambda e: self.actualizar_sugerencias(self.search_combo, self.list_sug_combo))
        self.list_sug_combo = tk.Listbox(self.tab_combo, bg="#252525", fg="white", height=4); self.list_sug_combo.pack(fill=tk.X); self.list_sug_combo.bind("<Double-Button-1>", self.seleccionar_para_combo)
        self.list_actual_combo = tk.Listbox(self.tab_combo, bg="#1a1a1a", fg=self.colors['success'], height=4); self.list_actual_combo.pack(fill=tk.X, pady=5)
        self.ent_porcentaje_combo = self.crear_input(self.tab_combo, "DESCUENTO (%)", True)
        tk.Button(self.tab_combo, text="CREAR COMBO", bg=self.colors['accent'], command=self.guardar_combo, font=('Segoe UI', 10, 'bold')).pack(fill=tk.X, pady=15)

    def setup_tab_volumen(self):
        tk.Label(self.tab_vol, text="PRECIO POR CANTIDAD", font=('Segoe UI', 11, 'bold'), bg=self.colors['bg_panel'], fg=self.colors['volumen']).pack(pady=15)
        tk.Label(self.tab_vol, text="BUSCAR PRODUCTO", font=('Segoe UI', 8), bg=self.colors['bg_panel'], fg=self.colors['text_dim']).pack(anchor="w")
        self.search_vol = tk.Entry(self.tab_vol, bg="#252525", fg="white", borderwidth=0); self.search_vol.pack(fill=tk.X, ipady=5); self.search_vol.bind("<KeyRelease>", lambda e: self.actualizar_sugerencias(self.search_vol, self.list_sug_vol))
        self.list_sug_vol = tk.Listbox(self.tab_vol, bg="#252525", fg="white", height=4); self.list_sug_vol.pack(fill=tk.X); self.list_sug_vol.bind("<Double-Button-1>", self.seleccionar_para_volumen)
        self.ent_cant_min = self.crear_input(self.tab_vol, "CANTIDAD MÍNIMA", True)
        self.ent_porcentaje_vol = self.crear_input(self.tab_vol, "DESCUENTO (%)", True)
        tk.Button(self.tab_vol, text="CREAR REGLA MAYORISTA", bg=self.colors['volumen'], fg="white", command=self.guardar_volumen, font=('Segoe UI', 10, 'bold')).pack(fill=tk.X, pady=20)

    def seleccionar_para_combo(self, event=None):
        idx = self.list_sug_combo.curselection()
        if idx:
            sel = self.list_sug_combo.get(idx); pid = sel.split(" | ")[0]
            if pid not in self.productos_combo:
                self.productos_combo.append(pid); self.list_actual_combo.insert(tk.END, sel)
                self.search_combo.delete(0, tk.END); self.list_sug_combo.delete(0, tk.END); self.search_combo.focus()

    def seleccionar_para_volumen(self, event=None):
        idx = self.list_sug_vol.curselection()
        if idx:
            sel = self.list_sug_vol.get(idx); self.prod_vol_id = sel.split(" | ")[0]; nombre = sel.split(" | ")[1]
            self.search_vol.delete(0, tk.END); self.search_vol.insert(0, f"OK: {nombre}")
            self.search_vol.config(fg=self.colors['success']); self.list_sug_vol.delete(0, tk.END)

    def guardar_volumen(self):
        c, p = self.ent_cant_min.get().strip(), self.ent_porcentaje_vol.get().strip()
        if not self.prod_vol_id or not c or not p: return
        try:
            conn = get_connection()
            with conn.cursor() as cursor:
                cursor.execute("""
                    INSERT INTO promociones_volumen (producto_id, empresa_id, cantidad_minima, descuento_porcentaje, activo) 
                    VALUES (%s, %s, %s, %s, 1)
                """, (int(self.prod_vol_id), self.empresa_id, int(c), float(p)))
            conn.commit(); conn.close(); messagebox.showinfo("Éxito", "Regla guardada."); self.cargar_promociones()
        except Exception as e: messagebox.showerror("Error", str(e))

    def guardar_cupon(self):
        c, p = self.ent_codigo.get().upper(), self.ent_porcentaje_qr.get()
        if not c or not p: return
        try:
            conn = get_connection()
            with conn.cursor() as cursor:
                cursor.execute("""
                    INSERT INTO cupones (codigo_qr, empresa_id, descuento_porcentaje, activo) 
                    VALUES (%s, %s, %s, 1)
                """, (c, self.empresa_id, float(p)))
            conn.commit(); conn.close(); self.generar_qr_visual(c); self.cargar_promociones()
        except Exception as e: messagebox.showerror("Error", str(e))

    def guardar_combo(self):
        n, p = self.ent_nombre_combo.get(), self.ent_porcentaje_combo.get()
        if not n or not p or len(self.productos_combo) < 2: return
        try:
            conn = get_connection()
            with conn.cursor() as cursor:
                cursor.execute("""
                    INSERT INTO promociones_combos (nombre_promo, empresa_id, productos_ids, descuento_porcentaje, activo) 
                    VALUES (%s, %s, %s, %s, 1)
                """, (n, self.empresa_id, ",".join(self.productos_combo), float(p)))
            conn.commit(); conn.close(); self.productos_combo = []; self.list_actual_combo.delete(0, tk.END); self.cargar_promociones()
        except Exception as e: messagebox.showerror("Error", str(e))

    def generar_qr_visual(self, texto):
        qr = qrcode.QRCode(box_size=5); qr.add_data(texto); qr.make()
        img = ImageTk.PhotoImage(qr.make_image().resize((180, 180)))
        self.qr_cache = img; self.qr_label.config(image=img)

    def cargar_promociones(self):
        for item in self.tabla.get_children(): self.tabla.delete(item)
        self.imagenes_tabla = [] 
        conn = get_connection()
        try:
            with conn.cursor() as cursor:
                # Cupones de esta empresa
                cursor.execute("SELECT id, codigo_qr, descuento_porcentaje FROM cupones WHERE activo=1 AND empresa_id=%s", (self.empresa_id,))
                for r in cursor.fetchall(): 
                    self.tabla.insert("", tk.END, text="", values=(r['id'], "CUPÓN", r['codigo_qr'], f"{r['descuento_porcentaje']}%", "ACTIVO"))
                
                # Combos de esta empresa
                cursor.execute("SELECT id, nombre_promo, descuento_porcentaje FROM promociones_combos WHERE activo=1 AND empresa_id=%s", (self.empresa_id,))
                for r in cursor.fetchall(): 
                    self.tabla.insert("", tk.END, text="", values=(r['id'], "COMBO", r['nombre_promo'], f"{r['descuento_porcentaje']}%", "ACTIVO"))
                
                # Mayorista de esta empresa
                cursor.execute("""
                    SELECT v.id, p.nombre, p.imagen, v.cantidad_minima, v.descuento_porcentaje 
                    FROM promociones_volumen v 
                    JOIN productos p ON v.producto_id = p.id
                    WHERE v.activo = 1 AND v.empresa_id = %s
                """, (self.empresa_id,))
                for r in cursor.fetchall():
                    foto = self.obtener_imagen_desde_ruta(r['imagen'])
                    self.tabla.insert("", tk.END, image=foto if foto else "", values=(r['id'], "MAYORISTA", f"{r['nombre']} (x{r['cantidad_minima']})", f"{r['descuento_porcentaje']}%", "ACTIVO"))
        except Exception as e:
            print(f"Error cargando tabla: {e}")
        finally: conn.close()

if __name__ == "__main__":
    # Soporta el paso de argumentos desde el main.py
    negocio = sys.argv[1] if len(sys.argv) > 1 else "NEXUS"
    emp_id = int(sys.argv[2]) if len(sys.argv) > 2 else 1
    usu_id = int(sys.argv[3]) if len(sys.argv) > 3 else 1
    
    root = tk.Tk()
    app = PromocionesGUI(root, negocio, emp_id, usu_id)
    root.mainloop()