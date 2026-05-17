/**
 * ISKOLar API Frontend Integration Examples
 * JavaScript/jQuery examples for integrating with the API
 */

class ISKOLarAPI {
    constructor(baseUrl = '/api') {
        this.baseUrl = baseUrl;
        this.token = localStorage.getItem('iskolar_token');
    }

    // Set authentication token
    setToken(token) {
        this.token = token;
        localStorage.setItem('iskolar_token', token);
    }

    // Remove authentication token
    removeToken() {
        this.token = null;
        localStorage.removeItem('iskolar_token');
    }

    // Make API request
    async request(endpoint, options = {}) {
        const url = `${this.baseUrl}${endpoint}`;
        const config = {
            headers: {
                'Content-Type': 'application/json',
                ...options.headers
            },
            ...options
        };

        // Add authorization header if token exists
        if (this.token) {
            config.headers.Authorization = `Bearer ${this.token}`;
        }

        try {
            const response = await fetch(url, config);
            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'API request failed');
            }

            return data;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    }

    // Authentication methods
    async register(userData) {
        return this.request('/auth/register', {
            method: 'POST',
            body: JSON.stringify(userData)
        });
    }

    async login(email, password) {
        const response = await this.request('/auth/login', {
            method: 'POST',
            body: JSON.stringify({ email, password })
        });

        if (response.success && response.data.token) {
            this.setToken(response.data.token);
        }

        return response;
    }

    async logout() {
        try {
            await this.request('/auth/logout', { method: 'POST' });
        } finally {
            this.removeToken();
        }
    }

    async verifyEmail(token) {
        return this.request('/auth/verify', {
            method: 'POST',
            body: JSON.stringify({ token })
        });
    }

    // Student methods
    async getProfile() {
        return this.request('/students/profile');
    }

    async updateProfilePhase(phase, data) {
        return this.request(`/students/profile/phase/${phase}`, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    }

    async getScholarships(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        return this.request(`/students/scholarships?${queryString}`);
    }

    async applyForScholarship(scholarshipId, applicationData) {
        return this.request(`/students/apply/${scholarshipId}`, {
            method: 'POST',
            body: JSON.stringify(applicationData)
        });
    }

    async getApplications() {
        return this.request('/students/applications');
    }

    // Scholarship methods
    async getAllScholarships(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        return this.request(`/scholarships?${queryString}`);
    }

    async getScholarshipById(id) {
        return this.request(`/scholarships/${id}`);
    }

    async searchScholarships(query, filters = {}) {
        const params = { q: query, ...filters };
        const queryString = new URLSearchParams(params).toString();
        return this.request(`/scholarships/search?${queryString}`);
    }
}

// Usage Examples

// Initialize API client
const api = new ISKOLarAPI();

// Example 1: User Registration
async function registerUser() {
    try {
        const userData = {
            fullname: 'Juan Dela Cruz',
            email: 'juan@example.com',
            password: 'password123',
            confirm_password: 'password123',
            user_type: 'student'
        };

        const response = await api.register(userData);
        
        if (response.success) {
            alert('Registration successful! Please check your email for verification.');
        }
    } catch (error) {
        alert('Registration failed: ' + error.message);
    }
}

// Example 2: User Login
async function loginUser() {
    try {
        const email = document.getElementById('email').value;
        const password = document.getElementById('password').value;

        const response = await api.login(email, password);
        
        if (response.success) {
            // Redirect based on user type
            const userType = response.data.user.user_type;
            
            if (userType === 'student') {
                if (!response.data.user.profile_completed) {
                    window.location.href = '/app/views/student/profile_setup.php';
                } else {
                    window.location.href = '/app/views/student_home.php';
                }
            } else if (userType === 'provider') {
                window.location.href = '/app/views/provider/dashboard.php';
            } else if (userType === 'admin') {
                window.location.href = '/app/views/admin/dashboard.php';
            }
        }
    } catch (error) {
        alert('Login failed: ' + error.message);
    }
}

// Example 3: Load Scholarships with Pagination
async function loadScholarships(page = 1) {
    try {
        const response = await api.getScholarships({
            page: page,
            limit: 10,
            search: document.getElementById('search').value
        });

        if (response.success) {
            displayScholarships(response.data);
            displayPagination(response.pagination);
        }
    } catch (error) {
        console.error('Failed to load scholarships:', error);
    }
}

