# WorkZone

Coworking-space booking platform built with **Laravel 12**, **Vue 3**, **Inertia.js**, and **Tailwind CSS**, branded for **Gaza Tashreel**. Guests discover workspaces, reserve time slots, and pay owners directly; owners manage listings and confirm payments; admins oversee users and platform activity.

---

## Features

- Public workspace catalog with search, filters, amenities, galleries, and featured listings
- Time-based booking with pending timeout (scheduler cancels stale pending bookings)
- **Direct-to-owner payments**: bank transfer, mobile wallet, or cash on arrival, with optional proof upload
- Owner dashboards: workspaces, offers/discounts, booking and payment review
- Admin user management: invite owners/admins via set-password links, suspend/reactivate accounts
- Bilingual UI (English / Arabic) with RTL support via `vue-i18n`
- Role-based authorization (policies + middleware)

---

## Stack

| Layer | Technology |
|--------|------------|
| Backend | Laravel 12, PHP 8.2+ |
| Frontend | Vue 3, Inertia.js, Tailwind CSS, Vite |
| Auth | Laravel Breeze (Inertia), Sanctum-ready |
| Testing | Pest, PHPUnit (sqlite `:memory:` in `phpunit.xml`) |
| Code style | Laravel Pint |
| Containers | Laravel Sail (Docker), MySQL 8, Redis |
| Payments (future) | PayPal package present but gated off by default |

---

## Architecture

WorkZone is a **monolithic Laravel application** with an Inertia-driven Vue SPA experience:

- **HTTP**: Controllers return Inertia responses; Ziggy exposes named routes to the frontend.
- **Domain**: Eloquent models, enums (`Role`, `BookingStatus`, `PaymentStatus`, etc.), form requests, and policies.
- **Payments**: A small gateway layer (`ManualPaymentGateway`, optional PayPal) orchestrates booking payment state; proofs are stored on a **private** disk.
- **Scheduling**: `bookings:expire-pending` runs every minute via the Laravel scheduler (required in production).

```text
Browser → Laravel (routes, middleware, policies) → Inertia → Vue pages
                              ↓
                    Eloquent / Services / Queues
```

---

## Roles

| Role | Access |
|------|--------|
| **User** | Browse `/spaces`, create bookings, view payment instructions, upload proof, manage profile |
| **Owner** | CRUD workspaces & offers, review bookings, confirm/reject manual payments |
| **Admin** | Dashboard, users (create owner/admin invitations, suspend/reactivate), workspaces oversight, bookings/reports |

**Account rules**

- Public registration always creates `role=user`; a forged `role` field is ignored.
- Owners and additional admins are created only by an admin (`/admin/users/create`) and receive a **set password** link (password reset token)—never a plain-text password.
- Suspended users cannot log in; their workspaces are hidden from public listings; existing bookings/payments remain.
- Users with bookings/payments cannot be hard-deleted. The last admin cannot be demoted, suspended, or deleted; admins cannot demote/suspend/delete themselves.

---

## Payment model

**Current (Phase 1): direct to owner**

- Funds flow **guest → owner** (bank, wallet, or cash). WorkZone does **not** hold platform funds or charge commission today.
- Each published workspace must define **payment instructions** (text) and **accepted methods** (`bank_transfer`, `wallet`, `cash`).
- Guests see instructions on the booking flow when unpaid. Owners **confirm** (marks paid + confirms booking) or **reject** proof with a reason. Proof is optional for **cash on arrival**.

**PayPal (reserved for future commission model)**

- PayPal checkout would settle to the **platform** account, not the owner’s—so it stays **disabled by default** (`PAYMENT_PAYPAL_ENABLED=false`) and is not shown as a normal guest payment option.
- Do not enable until commission/payout design is explicit.

See `.env.example` for `PAYMENT_*` and `PAYPAL_*` variables.

---

## Installation

### Requirements

- PHP 8.2+, Composer, Node.js 20+, npm
- SQLite (local default) or MySQL/PostgreSQL
- Optional: Docker Desktop + WSL2 (for Sail on Windows)

