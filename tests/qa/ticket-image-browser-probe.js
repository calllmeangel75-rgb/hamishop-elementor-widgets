'use strict';
// Real-browser regression for the v1.11.274 ticket-image fix.
//
// Needs a live site and real credentials, so it only runs when the HSW_QA_*
// variables are set and is not part of CI (same rule as the other probes in
// tests/). It proves the three roles with Chromium cookies, not curl:
//   owner 200, staff 200, another customer 403, guest 403, bad link 403.
//
//   HSW_QA_BASE=https://shop.example
//   HSW_QA_OWNER_USER=... HSW_QA_OWNER_PASS=...
//   HSW_QA_TICKET_ID=1683                 (optional: first ticket is used)
//   HSW_QA_OTHER_USER=... HSW_QA_OTHER_PASS=...   (optional)
//   HSW_QA_ADMIN_USER=... HSW_QA_ADMIN_PASS=...   (optional)

const { chromium } = require('playwright');

const BASE = (process.env.HSW_QA_BASE || '').replace(/\/+$/, '');
const TICKET = (process.env.HSW_QA_TICKET_ID || '').trim();
const OWNER = [process.env.HSW_QA_OWNER_USER, process.env.HSW_QA_OWNER_PASS];
const OTHER = [process.env.HSW_QA_OTHER_USER, process.env.HSW_QA_OTHER_PASS];
const ADMIN = [process.env.HSW_QA_ADMIN_USER, process.env.HSW_QA_ADMIN_PASS];

if (!BASE || !OWNER[0] || !OWNER[1]) {
  console.log('SKIP ticket-image browser probe: set HSW_QA_BASE, HSW_QA_OWNER_USER and HSW_QA_OWNER_PASS.');
  process.exit(0);
}

const failures = [];
const check = (name, ok, detail) => {
  console.log((ok ? 'PASS ' : 'FAIL ') + name + (ok || !detail ? '' : ' — ' + detail));
  if (!ok) { failures.push(name); }
};

async function login(page, user, pass) {
  await page.goto(BASE + '/wp-login.php', { waitUntil: 'domcontentloaded' });
  if (await page.$('#user_login')) {
    await page.fill('#user_login', user);
    await page.fill('#user_pass', pass);
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 30000 }).catch(() => {}),
      page.click('#wp-submit'),
    ]);
  }
  return !(await page.$('#login_error'));
}

async function contextFor(browser, user, pass) {
  const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const page = await context.newPage();
  if (user && pass) {
    const ok = await login(page, user, pass);
    if (!ok) { throw new Error('login failed for ' + user); }
  }
  return { context, page };
}

async function findImageUrl(page) {
  const path = TICKET ? `/my-account/support/${TICKET}/` : '/my-account/support/';
  await page.goto(BASE + path, { waitUntil: 'load' });

  if (!TICKET) {
    const href = await page.evaluate(() => {
      const link = document.querySelector('a[data-hsw-ticket]');
      return link ? link.href : '';
    });
    if (href) { await page.goto(href, { waitUntil: 'load' }); }
  }

  return page.evaluate(() => {
    const img = document.querySelector('img[src*="hsw_ticket_image"]');
    return img ? img.src : '';
  });
}

const describe = (response) => response
  ? `${response.status()} ${response.headers()['content-type'] || ''}`.trim()
  : 'no response';

(async () => {
  const browser = await chromium.launch({ headless: true, ...(process.env.HSW_BROWSER_PATH ? { executablePath: process.env.HSW_BROWSER_PATH } : {}) });
  try {
    /* ---- owner: the image must actually paint ---- */
    const owner = await contextFor(browser, OWNER[0], OWNER[1]);
    const imageUrl = await findImageUrl(owner.page);
    check('ticket page exposes a signed admin-post image URL', /admin-post\.php\?.*action=hsw_ticket_image/.test(imageUrl) && !/\/wp-json\//.test(imageUrl), imageUrl || 'no <img> found');
    if (!imageUrl) { throw new Error('no signed image URL to test'); }

    const loaded = await owner.page.evaluate(async (url) => {
      const img = new Image();
      img.src = url;
      try { await img.decode(); } catch (e) { return { ok: false, width: 0 }; }
      return { ok: true, width: img.naturalWidth };
    }, imageUrl);
    check('owner sees a decoded image in the browser', loaded.ok && loaded.width > 0, JSON.stringify(loaded));

    const ownerResponse = await owner.page.request.get(imageUrl);
    const ownerType = ownerResponse.headers()['content-type'] || '';
    check('owner request answers 200 with an image body', ownerResponse.status() === 200 && /^image\//.test(ownerType), describe(ownerResponse));
    const ownerHeaders = ownerResponse.headers();
    check('image response is not cacheable and is sniff-proof', /no-store|no-cache/.test(ownerHeaders['cache-control'] || '') && ownerHeaders['x-content-type-options'] === 'nosniff', JSON.stringify({ cache: ownerHeaders['cache-control'], nosniff: ownerHeaders['x-content-type-options'] }));

    /* ---- another signed-in customer: 403 ---- */
    if (OTHER[0] && OTHER[1]) {
      const other = await contextFor(browser, OTHER[0], OTHER[1]);
      const response = await other.page.request.get(imageUrl);
      check('another customer gets 403', response.status() === 403, describe(response));
      await other.context.close();
    }

    /* ---- guest: 403 ---- */
    const guest = await contextFor(browser, '', '');
    const guestResponse = await guest.page.request.get(imageUrl);
    check('guest gets 403', guestResponse.status() === 403, describe(guestResponse));
    await guest.context.close();

    /* ---- tampered / expired link: 403 even for the owner ---- */
    const tampered = imageUrl.replace(/([?&]sig=)[0-9a-f]+/, '$1' + '0'.repeat(64));
    const tamperedResponse = await owner.page.request.get(tampered);
    check('tampered signature gets 403', tamperedResponse.status() === 403, describe(tamperedResponse));
    const stale = imageUrl.replace(/([?&]ts=)\d+/, (m, p) => p + String(Math.floor(Date.now() / 1000) - 8 * 24 * 3600));
    const staleResponse = await owner.page.request.get(stale);
    check('link older than seven days gets 403', staleResponse.status() === 403, describe(staleResponse));

    /* ---- shop staff: 200, and the admin screen shows it ---- */
    if (ADMIN[0] && ADMIN[1]) {
      const admin = await contextFor(browser, ADMIN[0], ADMIN[1]);
      const response = await admin.page.request.get(imageUrl);
      check('shop staff gets 200', response.status() === 200 && /^image\//.test(response.headers()['content-type'] || ''), describe(response));
      if (TICKET) {
        await admin.page.goto(`${BASE}/wp-admin/post.php?post=${encodeURIComponent(TICKET)}&action=edit`, { waitUntil: 'load' });
        const adminImage = await admin.page.evaluate(() => {
          const img = document.querySelector('img[src*="hsw_ticket_image"]');
          return img ? { src: img.src, width: img.naturalWidth } : null;
        });
        check('admin edit screen shows the ticket image', !!adminImage && adminImage.width > 0, JSON.stringify(adminImage));
      }
      await admin.context.close();
    }

    await owner.context.close();
  } finally {
    await browser.close();
  }

  if (failures.length) {
    console.error('\n' + failures.length + ' ticket-image check(s) failed.');
    process.exit(1);
  }
  console.log('\nAll ticket-image browser checks passed.');
})().catch((error) => {
  console.error('PROBE ERROR', error && error.message ? error.message : error);
  process.exit(1);
});
