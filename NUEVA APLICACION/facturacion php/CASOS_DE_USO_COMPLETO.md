# CASOS DE USO COMPLETOS PARA PRUEBA DEL SISTEMA DE FACTURACIÓN NEXUS POS

## 📋 PROCEDIMIENTO GENERAL DE PRUEBA

### Antes de Comenzar
1. **Limpiar caché del navegador**
2. **Verificar conexión a base de datos**
3. **Tener datos de prueba listos**
4. **Documentar cada paso con capturas**

### En Caso de Error
1. **Capturar pantalla del error**
2. **Copiar mensaje exacto**
3. **Verificar consola del navegador (F12)**
4. **Revisar logs del servidor si es posible**
5. **Reportar con: URL, acción, datos ingresados, error completo**

---

## 🚀 0. MÓDULO DE SETUP INICIAL

### 0.1 Primera instalación del sistema
- **URL**: `/setup.php`
- **Acción**: Configuración inicial desde base de datos vacía
- **Resultado esperado**: Redirección automática desde login.php si no hay usuarios
- **Errores comunes**:
  - No redirige a setup → Verificar consulta de usuarios
  - Error en conexión → Revisar configuración de base de datos

### 0.2 Configuración de empresa y administrador
- **Acción**: Completar formulario de setup inicial
- **Datos requeridos**:
  - Empresa: Nombre, CUIT, dirección, teléfono, email
  - Admin: Usuario, contraseña, email
- **Resultado esperado**: Empresa + usuario admin + configuraciones básicas creadas
- **Errores comunes**:
  - "Complete todos los campos obligatorios" → Verificar campos marcados con *
  - "Error en la configuración" → Revisar logs de SQL
  - CUIT inválido → Verificar formato XX-XXXXXXXX-X

### 0.3 Validación de datos del setup
- **Acción**: Probar configuración automática de CUIT
- **Resultado esperado**: Formato automático XX-XXXXXXXX-X
- **Errores comunes**:
  - Formato incorrecto → Revisar JavaScript de formateo
  - No permite guardar → Validar longitud mínima

### 0.4 Creación automática de configuraciones
- **Acción**: Verificar que se creen todas las tablas necesarias
- **Tablas creadas automáticamente**:
  - `empresas` - Datos de la empresa
  - `usuarios` - Usuario administrador
  - `nombre_negocio` - Configuración fiscal
  - `config_pagos` - Configuración de pasarelas
- **Resultado esperado**: Sistema listo para usar
- **Errores comunes**:
  - Faltan configuraciones → Revisar transacción SQL
  - No redirige al login → Verificar header location

### 0.5 Acceso posterior al setup
- **Acción**: Ingresar con credenciales creadas
- **Resultado esperado**: Acceso al dashboard con empresa configurada
- **Errores comunes**:
  - "Credenciales incorrectas" → Verificar que se haya guardado el usuario
  - No muestra empresa → Revisar relación usuario-empresa

---

## 🔐 1. MÓDULO DE AUTENTICACIÓN

### 1.1 Login de Usuario
- **URL**: `/login.php`
- **Acción**: Ingresar credenciales válidas
- **Datos**: Usuario: admin, Contraseña: contraseña_correcta
- **Resultado esperado**: Acceso al dashboard
- **Errores comunes**:
  - "Usuario o contraseña incorrectos" → Verificar credenciales
  - Página blanca → Revisar logs de PHP
  - Redirección infinita → Limpiar cookies

### 1.2 Login con credenciales incorrectas
- **Acción**: Ingresar datos inválidos
- **Resultado esperado**: Mensaje de error claro
- **Errores comunes**:
  - Mensaje confuso → Mejorar texto de error
  - No muestra error → Verificar JavaScript

### 1.3 Logout
- **Acción**: Cerrar sesión
- **URL**: `/logout.php`
- **Resultado esperado**: Redirección a login
- **Errores comunes**:
  - No cierra sesión → Verificar destrucción de sesión
  - Redirección incorrecta → Revisar header

---

## � 2. MÓDULO DE PRODUCTOS (CONFIGURACIÓN OBLIGATORIA)

### 2.1 Cargar productos iniciales
- **URL**: `/productos.php`
- **Acción**: Configurar catálogo de productos
- **Pasos**:
  1. Ingresar nombre del producto
  2. Establecer precio de venta
  3. Configurar precio de costo
  4. Definir stock inicial
  5. Asignar categoría (opcional)
  6. Agregar imagen (opcional)
