<?php
$stored_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
$password = 'password';

echo "Verificando contraseña...\n";
echo "Hash almacenado: " . $stored_hash . "\n";
echo "Contraseña a verificar: " . $password . "\n";
echo "Resultado de verificación: " . (password_verify($password, $stored_hash) ? "VÁLIDA" : "INVÁLIDA") . "\n";

// Generar un nuevo hash para comparar
$new_hash = password_hash($password, PASSWORD_DEFAULT);
echo "\nGenerando nuevo hash para 'password': " . $new_hash . "\n";
echo "Verificación del nuevo hash: " . (password_verify($password, $new_hash) ? "VÁLIDA" : "INVÁLIDA") . "\n";
?> 