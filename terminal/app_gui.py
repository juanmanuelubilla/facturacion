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

        self.cargar_productos()

    def cargar_productos(self):
        productos = obtener_productos()

        self.tabla.setRowCount(len(productos))

        for row, p in enumerate(productos):
            self.tabla.setItem(row, 0, QTableWidgetItem(str(p["id"])))
            self.tabla.setItem(row, 1, QTableWidgetItem(p["codigo"]))
            self.tabla.setItem(row, 2, QTableWidgetItem(p["nombre"]))
            self.tabla.setItem(row, 3, QTableWidgetItem(str(p["precio"])))
            self.tabla.setItem(row, 4, QTableWidgetItem(str(p["stock"])))

    def eliminar(self):
        row = self.tabla.currentRow()

        if row < 0:
            QMessageBox.warning(self, "Error", "Seleccioná un producto")
            return

        producto_id = int(self.tabla.item(row, 0).text())

        eliminar_producto(producto_id)
        self.cargar_productos()


if __name__ == "__main__":
    app = QApplication(sys.argv)
    ventana = App()
    ventana.show()
    sys.exit(app.exec())