- **Resultado esperado**: Producto disponible para ventas
- **Errores comunes**:
  - "No se puede guardar" → Verificar campos obligatorios
  - "Stock inválido" → Ingresar valor numérico válido
  - "Precio duplicado" → Verificar productos existentes

### 2.2 Configurar categorías
- **URL**: `/productos.php` (sección categorías)
- **Acción**: Organizar productos por categorías
- **Pasos**:
  1. Crear nueva categoría
  2. Asignar productos a categorías
  3. Configurar orden visual
- **Resultado esperado**: Catálogo organizado
- **Errores comunes**:
  - "Categoría duplicada" → Usar nombre único
  - "No se asigna" → Verificar selección de productos

### 2.3 Configurar stock y umbrales
- **URL**: `/configurar.php`
- **Acción**: Definir políticas de inventario
- **Pasos**:
  1. Establecer umbral de stock bajo
  2. Configurar alertas de reposición
  3. Definir políticas de fraccionamiento
- **Resultado esperado**: Sistema de inventario inteligente
- **Errores comunes**:
  - "Umbral inválido" → Ingresar valor numérico positivo
  - "No guarda configuración" → Verificar permisos

---

## 💰 3. MÓDULO DE VENTAS (OPERACIÓN)

### 3.1 Venta simple con efectivo
- **URL**: `/ventas.php`
- **Requisito**: Productos cargados en sistema (Sección 2)
- **Pasos**:
  1. Buscar producto por código o nombre
  2. Verificar stock disponible
  3. Agregar al carrito
  4. Confirmar venta
  5. Procesar pago con efectivo
- **Resultado esperado**: Ticket generado, stock actualizado
- **Errores comunes**:
  - "Producto no encontrado" → Verificar catálogo cargado
  - "Stock insuficiente" → Revisar inventario
  - "Error en cálculo" → Verificar precios y descuentos

### 3.2 Venta con método de pago mixto
- **Acción**: Pagar con múltiples métodos
- **Pasos**: Seleccionar métodos, distribuir monto total
- **Resultado esperado**: Venta registrada con desglose de pagos
- **Errores comunes**:
  - Montos no suman total → Validar distribución
  - Método no disponible → Configurar métodos habilitados

### 3.3 Venta con cliente asociado
- **Acción**: Asociar venta a cliente existente
- **Pasos**: Buscar cliente, seleccionar, confirmar venta
- **Resultado esperado**: Venta registrada, cuenta corriente actualizada
- **Errores comunes**:
  - "Cliente no encontrado" → Verificar base de clientes
  - "No actualiza cuenta" → Revisar configuración contable

### 3.4 Venta con descuentos y promociones
- **Acción**: Aplicar descuentos automáticos o manuales
- **Pasos**: Seleccionar productos, aplicar descuentos, confirmar
- **Resultado esperado**: Cálculo correcto con descuentos visibles
- **Errores comunes**:
  - "Descuento no aplica" → Verificar condiciones
  - "Descuento doble" → Validar lógica de promociones

---

## 🚨 4. FLUJO DE INICIO - Sitio Nuevo

### 4.1 Secuencia lógica de implementación
1. **INSTALACIÓN** → `/setup.php` (configuración inicial)
2. **PRODUCTOS** → `/productos.php` (cargar catálogo)
3. **CLIENTES** → `/clientes.php` (cargar base de clientes)
4. **VENTAS** → `/ventas.php` (comenzar operaciones)
5. **INVENTARIO** → `/inventario.php` (control de stock)

### 4.2 Verificación de sistema listo
- **Productos cargados**: Mínimo 5 productos básicos
- **Stock disponible**: Valores positivos en inventario
- **Precios configurados**: Venta y costo definidos
- **Categorías organizadas**: Estructura lógica

### 4.3 Primera venta de prueba
- **URL**: `/ventas.php`
- **Pasos**:
  1. Seleccionar producto con stock
  2. Agregar cantidad válida
  3. Procesar venta con efectivo
  4. Verificar ticket generado
- **Resultado esperado**: Sistema operativo y funcional

---

## 📦 3. MÓDULO DE PRODUCTOS

### 3.1 Crear nuevo producto
- **URL**: `/productos.php`
- **Acción**: Agregar producto completo
- **Datos**: Nombre, precio, stock, categoría, imagen, tags
- **Resultado esperado**: Producto guardado con imagen optimizada
- **Errores comunes**:
  - "Error al subir imagen" → Verificar permisos y tamaño
  - "Precio inválido" → Validar formato numérico
  - No guarda → Revisar validación de campos

