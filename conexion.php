<?php
// Configuración de la conexión a PostgreSQL
$host = 'localhost';
$port = '5432'; // Puerto estándar de PostgreSQL
$dbname = 'ferreteria_db'; // El nombre de tu base de datos
$user = 'postgres'; // El usuario por defecto
$password = '10kenneth10'; // Reemplaza con tu contraseña de PostgreSQL

$dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
$pdo = null;

try {
    // Crear la conexión PDO
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC 
    ]);
    
} catch (PDOException $e) {
    die("Error de conexión a la base de datos: " . $e->getMessage());
}

/**
 * Registra una acción administrativa en la tabla de auditoría.
 */
function log_auditoria($pdo, $usuario_id, $accion, $tabla_afectada = null, $registro_id = null) {
    try {
        $stmt = $pdo->prepare("INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id, fecha) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$usuario_id, $accion, $tabla_afectada, $registro_id]);
        return true;
    } catch (PDOException $e) {
        error_log("Error en auditoría: " . $e->getMessage());
        return false;
    }
}
?>