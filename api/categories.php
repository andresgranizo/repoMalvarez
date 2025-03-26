<?php
session_start();
header('Content-Type: application/json');

// Verificar autenticación y rol de administrador
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once '../includes/Database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Obtener el método HTTP
    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            // Listar categorías
            if (isset($_GET['event_id'])) {
                // Obtener categorías para un evento específico
                $stmt = $conn->prepare("
                    SELECT c.* 
                    FROM categories c
                    INNER JOIN events e ON e.category_id = c.id
                    WHERE e.id = :event_id
                ");
                $stmt->execute(['event_id' => $_GET['event_id']]);
            } else {
                // Obtener todas las categorías con conteo de eventos
                $stmt = $conn->query("
                    SELECT c.*, COUNT(e.id) as total_events
                    FROM categories c
                    LEFT JOIN events e ON c.id = e.category_id
                    GROUP BY c.id
                    ORDER BY c.name
                ");
            }
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $categories]);
            break;

        case 'POST':
            // Crear nueva categoría
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['name']) || empty(trim($data['name']))) {
                throw new Exception('El nombre de la categoría es requerido');
            }

            // Verificar si ya existe una categoría con el mismo nombre
            $stmt = $conn->prepare("SELECT id FROM categories WHERE name = :name");
            $stmt->execute(['name' => trim($data['name'])]);
            if ($stmt->fetch()) {
                throw new Exception('Ya existe una categoría con este nombre');
            }

            $stmt = $conn->prepare("
                INSERT INTO categories (name, description, created_at) 
                VALUES (:name, :description, NOW())
            ");

            $stmt->execute([
                'name' => trim($data['name']),
                'description' => isset($data['description']) ? trim($data['description']) : null
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Categoría creada exitosamente',
                'id' => $conn->lastInsertId()
            ]);
            break;

        case 'PUT':
            // Actualizar categoría existente
            if (!isset($_GET['id'])) {
                throw new Exception('ID de categoría no proporcionado');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['name']) || empty(trim($data['name']))) {
                throw new Exception('El nombre de la categoría es requerido');
            }

            // Verificar si existe otra categoría con el mismo nombre
            $stmt = $conn->prepare("
                SELECT id 
                FROM categories 
                WHERE name = :name AND id != :id
            ");
            $stmt->execute([
                'name' => trim($data['name']),
                'id' => $_GET['id']
            ]);
            if ($stmt->fetch()) {
                throw new Exception('Ya existe otra categoría con este nombre');
            }

            $stmt = $conn->prepare("
                UPDATE categories 
                SET name = :name, 
                    description = :description,
                    updated_at = NOW()
                WHERE id = :id
            ");

            $stmt->execute([
                'id' => $_GET['id'],
                'name' => trim($data['name']),
                'description' => isset($data['description']) ? trim($data['description']) : null
            ]);

            if ($stmt->rowCount() === 0) {
                throw new Exception('Categoría no encontrada');
            }

            echo json_encode([
                'success' => true,
                'message' => 'Categoría actualizada exitosamente'
            ]);
            break;

        case 'DELETE':
            // Eliminar categoría
            if (!isset($_GET['id'])) {
                throw new Exception('ID de categoría no proporcionado');
            }

            // Verificar si la categoría existe
            $stmt = $conn->prepare("SELECT id FROM categories WHERE id = :id");
            $stmt->execute(['id' => $_GET['id']]);
            if (!$stmt->fetch()) {
                throw new Exception('Categoría no encontrada');
            }

            // Verificar si la categoría tiene eventos asociados
            $stmt = $conn->prepare("
                SELECT COUNT(*) as total 
                FROM events 
                WHERE category_id = :id
            ");
            $stmt->execute(['id' => $_GET['id']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result['total'] > 0) {
                throw new Exception('No se puede eliminar la categoría porque tiene eventos asociados');
            }

            $stmt = $conn->prepare("DELETE FROM categories WHERE id = :id");
            $stmt->execute(['id' => $_GET['id']]);

            echo json_encode([
                'success' => true,
                'message' => 'Categoría eliminada exitosamente'
            ]);
            break;

        default:
            throw new Exception('Método no permitido');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 