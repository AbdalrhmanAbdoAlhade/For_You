# For_You API Guide

REST API for the For_You store and influencer affiliate program.

- **Base URL (local):** `http://localhost:8000/api`
- **Content type:** `application/json` (image uploads use `multipart/form-data`)
- **Authentication:** Laravel Sanctum bearer tokens
- **Postman collection:** [`postman/For_You_API.postman_collection.json`](../postman/For_You_API.postman_collection.json)

## Overview

| Item | Value |
| :--- | :--- |
| Framework | Laravel 13 / PHP 8.4.1+ |
| Auth | Sanctum personal access tokens |
| Default locale | Arabic (`ar`), English available via `Accept-Language: en` |
| Timezone | `config('app.timezone')`, default `Africa/Cairo` |
| Money | EGP, stored as decimal strings and returned as floats in reports |

Send `Accept: application/json` on every request. Send `Accept-Language: ar`
or `en` to control translated `message` fields.

---

## Authentication

### Register (customer)

`POST /api/register`

```json
{
  "name": "Ahmed Ali",
  "email": "ahmed@example.com",
  "phone": "01012345678",
  "password": "secret123",
  "password_confirmation": "secret123"
}
```

Returns `201` with `{ user, token }`.

### Login

`POST /api/login`

```json
{ "email": "ahmed@example.com", "password": "secret123" }
```

Returns `{ user, token }`. The token is a Sanctum bearer token:

```http
Authorization: Bearer <token>
```

### Social login

| Method | Path | Description |
| :--- | :--- | :--- |
| GET | `/api/auth/{provider}/redirect` | Web redirect (`google`, `apple`) |
| GET | `/api/auth/{provider}/callback` | Web callback |
| POST | `/api/auth/{provider}/token` | Mobile token login |

For Google send `{ "id_token": "..." }`. For Apple send `id_token` or
`access_token`. The API validates the Google token audience against
`GOOGLE_CLIENT_IDS` and only allows active customers to use social login.

Web redirects are stateless, so the API issues a random `state` stored in the
cache for 10 minutes and requires the same `state` on the callback. Missing,
expired or mismatched state returns `401`. Apple `id_token` claims (`aud`,
`iss`, `exp`) are validated locally before the token is accepted.

### Account

| Method | Path | Description |
| :--- | :--- | :--- |
| GET | `/api/auth/me` | Current user |
| POST | `/api/auth/logout` | Revoke current token |
| PUT/PATCH | `/api/auth/profile` | Update profile (use `POST` + `_method=PUT` for avatar upload) |
| POST | `/api/auth/change-password` | Change password and revoke other sessions |
| POST | `/api/auth/forgot-password` | Send reset link |
| POST | `/api/auth/reset-password` | Reset with token |

Profile fields: `name`, `email`, `phone`, `avatar`, `current_password`.
Changing email requires `current_password` for accounts that have a password,
and resets `email_verified_at` because the new address has not been verified.
Passwordless social accounts cannot change their email from the API (`403`);
they must set a password first. Avatar uploads are capped at 2000×2000 px.

Soft-deleted accounts (influencers/employees) release their unique email and
phone so the same identifiers can be reused by a new account. The original
values are preserved in `users.deleted_email` / `users.deleted_phone` for
support and audit; the live `email`/`phone` columns are replaced with
`deleted+{id}@deleted.invalid` and `deleted-{id}`.

---

## Roles and permissions

Three user types exist: `customer`, `influencer`, `employee`, plus `admin`
(Super Admin). Admins bypass permission checks; employees are checked against
their roles.

Middleware aliases:

- `auth:sanctum` — valid bearer token
- `active` — account is not disabled
- `influencer` — user type must be `influencer`
- `admin` — user type must be `admin` or `employee`
- `permission:a,b` — any of the listed permissions is enough

### Permission slugs

