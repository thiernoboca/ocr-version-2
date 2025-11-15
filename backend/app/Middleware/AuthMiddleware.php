<?php

namespace App\Middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Utils\Logger;

class AuthMiddleware
{
    public static function verify(): bool
    {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;

        if (!$authHeader) {
            Logger::warning('Tentative d\'accès sans token JWT');
            return false;
        }

        // Extraire le token (format: "Bearer TOKEN")
        $parts = explode(' ', $authHeader);
        if (count($parts) !== 2 || $parts[0] !== 'Bearer') {
            Logger::warning('Format de token JWT invalide');
            return false;
        }

        $token = $parts[1];

        try {
            $secret = $_ENV['JWT_SECRET'];
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));

            // Stocker les infos utilisateur dans une variable globale
            $GLOBALS['user_id'] = $decoded->user_id ?? null;
            $GLOBALS['user_email'] = $decoded->email ?? null;

            return true;
        } catch (\Exception $e) {
            Logger::error('Erreur de validation JWT', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public static function generateToken(int $userId, string $email): string
    {
        $secret = $_ENV['JWT_SECRET'];
        $expiration = time() + (int)($_ENV['JWT_EXPIRATION'] ?? 3600);

        $payload = [
            'user_id' => $userId,
            'email' => $email,
            'iat' => time(),
            'exp' => $expiration
        ];

        return JWT::encode($payload, $secret, 'HS256');
    }

    public static function getCurrentUserId(): ?int
    {
        return $GLOBALS['user_id'] ?? null;
    }
}
