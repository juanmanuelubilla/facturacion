<?php
require_once 'config.php';
requireLogin();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pantalla Cliente</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white min-h-screen">
    <div class="flex flex-col h-full">
        <div class="flex justify-between items-center p-6 bg-gray-800">
            <h1 class="text-3xl font-bold text-green-400" id="pcNombreNegocio">NEXUS POS</h1>
            <div class="text-4xl font-bold" id="pcTotal">$0.00</div>
        </div>
        <div class="flex-1 flex p-6 gap-4">
            <div class="flex-1 bg-gray-800 rounded-lg p-4">
                <h2 class="text-lg font-bold text-gray-400 mb-4">ULTIMO PRODUCTO</h2>
                <div class="flex gap-4 mb-4">
                    <div id="pcImagenContainer" class="w-32 h-32 flex items-center justify-center bg-gray-700 rounded-lg overflow-hidden flex-shrink-0">
                        <img id="pcImagen" src="" alt="" class="max-w-full max-h-full object-contain hidden">
                        <span id="pcSinImagen" class="text-gray-500 text-3xl">🖼️</span>
                    </div>
                    <div class="flex-1 flex flex-col justify-center">
                        <h3 class="text-2xl font-bold" id="pcNombre">-</h3>
                        <p class="text-blue-400 text-xl" id="pcDetalle"></p>
                    </div>
                </div>
            </div>
            <div class="flex-1 bg-gray-800 rounded-lg p-4">
                <h2 class="text-lg font-bold text-gray-400 mb-4">COMPRA EN CURSO</h2>
                <div id="pcItems" class="space-y-2"></div>
                <p class="text-orange-400 font-bold mt-4" id="pcCliente">CLIENTE: CONSUMIDOR FINAL</p>
                
                <!-- Información de pago -->
                <div id="pcPagoInfo" class="mt-4 p-3 bg-gray-700 rounded-lg hidden">
                    <h3 class="text-sm font-bold text-gray-400 mb-2">INFORMACIÓN DE PAGO</h3>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-gray-400">Método:</span>
                            <span class="text-white font-bold" id="pcMetodoPago">-</span>
                        </div>
                        <div>
                            <span class="text-gray-400">Entregado:</span>
                            <span class="text-green-400 font-bold" id="pcMontoEntregado">$0.00</span>
                        </div>
                        <div>
                            <span class="text-gray-400">Vuelto:</span>
                            <span class="text-yellow-400 font-bold" id="pcVuelto">$0.00</span>
                        </div>
                    </div>
                </div>
                
                <!-- Confirmación de venta -->
                <div id="pcConfirmacion" class="mt-4 p-4 bg-green-800 rounded-lg hidden">
                    <h3 class="text-lg font-bold text-green-400 mb-2">✅ VENTA COMPLETADA</h3>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-white mb-2" id="pcVentaId">-</div>
                        <div class="text-sm text-gray-300" id="pcMensaje">Gracias por su compra</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Escuchar mensajes desde la ventana principal
        window.addEventListener('message', function(event) {
            const data = event.data;
            if (data.type === 'actualizar') {
                document.getElementById('pcTotal').textContent = '$' + data.total.toFixed(2);
                document.getElementById('pcCliente').textContent = 'CLIENTE: ' + data.cliente;
                document.getElementById('pcNombreNegocio').textContent = data.nombreNegocio;

                // Ultimo producto
                if (data.ultimo) {
                    document.getElementById('pcNombre').textContent = data.ultimo.nombre.toUpperCase();
                    document.getElementById('pcDetalle').textContent = 
                        data.ultimo.cantidad + ' x $' + data.ultimo.precio.toFixed(2) + ' = $' + (data.ultimo.precio * data.ultimo.cantidad).toFixed(2);
                    
                    // Mostrar imagen si existe
                    const img = document.getElementById('pcImagen');
                    const sinImg = document.getElementById('pcSinImagen');
                    if (data.ultimo.imagen) {
                        img.src = data.ultimo.imagen;
                        img.classList.remove('hidden');
                        sinImg.classList.add('hidden');
                    } else {
                        img.classList.add('hidden');
                        sinImg.classList.remove('hidden');
                    }
                } else {
                    document.getElementById('pcNombre').textContent = 'SIN PRODUCTOS';
                    document.getElementById('pcDetalle').textContent = '';
                    document.getElementById('pcImagen').classList.add('hidden');
                    document.getElementById('pcSinImagen').classList.remove('hidden');
                }

                // Items del carrito
                document.getElementById('pcItems').innerHTML = data.items.map(item =>
                    '<div class="bg-gray-700 p-2 rounded flex gap-3">' +
                    '<div class="w-12 h-12 flex items-center justify-center bg-gray-600 rounded overflow-hidden flex-shrink-0">' +
                    (item.imagen ? 
                        '<img src="' + item.imagen + '" alt="' + item.nombre + '" class="w-full h-full object-cover">' :
                        '<span class="text-gray-400 text-2xl">🖼️</span>'
                    ) +
                    '</div>' +
                    '<div class="flex-1">' +
                    '<div class="font-bold">' + item.nombre + '</div>' +
                    '<div class="text-sm text-gray-400">' + item.cantidad + ' x $' + item.precio.toFixed(2) + '</div>' +
                    '</div>' +
                    '<div class="text-green-400 font-bold">$' + (item.precio * item.cantidad).toFixed(2) + '</div>' +
                    '</div>'
                ).join('');

                // Mostrar información de pago si existe
                if (data.pago && data.pago.metodo === 'EFECTIVO') {
                    const pagoInfo = document.getElementById('pcPagoInfo');
                    pagoInfo.classList.remove('hidden');
                    document.getElementById('pcMetodoPago').textContent = data.pago.metodo;
                    document.getElementById('pcMontoEntregado').textContent = '$' + data.pago.montoEntregado.toFixed(2);
                    document.getElementById('pcVuelto').textContent = '$' + data.pago.vuelto.toFixed(2);
                } else {
                    document.getElementById('pcPagoInfo').classList.add('hidden');
                }
            } else if (data.type === 'ventaCompletada') {
                const confirmacion = document.getElementById('pcConfirmacion');
                confirmacion.classList.remove('hidden');
                document.getElementById('pcVentaId').textContent = '#' + data.venta_id;
                document.getElementById('pcMensaje').textContent = '✅ ¡Venta completada exitosamente!';
                
                // Ocultar información de pago
                document.getElementById('pcPagoInfo').classList.add('hidden');
                
                // Mostrar por 3 segundos y luego ocultar
                setTimeout(() => {
                    confirmacion.classList.add('hidden');
                }, 3000);
            }
        });
    </script>
</body>
</html>
