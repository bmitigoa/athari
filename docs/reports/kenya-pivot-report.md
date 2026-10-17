# Athari — Kenya Pivot Report

**Owner:** Baraka Mitigoa  **Project:** Athari (NGO Project & Impact Portal)  **Date:** 17 October 2026
**Local site:** https://athari.ddev.site  **Repository:** https://github.com/bmitigoa/athari

The Day 1 to Day 4 reports were **not** rewritten. They describe the portal as it was built for Uganda. This report explains what changed when Athari moved to Kenya, and why.

---

## 1. Summary

Athari now targets **Kenya**. Everything that was specific to Uganda has been replaced:

- The Region taxonomy is **Kenya's 47 counties**, grouped under the **8 former provinces**.
- All sample data (5 partners, 15 locations, 10 projects, 30 reports, 19 sample donations) was regenerated for Kenya, with real coordinates and Kenyan names.
- **KES (Kenyan shillings) is the single currency** everywhere: project budgets, report funds spent, the dashboard, the charts and donation totals.
- The map opens centred on Kenya.
- **M-Pesa is the only payment provider.** The MTN MoMo plan is dropped. The `PaymentProviderInterface` stays as the extension point; Airtel Money Kenya and card payments are noted as future providers.

All tests and the Day 2 to Day 4 page and permission checks pass on the Kenya data, and the screenshots were retaken.

---

## 2. Why

Daraja, the payment service Athari uses, belongs to Safaricom in **Kenya**. It takes Kenyan shillings and Safaricom numbers (`2547…` and `2541…`). On Day 4 this was already a mismatch with a Uganda-focused portal; the Day 4 report flagged it. Moving the portal to Kenya makes the data, the currency and the payment provider agree, and it lets the sandbox and a future go-live use the same country.

---

## 3. Before and after

### 3.1 Geography (Region taxonomy)

| | Before (Uganda) | After (Kenya) |
|---|---|---|
| Top-level terms | 4 regions: Central, Eastern, Northern, Western | 8 former provinces: Nairobi, Central, Coast, Eastern, North Eastern, Nyanza, Rift Valley, Western |
| Child terms | 20 districts | **47 counties** |
| Total terms | 24 | 55 |
| Name for the lowest level | "District" | "County" (field labels on Location and Project) |
| Chart "Funds spent by region" groups by | the 4 regions | the 8 provinces |

Counties per province: Nairobi 1, Central 5, Coast 6, Eastern 8, North Eastern 3, Nyanza 6, Rift Valley 14, Western 4 (total 47). Nairobi is both a province and a county, so there are two terms named Nairobi: the province, and the county under it.

### 3.2 Sample data

| | Before (Uganda) | After (Kenya) |
|---|---|---|
| Partners (5, fictional) | Mwangaza Community Health Trust, Bright Futures Education Foundation, Pearl Water & Sanitation Initiative, Harvest Hope Livelihoods Cooperative, Safe Haven Child Protection Network | Afya Bora Community Trust (Nairobi), Elimu Mkono Foundation (Kisumu), Maji Safi Initiative (Lodwar), Mavuno Livelihoods Cooperative (Nakuru), Ulinzi wa Mtoto Network (Mombasa) |
| Locations (15) | Kawempe, Wakiso, Masaka, Mbale, Tororo, Soroti, Gulu, Kitgum, Lira, Arua, Mbarara, Fort Portal, Jinja, Mukono, Kasese | Kibera (Nairobi), Likoni (Mombasa), Garissa town, Homa Bay, Lodwar and Kakuma (Turkana), Makueni, Kitui, Kilifi, Marsabit, Kakamega, Nyeri, Kisumu, Nakuru, Garsen (Tana River): **14 counties** |
| Projects (10) | Kawempe Maternal & Child Health Outreach … Kasese Flood Response & Recovery | Kibera Maternal & Child Health Outreach, Garissa and Homa Bay Learning Recovery Programme, Turkana Rural Water Points Rehabilitation, Makueni Sand Dams & Savings Groups, Kilifi Girls' Safe Spaces & Child Protection, Marsabit Borehole & Hygiene Promotion, Kakamega Dairy Value Chain Strengthening, Nyeri Coffee Outgrowers Training, Kisumu Youth Skills & Employment, Tana River Flood Response & Recovery |
| Project mix | 6 active, 3 completed, 1 planned | unchanged |
| Reports (30) | 16 published, 5 in review, 6 draft, 3 archived | unchanged mix (same counts) |
| Sample donations | 25 records (19 sample + 6 sandbox test donations) | 19 sample donations (the sandbox test records were part of the cleared content) |
| Phone numbers | fictional `2547000000xx` | unchanged |

Real places and coordinates: for example Kibera -1.3133, 36.7889; Lodwar 3.1191, 35.5973; Kilifi -3.6305, 39.8499; Marsabit 2.3284, 37.9899. The organisations, the people reached and the money are **fictional sample data**.

