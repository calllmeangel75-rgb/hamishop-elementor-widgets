// Minimal glass header: tagline, icon pickers, pill/action style controls.
// The page is created by tests/fixture-minimal-page.php (slug /qa-header-minimal/).
const { chromium } = require('playwright');

const BASE = process.argv[2] || 'http://localhost/sks';
const PAGE = BASE + '/qa-header-minimal/';
let failures = 0;

const check = (label, ok, detail) => {
  if (!ok) { failures++; }
  console.log((ok ? 'ok   ' : 'FAIL ') + label + (detail ? ' — ' + detail : ''));
};

const useOf = (el) => {
  const u = el && el.querySelector('use');
  return u ? (u.getAttribute('href') || u.getAttribute('xlink:href') || '').replace('#hsw-i-', '') : '';
};

(async () => {
  const browser = await chromium.launch();

  for (const width of [1440, 390]) {
    const ctx = await browser.newContext({ viewport: { width, height: 900 }, deviceScaleFactor: 2 });
    const page = await ctx.newPage();
    const errors = [];
    page.on('console', (m) => { if (m.type() === 'error') { errors.push(m.text()); } });
    page.on('pageerror', (e) => errors.push(e.message));

    await page.goto(PAGE, { waitUntil: 'load' });
    await page.waitForTimeout(900);

    const info = await page.evaluate(() => {
      const pill = document.querySelector('.hsw-hd__pill');
      const tag = document.querySelector('.hsw-hd__brandtag');
      const cs = pill ? getComputedStyle(pill) : null;
      const icon = (sel) => {
        const el = document.querySelector(sel);
        const u = el && el.querySelector('use');
        return u ? (u.getAttribute('href') || u.getAttribute('xlink:href') || '').replace('#hsw-i-', '') : '';
      };
      const burger = document.querySelector('.hsw-hd__minactions .hsw-hd__iconbtn') || document.querySelector('.hsw-hd__burger');
      const burgerBox = burger ? burger.getBoundingClientRect() : null;
      return {
        tagText: tag ? tag.textContent.trim() : '',
        tagVisible: tag ? getComputedStyle(tag).display !== 'none' && tag.getBoundingClientRect().height > 0 : false,
        burger: icon('.hsw-hd__burger'),
        search: icon('.hsw-hd__minactions .hsw-hd__iconbtn'),
        account: icon('.hsw-hd__account'),
        cart: icon('.hsw-hd__cart'),
        cta: icon('.hsw-hd__cta'),
        gap: cs ? cs.gap : '',
        minH: cs ? cs.minHeight : '',
        padH: cs ? cs.paddingLeft : '',
        pillBg: cs ? cs.backgroundColor : '',
        actSize: cs ? cs.getPropertyValue('--hsw-hd-act-size').trim() : '',
        actBox: burgerBox ? Math.round(burgerBox.width) : 0,
        navColor: (() => {
          const a = document.querySelector('.hsw-hd__minnav .hsw-hd__menu > li > a');
          return a ? getComputedStyle(a).color : '';
        })(),
        ctaBg: (() => {
          const a = document.querySelector('.hsw-hd__cta');
          return a ? getComputedStyle(a).backgroundColor : '';
        })(),
      };
    });

    check(width + ': tagline is printed', info.tagText.length > 3, info.tagText);
    check(width + ': tagline is visible', info.tagVisible);
    check(width + ': menu icon follows the control', info.burger === 'grid_view', info.burger);
    check(width + ': search icon', info.search === 'search', info.search);
    check(width + ': account icon', info.account === 'person', info.account);
    check(width + ': cart icon follows the control', info.cart === 'shopping_bag', info.cart);
    check(width + ': CTA icon follows the control', info.cta === 'arrow_forward', info.cta);
    check(width + ': action size control applies', info.actSize.startsWith('44') && info.actBox === 44,
      info.actSize + ' / box=' + info.actBox);
    check(width + ': pill gap control applies', Math.round(parseFloat(info.gap)) === 20, info.gap);
    check(width + ': pill height control applies', Math.round(parseFloat(info.minH)) === 68, info.minH);
    check(width + ': pill padding control applies', Math.round(parseFloat(info.padH)) === 16, info.padH);
    check(width + ': pill background control applies', info.pillBg === 'rgb(255, 255, 255)', info.pillBg);
    check(width + ': nav link colour applies', /rgb\(15, 118, 110\)/.test(info.navColor), info.navColor);
    check(width + ': CTA colour applies', /rgb\(15, 118, 110\)/.test(info.ctaBg), info.ctaBg);
    check(width + ': no script errors', errors.filter((e) => !/Failed to load resource/.test(e)).length === 0,
      errors.filter((e) => !/Failed to load resource/.test(e))[0] || '');

    await page.screenshot({ path: 'tests/_shot-minimal-' + width + '.png', fullPage: false });
    await ctx.close();
  }

  await browser.close();
  console.log(failures ? 'RESULT: ' + failures + ' problem(s)' : 'RESULT: clean');
  process.exit(failures ? 1 : 0);
})();
