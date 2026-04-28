# CASOS DE USO PARA PRUEBA DEL SISTEMA DE FACTURACIÓN

## 1. MÓDULO DE AUTENTICACIÓN

### 1.1 Login de Usuario
- **Acción**: Ingresar credenciales válidas
- **URL**: `/login.php`
- **Datos**: Usuario y contraseña correctos
- **Resultado esperado**: Acceso al dashboard según rol

### 1.2 Login con credenciales incorrectas
- **Acción**: Ingresar credenciales inválidas
- **Resultado esperado**: Mensaje de error, no acceso

### 1.3 Logout
- **Acción**: Cerrar sesión
- **Resultado esperado**: Redirección a login

## 2. MÓDULO DE VENTAS

### 2.1 Venta simple con efectivo
- **Acción**: Agregar productos, pagar en efectivo
- **Pasos**:
  1. Buscar producto por código o nombre
  2. Agregar al carrito
  3. Confirmar venta
  4. Pagar con efectivo
- **Resultado esperado**: Ticket generado, stock actualizado, asiento contable creado

### 2.2 Venta con método de pago mixto
- **Acción**: Pagar parte en efectivo, parte con tarjeta
- **Resultado esperado**: Venta registrada con múltiples métodos de pago

### 2.3 Venta con cliente asociado
- **Acción**: Seleccionar cliente durante la venta
- **Resultado esperado**: Venta asociada al cliente, actualización de cuenta corriente

### 2.4 Venta con descuento
- **Acción**: Aplicar descuento a productos o total
- **Resultado esperado**: Cálculo correcto de descuentos

## 3. MÓDULO DE PRODUCTOS

### 3.1 Crear nuevo producto
- **Acción**: Agregar producto con imagen
- **URL**: `/productos.php`
- **Datos**: Nombre, precio, stock, categoría, imagen
- **Resultado esperado**: Producto guardado con imagen optimizada

### 3.2 Editar producto existente
- **Acción**: Modificar datos de producto
- **Resultado esperado**: Cambios guardados

### 3.3 Bajar stock de producto
- **Acción**: Reducir stock manualmente
- **Resultado esperado**: Stock actualizado

### 3.4 Buscar producto
- **Acción**: Buscar por nombre o código
- **Resultado esperado**: Resultados relevantes

## 4. MÓDULO DE CLIENTES

### 4.1 Crear nuevo cliente
- **Acción**: Agregar cliente con foto
- **URL**: `/clientes.php`
- **Datos**: Nombre, apellido, email, teléfono, foto
- **Resultado esperado**: Cliente guardado con foto optimizada

### 4.2 Editar cliente
- **Acción**: Modificar datos de cliente
- **Resultado esperado**: Cambios guardados

### 4.3 Ver cuenta corriente
- **Acción**: Consultar saldo y movimientos
- **Resultado esperado**: Historial completo

## 5. MÓDULO DE PRESUPUESTOS

### 5.1 Crear presupuesto
- **Acción**: Generar presupuesto para cliente
- **URL**: `/presupuestos.php`
- **Pasos**:
  1. Seleccionar cliente
  2. Agregar productos
  3. Configurar vigencia
  4. Generar PDF
- **Resultado esperado**: Presupuesto guardado, PDF generado

### 5.2 Convertir presupuesto a venta
- **Acción**: Desde presupuesto, crear venta
- **Resultado esperado**: Venta generada desde presupuesto

### 5.3 Enviar presupuesto por email
- **Acción**: Enviar PDF por email
- **Resultado esperado**: Email enviado con presupuesto adjunto

### 5.4 Enviar presupuesto por WhatsApp
- **Acción**: Enviar PDF por WhatsApp
- **Resultado esperado**: Mensaje enviado con PDF

## 6. MÓDULO DE IMÁGENES CON IA

### 6.1 Generar imagen con IA
- **Acción**: Crear imagen para producto con IA
- **URL**: `/imagenes.php`
- **Pasos**:
  1. Seleccionar producto
  2. Elegir estilo
  3. Generar prompt
  4. Obtener imagen de IA
- **Resultado esperado**: Imagen generada y guardada

### 6.2 Enviar imagen a banners
- **Acción**: Desde imágenes, enviar a banners
- **Resultado esperado**: Redirección a banners con datos precargados

### 6.3 Abrir IA externa
- **Acción**: Abrir interfaz externa de IA
- **Resultado esperado**: Nueva ventana con URL del proveedor

## 7. MÓDULO DE BANNERS

### 7.1 Crear banner desde imagen local
- **Acción**: Subir imagen y configurar banner
- **URL**: `/banners.php`
- **Resultado esperado**: Banner activo para DLNA

### 7.2 Crear banner desde IA
- **Acción**: Recibir imagen desde imagenes.php
- **Resultado esperado**: Banner creado con imagen de IA

### 7.3 Crear banner desde aviso
- **Acción**: Recibir imagen desde avisos.php
- **Resultado esperado**: Banner creado con imagen de aviso

### 7.4 Configurar DLNA
- **Acción**: Configurar servidor DLNA
- **Resultado esperado**: Banners enviados a pantallas DLNA

