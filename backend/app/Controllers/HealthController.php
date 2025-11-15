<?php

namespace App\Controllers;

use App\Utils\Response;
use App\Utils\Database;

class HealthController
{
    /**
     * Vérifier l'état de santé du système
     */
    public function check(): void
    {
        $checks = [
            'api' => true,
            'database' => $this->checkDatabase(),
            'tesseract' => $this->checkTesseract(),
            'python' => $this->checkPython(),
            'storage' => $this->checkStorage(),
        ];

        $allHealthy = !in_array(false, $checks, true);

        Response::json([
            'success' => $allHealthy,
            'status' => $allHealthy ? 'healthy' : 'unhealthy',
            'checks' => $checks,
            'timestamp' => date('c')
        ], $allHealthy ? 200 : 503);
    }

    private function checkDatabase(): bool
    {
        try {
            Database::getConnection();
            Database::query('SELECT 1');
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function checkTesseract(): bool
    {
        $tesseractPath = $_ENV['TESSERACT_PATH'] ?? '/usr/bin/tesseract';
        return file_exists($tesseractPath) && is_executable($tesseractPath);
    }

    private function checkPython(): bool
    {
        $pythonPath = $_ENV['PYTHON_PATH'] ?? '/usr/bin/python3';
        return file_exists($pythonPath) && is_executable($pythonPath);
    }

    private function checkStorage(): bool
    {
        $uploadPath = __DIR__ . '/../../' . $_ENV['UPLOAD_PATH'];
        return is_dir($uploadPath) && is_writable($uploadPath);
    }
}