### 3.2 Editar producto existente
- **Acción**: Modificar datos de producto
- **Pasos**: Buscar producto, editar, guardar cambios
- **Resultado esperado**: Cambios reflejados en sistema
- **Errores comunes**:
  - No actualiza → Verificar ID de producto
  - Pierde imagen → Revisar manejo de archivos

### 3.3 Eliminar producto
- **Acción**: Dar de baja producto
- **Resultado esperado**: Producto marcado como inactivo
- **Errores comunes**:
  - Elimina físicamente → Debe ser baja lógica
  - Error por ventas asociadas → Validar restricciones

### 3.4 Gestión de categorías
- **Acción**: Crear/editar categorías
- **Resultado esperado**: Categorías disponibles en productos
- **Errores comunes**:
  - No muestra categorías → Revisar consulta SQL
  - Categoría duplicada → Validar unicidad

---

## 👥 4. MÓDULO DE CLIENTES

### 4.1 Crear nuevo cliente
- **URL**: `/clientes.php`
- **Acción**: Agregar cliente completo
- **Datos**: Nombre, apellido, DNI, teléfono, email, dirección
- **Resultado esperado**: Cliente guardado con foto si corresponde
- **Errores comunes**:
  - "DNI duplicado" → Verificar validación
  - Error en foto → Revisar tamaño y formato

### 4.2 Editar cliente
- **Acción**: Modificar datos personales
- **Resultado esperado**: Datos actualizados correctamente
- **Errores comunes**:
  - No guarda cambios → Revisar formulario
  - Pierde foto → Validar manejo de imágenes

### 4.3 Consultar cuenta corriente
- **Acción**: Ver historial de compras y saldos
- **Resultado esperado**: Listado completo con saldos
- **Errores comunes**:
  - Saldo incorrecto → Revisar cálculo
  - No muestra historial → Verificar consultas

---

## 🏪 5. MÓDULO DE CONFIGURACIÓN

### 5.1 Configuración de empresa
- **URL**: `/configurar.php`
- **Acción**: Configurar datos básicos
- **Datos**: Nombre, CUIT, dirección, impuestos
- **Resultado esperado**: Configuración guardada
- **Errores comunes**:
  - No guarda → Verificar permisos de escritura
  - Datos inválidos → Validar formatos

### 5.2 Configuración AFIP
- **Acción**: Configurar certificados y claves
- **Resultado esperado**: Conexión con AFIP funcionando
- **Errores comunes**:
  - Error en certificado → Verificar formato PEM
  - Timeout de conexión → Revisar firewall

### 5.3 Configuración de pagos
- **Acción**: Configurar Mercado Pago, Modo, etc.
- **Resultado esperado**: Pasarelas de pago activas
- **Errores comunes**:
  - API keys inválidas → Verificar credenciales
  - Webhook no responde → Revisar URL

### 5.4 Configuración de rutas
- **Acción**: Generar rutas de archivos
- **Resultado esperado**: Estructura de archivos creada
- **Errores comunes**:
  - Permisos denegados → Verificar chmod
  - Rutas incorrectas → Revisar configuración

### 5.5 Configuración de inventario
- **URL**: `/configurar.php` (Tab INVENTARIO)
- **Acción**: Configurar umbrales de stock bajo
- **Datos**: Stock bajo para productos enteros y por peso
- **Resultado esperado**: Umbrales configurados globalmente
- **Errores comunes**:
  - No guarda → Verificar permisos de escritura
  - No se aplica → Revisar carga en inventario.php

---

## 📊 6. MÓDULO DE REPORTES

### 6.1 Reporte de ventas
- **URL**: `/reportes.php`
- **Acción**: Generar reporte de ventas
- **Filtros**: Fecha, tipo de reporte
- **Resultado esperado**: Datos correctos con gráficos
- **Errores comunes**:
  - No muestra datos → Verificar filtros de fecha
  - Gráficos vacíos → Revisar JavaScript
  - Error en exportación CSV → Validar formato

### 6.2 Reporte de productos
- **Acción**: Ver productos más vendidos
- **Resultado esperado**: Ranking correcto con cantidades
- **Errores comunes**:
  - Orden incorrecto → Revisar ORDER BY
  - Cantidades erróneas → Verificar GROUP BY

### 6.3 Reporte de clientes
- **Acción**: Ver compras por cliente
- **Resultado esperado**: Listado con montos totales
- **Errores comunes**:
  - Clientes sin datos → Verificar JOIN
  - Totales incorrectos → Revisar SUM

