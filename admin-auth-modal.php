<!-- Admin Authentication Modal -->
<!-- Include this in pages that need inline admin authentication -->

<div id="adminAuthModal" class="admin-auth-modal" style="display: none;">
    <div class="admin-auth-overlay"></div>
    <div class="admin-auth-content">
        <button class="admin-auth-close" onclick="closeAdminAuthModal()">
            <i class="fas fa-times"></i>
        </button>

        <div class="admin-auth-header">
            <div class="admin-auth-icon">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h3>Admin Authentication Required</h3>
            <p id="adminAuthMessage">Please enter admin credentials to continue</p>
        </div>

        <form id="adminAuthForm" onsubmit="return handleAdminAuth(event)">
            <div class="admin-auth-error" id="adminAuthError" style="display: none;"></div>

            <input type="hidden" id="adminAuthAction" name="action" value="">
            <input type="hidden" id="adminAuthData" name="data" value="">

            <label class="input-icon">
                <i class="fas fa-user"></i>
                <input
                    type="text"
                    id="adminUsername"
                    name="username"
                    required
                    placeholder=" "
                    maxlength="50"
                    autocomplete="off">
                <span>Admin Username</span>
            </label>

            <label class="input-icon">
                <i class="fas fa-lock"></i>
                <input
                    type="password"
                    id="adminPassword"
                    name="password"
                    required
                    placeholder=" "
                    maxlength="100"
                    autocomplete="off">
                <span>Admin Password</span>
                <i class="fas fa-eye password-toggle" onclick="toggleAdminPassword()"></i>
            </label>

            <div class="admin-auth-actions">
                <button type="button" class="btn-cancel" onclick="closeAdminAuthModal()">
                    Cancel
                </button>
                <button type="submit" class="btn-submit" id="adminAuthSubmit">
                    <i class="fas fa-check"></i> Authenticate
                </button>
            </div>
        </form>
    </div>
</div>

