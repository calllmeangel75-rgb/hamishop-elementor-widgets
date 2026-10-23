// Admin UI probe: font, header buttons, ticket editor styling.
const { chromium } = require('playwright');

const BASE = 'http://localhost/sks';
const OUT = 'D:/work-webs/build/hamishop-elementor-widgets/tests/_shots';
const TICKET = process.argv[2] || '13469';

(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
  const page = await ctx.newPage();

  await page.goto(`${BASE}/wp-login.php`, { waitUntil: 'domcontentloaded' });
  await page.fill('#user_login', 'hsw_admin_view');
  await page.fill('#user_pass', 'Admin!2345');
  await page.click('#wp-submit');
  await page.waitForTimeout(2500);
  console.log('login ->', page.url());

  const report = {};

  /* 1. HamiShop dashboard: font + header buttons */
  await page.goto(`${BASE}/wp-admin/admin.php?page=hamishop`, { waitUntil: 'load', timeout: 60000 });
  await page.waitForTimeout(1500);

  report.dashboard = await page.evaluate(() => {
    const body = getComputedStyle(document.body);
    const btns = [...document.querySelectorAll('.hsw-admin-headactions .button')].map((el) => {
      const cs = getComputedStyle(el);
      const r = el.getBoundingClientRect();
      return {
        text: el.textContent.trim(),
        color: cs.color,
        background: cs.backgroundColor,
        border: cs.borderColor,
        w: Math.round(r.width),
        h: Math.round(r.height),
      };
    });
    return {
      bodyFont: body.fontFamily,
      buttons: btns,
      hasAccountPageLink: /مشاهده صفحه حساب/.test(document.body.innerText),
      fonts: [...document.fonts].map((f) => `${f.family} ${f.status}`),
    };
  });

  await page.screenshot({ path: `${OUT}/adm-dashboard.png`, fullPage: false });

  /* 2. Ticket editor */
  await page.goto(`${BASE}/wp-admin/post.php?post=${TICKET}&action=edit`, { waitUntil: 'load', timeout: 60000 });
  await page.waitForTimeout(2000);

  report.ticket = await page.evaluate(() => {
    const cs = (el) => (el ? getComputedStyle(el) : null);
    const info = (el, keys) => {
      const s = cs(el);
      if (!s) return null;
      const o = {};
      keys.forEach((k) => (o[k] = s[k]));
      return o;
    };
    const badge = document.querySelector('.hsw-admin-badge');
    const staff = document.querySelector('.hsw-ticket-msg.is-staff');
    return {
      bodyFont: getComputedStyle(document.body).fontFamily,
      badge: badge
        ? {
            text: badge.textContent.trim(),
            ...info(badge, ['backgroundColor', 'color', 'borderRadius', 'fontWeight', 'fontFamily']),
          }
        : null,
      toolbar: info(document.querySelector('.hsw-ticket-toolbar'), ['display', 'backgroundColor', 'borderRadius']),
      thread: info(document.querySelector('.hsw-ticket-thread'), ['backgroundColor', 'borderRadius', 'maxHeight']),
      msgCount: document.querySelectorAll('.hsw-ticket-msg').length,
      msg: info(document.querySelector('.hsw-ticket-msg'), ['backgroundColor', 'borderRadius', 'padding']),
      staffMsg: info(staff, ['backgroundColor', 'borderColor']),
      reply: info(document.querySelector('.hsw-ticket-reply'), ['borderRadius', 'backgroundColor']),
      attach: document.querySelectorAll('.hsw-ticket-attach img').length,
    };
  });

  await page.screenshot({ path: `${OUT}/adm-ticket.png`, fullPage: true });

  console.log(JSON.stringify(report, null, 2));
  await browser.close();
})().catch((e) => {
  console.error(e);
  process.exit(2);
});
