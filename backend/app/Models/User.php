<?php

namespace App\Models;

use App\Utils\Database;

class User
{
    /**
     * Créer un nouvel utilisateur
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO users (name, email, password, created_at)
                VALUES (:name, :email, :password, NOW())";

        Database::query($sql, [
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password']
        ]);

        return (int)Database::lastInsertId();
    }

    /**
     * Trouver un utilisateur par email
     */
    public function findByEmail(string $email): ?array
    {
        $sql = "SELECT * FROM users WHERE email = :email LIMIT 1";
        $stmt = Database::query($sql, ['email' => $email]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    /**
     * Trouver un utilisateur par ID
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT id, name, email, created_at, last_login_at FROM users WHERE id = :id LIMIT 1";
        $stmt = Database::query($sql, ['id' => $id]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    /**
     * Mettre à jour la dernière connexion
     */
    public function updateLastLogin(int $id): void
    {
        $sql = "UPDATE users SET last_login_at = NOW() WHERE id = :id";
        Database::query($sql, ['id' => $id]);
    }

    /**
     * Supprimer un utilisateur et toutes ses données (RGPD)
     */
    public function delete(int $id): void
    {
        // Supprimer d'abord les documents de l'utilisateur
        $sql = "DELETE FROM documents WHERE user_id = :user_id";
        Database::query($sql, ['user_id' => $id]);

        // Puis supprimer l'utilisateur
        $sql = "DELETE FROM users WHERE id = :id";
        Database::query($sql, ['id' => $id]);
    }
}
