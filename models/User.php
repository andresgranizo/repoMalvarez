<?php
class User {
    private $conn;
    private $table_name = "users";

    // Propiedades del objeto
    public $id;
    public $role_id;
    public $email;
    public $password;
    public $first_name;
    public $last_name;
    public $phone;
    public $is_active;
    public $mfa_enabled;
    public $mfa_secret;
    public $created_at;
    public $updated_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Crear nuevo usuario
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                (role_id, email, password, first_name, last_name, phone)
                VALUES
                (:role_id, :email, :password, :first_name, :last_name, :phone)";

        $stmt = $this->conn->prepare($query);

        // Sanitizar
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->first_name = htmlspecialchars(strip_tags($this->first_name));
        $this->last_name = htmlspecialchars(strip_tags($this->last_name));
        $this->phone = htmlspecialchars(strip_tags($this->phone));

        // Hash de la contraseña
        $this->password = password_hash($this->password, PASSWORD_DEFAULT);

        // Vincular valores
        $stmt->bindParam(":role_id", $this->role_id);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":password", $this->password);
        $stmt->bindParam(":first_name", $this->first_name);
        $stmt->bindParam(":last_name", $this->last_name);
        $stmt->bindParam(":phone", $this->phone);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Verificar si existe el email
    public function emailExists() {
        $query = "SELECT id, role_id, password, first_name, last_name
                FROM " . $this->table_name . "
                WHERE email = ?
                LIMIT 0,1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->email);
        $stmt->execute();

        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->id = $row['id'];
            $this->role_id = $row['role_id'];
            $this->password = $row['password'];
            $this->first_name = $row['first_name'];
            $this->last_name = $row['last_name'];
            return true;
        }
        return false;
    }

    // Actualizar usuario
    public function update() {
        $query = "UPDATE " . $this->table_name . "
                SET
                    first_name = :first_name,
                    last_name = :last_name,
                    phone = :phone
                WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        // Sanitizar
        $this->first_name = htmlspecialchars(strip_tags($this->first_name));
        $this->last_name = htmlspecialchars(strip_tags($this->last_name));
        $this->phone = htmlspecialchars(strip_tags($this->phone));
        $this->id = htmlspecialchars(strip_tags($this->id));

        // Vincular valores
        $stmt->bindParam(":first_name", $this->first_name);
        $stmt->bindParam(":last_name", $this->last_name);
        $stmt->bindParam(":phone", $this->phone);
        $stmt->bindParam(":id", $this->id);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Obtener permisos del usuario
    public function getPermissions() {
        $query = "SELECT p.name
                FROM permissions p
                INNER JOIN roles_permissions rp ON p.id = rp.permission_id
                WHERE rp.role_id = ?";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->role_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
?> 