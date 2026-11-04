# Clothing API

A Laravel JSON API for the clothing shop. The API provides the catalog and inventory used by the Flutter app, customer accounts and addresses, checkout and orders, payment processing, receipts, and admin operations.

The Flutter client is in the sibling `clothing_app/` directory. This README describes the backend in `clothing-api/`.

## 1. Technology and requirements

- PHP 8.3 (Composer constraint: `^8.3`)
- Composer
- SQLite for the default local setup, or another Laravel-supported database
- Node.js and npm only if you need to build the included Vite assets
- A public HTTPS callback URL and AnajakPay credentials to test real payments

The project uses Laravel 13, Laravel Sanctum for API tokens, Eloquent models and migrations, Laravel queues for background jobs, and Pest/PHPUnit for tests.

## 2. Install and run locally

From the repository root, enter the API project:

```powershell
cd clothing-api
```

Install PHP dependencies and create the local environment file if it does not exist:

```powershell
composer install
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
php artisan key:generate
```

The supplied environment uses SQLite. The repository includes `database/database.sqlite`; if you configure a different database, update the corresponding `DB_*` values in `.env` first.

Run migrations and load sample data:

```powershell
php artisan migrate --seed
```

Start the API:

```powershell
php artisan serve
```

By default, the server is available at `http://127.0.0.1:8000`. To make it reachable from a physical phone or another device on your network, bind to all interfaces:

```powershell
php artisan serve --host=0.0.0.0
```

Use the computer's LAN IP address in the Flutter development configuration; `localhost` on a phone or emulator usually refers to that device, not your development computer.

For local development with the queue worker, application logs, and Vite process together, Composer also defines:

```powershell
composer run dev
```

If you are not using the combined command, run `php artisan queue:work` in a separate terminal to process queued notifications.

## 3. Seed data and administrator account

`php artisan db:seed` runs `DatabaseSeeder`, which invokes the admin, customer, category, product, and demo-order seeders. The sample catalog contains categories and products with size/color variants, and the demo seeder adds example orders, payments, receipts, and notification records.

For a local-only seeded admin, the seeder falls back to `admin@example.com` / `password`. Change these immediately if you use the account. Before production seeding, set explicit `ADMIN_EMAIL` and `ADMIN_PASSWORD` values in the server environment; production seeding stops if they are missing. Never rely on demo credentials in a deployed environment.

## 4. How the code is organized

The API is versioned under `/api/v1`. A typical request follows this path:

1. A route in `routes/api.php` selects a controller.
2. A Form Request validates user input.
3. The controller authorizes the operation and coordinates the request.
4. Services implement business rules such as pricing, stock reservation, and payment verification.
5. Eloquent models read or update the database.
6. API Resources format the JSON response.

Important directories:

| Path | Purpose |
|---|---|
| `routes/api.php` | Public, authenticated, and administrator API routes |
| `app/Http/Controllers/Api/V1/` | API endpoint handlers |
| `app/Http/Requests/` | Request validation |
| `app/Http/Resources/` | Consistent JSON representations |
| `app/Models/` | Eloquent models and relationships |
| `app/Services/` | Checkout, inventory, payment, receipt, and notification logic |
| `app/Services/Payments/` | Payment-provider interface and AnajakPay/KHQR integration |
| `app/Enums/` | User, payment, notification, and fulfillment states |
| `app/Policies/` | Ownership and access policies |
| `app/Http/Middleware/` | Admin authorization and JSON response handling |
| `app/Jobs/` | Background push and Telegram notification jobs |
| `database/migrations/` | Database schema history |
| `database/seeders/` | Local/demo data and initial administrator |
| `tests/Feature/` | API, authorization, checkout, database, and payment behavior tests |
| `tests/Unit/` | Focused service tests |

`bootstrap/app.php` registers the API routes, the `admin` middleware alias, and middleware that makes API responses JSON.

## 5. Main data model

