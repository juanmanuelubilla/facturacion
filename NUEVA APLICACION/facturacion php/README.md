# NEXUS POS - Versión PHP

Sistema de facturación completo y profesional convertido de Python a PHP. Estructura modular con arquitectura empresarial.

## 📁 Archivos Principales

### **Configuración y Autenticación**
- `config.php` - Configuración central, conexión a BD y utilidades
- `login.php` - Sistema de login multiempresa
- `logout.php` - Cierre de sesión seguro

### **Gestión de Usuarios y Empresas**
- `usuarios.php` - Administración de usuarios y roles
- `empresas.php` - Gestión multiempresa completa

### **Módulos Principales**
- `dashboard.php` - Panel principal con estadísticas en tiempo real
- `productos.php` - Gestión completa de inventario con imágenes y códigos de barras
- `clientes.php` - Agenda de clientes con procesamiento de imágenes
- `ventas.php` - Punto de venta con pantalla cliente integrada
- `finanzas.php` - Control de caja y movimientos financieros
- `contabilidad.php` - Sistema contable completo con asientos automáticos

### **Marketing y Comunicación**
- `whatsapp.php` - Envío masivo de mensajes WhatsApp
- `promociones.php` - Gestión de cupones y descuentos

### **Gestión Visual y Multimedia**
- `banners.php` - Sistema de banners DLNA con procesamiento de imágenes
- `imagenes.php` - Generador de imágenes con IA
- `etiquetas.php` - Generación de códigos de barras EAN-13

### **Reportes y Configuración**
- `reportes.php` - Sistema de reportes
- `configurar.php` - Configuración general del sistema

### **APIs y Servicios**
- `api_procesar_venta.php` - API para procesamiento de ventas
- `api_whatsapp.php` - API para envío de mensajes
- `api_generar_etiqueta.php` - API para generación de etiquetas

### **Librerías y Clases**
- `lib/` - Clases reutilizables:
  - `ImageProcessor.php` - Procesamiento avanzado de imágenes
  - `EmpresaFiles.php` - Gestión de archivos multiempresa
  - `BarcodeGenerator.php` - Generador de códigos de barras
  - `PDFGenerator.php` - Generador de PDFs
  - `WhatsAppService.php` - Servicio de integración WhatsApp
  - `MotorContable.php` - Motor contable automático

### **Pantallas Auxiliares**
- `pantalla_cliente.php` - Visualización para clientes con información de pago

## 🚀 Ejecución

```bash
cd /home/pi/facturacion_php
php -S localhost:8000
```

Abrir en navegador: `http://localhost:8000/login.php`

## 🔄 Flujo de Implementación

### **Paso 1: Configuración Inicial**
1. Acceder a `/setup.php` para configuración inicial
2. Crear empresa y administrador principal
3. Configurar parámetros básicos del sistema

### **Paso 2: Configuración de Productos (OBLIGATORIO)**
1. Acceder a `/productos.php`
2. Cargar productos básicos con:
   - Nombre y descripción
   - Precio de venta y costo
   - Stock inicial
   - Categorías (opcional)
3. **IMPORTANTE**: Sin productos cargados, el sistema no permitirá ventas

### **Paso 3: Configuración de Clientes**
1. Acceder a `/clientes.php`
2. Cargar base de clientes inicial
3. Configurar datos de contacto

### **Paso 4: Operación de Ventas**
1. Acceder a `/ventas.php`
2. Realizar ventas con productos cargados
3. Verificar stock y facturación

### **Paso 5: Control de Inventario**
1. Acceder a `/inventario.php`
2. Monitorear niveles de stock
3. Configurar umbrales de alerta

## ⚠️ Requisitos Mínimos de Operación

Antes de comenzar operaciones, asegúrese de:

- ✅ **Configuración completada** en `/setup.php`
- ✅ **Mínimo 5 productos** cargados con stock positivo
- ✅ **Precios definidos** (venta y costo)
- ✅ **Categorías configuradas** (opcional pero recomendado)
- ✅ **Clientes básicos** cargados (opcional)

## 🚨 Flujo Lógico del Sistema

```
INSTALACIÓN → PRODUCTOS → CLIENTES → VENTAS → INVENTARIO
    ↓           ↓          ↓        ↓         ↓
setup.php → productos.php → clientes.php → ventas.php → inventario.php
```

**NOTA**: El sistema sigue una lógica estricta donde no se pueden realizar ventas sin tener productos previamente cargados en el sistema.

## ✨ Características Principales

### **Multiempresa Completo**
- Aislamiento total de datos por empresa
- Estructura de archivos `files/empresa_X/`
- Configuración independiente por empresa

### **Sistema de Roles**
- **Admin**: Acceso completo a todos los módulos
- **Jefe**: Gestión de productos, ventas, finanzas, contabilidad
- **Vendedor**: Solo operaciones de venta

### **Procesamiento de Imágenes**
- Redimensionamiento automático
- Optimización de tamaño y calidad
- Renombrado con fecha/hora
- Miniaturas para visualización rápida

### **Integración Contable**
- Asientos automáticos desde ventas
- Libro Diario y Mayor
- Balance General y Estado de Resultados
- Cálculo automático de IVA

### **Punto de Venta Avanzado**
- Carrito de compras en tiempo real
- Múltiples métodos de pago
- Pantalla cliente con información de vuelto
- Confirmación visual de ventas exitosas
- Miniaturas de productos en tiempo real

### **Códigos de Barras EAN-13**
- Generación automática con checksum
- Soporte completo en productos y etiquetas
- Impresión de etiquetas PDF

### **Marketing Digital**
- Envío masivo WhatsApp
- Sistema de banners DLNA
- Gestión de promociones y cupones

## 🗄️ Base de Datos

- **Motor**: MySQL/MariaDB
- **Nombre**: `facturacion`
- **Características**: Multiempresa, transaccional, con índices optimizados

## 🔐 Seguridad

- Autenticación con SHA2-256
- Protección de sesión
- Validación de permisos por rol
- Aislamiento de datos por empresa

## 📱 Responsive Design

- Diseño moderno con Tailwind CSS
- Interfaz adaptable a dispositivos móviles
- Experiencia de usuario optimizada

## 🎯 Estado Actual

**✅ SISTEMA COMPLETO Y FUNCIONAL**

Todos los módulos implementados y probados:
- ✅ Login y autenticación multiempresa
- ✅ Dashboard con estadísticas en tiempo real
- ✅ Gestión completa de productos con imágenes
- ✅ Sistema de ventas con pantalla cliente
- ✅ Contabilidad automática integrada
- ✅ Finanzas y control de caja
- ✅ Marketing WhatsApp y banners
- ✅ Generación de etiquetas y códigos de barras
- ✅ Sistema de reportes
- ✅ Configuración centralizada

**🚀 LISTO PARA PRODUCCIÓN**
