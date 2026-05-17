<?php
/**
 * API Response Handler
 * Standardized JSON responses for the API
 */

class ApiResponse {
    
    /**
     * Send success response
     */
    public static function success($data = null, $message = 'Success', $code = 200) {
        http_response_code($code);
        
        $response = [
            'success' => true,
            'message' => $message,
            'timestamp' => date('c'),
            'data' => $data
        ];
        
        echo json_encode($response, JSON_PRETTY_PRINT);
        exit();
    }
    
    /**
     * Send error response
     */
    public static function error($message = 'Error', $code = 400, $details = null) {
        http_response_code($code);
        
        $response = [
            'success' => false,
            'message' => $message,
            'timestamp' => date('c'),
            'error' => [
                'code' => $code,
                'details' => $details
            ]
        ];
        
        echo json_encode($response, JSON_PRETTY_PRINT);
        exit();
    }
    
    /**
     * Send paginated response
     */
    public static function paginated($data, $total, $page, $limit, $message = 'Success') {
        $totalPages = ceil($total / $limit);
        
        $response = [
            'success' => true,
            'message' => $message,
            'timestamp' => date('c'),
            'data' => $data,
            'pagination' => [
                'current_page' => (int)$page,
                'per_page' => (int)$limit,
                'total' => (int)$total,
                'total_pages' => $totalPages,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1
            ]
        ];
        
        http_response_code(200);
        echo json_encode($response, JSON_PRETTY_PRINT);
        exit();
    }
    
    /**
     * Send validation error response
     */
    public static function validationError($errors) {
        self::error('Validation failed', 422, [
            'validation_errors' => $errors
        ]);
    }
    
    /**
     * Send unauthorized response
     */
    public static function unauthorized($message = 'Unauthorized') {
        self::error($message, 401);
    }
    
    /**
     * Send forbidden response
     */
    public static function forbidden($message = 'Forbidden') {
        self::error($message, 403);
    }
    
    /**
     * Send not found response
     */
    public static function notFound($message = 'Resource not found') {
        self::error($message, 404);
    }
}