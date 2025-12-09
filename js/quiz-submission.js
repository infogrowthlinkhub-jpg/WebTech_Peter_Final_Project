/**
 * Quiz Score Submission
 * Handles submitting quiz scores to the API for prerequisite checking
 */

// Global function to submit quiz score (can be called from embedded quiz JavaScript)
function submitQuizScore(lessonId, score, totalQuestions, percentage) {
    if (!lessonId || totalQuestions === 0) {
        console.error('Invalid quiz data for submission');
        return Promise.reject('Invalid quiz data');
    }
    
    return fetch('api/quiz-score.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            lesson_id: parseInt(lessonId),
            score: parseInt(score),
            total_questions: parseInt(totalQuestions),
            percentage: parseFloat(percentage)
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Quiz score submitted successfully:', data);
            return data;
        } else {
            console.error('Failed to submit quiz score:', data.message);
            throw new Error(data.message || 'Failed to submit quiz score');
        }
    })
    .catch(error => {
        console.error('Error submitting quiz score:', error);
        throw error;
    });
}

// Auto-detect quiz submissions and submit scores
document.addEventListener('DOMContentLoaded', function() {
    // Check if we have a lesson ID available
    if (typeof CURRENT_LESSON_ID === 'undefined') {
        console.warn('CURRENT_LESSON_ID not defined, quiz score submission may not work');
        return;
    }
    
    const lessonId = CURRENT_LESSON_ID;
    const quizResultsDiv = document.getElementById('quiz-results');
    
    if (!quizResultsDiv) {
        // Quiz form exists but results haven't been shown yet
        // Watch for results to appear
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
                    const resultsDiv = document.getElementById('quiz-results');
                    if (resultsDiv && resultsDiv.style.display !== 'none') {
                        // Results are now visible, extract and submit score
                        extractAndSubmitScore(lessonId, resultsDiv);
                        observer.disconnect();
                    }
                }
            });
        });
        
        // Observe the quiz-results div for changes
        const quizForm = document.getElementById('quiz-form');
        if (quizForm) {
            // Wait a bit for the quiz-results div to exist
            setTimeout(function() {
                const resultsDiv = document.getElementById('quiz-results');
                if (resultsDiv) {
                    observer.observe(resultsDiv, {
                        attributes: true,
                        attributeFilter: ['style']
                    });
                }
            }, 500);
        }
    }
});

// Function to extract score from results and submit
function extractAndSubmitScore(lessonId, resultsDiv) {
    if (!resultsDiv || !lessonId) return;
    
    // Check if we've already submitted (to avoid duplicate submissions)
    if (resultsDiv.hasAttribute('data-score-submitted')) {
        return;
    }
    
    // Extract score information from results HTML
    const resultsHTML = resultsDiv.innerHTML;
    const scoreMatch = resultsHTML.match(/Quiz Results:\s*(\d+)\/(\d+)\s*\((\d+(?:\.\d+)?)%\)/);
    
    if (scoreMatch) {
        const score = parseInt(scoreMatch[1]);
        const totalQuestions = parseInt(scoreMatch[2]);
        const percentage = parseFloat(scoreMatch[3]);
        
        // Mark as submitted to avoid duplicates
        resultsDiv.setAttribute('data-score-submitted', 'true');
        
        // Submit score to API
        submitQuizScore(lessonId, score, totalQuestions, percentage)
            .then(function(data) {
                // Show feedback message
                let feedbackMsg = document.getElementById('quiz-feedback-message');
                if (!feedbackMsg) {
                    feedbackMsg = document.createElement('div');
                    feedbackMsg.id = 'quiz-feedback-message';
                    feedbackMsg.style.cssText = 'padding: 15px; border-radius: 5px; margin-top: 15px; text-align: center; font-weight: 600;';
                    resultsDiv.parentNode.insertBefore(feedbackMsg, resultsDiv.nextSibling);
                }
                
                if (data.passed) {
                    feedbackMsg.style.background = '#10b981';
                    feedbackMsg.style.color = 'white';
                    feedbackMsg.innerHTML = '<strong>🎉 Quiz Passed!</strong> You scored ' + percentage.toFixed(0) + '%. The next lesson is now unlocked.';
                    
                    // Reload page after 2 seconds to update navigation
                    setTimeout(function() {
                        window.location.reload();
                    }, 2000);
                } else {
                    feedbackMsg.style.background = '#f59e0b';
                    feedbackMsg.style.color = 'white';
                    feedbackMsg.innerHTML = '<strong>⚠️ Quiz Not Passed</strong> You scored ' + percentage.toFixed(0) + '%. You need at least 60% to unlock the next lesson. Please review the lesson and try again.';
                }
            })
            .catch(function(error) {
                console.error('Failed to submit quiz score:', error);
            });
    }
}

// Enhanced wrapper - monitors quiz submissions and automatically submits scores
(function() {
    'use strict';
    
    function initQuizSubmission() {
        if (typeof CURRENT_LESSON_ID === 'undefined') {
            console.warn('CURRENT_LESSON_ID not available for quiz submission');
            return;
        }
        
        const lessonId = CURRENT_LESSON_ID;
        
        // Set up observer to watch for quiz results appearing
        function setupResultsObserver() {
            const resultsDiv = document.getElementById('quiz-results');
            
            if (resultsDiv) {
                // Check if results are already visible
                if (resultsDiv.style.display !== 'none' && !resultsDiv.hasAttribute('data-score-submitted')) {
                    extractAndSubmitScore(lessonId, resultsDiv);
                }
                
                // Watch for results to appear
                const observer = new MutationObserver(function(mutations) {
                    if (resultsDiv.style.display !== 'none' && !resultsDiv.hasAttribute('data-score-submitted')) {
                        extractAndSubmitScore(lessonId, resultsDiv);
                    }
                });
                
                observer.observe(resultsDiv, {
                    attributes: true,
                    attributeFilter: ['style'],
                    childList: true,
                    subtree: true
                });
            }
        }
        
        // Set up immediately and also after a delay to catch dynamically added content
        setupResultsObserver();
        
        // Also check after content loads (in case quiz is loaded dynamically)
        setTimeout(setupResultsObserver, 1000);
        setTimeout(setupResultsObserver, 2000);
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initQuizSubmission);
    } else {
        initQuizSubmission();
    }
    
    // Also initialize after a delay to catch late-loading content
    setTimeout(initQuizSubmission, 500);
})();

