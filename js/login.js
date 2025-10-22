function togglePassword() {
    const passwordInput = document.getElementById('password');
    const toggleIcon = document.querySelector('.password-toggle i');

    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.className = 'fas fa-eye-slash';
    } else {
        passwordInput.type = 'password';
        toggleIcon.className = 'fas fa-eye';
    }
}

// Add focus effects
document.addEventListener('DOMContentLoaded', function() {
    const inputs = document.querySelectorAll('input');
    inputs.forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.classList.add('focused');
        });

        input.addEventListener('blur', function() {
            if (!this.value) {
                this.parentElement.classList.remove('focused');
            }
        });
    });

    setTimeout(() => {
        const messages = document.querySelectorAll('.success-message, .error-message');
        messages.forEach(msg => {
            msg.style.animation = 'slideDown 0.3s ease reverse';
            setTimeout(() => msg.remove(), 300);
        });
    }, 5000);

    // Auto-focus username field
    document.getElementById('username').focus();
});