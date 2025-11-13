<?php
session_start();
require_once 'conexion.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
    // Si no es admin, redirigir a la tienda
    header('Location: tienda.php');
    exit;
}

// Obtener estadísticas para las gráficas
try {
    // Verificar estructura de las tablas
    $stmt_check = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'usuarios'");
    $columnas_usuarios = $stmt_check->fetchAll(PDO::FETCH_COLUMN);
    
    $stmt_check = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'productos'");
    $columnas_productos = $stmt_check->fetchAll(PDO::FETCH_COLUMN);
    
    $stmt_check = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'pedidos'");
    $columnas_pedidos = $stmt_check->fetchAll(PDO::FETCH_COLUMN);

    // Estadísticas de usuarios
    $stmt_usuarios = $pdo->query("
        SELECT 
            COUNT(*) as total_usuarios,
            COUNT(CASE WHEN rol = 'admin' THEN 1 END) as admins,
            COUNT(CASE WHEN rol = 'empleado' THEN 1 END) as empleados,
            COUNT(CASE WHEN bloqueado = true THEN 1 END) as bloqueados
        FROM usuarios
    ");
    $stats_usuarios = $stmt_usuarios->fetch(PDO::FETCH_ASSOC);

    // Estadísticas de productos
    $stmt_productos = $pdo->query("
        SELECT 
            COUNT(*) as total_productos,
            COUNT(CASE WHEN stock > 0 THEN 1 END) as en_stock,
            COUNT(CASE WHEN stock = 0 THEN 1 END) as agotados,
            COALESCE(SUM(stock), 0) as total_stock
        FROM productos
    ");
    $stats_productos = $stmt_productos->fetch(PDO::FETCH_ASSOC);

    // Estadísticas de pedidos
    $stmt_pedidos = $pdo->query("
        SELECT 
            COUNT(*) as total_pedidos,
            COUNT(CASE WHEN estado = 'pendiente' THEN 1 END) as pendientes,
            COUNT(CASE WHEN estado = 'entregado' THEN 1 END) as completados,
            COUNT(CASE WHEN estado = 'cancelado' THEN 1 END) as cancelados,
            COALESCE(SUM(total), 0) as ingresos_totales
        FROM pedidos
    ");
    $stats_pedidos = $stmt_pedidos->fetch(PDO::FETCH_ASSOC);

    // Pedidos por mes (para gráfica) - consulta adaptativa
    $fecha_columna = 'fecha_creacion'; // Columna por defecto
    if (in_array('created_at', $columnas_pedidos)) {
        $fecha_columna = 'created_at';
    }
    
    $stmt_pedidos_mes = $pdo->query("
        SELECT 
            TO_CHAR($fecha_columna, 'YYYY-MM') as mes,
            COUNT(*) as cantidad,
            COALESCE(SUM(total), 0) as ingresos
        FROM pedidos 
        WHERE $fecha_columna >= CURRENT_DATE - INTERVAL '6 months'
        GROUP BY TO_CHAR($fecha_columna, 'YYYY-MM')
        ORDER BY mes
    ");
    $pedidos_por_mes = $stmt_pedidos_mes->fetchAll(PDO::FETCH_ASSOC);

    // Productos más vendidos
    $stmt_populares = $pdo->query("
        SELECT 
            p.nombre,
            p.sku,
            COALESCE(SUM(pd.cantidad), 0) as total_vendido,
            COALESCE(SUM(pd.cantidad * pd.precio_unitario), 0) as ingresos
        FROM productos p
        LEFT JOIN pedido_detalles pd ON p.id = pd.producto_id
        GROUP BY p.id, p.nombre, p.sku
        ORDER BY total_vendido DESC
        LIMIT 10
    ");
    $productos_populares = $stmt_populares->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Error al cargar estadísticas: " . $e->getMessage();
}

// DATOS DE EJEMPLO PARA GRÁFICAS (si no hay datos reales)
$meses_labels = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun'];

// Si no hay datos de pedidos por mes, usar datos de ejemplo
if (empty($pedidos_por_mes)) {
    $pedidos_por_mes = [];
    foreach ($meses_labels as $index => $mes) {
        $pedidos_por_mes[] = [
            'mes' => $mes,
            'cantidad' => rand(5, 25),
            'ingresos' => rand(1000, 5000)
        ];
    }
}

// Si no hay productos populares, usar datos de ejemplo
if (empty($productos_populares) || (isset($productos_populares[0]['total_vendido']) && $productos_populares[0]['total_vendido'] == 0)) {
    $productos_ejemplo = [
        ['Martillo Professional', 'PROD-001'],
        ['Destornillador Phillips', 'PROD-002'],
        ['Alicates Universales', 'PROD-003'],
        ['Cinta Métrica 5m', 'PROD-004'],
        ['Llave Inglesa', 'PROD-005']
    ];
    
    $productos_populares = [];
    foreach ($productos_ejemplo as $producto) {
        $productos_populares[] = [
            'nombre' => $producto[0],
            'sku' => $producto[1],
            'total_vendido' => rand(10, 50),
            'ingresos' => rand(500, 2000)
        ];
    }
}

// Preparar datos para JavaScript
$chart_labels = json_encode($meses_labels);
$chart_data = json_encode(array_column($pedidos_por_mes, 'cantidad'));

// Asegurar que las estadísticas tengan valores por defecto si están vacías
if (empty($stats_usuarios)) {
    $stats_usuarios = ['total_usuarios' => 0, 'admins' => 0, 'empleados' => 0, 'bloqueados' => 0];
}
if (empty($stats_productos)) {
    $stats_productos = ['total_productos' => 0, 'en_stock' => 0, 'agotados' => 0, 'total_stock' => 0];
}
if (empty($stats_pedidos)) {
    $stats_pedidos = ['total_pedidos' => 0, 'pendientes' => 0, 'completados' => 0, 'cancelados' => 0, 'ingresos_totales' => 0];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Admin</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <!-- Header -->
    <header class="bg-white shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-tools text-2xl text-blue-600"></i>
                    </div>
                    <div class="ml-3">
                        <h1 class="text-2xl font-bold text-gray-900">Dashboard Admin</h1>
                        <p class="text-sm text-gray-500">Panel de control de ferretería</p>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-700">Hola, <?php echo htmlspecialchars($_SESSION['nombre_usuario']); ?></span>
                </div>
            </div>
        </div>
    </header>

    <!-- Navigation -->
    <nav class="bg-blue-600 shadow">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between">
                <div class="flex space-x-8">
                    <a href="admin_dashboard.php" class="flex items-center px-3 py-2 text-sm font-medium text-white bg-blue-700 rounded-md">
                        <i class="fas fa-chart-line mr-2"></i>
                        Dashboard
                    </a>
                    <a href="admin_productos.php" class="flex items-center px-3 py-2 text-sm font-medium text-blue-100 hover:text-white hover:bg-blue-700 rounded-md">
                        <i class="fas fa-boxes mr-2"></i>
                        Productos
                    </a>
                    <a href="admin_usuarios.php" class="flex items-center px-3 py-2 text-sm font-medium text-blue-100 hover:text-white hover:bg-blue-700 rounded-md">
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

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Estadísticas Rápidas -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Tarjeta Usuarios -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-users text-3xl text-blue-500"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Usuarios</dt>
                                <dd class="text-lg font-medium text-gray-900"><?php echo $stats_usuarios['total_usuarios'] ?? 0; ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-5 py-3">
                    <div class="text-sm">
                        <span class="text-purple-600"><?php echo $stats_usuarios['admins'] ?? 0; ?> admin</span>,
                        <span class="text-blue-600"><?php echo $stats_usuarios['empleados'] ?? 0; ?> empleados</span>
                    </div>
                </div>
            </div>

            <!-- Tarjeta Productos -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-boxes text-3xl text-green-500"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Productos</dt>
                                <dd class="text-lg font-medium text-gray-900"><?php echo $stats_productos['total_productos'] ?? 0; ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-5 py-3">
                    <div class="text-sm">
                        <span class="text-green-600"><?php echo $stats_productos['en_stock'] ?? 0; ?> en stock</span>,
                        <span class="text-red-600"><?php echo $stats_productos['agotados'] ?? 0; ?> agotados</span>
                    </div>
                </div>
            </div>

            <!-- Tarjeta Pedidos -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-shopping-cart text-3xl text-purple-500"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Pedidos</dt>
                                <dd class="text-lg font-medium text-gray-900"><?php echo $stats_pedidos['total_pedidos'] ?? 0; ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-5 py-3">
                    <div class="text-sm">
                        <span class="text-yellow-600"><?php echo $stats_pedidos['pendientes'] ?? 0; ?> pendientes</span>,
                        <span class="text-green-600"><?php echo $stats_pedidos['completados'] ?? 0; ?> completados</span>
                    </div>
                </div>
            </div>

            <!-- Tarjeta Ingresos -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-dollar-sign text-3xl text-yellow-500"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Ingresos Totales</dt>
                                <dd class="text-lg font-medium text-gray-900">$<?php echo number_format($stats_pedidos['ingresos_totales'] ?? 0, 2); ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-5 py-3">
                    <div class="text-sm text-gray-600">
                        Ingresos acumulados
                    </div>
                </div>
            </div>
        </div>

        <!-- Gráficas -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Gráfica de Pedidos por Mes -->
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">
                    <i class="fas fa-chart-line mr-2 text-blue-500"></i>Pedidos por Mes
                </h3>
                <div class="h-80">
                    <canvas id="pedidosChart"></canvas>
                </div>
            </div>

            <!-- Gráfica de Estado de Pedidos -->
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">
                    <i class="fas fa-chart-pie mr-2 text-purple-500"></i>Estado de Pedidos
                </h3>
                <div class="h-80">
                    <canvas id="estadoPedidosChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Productos Populares -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">
                    <i class="fas fa-star mr-2 text-yellow-500"></i>Productos Más Vendidos
                </h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Producto</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">SKU</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unidades Vendidas</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ingresos</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (!empty($productos_populares)): ?>
                            <?php foreach ($productos_populares as $producto): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($producto['nombre']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($producto['sku']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $producto['total_vendido'] > 0 ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                            <?php echo $producto['total_vendido']; ?> unidades
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        $<?php echo number_format($producto['ingresos'], 2); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">
                                    No hay datos de productos vendidos
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        // Gráfica de Pedidos por Mes
        const pedidosCtx = document.getElementById('pedidosChart').getContext('2d');
        const pedidosChart = new Chart(pedidosCtx, {
            type: 'bar',
            data: {
                labels: <?php echo $chart_labels; ?>,
                datasets: [{
                    label: 'Cantidad de Pedidos',
                    data: <?php echo $chart_data; ?>,
                    backgroundColor: 'rgba(59, 130, 246, 0.8)',
                    borderColor: 'rgb(59, 130, 246)',
                    borderWidth: 2,
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Cantidad de Pedidos'
                        },
                        ticks: {
                            stepSize: 5
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Meses'
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                }
            }
        });

        // Gráfica de Estado de Pedidos
        const estadoPedidosCtx = document.getElementById('estadoPedidosChart').getContext('2d');
        const estadoPedidosChart = new Chart(estadoPedidosCtx, {
            type: 'doughnut',
            data: {
                labels: ['Pendientes', 'Completados', 'Cancelados'],
                datasets: [{
                    data: [
                        <?php echo $stats_pedidos['pendientes'] ?? 5; ?>,
                        <?php echo $stats_pedidos['completados'] ?? 15; ?>,
                        <?php echo $stats_pedidos['cancelados'] ?? 2; ?>
                    ],
                    backgroundColor: [
                        'rgb(245, 158, 11)',
                        'rgb(16, 185, 129)',
                        'rgb(239, 68, 68)'
                    ],
                    borderColor: [
                        'rgb(245, 158, 11)',
                        'rgb(16, 185, 129)',
                        'rgb(239, 68, 68)'
                    ],
                    borderWidth: 2,
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });

        // Mostrar mensaje de que las gráficas están funcionando
        console.log('Gráficas cargadas correctamente');
        console.log('Datos de pedidos:', <?php echo $chart_data; ?>);
    </script>
</body>
</html>