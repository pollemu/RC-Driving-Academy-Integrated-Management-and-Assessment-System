## Objective
- Maintain a clean, Laravel-conventional, repo-ready RC Driving Academy IMAS (SQLite, migrations/seeders, frontend wired to API) meeting ISO/IEC 25010 criteria.
- Build a user-friendly "grocery cart" enrollment + **PayMongo-hosted payment** flow (student picks one TDC + any PDC, downpayment-gated scheduling, PDC locked until TDC assessment), and ensure a **rejected payment** cleanly reverts the student to a re-payable state (no "stuck enrolled / waiting for admin" state).

## Important Details
- Root: `C:\xampp\htdocs\RC Driving Academy IMAS` (Laravel 13.19, PHP 8.4.14, `pdo_sqlite`+`sqlite3`). **DB = SQLite** (`database/database.sqlite`).
- GitHub: `https://github.com/pollemu/RC-Driving-Academy-Integrated-Management-and-Assessment-System.git` (last pushed `ac88469`; working tree has UNCOMMITTED changes — not yet committed).
- Frontend at `public/frontend/`; dev server `php artisan serve --port=8124`.
- **Enrollment rules (confirmed):** TDC single-select (F2F ₱799 / Online ₱999, full to attend); PDC only after TDC `Assessment` exists; PDC 4W ₱4000 with ₱2000 downpayment gate; PDC 2W ₱1499 full. Admin verifies; `Enrollment` activates (`status='active'`) only if verified `amount >= course.downpayment`.
- **PayMongo decisions (user-chosen):** redirect to hosted checkout (GCash/Card/OTC); auto-verify via webhook (`payment.paid`/`checkout_session.paid`); local dev uses return-URL check (no ngrok tunnel). `.env` has `PAYMONGO_SECRET_KEY`/`PUBLIC_KEY`/`WEBHOOK_SECRET`/`API_URL`.
- Auth: Sanctum 8h tokens; admin `admin@rcdriving.ph`/`admin123`.

## Work State
### Completed (since last summary)
- **Pay modal + cart CSS:** `#payModal` in `student/dashboard.html`; `.modal-overlay`, `.enroll-group`, `.enroll-opt` (+`.is-selected`/`.is-done`/`.is-locked`), `.cart-line`, `.cart-total`, `.pay-methods`, `.notice`/`.notice-warn` in `student.css`.
- **Route-order bug fixed:** `/student/payments/cart` before `/payments/{enrollment}`.
- **cartStore bug fixes:** `method` nullable + `gcash` default; `reference_no` null-coalesced; duplicate pending-payment skip.
- **Logo responsiveness:** `signup.css` `.look`/`.look-logo`/`.look-fallback`/`.look-text` (56→46→40px) + `base.css` global `img{max-width:100%}`.
- **Enroll selection bug fixed:** selection tracked in `ENROLL_SELECTED = {tdc:null, pdc:{}}`; `submitEnroll()` builds cart from state.
- **PayMongo integration:** `PayMongoService` (`createCheckoutSession`/`getCheckoutSession`), `PaymentController@cartStore` (pending payments + combined checkout → `checkout_url`), `Payment@markVerified()`, `PaymentController@returnVerify`, `WebhookController@paymongo` (public, signature-verified), routes + frontend `processPay()` redirect / `handlePayMongoReturn()`. Tested: 502 graceful when PayMongo down; markVerified activates + idempotent; returnVerify handles missing/unknown.
- **Reject-state bug fixed (current session):**
  - `Admin/PaymentController@reject` now reverts the linked `Enrollment` from `active` → `pending` (so scheduling re-locks and the student can pay again); rejected payment stays in history.
  - `student.js` enroll tab no longer paints a `pending`/`unpaid` enrollment as green `is-done`; instead shows a selected option + hint "Enrolled — complete payment to activate scheduling" and only `is-done` when status is `active`/`completed`.
  - Payments tab wording changed from "Awaiting payment" → "Needs payment"; added a `.notice-warn` banner when any payment is `rejected` ("A payment was rejected. Please pay again below to activate your enrollment."). `.notice`/`.notice-warn` styles added to `student.css`.
  - PHP `php -l` clean on the controller.

### Active
- (none — reject-state bug resolved in code; pending user re-test)

### Blocked
- (none)

## Next Move
1. Re-test end-to-end: student pays → admin `reject` → student dashboard should show enrollment NOT as green "done", a rejected-payment warning banner, and a working "Pay Now" that re-bills via PayMongo → on success enrollment activates.
2. `git add -A` + commit + push all uncommitted work to GitHub.

## Relevant Files
- `app/Http/Controllers/Api/Admin/PaymentController.php` — `reject()` reverts enrollment `active`→`pending`.
- `app/Http/Controllers/Api/Student/PaymentController.php` — `cartStore` (PayMongo), `returnVerify`.
- `app/Models/Payment.php` — `markVerified()`.
- `app/Services/PayMongoService.php` — `createCheckoutSession()`, `getCheckoutSession()`.
- `app/Http/Controllers/Api/WebhookController.php` — public PayMongo webhook.
- `routes/api.php` — `/api/webhooks/paymongo`, `/api/student/payments/return`, `/api/student/payments/cart` order.
- `public/frontend/js/student.js` — enroll cart, `processPay()` redirect, `handlePayMongoReturn()`, `statusBadge()`, dues "Pay Now", reject warning banner, enroll `is-done` logic.
- `public/frontend/student/dashboard.html` — `#payModal`.
- `public/frontend/css/student.css` — cart/modal/enroll styles, `.is-selected`, `.notice`/`.notice-warn`.
- `public/frontend/css/signup.css` — `.look-logo` responsive.
- `public/frontend/css/base.css` — global `img{max-width:100%}`.
- `https://github.com/pollemu/RC-Driving-Academy-Integrated-Management-and-Assessment-System.git` — remote (uncommitted changes pending push).
