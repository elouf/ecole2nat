(function () {
    'use strict';

    function normalize(value) {
        return String(value || '')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function sortableValue(cell) {
        if (!cell) {
            return { type: 'text', value: '' };
        }

        var explicit = cell.getAttribute('data-sort-value');
        var text = normalize(explicit !== null ? explicit : cell.textContent);

        if (text === '' || text === '—') {
            return { type: 'empty', value: '' };
        }

        var frenchDate = text.match(/^(\d{2})\/(\d{2})\/(\d{4})(?:\s+(\d{2}):(\d{2}))?$/);
        if (frenchDate) {
            return {
                type: 'number',
                value: Date.UTC(
                    parseInt(frenchDate[3], 10),
                    parseInt(frenchDate[2], 10) - 1,
                    parseInt(frenchDate[1], 10),
                    parseInt(frenchDate[4] || '0', 10),
                    parseInt(frenchDate[5] || '0', 10)
                )
            };
        }

        var isoDate = text.match(/^(\d{4})-(\d{2})-(\d{2})$/);
        if (isoDate) {
            return {
                type: 'number',
                value: Date.UTC(
                    parseInt(isoDate[1], 10),
                    parseInt(isoDate[2], 10) - 1,
                    parseInt(isoDate[3], 10)
                )
            };
        }

        var numeric = text.replace(/\s/g, '').replace(',', '.');
        if (/^-?\d+(?:\.\d+)?$/.test(numeric)) {
            return { type: 'number', value: parseFloat(numeric) };
        }

        var numericWithUnit = text.match(/^(-?\d+(?:[.,]\d+)?)\s+(?:min(?:ute)?s?|nageurs?|groupes?|parties?|exercices?|compétences?)$/i);
        if (numericWithUnit) {
            return { type: 'number', value: parseFloat(numericWithUnit[1].replace(',', '.')) };
        }

        return { type: 'text', value: text.toLocaleLowerCase('fr-FR') };
    }

    function compareValues(a, b, direction) {
        if (a.type === 'empty' && b.type !== 'empty') {
            return 1;
        }
        if (b.type === 'empty' && a.type !== 'empty') {
            return -1;
        }

        var result;
        if (a.type === 'number' && b.type === 'number') {
            result = a.value - b.value;
        } else {
            result = String(a.value).localeCompare(String(b.value), 'fr', {
                numeric: true,
                sensitivity: 'base'
            });
        }

        return direction === 'desc' ? -result : result;
    }

    function isSortableHeader(header) {
        if (header.classList.contains('check-column')) {
            return false;
        }
        if (header.querySelector('input, button')) {
            return false;
        }
        // Server-side sortable headers already contain their own links.
        if (header.querySelector('a')) {
            return false;
        }

        var label = normalize(header.textContent).toLocaleLowerCase('fr-FR');
        return label !== '' && label !== 'action' && label !== 'actions';
    }

    function initTable(table) {
        if (table.getAttribute('data-e2n-sort') === 'server' || table.getAttribute('data-e2n-sort') === 'off') {
            return;
        }

        var body = table.tBodies && table.tBodies[0];
        var head = table.tHead;
        if (!head || !body || body.rows.length < 2) {
            return;
        }

        // Tables used as editable forms keep their pedagogical/manual order.
        if (body.querySelector('select, textarea, input:not([type=\"checkbox\"]):not([type=\"hidden\"])')) {
            return;
        }

        var headers = Array.prototype.slice.call(head.rows[head.rows.length - 1].cells);
        var hasSortable = false;

        headers.forEach(function (header, index) {
            if (!isSortableHeader(header)) {
                return;
            }

            hasSortable = true;
            header.setAttribute('data-e2n-sortable', '1');
            header.setAttribute('tabindex', '0');
            header.setAttribute('role', 'button');
            header.setAttribute('aria-sort', 'none');
            header.setAttribute('title', 'Cliquer pour trier');

            var indicator = document.createElement('span');
            indicator.className = 'e2n-sort-indicator';
            indicator.setAttribute('aria-hidden', 'true');
            indicator.textContent = '↕';
            header.appendChild(indicator);

            function sort() {
                var current = header.getAttribute('aria-sort');
                var direction = current === 'ascending' ? 'desc' : 'asc';
                var rows = Array.prototype.slice.call(body.rows);

                rows.sort(function (rowA, rowB) {
                    var a = sortableValue(rowA.cells[index]);
                    var b = sortableValue(rowB.cells[index]);
                    return compareValues(a, b, direction);
                });

                headers.forEach(function (other) {
                    if (other.getAttribute('data-e2n-sortable') === '1') {
                        other.setAttribute('aria-sort', 'none');
                        var otherIndicator = other.querySelector('.e2n-sort-indicator');
                        if (otherIndicator) {
                            otherIndicator.textContent = '↕';
                        }
                    }
                });

                header.setAttribute('aria-sort', direction === 'asc' ? 'ascending' : 'descending');
                indicator.textContent = direction === 'asc' ? '▲' : '▼';

                rows.forEach(function (row) {
                    body.appendChild(row);
                });
            }

            header.addEventListener('click', sort);
            header.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    sort();
                }
            });
        });

        if (hasSortable) {
            table.classList.add('e2n-sortable-table');
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.wrap table.widefat:not(.form-table)').forEach(initTable);
    });
}());