### 6.4 Reporte con gráficos
- **Acción**: Visualizar datos en gráficos
- **Gráficos disponibles**:
  - 📦 Productos más vendidos (barras)
  - 💰 Ventas por día (líneas)
  - 💳 Métodos de pago (dona)
  - 📋 Ventas por categoría (torta)
- **Resultado esperado**: Gráficos interactivos con datos reales
- **Errores comunes**:
  - Gráficos vacíos → Verificar datos PHP
  - Error de Chart.js → Revisar consola
  - Colores incorrectos → Validar array de colores

---

## 📋 7. MÓDULO DE CONTABILIDAD

### 7.1 Plan de cuentas
- **URL**: `/contabilidad.php`
- **Acción**: Configurar plan contable
- **Resultado esperado**: Cuentas configuradas
- **Errores comunes**:
  - No guarda cuentas → Verificar SQL
  - Desbalance contable → Validar débitos/créditos

### 7.2 Asientos automáticos
- **Acción**: Generar asientos de ventas
- **Resultado esperado**: Asientos creados correctamente
- **Errores comunes**:
  - No genera asientos → Revisar triggers
  - Montos incorrectos → Validar cálculos

---

## 📱 8. MÓDULO DE WHATSAPP

### 8.1 Envío de mensajes
- **URL**: `/whatsapp.php`
- **Acción**: Enviar mensaje a cliente
- **Resultado esperado**: Mensaje enviado
- **Errores comunes**:
  - No envía → Verificar API key
  - Formato inválido → Validar número

### 8.2 Envío masivo
- **Acción**: Enviar a múltiples clientes
- **Resultado esperado**: Mensajes enviados con reporte
- **Errores comunes**:
  - Límite excedido → Verificar cuotas API
  - Falla parcial → Revisar manejo de errores

---

## 📹 9. MÓDULO DE CÁMARAS

### 9.1 Configuración de cámaras
- **URL**: `/camaras.php`
- **Acción**: Agregar/configurar cámara
- **Resultado esperado**: Cámara conectada y visible
- **Errores comunes**:
  - No detecta cámara → Verificar IP y puerto
  - Sin imagen → Revisar formato de video

### 9.2 Grabación continua
- **Acción**: Iniciar grabación
- **Resultado esperado**: Video siendo grabado
- **Errores comunes**:
  - No graba → Verificar espacio en disco
  - Calidad baja → Ajustar configuración

---

## 🤖 10. MÓDULO DE RECONOCIMIENTO FACIAL

### 10.1 Registro de rostros
- **URL**: `/reconocimiento_facial.php`
- **Acción**: Capturar y registrar rostro
- **Resultado esperado**: Rostro guardado en base
- **Errores comunes**:
  - No detecta rostro → Revisar iluminación
  - Error en entrenamiento → Verificar modelo

### 10.2 Detección en tiempo real
- **Acción**: Activar reconocimiento
- **Resultado esperado**: Detección y alertas
- **Errores comunes**:
  - Falsos positivos → Ajustar umbral
  - No detecta → Revisar cámara

---

## 🖨️ 11. MÓDULO DE TICKETS

### 11.1 Impresión de tickets
- **URL**: `/tickets.php`
- **Acción**: Configurar impresora
- **Resultado esperado**: Ticket impreso correctamente
- **Errores comunes**:
  - No imprime → Verificar conexión USB/IP
  - Formato incorrecto → Ajustar plantilla

### 11.2 Configuración de impresoras
- **Acción**: Agregar impresora térmica
- **Resultado esperado**: Impresora reconocida
- **Errores comunes**:
  - Driver no encontrado → Instalar drivers
  - Puerto ocupado → Liberar puerto

---

## 📢 12. MÓDULO DE AVISOS

### 12.1 Crear aviso
- **URL**: `/avisos.php`
- **Acción**: Crear nuevo aviso
- **Datos**: Título, descripción, imagen, expiración
- **Resultado esperado**: Aviso guardado y visible
- **Errores comunes**:
  - No guarda imagen → Verificar tamaño
  - No muestra en dashboard → Revisar consulta

### 12.2 Gestión de avisos
- **Acción**: Editar/eliminar avisos
- **Resultado esperado**: Cambios aplicados
- **Errores comunes**:
  - No elimina → Verificar permisos
  - Pierde formato → Validar HTML

---

## 🎯 13. MÓDULO DE PROMOCIONES

