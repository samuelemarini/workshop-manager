import $ from 'jquery';
import { t, separators, formatNumber } from './js/i18n.js';
import './styles/app.scss';

window.jQuery = window.$ = $;
window.bootstrap = require('bootstrap/dist/js/bootstrap.bundle.js');
require('bootstrap-select');
require('bootstrap-select/dist/css/bootstrap-select.min.css');
require('@fortawesome/fontawesome-free/css/all.min.css');
require('@fontsource/barlow/400.css');
require('@fontsource/barlow/500.css');
require('@fontsource/barlow/600.css');
require('@fontsource/barlow/700.css');
require('@fontsource/barlow-condensed/600.css');
require('@fontsource/barlow-condensed/700.css');

import DataTable from 'datatables.net-dt';
require('datatables.net-dt/css/dataTables.dataTables.min.css');
DataTable.use($);

// testi letti quando servono (non al caricamento del modulo), separatori della lingua della pagina
const dtLanguage = () => ({
    emptyTable: t('Nessun dato presente'),
    info: t('%start%–%end% di %total%', { start: '_START_', end: '_END_', total: '_TOTAL_' }),
    infoEmpty: t('Nessun elemento'),
    infoFiltered: t('(filtrati da %max%)', { max: '_MAX_' }),
    zeroRecords: t('Nessun risultato'),
    decimal: separators().decimal,
    thousands: separators().group,
    paginate: { first: '«', previous: '‹', next: '›', last: '»' },
    aria: {
        orderable: t(': attiva per ordinare'),
        orderableReverse: t(': attiva per invertire l’ordine'),
        orderableRemove: t(': attiva per togliere l’ordinamento'),
        paginate: { first: t('Prima pagina'), previous: t('Pagina precedente'), next: t('Pagina successiva'), last: t('Ultima pagina') },
    },
});

/* --------------------------------------------------------------------------
   Inizializzazione dei componenti dentro un contenitore (pagina o modale)
   -------------------------------------------------------------------------- */
function initComponents(root) {
    const $root = $(root);
    $root.find('select.selectpicker').selectpicker({ noneSelectedText: '—', liveSearchPlaceholder: t('Cerca...'), noneResultsText: t('Nessun risultato per %term%', { term: '{0}' }) });
    $root.find('[data-bs-toggle="tooltip"]').each((i, el) => new bootstrap.Tooltip(el));
    // il calendario si apre cliccando ovunque nel campo, non solo sull'icona
    $root.find('input[type=date], input[type=datetime-local], input[type=time]').on('click', function () {
        try { this.showPicker(); } catch (e) { /* browser senza showPicker */ }
    });
}

/* --------------------------------------------------------------------------
   Tabelle: DataTables lato client + casella di ricerca + filtri a chip.
   <table class="app-table" data-datatable data-order='[[0,"desc"]]'>
   <input data-table-search="#id">   <div class="filter-chips" data-table-filter="#id">
   Le righe con data-filter="a b" passano il filtro chip con data-value="a".
   -------------------------------------------------------------------------- */
function initTables() {
    $('table[data-datatable]').each(function () {
        const table = this;
        const $table = $(table);
        const dt = new DataTable(table, {
            pageLength: 25,
            lengthChange: false,
            order: $table.data('order') || [],
            paging: $table.find('tbody tr').length > 25,
            info: $table.find('tbody tr').length > 25,
            language: dtLanguage(),
            columnDefs: [{ targets: 'no-sort', orderable: false }],
            layout: { topStart: null, topEnd: null, bottomStart: 'info', bottomEnd: 'paging' },
        });

        $(`[data-table-search="#${table.id}"]`).on('input', function () {
            dt.search(this.value).draw();
        });

        const $chips = $(`[data-table-filter="#${table.id}"]`);
        if ($chips.length) {
            let current = $chips.find('.active').data('value') || '';
            DataTable.ext.search.push((settings, data, index) => {
                if (settings.nTable !== table || !current) return true;
                const tokens = (dt.row(index).node().dataset.filter || '').split(' ');
                return tokens.includes(current);
            });
            $chips.on('click', 'button', function () {
                $chips.find('button').removeClass('active');
                $(this).addClass('active');
                current = $(this).data('value') || '';
                const url = new URL(window.location);
                url.searchParams.set('filter', current || 'all');
                window.history.replaceState(null, '', url);
                dt.draw();
            });
            dt.draw();
        }
    });
}

/* --------------------------------------------------------------------------
   Modale con form caricato via AJAX: <a data-modal="url" data-title="...">
   Il submit avviene in fetch: se il server reindirizza si segue il redirect,
   altrimenti (errori di validazione) si ridisegna il contenuto della modale.
   -------------------------------------------------------------------------- */
