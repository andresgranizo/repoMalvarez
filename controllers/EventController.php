<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
require_once '../models/Event.php';
require_once '../models/Registration.php';
require_once '../includes/AuthMiddleware.php';

class EventController {
    private $db;
    private $event;
    private $registrationModel;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->event = new Event($this->db);
        $this->registrationModel = new Registration($this->db);
    }

    public function create($data) {
        // Verificar permisos
        AuthMiddleware::requirePermission('manage_events');

        // Validar datos requeridos
        if(
            empty($data['title']) ||
            empty($data['event_type']) ||
            empty($data['modality']) ||
            empty($data['start_date']) ||
            empty($data['end_date'])
        ) {
            return [
                'success' => false,
                'message' => 'Faltan campos requeridos'
            ];
        }

        // Asignar valores
        $this->event->organizer_id = $_SESSION['user_id'];
        $this->event->title = $data['title'];
        $this->event->description = $data['description'] ?? '';
        $this->event->event_type = $data['event_type'];
        $this->event->modality = $data['modality'];
        $this->event->start_date = $data['start_date'];
        $this->event->end_date = $data['end_date'];
        $this->event->location = $data['location'] ?? null;
        $this->event->virtual_link = $data['virtual_link'] ?? null;
        $this->event->max_capacity = $data['max_capacity'] ?? null;
        $this->event->branding_logo = $data['branding_logo'] ?? null;
        $this->event->branding_colors = json_encode($data['branding_colors'] ?? null);
        $this->event->status = $data['status'] ?? 'draft';

        // Crear evento
        if($this->event->create()) {
            return [
                'success' => true,
                'message' => 'Evento creado exitosamente'
            ];
        }

        return [
            'success' => false,
            'message' => 'No se pudo crear el evento'
        ];
    }

    public function getAll($search = '', $filters = []) {
        // No requiere autenticación para ver eventos públicos
        $stmt = $this->event->read($search, $filters);
        $events = [];

        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $event_item = [
                'id' => $row['id'],
                'title' => $row['title'],
                'description' => $row['description'],
                'event_type' => $row['event_type'],
                'modality' => $row['modality'],
                'start_date' => $row['start_date'],
                'end_date' => $row['end_date'],
                'location' => $row['location'],
                'virtual_link' => $row['virtual_link'],
                'max_capacity' => $row['max_capacity'],
                'branding_logo' => $row['branding_logo'],
                'branding_colors' => json_decode($row['branding_colors']),
                'status' => $row['status'],
                'organizer' => [
                    'id' => $row['organizer_id'],
                    'name' => $row['organizer_name']
                ]
            ];
            $events[] = $event_item;
        }

        return [
            'success' => true,
            'data' => $events
        ];
    }

    public function getOne($id) {
        $this->event->id = $id;
        
        if($this->event->readOne()) {
            $event_data = [
                'id' => $this->event->id,
                'title' => $this->event->title,
                'description' => $this->event->description,
                'event_type' => $this->event->event_type,
                'modality' => $this->event->modality,
                'start_date' => $this->event->start_date,
                'end_date' => $this->event->end_date,
                'location' => $this->event->location,
                'virtual_link' => $this->event->virtual_link,
                'max_capacity' => $this->event->max_capacity,
                'branding_logo' => $this->event->branding_logo,
                'branding_colors' => json_decode($this->event->branding_colors),
                'status' => $this->event->status
            ];

            return [
                'success' => true,
                'data' => $event_data
            ];
        }

        return [
            'success' => false,
            'message' => 'Evento no encontrado'
        ];
    }

    public function update($id, $data) {
        // Verificar permisos
        AuthMiddleware::requirePermission('manage_events');

        $this->event->id = $id;
        
        // Verificar si el evento existe
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
                'message' => 'No tiene permisos para editar este evento'
            ];
        }

        // Actualizar solo los campos proporcionados
        $this->event->title = $data['title'] ?? $this->event->title;
        $this->event->description = $data['description'] ?? $this->event->description;
        $this->event->event_type = $data['event_type'] ?? $this->event->event_type;
        $this->event->modality = $data['modality'] ?? $this->event->modality;
        $this->event->start_date = $data['start_date'] ?? $this->event->start_date;
        $this->event->end_date = $data['end_date'] ?? $this->event->end_date;
        $this->event->location = $data['location'] ?? $this->event->location;
        $this->event->virtual_link = $data['virtual_link'] ?? $this->event->virtual_link;
        $this->event->max_capacity = $data['max_capacity'] ?? $this->event->max_capacity;
        $this->event->branding_logo = $data['branding_logo'] ?? $this->event->branding_logo;
        $this->event->branding_colors = isset($data['branding_colors']) ? json_encode($data['branding_colors']) : $this->event->branding_colors;
        $this->event->status = $data['status'] ?? $this->event->status;

        if($this->event->update()) {
            return [
                'success' => true,
                'message' => 'Evento actualizado exitosamente'
            ];
        }

        return [
            'success' => false,
            'message' => 'No se pudo actualizar el evento'
        ];
    }

    public function delete($id) {
        try {
            // Debug de sesión
            error_log("Contenido de SESSION: " . print_r($_SESSION, true));

            // Verificar permisos
            if (!isset($_SESSION['user']) || !isset($_SESSION['user']['role'])) {
                error_log("Falta user o role en la sesión");
                throw new Exception("No autorizado - Sesión inválida");
            }

            error_log("Rol del usuario: " . $_SESSION['user']['role']);

            if ($_SESSION['user']['role'] !== 'admin' && $_SESSION['user']['role'] !== 'organizer') {
                error_log("Rol incorrecto. Se requiere rol 'admin' u 'organizer', se recibió: " . $_SESSION['user']['role']);
                throw new Exception("No tienes permisos para eliminar eventos");
            }

            // Si es organizador, verificar que sea el propietario del evento
            if ($_SESSION['user']['role'] === 'organizer') {
                $this->event->id = $id;
                if (!$this->event->readOne() || $this->event->organizer_id !== $_SESSION['user']['id']) {
                    throw new Exception("No tienes permisos para eliminar este evento");
                }
            }

            // Verificar si hay registros asociados
            $stmt = $this->registrationModel->readByEvent($id);
            if ($stmt->rowCount() > 0) {
                throw new Exception("No se puede eliminar el evento porque tiene registros asociados");
            }

            // Intentar eliminar el evento
            $result = $this->event->delete($id);
            
            if ($result) {
                return array(
                    "success" => true,
                    "message" => "Evento eliminado correctamente"
                );
            } else {
                throw new Exception("Error al eliminar el evento");
            }
        } catch (Exception $e) {
            error_log("Error en EventController->delete: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return array(
                "success" => false,
                "message" => $e->getMessage()
            );
        }
    }

    public function checkAvailability($id) {
        $this->event->id = $id;
        
        if(!$this->event->readOne()) {
            return [
                'success' => false,
                'message' => 'Evento no encontrado'
            ];
        }

        $available = $this->event->checkAvailability();
        return [
            'success' => true,
            'available' => $available,
            'message' => $available ? 'Hay cupos disponibles' : 'No hay cupos disponibles'
        ];
    }
}
?> 