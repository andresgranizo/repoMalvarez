<?php
require_once '../includes/Database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Usuarios de prueba
    $users = [
        ['email' => 'admin@eventmanager.com', 'password' => 'password'],
        ['email' => 'organizador@eventmanager.com', 'password' => 'password'],
        ['email' => 'usuario@eventmanager.com', 'password' => 'password']
    ];

    foreach ($users as $user) {
        $hashedPassword = password_hash($user['password'], PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
        if ($stmt->execute([$hashedPassword, $user['email']])) {
            echo "Contraseña actualizada para: " . $user['email'] . "\n";
        } else {
            echo "Error actualizando contraseña para: " . $user['email'] . "\n";
        }
    }

    echo "¡Proceso completado!\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?> 