### 13.1 Crear promoción
- **URL**: `/promociones.php`
- **Acción**: Configurar promoción
- **Datos**: Productos, descuento, vigencia
- **Resultado esperado**: Promoción activa
- **Errores comunes**:
  - No se aplica → Verificar fechas
  - Descuento incorrecto → Revisar cálculo

### 13.2 Promociones por volumen
- **Acción**: Configurar descuentos por cantidad
- **Resultado esperado**: Descuentos automáticos
- **Errores comunes**:
  - No activa → Revisar condiciones
  - Acumula descuentos → Validar lógica

---

## 🏷️ 14. MÓDULO DE ETIQUETAS

### 14.1 Generar etiquetas
- **URL**: `/etiquetas.php`
- **Acción**: Imprimir etiquetas de productos
- **Resultado esperado**: Etiquetas impresas
- **Errores comunes**:
  - Formato incorrecto → Ajustar plantilla
  - No imprime → Verificar impresora

---

## � 15. MÓDULO DE IMÁGENES

### 15.1 Subir imagen de producto
- **URL**: `/imagenes.php`
- **Acción**: Cargar imagen para producto
- **Datos**: Archivo de imagen (JPG, PNG, WebP)
- **Resultado esperado**: Imagen optimizada y guardada
- **Errores comunes**:
  - "Archivo muy grande" → Verificar límite de tamaño
  - "Formato no soportado" → Validar extensión
  - No se guarda → Revisar permisos de directorio

### 15.2 Gestión de galería
- **Acción**: Ver y eliminar imágenes
- **Resultado esperado**: Galería organizada
- **Errores comunes**:
  - No muestra imágenes → Verificar ruta
  - No elimina → Validar permisos

---

## � 16. MÓDULO DE CONFIGURACIÓN DE URLs

### 16.1 Configuración del sistema de URLs
- **URL**: `/configuracion_urls.php`
- **Acción**: Configurar modo single vs multiempresa
- **Modos disponibles**:
  - Single: Una sola empresa
  - Multiempresa (subdominios): empresa.nexuspos.com
  - Multiempresa (prefijos): nexuspos.com/empresa
  - Multiempresa (parámetros): nexuspos.com?empresa=nombre
- **Resultado esperado**: Configuración guardada y activa
- **Errores comunes**:
  - No guarda configuración → Verificar permisos de escritura
  - Modo no aplica → Revisar .htaccess configuración
  - URLs no funcionan → Verificar configuración Apache

### 16.2 URLs personalizadas por empresa
- **Acción**: Asignar URL personalizada a cada empresa
- **Datos**: URL única (3-30 caracteres, alfanumérico con guiones)
- **Resultado esperado**: URL personalizada funcionando
- **Errores comunes**:
  - "URL inválida" → Verificar formato (solo letras, números, guiones)
  - "URL no disponible" → Ya está en uso por otra empresa
  - No funciona → Revisar configuración DNS (subdominios)

### 16.3 Validación de URLs personalizadas
- **Acción**: Probar diferentes formatos de URLs
- **Formatos válidos**: mi-empresa, empresa123, mi_empresa_2024
- **Formatos inválidos**: mi empresa, empresa@123, Mí Empresa
- **Resultado esperado**: Validación correcta con mensajes claros
- **Errores comunes**:
  - No valida longitud → Revisar regla de 3-30 caracteres
  - Acepta caracteres especiales → Revisar expresión regular

### 16.4 Detección automática de empresa por URL
- **Acción**: Acceder al sistema usando diferentes URLs
- **Modo subdominio**: empresa1.nexuspos.com → Detecta empresa1
- **Modo prefijo**: nexuspos.com/empresa1 → Detecta empresa1
- **Modo parámetro**: nexuspos.com?empresa=empresa1 → Detecta empresa1
- **Resultado esperado**: Sistema detecta empresa correcta automáticamente
- **Errores comunes**:
  - No detecta empresa → Verificar .htaccess y URLManager
  - Detecta empresa incorrecta → Revisar coincidencia de URLs
  - Redirección infinita → Verificar bucle de detección

### 16.5 Transición entre modos
- **Acción**: Cambiar de single a multiempresa o viceversa
- **Resultado esperado**: Sistema adapta URLs automáticamente
- **Errores comunes**:
  - Pérdida de acceso → Verificar configuración de URLs
  - URLs antiguas no funcionan → Actualizar bookmarks
  - Confusión en usuarios → Comunicar cambios

### 16.6 Configuración de .htaccess
- **Acción**: Activar modo de URLs deseado
- **Opciones**:
  - Subdominios: Descomentar sección de subdominios
  - Prefijos: Descomentar sección de prefijos
  - Parámetros: Usar por defecto
