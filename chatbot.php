<?php
session_start();
require_once 'conexion.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$mensaje_usuario = trim($input['mensaje'] ?? '');

if (empty($mensaje_usuario)) {
    echo json_encode(['error' => 'Mensaje vacío']);
    exit;
}

try {
    // Obtener información de productos para el contexto del chatbot
    $stmt = $pdo->query("
        SELECT p.nombre, p.descripcion, p.precio, p.stock, c.nombre as categoria 
        FROM productos p 
        LEFT JOIN categorias c ON p.categoria_id = c.id 
        ORDER BY p.nombre
    ");
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Preparar contexto con información de productos
    $contexto_productos = "Información de productos disponibles:\n";
    foreach ($productos as $producto) {
        $disponibilidad = $producto['stock'] > 0 ? 
            "EN STOCK ({$producto['stock']} unidades)" : 
            "SIN STOCK";
        $contexto_productos .= "- {$producto['nombre']}: {$producto['descripcion']}. Precio: \${$producto['precio']}. {$disponibilidad}. Categoría: {$producto['categoria']}\n";
    }

    // Sistema de prompts para el chatbot
    $sistema_prompt = "Eres un asistente virtual de una ferretería online. 
    Tu función es ayudar a los clientes a encontrar productos, verificar disponibilidad y asistir en sus compras.
    
    INFORMACIÓN ACTUAL DE PRODUCTOS:
    {$contexto_productos}
    
    INSTRUCCIONES:
    1. Responde de manera amable y útil
    2. Si preguntan por un producto específico, verifica si está en stock
    3. Si un producto no está disponible, sugiere alternativas similares
    4. Ayuda a los usuarios a encontrar productos por categoría
    5. Proporciona precios cuando sean solicitados
    6. Si no sabes algo, sé honesto
    7. Mantén las respuestas breves y directas
    8. Usa emojis apropiados ocasionalmente para hacer la conversación más amigable
    
    CATEGORÍAS DISPONIBLES:
    - Herramientas Manuales: martillos, destornilladores, alicates, etc.
    - Electricidad: cables, enchufes, interruptores, herramientas eléctricas
    - Fontanería: tuberías, llaves, conectores, herramientas de plomería
    
    El usuario dice: \"{$mensaje_usuario}\"";

    // Usar OpenAI API (necesitarás una API key)
    $respuesta = usarOpenAI($sistema_prompt, $mensaje_usuario);
    
    // Si no hay API key, usar sistema de respuestas predefinidas
    if (!$respuesta) {
        $respuesta = generarRespuestaManual($mensaje_usuario, $productos);
    }

    echo json_encode(['respuesta' => $respuesta]);

} catch (Exception $e) {
    echo json_encode(['error' => 'Error en el servidor: ' . $e->getMessage()]);
}

function usarOpenAI($sistema_prompt, $mensaje_usuario) {
    $api_key = 'TU_API_KEY_DE_OPENAI'; // Reemplaza con tu API key
    
    if (empty($api_key) || $api_key === 'TU_API_KEY_DE_OPENAI') {
        return false; // Usar respuestas manuales si no hay API key
    }
    
    $data = [
        'model' => 'gpt-3.5-turbo',
        'messages' => [
            ['role' => 'system', 'content' => $sistema_prompt],
            ['role' => 'user', 'content' => $mensaje_usuario]
        ],
        'max_tokens' => 500,
        'temperature' => 0.7
    ];
    
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $resultado = json_decode($response, true);
    
    if (isset($resultado['choices'][0]['message']['content'])) {
        return trim($resultado['choices'][0]['message']['content']);
    }
    
    return false;
}

function generarRespuestaManual($mensaje, $productos) {
    $mensaje = strtolower($mensaje);
    
    // Buscar productos por nombre
    foreach ($productos as $producto) {
        if (stripos($mensaje, strtolower($producto['nombre'])) !== false) {
            if ($producto['stock'] > 0) {
                return "✅ **{$producto['nombre']}** está disponible!\n\n📝 *{$producto['descripcion']}*\n💰 Precio: $" . number_format($producto['precio'], 2) . "\n📦 Stock: {$producto['stock']} unidades\n🏷️ Categoría: {$producto['categoria']}\n\n¿Te interesa este producto? Puedes agregarlo al carrito desde la tienda.";
            } else {
                // Buscar productos similares en la misma categoría
                $alternativas = array_filter($productos, function($p) use ($producto) {
                    return $p['categoria'] === $producto['categoria'] && $p['stock'] > 0 && $p['id'] !== $producto['id'];
                });
                
                $respuesta = "❌ **{$producto['nombre']}** no está disponible en este momento.\n\n";
                
                if (!empty($alternativas)) {
                    $respuesta .= "📋 Te sugiero estas alternativas disponibles:\n";
                    foreach (array_slice($alternativas, 0, 3) as $alt) {
                        $respuesta .= "• {$alt['nombre']} - $" . number_format($alt['precio'], 2) . " (Stock: {$alt['stock']})\n";
                    }
                }
                
                return $respuesta;
            }
        }
    }
    
    // Respuestas predefinidas para preguntas comunes
    $respuestas = [
        'hola|buenos días|buenas tardes|buenas noches' => 
            '¡Hola! 👋 Soy tu asistente de ferretería. ¿En qué puedo ayudarte hoy? Puedo informarte sobre productos, verificar stock y ayudarte a encontrar lo que necesitas.',
        
        'qué tienen|qué venden|productos|catálogo' =>
            'Tenemos una amplia variedad de productos de ferretería:\n\n🔨 **Herramientas Manuales**: martillos, destornilladores, alicates, etc.\n⚡ **Electricidad**: cables, enchufes, interruptores, herramientas eléctricas\n🚰 **Fontanería**: tuberías, llaves, conectores, herramientas de plomería\n\n¿Te interesa alguna categoría en particular?',
        
        'precio|cuánto cuesta|valor' =>
            'Puedo consultar precios específicos. ¿De qué producto quieres saber el precio?',
        
        'stock|disponible|hay' =>
            'Puedo verificar la disponibilidad de productos. ¿Qué producto te interesa?',
        
        'herramientas|manuales' =>
            '🔨 **Herramientas Manuales disponibles**:\nPregunta por martillos, destornilladores, alicates, llaves inglesas, etc. ¿Qué herramienta necesitas?',
        
        'electricidad|eléctrico' =>
            '⚡ **Productos de Electricidad**:\nTenemos cables, enchufes, interruptores, herramientas eléctricas y más. ¿Qué estás buscando?',
        
        'fontanería|plomería|tuberías' =>
            '🚰 **Productos de Fontanería**:\nDisponemos de tuberías, llaves de paso, conectores, herramientas de plomería. ¿En qué puedo ayudarte?',
        
        'gracias|thanks' =>
            '¡De nada! 😊 ¿Hay algo más en lo que pueda ayudarte?',
        
        'adiós|chao|hasta luego' =>
            '¡Hasta luego! 👋 Recuerda que estoy aquí para ayudarte cuando lo necesites.'
    ];
    
    foreach ($respuestas as $patron => $respuesta) {
        if (preg_match("/$patron/", $mensaje)) {
            return $respuesta;
        }
    }
    
    return "🤔 No estoy seguro de entender tu pregunta. Puedo ayudarte con:\n• Información de productos\n• Verificación de stock\n• Precios\n• Categorías disponibles\n\n¿Podrías reformular tu pregunta?";
}
?>