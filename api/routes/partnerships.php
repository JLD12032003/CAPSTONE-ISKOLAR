<?php
/**
 * Partnership API Routes
 * RESTful endpoints for partnership request workflow
 */

require_once __DIR__ . '/../../app/controllers/PartnershipController.php';

class PartnershipRoutes {
    private $controller;
    
    public function __construct() {
        $this->controller = new PartnershipController();
    }
    
    /**
     * Handle partnership API requests
     */
    public function handleRequest($method, $path, $params = []) {
        try {
            switch ($method) {
                case 'POST':
                    return $this->handlePost($path, $params);
                    
                case 'GET':
                    return $this->handleGet($path, $params);
                    
                case 'PUT':
                    return $this->handlePut($path, $params);
                    
                default:
                    return $this->errorResponse('Method not allowed', 405);
            }
            
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
    
    /**
     * Handle POST requests
     */
    private function handlePost($path, $params) {
        switch ($path) {
            case '/partnerships/request':
                return $this->submitPartnershipRequest();
                
            case '/partnerships/approve':
                if (isset($params['token'])) {
                    return $this->processApproval($params['token']);
                }
                return $this->errorResponse('Token required', 400);
                
            default:
                return $this->errorResponse('Endpoint not found', 404);
        }
    }
    
    /**
     * Handle GET requests
     */
    private function handleGet($path, $params) {
        switch (true) {
            case $path === '/partnerships':
                return $this->getPartnershipRequests($params);
                
            case preg_match('/^\/partnerships\/(\d+)$/', $path, $matches):
                return $this->getPartnershipRequest($matches[1]);
                
            case preg_match('/^\/partnerships\/(\d+)\/logs$/', $path, $matches):
                return $this->getAuditLogs($matches[1]);
                
            case $path === '/partnerships/statistics':
                return $this->getStatistics($params);
                
            case preg_match('/^\/partnerships\/approve\/([a-f0-9]+)$/', $path, $matches):
                return $this->showApprovalPage($matches[1]);
                
            default:
                return $this->errorResponse('Endpoint not found', 404);
        }
    }
    
    /**
     * Handle PUT requests
     */
    private function handlePut($path, $params) {
        switch (true) {
            case preg_match('/^\/partnerships\/(\d+)\/status$/', $path, $matches):
                return $this->updatePartnershipStatus($matches[1]);
                
            default:
                return $this->errorResponse('Endpoint not found', 404);
        }
    }
    
    /**
     * Submit partnership request
     */
    private function submitPartnershipRequest() {
        // Validate authentication
        if (!$this->isAuthenticated()) {
            return $this->errorResponse('Authentication required', 401);
        }
        
        $result = $this->controller->submitRequest();
        
        if ($result['success']) {
            return $this->successResponse($result['message'], [
                'request_id' => $result['request_id']
            ], 201);
        } else {
            return $this->errorResponse($result['message'], 400);
        }
    }
    
    /**
     * Process approval from email link
     */
    private function processApproval($token) {
        $action = $_GET['action'] ?? $_POST['action'] ?? null;
        
        if (!$action || !in_array($action, ['APPROVED', 'REJECTED'])) {
            return $this->errorResponse('Valid action required (APPROVED/REJECTED)', 400);
        }
        
        $result = $this->controller->processApproval($token, $action);
        
        if ($result['success']) {
            return $this->successResponse($result['message']);
        } else {
            return $this->errorResponse($result['message'], 400);
        }
    }
    
    /**
     * Get partnership requests
     */
    private function getPartnershipRequests($params) {
        if (!$this->isAuthenticated()) {
            return $this->errorResponse('Authentication required', 401);
        }
        
        $userType = $_SESSION['user_type'];
        
        if ($userType === 'provider') {
            $result = $this->controller->getProviderRequests();
        } elseif ($userType === 'admin') {
            $schoolId = $params['school_id'] ?? null;
            $stage = $params['stage'] ?? null;
            $result = $this->controller->getSchoolRequests($schoolId, $stage);
        } else {
            return $this->errorResponse('Access denied', 403);
        }
        
        if ($result['success']) {
            return $this->successResponse('Requests retrieved successfully', $result['data']);
        } else {
            return $this->errorResponse($result['message'], 400);
        }
    }
    
    /**
     * Get specific partnership request
     */
    private function getPartnershipRequest($id) {
        if (!$this->isAuthenticated()) {
            return $this->errorResponse('Authentication required', 401);
        }
        
        $result = $this->controller->getRequest($id);
        
        if ($result['success']) {
            return $this->successResponse('Request retrieved successfully', $result['data']);
        } else {
            return $this->errorResponse($result['message'], 404);
        }
    }
    
    /**
     * Get audit logs
     */
    private function getAuditLogs($requestId) {
        if (!$this->isAuthenticated()) {
            return $this->errorResponse('Authentication required', 401);
        }
        
        $result = $this->controller->getAuditLogs($requestId);
        
        if ($result['success']) {
            return $this->successResponse('Audit logs retrieved successfully', $result['data']);
        } else {
            return $this->errorResponse($result['message'], 400);
        }
    }
    
    /**
     * Get partnership statistics
     */
    private function getStatistics($params) {
        if (!$this->isAuthenticated()) {
            return $this->errorResponse('Authentication required', 401);
        }
        
        $schoolId = null;
        if ($_SESSION['user_type'] === 'admin') {
            $schoolId = $params['school_id'] ?? null;
        }
        
        $result = $this->controller->getStatistics($schoolId);
        
        if ($result['success']) {
            return $this->successResponse('Statistics retrieved successfully', $result['data']);
        } else {
            return $this->errorResponse($result['message'], 400);
        }
    }
    
    /**
     * Show approval page (HTML response)
     */
    private function showApprovalPage($token) {
        // This would render an HTML page for email approval
        // For now, return JSON with approval form data
        
        try {
            // Validate token exists and is not expired
            $database = new Database();
            $conn = $database->connect();
            
            $stmt = $conn->prepare("
                SELECT as_stage.*, pr.organization_name, pr.partnership_title
                FROM approval_stages as_stage
                JOIN partnership_requests pr ON as_stage.partnership_request_id = pr.id
                WHERE as_stage.approval_token = ? 
                AND as_stage.token_expires_at > NOW() 
                AND as_stage.token_used = 0
            ");
            $stmt->execute([$token]);
            $stage = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$stage) {
                return $this->errorResponse('Invalid or expired approval link', 400);
            }
            
            return $this->successResponse('Approval page data', [
                'token' => $token,
                'organization_name' => $stage['organization_name'],
                'partnership_title' => $stage['partnership_title'],
                'stage_name' => $stage['stage_name'],
                'recipient_role' => $stage['recipient_role'],
                'expires_at' => $stage['token_expires_at']
            ]);
            
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
    
    /**
     * Check if user is authenticated
     */
    private function isAuthenticated() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    /**
     * Return success response
     */
    private function successResponse($message, $data = null, $statusCode = 200) {
        http_response_code($statusCode);
        
        $response = [
            'success' => true,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        header('Content-Type: application/json');
        return json_encode($response, JSON_PRETTY_PRINT);
    }
    
    /**
     * Return error response
     */
    private function errorResponse($message, $statusCode = 400) {
        http_response_code($statusCode);
        
        $response = [
            'success' => false,
            'error' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        header('Content-Type: application/json');
        return json_encode($response, JSON_PRETTY_PRINT);
    }
}

// Handle the request if this file is accessed directly
if (basename($_SERVER['PHP_SELF']) === 'partnerships.php') {
    session_start();
    
    $routes = new PartnershipRoutes();
    $method = $_SERVER['REQUEST_METHOD'];
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    
    // Remove API prefix from path
    $path = str_replace('/api', '', $path);
    
    // Get query parameters
    $params = $_GET;
    
    echo $routes->handleRequest($method, $path, $params);
}
?>