- **Resultado esperado**: Rewrite de URLs funcionando
- **Errores comunes**:
  - .htaccess no funciona → Verificar mod_rewrite de Apache
  - Error 500 → Sintaxis incorrecta en .htaccess
  - No reescribe URLs → Verificar AllowOverride

---

## 📦 18. MÓDULO DE INVENTARIO / STOCK

### 18.1 Configuración de umbrales de stock (Global)
- **URL**: `/configurar.php` (Tab INVENTARIO)
- **Acción**: Configurar umbrales de stock bajo por tipo de producto
- **Datos**: 
  - Stock bajo (productos enteros): Ej: 5 unidades
  - Stock bajo (productos por peso): Ej: 1.000 kg
- **Resultado esperado**: Umbrales configurados y aplicados automáticamente
- **Errores comunes**:
  - No guarda configuración → Verificar permisos de escritura
  - No se aplica → Revisar carga de configuración en inventario.php

### 18.2 Configuración de stock por producto
- **URL**: `/inventario.php`
- **Acción**: Configurar stock actual, mínimo y máximo individual
- **Datos**: Stock actual, stock mínimo, stock máximo, ubicación
- **Prioridad**: Si el producto tiene stock mínimo configurado, usa ese valor. Si no, usa la configuración global.
- **Resultado esperado**: Stock configurado y alertas generadas si corresponde
- **Errores comunes**:
  - No guarda stock → Verificar relación producto-empresa
  - Alertas no se generan → Revisar lógica de comparación
  - Stock negativo → Validar entradas

### 18.3 Registro de movimientos de inventario
- **Acción**: Registrar entrada, salida o ajuste de stock
- **Tipos**: Entrada, Salida, Ajuste, Transferencia
- **Resultado esperado**: Movimiento registrado y stock actualizado
- **Errores comunes**:
  - Stock no se actualiza → Revisar cálculo de signos
  - Movimiento duplicado → Verificar validación
  - Cantidad inválida → Validar números positivos

### 18.4 Visualización de inventario con imágenes
- **Acción**: Ver listado de productos con imágenes
- **Características**:
  - Imagen del producto (si tiene)
  - Stock formateado según tipo (entero vs decimal)
  - Ordenamiento por columnas (nombre, código, stock, precio)
  - Estado de stock (CRÍTICO, BAJO, OK)
- **Resultado esperado**: Listado visual y ordenado
- **Errores comunes**:
  - Imagen no carga → Verificar ruta
  - Stock mal formateado → Revisar función formatearStock()
  - Ordenamiento no funciona → Verificar parámetros URL

### 18.5 Alertas de stock bajo
- **Acción**: Verificar alertas de stock crítico o bajo
- **Tipos de alerta**: Crítico (sin stock), Bajo (bajo mínimo), Sobre (sobre máximo)
- **Resultado esperado**: Alertas visibles y marcables como leídas
- **Errores comunes**:
  - Alertas no aparecen → Revisar umbrales de stock
  - No se marcan leídas → Verificar actualización de estado
  - Alertas duplicadas → Revisar lógica de generación

### 18.6 Consulta de movimientos históricos
- **Acción**: Ver historial de movimientos de inventario
- **Resultado esperado**: Lista completa con filtros por fecha y tipo
- **Errores comunes**:
  - Historial incompleto → Revisar registro de movimientos
  - Fechas incorrectas → Validar timezone
  - Usuario no aparece → Verificar relación usuario-movimiento

### 18.7 Ajuste de inventario
- **Acción**: Corregir stock por conteo físico o merma
- **Resultado esperado**: Stock ajustado con motivo documentado
- **Errores comunes**:
  - Ajuste no refleja → Revisar cálculo de diferencia
  - Sin motivo → Validar campo obligatorio
  - Stock negativo → Prevenir ajustes inválidos

---

## 💵 19. MÓDULO DE CAJA / DIARIO

### 19.1 Apertura de caja
- **URL**: `/caja.php`
- **Acción**: Abrir nueva caja con monto inicial
- **Datos**: Monto de apertura, observaciones
- **Resultado esperado**: Caja abierta y movimiento registrado
- **Errores comunes**:
  - No permite abrir → Verificar si hay caja abierta
  - Monto inválido → Validar números positivos
  - Número de caja duplicado → Revisar secuencia

