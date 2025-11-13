<?php
session_start();
require_once 'conexion.php'; 

// --- VERIFICACIÓN DE SESIÓN MEJORADA ---
// Si no está logueado, puede seguir como invitado pero no puede comprar
if (!isset($_SESSION['usuario_id'])) {
    // Usuario no logueado - puede ver pero no comprar
    $usuario_id = null;
    $puede_comprar = false;
} else {
    $usuario_id = $_SESSION['usuario_id'];
    $puede_comprar = true;
    
    // Si es admin y está en la tienda, mostrar opción para ir al panel
    if ($_SESSION['rol'] === 'admin' && basename($_SERVER['PHP_SELF']) === 'tienda.php') {
        $es_admin = true;
    } else {
        $es_admin = false;
    }
}

// Inicializar el carrito
if (!isset($_SESSION['carrito'])) {
    $_SESSION['carrito'] = [];
}

$mensaje = '';
$productos = [];
$categorias = [];
$categoria_actual = $_GET['categoria'] ?? 'todas';
$locales = [
    1 => ['nombre' => 'Local 1', 'direccion' => 'Calle 30 #5-20, Barrio Alfonso López.'],
    2 => ['nombre' => 'Local 2', 'direccion' => 'Carrera 10 #18-50, Centro (Cerca al Parque Centenario).'],
    3 => ['nombre' => 'Local 3', 'direccion' => 'Avenida El Trabajo #28-15, Ciudadela Mía.'],
];

// Costo de envío a domicilio en pesos colombianos
$costo_domicilio = 5000; // $5.000 pesos

