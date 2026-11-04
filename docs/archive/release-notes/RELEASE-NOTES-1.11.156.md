# HamiShop Elementor Widgets 1.11.156 - release notes

Release date: 2026-10-28

## Changes in 1.11.156

- Removed the **header-wide gradient shine** completely: the Elementor control, the root modifier class, the clipped shine layer in the store header, the CSS layer, and the keyframes are gone. The header itself no longer animates.
- Kept the **logo shine** as the only shine effect. It is still enabled by default and continues to use the logo colour/duration controls in the header Style tab.
- Image logos no longer use a fixed square box. They render with their own intrinsic aspect ratio, so wide, square, and tall logos keep their real proportions.
- Reinterpreted the existing logo size control as **maximum logo height** (`حداکثر ارتفاع لوگو`). The rendered width is the smaller of the source width, the height cap multiplied by the source aspect ratio, and a responsive `38vw` limit; a `min(6 × height, 38vw, 100%)` max-width guard remains in place.
- The attachment's aspect ratio is written to the logo wrapper, so the correct space is reserved before the image loads and the layout does not collapse to a zero-width logo.
- Excluded image logos from the square and 50%-pill radius rules. The bundled storefront icon fallback remains square.
- Updated the responsive image `sizes` declaration to match the new logo width cap.
- Preserved the 1.11.155 cart drawer behaviour: same inline-end side, 460 ms slide with opacity fade, 380 ms overlay fade, and the existing primary-blue accents.
- Updated the plugin version, readme stable tag/changelog, translation template, minified assets, and release archive metadata.

## Validation run for 1.11.156

- PHP lint passed for the modified PHP files.
- Source search confirms no remaining `header_shine`, `hsw-hd__shine`, `hsw-hd--header-shine`, or `hsw-hd-header-shine` references outside historical release notes. The minified header CSS was rebuilt and contains no header-shine rules.
- Live desktop header verified with the 159×40 shop logo at 1000px and 1400px viewports: the logo renders at 159×40 (intrinsic ratio 3.975), not as a 44×44 square, and no header-wide shine layer is emitted.
- Live mobile viewport verified at 390px: the logo scales to 104×26.2, the header has no horizontal overflow, and the logo shine remains active. The header also stays within its own width at 320px.
- The storefront icon fallback still renders square, and image logos are excluded from the 50%-pill radius.
- Cart drawer rechecked on desktop and mobile: side, timing, fade, and primary colours are unchanged.
- Browser console and WordPress debug log checked; no HSW errors.