### 3.3 Money

| | Before | After |
|---|---|---|
| Currency of project budgets | USD | **KES** |
| Currency of funds spent | USD | **KES** |
| Donations | KES (Daraja) | KES |
| Field labels | "Budget (USD)", "Funds spent (USD)" | "Budget (KES)", "Funds spent (KES)" |
| Number prefix | `USD ` | `KES ` |
| Total of all project budgets | USD 1,585,500 | **KES 166,550,000** |
| Funds spent, published reports only | USD 461,000 | **KES 59,930,000** (about 130 KES to the dollar, rounded to 500 shillings) |
| Beneficiaries, published reports | 39,360 | 39,360 (people did not change) |
| Successful sample donations | KES 93,503 | KES 138,500 (15 donations) |
| Biggest single budget | USD 310,000 | KES 32,000,000 |

**Compact stat cards.** KES amounts are larger numbers than the USD ones, so the dashboard shortens them to fit a card: `KES 59.9M` (millions), `KES 138.5K` (hundreds of thousands) and plain `KES 82,500` below that. The full amount is still shown in tables, on project pages and in the donations report.

### 3.4 Map

| | Before | After |
|---|---|---|
| Centre | 1.3733 N, 32.2903 E (Uganda) | **0.0236 N, 37.9062 E (Kenya)** |
| Zoom | 7 | 6, which shows the whole country |
| Markers | 15 | 15 (all inside Kenya's boundaries, checked by a test) |

### 3.5 Payments

| | Before | After |
|---|---|---|
| Primary provider | M-Pesa (Daraja) | M-Pesa (Daraja), unchanged |
| MTN MoMo | "Optional next step" | **Dropped** |
| `PaymentProviderInterface` | MTN MoMo or Airtel Money named in its comment | Kept; documentation names **Airtel Money Kenya** and **card payments** as future providers |
| Phone validation | `2547…` and `2541…` | unchanged: it already matched Kenya |

### 3.6 Wording

| Place | Before | After |
|---|---|---|
| Homepage eyebrow | "NGO Project & Impact Portal · Uganda" | "… · Kenya" |
| Projects filter | "Region / district" | "Region / county" |
| Project cards, table and page | "Region" | "County" |
| Partner address | country limited to Uganda | limited to Kenya |
| `CLAUDE.md` (kept untracked) | Uganda focus, Ugandan regions and locations, MTN MoMo | Kenya focus, 47 counties, KES, M-Pesa only |

---

## 4. How it was done safely

| Step | What I did |
|---|---|
| Backup | A database snapshot named `pre-kenya-pivot` before anything was deleted. To go back: `ddev snapshot restore pre-kenya-pivot`. |
| Confirmation | I stopped and asked before deleting content, and listed exactly what would go: 25 donations, 5 partners, 15 locations, 10 projects, 30 reports and 24 region terms. |
| Reset script | `scripts/kenya-02-reset-content.php` deletes **content only**, and refuses to run unless it is given the word `CONFIRM`. It never touches configuration, users, menu links, the About page or the Sector terms. |
| No views deleted | On Day 4, deleting one view also deleted the views and blocks that depended on it. This time the Views were **edited in place** (`scripts/kenya-04-config.php`), never deleted, so that cannot happen. |
| Reusable seed | `scripts/kenya-03-seed.php` reads all its data from one file, `scripts/seed/kenya-data.php`, and is idempotent: the second run created nothing. It also repairs an existing project or location that points at the wrong county. |
| Old scripts | `day1-content-model.php`, `day2-04-seed-data.php` and `day4-04-seed-donations.php` are kept as a record and now say they are superseded. |
| Reports | Day 1 to Day 4 reports untouched. |

New and changed scripts:

| Script | Purpose |
|---|---|
| `scripts/seed/kenya-data.php` | The single source of the Kenya sample data |
| `scripts/kenya-01-regions.php` | Builds the 8 provinces and 47 counties |
| `scripts/kenya-02-reset-content.php` | Destructive reset (needs `CONFIRM`) |
| `scripts/kenya-03-seed.php` | Seeds partners, locations, projects, reports and donations |
| `scripts/kenya-04-config.php` | KES labels and prefixes, county wording, Kenyan map centre and compact totals, in place |
| `scripts/kenya-05-verify.php` | Checks the database, configuration and rendered pages |
| `tests/KenyaSeedDataTest.php` | PHPUnit test of the data file |
| `scripts/lib-views.php`, `day3-03`, `day3-05`, `day4-01` | Updated so rebuilding the views from scratch also gives KES and Kenya |

---

## 5. Test results

| Check | Result |
|---|---|
| Kenya data test (`KenyaSeedDataTest`): 8 provinces and 47 counties, 15 locations inside Kenya's boundaries and spread over at least 12 counties, 10 projects with budgets in the millions of shillings, 30 reports in the right states within their project budgets, 19 donations, no Ugandan leftovers | **6 tests, 571 assertions pass** |
| Kenya verification (`kenya-05-verify.php`): database, configuration, content and 8 rendered pages | **45 of 45 pass** |
| Day 2 permission checks | **83 of 83 pass** |
| Day 3 page checks (4 roles × 13 pages + menu) | **56 of 56 pass** |
| Day 4 donation page checks | **43 of 43 pass** |
| Module unit tests (phone numbers, callback parsing, idempotency, Daraja client) | **45 tests, 129 assertions pass** |
| Module kernel tests (real database) | **4 tests, 52 assertions pass** |
| Drupal coding standards (PHPCS) | **clean** |

What the Kenya verification proves, in plain terms:

- 8 top-level provinces and 47 counties exist; every location and project is attached to a real county (not a province).
- No configuration object, no content (titles, text, terms, URL aliases) and no rendered page contains "Uganda", "UGX", "USD", a Ugandan place name or "MoMo".
- The dashboard figures equal the database: 39,360 beneficiaries and KES 59.9M for published reports only. Drafts, reports in review and archived reports are still left out.
- The map has 15 distinct markers and opens on Kenya.
- The seed is repeatable: a second run created 0 items.

Full output is in `docs/reports/kenya-verification.txt` and the three `day-N-verification-kenya.txt` files.

---

## 6. Problems found and fixed during the pivot

| Problem | Cause | Fix |
|---|---|---|
| The Nairobi project and location pointed at the **province** instead of the county. | Nairobi is the name of both. The lookup took the first term called Nairobi. | The lookup now prefers a term that has a parent (a county). The seed also repairs existing records; 2 were repaired. The verification caught this. |
| A test said Turkana spent more than its budget (KES 25.2M against 24M). | The shilling budget was set too low when converting from dollars. | Budget set to KES 32,000,000. The data test checks every project. |
| The "no Ugandan leftovers" test flagged **Nyandarua**, a Kenyan county. | The word list contained "Arua", which matches inside Nyandarua. | Whole-word matching. |
| A first fix of that test silently removed the backslashes, so the test could never fail. | A scripting escape. | Rewritten with real `\b` boundaries and confirmed there are no stray control characters. |
| Day 3 and Day 4 page checks failed with 404s. | They requested the old Ugandan project's address. | Updated to the Kenyan project. |
| The homepage donations card showed "KES 138,50…", cut off. | A six-figure amount did not fit the card. | Added the compact "K" format (KES 138.5K). |
| The map-marker check counted 16. | The map's own centre point is in the same settings. | The check excludes the centre. |
| Three checks had wrong expected values (the province order and the budget total). | My arithmetic and sort order in the check, not the site. | Corrected the checks, then recomputed from the database to confirm. |

---

## 7. What did not change

- The workflow, roles, permissions, the donation module's code, the Daraja integration, the theme and the page layouts.
- The Day 1 to Day 4 reports and their screenshots in the PDF files (they show the Ugandan version).
- The test users and their passwords.

---

## 8. Incomplete or worth knowing

- **The old reports describe Uganda.** That is intended, but anyone reading Day 1 to Day 4 should read this report alongside them.
- **Old web addresses are gone.** The Ugandan projects had addresses like `/projects/kawempe-maternal-child-health-outreach`; they now return "page not found". The Kenyan addresses are new (for example `/projects/kibera-maternal-child-health-outreach`). Nothing outside the site linked to the old ones.
- **Former provinces are a grouping only.** Kenya abolished provinces in 2013; counties are the real units. The provinces are kept only to group the 47 counties in filters and in the "funds by region" chart. The chart will skip any project tagged directly with a province and not a county; the seed never does that.
- **Exchange rate.** Shilling amounts are a rough conversion (about 130 KES to the dollar) of the earlier sample figures. They are illustrative, not real.
- **Sandbox donations.** The test donations made against the Daraja sandbox were cleared with the rest of the Ugandan content. A sandbox test can be repeated from the Day 4 report. The success path in the sandbox is still simulated, because it cannot approve a prompt.
- **Not yet built:** Airtel Money Kenya and card payments (the interface is ready for them), and production go-live with Safaricom.
- **Rollback:** `ddev snapshot restore pre-kenya-pivot` restores the database to the Ugandan version; reverting the pivot commits restores the code and configuration.

---

## 9. Git log

```
050e277 docs: retake screenshots with the Kenya data
ccba133 config: compact KES on stat cards (M and K) so large shilling amounts fit
dfd5533 test: rerun Day 2-4 checks against Kenya data and add Kenya verification
e04c94b config: switch to KES, Kenyan counties and a Kenya-centred map; M-Pesa is the only provider
210c077 feat: add Kenya sample data, county regions, reset and seed scripts with data tests
```

A final `docs: kenya pivot report` commit adds this report and its PDF.
