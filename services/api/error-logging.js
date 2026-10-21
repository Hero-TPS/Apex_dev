/**
 * assets/js/error-logging.js
 *
 * Site-wide client-side error reporting. Generalizes the pattern piloted
 * in modules/DistanceCalculator/log-error.php + its inline JS to every
 * page. Reports uncaught errors, unhandled promise rejections, and failed
 * jQuery AJAX calls to maintenance/api/log_js_error.php, which writes them
 * into the existing system_logs table (viewable at /maintenance/logs.php).
 *
 * This exists because development happens on Android with no browser
 * console access — silent JS failures are otherwise invisible.
 *
 * Requires window.APP_BASE_URL to be set (see includes/header.php).
 */
(function () {
    var LOG_ENDPOINT = (window.APP_BASE_URL || '') + '/maintenance/api/log_js_error.php';

    function logJsError(level, message, context) {
        context = context || {};
        try {
            fetch(LOG_ENDPOINT, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ level: level, message: message, context: context }),
                keepalive: true
            }).catch(function () {
                // If the logger itself can't be reached, nothing more we
                // can do without a console — fail silently.
            });
        } catch (e) {
            // fetch unsupported or threw synchronously — nothing more to do.
        }
    }

    // Catches any uncaught JS error on the page.
    window.addEventListener('error', function (event) {
        logJsError('ERROR', 'Uncaught JS error: ' + event.message, {
            source: event.filename,
            line: event.lineno,
            col: event.colno
        });
    });

    // Catches unhandled promise rejections — e.g. a fetch() call whose
    // .catch() was forgotten, or a rejected promise nobody awaited. This
    // is the main source of silent failures that console.error would
    // normally surface.
    window.addEventListener('unhandledrejection', function (event) {
        var reason = event.reason;
        var message = (reason && reason.message) ? reason.message : String(reason);
        logJsError('ERROR', 'Unhandled promise rejection: ' + message, {
            stack: (reason && reason.stack) ? reason.stack : null
        });
    });

    // Global hook for jQuery's $.ajax — catches every failed AJAX call
    // site-wide, even ones whose own error: callback only shows a toast
    // and doesn't log anything itself.
    if (window.jQuery) {
        jQuery(document).ajaxError(function (event, jqXHR, settings, thrownError) {
            logJsError('ERROR', 'AJAX request failed: ' + (settings.url || 'unknown URL'), {
                status: jqXHR.status,
                statusText: jqXHR.statusText,
                thrownError: thrownError || null
            });
        });
    }

    // Exposed so a page can report its own caught exceptions with extra
    // context, the same way modules/DistanceCalculator does today.
    window.logJsError = logJsError;
})();
