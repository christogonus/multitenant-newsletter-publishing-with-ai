# NewsPub AI — Deployment Checklist

For a Ploi server running PHP 8.5, MySQL and Redis. Paths assume the site lives in `/home/ploi/your-domain.com`; adjust to yours.

## 1. Server and DNS (first deploy only)

- [ ] DNS: an **A record** for the app domain and a **wildcard A record** (`*.your-domain.com`) to the server IP. Publications live on subdomains.
- [ ] SSL: issue a certificate in Ploi that covers both `your-domain.com` and `*.your-domain.com` (wildcard needs DNS validation).
- [ ] Redis is installed and running on the server.
- [ ] Uploads up to 100 MB for order-bump files: PHP `upload_max_filesize` and `post_max_size` = `100M`, nginx `client_max_body_size 100M;`.

## 2. Environment (`.env` in Ploi → Environment)

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://your-domain.com`, `APP_KEY` set (`php artisan key:generate` once).
- [ ] Database: `DB_CONNECTION=mysql` plus `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.
- [ ] `TRUSTED_PROXIES`: leave empty when nginx faces the internet. If you put Cloudflare or a load balancer in front, list its IPs or ranges here, never `*`, or every visitor shares one rate limit.
- [ ] Queues and cache on Redis: `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`, `REDIS_HOST`, `REDIS_PASSWORD`, `REDIS_PORT`.
- [ ] Mail: `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` (welcome emails and set-password links depend on it).
- [ ] `NEWSPUB_PUBLIC_ROOT=your-domain.com` (publications become `name.your-domain.com`), `NEWSPUB_SUPPORT_EMAIL`, `NEWSPUB_ALERT_EMAIL`.
- [ ] AI: see section 6 (OpenAI GPT-6 Luna).
- [ ] LaunchpadJV:
  - `LAUNCHPAD_WEBHOOK_KEY` = a long random value.
  - In LaunchpadJV, set the IPN URL to `https://your-domain.com/ipn/launchpad?key=THAT_VALUE`.
  - `LAUNCHPAD_PRODUCT_*` = the LaunchpadJV product IDs (`product_id`, or `product_id:offer_id` when Silver and Gold share one product).
  - `CHECKOUT_*` = each product's checkout link (used on upgrade buttons).
- [ ] Custom domains (OTO 8): `NEWSPUB_CERTIFICATES=ploi`, `PLOI_API_TOKEN`, `PLOI_SERVER_ID`, `PLOI_SITE_ID`, `NEWSPUB_SERVER_IP=` the server's public IP.
- [ ] `NEWSPUB_AI_FAKE=false` (it is ignored in production anyway).

## 3. Deploy script (Ploi → Deploy script)

```bash
cd /home/ploi/your-domain.com
git pull origin main
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev
npm ci && npm run build
php artisan migrate --force
php artisan optimize
php artisan horizon:terminate
```

`horizon:terminate` makes the queue workers restart on the new code and settings after every deploy.

## 4. Cron job (scheduler)

In Ploi → **Scheduler**, add one job that runs **every minute**:

```bash
php /home/ploi/your-domain.com/artisan schedule:run
```

(Same as the crontab line `* * * * * cd /home/ploi/your-domain.com && php artisan schedule:run >> /dev/null 2>&1`.)

That single job runs everything the app schedules:

| Task | How often | What it does |
|---|---|---|
| `newspub:publish-scheduled` | every minute | Publishes issues whose scheduled time has come (Autopilot) |
| `newspub:check-domain-certificates` | every 10 minutes | Checks SSL status of buyers' custom domains |
| `newspub:check-ai-spend` | hourly | Emails admins when AI spend passes the daily alert |
| `queue:prune-failed --hours=168` | daily | Clears failed jobs older than 7 days |

## 5. Queue workers (Horizon)

Run **one** daemon: Horizon starts and balances all workers itself. In Ploi → **Daemons**, add:

- Command: `php /home/ploi/your-domain.com/artisan horizon`
- User: `ploi`
- Processes: `1`

Do **not** add separate `queue:work` daemons as well. Horizon runs these worker groups in production:

| Group | Queues (in priority order) | Max workers | What runs there |
|---|---|---|---|
| generation | `generation-priority`, `generation` | 12 | AI issue writing; OTO 2 Gold buyers go first |
| delivery | `publishing`, `integrations` | 6 | Publishing, sending to beehiiv, Kit, Mailchimp and the rest, reader sync |
| default | `default` | 4 | Emails and everything else |

Check it at `https://your-domain.com/horizon` (admins only): the status should say **Active**.

## 6. Running on OpenAI GPT-6 Luna

Set these in `.env`:

```dotenv
OPENAI_API_KEY=sk-...
NEWSPUB_AI_PROVIDER=openai
NEWSPUB_AI_MODEL=gpt-6-luna

# Optional: a richer model for OTO 2 Gold buyers' issues. Leave empty to use gpt-6-luna for everyone.
NEWSPUB_AI_PREMIUM_MODEL=

# USD per million tokens, from OpenAI's pricing page. Drives the AI spend page and the daily alert.
NEWSPUB_AI_PRICE_INPUT=
NEWSPUB_AI_PRICE_OUTPUT=
NEWSPUB_AI_DAILY_SPEND_ALERT=50
```

Optional backup provider: `NEWSPUB_AI_PROVIDER=openai,anthropic` falls back to Anthropic when OpenAI is down (also set `ANTHROPIC_API_KEY`). The model setting applies to the first provider only.

Then apply it: run `php artisan config:cache` and `php artisan horizon:terminate`. The workers keep the old model until they restart.

## 7. First deploy only

- [ ] `php artisan db:seed --force` loads sponsors, done-for-you kits, the cross-promo network and the order bump. It's safe to run again; existing rows are kept.
- [ ] `php artisan storage:link` makes uploaded logos and issue images public.
- [ ] Create your admin: `php artisan newspub:make-admin you@example.com --name="Your Name"`. It prints a password for a new account.

## 8. Smoke test after deploying

- [ ] Sign in, open the account menu, click **Admin**, and sign out again.
- [ ] Admin → Buyers → **Create demo account** with a few products. Sign in as it in a private window.
- [ ] Write an issue. It should finish in about a minute (proves Horizon and the AI key work).
- [ ] Publish it and open the public page on its subdomain.
- [ ] Send a test IPN from LaunchpadJV, or make a test purchase. The buyer should appear under Admin → Buyers with a welcome email.
- [ ] `/horizon` shows Active and no failed jobs; `/pulse` loads.
