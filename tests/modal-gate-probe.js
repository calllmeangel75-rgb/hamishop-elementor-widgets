const { chromium } = require('playwright');

async function addAndInspect(page, url, selector) {
  await page.goto(url, { waitUntil: 'load' });
  await page.waitForTimeout(900);
  const btn = await page.$(selector);
  if (!btn) return { error: 'no add button' };
  await btn.click();
  await page.waitForTimeout(1800);
  return page.evaluate(() => ({
    modal: !!document.querySelector('.hsw-modal.is-show'),
    toast: !!document.querySelector('.hsw-toast.is-show'),
    toastText: document.querySelector('.hsw-toast.is-show')?.textContent.trim().slice(0, 40) || null,
  }));
}

(async () => {
  const b = await chromium.launch();
  const ctx = await b.newContext({ viewport: { width: 1440, height: 900 } });
  const p = await ctx.newPage();

  console.log('archive:', JSON.stringify(await addAndInspect(p, 'http://localhost/sks/shop/', '[data-hsw-add]')));
  await p.waitForTimeout(600);
  console.log('product:', JSON.stringify(await addAndInspect(p, 'http://localhost/sks/?p=13192', '[data-hsw-add]')), '(may be no button)');

  await b.close();
})();
