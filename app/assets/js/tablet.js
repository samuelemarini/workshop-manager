import '../styles/tablet.scss';
require('@fortawesome/fontawesome-free/css/all.min.css');
require('@fontsource/barlow/400.css');
require('@fontsource/barlow/600.css');
require('@fontsource/barlow/700.css');
require('@fontsource/barlow-condensed/700.css');

const token = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
const pad = (n) => String(n).padStart(2, '0');

function post(url, data) {
    const body = new FormData();
    body.append('_token', token());
    Object.entries(data || {}).forEach(([k, v]) => body.append(k, v));
    return fetch(url, { method: 'POST', body, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then((r) => (r.ok ? r.json() : Promise.reject(r)));
}

/* Cronometri: [data-timer]=secondi già lavorati, data-paused = fermo, data-format hm | hms */
function initTimers() {
    const started = Date.now();
    const timers = [...document.querySelectorAll('[data-timer]')];
    const delta = document.querySelector('[data-delta]');
    const progress = document.querySelector('[data-progress]');
    const clocks = document.querySelectorAll('[data-clock]');
    const tick = () => {
        const elapsed = Math.floor((Date.now() - started) / 1000);
        timers.forEach((el) => {
            const s = parseInt(el.dataset.timer, 10) + (el.hasAttribute('data-paused') ? 0 : elapsed);
            const h = Math.floor(s / 3600);
            const m = Math.floor((s % 3600) / 60);
            el.textContent = el.dataset.format === 'hms' ? `${h}:${pad(m)}:${pad(s % 60)}` : `${h}:${pad(m)}`;
            if (el.dataset.estimate) {
                const est = parseInt(el.dataset.estimate, 10);
                el.classList.toggle('is-over', est > 0 && s > est);
                if (delta && est > 0) {
                    const d = Math.abs(est - s);
                    delta.textContent = (s > est ? 'oltre la stima di ' : 'mancano ') + `${Math.floor(d / 3600)}:${pad(Math.floor((d % 3600) / 60))}`;
                    delta.style.color = s > est ? '#ff8a9c' : '';
                }
                if (progress && est > 0) progress.style.width = Math.min(100, (s / est) * 100) + '%';
            }
        });
        clocks.forEach((c) => {
            const [h, m, sec] = c.dataset.clock.split(':').map(Number);
            const t = (h * 3600 + m * 60 + sec + elapsed) % 86400;
            c.textContent = `${Math.floor(t / 3600)}:${pad(Math.floor((t % 3600) / 60))}`;
        });
    };
    tick();
    setInterval(tick, 1000);
}

/* Fogli a comparsa */
function initSheets() {
    document.addEventListener('click', (e) => {
        const open = e.target.closest('[data-sheet-open]');
        if (open) {
            document.querySelector(open.dataset.sheetOpen).hidden = false;
            return;
        }
        if (e.target.closest('[data-sheet-close]') || e.target.classList.contains('op-sheet-bg')) {
            e.target.closest('.op-sheet-bg').hidden = true;
        }
    });
}

/* Schede Controlli / Ricambi / Note: senza ricaricare la pagina */
function initTabs() {
    document.querySelectorAll('[data-tab]').forEach((btn) => btn.addEventListener('click', () => {
        document.querySelectorAll('[data-tab]').forEach((b) => b.classList.toggle('on', b === btn));
        document.querySelectorAll('[data-pane]').forEach((p) => { p.hidden = p.dataset.pane !== btn.dataset.tab; });
        const url = new URL(window.location);
        url.searchParams.set('tab', btn.dataset.tab);
        window.history.replaceState(null, '', url);
    }));
}

/* Spunta dei controlli: si salva subito */
function initChecklist() {
    document.querySelectorAll('[data-check-url]').forEach((btn) => btn.addEventListener('click', () => {
        btn.classList.add('is-saving');
        btn.classList.toggle('on');
        post(btn.dataset.checkUrl).then((r) => {
            btn.classList.toggle('on', r.done);
            btn.setAttribute('aria-pressed', r.done ? 'true' : 'false');
            const count = document.querySelector('[data-check-count]');
            if (count) count.textContent = `${r.count}/${r.total}`;
        }).catch(() => btn.classList.toggle('on')).finally(() => btn.classList.remove('is-saving'));
    }));
}

/* Quantità dei ricambi */
function initQuantities() {
    document.querySelectorAll('[data-qty-url]').forEach((box) => box.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-delta]');
        if (!btn) return;
        post(box.dataset.qtyUrl, { delta: btn.dataset.delta }).then((r) => {
            box.querySelector('[data-qty]').textContent = String(r.qty).replace('.', ',');
        });
    }));
}

