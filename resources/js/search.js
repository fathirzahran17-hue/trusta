// Pencarian global (Ctrl+K). Hasil dimuat dari route client.search dan dirender dengan textContent (aman dari XSS).
export function initSearch() {
    const root = document.getElementById('global-search');
    if (!root) return;
    const input = root.querySelector('#gs-input');
    const box = root.querySelector('#gs-results');
    const url = root.dataset.url;
    let timer = null;
    let items = [];
    let active = -1;

    const open = () => { root.hidden = false; input.value = ''; box.textContent = ''; items = []; active = -1; setTimeout(() => input.focus(), 10); };
    const close = () => { root.hidden = true; };

    function setActive(i) {
        const links = box.querySelectorAll('a.gs-item');
        if (!links.length) return;
        active = (i + links.length) % links.length;
        links.forEach((a, idx) => a.classList.toggle('active', idx === active));
        links[active].scrollIntoView({ block: 'nearest' });
    }

    function render(results, query) {
        box.textContent = '';
        if (!results.length) {
            const empty = document.createElement('div');
            empty.className = 'gs-empty';
            empty.textContent = query.length < 2 ? 'Ketik minimal 2 huruf.' : 'Tidak ada hasil.';
            box.appendChild(empty);
            return;
        }
        let group = null;
        results.forEach((r) => {
            if (r.group !== group) {
                group = r.group;
                const h = document.createElement('div');
                h.className = 'gs-group';
                h.textContent = group;
                box.appendChild(h);
            }
            const a = document.createElement('a');
            a.className = 'gs-item';
            a.href = r.url;
            const main = document.createElement('span');
            main.className = 'gs-main';
            main.textContent = r.label || '-';
            const sub = document.createElement('span');
            sub.className = 'gs-sub';
            sub.textContent = r.sub || '';
            a.append(main, sub);
            box.appendChild(a);
        });
        setActive(0);
    }

    async function search(q) {
        if (q.length < 2) return render([], q);
        try {
            const res = await fetch(url + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
            if (!res.ok) throw new Error('bad status');
            const data = await res.json();
            items = data.results || [];
            render(items, q);
        } catch (e) {
            box.textContent = '';
            const err = document.createElement('div');
            err.className = 'gs-empty';
            err.textContent = 'Pencarian gagal. Coba lagi.';
            box.appendChild(err);
        }
    }

    input.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => search(input.value.trim()), 200); });
    input.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowDown') { e.preventDefault(); setActive(active + 1); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); setActive(active - 1); }
        else if (e.key === 'Enter') {
            const links = box.querySelectorAll('a.gs-item');
            if (links[active]) { e.preventDefault(); window.location.href = links[active].href; }
        }
    });
    root.addEventListener('click', (e) => { if (e.target === root) close(); });
    document.addEventListener('click', (e) => { if (e.target.closest('[data-search-open]')) { e.preventDefault(); open(); } });
    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); root.hidden ? open() : close(); }
        else if (e.key === 'Escape' && !root.hidden) close();
    });
}
