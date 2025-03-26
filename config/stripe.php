<?php
// Configuración de Stripe
return [
    'publishable_key' => getenv('STRIPE_PUBLISHABLE_KEY'),
    'secret_key' => getenv('STRIPE_SECRET_KEY'),
    'webhook_secret' => getenv('STRIPE_WEBHOOK_SECRET'),
    'currency' => 'usd',
    'urls' => [
        'base' => 'https://tudominio.com/EventManager',
        'success' => '/api/payments/stripe/success.php',
        'cancel' => '/views/events/register.php'
    ]
]; 