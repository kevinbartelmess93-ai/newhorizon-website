#!/usr/bin/env bash
# New Horizon RP – Webseite + Forum auf 62.68.75.135 hinter Caddy (einmalig als root ausführen)
set -euo pipefail
SITE=/var/www/newhorizonrp
REPO=https://github.com/kevinbartelmess93-ai/newhorizon-website.git

echo "== 1/5 Caddy und PHP installieren"
apt-get update -q
apt-get install -y -q debian-keyring debian-archive-keyring apt-transport-https curl gnupg git
curl -1sLf https://dl.cloudsmith.io/public/caddy/stable/gpg.key | gpg --dearmor --yes -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
curl -1sLf https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt > /etc/apt/sources.list.d/caddy-stable.list
apt-get update -q
apt-get install -y -q caddy php8.1-fpm php8.1-curl php8.1-mbstring || true
systemctl stop caddy || true

echo "== 2/5 Webseite holen"
if [ -d "$SITE/.git" ]; then git -C "$SITE" pull -q --ff-only; else git clone -q "$REPO" "$SITE"; fi
mkdir -p "$SITE/api/cache" && chown -R www-data:www-data "$SITE/api/cache"
echo '*/5 * * * * root git -C /var/www/newhorizonrp pull -q --ff-only >/dev/null 2>&1' > /etc/cron.d/nh-website-pull

echo "== 3/5 Discourse auf Socket umstellen (Sicherung von app.yml wird angelegt)"
cd /var/discourse
cp containers/app.yml "containers/app.yml.bak-$(date +%F-%H%M)"
sed -i -E 's|^(\s*)- "?templates/web\.ssl\.template\.yml"?\s*$|\1#- "templates/web.ssl.template.yml"|' containers/app.yml
sed -i -E 's|^(\s*)- "?templates/web\.letsencrypt\.ssl\.template\.yml"?\s*$|\1#- "templates/web.letsencrypt.ssl.template.yml"|' containers/app.yml
grep -q 'web.socketed.template' containers/app.yml || sed -i -E '/^\s*- "?templates\/web\.template\.yml"?\s*$/a\  - "templates/web.socketed.template.yml"' containers/app.yml
sed -i -E 's/^(\s*)- "(80:80|443:443)"/\1#- "\2"/' containers/app.yml
echo "--- geänderte Zeilen:"; grep -nE 'templates/web|80:80|443:443' containers/app.yml

echo "== 4/5 Discourse neu bauen (Forum ca. 10-15 Minuten offline)"
./launcher rebuild app

echo "== 5/5 Caddy starten"
cp "$SITE/deploy/Caddyfile" /etc/caddy/Caddyfile
caddy validate --config /etc/caddy/Caddyfile
systemctl enable --now caddy
systemctl restart caddy
sleep 15
for u in https://forum.newhorizonrp.de https://newhorizonrp.de https://newhorizonrp.de/api/status.php; do
  printf '%-45s ' "$u"; curl -s -o /dev/null -w '%{http_code}\n' "$u" || echo fehler
done
echo "FERTIG"
