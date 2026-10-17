"""Builds docs/diagrams/architecture.svg and data-model.svg (no dependencies).

Run from the repository root:  python scripts/make-diagrams.py
"""
import os

G, GD, E, SAND, INK = '#2f6b4f', '#1f4a36', '#c0682f', '#f6f1e7', '#25302a'
GREY = '#7a9e7e'
OUT = os.path.join('docs', 'diagrams')


def box(x, y, w, h, title, lines, head=G, size=13):
    o = [
        f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="8" fill="#fff" stroke="{head}" stroke-width="2"/>',
        f'<rect x="{x}" y="{y}" width="{w}" height="28" rx="8" fill="{head}"/>',
        f'<rect x="{x}" y="{y + 14}" width="{w}" height="14" fill="{head}"/>',
        f'<text x="{x + w / 2}" y="{y + 19}" text-anchor="middle" fill="#fff" font-weight="700" font-size="14">{title}</text>',
    ]
    for i, t in enumerate(lines):
        o.append(f'<text x="{x + 10}" y="{y + 48 + i * (size + 5)}" font-size="{size}" fill="{INK}">{t}</text>')
    return '\n'.join(o)


def arrow(x1, y1, x2, y2, label='', color=INK, dash=False, lx=None, ly=None):
    d = ' stroke-dasharray="7,4"' if dash else ''
    o = f'<line x1="{x1}" y1="{y1}" x2="{x2}" y2="{y2}" stroke="{color}" stroke-width="2"{d} marker-end="url(#m)"/>'
    if label:
        tx = lx if lx is not None else (x1 + x2) / 2
        ty = ly if ly is not None else (y1 + y2) / 2 - 6
        o += f'<text x="{tx}" y="{ty}" text-anchor="middle" font-size="12" fill="{color}">{label}</text>'
    return o


def svg(w, h, title, body):
    return (
        f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {w} {h}" width="{w}" height="{h}" '
        f'font-family="Segoe UI, Arial, sans-serif">'
        f'<defs><marker id="m" markerWidth="10" markerHeight="8" refX="9" refY="4" orient="auto">'
        f'<path d="M0,0 L10,4 L0,8 z" fill="{INK}"/></marker></defs>'
        f'<rect width="{w}" height="{h}" fill="{SAND}"/>'
        f'<text x="{w / 2}" y="32" text-anchor="middle" font-size="20" font-weight="700" fill="{GD}">{title}</text>'
        f'{body}</svg>'
    )


def architecture():
    a = [
        box(30, 70, 190, 110, 'Visitors and staff',
            ['Public: home, projects,', 'impact, map, donate', 'Staff: field officers,', 'M&amp;E reviewers, admins'], E),
        box(30, 230, 190, 90, 'API clients',
            ['Dashboards, apps, research', 'GET /api/projects, /api/impact', 'read-only, published only'], E),
        f'<rect x="290" y="60" width="460" height="400" rx="12" fill="#eef4f0" stroke="{G}" '
        f'stroke-width="2" stroke-dasharray="6,4"/>',
        f'<text x="520" y="84" text-anchor="middle" font-size="14" font-weight="700" fill="{GD}">'
        f'Your server: one small VPS (Docker)</text>',
        box(310, 100, 420, 70, 'Caddy + FrankenPHP (container app)',
            ['Automatic HTTPS, security headers, blocks private files', 'Runs PHP 8.3'], GD),
        box(310, 190, 200, 190, 'Drupal 11 core',
            ['Content types, taxonomy,', 'Content Moderation workflow,', 'Views, users and roles,',
             'JSON:API (read-only)', 'Config management (Git)']),
        box(530, 190, 200, 190, 'Athari code',
            ['athari_theme (Olivero)', 'athari_donate (M-Pesa)', 'athari_api (published only,',
             '  impact summary)', 'Pathauto, Geofield, Leaflet,', 'Charts, Address']),
        box(310, 395, 420, 50, 'MariaDB (container db)', ['Not reachable from outside the server'], GD, 12),
        box(830, 70, 210, 100, 'Safaricom Daraja',
            ['OAuth token, STK Push, STK Query', 'POSTs the result to the callback', '(sandbox today)'], E),
        box(830, 205, 210, 80, 'Public libraries', ['OpenStreetMap map tiles', 'Chart.js (CDN) for charts'], GREY),
        box(830, 320, 210, 80, 'GitHub', ['Code, config/sync, docs', 'Server pulls tagged releases'], GREY),
        box(830, 435, 210, 60, 'Backups', ['Daily DB and files, 14 days'], GREY, 12),
        arrow(220, 125, 308, 125, 'HTTPS', lx=264, ly=118),
        arrow(220, 275, 308, 160, 'HTTPS', lx=250, ly=232),
        arrow(520, 170, 520, 188),
        arrow(520, 380, 520, 393),
        arrow(730, 135, 828, 120, 'STK Push', lx=790, ly=108),
        arrow(828, 150, 730, 150, 'callback', E, True, 790, 186),
        arrow(730, 360, 828, 360, 'git pull', lx=790, ly=352),
        arrow(730, 440, 828, 460, 'nightly', lx=790, ly=440),
    ]
    return svg(1070, 510, 'Athari architecture', '\n'.join(a))


