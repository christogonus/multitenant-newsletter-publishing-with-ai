# NewsPub AI: Laravel Build Plan (FE + 12 OTOs)

Oct 18, 2026

## Read first: eight decisions that change the build

The strategy is buildable on Laravel 13 with first-party packages only, but eight points conflict with your constraints or with each other, and each changes the code. Settle them before the first Claude Code session; my recommendation is in the right column.

| # | Issue in the strategy doc | Recommendation |
| --- | --- | --- |
| 1 | Doc says Stripe for buyer billing; you are launching on LaunchpadJV with lifetime deals | Treat checkout as external. Build an IPN/webhook receiver (WarriorPlus assumed) that creates accounts and unlocks features. No Cashier for buyers. Confirm the processor and its IPN fields. |
| 2 | FE allows "15 newsletters per week", yet OTO 9 sells "multiple newsletters" and OTO 1 Lite covers "one platform/brand" | FE = 1 publication with 15 AI-written issues per week. OTO 2 lifts the weekly cap; OTO 5 and OTO 9 lift the publication cap. |
| 3 | Lifetime access plus AI writing means open-ended cost behind a $17 sale | Meter every generation. Enforce the 15/week cap server-side. Give "unlimited" (OTO 2) a hidden fair-use ceiling written into the terms. Add bring-your-own-key later if costs bite. |
| 4 | Doc recommends Next.js, Node, MJML, a news API and per-user subdomain tenancy | Replace with Livewire + Alpine on a custom Blade component kit, Blade table layouts, the AI SDK's built-in web search tool, and one database scoped by newsletter_id. No stancl/tenancy (third-party). |
| 5 | Subscribers are collected with no confirmation email, but the vendor must never become a sender | Store consent time and IP, add a honeypot and throttling, and let the connected platform (OTO 1) run double opt-in. State this in the terms. |
| 6 | Product is named NewsPub AI and also INBOXEMPIRE AI | Use NewsPub AI everywhere (section 6.4 chooses it). Fix the positioning block before any copy is written. |
| 7 | OTO 12 gives "full rights to sell" but never says how a reseller's buyers get accounts | License codes: the reseller generates FE-tier redemption codes up to a quota and sells them on their own checkout. No self-hosting, no source code. |
| 8 | Custom domains (OTO 8) and wildcard subdomains need TLS that no Laravel package provides | Solve at the proxy: wildcard certificate for the main domain, on-demand TLS (Caddy) or Cloudflare custom hostnames for customer domains. Laravel only answers "is this domain allowed?". |

## First-party package map

Every capability in the strategy has a first-party answer except custom-domain TLS, so the dependency list stays short. Package availability checked on laravel.com and Packagist on 18 Oct 2026.

| Package | Role in NewsPub AI |
| --- | --- |
| laravel/framework 13, PHP 8.4 | Core. Queues, scheduler, Http client, Mail, signed URLs, rate limiting, encrypted casts, filesystem. |
| Livewire 4, Alpine, Tailwind v4 | Dashboard runtime and styling, with every screen built from our own component kit (see UI design system). No starter-kit UI. Livewire is the one non-Laravel-team package, kept because it is your existing stack. |
| laravel/fortify | Login, password reset, email verification, 2FA. Buyers get an account from the IPN, then a set-password link. |
| laravel/ai | Newsletter writer agents, structured output, queued generation, provider failover, built-in web search tool for fresh niche topics. Pin the version: laravel.com listed v0.7.x when I checked. |
| laravel/pennant | One feature per product (FE, bump, each OTO). Gates every screen and limit. |
| laravel/horizon | Redis queues and supervisors for generation, publishing, platform pushes. |
| laravel/boost, laravel/mcp | Gives Claude Code Laravel-aware docs search, schema and tinker tools plus project guidelines. Dev only. |
| laravel/dusk | Browser test for the under-15-minute signup-to-live journey. |
| laravel/pulse, laravel/pail, laravel/pint | Cost and queue monitoring, log tailing, code style. Add laravel/telescope locally only. |
| laravel/sanctum | Optional. Token API for reseller code provisioning (OTO 12) if you expose one. |
| laravel/nova | Optional, paid. Admin panel if you already hold a licence; otherwise build 4 small admin pages from the same component kit. |