- **User** has a role (`customer` or `admin`), addresses, orders, device tokens, and notifications.
- **Category** groups products.
- **Product** belongs to a category and has a catalog price, active state, `asset_key`, images, and variants. `asset_key` is a Flutter asset identifier, not an image URL.
- **ProductVariant** represents a product's size/color combination, SKU, and available stock.
- **Address** stores a customer's saved delivery information.
- **Order** belongs to a customer and contains a shipping-address snapshot, totals, payment/fulfillment states, and order items.
- **OrderItem** preserves product name, asset key, size, color, quantity, and price snapshots so older orders remain meaningful if catalog data changes.
- **Payment** records provider, unique payment reference, amount, status, verification timestamp, and safe provider metadata.
- **TransactionBill** stores the receipt associated with a payment.
- **OrderActivity** records important order and payment state changes.
- **DeviceToken** and **Notification** support device notifications and their audit records.

Money is stored in integer minor units (for example, cents) in the database. Provider requests and client-facing price fields format USD amounts as decimal currency values.

## 6. API conventions and authentication

All API routes start with `/api/v1`. Send JSON requests with:

```http
Accept: application/json
Content-Type: application/json
```

Register or log in to receive a Sanctum personal access token. Send it on protected routes as:

```http
Authorization: Bearer YOUR_TOKEN
```

`POST /api/v1/auth/logout` revokes the current token. Routes under `/api/v1/admin` require an authenticated user with the admin role. Customer order, address, and receipt access is restricted by ownership checks and policies.

Validation errors are returned as JSON (normally HTTP 422). Unauthenticated requests receive HTTP 401 and forbidden operations receive HTTP 403.

## 7. Endpoint guide

### Public catalog and authentication

| Method | Endpoint | Purpose |
|---|---|---|
| `POST` | `/api/v1/auth/register` | Create a customer and return a token |
| `POST` | `/api/v1/auth/login` | Authenticate and return a token |
| `GET` | `/api/v1/categories` | List active categories |
| `GET` | `/api/v1/products` | List active products |
| `GET` | `/api/v1/products/{product}` | Get an active product and its variants/images |
| `POST` | `/api/v1/payments/callback` | Receive a signed payment-provider callback |
| `GET`, `POST` | `/api/v1/payments/khqr/success` | Handle hosted-checkout browser return or provider POST |

The product list accepts `category_id`, `q`, `min_price`, `max_price`, and `per_page` query parameters.

### Signed-in customer

| Method | Endpoint | Purpose |
|---|---|---|
| `POST` | `/api/v1/auth/logout` | Revoke the current token |
| `GET`, `PATCH` | `/api/v1/me` | Read or update the profile |
| `PATCH` | `/api/v1/me/password` | Change the password |
| `POST`, `DELETE` | `/api/v1/me/avatar` | Upload or remove the avatar |
| `GET`, `POST` | `/api/v1/addresses` | List or create addresses |
| `GET`, `PATCH`, `DELETE` | `/api/v1/addresses/{address}` | Read, update, or delete an address |
| `POST` | `/api/v1/addresses/{address}/set-default` | Set the default address |
| `POST` | `/api/v1/checkout/preview` | Preview checkout prices without creating an order |
| `POST` | `/api/v1/orders` | Place an order |
| `GET` | `/api/v1/orders` | List the signed-in customer's orders |
| `GET` | `/api/v1/orders/{order}` | Read an order |
| `GET` | `/api/v1/orders/{order}/payment-status` | Check payment status |
| `POST` | `/api/v1/orders/{order}/cancel` | Request order cancellation |
| `POST` | `/api/v1/orders/{order}/cod-details` | Resubmit COD details after correction is requested |
| `POST` | `/api/v1/orders/{order}/switch-to-aba` | Change an eligible rejected COD order to ABA Pay |
| `GET` | `/api/v1/orders/{order}/receipt` | Read the authorized order receipt |
| `POST` | `/api/v1/payments/{payment}/initiate` | Start payment for an order |
| `POST`, `DELETE` | `/api/v1/device-tokens` | Register or remove a push-notification token |

### Administrator

All endpoints below are under `/api/v1/admin` and require the admin role.

