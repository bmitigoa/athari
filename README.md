# Athari: NGO Project and Impact Portal

**Athari** (Swahili for "impact") is an open-source Drupal 11 portal that helps a Kenyan NGO manage its projects, collect field reports through a review workflow, show its impact on maps and charts, and accept donations by M-Pesa.

It was built as a five-day portfolio project, aimed at NGO IT and information-systems roles in Kenya. All amounts are in Kenyan shillings (KES) and the organisations, projects and people in the demo data are fictional.

| | |
|---|---|
| **Live demo** | LIVE_URL_PLACEHOLDER |
| **Public API** | `/api` (read-only, see [docs/api.md](docs/api.md)) |
| **Source** | https://github.com/bmitigoa/athari |

![Athari home page](docs/screenshots/homepage.png)

## Demo logins

The live demo has two limited staff accounts you can use to try the workflow. They can only touch demo data.

| User | Role | What to try |
|---|---|---|
| `fieldofficer` | Field Officer | Add a report under *Content*, then submit it for review |
| `reviewer` | M&E Reviewer | Open the *Review queue*, approve or reject a report |

The passwords are given with the live URL (they are generated on the server, not stored in this repository). There is also a full administrator account whose password stays on the server only.

## Features

- **Projects, locations, partners.** Each project has a sector, a county, a budget in KES, dates, a status, partner organisations and project sites.
- **Kenya geography.** The 8 former provinces and 47 counties are loaded as a hierarchical vocabulary, so projects can be filtered by county or province.
- **Field reports with a review workflow.** Reports move through Draft, In Review, Published and Archived. Field officers write and submit, M&E reviewers approve or send back, and only published reports count towards public figures.
- **Impact dashboard.** Totals of beneficiaries reached and funds spent, plus charts by sector and by province, all calculated from published reports.
- **Project map.** Project sites on an OpenStreetMap map (Leaflet and Geofield).
- **M-Pesa donations.** A donation form sends an STK Push through Safaricom Daraja. The callback is stored in a custom Donation entity, with a status page for the donor and per-project totals.
- **Read-only public API.** JSON:API for projects, locations, partners, sectors, counties and published reports, plus a ready-made `/api/impact` summary. Donations, drafts and user data are never exposed.
- **Hardened production build.** HTTPS, security headers, private files, no development modules, scheduled cron and nightly backups.

| Projects | Impact |
|---|---|
| ![Projects](docs/screenshots/projects.png) | ![Impact](docs/screenshots/impact.png) |

| Map | Donate |
|---|---|
| ![Map](docs/screenshots/map.png) | ![Donate](docs/screenshots/donate-form.png) |

## Architecture

![Architecture](docs/diagrams/architecture.svg)

Everything runs on one small server in two containers: **MariaDB** and the **web app** (Drupal 11 on FrankenPHP, which is the Caddy web server with PHP built in). Caddy gets and renews the HTTPS certificate on its own and refuses requests for private files. Configuration lives in Git (`config/sync`) so the same site can be rebuilt anywhere.

How a donation flows:

![Donation sequence](docs/diagrams/payment-sequence.svg)

## Data model

![Data model](docs/diagrams/data-model.svg)

## Tech stack

| Layer | Choice |
|---|---|
| CMS | Drupal 11.4, PHP 8.3, Composer, Drush 13 |
| Database | MariaDB 11.8 |
| Web server | FrankenPHP (Caddy) with automatic HTTPS |
| Contrib modules | Admin Toolbar, Pathauto, Token, Geofield, Leaflet, Charts (Chart.js), Address, JSON:API Extras, Role Delegation, Config Split |
| Core features | Content Moderation, Workflows, Views, JSON:API, Language, Content Translation |
| Custom code | `athari_donate` (M-Pesa Daraja), `athari_api` (public API rules and impact summary), `athari_theme` (Olivero subtheme) |
| Local development | DDEV (Docker) |
| Quality checks | PHPUnit (unit and kernel), PHPCS with Drupal standards, verification scripts |

