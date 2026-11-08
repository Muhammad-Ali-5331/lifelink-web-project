// LifeLink front-end: theme, countdown, confirmation dialog, live request filtering.

// ---------- 1. Dark mode (remembered in the browser) ----------
(function () {
    const saved = localStorage.getItem('theme');
    if (saved) document.documentElement.dataset.theme = saved;
})();

document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.getElementById('theme-toggle');
    if (toggle) toggle.addEventListener('click', () => {
        const next = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
        document.documentElement.dataset.theme = next;
        localStorage.setItem('theme', next);
    });

    // ---------- 2. Live eligibility countdown ----------
    document.querySelectorAll('[data-countdown]').forEach(el => {
        const target = new Date(el.dataset.countdown + 'T00:00:00');
        const days = Math.ceil((target - new Date()) / 86400000);
        if (days > 0) {
            el.textContent = 'You can donate again in ' + days + ' day' + (days === 1 ? '' : 's') +
                             ' (' + target.toDateString() + ').';
        } else {
            el.textContent = 'You are eligible to donate again. Refresh the page.';
        }
    });

    // ---------- 3. Confirmation modal for forms with data-confirm ----------
    const dialog = document.getElementById('confirm-dialog');
    let pendingForm = null;
    document.querySelectorAll('form[data-confirm]').forEach(form => {
        form.addEventListener('submit', ev => {
            if (form.dataset.ok) return;               // already confirmed
            ev.preventDefault();
            pendingForm = form;
            document.getElementById('confirm-text').textContent = form.dataset.confirm;
            dialog.showModal();
        });
    });
    if (dialog) {
        document.getElementById('confirm-no').addEventListener('click', () => dialog.close());
        document.getElementById('confirm-yes').addEventListener('click', () => {
            dialog.close();
            if (pendingForm) { pendingForm.dataset.ok = '1'; pendingForm.submit(); }
        });
    }

    // ---------- 4. Register page: show donor fields only for donors ----------
    const role = document.getElementById('role');
    const donorFields = document.getElementById('donor-fields');
    if (role && donorFields) {
        const sync = () => donorFields.style.display = role.value === 'donor' ? 'block' : 'none';
        role.addEventListener('change', sync);
        sync();
    }

    // ---------- 5. Donor dashboard: live filtering via the JSON endpoint ----------
    const list = document.getElementById('request-list');
    if (list) {
        const fGroup = document.getElementById('f-group');
        const fCity = document.getElementById('f-city');
        const csrf = list.dataset.csrf;

        const esc = s => String(s).replace(/[&<>"']/g,
            c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

        async function load() {
            const url = '/api/requests.php?blood_group=' + encodeURIComponent(fGroup.value) +
                        '&city_id=' + encodeURIComponent(fCity.value);
            try {
                const res = await fetch(url);
                const rows = await res.json();
                if (!Array.isArray(rows) || rows.length === 0) {
                    list.innerHTML = '<p class="muted">No matching open requests right now.</p>';
                    return;
                }
                list.innerHTML = rows.map(r => `
                    <div class="card request ${esc(r.urgency)}">
                        <div class="row between">
                            <h3>${esc(r.patient_name)} <span class="bg">${esc(r.blood_group)}</span></h3>
                            <span class="badge ${esc(r.urgency)}">${esc(r.urgency)}</span>
                        </div>
                        <p>${esc(r.hospital_name)}, ${esc(r.hospital_address)} (${esc(r.city_name)})<br>
                           ${esc(r.units_remaining)} unit(s) still needed &middot; by <b>${esc(r.needed_by)}</b></p>
                        ${r.can_offer
                            ? `<form method="post" action="/donor/offer.php">
                                   <input type="hidden" name="csrf" value="${esc(csrf)}">
                                   <input type="hidden" name="request_id" value="${esc(r.request_id)}">
                                   <button class="btn">I can donate</button>
                               </form>`
                            : (r.already_offered > 0
                                ? '<span class="badge accepted">You already offered</span>'
                                : '<span class="muted">You cannot offer for this request (different city or not eligible yet).</span>')}
                    </div>`).join('');
            } catch (err) {
                list.innerHTML = '<p class="alert error">Could not load requests.</p>';
            }
        }
        fGroup.addEventListener('change', load);
        fCity.addEventListener('change', load);
        load();
    }
});
