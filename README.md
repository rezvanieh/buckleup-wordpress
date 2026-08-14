# BuckleUp WordPress

Custom WordPress theme + plugin rebuild of the BuckleUp Driving School site
(originally Next.js 16 + Prisma/Postgres). Runs entirely in Docker locally first.

## Quick start

```bash
cp .env.example .env
make up           # start db, wordpress, nginx, adminer, mailpit
make provision    # install WP, theme, plugins, roles, users, seed data
make build-assets # compile theme CSS/JS (Vite + Tailwind v4)
```

Then open:

| URL                              | What                                  |
| -------------------------------- | ------------------------------------- |
| http://localhost:8080            | The site                              |
| http://localhost:8080/wp-admin   | WP admin (user `admin`; password is generated and printed by `provision.sh`, or set `WP_ADMIN_PASSWORD` in `.env`) |
| http://localhost:8081            | Adminer (DB GUI)                      |
| http://localhost:8025            | Mailpit (caught outgoing email)       |

## What's in the repo

```
docker-compose.yml          # the dev stack
docker/                     # nginx vhost, custom WP image, mu-plugins (Mailpit SMTP)
scripts/provision.sh        # idempotent bootstrap (WP-CLI)
scripts/wp/*.php            # roles, users, catalog & content seeds, media import
wp-content/themes/buckleup  # custom block theme (Vite + Tailwind v4, Geist fonts)
wp-content/plugins/buckleup-core  # domain plugin (CPTs, REST, notifications)
.github/workflows/ci.yml    # PHP lint + theme build + compose validate
```

Only the **theme** and **plugin** are version-controlled. WordPress core lives in a
Docker volume; uploads are runtime data (see `.gitignore`).

See [DEVELOPMENT.md](./DEVELOPMENT.md) for the full workflow, conventions, and
how the panel teams plug into this scaffold.
