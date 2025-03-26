<?php
require_once '../config/database.php';
require_once '../models/User.php';

class AuthController {
    private $db;
    private $user;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->user = new User($this->db);
    }

    public function register($data) {
        // Validar datos requeridos
        if(
            empty($data['email']) ||
            empty($data['password']) ||
            empty($data['first_name']) ||
            empty($data['last_name'])
        ) {
            return [
                'success' => false,
                'message' => 'Todos los campos son requeridos'
            ];
        }

        // Verificar si el email ya existe
        $this->user->email = $data['email'];
        if($this->user->emailExists()) {
            return [
                'success' => false,
                'message' => 'El email ya está registrado'
            ];
        }

        // Asignar valores
        $this->user->role_id = $data['role_id'] ?? 3; // Por defecto rol de asistente
        $this->user->email = $data['email'];
        $this->user->password = $data['password'];
        $this->user->first_name = $data['first_name'];
        $this->user->last_name = $data['last_name'];
        $this->user->phone = $data['phone'] ?? null;

        // Crear usuario
        if($this->user->create()) {
            return [
                'success' => true,
                'message' => 'Usuario registrado exitosamente'
            ];
        }

        return [
            'success' => false,
            'message' => 'No se pudo registrar el usuario'
        ];
    }

    public function login($email, $password) {
        // Validar datos requeridos
        if(empty($email) || empty($password)) {
            return [
                'success' => false,
                'message' => 'Email y contraseña son requeridos'
            ];
        }

        // Verificar si existe el usuario
        $this->user->email = $email;
        if($this->user->emailExists()) {
            // Verificar contraseña
            if(password_verify($password, $this->user->password)) {
                // Iniciar sesión
                session_start();
                $_SESSION['user_id'] = $this->user->id;
                $_SESSION['role_id'] = $this->user->role_id;
                $_SESSION['first_name'] = $this->user->first_name;
                $_SESSION['last_name'] = $this->user->last_name;

                // Obtener permisos
                $_SESSION['permissions'] = $this->user->getPermissions();

                return [
                    'success' => true,
                    'message' => 'Login exitoso',
                    'user' => [
                        'id' => $this->user->id,
                        'email' => $this->user->email,
                        'first_name' => $this->user->first_name,
                        'last_name' => $this->user->last_name,
                        'role_id' => $this->user->role_id
                    ]
                ];
            }
        }

        return [
            'success' => false,
            'message' => 'Email o contraseña incorrectos'
        ];
    }

    public function logout() {
        session_start();
        session_destroy();
        return [
            'success' => true,
            'message' => 'Sesión cerrada exitosamente'
        ];
    }

    public function getCurrentUser() {
        session_start();
        if(isset($_SESSION['user_id'])) {
            return [
                'success' => true,
                'user' => [
                    'id' => $_SESSION['user_id'],
                    'first_name' => $_SESSION['first_name'],
                    'last_name' => $_SESSION['last_name'],
                    'role_id' => $_SESSION['role_id'],
                    'permissions' => $_SESSION['permissions']
                ]
            ];
        }
        return [
            'success' => false,
            'message' => 'No hay sesión activa'
        ];
    }
}
?> 