/* "Termina" chiede un secondo tocco per evitare chiusure per errore */
function initConfirmTap() {
    document.querySelectorAll('form[data-confirm-tap]').forEach((form) => {
        const btn = form.querySelector('button');
        const label = btn.innerHTML;
        form.addEventListener('submit', (e) => {
            if (btn.classList.contains('is-armed')) return;
            e.preventDefault();
            btn.classList.add('is-armed');
            btn.textContent = form.dataset.confirmTap;
            setTimeout(() => { btn.classList.remove('is-armed'); btn.innerHTML = label; }, 4000);
        });
    });
}

/* PIN dei meccanici */
function initPin() {
    const sheet = document.getElementById('pinSheet');
    if (!sheet) return;
    const dots = sheet.querySelectorAll('.op-pin-dots i');
    let form = null;
    let pin = '';
    const draw = () => dots.forEach((d, i) => d.classList.toggle('on', i < pin.length));
    document.querySelectorAll('form[data-pin-form]').forEach((f) => f.addEventListener('submit', (e) => {
        if (f.pin.value.length === 4) return;
        e.preventDefault();
        form = f;
        pin = '';
        draw();
        sheet.hidden = false;
    }));
    sheet.addEventListener('click', (e) => {
        const digit = e.target.closest('[data-digit]');
        if (digit && pin.length < 4) {
            pin += digit.dataset.digit;
            draw();
            if (pin.length === 4) {
                form.pin.value = pin;
                form.requestSubmit ? form.requestSubmit() : form.submit();
            }
        }
        if (e.target.closest('[data-pin-back]')) { pin = pin.slice(0, -1); draw(); }
        if (e.target.closest('[data-pin-cancel]')) sheet.hidden = true;
    });
}

/* Foto: si caricano appena scattate */
function initUploads() {
    document.querySelectorAll('[data-autosubmit]').forEach((input) => input.addEventListener('change', () => {
        if (input.files.length) input.form.submit();
    }));
    document.querySelectorAll('input[type=file]:not([data-autosubmit])').forEach((input) => input.addEventListener('change', () => {
        const label = input.closest('label')?.querySelector('[data-file-label]');
        if (label) label.textContent = input.files.length ? 'Foto pronta' : 'Aggiungi foto';
    }));
}

/* Tablet condiviso: dopo qualche minuto senza tocchi si torna alla scelta del meccanico
   (il cronometro continua: i tempi sono sul server) */
function initAutoLogout() {
    const form = document.getElementById('autoLogout');
    if (!form) return;
    let last = Date.now();
    ['pointerdown', 'keydown', 'input'].forEach((ev) => document.addEventListener(ev, () => { last = Date.now(); }, true));
    setInterval(() => {
        const sheetOpen = [...document.querySelectorAll('.op-sheet-bg')].some((s) => !s.hidden);
        if (!sheetOpen && Date.now() - last > 3 * 60 * 1000) form.submit();
    }, 10000);
}

document.addEventListener('DOMContentLoaded', () => {
    initTimers();
    initSheets();
    initTabs();
    initChecklist();
    initQuantities();
    initConfirmTap();
    initPin();
    initUploads();
    initAutoLogout();
    setTimeout(() => document.querySelectorAll('.op-toast').forEach((t) => t.remove()), 3500);
});
