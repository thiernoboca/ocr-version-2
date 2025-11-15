<?php

namespace App\Utils;

class Response
{
    public static function json(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    public static function success($data = null, string $message = 'Succès', int $statusCode = 200): void
    {
        self::json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $statusCode);
    }

    public static function error(string $message, $errors = null, int $statusCode = 400): void
    {
        self::json([
            'success' => false,
            'error' => $message,
            'details' => $errors
        ], $statusCode);
    }

    public static function unauthorized(string $message = 'Non autorisé'): void
    {
        self::error($message, null, 401);
    }

    public static function forbidden(string $message = 'Accès interdit'): void
    {
        self::error($message, null, 403);
    }

    public static function notFound(string $message = 'Ressource non trouvée'): void
    {
        self::error($message, null, 404);
    }

    public static function serverError(string $message = 'Erreur serveur'): void
    {
        self::error($message, null, 500);
    }
}