## Local setup (DDEV)

You need Docker Desktop and [DDEV](https://ddev.com).

```bash
git clone https://github.com/bmitigoa/athari.git
cd athari
ddev start
ddev composer install
ddev drush site:install --existing-config -y --account-name=admin --account-pass=CHOOSE_A_PASSWORD
ddev drush php:script scripts/kenya-01-regions.php     # provinces and counties
ddev drush php:script scripts/kenya-03-seed.php        # demo projects, reports, donations
ddev launch
```

The site is at `https://athari.ddev.site`. To try the staff workflow locally, create users with `scripts/day2-03-users.php` (edit the passwords first).

### M-Pesa sandbox keys

Daraja keys are never stored in Git. Copy `docs/daraja-settings.local.php.example` into `web/sites/default/settings.local.php` and fill in your own sandbox consumer key, secret and passkey from the [Safaricom developer portal](https://developer.safaricom.co.ke). A public HTTPS callback address is needed for real sandbox callbacks; on a laptop you can use a tunnel, or test the callback locally with `scripts/day4-05-simulate-callback.php`.

## Public API

Read-only and anonymous. A few examples (full list with sample responses in [docs/api.md](docs/api.md)):

```bash
# All projects, with their sector, county and locations
curl -H "Accept: application/vnd.api+json" "https://YOUR-SITE/api/projects?include=sector,county,locations"

# Only WASH projects
curl "https://YOUR-SITE/api/projects?filter[sector.name]=WASH"

# Published reports of one project, newest first
curl "https://YOUR-SITE/api/reports?filter[project.id]=PROJECT_UUID&sort=-report_date"

# Impact totals, ready for a dashboard
curl "https://YOUR-SITE/api/impact"
```

## Testing

```bash
# Unit and kernel tests of the custom modules (kernel tests need the database address)
ddev exec "SIMPLETEST_DB=mysql://db:db@db/db vendor/bin/phpunit -c web/core web/modules/custom"

# Coding standards
ddev exec vendor/bin/phpcs --standard=Drupal,DrupalPractice --extensions=php,module,inc,install,yml web/modules/custom

# Anonymous API checks (37) against local or live site
powershell -File scripts/day5-02-verify-api.ps1 -Base https://athari.ddev.site

# Configuration and permissions checks, and HTTP security probe
ddev drush php:script scripts/day5-04-security-check.php
powershell -File scripts/day5-05-security-http.ps1 -Base https://athari.ddev.site
```

Results of the checks that were run for the release are saved in `docs/reports/`.

## Deploying to a server

A one-command installer for an Ubuntu 24.04 server with Docker is in [`deploy/`](deploy/README.md). It installs Docker, a firewall and automatic security updates, builds the image, installs Drupal from the exported configuration, loads the Kenya demo data, schedules cron and daily backups, and sets the M-Pesa callback base URL to the live domain.

```bash
curl -fsSL https://raw.githubusercontent.com/bmitigoa/athari/main/deploy/bootstrap.sh | sudo bash
```

## Payments

**M-Pesa runs against the Daraja sandbox; production requires merchant onboarding with Safaricom.** The sandbox never approves a payment on its own, so successful payments in the demo are shown with seeded data and a simulated callback.

## Documentation

- [Public API](docs/api.md)
- [Deployment runbook](deploy/README.md)
- Daily build reports in [docs/reports](docs/reports), and the final summary [docs/reports/final-summary.pdf](docs/reports/final-summary.pdf)

## Roadmap

- Airtel Money Kenya as a second mobile-money provider behind the same payment interface
- Card payments
- Two-factor login for staff accounts
- Self-hosted chart library (no CDN)
- Swahili translations of the public pages

## Author

Baraka Mitigoa. Built in Nairobi, Kenya.
