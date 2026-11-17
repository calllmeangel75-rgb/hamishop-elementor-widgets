# HamiShop Elementor Widgets — Release 1.11.304

Two reports from the shop owner: a bought product stayed in the cart, and a customer could print the
invoice.

## 1) The cart kept the order it had just paid

`HSW_Checkout_Complete::pay_awaiting_order()` sends an **existing** pending order back to the
gateway (the retried-payment path: the first attempt failed, the cart was kept, the customer pays from
the pay step). It clears `order_awaiting_payment` and returns success — but it never emptied the cart,
while the normal `complete()` path always does.

That branch is only reachable when `$order->has_cart_hash( WC()->cart->get_cart_hash() )` matches,
i.e. the order **is** the cart on screen, so the fix is unambiguous: empty the cart once the gateway
accepted the payment.

A second, narrower net was added on the completion page: when a visitor who may not fulfil orders
opens `/complete/` for an order, the cart is emptied **only if every cart line belongs to that order**
and no line exceeds the ordered quantity. A customer who bought something and then added a different
product keeps their new cart, and staff opening a customer's order never touch their own cart.

## 2) Invoice and postal label are staff documents

`HSW_Invoice::authorize()` used to accept the order owner with a valid key for `part=invoice`
(`part=postal` was already staff-only). Both sheets carry the letterhead, the stamp and the shipment
chart, and the owner asked for them to be produced only by whoever fulfils orders — administrator,
shop manager, any role WooCommerce grants `edit_shop_orders`.

- `authorize()` now requires `edit_shop_orders` **or** `manage_woocommerce` for every part; a customer
  URL answers 403 «برای مشاهدهٔ این فاکتور دسترسی ندارید».
- The «چاپ فاکتور» button is not rendered in the customer's order view any more; staff still see it.

Customers keep their order page (items, totals, address, status); they no longer get the seller's
paperwork. If a shop wants a customer-facing receipt later, that is a separate, deliberately lighter
document.

## Checks

`php tools/ci-checks.php` — **29 checks**, including two new ones:
- 6h: `authorize()` refuses non-staff and the account button is behind `edit_shop_orders`.
- 6i: `pay_awaiting_order()` empties the cart.

`php tests/harness.php` (118 classes) and `php tests/unit/run.php` (56 assertions): clean.
