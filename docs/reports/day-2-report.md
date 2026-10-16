# Athari — Day 2 Report: Roles, Workflow & Sample Data

**Owner:** Baraka Mitigoa  **Project:** Athari (NGO Project & Impact Portal)  **Date:** 16 October 2026
**Local site:** https://athari.ddev.site  **Repository:** https://github.com/bmitigoa/athari

---

## 1. Summary

Day 2 turned the empty content model into a working editorial system with realistic data.

- The **Partner** type gained an **Address** field, so the Address module is now actually used.
- Three roles now exist: **Field Officer**, **M&E Reviewer** and **Admin**, each with a deliberately narrow set of permissions.
- A **Report review** workflow (Draft → In Review → Published → Archived) now governs every Report. Field officers can write and submit reports, but **cannot publish**; only reviewers and admins can.
- Three local test users were created, one per role.
- A repeatable seed script loaded **5 partners, 15 locations, 10 projects and 30 reports** with realistic Ugandan data, with reports spread across all four workflow states.
- An automated check script confirmed **83 permission and workflow checks, all passing**.

Also done: the Day 1 report's commit hashes were corrected after `CLAUDE.md` was removed from Git history.

---

## 2. Everything created or changed

### 2.1 Partner address field

| Item | What it is | Why |
|---|---|---|
| `field_address` on Partner | Structured Address field (street, city, country), limited to Uganda. | Lets each partner organisation have a proper postal address and uses the Address module installed on Day 1. |
| Form display / view display entries | The Address widget on the edit form and the address formatter on the page. | Make the field editable and visible. |

### 2.2 Roles

| Role (label) | Machine name | Purpose |
|---|---|---|
| Field Officer | `field_officer` | Writes field reports from the ground and submits them for review. |
| M&E Reviewer | `me_reviewer` | Monitoring & Evaluation staff who check reports and approve or reject them. |
| Admin | `ngo_admin` | NGO programme administrators who manage all content, taxonomy and users. |

Drupal already had a built-in **Administrator** role (from the Standard profile). I kept it as the technical super-user role (user 1 / `admin`) for site building and system configuration. The new **Admin** role is the day-to-day NGO manager and **cannot change modules, site-wide settings or code-level configuration**.

### 2.3 The Report review workflow

A **workflow** is a set of *states* and the *transitions* allowed between them. Content Moderation attaches one to a content type; here, only to **Report**.

| State | Published? | Meaning |
|---|---|---|
| Draft | No | Being written. Visible only to its author and staff. |
| In Review | No | Submitted; waiting for an M&E Reviewer. |
| Published | Yes | Approved. Visible to the public and counted in impact totals. |
| Archived | No | Withdrawn or superseded; kept for history, hidden from the public. |

**Transition table** (a transition moves a report from one state to another; each has its own permission):

| Transition (machine name) | From → To | Field Officer | M&E Reviewer | Admin |
|---|---|:-:|:-:|:-:|
| Save as draft (`create_draft`) | Draft → Draft | ✔ | ✘ | ✔ |
| Submit for review (`submit_for_review`) | Draft → In Review | ✔ | ✘ | ✔ |
| Reject (return to draft) (`return_to_draft`) | In Review → Draft | ✘ | ✔ | ✔ |
| Approve and publish (`publish`) | In Review → Published | ✘ | ✔ | ✔ |
| Save published report (`republish`) | Published → Published | ✘ | ✔ | ✔ |
| Archive (`archive`) | Published → Archived | ✘ | ✔ | ✔ |
| Restore archived report to draft (`restore`) | Archived → Draft | ✘ | ✘ | ✔ |

There is **no** Draft → Published transition, so nobody (even Admin) can skip review. Content Moderation's default transitions were removed to enforce this.

Config files: `workflows.workflow.report_review.yml`. (Content Moderation also installs an unused default `editorial` workflow with no content attached; it was left alone.)

### 2.4 Permissions matrix (role × action)

Results come from `scripts/day2-05-verify-permissions.php` (see section 5). ✔ = allowed, ✘ = denied.