### Steps

```bash
git clone <repository-url> workzone
cd workzone
composer install
cp .env.example .env
php artisan key:generate
```

**Database (SQLite — default in `.env.example`)**

```bash
touch database/database.sqlite
php artisan migrate
php artisan db:seed
```

**Database (MySQL — typical with Sail)**

Set in `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=workzone
DB_USERNAME=sail
DB_PASSWORD=password
```

Then migrate (and seed) inside Sail or against your MySQL instance.

**Frontend**

```bash
npm install
npm run dev
```

**Application server**

```bash
php artisan serve
```

Or use the Composer `dev` script to run server, queue, logs, and Vite together:

```bash
composer dev
```

### Environment highlights

Copy `.env.example` to `.env` and adjust:

| Area | Notes |
|------|--------|
| `APP_URL` | Public URL of the app |
| `DB_*` | SQLite file or MySQL credentials |
| `BOOKING_PENDING_TIMEOUT_MINUTES` | Pending booking expiry window |
| `FILESYSTEM_DISK` | Use `local`; payment proofs use the private disk |
| `QUEUE_CONNECTION` | `database` locally; run `php artisan queue:work` if you rely on queued jobs |

### Mail (invitations)

Owner/admin invitations use `SetPasswordInvitation`.

| `MAIL_MAILER` | Behavior |
|---------------|----------|
| `log` / `array` | No outbound mail; admin UI shows a **one-time setup URL** |
| `smtp` (or SES, Postmark, Resend, etc.) | Sends email with the reset link |

Configure `MAIL_*` in `.env` for real delivery. Comments in `.env.example` document SMTP placeholders.

### Seeders

```bash
php artisan db:seed
```

`DatabaseSeeder` creates demo users, sample workspaces, and pending bookings. Safe for **local/demo only**—never use default passwords in production.

---

## Running locally

**Option A — PHP + Vite (lightweight)**

```bash
php artisan serve
npm run dev
```

Visit `http://localhost:8000` (or your `APP_URL`).

**Option B — Laravel Sail (Docker)**

Requires Docker. After `composer install`:

```bash
./vendor/bin/sail up
```

First run builds the `laravel.test` image (PHP 8.2). MySQL and Redis start via `compose.yaml`.

```bash
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan db:seed
./vendor/bin/sail npm install
./vendor/bin/sail npm run dev
```

App: `http://localhost` (port `APP_PORT`, default 80). Vite: port `VITE_PORT` (default 5173).

On Windows, use **WSL2** for Sail; `./vendor/bin/sail` is a bash script.

---

## Testing & quality

Tests use **SQLite in-memory** (`phpunit.xml` sets `DB_DATABASE=:memory:`).

```bash
php artisan test          # Pest
vendor/bin/pint --test    # Code style (CI runs this)
npm run build             # Production frontend build
```

GitHub Actions (`.github/workflows/ci.yml`) runs the same checks on push/PR to `main` or `master`.

---

## Project structure

```text
app/
  Actions/          Booking creation and domain actions
  Enums/            Roles, booking/payment statuses
  Http/Controllers/ Admin, Owner, User, Auth, Payment
  Models/           User, Workspace, Booking, Payment, Offer, …
  Policies/         Authorization per model
  Services/         Payments, pricing, offers, workspace gallery
database/migrations/ Schema evolution
resources/js/
  Pages/            Inertia Vue pages by area (Admin, Owner, User, Auth)
  Components/       Shared UI (AppShell, Navbar, …)
  i18n/locales/     en.json, ar.json
routes/web.php      Public, auth, and role-prefixed routes
tests/Feature/      Pest feature tests
compose.yaml        Laravel Sail (MySQL + Redis)
```

---

## Demo accounts (local / demo only)

After seeding, password for all accounts is **`password`**:

| Email | Role |
|-------|------|
| admin@example.com | admin |
| owner@example.com | owner |
| user@example.com | user |

**Do not** deploy these credentials to production.

---

## Data model (ERD)

Core tables requested for documentation:

