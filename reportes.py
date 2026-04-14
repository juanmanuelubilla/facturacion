import tkinter as tk
from tkinter import ttk
import matplotlib.pyplot as plt
from matplotlib.backends.backend_tkagg import FigureCanvasTkAgg
import pandas as pd
import sys
import warnings
from db import get_connection

# Ignorar advertencias de pandas sobre la conexión
warnings.filterwarnings("ignore", category=UserWarning)

class ReportesGUI:
    def __init__(self, root, nombre_negocio="NEXUS", empresa_id=1):
        self.root = root
        # Si el ID es 0 o falla, usamos 1 que es donde están tus datos
        try:
            self.empresa_id = int(empresa_id) if int(empresa_id) != 0 else 1
        except:
            self.empresa_id = 1
            
        self.root.title(f"{nombre_negocio} - REPORTES")
        self.root.geometry("1100x750")
        self.root.configure(bg='#121212')
        
        self.colors = {
            'bg': '#121212', 
            'card': '#1e1e1e', 
            'ingreso': '#00db84', 
            'gasto': '#ff4757',
            'texto': '#ffffff'
        }
        
        self.crear_widgets()
        self.cargar_graficos()

    def crear_widgets(self):
        header = tk.Frame(self.root, bg=self.colors['bg'], pady=20)
        header.pack(fill=tk.X, padx=20)
        
        tk.Label(header, text="📈 PANEL DE RENDIMIENTO", 
                 font=('Segoe UI', 18, 'bold'), bg=self.colors['bg'], fg="white").pack(side=tk.LEFT)
        
        tk.Button(header, text="CERRAR", bg="#333", fg="white", font=('Segoe UI', 10, 'bold'),
                  relief="flat", command=self.root.destroy, padx=20).pack(side=tk.RIGHT)

        self.main_container = tk.Frame(self.root, bg=self.colors['bg'])
        self.main_container.pack(fill=tk.BOTH, expand=True, padx=20, pady=10)

        self.side_panel = tk.Frame(self.main_container, bg=self.colors['card'], width=250, padx=20, pady=20)
        self.side_panel.pack(side=tk.LEFT, fill=tk.Y, padx=(0, 15))

    def obtener_datos(self):
        try:
            conn = get_connection()
            query = """
                SELECT dia, SUM(ingresos) as ingresos, SUM(gastos) as gastos
                FROM (
                    SELECT DATE(fecha) as dia, SUM(total) as ingresos, 0 as gastos
                    FROM ventas 
                    WHERE empresa_id = %s AND estado = 'COMPLETADA'
                    GROUP BY dia
                    
                    UNION ALL
                    
                    SELECT fecha as dia, SUM(monto) as ingresos, 0 as gastos
                    FROM finanzas 
                    WHERE empresa_id = %s AND tipo = 'INGRESO' AND categoria != 'Ventas'
                    GROUP BY dia
                    
                    UNION ALL
                    
                    SELECT fecha as dia, 0 as ingresos, SUM(monto) as gastos
                    FROM finanzas 
                    WHERE empresa_id = %s AND tipo = 'GASTO'
                    GROUP BY dia
                ) AS m
                GROUP BY dia 
                ORDER BY dia ASC
            """
            # Ejecutar query
            df = pd.read_sql(query, conn, params=(self.empresa_id, self.empresa_id, self.empresa_id))
            conn.close()

            if not df.empty:
                # SOLUCIÓN AL ERROR: Convertir explícitamente a números
                df['ingresos'] = pd.to_numeric(df['ingresos'], errors='coerce').fillna(0.0)
                df['gastos'] = pd.to_numeric(df['gastos'], errors='coerce').fillna(0.0)
                df['dia'] = df['dia'].astype(str)
            
            return df
        except Exception as e:
            print(f"Error en base de datos: {e}")
            return pd.DataFrame()

    def cargar_graficos(self):
        df = self.obtener_datos()
        
        if df.empty or (df['ingresos'].sum() == 0 and df['gastos'].sum() == 0):
            lbl = tk.Label(self.main_container, text="No hay movimientos financieros registrados\npara la empresa seleccionada.", 
                           bg=self.colors['bg'], fg="#666", font=('Segoe UI', 12))
            lbl.pack(expand=True)
            return

        # Crear figura de Matplotlib
        fig, ax = plt.subplots(figsize=(8, 5), facecolor=self.colors['card'])
        ax.set_facecolor(self.colors['card'])
        
        # Dibujar barras y líneas
        ax.bar(df['dia'], df['ingresos'], color=self.colors['ingreso'], label='Ingresos', alpha=0.7)
        ax.plot(df['dia'], df['gastos'], color=self.colors['gasto'], label='Gastos', marker='o', linewidth=2)
        
        # Estética del gráfico
        ax.tick_params(colors='white', labelsize=8)
        ax.spines['bottom'].set_color('#444')
        ax.spines['top'].set_visible(False)
        ax.spines['right'].set_visible(False)
        ax.spines['left'].set_color('#444')
        
        ax.legend(facecolor='#333', labelcolor='white', edgecolor='none')
        plt.xticks(rotation=45)
        plt.tight_layout()

        canvas = FigureCanvasTkAgg(fig, master=self.main_container)
        canvas.draw()
        canvas.get_tk_widget().pack(fill=tk.BOTH, expand=True)

        # Totales en el panel lateral (Ahora con números garantizados)
        total_in = float(df['ingresos'].sum())
        total_out = float(df['gastos'].sum())
        
        self.crear_metrica("INGRESOS TOTALES", f"$ {total_in:,.2f}", self.colors['ingreso'])
        self.crear_metrica("GASTOS TOTALES", f"$ {total_out:,.2f}", self.colors['gasto'])
        self.crear_metrica("BALANCE NETO", f"$ {(total_in - total_out):,.2f}", "#00a8ff")

    def crear_metrica(self, titulo, valor, color):
        frame = tk.Frame(self.side_panel, bg=self.colors['card'], pady=10)
        frame.pack(fill=tk.X)
        tk.Label(frame, text=titulo, bg=self.colors['card'], fg="#888", font=('Segoe UI', 8, 'bold')).pack(anchor="w")
        tk.Label(frame, text=valor, bg=self.colors['card'], fg=color, font=('Consolas', 14, 'bold')).pack(anchor="w")

if __name__ == "__main__":
    root = tk.Tk()
    # Capturar argumentos de main.py
    args = sys.argv + ["NEXUS", "1"]
    app = ReportesGUI(root, args[1], args[2])
    root.mainloop()