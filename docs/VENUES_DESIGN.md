# Phase 12 — Venues & Units (design)

**Status:** STAGE 2–3 — implementing (owner/admin UI + public venue catalog).  
**Branch:** `feature/phase-12-venues-and-units`  
**Out of scope:** availability calendars, maps, QR, online payments, daily/monthly pricing.

---

## 1. Current state audit

Today a **workspace is both the public listing and the bookable unit**. Almost every domain path keys off `workspaces.id`.

### 1.1 `workspaces` columns (as of phase 11)

| Column | Role |
|--------|------|
| `id` | PK; used in routes, FKs, Inertia links |
| `owner_id` | Owner isolation; policies; owner dashboards |
| `name`, `slug` (unique), `description` | Listing identity (slug unused for routing) |
| `location` (legacy string), `location_id` → `locations` | Place / city filters |
| `capacity`, `booking_mode` (`seat` \| `whole`), `price_per_hour` | Inventory + pricing |
| `opening_time`, `closing_time` | Booking window validation |
| `image_url` (legacy), gallery via `workspace_images` | Cover / gallery |
| `status` (`draft` / `published` / `archived`), `featured` | Visibility |
| `payment_instructions`, `payment_methods` | Legacy listing-level payment copy (platform methods supersede for checkout) |

### 1.2 Tables that reference `workspace_id`

| Table | FK behavior | Notes |
|-------|-------------|--------|
| `bookings` | cascadeOnDelete | Overlap / seat locks; all payment & ledger paths |
| `offers` | cascadeOnDelete | Per-workspace active discount |
| `workspace_images` | cascadeOnDelete | Gallery + primary / sort |
| `amenity_workspace` | cascadeOnDelete | Amenity M2M |
| `payments` | — | **No** `workspace_id`; via `booking_id` |
| `owner_ledger_entries` | — | **No** `workspace_id`; via nullable `booking_id` |
| `owner_payouts` | — | Owner-level only |

### 1.3 Services / business logic

| Concern | Location | Workspace fields used |
|---------|----------|------------------------|
| Pricing | `BookingPricingService` | `price_per_hour`, `booking_mode` |
| Discount | `ActiveOfferResolver` | Offers by `workspace_id`; highest valid % |
| Seats / overlap | `SeatAvailability`, `CreateBooking` | `capacity`, `booking_mode`, hours |
| Gallery | `WorkspaceGalleryService` | Images + sync `image_url` |
| Ledger | `OwnerLedgerService` | Owner via `booking.workspace.owner`; breakdown by workspace **name** |

### 1.4 Routes & controllers

| Area | Route names | Binding |
|------|-------------|---------|
| Public list/show | `spaces.index`, `spaces.show` | Show binds **numeric id** (`/spaces/{workspace}`); slug not used |
| Home featured | `home` | `Workspace::published()->featured()` |
| Owner CRUD | `owner.workspaces.*` + image routes | Scoped by `owner_id` |
| Admin CRUD | `admin.workspaces.*` | Any workspace; owner picker on create |
| Bookings | `user.bookings.*`, `owner.bookings.*`, `admin.bookings.*` | Workspace on create / show |
| Offers | `owner.offers.*` | Workspace must belong to owner |
| Reports / CSV | `admin.reports.*`, `admin.owner-accounts.*`, payout statements | Filter / column = workspace name |

### 1.5 Policies & visibility

- `WorkspacePolicy`: public `view`/`viewAny`; update/delete = admin **or** owning owner.
- `BookingPolicy` / `OfferPolicy`: owner access through workspace / offer `owner_id`.
- Public listing/show: `status = published` **and** owner `is_active` (`scopePublished`).
- Soft archive: destroy with bookings → `archived`, not hard delete.
- Isolation: owner A cannot mutate owner B’s rows (policy + query scopes). Controllers enforce published on public show even though policy `view` is open.

**Auth matrix today** lives in README Roles + policies; there is no dedicated `docs/AUTHORIZATION_MATRIX.md` yet (to be added/updated in implementation).

### 1.6 Filters (`WorkspaceFilter`)

`search`/`q`, `location_id`, `city`, `min_price`, `max_price`, `capacity`, `amenities`, `featured`, optional `status`, `per_page`. All evaluate **workspace** rows.

