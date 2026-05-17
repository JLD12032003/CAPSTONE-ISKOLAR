<?php
/**
 * ISKOLar REST API
 * Version: 1.0
 * 
 * Main API entry point with routing
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/config/ApiConfig.php';
require_once __DIR__ . '/middleware/AuthMiddleware.php';
require_once __DIR__ . '/controllers/ApiController.php';
require_once __DIR__ . '/controllers/AuthApiController.php';
require_once __DIR__ . '/controllers/StudentApiController.php';
require_once __DIR__ . '/controllers/ScholarshipApiController.php';
require_once __DIR__ . '/controllers/ProviderApiController.php';
require_once __DIR__ . '/controllers/AdminApiController.php';

class ApiRouter {
    private $routes = [];
    private $authMiddleware;

    public function __construct() {
        $this->authMiddleware = new AuthMiddleware();
    }

    public function addRoute($method, $path, $controller, $action, $requireAuth = false) {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'controller' => $controller,
            'action' => $action,
            'requireAuth' => $requireAuth
        ];
    }

    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $path = str_replace('/api', '', $path); // Remove /api prefix
        
        foreach ($this->routes as $route) {
            if ($this->matchRoute($method, $path, $route)) {
                // Check authentication if required
                if ($route['requireAuth']) {
                    $authResult = $this->authMiddleware->authenticate();
                    if (!$authResult['success']) {
                        http_response_code(401);
                        echo json_encode(['error' => 'Unauthorized', 'message' => $authResult['message']]);
                        return;
                    }
                    $_REQUEST['user'] = $authResult['user'];
                }

                // Instantiate controller and call action
                $controllerClass = $route['controller'];
                $controller = new $controllerClass();
                $action = $route['action'];
                
                try {
                    $controller->$action();
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode([
                        'error' => 'Internal Server Error',
                        'message' => ApiConfig::DEBUG_MODE ? $e->getMessage() : 'An error occurred'
                    ]);
                }
                return;
            }
        }

        // Route not found
        http_response_code(404);
        echo json_encode(['error' => 'Not Found', 'message' => 'API endpoint not found']);
    }

    private function matchRoute($method, $path, $route) {
        if ($method !== $route['method']) {
            return false;
        }

        // Simple pattern matching (can be enhanced with regex)
        $routePath = $route['path'];
        
        // Handle dynamic segments like /users/{id}
        $routePattern = preg_replace('/\{[^}]+\}/', '([^/]+)', $routePath);
        $routePattern = '#^' . $routePattern . '$#';
        
        if (preg_match($routePattern, $path, $matches)) {
            // Store path parameters
            $_REQUEST['path_params'] = array_slice($matches, 1);
            return true;
        }

        return false;
    }
}

// Initialize router
$router = new ApiRouter();

// ============================================
// API ROUTES DEFINITION
// ============================================

// Authentication routes (no auth required)
$router->addRoute('POST', '/auth/login', 'AuthApiController', 'login');
$router->addRoute('POST', '/auth/register', 'AuthApiController', 'register');
$router->addRoute('POST', '/auth/verify-email', 'AuthApiController', 'verifyEmail');
$router->addRoute('POST', '/auth/forgot-password', 'AuthApiController', 'forgotPassword');
$router->addRoute('POST', '/auth/reset-password', 'AuthApiController', 'resetPassword');

// Public routes
$router->addRoute('GET', '/scholarships', 'ScholarshipApiController', 'getPublicScholarships');
$router->addRoute('GET', '/scholarships/{id}', 'ScholarshipApiController', 'getScholarship');
$router->addRoute('GET', '/schools', 'ApiController', 'getSchools');

// Student routes (auth required)
$router->addRoute('GET', '/student/profile', 'StudentApiController', 'getProfile', true);
$router->addRoute('PUT', '/student/profile', 'StudentApiController', 'updateProfile', true);
$router->addRoute('POST', '/student/profile/phase/{phase}', 'StudentApiController', 'updateProfilePhase', true);
$router->addRoute('GET', '/student/applications', 'StudentApiController', 'getApplications', true);
$router->addRoute('POST', '/student/applications', 'StudentApiController', 'submitApplication', true);
$router->addRoute('GET', '/student/awards', 'StudentApiController', 'getAwards', true);
$router->addRoute('GET', '/student/dashboard', 'StudentApiController', 'getDashboard', true);

// Provider routes (auth required)
$router->addRoute('GET', '/provider/scholarships', 'ProviderApiController', 'getScholarships', true);
$router->addRoute('POST', '/provider/scholarships', 'ProviderApiController', 'createScholarship', true);
$router->addRoute('PUT', '/provider/scholarships/{id}', 'ProviderApiController', 'updateScholarship', true);
$router->addRoute('GET', '/provider/applications', 'ProviderApiController', 'getApplications', true);
$router->addRoute('PUT', '/provider/applications/{id}', 'ProviderApiController', 'updateApplication', true);
$router->addRoute('GET', '/provider/dashboard', 'ProviderApiController', 'getDashboard', true);

// Admin routes (auth required)
$router->addRoute('GET', '/admin/students', 'AdminApiController', 'getStudents', true);
$router->addRoute('GET', '/admin/providers', 'AdminApiController', 'getProviders', true);
$router->addRoute('GET', '/admin/scholarships', 'AdminApiController', 'getScholarships', true);
$router->addRoute('GET', '/admin/reports', 'AdminApiController', 'getReports', true);
$router->addRoute('POST', '/admin/reports', 'AdminApiController', 'generateReport', true);
$router->addRoute('GET', '/admin/dashboard', 'AdminApiController', 'getDashboard', true);

// General routes (auth required)
$router->addRoute('GET', '/user/profile', 'ApiController', 'getUserProfile', true);
$router->addRoute('PUT', '/user/profile', 'ApiController', 'updateUserProfile', true);
$router->addRoute('POST', '/user/logout', 'ApiController', 'logout', true);

// Handle the request
$router->handleRequest();