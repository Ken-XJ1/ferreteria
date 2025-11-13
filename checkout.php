<?php
session_start();
require_once 'conexion.php';

// Función auxiliar para generar un código único de recibo
function generarCodigoRecibo($length = 8) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $code;
}

$mensaje = "";
$usuario_id = $_SESSION['usuario_id'] ?? NULL; // Permite checkout como invitado

// --- Lógica para FINALIZAR PEDIDO (POST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['accion']) && $_POST['accion'] == 'finalizar') {
    $local_id = intval($_POST['local_id'] ?? 0);
    
    if (empty($_SESSION['carrito'])) {
        $mensaje = "Error: El carrito está vacío.";
    } elseif ($local_id <= 0) {
        $mensaje = "Error: Debe seleccionar un local de recolección.";
    } else {
        
        try {
            // 1. Verificar disponibilidad del local (doble check)
            $stmt_local = $pdo->prepare("SELECT disponible FROM locales WHERE id = ?");
            $stmt_local->execute([$local_id]);
            $local = $stmt_local->fetch();
            
            if (!$local || !$local['disponible']) {
                $mensaje = "Error: El local seleccionado ya no está disponible.";
            } else {

                // --- INICIO DE TRANSACCIÓN ---
                // Usamos transacciones para garantizar que todos los datos (pedido, detalles, stock) 
                // se guarden o ninguno.
                $pdo->beginTransaction();

                $codigo_recibo = generarCodigoRecibo();
                $total_pedido = 0;
                
                // Calcular el total
                foreach ($_SESSION['carrito'] as $item) {
                    $total_pedido += $item['precio'] * $item['cantidad'];
                }

                // 2. Insertar el Pedido principal
                $stmt_pedido = $pdo->prepare("INSERT INTO pedidos (usuario_id, local_id, codigo_recibo, total) VALUES (?, ?, ?, ?)");
                $stmt_pedido->execute([$usuario_id, $local_id, $codigo_recibo, $total_pedido]);
                $pedido_id = $pdo->lastInsertId('pedidos_id_seq'); // Obtener el ID del pedido recién creado

                // 3. Insertar Detalles y Actualizar Stock
                foreach ($_SESSION['carrito'] as $producto_id => $item) {
                    // a. Insertar Detalle
                    $stmt_detalle = $pdo->prepare("INSERT INTO detalles_pedido (pedido_id, producto_id, cantidad, precio_unitario) VALUES (?, ?, ?, ?)");
                    $stmt_detalle->execute([$pedido_id, $producto_id, $item['cantidad'], $item['precio']]);
                    
                    // b. Actualizar Stock (Restar cantidad)
                    $stmt_stock = $pdo->prepare("UPDATE productos SET stock = stock - ? WHERE id = ? AND stock >= ?");
                    if ($stmt_stock->execute([$item['cantidad'], $producto_id, $item['cantidad']]) === false || $stmt_stock->rowCount() === 0) {
                        // Si la actualización falla (ej. stock insuficiente), forzamos un error y ROLLBACK.
                         throw new Exception("Stock insuficiente para el producto: " . $item['nombre']);
                    }
                }
                
                // 4. Si todo es exitoso, confirmar la transacción
                $pdo->commit();

                // 5. Limpiar carrito y redirigir con mensaje de éxito/recibo
                unset($_SESSION['carrito']);
                header("Location: checkout.php?exito=1&codigo=$codigo_recibo");
                exit;
            }
        } catch (Exception $e) {
            // Si algo falla, revertir todas las operaciones de la BD
            $pdo->rollBack();
            $mensaje = "Error al procesar el pedido: " . $e->getMessage();
        }
    }
}
// --- Fin Lógica Finalizar Pedido ---


// --- Lógica para Cargar Datos (GET/Vista) ---

// Redirigir si el carrito está vacío y no hay mensaje de éxito
if (empty($_SESSION['carrito']) && !isset($_GET['exito'])) {
    header('Location: tienda.php');
    exit;
}

// Cargar Locales
try {
    $stmt_locales = $pdo->query("SELECT id, nombre, direccion, disponible FROM locales ORDER BY nombre ASC");
    $locales = $stmt_locales->fetchAll();
} catch (PDOException $e) {
    die("Error al cargar locales: " . $e->getMessage());
}

// Calcular totales para la vista
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
    <title>Finalizar Compra - Ferretería</title>
   <link rel="stylesheet" href="estilos.css">
</head>
<body>

    <div class="barra-superior">
        <a href="tienda.php"> &larr; Seguir Comprando</a>
    </div>

    <div class="contenedor">
        
        <?php if (!empty($mensaje)): ?>
            <div class="mensaje error"><?php echo $mensaje; ?></div>
        <?php endif; ?>

        <?php if (isset($_GET['exito']) && isset($_GET['codigo'])): 
            // --- VISTA DEL RECIBO FINAL ---
            $codigo_final = htmlspecialchars($_GET['codigo']);
        ?>
            <h1>✅ Pedido Finalizado con Éxito</h1>
            <div class="recibo-box">
                <h3>¡Gracias por tu compra!</h3>
                <p>Tu pedido ha sido registrado y está siendo preparado.</p>
                <p>Muestra el siguiente **Código de Recibo** o el comprobante en el local seleccionado para reclamar tus productos:</p>
                <div class="codigo"><?php echo $codigo_final; ?></div>
                <p>Hemos enviado un comprobante virtual (simulado) con los detalles.</p>
                <a href="tienda.php">Hacer otra compra</a>
            </div>

        <?php else: 
            // --- VISTA NORMAL DE CHECKOUT ---
        ?>
            <h1>Finalizar Compra</h1>

            <h2>1. Resumen del Carrito</h2>
            <table>
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Precio Unitario</th>
                        <th>Cantidad</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($_SESSION['carrito'] as $id => $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['nombre']); ?></td>
                            <td>$<?php echo number_format($item['precio'], 2); ?></td>
                            <td><?php echo $item['cantidad']; ?></td>
                            <td>$<?php echo number_format($item['precio'] * $item['cantidad'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="total-fila">
                        <td colspan="3">Total Final:</td>
                        <td>$<?php echo number_format($total_carrito, 2); ?></td>
                    </tr>
                </tbody>
            </table>

            <form method="POST">
                <h2>2. Selecciona Local de Recolección</h2>
                <input type="hidden" name="accion" value="finalizar">

                <?php foreach ($locales as $local): ?>
                    <?php 
                        $clase = $local['disponible'] ? '' : 'no-disponible';
                        $estado = $local['disponible'] ? 'Disponible' : 'No disponible (Falta de Stock)';
                    ?>
                    <div class="local-opcion <?php echo $clase; ?>">
                        <input type="radio" 
                               id="local_<?php echo $local['id']; ?>" 
                               name="local_id" 
                               value="<?php echo $local['id']; ?>"
                               <?php echo $local['disponible'] ? 'required' : 'disabled'; ?>>
                        
                        <label for="local_<?php echo $local['id']; ?>">
                            <strong><?php echo htmlspecialchars($local['nombre']); ?></strong><br>
                            <?php echo htmlspecialchars($local['direccion']); ?><br>
                            <span class="estado"><?php echo $estado; ?></span>
                        </label>
                    </div>
                <?php endforeach; ?>

                <button type="submit" class="btn-finalizar" <?php echo empty($_SESSION['carrito']) ? 'disabled' : ''; ?>>
                    Confirmar Pedido y Generar Recibo
                </button>
            </form>
        <?php endif; ?>
    </div>

</body>
</html> 