### 1.7 Vue surfaces

- Public: `Home.vue`, `User/Workspaces/Index.vue`, `User/Workspaces/Show.vue`, booking create/edit/show.
- Owner: workspaces CRUD, offers, bookings, dashboard, payout statement.
- Admin: workspaces CRUD, bookings, reports, owner accounts, dashboard.
- Shared: `WorkspaceCard`, `WorkspaceCover`, `WorkspaceFormFields`, `GalleryManager`, `BookingCard`.

### 1.8 Seeders / factories

`WorkspaceFactory` (default `booking_mode = whole`), `BookingFactory`, `OfferFactory`, `DemoSeeder` (3 flat workspaces + amenities/offers/bookings).

### 1.9 Totals that must not change after backfill

Ledger earnings/commission/refunds, payout available balance, report aggregates, CSV statement sums — all derived from **bookings/payments/ledger**, not from listing shape. As long as `bookings.workspace_id` (unit id) is unchanged, money totals stay identical; only **labels** gain venue name.

---

## 2. Target model

### 2.1 Concepts

| Concept | Table | Meaning |
|---------|-------|---------|
| **Venue** | `venues` | Public listing (what `/spaces` cards show) |
| **Unit** | `workspaces` (renamed in product language only) | Bookable room/desk; **same `id`** as today |

Code may keep the `Workspace` model name in phase 12 (less churn) while UI copy says “unit” / “room”. Optional later alias `Unit extends Workspace`.

### 2.2 Proposed schema

#### `venues`

| Column | Type | Notes |
|--------|------|--------|
| `id` | bigint PK | |
| `owner_id` | FK users | Source of truth for ownership |
| `name`, `slug` (unique), `description` | | Slug taken from old workspace on backfill |
| `location_id` | FK locations, nullable | |
| `address` | string, nullable | New; backfill from `locations.address` or legacy `location` string |
| `status` | string | `draft` / `published` / `archived` (reuse `WorkspaceStatus` values or `VenueStatus`) |
| `featured` | bool | Venue-level featured for public home/list |
| timestamps | | |

Indexes: unique `slug`; `(status, featured)`; `owner_id`; `location_id`.

#### `venue_images`

Mirror of `workspace_images`: `venue_id`, `path`, `is_primary`, `sort_order`, timestamps. Cascade on venue delete only when venue has **no** units with bookings (same archive rules as today).

#### `amenity_venue`

M2M pivot (`amenity_id`, `venue_id`), unique pair.

#### `workspaces` (units) — additive columns

| Column | Notes |
|--------|--------|
| `venue_id` | FK `venues`, **required after backfill**, restrict/cascade carefully (see risks) |
| `type` | enum string: `hot_desk`, `private_office`, `meeting_room`, `training_room`, `other` |

**Keep:** `id`, `capacity`, `booking_mode` (`seat`/`whole`), `price_per_hour`, `opening_time`/`closing_time`, `status`, `image_url`, gallery + amenities pivots, timestamps.

**Ownership denormalization (recommended):** keep `owner_id` on `workspaces`, always equal to `venues.owner_id`, synced when venue owner changes. Rationale: existing policies, ledger joins, and `where('owner_id')` scopes stay correct with minimal risk. Venue remains the source of truth in the UI/API.

**Demote / stop writing for listing UX (keep columns for back-compat until a later cleanup):**

- Unit-level `name` / `slug` / `description` / `location` / `location_id` / `featured` — after backfill, public listing uses venue fields; unit may keep a short optional `name` (e.g. “Room A”) for owner UX. Propose: add optional `label` (or keep `name` as unit label) while venue owns marketing name/slug.
- Prefer **venue** `featured` for catalog; unit `featured` ignored in public queries (column retained).

#### Offers

Extend `offers`:

| Column | Notes |
|--------|--------|
| `workspace_id` | Nullable; unit-scoped offer |
| `venue_id` | Nullable; applies to **all units** of the venue |

Constraint: exactly one of (`workspace_id`, `venue_id`) non-null (check constraint or app validation). Overlap rules: unit offers collide per unit; venue offers collide per venue; resolving **active discount** stays in **one** service (`ActiveOfferResolver`):

