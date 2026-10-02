# Deploying to Oracle Cloud Always Free

The whole stack — web, API, worker, database, TLS — runs on one Always Free ARM
instance. Nothing here needs a paid tier.

Everything below was verified by running `docker-compose.prod.yml` locally against
the real production images. What could not be verified from here is the Oracle
console itself, so treat the console steps as a map rather than a transcript —
menu names move.

## What you are getting

| | |
|---|---|
| Instance | Ampere A1 (ARM), 2 OCPU / 12 GB is ample; Always Free allows up to 4 / 24 |
| Storage | Boot volume, well inside the 200 GB Always Free allowance |
| Cost | £0, provided you stay on Always Free shapes |

Every base image used is multi-arch, so ARM needs no changes:

```
php:8.2-fpm-alpine   386, amd64, arm, arm64, ppc64le, riscv64, s390x
mysql:8.0            amd64, arm64
caddy:2-alpine       multi-arch
```

## 1. Create the instance

Compute → Instances → Create. Change the shape to **Ampere A1 Flex** and pick
**Ubuntu 24.04** (or 22.04). Add your SSH key.

> **If you see "Out of host capacity"** — this is the single most common obstacle
> with Always Free ARM, and it is not your account. Try a different availability
> domain, then a different home region. Capacity frees up; retrying over a day or
> two usually works.

## 2. Open the ports — in *two* places

This is the step that catches almost everyone. Oracle filters traffic twice, and
opening only the first does nothing.

**a) The virtual network.** Networking → Virtual Cloud Networks → your VCN →
Security Lists → default. Add ingress rules allowing `0.0.0.0/0` to TCP 80 and 443.

**b) The instance's own firewall.** Oracle's Ubuntu images ship with iptables rules
that drop everything except SSH, and they persist across reboots. On the instance:

```bash
sudo iptables -I INPUT 6 -m state --state NEW -p tcp --dport 80 -j ACCEPT
sudo iptables -I INPUT 6 -m state --state NEW -p tcp --dport 443 -j ACCEPT
sudo netfilter-persistent save
```

If the site is unreachable and the security list looks right, it is almost always
this.

## 3. Install Docker

```bash
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker "$USER"
newgrp docker
```

## 4. Point DNS at it

An `A` record for your hostname → the instance's public IP. Do this **before**
bringing the stack up: Caddy asks Let's Encrypt for a certificate on first start,
and that only succeeds once the name resolves to this machine.

## 5. Configure and start

```bash
git clone https://github.com/Liya66/mathdeck.git
cd mathdeck
cp .env.example .env
```

Fill in `.env`:

```bash
SITE_ADDRESS=mathdeck.example.com
MATCHDECK_TOKEN_SECRET=$(openssl rand -hex 32)
MYSQL_ROOT_PASSWORD=$(openssl rand -base64 24)
MYSQL_PASSWORD=$(openssl rand -base64 24)
```

`.env` is gitignored. The values in `docker-compose.yml` are development ones and
are published in this repository — they are not secrets and must never be reused
here.

```bash
make prod-up
```

That builds the production images, waits for the database, applies migrations, and
then starts everything. Caddy obtains the certificate on its own.

## 6. Create the first teacher

The API lets a teacher create students but never another teacher, so the first one
is made on the machine:

```bash
make prod-account ARGS='miss-lee "Miss Lee" teacher'
```

It prints a generated passcode once.

## Day to day

```bash
make prod-deploy    # pull, build, migrate, restart
make prod-logs      # follow app, worker and caddy
make prod-backup    # dump what cannot be rebuilt
make prod-down      # stop (volumes survive)
```

### Backups

`bin/backup` dumps `accounts`, `deck_versions`, `matches`, `match_events`,
`match_commands` and `schema_migrations` — and deliberately not `fact_attempt`,
because that is a projection. Restore the dump, clear the projection cursor, and
the worker rebuilds every analytics row from the event log. The backup is smaller
and the restore is self-correcting.

A nightly cron:

```cron
0 3 * * * cd /home/ubuntu/mathdeck && set -a && . ./.env && set +a && ./bin/backup /home/ubuntu/backups >> /home/ubuntu/backup.log 2>&1
```

Copy those off the instance. A backup that only exists on the machine it is backing
up is not a backup.

## What this setup does not give you

Worth being clear about, because a single free instance is genuinely fine for a
demo and genuinely not fine for a classroom:

- **No redundancy.** One instance, one database, one disk.
- **No zero-downtime deploys.** `prod-deploy` restarts the containers; there is a
  few-seconds gap.
- **Oracle may reclaim idle Always Free instances.** Fine for a portfolio piece.
- **No data processing agreement.** The `accounts` table holds real display names
  even though analytics is pseudonymised. If actual children use this, hosting
  region, a DPA, retention and deletion all become requirements — and that is a
  conversation to have before the first class, not after.
