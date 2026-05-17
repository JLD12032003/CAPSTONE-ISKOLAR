<?php
/**
 * Admin API Controller
 */

class AdminApiController extends ApiController {
    
    public function __construct() {
        parent::__construct();
        $this->validateUser('admin');
    }
    
    // Get students
    public function getStudents() {
        try {
            $pagination = ApiConfig::getPagination();
            $search = $_GET['search'] ?? null;
            
            $whereClause = "u.user_type = 'student' AND u.school_id = ?";
            $params = [$this->user['school_id']];
            
            if ($search) {
                $whereClause .= " AND (u.fullname LIKE ? OR u.email LIKE ?)";
                $searchTerm = '%' . $search . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM users u WHERE {$whereClause}";
            $stmt = $this->conn->prepare($countSql);
            $stmt->execute($params);
            $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Get students with pagination
            $sql = "
                SELECT u.*, sp.course, sp.year_level, sp.gwa,
                       COUNT(DISTINCT sa.id) as total_applications,
                       COUNT(DISTINCT saw.id) as total_awards
                FROM users u
                LEFT JOIN student_profiles sp ON u.id = sp.user_id
                LEFT JOIN scholarship_applications sa ON u.id = sa.student_id
                LEFT JOIN scholarship_awards saw ON u.id = saw.student_id
                WHERE {$whereClause}
                GROUP BY u.id
                ORDER BY u.created_at DESC
                LIMIT ? OFFSET ?
            ";
            
            $params[] = $pagination['limit'];
            $params[] = $pagination['offset'];
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $responseData = [
                'students' => $students,
                'pagination' => [
                    'page' => $pagination['page'],
                    'limit' => $pagination['limit'],
                    'total' => intval($total),
                    'pages' => ceil($total / $pagination['limit'])
                ]
            ];
            
            ApiConfig::response($responseData, 'Students retrieved successfully');
        } catch (Exception $e) {
            ApiConfig::response(null, 'Error retrieving students', 500);
        }
    }
    
    // Get providers
    public function getProviders() {
        try {
            $pagination = ApiConfig::getPagination();
            
            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM users WHERE user_type = 'provider'";
            $stmt = $this->conn->prepare($countSql);
            $stmt->execute();
            $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Get providers with pagination
            $sql = "
                SELECT u.*, pp.organization_name, pp.organization_type,
                       COUNT(DISTINCT s.id) as total_scholarships,
                       COUNT(DISTINCT sa.id) as total_applications
                FROM users u
                LEFT JOIN provider_profiles pp ON u.id = pp.user_id
                LEFT JOIN scholarships s ON u.id = s.provider_id
                LEFT JOIN scholarship_applications sa ON s.id = sa.scholarship_id
                WHERE u.user_type = 'provider'
                GROUP BY u.id
                ORDER BY u.created_at DESC
                LIMIT ? OFFSET ?
            ";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$pagination['limit'], $pagination['offset']]);
            $providers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $responseData = [
                'providers' => $providers,
                'pagination' => [
                    'page' => $pagination['page'],
                    'limit' => $pagination['limit'],
                    'total' => intval($total),
                    'pages' => ceil($total / $pagination['limit'])
                ]
            ];
            
            ApiConfig::response($responseData, 'Providers retrieved successfully');
        } catch (Exception $e) {
            ApiConfig::response(null, 'Error retrieving providers', 500);
        }
    }
    
    // Get scholarships for admin's school
    public function getScholarships() {
        try {
            $pagination = ApiConfig::getPagination();
            $status = $_GET['status'] ?? null;
            
            $whereClause = "s.school_id = ?";
            $params = [$this->user['school_id']];
            
            if ($status) {
                $whereClause .= " AND s.status = ?";
                $params[] = $status;
            }
            
            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM scholarships s WHERE {$whereClause}";
            $stmt = $this->conn->prepare($countSql);
            $stmt->execute($params);
            $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Get scholarships with pagination
            $sql = "
                SELECT s.*, u.fullname as provider_name, pp.organization_name,
                       COUNT(DISTINCT sa.id) as total_applications,
                       COUNT(DISTINCT saw.id) as total_awards
                FROM scholarships s
                JOIN users u ON s.provider_id = u.id
                LEFT JOIN provider_profiles pp ON u.id = pp.user_id
                LEFT JOIN scholarship_applications sa ON s.id = sa.scholarship_id
                LEFT JOIN scholarship_awards saw ON s.id = saw.scholarship_id
                WHERE {$whereClause}
                GROUP BY s.id
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
                ]
            ];
            
            ApiConfig::response($responseData, 'Scholarships retrieved successfully');
        } catch (Exception $e) {
            ApiConfig::response(null, 'Error retrieving scholarships', 500);
        }
    }
    
    // Get reports
    public function getReports() {
        try {
            $pagination = ApiConfig::getPagination();
            
            $sql = "
                SELECT * FROM reports 
                WHERE school_id = ? OR school_id IS NULL
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?
            ";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$this->user['school_id'], $pagination['limit'], $pagination['offset']]);
            $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            ApiConfig::response($reports, 'Reports retrieved successfully');
        } catch (Exception $e) {
            ApiConfig::response(null, 'Error retrieving reports', 500);
        }
    }
    
    // Generate report
    public function generateReport() {
        $data = $this->getRequestData();
        $data = ApiConfig::sanitizeInput($data);
        
        $required = ['report_type', 'title'];
        $missing = ApiConfig::validateRequired($data, $required);
        
        if (!empty($missing)) {
            ApiConfig::response(null, 'Missing required fields: ' . implode(', ', $missing), 422);
        }
        
        try {
            $reportData = $this->generateReportData($data['report_type'], $data);
            
            $stmt = $this->conn->prepare("
                INSERT INTO reports (report_type, generated_by, school_id, title, report_data, period_start, period_end)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['report_type'],
                $this->user['id'],
                $this->user['school_id'],
                $data['title'],
                json_encode($reportData),
                $data['period_start'] ?? null,
                $data['period_end'] ?? null
            ]);
            
            $reportId = $this->conn->lastInsertId();
            
            ApiConfig::response(['report_id' => $reportId], 'Report generated successfully', 201);
        } catch (Exception $e) {
            ApiConfig::response(null, 'Error generating report', 500);
        }
    }
    
    // Get admin dashboard data
    public function getDashboard() {
        try {
            // Get statistics for admin's school
            $stmt = $this->conn->prepare("
                SELECT COUNT(DISTINCT u.id) as count 
                FROM users u
                WHERE u.user_type = 'student' AND u.school_id = ?
            ");
            $stmt->execute([$this->user['school_id']]);
            $totalStudents = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            $stmt = $this->conn->prepare("
                SELECT COUNT(DISTINCT s.id) as count 
                FROM scholarships s
                WHERE s.school_id = ? AND s.status = 'Active'
            ");
            $stmt->execute([$this->user['school_id']]);
            $activeScholarships = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            $stmt = $this->conn->prepare("
                SELECT COUNT(DISTINCT sa.id) as count 
                FROM scholarship_applications sa
                JOIN users u ON sa.student_id = u.id
                WHERE u.school_id = ?
            ");
            $stmt->execute([$this->user['school_id']]);
            $totalApplications = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            $stmt = $this->conn->prepare("
                SELECT COUNT(DISTINCT saw.id) as count 
                FROM scholarship_awards saw
                JOIN users u ON saw.student_id = u.id
                WHERE u.school_id = ?
            ");
            $stmt->execute([$this->user['school_id']]);
            $totalAwards = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            // Get school information
            $stmt = $this->conn->prepare("SELECT * FROM schools WHERE id = ?");
            $stmt->execute([$this->user['school_id']]);
            $school = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get recent scholarships
            $stmt = $this->conn->prepare("
                SELECT s.*, u.fullname as provider_name, pp.organization_name
                FROM scholarships s
                JOIN users u ON s.provider_id = u.id
                LEFT JOIN provider_profiles pp ON u.id = pp.user_id
                WHERE s.school_id = ?
                ORDER BY s.created_at DESC
                LIMIT 5
            ");
            $stmt->execute([$this->user['school_id']]);
            $recentScholarships = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $dashboardData = [
                'statistics' => [
                    'total_students' => intval($totalStudents),
                    'active_scholarships' => intval($activeScholarships),
                    'total_applications' => intval($totalApplications),
                    'total_awards' => intval($totalAwards)
                ],
                'school' => $school,
                'recent_scholarships' => $recentScholarships
            ];
            
            ApiConfig::response($dashboardData, 'Dashboard data retrieved successfully');
        } catch (Exception $e) {
            ApiConfig::response(null, 'Error retrieving dashboard data', 500);
        }
    }
    
    private function generateReportData($reportType, $params) {
        // This would contain the logic to generate different types of reports
        // For now, return a simple structure
        return [
            'type' => $reportType,
            'generated_at' => date('Y-m-d H:i:s'),
            'school_id' => $this->user['school_id'],
            'parameters' => $params
        ];
    }
}