| Action | Anonymous | Field Officer | M&E Reviewer | Admin |
|---|:-:|:-:|:-:|:-:|
| View a published report | ✔ | ✔ | ✔ | ✔ |
| View an unpublished report (own draft) | ✘ | ✔ | ✔ | ✔ |
| View someone else's archived report | ✘ | ✘ | ✔ | ✔ |
| Create a report | ✘ | ✔ | ✘ | ✔ |
| Edit own draft report | ✘ | ✔ | — | ✔ |
| Edit a draft written by another officer | ✘ | ✘ | ✘ | ✔ |
| Edit someone else's published report | ✘ | ✘ | ✔ | ✔ |
| Delete a report | ✘ | ✘ | ✘ | ✔ |
| Create / edit / delete a project, location or partner | ✘ | ✘ | ✘ | ✔ |
| Create taxonomy terms (Sector, Region) | ✘ | ✘ | ✘ | ✔ |
| See the content overview (`/admin/content`) | ✘ | ✔ | ✔ | ✔ |
| Manage users | ✘ | ✘ | ✘ | ✔ |
| **Publish a report** | ✘ | **✘** | ✔ (from In Review) | ✔ (from In Review) |

Notes:

- A reviewer can edit reports only while a valid transition exists for them. Content Moderation hides the edit form when the user has no transition available, which is why reviewers cannot edit Drafts and field officers cannot edit reports In Review or Published. This is intentional: authors cannot change work after submission.
- Field officers can see **their own** unpublished reports only. Reviewers and admins see all.

### 2.5 Test users (local only)

| Username | Password | Role | Email |
|---|---|---|---|
| `fieldofficer` | `fieldofficer123` | Field Officer | fieldofficer@athari.test |
| `reviewer` | `reviewer123` | M&E Reviewer | reviewer@athari.test |
| `ngoadmin` | `ngoadmin123` | Admin | ngoadmin@athari.test |
| `admin` | `admin` | Administrator (super-user) | — |

These passwords are deliberately simple and are **for the local DDEV site only**. Never reuse them on a deployed site. Users are content (stored in the database), not configuration, so they are not exported to Git; the script `scripts/day2-03-users.php` recreates them.

### 2.6 Sample data

All organisations are **fictional** (`example.org` web addresses) and the figures are **illustrative sample data**, not real programme results.

| Content | Count | Details |
|---|---|---|
| Partners | 5 | Mwangaza Community Health Trust (Kampala), Bright Futures Education Foundation (Mbale), Pearl Water & Sanitation Initiative (Gulu), Harvest Hope Livelihoods Cooperative (Mbarara), Safe Haven Child Protection Network (Lira). Each has a website and address. |
| Locations | 15 | One per district with real coordinates, e.g. Gulu Returnee Resource Centre (2.7746, 32.2990), Kawempe Health Centre IV (0.3790, 32.5560), Kasese Flood Recovery Camp (0.1833, 30.0833). Districts match the Region taxonomy. |
| Projects | 10 | Budgets from USD 64,000 to 310,000, covering all five sectors. Statuses: 6 Active, 3 Completed, 1 Planned. |
| Reports | 30 | Spread across 9 projects (the Planned project has none yet). |

Projects: Kawempe Maternal & Child Health Outreach; Mbale Schools Learning Recovery Programme; Soroti Rural Water Points Rehabilitation; Gulu Returnee Livelihoods & Savings Groups; Lira Girls' Safe Spaces & Child Protection; Arua Borehole & Hygiene Promotion; Mbarara Dairy Value Chain Strengthening; Fort Portal Tea Outgrowers Training; Jinja Youth Skills & Employment; Kasese Flood Response & Recovery.

Report states: **16 Published, 5 In Review, 6 Draft, 3 Archived.** Most reports are authored by `fieldofficer`; four (three archived, one published) by `ngoadmin`, so ownership rules can be tested. Total beneficiaries recorded across all reports: 58,285.

### 2.7 Scripts added (`scripts/`)

| File | Purpose |
|---|---|
| `lib.php` | Shared helper that creates a field plus its form and view display. |
| `day2-01-partner-address.php` | Adds the Partner address field. |
| `day2-02-roles-workflow.php` | Creates the workflow, transitions, roles and permissions. |
| `day2-03-users.php` | Creates the three test users. |
| `day2-04-seed-data.php` | Seeds partners, locations, projects and reports. Safe to re-run (skips existing titles). |
| `day2-05-verify-permissions.php` | Runs 83 access and workflow checks without saving anything. |

---

## 3. Modules installed

| Module | Type | Purpose | Use in Athari |
|---|---|---|---|
| Content Moderation | Core | Adds moderation states to content and controls who may move content between them. | Runs the Report review workflow. |
| Workflows | Core | The engine that defines states and transitions. | Stores the `report_review` workflow. |

No new contrib modules were needed. The Address module from Day 1 is now in use.

---

## 4. Code walkthrough

