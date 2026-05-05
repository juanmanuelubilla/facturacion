<?php
require_once 'config.php';
require_once 'lib/CSRFProtection.php';

// Verificar si es la primera instalación (sin usuarios)
$db = getDB();
$usuarios_count = fetch("SELECT COUNT(*) as total FROM usuarios")['total'];

if ($usuarios_count == 0) {
    header('Location: setup.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar token CSRF
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Error de seguridad: Token inválido';
    } else {
        $usuario = trim($_POST['usuario'] ?? '');
        $password = $_POST['password'] ?? '';
        $empresa_id = $_POST['empresa_id'] ?? '';
        
        if ($usuario && $password && $empresa_id) {
        $db = getDB();
        $stmt = $db->prepare("SELECT id, nombre, rol, empresa_id FROM usuarios WHERE nombre = ? AND password = SHA2(?, 256) AND empresa_id = ?");
        $stmt->execute([$usuario, $password, $empresa_id]);
        $user = $stmt->fetch();
        
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_nombre'] = $user['nombre'];
            $_SESSION['user_rol'] = $user['rol'];
            $_SESSION['empresa_id'] = $user['empresa_id'];
            
            // Regenerar token CSRF después de login exitoso
            CSRFProtection::regenerateToken();
            
            // Limpiar intentos fallidos previos
            query("DELETE FROM login_attempts WHERE usuario = ? AND empresa_id = ? AND success = FALSE", 
                   [$usuario, $empresa_id]);
            
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Credenciales incorrectas';
            
            // Registrar intento fallido
            query("INSERT INTO login_attempts (usuario, empresa_id, ip_address, user_agent, success, failure_reason) 
                   VALUES (?, ?, ?, ?, ?, ?)", [
                $usuario, 
                $empresa_id, 
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                FALSE,
                'Credenciales incorrectas'
            ]);
        }
    } else {
        $error = 'Complete todos los campos';
    }
    }
}

$empresas = fetchAll("SELECT id, nombre FROM empresas WHERE activo = 1");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WARP POS - Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen flex items-center justify-center bg-gradient-to-br from-gray-900 to-gray-800">
    <div class="bg-gray-800 p-8 rounded-lg shadow-2xl w-full max-w-md">
        <div class="text-center mb-8">
            <div class="text-6xl mb-4">◈</div>
            <h1 class="text-2xl font-bold text-white">BIENVENIDO</h1>
            <p class="text-gray-400 mt-2">WARP POS</p>
        </div>
        
        <?php if ($error): ?>
            <div class="bg-red-500 text-white p-3 rounded mb-4"><?= $error ?></div>
        <?php endif; ?>
        
        <form method="POST" class="space-y-4">
            <?= getCSRFHiddenField() ?>
            <div>
                <label class="block text-gray-400 text-sm mb-2">Empresa</label>
                <select name="empresa_id" required class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                    <?php foreach ($empresas as $e): ?>
                        <option value="<?= $e['id'] ?>"><?= $e['id'] ?> - <?= htmlspecialchars($e['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-gray-400 text-sm mb-2">Usuario</label>
                <input type="text" name="usuario" required class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
            </div>
            <div>
                <label class="block text-gray-400 text-sm mb-2">Contraseña</label>
                <input type="password" name="password" required class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
            </div>
            <button type="submit" class="w-full bg-purple-600 hover:bg-purple-700 text-white font-bold py-3 rounded">INGRESAR</button>
        </form>
    </div>
</body>
</html>
