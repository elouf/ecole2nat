(function () {
    'use strict';

    if (typeof e2nCoachAjax === 'undefined') {
        return;
    }

    var queues = new Map();
    var noteTimers = new WeakMap();
    var editorTimers = new WeakMap();

    function saveStatus(element, state, message) {
        if (!element) return;
        element.classList.remove('is-saving', 'is-saved', 'is-error');
        if (state) element.classList.add('is-' + state);
        element.textContent = message || '';
    }

    function statusFor(element) {
        var card = element.closest('.e2n-card');
        return card ? card.querySelector('[data-e2n-save-status]') : null;
    }

    function rowFor(element) {
        return element.closest('.e2n-attendance-row, .e2n-collective-row, .e2n-skill');
    }

    function markRow(element, state) {
        var row = rowFor(element);
        if (!row) return;
        row.classList.remove('is-saving', 'is-saved', 'is-error');
        if (state) row.classList.add('is-' + state);
    }

    function post(payload) {
        var body = new FormData();
        Object.keys(payload).forEach(function (key) {
            var value = payload[key];
            if (value && typeof value === 'object' && !Array.isArray(value)) {
                Object.keys(value).forEach(function (subKey) {
                    body.append(key + '[' + subKey + ']', value[subKey]);
                });
            } else {
                body.append(key, value);
            }
        });
        body.append('nonce', e2nCoachAjax.nonce);

        return fetch(e2nCoachAjax.url, {
            method: 'POST',
            credentials: 'same-origin',
            body: body
        }).then(function (response) {
            return response.json().then(function (json) {
                if (!response.ok || !json.success) {
                    var message = json && json.data && json.data.message ? json.data.message : e2nCoachAjax.error;
                    throw new Error(message);
                }
                return json;
            });
        });
    }

    function queueSave(key, payload, element, onSuccess) {
        var state = queues.get(key);
        if (!state) {
            state = { busy: false, pending: null };
            queues.set(key, state);
        }
        state.pending = { payload: payload, element: element, onSuccess: onSuccess };
        runQueue(key, state);
    }

    function runQueue(key, state) {
        if (state.busy || !state.pending) return;

        var job = state.pending;
        state.pending = null;
        state.busy = true;

        var indicator = statusFor(job.element);
        saveStatus(indicator, 'saving', e2nCoachAjax.saving);
        markRow(job.element, 'saving');

        post(job.payload).then(function (json) {
            markRow(job.element, 'saved');
            if (typeof job.onSuccess === 'function') job.onSuccess(json);
            if (!state.pending) saveStatus(indicator, 'saved', e2nCoachAjax.saved);
        }).catch(function (error) {
            markRow(job.element, 'error');
            saveStatus(indicator, 'error', error.message || e2nCoachAjax.error);
        }).finally(function () {
            state.busy = false;
            if (state.pending) {
                runQueue(key, state);
            } else {
                window.setTimeout(function () {
                    if (indicator && indicator.classList.contains('is-saved')) {
                        saveStatus(indicator, '', '');
                    }
                }, 1800);
            }
        });
    }

    function updateAttendanceSummary(scope) {
        var card = scope.closest('.e2n-card');
        if (!card) return;
        var summary = card.querySelector('[data-e2n-attendance-summary]');
        if (!summary) return;

        var present = 0;
        var absent = 0;
        scope.querySelectorAll('input[data-e2n-kind="attendance"]:checked').forEach(function (radio) {
            if (radio.value === 'present') present++;
            if (radio.value === 'absent') absent++;
        });
        var total = parseInt(summary.getAttribute('data-total') || '0', 10);
        summary.textContent = present + ' présents · ' + absent + ' absents · ' + total + ' prévus';
    }

    document.addEventListener('change', function (event) {
        var input = event.target;
        if (!(input instanceof HTMLInputElement) || input.type !== 'radio') return;

        var kind = input.getAttribute('data-e2n-kind');
        if (kind === 'attendance') {
            var attendanceScope = input.closest('.e2n-attendance-form');
            if (attendanceScope) updateAttendanceSummary(attendanceScope);
            queueSave(
                'attendance:' + input.dataset.groupId + ':' + input.dataset.sessionDate + ':' + input.dataset.swimmerId,
                {
                    action: 'e2n_coach_save_attendance',
                    group_id: input.dataset.groupId,
                    swimmer_id: input.dataset.swimmerId,
                    session_date: input.dataset.sessionDate,
                    status: input.value
                },
                input
            );
        }

        if (kind === 'evaluation') {
            queueSave(
                'evaluation:' + input.dataset.groupId + ':' + input.dataset.swimmerId + ':' + input.dataset.skillId,
                {
                    action: 'e2n_coach_save_evaluation',
                    group_id: input.dataset.groupId,
                    swimmer_id: input.dataset.swimmerId,
                    skill_id: input.dataset.skillId,
                    session_date: input.dataset.sessionDate,
                    status: input.value
                },
                input
            );
        }
    });

    document.addEventListener('input', function (event) {
        var field = event.target;
        if (!(field instanceof HTMLInputElement) && !(field instanceof HTMLTextAreaElement)) return;

        var editorKind = field.getAttribute('data-e2n-editor-field');
        if (editorKind) {
            var editorPrevious = editorTimers.get(field);
            if (editorPrevious) window.clearTimeout(editorPrevious);
            saveStatus(statusFor(field), 'saving', e2nCoachAjax.saving);
            editorTimers.set(field, window.setTimeout(function () {
                var scope = editorKind === 'general' ? field.closest('.e2n-editor-general') : field.closest('.e2n-editor-part, .e2n-editor-exercise');
                var payload = {
                    action: 'e2n_coach_session_action',
                    editor_action: editorKind === 'general' ? 'save_general' : (editorKind === 'part' ? 'save_part' : 'save_exercise'),
                    group_id: field.dataset.groupId,
                    session_id: field.dataset.sessionId,
                    session_date: field.dataset.sessionDate
                };
                if (editorKind === 'general') {
                    payload.name = scope.querySelector('[data-field="name"]').value;
                    payload.objectives = scope.querySelector('[data-field="objectives"]').value;
                } else if (editorKind === 'part') {
                    payload.part_id = field.dataset.partId;
                    payload.title = field.value;
                } else {
                    payload.item_id = field.dataset.itemId;
                    payload.duration = scope.querySelector('[data-field="duration"]').value;
                    payload.notes = scope.querySelector('[data-field="notes"]').value;
                }
                queueSave('session:' + editorKind + ':' + (field.dataset.itemId || field.dataset.partId || field.dataset.sessionId), payload, field);
            }, 700));
            return;
        }

        if (!(field instanceof HTMLTextAreaElement) || field.getAttribute('data-e2n-kind') !== 'note') return;

        var previous = noteTimers.get(field);
        if (previous) window.clearTimeout(previous);
        saveStatus(statusFor(field), 'saving', e2nCoachAjax.saving);
        markRow(field, 'saving');

        noteTimers.set(field, window.setTimeout(function () {
            queueSave(
                'note:' + field.dataset.groupId + ':' + field.dataset.swimmerId + ':' + field.dataset.skillId,
                {
                    action: 'e2n_coach_save_note',
                    group_id: field.dataset.groupId,
                    swimmer_id: field.dataset.swimmerId,
                    skill_id: field.dataset.skillId,
                    session_date: field.dataset.sessionDate,
                    note: field.value
                },
                field
            );
        }, 900));
    });

    document.addEventListener('submit', function (event) {
        var form = event.target.closest('[data-e2n-session-action]');
        if (!form) return;
        event.preventDefault();
        var confirmation = form.getAttribute('data-confirm');
        if (confirmation && !window.confirm(confirmation)) return;

        var payload = { action: 'e2n_coach_session_action' };
        new FormData(form).forEach(function (value, key) { payload[key] = value; });
        var indicator = form.querySelector('[data-e2n-save-status]') || statusFor(form);
        saveStatus(indicator, 'saving', e2nCoachAjax.saving);
        form.querySelectorAll('button').forEach(function (button) { button.disabled = true; });
        post(payload).then(function (json) {
            if (json.data && json.data.redirect) {
                window.location.assign(json.data.redirect);
                return;
            }
            window.location.reload();
        }).catch(function (error) {
            saveStatus(indicator, 'error', error.message || e2nCoachAjax.error);
            form.querySelectorAll('button').forEach(function (button) { button.disabled = false; });
        });
    });

    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-e2n-all-present]');
        if (!button) return;

        var scope = button.closest('.e2n-attendance-form');
        if (!scope) return;

        var statuses = {};
        scope.querySelectorAll('input[data-e2n-kind="attendance"][value="present"]').forEach(function (radio) {
            if (radio.disabled) return;
            radio.checked = true;
            statuses[radio.dataset.swimmerId] = 'present';
            markRow(radio, 'saving');
        });
        updateAttendanceSummary(scope);

        queueSave(
            'attendance-batch:' + button.dataset.groupId + ':' + button.dataset.sessionDate,
            {
                action: 'e2n_coach_save_attendance',
                group_id: button.dataset.groupId,
                session_date: button.dataset.sessionDate,
                statuses: statuses
            },
            button,
            function () {
                scope.querySelectorAll('.e2n-attendance-row.is-saving').forEach(function (row) {
                    row.classList.remove('is-saving');
                    row.classList.add('is-saved');
                });
            }
        );
    });
}());