**Deliberately not used:** Flux UI or any other UI kit, stancl/tenancy, Spatie packages, Filament, MJML, any HTML sanitiser. Laravel Cashier is skipped for buyers because LaunchpadJV handles checkout; revisit it only if sponsor-paid subscriptions (OTO 4) move in-app. Laravel Socialite is skipped because Beehiiv, Ghost, Kit, Mailchimp and MailerLite connect through API keys or OAuth that the Http client handles directly. Laravel Reverb is optional for live progress; Livewire polling is enough for launch.

**Built natively:** wildcard-host routing with `Route::domain()`, email-safe layouts as Blade table components (Laravel already bundles the CSS inliner), and a driver-per-platform integration layer on `Illuminate\Support\Manager`.

## Architecture

One Laravel app serves three surfaces, and a purchase ledger, not the UI, decides what each buyer can do.

```text
Checkout (LaunchpadJV) -> IPN endpoint (verify, dedupe) -> Purchases ledger (source of truth) -> Pennant features (one per product)
                                                                                                        |
                                                                                                    unlocks
                                                                                                        v
AI writer (laravel/ai agent) <- calls <- Generation queue (Horizon) <- generate <- Dashboard (Livewire + Alpine)
                                               |                                          |
                                          push issue                                   publish
                                               v                                          v
Beehiiv, Ghost, Kit, Mailchimp, MailerLite <- API calls <- Platform drivers (OTO 1) <- subscriber sync <- Hosted pages (Blade, cached)
```

A sale becomes a ledger row, the ledger unlocks dashboard features, and the dashboard drives generation, hosted pages and platform delivery.

### Three surfaces, one codebase

- **Dashboard** (app.yourdomain): Livewire + Alpine on the custom component kit, behind Fortify auth.
- **Public publication pages** (brand.yourdomain, later custom domains): plain Blade, server-rendered, cached, with a small subscribe form. Registered through `Route::domain()` in a separate route file.
- **Internal endpoints**: the purchase IPN, the unsubscribe and report links (signed URLs), and the "is this domain allowed?" check for the proxy.

### Purchases and entitlements

