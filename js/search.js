/**
 * Search Functionality for NileTech Learning Platform
 * Handles search for modules, lessons, and mentors
 */

// Search Modules
function initModuleSearch() {
    const searchInput = document.getElementById('module-search-input');
    const resultsContainer = document.getElementById('module-search-results');
    
    if (!searchInput || !resultsContainer) {
        return;
    }
    
    let searchTimeout;
    
    searchInput.addEventListener('input', function() {
        const query = this.value.trim();
        
        clearTimeout(searchTimeout);
        
        if (query.length < 2) {
            resultsContainer.innerHTML = '';
            resultsContainer.style.display = 'none';
            return;
        }
        
        searchTimeout = setTimeout(() => {
            searchModules(query, resultsContainer);
        }, 300);
    });
    
    // Hide results when clicking outside
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !resultsContainer.contains(e.target)) {
            resultsContainer.style.display = 'none';
        }
    });
}

function searchModules(query, resultsContainer) {
    fetch(`api/search/modules.php?q=${encodeURIComponent(query)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.results.length > 0) {
                displayModuleResults(data.results, resultsContainer);
            } else {
                resultsContainer.innerHTML = '<div class="search-no-results">No modules found</div>';
                resultsContainer.style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Search error:', error);
            resultsContainer.innerHTML = '<div class="search-error">Error searching modules</div>';
            resultsContainer.style.display = 'block';
        });
}

function displayModuleResults(modules, container) {
    let html = '<div class="search-results-list">';
    
    modules.forEach(module => {
        const isLoggedIn = container.dataset.loggedIn === 'true';
        const startLink = isLoggedIn 
            ? `module.php?module=${encodeURIComponent(module.slug)}`
            : 'login.php';
        const linkText = isLoggedIn ? 'Start Module' : 'Login to Start';
        
        html += `
            <div class="search-result-item">
                <div class="search-result-icon">${module.icon || '💻'}</div>
                <div class="search-result-content">
                    <h4>${escapeHtml(module.name)}</h4>
                    <p>${escapeHtml(module.description || '').substring(0, 100)}...</p>
                </div>
                <a href="${startLink}" class="search-result-action">${linkText}</a>
            </div>
        `;
    });
    
    html += '</div>';
    container.innerHTML = html;
    container.style.display = 'block';
}

// Search Lessons
function initLessonSearch(moduleId) {
    const searchInput = document.getElementById('lesson-search-input');
    const resultsContainer = document.getElementById('lesson-search-results');
    
    if (!searchInput || !resultsContainer || !moduleId) {
        return;
    }
    
    let searchTimeout;
    
    searchInput.addEventListener('input', function() {
        const query = this.value.trim();
        
        clearTimeout(searchTimeout);
        
        if (query.length < 2) {
            resultsContainer.innerHTML = '';
            resultsContainer.style.display = 'none';
            // Show all lessons again
            const allLessons = document.querySelectorAll('.lesson-card');
            allLessons.forEach(lesson => {
                lesson.style.display = '';
            });
            return;
        }
        
        searchTimeout = setTimeout(() => {
            searchLessons(query, moduleId, resultsContainer);
        }, 300);
    });
}

function searchLessons(query, moduleId, resultsContainer) {
    fetch(`api/search/lessons.php?q=${encodeURIComponent(query)}&module_id=${moduleId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayLessonResults(data.results, resultsContainer);
            } else {
                console.error('Search error:', data.message);
            }
        })
        .catch(error => {
            console.error('Search error:', error);
        });
}

function displayLessonResults(lessons, container) {
    // Hide all lessons first
    const allLessons = document.querySelectorAll('.lesson-card');
    allLessons.forEach(lesson => {
        lesson.style.display = 'none';
    });
    
    // Show matching lessons
    lessons.forEach(lesson => {
        const lessonElement = document.querySelector(`[data-lesson-id="${lesson.id}"]`);
        if (lessonElement) {
            lessonElement.style.display = '';
        }
    });
    
    // Show message if no results
    if (lessons.length === 0) {
        if (!container.querySelector('.search-no-results')) {
            const noResults = document.createElement('div');
            noResults.className = 'search-no-results';
            noResults.textContent = 'No lessons found';
            container.appendChild(noResults);
        }
    } else {
        const noResults = container.querySelector('.search-no-results');
        if (noResults) {
            noResults.remove();
        }
    }
}

// Search Mentors
function initMentorSearch() {
    const searchInput = document.getElementById('mentor-search-input');
    const resultsContainer = document.getElementById('mentor-search-results');
    
    if (!searchInput || !resultsContainer) {
        return;
    }
    
    let searchTimeout;
    
    searchInput.addEventListener('input', function() {
        const query = this.value.trim();
        
        clearTimeout(searchTimeout);
        
        if (query.length < 2) {
            resultsContainer.innerHTML = '';
            resultsContainer.style.display = 'none';
            // Show all mentors again
            const allMentors = document.querySelectorAll('.mentor-card');
            allMentors.forEach(mentor => {
                mentor.style.display = '';
            });
            return;
        }
        
        searchTimeout = setTimeout(() => {
            searchMentors(query, resultsContainer);
        }, 300);
    });
}

function searchMentors(query, resultsContainer) {
    fetch(`api/search/mentors.php?q=${encodeURIComponent(query)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayMentorResults(data.results, resultsContainer);
            } else {
                console.error('Search error:', data.message);
            }
        })
        .catch(error => {
            console.error('Search error:', error);
        });
}

function displayMentorResults(mentors, container) {
    // Hide all mentors first
    const allMentors = document.querySelectorAll('.mentor-card');
    allMentors.forEach(mentor => {
        mentor.style.display = 'none';
    });
    
    // Show matching mentors
    mentors.forEach(mentor => {
        const mentorElement = document.querySelector(`[data-mentor-name="${escapeHtml(mentor.name)}"]`);
        if (mentorElement) {
            mentorElement.style.display = '';
        }
    });
    
    // Show message if no results
    if (mentors.length === 0) {
        if (!container.querySelector('.search-no-results')) {
            const noResults = document.createElement('div');
            noResults.className = 'search-no-results';
            noResults.textContent = 'No mentors found';
            noResults.style.cssText = 'padding: 20px; text-align: center; color: #666;';
            container.appendChild(noResults);
        }
    } else {
        const noResults = container.querySelector('.search-no-results');
        if (noResults) {
            noResults.remove();
        }
    }
}

// Utility function to escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Initialize search when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    initModuleSearch();
    initMentorSearch();
});

