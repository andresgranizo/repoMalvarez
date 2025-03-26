<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST,GET,PUT,DELETE,OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Manejar preflight request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Verificar si hay errores de PHP antes de continuar
if (error_get_last()) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor'
    ]);
    exit();
}

require_once '../controllers/EventController.php';

try {
    // Log para depuración
    error_log("Método de solicitud original: " . $_SERVER['REQUEST_METHOD']);

    // Obtener datos de la petición
    $input = file_get_contents("php://input");
    error_log("Datos recibidos (raw): " . $input);
    $data = json_decode($input, true);
    error_log("Datos recibidos (parsed): " . print_r($data, true));

    $eventController = new EventController();

    // Determinar el método real de la petición
    $requestMethod = $_SERVER["REQUEST_METHOD"];
    if ($requestMethod === 'POST' && isset($data['_method'])) {
        $requestMethod = strtoupper($data['_method']);
        error_log("Método modificado a: " . $requestMethod);
    }

    // Obtener el ID del evento
    $id = isset($_GET['id']) ? $_GET['id'] : (isset($data['id']) ? $data['id'] : null);
    error_log("ID del evento: " . ($id ?? 'no proporcionado'));

    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $filters = isset($_GET['filters']) ? json_decode($_GET['filters'], true) : [];

    switch($requestMethod) {
        case 'GET':
            if($id) {
                $response = $eventController->getOne($id);
            } else {
                $response = $eventController->getAll($search, $filters);
            }
            break;

        case 'POST':
            if(isset($_GET['action']) && $_GET['action'] === 'check-availability' && $id) {
                $response = $eventController->checkAvailability($id);
            } else {
                $response = $eventController->create($data);
            }
            break;

        case 'PUT':
            if($id) {
                $response = $eventController->update($id, $data);
            } else {
                $response = array(
                    "success" => false,
                    "message" => "ID del evento no proporcionado"
                );
            }
            break;

        case 'DELETE':
            error_log("Procesando solicitud DELETE para el evento ID: " . $id);
            if($id) {
                $response = $eventController->delete($id);
                error_log("Respuesta del controlador: " . print_r($response, true));
            } else {
                $response = array(
                    "success" => false,
                    "message" => "ID del evento no proporcionado"
                );
            }
            break;

        default:
            $response = array(
                "success" => false,
                "message" => "Método no permitido"
            );
            break;
    }

} catch (Exception $e) {
    error_log("Error en api/events.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    $response = array(
        "success" => false,
        "message" => $e->getMessage()
    );
}

// Limpiar cualquier salida anterior
if (ob_get_length()) ob_clean();

// Enviar respuesta
http_response_code($response['success'] ? 200 : 400);
error_log("Enviando respuesta: " . print_r($response, true));
echo json_encode($response);
exit();
?> 