# WorkZone security review

Review date: 2026-10-05 (Phase 11 payment hardening). Prior: Phase 7 (2026-10-04).

## Phase 11 findings and fixes

| # | Area | Finding | Severity | Fix / status |
|---|------|---------|----------|--------------|
| A1 | Transfer reference | Live row payment `#1` had `transfer_reference = "0"`. Weak validation allowed it. | High | `TransferReferenceRule`: trim, 6–64 chars, ≥4 distinct, not all zeros/repeated, `[A-Za-z0-9-]`, uniqueness case-insensitive **per platform payment method**. Invalid existing rows reported by `payments:reconcile` (not deleted). |
| A2 | Timestamps | `metadata.confirmed_at` was hand-formatted via `toIso8601String()` separately from `paid_at`, risking local clocks labeled as UTC. | High | App storage timezone is **UTC**; display timezone **Asia/Gaza**. `MarkBookingPaid` sets `paid_at` and `metadata.confirmed_at` from the **same** `AppTimezone::now()` instant. |
| B3 | Amount confirm | Admin could confirm without entering received amount. | High | Confirm requires `received_amount`. Mismatch blocked unless `partial`/`overpaid` + mandatory note. Partial keeps booking unpaid. |
| B4 | State machine | No explicit transition map; race risk on double confirm. | High | `PaymentStateMachine` + `lockForUpdate`; illegal transitions → validation error; confirm idempotent. |
| B5 | Re-auth | Sensitive money actions lacked password re-confirm. | High | `password.confirm` (15m window) on confirm/reject, payouts approve/pay/reject, platform methods/settings, role update, password reset. |
| B6 | Platform methods | Highest-risk change had no audit/email. | High | Password confirm + audit log (masked identifiers) + email to admin. |
| C7 | Audit trail | No append-only audit log. | High | `audit_logs` table + `/admin/audit-logs` + CSV; model forbids update/delete. |
| C8 | Ledger | Entries were mutable. | High | `OwnerLedgerEntry` throws on update/delete; corrections via adjustments only. |
| C9 | Reconciliation | No daily invariant check. | Medium | `php artisan payments:reconcile` (daily schedule) + dashboard card (red when failing). |
| D10 | Uploads | Mime-only checks; proofs lacked hardened headers. | High | Content validation + re-encode (strip EXIF), random names, private disk, `nosniff` + `private, no-store`, attempt caps. |
| D11 | Proof reuse | Same file could be attached silently. | Medium | SHA-256 stored; admin warned (not auto-rejected) on reuse. |
| E12 | Admin 2FA | No TOTP. | High | Built-in TOTP + recovery codes; enforceable for admin; idle session timeout. |
| E13 | Prod hardening | No automated security gate. | Medium | Security headers middleware + `php artisan app:security-check`. |
| E14 | Money math | Float risk in some paths. | Medium | `Money` BCMath + property-style pricing/commission tests. |

## Residual recommendations

1. Prefer object storage (S3) with private ACL for proofs in production.
2. Enforce `ADMIN_2FA_ENFORCED=true` in production after admins enroll.
3. Review any historical invalid `transfer_reference` rows listed by reconcile; correct operationally, do not delete payment history.
4. Keep `APP_DEBUG=false`, secure cookies, and HTTPS/HSTS at the reverse proxy.

## Earlier Phase 7 notes (still applicable)

Mass assignment, IDOR booking policies, invitation token hygiene, admin `noindex`, PayPal webhook CSRF exception — see prior rows in git history for Phase 7 detail.
