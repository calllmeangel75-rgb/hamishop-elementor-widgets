// Header pin + smart-hide regression probe.
// Verifies on both desktop and mobile viewports:
//   * the header is actually pinned (position:fixed, top:0 while scrolled)
//   * the placeholder holds its height (no layout jump)
//   * scrolling down hides it, the first scroll up brings it back
//   * no horizontal overflow is introduced by pinning
const { chromium } = require('playwright');

const BASE = 'http://localhost/sks';
const PAGE = process.argv[2] || '/shop/';

const VIEWPORTS = [
  { name: 'desktop', width: 1440, height: 800 },
  { name: 'mobile', width: 390, height: 844 },
];

let failures = 0;

function check(label, ok, detail) {
  console.log(`   ${ok ? 'PASS' : 'FAIL'}  ${label}${detail ? '  — ' + detail : ''}`);
  if (!ok) failures++;
}

async function scroll(page, y) {
  await page.evaluate((v) => window.scrollTo({ top: v, behavior: 'instant' }), y);
  await page.waitForTimeout(700);
}

(async () => {
  const browser = await chromium.launch();

  for (const vp of VIEWPORTS) {
    const ctx = await browser.newContext({ viewport: { width: vp.width, height: vp.height } });
    const page = await ctx.newPage();
    await page.goto(BASE + PAGE, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1200);

    console.log(`\n=== ${vp.name} ${vp.width}x${vp.height} ${PAGE} ===`);

    const base = await page.evaluate(() => {
      const el = document.querySelector('[data-hsw-header]');
      if (!el) return { error: 'no header' };
      const ph = el.previousElementSibling;
      const cs = getComputedStyle(el);
      return {
        cls: el.className,
        position: cs.position,
        zIndex: cs.zIndex,
        headerH: Math.round(el.getBoundingClientRect().height),
        phH: ph && ph.classList.contains('hsw-hd__ph') ? Math.round(ph.getBoundingClientRect().height) : null,
        docH: document.documentElement.scrollHeight,
        winH: innerHeight,
        overflowX: document.documentElement.scrollWidth - document.documentElement.clientWidth,
      };
    });

    if (base.error) {
      check('header found', false, base.error);
      await ctx.close();
      continue;
    }

    check('pinned (position:fixed)', base.position === 'fixed', base.position);
    check('placeholder mirrors height', base.phH === base.headerH, `header ${base.headerH}px / ph ${base.phH}px`);
    check('no horizontal overflow', base.overflowX <= 0, `${base.overflowX}px`);

    const atTop = await page.evaluate(() => {
      const r = document.querySelector('[data-hsw-header]').getBoundingClientRect();
      return { top: Math.round(r.top), bottom: Math.round(r.bottom), hidden: document.querySelector('[data-hsw-header]').classList.contains('is-hidden') };
    });
    check('visible at page top', atTop.top === 0 && !atTop.hidden, `top ${atTop.top}`);

    const maxScroll = Math.max(0, base.docH - base.winH);
    const downTo = Math.min(maxScroll, 420);

    await scroll(page, downTo);
    const down = await page.evaluate(() => {
      const el = document.querySelector('[data-hsw-header]');
      const r = el.getBoundingClientRect();
      return { hidden: el.classList.contains('is-hidden'), top: Math.round(r.top), bottom: Math.round(r.bottom), y: Math.round(scrollY) };
    });
    check('hidden after scrolling down', down.hidden === true, `scrollY ${down.y}`);
    check('fully off-screen while hidden', down.bottom <= 0, `bottom ${down.bottom}`);

    await scroll(page, downTo - 140);
    const up = await page.evaluate(() => {
      const el = document.querySelector('[data-hsw-header]');
      const r = el.getBoundingClientRect();
      return { hidden: el.classList.contains('is-hidden'), top: Math.round(r.top), y: Math.round(scrollY) };
    });
    check('back on first scroll up', up.hidden === false, `scrollY ${up.y}`);
    check('pinned at top after reveal', Math.abs(up.top) <= 2, `top ${up.top}`);

    const noJump = await page.evaluate(() => {
      const ph = document.querySelector('.hsw-hd__ph');
      const el = document.querySelector('[data-hsw-header]');
      return { ph: Math.round(ph.getBoundingClientRect().height), h: Math.round(el.getBoundingClientRect().height) };
    });
    check('placeholder still matches after scroll', noJump.ph === noJump.h, `${noJump.ph} / ${noJump.h}`);

    await ctx.close();
  }

  await browser.close();
  console.log(`\n${failures === 0 ? 'ALL CHECKS PASSED' : failures + ' CHECK(S) FAILED'}`);
  process.exit(failures ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(2); });
