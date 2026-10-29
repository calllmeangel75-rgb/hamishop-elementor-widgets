// Guards the archive filter form: one copy of every field must be submitted,
// never the hidden desktop/sheet duplicate (that used to submit the same name
// twice and let the stale copy win).
const { chromium } = require('playwright');

const BASE = process.argv[2] || 'http://localhost/sks';
let failures = 0;

function check(label, url, expect) {
  const ok = expect(url);
  if (!ok) { failures++; }
  console.log((ok ? 'ok   ' : 'FAIL ') + label + ' → ' + url);
}

/* The form auto-submits 650ms after a change; wait for both the navigation and
 * the tail of that timer, otherwise the next click races a pending submit. */
async function toggle(page, selector) {
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'load', timeout: 20000 }),
    page.click(selector),
  ]);
  await page.waitForLoadState('load');
  await page.waitForTimeout(900);
}

(async () => {
  const browser = await chromium.launch();

  /* ---------- desktop: sidebar switch ---------- */
  {
    const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await ctx.newPage();
    const errors = [];
    page.on('pageerror', (e) => errors.push(e.message));

    await page.goto(BASE + '/shop/', { waitUntil: 'load' });
    await toggle(page, '.hsw-af__side .hsw-af__switchinput[name="instock"]');
    check('desktop  instock on ', page.url(), (u) =>
      (u.match(/instock=1/g) || []).length === 1);

    await toggle(page, '.hsw-af__side .hsw-af__switchinput[name="instock"]');
    check('desktop  instock off', page.url(), (u) => !/instock=/.test(u));

    /* A category checkbox must also travel alone. */
    const cat = await page.$('.hsw-af__side input[type="checkbox"][name^="filter_"]');
    if (cat) {
      await toggle(page, '.hsw-af__side input[type="checkbox"][name^="filter_"]');
      check('desktop  category   ', page.url(), (u) => {
        const name = (u.split('?')[1] || '').split('&').filter((p) => /^filter_/.test(p));
        return name.length === 1 && !/,.*,/.test(name[0]);
      });
    }
    if (errors.length) { failures++; console.log('page errors:', errors); }
    await ctx.close();
  }

  /* ---------- mobile: bottom sheet ---------- */
  {
    const ctx = await browser.newContext({ viewport: { width: 390, height: 844 } });
    const page = await ctx.newPage();
    const errors = [];
    page.on('pageerror', (e) => errors.push(e.message));

    await page.goto(BASE + '/shop/', { waitUntil: 'load' });
    await page.click('[data-hsw-open="hsw-filter"]');
    await page.waitForTimeout(400);
    const sw = await page.$('.hsw-asheet--filter .hsw-af__switchinput[name="instock"]');
    if (!sw) {
      console.log('FAIL mobile: no instock switch inside the sheet');
      failures++;
    } else {
      await sw.click();
      await page.click('.hsw-asheet--filter .hsw-af__apply');
      await page.waitForLoadState('load');
      await page.waitForTimeout(900);
      check('mobile   instock on ', page.url(), (u) =>
        (u.match(/instock=1/g) || []).length === 1);
    }
    if (errors.length) { failures++; console.log('page errors:', errors); }
    await ctx.close();
  }

  await browser.close();
  console.log(failures ? 'RESULT: ' + failures + ' problem(s)' : 'RESULT: clean');
  process.exit(failures ? 1 : 0);
})();
