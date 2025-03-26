<?php
class Event {
    private $conn;
    private $table_name = "events";

    // Propiedades del objeto
    public $id;
    public $organizer_id;
    public $title;
    public $description;
    public $event_type;
    public $modality;
    public $start_date;
    public $end_date;
    public $location;
    public $virtual_link;
    public $max_capacity;
    public $branding_logo;
    public $branding_colors;
    public $status;
    public $created_at;
    public $updated_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Crear nuevo evento
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                (organizer_id, title, description, event_type, modality, 
                start_date, end_date, location, virtual_link, max_capacity,
                branding_logo, branding_colors, status)
                VALUES
                (:organizer_id, :title, :description, :event_type, :modality,
                :start_date, :end_date, :location, :virtual_link, :max_capacity,
                :branding_logo, :branding_colors, :status)";

        $stmt = $this->conn->prepare($query);

        // Sanitizar
        $this->title = htmlspecialchars(strip_tags($this->title));
        $this->description = htmlspecialchars(strip_tags($this->description));
        $this->location = htmlspecialchars(strip_tags($this->location));
        $this->virtual_link = htmlspecialchars(strip_tags($this->virtual_link));
        $this->branding_logo = htmlspecialchars(strip_tags($this->branding_logo));

        // Vincular valores
        $stmt->bindParam(":organizer_id", $this->organizer_id);
        $stmt->bindParam(":title", $this->title);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":event_type", $this->event_type);
        $stmt->bindParam(":modality", $this->modality);
        $stmt->bindParam(":start_date", $this->start_date);
        $stmt->bindParam(":end_date", $this->end_date);
        $stmt->bindParam(":location", $this->location);
        $stmt->bindParam(":virtual_link", $this->virtual_link);
        $stmt->bindParam(":max_capacity", $this->max_capacity);
        $stmt->bindParam(":branding_logo", $this->branding_logo);
        $stmt->bindParam(":branding_colors", $this->branding_colors);
        $stmt->bindParam(":status", $this->status);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Leer todos los eventos
    public function read($search = '', $filters = []) {
        $query = "SELECT e.*, u.name as organizer_name 
                FROM " . $this->table_name . " e
                LEFT JOIN users u ON e.organizer_id = u.id
                WHERE 1=1";

        // Aplicar filtros de búsqueda
        if(!empty($search)) {
            $query .= " AND (e.title LIKE :search OR e.description LIKE :search)";
        }

        // Aplicar filtros adicionales
        if(!empty($filters)) {
            if(isset($filters['event_type'])) {
                $query .= " AND e.event_type = :event_type";
            }
            if(isset($filters['modality'])) {
                $query .= " AND e.modality = :modality";
            }
            if(isset($filters['status'])) {
                $query .= " AND e.status = :status";
            }
            if(isset($filters['start_date'])) {
                $query .= " AND e.start_date >= :start_date";
            }
            if(isset($filters['end_date'])) {
                $query .= " AND e.end_date <= :end_date";
            }
        }

        $query .= " ORDER BY e.start_date ASC";

        $stmt = $this->conn->prepare($query);

        // Vincular parámetros de búsqueda
        if(!empty($search)) {
            $searchTerm = "%{$search}%";
            $stmt->bindParam(":search", $searchTerm);
        }

        // Vincular parámetros de filtros
        if(!empty($filters)) {
            foreach($filters as $key => $value) {
                $stmt->bindParam(":{$key}", $value);
            }
        }

        $stmt->execute();
        return $stmt;
    }

    // Leer un evento específico
    public function readOne() {
        $query = "SELECT e.*, u.name as organizer_name 
                FROM " . $this->table_name . " e
                LEFT JOIN users u ON e.organizer_id = u.id
                WHERE e.id = ?
                LIMIT 0,1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if($row) {
            $this->organizer_id = $row['organizer_id'];
            $this->title = $row['title'];
            $this->description = $row['description'];
            $this->event_type = $row['event_type'];
            $this->modality = $row['modality'];
            $this->start_date = $row['start_date'];
            $this->end_date = $row['end_date'];
            $this->location = $row['location'];
            $this->virtual_link = $row['virtual_link'];
            $this->max_capacity = $row['max_capacity'];
            $this->branding_logo = $row['branding_logo'];
            $this->branding_colors = $row['branding_colors'];
            $this->status = $row['status'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            return true;
        }
        return false;
    }

    // Actualizar evento
    public function update() {
        $query = "UPDATE " . $this->table_name . "
                SET
                    title = :title,
                    description = :description,
                    event_type = :event_type,
                    modality = :modality,
                    start_date = :start_date,
                    end_date = :end_date,
                    location = :location,
                    virtual_link = :virtual_link,
                    max_capacity = :max_capacity,
                    branding_logo = :branding_logo,
                    branding_colors = :branding_colors,
                    status = :status
                WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        // Sanitizar
        $this->title = htmlspecialchars(strip_tags($this->title));
        $this->description = htmlspecialchars(strip_tags($this->description));
        $this->location = htmlspecialchars(strip_tags($this->location));
        $this->virtual_link = htmlspecialchars(strip_tags($this->virtual_link));
        $this->branding_logo = htmlspecialchars(strip_tags($this->branding_logo));

        // Vincular valores
        $stmt->bindParam(":title", $this->title);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":event_type", $this->event_type);
        $stmt->bindParam(":modality", $this->modality);
        $stmt->bindParam(":start_date", $this->start_date);
        $stmt->bindParam(":end_date", $this->end_date);
        $stmt->bindParam(":location", $this->location);
        $stmt->bindParam(":virtual_link", $this->virtual_link);
        $stmt->bindParam(":max_capacity", $this->max_capacity);
        $stmt->bindParam(":branding_logo", $this->branding_logo);
        $stmt->bindParam(":branding_colors", $this->branding_colors);
        $stmt->bindParam(":status", $this->status);
        $stmt->bindParam(":id", $this->id);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Eliminar evento
    public function delete($id = null) {
        try {
            $this->id = $id ?? $this->id;
            
            // Primero verificar si hay registros relacionados
            $query = "SELECT COUNT(*) as count FROM registrations WHERE event_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(1, $this->id);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['count'] > 0) {
                throw new Exception("No se puede eliminar el evento porque tiene registros asociados");
            }

            // Iniciar transacción
            $this->conn->beginTransaction();

            // Eliminar registros relacionados en pricing_categories
            $query = "DELETE FROM pricing_categories WHERE event_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(1, $this->id);
            $stmt->execute();

            // Eliminar el evento
            $query = "DELETE FROM " . $this->table_name . " WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(1, $this->id);
            $stmt->execute();

            // Confirmar transacción
            $this->conn->commit();
            return true;

        } catch (Exception $e) {
            // Revertir cambios si hay error
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }
    }

    // Verificar disponibilidad
    public function checkAvailability() {
        $query = "SELECT COUNT(*) as total_registrations
                FROM registrations
                WHERE event_id = ? AND status = 'confirmed'";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_registrations = $row['total_registrations'];

        return $this->max_capacity > $total_registrations;
    }
}
?> 