## 8. MÓDULO DE AVISOS

### 8.1 Crear aviso con IA
- **Acción**: Generar aviso con imagen de IA
- **URL**: `/avisos.php`
- **Pasos**:
  1. Completar título y descripción
  2. Seleccionar tipo (perdido/encontrado/mascota/servicio/otro)
  3. Escribir prompt para IA
  4. Seleccionar proveedor IA
  5. Generar imagen
- **Resultado esperado**: Aviso guardado con imagen generada

### 8.2 Crear aviso sin IA
- **Acción**: Crear aviso solo con texto
- **Resultado esperado**: Aviso guardado sin imagen

### 8.3 Enviar aviso a banners
- **Acción**: Desde aviso, enviar a banners
- **Resultado esperado**: Redirección a banners con datos del aviso

### 8.4 Editar aviso
- **Acción**: Modificar datos del aviso
- **Resultado esperado**: Cambios guardados

### 8.5 Listar avisos
- **Acción**: Ver todos los avisos creados
- **Resultado esperado**: Grid visual con todos los avisos

## 9. MÓDULO DE WHATSAPP

### 9.1 Configurar WhatsApp
- **Acción**: Configurar API de WhatsApp
- **URL**: `/configurar.php` (tab WhatsApp)
- **Resultado esperado**: Configuración guardada

### 9.2 Enviar mensaje de prueba
- **Acción**: Enviar mensaje a número de prueba
- **Resultado esperado**: Mensaje enviado

### 9.3 Enviar presupuesto por WhatsApp
- **Acción**: Desde presupuestos, enviar por WhatsApp
- **Resultado esperado**: PDF enviado por WhatsApp

## 10. MÓDULO DE ETIQUETAS

### 10.1 Generar etiquetas de productos
- **Acción**: Crear etiquetas con código de barras
- **URL**: `/etiquetas.php`
- **Pasos**:
  1. Seleccionar productos
  2. Configurar cantidad
  3. Generar PDF
- **Resultado esperado**: PDF con etiquetas generado

### 10.2 Imprimir etiquetas
- **Acción**: Enviar a impresora
- **Resultado esperado**: Etiquetas impresas

## 11. MÓDULO DE CONTABILIDAD

### 11.1 Ver libro diario
- **Acción**: Consultar movimientos del día
- **URL**: `/contabilidad.php`
- **Resultado esperado**: Listado de asientos contables

### 11.2 Ver mayor
- **Acción**: Consultar mayor de cuentas
- **Resultado esperado**: Saldos y movimientos por cuenta

### 11.3 Generar balance
- **Acción**: Ver balance general
- **Resultado esperado**: Balance con activos y pasivos

### 11.4 Ver estado de resultados
- **Acción**: Consultar ganancias y pérdidas
- **Resultado esperado**: Estado de resultados completo

## 12. MÓDULO DE FINANZAS

### 12.1 Registrar movimiento
- **Acción**: Agregar ingreso o egreso
- **URL**: `/finanzas.php`
- **Resultado esperado**: Movimiento registrado, asiento contable creado

### 12.2 Ver flujo de caja
- **Acción**: Consultar movimientos del período
- **Resultado esperado**: Resumen de flujo de caja

### 12.3 Conciliación bancaria
- **Acción**: Conciliar movimientos
- **Resultado esperado**: Movimientos conciliados

## 13. MÓDULO DE CÁMARAS

### 13.1 Configurar cámara
- **Acción**: Agregar cámara IP
- **URL**: `/camaras.php`
- **Datos**: IP, usuario, contraseña, nombre
- **Resultado esperado**: Cámara configurada

### 13.2 Ver streaming en vivo
- **Acción**: Ver video de cámara
- **Resultado esperado**: Streaming en tiempo real

### 13.3 Grabar evento
- **Acción**: Iniciar grabación manual
- **Resultado esperado**: Video guardado

## 14. MÓDULO DE RECONOCIMIENTO FACIAL

### 14.1 Configurar reconocimiento facial
- **Acción**: Configurar umbrales y alertas
- **URL**: `/configurar.php` (tab Reconocimiento Facial)
- **Resultado esperado**: Configuración guardada

### 14.2 Registrar perfil facial
- **Acción**: Asociar foto de cliente a perfil
- **Resultado esperado**: Perfil facial registrado

### 14.3 Ver detecciones
- **Acción**: Consultar log de detecciones
- **URL**: `/reconocimiento_facial.php`
- **Resultado esperado**: Historial de detecciones

### 14.4 Probar daemon
- **Acción**: Verificar funcionamiento del daemon
- **Comando**: `sudo systemctl status face-recognition-daemon`
- **Resultado esperado**: Servicio activo

## 15. MÓDULO DE CONFIGURACIÓN

### 15.1 Configurar empresa
- **Acción**: Configurar datos del negocio
- **URL**: `/configurar.php`
- **Resultado esperado**: Configuración guardada

### 15.2 Configurar IA
- **Acción**: Agregar proveedores de IA
- **Resultado esperado**: Proveedores configurados

