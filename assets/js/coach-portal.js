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

    document.querySelectorAll('[data-e2n-performance-report]').forEach(function (report) {
        var allButton = report.querySelector('[data-e2n-toggle-all-charts]');
        var chartPanels = Array.from(report.querySelectorAll('[data-e2n-event-chart]'));
        function updateButtons() {
            report.querySelectorAll('[data-e2n-toggle-chart]').forEach(function (button) {
                var panel = button.closest('.e2n-performance-event').querySelector('[data-e2n-event-chart]');
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
                var panel = button.closest('.e2n-performance-event').querySelector('[data-e2n-event-chart]');
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
        var deleteTime = event.target.closest('[data-e2n-delete-swimmer-time]');
        var purgeTimes = event.target.closest('[data-e2n-purge-swimmer-times]');
        var button = deleteTime || purgeTimes;
        if (!(button instanceof HTMLButtonElement)) return;
        var confirmation = deleteTime ? e2nCoachAjax.confirmDeleteSwimmerTime : e2nCoachAjax.confirmPurgeSwimmerTimes;
        if (!window.confirm(confirmation)) return;
        button.disabled = true;
        post(deleteTime ? {
            action: 'e2n_coach_delete_swimmer_performance',
            source: button.dataset.source || '',
            performance_id: button.dataset.performanceId || '0',
            group_id: button.dataset.groupId || '0',
            swimmer_id: button.dataset.swimmerId || '0'
        } : {
            action: 'e2n_coach_purge_swimmer_performances',
            group_id: button.dataset.groupId || '0',
            swimmer_id: button.dataset.swimmerId || '0'
        }).then(function () {
            window.location.reload();
        }).catch(function (error) {
            button.disabled = false;
            window.alert(error.message || e2nCoachAjax.error);
        });
    });

    document.querySelectorAll('[data-e2n-race-timer]').forEach(function (timer) {
        var contextType = timer.dataset.contextType || 'competition';
        var contextId = timer.dataset.contextId;
        var isTraining = contextType.indexOf('training') === 0;
        var eventSelect = timer.querySelector('[data-e2n-race-event]');
        var play = timer.querySelector('[data-e2n-race-play]');
        var reset = timer.querySelector('[data-e2n-race-reset]');
        var deleteSeries = timer.querySelector('[data-e2n-race-delete-series]');
        var viewToggle = timer.querySelector('[data-e2n-race-view-toggle]');
        var message = timer.querySelector('[data-e2n-race-message]');
        var selectors = Array.from(timer.querySelectorAll('[data-e2n-race-select]'));
        var cards = Array.from(timer.querySelectorAll('[data-e2n-race-card]'));
        var storageKey = 'e2n-race-' + contextType + '-' + contextId;
        var running = false;
        var startMark = 0;
        var startEpoch = 0;
        var frame = 0;
        var wakeLock = null;
        var saveTimers = new WeakMap();
        var seriesKey = '';
        var compactStorageKey = 'e2n-race-compact';

        function setCompactView(compact) {
            timer.classList.toggle('is-compact', compact);
            viewToggle.setAttribute('aria-pressed', compact ? 'true' : 'false');
            viewToggle.querySelector('[data-e2n-race-view-icon]').textContent = compact ? '▦' : '▤';
            viewToggle.querySelector('[data-e2n-race-view-label]').textContent = compact ? viewToggle.dataset.detailedLabel : viewToggle.dataset.compactLabel;
            try { localStorage.setItem(compactStorageKey, compact ? '1' : '0'); } catch (error) {}
        }

        viewToggle.addEventListener('click', function () { setCompactView(!timer.classList.contains('is-compact')); });
        try { setCompactView(localStorage.getItem(compactStorageKey) === '1'); } catch (error) { setCompactView(false); }

        function formatElapsed(milliseconds) {
            var centiseconds = Math.max(0, Math.floor(milliseconds / 10));
            var minutes = Math.floor(centiseconds / 6000);
            var seconds = Math.floor((centiseconds % 6000) / 100);
            var hundredths = centiseconds % 100;
            return minutes + ':' + String(seconds).padStart(2, '0') + '.' + String(hundredths).padStart(2, '0');
        }
        function cardFor(id) { return cards.find(function (card) { return card.dataset.swimmerId === String(id); }); }
        function selectedCards() { return cards.filter(function (card) { return !card.hidden; }); }
        function elapsed() { return running ? performance.now() - startMark : 0; }
        function updatePlayAvailability() { play.disabled = running || !eventSelect.value || selectedCards().length === 0; }
        function newSeriesKey() {
            if (window.crypto && typeof window.crypto.randomUUID === 'function') return window.crypto.randomUUID();
            return Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 14);
        }
        function updateDeleteActions() {
            var savedCards = cards.filter(function (card) { return card.dataset.performanceId !== '0'; });
            deleteSeries.hidden = savedCards.length === 0 || !seriesKey;
            cards.forEach(function (card) { card.querySelector('[data-e2n-race-delete-time]').hidden = card.dataset.performanceId === '0'; });
        }
        function persist() {
            if (!running && startEpoch > 0) { sessionStorage.removeItem(storageKey); return; }
            if (!eventSelect.value || selectedCards().length === 0) { sessionStorage.removeItem(storageKey); return; }
            var state = { event: eventSelect.value, running: running, startEpoch: startEpoch, seriesKey: seriesKey, swimmers: {} };
            selectedCards().forEach(function (card) {
                state.swimmers[card.dataset.swimmerId] = {
                    performanceId: card.dataset.performanceId || '0', stopped: card.classList.contains('is-stopped'),
                    time: card.querySelector('[data-e2n-race-time]').value,
                    comment: card.querySelector('[data-e2n-race-comment]').value,
                    dq: card.querySelector('[data-e2n-race-dq]').checked,
                    rating: (card.querySelector('input[type="radio"]:checked') || {}).value || ''
                };
            });
            sessionStorage.setItem(storageKey, JSON.stringify(state));
        }
        function tick() {
            if (!running) return;
            var value = formatElapsed(elapsed());
            selectedCards().forEach(function (card) {
                if (!card.classList.contains('is-stopped')) card.querySelector('[data-e2n-race-time]').value = value;
            });
            frame = window.requestAnimationFrame(tick);
        }
        function saveCard(card) {
            var time = card.querySelector('[data-e2n-race-time]').value;
            if (!time) return Promise.resolve();
            var status = card.querySelector('[data-e2n-race-card-status]');
            status.className = 'e2n-race-card-status is-saving'; status.textContent = e2nCoachAjax.saving;
            var payload = {
                action: isTraining ? 'e2n_coach_save_training_performance' : 'e2n_coach_save_timed_performance',
                swimmer_id: card.dataset.swimmerId, performance_id: card.dataset.performanceId || '0',
                event_code: eventSelect.value, elapsed_time: time,
                comment: card.querySelector('[data-e2n-race-comment]').value,
                is_disqualified: card.querySelector('[data-e2n-race-dq]').checked ? '1' : '',
                time_rating: (card.querySelector('input[type="radio"]:checked') || {}).value || '0'
            };
            payload.series_key = seriesKey;
            payload[isTraining ? 'group_id' : 'competition_id'] = isTraining ? (card.dataset.groupId || contextId) : contextId;
            return post(payload).then(function (json) {
                card.dataset.performanceId = String(json.data.performance_id);
                updateDeleteActions();
                status.className = 'e2n-race-card-status is-saved'; status.textContent = json.data.message;
                persist();
            }).catch(function (error) {
                status.className = 'e2n-race-card-status is-error'; status.textContent = error.message || e2nCoachAjax.error;
                card.querySelector('[data-e2n-race-stop]').disabled = false;
                throw error;
            });
        }
        function finishIfComplete() {
            if (selectedCards().every(function (card) { return card.classList.contains('is-stopped'); })) {
                running = false; window.cancelAnimationFrame(frame); reset.hidden = false;
                message.textContent = 'Série terminée.';
                if (wakeLock) wakeLock.release().catch(function () {});
                sessionStorage.removeItem(storageKey);
            }
        }
        function toggleCard(selector) {
            var card = cardFor(selector.value); if (!card) return;
            card.hidden = !selector.checked;
            if (!selector.checked) { card.classList.remove('is-stopped'); card.dataset.performanceId = '0'; }
            updatePlayAvailability(); persist();
        }
        function syncCategoryAvailability() {
            if (contextType !== 'training-categories' || running) return;
            var checkedCategories = new Set(Array.from(document.querySelectorAll('[data-e2n-kind="category-visibility"]:checked')).map(function (checkbox) { return checkbox.value; }));
            timer.querySelectorAll('[data-e2n-race-participant]').forEach(function (label) {
                var available = checkedCategories.has(label.dataset.categoryId || '');
                var selector = label.querySelector('[data-e2n-race-select]');
                if (!available && selector instanceof HTMLInputElement && selector.checked) {
                    selector.checked = false;
                    toggleCard(selector);
                }
                label.hidden = !available;
            });
        }
        selectors.forEach(function (selector) { selector.addEventListener('change', function () { toggleCard(selector); }); });
        document.addEventListener('e2n:category-visibility', syncCategoryAvailability);
        eventSelect.addEventListener('change', function () { updatePlayAvailability(); persist(); });
        play.addEventListener('click', function () {
            if (!eventSelect.value || selectedCards().length === 0) { message.textContent = e2nCoachAjax.selectRace; return; }
            if (!seriesKey) seriesKey = newSeriesKey();
            running = true; startMark = performance.now(); startEpoch = Date.now();
            eventSelect.disabled = true; selectors.forEach(function (selector) { selector.disabled = true; });
            selectedCards().forEach(function (card) { card.querySelector('[data-e2n-race-stop]').disabled = false; });
            play.disabled = true; reset.hidden = false; message.textContent = 'Chronomètre en cours…';
            if (navigator.wakeLock) navigator.wakeLock.request('screen').then(function (lock) { wakeLock = lock; }).catch(function () {});
            persist(); tick();
        });
        timer.addEventListener('click', function (event) {
            var deleteTime = event.target.closest('[data-e2n-race-delete-time]');
            if (deleteTime instanceof HTMLButtonElement) {
                var deleteCard = deleteTime.closest('[data-e2n-race-card]');
                if (!deleteCard || deleteCard.dataset.performanceId === '0' || !window.confirm(e2nCoachAjax.confirmDeleteRaceTime)) return;
                deleteTime.disabled = true;
                var deletePayload = { action: 'e2n_coach_delete_timed_performance', context_type: isTraining ? 'training' : 'competition', swimmer_id: deleteCard.dataset.swimmerId, performance_id: deleteCard.dataset.performanceId };
                deletePayload[isTraining ? 'group_id' : 'competition_id'] = isTraining ? (deleteCard.dataset.groupId || contextId) : contextId;
                post(deletePayload).then(function (json) {
                    deleteCard.dataset.performanceId = '0';
                    var deletedSelector = selectors.find(function (selector) { return selector.value === deleteCard.dataset.swimmerId; });
                    if (deletedSelector) deletedSelector.checked = false;
                    deleteCard.hidden = true;
                    deleteCard.classList.remove('is-stopped');
                    deleteCard.querySelector('[data-e2n-race-time]').value = '';
                    deleteCard.querySelector('[data-e2n-race-card-status]').textContent = '';
                    message.textContent = json.data.message;
                    updateDeleteActions(); persist();
                }).catch(function (error) {
                    deleteCard.querySelector('[data-e2n-race-card-status]').textContent = error.message || e2nCoachAjax.error;
                    deleteTime.disabled = false;
                });
                return;
            }
            var stop = event.target.closest('[data-e2n-race-stop]'); if (!(stop instanceof HTMLButtonElement)) return;
            var card = stop.closest('[data-e2n-race-card]');
            if (!card.classList.contains('is-stopped')) {
                card.querySelector('[data-e2n-race-time]').value = formatElapsed(elapsed());
                card.classList.add('is-stopped'); stop.disabled = true;
            }
            saveCard(card).finally(finishIfComplete);
        });
        deleteSeries.addEventListener('click', function () {
            if (!seriesKey || !window.confirm(e2nCoachAjax.confirmDeleteRaceSeries)) return;
            deleteSeries.disabled = true;
            var payload = { action: 'e2n_coach_delete_timed_series', context_type: isTraining ? 'training' : 'competition', series_key: seriesKey };
            if (!isTraining) payload.competition_id = contextId;
            post(payload).then(function () {
                running = false; window.cancelAnimationFrame(frame); deleteSeries.disabled = false;
                reset.click(); message.textContent = e2nCoachAjax.raceSeriesDeleted;
            }).catch(function (error) { deleteSeries.disabled = false; message.textContent = error.message || e2nCoachAjax.error; });
        });
        timer.addEventListener('input', function (event) {
            var card = event.target.closest('[data-e2n-race-card]');
            if (!card || card.dataset.performanceId === '0') { persist(); return; }
            window.clearTimeout(saveTimers.get(card));
            saveTimers.set(card, window.setTimeout(function () { saveCard(card).catch(function () {}); }, 500));
        });
        timer.addEventListener('change', function (event) {
            var card = event.target.closest('[data-e2n-race-card]');
            if (card && card.dataset.performanceId !== '0') saveCard(card).catch(function () {});
        });
        reset.addEventListener('click', function () {
            if (running && !window.confirm(e2nCoachAjax.confirmRaceReset)) return;
            running = false; window.cancelAnimationFrame(frame); sessionStorage.removeItem(storageKey);
            seriesKey = ''; startEpoch = 0; startMark = 0;
            eventSelect.disabled = false; eventSelect.value = ''; reset.hidden = true; message.textContent = '';
            selectors.forEach(function (selector) { selector.checked = false; selector.disabled = false; });
            cards.forEach(function (card) {
                card.hidden = true; card.classList.remove('is-stopped'); card.dataset.performanceId = '0';
                card.querySelector('[data-e2n-race-time]').value = ''; card.querySelector('[data-e2n-race-comment]').value = '';
                card.querySelector('[data-e2n-race-dq]').checked = false; card.querySelectorAll('input[type="radio"]').forEach(function (radio) { radio.checked = false; });
                card.querySelector('[data-e2n-race-card-status]').textContent = '';
            });
            updateDeleteActions();
            syncCategoryAvailability();
            updatePlayAvailability();
        });
        try {
            var restored = JSON.parse(sessionStorage.getItem(storageKey) || 'null');
            if (restored && !restored.running && restored.startEpoch > 0) {
                sessionStorage.removeItem(storageKey);
                restored = null;
            }
            if (restored && restored.event && restored.swimmers) {
                eventSelect.value = restored.event;
                seriesKey = restored.seriesKey || '';
                Object.keys(restored.swimmers).forEach(function (id) {
                    var selector = selectors.find(function (item) { return item.value === id; }); var card = cardFor(id); var saved = restored.swimmers[id];
                    if (!selector || !card) return; selector.checked = true; card.hidden = false; card.dataset.performanceId = saved.performanceId || '0';
                    card.querySelector('[data-e2n-race-time]').value = saved.time || ''; card.querySelector('[data-e2n-race-comment]').value = saved.comment || ''; card.querySelector('[data-e2n-race-dq]').checked = !!saved.dq;
                    var rating = card.querySelector('input[type="radio"][value="' + saved.rating + '"]'); if (rating) rating.checked = true;
                    if (saved.stopped) card.classList.add('is-stopped');
                });
                updateDeleteActions();
                if (restored.running) {
                    running = true; startEpoch = restored.startEpoch; startMark = performance.now() - Math.max(0, Date.now() - startEpoch);
                    eventSelect.disabled = true; selectors.forEach(function (selector) { selector.disabled = true; }); play.disabled = true; reset.hidden = false;
                    selectedCards().forEach(function (card) { card.querySelector('[data-e2n-race-stop]').disabled = card.classList.contains('is-stopped'); }); tick();
                } else { reset.hidden = false; updatePlayAvailability(); }
            }
        } catch (error) { sessionStorage.removeItem(storageKey); }
        syncCategoryAvailability();
        updateDeleteActions();
        updatePlayAvailability();
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
            document.dispatchEvent(new CustomEvent('e2n:category-visibility'));
            var hiddenCategories = Array.from(document.querySelectorAll('[data-e2n-kind="category-visibility"]:not(:checked)')).map(function (checkbox) { return checkbox.value; });
            var categoryStatus = document.querySelector('[data-e2n-category-filter-status]');
            if (categoryStatus) categoryStatus.textContent = e2nCoachAjax.saving;
            post({ action: 'e2n_coach_save_category_visibility', 'hidden_categories[]': hiddenCategories }).then(function () {
                if (categoryStatus) categoryStatus.textContent = e2nCoachAjax.saved;
            }).catch(function (error) {
                input.checked = !input.checked;
                if (section) section.hidden = !input.checked;
                document.dispatchEvent(new CustomEvent('e2n:category-visibility'));
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
