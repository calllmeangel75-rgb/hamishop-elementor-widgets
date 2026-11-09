# 1.11.259 — staging pre-release

- Load the WooCommerce cart from the session before checking or changing checkout coupons. A disposable WP/WC regression models a cart created after `wp_loaded` and checks coupon removal and totals.
- This is not a production release. Actual browser and HTTP session behavior must still be verified on staging. Other coupon UI and financial readiness issues remain open.
- Includes the existing cash-on-delivery loyalty confirmation prototype; its remaining accounting and concurrency risks are not resolved by this release.
