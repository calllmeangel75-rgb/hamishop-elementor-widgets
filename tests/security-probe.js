// Admin URL guard probe: a signed-in administrator must reach the dashboard at
// the custom slug, be redirected there from the old landing page, and keep
// admin-ajax working.
const { chromium } = require('playwright');

const BASE = 'http://localhost/sks';
const SLUG = process.argv[2] || 'manage';

let failures = 0;
function check(label, ok, detail) {
  console.log(`   ${ok ? 'PASS' : 'FAIL'}  ${label}${detail ? '  — ' + detail : ''}`);
  if (!ok) failures++;
}

(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const page = await ctx.newPage();

  // Log in through the real form.
  await page.goto(`${BASE}/wp-login.php`, { waitUntil: 'domcontentloaded' });
  await page.fill('#user_login', 'hsw_test_admin');
  await page.fill('#user_pass', 'Admin!2345');
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded' }).catch(() => {}),
    page.click('#wp-submit'),
  ]);
  await page.waitForTimeout(1200);

  const afterLogin = page.url();
  console.log('   after login →', afterLogin);

  // The custom slug must serve the dashboard.
  const res = await page.goto(`${BASE}/${SLUG}/`, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(800);
  const dash = await page.evaluate(() => ({
    hasWpadmin: !!document.getElementById('wpadminbar'),
    hasMenu: !!document.getElementById('adminmenu'),
    title: document.title,
    url: location.href,
  }));
  check('dashboard loads at the custom slug', res.status() === 200 && dash.hasMenu, `${res.status()} ${dash.url}`);

  // The old landing page redirects a signed-in admin to the custom slug.
  await page.goto(`${BASE}/wp-admin/`, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(500);
  const landed = page.url();
  check('old landing page redirects to the slug', landed.includes(`/${SLUG}/`), landed);

  // A deep admin page still works from the old path (no lockout possible).
  const deep = await page.goto(`${BASE}/wp-admin/options-general.php`, { waitUntil: 'domcontentloaded' });
  const deepOk = await page.evaluate(() => !!document.getElementById('adminmenu'));
  check('deep admin pages still reachable', deep.status() === 200 && deepOk, String(deep.status()));

  // admin-ajax must answer for a signed-in admin too.
  const ajax = await page.evaluate(async () => {
    const r = await fetch('/sks/wp-admin/admin-ajax.php?action=heartbeat', { credentials: 'include' });
    return r.status;
  });
  check('admin-ajax responds', ajax > 0 && ajax !== 404, String(ajax));

  await browser.close();
  console.log(`\n${failures === 0 ? 'ALL CHECKS PASSED' : failures + ' CHECK(S) FAILED'}`);
  process.exit(failures ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(2); });
