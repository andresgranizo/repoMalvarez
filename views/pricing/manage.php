<?php
require_once '../../includes/auth.php';
require_once '../../includes/config.php';
require_once '../../includes/Database.php';

// Verificar si el usuario está autenticado y es organizador
if (!isAuthenticated() || !hasRole('organizer')) {
    header('Location: ../auth/login.php');
    exit;
}

$db = new Database();
$conn = $db->getConnection();
$user_id = $_SESSION['user']['id'];
$success_message = '';
$error_message = '';

// Obtener el ID del evento si se proporciona
$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;

// Verificar si el evento pertenece al organizador
if ($event_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM events WHERE id = :event_id AND organizer_id = :user_id");
    $stmt->execute([
        ':event_id' => $event_id,
        ':user_id' => $user_id
    ]);
    
    if ($stmt->rowCount() === 0) {
        header('Location: ../events/manage.php');
        exit;
    }
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Procesar el formulario de nueva categoría
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'create' || $_POST['action'] === 'update') {
            $name = trim($_POST['name']);
            $price = floatval($_POST['price']);
            $description = trim($_POST['description']);
            $capacity = !empty($_POST['capacity']) ? intval($_POST['capacity']) : null;
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if ($_POST['action'] === 'create') {
                $stmt = $conn->prepare("INSERT INTO pricing_categories (event_id, name, price, description, capacity, is_active) VALUES (:event_id, :name, :price, :description, :capacity, :is_active)");
                $params = [
                    ':event_id' => $event_id,
                    ':name' => $name,
                    ':price' => $price,
                    ':description' => $description,
                    ':capacity' => $capacity,
                    ':is_active' => $is_active
                ];
            } else {
                $category_id = intval($_POST['category_id']);
                $stmt = $conn->prepare("UPDATE pricing_categories SET name = :name, price = :price, description = :description, capacity = :capacity, is_active = :is_active WHERE id = :category_id AND event_id = :event_id");
                $params = [
                    ':name' => $name,
                    ':price' => $price,
                    ':description' => $description,
                    ':capacity' => $capacity,
                    ':is_active' => $is_active,
                    ':category_id' => $category_id,
                    ':event_id' => $event_id
                ];
            }
            
            if ($stmt->execute($params)) {
                $success_message = "Categoría " . ($_POST['action'] === 'create' ? "creada" : "actualizada") . " exitosamente.";
            } else {
                $error_message = "Error al " . ($_POST['action'] === 'create' ? "crear" : "actualizar") . " la categoría.";
            }
        } elseif ($_POST['action'] === 'delete') {
            $category_id = intval($_POST['category_id']);
            $stmt = $conn->prepare("DELETE FROM pricing_categories WHERE id = :category_id AND event_id = :event_id");
            
            if ($stmt->execute([':category_id' => $category_id, ':event_id' => $event_id])) {
                $success_message = "Categoría eliminada exitosamente.";
            } else {
                $error_message = "Error al eliminar la categoría.";
            }
        }
    }
}

// Obtener las categorías de precio del evento
$categories = [];
if ($event_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM pricing_categories WHERE event_id = :event_id ORDER BY price ASC");
    $stmt->execute([':event_id' => $event_id]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Categorías de Precio - EventManager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/navbar.php'; ?>

    <div class="container mt-4">
        <?php if ($event_id > 0): ?>
            <h2>Categorías de Precio - <?php echo isset($event['name']) ? htmlspecialchars($event['name']) : ''; ?></h2>
            
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <div class="mb-4">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#categoryModal">
                    <i class="fas fa-plus"></i> Nueva Categoría
                </button>
            </div>

            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Precio</th>
                            <th>Descripción</th>
                            <th>Capacidad</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $category): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($category['name']); ?></td>
                                <td>$<?php echo number_format($category['price'], 2); ?></td>
                                <td><?php echo htmlspecialchars($category['description']); ?></td>
                                <td><?php echo $category['capacity'] ? $category['capacity'] : 'Sin límite'; ?></td>
                                <td>
                                    <span class="badge <?php echo $category['is_active'] ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo $category['is_active'] ? 'Activa' : 'Inactiva'; ?>
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-primary edit-category" 
                                            data-category='<?php echo json_encode($category); ?>'
                                            data-bs-toggle="modal" data-bs-target="#categoryModal">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger delete-category"
                                            data-category-id="<?php echo $category['id']; ?>"
                                            data-category-name="<?php echo htmlspecialchars($category['name']); ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($categories)): ?>
                            <tr>
                                <td colspan="6" class="text-center">No hay categorías de precio definidas.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Modal para crear/editar categoría -->
            <div class="modal fade" id="categoryModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Categoría de Precio</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form id="categoryForm" method="POST">
                            <div class="modal-body">
                                <input type="hidden" name="action" value="create">
                                <input type="hidden" name="category_id" value="">
                                
                                <div class="mb-3">
                                    <label for="name" class="form-label">Nombre</label>
                                    <input type="text" class="form-control" id="name" name="name" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="price" class="form-label">Precio</label>
                                    <input type="number" class="form-control" id="price" name="price" step="0.01" min="0" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">Descripción</label>
                                    <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="capacity" class="form-label">Capacidad (opcional)</label>
                                    <input type="number" class="form-control" id="capacity" name="capacity" min="1">
                                </div>
                                
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="is_active" name="is_active" checked>
                                    <label class="form-check-label" for="is_active">Activa</label>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-primary">Guardar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Formulario oculto para eliminar categoría -->
            <form id="deleteForm" method="POST" style="display: none;">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="category_id" value="">
            </form>
        <?php else: ?>
            <div class="alert alert-warning">
                Por favor, seleccione un evento para gestionar sus categorías de precio.
                <a href="../events/manage.php" class="btn btn-primary ms-3">Ver Eventos</a>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Manejar edición de categoría
            document.querySelectorAll('.edit-category').forEach(button => {
                button.addEventListener('click', function() {
                    const category = JSON.parse(this.dataset.category);
                    const form = document.getElementById('categoryForm');
                    form.elements['action'].value = 'update';
                    form.elements['category_id'].value = category.id;
                    form.elements['name'].value = category.name;
                    form.elements['price'].value = category.price;
                    form.elements['description'].value = category.description;
                    form.elements['capacity'].value = category.capacity || '';
                    form.elements['is_active'].checked = category.is_active == 1;
                });
            });

            // Manejar eliminación de categoría
            document.querySelectorAll('.delete-category').forEach(button => {
                button.addEventListener('click', function() {
                    const categoryId = this.dataset.categoryId;
                    const categoryName = this.dataset.categoryName;
                    if (confirm(`¿Está seguro que desea eliminar la categoría "${categoryName}"?`)) {
                        const form = document.getElementById('deleteForm');
                        form.elements['category_id'].value = categoryId;
                        form.submit();
                    }
                });
            });

            // Limpiar modal al abrirlo para crear nueva categoría
            document.querySelector('[data-bs-target="#categoryModal"]').addEventListener('click', function() {
                const form = document.getElementById('categoryForm');
                form.reset();
                form.elements['action'].value = 'create';
                form.elements['category_id'].value = '';
            });
        });
    </script>
</body>
</html> 