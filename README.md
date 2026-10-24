<p align="center"><img src="public/logo-dark-trim.webp" width="420" alt="NewsletterPub AI"></p>

# NewsletterPub AI

**World's First AI Newsletter Empire Builder That Turns Any Niche Into Fully Written, Published & Monetized Newsletters: Fresh Topics, Ready-To-Send Content, Branded Pages, Subscriber Growth, Sponsors & Paid Readers, All On Autopilot**

**World's First AI Newsletter Publisher That Researches, Writes, Designs & Publishes 15 Done-For-You Newsletters A Week In Any Niche, Then Grows Your List And Lands You Sponsors**

NewsletterPub AI lets a buyer start a newsletter business with AI. They pick a niche, the app researches fresh topics and writes each newsletter, and the buyer edits, publishes and grows a readership from one dashboard. Every publication gets its own public page on a subdomain (or, with an upgrade, the buyer's own domain), a subscribe form, and optional delivery to the email service the buyer already uses.

It is sold as a lifetime deal on LaunchpadJV: a front-end product, an order bump and twelve upgrades (OTOs). Checkout happens on LaunchpadJV; this app receives the sale, creates the account and unlocks what the buyer paid for.

## Products

| Product | Price | What it unlocks |
| --- | --- | --- |
| NewsletterPub AI (front end) | $17 | Up to 3 publications, 15 AI-written newsletters per publication per week |
| Niche and Swipe Vault (bump) | $17 | Ranked niches, proven subject lines and a 30-day launch plan |
| OTO 1 Delivery | $97 | Send newsletters through beehiiv, Ghost, Kit, Mailchimp, MailerLite, a webhook or an HTML export |
| OTO 2 Silver | $67 | No publication cap and unlimited newsletters |
| OTO 2 Gold | $97 | Silver, plus a priority writing queue and the premium AI model |
| OTO 3 Growth | $97 | Sign-up pages, free gifts and reader referrals |
| OTO 4 Sponsor | $97 | Sponsor directory, media kit, rate guide and paid newsletters (Stripe, PayPal or manual payments) |
| OTO 5 Done-For-You | $127 | Ready-made publications with topics and first newsletters written |
| OTO 6 Social | $67 | Turn each newsletter into social posts and video scripts |
| OTO 7 Autopilot | $67 | Plan a month of newsletters and publish them on schedule |
| OTO 8 Premium | $67 | Premium designs, logo and colours, and a custom domain |
| OTO 9 Multi | $97 | One dashboard for every publication, its readers and its earnings |
| OTO 10 Network | $97 | Swap recommendations with other publications |
| OTO 11 Agency | $197 | Run publications for paying clients, with packages and contracts |
| OTO 12 Reseller | $297 | Sell NewsletterPub AI accounts through license codes |

Each product is a Pennant feature that resolves from the `purchases` ledger, so refunds and chargebacks revoke access automatically. Product IDs and checkout links come from `config/products.php`.

## How it works

1. **Sale.** LaunchpadJV posts an IPN to `/ipn/launchpad?key=…`. The app records it in the purchases ledger (idempotent on the transaction ID), creates the buyer if the email is new and sends a welcome email.
2. **Writing.** The buyer asks for a newsletter. The app checks the weekly quota, runs an optional web-search research step (cached per niche) and has a `laravel/ai` agent write the newsletter as structured blocks on the Horizon `generation` queue. Gold buyers go to `generation-priority`.
3. **Publishing.** The buyer edits the blocks and publishes. Blade renders the blocks into the public page and email-safe HTML, so no raw AI HTML is ever stored or shown.
4. **Delivery.** With OTO 1, publishing pushes the newsletter to the buyer's connected platform and syncs subscribers on the `publishing` and `integrations` queues.

One Laravel app serves three surfaces: the dashboard (Livewire, behind Fortify), public publication pages on subdomains and custom domains (`routes/public.php`), and internal endpoints such as the IPN and the proxy's domain check (`routes/internal.php`).

## Stack

- PHP 8.5, Laravel 13
- Livewire 4, Alpine and Tailwind v4 on a custom Blade component kit
- `laravel/ai` for writing, research and provider failover (OpenAI by default, Anthropic as a backup)
- `laravel/fortify` for authentication, `laravel/pennant` for product access
- `laravel/horizon` on Redis for queues, `laravel/pulse` for monitoring
- Pest for tests, Dusk for the signup-to-live browser journey
- Ploi for hosting and custom-domain certificates

## Local setup

The app is served by Laravel Herd at `https://newsletterpub.test`.

```bash
composer run setup
php artisan db:seed
php artisan newspub:make-admin you@example.com --name="Your Name"
composer run dev
```

Set `NEWSPUB_AI_FAKE=true` in `.env` to write newsletters with a fake writer instead of calling an AI provider. For real writing, set `OPENAI_API_KEY`, `NEWSPUB_AI_PROVIDER` and `NEWSPUB_AI_MODEL`.

Admins can create demo accounts with any mix of products under Admin → Buyers, which is the quickest way to try each upgrade.

## Configuration

The main `.env` settings beyond Laravel's defaults:

| Setting | Purpose |
| --- | --- |
| `NEWSPUB_PUBLIC_ROOT` | Domain that publication subdomains live under |
| `NEWSPUB_SUPPORT_EMAIL`, `NEWSPUB_ALERT_EMAIL` | Support address shown to buyers; where admin alerts go |
| `NEWSPUB_DEFAULT_PASSWORD` | Starting password given to new buyers |
| `NEWSPUB_AI_PROVIDER`, `NEWSPUB_AI_MODEL`, `NEWSPUB_AI_PREMIUM_MODEL` | AI providers (comma-separated for failover) and models |
| `NEWSPUB_AI_PRICE_INPUT`, `NEWSPUB_AI_PRICE_OUTPUT`, `NEWSPUB_AI_DAILY_SPEND_ALERT` | Token prices and the daily spend alert |
| `LAUNCHPAD_WEBHOOK_KEY` | Secret in the IPN URL |
| `LAUNCHPAD_PRODUCT_*`, `CHECKOUT_*` | LaunchpadJV product IDs and checkout links per product |
| `NEWSPUB_CERTIFICATES`, `PLOI_*`, `NEWSPUB_SERVER_IP` | Custom-domain certificates through Ploi |

## Artisan commands

| Command | What it does |
| --- | --- |
| `newspub:make-admin` | Create an admin account or promote an existing one (`--revoke` removes admin access) |
| `newspub:apply-default-password` | Give buyers who never set a password the default password (`--dry-run` to count first) |
| `newspub:publish-scheduled` | Publish Autopilot newsletters whose time has come (every minute) |
| `newspub:check-domain-certificates` | Check certificate status of custom domains (every 10 minutes) |
| `newspub:check-ai-spend` | Email admins when AI spend passes the daily alert (hourly) |

## Tests

```bash
php artisan test --compact
php artisan dusk
```

## Deployment

See [DEPLOY-CHECKLIST.md](DEPLOY-CHECKLIST.md) for the Ploi server, environment, deploy script, scheduler, Horizon daemon and smoke test. [HOW-TO-ADD-BUMP.md](HOW-TO-ADD-BUMP.md) covers setting up order bumps, and [TODO.md](TODO.md) lists deferred features.