| Group | Permissions |
| :--- | :--- |
| Products | `products.view`, `products.create`, `products.edit`, `products.delete` |
| Categories | `categories.view`, `categories.create`, `categories.edit`, `categories.delete` |
| Orders | `orders.view`, `orders.update`, `orders.manage`, `orders.cancel` |
| Customers | `customers.view`, `customers.manage` |
| Influencers | `influencers.view`, `influencers.create`, `influencers.edit`, `influencers.delete`, `influencers.products`, `influencers.links`, `influencers.wallets`, `influencers.commissions` |
| Withdrawals | `withdrawals.view`, `withdrawals.approve`, `withdrawals.pay` |
| Payments | `payments.view`, `payments.manage`, `payments.confirm`, `payments.refund` |
| Shipping | `shipping.view`, `shipping.manage` |
| Employees | `employees.view`, `employees.create`, `employees.edit`, `employees.delete`, `roles.manage`, `permissions.manage` |
| Reports | `reports.view`, `reports.sales`, `reports.influencers` |
| Content | `dashboard.view`, `banners.*`, `coupons.*` |

Seeded roles: `super-admin`, `products-manager`, `orders-manager`,
`influencers-manager`, `finance-manager`. Run `php artisan migrate --seed` to
create permissions, roles and the Super Admin from `ADMIN_EMAIL` /
`ADMIN_PASSWORD`.

---

## Order flow

### Status machine

```text
new → confirmed → processing → ready_for_shipping → shipped → delivered
  │        │            │                │              │
  └────────┴────────────┴────────────────┘              └──→ returned
                    cancelled
```

Allowed transitions:

| From | To |
| :--- | :--- |
| `new` | `confirmed`, `cancelled` |
| `confirmed` | `processing`, `cancelled` |
| `processing` | `ready_for_shipping`, `cancelled` |
| `ready_for_shipping` | `shipped`, `cancelled` |
| `shipped` | `delivered`, `returned` |
| `delivered` | `returned` |
| `cancelled` / `returned` | terminal |

Any other transition returns `422`.

### Create order

`POST /api/orders` (or `POST /api/checkout` to create from the cart).

The `Idempotency-Key` header is **required** for `POST /api/orders`:

```http
Idempotency-Key: 4c1f8c4e-2b0e-4c9f-9a2e-1d0f5a7b8c90
```

```json
{
  "items": [
    { "product_id": 1, "quantity": 2 }
  ],
  "customer_name": "Ahmed Ali",
  "customer_phone": "01012345678",
  "customer_email": "ahmed@example.com",
  "shipping_address": "12 Nile Street, Cairo",
  "city": "Cairo",
  "country": "Egypt",
  "coupon_code": "WELCOME10",
  "payment_method": "manual",
  "ref": "ABC123"
}
```

- The same key with the same body replays the original `201` response and adds
  `Idempotent-Replayed: true`.
- The same key with a different body returns `422`.
- A concurrent request with the same key returns `409` while the first is being
  processed.
- Successful responses are cached for 7 days.

`POST /api/checkout` accepts `address_id` instead of the customer/address fields
and empties the cart in the same transaction. The cart row is locked, so two
simultaneous checkouts cannot create duplicate orders.

### Other order endpoints

| Method | Path | Description |
| :--- | :--- | :--- |
| GET | `/api/orders` | Customer orders (paginated) |
| GET | `/api/orders/{id}` | Customer order details |
| POST | `/api/orders/{id}/cancel` | Cancel a cancellable order |
| POST | `/api/orders/{id}/pay` | Start/retry payment (see Payments) |
| POST | `/api/checkout/quote` | Price quote without creating an order |

---

## Commission flow

1. A customer opens a promotional link: `GET /api/track/{code}`. The API records
   a click at most once per visitor per `LINK_CLICK_TTL_MINUTES`.
2. The frontend stores the returned `ref` and sends it when creating the order.
3. The order stores `influencer_id`, `promotional_link_id` and the effective
   commission percentage per line.
   A `NULL` percentage on `influencer_product` means "use
   `INFLUENCER_DEFAULT_COMMISSION`"; `0` means eligible with a zero
   commission. `NULL` on `order_items.commission_percentage` (frozen at order
   time) means the line is not eligible at all.
