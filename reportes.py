import tkinter as tk
from tkinter import ttk
import matplotlib.pyplot as plt
from matplotlib.backends.backend_tkagg import FigureCanvasTkAgg
import pandas as pd
import sys
import warnings
from datetime import datetime, timedelta
from db import get_connection

warnings.filterwarnings("ignore", category=UserWarning)

class ReportesGUI:
    def __init__(self, root, nombre_negocio="NEXUS", empresa_id=1):
        self.root = root
        
        try:
            self.empresa_id = int(str(empresa_id).strip())
            if self.empresa_id == 0:
                self.empresa_id = 1
        except:
            self.empresa_id = 1

        print(f"DEBUG REPORTES → Empresa ID: {self.empresa_id}")

        self.filtro_actual = "MES"
        self.root.title(f"{nombre_negocio} - REPORTES PRO")
        self.root.geometry("1300x850")
        self.root.configure(bg='#121212')

        self.colors = {
            'bg': '#121212',
            'card': '#1e1e1e',
            'ingreso': '#00db84',
            'gasto': '#ff4757',
            'texto': '#ffffff',
            'accent': '#00a8ff'
        }

        self.crear_widgets()
        self.cargar_graficos()

    def crear_widgets(self):
        header = tk.Frame(self.root, bg=self.colors['bg'], pady=10)
        header.pack(fill=tk.X, padx=20)

        tk.Label(header, text="📊 DASHBOARD FINANCIERO",
                 font=('Segoe UI', 18, 'bold'),
                 bg=self.colors['bg'], fg="white").pack(side=tk.LEFT)

        filtros = tk.Frame(header, bg=self.colors['bg'])
        filtros.pack(side=tk.RIGHT)

        for f in ["HOY", "SEMANA", "MES", "TODO"]:
            tk.Button(
                filtros, text=f,
                command=lambda x=f: self.cambiar_filtro(x),
                bg="#333", fg="white", relief="flat", padx=10, cursor="hand2"
            ).pack(side=tk.LEFT, padx=3)

        self.main_container = tk.Frame(self.root, bg=self.colors['bg'])
        self.main_container.pack(fill=tk.BOTH, expand=True, padx=20, pady=10)

        self.side_panel = tk.Frame(self.main_container, bg=self.colors['card'], width=300)
        self.side_panel.pack(side=tk.LEFT, fill=tk.Y, padx=(0, 10))
        self.side_panel.pack_propagate(False)

        self.graph_frame = tk.Frame(self.main_container, bg=self.colors['card'])
        self.graph_frame.pack(side=tk.RIGHT, fill=tk.BOTH, expand=True)

    def get_fecha_desde(self):
        hoy = datetime.now()
        if self.filtro_actual == "HOY":
            fecha = hoy.replace(hour=0, minute=0, second=0)
        elif self.filtro_actual == "SEMANA":
            fecha = hoy - timedelta(days=7)
        elif self.filtro_actual == "MES":
            fecha = hoy - timedelta(days=30)
        else:
            return None
        return fecha.strftime('%Y-%m-%d %H:%M:%S')

    def cambiar_filtro(self, filtro):
        self.filtro_actual = filtro
        self.cargar_graficos()

    def obtener_datos(self):
        try:
            conn = get_connection()
            fecha_desde = self.get_fecha_desde()
            where_fecha = "AND fecha >= %s" if fecha_desde else ""
            
            params = [self.empresa_id]
            if fecha_desde: params.append(fecha_desde)

            query = f"""
                SELECT DATE(fecha) as dia, SUM(total) as ingresos
                FROM ventas
                WHERE empresa_id=%s AND estado='COMPLETADA' {where_fecha}
                GROUP BY DATE(fecha) ORDER BY dia
            """

            # Leemos los datos
            df = pd.read_sql(query, conn, params=params)
            conn.close()

            if df.empty:
                return pd.DataFrame()

            # 🔥 FIX CRÍTICO: Eliminar filas que accidentalmente repitan los nombres de las columnas
            df = df[df['dia'] != 'dia'] 

            # Convertir a tipos correctos con manejo de errores
            df['ingresos'] = pd.to_numeric(df['ingresos'], errors='coerce').fillna(0)
            
            # Formatear la fecha para el gráfico (Día/Mes)
            df['dia'] = pd.to_datetime(df['dia'], errors='coerce').dt.strftime('%d/%m')
            
            # Eliminar posibles nulos después de la conversión
            df = df.dropna(subset=['dia'])

            return df

        except Exception as e:
            print("ERROR SQL EN REPORTE:", e)
            return pd.DataFrame()

    def top_productos(self):
        try:
            conn = get_connection()
            query = """
                SELECT i.nombre, SUM(i.cantidad) as total
                FROM venta_items i
                JOIN ventas v ON i.venta_id = v.id
                WHERE v.empresa_id=%s
                GROUP BY i.nombre ORDER BY total DESC LIMIT 5
            """
            df = pd.read_sql(query, conn, params=(self.empresa_id,))
            conn.close()
            return df[df['nombre'] != 'nombre'] # Fix preventivo
        except:
            return pd.DataFrame()

    def ventas_por_usuario(self):
        try:
            conn = get_connection()
            query = """
                SELECT u.nombre, COUNT(v.id) as total
                FROM ventas v
                JOIN usuarios u ON v.usuario_id = u.id
                WHERE v.empresa_id=%s
                GROUP BY u.nombre
            """
            df = pd.read_sql(query, conn, params=(self.empresa_id,))
            conn.close()
            return df[df['nombre'] != 'nombre'] # Fix preventivo
        except:
            return pd.DataFrame()

    def cargar_graficos(self):
        for w in self.graph_frame.winfo_children(): w.destroy()
        for w in self.side_panel.winfo_children(): w.destroy()

        df = self.obtener_datos()

        if df.empty:
            tk.Label(self.graph_frame, text=f"SIN DATOS REGISTRADOS",
                     bg=self.colors['card'], fg="gray", font=('Segoe UI', 12)).pack(expand=True)
            return

        fig, ax = plt.subplots(figsize=(9, 5), facecolor=self.colors['card'])
        ax.set_facecolor(self.colors['card'])

        ax.bar(df['dia'], df['ingresos'], color=self.colors['ingreso'], alpha=0.6, label="Ingresos")
        ax.plot(df['dia'], df['ingresos'], marker='o', color='white')

        ax.set_title("Ventas por día", color='white')
        ax.tick_params(colors='white')
        ax.legend()

        canvas = FigureCanvasTkAgg(fig, master=self.graph_frame)
        canvas.draw()
        canvas.get_tk_widget().pack(fill=tk.BOTH, expand=True)

        self.metric("TOTAL VENTAS", df['ingresos'].sum(), self.colors['ingreso'])

        # Paneles laterales
        tk.Label(self.side_panel, text="\nTOP PRODUCTOS", fg="white", bg=self.colors['card'], font=('Segoe UI', 10, 'bold')).pack()
        for _, r in self.top_productos().iterrows():
            tk.Label(self.side_panel, text=f"{r['nombre']} ({r['total']})", bg=self.colors['card'], fg="#aaa").pack(anchor="w", padx=10)

        tk.Label(self.side_panel, text="\nVENTAS POR USUARIO", fg="white", bg=self.colors['card'], font=('Segoe UI', 10, 'bold')).pack()
        for _, r in self.ventas_por_usuario().iterrows():
            tk.Label(self.side_panel, text=f"{r['nombre']} ({r['total']})", bg=self.colors['card'], fg="#aaa").pack(anchor="w", padx=10)

    def metric(self, titulo, valor, color):
        tk.Label(self.side_panel, text=titulo, bg=self.colors['card'], fg="#888", font=('Segoe UI', 9)).pack(anchor="w", padx=10)
        tk.Label(self.side_panel, text=f"$ {valor:,.2f}", bg=self.colors['card'], fg=color, font=('Consolas', 14, 'bold')).pack(anchor="w", padx=10, pady=(0,10))

if __name__ == "__main__":
    root = tk.Tk()
    nom = sys.argv[1] if len(sys.argv) > 1 else "NEXUS"
    emp = sys.argv[2] if len(sys.argv) > 2 else 1
    app = ReportesGUI(root, nom, emp)
    root.mainloop()