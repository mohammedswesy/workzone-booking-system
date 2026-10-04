# WorkZone security review (Phase 7)

Review date: 2026-10-04. Scope: mass assignment, IDOR, XSS, uploads, rate limiting, role escalation, admin discoverability.

## Findings and fixes

| Area | Finding | Severity | Fix / status |
|------|---------|----------|--------------|
| Mass assignment | `role` / `is_active` are not on `User::$fillable`; registration force-fills `Role::User` only. | Low (mitigated) | Covered by `AdminUserManagementTest` + `RoleBoundaryTest`. |
| Mass assignment | `Workspace` includes `owner_id` in fillable, but store/update FormRequests never accept `owner_id`; controller sets owner from auth user. | Low (mitigated) | Do not add `owner_id` to request rules. |
| IDOR — bookings | Policies authorize view/update/delete; user/owner controllers authorize via policy. | OK | Existing Pest role/booking tests. |
| IDOR — payment proofs | Previously public `/storage` URLs. | High → fixed | Private `local` disk + `payments.proof.show` authorize booker/owner/admin. |
| IDOR — payment confirm/reject | Owner A cannot confirm owner B payments. | OK | `DirectOwnerPaymentTest`. |
| XSS | Inertia/Vue escapes text by default; payment instructions rendered in `<pre>` as text. | OK | Avoid `v-html` for user content (none added). |
| Upload validation | Gallery: image mimes jpeg/png/jpg/webp max 2MB; proofs: jpg/jpeg/png/webp/pdf max 4MB. | OK | Keep mime + size checks; store proofs privately. |
| Rate limiting | Login had custom RateLimiter (5/min); register/booking/proof lacked route throttles. | Medium → fixed | Named limiters `login`, `register`, `bookings`, `payment-proof` applied on routes. |
| Role escalation | Public register ignores forged `role`; owners created only by admin. | OK | Tests assert forging fails. |
| Suspended accounts | Cannot login; workspaces hidden from published scope. | OK | Redirect to suspended page. |
| Invitation tokens | Plain-text setup URL must not hit application logs; expire/single-use. | Medium → fixed | Invitations broker 24h; forgot-password 60m; no Log of setup URL. |
| Admin indexing | Admin UI could be indexed by crawlers if exposed. | Low → fixed | `noindex,nofollow` meta + `X-Robots-Tag` on `/admin*`. |
| PayPal webhook | CSRF excepted; signature verification in gateway. | Info | Keep `PAYPAL_WEBHOOK_ID` set in production. |
| Demo passwords | Seeded `password` accounts. | High in prod | `DatabaseSeeder` no-ops in production. |

## Residual recommendations

1. Prefer object storage (S3) with private ACL for proofs in production.
2. Add CSP headers at the reverse-proxy layer.
3. Monitor failed login / proof-upload rate-limit hits.
4. Rotate invitation links if an admin session is compromised while mail is undeliverable (URL flashed once).