4. When the order becomes `delivered`, a commission row is created with status
   `pending` and `available_at = now + INFLUENCER_COMMISSION_HOLD_DAYS`.
5. The hourly `commissions:release` command moves due commissions to `approved`
   and credits the influencer wallet.
6. Cancelling or returning an order reverses commissions. Pending commissions
   are cancelled; already-earned commissions are debited (the wallet may go
   negative, representing debt).

Commission endpoints:

| Method | Path | Description |
| :--- | :--- | :--- |
| GET | `/api/influencer/dashboard` | Sales, commissions, wallet summary |
| GET | `/api/influencer/products` | Linked products and percentages |
| GET | `/api/influencer/percentage` | Default and per-product percentages |
| GET | `/api/influencer/links` | Promotional links with clicks/conversions |
| GET | `/api/influencer/sales` | Sales and commission totals (no customer PII) |
| GET | `/api/influencer/wallet` | Balance, held commissions, transactions |
| POST | `/api/influencer/wallet/withdraw` | Request a withdrawal |
| GET | `/api/influencer/wallet/withdrawals` | Own withdrawal requests |

"Sales" everywhere (admin dashboard, reports, influencer dashboard/sales and
promotional links) means **`delivered` orders only** (`Order::SALE_STATUSES`).
`NON_SALE_STATUSES` (`cancelled`, `returned`) is still used for coupon usage
limits, not for revenue.

---

## Withdrawal flow (four-eyes)

Statuses: `pending → approved → paid`, or `pending → rejected`.

```text
Influencer requests withdrawal (pending)
        │
        ▼
Approver (withdrawals.approve) approves or rejects
        │
        ▼
Payer (withdrawals.pay) marks paid and stores transfer_reference
```

When `WITHDRAWAL_FOUR_EYES=true`, the employee who approved a request cannot be
the one who marks it paid. Requesting a withdrawal validates:

- amount ≥ `WalletService::MIN_WITHDRAWAL`
- amount ≤ available balance (balance minus pending withdrawals)
- allowed method: `vodafone_cash`, `instapay`, `bank_transfer`, `orange_cash`,
  `etisalat_cash`

Endpoints:

| Method | Path | Permission |
| :--- | :--- | :--- |
| GET | `/api/admin/withdrawals?status=` | `withdrawals.view` |
| PATCH | `/api/admin/withdrawals/{id}/process` | `withdrawals.approve` or `withdrawals.pay` |

```json
{ "status": "approved", "admin_note": "Verified" }
```

```json
{ "status": "paid", "transfer_reference": "BANK-2026-0001" }
```

All wallet changes run inside a transaction with row locks. Audit entries are
written for approve/reject/pay actions.

---

## Payments

### Payment initiation

`POST /api/orders/{id}/pay`

```json
{ "gateway": "manual" }
```

`gateway` is optional and must be one of the keys in
`config/payments.php` (`manual`, `easykash`). When omitted, the payment method
already stored on the order is used.

Response:

```json
{
  "message": "تم تجهيز الدفع",
  "payment": {
    "order_id": 12,
    "order_number": "ORD-2026-000012",
    "payment_id": 12,
    "gateway": "manual",
    "status": "pending",
    "transaction_reference": "MAN-XXXXXXXXXXXXXX",
    "payment_url": null,
    "instructions": "سيتم تأكيد الدفع يدويًا من إدارة المتجر بعد التحويل.",
    "amount": 250.0,
    "currency": "EGP"
  }
}
```

Rules:

1. Only the order owner can initiate payment; other customers get `404`.
2. The order must not be `cancelled`/`returned` → `422`.
3. The payment must be `pending` or `failed`; `paid`/`refunded` → `422`.
4. Requesting a different gateway updates `payments.payment_method` and the
   initiated reference is stored in `payments.gateway_response`.
