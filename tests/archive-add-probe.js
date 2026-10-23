const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch();
  const ctx = await b.newContext({ viewport: { width: 1440, height: 1000 } });
  const p = await ctx.newPage();
  const logs = [];
  p.on('console', m => logs.push('CONSOLE ' + m.type() + ': ' + m.text().slice(0, 200)));
  p.on('pageerror', e => logs.push('PAGEERROR: ' + e.message.slice(0, 200)));
  p.on('response', r => { if (r.url().includes('wc-ajax')) logs.push('AJAX ' + r.status() + ' ' + r.url()); });

  await p.goto('http://localhost/sks/shop/', { waitUntil: 'load', timeout: 60000 });
  await p.waitForTimeout(1500);

  const info = await p.evaluate(() => ({
    buttons: document.querySelectorAll('[data-hsw-add]').length,
    loopButtons: document.querySelectorAll('.hsw-add[data-product_id]').length,
    scripts: [...document.querySelectorAll('script[src]')].map(s => s.src.split('/').pop().split('?')[0]).filter(n => n.startsWith('hsw-')),
    hasArchiveData: typeof window.hswArchiveData,
    hasHswData: typeof window.hswData,
    badge: document.querySelector('[data-hsw-cart-count]')?.textContent,
  }));
  console.log(JSON.stringify(info, null, 2));

  const btn = await p.$('[data-hsw-add]');
  if (btn) {
    await btn.click();
    await p.waitForTimeout(2500);
    const after = await p.evaluate(() => ({
      badge: document.querySelector('[data-hsw-cart-count]')?.textContent,
      badgeData: document.querySelector('[data-hsw-cart-count]')?.getAttribute('data-count'),
      toast: document.querySelector('.hsw-toast')?.textContent || null,
      toastShown: !!document.querySelector('.hsw-toast.is-show'),
      busy: !!document.querySelector('[data-hsw-add].is-busy'),
      added: !!document.querySelector('[data-hsw-add].is-added'),
      icon: document.querySelector('[data-hsw-add] .hsw-ic')?.textContent,
      url: location.href,
    }));
    console.log(JSON.stringify(after, null, 2));
  } else {
    console.log('NO BUTTONS FOUND');
  }
  console.log(logs.join('\n'));
  await b.close();
})();
