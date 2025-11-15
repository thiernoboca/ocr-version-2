<?php

/**
 * Point d'entrée principal de l'application OCR
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Utils\Router;
use App\Middleware\CorsMiddleware;
use App\Middleware\AuthMiddleware;
use Dotenv\Dotenv;

// Charger les variables d'environnement
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Gestion des erreurs
error_reporting($_ENV['APP_DEBUG'] === 'true' ? E_ALL : 0);
ini_set('display_errors', $_ENV['APP_DEBUG'] === 'true' ? '1' : '0');

// Headers CORS
CorsMiddleware::handle();

// Gérer les requêtes OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Initialiser le routeur
$router = new Router();

// Routes publiques (sans authentification)
$router->get('/', function() {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'OCR Document System API',
        'version' => '2.0',
        'endpoints' => [
            'POST /api/auth/login' => 'Authentification',
            'POST /api/documents/upload' => 'Upload et OCR de document',
            'GET /api/documents/{id}' => 'Récupérer un document',
            'DELETE /api/documents/{id}' => 'Supprimer un document',
            'GET /api/health' => 'Vérifier l\'état du système'
        ]
    ]);
});

$router->get('/api/health', 'App\Controllers\HealthController@check');
$router->post('/api/auth/login', 'App\Controllers\AuthController@login');
$router->post('/api/auth/register', 'App\Controllers\AuthController@register');

// Routes protégées (avec authentification JWT)
$protectedRoutes = [
    'POST /api/documents/upload' => 'App\Controllers\DocumentController@upload',
    'GET /api/documents/{id}' => 'App\Controllers\DocumentController@show',
    'GET /api/documents' => 'App\Controllers\DocumentController@index',
    'DELETE /api/documents/{id}' => 'App\Controllers\DocumentController@delete',

    // RGPD
    'GET /api/gdpr/export' => 'App\Controllers\GdprController@exportData',
    'GET /api/gdpr/info' => 'App\Controllers\GdprController@dataInfo',
    'DELETE /api/gdpr/account' => 'App\Controllers\GdprController@deleteAccount',
];

foreach ($protectedRoutes as $route => $handler) {
    list($method, $path) = explode(' ', $route);

    $router->addRoute($method, $path, function() use ($handler) {
        // Vérifier l'authentification JWT
        if (!AuthMiddleware::verify()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Non autorisé']);
            return;
        }

        // Exécuter le contrôleur
        list($class, $method) = explode('@', $handler);
        $controller = new $class();
        $controller->$method();
    });
}

// Dispatch de la requête
try {
    $router->dispatch();
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Erreur serveur',
        'message' => $_ENV['APP_DEBUG'] === 'true' ? $e->getMessage() : 'Une erreur est survenue'
    ]);
}