def data_model():
    d = [
        box(20, 60, 250, 150, 'Region (taxonomy)',
            ['8 former provinces (top level)', '47 counties (children)', 'name, parent', 'URL: /region/kisumu'], GD),
        box(20, 240, 250, 110, 'Sector (taxonomy)', ['Health, Education, WASH,', 'Livelihoods, Protection'], GD),
        box(330, 60, 290, 290, 'Project (content)',
            ['title, description', 'budget (KES)', 'start date, end date', 'status: planned / active / completed',
             'sector  to  Sector', 'county  to  Region (a county)', 'locations  to  Location (many)',
             'partners  to  Partner (many)', 'URL: /projects/&lt;title&gt;']),
        box(680, 60, 240, 130, 'Location (content)', ['title', 'county  to  Region', 'coordinates (lat, lon)']),
        box(680, 220, 240, 130, 'Partner (content)', ['title, website, logo', 'address (Kenya)', 'fictional organisations']),
        box(330, 400, 290, 240, 'Report (content, moderated)',
            ['title, report date', 'project  to  Project', 'beneficiaries reached', 'funds spent (KES)',
             'narrative, attachments', 'states: Draft, In Review,', '  Published, Archived',
             'Only Published counts in totals']),
        box(680, 400, 300, 240, 'Donation (custom entity)',
            ['project  to  Project', 'amount (KES), phone (masked)', 'status: pending / success /', '  failed / cancelled',
             'M-Pesa receipt, result code', 'MerchantRequestID,', 'CheckoutRequestID', 'created, changed, paid'], E),
        box(20, 400, 250, 240, 'Users and roles',
            ['Field Officer: create and edit', '  own reports, submit', 'M&amp;E Reviewer: approve, reject,', '  archive',
             'Admin: content, staff users', 'Anonymous: read published'], GREY),
        arrow(330, 120, 272, 120, 'county', lx=300, ly=112),
        arrow(330, 290, 272, 290, 'sector', lx=300, ly=282),
        arrow(620, 110, 678, 110, 'many', lx=650, ly=102),
        arrow(620, 270, 678, 270, 'many', lx=650, ly=262),
        arrow(475, 400, 475, 352, 'project', lx=505, ly=380),
        arrow(680, 520, 622, 340, 'project', lx=670, ly=436),
        arrow(272, 520, 328, 520, 'author', INK, True, 300, 512),
    ]
    return svg(1000, 670, 'Athari data model', '\n'.join(d))


os.makedirs(OUT, exist_ok=True)
for name, content in (('architecture.svg', architecture()), ('data-model.svg', data_model())):
    with open(os.path.join(OUT, name), 'w', encoding='utf-8') as f:
        f.write(content)
print('written')
