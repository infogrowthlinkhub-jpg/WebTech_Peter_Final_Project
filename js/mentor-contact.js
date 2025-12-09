/**
 * Mentor Contact Form Handler
 */

document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('contactMentorModal');
    const contactButtons = document.querySelectorAll('.btn-contact-mentor');
    const closeBtn = document.getElementById('closeContactModal');
    const cancelBtn = document.getElementById('cancelContact');
    const form = document.getElementById('contactMentorForm');
    const submitBtn = document.getElementById('submitContact');
    
    // Form fields
    const mentorNameInput = document.getElementById('mentorName');
    const subjectInput = document.getElementById('contactSubject');
    const messageInput = document.getElementById('contactMessage');
    
    // Error elements
    const subjectError = document.getElementById('subjectError');
    const messageError = document.getElementById('messageError');
    
    // Open modal when contact button is clicked
    contactButtons.forEach(button => {
        button.addEventListener('click', function() {
            const mentorName = this.getAttribute('data-mentor-name');
            mentorNameInput.value = mentorName;
            
            // Update modal title with mentor name
            const modalTitle = modal.querySelector('.modal-header h2');
            if (modalTitle) {
                modalTitle.textContent = `Contact ${mentorName}`;
            }
            
            // Show modal
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
            
            // Focus on subject input
            setTimeout(() => {
                subjectInput.focus();
            }, 100);
        });
    });
    
    // Close modal
    function closeModal() {
        modal.classList.remove('active');
        document.body.style.overflow = '';
        form.reset();
        clearAllErrors();
    }
    
    if (closeBtn) {
        closeBtn.addEventListener('click', closeModal);
    }
    
    if (cancelBtn) {
        cancelBtn.addEventListener('click', closeModal);
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
    
    // Clear errors
    function clearAllErrors() {
        subjectError.textContent = '';
        subjectError.classList.remove('show');
        messageError.textContent = '';
        messageError.classList.remove('show');
        subjectInput.classList.remove('error');
        messageInput.classList.remove('error');
    }
    
    // Show error
    function showError(input, errorElement, message) {
        errorElement.textContent = message;
        errorElement.classList.add('show');
        input.classList.add('error');
    }
    
    // Show success message
    function showSuccessMessage(message) {
        // Create or get success message element
        let successMsg = document.getElementById('contactSuccessMessage');
        if (!successMsg) {
            successMsg = document.createElement('div');
            successMsg.id = 'contactSuccessMessage';
            successMsg.className = 'success-message';
            form.insertBefore(successMsg, form.firstChild);
        }
        
        successMsg.textContent = message;
        successMsg.classList.add('show');
        
        // Scroll to top to show message
        successMsg.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        
        // Hide after 5 seconds
        setTimeout(() => {
            successMsg.classList.remove('show');
        }, 5000);
    }
    
    // Form validation
    function validateForm() {
        clearAllErrors();
        let isValid = true;
        
        // Validate subject
        if (subjectInput.value.trim().length < 3) {
            showError(subjectInput, subjectError, 'Subject must be at least 3 characters long.');
            isValid = false;
        }
        
        // Validate message
        if (messageInput.value.trim().length < 10) {
            showError(messageInput, messageError, 'Message must be at least 10 characters long.');
            isValid = false;
        }
        
        return isValid;
    }
    
    // Handle form submission
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (!validateForm()) {
            return;
        }
        
        // Disable submit button
        submitBtn.disabled = true;
        submitBtn.textContent = 'Sending...';
        
        // Prepare form data
        const formData = {
            mentor_name: mentorNameInput.value.trim(),
            subject: subjectInput.value.trim(),
            message: messageInput.value.trim()
        };
        
        // Submit via API
        fetch('api/mentor-contact.php', {
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
                // Close modal after 2 seconds
                setTimeout(() => {
                    closeModal();
                }, 2000);
            } else {
                // Show errors
                if (data.errors) {
                    Object.keys(data.errors).forEach(field => {
                        if (field === 'subject') {
                            showError(subjectInput, subjectError, data.errors[field]);
                        } else if (field === 'message') {
                            showError(messageInput, messageError, data.errors[field]);
                        }
                    });
                } else {
                    alert(data.message || 'An error occurred. Please try again.');
                }
                submitBtn.disabled = false;
                submitBtn.textContent = 'Send Message';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while sending your message. Please try again.');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Send Message';
        });
    });
});

