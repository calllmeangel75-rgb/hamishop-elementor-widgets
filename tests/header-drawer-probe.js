// The header is pinned (position:fixed) and slides with a transform. A
// transform turns it into the containing block for the `position:fixed` mobile
// drawer nested inside, so this probe makes sure the drawer still covers the
// viewport — including right after a hide/reveal cycle.
const { chromium } = require('playwright');

const BASE = 'http://localhost/sks';
const PAGE = '/shop/';

let failures = 0;
function check(label, ok, detail) {
  console.log(`   ${ok ? 'PASS' : 'FAIL'}  ${label}${detail ? '  — ' + detail : ''}`);
  if (!ok) failures++;
}

(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
  const page = await ctx.newPage();
  await page.goto(BASE + PAGE, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(1200);

  // Hide then reveal, so the drawer opens right after a transform cycle.
  await page.evaluate(() => window.scrollTo({ top: 400, behavior: 'instant' }));
  await page.waitForTimeout(450);
  await page.evaluate(() => window.scrollTo({ top: 260, behavior: 'instant' }));
  await page.waitForTimeout(450);

  // A site can ship two headers (store for desktop, minimal pill for mobile).
  // Only the rendered one is interactable, so pick the widest.
  const target = await page.evaluate(() => {
    const list = [...document.querySelectorAll('[data-hsw-header]')]
      .map((el, i) => ({ i, w: el.getBoundingClientRect().width }))
      .sort((a, b) => b.w - a.w);
    return list[0] || null;
  });
  check('a rendered header exists', !!target && target.w > 0, target ? `${Math.round(target.w)}px` : 'none');
  if (!target || target.w === 0) { await browser.close(); process.exit(1); }

  const opener = page.locator('[data-hsw-hd-drawer-open]').nth(0);
  const count = await opener.count();
  check('mobile drawer opener exists', count > 0, `${count} found`);
  if (!count) { await browser.close(); process.exit(1); }

  await page.evaluate(() => {
    const btn = document.querySelector('[data-hsw-hd-drawer-open]');
    btn.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
  });
  await page.waitForTimeout(600);

  const drawer = await page.evaluate(() => {
    const d = document.querySelector('.hsw-hd__drawer');
    if (!d) return { error: 'no drawer' };
    const r = d.getBoundingClientRect();
    const cs = getComputedStyle(d);
    const header = document.querySelector('[data-hsw-header]');
    return {
      open: d.classList.contains('is-open'),
      visible: cs.visibility,
      w: Math.round(r.width),
      h: Math.round(r.height),
      top: Math.round(r.top),
      left: Math.round(r.left),
      vw: document.documentElement.clientWidth,
      vh: innerHeight,
      headerTransform: getComputedStyle(header).transform,
    };
  });

  check('drawer opened', drawer.open === true, drawer.visible);
  check('drawer spans the viewport width', Math.abs(drawer.w - drawer.vw) <= 1, `${drawer.w} / ${drawer.vw}`);
  check('drawer spans the viewport height', Math.abs(drawer.h - drawer.vh) <= 1, `${drawer.h} / ${drawer.vh}`);
  check('drawer starts at the top', Math.abs(drawer.top) <= 1, String(drawer.top));
  check('header carries no transform while open', drawer.headerTransform === 'none', drawer.headerTransform);

  // And while the drawer is open, scrolling must not hide the header.
  await page.evaluate(() => window.scrollBy(0, 300));
  await page.waitForTimeout(400);
  const stillShown = await page.evaluate(() => !document.querySelector('[data-hsw-header]').classList.contains('is-hidden'));
  check('header stays visible while drawer is open', stillShown === true);

  await browser.close();
  console.log(`\n${failures === 0 ? 'ALL CHECKS PASSED' : failures + ' CHECK(S) FAILED'}`);
  process.exit(failures ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(2); });
