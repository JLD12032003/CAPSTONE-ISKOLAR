<?php
/**
 * API Configuration
 */

// API Settings
define('API_VERSION', '1.0');
define('API_BASE_URL', '/api');

// JWT Settings
define('JWT_SECRET_KEY', 'ISKOLar_API_Secret_Key_2026_Change_In_Production');
define('JWT_ALGORITHM', 'HS256');
define('JWT_EXPIRATION', 24 * 60 * 60); // 24 hours

// Rate Limiting
define('RATE_LIMIT_AUTH', 5); // 5 requests per minute for auth endpoints
define('RATE_LIMIT_GENERAL', 100); // 100 requests per minute for general endpoints
define('RATE_LIMIT_SEARCH', 20); // 20 requests per minute for search endpoints

// Pagination
define('DEFAULT_PAGE_SIZE', 10);
define('MAX_PAGE_SIZE', 100);

// File Upload
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_FILE_TYPES', ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx']);

// API Response Headers
function setApiHeaders() {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('X-API-Version: ' . API_VERSION);
    header('X-Powered-By: ISKOLar API');
}

// Error Reporting (disable in production)
if (getenv('ENVIRONMENT') !== 'production') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Timezone
date_default_timezone_set('Asia/Manila');

// CORS Headers
setApiHeaders();