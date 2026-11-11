# Turnstile service

Cloudflare Turnstile as the site's bot protection for public forms (repo `Osmium-Services/osmium-turnstile`,
v1.2.6<!--v:turnstile-->, category security-privacy). Turnstile is a form widget only: no Cloudflare proxy, CDN or DNS change.
Settings are under Content > Turnstile; keys live in `app/config/services/turnstile.json.php`, which updates leave alone.
Part of [[Service Catalogue]].

## How a form is protected

A form does not name Turnstile. It fires two hooks ([[Hooks Reference]]) and any protection service answers them, so
swapping providers later is a settings change, not a form change.

- **`form.widget`** returns the widget markup for the form's page, or nothing when Turnstile is off or has no site key.
- **`form.verify`** is asked by the form's submit endpoint. Turnstile answers `true` (passed), `false` (failed or no token)
  or nothing (off, or Cloudflare unreachable). The form refuses only on `false` and accepts when nobody answers.

The review and restock forms use it. Its own checks (honeypot, one-use token, 4-second minimum, three per IP per hour) always
run as well. Turning Turnstile off or uninstalling it leaves those in place. Other forms can still call
`TurnstileConfig::verify($_POST['cf-turnstile-response'] ?? '')` directly.

## What a check can come to

| Outcome | Form answer | Logged | Counted |
|---|---|---|---|
| passed | `true` | no | yes |
| failed (Cloudflare said no) | `false` | yes, with Cloudflare's error codes | yes |
| missing (no token posted) | `false` | no | yes |
| unreachable (no answer, or nonsense) | nothing: **fails open** | yes | yes |

Failing open means a Cloudflare outage cannot lose a customer's review; the form's own checks still apply. The settings
page shows a 30-day table per form from `turnstile_checks` (day, form, outcome, total; 90 days kept). Passed should rise
with real submissions. Many "No token" results from real visitors mean the widget is not loading.

## Why the submit button is held (1.1.1)

The invisible widget fetches its token about 1.6 seconds after the page loads. A form posted sooner went out with no token
and was refused, then the retry worked. The widget script now disables the form's submit buttons until Turnstile answers
(or errors), re-disables them if a token expires, and releases them after 10 seconds regardless, so an ad blocker or an
outage cannot trap a visitor. The script runs before the form's own buttons are parsed, so it looks the buttons up each
time rather than once.

## Setup and testing

- Create the widget in Cloudflare with the hostnames of every site that uses it (a staging and a live widget keep the
  analytics apart). The widget mode (visible box, badge or invisible) is set only in Cloudflare; the service has no mode setting (removed in v1.2.4, it never did anything).
- Enabled with no **site key**: no widget appears, so every review is refused as "No token". Untick Enabled, or fill the key.
- Local testing: Cloudflare publishes dummy keys, e.g. site key `1x00000000000000000000AA` with secret
  `1x0000000000000000000000000000000AA` (always passes). It renders a visible "Success! For testing only" box whatever the
  mode, which is expected. Real keys are invisible.
- The Cloudflare dashboard analytics lag by several minutes; the settings page counts are instant.
- Only tagged releases install, so tag `vX.Y.Z` after a version bump ([[Osmium Store - Operations]]).
