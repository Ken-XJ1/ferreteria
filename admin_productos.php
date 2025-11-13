<?php
session_start();
require_once 'conexion.php';

// Verificar permisos de administrador
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Manejar acciones
$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    if ($accion === 'crear_producto') {
        $nombre = trim($_POST['nombre'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $precio = floatval($_POST['precio'] ?? 0);
        $stock = intval($_POST['stock'] ?? 0);
        $categoria_id = intval($_POST['categoria_id'] ?? 0);
        $sku = trim($_POST['sku'] ?? '');
        
        if (!empty($nombre) && $precio > 0) {
            try {
                // Generar SKU automático si no se proporciona
                if (empty($sku)) {
                    $sku = 'PROD-' . strtoupper(uniqid());
                }
                
                $stmt = $pdo->prepare("INSERT INTO productos (nombre, descripcion, precio, stock, categoria_id, sku) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$nombre, $descripcion, $precio, $stock, $categoria_id, $sku]);
                
                $mensaje = "Producto creado exitosamente.";
            } catch (PDOException $e) {
                $mensaje = "Error al crear producto: " . $e->getMessage();
            }
        } else {
            $mensaje = "Por favor, completa todos los campos requeridos.";
        }
    }
    elseif ($accion === 'editar_producto') {
        $producto_id = intval($_POST['producto_id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $precio = floatval($_POST['precio'] ?? 0);
        $stock = intval($_POST['stock'] ?? 0);
        $categoria_id = intval($_POST['categoria_id'] ?? 0);
        
        if ($producto_id > 0 && !empty($nombre) && $precio > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE productos SET nombre = ?, descripcion = ?, precio = ?, stock = ?, categoria_id = ? WHERE id = ?");
                $stmt->execute([$nombre, $descripcion, $precio, $stock, $categoria_id, $producto_id]);
                
                $mensaje = "Producto actualizado exitosamente.";
            } catch (PDOException $e) {
                $mensaje = "Error al actualizar producto: " . $e->getMessage();
            }
        }
    }
    elseif ($accion === 'eliminar_producto') {
        $producto_id = intval($_POST['producto_id'] ?? 0);
        
        if ($producto_id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM productos WHERE id = ?");
                $stmt->execute([$producto_id]);
                
                $mensaje = "Producto eliminado exitosamente.";
            } catch (PDOException $e) {
                $mensaje = "Error al eliminar producto: " . $e->getMessage();
            }
        }
    }
}

// Obtener productos y categorías
try {
    $stmt = $pdo->query("
        SELECT p.*, c.nombre as categoria_nombre 
        FROM productos p 
        LEFT JOIN categorias c ON p.categoria_id = c.id 
        ORDER BY p.nombre ASC
    ");
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt_categorias = $pdo->query("SELECT id, nombre FROM categorias ORDER BY nombre ASC");
    $categorias = $stmt_categorias->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $productos = [];
    $categorias = [];
    $error = "Error al cargar productos: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Productos - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
                        <h1 class="text-2xl font-bold text-gray-900">Gestión de Productos</h1>
                        <p class="text-sm text-gray-500">Panel de administración</p>
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
                    <a href="admin_dashboard.php" class="flex items-center px-3 py-2 text-sm font-medium text-blue-100 hover:text-white hover:bg-blue-700 rounded-md">
                        <i class="fas fa-chart-line mr-2"></i>
                        Dashboard
                    </a>
                    <a href="admin_productos.php" class="flex items-center px-3 py-2 text-sm font-medium text-white bg-blue-700 rounded-md">
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

    <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <?php if (!empty($mensaje)): ?>
            <div class="mb-6 p-4 rounded-md <?php echo strpos($mensaje, 'Error') !== false ? 'bg-red-100 border border-red-400 text-red-700' : 'bg-green-100 border border-green-400 text-green-700'; ?>">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Formulario para crear/editar producto -->
            <div class="lg:col-span-1">
                <div class="bg-white shadow rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900" id="formTitle">Crear Nuevo Producto</h3>
                    </div>
                    <div class="p-6">
                        <form method="POST" action="admin_productos.php" id="productForm">
                            <input type="hidden" name="accion" id="accion" value="crear_producto">
                            <input type="hidden" name="producto_id" id="producto_id" value="">
                            
                            <div class="space-y-4">
                                <div>
                                    <label for="nombre" class="block text-sm font-medium text-gray-700">Nombre del Producto *</label>
                                    <input type="text" name="nombre" id="nombre" required
                                           class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                
                                <div>
                                    <label for="descripcion" class="block text-sm font-medium text-gray-700">Descripción</label>
                                    <textarea name="descripcion" id="descripcion" rows="3"
                                              class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500"></textarea>
                                </div>
                                
                                <div>
                                    <label for="precio" class="block text-sm font-medium text-gray-700">Precio *</label>
                                    <input type="number" name="precio" id="precio" step="0.01" min="0" required
                                           class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                
                                <div>
                                    <label for="stock" class="block text-sm font-medium text-gray-700">Stock</label>
                                    <input type="number" name="stock" id="stock" min="0" value="0"
                                           class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                
                                <div>
                                    <label for="categoria_id" class="block text-sm font-medium text-gray-700">Categoría</label>
                                    <select name="categoria_id" id="categoria_id"
                                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                        <option value="0">Sin categoría</option>
                                        <?php foreach ($categorias as $categoria): ?>
                                            <option value="<?php echo $categoria['id']; ?>"><?php echo htmlspecialchars($categoria['nombre']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div>
                                    <label for="sku" class="block text-sm font-medium text-gray-700">SKU</label>
                                    <input type="text" name="sku" id="sku"
                                           class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                           placeholder="Dejar vacío para generar automáticamente">
                                </div>
                                
                                <div class="flex space-x-3">
                                    <button type="submit" class="flex-1 bg-blue-600 border border-transparent rounded-md shadow-sm py-2 px-4 inline-flex justify-center text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        <i class="fas fa-save mr-2"></i>
                                        <span id="submitText">Crear Producto</span>
                                    </button>
                                    <button type="button" id="cancelEdit" class="hidden bg-gray-500 border border-transparent rounded-md shadow-sm py-2 px-4 inline-flex justify-center text-sm font-medium text-white hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                                        Cancelar
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Lista de productos -->
            <div class="lg:col-span-2">
                <div class="bg-white shadow rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Lista de Productos</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Producto</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Precio</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Categoría</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (!empty($productos)): ?>
                                    <?php foreach ($productos as $producto): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="ml-4">
                                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($producto['nombre']); ?></div>
                                                        <div class="text-sm text-gray-500">SKU: <?php echo htmlspecialchars($producto['sku']); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">$<?php echo number_format($producto['precio'], 2); ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $producto['stock'] > 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                    <?php echo $producto['stock']; ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($producto['categoria_nombre'] ?? 'Sin categoría'); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <button onclick="editarProducto(<?php echo htmlspecialchars(json_encode($producto)); ?>)" 
                                                        class="text-blue-600 hover:text-blue-900 mr-3">
                                                    <i class="fas fa-edit"></i> Editar
                                                </button>
                                                <form method="POST" action="admin_productos.php" class="inline" onsubmit="return confirm('¿Estás seguro de que quieres eliminar este producto?');">
                                                    <input type="hidden" name="accion" value="eliminar_producto">
                                                    <input type="hidden" name="producto_id" value="<?php echo $producto['id']; ?>">
                                                    <button type="submit" class="text-red-600 hover:text-red-900">
                                                        <i class="fas fa-trash"></i> Eliminar
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">
                                            No hay productos registrados
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        function editarProducto(producto) {
            document.getElementById('formTitle').textContent = 'Editar Producto';
            document.getElementById('accion').value = 'editar_producto';
            document.getElementById('producto_id').value = producto.id;
            document.getElementById('nombre').value = producto.nombre;
            document.getElementById('descripcion').value = producto.descripcion || '';
            document.getElementById('precio').value = producto.precio;
            document.getElementById('stock').value = producto.stock;
            document.getElementById('categoria_id').value = producto.categoria_id || 0;
            document.getElementById('sku').value = producto.sku;
            document.getElementById('submitText').textContent = 'Actualizar Producto';
            document.getElementById('cancelEdit').classList.remove('hidden');
            
            // Scroll al formulario
            document.getElementById('productForm').scrollIntoView({ behavior: 'smooth' });
        }
        
        document.getElementById('cancelEdit').addEventListener('click', function() {
            resetForm();
        });
        
        function resetForm() {
            document.getElementById('formTitle').textContent = 'Crear Nuevo Producto';
            document.getElementById('accion').value = 'crear_producto';
            document.getElementById('producto_id').value = '';
            document.getElementById('productForm').reset();
            document.getElementById('submitText').textContent = 'Crear Producto';
            document.getElementById('cancelEdit').classList.add('hidden');
        }
    </script>
</body>
</html>