5. `manual` never returns a payment URL and is confirmed later by an admin.
6. `easykash` returns a `payment_url` when `EASYKASH_INITIATE_URL` is set.
7. EasyKash URLs are signed: `signature` = HMAC-SHA256 over the sorted query
   parameters (`order_number`, `amount`, `currency`, `reference`, `timestamp`,
   `merchant_id`) using `EASYKASH_INITIATE_SECRET` (falls back to
   `EASYKASH_WEBHOOK_SECRET`). `amount` is sent with two decimals.
8. Validation and row locks run in a short transaction; the gateway call itself
   runs outside any transaction.

### Manual confirmation

`PATCH /api/admin/payments/{id}/status`

```json
{ "status": "paid", "transaction_id": "MANUAL-0001" }
```

`transaction_id` is required when marking a payment paid. Statuses: `paid`,
`failed`, `refunded`. Confirmation requires `payments.confirm`; refunds require
`payments.refund`. Retrying a failed payment is supported: `failed → paid` is
allowed.

### Payment webhook

`POST /api/webhooks/payments/{gateway}` (public, signature-protected)

Supported webhook gateway: `easykash` (skeleton). `manual` does not accept
webhooks.

Headers:

```http
Content-Type: application/json
X-EasyKash-Signature: <hex hmac-sha256>
```

The signature is `hash_hmac('sha256', rawBody, EASYKASH_WEBHOOK_SECRET)`.
Payload:

```json
{
  "order_number": "ORD-2026-000001",
  "amount": 250.00,
  "status": "paid",
  "transaction_id": "EK-TXN-001",
  "message": "optional"
}
```

Rules:

1. Invalid signature → `401`.
2. Unknown gateway → `404`.
3. Unknown order → `200` with `status: ignored` (audit entry written, so the
   gateway stops retrying).
4. Amount differing from `orders.total` beyond `payments.amount_tolerance`
   (0.01) → `422` and an audit entry. This applies to both paid and failed
   events.
5. A webhook whose gateway differs from `payments.payment_method` → `200` with
   `status: ignored` and an audit entry.
6. Replaying a paid event is idempotent → `200` with `status: already_paid`.
7. Failed/cancelled events mark the payment `failed` **without cancelling the
   order**. The order stays `new` so the customer can retry via
   `POST /api/orders/{id}/pay`; if they do not, `orders:expire-unpaid` cancels
   it after `UNPAID_ORDER_TTL_HOURS`.
8. Unsupported/pending events return `200` with `status: ignored`.
9. A paid event that arrives **after** the order was cancelled/returned (for
   example a late EasyKash confirmation) is recorded: the payment becomes
   `paid`, an `payment.webhook.late_paid` audit entry is written, and the
   response is `200` with `status: needs_refund`. The order status is not
   changed; finance must refund manually.
10. Replay protection is optional: when `EASYKASH_WEBHOOK_TOLERANCE` > 0 the
    signed payload must include `timestamp` within that many seconds, otherwise
    the webhook is rejected with `401`.

To add a real gateway: implement `App\Contracts\PaymentGatewayInterface`,
register it in `PaymentGatewayManager`, and add credentials under
`config/payments.php`. Keep `initiate()` free of external calls unless the
gateway is enabled in the target environment.

---

## Shipping

### Manual shipment

`POST /api/admin/orders/{order}/shipping` with
`{ shipping_company, tracking_number, shipping_address }`.

Requirements:

- order status is `ready_for_shipping` (or already `shipped`)
- payment is `paid` when `REQUIRE_PAYMENT_BEFORE_SHIPPING=true`
- one shipment per order

### Shipping statuses

```text
pending → picked_up → in_transit → out_for_delivery → delivered
   │           │            │               │
   └───────────┴────────────┴───────────────┴──→ failed / returned
failed → pending (retry) or returned
returned → terminal
```

