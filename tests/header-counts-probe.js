// Header category counts must match the archive sidebar, and the cart notice
// focus ring must use the store palette instead of the browser default.
const { chromium } = require('playwright');

const BASE = process.argv[2] || 'http://localhost/sks';
let failures = 0;

const check = (label, ok, detail) => {
  if (!ok) { failures++; }
  console.log((ok ? 'ok   ' : 'FAIL ') + label + (detail ? ' — ' + detail : ''));
};

(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
  const page = await ctx.newPage();
  const errors = [];
  page.on('console', (m) => { if (m.type() === 'error') { errors.push(m.text()); } });
  page.on('pageerror', (e) => errors.push(e.message));

  await page.goto(BASE + '/wp-login.php', { waitUntil: 'domcontentloaded' });
  if (await page.$('#user_login')) {
    await page.fill('#user_login', 'sks');
    await page.fill('#user_pass', 'Test!2345');
    await page.click('#wp-submit');
    await page.waitForURL((u) => !/wp-login\.php/.test(u.pathname), { timeout: 25000 }).catch(() => {});
  }

  await page.goto(BASE + '/shop/', { waitUntil: 'load' });
  await page.waitForTimeout(700);

  const counts = await page.evaluate(() => {
    const parse = (txt) => Number(String(txt || '').replace(/[\u06F0-\u06F9]/g, (d) => String(d.charCodeAt(0) - 0x06F0)).replace(/[^\d]/g, '')) || 0;
    const drawer = {};
    document.querySelectorAll('.hsw-hd__drawercats li a').forEach((a) => {
      const spans = a.querySelectorAll('span');
      if (spans.length > 1) { drawer[spans[0].textContent.trim()] = parse(spans[1].textContent); }
    });
    const sidebar = {};
    document.querySelectorAll('.hsw-af__side .hsw-af__catnode').forEach((node) => {
      const name = (node.querySelector('.hsw-af__catname') || {}).textContent || '';
      const cnt = node.querySelector('.hsw-af__cnt, .hsw-af__badge');
      if (name.trim() && cnt) { sidebar[name.trim()] = parse(cnt.textContent); }
    });
    return { drawer, sidebar };
  });

  const shared = Object.keys(counts.drawer).filter((name) => name in counts.sidebar);
  const mismatched = shared.filter((name) => counts.drawer[name] !== counts.sidebar[name]);
  check(
    'header counts equal the archive sidebar',
    shared.length > 0 && mismatched.length === 0,
    shared.map((n) => n + ':' + counts.drawer[n] + '/' + counts.sidebar[n]).join(', ')
  );

  /* notice focus ring */
  await page.goto(BASE + '/cart/', { waitUntil: 'load' });
  const empty = await page.evaluate(() => !document.querySelector('.hsw-citems__item'));
  if (empty) {
    await page.goto(BASE + '/?add-to-cart=13161&quantity=1', { waitUntil: 'load' });
    await page.goto(BASE + '/cart/', { waitUntil: 'load' });
  }
  await page.waitForTimeout(500);
  const plus = await page.$('.hsw-c-qty__btn[data-dir="plus"]');
  if (plus) {
    await plus.click();
    await page.waitForTimeout(1800);
    const ring = await page.evaluate(() => {
      const n = document.querySelector('.woocommerce-message');
      if (!n) { return null; }
      const cs = getComputedStyle(n);
      return { focused: document.activeElement === n, style: cs.outlineStyle, color: cs.outlineColor, width: cs.outlineWidth };
    });
    check(
      'cart notice focus ring uses the palette',
      !!ring && ring.style === 'solid' && ring.color !== 'rgb(229, 151, 0)',
      ring ? JSON.stringify(ring) : 'no notice'
    );
  } else {
    check('cart notice focus ring uses the palette', false, 'no qty button');
  }

  check('no console errors', errors.length === 0, errors.slice(0, 3).join(' | '));

  await browser.close();
  console.log(failures ? 'RESULT: ' + failures + ' problem(s)' : 'RESULT: clean');
  process.exit(failures ? 1 : 0);
})();
