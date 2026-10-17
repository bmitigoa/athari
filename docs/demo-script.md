# Athari: Demo Video Script (about 3 minutes)

**Focus:** first show how Athari is built and run (**Docker** and **Drupal**), then the portal itself.

**Before you record**

- Docker Desktop running. In a terminal inside the project: `ddev start`. Open `https://athari.ddev.site` in a browser.
- Demo data loaded (README quick-start). Four logins (local only): `admin / admin`, `fieldofficer / fieldofficer123`, `reviewer / reviewer123`, `ngoadmin / ngoadmin123`.
- Open these in the editor, ready to show: `deploy/Dockerfile`, `deploy/docker-compose.yml`, `deploy/Caddyfile`, `web/modules/custom/athari_donate/src/Service/DarajaClient.php`, `web/modules/custom/athari_api/src/Hook/ApiHooks.php`.
- Terminal font large, browser at 100% zoom, other tabs closed.
- Do the **workflow step (scene 7)** last or re-seed afterwards: publishing a report changes the totals.

---

## Part A: Docker and Drupal under the hood (about 1 minute 45 seconds)

| Time | Screen | What to show | What to say |
|---|---|---|---|
| **0:00 to 0:20** | Terminal | Run `docker ps` (shows the DDEV web, database and router containers). Then `ddev describe` | "Athari is a Drupal 11 portal for Kenyan NGOs. It runs entirely in Docker. Locally DDEV starts a web container, a MariaDB container and a router, so anyone can run it with five commands." |
| **0:20 to 0:50** | Editor | Open `deploy/docker-compose.yml`, then `deploy/Dockerfile`, then `deploy/Caddyfile` (scroll the block rules) | "For servers there is a production stack: two containers, MariaDB and the app. The app is Drupal on FrankenPHP, which is the Caddy web server with PHP built in. Caddy gets the HTTPS certificate by itself, hides private files, and the image is built without development tools. One script installs it on an Oracle Cloud or any Ubuntu server; I tested it on Docker, including an ARM64 build." |
| **0:50 to 1:15** | Terminal | Run `ddev drush status`, then `ddev drush config:status`, then `ls config/sync \| head -20` | "The whole site is configuration in Git: content types, fields, views, roles and the workflow. A new machine rebuilds the same site with one install command. That is Drupal configuration management." |
| **1:15 to 1:45** | Browser (logged in as `admin`) | *Structure, Content types* (Project, Location, Partner, Report). *Configuration, Workflow* (the four states). *Structure, Views* (projects, impact, map, review queue, donations) | "The content model has projects, locations, partners and reports, linked by entity references. Reports go through Draft, In Review, Published, Archived with Content Moderation. The dashboards and lists are Views, built from configuration rather than code." |

## Part B: Custom code and API (about 40 seconds)

| Time | Screen | What to show | What to say |
|---|---|---|---|
| **1:45 to 2:10** | Editor | `DarajaClient.php` (token and STK Push methods), then the `athari_donate` folder tree | "The custom code is small and focused. `athari_donate` is a Drupal module with a service that talks to Safaricom Daraja for M-Pesa, a Donation entity, a form, and a secret callback route. Credentials never enter Git." |
| **2:10 to 2:25** | Browser | `/api/projects?include=sector,county`, `/api/impact`, then `/api/donations` (404) | "A read-only JSON API serves projects and approved reports. Donations, drafts and user data are never exposed, and tests prove it." |

## Part C: The portal in use (about 1 minute)

| Time | Screen | What to click | What to say |
|---|---|---|---|
| **2:25 to 2:40** | Home, Projects, Impact, Map | Home hero and stat cards. *Projects*, filter WASH. *Impact* charts. *Map*, click a pin | "For visitors: projects filtered by sector and county, an impact dashboard with totals and charts, and a map. Only approved reports are counted." |
| **2:40 to 3:15** | Report workflow | Log in as `fieldofficer`. *Add content, Report*: pick a project, enter beneficiaries and funds, save as **In Review**. Log in as `reviewer`, open the **Review queue**, set **Published**. Open *Impact*: totals have risen | "Field officers submit reports and reviewers approve them. Only then do they change the public figures." |
| **3:15 to 3:35** | Donate | Open a project, click **Donate**, type phone `0712345678` to show the validation message | "Donations go through M-Pesa STK Push. It runs against the Daraja sandbox; production needs merchant onboarding with Safaricom." |
| **3:35 to 3:45** | Home | Back to the home page | "Athari: Drupal, Docker, M-Pesa and a public API in one portfolio project. The code and documentation are on GitHub. Thank you." |

Total: about 3 minutes 45 seconds. For a three-minute cut: drop the Caddyfile scroll, the `config:status` command, the invalid-phone demo, and the Map click.

---

## Tips

- Speak slowly and pause one second after each screen changes. Zoom the editor to 150% so code is readable.
- Do not show a password field in focus; use the demo accounts only.
- Do not submit a real donation unless your Daraja sandbox keys are configured.
- Reload each page once before recording (cache warm-up).
- Put the finished video link in the README, replacing `DEMO_VIDEO_LINK_PLACEHOLDER`.