const modalEl = () => document.getElementById('appModal');
// URL da cui è stato caricato il contenuto: i form senza action si inviano lì, non alla pagina sotto
let modalUrl = null;

function openModal(url, title, size) {
    modalUrl = url;
    const el = modalEl();
    el.querySelector('.modal-title').textContent = title || '';
    el.querySelector('.modal-dialog').className = 'modal-dialog modal-dialog-centered modal-dialog-scrollable ' + (size || 'modal-lg');
    const body = el.querySelector('.modal-body');
    body.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>';
    bootstrap.Modal.getOrCreateInstance(el).show();

    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then((r) => r.text())
        .then((html) => renderModal(html));
}

function renderModal(html) {
    const body = modalEl().querySelector('.modal-body');
    body.innerHTML = html;
    initComponents(body);
    const first = body.querySelector('input:not([type=hidden]), select, textarea');
    if (first) setTimeout(() => first.focus(), 200);
}

$(document).on('click', '[data-modal]', function (e) {
    e.preventDefault();
    openModal($(this).data('modal'), $(this).data('title'), $(this).data('size'));
});

$(document).on('submit', '#appModal form:not([data-native])', function (e) {
    e.preventDefault();
    const form = this;
    showLoader();
    // form.action senza attributo vale l'URL della pagina: si usa l'attributo vero
    fetch(form.getAttribute('action') || modalUrl || window.location.href, {
        method: 'POST',
        body: new FormData(form),
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
    }).then((r) => {
        if (r.redirected) {
            window.location.href = r.url;
            return null;
        }
        return r.text();
    }).then((html) => {
        if (html !== null) {
            hideLoader();
            renderModal(html);
        }
    }).catch(() => hideLoader());
});

/* --------------------------------------------------------------------------
   Conferma prima dell'invio: <form data-confirm="Sei sicuro?">
   -------------------------------------------------------------------------- */
$(document).on('submit', 'form[data-confirm]', function (e) {
    const form = this;
    if (form.dataset.confirmed) return;
    e.preventDefault();
    const el = document.getElementById('confirmModal');
    el.querySelector('.confirm-text').textContent = form.dataset.confirm;
    const modal = bootstrap.Modal.getOrCreateInstance(el);
    $(el).find('.confirm-yes').off('click').on('click', () => {
        form.dataset.confirmed = '1';
        modal.hide();
        form.requestSubmit ? form.requestSubmit() : form.submit();
    });
    modal.show();
});

/* --------------------------------------------------------------------------
   Collezioni di form (righe spedizione, righe picking)
   <div data-collection data-prototype="..." data-index="n"> ... [data-collection-add]
   -------------------------------------------------------------------------- */
$(document).on('click', '[data-collection-add]', function () {
    const $holder = $($(this).data('collection-add'));
    const index = parseInt($holder.attr('data-index'), 10);
    const html = $holder.data('prototype').replace(/__name__/g, index);
    $holder.attr('data-index', index + 1);
    const $row = $(`<div class="collection-row"><div class="fields">${html}</div>
        <button type="button" class="btn btn-light btn-icon" data-collection-remove title="${t('Rimuovi')}" aria-label="${t('Rimuovi')}"><i class="fa fa-trash"></i></button></div>`);
    $holder.append($row);
    initComponents($row);
});

$(document).on('click', '[data-collection-remove]', function () {
    $(this).closest('.collection-row').remove();
});

/* --------------------------------------------------------------------------
   Filtri a chip su liste semplici (non DataTables):
   <div class="filter-chips" data-list-filter="#lista"> + figli con data-filter="valore"
   -------------------------------------------------------------------------- */
function initListFilters() {
    $('[data-list-filter]').each(function () {
        const $chips = $(this);
        const $items = $($chips.data('list-filter')).children('[data-filter]');
        $chips.on('click', 'button', function () {
            $chips.find('button').removeClass('active');
            $(this).addClass('active');
            const value = String($(this).data('value') || '');
            $items.each(function () {
                this.hidden = value !== '' && this.dataset.filter !== value;
            });
        });
    });
}

/* --------------------------------------------------------------------------
   Schede Bootstrap: #ancora nell'URL apre la scheda giusta (es. dopo il salvataggio
   di un intervento si torna su "Interventi"), e la scheda scelta resta nell'URL.
   -------------------------------------------------------------------------- */
