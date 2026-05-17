# 🚀 ISKOLar REST API Documentation

## Overview

The ISKOLar REST API provides programmatic access to the scholarship management system. It supports all major operations for students, providers, and administrators.

**Base URL**: `http://your-domain.com/api/`  
**Version**: 1.0  
**Authentication**: JWT Bearer Token  

---

## 📋 Table of Contents

- [Authentication](#authentication)
- [Response Format](#response-format)
- [Error Handling](#error-handling)
- [Rate Limiting](#rate-limiting)
- [Endpoints](#endpoints)
  - [Authentication](#authentication-endpoints)
  - [Public](#public-endpoints)
  - [Student](#student-endpoints)
  - [Provider](#provider-endpoints)
  - [Admin](#admin-endpoints)

---

## 🔐 Authentication

### JWT Token Authentication

Most endpoints require authentication using JWT tokens. Include the token in the Authorization header:

```
Authorization: Bearer YOUR_JWT_TOKEN
```

### Getting a Token

```http
POST /api/auth/login
Content-Type: application/json

{
  "email": "student@example.com",
  "password": "password123"
}
```

**Response:**
```json
{
  "success": true,
  "status": 200,
  "message": "Login successful",
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "user": {
      "id": 1,
      "fullname": "Juan Dela Cruz",
      "email": "student@example.com",
      "user_type": "student",
      "profile_completed": true
    }
  }
}
```

---

## 📊 Response Format

All API responses follow a consistent format:

```json
{
  "success": true,
  "status": 200,
  "message": "Operation successful",
  "data": { ... },
  "timestamp": "2026-04-20T10:30:00+00:00",
  "version": "1.0"
}
```

### Success Response (200-299)
```json
{
  "success": true,
  "status": 200,
  "message": "Data retrieved successfully",
  "data": { ... }
}
```

### Error Response (400-599)
```json
{
  "success": false,
  "status": 400,
  "message": "Bad request",
  "errors": ["Field 'email' is required"]
}
```

---

## ⚠️ Error Handling

### HTTP Status Codes

| Code | Description |
|------|-------------|
| 200 | OK - Request successful |
| 201 | Created - Resource created |
| 400 | Bad Request - Invalid input |
| 401 | Unauthorized - Authentication required |
| 403 | Forbidden - Insufficient permissions |
| 404 | Not Found - Resource not found |
| 422 | Unprocessable Entity - Validation failed |
| 500 | Internal Server Error |

### Common Error Responses

**Authentication Required:**
```json
{
  "success": false,
  "status": 401,
  "message": "Authorization header missing"
}
```

**Validation Error:**
```json
{
  "success": false,
  "status": 422,
  "message": "Missing required fields: email, password"
}
```

---

## 🚦 Rate Limiting

- **Limit**: 100 requests per hour per IP
- **Headers**: Rate limit info included in response headers
- **Exceeded**: Returns 429 Too Many Requests

---

## 📚 Endpoints

### Authentication Endpoints

#### POST /auth/login
Login with email and password.

**Request:**
```json
{
  "email": "user@example.com",
  "password": "password123"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "token": "jwt_token_here",
    "user": { ... }
  }
}
```

#### POST /auth/register
Register a new user account.

**Request:**
```json
{
  "fullname": "Juan Dela Cruz",
  "email": "juan@example.com",
  "password": "password123",
  "user_type": "student"
}
```

#### POST /auth/verify-email
Verify email address with token.

**Request:**
```json
{
  "token": "verification_token_here"
}
```

#### POST /auth/forgot-password
Request password reset.

**Request:**
```json
{
  "email": "user@example.com"
}
```

#### POST /auth/reset-password
Reset password with token.

**Request:**
```json
{
  "token": "reset_token_here",
  "password": "new_password123"
}
```

---

### Public Endpoints

#### GET /scholarships
Get public scholarships (no auth required).

**Query Parameters:**
- `page` (int): Page number (default: 1)
- `limit` (int): Items per page (default: 20, max: 100)
- `search` (string): Search in title/description
- `type` (string): Scholarship type filter
- `min_amount` (float): Minimum amount filter
- `max_amount` (float): Maximum amount filter
- `school_id` (int): School filter

**Response:**
```json
{
  "success": true,
  "data": {
    "scholarships": [...],
    "pagination": {
      "page": 1,
      "limit": 20,
      "total": 50,
      "pages": 3
    }
  }
}
```

#### GET /scholarships/{id}
Get single scholarship details.

#### GET /schools
Get list of schools.

---

### Student Endpoints
*Requires authentication with student role*

#### GET /student/profile
Get student profile data.

#### PUT /student/profile
Update entire student profile.

#### POST /student/profile/phase/{phase}
Update specific profile phase (1-5).

**Request for Phase 1:**
```json
{
  "last_name": "Dela Cruz",
  "first_name": "Juan",
  "birthdate": "2000-01-15",
  "place_of_birth": "Manila",
  "sex": "Male",
  "civil_status": "Single",
  "citizenship": "Filipino",
  "mobile_number": "09123456789",
  "present_address": "123 Main St, Manila",
  "permanent_address": "123 Main St, Manila",
  "zip_code": "1000"
}
```

#### GET /student/applications
Get student's scholarship applications.

#### POST /student/applications
Submit scholarship application.

**Request:**
```json
{
  "scholarship_id": 1,
  "personal_statement": "I am passionate about...",
  "why_deserve_scholarship": "I deserve this because..."
}
```

#### GET /student/awards
Get student's scholarship awards.

#### GET /student/dashboard
Get student dashboard data.

---

### Provider Endpoints
*Requires authentication with provider role*

#### GET /provider/scholarships
Get provider's scholarships.

#### POST /provider/scholarships
Create new scholarship.

**Request:**
```json
{
  "title": "Excellence Scholarship",
  "description": "For outstanding students",
  "scholarship_type": "Full",
  "amount": 50000,
  "slots": 10,
  "school_id": 1,
  "eligible_courses": "Engineering, IT",
  "min_gwa": 1.75,
  "max_family_income": 100000,
  "year_levels": "3rd Year, 4th Year",
  "application_start": "2026-05-01",
  "application_end": "2026-06-30",
  "status": "Active"
}
```

#### PUT /provider/scholarships/{id}
Update scholarship.

#### GET /provider/applications
Get applications for provider's scholarships.

**Query Parameters:**
- `scholarship_id` (int): Filter by scholarship
- `status` (string): Filter by status

#### PUT /provider/applications/{id}
Update application status (approve/reject).

**Request:**
```json
{
  "provider_decision": "Approved",
  "provider_notes": "Excellent candidate",
  "amount_awarded": 50000
}
```

#### GET /provider/dashboard
Get provider dashboard data.

---

### Admin Endpoints
*Requires authentication with admin role*

#### GET /admin/students
Get students for admin's school.

**Query Parameters:**
- `page`, `limit`: Pagination
- `search`: Search students

#### GET /admin/providers
Get all providers.

#### GET /admin/scholarships
Get scholarships for admin's school.

**Query Parameters:**
- `status`: Filter by status

#### GET /admin/reports
Get generated reports.

#### POST /admin/reports
Generate new report.

**Request:**
```json
{
  "report_type": "Monthly",
  "title": "April 2026 Report",
  "period_start": "2026-04-01",
  "period_end": "2026-04-30"
}
```

#### GET /admin/dashboard
Get admin dashboard data.

---

### General Endpoints
*Requires authentication*

#### GET /user/profile
Get current user profile.

#### PUT /user/profile
Update current user profile.

#### POST /user/logout
Logout (invalidate token).

---

## 📱 Usage Examples

### JavaScript/Fetch

```javascript
// Login
const loginResponse = await fetch('/api/auth/login', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    email: 'student@example.com',
    password: 'password123'
  })
});

const loginData = await loginResponse.json();
const token = loginData.data.token;

// Get scholarships
const scholarshipsResponse = await fetch('/api/scholarships?page=1&limit=10', {
  headers: {
    'Authorization': `Bearer ${token}`
  }
});

const scholarships = await scholarshipsResponse.json();
```

### cURL

```bash
# Login
curl -X POST http://localhost/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"student@example.com","password":"password123"}'

# Get scholarships with token
curl -X GET "http://localhost/api/scholarships?page=1&limit=10" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"

# Submit application
curl -X POST http://localhost/api/student/applications \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{
    "scholarship_id": 1,
    "personal_statement": "I am passionate about education...",
    "why_deserve_scholarship": "I deserve this scholarship because..."
  }'
```

### PHP

```php
// Login
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/api/auth/login');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'email' => 'student@example.com',
    'password' => 'password123'
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$data = json_decode($response, true);
$token = $data['data']['token'];

// Get profile
curl_setopt($ch, CURLOPT_URL, 'http://localhost/api/student/profile');
curl_setopt($ch, CURLOPT_POST, 0);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token
]);

$profile = curl_exec($ch);
curl_close($ch);
```

---

## 🔧 Integration Guide

### Mobile App Integration

1. **Authentication Flow**
   - Login → Store JWT token securely
   - Include token in all API requests
   - Handle token expiration (401 responses)

2. **Profile Setup**
   - Check `profile_completed` status
   - Guide user through 5-phase setup
   - Use phase-specific endpoints

3. **Real-time Updates**
   - Poll dashboard endpoint for updates
   - Implement push notifications for status changes

### Third-party Integration

1. **Webhook Support** (Future)
   - Application status changes
   - New scholarship notifications
   - Award notifications

2. **Bulk Operations** (Future)
   - Batch student import
   - Bulk scholarship creation
   - Mass notifications

---

## 🛠️ Development

### Local Setup

1. **Install Dependencies**
   ```bash
   # Ensure PHP 7.4+ and MySQL 5.7+
   # Import database_rbac.sql
   ```

2. **Configure API**
   ```php
   // api/config/ApiConfig.php
   const JWT_SECRET = 'your-secret-key';
   const DEBUG_MODE = true; // Set false in production
   ```

3. **Test Endpoints**
   ```bash
   # Test login
   curl -X POST http://localhost/api/auth/login \
     -H "Content-Type: application/json" \
     -d '{"email":"admin@davaocentralcollege.edu.ph","password":"Admin@DCC2024"}'
   ```

### Testing

Use tools like:
- **Postman** - API testing and documentation
- **Insomnia** - REST client
- **cURL** - Command line testing
- **PHPUnit** - Automated testing (future)

---

## 📈 Roadmap

### Version 1.1
- [ ] File upload endpoints
- [ ] Webhook support
- [ ] Real-time notifications
- [ ] Advanced filtering

### Version 1.2
- [ ] GraphQL support
- [ ] Bulk operations
- [ ] Advanced analytics
- [ ] Mobile SDK

---

## 🤝 Support

- **Documentation**: This file
- **Issues**: Report via GitHub
- **Email**: api-support@iskolar.ph

---

**API Version**: 1.0  
**Last Updated**: April 20, 2026  
**Status**: ✅ Production Ready