### 19.2 Registro de movimientos de caja
- **Acción**: Registrar gastos, ingresos, retiros, depósitos
- **Tipos**: Gasto, Ingreso, Retiro, Depósito, Ajuste
- **Datos**: Monto, método de pago, descripción
- **Resultado esperado**: Movimiento registrado y total actualizado
- **Errores comunes**:
  - Total no se actualiza → Revisar cálculo de sumas
  - Movimiento sin caja → Verificar caja abierta
  - Monto negativo → Validar entradas

### 19.3 Cierre de caja
- **Acción**: Cerrar caja con arqueo de efectivo
- **Datos**: Monto de cierre, observaciones
- **Resultado esperado**: Caja cerrada con cálculo de diferencia
- **Errores comunes**:
  - Diferencia incorrecta → Revisar cálculo de esperado
  - No permite cerrar → Verificar estado de caja
  - Diferencia no se calcula → Validar lógica

### 19.4 Arqueo de caja
- **Acción**: Comparar monto real vs esperado
- **Resultado esperado**: Diferencia calculada y reportada
- **Errores comunes**:
  - Diferencia no coincide → Revisar movimientos no registrados
  - Falta movimiento → Verificar registro de ventas
  - Cálculo erróneo → Validar sumatoria

### 19.5 Consulta de cajas cerradas
- **Acción**: Ver historial de cajas cerradas
- **Resultado esperado**: Lista con diferencias y totales
- **Errores comunes**:
  - Historial incompleto → Revisar cierre de cajas
  - Diferencias no muestran → Verificar cálculo al cierre
  - Usuario incorrecto → Validar relación usuario-caja

---

## 📈 20. MÓDULO DE FINANZAS

### 20.1 Registrar gasto
- **URL**: `/finanzas.php`
- **Acción**: Ingresar gasto
- **Datos**: Categoría, monto, descripción
- **Resultado esperado**: Gasto registrado
- **Errores comunes**:
  - No guarda → Verificar validación
  - Categoría inválida → Revisar lista

### 20.2 Registrar ingreso
- **Acción**: Ingresar ingreso extra
- **Resultado esperado**: Ingreso registrado
- **Errores comunes**:
  - No actualiza balance → Revisar cálculo
  - Fecha incorrecta → Validar formato

---

## 🔧 PROCEDIMIENTOS DE MANEJO DE ERRORES

### Errores de Base de Datos
```
Error: SQLSTATE[HY000] [2002] Connection refused
Solución: Verificar que MySQL esté corriendo
Comando: sudo systemctl status mysql
```

### Errores de Permisos
```
Error: Permission denied
Solución: Ajustar permisos de archivos/directorios
Comando: sudo chown -R www-data:www-data /ruta/a/archivos
```

### Errores de Memoria
```
Error: Fatal error: Allowed memory size exhausted
Solución: Aumentar límite de memoria
Comando: Editar php.ini memory_limit = 256M
```

### Errores de Tiempo
```
Error: Maximum execution time exceeded
Solución: Aumentar tiempo de ejecución
Comando: Editar php.ini max_execution_time = 300
```

### Errores de Reportes
```
Error: Gráficos no se muestran
Solución: Verificar Chart.js y datos JSON
Comando: Revisar consola del navegador (F12)
```

### Errores de Fechas
```
Error: Filtro de fechas no funciona
Solución: Verificar formato datetime en consultas
Comando: Agregar hora a filtros BETWEEN
```

### Errores de Setup Inicial
```
Error: No redirige a setup.php
Solución: Verificar consulta de usuarios en login.php
Comando: SELECT COUNT(*) as total FROM usuarios
```

### Errores de Instalación Limpia
```
Error: No se puede crear empresa sin usuarios
Solución: Usar setup.php para configuración inicial
Comando: Acceder a login.php (redirige automáticamente)
```

### Errores de URLs Multiempresa
```
Error: Las URLs personalizadas no funcionan
Solución: Verificar configuración de .htaccess y DNS
Comando: a2enmod rewrite && systemctl restart apache2
```

### Errores de Configuración de URLs
```
Error: No se detecta la empresa por URL
Solución: Revisar tabla empresas y campo url_personalizada
Comando: SELECT id, nombre, url_personalizada FROM empresas WHERE activo = 1
```

---

## 📋 CHECKLIST DE PRUEBA COMPLETO

### Antes de Iniciar
- [ ] Base de datos funcionando
- [ ] Permisos de archivos correctos
- [ ] Servicios web activos
- [ ] Datos de prueba cargados

