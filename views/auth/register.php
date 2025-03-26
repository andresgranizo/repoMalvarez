<?php
session_start();

// Redirigir si ya está autenticado
if(isset($_SESSION['user'])) {
    header('Location: ../../index.php');
    exit;
}

// Procesar el formulario de registro
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'action' => 'register',
        'name' => trim($_POST['first_name'] . ' ' . $_POST['last_name']),
        'email' => trim($_POST['email']),
        'password' => $_POST['password'],
        'ieee_member' => isset($_POST['ieee_member']) ? 1 : 0,
        'ieee_member_id' => isset($_POST['ieee_member']) ? trim($_POST['ieee_member_id']) : null
    ];

    error_log("Datos a enviar: " . print_r($data, true));

    // Validar contraseña
    if($data['password'] !== $_POST['password_confirm']) {
        $error = 'Las contraseñas no coinciden';
    } else {
        // Llamar a la API de autenticación
        $ch = curl_init('http://localhost/EventManager/api/auth.php');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        error_log("Respuesta HTTP: " . $http_code);
        error_log("Respuesta API: " . $response);
        
        if (curl_errno($ch)) {
            $error = 'Error de conexión: ' . curl_error($ch);
            $debug_info = "Error CURL: " . curl_error($ch);
        } else {
            $result = json_decode($response, true);
            $json_error = json_last_error();
            
            $debug_info = "Código HTTP: " . $http_code . "\n";
            $debug_info .= "Respuesta cruda: " . $response . "\n";
            $debug_info .= "Error JSON: " . json_last_error_msg() . "\n";
            $debug_info .= "Resultado decodificado: " . print_r($result, true);
            
            if ($json_error !== JSON_ERROR_NONE) {
                $error = 'Error al procesar la respuesta del servidor: ' . json_last_error_msg();
            } else if ($result === null) {
                $error = 'La respuesta del servidor está vacía';
            } else if ($result['success']) {
                header('Location: login.php?registered=true');
                exit;
            } else {
                $error = $result['message'] ?? 'Error en el registro. Por favor, intente más tarde.';
            }
        }
        curl_close($ch);
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - EventManager</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .register-container {
            max-width: 500px;
            margin: 50px auto;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .card-header {
            background-color: #0d6efd;
            color: white;
            text-align: center;
            padding: 20px;
            border-radius: 10px 10px 0 0;
        }
        .form-control:focus {
            box-shadow: none;
            border-color: #0d6efd;
        }
        .btn-primary {
            width: 100%;
            padding: 12px;
        }
        .password-requirements {
            font-size: 0.875rem;
            color: #6c757d;
        }
        .requirement {
            margin-bottom: 5px;
        }
        .requirement i {
            margin-right: 5px;
        }
        .valid {
            color: #198754;
        }
        .invalid {
            color: #dc3545;
        }
        .debug-info {
            margin-top: 20px;
            padding: 10px;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            font-family: monospace;
            white-space: pre-wrap;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="register-container">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Crear Cuenta</h4>
                </div>
                <div class="card-body p-4">
                    <?php if(isset($error)): ?>
                        <div class="alert alert-danger" role="alert">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                        <?php if(isset($debug_info)): ?>
                            <div class="debug-info">
                                <strong>Información de depuración:</strong>
                                <?php echo htmlspecialchars($debug_info); ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <form method="POST" action="" id="registerForm">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="first_name" class="form-label">Nombre</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" required>
                            </div>
                            <div class="col-md-6">
                                <label for="last_name" class="form-label">Apellido</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Correo Electrónico</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="phone" class="form-label">Teléfono</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                <input type="tel" class="form-control" id="phone" name="phone">
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="ieeeSwitch" name="ieee_member">
                                <label class="form-check-label" for="ieeeSwitch">¿Eres miembro IEEE?</label>
                            </div>
                        </div>

                        <div class="mb-3" id="ieeeMemberIdGroup" style="display: none;">
                            <label for="ieeeMemberId" class="form-label">IEEE Member ID</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                <input type="text" class="form-control" id="ieeeMemberId" name="ieee_member_id">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Contraseña</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" required autocomplete="new-password">
                            </div>
                            <div class="password-requirements mt-2">
                                <div class="requirement" id="length">
                                    <i class="fas fa-circle"></i> Mínimo 8 caracteres
                                </div>
                                <div class="requirement" id="uppercase">
                                    <i class="fas fa-circle"></i> Al menos una mayúscula
                                </div>
                                <div class="requirement" id="lowercase">
                                    <i class="fas fa-circle"></i> Al menos una minúscula
                                </div>
                                <div class="requirement" id="number">
                                    <i class="fas fa-circle"></i> Al menos un número
                                </div>
                                <div class="requirement" id="special">
                                    <i class="fas fa-circle"></i> Al menos un carácter especial
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="password_confirm" class="form-label">Confirmar Contraseña</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="password_confirm" name="password_confirm" required autocomplete="new-password">
                            </div>
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="terms" required>
                            <label class="form-check-label" for="terms">
                                Acepto los <a href="#" class="text-decoration-none">términos y condiciones</a>
                            </label>
                        </div>

                        <button type="submit" class="btn btn-primary" id="submitBtn" disabled>Registrarse</button>
                    </form>

                    <div class="text-center mt-3">
                        <p class="mb-0">¿Ya tienes una cuenta? <a href="login.php" class="text-decoration-none">Inicia Sesión</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const password = document.getElementById('password');
            const passwordConfirm = document.getElementById('password_confirm');
            const terms = document.getElementById('terms');
            const submitBtn = document.getElementById('submitBtn');
            const requirements = {
                length: /.{8,}/,
                uppercase: /[A-Z]/,
                lowercase: /[a-z]/,
                number: /[0-9]/,
                special: /[!@#$%^&*(),.?":{}|<>]/
            };

            function updateRequirements() {
                let valid = true;
                const value = password.value;

                for(let req in requirements) {
                    const element = document.getElementById(req);
                    const isValid = requirements[req].test(value);
                    element.classList.toggle('valid', isValid);
                    element.classList.toggle('invalid', !isValid);
                    element.querySelector('i').className = `fas fa-${isValid ? 'check' : 'circle'}`;
                    valid = valid && isValid;
                }

                return valid;
            }

            function validateForm() {
                const passwordValid = updateRequirements();
                const passwordsMatch = password.value === passwordConfirm.value;
                const termsAccepted = terms.checked;

                submitBtn.disabled = !(passwordValid && passwordsMatch && termsAccepted);
            }

            password.addEventListener('input', validateForm);
            passwordConfirm.addEventListener('input', validateForm);
            terms.addEventListener('change', validateForm);

            // Manejo del switch IEEE
            const ieeeSwitch = document.getElementById('ieeeSwitch');
            const ieeeMemberIdGroup = document.getElementById('ieeeMemberIdGroup');
            const ieeeMemberId = document.getElementById('ieeeMemberId');

            ieeeSwitch.addEventListener('change', function() {
                ieeeMemberIdGroup.style.display = this.checked ? 'block' : 'none';
                if (this.checked) {
                    ieeeMemberId.setAttribute('required', '');
                } else {
                    ieeeMemberId.removeAttribute('required');
                    ieeeMemberId.value = '';
                }
            });
        });
    </script>
</body>
</html> 