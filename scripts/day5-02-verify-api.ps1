<#
.SYNOPSIS
  Checks the public API as an ANONYMOUS visitor and prints PASS/FAIL lines.

.DESCRIPTION
  Proves what the API does and does not expose: only six resources, published
  reports only, no donations, users, e-mail addresses, phone numbers or
  credentials, and no way to write. Needs the site running, and a file of
  sample UUIDs (see UuidFile) listing one report per moderation state, a page
  and a user. No login or cookie is used anywhere in this script.

  Usage: powershell -File scripts/day5-02-verify-api.ps1 -Base https://athari.ddev.site -UuidFile uuids.txt
#>
param(
  [string]$Base = 'https://athari.ddev.site',
  [string]$UuidFile = ''
)

$ErrorActionPreference = 'Stop'
$script:fails = 0
$script:checks = 0

function Check($what, $expected, $actual) {
  $script:checks++
  $ok = ("$expected" -eq "$actual")
  if (-not $ok) { $script:fails++ }
  "API|$what|$expected|$actual|" + $(if ($ok) { 'PASS' } else { 'FAIL' })
}

function Get-Raw($path, $method = 'GET', $body = $null) {
  try {
    $args = @{ Uri = "$Base$path"; Method = $method; UseBasicParsing = $true }
    if ($body) { $args.Body = $body; $args.ContentType = 'application/vnd.api+json' }
    $r = Invoke-WebRequest @args
    return @{ Status = [int]$r.StatusCode; Text = [Text.Encoding]::UTF8.GetString($r.Content); Headers = $r.Headers }
  }
  catch {
    $resp = $_.Exception.Response
    if ($resp) { return @{ Status = [int]$resp.StatusCode; Text = ''; Headers = $resp.Headers } }
    throw
  }
}

function Get-All($path) {
  # Follows the pagination links and returns every item (JSON:API pages hold at most 50).
  $items = @()
  $url = $path
  while ($url) {
    $r = Get-Raw ($url -replace [regex]::Escape($Base), '')
    if ($r.Status -ne 200) { break }
    $j = $r.Text | ConvertFrom-Json
    $items += @($j.data)
    $url = $j.links.next.href
  }
  return $items
}

function Get-Json($path) {
  $r = Get-Raw $path
  if ($r.Status -ne 200) { return $null }
  return $r.Text | ConvertFrom-Json
}

$uuids = @{}
if ($UuidFile -and (Test-Path $UuidFile)) {
  foreach ($line in Get-Content $UuidFile) {
    foreach ($m in [regex]::Matches($line, '(UUID_\w+|PAGE_UUID|USER_UUID)=([0-9a-f-]{36})')) { $uuids[$m.Groups[1].Value] = $m.Groups[2].Value }
    foreach ($m in [regex]::Matches($line, 'TITLE_(\w+)=([^;]+)$')) { $uuids["TITLE_$($m.Groups[1].Value)"] = $m.Groups[2].Value.Trim() }
  }
}

# 1. The index lists exactly the six public resources.
$index = Get-Json '/api'
$names = @($index.links.PSObject.Properties.Name | Where-Object { $_ -ne 'self' } | Sort-Object)
Check 'Index lists exactly six resources' 'locations,partners,projects,regions,reports,sectors' ($names -join ',')

# 2. Everything else is off, under both the new and the old prefix.
foreach ($p in '/jsonapi', '/jsonapi/user/user', '/jsonapi/athari_donation/athari_donation', '/api/user/user', '/api/users', '/api/donations',
  '/api/athari_donation/athari_donation', '/api/node/page', '/api/pages', '/api/file/file', '/api/user_role/user_role', '/api/taxonomy_vocabulary/taxonomy_vocabulary') {
  $r = Get-Raw $p
  Check "GET $p is not available" 'blocked' $(if ($r.Status -in 403, 404) { 'blocked' } else { "status $($r.Status)" })
}
if ($uuids.PAGE_UUID) { Check 'A Basic page is not served by the API' 404 (Get-Raw "/api/pages/$($uuids.PAGE_UUID)").Status }
if ($uuids.USER_UUID) { Check 'A user account is not served by the API' 404 (Get-Raw "/api/users/$($uuids.USER_UUID)").Status }

