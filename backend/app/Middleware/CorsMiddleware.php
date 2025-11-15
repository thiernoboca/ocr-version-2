<?php

namespace App\Middleware;

class CorsMiddleware
{
    public static function handle(): void
    {
        $allowedOrigins = explode(',', $_ENV['CORS_ALLOWED_ORIGINS'] ?? '*');
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        // Vérifier si l'origine est autorisée
        if (in_array('*', $allowedOrigins) || in_array($origin, $allowedOrigins)) {
            header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
        }

        header('Access-Control-Allow-Methods: ' . ($_ENV['CORS_ALLOWED_METHODS'] ?? 'GET, POST, PUT, DELETE, OPTIONS'));
        header('Access-Control-Allow-Headers: ' . ($_ENV['CORS_ALLOWED_HEADERS'] ?? 'Content-Type, Authorization'));
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');
    }
}
