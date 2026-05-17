/**
 * Session Timeout Handler
 * Handles client-side session timeout warnings and automatic logout
 * Works with existing system without breaking functionality
 */

class SessionTimeoutHandler {
    constructor(config = {}) {
        this.config = {
            timeoutDuration: config.timeoutDuration || 3600, // 1 hour default
            warningTime: config.warningTime || 300, // 5 minutes warning
            checkInterval: config.checkInterval || 60, // Check every minute
            extendUrl: config.extendUrl || 'extend_session.php',
            logoutUrl: config.logoutUrl || 'logout.php',
            ...config
        };
        
        this.warningShown = false;
        this.timeRemaining = this.config.timeoutDuration;
        this.lastActivity = Date.now();
        
        this.init();
    }
    
    init() {
        // Only initialize if we have a valid timeout duration
        if (!this.config.timeoutDuration) {
            return;
        }
        
        // Track user activity
        this.trackActivity();
        
        // Start timeout checker
        this.startTimeoutChecker();
        
        // Create warning modal (hidden by default)
        this.createWarningModal();
    }
    
    trackActivity() {
        const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];
        
        events.forEach(event => {
            document.addEventListener(event, () => {
                this.updateActivity();
            }, { passive: true });
        });
    }
    
    updateActivity() {
        this.lastActivity = Date.now();
        
        // Hide warning if shown and user is active
        if (this.warningShown) {
            this.hideWarning();
        }
        
        // Extend session on server
        this.extendSession();
    }
    
    startTimeoutChecker() {
        setInterval(() => {
            this.checkTimeout();
        }, this.config.checkInterval * 1000);
    }
    
    checkTimeout() {
        const now = Date.now();
        const timeSinceActivity = (now - this.lastActivity) / 1000;
        this.timeRemaining = this.config.timeoutDuration - timeSinceActivity;
        
        if (this.timeRemaining <= 0) {
            this.handleTimeout();
        } else if (this.timeRemaining <= this.config.warningTime && !this.warningShown) {
            this.showWarning();
        }
    }
    
    showWarning() {
        this.warningShown = true;
        const modal = document.getElementById('session-timeout-modal');
        if (modal) {
            modal.style.display = 'block';
            this.updateWarningTimer();
        }
    }
    
    hideWarning() {
        this.warningShown = false;
        const modal = document.getElementById('session-timeout-modal');
        if (modal) {
            modal.style.display = 'none';
        }
    }
    
    updateWarningTimer() {
        const timerElement = document.getElementById('timeout-timer');
        if (timerElement && this.warningShown) {
            const minutes = Math.floor(this.timeRemaining / 60);
            const seconds = Math.floor(this.timeRemaining % 60);
            timerElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            
            if (this.timeRemaining > 0) {
                setTimeout(() => this.updateWarningTimer(), 1000);
            }
        }
    }
    
    extendSession() {
        // Don't make too many requests
        if (this.lastExtendRequest && (Date.now() - this.lastExtendRequest) < 30000) {
            return;
        }
        
        this.lastExtendRequest = Date.now();
        
        fetch(this.config.extendUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ action: 'extend' })
        }).catch(error => {
            console.log('Session extend request failed:', error);
        });
    }
    
    stayLoggedIn() {
        this.lastActivity = Date.now();
        this.hideWarning();
        this.extendSession();
    }
    
    logoutNow() {
        window.location.href = this.config.logoutUrl;
    }
    
    handleTimeout() {
        // Redirect to logout
        window.location.href = this.config.logoutUrl + '?reason=timeout';
    }
    
    createWarningModal() {
        // Only create if it doesn't exist
        if (document.getElementById('session-timeout-modal')) {
            return;
        }
        
        const modalHtml = `
            <div id="session-timeout-modal" class="session-timeout-modal" style="display: none;">
                <div class="session-timeout-content">
                    <div class="session-timeout-header">
                        <h3>⏰ Session Timeout Warning</h3>
                    </div>
                    <div class="session-timeout-body">
                        <p>Your session will expire in <strong id="timeout-timer">5:00</strong></p>
                        <p>You will be automatically logged out for security reasons.</p>
                        <p>Click "Stay Logged In" to continue your session.</p>
                    </div>
                    <div class="session-timeout-footer">
                        <button onclick="sessionTimeout.stayLoggedIn()" class="btn btn-primary">
                            Stay Logged In
                        </button>
                        <button onclick="sessionTimeout.logoutNow()" class="btn btn-secondary">
                            Logout Now
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // Add CSS styles
        this.addStyles();
    }
    
    addStyles() {
        const styles = `
            <style>
            .session-timeout-modal {
                position: fixed;
                z-index: 10000;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0,0,0,0.5);
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .session-timeout-content {
                background-color: #fff;
                padding: 0;
                border-radius: 8px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.3);
                max-width: 400px;
                width: 90%;
                animation: slideIn 0.3s ease-out;
            }
            
            .session-timeout-header {
                background-color: #ff6b35;
                color: white;
                padding: 15px 20px;
                border-radius: 8px 8px 0 0;
                text-align: center;
            }
            
            .session-timeout-header h3 {
                margin: 0;
                font-size: 18px;
            }
            
            .session-timeout-body {
                padding: 20px;
                text-align: center;
            }
            
            .session-timeout-body p {
                margin: 10px 0;
                color: #333;
            }
            
            #timeout-timer {
                color: #ff6b35;
                font-size: 24px;
                font-weight: bold;
            }
            
            .session-timeout-footer {
                padding: 15px 20px;
                text-align: center;
                border-top: 1px solid #eee;
                display: flex;
                gap: 10px;
                justify-content: center;
            }
            
            .session-timeout-footer .btn {
                padding: 8px 16px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 14px;
                min-width: 120px;
            }
            
            .session-timeout-footer .btn-primary {
                background-color: #0055ff;
                color: white;
            }
            
            .session-timeout-footer .btn-secondary {
                background-color: #6c757d;
                color: white;
            }
            
            .session-timeout-footer .btn:hover {
                opacity: 0.9;
            }
            
            @keyframes slideIn {
                from {
                    transform: translateY(-50px);
                    opacity: 0;
                }
                to {
                    transform: translateY(0);
                    opacity: 1;
                }
            }
            </style>
        `;
        
        document.head.insertAdjacentHTML('beforeend', styles);
    }
}

// Global instance
let sessionTimeout = null;

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Check if timeout config is available
    if (typeof sessionTimeoutConfig !== 'undefined') {
        sessionTimeout = new SessionTimeoutHandler(sessionTimeoutConfig);
    }
});