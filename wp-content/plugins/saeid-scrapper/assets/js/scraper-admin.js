(function ($) {
    'use strict';

    const settings = window.saeidScrapperScrape || {};
    const strings = settings.strings || {};
    const counts = settings.overview && settings.overview.counts ? settings.overview.counts : { total: 0, pending: 0, success: 0, failed: 0 };

    const state = {
        counts: counts,
        running: false,
        startTime: null,
        inFlight: false,
        retries: 0,
    };

    function init() {
        updateCounts(state.counts);
        renderPending(settings.overview ? settings.overview.pending : []);
        renderCompleted(settings.overview ? settings.overview.completed : []);
        renderFailed(settings.overview ? settings.overview.failed : []);
        updateProgress();

        const startButton = $('#saeid-scrapper-start');
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
            action: 'saeid_scrapper_process_next',
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
            addLog(strings.noUrls || 'Processing stopped.', true);
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

        if (data && Array.isArray(data.pending)) {
            renderPending(data.pending);
        }

        if (data && Array.isArray(data.completed)) {
            renderCompleted(data.completed);
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

        if (data.status === 'skipped') {
            window.setTimeout(processNext, 400);
            return;
        }

        window.setTimeout(processNext, 400);
    }

    function stopProcessing() {
        state.running = false;
        state.inFlight = false;
        const startButton = $('#saeid-scrapper-start');
        startButton.prop('disabled', false).text(strings.start || startButton.text());
    }

    function updateCounts(newCounts) {
        state.counts = $.extend({}, state.counts, newCounts || {});
        $('#saeid-scrapper-total-count').text(state.counts.total || 0);
        $('#saeid-scrapper-pending-count').text(state.counts.pending || 0);
        $('#saeid-scrapper-success-count').text(state.counts.success || 0);
        $('#saeid-scrapper-failed-count').text(state.counts.failed || 0);
    }

    function updateProgress() {
        const total = parseInt(state.counts.total || 0, 10);
        const pending = parseInt(state.counts.pending || 0, 10);
        const processed = total > 0 ? total - pending : 0;
        const percent = total > 0 ? Math.round((processed / total) * 100) : (state.running ? 100 : 0);
        $('#saeid-scrapper-progress-bar').css('width', Math.min(100, Math.max(0, percent)) + '%');
        $('#saeid-scrapper-progress-label').text((Math.min(100, Math.max(0, percent))) + '%');
        updateRemainingTime(processed, pending);
    }

    function updateRemainingTime(processed, pending) {
        const label = $('#saeid-scrapper-time-remaining');
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

    function renderPending(items) {
        const tbody = $('#saeid-scrapper-pending-list');
        tbody.empty();

        if (!items || items.length === 0) {
            tbody.append('<tr><td colspan="2">' + (strings.noUrls || 'No pending URLs.') + '</td></tr>');
            return;
        }

        items.forEach(function (item) {
            const statusText = item.label || item.status || '';
            const status = statusText ? $('<span/>').text(statusText).html() : '';
            const url = item.url ? $('<a/>', {
                href: item.url,
                target: '_blank',
                rel: 'noopener noreferrer'
            }).text(item.url).prop('outerHTML') : '';

            tbody.append('<tr><td>' + url + '</td><td>' + status + '</td></tr>');
        });
    }

    function renderCompleted(items) {
        const tbody = $('#saeid-scrapper-completed-list');
        tbody.empty();

        if (!items || items.length === 0) {
            tbody.append('<tr><td colspan="2">' + (strings.noProducts || 'No products yet.') + '</td></tr>');
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
                const editUrl = item.edit_link ? item.edit_link : null;
                if (editUrl) {
                    productColumn = $('<a/>', {
                        href: editUrl,
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

    function renderFailed(items) {
        const tbody = $('#saeid-scrapper-failed-list');
        tbody.empty();

        if (!items || items.length === 0) {
            tbody.append('<tr><td colspan="2">' + (strings.noErrors || 'No errors.') + '</td></tr>');
            return;
        }

        items.forEach(function (item) {
            const url = item.url ? $('<a/>', {
                href: item.url,
                target: '_blank',
                rel: 'noopener noreferrer'
            }).text(item.url).prop('outerHTML') : '';

            const error = item.last_error ? $('<span/>').text(item.last_error).html() : '';
            tbody.append('<tr><td>' + url + '</td><td>' + error + '</td></tr>');
        });
    }

    function addLog(message, isError) {
        if (!message) {
            return;
        }

        const list = $('#saeid-scrapper-log');
        const item = $('<li/>').html(message);
        if (isError) {
            item.addClass('saeid-scrapper-log--error');
        }

        list.append(item);

        const maxEntries = 100;
        if (list.children().length > maxEntries) {
            list.children().first().remove();
        }

        list.scrollTop(list[0].scrollHeight);
    }

    $(init);
})(jQuery);
