<?php

namespace App\Controllers;

use App\Models\User;
use App\Middleware\AuthMiddleware;
use App\Utils\Response;
use App\Utils\Validator;
use App\Utils\Logger;

class AuthController
{
    private User $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    /**
     * Inscription d'un nouvel utilisateur
     */
    public function register(): void
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);

            // Validation
            $email = $input['email'] ?? null;
            $password = $input['password'] ?? null;
            $name = $input['name'] ?? null;

            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Response::error('Email invalide', null, 422);
                return;
            }

            if (!$password || strlen($password) < 8) {
                Response::error('Le mot de passe doit contenir au moins 8 caractères', null, 422);
                return;
            }

            if (!$name || strlen($name) < 2) {
                Response::error('Nom invalide', null, 422);
                return;
            }

            // Vérifier si l'email existe déjà
            if ($this->userModel->findByEmail($email)) {
                Response::error('Cet email est déjà utilisé', null, 409);
                return;
            }

            // Créer l'utilisateur
            $userId = $this->userModel->create([
                'name' => Validator::sanitizeString($name),
                'email' => strtolower(trim($email)),
                'password' => password_hash($password, PASSWORD_BCRYPT)
            ]);

            // Générer un token JWT
            $token = AuthMiddleware::generateToken($userId, $email);

            Logger::info('Nouvel utilisateur inscrit', ['user_id' => $userId, 'email' => $email]);

            Response::success([
                'user_id' => $userId,
                'token' => $token,
                'expires_in' => (int)$_ENV['JWT_EXPIRATION']
            ], 'Inscription réussie', 201);

        } catch (\Exception $e) {
            Logger::error('Erreur inscription', ['error' => $e->getMessage()]);
            Response::serverError();
        }
    }

    /**
     * Connexion d'un utilisateur
     */
    public function login(): void
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);

            $email = $input['email'] ?? null;
            $password = $input['password'] ?? null;

            if (!$email || !$password) {
                Response::error('Email et mot de passe requis', null, 422);
                return;
            }

            // Trouver l'utilisateur
            $user = $this->userModel->findByEmail($email);

            if (!$user || !password_verify($password, $user['password'])) {
                Response::error('Identifiants incorrects', null, 401);
                return;
            }

            // Générer un token JWT
            $token = AuthMiddleware::generateToken($user['id'], $user['email']);

            // Mettre à jour la dernière connexion
            $this->userModel->updateLastLogin($user['id']);

            Logger::info('Utilisateur connecté', ['user_id' => $user['id'], 'email' => $email]);

            Response::success([
                'user_id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'token' => $token,
                'expires_in' => (int)$_ENV['JWT_EXPIRATION']
            ], 'Connexion réussie');

        } catch (\Exception $e) {
            Logger::error('Erreur connexion', ['error' => $e->getMessage()]);
            Response::serverError();
        }
    }
}
