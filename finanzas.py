import tkinter as tk
from tkinter import ttk, messagebox
from db import get_connection

# Nota: Importar la clase del nuevo archivo si decides separarlos
# from reportes import ReportesGUI 

class FinanzasGUI:
    def __init__(self, root, nombre_negocio="NEXUS", empresa_id=1, usuario_id=1):
        self.root = root
        self.empresa_id = int(empresa_id)
        self.usuario_id = int(usuario_id)
        
        self.root.title(f"{nombre_negocio} - RENTABILIDAD Y CAJA")
        self.root.geometry("1200x800")
        
        self.colors = {
            'bg': '#121212', 'card': '#1e1e1e', 'accent': '#00a8ff',
            'ingreso': '#00db84', 'gasto': '#ff4757', 'text': '#ffffff',
            'btn_reports': '#6c5ce7'
        }
        
        # Categorías Extendidas Mergeadas
        self.categorias_por_tipo = {
            "INGRESO": [
                "Ventas POS", "Aporte de Capital", "Devolución Proveedor", 
                "Préstamo Bancario", "Intereses Ganados", "Otros Ingresos"
            ],
            "GASTO": [
                "Mercadería (Compra)", "Sueldos y Jornales", "Leyes Sociales / Impuestos",
                "Arriendo de Local", "Servicios: Luz", "Servicios: Agua", 
                "Servicios: Internet/Teléfono", "Marketing y Publicidad",
                "Mantenimiento y Reparaciones", "Limpieza e Higiene",
                "Retiro de Socio", "Pago de Préstamos", "Otros Gastos"
            ]
        }
        
        self.root.configure(bg=self.colors['bg'])
        self.crear_widgets()
        self.actualizar_reporte()

    def crear_widgets(self):
        # --- HEADER ---
        header = tk.Frame(self.root, bg=self.colors['bg'], pady=20)
        header.pack(fill=tk.X, padx=20)
        
        tk.Label(header, text="📊 BALANCE Y RENTABILIDAD REAL", font=('Segoe UI', 16, 'bold'), 
                 bg=self.colors['bg'], fg="white").pack(side=tk.LEFT)
        
        # Botón para abrir Gráficos
        tk.Button(header, text="📈 VER REPORTES Y GRÁFICOS", bg=self.colors['btn_reports'], 
                  fg="white", font=('Segoe UI', 10, 'bold'), relief="flat", padx=15,
                  command=self.abrir_reportes, cursor="hand2").pack(side=tk.RIGHT)
        
        # --- PANEL DE RESUMEN ---
        self.resumen_frame = tk.Frame(self.root, bg=self.colors['bg'])
        self.resumen_frame.pack(fill=tk.X, padx=20, pady=10)
        
        self.card_ventas = self.crear_card(self.resumen_frame, "VENTAS TOTALES (BRUTO)", "$0.00", self.colors['text'])
        self.card_gastos = self.crear_card(self.resumen_frame, "TOTAL GASTOS", "$0.00", self.colors['gasto'])
        self.card_utilidad = self.crear_card(self.resumen_frame, "UTILIDAD (GANANCIA REAL)", "$0.00", self.colors['ingreso'])

        # --- CUERPO ---
        cuerpo = tk.Frame(self.root, bg=self.colors['bg'])
        cuerpo.pack(fill=tk.BOTH, expand=True, padx=20, pady=20)

        # Formulario
        form = tk.LabelFrame(cuerpo, text=" Registrar Movimiento de Caja ", bg=self.colors['card'], fg="white", 
                             padx=20, pady=20, font=('Segoe UI', 10, 'bold'), relief="flat")
        form.pack(side=tk.LEFT, fill=tk.Y, padx=(0, 20))

        tk.Label(form, text="Tipo de Movimiento:", bg=self.colors['card'], fg="#aaa").pack(anchor="w")
        self.cb_tipo = ttk.Combobox(form, values=list(self.categorias_por_tipo.keys()), state="readonly", font=('Segoe UI', 11))
        self.cb_tipo.pack(fill=tk.X, pady=(0, 15))
        self.cb_tipo.bind("<<ComboboxSelected>>", self.actualizar_categorias)

        tk.Label(form, text="Categoría:", bg=self.colors['card'], fg="#aaa").pack(anchor="w")
        self.cb_cat = ttk.Combobox(form, values=[], state="readonly", font=('Segoe UI', 11))
        self.cb_cat.pack(fill=tk.X, pady=(0, 15))

        tk.Label(form, text="Monto ($):", bg=self.colors['card'], fg="#aaa").pack(anchor="w")
        self.ent_monto = tk.Entry(form, font=('Segoe UI', 12), bg="#252525", fg="white", borderwidth=0)
        self.ent_monto.pack(fill=tk.X, ipady=8, pady=(0, 15))

        tk.Label(form, text="Descripción / Nota:", bg=self.colors['card'], fg="#aaa").pack(anchor="w")
        self.ent_desc = tk.Entry(form, font=('Segoe UI', 10), bg="#252525", fg="white", borderwidth=0)
        self.ent_desc.pack(fill=tk.X, ipady=8, pady=(0, 20))

        tk.Button(form, text="GUARDAR EN CAJA", bg=self.colors['accent'], fg="white", font=('Segoe UI', 10, 'bold'),
                  relief="flat", pady=10, command=self.registrar_movimiento, cursor="hand2").pack(fill=tk.X)

        # Tabla
        tabla_frame = tk.Frame(cuerpo, bg=self.colors['card'])
        tabla_frame.pack(side=tk.RIGHT, fill=tk.BOTH, expand=True)

        self.tabla = ttk.Treeview(tabla_frame, columns=("tipo", "cat", "monto", "desc", "hora"), show='headings')
        self.tabla.heading("tipo", text="TIPO"); self.tabla.column("tipo", width=90)
        self.tabla.heading("cat", text="CATEGORÍA"); self.tabla.column("cat", width=150)
        self.tabla.heading("monto", text="MONTO"); self.tabla.column("monto", width=100, anchor="e")
        self.tabla.heading("desc", text="DESCRIPCIÓN"); self.tabla.column("desc", width=200)
        self.tabla.heading("hora", text="HORA"); self.tabla.column("hora", width=80)
        self.tabla.pack(fill=tk.BOTH, expand=True)

    def crear_card(self, parent, titulo, valor, color):
        f = tk.Frame(parent, bg=self.colors['card'], padx=20, pady=15, highlightthickness=1, highlightbackground="#333")
        f.pack(side=tk.LEFT, expand=True, fill=tk.X, padx=5)
        tk.Label(f, text=titulo, bg=self.colors['card'], fg="#888", font=('Segoe UI', 8, 'bold')).pack()
        lbl_val = tk.Label(f, text=valor, bg=self.colors['card'], fg=color, font=('Segoe UI', 16, 'bold'))
        lbl_val.pack()
        return lbl_val

    def actualizar_categorias(self, event=None):
        tipo = self.cb_tipo.get()
        self.cb_cat['values'] = self.categorias_por_tipo.get(tipo, [])
        self.cb_cat.set("")

    def registrar_movimiento(self):
        tipo = self.cb_tipo.get()
        monto = self.ent_monto.get()
        cat = self.cb_cat.get()
        desc = self.ent_desc.get()
        if not monto or not tipo or not cat: 
            messagebox.showwarning("Atención", "Completa Tipo, Categoría y Monto")
            return
        try:
            conn = get_connection()
            with conn.cursor() as cursor:
                cursor.execute("""
                    INSERT INTO finanzas (empresa_id, tipo, categoria, monto, descripcion, usuario_id)
                    VALUES (%s, %s, %s, %s, %s, %s)
                """, (self.empresa_id, tipo, cat, float(monto), desc, self.usuario_id))
            conn.commit()
            conn.close()
            self.ent_monto.delete(0, tk.END); self.ent_desc.delete(0, tk.END)
            self.actualizar_reporte()
        except Exception as e:
            messagebox.showerror("Error", str(e))

    def actualizar_reporte(self):
        for i in self.tabla.get_children(): self.tabla.delete(i)
        try:
            conn = get_connection()
            with conn.cursor() as cursor:
                # 1. Movimientos Manuales (Excluyendo 'Ventas' automáticas para no duplicar en lista)
                cursor.execute("""
                    SELECT tipo, categoria, monto, descripcion, DATE_FORMAT(hora, '%%H:%%i') as hora_f 
                    FROM finanzas 
                    WHERE empresa_id = %s AND fecha = CURRENT_DATE AND categoria != 'Ventas'
                    ORDER BY hora DESC
                """, (self.empresa_id,))
                movs = cursor.fetchall()
                total_ingresos_extra, total_gastos = 0, 0
                for m in movs:
                    monto = float(m['monto'])
                    if m['tipo'] == 'INGRESO': total_ingresos_extra += monto
                    else: total_gastos += monto
                    self.tabla.insert("", tk.END, values=(m['tipo'], m['categoria'], f"${monto:.2f}", m['descripcion'], m['hora_f']))

                # 2. Datos consolidados de Ventas
                cursor.execute("""
                    SELECT SUM(total) as ventas_brutas, SUM(ganancia) as utilidad_ventas
                    FROM ventas WHERE empresa_id = %s AND DATE(fecha) = CURRENT_DATE
                """, (self.empresa_id,))
                res_ventas = cursor.fetchone()
                ventas_bruto = float(res_ventas['ventas_brutas'] or 0)
                utilidad_de_ventas = float(res_ventas['utilidad_ventas'] or 0)

                self.card_ventas.config(text=f"${ventas_bruto:.2f}")
                self.card_gastos.config(text=f"${total_gastos:.2f}")
                self.card_utilidad.config(text=f"${(utilidad_de_ventas + total_ingresos_extra - total_gastos):.2f}")
            conn.close()
        except Exception as e: print(f"Error reporte: {e}")

    def abrir_reportes(self):
        # Aquí puedes llamar a la clase ReportesGUI del otro módulo
        # from reportes import ReportesGUI
        # ReportesGUI(self.root, self.empresa_id)
        messagebox.showinfo("Reportes", "Módulo de Reportes Históricos (Matplotlib) inicializado.")

if __name__ == "__main__":
    root = tk.Tk()
    app = FinanzasGUI(root, "BOTILLERIA TEST", 1, 1)
    root.mainloop()