| Method | Endpoint | Purpose |
|---|---|---|
| `GET` | `/dashboard` | Summary metrics and recent orders |
| `GET` | `/orders` | List orders |
| `PATCH` | `/orders/{order}/fulfillment` | Update fulfillment status |
| `PATCH` | `/orders/{order}/cod-review` | Approve, reject, or request corrections for COD |
| `PATCH` | `/orders/{order}/cancellation-review` | Approve or reject a cancellation request |
| `PATCH` | `/orders/{order}/cod-approval` | Review COD order approval |
| `POST` | `/orders/{order}/cod-balance` | Record remaining cash-on-delivery balance |
| `POST` | `/orders/{order}/refund` | Record a refund |
| `GET` | `/orders/{order}/cod-document` | View the protected COD document |
| `GET` | `/payments` | List payments |
| `GET`, `POST` | `/categories` | List or create categories |
| `PUT`, `PATCH`, `DELETE` | `/categories/{category}` | Update or delete a category |
| `GET`, `POST` | `/products` | List or create products |
| `PUT`, `PATCH`, `DELETE` | `/products/{product}` | Update or delete a product |
| `POST` | `/products/{product}/images` | Update product images |
| `GET`, `POST` | `/products/{product}/variants` | List or create a product variant |
| `PUT`, `PATCH`, `DELETE` | `/products/{product}/variants/{variant}` | Update or delete a product variant |

## 8. Typical customer workflow

1. Register or log in and retain the returned token securely in the client.
2. Load categories and products. Select an available product variant.
3. Create or select a delivery address.
4. Call `POST /api/v1/checkout/preview` to show the server-calculated total.
5. Submit `POST /api/v1/orders` with an address, item variant IDs and quantities, and the required `idempotency_key`. The server validates ownership, recalculates prices, checks stock, reserves inventory, and writes the order and item snapshots.
6. For a QR/hosted payment, call `POST /api/v1/payments/{paymentId}/initiate` with a supported provider. Render the returned QR data or open the returned hosted-checkout URL.
7. Check `GET /api/v1/orders/{order}/payment-status` for the latest verified state, then retrieve the receipt after payment is confirmed.

Example checkout preview:

```json
{
  "address_id": 1,
  "items": [
    { "product_variant_id": 12, "quantity": 2 }
  ]
}
```

Example order body for a non-COD order:

```json
{
  "address_id": 1,
  "idempotency_key": "client-generated-request-key",
  "payment_method": "khqr",
  "items": [
    { "product_variant_id": 12, "quantity": 2 }
  ]
}
```

For cash-on-delivery (`payment_method: "cash"`), send a multipart form request with the required `cod.name`, `cod.phone`, `cod.email`, `cod.telegram`, `cod.address`, `cod.home_photo`, and accepted `cod.terms_accepted` fields. COD orders require admin verification and a 50% ABA Pay deposit before fulfillment proceeds.

## 9. Checkout, inventory, and order states

`CheckoutService` is the source of truth for price calculation. It reads product-variant prices from the database, and the client cannot set authoritative totals. Prices are stored as minor units. Order creation runs in a database transaction and creates a single payment record and immutable order-item snapshots.

`InventoryService` locks variants while changing stock. It atomically reserves stock when an order is placed and releases it for eligible cancellation/rejection flows. Insufficient stock returns a validation-style error instead of allowing an oversell.

The order tracks two related state machines:

- **Payment status:** `unpaid`, `pending`, `partially_paid`, `paid`, `failed`, `expired`, `refunded`, `refund_pending`, `partially_refunded`, or `cancelled`.
- **Fulfillment status:** `pending_payment`, `pending_verification`, `awaiting_deposit`, `pending_approval`, `processing`, `shipped`, `delivered`, `cancellation_requested`, `cancelled`, or `rejected`.

COD first enters document verification. If approved, the customer pays the required deposit; the remaining balance is recorded by an administrator. The admin endpoints enforce valid transitions, payment requirements, and inventory/document cleanup.

## 10. Payment provider setup

The payment integration is server-side. Do not put provider secrets, profile keys, or signing logic in Flutter, and do not commit real credentials.

Add the applicable settings to `clothing-api/.env`:

```dotenv
PAYMENT_DRIVER=khqr
KHQR_BASE_URL=https://anajakpay.com/api
KHQR_PROFILE_ID=your-provider-profile-id
KHQR_SECRET_KEY=your-provider-secret
KHQR_PROFILE_KEY=your-hosted-checkout-profile-key
KHQR_WEBHOOK_URL=https://your-public-host/api/v1/payments/callback
KHQR_CHECKOUT_RETURN_URL=https://your-public-host/api/v1/payments/khqr/success
FRONTEND_PAYMENT_RETURN_URL=
KHQR_TIMEOUT=15
QUEUE_CONNECTION=database
```

- **Direct KHQR (`khqr`)** creates a QR and uses the configured webhook URL for provider notifications.
- **Hosted checkout (`khqr_checkout`)** returns a `checkout_url`. Its `KHQR_CHECKOUT_RETURN_URL` must be a publicly reachable API return endpoint; `FRONTEND_PAYMENT_RETURN_URL` is an optional client redirect after the API verifies the provider return.
- **ABA Pay (`aba_pay`)** uses the KHQR-compatible provider integration. Set `ANJAKPAY_PROFILE_ID` and `ANJAKPAY_SECRET` when those credentials differ from the KHQR values.
- `KHQR_PROFILE_KEY` is used to validate signed hosted-checkout browser returns. Keep it server-side.
- `KHQR_SECRET_KEY` may also be supplied as `KHQR_SECRET`; the webhook URL has a legacy fallback to `KHQR_SUCCESS_URL`.

For a local test, expose the API through a trusted HTTPS tunnel and configure the provider callback with that public URL. Use `php artisan config:clear` after changing configuration. Run a queue worker to process background notifications:

```powershell
php artisan queue:work
```

An order payment reference is sent as the provider transaction ID. Amounts are sent as two-decimal USD strings. A callback is not treated as proof of payment by itself: Laravel validates the callback signature and amount, asks the provider to verify the transaction, then updates payment/order state and issues a receipt inside a database transaction. Duplicate confirmations do not issue duplicate receipts. Hosted-checkout browser returns are verified and checked with the provider before local payment state is changed.

## 11. Optional integrations

- **Firebase Cloud Messaging:** configure `FIREBASE_CREDENTIALS` with a server-side service-account JSON file path and `FIREBASE_PROJECT_ID`. Never commit the service-account file. Push notifications are queued.
- **Telegram:** configure `TELEGRAM_BOT_TOKEN` and `TELEGRAM_ADMIN_CHAT_IDS` for Telegram notifications.
- **Vite assets:** use `npm install`, `npm run dev`, or `npm run build` only if you need the included frontend/build assets. The main shop client is the separate Flutter application.

## 12. Tests and troubleshooting

Run the test suite from `clothing-api/`:

```powershell
php artisan test
```

Or use the Composer script:

```powershell
composer test
```

The suite includes feature coverage for routes, auth, catalog, addresses, checkout, stock, orders, receipts, admin flows, and payments, plus unit tests for checkout, inventory, and receipt services. Tests use an in-memory SQLite database and a synchronous queue, as configured in `phpunit.xml`.

Common checks:

- **Database connection errors:** confirm `DB_CONNECTION` and related `.env` settings, then run `php artisan migrate`.
- **Changes to `.env` seem ignored:** run `php artisan config:clear`.
- **Queued notifications do not run:** start `php artisan queue:work` and confirm the configured queue backend is available.
- **Provider callback fails locally:** callback URLs must be reachable over HTTPS from the provider. A device's `localhost` URL is not the same as the development computer's host.
- **A payment remains pending:** check server logs and provider status, then verify the transaction reference, currency, amount, and callback URL. A client redirect alone is not confirmation.
- **A request returns 401 or 403:** verify the bearer token, token validity, resource ownership, and admin role.

## 13. Security notes

- Keep `.env`, provider credentials, Firebase credentials, and uploaded COD documents private.
- Use HTTPS for deployed API traffic and provider callbacks.
- Use unique production administrator credentials and disable `APP_DEBUG`.
- Do not trust client-supplied prices, payment statuses, or redirect parameters.
- Protect and rotate application logs; payment references, statuses, and operational metadata may be logged for troubleshooting.
- Review authorization and state-transition rules before exposing new routes.
