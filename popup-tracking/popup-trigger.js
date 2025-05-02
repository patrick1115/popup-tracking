function runPopupLogic() {
    setTimeout(function () {
        const wrapper = document.querySelector('.has-category-operations-notifications');
        if (!wrapper) {
            console.log('No wrapper found after timeout.');
            return;
        }

        const latest = wrapper.getAttribute('data-latest-post');
        console.log('Latest Post Date:', latest);

        const today = new Date();
        const postDate = new Date(latest);
        const timeDiff = today - postDate;
        const daysDiff = timeDiff / (1000 * 60 * 60 * 24);

        const storageKey = 'popupLastSeen';
        const lastSeenDate = localStorage.getItem(storageKey);

        console.log('Days Difference:', daysDiff);
        console.log('Last Seen:', lastSeenDate);

        if (
            daysDiff <= 5 &&
            latest !== lastSeenDate &&
            typeof elementorProFrontend !== 'undefined' &&
            elementorProFrontend.modules &&
            elementorProFrontend.modules.popup
        ) {
            console.log('Triggering popup!');
            elementorProFrontend.modules.popup.showPopup({ id: 12961 });

            // Handle close button clicks
            document.addEventListener('click', function (e) {
                const closeBtn = e.target.closest('.eicon-close, .dialog-close, .elementor-popup-modal .elementor-button');
                if (closeBtn) {
                    localStorage.setItem(storageKey, latest);
                    console.log('Popup dismissed, stored date:', latest);
                    
                    // Record dismissal
                    sendDismissal(latest);
                }
            });

            // Setup observer to attach click handler to the dismiss button
            const popupObserver = new MutationObserver(function () {
                const dismissButton = document.querySelector('.elementor-popup-modal .elementor-button');
                if (dismissButton) {
                    dismissButton.addEventListener('click', function () {
                        localStorage.setItem(storageKey, latest);
                        sendDismissal(latest);
                        popupObserver.disconnect();
                    });
                }
            });

            popupObserver.observe(document.body, { childList: true, subtree: true });
        } else {
            console.log('Popup not shown: either already seen or too old.');
        }
    }, 1500);
}

function sendDismissal(postDate, attempt = 1) {
    localStorage.setItem('popupLastSeen', postDate);
    
    // Debug message that doesn't rely on console
    if (document.querySelector('#debug-message')) {
        document.querySelector('#debug-message').textContent = 'Sending dismissal request...';
    }

    // Add a unique parameter to prevent caching
    const cacheBuster = new Date().getTime();
    
    // Create a Promise that will timeout after 8 seconds
    const timeoutPromise = new Promise((_, reject) => {
        setTimeout(() => reject(new Error('Request timeout')), 8000);
    });
    
    // Create the fetch request
    const fetchPromise = fetch(operations_notifications_ajax.ajaxurl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'Cache-Control': 'no-cache',
        },
        body: 'action=record_popup_dismissal&post_date=' + encodeURIComponent(postDate) + '&cache_buster=' + cacheBuster,
        credentials: 'same-origin' // Include cookies for authentication
    });
    
    // Race the fetch against the timeout
    Promise.race([fetchPromise, timeoutPromise])
        .then((response) => {
            if (!response.ok) throw new Error('Network response status: ' + response.status);
            return response.json();
        })
        .then((data) => {
            if (document.querySelector('#debug-message')) {
                document.querySelector('#debug-message').textContent = 'Dismissal recorded successfully';
            }
            console.log('Dismissal recorded:', data);
        })
        .catch((error) => {
            if (document.querySelector('#debug-message')) {
                document.querySelector('#debug-message').textContent = 'Error: ' + error.message;
            }
            console.warn(`Attempt ${attempt} failed:`, error);
            
            if (attempt < 3) {
                setTimeout(() => sendDismissal(postDate, attempt + 1), 1500 * attempt);
            } else {
                // Final fallback - use both sendBeacon AND a direct XMLHttpRequest
                console.warn('Using sendBeacon and XMLHttpRequest fallbacks');
                
                // Try sendBeacon
                navigator.sendBeacon(
                    operations_notifications_ajax.ajaxurl,
                    new URLSearchParams({
                        action: 'record_popup_dismissal',
                        post_date: postDate,
                        fallback: 'true',
                        cache_buster: cacheBuster
                    })
                );
                
                // Also try XMLHttpRequest as a last resort
                const xhr = new XMLHttpRequest();
                xhr.open('POST', operations_notifications_ajax.ajaxurl, true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4) {
                        if (document.querySelector('#debug-message')) {
                            document.querySelector('#debug-message').textContent = 'XHR completed with status: ' + xhr.status;
                        }
                    }
                };
                xhr.send('action=record_popup_dismissal&post_date=' + encodeURIComponent(postDate) + '&fallback=xhr&cache_buster=' + cacheBuster);
            }
        });
}

function waitForElementorPopupModule(callback, attempt = 0) {
    if (
        typeof elementorProFrontend !== 'undefined' &&
        elementorProFrontend.modules &&
        elementorProFrontend.modules.popup
    ) {
        console.log('Elementor popup module found');
        callback();
    } else if (attempt < 20) {
        console.log('Waiting for Elementor popup module, attempt ' + attempt); 
        setTimeout(() => waitForElementorPopupModule(callback, attempt + 1), 250);
    } else {
        console.warn('Elementor popup module not available after 20 attempts.');
    }
}

function runWhenReady() {
    if (document.readyState === "loading") {
        console.log('Document still loading, waiting for DOMContentLoaded'); 
        document.addEventListener("DOMContentLoaded", function() {
            waitForElementorPopupModule(runPopupLogic); 
        });
    } else {
        console.log('Document already loaded, running popup logic directly'); 
        waitForElementorPopupModule(runPopupLogic);
    }
}

runWhenReady();
