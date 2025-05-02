
// Create a debug div for tracing without console
function createDebugDiv() {
    if (!document.querySelector('#popup-debug-container')) {
        const debugDiv = document.createElement('div');
        debugDiv.id = 'popup-debug-container';
        debugDiv.style.cssText = 'position: fixed; bottom: 10px; right: 10px; background: rgba(0,0,0,0.7); color: white; padding: 10px; font-size: 12px; max-width: 300px; z-index: 999999; border-radius: 5px; display: none;';
        
        const debugTitle = document.createElement('div');
        debugTitle.textContent = 'Popup Debug';
        debugTitle.style.fontWeight = 'bold';
        debugTitle.style.marginBottom = '5px';
        debugTitle.style.borderBottom = '1px solid #555';
        debugDiv.appendChild(debugTitle);
        
        const debugContent = document.createElement('div');
        debugContent.id = 'debug-message';
        debugContent.style.maxHeight = '150px';
        debugContent.style.overflow = 'auto';
        debugDiv.appendChild(debugContent);
        
        const toggleButton = document.createElement('button');
        toggleButton.textContent = 'Toggle Debug';
        toggleButton.style.cssText = 'position: fixed; bottom: 10px; right: 10px; background: #333; color: white; border: none; padding: 5px; border-radius: 3px; z-index: 1000000; font-size: 11px;';
        toggleButton.onclick = function() {
            debugDiv.style.display = debugDiv.style.display === 'none' ? 'block' : 'none';
        };
        
        document.body.appendChild(debugDiv);
        document.body.appendChild(toggleButton);
    }
    return document.querySelector('#debug-message');
}

// Log to both console and debug div
function debugLog(message) {
    console.log(message);
    const debugEl = createDebugDiv();
    const timestamp = new Date().toLocaleTimeString();
    debugEl.innerHTML += `<div>[${timestamp}] ${message}</div>`;
    debugEl.scrollTop = debugEl.scrollHeight;
}

// Function to check if admin-ajax.php is accessible
function checkAjaxEndpoint() {
    debugLog('Testing admin-ajax.php accessibility...');
    fetch(operations_notifications_ajax.ajaxurl + '?action=ping', {
        method: 'GET',
        headers: { 'Cache-Control': 'no-cache' }
    })
    .then(response => {
        debugLog(`AJAX endpoint status: ${response.status} ${response.statusText}`);
    })
    .catch(error => {
        debugLog(`AJAX endpoint error: ${error.message}`);
    });
}

// Initialize debugging
document.addEventListener('DOMContentLoaded', function() {
    createDebugDiv();
    debugLog('Popup Debug Initialized');
    debugLog('AJAX URL: ' + operations_notifications_ajax.ajaxurl);
    
    setTimeout(checkAjaxEndpoint, 1000);
});