Updating a shipment synchronizes the order:

- `picked_up` / `in_transit` / `out_for_delivery` → order `shipped`
- `delivered` → order `delivered`
- `returned` → order `returned`

Returning a shipment whose payment is still `paid` is accepted at the shipment
level, but the order is left unchanged and a `shipping.returned.needs_refund`
audit entry is written. Refund the payment first (`PATCH
/api/admin/payments/{id}/status` with `refunded`), then the order can move to
`returned`.

### Shipping webhook

`POST /api/webhooks/shipping/{gateway}` (public, signature-protected)

Supported gateway: `manual`.

Headers: `X-Shipping-Signature: <hex hmac-sha256>` using
`SHIPPING_WEBHOOK_SECRET`.

```json
{
  "tracking_number": "TRK-001",
  "order_number": "ORD-2026-000001",
  "status": "in_transit"
}
```

At least one of `tracking_number` or `order_number` is required. The webhook
uses the same transition rules as the admin endpoint.

---

## Environment variables

See `.env.example` for the full list. Key variables:

| Variable | Default | Purpose |
| :--- | :--- | :--- |
| `APP_TIMEZONE` | `Africa/Cairo` | Reporting and date calculations |
| `APP_LOCALE` | `ar` | Default message language |
| `DB_*` | MySQL | Database connection |
| `DB_TIMEZONE` | — | Optional MySQL session timezone (e.g. `+00:00`) |
| `SANCTUM_STATEFUL_DOMAINS` | `localhost,127.0.0.1` | Sanctum stateful hosts |
| `SANCTUM_EXPIRATION` | `10080` | Bearer token lifetime in minutes (0 = no expiry) |
| `TRUSTED_PROXIES` | — | Comma-separated proxy IPs, or `*`; empty = no trusted proxy |
| `APP_DEBUG` | `true` (example) | Must be `false` in production |
| `FRONTEND_URL` | `http://localhost:3000` | Password reset links |
| `ADMIN_EMAIL`, `ADMIN_PASSWORD`, `ADMIN_NAME`, `ADMIN_PHONE` | — | Seeded Super Admin (required in production) |
| `PAYMENT_GATEWAY` | `manual` | Default payment gateway driver |
| `PAYMENT_METHODS` | `manual,easykash` | Accepted `payment_method` values |
| `DEFAULT_PAYMENT_METHOD` | `manual` | Fallback payment method |
| `EASYKASH_WEBHOOK_SECRET` | — | Payment webhook HMAC secret |
| `EASYKASH_SIGNATURE_HEADER` | `X-EasyKash-Signature` | Signature header name |
| `EASYKASH_INITIATE_SECRET` | falls back to webhook secret | Payment URL signing secret |
| `EASYKASH_WEBHOOK_TOLERANCE` | `0` | Replay window in seconds (0 = disabled) |
| `EASYKASH_MERCHANT_ID`, `EASYKASH_INITIATE_URL`, `EASYKASH_MODE` | — | Gateway credentials/mode |
| `SHIPPING_GATEWAY` | `manual` | Shipping gateway driver |
| `SHIPPING_WEBHOOK_SECRET` | — | Shipping webhook HMAC secret |
| `SHIPPING_SIGNATURE_HEADER` | `X-Shipping-Signature` | Shipping signature header name |
| `SHIPPING_FLAT_RATE` | `50` | Flat shipping cost |
| `SHIPPING_FREE_OVER` | — | Free shipping threshold |
| `INFLUENCER_DEFAULT_COMMISSION` | `10` | Default commission percentage |
| `INFLUENCER_COMMISSION_HOLD_DAYS` | `7` | Hold period before approval |
| `UNPAID_ORDER_TTL_HOURS` | `24` | Auto-cancel unpaid orders (0 disables) |
| `EXPIRE_UNPAID_PAYMENT_METHODS` | `easykash` | Payment methods eligible for auto-cancel (manual/COD excluded) |
| `WITHDRAWAL_FOUR_EYES` | `true` | Enforce approver ≠ payer |
| `REQUIRE_PAYMENT_BEFORE_SHIPPING` | `true` | Block shipping before payment |
| `LINK_CLICK_TTL_MINUTES` | `60` | Deduplicate clicks per visitor |
| `EMAIL_VERIFICATION_ENABLED` | `false` | Email verification skeleton |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_CLIENT_IDS`, `GOOGLE_REDIRECT_URI` | — | Google social login |
| `APPLE_CLIENT_ID`, `APPLE_CLIENT_SECRET`, `APPLE_REDIRECT_URI`, `APPLE_TEAM_ID`, `APPLE_KEY_ID`, `APPLE_PRIVATE_KEY` | — | Apple social login |

---

## Cron / scheduler

Run the scheduler every minute in production:

```cron
* * * * * cd /path/to/For_You && php artisan schedule:run >> /dev/null 2>&1
```

| Command | Schedule | Purpose |
| :--- | :--- | :--- |
| `commissions:release` | hourly | Release due `pending` commissions to wallets |
| `orders:expire-unpaid` | every 15 minutes | Cancel stale unpaid orders (only `EXPIRE_UNPAID_PAYMENT_METHODS`) and restore stock |

`orders:expire-unpaid` writes an `order.auto_cancelled` audit entry for every
order it cancels. Manual/COD orders are never auto-cancelled because collection
happens on delivery.

Both commands use `withoutOverlapping()`.

---

## Common errors

| Status | When |
| :--- | :--- |
| `401` | Missing/invalid token, invalid webhook signature |
| `403` | Wrong user type, missing permission, disabled account, four-eyes violation |
| `404` | Missing resource or unsupported webhook gateway |
| `409` | Duplicate influencer-product link, concurrent idempotent request, social account conflict |
| `422` | Validation failure, illegal status transition, insufficient balance, amount mismatch |
| `429` | Rate limit exceeded (login, register, password reset, social, API, webhooks) |

Validation errors use Laravel's standard shape:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email field is required."]
  }
}
```