1. Best active **unit** offer for that workspace, else  
2. Best active **venue** offer for `workspace.venue_id`, else none.

Highest percent wins within each tier; unit tier beats venue tier (explicit override). Document this in code comments + tests.

### 2.3 Inheritance rules (images & amenities)

- **Venue** always has its own gallery/amenities (public hero).
- **Unit** may have extra images/amenities.
- Effective amenities for display = unit amenities if non-empty, else venue amenities.
- Effective gallery for unit card/detail = unit images if non-empty, else venue gallery.
- Cover URL helper updated accordingly (still CSP-safe / self-hosted only).

### 2.4 Type mapping on backfill

| Old `booking_mode` | New `type` | `booking_mode` kept |
|--------------------|------------|---------------------|
| `seat` | `hot_desk` | `seat` |
| `whole` | `other` | `whole` |

Owners/admins can change `type` later without changing seat/whole rules.

### 2.5 Publishing rules

- Venue may be `draft` / `published` / `archived`.
- **Publishing a venue requires ≥ 1 unit with `status = published`** (active for booking).
- Public visibility: venue `published` + owner `is_active` + at least one published unit.
- Unit with bookings: archive only (existing pattern), never hard-delete.
- Suspended owner: venues (and thus units) hidden via owner `is_active` (same as today’s `scopePublished`).

### 2.6 URL strategy

| URL | Behavior |
|-----|----------|
| `GET /spaces` | Lists **venues** (paginated) |
| `GET /spaces/{venue}` | Venue page (bind by **slug**, fallback id) |
| `GET /spaces/{workspace}` **legacy** | If segment matches a **unit id**, **302/301** to venue show with `?unit={id}` preselected |
| Booking create | Still books **one unit**; deep-link `workspace_id` unchanged |

Disambiguation: prefer slug match for venues; numeric-only segments that match `workspaces.id` take the legacy redirect path. Document in routes.

### 2.7 Product behavior (summary)

- **Public list:** venue card — cover, name, city, amenities, unit-type badges, “from X / hour” (min effective price among published units), unit count. Filters (keyword, location, price, capacity, amenities, featured, **new type**) apply at **unit** level; results **grouped by venue**; server pagination; eager-load to avoid N+1.
- **Venue page:** gallery, description, amenities, unit list (type, capacity, mode, price, offer badge, images); booking panel for **selected unit** only (existing pricing/seat/overlap services).
- **Owner:** Add venue → add units; lists for venues and units; dashboard/reports scoped to own `owner_id`; archive units with bookings.
- **Admin:** Same forms + active owner picker; archive; reports with venue totals + per-unit breakdown; ledger/payouts/CSV show **venue + unit** names; **totals unchanged** (snapshot test).
- **Auth:** Owner A cannot touch B’s venues/units; users only published; suspended owners hidden; update policies + new/updated authorization matrix doc.

### 2.8 Alternatives considered

| Option | Decision |
|--------|----------|
| Rename table `workspaces` → `units` | **Defer** — high churn for FKs/tests; product label “unit” is enough for phase 12 |
| Drop `workspaces.owner_id` | **Defer** — keep denormalized sync instead |
| Venue-only offers without unit offers | Rejected — unit overrides needed |
| Soft-delete venues cascading to units | Rejected — archive pattern only |

---

## 3. Migration plan (implementation after approval)

All steps use **reversible** Laravel migrations. **Flag before MySQL run** (see §5). No `*.sql` dump files.

### Step A — Schema (empty tables + nullable FKs)

1. Create `venues`, `venue_images`, `amenity_venue`.
2. Add nullable `venues`-compatible columns on `workspaces`: `venue_id` (nullable FK), `type` (nullable string).
3. Add nullable `venue_id` on `offers`; make `workspace_id` nullable (with app check: one of two set).

### Step B — Backfill (data migration, reversible via down that nulls new FKs / deletes created venues **only if** created by this migration’s marker — prefer `venues` rows tagged or delete where no pre-existing ids)

For each existing workspace:

