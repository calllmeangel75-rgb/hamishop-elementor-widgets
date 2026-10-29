// Archive filters: removing one filter and clearing all of them, desktop + mobile.
const { chromium } = require('playwright');

const BASE = process.argv[2] || 'http://localhost/sks';
const SHOP = BASE + '/shop/';
let failures = 0;

const check = (label, ok, detail) => {
  if (!ok) { failures++; }
  console.log((ok ? 'ok   ' : 'FAIL ') + label + (detail ? ' — ' + detail : ''));
};

const params = (url) => {
  const u = new URL(url);
  const out = {};
  u.searchParams.forEach((v, k) => { out[k] = v; });
  return out;
};

/* Auto-submit fires 650ms after a change; wait for the navigation when it
   happens and for the tail of the timer when it does not. */
async function act(page, fn) {
  const nav = page.waitForNavigation({ waitUntil: 'load', timeout: 8000 }).catch(() => null);
  await fn();
  await nav;
  await page.waitForLoadState('load').catch(() => {});
  await page.waitForTimeout(900);
}

async function visible(page, sel) {
  for (const el of await page.$$(sel)) {
    if (await el.isVisible()) { return el; }
  }
  return null;
}

(async () => {
  const browser = await chromium.launch();

  /* ---------------- desktop ---------------- */
  {
    const ctx = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
    const page = await ctx.newPage();
    const errors = [];
    page.on('console', (m) => { if (m.type() === 'error') { errors.push(m.text()); } });
    page.on('pageerror', (e) => errors.push(e.message));

    await page.goto(SHOP, { waitUntil: 'load' });
    await page.waitForTimeout(500);

    const countText = () => page.evaluate(() => {
      const el = document.querySelector('.hsw-at__count');
      return el ? el.textContent.replace(/\s+/g, ' ').trim() : '';
    });
    const baselineCount = await countText();

    await act(page, () => page.click('.hsw-af__side .hsw-af__switchinput[name="instock"]'));
    const withStock = params(page.url());
    check('desktop: instock applied', withStock.instock === '1', JSON.stringify(withStock));

    /* the second filter is an attribute chip (categories are navigation links) */
    const chip = await visible(page, '.hsw-af__side [data-hsw-tax][data-hsw-value]');
    const chipTax = chip ? await chip.evaluate((el) => el.getAttribute('data-hsw-tax') + '=' + el.getAttribute('data-hsw-value')) : '';
    if (chip) {
      await act(page, () => chip.click());
    }
    const both = params(page.url());
    const attrKey = Object.keys(both).find((k) => k.startsWith('filter_'));
    check('desktop: second filter combines', both.instock === '1' && !!attrKey, JSON.stringify(both));

    const tagCount = await page.evaluate(() => document.querySelectorAll('.hsw-at__tag').length);
    check('desktop: one tag per active filter', tagCount >= 2, String(tagCount));

    /* filter, then pick a category: the other filters must survive the jump */
    if (attrKey) {
      const catLink = await page.$('.hsw-af__side .hsw-af__catlink[href*="/product-category/"]');
      if (catLink) {
        await act(page, () => catLink.click());
        const onCategory = params(page.url());
        check('desktop: picking a category keeps the active filters',
          onCategory.instock === '1' && !!onCategory[attrKey] && /\/product-category\//.test(page.url()),
          page.url());
        /* return to the shop for the tag tests */
        await page.goto(SHOP + '?instock=1&' + attrKey + '=' + encodeURIComponent(both[attrKey]), { waitUntil: 'load' });
        await page.waitForTimeout(600);
      }
    }

    /* remove the attribute tag only */
    if (attrKey) {
      const tagLinks = await page.$$('.hsw-at__tag');
      let removed = false;
      for (const link of tagLinks) {
        const label = ((await link.textContent()) || '').replace(/\s+/g, ' ');
        if (chipTax && label.includes(chipTax.split('=')[1].slice(0, 6))) {
          await act(page, () => link.click());
          removed = true;
          break;
        }
      }
      if (!removed && tagLinks.length) {
        await act(page, () => tagLinks[0].click());
      }
      const after = params(page.url());
      check('desktop: removing one filter keeps the other',
        after.instock === '1' && !Object.keys(after).some((k) => k === attrKey),
        JSON.stringify(after));
    }

    /* clear everything from the tags row */
    const clear = await page.$('.hsw-at__tagclear');
    if (clear) {
      await act(page, () => clear.click());
      const after = params(page.url());
      const dirty = Object.keys(after).filter((k) => k === 'instock' || k.startsWith('filter_') || k === 'onsale' || k === 'express' || k.startsWith('min_price') || k.startsWith('max_price'));
      check('desktop: «پاک‌کردن» clears every filter', dirty.length === 0, JSON.stringify(after));
      check('desktop: tags row disappears after clearing',
        (await page.evaluate(() => document.querySelectorAll('.hsw-at__tag').length)) === 0);
      check('desktop: the full catalogue is back after clearing',
        (await countText()) === baselineCount && baselineCount !== '',
        'before=' + baselineCount + ' after=' + (await countText()));
    } else {
      check('desktop: «پاک‌کردن» exists', false, 'no .hsw-at__tagclear');
    }

    /* sidebar «حذف همه» */
    await act(page, () => page.click('.hsw-af__side .hsw-af__switchinput[name="instock"]'));
    check('desktop: instock re-applied', params(page.url()).instock === '1', JSON.stringify(params(page.url())));
    const reset = await page.$('.hsw-af__side .hsw-af__reset');
    if (reset) {
      await act(page, () => reset.click());
      const after = params(page.url());
      check('desktop: sidebar «حذف همه» clears filters', !after.instock, JSON.stringify(after));
    } else {
      check('desktop: sidebar reset exists', false, 'missing .hsw-af__reset');
    }

    check('desktop: no console errors', errors.length === 0, errors.slice(0, 2).join(' | '));
    await ctx.close();
  }

  /* ---------------- mobile sheet ---------------- */
  {
    const ctx = await browser.newContext({ viewport: { width: 390, height: 844 } });
    const page = await ctx.newPage();
    const errors = [];
    page.on('console', (m) => { if (m.type() === 'error') { errors.push(m.text()); } });
    page.on('pageerror', (e) => errors.push(e.message));

    await page.goto(SHOP, { waitUntil: 'load' });
    await page.waitForTimeout(500);

    const openSheet = async () => {
      await page.click('[data-hsw-open="hsw-filter"]');
      await page.waitForTimeout(400);
    };
    const applySheet = async () => {
      const btn = await visible(page, '.hsw-asheet--filter .hsw-af__apply');
      if (!btn) { return; }
      await act(page, () => btn.click());
    };

    await openSheet();
    await page.click('.hsw-asheet--filter .hsw-af__switchinput[name="instock"]');
    await applySheet();
    check('mobile: instock applied from the sheet', params(page.url()).instock === '1', JSON.stringify(params(page.url())));

    await openSheet();
    await page.click('.hsw-asheet--filter .hsw-af__switchinput[name="instock"]');
    await applySheet();
    check('mobile: turning it off in the sheet removes it', !params(page.url()).instock, JSON.stringify(params(page.url())));

    /* two filters, then clear-all from the sheet */
    await openSheet();
    await page.click('.hsw-asheet--filter .hsw-af__switchinput[name="instock"]');
    const mChip = await visible(page, '.hsw-asheet--filter [data-hsw-tax][data-hsw-value]');
    if (mChip) { await mChip.click(); }
    await applySheet();
    const twoFilters = params(page.url());
    check('mobile: two filters submitted once each',
      twoFilters.instock === '1' && Object.keys(twoFilters).some((k) => k.startsWith('filter_')),
      JSON.stringify(twoFilters));

    await openSheet();
    const clearBtn = await visible(page, '.hsw-asheet--filter .hsw-af__clear');
    if (clearBtn) {
      await act(page, () => clearBtn.click());
      const after = params(page.url());
      check('mobile: sheet «حذف همه» clears filters',
        !after.instock && !Object.keys(after).some((k) => k.startsWith('filter_')), JSON.stringify(after));
    } else {
      check('mobile: sheet «حذف همه» exists', false, 'missing .hsw-af__clear');
    }

    check('mobile: no console errors', errors.length === 0, errors.slice(0, 2).join(' | '));
    await ctx.close();
  }

  await browser.close();
  console.log(failures ? 'RESULT: ' + failures + ' problem(s)' : 'RESULT: clean');
  process.exit(failures ? 1 : 0);
})();
