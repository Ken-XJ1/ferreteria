<?php
session_start();
require_once 'conexion.php'; 

// --- VERIFICACIÓN DE ADMINISTRADOR ---
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
     die("Acceso denegado.");
}
$admin_id = $_SESSION['usuario_id'];

$logs = [];
try {
    $sql_auditoria = "
        SELECT 
            a.fecha_hora, 
            u.nombre_usuario AS admin_nombre,  
            a.accion, 
            a.tabla_afectada, 
            a.id_registro AS registro_afectado_id 
        FROM auditoria_eventos a
        LEFT JOIN usuarios u ON a.usuario_db = u.nombre_usuario  
        ORDER BY a.fecha_hora DESC 
        LIMIT 100";
    
    $stmt_auditoria = $pdo->query($sql_auditoria);
    $logs = $stmt_auditoria->fetchAll();
} catch (PDOException $e) {
    die("Error al cargar la auditoría: " . $e->getMessage()); 
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Admin | Log de Auditoría</title>
    <link rel="stylesheet" href="estilo.css">
</head>
<body>
    <div class="admin-layout">
        <aside class="admin-sidebar">
            <div class="sidebar-header">
                <h3 style="color: white; margin: 0;">Admin Ferretería</h3>
                <button class="sidebar-toggle">≡</button>
            </div>
            <ul class="sidebar-menu">
                <li>
                    <a href="admin_usuarios.php">
                        <span class="menu-icon">👥</span>
                        <span class="menu-text">Gestión de Usuarios</span>
                    </a>
                </li>
                <li>
                    <a href="admin_productos.php">
                        <span class="menu-icon">🔧</span>
                        <span class="menu-text">Gestión de Productos</span>
                    </a>
                </li>
                <li>
                    <a href="admin_auditoria.php" class="active">
                        <span class="menu-icon">🕵️‍♂️</span>
                        <span class="menu-text">Log de Auditoría</span>
                    </a>
                </li>
                <li>
                    <a href="tienda.php">
                        <span class="menu-icon">🛒</span>
                        <span class="menu-text">Ir a la Tienda</span>
                    </a>
                </li>
                <li>
                    <a href="cerrar_sesion.php">
                        <span class="menu-icon">🚪</span>
                        <span class="menu-text">Cerrar Sesión</span>
                    </a>
                </li>
            </ul>
        </aside>

        <main class="admin-content">
            <div class="page-header">
                <h1 class="page-title">
                    <span class="page-title-icon">🕵️‍♂️</span>
                    Log de Auditoría del Sistema
                </h1>
                <p>Registro cronológico de todas las acciones importantes realizadas por los administradores.</p>
            </div>

            <div class="table-container">
                <h2>Últimas 100 Acciones</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Fecha/Hora</th>
                            <th>Administrador</th>
                            <th>Acción Realizada</th>
                            <th>Tabla Afectada</th>
                            <th>Registro ID</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: var(--spacing-xl);">
                                    No hay registros de auditoría aún.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td style="font-family: monospace; font-size: 0.9em;">
                                        <?php echo htmlspecialchars($log['fecha_hora']); ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($log['admin_nombre'] ?? 'Usuario Desconocido'); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($log['accion']); ?></td>
                                    <td>
                                        <span style="background-color: var(--color-primary); color: white; padding: 2px 8px; border-radius: var(--border-radius-sm); font-size: 0.8em;">
                                            <?php echo htmlspecialchars($log['tabla_afectada']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($log['registro_afectado_id']): ?>
                                            <code style="background-color: var(--color-light); padding: 2px 6px; border-radius: var(--border-radius-sm);">
                                                #<?php echo htmlspecialchars($log['registro_afectado_id']); ?>
                                            </code>
                                        <?php else: ?>
                                            <span style="color: var(--color-gray);">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.querySelector('.sidebar-toggle');
            const adminSidebar = document.querySelector('.admin-sidebar');
            
            if (sidebarToggle && adminSidebar) {
                sidebarToggle.addEventListener('click', function() {
                    adminSidebar.classList.toggle('collapsed');
                });
            }
        });
    </script>
</body>
</html>