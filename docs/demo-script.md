# Athari: Demo Video Script (2 to 3 minutes)

**Before you record**

- Start the site: `ddev start`, then open `https://athari.ddev.site`.
- Make sure the demo data is loaded (see the README quick-start). If you have already run the workflow step once, re-seed or accept that the totals are higher.
- Use a clean browser window at full-screen width, zoom 100%, and close other tabs.
- Have four logins ready (local only): `fieldofficer / fieldofficer123`, `reviewer / reviewer123`, `ngoadmin / ngoadmin123`, `admin / admin`.
- Do the **workflow step (4)** last or re-seed afterwards, because publishing a report changes the totals.

---

## Scene by scene

| Time | Screen | What to click | What to say |
|---|---|---|---|
| **0:00 to 0:15** | Home page | Scroll slowly past the hero, the four impact stat cards and the map | "Athari is a Drupal portal for a Kenyan NGO. It manages projects, collects field reports, shows impact on charts and a map, and takes donations by M-Pesa." |
| **0:15 to 0:40** | Projects | Click *Projects*. Filter by sector **WASH**, then by county **Turkana**. Open one project | "Every project has a sector, a county, a budget in shillings, its sites, its partners and its published reports. Visitors can filter by sector, county and status." |
| **0:40 to 1:05** | Impact, then Map | Click *Impact*: point at the totals and the charts by sector and region. Click *Map*: click one pin | "The impact page adds up people reached and shillings spent. Only reports that a reviewer has approved are counted. The map shows where the work happens." |
| **1:05 to 1:50** | Report workflow | Log in as `fieldofficer`. *Content, Add content, Report*: choose a project, enter beneficiaries and funds spent, add a short narrative, set the state to **In Review**, save. Log out. Log in as `reviewer`, open the **Review queue**, open the report, set **Published**. Open *Impact* again and show the totals have increased | "Field officers write reports and submit them for review. A reviewer checks and publishes. Only then does the report appear on the site and change the totals." |
| **1:50 to 2:20** | Donations | Open a project and click **Donate**. Show the form and type an invalid phone such as `0712345678` to show the validation message. Back on the project page, point at "donations received" | "Donors choose a project, an amount and their phone number in the 2547 format. Athari sends an M-Pesa STK Push through Safaricom Daraja and records the result. It runs against the Daraja sandbox; production needs merchant onboarding with Safaricom." |
| **2:20 to 2:45** | API, then close | Open `/api/projects?include=sector,county`, then `/api/impact`, then `/api/donations` (shows 404) | "There is also a read-only public API for projects, locations, partners and approved reports. Donations, drafts and user data are never exposed. The whole site is rebuilt from Git, and it is ready to deploy on Oracle Cloud or any VPS. Thank you." |

Total: about 2 minutes 45 seconds. To shorten to two minutes, skip the county filter (scene 2) and the invalid phone demo (scene 5).

---

## Tips

- Speak slowly and pause one second after each page loads.
- Do not show the admin password prompt or any password field in focus; use the demo accounts above.
- Do not submit a real donation unless your Daraja sandbox keys are configured.
- If a page is slow the first time, reload once before recording (cache warm-up).
- Put the finished video link in the README, replacing `DEMO_VIDEO_LINK_PLACEHOLDER`.
