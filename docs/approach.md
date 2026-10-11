# Approach, decisions and notes

How the store was designed and built. For running it, see the [README](../README.md). The app is at **http://localhost:8080**.

- [Approach](#approach)
- [Decisions](#decisions)
- [Performance](#performance)
- [Still to build](#still-to-build)
- [How AI was used](#how-ai-was-used)

## Approach

1. **Questions before code.** Stack, database, roles, duplicate SKUs, guest carts, fake payments, and what “high traffic” meant. Several answers changed; the big one was [rejecting duplicate SKUs](#duplicate-skus).
2. **Read the sample CSV first.** Expected outcome: 85 created, 7 invalid, 3 duplicates, 2 empty. Those numbers became the importer tests.
3. **One feature branch at a time.** CI → Docker → domain → admin CRUD → CSV import → catalog → cart/checkout/orders → performance. [Conventional Commits](https://www.conventionalcommits.org/), merged to `main` with `--no-ff`.
4. **Test on MySQL.** Feature tests cover auth, validation, import (including a 20,000-row file, a mid-import crash, and a repeat import), search, cart, checkout and orders.
5. **Check it as a user.** Browser pass on the dev stack, plus a 100,000-row import through the real queue worker.

## Decisions

### Stack: Laravel, Inertia, Vue 3

Server-side routing and validation, Vue for the UI, no separate API. Started from Laravel’s Vue starter kit (Fortify, Wayfinder, Tailwind 4).

- **Not Livewire** — awkward for the catalog and import screens.
- **Not a separate SPA** — second deployable and duplicated validation.
- **Not Blade** — too limited for the UI.

### MySQL, including for tests

Needed for InnoDB full-text search, row locks, and `UPDATE … WHERE stock >= ?`. Tests use a separate `testing` database, not SQLite. PostgreSQL would also work.

### Money as integer cents

`price_cents` plus a `Money` value object. No floats. More than two decimal places is rejected, not rounded.

### Categories in their own table

CSV free-text is normalized (trim, capitalize, unique slug). Gives cheap facet counts and `?category=home-office`. A string column or a PHP enum would not.

### Roles: a column and policies

`admin` or `customer`. Gate on `/admin`, `ProductPolicy` on actions. Role is not mass-assignable. `spatie/laravel-permission` is overkill for two fixed roles.

### Products are soft-deleted

Hidden from the store, kept for order history. SKU stays reserved so imports cannot reuse it.

### One validation set for the form and the importer

`ProductRules` and `ProductInput` are shared.

- Normalize first: trim, Unicode, uppercase SKUs, strip a leading `$`, empty → `null`.
- HTML in a name is rejected.
- SQL-looking text is stored as-is (parameter binding).

### Duplicate SKUs

Changed during planning: **reject, don’t upsert.**

- First valid row in the file wins; later repeats are errors.
- A SKU that already exists (including soft-deleted) is an error.
- An invalid first occurrence does not block a later valid one.

Rejected alternatives: last-row or first-row upsert, fail the whole file, an “update existing” switch (possible later).

### CSV imports

Built for 10k–100k+ rows, not the 97-row sample.

- Dedicated `imports` queue and worker; progress page every 2 seconds.
- Streamed with `league/csv` (BOM, quotes, Windows line endings, Windows-1252 → UTF-8).
- Chunks of 1,000: one SKU check, one product insert, one error insert.
- Resumable: each chunk is one transaction; a retry continues from the last committed row.
- Race-safe: unique index clash falls back to row-by-row and reports a duplicate.
- Headers matched by name, not position.

**Measured:** 20,000 generated rows in under 200 queries; 100,000 rows in ~43s on the unoptimized dev image.

Errors live in `product_import_errors` so they can be paginated, filtered, and exported.

### Catalog search

InnoDB `FULLTEXT` on name and description (boolean mode). Every word must match; prefixes match; exact SKU matches. Stopwords are stripped so MySQL does not return nothing. Facet counts respect the current search. Bad query params fall back to defaults (`?q[]=x` used to 500).

**Measured:** 150–500 ms per uncached page with 100,000 products on the dev image. Cached behaviour is [below](#performance).

`LIKE '%term%'` was rejected — it cannot use an index.

### Docker

[serversideup/php](https://serversideup.net/open-source/docker-php/), nginx + PHP-FPM (16 workers). Separate containers for web, default queue, import queue, and scheduler. Bootstrap generates the app key, waits for MySQL, migrates and seeds. `make setup` or one `docker compose` command.

### CI

On every push, not on PR open. A new push cancels the previous run. Two jobs: PHPUnit on MySQL, and lint (Pint, Larastan 7, frontend, TypeScript).

### Placeholder images

The CSV has no images. Each product gets a category-tinted SVG. Real uploads (S3) later.

### Cart, checkout, payments

- Guests: session cart, merged on login/register, quantities capped at stock.
- Checkout: `UPDATE … SET stock = stock - ? WHERE stock >= ?` per item, product IDs sorted to avoid deadlocks. Charge happens outside that transaction; a decline releases the reservation.
- Idempotency key + per-user Redis lock against double submit.
- Unpaid reservations expire after 15 minutes.
- Fake gateway behind `PaymentGateway`. Luhn / expiry / CVC. Test cards: `4242…` approved, `4000…0002` declined, `4000…9995` insufficient funds, `4000…0119` gateway error. Store brand + last 4 only.
- Paid orders in history; confirmation email on the default queue.

## Performance

Code-level safeguards for a busy catalog. Infrastructure (read replica, extra app replicas) is out of scope here.

- **Catalog result cache.** Pages (products, facets, active category) live in Redis for 60 seconds, keyed by `q` / category / sort / page plus a version. The version bumps when a product is created, renamed, recategorized, deleted, or imported — not when checkout changes stock. Live stock is overlaid with one `WHERE id IN (…)`.
- **Rate limits.** 60 catalog searches, 30 cart actions, and 10 checkout attempts per minute. Tighter on checkout, looser on search. Order history is not on the checkout limiter.
- **Cached cart count.** Header badge is cached per user or session and forgotten only when the cart changes. Admin pages skip it and share `0`, so catalog browse does not query `cart_items`.

Also already in place: Redis for cache, sessions and queues; queued mail and imports; checkout lock and conditional stock updates; catalog/order indexes.

## Still to build

- MySQL read replica for catalog reads.

## How AI was used

Built in Cursor. I directed and reviewed; the agent implemented.

I set the stack and the rules: PHPUnit, guest carts, catalog filters/sort/pagination, reject duplicate SKUs, one-command Docker setup, CI on push only. Open questions were settled before a branch started.

The agent wrote the code and tests, ran Pint, Larastan, frontend checks and browser passes, and timed the large imports. One feature branch at a time.

Review caught real bugs: a silent CI format failure, the demo admin seeded before `role` existed, `?q[]=x` 500ing, `composer dev` missing the import queue, and git rewriting CSV line endings. Each was fixed, with a test where it made sense.

No AI comments in the code, as the brief requires.