1. Insert one `venues` row copying: `owner_id`, `name`, `slug`, `description`, `location_id`, `address` (from place/legacy), `status`, `featured`.
2. Copy `workspace_images` → `venue_images`; copy `amenity_workspace` → `amenity_venue`.
3. Set `workspaces.venue_id`, `type` from booking_mode map; keep unit `id` and booking FKs untouched.
4. Optionally set unit `name` to a default label (`"Unit"` / localized) **or** keep original name as unit label while venue has the marketing name (same string twice is OK for v1).

**Idempotent:** skip workspaces that already have `venue_id`.

### Step C — Enforce constraints

1. `venue_id` NOT NULL on `workspaces`.
2. `type` NOT NULL with default `other`.
3. Offers check: exactly one of venue/unit set.

### Step D — Application cutover (code, not SQL)

Filters, controllers, Vue, policies, seeders, factories, smoke tests — in small PRs/steps, tests after each.

### Step E — Totals snapshot test

Feature test: seed known bookings/ledger → capture sums → run backfill (or assert on migrated DB) → assert identical ledger/report totals; assert labels can include venue name.

---

## 4. Implementation steps (after approval only)

1. Migrations A–C + backfill command/migration + snapshot test.  
2. Models/enums (`Venue`, `VenueStatus`/`WorkspaceType`, relations) + `ActiveOfferResolver` venue tier.  
3. Policies + authorization matrix doc.  
4. Public list/show + legacy redirect + filters (unit-level → group by venue).  
5. Booking panel wired to selected unit (reuse services).  
6. Owner venue/unit CRUD + publish guard.  
7. Admin parity + reports/CSV venue+unit columns.  
8. Seeders/factories/demo catalog (venues with several units).  
9. i18n AR/EN, dark mode, responsive UI.  
10. Extend Playwright smoke for every new page; Pest + Pint + `npm run build` + smoke each milestone.

---

## 5. Risks & MySQL flags

| Risk | Mitigation |
|------|------------|
| **Money totals drift** | Never change booking/payment/ledger FKs; snapshot test before/after backfill |
| **Offer overlap rules** ambiguous with venue+unit | Single resolver + explicit precedence tests |
| **Slug collision** venue vs old workspace slug | Venue takes existing slug; unit slug unused for routing (or suffix `-unit` if uniqueness required on both) |
| **`offers.workspace_id` NOT NULL today** | Making nullable is a **schema change** — flag on MySQL; deploy with app that validates XOR |
| **Adding NOT NULL `venue_id`** | Only after backfill; flag downtime/lock on large tables |
| **Cascade delete** venue → units | Prefer **restrict** if units exist; archive venue instead of delete |
| **N+1 on grouped venue list** | Eager-load units, images, amenities, active offers; aggregate “from price” / types in query or careful collection map |
| **Legacy `/spaces/{id}`** | Dual resolution + redirect tests |
| **Denormalized `owner_id`** drift | Sync on venue update; assert in policy tests |
| **Payment instructions on unit** | Leave columns; no product change this phase |
| **Opening hours only on unit** | Correct for booking; venue has no hours |
| **Featured** on both levels | Public uses venue.featured only |
| **Admin reports UI** | Add venue grouping without changing sum queries’ definitions |
| **Playwright / demo data** | Seed multi-unit venues so pagination and unit picker are exercised |

### Flagged before MySQL production run

1. `offers.workspace_id` drop NOT NULL + add `venue_id`.  
2. `workspaces.venue_id` null → NOT NULL after backfill (table rewrite / lock possible).  
3. New FKs/indexes on `venues`, `venue_images`, `amenity_venue`, `workspaces.venue_id`, `offers.venue_id`.  
4. Backfill volume = 1 venue + image/amenity copies per existing workspace (usually small).

---

## 6. Explicit non-goals (phase 12)

- Map / geolocation UI  
- QR check-in  
- Gateway / online card payments  
- Daily or monthly pricing plans  
- Multi-unit cart / book several units in one checkout  
- Renaming DB table `workspaces` → `units`

---

## 7. Approved decisions (STEP 0 + amendments)

