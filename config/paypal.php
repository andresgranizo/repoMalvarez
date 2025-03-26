<?php
// Configuración de PayPal
return [
    'client_id' => getenv('PAYPAL_CLIENT_ID'),
    'client_secret' => getenv('PAYPAL_CLIENT_SECRET'),
    'mode' => 'sandbox', // 'sandbox' o 'live'
    'currency' => 'USD',
    'urls' => [
        'base' => 'https://tudominio.com/EventManager',
        'success' => '/api/payments/paypal/capture.php',
        'cancel' => '/views/events/register.php'
    ]
]; 