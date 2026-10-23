# New Horizon RP – Webseite

Quelltext von https://newhorizonrp.de. Der Server holt Änderungen aus `main` alle 5 Minuten (`deploy/setup-server.sh`).

- `index.html`, `impressum.html`, `datenschutz.html` – Seiten
- `assets/` – CSS, JS, Schriften (lokal, keine Google-Fonts), Logo, Video
- `api/status.php` – Live-Serverstatus (FiveM `dynamic.json`, 30 s Cache)
- `api/forum.php` – Ankündigungen und Team aus forum.newhorizonrp.de (5 min Cache)
- `deploy/` – Caddy-Konfiguration und einmaliges Server-Setup
