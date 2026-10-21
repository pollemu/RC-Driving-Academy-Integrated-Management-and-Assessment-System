# Deploying to Render (Docker + Postgres)

The app runs in a single Docker container (nginx + php-fpm) and uses a managed
Render Postgres database. Configuration comes entirely from Render environment
variables; `.env` is never committed.

## What's included
- `Dockerfile` — PHP 8.3 + nginx, installs Composer deps, serves `public/`.
- `docker/nginx.conf` — Laravel-friendly nginx config (listens on `$PORT`).
- `docker/entrypoint.sh` — writes runtime dirs, migrates + seeds, caches config, starts nginx.
- `render.yaml` — Render Blueprint (web service + Postgres + env vars).
- `.dockerignore` — keeps secrets and local files out of the image.

## One-time setup
1. Get an application key locally and copy the value:
   ```
   php artisan key:generate --show
   ```
   It looks like `base64:....`. You'll paste it into Render as `APP_KEY`.

2. Push the repo (including the new deploy files) to GitHub.

## Deploy with the Blueprint
1. Render Dashboard -> **New +** -> **Blueprint** -> select this repo.
2. Render reads `render.yaml` and creates:
   - a **Postgres** database (`rcda-db`), and
   - the **web service** (`rc-driving-academy`).
3. Fill in the secrets Render marks as required (`sync: false`):
   - `APP_KEY` — the `base64:` value from step 1.
   - `MAIL_USERNAME`, `MAIL_PASSWORD` — Brevo SMTP.
   - `SEMAPHORE_API_KEY` — Semaphore.
   - `PAYMONGO_SECRET_KEY`, `PAYMONGO_WEBHOOK_SECRET` — PayMongo.
4. Click **Apply**. The first build runs `composer install`, then the
   entrypoint runs `migrate --force` + `db:seed --force` (idempotent).

The app will be live at:
`https://rc-driving-academy-integrated-management.onrender.com`

Health check: `/up`.

## Post-deploy
- **PayMongo webhook:** add endpoint
  `https://rc-driving-academy-integrated-management.onrender.com/api/webhooks/paymongo`
  for `checkout_session.payment.paid`, then paste the signing secret as
  `PAYMONGO_WEBHOOK_SECRET` and redeploy (or update the env var and restart).
- **Demo logins** (seeded once):
  - Admin: `admin@rcdriving.ph` / `admin123`
  - Instructor: `mario.santos@rcdriving.ph` / `instructor123`
  - Student: `john.cruz@rcdriving.ph` / `student123`
  Change these before real use.
- **Custom domain:** add it under Settings -> Custom Domain, then update
  `APP_URL` (and the PayMongo webhook URL) to the new domain.

## Important notes
- **Ephemeral disk:** anything written to the container filesystem (uploaded
  receipts, logs) is lost on deploy/restart. Receipts are stored on the private
  `local` disk, so they will not persist — move them to S3/R2 if you need them
  long-term. Demo data lives in Postgres, so it persists.
- **Free Postgres** on Render may expire after a trial period; check Render's
  current plan terms. Upgrade the database plan for production.
- **Queues:** `QUEUE_CONNECTION=sync`, so assessment grading runs inline. If you
  switch to `database`, add a Render **Background Worker** running
  `php artisan queue:work`.
- **Cold starts:** free web services spin down when idle; the first request
  afterwards is slow while the container wakes up.
- **HTTPS/proxies:** Render terminates TLS. If you later add Cloudflare or
  another proxy, configure TrustProxies so `APP_URL`/scheme detection stays
  correct.

## Re-deploying
Push to the connected branch (or click **Manual Deploy**). Each deploy rebuilds
the image and re-runs migrations + seeding. Because seeding is guarded, existing
data (including new registrations and payments) is preserved.
