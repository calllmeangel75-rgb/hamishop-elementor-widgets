# HamiShop Elementor Widgets 1.11.155 - release notes

Release date: 2026-10-28

## Changes in 1.11.155

- Added a visible **header gradient shine** to the store header's main row. The sweep is rendered in a clipped layer so search suggestions and dropdown panels are not clipped.
- The existing logo shine is now enabled by default, so headers saved before the 1.11.153 control was added still show the effect. Both the header and logo shine have adjustable colour and duration in the header Style tab.
- Updated the cart drawer to enter and leave from the same inline-end edge, with a medium-speed (460 ms) slide plus opacity fade. The overlay keeps its 380 ms fade.
- Preserved the cart's primary palette accents: the title icon, count badge, quantity controls, total and checkout button still use the configured HamiShop primary colour.
- Respects `prefers-reduced-motion`: the header shine is hidden and the cart transitions are disabled when reduced motion is requested.
- Updated the plugin version, readme stable tag/changelog, translation template and release archive metadata.

## Validation run for 1.11.155

- PHP lint passed for the modified PHP files.
- Header render verified: `hsw-hd--header-shine` and the clipped shine layer are present; the logo mark receives `hsw-hd__logomark--shine` from the new default.
- Cart drawer verified on the live RTL shop: open and close both use the inline-end side; computed opacity fades from 0 to 1 and back; the panel uses a 460 ms transform transition and the overlay uses a 380 ms fade.
- Primary cart colours rechecked: primary `#1d4ed8` remains on the title icon, badge, quantity controls, total and checkout button.
- Reduced-motion and keyboard/overlay/Escape close paths rechecked.
- Browser console and WordPress debug log checked; no HSW errors.
