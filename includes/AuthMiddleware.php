<?php
class AuthMiddleware {
    public static function isAuthenticated() {
        if(!isset($_SESSION['user'])) {
            return false;
        }
        return true;
    }

    public static function hasPermission($permission) {
        if(!self::isAuthenticated()) {
            return false;
        }

        // Para este sistema, los roles admin y organizer tienen permiso para gestionar eventos
        if($permission === 'manage_events') {
            return in_array($_SESSION['user']['role'], ['admin', 'organizer']);
        }

        return false;
    }

    public static function requireAuth() {
        if(!self::isAuthenticated()) {
            header('HTTP/1.0 401 Unauthorized');
            echo json_encode([
                'success' => false,
                'message' => 'No autorizado'
            ]);
            exit;
        }
    }

    public static function requirePermission($permission) {
        self::requireAuth();
        
        if(!self::hasPermission($permission)) {
            header('HTTP/1.0 403 Forbidden');
            echo json_encode([
                'success' => false,
                'message' => 'No tiene permisos para realizar esta acción'
            ]);
            exit;
        }
    }

    public static function getUserRole() {
        if(!self::isAuthenticated()) {
            return null;
        }
        return $_SESSION['user']['role'];
    }

    public static function isAdmin() {
        return self::getUserRole() === 'admin';
    }
}
?> 