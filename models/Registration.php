<?php
class Registration {
    private $conn;
    private $table_name = "registrations";

    // Propiedades del objeto
    public $id;
    public $event_id;
    public $user_id;
    public $category_id;
    public $status;
    public $registration_data;
    public $qr_code;
    public $created_at;
    public $updated_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Crear nueva inscripción
    public function create() {
        // Verificar disponibilidad
        $query = "SELECT COUNT(*) as total_registrations, e.max_capacity
                FROM " . $this->table_name . " r
                INNER JOIN events e ON r.event_id = e.id
                WHERE r.event_id = ? AND r.status = 'confirmed'
                GROUP BY e.max_capacity";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->event_id);
        $stmt->execute();

        if($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if($row['total_registrations'] >= $row['max_capacity']) {
                // Establecer estado en lista de espera
                $this->status = 'waitlist';
            }
        }

        // Crear inscripción
        $query = "INSERT INTO " . $this->table_name . "
                (event_id, user_id, category_id, status, registration_data, qr_code)
                VALUES
                (:event_id, :user_id, :category_id, :status, :registration_data, :qr_code)";

        $stmt = $this->conn->prepare($query);

        // Generar código QR único
        $this->qr_code = uniqid('REG_', true);

        // Vincular valores
        $stmt->bindParam(":event_id", $this->event_id);
        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->bindParam(":category_id", $this->category_id);
        $stmt->bindParam(":status", $this->status);
        $stmt->bindParam(":registration_data", $this->registration_data);
        $stmt->bindParam(":qr_code", $this->qr_code);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Leer registros por evento
    public function readByEvent($event_id) {
        $query = "SELECT r.*, u.name as user_name, u.email, pc.name as category_name
                FROM " . $this->table_name . " r
                LEFT JOIN users u ON r.user_id = u.id
                LEFT JOIN pricing_categories pc ON r.pricing_category_id = pc.id
                WHERE r.event_id = ?
                ORDER BY r.created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $event_id);
        $stmt->execute();

        return $stmt;
    }

    // Leer registros por usuario
    public function readByUser($user_id) {
        $query = "SELECT r.*, e.title as event_title, u.name as user_name,
                        e.start_date, e.end_date, e.location, e.virtual_link,
                        pc.name as category_name, pc.price,
                        p.status as payment_status, p.payment_date
                FROM " . $this->table_name . " r
                LEFT JOIN events e ON r.event_id = e.id
                LEFT JOIN users u ON r.user_id = u.id
                LEFT JOIN pricing_categories pc ON r.pricing_category_id = pc.id
                LEFT JOIN payments p ON r.id = p.registration_id
                WHERE r.user_id = ?
                ORDER BY r.created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $user_id);
        $stmt->execute();

        return $stmt;
    }

    // Actualizar estado de inscripción
    public function updateStatus() {
        $query = "UPDATE " . $this->table_name . "
                SET status = :status
                WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":status", $this->status);
        $stmt->bindParam(":id", $this->id);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Verificar si el usuario ya está inscrito
    public function isUserRegistered() {
        $query = "SELECT id FROM " . $this->table_name . "
                WHERE event_id = ? AND user_id = ?
                LIMIT 0,1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->event_id);
        $stmt->bindParam(2, $this->user_id);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    // Cancelar inscripción
    public function cancel() {
        $query = "UPDATE " . $this->table_name . "
                SET status = 'cancelled'
                WHERE id = :id AND user_id = :user_id";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":user_id", $this->user_id);

        if($stmt->execute()) {
            // Si hay lista de espera, confirmar la siguiente inscripción
            $this->promoteFromWaitlist();
            return true;
        }
        return false;
    }

    // Promover siguiente inscripción de la lista de espera
    private function promoteFromWaitlist() {
        $query = "SELECT r.id
                FROM " . $this->table_name . " r
                WHERE r.event_id = ? AND r.status = 'waitlist'
                ORDER BY r.created_at ASC
                LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->event_id);
        $stmt->execute();

        if($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->id = $row['id'];
            $this->status = 'confirmed';
            $this->updateStatus();
        }
    }

    // Validar inscripción por código QR
    public function validateQRCode($qr_code) {
        $query = "SELECT r.*, e.title as event_title, u.first_name, u.last_name,
                pc.name as category_name
                FROM " . $this->table_name . " r
                INNER JOIN events e ON r.event_id = e.id
                INNER JOIN users u ON r.user_id = u.id
                INNER JOIN participant_categories pc ON r.category_id = pc.id
                WHERE r.qr_code = ? AND r.status = 'confirmed'
                LIMIT 0,1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $qr_code);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?> 