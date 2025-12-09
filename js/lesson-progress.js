/**
 * Lesson Progress Tracking via API
 * Handles marking lessons as complete using AJAX
 */

document.addEventListener('DOMContentLoaded', function() {
    const completeForm = document.querySelector('form[method="POST"]');
    const completeButton = document.querySelector('button[name="mark_complete"]');
    
    if (!completeForm || !completeButton) {
        return; // No completion form on this page
    }
    
    // Get lesson ID from URL or data attribute
    const lessonId = completeButton.getAttribute('data-lesson-id') || 
                     document.querySelector('[data-lesson-id]')?.getAttribute('data-lesson-id');
    
    if (!lessonId) {
        return; // Can't proceed without lesson ID
    }
    
    // Override form submission
    completeForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Disable button
        completeButton.disabled = true;
        completeButton.textContent = 'Marking...';
        
        // Submit via API
        fetch('api/progress.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                lesson_id: parseInt(lessonId),
                action: 'complete'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success message
                showSuccessMessage('Lesson marked as complete! Great job!');
                
                // Update button state
                completeButton.disabled = true;
                completeButton.textContent = '✓ Completed';
                completeButton.style.background = '#10b981';
                
                // Update progress stats if available
                if (data.stats) {
                    updateProgressStats(data.stats);
                }
                
                // Reload page after 1 second to show updated state
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                showErrorMessage(data.message || 'Failed to mark lesson as complete. Please try again.');
                completeButton.disabled = false;
                completeButton.textContent = 'Mark as Complete ✓';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showErrorMessage('Network error. Please check your connection and try again.');
            completeButton.disabled = false;
            completeButton.textContent = 'Mark as Complete ✓';
        });
    });
    
    function showSuccessMessage(message) {
        // Remove existing messages
        const existing = document.querySelector('.progress-message');
        if (existing) {
            existing.remove();
        }
        
        // Create success message
        const messageDiv = document.createElement('div');
        messageDiv.className = 'progress-message';
        messageDiv.style.cssText = 'background: #10b981; color: white; padding: 1rem; border-radius: 0.5rem; margin: 1rem 0; text-align: center;';
        messageDiv.textContent = message;
        
        // Insert before form
        completeForm.parentNode.insertBefore(messageDiv, completeForm);
        
        // Remove after 3 seconds
        setTimeout(() => {
            messageDiv.remove();
        }, 3000);
    }
    
    function showErrorMessage(message) {
        // Remove existing messages
        const existing = document.querySelector('.progress-message');
        if (existing) {
            existing.remove();
        }
        
        // Create error message
        const messageDiv = document.createElement('div');
        messageDiv.className = 'progress-message';
        messageDiv.style.cssText = 'background: #ef4444; color: white; padding: 1rem; border-radius: 0.5rem; margin: 1rem 0; text-align: center;';
        messageDiv.textContent = message;
        
        // Insert before form
        completeForm.parentNode.insertBefore(messageDiv, completeForm);
        
        // Remove after 5 seconds
        setTimeout(() => {
            messageDiv.remove();
        }, 5000);
    }
    
    function updateProgressStats(stats) {
        // Update any progress indicators on the page
        const progressElements = document.querySelectorAll('[data-progress]');
        progressElements.forEach(el => {
            if (stats.completed_lessons && stats.total_lessons) {
                const percentage = Math.round((stats.completed_lessons / stats.total_lessons) * 100);
                el.textContent = `${stats.completed_lessons} / ${stats.total_lessons} lessons (${percentage}%)`;
            }
        });
    }
});

