# HamiShop Elementor Widgets — Release 1.11.277

**Security & Privacy Fix** — Order access authorization & billing email verification on completion views.

## Fixed

- **Order access control on `/complete/` and order-received endpoints (`HSW_Order_Auth`):**
  - Resolves privacy leak where anyone possessing an order key could view sensitive order details of a registered customer (`customer_id > 0`).
  - Implements WooCommerce core's verification standard:
    - **Owner logged in:** Access granted immediately.
    - **Guest order with valid key (`customer_id = 0`):** Access granted immediately.
    - **Other logged-in customer:** Strictly forbidden (access denied, no details leaked, no thankyou hook).
    - **Emailed link opened after logout (unauthenticated visitor):** Requires authentication or billing email verification. When the customer submits the matching billing email address, ownership is verified (stored in session and secure cookie) and order details are revealed.
- **Thank-you hooks gated behind authorization:**
  - `woocommerce_thankyou`, `woocommerce_before_thankyou`, and gateway thank-you hooks strictly fire only after order access verification is successfully passed.
  - No hooks fire during email verification prompt or for unauthorized viewers.

## Added

- `includes/class-hsw-order-auth.php` — Centralized order authorization and verification helper.
- `tests/qa/order-auth-wp.php` — Comprehensive regression test suite covering owner, guest order with key, other customer, and email link verification after logout.