1. **Naming:** Keep Eloquent `Workspace` for units. Document everywhere: **Venue = building, Workspace = unit**.
2. **Owner sync:** Denormalized `workspaces.owner_id` stays; synced in **one** place (action) inside a DB transaction. `payments:reconcile` gains a consistency check + test. Changing venue owner when bookings/offers exist requires **explicit admin action** + confirmation + `audit_logs` entry + update `offers.owner_id`. Historical ledger rows keep their original `owner_id`.
3. **Offers:** Most specific wins (unit over venue), **never stacked** (even if unit % is lower). One service (`ActiveOfferResolver`). Overlap validation per scope (unit vs venue).
4. **URLs:** Legacy `/spaces/{unitId}` **301** → venue page `?unit=`. Venue slugs: unique, editable, **must contain ≥1 non-digit**. Old slugs redirect via `venue_slug_redirects`.
5. **Binding:** Venue routes bind by **slug**.

### Amendments

| # | Rule |
|---|------|
| a | Pre-flight: backfill **stops with a report** if any `owner_id` NULL, booking→missing workspace, or slug collisions. Commands: `venues:preflight` (read-only counts), `venues:verify` (post-migration). |
| b | Backfill **copies image rows** (same paths) to `venue_images`; import legacy `image_url` as primary if no gallery. Delete physical file only when no other image row references that path. |
| c | List **paginates venues** with ≥1 matching unit; each venue once; “from X” without N+1 (incl. offers). |
| d | Archiving a unit with **future pending/confirmed** bookings is **blocked**; past bookings untouched. |
| e | Do **not** drop `opening_time`/`closing_time` or any existing column. `down()` best-effort; real rollback = restore backup. |
| f | CSV/reports/dashboards/statements add venue + unit names; totals identical (snapshot test). |
| g | Seeders/factories/demo multi-unit venues; `docs/AUTHORIZATION_MATRIX.md`; Playwright extended. |

### Implementation stages

| Stage | Scope | Gate |
|-------|--------|------|
| **1** | Schema, preflight, backfill, verify, legacy redirects, minimal UI still works, totals snapshot | **STOP** — flag migrations; run preflight + migrate on data copy |
| **2** | Owner/admin venue + unit CRUD/archive | After stage 1 approval |
| **3** | Public venue page, search grouping, booking panel, offers UI, reports columns | Continues after stage 2 in same pass unless flagged |

---

## 8. Changelog

| Date | Change |
|------|--------|
| 2026-10-05 | STEP 0 design drafted. |
| 2026-10-05 | STEP 0 **approved** with decisions 1–5 and amendments a–g; status → implementing stage 1. |
| 2026-10-05 | Clarified: unit offer always wins over venue (no stacking); admin owner-transfer audit + offer `owner_id` update; slug redirects table; image path refcounting; archive block for future pending/confirmed; `venues:preflight` / `venues:verify`. |
| 2026-10-05 | **STAGE 1 landed (code):** schema migrations, preflight/backfill/verify commands, owner sync action, offer resolver precedence, legacy 301 redirects, EnsureVenueForWorkspace for create paths, totals/reconcile tests. **Awaiting operator:** run `venues:preflight` then migrate on a data copy. |
| 2026-10-05 | Stage 1 **approved on real local data** (preflight/verify OK, totals unchanged). |
| 2026-10-05 | **STAGE 2–3 landed:** owner/admin venue+unit CRUD with publish checklist; public venue list/show + `?unit=` booking panel; venue/unit offers; reports/CSV venue+unit columns; DemoSeeder multi-unit; `EnsureVenueForWorkspace` removed (legacy workspace POST uses `CreateVenueWithUnit`); Playwright + Pest coverage. |

### Stage 1 — flagged migrations (run on a copy first)

```text
php artisan venues:preflight
php artisan migrate --path=database/migrations/2026_10_05_120000_create_venues_tables.php
php artisan migrate --path=database/migrations/2026_10_05_120100_add_venue_fields_to_workspaces_and_offers.php
php artisan migrate --path=database/migrations/2026_10_05_120200_backfill_venues_from_workspaces.php
php artisan migrate --path=database/migrations/2026_10_05_120300_enforce_workspace_venue_not_null.php
php artisan venues:verify
```

Or simply `php artisan migrate` after preflight is OK. Real rollback = **restore backup** (`down()` is best-effort only).

---

*Stage 1 implementation follows. Do not run MySQL production migrate until preflight is clean on a data copy.*