### Durante la Prueba
- [ ] Cada módulo probado
- [ ] Todos los formularios funcionan
- [ ] Validaciones activas
- [ ] Errores manejados correctamente
- [ ] Redirecciones funcionan
- [ ] Imágenes se suben correctamente
- [ ] Reportes generan datos
- [ ] Gráficos se muestran
- [ ] Exportaciones funcionan
- [ ] Setup inicial funciona (instalación limpia)
- [ ] Sistema de URLs funciona (single/multiempresa)
- [ ] URLs personalizadas se configuran correctamente

### Después de la Prueba
- [ ] Logs revisados
- [ ] Performance aceptable
- [ ] Seguridad validada
- [ ] Documentación actualizada

---

## 🚀 COMANDOS ÚTILES PARA DEPURACIÓN

### Ver logs de errores
```bash
tail -f /var/log/apache2/error.log
tail -f /var/log/php_errors.log
```

### Reiniciar servicios
```bash
sudo systemctl restart apache2
sudo systemctl restart mysql
```

### Verificar permisos
```bash
ls -la /ruta/a/archivos
sudo chown -R www-data:www-data /ruta/a/archivos
sudo chmod -R 755 /ruta/a/archivos
```

### Limpiar caché
```bash
sudo rm -rf /var/cache/apache2/*
sudo systemctl restart apache2
```

### Verificar espacio en disco
```bash
df -h
du -sh /ruta/a/archivos
```

---

## 📞 SOPORTE Y REPORTES

### Para Reportar Errores
1. **Capturar pantalla completa**
2. **Copiar mensaje exacto**
3. **Indicar URL completa**
4. **Describir pasos realizados**
5. **Incluir datos de prueba usados**
6. **Adjamar captura de consola (F12)**

### Información del Sistema
- **Versión PHP**: `php -v`
- **Versión MySQL**: `mysql --version`
- **Navegador**: Chrome/Firefox/Safari + versión
- **Sistema Operativo**: Linux/Windows/Mac + versión

### Errores Comunes y Soluciones

#### Error: "No se pueden mostrar los gráficos"
- **Causa**: Chart.js no cargado o datos vacíos
- **Solución**: 
  1. Verificar conexión a internet
  2. Revisar consola del navegador
  3. Validar que existan datos en el período

#### Error: "El filtro de fechas no funciona"
- **Causa**: Formato de fecha incorrecto
- **Solución**:
  1. Usar formato YYYY-MM-DD
  2. Verificar que la tabla tenga datetime
  3. Agregar hora: 00:00:00 y 23:59:59

#### Error: "No se guardan los cambios en configuración"
- **Causa**: Permisos de escritura
- **Solución**:
  1. Verificar permisos del directorio
  2. Revisar configuración de PHP
  3. Validar que los datos lleguen al servidor

---

## 🎯 FLUJO DE PRUEBA RECOMENDADO

### Día 1: Módulos Básicos
1. **Setup Inicial** (Instalación limpia)
2. **Autenticación** (Login/Logout)
3. **Dashboard** (Visualización general)
4. **Ventas** (Proceso completo)
5. **Productos** (CRUD básico)
6. **Clientes** (Gestión completa)

### Día 2: Módulos Avanzados
1. **Configuración** (Todos los tabs incluyendo Inventario)
2. **Configuración de URLs** (Single/Multiempresa)
3. **Reportes** (Con gráficos)
4. **Finanzas** (Gastos e ingresos)
5. **Inventario** (Stock, alertas, umbrales globales)
6. **Caja** (Apertura/cierre y movimientos)
7. **Contabilidad** (Plan de cuentas)

### Día 3: Módulos Especializados
1. **WhatsApp** (Envío de mensajes)
2. **Cámaras** (Configuración)
3. **Reconocimiento Facial** (Registro)
4. **Tickets** (Impresión)
5. **Promociones** (Descuentos)

### Día 4: Integración y Stress
1. **Proceso completo de venta**
2. **Múltiples usuarios simultáneos**
3. **Exportación de datos**
4. **Performance del sistema**

---

## 📊 MÉTRICAS DE PRUEBA

### Rendimiento Esperado
- **Tiempo de carga**: < 3 segundos
- **Respuesta API**: < 500ms
- **Uso de memoria**: < 128MB
- **Concurrencia**: 10 usuarios simultáneos

### Funcionalidad Crítica
- **Proceso de venta**: 99.9% uptime
- **Generación de tickets**: Siempre funcional
- **Cálculos financieros**: 100% precisión
- **Integridad de datos**: Sin corrupción

---

*Este documento debe ser actualizado con cada nueva versión del sistema*
*Última actualización: $(date)*
