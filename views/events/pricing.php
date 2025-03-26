<?php
session_start();

// Verificar si el usuario está autenticado y es organizador
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'organizer') {
    header('Location: ../auth/login.php');
    exit;
}

require_once '../../includes/Database.php';
require_once '../../includes/utilities.php';

$error = null;
$success = null;
$event = null;
$pricing_categories = [];
$payment_methods = [];

// Verificar que se proporcionó un ID de evento
if (!isset($_GET['event_id'])) {
    header('Location: manage.php');
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    $user_id = $_SESSION['user']['id'];
    $event_id = $_GET['event_id'];

    // Verificar que el evento pertenece al organizador
    $stmt = $conn->prepare("
        SELECT * FROM events 
        WHERE id = ? AND organizer_id = ?
    ");
    $stmt->execute([$event_id, $user_id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        header('Location: manage.php');
        exit;
    }

    // Obtener métodos de pago activos
    $stmt = $conn->query("SELECT * FROM payment_methods WHERE is_active = 1");
    $payment_methods = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Procesar formulario
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'create' || $action === 'update') {
            $name = $_POST['name'];
            $description = $_POST['description'];
            $price = $_POST['price'];
            $discount_percentage = $_POST['discount_percentage'] ?? 0;
            $ieee_member_discount = $_POST['ieee_member_discount'] ?? 0;
            $ieee_region = $_POST['ieee_region'] ?? null;
            $max_capacity = $_POST['max_capacity'] ?? null;
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            if ($action === 'create') {
                $stmt = $conn->prepare("
                    INSERT INTO pricing_categories (
                        event_id, name, description, price, discount_percentage,
                        ieee_member_discount, ieee_region, max_capacity, is_active
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $event_id, $name, $description, $price, $discount_percentage,
                    $ieee_member_discount, $ieee_region, $max_capacity, $is_active
                ]);
                $success = "Categoría de precio creada exitosamente";
            } else {
                $id = $_POST['id'];
                $stmt = $conn->prepare("
                    UPDATE pricing_categories 
                    SET name = ?, description = ?, price = ?, discount_percentage = ?,
                        ieee_member_discount = ?, ieee_region = ?, max_capacity = ?, is_active = ?
                    WHERE id = ? AND event_id = ?
                ");
                $stmt->execute([
                    $name, $description, $price, $discount_percentage,
                    $ieee_member_discount, $ieee_region, $max_capacity, $is_active,
                    $id, $event_id
                ]);
                $success = "Categoría de precio actualizada exitosamente";
            }
        } elseif ($action === 'delete') {
            $id = $_POST['id'];
            $stmt = $conn->prepare("DELETE FROM pricing_categories WHERE id = ? AND event_id = ?");
            $stmt->execute([$id, $event_id]);
            $success = "Categoría de precio eliminada exitosamente";
        } elseif ($action === 'update_payment_methods') {
            $selected_methods = $_POST['payment_methods'] ?? [];
            $payment_config = [
                'enabled_methods' => $selected_methods
            ];
            
            $stmt = $conn->prepare("UPDATE events SET payment_config = ? WHERE id = ?");
            $stmt->execute([json_encode($payment_config), $event_id]);
            $success = "Métodos de pago actualizados exitosamente";
            
            // Recargar evento para obtener la configuración actualizada
            $stmt = $conn->prepare("SELECT * FROM events WHERE id = ?");
            $stmt->execute([$event_id]);
            $event = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }

    // Obtener categorías de precio
    $stmt = $conn->prepare("
        SELECT * FROM pricing_categories 
        WHERE event_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$event_id]);
    $pricing_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
}

// Obtener métodos de pago habilitados
$enabled_methods = [];
if ($event && isset($event['payment_config'])) {
    $payment_config = json_decode($event['payment_config'], true);
    $enabled_methods = $payment_config['enabled_methods'] ?? [];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración de Precios - <?php echo htmlspecialchars($event['title']); ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="../../index.php">
                <i class="fas fa-calendar-alt"></i> EventManager
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../dashboard/organizer.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="manage.php">
                            <i class="fas fa-calendar"></i> Mis Eventos
                        </a>
                    </li>
                </ul>
                <div class="navbar-nav">
                    <span class="nav-link">
                        <i class="fas fa-user"></i> 
                        <?php echo htmlspecialchars($_SESSION['user']['name']); ?>
                    </span>
                    <a class="nav-link" href="../auth/logout.php">
                        <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3">
                <i class="fas fa-dollar-sign"></i> Configuración de Precios
            </h1>
            <div>
                <button type="button" class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#pricingCategoryModal">
                    <i class="fas fa-plus"></i> Nueva Categoría
                </button>
                <a href="view.php?id=<?php echo $event_id; ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Volver al Evento
                </a>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Métodos de Pago -->
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Métodos de Pago</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_payment_methods">
                            
                            <?php foreach ($payment_methods as $method): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" 
                                           name="payment_methods[]" 
                                           value="<?php echo $method['id']; ?>"
                                           id="payment_method_<?php echo $method['id']; ?>"
                                           <?php echo in_array($method['id'], $enabled_methods) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="payment_method_<?php echo $method['id']; ?>">
                                        <?php echo htmlspecialchars($method['name']); ?>
                                        <?php if (!empty($method['type']) && !empty($method['provider'])): ?>
                                            <small class="text-muted">
                                                (<?php echo ucfirst($method['type']); ?> - <?php echo ucfirst($method['provider']); ?>)
                                            </small>
                                        <?php endif; ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>

                            <button type="submit" class="btn btn-primary mt-3">
                                <i class="fas fa-save"></i> Guardar Cambios
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Categorías de Precio -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Categorías de Precio</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Precio</th>
                                        <th>Descuentos</th>
                                        <th>Capacidad</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pricing_categories as $category): ?>
                                        <tr>
                                            <td>
                                                <?php echo htmlspecialchars($category['name']); ?>
                                                <?php if ($category['description']): ?>
                                                    <small class="text-muted d-block">
                                                        <?php echo htmlspecialchars($category['description']); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>$<?php echo number_format($category['price'], 2); ?></td>
                                            <td>
                                                <?php if (!empty($category['discount_percentage']) && $category['discount_percentage'] > 0): ?>
                                                    <span class="badge bg-success">
                                                        <?php echo number_format($category['discount_percentage'], 2); ?>% General
                                                    </span>
                                                <?php endif; ?>
                                                <?php if (!empty($category['ieee_member_discount']) && $category['ieee_member_discount'] > 0): ?>
                                                    <span class="badge bg-info">
                                                        <?php echo number_format($category['ieee_member_discount'], 2); ?>% IEEE
                                                        <?php if (!empty($category['ieee_region'])): ?>
                                                            R<?php echo htmlspecialchars($category['ieee_region']); ?>
                                                        <?php endif; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($category['max_capacity'])): ?>
                                                    <?php echo number_format($category['max_capacity']); ?> plazas
                                                <?php else: ?>
                                                    <span class="text-muted">Sin límite</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $category['is_active'] ? 'success' : 'danger'; ?>">
                                                    <?php echo $category['is_active'] ? 'Activo' : 'Inactivo'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info" 
                                                        onclick="editCategory(<?php echo htmlspecialchars(json_encode($category)); ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('¿Está seguro de eliminar esta categoría?')">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo $category['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Categoría de Precio -->
    <div class="modal fade" id="pricingCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="pricingCategoryForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitle">Nueva Categoría de Precio</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" id="formAction" value="create">
                        <input type="hidden" name="id" id="categoryId">

                        <div class="mb-3">
                            <label for="name" class="form-label">Nombre</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Descripción</label>
                            <textarea class="form-control" id="description" name="description" rows="2"></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="price" class="form-label">Precio</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="price" name="price" 
                                       step="0.01" min="0" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="discount_percentage" class="form-label">Descuento General (%)</label>
                                <input type="number" class="form-control" id="discount_percentage" 
                                       name="discount_percentage" min="0" max="100" value="0">
                            </div>
                            <div class="col-md-6">
                                <label for="ieee_member_discount" class="form-label">Descuento IEEE (%)</label>
                                <input type="number" class="form-control" id="ieee_member_discount" 
                                       name="ieee_member_discount" min="0" max="100" value="0">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="ieee_region" class="form-label">Región IEEE</label>
                                <select class="form-select" id="ieee_region" name="ieee_region">
                                    <option value="">Todas las regiones</option>
                                    <?php for ($i = 1; $i <= 10; $i++): ?>
                                        <option value="<?php echo $i; ?>">R<?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="max_capacity" class="form-label">Capacidad Máxima</label>
                                <input type="number" class="form-control" id="max_capacity" 
                                       name="max_capacity" min="1">
                            </div>
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="is_active" name="is_active" checked>
                            <label class="form-check-label" for="is_active">Activo</label>
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

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function editCategory(category) {
            document.getElementById('formAction').value = 'update';
            document.getElementById('modalTitle').textContent = 'Editar Categoría de Precio';
            document.getElementById('categoryId').value = category.id;
            document.getElementById('name').value = category.name;
            document.getElementById('description').value = category.description;
            document.getElementById('price').value = category.price;
            document.getElementById('discount_percentage').value = category.discount_percentage;
            document.getElementById('ieee_member_discount').value = category.ieee_member_discount;
            document.getElementById('ieee_region').value = category.ieee_region;
            document.getElementById('max_capacity').value = category.max_capacity;
            document.getElementById('is_active').checked = category.is_active;

            new bootstrap.Modal(document.getElementById('pricingCategoryModal')).show();
        }
    </script>
</body>
</html> 