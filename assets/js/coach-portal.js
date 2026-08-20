(function () {
    'use strict';

    function normalize(value) {
        return value.toLocaleLowerCase('fr').normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }

    document.addEventListener('input', function (event) {
        var field = event.target;
        if (!(field instanceof HTMLInputElement) || !field.matches('[data-e2n-swimmer-search]')) return;
        var query = normalize(field.value.trim());
        var visible = 0;
        document.querySelectorAll('[data-e2n-swimmer-card]').forEach(function (card) {
            var matches = query === '' || normalize(card.dataset.search || card.textContent || '').indexOf(query) !== -1;
            card.hidden = !matches;
            if (matches) visible++;
        });
        var empty = document.querySelector('[data-e2n-empty-filter]');
        if (empty) empty.hidden = visible !== 0;
    });

    // La recherche est entièrement locale et doit rester disponible même si
    // la configuration AJAX n'a pas été injectée (cache de page, script isolé).
    if (typeof e2nCoachAjax === 'undefined') return;

    var queues = new Map();
    var noteTimers = new WeakMap();

    function indicatorFor(element) {
        var card = element.closest('.e2n-card');
        return card ? card.querySelector('[data-e2n-save-status]') : null;
    }

    function rowFor(element) {
        return element.closest('.e2n-collective-row, .e2n-skill');
    }

    function showState(element, state, message) {
        var indicator = indicatorFor(element);
        var row = rowFor(element);
        [indicator, row].forEach(function (target) {
            if (!target) return;
            target.classList.remove('is-saving', 'is-saved', 'is-error');
            if (state) target.classList.add('is-' + state);
        });
        if (indicator) indicator.textContent = message || '';
    }

    function post(payload) {
        var body = new FormData();
        Object.keys(payload).forEach(function (key) { body.append(key, payload[key]); });
        body.append('nonce', e2nCoachAjax.nonce);
        return fetch(e2nCoachAjax.url, { method: 'POST', credentials: 'same-origin', body: body })
            .then(function (response) {
                return response.json().then(function (json) {
                    if (!response.ok || !json.success) {
                        throw new Error(json && json.data && json.data.message ? json.data.message : e2nCoachAjax.error);
                    }
                    return json;
                });
            });
    }

    document.addEventListener('click', function (event) {
        if (!(event.target instanceof Element)) return;
        var button = event.target.closest('[data-e2n-send-parent-code]');
        if (!(button instanceof HTMLButtonElement)) return;
        if (!window.confirm(e2nCoachAjax.confirmParentCode)) return;

        var status = document.querySelector('[data-e2n-parent-code-status]');
        button.disabled = true;
        if (status) {
            status.className = 'e2n-parent-code-status is-saving';
            status.textContent = e2nCoachAjax.sendingParentCode;
        }
        post({
            action: 'e2n_coach_send_parent_code',
            group_id: button.dataset.groupId,
            swimmer_id: button.dataset.swimmerId
        }).then(function (json) {
            if (status) {
                status.className = 'e2n-parent-code-status is-saved';
                status.textContent = json.data.message;
            }
        }).catch(function (error) {
            if (status) {
                status.className = 'e2n-parent-code-status is-error';
                status.textContent = error.message || e2nCoachAjax.error;
            }
        }).finally(function () {
            button.disabled = false;
        });
    });

    function queueSave(key, payload, element) {
        var state = queues.get(key) || { busy: false, pending: null };
        queues.set(key, state);
        state.pending = { payload: payload, element: element };
        runQueue(key, state);
    }

    function runQueue(key, state) {
        if (state.busy || !state.pending) return;
        var job = state.pending;
        state.pending = null;
        state.busy = true;
        showState(job.element, 'saving', e2nCoachAjax.saving);
        post(job.payload).then(function () {
            if (!state.pending) showState(job.element, 'saved', e2nCoachAjax.saved);
        }).catch(function (error) {
            showState(job.element, 'error', error.message || e2nCoachAjax.error);
        }).finally(function () {
            state.busy = false;
            if (state.pending) runQueue(key, state);
        });
    }

    document.addEventListener('change', function (event) {
        var input = event.target;
        if (!(input instanceof HTMLInputElement) || input.type !== 'radio' || input.dataset.e2nKind !== 'evaluation') return;
        queueSave(
            'evaluation:' + input.dataset.groupId + ':' + input.dataset.swimmerId + ':' + input.dataset.skillId,
            {
                action: 'e2n_coach_save_evaluation',
                group_id: input.dataset.groupId,
                swimmer_id: input.dataset.swimmerId,
                skill_id: input.dataset.skillId,
                status: input.value
            },
            input
        );
    });

    document.addEventListener('input', function (event) {
        var field = event.target;
        if (!(field instanceof HTMLTextAreaElement) || field.dataset.e2nKind !== 'note') return;
        var previous = noteTimers.get(field);
        if (previous) window.clearTimeout(previous);
        showState(field, 'saving', e2nCoachAjax.saving);
        noteTimers.set(field, window.setTimeout(function () {
            queueSave(
                'note:' + field.dataset.groupId + ':' + field.dataset.swimmerId + ':' + field.dataset.skillId,
                {
                    action: 'e2n_coach_save_note',
                    group_id: field.dataset.groupId,
                    swimmer_id: field.dataset.swimmerId,
                    skill_id: field.dataset.skillId,
                    note: field.value
                },
                field
            );
        }, 900));
    });
}());
