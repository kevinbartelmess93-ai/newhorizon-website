(() => {
  const API = window.NH_API || 'api/';

  // Verbindungsbefehl kopieren
  const copyBtn = document.getElementById('copy-connect');
  const cmd = document.getElementById('connect-cmd');
  if (copyBtn && cmd) {
    copyBtn.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(cmd.textContent.trim());
        copyBtn.textContent = 'Kopiert';
      } catch {
        const r = document.createRange(); r.selectNodeContents(cmd);
        const s = getSelection(); s.removeAllRanges(); s.addRange(r);
        copyBtn.textContent = 'Markiert';
      }
      setTimeout(() => { copyBtn.textContent = 'Kopieren'; }, 2000);
    });
  }

  // Live-Serverstatus
  const status = document.getElementById('status');
  async function loadStatus() {
    if (!status) return;
    const text = status.querySelector('.text');
    try {
      const r = await fetch(API + 'status.php', { cache: 'no-store' });
      if (!r.ok) throw new Error(r.status);
      const d = await r.json();
      if (d.online) {
        status.dataset.state = 'online';
        text.innerHTML = 'Server online · <span class="count"></span> Spieler';
        text.querySelector('.count').textContent = `${d.players} / ${d.max}`;
      } else {
        status.dataset.state = 'offline';
        text.textContent = 'Server gerade offline';
      }
    } catch {
      status.dataset.state = 'unknown';
      text.textContent = 'Status gerade nicht abrufbar';
    }
  }
  loadStatus();
  setInterval(loadStatus, 60000);

  const fmtDate = (iso) => new Date(iso).toLocaleDateString('de-DE', { day: '2-digit', month: 'short', year: 'numeric' });
  const el = (tag, cls, txt) => { const e = document.createElement(tag); if (cls) e.className = cls; if (txt != null) e.textContent = txt; return e; };

  // Ankündigungen aus dem Forum
  async function loadNews() {
    const list = document.getElementById('news-list');
    if (!list) return;
    try {
      const r = await fetch(API + 'forum.php?what=news');
      if (!r.ok) throw new Error(r.status);
      const items = (await r.json()).items || [];
      if (!items.length) return;
      list.replaceChildren(...items.slice(0, 3).map((n) => {
        const a = el('a', 'card'); a.href = n.url;
        const t = el('time', null, fmtDate(n.date)); t.dateTime = n.date;
        a.append(t, el('h3', null, n.title), el('p', null, n.excerpt || ''));
        return a;
      }));
    } catch { /* Platzhalter mit Forum-Link bleibt stehen */ }
  }
  loadNews();

  // Team aus den Forum-Rängen
  async function loadTeam() {
    const list = document.getElementById('team-list');
    if (!list) return;
    try {
      const r = await fetch(API + 'forum.php?what=team');
      if (!r.ok) throw new Error(r.status);
      const members = (await r.json()).members || [];
      if (!members.length) return;
      list.replaceChildren(...members.map((m) => {
        const card = el('div', 'member');
        if (m.avatar) { const img = el('img'); img.src = m.avatar; img.alt = ''; img.loading = 'lazy'; img.width = 48; img.height = 48; card.append(img); }
        else card.append(el('span', 'initial', (m.name || '?')[0].toUpperCase()));
        const box = el('div');
        const rank = el('span', null, m.rank); if (m.color) rank.style.setProperty('--rank', '#' + m.color);
        box.append(el('strong', null, m.name), rank);
        card.append(box);
        return card;
      }));
    } catch { /* statische Liste bleibt stehen */ }
  }
  loadTeam();
})();
