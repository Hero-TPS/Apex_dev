// assets/js/whatsapp-menu.js
// Behaviour for the WhatsApp dropdown menu: open / close, and log the message when one is sent.
// Markup comes from renderWhatsAppMenu() in modules/WhatsApp/helpers.php (and the same markup
// built in JS in modules/Clients/index.php). Loaded site-wide via includes/header.php.
(function () {
    'use strict';

    function closeAll(except) {
        document.querySelectorAll('.at-wa-menu').forEach(function (menu) {
            if (menu === except) {
                return;
            }
            var list = menu.querySelector('.at-wa-menu-list');
            var toggle = menu.querySelector('.at-wa-menu-toggle');
            if (list) {
                list.hidden = true;
            }
            if (toggle) {
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
    }

    // Records the send in whatsapp_log (shown under Message History). A blank message has
    // nothing to record, and the log API rejects empty content.
    function logSend(item) {
        var message = item.getAttribute('data-wa-msg') || '';
        if (message === '') {
            return;
        }

        var body = new URLSearchParams();
        body.append('action', 'log_whatsapp');
        body.append('booking_id', item.getAttribute('data-booking-id') || '');
        body.append('contact_id', item.getAttribute('data-contact-id') || '');
        body.append('message_type', item.getAttribute('data-wa-key') || 'message');
        body.append('message_content', message);
        body.append('sent_by', 'user');

        fetch((window.APP_BASE_URL || '') + '/modules/Bookings/api/index.php', {
            method: 'POST',
            body: body,
            credentials: 'same-origin'
        }).catch(function (err) {
            if (window.logJsError) {
                window.logJsError('warning', 'WhatsApp send log failed', { error: String(err) });
            }
        });
    }

    document.addEventListener('click', function (e) {
        var toggle = e.target.closest('.at-wa-menu-toggle');
        if (toggle) {
            var menu = toggle.closest('.at-wa-menu');
            var list = menu.querySelector('.at-wa-menu-list');
            var opening = list.hidden;
            closeAll(menu);
            list.hidden = !opening;
            toggle.setAttribute('aria-expanded', opening ? 'true' : 'false');
            return;
        }

        var item = e.target.closest('.at-wa-menu-item');
        if (item) {
            logSend(item);
        }
        closeAll(null);
    });
})();
