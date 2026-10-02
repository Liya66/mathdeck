# Caddy needs the static client on its own disk: PHP-FPM speaks FastCGI, not HTTP,
# so it cannot serve files for it. Baking them in rather than sharing a volume
# keeps both images immutable and makes a deploy a single atomic swap.
FROM caddy:2-alpine

COPY docker/Caddyfile /etc/caddy/Caddyfile
COPY public /srv/public