1. LaunchpadJV/WarriorPlus sends an IPN for every sale, refund and chargeback. The endpoint verifies the security key, is idempotent on the transaction ID, and writes a row to a `purchases` ledger.
2. It creates the user if the email is new and sends a welcome email with a set-password link (Fortify's reset broker).
3. Each Pennant feature (`FrontEnd`, `Oto1Pro`, `Oto2Unlimited`, ...) resolves from the ledger. A refund or chargeback revokes by purging the feature, so the ledger stays the single source of truth.
4. Locked screens show an upgrade button that links to that OTO's checkout URL from `config/products.php`, which also maps each WarriorPlus product ID to its feature key.

### Generation pipeline

1. A Livewire action checks the weekly quota, creates an `issues` row as `generating` and dispatches a job to the `generation` queue (a Horizon supervisor).
2. An optional research step uses the AI SDK web search tool and caches the result per niche for several hours, so 500 users in one niche cost one search.
3. A `NewsletterWriter` agent returns structured output: subject, preheader and typed sections. Wrap the SDK behind your own interface so a version change touches one file.
4. Content is stored as structured blocks (heading, paragraph, bullets, link, image), never raw HTML. Blade renders those blocks into the public page and the email-safe HTML. This removes XSS risk without a sanitiser package and makes the editor simple.
5. The Livewire page polls until the issue is ready. The user edits blocks, approves and publishes; publishing busts the page cache and shows the public URL.

Every generation writes to `ai_usage` (tokens, cost, user), which feeds the quota service and a Pulse card for spend.

### Delivery layer (OTO 1)

A `PlatformDriver` contract with one driver each for Beehiiv, Ghost, Kit, Mailchimp and MailerLite, plus an `HtmlExportDriver` for Substack and anything else. Tokens use Laravel's encrypted cast. Two jobs do the work: `SyncSubscribersJob` pushes collected subscribers to the platform, and `PushIssueJob` sends the issue, with retries, backoff and a table that turns provider errors into plain-English fixes. The vendor never sends subscriber email.

### Safety by default

Terms checkbox on signup and subscribe, report-abuse link on every public page, honeypot plus `RateLimiter` on the subscribe form, signed unsubscribe and deletion links, and a moderation flag admins can use to take a page offline.

## UI design system

The dashboard uses a custom component kit on Tailwind v4, designed to feel like a polished enterprise product while a complete beginner still finishes the 15-minute journey unaided. No Flux and no other UI kit.

### Foundations

- Design tokens live in `resources/css/app.css` under Tailwind v4 `@theme`: colour, radius (8 px controls, 12 px cards, 16 px hero panels), an 8-point spacing scale, two shadow levels and a type scale. Dark mode redefines the same tokens.
- Components are anonymous Blade components in `resources/views/components/ui/` (`<x-ui.button>`, `<x-ui.card>` and so on). Livewire holds state; Alpine handles dropdowns, modals, tabs, toasts and drag-sorting.
- Icons come from an `<x-ui.icon>` component over SVG files kept in the repo (Heroicons is MIT-licensed, so copy only the ones you use). No icon package is added.
- One self-hosted variable font, loaded through Vite.

### Colour system

Neutral slate surfaces carry the page and colour does the guiding. Indigo is the brand and the default primary action. Each product area has its own accent, used for its sidebar icon chip, page header band and primary button, so a user always knows which part of the business they are in. Solid colours take white text at WCAG AA contrast; use the ink shade for text on a tint.

| Role | Used for | Solid | Tint | Ink |
| --- | --- | --- | --- | --- |
| Brand | Main actions, focus ring | #4F46E5 | #EEF2FF | #3730A3 |
| Create | Writer, editor, templates | #7C3AED | #F5F3FF | #5B21B6 |
| Publish | Hosted page, platform delivery, subscribers | #2563EB | #EFF6FF | #1E40AF |
| Grow | Opt-in pages, referrals, cross-promo network | #047857 | #ECFDF5 | #064E3B |
| Earn | Sponsors, media kit, paid subscriptions | #B45309 | #FFFBEB | #78350F |
| Social | Repurposing engine | #E11D48 | #FFF1F2 | #9F1239 |
| Automate | Autopilot, calendar, multi-newsletter manager | #0E7490 | #ECFEFF | #164E63 |

Neutrals: page #F8FAFC, card #FFFFFF, border #E2E8F0, text #0F172A, muted text #475569. Status: success #15803D, warning #B45309, danger #B91C1C.

### Components to build first

Button (four variants, three sizes, loading state), form fields with label, hint and error, card, stat card, badge and status pill, tabs, modal and slide-over, dropdown, toast, table with empty state, stepper, progress bar, skeleton, empty state, copy-to-clipboard field, and the locked-feature card. Every component renders in all its states on a local-only `/styleguide` page, and that page is the Phase 0 UI exit gate.

### Screens and patterns

- **Home:** one hero action, "Start My Newsletter", then a four-step checklist (pick niche, review first issue, publish, share link) with progress.
- **Wizard:** niches as searchable selectable cards with a free-text option, frequency as a segmented control, a live summary beside it.
- **Generation:** a three-stage progress panel (researching, writing, designing) instead of a spinner.
- **Issue editor:** drag-sortable block list on the left, live preview on the right; on phones they become two tabs.
- **Publish:** the public URL as a large copy field with share buttons, then a "what next" card.
- **Locked features:** the product's accent colour, a lock badge, a one-line benefit, the price and an Upgrade button to its checkout. Never an error or a dead end.
- **Sidebar:** colour-coded icon chips per area; locked areas stay visible and tinted. A publication switcher appears in the top bar only with OTO 9.
- **Integrations:** one card per platform with plain-English steps and a Test connection button.

### Rules every screen follows

One primary action per screen. 44 px minimum touch targets and mobile first. Plain words. Every async action shows progress and every failure shows a fix. WCAG 2.2 AA contrast, visible focus rings, reduced-motion support, light and dark themes. Empty states always offer the next action. Public pages and newsletter templates keep their own themes and do not use the dashboard palette.

## Data model

The core tables follow section 3.10 of the strategy, with four additions that lifetime selling and AI cost make necessary: `purchases`, `ai_usage`, `domains` and `license_codes`. Pennant keeps its own `features` table; do not duplicate it.

| Table | Key columns | Ships in |
| --- | --- | --- |
| users | email, name, password, is_admin, terms_accepted_at | Foundation |
| purchases | user_id, wp_txn_id (unique), product_key, amount, action (sale/refund/chargeback), raw payload (json) | Foundation |
| newsletters | user_id, name, niche, frequency, subdomain (unique), voice, branding (json), status | FE |
| issues | newsletter_id, title, subject_line, preheader, blocks (json), status, public_slug, published_at | FE |
| subscribers | newsletter_id, email, name, source, consent_at, consent_ip, unsubscribed_at | FE |
| ai_usage | user_id, newsletter_id, kind, input_tokens, output_tokens, cost, created_at | FE |
| reports | issue_id, reason, reporter_email, resolved_at | FE |
| platform_connections | user_id, newsletter_id, platform, encrypted credentials, status, last_error | OTO 1 |
| lead_pages, referrals | newsletter_id, slug, magnet file, referrer subscriber_id, reward tier | OTO 3 |
| sponsors, media_kits, outreach_templates | directory entries, rate guide, generated kit (json) | OTO 4 |
| kits | niche, voice, topic pipeline, seed issues (installed into a publication) | OTO 5 |
| social_posts | issue_id, channel, body, status | OTO 6 |
| calendar_items | newsletter_id, topic, scheduled_for, issue_id | OTO 7 |
| templates, domains | template key, premium flag; hostname, verified_at, tls_status | OTO 8 |
| network_listings, network_swaps | newsletter_id, category, audience size, swap status | OTO 10 |
| clients, client_newsletters | owner user_id, client name, package, contract status | OTO 11 |
| license_codes | reseller user_id, code, redeemed_by, redeemed_at, quota | OTO 12 |

All user-owned tables carry `user_id` or `newsletter_id` and are reached only through policies; public routes read through a dedicated query that returns published issues only.

## Build phases

Build in nine phases ordered by what a buyer meets first, so the sellable core (phases 0 to 2) is finished before any upsell code. Durations are my estimates for one expert developer working with Claude Code, about 15 weeks end to end; treat them as a starting point.

1. **Phase 0: Foundation, UI kit and purchase spine (about 1.5 weeks).** Repo, Boost, CLAUDE.md, Fortify, Pennant, Horizon, AI config, host routing, design tokens, the base component kit, auth screens and a /styleguide page, `purchases` ledger, IPN receiver, welcome email, minimal admin. *Exit gate:* a faked IPN creates a user and unlocks the FE feature, a refund revokes it, and CI is green.
2. **Phase 1: Front End core (about 2 weeks).** Niche and frequency picker, 4 to 5 strong newsletter templates, `NewsletterWriter` agent, weekly quota, block editor, publish engine, public pages, subscribe box, share links, terms and report flow. *Exit gate:* a Dusk test goes from signup to a live public issue in under 15 minutes, the 15-per-week cap holds, and the subscribe form survives a bot test.
3. **Phase 2: Front End polish and order bump (about 1 week).** Onboarding, empty states, plain-language audit (no SMTP, API or DNS words), bump vault as gated content pages, locked-feature screens for every OTO linking to its checkout, support page. *Exit gate:* a stranger finishes the journey unaided. FE and bump are now sellable.
4. **Phase 3: Delivery, OTO 1 (about 2 weeks).** Five platform drivers, HTML export, subscriber sync, Lite and Pro limits, plain-English error table. *Exit gate:* each driver pushes a real issue to a real free account.
5. **Phase 4: Growth and money, OTOs 2, 3, 4 (about 2 weeks).** Cap lift and priority queue; opt-in pages, lead magnets, referral system; sponsor directory, media-kit agent, outreach templates, rate guide.
6. **Phase 5: Output multipliers, OTOs 6, 7, 8 (about 2 weeks).** Social repurposing agent; bulk generation, calendar and scheduled publishing; premium templates, branding and custom domains with proxy TLS.
7. **Phase 6: Scale, OTOs 5 and 9 (about 1 week).** Kit installer plus the 20+ kits (a content task, start early), and the multi-publication dashboard.
8. **Phase 7: Platform plays, OTOs 10, 11, 12 (about 3 weeks).** Cross-promo network, agency client dashboard with packages and contracts, reseller licence codes and white-label asset pack.
9. **Phase 8: Hardening and launch (about 1 week).** Load test on public pages, security pass, backup and restore drill, Pulse alerts for AI spend and failed jobs, JV and affiliate materials.

**Launch cut.** Confirm LaunchpadJV's rules on delivery timing. If OTOs may be delivered shortly after launch, phases 0 to 4 are a credible first cut; if every advertised OTO must work on day one, phases 0 to 7 are the launch scope and phase 7 sets the date.

## OTO-by-OTO build spec

Every product is one Pennant feature, and each row below is a unit you can hand to Claude Code as its own branch. Prices match the strategy's cart of about $1,438; effort is S (days), M (1 week), L (2 weeks), XL (3+ weeks).

| Product | Price | Feature key | What to build | Effort | Needs |
| --- | --- | --- | --- | --- | --- |
| Front End | $17 | `front_end` | Create, publish and collect: niche picker, AI writer, block editor, hosted page, subscribe box, share links, 15 issues per week | XL | Foundation |
| Order bump: Niche and Swipe Vault | $17 | `bump_vault` | Gated content pages: ranked niches, subject-line swipes, 30-day plan. Content work, light code | S | FE |
| OTO 1: Delivery and Integrations | $67 Lite / $97 Pro | `oto1_lite`, `oto1_pro` | Five platform drivers, HTML export, subscriber sync, auto-publish. Lite = 1 connection; Pro = unlimited plus scheduled auto-publish | L | FE |
| OTO 2: Unlimited Creation | $97 | `oto2_unlimited` | Remove the weekly cap (keep a hidden fair-use ceiling), priority queue lane | S | FE |
| OTO 3: Subscriber Growth Engine | $97 | `oto3_growth` | AI-built opt-in pages and lead magnets, reader-gets-reader referrals with reward tiers, 1,000-subscriber playbook | L | FE |
| OTO 4: Sponsor and Payday Vault | $97 | `oto4_sponsor` | Sponsor and ad-network directory, media-kit agent, outreach templates, rate guide, paid-subscription setup guide | M | FE |
| OTO 5: Done-For-You Empire | $127 | `oto5_dfy` | 20+ installable kits (niche, voice, topic pipeline, seed issues), commercial-rights page, raises publication cap | M code, L content | FE |
| OTO 6: Social Repurposing | $67 | `oto6_social` | Agent that turns an issue into X threads, LinkedIn posts, short-video scripts; copy buttons | S | FE |
| OTO 7: Content Autopilot | $67 | `oto7_autopilot` | Bulk-generate a month, topic suggestions per niche, calendar, queued scheduled publishing via the scheduler | M | FE, OTO 2 advised |
| OTO 8: Premium Designs and Domain | $67 | `oto8_premium` | Premium template set, logo, colours and fonts, custom domain with DNS verification and proxy TLS | L | FE |
| OTO 9: Multi-Newsletter Manager | $97 | `oto9_multi` | Unlimited publications, one dashboard, per-publication subscribers and settings | M | FE |
| OTO 10: Cross-Promo Network | $97 | `oto10_network` | Opt-in directory of publications, recommendation swaps shown on public pages, placement priority for active members | XL | FE, OTO 3 advised |
| OTO 11: Agency / Client License | $197 | `oto11_agency` | Client records, per-client publications, package templates, contract and pricing templates | L | OTO 9 logic |
| OTO 12: Reseller License | $297 | `oto12_reseller` | Reseller dashboard, license-code generation and redemption, sales-asset pack, white-label sign-up page | L | FE |

The OTO 10 network is the riskiest build because it needs enough members to feel valuable; launch it with an admin-seeded set of publications so the directory is never empty.

## Claude Code workflow

Give Claude Code the strategy, this plan and a strict CLAUDE.md, then run one phase per branch in plan mode with a test gate before merging.

### Setup (Phase 0, day 1)

```bash
laravel new newspub   # choose no starter kit; Livewire and Fortify are added below
cd newspub
mkdir docs   # save the strategy doc as docs/strategy.md and this plan as docs/build-plan.md
composer require livewire/livewire laravel/fortify laravel/ai laravel/pennant laravel/horizon laravel/pulse
composer require --dev laravel/boost laravel/dusk laravel/pail
php artisan boost:install
php artisan horizon:install
php artisan vendor:publish --provider="Laravel\Pennant\PennantServiceProvider"
php artisan vendor:publish --provider="Laravel\Ai\AiServiceProvider"
php artisan migrate
```

Horizon needs the pcntl extension, which Windows PHP lacks. On your Herd machine run `php artisan queue:work --queue=generation,default` locally, or use WSL2, and keep Horizon for the VPS.

### CLAUDE.md skeleton

```markdown
# NewsPub AI
Laravel 13, PHP 8.4, Livewire 4, Alpine, Tailwind v4 with a custom component kit (no Flux), Pest. Read docs/strategy.md and docs/build-plan.md first.

## Hard rules
- First-party Laravel packages only, and no UI kits (no Flux). Ask before adding any other composer package.
- Content is stored as structured blocks (json), never raw HTML. Blade renders it.
- The purchases table is the source of truth. Pennant features resolve from it. Never grant a feature by hand in code.
- The app never sends subscriber email. System email only (welcome, reset).
- Every AI call goes through App\Ai\NewsletterWriter or a sibling interface, writes an ai_usage row, and respects the quota service.
- Customer-facing UI copy is plain language: no SMTP, API, DNS, deliverability.
- Any action reachable in 3 clicks or fewer from the dashboard.

## Conventions
- Livewire class components, UI built only from components in resources/views/components/ui, policies for every user-owned model.
- Queues: generation, publishing, integrations, default.
- Pint before commit. Pest feature test for every route, job and entitlement.

## Definition of done
Pint clean, Pest green, Dusk journey green when UI changed, no new dependency, docs/build-plan.md checklist updated.
```

Add your own Livewire and Laravel conventions from your existing projects to the Conventions block so Claude Code matches your style.

### Working method

- Start each phase in plan mode. Ask for a plan that lists files, migrations, tests and risks, approve it, then let it build.
- Let Boost supply framework docs, schema and tinker access; do not paste docs into prompts.
- Use an Explore agent when a phase touches many files, and a fresh review agent after each phase to check it against the exit gate in this plan.
- Fake the AI in tests with the SDK's fake helpers, and fake every platform API with `Http::fake()`. Real-account checks happen only in the Phase 3 exit gate.
- Never let Claude Code touch purchases or IPN code without a test for idempotency, refunds and chargebacks.

### Prompts

**Phase 0**

```text
Read docs/strategy.md and docs/build-plan.md. Enter plan mode for Phase 0 only. Plan: (1) config/products.php mapping WarriorPlus product IDs to the 15 feature keys in the OTO table, (2) a purchases migration and model, (3) an idempotent IPN controller that verifies the security key, creates the user, sends a welcome email with a set-password link, and records sales, refunds and chargebacks, (4) one Pennant class per feature resolving from purchases, (5) host-based route files for app, public and internal surfaces, (6) Pest tests for the exit gate. Also build the design tokens, ui components, auth screens and /styleguide page described in the UI design system section. Do not build any FE screens yet.
```

**Phase 1 and later**

```text
Enter plan mode for Phase <N> in docs/build-plan.md. Re-read the rows it covers in the OTO table and the architecture section. Produce a plan with migrations, models, policies, Livewire components, jobs, tests and the exit gate check. After I approve, build it, run Pint and Pest, then report which exit-gate items pass.
```

Ask for the IPN field names and the WarriorPlus product IDs from your vendor account before Phase 0, because the IPN controller and `config/products.php` depend on them.

## Launch checklist and risks

The two risks most likely to hurt are AI cost on lifetime accounts and content that must be curated by hand, not coded.

| Risk | Mitigation |
| --- | --- |
| AI spend outruns $17 sales | Quota service, per-niche research cache, spend card in Pulse with an alert, hidden fair-use ceiling for OTO 2, bring-your-own-key as a fallback |
| laravel/ai is young (v0.7.x on laravel.com when checked) | Pin the version, keep it behind your own interface, run the AI test suite on every upgrade |
| Unconfirmed subscribers get emailed through a connected platform | Consent timestamp and IP stored, platform-side double opt-in, plain reminder in the OTO 1 screen |
| Refunds and chargebacks leave features unlocked | Ledger-driven Pennant features, refund tests, admin view of revoked accounts |
| Hand-made content slips: bump vault, 20+ kits, sponsor directory, network seed | Start content in Phase 1 and run it in parallel; re-check sponsor and ad-network entries are real and active before launch |
| Morning Brew, The Hustle and Milk Road figures are stated as reported | Verify every figure and date before it reaches a sales page, as the strategy itself instructs |
| Substack "integration" claim | Market the five API platforms plus export HTML; do not claim Substack publishing |
| Custom-domain TLS and wildcard DNS go wrong in production | Prove them on a staging domain in Phase 5, not at launch |
| OTO 11 and 12 grow into full products | Hold to templates for agency and license codes for resellers; anything more is post-launch |

- [ ] Confirm the checkout processor, IPN fields and WarriorPlus product IDs
- [ ] Confirm LaunchpadJV rules on OTO delivery timing
- [ ] Decide the FE publication cap and the OTO 2 fair-use ceiling
- [ ] Register the main domain and wildcard DNS; set up the proxy
- [ ] Write terms, privacy policy and refund handling for lifetime access
- [ ] Generate real sample issues in 5 niches for proof screenshots
- [ ] Beta test with 10 non-writers and collect testimonials
- [ ] Run the load test, backup restore and failed-job alert drill
