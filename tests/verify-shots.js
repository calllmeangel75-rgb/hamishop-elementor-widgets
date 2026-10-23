// Captures the email-verification banner: collapsed, expanded, and after send.
const { chromium } = require('playwright');

const BASE = 'http://localhost/sks';
const OUT = 'D:/work-webs/build/hamishop-elementor-widgets/tests/_shots';

(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const page = await ctx.newPage();

  await page.goto(`${BASE}/wp-login.php`, { waitUntil: 'domcontentloaded' });
  await page.fill('#user_login', 'hsw_shot_test');
  await page.fill('#user_pass', 'Test!2345');
  await page.click('#wp-submit');
  await page.waitForTimeout(2200);

  await page.goto(`${BASE}/my-account/orders/`, { waitUntil: 'load', timeout: 60000 });
  await page.waitForTimeout(1500);

  const banner = page.locator('[data-hsw-verify]').first();
  const count = await banner.count();
  console.log('banner found:', count);
  if (!count) { await browser.close(); process.exit(1); }

  await banner.scrollIntoViewIfNeeded();
  await page.waitForTimeout(300);
  await banner.screenshot({ path: `${OUT}/verify-collapsed.png` });

  await page.evaluate(() => document.querySelector('[data-hsw-verify-open]').click());
  await page.waitForTimeout(500);
  await banner.screenshot({ path: `${OUT}/verify-form.png` });

  await page.evaluate(() => document.querySelector('[data-hsw-verify-send]').click());
  await page.waitForTimeout(1800);
  await banner.screenshot({ path: `${OUT}/verify-code.png` });

  const state = await page.evaluate(() => ({
    stepVisible: !document.querySelector('[data-hsw-verify-step]').hidden,
    message: document.querySelector('[data-hsw-verify-msg]').textContent.trim(),
  }));
  console.log(JSON.stringify(state));

  // Mobile view of the collapsed banner.
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto(`${BASE}/my-account/`, { waitUntil: 'load', timeout: 60000 });
  await page.waitForTimeout(1200);
  const m = page.locator('[data-hsw-verify]').first();
  if (await m.count()) {
    await m.scrollIntoViewIfNeeded();
    await page.waitForTimeout(300);
    await m.screenshot({ path: `${OUT}/verify-mobile.png` });
  }

  await browser.close();
})().catch((e) => { console.error(e); process.exit(2); });
