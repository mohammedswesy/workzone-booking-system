# WorkZone

Coworking-space booking platform (Laravel 12 + Vue 3 + Inertia + Tailwind), branded with **Gaza Tashreel**.

## Roles

- **User** — browse spaces, book slots, pay the owner directly, upload proof
- **Owner** — manage workspaces, offers, confirm/reject payments, booking status
- **Admin** — platform oversight, users, reports

## Payments (product model)

Payments go **directly from the guest to the owner** (bank transfer, mobile wallet, or cash on arrival). WorkZone only organizes bookings: **no commission and no platform-held funds** for now.

Each published workspace must define:

1. **Payment instructions** (text — IBAN, wallet number, cash notes, etc.)
2. **Accepted methods** (`bank_transfer`, `wallet`, `cash`)

Guests see those instructions on their booking page when unpaid. Owners confirm receipt (marks paid + booking confirmed) or reject proof with a short reason the guest can see. Proof is optional for **cash on arrival**.

### PayPal (kept, not in the main UI)

PayPal integration code remains for a **future commission model**. A PayPal checkout would settle to the **platform** PayPal account, not the owner’s — so it is **disabled by default** (`PAYMENT_PAYPAL_ENABLED=false`) and is **not presented** as a normal payment option. Do not enable it until there is an explicit platform payout/commission design.

## Stack

- Laravel 12, PHP 8.2+
- Vue 3, Inertia.js, Tailwind CSS, vue-i18n (AR/EN + RTL)
- Pest, Vite

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
# configure DB, then:
php artisan migrate
php artisan db:seed
npm install
npm run dev
php artisan serve
```

Demo accounts (seeded, password `password`):

| Email | Role |
|-------|------|
| admin@example.com | admin |
| owner@example.com | owner |
| user@example.com | user |

## Tests

```bash
php artisan test
npm run build
```

## License

MIT (application code). Laravel framework licenses apply to framework files.
