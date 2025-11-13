<?php
session_start();
require_once 'conexion.php';

// Verificar permisos de administrador
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Manejar acciones de bloqueo/desbloqueo
$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $accion = $_POST['accion'] ?? '';
    $usuario_id = (int)($_POST['usuario_id'] ?? 0);
    
    if ($accion === 'bloquear') {
        $duracion = (int)($_POST['duracion'] ?? 0);
        $razon = $_POST['razon'] ?? 'Bloqueo administrativo';
        
        if ($duracion === 0) {
            // Bloqueo permanente
            $stmt = $pdo->prepare("UPDATE usuarios SET bloqueado = true, bloqueado_hasta = NULL WHERE id = ?");
            $stmt->execute([$usuario_id]);
        } else {
            // Bloqueo temporal
            $bloqueado_hasta = date('Y-m-d H:i:s', strtotime("+$duracion minutes"));
            $stmt = $pdo->prepare("UPDATE usuarios SET bloqueado = false, bloqueado_hasta = ? WHERE id = ?");
            $stmt->execute([$bloqueado_hasta, $usuario_id]);
        }
        
        $mensaje = "Usuario bloqueado exitosamente.";
        
    } elseif ($accion === 'desbloquear') {
        $stmt = $pdo->prepare("UPDATE usuarios SET bloqueado = false, bloqueado_hasta = NULL, intentos_fallidos = 0 WHERE id = ?");
        $stmt->execute([$usuario_id]);
        
        $mensaje = "Usuario desbloqueado exitosamente.";
    }
    
    // Recargar la página para ver los cambios
    header('Location: admin_usuarios.php');
    exit;
}

