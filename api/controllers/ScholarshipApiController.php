<?php
/**
 * Scholarship API Controller
 */

require_once __DIR__ . '/../../app/models/Scholarship.php';

class ScholarshipApiController extends ApiController {
    
    // Get public scholarships (no auth required)
    public function getPublicScholarships() {
        try {
            $scholarshipModel = new Scholarship();
            $pagination = ApiConfig::getPagination();
            
            // Get filters from query parameters
            $filters = [
                'search' => $_GET['search'] ?? null,
                'type' => $_GET['type'] ?? null,
                'min_amount' => $_GET['min_amount'] ?? null,
                'max_amount' => $_GET['max_amount'] ?? null,
                'school_id' => $_GET['school_id'] ?? null
            ];
            
            // Build query with filters
            $whereConditions = ["s.status = 'Active'", "s.application_start <= CURDATE()", "s.application_end >= CURDATE()"];
            $params = [];
            
            if ($filters['search']) {
                $whereConditions[] = "(s.title LIKE ? OR s.description LIKE ?)";
                $searchTerm = '%' . $filters['search'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            if ($filters['type']) {
                $whereConditions[] = "s.scholarship_type = ?";
                $params[] = $filters['type'];
            }
            
            if ($filters['min_amount']) {
                $whereConditions[] = "s.amount >= ?";
                $params[] = floatval($filters['min_amount']);
            }
            
            if ($filters['max_amount']) {
                $whereConditions[] = "s.amount <= ?";
                $params[] = floatval($filters['max_amount']);
            }
            
            if ($filters['school_id']) {
                $whereConditions[] = "s.school_id = ?";
                $params[] = intval($filters['school_id']);
            }
            
            $whereClause = implode(' AND ', $whereConditions);
            
            // Get total count
            $countSql = "
                SELECT COUNT(*) as total
                FROM scholarships s
                JOIN users u ON s.provider_id = u.id
                LEFT JOIN provider_profiles pp ON u.id = pp.user_id
                LEFT JOIN schools sc ON s.school_id = sc.id
                WHERE {$whereClause}
            ";
            
            $stmt = $this->conn->prepare($countSql);
            $stmt->execute($params);
            $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Get scholarships with pagination
            $sql = "
                SELECT s.*, u.fullname as provider_name, pp.organization_name, sc.school_name,
                       (s.slots - s.available_slots) as filled_slots
                FROM scholarships s
                JOIN users u ON s.provider_id = u.id
                LEFT JOIN provider_profiles pp ON u.id = pp.user_id
                LEFT JOIN schools sc ON s.school_id = sc.id
                WHERE {$whereClause}
                ORDER BY s.created_at DESC
                LIMIT ? OFFSET ?
            ";
            
            $params[] = $pagination['limit'];
            $params[] = $pagination['offset'];
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $scholarships = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $responseData = [
                'scholarships' => $scholarships,
                'pagination' => [
                    'page' => $pagination['page'],
                    'limit' => $pagination['limit'],
                    'total' => intval($total),
                    'pages' => ceil($total / $pagination['limit'])
                ],
                'filters' => $filters
            ];
            
            ApiConfig::response($responseData, 'Scholarships retrieved successfully');
        } catch (Exception $e) {
            ApiConfig::response(null, 'Error retrieving scholarships', 500);
        }
    }
    
    // Get single scholarship
    public function getScholarship() {
        $scholarshipId = intval($_REQUEST['path_params'][0] ?? 0);
        
        if (!$scholarshipId) {
            ApiConfig::response(null, 'Invalid scholarship ID', 422);
        }
        
        try {
            $scholarshipModel = new Scholarship();
            $scholarship = $scholarshipModel->getScholarshipById($scholarshipId);
            
            if (!$scholarship) {
                ApiConfig::response(null, 'Scholarship not found', 404);
            }
            
            // Get application statistics
            $stmt = $this->conn->prepare("
                SELECT 
                    COUNT(*) as total_applications,
                    SUM(CASE WHEN status = 'Submitted' THEN 1 ELSE 0 END) as pending_applications,
                    SUM(CASE WHEN provider_decision = 'Approved' THEN 1 ELSE 0 END) as approved_applications
                FROM scholarship_applications 
                WHERE scholarship_id = ?
            ");
            $stmt->execute([$scholarshipId]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $responseData = [
                'scholarship' => $scholarship,
                'statistics' => $stats
            ];
            
            ApiConfig::response($responseData, 'Scholarship retrieved successfully');
        } catch (Exception $e) {
            ApiConfig::response(null, 'Error retrieving scholarship', 500);
        }
    }
}