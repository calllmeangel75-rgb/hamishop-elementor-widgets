// Captures the two new admin screens (brand + security).
const { chromium } = require('playwright');

const BASE = 'http://localhost/sks';
const OUT = 'D:/work-webs/build/hamishop-elementor-widgets/tests/_shots';
const SLUG = process.argv[2] || 'manage';

(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 1360, height: 1100 } });
  const page = await ctx.newPage();

  await page.goto(`${BASE}/wp-login.php`, { waitUntil: 'domcontentloaded' });
  await page.fill('#user_login', 'hsw_test_admin');
  await page.fill('#user_pass', 'Admin!2345');
  await page.click('#wp-submit');
  await page.waitForTimeout(2500);
  console.log('after login:', page.url());

  for (const [name, page_slug] of [['security', 'hamishop-security'], ['brand', 'hamishop-brand']]) {
    await page.goto(`${BASE}/${SLUG}/admin.php?page=${page_slug}`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1500);
    await page.screenshot({ path: `${OUT}/admin-${name}.png`, fullPage: true });
    console.log(name, page.url());
  }

  await browser.close();
})().catch((e) => { console.error(e); process.exit(2); });
