import sys
from PyQt6.QtWidgets import (
    QApplication, QWidget, QVBoxLayout, QPushButton,
    QTableWidget, QTableWidgetItem, QMessageBox
)

from productos import obtener_productos, eliminar_producto


class App(QWidget):

    def __init__(self):
        super().__init__()

        self.setWindowTitle("Sistema de Facturación")
        self.resize(800, 400)

        layout = QVBoxLayout()

        # TABLA
        self.tabla = QTableWidget()
        self.tabla.setColumnCount(5)
        self.tabla.setHorizontalHeaderLabels(
            ["ID", "Código", "Nombre", "Precio", "Stock"]
        )

        layout.addWidget(self.tabla)

        # BOTONES
        self.btn_cargar = QPushButton("Cargar productos")
        self.btn_cargar.clicked.connect(self.cargar_productos)
        layout.addWidget(self.btn_cargar)

        self.btn_eliminar = QPushButton("Eliminar seleccionado")
        self.btn_eliminar.clicked.connect(self.eliminar)
        layout.addWidget(self.btn_eliminar)

        self.setLayout(layout)

        # Cargar automáticamente al iniciar
        self.cargar_productos()

    # =========================
    # CARGAR PRODUCTOS
    # =========================
    def cargar_productos(self):
        try:
            productos = obtener_productos()

            print("DEBUG PRODUCTOS:", productos)  # 🔥 DEBUG

            self.tabla.setRowCount(0)

            for p in productos:
                row = self.tabla.rowCount()
                self.tabla.insertRow(row)

                self.tabla.setItem(row, 0, QTableWidgetItem(str(p["id"])))
                self.tabla.setItem(row, 1, QTableWidgetItem(p["codigo"]))
                self.tabla.setItem(row, 2, QTableWidgetItem(p["nombre"]))
                self.tabla.setItem(row, 3, QTableWidgetItem(str(p["precio"])))
                self.tabla.setItem(row, 4, QTableWidgetItem(str(p["stock"])))

        except Exception as e:
            print("ERROR AL CARGAR:", e)
            QMessageBox.critical(self, "Error", f"Error al cargar productos:\n{e}")

    # =========================
    # ELIMINAR
    # =========================
    def eliminar(self):
        try:
            row = self.tabla.currentRow()

            if row < 0:
                QMessageBox.warning(self, "Error", "Seleccioná un producto")
                return

            producto_id_item = self.tabla.item(row, 0)

            if not producto_id_item:
                QMessageBox.warning(self, "Error", "ID inválido")
                return

            producto_id = int(producto_id_item.text())

            eliminar_producto(producto_id)

            QMessageBox.information(self, "OK", "Producto eliminado")

            self.cargar_productos()

        except Exception as e:
            print("ERROR AL ELIMINAR:", e)
            QMessageBox.critical(self, "Error", f"Error al eliminar:\n{e}")


if __name__ == "__main__":
    app = QApplication(sys.argv)
    ventana = App()
    ventana.show()
    sys.exit(app.exec())