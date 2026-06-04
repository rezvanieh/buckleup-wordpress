# BuckleUp on Bluehost — Email (SMTP) + DNS deliverability

## Why this is required
- All site email goes through WordPress `wp_mail()`:
  - **Contact form** (`buckleup-core/includes/contact.php`) → emails the business
    inbox (`buckleup_settings['email']`, falls back to `admin_email`) with the
    submitter as Reply-To.
  - **Console app** (`buckleup-app`) → registration/welcome + booking notifications.
- The dev transport (Mailpit mu-plugin `00-mailpit-smtp.php`) is **not deployed**.
- Bare `wp_mail()` on shared hosting falls back to PHP `mail()`/sendmail, which is
  routinely spam-filtered or silently dropped. So configure a real SMTP provider.

## Recommended setup (low volume, free tier is plenty)
1. **Plugin:** FluentSMTP (free, no sending limits of its own) — or WP Mail SMTP.
2. **Provider:** pick one and verify the **domain** (not just a single address):
   - **Brevo** (free 300 emails/day) — easy, good for this volume.
   - **Resend**, **Amazon SES**, **Mailgun**, **SendLayer** — all fine.
3. In the SMTP plugin, set:
   - **From email:** `info@buckleupdriving.ca` (match `buckleup_settings['email']`).
   - **From name:** `BuckleUp Driving School`.
   - SMTP host/port/credentials from the provider, or the provider's API key.
4. Keep "Force From Email" ON so plugin mail doesn't override the verified sender.

## DNS records to publish for `buckleupdriving.ca`
Add these at the **domain's DNS** (wherever the nameservers point — Bluehost DNS
if the domain is registered/parked there, otherwise the registrar). Exact values
come from the email provider's domain-verification screen.

| Type | Host | Value (example — use the provider's) | Purpose |
|---|---|---|---|
| TXT (SPF) | `@` | `v=spf1 include:<provider>.com ~all` | Authorizes the provider to send for you. Merge into ONE SPF record if you already have one. |
| CNAME/TXT (DKIM) | `<selector>._domainkey` | provider-supplied | Cryptographically signs mail. |
| TXT (DMARC) | `_dmarc` | `v=DMARC1; p=quarantine; rua=mailto:dmarc@buckleupdriving.ca; fo=1` | Policy + reporting. Start with `p=none` to monitor, tighten to `quarantine`/`reject`. |

> If you keep Bluehost's own mailbox/webmail for `info@buckleupdriving.ca`, make
> sure the SPF record `include`s both Bluehost's mail and the transactional
> provider (one combined `v=spf1 ... ~all` record — never two SPF records).

## Verify before go-live
1. Use the SMTP plugin's **"Send test email"** → arrives in an external inbox
   (Gmail), lands in **Inbox** not Spam.
2. Submit the live **/contact** form → business inbox receives it, **Reply-To** is
   the submitter, and the honeypot + rate-limit (3 / 10 min) behave.
3. Check the received email's headers show **SPF=pass, DKIM=pass, DMARC=pass**
   (Gmail: "Show original").
