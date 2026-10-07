# Payments runbook (Phase 11)

## Weekly reconciliation

1. Run `php artisan payments:reconcile` (also scheduled daily at 02:00 UTC).
2. Open **Admin → Dashboard** and check the reconciliation card (red = invariants broken).
3. Open **Admin → Audit logs** for unusual confirms, payouts, or method changes.
4. Clear each issue with an adjustment entry + note (never edit/delete ledger rows).

Exit code is non-zero when any invariant breaks.

## Wrong amount received

1. On the booking payment review, enter the **received** amount.
2. If it differs from expected:
   - **Partial**: choose Partial + mandatory note. Booking stays **unpaid**; ask the customer to pay the remainder and submit a new proof if needed.
   - **Overpaid**: choose Overpaid + note. Payment is marked paid; the difference is recorded in `amount_note` / metadata.
3. Password re-confirmation is required.

## Duplicate transfer reference

References must be unique **per platform payment method** (case-insensitive). If a customer reuses a reference on the same method, ask for the bank’s unique transaction id. Cross-method reuse is allowed.

Historical invalid refs (e.g. `"0"`) are reported by reconcile and must not be deleted.

## Refund after payout

1. Do **not** mutate ledger earnings/commissions.
2. Post a **refund** reversal via the ledger service (creates compensating entries).
3. If the owner was already paid out, post an **adjustment** (negative) with a clear note and collect the overpayment operationally.
4. Audit log should show the refund/adjustment actor.

## Lost proof

1. Proofs are private; only booker + admin can download (`Cache-Control: private, no-store`).
2. If the file is missing on disk, reject the pending payment with a reason asking for a fresh upload.
3. Upload attempts per booking are capped; do not bypass the cap without an adjustment process.

## Suspected fraud

1. Do not confirm. Reject with a clear reason.
2. Check audit logs for related admin actions and login events.
3. Check proof reuse warnings (same SHA-256 on another booking).
4. If a platform payment method was changed, verify the change email and audit old/new masked identifiers.
5. Rotate admin passwords / 2FA recovery codes if an admin session may be compromised.

## Admin 2FA lockout recovery

`ADMIN_2FA_ENFORCED` defaults to **false** in `.env.example`. Turn it on only after every admin has enrolled.

When enforced and an admin has **not** completed setup, they are redirected to `/admin/two-factor/setup` (not locked out of the app). After enrollment they must pass the challenge each session.

If an admin loses their authenticator and recovery codes:

```bash
php artisan admin:2fa-reset admin@example.com
```

The command asks for confirmation, clears the encrypted secret/recovery codes, writes an `admin.2fa_reset` audit log entry, and **never prints secrets**. The admin can then sign in and enroll again.

## Commands

```bash
php artisan payments:reconcile
php artisan payments:reconcile --json
php artisan app:security-check
php artisan admin:2fa-reset {email}
```

## Migrations (flag before MySQL)

- `2026_10_05_110000_phase11_payment_hardening.php` — composite unique on transfer refs, proof hash/attempts/amount fields, `audit_logs`, admin 2FA columns. **Reversible. Does not delete invalid references.**