Arabic messages are returned when `Accept-Language: ar` is sent.

### Rate limiters

| Limiter | Scope |
| :--- | :--- |
| `login` | Login attempts |
| `register` | Registration |
| `forgot-password`, `reset-password` | Password flows |
| `change-password` | Authenticated password change |
| `social` | Social login redirect/token |
| `api` | Authenticated API traffic |
| `track` | Public promotional-link click tracking |
| `webhook` | Payment/shipping webhooks |

---

## Endpoint index

### Public

| Method | Path |
| :--- | :--- |
| POST | `/api/register`, `/api/login` |
| POST | `/api/auth/forgot-password`, `/api/auth/reset-password` |
| GET/POST | `/api/auth/{provider}/redirect`, `/callback`, `/token` |
| GET | `/api/home` |
| GET | `/api/categories`, `/api/categories/{id}` |
| GET | `/api/products`, `/api/products/featured`, `/api/products/offers`, `/api/products/new`, `/api/products/{id}` |
| GET | `/api/influencers`, `/api/influencers/{id}/products` |
| GET | `/api/track/{code}` |
| POST | `/api/webhooks/payments/{gateway}`, `/api/webhooks/shipping/{gateway}` |

### Customer (auth)

Addresses `/api/addresses`, cart `/api/cart`, checkout `/api/checkout`,
orders `/api/orders`, notifications `/api/notifications`.

### Influencer (auth + `influencer`)

`/api/influencer/{dashboard,products,percentage,links,sales,wallet,wallet/withdraw,wallet/withdrawals}`.

### Admin / employee (auth + `admin` + permission)

`/api/admin/{dashboard,categories,products,orders,customers,payments,shipping,influencers,influencer-sales,links,wallets,wallet-transactions,withdrawals,banners,coupons,reports,roles,permissions,employees}`.

See the Postman collection for request bodies and saved examples.
