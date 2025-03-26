<?php
require_once '../config/database.php';
require_once '../models/Registration.php';
require_once '../models/Event.php';
require_once '../includes/AuthMiddleware.php';

class RegistrationController {
    private $db;
    private $registration;
    private $event;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->registration = new Registration($this->db);
        $this->event = new Event($this->db);
    }

    public function register($data) {
        // Verificar autenticación
        AuthMiddleware::requireAuth();

        // Validar datos requeridos
        if(
            empty($data['event_id']) ||
            empty($data['category_id'])
        ) {
            return [
                'success' => false,
                'message' => 'Faltan campos requeridos'
            ];
        }

        // Verificar si el evento existe y está disponible
        $this->event->id = $data['event_id'];
        if(!$this->event->readOne()) {
            return [
                'success' => false,
                'message' => 'Evento no encontrado'
            ];
        }

        if($this->event->status !== 'published') {
            return [
                'success' => false,
                'message' => 'El evento no está disponible para inscripciones'
            ];
        }

        // Verificar si el usuario ya está inscrito
        $this->registration->event_id = $data['event_id'];
        $this->registration->user_id = $_SESSION['user_id'];
        if($this->registration->isUserRegistered()) {
            return [
                'success' => false,
                'message' => 'Ya estás inscrito en este evento'
            ];
        }

        // Asignar valores
        $this->registration->category_id = $data['category_id'];
        $this->registration->status = 'pending';
        $this->registration->registration_data = json_encode($data['registration_data'] ?? null);

        // Crear inscripción
        if($this->registration->create()) {
            return [
                'success' => true,
                'message' => 'Inscripción realizada exitosamente',
                'data' => [
                    'status' => $this->registration->status,
                    'qr_code' => $this->registration->qr_code
                ]
            ];
        }

        return [
            'success' => false,
            'message' => 'No se pudo realizar la inscripción'
        ];
    }

    public function getEventRegistrations($event_id) {
        // Verificar permisos
        AuthMiddleware::requirePermission('manage_events');

        // Verificar si el evento existe
        $this->event->id = $event_id;
        if(!$this->event->readOne()) {
            return [
                'success' => false,
                'message' => 'Evento no encontrado'
            ];
        }

        // Verificar si el usuario es el organizador o admin
        if(!AuthMiddleware::isAdmin() && $this->event->organizer_id != $_SESSION['user_id']) {
            return [
                'success' => false,
                'message' => 'No tiene permisos para ver las inscripciones de este evento'
            ];
        }

        $stmt = $this->registration->readByEvent($event_id);
        $registrations = [];

        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $registration_item = [
                'id' => $row['id'],
                'event_id' => $row['event_id'],
                'user' => [
                    'id' => $row['user_id'],
                    'name' => $row['user_name'],
                    'email' => $row['email']
                ],
                'status' => $row['status'],
                'created_at' => $row['created_at']
            ];
            $registrations[] = $registration_item;
        }

        return [
            'success' => true,
            'data' => $registrations
        ];
    }

    public function getUserRegistrations() {
        // Verificar autenticación
        AuthMiddleware::requireAuth();

        $stmt = $this->registration->readByUser($_SESSION['user_id']);
        $registrations = [];

        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $registration_item = [
                'id' => $row['id'],
                'event' => [
                    'title' => $row['event_title'],
                    'start_date' => $row['start_date'],
                    'end_date' => $row['end_date']
                ],
                'category' => $row['category_name'],
                'status' => $row['status'],
                'registration_data' => json_decode($row['registration_data']),
                'qr_code' => $row['qr_code'],
                'created_at' => $row['created_at']
            ];
            $registrations[] = $registration_item;
        }

        return [
            'success' => true,
            'data' => $registrations
        ];
    }

    public function updateStatus($id, $status) {
        // Verificar permisos
        AuthMiddleware::requirePermission('manage_events');

        $this->registration->id = $id;
        $this->registration->status = $status;

        if($this->registration->updateStatus()) {
            return [
                'success' => true,
                'message' => 'Estado de inscripción actualizado exitosamente'
            ];
        }

        return [
            'success' => false,
            'message' => 'No se pudo actualizar el estado de la inscripción'
        ];
    }

    public function cancel($id) {
        // Verificar autenticación
        AuthMiddleware::requireAuth();

        $this->registration->id = $id;
        $this->registration->user_id = $_SESSION['user_id'];

        if($this->registration->cancel()) {
            return [
                'success' => true,
                'message' => 'Inscripción cancelada exitosamente'
            ];
        }

        return [
            'success' => false,
            'message' => 'No se pudo cancelar la inscripción'
        ];
    }

    public function validateQRCode($qr_code) {
        // Verificar permisos
        AuthMiddleware::requirePermission('manage_events');

        $registration_data = $this->registration->validateQRCode($qr_code);

        if($registration_data) {
            return [
                'success' => true,
                'data' => $registration_data
            ];
        }

        return [
            'success' => false,
            'message' => 'Código QR inválido o inscripción no confirmada'
        ];
    }
}
?> 