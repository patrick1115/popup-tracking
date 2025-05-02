function runPopupLogic() {
    
    setTimeout(function () {
        const wrapper = document.querySelector('.has-category-operations-notifications');
        if (!wrapper) return; 

        if (wrapper) {
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
    
        document.addEventListener('click', function (e) {
                const closeBtn = e.target.closest('.eicon-close, .dialog-close, .elementor-popup-modal .elementor-button');
                if (closeBtn) {
                localStorage.setItem(storageKey, latest);
                console.log('Popup dismissed, stored date:', latest);
                }
            });
            const popupObserver = new MutationObserver(function () {
                const dismissButton = document.querySelector('.elementor-popup-modal .elementor-button');
                if (dismissButton) {
                    dismissButton.addEventListener('click', function () {
                        localStorage.setItem(storageKey, latest);
        
                        fetch(operations_notifications_ajax.ajaxurl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=record_popup_dismissal&post_date=' + encodeURIComponent(latest),
                        })
                        .then(response => response.text())
                        .then(data => {
                            console.log('Dismissal recorded:', data);
                        })
                        .catch(error => {
                            console.error('Dismissal logging failed:', error);  
                        });
                        popupObserver.disconnect();
                    }); 
                }
            });
    
            popupObserver.observe(document.body, { childList: true, subtree: true });
        } else {
            console.log('Popup not shown: either already seen or too old.');
                }
    
        } else {
            console.log('No wrapper found after timeout.');
        }

    }, 1500);   
}   


function sendDismissal(postDate, attempt = 1) {
    localStorage.setItem('popupLastSeen', postDate);

    fetch(operations_notifications_ajax.ajaxurl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=record_popup_dismissal&post_date=' + encodeURIComponent(postDate),
    })
        .then((response) => {
            if (!response.ok) throw new Error('Network response was not OK');
            return response.json();
        })
        .then((data) => {
            console.log('Dismissal recorded:', data);
        })
        .catch((error) => {
            console.warn(`Attempt ${attempt} failed:`, error);
            if (attempt < 3) {
                setTimeout(() => sendDismissal(postDate, attempt + 1), 1000 * attempt);
            } else {
                console.warn('Using sendBeacon fallback');
                navigator.sendBeacon(
                    operations_notifications_ajax.ajaxurl,
                    new URLSearchParams({
                        action: 'record_popup_dismissal',
                        post_date: postDate,
                        fallback: 'true',
                    })
                );
            }
        });
}

function waitForElementorPopupModule(callback, attempt = 0) {
    if (
        typeof elementorProFrontend !== 'undefined' &&
        elementorProFrontend.modules &&
        elementorProFrontend.modules.popup
    ) {
		console.log('in if statement');
        callback();
    } else if (attempt < 20) {
		console.log('in else if statement'); 
        setTimeout(() => waitForElementorPopupModule(callback, attempt + 1), 250);
    } else {
        console.warn('Elementor popup module not available.');
    }
}

function runWhenReady() {
	if (document.readyState === "loading") {
		console.log('if runState loading'); 
        //waitForElementorPopupModule(runPopupLogic);

		document.addEventListener("DOMContentLoaded", function() {
			//runPopupLogic(); 
			waitForElementorPopupModule(runPopupLogic); 
		});
	} else {
		console.log('else runState'); 
		runPopupLogic(); 
	}
}

runWhenReady();

document.addEventListener('click', function(e) {
	console.log("clicked element", e.target); 
	if (e.target.matches('.elementor-button')) {
		console.log("Butto clicked----");
            console.log('Triggering popup!');
            const popupId = 12961;
            elementorProFrontend.modules.popup.showPopup({ id: popupId });

            const handleDismiss = () => {
                localStorage.setItem(storageKey, latest);

                const payload = new URLSearchParams();
                payload.append('action', 'record_popup_dismissal');
                payload.append('post_date', latest);

                if (navigator.sendBeacon) {
                    console.log('at handleDismiss sendBeacon'); 
					const blob = new Blob([payload], { type: 'application/x-www-form-urlencoded' });
                    navigator.sendBeacon(operations_notifications_ajax.ajaxurl, blob);
                //} else {
                	console.log("at FETCH"); 
                    fetch(operations_notifications_ajax.ajaxurl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: payload.toString(),
                    })
                    .then(res => res.json())
                    .then(data => {
                        console.log('Dismissal recorded via fetch:', data);
                    })
                    .catch(err => {
                        console.error('Dismissal failed:', err);
                    });
                }
            };

            const observer = new MutationObserver(() => {
                const dismissButton = document.querySelector('.elementor-popup-modal .elementor-button');
                if (dismissButton) {
                    console.log('Dismiss button EVENTLISTE, attaching handler');
                    dismissButton.addEventListener('click', handleDismiss, { once: true });
                    observer.disconnect();
                }
            });

            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
	}
});