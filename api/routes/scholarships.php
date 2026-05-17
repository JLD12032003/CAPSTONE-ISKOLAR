<?php
/**
 * Scholarships API Routes
 */

require_once __DIR__ . '/../../app/models/Scholarship.php';

class ScholarshipsApi {
    
    /**
     * Get all scholarships
     * GET /api/scholarships
     */
    public static function getAll() {
        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 10);
        $search = $_GET['search'] ?? '';
        $type = $_GET['type'] ?? '';
        $minAmount = $_GET['min_amount'] ?? '';
        $maxAmount = $_GET['max_amount'] ?? '';
        
        $errors = ApiValidator::pagination($page, $limit);
        if (!empty($errors)) {
            ApiResponse::validationError($errors);
        }
        
        $scholarshipModel = new Scholarship();
        $scholarships = $scholarshipModel->getActiveScholarships();
        
        // Apply filters
        if ($search) {
            $scholarships = array_filter($scholarships, function($scholarship) use ($search) {
                return stripos($scholarship['title'], $search) !== false ||
                       stripos($scholarship['description'], $search) !== false ||
                       stripos($scholarship['provider_name'], $search) !== false;
            });
        }
        
        if ($type) {
            $scholarships = array_filter($scholarships, function($scholarship) use ($type) {
                return $scholarship['scholarship_type'] === $type;
            });
        }
        
        if ($minAmount) {
            $scholarships = array_filter($scholarships, function($scholarship) use ($minAmount) {
                return $scholarship['amount'] >= $minAmount;
            });
        }
        
        if ($maxAmount) {
            $scholarships = array_filter($scholarships, function($scholarship) use ($maxAmount) {
                return $scholarship['amount'] <= $maxAmount;
            });
        }
        
        // Apply pagination
        $total = count($scholarships);
        $offset = ($page - 1) * $limit;
        $scholarships = array_slice($scholarships, $offset, $limit);
        
        ApiResponse::paginated($scholarships, $total, $page, $limit);
    }
    
    /**
     * Get scholarship by ID
     * GET /api/scholarships/{id}
     */
    public static function getById($id) {
        $scholarshipModel = new Scholarship();
        $scholarship = $scholarshipModel->getScholarshipById($id);
        
        if (!$scholarship) {
            ApiResponse::notFound('Scholarship not found');
        }
        
        ApiResponse::success($scholarship);
    }
    
    /**
     * Search scholarships
     * GET /api/scholarships/search
     */
    public static function search() {
        $query = $_GET['q'] ?? '';
        $filters = [
            'type' => $_GET['type'] ?? '',
            'min_amount' => $_GET['min_amount'] ?? '',
            'max_amount' => $_GET['max_amount'] ?? '',
            'school' => $_GET['school'] ?? '',
            'year_level' => $_GET['year_level'] ?? ''
        ];
        
        if (empty($query) && empty(array_filter($filters))) {
            ApiResponse::error('Search query or filters required', 400);
        }
        
        $scholarshipModel = new Scholarship();
        $scholarships = $scholarshipModel->getActiveScholarships();
        
        // Apply search and filters
        $results = array_filter($scholarships, function($scholarship) use ($query, $filters) {
            $matchesQuery = empty($query) || 
                           stripos($scholarship['title'], $query) !== false ||
                           stripos($scholarship['description'], $query) !== false ||
                           stripos($scholarship['provider_name'], $query) !== false;
            
            $matchesFilters = true;
            
            if ($filters['type'] && $scholarship['scholarship_type'] !== $filters['type']) {
                $matchesFilters = false;
            }
            
            if ($filters['min_amount'] && $scholarship['amount'] < $filters['min_amount']) {
                $matchesFilters = false;
            }
            
            if ($filters['max_amount'] && $scholarship['amount'] > $filters['max_amount']) {
                $matchesFilters = false;
            }
            
            if ($filters['school'] && stripos($scholarship['school_name'], $filters['school']) === false) {
                $matchesFilters = false;
            }
            
            return $matchesQuery && $matchesFilters;
        });
        
        ApiResponse::success([
            'query' => $query,
            'filters' => array_filter($filters),
            'results' => array_values($results),
            'total_results' => count($results)
        ]);
    }
}