/*
 * Auto-submitting filter bars.
 *
 * A form marked `data-auto-filter` applies its filters the moment they change,
 * so there is no Filter button to press: picking a dropdown option or a date
 * submits straight away, and typing in a text field submits after a short
 * pause so a word is not submitted one letter at a time. Enter still submits
 * immediately, and the row dims while the new page loads.
 *
 * Delegated on the document, so it also covers filter bars added later (an
 * "Add Item" row, a modal) without re-binding. Purely a convenience: with
 * JavaScript off the form still submits on Enter and the URL filters still
 * work, so nothing depends on this file.
 */
(function () {
    'use strict';

    var PAUSE_MS = 400;

    function submit(form) {
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.submit();
        }
    }

    // Dropdowns and dates: one decisive change is the whole intent.
    document.addEventListener('change', function (event) {
        var field = event.target;
        if (!field.matches('select, input[type="date"]')) return;

        var form = field.closest('form[data-auto-filter]');
        if (form) submit(form);
    });

    // Text: wait for the typing to settle before reloading the list.
    var timer;
    document.addEventListener('input', function (event) {
        var field = event.target;
        if (!field.matches('input[type="text"], input[type="search"]')) return;

        var form = field.closest('form[data-auto-filter]');
        if (!form) return;

        clearTimeout(timer);
        timer = setTimeout(function () {
            submit(form);
        }, PAUSE_MS);
    });

    // Feedback: the results are on their way.
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (form.matches('form[data-auto-filter]')) form.classList.add('is-filtering');
    });
})();
