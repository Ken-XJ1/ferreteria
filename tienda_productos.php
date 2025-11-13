<?php
session_start();
require_once 'conexion.php';

// Inicializar el carrito si no existe
if (!isset($_SESSION['carrito'])) {
    $_SESSION['carrito'] = [];
}

$mensaje = "";

// --- Lógica para AÑADIR al Carrito ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['accion']) && $_POST['accion'] == 'agregar') {
    $producto_id = intval($_POST['producto_id']);
    $cantidad = max(1, intval($_POST['cantidad'])); // Asegurar que sea al menos 1

    try {
        $stmt = $pdo->prepare("SELECT id, nombre, precio, stock FROM productos WHERE id = ?");
        $stmt->execute([$producto_id]);
        $producto = $stmt->fetch();

        if ($producto) {
            if ($cantidad > $producto['stock']) {
                $mensaje = "Error: Solo quedan {$producto['stock']} unidades de {$producto['nombre']}.";
            } else {
                // Estructura del carrito: ['id' => ['nombre', 'precio', 'cantidad']]
                if (isset($_SESSION['carrito'][$producto_id])) {
                    $_SESSION['carrito'][$producto_id]['cantidad'] += $cantidad;
                } else {
                    $_SESSION['carrito'][$producto_id] = [
                        'nombre' => $producto['nombre'],
                        'precio' => $producto['precio'],
                        'cantidad' => $cantidad
                    ];
                }
                $mensaje = "Éxito: {$cantidad}x {$producto['nombre']} añadido al carrito.";
            }
        } else {
            $mensaje = "Error: Producto no encontrado.";
        }
    } catch (PDOException $e) {
        $mensaje = "Error de BD: " . $e->getMessage();
    }
}
// --- Fin Lógica Carrito ---


// --- Lógica para Cargar Categorías y Productos ---
try {
    // Consulta para obtener categorías y productos relacionados en una sola pasada
    $stmt_data = $pdo->query("
        SELECT 
            c.id as categoria_id,
            c.nombre as categoria_nombre,
            p.id as producto_id,
            p.nombre as producto_nombre,
            p.precio,
            p.stock
        FROM categorias c
        JOIN productos p ON c.id = p.categoria_id
        ORDER BY c.nombre, p.nombre
    ");
    $resultados = $stmt_data->fetchAll();

    $categorias = [];
    foreach ($resultados as $row) {
        $categorias[$row['categoria_id']]['nombre'] = $row['categoria_nombre'];
        $categorias[$row['categoria_id']]['productos'][] = [
            'id' => $row['producto_id'],
            'nombre' => $row['producto_nombre'],
            'precio' => $row['precio'],
            'stock' => $row['stock']
        ];
    }
} catch (PDOException $e) {
    die("Error al cargar la tienda: " . $e->getMessage());
}

// Calcular el total del carrito para mostrar en la cabecera
$total_carrito = 0;
$items_carrito = 0;
foreach ($_SESSION['carrito'] as $item) {
    $total_carrito += $item['precio'] * $item['cantidad'];
    $items_carrito += $item['cantidad'];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tienda Ferretería</title>
   <link rel="stylesheet" href="estilos.css">
</head>
<body>

    <div class="barra-superior">
        <div>Tienda Ferretería</div>
        <div>
            Carrito: <?php echo $items_carrito; ?> artículos (Total: $<?php echo number_format($total_carrito, 2); ?>)
            <a href="checkout.php" style="margin-left: 15px;">Finalizar Compra &raquo;</a>
        </div>
    </div>

    <div class="contenedor">
        <h1>Productos Disponibles</h1>

        <?php if (!empty($mensaje)): ?>
            <div class="mensaje <?php echo strpos($mensaje, 'Error') !== false ? 'error' : 'exito'; ?>">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <?php foreach ($categorias as $cat): ?>
            <div class="categoria-titulo">
                <h2><?php echo htmlspecialchars($cat['nombre']); ?></h2>
            </div>
            <div class="productos-grid">
                <?php foreach ($cat['productos'] as $prod): ?>
                    <div class="producto-card">
                        <h4><?php echo htmlspecialchars($prod['nombre']); ?></h4>
                        <p class="precio">$<?php echo number_format($prod['precio'], 2); ?></p>
                        <p>
                            Stock: 
                            <span class="<?php echo ($prod['stock'] < 10) ? 'stock-bajo' : 'stock-alto'; ?>">
                                <?php echo $prod['stock']; ?>
                            </span>
                        </p>

                        <?php if ($prod['stock'] > 0): ?>
                            <form method="POST">
                                <input type="hidden" name="accion" value="agregar">
                                <input type="hidden" name="producto_id" value="<?php echo $prod['id']; ?>">
                                Cantidad: <input type="number" name="cantidad" value="1" min="1" max="<?php echo $prod['stock']; ?>" style="width: 60px; margin-right: 10px;">
                                <button type="submit" class="btn-agregar">Añadir al Carrito</button>
                            </form>
                        <?php else: ?>
                            <p class="stock-bajo">Agotado</p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>

</body>
</html>