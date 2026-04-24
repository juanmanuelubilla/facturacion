import tkinter as tk
from tkinter import ttk, messagebox
from datetime import datetime, timedelta
from decimal import Decimal
from db import get_connection
import calendar
from PIL import Image, ImageTk
import os
import sys

class ReportesGUI:
    def __init__(self, root, nombre_negocio="NEXUS", empresa_id=1, usuario_id=1):
        self.root = root
        self.empresa_id = int(empresa_id)
        self.usuario_id = int(usuario_id)
        
        # Cargar configuración de la empresa
        self.config_negocio = self.cargar_datos_negocio()
        self.nombre_negocio = self.config_negocio.get('nombre_negocio', nombre_negocio).upper()
        
        self.root.title(f"{self.nombre_negocio} - REPORTES")
        self.root.geometry("1400x900")
        
        self.colors = {
            'bg_main': '#121212', 'bg_panel': '#1e1e1e', 'accent': '#00a8ff',
            'success': '#00db84', 'danger': '#ff4757', 'text_main': '#ffffff',
            'text_dim': '#a0a0a0', 'border': '#333333', 'warning': '#f39c12'
        }
        
        self.configurar_estilo()
        self.crear_widgets()
        self.cargar_configuracion_fechas()

    def cargar_datos_negocio(self):
        """Carga los datos de configuración de la empresa"""
        try:
            conn = get_connection()
            with conn.cursor() as cursor:
                cursor.execute("SELECT * FROM nombre_negocio WHERE empresa_id=%s OR id=1 LIMIT 1", (self.empresa_id,))
                res = cursor.fetchone()
                return res if res else {}
        except:
            return {}
        finally:
            if 'conn' in locals() and conn: conn.close()

    def configurar_estilo(self):
        """Configura el estilo de la interfaz"""
        style = ttk.Style()
        style.theme_use('clam')
        self.root.configure(bg=self.colors['bg_main'])
        
        # Configurar estilos para treeviews
        style.configure('Report.Treeview', background=self.colors['bg_panel'], foreground=self.colors['text_main'],
                        fieldbackground=self.colors['bg_panel'], borderwidth=0, font=('Segoe UI', 10))
        style.configure('Report.Treeview.Heading', font=('Segoe UI', 10, 'bold'), 
                        background="#252525", foreground="white", relief="flat")

    def crear_widgets(self):
        """Crea todos los widgets de la interfaz"""
        main_container = tk.Frame(self.root, bg=self.colors['bg_main'], padx=20, pady=20)
        main_container.pack(fill=tk.BOTH, expand=True)

        # Header
        header = tk.Frame(main_container, bg=self.colors['bg_main'])
        header.pack(fill=tk.X, pady=(0, 20))
        
        tk.Label(header, text="📊 PANEL DE REPORTES", 
                 font=('Segoe UI', 18, 'bold'), bg=self.colors['bg_main'], 
                 fg=self.colors['accent']).pack(side=tk.LEFT)

        # Frame principal
        content_frame = tk.Frame(main_container, bg=self.colors['bg_main'])
        content_frame.pack(fill=tk.BOTH, expand=True)

        # Panel izquierdo - Filtros
        left_panel = tk.Frame(content_frame, bg=self.colors['bg_panel'], width=350, highlightthickness=1, highlightbackground=self.colors['border'])
        left_panel.pack(side=tk.LEFT, fill=tk.Y, padx=(0, 10))
        left_panel.pack_propagate(False)

        self.crear_panel_filtros(left_panel)

        # Panel derecho - Resultados
        right_panel = tk.Frame(content_frame, bg=self.colors['bg_main'])
        right_panel.pack(side=tk.RIGHT, fill=tk.BOTH, expand=True)

        self.crear_panel_resultados(right_panel)

    def crear_panel_filtros(self, parent):
        """Crea el panel de filtros"""
        tk.Label(parent, text="FILTROS DE REPORTE", 
                 font=('Segoe UI', 12, 'bold'), bg=self.colors['bg_panel'], 
                 fg=self.colors['accent']).pack(pady=15)

        # Tipo de reporte
        tk.Label(parent, text="Tipo de Reporte:", 
                 font=('Segoe UI', 10, 'bold'), bg=self.colors['bg_panel'], 
                 fg=self.colors['text_main']).pack(anchor="w", padx=20, pady=(10, 5))

        self.tipo_reporte = tk.StringVar(value="ganancias")
        tipos = [
            ("Ganancias", "ganancias"),
            ("Gastos", "gastos"),
            ("Ventas Brutas", "ventas_brutas"),
            ("Impuestos IVA", "impuestos"),
            ("Ganancia Neta", "ganancia_neta")
        ]

        for text, value in tipos:
            tk.Radiobutton(parent, text=text, variable=self.tipo_reporte, value=value,
                          bg=self.colors['bg_panel'], fg=self.colors['text_main'],
                          selectcolor=self.colors['accent'], font=('Segoe UI', 9)).pack(anchor="w", padx=20)

        # Filtros de fecha
        tk.Label(parent, text="Rango de Fechas:", 
                 font=('Segoe UI', 10, 'bold'), bg=self.colors['bg_panel'], 
                 fg=self.colors['text_main']).pack(anchor="w", padx=20, pady=(20, 5))

        # Frame para filtros de fecha
        fecha_frame = tk.Frame(parent, bg=self.colors['bg_panel'])
        fecha_frame.pack(fill=tk.X, padx=20, pady=10)

        # Filtro rápido
        tk.Label(fecha_frame, text="Filtro Rápido:", 
                 font=('Segoe UI', 9, 'bold'), bg=self.colors['bg_panel'], 
                 fg=self.colors['text_dim']).pack(anchor="w")

        filtro_frame = tk.Frame(fecha_frame, bg=self.colors['bg_panel'])
        filtro_frame.pack(fill=tk.X, pady=5)

        self.filtro_rapido = tk.StringVar(value="hoy")
        filtros_rapidos = [
            ("Hoy", "hoy"),
            ("Ayer", "ayer"),
            ("Esta Semana", "semana"),
            ("Este Mes", "mes"),
            ("Mes Anterior", "mes_anterior"),
            ("Este Año", "año"),
            ("Personalizado", "personalizado")
        ]

        for text, value in filtros_rapidos:
            tk.Radiobutton(filtro_frame, text=text, variable=self.filtro_rapido, value=value,
                          bg=self.colors['bg_panel'], fg=self.colors['text_main'],
                          selectcolor=self.colors['accent'], font=('Segoe UI', 8),
                          command=self.actualizar_fechas_filtro).pack(side=tk.LEFT, padx=5)

        # Fechas personalizadas
        fechas_frame = tk.Frame(parent, bg=self.colors['bg_panel'])
        fechas_frame.pack(fill=tk.X, padx=20, pady=10)

        # Fecha desde
        desde_frame = tk.Frame(fechas_frame, bg=self.colors['bg_panel'])
        desde_frame.pack(fill=tk.X, pady=5)
        
        tk.Label(desde_frame, text="Desde:", font=('Segoe UI', 9, 'bold'),
                 bg=self.colors['bg_panel'], fg=self.colors['text_main']).pack(side=tk.LEFT, padx=(0, 10))
        
        self.fecha_desde = tk.Entry(desde_frame, font=('Segoe UI', 10), bg="#252525", fg="white", width=15)
        self.fecha_desde.pack(side=tk.LEFT, padx=5)

        # Fecha hasta
        hasta_frame = tk.Frame(fechas_frame, bg=self.colors['bg_panel'])
        hasta_frame.pack(fill=tk.X, pady=5)
        
        tk.Label(hasta_frame, text="Hasta:", font=('Segoe UI', 9, 'bold'),
                 bg=self.colors['bg_panel'], fg=self.colors['text_main']).pack(side=tk.LEFT, padx=(0, 10))
        
        self.fecha_hasta = tk.Entry(hasta_frame, font=('Segoe UI', 10), bg="#252525", fg="white", width=15)
        self.fecha_hasta.pack(side=tk.LEFT, padx=5)

        # Botones
        botones_frame = tk.Frame(parent, bg=self.colors['bg_panel'])
        botones_frame.pack(fill=tk.X, padx=20, pady=20)

        btn_generar = tk.Button(botones_frame, text="📊 GENERAR REPORTE", 
                                bg=self.colors['success'], fg="black", 
                                font=('Segoe UI', 11, 'bold'), 
                                relief="flat", padx=20, pady=10, 
                                command=self.generar_reporte, cursor="hand2")
        btn_generar.pack(side=tk.LEFT, padx=5)

        btn_exportar = tk.Button(botones_frame, text="📤 EXPORTAR CSV", 
                                bg=self.colors['accent'], fg="white", 
                                font=('Segoe UI', 11, 'bold'), 
                                relief="flat", padx=20, pady=10, 
                                command=self.exportar_csv, cursor="hand2")
        btn_exportar.pack(side=tk.LEFT, padx=5)

        btn_limpiar = tk.Button(botones_frame, text="🗑️ LIMPIAR", 
                                bg=self.colors['danger'], fg="white", 
                                font=('Segoe UI', 11, 'bold'), 
                                relief="flat", padx=20, pady=10, 
                                command=self.limpiar_resultados, cursor="hand2")
        btn_limpiar.pack(side=tk.LEFT, padx=5)

    def crear_panel_resultados(self, parent):
        """Crea el panel de resultados"""
        # Frame principal
        main_frame = tk.Frame(parent, bg=self.colors['bg_main'])
        main_frame.pack(fill=tk.BOTH, expand=True)

        # Header con resumen
        header_frame = tk.Frame(main_frame, bg=self.colors['bg_panel'], highlightthickness=1, highlightbackground=self.colors['border'])
        header_frame.pack(fill=tk.X, pady=(0, 10))

        self.lbl_resumen = tk.Label(header_frame, text="Seleccione un tipo de reporte y haga clic en GENERAR", 
                                     font=('Segoe UI', 12, 'bold'), bg=self.colors['bg_panel'], 
                                     fg=self.colors['text_dim'])
        self.lbl_resumen.pack(pady=20)

        # Tabla de resultados
        tabla_frame = tk.Frame(main_frame, bg=self.colors['bg_panel'])
        tabla_frame.pack(fill=tk.BOTH, expand=True, padx=10, pady=10)

        # Scrollbar para la tabla
        scroll_frame = tk.Frame(tabla_frame, bg=self.colors['bg_panel'])
        scroll_frame.pack(fill=tk.BOTH, expand=True)

        scrollbar = ttk.Scrollbar(scroll_frame)
        scrollbar.pack(side=tk.RIGHT, fill=tk.Y)

        self.tabla_resultados = ttk.Treeview(scroll_frame, columns=("Fecha", "Descripción", "Ingresos", "Egresos", "Neto"), 
                                           show="tree headings", style="Report.Treeview", height=20)
        self.tabla_resultados.heading("#0", text="ID")
        self.tabla_resultados.heading("Fecha", text="FECHA")
        self.tabla_resultados.heading("Descripción", text="DESCRIPIÓN")
        self.tabla_resultados.heading("Ingresos", text="INGRESOS $")
        self.tabla_resultados.heading("Egresos", text="EGRESOS $")
        self.tabla_resultados.heading("Neto", text="NETO $")

        # Configurar columnas
        self.tabla_resultados.column("#0", width=60, anchor="center")
        self.tabla_resultados.column("Fecha", width=120, anchor="center")
        self.tabla_resultados.column("Descripción", width=300, anchor="w")
        self.tabla_resultados.column("Ingresos", width=120, anchor="e")
        self.tabla_resultados.column("Egresos", width=120, anchor="e")
        self.tabla_resultados.column("Neto", width=120, anchor="e")

        self.tabla_resultados.pack(side=tk.LEFT, fill=tk.BOTH, expand=True)
        scrollbar.config(command=self.tabla_resultados.yview)
        self.tabla_resultados.config(yscrollcommand=scrollbar.set)

    def actualizar_fechas_filtro(self):
        """Actualiza las fechas según el filtro rápido seleccionado"""
        hoy = datetime.now()
        filtro = self.filtro_rapido.get()

        if filtro == "hoy":
            self.fecha_desde.delete(0, tk.END)
            self.fecha_hasta.delete(0, tk.END)
            self.fecha_desde.insert(0, hoy.strftime("%Y-%m-%d"))
            self.fecha_hasta.insert(0, hoy.strftime("%Y-%m-%d"))
            
        elif filtro == "ayer":
            ayer = hoy - timedelta(days=1)
            self.fecha_desde.delete(0, tk.END)
            self.fecha_hasta.delete(0, tk.END)
            self.fecha_desde.insert(0, ayer.strftime("%Y-%m-%d"))
            self.fecha_hasta.insert(0, ayer.strftime("%Y-%m-%d"))
            
        elif filtro == "semana":
            inicio_semana = hoy - timedelta(days=hoy.weekday())
            self.fecha_desde.delete(0, tk.END)
            self.fecha_hasta.delete(0, tk.END)
            self.fecha_desde.insert(0, inicio_semana.strftime("%Y-%m-%d"))
            self.fecha_hasta.insert(0, hoy.strftime("%Y-%m-%d"))
            
        elif filtro == "mes":
            primer_dia = hoy.replace(day=1)
            ultimo_dia = hoy.replace(day=calendar.monthrange(hoy.year, hoy.month)[1])
            self.fecha_desde.delete(0, tk.END)
            self.fecha_hasta.delete(0, tk.END)
            self.fecha_desde.insert(0, primer_dia.strftime("%Y-%m-%d"))
            self.fecha_hasta.insert(0, ultimo_dia.strftime("%Y-%m-%d"))
            
        elif filtro == "mes_anterior":
            if hoy.month == 1:
                mes_anterior = hoy.replace(year=hoy.year-1, month=12)
            else:
                mes_anterior = hoy.replace(month=hoy.month-1)
            
            primer_dia = mes_anterior.replace(day=1)
            ultimo_dia = mes_anterior.replace(day=calendar.monthrange(mes_anterior.year, mes_anterior.month)[1])
            
            self.fecha_desde.delete(0, tk.END)
            self.fecha_hasta.delete(0, tk.END)
            self.fecha_desde.insert(0, primer_dia.strftime("%Y-%m-%d"))
            self.fecha_hasta.insert(0, ultimo_dia.strftime("%Y-%m-%d"))
            
        elif filtro == "año":
            primer_dia = hoy.replace(month=1, day=1)
            ultimo_dia = hoy.replace(month=12, day=31)
            self.fecha_desde.delete(0, tk.END)
            self.fecha_hasta.delete(0, tk.END)
            self.fecha_desde.insert(0, primer_dia.strftime("%Y-%m-%d"))
            self.fecha_hasta.insert(0, ultimo_dia.strftime("%Y-%m-%d"))
            
        elif filtro == "personalizado":
            # No hacer nada, dejar las fechas como están
            pass

    def generar_reporte(self):
        """Genera el reporte según los filtros seleccionados"""
        try:
            # Obtener fechas
            fecha_desde = self.fecha_desde.get().strip()
            fecha_hasta = self.fecha_hasta.get().strip()
            
            if not fecha_desde or not fecha_hasta:
                messagebox.showerror("Error", "Debe seleccionar un rango de fechas")
                return

            # Convertir a datetime
            try:
                desde = datetime.strptime(fecha_desde, "%Y-%m-%d")
                hasta = datetime.strptime(fecha_hasta, "%Y-%m-%d")
                hasta = hasta.replace(hour=23, minute=59, second=59)
            except ValueError:
                messagebox.showerror("Error", "Formato de fecha inválido. Use YYYY-MM-DD")
                return

            tipo_reporte = self.tipo_reporte.get()
            
            # Limpiar tabla anterior
            for item in self.tabla_resultados.get_children():
                self.tabla_resultados.delete(item)

            # Generar datos según el tipo de reporte
            if tipo_reporte == "ganancias":
                self.generar_reporte_ganancias(desde, hasta)
            elif tipo_reporte == "gastos":
                self.generar_reporte_gastos(desde, hasta)
            elif tipo_reporte == "ventas_brutas":
                self.generar_reporte_ventas_brutas(desde, hasta)
            elif tipo_reporte == "impuestos":
                self.generar_reporte_impuestos(desde, hasta)
            elif tipo_reporte == "ganancia_neta":
                self.generar_reporte_ganancia_neta(desde, hasta)

        except Exception as e:
            print(f"Error general en generar_reporte: {e}")
            messagebox.showerror("Error", f"Error al generar reporte: {str(e)}")

    def generar_reporte_ganancias(self, desde, hasta):
        """Genera reporte de ganancias"""
        try:
            conn = get_connection()
            with conn.cursor() as cursor:
                # Consulta corregida para traer ventas con sus ganancias
                cursor.execute("""
                    SELECT DATE(v.fecha) as fecha, v.total as total, v.ganancia as ganancia,
                           COUNT(v.id) as cantidad_ventas
                    FROM ventas v
                    WHERE v.empresa_id = %s AND v.fecha BETWEEN %s AND %s
                    AND v.estado = 'completado'
                    GROUP BY DATE(v.fecha)
                    ORDER BY DATE(v.fecha)
                """, (self.empresa_id, desde, hasta))
                
                resultados = cursor.fetchall()
                
                if not resultados:
                    self.lbl_resumen.config(text="No se encontraron ventas en el período seleccionado")
                    return
                
                total_ventas = 0
                total_ganancia = 0
                
                for row in resultados:
                    fecha = row['fecha']
                    total = float(row['total'])
                    ganancia = float(row['ganancia'])
                    cantidad = int(row['cantidad_ventas'])
                    
                    total_ventas += total
                    total_ganancia += ganancia
                    
                    self.tabla_resultados.insert("", tk.END, values=(
                        fecha, f"Ventas del día", f"{total:.2f}", "", f"{ganancia:.2f}"
                    ))
                
                # Agregar totales
                self.tabla_resultados.insert("", tk.END, values=(
                    "TOTAL", "Resumen del Período", f"{total_ventas:.2f}", "", f"{total_ganancia:.2f}"
                ))
                
                self.lbl_resumen.config(
                    text=f"GANANCIAS: {len(resultados)} días | Ventas: ${total_ventas:.2f} | Ganancia: ${total_ganancia:.2f}"
                )
                
        except Exception as e:
            print(f"Error en reporte de ganancias: {e}")
            messagebox.showerror("Error", f"Error en reporte de ganancias: {str(e)}")

    def generar_reporte_gastos(self, desde, hasta):
        """Genera reporte de gastos"""
        try:
            conn = get_connection()
            with conn.cursor() as cursor:
                # Consulta corregida para traer gastos de finanzas
                cursor.execute("""
                    SELECT DATE(f.fecha) as fecha, f.monto as monto, f.descripcion,
                           COUNT(f.id) as cantidad_gastos
                    FROM finanzas f
                    WHERE f.empresa_id = %s AND f.fecha BETWEEN %s AND %s
                    AND f.tipo = 'GASTO'
                    GROUP BY DATE(f.fecha), f.descripcion
                    ORDER BY DATE(f.fecha)
                """, (self.empresa_id, desde, hasta))
                
                resultados = cursor.fetchall()
                
                if not resultados:
                    self.lbl_resumen.config(text="No se encontraron gastos en el período seleccionado")
                    return
                
                total_gastos = 0
                
                for row in resultados:
                    fecha = row['fecha']
                    monto = float(row['monto'])
                    descripcion = row['descripcion']
                    cantidad = int(row['cantidad_gastos'])
                    
                    total_gastos += monto
                    
                    self.tabla_resultados.insert("", tk.END, values=(
                        fecha, descripcion, "", f"{monto:.2f}", f"-{monto:.2f}"
                    ))
                
                # Agregar totales
                self.tabla_resultados.insert("", tk.END, values=(
                    "TOTAL", "Resumen del Período", "", f"{total_gastos:.2f}", f"-{total_gastos:.2f}"
                ))
                
                self.lbl_resumen.config(
                    text=f"GASTOS: {len(resultados)} registros | Total: ${total_gastos:.2f}"
                )
                
        except Exception as e:
            print(f"Error en reporte de gastos: {e}")
            messagebox.showerror("Error", f"Error en reporte de gastos: {str(e)}")

    def generar_reporte_ventas_brutas(self, desde, hasta):
        """Genera reporte de ventas brutas"""
        try:
            conn = get_connection()
            with conn.cursor() as cursor:
                # Consulta corregida para traer ventas brutas
                cursor.execute("""
                    SELECT DATE(v.fecha) as fecha, SUM(v.total) as total_ventas,
                           COUNT(v.id) as cantidad_ventas
                    FROM ventas v
                    WHERE v.empresa_id = %s AND v.fecha BETWEEN %s AND %s
                    AND v.estado = 'completado'
                    GROUP BY DATE(v.fecha)
                    ORDER BY DATE(v.fecha)
                """, (self.empresa_id, desde, hasta))
                
                resultados = cursor.fetchall()
                
                if not resultados:
                    self.lbl_resumen.config(text="No se encontraron ventas en el período seleccionado")
                    return
                
                total_ventas_brutas = 0
                
                for row in resultados:
                    fecha = row['fecha']
                    total = float(row['total_ventas'])
                    cantidad = int(row['cantidad_ventas'])
                    
                    total_ventas_brutas += total
                    
                    self.tabla_resultados.insert("", tk.END, values=(
                        fecha, f"Ventas brutas", f"{total:.2f}", "", ""
                    ))
                
                # Agregar totales
                self.tabla_resultados.insert("", tk.END, values=(
                    "TOTAL", "Resumen del Período", f"{total_ventas_brutas:.2f}", "", ""
                ))
                
                self.lbl_resumen.config(
                    text=f"VENTAS BRUTAS: {len(resultados)} días | Total: ${total_ventas_brutas:.2f}"
                )
                
        except Exception as e:
            print(f"Error en reporte de ventas brutas: {e}")
            messagebox.showerror("Error", f"Error en reporte de ventas brutas: {str(e)}")

    def generar_reporte_impuestos(self, desde, hasta):
        """Genera reporte de impuestos IVA"""
        try:
            conn = get_connection()
            with conn.cursor() as cursor:
                # Consulta corregida para traer impuestos de ventas
                cursor.execute("""
                    SELECT DATE(v.fecha) as fecha, 
                           SUM(v.total * 0.21) as iva_calculado,
                           COUNT(v.id) as cantidad_ventas
                    FROM ventas v
                    WHERE v.empresa_id = %s AND v.fecha BETWEEN %s AND %s
                    AND v.estado = 'completado'
                    GROUP BY DATE(v.fecha)
                    ORDER BY DATE(v.fecha)
                """, (self.empresa_id, desde, hasta))
                
                resultados = cursor.fetchall()
                
                if not resultados:
                    self.lbl_resumen.config(text="No se encontraron ventas con impuestos en el período seleccionado")
                    return
                
                total_iva = 0
                
                for row in resultados:
                    fecha = row['fecha']
                    iva = float(row['iva_calculado'])
                    cantidad = int(row['cantidad_ventas'])
                    
                    total_iva += iva
                    
                    self.tabla_resultados.insert("", tk.END, values=(
                        fecha, f"IVA (21%)", f"{iva:.2f}", "", f"-{iva:.2f}"
                    ))
                
                # Agregar totales
                self.tabla_resultados.insert("", tk.END, values=(
                    "TOTAL", "Resumen del Período", f"{total_iva:.2f}", "", f"-{total_iva:.2f}"
                ))
                
                self.lbl_resumen.config(
                    text=f"IMPUESTOS IVA: {len(resultados)} días | Total IVA: ${total_iva:.2f}"
                )
                
        except Exception as e:
            print(f"Error en reporte de impuestos: {e}")
            messagebox.showerror("Error", f"Error en reporte de impuestos: {str(e)}")

    def generar_reporte_ganancia_neta(self, desde, hasta):
        """Genera reporte de ganancia neta (ventas - costos - impuestos)"""
        try:
            conn = get_connection()
            with conn.cursor() as cursor:
                # Consulta corregida para traer datos de ganancia neta
                cursor.execute("""
                    SELECT DATE(v.fecha) as fecha, 
                           SUM(v.total) as total_ventas,
                           SUM(v.ganancia) as total_ganancia,
                           SUM(v.total * 0.21) as total_iva,
                           COUNT(v.id) as cantidad_ventas
                    FROM ventas v
                    WHERE v.empresa_id = %s AND v.fecha BETWEEN %s AND %s
                    AND v.estado = 'completado'
                    GROUP BY DATE(v.fecha)
                    ORDER BY DATE(v.fecha)
                """, (self.empresa_id, desde, hasta))
                
                resultados = cursor.fetchall()
                
                if not resultados:
                    self.lbl_resumen.config(text="No se encontraron ventas en el período seleccionado")
                    return
                
                total_ventas_brutas = 0
                total_ganancia = 0
                total_iva = 0
                total_ganancia_neta = 0
                
                for row in resultados:
                    fecha = row['fecha']
                    ventas = float(row['total_ventas'])
                    ganancia = float(row['total_ganancia'])
                    iva = float(row['total_iva'])
                    cantidad = int(row['cantidad_ventas'])
                    
                    total_ventas_brutas += ventas
                    total_ganancia += ganancia
                    total_iva += iva
                    
                    # Ganancia neta = Ventas - IVA
                    ganancia_neta = ventas - iva
                    
                    total_ganancia_neta += ganancia_neta
                    
                    self.tabla_resultados.insert("", tk.END, values=(
                        fecha, f"Ganancia Neta", f"{ganancia_neta:.2f}", "", ""
                    ))
                
                # Agregar totales
                self.tabla_resultados.insert("", tk.END, values=(
                    "TOTAL", "Resumen del Período", f"${total_ventas_brutas:.2f}", 
                    f"${total_iva:.2f}", f"${total_ganancia_neta:.2f}"
                ))
                
                self.lbl_resumen.config(
                    text=f"GANANCIA NETA: {len(resultados)} días | Ventas: ${total_ventas_brutas:.2f} | IVA: ${total_iva:.2f} | Ganancia Neta: ${total_ganancia_neta:.2f}"
                )
                
        except Exception as e:
            print(f"Error en reporte de ganancia neta: {e}")
            messagebox.showerror("Error", f"Error en reporte de ganancia neta: {str(e)}")

    def exportar_csv(self):
        """Exporta los datos actuales a CSV"""
        try:
            from tkinter import filedialog
            import csv
            
            # Obtener datos de la tabla
            datos = []
            for child in self.tabla_resultados.get_children():
                valores = self.tabla_resultados.item(child, 'values')
                datos.append(valores)
            
            if not datos:
                messagebox.showwarning("Sin datos", "No hay datos para exportar")
                return
            
            # Seleccionar archivo
            archivo = filedialog.asksaveasfilename(
                defaultfilename=f"reporte_{datetime.now().strftime('%Y%m%d_%H%M%S')}.csv",
                filetypes=[("CSV files", "*.csv"), ("All files", "*.*")]
            )
            
            if archivo:
                with open(archivo, 'w', newline='', encoding='utf-8') as csvfile:
                    writer = csv.writer(csvfile)
                    writer.writerow(["Fecha", "Descripción", "Ingresos", "Egresos", "Neto"])
                    writer.writerows(datos)
                
                messagebox.showinfo("Exportado", f"Datos exportados a {archivo}")
                
        except Exception as e:
            messagebox.showerror("Error", f"Error al exportar CSV: {str(e)}")

    def limpiar_resultados(self):
        """Limpia la tabla de resultados"""
        for item in self.tabla_resultados.get_children():
            self.tabla_resultados.delete(item)
        self.lbl_resumen.config(text="Tabla limpia. Generar un nuevo reporte para ver resultados.")

    def cargar_configuracion_fechas(self):
        """Carga configuración de fechas por defecto"""
        hoy = datetime.now()
        self.fecha_desde.insert(0, hoy.strftime("%Y-%m-%d"))
        self.fecha_hasta.insert(0, hoy.strftime("%Y-%m-%d"))

if __name__ == "__main__":
    # Soporta el paso de argumentos desde el main.py
    negocio = sys.argv[1] if len(sys.argv) > 1 else "NEXUS"
    emp_id = int(sys.argv[2]) if len(sys.argv) > 2 else 1
    usu_id = int(sys.argv[3]) if len(sys.argv) > 3 else 1
    
    root = tk.Tk()
    app = ReportesGUI(root, negocio, emp_id, usu_id)
    root.mainloop()
