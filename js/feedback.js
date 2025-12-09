/**
 * Feedback Form Validation and Modal Handling
 */

document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('feedbackModal');
    const openBtn = document.getElementById('openFeedbackModal');
    const closeBtn = document.getElementById('closeModal');
    const form = document.getElementById('feedbackForm');
    const submitBtn = document.getElementById('submitBtn');
    
    // Form fields
    const feedbackType = document.getElementById('feedback_type');
    const title = document.getElementById('title');
    const description = document.getElementById('description');
    const email = document.getElementById('email');
    
    // Error elements
    const feedbackTypeError = document.getElementById('feedback_type_error');
    const titleError = document.getElementById('title_error');
    const descriptionError = document.getElementById('description_error');
    const emailError = document.getElementById('email_error');
    
    // Open modal
    if (openBtn) {
        openBtn.addEventListener('click', function() {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        });
    }
    
    // Close modal
    function closeModal() {
        modal.classList.remove('active');
        document.body.style.overflow = '';
        // Reset form if no errors
        if (!form.querySelector('.error-message.show')) {
            form.reset();
            clearAllErrors();
        }
    }
    
    if (closeBtn) {
        closeBtn.addEventListener('click', closeModal);
    }
    
    // Close modal when clicking outside
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeModal();
            }
        });
    }
    
    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.classList.contains('active')) {
            closeModal();
        }
    });
    
    // Validation functions
    function validateFeedbackType() {
        const value = feedbackType.value;
        if (!value) {
            showError(feedbackType, feedbackTypeError, 'Please select a feedback type.');
            return false;
        }
        clearError(feedbackType, feedbackTypeError);
        return true;
    }
    
    function validateTitle() {
        const value = title.value.trim();
        if (!value) {
            showError(title, titleError, 'Title is required.');
            return false;
        }
        if (value.length < 3) {
            showError(title, titleError, 'Title must be at least 3 characters long.');
            return false;
        }
        if (value.length > 200) {
            showError(title, titleError, 'Title must not exceed 200 characters.');
            return false;
        }
        clearError(title, titleError);
        return true;
    }
    
    function validateDescription() {
        const value = description.value.trim();
        if (!value) {
            showError(description, descriptionError, 'Description is required.');
            return false;
        }
        if (value.length < 10) {
            showError(description, descriptionError, 'Description must be at least 10 characters long.');
            return false;
        }
        clearError(description, descriptionError);
        return true;
    }
    
    function validateEmail() {
        const value = email.value.trim();
        if (value && !isValidEmail(value)) {
            showError(email, emailError, 'Please enter a valid email address.');
            return false;
        }
        clearError(email, emailError);
        return true;
    }
    
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    function showError(input, errorElement, message) {
        input.classList.add('error');
        errorElement.textContent = message;
        errorElement.classList.add('show');
    }
    
    function clearError(input, errorElement) {
        input.classList.remove('error');
        errorElement.textContent = '';
        errorElement.classList.remove('show');
    }
    
    function clearAllErrors() {
        clearError(feedbackType, feedbackTypeError);
        clearError(title, titleError);
        clearError(description, descriptionError);
        clearError(email, emailError);
        feedbackType.classList.remove('error');
        title.classList.remove('error');
        description.classList.remove('error');
        email.classList.remove('error');
    }
    
    // Real-time validation
    if (feedbackType) {
        feedbackType.addEventListener('blur', validateFeedbackType);
        feedbackType.addEventListener('change', validateFeedbackType);
    }
    
    if (title) {
        title.addEventListener('blur', validateTitle);
        title.addEventListener('input', function() {
            if (this.value.trim().length >= 3 && this.value.trim().length <= 200) {
                clearError(title, titleError);
            }
        });
    }
    
    if (description) {
        description.addEventListener('blur', validateDescription);
        description.addEventListener('input', function() {
            if (this.value.trim().length >= 10) {
                clearError(description, descriptionError);
            }
        });
    }
    
    if (email) {
        email.addEventListener('blur', validateEmail);
        email.addEventListener('input', function() {
            if (!this.value.trim() || isValidEmail(this.value.trim())) {
                clearError(email, emailError);
            }
        });
    }
    
    // Form submission via API
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Validate all fields
            const isFeedbackTypeValid = validateFeedbackType();
            const isTitleValid = validateTitle();
            const isDescriptionValid = validateDescription();
            const isEmailValid = validateEmail();
            
            if (!isFeedbackTypeValid || !isTitleValid || !isDescriptionValid || !isEmailValid) {
                // Scroll to first error
                const firstError = form.querySelector('.error-message.show');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                return false;
            }
            
            // Disable submit button to prevent double submission
            submitBtn.disabled = true;
            submitBtn.textContent = 'Submitting...';
            
            // Prepare form data
            const formData = {
                feedback_type: feedbackType.value,
                title: title.value.trim(),
                description: description.value.trim(),
                email: email.value.trim()
            };
            
            // Submit via API
            fetch('api/feedback.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(formData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    showSuccessMessage(data.message);
                    // Reset form
                    form.reset();
                    clearAllErrors();
                    // Close modal after 1.5 seconds
                    setTimeout(() => {
                        closeModal();
                        // Reload page to show new feedback
                        window.location.reload();
                    }, 1500);
                } else {
                    // Show errors
                    if (data.errors) {
                        Object.keys(data.errors).forEach(field => {
                            const fieldMap = {
                                'feedback_type': { input: feedbackType, error: feedbackTypeError },
                                'title': { input: title, error: titleError },
                                'description': { input: description, error: descriptionError },
                                'email': { input: email, error: emailError }
                            };
                            
                            if (fieldMap[field]) {
                                showError(fieldMap[field].input, fieldMap[field].error, data.errors[field]);
                            }
                        });
                    } else {
                        showErrorMessage(data.message || 'An error occurred. Please try again.');
                    }
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Submit Feedback';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showErrorMessage('Network error. Please check your connection and try again.');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Submit Feedback';
            });
        });
    }
    
    function showSuccessMessage(message) {
        // Create or update success message element
        let successMsg = document.getElementById('feedbackSuccessMessage');
        if (!successMsg) {
            successMsg = document.createElement('div');
            successMsg.id = 'feedbackSuccessMessage';
            successMsg.style.cssText = 'background: #10b981; color: white; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; text-align: center;';
            form.insertBefore(successMsg, form.firstChild);
        }
        successMsg.textContent = message;
        successMsg.style.display = 'block';
        
        // Remove after 3 seconds
        setTimeout(() => {
            successMsg.style.display = 'none';
        }, 3000);
    }
    
    function showErrorMessage(message) {
        // Create or update error message element
        let errorMsg = document.getElementById('feedbackErrorMessage');
        if (!errorMsg) {
            errorMsg = document.createElement('div');
            errorMsg.id = 'feedbackErrorMessage';
            errorMsg.style.cssText = 'background: #ef4444; color: white; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; text-align: center;';
            form.insertBefore(errorMsg, form.firstChild);
        }
        errorMsg.textContent = message;
        errorMsg.style.display = 'block';
        
        // Remove after 5 seconds
        setTimeout(() => {
            errorMsg.style.display = 'none';
        }, 5000);
    }
    
    // Auto-close modal on success (if success message is shown)
    const successMessage = document.getElementById('successMessage');
    if (successMessage && successMessage.classList.contains('show')) {
        // Auto-open modal was closed, show success message
        setTimeout(function() {
            if (modal.classList.contains('active')) {
                closeModal();
            }
        }, 100);
    }
});

