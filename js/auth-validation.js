/**
 * NileTech Learning Website - Authentication Form Validation
 * Handles form validation for Signup and Login pages
 */

// ============================================
// Utility Functions
// ============================================

/**
 * Validate email format
 * @param {string} email - Email address to validate
 * @returns {boolean} - True if email is valid
 */
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

/**
 * Show error message
 * @param {HTMLElement} errorElement - Error message element
 * @param {string} message - Error message to display
 */
function showError(errorElement, message) {
    errorElement.textContent = message;
    errorElement.classList.add('show');
}

/**
 * Clear error message
 * @param {HTMLElement} errorElement - Error message element
 */
function clearError(errorElement) {
    errorElement.textContent = '';
    errorElement.classList.remove('show');
}

/**
 * Validate input field and show/hide errors
 * @param {HTMLInputElement} input - Input element to validate
 * @param {HTMLElement} errorElement - Error message element
 * @param {Function} validator - Validation function
 * @param {string} errorMessage - Error message to show if validation fails
 * @returns {boolean} - True if input is valid
 */
function validateField(input, errorElement, validator, errorMessage) {
    const value = input.value.trim();
    
    if (validator(value)) {
        clearError(errorElement);
        input.style.borderColor = '';
        return true;
    } else {
        showError(errorElement, errorMessage);
        input.style.borderColor = '#ef4444';
        return false;
    }
}

// ============================================
// Signup Form Validation
// ============================================
const signupForm = document.getElementById('signupForm');

