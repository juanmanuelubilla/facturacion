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
        try:
            # Si el ID es 0, lo tratamos como 1 pero la query buscará ambos
            self.empresa_id = int(empresa_id) if int(empresa_id) != 0 else 1
        except:
            self.empresa_id = 1
            
        self.root.title(f"{nombre_negocio} - REPORTES Y ESTADÍSTICAS")
        self.root.geometry("1200x800")
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
        # Header
        header = tk.Frame(self.root, bg=self.colors['bg'], pady=20)
        header.pack(fill=tk.X, padx=20)
        
        tk.Label(header, text="📈 PANEL DE RENDIMIENTO FINANCIERO", 
                 font=('Segoe UI', 18, 'bold'), bg=self.colors['bg'], fg="white").pack(side=tk.LEFT)
        
        tk.Button(header, text="CERRAR", bg="#333", fg="white", font=('Segoe UI', 10, 'bold'),
                  relief="flat", command=self.root.destroy, padx=20, cursor="hand2").pack(side=tk.RIGHT)

        # Contenedor Principal
        self.main_container = tk.Frame(self.root, bg=self.colors['bg'])
        self.main_container.pack(fill=tk.BOTH, expand=True, padx=20, pady=10)

        # Panel Lateral para métricas
        self.side_panel = tk.Frame(self.main_container, bg=self.colors['card'], width=280, padx=20, pady=20)
        self.side_panel.pack(side=tk.LEFT, fill=tk.Y, padx=(0, 15))
        self.side_panel.pack_propagate(False)

        # Área del Gráfico
        self.graph_frame = tk.Frame(self.main_container, bg=self.colors['card'], highlightthickness=1, highlightbackground="#333")
        self.graph_frame.pack(side=tk.RIGHT, fill=tk.BOTH, expand=True)

    def obtener_datos(self):
        try:
            conn = get_connection()
            # Ajuste de query: Filtramos por empresa_id %s O empresa_id 0 (para tus datos actuales)
            query = """
                SELECT dia, SUM(ingresos) as ingresos, SUM(gastos) as gastos
                FROM (
                    -- Ventas de productos (buscamos ID enviado e ID 0 por compatibilidad)
                    SELECT DATE(fecha) as dia, SUM(total) as ingresos, 0 as gastos
                    FROM ventas 
                    WHERE (empresa_id = %s OR empresa_id = 0) AND estado = 'COMPLETADA'
                    GROUP BY dia
                    
                    UNION ALL
                    
                    -- Otros ingresos manuales (Finanzas)
                    SELECT fecha as dia, SUM(monto) as ingresos, 0 as gastos
                    FROM finanzas 
                    WHERE (empresa_id = %s OR empresa_id = 0) AND tipo = 'INGRESO' AND categoria != 'Ventas'
                    GROUP BY dia
                    
                    UNION ALL
                    
                    -- Gastos registrados (Finanzas)
                    SELECT fecha as dia, 0 as ingresos, SUM(monto) as gastos
                    FROM finanzas 
                    WHERE (empresa_id = %s OR empresa_id = 0) AND tipo = 'GASTO'
                    GROUP BY dia
                ) AS m
                GROUP BY dia 
                ORDER BY dia ASC
            """
            df = pd.read_sql(query, conn, params=(self.empresa_id, self.empresa_id, self.empresa_id))
            conn.close()

            if not df.empty:
                df['ingresos'] = pd.to_numeric(df['ingresos'], errors='coerce').fillna(0.0)
                df['gastos'] = pd.to_numeric(df['gastos'], errors='coerce').fillna(0.0)
                df['dia'] = pd.to_datetime(df['dia']).dt.strftime('%d/%m')
            
            return df
        except Exception as e:
            print(f"Error en extracción de reportes: {e}")
            return pd.DataFrame()

    def cargar_graficos(self):
        for widget in self.graph_frame.winfo_children():
            widget.destroy()

        df = self.obtener_datos()
        
        if df.empty or (df['ingresos'].sum() == 0 and df['gastos'].sum() == 0):
            lbl = tk.Label(self.graph_frame, text="Sin datos suficientes para generar gráficos.\nRevise que las ventas estén 'COMPLETADAS'.", 
                           bg=self.colors['card'], fg="#666", font=('Segoe UI', 12))
            lbl.pack(expand=True)
            return

        plt.rcParams.update({'text.color': 'white', 'axes.labelcolor': 'white'})
        fig, ax = plt.subplots(figsize=(10, 6), facecolor=self.colors['card'])
        ax.set_facecolor(self.colors['card'])
        
        # Gráfico de barras para ingresos
        ax.bar(df['dia'], df['ingresos'], color=self.colors['ingreso'], label='Ingresos ($)', alpha=0.6, width=0.5)
        
        # Gráfico de línea para gastos
        ax.plot(df['dia'], df['gastos'], color=self.colors['gasto'], label='Gastos ($)', 
                marker='o', linewidth=3, markersize=8, markerfacecolor='white')
        
        ax.set_title("Evolución de Caja", fontsize=14, pad=20, color='white', fontweight='bold')
        ax.tick_params(colors='white', labelsize=9)
        ax.grid(axis='y', linestyle='--', alpha=0.1, color='white')
        
        for spine in ax.spines.values():
            spine.set_visible(False)
        
        ax.legend(facecolor='#333', labelcolor='white', edgecolor='none', loc='upper left')
        plt.tight_layout()

        canvas = FigureCanvasTkAgg(fig, master=self.graph_frame)
        canvas.draw()
        canvas.get_tk_widget().pack(fill=tk.BOTH, expand=True, padx=10, pady=10)

        # Cálculo de métricas para el panel lateral
        total_in = float(df['ingresos'].sum())
        total_out = float(df['gastos'].sum())
        balance = total_in - total_out
        
        tk.Label(self.side_panel, text="RESUMEN TOTAL", font=('Segoe UI', 10, 'bold'), 
                 bg=self.colors['card'], fg=self.colors['accent']).pack(anchor="w", pady=(0, 20))

        self.crear_metrica("INGRESOS", f"$ {total_in:,.2f}", self.colors['ingreso'])
        self.crear_metrica("GASTOS", f"$ {total_out:,.2f}", self.colors['gasto'])
        
        tk.Frame(self.side_panel, height=1, bg="#333").pack(fill=tk.X, pady=20)
        
        color_bal = self.colors['ingreso'] if balance >= 0 else self.colors['gasto']
        self.crear_metrica("BALANCE NETO", f"$ {balance:,.2f}", color_bal)

    def crear_metrica(self, titulo, valor, color):
        frame = tk.Frame(self.side_panel, bg=self.colors['card'], pady=12)
        frame.pack(fill=tk.X)
        tk.Label(frame, text=titulo, bg=self.colors['card'], fg="#888", font=('Segoe UI', 8, 'bold')).pack(anchor="w")
        tk.Label(frame, text=valor, bg=self.colors['card'], fg=color, font=('Consolas', 16, 'bold')).pack(anchor="w")

if __name__ == "__main__":
    root = tk.Tk()
    nom = sys.argv[1] if len(sys.argv) > 1 else "NEXUS"
    emp = sys.argv[2] if len(sys.argv) > 2 else 1
    app = ReportesGUI(root, nom, emp)
    root.mainloop()