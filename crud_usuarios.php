<?php
session_start();
require_once 'conexion.php';

// --- 1. VERIFICACIÓN DE ACCESO (Solo para Administradores) ---
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
    // Si no es admin o no está logueado, redirigir
    header('Location: login.php');
    exit;
}

$mensaje = "";

// --- 2. Lógica para ELIMINAR Usuario (Delete - D) ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['accion']) && $_POST['accion'] === 'eliminar' && isset($_POST['usuario_id'])) {
    $id_a_eliminar = $_POST['usuario_id'];

    if ($id_a_eliminar == $_SESSION['usuario_id']) {
        $mensaje = "Error: No puedes eliminar tu propia cuenta activa.";
    } else {
        try {
            // La auditoría registrará este borrado automáticamente
            $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
            if ($stmt->execute([$id_a_eliminar])) {
                $mensaje = "Éxito: Usuario con ID $id_a_eliminar eliminado correctamente.";
            } else {
                $mensaje = "Error al intentar eliminar el usuario.";
            }
        } catch (PDOException $e) {
            $mensaje = "Error de BD al eliminar: " . $e->getMessage();
        }
    }
}
// --- Fin Lógica ELIMINAR ---


// --- 3. Lógica para LEER Usuarios (Read - R) ---
try {
    // Seleccionamos todos los campos relevantes, incluyendo los de bloqueo
    $stmt_usuarios = $pdo->query("SELECT id, nombre_usuario, email, rol, intentos_fallidos, bloqueado_hasta FROM usuarios ORDER BY id ASC");
    $usuarios = $stmt_usuarios->fetchAll();
} catch (PDOException $e) {
    $mensaje = "Error al cargar la lista de usuarios: " . $e->getMessage();
    $usuarios = [];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRUD Usuarios - Ferretería</title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>

    <div class="header">
        <h1>Gestión de Usuarios</h1>
        <div>
            <a href="panel_admin.php" style="background-color:#5c677d;">Volver al Panel</a>
            <a href="cerrar_sesion.php">Cerrar Sesión</a>
        </div>
    </div>

    <div class="panel">
        
        <?php if (!empty($mensaje)): ?>
            <div class="mensaje <?php echo strpos($mensaje, 'Error') !== false ? 'error' : 'exito'; ?>">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <a href="registro.php" class="btn-crear">Crear Nuevo Usuario</a>

        <h2>Lista de Usuarios (Total: <?php echo count($usuarios); ?>)</h2>

        <?php if (count($usuarios) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Usuario</th>
                        <th>Email</th>
                        <th>Rol</th>
                        <th>Intentos Fallidos</th>
                        <th>Bloqueado Hasta</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $usuario): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($usuario['id']); ?></td>
                            <td><?php echo htmlspecialchars($usuario['nombre_usuario']); ?></td>
                            <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                            <td class="<?php echo ($usuario['rol'] === 'admin') ? 'rol-admin' : ''; ?>">
                                <?php echo htmlspecialchars($usuario['rol']); ?>
                            </td>
                            <td><?php echo htmlspecialchars($usuario['intentos_fallidos']); ?></td>
                            <td>
                                <?php 
                                if ($usuario['bloqueado_hasta'] && strtotime($usuario['bloqueado_hasta']) > time()) {
                                    echo '<span class="bloqueado">' . date('H:i:s', strtotime($usuario['bloqueado_hasta'])) . '</span>';
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td>
                                <a href="editar_usuario.php?id=<?php echo $usuario['id']; ?>" class="btn-accion btn-editar">Editar</a>
                                
                                <form method="POST" style="display:inline-block;" onsubmit="return confirm('¿Estás seguro de ELIMINAR al usuario <?php echo htmlspecialchars($usuario['nombre_usuario']); ?>? Esta acción es irreversible y queda registrada en auditoría.');">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="usuario_id" value="<?php echo $usuario['id']; ?>">
                                    <button type="submit" class="btn-accion btn-eliminar">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No hay usuarios registrados en el sistema. Usa el botón "Crear Nuevo Usuario" para empezar.</p>
        <?php endif; ?>

    </div>

</body>
</html>