<style>
    .admin-auth-modal {
        position: fixed;
        inset: 0;
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: var(--spacing-md, 20px);
        animation: fadeIn 0.2s ease;
    }

    .admin-auth-overlay {
        position: absolute;
        inset: 0;
        background: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(4px);
    }

    .admin-auth-content {
        position: relative;
        background: var(--bg-dark, #24323d);
        border-radius: var(--radius-xl, 20px);
        padding: var(--spacing-2xl, 30px);
        max-width: 450px;
        width: 100%;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
        animation: slideUp 0.3s ease;
    }

    .admin-auth-close {
        position: absolute;
        top: var(--spacing-md, 15px);
        right: var(--spacing-md, 15px);
        background: none;
        border: none;
        color: var(--text-light, #e0e0e0);
        font-size: 1.5rem;
        cursor: pointer;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: all var(--transition-base, 0.2s ease);
    }

    .admin-auth-close:hover {
        background: rgba(255, 255, 255, 0.1);
        color: var(--text-white, #fff);
        transform: rotate(90deg);
    }

    .admin-auth-header {
        text-align: center;
        margin-bottom: var(--spacing-xl, 25px);
    }

    .admin-auth-icon {
        width: 70px;
        height: 70px;
        margin: 0 auto var(--spacing-md, 15px);
        background: var(--color-secondary, #ff9800);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        color: white;
        box-shadow: 0 4px 20px rgba(255, 152, 0, 0.3);
    }

    .admin-auth-header h3 {
        color: var(--color-accent, #1ab188);
        font-size: 1.5rem;
        margin-bottom: var(--spacing-sm, 8px);
    }

    .admin-auth-header p {
        color: var(--text-light, #e0e0e0);
        font-size: var(--font-size-sm, 14px);
        margin: 0;
    }

    .admin-auth-error {
        background: rgba(244, 67, 54, 0.1);
        border-left: 4px solid var(--color-error, #f44336);
        color: var(--color-error, #f44336);
        padding: var(--spacing-md, 12px);
        border-radius: var(--radius-md, 8px);
        margin-bottom: var(--spacing-md, 15px);
        font-size: var(--font-size-sm, 14px);
        animation: shake 0.3s ease;
    }

    .admin-auth-actions {
        display: flex;
        gap: var(--spacing-md, 12px);
        margin-top: var(--spacing-lg, 20px);
    }

    .admin-auth-actions button {
        flex: 1;
        padding: var(--spacing-md, 12px);
        border-radius: var(--radius-lg, 10px);
        font-size: var(--font-size-base, 16px);
        font-weight: 600;
        cursor: pointer;
        transition: all var(--transition-base, 0.2s ease);
        border: none;
    }

    .btn-cancel {
        background: var(--bg-darker, #2c3e48);
        color: var(--text-light, #e0e0e0);
    }

    .btn-cancel:hover {
        background: var(--bg-darkest, #344955);
    }

    .btn-submit {
        background: var(--color-accent, #1ab188);
        color: var(--text-white, #fff);
    }

    .btn-submit:hover {
        background: var(--color-accent-hover, #1ec79a);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(26, 177, 136, 0.3);
    }

    .btn-submit:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
        }

        to {
            opacity: 1;
        }
    }

    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes shake {

        0%,
        100% {
            transform: translateX(0);
        }

        25% {
            transform: translateX(-10px);
        }

        75% {
            transform: translateX(10px);
        }
    }

    @media (max-width: 576px) {
        .admin-auth-content {
            padding: var(--spacing-xl, 25px) var(--spacing-lg, 20px);
        }

        .admin-auth-icon {
            width: 60px;
            height: 60px;
            font-size: 1.75rem;
        }

        .admin-auth-actions {
            flex-direction: column;
        }
    }
</style>

<script>
    let adminAuthCallback = null;

    /**
     * Show admin authentication modal
     * @param {string} action - The action to perform (e.g., 'approve_customer')
     * @param {object} data - Data related to the action
     * @param {string} message - Custom message to display
     * @param {function} callback - Function to call on successful authentication
     */
    function showAdminAuthModal(action, data = {}, message = null, callback = null) {
        const modal = document.getElementById('adminAuthModal');
        const messageEl = document.getElementById('adminAuthMessage');
        const actionInput = document.getElementById('adminAuthAction');
        const dataInput = document.getElementById('adminAuthData');
        const errorEl = document.getElementById('adminAuthError');

        // Set custom message if provided
        if (message) {
            messageEl.textContent = message;
        } else {
            messageEl.textContent = 'Please enter admin credentials to continue';
        }

        // Set action and data
        actionInput.value = action;
        dataInput.value = JSON.stringify(data);

        // Store callback
        adminAuthCallback = callback;

        // Clear form and errors
        document.getElementById('adminAuthForm').reset();
        errorEl.style.display = 'none';

        // Show modal
        modal.style.display = 'flex';

        // Focus on username field
        setTimeout(() => {
            document.getElementById('adminUsername').focus();
        }, 100);
    }

    /**
     * Close admin authentication modal
     */
    function closeAdminAuthModal() {
        const modal = document.getElementById('adminAuthModal');
        modal.style.display = 'none';
        adminAuthCallback = null;
    }

    /**
     * Toggle admin password visibility
     */
    function toggleAdminPassword() {
        const passwordInput = document.getElementById('adminPassword');
        const icon = event.target;

        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);

        icon.classList.toggle('fa-eye');
        icon.classList.toggle('fa-eye-slash');
    }

    /**
     * Handle admin authentication form submission
     */
    async function handleAdminAuth(event) {
        event.preventDefault();

        const form = event.target;
        const submitBtn = document.getElementById('adminAuthSubmit');
        const errorEl = document.getElementById('adminAuthError');

        // Disable submit button
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Authenticating...';

        // Hide previous errors
        errorEl.style.display = 'none';

        try {
            const formData = new FormData(form);

            const response = await fetch('verify-admin.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                // If callback exists, execute it
                if (adminAuthCallback && typeof adminAuthCallback === 'function') {
                    await adminAuthCallback(result);
                }

                // Show success and close modal
                closeAdminAuthModal();

                // Optionally reload page or show success message
                if (result.reload) {
                    location.reload();
                }
            } else {
                // Show error
                errorEl.textContent = result.message || 'Authentication failed. Please try again.';
                errorEl.style.display = 'block';
            }
        } catch (error) {
            errorEl.textContent = 'An error occurred. Please try again.';
            errorEl.style.display = 'block';
            console.error('Admin auth error:', error);
        } finally {
            // Re-enable submit button
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-check"></i> Authenticate';
        }

        return false;
    }

    // Close modal on overlay click
    document.addEventListener('click', function(event) {
        const modal = document.getElementById('adminAuthModal');
        if (modal && event.target.classList.contains('admin-auth-overlay')) {
            closeAdminAuthModal();
        }
    });

    // Close modal on Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            const modal = document.getElementById('adminAuthModal');
            if (modal && modal.style.display === 'flex') {
                closeAdminAuthModal();
            }
        }
    });
</script>