// Obtener lista de usuarios - consulta adaptativa
try {
    // Verificar columnas existentes
    $stmt_check = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'usuarios'");
    $columnas = $stmt_check->fetchAll(PDO::FETCH_COLUMN);
    
    $columnas_select = ['id', 'nombre', 'nombre_usuario', 'email', 'rol', 'bloqueado', 'bloqueado_hasta', 'intentos_fallidos', 'ultimo_intento'];
    
    // Agregar fecha de registro si existe
    if (in_array('fecha_registro', $columnas)) {
        $columnas_select[] = 'fecha_registro';
    } elseif (in_array('created_at', $columnas)) {
        $columnas_select[] = 'created_at';
    }
    
    $columnas_sql = implode(', ', $columnas_select);
    
    $stmt = $pdo->query("SELECT $columnas_sql FROM usuarios ORDER BY id DESC");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $usuarios = [];
    $error = "Error al cargar usuarios: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <!-- Header y Navigation (igual que en admin_dashboard) -->
    <header class="bg-white shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-tools text-2xl text-blue-600"></i>
                    </div>
                    <div class="ml-3">
                        <h1 class="text-2xl font-bold text-gray-900">Gestión de Usuarios</h1>
                        <p class="text-sm text-gray-500">Panel de administración</p>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-700">Hola, <?php echo htmlspecialchars($_SESSION['nombre_usuario']); ?></span>
                </div>
            </div>
        </div>
    </header>

    <nav class="bg-blue-600 shadow">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between">
                <div class="flex space-x-8">
                    <a href="admin_dashboard.php" class="flex items-center px-3 py-2 text-sm font-medium text-blue-100 hover:text-white hover:bg-blue-700 rounded-md">
                        <i class="fas fa-chart-line mr-2"></i>
                        Dashboard
                    </a>
                    <a href="admin_productos.php" class="flex items-center px-3 py-2 text-sm font-medium text-blue-100 hover:text-white hover:bg-blue-700 rounded-md">
                        <i class="fas fa-boxes mr-2"></i>
                        Productos
                    </a>
                    <a href="admin_usuarios.php" class="flex items-center px-3 py-2 text-sm font-medium text-white bg-blue-700 rounded-md">
                        <i class="fas fa-users mr-2"></i>
                        Usuarios
                    </a>
                    <a href="admin_pedidos.php" class="flex items-center px-3 py-2 text-sm font-medium text-blue-100 hover:text-white hover:bg-blue-700 rounded-md">
                        <i class="fas fa-shopping-cart mr-2"></i>
                        Pedidos
                    </a>
                    <a href="tienda.php" class="flex items-center px-3 py-2 text-sm font-medium text-blue-100 hover:text-white hover:bg-blue-700 rounded-md">
                        <i class="fas fa-store mr-2"></i>
                        Ver Tienda
                    </a>
                </div>
                <div class="flex items-center">
                    <a href="cerrar_sesion.php" class="flex items-center px-3 py-2 text-sm font-medium text-blue-100 hover:text-white hover:bg-red-600 rounded-md">
                        <i class="fas fa-sign-out-alt mr-2"></i>
                        Cerrar Sesión
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <?php if (!empty($mensaje)): ?>
            <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="bg-white shadow rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Lista de Usuarios</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usuario</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rol</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bloqueo</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Intentos Fallidos</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Último Intento</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (!empty($usuarios)): ?>
                            <?php foreach ($usuarios as $usuario): ?>
                                <?php
                                $esta_bloqueado = $usuario['bloqueado'] || 
                                                ($usuario['bloqueado_hasta'] && strtotime($usuario['bloqueado_hasta']) > time());
                                $tiempo_restante = '';
                                
                                if ($usuario['bloqueado_hasta'] && strtotime($usuario['bloqueado_hasta']) > time()) {
                                    $diferencia = strtotime($usuario['bloqueado_hasta']) - time();
                                    $horas = floor($diferencia / 3600);
                                    $minutos = floor(($diferencia % 3600) / 60);
                                    $tiempo_restante = " ($horas h $minutos m)";
                                }
                                ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($usuario['nombre']); ?></div>
                                        <div class="text-sm text-gray-500">@<?php echo htmlspecialchars($usuario['nombre_usuario']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($usuario['email']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $usuario['rol'] === 'admin' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'; ?>">
                                            <?php echo htmlspecialchars($usuario['rol']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $esta_bloqueado ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'; ?>">
                                            <?php echo $esta_bloqueado ? 'Bloqueado' : 'Activo'; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php if ($esta_bloqueado): ?>
                                            <span class="text-red-600">
                                                <?php echo $usuario['bloqueado'] ? 'Permanente' : 'Temporal'; ?>
                                                <?php echo $tiempo_restante; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-green-600">Desbloqueado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo $usuario['intentos_fallidos']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo $usuario['ultimo_intento'] ? date('d/m/Y H:i', strtotime($usuario['ultimo_intento'])) : 'Nunca'; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <?php if ($esta_bloqueado): ?>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="usuario_id" value="<?php echo $usuario['id']; ?>">
                                                <input type="hidden" name="accion" value="desbloquear">
                                                <button type="submit" class="text-green-600 hover:text-green-900">
                                                    <i class="fas fa-unlock"></i> Desbloquear
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <button onclick="abrirModalBloqueo(<?php echo $usuario['id']; ?>, '<?php echo htmlspecialchars($usuario['nombre']); ?>')" 
                                                    class="text-red-600 hover:text-red-900">
                                                <i class="fas fa-lock"></i> Bloquear
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="px-6 py-4 text-center text-sm text-gray-500">
                                    No hay usuarios registrados
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Modal para bloquear usuario -->
    <div id="modalBloqueo" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900">Bloquear Usuario</h3>
                <form method="POST" action="admin_usuarios.php" class="mt-4">
                    <input type="hidden" name="usuario_id" id="modal_usuario_id">
                    <input type="hidden" name="accion" value="bloquear">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Usuario:</label>
                        <input type="text" id="modal_nombre_usuario" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3" readonly>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Duración del bloqueo:</label>
                        <select name="duracion" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3" required>
                            <option value="30">30 minutos</option>
                            <option value="60">1 hora</option>
                            <option value="240">4 horas</option>
                            <option value="1440">1 día</option>
                            <option value="0">Permanente</option>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Razón del bloqueo:</label>
                        <textarea name="razon" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3" rows="3" placeholder="Motivo del bloqueo..." required></textarea>
                    </div>
                    
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="cerrarModal()" class="bg-gray-300 hover:bg-gray-400 text-gray-800 py-2 px-4 rounded">
                            Cancelar
                        </button>
                        <button type="submit" class="bg-red-600 hover:bg-red-700 text-white py-2 px-4 rounded">
                            Confirmar Bloqueo
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function abrirModalBloqueo(usuarioId, nombreUsuario) {
            document.getElementById('modal_usuario_id').value = usuarioId;
            document.getElementById('modal_nombre_usuario').value = nombreUsuario;
            document.getElementById('modalBloqueo').classList.remove('hidden');
        }
        
        function cerrarModal() {
            document.getElementById('modalBloqueo').classList.add('hidden');
        }
        
        // Cerrar modal al hacer clic fuera
        window.onclick = function(event) {
            const modal = document.getElementById('modalBloqueo');
            if (event.target === modal) {
                cerrarModal();
            }
        }
    </script>
</body>
</html>