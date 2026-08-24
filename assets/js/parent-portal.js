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

    document.querySelectorAll('[data-e2n-performance-report]').forEach(function (report) {
        var allButton = report.querySelector('[data-e2n-toggle-all-charts]');
        var chartPanels = Array.from(report.querySelectorAll('[data-e2n-event-chart]'));
        function updateButtons() {
            report.querySelectorAll('[data-e2n-toggle-chart]').forEach(function (button) {
                var panel = button.closest('.e2n-parent-performance-event').querySelector('[data-e2n-event-chart]');
                var isOpen = panel && !panel.hidden;
                button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                button.textContent = isOpen ? button.dataset.hideLabel : button.dataset.showLabel;
            });
            if (!allButton) return;
            var allOpen = chartPanels.length > 0 && chartPanels.every(function (panel) { return !panel.hidden; });
            allButton.setAttribute('aria-expanded', allOpen ? 'true' : 'false');
            allButton.textContent = allOpen ? allButton.dataset.hideLabel : allButton.dataset.showLabel;
        }
        report.addEventListener('click', function (event) {
            var button = event.target.closest('[data-e2n-toggle-chart]');
            if (button) {
                var panel = button.closest('.e2n-parent-performance-event').querySelector('[data-e2n-event-chart]');
                if (panel) panel.hidden = !panel.hidden;
                updateButtons();
            }
        });
        if (allButton) allButton.addEventListener('click', function () {
            var openAll = !chartPanels.every(function (panel) { return !panel.hidden; });
            chartPanels.forEach(function (panel) { panel.hidden = !openAll; });
            updateButtons();
        });
        updateButtons();
    });

    document.querySelectorAll('[data-e2n-performance-chart]').forEach(function (chart) {
        var tooltip = chart.querySelector('[data-e2n-chart-tooltip]');
        var pinnedPoint = null;
        function showPoint(point) {
            if (!tooltip || !point) return;
            tooltip.textContent = (point.dataset.date || '') + ' · ' + (point.dataset.time || '');
            tooltip.hidden = false;
        }
        function hidePoint() {
            if (!tooltip || pinnedPoint) return;
            tooltip.hidden = true;
            tooltip.textContent = '';
        }
        chart.querySelectorAll('[data-e2n-chart-point]').forEach(function (point) {
            point.addEventListener('pointerenter', function () { showPoint(point); });
            point.addEventListener('pointerleave', hidePoint);
            point.addEventListener('focus', function () { showPoint(point); });
            point.addEventListener('blur', hidePoint);
            point.addEventListener('click', function (event) {
                event.stopPropagation();
                pinnedPoint = pinnedPoint === point ? null : point;
                if (pinnedPoint) showPoint(point); else hidePoint();
            });
            point.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); point.click(); }
            });
        });
        chart.addEventListener('click', function () { pinnedPoint = null; hidePoint(); });
    });
}());
