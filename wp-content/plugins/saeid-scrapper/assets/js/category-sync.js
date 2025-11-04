(function ($) {
    'use strict';

    const settings = window.saeidScrapperCategory || {};
    const strings = settings.strings || {};
    const counts = settings.overview && settings.overview.counts ? settings.overview.counts : { total: 0, pending: 0, success: 0, failed: 0 };

    const state = {
        counts: counts,
        running: false,
        inFlight: false,
        startTime: null,
        retries: 0,
    };

    function init() {
        updateCounts(state.counts);
        renderExisting(settings.overview ? settings.overview.existing : []);
        renderMissing(settings.overview ? settings.overview.missing : []);
        renderFailed(settings.overview ? settings.overview.failed : []);
        updateProgress();

        const startButton = $('#saeid-scrapper-category-start');
        startButton.on('click', function (event) {
            event.preventDefault();
            if (state.running) {
                return;
            }

            state.running = true;
            state.startTime = Date.now();
            startButton.prop('disabled', true).text(strings.processing || startButton.text());
            processNext();
        });
    }

    function processNext() {
        if (state.inFlight) {
            return;
        }

        state.inFlight = true;

        $.post(settings.ajaxUrl, {
            action: 'saeid_scrapper_sync_category',
            nonce: settings.nonce,
        }).done(function (response) {
            state.inFlight = false;

            if (!response || response.success !== true) {
                handleAjaxError(response && response.data ? response.data.message : '');
                return;
            }

            handleAjaxSuccess(response.data);
        }).fail(function (jqXHR) {
            state.inFlight = false;
            handleAjaxError(jqXHR && jqXHR.statusText ? jqXHR.statusText : '');
        });
    }

    function handleAjaxError(message) {
        state.retries++;
        addLog((strings.error || 'Error: ') + (message || 'Unknown error'), true);

        if (state.retries > 5) {
            addLog(strings.noItems || 'Processing stopped.', true);
            stopProcessing();
            return;
        }

        window.setTimeout(processNext, 1500);
    }

    function handleAjaxSuccess(data) {
        state.retries = 0;

        if (data && data.counts) {
            updateCounts(data.counts);
        }

        if (data && Array.isArray(data.existing)) {
            renderExisting(data.existing);
        }

        if (data && Array.isArray(data.missing)) {
            renderMissing(data.missing);
        }

        if (data && Array.isArray(data.failed)) {
            renderFailed(data.failed);
        }

        if (data && data.message) {
            addLog(data.message, data.status === 'failed');
        }

        updateProgress();

        if (!data || data.status === 'done') {
            addLog(strings.completed || 'Completed.', false);
            stopProcessing();
            return;
        }

        window.setTimeout(processNext, 400);
    }

    function stopProcessing() {
        state.running = false;
        state.inFlight = false;
        const startButton = $('#saeid-scrapper-category-start');
        startButton.prop('disabled', false).text(strings.start || startButton.text());
    }

    function updateCounts(newCounts) {
        state.counts = $.extend({}, state.counts, newCounts || {});
        $('#saeid-scrapper-category-total').text(state.counts.total || 0);
        $('#saeid-scrapper-category-pending').text(state.counts.pending || 0);
        $('#saeid-scrapper-category-success').text(state.counts.success || 0);
        $('#saeid-scrapper-category-failed').text(state.counts.failed || 0);
    }

    function updateProgress() {
        const total = parseInt(state.counts.total || 0, 10);
        const pending = parseInt(state.counts.pending || 0, 10);
        const processed = total > 0 ? total - pending : 0;
        const percent = total > 0 ? Math.round((processed / total) * 100) : (state.running ? 100 : 0);
        $('#saeid-scrapper-category-progress-bar').css('width', Math.min(100, Math.max(0, percent)) + '%');
        $('#saeid-scrapper-category-progress-label').text((Math.min(100, Math.max(0, percent))) + '%');
        updateRemainingTime(processed, pending);
    }

    function updateRemainingTime(processed, pending) {
        const label = $('#saeid-scrapper-category-time-remaining');
        if (!state.running || processed <= 0 || pending <= 0) {
            label.text('');
            return;
        }

        const elapsedSeconds = (Date.now() - state.startTime) / 1000;
        const average = elapsedSeconds / processed;
        const remainingSeconds = Math.max(0, Math.round(average * pending));

        if (remainingSeconds > 120) {
            const minutes = Math.round(remainingSeconds / 60);
            label.text((strings.remainingTime || 'Remaining') + ': ' + minutes + ' ' + (strings.minutes || 'minutes'));
        } else {
            label.text((strings.remainingTime || 'Remaining') + ': ' + remainingSeconds + ' ' + (strings.seconds || 'seconds'));
        }
    }

    function renderExisting(items) {
        const tbody = $('#saeid-scrapper-category-existing');
        tbody.empty();

        if (!items || items.length === 0) {
            tbody.append('<tr><td colspan="2">' + (strings.noItems || 'No items.') + '</td></tr>');
            return;
        }

        items.forEach(function (item) {
            const url = item.url ? $('<a/>', {
                href: item.url,
                target: '_blank',
                rel: 'noopener noreferrer'
            }).text(item.url).prop('outerHTML') : '';

            let productColumn = '&mdash;';
            if (item.product_id) {
                if (item.edit_link) {
                    productColumn = $('<a/>', {
                        href: item.edit_link,
                        target: '_blank',
                        rel: 'noopener noreferrer'
                    }).text(item.product_id).prop('outerHTML');
                } else {
                    productColumn = $('<span/>').text(item.product_id).html();
                }
            }

            tbody.append('<tr><td>' + url + '</td><td>' + productColumn + '</td></tr>');
        });
    }

    function renderMissing(items) {
        const tbody = $('#saeid-scrapper-category-missing');
        tbody.empty();

        if (!items || items.length === 0) {
            tbody.append('<tr><td>' + (strings.noItems || 'No items.') + '</td></tr>');
            return;
        }

        items.forEach(function (item) {
            const url = item.url ? $('<a/>', {
                href: item.url,
                target: '_blank',
                rel: 'noopener noreferrer'
            }).text(item.url).prop('outerHTML') : '';

            tbody.append('<tr><td>' + url + '</td></tr>');
        });
    }

    function renderFailed(items) {
        const tbody = $('#saeid-scrapper-category-failed-list');
        tbody.empty();

        if (!items || items.length === 0) {
            tbody.append('<tr><td colspan="2">' + (strings.noItems || 'No items.') + '</td></tr>');
            return;
        }

        items.forEach(function (item) {
            const url = item.url ? $('<a/>', {
                href: item.url,
                target: '_blank',
                rel: 'noopener noreferrer'
            }).text(item.url).prop('outerHTML') : '';
            const message = item.message ? $('<span/>').text(item.message).html() : '';
            tbody.append('<tr><td>' + url + '</td><td>' + message + '</td></tr>');
        });
    }

    function addLog(message, isError) {
        if (!message) {
            return;
        }

        const log = $('#saeid-scrapper-category-log');
        const item = $('<li/>').text(message);
        if (isError) {
            item.addClass('saeid-scrapper-log--error');
        }

        log.append(item);

        if (log.length) {
            log.scrollTop(log[0].scrollHeight);
        }
    }

    $(init);
})(jQuery);
