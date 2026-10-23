// Page-cache safety for the header cart badge.
//
// A cached page replays the badge of whoever warmed the cache. The header JS
// decides from `woocommerce_items_in_cart` whether to re-sync from WooCommerce
// or reset to empty — and it can only do that if the server prints `data-count`.
//
// Case A: no cart cookie + stale badge "۳"  -> must end up "۰"
// Case B: real cart cookie + stale badge "۳" -> must end up with the real count
const { chromium } = require('playwright');

const BASE = 'http://localhost/sks';
const PRODUCT = process.argv[2] || '13192';

let failures = 0;
function check(label, ok, detail) {
  console.log(`   ${ok ? 'PASS' : 'FAIL'}  ${label}${detail ? '  — ' + detail : ''}`);
  if (!ok) failures++;
}

const STALE = /(<span class="hsw-hd__badge" data-hsw-cart-count data-count=")\d+("[^>]*>)[^<]*(<\/span>)/;

async function replayStaleBadge(page, pattern) {
  await page.route(pattern, async (route) => {
    const res = await route.fetch();
    const body = (await res.text()).replace(STALE, '$13$2۳$3');
    await route.fulfill({ response: res, body });
  });
}

(async () => {
  const browser = await chromium.launch();

  // --- Case A: visitor without a cart -------------------------------------
  {
    const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await ctx.newPage();
    await replayStaleBadge(page, '**/');
    await page.goto(`${BASE}/`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(2200);

    const state = await page.evaluate(() => ({
      text: [...document.querySelectorAll('[data-hsw-cart-count]')].map((e) => e.textContent.trim()),
      hasCookie: /(?:^|;\s*)woocommerce_items_in_cart=/.test(document.cookie),
      hasAttr: [...document.querySelectorAll('[data-hsw-cart-count]')].every((e) => e.getAttribute('data-count') !== null),
    }));
    check('server prints data-count', state.hasAttr);
    check('no cart cookie on a fresh visit', state.hasCookie === false);
    check('stale badge cleared to zero', state.text.every((t) => t === '۰'), state.text.join('|'));
    await ctx.close();
  }

  // --- Case B: visitor with a real cart -----------------------------------
  {
    const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await ctx.newPage();
    await page.goto(`${BASE}/?add-to-cart=${PRODUCT}`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1500);
    await replayStaleBadge(page, '**/shop/**');
    await page.goto(`${BASE}/shop/`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(2500);

    const state = await page.evaluate(() => ({
      text: [...document.querySelectorAll('[data-hsw-cart-count]')].map((e) => e.textContent.trim()),
      hasCookie: /(?:^|;\s*)woocommerce_items_in_cart=/.test(document.cookie),
      dataCount: [...document.querySelectorAll('[data-hsw-cart-count]')].map((e) => e.getAttribute('data-count')),
    }));
    check('cart cookie present', state.hasCookie === true);
    check('badge re-synced from WooCommerce', state.text.every((t) => t !== '۳' && t !== '۰'), state.text.join('|'));
    check('data-count follows the real count', state.dataCount.every((d) => Number(d) > 0), state.dataCount.join('|'));
    await ctx.close();
  }

  await browser.close();
  console.log(`\n${failures === 0 ? 'ALL CHECKS PASSED' : failures + ' CHECK(S) FAILED'}`);
  process.exit(failures ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(2); });