function initTabsFromHash() {
    const tabs = document.querySelectorAll('[data-bs-toggle="tab"]');
    if (!tabs.length) return;
    const fromHash = window.location.hash && document.querySelector(`[data-bs-toggle="tab"][data-bs-target="${window.location.hash}"]`);
    if (fromHash) bootstrap.Tab.getOrCreateInstance(fromHash).show();
    tabs.forEach((tab) => tab.addEventListener('shown.bs.tab', () => {
        window.history.replaceState(null, '', tab.dataset.bsTarget);
    }));
}

/* --------------------------------------------------------------------------
   Varie
   -------------------------------------------------------------------------- */
function showLoader() { document.querySelector('.loader-overlay')?.classList.add('show'); }
function hideLoader() { document.querySelector('.loader-overlay')?.classList.remove('show'); }

$(document).on('click', 'tr[data-href]', function (e) {
    if ($(e.target).closest('a, button, form, input, select, .dropdown').length) return;
    window.location.href = this.dataset.href;
});

$(document).on('submit', 'form[data-loader]', showLoader);

$(function () {
    const sidebar = document.getElementById('sidebar');
    const toggle = document.getElementById('sidebarToggle');
    if (sidebar && toggle) {
        const overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        document.body.appendChild(overlay);
        const flip = () => {
            const open = sidebar.classList.toggle('active');
            overlay.classList.toggle('active', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        };
        toggle.addEventListener('click', flip);
        overlay.addEventListener('click', flip);
    }

    initComponents(document);
    initTables();
    initListFilters();
    initTabsFromHash();
    initTimers();
    initAcceptance();
    initPlanningDrag();
    initWeekDrag();
    initBoard();
    initJobs();

    // i messaggi di successo spariscono da soli
    setTimeout(() => $('.flash-stack .alert-success').fadeOut(400), 5000);
});

/* --------------------------------------------------------------------------
   Cronometri delle lavorazioni in corso: [data-timer]=secondi, data-format hm | hms
   -------------------------------------------------------------------------- */
function initTimers() {
    const timers = document.querySelectorAll('[data-timer]:not([data-paused])');
    if (!timers.length) return;
    const started = Date.now();
    const pad = (n) => String(n).padStart(2, '0');
    setInterval(() => {
        const elapsed = Math.floor((Date.now() - started) / 1000);
        timers.forEach((el) => {
            const s = parseInt(el.dataset.timer, 10) + elapsed;
            const base = `${Math.floor(s / 3600)}:${pad(Math.floor((s % 3600) / 60))}`;
            el.textContent = el.dataset.format === 'hms' ? `${base}:${pad(s % 60)}` : base;
        });
    }, 1000);
}

/* --------------------------------------------------------------------------
   Lavorazione tipo nella modale: precompila titolo, tempo e controlli
   -------------------------------------------------------------------------- */
$(document).on('change', 'select[data-template-select]', function () {
    const opt = this.selectedOptions[0];
    const form = this.form;
    if (!opt || !opt.value) return;
    const set = (suffix, value) => {
        const field = form.querySelector(`[name$="[${suffix}]"]`);
        if (field) field.value = value;
    };
    set('title', opt.textContent.trim());
    set('estimatedMinutes', opt.dataset.minutes);
    set('checklistText', opt.dataset.checklist || '');
});

/* --------------------------------------------------------------------------
   Accettazione: veicolo noto → cliente proprietario; campi "nuovo" solo se servono;
   targa scritta a mano già in anagrafica → si sceglie il veicolo esistente
   -------------------------------------------------------------------------- */
function initAcceptance() {
    const form = document.querySelector('form[data-acceptance]');
    if (!form) return;
    const $vehicle = $(form).find('select[data-vehicle-select]');
    const $customer = $(form).find('select[data-customer-select]');
    const newVehicle = form.querySelector('[data-new-vehicle]');
    const newCustomer = form.querySelector('[data-new-customer]');
    const sync = () => {
        newVehicle.hidden = !!$vehicle.val();
        newCustomer.hidden = !!$customer.val();
    };
    $vehicle.on('changed.bs.select change', () => {
        const opt = $vehicle.find('option:selected')[0];
        if (opt && opt.dataset.owner && !$customer.val()) {
            $customer.selectpicker('val', opt.dataset.owner);
        }
        const km = form.querySelector('[name$="[kmIn]"]');
        if (opt && opt.dataset.km && km && !km.value) km.placeholder = t('ultimi letti %km%', { km: formatNumber(Number(opt.dataset.km), 0) });
        sync();
    });
    $customer.on('changed.bs.select change', sync);
    const plate = form.querySelector('[data-plate-lookup]');
    plate?.addEventListener('change', () => {
        fetch(`${form.dataset.lookupUrl}?plate=${encodeURIComponent(plate.value)}`)
            .then((r) => r.json())
            .then((v) => {
                if (!v?.id) return;
                $vehicle.selectpicker('val', String(v.id));
                if (v.owner && !$customer.val()) $customer.selectpicker('val', String(v.owner));
                plate.value = '';
                sync();
            });
    });
    sync();
}

/* --------------------------------------------------------------------------
   Planning: trascinamento di barre e lavorazioni da pianificare sulla griglia.
   La colonna d'arrivo dà l'orario (quarti d'ora), la riga il meccanico.
   -------------------------------------------------------------------------- */
function initPlanningDrag() {
    const grid = document.getElementById('planningGrid');
    if (!grid) return;
    const slot = parseInt(grid.dataset.slot, 10);
    const start = parseInt(grid.dataset.start, 10);
    let dragged = null;

    document.querySelectorAll('[draggable="true"][data-task]').forEach((el) => {
        el.addEventListener('dragstart', (e) => {
            dragged = el;
            el.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', el.dataset.task);
        });
        el.addEventListener('dragend', () => el.classList.remove('dragging'));
    });

    grid.querySelectorAll('[data-drop-row]').forEach((row) => {
        row.addEventListener('dragover', (e) => { if (dragged) { e.preventDefault(); row.classList.add('drop-over'); } });
        row.addEventListener('dragleave', () => row.classList.remove('drop-over'));
        row.addEventListener('drop', (e) => {
            e.preventDefault();
            row.classList.remove('drop-over');
            if (!dragged) return;
            const label = row.querySelector('.pg-label').getBoundingClientRect();
            const rect = row.getBoundingClientRect();
            const slots = parseInt(getComputedStyle(grid).getPropertyValue('--slots'), 10);
            const slotWidth = (rect.right - label.right) / slots;
            const index = Math.max(0, Math.min(slots - 1, Math.floor((e.clientX - label.right) / slotWidth)));
            const minute = start + index * slot;
            const data = { task: dragged.dataset.task, start: `${grid.dataset.day} ${String(Math.floor(minute / 60)).padStart(2, '0')}:${String(minute % 60).padStart(2, '0')}`, _token: grid.dataset.token };
            if ('mechanic' in row.dataset) data.mechanic = row.dataset.mechanic;
            const body = new FormData();
            Object.entries(data).forEach(([k, v]) => body.append(k, v));
            showLoader();
            fetch(grid.dataset.moveUrl, { method: 'POST', body, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then((r) => r.json())
                .then((r) => {
                    if (r.error) { hideLoader(); alert(r.error); } else { window.location.reload(); }
                })
                .catch(() => hideLoader());
            dragged = null;
        });
    });
}

/* --------------------------------------------------------------------------
   Bacheca: trascinamento fra colonne = cambio di stato, filtri per meccanico e ricerca
   -------------------------------------------------------------------------- */
function initBoard() {
    const board = document.getElementById('board');
    if (!board) return;
    let dragged = null;
    let pending = null;

    const refreshCounts = () => board.querySelectorAll('.kcol').forEach((col) => {
        const visible = [...col.querySelectorAll('.kcard')].filter((c) => !c.hidden).length;
        col.querySelector('.kcount').textContent = visible;
        col.querySelector('.kempty').hidden = visible > 0;
    });

    const move = (card, col, reason) => {
        const body = new FormData();
        body.append('_token', board.dataset.token);
        body.append('status', col.dataset.status);
        if (reason) body.append('reason', reason);
        const from = card.parentElement;
        col.insertBefore(card, col.querySelector('.kempty'));
        card.className = card.className.replace(/k-[A-Z_]+/, `k-${col.dataset.status}`);
        refreshCounts();
        fetch(card.dataset.statusUrl, { method: 'POST', body, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then((r) => { if (!r.ok) throw new Error(); })
            .catch(() => { from.insertBefore(card, from.querySelector('.kempty')); refreshCounts(); alert(t('Non è stato possibile cambiare lo stato.')); });
    };

    board.querySelectorAll('.kcard').forEach((card) => {
        card.addEventListener('dragstart', (e) => { dragged = card; card.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; });
        card.addEventListener('dragend', () => card.classList.remove('dragging'));
    });
    board.querySelectorAll('.kcol').forEach((col) => {
        col.addEventListener('dragover', (e) => { if (dragged) { e.preventDefault(); col.classList.add('drop-over'); } });
        col.addEventListener('dragleave', (e) => { if (!col.contains(e.relatedTarget)) col.classList.remove('drop-over'); });
        col.addEventListener('drop', (e) => {
            e.preventDefault();
            col.classList.remove('drop-over');
            if (!dragged || dragged.parentElement === col) return;
            if (col.dataset.status === 'WAITING') {
                pending = { card: dragged, col };
                bootstrap.Modal.getOrCreateInstance(document.getElementById('waitModal')).show();
            } else {
                move(dragged, col);
            }
            dragged = null;
        });
    });

    const waitModal = document.getElementById('waitModal');
    waitModal?.querySelector('form').addEventListener('submit', (e) => {
        e.preventDefault();
        if (!pending) return;
        const choice = waitModal.querySelector('input[name="reasonChoice"]:checked')?.value || '';
        const note = waitModal.querySelector('#waitNote').value.trim();
        move(pending.card, pending.col, note ? `${choice} · ${note}` : choice);
        pending = null;
        waitModal.querySelector('#waitNote').value = '';
        bootstrap.Modal.getInstance(waitModal).hide();
    });

    let who = '';
    let text = '';
    const filter = () => {
        board.querySelectorAll('.kcard').forEach((card) => {
            const tags = (card.dataset.filter || '').split(' ');
            card.hidden = (who && !tags.includes(who)) || (text && !card.dataset.search.includes(text));
        });
        refreshCounts();
    };
    document.getElementById('boardFilter')?.addEventListener('click', (e) => {
        const btn = e.target.closest('button');
        if (!btn) return;
        document.querySelectorAll('#boardFilter button').forEach((b) => b.classList.toggle('active', b === btn));
        who = btn.dataset.value || '';
        filter();
    });
    document.getElementById('boardSearch')?.addEventListener('input', (e) => { text = e.target.value.trim().toLowerCase(); filter(); });
}

/* --------------------------------------------------------------------------
   Lavori in officina: filtro per meccanico e ricerca, conteggi per sezione
   -------------------------------------------------------------------------- */
function initJobs() {
    const jobs = document.getElementById('jobs');
    if (!jobs) return;
    let who = '';
    let text = '';
    const filter = () => jobs.querySelectorAll('[data-jobs-section]').forEach((section) => {
        let visible = 0;
        section.querySelectorAll('.task-row').forEach((row) => {
            row.hidden = (who && row.dataset.filter !== who) || (text && !row.dataset.search.includes(text));
            if (!row.hidden) visible++;
        });
        section.querySelector('[data-jobs-count]').textContent = visible;
        section.querySelector('[data-jobs-empty]').hidden = visible > 0;
    });
    document.getElementById('jobsFilter').addEventListener('click', (e) => {
        const btn = e.target.closest('button');
        if (!btn) return;
        document.querySelectorAll('#jobsFilter button').forEach((b) => b.classList.toggle('active', b === btn));
        who = btn.dataset.value || '';
        filter();
    });
    document.getElementById('jobsSearch').addEventListener('input', (e) => { text = e.target.value.trim().toLowerCase(); filter(); });
}

/* --------------------------------------------------------------------------
   Calendario settimana: un lavoro trascinato su un altro giorno o meccanico
   mantiene l'orario (8:00 se non ne aveva uno)
   -------------------------------------------------------------------------- */
function initWeekDrag() {
    const grid = document.getElementById('weekGrid');
    if (!grid) return;
    let dragged = null;
    grid.querySelectorAll('.wk-item[draggable="true"]').forEach((el) => {
        el.addEventListener('dragstart', (e) => { dragged = el; el.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; });
        el.addEventListener('dragend', () => el.classList.remove('dragging'));
    });
    grid.querySelectorAll('[data-drop-day]').forEach((cell) => {
        cell.addEventListener('dragover', (e) => { if (dragged) { e.preventDefault(); cell.classList.add('drop-over'); } });
        cell.addEventListener('dragleave', () => cell.classList.remove('drop-over'));
        cell.addEventListener('drop', (e) => {
            e.preventDefault();
            cell.classList.remove('drop-over');
            if (!dragged) return;
            const [h, m] = dragged.dataset.time.split(':');
            const body = new FormData();
            body.append('_token', grid.dataset.token);
            body.append('task', dragged.dataset.task);
            body.append('start', `${cell.dataset.dropDay} ${h.padStart(2, '0')}:${m}`);
            body.append('mechanic', cell.dataset.mechanic);
            showLoader();
            fetch(grid.dataset.moveUrl, { method: 'POST', body, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then((r) => r.json())
                .then((r) => { if (r.error) { hideLoader(); alert(r.error); } else { window.location.reload(); } })
                .catch(() => hideLoader());
            dragged = null;
        });
    });
}

window.openModal = openModal;
window.showLoader = showLoader;
window.hideLoader = hideLoader;