function displayScholarships(scholarships) {
    const container = document.getElementById('scholarships-container');
    container.innerHTML = '';

    scholarships.forEach(scholarship => {
        const card = document.createElement('div');
        card.className = 'scholarship-card';
        card.innerHTML = `
            <h3>${scholarship.title}</h3>
            <p>${scholarship.description}</p>
            <div class="scholarship-details">
                <span class="amount">₱${Number(scholarship.amount).toLocaleString()}</span>
                <span class="type">${scholarship.scholarship_type}</span>
                <span class="provider">${scholarship.provider_name}</span>
            </div>
            <button onclick="applyForScholarship(${scholarship.id})" class="btn-apply">
                Apply Now
            </button>
        `;
        container.appendChild(card);
    });
}

// Example 4: Apply for Scholarship
async function applyForScholarship(scholarshipId) {
    try {
        const applicationData = {
            personal_statement: document.getElementById('personal_statement').value,
            why_deserve_scholarship: document.getElementById('why_deserve').value
        };

        const response = await api.applyForScholarship(scholarshipId, applicationData);
        
        if (response.success) {
            alert('Application submitted successfully!');
            // Refresh applications list
            loadMyApplications();
        }
    } catch (error) {
        if (error.message.includes('Profile must be completed')) {
            if (confirm('You need to complete your profile first. Go to profile setup?')) {
                window.location.href = '/app/views/student/profile_setup.php';
            }
        } else {
            alert('Application failed: ' + error.message);
        }
    }
}

// Example 5: Update Profile Phase
async function updateProfilePhase(phase) {
    try {
        const formData = new FormData(document.getElementById(`phase-${phase}-form`));
        const data = Object.fromEntries(formData.entries());

        const response = await api.updateProfilePhase(phase, data);
        
        if (response.success) {
            alert(`Phase ${phase} updated successfully!`);
            // Move to next phase or complete profile
            if (phase < 5) {
                showPhase(phase + 1);
            } else {
                window.location.href = '/app/views/student_home.php';
            }
        }
    } catch (error) {
        alert('Update failed: ' + error.message);
    }
}

// Example 6: Search Scholarships with Filters
async function searchScholarships() {
    try {
        const query = document.getElementById('search-query').value;
        const filters = {
            type: document.getElementById('scholarship-type').value,
            min_amount: document.getElementById('min-amount').value,
            max_amount: document.getElementById('max-amount').value
        };

        const response = await api.searchScholarships(query, filters);
        
        if (response.success) {
            displaySearchResults(response.data.results);
            document.getElementById('results-count').textContent = 
                `Found ${response.data.total_results} scholarships`;
        }
    } catch (error) {
        console.error('Search failed:', error);
    }
}

// Example 7: Auto-refresh Token
setInterval(async () => {
    if (api.token) {
        try {
            await api.request('/auth/refresh', { method: 'POST' });
        } catch (error) {
            // Token expired, redirect to login
            api.removeToken();
            window.location.href = '/index.php';
        }
    }
}, 23 * 60 * 60 * 1000); // Refresh every 23 hours

// Example 8: Handle API Errors Globally
window.addEventListener('unhandledrejection', event => {
    if (event.reason && event.reason.message) {
        if (event.reason.message.includes('Unauthorized')) {
            api.removeToken();
            window.location.href = '/index.php';
        }
    }
});

// jQuery Examples (if using jQuery)
$(document).ready(function() {
    // Load scholarships on page load
    loadScholarships();

    // Search on input change (with debounce)
    let searchTimeout;
    $('#search').on('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            loadScholarships(1);
        }, 500);
    });

    // Form submission with API
    $('#application-form').on('submit', async function(e) {
        e.preventDefault();
        
        const scholarshipId = $(this).data('scholarship-id');
        const formData = {
            personal_statement: $('#personal_statement').val(),
            why_deserve_scholarship: $('#why_deserve').val()
        };

        try {
            await api.applyForScholarship(scholarshipId, formData);
            $('#application-modal').modal('hide');
            showSuccessMessage('Application submitted successfully!');
        } catch (error) {
            showErrorMessage(error.message);
        }
    });
});

// Utility functions
function showSuccessMessage(message) {
    // Show success toast or modal
    console.log('Success:', message);
}

function showErrorMessage(message) {
    // Show error toast or modal
    console.error('Error:', message);
}

// Export for use in other files
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ISKOLarAPI;
}