// -----------------------------------------------------------
// 1. MANEJO DE ACCIONES 
// -----------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'agregar_carrito') {
        $producto_id = (int)$_POST['producto_id'];
        $cantidad = (int)$_POST['cantidad'] ?? 1;

        if ($cantidad > 0) {
            // Verificar stock disponible
            $stmt = $pdo->prepare("SELECT stock FROM productos WHERE id = ?");
            $stmt->execute([$producto_id]);
            $producto = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($producto) {
                $stock_disponible = $producto['stock'];
                $cantidad_actual = $_SESSION['carrito'][$producto_id] ?? 0;
                $nueva_cantidad = $cantidad_actual + $cantidad;
                
                if ($nueva_cantidad <= $stock_disponible) {
                    $_SESSION['carrito'][$producto_id] = $nueva_cantidad;
                    $mensaje = 'Producto agregado al carrito.';
                } else {
                    $mensaje = 'No hay suficiente stock disponible.';
                }
            }
        }
    }

    if ($accion === 'actualizar_carrito') {
        foreach ($_POST['cantidades'] as $id => $cantidad) {
            $id = (int)$id;
            $cantidad = (int)$cantidad;
            
            if ($cantidad > 0) {
                // Verificar stock disponible antes de actualizar
                $stmt = $pdo->prepare("SELECT stock FROM productos WHERE id = ?");
                $stmt->execute([$id]);
                $producto = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($producto && $cantidad <= $producto['stock']) {
                    $_SESSION['carrito'][$id] = $cantidad;
                } else {
                    $mensaje = 'No hay suficiente stock para ' . htmlspecialchars($producto['nombre'] ?? 'el producto');
                    $_SESSION['carrito'][$id] = min($cantidad, $producto['stock'] ?? 0);
                }
            } else {
                unset($_SESSION['carrito'][$id]);
            }
        }
        if (empty($mensaje)) {
            $mensaje = 'Carrito actualizado.';
        }
    }

    if ($accion === 'finalizar_pedido' && !empty($_SESSION['carrito'])) {
        $tipo_entrega = $_POST['tipo_entrega'] ?? 'recogida';
        $local_id = ($tipo_entrega === 'recogida') ? (int)$_POST['local_recogida'] : null;
        $direccion_domicilio = ($tipo_entrega === 'domicilio') ? trim($_POST['direccion_domicilio']) : null;
        
        // Validaciones
        if ($tipo_entrega === 'recogida' && !$local_id) {
            $mensaje = 'Debe seleccionar un local de recogida válido.';
        } elseif ($tipo_entrega === 'domicilio' && empty($direccion_domicilio)) {
            $mensaje = 'Debe ingresar una dirección para la entrega a domicilio.';
        } else {
            try {
                $pdo->beginTransaction();
                
                $codigo_recibo = strtoupper(substr(md5(time() . $usuario_id), 0, 8));
                $fecha_pedido = date('Y-m-d H:i:s');
                $total_pedido = 0;
                $productos_carrito = [];
                
                // Obtener información de productos y verificar stock
                $ids = implode(',', array_keys($_SESSION['carrito']));
                $stmt = $pdo->query("SELECT id, nombre, precio, stock FROM productos WHERE id IN ($ids)");
                $productos_db = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Crear un array indexado por ID para fácil acceso
                $productos_por_id = [];
                foreach ($productos_db as $producto) {
                    $productos_por_id[$producto['id']] = $producto;
                }
                
                // Verificar stock y calcular total
                foreach ($_SESSION['carrito'] as $id => $cantidad) {
                    if (isset($productos_por_id[$id])) {
                        $producto = $productos_por_id[$id];
                        
                        if ($cantidad > $producto['stock']) {
                            throw new Exception("No hay suficiente stock para: " . $producto['nombre']);
                        }
                        
                        $subtotal = $producto['precio'] * $cantidad;
                        $total_pedido += $subtotal;
                        $productos_carrito[] = [
                            'id' => $id,
                            'nombre' => $producto['nombre'],
                            'precio' => $producto['precio'],
                            'cantidad' => $cantidad,
                            'subtotal' => $subtotal
                        ];
                    }
                }
                
                // Agregar costo de domicilio si aplica
                $costo_envio = 0;
                if ($tipo_entrega === 'domicilio') {
                    $costo_envio = $costo_domicilio;
                    $total_pedido += $costo_envio;
                }
                
                // Primero verificar si la tabla tiene las columnas necesarias
                $stmt_check = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'pedidos'");
                $columnas_pedidos = $stmt_check->fetchAll(PDO::FETCH_COLUMN);
                
                // Insertar pedido en la base de datos (versión adaptativa)
                if (in_array('tipo_entrega', $columnas_pedidos) && in_array('direccion_entrega', $columnas_pedidos) && in_array('costo_envio', $columnas_pedidos)) {
                    // Si la tabla tiene las nuevas columnas
                    $stmt_pedido = $pdo->prepare("
                        INSERT INTO pedidos (usuario_id, local_id, codigo_recibo, fecha_pedido, total, estado, tipo_entrega, direccion_entrega, costo_envio) 
                        VALUES (?, ?, ?, ?, ?, 'pendiente', ?, ?, ?)
                    ");
                    $stmt_pedido->execute([
                        $usuario_id, 
                        $local_id, 
                        $codigo_recibo, 
                        $fecha_pedido, 
                        $total_pedido, 
                        $tipo_entrega, 
                        $direccion_domicilio,
                        $costo_envio
                    ]);
                } else {
                    // Si la tabla no tiene las nuevas columnas
                    $stmt_pedido = $pdo->prepare("
                        INSERT INTO pedidos (usuario_id, local_id, codigo_recibo, fecha_pedido, total, estado) 
                        VALUES (?, ?, ?, ?, ?, 'pendiente')
                    ");
                    $stmt_pedido->execute([
                        $usuario_id, 
                        $local_id, 
                        $codigo_recibo, 
                        $fecha_pedido, 
                        $total_pedido
                    ]);
                }
                
                $pedido_id = $pdo->lastInsertId();
                
                // Insertar detalles del pedido y actualizar stock
                foreach ($_SESSION['carrito'] as $producto_id => $cantidad) {
                    if (isset($productos_por_id[$producto_id])) {
                        $producto = $productos_por_id[$producto_id];
                        
                        // Insertar detalle del pedido
                        $stmt_detalle = $pdo->prepare("
                            INSERT INTO pedido_detalles (pedido_id, producto_id, cantidad, precio_unitario) 
                            VALUES (?, ?, ?, ?)
                        ");
                        $stmt_detalle->execute([$pedido_id, $producto_id, $cantidad, $producto['precio']]);
                        
                        // Actualizar stock del producto
                        $nuevo_stock = $producto['stock'] - $cantidad;
                        $stmt_update = $pdo->prepare("UPDATE productos SET stock = ? WHERE id = ?");
                        $stmt_update->execute([$nuevo_stock, $producto_id]);
                    }
                }
                
                $pdo->commit();
                
                // Preparar datos para el recibo
                $recibo_data = [
                    'codigo' => $codigo_recibo,
                    'fecha' => $fecha_pedido,
                    'usuario_id' => $usuario_id,
                    'total' => $total_pedido,
                    'productos' => $productos_carrito,
                    'tipo_entrega' => $tipo_entrega,
                    'costo_envio' => $costo_envio
                ];
                
                if ($tipo_entrega === 'recogida') {
                    $recibo_data['local'] = $locales[$local_id];
                } else {
                    $recibo_data['direccion'] = $direccion_domicilio;
                }
                
                // Guardar recibo en sesión para mostrar
                $_SESSION['recibo'] = $recibo_data;
                
                // Registrar en auditoría si la función existe
                if (function_exists('log_auditoria')) {
                    log_auditoria($pdo, $_SESSION['usuario_id'], "Pedido finalizado (Recibo: $codigo_recibo)", 'pedidos', $pedido_id);
                }

                // Limpiar carrito
                unset($_SESSION['carrito']);
                
                header('Location: tienda.php?recibo=1');
                exit;
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $mensaje = 'Error al procesar el pedido: ' . $e->getMessage();
            }
        }
    }
}

// -----------------------------------------------------------
// 2. OBTENER CATEGORÍAS Y PRODUCTOS
// -----------------------------------------------------------
try {
    // Obtener categorías
    $stmt_categorias = $pdo->query("SELECT id, nombre, descripcion FROM categorias ORDER BY nombre ASC");
    $categorias = $stmt_categorias->fetchAll();

    // Obtener productos según categoría
    if ($categoria_actual === 'todas') {
        $stmt = $pdo->query("SELECT p.id, p.sku, p.nombre, p.descripcion, p.precio, p.stock, c.nombre as categoria_nombre 
                           FROM productos p 
                           LEFT JOIN categorias c ON p.categoria_id = c.id 
                           WHERE p.stock > 0 
                           ORDER BY p.nombre ASC");
    } else {
        $stmt = $pdo->prepare("SELECT p.id, p.sku, p.nombre, p.descripcion, p.precio, p.stock, c.nombre as categoria_nombre 
                             FROM productos p 
                             LEFT JOIN categorias c ON p.categoria_id = c.id 
                             WHERE p.categoria_id = ? AND p.stock > 0 
                             ORDER BY p.nombre ASC");
        $stmt->execute([$categoria_actual]);
    }
    $productos = $stmt->fetchAll();
} catch (PDOException $e) {
    $productos = []; 
    $categorias = [];
    $mensaje = "Error al cargar el catálogo: " . $e->getMessage();
}

// -----------------------------------------------------------
// 3. MOSTRAR RECIBO 
// -----------------------------------------------------------
if (isset($_GET['recibo']) && isset($_SESSION['recibo'])) {
    $recibo = $_SESSION['recibo'];
    unset($_SESSION['recibo']);
}

// -----------------------------------------------------------
// 4. PREPARAR DATOS DEL CARRITO
// -----------------------------------------------------------
$carrito_detalles = [];
$carrito_total = 0;
if (!empty($_SESSION['carrito']) && empty($recibo)) {
    $ids = implode(',', array_keys($_SESSION['carrito']));
    $stmt = $pdo->query("SELECT id, nombre, precio, stock FROM productos WHERE id IN ($ids)");
    $productos_db = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($productos_db as $producto) {
        $cantidad = $_SESSION['carrito'][$producto['id']];
        $subtotal = $producto['precio'] * $cantidad;
        $carrito_total += $subtotal;
        $carrito_detalles[] = [
            'id' => $producto['id'],
            'nombre' => $producto['nombre'],
            'precio' => $producto['precio'],
            'cantidad' => $cantidad,
            'stock_disponible' => $producto['stock'],
            'subtotal' => $subtotal
        ];
    }
}

function obtener_url_imagen($id, $categoria_id) {
    $imagenes_por_categoria = [
        1 => ['🔨', '🛠️', '⚒️', '📏', '🔧'],
        2 => ['🔌', '💡', '⚡', '🔋', '📟'],
        3 => ['🚰', '🔩', '🛁', '🚿', '💧']
    ];
    
    $iconos = $imagenes_por_categoria[$categoria_id] ?? ['📦'];
    $icono_index = $id % count($iconos);
    
    return $iconos[$icono_index];
}

// Función para formatear precios en pesos colombianos
function formato_pesos($precio) {
    return '$' . number_format($precio, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tienda Ferretería - ¡Compra en Línea!</title>
    <link rel="stylesheet" href="estilo.css">
    <style>
        .tipo-entrega-option {
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .tipo-entrega-option:hover {
            border-color: #4299e1;
            background-color: #f7fafc;
        }
        .tipo-entrega-option.selected {
            border-color: #4299e1;
            background-color: #ebf8ff;
        }
        .costo-domicilio {
            color: #e53e3e;
            font-weight: bold;
            margin-left: 10px;
        }
        .hidden {
            display: none;
        }
        .error {
            color: #e53e3e;
            font-size: 0.9em;
            margin-top: 5px;
        }
        
        /* Estilos del Chatbot */
        .chatbot-container {
            position: fixed;
            bottom: 100px;
            right: 25px;
            width: 350px;
            height: 500px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            display: none;
            flex-direction: column;
            z-index: 1000;
            border: 1px solid #e2e8f0;
        }

        .chatbot-container.open {
            display: flex;
        }

        .chatbot-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-radius: 15px 15px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .chatbot-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
        }

        .chatbot-title i {
            font-size: 1.2em;
        }

        .chatbot-toggle {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            padding: 5px;
        }

        .chatbot-messages {
            flex: 1;
            padding: 15px;
            overflow-y: auto;
            background: #f8fafc;
        }

        .chat-message {
            display: flex;
            margin-bottom: 15px;
            gap: 10px;
        }

        .bot-message {
            align-items: flex-start;
        }

        .user-message {
            align-items: flex-end;
            flex-direction: row-reverse;
        }

        .message-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .bot-message .message-avatar {
            background: #667eea;
            color: white;
        }

        .user-message .message-avatar {
            background: #48bb78;
            color: white;
        }

        .message-content {
            max-width: 80%;
            padding: 12px 15px;
            border-radius: 18px;
            line-height: 1.4;
            white-space: pre-line;
        }

        .bot-message .message-content {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 18px 18px 18px 5px;
        }

        .user-message .message-content {
            background: #667eea;
            color: white;
            border-radius: 18px 18px 5px 18px;
        }

        .chatbot-input-container {
            display: flex;
            padding: 15px;
            border-top: 1px solid #e2e8f0;
            background: white;
            border-radius: 0 0 15px 15px;
        }

        .chatbot-input {
            flex: 1;
            padding: 12px 15px;
            border: 1px solid #e2e8f0;
            border-radius: 25px;
            outline: none;
            font-size: 14px;
        }

        .chatbot-input:focus {
            border-color: #667eea;
        }

        .chatbot-send {
            background: #667eea;
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            margin-left: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.3s;
        }

        .chatbot-send:hover {
            background: #5a6fd8;
        }

        .chatbot-launcher {
            position: fixed;
            bottom: 25px;
            right: 25px;
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
            z-index: 999;
            transition: transform 0.3s;
        }

        .chatbot-launcher:hover {
            transform: scale(1.1);
        }

        .chatbot-pulse {
            position: absolute;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: rgba(102, 126, 234, 0.4);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
                opacity: 1;
            }
            100% {
                transform: scale(1.5);
                opacity: 0;
            }
        }

        .typing-indicator {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 10px 15px;
        }

        .typing-dot {
            width: 8px;
            height: 8px;
            background: #667eea;
            border-radius: 50%;
            animation: typing 1.4s infinite;
        }

        .typing-dot:nth-child(2) {
            animation-delay: 0.2s;
        }

        .typing-dot:nth-child(3) {
            animation-delay: 0.4s;
        }

        @keyframes typing {
            0%, 60%, 100% {
                transform: translateY(0);
            }
            30% {
                transform: translateY(-5px);
            }
        }
    </style>
</head>
<body>
    <header class="main-header">
        <div class="header-content">
            <div class="logo">
                <span class="logo-icon">🔨</span>
                <span>Ferretería Online</span>
            </div>
            <nav class="main-nav">
                <ul>
                    <li><a href="tienda.php" class="<?php echo $categoria_actual === 'todas' ? 'active' : ''; ?>">Todos</a></li>
                    <?php foreach ($categorias as $categoria): ?>
                        <li>
                            <a href="tienda.php?categoria=<?php echo $categoria['id']; ?>" 
                               class="<?php echo $categoria_actual == $categoria['id'] ? 'active' : ''; ?>">
                                <?php echo htmlspecialchars($categoria['nombre']); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>
            <div class="user-menu">
                <?php if (isset($_SESSION['usuario_id'])): ?>
                    <span class="user-info">Hola, <?php echo htmlspecialchars($_SESSION['nombre_usuario']); ?></span>
                    <?php if ($_SESSION['rol'] === 'admin'): ?>
                        <a href="admin_dashboard.php" class="btn btn-outline btn-sm">Panel Admin</a>
                    <?php endif; ?>
                    <a href="cerrar_sesion.php" class="btn btn-outline btn-sm">Cerrar Sesión</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-outline btn-sm">Iniciar Sesión</a>
                    <a href="registro.php" class="btn btn-primary btn-sm">Registrarse</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <?php if (isset($recibo)): ?>
        <main class="container">
            <div class="card recibo-box" style="max-width: 600px; margin: 2rem auto;">
                <div class="card-header" style="text-align: center; background-color: var(--color-secondary); color: white;">
                    <h2 style="color: white; margin: 0;">🎉 Pedido Finalizado con Éxito</h2>
                </div>
                <div class="card-body">
                    <p>¡Gracias por tu compra! <?php echo $recibo['tipo_entrega'] === 'recogida' ? 'Tu pedido está listo para ser recogido en el local seleccionado.' : 'Tu pedido será enviado a tu domicilio.'; ?></p>
                    
                    <div class="form-group">
                        <label class="form-label">Código de Pedido Único:</label>
                        <div style="font-size: 2rem; font-weight: bold; color: var(--color-accent); text-align: center; padding: 1rem; border: 2px dashed var(--color-accent); border-radius: var(--border-radius);">
                            <?php echo htmlspecialchars($recibo['codigo']); ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Detalles de Entrega:</label>
                        <div style="padding: 1rem; background-color: var(--color-lighter); border-radius: var(--border-radius);">
                            <?php if ($recibo['tipo_entrega'] === 'recogida'): ?>
                                <strong>📍 Recogida en Local:</strong><br>
                                <strong>Local:</strong> <?php echo htmlspecialchars($recibo['local']['nombre']); ?><br>
                                <strong>Dirección:</strong> <?php echo htmlspecialchars($recibo['local']['direccion']); ?>
                            <?php else: ?>
                                <strong>🚚 Entrega a Domicilio:</strong><br>
                                <strong>Dirección:</strong> <?php echo htmlspecialchars($recibo['direccion']); ?><br>
                                <strong>Costo de envío:</strong> <?php echo formato_pesos($recibo['costo_envio']); ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Resumen de Compra:</label>
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th>Cantidad</th>
                                        <th>Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recibo['productos'] as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['nombre']); ?></td>
                                            <td><?php echo $item['cantidad']; ?></td>
                                            <td><?php echo formato_pesos($item['subtotal']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if ($recibo['tipo_entrega'] === 'domicilio'): ?>
                                        <tr>
                                            <td colspan="2" style="text-align: right;">Costo de envío:</td>
                                            <td><?php echo formato_pesos($recibo['costo_envio']); ?></td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot>
                                    <tr style="background-color: var(--color-lighter);">
                                        <td colspan="2" style="text-align: right; font-weight: bold;">Total:</td>
                                        <td style="font-weight: bold; color: var(--color-accent);"><?php echo formato_pesos($recibo['total']); ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    
                    <div style="text-align: center; margin-top: 2rem;">
                        <?php if ($recibo['tipo_entrega'] === 'recogida'): ?>
                            <p style="font-weight: bold;">Presenta el código de recogida en el local.</p>
                        <?php else: ?>
                            <p style="font-weight: bold;">Tu pedido será enviado en un plazo de 24-48 horas.</p>
                        <?php endif; ?>
                        <a href="tienda.php" class="btn btn-primary">Volver a la Tienda</a>
                    </div>
                </div>
            </div>
        </main>
    <?php else: ?>
        <main class="container">
            <div class="page-header">
                <h1 class="page-title">
                    <span class="page-title-icon">🛍️</span>
                    <?php 
                        if ($categoria_actual === 'todas') {
                            echo 'Todos los Productos';
                        } else {
                            $categoria_nombre = '';
                            foreach ($categorias as $cat) {
                                if ($cat['id'] == $categoria_actual) {
                                    $categoria_nombre = $cat['nombre'];
                                    break;
                                }
                            }
                            echo htmlspecialchars($categoria_nombre);
                        }
                    ?>
                </h1>
                <p>
                    <?php 
                        if ($categoria_actual === 'todas') {
                            echo 'Explora nuestro catálogo completo de ferretería';
                        } else {
                            $categoria_desc = '';
                            foreach ($categorias as $cat) {
                                if ($cat['id'] == $categoria_actual) {
                                    $categoria_desc = $cat['descripcion'];
                                    break;
                                }
                            }
                            echo htmlspecialchars($categoria_desc);
                        }
                    ?>
                </p>
            </div>

            <?php if (!empty($mensaje)): ?>
                <div class="alert alert-<?php echo strpos($mensaje, 'Error') !== false ? 'error' : 'success'; ?>">
                    <?php echo $mensaje; ?>
                </div>
            <?php endif; ?>

            <!-- Sección de Categorías -->
            <?php if ($categoria_actual === 'todas'): ?>
                <section style="margin-bottom: var(--spacing-xxl);">
                    <h2 style="margin-bottom: var(--spacing-lg);">Categorías</h2>
                    <div class="categorias-grid">
                        <?php foreach ($categorias as $categoria): ?>
                            <div class="categoria-card" onclick="window.location.href='tienda.php?categoria=<?php echo $categoria['id']; ?>'">
                                <div class="categoria-imagen">
                                    <?php 
                                        $iconos = ['🔨', '🔧', '🔌', '🚰'];
                                        $icono = $iconos[($categoria['id'] - 1) % count($iconos)] ?? '📦';
                                        echo $icono;
                                    ?>
                                </div>
                                <div class="categoria-info">
                                    <h3><?php echo htmlspecialchars($categoria['nombre']); ?></h3>
                                    <p><?php echo htmlspecialchars($categoria['descripcion']); ?></p>
                                    <div class="categoria-stats">
                                        <span>Ver productos</span>
                                        <span>→</span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <div class="shop-layout">
                <div class="products-grid">
                    <?php if (empty($productos)): ?>
                        <div class="card">
                            <div class="card-body" style="text-align: center;">
                                <h3>No hay productos disponibles</h3>
                                <p>No hay productos en esta categoría o no hay stock disponible.</p>
                                <a href="tienda.php" class="btn btn-primary">Ver Todas las Categorías</a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($productos as $producto): ?>
                        <div class="card product-card fade-in-up">
                            <div class="product-image" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-size: 4rem; display: flex; align-items: center; justify-content: center;">
                                <?php 
                                    // Determinar categoría_id basado en el nombre de categoría
                                    $categoria_id_map = [
                                        'Herramientas Manuales' => 1,
                                        'Electricidad' => 2,
                                        'Fontanería' => 3
                                    ];
                                    $cat_id = $categoria_id_map[$producto['categoria_nombre']] ?? 1;
                                    echo obtener_url_imagen($producto['id'], $cat_id); 
                                ?>
                            </div>
                            <div class="product-info">
                                <h3 class="product-title"><?php echo htmlspecialchars($producto['nombre']); ?></h3>
                                <p class="product-description"><?php echo htmlspecialchars($producto['descripcion']); ?></p>
                                <div style="font-size: 0.8em; color: var(--color-gray); margin-bottom: var(--spacing-md);">
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <span style="background: var(--color-light); padding: 2px 8px; border-radius: var(--border-radius-sm);">
                                            <?php echo htmlspecialchars($producto['categoria_nombre']); ?>
                                        </span>
                                        <span style="font-family: monospace; color: var(--color-gray-dark);">
                                            SKU: <?php echo htmlspecialchars($producto['sku']); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="product-price"><?php echo formato_pesos($producto['precio']); ?></div>
                                <div class="product-stock <?php echo $producto['stock'] > 10 ? 'in-stock' : 'out-of-stock'; ?>">
                                    <?php echo $producto['stock'] > 0 ? 'En stock: ' . $producto['stock'] . ' unidades' : 'Agotado'; ?>
                                </div>
                                
                                <?php if ($producto['stock'] > 0): ?>
                                    <form method="POST" action="tienda.php" style="margin-top: auto;">
                                        <input type="hidden" name="accion" value="agregar_carrito">
                                        <input type="hidden" name="producto_id" value="<?php echo $producto['id']; ?>">
                                        
                                        <div class="form-group" style="margin-bottom: var(--spacing-md);">
                                            <label for="cantidad_<?php echo $producto['id']; ?>" class="form-label">Cantidad:</label>
                                            <select id="cantidad_<?php echo $producto['id']; ?>" name="cantidad" class="form-control" required>
                                                <?php for ($i = 1; $i <= min(10, $producto['stock']); $i++): ?>
                                                    <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                        <button type="submit" class="btn btn-primary" style="width: 100%;">Agregar al Carrito</button>
                                    </form>
                                <?php else: ?>
                                    <button class="btn" disabled style="width: 100%;">No disponible</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <aside class="cart-sidebar">
                    <div class="card">
                        <div class="card-header">
                            <h3 style="margin: 0;">🛒 Carrito de Compras</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($carrito_detalles)): ?>
                                <p style="text-align: center; color: var(--color-gray);">Tu carrito está vacío.</p>
                            <?php else: ?>
                                <form method="POST" action="tienda.php" id="form-actualizar-carrito">
                                    <input type="hidden" name="accion" value="actualizar_carrito">
                                    
                                    <?php foreach ($carrito_detalles as $item): ?>
                                        <div class="form-group" style="display: flex; justify-content: space-between; align-items: center; padding: var(--spacing-sm) 0; border-bottom: 1px solid var(--color-light);">
                                            <div style="flex: 1;">
                                                <strong><?php echo htmlspecialchars($item['nombre']); ?></strong><br>
                                                <small><?php echo formato_pesos($item['precio']); ?> c/u</small>
                                                <?php if ($item['cantidad'] > $item['stock_disponible']): ?>
                                                    <br><small style="color: var(--color-danger);">Stock insuficiente</small>
                                                <?php endif; ?>
                                            </div>
                                            <input type="number" name="cantidades[<?php echo $item['id']; ?>]" 
                                                   value="<?php echo $item['cantidad']; ?>" 
                                                   min="0" 
                                                   max="<?php echo $item['stock_disponible']; ?>"
                                                   style="width: 60px; padding: var(--spacing-xs); border: 1px solid var(--color-gray); border-radius: var(--border-radius-sm);">
                                        </div>
                                    <?php endforeach; ?>
                                    
                                    <div style="font-size: 1.2rem; font-weight: bold; padding: var(--spacing-md) 0; border-top: 2px solid var(--color-primary); margin-top: var(--spacing-md);">
                                        Total: <?php echo formato_pesos($carrito_total); ?>
                                    </div>

                                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: var(--spacing-md);">Actualizar Carrito</button>
                                </form>

                                <div style="margin-top: var(--spacing-xl);">
                                    <h4>Finalizar Pedido</h4>
                                    <form method="POST" action="tienda.php" id="form-finalizar-pedido" onsubmit="return validarFormulario()">
                                        <input type="hidden" name="accion" value="finalizar_pedido">
                                        
                                        <div class="form-group">
                                            <label class="form-label">Tipo de Entrega:</label>
                                            
                                            <div class="tipo-entrega-option" onclick="seleccionarTipoEntrega('recogida')" id="option-recogida">
                                                <input type="radio" name="tipo_entrega" value="recogida" id="recogida" checked style="margin-right: 10px;">
                                                <label for="recogida" style="cursor: pointer; font-weight: bold;">
                                                    📍 Recogida en Local
                                                </label>
                                                <p style="margin: 5px 0 0 25px; color: var(--color-gray); font-size: 0.9em;">
                                                    Recoge tu pedido en uno de nuestros locales
                                                </p>
                                            </div>
                                            
                                            <div class="tipo-entrega-option" onclick="seleccionarTipoEntrega('domicilio')" id="option-domicilio">
                                                <input type="radio" name="tipo_entrega" value="domicilio" id="domicilio" style="margin-right: 10px;">
                                                <label for="domicilio" style="cursor: pointer; font-weight: bold;">
                                                    🚚 Entrega a Domicilio
                                                </label>
                                                <span class="costo-domicilio">+<?php echo formato_pesos($costo_domicilio); ?></span>
                                                <p style="margin: 5px 0 0 25px; color: var(--color-gray); font-size: 0.9em;">
                                                    Recibe tu pedido en la comodidad de tu hogar
                                                </p>
                                            </div>
                                        </div>
                                        
                                        <div class="form-group" id="seccion-local-recogida">
                                            <label for="local_recogida" class="form-label">Selecciona Local de Recogida:</label>
                                            <select id="local_recogida" name="local_recogida" class="form-control">
                                                <option value="">-- Seleccionar Local --</option>
                                                <?php foreach ($locales as $id => $local): ?>
                                                    <option value="<?php echo $id; ?>">
                                                        <?php echo htmlspecialchars($local['nombre']); ?> - <?php echo htmlspecialchars($local['direccion']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div id="error-local" class="error hidden">Debes seleccionar un local de recogida</div>
                                        </div>
                                        
                                        <div class="form-group hidden" id="seccion-domicilio">
                                            <label for="direccion_domicilio" class="form-label">Dirección de Entrega:</label>
                                            <textarea id="direccion_domicilio" name="direccion_domicilio" class="form-control" rows="3" placeholder="Ingresa tu dirección completa para la entrega..."></textarea>
                                            <div id="error-domicilio" class="error hidden">Debes ingresar tu dirección para la entrega a domicilio</div>
                                            <small style="color: var(--color-gray);">Costo de envío: <?php echo formato_pesos($costo_domicilio); ?></small>
                                        </div>
                                        
                                        <div style="font-size: 1.1rem; font-weight: bold; padding: var(--spacing-md) 0; text-align: center; background-color: var(--color-lighter); border-radius: var(--border-radius); margin: var(--spacing-md) 0;">
                                            Total Final: 
                                            <span id="total-final"><?php echo formato_pesos($carrito_total); ?></span>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-accent btn-lg" style="width: 100%;" id="btn-comprar">Comprar y Generar Recibo</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </aside>
            </div>
        </main>
    <?php endif; ?>

    <!-- Chatbot Widget -->
    <div id="chatbot-container" class="chatbot-container">
        <div id="chatbot-header" class="chatbot-header">
            <div class="chatbot-title">
                <i class="fas fa-robot"></i>
                <span>Asistente Virtual</span>
            </div>
            <button id="chatbot-toggle" class="chatbot-toggle">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div id="chatbot-messages" class="chatbot-messages">
            <div class="chat-message bot-message">
                <div class="message-avatar">
                    <i class="fas fa-robot"></i>
                </div>
                <div class="message-content">
                    ¡Hola! 👋 Soy tu asistente de ferretería. ¿En qué puedo ayudarte hoy? Puedo informarte sobre productos disponibles, verificar stock y ayudarte a encontrar lo que necesitas.
                </div>
            </div>
        </div>
        
        <div class="chatbot-input-container">
            <input type="text" id="chatbot-input" placeholder="Escribe tu pregunta..." class="chatbot-input">
            <button id="chatbot-send" class="chatbot-send">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </div>

    <!-- Botón flotante del chatbot -->
    <button id="chatbot-launcher" class="chatbot-launcher">
        <i class="fas fa-robot"></i>
        <span class="chatbot-pulse"></span>
    </button>

    <script>
        // Script para el sistema de compra
        function seleccionarTipoEntrega(tipo) {
            // Actualizar radio buttons
            document.getElementById(tipo).checked = true;
            
            // Actualizar estilos visuales
            document.querySelectorAll('.tipo-entrega-option').forEach(option => {
                option.classList.remove('selected');
            });
            document.getElementById('option-' + tipo).classList.add('selected');
            
            // Mostrar/ocultar secciones
            if (tipo === 'recogida') {
                document.getElementById('seccion-local-recogida').classList.remove('hidden');
                document.getElementById('seccion-domicilio').classList.add('hidden');
                document.getElementById('total-final').textContent = '<?php echo formato_pesos($carrito_total); ?>';
            } else {
                document.getElementById('seccion-local-recogida').classList.add('hidden');
                document.getElementById('seccion-domicilio').classList.remove('hidden');
                const totalConEnvio = <?php echo $carrito_total; ?> + <?php echo $costo_domicilio; ?>;
                document.getElementById('total-final').textContent = formatPesos(totalConEnvio);
            }
        }

        function formatPesos(precio) {
            return '$' + precio.toFixed(0).replace(/\d(?=(\d{3})+$)/g, '$&.');
        }

        function validarFormulario() {
            const tipoEntrega = document.querySelector('input[name="tipo_entrega"]:checked').value;
            let valido = true;

            // Limpiar errores anteriores
            document.querySelectorAll('.error').forEach(error => {
                error.classList.add('hidden');
            });

            if (tipoEntrega === 'recogida') {
                const local = document.getElementById('local_recogida').value;
                if (!local) {
                    document.getElementById('error-local').classList.remove('hidden');
                    valido = false;
                }
            } else {
                const direccion = document.getElementById('direccion_domicilio').value.trim();
                if (!direccion) {
                    document.getElementById('error-domicilio').classList.remove('hidden');
                    valido = false;
                }
            }

            if (!valido) {
                // Scroll to first error
                const firstError = document.querySelector('.error:not(.hidden)');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }

            return valido;
        }

        // Inicializar sistema de compra
        document.addEventListener('DOMContentLoaded', function() {
            seleccionarTipoEntrega('recogida');
        });

        // Script para el chatbot
        document.addEventListener('DOMContentLoaded', function() {
            const chatbotContainer = document.getElementById('chatbot-container');
            const chatbotToggle = document.getElementById('chatbot-toggle');
            const chatbotLauncher = document.getElementById('chatbot-launcher');
            const chatbotMessages = document.getElementById('chatbot-messages');
            const chatbotInput = document.getElementById('chatbot-input');
            const chatbotSend = document.getElementById('chatbot-send');
            
            // Alternar visibilidad del chatbot
            chatbotLauncher.addEventListener('click', () => {
                chatbotContainer.classList.add('open');
                chatbotLauncher.style.display = 'none';
                chatbotInput.focus();
            });
            
            chatbotToggle.addEventListener('click', () => {
                chatbotContainer.classList.remove('open');
                chatbotLauncher.style.display = 'flex';
            });
            
            // Enviar mensaje
            function enviarMensaje() {
                const mensaje = chatbotInput.value.trim();
                if (!mensaje) return;
                
                // Agregar mensaje del usuario
                agregarMensajeUsuario(mensaje);
                chatbotInput.value = '';
                
                // Mostrar indicador de typing
                mostrarTyping();
                
                // Enviar al servidor
                fetch('chatbot.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ mensaje: mensaje })
                })
                .then(response => response.json())
                .then(data => {
                    ocultarTyping();
                    if (data.error) {
                        agregarMensajeBot('Lo siento, hubo un error. Por favor intenta nuevamente.');
                    } else {
                        agregarMensajeBot(data.respuesta);
                    }
                })
                .catch(error => {
                    ocultarTyping();
                    agregarMensajeBot('Error de conexión. Por favor intenta nuevamente.');
                });
            }
            
            // Event listeners para enviar mensaje
            chatbotSend.addEventListener('click', enviarMensaje);
            chatbotInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    enviarMensaje();
                }
            });
            
            // Funciones auxiliares
            function agregarMensajeUsuario(mensaje) {
                const messageDiv = document.createElement('div');
                messageDiv.className = 'chat-message user-message';
                messageDiv.innerHTML = `
                    <div class="message-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="message-content">${mensaje}</div>
                `;
                chatbotMessages.appendChild(messageDiv);
                scrollToBottom();
            }
            
            function agregarMensajeBot(mensaje) {
                const messageDiv = document.createElement('div');
                messageDiv.className = 'chat-message bot-message';
                messageDiv.innerHTML = `
                    <div class="message-avatar">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div class="message-content">${mensaje}</div>
                `;
                chatbotMessages.appendChild(messageDiv);
                scrollToBottom();
            }
            
            function mostrarTyping() {
                const typingDiv = document.createElement('div');
                typingDiv.className = 'chat-message bot-message';
                typingDiv.id = 'typing-indicator';
                typingDiv.innerHTML = `
                    <div class="message-avatar">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div class="message-content">
                        <div class="typing-indicator">
                            <div class="typing-dot"></div>
                            <div class="typing-dot"></div>
                            <div class="typing-dot"></div>
                        </div>
                    </div>
                `;
                chatbotMessages.appendChild(typingDiv);
                scrollToBottom();
            }
            
            function ocultarTyping() {
                const typing = document.getElementById('typing-indicator');
                if (typing) {
                    typing.remove();
                }
            }
            
            function scrollToBottom() {
                chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
            }
            
            // Mensaje de bienvenida automático después de 3 segundos
            setTimeout(() => {
                if (!chatbotContainer.classList.contains('open')) {
                    chatbotLauncher.querySelector('.chatbot-pulse').style.animation = 'pulse 1s infinite';
                }
            }, 3000);
        });
    </script>
</body>
</html>