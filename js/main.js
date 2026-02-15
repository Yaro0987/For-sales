// js/main.js
document.addEventListener('DOMContentLoaded', function() {
    initializeNavigation();
    initializeMobileMenu();
    initializeSmoothScroll();
    initializeCommentSystem();
    initializeReactions();
    setCurrentYear();
});

// Navigation and Header
function initializeNavigation() {
    const mainHeader = document.getElementById('mainHeader');
    const navLinks = document.querySelectorAll('.nav-link');
    
    // Scroll effect
    window.addEventListener('scroll', () => {
        if (window.scrollY > 50) {
            mainHeader?.classList.add('scrolled');
        } else {
            mainHeader?.classList.remove('scrolled');
        }
        
        // Active section highlighting
        if (navLinks.length) {
            const sections = document.querySelectorAll('.section[id]');
            let current = '';
            
            sections.forEach(section => {
                const sectionTop = section.offsetTop - 150;
                const sectionHeight = section.clientHeight;
                
                if (window.scrollY >= sectionTop && window.scrollY < sectionTop + sectionHeight) {
                    current = section.getAttribute('id');
                }
            });
            
            navLinks.forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href') === `#${current}`) {
                    link.classList.add('active');
                }
            });
        }
    });
}

// Mobile Menu - Slide from Left
function initializeMobileMenu() {
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const navLinks = document.getElementById('navLinks');
    const menuOverlay = document.getElementById('menuOverlay');
    
    if (mobileMenuToggle && navLinks) {
        mobileMenuToggle.addEventListener('click', () => {
            navLinks.classList.toggle('active');
            document.body.style.overflow = navLinks.classList.contains('active') ? 'hidden' : '';
            
            // Update menu icon
            const icon = mobileMenuToggle.querySelector('.material-symbols-outlined');
            if (icon) {
                icon.textContent = navLinks.classList.contains('active') ? 'close' : 'menu';
            }
            
            // Show/hide overlay
            if (menuOverlay) {
                menuOverlay.classList.toggle('active');
            }
        });
        
        // Close menu when clicking a link
        navLinks.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                navLinks.classList.remove('active');
                document.body.style.overflow = '';
                if (menuOverlay) {
                    menuOverlay.classList.remove('active');
                }
                const icon = mobileMenuToggle.querySelector('.material-symbols-outlined');
                if (icon) {
                    icon.textContent = 'menu';
                }
            });
        });
        
        // Close menu when clicking overlay
        if (menuOverlay) {
            menuOverlay.addEventListener('click', () => {
                navLinks.classList.remove('active');
                document.body.style.overflow = '';
                menuOverlay.classList.remove('active');
                const icon = mobileMenuToggle.querySelector('.material-symbols-outlined');
                if (icon) {
                    icon.textContent = 'menu';
                }
            });
        }
    }
}

// Smooth Scrolling
function initializeSmoothScroll() {
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href === '#') return;
            
            e.preventDefault();
            const targetElement = document.querySelector(href);
            
            if (targetElement) {
                const headerHeight = document.querySelector('.executive-header')?.offsetHeight || 0;
                const targetPosition = targetElement.offsetTop - headerHeight;
                
                window.scrollTo({
                    top: targetPosition,
                    behavior: 'smooth'
                });
            }
        });
    });
}

// Comment System with Cookies
function initializeCommentSystem() {
    const commentForms = document.querySelectorAll('.comment-form');
    
    commentForms.forEach(form => {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(form);
            const commentData = {
                projectId: form.dataset.projectId,
                name: formData.get('name'),
                email: formData.get('email'),
                comment: formData.get('comment'),
                rating: formData.get('rating'),
                date: new Date().toISOString().split('T')[0],
                ip: await getUserIP(),
                likes: 0,
                dislikes: 0,
                hearts: 0,
                approved: false
            };
            
            // Save comment to localStorage (temporary)
            const comments = JSON.parse(localStorage.getItem('comments') || '[]');
            comments.push(commentData);
            localStorage.setItem('comments', JSON.stringify(comments));
            
            // Show success message
            showNotification('Comment submitted for approval', 'success');
            form.reset();
            
            // Update UI
            displayComments(commentData.projectId);
        });
    });
}