if (signupForm) {
    // Note: All users are created with role = 'user' by default
    // Admin roles can only be assigned by the super admin (peter.admin@nitech.com)
    // This is enforced server-side in signup.php
    const fullNameInput = document.getElementById('fullName');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirmPassword');
    
    const fullNameError = document.getElementById('fullNameError');
    const emailError = document.getElementById('emailError');
    const passwordError = document.getElementById('passwordError');
    const confirmPasswordError = document.getElementById('confirmPasswordError');

    // Real-time validation for Full Name
    fullNameInput.addEventListener('blur', function() {
        validateField(
            fullNameInput,
            fullNameError,
            (value) => value.length >= 3,
            'Full name must be at least 3 characters long'
        );
    });

    fullNameInput.addEventListener('input', function() {
        if (this.value.trim().length >= 3) {
            clearError(fullNameError);
            this.style.borderColor = '';
        }
    });

    // Real-time validation for Email
    emailInput.addEventListener('blur', function() {
        validateField(
            emailInput,
            emailError,
            (value) => isValidEmail(value),
            'Please enter a valid email address'
        );
    });

    emailInput.addEventListener('input', function() {
        if (isValidEmail(this.value.trim())) {
            clearError(emailError);
            this.style.borderColor = '';
        }
    });

    // Real-time validation for Password
    passwordInput.addEventListener('blur', function() {
        validateField(
            passwordInput,
            passwordError,
            (value) => value.length >= 8 && /[A-Z]/.test(value) && /[a-z]/.test(value) && /[0-9]/.test(value),
            'Password must be at least 8 characters with uppercase, lowercase, and number'
        );
    });

    passwordInput.addEventListener('input', function() {
        if (this.value.length >= 8 && /[A-Z]/.test(this.value) && /[a-z]/.test(this.value) && /[0-9]/.test(this.value)) {
            clearError(passwordError);
            this.style.borderColor = '';
        }
        // Re-validate confirm password when password changes
        if (confirmPasswordInput && confirmPasswordInput.value) {
            if (confirmPasswordInput.value === this.value) {
                clearError(confirmPasswordError);
                confirmPasswordInput.style.borderColor = '';
            } else {
                showError(confirmPasswordError, 'Passwords do not match');
                confirmPasswordInput.style.borderColor = '#ef4444';
            }
        }
    });

    // Real-time validation for Confirm Password
    if (confirmPasswordInput) {
        confirmPasswordInput.addEventListener('blur', function() {
            if (this.value.trim() === '') {
                showError(confirmPasswordError, 'Please retype your password');
                this.style.borderColor = '#ef4444';
            } else if (this.value !== passwordInput.value) {
                showError(confirmPasswordError, 'Passwords do not match. Please try again.');
                this.style.borderColor = '#ef4444';
            } else {
                clearError(confirmPasswordError);
                this.style.borderColor = '';
            }
        });

        confirmPasswordInput.addEventListener('input', function() {
            if (this.value === passwordInput.value && this.value.length > 0) {
                clearError(confirmPasswordError);
                this.style.borderColor = '';
            } else if (this.value.length > 0) {
                showError(confirmPasswordError, 'Passwords do not match');
                this.style.borderColor = '#ef4444';
            }
        });
    }

    // Form submission validation
    signupForm.addEventListener('submit', function(e) {
        // Validate all fields
        const isFullNameValid = validateField(
            fullNameInput,
            fullNameError,
            (value) => value.length >= 3,
            'Full name must be at least 3 characters long'
        );

        const isEmailValid = validateField(
            emailInput,
            emailError,
            (value) => isValidEmail(value),
            'Please enter a valid email address'
        );

        const isPasswordValid = validateField(
            passwordInput,
            passwordError,
            (value) => value.length >= 8 && /[A-Z]/.test(value) && /[a-z]/.test(value) && /[0-9]/.test(value),
            'Password must be at least 8 characters with uppercase, lowercase, and number'
        );

        const isConfirmPasswordValid = confirmPasswordInput ? validateField(
            confirmPasswordInput,
            confirmPasswordError,
            (value) => value === passwordInput.value && value.length > 0,
            'Passwords do not match. Please try again.'
        ) : true;

        // If validation fails, prevent form submission
        if (!isFullNameValid || !isEmailValid || !isPasswordValid || !isConfirmPasswordValid) {
            e.preventDefault();
            // Scroll to first error
            const firstError = signupForm.querySelector('.error-message.show');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
        // If all validations pass, allow form to submit naturally to signup.php
        // signup.php will handle registration and redirect to login.php
    });
}

// ============================================
// Login Form Validation
// ============================================
const loginForm = document.getElementById('loginForm');

if (loginForm) {
    const loginEmailInput = document.getElementById('loginEmail');
    const loginPasswordInput = document.getElementById('loginPassword');
    
    const loginEmailError = document.getElementById('loginEmailError');
    const loginPasswordError = document.getElementById('loginPasswordError');

    // Real-time validation for Email
    loginEmailInput.addEventListener('blur', function() {
        validateField(
            loginEmailInput,
            loginEmailError,
            (value) => isValidEmail(value),
            'Please enter a valid email address'
        );
    });

    loginEmailInput.addEventListener('input', function() {
        if (isValidEmail(this.value.trim())) {
            clearError(loginEmailError);
            this.style.borderColor = '';
        }
    });

    // Real-time validation for Password
    loginPasswordInput.addEventListener('blur', function() {
        validateField(
            loginPasswordInput,
            loginPasswordError,
            (value) => value.length > 0,
            'Password is required'
        );
    });

    loginPasswordInput.addEventListener('input', function() {
        if (this.value.length > 0) {
            clearError(loginPasswordError);
            this.style.borderColor = '';
        }
    });

    // Form submission validation
    loginForm.addEventListener('submit', function(e) {
        // Validate all fields
        const isEmailValid = validateField(
            loginEmailInput,
            loginEmailError,
            (value) => isValidEmail(value),
            'Please enter a valid email address'
        );

        const isPasswordValid = validateField(
            loginPasswordInput,
            loginPasswordError,
            (value) => value.length > 0,
            'Password is required'
        );

        // If validation fails, prevent form submission
        if (!isEmailValid || !isPasswordValid) {
            e.preventDefault();
            // Scroll to first error
            const firstError = loginForm.querySelector('.error-message.show');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
        // If validation passes, allow form to submit naturally to login.php
        // login.php will handle authentication and redirect to index.php
    });
}


