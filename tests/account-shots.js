// Logs in as the throwaway customer and captures the account screens so the
// modernised fields can be eyeballed.
const { chromium } = require('playwright');

const BASE = 'http://localhost/sks';
const OUT = 'D:/work-webs/build/hamishop-elementor-widgets/tests/_shots';

(async () => {
  const browser = await chromium.launch();

  for (const vp of [
    { name: 'desktop', width: 1440, height: 1000 },
    { name: 'mobile', width: 390, height: 844 },
  ]) {
    const ctx = await browser.newContext({ viewport: { width: vp.width, height: vp.height } });
    const page = await ctx.newPage();

    // Logged out: the auth panel with its brand mark + login fields.
    await page.goto(BASE + '/my-account/', { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1200);
    await page.screenshot({ path: `${OUT}/login-${vp.name}.png`, fullPage: false });

    // Log in.
    await page.fill('#hsw-ac-username', 'hsw_test_customer');
    await page.fill('#hsw-ac-password', 'Test!2345');
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'domcontentloaded' }).catch(() => {}),
      page.evaluate(() => document.querySelector('.hsw-ac-auth__submit').click()),
    ]);
    await page.waitForTimeout(1500);

    await page.goto(BASE + '/my-account/edit-account/', { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1500);
    await page.screenshot({ path: `${OUT}/account-${vp.name}.png`, fullPage: true });

    // Focus state of the first field.
    await page.evaluate(() => {
      const el = document.querySelector('.woocommerce-EditAccountForm input.input-text');
      if (el) { el.focus(); el.scrollIntoView({ block: 'center' }); }
    });
    await page.waitForTimeout(400);
    await page.screenshot({ path: `${OUT}/account-focus-${vp.name}.png`, fullPage: false });

    const metrics = await page.evaluate(() => {
      const input = document.querySelector('.woocommerce-EditAccountForm input.input-text');
      if (!input) return { error: 'no form' };
      const cs = getComputedStyle(input);
      const r = input.getBoundingClientRect();
      return {
        height: Math.round(r.height),
        radius: cs.borderRadius,
        bg: cs.backgroundColor,
        fontSize: cs.fontSize,
        formCols: getComputedStyle(document.querySelector('.woocommerce-EditAccountForm')).gridTemplateColumns,
      };
    });
    console.log(vp.name, JSON.stringify(metrics));

    await ctx.close();
  }

  await browser.close();
})().catch((e) => { console.error(e); process.exit(2); });
