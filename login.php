<?php
session_start();
require_once 'conexion.php';

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (!empty($email) && !empty($password)) {
        try {
            // Buscar usuario por email - USANDO LOS NOMBRES CORRECTOS DE COLUMNAS
            $stmt = $pdo->prepare("SELECT id, nombre, email, contrasena_hash, rol, bloqueado FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($usuario) {
                // Verificar si el usuario está bloqueado
                if ($usuario['bloqueado']) {
                    $error = "Tu cuenta está bloqueada. Contacta al administrador.";
                } else {
                    // Verificar contraseña - usando contrasena_hash
                    if (password_verify($password, $usuario['contrasena_hash'])) {
                        // Iniciar sesión
                        $_SESSION['usuario_id'] = $usuario['id'];
                        $_SESSION['nombre_usuario'] = $usuario['nombre'];
                        $_SESSION['email'] = $usuario['email'];
                        $_SESSION['rol'] = $usuario['rol'];

                        // Redirigir según el rol
                        if ($usuario['rol'] === 'admin') {
                            header('Location: admin_dashboard.php');
                        } else {
                            header('Location: tienda.php'); // Usuarios normales van a la tienda
                        }
                        exit;
                    } else {
                        $error = "Credenciales incorrectas.";
                    }
                }
            } else {
                $error = "Credenciales incorrectas.";
            }
        } catch (PDOException $e) {
            $error = "Error en el sistema: " . $e->getMessage();
        }
    } else {
        $error = "Por favor, completa todos los campos.";
    }
}

// Si ya está logueado, redirigir según su rol
if (isset($_SESSION['usuario_id'])) {
    if ($_SESSION['rol'] === 'admin') {
        header('Location: admin_dashboard.php');
    } else {
        header('Location: tienda.php');
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Ferretería Online</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .login-container {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        .input-group {
            position: relative;
        }
        .input-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
        }
        .input-group input {
            padding-left: 45px;
        }
    </style>
</head>
<body class="login-container">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full login-card p-8">
            <!-- Logo y Título -->
            <div class="text-center mb-8">
                <div class="flex justify-center mb-4">
                    <div class="bg-blue-100 p-3 rounded-full">
                        <i class="fas fa-tools text-3xl text-blue-600"></i>
                    </div>
                </div>
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Ferretería Online</h1>
                <p class="text-gray-600">Inicia sesión en tu cuenta</p>
            </div>

            <!-- Mensaje de Error -->
            <?php if (!empty($error)): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6 flex items-center">
                    <i class="fas fa-exclamation-circle mr-3"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <!-- Formulario de Login -->
            <form method="POST" action="login.php" class="space-y-6">
                <!-- Campo Email -->
                <div class="input-group">
                    <label for="email" class="sr-only">Correo Electrónico</label>
                    <i class="fas fa-envelope"></i>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        required 
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200"
                        placeholder="Correo electrónico"
                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                    >
                </div>

                <!-- Campo Contraseña -->
                <div class="input-group">
                    <label for="password" class="sr-only">Contraseña</label>
                    <i class="fas fa-lock"></i>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        required 
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200"
                        placeholder="Contraseña"
                    >
                </div>

                <!-- Botón de Login -->
                <button 
                    type="submit" 
                    class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 font-medium transition duration-200 transform hover:scale-105 shadow-lg"
                >
                    <i class="fas fa-sign-in-alt mr-2"></i>Iniciar Sesión
                </button>
            </form>

            <!-- Enlaces Adicionales -->
            <div class="mt-8 space-y-4 text-center">
                <!-- Registro -->
                <div>
                    <p class="text-gray-600">
                        ¿No tienes cuenta? 
                        <a href="registro.php" class="text-blue-600 hover:text-blue-800 font-medium transition duration-200">
                            Regístrate aquí
                        </a>
                    </p>
                </div>

                <!-- Separador -->
                <div class="relative">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-300"></div>
                    </div>
                    <div class="relative flex justify-center text-sm">
                        <span class="px-2 bg-white text-gray-500">o</span>
                    </div>
                </div>

                <!-- Continuar como invitado -->
                <div>
                    <a href="tienda.php" class="inline-flex items-center text-gray-500 hover:text-gray-700 transition duration-200">
                        <i class="fas fa-store mr-2"></i>
                        Continuar como invitado
                    </a>
                </div>
            </div>

            <!-- Información adicional -->
            <div class="mt-8 pt-6 border-t border-gray-200">
                <div class="text-center text-sm text-gray-500">
                    <p>¿Problemas para iniciar sesión?</p>
                    <p class="mt-1">Contacta al administrador del sistema</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Script para mejorar la experiencia de usuario -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Focus en el primer campo
            document.getElementById('email').focus();
            
            // Mostrar/ocultar contraseña
            const passwordInput = document.getElementById('password');
            const passwordGroup = document.querySelectorAll('.input-group')[1];
            
            const togglePassword = document.createElement('span');
            togglePassword.innerHTML = '<i class="fas fa-eye"></i>';
            togglePassword.className = 'absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 cursor-pointer hover:text-gray-600';
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
            });
            
            passwordGroup.appendChild(togglePassword);
        });
    </script>
</body>
</html>