# 3. Reports: only published ones, in the list and by direct address.
$reports = Get-Json '/api/reports?page[limit]=50'
Check 'Reports in the API' 16 @(Get-All '/api/reports').Count
Check 'No total count that would reveal unpublished reports' 'absent' $(if ($reports.meta -and $reports.meta.PSObject.Properties.Name -contains 'count') { 'present' } else { 'absent' })
foreach ($state in 'draft', 'in_review', 'archived') {
  $key = "UUID_$state"
  if ($uuids[$key]) {
    $r = Get-Raw "/api/reports/$($uuids[$key])"
    Check "A $state report is not available by its address" 'blocked' $(if ($r.Status -in 403, 404) { 'blocked' } else { "status $($r.Status)" })
    $title = $uuids["TITLE_$state"]
    $listed = @($reports.data | Where-Object { $_.attributes.title -eq $title }).Count
    Check "A $state report is not in the list" 0 $listed
  }
}
if ($uuids.UUID_published) { Check 'A published report is available' 200 (Get-Raw "/api/reports/$($uuids.UUID_published)").Status }

# 4. No private data anywhere in the public output.
$dump = ''
foreach ($path in '/api/projects?page[limit]=50&include=locations,partners,sector,county', '/api/locations?page[limit]=50&include=county',
  '/api/partners?page[limit]=50', '/api/reports?page[limit]=50&include=project', '/api/sectors', '/api/regions?page[limit]=100', '/api/impact') {
  $dump += (Get-Raw $path).Text
}
foreach ($pattern in @(
    @('e-mail address (any @)', '[A-Za-z0-9._-]+@[A-Za-z0-9.-]+\.[a-z]{2,}'),
    @('"mail" field', '"mail"'),
    @('user names of test accounts', 'fieldofficer|reviewer"|ngoadmin'),
    @('password or hash', '"pass"|\$S\$'),
    @('Daraja or M-Pesa credentials', 'consumer_|passkey|CheckoutRequestID|MerchantRequestID|mpesa_receipt|SEED0'),
    @('donor phone numbers', '2547\d{8}|2541\d{8}'),
    @('moderation state', 'moderation_state'),
    @('author relationship', '"uid"|"revision_uid"'),
    @('file attachments', 'field_attachments|"attachments"')
  )) {
  Check "Public output has no $($pattern[0])" 0 ([regex]::Matches($dump, $pattern[1])).Count
}

# 5. Read-only: writes are refused.
foreach ($m in 'POST', 'PATCH', 'DELETE') {
  $r = Get-Raw '/api/projects' $m '{"data":{"type":"projects","attributes":{"title":"x"}}}'
  Check "$m /api/projects is refused" 'refused' $(if ($r.Status -in 403, 405) { 'refused' } else { "status $($r.Status)" })
}

# 6. Useful queries work (filters, includes, the impact summary).
$wash = Get-Json '/api/projects?filter[sector.name]=WASH&include=locations,sector'
Check 'Filter: WASH projects' 2 @($wash.data).Count
Check 'Include: locations of the WASH projects' 3 @($wash.included | Where-Object { $_.type -eq 'locations' }).Count
$tk = Get-Json '/api/projects?filter[county.name]=Turkana'
Check 'Filter: projects in Turkana county' 1 @($tk.data).Count
$rift = Get-Json '/api/projects?filter[county.parent.name]=Rift Valley'
Check 'Filter: projects in the Rift Valley province' 1 @($rift.data).Count
$active = Get-Json '/api/projects?filter[status]=active&sort=-budget_kes'
Check 'Filter: active projects' 6 @($active.data).Count
Check 'Sort: biggest active budget first (KES)' 31000000 ([int64]$active.data[0].attributes.budget_kes)
Check 'Regions (8 provinces and 47 counties)' 55 @(Get-All '/api/regions').Count
$sectors = Get-Json '/api/sectors'
Check 'Sectors' 5 @($sectors.data).Count
$impact = ((Get-Raw '/api/impact').Text | ConvertFrom-Json).data.attributes
Check 'Impact: beneficiaries (published reports only)' 39360 $impact.beneficiaries_reached
Check 'Impact: funds spent KES (published reports only)' 59930000 $impact.funds_spent_kes

"SUMMARY|" + $(if ($script:fails -eq 0) { "ALL $($script:checks) API CHECKS PASSED" } else { "$($script:fails) of $($script:checks) CHECK(S) FAILED" })