```mermaid
erDiagram
    users ||--o{ workspaces : owns
    users ||--o{ bookings : makes
    users ||--o{ offers : creates
    workspaces ||--o{ bookings : receives
    workspaces ||--o{ workspace_images : has
    workspaces ||--o{ offers : has
    workspaces }o--|| locations : at
    workspaces }o--o{ amenities : amenity_workspace
    bookings ||--o| payments : has
    users {
        bigint id PK
        string name
        string email UK
        string password
        string role
        string phone nullable
        boolean is_active
        timestamps
    }
    password_reset_tokens {
        string email PK
        string token
        timestamp created_at
    }
    workspaces {
        bigint id PK
        bigint owner_id FK
        bigint location_id FK nullable
        string name
        string slug UK
        text description
        int capacity
        decimal price_per_hour
        text payment_instructions
        json payment_methods
        string status
        timestamps
    }
    locations {
        bigint id PK
        string name
        string city nullable
        decimal lat nullable
        decimal lng nullable
    }
    amenities {
        bigint id PK
        string name UK
        string slug UK
    }
    amenity_workspace {
        bigint id PK
        bigint amenity_id FK
        bigint workspace_id FK
    }
    workspace_images {
        bigint id PK
        bigint workspace_id FK
        string path
        boolean is_primary
        int sort_order
    }
    bookings {
        bigint id PK
        bigint user_id FK
        bigint workspace_id FK
        timestamp start_at
        timestamp end_at
        decimal total_price
        string status
        string payment_status
        timestamps
    }
    payments {
        bigint id PK
        bigint booking_id FK
        string provider
        decimal amount
        string currency
        string status
        string proof_path nullable
        string rejection_reason nullable
        timestamps
    }
    offers {
        bigint id PK
        bigint owner_id FK
        bigint workspace_id FK
        string title
        tinyint discount_percent
        timestamp starts_at nullable
        timestamp ends_at nullable
        boolean is_active
    }
```

---

## Screenshots

<!-- Add screenshots here, e.g. docs/screenshots/home.png -->

| Screen | Description |
|--------|-------------|
| _Placeholder_ | Public home / featured workspaces |
| _Placeholder_ | Workspace detail & booking |
| _Placeholder_ | Owner payment confirmation |
| _Placeholder_ | Admin user invitation |

---

## Future improvements

- Platform commission model and optional PayPal checkout (with clear payout rules)
- Automated email/SMS reminders for pending payments and upcoming bookings
- Richer availability (blackout dates, recurring slots)
- Owner payout reporting and admin financial exports
- Native mobile-friendly PWA or dedicated apps
- Full observability (structured logging, error tracking)

---

## Deployment

Production checklist:

1. **Environment** — Set `APP_ENV=production`, `APP_DEBUG=false`, strong `APP_KEY`, real `APP_URL`, production `DB_*`, mail transport, and `PAYMENT_*` flags. Never enable demo seed accounts.
2. **Dependencies** — `composer install --no-dev --optimize-autoloader`, `npm ci && npm run build`.
3. **Database** — `php artisan migrate --force`.
4. **Storage** — `php artisan storage:link`; ensure private disk for payment proofs is writable and **not** web-public.
5. **Caches** — `php artisan config:cache`, `php artisan route:cache`, `php artisan view:cache` (after env is final).
6. **Queue worker** — If `QUEUE_CONNECTION` is not `sync`, run a supervised worker, e.g. `php artisan queue:work --tries=3`.
7. **Scheduler (required)** — Pending bookings expire via the scheduler. Add a cron entry on the server:

   ```cron
   * * * * * cd /path/to/workzone && php artisan schedule:run >> /dev/null 2>&1
   ```

   This runs `bookings:expire-pending` every minute (see `routes/console.php`).

8. **Backups** — Schedule regular database and `storage/app` backups (payment proofs). Test restores periodically.
9. **HTTPS** — Terminate TLS at your reverse proxy; set trusted proxies if needed.

---

## License

MIT (application code). Laravel and third-party packages retain their respective licenses.
