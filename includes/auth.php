<?php
session_start();

function isAuthenticated() {
    return isset($_SESSION['user']) && !empty($_SESSION['user']);
}

function hasRole($role) {
    return isAuthenticated() && $_SESSION['user']['role'] === $role;
}

function checkLogin() {
    if (!isAuthenticated()) {
        header('Location: /EventManager/views/auth/login.php');
        exit;
    }
}

function checkRole($required_role) {
    checkLogin();
    if (!hasRole($required_role)) {
        header('Location: /EventManager/views/errors/403.php');
        exit;
    }
}

class Auth {
    private $conn;
    private $table_name = "users";

    public function __construct($db) {
        $this->conn = $db;
    }

    // Verificar si el usuario está autenticado
    public static function checkLogin() {
        if (!isset($_SESSION['user_id'])) {
            header("Location: " . BASE_URL . "login");
            exit();
        }
    }

    // Verificar permisos de rol
    public static function checkRole($required_role) {
        self::checkLogin();
        if ($_SESSION['user_role'] != $required_role && $_SESSION['user_role'] != ROLE_ADMIN) {
            header("HTTP/1.1 403 Forbidden");
            include_once "views/403.php";
            exit();
        }
    }

    // Login de usuario
    public function login($email, $password) {
        $query = "SELECT u.*, r.name as role_name 
                FROM " . $this->table_name . " u
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE u.email = ?";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (password_verify($password, $row['password'])) {
                if ($row['status'] != 'active') {
                    return ["error" => "Tu cuenta está " . $row['status']];
                }

                // Iniciar sesión
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['user_role'] = $row['role_id'];
                $_SESSION['role_name'] = $row['role_name'];

                return ["success" => true];
            }
        }

        return ["error" => "Email o contraseña incorrectos"];
    }

    // Registro de usuario
    public function register($username, $email, $password, $role_id = ROLE_ATTENDEE) {
        // Verificar si el email ya existe
        if ($this->emailExists($email)) {
            return ["error" => "El email ya está registrado"];
        }

        // Verificar si el username ya existe
        if ($this->usernameExists($username)) {
            return ["error" => "El nombre de usuario ya está en uso"];
        }

        $query = "INSERT INTO " . $this->table_name . "
                (username, email, password, role_id)
                VALUES
                (:username, :email, :password, :role_id)";

        $stmt = $this->conn->prepare($query);

        // Sanitizar y hashear
        $username = htmlspecialchars(strip_tags($username));
        $email = htmlspecialchars(strip_tags($email));
        $password = password_hash($password, PASSWORD_DEFAULT);
        $role_id = htmlspecialchars(strip_tags($role_id));

        // Vincular valores
        $stmt->bindParam(":username", $username);
        $stmt->bindParam(":email", $email);
        $stmt->bindParam(":password", $password);
        $stmt->bindParam(":role_id", $role_id);

        if ($stmt->execute()) {
            return ["success" => true];
        }

        return ["error" => "Error al registrar el usuario"];
    }

    // Verificar si existe el email
    private function emailExists($email) {
        $query = "SELECT id FROM " . $this->table_name . " WHERE email = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $email);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    // Verificar si existe el username
    private function usernameExists($username) {
        $query = "SELECT id FROM " . $this->table_name . " WHERE username = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $username);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    // Cerrar sesión
    public static function logout() {
        session_destroy();
        header("Location: " . BASE_URL . "login");
        exit();
    }

    // Cambiar contraseña
    public function changePassword($user_id, $current_password, $new_password) {
        // Verificar contraseña actual
        $query = "SELECT password FROM " . $this->table_name . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $user_id);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (password_verify($current_password, $row['password'])) {
                // Actualizar contraseña
                $query = "UPDATE " . $this->table_name . "
                        SET password = :password
                        WHERE id = :id";

                $stmt = $this->conn->prepare($query);
                $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                
                $stmt->bindParam(":password", $new_password_hash);
                $stmt->bindParam(":id", $user_id);

                if ($stmt->execute()) {
                    return ["success" => true];
                }
            }
        }

        return ["error" => "Error al cambiar la contraseña"];
    }
}
?> 