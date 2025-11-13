<?php
session_start();
require_once 'conexion.php';

// Si ya está logueado, redirigir a tienda
if (isset($_SESSION['usuario_id'])) {
    header('Location: tienda.php');
    exit;
}

// Inicializar variables
$error = '';
$mensaje = '';
$nombre = '';
$email = '';
$es_admin = isset($_POST['es_admin']);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $es_admin = isset($_POST['es_admin']);
    
    // Validaciones
    if (empty($nombre) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "Por favor, completa todos los campos.";
    } elseif ($password !== $confirm_password) {
        $error = "Las contraseñas no coinciden.";
    } elseif (strlen($password) < 6) {
        $error = "La contraseña debe tener al menos 6 caracteres.";
    } else {
        try {
            // Verificar si el email ya existe
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                $error = "Este email ya está registrado.";
            } else {
                // Determinar el rol
                $rol = $es_admin ? 'admin' : 'empleado';
                
                // Crear nombre de usuario a partir del email (primera parte antes del @)
                $nombre_usuario = explode('@', $email)[0];
                
                // Crear nuevo usuario - USANDO LOS NOMBRES CORRECTOS DE COLUMNAS
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, nombre_usuario, email, contrasena_hash, rol) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$nombre, $nombre_usuario, $email, $password_hash, $rol]);
                
                if ($es_admin) {
                    $mensaje = "¡Registro de administrador exitoso! Ahora puedes iniciar sesión.";
                } else {
                    $mensaje = "¡Registro exitoso! Ahora puedes iniciar sesión.";
                }
                
                $nombre = '';
                $email = '';
                $es_admin = false;
            }
        } catch (PDOException $e) {
            $error = "Error en el registro: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - Ferretería</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .register-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 450px;
            padding: 40px;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo-icon {
            font-size: 3rem;
            color: #667eea;
            margin-bottom: 10px;
        }
        
        .logo h1 {
            color: #333;
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .logo .subtitle {
            color: #666;
            font-size: 1rem;
            font-weight: normal;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 2px solid #e1e5e9;
            margin: 20px 0;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .checkbox-group:hover {
            border-color: #667eea;
            background: #f0f2f5;
        }
        
        .checkbox-group.checked {
            background: #e8f5e8;
            border-color: #27ae60;
        }
        
        .checkbox-input {
            width: 20px;
            height: 20px;
            border: 2px solid #ccc;
            border-radius: 4px;
            cursor: pointer;
            position: relative;
            transition: all 0.3s ease;
        }
        
        .checkbox-input.checked {
            background: #27ae60;
            border-color: #27ae60;
        }
        
        .checkbox-input.checked::after {
            content: '✓';
            color: white;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-weight: bold;
        }
        
        .checkbox-label {
            flex: 1;
            color: #555;
            font-weight: 500;
            cursor: pointer;
        }
        
        .admin-badge {
            display: inline-block;
            background: linear-gradient(135deg, #27ae60 0%, #219653 100%);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-left: 10px;
        }
        
        .empleado-badge {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-left: 10px;
        }
        
        .btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-admin {
            background: linear-gradient(135deg, #27ae60 0%, #219653 100%);
        }
        
        .btn-admin:hover {
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.4);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
        }
        
        .btn-secondary:hover {
            box-shadow: 0 5px 15px rgba(108, 117, 125, 0.4);
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-danger {
            background-color: #fee;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .alert-success {
            background-color: #e8f5e8;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .register-links {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e1e5e9;
        }
        
        .register-links a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            margin: 0 10px;
        }
        
        .register-links a:hover {
            text-decoration: underline;
        }
        
        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        .button-group .btn {
            flex: 1;
        }
        
        .role-info {
            text-align: center;
            margin: 10px 0;
            color: #666;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="logo">
            <div class="logo-icon">🔨</div>
            <h1>Crear Cuenta</h1>
            <div class="subtitle">Únete a nuestra ferretería online</div>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="registro.php">
            <div class="form-group">
                <label class="form-label">Nombre completo:</label>
                <input type="text" name="nombre" class="form-control" 
                       value="<?php echo htmlspecialchars($nombre); ?>" 
                       required autofocus>
            </div>
            
            <div class="form-group">
                <label class="form-label">Email:</label>
                <input type="email" name="email" class="form-control" 
                       value="<?php echo htmlspecialchars($email); ?>" 
                       required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Contraseña:</label>
                <input type="password" name="password" class="form-control" 
                       placeholder="Mínimo 6 caracteres" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Confirmar Contraseña:</label>
                <input type="password" name="confirm_password" class="form-control" required>
            </div>
            
            <!-- Checkbox para administrador -->
            <div class="checkbox-group <?php echo $es_admin ? 'checked' : ''; ?>" onclick="toggleAdmin()">
                <div class="checkbox-input <?php echo $es_admin ? 'checked' : ''; ?>" id="adminCheckbox"></div>
                <div class="checkbox-label">
                    <?php if ($es_admin): ?>
                        Registrar como administrador 
                        <span class="admin-badge">👑 Admin</span>
                    <?php else: ?>
                        Registrar como empleado 
                        <span class="empleado-badge">👤 Empleado</span>
                    <?php endif; ?>
                </div>
                <input type="hidden" name="es_admin" id="es_admin" value="<?php echo $es_admin ? '1' : '0'; ?>">
            </div>
            
            <div class="role-info">
                <?php if ($es_admin): ?>
                    <strong>👑 Modo Administrador:</strong> Tendrás acceso completo al sistema
                <?php else: ?>
                    <strong>👤 Modo Empleado:</strong> Acceso estándar a la tienda
                <?php endif; ?>
            </div>
            
            <div class="button-group">
                <button type="submit" class="<?php echo $es_admin ? 'btn-admin' : 'btn'; ?>">
                    <?php echo $es_admin ? 'Registrar como Admin 👑' : 'Registrar como Empleado 👤'; ?>
                </button>
                <a href="login.php" class="btn btn-secondary" style="text-align: center; line-height: 40px;">
                    Iniciar Sesión
                </a>
            </div>
        </form>
        
        <div class="register-links">
            <a href="tienda.php">← Ver tienda como invitado</a>
        </div>
    </div>

    <script>
        function toggleAdmin() {
            const checkbox = document.getElementById('adminCheckbox');
            const hiddenInput = document.getElementById('es_admin');
            const checkboxGroup = document.querySelector('.checkbox-group');
            const checkboxLabel = document.querySelector('.checkbox-label');
            const submitButton = document.querySelector('button[type="submit"]');
            const roleInfo = document.querySelector('.role-info');
            
            if (checkbox.classList.contains('checked')) {
                // Cambiar a empleado
                checkbox.classList.remove('checked');
                checkboxGroup.classList.remove('checked');
                hiddenInput.value = '0';
                submitButton.textContent = 'Registrar como Empleado 👤';
                submitButton.classList.remove('btn-admin');
                
                checkboxLabel.innerHTML = 'Registrar como empleado <span class="empleado-badge">👤 Empleado</span>';
                roleInfo.innerHTML = '<strong>👤 Modo Empleado:</strong> Acceso estándar a la tienda';
            } else {
                // Cambiar a admin
                checkbox.classList.add('checked');
                checkboxGroup.classList.add('checked');
                hiddenInput.value = '1';
                submitButton.textContent = 'Registrar como Admin 👑';
                submitButton.classList.add('btn-admin');
                
                checkboxLabel.innerHTML = 'Registrar como administrador <span class="admin-badge">👑 Admin</span>';
                roleInfo.innerHTML = '<strong>👑 Modo Administrador:</strong> Tendrás acceso completo al sistema';
            }
        }
        
        // Limpiar mensajes después de 5 segundos
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                alert.style.transition = 'opacity 0.5s ease';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>