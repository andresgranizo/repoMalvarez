<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST,GET,PUT,DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../controllers/RegistrationController.php';

$registrationController = new RegistrationController();

// Obtener datos de la petición
$data = json_decode(file_get_contents("php://input"), true);

// Determinar la acción basada en el método de la petición
$requestMethod = $_SERVER["REQUEST_METHOD"];
$action = isset($_GET['action']) ? $_GET['action'] : '';
$id = isset($_GET['id']) ? $_GET['id'] : null;
$event_id = isset($_GET['event_id']) ? $_GET['event_id'] : null;

switch($requestMethod) {
    case 'GET':
        if($event_id) {
            $response = $registrationController->getEventRegistrations($event_id);
        } else {
            $response = $registrationController->getUserRegistrations();
        }
        break;

    case 'POST':
        if($action === 'validate-qr' && isset($data['qr_code'])) {
            $response = $registrationController->validateQRCode($data['qr_code']);
        } else {
            $response = $registrationController->register($data);
        }
        break;

    case 'PUT':
        if($id && isset($data['status'])) {
            $response = $registrationController->updateStatus($id, $data['status']);
        } else {
            $response = array(
                "success" => false,
                "message" => "Datos incompletos para actualizar el estado"
            );
        }
        break;

    case 'DELETE':
        if($id) {
            $response = $registrationController->cancel($id);
        } else {
            $response = array(
                "success" => false,
                "message" => "ID de inscripción no proporcionado"
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

// Enviar respuesta
echo json_encode($response);
?> 