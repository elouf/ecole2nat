(function () {
    'use strict';

    function updateAttendanceDays(form) {
        var fieldset = form.querySelector('[data-e2n-attendance-days]');
        if (!fieldset) return;
        var response = form.querySelector('input[name="response"]:checked');
        var enabled = response && response.value === 'yes';
        fieldset.hidden = !enabled;
        fieldset.querySelectorAll('input').forEach(function (input) {
            input.disabled = !enabled;
            input.required = enabled;
        });
    }

    document.querySelectorAll('[data-e2n-competition-response]').forEach(function (form) {
        updateAttendanceDays(form);
        form.querySelectorAll('input[name="response"]').forEach(function (input) {
            input.addEventListener('change', function () { updateAttendanceDays(form); });
        });
    });
}());
