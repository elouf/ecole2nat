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

    function competitionRank(row) {
        var response = row.querySelector('[data-e2n-kind="competition-response"]:checked');
        var engaged = row.querySelector('[data-e2n-kind="competition-engaged"]');
        if (response && response.value === 'yes') return engaged && engaged.checked ? 0 : 1;
        if (response && response.value === 'no') return 2;
        return 3;
    }

    function updateCompetitionRow(row) {
        row.classList.remove('is-complete', 'is-pending', 'is-declined', 'is-unanswered');
        row.classList.add(['is-complete', 'is-pending', 'is-declined', 'is-unanswered'][competitionRank(row)]);
    }

    function sortCompetitionRows(button) {
        var card = button.closest('.e2n-card');
        var list = card ? card.querySelector('.e2n-competition-swimmers') : null;
        if (!list) return;
        var mode = button.dataset.e2nCompetitionSort;
        var rows = Array.from(list.querySelectorAll('[data-e2n-competition-swimmer]'));
        rows.sort(function (left, right) {
            var alpha = (left.dataset.alpha || '').localeCompare(right.dataset.alpha || '', 'fr');
            return mode === 'status' ? competitionRank(left) - competitionRank(right) || alpha : alpha;
        });
        rows.forEach(function (row) { list.appendChild(row); });
        card.querySelectorAll('[data-e2n-competition-sort]').forEach(function (sortButton) {
            sortButton.classList.toggle('is-active', sortButton === button);
            sortButton.setAttribute('aria-pressed', sortButton === button ? 'true' : 'false');
        });
    }

    document.addEventListener('click', function (event) {
        if (!(event.target instanceof Element)) return;
        var eventButton = event.target.closest('[data-e2n-event]');
        if (eventButton instanceof HTMLButtonElement) {
            var performanceForm = eventButton.closest('[data-e2n-performance-form]');
            if (!performanceForm) return;
            if ((performanceForm.dataset.performanceId || '0') === '0') {
                var elapsedTime = performanceForm.querySelector('[name="elapsed_time"]');
                var comment = performanceForm.querySelector('[name="comment"]');
                var disqualified = performanceForm.querySelector('[name="is_disqualified"]');
                if (elapsedTime) elapsedTime.value = '';
                if (comment) comment.value = '';
                if (disqualified instanceof HTMLInputElement) disqualified.checked = false;
                performanceForm.querySelectorAll('[name="time_rating"]').forEach(function (radio) { radio.checked = false; });
            }
            performanceForm.querySelector('[data-e2n-event-value]').value = eventButton.dataset.e2nEvent || '';
            performanceForm.querySelectorAll('[data-e2n-event]').forEach(function (button) {
                button.classList.toggle('is-selected', button === eventButton);
                button.hidden = button !== eventButton;
            });
            performanceForm.querySelectorAll('.e2n-event-row').forEach(function (row) { row.hidden = !row.contains(eventButton); });
            performanceForm.querySelector('[data-e2n-performance-fields]').hidden = false;
            return;
        }
        var cancelEvent = event.target.closest('[data-e2n-event-cancel]');
        if (cancelEvent instanceof HTMLButtonElement) {
            var cancelForm = cancelEvent.closest('[data-e2n-performance-form]');
            if (!cancelForm) return;
            cancelForm.reset();
            cancelForm.querySelector('[data-e2n-event-value]').value = '';
            cancelForm.querySelectorAll('[data-e2n-event]').forEach(function (button) { button.hidden = false; button.classList.remove('is-selected'); });
            cancelForm.querySelectorAll('.e2n-event-row').forEach(function (row) { row.hidden = false; });
            cancelForm.querySelector('[data-e2n-performance-fields]').hidden = true;
            return;
        }
        var sortButton = event.target.closest('[data-e2n-competition-sort]');
        if (sortButton instanceof HTMLButtonElement) sortCompetitionRows(sortButton);
    });

    document.querySelectorAll('[data-e2n-performance-form]').forEach(function (form) {
        var selected = form.querySelector('[data-e2n-event].is-selected');
        if (!selected) return;
        form.querySelectorAll('[data-e2n-event]').forEach(function (button) { button.hidden = button !== selected; });
        form.querySelectorAll('.e2n-event-row').forEach(function (row) { row.hidden = !row.contains(selected); });
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
        Object.keys(payload).forEach(function (key) {
            var value = payload[key];
            if (Array.isArray(value)) value.forEach(function (item) { body.append(key, item); });
            else body.append(key, value);
        });
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
        var showButton = event.target.closest('[data-e2n-show-parent-code]');
        if (showButton instanceof HTMLButtonElement) {
            var showStatus = document.querySelector('[data-e2n-parent-code-status]');
            showButton.disabled = true;
            if (showStatus) {
                showStatus.className = 'e2n-parent-code-status is-saving';
                showStatus.textContent = e2nCoachAjax.loadingParentCode;
            }
            post({
                action: 'e2n_coach_get_parent_code',
                group_id: showButton.dataset.groupId,
                swimmer_id: showButton.dataset.swimmerId
            }).then(function (json) {
                if (showStatus) {
                    showStatus.className = 'e2n-parent-code-status is-saved';
                    showStatus.textContent = json.data.message;
                }
                if (navigator.clipboard && json.data.code) navigator.clipboard.writeText(json.data.code).catch(function () {});
            }).catch(function (error) {
                if (showStatus) {
                    showStatus.className = 'e2n-parent-code-status is-error';
                    showStatus.textContent = error.message || e2nCoachAjax.error;
                }
            }).finally(function () { showButton.disabled = false; });
            return;
        }
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
        if (input instanceof HTMLInputElement && input.dataset.e2nKind === 'category-visibility') {
            var section = document.querySelector('[data-e2n-category-section="' + input.value + '"]');
            if (section) section.hidden = !input.checked;
            var hiddenCategories = Array.from(document.querySelectorAll('[data-e2n-kind="category-visibility"]:not(:checked)')).map(function (checkbox) { return checkbox.value; });
            var categoryStatus = document.querySelector('[data-e2n-category-filter-status]');
            if (categoryStatus) categoryStatus.textContent = e2nCoachAjax.saving;
            post({ action: 'e2n_coach_save_category_visibility', 'hidden_categories[]': hiddenCategories }).then(function () {
                if (categoryStatus) categoryStatus.textContent = e2nCoachAjax.saved;
            }).catch(function (error) {
                input.checked = !input.checked;
                if (section) section.hidden = !input.checked;
                if (categoryStatus) categoryStatus.textContent = error.message || e2nCoachAjax.error;
            });
            return;
        }
        if (input instanceof HTMLInputElement && input.dataset.e2nKind === 'competition-response') {
            queueSave('competition:' + input.dataset.competitionId + ':' + input.dataset.swimmerId, {
                action: 'e2n_coach_save_competition_response', competition_id: input.dataset.competitionId,
                swimmer_id: input.dataset.swimmerId, response: input.value
            }, input);
            var competitionRow = input.closest('.e2n-competition-swimmer');
            var engagedInput = competitionRow ? competitionRow.querySelector('[data-e2n-kind="competition-engaged"]') : null;
            if (engagedInput instanceof HTMLInputElement) { engagedInput.disabled = input.value !== 'yes'; if (input.value !== 'yes') engagedInput.checked = false; }
            if (competitionRow) updateCompetitionRow(competitionRow);
            var activeResponseSort = competitionRow ? competitionRow.closest('.e2n-card').querySelector('[data-e2n-competition-sort].is-active') : null;
            if (activeResponseSort instanceof HTMLButtonElement && activeResponseSort.dataset.e2nCompetitionSort === 'status') sortCompetitionRows(activeResponseSort);
            return;
        }
        if (input instanceof HTMLInputElement && input.dataset.e2nKind === 'competition-engaged') {
            queueSave('competition:' + input.dataset.competitionId + ':' + input.dataset.swimmerId, {
                action: 'e2n_coach_set_competition_engaged', competition_id: input.dataset.competitionId,
                swimmer_id: input.dataset.swimmerId, engaged: input.checked ? '1' : ''
            }, input);
            var engagementRow = input.closest('.e2n-competition-swimmer');
            if (engagementRow) updateCompetitionRow(engagementRow);
            var activeEngagementSort = engagementRow ? engagementRow.closest('.e2n-card').querySelector('[data-e2n-competition-sort].is-active') : null;
            if (activeEngagementSort instanceof HTMLButtonElement && activeEngagementSort.dataset.e2nCompetitionSort === 'status') sortCompetitionRows(activeEngagementSort);
            return;
        }
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