// Get User IP via cookie
function getUserIP() {
    // Simple IP tracking via cookie
    let ip = getCookie('user_ip');
    if (!ip) {
        ip = 'visitor_' + Math.random().toString(36).substr(2, 9);
        setCookie('user_ip', ip, 365);
    }
    return ip;
}

// Cookie utilities
function setCookie(name, value, days) {
    const date = new Date();
    date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
    document.cookie = `${name}=${value};expires=${date.toUTCString()};path=/`;
}

function getCookie(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop().split(';').shift();
    return null;
}

// Reactions System (Like, Heart, Dislike)
function initializeReactions() {
    document.querySelectorAll('.reaction-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const projectId = this.dataset.projectId;
            const reactionType = this.dataset.reaction;
            const userIP = getUserIP();
            
            // Check if user already reacted
            const reactions = JSON.parse(localStorage.getItem(`reactions_${projectId}`) || '{}');
            const userReaction = reactions[userIP];
            
            if (userReaction === reactionType) {
                // Remove reaction
                delete reactions[userIP];
                updateReactionCount(projectId, reactionType, -1);
                this.classList.remove('active');
            } else {
                // Remove previous reaction if exists
                if (userReaction) {
                    updateReactionCount(projectId, userReaction, -1);
                    document.querySelector(`[data-project-id="${projectId}"][data-reaction="${userReaction}"]`)
                        ?.classList.remove('active');
                }
                
                // Add new reaction
                reactions[userIP] = reactionType;
                updateReactionCount(projectId, reactionType, 1);
                this.classList.add('active');
            }
            
            localStorage.setItem(`reactions_${projectId}`, JSON.stringify(reactions));
        });
    });
}

function updateReactionCount(projectId, reactionType, change) {
    const counter = document.querySelector(`[data-project-id="${projectId}"][data-reaction="${reactionType}"] .count`);
    if (counter) {
        const current = parseInt(counter.textContent) || 0;
        counter.textContent = current + change;
    }
}

function displayComments(projectId) {
    const commentsContainer = document.querySelector(`.comments-container[data-project-id="${projectId}"]`);
    if (!commentsContainer) return;
    
    const allComments = JSON.parse(localStorage.getItem('comments') || '[]');
    const projectComments = allComments.filter(c => c.projectId === projectId && c.approved);
    
    commentsContainer.innerHTML = projectComments.map(comment => `
        <div class="comment-card">
            <div class="comment-header">
                <strong>${comment.name}</strong>
                <span class="comment-date">${comment.date}</span>
            </div>
            ${comment.rating ? `<div class="comment-rating">${'⭐'.repeat(comment.rating)}</div>` : ''}
            <p class="comment-text">${comment.comment}</p>
            <div class="comment-reactions">
                <button class="reaction-btn" data-project-id="${projectId}" data-reaction="likes">
                    <span class="material-symbols-outlined">thumb_up</span>
                    <span class="count">${comment.likes || 0}</span>
                </button>
                <button class="reaction-btn" data-project-id="${projectId}" data-reaction="hearts">
                    <span class="material-symbols-outlined">favorite</span>
                    <span class="count">${comment.hearts || 0}</span>
                </button>
                <button class="reaction-btn" data-project-id="${projectId}" data-reaction="dislikes">
                    <span class="material-symbols-outlined">thumb_down</span>
                    <span class="count">${comment.dislikes || 0}</span>
                </button>
            </div>
        </div>
    `).join('');
}

// Notification System
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <span class="material-symbols-outlined">${type === 'success' ? 'check_circle' : 'info'}</span>
        <span>${message}</span>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.classList.add('show');
    }, 100);
    
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 3000);
}

// Set current year in footer
function setCurrentYear() {
    const yearElement = document.getElementById('currentYear');
    if (yearElement) {
        yearElement.textContent = new Date().getFullYear();
    }
}

// Image lazy loading
document.addEventListener('DOMContentLoaded', function() {
    const images = document.querySelectorAll('img[data-src]');
    
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.classList.add('loaded');
                observer.unobserve(img);
            }
        });
    });
    
    images.forEach(img => imageObserver.observe(img));
});

// Export for use in other files
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        initializeNavigation,
        initializeMobileMenu,
        initializeSmoothScroll,
        initializeCommentSystem,
        initializeReactions
    };
}

