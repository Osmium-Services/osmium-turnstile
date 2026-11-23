---
title: Protect your forms from spam bots
tags: turnstile, cloudflare, spam, bots, captcha, form, site key, secret key
---

**Cloudflare Turnstile** checks that a form is being filled in by a person, without asking visitors to solve puzzles.

## Set it up

1. In your Cloudflare account, create a Turnstile widget and copy its **Site Key** and **Secret Key**.
2. Open **Cloudflare Turnstile Settings**, paste both keys and tick **Enabled**.
3. Press **Save Settings**.

It then protects every form that supports it, such as the product review form. How the widget looks (a checkbox, a small badge or nothing) is set on the widget in the Cloudflare dashboard, not here.

If Cloudflare cannot be reached, forms still work. Only a form that fails the check is refused.

## Check it is working

**Checks in the last 30 days** counts the results for each form:

- **Passed** should rise with real submissions.
- **Failed** and **No token** are bots being blocked.
- Many **No token** results from real visitors mean the widget is not loading on the page.
- **Cloudflare unreachable** means the check could not be made.
