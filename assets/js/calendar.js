// assets/js/calendar.js
function openCalendarApp(calendarId) {
    const userAgent = navigator.userAgent || navigator.vendor;
    const isIOS = /iPad|iPhone|iPod/.test(userAgent);
    const isAndroid = /android/i.test(userAgent);
    const webUrl = 'https://calendar.google.com/calendar/u/0?cid=' + encodeURIComponent(calendarId);

    if (isIOS) {
        // Try Google Calendar app, fallback to web
        window.location = 'googlegcal://';
        setTimeout(() => window.location = webUrl, 500);
    } else if (isAndroid) {
        // Android intent (simplified reliable fallback)
        window.open(webUrl, '_blank');
    } else {
        // Desktop: open in new tab
        window.open(webUrl, '_blank');
    }
}