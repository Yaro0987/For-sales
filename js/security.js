
// main.js - Anti-screenshot, copy, and recording protection (No Redirects)

(function() {
    'use strict';
    
    // 1. Disable right-click context menu
    document.addEventListener('contextmenu', function(e) {
        e.preventDefault();
        return false;
    });
    
    // 2. Disable keyboard shortcuts for developer tools and copying
    document.addEventListener('keydown', function(e) {
        // Disable F12, Ctrl+Shift+I, Ctrl+Shift+J, Ctrl+U, Ctrl+C, Ctrl+V, Ctrl+X, Ctrl+S, Ctrl+P
        const forbiddenKeys = [
            e.key === 'F12',
            (e.ctrlKey && e.shiftKey && (e.key === 'I' || e.key === 'J' || e.key === 'C')),
            (e.ctrlKey && e.key === 'U'),
            (e.ctrlKey && (e.key === 'c' || e.key === 'C')),
            (e.ctrlKey && (e.key === 'v' || e.key === 'V')),
            (e.ctrlKey && (e.key === 'x' || e.key === 'X')),
            (e.ctrlKey && (e.key === 's' || e.key === 'S')),
            (e.ctrlKey && (e.key === 'p' || e.key === 'P')),
            (e.metaKey && (e.key === 'c' || e.key === 'C')), // For Mac
            (e.metaKey && (e.key === 'v' || e.key === 'V')), // For Mac
            (e.metaKey && (e.key === 'x' || e.key === 'X')), // For Mac
            (e.metaKey && (e.key === 's' || e.key === 'S')), // For Mac
            (e.metaKey && e.altKey && e.key === 'I') // For Mac
        ];
        
        if (forbiddenKeys.some(Boolean)) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        }
    });
    
    // 3. Prevent selection and copying via CSS and JavaScript
    document.body.style.userSelect = 'none';
    document.body.style.webkitUserSelect = 'none';
    document.body.style.msUserSelect = 'none';
    document.body.style.mozUserSelect = 'none';
    
    // 4. Disable copy, cut, and paste events
    document.addEventListener('copy', function(e) {
        e.preventDefault();
        e.clipboardData.setData('text/plain', '');
        return false;
    });
    
    document.addEventListener('cut', function(e) {
        e.preventDefault();
        return false;
    });
    
    document.addEventListener('paste', function(e) {
        e.preventDefault();
        return false;
    });
    
    // 5. Disable drag and drop
    document.addEventListener('dragstart', function(e) {
        e.preventDefault();
        return false;
    });
    
    // 6. Detect and block developer tools (without redirect)
    (function detectDevTools() {
        // Method 1: Check console.log
        const element = new Image();
        Object.defineProperty(element, 'id', {
            get: function() {
                // Just block without redirect
                throw new Error('Dev tools detected');
            }
        });
        
        console.log('%c', element);
        
        // Method 2: Check dev tools opening via debugger
        setInterval(function() {
            const startTime = performance.now();
            debugger;
            const endTime = performance.now();
            
            if (endTime - startTime > 100) {
                // Dev tools detected - you could add a warning or just block
                document.body.innerHTML = '<h1>Developer tools detected. Please close them to continue.</h1>';
            }
        }, 1000);
    })();
    
    // 7. Disable print
    window.addEventListener('beforeprint', function(e) {
        e.preventDefault();
        return false;
    });
    
    // 8. Override console methods (keep them but prevent logging)
    const consoleMethods = ['log', 'info', 'warn', 'error', 'debug'];
    consoleMethods.forEach(method => {
        console[method] = function() {
            // Silently ignore console calls
            return;
        };
    });
    
    // 9. Prevent iframe embedding
    if (window.self !== window.top) {
        document.body.innerHTML = '<h1>This page cannot be embedded in iframes</h1>';
    }
    
    // 10. Disable image dragging and saving
    document.querySelectorAll('img').forEach(img => {
        img.draggable = false;
        img.addEventListener('contextmenu', e => e.preventDefault());
    });
    
    // Also handle dynamically added images
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.type === 'childList') {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeName === 'IMG') {
                        node.draggable = false;
                        node.addEventListener('contextmenu', e => e.preventDefault());
                    }
                });
            }
        });
    });
    
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
    
    // 11. Add CSS protection
    const style = document.createElement('style');
    style.textContent = `
        * {
            -webkit-touch-callout: none;
            -webkit-user-select: none;
            -khtml-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
        }
        img, video, canvas {
            pointer-events: none;
        }
    `;
    document.head.appendChild(style);
    
    // 12. Disable text selection
    document.addEventListener('selectstart', function(e) {
        e.preventDefault();
        return false;
    });
    
    // 13. Disable media keys for screen recording (where possible)
    document.addEventListener('keydown', function(e) {
        // Windows + G (Game bar), Windows + Alt + R (recording)
        if ((e.metaKey && e.key === 'g') || 
            (e.metaKey && e.altKey && e.key === 'r')) {
            e.preventDefault();
            return false;
        }
    });
    
    console.log('Protection script loaded successfully (no redirects)');
})();