All scripts run with `ddev drush php:script <file>`, which boots Drupal and runs the file with its full API available.

**`lib.php` — `athari_lib_add_field()`**: creates `FieldStorageConfig` (the database column) and `FieldConfig` (the field on one content type) if missing, then registers the field in the `EntityFormDisplay` (widget) and `EntityViewDisplay` (formatter). Re-running is safe because it checks first.

**`day2-02-roles-workflow.php`**
1. **Workflow.** `Workflow::create()` makes a `content_moderation` workflow. Its *type plugin* (`$workflow->getTypePlugin()`) owns states and transitions. Drupal pre-creates Draft and Published plus default transitions, so the script adds In Review and Archived, deletes the defaults, and adds seven explicit transitions. `addEntityTypeAndBundle('node','report')` attaches it to Reports. Each state has `published` and `default_revision` flags, which control whether content is public and whether saving makes that revision the live one.
2. **Roles.** `Role::create()` then `grantPermission()` for each permission string. Permission names for transitions follow the pattern `use <workflow> transition <transition>`. An invalid permission name would throw an error, which is how the script caught a non-existent permission during development.

**`day2-03-users.php`**: `User::create()` with `roles`, skipping users that already exist. Drupal hashes the password on save.

**`day2-04-seed-data.php`**: runs as user 1 via `account_switcher` so every moderation transition is allowed. Helpers `athari_seed_find()` and `athari_seed_term()` look up existing nodes and taxonomy term IDs. Partners come first, then locations (coordinates stored as WKT, `POINT (longitude latitude)`), then projects (which reference locations, partners, sector and region by entity ID), then reports. Each report is saved as Draft and then pushed through the real transitions (`in_review`, `published`, `archived`) so each report has a genuine revision history. Pathauto creates the URL aliases automatically.

**`day2-05-verify-permissions.php`**: for each user it calls `$node->access('view'|'update'|'delete', $account)` and `createAccess()` for entity access, `state_transition_validation->getValidTransitions()` to list the transitions available from each state, and `$node->validate()` after setting a new moderation state to confirm illegal moves are rejected. Nothing is saved.

---

## 5. How to use / test it

### Log in and try each role

1. Open https://athari.ddev.site/user/login and log in as each user from section 2.5. (Use a private window per user, or log out between them.)
2. **As `fieldofficer`:**
   - Go to `/node/add/report`. Pick a project and fill in the report.
   - The *Change to* dropdown offers only **Draft** and **In Review**. There is no *Published* option.
   - Save as In Review. You can no longer edit it.
   - `/node/add/project` should show *Access denied*.
3. **As `reviewer`:**
   - Go to `/admin/content` and filter by content type *Report*.
   - Open a report that is *In Review* and choose Edit. The state dropdown offers **Reject (return to draft)** and **Approve and publish**.
   - Approve one, then visit its public URL while logged out to confirm it is visible.
4. **As `ngoadmin`:**
   - Create a project, location or partner, then archive and restore a report.
   - Confirm you can manage users at `/admin/people`.
5. **Anonymous visitor:** published reports are visible; draft, in-review and archived reports return *Access denied*.

### Re-run the automated checks

```bash
ddev drush php:script scripts/day2-05-verify-permissions.php
```

The last line should read `SUMMARY|ALL CHECKS PASSED`. The saved output is in `docs/reports/day-2-verification.txt`.

### Re-seed or reset

`ddev drush php:script scripts/day2-04-seed-data.php` is idempotent. It creates only what is missing.

---

## 6. Verification results

I verified behaviour **server-side**, by switching to each account inside Drupal and asking its access and moderation systems what that user may do. I did **not** type the test-user passwords into the browser login form, so the on-screen experience (the dropdown contents in step 2 to 4 above) is the part for you to confirm by hand.

| Check group | Checks | Result |
|---|---:|---|
| Entity access (view, create, edit, delete, taxonomy, admin pages): 15 checks × 4 roles | 60 | All pass |
| Allowed transitions from each of 4 states × 3 roles | 12 | All pass |
| Attempted state changes (legal and illegal) | 11 | All pass |
| Anonymous HTTP requests (separate, run by hand) | 10 | As expected |
| **Total scripted checks** | **83** | **All pass** |

Highlights:

- Field officer trying Draft → Published: **rejected**. In Review → Published: **rejected**. Draft → In Review: allowed.
- Reviewer Draft → Published (never submitted): **rejected**. In Review → Published and In Review → Draft: allowed. Archived → Draft: **rejected**.
- Admin Draft → Published (skip review): **rejected**. Archived → Draft: allowed.
- Anonymous HTTP: published project, partner, location, report, sector and region pages return 200; draft and archived reports, `/admin/content` and `/node/add/report` return 403.