### 15.3 Configurar DLNA
- **Acción**: Configurar servidor DLNA
- **Resultado esperado**: DLNA configurado

### 15.4 Configurar tickets
- **Acción**: Configurar impresora de tickets
- **Resultado esperado**: Impresora detectada y configurada

## 16. FLUJOS INTEGRADOS

### 16.1 Flujo completo de venta
- **Secuencia**: Login → Venta → Pago → Ticket → Asiento contable
- **Resultado esperado**: Todo el proceso integrado funcionando

### 16.2 Flujo de fidelización
- **Secuencia**: Cliente → Reconocimiento facial → Alerta de bienvenida → Venta personalizada
- **Resultado esperado**: Experiencia personalizada

### 16.3 Flujo de marketing
- **Secuencia**: Producto → Imagen IA → Banner → DLNA → Visualización
- **Resultado esperado**: Marketing visual automatizado

### 16.4 Flujo de avisos
- **Secuencia**: Aviso → Imagen IA → Banner → DLNA → Publicación
- **Resultado esperado**: Aviso publicado automáticamente

## 17. CASOS DE ERROR

### 17.1 Sin conexión a base de datos
- **Acción**: Desconectar servidor de base de datos
- **Resultado esperado**: Mensaje de error amigable

### 17.2 Sin stock
- **Acción**: Intentar vender producto sin stock
- **Resultado esperado**: Alerta de stock insuficiente

### 17.3 IA no disponible
- **Acción**: Probar generación con API caída
- **Resultado esperado**: Mensaje de error, reintento

### 17.4 DLNA no conectado
- **Acción**: Enviar banner con servidor DLNA caído
- **Resultado esperado**: Error de conexión, cola de envío

## 18. PRUEBAS DE RENDIMIENTO

### 18.1 Múltiples ventas simultáneas
- **Acción**: Realizar 10 ventas concurrentes
- **Resultado esperado**: Sin conflictos de stock ni asientos

### 18.2 Gran cantidad de productos
- **Acción**: Cargar 1000+ productos
- **Resultado esperado**: Búsquedas rápidas, sin lentitud

### 18.3 Streaming múltiple
- **Acción**: Ver 4 cámaras simultáneamente
- **Resultado esperado**: Streaming fluido en todas

### 18.4 Reconocimiento facial continuo
- **Acción**: Daemon corriendo 24 horas
- **Resultado esperado**: Sin consumos excesivos de memoria

## 19. PRUEBAS DE SEGURIDAD

### 19.1 Acceso no autorizado
- **Acción**: Intentar acceder sin login
- **Resultado esperado**: Redirección a login

### 19.2 Inyección SQL
- **Acción**: Intentar inyección en campos de búsqueda
- **Resultado esperado**: Bloqueo, sin ejecución

### 19.3 XSS
- **Acción**: Intentar inyectar scripts en campos
- **Resultado esperado**: Sanitización, sin ejecución

### 19.4 CSRF
- **Acción**: Intentar envío de formulario externo
- **Resultado esperado**: Token CSRF válido requerido

## 20. PRUEBAS DE MULTIEMPRESA

### 20.1 Aislamiento de datos
- **Acción**: Crear datos en empresa A, verificar que no se ven en B
- **Resultado esperado**: Datos completamente aislados

### 20.2 Configuración independiente
- **Acción**: Configurar diferente en cada empresa
- **Resultado esperado**: Configuraciones independientes

### 20.3 Archivos separados
- **Acción**: Subir archivos en diferentes empresas
- **Resultado esperado**: Archivos en carpetas separadas

---

## EJECUCIÓN DE PRUEBAS

### Preparación
1. Crear base de datos de prueba
2. Cargar datos de muestra
3. Configurar usuarios de prueba
4. Preparar entorno de prueba

### Herramientas
- **Postman**: Para probar APIs
- **Browser DevTools**: Para debugging frontend
- **MySQL Workbench**: Para verificar datos
- **Selenium**: Para pruebas automatizadas (opcional)

### Checklist de Pruebas
- [ ] Login y logout
- [ ] CRUD de productos
- [ ] CRUD de clientes  
- [ ] Flujo completo de ventas
- [ ] Generación de imágenes IA
- [ ] Sistema de banners
- [ ] Módulo de avisos
- [ ] Envío de WhatsApp
- [ ] Generación de etiquetas
- [ ] Contabilidad automática
- [ ] Finanzas y movimientos
- [ ] Cámaras y streaming
- [ ] Reconocimiento facial
- [ ] Configuración general
- [ ] Multiempresa
- [ ] Seguridad
- [ ] Rendimiento

---

## NOTAS IMPORTANTES

1. **Base de Datos**: Usar `facturacion_test` para pruebas
2. **Archivos**: Usar `files_test/` para no afectar producción
3. **APIs IA**: Usar cuentas de prueba o sandbox
4. **DLNA**: Simular con servidor local de prueba
5. **Daemon**: Detener en producción, iniciar solo para pruebas
6. **Logs**: Verificar logs en `/var/log/` y logs de aplicación
7. **Backups**: Hacer backup antes de pruebas destructivas
