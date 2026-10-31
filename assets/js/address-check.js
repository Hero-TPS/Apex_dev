// assets/js/address-check.js
// Shared address checking for the intranet (client address, Services destinations).
// Two ways to check an address:
//   1. Google Places suggestions: picking one stores the exact Google place (place id and point).
//   2. A server check ("Check address"): asks Google whether it can find the typed text at all.
// If the Maps script cannot load, both fields still work as plain text and the server check still works.
(function (window, $) {
    'use strict';

    var mapsKey = '';
    var mapsState = 'idle';          // idle, loading, ready, failed
    var waiting = [];

    function runWaiting() {
        var list = waiting;
        waiting = [];
        list.forEach(function (fn) { try { fn(); } catch (err) { /* one bad field must not stop the others */ } });
    }

    window.atAddressMapsLoaded = function () {
        mapsState = 'ready';
        runWaiting();
    };
    window.gm_authFailure = function () {
        mapsState = 'failed';
        logProblem('Google rejected the map key for this site. Check its allowed websites.');
    };

    function logProblem(reason) {
        if (window.logJsError) {
            window.logJsError('ERROR', 'Address suggestions unavailable: ' + reason, { page_url: window.location.href });
        }
    }

    function loadMaps() {
        if (mapsState !== 'idle') { return; }
        if (!mapsKey) { mapsState = 'failed'; return; }
        mapsState = 'loading';
        var s = document.createElement('script');
        s.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(mapsKey) + '&libraries=places&callback=atAddressMapsLoaded';
        s.async = true;
        s.onerror = function () {
            mapsState = 'failed';
            logProblem('the Google Maps script did not load');
        };
        document.head.appendChild(s);
    }

    function init(key) {
        mapsKey = key || mapsKey;
        loadMaps();
    }

    // Google suggestions on one text input. onPick receives {placeId, address, name, lat, lng}.
    function attachSearch(input, onPick) {
        if (!input) { return; }
        $(input).on('keydown', function (e) {
            if (e.key === 'Enter' && $('.pac-container:visible').length) { e.preventDefault(); }
        });
        var start = function () {
            try {
                var ac = new google.maps.places.Autocomplete(input, {
                    componentRestrictions: { country: 'za' },
                    fields: ['place_id', 'formatted_address', 'name', 'geometry']
                });
                ac.addListener('place_changed', function () {
                    var p = ac.getPlace();
                    if (!p || !p.place_id) { return; }
                    var addr = p.formatted_address || '';
                    var nm   = p.name || '';
                    var full = (nm && addr.indexOf(nm) !== 0) ? (addr ? nm + ', ' + addr : nm) : (addr || input.value);
                    var loc  = p.geometry && p.geometry.location ? p.geometry.location : null;
                    onPick({
                        placeId: p.place_id,
                        address: full,
                        name:    nm,
                        lat:     loc ? loc.lat() : null,
                        lng:     loc ? loc.lng() : null
                    });
                });
            } catch (err) {
                mapsState = 'failed';
                logProblem('Autocomplete error');
            }
        };
        if (mapsState === 'ready') { start(); } else if (mapsState !== 'failed') { waiting.push(start); }
    }

    function mapsAvailable() { return mapsState !== 'failed'; }

    // Server check: can Google find this address, and is it near enough to be believable?
    // done(result) gets {success, level (ok, warn, fail), message, matched}.
    function serverCheck(url, params, done) {
        $.ajax({
            type: 'POST',
            url: url,
            data: params,
            dataType: 'json',
            success: function (res) { done(res); },
            error: function () {
                done({ success: false, level: 'warn', message: 'The check could not be completed. Please try again.' });
            }
        });
    }

    function showNote($el, level, text) {
        $el.attr('class', 'at-addr-note' + (level ? ' at-addr-' + level : '')).text(text || '');
    }

    // The client address field: a textarea with a Google search box above it, a status line and a Check button.
    // opts: address, find, placeId, verified, status, checkBtn (selectors), checkUrl, villageMatch, hasPin
    function bindClientAddress(opts) {
        var $addr = $(opts.address), $find = $(opts.find), $pid = $(opts.placeId), $ver = $(opts.verified);
        var $status = $(opts.status), $btn = $(opts.checkBtn);
        var hasPin = !!opts.hasPin;
        var village = (opts.villageMatch || '').toLowerCase();

        function isVillage() {
            return village !== '' && $addr.val().toLowerCase().indexOf(village) !== -1;
        }

        function refresh() {
            var addr = $.trim($addr.val());
            if (addr === '') {
                showNote($status, '', '');
                return;
            }
            if (hasPin) {
                showNote($status, 'ok', '✅ GPS pin set: distances use the pin.');
            } else if (isVillage()) {
                showNote($status, 'info', 'Estate address: distances are measured from the estate main gate, so no check is needed.');
            } else if ($ver.val() === '1') {
                showNote($status, 'ok', $pid.val() ? '✅ Checked: exact Google place saved.' : '✅ Checked: Google found this address.');
            } else {
                showNote($status, 'warn', '⚠️ Not checked against Google yet. Search above, or use Check address.');
            }
        }

        $addr.on('input', function () {
            // Typing changes the address, so any earlier check no longer applies
            $pid.val('');
            $ver.val('0');
            refresh();
        });

        attachSearch($find[0], function (p) {
            $addr.val(p.address);
            $pid.val(p.placeId);
            $ver.val('1');
            $find.val('');
            refresh();
        });

        $btn.on('click', function () {
            var addr = $.trim($addr.val());
            if (addr === '') {
                showNote($status, 'warn', 'Enter an address first.');
                return;
            }
            var original = $btn.text();
            $btn.prop('disabled', true).text('Checking…');
            showNote($status, 'info', 'Asking Google…');
            serverCheck(opts.checkUrl, { address: addr }, function (res) {
                $btn.prop('disabled', false).text(original);
                if (res.level === 'ok') {
                    $ver.val('1');
                    showNote($status, 'ok', '✅ ' + res.message);
                } else {
                    $ver.val('0');
                    showNote($status, res.level === 'fail' ? 'fail' : 'warn', '⚠️ ' + (res.message || 'The check could not be completed.'));
                }
            });
        });

        return {
            setPin: function (v) { hasPin = !!v; refresh(); },
            refresh: refresh
        };
    }

    window.AtAddress = {
        init: init,
        attachSearch: attachSearch,
        mapsAvailable: mapsAvailable,
        serverCheck: serverCheck,
        showNote: showNote,
        bindClientAddress: bindClientAddress
    };
})(window, jQuery);
