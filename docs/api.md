# Athari public API

A **read-only** JSON:API for Athari's public data. It serves published projects, locations, partners, reports, sectors and regions (provinces and counties), plus one impact summary. No login is needed, and nothing can be changed through it.

- **Base URL (local):** `https://athari.ddev.site/api`
- **Base URL (live):** see the README
- **Format:** [JSON:API 1.1](https://jsonapi.org/format/). Send `Accept: application/vnd.api+json`.
- **Methods:** `GET` only. `POST`, `PATCH` and `DELETE` are refused.
- **Money:** Kenyan shillings (KES). Amounts arrive as decimal strings such as `"32000000.00"`.
- **CORS:** any website may read it with `GET` (no cookies are ever allowed).

## What is, and is not, exposed

| Exposed | Path | Notes |
|---|---|---|
| Projects | `/api/projects` | with sector, county, locations and partners as relationships |
| Locations | `/api/locations` | with `coordinates` (latitude and longitude) and county |
| Partners | `/api/partners` | name, website, address |
| Reports | `/api/reports` | **published reports only**, linked to their project |
| Sectors | `/api/sectors` | Health, Education, WASH, Livelihoods, Protection |
| Regions | `/api/regions` | 8 former provinces (top level) and 47 counties (children) |
| Impact summary | `/api/impact` | totals; see example 6 |

**Never exposed:** donations or donors (including phone numbers and M-Pesa receipts), user accounts and e-mail addresses, roles, files and report attachments, draft, in-review and archived reports, moderation states, authors, revision data, Daraja settings or any credential. Everything not listed above is switched off by default, so a resource added to the site later stays private until it is deliberately listed.

These claims are tested: `scripts/day5-02-verify-api.ps1` makes 46 requests **as an anonymous visitor** (see the testing section of the README).

## How to use it

| You want | Write |
|---|---|
| One field only | `?fields[projects]=title,budget_kes` |
| Related data in the same answer | `?include=locations,sector` |
| Filter by a field | `?filter[status]=active` |
| Filter through a relationship | `?filter[sector.name]=WASH` or `?filter[county.parent.name]=Coast` |
| Sort (a minus sign reverses) | `?sort=-budget_kes` |
| Page through results | `?page[limit]=20` and follow `links.next` (50 is the maximum page size) |

Field names are public names, not Drupal's: `budget_kes`, `start_date`, `county`, `sector`, `locations`, `partners`, `description`, `status` for projects; `project`, `report_date`, `beneficiaries`, `funds_spent_kes`, `narrative` for reports; `coordinates`, `county` for locations. Every resource also has `title` (or `name`), `created`, `changed` and `path`.

Responses are trimmed below to the interesting parts. Real ones also carry `links` and a `jsonapi` block.

---

## Example 1: WASH projects with their locations

Projects in the WASH sector, with the sector and every location included.

```bash
curl -H "Accept: application/vnd.api+json" \
  "https://athari.ddev.site/api/projects?filter[sector.name]=WASH&include=locations,sector&fields[projects]=title,budget_kes,status&fields[locations]=title,coordinates&fields[sectors]=name&sort=title"
```

```json
{
  "data": [
    {
      "type": "projects",
      "id": "414fd110-381a-4a2b-87ef-acb8f865f935",
      "attributes": {
        "title": "Marsabit Borehole & Hygiene Promotion",
        "budget_kes": "15000000.00",
        "status": "planned"
      },
      "relationships": {
        "locations": { "data": [{ "type": "locations", "id": "2ae06388-6cdc-457a-872a-63eb29d3dec7" }] },
        "sector":    { "data": { "type": "sectors", "id": "22001225-6359-4dc7-9a78-69f1ac106f34" } }
      }
    },
    {
      "type": "projects",
      "id": "2c01e932-396c-415d-a8bb-17fcda8b6ae7",
      "attributes": {
        "title": "Turkana Rural Water Points Rehabilitation",
        "budget_kes": "32000000.00",
        "status": "completed"
      }
    }
  ],
  "included": [
    { "type": "sectors", "id": "22001225-6359-4dc7-9a78-69f1ac106f34", "attributes": { "name": "WASH" } },
    { "type": "locations", "id": "2ae06388-6cdc-457a-872a-63eb29d3dec7",
      "attributes": { "title": "Marsabit Town Water Point", "coordinates": { "lat": 2.3284, "lon": 37.9899 } } },
    { "type": "locations", "id": "82b939b1-3d0b-4af1-b967-48067ab81afd",
      "attributes": { "title": "Lodwar Water Scheme", "coordinates": { "lat": 3.1191, "lon": 35.5973 } } },
    { "type": "locations", "id": "9a6e6be4-6e06-4ea6-9647-2c956b3b1dee",
      "attributes": { "title": "Kakuma Water Point", "coordinates": { "lat": 3.7122, "lon": 34.8588 } } }
  ]
}
```

(`coordinates` also carries `value`, `geohash` and bounding-box helpers; trimmed here.)

## Example 2: projects in one county

```bash
curl "https://athari.ddev.site/api/projects?filter[county.name]=Turkana&fields[projects]=title,status,start_date,end_date,budget_kes"
```

```json
{
  "data": [
    {
      "type": "projects",
      "id": "2c01e932-396c-415d-a8bb-17fcda8b6ae7",
      "attributes": {
        "title": "Turkana Rural Water Points Rehabilitation",
        "budget_kes": "32000000.00",
        "status": "completed",
        "start_date": "2024-06-01",
        "end_date": "2026-05-31"
      }
    }
  ]
}
```

## Example 3: projects in a whole province

Counties belong to a former province. Filtering on the county's parent finds every project in that province. Here, the Rift Valley, with the county included.

```bash
curl "https://athari.ddev.site/api/projects?filter[county.parent.name]=Rift%20Valley&include=county&fields[projects]=title&fields[regions]=name"
```

```json
{
  "data": [
    {
      "type": "projects",
      "id": "2c01e932-396c-415d-a8bb-17fcda8b6ae7",
      "attributes": { "title": "Turkana Rural Water Points Rehabilitation" }
    }
  ],
  "included": [
    { "type": "regions", "attributes": { "name": "Turkana" } }
  ]
}
```

## Example 4: the published reports of one project, newest first

```bash
curl "https://athari.ddev.site/api/reports?filter[project.id]=50d7b332-2d5c-43c3-ad43-65fd3111eb79&sort=-report_date&fields[reports]=title,report_date,beneficiaries,funds_spent_kes"
```

```json
{
  "data": [
    { "type": "reports", "id": "fedf0be5-73a1-47ae-92d6-9dc27f4ff863",
      "attributes": { "title": "Kibera Maternal Health field report, June 2026",
                      "report_date": "2026-06-30", "beneficiaries": 3410, "funds_spent_kes": "5057000.00" } },
    { "type": "reports", "id": "18e620da-7af8-4105-977f-028ddab0fd8e",
      "attributes": { "title": "Kibera Maternal Health field report, December 2025",
                      "report_date": "2025-12-31", "beneficiaries": 2860, "funds_spent_kes": "4095000.00" } },
    { "type": "reports", "id": "a55e3ef0-a4ff-46c1-811e-5f739691edb9",
      "attributes": { "title": "Kibera Maternal Health field report, June 2025",
                      "report_date": "2025-06-30", "beneficiaries": 1240, "funds_spent_kes": "1846000.00" } }
  ]
}
```

The project also has a fourth report, still in review. It does **not** appear, and asking for it directly returns 404.

## Example 5: where we work in a county (for a map)

```bash
curl "https://athari.ddev.site/api/locations?filter[county.name]=Turkana&fields[locations]=title,coordinates"
```

```json
{
  "data": [
    { "type": "locations", "attributes": { "title": "Lodwar Water Scheme",  "coordinates": { "lat": 3.1191, "lon": 35.5973 } } },
    { "type": "locations", "attributes": { "title": "Kakuma Water Point",   "coordinates": { "lat": 3.7122, "lon": 34.8588 } } }
  ]
}
```

## Example 6: the impact summary

`GET /api/impact` returns the totals the dashboard shows. Report figures count **published** reports only. Only totals are returned, never individual donations.

```bash
curl "https://athari.ddev.site/api/impact"
```

```json
{
  "data": {
    "type": "impact",
    "id": "summary",
    "attributes": {
      "beneficiaries_reached": 39360,
      "funds_spent_kes": 59930000,
      "published_reports": 16,
      "active_projects": 6,
      "donations_received_kes": 138500,
      "successful_donations": 15,
      "by_sector": [
        { "sector": "Health", "published_reports": 3, "beneficiaries": 7510, "funds_spent_kes": 10998000 }
      ],
      "by_province": [
        { "province": "Nairobi", "published_reports": 3, "beneficiaries": 7510, "funds_spent_kes": 10998000 },
        { "province": "Rift Valley", "published_reports": 2, "beneficiaries": 21200, "funds_spent_kes": 17654000 }
      ]
    }
  },
  "meta": { "currency": "KES", "note": "Report figures count published reports only. Donation figures are totals of successful donations; no donor details are exposed." }
}
```

(`by_sector` and `by_province` list every group; trimmed here.) The figures are sample data.

---

## Consuming it

```js
// Browser or Node 18+
const res = await fetch('https://athari.ddev.site/api/projects?filter[status]=active&include=county', {
  headers: { Accept: 'application/vnd.api+json' },
});
const { data, included } = await res.json();
console.log(data.map(p => p.attributes.title));
```

```python
import requests
r = requests.get("https://athari.ddev.site/api/impact", headers={"Accept": "application/vnd.api+json"})
print(r.json()["data"]["attributes"]["beneficiaries_reached"])
```

## Errors

| Status | Meaning |
|---|---|
| 200 | OK |
| 403 | Refused (for example a write attempt, or content you may not read) |
| 404 | Not found, or not public (draft reports, users, donations and anything else switched off all look like this) |
| 405 | Method not allowed |

## For maintainers

- The API is configured by `scripts/day5-01-api-config.php`, which writes the `jsonapi_extras` configuration that is exported to `config/sync`.
- Published-only is enforced twice: Drupal's own access check, and `athari_api`'s `hook_node_access`, which also removes drafts from staff on API routes.
- The JSON:API total count is switched off (`include_count: false`) because it counted unpublished reports too. Use `links.next` to page.
- To expose a new field: add it to the resource config script and re-run it; unknown fields fail closed (`validate_configuration_integrity`).
