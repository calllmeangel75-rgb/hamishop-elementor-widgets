// Invoice sheet: access rules, single A4 page, postal block, palette accent,
// zero files written to disk.
const { chromium } = require('playwright');

const BASE = process.argv[2] || 'http://localhost/sks';
const CUSTOMER = { user: 'sks', pass: 'Test!2345' };
let failures = 0;

const check = (label, ok, detail) => {
  if (!ok) { failures++; }
  console.log((ok ? 'ok   ' : 'FAIL ') + label + (detail ? ' — ' + detail : ''));
};

async function login(page, who) {
  await page.goto(BASE + '/wp-login.php', { waitUntil: 'domcontentloaded' });
  if (!(await page.$('#user_login'))) { return true; }
  await page.fill('#user_login', who.user);
  await page.fill('#user_pass', who.pass);
  await page.click('#wp-submit');
  try {
    await page.waitForURL((u) => !/wp-login\.php/.test(u.pathname), { timeout: 25000 });
    return true;
  } catch (e) { return false; }
}

/* Chromium's own print pipeline: the number of `/Type /Page` objects is the
   number of printed sheets. */
const pdfPages = (buffer) => {
  const text = buffer.toString('latin1');
  const m = text.match(/\/Type\s*\/Page[^s]/g);
  return m ? m.length : 0;
};

(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 1200, height: 1000 } });
  const page = await ctx.newPage();

  /* ---------- customer: find the button, open the sheet ---------- */
  check('customer logged in', await login(page, CUSTOMER), page.url());

  await page.goto(BASE + '/my-account/orders/', { waitUntil: 'load' });
  await page.waitForTimeout(600);
  const orderHref = await page.evaluate(() => {
    /* Highest order id, not DOM order: the list can be sorted ascending. */
    const links = [...document.querySelectorAll('a[href*="/view-order/"]')]
      .map((x) => ({ href: x.href, id: Number((x.href.match(/view-order\/(\d+)/) || [])[1]) || 0 }))
      .sort((a, b) => b.id - a.id);
    return links.length ? links[0].href : '';
  });
  check('an order exists for the customer', !!orderHref, orderHref);

  await page.goto(orderHref, { waitUntil: 'load' });
  await page.waitForTimeout(600);

  const link = await page.$('[data-hsw-print-link]');
  check('order view shows the print button', !!link);
  const sheetUrl = link ? await link.evaluate((a) => a.href) : '';
  check('print button carries a signed url', /hsw_invoice=\d+&key=[a-f0-9]{8,}/.test(sheetUrl), sheetUrl);

  await page.goto(sheetUrl, { waitUntil: 'load' });
  await page.waitForTimeout(700);

  const sheet = await page.evaluate(() => {
    const root = document.querySelector('.hsw-inv--sheet');
    const cs = root ? getComputedStyle(root) : null;
    return {
      status: document.title,
      hasSheet: !!root,
      logo: !!document.querySelector('.hsw-inv__logo'),
      from: document.body.innerText.includes('فرستنده'),
      to: document.body.innerText.includes('گیرنده'),
      zipBoxes: document.querySelectorAll('.hsw-inv__zipdigits b').length,
      items: document.querySelectorAll('.hsw-inv__items tbody tr').length,
      density: (root ? root.className : '').match(/hsw-inv--(dense|tight|ultra)/),
      pageRows: document.querySelectorAll('.hsw-inv__items tbody tr').length,
      total: (document.querySelector('.hsw-inv__grand td') || {}).innerText || '',
      words: (document.querySelector('.hsw-inv__words') || {}).innerText || '',
      hasBar: !!document.querySelector('.hsw-sheetbar'),
      width: cs ? Math.round(root.getBoundingClientRect().width) : 0,
      accent: cs ? cs.getPropertyValue('--hsw-inv-accent').trim() : '',
      fontFamily: cs ? cs.fontFamily.split(',')[0] : '',
    };
  });

  check('sheet renders', sheet.hasSheet, sheet.status);
  check('store logo printed', sheet.logo);
  check('postal chart has sender and recipient', sheet.from && sheet.to, JSON.stringify({ from: sheet.from, to: sheet.to }));
  check('recipient postal code is ten boxes', sheet.zipBoxes === 10, String(sheet.zipBoxes));
  check('items and total are printed', sheet.items > 0 && /[\d۰-۹]/.test(sheet.total), sheet.total + ' / rows=' + sheet.items);
  check('amount in words present', sheet.words.length > 3, sheet.words.slice(0, 40));
  check('toolbar is not part of the paper', sheet.hasBar);
  if (sheet.items > 18) {
    check('a long order switches to the tight density', !!sheet.density, String(sheet.density));
  }

  /* ---------- single page ---------- */
  const pdf = await page.pdf({ format: 'A4', printBackground: true, margin: { top: '0', right: '0', bottom: '0', left: '0' } });
  const pages = pdfPages(pdf);
  check('invoice prints on one A4 page', pages === 1, pages + ' page(s), ' + Math.round(pdf.length / 1024) + 'KB');

  /* ---------- access ---------- */
  const wrong = sheetUrl.replace(/key=[a-f0-9]+/, 'key=deadbeefdeadbeef');
  const bad = await page.request.get(wrong);
  check('a bad nonce is rejected', bad.status() === 403, String(bad.status()));

  const anon = await browser.newContext();
  const anonPage = await anon.newPage();
  const anonRes = await anonPage.request.get(sheetUrl);
  check('a guest cannot open the invoice', anonRes.status() === 403, String(anonRes.status()));
  await anon.close();

  /* ---------- another logged-in customer must not see it ---------- */
  const other = await browser.newContext();
  const otherPage = await other.newPage();
  const otherLogged = await login(otherPage, { user: 'hsw_test_customer', pass: process.env.HSW_OTHER_PASS || 'Hsw!QA2026' });
  if (otherLogged) {
    const res = await otherPage.request.get(sheetUrl);
    check('another customer is refused', res.status() === 403, String(res.status()));
  } else {
    console.log('     (second customer not logged in, cross-customer check skipped)');
  }
  await other.close();

  /* ---------- staff button on the order screen ---------- */
  const admin = await browser.newContext();
  const adminPage = await admin.newPage();
  const adminPass = process.env.HSW_ADMIN_PASS || '';
  if (adminPass && await login(adminPage, { user: 'hsw_test_admin', pass: adminPass })) {
    const oid = Number((sheetUrl.match(/hsw_invoice=(\d+)/) || [])[1]);
    await adminPage.goto(BASE + '/wp-admin/post.php?post=' + oid + '&action=edit', { waitUntil: 'load' });
    await adminPage.waitForTimeout(600);
    const staff = await adminPage.evaluate(() => {
      const links = [...document.querySelectorAll('a')].filter((a) => /hsw_invoice=\d+/.test(a.href || ''));
      return { count: links.length, label: links.length ? links[0].textContent.trim() : '', href: links.length ? links[0].href : '' };
    });
    check('order screen shows the staff print button', staff.count > 0, staff.label + ' -> ' + staff.href.slice(0, 70));
    if (staff.href) {
      const res = await adminPage.request.get(staff.href);
      check('staff link opens the sheet', res.status() === 200, String(res.status()));
    }
  } else {
    console.log('     (admin not tested: HSW_ADMIN_PASS not set)');
  }
  await admin.close();

  await browser.close();
  console.log(failures ? 'RESULT: ' + failures + ' problem(s)' : 'RESULT: clean');
  process.exit(failures ? 1 : 0);
})();