One expectation in my own test was wrong at first (I assumed Admin could not edit a field officer's draft; Admin has "edit any report content", so it can). I corrected the test, not the permissions, because the permission design is intended.

---

## 7. Commands run

| Command | What it does |
|---|---|
| `ddev start` | Starts the project containers (needed again after Docker restarted). |
| `ddev drush en content_moderation workflows` | Enables the moderation modules. |
| `ddev drush php:script <file>` | Runs a PHP script with Drupal loaded. |
| `ddev drush cr` | Clears all caches. |
| `ddev drush cex -y` | Exports configuration to `config/sync`. |
| `ddev mutagen sync` | Forces file sync between container and Windows before Git commands. |
| `git add`, `git commit`, `git push` | Version control. |

---

## 8. Drupal concepts explained

- **Roles and permissions:** a role is a named bundle of permissions; users get roles. Permissions are strings such as `create report content`. Roles are configuration, so they live in Git. Users are content, so they do not.
- **Own vs any:** Drupal separates `edit own X content` from `edit any X content`. Field officers get only "own".
- **Content Moderation:** adds a *moderation state* field to content. The state controls whether a revision is published and which transitions are available.
- **Workflow, state, transition:** a state is a position in the process; a transition is an allowed move between states. Each transition has its own permission, so you control *who may move content where*, separate from who may edit it.
- **Revisions:** every save in a workflow creates a revision. Unpublished (Draft, In Review) revisions can exist alongside the published one.
- **Entity access vs route access:** `$node->access('update', $account)` asks Drupal if an account may perform an operation; other modules (such as Content Moderation) can veto it.
- **Account switcher:** a service that temporarily runs code as another user. The seed script uses it as user 1; the verification script uses it to impersonate each role.
- **Idempotent scripts:** safe to run repeatedly. The seed and role scripts check whether things exist first.

---

## 9. Issues and fixes

| Issue | Cause | Fix |
|---|---|---|
| `ddev` failed: cannot connect to Docker. | Docker Desktop was not running after a restart. | Started Docker Desktop from `C:\Users\user\AppData\Local\Programs\DockerDesktop` and ran `ddev start`. |
| "The state 'draft' already exists". | Content Moderation workflows are created with Draft and Published already present. | Only add In Review and Archived. |
| "Call to undefined method Workflow::addTransition()". | Transitions are managed by the workflow's *type plugin*, not the entity. | Use `$workflow->getTypePlugin()->addTransition()`. |
| "'create_new_draft' transition already allows draft to draft". | Default transitions from Content Moderation conflicted with mine. | Deleted all default transitions first, then added the seven custom ones. |
| Permission `view content moderation overview` rejected. | The permission does not exist in Drupal 11. | Listed real permission names with a small script and removed it. |
| A listing command failed with a bash quoting error. | PowerShell and bash both parse quotes inside `ddev exec "..."`. | Moved logic into `.php` script files instead of inline `drush ev`. |
| One verification check failed. | My expectation was wrong, not the site. | Corrected the expected value (Admin may edit any report). |
| UI login with test passwords was not exercised. | I only type credentials into forms on recognised local test hosts, and `athari.ddev.site` is not one of them. | Verified through Drupal's access system instead and left the on-screen check to you (section 5). |

---

## 10. Git log (Day 2)

```
ecc448b test: add permission and workflow verification script with results
c5fbdb3 feat: add test user and sample data seed scripts
20a46eb config: add field officer, M&E reviewer and admin roles with report review workflow
ffd5bcc feat: add address field to partner content type
e97cd54 docs: update day 1 report commit hashes
```

A final `docs: day 2 report` commit adds this report and its PDF.

---

## 11. Next day (Day 3 — Dashboard, Map & Theme)

- Views: Projects listing with exposed filters (sector, region, status), an Impact dashboard (totals for beneficiaries and funds using Views aggregation, plus charts by sector and region), a Project map (Leaflet + Geofield), and a Review queue for M&E Reviewers.
- Build the `athari_theme` subtheme of Olivero: logo, colours and homepage layout.
- Build the homepage: hero section, key impact stats, featured projects and map.

Note for Day 3: impact totals must count **Published** reports only, so the seed data's Draft, In Review and Archived reports deliberately do not inflate the dashboard.
