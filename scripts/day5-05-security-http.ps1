<#
.SYNOPSIS
  Probes the site from the outside, as an anonymous visitor, for things that
  must not be reachable and headers that should be present.

.DESCRIPTION
  Usage: powershell -File scripts/day5-05-security-http.ps1 -Base https://athari.ddev.site
         powershell -File scripts/day5-05-security-http.ps1 -Base https://live.example.org -Live
  With -Live it also expects HTTPS, HTTP-to-HTTPS redirect and an HSTS header.
  Output lines: SECHTTP|check|expected|actual|PASS or FAIL.
#>
param(
  [string]$Base = 'https://athari.ddev.site',
  [switch]$Live,
  [switch]$Insecure,
  [string]$HttpBase = ''
)

if ($Insecure) {
  # Local trial stacks use a self-signed certificate. Never use this against the live site.
  Add-Type @"
using System.Net; using System.Security.Cryptography.X509Certificates;
public class TrustAll : ICertificatePolicy { public bool CheckValidationResult(ServicePoint s, X509Certificate c, WebRequest r, int p) { return true; } }
"@
  [System.Net.ServicePointManager]::CertificatePolicy = New-Object TrustAll
}

$script:fails = 0
$script:checks = 0
function Check($what, $expected, $actual) {
  $script:checks++
  $ok = ("$expected" -eq "$actual")
  if (-not $ok) { $script:fails++ }
  "SECHTTP|$what|$expected|$actual|" + $(if ($ok) { 'PASS' } else { 'FAIL' })
}

function Probe($path) {
  try {
    $r = Invoke-WebRequest "$Base$path" -UseBasicParsing -MaximumRedirection 0
    return @{ Status = [int]$r.StatusCode; Headers = $r.Headers; Text = $r.Content }
  }
  catch {
    $resp = $_.Exception.Response
    if ($resp) { return @{ Status = [int]$resp.StatusCode; Headers = $resp.Headers; Text = '' } }
    return @{ Status = 0; Headers = @{}; Text = '' }
  }
}

# Pages and files that must not be readable by an anonymous visitor.
$blocked = @(
  '/user/register', '/user/1', '/admin', '/admin/config', '/admin/people', '/admin/reports/status', '/admin/content/donations',
  '/admin/config/athari/donate', '/node/add/report', '/update.php', '/core/install.php',
  '/sites/default/settings.php', '/sites/default/settings.local.php', '/sites/default/settings.ddev.php', '/sites/default/services.yml',
  '/composer.json', '/composer.lock', '/vendor/autoload.php', '/config/sync/system.site.yml', '/config/sync/athari_donate.settings.yml',
  '/.git/config', '/.env', '/.ddev/config.yaml', '/private/', '/scripts/day4-04-seed-donations.php', '/CLAUDE.md',
  '/web/README.md', '/core/CHANGELOG.txt', '/core/INSTALL.txt', '/sites/default/files/.htaccess',
  '/athari/mpesa/callback/00000000000000000000000000000000'
)
# DDEV's development web server does not apply Drupal's file-protection rules, so these
# are reported as INFO there. The live server must block every one of them (-Live).
$devGaps = '/core/install.php', '/sites/default/settings.php', '/sites/default/settings.local.php',
  '/sites/default/settings.ddev.php', '/sites/default/services.yml', '/core/CHANGELOG.txt', '/core/INSTALL.txt'
foreach ($p in $blocked) {
  if (-not $Live -and $p -in $devGaps) {
    "SECHTTP|GET $p (development server only: blocked by the live server rules)|info|info|INFO"
    continue
  }
  $r = Probe $p
  # The callback path is POST-only: a GET is 405 and must never be 200.
  Check "GET $p is not readable" 'not readable' $(if ($r.Status -in 301, 302, 303, 307, 308 -and $p -eq '/user/register') { 'not readable' } elseif ($r.Status -in 401, 403, 404, 405) { 'not readable' } else { "status $($r.Status)" })
}

# Pages that must work.
foreach ($p in '/', '/projects', '/impact', '/map', '/about', '/donate', '/api', '/api/impact', '/user/login', '/robots.txt') {
  Check "GET $p works" 200 (Probe $p).Status
}

# Headers on a normal page.
$front = Probe '/'
Check 'X-Content-Type-Options: nosniff' 'nosniff' $front.Headers['X-Content-Type-Options']
Check 'X-Frame-Options is set' 'SAMEORIGIN' $front.Headers['X-Frame-Options']
Check 'No PHP version header (X-Powered-By)' 'absent' $(if ($front.Headers['X-Powered-By']) { 'present' } else { 'absent' })
if ($Live) {
  Check 'HSTS header' TRUE ([bool]$front.Headers['Strict-Transport-Security'])
  Check 'X-Generator (Drupal version) header removed' 'absent' $(if ($front.Headers['X-Generator']) { 'present' } else { 'absent' })
  $http = if ($HttpBase) { $HttpBase } else { $Base -replace '^https:', 'http:' }
  try { $r = Invoke-WebRequest "$http/" -UseBasicParsing -MaximumRedirection 0 -ErrorAction Stop; $st = [int]$r.StatusCode } catch { $st = [int]$_.Exception.Response.StatusCode }
  Check 'HTTP redirects to HTTPS' 'redirect' $(if ($st -in 301, 308) { 'redirect' } else { "status $st" })
}
else {
  Check 'X-Generator header (informational: removed at the live web server)' 'info' 'info'
}

# Login page must not reveal whether an account exists.
$body = @{ name = 'nobody-here'; pass = 'wrong'; form_id = 'user_login_form'; op = 'Log in' }
try {
  $page = Invoke-WebRequest "$Base/user/login" -UseBasicParsing -SessionVariable s
  $bid = [regex]::Match($page.Content, 'name="form_build_id" value="([^"]+)"').Groups[1].Value
  $body.form_build_id = $bid
  $post = Invoke-WebRequest "$Base/user/login" -Method POST -Body $body -WebSession $s -UseBasicParsing
  $msg = [regex]::Replace($post.Content, '<[^>]+>', ' ')
  Check 'Failed login does not say which part was wrong' 0 ([regex]::Matches($msg, 'is not recognized as a username|no account|does not exist')).Count
}
catch { Check 'Failed login page responds' 200 0 }

"SUMMARY|" + $(if ($script:fails -eq 0) { "ALL $($script:checks) HTTP SECURITY CHECKS PASSED" } else { "$($script:fails) of $($script:checks) CHECK(S) FAILED" })
