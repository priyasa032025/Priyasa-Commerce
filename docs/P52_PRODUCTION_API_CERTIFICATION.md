# P52 — Production API Certification & E2E Consolidation

P52 is a **certification and integration release**, not another commerce feature release.

## Certification layers

1. PHP syntax across the cumulative P0–P51 patch tree.
2. Migration timestamp uniqueness.
3. Canonical `/api/v1` route ownership.
4. Controller/action and route contract checks from earlier releases.
5. OpenAPI 3.0.3 presence and core-path coverage.
6. Laravel runtime route registration.
7. API contract response checks.
8. Disposable test-database migration + seed execution.
9. Laravel Feature/E2E tests.
10. Optional live HTTP smoke test against a running test deployment.

## Run static certification

From this patch tree:

```bash
php Tools/p52_api_certify.php
```

The result is written to:

```text
docs/p52-certification-report.json
```

## Run real Laravel E2E

Copy the cumulative P0–P51 source into the real Laravel application and install it normally. Then run from the Laravel application root:

```bash
APP_ENV=testing P52_ALLOW_DB_RESET=1 ./path/to/p52_run_e2e.sh
```

Use a disposable testing database. `migrate:fresh` is intentionally guarded and will not run unless both variables are explicitly set.

For an already-running test server:

```bash
APP_ENV=testing P52_ALLOW_DB_RESET=1 P52_BASE_URL=http://127.0.0.1:8000 ./path/to/p52_run_e2e.sh
```

## What this certifies

The runtime suite proves that the real Laravel application can register the API tree and that the core storefront contract endpoints return the expected API envelope.

The complete business transaction tests should be run with the project's real factories/test fixtures and sandbox credentials for:

- OTP/authentication
- cart and cart merge
- inventory reservation/commit/release
- coupon/promotion/loyalty
- checkout idempotency
- COD
- Razorpay sandbox payment + webhook replay
- warehouse allocation
- Shiprocket sandbox/test integration
- order lifecycle
- return/exchange/refund
- FCM/WhatsApp/email adapters
- admin RBAC and mutations

P52 does **not** fabricate external credentials or claim a third-party integration passed without executing it.

## Certification rule

Production release is `CERTIFIED` only when:

- static scanner passes,
- Laravel migrations pass on a clean disposable DB,
- Laravel feature/E2E tests pass,
- API route inventory is generated from the real route table,
- OpenAPI matches the deployed route inventory,
- payment/webhook and fulfillment integration tests pass with sandbox credentials.

A PHP lint pass alone is not considered production certification.
